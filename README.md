# Sri Panchami Spiritual Platform

Custom modular PHP monorepo for devotional ecommerce and astrologer appointments.

## Local run
```bash
php -S 127.0.0.1:8000
php tests/run.php
php tools/generate-project-map.php
php tools/validate-project-map.php
```

## Structure
- `app/` routing/controllers/services
- `views/` public, account, and admin pages
- `storage/data/` JSON collections
- `tools/` generated project map tooling
- `docs/` architecture, deployment, and generated maps
