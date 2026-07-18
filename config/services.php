<?php

return [

    'api_card_db' => [
        'host' => env('API_CARD_DB_HOST'),
        'port' => env('API_CARD_DB_PORT', 3306),
        'database' => env('API_CARD_DB_DATABASE'),
        'username' => env('API_CARD_DB_USERNAME'),
        'password' => env('API_CARD_DB_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'geetest' => [
        'id' => env('GEETEST_ID'),
        'key' => env('GEETEST_KEY'),
    ],

];
