const CACHE_VERSION = 'ricosty-pwa-v2';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/pwa/icons/icon-192.png',
    '/pwa/icons/icon-512.png',
    '/pwa/icons/icon-maskable-512.png',
    '/pwa/icons/apple-touch-icon.png',
];

const NEVER_CACHE_PREFIXES = [
    '/bioimpedance',
    '/r/',
    '/login',
    '/logout',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('ricosty-pwa-') && key !== STATIC_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type !== 'CLEAR_OPERATIONAL_CACHES') return;

    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith('ricosty-pwa-')).map((key) => caches.delete(key)))),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    const isSameOrigin = url.origin === self.location.origin;
    const isSensitivePath = isSameOrigin && NEVER_CACHE_PREFIXES.some((prefix) => url.pathname === prefix || url.pathname.startsWith(prefix));

    if (isSensitivePath) return;

    if (request.mode === 'navigate') {
        if (!isSameOrigin || !url.pathname.startsWith('/app')) return;

        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    const isBuildAsset = isSameOrigin && url.pathname.startsWith('/build/assets/');
    const isPwaAsset = isSameOrigin && (url.pathname.startsWith('/pwa/') || url.pathname === '/manifest.webmanifest' || url.pathname === OFFLINE_URL);
    const isBrandAsset = isSameOrigin && url.pathname.startsWith('/images/brand/');
    const isFontAsset = url.hostname === 'fonts.bunny.net' && ['font', 'style'].includes(request.destination);

    if (!isBuildAsset && !isPwaAsset && !isBrandAsset && !isFontAsset) return;

    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) return cachedResponse;

            return fetch(request).then((networkResponse) => {
                if (!networkResponse || (!networkResponse.ok && networkResponse.type !== 'opaque')) return networkResponse;

                const responseToCache = networkResponse.clone();
                caches.open(STATIC_CACHE).then((cache) => cache.put(request, responseToCache));

                return networkResponse;
            });
        }),
    );
});
