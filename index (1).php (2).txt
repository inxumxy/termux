<?php
// ============================================
// PHP TELEGRAM BOT HOSTING PLATFORM
// ============================================

// Error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_error.log');
error_reporting(E_ALL);

// Bot Configuration
define('BOT_TOKEN', '8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E');
define('ADMIN_ID', 7019306015);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('WEBHOOK_URL', 'https://aicode.uz/nonocha/');
define('GEMINI_API_KEY', 'AIzaSyCxAPfTD0dp4PP0S4XR3wtpzlzszeBr3hw');
define('GEMINI_MODEL', 'gemini-2.5-flash');

// Directories
define('DATA_DIR', __DIR__ . '/data/');
define('USERS_DIR', DATA_DIR . 'users/');
define('BOTS_DIR', __DIR__ . '/bots/');
define('CONFIG_FILE', DATA_DIR . 'config.json');
define('STATS_FILE', DATA_DIR . 'stats.json');

// Create directories if not exist
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
if (!file_exists(USERS_DIR)) mkdir(USERS_DIR, 0777, true);
if (!file_exists(BOTS_DIR)) mkdir(BOTS_DIR, 0777, true);

// Load configuration
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        $default = [
            'channels' => [],
            'plans' => [
                'free' => ['bots' => 1, 'storage' => 1, 'price' => 0],
                'pro' => ['bots' => 4, 'storage' => 4.5, 'price' => 99],
                'vip' => ['bots' => 7, 'storage' => 15, 'price' => 299]
            ],
            'maintenance' => false,
            'maintenance_until' => 0,
            'banned_users' => []
        ];
        saveConfig($default);
        return $default;
    }
    return json_decode(file_get_contents(CONFIG_FILE), true);
}

function saveConfig($config) {
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Load statistics
function loadStats() {
    if (!file_exists(STATS_FILE)) {
        $default = [
            'total_users' => 0,
            'total_bots' => 0,
            'total_banned' => 0,
            'total_storage_used' => 0
        ];
        saveStats($default);
        return $default;
    }
    return json_decode(file_get_contents(STATS_FILE), true);
}

function saveStats($stats) {
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

// User data management
function getUserFile($user_id) {
    return USERS_DIR . $user_id . '.json';
}

function loadUser($user_id) {
    $file = getUserFile($user_id);
    if (!file_exists($file)) {
        $user = [
            'id' => $user_id,
            'plan' => 'free',
            'balance' => 0,
            'bots' => [],
            'storage_used' => 0,
            'ai_requests_today' => 0,
            'ai_date' => date('Y-m-d'),
            'ai_history' => [],
            'joined_date' => date('Y-m-d H:i:s')
        ];
        saveUser($user_id, $user);
        
        $stats = loadStats();
        $stats['total_users']++;
        saveStats($stats);
        
        return $user;
    }
    return json_decode(file_get_contents($file), true);
}

function saveUser($user_id, $data) {
    file_put_contents(getUserFile($user_id), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Telegram API functions
function apiRequest($method, $parameters = []) {
    $url = API_URL . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMessage($chat_id, $text, $keyboard = null, $parse_mode = 'HTML') {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    if ($keyboard) {
        $data['reply_markup'] = $keyboard;
    }
    return apiRequest('sendMessage', $data);
}

function editMessage($chat_id, $message_id, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $data['reply_markup'] = $keyboard;
    }
    return apiRequest('editMessageText', $data);
}

function answerCallback($callback_id, $text = '', $alert = false) {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callback_id,
        'text' => $text,
        'show_alert' => $alert
    ]);
}

function checkSubscription($user_id, $channel_username) {
    $result = apiRequest('getChatMember', [
        'chat_id' => $channel_username,
        'user_id' => $user_id
    ]);
    
    if (!$result['ok']) return false;
    
    $status = $result['result']['status'];
    return in_array($status, ['member', 'administrator', 'creator']);
}

function getBotInfo($token) {
    $url = "https://api.telegram.org/bot{$token}/getMe";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function setWebhook($token, $webhook_url) {
    $url = "https://api.telegram.org/bot{$token}/setWebhook";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $webhook_url]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Main menu keyboard
function getMainMenu($user_id) {
    $user = loadUser($user_id);
    return [
        'inline_keyboard' => [
            [
                ['text' => '🤖 AI Agent bot yaratish', 'callback_data' => 'ai_bot_create']
            ],
            [
                ['text' => '💻 Kod orqali bot', 'callback_data' => 'manual_bot_create'],
                ['text' => '📁 File Manager', 'callback_data' => 'file_manager']
            ],
            [
                ['text' => '🤖 Mening Botlarim', 'callback_data' => 'my_bots']
            ],
            [
                ['text' => '👤 Kabinet', 'callback_data' => 'cabinet'],
                ['text' => '❓ Yordam', 'callback_data' => 'help']
            ],
            [
                ['text' => '📞 Admin bilan Bog\'lanish', 'callback_data' => 'contact_admin']
            ]
        ]
    ];
}

// Admin menu keyboard
function getAdminMenu() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '📊 Statistika', 'callback_data' => 'admin_stats']
            ],
            [
                ['text' => '💎 Tarif belgilash', 'callback_data' => 'admin_plans'],
                ['text' => '💰 Balans qo\'shish', 'callback_data' => 'admin_balance']
            ],
            [
                ['text' => '📢 Majburiy kanal', 'callback_data' => 'admin_channels']
            ],
            [
                ['text' => '📮 Reklama/Post', 'callback_data' => 'admin_post']
            ],
            [
                ['text' => '🔧 Maintenance', 'callback_data' => 'admin_maintenance']
            ],
            [
                ['text' => '🚫 Ban qilish', 'callback_data' => 'admin_ban']
            ],
            [
                ['text' => '❌ Yopish', 'callback_data' => 'close_admin']
            ]
        ]
    ];
}

// Check if code is safe
function checkCodeSafety($code) {
    $forbidden = [
        'vendor/autoload.php',
        'mysql_connect',
        'mysqli_connect',
        'PDO',
        'exec(',
        'shell_exec',
        'system(',
        'passthru',
        'proc_open',
        'popen',
        'curl_exec',
        'curl_multi_exec',
        'parse_ini_file',
        'show_source',
        'python',
        'subprocess',
        'os.system',
        'eval(',
        'base64_decode',
        'assert(',
        'create_function',
        'include(',
        'require(',
        'include_once(',
        'require_once(',
        'file_get_contents("http',
        'file_get_contents(\'http',
        'fopen("http',
        'fopen(\'http',
        'rmdir',
        '__halt_compiler'
    ];
    
    foreach ($forbidden as $pattern) {
        if (stripos($code, $pattern) !== false) {
            return false;
        }
    }
    
    return true;
}

// Calculate directory size
function getDirSize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    
    return $size / 1024 / 1024; // MB
}

