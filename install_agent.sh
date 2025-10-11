#!/data/data/com.termux/files/usr/bin/bash

# Gemini AI Agent o'rnatuvchi skript - Termux uchun

# --- Ranglar ---
RED='\033[1;31m'
GREEN='\033[1;32m'
YELLOW='\033[1;33m'
BLUE='\033[1;34m'
CYAN='\033[1;36m'
NC='\033[0m' # No Color

echo -e "${BLUE}╭────────────────────────────────────────────────╮${NC}"
echo -e "${BLUE}│${GREEN}  🤖 Gemini AI Agent O'rnatuvchi - Termux     ${BLUE}│${NC}"
echo -e "${BLUE}╰────────────────────────────────────────────────╯${NC}\n"

# --- Termux tekshirish ---
if [ ! -d "$PREFIX" ]; then
    echo -e "${RED}❌ Xato: Bu skript faqat Termux'da ishga tushirilishi kerak!${NC}"
    exit 1
fi

echo -e "${CYAN}[1/5]${NC} ${YELLOW}Paketlar yangilanmoqda...${NC}"
pkg update -y

echo -e "${CYAN}[2/5]${NC} ${YELLOW}Python va zarur dasturlar o'rnatilmoqda...${NC}"
pkg install -y python python-pip git

echo -e "${CYAN}[3/5]${NC} ${YELLOW}Python kutubxonalari o'rnatilmoqda...${NC}"
pip install --upgrade pip
pip install -r requirements.txt

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Python kutubxonalarni o'rnatishda xato!${NC}"
    exit 1
fi

echo -e "${CYAN}[4/5]${NC} ${YELLOW}Fayl ruxsatlari sozlanmoqda...${NC}"
chmod +x ai_agent.py

echo -e "${CYAN}[5/5]${NC} ${YELLOW}Konfiguratsiya yaratilmoqda...${NC}"
mkdir -p "$HOME/.config/gemini_agent"

echo -e "\n${GREEN}╭────────────────────────────────────────────────╮${NC}"
echo -e "${GREEN}│  ✅ O'rnatish muvaffaqiyatli yakunlandi!      │${NC}"
echo -e "${GREEN}╰────────────────────────────────────────────────╯${NC}\n"

echo -e "${CYAN}Agent'ni ishga tushirish uchun:${NC}"
echo -e "  ${YELLOW}python ai_agent.py${NC}\n"

echo -e "${CYAN}Birinchi ishga tushirishda:${NC}"
echo -e "  1️⃣  Google AI Studio'dan Gemini API kalitini oling"
echo -e "  2️⃣  Link: ${BLUE}https://makersuite.google.com/app/apikey${NC}"
echo -e "  3️⃣  API kalitni agent'ga kiriting\n"

echo -e "${GREEN}📚 Qo'llanma: README_uz.md faylini o'qing${NC}\n"

exit 0
