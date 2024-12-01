<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

function fetchDetails($url, $apiToken, $logger)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url . '?api_token=' . $apiToken,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($curl);
    $error    = curl_error($curl);
    curl_close($curl);

    if ($error) {
        $logger->error('Failed to fetch details', ['error' => $error]);
        throw new Exception("Failed to fetch details: {$error}");
    }

    $data = json_decode($response, true);
    if ($data['success'] !== true) {
        $logger->error('Failed to fetch details', ['response' => $data]);
        throw new Exception("Failed to fetch details: " . json_encode($data));
    }

    return $data['data'];
}

function fetchLeadDetails($leadId, $apiUrl, $apiToken)
{
    $logger = new Logger('fetch_lead');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));

    try {
        // Fetch lead details
        $leadUrl = "{$apiUrl}/leads/{$leadId}";
        $lead = fetchDetails($leadUrl, $apiToken, $logger);

        // Fetch related person details
        $person = null;
        if (isset($lead['person_id'])) {
            $personUrl = "{$apiUrl}/persons/{$lead['person_id']}";
            $person = fetchDetails($personUrl, $apiToken, $logger);
        }

        // Fetch related organization details
        $organization = null;
        if (isset($lead['organization_id'])) {
            $organizationUrl = "{$apiUrl}/organizations/{$lead['organization_id']}";
            $organization = fetchDetails($organizationUrl, $apiToken, $logger);
        }

        // Combine all details
        return [
            'lead'         => $lead,
            'person'       => $person,
            'organization' => $organization,
        ];
    } catch (Exception $e) {
        $logger->error('An error occurred', ['exception' => $e->getMessage()]);
        throw $e;
    }
}

// Fetch lead ID from the command line argument
if ($argc < 2) {
    echo "Usage: php fetch_lead.php <lead_id>\n";
    exit(1);
}

$leadId = $argv[1];

// Pipedrive API details
$apiUrl = "https://nettbureauasdevelopmentteam.pipedrive.com/api/v1";
$apiToken = 'enter_api_token_here';

try {
    $leadDetails = fetchLeadDetails($leadId, $apiUrl, $apiToken);
    echo "Complete Lead Details:\n";
    print_r($leadDetails);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
