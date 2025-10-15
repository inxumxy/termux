<?php
// Error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_error.log');
error_reporting(E_ALL);

// Bot Configuration
define('BOT_TOKEN', '8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E');
define('ADMIN_ID', 7019306015);
define('GEMINI_API_KEY', 'AIzaSyCxAPfTD0dp4PP0S4XR3wtpzlzszeBr3hw');
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DATA_DIR', __DIR__ . '/data/');
define('BOTS_DIR', __DIR__ . '/bots/');

// Initialize directories
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!file_exists(BOTS_DIR)) mkdir(BOTS_DIR, 0755, true);

// Get incoming update
$content = file_get_contents('php://input');
$update = json_decode($content, true);

// Log incoming updates
file_put_contents(__DIR__ . '/webhook.log', date('Y-m-d H:i:s') . ' - ' . $content . "\n", FILE_APPEND);

// ============================================
// DATABASE FUNCTIONS (JSON-based)
// ============================================

function getUsers() {
    $file = DATA_DIR . 'users.json';
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveUsers($users) {
    file_put_contents(DATA_DIR . 'users.json', json_encode($users, JSON_PRETTY_PRINT));
}

function getUser($user_id) {
    $users = getUsers();
    return isset($users[$user_id]) ? $users[$user_id] : null;
}

function saveUser($user_id, $data) {
    $users = getUsers();
    $users[$user_id] = $data;
    saveUsers($users);
}

function initUser($user_id, $username = '', $first_name = '') {
    $user = getUser($user_id);
    if (!$user) {
        $user = [
            'user_id' => $user_id,
            'username' => $username,
            'first_name' => $first_name,
            'balance' => 0,
            'tariff' => 'free',
            'bots' => [],
            'state' => '',
            'temp_data' => [],
            'joined_date' => time(),
            'banned' => false,
            'ai_requests_today' => 0,
            'last_ai_date' => date('Y-m-d')
        ];
        saveUser($user_id, $user);
        
        // Create user directory
        $user_dir = BOTS_DIR . $user_id;
        if (!file_exists($user_dir)) mkdir($user_dir, 0755, true);
    }
    return $user;
}

function getSettings() {
    $file = DATA_DIR . 'settings.json';
    if (!file_exists($file)) {
        $default = [
            'maintenance' => false,
            'maintenance_until' => 0,
            'mandatory_channels' => [],
            'tariffs' => [
                'free' => ['bots' => 1, 'storage' => 1, 'price' => 0, 'name' => 'Free'],
                'pro' => ['bots' => 4, 'storage' => 4.5, 'price' => 99, 'name' => 'Pro'],
                'vip' => ['bots' => 7, 'storage' => 15, 'price' => 299, 'name' => 'VIP']
            ]
        ];
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    return json_decode(file_get_contents($file), true);
}

function saveSettings($settings) {
    file_put_contents(DATA_DIR . 'settings.json', json_encode($settings, JSON_PRETTY_PRINT));
}

function getBannedUsers() {
    $file = DATA_DIR . 'banned.json';
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveBannedUsers($banned) {
    file_put_contents(DATA_DIR . 'banned.json', json_encode($banned, JSON_PRETTY_PRINT));
}

function getAIHistory($user_id, $bot_num) {
    $file = DATA_DIR . "ai_history_{$user_id}_{$bot_num}.json";
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true) ?: [];
    
    // Clear history if it's a new day
    if (isset($data['date']) && $data['date'] != date('Y-m-d')) {
        $data = ['date' => date('Y-m-d'), 'history' => []];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    return $data['history'] ?? [];
}

function saveAIHistory($user_id, $bot_num, $history) {
    $file = DATA_DIR . "ai_history_{$user_id}_{$bot_num}.json";
    $data = ['date' => date('Y-m-d'), 'history' => $history];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function getStatistics() {
    $users = getUsers();
    $total_users = count($users);
    $total_bots = 0;
    $total_usage = 0;
    $banned = count(getBannedUsers());
    
    foreach ($users as $user) {
        $total_bots += count($user['bots']);
        foreach ($user['bots'] as $bot) {
            if (isset($bot['storage_used'])) {
                $total_usage += $bot['storage_used'];
            }
        }
    }
    
    return [
        'total_users' => $total_users,
        'total_bots' => $total_bots,
        'total_usage_mb' => round($total_usage, 2),
        'banned_users' => $banned
    ];
}

// ============================================
// TELEGRAM API FUNCTIONS
// ============================================

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

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
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

function answerCallback($callback_id, $text = '', $alert = false) {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callback_id,
        'text' => $text,
        'show_alert' => $alert
    ]);
}

function checkSubscription($user_id, $channel_id) {
    $result = apiRequest('getChatMember', [
        'chat_id' => $channel_id,
        'user_id' => $user_id
    ]);
    
    if (isset($result['ok']) && $result['ok']) {
        $status = $result['result']['status'];
        return in_array($status, ['member', 'administrator', 'creator']);
    }
    return false;
}

function getBotInfo($token) {
    $url = "https://api.telegram.org/bot{$token}/getMe";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    
    if (isset($data['ok']) && $data['ok']) {
        return $data['result'];
    }
    return false;
}

function setWebhook($token, $url) {
    $webhook_url = "https://api.telegram.org/bot{$token}/setWebhook";
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['url' => $url]));
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// ============================================
// MENU FUNCTIONS
// ============================================

function getMainMenu() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '🤖 AI Agent bot yaratish', 'callback_data' => 'ai_create'],
                ['text' => '💻 Kod orqali bot', 'callback_data' => 'code_bot']
            ],
            [
                ['text' => '📁 File Meneger', 'callback_data' => 'file_manager'],
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

function getAdminMenu() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '⚙️ Ta\'rif belgilash', 'callback_data' => 'admin_tariff'],
                ['text' => '💎 Balans qo\'shish', 'callback_data' => 'admin_balance']
            ],
            [
                ['text' => '📢 Majburiy kanal', 'callback_data' => 'admin_channels'],
                ['text' => '📣 Reklama/Post', 'callback_data' => 'admin_broadcast']
            ],
            [
                ['text' => '🔧 Maintainse', 'callback_data' => 'admin_maintenance'],
                ['text' => '📊 Statistika', 'callback_data' => 'admin_stats']
            ],
            [
                ['text' => '🚫 Ban/Unban', 'callback_data' => 'admin_ban']
            ],
            [
                ['text' => '🔙 Yopish', 'callback_data' => 'close']
            ]
        ]
    ];
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

