<?php $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); $csrf = $_SESSION['csrf_token']; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — Sri Panchami Spiritual</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/admin/manifest.json">
<meta name="theme-color" content="#3a0003">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SPS Admin">
<link rel="apple-touch-icon" href="/assets/images/logo-square.jpeg">
<link rel="stylesheet" href="/assets/css/band.css">
<style>
.admin-shell { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
.admin-sidebar { background: var(--color-ink); color: rgba(255,255,255,0.6); display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
.admin-sidebar__brand { padding: var(--space-lg); border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: var(--space-2xs); }
.admin-sidebar__brand span { font-family: var(--font-serif); font-size: 1.05rem; color: var(--color-gold); font-weight: 600; }
.admin-sidebar__brand small { font-size: 0.7rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.1em; }
.admin-sidebar .admin-sidebar__nav { display: flex; flex: 1; flex-direction: column; position: static; inset: auto; background: transparent; box-shadow: none; border: 0; padding: var(--space-xs) 0; }
.admin-nav-top, .admin-nav-toggle { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm) var(--space-lg); color: rgba(255,255,255,0.55); font-size: 0.85rem; transition: all var(--transition-base); border-left: 3px solid transparent; cursor: pointer; background: none; border-right: 0; border-top: 0; border-bottom: 0; width: 100%; text-align: left; font-family: inherit; }
.admin-nav-top:hover, .admin-nav-toggle:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.85); }
.admin-nav-top.active { background: rgba(212,175,55,0.08); color: var(--color-gold); border-left-color: var(--color-gold); }
.admin-nav-top svg, .admin-nav-toggle svg { flex-shrink: 0; opacity: 0.7; }
.admin-nav-top.active svg { opacity: 1; }
.admin-nav-chevron { margin-left: auto; transition: transform var(--transition-base); }
.admin-nav-toggle[aria-expanded="false"] .admin-nav-chevron { transform: rotate(-90deg); }
.admin-submenu { display: flex; flex-direction: column; }
.admin-submenu a { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-xs) var(--space-lg) var(--space-xs) calc(var(--space-lg) + 24px); color: rgba(255,255,255,0.4); font-size: 0.82rem; transition: all var(--transition-base); border-left: 3px solid transparent; }
.admin-submenu a:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.75); }
.admin-submenu a.active { background: rgba(212,175,55,0.06); color: var(--color-gold); border-left-color: var(--color-gold); }
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
        <nav class="admin-sidebar__nav" id="admin-nav">
            <a href="/admin" class="admin-nav-top <?= ($_SERVER['REQUEST_URI'] === '/admin' ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <button type="button" class="admin-nav-toggle" data-target="menu-catalog" aria-expanded="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                Catalog
                <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="admin-submenu" id="menu-catalog">
                <a href="/admin/products" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/products') === 0 ? 'active' : '') ?>">Products</a>
                <a href="/admin/categories" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/categories') === 0 ? 'active' : '') ?>">Categories</a>
                <a href="/admin/coupons" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/coupons') === 0 ? 'active' : '') ?>">Coupons</a>
            </div>
            <button type="button" class="admin-nav-toggle" data-target="menu-services" aria-expanded="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
                Services
                <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="admin-submenu" id="menu-services">
                <a href="/admin/astrologers" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/astrologers') === 0 ? 'active' : '') ?>">Astrologers</a>
                <a href="/admin/appointments" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/appointments') === 0 ? 'active' : '') ?>">Sessions</a>
                <a href="/admin/consultation-analytics" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/consultation-analytics') === 0 ? 'active' : '') ?>">Analytics</a>
                <a href="/admin/temples" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/temples') === 0 ? 'active' : '') ?>">Temples</a>
            </div>
            <button type="button" class="admin-nav-toggle" data-target="menu-commerce" aria-expanded="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Commerce
                <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="admin-submenu" id="menu-commerce">
                <a href="/admin/orders" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/orders') === 0 ? 'active' : '') ?>">Orders</a>
                <a href="/admin/shipping" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/shipping') === 0 ? 'active' : '') ?>">Shipping</a>
                <a href="/admin/tax-report" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/tax-report') === 0 ? 'active' : '') ?>">Tax Report</a>
                <a href="/admin/contact-submissions" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/contact-submissions') === 0 ? 'active' : '') ?>">Contacts</a>
                <a href="/admin/support-tickets" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/support-tickets') === 0 ? 'active' : '') ?>">Support</a>
                <a href="/admin/email-inbox" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/email-inbox') === 0 ? 'active' : '') ?>">Email Inbox</a>
                <a href="/admin/email-outbox" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/email-outbox') === 0 ? 'active' : '') ?>">Email Outbox</a>
            </div>
            <button type="button" class="admin-nav-toggle" data-target="menu-appearance" aria-expanded="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                Appearance
                <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="admin-submenu" id="menu-appearance">
                <a href="/admin/media" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/media') === 0 ? 'active' : '') ?>">Media Library</a>
                <a href="/admin/appearance" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/appearance') === 0 ? 'active' : '') ?>">Logo & Favicon</a>
            </div>
            <button type="button" class="admin-nav-toggle" data-target="menu-settings" aria-expanded="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Settings
                <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="admin-submenu" id="menu-settings">
                <a href="/admin/environment" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/environment') === 0 ? 'active' : '') ?>">Environment</a>
                <a href="/admin/integrations" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/integrations') === 0 ? 'active' : '') ?>">Integrations</a>
                <a href="/admin/agent" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/agent') === 0 ? 'active' : '') ?>">AI Agent</a>
                <a href="/admin/settings" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/settings') === 0 ? 'active' : '') ?>">Site Settings</a>
                <a href="/admin/backups" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/backups') === 0 ? 'active' : '') ?>">Backups</a>
                <a href="/admin/audit-log" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/audit-log') === 0 ? 'active' : '') ?>">Audit Log</a>
            </div>
            <a href="/admin/developer/project-map" class="admin-nav-top <?= (strpos($_SERVER['REQUEST_URI'], '/admin/developer/project-map') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h6v6H3zM15 3h6v6h-6zM9 15h6v6H9z"/><path d="M6 9v3h6v3M18 9v3h-6"/></svg>
                Project Map
            </a>
            <a href="/admin/developer/workflow" class="admin-nav-top <?= (strpos($_SERVER['REQUEST_URI'], '/admin/developer/workflow') === 0 ? 'active' : '') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Workflow
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
        <div class="admin-breadcrumb" style="padding:var(--space-xs) var(--space-xl); font-size:0.78rem; color:var(--color-text-muted); background:var(--color-white); border-bottom:1px solid var(--color-border); display:flex; gap:var(--space-xs); align-items:center;">
            <a href="/admin" style="color:var(--color-text-muted);">Dashboard</a>
            <?php
            $__path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH);
            $__segments = array_values(array_filter(explode('/', $__path)));
            $__crumb = '';
            $__labelMap = [
                'products'=>'Products','categories'=>'Categories','coupons'=>'Coupons',
                'orders'=>'Orders','shipping'=>'Shipping','tax-report'=>'Tax Report','astrologers'=>'Astrologers',
                'appointments'=>'Sessions',
                'consultation-analytics'=>'Analytics','temples'=>'Temples',
                'settings'=>'Site Settings','integrations'=>'Integrations',
                'backups'=>'Backups','audit-log'=>'Audit Log',
                'contact-submissions'=>'Contacts','support-tickets'=>'Support',
                'email-inbox'=>'Email Inbox','email-outbox'=>'Email Outbox',
                'appearance'=>'Logo & Favicon','media'=>'Media Library',
                'environment'=>'Environment','developer'=>'Developer',
                'project-map'=>'Project Map','contact_submissions'=>'Contacts',
            ];
            foreach ($__segments as $__i => $__seg):
                if ($__seg === 'admin' && $__i === 0) continue;
                $__crumb .= '/' . $__seg;
                $__label = $__labelMap[$__seg] ?? ucwords(str_replace(['-','_'], ' ', $__seg));
                if ($__i === count($__segments) - 1):
            ?>
                <span style="color:var(--color-ink); font-weight:500;"><?= e($__label) ?></span>
            <?php else: ?>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                <a href="<?= e($__crumb) ?>" style="color:var(--color-text-muted);"><?= e($__label) ?></a>
            <?php endif; endforeach; ?>
        </div>
        <div class="admin-body">
        <?php
        $__flash = $_SESSION['flash'] ?? null;
        if ($__flash):
            $__msg = is_array($__flash) ? $__flash['message'] : $__flash;
            $__type = is_array($__flash) ? ($__flash['type'] ?? 'info') : 'info';
            unset($_SESSION['flash']);
        ?>
        <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?= json_encode($__msg) ?>,<?= json_encode($__type) ?>);});</script>
        <?php endif; ?>
        <?php require $viewFile; ?>
        </div>
    </main>
