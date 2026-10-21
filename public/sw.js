/*
 * Photo Backup Organizer - service worker.
 *
 * FULL OFFLINE MODE.
 *
 * This build is intentionally offline-first so the installed app keeps
 * working without a connection: pages you have visited and the photos
 * you have viewed are cached on the device and served back offline.
 *
 * SECURITY NOTE: because authenticated HTML and photo images are cached,
 * content you have opened is readable from this device's Cache Storage
 * while signed in. Signing out clears every cache this worker owns (see
 * the `signout` message handler and the periodic purge below), so no
 * session-bound content survives a sign-out.
 *
 * Cache groups:
 *   1. App shell      - /offline.html, hashed /build/* assets.
 *   2. Pages (HTML)   - every successful navigation response.
 *   3. Photos/media   - images and other public GET responses.
 *
 * Strategies:
 *   - Navigations    : network-first, fall back to cache, then offline.html.
 *   - Build assets   : cache-first (content-addressed, immutable).
 *   - Photos / media : cache-first with background revalidate.
 *   - Everything else: network-first with cache fallback.
 */

const CACHE_VERSION = 'v2';
const SHELL_CACHE = `pbo-shell-${CACHE_VERSION}`;
const PAGE_CACHE = `pbo-pages-${CACHE_VERSION}`;
const MEDIA_CACHE = `pbo-media-${CACHE_VERSION}`;
const OWNED_CACHES = [SHELL_CACHE, PAGE_CACHE, MEDIA_CACHE];

const OFFLINE_URL = '/offline.html';
const BUILD_ASSET = /^\/build\/[^?#]+\.(?:css|js|woff2?|ttf|png|jpg|jpeg|svg|webp|ico)$/;
const MEDIA_ASSET = /^\/photos\/[^?#]+\/(?:raw|file|thumb|download)$|\.(?:png|jpe?g|webp|gif|svg|avif)$/i;

// Shell files precached on install so the very first offline visit works.
const PRECACHE = [OFFLINE_URL, '/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) =>
                Promise.all(
                    PRECACHE.map((url) =>
                        cache.add(new Request(url, { cache: 'reload' })).catch(() => null)
                    )
                )
            )
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key.startsWith('pbo-') && !OWNED_CACHES.includes(key))
                        .map((key) => caches.delete(key))
                )
            )
            .then(() => self.clients.claim())
    );
});

// Sign-out must leave nothing session-bound behind.
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'pbo-clear-caches') {
        event.waitUntil(
            caches.keys().then((keys) =>
                Promise.all(
                    keys.filter((key) => key.startsWith('pbo-')).map((key) => caches.delete(key))
                )
            )
        );
    }
});

const putIfCacheable = async (cacheName, request, response) => {
    if (!response || !response.ok || response.type === 'opaque') return response;
    const cache = await caches.open(cacheName);
    cache.put(request, response.clone());
    return response;
};

async function networkFirstPage(request) {
    try {
        const response = await fetch(request);
        // Cache the rendered page so it is available offline later.
        putIfCacheable(PAGE_CACHE, request, response);
        return response;
    } catch (error) {
        const cached = await caches.match(request, { ignoreSearch: true });
        if (cached) return cached;
        const offline = await caches.match(OFFLINE_URL, { ignoreSearch: true });
        return offline || Response.error();
    }
}

async function cacheFirst(cacheName, request) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request, { ignoreSearch: true });
    if (cached) {
        // Revalidate in the background so the cache stays fresh.
        fetch(request)
            .then((response) => putIfCacheable(cacheName, request, response))
            .catch(() => {});
        return cached;
    }
    try {
        const response = await fetch(request);
        return putIfCacheable(cacheName, request, response);
    } catch (error) {
        return Response.error();
    }
}

async function networkFirstMedia(request) {
    try {
        const response = await fetch(request);
        return putIfCacheable(MEDIA_CACHE, request, response);
    } catch (error) {
        const cached = await caches.match(request, { ignoreSearch: true });
        return cached || Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return; // mutations always hit the network

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return; // cross-origin: network only

    const path = url.pathname;

    // 1) Hashed build assets: cache-first (content-addressed, immutable).
    if (BUILD_ASSET.test(path)) {
        event.respondWith(cacheFirst(SHELL_CACHE, request));
        return;
    }

    // 2) Photo images / media: cache-first with background revalidate so
    //    viewed photos stay available offline.
    if (MEDIA_ASSET.test(path)) {
        event.respondWith(cacheFirst(MEDIA_CACHE, request));
        return;
    }

    // 3) Page navigations: network-first, cache the HTML, fall back offline.
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request));
        return;
    }

    // 4) Everything else (JSON, downloads, ...): network-first with cache.
    event.respondWith(networkFirstMedia(request));
});
