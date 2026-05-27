# Hostinger Deployment

Deploy the GitHub `main` branch into `/public_html` using Hostinger Git deployment. Keep `storage/` writable, ensure `.htaccess` is enabled, and configure runtime secrets after deploy. Regenerate the project map before each release.

## Deployment Notes
- Frontend: React.js components and vanilla JS for UI interactions
- Backend: PHP handles API requests and server-side logic
- Database: JSON file-based storage (no MySQL/PostgreSQL required)
- Ensure `storage/data/` directory has write permissions (755 or 775)