// Gemini AI request
function geminiRequest($user_id, $user_message, $bot_token = null) {
    $user = loadUser($user_id);
    
    // Check daily limit
    if ($user['ai_date'] !== date('Y-m-d')) {
        $user['ai_requests_today'] = 0;
        $user['ai_date'] = date('Y-m-d');
        $user['ai_history'] = [];
        saveUser($user_id, $user);
    }
    
    if ($user['ai_requests_today'] >= 20) {
        return ['error' => 'Kunlik 20 so\'rov limiti tugadi! Ertaga qayta urinib ko\'ring.'];
    }
    
    // Build conversation history
    $contents = [];
    foreach ($user['ai_history'] as $msg) {
        $contents[] = $msg;
    }
    
    // System instruction
    $system_instruction = "Siz NONOCHA BOT nomli AI Agent sizning vazifangiz PHP telegram bot kodlarini yozish, tahrirlash va xatolarni tuzatish. Faqat PHP kodlarini yozing, boshqa tillarni ishlatmang. Kodda vendor/autoload.php, MySQL, PDO va xavfli funksiyalarni ishlatmang. Har doim to'liq va ishlaydigan kod yozing.";
    
    if ($bot_token) {
        $system_instruction .= " Bot Token: {$bot_token}";
    }
    
    // Add user message
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $user_message]]
    ];
    
    $request = [
        'contents' => $contents,
        'systemInstruction' => [
            'parts' => [['text' => $system_instruction]]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 8192
        ]
    ];
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    curl_close($ch);
    
    $response = json_decode($result, true);
    
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_response = $response['candidates'][0]['content']['parts'][0]['text'];
        
        // Update history
        $user['ai_history'][] = ['role' => 'user', 'parts' => [['text' => $user_message]]];
        $user['ai_history'][] = ['role' => 'model', 'parts' => [['text' => $ai_response]]];
        
        if (count($user['ai_history']) > 10) {
            $user['ai_history'] = array_slice($user['ai_history'], -10);
        }
        
        $user['ai_requests_today']++;
        saveUser($user_id, $user);
        
        return ['response' => $ai_response];
    }
    
    return ['error' => 'AI dan javob olishda xatolik yuz berdi.'];
}

// Handle /start command
function handleStart($chat_id, $user_id, $username, $first_name) {
    $config = loadConfig();
    
    // Check if banned
    if (in_array($user_id, $config['banned_users'])) {
        sendMessage($chat_id, "❌ Siz botdan foydalanishdan cheklangansiz!");
        return;
    }
    
    // Check maintenance
    if ($config['maintenance'] && $user_id != ADMIN_ID) {
        $until = date('Y-m-d H:i:s', $config['maintenance_until']);
        sendMessage($chat_id, "🔧 Bot texnik ishlar olib borilmoqda!\n\n⏰ Tugash vaqti: {$until}");
        return;
    }
    
    // Check mandatory channels
    if (!empty($config['channels']) && $user_id != ADMIN_ID) {
        $not_subscribed = [];
        foreach ($config['channels'] as $channel) {
            if (!checkSubscription($user_id, $channel)) {
                $not_subscribed[] = $channel;
            }
        }
        
        if (!empty($not_subscribed)) {
            $text = "⚠️ Botdan foydalanish uchun quyidagi kanallarga a'zo bo'ling:\n\n";
            $buttons = [];
            
            foreach ($not_subscribed as $channel) {
                $text .= "📢 {$channel}\n";
                $buttons[] = [['text' => "Kanalga o'tish", 'url' => "https://t.me/" . ltrim($channel, '@')]];
            }
            
            $buttons[] = [['text' => "✅ Tekshirish", 'callback_data' => 'check_subscription']];
            
            sendMessage($chat_id, $text, ['inline_keyboard' => $buttons]);
            return;
        }
    }
    
    // Load user
    $user = loadUser($user_id);
    
    $mention = $username ? "@{$username}" : $first_name;
    $welcome = "👋 Xush kelibsiz, {$mention}!\n\n";
    $welcome .= "🤖 Bu bot orqali siz o'zingizning Telegram botlaringizni yaratishingiz va boshqarishingiz mumkin!\n\n";
    $welcome .= "💎 Joriy tarifingiz: <b>" . strtoupper($user['plan']) . "</b>\n";
    $welcome .= "💰 Balans: <b>{$user['balance']}</b> olmos\n\n";
    $welcome .= "Quyidagi menyudan kerakli bo'limni tanlang:";
    
    if ($user_id == ADMIN_ID) {
        $welcome .= "\n\n👑 <b>Admin Panel</b>";
    }
    
    sendMessage($chat_id, $welcome, getMainMenu($user_id));
}

