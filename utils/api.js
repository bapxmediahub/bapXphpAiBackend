/**
 * Core utilities: API client, Cart, formatters
 */
const API = {
    get: (ep) => fetch('/api' + ep).then(r => r.json()),
    post: (ep, d) => fetch('/api' + ep, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(d) }).then(r => r.json())
};

const Cart = {
    items: JSON.parse(localStorage.getItem('cart') || '[]'),
    save() { localStorage.setItem('cart', JSON.stringify(this.items)); this.update(); },
    add(p, q=1) { const x = this.items.find(i => i.slug === p.slug); x ? x.qty += q : this.items.push({...p, qty:q}); this.save(); },
    remove(slug) { this.items = this.items.filter(i => i.slug !== slug); this.save(); },
    updateQty(slug, q) { const i = this.items.find(i => i.slug === slug); if(i) i.qty = Math.max(1, q); this.save(); },
    clear() { this.items = []; this.save(); },
    get total() { return this.items.reduce((s, i) => s + (i.offer_price || i.price) * i.qty, 0); },
    get count() { return this.items.reduce((s, i) => s + i.qty, 0); },
    update() { document.querySelectorAll('.cart-badge').forEach(e => { e.textContent = this.count; e.style.display = this.count > 0 ? '' : 'none'; }); }
};

const fmt = n => '₹' + Number(n).toLocaleString('en-IN');
const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
const img = (src, alt) => src || 'https://placehold.co/400x400/fdfbf7/8c7e6d?text=' + encodeURIComponent(alt || 'Image');