</div>
<script>
if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).catch(function(){}); }
var installPrompt=null;window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();installPrompt=e;document.getElementById('pwa-install-btn').style.display='flex';});window.addEventListener('appinstalled',function(){installPrompt=null;document.getElementById('pwa-install-btn').style.display='none';});document.addEventListener('click',function(e){if(e.target.closest('#pwa-install-btn')){if(!installPrompt)return;installPrompt.prompt();installPrompt.userChoice.then(function(){installPrompt=null;document.getElementById('pwa-install-btn').style.display='none';});}});
function showToast(msg,type){type=type||'info';var c=document.getElementById('toast-container');if(!c){c=document.createElement('div');c.id='toast-container';document.body.appendChild(c);}var t=document.createElement('div');t.className='toast toast--'+type;var icons={success:'✓',error:'✕',warning:'⚠',info:'ℹ'};t.innerHTML='<span class="toast__icon">'+(icons[type]||'ℹ')+'</span><span class="toast__text">'+msg+'</span><button class="toast__close" aria-label="Dismiss">&times;</button>';t.querySelector('.toast__close').addEventListener('click',function(e){e.stopPropagation();dismiss(t);});t.addEventListener('click',function(){dismiss(t);});c.appendChild(t);var timer=setTimeout(function(){dismiss(t);},4000);function dismiss(el){if(el.classList.contains('toast--out'))return;el.classList.add('toast--out');clearTimeout(timer);setTimeout(function(){if(el.parentNode)el.parentNode.removeChild(el);},250);}}
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
document.querySelectorAll('.admin-nav-toggle').forEach(function(btn){btn.addEventListener('click',function(){var target=document.getElementById(btn.dataset.target);if(!target)return;var expanded=btn.getAttribute('aria-expanded')==='true';btn.setAttribute('aria-expanded',String(!expanded));target.style.display=expanded?'none':'';});});
document.querySelectorAll('.table-wrap').forEach(function(wrap){var table=wrap.querySelector('table');if(!table||table.querySelector('.admin-search-added'))return;var search=document.createElement('input');search.type='text';search.placeholder='Search\u2026';search.style.cssText='width:100%;max-width:320px;margin-bottom:var(--space-sm);padding:0.5rem 0.75rem;font-size:0.85rem;border:1px solid var(--color-border);border-radius:var(--radius-sm);background:var(--color-white);';wrap.parentNode.insertBefore(search,wrap);search.addEventListener('input',function(){var q=this.value.toLowerCase().trim();table.querySelectorAll('tbody tr').forEach(function(row){if(!q||row.textContent.toLowerCase().includes(q)){row.style.display=''}else{row.style.display='none'}});});table.classList.add('admin-search-added');});
</script>
<div id="toast-container"></div>
<button id="pwa-install-btn" class="pwa-install-btn">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Install App
</button>
</body>
</html>
