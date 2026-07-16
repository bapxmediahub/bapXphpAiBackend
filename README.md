---
title: PHP JSON Agent Ready Backend
description: PHP/MySQL agent-ready full-stack product base for small PHP hosting with auth, admin CRUD, ecommerce, and more.
category: root
---

# PHP JSON Agent Ready Backend and Full-Stack Platform

This repository is a **PHP/MySQL agent-ready** full-stack product base for small PHP hosting (Hostinger, cPanel, etc. with `public_html`). It ships with auth, admin CRUD, ecommerce, scheduled consultant bookings, saved addresses, reviews, media library, Help-category blog guides, support assistant, mail queue, and built-in AI-agent instructions.

**All project operations go through the `bapXphp` CLI.** Never edit content files directly.

Add the project root to your PATH so `bapXphp` works from anywhere:

```bash
echo 'export PATH="$PATH:'$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)'"' >> ~/.zshrc
source ~/.zshrc
```

---

## Quick Start

```bash
bapXphp help           # Full command reference
bapXphp understand     # Project overview
bapXphp serve          # Start dev server at 127.0.0.1:6020
```

## Environment Setup

```dotenv
APP_NAME="Your App Name"
APP_URL=https://your-domain.example
BAPX_MYSQL_HOST=localhost
BAPX_MYSQL_PORT=3306
BAPX_MYSQL_DB=database_name
BAPX_MYSQL_USER=database_user
BAPX_MYSQL_PASS=database_password
```

`.env` provides the site URL and direct MySQL connection. Application secrets such as Razorpay, Google OAuth, SMTP, and the support model remain in the remote MySQL `secrets` collection and are managed through **Admin → Integrations**. The fallback database API defaults to `APP_URL/remotedb` and can be overridden with `BAPX_REMOTE_DB_URL`. The `/remoteDB` endpoint can be password-protected by setting `REMOTE_DB_PASSWORD` in `.env` or via **Admin → Integrations → Remote DB Password**.

Use **Admin → Settings** for store and site behavior. Use **Admin → Integrations** for credentials and third-party connection status.

---

## All bapXphp Commands

### Orientation
| Command | Description |
|---------|-------------|
| `bapXphp help` | Full command reference |
| `bapXphp understand` | Project overview: schema, commits, issues, PRs, skills, AGENTS.md |
| `bapXphp context` | Quick session: branch, pending changes, last test result |

### Development
| Command | Description |
|---------|-------------|
| `bapXphp test` | Run PHP test suite |
| `bapXphp lint [path]` | PHP syntax check (`php -l`) |
| `bapXphp ci` | Non-mutating validation: lint → test → both map validators → smoke |
| `bapXphp check` | Shorthand for `bapXphp ci` |
| `bapXphp update` | Regenerate and validate both generated maps |
| `bapXphp serve` | Start dev server on `127.0.0.1:6020` |
| `bapXphp smoke` | Run local smoke tests against dev server |

### Schema (collections.php)
| Command | Description |
|---------|-------------|
| `bapXphp schema list` | List all collections with field counts |
| `bapXphp schema show <col>` | Show full schema: fields, types, constraints |
| `bapXphp add <field>:<type> [opts] under <collection>` | Add a field to a collection |
| `bapXphp remove <field> under <collection>` | Remove a field from a collection |

### Read / Write Content (CRUD — use these, never edit files directly)

| Command | Description |
|---------|-------------|
| `bapXphp read blog` | List all blog posts |
| `bapXphp read blog <slug>` | Read a blog post with YAML frontmatter |
| `bapXphp write blog` | Create a new blog post (interactive — auto-slug, auto-timestamp, auto-URL) |
| `bapXphp write blog <slug>` | Edit an existing blog post |
| `bapXphp read product` | List all products |
| `bapXphp read product <slug>` | Read a product with all fields |
| `bapXphp write product` | Create a new product (interactive) |
| `bapXphp write product <slug>` | Edit an existing product |

