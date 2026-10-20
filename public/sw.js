/*
 * Photo Backup Organizer - service worker.
 *
 * SECURITY MODEL (allowlist only - everything else is network-only):
 *   The ONLY things ever written to a Cache Storage are:
 *     1. /offline.html            (precached on install, static markup)
 *     2. /build/* hashed assets   (public, immutable, login-independent:
 *                                  css/js/fonts/images produced by Vite)
 *
 *   Explicitly NEVER cached, by construction and by guard:
 *     - authenticated HTML (all navigations are network-only; on failure the
 *       offline page is returned from cache instead of the stored page)
 *     - photo image responses (never under /build/; also path-guarded)
 *     - downloads (path-guarded)
 *     - anything under /private (path-guarded)
 *     - any request with an Authorization header
 *
 *   Result: nothing tied to a login session is readable offline, and
 *   signing out leaves no session-bound data in Cache Storage.
 */

const CACHE_NAME = 'pbo-static-v1';
const OFFLINE_URL = '/offline.html';
const BUILD_ASSET = /^\/build\/[^?#]+\.(?:css|js|woff2?|ttf|png|jpg|jpeg|svg|webp|ico)$/;

// Path prefixes whose responses must never be stored, even if they ever
// matched the asset pattern above.
const NEVER_CACHE_PREFIXES = ['/private', '/photos/', '/download', '/livewire'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
            )
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return; // network only

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return; // cross-origin: network only
    if (request.headers.has('Authorization')) return; // never store authenticated responses

    const path = url.pathname;
    if (NEVER_CACHE_PREFIXES.some((prefix) => path.startsWith(prefix))) return; // never cached

    // 1) Hashed build assets: cache-first (safe: content-addressed, public).
    if (BUILD_ASSET.test(path)) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) return cached;
                const response = await fetch(request);
                if (response && response.ok && response.type !== 'opaque') {
                    cache.put(request, response.clone());
                }
                return response;
            })
        );
        return;
    }

    // 2) Page navigations: network only - authenticated HTML is never cached.
    //    Offline: show the static offline page instead.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(OFFLINE_URL).then((page) => page || Response.error())
            )
        );
        return;
    }

    // 3) Everything else (HTML, images, downloads, API, ...): network only.
});
