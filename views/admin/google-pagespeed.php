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
        <?php if ($cooldown): ?><p role="status">A recent report is available below. Another request is allowed after ten minutes to respect provider quotas.</p><?php endif; ?>
        <p>PageSpeed results are requested only when you press Analyze. Google may rate-limit reports. Missing field data is displayed as unavailable, not as zero.</p>
    <?php endif; ?>
    <p><a href="/admin/google-reporting">Google Analytics and Search Console</a></p>
</section>

<?php if (is_array($record)): ?>
<section class="admin-card google-pagespeed" aria-labelledby="google-pagespeed-results">
    <h2 id="google-pagespeed-results">Last PageSpeed report</h2>
    <p>Requested <time datetime="<?= e(date(DATE_ATOM,$record['at'])) ?>"><?= e(date('d M Y, h:i A',$record['at'])) ?> IST</time>. The report is a point-in-time measurement; it does not establish Google Search ranking or continuous uptime.</p>
    <div class="google-pagespeed__results">
    <?php foreach (($record['results'] ?? []) as $result): ?>
        <?php if (!is_array($result)) continue; ?>
        <section class="google-pagespeed__result" aria-label="<?= e(ucfirst((string)($result['strategy'] ?? 'Unknown'))) ?> performance">
            <h3><?= e(ucfirst((string)($result['strategy'] ?? 'Unknown'))) ?></h3>
            <?php if (($result['status'] ?? '') === 'unavailable'): ?>
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
