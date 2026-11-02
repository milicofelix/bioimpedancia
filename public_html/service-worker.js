const CACHE_VERSION = 'vita-scan-pwa-v6';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const CACHE_PREFIXES = ['vita-scan-pwa-', 'ricosty-pwa-'];
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/pwa/icons/icon-192.png',
    '/pwa/icons/icon-512.png',
    '/pwa/icons/apple-touch-icon.png',
    '/images/brand/bioflow-mark.png',
];

const NEVER_CACHE_PREFIXES = [
    '/bioimpedance',
    '/r/',
    '/login',
    '/logout',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => Promise.all(
            PRECACHE_URLS.map((url) => fetch(new Request(url, { cache: 'reload' })).then((response) => {
                if (!response.ok) throw new Error(`Falha ao preparar recurso offline: ${url}`);
                return cache.put(url, response);
            })),
        )),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => CACHE_PREFIXES.some((prefix) => key.startsWith(prefix)) && key !== STATIC_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }

    if (event.data?.type !== 'CLEAR_OPERATIONAL_CACHES') return;

    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => CACHE_PREFIXES.some((prefix) => key.startsWith(prefix))).map((key) => caches.delete(key)))),
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

    if (isBuildAsset || isFontAsset) {
        event.respondWith(cacheFirst(request));
        return;
    }

    event.respondWith(networkFirst(request));
});

async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) return cachedResponse;

    const networkResponse = await fetch(request);
    await storeCacheableResponse(request, networkResponse);
    return networkResponse;
}

async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request, { cache: 'no-cache' });
        await storeCacheableResponse(request, networkResponse);
        return networkResponse;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) return cachedResponse;
        throw error;
    }
}

async function storeCacheableResponse(request, response) {
    if (!response || (!response.ok && response.type !== 'opaque')) return;
    const cache = await caches.open(STATIC_CACHE);
    await cache.put(request, response.clone());
}
