<link rel="stylesheet" href="/assets/css/shop-refinement.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shop-refinement.css') ?>">
<section class="section shop-section">
    <?php
        $cartQuantities = [];
        foreach ($_SESSION['cart'] ?? [] as $cartItem) {
            $cartQuantities[(string)($cartItem['slug'] ?? '')] = (int)($cartItem['qty'] ?? 0);
        }
        $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
        $categoryLabels = ['' => 'All products'];
        foreach ($categories as $cat) {
            if (!empty($cat['slug'])) $categoryLabels[$cat['slug']] = $cat['name'] ?? 'Category';
        }
        foreach ($shopCollections as $collection) {
            $categoryLabels[$collection['slug']] = $collection['name'];
        }
        $shopHeading = $selectedCollection['name'] ?? $categoryLabels[$category] ?? ($category ?: 'Shop');
        if ($category !== '' && !isset($categoryLabels[$category])) $categoryLabels[$category] = $shopHeading;
        $shopUrl = static function (string $category, string $search, string $sort): string {
            $query = http_build_query(array_filter(
                ['category' => $category, 'q' => $search, 'sort' => $sort],
                static fn(string $value): bool => $value !== ''
            ), '', '&', PHP_QUERY_RFC3986);
            return '/shop' . ($query === '' ? '' : '?' . $query);
        };
        $returnUrl = $shopUrl($category, $search, $sort);
        $hasFilters = $category !== '' || $search !== '' || $sort !== '';
    ?>
    <div class="shop-layout">
        <aside class="shop-sidebar">
            <nav class="shop-filters" aria-label="Shop categories">
                <h2>Categories</h2>
                <div class="filter-group">
                    <?php foreach ($categoryLabels as $slug => $label): ?>
                        <?php $active = $category === (string)$slug; ?>
                        <a href="<?= e($shopUrl((string)$slug, $search, $sort)) ?>" class="filter-chip<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </nav>
        </aside>
        <div class="shop-results">
            <header class="shop-toolbar">
                <h1><?= e($category === '' ? 'Shop' : $shopHeading) ?></h1>
                <p class="shop-toolbar__count"><?= count($items) ?> product<?= count($items) === 1 ? '' : 's' ?></p>
            </header>
            <form class="shop-discovery" action="/shop" method="get" aria-label="Find products">
                <label class="shop-discovery__search">
                    Search products
                    <input type="search" name="q" value="<?= e($search) ?>" maxlength="120" placeholder="Try pendant or rudraksha">
                </label>
                <label class="shop-discovery__category">
                    Category or collection
                    <select name="category">
                        <?php foreach ($categoryLabels as $slug => $label): ?>
                            <option value="<?= e((string)$slug) ?>"<?= $category === (string)$slug ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Sort by
                    <select name="sort">
                        <?php foreach ($sortOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn btn-primary" type="submit">Apply</button>
            </form>
            <?php if ($hasFilters): ?>
                <ul class="shop-applied" aria-label="Applied shop filters">
                    <?php if ($category !== ''): ?>
                        <li><a href="<?= e($shopUrl('', $search, $sort)) ?>" aria-label="Remove category filter"><?= e($shopHeading) ?> <span aria-hidden="true">×</span></a></li>
                    <?php endif; ?>
                    <?php if ($search !== ''): ?>
                        <li><a href="<?= e($shopUrl($category, '', $sort)) ?>" aria-label="Remove search filter">Search “<?= e($search) ?>” <span aria-hidden="true">×</span></a></li>
                    <?php endif; ?>
                    <?php if ($sort !== ''): ?>
                        <li><a href="<?= e($shopUrl($category, $search, '')) ?>" aria-label="Reset sorting"><?= e($sortOptions[$sort]) ?> <span aria-hidden="true">×</span></a></li>
                    <?php endif; ?>
                    <li><a class="shop-applied__clear" href="/shop">Clear all</a></li>
                </ul>
            <?php endif; ?>
            <?php if (empty($items)): ?>
                <div class="shop-empty panel">
                    <h2><?= $hasFilters ? 'No matching products' : 'No products available' ?></h2>
                    <p><?= $hasFilters ? 'Try another search or choose a different category.' : 'Check back soon for new products.' ?></p>
                    <?php if ($hasFilters): ?><a href="/shop" class="btn btn-primary">Browse all products</a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach($items as $item): ?>
                        <?php $hasOffer = !empty($item['offer_price']) && $item['offer_price'] < $item['price']; ?>
                        <?php
                            $itemSlug = trim((string)($item['slug'] ?? ''));
                            $productUrl = '/product/' . rawurlencode($itemSlug);
                            $isPurchasable = in_array((string)($item['stock_status'] ?? 'in_stock'), ['in_stock', 'active'], true);
                        ?>
                        <article class="product-card">
                            <a class="product-card__image" href="<?= e($productUrl) ?>" aria-label="View <?= e($item['name']) ?>">
                                <img src="<?= e(webp_src($item['image_url'] ?? placeholder_img($item['name']))) ?>" data-product-image-fallback="<?= e(placeholder_img($item['name'])) ?>" alt="<?= e($item['name']) ?>" decoding="async">
                                <?php if($hasOffer): ?>
                                    <span class="product-card__badge product-card__badge--sale">Sale</span>
                                <?php endif; ?>
                            </a>
                            <div class="product-card__body">
                                <?php if(!empty($item['category'])): ?>
                                    <span class="shop-product-category"><?= e($categoryLabels[$item['category']] ?? $item['category']) ?></span>
                                <?php endif; ?>
                                <h2><a class="product-card__title" href="<?= e($productUrl) ?>"><?= e($item['name']) ?></a></h2>
                                <p class="product-card__desc"><?= e(strip_tags($item['description'] ?? '')) ?></p>
                                <div class="product-card__price-row">
                                    <span class="price">₹<?= e((string)(($item['offer_price'] ?? 0) ?: ($item['price'] ?? 0))) ?></span>
                                    <?php if($hasOffer): ?>
                                        <span class="old-price">₹<?= e($item['price']) ?></span>
                                        <?php $pct = round((1 - $item['offer_price'] / ($item['price'] ?: 1)) * 100); ?>
                                        <span class="discount-pct">-<?= $pct ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-card__actions">
                                    <?php if($isPurchasable): ?>
                        <?php $inCart = (int)($cartQuantities[$itemSlug] ?? 0); ?>
                        <div class="product-purchase" data-cart-control data-slug="<?= e($itemSlug) ?>">
                            <form method="post" action="/cart/add">
                                <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="redirect" value="/checkout">
                                <button class="btn btn-sm btn-primary" type="submit">Buy Now</button>
                            </form>
                            <div class="product-cart-control">
                                <form method="post" action="/cart/add" data-cart-add <?= $inCart > 0 ? 'hidden' : '' ?>>
                                    <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                    <input type="hidden" name="redirect" value="<?= e($returnUrl) ?>">
                                    <button class="btn btn-sm btn-outline" type="submit">Add to Cart</button>
                                </form>
                                <div class="product-cart-control__stepper" data-cart-stepper <?= $inCart > 0 ? '' : 'hidden' ?>>
                                    <?php foreach (['dec' => '−', 'inc' => '+'] as $cartAction => $symbol): ?>
                                    <?php if ($cartAction === 'inc'): ?><output data-cart-quantity aria-live="polite" aria-label="Quantity in cart for <?= e($item['name']) ?>"><?= $inCart ?></output><?php endif; ?>
                                    <form method="post" action="/cart/update" data-cart-change>
                                        <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="<?= $cartAction ?>">
                                        <input type="hidden" name="redirect" value="<?= e($returnUrl) ?>">
                                        <button type="submit" aria-label="<?= $cartAction === 'dec' ? 'Decrease' : 'Increase' ?> cart quantity for <?= e($item['name']) ?>"><?= $symbol ?></button>
                                    </form>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                                    <?php else: ?>
                                    <span class="product-card__unavailable">Out of stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="container" style="margin-top:var(--space-2xl);">
        <div class="page-cta-card reveal">
            <div>
                <span class="page-cta-card__eyebrow">Need help?</span>
                <h3>Send a General Enquiry</h3>
                <p>Contact us with product questions, order support, temple guidance, or general store enquiries.</p>
            </div>
            <a class="btn btn-primary page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
        </div>
    </div>
</section>