// Process incoming update
$update = json_decode(file_get_contents('php://input'), true);
file_put_contents(__DIR__ . '/update_log.txt', date('Y-m-d H:i:s') . "\n" . json_encode($update, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);

if (!$update) exit;

// Handle message
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $username = isset($message['from']['username']) ? $message['from']['username'] : '';
    $first_name = isset($message['from']['first_name']) ? $message['from']['first_name'] : 'User';
    $text = isset($message['text']) ? $message['text'] : '';
    
    // Load config
    $config = loadConfig();
    
    // Check banned
    if (in_array($user_id, $config['banned_users']) && $user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Siz botdan foydalanishdan cheklangansiz!");
        exit;
    }
    
    // /start command
    if ($text == '/start') {
        handleStart($chat_id, $user_id, $username, $first_name);
        exit;
    }
    
    // /admin command
    if ($text == '/admin' && $user_id == ADMIN_ID) {
        sendMessage($chat_id, "👑 <b>Admin Panel</b>\n\nKerakli bo'limni tanlang:", getAdminMenu());
        exit;
    }
    
    // Handle file upload for manual bot creation
    if (isset($message['document'])) {
        $user = loadUser($user_id);
        
        if (isset($user['temp_action']) && $user['temp_action'] == 'waiting_code_file') {
            $file_id = $message['document']['file_id'];
            $file_name = $message['document']['file_name'];
            
            // Check file extension
            if (!preg_match('/\.php$/i', $file_name)) {
                sendMessage($chat_id, "❌ Faqat .php fayllarni yuklash mumkin!");
                exit;
            }
            
            // Get file
            $file_info = apiRequest('getFile', ['file_id' => $file_id]);
            if (!$file_info['ok']) {
                sendMessage($chat_id, "❌ Faylni yuklashda xatolik!");
                exit;
            }
            
            $file_path = $file_info['result']['file_path'];
            $file_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/{$file_path}";
            $code = file_get_contents($file_url);
            
            // Check code safety
            if (!checkCodeSafety($code)) {
                sendMessage($chat_id, "❌ Kodda taqiqlangan funksiyalar topildi!\n\n🚫 Taqiqlangan:\n- vendor/autoload.php\n- MySQL/PDO\n- Python kod\n- Xavfli funksiyalar (exec, shell_exec, va boshqalar)");
                
                unset($user['temp_action']);
                unset($user['temp_bot_token']);
                saveUser($user_id, $user);
                exit;
            }
            
            // Check if bot token exists in code
            $bot_token = $user['temp_bot_token'];
            if (stripos($code, $bot_token) === false) {
                sendMessage($chat_id, "⚠️ Kodda bot token topilmadi!\n\nBot token: <code>{$bot_token}</code>\n\nKodingizga bot tokenni qo'shing.");
                exit;
            }
            
            // Get bot info
            $bot_info = getBotInfo($bot_token);
            if (!$bot_info['ok']) {
                sendMessage($chat_id, "❌ Bot token noto'g'ri!");
                unset($user['temp_action']);
                unset($user['temp_bot_token']);
                saveUser($user_id, $user);
                exit;
            }
            
            $bot_username = $bot_info['result']['username'];
            
            // Check bot limit
            $config = loadConfig();
            $plan_limits = $config['plans'][$user['plan']];
            
            if (count($user['bots']) >= $plan_limits['bots']) {
                sendMessage($chat_id, "❌ Bot yaratish limiti tugadi!\n\n📊 Tarifingiz: <b>" . strtoupper($user['plan']) . "</b>\n🤖 Maksimal botlar: {$plan_limits['bots']}\n\n💡 Ko'proq bot yaratish uchun tarifingizni yangilang!");
                
                unset($user['temp_action']);
                unset($user['temp_bot_token']);
                saveUser($user_id, $user);
                exit;
            }
            
            // Find available bot number
            $bot_numbers = [];
            foreach ($user['bots'] as $bot) {
                $bot_numbers[] = $bot['bot_number'];
            }
            
            $bot_number = 1;
            while (in_array($bot_number, $bot_numbers) && $bot_number <= $plan_limits['bots']) {
                $bot_number++;
            }
            
            // Create bot directory
            $bot_dir = BOTS_DIR . "{$user_id}/{$bot_number}/";
            if (!file_exists($bot_dir)) {
                mkdir($bot_dir, 0777, true);
            }
            
            // Save code
            file_put_contents($bot_dir . 'index.php', $code);
            file_put_contents($bot_dir . 'log.txt', "Bot yaratildi: " . date('Y-m-d H:i:s') . "\n");
            
            // Set webhook
            $webhook_url = WEBHOOK_URL . "bots/{$user_id}/{$bot_number}/index.php";
            $webhook_result = setWebhook($bot_token, $webhook_url);
            
            if (!$webhook_result['ok']) {
                sendMessage($chat_id, "❌ Webhook o'rnatishda xatolik!");
                unset($user['temp_action']);
                unset($user['temp_bot_token']);
                saveUser($user_id, $user);
                exit;
            }
            
            // Save bot info
            $user['bots'][] = [
                'bot_number' => $bot_number,
                'username' => $bot_username,
                'token' => $bot_token,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Update storage
            $user['storage_used'] = getDirSize(BOTS_DIR . "{$user_id}/");
            
            unset($user['temp_action']);
            unset($user['temp_bot_token']);
            saveUser($user_id, $user);
            
            // Update stats
            $stats = loadStats();
            $stats['total_bots']++;
            $stats['total_storage_used'] = getDirSize(BOTS_DIR);
            saveStats($stats);
            
            sendMessage($chat_id, "✅ Bot muvaffaqiyatli yaratildi!\n\n🤖 Bot: @{$bot_username}\n📍 Bot raqami: {$bot_number}\n\n🔗 Bot ishga tushdi!");
            exit;
        }
    }
    
    // Handle admin adding balance
    if ($user_id == ADMIN_ID && isset($message['reply_to_message'])) {
        $replied_text = $message['reply_to_message']['text'];
        
        if (strpos($replied_text, 'Foydalanuvchi ID ni yuboring') !== false) {
            $target_user_id = trim($text);
            
            if (!is_numeric($target_user_id)) {
                sendMessage($chat_id, "❌ Noto'g'ri ID!");
                exit;
            }
            
            $user = loadUser($user_id);
            $user['temp_admin_balance_user'] = $target_user_id;
            saveUser($user_id, $user);
            
            $sent = sendMessage($chat_id, "💰 Qo'shiladigan olmos miqdorini yuboring:");
            exit;
        }
        
        if (strpos($replied_text, 'olmos miqdorini yuboring') !== false) {
            $user = loadUser($user_id);
            
            if (!isset($user['temp_admin_balance_user'])) {
                sendMessage($chat_id, "❌ Avval foydalanuvchi ID ni yuboring!");
                exit;
            }
            
            $amount = intval($text);
            if ($amount <= 0) {
                sendMessage($chat_id, "❌ Miqdor 0 dan katta bo'lishi kerak!");
                exit;
            }
            
            $target_user_id = $user['temp_admin_balance_user'];
            $target_user = loadUser($target_user_id);
            $target_user['balance'] += $amount;
            saveUser($target_user_id, $target_user);
            
            unset($user['temp_admin_balance_user']);
            saveUser($user_id, $user);
            
            sendMessage($chat_id, "✅ {$amount} olmos qo'shildi!\n\n👤 User ID: {$target_user_id}\n💰 Yangi balans: {$target_user['balance']} olmos");
            sendMessage($target_user_id, "🎁 Sizning balansingizga {$amount} olmos qo'shildi!\n\n💰 Joriy balans: {$target_user['balance']} olmos");
            exit;
        }
        
        // Handle channel adding
        if (strpos($replied_text, 'Kanal username') !== false) {
            $channel = trim($text);
            
            if (!preg_match('/^@/', $channel)) {
                $channel = '@' . $channel;
            }
            
            $config = loadConfig();
            if (!in_array($channel, $config['channels'])) {
                $config['channels'][] = $channel;
                saveConfig($config);
                sendMessage($chat_id, "✅ Kanal qo'shildi: {$channel}");
            } else {
                sendMessage($chat_id, "⚠️ Kanal allaqachon mavjud!");
            }
            exit;
        }
        
        // Handle ban user
        if (strpos($replied_text, 'Ban qilinadigan foydalanuvchi ID') !== false) {
            $ban_user_id = intval(trim($text));
            
            if ($ban_user_id <= 0) {
                sendMessage($chat_id, "❌ Noto'g'ri ID!");
                exit;
            }
            
            $config = loadConfig();
            
            if (!in_array($ban_user_id, $config['banned_users'])) {
                $config['banned_users'][] = $ban_user_id;
                saveConfig($config);
                
                $stats = loadStats();
                $stats['total_banned']++;
                saveStats($stats);
                
                sendMessage($chat_id, "✅ Foydalanuvchi ban qilindi!\n\n👤 User ID: {$ban_user_id}");
                sendMessage($ban_user_id, "❌ Siz botdan foydalanishdan cheklangansiz!");
            } else {
                sendMessage($chat_id, "⚠️ Foydalanuvchi allaqachon banlangan!");
            }
            exit;
        }
        
        // Handle unban user
        if (strpos($replied_text, 'Bandan chiqariladigan foydalanuvchi ID') !== false) {
            $unban_user_id = intval(trim($text));
            
            if ($unban_user_id <= 0) {
                sendMessage($chat_id, "❌ Noto'g'ri ID!");
                exit;
            }
            
            $config = loadConfig();
            
            if (($key = array_search($unban_user_id, $config['banned_users'])) !== false) {
                unset($config['banned_users'][$key]);
                $config['banned_users'] = array_values($config['banned_users']);
                saveConfig($config);
                
                $stats = loadStats();
                $stats['total_banned']--;
                saveStats($stats);
                
                sendMessage($chat_id, "✅ Foydalanuvchi bandan chiqarildi!\n\n👤 User ID: {$unban_user_id}");
                sendMessage($unban_user_id, "✅ Siz botdan foydalanish huquqiga ega bo'ldingiz!");
            } else {
                sendMessage($chat_id, "⚠️ Foydalanuvchi banlangan emas!");
            }
            exit;
        }
        
        // Handle broadcast message
        if (strpos($replied_text, 'Barcha foydalanuvchilarga yuboriladigan xabarni yozing') !== false) {
            $broadcast_text = $text;
            
            $user_files = glob(USERS_DIR . '*.json');
            $sent_count = 0;
            
            foreach ($user_files as $user_file) {
                $target_user_id = intval(basename($user_file, '.json'));
                
                if ($target_user_id > 0) {
                    $result = sendMessage($target_user_id, $broadcast_text);
                    if ($result['ok']) {
                        $sent_count++;
                    }
                    usleep(50000); // 50ms delay
                }
            }
            
            sendMessage($chat_id, "✅ Xabar yuborildi!\n\n📊 Jami: {$sent_count} ta foydalanuvchi");
            exit;
        }
        
        // Handle maintenance time
        if (strpos($replied_text, 'Maintenance muddatini kiriting') !== false) {
            $time_input = trim($text);
            
            // Parse time (e.g., "2h", "30m", "1d")
            preg_match('/(\d+)([hmd])/i', $time_input, $matches);
            
            if (!$matches) {
                sendMessage($chat_id, "❌ Noto'g'ri format! Misol: 2h (2 soat), 30m (30 daqiqa), 1d (1 kun)");
                exit;
            }
            
            $amount = intval($matches[1]);
            $unit = strtolower($matches[2]);
            
            $seconds = 0;
            switch ($unit) {
                case 'h':
                    $seconds = $amount * 3600;
                    break;
                case 'm':
                    $seconds = $amount * 60;
                    break;
                case 'd':
                    $seconds = $amount * 86400;
                    break;
            }
            
            $config = loadConfig();
            $config['maintenance'] = true;
            $config['maintenance_until'] = time() + $seconds;
            saveConfig($config);
            
            $until = date('Y-m-d H:i:s', $config['maintenance_until']);
            sendMessage($chat_id, "🔧 Maintenance rejimi yoqildi!\n\n⏰ Tugash vaqti: {$until}");
            exit;
        }
    }
    
    // Handle contact admin messages
    if ($user_id != ADMIN_ID) {
        $user = loadUser($user_id);
        
        if (isset($user['temp_action']) && $user['temp_action'] == 'contact_admin') {
            $admin_message = "📬 Yangi xabar!\n\n";
            $admin_message .= "👤 User: " . ($username ? "@{$username}" : $first_name) . "\n";
            $admin_message .= "🆔 ID: {$user_id}\n\n";
            $admin_message .= "💬 Xabar:\n{$text}";
            
            sendMessage(ADMIN_ID, $admin_message);
            sendMessage($chat_id, "✅ Xabaringiz adminga yuborildi!");
            
            unset($user['temp_action']);
            saveUser($user_id, $user);
            exit;
        }
        
        // Handle AI Agent conversation
        if (isset($user['temp_action']) && $user['temp_action'] == 'ai_agent_chat') {
            sendMessage($chat_id, "🤔 NONOCHA Bot o'ylayapti...");
            
            $bot_token = isset($user['temp_ai_bot_token']) ? $user['temp_ai_bot_token'] : null;
            $bot_number = isset($user['temp_ai_bot_number']) ? $user['temp_ai_bot_number'] : null;
            
            $ai_result = geminiRequest($user_id, $text, $bot_token);
            
            if (isset($ai_result['error'])) {
                sendMessage($chat_id, "❌ " . $ai_result['error']);
                exit;
            }
            
            $ai_response = $ai_result['response'];
            
            // Check if response contains PHP code
            if (preg_match('/```php\s*(.+?)\s*```/s', $ai_response, $matches)) {
                $code = $matches[1];
                
                // Check code safety
                if (!checkCodeSafety($code)) {
                    sendMessage($chat_id, "❌ AI xavfli kod yaratdi! Iltimos, boshqa so'rov yuboring.");
                    exit;
                }
                
                if ($bot_number && $bot_token) {
                    // Save code to file
                    $bot_dir = BOTS_DIR . "{$user_id}/{$bot_number}/";
                    if (!file_exists($bot_dir)) {
                        mkdir($bot_dir, 0777, true);
                    }
                    
                    file_put_contents($bot_dir . 'index.php', $code);
                    file_put_contents($bot_dir . 'log.txt', "AI tomonidan yangilandi: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                    
                    // Update storage
                    $user = loadUser($user_id);
                    $user['storage_used'] = getDirSize(BOTS_DIR . "{$user_id}/");
                    saveUser($user_id, $user);
                    
                    sendMessage($chat_id, "✅ Kod yozildi va saqlandi!\n\n" . substr($ai_response, 0, 3000));
                } else {
                    sendMessage($chat_id, $ai_response);
                }
            } else {
                sendMessage($chat_id, $ai_response);
            }
            
            exit;
        }
        
        // Handle manual bot token input
        if (isset($user['temp_action']) && $user['temp_action'] == 'waiting_bot_token') {
            $bot_token = trim($text);
            
            // Validate token format
            if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $bot_token)) {
                sendMessage($chat_id, "❌ Noto'g'ri token formati!\n\nTo'g'ri format: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz");
                exit;
            }
            
            // Check bot info
            $bot_info = getBotInfo($bot_token);
            
            if (!$bot_info['ok']) {
                sendMessage($chat_id, "❌ Bot topilmadi! Token noto'g'ri.");
                exit;
            }
            
            $bot_username = $bot_info['result']['username'];
            
            // Save token temporarily
            $user['temp_bot_token'] = $bot_token;
            $user['temp_action'] = 'waiting_code_file';
            saveUser($user_id, $user);
            
            sendMessage($chat_id, "✅ Bot topildi: @{$bot_username}\n\n📎 Endi bot uchun PHP kod faylini yuboring (.php)");
            exit;
        }
        
        // Handle AI bot token input
        if (isset($user['temp_action']) && $user['temp_action'] == 'waiting_ai_bot_token') {
            $bot_token = trim($text);
            
            // Validate token format
            if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $bot_token)) {
                sendMessage($chat_id, "❌ Noto'g'ri token formati!\n\nTo'g'ri format: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz");
                exit;
            }
            
            // Check bot info
            $bot_info = getBotInfo($bot_token);
            
            if (!$bot_info['ok']) {
                sendMessage($chat_id, "❌ Bot topilmadi! Token noto'g'ri.");
                exit;
            }
            
            $bot_username = $bot_info['result']['username'];
            
            // Check bot limit
            $config = loadConfig();
            $plan_limits = $config['plans'][$user['plan']];
            
            if (count($user['bots']) >= $plan_limits['bots']) {
                sendMessage($chat_id, "❌ Bot yaratish limiti tugadi!\n\n📊 Tarifingiz: <b>" . strtoupper($user['plan']) . "</b>\n🤖 Maksimal botlar: {$plan_limits['bots']}\n\n💡 Ko'proq bot yaratish uchun tarifingizni yangilang!");
                
                unset($user['temp_action']);
                saveUser($user_id, $user);
                exit;
            }
            
            // Find available bot number
            $bot_numbers = [];
            foreach ($user['bots'] as $bot) {
                $bot_numbers[] = $bot['bot_number'];
            }
            
            $bot_number = 1;
            while (in_array($bot_number, $bot_numbers) && $bot_number <= $plan_limits['bots']) {
                $bot_number++;
            }
            
            // Create bot directory
            $bot_dir = BOTS_DIR . "{$user_id}/{$bot_number}/";
            if (!file_exists($bot_dir)) {
                mkdir($bot_dir, 0777, true);
            }
            
            // Create basic bot code
            $basic_code = "<?php\n// Bot yaratilmoqda...\nfile_put_contents('log.txt', 'Bot ishga tushdi: ' . date('Y-m-d H:i:s'));\n?>";
            file_put_contents($bot_dir . 'index.php', $basic_code);
            file_put_contents($bot_dir . 'log.txt', "Bot yaratildi: " . date('Y-m-d H:i:s') . "\n");
            
            // Save bot info
            $user['bots'][] = [
                'bot_number' => $bot_number,
                'username' => $bot_username,
                'token' => $bot_token,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $user['temp_ai_bot_token'] = $bot_token;
            $user['temp_ai_bot_number'] = $bot_number;
            $user['temp_action'] = 'ai_agent_chat';
            $user['storage_used'] = getDirSize(BOTS_DIR . "{$user_id}/");
            saveUser($user_id, $user);
            
            // Update stats
            $stats = loadStats();
            $stats['total_bots']++;
            $stats['total_storage_used'] = getDirSize(BOTS_DIR);
            saveStats($stats);
            
            sendMessage($chat_id, "✅ Bot yaratildi: @{$bot_username}\n\n🤖 NONOCHA AI Agent tayyor!\n\n💬 Menga botingiz uchun qanday funksiya kerakligini ayting, men kod yozaman.\n\nMisol:\n- \"Oddiy echo bot yarat\"\n- \"Inline tugmali menyu qo'sh\"\n- \"Foydalanuvchilarni saqlaydigan bot yarat\"");
            exit;
        }
    }
}

