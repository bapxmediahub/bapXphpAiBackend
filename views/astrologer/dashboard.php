<section class="section workspace-page">
    <div class="workspace-heading">
        <div>
            <span class="eyebrow">Astrologer Workspace</span>
            <h1><?= e($profile['name'] ?? 'Consultant') ?></h1>
            <p>Manage incoming consultation requests and open active customer rooms.</p>
        </div>
        <span class="status-pill"><?= e(ucfirst($profile['availability_status'] ?? 'offline')) ?></span>
    </div>
    <div class="workspace-metrics">
        <article><strong><?= count($sessions) ?></strong><span>Total sessions</span></article>
        <article><strong><?= count(array_filter($sessions, fn($s)=>($s['status']??'')==='requested')) ?></strong><span>Waiting</span></article>
        <article><strong><?= count(array_filter($sessions, fn($s)=>in_array(($s['status']??''),['accepted','active'],true))) ?></strong><span>Active</span></article>
    </div>
    <form method="post" action="/astrologer/availability" class="workspace-availability">
        <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label for="availability-status">Availability</label>
        <select id="availability-status" name="availability_status">
            <?php foreach(['available'=>'Available for sessions','busy'=>'Busy - allow waitlist','offline'=>'Offline'] as $value=>$label): ?>
                <option value="<?= e($value) ?>" <?= ($profile['availability_status'] ?? 'offline') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Update</button>
    </form>
    <div class="panel workspace-list">
        <div class="workspace-list__head"><h2>Consultations</h2><a href="/astrologer/change-password">Change password</a></div>
        <?php if(!$sessions): ?><p class="empty-state">No consultation requests assigned yet.</p><?php endif; ?>
        <?php foreach(array_reverse($sessions) as $session): ?>
            <a class="workspace-session" href="/consultation/<?= e($session['id'] ?? '') ?>">
                <span><strong><?= e($session['customer_name'] ?? 'Customer') ?></strong><small><?= e($session['session_type'] ?? ucfirst($session['mode'] ?? 'Consultation')) ?></small></span>
                <span><small><?= e(substr((string)($session['created_at'] ?? ''),0,16)) ?></small><b><?= e(ucfirst(str_replace('_',' ',(string)($session['status']??'requested')))) ?></b></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
