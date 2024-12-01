## PHP Create a lead in pipedrive

This script allows you to create a lead in pipedrive by entering org, personal data and other relevant information. When you run the script with a valid api_token a lead is created in the database.

## Requirements

* **PHP 7.4** or higher
* **Composer** for dependency management
* API key for pipedrive

## How to install

* Clone the repository
  "git **clone** https://github.com/maheggh/php-case.git" or extract the zip file and
  cd php-case
* replace the placeholder API token in src/pipedrive_lead_integration.php

  **$apiToken** = **'enter_api_token_here'**;

## How to test

* Open test/test_data.json and enter your test data
* run the script by "cd src" and run "php pipedrive_lead_integration.php"
* If the org or person is already registered you will get a prompt (org/person already exists, do you want to continue? (y/n)
* If you want to see the lead you have just created you can cd test and run "php fetch_lead.php"
