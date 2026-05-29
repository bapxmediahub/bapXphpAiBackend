/**
 * Site Header component
 */
function Header() {
    const h = document.createElement('header');
    h.className = 'site-header';
    h.innerHTML = `
        <a href="/" class="brand" data-link><img src="/assets/images/logo-small.jpeg" width="52" height="52" alt="Logo"><span>Sri Panchami Spiritual</span></a>
        <button class="menu-toggle" id="menu-toggle" aria-label="Menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <nav id="primary-nav">
            <a href="/" data-link>Home</a>
            <a href="/shop" data-link>Shop</a>
            <a href="/temples" data-link>Temples</a>
            <a href="/astrologers" data-link>Astrologers</a>
            <a href="/about" data-link>About Us</a>
            <a href="/contact" data-link>Contact Us</a>
        </nav>
        <div class="header-actions">
            <button class="cart-btn" id="cart-btn-header" aria-label="Cart">🛒<span class="cart-badge" style="${Cart.count>0?'':'display:none'}">${Cart.count}</span></button>
        </div>`;
    h.querySelector('#cart-btn-header').onclick = () => Router.nav('/cart');
    h.querySelector('#menu-toggle').onclick = () => h.querySelector('#primary-nav').classList.toggle('open');
    return h;
}
