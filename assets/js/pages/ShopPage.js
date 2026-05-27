/**
 * Shop Page Component
 */

function ShopPage() {
    const { navigate } = useApp();
    const [products, setProducts] = useState([]);
    const [categories, setCategories] = useState([]);
    const [selectedCategory, setSelectedCategory] = useState('');
    const [loading, setLoading] = useState(true);
    
    const urlParams = new URLSearchParams(window.location.search);
    const categoryParam = urlParams.get('category') || '';
    
    useEffect(() => {
        Promise.all([
            API.get('/shop'),
            API.get('/categories')
        ]).then(([shopData, categoriesData]) => {
            setProducts(shopData.items || []);
            setCategories(categoriesData || []);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, [categoryParam]);
    
    useEffect(() => {
        setSelectedCategory(categoryParam);
    }, [categoryParam]);
    
    const filteredProducts = selectedCategory 
        ? products.filter(p => p.category === selectedCategory)
        : products;
    
    if (loading) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } }, 'Loading...');
    
    return React.createElement('section', { className: 'section', style: { paddingTop: 'var(--space-xl)' } },
        React.createElement('div', { className: 'container' },
            React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-2xl)' } },
                React.createElement('span', { className: 'eyebrow' }, 'Sacred Collection'),
                React.createElement('h1', { className: 'section-title', style: { marginBottom: 'var(--space-sm)' } }, 'Shop Spiritual Products'),
                React.createElement('p', { className: 'lede', style: { margin: '0 auto' } }, 'Authentic spiritual products crafted with devotion and care.')
            ),
            
            React.createElement('div', { className: 'shop-layout' },
                // Sidebar Filters
                React.createElement('aside', { className: 'shop-sidebar' },
                    React.createElement('div', { className: 'shop-filters' },
                        React.createElement('h3', null, 'Categories'),
                        React.createElement('div', { className: 'filter-group' },
                            React.createElement('button', {
                                className: `filter-chip ${!selectedCategory ? 'active' : ''}`,
                                onClick: () => navigate('/shop')
                            }, 'All'),
                            categories.map(cat =>
                                React.createElement('button', {
                                    key: cat.slug,
                                    className: `filter-chip ${selectedCategory === cat.slug ? 'active' : ''}`,
                                    onClick: () => navigate(`/shop?category=${cat.slug}`)
                                }, cat.name)
                            )
                        )
                    )
                ),
                
                // Product Grid
                React.createElement('div', null,
                    React.createElement('div', { className: 'shop-toolbar' },
                        React.createElement('span', { className: 'shop-toolbar__count' }, `${filteredProducts.length} products`),
                    ),
                    filteredProducts.length === 0
                        ? React.createElement('div', { style: { textAlign: 'center', padding: '3rem' } },
                            React.createElement('p', { style: { color: 'var(--color-text-muted)' } }, 'No products found in this category.')
                        )
                        : React.createElement('div', { className: 'product-grid' },
                            filteredProducts.map(product => 
                                React.createElement(ProductCard, { key: product.slug, product: product })
                            )
                        )
                )
            )
        )
    );
}

// Product Detail Page
function ProductPage() {
    const { addToCart, navigate } = useApp();
    const [product, setProduct] = useState(null);
    const [related, setRelated] = useState([]);
    const [qty, setQty] = useState(1);
    const [loading, setLoading] = useState(true);
    
    const slug = window.location.pathname.split('/product/')[1];
    
    useEffect(() => {
        API.get(`/product/${slug}`).then(data => {
            setProduct(data.product);
            setRelated(data.related || []);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, [slug]);
    
    if (loading) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } }, 'Loading...');
    if (!product) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } },
        React.createElement('h2', null, 'Product Not Found'),
        React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/shop') }, 'Back to Shop')
    );
    
    const hasOffer = product.offer_price && product.offer_price < product.price;
    
    return React.createElement('section', { className: 'section' },
        React.createElement('div', { className: 'container' },
            React.createElement('div', { className: 'product-detail' },
                // Product Gallery
                React.createElement('div', { className: 'product-gallery' },
                    React.createElement('div', { className: 'product-gallery__main' },
                        React.createElement('img', { src: Utils.getAssetUrl(product.image_url), alt: product.name })
                    )
                ),
                
                // Product Info
                React.createElement('div', { className: 'product-info' },
                    React.createElement('span', { className: 'eyebrow' }, product.category),
                    React.createElement('h1', null, product.name),
                    React.createElement('div', { className: 'product-info__price' },
                        React.createElement('span', { className: 'price' }, Utils.formatPrice(product.offer_price || product.price)),
                        hasOffer && React.createElement('span', { className: 'old-price' }, Utils.formatPrice(product.price))
                    ),
                    React.createElement('p', { className: 'product-info__desc' }, product.description),
                    
                    React.createElement('div', { className: 'product-info__form' },
                        React.createElement('div', { className: 'qty-input' },
                            React.createElement('button', { onClick: () => setQty(Math.max(1, qty - 1)) }, '-'),
                            React.createElement('input', { type: 'number', value: qty, readOnly }),
                            React.createElement('button', { onClick: () => setQty(qty + 1) }, '+')
                        ),
                        React.createElement('button', { 
                            className: 'btn btn-primary', 
                            onClick: () => addToCart(product, qty)
                        }, 'Add to Cart')
                    ),
                    
                    React.createElement('div', { className: 'product-info__features' },
                        React.createElement('div', { className: 'product-feature' }, React.createElement('span', null, '📦'), React.createElement('span', null, 'Free Shipping')),
                        React.createElement('div', { className: 'product-feature' }, React.createElement('span', null, '✓'), React.createElement('span', null, 'Authentic Product')),
                        React.createElement('div', { className: 'product-feature' }, React.createElement('span', null, '🔒'), React.createElement('span', null, 'Secure Payment')),
                        React.createElement('div', { className: 'product-feature' }, React.createElement('span', null, '↩️'), React.createElement('span', null, 'Easy Returns'))
                    )
                )
            ),
            
            // Related Products
            related.length > 0 && React.createElement('div', { style: { marginTop: 'var(--space-4xl)' } },
                React.createElement('h2', { className: 'section-title', style: { textAlign: 'center' } }, 'Related Products'),
                React.createElement('div', { className: 'product-grid', style: { marginTop: 'var(--space-xl)' } },
                    related.slice(0, 4).map(p => 
                        React.createElement(ProductCard, { key: p.slug, product: p })
                    )
                )
            )
        )
    );
}

// Category Page (alias to shop with filter)
function CategoryPage() {
    return React.createElement(ShopPage);
}

window.ShopPage = ShopPage;
window.ProductPage = ProductPage;
window.CategoryPage = CategoryPage;
