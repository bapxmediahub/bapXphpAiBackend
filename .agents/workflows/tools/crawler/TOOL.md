# crawler - Recursive Site Crawler

Built on browser-agent HTTP mode for shared hosting.

## Features
- BFS/DFS with depth limit
- robots.txt compliance
- Politeness delay (configurable)
- Duplicate URL detection
- Content extraction to Markdown
- Sitemap generation

## Usage (planned)
browser-agent crawl https://example.com --depth=3 --delay=1000 --output=md

## Integration
- Uses HTTP mode: open, links, forms, snapshot
- Extracts main content via readability logic
- Converts HTML to clean Markdown
- Stores session per URL in .agents/temp/crawl/
