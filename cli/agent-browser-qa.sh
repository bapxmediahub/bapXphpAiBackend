#!/usr/bin/env bash
# Developer/CI-only Chromium QA on the real PHP views and synthetic data.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
command -v php >/dev/null || { echo "PHP CLI is required" >&2; exit 2; }
command -v node >/dev/null || { echo "Node.js is required for browser QA" >&2; exit 2; }
node -e "require('playwright')" >/dev/null 2>&1 || {
  echo "Playwright missing. Use the pinned GitHub Actions job or install playwright@1.62.1." >&2
  exit 2
}
CHROMIUM_PATH="$(command -v google-chrome || command -v chromium || true)"
export CHROMIUM_PATH
if [[ -z "$CHROMIUM_PATH" ]]; then echo "Chromium or Chrome is required" >&2; exit 2; fi
node --test tests/chat-ui-playwright.cjs
