<h1>My Orders</h1>
<?php if(empty($orders)): ?>
    <p>You have no orders yet.</p>
<?php else: ?>
    <table>
        <tr><th>Order</th><th>Status</th><th>Total</th><th>Email</th></tr>
        <?php foreach($orders as $order): ?>
            <tr>
                <td><?= e($order['id'] ?? '') ?></td>
                <td><?= e($order['status'] ?? 'pending') ?></td>
                <td>₹<?= e((string)($order['total'] ?? 0)) ?></td>
                <td><?= e($order['customer_email'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
