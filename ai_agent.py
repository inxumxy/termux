#!/usr/bin/env python3
"""
Gemini AI Agent - Termux uchun kuchli AI agent
LangChain va Google Gemini API bilan ishlaydi
"""

import os
import sys
import json
from pathlib import Path
from typing import List, Dict, Any

try:
    from langchain_google_genai import ChatGoogleGenerativeAI
    from langchain.agents import AgentExecutor, create_react_agent
    from langchain.tools import Tool, StructuredTool
    from langchain import hub
    from langchain.memory import ConversationBufferMemory
    from langchain.schema import SystemMessage
    from langchain_core.prompts import PromptTemplate
except ImportError:
    print("❌ Kerakli kutubxonalar o'rnatilmagan!")
    print("📦 Iltimos, avval o'rnatish buyrug'ini bajaring:")
    print("   pip install -r requirements.txt")
    sys.exit(1)

# Konfiguratsiya
CONFIG_DIR = Path.home() / ".config" / "gemini_agent"
CONFIG_FILE = CONFIG_DIR / "config.json"
HISTORY_FILE = CONFIG_DIR / "conversation_history.json"


class Colors:
    """Terminal ranglari"""
    RED = '\033[1;31m'
    GREEN = '\033[1;32m'
    YELLOW = '\033[1;33m'
    BLUE = '\033[1;34m'
    MAGENTA = '\033[1;35m'
    CYAN = '\033[1;36m'
    WHITE = '\033[1;37m'
    GRAY = '\033[0;37m'
    NC = '\033[0m'  # No Color
    BOLD = '\033[1m'


class FileOperations:
    """Fayl operatsiyalari uchun toollar"""
    
    @staticmethod
    def read_file(file_path: str) -> str:
        """Faylni o'qish"""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
            return f"✅ Fayl muvaffaqiyatli o'qildi:\n{content}"
        except FileNotFoundError:
            return f"❌ Xato: '{file_path}' fayli topilmadi"
        except Exception as e:
            return f"❌ Xato: {str(e)}"
    
    @staticmethod
    def write_file(file_path: str, content: str) -> str:
        """Faylga yozish yoki yangi fayl yaratish"""
        try:
            # Parent direktoriya mavjud emasligini tekshirish
            Path(file_path).parent.mkdir(parents=True, exist_ok=True)
            
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            return f"✅ Fayl muvaffaqiyatli yaratildi/yangilandi: {file_path}"
        except Exception as e:
            return f"❌ Xato: {str(e)}"
    
    @staticmethod
    def append_file(file_path: str, content: str) -> str:
        """Faylga qo'shimcha qilish"""
        try:
            with open(file_path, 'a', encoding='utf-8') as f:
                f.write(content)
            return f"✅ Faylga qo'shildi: {file_path}"
        except Exception as e:
            return f"❌ Xato: {str(e)}"
    
    @staticmethod
    def list_directory(directory_path: str = ".") -> str:
        """Direktoriya tarkibini ko'rish"""
        try:
            path = Path(directory_path)
            if not path.exists():
                return f"❌ Direktoriya topilmadi: {directory_path}"
            
            items = []
            for item in sorted(path.iterdir()):
                prefix = "📁" if item.is_dir() else "📄"
                items.append(f"{prefix} {item.name}")
            
            return f"📂 Direktoriya: {directory_path}\n" + "\n".join(items)
        except Exception as e:
            return f"❌ Xato: {str(e)}"
    
    @staticmethod
    def delete_file(file_path: str) -> str:
        """Faylni o'chirish"""
        try:
            Path(file_path).unlink()
            return f"✅ Fayl o'chirildi: {file_path}"
        except FileNotFoundError:
            return f"❌ Fayl topilmadi: {file_path}"
        except Exception as e:
            return f"❌ Xato: {str(e)}"
    
    @staticmethod
    def create_directory(directory_path: str) -> str:
        """Yangi direktoriya yaratish"""
        try:
            Path(directory_path).mkdir(parents=True, exist_ok=True)
            return f"✅ Direktoriya yaratildi: {directory_path}"
        except Exception as e:
            return f"❌ Xato: {str(e)}"


