const CACHE = 'sps-user-v2';
const PRECACHE = ['/manifest.json'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.map(n => n !== CACHE ? caches.delete(n) : null))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => caches.match('/')));
    return;
  }
  e.respondWith(
    caches.match(e.request).then(c => c || fetch(e.request).then(r => {
      if (!r || r.status !== 200 || r.type !== 'basic') return r;
      if (!url.pathname.startsWith('/admin') && !url.pathname.startsWith('/api') && !url.pathname.startsWith('/account') && !url.pathname.startsWith('/astrologer') && /\.(?:css|js|webp|png|jpg|jpeg|svg|ico|woff2?)$/i.test(url.pathname)) {
        caches.open(CACHE).then(ca => ca.put(e.request, r.clone()));
      }
      return r;
    }).catch(() => new Response('Offline', { status: 503 })))
  );
});
