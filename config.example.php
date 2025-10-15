<?php
// Configuration file - Copy this to config.php and update values

// Bot configuration
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('ADMIN_ID', YOUR_ADMIN_ID_HERE); // Numeric ID
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');

// Domain configuration
define('DOMAIN', 'yourdomain.com'); // Without https://

// Webhook URLs
define('MAIN_WEBHOOK_URL', 'https://' . DOMAIN . '/index.php');
define('USER_BOT_WEBHOOK_URL', 'https://' . DOMAIN . '/webhook.php');

// Database
define('DB_PATH', __DIR__ . '/bot_database.db');

// Directories
define('USERS_DIR', __DIR__ . '/users');
define('LOGS_DIR', __DIR__ . '/logs');

// Limits
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('AI_DAILY_LIMIT', 20);

// Telegram API
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Gemini API
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/');
?>
