# browser-agent - Pure PHP HTTP Crawler

## Capabilities
- HTTP mode: cURL + DOMDocument (shared hosting compatible)
- Commands: open, click, fill, submit, snapshot, search, links, forms, smoke, captcha
- Session persistence: cookies, history, form data
- Config: delay, timeout, UA rotation, CDP endpoint
- CDP mode: remote Chrome via DevTools Protocol

## Usage
browser-agent open https://example.com
browser-agent search query --engine=ddg
browser-agent click a.product
browser-agent fill input[name=email] test@example.com
browser-agent submit
browser-agent snapshot --output=page.yml
browser-agent cdp Target.getTargets

## Config
browser-agent config set cdp_ws ws://host:9222/devtools/browser/xxx
browser-agent config set request_delay_ms 1000

## Output
- YAML snapshots (AI-readable DOM structure)
- Raw HTML
- Link/form extraction
- Captcha detection
