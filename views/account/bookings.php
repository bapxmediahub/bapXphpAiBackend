<h1>My Bookings</h1>
<?php if(empty($bookings)): ?>
    <p>You have no booking records yet.</p>
<?php else: ?>
    <table>
        <tr><th>Date</th><th>Time</th><th>Astrologer</th><th>Mode</th><th>Status</th><th>Meeting</th></tr>
        <?php foreach($bookings as $booking): ?>
            <tr>
                <td><?= e($booking['date'] ?? '') ?></td>
                <td><?= e($booking['time'] ?? '') ?></td>
                <td><?= e($booking['astrologer_name'] ?? $booking['astrologer_slug'] ?? '') ?></td>
                <td><?= e($booking['mode'] ?? '') ?></td>
                <td><?= e($booking['status'] ?? '') ?></td>
                <td><?php if(!empty($booking['meeting_link'])): ?><a href="<?= e($booking['meeting_link']) ?>" target="_blank">Join</a><?php else: ?>N/A<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
