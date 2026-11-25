<section class="not-found-page">
    <div class="container">
        <div class="not-found-shell reveal">
            <div class="not-found-mark" aria-hidden="true">
                <span>404</span>
            </div>
            <div class="not-found-copy">
                <h1>Page not found</h1>
                <p class="lede">If you followed a link, it may be out of date. Choose a page below to keep browsing.</p>
                <nav class="not-found-actions" aria-label="Find another page">
                    <a href="/" class="btn btn-primary">Go to home</a>
                    <a href="/search" class="btn btn-outline">Search the site</a>
                    <?php if (module_on('shop')): ?><a href="/shop" class="btn btn-ghost">Shop</a><?php endif; ?>
                    <a href="/temples" class="btn btn-ghost">Temples</a>
                    <a href="/contact" class="btn btn-ghost">Contact</a>
                </nav>
            </div>
        </div>
    </div>
</section>
