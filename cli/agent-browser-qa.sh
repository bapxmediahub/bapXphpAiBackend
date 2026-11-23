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
# Honor a caller-selected Chromium path, then discover supported local installs.
# macOS ships GUI Chrome without a `google-chrome` shell command on PATH.
if [[ -z "${CHROMIUM_PATH:-}" ]]; then
  CHROMIUM_PATH="$(command -v google-chrome || command -v google-chrome-stable || command -v chromium || command -v chromium-browser || true)"
fi
if [[ -z "${CHROMIUM_PATH:-}" && "$(uname -s)" == "Darwin" ]]; then
  for candidate in \
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
    "$HOME/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
    "/Applications/Chromium.app/Contents/MacOS/Chromium"; do
    if [[ -x "$candidate" ]]; then CHROMIUM_PATH="$candidate"; break; fi
  done
fi
if [[ -z "${CHROMIUM_PATH:-}" || ! -x "$CHROMIUM_PATH" ]]; then
  echo "Chromium or Chrome is required (set CHROMIUM_PATH to its executable)" >&2
  exit 2
fi
export CHROMIUM_PATH
node --test tests/chat-ui-playwright.cjs
