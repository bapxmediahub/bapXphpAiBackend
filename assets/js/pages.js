/**
 * Page Components
 */

// ============================================
// HOME PAGE
// ============================================
async function HomePage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</div>';
    
    try {
        const data = await API.get('/');
        const { products = [], categories = [], astrologers = [], temples = [] } = data;
        
        root.innerHTML = '';
        root.className = '';

        // Hero
        const hero = document.createElement('section');
        hero.className = 'home-hero';
        hero.innerHTML = `
            <div class="hero-copy">
                <span class="eyebrow">Blessings · Protection · Prosperity</span>
                <h1>Divine Grace.<br>Timeless Protection.</h1>
                <p class="lede">Authentic spiritual products, sacred jewelry, expert astrology and temple guidance to elevate your life.</p>
                <div class="hero-actions">
                    <button class="btn btn-primary" onclick="Router.navigate('/shop')">Shop Spiritual Products</button>
                    <button class="btn btn-outline" onclick="Router.navigate('/astrologers')">Book Astrology</button>
                </div>
                <div class="hero-stats">
                    <div><div class="hero-stat-value">500+</div><div class="hero-stat-label">Happy Devotees</div></div>
                    <div><div class="hero-stat-value">14+</div><div class="hero-stat-label">Sacred Items</div></div>
                    <div><div class="hero-stat-value">3</div><div class="hero-stat-label">Expert Astrologers</div></div>
                </div>
            </div>
            <div class="hero-deity">
                <img src="/assets/images/varahi-amman.png" alt="Sri Maha Varahi Amman">
            </div>
        `;
        root.appendChild(hero);

        // Trust Bar
        const trust = document.createElement('div');
        trust.className = 'trust-bar';
        trust.innerHTML = `
            <div class="trust-item">🔒 Secure Payments</div>
            <div class="trust-item">📦 Fast Delivery</div>
            <div class="trust-item">✓ Authentic Products</div>
            <div class="trust-item">✨ Blessed Items</div>
        `;
        root.appendChild(trust);

        // Categories
        if (categories.length) {
            const catSection = document.createElement('section');
            catSection.className = 'category-section section';
            catSection.innerHTML = `
                <div class="section-header">
                    <h2 class="section-title">Shop by Category</h2>
                    <p class="lede">Curated collections for every spiritual need</p>
                </div>
                <div class="category-grid">
                    ${categories.map(c => `
                        <a class="category-card" href="/shop?category=${c.slug}" data-link>
                            <div class="category-img-wrap">
                                <img src="${img(c.image_url, c.name)}" alt="${esc(c.name)}">
                            </div>
                            <h3>${esc(c.name)}</h3>
                            <p>${esc(c.description)}</p>
                        </a>
                    `).join('')}
                </div>
            `;
            root.appendChild(catSection);
        }

        // Featured Products
        if (products.length) {
            const prodSection = document.createElement('section');
            prodSection.className = 'section';
            prodSection.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem">
                    <h2 class="section-title" style="margin:0">Featured Products</h2>
                    <button class="btn btn-sm btn-ghost" onclick="Router.navigate('/shop')">View All</button>
                </div>
                <div class="product-grid" id="featured-products"></div>
            `;
            const grid = prodSection.querySelector('#featured-products');
            products.slice(0, 6).forEach(p => grid.appendChild(ProductCard(p)));
            root.appendChild(prodSection);
        }

        // Temples
        if (temples.length) {
            const templeSection = document.createElement('section');
            templeSection.className = 'section section--alt';
            templeSection.innerHTML = `
                <div class="section-header">
                    <span class="eyebrow">Sacred Spaces · Divine Energy</span>
                    <h2 class="section-title">Our Temples</h2>
                    <p class="lede">Visit our sacred spaces for divine blessings and spiritual awakening.</p>
                </div>
                <div class="showcase-grid" id="home-temples"></div>
                <div style="text-align:center;margin-top:2rem">
                    <button class="btn btn-primary" onclick="Router.navigate('/temples')">View All Temples</button>
                </div>
            `;
            const grid = templeSection.querySelector('#home-temples');
            temples.slice(0, 3).forEach(t => {
                const card = document.createElement('article');
                card.className = 'showcase-card';
                card.innerHTML = `
                    <div style="background:var(--color-bg-alt);border-radius:var(--radius-md);margin-bottom:var(--space-md);height:160px;display:flex;align-items:center;justify-content:center;overflow:hidden">
                        ${t.image_url ? `<img src="${t.image_url}" alt="${esc(t.name)}" style="width:100%;height:100%;object-fit:cover">` : '<span style="font-size:3rem">🛕</span>'}
                    </div>
                    <h2>${esc(t.name)}</h2>
                    <p>${esc(t.description)}</p>
                    <button class="btn btn-sm btn-primary" onclick="Router.navigate('/temples/${t.slug}')">View Details</button>
                `;
                grid.appendChild(card);
            });
            root.appendChild(templeSection);
        }

        // Astrologers
        if (astrologers.length) {
            const astroSection = document.createElement('section');
            astroSection.className = 'section';
            astroSection.innerHTML = `
                <div class="section-header">
                    <span class="eyebrow">Guidance · Clarity · Remedies</span>
                    <h2 class="section-title">Expert Astrology for a Better Tomorrow</h2>
                    <p class="lede">Consult experienced astrologers for accurate predictions and remedy guidance.</p>
                </div>
                <div class="astrologer-grid" id="home-astros"></div>
                <div style="text-align:center;margin-top:2rem">
                    <button class="btn btn-primary" onclick="Router.navigate('/astrologers')">Book Astrology Consultation</button>
                </div>
            `;
            const grid = astroSection.querySelector('#home-astros');
            astrologers.slice(0, 3).forEach(a => {
                const card = document.createElement('article');
                card.className = 'astrologer-card';
                card.innerHTML = `
                    <div class="astrologer-card__header">
                        <img class="astrologer-card__photo" src="${img(a.photo_url, a.name)}" alt="${esc(a.name)}">
                        <div>
                            <h3 class="astrologer-card__name">${esc(a.name)}</h3>
                            <p class="astrologer-card__speciality">${esc(a.speciality || 'Vedic Astrology')}</p>
                        </div>
                    </div>
                    <div class="astrologer-card__body">
                        <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Experience</span><span class="astrologer-card__stat-value">${a.experience_years || 'N/A'} yrs</span></div>
                        <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Languages</span><span class="astrologer-card__stat-value">${(a.languages || []).slice(0, 2).join(', ')}</span></div>
                    </div>
                    <div class="astrologer-card__footer">
                        <span class="astrologer-card__price">${fmt(a.price || 0)}</span>
                        <button class="btn btn-sm btn-outline" onclick="Router.navigate('/astrologers/${a.slug}')">Book Now</button>
                    </div>
                `;
                grid.appendChild(card);
            });
            root.appendChild(astroSection);
        }

        // Why Choose Us
        const why = document.createElement('section');
        why.className = 'section section--alt';
        why.innerHTML = `
            <div class="section-header"><h2 class="section-title">Why Choose Us</h2></div>
            <div class="feature-strip">
                <article class="panel"><span style="font-size:2rem;display:block;margin-bottom:var(--space-sm)">🛕</span><h3>Authentic</h3><p>Genuine spiritual products sourced with devotion</p></article>
                <article class="panel"><span style="font-size:2rem;display:block;margin-bottom:var(--space-sm)">⭐</span><h3>Expert Guidance</h3><p>Experienced astrologers with proven track record</p></article>
                <article class="panel"><span style="font-size:2rem;display:block;margin-bottom:var(--space-sm)">🔒</span><h3>Secure</h3><p>Safe payments via Razorpay with encryption</p></article>
                <article class="panel"><span style="font-size:2rem;display:block;margin-bottom:var(--space-sm)">📦</span><h3>Fast Delivery</h3><p>Quick and careful shipping across India</p></article>
            </div>
        `;
        root.appendChild(why);

        // Footer
        root.appendChild(Footer());
        root.appendChild(BottomNav());

    } catch (e) {
        root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error loading page. <button class="btn btn-primary" onclick="Router.render()">Retry</button></p></div>`;
    }
}

