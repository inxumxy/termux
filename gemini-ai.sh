#!/data/data/com.termux/files/usr/bin/bash

# Termux Gemini AI Assistant
# Adapted for Google Gemini API. Original structure by Alienkrishn.
# Version: 1.0 (Gemini Edition)

# --- ANSI Color Codes ---
RED='\033[1;31m'
GREEN='\033[1;32m'
YELLOW='\033[1;33m'
BLUE='\033[1;34m'
MAGENTA='\033[1;35m'
CYAN='\033[1;36m'
WHITE='\033[1;37m'
GRAY='\033[0;37m'
NC='\033[0m' # No Color

# --- Configuration ---
CONFIG_DIR="$HOME/.config/gemini_assistant"
HISTORY_FILE="$CONFIG_DIR/history.json"
CONFIG_FILE="$CONFIG_DIR/config"
API_KEY_FILE="$CONFIG_DIR/api_key"
VERSION="1.0"

# --- Default settings ---
MODEL="gemini-pro"
ENABLE_HISTORY=true
MAX_TOKENS=1024
TEMPERATURE=0.7
HISTORY_LENGTH=6 # Suhbat konteksti uchun saqlanadigan xabarlar soni (juft bo'lishi kerak)

# --- Functions ---

init_config_dir() {
    if [ ! -d "$CONFIG_DIR" ]; then
        mkdir -p "$CONFIG_DIR"
        chmod 700 "$CONFIG_DIR"
    fi
    if [ ! -f "$HISTORY_FILE" ]; then
        echo "[]" > "$HISTORY_FILE"
    fi
}

load_api_key() {
    [ -f "$API_KEY_FILE" ] && API_KEY=$(cat "$API_KEY_FILE" | tr -d '\n') || API_KEY=""
}

save_api_key() {
    echo -n "$1" > "$API_KEY_FILE"
    chmod 600 "$API_KEY_FILE"
}

load_config() {
    if [ -f "$CONFIG_FILE" ]; then
        source "$CONFIG_FILE"
    else
        save_config
    fi
    load_api_key
}

save_config() {
    cat > "$CONFIG_FILE" <<EOF
MODEL="$MODEL"
ENABLE_HISTORY=$ENABLE_HISTORY
MAX_TOKENS=$MAX_TOKENS
TEMPERATURE=$TEMPERATURE
HISTORY_LENGTH=$HISTORY_LENGTH
EOF
    chmod 600 "$CONFIG_FILE"
}

print_header() {
    clear
    echo -e "${BLUE}╭──────────────────────────────────────────╮${NC}"
    echo -e "${BLUE}│${NC}${WHITE}     Termux Gemini AI Assistant ${GRAY}v$VERSION${NC}     ${BLUE}│${NC}"
    echo -e "${BLUE}╰──────────────────────────────────────────╯${NC}\n"
}

print_menu() {
    echo -e "${YELLOW}1.${NC} Suhbatni Boshlash"
    echo -e "${YELLOW}2.${NC} Sozlamalar"
    echo -e "${YELLOW}3.${NC} Suhbat Tarixini Ko'rish"
    echo -e "${YELLOW}4.${NC} Chiqish"
    echo ""
}

print_settings_menu() {
    echo -e "${MAGENTA}╭──────────────────────────────────────────╮${NC}"
    echo -e "${MAGENTA}│${NC}${WHITE}           AI Sozlamalari             ${MAGENTA}│${NC}"
    echo -e "${MAGENTA}╰──────────────────────────────────────────╯${NC}"
    echo -e "${CYAN}1.${NC} API Kaliti:         ${GREEN}${API_KEY:0:4}****${API_KEY: -4}${NC}"
    echo -e "${CYAN}2.${NC} Model:              ${GREEN}$MODEL${NC} ${GRAY}(o'zgartirib bo'lmaydi)${NC}"
    echo -e "${CYAN}3.${NC} Maks. Tokenlar:     ${GREEN}$MAX_TOKENS${NC} ${GRAY}(100-8192)${NC}"
    echo -e "${CYAN}4.${NC} Harorat (Temp):     ${GREEN}$TEMPERATURE${NC} ${GRAY}(0.1-1.0)${NC}"
    echo -e "${CYAN}5.${NC} Suhbat Tarixi:      ${GREEN}$ENABLE_HISTORY${NC}"
    echo -e "${CYAN}6.${NC} Tarixni Tozalash"
    echo -e "${CYAN}7.${NC} Bosh Menuga Qaytish"
    echo ""
}

init() {
    init_config_dir
    load_config
    
    if ! command -v jq &> /dev/null || ! command -v curl &> /dev/null; then
       echo -e "${RED}Xato: 'jq' yoki 'curl' topilmadi. O'rnatuvchini ishga tushiring.${NC}"
       exit 1
    fi
    
    if [ -z "$API_KEY" ]; then
        echo -e "${RED}Diqqat: API kaliti kiritilmagan!${NC}"
        echo -e "Iltimos, sozlamalar menyusida Gemini API kalitingizni kiriting."
        sleep 2
    fi
}

