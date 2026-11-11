<section class="report-workspace dashboard-commerce" aria-labelledby="dashboard-commerce-title">
    <div class="report-head">
        <div>
            <h1 id="dashboard-commerce-title">Commerce overview</h1>
            <p><time datetime="<?= e($from) ?>"><?= e($from) ?></time> to <time datetime="<?= e($to) ?>"><?= e($to) ?></time></p>
        </div>
        <a class="btn btn-outline" href="/admin/tax-report?<?= e(http_build_query(['report' => 'sales', 'from' => $from, 'to' => $to, 'group' => $period === 90 ? 'week' : 'day'])) ?>">Open Commerce Reports</a>
    </div>
    <nav class="dashboard-commerce__periods" aria-label="Analytics period">
        <?php foreach ([7, 30, 90] as $days): ?>
            <a href="/admin?period=<?= $days ?>" class="btn btn-sm <?= $period === $days ? 'btn-primary' : 'btn-outline' ?>" <?= $period === $days ? 'aria-current="page"' : '' ?>>Last <?= $days ?> days</a>
        <?php endforeach; ?>
    </nav>
    <?php $money = static fn($amount): string => '₹' . number_format((float)$amount, 2); ?>
    <?php require __DIR__ . '/_commerce-overview.php'; ?>
    <nav class="dashboard-commerce__periods" aria-label="Detailed reports">
        <?php foreach (\App\Services\StoreStatsService::REPORT_TYPES as $type => $label): ?>
            <?php if ($type === 'sales') continue; ?>
            <a class="btn btn-sm btn-outline" href="/admin/tax-report?<?= e(http_build_query(['report' => $type, 'from' => $from, 'to' => $to])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</section>

<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        </div>
        <div class="admin-stat__value"><?= (int)($productCount ?? 0) ?></div>
        <div class="admin-stat__label">Products</div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        </div>
        <div class="admin-stat__value"><?= (int)($orderCount ?? 0) ?></div>
        <div class="admin-stat__label">Orders</div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="admin-stat__value"><?= (int)($bookingCount ?? 0) ?></div>
        <div class="admin-stat__label">Saved Sessions</div>
    </div>
</div>

<?php
$__secretsGa = (new \App\Services\SecretService())->all();
$__gaConfigured = !empty($__secretsGa['google_analytics_id']);
$__adsConfigured = !empty($__secretsGa['google_ads_id']);
$__gsvConfigured = !empty($__secretsGa['google_site_verification']);
$__googleSiteKitEnabled = $__gaConfigured || $__adsConfigured || $__gsvConfigured;
?>

<?php if ($__googleSiteKitEnabled): ?>
<div class="admin-card" style="margin-bottom:var(--space-lg); border-left:3px solid var(--color-gold);">
    <h2 style="display:flex; align-items:center; gap:var(--space-sm); font-size:1rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
        Google Site Kit
    </h2>
    <div style="display:flex; flex-wrap:wrap; gap:var(--space-md); font-size:0.85rem;">
        <?php if ($__gaConfigured): ?>
        <span style="display:flex; align-items:center; gap:var(--space-xs);">
            <span style="color:var(--color-success);">●</span> Analytics
            <a href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener" style="font-size:0.75rem;">Open GA4 →</a>
        </span>
        <?php endif; ?>
        <?php if ($__adsConfigured): ?>
        <span style="display:flex; align-items:center; gap:var(--space-xs);">
            <span style="color:var(--color-success);">●</span> Ads
            <a href="https://ads.google.com/" target="_blank" rel="noopener" style="font-size:0.75rem;">Open Ads →</a>
        </span>
        <?php endif; ?>
        <?php if ($__gsvConfigured): ?>
        <span style="display:flex; align-items:center; gap:var(--space-xs);">
            <span style="color:var(--color-success);">●</span> Search Console
            <a href="https://search.google.com/search-console" target="_blank" rel="noopener" style="font-size:0.75rem;">Open Console →</a>
        </span>
        <?php endif; ?>
    </div>
    <p style="margin:var(--space-sm) 0 0; font-size:0.78rem; color:var(--color-text-muted);">
        Google Analytics, Ads conversion tracking, and Search Console verification are active.
        <a href="/admin/integrations" style="font-weight:500;">Configure in Integrations</a>
    </p>
</div>
<?php else: ?>
<div class="admin-card" style="margin-bottom:var(--space-lg); border-left:3px solid var(--color-border);">
    <h2 style="display:flex; align-items:center; gap:var(--space-sm); font-size:1rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
        Google Analytics
    </h2>
    <p style="margin:0; font-size:0.85rem; color:var(--color-text-muted);">
        Google Analytics is not configured. 
        <a href="/admin/integrations">Set up GA4, Ads, and Search Console in Integrations →</a>
    </p>
</div>
<?php endif; ?>

<div class="admin-quick-grid">
    <div class="admin-card">
        <h2><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg> Catalog</h2>
        <p>Manage products, categories, collections, and discount coupons.</p>
        <div class="admin-card__actions">
            <a href="/admin/products" class="btn btn-sm btn-ghost">Products</a>
            <a href="/admin/categories" class="btn btn-sm btn-ghost">Categories</a>
            <a href="/admin/collections" class="btn btn-sm btn-ghost">Special Collections</a>
            <a href="/admin/coupons" class="btn btn-sm btn-ghost">Coupons</a>
        </div>
    </div>
    <div class="admin-card">
        <h2><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg> Services</h2>
        <p>Review saved sessions and manage service records.</p>
        <div class="admin-card__actions">
            <a href="/admin/appointments" class="btn btn-sm btn-ghost">Sessions</a>
            <a href="/admin/consultation-analytics" class="btn btn-sm btn-ghost">Analytics</a>
            <a href="/admin/temples" class="btn btn-sm btn-ghost">Temples</a>
        </div>
    </div>
    <div class="admin-card">
        <h2><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> Operations</h2>
        <p>Orders, shipping, settings, and integrations.</p>
        <div class="admin-card__actions">
            <a href="/admin/orders" class="btn btn-sm btn-ghost">Orders</a>
            <a href="/admin/shipping" class="btn btn-sm btn-ghost">Shipping</a>
            <a href="/admin/settings" class="btn btn-sm btn-ghost">Settings</a>
            <a href="/admin/integrations" class="btn btn-sm btn-ghost">Integrations</a>
        </div>
    </div>
</div>

<div class="admin-card" style="margin-top:var(--space-lg);">
    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:var(--space-md); margin-bottom:var(--space-md);">
        <div>
            <h2 style="font-size:1.1rem; margin:0;">Recent Sessions</h2>
            <p style="color:var(--color-text-muted); margin:var(--space-xs) 0 0;">Historical appointment requests are retained here for follow-up.</p>
        </div>
        <a href="/admin/appointments" class="btn btn-sm btn-ghost">View all</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Customer</th><th>Requested for</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
            <?php if (empty($appointments)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--color-text-muted); padding:var(--space-lg);">No saved session requests yet.</td></tr>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td><?= e((string)($appointment['customer_email'] ?? 'Guest')) ?></td>
                    <td><?= e(trim((string)($appointment['preferred_date'] ?? '') . ' ' . (string)($appointment['preferred_time'] ?? '')) ?: 'Not specified') ?></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', (string)($appointment['status'] ?? 'requested')))) ?></td>
                    <td><?= e(substr((string)($appointment['created_at'] ?? ''), 0, 10) ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