class CodeOperations:
    """Kod operatsiyalari"""
    
    @staticmethod
    def execute_python_code(code: str) -> str:
        """Python kodini bajarish (xavfsizlik bilan)"""
        try:
            # Cheklangan namespace
            safe_namespace = {
                '__builtins__': {
                    'print': print,
                    'len': len,
                    'str': str,
                    'int': int,
                    'float': float,
                    'list': list,
                    'dict': dict,
                    'range': range,
                    'sum': sum,
                    'max': max,
                    'min': min,
                }
            }
            
            # Koddan stdout ni olish
            from io import StringIO
            old_stdout = sys.stdout
            sys.stdout = StringIO()
            
            exec(code, safe_namespace)
            
            output = sys.stdout.getvalue()
            sys.stdout = old_stdout
            
            return f"✅ Kod bajarildi:\n{output}"
        except Exception as e:
            sys.stdout = old_stdout
            return f"❌ Kod bajarilishida xato: {str(e)}"
    
    @staticmethod
    def execute_shell_command(command: str) -> str:
        """Shell buyruqni bajarish"""
        import subprocess
        try:
            # Xavfli buyruqlarni bloklash
            dangerous_commands = ['rm -rf /', 'dd', 'mkfs', 'format']
            if any(cmd in command for cmd in dangerous_commands):
                return "❌ Xavfli buyruq! Bajarilmaydi."
            
            result = subprocess.run(
                command,
                shell=True,
                capture_output=True,
                text=True,
                timeout=30
            )
            
            output = result.stdout
            if result.stderr:
                output += f"\n⚠️ Xatoliklar:\n{result.stderr}"
            
            return f"✅ Buyruq bajarildi:\n{output}"
        except subprocess.TimeoutExpired:
            return "❌ Buyruq 30 soniyada bajarilmadi (timeout)"
        except Exception as e:
            return f"❌ Xato: {str(e)}"


