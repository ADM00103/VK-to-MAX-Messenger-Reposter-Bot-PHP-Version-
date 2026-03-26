<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';

$config = require __DIR__ . '/config.php';
$lastPostId = getLastPostId($config); // Читаем из storage/last_post_id.txt

botLog("🤖 VK to MAX Reposter запущен");

while (true) {
    try {
        // 1. Проверяем новые посты в ВК
        $newPosts = fetchNewVkPosts($config, $lastPostId);
        
        // 2. Репостим в порядке возрастания (хронология)
        foreach ($newPosts as $post) {
            $result = sendPostToMax($post, $config);
            if ($result) {
                $lastPostId = max($lastPostId, $post['id']);
                saveLastPostId($lastPostId);
            }
        }
        
        // 3. Обрабатываем команды админов (Long Poll MAX API)
        processAdminCommands($config);
        
    } catch (Exception $e) {
        botLog("❌ Ошибка: " . $e->getMessage(), 'error');
    }
    
    sleep($config['bot']['check_interval']);
}