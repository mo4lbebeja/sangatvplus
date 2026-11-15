<?php

return [
    // Telegram alerts & offsite backup (bot from @BotFather, chat id of the group/user that receives them).
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'alerts' => [
        // Extra recipients besides Profil Hotel → "Email laporan".
        'emails' => array_filter(array_map('trim', explode(',', (string) env('ALERT_EMAILS', '')))),
        'channel_failed_checks' => (int) env('ALERT_CHANNEL_FAILED_CHECKS', 3), // ×10 min
        'tv_offline_hours' => (int) env('ALERT_TV_OFFLINE_HOURS', 24),
    ],

    'backup' => [
        // Encrypts every backup (AES-256). Keep it outside the server too: without it backups cannot be restored.
        'password' => env('BACKUP_PASSWORD'),
        'keep' => (int) env('BACKUP_KEEP', 14),
        // Offsite copy to cloud storage with rclone (e.g. Google Drive "gdrive:SangaTV-Backup"); empty = off.
        'rclone_remote' => env('BACKUP_RCLONE_REMOTE'),
        'rclone_config' => env('BACKUP_RCLONE_CONFIG', storage_path('app/private/rclone.conf')),
        'rclone_keep_days' => (int) env('BACKUP_RCLONE_KEEP_DAYS', 60),
    ],

    // Checked-out guests are anonymised after this many days (UU PDP: keep personal data only as long as needed).
    'guest_retention_days' => (int) env('GUEST_RETENTION_DAYS', 90),
];
