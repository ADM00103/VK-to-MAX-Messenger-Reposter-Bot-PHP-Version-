<?php
// ==========================================
// ⚙️ SETTINGS (НАСТРОЙКИ)
// ==========================================

// ВК
define('VK_SERVICE_TOKEN', 'ВСТАВЬТЕ_ТУТ_СЕРВИСНЫЙ_КЛЮЧ_ВК');
define('VK_GROUP_ID', -174162942); // не забудьте минус
define('VK_API_VERSION', '5.154');

// MAX
define('MAX_BOT_TOKEN', 'ВСТАВЬТЕ_ТУТ_ТОКЕН_БОТА_MAX');
define('MAX_CHAT_ID', -69410493377140); // ID канала (обычно с минусом)
define('MAX_API_URL', 'https://api.max.botapi.com'); // примерный вид, проверьте по документации

// ADMIN (для безопасности)
define('ADMIN_IDS', '12345678,987654321'); 
// список ID через запятую; можно оставить один

// ОБЩЕЕ
define('CHECK_INTERVAL', 30); // пауза между проверками постов (сек)
define('LOG_FILE', __DIR__ . '/data/bot.log');
define('LAST_POST_FILE', __DIR__ . '/data/last_post_id.txt');