# Deployment Guide - Hostinger

## Deployment Steps

1. **Upload files** to `/public_html` via Git or FTP
2. **Set permissions**: `storage/data/` must be writable (755 or 775)
3. **Configure .htaccess**: Ensure mod_rewrite is enabled
4. **Test API**: Visit `/api/` to verify PHP is working

## Hostinger Requirements

- PHP 7.4 or higher
- mod_rewrite enabled
- Write permissions on `storage/` directory

## Architecture Notes

- **Frontend**: React SPA (static JS/CSS files)
- **Backend**: PHP API endpoints only
- **Database**: JSON files (no MySQL required)
- **Build Step**: None - React loads via CDN

## Directory Structure on Hostinger

```
/public_html/
├── .htaccess          # URL rewriting
├── index.php          # Main entry point
├── api/               # PHP API
├── app/               # PHP backend (not web-accessible)
├── assets/            # Static files (JS, CSS, images)
│   └── js/            # React app files
├── storage/           # JSON data (writable)
│   └── data/          # Data files
└── views/             # Layouts
```

## Troubleshooting

- **500 Error**: Check PHP version and .htaccess
- **API not working**: Ensure `api/` directory exists and PHP is running
- **Data not saving**: Check `storage/data/` permissions
- **React not loading**: Check browser console for JS errors
