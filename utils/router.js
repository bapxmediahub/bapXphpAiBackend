/**
 * Simple client-side router
 */
const Router = {
    routes: {},
    reg(path, fn) { this.routes[path] = fn; },
    nav(path) { history.pushState({}, '', path); window.scrollTo(0, 0); this.render(); },
    render() {
        const path = window.location.pathname;
        const fn = this.routes[path] || this.routes['*'];
        const root = document.getElementById('root');
        if (fn && root) fn(root);
        document.querySelectorAll('.cart-badge').forEach(e => { e.textContent = Cart.count; e.style.display = Cart.count > 0 ? '' : 'none'; });
    },
    init() {
        window.addEventListener('popstate', () => this.render());
        document.addEventListener('click', e => {
            const a = e.target.closest('a[data-link]');
            if (a) { e.preventDefault(); this.nav(a.getAttribute('href')); }
        });
        this.render();
    }
};
