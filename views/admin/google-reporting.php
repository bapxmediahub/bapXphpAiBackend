<section class="admin-card" aria-labelledby="google-reporting-title">
    <h2 id="google-reporting-title"><?= $connected ? 'Reporting properties' : 'Connect a Google account' ?></h2>
    <p><a href="/admin#web-analytics">View dashboard reports</a></p>
    <?php if (!$connected): ?>
        <p>Connect the Google account that can read your GA4 property and Search Console site.</p>
        <p>In Google Cloud, enable the Google Analytics Admin API, Google Analytics Data API and Search Console API. Register this authorized redirect URI on the web OAuth client.</p>
        <p><code><?= e($callback) ?></code></p>
        <?php if ($configured): ?>
            <form method="post" action="/admin/google-reporting/connect"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn-primary">Connect Google</button></form>
        <?php else: ?>
            <p><a class="btn btn-outline" href="/admin/integrations#google-oauth">Set up Google OAuth credentials</a></p>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($message !== ''): ?><p role="status"><?= e($message) ?></p><?php endif; ?>
        <?php if ($choices): ?>
            <form class="admin-form" method="post" action="/admin/google-reporting/select">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="generation" value="<?= e($generation) ?>">
            <input type="hidden" name="revision" value="<?= e($revision) ?>">
                <?php foreach (['property'=>'GA4 property','site'=>'Search Console site'] as $key=>$label): ?>
                    <?php if (isset($choices[$key]['error'])): ?>
                        <p role="status"><strong><?= e($label) ?></strong> · <?= e($choices[$key]['error']) ?></p>
                    <?php else: ?>
                        <label><?= e($label) ?><select name="<?= e($key) ?>">
                            <option value="">No selection</option>
                            <?php foreach ($choices[$key]['data'] as $id=>$name): ?><option value="<?= e($id) ?>" <?= $id === ($key === 'property' ? $property : $site) ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                        </select></label>
                        <?php if (empty($choices[$key]['data'])): ?><p>Google returned no accessible <?= e($label) ?> for this account.</p><?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (isset($choices['property']['data']) || isset($choices['site']['data'])): ?><button class="btn btn-primary">Save reporting properties</button><?php endif; ?>
            </form>
        <?php endif; ?>
        <form method="post" action="/admin/google-reporting/disconnect">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="generation" value="<?= e($generation) ?>">
            <input type="hidden" name="revision" value="<?= e($revision) ?>">
            <p>Disconnect revokes the Google authorization used by this reporting connection.</p>
            <button class="btn btn-outline">Disconnect Google reporting</button>
        </form>
    <?php endif; ?>
</section>
