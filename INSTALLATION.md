# 📦 O'rnatish bo'yicha batafsil qo'llanma

## 1️⃣ Tayyorgarlik

### Hosting talablari:
- ✅ PHP 7.4 yoki yuqori versiya
- ✅ SQLite3 extension
- ✅ cURL extension
- ✅ Apache/Nginx web server
- ✅ SSL sertifikat (HTTPS)
- ✅ Kamida 100 MB bo'sh joy

### Telegram Bot yaratish:
1. Telegram'da [@BotFather](https://t.me/BotFather) ga boring
2. `/newbot` buyrug'ini yuboring
3. Bot nomini kiriting (masalan: "My Bot Hosting")
4. Bot username kiriting (masalan: "mybothosting_bot")
5. Tokenni saqlang (masalan: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

### Admin ID olish:
1. Telegram'da [@userinfobot](https://t.me/userinfobot) ga boring
2. `/start` bosing
3. ID ni ko'chirib oling (masalan: `123456789`)

### Gemini API Key olish:
1. [Google AI Studio](https://makersuite.google.com/app/apikey) ga kiring
2. "Create API Key" tugmasini bosing
3. API key ni ko'chirib oling

## 2️⃣ Fayllarni yuklash

### Git orqali:
```bash
git clone https://github.com/yourusername/php-telegram-bot-hosting-platform.git
cd php-telegram-bot-hosting-platform
```

### Yoki ZIP fayl:
1. Repository dan "Download ZIP" bosing
2. Fayllarni extract qiling
3. Hosting ga yuklang

## 3️⃣ Konfiguratsiya

### index.php ni tahrirlash:
Faylni ochib, quyidagi qatorlarni toping va o'zgartiring:

```php
// Bot configuration
define('BOT_TOKEN', '8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E'); // ← O'z tokeningizni kiriting
define('ADMIN_ID', 7019306015); // ← O'z ID ingizni kiriting
define('GEMINI_API_KEY', 'AIzaSyCxAPfTD0dp4PP0S4XR3wtpzlzszeBr3hw'); // ← O'z API key ingizni kiriting
```

### Webhook URL ni o'zgartirish:
`index.php` da quyidagi funksiyani toping:

```php
function setWebhookForUserBot($bot_token, $user_id, $bot_number) {
    $webhook_url = "https://yourdomain.com/webhook.php?user_id=$user_id&bot_number=$bot_number";
    // ↑ yourdomain.com ni o'z domeningizga o'zgartiring
```

O'zgartiring:
```php
    $webhook_url = "https://SIZNING-DOMENINGIZ.com/webhook.php?user_id=$user_id&bot_number=$bot_number";
```

## 4️⃣ Permissions sozlash

### Linux/Unix hosting:
```bash
# Fayllar uchun
chmod 644 *.php
chmod 644 .htaccess

# Papkalar uchun
mkdir -p users
chmod 755 users

# Execute permission
chmod 755 setup.php
```

### cPanel orqali:
1. File Manager ga kiring
2. Har bir fayl/papkani tanlang
3. "Permissions" tugmasini bosing
4. Kerakli ruxsatlarni bering:
   - Fayllar: 644
   - Papkalar: 755
   - users/: 777

## 5️⃣ Webhook o'rnatish

### Usul 1 - PHP CLI orqali:
```bash
php setup.php
```

### Usul 2 - Brauzer orqali:
1. Brauzeringizda oching: `https://SIZNING-DOMENINGIZ.com/setup.php`
2. Natijani ko'ring
3. "✅ Webhook muvaffaqiyatli o'rnatildi!" ko'rinishi kerak

### Usul 3 - cURL orqali:
```bash
curl -X POST "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://SIZNING-DOMENINGIZ.com/index.php"
```

## 6️⃣ Test qilish

### 1. Botni test qiling:
1. Telegram'da botingizni qidiring
2. `/start` bosing
3. Xush kelibsiz xabari kelishi kerak

### 2. Tugmalarni test qiling:
- ✅ Har bir inline tugmani bosing
- ✅ Admin panel ishlaganligini tekshiring (admin sifatida)
- ✅ Bot yaratishni sinab ko'ring

### 3. Loglarni tekshiring:
```bash
# Bot loglari
tail -f bot_log.txt

# Xato loglari
tail -f bot_error.log
```

## 7️⃣ Muammolarni hal qilish

### ❌ "Bot javob bermayapti"

**Sabablari:**
1. Webhook noto'g'ri o'rnatilgan
2. SSL sertifikat ishlamayapti
3. PHP xatolar bor

**Yechimlar:**
```bash
# Webhook holatini tekshiring
curl "https://api.telegram.org/bot<YOUR_TOKEN>/getWebhookInfo"

# PHP xatolarni ko'ring
tail -f bot_error.log

# Webhook qayta o'rnating
php setup.php
```

### ❌ "Database xatosi"

**Sabablari:**
1. SQLite3 extension o'rnatilmagan
2. Write permission yo'q

**Yechimlar:**
```bash
# SQLite3 tekshirish
php -m | grep sqlite3

# Permission berish
chmod 666 bot_database.db
chmod 755 .
```

### ❌ "Fayl yuklanmayapti"

**Sabablari:**
1. upload_max_filesize kichik
2. users/ papkasiga write permission yo'q

**Yechimlar:**

**.htaccess** ga qo'shing:
```apache
php_value upload_max_filesize 10M
php_value post_max_size 10M
```

Yoki **php.ini**:
```ini
upload_max_filesize = 10M
post_max_size = 10M
```

### ❌ "AI Agent ishlamayapti"

**Sabablari:**
1. Gemini API key noto'g'ri
2. API limit tugagan
3. Internet yo'q

**Yechimlar:**
1. API key ni tekshiring
2. Boshqa API key oling
3. cURL ishlashini tekshiring

## 8️⃣ Xavfsizlik

### SSL sertifikat:
```bash
# Let's Encrypt bilan (bepul)
certbot --apache -d yourdomain.com
```

### .htaccess himoyasi:
```apache
# Database himoyasi
<Files "bot_database.db">
    Order allow,deny
    Deny from all
</Files>

# Log fayllar
<FilesMatch "\.(log|txt)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### Backup:
```bash
# Database backup
cp bot_database.db bot_database_backup_$(date +%Y%m%d).db

# Fayllar backup
tar -czf backup_$(date +%Y%m%d).tar.gz *.php users/
```

## 9️⃣ Optimization

### Apache mod_rewrite:
```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### PHP optimization:
```ini
# php.ini
memory_limit = 128M
max_execution_time = 30
opcache.enable = 1
```

### Database optimization:
```bash
# SQLite vacuum
sqlite3 bot_database.db "VACUUM;"
```

## 🔟 Qo'shimcha

### Monitoring:
1. [UptimeRobot](https://uptimerobot.com/) - Bot online ekanligini tekshirish
2. [Sentry](https://sentry.io/) - Xatolarni monitoring qilish

### Backup automation:
```bash
# Cron job (har kuni soat 2 da)
0 2 * * * cd /path/to/bot && ./backup.sh
```

**backup.sh**:
```bash
#!/bin/bash
DATE=$(date +%Y%m%d)
cp bot_database.db backups/db_$DATE.db
tar -czf backups/files_$DATE.tar.gz users/
# Eski backup'larni o'chirish (30 kundan eski)
find backups/ -name "*.db" -mtime +30 -delete
find backups/ -name "*.tar.gz" -mtime +30 -delete
```

### Load balancing:
Agar ko'p foydalanuvchi bo'lsa:
1. Ko'proq server qo'shing
2. Load balancer sozlang
3. Database ni taqsimlang

## ✅ Tayyor!

Endi sizning bot hosting platformangiz ishlashga tayyor!

**Keyingi qadamlar:**
1. ✅ Botni test qiling
2. ✅ Majburiy kanal qo'shing
3. ✅ Ta'riflarni sozlang
4. ✅ Foydalanuvchilarni taklif qiling

**Yordam kerakmi?**
- 📧 Telegram: [@WINAIKO](https://t.me/WINAIKO)
- 📚 Documentation: README.md
- 🐛 Issues: GitHub Issues

**Omad tilaymiz! 🚀**
