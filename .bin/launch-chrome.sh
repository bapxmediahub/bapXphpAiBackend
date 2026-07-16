#!/bin/bash
# Launch headless Chrome from storage/.bin/chrome-linux/chrome
# Usage: .bin/launch-chrome.sh [--port=9222] [--user-data-dir=/path] [--headless=false]

CHROME_BIN="$(dirname "$0")/chrome-linux/chrome"
PORT=9222
USER_DATA_DIR=""
HEADLESS=true
NO_SANDBOX=true

for arg in "$@"; do
    case $arg in
        --port=*) PORT="${arg#*=}" ;;
        --user-data-dir=*) USER_DATA_DIR="${arg#*=}" ;;
        --headless=false) HEADLESS=false ;;
        --no-sandbox=false) NO_SANDBOX=false ;;
    esac
done

if [[ ! -x "$CHROME_BIN" ]]; then
    echo "Chrome not found at $CHROME_BIN" >&2
    exit 1
fi

CMD=("$CHROME_BIN")
if [[ "$HEADLESS" = true ]]; then CMD+=("--headless=new"); fi
if [[ "$NO_SANDBOX" = true ]]; then CMD+=("--no-sandbox"); fi
CMD+=("--disable-gpu" "--disable-dev-shm-usage" "--remote-debugging-port=$PORT" "--remote-debugging-address=0.0.0.0")
if [[ -n "$USER_DATA_DIR" ]]; then CMD+=("--user-data-dir=$USER_DATA_DIR"); else CMD+=("--user-data-dir=$(mktemp -d)"); fi

echo "Starting Chrome on port $PORT..."
echo "CDP endpoint: ws://localhost:$PORT/devtools/browser/"
exec "${CMD[@]}"
