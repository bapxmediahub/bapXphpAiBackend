<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title) ?></h2>
    <p style="color:var(--color-text-muted); margin:0 0 var(--space-lg);">View and manage <?= e(strtolower($title)) ?> records. Use the sidebar to add or edit entries.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Details</th><th>Actions</th></tr></thead>
            <tbody>
            <tr><td colspan="3" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">Data managed through individual resource pages in the sidebar.</td></tr>
            </tbody>
        </table>
    </div>
</div>
