<?php
require_once 'config.php';
require_once 'logger.php';

class VKtoMAXReposter {
    private $lastPostId = null;
    private $processedPosts = [];
    
    public function __construct() {
        $this->loadState();
    }
    
    /**
     * Загрузка состояния бота
     */
    private function loadState() {
        $stateFile = __DIR__ . '/state.json';
        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            $this->lastPostId = $state['last_post_id'] ?? null;
            $this->processedPosts = $state['processed_posts'] ?? [];
        }
    }
    
    /**
     * Сохранение состояния бота
     */
    private function saveState() {
        $state = [
            'last_post_id' => $this->lastPostId,
            'processed_posts' => $this->processedPosts,
            'last_check' => time()
        ];
        file_put_contents(__DIR__ . '/state.json', json_encode($state));
    }
    
    /**
     * Получение постов из VK
     */
    private function getVKPosts($count = 10) {
        $url = "https://api.vk.com/method/wall.get";
        $params = [
            'owner_id' => VK_GROUP_ID,
            'count' => $count,
            'access_token' => VK_SERVICE_TOKEN,
            'v' => VK_API_VERSION
        ];
        
        $response = $this->makeRequest($url, $params);
        
        if (isset($response['response']['items'])) {
            return array_reverse($response['response']['items']);
        }
        
        logMessage("Ошибка получения постов VK: " . json_encode($response));
        return [];
    }
    
    /**
     * Скачивание вложения
     */
    private function downloadAttachment($url, $type) {
        $tempFile = tempnam(sys_get_temp_dir(), 'vk_');
        
        $ch = curl_init($url);
        $fp = fopen($tempFile, 'wb');
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        
        $mimeType = mime_content_type($tempFile);
        $extension = $this->getExtensionFromMime($mimeType);
        
        return [
            'path' => $tempFile,
            'mime' => $mimeType,
            'ext' => $extension,
            'type' => $type
        ];
    }
    
    /**
     * Загрузка файла в MAX
     */
    private function uploadToMAX($fileData) {
        // Получаем URL для загрузки
        $uploadUrl = MAX_API_URL . "/messages/upload";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . MAX_BOT_TOKEN
        ]);
        
        // Создаем multipart/form-data
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileData['path']);
        finfo_close($finfo);
        
        $curlFile = new CURLFile($fileData['path'], $mimeType, 'file.' . $fileData['ext']);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $curlFile]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $uploadData = json_decode($response, true);
            return $uploadData['file_id'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Форматирование текста поста
     */
    private function formatPostText($post) {
        $text = $post['text'] ?? '';
        
        // Добавляем информацию о перепосте
        if (isset($post['copy_history'])) {
            $text .= "\n\n📌 Репост:\n" . $post['copy_history'][0]['text'];
        }
        
        // Добавляем ссылку на оригинал
        $postUrl = "https://vk.com/wall" . VK_GROUP_ID . "_" . $post['id'];
        $text .= "\n\n🔗 [Открыть в VK](" . $postUrl . ")";
        
        return $text;
    }
    
    /**
     * Отправка сообщения в MAX
     */
    private function sendToMAX($text, $attachments = []) {
        $url = MAX_API_URL . "/messages/send";
        
        $data = [
            'chat_id' => MAX_CHAT_ID,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];
        
        if (!empty($attachments)) {
            $data['attachments'] = $attachments;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . MAX_BOT_TOKEN,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            logMessage("✅ Пост успешно отправлен в MAX");
            return true;
        } else {
            logMessage("❌ Ошибка отправки в MAX: " . $response);
            return false;
        }
    }
    
    /**
     * Обработка одного поста
     */
    private function processPost($post) {
        $postId = $post['id'];
        
        // Проверяем, обработан ли пост
        if (in_array($postId, $this->processedPosts)) {
            return false;
        }
        
        logMessage("📝 Обработка поста #{$postId}");
        
        $text = $this->formatPostText($post);
        $attachments = [];
        
        // Обрабатываем вложения
        if (isset($post['attachments'])) {
            foreach ($post['attachments'] as $attachment) {
                $type = $attachment['type'];
                $fileUrl = null;
                
                switch ($type) {
                    case 'photo':
                        $sizes = $attachment['photo']['sizes'];
                        $fileUrl = end($sizes)['url'];
                        break;
                    case 'video':
                        $fileUrl = "https://vk.com/video" . $attachment['video']['owner_id'] . "_" . $attachment['video']['id'];
                        break;
                    case 'doc':
                        $fileUrl = $attachment['doc']['url'];
                        break;
                }
                
                if ($fileUrl) {
                    $downloaded = $this->downloadAttachment($fileUrl, $type);
                    $uploaded = $this->uploadToMAX($downloaded);
                    
                    if ($uploaded) {
                        $attachments[] = $uploaded;
                    }
                    
                    // Удаляем временный файл
                    unlink($downloaded['path']);
                }
            }
        }
        
        // Отправляем в MAX
        $success = $this->sendToMAX($text, $attachments);
        
        if ($success) {
            $this->processedPosts[] = $postId;
            // Ограничиваем массив обработанных постов
            if (count($this->processedPosts) > 100) {
                array_shift($this->processedPosts);
            }
            $this->saveState();
            return true;
        }
        
        return false;
    }
    
    /**
     * Основной метод проверки новых постов
     */
    public function checkNewPosts() {
        logMessage("🔍 Проверка новых постов...");
        
        $posts = $this->getVKPosts(MAX_POSTS_PER_CHECK);
        $processed = 0;
        
        foreach ($posts as $post) {
            if ($this->processPost($post)) {
                $processed++;
            }
        }
        
        if ($processed > 0) {
            logMessage("✨ Обработано новых постов: {$processed}");
        } else {
            logMessage("📭 Новых постов не найдено");
        }
        
        return $processed;
    }
    
    /**
     * Заполнение канала последними постами
     */
    public function fillChannel($count) {
        logMessage("📦 Заполнение канала {$count} последними постами...");
        
        $posts = $this->getVKPosts($count);
        $processed = 0;
        
        foreach ($posts as $post) {
            if ($this->processPost($post)) {
                $processed++;
                sleep(1); // Пауза между постами
            }
        }
        
        logMessage("✅ Заполнено постов: {$processed}");
        return $processed;
    }
    
    /**
     * Выполнение HTTP запроса
     */
    private function makeRequest($url, $params) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Получение расширения файла по MIME типу
     */
    private function getExtensionFromMime($mime) {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'text/plain' => 'txt'
        ];
        
        return $map[$mime] ?? 'file';
    }
}

