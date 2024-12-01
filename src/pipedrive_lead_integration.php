<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

function makeLead($leadData)
{
    // Initialize Logger
    $logger = new Logger('pipedrive_integration');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));

    try {
        // API credentials
        $pipedriveCompanyDomain = 'nettbureauasdevelopmentteam';
        $pipedriveApiUrl = "https://{$pipedriveCompanyDomain}.pipedrive.com/api/v1";
        $apiToken = 'insert API here';

        // Custom field IDs for leads and persons
        $customFieldIds = [
            'lead' => [
                'housing_type'  => '9cbbad3c5d83d6d258ef27db4d3784b5e0d5fd32',
                'property_size' => '7a275c324d7fbe5ab62c9f05bfbe87dad3acc3ba',
                'deal_type'     => 'cebe4ad7ce36c3508c3722b6e0072c6de5250586',
            ],
            'person' => [
                'contact_type' => 'fd460d099264059d975249b20e071e05392f329d',
            ],
        ];

        // Custom field options mapping
        $customFieldOptions = [
            'housing_type' => [
                'Enebolig'     => 33,
                'Leilighet'    => 34,
                'Tomannsbolig' => 35,
                'Rekkehus'     => 36,
                'Hytte'        => 37,
                'Annet'        => 38,
            ],
            'deal_type' => [
                'Alle strømavtaler er aktuelle' => 39,
                'Fastpris'                      => 40,
                'Spotpris'                      => 41,
                'Kraftforvaltning'              => 42,
                'Annen avtale/vet ikke'         => 43,
            ],
        ];

        // Check if organization already exists
        $organizationSearch = pipedriveAPICall(
            "{$pipedriveApiUrl}/organizations/search",
            $apiToken,
            ['term' => $leadData['organization_name'], 'fields' => 'name'],
            'GET'
        );

        if (!empty($organizationSearch['data']['items'])) {
            $organizationId = $organizationSearch['data']['items'][0]['item']['id'];
            $logger->info('Organization found', ['id' => $organizationId]);
        } else {
            // Create new organization
            $organizationData = [
                'name' => $leadData['organization_name'] ?? 'Default Organization',
            ];

            $logger->info('Creating organization', ['data' => $organizationData]);
            $organizationResponse = pipedriveAPICall("{$pipedriveApiUrl}/organizations", $apiToken, $organizationData);

            if (!$organizationResponse['success']) {
                $logger->error('Failed to create organization', ['error' => $organizationResponse['error']]);
                throw new Exception('Failed to create organization: ' . $organizationResponse['error']);
            }

            $organizationId = $organizationResponse['data']['id'];
            $logger->info('Organization created', ['id' => $organizationId]);
        }

        // Check if person already exists
        $personSearch = pipedriveAPICall(
            "{$pipedriveApiUrl}/persons/search",
            $apiToken,
            ['term' => $leadData['email'], 'fields' => 'email'],
            'GET'
        );

        if (!empty($personSearch['data']['items'])) {
            $personId = $personSearch['data']['items'][0]['item']['id'];
            $logger->info('Person found', ['id' => $personId]);
        } else {
            // Create new person
            $personData = [
                'name'       => $leadData['name'],
                'phone'      => $leadData['phone'],
                'email'      => $leadData['email'],
                'org_id'     => $organizationId,
                $customFieldIds['person']['contact_type'] => $customFieldOptions['contact_type'][$leadData['contact_type']] ?? null,
            ];

            $logger->info('Creating person', ['data' => $personData]);
            $personResponse = pipedriveAPICall("{$pipedriveApiUrl}/persons", $apiToken, $personData);

            if (!$personResponse['success']) {
                $logger->error('Failed to create person', ['error' => $personResponse['error']]);
                throw new Exception('Failed to create person: ' . $personResponse['error']);
            }

            $personId = $personResponse['data']['id'];
            $logger->info('Person created', ['id' => $personId]);
        }

        // Check if lead already exists
        $leadSearch = pipedriveAPICall(
            "{$pipedriveApiUrl}/leads/search",
            $apiToken,
            ['term' => $leadData['name'], 'fields' => 'title'],
            'GET'
        );

        if (!empty($leadSearch['data']['items'])) {
            $logger->info('Lead already exists, skipping creation.');
            return 'Lead already exists, skipping creation.';
        }

        // Create new lead
        $newLeadData = [
            'title'           => 'New lead from integration',
            'person_id'       => $personId,
            'organization_id' => $organizationId,
            $customFieldIds['lead']['housing_type']  => $customFieldOptions['housing_type'][$leadData['housing_type']] ?? null,
            $customFieldIds['lead']['property_size'] => (int) $leadData['property_size'],
            $customFieldIds['lead']['deal_type']     => $customFieldOptions['deal_type'][$leadData['deal_type']] ?? null,
        ];

        $logger->info('Creating lead', ['data' => $newLeadData]);
        $leadResponse = pipedriveAPICall("{$pipedriveApiUrl}/leads", $apiToken, $newLeadData);

        if (!$leadResponse['success']) {
            $logger->error('Failed to create lead', ['error' => $leadResponse['error']]);
            throw new Exception('Failed to create lead: ' . $leadResponse['error']);
        }

        $leadId = $leadResponse['data']['id'];
        $logger->info('Lead created', ['id' => $leadId]);

        return 'Lead created with ID: ' . $leadId;
    } catch (Exception $e) {
        $logger->error('An error occurred', ['exception' => $e]);
        throw $e;
    }
}

function deleteLead($leadId)
{
    global $pipedriveApiUrl, $apiToken, $logger;

    $logger->info('Deleting lead', ['id' => $leadId]);
    $deleteResponse = pipedriveAPICall("{$pipedriveApiUrl}/leads/{$leadId}", $apiToken, null, 'DELETE');

    if ($deleteResponse['success']) {
        $logger->info('Lead deleted successfully', ['id' => $leadId]);
    } else {
        $logger->error('Failed to delete lead', ['error' => $deleteResponse['error']]);
    }
}



function pipedriveAPICall($url, $token, $data = null, $method = 'POST')
{
    $curl = curl_init();

    $options = [
        CURLOPT_URL            => $url . '?api_token=' . $token,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
        ],
    ];

    if ($data && $method !== 'GET') {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $error    = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    return json_decode($response, true);
}

// Load test data from JSON file
try {
    $testDataFile = __DIR__ . '/../test/test_data.json';
    if (!file_exists($testDataFile)) {
        throw new Exception("Test data file not found: {$testDataFile}");
    }

    $testData = json_decode(file_get_contents($testDataFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error decoding test data JSON: " . json_last_error_msg());
    }

    $result = makeLead($testData);
    echo $result . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
