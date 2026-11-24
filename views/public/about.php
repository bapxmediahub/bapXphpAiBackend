<link rel="stylesheet" href="/assets/css/about-editorial.css?v=<?= filemtime(app_path('assets/css/about-editorial.css')) ?>">
<div class="about-editorial" data-about-editorial>
    <header class="about-hero">
        <div class="container about-hero__inner">
            <div class="about-hero__copy">
                <p class="about-kicker">About <?= e($seo['og_site_name']) ?></p>
                <h1><?= e($about['headline']) ?></h1>
                <p class="about-hero__lede"><?= e($about['intro']) ?></p>
                <a class="about-hero__link" href="#our-story">Discover our story <span aria-hidden="true">↓</span></a>
            </div>
            <?php if (!empty($about['hero_image'])): $image = $about['hero_image']; ?>
                <div class="about-hero__visual">
                    <img src="<?= e($image['src']) ?>" alt="<?= e($image['alt']) ?>" width="<?= (int)$image['width'] ?>" height="<?= (int)$image['height'] ?>" fetchpriority="high" decoding="async">
                </div>
            <?php endif; ?>
        </div>
    </header>

    <section class="about-section" id="our-story" aria-labelledby="about-story-title">
        <div class="container about-story">
            <?php if (!empty($about['story']['image'])): $image = $about['story']['image']; ?>
                <figure class="about-story__figure">
                    <img src="<?= e($image['src']) ?>" alt="<?= e($image['alt']) ?>" width="<?= (int)$image['width'] ?>" height="<?= (int)$image['height'] ?>" loading="lazy" decoding="async">
                    <?php if (!empty($about['story']['caption'])): ?>
                        <figcaption><?= e($about['story']['caption']) ?></figcaption>
                    <?php endif; ?>
                </figure>
            <?php endif; ?>
            <div class="about-story__copy">
                <p class="about-kicker"><?= e($about['story']['eyebrow']) ?></p>
                <h2 id="about-story-title"><?= e($about['story']['title']) ?></h2>
                <?php foreach ($about['story']['paragraphs'] as $paragraph): ?>
                    <p><?= e($paragraph) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($aboutPaths): ?>
    <section class="about-section about-explore" aria-labelledby="about-explore-title">
        <div class="container">
            <p class="about-kicker">Explore</p>
            <h2 id="about-explore-title"><?= e($about['explore_title']) ?></h2>
            <div class="about-pathways">
                <?php foreach ($aboutPaths as $index => $entry): ?>
                    <article class="about-pathway">
                        <span class="about-pathway__number" aria-hidden="true"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <h3><?= e($entry['title']) ?></h3>
                        <p><?= e($entry['description']) ?></p>
                        <a href="<?= e($entry['path']) ?>"><?= e($entry['label']) ?> <span aria-hidden="true">↗</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="about-section about-contact" aria-labelledby="about-contact-title">
        <div class="container">
            <div class="about-contact__grid">
                <div>
                    <p class="about-kicker">Here to help</p>
                    <h2 id="about-contact-title"><?= e($about['contact_title']) ?></h2>
                    <p><?= e($about['contact_intro']) ?></p>
                </div>
                <address class="about-contact__details">
                    <?php foreach ($publicContact['phones'] as $phone): ?>
                        <a href="tel:<?= e($phone['number']) ?>"><?= e($phone['label']) ?></a>
                    <?php endforeach; ?>
                    <a href="mailto:<?= e($publicContact['email']) ?>"><?= e($publicContact['email']) ?></a>
                    <?php foreach ($publicContact['hours'] as $slot): ?>
                        <span><?= e($slot['days']) ?> · <?= e($slot['hours']) ?></span>
                    <?php endforeach; ?>
                </address>
            </div>
            <div class="page-cta-card about-contact__cta">
                <div>
                    <span class="page-cta-card__eyebrow">Send a message</span>
                    <h3>Send a General Enquiry</h3>
                    <p>Tell us what you need and how we can help.</p>
                </div>
                <a class="btn btn-primary page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
            </div>
        </div>
    </section>
</div>
<?= $seo['about_json_ld'] ?? '' ?>
