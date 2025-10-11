# 🤖 Gemini AI Agent - Termux Edition

A powerful AI agent for Termux terminal powered by Google Gemini API and LangChain framework.

[O'zbekcha qo'llanma / Uzbek Guide](README_uz.md) | [Quick Start](QUICKSTART_uz.md)

## ✨ Features

- 📝 **Code Writing & Editing** - AI agent helps you write and modify code
- 📁 **File Operations** - Create, read, edit, and delete files
- 🐍 **Python Code Execution** - Run Python code directly
- 💻 **Terminal Commands** - Execute shell commands through the agent
- 🧠 **Conversation Memory** - Agent remembers previous conversations
- 🔧 **Multi-functional Tools** - 8 different tools for various tasks

## 📦 Installation

### 1. Update Termux

```bash
pkg update && pkg upgrade -y
```

### 2. Install Python and required packages

```bash
pkg install -y python python-pip git
```

### 3. Clone or download the project

```bash
# If it's a Git repository:
git clone <repository_url>
cd <directory>

# Or manually copy the files
```

### 4. Install Python libraries

```bash
pip install -r requirements.txt
```

Or use the automated installer:

```bash
bash install_agent.sh
```

### 5. Get Gemini API Key

1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Click "Create API Key"
3. Copy the API key

## 🚀 Usage

### Start the Agent

```bash
python ai_agent.py
```

On first run, you'll be asked to enter your Gemini API key.

### Commands

Once the agent is running, you can use these commands:

- `/help` - Show help information
- `/clear` - Clear the screen
- `/history` - View conversation history
- `/reset` - Reset the conversation
- `/exit` - Exit the agent

### Examples

#### 1. Write Code

```
You > Write a function to calculate Fibonacci numbers in Python
```

#### 2. Create a File

```
You > Create a file called test.py with a "Hello World" program
```

#### 3. List Directory

```
You > Show me the contents of the current directory
```

#### 4. Execute Code

```
You > Execute the code: print(2 + 2)
```

#### 5. Run Shell Command

```
You > Run the command: ls -la
```

## 🔧 Agent Capabilities

The agent has access to these tools:

1. **read_file** - Read file contents
2. **write_file** - Create new file or edit existing
3. **append_file** - Append to file
4. **list_directory** - List directory contents
5. **delete_file** - Delete a file
6. **create_directory** - Create new directory
7. **execute_python** - Execute Python code
8. **execute_shell** - Run shell commands

## 📁 File Structure

```
.
├── ai_agent.py           # Main agent program
├── requirements.txt      # Python dependencies
├── README.md            # English guide (this file)
├── README_uz.md         # Uzbek guide
├── QUICKSTART_uz.md     # Quick start guide (Uzbek)
├── install_agent.sh     # Installation script
├── gemini-ai.sh         # Simple Gemini chat (bash)
└── termux.sh            # Termux installer script
```

## ⚙️ Configuration

The agent stores settings in:

```
~/.config/gemini_agent/
├── config.json                  # Main configuration
└── conversation_history.json    # Chat history
```

To change API key:

```bash
rm ~/.config/gemini_agent/config.json
python ai_agent.py
```

## 🛡️ Security

- Python code execution runs in a restricted namespace
- Dangerous shell commands (e.g., `rm -rf /`) are blocked
- API key is stored securely

## 🐛 Troubleshooting

### ModuleNotFoundError

```bash
pip install -r requirements.txt
```

### API Error

- Check your API key
- Check your internet connection
- Verify your API key is active at [Google AI Studio](https://makersuite.google.com/)

### Permission Denied

```bash
chmod +x ai_agent.py
```

## 📚 Resources

- [LangChain Documentation](https://python.langchain.com/)
- [Google Gemini API](https://ai.google.dev/)
- [Termux Wiki](https://wiki.termux.com/)

## 💡 Tips

1. **Be specific** - The more specific your request, the better the agent's response
2. **Work step-by-step** - Break complex tasks into smaller parts
3. **Use conversation history** - The agent remembers previous conversations
4. **Learn the tools** - Use `/help` command to explore all capabilities

## 📝 License

This project is open source and created for educational purposes.

## 🤝 Contributing

Feedback and suggestions are always welcome!

---

**Author:** AI Assistant  
**Version:** 1.0  
**Date:** 2025
