/**
 * Nexus Enterprise AI CRM SaaS - Progressive Web App Service Worker
 * 
 * Strict Security Policy:
 * Static assets (scripts, styles, icons) are safely cached for offline shell access.
 * Sensitive CRM API responses (/api/*) and private customer data are NEVER cached in CacheStorage.
 */

const CACHE_NAME = 'nexus-crm-static-v1.0.0';

const PRECACHE_ASSETS = [
  '/',
  '/index.html',
  '/manifest.webmanifest',
  '/icons/icon.svg',
];

// 1. Install lifecycle: pre-cache application shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// 2. Activate lifecycle: purge older cache versions
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((name) => {
          if (name !== CACHE_NAME) {
            return caches.delete(name);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// 3. Fetch interceptor
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // SECURITY ENFORCEMENT:
  // Never cache API responses, private customer records, or authenticated endpoints
  if (
    url.pathname.startsWith('/api') ||
    url.pathname.includes('/api/v1') ||
    request.headers.has('Authorization') ||
    request.method !== 'GET'
  ) {
    // Pure network pass-through without browser caching
    return;
  }

  // Handle SPA Navigation requests: Network-first with offline fallback to cached shell
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(async () => {
        const cache = await caches.open(CACHE_NAME);
        const cachedShell = await cache.match('/index.html');
        return cachedShell || new Response('Offline - Nexus CRM requires network connectivity', {
          status: 503,
          headers: { 'Content-Type': 'text/plain' },
        });
      })
    );
    return;
  }

  // Handle Static Assets (JS, CSS, SVGs, Fonts, Images): Stale-While-Revalidate / Cache-First
  if (
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.includes('/assets/')
  ) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          // Asynchronously update cache in background
          fetch(request).then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              caches.open(CACHE_NAME).then((cache) => cache.put(request, networkResponse));
            }
          }).catch(() => {});
          return cachedResponse;
        }

        return fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const clone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return networkResponse;
        });
      })
    );
  }
});

// 4. Push Notification Event Listener
self.addEventListener('push', (event) => {
  if (!event.data) return;

  try {
    const data = event.data.json();
    const title = data.title || 'Nexus Enterprise CRM';
    const options = {
      body: data.body || 'New customer activity recorded',
      icon: data.icon || '/icons/icon.svg',
      badge: '/icons/icon.svg',
      tag: data.tag || 'crm-notification',
      data: {
        url: data.url || '/',
      },
      vibrate: [100, 50, 100],
    };

    event.waitUntil(self.registration.showNotification(title, options));
  } catch (err) {
    const text = event.data.text();
    event.waitUntil(
      self.registration.showNotification('Nexus Enterprise CRM', {
        body: text,
        icon: '/icons/icon.svg',
      })
    );
  }
});

// 5. Notification Click Handler with Deep-Link Navigation
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      // Focus existing tab if open
      for (const client of windowClients) {
        if (client.url.includes(targetUrl) && 'focus' in client) {
          return client.focus();
        }
      }
      // Otherwise open new window
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
