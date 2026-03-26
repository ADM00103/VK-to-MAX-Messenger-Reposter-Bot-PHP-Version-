<?php
/**
 * Получение постов со стены ВК
 */
function fetchVkWall($config, $count = 10, $offset = 0) {
    $url = 'https://api.vk.com/method/wall.get';
    $params = [
        'owner_id' => $config['vk']['group_id'],
        'count' => $count,
        'offset' => $offset,
        'filter' => 'owner',
        'extended' => 0,
        'v' => $config['vk']['api_version'],
        'access_token' => $config['vk']['service_token'],
    ];
    
    $response = file_get_contents($url . '?' . http_build_query($params));
    $data = json_decode($response, true);
    
    if (!isset($data['response']['items'])) {
        throw new Exception('VK API error: ' . ($data['error']['error_msg'] ?? 'Unknown'));
    }
    
    return array_reverse($data['response']['items']); // От старых к новым
}

/**
 * Отправка сообщения в MAX с медиа и кнопками
 */
function sendPostToMax($post, $config) {
    $text = preparePostText($post, $config);
    $attachments = prepareAttachments($post, $config);
    
    $payload = [
        'chatId' => $config['max']['chat_id'],
        'text' => $text,
    ];
    
    // Добавляем кнопки, если включено
    if ($config['bot']['enable_backlink'] && !empty($post['id'])) {
        $payload['markup'] = [
            'inline_keyboard' => [[
                [
                    'text' => '🔗 Открыть в ВК',
                    'url' => "https://vk.com/wall{$config['vk']['group_id']}_{$post['id']}",
                ]
            ]]
        ];
    }
    
    // Если есть фото — отправляем как multipart/form-data
    if (!empty($attachments['photos'])) {
        return sendMultipartMessage($config, $payload, $attachments);
    }
    
    // Обычный текст
    return sendJsonMessage($config, $payload);
}

/**
 * Логирование
 */
function botLog($message, $level = 'info') {
    $config = require __DIR__ . '/config.php';
    $logFile = $config['bot']['storage_path'] . '/logs/bot.log';
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message\n";
    
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    
    // Вывод в консоль при запуске
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}