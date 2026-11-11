const CACHE_NAME = 'proxiservices-public-v1';
const PUBLIC_ASSETS = [
    '/offline.html',
    '/favicon.svg',
    '/apple-touch-icon.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PUBLIC_ASSETS)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('proxiservices-public-') &&
                                key !== CACHE_NAME,
                        )
                        .map((key) => caches.delete(key)),
                ),
            ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(
                async () =>
                    (await caches.match('/offline.html')) ?? Response.error(),
            ),
        );
        return;
    }
    if (
        !['style', 'script', 'image', 'font'].includes(request.destination) &&
        !PUBLIC_ASSETS.includes(url.pathname)
    )
        return;
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && response.type === 'basic') {
                    const copy = response.clone();
                    void caches
                        .open(CACHE_NAME)
                        .then((cache) => cache.put(request, copy));
                }
                return response;
            })
            .catch(
                async () => (await caches.match(request)) ?? Response.error(),
            ),
    );
});