class GeminiAgent:
    """Gemini AI Agent"""
    
    def __init__(self, api_key: str, model: str = "gemini-pro"):
        self.api_key = api_key
        self.model = model
        self.llm = None
        self.agent = None
        self.memory = None
        self._setup()
    
    def _setup(self):
        """Agent sozlash"""
        # LLM yaratish
        self.llm = ChatGoogleGenerativeAI(
            model=self.model,
            google_api_key=self.api_key,
            temperature=0.7,
            convert_system_message_to_human=True
        )
        
        # Memory
        self.memory = ConversationBufferMemory(
            memory_key="chat_history",
            return_messages=True
        )
        
        # Tools yaratish
        tools = self._create_tools()
        
        # Agent prompt
        prompt = PromptTemplate.from_template("""Siz Termux terminalida ishlaydigan kuchli AI agent assistentsiz.
        
Sizning vazifalaringiz:
- Foydalanuvchiga kod yozishda yordam berish
- Fayllar bilan ishlash (yaratish, o'qish, tahrirlash)
- Kod bajarish va test qilish
- Terminal buyruqlarini bajarish
- Muammolarni hal qilish

Siz quyidagi toollardan foydalanishingiz mumkin:

{tools}

Quyidagi formatdan foydalaning:

Savol: foydalanuvchi savoli
Fikr: nimani qilish kerakligi haqida o'ylash
Harakat: bajariladigan tool [{tool_names}]
Harakat kirishi: tool uchun kirish
Kuzatuv: tool natijasi
... (bu Fikr/Harakat/Harakat kirishi/Kuzatuv takrorlanishi mumkin)
Fikr: Endi men yakuniy javobni bilaman
Yakuniy javob: foydalanuvchiga yakuniy javob

Boshlang!

Oldingi suhbatlar:
{chat_history}

Savol: {input}
{agent_scratchpad}""")
        
        # Agent yaratish
        self.agent = create_react_agent(
            llm=self.llm,
            tools=tools,
            prompt=prompt
        )
        
        self.agent_executor = AgentExecutor(
            agent=self.agent,
            tools=tools,
            memory=self.memory,
            verbose=True,
            handle_parsing_errors=True,
            max_iterations=5
        )
    
    def _create_tools(self) -> List[Tool]:
        """Barcha toollarni yaratish"""
        file_ops = FileOperations()
        code_ops = CodeOperations()
        
        tools = [
            StructuredTool.from_function(
                func=file_ops.read_file,
                name="read_file",
                description="Faylni o'qish. Kirish: fayl yo'li (masalan: 'test.py')"
            ),
            StructuredTool.from_function(
                func=file_ops.write_file,
                name="write_file",
                description="Yangi fayl yaratish yoki mavjud faylni tahrirlash. Kirish: fayl yo'li va mazmun"
            ),
            StructuredTool.from_function(
                func=file_ops.append_file,
                name="append_file",
                description="Faylga qo'shimcha qilish. Kirish: fayl yo'li va mazmun"
            ),
            StructuredTool.from_function(
                func=file_ops.list_directory,
                name="list_directory",
                description="Direktoriya tarkibini ko'rish. Kirish: direktoriya yo'li (standart: '.')"
            ),
            StructuredTool.from_function(
                func=file_ops.delete_file,
                name="delete_file",
                description="Faylni o'chirish. Kirish: fayl yo'li"
            ),
            StructuredTool.from_function(
                func=file_ops.create_directory,
                name="create_directory",
                description="Yangi direktoriya yaratish. Kirish: direktoriya yo'li"
            ),
            StructuredTool.from_function(
                func=code_ops.execute_python_code,
                name="execute_python",
                description="Python kodini bajarish. Kirish: Python kodi"
            ),
            StructuredTool.from_function(
                func=code_ops.execute_shell_command,
                name="execute_shell",
                description="Terminal buyruqni bajarish. Kirish: buyruq"
            ),
        ]
        
        return tools
    
    def chat(self, user_input: str) -> str:
        """Foydalanuvchi bilan suhbat"""
        try:
            response = self.agent_executor.invoke({"input": user_input})
            return response['output']
        except Exception as e:
            return f"❌ Xato: {str(e)}"


class ConfigManager:
    """Konfiguratsiyani boshqarish"""
    
    @staticmethod
    def init_config():
        """Konfiguratsiya direktoriyasini yaratish"""
        CONFIG_DIR.mkdir(parents=True, exist_ok=True)
        if not HISTORY_FILE.exists():
            HISTORY_FILE.write_text("[]")
    
    @staticmethod
    def load_config() -> Dict[str, Any]:
        """Konfiguratsiyani yuklash"""
        if CONFIG_FILE.exists():
            return json.loads(CONFIG_FILE.read_text())
        return {}
    
    @staticmethod
    def save_config(config: Dict[str, Any]):
        """Konfiguratsiyani saqlash"""
        CONFIG_FILE.write_text(json.dumps(config, indent=2))
    
    @staticmethod
    def get_api_key() -> str:
        """API kalitni olish"""
        config = ConfigManager.load_config()
        
        if 'api_key' in config:
            return config['api_key']
        
        # Agar config da bo'lmasa, so'rash
        print(f"{Colors.YELLOW}Gemini API kalitingizni kiriting:{Colors.NC}")
        print(f"{Colors.GRAY}(Google AI Studio'dan oling: https://makersuite.google.com/app/apikey){Colors.NC}")
        api_key = input("API Key: ").strip()
        
        config['api_key'] = api_key
        ConfigManager.save_config(config)
        
        return api_key


def print_header():
    """Sarlavha chop etish"""
    print(f"\n{Colors.BLUE}╭────────────────────────────────────────────────╮{Colors.NC}")
    print(f"{Colors.BLUE}│{Colors.WHITE}  🤖 Gemini AI Agent - Termux Edition 🚀      {Colors.BLUE}│{Colors.NC}")
    print(f"{Colors.BLUE}╰────────────────────────────────────────────────╯{Colors.NC}\n")


