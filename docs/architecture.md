# Architecture

## Frontend (React + JavaScript)
- React 18 loaded via CDN (no build step required)
- Vanilla JavaScript for state management and routing
- Single Page Application (SPA) architecture
- All UI components decoupled from backend
- Compatible with Hostinger shared hosting (static files)

## Backend (PHP)
- PHP serves JSON API endpoints only (`/api/*`)
- No HTML rendering in PHP controllers
- Service-oriented architecture
- Route registry in `app/Services/ProjectMapService.php`

## Data Persistence
- JSON file-based storage in `storage/data/`
- No SQL/MySQL database required
- Atomic writes with lock files for data integrity

## Data Flow
1. React SPA loads from `index.php` (spa.php layout)
2. React components fetch data from `/api/*` endpoints
3. PHP controllers return JSON responses
4. React updates UI based on API responses

## File Structure
```
├── api/                    # PHP API entry point
├── app/
│   ├── Controllers/        # PHP controllers (JSON responses)
│   ├── Services/           # Business logic
│   └── Router.php          # Route handling
├── assets/
│   ├── css/                # Stylesheets
│   └── js/
│       ├── app.js          # Core app (API, Store, Router)
│       ├── components/     # React components (Layout)
│       ├── pages/          # Page components
│       └── main.js         # App entry point
├── storage/data/           # JSON data files
└── views/
    └── layouts/
        ├── spa.php         # React SPA layout
        └── admin.php       # Admin PHP layout (internal)
```
