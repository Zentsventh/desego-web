<?php

return [
    'mail' => [
        'app_url' => env('APP_URL', 'http://localhost:8000'),
        'from_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'DESEGO CEVICHERÍA'),
        'smtp_host' => env('MAIL_HOST', ''),
        'smtp_port' => (int) env('MAIL_PORT', 587),
        'smtp_encryption' => env('DESEGO_SMTP_ENCRYPTION', 'tls'),
        'smtp_username' => env('MAIL_USERNAME', ''),
        'smtp_password' => env('MAIL_PASSWORD', ''),
        'allow_local_debug_link' => env('DESEGO_ALLOW_LOCAL_RESET_LINK', false),
    ],
    'integration_environment' => [
        'DESEGO_SMTP_HOST' => env('MAIL_HOST'),
        'DESEGO_WHATSAPP_TOKEN' => env('DESEGO_WHATSAPP_TOKEN'),
        'DESEGO_MAPS_API_KEY' => env('DESEGO_MAPS_API_KEY'),
        'DESEGO_PAYMENT_SECRET' => env('DESEGO_PAYMENT_SECRET'),
        'DESEGO_WEBHOOK_SECRET' => env('DESEGO_WEBHOOK_SECRET'),
    ],
    'backup' => [
        'encryption_key_hex' => env('DESEGO_BACKUP_KEY', ''),
        'storage_dir' => storage_path('app/private/desego/backups'),
        'redundant_dir' => env('DESEGO_BACKUP_REDUNDANT_DIR', ''),
        'max_dump_bytes' => 67108864,
    ],
];
