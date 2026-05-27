/**
 * Astrologer Card component
 */
function AstroCard(a) {
    const c = document.createElement('article');
    c.className = 'astrologer-card';
    c.innerHTML = `<div class="astrologer-card__header"><div class="astrologer-card__photo-wrap"><img class="astrologer-card__photo" src="${img(a.photo_url, a.name)}" alt="${esc(a.name)}" loading="lazy"></div><div><h3 class="astrologer-card__name">${esc(a.name)}</h3><p class="astrologer-card__speciality">${esc(a.speciality||'Vedic Astrology')}</p></div></div>
    <div class="astrologer-card__body"><div class="astrologer-card__stat"><span>Experience</span><span>${a.experience_years||'N/A'} yrs</span></div><div class="astrologer-card__stat"><span>Languages</span><span>${(a.languages||[]).slice(0,2).join(', ')}</span></div></div>
    <div class="astrologer-card__footer"><span class="astrologer-card__price">${fmt(a.price||0)}</span><a href="/astrologers/${a.slug}" data-link class="btn btn-sm btn-primary">Book Now</a></div>`;
    return c;
}
