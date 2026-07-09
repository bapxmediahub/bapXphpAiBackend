# PHP JSON Agent Ready Backend and Full-Stack Platform

This repository is a **PHP/MySQL agent-ready** full-stack product base for small PHP hosting (Hostinger, cPanel, etc. with `public_html`). It ships with auth, admin CRUD, ecommerce, astrologer marketplace, wallet, reviews, media library, blog, support assistant, mail queue, and built-in AI-agent instructions.

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
```

**`.env` holds only `APP_NAME` and `APP_URL` — nothing else.** Never put secrets, API keys, credentials, or tokens in `.env`. All secrets are stored in the remote MySQL database and managed through **Admin → Integrations** or **Admin → Settings**.

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
| `bapXphp check` | Full validation: lint → test → map:gen → map:val → smoke |
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

### Git
| Command | Description |
|---------|-------------|
| `bapXphp status` | Git status + recent commits |
| `bapXphp logs` | Tail recent error logs from `storage/logs/` |

### GitHub
| Command | Description |
|---------|-------------|
| `bapXphp issue` | Create a GitHub issue (interactive via `gh`) |
| `bapXphp pr` | Create a GitHub PR (interactive) |
| `bapXphp merge` | Merge a GitHub PR (interactive) |

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
| `bapXphp db raw <sql>` | Execute raw SQL |
| `bapXphp db init` | Create MySQL tables from `collections.php` schema |
| `bapXphp db sync` | Create MySQL tables from schema (seed data lives in MySQL) |

### Blog & Docs
| Command | Description |
|---------|-------------|
| `bapXphp docsmap` | Regenerate `docs/KnowledgeMap.mmd` from docs, AGENTS.md, and skills |
| `bapXphp bloggen` | Regenerate blog cache from GitHub markdown sources |

### Validation (shortcut)
| Command | Description |
|---------|-------------|
| `bapXphp check` | Full chain: lint → test → map:gen → map:val → smoke |

---

## What This App Includes

- **Product catalog** with 7 products across 3 categories (sacred-emblems, jewelry, pooja-idols)
- **Astrologer marketplace** with 21 client-provided profiles, admin-created accounts, private messaging, browser audio calls, credit pricing, and session history
- **Wallet system** with Razorpay recharge, service charge/tax breakdown, credit balance
- **Support assistant** AI agent that answers product, order, wallet, and session questions
- **Reviews** for products and astrology sessions
- **Temple listing** with addresses, timings, maps
- **Contact/consultation request** forms
- **Customer account** with order history, session view, wallet
- **Owner admin** for products, categories, coupons, astrologers, orders, temples, settings, integrations, backups, audit logs, blog, media library, email inbox/outbox, support tickets, contact submissions, project map
- **Blog** with YAML frontmatter posts in `content/blog/posts/`
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

## Stack

- PHP 8.x with PDO MySQL
- MySQL (runtime data store)
- PHP-rendered templates
- Razorpay payment integration
- Google OAuth scaffolding
- No build step, no Node, no Postgres, no Redis
