# Session Memory

Continuous JSON conversation log at `.tmp/session.json`.

## Purpose

Captures every user input prompt and CTO reply as a continuous, append-only JSON array — like a WhatsApp conversation with timestamps and attachments.

## Format

```json
{
  "session_id": "ses_abc123",
  "created_at": "2026-07-16T12:00:00Z",
  "updated_at": "2026-07-16T12:00:05Z",
  "entries": [
    {"id": 1, "role": "user",     "content": "prompt text",   "timestamp": "...", "attachments": []},
    {"id": 2, "role": "cto",      "content": "response text", "timestamp": "...", "attachments": []}
  ]
}
```

Roles: `user` = input prompt, `cto` = handler response, `question` = tool question, `answer` = user reply.

## Commands

| Command | Description |
|---|---|
| `bapXphp memory init` | Initialize `.tmp/session.json` |
| `bapXphp memory add <role> <content>` | Append entry |
| `bapXphp memory list` | Show all entries (summary) |
| `bapXphp memory search <text>` | Search entries by content |
| `bapXphp memory last [n]` | Show last n entries |
| `bapXphp memory export` | Dump full JSON |
| `bapXphp memory export-jsonl` | Dump as JSONL (one JSON per line) |
| `bapXphp memory info` | Session stats |
| `bapXphp memory clear` | Erase session memory |

## Usage

1. At session start: `bapXphp memory init`
2. Log each user prompt: `bapXphp memory add user "<exact prompt>"`
3. Log each CTO response: `bapXphp memory add cto "<response summary>"`
4. Search later: `bapXphp memory search "<keyword>"`
5. Fork/export to new session: `bapXphp memory export > context.json`

## Important

- The JSON file is append-friendly and human-readable
- Attachments array can hold `{"name": "...", "path": "..."}` objects
- Previous sessions persist — `memory init` creates a fresh file
- Use `memory search` with exact user prompt text for reliable lookup