### Project Map
| Command | Description |
|---------|-------------|
| `bapXphp map` | View the generated project map |
| `bapXphp map:gen` | Regenerate `docs/systematic-map.mmd` from source |
| `bapXphp map:val` | Validate the project map is up to date |
| `bapXphp docsmap` | Regenerate `docs/map.mmd` (content mindmap: skills, docs, blog, agents) |
| `bapXphp codemap` | Regenerate `map.mmd` (root, code dependency graph with edges + gaps) |

### Skills & Routes
| Command | Description |
|---------|-------------|
| `bapXphp skills` | List available agent skills with descriptions |
| `bapXphp route:list` | List all registered routes with controllers |

### Tool Management
| Command | Description |
|---------|-------------|
| `bapXphp tool list` | List all PHP tools in `cli/` |
| `bapXphp tool add <file>` | Create a new PHP tool with nano editor |

### Browser Agent (headless browser automation)
| Command | Description |
|---------|-------------|
| `bapXphp browser-agent open <url>` | Fetch page, render YAML snapshot |
| `bapXphp browser-agent click <selector>` | Click element, follow navigation |
| `bapXphp browser-agent fill <selector> <value>` | Fill form input |
| `bapXphp browser-agent submit [selector]` | Submit form |
| `bapXphp browser-agent snapshot [--max-e=N] [--max-d=N] [--ref=eN]` | YAML page snapshot with depth/element/ref filters |
| `bapXphp browser-agent smoke <url>` | Quick health check (GET-based) |
| `bapXphp browser-agent screenshot [file]` | YAML snapshot output (not pixel) |
| `bapXphp browser-agent config set <key> <value>` | Runtime config: `request_delay_ms`, `timeout`, `tracing` |
| `bapXphp browser-agent log` | Audit trail of all requests |
| `bapXphp browser-agent cookies` | Show cookie jar contents |
| `bapXphp browser-agent count <tag>` | Count DOM tags |
| `bapXphp browser-agent close` | Clean session/cookies/logs |
| `bapXphp browser-agent --pw <command>` | Forward to Playwright (local dev only) |

### Git
| Command | Description |
|---------|-------------|
| `bapXphp status` | Git status + recent commits |
| `bapXphp logs [--limit N]` | Read recent live audit events from remote MySQL |
| `bapXphp logs --local` | Tail ignored local development logs explicitly |
| `bapXphp artifacts:clean --dry-run` | Audit tracked runtime and Playwright artifacts before untracking |

### Repository Operations
| Command | Description |
|---------|-------------|
| `git status --short --branch` | Inspect the checkout |
| `git switch -c fix/issue-N-description` | Create an isolated branch |
| `git add` / `git commit` / `git push` | Record and publish source changes |
| `bapXphp hooks install` | Install repository-owned Git enforcement hooks |
| `bapXphp tui` | Open the interactive project terminal UI |

Hostinger needs plain Git, not GitHub CLI. GitHub issues, handoffs, PR comments,
review routing, and merge coordination run in GitHub Actions or the GitHub web
interface.

### Mail & Images
| Command | Description |
|---------|-------------|
| `bapXphp mail:process` | Process pending mail queue |
| `bapXphp images:optimize` | Convert/resize images to WebP |

### Database (MySQL direct — no SSH needed)
| Command | Description |
|---------|-------------|
| `bapXphp db tables` | List all MySQL tables |
| `bapXphp db describe <table>` | Describe MySQL table columns |
| `bapXphp db list` | List all schema collections |
| `bapXphp db show <collection>` | Show collection fields and types |
| `bapXphp db query <collection> [--where 'f=v'] [--limit N] [--id id] [--owner email]` | Query records from MySQL |
| `bapXphp db find <collection> <id>` | Find a record by ID |
| `bapXphp db status` | Verify direct MySQL, remote fallback, and integration readiness |
| `bapXphp db raw <sql>` | Execute raw SQL |
| `bapXphp db init` | Create MySQL tables from `collections.php` schema |
| `bapXphp db sync` | Create MySQL tables from schema (seed data lives in MySQL) |

