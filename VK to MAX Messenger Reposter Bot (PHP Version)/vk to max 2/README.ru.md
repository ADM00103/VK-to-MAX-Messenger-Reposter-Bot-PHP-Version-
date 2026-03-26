🤖 VK to MAX Messenger Reposter Bot (PHP Version)
Полноценный аналог оригинального бота на PHP, который автоматически репостит контент из группы ВКонтакте в канал мессенджера MAX (VK Teams).
📁 Структура проекта
vk-max-reposter-php/
├── bot.php                 # Основной скрипт бота
├── config.php              # Файл конфигурации (настройки)
├── composer.json           # Зависимости PHP
├── functions.php           # Вспомогательные функции
├── storage/
│   ├── last_post_id.txt    # ID последнего обработанного поста
│   └── logs/               # Папка для логов
├── README.md               # Документация (эта инструкция)
└── .env.example            # Шаблон переменных окружения

🚀 Функционал
Функция
Описание
✅ Умная очередь
Проверяет последние 10 постов, репостит все пропущенные в хронологическом порядке
✅ Медиа-поддержка
Скачивает и загружает фото (до 10 в посте), видео, документы
✅ Интерактивные кнопки
Добавляет кнопку «🔗 Открыть в ВК» к каждому сообщению
✅ Админ-панель
Управление через личные сообщения: /status, /check, /log, /stop
✅ Безопасность
Система белых списков (реагирует только на авторизованных админов)
✅ Backfill
Команда /fill 10 мгновенно загружает последние N постов в пустой канал
✅ Прокси-фикс
Автоматическое обхождение системных прокси на Windows
✅ Логирование
Запись всех действий в файл storage/logs/bot.log
🛠️ Установка и настройка
# Требуется:
# • PHP 7.4 или выше
# • Расширения: curl, json, mbstring, fileinfo
# • Composer (для установки зависимостей)

# Проверка версии PHP:
php -v

# Установка Composer (если нет):
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer



# Клонируем репозиторий (или создаём файлы вручную)
git clone https://github.com/YOUR_USERNAME/vk-max-reposter-php.git
cd vk-max-reposter-php

# Устанавливаем зависимости через Composer
composer install

# Создаём папки для хранения данных
mkdir -p storage/logs
chmod 755 storage storage/logs


Сбор секретных ключей 🔑
Ключ
Где получить
Пример
VK_SERVICE_TOKEN
dev.vk.com → Создать приложение → Настройки → Сервисный ключ
vk1.a.AbCdEf123456...
VK_GROUP_ID
Откройте пост группы → vk.com/wall-174162942_123 → ID с минусом
-174162942
MAX_BOT_TOKEN
В MAX найдите @Metabot → /newbot → создайте бота
0000000000:AAHdA...
MAX_CHAT_ID
Создайте канал → добавьте бота в админы → узнайте ID через /start
-69410493377140
ADMIN_IDS
Напишите боту в ЛС → в логах появится ваш ID
[12345678, 87654321]
ШАГ 3: Настройка конфигурации

Скопируйте .env.example в .env и отредактируйте:

# ===== VK API =====
VK_SERVICE_TOKEN=your_vk_service_token_here
VK_GROUP_ID=-174162942

# ===== MAX Messenger API =====
MAX_BOT_TOKEN=your_max_bot_token_here
MAX_CHAT_ID=-69410493377140
MAX_SERVER=https://myteam.mail.ru/bot/v1/

# ===== Безопасность =====
ADMIN_IDS=[12345678]

# ===== Настройки бота =====
CHECK_INTERVAL=60          # Интервал проверки в секундах
POSTS_LIMIT=10             # Сколько последних постов проверять
ENABLE_BACKLINK=true       # Добавлять кнопку "Открыть в ВК"
LOG_LEVEL=info             # debug, info, warning, error


Запуск бота 🚀
# Одноразовый запуск:
php bot.php

# Запуск в фоне (Linux):
nohup php bot.php > storage/logs/output.log 2>&1 &

# Или через systemd (рекомендуется для продакшена):
sudo nano /etc/systemd/system/vk-max-bot.service

Пример systemd-юнита:
[Unit]
Description=VK to MAX Reposter Bot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/vk-max-reposter-php
ExecStart=/usr/bin/php /var/www/vk-max-reposter-php/bot.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target


# Активация:
sudo systemctl daemon-reload
sudo systemctl enable vk-max-bot
sudo systemctl start vk-max-bot
sudo systemctl status vk-max-bot


🎮 Админ-команды
Отправляйте команды боту в личные сообщения в мессенджере MAX:
Команда
Описание
/start
Показать интерактивное меню с кнопками
/check
Принудительно проверить ВК на новые посты
/status
Показать статус бота: время работы, последний пост, ошибки
/log [N]
Показать последние N записей лога (по умолчанию 15)
/fill N
Загрузить последние N постов из ВК (например, /fill 10)
/stop
Остановить скрипт бота (работает только при запуске в одном процессе)
/help
Показать справку по командам