def print_help():
    """Yordam ma'lumotlari"""
    print(f"{Colors.CYAN}Mavjud buyruqlar:{Colors.NC}")
    print(f"  {Colors.GREEN}/help{Colors.NC}     - Yordam")
    print(f"  {Colors.GREEN}/clear{Colors.NC}    - Ekranni tozalash")
    print(f"  {Colors.GREEN}/history{Colors.NC}  - Suhbat tarixini ko'rish")
    print(f"  {Colors.GREEN}/reset{Colors.NC}    - Suhbatni qayta boshlash")
    print(f"  {Colors.GREEN}/exit{Colors.NC}     - Chiqish\n")
    
    print(f"{Colors.CYAN}Agent qilishi mumkin bo'lgan ishlar:{Colors.NC}")
    print(f"  • Kod yozish va tahrirlash")
    print(f"  • Fayllar bilan ishlash (yaratish, o'qish, o'chirish)")
    print(f"  • Python kodini bajarish")
    print(f"  • Terminal buyruqlarini bajarish")
    print(f"  • Muammolarni tahlil qilish va hal qilish\n")


def main():
    """Asosiy funksiya"""
    os.system('clear')
    print_header()
    
    # Konfiguratsiyani sozlash
    ConfigManager.init_config()
    api_key = ConfigManager.get_api_key()
    
    if not api_key:
        print(f"{Colors.RED}❌ API kalit kiritilmadi!{Colors.NC}")
        sys.exit(1)
    
    # Agent yaratish
    print(f"{Colors.YELLOW}⏳ Agent ishga tushirilmoqda...{Colors.NC}")
    try:
        agent = GeminiAgent(api_key)
        print(f"{Colors.GREEN}✅ Agent tayyor!{Colors.NC}\n")
    except Exception as e:
        print(f"{Colors.RED}❌ Agent yaratishda xato: {e}{Colors.NC}")
        sys.exit(1)
    
    print_help()
    
    # Asosiy suhbat sikli
    while True:
        try:
            user_input = input(f"{Colors.YELLOW}Siz >{Colors.NC} ").strip()
            
            if not user_input:
                continue
            
            # Buyruqlarni tekshirish
            if user_input.lower() in ['/exit', '/quit', 'exit', 'quit']:
                print(f"{Colors.GREEN}👋 Xayr!{Colors.NC}")
                break
            
            elif user_input.lower() == '/help':
                print_help()
                continue
            
            elif user_input.lower() == '/clear':
                os.system('clear')
                print_header()
                continue
            
            elif user_input.lower() == '/reset':
                agent.memory.clear()
                print(f"{Colors.GREEN}✅ Suhbat qayta boshlandi{Colors.NC}\n")
                continue
            
            elif user_input.lower() == '/history':
                print(f"{Colors.CYAN}Suhbat tarixi:{Colors.NC}")
                for msg in agent.memory.chat_memory.messages:
                    print(f"  {msg}")
                print()
                continue
            
            # Agent bilan suhbat
            print(f"{Colors.GRAY}🤖 O'ylanmoqda...{Colors.NC}\n")
            response = agent.chat(user_input)
            
            print(f"\n{Colors.BLUE}╭────────────────────────────────────────────────╮{Colors.NC}")
            print(f"{Colors.BLUE}│{Colors.CYAN} Agent javobi:{Colors.NC}")
            print(f"{Colors.BLUE}╰────────────────────────────────────────────────╯{Colors.NC}")
            print(f"{Colors.WHITE}{response}{Colors.NC}\n")
            
        except KeyboardInterrupt:
            print(f"\n{Colors.YELLOW}⚠️ Chiqish uchun /exit yozing{Colors.NC}")
        except Exception as e:
            print(f"{Colors.RED}❌ Xato: {e}{Colors.NC}\n")


if __name__ == "__main__":
    main()
