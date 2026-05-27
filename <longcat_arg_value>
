/**
 * Sri Panchami Spiritual - Vanilla JS SPA
 * Fast, no dependencies, works offline
 * PHP backend with JSON API
 */

// ============================================
// API Service
// ============================================
const API = {
    async get(endpoint) {
        const res = await fetch('/api' + endpoint);
        return res.json();
    },
    async post(endpoint, data) {
        const res = await fetch('/api' + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    }
};

// ============================================
// Simple Router
// ============================================
const Router = {
    routes: {},
    currentRoute: '/',

    init() {
        window.addEventListener('popstate', () => this.render());
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href^="/"]');
            if (link && !link.target) {
                e.preventDefault();
                this.navigate(link.getAttribute('href'));
            }
        });
        this.render();
    },

    register(path, handler) {
        this.routes[path] = handler;
    },

    navigate(path) {
        window.history.pushState({}, '', path);
        window.scrollTo(0, 0);
        this.render();
    },

    render() {
        const path = window.location.pathname;
        const search = window.location.search;
        const fullPath = path + search;
        this.currentRoute = path;

        // Find matching route
        let handler = this.routes[path];
        
        // Pattern matching for dynamic routes
        if (!handler) {
            for (const [route, fn] of Object.entries(this.routes)) {
                if (route.includes('{')) {
                    const pattern = route.replace(/\{(\w+)\}/g, '([^/]+)');
                    const regex = new RegExp('^' + pattern + '$');
                    if (regex.test(path)) {
                        const matches = path.match(regex);
                        handler = { fn, params: matches.slice(1) };
                        break;
                    }
                }
            }
        }

        // Update nav active state
        document.querySelectorAll('nav a, .bottom-nav a').forEach(a => {
            a.classList.toggle('active', a.getAttribute('href') === path);
        });

        if (handler) {
            const root = document.getElementById('root');
            if (root) {
                if (typeof handler === 'function') {
                    handler(root);
                } else {
                    handler.fn(root, ...handler.params);
                }
            }
        } else {
            // 404
            const root = document.getElementById('root');
            if (root) root.innerHTML = '<div class="section" style="text-align:center;padding:4rem"><h1>404</h1><p>Page not found</p><a href="/" data-link class="btn btn-primary">Go Home</a></div>';
        }
    }
};

// ============================================
// Cart Store
// ============================================
const Cart = {
    items: JSON.parse(localStorage.getItem('cart') || '[]'),

    save() {
        localStorage.setItem('cart', JSON.stringify(this.items));
        this.updateBadge();
    },

    add(product, qty = 1) {
        const existing = this.items.find(i => i.slug === product.slug);
        if (existing) {
            existing.qty += qty;
        } else {
            this.items.push({ ...product, qty });
        }
        this.save();
    },

    remove(slug) {
        this.items = this.items.filter(i => i.slug !== slug);
        this.save();
    },

    updateQty(slug, qty) {
        const item = this.items.find(i => i.slug === slug);
        if (item) item.qty = Math.max(1, qty);
        this.save();
    },

    clear() {
        this.items = [];
        this.save();
    },

    get total() {
        return this.items.reduce((sum, i) => sum + (i.offer_price || i.price) * i.qty, 0);
    },

    get count() {
        return this.items.reduce((sum, i) => sum + i.qty, 0);
    },

    updateBadge() {
        document.querySelectorAll('.cart-count').forEach(el => {
            el.textContent = this.count;
            el.style.display = this.count > 0 ? 'flex' : 'none';
        });
    }
};

// ============================================
// Helpers
// ============================================
const fmt = (n) => '₹' + Number(n).toLocaleString('en-IN');

const esc = (s) => {
    const d = document.createElement('d');
    d.textContent = s;
    return d.innerHTML;
};

const img = (src, alt) => src || `https://placehold.co/400x400/fdfbf7/8c7e6d?text=${encodeURIComponent(alt || 'Product')}`;

// Register components on window
window.API = API;
window.Router = Router;
window.Cart = Cart;
window.fmt = fmt;
window.esc = esc;
window.img = img;
