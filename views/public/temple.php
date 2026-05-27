<?php
$temple = (new \App\Services\TempleService())->findBySlug($slug);
?>

<?php if(!$temple): ?>
    <div class="section" style="text-align:center; padding:var(--space-4xl) var(--space-md);">
        <div style="font-size:3rem; margin-bottom:var(--space-md);">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto;"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
        </div>
        <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Temple Not Found</h1>
        <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">The temple you're looking for doesn't exist.</p>
        <a href="/temples" class="btn btn-primary">View All Temples</a>
    </div>
<?php else: ?>
    <section class="section" style="padding-top:var(--space-xl);">
        <div class="container container--narrow">
            <div style="text-align:center; margin-bottom:var(--space-2xl);">
                <div style="background:var(--color-bg-alt); border-radius:var(--radius-lg); margin-bottom:var(--space-lg); height:250px; display:flex; align-items:center; justify-content:center;">
                    <?php if(!empty($temple['image_url'])): ?>
                        <img src="<?= e($temple['image_url']) ?>" alt="<?= e($temple['name']) ?>" style="width:100%; height:100%; object-fit:cover; border-radius:var(--radius-lg);">
                    <?php else: ?>
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
                    <?php endif; ?>
                </div>
                <span class="eyebrow">Sacred Space</span>
                <h1 style="font-family:var(--font-serif); margin:var(--space-sm) 0;"><?= e($temple['name']) ?></h1>
                <p class="lede" style="margin:0 auto var(--space-lg);"><?= e($temple['description']) ?></p>
            </div>
            <div class="panel">
                <div style="display:grid; gap:var(--space-md);">
                    <?php if(!empty($temple['address'])): ?>
                        <div style="display:flex; align-items:center; gap:var(--space-sm);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-maroon)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span><?= e($temple['address']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($temple['timings'])): ?>
                        <div style="display:flex; align-items:center; gap:var(--space-sm);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-maroon)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span><?= e($temple['timings']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($temple['pooja_types']) && is_array($temple['pooja_types'])): ?>
                        <div style="display:flex; align-items:flex-start; gap:var(--space-sm);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-maroon)" stroke-width="2" style="margin-top:2px;"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            <div>
                                <strong>Available Poojas:</strong>
                                <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs); margin-top:var(--space-xs);">
                                    <?php foreach($temple['pooja_types'] as $pooja): ?>
                                        <span class="badge badge--info"><?= e($pooja) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align:center; margin-top:var(--space-xl);">
                <a href="/contact" class="btn btn-primary">Visit Temple</a>
                <a href="/temples" class="btn btn-ghost" style="margin-left:var(--space-sm);">View All Temples</a>
            </div>
        </div>
    </section>
<?php endif; ?>