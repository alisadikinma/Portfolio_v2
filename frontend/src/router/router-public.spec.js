import { describe, it, expect } from 'vitest'
import router from './index'

// PF-2 phase 5B: public pages live in the Astro site (web/). The SPA keeps only
// /login, /newsletter/unsubscribe, /admin... and the catch-all.
const ALLOWED_EXACT = new Set(['/login', '/newsletter/unsubscribe', '/'])

function isAllowed(path) {
  if (ALLOWED_EXACT.has(path)) return true
  if (path === '/admin' || path.startsWith('/admin/')) return true
  if (path === '/:pathMatch(.*)*') return true
  return false
}

describe('router has no public routes (PF-2 5B)', () => {
  it('only registers login, newsletter unsubscribe, admin and the catch-all', () => {
    const paths = router.getRoutes().map((r) => r.path)
    const publicPaths = paths.filter((p) => !isAllowed(p))
    expect(publicPaths).toEqual([])
  })

  it('has no /:lang routes', () => {
    const paths = router.getRoutes().map((r) => r.path)
    expect(paths.filter((p) => p.startsWith('/:lang'))).toEqual([])
  })

  it('redirects "/" to /admin for local dev', () => {
    const root = router.getRoutes().find((r) => r.path === '/')
    expect(root).toBeDefined()
    const target = typeof root.redirect === 'function' ? root.redirect({}) : root.redirect
    expect(target).toBe('/admin')
  })

  it('resolves an unknown public path to the catch-all, not a public page', () => {
    expect(router.resolve('/id/blog/some-post').name).toBe('not-found')
    expect(router.resolve('/faq').name).toBe('not-found')
  })

  it('keeps login, unsubscribe and admin reachable', () => {
    expect(router.resolve('/login').name).toBe('login')
    expect(router.resolve('/newsletter/unsubscribe').name).toBe('newsletter-unsubscribe')
    expect(router.resolve('/admin').name).toBe('admin')
    expect(router.resolve('/admin/posts').name).toBe('admin-posts')
  })
})
