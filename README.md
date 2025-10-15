# 🤖 PHP Telegram Bot Hosting Platform

Bu loyiha foydalanuvchilarga o'z Telegram botlarini yaratish va host qilish imkoniyatini beruvchi platforma.

## ✨ Xususiyatlar

### 👥 Foydalanuvchi funksiyalari:
- 🤖 **AI Agent bot yaratish** - Gemini AI yordamida bot yaratish
- 💻 **Kod orqali bot** - O'z PHP kodingiz bilan bot yaratish
- 📁 **File Manager** - Bot fayllarini boshqarish
- 🤖 **Mening Botlarim** - Yaratgan botlarni ko'rish va boshqarish
- 💼 **Kabinet** - Balans, tarif va statistika
- ❓ **Yordam** - Bot qo'llanmasi
- 📞 **Admin bilan bog'lanish** - Savol va takliflar uchun

### 👨‍💼 Admin funksiyalari:
- ⚙️ **Ta'rif belgilash** - Free, Pro, VIP tarif sozlamalari
- 💎 **Balans qo'shish** - Foydalanuvchilarga olmos berish
- 📢 **Majburiy kanal** - A'zolik kanallarini boshqarish
- 📣 **Reklama/Post** - Barcha foydalanuvchilarga xabar yuborish
- 🔧 **Maintenance** - Texnik ishlar rejimi
- 📊 **Statistika** - Platform statistikasi
- 🚫 **Ban/Unban** - Foydalanuvchilarni bloklash

### 🔒 Xavfsizlik:
- ✅ Xavfli kod tekshiruvi (anti-cheat)
- ✅ vendor/autoload.php ta'qiqlangan
- ✅ MySQL va database amallar bloklangan
- ✅ Python kod ta'qiqlangan
- ✅ Xavfli PHP funksiyalar bloklangan
- ✅ File size va storage limitleri

### 💎 Ta'riflar (default):
- **FREE**: 1 bot, 1 MB storage, 0 olmos
- **PRO**: 4 bot, 4.5 MB storage, 99 olmos
- **VIP**: 7 bot, 15 MB storage, 299 olmos

## 📋 O'rnatish

### 1. Talablar:
- PHP 7.4 yoki yuqori
- SQLite3 support
- cURL extension
- Apache/Nginx web server
- SSL sertifikat (Telegram webhook uchun)

### 2. Fayllarni yuklash:
```bash
git clone https://github.com/yourusername/php-telegram-bot-hosting-platform.git
cd php-telegram-bot-hosting-platform
```

### 3. Permissions berish:
```bash
chmod 755 index.php webhook.php setup.php
mkdir -p users
chmod 777 users/
```

### 4. Konfiguratsiya:
1. `index.php` faylidagi quyidagi sozlamalarni o'zgartiring:
   - `BOT_TOKEN` - BotFather'dan olingan token
   - `ADMIN_ID` - Sizning Telegram ID
   - `GEMINI_API_KEY` - Google AI Studio'dan API key
   - `yourdomain.com` - O'z domeningiz

2. `setWebhookForUserBot` funksiyasida ham domenni o'zgartiring

### 5. Webhook o'rnatish:
```bash
php setup.php
```

Yoki brauzerda: `https://yourdomain.com/setup.php`

### 6. Botni ishga tushirish:
Telegram'da botingizni qidiring va `/start` bosing!

## 📁 Fayl strukturasi:

```
/
├── index.php              # Asosiy bot fayli
├── webhook.php            # Foydalanuvchi botlari uchun webhook
├── setup.php              # O'rnatish scripti
├── .htaccess             # Apache konfiguratsiyasi
├── config.example.php    # Misol konfiguratsiya
├── bot_database.db       # SQLite database
├── bot_log.txt           # Bot loglari
├── bot_error.log         # Xato loglari
└── users/                # Foydalanuvchi botlari
    └── [user_id]/
        └── [bot_number]/
            ├── index.php
            └── log.txt
```

## 🚀 Ishlatish

### Foydalanuvchi uchun:

1. **Bot yaratish (Kod orqali)**:
   - "💻 Kod orqali bot" tugmasini bosing
   - BotFather'dan olingan tokenni yuboring
   - PHP fayl ko'rinishida bot kodini yuboring
   - Bot avtomatik tekshiriladi va webhook o'rnatiladi

2. **AI bilan bot yaratish**:
   - "🤖 AI Agent bot yaratish" tugmasini bosing
   - Qanday bot yaratmoqchi ekanligingizni yozing
   - AI sizga kod yozadi va yo'riqnoma beradi
   - Kunlik 20 so'rov limiti

3. **File Manager**:
   - Botingiz fayllarini ko'rish
   - Fayllarni yuklab olish
   - Fayllarni o'chirish

4. **Ta'rifni yangilash**:
   - Kabinet → Ta'rif
   - Kerakli tarifni tanlang
   - Olmos bilan to'lang

