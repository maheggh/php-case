<?php

function makeLead($data)
{
    // API
    $companyDomain = 'nettbureauasdevelopmentteam';
    $apiUrl = "https://{$companyDomain}.pipedrive.com/api/v1";
    $token = 'cc8b7ad043662da5fc83b3359789daea6cf21c8a'; 

    // Felt for leads og personer
    $fieldElements = [
        'lead' => [
            'housing_type'  => '9cbbad3c5d83d6d258ef27db4d3784b5e0d5fd32', 
            'property_size' => '7a275c324d7fbe5ab62c9f05bfbe87dad3acc3ba', 
            'comment'       => '479370d7514958b2b4b4049c37be492f357fe7d8', 
            'deal_type'     => 'cebe4ad7ce36c3508c3722b6e0072c6de5250586', 
        ],
        'person' => [
            'contact_type' => 'fd460d099264059d975249b20e071e05392f329d', 
        ],
    ];

    // Alternative IDer
    $optionMapping = [
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

    // Opprett en organisasjon
    $organizationData = [
        'name' => $data['organization_name'] ?? 'Default Organization',
    ];

    $organizationResponse = pipedriveAPICall("{$apiUrl}/organizations", $token, $organizationData);

    if (!$organizationResponse['success']) {
        throw new Exception('Failed to create organization: ' . $organizationResponse['error']);
    }

    $organizationId = $organizationResponse['data']['id'];

    // Opprett en person knyttet til organisasjonen
    $personData = [
        'name'       => $data['name'],
        'phone'      => $data['phone'],
        'email'      => $data['email'],
        'org_id'     => $organizationId,;
        // Sett egendefinert felt for kontakttype
        $fieldElements['person']['contact_type'] => $optionMapping['contact_type'][$data['contact_type']] ?? null,
    ];

    $personResponse = pipedriveAPICall("{$apiUrl}/persons", $token, $personData);

    if (!$personResponse['success']) {
        throw new Exception('Failed to create person: ' . $personResponse['error']);
    }

    $personId = $personResponse['data']['id'];

    // Opprett en lead knyttet til både personen og organisasjonen
    $leadData = [
        'title'           => 'Ny lead fra integrasjon',
        'person_id'       => $personId,
        'organization_id' => $organizationId,

        // Sett egendefinerte felt for lead
        $fieldElements['lead']['housing_type']  => $optionMapping['housing_type'][$data['housing_type']] ?? null,
        $fieldElements['lead']['property_size'] => $data['property_size'],
        $fieldElements['lead']['deal_type']     => $optionMapping['deal_type'][$data['deal_type']] ?? null,
    ];

    $leadResponse = pipedriveAPICall("{$apiUrl}/leads", $token, $leadData);

    if (!$leadResponse['success']) {
        throw new Exception('Failed to create lead: ' . $leadResponse['error']);
    }

    return 'Lead opprettet med ID: ' . $leadResponse['data']['id'];
}

function pipedriveAPICall($url, $token, $data)
{
    // start cURL-sesjonen med init
    $curl = curl_init();

    // Sett cURL-alternativer
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url . '?api_token=' . $token,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    // Utfør cURL-forespørselen
    $response = curl_exec($curl);
    $error    = curl_error($curl);

    // Lukk cURL-sesjonen
    curl_close($curl);

    // Hvis feil oppstår, sende en feilkode
    if ($error) {
        return ['success' => false, 'error' => $error];
    } else {
        return json_decode($response, true);
    }
}

// Dette er en test med noen eksempeldata
try {
    $testData = [
        'name'          => 'Ola Nordmann',
        'phone'         => '12345678',
        'email'         => 'ola.nordmann@online.no',
        'housing_type'  => 'Enebolig',
        'property_size' => 160,
        'deal_type'     => 'Spotpris',
        'contact_type'  => 'Privat',
    ];

    $result = makeLead($testData);
    echo $result . PHP_EOL;
} catch (Exception $e) {
    echo 'Feil: ' . $e->getMessage() . PHP_EOL;
}
