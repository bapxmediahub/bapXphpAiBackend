# remote-chrome - External Chrome via CDP

For JS-rendered sites on shared hosting.

## Architecture
browser-agent (PHP) -> WebSocket -> remote Chrome (VPS/Browserless) CDP

## Setup
1. VPS: apt install chromium && chromium --headless --remote-debugging-port=9222 --no-sandbox
2. Browserless: docker run -p 3000:3000 browserless/chrome
3. WS URL: curl http://host:9222/json/version

## Configure
browser-agent config set cdp_ws ws://your-vps:9222/devtools/browser/<id>

## CDP Commands
- Target.getTargets — list pages
- Target.createTarget {url:...} — new tab
- Page.navigate {url:...} — navigate
- Runtime.evaluate {expression:...} — execute JS
- Page.captureScreenshot {format:png,captureBeyondViewport:true} — screenshot
- Page.printToPDF {landscape:true} — PDF
