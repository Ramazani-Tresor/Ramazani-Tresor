<?php

return [
    'backup' => [
        // Clé AES-256 (32 octets) en base64 : php artisan karo:backup-key. À conserver HORS du serveur.
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        'path' => env('BACKUP_PATH', storage_path('app/private/backups')),
        'secondary_path' => env('BACKUP_SECONDARY_PATH'),
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', 'mysql'),
    ],
    // Modules activables
    'demo_mode' => env('KARO_DEMO', false),
];
