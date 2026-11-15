<?php
$helpCategories=$helpCategories??[];$guide=$guide??null;$guides=$guides??[];
$query=(string)($query??'');$category=(string)($category??'');
$helpUrl=static function(string $selected,string $search=''):string {
    $params=array_filter(['category'=>$selected,'q'=>$search],static fn($value):bool=>$value!=='');
    return '/admin/docs'.($params===[]?'':'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986));
};
?>
<section class="admin-help" aria-labelledby="admin-help-title">
<?php if($guide===null): ?>
    <header class="admin-help__intro">
        <span class="eyebrow">OWNER WORKSPACE · HOW-TO GUIDES</span>
        <h2 id="admin-help-title">What would you like to do?</h2>
        <p>Find the right steps for the screen you are using. These guides explain real controls, what to verify, and when to stop and ask for help.</p>
    </header>
    <form class="admin-help__search" action="/admin/docs" method="get" role="search" aria-label="Search operator guides">
        <?php if($category!==''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
        <label for="admin-help-query">Search guides</label>
        <div><input type="search" id="admin-help-query" name="q" maxlength="80" value="<?= e($query) ?>" placeholder="Try orders, email, images or agent history">
            <button class="btn btn-primary" type="submit">Search</button></div>
    </form>
    <nav class="admin-help__categories" aria-label="Guide categories">
        <a href="<?= e($helpUrl('',$query)) ?>"<?= $category===''?' aria-current="page"':'' ?>>All guides</a>
        <?php foreach($helpCategories as $key=>$label): ?>
            <a href="<?= e($helpUrl($key,$query)) ?>"<?= $category===$key?' aria-current="page"':'' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-help__results">
        <p role="status"><?= count($guides) ?> <?= count($guides)===1?'guide':'guides' ?><?= $query!==''?' matching “'.e($query).'”':'' ?><?= isset($helpCategories[$category])?' in '.e($helpCategories[$category]):'' ?>.</p>
        <?php if($query!==''||$category!==''): ?><a href="/admin/docs">Clear filters</a><?php endif; ?>
    </div>
    <?php if($guides===[]): ?>
    <div class="admin-help__empty"><h3>No guides match those filters</h3><p>Try a shorter task name, choose another category, or return to all guides. Search does not inspect customer records or private system files.</p><a class="btn btn-outline" href="/admin/docs">Show all guides</a></div>
    <?php else: ?>
    <div class="admin-help__grid">
        <?php foreach($guides as $item): ?>
        <a class="admin-help__card" href="/admin/docs/<?= e($item['slug']) ?>">
            <span class="admin-help__category"><?= e($helpCategories[$item['category']]??'Guide') ?></span>
            <h3><?= e($item['title']) ?></h3><p><?= e($item['summary']) ?></p>
            <span class="admin-help__card-footer"><?= (int)$item['reading_minutes'] ?> min read <span aria-hidden="true">↗</span></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <footer class="admin-help__note"><strong>Before you change anything</strong><p>A guide is not a completed action. Use the normal form, check the saved result, and never repeat a payment or uncertain operation just because a page was slow. Available sections depend on the site's enabled modules.</p></footer>
<?php else: ?>
    <a class="admin-help__back" href="/admin/docs">← All guides</a>
    <header class="admin-help__intro">
        <span class="eyebrow"><?= e($helpCategories[$guide['category']]??'Guide') ?> · <?= (int)$guide['reading_minutes'] ?> MIN READ</span>
        <h2 id="admin-help-title"><?= e($guide['title']) ?></h2><p><?= e($guide['summary']) ?></p>
    </header>
    <div class="admin-help__guide-layout">
        <aside class="admin-help__contents" aria-label="Guide navigation">
            <h3>In this guide</h3><nav aria-label="On this page"><ol>
                <?php foreach($guide['toc'] as $section): ?><li><a href="#<?= e($section['id']) ?>"><?= e($section['label']) ?></a></li><?php endforeach; ?>
            </ol></nav>
            <h3>Go to the workspace</h3><div class="admin-help__task-links">
                <?php foreach($guide['links'] as $link): ?><a href="<?= e($link['url']) ?>"><?= e($link['label']) ?> <span aria-hidden="true">↗</span></a><?php endforeach; ?>
            </div>
        </aside>
        <article class="admin-help__article" aria-label="<?= e($guide['title']) ?> instructions"><?= $guide['html'] ?></article>
    </div>
    <footer class="admin-help__note"><strong>Something does not match your screen?</strong><p>Keep the page name and a safe description of what happened for the development team. Do not share passwords, customer details or private exports, and do not attempt a destructive workaround.</p><a href="/admin/docs">Browse another guide</a></footer>
<?php endif; ?>
</section>
