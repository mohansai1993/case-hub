<?php

return [

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
