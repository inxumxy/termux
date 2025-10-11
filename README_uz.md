# 🤖 Gemini AI Agent - Termux Edition

Termux terminal uchun kuchli AI agent. Google Gemini API va LangChain frameworkidan foydalanadi.

## ✨ Xususiyatlar

- 📝 **Kod yozish va tahrirlash** - AI agent sizga kod yozishda yordam beradi
- 📁 **Fayl operatsiyalari** - Fayllarni yaratish, o'qish, tahrirlash va o'chirish
- 🐍 **Python kod bajarish** - Python kodini to'g'ridan-to'g'ri bajaring
- 💻 **Terminal buyruqlari** - Shell buyruqlarini agent orqali bajaring
- 🧠 **Suhbat xotirasi** - Agent oldingi suhbatlarni eslab qoladi
- 🔧 **Ko'p funksional toollar** - Turli xil vazifalarni bajarish uchun 8 ta tool

## 📦 O'rnatish

### 1. Termux'ni yangilash

```bash
pkg update && pkg upgrade -y
```

### 2. Python va kerakli paketlarni o'rnatish

```bash
pkg install -y python python-pip git
```

### 3. Loyihani klonlash yoki fayllarni yuklab olish

```bash
# Agar Git repositoriy bo'lsa:
git clone <repository_url>
cd <directory>

# Yoki fayllarni qo'lda ko'chirib olish mumkin
```

### 4. Python kutubxonalarini o'rnatish

```bash
pip install -r requirements.txt
```

### 5. Gemini API kalitini olish

1. [Google AI Studio](https://makersuite.google.com/app/apikey) saytiga kiring
2. "Create API Key" tugmasini bosing
3. API kalitni nusxa oling

## 🚀 Ishlatish

### Agent'ni ishga tushirish

```bash
python ai_agent.py
```

Birinchi ishga tushirishda sizdan Gemini API kaliti so'raladi. Uni kiriting va Enter bosing.

### Asosiy buyruqlar

Agent ishga tushgandan keyin quyidagi buyruqlardan foydalanishingiz mumkin:

- `/help` - Yordam ma'lumotlarini ko'rish
- `/clear` - Ekranni tozalash
- `/history` - Suhbat tarixini ko'rish
- `/reset` - Suhbatni qayta boshlash
- `/exit` - Agent'dan chiqish

### Misollar

#### 1. Kod yozish

```
Siz > Python'da Fibonacci sonlarini hisoblaydigan funksiya yoz
```

#### 2. Fayl yaratish

```
Siz > test.py nomli faylda "Hello World" dasturi yarat
```

#### 3. Direktoriya ko'rish

```
Siz > Joriy direktoriya tarkibini ko'rsat
```

#### 4. Kod bajarish

```
Siz > print(2 + 2) kodini bajar
```

#### 5. Terminal buyrug'i

```
Siz > ls -la buyrug'ini bajar
```

## 🔧 Agent'ning qobiliyatlari

Agent quyidagi toollardan foydalanadi:

1. **read_file** - Faylni o'qish
2. **write_file** - Yangi fayl yaratish yoki tahrirlash
3. **append_file** - Faylga qo'shimcha qilish
4. **list_directory** - Direktoriya tarkibini ko'rish
5. **delete_file** - Faylni o'chirish
6. **create_directory** - Yangi direktoriya yaratish
7. **execute_python** - Python kodini bajarish
8. **execute_shell** - Terminal buyruqni bajarish

## 📁 Fayl tuzilmasi

```
.
├── ai_agent.py           # Asosiy agent dasturi
├── requirements.txt      # Python kutubxonalar ro'yxati
├── README_uz.md         # O'zbekcha qo'llanma (bu fayl)
├── gemini-ai.sh         # Oddiy Gemini chat (bash)
└── termux.sh            # Termux o'rnatuvchi skript
```

## ⚙️ Konfiguratsiya

Agent quyidagi direktoriyada sozlamalarni saqlaydi:

```
~/.config/gemini_agent/
├── config.json                  # Asosiy sozlamalar
└── conversation_history.json    # Suhbat tarixi
```

API kalitni o'zgartirish uchun:

```bash
rm ~/.config/gemini_agent/config.json
python ai_agent.py
```

## 🛡️ Xavfsizlik

- Python kod bajarish cheklangan namespace'da ishlaydi
- Xavfli shell buyruqlar (masalan, `rm -rf /`) bloklangan
- API kaliti shifrlangan tarzda saqlanadi

## 🐛 Muammolarni hal qilish

### ModuleNotFoundError

```bash
pip install -r requirements.txt
```

### API xatosi

- API kalitingizni tekshiring
- Internet aloqangizni tekshiring
- [Google AI Studio](https://makersuite.google.com/)da API kalitingiz faol ekanligini tasdiqlang

### Permission denied

```bash
chmod +x ai_agent.py
```

## 📚 Qo'shimcha ma'lumot

- [LangChain Documentation](https://python.langchain.com/)
- [Google Gemini API](https://ai.google.dev/)
- [Termux Wiki](https://wiki.termux.com/)

## 💡 Maslahatlar

1. **Aniq so'rovlar bering** - Qancha aniq so'rasangiz, agent shuncha yaxshi javob beradi
2. **Bosqichma-bosqich ishlang** - Murakkab vazifalarni kichik qismlarga bo'ling
3. **Suhbat tarixidan foydalaning** - Agent oldingi suhbatlarni eslaydi
4. **Toollarni bilib oling** - `/help` buyrug'i bilan barcha imkoniyatlarni o'rganing

## 📝 Litsenziya

Bu loyiha ochiq kodli va o'quv maqsadlarida foydalanish uchun yaratilgan.

## 🤝 Hissa qo'shish

Fikr-mulohazalar va taklif lar har doim xush kelibsiz!

---

**Muallif:** AI Assistant  
**Versiya:** 1.0  
**Sana:** 2025
