<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_error.log');

// Bot configuration
define('BOT_TOKEN', '8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E');
define('ADMIN_ID', 7019306015);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('GEMINI_API_KEY', 'AIzaSyCxAPfTD0dp4PP0S4XR3wtpzlzszeBr3hw');
define('GEMINI_MODEL', 'gemini-2.5-flash');

// Database initialization
$db = new SQLite3(__DIR__ . '/bot_database.db');

// Create tables
$db->exec("CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY,
    username TEXT,
    first_name TEXT,
    tariff TEXT DEFAULT 'free',
    balance INTEGER DEFAULT 0,
    is_banned INTEGER DEFAULT 0,
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS bots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    bot_token TEXT,
    bot_username TEXT,
    bot_number INTEGER,
    webhook_set INTEGER DEFAULT 0,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS channels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    channel_id TEXT,
    channel_username TEXT,
    added_at INTEGER DEFAULT (strftime('%s', 'now'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS tariffs (
    name TEXT PRIMARY KEY,
    bot_limit INTEGER,
    storage_limit_mb REAL,
    price_diamond INTEGER
)");

$db->exec("CREATE TABLE IF NOT EXISTS admin_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    message_text TEXT,
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS user_states (
    user_id INTEGER PRIMARY KEY,
    state TEXT,
    data TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS ai_conversations (
    user_id INTEGER,
    bot_number INTEGER,
    role TEXT,
    content TEXT,
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS ai_usage (
    user_id INTEGER PRIMARY KEY,
    daily_requests INTEGER DEFAULT 0,
    last_reset INTEGER DEFAULT (strftime('%s', 'now'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS maintenance (
    id INTEGER PRIMARY KEY,
    is_active INTEGER DEFAULT 0,
    end_time INTEGER DEFAULT 0,
    message TEXT
)");

// Initialize default tariffs
$stmt = $db->prepare("INSERT OR REPLACE INTO tariffs (name, bot_limit, storage_limit_mb, price_diamond) VALUES (?, ?, ?, ?)");
$tariffs = [
    ['free', 1, 1.0, 0],
    ['pro', 4, 4.5, 99],
    ['vip', 7, 15.0, 299]
];
foreach ($tariffs as $tariff) {
    $stmt->bindValue(1, $tariff[0]);
    $stmt->bindValue(2, $tariff[1]);
    $stmt->bindValue(3, $tariff[2]);
    $stmt->bindValue(4, $tariff[3]);
    $stmt->execute();
}

// Helper functions
function logMessage($message) {
    $logFile = __DIR__ . '/bot_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function apiRequest($method, $parameters = []) {
    $url = API_URL . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup);
    }
    return apiRequest('sendMessage', $params);
}

function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup);
    }
    return apiRequest('editMessageText', $params);
}

function answerCallbackQuery($callback_query_id, $text = '', $show_alert = false) {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => $show_alert
    ]);
}

function checkMembership($user_id) {
    global $db;
    $result = $db->query("SELECT channel_id FROM channels");
    $channels = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $channels[] = $row['channel_id'];
    }
    
    if (empty($channels)) {
        return true;
    }
    
    foreach ($channels as $channel_id) {
        $response = apiRequest('getChatMember', [
            'chat_id' => $channel_id,
            'user_id' => $user_id
        ]);
        
        if (!$response['ok'] || !in_array($response['result']['status'], ['member', 'administrator', 'creator'])) {
            return false;
        }
    }
    
    return true;
}

function getOrCreateUser($user_id, $username = '', $first_name = '') {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        $stmt = $db->prepare("INSERT INTO users (user_id, username, first_name) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $username, SQLITE3_TEXT);
        $stmt->bindValue(3, $first_name, SQLITE3_TEXT);
        $stmt->execute();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    return $user;
}

function getUserState($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT state, data FROM user_states WHERE user_id = ?");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? ['state' => $row['state'], 'data' => $row['data']] : null;
}

function setUserState($user_id, $state, $data = '') {
    global $db;
    $stmt = $db->prepare("INSERT OR REPLACE INTO user_states (user_id, state, data) VALUES (?, ?, ?)");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $state, SQLITE3_TEXT);
    $stmt->bindValue(3, $data, SQLITE3_TEXT);
    $stmt->execute();
}

function clearUserState($user_id) {
    global $db;
    $stmt = $db->prepare("DELETE FROM user_states WHERE user_id = ?");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->execute();
}

function getMainMenu() {
    return [
        'inline_keyboard' => [
            [['text' => '🤖 AI Agent bot yaratish', 'callback_data' => 'ai_create_bot']],
            [['text' => '💻 Kod orqali bot', 'callback_data' => 'code_create_bot'], ['text' => '📁 File Manager', 'callback_data' => 'file_manager']],
            [['text' => '🤖 Mening Botlarim', 'callback_data' => 'my_bots']],
            [['text' => '💼 Kabinet', 'callback_data' => 'cabinet'], ['text' => '❓ Yordam', 'callback_data' => 'help']],
            [['text' => '📞 Admin bilan Bog\'lanish', 'callback_data' => 'contact_admin']]
        ]
    ];
}

function getAdminMenu() {
    return [
        'inline_keyboard' => [
            [['text' => '⚙️ Ta\'rif belgilash', 'callback_data' => 'admin_tariff']],
            [['text' => '💎 Balans qo\'shish', 'callback_data' => 'admin_add_balance']],
            [['text' => '📢 Majburiy kanal', 'callback_data' => 'admin_channels']],
            [['text' => '📣 Reklama/Post', 'callback_data' => 'admin_broadcast']],
            [['text' => '🔧 Maintenance', 'callback_data' => 'admin_maintenance']],
            [['text' => '📊 Statistika', 'callback_data' => 'admin_stats']],
            [['text' => '🚫 Ban/Unban', 'callback_data' => 'admin_ban']],
            [['text' => '❌ Yopish', 'callback_data' => 'admin_close']]
        ]
    ];
}

function getTariffLimits($tariff_name) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM tariffs WHERE name = ?");
    $stmt->bindValue(1, $tariff_name, SQLITE3_TEXT);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function getUserBots($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM bots WHERE user_id = ? ORDER BY bot_number");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $bots = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $bots[] = $row;
    }
    return $bots;
}

