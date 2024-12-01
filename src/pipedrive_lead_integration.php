<?php
// Include Composer's autoloader for loading dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Import the Monolog Logger classes for logging purposes
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Main function to create a lead in Pipedrive
 * Before running the script, make sure you enter the correct API token.
 */
function makeLead($leadData)
{
    // Initialize Logger for logging activities and debugging
    $logger = new Logger('pipedrive_integration');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));

    try {
        // Pipedrive API credentials
        $pipedriveCompanyDomain = 'nettbureauasdevelopmentteam';
        $pipedriveApiUrl = "https://{$pipedriveCompanyDomain}.pipedrive.com/api/v1";
        // Enter your valid API token here
        $apiToken = 'Enter_your_token_here';

        /**
         * Custom field IDs for leads and persons.
         * These IDs correspond to custom fields you've set up in Pipedrive.
         */
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

        /**
         * Custom field options mapping.
         * This maps the readable option names to their corresponding IDs in Pipedrive.
         */
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
            'contact_type' => [
                'Privat'     => 30,
                'Borettslag' => 31,
                'Bedrift'    => 32,
            ],
        ];

        /**
         * Step 1: Check if the organization already exists in Pipedrive.
         * The search is performed using the organization name provided in $leadData.
         */
        $organizationSearch = pipedriveAPICall(
            "{$pipedriveApiUrl}/organizations/find",
            $apiToken,
            ['term' => $leadData['organization_name'], 'exact_match' => true],
            'GET'
        );

        if (!empty($organizationSearch['data'])) {
            // Organization exists; retrieve its ID
            $organizationId = $organizationSearch['data'][0]['id'];
            $logger->info('Organization found', ['id' => $organizationId]);
            echo "Warning: Organization '{$leadData['organization_name']}' already exists with ID: {$organizationId}.\n";
        } else {
            // Organization does not exist; create a new one
            $organizationData = [
                'name' => $leadData['organization_name'],
            ];

            $logger->info('Creating organization', ['data' => $organizationData]);
            $organizationResponse = pipedriveAPICall(
                "{$pipedriveApiUrl}/organizations",
                $apiToken,
                $organizationData
            );

            // Handle any errors during organization creation
            if (!$organizationResponse['success']) {
                $logger->error('Failed to create organization', ['error' => $organizationResponse['error']]);
                throw new Exception('Failed to create organization: ' . $organizationResponse['error']);
            }

            // Retrieve the new organization's ID
            $organizationId = $organizationResponse['data']['id'];
            $logger->info('Organization created', ['id' => $organizationId]);
        }

        /**
         * Step 2: Check if the person (contact) already exists using the email and phone number.
         * Uses the `/persons/search` endpoint to search by email.
         */
        $personSearch = pipedriveAPICall(
            "{$pipedriveApiUrl}/persons/search",
            $apiToken,
            ['term' => $leadData['email'], 'fields' => 'email'],
            'GET'
        );

        // Log the person search response for debugging
        $logger->debug('Person search response', ['response' => $personSearch]);

        // Initialize person ID as null; will be set if a matching person is found
        $personId = null;
        if (!empty($personSearch['data']['items'])) {
            // Iterate over each potential matching person
            foreach ($personSearch['data']['items'] as $item) {
                $personIdCandidate = $item['item']['id'];

                // Fetch full details of the person to access email and phone arrays
                $personDetailsResponse = pipedriveAPICall(
                    "{$pipedriveApiUrl}/persons/{$personIdCandidate}",
                    $apiToken,
                    null,
                    'GET'
                );

                if ($personDetailsResponse['success']) {
                    $personDetails = $personDetailsResponse['data'];
                    // Extract arrays of emails and phone numbers
                    $emails = array_column($personDetails['email'], 'value');
                    $phones = array_column($personDetails['phone'], 'value');

                    // Normalize input and stored emails and phones for comparison
                    $inputEmail = strtolower(trim($leadData['email']));
                    $inputPhone = preg_replace('/\D/', '', $leadData['phone']); // Remove non-digit characters

                    $emails = array_map('strtolower', array_map('trim', $emails));
                    $phones = array_map(function ($phone) {
                        return preg_replace('/\D/', '', $phone);
                    }, $phones);

                    // Check for matches
                    $emailMatch = in_array($inputEmail, $emails, true);
                    $phoneMatch = in_array($inputPhone, $phones, true);

                    // Log comparison results
                    $logger->debug('Email and phone comparison', [
                        'inputEmail' => $inputEmail,
                        'storedEmails' => $emails,
                        'inputPhone' => $inputPhone,
                        'storedPhones' => $phones,
                        'emailMatch' => $emailMatch,
                        'phoneMatch' => $phoneMatch,
                    ]);

                    if ($emailMatch || $phoneMatch) {
                        // Match found; set person ID and prompt the user
                        $personId = $personIdCandidate;
                        $logger->info('Person found', ['id' => $personId]);
                        echo "Warning: Person '{$leadData['name']}' already exists with ID: {$personId}.\n";
                        if (!promptToContinue()) {
                            return 'Operation aborted by the user.';
                        }
                        break;
                    }
                }
            }
        }

        if (!$personId) {
            // Step 3: Person does not exist; create a new person
            $personData = [
                'name'       => $leadData['name'],
                'phone'      => $leadData['phone'],
                'email'      => $leadData['email'],
                'org_id'     => $organizationId,
                // Map the contact type custom field
                $customFieldIds['person']['contact_type'] => $customFieldOptions['contact_type'][$leadData['contact_type']] ?? null,
            ];

            $logger->info('Creating person', ['data' => $personData]);
            $personResponse = pipedriveAPICall(
                "{$pipedriveApiUrl}/persons",
                $apiToken,
                $personData
            );

            // Handle any errors during person creation
            if (!$personResponse['success']) {
                $logger->error('Failed to create person', ['error' => $personResponse['error']]);
                throw new Exception('Failed to create person: ' . $personResponse['error']);
            }

            // Retrieve the new person's ID
            $personId = $personResponse['data']['id'];
            $logger->info('Person created', ['id' => $personId]);
        }

        /**
         * Step 4: Check if a lead already exists for this person and organization.
         * The check is based on matching title, person ID, and organization ID.
         */
        $leadSearch = pipedriveAPICall(
            "{$pipedriveApiUrl}/leads",
            $apiToken,
            null,
            'GET'
        );

        $leadExists = false;
        if (!empty($leadSearch['data'])) {
            foreach ($leadSearch['data'] as $lead) {
                if (
                    $lead['title'] === 'New lead from integration' &&
                    (int)$lead['person_id'] === (int)$personId &&
                    (int)$lead['organization_id'] === (int)$organizationId
                ) {
                    // Lead exists; set flag and prompt the user
                    $leadExists = true;
                    $logger->info('Duplicate lead found', ['id' => $lead['id']]);
                    echo "Warning: Lead for person ID '{$personId}' already exists with ID: {$lead['id']}.\n";
                    if (!promptToContinue()) {
                        return 'Operation aborted by the user.';
                    }
                    break;
                }
            }
        }

        if (!$leadExists) {
            // Step 5: Lead does not exist; create a new lead
            $newLeadData = [
                'title'           => 'New lead from integration',
                'person_id'       => $personId,
                'organization_id' => $organizationId,
                // Map custom fields for the lead
                $customFieldIds['lead']['housing_type']  => $customFieldOptions['housing_type'][$leadData['housing_type']] ?? null,
                $customFieldIds['lead']['property_size'] => (int)$leadData['property_size'],
                $customFieldIds['lead']['deal_type']     => $customFieldOptions['deal_type'][$leadData['deal_type']] ?? null,
            ];

            $logger->info('Creating lead', ['data' => $newLeadData]);
            $leadResponse = pipedriveAPICall(
                "{$pipedriveApiUrl}/leads",
                $apiToken,
                $newLeadData
            );

            // Handle any errors during lead creation
            if (!$leadResponse['success']) {
                $logger->error('Failed to create lead', ['error' => $leadResponse['error']]);
                throw new Exception('Failed to create lead: ' . $leadResponse['error']);
            }

            // Retrieve the new lead's ID
            $leadId = $leadResponse['data']['id'];
            $logger->info('Lead created', ['id' => $leadId]);

            return 'Lead created with ID: ' . $leadId;
        } else {
            // Lead already exists; skip creation
            return 'Lead already exists, skipped creation.';
        }
    } catch (Exception $e) {
        // Catch any exceptions and log the error
        $logger->error('An error occurred', ['exception' => $e->getMessage()]);
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Function to make API calls to Pipedrive
 */
function pipedriveAPICall($url, $token, $params = null, $method = 'POST')
{
    // Initialize a cURL session
    $curl = curl_init();

    // Build the query string with the API token
    $query = '?api_token=' . $token;
    if ($params && $method === 'GET') {
        // Append parameters to the query string for GET requests
        $query .= '&' . http_build_query($params);
    }

    // Set cURL options
    $options = [
        CURLOPT_URL            => $url . $query,
        CURLOPT_RETURNTRANSFER => true, // Return the response as a string
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
        ],
    ];

    // Include POST fields for non-GET requests
    if ($params && $method !== 'GET') {
        $options[CURLOPT_POSTFIELDS] = json_encode($params);
    }

    // Apply the options to the cURL session
    curl_setopt_array($curl, $options);

    // Execute the API call
    $response = curl_exec($curl);
    $error    = curl_error($curl);
    curl_close($curl); // Close the cURL session

    // Check for errors in the API call
    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    // Decode the JSON response into an associative array
    return json_decode($response, true);
}

/**
 * Function to prompt the user for confirmation
 */
function promptToContinue()
{
    echo "Do you want to continue? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    // Return true if the user inputs 'y' (case-insensitive)
    return strtolower($line) === 'y';
}

// Load test data from a JSON file
try {
    $testDataFile = __DIR__ . '/../test/test_data.json';
    if (!file_exists($testDataFile)) {
        throw new Exception("Test data file not found: {$testDataFile}");
    }

    // Decode the JSON data from the test data file
    $testData = json_decode(file_get_contents($testDataFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error decoding test data JSON: " . json_last_error_msg());
    }

    // Call the main function to create the lead with the test data
    $result = makeLead($testData);
    echo $result . PHP_EOL;
} catch (Exception $e) {
    // Handle any exceptions during the loading of test data
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