chat_session() {
    echo -e "\n${GREEN}Suhbat boshlanmoqda...${NC}"
    echo -e "${GRAY}'exit' yoki 'quit' deb yozib, suhbatni yakunlang.${NC}\n"
    
    while true; do
        echo -ne "${YELLOW}Siz: ${NC}"
        read -r user_prompt
        
        if [[ "$user_prompt" =~ ^(exit|quit)$ ]]; then break; fi
        if [ -z "$user_prompt" ]; then continue; fi
        
        # --- API Request Logic ---
        echo -ne "${GRAY}🤖 O'ylanmoqda...${NC}\r"

        local history_context='[]'
        if [ "$ENABLE_HISTORY" = true ]; then
            history_context=$(jq '.' "$HISTORY_FILE" | jq ".[-${HISTORY_LENGTH}:]")
        fi

        # Add user prompt to history
        local new_history_entry=$(jq -n --arg prompt "$user_prompt" \
            '{role: "user", parts: [{text: $prompt}]}')
        history_context=$(echo "$history_context" | jq ". += [$new_history_entry]")

        # Construct payload
        local payload=$(jq -n \
            --argjson contents "$history_context" \
            --argjson temperature "$TEMPERATURE" \
            --argjson max_tokens "$MAX_TOKENS" \
            '{
                "contents": $contents,
                "generationConfig": {
                    "temperature": $temperature,
                    "maxOutputTokens": $max_tokens
                }
            }')
        
        API_URL="https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=${API_KEY}"
        
        response=$(timeout 45 curl -s -X POST -H "Content-Type: application/json" -d "$payload" "$API_URL")
        
        echo -ne "                            \r" # Clear thinking indicator
        
        if [ $? -eq 124 ]; then
            echo -e "${RED}Xato: So'rov 45 soniyada javob bermadi (timeout).${NC}"
            continue
        fi

        if echo "$response" | jq -e '.error' >/dev/null; then
            error_msg=$(echo "$response" | jq -r '.error.message')
            echo -e "${RED}API Xatosi: ${error_msg}${NC}"
            continue
        fi

        answer=$(echo "$response" | jq -r '.candidates[0].content.parts[0].text // ""')

        if [ -z "$answer" ]; then
            reason=$(echo "$response" | jq -r '.candidates[0].finishReason // "Noma\'lum"')
            echo -e "${YELLOW}Model javob qaytarmadi. Sabab: ${reason}${NC}"
            echo -e "${GRAY}(Bu xavfsizlik sozlamalari yoki boshqa cheklovlar tufayli bo'lishi mumkin)${NC}"
            continue
        fi

        # --- Display Response ---
        echo -e "\n${BLUE}╭──────────────────────────────────────────╮${NC}"
        echo -e "${BLUE}│${NC} ${CYAN}Gemini AI${NC}"
        echo -e "${BLUE}╰──────────────────────────────────────────╯${NC}"
        echo -e "${WHITE}$answer${NC}"
        echo -e "${BLUE}────────────────────────────────────────────${NC}\n"

        if [ "$ENABLE_HISTORY" = true ]; then
            local model_response_entry=$(jq -n --arg answer "$answer" \
                '{role: "model", parts: [{text: $answer}]}')
            
            # Save user prompt and model response to history file
            final_history=$(echo "$history_context" | jq ". += [$model_response_entry]")
            echo "$final_history" > "$HISTORY_FILE"
        fi
    done
}

settings_menu() {
    while true; do
        print_header
        print_settings_menu
        read -p "Tanlovingizni kiriting: " choice
        
        case $choice in
            1) 
                read -p "Yangi Gemini API kalitini kiriting: " new_key
                save_api_key "$new_key"
                load_api_key
                echo -e "${GREEN}API kaliti muvaffaqiyatli yangilandi!${NC}"; sleep 1
                ;;
            2)
                echo -e "${YELLOW}Hozirda faqat 'gemini-pro' modeli qo'llab-quvvatlanadi.${NC}"; sleep 2
                ;;
            3) 
                read -p "Maksimal tokenlar sonini kiriting (100-8192): " MAX_TOKENS
                save_config; echo -e "${GREEN}Sozlama saqlandi!${NC}"; sleep 1
                ;;
            4) 
                read -p "Haroratni kiriting (0.1-1.0): " TEMPERATURE
                save_config; echo -e "${GREEN}Sozlama saqlandi!${NC}"; sleep 1
                ;;
            5) 
                ENABLE_HISTORY=$([ "$ENABLE_HISTORY" = true ] && echo false || echo true)
                save_config; echo -e "${GREEN}Suhbat tarixi holati o'zgardi: $ENABLE_HISTORY${NC}"; sleep 1
                ;;
            6) 
                read -p "Rostan ham butun suhbat tarixini o'chirmoqchimisiz? (ha/yo'q): " confirm
                if [ "$confirm" = "ha" ]; then
                    echo "[]" > "$HISTORY_FILE"
                    echo -e "${GREEN}Tarix tozalandi!${NC}"
                else
                    echo -e "${YELLOW}Amal bekor qilindi.${NC}"
                fi
                sleep 1
                ;;
            7) break ;;
            *) echo -e "${RED}Noto'g'ri tanlov.${NC}"; sleep 1 ;;
        esac
    done
}

# --- Main Logic ---
main_menu() {
    init
    while true; do
        print_header
        print_menu
        read -p "Tanlovingizni kiriting: " choice
        case $choice in
            1) chat_session ;;
            2) settings_menu ;;
            3) less -R "$HISTORY_FILE" ;; # Tarixni JSON formatida ko'rsatadi
            4) echo -e "${GREEN}Xayr!${NC}"; exit 0 ;;
            *) echo -e "${RED}Noto'g'ri tanlov.${NC}"; sleep 1 ;;
        esac
    done
}

main_menu