// ==========================================
// Обработка команд из MAX
// ==========================================

function handleCommand($command, $userId) {
    // Проверка прав администратора
    if (!in_array($userId, ADMIN_IDS)) {
        logMessage("⛔ Неавторизованный доступ от ID: {$userId}");
        return "⛔ У вас нет прав для использования этого бота.";
    }
    
    $bot = new VKtoMAXReposter();
    
    switch ($command) {
        case '/status':
            return "🟢 Бот работает\n📊 Статус: активен\n⏱ Последняя проверка: " . date('Y-m-d H:i:s');
            
        case '/check':
            $count = $bot->checkNewPosts();
            return "🔍 Проверено постов: {$count}";
            
        case '/log':
            $logFile = LOG_FILE;
            if (file_exists($logFile)) {
                $logs = file($logFile);
                $lastLogs = array_slice($logs, -15);
                return "📋 Последние 15 логов:\n```\n" . implode('', $lastLogs) . "\n```";
            }
            return "📋 Лог-файл не найден";
            
        case '/stop':
            logMessage("🛑 Бот остановлен администратором");
            return "🛑 Бот остановлен";
            
        default:
            if (preg_match('/^\/fill (\d+)$/', $command, $matches)) {
                $count = min($matches[1], 50);
                $processed = $bot->fillChannel($count);
                return "📦 Заполнено {$processed} постов из {$count}";
            }
            return "🤖 Доступные команды:\n/status - статус бота\n/check - проверить новые посты\n/fill N - заполнить N постов\n/log - показать логи\n/stop - остановить бота";
    }
}

// ==========================================
// Вебхук для получения команд из MAX
// ==========================================

if (php_sapi_name() === 'cli') {
    // Запуск из командной строки
    if ($argc > 1) {
        $command = $argv[1];
        $userId = $argv[2] ?? 0;
        echo handleCommand($command, $userId);
    } else {
        $bot = new VKtoMAXReposter();
        $bot->checkNewPosts();
    }
} else {
    // Запуск как веб-хук
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['message'])) {
        $text = $input['message']['text'];
        $userId = $input['message']['from']['id'];
        
        $response = handleCommand($text, $userId);
        
        header('Content-Type: application/json');
        echo json_encode(['text' => $response]);
    }
}

?>