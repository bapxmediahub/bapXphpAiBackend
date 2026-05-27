/**
 * Page Components for Sri Panchami Spiritual
 */

const { useState, useEffect } = React;

// ============================================
// Home Page
// ============================================
function HomePage() {
    const { addToCart, navigate } = useApp();
    const [data, setData] = useState({ products: [], categories: [], astrologers: [], temples: [] });
    const [loading, setLoading] = useState(true);
    
    useEffect(() => {
        API.get('/').then(setData).finally(() => setLoading(false));
    }, []);
    
    if (loading) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } }, 'Loading...');
    
    return React.createElement(React.Fragment, null,
        // Hero Section
        React.createElement('section', { className: 'home-hero' },
            React.createElement('div', { className: 'hero-copy' },
                React.createElement('span', { className: 'eyebrow' }, 'Blessings · Protection · Prosperity'),
                React.createElement('h1', null, 'Divine Grace.', React.createElement('br'), 'Timeless Protection.'),
                React.createElement('p', { className: 'lede' }, 'Authentic spiritual products, sacred jewelry, expert astrology and temple guidance to elevate your life.'),
                React.createElement('div', { className: 'hero-actions' },
                    React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/shop') }, 'Shop Spiritual Products'),
                    React.createElement('button', { className: 'btn btn-outline', onClick: () => navigate('/astrologers') }, 'Book Astrology')
                ),
                React.createElement('div', { className: 'hero-stats' },
                    React.createElement('div', null, React.createElement('div', { className: 'hero-stat-value' }, '500+'), React.createElement('div', { className: 'hero-stat-label' }, 'Happy Devotees')),
                    React.createElement('div', null, React.createElement('div', { className: 'hero-stat-value' }, '14+'), React.createElement('div', { className: 'hero-stat-label' }, 'Sacred Items')),
                    React.createElement('div', null, React.createElement('div', { className: 'hero-stat-value' }, '3'), React.createElement('div', { className: 'hero-stat-label' }, 'Expert Astrologers'))
                )
            ),
            React.createElement('div', { className: 'hero-deity' },
                React.createElement('img', { src: '/assets/images/varahi-amman.png', alt: 'Sri Maha Varahi Amman' })
            )
        ),
        
        // Trust Bar
        React.createElement('div', { className: 'trust-bar' },
            React.createElement('div', { className: 'trust-item' }, '🔒 Secure Payments'),
            React.createElement('div', { className: 'trust-item' }, '📦 Fast Delivery'),
            React.createElement('div', { className: 'trust-item' }, '✓ Authentic Products'),
            React.createElement('div', { className: 'trust-item' }, '✨ Blessed Items')
        ),
        
        // Categories Section
        React.createElement('section', { className: 'category-section section' },
            React.createElement('div', { className: 'section-header' },
                React.createElement('h2', { className: 'section-title' }, 'Shop by Category'),
                React.createElement('p', { className: 'lede' }, 'Curated collections for every spiritual need')
            ),
            React.createElement('div', { className: 'category-grid' },
                data.categories.map(cat => 
                    React.createElement('a', {
                        key: cat.slug,
                        className: 'category-card',
                        href: `/shop?category=${cat.slug}`,
                        onClick: (e) => { e.preventDefault(); navigate(`/shop?category=${cat.slug}`); }
                    },
                        React.createElement('div', { className: 'category-img-wrap' },
                            React.createElement('img', { src: cat.image_url || `https://placehold.co/120x120/fdfbf7/d4af37?text=${encodeURIComponent(cat.name)}`, alt: cat.name })
                        ),
                        React.createElement('h3', null, cat.name),
                        React.createElement('p', null, cat.description)
                    )
                )
            )
        ),
        
        // Featured Products
        React.createElement('section', { className: 'section' },
            React.createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2rem' } },
                React.createElement('h2', { className: 'section-title', style: { margin: 0 } }, 'Featured Products'),
                React.createElement('button', { className: 'btn btn-sm btn-ghost', onClick: () => navigate('/shop') }, 'View All')
            ),
            React.createElement('div', { className: 'product-grid' },
                data.products.slice(0, 5).map(product => 
                    React.createElement(ProductCard, { key: product.slug, product: product })
                )
            )
        ),
        
        // Temples Section
        data.temples.length > 0 && React.createElement('section', { className: 'section section--alt' },
            React.createElement('div', { className: 'section-header' },
                React.createElement('span', { className: 'eyebrow' }, 'Sacred Spaces · Divine Energy'),
                React.createElement('h2', { className: 'section-title' }, 'Our Temples'),
                React.createElement('p', { className: 'lede' }, 'Visit our sacred spaces for divine blessings and spiritual awakening.')
            ),
            React.createElement('div', { className: 'showcase-grid' },
                data.temples.slice(0, 3).map(temple =>
                    React.createElement('article', { key: temple.slug, className: 'showcase-card' },
                        React.createElement('div', { style: { background: 'var(--color-bg-alt)', borderRadius: 'var(--radius-md)', marginBottom: 'var(--space-md)', height: '160px', display: 'flex', alignItems: 'center', justifyContent: 'center' } },
                            temple.image_url 
                                ? React.createElement('img', { src: temple.image_url, alt: temple.name, style: { width: '100%', height: '100%', objectFit: 'cover', borderRadius: 'var(--radius-md)' } })
                                : React.createElement('span', { style: { fontSize: '2rem' } }, '🛕')
                        ),
                        React.createElement('h2', null, temple.name),
                        React.createElement('p', null, temple.description),
                        React.createElement('button', { className: 'btn btn-sm btn-primary', onClick: () => navigate(`/temples/${temple.slug}`) }, 'View Details')
                    )
                )
            ),
            React.createElement('div', { style: { textAlign: 'center', marginTop: '2rem' } },
                React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/temples') }, 'View All Temples')
            )
        ),
        
        // Astrology Section
        React.createElement('section', { className: 'section' },
            React.createElement('div', { className: 'section-header' },
                React.createElement('span', { className: 'eyebrow' }, 'Guidance · Clarity · Remedies'),
                React.createElement('h2', { className: 'section-title' }, 'Expert Astrology for a Better Tomorrow'),
                React.createElement('p', { className: 'lede' }, 'Consult experienced astrologers for accurate predictions, remedy guidance tailored to your life path.')
            ),
            React.createElement('div', { className: 'astrologer-grid', style: { marginBottom: '2rem' } },
                data.astrologers.slice(0, 3).map(astro =>
                    React.createElement('article', { key: astro.slug, className: 'astrologer-card' },
                        React.createElement('div', { className: 'astrologer-card__header' },
                            React.createElement('img', { className: 'astrologer-card__photo', src: astro.photo_url || 'https://placehold.co/100x100/fdfbf7/d4af37?text=Guru', alt: astro.name }),
                            React.createElement('div', null,
                                React.createElement('h3', { className: 'astrologer-card__name' }, astro.name),
                                React.createElement('p', { className: 'astrologer-card__speciality' }, astro.speciality || 'Vedic Astrology')
                            )
                        ),
                        React.createElement('div', { className: 'astrologer-card__body' },
                            React.createElement('div', { className: 'astrologer-card__stat' },
                                React.createElement('span', { className: 'astrologer-card__stat-label' }, 'Experience'),
                                React.createElement('span', { className: 'astrologer-card__stat-value' }, `${astro.experience_years || 'N/A'} yrs`)
                            ),
                            React.createElement('div', { className: 'astrologer-card__stat' },
                                React.createElement('span', { className: 'astrologer-card__stat-label' }, 'Languages'),
                                React.createElement('span', { className: 'astrologer-card__stat-value' }, (astro.languages || []).slice(0, 2).join(', '))
                            )
                        ),
                        React.createElement('div', { className: 'astrologer-card__footer' },
                            React.createElement('span', { className: 'astrologer-card__price' }, Utils.formatPrice(astro.price || 0)),
                            React.createElement('button', { className: 'btn btn-sm btn-outline', onClick: () => navigate(`/astrologers/${astro.slug}`) }, 'Book Now')
                        )
                    )
                )
            ),
            React.createElement('div', { style: { textAlign: 'center' } },
                React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/astrologers') }, 'Book Astrology Consultation')
            )
        ),
        
        // Why Choose Us
        React.createElement('section', { className: 'section section--alt' },
            React.createElement('div', { className: 'section-header' },
                React.createElement('h2', { className: 'section-title' }, 'Why Choose Us')
            ),
            React.createElement('div', { className: 'feature-strip' },
                React.createElement('article', { className: 'panel' },
                    React.createElement('span', { style: { fontSize: '2rem', display: 'block', marginBottom: 'var(--space-sm)' } }, '🛕'),
                    React.createElement('h3', null, 'Authentic'),
                    React.createElement('p', null, 'Genuine spiritual products sourced with devotion')
                ),
                React.createElement('article', { className: 'panel' },
                    React.createElement('span', { style: { fontSize: '2rem', display: 'block', marginBottom: 'var(--space-sm)' } }, '⭐'),
                    React.createElement('h3', null, 'Expert Guidance'),
                    React.createElement('p', null, 'Experienced astrologers with proven track record')
                ),
                React.createElement('article', { className: 'panel' },
                    React.createElement('span', { style: { fontSize: '2rem', display: 'block', marginBottom: 'var(--space-sm)' } }, '🔒'),
                    React.createElement('h3', null, 'Secure'),
                    React.createElement('p', null, 'Safe payments via Razorpay with encryption')
                ),
                React.createElement('article', { className: 'panel' },
                    React.createElement('span', { style: { fontSize: '2rem', display: 'block', marginBottom: 'var(--space-sm)' } }, '📦'),
                    React.createElement('h3', null, 'Fast Delivery'),
                    React.createElement('p', null, 'Quick and careful shipping across India')
                )
            )
        )
    );
}

