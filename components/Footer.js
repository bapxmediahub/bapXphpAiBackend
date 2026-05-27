/**
 * Site Footer component
 */
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