function checkCodeSecurity($code) {
    $dangerous_patterns = [
        '/vendor\/autoload\.php/i',
        '/require.*vendor\/autoload/i',
        '/include.*vendor\/autoload/i',
        '/exec\s*\(/i',
        '/shell_exec\s*\(/i',
        '/system\s*\(/i',
        '/passthru\s*\(/i',
        '/`[^`]+`/',
        '/popen\s*\(/i',
        '/proc_open\s*\(/i',
        '/mysql_connect/i',
        '/mysqli_connect/i',
        '/new\s+PDO/i',
        '/new\s+mysqli/i',
        '/pg_connect/i',
        '/oci_connect/i',
        '/mssql_connect/i',
        '/sqlite_open/i',
        '/unlink\s*\(/i',
        '/rmdir\s*\(/i',
        '/file_put_contents.*\.\.\//i',
        '/<\?python/i',
        '/import\s+os/i',
        '/import\s+sys/i',
        '/subprocess\./i'
    ];
    
    $errors = [];
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $code)) {
            if (strpos($pattern, 'vendor') !== false) {
                $errors[] = "❌ vendor/autoload.php ishlatish taqiqlangan";
            } elseif (strpos($pattern, 'mysql') !== false || strpos($pattern, 'PDO') !== false || strpos($pattern, 'pg_') !== false) {
                $errors[] = "❌ Database ulanishlari taqiqlangan";
            } elseif (strpos($pattern, 'exec') !== false || strpos($pattern, 'shell') !== false || strpos($pattern, 'system') !== false) {
                $errors[] = "❌ Shell buyruqlari taqiqlangan";
            } elseif (strpos($pattern, 'python') !== false || strpos($pattern, 'import') !== false) {
                $errors[] = "❌ Python kodi taqiqlangan, faqat PHP mumkin";
            } elseif (strpos($pattern, 'unlink') !== false || strpos($pattern, 'rmdir') !== false) {
                $errors[] = "❌ Fayl o'chirish funksiyalari taqiqlangan";
            }
        }
    }
    
    return $errors;
}

function calculateDirectorySize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $size += calculateDirectorySize($path);
            } else {
                $size += filesize($path);
            }
        }
    }
    return $size;
}

function getUserStorageUsed($user_id) {
    $user_dir = BOTS_DIR . $user_id;
    if (!is_dir($user_dir)) return 0;
    
    $size_bytes = calculateDirectorySize($user_dir);
    return round($size_bytes / 1024 / 1024, 2); // Convert to MB
}

// ============================================
// AI AGENT FUNCTIONS
// ============================================

function callGeminiAPI($messages, $system_prompt = '') {
    $contents = [];
    
    foreach ($messages as $msg) {
        $contents[] = [
            'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $msg['content']]]
        ];
    }
    
    $request_data = [
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.7,
            'topP' => 0.95,
            'topK' => 40,
            'maxOutputTokens' => 8192
        ],
        'systemInstruction' => [
            'parts' => [['text' => $system_prompt]]
        ]
    ];
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    $response = json_decode($result, true);
    
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return $response['candidates'][0]['content']['parts'][0]['text'];
    }
    
    return "Xatolik yuz berdi. Iltimos qaytadan urinib ko'ring.";
}

