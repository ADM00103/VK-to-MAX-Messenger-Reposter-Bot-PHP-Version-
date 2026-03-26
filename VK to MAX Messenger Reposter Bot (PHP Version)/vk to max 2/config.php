<?php
return [
    'vk' => [
        'service_token' => getenv('VK_SERVICE_TOKEN'),
        'group_id' => (int)getenv('VK_GROUP_ID'),
        'api_version' => '5.199',
    ],
    'max' => [
        'bot_token' => getenv('MAX_BOT_TOKEN'),
        'chat_id' => (int)getenv('MAX_CHAT_ID'),
        'server' => getenv('MAX_SERVER') ?: 'https://myteam.mail.ru/bot/v1/',
    ],
    'bot' => [
        'admin_ids' => json_decode(getenv('ADMIN_IDS') ?: '[]', true),
        'check_interval' => (int)(getenv('CHECK_INTERVAL') ?: 60),
        'posts_limit' => (int)(getenv('POSTS_LIMIT') ?: 10),
        'enable_backlink' => filter_var(getenv('ENABLE_BACKLINK'), FILTER_VALIDATE_BOOLEAN),
        'log_level' => getenv('LOG_LEVEL') ?: 'info',
        'storage_path' => __DIR__ . '/storage',
    ],
];