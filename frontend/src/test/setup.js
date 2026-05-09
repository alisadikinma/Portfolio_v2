/**
 * Global setup for Vitest + jsdom.
 *
 * jsdom doesn't ship matchMedia, IntersectionObserver, or
 * ResizeObserver. Components under test that read these APIs (calendar
 * heatmap responsive checks, scroll observers in admin views) need
 * harmless stubs to avoid `undefined is not a function` failures.
 *
 * Keep this file lean — only add stubs that components actually need.
 * Each addition deserves a comment explaining the consumer.
 */

// matchMedia — used by AdminLayout responsive sidebar logic and the
// calendar's mobile vs desktop breakpoint detection.
if (typeof window !== 'undefined' && typeof window.matchMedia !== 'function') {
  window.matchMedia = (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  })
}

// IntersectionObserver — used by scroll-reveal directives and lazy loaders.
if (typeof window !== 'undefined' && typeof window.IntersectionObserver !== 'function') {
  class IntersectionObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
    takeRecords() { return [] }
  }
  window.IntersectionObserver = IntersectionObserverStub
  globalThis.IntersectionObserver = IntersectionObserverStub
}

// ResizeObserver — used by chart sizing and dynamic textarea growth.
if (typeof window !== 'undefined' && typeof window.ResizeObserver !== 'function') {
  class ResizeObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
  window.ResizeObserver = ResizeObserverStub
  globalThis.ResizeObserver = ResizeObserverStub
}
