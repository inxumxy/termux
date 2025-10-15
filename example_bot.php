<?php
/**
 * Example Telegram Bot Code
 * This is a simple echo bot that users can upload to the platform
 * 
 * IMPORTANT: Replace YOUR_BOT_TOKEN with actual token from BotFather
 */

// Bot configuration
define('BOT_TOKEN', 'YOUR_BOT_TOKEN');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Get webhook update
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Helper function to send message
function sendMessage($chat_id, $text) {
    $url = API_URL . 'sendMessage';
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

// Process message
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $first_name = $message['from']['first_name'] ?? 'User';
    
    // Handle /start command
    if ($text == '/start') {
        sendMessage($chat_id, "Salom, $first_name! Men oddiy echo bot.\n\nMenga xabar yuboring, men uni qaytaraman.");
    }
    // Echo other messages
    else {
        sendMessage($chat_id, "Siz yozdingiz: $text");
    }
}

// Process callback queries
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_id = $callback_query['id'];
    $chat_id = $callback_query['message']['chat']['id'];
    $data = $callback_query['data'];
    
    // Answer callback
    $url = API_URL . 'answerCallbackQuery';
    $params = [
        'callback_query_id' => $callback_id,
        'text' => 'Tugma bosildi!'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_exec($ch);
    curl_close($ch);
}
?>
