/**
 * Layout Components
 */

function Layout(root, content) {
    root.innerHTML = '';
    root.appendChild(Header());
    const main = document.createElement('main');
    main.appendChild(content);
    root.appendChild(main);
    root.appendChild(Footer());
    root.appendChild(BottomNav());
}

function Header() {
    const header = document.createElement('header');
    header.className = 'site-header';
    header.innerHTML = `
        <a href="/" class="brand" data-link>
            <img src="/assets/images/logo-square.jpeg" alt="Sri Panchami Spiritual">
            <span>Sri Panchami Spiritual</span>
        </a>
        <button class="menu-toggle" id="menu-toggle" aria-label="Menu">☰</button>
        <nav id="primary-nav">
            <a href="/" data-link>Home</a>
            <a href="/shop" data-link>Shop</a>
            <a href="/temples" data-link>Temples</a>
            <a href="/astrologers" data-link>Astrologers</a>
            <a href="/about" data-link>About Us</a>
            <a href="/contact" data-link>Contact Us</a>
        </nav>
        <div class="header-actions">
            <button class="cart-btn" onclick="Router.navigate('/cart')" aria-label="Cart">
                🛒
                <span class="cart-count" style="${Cart.count ? '' : 'display:none'}">${Cart.count}</span>
            </button>
        </div>
    `;

    header.querySelector('#menu-toggle').addEventListener('click', () => {
        header.querySelector('#primary-nav').classList.toggle('open');
    });

    return header;
}

function Footer() {
    const footer = document.createElement('footer');
    footer.className = 'site-footer';
    footer.innerHTML = `
        <div class="footer-grid">
            <div>
                <span class="footer-brand">Sri Panchami Spiritual</span>
                <p class="footer-desc">Authentic spiritual products, sacred jewellery, expert Vedic astrology and temple guidance in Chennai.</p>
            </div>
            <div>
                <h4 class="footer-heading">Shop</h4>
                <ul class="footer-links">
                    <li><a href="/shop" data-link>All Products</a></li>
                    <li><a href="/temples" data-link>Temples</a></li>
                    <li><a href="/astrologers" data-link>Astrologers</a></li>
                    <li><a href="/about" data-link>About Us</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Services</h4>
                <ul class="footer-links">
                    <li><a href="/astrologers" data-link>Astrology</a></li>
                    <li><a href="/temples" data-link>Temples</a></li>
                    <li><a href="/contact" data-link>Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Contact</h4>
                <ul class="footer-links">
                    <li>23, 1st Cross Street Kothari Nagar</li>
                    <li>Ramapuram, Chennai, Tamil Nadu 600089</li>
                    <li><a href="mailto:sripanchamispiritual@gmail.com">sripanchamispiritual@gmail.com</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">© ${new Date().getFullYear()} Sri Panchami Spiritual · Chennai, Tamil Nadu</div>
    `;
    return footer;
}

function BottomNav() {
    const nav = document.createElement('nav');
    nav.className = 'bottom-nav';
    const path = window.location.pathname;
    const items = [
        { path: '/', icon: '🏠', label: 'Home' },
        { path: '/shop', icon: '🛍️', label: 'Shop' },
        { path: '/temples', icon: '🛕', label: 'Temples' },
        { path: '/astrologers', icon: '⭐', label: 'Astro' },
        { path: '/cart', icon: '🛒', label: 'Cart' }
    ];
    nav.innerHTML = '<div class="nav-grid">' + items.map(item =>
        `<a href="${item.path}" data-link class="nav-item ${path === item.path ? 'active' : ''}">
            <span class="icon">${item.icon}</span><span>${item.label}</span>
        </a>`
    ).join('') + '</div>';
    return nav;
}

function Section({ className = '', children }) {
    const sec = document.createElement('section');
    sec.className = `section ${className}`;
    if (children) sec.append(...children);
    return sec;
}

function Container({ children, narrow = false }) {
    const div = document.createElement('div');
    div.className = `container${narrow ? ' container--narrow' : ''}`;
    if (children) div.append(...children);
    return div;
}

function Grid({ cols = 'auto', children }) {
    const div = document.createElement('div');
    div.className = 'grid';
    if (cols !== 'auto') div.style.gridTemplateColumns = `repeat(auto-fit, minmax(${cols}, 1fr))`;
    if (children) div.append(...children);
    return div;
}

function Panel({ children, textCenter = false }) {
    const div = document.createElement('div');
    div.className = `panel${textCenter ? ' text-center' : ''}`;
    if (children) div.append(...children);
    return div;
}

function ProductCard(p) {
    const article = document.createElement('article');
    article.className = 'product-card';
    const hasOffer = p.offer_price && p.offer_price < p.price;
    article.innerHTML = `
        <div class="product-card__image">
            <img src="${img(p.image_url, p.name)}" alt="${esc(p.name)}" loading="lazy">
            ${hasOffer ? '<span class="product-card__badge product-card__badge--sale">Sale</span>' : ''}
        </div>
        <div class="product-card__body">
            <h3>${esc(p.name)}</h3>
            <p class="product-card__desc">${esc(p.description)}</p>
            <div class="product-card__price-row">
                <span class="price">${fmt(p.offer_price || p.price)}</span>
                ${hasOffer ? `<span class="old-price">${fmt(p.price)}</span><span class="discount-pct">-${Math.round((1 - p.offer_price / p.price) * 100)}%</span>` : ''}
            </div>
            <div class="product-card__actions">
                <a href="/product/${p.slug}" data-link class="btn btn-sm btn-ghost">View</a>
                <button class="btn btn-sm btn-primary" onclick="Cart.add(${JSON.stringify(p).replace(/"/g, '&quot;')}, 1)">Add to Cart</button>
            </div>
        </div>
    `;
    return article;
}

window.Layout = Layout;
window.Header = Header;
window.Footer = Footer;
window.BottomNav = BottomNav;
window.Section = Section;
window.Container = Container;
window.Grid = Grid;
window.Panel = Panel;
window.ProductCard = ProductCard;
