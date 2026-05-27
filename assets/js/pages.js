/**
 * Page Handlers
 */

async function HomePage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    try {
        const data = await API.get('/');
        const { products=[], categories=[], astrologers=[], temples=[] } = data;
        root.innerHTML = '';
        root.appendChild(Header());

        // Hero
        const hero = document.createElement('section');
        hero.className = 'home-hero';
        hero.innerHTML = `<div class="hero-copy"><span class="eyebrow">Blessings · Protection · Prosperity</span><h1>Divine Grace.<br>Timeless Protection.</h1><p class="lede">Authentic spiritual products, sacred jewelry, expert astrology and temple guidance.</p><div class="hero-actions"><a href="/shop" data-link class="btn btn-primary">Shop Spiritual Products</a><a href="/astrologers" data-link class="btn btn-outline">Book Astrology</a></div><div class="hero-stats"><div><div class="hero-stat-value">500+</div><div class="hero-stat-label">Happy Devotees</div></div><div><div class="hero-stat-value">14+</div><div class="hero-stat-label">Sacred Items</div></div><div><div class="hero-stat-value">3</div><div class="hero-stat-label">Expert Astrologers</div></div></div></div><div class="hero-deity"><img src="/assets/images/varahi-amman.png" alt="Sri Maha Varahi Amman"></div>`;
        root.appendChild(hero);

        // Trust bar
        root.appendChild(Object.assign(document.createElement('div'), {className:'trust-bar', innerHTML:'<div class="trust-item">🔒 Secure Payments</div><div class="trust-item">📦 Fast Delivery</div><div class="trust-item">✓ Authentic Products</div><div class="trust-item">✨ Blessed Items</div>'}));

        // Categories
        if (categories.length) {
            const s = document.createElement('section');
            s.className = 'category-section section';
            s.innerHTML = `<div class="section-header"><h2 class="section-title">Shop by Category</h2><p class="lede">Curated collections for every spiritual need</p></div><div class="category-grid">${categories.map(c => `<a class="category-card" href="/shop?category=${c.slug}" data-link><div class="category-img-wrap"><img src="${img(c.image_url,c.name)}" alt="${esc(c.name)}"></div><h3>${esc(c.name)}</h3><p>${esc(c.description)}</p></a>`).join('')}</div>`;
            root.appendChild(s);
        }

        // Products
        if (products.length) {
            const s = document.createElement('section');
            s.className = 'section';
            s.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem"><h2 class="section-title" style="margin:0">Featured Products</h2><a href="/shop" data-link class="btn btn-sm btn-ghost">View All</a></div><div class="product-grid" id="fp"></div>`;
            products.slice(0,6).forEach(p => s.querySelector('#fp').appendChild(ProductCard(p)));
            root.appendChild(s);
        }

        // Astrologers section on home
        if (astrologers.length) {
            const s = document.createElement('section');
            s.className = 'section';
            s.innerHTML = `<div class="section-header"><span class="eyebrow">Guidance · Clarity · Remedies</span><h2 class="section-title">Expert Astrology</h2><p class="lede">Consult experienced astrologers for accurate predictions.</p></div><div class="astrologer-grid" id="ha"></div><div style="text-align:center;margin-top:2rem"><a href="/astrologers" data-link class="btn btn-primary">Book Astrology Consultation</a></div>`;
            astrologers.slice(0,3).forEach(a => s.querySelector('#ha').appendChild(AstroCard(a)));
            root.appendChild(s);
        }

        // Why choose us
        const why = document.createElement('section');
        why.className = 'section section--alt';
        why.innerHTML = `<div class="section-header"><h2 class="section-title">Why Choose Us</h2></div><div class="feature-strip"><article class="panel"><span style="font-size:2rem;display:block;margin-bottom:1rem">🛕</span><h3>Authentic</h3><p>Genuine spiritual products sourced with devotion</p></article><article class="panel"><span style="font-size:2rem;display:block;margin-bottom:1rem">⭐</span><h3>Expert Guidance</h3><p>Experienced astrologers with proven track record</p></article><article class="panel"><span style="font-size:2rem;display:block;margin-bottom:1rem">🔒</span><h3>Secure</h3><p>Safe payments via Razorpay</p></article><article class="panel"><span style="font-size:2rem;display:block;margin-bottom:1rem">📦</span><h3>Fast Delivery</h3><p>Quick shipping across India</p></article></div>`;
        root.appendChild(why);

        root.appendChild(Footer());
        root.appendChild(BottomNav());
    } catch(e) { root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error loading. <a href="/" data-link class="btn btn-primary">Retry</a></p></div>`; root.appendChild(Footer()); root.appendChild(BottomNav()); }
}

async function ShopPage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    try {
        const cat = new URLSearchParams(window.location.search).get('category') || '';
        const [shopData, cats] = await Promise.all([API.get('/shop'+(cat?'?category='+cat:'')), API.get('/categories').catch(()=>[])]);
        const products = shopData.items || shopData.products || [];
        root.innerHTML = '';
        root.appendChild(Header());
        const s = document.createElement('section');
        s.className = 'section';
        s.style.paddingTop = 'var(--space-xl)';
        s.innerHTML = `<div class="container"><div style="text-align:center;margin-bottom:var(--space-2xl)"><span class="eyebrow">Sacred Collection</span><h1 class="section-title" style="margin-bottom:var(--space-sm)">Shop Spiritual Products</h1><p class="lede" style="margin:0 auto">Authentic spiritual products crafted with devotion.</p></div><div class="shop-layout"><aside class="shop-sidebar"><div class="shop-filters"><h3>Categories</h3><div class="filter-group"><button class="filter-chip ${!cat?'active':''}" onclick="Router.navigate('/shop')">All</button>${cats.map(c => `<button class="filter-chip ${cat===c.slug?'active':''}" onclick="Router.navigate('/shop?category=${c.slug}')">${esc(c.name)}</button>`).join('')}</div></div></aside><div><div class="shop-toolbar"><span class="shop-toolbar__count">${products.length} products</span></div><div class="product-grid" id="sp"></div></div></div></div>`;
        const g = s.querySelector('#sp');
        products.length ? products.forEach(p => g.appendChild(ProductCard(p))) : g.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--color-text-muted)">No products found.</div>';
        root.appendChild(s);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
    } catch(e) { root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error loading shop.</p></div>`; root.appendChild(Footer()); root.appendChild(BottomNav()); }
}

