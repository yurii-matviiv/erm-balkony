<?php

// VAPID keys identify this server to browsers' push services. Subscriptions are
// bound to the PUBLIC key, so local and hosting use different pairs (each has its
// own DB). Generate and write a pair with `php artisan push:vapid-keys --write` (or paste the lines printed without --write)
// into that environment's .env. Never commit the private key.
return [
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:' . env('MAIL_FROM_ADDRESS', 'hello@example.com')),
];