function getUserStorageUsage($user_id) {
    $user_dir = __DIR__ . "/users/$user_id";
    if (!is_dir($user_dir)) {
        return 0;
    }
    
    $size = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($user_dir));
    foreach ($files as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size / (1024 * 1024); // Convert to MB
}

function checkBotToken($token) {
    $url = "https://api.telegram.org/bot$token/getMe";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($result, true);
    
    if ($response && isset($response['ok']) && $response['ok']) {
        return $response['result'];
    }
    return false;
}

function scanCodeForThreats($code) {
    $dangerous_patterns = [
        '/vendor\/autoload\.php/',
        '/require.*vendor/',
        '/include.*vendor/',
        '/mysql_connect/',
        '/mysqli_/',
        '/PDO/',
        '/exec\s*\(/',
        '/shell_exec/',
        '/system\s*\(/',
        '/passthru/',
        '/proc_open/',
        '/popen/',
        '/curl_exec.*(?!telegram\.org)/',
        '/file_get_contents.*(?!telegram\.org)/',
        '/python/',
        '/\$_SERVER\[/',
        '/eval\s*\(/',
        '/base64_decode/',
        '/gzinflate/',
        '/str_rot13/',
        '/assert\s*\(/',
        '/unlink\s*\(.*\.\./',
        '/rmdir/',
        '/disk_free_space/',
        '/disk_total_space/',
        '/ini_set.*memory_limit/',
        '/set_time_limit\s*\(\s*0\s*\)/',
        '/while\s*\(\s*true\s*\)/',
        '/for\s*\(.*;;.*\)/'
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $code)) {
            return false;
        }
    }
    
    return true;
}

function setWebhookForUserBot($bot_token, $user_id, $bot_number) {
    $webhook_url = "https://yourdomain.com/webhook.php?user_id=$user_id&bot_number=$bot_number";
    $url = "https://api.telegram.org/bot$bot_token/setWebhook";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['url' => $webhook_url]));
    $result = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($result, true);
    
    return $response && isset($response['ok']) && $response['ok'];
}

function createUserBotDirectory($user_id, $bot_number) {
    $dir = __DIR__ . "/users/$user_id/$bot_number";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function getAvailableBotNumber($user_id) {
    global $db;
    $user = getOrCreateUser($user_id);
    $tariff = getTariffLimits($user['tariff']);
    $existing_bots = getUserBots($user_id);
    
    if (count($existing_bots) >= $tariff['bot_limit']) {
        return false;
    }
    
    $used_numbers = array_column($existing_bots, 'bot_number');
    for ($i = 1; $i <= $tariff['bot_limit']; $i++) {
        if (!in_array($i, $used_numbers)) {
            return $i;
        }
    }
    
    return false;
}

function checkAILimit($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM ai_usage WHERE user_id = ?");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $usage = $result->fetchArray(SQLITE3_ASSOC);
    
    $now = time();
    $day_start = strtotime('today');
    
    if (!$usage) {
        $stmt = $db->prepare("INSERT INTO ai_usage (user_id, daily_requests, last_reset) VALUES (?, 0, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $now, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }
    
    if ($usage['last_reset'] < $day_start) {
        $stmt = $db->prepare("UPDATE ai_usage SET daily_requests = 0, last_reset = ? WHERE user_id = ?");
        $stmt->bindValue(1, $now, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }
    
    return $usage['daily_requests'] < 20;
}

function incrementAIUsage($user_id) {
    global $db;
    $stmt = $db->prepare("UPDATE ai_usage SET daily_requests = daily_requests + 1 WHERE user_id = ?");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->execute();
}

function callGeminiAPI($user_id, $bot_number, $user_message) {
    global $db;
    
    // Get conversation history
    $stmt = $db->prepare("SELECT role, content FROM ai_conversations WHERE user_id = ? AND bot_number = ? AND created_at > ? ORDER BY created_at");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $bot_number, SQLITE3_INTEGER);
    $stmt->bindValue(3, strtotime('today'), SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $contents = [];
    
    // System prompt
    $system_prompt = "Siz NONOCHA BOT nomli AI agentsiz. Siz PHP telegram bot yaratish bo'yicha mutaxassiz. 
Vazifangiz: Foydalanuvchi uchun telegram bot kodini yozish, tahrirлаш va xatolarni tuzatish.

MUHIM qoidalar:
1. Faqat PHP procedural kod yozing (OOP emas)
2. Vendor/autoload.php ishlatmang
3. MySQL va boshqa database amallarni ishlatmang
4. Python kod yozmang
5. Faqat Telegram Bot API ishlating
6. Barcha xatolarni log.txt ga yozish kodini qo'shing
7. Kod xavfsiz bo'lishi kerak
8. Fayllar faqat user_id/$bot_number/ papkasida bo'ladi

Foydalanuvchi kodini tahlil qiling, xatolarni toping va tuzating. Javoblaringizda aniq va tushunarli bo'ling.";
    
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $system_prompt]]
    ];
    
    $contents[] = [
        'role' => 'model',
        'parts' => [['text' => "Salom! Men NONOCHA BOT. Sizning PHP telegram bot yaratishingizga yordam beraman. Qanday bot yaratmoqchisiz?"]]
    ];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $contents[] = [
            'role' => $row['role'],
            'parts' => [['text' => $row['content']]]
        ];
    }
    
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $user_message]]
    ];
    
    $requestData = [
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048
        ]
    ];
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    curl_close($ch);
    
    $response = json_decode($result, true);
    
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_response = $response['candidates'][0]['content']['parts'][0]['text'];
        
        // Save conversation
        $stmt = $db->prepare("INSERT INTO ai_conversations (user_id, bot_number, role, content) VALUES (?, ?, ?, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $bot_number, SQLITE3_INTEGER);
        $stmt->bindValue(3, 'user', SQLITE3_TEXT);
        $stmt->bindValue(4, $user_message, SQLITE3_TEXT);
        $stmt->execute();
        
        $stmt = $db->prepare("INSERT INTO ai_conversations (user_id, bot_number, role, content) VALUES (?, ?, ?, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $bot_number, SQLITE3_INTEGER);
        $stmt->bindValue(3, 'model', SQLITE3_TEXT);
        $stmt->bindValue(4, $ai_response, SQLITE3_TEXT);
        $stmt->execute();
        
        return $ai_response;
    }
    
    return "Xatolik yuz berdi. Qaytadan urinib ko'ring.";
}

