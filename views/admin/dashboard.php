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
<div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(300px,1fr));">
    <div class="admin-card">
        <h2>🛍️ Catalog</h2>
        <p style="color:var(--color-text-muted); font-size:0.9rem; margin-bottom:var(--space-md);">Manage products, categories, and discount coupons.</p>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <a href="/admin/products" class="btn btn-sm btn-ghost">Products</a>
            <a href="/admin/categories" class="btn btn-sm btn-ghost">Categories</a>
            <a href="/admin/coupons" class="btn btn-sm btn-ghost">Coupons</a>
        </div>
    </div>
    <div class="admin-card">
        <h2>📅 Bookings</h2>
        <p style="color:var(--color-text-muted); font-size:0.9rem; margin-bottom:var(--space-md);">Manage astrologers, appointments, and schedules.</p>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <a href="/admin/astrologers" class="btn btn-sm btn-ghost">Astrologers</a>
            <a href="/admin/appointments" class="btn btn-sm btn-ghost">Appointments</a>
            <a href="/admin/temples" class="btn btn-sm btn-ghost">Temples</a>
        </div>
    </div>
    <div class="admin-card">
        <h2>⚙️ Operations</h2>
        <p style="color:var(--color-text-muted); font-size:0.9rem; margin-bottom:var(--space-md);">Orders, settings, integrations, and system tools.</p>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <a href="/admin/orders" class="btn btn-sm btn-ghost">Orders</a>
            <a href="/admin/settings" class="btn btn-sm btn-ghost">Settings</a>
            <a href="/admin/integrations" class="btn btn-sm btn-ghost">Integrations</a>
            <a href="/admin/project-map" class="btn btn-sm btn-ghost">Project Map</a>
        </div>
    </div>
</div>
