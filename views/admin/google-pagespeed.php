<section class="admin-card google-pagespeed" aria-labelledby="google-pagespeed-heading">
    <h1 id="google-pagespeed-heading">PageSpeed Insights</h1>
    <p>Measure the configured public website on mobile and desktop using Google's PageSpeed Insights API. This is a native website feature; no WordPress plugin or additional Google reporting permissions are required.</p>
    <?php if ($notice !== ''): ?>
        <p role="status"><?= e($notice) ?></p>
    <?php else: ?>
        <p><strong>Website:</strong> <code><?= e($origin) ?>/</code></p>
        <form method="post" action="/admin/google-pagespeed/run">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="btn btn-primary" type="submit" <?= $cooldown ? 'disabled' : '' ?>>Analyze mobile and desktop</button>
        </form>
        <?php if ($cooldown): ?><p role="status">A recent attempt has claimed the shared site cooldown. Another request is allowed after ten minutes to respect provider quotas.</p><?php endif; ?>
        <p>PageSpeed results are requested only when you press Analyze. Google may rate-limit reports. Missing field data is displayed as unavailable, not as zero. A dedicated PageSpeed API key can be configured in Integrations; it is never sent to the browser.</p>
    <?php endif; ?>
    <p><a href="/admin/google-reporting">Google Analytics and Search Console</a></p>
</section>

<?php if (is_array($record)): ?>
<section class="admin-card google-pagespeed" aria-labelledby="google-pagespeed-results">
    <h2 id="google-pagespeed-results">Latest PageSpeed attempt</h2>
    <?php if (!empty($record['running'])): ?><p role="status">A report attempt was claimed; the result has not been confirmed yet.</p><?php endif; ?>
    <p>Requested <time datetime="<?= e(date(DATE_ATOM,$record['at'])) ?>"><?= e(date('d M Y, h:i A',$record['at'])) ?> IST</time>. The report is a point-in-time measurement; it does not establish Google Search ranking or continuous uptime.</p>
    <div class="google-pagespeed__results">
    <?php foreach (($record['results'] ?? []) as $result): ?>
        <?php if (!is_array($result)) continue; ?>
        <section class="google-pagespeed__result" aria-label="<?= e(ucfirst((string)($result['strategy'] ?? 'Unknown'))) ?> performance">
            <h3><?= e(ucfirst((string)($result['strategy'] ?? 'Unknown'))) ?></h3>
            <?php if (in_array($result['status'] ?? '', ['unavailable','not_attempted'], true)): ?>
                <p role="status"><?= e((string)($result['message'] ?? 'PageSpeed is unavailable.')) ?></p>
            <?php else: ?>
                <p class="google-pagespeed__score">
                    <?php if (($result['performance_score'] ?? null) === null): ?>
                        Performance score unavailable
                    <?php else: ?>
                        <strong><?= (int)$result['performance_score'] ?>/100</strong> performance
                    <?php endif; ?>
                </p>
                <h4>Lab measurements</h4>
                <dl>
                <?php foreach (($result['lab'] ?? []) as $item): ?>
                    <div><dt><?= e((string)$item['name']) ?></dt><dd><?= $item['display'] === null ? 'Unavailable' : e((string)$item['display']) ?></dd></div>
                <?php endforeach; ?>
                </dl>
                <h4>Real-user field data</h4>
                <?php if (($result['field_status'] ?? '') !== 'reported'): ?>
                    <p>Not enough field data, or the provider did not supply it.</p>
                <?php else: ?>
                    <dl>
                    <?php foreach (($result['field'] ?? []) as $item): ?>
                        <div><dt><?= e((string)$item['name']) ?></dt>
                            <dd><?= $item['display'] === null ? 'Unavailable' : e((string)$item['display']) ?>
                                <?= $item['category'] === null ? '' : '('.e((string)$item['category']).')' ?></dd></div>
                    <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if (!empty($result['opportunities'])): ?>
                    <h4>Suggested improvements</h4>
                    <ul><?php foreach ($result['opportunities'] as $item): ?>
                        <li><?= e((string)$item['title']) ?> — approximately <?= (int)$item['savings_ms'] ?> ms potential lab savings</li>
                    <?php endforeach; ?></ul>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
$previous=[];
foreach (($record['last_good']??[]) as $strategy=>$entry) {
    if ($entry['at']!==($record['at']??null)) $previous[$strategy]=$entry;
}
?>
<?php if ($previous!==[]): ?>
<section class="admin-card google-pagespeed" aria-label="Previous successful PageSpeed reports">
    <h2>Previous successful measurements</h2>
    <p>Historical results, not a successful measurement from the latest attempt.</p>
    <dl>
    <?php foreach ($previous as $strategy=>$entry): ?>
        <dt><?= e(ucfirst($strategy)) ?> · <time datetime="<?= e(date(DATE_ATOM,$entry['at'])) ?>"><?= e(date('d M Y, h:i A',$entry['at'])) ?> IST</time></dt>
        <dd><?= (int)$entry['report']['performance_score'] ?>/100 performance
            <?php foreach (($entry['report']['lab']??[]) as $metric): ?>
                <br><?= e((string)$metric['name']) ?>: <?= $metric['display']===null?'Unavailable':e((string)$metric['display']) ?>
            <?php endforeach; ?>
        </dd>
    <?php endforeach; ?>
    </dl>
</section>
<?php endif; ?>
