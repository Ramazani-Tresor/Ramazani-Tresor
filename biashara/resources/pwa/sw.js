/*
 * Biashara — Service Worker.
 * Stratégies :
 *  - ressources statiques (CSS, JS, polices, images) : cache d'abord ;
 *  - pages HTML : réseau d'abord, repli sur la dernière version en cache, puis page « hors ligne » ;
 *  - API JSON : jamais mises en cache par le Service Worker (les données hors ligne vivent dans IndexedDB).
 */
const VERSION = 'bz-v1';
const STATIC = `${VERSION}-static`;
const PAGES = `${VERSION}-pages`;
const scope = self.registration.scope.replace(/\/$/, '');
const PRECACHE = [
    '/vendor/bootstrap/bootstrap.min.css', '/vendor/bootstrap/bootstrap.bundle.min.js',
    '/vendor/bootstrap-icons/bootstrap-icons.min.css', '/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
    '/css/app.css', '/js/app.js', '/js/offline-store.js', '/js/pos.js', '/images/logo.svg', '/images/icon-192.png', '/hors-ligne',
].map(p => scope + p);

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => !k.startsWith(VERSION)).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.includes('/api/')) return;

    if (/\.(css|js|woff2?|png|svg|jpg|jpeg|webp|ico)$/.test(url.pathname)) {
        event.respondWith(caches.match(req, { ignoreSearch: true }).then(hit => hit || fetch(req).then(res => {
            if (res.ok) { const copy = res.clone(); caches.open(STATIC).then(c => c.put(req, copy)); }
            return res;
        })));
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).then(res => {
            // Seul le point de vente est conservé pour un usage hors ligne.
            if (res.ok && url.pathname.endsWith('/caisse-vente')) { const copy = res.clone(); caches.open(PAGES).then(c => c.put(req, copy)); }
            return res;
        }).catch(() => caches.match(req, { ignoreSearch: true }).then(hit => hit || caches.match(scope + '/hors-ligne'))));
    }
});
