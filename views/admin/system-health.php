<?php
$health=$runtimeHealth;$php=$health['php'];$db=$health['database'];$extensions=$health['extensions'];
$labels=['meets_requirement'=>'Meets requirement','unsupported_prerelease'=>'Prerelease — stable release required','below_minimum'=>'Below required version','unavailable'=>'Unavailable — not verified','engine_unknown'=>'Database engine not identified','engine_mismatch'=>'Different database engine','missing_required_extensions'=>'Required extensions missing'];
$statusLabel=static fn(string $value):string=>$labels[$value]??'Not verified';
?>
<section class="runtime-health" aria-labelledby="runtime-health-title" data-runtime-health>
    <header class="runtime-health__intro">
        <span class="eyebrow">NATIVE PLATFORM · READ-ONLY DIAGNOSTICS</span>
        <h2 id="runtime-health-title">System Health</h2>
        <p>Check the PHP process serving this page and the configured business database. These checks do not install software, migrate data, rotate credentials or change checkout.</p>
    </header>
    <div class="runtime-health__summary" data-requirements-met="<?= $health['requirements_met']?'true':'false' ?>">
        <strong><?= $health['requirements_met']?'Declared runtime requirements are met':'Runtime requirements need attention' ?></strong>
        <p>Target: <strong>PHP 8.3 or newer</strong>, <strong>MySQL 8.4 or newer</strong>, and the listed required extensions.</p>
        <?php if(!$health['requirements_met']): ?><p>An unmet target is not a claim that the website is offline. Preserve the running site and investigate the specific finding before changing hosting or database settings.</p><?php endif; ?>
    </div>
    <div class="runtime-health__grid">
        <section class="admin-card" aria-labelledby="runtime-php-title">
            <h3 id="runtime-php-title">PHP runtime</h3>
            <p class="runtime-health__version" data-php-version><?= e($php['version']) ?></p>
            <p data-php-status><?= e($statusLabel($php['status'])) ?></p>
            <dl><div><dt>Required minimum</dt><dd><?= e($php['minimum']) ?></dd></div><div><dt>Observed process</dt><dd><?= e($php['sapi']) ?></dd></div></dl>
            <p class="runtime-health__caption">This is the web process running this page. A developer terminal may use a different PHP version.</p>
        </section>
        <section class="admin-card" aria-labelledby="runtime-db-title">
            <h3 id="runtime-db-title">Business database</h3>
            <p class="runtime-health__version"><span data-db-engine><?= e($db['engine']) ?></span> <span data-db-version><?= e($db['version']) ?></span></p>
            <p data-db-status><?= e($statusLabel($db['status'])) ?></p>
            <dl><div><dt>Required engine</dt><dd>MySQL</dd></div><div><dt>Required minimum</dt><dd><?= e($db['minimum']) ?></dd></div></dl>
            <p class="runtime-health__caption">A higher MariaDB version number is not MySQL 8.4 compliance. Unknown or unavailable information is not a pass.</p>
        </section>
        <section class="admin-card runtime-health__extensions" aria-labelledby="runtime-extensions-title">
            <h3 id="runtime-extensions-title">Required PHP extensions</h3>
            <ul><?php foreach($extensions['required'] as $extension): ?><li><code><?= e($extension) ?></code><span><?= in_array($extension,$extensions['missing'],true)?'Missing':'Loaded' ?></span></li><?php endforeach; ?></ul>
            <p class="runtime-health__caption">Only the required extension names are shown; this is not a public phpinfo dump.</p>
        </section>
    </div>
    <section class="admin-card" aria-labelledby="runtime-next-title">
        <h3 id="runtime-next-title">What to do with a finding</h3>
        <p>For an older PHP version or a missing extension, ask the hosting operator to test the change before deployment. For a different database engine, prepare an approved destination, backup, restore test, data/query checks and rollback plan before any cutover. This screen performs none of those actions.</p>
        <p>Version checks do not prove backup recoverability, payment correctness, email inbox delivery, security against every attack or overall feature completeness.</p>
        <div class="runtime-health__actions"><a class="btn btn-outline" href="/admin/system-health">Refresh checks</a><a class="btn btn-ghost" href="/admin/docs/settings">Read the settings guide</a></div>
        <p class="runtime-health__caption">Observed at <time datetime="<?= e($health['observed_at']) ?>"><?= e($health['observed_at']) ?></time>. No database host, username, password, private filesystem path or raw provider exception is displayed.</p>
    </section>
</section>
