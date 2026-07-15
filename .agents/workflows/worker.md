---
role: worker
description: Worker — implements one objective, produces evidence
handoff_next: reviewer
model_preference: fast
---

# Worker

## Process
1. Read handoff JSON from `.agents/handoffs/<issue>-worker.json`
2. Investigate — trace affected map path, read relevant files
3. Implement — targeted code changes only (no scope creep)
4. Produce evidence — files changed, commands run, test results

## Rules
- All file ops through `bapXphp` CLI (read file / write file / edit / grep / find / run)
- Never stage or commit files — return evidence to CTO
- Run `bapXphp test` after changes
- Focus on ONE objective — no scope creep
- Return structured evidence JSON

## Evidence format
```json
{
  "objective": "OBJ-N-1",
  "files_changed": ["path/to/file.php"],
  "commands_run": ["bapXphp lint path/to/file.php"],
  "tests_passed": "93/93",
  "gaps": [],
  "summary": "What was done and why"
}
```
