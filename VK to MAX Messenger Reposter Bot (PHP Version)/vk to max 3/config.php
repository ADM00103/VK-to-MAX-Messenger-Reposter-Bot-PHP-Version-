<?php
// ==========================================
// ⚙️ НАСТРОЙКИ БОТА
// ==========================================

// VK API настройки
define('VK_SERVICE_TOKEN', 'ВАШ_СЕРВИСНЫЙ_ТОКЕН_VK');
define('VK_GROUP_ID', -123456789); // ID группы с минусом
define('VK_API_VERSION', '5.131');

// MAX (VK Teams) настройки
define('MAX_BOT_TOKEN', 'ВАШ_ТОКЕН_БОТА_MAX');
define('MAX_API_URL', 'https://api.max.ru/v1'); // URL вашего MAX API
define('MAX_CHAT_ID', '-123456789'); // ID канала

// Настройки безопасности
define('ADMIN_IDS', [123456789]); // Ваши ID в MAX (числа)
define('WHITELIST_IPS', []); // IP для доступа к веб-интерфейсу

// Настройки бота
define('CHECK_INTERVAL', 60); // Интервал проверки (секунды)
define('MAX_POSTS_PER_CHECK', 10); // Максимум постов за проверку
define('LOG_FILE', __DIR__ . '/logs/bot.log');

// ==========================================
?>