// Handle callback query
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $callback_id = $callback['id'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $user_id = $callback['from']['id'];
    $data = $callback['data'];
    $username = isset($callback['from']['username']) ? $callback['from']['username'] : '';
    $first_name = isset($callback['from']['first_name']) ? $callback['from']['first_name'] : 'User';
    
    $config = loadConfig();
    $user = loadUser($user_id);
    
    // Check subscription
    if ($data == 'check_subscription') {
        $not_subscribed = [];
        foreach ($config['channels'] as $channel) {
            if (!checkSubscription($user_id, $channel)) {
                $not_subscribed[] = $channel;
            }
        }
        
        if (empty($not_subscribed)) {
            answerCallback($callback_id, "✅ Tasdiqlandi!", false);
            handleStart($chat_id, $user_id, $username, $first_name);
        } else {
            answerCallback($callback_id, "❌ Hali barcha kanallarga azo bo'lmadingiz!", true);
        }
        exit;
    }
    
    // Main menu
    if ($data == 'main_menu') {
        $mention = $username ? "@{$username}" : $first_name;
        $welcome = "👋 Xush kelibsiz, {$mention}!\n\n";
        $welcome .= "💎 Joriy tarifingiz: <b>" . strtoupper($user['plan']) . "</b>\n";
        $welcome .= "💰 Balans: <b>{$user['balance']}</b> olmos\n\n";
        $welcome .= "Quyidagi menyudan kerakli bo'limni tanlang:";
        
        editMessage($chat_id, $message_id, $welcome, getMainMenu($user_id));
        answerCallback($callback_id);
        exit;
    }
    
    // AI Bot Create
    if ($data == 'ai_bot_create') {
        $text = "🤖 <b>AI Agent Bot Yaratish</b>\n\n";
        $text .= "NONOCHA AI Agent sizning botingizni yaratadi!\n\n";
        $text .= "📊 Kunlik limit: 20 so'rov\n";
        $text .= "✅ Bugungi so'rovlar: {$user['ai_requests_today']}/20\n\n";
        $text .= "🔹 Avval bot tokeningizni yuboring (BotFather dan oling)";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        
        $user['temp_action'] = 'waiting_ai_bot_token';
        saveUser($user_id, $user);
        exit;
    }
    
    // Manual Bot Create
    if ($data == 'manual_bot_create') {
        $text = "💻 <b>Kod orqali Bot Yaratish</b>\n\n";
        $text .= "1️⃣ Bot tokenini yuboring (BotFather dan)\n";
        $text .= "2️⃣ PHP kod faylini yuklang\n";
        $text .= "3️⃣ Avtomatik webhook o'rnatiladi\n\n";
        $text .= "⚠️ Taqiqlanganlar:\n";
        $text .= "- vendor/autoload.php\n";
        $text .= "- MySQL/PDO\n";
        $text .= "- Python kod\n";
        $text .= "- Xavfli funksiyalar\n\n";
        $text .= "📝 Endi bot tokeningizni yuboring:";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        
        $user['temp_action'] = 'waiting_bot_token';
        saveUser($user_id, $user);
        exit;
    }
    
    // File Manager
    if ($data == 'file_manager') {
        $user_bots_dir = BOTS_DIR . "{$user_id}/";
        
        if (!is_dir($user_bots_dir) || empty($user['bots'])) {
            answerCallback($callback_id, "❌ Sizda hali botlar yo'q!", true);
            exit;
        }
        
        $text = "📁 <b>File Manager</b>\n\n";
        $text .= "📊 Storage: " . number_format($user['storage_used'], 2) . " MB\n\n";
        $text .= "Bot raqamini tanlang:";
        
        $buttons = [];
        foreach ($user['bots'] as $bot) {
            $buttons[] = [['text' => "🤖 Bot #{$bot['bot_number']} (@{$bot['username']})", 'callback_data' => "fm_bot_{$bot['bot_number']}"]];
        }
        $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        answerCallback($callback_id);
        exit;
    }
    
    // File Manager - View Bot Files
    if (preg_match('/^fm_bot_(\d+)$/', $data, $matches)) {
        $bot_number = intval($matches[1]);
        $bot_dir = BOTS_DIR . "{$user_id}/{$bot_number}/";
        
        if (!is_dir($bot_dir)) {
            answerCallback($callback_id, "❌ Bot topilmadi!", true);
            exit;
        }
        
        $files = scandir($bot_dir);
        $text = "📁 <b>Bot #{$bot_number} Fayllar</b>\n\n";
        
        $buttons = [];
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $size = filesize($bot_dir . $file);
            $size_kb = number_format($size / 1024, 2);
            
            $text .= "📄 {$file} ({$size_kb} KB)\n";
            $buttons[] = [
                ['text' => "📄 {$file}", 'callback_data' => "fm_file_{$bot_number}_{$file}"],
                ['text' => '❌', 'callback_data' => "fm_delete_{$bot_number}_{$file}"]
            ];
        }
        
        $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'file_manager']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        answerCallback($callback_id);
        exit;
    }
    
    // File Manager - View File
    if (preg_match('/^fm_file_(\d+)_(.+)$/', $data, $matches)) {
        $bot_number = intval($matches[1]);
        $file_name = $matches[2];
        $file_path = BOTS_DIR . "{$user_id}/{$bot_number}/{$file_name}";
        
        if (!file_exists($file_path)) {
            answerCallback($callback_id, "❌ Fayl topilmadi!", true);
            exit;
        }
        
        // Send file to user
        $file_handle = fopen($file_path, 'r');
        
        apiRequest('sendDocument', [
            'chat_id' => $chat_id,
            'document' => new CURLFile($file_path),
            'caption' => "📄 Bot #{$bot_number} - {$file_name}"
        ]);
        
        answerCallback($callback_id, "✅ Fayl yuborildi!");
        exit;
    }
    
    // File Manager - Delete File
    if (preg_match('/^fm_delete_(\d+)_(.+)$/', $data, $matches)) {
        $bot_number = intval($matches[1]);
        $file_name = $matches[2];
        $file_path = BOTS_DIR . "{$user_id}/{$bot_number}/{$file_name}";
        
        if (file_exists($file_path)) {
            unlink($file_path);
            
            $user['storage_used'] = getDirSize(BOTS_DIR . "{$user_id}/");
            saveUser($user_id, $user);
            
            answerCallback($callback_id, "✅ Fayl o'chirildi!");
            
            // Refresh file list
            $bot_dir = BOTS_DIR . "{$user_id}/{$bot_number}/";
            $files = scandir($bot_dir);
            $text = "📁 <b>Bot #{$bot_number} Fayllar</b>\n\n";
            
            $buttons = [];
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') continue;
                
                $size = filesize($bot_dir . $file);
                $size_kb = number_format($size / 1024, 2);
                
                $text .= "📄 {$file} ({$size_kb} KB)\n";
                $buttons[] = [
                    ['text' => "📄 {$file}", 'callback_data' => "fm_file_{$bot_number}_{$file}"],
                    ['text' => '❌', 'callback_data' => "fm_delete_{$bot_number}_{$file}"]
                ];
            }
            
            $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'file_manager']];
            
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        } else {
            answerCallback($callback_id, "❌ Fayl topilmadi!", true);
        }
        exit;
    }
    
    // My Bots
    if ($data == 'my_bots') {
        if (empty($user['bots'])) {
            $text = "🤖 <b>Mening Botlarim</b>\n\n";
            $text .= "Sizda hali botlar yo'q!\n\n";
            $text .= "Bot yaratish uchun menyudan birini tanlang.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']]
                ]
            ];
            
            editMessage($chat_id, $message_id, $text, $keyboard);
            answerCallback($callback_id);
            exit;
        }
        
        $config = loadConfig();
        $plan_limits = $config['plans'][$user['plan']];
        
        $text = "🤖 <b>Mening Botlarim</b>\n\n";
        $text .= "📊 Botlar: " . count($user['bots']) . "/" . $plan_limits['bots'] . "\n";
        $text .= "💾 Storage: " . number_format($user['storage_used'], 2) . "/" . $plan_limits['storage'] . " MB\n\n";
        
        $buttons = [];
        foreach ($user['bots'] as $index => $bot) {
            $text .= "#{$bot['bot_number']} @{$bot['username']}\n";
            $text .= "   📅 {$bot['created_at']}\n\n";
            
            $buttons[] = [['text' => "🗑 Bot #{$bot['bot_number']} o'chirish", 'callback_data' => "delete_bot_{$bot['bot_number']}"]];
        }
        
        $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        answerCallback($callback_id);
        exit;
    }
    
    // Delete Bot
    if (preg_match('/^delete_bot_(\d+)$/', $data, $matches)) {
        $bot_number = intval($matches[1]);
        
        // Find and remove bot
        $bot_index = -1;
        foreach ($user['bots'] as $index => $bot) {
            if ($bot['bot_number'] == $bot_number) {
                $bot_index = $index;
                break;
            }
        }
        
        if ($bot_index === -1) {
            answerCallback($callback_id, "❌ Bot topilmadi!", true);
            exit;
        }
        
        // Delete bot directory
        $bot_dir = BOTS_DIR . "{$user_id}/{$bot_number}/";
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
        
        // Remove from user data
        array_splice($user['bots'], $bot_index, 1);
        $user['storage_used'] = getDirSize(BOTS_DIR . "{$user_id}/");
        saveUser($user_id, $user);
        
        // Update stats
        $stats = loadStats();
        $stats['total_bots']--;
        $stats['total_storage_used'] = getDirSize(BOTS_DIR);
        saveStats($stats);
        
        answerCallback($callback_id, "✅ Bot o'chirildi!");
        
        // Refresh list
        if (empty($user['bots'])) {
            $text = "🤖 <b>Mening Botlarim</b>\n\n";
            $text .= "Sizda hali botlar yo'q!";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']]
                ]
            ];
            
            editMessage($chat_id, $message_id, $text, $keyboard);
        } else {
            // Show updated list
            $config = loadConfig();
            $plan_limits = $config['plans'][$user['plan']];
            
            $text = "🤖 <b>Mening Botlarim</b>\n\n";
            $text .= "📊 Botlar: " . count($user['bots']) . "/" . $plan_limits['bots'] . "\n";
            $text .= "💾 Storage: " . number_format($user['storage_used'], 2) . "/" . $plan_limits['storage'] . " MB\n\n";
            
            $buttons = [];
            foreach ($user['bots'] as $bot) {
                $text .= "#{$bot['bot_number']} @{$bot['username']}\n";
                $text .= "   📅 {$bot['created_at']}\n\n";
                
                $buttons[] = [['text' => "🗑 Bot #{$bot['bot_number']} o'chirish", 'callback_data' => "delete_bot_{$bot['bot_number']}"]];
            }
            
            $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']];
            
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        }
        
        exit;
    }
    
    // Cabinet
    if ($data == 'cabinet') {
        $config = loadConfig();
        $plan_limits = $config['plans'][$user['plan']];
        
        $text = "👤 <b>Kabinet</b>\n\n";
        $text .= "📊 Joriy tarif: <b>" . strtoupper($user['plan']) . "</b>\n";
        $text .= "💰 Balans: <b>{$user['balance']}</b> olmos\n\n";
        $text .= "📈 Limitlar:\n";
        $text .= "   🤖 Botlar: " . count($user['bots']) . "/" . $plan_limits['bots'] . "\n";
        $text .= "   💾 Storage: " . number_format($user['storage_used'], 2) . "/" . $plan_limits['storage'] . " MB\n";
        $text .= "   🤖 AI so'rovlar: {$user['ai_requests_today']}/20 (bugun)\n\n";
        $text .= "📅 Qo'shilgan sana: {$user['joined_date']}";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💰 Balans', 'callback_data' => 'cabinet_balance'],
                    ['text' => '💎 Tarif', 'callback_data' => 'cabinet_plan']
                ],
                [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    // Cabinet - Balance
    if ($data == 'cabinet_balance') {
        $text = "💰 <b>Balans</b>\n\n";
        $text .= "💎 Hisobdagi balans: <b>{$user['balance']}</b> olmos\n";
        $text .= "💵 1 olmos = 120 UZS\n\n";
        $text .= "Balansni to'ldirish uchun admin bilan bog'laning:";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '👤 Admin: @WINAIKO', 'url' => 'https://t.me/WINAIKO']],
                [['text' => '◀️ Orqaga', 'callback_data' => 'cabinet']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    // Cabinet - Plan
    if ($data == 'cabinet_plan') {
        $config = loadConfig();
        $plans = $config['plans'];
        
        $text = "💎 <b>Tariflar</b>\n\n";
        
        foreach ($plans as $plan_name => $limits) {
            $emoji = $plan_name == 'free' ? '🆓' : ($plan_name == 'pro' ? '⭐' : '👑');
            $text .= "{$emoji} <b>" . strtoupper($plan_name) . "</b>\n";
            $text .= "   🤖 Botlar: {$limits['bots']} ta\n";
            $text .= "   💾 Storage: {$limits['storage']} MB\n";
            $text .= "   💰 Narxi: {$limits['price']} olmos\n\n";
        }
        
        $text .= "📊 Joriy tarifingiz: <b>" . strtoupper($user['plan']) . "</b>\n";
        $text .= "💰 Balansingiz: <b>{$user['balance']}</b> olmos";
        
        $buttons = [];
        
        foreach ($plans as $plan_name => $limits) {
            if ($plan_name != $user['plan'] && $limits['price'] > 0) {
                $buttons[] = [['text' => "Sotib olish: " . strtoupper($plan_name) . " ({$limits['price']} olmos)", 'callback_data' => "buy_plan_{$plan_name}"]];
            }
        }
        
        $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'cabinet']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        answerCallback($callback_id);
        exit;
    }
    
    // Buy Plan
    if (preg_match('/^buy_plan_(.+)$/', $data, $matches)) {
        $plan_name = $matches[1];
        $config = loadConfig();
        
        if (!isset($config['plans'][$plan_name])) {
            answerCallback($callback_id, "❌ Tarif topilmadi!", true);
            exit;
        }
        
        $plan = $config['plans'][$plan_name];
        
        if ($user['balance'] < $plan['price']) {
            answerCallback($callback_id, "❌ Balansingiz yetarli emas! Kerak: {$plan['price']} olmos, Sizda: {$user['balance']} olmos", true);
            exit;
        }
        
        $user['balance'] -= $plan['price'];
        $user['plan'] = $plan_name;
        saveUser($user_id, $user);
        
        answerCallback($callback_id, "✅ Tarif muvaffaqiyatli sotib olindi!");
        
        $text = "✅ <b>Tarif yangilandi!</b>\n\n";
        $text .= "💎 Yangi tarif: <b>" . strtoupper($plan_name) . "</b>\n";
        $text .= "💰 To'langan: {$plan['price']} olmos\n";
        $text .= "💰 Qolgan balans: {$user['balance']} olmos\n\n";
        $text .= "📈 Yangi limitlar:\n";
        $text .= "   🤖 Botlar: {$plan['bots']} ta\n";
        $text .= "   💾 Storage: {$plan['storage']} MB";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '◀️ Orqaga', 'callback_data' => 'cabinet']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        exit;
    }
    
    // Help
    if ($data == 'help') {
        $text = "❓ <b>Yordam</b>\n\n";
        $text .= "<b>Bot qo'llanmasi:</b>\n\n";
        $text .= "1️⃣ <b>Bot yaratish:</b>\n";
        $text .= "   - @BotFather dan yangi bot yarating\n";
        $text .= "   - Bot tokenini oling\n";
        $text .= "   - AI Agent yoki Kod orqali bot yarating\n\n";
        $text .= "2️⃣ <b>BotFather dan token olish:</b>\n";
        $text .= "   - @BotFather ga /newbot yuboring\n";
        $text .= "   - Bot nomini kiriting\n";
        $text .= "   - Bot username kiriting\n";
        $text .= "   - Tokenni nusxa oling\n\n";
        $text .= "3️⃣ <b>Kod tahrirlash:</b>\n";
        $text .= "   - Play Market: QuickEdit, AIDE\n";
        $text .= "   - App Store: Textastic, Koder\n\n";
        $text .= "4️⃣ <b>Tarif yangilash:</b>\n";
        $text .= "   - Kabinet > Tarif\n";
        $text .= "   - Kerakli tarifni tanlang\n";
        $text .= "   - Balansdan to'lang\n\n";
        $text .= "💡 Qo'shimcha savol bo'lsa admin bilan bog'laning!";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📞 Admin: @WINAIKO', 'url' => 'https://t.me/WINAIKO']],
                [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    // Contact Admin
    if ($data == 'contact_admin') {
        $text = "📞 <b>Admin bilan Bog'lanish</b>\n\n";
        $text .= "Savolingizni yoki murojaatingizni yozing, xabar admin ga yuboriladi.\n\n";
        $text .= "Admin: @WINAIKO";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '👤 To\'g\'ridan-to\'g\'ri yozish', 'url' => 'https://t.me/WINAIKO']],
                [['text' => '◀️ Orqaga', 'callback_data' => 'main_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        
        $user['temp_action'] = 'contact_admin';
        saveUser($user_id, $user);
        exit;
    }
    
    // ===== ADMIN PANEL =====
    
    if ($user_id != ADMIN_ID) {
        answerCallback($callback_id, "❌ Sizda ruxsat yo'q!", true);
        exit;
    }
    
    // Admin Stats
    if ($data == 'admin_stats') {
        $stats = loadStats();
        
        $text = "📊 <b>Statistika</b>\n\n";
        $text .= "👥 Jami foydalanuvchilar: {$stats['total_users']}\n";
        $text .= "🤖 Jami botlar: {$stats['total_bots']}\n";
        $text .= "🚫 Ban qilinganlar: {$stats['total_banned']}\n";
        $text .= "💾 Umumiy storage: " . number_format($stats['total_storage_used'], 2) . " MB\n\n";
        $text .= "📅 Vaqt: " . date('Y-m-d H:i:s');
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 Yangilash', 'callback_data' => 'admin_stats']],
                [['text' => '◀️ Orqaga', 'callback_data' => 'admin_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    // Admin Menu
    if ($data == 'admin_menu') {
        sendMessage($chat_id, "👑 <b>Admin Panel</b>\n\nKerakli bo'limni tanlang:", getAdminMenu());
        answerCallback($callback_id);
        exit;
    }
    
    // Admin Plans
    if ($data == 'admin_plans') {
        $config = loadConfig();
        $plans = $config['plans'];
        
        $text = "💎 <b>Tariflarni Boshqarish</b>\n\n";
        
        foreach ($plans as $plan_name => $limits) {
            $text .= "<b>" . strtoupper($plan_name) . ":</b>\n";
            $text .= "   Botlar: {$limits['bots']}\n";
            $text .= "   Storage: {$limits['storage']} MB\n";
            $text .= "   Narxi: {$limits['price']} olmos\n\n";
        }
        
        $text .= "⚠️ Tariflarni o'zgartirish uchun config.json faylini tahrirlang.";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '◀️ Orqaga', 'callback_data' => 'admin_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    // Admin Balance
    if ($data == 'admin_balance') {
        answerCallback($callback_id);
        sendMessage($chat_id, "💰 <b>Balans Qo'shish</b>\n\nFoydalanuvchi ID ni yuboring:");
        exit;
    }
    
    // Admin Channels
    if ($data == 'admin_channels') {
        $config = loadConfig();
        
        $text = "📢 <b>Majburiy Kanallar</b>\n\n";
        
        if (empty($config['channels'])) {
            $text .= "Majburiy kanallar yo'q.\n\n";
        } else {
            foreach ($config['channels'] as $index => $channel) {
                $text .= ($index + 1) . ". {$channel}\n";
            }
            $text .= "\n";
        }
        
        $buttons = [];
        
        foreach ($config['channels'] as $channel) {
            $buttons[] = [['text' => "❌ {$channel}", 'callback_data' => "remove_channel_" . base64_encode($channel)]];
        }
        
        $buttons[] = [['text' => '➕ Kanal qo\'shish', 'callback_data' => 'add_channel']];
        $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'admin_menu']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        answerCallback($callback_id);
        exit;
    }
    
    // Add Channel
    if ($data == 'add_channel') {
        answerCallback($callback_id);
        sendMessage($chat_id, "📢 Kanal username ni yuboring (masalan: @channel):");
        exit;
    }
    
    // Remove Channel
    if (preg_match('/^remove_channel_(.+)$/', $data, $matches)) {
        $channel = base64_decode($matches[1]);
        $config = loadConfig();
        
        if (($key = array_search($channel, $config['channels'])) !== false) {
            unset($config['channels'][$key]);
            $config['channels'] = array_values($config['channels']);
            saveConfig($config);
            
            answerCallback($callback_id, "✅ Kanal o'chirildi!");
            
            // Refresh list
            $text = "📢 <b>Majburiy Kanallar</b>\n\n";
            
            if (empty($config['channels'])) {
                $text .= "Majburiy kanallar yo'q.\n\n";
            } else {
                foreach ($config['channels'] as $index => $ch) {
                    $text .= ($index + 1) . ". {$ch}\n";
                }
                $text .= "\n";
            }
            
            $buttons = [];
            
            foreach ($config['channels'] as $ch) {
                $buttons[] = [['text' => "❌ {$ch}", 'callback_data' => "remove_channel_" . base64_encode($ch)]];
            }
            
            $buttons[] = [['text' => '➕ Kanal qo\'shish', 'callback_data' => 'add_channel']];
            $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'admin_menu']];
            
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        } else {
            answerCallback($callback_id, "❌ Kanal topilmadi!", true);
        }
        exit;
    }
    
    // Admin Post
    if ($data == 'admin_post') {
        answerCallback($callback_id);
        sendMessage($chat_id, "📮 <b>Reklama/Post</b>\n\nBarcha foydalanuvchilarga yuboriladigan xabarni yozing:");
        exit;
    }
    
    // Admin Maintenance
    if ($data == 'admin_maintenance') {
        $config = loadConfig();
        
        $text = "🔧 <b>Maintenance Rejimi</b>\n\n";
        $text .= "Holat: " . ($config['maintenance'] ? "✅ Yoqilgan" : "❌ O'chirilgan") . "\n";
        
        if ($config['maintenance']) {
            $until = date('Y-m-d H:i:s', $config['maintenance_until']);
            $text .= "Tugash vaqti: {$until}\n";
        }
        
        $buttons = [];
        
        if ($config['maintenance']) {
            $buttons[] = [['text' => '❌ O\'chirish', 'callback_data' => 'maintenance_off']];
        } else {
            $buttons[] = [['text' => '✅ Yoqish', 'callback_data' => 'maintenance_on']];
        }
        
        $buttons[] = [['text' => '◀️ Orqaga', 'callback_data' => 'admin_menu']];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        answerCallback($callback_id);
        exit;
    }
    
    // Maintenance On
    if ($data == 'maintenance_on') {
        answerCallback($callback_id);
        sendMessage($chat_id, "🔧 Maintenance muddatini kiriting (masalan: 2h - 2 soat, 30m - 30 daqiqa, 1d - 1 kun):");
        exit;
    }
    
    // Maintenance Off
    if ($data == 'maintenance_off') {
        $config = loadConfig();
        $config['maintenance'] = false;
        $config['maintenance_until'] = 0;
        saveConfig($config);
        
        answerCallback($callback_id, "✅ Maintenance o'chirildi!");
        
        $text = "🔧 <b>Maintenance Rejimi</b>\n\n";
        $text .= "Holat: ❌ O'chirilgan\n";
        
        $buttons = [
            [['text' => '✅ Yoqish', 'callback_data' => 'maintenance_on']],
            [['text' => '◀️ Orqaga', 'callback_data' => 'admin_menu']]
        ];
        
        editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $buttons]);
        exit;
    }
    
    // Admin Ban
    if ($data == 'admin_ban') {
        $text = "🚫 <b>Ban/Unban</b>\n\n";
        $text .= "Nima qilmoqchisiz?";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🚫 Ban qilish', 'callback_data' => 'ban_user']],
                [['text' => '✅ Bandan chiqarish', 'callback_data' => 'unban_user']],
                [['text' => '◀️ Orqaga', 'callback_data' => 'admin_menu']]
            ]
        ];
        
        editMessage($chat_id, $message_id, $text, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    // Ban User
    if ($data == 'ban_user') {
        answerCallback($callback_id);
        sendMessage($chat_id, "🚫 Ban qilinadigan foydalanuvchi ID ni yuboring:");
        exit;
    }
    
    // Unban User
    if ($data == 'unban_user') {
        answerCallback($callback_id);
        sendMessage($chat_id, "✅ Bandan chiqariladigan foydalanuvchi ID ni yuboring:");
        exit;
    }
    
    // Close Admin
    if ($data == 'close_admin') {
        apiRequest('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
        answerCallback($callback_id, "✅ Yopildi");
        exit;
    }
}

// Set webhook
$webhook_url = WEBHOOK_URL . 'index.php';
setWebhook(BOT_TOKEN, $webhook_url);

?>