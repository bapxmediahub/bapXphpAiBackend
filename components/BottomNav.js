/**
 * Mobile Bottom Navigation component
 */
function BottomNav() {
    const n = document.createElement('nav');
    n.className = 'bottom-nav';
    const path = window.location.pathname;
    const items = [{p:'/',i:'🏠',l:'Home'},{p:'/shop',i:'🛍️',l:'Shop'},{p:'/temples',i:'🛕',l:'Temples'},{p:'/astrologers',i:'⭐',l:'Astro'},{p:'/cart',i:'🛒',l:'Cart'}];
    n.innerHTML = '<div class="nav-grid">' + items.map(it => `<a href="${it.p}" data-link class="nav-item ${path===it.p?'active':''}"><span class="icon">${it.i}</span><span>${it.l}</span></a>`).join('') + '</div>';
    return n;
}