function createBotWithAI($user_id, $bot_num, $user_request, $bot_token, $chat_id, $message_id) {
    try {
        // Update progress
        editMessage($chat_id, $message_id, "⏳ <b>Thinking...</b>\n\nSo'rovingiz tahlil qilinyapti...");
        
        $bot_dir = BOTS_DIR . $user_id . '/' . $bot_num;
        if (!file_exists($bot_dir)) mkdir($bot_dir, 0755, true);
        
        $index_file = $bot_dir . '/index.php';
        $log_file = $bot_dir . '/log.txt';
        
        // Get AI history
        $history = getAIHistory($user_id, $bot_num);
        
        // System prompt for NONOCHA BOT
        $system_prompt = "Siz NONOCHA BOT nomli yordamchi AI dasturchi assistentisiz. Sizning vazifangiz foydalanuvchiga Telegram bot yaratishda yordam berish. Siz faqat PHP tilida kod yozasiz (procedural PHP, OOP emas). Sizning yaratadigan botlaringiz quyidagi talablarga javob berishi kerak:

1. Faqat bitta index.php fayliga yozing
2. vendor/autoload.php ishlatmang
3. MySQL yoki boshqa database ishlatmang
4. Shell buyruqlarini ishlatmang
5. Faqat PHP kodi yozing
6. Bot token: {$bot_token}
7. Error logging uchun log.txt faylga yozish qo'shing
8. Webhook bilan ishlash uchun kod yozing

Agar kod xatosi bo'lsa, log.txt ni o'qib xatoni toping va tuzating. Har doim to'liq ishlaydigan kod yozing.";
        
        // Add user request to history
        $history[] = ['role' => 'user', 'content' => $user_request];
        
        editMessage($chat_id, $message_id, "⏳ <b>Kod yozilyapti...</b>\n\nAI agent sizning botingizni yaratyapti...");
        
        // Call Gemini API
        $ai_response = callGeminiAPI($history, $system_prompt);
        
        // Add AI response to history
        $history[] = ['role' => 'assistant', 'content' => $ai_response];
        saveAIHistory($user_id, $bot_num, $history);
        
        editMessage($chat_id, $message_id, "⏳ <b>Fayl yaratilyapti...</b>\n\nKod faylga yozilyapti...");
        
        // Extract PHP code from response
        $code = '';
        if (preg_match('/```php\s*(.*?)\s*```/s', $ai_response, $matches)) {
            $code = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $ai_response, $matches)) {
            $code = $matches[1];
        } else {
            $code = $ai_response;
        }
        
        // Ensure code starts with <?php
        if (strpos(trim($code), '<?php') !== 0) {
            $code = "<?php\n" . $code;
        }
        
        // Write code to file
        file_put_contents($index_file, $code);
        file_put_contents($log_file, "Bot yaratildi: " . date('Y-m-d H:i:s') . "\n");
        
        editMessage($chat_id, $message_id, "⏳ <b>Webhook o'rnatilmoqda...</b>\n\nBotingiz faollashtirilmoqda...");
        
        // Set webhook - AUTO-DETECT DOMAIN
        $current_domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'yourdomain.com';
        $webhook_url = "https://{$current_domain}/bots/{$user_id}/{$bot_num}/index.php";
        $webhook_result = setWebhook($bot_token, $webhook_url);
        
        // Return result
        return [
            'success' => true,
            'bot_num' => $bot_num,
            'webhook_status' => isset($webhook_result['ok']) ? $webhook_result['ok'] : false,
            'ai_response' => $ai_response,
            'webhook_url' => $webhook_url
        ];
        
    } catch (Exception $e) {
        // Log error
        file_put_contents(__DIR__ . '/bot_error.log', date('Y-m-d H:i:s') . ' - AI Error: ' . $e->getMessage() . "\n", FILE_APPEND);
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ============================================
// MAIN HANDLER
// ============================================

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $username = isset($message['from']['username']) ? $message['from']['username'] : '';
    $first_name = isset($message['from']['first_name']) ? $message['from']['first_name'] : '';
    $text = isset($message['text']) ? $message['text'] : '';
    
    // Check if user is banned
    $banned = getBannedUsers();
    if (in_array($user_id, $banned)) {
        sendMessage($chat_id, "❌ Siz bloklangansiz!");
        exit;
    }
    
    // Check maintenance mode
    $settings = getSettings();
    if ($settings['maintenance'] && $user_id != ADMIN_ID) {
        $until = $settings['maintenance_until'] > 0 ? date('Y-m-d H:i', $settings['maintenance_until']) : 'noma\'lum vaqtgacha';
        sendMessage($chat_id, "🔧 Bot ta'mirlash rejimida.\nQayta ishga tushirish: {$until}");
        exit;
    }
    
    // Initialize user
    $user = initUser($user_id, $username, $first_name);
    
    // Handle /start command
    if ($text == '/start') {
        // Check mandatory channels
        if (!empty($settings['mandatory_channels'])) {
            $not_subscribed = [];
            foreach ($settings['mandatory_channels'] as $channel) {
                if (!checkSubscription($user_id, $channel['id'])) {
                    $not_subscribed[] = $channel;
                }
            }
            
            if (!empty($not_subscribed)) {
                $msg = "📢 <b>Botdan foydalanish uchun quyidagi kanallarga a'zo bo'ling:</b>\n\n";
                $keyboard = ['inline_keyboard' => []];
                
                foreach ($not_subscribed as $channel) {
                    $msg .= "➤ {$channel['name']}\n";
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $channel['name'], 'url' => $channel['url']]
                    ];
                }
                
                $keyboard['inline_keyboard'][] = [
                    ['text' => '✅ Tasdiqlash', 'callback_data' => 'check_subscription']
                ];
                
                sendMessage($chat_id, $msg, $keyboard);
                exit;
            }
        }
        
        // Welcome message
        $welcome = "👋 <b>Xush kelibsiz, <a href='tg://user?id={$user_id}'>{$first_name}</a>!</b>\n\n";
        $welcome .= "🤖 Bu bot orqali siz o'z Telegram botlaringizni yaratishingiz va boshqarishingiz mumkin.\n\n";
        $welcome .= "Quyidagi menyudan kerakli bo'limni tanlang:";
        
        sendMessage($chat_id, $welcome, getMainMenu());
        
        // Clear user state
        $user['state'] = '';
        $user['temp_data'] = [];
        saveUser($user_id, $user);
        exit;
    }
    
    // Admin panel access
    if ($text == '/admin' && $user_id == ADMIN_ID) {
        sendMessage($chat_id, "🔐 <b>Admin Panel</b>\n\nQuyidagi funksiyalardan birini tanlang:", getAdminMenu());
        exit;
    }
    
    // Handle user states
    if (!empty($user['state'])) {
        
        // State: Waiting for bot token
        if ($user['state'] == 'waiting_bot_token') {
            $bot_info = getBotInfo($text);
            
            if (!$bot_info) {
                sendMessage($chat_id, "❌ Noto'g'ri bot token! Iltimos to'g'ri token kiriting yoki /start bosing.");
                exit;
            }
            
            $bot_username = $bot_info['username'];
            
            // Check bot limit
            $tariff_limits = $settings['tariffs'][$user['tariff']];
            if (count($user['bots']) >= $tariff_limits['bots']) {
                sendMessage($chat_id, "❌ Botlar limiti tugadi!\n\nSizning tarifingiz: {$user['tariff']}\nLimit: {$tariff_limits['bots']} ta bot\n\nTarifni yangilash uchun /start bosing va Kabinet bo'limiga o'ting.");
                $user['state'] = '';
                saveUser($user_id, $user);
                exit;
            }
            
            // Check if bot already exists
            foreach ($user['bots'] as $bot) {
                if ($bot['token'] == $text) {
                    sendMessage($chat_id, "❌ Bu bot allaqachon qo'shilgan!");
                    $user['state'] = '';
                    saveUser($user_id, $user);
                    exit;
                }
            }
            
            // Save bot token temporarily
            $user['temp_data']['bot_token'] = $text;
            $user['temp_data']['bot_username'] = $bot_username;
            $user['state'] = 'waiting_bot_code';
            saveUser($user_id, $user);
            
            sendMessage($chat_id, "✅ Bot topildi: @{$bot_username}\n\n📤 Endi bot uchun PHP kod faylini yuboring (.php file):");
            exit;
        }
        
        // State: Waiting for AI bot token
        if ($user['state'] == 'waiting_ai_bot_token') {
            $bot_info = getBotInfo($text);
            
            if (!$bot_info) {
                sendMessage($chat_id, "❌ Noto'g'ri bot token! Iltimos to'g'ri token kiriting yoki /start bosing.");
                exit;
            }
            
            $bot_username = $bot_info['username'];
            
            // Check bot limit
            $tariff_limits = $settings['tariffs'][$user['tariff']];
            if (count($user['bots']) >= $tariff_limits['bots']) {
                sendMessage($chat_id, "❌ Botlar limiti tugadi!\n\nSizning tarifingiz: {$user['tariff']}\nLimit: {$tariff_limits['bots']} ta bot");
                $user['state'] = '';
                saveUser($user_id, $user);
                exit;
            }
            
            // Save bot token temporarily
            $user['temp_data']['ai_bot_token'] = $text;
            $user['temp_data']['ai_bot_username'] = $bot_username;
            $user['state'] = 'ai_agent_active';
            saveUser($user_id, $user);
            
            sendMessage($chat_id, "✅ Bot topildi: @{$bot_username}\n\n🤖 <b>AI Agent NONOCHA BOT faol!</b>\n\nQanday bot yaratishni xohlaysiz? Tavsifini yozing:\n\nMisol: 'Oddiy echo bot yarat, foydalanuvchi yozgan xabarni qaytarsin'");
            exit;
        }
        
        // State: AI Agent active
        if ($user['state'] == 'ai_agent_active') {
            // Check AI request limit
            if ($user['last_ai_date'] != date('Y-m-d')) {
                $user['ai_requests_today'] = 0;
                $user['last_ai_date'] = date('Y-m-d');
            }
            
            if ($user['ai_requests_today'] >= 20) {
                sendMessage($chat_id, "❌ Kunlik AI so'rovlar limiti tugadi! (20/20)\n\nErtaga qayta urinib ko'ring.");
                exit;
            }
            
            // Increment AI requests
            $user['ai_requests_today']++;
            saveUser($user_id, $user);
            
            // Determine bot number
            $bot_num = count($user['bots']) + 1;
            
            // Send thinking message
            $thinking_msg = sendMessage($chat_id, "⏳ <b>AI Agent ishga tushmoqda...</b>");
            
            if (!isset($thinking_msg['result']['message_id'])) {
                sendMessage($chat_id, "❌ Xatolik yuz berdi. Iltimos qaytadan urinib ko'ring.");
                exit;
            }
            
            // Create bot with AI
            $result = createBotWithAI($user_id, $bot_num, $text, $user['temp_data']['ai_bot_token'], $chat_id, $thinking_msg['result']['message_id']);
            
            if ($result['success']) {
                // Save bot to user's list
                $user['bots'][] = [
                    'bot_num' => $bot_num,
                    'token' => $user['temp_data']['ai_bot_token'],
                    'username' => $user['temp_data']['ai_bot_username'],
                    'created_at' => time(),
                    'type' => 'ai'
                ];
                
                // Prepare success message
                $success_msg = "✅ <b>Bot muvaffaqiyatli yaratildi!</b>\n\n";
                $success_msg .= "🤖 Bot: @{$user['temp_data']['ai_bot_username']}\n";
                $success_msg .= "📁 Fayl: bots/{$user_id}/{$bot_num}/index.php\n";
                $success_msg .= "🌐 Webhook: " . ($result['webhook_status'] ? '✅ O\'rnatildi' : '❌ Xatolik') . "\n";
                $success_msg .= "🔗 URL: {$result['webhook_url']}\n\n";
                $ai_preview = function_exists('mb_substr') ? mb_substr($result['ai_response'], 0, 350) : substr($result['ai_response'], 0, 350);
                $success_msg .= "💬 <b>AI javobi:</b>\n" . $ai_preview . "...";
                
                editMessage($chat_id, $thinking_msg['result']['message_id'], $success_msg, getMainMenu());
            } else {
                $error_msg = "❌ <b>Bot yaratishda xatolik!</b>\n\n";
                $error_msg .= "Xatolik: " . (isset($result['error']) ? $result['error'] : 'Noma\'lum xatolik');
                
                editMessage($chat_id, $thinking_msg['result']['message_id'], $error_msg, getMainMenu());
            }
            
            $user['state'] = '';
            $user['temp_data'] = [];
            saveUser($user_id, $user);
            exit;
        }
        
        // State: Admin waiting for user ID to add balance
        if ($user['state'] == 'admin_add_balance_id') {
            if (!is_numeric($text)) {
                sendMessage($chat_id, "❌ Foydalanuvchi ID raqam bo'lishi kerak!");
                exit;
            }
            
            $target_user = getUser($text);
            if (!$target_user) {
                sendMessage($chat_id, "❌ Foydalanuvchi topilmadi!");
                exit;
            }
            
            $user['temp_data']['target_user_id'] = $text;
            $user['state'] = 'admin_add_balance_amount';
            saveUser($user_id, $user);
            
            sendMessage($chat_id, "💎 Qancha olmos qo'shmoqchisiz?");
            exit;
        }
        
        // State: Admin waiting for balance amount
        if ($user['state'] == 'admin_add_balance_amount') {
            if (!is_numeric($text)) {
                sendMessage($chat_id, "❌ Miqdor raqam bo'lishi kerak!");
                exit;
            }
            
            $target_user_id = $user['temp_data']['target_user_id'];
            $amount = intval($text);
            
            $target_user = getUser($target_user_id);
            $target_user['balance'] += $amount;
            saveUser($target_user_id, $target_user);
            
            sendMessage($chat_id, "✅ Foydalanuvchi #{$target_user_id} ga {$amount} olmos qo'shildi!");
            sendMessage($target_user_id, "💎 Sizga {$amount} olmos qo'shildi!\n\nJoriy balans: {$target_user['balance']} olmos");
            
            $user['state'] = '';
            $user['temp_data'] = [];
            saveUser($user_id, $user);
            exit;
        }
        
        // State: Admin waiting for channel to add
        if ($user['state'] == 'admin_add_channel') {
            // Expected format: @channel_username|Channel Name|https://t.me/channel
            $parts = explode('|', $text);
            if (count($parts) != 3) {
                sendMessage($chat_id, "❌ Noto'g'ri format! Format: @channel_username|Kanal Nomi|https://t.me/channel");
                exit;
            }
            
            $settings = getSettings();
            $settings['mandatory_channels'][] = [
                'id' => $parts[0],
                'name' => $parts[1],
                'url' => $parts[2]
            ];
            saveSettings($settings);
            
            sendMessage($chat_id, "✅ Kanal qo'shildi!");
            
            $user['state'] = '';
            saveUser($user_id, $user);
            exit;
        }
        
        // State: Admin broadcast message
        if ($user['state'] == 'admin_broadcast') {
            $users = getUsers();
            $sent = 0;
            $failed = 0;
            
            foreach ($users as $uid => $u) {
                $result = sendMessage($uid, $text);
                if ($result['ok']) {
                    $sent++;
                } else {
                    $failed++;
                }
                usleep(50000); // 50ms delay to avoid flood
            }
            
            sendMessage($chat_id, "✅ Xabar yuborildi!\n\nYuborildi: {$sent}\nXato: {$failed}");
            
            $user['state'] = '';
            saveUser($user_id, $user);
            exit;
        }
        
        // State: Admin ban user
        if ($user['state'] == 'admin_ban_user') {
            if (!is_numeric($text)) {
                sendMessage($chat_id, "❌ Foydalanuvchi ID raqam bo'lishi kerak!");
                exit;
            }
            
            $banned = getBannedUsers();
            if (!in_array(intval($text), $banned)) {
                $banned[] = intval($text);
                saveBannedUsers($banned);
                sendMessage($chat_id, "✅ Foydalanuvchi #{$text} bloklandi!");
            } else {
                sendMessage($chat_id, "❌ Foydalanuvchi allaqachon bloklangan!");
            }
            
            $user['state'] = '';
            saveUser($user_id, $user);
            exit;
        }
        
        // State: Admin unban user
        if ($user['state'] == 'admin_unban_user') {
            if (!is_numeric($text)) {
                sendMessage($chat_id, "❌ Foydalanuvchi ID raqam bo'lishi kerak!");
                exit;
            }
            
            $banned = getBannedUsers();
            $key = array_search(intval($text), $banned);
            if ($key !== false) {
                unset($banned[$key]);
                saveBannedUsers(array_values($banned));
                sendMessage($chat_id, "✅ Foydalanuvchi #{$text} blokdan chiqarildi!");
            } else {
                sendMessage($chat_id, "❌ Foydalanuvchi blokda emas!");
            }
            
            $user['state'] = '';
            saveUser($user_id, $user);
            exit;
        }
        
        // State: Contact admin (user sending message to admin)
        if ($user['state'] == 'contact_admin') {
            // Forward message to admin
            $msg = "📨 <b>Yangi xabar foydalanuvchidan:</b>\n\n";
            $msg .= "👤 Foydalanuvchi: <a href='tg://user?id={$user_id}'>{$first_name}</a> (ID: {$user_id})\n";
            $msg .= "📝 Xabar: {$text}";
            
            sendMessage(ADMIN_ID, $msg);
            sendMessage($chat_id, "✅ Xabaringiz adminga yuborildi!");
            
            $user['state'] = '';
            saveUser($user_id, $user);
            exit;
        }
    }
    
    // Handle document (file upload)
    if (isset($message['document'])) {
        if ($user['state'] == 'waiting_bot_code') {
            $file_id = $message['document']['file_id'];
            $file_name = $message['document']['file_name'];
            
            // Check file extension
            if (pathinfo($file_name, PATHINFO_EXTENSION) != 'php') {
                sendMessage($chat_id, "❌ Faqat .php fayl qabul qilinadi!");
                exit;
            }
            
            // Get file
            $file_info = apiRequest('getFile', ['file_id' => $file_id]);
            if (!isset($file_info['result']['file_path'])) {
                sendMessage($chat_id, "❌ Faylni yuklashda xatolik!");
                exit;
            }
            
            $file_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $file_info['result']['file_path'];
            $file_content = file_get_contents($file_url);
            
            // Security check
            $security_errors = checkCodeSecurity($file_content);
            if (!empty($security_errors)) {
                $error_msg = "🚫 <b>Xavfsizlik tekshiruvi muvaffaqiyatsiz!</b>\n\n";
                foreach ($security_errors as $error) {
                    $error_msg .= "{$error}\n";
                }
                sendMessage($chat_id, $error_msg);
                
                $user['state'] = '';
                $user['temp_data'] = [];
                saveUser($user_id, $user);
                exit;
            }
            
            // Check if token exists in code
            $bot_token = $user['temp_data']['bot_token'];
            if (strpos($file_content, $bot_token) === false) {
                sendMessage($chat_id, "⚠️ Ogohantirish: Kodda bot token topilmadi!\n\nDavom ettirishni xohlaysizmi?", [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Ha, davom ettirish', 'callback_data' => 'confirm_upload'],
                            ['text' => '❌ Yo\'q, bekor qilish', 'callback_data' => 'cancel_upload']
                        ]
                    ]
                ]);
                
                $user['temp_data']['file_content'] = $file_content;
                saveUser($user_id, $user);
                exit;
            }
            
            // Check storage limit
            $file_size = strlen($file_content) / 1024 / 1024; // Size in MB
            $current_usage = getUserStorageUsed($user_id);
            $tariff_limits = $settings['tariffs'][$user['tariff']];
            
            if (($current_usage + $file_size) > $tariff_limits['storage']) {
                sendMessage($chat_id, "❌ Storage limiti yetarli emas!\n\nJoriy: {$current_usage} MB\nFayl: " . round($file_size, 2) . " MB\nLimit: {$tariff_limits['storage']} MB");
                $user['state'] = '';
                $user['temp_data'] = [];
                saveUser($user_id, $user);
                exit;
            }
            
            // Determine bot number
            $bot_num = count($user['bots']) + 1;
            
            // Create bot directory
            $bot_dir = BOTS_DIR . $user_id . '/' . $bot_num;
            if (!file_exists($bot_dir)) mkdir($bot_dir, 0755, true);
            
            // Save file
            file_put_contents($bot_dir . '/index.php', $file_content);
            
            // Set webhook - AUTO-DETECT DOMAIN
            $current_domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $webhook_url = "https://{$current_domain}/bots/{$user_id}/{$bot_num}/index.php";
            setWebhook($bot_token, $webhook_url);
            
            // Save bot
            $user['bots'][] = [
                'bot_num' => $bot_num,
                'token' => $bot_token,
                'username' => $user['temp_data']['bot_username'],
                'created_at' => time(),
                'type' => 'code',
                'storage_used' => $file_size
            ];
            
            $user['state'] = '';
            $user['temp_data'] = [];
            saveUser($user_id, $user);
            
            sendMessage($chat_id, "✅ <b>Bot muvaffaqiyatli yaratildi!</b>\n\n🤖 Bot: @{$user['temp_data']['bot_username']}\n📁 Fayl: user_{$user_id}/bot_{$bot_num}/index.php\n💾 Hajm: " . round($file_size, 2) . " MB\n\n🌐 Webhook o'rnatildi!", getMainMenu());
            exit;
        }
    }
}

