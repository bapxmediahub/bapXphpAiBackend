<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Sri Panchami Spiritual</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/band.css">
<style>
.admin-shell { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
.admin-sidebar { background: var(--color-ink); color: rgba(255,255,255,0.6); display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
.admin-sidebar__brand { padding: var(--space-lg); border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: var(--space-2xs); }
.admin-sidebar__brand span { font-family: var(--font-serif); font-size: 1.05rem; color: var(--color-gold); font-weight: 600; }
.admin-sidebar__brand small { font-size: 0.7rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.1em; }
.admin-sidebar .admin-sidebar__nav { display: flex; flex: 1; flex-direction: column; position: static; inset: auto; background: transparent; box-shadow: none; border: 0; padding: var(--space-sm) 0; }
.admin-sidebar__section { padding: var(--space-md) var(--space-lg) var(--space-xs); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.15em; color: rgba(255,255,255,0.25); font-weight: 600; }
.admin-sidebar__nav a { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm) var(--space-lg); color: rgba(255,255,255,0.55); font-size: 0.85rem; transition: all var(--transition-base); border-left: 3px solid transparent; }
.admin-sidebar__nav a:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.85); }
.admin-sidebar__nav a.active { background: rgba(212,175,55,0.08); color: var(--color-gold); border-left-color: var(--color-gold); }
.admin-sidebar__nav a svg { flex-shrink: 0; opacity: 0.7; }
.admin-sidebar__nav a.active svg { opacity: 1; }
.admin-sidebar__footer { padding: var(--space-md) var(--space-lg); border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: var(--space-xs); }
.admin-sidebar__footer a { display: flex; align-items: center; gap: var(--space-sm); font-size: 0.8rem; color: rgba(255,255,255,0.4); transition: color var(--transition-base); }
.admin-sidebar__footer a:hover { color: var(--color-error); }
.admin-main { background: var(--color-bg-alt); min-height: 100vh; }
.admin-topbar { background: var(--color-white); border-bottom: 1px solid var(--color-border); padding: var(--space-md) var(--space-xl); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
.admin-topbar h1 { font-family: var(--font-serif); font-size: 1.25rem; margin: 0; color: var(--color-ink); }
.admin-topbar__actions { display: flex; align-items: center; gap: var(--space-sm); }
.admin-body { padding: var(--space-xl); }
@media (max-width: 768px) {
    .admin-shell { grid-template-columns: 1fr; }
    .admin-sidebar { display: none; position: fixed; top: 0; left: 0; bottom: 0; width: 260px; z-index: 1000; }
    .admin-sidebar.open { display: flex; }
    #sidebarToggle { display: inline-flex !important; }
    .admin-topbar { padding: var(--space-sm) var(--space-md); }
    .admin-body { padding: var(--space-md); }
}
</style>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar__brand">
            <span>Sri Panchami Spiritual</span>
            <small>Admin Panel</small>
        </div>
        <nav class="admin-sidebar__nav">
            <div class="admin-sidebar__section">Main</div>
            <a href="/admin" class="<?= ($_SERVER['REQUEST_URI'] === '/admin' ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <div class="admin-sidebar__section">Catalog</div>
            <a href="/admin/products" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/products') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                Products
            </a>
            <a href="/admin/categories" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/categories') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Categories
            </a>
            <a href="/admin/coupons" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/coupons') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Coupons
            </a>
            <div class="admin-sidebar__section">Services</div>
            <a href="/admin/astrologers" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/astrologers') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
                Astrologers
            </a>
            <a href="/admin/appointments" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/appointments') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Sessions
            </a>
            <a href="/admin/temples" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/temples') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
                Temples
            </a>
            <div class="admin-sidebar__section">Operations</div>
            <a href="/admin/orders" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/orders') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Orders
            </a>
            <a href="/admin/contact-submissions" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/contact-submissions') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 01-4 4H7l-4 4V7a4 4 0 014-4h10a4 4 0 014 4z"/></svg>
                Contacts
            </a>
            <a href="/admin/support-tickets" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/support-tickets') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
                Support
            </a>
            <a href="/admin/media" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/media') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                Media
            </a>
            <a href="/admin/environment" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/environment') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                Environment
            </a>
            <a href="/admin/settings" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/settings') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Settings
            </a>
            <a href="/admin/integrations" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/integrations') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1"/></svg>
                Integrations
            </a>
            <a href="/admin/shipping" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/shipping') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                Shipping
            </a>
            <a href="/admin/backups" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/backups') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Backups
            </a>
            <a href="/admin/audit-log" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/audit-log') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                Audit Log
            </a>
            <a href="/admin/developer/project-map" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/developer/project-map') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h6v6H3zM15 3h6v6h-6zM9 15h6v6H9z"/><path d="M6 9v3h6v3M18 9v3h-6"/></svg>
                Project Map
            </a>
        </nav>
        <div class="admin-sidebar__footer">
            <a href="/">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                View Site
            </a>
            <a href="/logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 17"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>
    <main class="admin-main">
        <div class="admin-topbar">
            <button class="btn btn-sm btn-ghost" id="sidebarToggle" style="display:none; margin-right:var(--space-sm);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
            <div class="admin-topbar__actions">
                <a href="/" class="btn btn-sm btn-ghost" target="_blank">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    View Site
                </a>
                <a href="/logout" class="btn btn-sm btn-ghost">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 17"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </div>
        <div class="admin-body">
        <?php if(!empty($_SESSION['flash'])): ?>
            <div class="flash flash--success" style="margin-bottom:var(--space-lg);"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
        <?php endif; ?>
        <?php require $viewFile; ?>
        </div>
    </main>
</div>
<script>
const sidebar = document.getElementById('admin-sidebar');
const toggle = document.getElementById('sidebarToggle');
if (toggle) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
}
</script>
</body>
</html>
