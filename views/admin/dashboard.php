<div class="admin-header">
    <h1>Dashboard</h1>
    <span style="font-size:0.8rem; color:var(--color-text-muted);">Welcome, Admin</span>
</div>
<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat__value"><?= (int)($productCount ?? 0) ?></div>
        <div class="admin-stat__label">Products</div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__value"><?= (int)($orderCount ?? 0) ?></div>
        <div class="admin-stat__label">Orders</div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__value"><?= (int)($bookingCount ?? 0) ?></div>
        <div class="admin-stat__label">Bookings</div>
    </div>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(280px,1fr));">
    <div class="admin-card">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:8px;"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            Catalog
        </h2>
        <p style="color:var(--color-text-muted); font-size:0.9rem; margin-bottom:var(--space-md);">Manage products, categories, and discount coupons.</p>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <a href="/admin/products" class="btn btn-sm btn-ghost">Products</a>
            <a href="/admin/categories" class="btn btn-sm btn-ghost">Categories</a>
            <a href="/admin/coupons" class="btn btn-sm btn-ghost">Coupons</a>
        </div>
    </div>
    <div class="admin-card">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:8px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Bookings
        </h2>
        <p style="color:var(--color-text-muted); font-size:0.9rem; margin-bottom:var(--space-md);">Manage astrologers, appointments, and schedules.</p>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <a href="/admin/astrologers" class="btn btn-sm btn-ghost">Astrologers</a>
            <a href="/admin/appointments" class="btn btn-sm btn-ghost">Appointments</a>
            <a href="/admin/temples" class="btn btn-sm btn-ghost">Temples</a>
        </div>
    </div>
    <div class="admin-card">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:8px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            Operations
        </h2>
        <p style="color:var(--color-text-muted); font-size:0.9rem; margin-bottom:var(--space-md);">Orders, shipping, settings, and integrations.</p>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <a href="/admin/orders" class="btn btn-sm btn-ghost">Orders</a>
            <a href="/admin/shipping" class="btn btn-sm btn-ghost">Shipping</a>
            <a href="/admin/settings" class="btn btn-sm btn-ghost">Settings</a>
            <a href="/admin/integrations" class="btn btn-sm btn-ghost">Integrations</a>
        </div>
    </div>
    <div class="admin-card">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:8px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            System
        </h2>
        <p style="color:var(--color-text-muted); font-size:0.9rem; margin-bottom:var(--space-md);">Backups, audit logs, and developer tools.</p>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <a href="/admin/backups" class="btn btn-sm btn-ghost">Backups</a>
            <a href="/admin/audit-log" class="btn btn-sm btn-ghost">Audit Log</a>
            <a href="/admin/project-map" class="btn btn-sm btn-ghost">Project Map</a>
        </div>
    </div>
</div>