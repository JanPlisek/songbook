const CACHE_NAME = 'zpevnik-v1';
const ASSETS_TO_CACHE = [
    'index.php',
    'list.php',
    'interprets.php',
    'assets/css/main.css',
    'assets/css/song.css',
    'assets/js/chord-visualizer.js',
    'https://fonts.googleapis.com/css2?family=Martian+Mono:wght@400;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0'
];

// Install event - caching basic assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// Activate event - cleaning up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.filter(name => name !== CACHE_NAME).map(name => caches.delete(name))
            );
        })
    );
});

// Fetch event - Stale-While-Revalidate for CSS/JS, Network-First for songs
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Don't cache admin pages or API calls that shouldn't be cached
    if (url.pathname.includes('admin') || url.pathname.includes('edit')) {
        return;
    }

    // Network-First strategy for song pages and API
    if (url.pathname.endsWith('song.php') || url.pathname.endsWith('search-api.php')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    const clonedResponse = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clonedResponse));
                    return response;
                })
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // Stale-While-Revalidate for other assets
    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            const fetchPromise = fetch(event.request).then(networkResponse => {
                caches.open(CACHE_NAME).then(cache => cache.put(event.request, networkResponse.clone()));
                return networkResponse;
            });
            return cachedResponse || fetchPromise;
        })
    );
});
