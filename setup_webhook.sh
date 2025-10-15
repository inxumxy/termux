#!/bin/bash

# Telegram Bot Webhook Setup Script
# Bu script bot uchun webhook o'rnatadi

BOT_TOKEN="8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E"

echo "========================================"
echo "Telegram Bot Webhook O'rnatish"
echo "========================================"
echo ""

# Domenni so'rash
read -p "Serveringiz domenini kiriting (misol: example.com): " DOMAIN

# Webhook URL yaratish
WEBHOOK_URL="https://${DOMAIN}/index.php"

echo ""
echo "Webhook o'rnatilmoqda..."
echo "URL: $WEBHOOK_URL"
echo ""

# Webhook o'rnatish
RESPONSE=$(curl -s "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook?url=${WEBHOOK_URL}")

echo "Javob: $RESPONSE"
echo ""

# Webhook holatini tekshirish
echo "Webhook holati tekshirilmoqda..."
INFO=$(curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo")

echo "$INFO" | python3 -m json.tool 2>/dev/null || echo "$INFO"

echo ""
echo "========================================"
echo "Webhook o'rnatish tugadi!"
echo "========================================"
echo ""
echo "Eslatma: Botni ishlatishdan oldin quyidagilarni bajaring:"
echo "1. index.php faylida domeningizni o'zgartiring"
echo "2. Kerakli papkalarga yozish huquqini bering (chmod 755)"
echo "3. PHP va cURL o'rnatilganligini tekshiring"
echo ""
