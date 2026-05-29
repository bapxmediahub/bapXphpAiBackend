/**
 * Astrologer Card component
 */
function AstroCard(a) {
    const c = document.createElement('article');
    c.className = 'astrologer-card';
    c.innerHTML = `<div class="astrologer-card__media"><img class="astrologer-card__photo" src="${img(a.photo_url, a.name)}" alt="${esc(a.name)}" loading="lazy"><div class="astrologer-card__media-badge">Live expert</div></div>
    <div class="astrologer-card__body astrologer-card__body--portrait"><div class="astrologer-card__title-row"><h3 class="astrologer-card__name">${esc(a.name)}</h3><span class="astrologer-card__status">Verified</span></div><p class="astrologer-card__speciality">${esc(a.speciality||'Vedic Astrology')}</p><p class="astrologer-card__bio">${esc(a.description||'Experienced astrologer available for private guidance.')}</p><div class="astrologer-card__meta"><span>${a.experience_years||'N/A'} yrs</span><span>${(a.languages||[]).slice(0,2).join(' · ')}</span></div></div>
    <div class="astrologer-card__footer"><span class="astrologer-card__price">5 credits/message · 0.5 credits/sec call</span><div class="astrologer-card__actions"><a href="/astrologers/${a.slug}" data-link class="btn btn-sm btn-ghost">Know More</a><a href="/astrologers/${a.slug}?mode=direct_call" data-link class="btn btn-sm btn-call">Call</a><a href="/astrologers/${a.slug}?mode=text_session" data-link class="btn btn-sm btn-message">Message</a></div></div>`;
    return c;
}
