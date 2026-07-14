<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">
            <h1>My Consultation Bookings</h1>
            <?php if(empty($bookings)): ?>
                <div style="text-align:center; padding:var(--space-2xl);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Bookings Yet</h3>
                    <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">Request a private appointment with one of our consultants.</p>
                    <a href="/consult" class="btn btn-primary">Browse Consultants</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Preferred schedule</th><th>Consultant</th><th>Phone</th><th>Notes</th><th>Status</th><th>Review</th></tr></thead>
                        <tbody>
                        <?php foreach($bookings as $booking): ?>
                            <?php $status = $booking['status'] ?? ''; ?>
                            <tr>
                                <td><?= e(trim(($booking['date'] ?? '') . ' ' . ($booking['time'] ?? ''))) ?></td>
                                <td><?= e($booking['astrologer_name'] ?? $booking['astrologer_slug'] ?? '') ?></td>
                                <td><?= e($booking['phone'] ?? '') ?></td>
                                <td><?= e($booking['notes'] ?? '') ?></td>
                                <td>
                                    <span class="badge badge--<?= $status === 'confirmed' ? 'success' : ($status === 'payment_pending' ? 'warning' : 'default') ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
                                </td>
                                <td>
                                    <?php if(in_array($status, ['session_ended', 'completed'], true)): ?>
                                        <?php $reviewRowId = $booking['id'] ?? bin2hex(random_bytes(4)); ?>
                                        <form class="review-inline-form" action="/reviews/astrologer" method="post">
                                            <input type="hidden" name="target_type" value="astrologer">
                                            <input type="hidden" name="target_slug" value="<?= e($booking['astrologer_slug'] ?? '') ?>">
                                            <input type="hidden" name="source_id" value="<?= e($booking['id'] ?? '') ?>">
                                            <input type="hidden" name="redirect" value="/account/dashboard/sessions">
                                            <div class="star-rating-input" aria-label="Rate astrologer out of 5">
                                                <?php for($i=5;$i>=1;$i--): ?>
                                                    <input id="astro-<?= e($reviewRowId) ?>-<?= $i ?>" type="radio" name="rating" value="<?= $i ?>" required>
                                                    <label for="astro-<?= e($reviewRowId) ?>-<?= $i ?>" title="<?= $i ?> stars">★</label>
                                                <?php endfor; ?>
                                            </div>
                                            <textarea name="review" placeholder="Write a short review"></textarea>
                                            <button type="submit" class="btn btn-sm btn-primary">Submit Review</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--color-text-muted); font-size:0.8rem;">Available after session ends</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
