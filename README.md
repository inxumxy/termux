# PHP Telegram Bot Hosting Platform

Bu PHP Telegram botlarni yaratish va hosting qilish uchun to'liq platform.

## ⚙️ O'rnatish

### 1. Server talablari
- PHP 7.4 yoki yuqori
- cURL extension
- JSON extension
- File write permissions

### 2. Sozlash

1. `index.php` faylini serveringizga yuklang
2. Webhook o'rnating:
```bash
https://api.telegram.org/bot8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E/setWebhook?url=https://SIZNING_DOMENINGIZ/index.php
```

3. Kerakli papkalar avtomatik yaratiladi:
   - `data/` - Foydalanuvchi ma'lumotlari va sozlamalar
   - `bots/` - Foydalanuvchilar tomonidan yaratilgan botlar

### 3. Webhook URL o'zgartirish

`index.php` faylida quyidagi qatorni toping va domeningizni kiriting:
```php
$webhook_url = "https://SIZNING_DOMENINGIZ/bots/{$user_id}/{$bot_num}/index.php";
```

## 🚀 Xususiyatlar

### Foydalanuvchi funksiyalari:
- ✅ Majburiy kanal a'zoligi tekshiruvi
- 🤖 AI Agent bilan bot yaratish (Gemini API)
- 💻 Kod yuklash orqali bot yaratish
- 📁 File Manager
- 🤖 Botlarni boshqarish
- 💎 Balans va tarif tizimi
- 📞 Admin bilan bog'lanish

### Admin funksiyalari:
- ⚙️ Ta'riflarni sozlash
- 💎 Balans qo'shish
- 📢 Majburiy kanal boshqaruvi
- 📣 Broadcasting (xabar yuborish)
- 🔧 Maintenance mode
- 📊 Statistika
- 🚫 Ban/Unban

## 📊 Ta'riflar (Default)

| Ta'rif | Botlar | Storage | Narx |
|--------|--------|---------|------|
| Free   | 1      | 1 MB    | 0 olmos |
| Pro    | 4      | 4.5 MB  | 99 olmos |
| VIP    | 7      | 15 MB   | 299 olmos |

## 🔒 Xavfsizlik

Bot quyidagi xavfli amallarni bloklaydi:
- ❌ `vendor/autoload.php` ishlatish
- ❌ Database ulanishlari (MySQL, PostgreSQL, etc.)
- ❌ Shell buyruqlari (exec, system, shell_exec)
- ❌ Python kod
- ❌ Fayl o'chirish funksiyalari hosting tashqarisida

## 🤖 AI Agent

- **Model:** Gemini 2.5 Flash
- **Kunlik limit:** 20 so'rov
- **Xususiyatlar:**
  - Avtomatik kod generatsiya
  - Xato aniqlash va tuzatish
  - Log tahlili
  - Avtomatik webhook o'rnatish

## 📝 Buyruqlar

- `/start` - Botni boshlash
- `/admin` - Admin panel (faqat admin uchun)

## 🗂️ Fayl tuzilmasi

```
/workspace/
├── index.php           # Asosiy bot fayli
├── data/              # Ma'lumotlar
│   ├── users.json     # Foydalanuvchilar
│   ├── settings.json  # Sozlamalar
│   ├── banned.json    # Bloklangan foydalanuvchilar
│   └── ai_history_*.json  # AI tarix
├── bots/              # Foydalanuvchi botlari
│   └── {user_id}/
│       └── {bot_num}/
│           ├── index.php
│           └── log.txt
└── bot_error.log      # Xato loglari
```

## 🔧 Sozlamalar

### Bot konfiguratsiyasi
```php
define('BOT_TOKEN', '8006687331:AAHvSMtO5lf0LuKYiW1GTivwzc9p6SeVU7E');
define('ADMIN_ID', 7019306015);
define('GEMINI_API_KEY', 'AIzaSyCxAPfTD0dp4PP0S4XR3wtpzlzszeBr3hw');
```

### Ta'riflarni o'zgartirish
`data/settings.json` faylini tahrirlang:
```json
{
  "tariffs": {
    "free": {"bots": 1, "storage": 1, "price": 0, "name": "Free"},
    "pro": {"bots": 4, "storage": 4.5, "price": 99, "name": "Pro"},
    "vip": {"bots": 7, "storage": 15, "price": 299, "name": "VIP"}
  }
}
```

## 📞 Yordam

Admin: [@WINAIKO](https://t.me/WINAIKO)

## 📄 Litsenziya

Shaxsiy foydalanish uchun.
