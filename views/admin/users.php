<?php
$filters = ['q' => $query, 'role' => $role, 'activity' => $activity, 'sort' => $sort, 'per_page' => $pageSize];
$pageUrl = static fn(int $number): string => '/admin/users?' . http_build_query($filters + ['page' => $number]);
$activeFilters = $query !== '' || $role !== '' || $activity !== '' || $sort !== 'name' || $pageSize !== 25;
?>
<section class="users-workspace">
    <header class="report-head">
        <div><h1>Users</h1><p>Find registered accounts and review orders matched by checkout email.</p></div>
        <a class="btn btn-outline" href="/admin/orders">All orders</a>
    </header>

    <div class="users-metrics" aria-label="Account overview">
        <a href="/admin/users"><span>Registered accounts</span><strong><?= number_format($stats['accounts']) ?></strong></a>
        <a href="/admin/users?activity=with_orders"><span>Accounts with orders</span><strong><?= number_format($stats['with_orders']) ?></strong></a>
        <a href="/admin/users?activity=repeat"><span>Accounts with 2+ orders</span><strong><?= number_format($stats['repeat']) ?></strong></a>
    </div>

    <form class="users-filters" method="get" action="/admin/users">
        <label>Search accounts
            <input type="search" name="q" value="<?= e($query) ?>" maxlength="150" placeholder="Name or email">
        </label>
        <label>Role
            <select name="role"><option value="">All roles</option>
                <?php foreach ($roles as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $role === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Order activity
            <select name="activity"><option value="">All activity</option>
                <?php foreach ($activityOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $activity === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sort by
            <select name="sort">
                <?php foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Per page
            <select name="per_page">
                <?php foreach ($sizeOptions as $value): ?>
                    <option value="<?= $value ?>" <?= $pageSize === $value ? 'selected' : '' ?>><?= $value ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="users-actions">
            <button class="btn btn-primary">Apply filters</button>
            <?php if ($activeFilters): ?><a class="btn btn-outline" href="/admin/users">Reset</a><?php endif; ?>
        </div>
    </form>

    <section class="users-directory" aria-labelledby="users-directory-title">
        <header class="users-directory__head">
            <h2 id="users-directory-title">Account directory</h2>
            <p><?= $count ? 'Showing ' . ($offset + 1) . '–' . ($offset + count($users)) . ' of ' . number_format($count) : '0' ?>
                <?= $count === 1 ? 'account' : 'accounts' ?></p>
        </header>
        <?php if ($users): ?>
            <div class="users-row users-row--head" aria-hidden="true">
                <span>Account</span><span>Role</span><span>Orders</span><span>Latest order (IST)</span><span></span>
            </div>
        <?php endif; ?>
        <?php foreach ($users as $user):
            $name = $user['name'] ?: ($user['email'] ?: 'Unnamed user');
            $orderCount = count($user['orders']);
            $latest = $user['orders'][0]['date'] ?? null;
            ?>
            <details class="users-entry">
                <summary class="users-row">
                    <span class="users-identity">
                        <span class="users-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                        <span><strong><?= e($name) ?></strong>
                            <?php if ($user['name'] && $user['email']): ?><small><?= e($user['email']) ?></small><?php endif; ?>
                        </span>
                    </span>
                    <span class="users-role"><?= e($roles[$user['role']]) ?></span>
                    <span class="users-orders"><?= $orderCount ?><span class="users-mobile-label"> <?= $orderCount === 1 ? 'order' : 'orders' ?></span></span>
                    <span class="users-latest">
                        <?php if ($latest): ?><span class="users-mobile-label">Latest </span><time datetime="<?= e($latest->format(DATE_ATOM)) ?>"><?= e($latest->format('d M Y, g:i a')) ?></time>
                        <?php else: ?>—<?php endif; ?>
                    </span>
                    <svg class="users-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="users-history">
                    <?php if (!$user['orders']): ?><p>No matching orders for this account.</p><?php else: ?>
                        <div class="table-wrap" data-no-table-search role="region" aria-label="Order history" tabindex="0">
                            <table><thead><tr><th>Order</th><th>Placed (IST)</th><th>Status</th><th>Total</th><th>Invoice</th></tr></thead><tbody>
                            <?php foreach ($user['orders'] as $order): $path = '/admin/orders/' . rawurlencode($order['id']); ?>
                                <tr><td><a href="<?= e($path) ?>"><?= e($order['id']) ?></a></td>
                                    <td><?php if ($order['date']): ?><time datetime="<?= e($order['date']->format(DATE_ATOM)) ?>"><?= e($order['date']->format('d M Y, g:i a')) ?></time><?php else: ?>Unavailable<?php endif; ?></td>
                                    <td><?= e(ucfirst(str_replace('_', ' ', $order['status'] ?: 'Unknown'))) ?></td>
                                    <td><?= is_numeric($order['total']) ? '₹' . e(number_format((float)$order['total'], 2)) : '—' ?></td>
                                    <td><?php if ($order['invoice'] !== ''): ?><a href="<?= e($path) ?>/invoice"><?= e($order['invoice']) ?></a><?php else: ?>Not issued<?php endif; ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
        <?php if (!$users): ?><p class="report-empty">No accounts match these filters.</p><?php endif; ?>
        <?php if ($pages > 1): ?>
            <nav class="users-pages" aria-label="Users pages">
                <?php if ($page > 1): ?><a class="btn btn-outline" href="<?= e($pageUrl($page - 1)) ?>">Previous</a><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-outline" href="<?= e($pageUrl($page + 1)) ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</section>