// Product Card Component
function ProductCard({ product, onAddToCart }) {
    const { addToCart, navigate } = useApp();
    const hasOffer = product.offer_price && product.offer_price < product.price;
    
    return React.createElement('article', { className: 'product-card' },
        React.createElement('div', { className: 'product-card__image' },
            React.createElement('img', { src: Utils.getAssetUrl(product.image_url), alt: product.name, loading: 'lazy' }),
            hasOffer && React.createElement('span', { className: 'product-card__badge product-card__badge--sale' }, 'Sale')
        ),
        React.createElement('div', { className: 'product-card__body' },
            React.createElement('h3', null, product.name),
            React.createElement('p', { className: 'product-card__desc' }, product.description),
            React.createElement('div', { className: 'product-card__price-row' },
                React.createElement('span', { className: 'price' }, Utils.formatPrice(product.offer_price || product.price)),
                hasOffer && React.createElement('span', { className: 'old-price' }, Utils.formatPrice(product.price)),
                hasOffer && React.createElement('span', { className: 'discount-pct' }, `-${Math.round((1 - product.offer_price / product.price) * 100)}%`)
            ),
            React.createElement('div', { className: 'product-card__actions' },
                React.createElement('button', { className: 'btn btn-sm btn-ghost', onClick: () => navigate(`/product/${product.slug}`) }, 'View'),
                React.createElement('button', { className: 'btn btn-sm btn-primary', onClick: () => addToCart(product, 1) }, 'Add to Cart')
            )
        )
    );
}

// Export
window.HomePage = HomePage;
window.ProductCard = ProductCard;
