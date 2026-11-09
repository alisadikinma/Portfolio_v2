// Kill-switch service worker (PF-2 phase 5B).
// The old worker cache-first-served images/videos for the retired public SPA. The
// public site is now Astro (web/), on the same origin, so a stale worker must not
// keep intercepting its media. Installing this version clears every media cache and
// unregisters the worker; browsers fetch /sw.js on navigation, so it self-heals.

self.addEventListener('install', () => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const names = await caches.keys()
      await Promise.all(names.filter((n) => n.startsWith('media-cache-')).map((n) => caches.delete(n)))
      await self.registration.unregister()
    })()
  )
})