### Blog & Docs
| Command | Description |
|---------|-------------|
| `bapXphp docsmap` | Regenerate `docs/map.mmd` from docs, skills, and agent files |
| `bapXphp codemap` | Regenerate `map.mmd` code dependency graph from source |
| `bapXphp update`  | Regenerate all maps + OKF bundle |
| `bapXphp bloggen` | Regenerate blog cache from GitHub markdown sources |

---

## Validation Workflow

Run the smallest useful validation for the change:

```bash
bapXphp lint path/to/changed.php    # syntax check
bapXphp update                       # regenerate maps
bapXphp ci                           # full non-mutating validation
```

For UI changes, also use browser verification on `127.0.0.1:6020`. For doc/AGENTS/skill changes, `bapXphp update` is sufficient.

## Model Routing

Model selection is configured in Admin → Integrations and stored in MySQL `secrets`:

| Role | Recommended Model |
|------|------------------|
| CTO/Orchestrator | Pro (Gemini 2.5 Pro / Claude Opus) |
| Worker | Fast (Gemini 2.5 Flash / Claude Sonnet) |
| Reviewer | Cheap (Gemini 2.5 Flash) |

Never hardcode model names or API keys. Read from `SecretService` at runtime.

---

## What This App Includes

- **Product catalog** with 7 products across 3 categories (sacred-emblems, jewelry, pooja-idols)
- **Consultant directory** with client-provided profiles, admin-created accounts, scheduled booking requests, and booking history
- **Saved addresses** with a default signup address and reusable checkout selection
- **Support assistant** AI agent that answers product, order, address, and booking questions
- **Reviews** for products and astrology sessions
- **Temple listing** with addresses, timings, maps
- **Contact/consultation request** forms
- **Customer account** with order history and consultation booking status
- **Owner admin** for products, categories, coupons, astrologers, orders, temples, settings, integrations, backups, audit logs, blog, media library, email inbox/outbox, support tickets, contact submissions, project map
- **Blog and Help guides** with YAML frontmatter posts in `content/blog/posts/`
- **Mail queue** for payment confirmations, shipment notifications, review requests
- **Media library** with upload, context tagging, metadata in `content/blog/posts/` and `storage/media.yaml`

## Architecture

- **Frontend**: PHP templates in `views/` themed to `Design.md` tokens
- **Backend**: PHP controllers and services in `app/` using `DatabaseService` (MySQL PDO wrapper)
- **Database**: MySQL is the primary runtime store. `config/database.php` holds connection config
- **Schema**: `storage/schema/collections.php` is the canonical schema contract
- **Blog**: YAML frontmatter `.md` files in `content/blog/posts/`
- **Media**: metadata in `storage/media.yaml` (not MySQL)
- **Secrets**: stored in MySQL `secrets` table, edited through **Admin → Integrations**
- **No SPA**: unknown routes return the PHP 404 page

## Documentation

- [AGENTS.md](AGENTS.md) — binding DOX workflow for agentic development
- [docs/README.md](docs/README.md) — full documentation index
- [docs/deployment-hostinger.md](docs/deployment-hostinger.md) — Hostinger Git auto-deploy
- [docs/systematic-map.mmd](docs/systematic-map.mmd) — generated route/controller/service map
- [docs/map.mmd](docs/map.mmd) — content/documentation mindmap
- [map.mmd](map.mmd) — code dependency graph with all edges + gaps

## Stack

- PHP 8.x with PDO MySQL
- MySQL (runtime data store)
- PHP-rendered templates
- Razorpay payment integration
- Google OAuth scaffolding
- No build step, no Node, no Postgres, no Redis
