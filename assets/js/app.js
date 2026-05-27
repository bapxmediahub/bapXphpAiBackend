/**
 * Sri Panchami Spiritual - React Frontend
 * 
 * Uses React via CDN - no build step required
 * Compatible with Hostinger shared hosting
 * 
 * Backend: PHP API endpoints (/api/*)
 * Data: JSON file-based persistence
 */

// ============================================
// API Service Layer
// ============================================
const API = {
    baseUrl: '/api',
    
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            headers: { 'Content-Type': 'application/json' },
            ...options
        };
        
        try {
            const response = await fetch(url, config);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    get(endpoint) {
        return this.request(endpoint);
    },
    
    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
};

// ============================================
// State Management (Simple Store)
// ============================================
const Store = {
    state: {
        cart: JSON.parse(localStorage.getItem('cart') || '[]'),
        user: JSON.parse(localStorage.getItem('user') || 'null'),
        products: [],
        categories: [],
        astrologers: [],
        temples: []
    },
    
    listeners: [],
    
    subscribe(listener) {
        this.listeners.push(listener);
        return () => {
            this.listeners = this.listeners.filter(l => l !== listener);
        };
    },
    
    notify() {
        this.listeners.forEach(listener => listener(this.state));
    },
    
    setState(updates) {
        this.state = { ...this.state, ...updates };
        this.notify();
    },
    
    // Cart methods
    addToCart(product, qty = 1) {
        const existing = this.state.cart.find(item => item.slug === product.slug);
        if (existing) {
            existing.qty += qty;
        } else {
            this.state.cart.push({ ...product, qty });
        }
        this.saveCart();
        this.notify();
    },
    
    removeFromCart(slug) {
        this.state.cart = this.state.cart.filter(item => item.slug !== slug);
        this.saveCart();
        this.notify();
    },
    
    updateCartQty(slug, qty) {
        const item = this.state.cart.find(i => i.slug === slug);
        if (item) item.qty = Math.max(1, qty);
        this.saveCart();
        this.notify();
    },
    
    clearCart() {
        this.state.cart = [];
        this.saveCart();
        this.notify();
    },
    
    saveCart() {
        localStorage.setItem('cart', JSON.stringify(this.state.cart));
    },
    
    get cartTotal() {
        return this.state.cart.reduce((sum, item) => {
            const price = item.offer_price || item.price || 0;
            return sum + (price * item.qty);
        }, 0);
    },
    
    get cartCount() {
        return this.state.cart.reduce((sum, item) => sum + item.qty, 0);
    }
};

// ============================================
// Router (Simple SPA Router)
// ============================================
const Router = {
    routes: {},
    currentPath: window.location.pathname,
    
    register(path, component) {
        this.routes[path] = component;
    },
    
    navigate(path) {
        window.history.pushState({}, '', path);
        this.currentPath = path;
        this.render();
    },
    
    render() {
        const path = window.location.pathname;
        const component = this.routes[path] || this.routes['/404'];
        if (component) {
            const root = document.getElementById('root');
            if (root) {
                const element = React.createElement(component);
                ReactDOM.render(element, root);
            }
        }
    },
    
    init() {
        window.addEventListener('popstate', () => this.render());
        this.render();
    }
};

// ============================================
// Utility Functions
// ============================================
const Utils = {
    formatPrice(amount) {
        return '₹' + Number(amount).toLocaleString('en-IN');
    },
    
    formatDate(timestamp) {
        return new Date(timestamp * 1000).toLocaleDateString('en-IN', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    },
    
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },
    
    getAssetUrl(path) {
        return path || 'https://placehold.co/400x400/fdfbf7/8c7e6d?text=Product';
    }
};

// ============================================
// Export for use in components
window.AppAPI = API;
window.AppStore = Store;
window.AppRouter = Router;
window.AppUtils = Utils;