// ============================================
// SHOP PAGE
// ============================================
async function ShopPage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    
    const urlParams = new URLSearchParams(window.location.search);
    const category = urlParams.get('category') || '';
    
    try {
        const [shopData, categories] = await Promise.all([
            API.get('/shop' + (category ? '?category=' + category : '')),
            API.get('/categories').catch(() => [])
        ]);
        
        const products = shopData.items || shopData.products || [];
        
        root.innerHTML = '';
        root.className = '';

        const section = document.createElement('section');
        section.className = 'section';
        section.style.paddingTop = 'var(--space-xl)';
        
        let html = `
            <div class="container">
                <div style="text-align:center;margin-bottom:var(--space-2xl)">
                    <span class="eyebrow">Sacred Collection</span>
                    <h1 class="section-title" style="margin-bottom:var(--space-sm)">Shop Spiritual Products</h1>
                    <p class="lede" style="margin:0 auto">Authentic spiritual products crafted with devotion and care.</p>
                </div>
                <div class="shop-layout">
                    <aside class="shop-sidebar">
                        <div class="shop-filters">
                            <h3>Categories</h3>
                            <div class="filter-group">
                                <button class="filter-chip ${!category ? 'active' : ''}" onclick="Router.navigate('/shop')">All</button>
                                ${categories.map(c => `<button class="filter-chip ${category === c.slug ? 'active' : ''}" onclick="Router.navigate('/shop?category=${c.slug}')">${esc(c.name)}</button>`).join('')}
                            </div>
                        </div>
                    </aside>
                    <div>
                        <div class="shop-toolbar">
                            <span class="shop-toolbar__count">${products.length} products</span>
                        </div>
                        <div class="product-grid" id="shop-products"></div>
                    </div>
                </div>
            </div>
        `;
        section.innerHTML = html;
        
        const grid = section.querySelector('#shop-products');
        if (products.length === 0) {
            grid.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--color-text-muted)">No products found in this category.</div>';
        } else {
            products.forEach(p => grid.appendChild(ProductCard(p)));
        }
        
        root.appendChild(section);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
        
    } catch (e) {
        root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error loading shop.</p></div>`;
    }
}

// ============================================
// PRODUCT DETAIL PAGE
// ============================================
async function ProductPage(root, slug) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    
    try {
        const data = await API.get('/product/' + slug);
        const product = data.product;
        
        if (!product) {
            root.innerHTML = '<div class="section" style="text-align:center;padding:4rem"><h2>Product Not Found</h2><button class="btn btn-primary" onclick="Router.navigate(\'/shop\')">Back to Shop</button></div>';
            return;
        }
        
        const related = data.related || [];
        const hasOffer = product.offer_price && product.offer_price < product.price;
        
        root.innerHTML = '';
        root.className = '';

        const section = document.createElement('section');
        section.className = 'section';
        section.innerHTML = `
            <div class="container">
                <div class="product-detail">
                    <div class="product-gallery">
                        <div class="product-gallery__main">
                            <img src="${img(product.image_url, product.name)}" alt="${esc(product.name)}">
                        </div>
                    </div>
                    <div class="product-info">
                        <span class="eyebrow">${esc(product.category)}</span>
                        <h1>${esc(product.name)}</h1>
                        <div class="product-info__price">
                            <span class="price">${fmt(product.offer_price || product.price)}</span>
                            ${hasOffer ? `<span class="old-price">${fmt(product.price)}</span>` : ''}
                        </div>
                        <p class="product-info__desc">${esc(product.description)}</p>
                        <div class="product-info__form">
                            <div class="qty-input">
                                <button onclick="this.nextElementSibling.value=Math.max(1,parseInt(this.nextElementSibling.value)-1)">-</button>
                                <input type="number" value="1" id="qty" min="1">
                                <button onclick="this.previousElementSibling.value=parseInt(this.previousElementSibling.value)+1">+</button>
                            </div>
                            <button class="btn btn-primary" onclick="Cart.add(${JSON.stringify(product).replace(/"/g, '&quot;')}, parseInt(document.getElementById('qty').value))">Add to Cart</button>
                        </div>
                        <div class="product-info__features">
                            <div class="product-feature"><span>📦</span><span>Free Shipping</span></div>
                            <div class="product-feature"><span>✓</span><span>Authentic Product</span></div>
                            <div class="product-feature"><span>🔒</span><span>Secure Payment</span></div>
                            <div class="product-feature"><span>↩️</span><span>Easy Returns</span></div>
                        </div>
                    </div>
                </div>
                ${related.length ? `
                    <div style="margin-top:var(--space-4xl)">
                        <h2 class="section-title" style="text-align:center">Related Products</h2>
                        <div class="product-grid" style="margin-top:var(--space-xl)" id="related-products"></div>
                    </div>
                ` : ''}
            </div>
        `;
        
        if (related.length) {
            const relGrid = section.querySelector('#related-products');
            related.slice(0, 4).forEach(p => relGrid.appendChild(ProductCard(p)));
        }
        
        root.appendChild(section);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
        
    } catch (e) {
        root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error loading product.</p></div>`;
    }
}

