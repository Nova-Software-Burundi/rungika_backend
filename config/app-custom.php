<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    */
    'brand_name' => env('APP_NAME', 'Rungika'),
    'brand_tagline' => env('APP_TAGLINE', 'Send & Receive Money Across Africa'),
    'logo_path' => env('APP_LOGO', 'images/logo.png'),
    'support_email' => env('SUPPORT_EMAIL', 'info@rungika.com'),
    'support_phone' => env('SUPPORT_PHONE', '+250 79 123 456'),
    'physical_address' => env('PHYSICAL_ADDRESS', 'Kigali, Rwanda'),
    'auto_email_domain' => env('AUTO_EMAIL_DOMAIN', 'rungika.app'),

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    */
    'default_send_currency' => env('DEFAULT_SEND_CURRENCY', 'USD'),
    'default_payout_currency' => env('DEFAULT_PAYOUT_CURRENCY', 'ZMW'),

    /*
    |--------------------------------------------------------------------------
    | Authentication & OTP
    |--------------------------------------------------------------------------
    */
    'otp_expiry_minutes' => env('OTP_EXPIRY_MINUTES', 10),
    'otp_lock_minutes' => env('OTP_LOCK_MINUTES', 5),
    'twofactor_temp_token_minutes' => env('TWOSTEP_TEMP_TOKEN_MINUTES', 5),
    'twofactor_setup_token_minutes' => env('TWOSTEP_SETUP_TOKEN_MINUTES', 10),
    'otp_bypass_token' => env('OTP_BYPASS_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | External Services
    |--------------------------------------------------------------------------
    */
    'flag_cdn_url' => env('FLAG_CDN_URL', 'https://flagcdn.com/w80'),
    'fines_user_agent' => env('FINES_USER_AGENT', 'Rungika/1.0'),

    /*
    |--------------------------------------------------------------------------
    | Support SLA (minutes)
    |--------------------------------------------------------------------------
    */
    'sla' => [
        'low' => [
            'first_response_minutes' => (int) env('SLA_LOW_FIRST_RESPONSE', 480),
            'resolution_minutes' => (int) env('SLA_LOW_RESOLUTION', 2880),
        ],
        'normal' => [
            'first_response_minutes' => (int) env('SLA_NORMAL_FIRST_RESPONSE', 240),
            'resolution_minutes' => (int) env('SLA_NORMAL_RESOLUTION', 1440),
        ],
        'high' => [
            'first_response_minutes' => (int) env('SLA_HIGH_FIRST_RESPONSE', 60),
            'resolution_minutes' => (int) env('SLA_HIGH_RESOLUTION', 480),
        ],
        'urgent' => [
            'first_response_minutes' => (int) env('SLA_URGENT_FIRST_RESPONSE', 15),
            'resolution_minutes' => (int) env('SLA_URGENT_RESOLUTION', 120),
        ],
    ],

];
