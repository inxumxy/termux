#!/data/data/com.termux/files/usr/bin/bash

# Gemini AI Assistant Installer for Termux
# Based on the structure by Alienkrishn, adapted for Google Gemini.

# --- Colors ---
RED='\033[1;31m'
GREEN='\033[1;32m'
YELLOW='\033[1;33m'
BLUE='\033[1;34m'
NC='\033[0m' # No Color

# --- Check if running in Termux ---
if [ ! -d "$PREFIX/bin" ]; then
    echo -e "${RED}Xato: Bu skript faqat Termux'da ishga tushirilishi kerak!${NC}"
    exit 1
fi

echo -e "${BLUE}╭──────────────────────────────────────────╮${NC}"
echo -e "${BLUE}│${NC}${GREEN}   Termux Gemini AI Assistant O'rnatuvchi   ${BLUE}│${NC}"
echo -e "${BLUE}╰──────────────────────────────────────────╯${NC}"

# --- Update packages ---
echo -e "${YELLOW}[*] Paketlar yangilanmoqda...${NC}"
pkg update -y && pkg upgrade -y

# --- Install dependencies ---
echo -e "${YELLOW}[*] Kerakli dasturlar (curl, jq) o'rnatilmoqda...${NC}"
pkg install -y curl jq

# --- Download the main script ---
# Skriptni to'g'ridan-to'g'ri GitHub Gist'dan yuklab olamiz
AI_SCRIPT_URL="https://gist.github.com/62782ee561d38d23cd3777b01bf048f1.git"

echo -e "${YELLOW}[*] Gemini AI Assistant yuklab olinmoqda...${NC}"
if curl -s -L -o "$PREFIX/bin/gemini-ai" "$AI_SCRIPT_URL"; then
    echo -e "${GREEN}[✓] Skript muvaffaqiyatli yuklab olindi.${NC}"
else
    echo -e "${RED}[X] Xato: Skriptni yuklab olib bo'lmadi. Internet aloqasini tekshiring.${NC}"
    exit 1
fi

# --- Make script executable ---
echo -e "${YELLOW}[*] Ishga tushirish huquqlari berilmoqda...${NC}"
chmod +x "$PREFIX/bin/gemini-ai"

# --- Create config directory ---
echo -e "${YELLOW}[*] Sozlamalar uchun papka yaratilmoqda...${NC}"
mkdir -p "$HOME/.config/gemini_assistant"

echo -e "${GREEN}[✓] O'rnatish muvaffaqiyatli yakunlandi!${NC}"
echo -e "\nAI Yordamchini ishga tushirish uchun terminalga shunchaki yozing: ${BLUE}gemini-ai${NC}"

# --- First run instructions ---
echo -e "\n${YELLOW}Birinchi ishga tushirishdan oldin:${NC}"
echo -e "1. ${BLUE}gemini-ai${NC} buyrug'ini tering"
echo -e "2. Sozlamalar (Settings) menyusiga kiring (2-raqam)"
echo -e "3. 'API Kaliti' (API Key) ni tanlang (1-raqam)"
echo -e "4. Google AI Studio'dan olgan Gemini API kalitingizni kiriting\n"

exit 0
