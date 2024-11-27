<?php

function makeLead($data)
{
    // API
    $companyDomain = 'nettbureauasdevelopmentteam';
    $apiUrl = "https://{$companyDomain}.pipedrive.com/api/v1";
    $token = 'sensored :)'; 

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

}