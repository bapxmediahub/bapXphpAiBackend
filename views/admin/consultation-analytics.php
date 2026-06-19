<div class="admin-metrics-grid">
<?php foreach(['total_sessions'=>'Total Sessions','accepted_sessions'=>'Accepted','completed_sessions'=>'Completed','message_count'=>'Messages','credits_spent'=>'Credits Spent','average_duration_seconds'=>'Avg. Duration (sec)','average_response_seconds'=>'Avg. Response (sec)'] as $key=>$label): ?>
    <article class="admin-card"><span style="color:var(--color-text-muted);font-size:.75rem;text-transform:uppercase"><?= e($label) ?></span><strong style="display:block;font-family:var(--font-serif);font-size:2rem;color:var(--color-maroon);margin-top:var(--space-xs)"><?= e((string)($metrics[$key]??0)) ?></strong></article>
<?php endforeach; ?>
</div>
<div class="admin-card" style="margin-top:var(--space-lg)"><h2 style="font-size:1.05rem">Analytics Contract</h2><p style="color:var(--color-text-muted);margin-bottom:0">Metrics are derived from consultation sessions, messages, timestamps, durations, and wallet credits. They update as astrologers accept, start, and complete sessions.</p></div>
