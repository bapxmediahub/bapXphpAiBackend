<h1>Astrologer Enquiry</h1>
<?php if(empty($items)): ?>
	<p>No astrologers are available at the moment. Add profiles from the admin dashboard.</p>
<?php else: ?>
	<div class="grid">
		<?php foreach($items as $item): ?>
			<article class="panel">
				<?php if(!empty($item['photo_url'])): ?><img src="<?= e($item['photo_url']) ?>" alt="<?= e($item['name'] ?? 'Astrologer') ?>"><?php endif; ?>
				<h2><?= e($item['name'] ?? 'Astrologer') ?></h2>
				<p><?= e($item['description'] ?? '') ?></p>
				<p><strong>Speciality:</strong> <?= e($item['speciality'] ?? 'General astrology') ?></p>
				<p><strong>Languages:</strong> <?= e(implode(', ', $item['languages'] ?? [])) ?></p>
				<p><strong>Experience:</strong> <?= e($item['experience_years'] ?? 'N/A') ?> years</p>
				<p><strong>Consultation:</strong> ₹<?= e((string)($item['price'] ?? 0)) ?></p>
				<?php if(!empty($item['slug'])): ?><a href="/astrologers/<?= e($item['slug']) ?>">View availability</a><?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
