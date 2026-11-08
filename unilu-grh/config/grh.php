<?php

return [
    'backup' => [
        // Clé AES-256 (32 octets) en base64 : php artisan grh:backup-key. À conserver HORS du serveur.
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        'path' => env('BACKUP_PATH') ?: storage_path('app/private/backups'),
        'secondary_path' => env('BACKUP_SECONDARY_PATH') ?: null,
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', 'mysql'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
    ],
    // Préfixe des matricules attribués à l'engagement (ex. UNILU-2026-0001)
    'matricule_prefix' => env('GRH_MATRICULE_PREFIX', 'UNILU'),
    // Notifications par courrier électronique (le canal interne est toujours actif)
    'mail_notifications' => env('GRH_MAIL_NOTIFICATIONS', true),
    // Alertes : nombre de jours avant l'échéance d'un mandat ou d'une situation
    'alert_days_mandate' => (int) env('GRH_ALERT_DAYS_MANDATE', 90),
    'alert_days_situation' => (int) env('GRH_ALERT_DAYS_SITUATION', 7),
    'demo_mode' => env('GRH_DEMO', false),
];
