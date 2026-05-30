/**
 * UI Components
 */
function Header() {
    const h = document.createElement('header');
    h.className = 'site-header';
    h.innerHTML = `
        <a href="/" class="brand" data-link><img src="/assets/images/logo-small.jpeg" width="52" height="52" alt="Sri Panchami Spiritual logo"><span>Sri Panchami Spiritual</span></a>
        <button class="menu-toggle" id="menu-toggle" aria-label="Menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <nav id="primary-nav">
            <a href="/" data-link>Home</a>
            <a href="/shop" data-link>Shop</a>
            <a href="/temples" data-link>Temples</a>
            <a href="/astrologers" data-link>Astrologers</a>
            <a href="/about" data-link>About Us</a>
            <a href="/contact" data-link>Contact Us</a>
            <a href="/login" data-link>Login</a>
        </nav>
        <div class="header-actions">
            <a class="cart-btn" href="/cart" data-link aria-label="Cart">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 001.9-1.4L23 6H6"/></svg>
                <span class="cart-badge" style="${Cart.count>0?'':'display:none'}">${Cart.count}</span>
            </a>
        </div>`;
    h.querySelector('#menu-toggle').onclick = () => h.querySelector('#primary-nav').classList.toggle('open');
    return h;
}

function Footer() {
    const f = document.createElement('footer');
    f.className = 'site-footer';
    f.innerHTML = `<div class="footer-grid">
        <div><span class="footer-brand">Sri Panchami Spiritual</span><p class="footer-desc">Authentic spiritual products, sacred jewellery, expert Vedic astrology and temple guidance in Chennai.</p></div>
        <div><h4 class="footer-heading">Shop</h4><ul class="footer-links"><li><a href="/shop" data-link>All Products</a></li><li><a href="/temples" data-link>Temples</a></li><li><a href="/astrologers" data-link>Astrologers</a></li><li><a href="/about" data-link>About Us</a></li></ul></div>
        <div><h4 class="footer-heading">Services</h4><ul class="footer-links"><li><a href="/astrologers" data-link>Astrology</a></li><li><a href="/temples" data-link>Temples</a></li><li><a href="/contact" data-link>Contact</a></li></ul></div>
        <div><h4 class="footer-heading">Contact</h4><ul class="footer-links"><li>23, 1st Cross Street Kothari Nagar</li><li>Ramapuram, Chennai, Tamil Nadu 600089</li><li><a href="mailto:sripanchamispiritual@gmail.com">sripanchamispiritual@gmail.com</a></li></ul></div>
    </div><div class="footer-bottom">© ${new Date().getFullYear()} Sri Panchami Spiritual · Chennai, Tamil Nadu</div>`;
    return f;
}

function BottomNav() {
    const n = document.createElement('nav');
    n.className = 'bottom-nav';
    const path = window.location.pathname;
    const icons = {
        home: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v11h14V10"/><path d="M9 21v-6h6v6"/></svg>',
        shop: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2l1 4h10l1-4"/><path d="M3 6h18l-2 15H5L3 6z"/></svg>',
        temple: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-5a3 3 0 016 0v5"/></svg>',
        astro: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.7 6.6 7.1.6-5.4 4.6 1.7 6.9L12 17l-6.1 3.7 1.7-6.9-5.4-4.6 7.1-.6L12 2z"/></svg>',
        cart: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 001.9-1.4L23 6H6"/></svg>'
    };
    const items = [{p:'/',i:icons.home,l:'Home'},{p:'/shop',i:icons.shop,l:'Shop'},{p:'/temples',i:icons.temple,l:'Temples'},{p:'/astrologers',i:icons.astro,l:'Astro'},{p:'/cart',i:icons.cart,l:'Cart'}];
    n.innerHTML = '<div class="nav-grid">' + items.map(it => `<a href="${it.p}" data-link class="nav-item ${path===it.p?'active':''}"><span class="icon">${it.i}</span><span>${it.l}</span></a>`).join('') + '</div>';
    return n;
}

function ProductCard(p) {
    const a = document.createElement('article');
    a.className = 'product-card';
    const offer = p.offer_price && p.offer_price < p.price;
    a.innerHTML = `<div class="product-card__image"><img src="${img(p.image_url, p.name)}" alt="${esc(p.name)}" loading="lazy">${offer?'<span class="product-card__badge product-card__badge--sale">Sale</span>':''}</div>
    <div class="product-card__body"><h3>${esc(p.name)}</h3><p class="product-card__desc">${esc(p.description)}</p>
    <div class="product-card__price-row"><span class="price">${fmt(p.offer_price||p.price)}</span>${offer?`<span class="old-price">${fmt(p.price)}</span><span class="discount-pct">-${Math.round((1-p.offer_price/p.price)*100)}%</span>`:''}</div>
    <div class="product-card__actions"><a href="/product/${p.slug}" data-link class="btn btn-sm btn-ghost">View</a><button class="btn btn-sm btn-primary btn-addcart">Add to Cart</button></div></div>`;
    a.querySelector('.btn-addcart').onclick = () => Cart.add(p, 1);
    return a;
}

function AstroCard(a) {
    const c = document.createElement('article');
    c.className = 'astrologer-card';
    c.innerHTML = `<div class="astrologer-card__media"><img class="astrologer-card__photo" src="${img(a.photo_url, a.name)}" alt="${esc(a.name)}" loading="lazy"><div class="astrologer-card__media-badge">Live expert</div></div>
    <div class="astrologer-card__body astrologer-card__body--portrait"><div class="astrologer-card__title-row"><h3 class="astrologer-card__name">${esc(a.name)}</h3><span class="astrologer-card__status">Verified</span></div><p class="astrologer-card__speciality">${esc(a.speciality||'Vedic Astrology')}</p><p class="astrologer-card__bio">${esc(a.description||'Experienced astrologer available for private guidance.')}</p><div class="astrologer-card__meta"><span>${a.experience_years||'N/A'} yrs</span><span>${(a.languages||[]).slice(0,2).join(' · ')}</span></div></div>
    <div class="astrologer-card__footer"><span class="astrologer-card__price">5 credits/message · 0.5 credits/sec call</span><div class="astrologer-card__actions"><a href="/astrologers/${a.slug}" data-link class="btn btn-sm btn-ghost">Know More</a><a href="/astrologers/${a.slug}?mode=direct_call" data-link class="btn btn-sm btn-call">Call</a><a href="/astrologers/${a.slug}?mode=text_session" data-link class="btn btn-sm btn-message">Message</a></div></div>`;
    return c;
}

// Page wrapper with header + footer
function Page(content) {
    const wrap = document.createElement('div');
    wrap.appendChild(Header());
    const main = document.createElement('main');
    main.appendChild(content);
    wrap.appendChild(main);
    wrap.appendChild(Footer());
    wrap.appendChild(BottomNav());
    return wrap;
}
