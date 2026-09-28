<?php

return [

    // "log" (dev, writes to storage/logs instead of calling Firebase) or
    // "firebase" (real delivery via the service account below).
    'driver' => env('PUSH_DRIVER', 'firebase'),

    'credentials' => base_path(
        env(
            'GOOGLE_CREDENTIALS_PATH',
            'storage/app/firebase/service-account.json'
        )
    ),

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'messaging_url' => env('FIREBASE_URL'),

    'messaging_scope' => env(
        'FIREBASE_SCOPE_MESSAGE_URL',
        'https://www.googleapis.com/auth/firebase.messaging'
    ),

];
