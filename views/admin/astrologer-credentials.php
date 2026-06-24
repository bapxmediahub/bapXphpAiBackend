<div class="admin-card">
    <div style="display:flex;justify-content:space-between;gap:var(--space-md);align-items:flex-start;margin-bottom:var(--space-lg)">
        <div>
            <h2 style="margin:0 0 var(--space-xs)">Astrologer Login IDs</h2>
            <p style="margin:0;color:var(--color-text-muted)">Give each astrologer their username and temporary password. The password disappears after they replace it.</p>
        </div>
        <a class="btn btn-sm btn-ghost" href="/admin/astrologers">Profiles</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Astrologer</th>
                    <th>Username</th>
                    <th>Temporary Password</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= e($row['name']) ?></strong></td>
                    <td><code><?= e($row['username']) ?></code></td>
                    <td><?= $row['temporary_password'] !== '' ? '<code>'.e($row['temporary_password']).'</code>' : '<span style="color:var(--color-text-muted)">Hidden</span>' ?></td>
                    <td><?= e($row['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
