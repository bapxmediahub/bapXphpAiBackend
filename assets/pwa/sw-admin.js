// Owner pages, including the reporting callback and dashboard, require a live session.
self.addEventListener('install', event => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.delete('sps-cache-admin-v1').then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request, {cache: 'no-store'}).catch(() =>
      new Response('Connect to the internet to open the admin workspace.', {
        status: 503,
        headers: {'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store'}
      })
    ));
  }
});
