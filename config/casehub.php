<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile API access tokens (minutes)
    |--------------------------------------------------------------------------
    |
    | "Remember me" on the login screen selects the longer lifetime.
    |
    */

    'api_tokens' => [
        'ttl' => (int) env('API_TOKEN_TTL_MINUTES', 60 * 24 * 7),
        'remember_ttl' => (int) env('API_TOKEN_REMEMBER_TTL_MINUTES', 60 * 24 * 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile photos
    |--------------------------------------------------------------------------
    */

    'profile_photo' => [
        'disk' => 'public',
        'directory' => 'profile-photos',
        'max_kb' => 5120, // "JPG or PNG, max 5MB"
    ],

    /*
    |--------------------------------------------------------------------------
    | Case evidence documents
    |--------------------------------------------------------------------------
    |
    | Private disk (not publicly browsable like profile photos) - evidence is
    | only ever served through the authenticated download endpoint.
    |
    */

    'case_documents' => [
        'disk' => 'local',
        'directory' => 'case-documents',
        'max_kb' => 20480, // 20MB per file
    ],

    /*
    |--------------------------------------------------------------------------
    | First Super Admin (used by AdminSeeder)
    |--------------------------------------------------------------------------
    |
    | Local: falls back to a dev login so the panel is usable straight after
    | `php artisan db:seed`. Any other environment: there is NO default; set
    | ADMIN_SEED_PASSWORD (and change it after first login), or skip this and
    | use `php artisan admin:create-super`.
    |
    */

    'seed_admin' => [
        'name' => env('ADMIN_SEED_NAME', 'Super Admin'),
        'email' => env('ADMIN_SEED_EMAIL', 'admin@casehub.test'),
        'mobile' => env('ADMIN_SEED_MOBILE', '9876543210'),
        'password' => env('ADMIN_SEED_PASSWORD'),
    ],

];
