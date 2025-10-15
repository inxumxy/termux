# 🚀 PHP Telegram Bot Hosting Platform - O'rnatish Qo'llanmasi

## 📋 Talablar

- PHP 7.4 yoki yuqori versiya
- Web server (Apache/Nginx)
- cURL extension
- SSL sertifikat (HTTPS)
- Domen nomi

## 📥 1-Qadam: Fayllarni yuklash

Barcha fayllarni serveringizga yuklang:
```
/var/www/html/
├── index.php
├── .htaccess
├── setup_webhook.sh
└── README.md
```

## 🔑 2-Qadam: Ruxsatlarni sozlash

```bash
cd /var/www/html
chmod 755 index.php
chmod 755 setup_webhook.sh
chmod 755 .
```

## 🌐 3-Qadam: Webhook o'rnatish

### Avtomatik usul:
```bash
./setup_webhook.sh
```

### Qo'lda usul:
```bash
curl "https://api.telegram.org/bot8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E/setWebhook?url=https://SIZNING_DOMENINGIZ/index.php"
```

## ⚙️ 4-Qadam: Sozlamalarni o'zgartirish

`index.php` faylini oching va quyidagi qatorni toping:

```php
// 885-qator atrofida
$webhook_url = "https://yourdomain.com/bots/{$user_id}/{$bot_num}/index.php";
```

O'zgartiring:
```php
$webhook_url = "https://SIZNING_DOMENINGIZ/bots/{$user_id}/{$bot_num}/index.php";
```

**Muhim:** `SIZNING_DOMENINGIZ` ni haqiqiy domeningiz bilan almashtiring!

## ✅ 5-Qadam: Botni test qilish

1. Telegram'da botingizni oching
2. `/start` buyrug'ini yuboring
3. Agar xush kelibsiz xabari kelsa - hammasi tayyor! 🎉

## 🔧 Konfiguratsiya

### Bot sozlamalari

`index.php` faylidagi konfiguratsiya:

```php
define('BOT_TOKEN', '8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E');
define('ADMIN_ID', 7019306015);
define('GEMINI_API_KEY', 'AIzaSyCxAPfTD0dp4PP0S4XR3wtpzlzszeBr3hw');
```

### Ta'riflarni o'zgartirish

Bot ishga tushgandan keyin `data/settings.json` fayli yaratiladi. Uni tahrirlash orqali ta'riflarni o'zgartirishingiz mumkin:

```json
{
  "maintenance": false,
  "maintenance_until": 0,
  "mandatory_channels": [],
  "tariffs": {
    "free": {
      "bots": 1,
      "storage": 1,
      "price": 0,
      "name": "Free"
    },
    "pro": {
      "bots": 5,
      "storage": 10,
      "price": 99,
      "name": "Pro"
    },
    "vip": {
      "bots": 10,
      "storage": 50,
      "price": 299,
      "name": "VIP"
    }
  }
}
```

## 📁 Papkalar tuzilishi

Bot ishga tushgach, quyidagi papkalar avtomatik yaratiladi:

```
/var/www/html/
├── index.php
├── data/
│   ├── users.json
│   ├── settings.json
│   ├── banned.json
│   └── ai_history_*.json
├── bots/
│   └── {user_id}/
│       └── {bot_number}/
│           ├── index.php
│           └── log.txt
├── bot_error.log
└── webhook.log
```

## 🛡️ Xavfsizlik

Bot avtomatik ravishda quyidagilarni bloklaydi:

- ❌ `vendor/autoload.php` ishlatish
- ❌ Database ulanishlari
- ❌ Shell buyruqlari
- ❌ Python kod
- ❌ Hosting uchun xavfli funksiyalar

## 🎯 Foydalanish

### Oddiy foydalanuvchi:

1. `/start` - Botni boshlash
2. **AI Agent bot yaratish** - Gemini AI yordamida bot yaratish
3. **Kod orqali bot** - Tayyor PHP kod yuklash
4. **File Meneger** - Fayllarni boshqarish
5. **Mening Botlarim** - Yaratilgan botlar ro'yxati
6. **Kabinet** - Balans va ta'rif
7. **Yordam** - Qo'llanma

### Admin:

1. `/admin` - Admin panelni ochish
2. Ta'riflarni sozlash
3. Foydalanuvchilarga balans qo'shish
4. Majburiy kanal qo'shish
5. Xabar yuborish (broadcast)
6. Statistika ko'rish
7. Foydalanuvchilarni boshqarish

## 🐛 Muammolarni hal qilish

### Webhook o'rnatilmadi?

```bash
# Webhook holatini tekshirish
curl "https://api.telegram.org/bot8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E/getWebhookInfo"
```

### Bot javob bermayapti?

1. `bot_error.log` va `webhook.log` fayllarini tekshiring
2. PHP error_log ni tekshiring: `/var/log/php_errors.log`
3. Web server loglarini tekshiring: `/var/log/apache2/error.log`

### PHP xatolari?

```bash
# PHP konfiguratsiyasini tekshirish
php -v
php -m | grep curl
php -m | grep json
```

### Fayllar yaratilmayapti?

```bash
# Ruxsatlarni tekshirish va o'zgartirish
chmod 755 /var/www/html
chmod 755 /var/www/html/data
chmod 755 /var/www/html/bots
```

## 📞 Yordam

Agar muammolar bo'lsa:

1. README.md faylini o'qing
2. Loglarni tekshiring
3. Admin bilan bog'laning: [@WINAIKO](https://t.me/WINAIKO)

## 💡 Maslahatlar

1. **Backup oling**: `data/` papkasini muntazam backup qiling
2. **Loglarni kuzating**: Xatolarni tez aniqlash uchun loglarni tekshiring
3. **HTTPS ishlating**: Telegram faqat HTTPS webhook qabul qiladi
4. **PHP sozlamalarini optimallashtiring**: Memory limit va execution time'ni oshiring

## 🎉 Tayyor!

Bot muvaffaqiyatli o'rnatildi va ishga tayyor!

Botingizdan foydalanishni boshlang: [@YOURBOT](https://t.me/YOURBOT)

---

**Yaratildi:** 2025-10-15
**Versiya:** 1.0
**Til:** PHP (Procedural)
**API:** Telegram Bot API
**AI:** Google Gemini 2.5 Flash
