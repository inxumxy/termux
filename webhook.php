<?php
// Webhook handler for user bots
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get user_id and bot_number from URL parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$bot_number = isset($_GET['bot_number']) ? (int)$_GET['bot_number'] : 0;

if (!$user_id || !$bot_number) {
    http_response_code(400);
    die('Invalid parameters');
}

// Check if bot file exists
$bot_file = __DIR__ . "/users/$user_id/$bot_number/index.php";

if (!file_exists($bot_file)) {
    http_response_code(404);
    die('Bot not found');
}

// Change directory to bot folder
chdir(dirname($bot_file));

// Include and execute bot code
include $bot_file;
?>
