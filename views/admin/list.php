<div class="admin-header">
    <h1><?= e($title ?? 'Admin Section') ?></h1>
    <a href="/admin" class="btn btn-sm btn-ghost">← Dashboard</a>
</div>
<div class="admin-card">
    <h2>Overview</h2>
    <p>CRUD workspace for <?= e(strtolower($title ?? 'records')) ?>. Manage your data using the tools available in the sidebar.</p>
</div>
