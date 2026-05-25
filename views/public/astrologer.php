<h1><?= e($astrologer['name'] ?? 'Astrologer Profile') ?></h1>
<?php if(!$astrologer): ?>
    <p>Astrologer not found.</p>
<?php else: ?>
    <?php if(!empty($astrologer['photo_url'])): ?><img src="<?= e($astrologer['photo_url']) ?>" alt="<?= e($astrologer['name']) ?>"><?php endif; ?>
    <p><?= e($astrologer['description'] ?? '') ?></p>
    <p><strong>Languages:</strong> <?= e(implode(', ', $astrologer['languages'] ?? [])) ?></p>
    <p><strong>Experience:</strong> <?= e($astrologer['experience_years'] ?? 'N/A') ?> years</p>
    <p><strong>Speciality:</strong> <?= e($astrologer['speciality'] ?? 'General astrology') ?></p>
    <p><strong>Consultation price:</strong> ₹<?= e((string)($astrologer['price'] ?? 0)) ?></p>
    <p><strong>Modes:</strong> <?= e(implode(', ', $astrologer['modes'] ?? [])) ?></p>
    <form method="get"><label>Date <input type="date" name="date" value="<?= e($date) ?>"></label><button>Check slots</button></form>
    <h2>Available slots</h2>
    <?php if(!$slots): ?>
        <p>No slots available for this date.</p>
    <?php else: ?>
        <div class="slot-grid">
            <?php foreach($slots as $slot): ?>
                <form method="post" action="/appointments/book" class="slot-card">
                    <input type="hidden" name="astrologer_slug" value="<?= e($astrologer['slug']) ?>">
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <input type="hidden" name="time" value="<?= e($slot) ?>">
                    <label>Name <input type="text" name="customer_name" value="<?= e($_SESSION['user']['name'] ?? '') ?>" required></label>
                    <label>Email <input type="email" name="customer_email" value="<?= e($_SESSION['user']['email'] ?? '') ?>" required></label>
                    <label>Mode <select name="mode"><option value="in-person">In person</option><option value="remote">Remote / Google Meet</option></select></label>
                    <button><?= e($slot) ?> Book</button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
