const CACHE_NAME = 'school-pwa-v2'; // Changed version to force update
const urlsToCache = [
  '/school_system1/assets/css/style.css',
  '/school_system1/assets/js/main.js'
  // Removed index.php to prevent infinite redirect loops!
];

self.addEventListener('install', event => {
  self.skipWaiting(); // Force activate new service worker immediately
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('activate', event => {
  // Clear old caches specifically the one causing the redirect loop
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  // Only cache GET requests and static assets. Bypass caching for PHP pages!
  if (event.request.method !== 'GET' || event.request.url.includes('.php')) {
      return; 
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response; // Cache
        }
        return fetch(event.request); // Network
      })
  );
});