function checkMaintenance() {
    global $db;
    $result = $db->query("SELECT * FROM maintenance WHERE id = 1");
    $maintenance = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($maintenance && $maintenance['is_active'] && $maintenance['end_time'] > time()) {
        return $maintenance;
    }
    
    return false;
}

// Main webhook handler
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

logMessage("Update received: " . json_encode($update));

// Check maintenance mode
$maintenance = checkMaintenance();
if ($maintenance && (!isset($update['message']['from']['id']) || $update['message']['from']['id'] != ADMIN_ID)) {
    if (isset($update['message'])) {
        sendMessage($update['message']['chat']['id'], "⚠️ Bot hozir texnik ishlar olib borilmoqda.\n\n" . $maintenance['message']);
    }
    exit;
}

// Handle callback queries
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $username = $callback_query['from']['username'] ?? '';
    $first_name = $callback_query['from']['first_name'] ?? '';
    $message_id = $callback_query['message']['message_id'];
    $data = $callback_query['data'];
    
    $user = getOrCreateUser($user_id, $username, $first_name);
    
    // Check if user is banned
    if ($user['is_banned']) {
        answerCallbackQuery($callback_query['id'], "Siz bloklangansiz!", true);
        exit;
    }
    
    // Check membership
    if (!checkMembership($user_id) && $user_id != ADMIN_ID) {
        answerCallbackQuery($callback_query['id'], "Iltimos avval kanallarga a'zo bo'ling!", true);
        exit;
    }
    
    if ($data == 'check_subscription') {
        if (checkMembership($user_id)) {
            answerCallbackQuery($callback_query['id'], "✅ Tasdiqlandi!");
            $mention = "<a href='tg://user?id=$user_id'>$first_name</a>";
            editMessage($chat_id, $message_id, "🎉 Xush kelibsiz, $mention!\n\nBot orqali siz o'z telegram botlaringizni host qilishingiz mumkin.", getMainMenu());
        } else {
            answerCallbackQuery($callback_query['id'], "❌ Siz hali kanallarga a'zo emassiz!", true);
        }
    }
    elseif ($data == 'main_menu') {
        editMessage($chat_id, $message_id, "📱 Asosiy menyu:", getMainMenu());
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'ai_create_bot') {
        $tariff = getTariffLimits($user['tariff']);
        $bots = getUserBots($user_id);
        
        if (count($bots) >= $tariff['bot_limit']) {
            answerCallbackQuery($callback_query['id'], "❌ Siz maksimal bot limitiga yetdingiz! Ta'rifni yangilang.", true);
            exit;
        }
        
        if (!checkAILimit($user_id)) {
            answerCallbackQuery($callback_query['id'], "❌ Kunlik limit tugadi (20 so'rov)!", true);
            exit;
        }
        
        $available_number = getAvailableBotNumber($user_id);
        if (!$available_number) {
            answerCallbackQuery($callback_query['id'], "❌ Bot raqami topilmadi!", true);
            exit;
        }
        
        editMessage($chat_id, $message_id, "🤖 AI Agent bot yaratish\n\nQanday bot yaratmoqchisiz? Botning vazifasini tushuntiring.\n\nMisol: \"Oddiy echo bot yarating\"", [
            'inline_keyboard' => [[['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']]]
        ]);
        setUserState($user_id, 'ai_create', $available_number);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'code_create_bot') {
        $tariff = getTariffLimits($user['tariff']);
        $bots = getUserBots($user_id);
        
        if (count($bots) >= $tariff['bot_limit']) {
            answerCallbackQuery($callback_query['id'], "❌ Siz maksimal bot limitiga yetdingiz! Ta'rifni yangilang.", true);
            exit;
        }
        
        editMessage($chat_id, $message_id, "💻 Kod orqali bot yaratish\n\nBot tokenini yuboring (BotFather'dan olingan):", [
            'inline_keyboard' => [[['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']]]
        ]);
        setUserState($user_id, 'waiting_token');
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'file_manager') {
        $bots = getUserBots($user_id);
        if (empty($bots)) {
            answerCallbackQuery($callback_query['id'], "❌ Sizda hech qanday bot yo'q!", true);
            exit;
        }
        
        $keyboard = [];
        foreach ($bots as $bot) {
            $keyboard[] = [['text' => "📁 Bot #{$bot['bot_number']} - @{$bot['bot_username']}", 'callback_data' => 'fm_bot_' . $bot['bot_number']]];
        }
        $keyboard[] = [['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']];
        
        editMessage($chat_id, $message_id, "📁 File Manager\n\nBotni tanlang:", ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif (strpos($data, 'fm_bot_') === 0) {
        $bot_number = (int)str_replace('fm_bot_', '', $data);
        $bot_dir = __DIR__ . "/users/$user_id/$bot_number";
        
        if (!is_dir($bot_dir)) {
            answerCallbackQuery($callback_query['id'], "❌ Papka topilmadi!", true);
            exit;
        }
        
        $files = array_diff(scandir($bot_dir), ['.', '..']);
        $keyboard = [];
        
        foreach ($files as $file) {
            $keyboard[] = [['text' => "📄 $file", 'callback_data' => "fm_file_{$bot_number}_$file"]];
        }
        $keyboard[] = [['text' => '🔙 Orqaga', 'callback_data' => 'file_manager']];
        
        $text = "📁 Bot #$bot_number fayllar:\n\n";
        $text .= empty($files) ? "Hech qanday fayl yo'q." : "Faylni tanlang:";
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif (strpos($data, 'fm_file_') === 0) {
        $parts = explode('_', $data);
        $bot_number = $parts[2];
        $filename = $parts[3];
        
        $keyboard = [
            [['text' => '📥 Yuklab olish', 'callback_data' => "fm_download_{$bot_number}_$filename"]],
            [['text' => 'ℹ️ Info', 'callback_data' => "fm_info_{$bot_number}_$filename"]],
            [['text' => '🗑 O\'chirish', 'callback_data' => "fm_delete_{$bot_number}_$filename"]],
            [['text' => '🔙 Orqaga', 'callback_data' => 'fm_bot_' . $bot_number]]
        ];
        
        editMessage($chat_id, $message_id, "📄 Fayl: $filename\n\nAmalni tanlang:", ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif (strpos($data, 'fm_download_') === 0) {
        $parts = explode('_', $data);
        $bot_number = $parts[2];
        $filename = $parts[3];
        $filepath = __DIR__ . "/users/$user_id/$bot_number/$filename";
        
        if (file_exists($filepath)) {
            apiRequest('sendDocument', [
                'chat_id' => $chat_id,
                'document' => new CURLFile($filepath),
                'caption' => "📄 Fayl: $filename"
            ]);
            answerCallbackQuery($callback_query['id'], "✅ Fayl yuborildi!");
        } else {
            answerCallbackQuery($callback_query['id'], "❌ Fayl topilmadi!", true);
        }
    }
    elseif (strpos($data, 'fm_info_') === 0) {
        $parts = explode('_', $data);
        $bot_number = $parts[2];
        $filename = $parts[3];
        $filepath = __DIR__ . "/users/$user_id/$bot_number/$filename";
        
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            $modified = date('Y-m-d H:i:s', filemtime($filepath));
            $text = "📄 Fayl ma'lumotlari:\n\n";
            $text .= "📌 Nom: $filename\n";
            $text .= "📊 Hajm: " . round($size / 1024, 2) . " KB\n";
            $text .= "📅 O'zgartirilgan: $modified";
            
            answerCallbackQuery($callback_query['id'], $text, true);
        } else {
            answerCallbackQuery($callback_query['id'], "❌ Fayl topilmadi!", true);
        }
    }
    elseif (strpos($data, 'fm_delete_') === 0) {
        $parts = explode('_', $data);
        $bot_number = $parts[2];
        $filename = $parts[3];
        $filepath = __DIR__ . "/users/$user_id/$bot_number/$filename";
        
        if (file_exists($filepath)) {
            unlink($filepath);
            answerCallbackQuery($callback_query['id'], "✅ Fayl o'chirildi!");
            // Refresh file list
            $data = 'fm_bot_' . $bot_number;
            $bot_dir = __DIR__ . "/users/$user_id/$bot_number";
            $files = array_diff(scandir($bot_dir), ['.', '..']);
            $keyboard = [];
            
            foreach ($files as $file) {
                $keyboard[] = [['text' => "📄 $file", 'callback_data' => "fm_file_{$bot_number}_$file"]];
            }
            $keyboard[] = [['text' => '🔙 Orqaga', 'callback_data' => 'file_manager']];
            
            $text = "📁 Bot #$bot_number fayllar:\n\n";
            $text .= empty($files) ? "Hech qanday fayl yo'q." : "Faylni tanlang:";
            
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        } else {
            answerCallbackQuery($callback_query['id'], "❌ Fayl topilmadi!", true);
        }
    }
    elseif ($data == 'my_bots') {
        $bots = getUserBots($user_id);
        
        if (empty($bots)) {
            editMessage($chat_id, $message_id, "🤖 Sizda hech qanday bot yo'q!\n\nBirinchi botingizni yarating.", getMainMenu());
            answerCallbackQuery($callback_query['id']);
            exit;
        }
        
        $text = "🤖 Sizning botlaringiz:\n\n";
        $keyboard = [];
        
        foreach ($bots as $bot) {
            $text .= "#{$bot['bot_number']} - @{$bot['bot_username']}\n";
            $keyboard[] = [['text' => "🤖 #{$bot['bot_number']} @{$bot['bot_username']}", 'callback_data' => 'bot_manage_' . $bot['id']]];
        }
        
        $keyboard[] = [['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif (strpos($data, 'bot_manage_') === 0) {
        $bot_id = (int)str_replace('bot_manage_', '', $data);
        $stmt = $db->prepare("SELECT * FROM bots WHERE id = ? AND user_id = ?");
        $stmt->bindValue(1, $bot_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $bot = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$bot) {
            answerCallbackQuery($callback_query['id'], "❌ Bot topilmadi!", true);
            exit;
        }
        
        $text = "🤖 Bot ma'lumotlari:\n\n";
        $text .= "📌 Bot: @{$bot['bot_username']}\n";
        $text .= "🔢 Raqam: #{$bot['bot_number']}\n";
        $text .= "🌐 Webhook: " . ($bot['webhook_set'] ? "✅ O'rnatilgan" : "❌ O'rnatilmagan") . "\n";
        $text .= "📅 Yaratilgan: " . date('Y-m-d H:i', $bot['created_at']);
        
        $keyboard = [
            [['text' => '🗑 Botni o\'chirish', 'callback_data' => 'bot_delete_' . $bot_id]],
            [['text' => '🔙 Orqaga', 'callback_data' => 'my_bots']]
        ];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif (strpos($data, 'bot_delete_') === 0) {
        $bot_id = (int)str_replace('bot_delete_', '', $data);
        $stmt = $db->prepare("SELECT * FROM bots WHERE id = ? AND user_id = ?");
        $stmt->bindValue(1, $bot_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $bot = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($bot) {
            // Delete bot files
            $bot_dir = __DIR__ . "/users/$user_id/{$bot['bot_number']}";
            if (is_dir($bot_dir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($bot_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($bot_dir);
            }
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM bots WHERE id = ?");
            $stmt->bindValue(1, $bot_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            answerCallbackQuery($callback_query['id'], "✅ Bot o'chirildi!");
            editMessage($chat_id, $message_id, "✅ Bot muvaffaqiyatli o'chirildi!", getMainMenu());
        } else {
            answerCallbackQuery($callback_query['id'], "❌ Bot topilmadi!", true);
        }
    }
    elseif ($data == 'cabinet') {
        $tariff = getTariffLimits($user['tariff']);
        $storage_used = getUserStorageUsage($user_id);
        $bots = getUserBots($user_id);
        
        $text = "💼 Kabinet\n\n";
        $text .= "👤 Foydalanuvchi: <a href='tg://user?id=$user_id'>$first_name</a>\n";
        $text .= "💎 Balans: {$user['balance']} olmos\n";
        $text .= "📊 Ta'rif: " . strtoupper($user['tariff']) . "\n";
        $text .= "🤖 Botlar: " . count($bots) . " / {$tariff['bot_limit']}\n";
        $text .= "💾 Storage: " . round($storage_used, 2) . " MB / {$tariff['storage_limit_mb']} MB";
        
        $keyboard = [
            [['text' => '💎 Balans', 'callback_data' => 'balance'], ['text' => '📊 Ta\'rif', 'callback_data' => 'tariff']],
            [['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']]
        ];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'balance') {
        $text = "💎 Balans: {$user['balance']} olmos\n\n";
        $text .= "💰 1 olmos = 120 UZS\n\n";
        $text .= "Balansni to'ldirish uchun admin bilan bog'laning:\n";
        $text .= "👤 @WINAIKO";
        
        $keyboard = [
            [['text' => '📞 Admin bilan bog\'lanish', 'url' => 'https://t.me/WINAIKO']],
            [['text' => '🔙 Orqaga', 'callback_data' => 'cabinet']]
        ];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'tariff') {
        $all_tariffs = $db->query("SELECT * FROM tariffs ORDER BY price_diamond");
        
        $text = "📊 Ta'riflar:\n\n";
        
        while ($t = $all_tariffs->fetchArray(SQLITE3_ASSOC)) {
            $text .= "▫️ " . strtoupper($t['name']) . "\n";
            $text .= "   🤖 {$t['bot_limit']} ta bot\n";
            $text .= "   💾 {$t['storage_limit_mb']} MB storage\n";
            $text .= "   💎 {$t['price_diamond']} olmos\n\n";
        }
        
        $text .= "Joriy ta'rif: " . strtoupper($user['tariff']);
        
        $keyboard = [];
        $all_tariffs = $db->query("SELECT * FROM tariffs ORDER BY price_diamond");
        while ($t = $all_tariffs->fetchArray(SQLITE3_ASSOC)) {
            if ($t['name'] != $user['tariff']) {
                $keyboard[] = [['text' => "⬆️ {$t['name']} ga o'tish ({$t['price_diamond']} olmos)", 'callback_data' => 'buy_tariff_' . $t['name']]];
            }
        }
        $keyboard[] = [['text' => '🔙 Orqaga', 'callback_data' => 'cabinet']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif (strpos($data, 'buy_tariff_') === 0) {
        $tariff_name = str_replace('buy_tariff_', '', $data);
        $tariff = getTariffLimits($tariff_name);
        
        if ($user['balance'] < $tariff['price_diamond']) {
            answerCallbackQuery($callback_query['id'], "❌ Balansda yetarli olmos yo'q!", true);
            exit;
        }
        
        // Update tariff
        $stmt = $db->prepare("UPDATE users SET tariff = ?, balance = balance - ? WHERE user_id = ?");
        $stmt->bindValue(1, $tariff_name, SQLITE3_TEXT);
        $stmt->bindValue(2, $tariff['price_diamond'], SQLITE3_INTEGER);
        $stmt->bindValue(3, $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        answerCallbackQuery($callback_query['id'], "✅ Ta'rif muvaffaqiyatli o'zgartirildi!", true);
        
        $text = "✅ Ta'rif muvaffaqiyatli o'zgartirildi!\n\n";
        $text .= "📊 Yangi ta'rif: " . strtoupper($tariff_name) . "\n";
        $text .= "🤖 Bot limiti: {$tariff['bot_limit']}\n";
        $text .= "💾 Storage: {$tariff['storage_limit_mb']} MB\n";
        $text .= "💎 To'landi: {$tariff['price_diamond']} olmos";
        
        editMessage($chat_id, $message_id, $text, getMainMenu());
    }
    elseif ($data == 'help') {
        $text = "❓ Yordam\n\n";
        $text .= "🤖 <b>Bot yaratish:</b>\n";
        $text .= "1. @BotFather ga o'ting\n";
        $text .= "2. /newbot buyrug'ini yuboring\n";
        $text .= "3. Bot nomi va username kiriting\n";
        $text .= "4. Token oling va bizning botga yuboring\n\n";
        $text .= "💻 <b>Kod tahrirлash:</b>\n";
        $text .= "Play Market yoki App Store dan kod tahrirlovchi ilovalarni yuklab oling:\n";
        $text .= "• QuickEdit\n";
        $text .= "• Code Editor\n";
        $text .= "• Acode\n\n";
        $text .= "📝 <b>Qo'shimcha yordam:</b>\n";
        $text .= "Admin bilan bog'laning: @WINAIKO";
        
        $keyboard = [
            [['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']]
        ];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'contact_admin') {
        $text = "📞 Admin bilan bog'lanish\n\n";
        $text .= "Sizning xabaringizni yuboring, admin ko'rib chiqadi va javob beradi.";
        
        setUserState($user_id, 'contact_admin');
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 Bekor qilish', 'callback_data' => 'main_menu']]]]);
        answerCallbackQuery($callback_query['id']);
    }
    // Admin panel callbacks
    elseif ($data == 'admin_panel' && $user_id == ADMIN_ID) {
        editMessage($chat_id, $message_id, "👨‍💼 Admin Panel", getAdminMenu());
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'admin_tariff' && $user_id == ADMIN_ID) {
        $text = "⚙️ Ta'riflarni sozлash\n\nTa'rifni tanlang:";
        $keyboard = [
            [['text' => 'FREE', 'callback_data' => 'edit_tariff_free']],
            [['text' => 'PRO', 'callback_data' => 'edit_tariff_pro']],
            [['text' => 'VIP', 'callback_data' => 'edit_tariff_vip']],
            [['text' => '🔙 Orqaga', 'callback_data' => 'admin_panel']]
        ];
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'admin_add_balance' && $user_id == ADMIN_ID) {
        $text = "💎 Balans qo'shish\n\nFoydalanuvchi ID ni yuboring:";
        setUserState($user_id, 'admin_add_balance_id');
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 Bekor qilish', 'callback_data' => 'admin_panel']]]]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'admin_channels' && $user_id == ADMIN_ID) {
        $channels = $db->query("SELECT * FROM channels");
        $text = "📢 Majburiy kanallar:\n\n";
        
        $has_channels = false;
        $keyboard = [];
        while ($channel = $channels->fetchArray(SQLITE3_ASSOC)) {
            $has_channels = true;
            $text .= "▫️ @{$channel['channel_username']} ({$channel['channel_id']})\n";
            $keyboard[] = [['text' => "❌ @{$channel['channel_username']}", 'callback_data' => 'remove_channel_' . $channel['id']]];
        }
        
        if (!$has_channels) {
            $text .= "Hech qanday kanal yo'q.";
        }
        
        $keyboard[] = [['text' => '➕ Kanal qo\'shish', 'callback_data' => 'add_channel']];
        $keyboard[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_panel']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'add_channel' && $user_id == ADMIN_ID) {
        $text = "📢 Kanal qo'shish\n\nKanal ID yoki username (@channel) yuboring:";
        setUserState($user_id, 'admin_add_channel');
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 Bekor qilish', 'callback_data' => 'admin_channels']]]]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif (strpos($data, 'remove_channel_') === 0 && $user_id == ADMIN_ID) {
        $channel_id = (int)str_replace('remove_channel_', '', $data);
        $stmt = $db->prepare("DELETE FROM channels WHERE id = ?");
        $stmt->bindValue(1, $channel_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        answerCallbackQuery($callback_query['id'], "✅ Kanal o'chirildi!");
        
        // Refresh list
        $channels = $db->query("SELECT * FROM channels");
        $text = "📢 Majburiy kanallar:\n\n";
        
        $has_channels = false;
        $keyboard = [];
        while ($channel = $channels->fetchArray(SQLITE3_ASSOC)) {
            $has_channels = true;
            $text .= "▫️ @{$channel['channel_username']} ({$channel['channel_id']})\n";
            $keyboard[] = [['text' => "❌ @{$channel['channel_username']}", 'callback_data' => 'remove_channel_' . $channel['id']]];
        }
        
        if (!$has_channels) {
            $text .= "Hech qanday kanal yo'q.";
        }
        
        $keyboard[] = [['text' => '➕ Kanal qo\'shish', 'callback_data' => 'add_channel']];
        $keyboard[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_panel']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
    }
    elseif ($data == 'admin_broadcast' && $user_id == ADMIN_ID) {
        $text = "📣 Reklama/Post\n\nXabaringizni yuboring (matn, rasm, video):";
        setUserState($user_id, 'admin_broadcast');
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 Bekor qilish', 'callback_data' => 'admin_panel']]]]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'admin_maintenance' && $user_id == ADMIN_ID) {
        $maintenance = $db->query("SELECT * FROM maintenance WHERE id = 1")->fetchArray(SQLITE3_ASSOC);
        
        $text = "🔧 Maintenance mode\n\n";
        $text .= "Status: " . ($maintenance && $maintenance['is_active'] ? "🔴 Aktiv" : "🟢 O'chirilgan") . "\n";
        
        $keyboard = [
            [['text' => '🔴 Yoqish', 'callback_data' => 'maintenance_on']],
            [['text' => '🟢 O\'chirish', 'callback_data' => 'maintenance_off']],
            [['text' => '🔙 Orqaga', 'callback_data' => 'admin_panel']]
        ];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'maintenance_on' && $user_id == ADMIN_ID) {
        $text = "🔧 Maintenance mode\n\nNecha soatga yoqmoqchisiz? (1-72)";
        setUserState($user_id, 'admin_maintenance_hours');
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 Bekor qilish', 'callback_data' => 'admin_maintenance']]]]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'maintenance_off' && $user_id == ADMIN_ID) {
        $db->exec("INSERT OR REPLACE INTO maintenance (id, is_active, end_time, message) VALUES (1, 0, 0, '')");
        answerCallbackQuery($callback_query['id'], "✅ Maintenance o'chirildi!");
        editMessage($chat_id, $message_id, "✅ Maintenance mode o'chirildi!", getAdminMenu());
    }
    elseif ($data == 'admin_stats' && $user_id == ADMIN_ID) {
        $total_users = $db->querySingle("SELECT COUNT(*) FROM users");
        $total_bots = $db->querySingle("SELECT COUNT(*) FROM bots");
        $banned_users = $db->querySingle("SELECT COUNT(*) FROM users WHERE is_banned = 1");
        $total_storage = 0;
        
        $users_dir = __DIR__ . "/users";
        if (is_dir($users_dir)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($users_dir));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $total_storage += $file->getSize();
                }
            }
        }
        
        $text = "📊 Statistika\n\n";
        $text .= "👥 Foydalanuvchilar: $total_users\n";
        $text .= "🤖 Yaratilgan botlar: $total_bots\n";
        $text .= "🚫 Bloklangan: $banned_users\n";
        $text .= "💾 Umumiy storage: " . round($total_storage / (1024 * 1024), 2) . " MB";
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 Orqaga', 'callback_data' => 'admin_panel']]]]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'admin_ban' && $user_id == ADMIN_ID) {
        $text = "🚫 Ban/Unban\n\nFoydalanuvchi ID ni yuboring:";
        setUserState($user_id, 'admin_ban_id');
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 Bekor qilish', 'callback_data' => 'admin_panel']]]]);
        answerCallbackQuery($callback_query['id']);
    }
    elseif ($data == 'admin_close' && $user_id == ADMIN_ID) {
        editMessage($chat_id, $message_id, "✅ Admin panel yopildi.", getMainMenu());
        answerCallbackQuery($callback_query['id']);
    }
    
    exit;
}

// Handle messages
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $username = $message['from']['username'] ?? '';
    $first_name = $message['from']['first_name'] ?? '';
    $text = $message['text'] ?? '';
    
    $user = getOrCreateUser($user_id, $username, $first_name);
    
    // Check if user is banned
    if ($user['is_banned'] && $user_id != ADMIN_ID) {
        sendMessage($chat_id, "🚫 Siz bloklangansiz!");
        exit;
    }
    
    // /start command
    if ($text == '/start') {
        if ($user_id == ADMIN_ID) {
            $mention = "<a href='tg://user?id=$user_id'>$first_name</a>";
            sendMessage($chat_id, "👨‍💼 Xush kelibsiz, Admin $mention!", getAdminMenu());
            exit;
        }
        
        // Check membership
        if (!checkMembership($user_id)) {
            $channels = $db->query("SELECT * FROM channels");
            $text = "🔔 Botdan foydalanish uchun quyidagi kanallarga a'zo bo'ling:\n\n";
            
            $keyboard = [];
            while ($channel = $channels->fetchArray(SQLITE3_ASSOC)) {
                $text .= "▫️ @{$channel['channel_username']}\n";
                $keyboard[] = [['text' => "@{$channel['channel_username']}", 'url' => "https://t.me/{$channel['channel_username']}"]];
            }
            
            $keyboard[] = [['text' => '✅ Tasdiqlash', 'callback_data' => 'check_subscription']];
            
            sendMessage($chat_id, $text, ['inline_keyboard' => $keyboard]);
            exit;
        }
        
        $mention = "<a href='tg://user?id=$user_id'>$first_name</a>";
        sendMessage($chat_id, "🎉 Xush kelibsiz, $mention!\n\nBot orqali siz o'z telegram botlaringizni host qilishingiz mumkin.", getMainMenu());
        exit;
    }
    
    // /admin command
    if ($text == '/admin' && $user_id == ADMIN_ID) {
        sendMessage($chat_id, "👨‍💼 Admin Panel", getAdminMenu());
        exit;
    }
    
    // Handle user states
    $state = getUserState($user_id);
    
    if ($state) {
        if ($state['state'] == 'waiting_token') {
            // Validate bot token
            $bot_info = checkBotToken($text);
            if (!$bot_info) {
                sendMessage($chat_id, "❌ Noto'g'ri token! Qayta urinib ko'ring yoki /start bosing.");
                exit;
            }
            
            // Check if bot already exists
            $stmt = $db->prepare("SELECT * FROM bots WHERE bot_token = ?");
            $stmt->bindValue(1, $text, SQLITE3_TEXT);
            $existing = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            
            if ($existing) {
                sendMessage($chat_id, "❌ Bu bot allaqachon ro'yxatdan o'tgan!");
                clearUserState($user_id);
                exit;
            }
            
            // Save token temporarily
            setUserState($user_id, 'waiting_code', json_encode(['token' => $text, 'username' => $bot_info['username']]));
            sendMessage($chat_id, "✅ Bot: @{$bot_info['username']}\n\n📄 Endi bot kodini PHP fayl ko'rinishida yuboring:");
            exit;
        }
        elseif ($state['state'] == 'waiting_code') {
            if (!isset($message['document'])) {
                sendMessage($chat_id, "❌ Iltimos fayl yuboring!");
                exit;
            }
            
            $file_id = $message['document']['file_id'];
            $file_info = apiRequest('getFile', ['file_id' => $file_id]);
            
            if (!$file_info['ok']) {
                sendMessage($chat_id, "❌ Faylni yuklab bo'lmadi!");
                exit;
            }
            
            $file_path = $file_info['result']['file_path'];
            $file_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/$file_path";
            $code = file_get_contents($file_url);
            
            // Security check
            if (!scanCodeForThreats($code)) {
                sendMessage($chat_id, "⚠️ Kod xavfsizlik tekshiruvidan o'tmadi!\n\nTa'qiqlangan:\n• vendor/autoload.php\n• MySQL/Database\n• Python\n• Xavfli funksiyalar");
                clearUserState($user_id);
                exit;
            }
            
            $data = json_decode($state['data'], true);
            $bot_token = $data['token'];
            $bot_username = $data['username'];
            
            // Check if token is in code
            if (strpos($code, $bot_token) === false) {
                sendMessage($chat_id, "❌ Kodda bot token topilmadi!");
                clearUserState($user_id);
                exit;
            }
            
            // Get available bot number
            $bot_number = getAvailableBotNumber($user_id);
            if ($bot_number === false) {
                sendMessage($chat_id, "❌ Bot limiti to'lgan! Ta'rifni yangilang.");
                clearUserState($user_id);
                exit;
            }
            
            // Check storage limit
            $tariff = getTariffLimits($user['tariff']);
            $current_storage = getUserStorageUsage($user_id);
            $file_size_mb = strlen($code) / (1024 * 1024);
            
            if (($current_storage + $file_size_mb) > $tariff['storage_limit_mb']) {
                sendMessage($chat_id, "❌ Storage limiti yetarli emas! Ta'rifni yangilang.");
                clearUserState($user_id);
                exit;
            }
            
            // Create directory and save file
            $bot_dir = createUserBotDirectory($user_id, $bot_number);
            file_put_contents("$bot_dir/index.php", $code);
            
            // Add logging to code
            $log_code = "\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\nini_set('log_errors', 1);\nini_set('error_log', __DIR__ . '/log.txt');\n";
            $code_with_log = "<?php\n$log_code\n" . ltrim($code, "<?php\n");
            file_put_contents("$bot_dir/index.php", $code_with_log);
            
            // Set webhook
            $webhook_set = setWebhookForUserBot($bot_token, $user_id, $bot_number);
            
            // Save to database
            $stmt = $db->prepare("INSERT INTO bots (user_id, bot_token, bot_username, bot_number, webhook_set) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $bot_token, SQLITE3_TEXT);
            $stmt->bindValue(3, $bot_username, SQLITE3_TEXT);
            $stmt->bindValue(4, $bot_number, SQLITE3_INTEGER);
            $stmt->bindValue(5, $webhook_set ? 1 : 0, SQLITE3_INTEGER);
            $stmt->execute();
            
            clearUserState($user_id);
            
            $response_text = "✅ Bot muvaffaqiyatli yaratildi!\n\n";
            $response_text .= "🤖 Bot: @$bot_username\n";
            $response_text .= "🔢 Bot raqami: #$bot_number\n";
            $response_text .= "🌐 Webhook: " . ($webhook_set ? "✅ O'rnatilgan" : "⚠️ Xatolik");
            
            sendMessage($chat_id, $response_text, getMainMenu());
            exit;
        }
        elseif ($state['state'] == 'ai_create') {
            if (!checkAILimit($user_id)) {
                sendMessage($chat_id, "❌ Kunlik limit tugadi (20 so'rov)!");
                clearUserState($user_id);
                exit;
            }
            
            $bot_number = (int)$state['data'];
            
            // Call Gemini AI
            incrementAIUsage($user_id);
            $ai_response = callGeminiAPI($user_id, $bot_number, $text);
            
            sendMessage($chat_id, "🤖 NONOCHA BOT:\n\n$ai_response", [
                'inline_keyboard' => [
                    [['text' => '✅ Tugadi', 'callback_data' => 'main_menu']],
                    [['text' => '💬 Davom ettirish', 'callback_data' => 'ai_continue']]
                ]
            ]);
            exit;
        }
        elseif ($state['state'] == 'contact_admin') {
            // Save message for admin
            $stmt = $db->prepare("INSERT INTO admin_messages (user_id, message_text) VALUES (?, ?)");
            $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $text, SQLITE3_TEXT);
            $stmt->execute();
            
            // Forward to admin
            $forward_text = "📬 Yangi xabar:\n\n";
            $forward_text .= "👤 User ID: $user_id\n";
            $forward_text .= "👤 Username: @$username\n";
            $forward_text .= "💬 Xabar:\n$text";
            
            sendMessage(ADMIN_ID, $forward_text, [
                'inline_keyboard' => [[['text' => '💬 Javob berish', 'callback_data' => 'admin_reply_' . $user_id]]]
            ]);
            
            clearUserState($user_id);
            sendMessage($chat_id, "✅ Xabaringiz adminga yuborildi!", getMainMenu());
            exit;
        }
        // Admin states
        elseif ($state['state'] == 'admin_add_balance_id' && $user_id == ADMIN_ID) {
            $target_user_id = (int)$text;
            setUserState($user_id, 'admin_add_balance_amount', $target_user_id);
            sendMessage($chat_id, "💎 Necha olmos qo'shmoqchisiz?");
            exit;
        }
        elseif ($state['state'] == 'admin_add_balance_amount' && $user_id == ADMIN_ID) {
            $amount = (int)$text;
            $target_user_id = (int)$state['data'];
            
            $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
            $stmt->bindValue(1, $amount, SQLITE3_INTEGER);
            $stmt->bindValue(2, $target_user_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            clearUserState($user_id);
            sendMessage($chat_id, "✅ Balans qo'shildi!\n\n👤 User: $target_user_id\n💎 Qo'shildi: $amount olmos", getAdminMenu());
            
            // Notify user
            sendMessage($target_user_id, "💎 Balansingizga $amount olmos qo'shildi!");
            exit;
        }
        elseif ($state['state'] == 'admin_add_channel' && $user_id == ADMIN_ID) {
            $channel_username = str_replace('@', '', $text);
            $channel_id = $text;
            
            $stmt = $db->prepare("INSERT INTO channels (channel_id, channel_username) VALUES (?, ?)");
            $stmt->bindValue(1, $channel_id, SQLITE3_TEXT);
            $stmt->bindValue(2, $channel_username, SQLITE3_TEXT);
            $stmt->execute();
            
            clearUserState($user_id);
            sendMessage($chat_id, "✅ Kanal qo'shildi: @$channel_username", getAdminMenu());
            exit;
        }
        elseif ($state['state'] == 'admin_maintenance_hours' && $user_id == ADMIN_ID) {
            $hours = (int)$text;
            if ($hours < 1 || $hours > 72) {
                sendMessage($chat_id, "❌ 1 dan 72 gacha bo'lgan son kiriting!");
                exit;
            }
            
            setUserState($user_id, 'admin_maintenance_message', $hours);
            sendMessage($chat_id, "💬 Xabar matnini yuboring:");
            exit;
        }
        elseif ($state['state'] == 'admin_maintenance_message' && $user_id == ADMIN_ID) {
            $hours = (int)$state['data'];
            $end_time = time() + ($hours * 3600);
            
            $db->exec("INSERT OR REPLACE INTO maintenance (id, is_active, end_time, message) VALUES (1, 1, $end_time, " . $db->escapeString($text) . ")");
            
            clearUserState($user_id);
            sendMessage($chat_id, "✅ Maintenance mode yoqildi!\n\n⏱ Davomiyligi: $hours soat\n💬 Xabar: $text", getAdminMenu());
            exit;
        }
        elseif ($state['state'] == 'admin_broadcast' && $user_id == ADMIN_ID) {
            $users = $db->query("SELECT user_id FROM users");
            $count = 0;
            
            while ($u = $users->fetchArray(SQLITE3_ASSOC)) {
                sendMessage($u['user_id'], $text);
                $count++;
            }
            
            clearUserState($user_id);
            sendMessage($chat_id, "✅ Xabar $count ta foydalanuvchiga yuborildi!", getAdminMenu());
            exit;
        }
        elseif ($state['state'] == 'admin_ban_id' && $user_id == ADMIN_ID) {
            $target_user_id = (int)$text;
            
            $stmt = $db->prepare("SELECT is_banned FROM users WHERE user_id = ?");
            $stmt->bindValue(1, $target_user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $target = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$target) {
                sendMessage($chat_id, "❌ Foydalanuvchi topilmadi!");
                clearUserState($user_id);
                exit;
            }
            
            $new_status = $target['is_banned'] ? 0 : 1;
            $stmt = $db->prepare("UPDATE users SET is_banned = ? WHERE user_id = ?");
            $stmt->bindValue(1, $new_status, SQLITE3_INTEGER);
            $stmt->bindValue(2, $target_user_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            clearUserState($user_id);
            $status_text = $new_status ? "bloklandi" : "blokdan chiqarildi";
            sendMessage($chat_id, "✅ Foydalanuvchi $target_user_id $status_text!", getAdminMenu());
            exit;
        }
    }
}

logMessage("Request processed successfully");
?>
