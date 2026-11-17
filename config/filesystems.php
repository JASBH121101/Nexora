<?php

return [
    'default' => env('FILESYSTEM_DISK', 'public'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
        'product_quarantine' => [
            'driver' => 'local',
            'root' => storage_path('app/product-quarantine'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],
        'private_media' => [
            'driver' => 'local',
            'root' => storage_path('app/private-media'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],
        'legacy_media_archive' => [
            'driver' => 'local',
            'root' => storage_path('app/private-media-archive'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
