# ❓ Ko'p So'raladigan Savollar (FAQ)

## 🚀 Umumiy savollar

### Bot nima qiladi?
Bu platforma orqali siz o'zingizning Telegram botlaringizni yaratishingiz va host qilishingiz mumkin. PHP kodingizni yuklaysiz yoki AI yordamida bot yarattirasiz.

### Bepulmi?
Ha! FREE tarif bilan 1 ta bot va 1 MB storage bepul. Ko'proq botlar uchun PRO yoki VIP tarifni sotib olishingiz mumkin.

### Qanday to'lov usullari qo'llab-quvvatlanadi?
Hozircha admin orqali olmos sotib olish mumkin (@WINAIKO). Kelajakda Click, Payme qo'shiladi.

---

## 🤖 Bot yaratish

### Bot yaratish uchun nima kerak?
1. BotFather'dan bot token
2. PHP kod (yoki AI yordamchi)
3. Platforma hisobi

### Qanday tillarni qo'llab-quvvatlaydi?
Hozircha faqat PHP (procedural). Node.js va Python kelajakda qo'shiladi.

### AI Agent qanday ishlaydi?
Siz botning vazifasini yozing, AI (NONOCHA BOT) sizga PHP kod yozadi, xatolarni tuzatadi va yo'riqnoma beradi. Kunlik 20 so'rov limiti bor.

### Botim ishlamayapti, nima qilish kerak?
1. File Manager → log.txt ni tekshiring
2. Kodda xato bormi ko'ring
3. Webhook o'rnatilganligini tekshiring
4. Admin bilan bog'laning

---

## 💻 Kod va xavfsizlik

### Qanday kod yozish mumkin?
- ✅ PHP procedural kod
- ✅ Telegram Bot API
- ✅ JSON fayllar
- ❌ Vendor kutubxonalar
- ❌ MySQL/Database
- ❌ Python kod

### Nega vendor/autoload.php ta'qiqlangan?
Xavfsizlik uchun. Tashqi kutubxonalar server xavfsizligiga tahdid solishi mumkin.

### Nega MySQL ishlatish mumkin emas?
Har bir bot alohida database yaratsa, server resurslariga ortiqcha yuk tushadi. JSON fayllar yetarli.

### Qanday misollar bor?
`example_bot.php` faylida oddiy echo bot kodi bor. Uni asosiy qilib o'z botingizni yarating.

---

## 📁 File Manager

### File Manager nima?
Bu orqali botingiz fayllarini ko'rish, yuklab olish va o'chirish mumkin.

### Yangi fayl qanday yuklash mumkin?
Xavfsizlik sababli yangi fayl to'g'ridan-to'g'ri yuklash mumkin emas. Faqat bot yaratish orqali.

