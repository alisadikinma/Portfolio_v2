import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia, setActivePinia } from 'pinia'

import AdminLayout from './AdminLayout.vue'

/**
 * AdminLayout sidebar consolidation test.
 *
 * Goal of Phase 1: collapse 5 social menu entries into 2 (Posts + Queue),
 * grouped under the SOCIAL MEDIA header. Per-platform navigation
 * (Facebook / Instagram / TikTok) lives inside Posts/Queue pages via
 * tab strip — the sidebar must not surface them as separate items.
 */

vi.mock('@/composables/useSiteSettings', () => ({
  useSiteSettings: () => ({
    siteLogo: { value: null },
    fetchSiteSettings: vi.fn().mockResolvedValue(undefined),
  }),
}))

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin', name: 'admin-dashboard', component: { template: '<div />' } },
      { path: '/admin/linkedin-posts', name: 'admin-linkedin-posts', component: { template: '<div />' } },
      { path: '/admin/linkedin-queue', name: 'admin-linkedin-queue', component: { template: '<div />' } },
      { path: '/admin/facebook-posts', name: 'admin-facebook-posts', component: { template: '<div />' } },
      { path: '/admin/instagram-posts', name: 'admin-instagram-posts', component: { template: '<div />' } },
      { path: '/admin/tiktok-posts', name: 'admin-tiktok-posts', component: { template: '<div />' } },
      { path: '/admin/content-engine', name: 'admin-content-engine', component: { template: '<div />' } },
      { path: '/admin/testimonials', name: 'admin-testimonials', component: { template: '<div />' } },
      { path: '/admin/newsletter', name: 'admin-newsletter', component: { template: '<div />' } },
      { path: '/admin/contacts', name: 'admin-contacts', component: { template: '<div />' } },
      { path: '/admin/about', name: 'admin-about', component: { template: '<div />' } },
      { path: '/admin/settings', name: 'admin-settings', component: { template: '<div />' } },
      { path: '/admin/automation/tokens', name: 'admin-automation-tokens', component: { template: '<div />' } },
      { path: '/admin/automation/logs', name: 'admin-automation-logs', component: { template: '<div />' } },
      { path: '/admin/automation/docs', name: 'admin-automation-docs', component: { template: '<div />' } },
      { path: '/admin/menu-items', name: 'admin-menu-items', component: { template: '<div />' } },
      { path: '/admin/page-sections', name: 'admin-page-sections', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
}

async function mountLayout() {
  const router = buildRouter()
  await router.push('/admin')
  await router.isReady()

  return mount(AdminLayout, {
    global: {
      plugins: [createPinia(), router],
      stubs: {
        BaseToast: { template: '<div data-test="toast-stub" />' },
      },
    },
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('AdminLayout sidebar — Phase 1 consolidation', () => {
  it('renders a single SOCIAL MEDIA section header (not LinkedIn + Cross-Post separately)', async () => {
    const wrapper = await mountLayout()
    const html = wrapper.html()

    // The merged header must exist exactly once — case-insensitive.
    const socialHeaderMatches = (html.match(/Social Media/gi) || []).length
    expect(socialHeaderMatches).toBeGreaterThanOrEqual(1)

    // The pre-Phase-1 split headers must NOT be rendered as separate sections.
    expect(html).not.toMatch(/<p[^>]*>\s*LinkedIn\s*<\/p>/i)
    expect(html).not.toMatch(/<p[^>]*>\s*Cross-Post\s*<\/p>/i)
  })

  it('exposes exactly 2 social-media nav entries: Posts + Queue', async () => {
    const wrapper = await mountLayout()

    const links = wrapper.findAll('a[href]')
    const socialHrefs = links
      .map((a) => a.attributes('href'))
      .filter((href) => /\/admin\/(linkedin|facebook|instagram|tiktok)-(posts|queue|drafts)/.test(href))

    // Phase 1 goal: ONLY LinkedIn Posts + LinkedIn Queue surface in the
    // sidebar. FB/IG/TT are reachable via platform tabs inside those
    // pages, NOT as standalone sidebar entries.
    expect(socialHrefs).toContain('/admin/linkedin-posts')
    expect(socialHrefs).toContain('/admin/linkedin-queue')
    expect(socialHrefs).not.toContain('/admin/facebook-posts')
    expect(socialHrefs).not.toContain('/admin/instagram-posts')
    expect(socialHrefs).not.toContain('/admin/tiktok-posts')

    // Hard cap: exactly 2 social nav entries in the sidebar.
    expect(socialHrefs).toHaveLength(2)
  })

  it('renders the Posts and Queue labels as platform-agnostic text', async () => {
    const wrapper = await mountLayout()
    const html = wrapper.html()

    // Labels should read "Posts" and "Queue" — without the LinkedIn
    // prefix from the earlier 5-entry layout. The header above them
    // ("Social Media") supplies the platform context.
    const postsLink = wrapper.find('a[href="/admin/linkedin-posts"]')
    const queueLink = wrapper.find('a[href="/admin/linkedin-queue"]')

    expect(postsLink.exists()).toBe(true)
    expect(queueLink.exists()).toBe(true)

    expect(postsLink.text().trim()).toBe('Posts')
    expect(queueLink.text().trim()).toBe('Queue')

    // The pre-Phase-1 "LinkedIn Posts" / "LinkedIn Queue" labels must
    // be gone — they conflict with the All-Platforms framing.
    expect(html).not.toMatch(/>\s*LinkedIn Posts\s*</)
    expect(html).not.toMatch(/>\s*LinkedIn Queue\s*</)
  })
})
