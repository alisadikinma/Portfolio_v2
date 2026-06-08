// Service Worker — Media Cache
// Caches videos and images on first load, serves from cache on repeat visits

const CACHE_NAME = 'media-cache-v2'

// Patterns to cache (videos + images from public folder)
const CACHEABLE_EXTENSIONS = ['.mp4', '.webp', '.png', '.jpg', '.jpeg', '.svg', '.gif', '.avif']

function isCacheable(url) {
  const parsed = new URL(url)
  // Only cache same-origin requests (skip YouTube thumbnails etc.)
  if (parsed.origin !== self.location.origin) return false
  // Check file extension
  return CACHEABLE_EXTENSIONS.some(ext => parsed.pathname.toLowerCase().endsWith(ext))
}

// Install: pre-cache hero videos (they load on every visit)
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Pre-cache the hero loop (Operator-at-console) + poster — load on every visit
      return cache.addAll([
        '/videos/hero-loop.webm',
        '/videos/hero-loop.mp4',
        '/videos/hero-poster.jpg',
        '/alisadikin-logo.png'
      ]).catch(() => {
        // Non-fatal: files might not exist yet
      })
    })
  )
  // Activate immediately (don't wait for old SW to finish)
  self.skipWaiting()
})

// Activate: clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) => {
      return Promise.all(
        names
          .filter((name) => name.startsWith('media-cache-') && name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      )
    })
  )
  // Take control of all clients immediately
  self.clients.claim()
})

// Fetch: Cache-first strategy for media files
self.addEventListener('fetch', (event) => {
  const { request } = event

  // Only handle GET requests for cacheable media
  if (request.method !== 'GET' || !isCacheable(request.url)) return

  event.respondWith(
    caches.open(CACHE_NAME).then(async (cache) => {
      // 1. Try cache first
      const cached = await cache.match(request)
      if (cached) return cached

      // 2. Not in cache — fetch from network
      try {
        const response = await fetch(request)

        // Only cache successful responses
        if (response.ok) {
          // Clone before caching (response can only be consumed once)
          cache.put(request, response.clone())
        }

        return response
      } catch (error) {
        // Network failed, no cache — return error
        return new Response('Media unavailable', { status: 503 })
      }
    })
  )
})
