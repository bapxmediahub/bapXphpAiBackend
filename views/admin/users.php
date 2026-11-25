<?php
$filters = ['q' => $query, 'reference' => $reference, 'role' => $role, 'activity' => $activity, 'period' => $period, 'status' => $status, 'sort' => $sort, 'per_page' => $pageSize];
$directoryUrl = static function (array $changes) use ($filters): string {
    $params = array_filter($changes + $filters, static fn($value) => $value !== '');
    return '/admin/users?' . http_build_query($params);
};
$advancedCount = count(array_filter([$reference !== '', $role !== '', $activity !== '', $period !== '', $status !== '', $sort !== 'name', $pageSize !== 25]));
$activeFilters = $query !== '' || $advancedCount > 0;
$appliedFilters = [
    'q' => ['Search', $query, ''],
    'reference' => ['Reference', $reference, ''],
    'role' => ['Role', $roles[$role] ?? '', ''],
    'activity' => ['Order activity', $activityOptions[$activity] ?? '', ''],
    'period' => ['Latest order', $periodOptions[$period] ?? '', ''],
    'status' => ['Latest order status', $statusOptions[$status] ?? '', ''],
    'sort' => ['Sort', $sort !== 'name' ? $sortOptions[$sort] : '', 'name'],
    'per_page' => ['Page size', $pageSize !== 25 ? $pageSize . ' per page' : '', 25],
];
$segments = [
    ['activity' => '', 'stat' => 'accounts', 'label' => 'All accounts'],
    ['activity' => 'no_orders', 'stat' => 'no_orders', 'label' => 'No orders'],
    ['activity' => 'one_order', 'stat' => 'one_order', 'label' => 'One order'],
    ['activity' => 'repeat', 'stat' => 'repeat', 'label' => '2+ orders'],
];
?>
<section class="users-workspace">
    <header class="report-head">
        <h1>Users</h1>
        <a class="btn btn-outline" href="/admin/orders"><?= $navIcon('bag') ?>All orders</a>
    </header>

    <section class="users-overview" aria-labelledby="users-overview-title">
        <header class="users-overview__heading">
            <h2 id="users-overview-title">Registered accounts</h2>
            <a href="<?= e($helpContext['url']) ?>"><?= $navIcon('help') ?>Users guide</a>
        </header>
        <div class="users-metrics">
            <?php foreach ($segments as $segment): ?>
                <a href="<?= e($directoryUrl(['activity' => $segment['activity']])) ?>"
                   <?= $activity === $segment['activity'] ? 'aria-current="true"' : '' ?>>
                    <span><?= e($segment['label']) ?></span>
                    <strong><?= number_format($stats[$segment['stat']]) ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="users-directory" aria-labelledby="users-directory-title">
        <header class="users-directory__head">
            <h2 id="users-directory-title">Directory</h2>
            <p class="users-result-count"><?= $count ? number_format($offset + 1) . '–' . number_format($offset + count($users)) . ' of ' . number_format($count) : '0' ?>
                <?= $count === 1 ? 'account' : 'accounts' ?></p>
            <p class="users-directory__scope">Orders matched by checkout email · IST</p>
        </header>
        <form class="users-filters" method="get" action="/admin/users" aria-label="Filter accounts">
            <div class="users-search">
                <div class="users-field">
                    <label for="users-q">Search accounts</label>
                    <input type="search" id="users-q" name="q" value="<?= e($query) ?>" maxlength="150" placeholder="Name or email">
                </div>
                <button type="submit" class="btn btn-primary"><?= $navIcon('search') ?>Search</button>
            </div>
            <details class="users-filter-options">
                <summary>
                    <?= $navIcon('filter') ?>
                    <span>More filters</span>
                    <?php if ($advancedCount): ?><span class="users-filter-count"><?= $advancedCount ?> active</span><?php endif; ?>
                </summary>
                <div class="users-filter-fields">
                    <div class="users-field users-reference">
                        <label for="users-reference">Order or invoice reference</label>
                        <input type="text" id="users-reference" name="reference" value="<?= e($reference) ?>" maxlength="150" placeholder="Enter the full reference">
                    </div>
                    <div class="users-field">
                        <label for="users-role">Role</label>
                        <select id="users-role" name="role"><option value="">All roles</option>
                            <?php foreach ($roles as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $role === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="users-field">
                        <label for="users-activity">Order activity</label>
                        <select id="users-activity" name="activity"><option value="">All activity</option>
                            <?php foreach ($activityOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $activity === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="users-field">
                        <label for="users-period">Latest order</label>
                        <select id="users-period" name="period"><option value="">Any date</option>
                            <?php foreach ($periodOptions as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $period === (string)$value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="users-field">
                        <label for="users-status">Latest order status</label>
                        <select id="users-status" name="status"><option value="">Any status</option>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="users-field">
                        <label for="users-sort">Sort by</label>
                        <select id="users-sort" name="sort">
                            <?php foreach ($sortOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="users-field">
                        <label for="users-per_page">Per page</label>
                        <select id="users-per_page" name="per_page">
                            <?php foreach ($sizeOptions as $value): ?>
                                <option value="<?= $value ?>" <?= $pageSize === $value ? 'selected' : '' ?>><?= $value ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="users-actions"><button type="submit" class="btn btn-primary"><?= $navIcon('check') ?>Apply filters</button></div>
                </div>
            </details>
        </form>

        <?php if ($activeFilters): ?>
            <div class="users-applied">
                <ul aria-label="Applied filters">
                    <?php foreach ($appliedFilters as $key => [$label, $value, $reset]): ?>
                        <?php if ($value === '') continue; ?>
                        <li><a href="<?= e($directoryUrl([$key => $reset])) ?>" aria-label="<?= e('Remove ' . strtolower($label) . ' filter: ' . $value) ?>">
                            <span><strong><?= e($label) ?></strong> <?= e($value) ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <a class="users-reset" href="/admin/users"><?= $navIcon('reset') ?>Reset filters</a>
            </div>
        <?php endif; ?>

        <?php if ($users): ?>
            <div class="users-row users-row--head" aria-hidden="true">
                <span>Account</span><span>Role</span><span>Orders</span><span>Latest order (IST)</span><span></span>
            </div>
        <?php endif; ?>
        <?php foreach ($users as $user):
            $name = $user['name'] ?: ($user['email'] ?: 'Unnamed account');
            $orderCount = count($user['orders']);
            $latest = $user['orders'][0] ?? null;
            $latestDate = $latest['date'] ?? null;
            ?>
            <details class="users-entry"<?= $reference !== '' && $count === 1 ? ' open' : '' ?>>
                <summary class="users-row">
                    <span class="users-identity">
                        <span class="users-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                        <span class="users-identity__text"><strong><?= e($name) ?></strong>
                            <?php if ($user['name'] && $user['email']): ?><small><?= e($user['email']) ?></small><?php endif; ?>
                        </span>
                    </span>
                    <span class="users-role<?= $user['role'] === 'admin' ? ' users-role--admin' : '' ?>"><?= e($roles[$user['role']]) ?></span>
                    <span class="users-orders"><?= number_format($orderCount) ?> <span class="users-order-unit"><?= $orderCount === 1 ? 'order' : 'orders' ?></span></span>
                    <span class="users-latest<?= $latest === null ? ' users-latest--empty' : '' ?>">
                        <?php if ($latestDate): ?>
                            <span class="users-sr-only">Latest order</span>
                            <span class="users-latest-status"><?= e($statusOptions[$latest['status']] ?? ($latest['status'] !== '' ? ucfirst(str_replace('_', ' ', $latest['status'])) : 'Status unavailable')) ?></span>
                            <time datetime="<?= e($latestDate->format(DATE_ATOM)) ?>">
                                <span><?= e($latestDate->format('d M Y')) ?></span><span><?= e($latestDate->format('g:i a')) ?></span>
                            </time>
                        <?php else: ?>
                            <?= $latest !== null ? 'Order date unavailable' : 'No matched orders' ?>
                        <?php endif; ?>
                    </span>
                    <svg class="users-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="users-history">
                    <h3>Order history</h3>
                    <?php if (!$user['orders']): ?>
                        <p>No orders match this account’s email.</p>
                    <?php else: ?>
                        <div class="table-wrap" data-no-table-search role="region" aria-label="Order history" tabindex="0">
                            <table role="table">
                                <thead role="rowgroup"><tr role="row">
                                    <th scope="col" role="columnheader">Order</th>
                                    <th scope="col" role="columnheader">Placed (IST)</th>
                                    <th scope="col" role="columnheader">Status</th>
                                    <th scope="col" role="columnheader">Total</th>
                                    <th scope="col" role="columnheader">Invoice</th>
                                </tr></thead>
                                <tbody role="rowgroup">
                                <?php foreach ($user['orders'] as $position => $order):
                                    $path = '/admin/orders/' . rawurlencode($order['id']);
                                    $isReferenceMatch = isset($user['reference_matches'][$position]);
                                    ?>
                                    <tr role="row"<?= $isReferenceMatch ? ' class="users-history__match"' : '' ?>>
                                        <td role="cell">
                                            <small aria-hidden="true">Order</small><a href="<?= e($path) ?>"><?= e($order['id']) ?></a>
                                            <?php if ($isReferenceMatch): ?><span class="users-history__match-label">Reference match</span><?php endif; ?>
                                        </td>
                                        <td role="cell"><small aria-hidden="true">Placed (IST)</small>
                                            <?php if ($order['date']): ?><time datetime="<?= e($order['date']->format(DATE_ATOM)) ?>"><?= e($order['date']->format('d M Y, g:i a')) ?></time>
                                            <?php else: ?>Unavailable<?php endif; ?>
                                        </td>
                                        <td role="cell"><small aria-hidden="true">Status</small><?= e(ucfirst(str_replace('_', ' ', $order['status'] ?: 'Unknown'))) ?></td>
                                        <td role="cell"><small aria-hidden="true">Total</small><?= is_numeric($order['total']) ? '₹' . e(number_format((float)$order['total'], 2)) : '—' ?></td>
                                        <td role="cell"><small aria-hidden="true">Invoice</small>
                                            <?php if ($order['invoice'] !== ''): ?><a href="<?= e($path) ?>/invoice"><?= e($order['invoice']) ?></a><?php else: ?>Not issued<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
        <?php if (!$users): ?>
            <div class="users-empty">
                <strong><?= $activeFilters ? 'No accounts match these filters' : 'No registered accounts yet' ?></strong>
                <?php if ($reference !== ''): ?><p>Check the full order or invoice reference and any other filters.</p>
                <?php elseif ($activeFilters): ?><p>Try another name or email, or clear the filters above.</p><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($pages > 1): ?>
            <nav class="users-pages" aria-label="Users pages">
                <?php if ($page > 1): ?><a class="btn btn-outline" href="<?= e($directoryUrl(['page' => $page - 1])) ?>"><?= $navIcon('previous') ?>Previous</a><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-outline" href="<?= e($directoryUrl(['page' => $page + 1])) ?>">Next<?= $navIcon('next') ?></a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</section>