async function ProductPage(root, slug) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    try {
        const data = await API.get('/product/' + slug);
        const p = data.product;
        if (!p) { root.innerHTML = '<div class="section" style="text-align:center;padding:4rem"><h2>Not Found</h2><a href="/shop" data-link class="btn btn-primary">Back</a></div>'; root.appendChild(Footer()); root.appendChild(BottomNav()); return; }
        const offer = p.offer_price && p.offer_price < p.price;
        root.innerHTML = '';
        root.appendChild(Header());
        const s = document.createElement('section');
        s.className = 'section';
        s.innerHTML = `<div class="container"><div class="product-detail"><div class="product-gallery"><div class="product-gallery__main"><img src="${img(p.image_url,p.name)}" alt="${esc(p.name)}"></div></div><div class="product-info"><span class="eyebrow">${esc(p.category)}</span><h1>${esc(p.name)}</h1><div class="product-info__price"><span class="price">${fmt(p.offer_price||p.price)}</span>${offer?`<span class="old-price">${fmt(p.price)}</span>`:''}</div><p class="product-info__desc">${esc(p.description)}</p><div class="product-info__form"><div class="qty-input"><button onclick="this.nextElementSibling.value=Math.max(1,parseInt(this.nextElementSibling.value)-1)">-</button><input type="number" value="1" id="qty"><button onclick="this.previousElementSibling.value=parseInt(this.previousElementSibling.value)+1">+</button></div><button class="btn btn-primary" id="addtocart">Add to Cart</button></div></div></div></div>`;
        s.querySelector('#addtocart').onclick = () => { Cart.add(p, parseInt(document.getElementById('qty').value)); };
        const relDiv = document.createElement('div');
        relDiv.innerHTML = '<h3 style="margin-top:2rem">Related Products</h3>';
        const relGrid = document.createElement('div');
        relGrid.className = 'product-grid';
        (data.related||[]).slice(0,4).forEach(r => relGrid.appendChild(ProductCard(r)));
        relDiv.appendChild(relGrid);
        s.querySelector('.container').appendChild(relDiv);
        root.appendChild(s);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
    } catch(e) { root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error</p></div>`; root.appendChild(Footer()); root.appendChild(BottomNav()); }
}

async function AstrologersPage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    try {
        const data = await API.get('/astrologers');
        const list = data.items || [];
        root.innerHTML = '';
        root.appendChild(Header());
        const s = document.createElement('section');
        s.className = 'section';
        s.style.paddingTop = 'var(--space-xl)';
        s.innerHTML = `<div class="container"><div style="text-align:center;margin-bottom:var(--space-2xl)"><span class="eyebrow">Expert Guidance · Divine Wisdom</span><h1 class="section-title" style="margin-bottom:var(--space-sm)">Our Astrologers</h1><p class="lede" style="margin:0 auto">Consult experienced Vedic astrologers for accurate predictions.</p></div><div class="astrologer-grid" id="ag"></div></div>`;
        const g = s.querySelector('#ag');
        list.length ? list.forEach(a => g.appendChild(AstroCard(a))) : g.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--color-text-muted)">No astrologers available.</div>';
        root.appendChild(s);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
    } catch(e) { root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error</p></div>`; root.appendChild(Header()); root.appendChild(Footer()); root.appendChild(BottomNav()); }
}