// Handle callback queries
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $callback_id = $callback['id'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $user_id = $callback['from']['id'];
    $data = $callback['data'];
    
    $user = getUser($user_id);
    if (!$user) {
        answerCallback($callback_id, "❌ Iltimos /start bosing");
        exit;
    }
    
    $settings = getSettings();
    
    // Check subscription
    if ($data == 'check_subscription') {
        if (!empty($settings['mandatory_channels'])) {
            $not_subscribed = [];
            foreach ($settings['mandatory_channels'] as $channel) {
                if (!checkSubscription($user_id, $channel['id'])) {
                    $not_subscribed[] = $channel;
                }
            }
            
            if (!empty($not_subscribed)) {
                answerCallback($callback_id, "❌ Siz hali barcha kanallarga a'zo bo'lmadingiz!", true);
                exit;
            }
        }
        
        // Subscription confirmed
        $welcome = "👋 <b>Xush kelibsiz, <a href='tg://user?id={$user_id}'>{$callback['from']['first_name']}</a>!</b>\n\n";
        $welcome .= "🤖 Bu bot orqali siz o'z Telegram botlaringizni yaratishingiz va boshqarishingiz mumkin.\n\n";
        $welcome .= "Quyidagi menyudan kerakli bo'limni tanlang:";
        
        editMessage($chat_id, $message_id, $welcome, getMainMenu());
        answerCallback($callback_id, "✅ Tasdiqlandi!");
        exit;
    }
    
    // Main menu callbacks
    if ($data == 'ai_create') {
        // Check bot limit
        $tariff_limits = $settings['tariffs'][$user['tariff']];
        if (count($user['bots']) >= $tariff_limits['bots']) {
            answerCallback($callback_id, "❌ Botlar limiti tugadi! Ta'rifni yangilang.", true);
            exit;
        }
        
        $user['state'] = 'waiting_ai_bot_token';
        saveUser($user_id, $user);
        
        $msg = "🤖 <b>AI Agent bot yaratish</b>\n\n";
        $msg .= "AI Agent NONOCHA BOT sizga bot yaratishda yordam beradi.\n\n";
        $msg .= "📝 Iltimos bot tokenini kiriting:\n\n";
        $msg .= "Token olish: @BotFather → /newbot";
        
        editMessage($chat_id, $message_id, $msg);
        answerCallback($callback_id);
        exit;
    }
    
    if ($data == 'code_bot') {
        // Check bot limit
        $tariff_limits = $settings['tariffs'][$user['tariff']];
        if (count($user['bots']) >= $tariff_limits['bots']) {
            answerCallback($callback_id, "❌ Botlar limiti tugadi! Ta'rifni yangilang.", true);
            exit;
        }
        
        $user['state'] = 'waiting_bot_token';
        saveUser($user_id, $user);
        
        $msg = "💻 <b>Kod orqali bot yaratish</b>\n\n";
        $msg .= "📝 Iltimos bot tokenini kiriting:\n\n";
        $msg .= "Token olish: @BotFather → /newbot";
        
        editMessage($chat_id, $message_id, $msg);
        answerCallback($callback_id);
        exit;
    }
    
    if ($data == 'file_manager') {
        $user_dir = BOTS_DIR . $user_id;
        
        if (!is_dir($user_dir) || count(scandir($user_dir)) <= 2) {
            answerCallback($callback_id, "📁 Hozircha fayllar yo'q", true);
            exit;
        }
        
        $msg = "📁 <b>File Meneger</b>\n\n";
        $msg .= "Sizning fayllaringiz:\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($user['bots'] as $bot) {
            $bot_dir = $user_dir . '/' . $bot['bot_num'];
            if (is_dir($bot_dir)) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => "📂 Bot #{$bot['bot_num']} - @{$bot['username']}", 'callback_data' => "fm_bot_{$bot['bot_num']}"]
                ];
            }
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']
        ];
        
        editMessage($chat_id, $message_id, $msg, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    if (strpos($data, 'fm_bot_') === 0) {
        $bot_num = str_replace('fm_bot_', '', $data);
        $bot_dir = BOTS_DIR . $user_id . '/' . $bot_num;
        
        if (!is_dir($bot_dir)) {
            answerCallback($callback_id, "❌ Papka topilmadi", true);
            exit;
        }
        
        $files = array_diff(scandir($bot_dir), ['.', '..']);
        
        $msg = "📂 <b>Bot #{$bot_num} fayllari</b>\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($files as $file) {
            $file_path = $bot_dir . '/' . $file;
            $size = filesize($file_path);
            $size_kb = round($size / 1024, 2);
            
            $msg .= "📄 {$file} ({$size_kb} KB)\n";
            
            $keyboard['inline_keyboard'][] = [
                ['text' => "📄 {$file}", 'callback_data' => "file_info_{$bot_num}_{$file}"],
                ['text' => '🗑️ O\'chirish', 'callback_data' => "file_del_{$bot_num}_{$file}"]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 Orqaga', 'callback_data' => 'file_manager']
        ];
        
        editMessage($chat_id, $message_id, $msg, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    if (strpos($data, 'file_info_') === 0) {
        $parts = explode('_', $data, 4);
        $bot_num = $parts[2];
        $filename = $parts[3];
        
        $file_path = BOTS_DIR . $user_id . '/' . $bot_num . '/' . $filename;
        
        if (!file_exists($file_path)) {
            answerCallback($callback_id, "❌ Fayl topilmadi", true);
            exit;
        }
        
        // Send file to user
        apiRequest('sendDocument', [
            'chat_id' => $chat_id,
            'document' => new CURLFile($file_path),
            'caption' => "📄 Fayl: {$filename}\n📂 Bot #{$bot_num}"
        ]);
        
        answerCallback($callback_id, "✅ Fayl yuborildi");
        exit;
    }
    
    if (strpos($data, 'file_del_') === 0) {
        $parts = explode('_', $data, 4);
        $bot_num = $parts[2];
        $filename = $parts[3];
        
        $file_path = BOTS_DIR . $user_id . '/' . $bot_num . '/' . $filename;
        
        if (file_exists($file_path)) {
            unlink($file_path);
            answerCallback($callback_id, "✅ Fayl o'chirildi");
            
            // Refresh file list
            $callback['data'] = "fm_bot_{$bot_num}";
            // Simulate callback handling
        } else {
            answerCallback($callback_id, "❌ Fayl topilmadi", true);
        }
        exit;
    }
    
    if ($data == 'my_bots') {
        if (empty($user['bots'])) {
            answerCallback($callback_id, "🤖 Hozircha botlar yo'q", true);
            exit;
        }
        
        $msg = "🤖 <b>Mening Botlarim</b>\n\n";
        $msg .= "Sizning botlaringiz ro'yxati:\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($user['bots'] as $idx => $bot) {
            $msg .= "#{$bot['bot_num']}. @{$bot['username']}\n";
            $msg .= "   📅 Yaratildi: " . date('Y-m-d H:i', $bot['created_at']) . "\n";
            $msg .= "   🔧 Turi: " . ($bot['type'] == 'ai' ? 'AI Agent' : 'Kod') . "\n\n";
            
            $keyboard['inline_keyboard'][] = [
                ['text' => "#{$bot['bot_num']} @{$bot['username']}", 'callback_data' => "bot_info_{$idx}"],
                ['text' => '🗑️', 'callback_data' => "bot_del_{$idx}"]
            ];
        }
        
        $tariff_limits = $settings['tariffs'][$user['tariff']];
        $msg .= "\n📊 " . count($user['bots']) . "/{$tariff_limits['bots']} botlar";
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']
        ];
        
        editMessage($chat_id, $message_id, $msg, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    if (strpos($data, 'bot_del_') === 0) {
        $idx = intval(str_replace('bot_del_', '', $data));
        
        if (!isset($user['bots'][$idx])) {
            answerCallback($callback_id, "❌ Bot topilmadi", true);
            exit;
        }
        
        $bot = $user['bots'][$idx];
        $bot_dir = BOTS_DIR . $user_id . '/' . $bot['bot_num'];
        
        // Delete bot directory
        if (is_dir($bot_dir)) {
            $files = array_diff(scandir($bot_dir), ['.', '..']);
            foreach ($files as $file) {
                unlink($bot_dir . '/' . $file);
            }
            rmdir($bot_dir);
        }
        
        // Remove from user's bots
        array_splice($user['bots'], $idx, 1);
        saveUser($user_id, $user);
        
        answerCallback($callback_id, "✅ Bot o'chirildi");
        
        // Refresh bots list
        $data = 'my_bots';
        // Simulate callback
        exit;
    }
    
    if ($data == 'cabinet') {
        $tariff = $user['tariff'];
        $tariff_info = $settings['tariffs'][$tariff];
        $storage_used = getUserStorageUsed($user_id);
        
        $msg = "👤 <b>Kabinet</b>\n\n";
        $msg .= "💎 Balans: {$user['balance']} olmos\n";
        $msg .= "📊 Joriy tarif: {$tariff_info['name']}\n";
        $msg .= "🤖 Botlar: " . count($user['bots']) . "/{$tariff_info['bots']}\n";
        $msg .= "💾 Storage: {$storage_used}/{$tariff_info['storage']} MB\n";
        $msg .= "🤖 AI so'rovlar: {$user['ai_requests_today']}/20 (bugun)\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💎 Balans', 'callback_data' => 'cabinet_balance'],
                    ['text' => '📊 Ta\'rif', 'callback_data' => 'cabinet_tariff']
                ],
                [
                    ['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        editMessage($chat_id, $message_id, $msg, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    if ($data == 'cabinet_balance') {
        $msg = "💎 <b>Balans</b>\n\n";
        $msg .= "Joriy balans: {$user['balance']} olmos\n\n";
        $msg .= "💰 1 olmos = 120 UZS\n\n";
        $msg .= "Olmos sotib olish uchun admin bilan bog'laning:\n";
        $msg .= "👤 Admin: @WINAIKO";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👤 Admin', 'url' => 'https://t.me/WINAIKO']
                ],
                [
                    ['text' => '🔙 Orqaga', 'callback_data' => 'cabinet']
                ]
            ]
        ];
        
        editMessage($chat_id, $message_id, $msg, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    if ($data == 'cabinet_tariff') {
        $current_tariff = $user['tariff'];
        
        $msg = "📊 <b>Ta'riflar</b>\n\n";
        
        foreach ($settings['tariffs'] as $key => $tariff) {
            $current = ($key == $current_tariff) ? '✅ ' : '';
            $msg .= "{$current}<b>{$tariff['name']}</b>\n";
            $msg .= "🤖 Botlar: {$tariff['bots']} ta\n";
            $msg .= "💾 Storage: {$tariff['storage']} MB\n";
            $msg .= "💎 Narxi: {$tariff['price']} olmos\n\n";
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⬆️ Pro ga o\'tish', 'callback_data' => 'upgrade_pro'],
                    ['text' => '⬆️ VIP ga o\'tish', 'callback_data' => 'upgrade_vip']
                ],
                [
                    ['text' => '🔙 Orqaga', 'callback_data' => 'cabinet']
                ]
            ]
        ];
        
        editMessage($chat_id, $message_id, $msg, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    if ($data == 'upgrade_pro' || $data == 'upgrade_vip') {
        $target_tariff = ($data == 'upgrade_pro') ? 'pro' : 'vip';
        $tariff_info = $settings['tariffs'][$target_tariff];
        
        if ($user['balance'] < $tariff_info['price']) {
            answerCallback($callback_id, "❌ Balans yetarli emas! Kerak: {$tariff_info['price']} olmos", true);
            exit;
        }
        
        $user['balance'] -= $tariff_info['price'];
        $user['tariff'] = $target_tariff;
        saveUser($user_id, $user);
        
        answerCallback($callback_id, "✅ Ta'rif yangilandi!");
        
        $msg = "✅ <b>Ta'rif muvaffaqiyatli yangilandi!</b>\n\n";
        $msg .= "📊 Yangi tarif: {$tariff_info['name']}\n";
        $msg .= "🤖 Botlar limiti: {$tariff_info['bots']} ta\n";
        $msg .= "💾 Storage limiti: {$tariff_info['storage']} MB\n";
        $msg .= "💎 Qolgan balans: {$user['balance']} olmos";
        
        editMessage($chat_id, $message_id, $msg, getMainMenu());
        exit;
    }
    
    if ($data == 'help') {
        $msg = "❓ <b>Yordam</b>\n\n";
        $msg .= "<b>Bot yaratish:</b>\n";
        $msg .= "1. @BotFather ga o'ting\n";
        $msg .= "2. /newbot buyrug'ini yuboring\n";
        $msg .= "3. Bot nomi va username kiriting\n";
        $msg .= "4. Token oling va bu botga yuboring\n\n";
        $msg .= "<b>Kod tahrirlash:</b>\n";
        $msg .= "📱 Android: QuickEdit, Acode\n";
        $msg .= "🍎 iOS: Textastic, Buffer Editor\n\n";
        $msg .= "<b>Ta'riflar:</b>\n";
        $msg .= "• Free: 1 bot, 1 MB\n";
        $msg .= "• Pro: 4 bot, 4.5 MB\n";
        $msg .= "• VIP: 7 bot, 15 MB\n\n";
        $msg .= "Qo'shimcha savol: @WINAIKO";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '@BotFather', 'url' => 'https://t.me/BotFather']
                ],
                [
                    ['text' => '🔙 Orqaga', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        editMessage($chat_id, $message_id, $msg, $keyboard);
        answerCallback($callback_id);
        exit;
    }
    
    if ($data == 'contact_admin') {
        $user['state'] = 'contact_admin';
        saveUser($user_id, $user);
        
        $msg = "📞 <b>Admin bilan bog'lanish</b>\n\n";
        $msg .= "Xabaringizni yozing, admin tez orada javob beradi:";
        
        editMessage($chat_id, $message_id, $msg);
        answerCallback($callback_id);
        exit;
    }
    
    if ($data == 'main_menu') {
        $welcome = "👋 <b>Asosiy menyu</b>\n\n";
        $welcome .= "Quyidagi bo'limlardan birini tanlang:";
        
        editMessage($chat_id, $message_id, $welcome, getMainMenu());
        answerCallback($callback_id);
        exit;
    }
    
    // Admin callbacks
    if ($user_id == ADMIN_ID) {
        if ($data == 'admin_tariff') {
            $msg = "⚙️ <b>Ta'riflarni belgilash</b>\n\n";
            $msg .= "Joriy ta'riflar:\n\n";
            
            foreach ($settings['tariffs'] as $key => $tariff) {
                $msg .= "<b>{$tariff['name']}</b>\n";
                $msg .= "🤖 Botlar: {$tariff['bots']}\n";
                $msg .= "💾 Storage: {$tariff['storage']} MB\n";
                $msg .= "💎 Narxi: {$tariff['price']} olmos\n\n";
            }
            
            $msg .= "Ta'riflarni o'zgartirish uchun data/settings.json faylini tahrirlang.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']]
                ]
            ];
            
            editMessage($chat_id, $message_id, $msg, $keyboard);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'admin_balance') {
            $user['state'] = 'admin_add_balance_id';
            saveUser($user_id, $user);
            
            $msg = "💎 <b>Balans qo'shish</b>\n\n";
            $msg .= "Foydalanuvchi ID raqamini kiriting:";
            
            editMessage($chat_id, $message_id, $msg);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'admin_channels') {
            $msg = "📢 <b>Majburiy kanallar</b>\n\n";
            
            if (empty($settings['mandatory_channels'])) {
                $msg .= "Hozircha majburiy kanallar yo'q.\n\n";
            } else {
                foreach ($settings['mandatory_channels'] as $idx => $channel) {
                    $msg .= "#{$idx}. {$channel['name']}\n";
                    $msg .= "   ID: {$channel['id']}\n";
                    $msg .= "   Link: {$channel['url']}\n\n";
                }
            }
            
            $msg .= "Kanal qo'shish: ID|Nomi|Link formatida yuboring";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '➕ Kanal qo\'shish', 'callback_data' => 'admin_add_channel']
                    ],
                    [
                        ['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']
                    ]
                ]
            ];
            
            editMessage($chat_id, $message_id, $msg, $keyboard);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'admin_add_channel') {
            $user['state'] = 'admin_add_channel';
            saveUser($user_id, $user);
            
            $msg = "📢 <b>Kanal qo'shish</b>\n\n";
            $msg .= "Kanal ma'lumotlarini quyidagi formatda yuboring:\n\n";
            $msg .= "<code>@channel_username|Kanal Nomi|https://t.me/channel</code>\n\n";
            $msg .= "Misol:\n";
            $msg .= "<code>@example|Misol Kanal|https://t.me/example</code>";
            
            editMessage($chat_id, $message_id, $msg);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'admin_broadcast') {
            $user['state'] = 'admin_broadcast';
            saveUser($user_id, $user);
            
            $msg = "📣 <b>Reklama/Post yuborish</b>\n\n";
            $msg .= "Barcha foydalanuvchilarga yuboriladigan xabarni yozing:";
            
            editMessage($chat_id, $message_id, $msg);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'admin_maintenance') {
            $msg = "🔧 <b>Maintainse rejimi</b>\n\n";
            $msg .= "Joriy holat: " . ($settings['maintenance'] ? '✅ Faol' : '❌ Faol emas') . "\n\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Yoqish', 'callback_data' => 'maint_on'],
                        ['text' => '❌ O\'chirish', 'callback_data' => 'maint_off']
                    ],
                    [
                        ['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']
                    ]
                ]
            ];
            
            editMessage($chat_id, $message_id, $msg, $keyboard);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'maint_on') {
            $settings['maintenance'] = true;
            $settings['maintenance_until'] = time() + 3600; // 1 hour
            saveSettings($settings);
            
            answerCallback($callback_id, "✅ Maintainse rejimi yoqildi");
            exit;
        }
        
        if ($data == 'maint_off') {
            $settings['maintenance'] = false;
            saveSettings($settings);
            
            answerCallback($callback_id, "✅ Maintainse rejimi o'chirildi");
            exit;
        }
        
        if ($data == 'admin_stats') {
            $stats = getStatistics();
            
            $msg = "📊 <b>Statistika</b>\n\n";
            $msg .= "👥 Jami foydalanuvchilar: {$stats['total_users']}\n";
            $msg .= "🤖 Jami botlar: {$stats['total_bots']}\n";
            $msg .= "💾 Jami storage: {$stats['total_usage_mb']} MB\n";
            $msg .= "🚫 Bloklangan: {$stats['banned_users']}\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']]
                ]
            ];
            
            editMessage($chat_id, $message_id, $msg, $keyboard);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'admin_ban') {
            $msg = "🚫 <b>Ban/Unban</b>\n\n";
            $msg .= "Foydalanuvchi ID raqamini yuboring:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🚫 Ban qilish', 'callback_data' => 'ban_user'],
                        ['text' => '✅ Unban', 'callback_data' => 'unban_user']
                    ],
                    [
                        ['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']
                    ]
                ]
            ];
            
            editMessage($chat_id, $message_id, $msg, $keyboard);
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'ban_user') {
            $user['state'] = 'admin_ban_user';
            saveUser($user_id, $user);
            
            editMessage($chat_id, $message_id, "🚫 Bloklash uchun foydalanuvchi ID kiriting:");
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'unban_user') {
            $user['state'] = 'admin_unban_user';
            saveUser($user_id, $user);
            
            editMessage($chat_id, $message_id, "✅ Blokdan chiqarish uchun foydalanuvchi ID kiriting:");
            answerCallback($callback_id);
            exit;
        }
        
        if ($data == 'admin_back') {
            editMessage($chat_id, $message_id, "🔐 <b>Admin Panel</b>\n\nQuyidagi funksiyalardan birini tanlang:", getAdminMenu());
            answerCallback($callback_id);
            exit;
        }
    }
    
    if ($data == 'close') {
        apiRequest('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
        answerCallback($callback_id);
        exit;
    }
    
    // Confirm upload
    if ($data == 'confirm_upload') {
        $file_content = $user['temp_data']['file_content'];
        $bot_token = $user['temp_data']['bot_token'];
        
        // Check storage limit
        $file_size = strlen($file_content) / 1024 / 1024;
        $current_usage = getUserStorageUsed($user_id);
        $tariff_limits = $settings['tariffs'][$user['tariff']];
        
        if (($current_usage + $file_size) > $tariff_limits['storage']) {
            answerCallback($callback_id, "❌ Storage limiti yetarli emas!", true);
            exit;
        }
        
        // Determine bot number
        $bot_num = count($user['bots']) + 1;
        
        // Create bot directory
        $bot_dir = BOTS_DIR . $user_id . '/' . $bot_num;
        if (!file_exists($bot_dir)) mkdir($bot_dir, 0755, true);
        
        // Save file
        file_put_contents($bot_dir . '/index.php', $file_content);
        
        // Set webhook - AUTO-DETECT DOMAIN
        $current_domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $webhook_url = "https://{$current_domain}/bots/{$user_id}/{$bot_num}/index.php";
        setWebhook($bot_token, $webhook_url);
        
        // Save bot
        $user['bots'][] = [
            'bot_num' => $bot_num,
            'token' => $bot_token,
            'username' => $user['temp_data']['bot_username'],
            'created_at' => time(),
            'type' => 'code',
            'storage_used' => $file_size
        ];
        
        $user['state'] = '';
        $user['temp_data'] = [];
        saveUser($user_id, $user);
        
        editMessage($chat_id, $message_id, "✅ <b>Bot muvaffaqiyatli yaratildi!</b>\n\n🤖 Bot: @{$user['temp_data']['bot_username']}\n📁 Fayl: user_{$user_id}/bot_{$bot_num}/index.php\n💾 Hajm: " . round($file_size, 2) . " MB\n\n🌐 Webhook o'rnatildi!", getMainMenu());
        answerCallback($callback_id, "✅ Bot yaratildi!");
        exit;
    }
    
    if ($data == 'cancel_upload') {
        $user['state'] = '';
        $user['temp_data'] = [];
        saveUser($user_id, $user);
        
        editMessage($chat_id, $message_id, "❌ Bekor qilindi", getMainMenu());
        answerCallback($callback_id);
        exit;
    }
}

// Default response
http_response_code(200);
?>