### Faylni qayta tahrirlash mumkinmi?
Ha, faylni yuklab oling, tahrirлang (QuickEdit, Acode) va yangi bot sifatida qayta yuklang (eski botni o'chiring).

---

## 💎 Balans va ta'riflar

### Olmos nima?
Bu platformaning virtual valyutasi. 1 olmos = 120 UZS.

### Qanday olmos sotib olish mumkin?
Admin bilan bog'laning (@WINAIKO), pul o'tkazing, olmos olasiz.

### Ta'riflar nimaga kerak?
- **FREE**: 1 bot, 1 MB - Sinab ko'rish uchun
- **PRO**: 4 bot, 4.5 MB - Professional ishlar uchun
- **VIP**: 7 bot, 15 MB - Katta loyihalar uchun

### Ta'rifni qanday yangilash mumkin?
Kabinet → Ta'rif → Kerakli tarifni tanlang → Olmos bilan to'lang

---

## 🔧 Texnik savollar

### Qaysi PHP versiyani qo'llab-quvvatlaydi?
PHP 7.4 va undan yuqori versiyalar.

### Webhook nima?
Bu orqali Telegram serveridan botingizga xabarlar keladi. Avtomatik sozlanadi.

### Log fayllar nima uchun?
Xatolarni aniqlash va tuzatish uchun. Har doim log.txt ni tekshiring.

### Storage limiti oshsa nima bo'ladi?
Yangi bot yoki fayl yuklash mumkin bo'lmaydi. Ta'rifni yangilang.

---

## 👨‍💼 Admin panel

### Admin panel kimga ko'rinadi?
Faqat ADMIN_ID ga. index.php da sozlangan.

### Qanday adminlik qilish mumkin?
- Ta'riflarni sozлаш
- Balans qo'shish
- Majburiy kanal sozлаш
- Post yuborish
- Statistika ko'rish
- Ban/Unban

### Majburiy a'zolik qanday ishlaydi?
Admin kanal qo'shadi, foydalanuvchi botga kirsa avval kanalga a'zo bo'lishi kerak.

---

## 🐛 Muammolar va yechimlar

### "Bot javob bermayapti"
**Yechim:**
1. Webhook tekshiring: `/getWebhookInfo`
2. PHP xatolarni ko'ring
3. SSL sertifikat bor-yo'qligini tekshiring

### "Database xatosi"
**Yechim:**
1. SQLite3 extension yoqing
2. bot_database.db ga write permission bering

### "Fayl yuklanmayapti"
**Yechim:**
1. upload_max_filesize ni oshiring
2. users/ papkasiga permission bering

### "AI Agent ishlamayapti"
**Yechim:**
1. GEMINI_API_KEY to'g'riligini tekshiring
2. Kunlik limit tugagan bo'lishi mumkin
3. Internet aloqani tekshiring

### "Storage limiti to'lgan"
**Yechim:**
1. Eski botlarni o'chiring
2. Kerak bo'lmagan fayllarni o'chiring
3. Ta'rifni yangilang

---

## 📚 Qo'llanmalar

### BotFather'dan token qanday olish mumkin?
1. [@BotFather](https://t.me/BotFather) ga yozing
2. `/newbot` yuboring
3. Bot nomini kiriting
4. Username kiriting (bot bilan tugashi kerak)
5. Tokenni ko'chirib oling

### User ID qanday bilish mumkin?
[@userinfobot](https://t.me/userinfobot) ga `/start` yuboring.

### Gemini API key qanday olish mumkin?
1. [Google AI Studio](https://makersuite.google.com/app/apikey) ga kiring
2. "Create API Key" bosing
3. API key ni ko'chirib oling

### Kod yozish uchun qanday ilova ishlatish mumkin?
**Android:**
- QuickEdit - Code Editor
- Acode - Code Editor
- Dcoder

**iOS:**
- Koder Code Editor
- Buffer Editor
- Textastic

**PC:**
- VS Code
- Sublime Text
- Notepad++

---

## 💡 Maslahatlar

### Yangi boshlovchilar uchun:
1. Avval FREE tarif bilan boshlang
2. example_bot.php ni o'rganing
3. Oddiy echo bot yarating
4. Log fayllarni doim tekshiring

### Pro foydalanuvchilar uchun:
1. Kod optimizatsiya qiling
2. Error handling qo'shing
3. JSON fayllar bilan ishlang
4. Backup olib turing

### AI Agent dan samarali foydalanish:
1. Aniq va batafsil savol bering
2. Xatolikni to'liq ko'rsating (log.txt)
3. Bosqichlab so'rang
4. Kunlik 20 so'rovni aqlli ishlating

---

## 🆘 Yordam

### Qo'shimcha yordam kerakmi?

**Telegram:**
- Admin: [@WINAIKO](https://t.me/WINAIKO)

**Dokumentatsiya:**
- README.md - Umumiy qo'llanma
- INSTALLATION.md - O'rnatish
- FEATURES.md - Barcha funksiyalar
- CHANGELOG.md - Yangiliklar

**GitHub:**
- Issues - Muammo haqida xabar bering
- Discussions - Savol-javob

---

**Savolingiz topilmadimi?**

Admin bilan bog'laning yoki GitHub Issues da savol bering! 🚀
