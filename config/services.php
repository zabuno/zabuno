<?php

return [

    /*
     * MAILGUN — `docs/93` (P0-06).
     *
     * Değerler ORTAMDAN gelir; bu dosyaya hiçbir anahtar yazılmaz. Bir
     * anahtarın depoya girmesi, onu gören herkese vermek demektir ve
     * geçmişten silmek pratikte imkânsızdır. Bir test bunu kontrol eder.
     *
     * `endpoint` açıkça yapılandırılabilir: Mailgun'un ABD ve AB uçları
     * ayrıdır (`api.mailgun.net` / `api.eu.mailgun.net`) ve yanlış uç,
     * "kimlik doğrulanamadı" olarak görünen bir bölge hatası verir.
     */
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'iyzico' => [
        'sandbox' => [
            'api_key' => env('IYZICO_SANDBOX_API_KEY', ''),
            'secret_key' => env('IYZICO_SANDBOX_SECRET_KEY', ''),
            'base_url' => env('IYZICO_SANDBOX_BASE_URL', ''),
            'profile' => [
                'buyer' => [
                    'id' => env('IYZICO_SANDBOX_PROFILE_BUYER_ID'),
                    'name' => env('IYZICO_SANDBOX_PROFILE_BUYER_NAME'),
                    'surname' => env('IYZICO_SANDBOX_PROFILE_BUYER_SURNAME'),
                    'email' => env('IYZICO_SANDBOX_PROFILE_BUYER_EMAIL'),
                    'identity_number' => env('IYZICO_SANDBOX_PROFILE_BUYER_IDENTITY_NUMBER'),
                    'registration_address' => env('IYZICO_SANDBOX_PROFILE_BUYER_REGISTRATION_ADDRESS'),
                    'city' => env('IYZICO_SANDBOX_PROFILE_BUYER_CITY'),
                    'country' => env('IYZICO_SANDBOX_PROFILE_BUYER_COUNTRY'),
                    'zip_code' => env('IYZICO_SANDBOX_PROFILE_BUYER_ZIP_CODE'),
                    'ip' => env('IYZICO_SANDBOX_PROFILE_BUYER_IP'),
                ],
                'billing_address' => [
                    'contact_name' => env('IYZICO_SANDBOX_PROFILE_BILLING_CONTACT_NAME'),
                    'city' => env('IYZICO_SANDBOX_PROFILE_BILLING_CITY'),
                    'country' => env('IYZICO_SANDBOX_PROFILE_BILLING_COUNTRY'),
                    'address' => env('IYZICO_SANDBOX_PROFILE_BILLING_ADDRESS'),
                    'zip_code' => env('IYZICO_SANDBOX_PROFILE_BILLING_ZIP_CODE'),
                ],
                'shipping_address' => [
                    'contact_name' => env('IYZICO_SANDBOX_PROFILE_SHIPPING_CONTACT_NAME'),
                    'city' => env('IYZICO_SANDBOX_PROFILE_SHIPPING_CITY'),
                    'country' => env('IYZICO_SANDBOX_PROFILE_SHIPPING_COUNTRY'),
                    'address' => env('IYZICO_SANDBOX_PROFILE_SHIPPING_ADDRESS'),
                    'zip_code' => env('IYZICO_SANDBOX_PROFILE_SHIPPING_ZIP_CODE'),
                ],
            ],
        ],
    ],

];
