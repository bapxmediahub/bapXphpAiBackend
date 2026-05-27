<?php
$contactService = new \App\Services\ContactService();
$submissions = $contactService->all();
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if ($action === 'mark-read' && $id) {
    $contactService->updateStatus($id, 'read');
    header('Location: /admin/contact-submissions');
    exit;
} elseif ($action === 'delete' && $id) {
    $contactService->delete($id);
    header('Location: /admin/contact-submissions');
    exit;
}

$currentSubmission = null;
if ($action === 'view' && $id) {
    $currentSubmission = $contactService->find($id);
    if ($currentSubmission && ($currentSubmission['status'] ?? 'new') === 'new') {
        $contactService->updateStatus($id, 'read');
        $currentSubmission['status'] = 'read';
    }
}
?>

<div class="admin-content">
    <div class="admin-header">
        <h1>Contact Form Submissions</h1>
        <span class="badge badge--info"><?= count($submissions) ?> total</span>
    </div>

    <?php if($currentSubmission): ?>
        <div class="admin-card" style="margin-bottom:var(--space-xl);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-lg); flex-wrap:wrap; gap:var(--space-sm);">
                <h2 style="margin:0; font-family:var(--font-serif);"><?= e($currentSubmission['name'] ?? 'Unknown') ?></h2>
                <div style="display:flex; gap:var(--space-xs);">
                    <span class="badge badge--<?= ($currentSubmission['status'] ?? 'new') === 'new' ? 'warning' : 'success' ?>">
                        <?= e(ucfirst($currentSubmission['status'] ?? 'new')) ?>
                    </span>
                    <a href="/admin/contact-submissions" class="btn btn-sm btn-ghost">← Back to List</a>
                </div>
            </div>

            <div style="display:grid; gap:var(--space-md); margin-bottom:var(--space-lg);">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md);">
                    <div class="form-group">
                        <label>Email</label>
                        <div style="padding:var(--space-sm); background:var(--color-bg-alt); border-radius:var(--radius-sm);">
                            <a href="mailto:<?= e($currentSubmission['email']) ?>" style="color:var(--color-maroon);"><?= e($currentSubmission['email']) ?></a>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <div style="padding:var(--space-sm); background:var(--color-bg-alt); border-radius:var(--radius-sm);">
                            <?php if(!empty($currentSubmission['phone'])): ?>
                                <a href="tel:<?= e($currentSubmission['phone']) ?>" style="color:var(--color-maroon);"><?= e($currentSubmission['phone']) ?></a>
                            <?php else: ?>
                                <span style="color:var(--color-text-muted);">Not provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md);">
                    <div class="form-group">
                        <label>Subject</label>
                        <div style="padding:var(--space-sm); background:var(--color-bg-alt); border-radius:var(--radius-sm);">
                            <?= e(ucwords(str_replace('-', ' ', $currentSubmission['subject'] ?? 'general'))) ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Submitted On</label>
                        <div style="padding:var(--space-sm); background:var(--color-bg-alt); border-radius:var(--radius-sm);">
                            <?= e(date('M d, Y \a\t h:i A', $currentSubmission['created_at'] ?? time())) ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <div style="padding:var(--space-md); background:var(--color-bg-alt); border-radius:var(--radius-sm); white-space:pre-wrap; line-height:1.7;"><?= e($currentSubmission['message']) ?></div>
                </div>
            </div>

            <div style="display:flex; gap:var(--space-sm); justify-content:flex-end;">
                <a href="mailto:<?= e($currentSubmission['email']) ?>?subject=Re: <?= e($currentSubmission['subject'] ?? 'Your Inquiry') ?>" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Reply via Email
                </a>
                <a href="/admin/contact-submissions?action=delete&id=<?= e($id) ?>" class="btn btn-outline" onclick="return confirm('Delete this submission?');">
                    Delete
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if(empty($submissions)): ?>
        <div class="admin-card" style="text-align:center; padding:var(--space-3xl);">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Submissions Yet</h3>
            <p style="color:var(--color-text-muted); margin:0;">Contact form submissions will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap admin-card">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($submissions as $submission): ?>
                    <tr>
                        <td>
                            <span class="badge badge--<?= ($submission['status'] ?? 'new') === 'new' ? 'warning' : 'success' ?>">
                                <?= e(ucfirst($submission['status'] ?? 'new')) ?>
                            </span>
                        </td>
                        <td><strong><?= e($submission['name'] ?? 'Unknown') ?></strong></td>
                        <td>
                            <a href="mailto:<?= e($submission['email'] ?? '') ?>" style="color:var(--color-maroon);">
                                <?= e($submission['email'] ?? 'N/A') ?>
                            </a>
                        </td>
                        <td><?= e(ucwords(str_replace('-', ' ', $submission['subject'] ?? 'general'))) ?></td>
                        <td style="white-space:nowrap;"><?= e(date('M d, Y', $submission['created_at'] ?? time())) ?></td>
                        <td>
                            <div style="display:flex; gap:var(--space-xs);">
                                <a href="/admin/contact-submissions?action=view&id=<?= e($submission['id']) ?>" class="btn btn-sm btn-ghost">View</a>
                                <?php if(($submission['status'] ?? 'new') === 'new'): ?>
                                    <a href="/admin/contact-submissions?action=mark-read&id=<?= e($submission['id']) ?>" class="btn btn-sm btn-outline">Mark Read</a>
                                <?php endif; ?>
                                <a href="/admin/contact-submissions?action=delete&id=<?= e($submission['id']) ?>" class="btn btn-ghost" style="color:var(--color-error);" onclick="return confirm('Delete this submission?');">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