### Admin uchun:

1. **Admin panel**:
   - `/admin` buyrug'i yoki "Admin Panel" tugmasi

2. **Balans qo'shish**:
   - Admin Panel → Balans qo'shish
   - User ID ni kiriting
   - Olmos miqdorini kiriting

3. **Majburiy kanal**:
   - Admin Panel → Majburiy kanal
   - Kanal qo'shish
   - Kanal ID yoki @username kiriting

4. **Post yuborish**:
   - Admin Panel → Reklama/Post
   - Xabaringizni yuboring
   - Barcha foydalanuvchilarga yuboriladi

## 🛠 API Integrations

### Telegram Bot API
- sendMessage
- editMessageText
- answerCallbackQuery
- sendDocument
- getChatMember
- getMe
- setWebhook

### Google Gemini AI API
- Model: gemini-2.5-flash
- generateContent endpoint
- Conversation history support
- Daily limit: 20 requests per user

## 🔧 Troubleshooting

### Bot javob bermayapti:
1. Webhook to'g'ri o'rnatilganligini tekshiring: `https://api.telegram.org/bot<TOKEN>/getWebhookInfo`
2. Loglarni ko'ring: `bot_log.txt` va `bot_error.log`
3. PHP xatolarini tekshiring

### Foydalanuvchi boti ishlamayapti:
1. `users/[user_id]/[bot_number]/log.txt` faylini tekshiring
2. Kodda xatolik borligini tekshiring
3. Webhook o'rnatilganligini tekshiring

### Database xatolari:
1. `bot_database.db` fayliga write permission borligini tekshiring
2. SQLite3 extension yoqilganligini tekshiring

## 🔐 Xavfsizlik maslahatlar

1. **.htaccess** orqali database va log fayllarni himoyalang
2. **BOT_TOKEN** va **GEMINI_API_KEY** ni maxfiy saqlang
3. **SSL sertifikat** ishlating
4. **PHP versiyasini** yangilab turing
5. **File upload** limitlarini to'g'ri sozlang

## 📊 Database struktura

### Tables:
- `users` - Foydalanuvchilar
- `bots` - Yaratilgan botlar
- `channels` - Majburiy kanallar
- `tariffs` - Ta'riflar
- `admin_messages` - Admin xabarlari
- `user_states` - Foydalanuvchi holatlari
- `ai_conversations` - AI suhbatlar
- `ai_usage` - AI limitlar
- `maintenance` - Texnik ishlar

## 📝 Bot komandalari

### Foydalanuvchi:
- `/start` - Botni boshlash

### Admin:
- `/admin` - Admin panel

## 💡 Maslahatlar

1. **Bot yaratishdan oldin**: BotFather'dan token oling
2. **Kod yozishda**: Procedural PHP ishlating (OOP emas)
3. **Xavfsizlik**: Vendor, MySQL, Python ishlatmang
4. **Storage**: Fayl hajmini nazorat qiling
5. **AI Agent**: Aniq va tushunarli savol bering

## 🤝 Hissa qo'shish

Pull request'lar qabul qilinadi! Katta o'zgarishlar uchun avval issue oching.

## 📝 Litsenziya

MIT License

## 👨‍💻 Muallif

Created with ❤️ by WINAIKO

## 📞 Aloqa

- Telegram: [@WINAIKO](https://t.me/WINAIKO)

## ⭐ Support

Agar loyiha foydali bo'lsa, GitHub'da ⭐ qo'yishni unutmang!

---

## 📖 To'liq qo'llanma

### Botni hosting ga joylashtirish:

1. **Hosting talab etiladi**:
   - PHP 7.4+
   - SQLite3
   - cURL
   - SSL sertifikat
   - Public_html yoki www papkasi

2. **Fayllarni yuklash**:
   - Barcha fayllarni public_html ga yuklab oling
   - users/ papkasini yarating va chmod 777 bering

3. **Sozlash**:
   - index.php da BOT_TOKEN, ADMIN_ID, GEMINI_API_KEY ni o'zgartiring
   - setup.php ni ishga tushiring

4. **Test qiling**:
   - Telegram'da /start bosing
   - Barcha tugmalarni test qiling

### Muammolarni hal qilish:

**"Bot javob bermayapti"**:
- Webhook URL to'g'riligini tekshiring
- SSL sertifikat faolligini tekshiring
- PHP error loglarni ko'ring

**"Fayl yuklanmayapti"**:
- upload_max_filesize ni tekshiring
- users/ papkasiga permission bering

**"Database xatosi"**:
- SQLite3 extension yoqilganligini tekshiring
- bot_database.db ga write permission bering

### Kengaytirish:

1. **Custom ta'riflar**: tariffs jadvalini o'zgartiring
2. **Ko'proq til**: Tarjimalar qo'shing
3. **Payment gateway**: To'lov tizimini integratsiya qiling
4. **Monitoring**: Bot usage tracking qo'shing
