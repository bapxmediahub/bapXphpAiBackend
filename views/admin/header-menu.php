<section class="admin-card" aria-labelledby="header-menu-options">
    <h2 id="header-menu-options">Public header links</h2>
    <p>Choose pages in display order. Leave a position empty to omit it. The home logo, account links, and the Search and Cart icons are managed separately.</p>
    <?php if (isset($_SESSION['header_menu_message'])): ?>
        <p role="status"><?= e($_SESSION['header_menu_message']) ?></p>
        <?php unset($_SESSION['header_menu_message']); ?>
    <?php endif; ?>
    <form class="admin-form" method="post" action="/admin/appearance/menu">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="revision" value="<?= (int)$menu['revision'] ?>">
        <?php for ($i=0; $i<\App\Services\SettingsService::HEADER_MENU_LIMIT; $i++): ?>
            <label>Position <?= $i+1 ?>
                <select name="positions[]">
                    <option value="">No link</option>
                    <?php if (isset($menu['items'][$i]) && !isset($targets[$menu['items'][$i]])): ?>
                        <option value="<?= e($menu['items'][$i]) ?>" selected>Unavailable page · <?= e(substr($menu['items'][$i], 3)) ?></option>
                    <?php endif; ?>
                    <?php foreach ($targets as $id=>$link): ?>
                        <option value="<?= e($id) ?>" <?= ($menu['items'][$i] ?? '') === $id ? 'selected' : '' ?>><?= e($link['label']) ?><?= isset($link['module']) && !$modules[$link['module']] ? ' (module off)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endfor; ?>
        <p>Published pages appear by title. Withheld pages stay hidden until published again. Shop and Blog follow their site module settings.</p>
        <button class="btn btn-primary">Save header menu</button>
    </form>
    <p><a href="/">View the public header</a></p>
</section>
