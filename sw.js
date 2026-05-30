const CACHE_VERSION = 'gunaso-v7';
const CACHE_NAME = `${CACHE_VERSION}`;
const CORE_ASSETS = [
  '/',
  '/index.php',
  '/manifest.webmanifest',
  '/images/icon-192x192.png',
  '/images/icon-512x512.png',
];

// Install event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(CORE_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.map(key => {
        if (key !== CACHE_NAME) return caches.delete(key);
      }))
    ).then(() => self.clients.claim())
  );
});

// Fetch event — network first, cache fallback
self.addEventListener('fetch', (event) => {
  // Only handle GET requests for http/https
  if (event.request.method !== 'GET' || !/^https?:/.test(event.request.url)) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Clone & cache valid responses only
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      })
      .catch(() =>
        caches.match(event.request).then(cached => cached || caches.match('/index.php'))
      )
  );
});

// Allow immediate activation
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
