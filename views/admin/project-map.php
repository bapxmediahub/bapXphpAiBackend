<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:var(--space-md); flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 var(--space-xs);">Project Map</h2>
            <p style="margin:0;">Generated route, controller, service, view, integration, schema, storage, tool, and gap registry for this PHP template app.</p>
        </div>
        <a href="/docs/systematic-map.mmd" class="btn btn-sm btn-ghost" target="_blank">Open Map</a>
    </div>
</div>

<div class="admin-card">
    <h2>Validation</h2>
    <div class="admin-map-status">
        <?php foreach($validation as $label => $items): ?>
            <div class="admin-map-status__item">
                <strong><?= e(ucwords(str_replace('_', ' ', $label))) ?></strong>
                <?php if(empty($items)): ?>
                    <span class="badge badge--success">Clear</span>
                <?php else: ?>
                    <span class="badge badge--error"><?= e((string)count($items)) ?> issue<?= count($items) === 1 ? '' : 's' ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-card">
    <h2>Routes</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Method</th><th>Path</th><th>Controller</th><th>Page</th><th>Services</th></tr>
            </thead>
            <tbody>
                <?php foreach(($map['routes'] ?? []) as $route): ?>
                    <tr>
                        <td><span class="badge badge--default"><?= e($route['method'] ?? '') ?></span></td>
                        <td><code><?= e($route['path'] ?? '') ?></code></td>
                        <td><?= e($route['controller'] ?? '') ?></td>
                        <td><?= e($route['page'] ?? '') ?></td>
                        <td><?= e(implode(', ', $route['services'] ?? [])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <h2>Storage Collections</h2>
    <div class="admin-chip-list">
        <?php foreach(($map['collections'] ?? []) as $collection): ?>
            <span class="badge badge--info"><?= e($collection) ?></span>
        <?php endforeach; ?>
    </div>
</div>