async function TemplesPage(root) {
    root.innerHTML = '<div style="text-align:center;padding:4rem"><p>Loading...</p></div>';
    try {
        const data = await API.get('/temples');
        const list = data.items || [];
        root.innerHTML = '';
        const s = document.createElement('section');
        s.className = 'section';
        s.style.paddingTop = 'var(--space-xl)';
        s.innerHTML = `<div class="container"><div style="text-align:center;margin-bottom:var(--space-2xl)"><span class="eyebrow">Sacred Spaces · Divine Energy</span><h1 class="section-title" style="margin-bottom:var(--space-sm)">Our Temples</h1><p class="lede" style="margin:0 auto">Visit our sacred spaces for divine blessings.</p></div><div class="showcase-grid" id="tg"></div></div>`;
        const g = s.querySelector('#tg');
        list.forEach(t => { const c = document.createElement('article'); c.className = 'showcase-card'; c.innerHTML = `<div style="background:var(--color-bg-alt);border-radius:var(--radius-md);margin-bottom:var(--space-md);height:180px;display:flex;align-items:center;justify-content:center;overflow:hidden">${t.image_url?`<img src="${t.image_url}" alt="${esc(t.name)}" style="width:100%;height:100%;object-fit:cover">`:'<span style="font-size:3rem">🛕</span>'}</div><h2>${esc(t.name)}</h2><p>${esc(t.description)}</p>${t.address?`<p style="margin-top:var(--space-sm);font-size:0.85rem;color:var(--color-text-muted)">📍 ${esc(t.address)}</p>`:''}<div style="margin-top:var(--space-md)"><a href="/temples/${t.slug}" data-link class="btn btn-sm btn-primary">View Details</a>${t.map_link?`<a href="${t.map_link}" target="_blank" rel="noopener" class="btn btn-sm btn-outline" style="margin-left:4px">Get Directions</a>`:''}</div>`; g.appendChild(c); });
        root.appendChild(s);
        root.appendChild(Footer());
        root.appendChild(BottomNav());
    } catch(e) { root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><p>Error</p></div>`; root.appendChild(Footer()); root.appendChild(BottomNav()); }
}

function ContactPage(root) {
    root.innerHTML = '';
    const s = document.createElement('section');
    s.className = 'section';
    s.innerHTML = `<div class="container container--narrow"><div style="text-align:center;margin-bottom:var(--space-2xl)"><span class="eyebrow">Get in Touch</span><h1 class="section-title" style="margin-bottom:var(--space-sm)">Contact Us</h1><p class="lede" style="margin:0 auto">We'd love to hear from you.</p></div><div id="ok" class="flash flash--success" style="display:none;margin-bottom:var(--space-lg)">✓ Thank you! We'll respond soon.</div><div class="admin-card" style="margin-bottom:var(--space-2xl)"><h2 style="font-family:var(--font-serif);text-align:center;margin-bottom:var(--space-lg)">Send Us a Message</h2><form id="cf" class="admin-form" style="max-width:600px;margin:0 auto"><div class="admin-form__row"><div class="form-group"><label>Name</label><input type="text" name="name" required></div><div class="form-group"><label>Email</label><input type="email" name="email" required></div></div><div class="admin-form__row"><div class="form-group"><label>Phone</label><input type="tel" name="phone"></div><div class="form-group"><label>Subject</label><select name="subject" required><option value="">Select</option><option value="general">General</option><option value="product">Product</option><option value="order">Order</option><option value="astrology">Astrology</option><option value="temple">Temple</option><option value="other">Other</option></select></div></div><div class="form-group"><label>Message</label><textarea name="message" required rows="5"></textarea></div><button type="submit" class="btn btn-primary btn-block">Send Message</button></form></div></div>`;
    s.querySelector('#cf').onsubmit = async e => { e.preventDefault(); try { await API.post('/contact', Object.fromEntries(new FormData(e.target))); s.querySelector('#ok').style.display = 'block'; e.target.reset(); } catch(err) { alert('Failed to send.'); } };
    root.appendChild(s);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

function AboutPage(root) {
    root.innerHTML = '';
    const s = document.createElement('section');
    s.className = 'section';
    s.style.paddingTop = 'var(--space-xl)';
    s.innerHTML = `<div class="container container--narrow"><div style="text-align:center;margin-bottom:var(--space-2xl)"><span class="eyebrow">Our Story</span><h1 class="section-title">About Us</h1><p class="lede">Dedicated to bringing authentic spiritual products to devotees.</p></div><div class="panel" style="margin-bottom:var(--space-xl)"><h2 style="font-family:var(--font-serif)">Our Mission</h2><p>Sri Panchami Spiritual provides genuine spiritual products, expert astrology consultations, and temple guidance. We source products directly from trusted artisans.</p></div><div class="panel"><h2 style="font-family:var(--font-serif)">Why Choose Us</h2><ul style="padding-left:var(--space-lg)"><li>Authentic and tested spiritual products</li><li>Expert Vedic astrologers</li><li>Temple partnership for genuine pooja items</li><li>Secure payment via Razorpay</li><li>Fast delivery across India</li></ul></div></div>`;
    root.appendChild(s);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

function CartPage(root) {
    root.innerHTML = '';
    const s = document.createElement('section');
    s.className = 'section';
    if (!Cart.items.length) {
        s.innerHTML = `<div class="container" style="text-align:center;padding:4rem"><div style="font-size:3rem;margin-bottom:var(--space-md)">🛒</div><h2 style="font-family:var(--font-serif)">Your Cart is Empty</h2><p style="color:var(--color-text-muted);margin-bottom:var(--space-lg)">Add products to get started.</p><a href="/shop" data-link class="btn btn-primary">Continue Shopping</a></div>`;
    } else {
        s.innerHTML = `<div class="container"><h1 style="font-family:var(--font-serif);text-align:center;margin-bottom:var(--space-2xl)">Shopping Cart</h1><div class="cart-layout"><div class="cart-items" id="ci"></div><div class="cart-summary"><h2>Order Summary</h2><div class="cart-summary__row"><span>Subtotal</span><span>${fmt(Cart.total)}</span></div><div class="cart-summary__row"><span>Shipping</span><span>Free</span></div><div class="cart-summary__row cart-summary__row--total"><span>Total</span><span>${fmt(Cart.total)}</span></div><a href="/checkout" data-link class="btn btn-primary btn-block" style="margin-top:var(--space-lg)">Proceed to Checkout</a><a href="/shop" data-link class="btn btn-ghost btn-block" style="margin-top:var(--space-sm)">Continue Shopping</a></div></div></div>`;
        const ci = s.querySelector('#ci');
        Cart.items.forEach(i => { const d = document.createElement('div'); d.className = 'cart-item'; d.innerHTML = `<img class="cart-item__img" src="${img(i.image_url,i.name)}" alt="${esc(i.name)}"><div><h3 class="cart-item__name"><a href="/product/${i.slug}" data-link>${esc(i.name)}</a></h3><p class="cart-item__meta">${esc(i.category)}</p></div><div style="display:flex;align-items:center;gap:var(--space-sm)"><button class="btn btn-sm" data-action="dec"> - </button><span>${i.qty}</span><button class="btn btn-sm" data-action="inc"> + </button></div><span class="cart-item__price">${fmt((i.offer_price||i.price)*i.qty)}</span><button class="cart-item__remove" data-action="rem">✕</button>`; d.querySelector('[data-action="dec"]').onclick = () => { Cart.updateQty(i.slug, i.qty-1); CartPage(root); }; d.querySelector('[data-action="inc"]').onclick = () => { Cart.updateQty(i.slug, i.qty+1); CartPage(root); }; d.querySelector('[data-action="rem"]').onclick = () => { Cart.remove(i.slug); CartPage(root); }; ci.appendChild(d); });
    }
    root.appendChild(s);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

function CheckoutPage(root) {
    root.innerHTML = '';
    if (!Cart.items.length) { root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><h2>Nothing to Checkout</h2><a href="/shop" data-link class="btn btn-primary">Go Shopping</a></div>`; root.appendChild(Footer()); root.appendChild(BottomNav()); return; }
    const s = document.createElement('section');
    s.className = 'section';
    s.innerHTML = `<div class="container"><h1 style="font-family:var(--font-serif);text-align:center;margin-bottom:var(--space-2xl)">Checkout</h1><div class="checkout-layout"><form class="checkout-form" id="co"><h2>Shipping Details</h2><div class="checkout-form__row"><div class="form-group"><label>Name</label><input type="text" name="name" required></div><div class="form-group"><label>Email</label><input type="email" name="email" required></div></div><div class="checkout-form__row"><div class="form-group"><label>Phone</label><input type="tel" name="phone" required></div><div class="form-group"><label>Pincode</label><input type="text" name="pincode" required></div></div><div class="form-group"><label>Address</label><textarea name="address" required rows="3"></textarea></div><div class="checkout-form__row"><div class="form-group"><label>City</label><input type="text" name="city" required></div><div class="form-group"><label>State</label><input type="text" name="state" required></div></div><button type="submit" class="btn btn-primary btn-block" style="margin-top:var(--space-lg)">Place Order</button></form><div class="checkout-summary"><h2>Order Summary</h2><div id="csi"></div><div class="cart-summary__row cart-summary__row--total" style="margin-top:var(--space-md);padding-top:var(--space-md);border-top:1px solid var(--color-border)"><span>Total</span><span>${fmt(Cart.total)}</span></div></div></div></div>`;
    const csi = s.querySelector('#csi');
    Cart.items.forEach(i => { const d = document.createElement('div'); d.className = 'checkout-item'; d.innerHTML = `<img class="checkout-item__img" src="${img(i.image_url,i.name)}"><div><div class="checkout-item__name">${esc(i.name)}</div><div class="checkout-item__meta">Qty: ${i.qty}</div></div><span class="checkout-item__price">${fmt((i.offer_price||i.price)*i.qty)}</span>`; csi.appendChild(d); });
    s.querySelector('#co').onsubmit = async e => { e.preventDefault(); try { await API.post('/checkout/create-order', {...Object.fromEntries(new FormData(e.target)), items: Cart.items, total: Cart.total}); Cart.clear(); Router.navigate('/order-success'); } catch(err) { alert('Order failed.'); } };
    root.appendChild(s);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

function OrderSuccessPage(root) {
    root.innerHTML = '';
    const s = document.createElement('section');
    s.className = 'section';
    s.style.textAlign = 'center';
    s.style.padding = '4rem';
    s.innerHTML = `<div style="font-size:4rem;margin-bottom:var(--space-md)">✓</div><h1 style="font-family:var(--font-serif)">Order Placed!</h1><p style="color:var(--color-text-muted);margin-bottom:var(--space-lg)">Thank you. We'll contact you soon.</p><a href="/shop" data-link class="btn btn-primary">Continue Shopping</a>`;
    root.appendChild(s);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

function NotFoundPage(root) {
    root.innerHTML = `<div class="section" style="text-align:center;padding:4rem"><h1 style="font-size:4rem;font-family:var(--font-serif)">404</h1><p style="color:var(--color-text-muted);margin-bottom:var(--space-lg)">Page not found</p><a href="/" data-link class="btn btn-primary">Go Home</a></div>`;
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}
