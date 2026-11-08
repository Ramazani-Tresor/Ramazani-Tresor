/*
 * Karo — Service Worker.
 *  - Ressources statiques locales (CSS, JS, icônes, images) : cache d'abord.
 *  - Pages HTML : réseau d'abord ; hors connexion, seule la page « Synchronisation » (consultation limitée et
 *    préparation de saisies) est servie depuis le cache, sinon une page explicative.
 *  - Les réponses JSON de l'API ne sont jamais mises en cache (données sensibles : IndexedDB avec expiration).
 */
const VERSION = 'karo-v2';
const scope = self.registration.scope.replace(/\/$/, '');
const PRECACHE = ['/vendor/bootstrap/bootstrap.min.css', '/vendor/bootstrap/bootstrap.bundle.min.js', '/vendor/bootstrap-icons/bootstrap-icons.min.css',
    '/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2', '/css/app.css', '/js/app.js', '/js/offline.js', '/images/logo.svg', '/images/icon-192.png',
    '/hors-connexion'].map(p => scope + p);

self.addEventListener('install', (e) => e.waitUntil(caches.open(VERSION).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting())));
self.addEventListener('activate', (e) => e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== VERSION).map(x => caches.delete(x)))).then(() => self.clients.claim())));
self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin || url.pathname.includes('/api/')) return;
    if (/\.(css|js|woff2?|png|svg|ico)$/.test(url.pathname)) {
        e.respondWith(caches.match(req, { ignoreSearch: true }).then(hit => hit || fetch(req).then(res => { if (res.ok) { const c = res.clone(); caches.open(VERSION).then(x => x.put(req, c)); } return res; })));
        return;
    }
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).then(res => {
            if (res.ok && url.pathname.endsWith('/synchronisation')) { const c = res.clone(); caches.open(VERSION).then(x => x.put(req, c)); }
            return res;
        }).catch(() => caches.match(req, { ignoreSearch: true }).then(hit => (url.pathname.endsWith('/synchronisation') && hit) || caches.match(scope + '/hors-connexion'))));
    }
});
