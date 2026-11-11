<section class="admin-card">
    <h1>Users</h1>
    <p>Registered accounts and orders placed using their checkout email. Orders without a matching account remain in <a href="/admin/orders">Orders</a>.</p>
    <form method="get" class="admin-form" action="/admin/users">
        <label for="users-search">Find a user by name or email</label>
        <input id="users-search" type="search" name="q" value="<?= e($query) ?>" maxlength="150">
        <button class="btn btn-primary">Search</button>
        <?php if ($query !== ''): ?><a class="btn btn-outline" href="/admin/users">Clear</a><?php endif; ?>
    </form>
    <p><?= $count ?> matching <?= $count === 1 ? 'account' : 'accounts' ?> · All times are India Standard Time</p>
    <?php foreach ($users as $user): ?>
        <details class="admin-card">
            <summary><strong><?= e($user['name'] ?: ($user['email'] ?: 'Unnamed user')) ?></strong>
                <?php if ($user['name'] && $user['email']): ?> · <?= e($user['email']) ?><?php endif; ?>
                · <?= e(ucfirst($user['role'] ?: 'Unknown role')) ?> · <?= count($user['orders']) ?> orders
                <?php if ($latest = ($user['orders'][0]['date'] ?? null)): ?> · Latest: <time datetime="<?= e($latest->format(DATE_ATOM)) ?>"><?= e($latest->format('d M Y, g:i a')) ?></time><?php endif; ?>
            </summary>
            <?php if (!$user['orders']): ?><p>No matching orders.</p><?php else: ?>
            <div class="table-wrap" role="region" aria-label="Order history" tabindex="0">
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
        </details>
    <?php endforeach; ?>
    <?php if (!$users): ?><p>No users match this search.</p><?php endif; ?>
    <?php if ($pages > 1): ?>
        <nav aria-label="Users pages">
            <?php if ($page > 1): ?><a class="btn btn-outline" href="?<?= e(http_build_query(['q'=>$query, 'page'=>$page-1])) ?>">Previous</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-outline" href="?<?= e(http_build_query(['q'=>$query, 'page'=>$page+1])) ?>">Next</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
