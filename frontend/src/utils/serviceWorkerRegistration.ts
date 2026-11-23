/**
 * Nexus Enterprise AI CRM SaaS - Service Worker Registration
 */

export function registerServiceWorker(): void {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', () => {
    const swUrl = '/sw.js';

    navigator.serviceWorker
      .register(swUrl)
      .then((registration) => {
        // Registration successful
        registration.onupdatefound = () => {
          const installingWorker = registration.installing;
          if (installingWorker == null) return;

          installingWorker.onstatechange = () => {
            if (installingWorker.state === 'installed') {
              if (navigator.serviceWorker.controller) {
                // New content available; will be used on next refresh
                console.log('[Nexus PWA] New app version available.');
              } else {
                // Content cached for offline use
                console.log('[Nexus PWA] Content cached for offline app shell.');
              }
            }
          };
        };
      })
      .catch((error) => {
        console.warn('[Nexus PWA] Service worker registration failed:', error);
      });
  });
}

export function unregisterServiceWorker(): void {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready
      .then((registration) => {
        registration.unregister();
      })
      .catch((error) => {
        console.error(error.message);
      });
  }
}
