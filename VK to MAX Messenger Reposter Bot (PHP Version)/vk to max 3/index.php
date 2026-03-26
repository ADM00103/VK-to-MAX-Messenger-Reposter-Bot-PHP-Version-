<?php
require_once 'config.php';
require_once 'logger.php';

// Проверка IP
if (!empty(WHITELIST_IPS) && !in_array(getClientIP(), WHITELIST_IPS)) {
    die("Доступ запрещен");
}

$action = $_GET['action'] ?? '';
$result = '';

if ($action == 'check') {
    $bot = new VKtoMAXReposter();
    $count = $bot->checkNewPosts();
    $result = "Проверено постов: {$count}";
    logMessage("Ручная проверка через веб-интерфейс");
} elseif ($action == 'fill' && isset($_GET['count'])) {
    $count = min((int)$_GET['count'], 50);
    $bot = new VKtoMAXReposter();
    $processed = $bot->fillChannel($count);
    $result = "Заполнено {$processed} постов из {$count}";
    logMessage("Заполнение канала через веб-интерфейс: {$count} постов");
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VK to MAX Bot - Управление</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .status {
            background: #f0f0f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            font-weight: 600;
            color: #555;
        }
        
        .status-value {
            color: #333;
            font-family: monospace;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        
        .fill-form {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .fill-form input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
        }
        
        .result {
            background: #e6f7e6;
            border-left: 4px solid #48bb78;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .logs {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            max-height: 400px;
        }
        
        .logs pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🤖 VK to MAX Bot</h1>
                <p>Автоматический репостер из ВКонтакте в MAX Messenger</p>
            </div>
            
            <div class="content">
                <div class="status">
                    <div class="status-item">
                        <span class="status-label">Статус:</span>
                        <span class="status-value">🟢 Активен</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">VK группа:</span>
                        <span class="status-value"><?php echo VK_GROUP_ID; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">MAX канал:</span>
                        <span class="status-value"><?php echo MAX_CHAT_ID; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Последняя проверка:</span>
                        <span class="status-value">
                            <?php 
                            $stateFile = __DIR__ . '/state.json';
                            if (file_exists($stateFile)) {
                                $state = json_decode(file_get_contents($stateFile), true);
                                echo isset($state['last_check']) ? date('Y-m-d H:i:s', $state['last_check']) : 'Никогда';
                            } else {
                                echo 'Никогда';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="button-group">
                    <a href="?action=check" class="btn btn-primary">🔍 Проверить новые посты</a>
                    <div style="position: relative;">
                        <form method="get" class="fill-form">
                            <input type="hidden" name="action" value="fill">
                            <input type="number" name="count" placeholder="Количество постов (1-50)" min="1" max="50" required>
                            <button type="submit" class="btn btn-success">📦 Заполнить канал</button>
                        </form>
                    </div>
                </div>
                
                <?php if ($result): ?>
                <div class="result">
                    ✅ <?php echo htmlspecialchars($result); ?>
                </div>
                <?php endif; ?>
                
                <h3 style="margin-bottom: 15px;">📋 Последние логи</h3>
                <div class="logs">
                    <pre><?php 
                    if (file_exists(LOG_FILE)) {
                        $logs = file(LOG_FILE);
                        $lastLogs = array_slice($logs, -20);
                        echo htmlspecialchars(implode('', $lastLogs));
                    } else {
                        echo "Лог-файл не найден";
                    }
                    ?></pre>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>VK to MAX Bot | Автоматический репостер</p>
        </div>
    </div>
</body>
</</html>