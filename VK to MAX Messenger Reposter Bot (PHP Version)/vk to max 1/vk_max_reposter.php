<?php
require_once __DIR__ . '/config.php';

// Простая запись лога
function log_message($msg) {
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
    echo $line;
}

// Чтение последнего обработанного ID
function get_last_post_id() {
    if (!file_exists(LAST_POST_FILE)) {
        return 0;
    }
    $id = trim(file_get_contents(LAST_POST_FILE));
    return ctype_digit($id) ? (int)$id : 0;
}

// Сохранение последнего обработанного ID
function set_last_post_id($id) {
    file_put_contents(LAST_POST_FILE, (string)$id);
}

// Вызов метода VK API
function vk_api_call($method, $params = []) {
    $params['access_token'] = VK_SERVICE_TOKEN;
    $params['v'] = VK_API_VERSION;

    $url = 'https://api.vk.com/method/' . $method;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    // обходим системные прокси
    curl_setopt($ch, CURLOPT_PROXY, '');
    $response = curl_exec($ch);
    if ($response === false) {
        log_message('VK curl error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        log_message('VK API error: ' . $data['error']['error_msg']);
        return null;
    }
    return $data['response'] ?? null;
}

// Получить последние N постов со стены
function vk_get_last_posts($count = 10) {
    $resp = vk_api_call('wall.get', [
        'owner_id' => VK_GROUP_ID,
        'count'    => $count,
    ]);
    if (!$resp || !isset($resp['items'])) {
        return [];
    }
    // Возвращаем в хронологическом порядке (от старых к новым)
    $items = $resp['items'];
    usort($items, function($a, $b) {
        return $a['date'] <=> $b['date'];
    });
    return $items;
}

// Отправка сообщения в MAX
function max_send_message($chat_id, $text, $buttons = []) {
    $url = MAX_API_URL . '/bot/' . urlencode(MAX_BOT_TOKEN) . '/sendMessage';

    $payload = [
        'chat_id' => $chat_id,
        'text'    => $text,
    ];

    if (!empty($buttons)) {
        $payload['reply_markup'] = [
            'inline_keyboard' => $buttons,
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_PROXY, '');
    $response = curl_exec($ch);
    if ($response === false) {
        log_message('MAX curl error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return json_decode($response, true);
}

// Отправка фото в MAX (как файл/медиа)
function max_send_photo($chat_id, $file_url, $caption = '') {
    // Вариант 1: если MAX поддерживает отправку по URL
    $url = MAX_API_URL . '/bot/' . urlencode(MAX_BOT_TOKEN) . '/sendPhoto';

    $payload = [
        'chat_id' => $chat_id,
        'photo'   => $file_url,
        'caption' => $caption,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_PROXY, '');
    $response = curl_exec($ch);
    if ($response === false) {
        log_message('MAX sendPhoto error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return json_decode($response, true);
}

// Проверка, админ ли это
function is_admin($user_id) {
    $ids = array_map('trim', explode(',', ADMIN_IDS));
    return in_array((string)$user_id, $ids, true);
}

// Формирование текста поста + ссылка "Открыть в VK"
function format_post_text($post) {
    $text = $post['text'] ?? '';
    $post_id = $post['id'];
    $owner_id = $post['owner_id'];
    $vk_link = 'https://vk.com/wall' . $owner_id . '_' . $post_id;
    $text .= "\n\n🔗 Открыть в VK: " . $vk_link;
    return $text;
}

// Отправка одного поста в MAX (текст + медиа)
function send_post_to_max($post) {
    $text = format_post_text($post);

    // 1) Сначала текст + кнопка
    $buttons = [
        [
            [
                'text' => '🔗 Открыть в VK',
                'url'  => 'https://vk.com/wall' . $post['owner_id'] . '_' . $post['id'],
            ]
        ]
    ];
    max_send_message(MAX_CHAT_ID, $text, $buttons);

    // 2) Потом фото
    if (!empty($post['attachments'])) {
        foreach ($post['attachments'] as $att) {
            if ($att['type'] === 'photo') {
                // Берём максимальный по размеру
                $sizes = $att['photo']['sizes'];
                usort($sizes, function($a, $b) {
                    return ($a['width'] * $a['height']) <=> ($b['width'] * $b['height']);
                });
                $best = end($sizes);
                $url = $best['url'] ?? null;
                if ($url) {
                    max_send_photo(MAX_CHAT_ID, $url);
                }
            } elseif ($att['type'] === 'video') {
                // В ВК для видео обычно нужно либо ссылку, либо заранее получать player
                $owner_id = $att['video']['owner_id'];
                $video_id = $att['video']['id'];
                $vk_video_link = 'https://vk.com/video' . $owner_id . '_' . $video_id;
                max_send_message(MAX_CHAT_ID, '🎬 Видео: ' . $vk_video_link);
            } elseif ($att['type'] === 'link') {
                $link_url = $att['link']['url'] ?? '';
                if ($link_url) {
                    max_send_message(MAX_CHAT_ID, '🔗 Ссылка: ' . $link_url);
                }
            }
        }
    }

    log_message('Post ' . $post['id'] . ' sent to MAX');
}

// Основная проверка новой ленты
function check_new_posts($limit = 10) {
    $last_id = get_last_post_id();
    $posts = vk_get_last_posts($limit);

    $to_send = [];
    foreach ($posts as $post) {
        if ($post['id'] > $last_id) {
            $to_send[] = $post;
        }
    }

    if (empty($to_send)) {
        log_message('No new posts');
        return;
    }

    // Отправляем по порядку (от старых к новым)
    foreach ($to_send as $p) {
        send_post_to_max($p);
        set_last_post_id($p['id']);
    }

    log_message('Sent ' . count($to_send) . ' new posts');
}

// Обработка команд от админа в MAX (webhook или polling — зависит от того, как вы настраиваете)
function handle_admin_command($from_id, $text) {
    if (!is_admin($from_id)) {
        log_message('⛔ Unauthorized access attempt (ID: ' . $from_id . ')');
        max_send_message($from_id, '⛔ У вас нет прав для управления этим ботом.');
        return;
    }

    $text = trim($text);

    if ($text === '/status') {
        max_send_message($from_id, '🟢 Бот запущен и работает.');
    } elseif ($text === '/check') {
        max_send_message($from_id, '🔎 Проверяю новые посты...');
        check_new_posts(10);
        max_send_message($from_id, '✅ Проверка завершена.');
    } elseif (strpos($text, '/fill') === 0) {
        // /fill 10
        $parts = explode(' ', $text);
        $n = isset($parts[1]) ? (int)$parts[1] : 5;
        if ($n <= 0) $n = 5;
        max_send_message($from_id, '📥 Загружаю последние ' . $n . ' постов...');
        $posts = vk_get_last_posts($n);
        foreach ($posts as $p) {
            send_post_to_max($p);
            set_last_post_id($p['id']);
        }
        max_send_message($from_id, '✅ Загрузка завершена.');
    } elseif ($text === '/stop') {
        max_send_message($from_id, '⛔ Останавливаю бота (скрипт будет завершён).');
        log_message('Bot stopped by admin ' . $from_id);
        exit;
    } elseif ($text === '/log') {
        if (file_exists(LOG_FILE)) {
            $log = file(LOG_FILE);
            $last = array_slice($log, -15);
            max_send_message($from_id, "📋 Последние записи лога:\n" . implode('', $last));
        } else {
            max_send_message($from_id, 'Лог пока пуст.');
        }
    } else {
        max_send_message($from_id, "Неизвестная команда. Доступно: /status, /check, /fill N, /log, /stop");
    }
}

// === Точка входа ======================================

// Вариант 1: запускаем как «демон» в CLI и каждые N секунд проверяем стену
if (php_sapi_name() === 'cli') {
    log_message('=== 🤖 VK TO MAX REPOSTER (PHP) STARTED ===');
    while (true) {
        check_new_posts(10);
        sleep(CHECK_INTERVAL);
    }
    exit;
}

// Вариант 2: обработка webhook от MAX (если вы настроили webhook-URL на этот файл)
$body = file_get_contents('php://input');
if ($body) {
    $update = json_decode($body, true);
    // Тут нужно свериться с реальной структурой webhook от MAX
    // Примерно:
    $from_id = $update['from']['id'] ?? null;
    $text    = $update['message']['text'] ?? '';

    if ($from_id && $text) {
        handle_admin_command($from_id, $text);
    }
}