#!/usr/bin/env bash
set -euo pipefail

# Stream responses from Gemini's streamGenerateContent API with Google Search tool.
# Usage:
#   GOOGLE_API_KEY=your_key ./gemini_stream_search.sh "your prompt here"
# or set GOOGLE_API_KEY in a .env file in this directory.

# --- Config ---
MODEL_ID="gemini-flash-latest"
API_METHOD="streamGenerateContent"
API_BASE="https://generativelanguage.googleapis.com/v1beta/models"
MAX_OUTPUT_TOKENS=8192
TEMPERATURE=0.7
ENABLE_GOOGLE_SEARCH_TOOL=1  # set to 0 to disable

# Load .env if present (for GOOGLE_API_KEY)
if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

# Accept either GOOGLE_API_KEY or GEMINI_API_KEY env var
API_KEY="${GOOGLE_API_KEY:-${GEMINI_API_KEY:-}}"

if [[ -z "${API_KEY}" ]]; then
  echo "Error: GOOGLE_API_KEY env var is required (or GEMINI_API_KEY)." >&2
  echo "Tip: copy .env.example to .env and set GOOGLE_API_KEY." >&2
  exit 1
fi

# Input prompt: from arg or stdin
if [[ $# -gt 0 ]]; then
  USER_PROMPT="$*"
else
  if [ -t 0 ]; then
    echo "Enter your prompt, then Ctrl-D:" >&2
  fi
  USER_PROMPT=$(cat)
fi

if [[ -z "${USER_PROMPT// /}" ]]; then
  echo "Error: empty prompt." >&2
  exit 1
fi

# Build payload JSON with jq to avoid syntax errors
TOOLS_JSON='[]'
if [[ "${ENABLE_GOOGLE_SEARCH_TOOL}" == "1" ]]; then
  TOOLS_JSON='[{"googleSearch":{}}]'
fi

PAYLOAD=$(jq -n --arg prompt "$USER_PROMPT" \
  --argjson temperature "$TEMPERATURE" \
  --argjson maxTokens "$MAX_OUTPUT_TOKENS" \
  --argjson tools "$TOOLS_JSON" '
  {
    contents: [
      { role: "user", parts: [ { text: $prompt } ] }
    ],
    generationConfig: {
      temperature: $temperature,
      maxOutputTokens: $maxTokens
    },
    tools: $tools
  }
')

REQUEST_URL="${API_BASE}/${MODEL_ID}:${API_METHOD}?key=${API_KEY}"

# Stream response; each line is a complete JSON object
# Print incremental text chunks as they arrive
curl -sN -X POST \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD" \
  "$REQUEST_URL" | \
  while IFS= read -r line; do
    # Try to extract text chunks; ignore lines that are not candidate deltas
    text_part=$(printf '%s' "$line" | jq -r 'try .candidates[0].content.parts[0].text // empty') || true
    if [[ -n "$text_part" ]]; then
      printf "%s" "$text_part"
      continue
    fi
    # If the API returns an error object mid-stream
    err_msg=$(printf '%s' "$line" | jq -r 'try .error.message // empty') || true
    if [[ -n "$err_msg" ]]; then
      printf '\n[API error] %s\n' "$err_msg" >&2
      exit 1
    fi
  done

printf "\n"