// ============================================
// CART PAGE
// ============================================
function CartPage(root) {
    root.innerHTML = '';
    root.className = '';

    const section = document.createElement('section');
    section.className = 'section';
    
    if (Cart.items.length === 0) {
        section.innerHTML = `
            <div class="container" style="text-align:center;padding:4rem">
                <div style="font-size:3rem;margin-bottom:var(--space-md)">🛒</div>
                <h2 style="font-family:var(--font-serif)">Your Cart is Empty</h2>
                <p style="color:var(--color-text-muted);margin-bottom:var(--space-lg)">Add some spiritual products to get started.</p>
                <button class="btn btn-primary" onclick="Router.navigate('/shop')">Continue Shopping</button>
            </div>
        `;
    } else {
        section.innerHTML = `
            <div class="container">
                <h1 style="font-family:var(--font-serif);text-align:center;margin-bottom:var(--space-2xl)">Shopping Cart</h1>
                <div class="cart-layout">
                    <div class="cart-items" id="cart-items"></div>
                    <div class="cart-summary">
                        <h2>Order Summary</h2>
                        <div class="cart-summary__row"><span>Subtotal</span><span>${fmt(Cart.total)}</span></div>
                        <div class="cart-summary__row"><span>Shipping</span><span>Free</span></div>
                        <div class="cart-summary__row cart-summary__row--total"><span>Total</span><span>${fmt(Cart.total)}</span></div>
                        <button class="btn btn-primary btn-block" style="margin-top:var(--space-lg)" onclick="Router.navigate('/checkout')">Proceed to Checkout</button>
                        <button class="btn btn-ghost btn-block" style="margin-top:var(--space-sm)" onclick="Router.navigate('/shop')">Continue Shopping</button>
                    </div>
                </div>
            </div>
        `;
        
        const itemsContainer = section.querySelector('#cart-items');
        Cart.items.forEach(item => {
            const price = item.offer_price || item.price || 0;
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <img class="cart-item__img" src="${img(item.image_url, item.name)}" alt="${esc(item.name)}">
                <div>
                    <h3 class="cart-item__name"><a href="/product/${item.slug}" data-link>${esc(item.name)}</a></h3>
                    <p class="cart-item__meta">${esc(item.category)}</p>
                </div>
                <div style="display:flex;align-items:center;gap:var(--space-sm)">
                    <button class="btn btn-sm" onclick="Cart.updateQty('${item.slug}', ${item.qty - 1}); Router.render()">-</button>
                    <span>${item.qty}</span>
                    <button class="btn btn-sm" onclick="Cart.updateQty('${item.slug}', ${item.qty + 1}); Router.render()">+</button>
                </div>
                <span class="cart-item__price">${fmt(price * item.qty)}</span>
                <button class="cart-item__remove" onclick="Cart.remove('${item.slug}'); Router.render()">✕</button>
            `;
            itemsContainer.appendChild(div);
        });
    }
    
    root.appendChild(section);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

// ============================================
// CHECKOUT PAGE
// ============================================
function CheckoutPage(root) {
    root.innerHTML = '';
    root.className = '';

    if (Cart.items.length === 0) {
        root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><h2>Nothing to Checkout</h2><button class="btn btn-primary" onclick="Router.navigate('/shop')">Go Shopping</button></div>`;
        root.appendChild(Footer());
        root.appendChild(BottomNav());
        return;
    }

    const section = document.createElement('section');
    section.className = 'section';
    section.innerHTML = `
        <div class="container">
            <h1 style="font-family:var(--font-serif);text-align:center;margin-bottom:var(--space-2xl)">Checkout</h1>
            <div class="checkout-layout">
                <form class="checkout-form" id="checkout-form">
                    <h2>Shipping Details</h2>
                    <div class="checkout-form__row">
                        <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                    </div>
                    <div class="checkout-form__row">
                        <div class="form-group"><label>Phone</label><input type="tel" name="phone" required></div>
                        <div class="form-group"><label>Pincode</label><input type="text" name="pincode" required></div>
                    </div>
                    <div class="form-group"><label>Address</label><textarea name="address" required rows="3"></textarea></div>
                    <div class="checkout-form__row">
                        <div class="form-group"><label>City</label><input type="text" name="city" required></div>
                        <div class="form-group"><label>State</label><input type="text" name="state" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:var(--space-lg)">Place Order</button>
                </form>
                <div class="checkout-summary">
                    <h2>Order Summary</h2>
                    <div id="checkout-items"></div>
                    <div class="cart-summary__row cart-summary__row--total" style="margin-top:var(--space-md);padding-top:var(--space-md);border-top:1px solid var(--color-border)">
                        <span>Total</span><span>${fmt(Cart.total)}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const itemsContainer = section.querySelector('#checkout-items');
    Cart.items.forEach(item => {
        const price = item.offer_price || item.price || 0;
        const div = document.createElement('div');
        div.className = 'checkout-item';
        div.innerHTML = `
            <img class="checkout-item__img" src="${img(item.image_url, item.name)}" alt="${esc(item.name)}">
            <div><div class="checkout-item__name">${esc(item.name)}</div><div class="checkout-item__meta">Qty: ${item.qty}</div></div>
            <span class="checkout-item__price">${fmt(price * item.qty)}</span>
        `;
        itemsContainer.appendChild(div);
    });
    
    section.querySelector('#checkout-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = Object.fromEntries(new FormData(e.target));
        try {
            await API.post('/checkout/create-order', { ...formData, items: Cart.items, total: Cart.total });
            Cart.clear();
            Router.navigate('/order-success');
        } catch (err) {
            alert('Order failed. Please try again.');
        }
    });
    
    root.appendChild(section);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

// ============================================
// ORDER SUCCESS PAGE
// ============================================
function OrderSuccessPage(root) {
    root.innerHTML = '';
    root.className = '';
    const section = document.createElement('section');
    section.className = 'section';
    section.style.textAlign = 'center';
    section.style.padding = '4rem';
    section.innerHTML = `
        <div style="font-size:4rem;margin-bottom:var(--space-md)">✓</div>
        <h1 style="font-family:var(--font-serif)">Order Placed Successfully!</h1>
        <p style="color:var(--color-text-muted);margin-bottom:var(--space-lg)">Thank you for your order. We will contact you soon.</p>
        <button class="btn btn-primary" onclick="Router.navigate('/shop')">Continue Shopping</button>
    `;
    root.appendChild(section);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

// ============================================
// ASTROLOGERS PAGE
// ============================================
async function AstrologersPage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    
    try {
        const data = await API.get('/astrologers');
        const astros = data.items || [];
        
        root.innerHTML = '';
        root.className = '';

        const section = document.createElement('section');
        section.className = 'section';
        section.style.paddingTop = 'var(--space-xl)';
        section.innerHTML = `
            <div class="container">
                <div style="text-align:center;margin-bottom:var(--space-2xl)">
                    <span class="eyebrow">Expert Guidance · Divine Wisdom</span>
                    <h1 class="section-title" style="margin-bottom:var(--space-sm)">Our Astrologers</h1>
                    <p class="lede" style="margin:0 auto">Consult experienced Vedic astrologers for accurate predictions.</p>
                </div>
                <div class="astrologer-grid" id="astro-grid"></div>
            </div>
        `;
        
        const grid = section.querySelector('#astro-grid');
        if (!astros.length) {
            grid.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--color-text-muted)">No astrologers available.</div>';
        } else {
            astros.forEach(a => {
                const card = document.createElement('article');
                card.className = 'astrologer-card';
                card.innerHTML = `
                    <div class="astrologer-card__header">
                        <img class="astrologer-card__photo" src="${img(a.photo_url, a.name)}" alt="${esc(a.name)}">
                        <div>
                            <h3 class="astrologer-card__name">${esc(a.name)}</h3>
                            <p class="astrologer-card__speciality">${esc(a.speciality || 'Vedic Astrology')}</p>
                        </div>
                    </div>
                    <div class="astrologer-card__body">
                        <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Experience</span><span class="astrologer-card__stat-value">${a.experience_years || 'N/A'} yrs</span></div>
                        <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Languages</span><span class="astrologer-card__stat-value">${(a.languages || []).slice(0, 2).join(', ')}</span></div>
                    </div>
                    <div class="astrologer-card__footer">
                        <span class="astrologer-card__price">${fmt(a.price || 0)}</span>
                        <button class="btn btn-sm btn-primary" onclick="Router.navigate('/astrologers/${a.slug}')">Book Now</button>
                    </div>
                `;
                grid.appendChild(card);
            });
        }
        
        root.appendChild(section);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
        
    } catch (e) {
        root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error loading astrologers.</p></div>`;
    }
}

// ============================================
// TEMPLES PAGE
// ============================================
async function TemplesPage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    
    try {
        const data = await API.get('/temples');
        const temples = data.items || [];
        
        root.innerHTML = '';
        root.className = '';

        const section = document.createElement('section');
        section.className = 'section';
        section.style.paddingTop = 'var(--space-xl)';
        section.innerHTML = `
            <div class="container">
                <div style="text-align:center;margin-bottom:var(--space-2xl)">
                    <span class="eyebrow">Sacred Spaces · Divine Energy</span>
                    <h1 class="section-title" style="margin-bottom:var(--space-sm)">Our Temples</h1>
                    <p class="lede" style="margin:0 auto">Visit our sacred spaces for divine blessings.</p>
                </div>
                <div class="showcase-grid" id="temple-grid"></div>
            </div>
        `;
        
        const grid = section.querySelector('#temple-grid');
        if (!temples.length) {
            grid.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--color-text-muted)">No temples listed.</div>';
        } else {
            temples.forEach(t => {
                const card = document.createElement('article');
                card.className = 'showcase-card';
                card.innerHTML = `
                    <div style="background:var(--color-bg-alt);border-radius:var(--radius-md);margin-bottom:var(--space-md);height:180px;display:flex;align-items:center;justify-content:center;overflow:hidden">
                        ${t.image_url ? `<img src="${t.image_url}" alt="${esc(t.name)}" style="width:100%;height:100%;object-fit:cover">` : '<span style="font-size:3rem">🛕</span>'}
                    </div>
                    <h2>${esc(t.name)}</h2>
                    <p>${esc(t.description)}</p>
                    ${t.address ? `<p style="margin-top:var(--space-sm);font-size:0.85rem;color:var(--color-text-muted)">📍 ${esc(t.address)}</p>` : ''}
                    <div style="margin-top:var(--space-md);display:flex;gap:var(--space-xs)">
                        <button class="btn btn-sm btn-primary" onclick="Router.navigate('/temples/${t.slug}')">View Details</button>
                        ${t.map_link ? `<a href="${t.map_link}" target="_blank" rel="noopener" class="btn btn-sm btn-outline">Get Directions</a>` : ''}
                    </div>
                `;
                grid.appendChild(card);
            });
        }
        
        root.appendChild(section);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
        
    } catch (e) {
        root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error loading temples.</p></div>`;
    }
}

// ============================================
// CONTACT PAGE
// ============================================
function ContactPage(root) {
    root.innerHTML = '';
    root.className = '';

    const section = document.createElement('section');
    section.className = 'section';
    section.innerHTML = `
        <div class="container container--narrow">
            <div style="text-align:center;margin-bottom:var(--space-2xl)">
                <span class="eyebrow">Get in Touch</span>
                <h1 class="section-title" style="margin-bottom:var(--space-sm)">Contact Us</h1>
                <p class="lede" style="margin:0 auto">We'd love to hear from you.</p>
            </div>
            <div id="contact-success" class="flash flash--success" style="display:none;margin-bottom:var(--space-lg)">✓ Thank you! We will get back to you soon.</div>
            <div class="grid" style="margin-bottom:var(--space-2xl)">
                <div class="panel text-center">
                    <span style="font-size:2rem;display:block;margin-bottom:var(--space-sm)">📧</span>
                    <h3 style="font-family:var(--font-serif)">Email</h3>
                    <a href="mailto:sripanchamispiritual@gmail.com" style="color:var(--color-maroon)">sripanchamispiritual@gmail.com</a>
                </div>
                <div class="panel text-center">
                    <span style="font-size:2rem;display:block;margin-bottom:var(--space-sm)">📍</span>
                    <h3 style="font-family:var(--font-serif)">Address</h3>
                    <p style="color:var(--color-text-muted);font-size:0.9rem">23, 1st Cross Street Kothari Nagar,<br>Ramapuram, Chennai,<br>Tamil Nadu 600089</p>
                </div>
                <div class="panel text-center">
                    <span style="font-size:2rem;display:block;margin-bottom:var(--space-sm)">🏢</span>
                    <h3 style="font-family:var(--font-serif)">Business</h3>
                    <p style="color:var(--color-text-muted);font-size:0.9rem">Reg. No: 33BZRPM8732J2ZQ</p>
                </div>
            </div>
            <div class="admin-card" style="margin-bottom:var(--space-2xl)">
                <h2 style="font-family:var(--font-serif);text-align:center;margin-bottom:var(--space-lg)">Send Us a Message</h2>
                <form id="contact-form" class="admin-form" style="max-width:600px;margin:0 auto">
                    <div class="admin-form__row">
                        <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                    </div>
                    <div class="admin-form__row">
                        <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
                        <div class="form-group"><label>Subject</label><select name="subject" required><option value="">Select</option><option value="general">General</option><option value="product">Product</option><option value="order">Order</option><option value="astrology">Astrology</option><option value="temple">Temple</option></select></div>
                    </div>
                    <div class="form-group"><label>Message</label><textarea name="message" required rows="5"></textarea></div>
                    <button type="submit" class="btn btn-primary btn-block">Send Message</button>
                </form>
            </div>
            <div>
                <h2 style="font-family:var(--font-serif);text-align:center;margin-bottom:var(--space-lg)">Find Us Here</h2>
                <div style="border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--color-border)">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3888.5!2d80.1767!3d13.0166!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3a5267c07a315555%3A0x9c2b1c5c5c5c5c5c!2sRamapuram%2C%20Chennai!5e0!3m2!1sen!2sin!4v1234567890" width="100%" height="400" style="border:0" allowfullscreen loading="lazy"></iframe>
                </div>
            </div>
        </div>
    `;
    
    section.querySelector('#contact-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target));
        try {
            await API.post('/contact', data);
            section.querySelector('#contact-success').style.display = 'block';
            e.target.reset();
        } catch (err) {
            alert('Failed to send message.');
        }
    });
    
    root.appendChild(section);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

// ============================================
// ABOUT PAGE
// ============================================
function AboutPage(root) {
    root.innerHTML = '';
    root.className = '';
    const section = document.createElement('section');
    section.className = 'section';
    section.style.paddingTop = 'var(--space-xl)';
    section.innerHTML = `
        <div class="container container--narrow">
            <div style="text-align:center;margin-bottom:var(--space-2xl)">
                <span class="eyebrow">Our Story</span>
                <h1 class="section-title">About Us</h1>
                <p class="lede">Dedicated to bringing authentic spiritual products and services to devotees.</p>
            </div>
            <div class="panel" style="margin-bottom:var(--space-xl)">
                <h2 style="font-family:var(--font-serif)">Our Mission</h2>
                <p>Sri Panchami Spiritual provides genuine spiritual products, expert astrology consultations, and temple guidance. We source products directly from trusted artisans.</p>
            </div>
            <div class="panel">
                <h2 style="font-family:var(--font-serif)">Why Choose Us</h2>
                <ul style="padding-left:var(--space-lg)">
                    <li>Authentic and tested spiritual products</li>
                    <li>Expert Vedic astrologers</li>
                    <li>Temple partnership for genuine pooja items</li>
                    <li>Secure payment via Razorpay</li>
                    <li>Fast delivery across India</li>
                </ul>
            </div>
        </div>
    `;
    root.appendChild(section);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

// ============================================
// 404 PAGE
// ============================================
function NotFoundPage(root) {
    root.innerHTML = '';
    root.className = '';
    const section = document.createElement('section');
    section.className = 'section';
    section.style.textAlign = 'center';
    section.style.padding = '4rem';
    section.innerHTML = `<h1 style="font-size:4rem;font-family:var(--font-serif)">404</h1><p style="color:var(--color-text-muted);margin-bottom:var(--space-lg)">Page not found</p><button class="btn btn-primary" onclick="Router.navigate('/')">Go Home</button>`;
    root.appendChild(section);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

// Register pages
window.HomePage = HomePage;
window.ShopPage = ShopPage;
window.ProductPage = ProductPage;
window.CartPage = CartPage;
window.CheckoutPage = CheckoutPage;
window.OrderSuccessPage = OrderSuccessPage;
window.AstrologersPage = AstrologersPage;
window.TemplesPage = TemplesPage;
window.ContactPage = ContactPage;
window.AboutPage = AboutPage;
window.NotFoundPage = NotFoundPage;
