const CACHE = 'sps-user-v1';
const PRECACHE = ['/','/shop','/consult','/login'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.map(n => n !== CACHE ? caches.delete(n) : null))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    caches.match(e.request).then(c => c || fetch(e.request).then(r => {
      if (!r || r.status !== 200 || r.type !== 'basic') return r;
      const url = new URL(e.request.url);
      if (!url.pathname.startsWith('/admin') && !url.pathname.startsWith('/api') && !url.pathname.startsWith('/account') && !url.pathname.startsWith('/astrologer')) {
        caches.open(CACHE).then(ca => ca.put(e.request, r.clone()));
      }
      return r;
    }).catch(() => e.request.mode === 'navigate' ? caches.match('/') : new Response('Offline', { status: 503 })))
  );
});
