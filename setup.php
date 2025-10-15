<?php
// Setup script - Run once to set webhook
define('BOT_TOKEN', '8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E');

// Change this to your domain
$webhook_url = 'https://yourdomain.com/index.php';

echo "Setting webhook to: $webhook_url\n\n";

$url = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'url' => $webhook_url,
    'drop_pending_updates' => true
]));

$result = curl_exec($ch);
curl_close($ch);

$response = json_decode($result, true);

if ($response && $response['ok']) {
    echo "✅ Webhook muvaffaqiyatli o'rnatildi!\n\n";
    echo "Webhook URL: " . $response['result']['url'] . "\n";
    echo "Status: Active\n";
} else {
    echo "❌ Xatolik: " . ($response['description'] ?? 'Unknown error') . "\n";
}

echo "\n\nBot info:\n";
$bot_info = json_decode(file_get_contents("https://api.telegram.org/bot" . BOT_TOKEN . "/getMe"), true);
if ($bot_info && $bot_info['ok']) {
    echo "Bot username: @" . $bot_info['result']['username'] . "\n";
    echo "Bot name: " . $bot_info['result']['first_name'] . "\n";
}

echo "\n\n⚠️ MUHIM:\n";
echo "1. index.php faylidagi 'yourdomain.com' ni o'z domeningizga o'zgartiring\n";
echo "2. setWebhookForUserBot funksiyasida ham domenni o'zgartiring\n";
echo "3. users/ papkasiga write permission bering (chmod 755)\n";
echo "4. Botni Telegram'da ishga tushiring: /start\n";
?>
