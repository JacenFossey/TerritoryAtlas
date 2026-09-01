const CACHE_PREFIX = 'territory-atlas-';
const SHELL_CACHE = `${CACHE_PREFIX}shell-v1`;
const SHELL_FILES = [
    '/',
    '/manifest.webmanifest',
    '/icons/app-icon-192.png',
    '/icons/app-icon-512.png',
    '/icons/apple-touch-icon.png',
];

const cacheBuildAssets = async (cache) => {
    try {
        const response = await fetch('/build/manifest.json', { cache: 'no-store' });

        if (!response.ok) {
            return;
        }

        const manifest = await response.json();
        const assetUrls = [...new Set(
            Object.values(manifest)
                .map((entry) => entry.file)
                .filter(Boolean)
                .map((file) => `/build/${file}`),
        )];

        await cache.addAll(['/build/manifest.json', ...assetUrls]);
    } catch {
        // The shell remains useful when build metadata is temporarily unavailable.
    }
};

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(SHELL_CACHE);

        await cache.addAll(SHELL_FILES);
        await cacheBuildAssets(cache);
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();
        const oldCacheNames = cacheNames.filter(
            (cacheName) => cacheName.startsWith(CACHE_PREFIX) && cacheName !== SHELL_CACHE,
        );

        await Promise.all(oldCacheNames.map((cacheName) => caches.delete(cacheName)));
        await self.clients.claim();
    })());
});

const networkFirstNavigation = async (request) => {
    const cache = await caches.open(SHELL_CACHE);

    try {
        const response = await fetch(request);

        if (response.ok) {
            await cache.put('/', response.clone());
        }

        return response;
    } catch (error) {
        const cachedResponse = await cache.match('/');

        if (cachedResponse) {
            return cachedResponse;
        }

        throw error;
    }
};

const networkFirst = async (request) => {
    const cache = await caches.open(SHELL_CACHE);

    try {
        const response = await fetch(request);

        if (response.ok) {
            await cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        const cachedResponse = await cache.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        throw error;
    }
};

const cacheFirst = async (request) => {
    const cachedResponse = await caches.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(SHELL_CACHE);
        await cache.put(request, response.clone());
    }

    return response;
};

const staleWhileRevalidate = async (request, event) => {
    const cache = await caches.open(SHELL_CACHE);
    const cachedResponse = await cache.match(request);
    const updatedResponse = fetch(request).then(async (response) => {
        if (response.ok) {
            await cache.put(request, response.clone());
        }

        return response;
    });

    if (cachedResponse) {
        event.waitUntil(updatedResponse.catch(() => undefined));

        return cachedResponse;
    }

    return updatedResponse;
};

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));

        return;
    }

    if (url.pathname.startsWith('/geo/')) {
        event.respondWith(staleWhileRevalidate(request, event));

        return;
    }

    if (url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(networkFirst(request));

        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));
    }
});
