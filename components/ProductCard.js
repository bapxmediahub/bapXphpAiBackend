/**
 * Product Card component
 */
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
