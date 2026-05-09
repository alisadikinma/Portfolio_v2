import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

import SocialPlatformTabs from './SocialPlatformTabs.vue'

/**
 * SocialPlatformTabs renders a segmented control of 4 platform tabs
 * (LinkedIn / Facebook / Instagram / TikTok). Used in Posts calendar +
 * Queue list + LinkedIn detail page so operators can hop between
 * platforms without going back to the sidebar.
 *
 * Mode='posts' uses calendar/feed routes; mode='queue' uses queue routes.
 * For FB/IG/TT (where queue + feed share a single CrossPostList route),
 * the tab appends ?scope=feed or ?scope=queue so CrossPostList knows
 * which internal mode to default to.
 */

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/linkedin-posts', component: { template: '<div />' } },
      { path: '/admin/linkedin-queue', component: { template: '<div />' } },
      { path: '/admin/facebook-posts', component: { template: '<div />' } },
      { path: '/admin/instagram-posts', component: { template: '<div />' } },
      { path: '/admin/tiktok-posts', component: { template: '<div />' } },
      { path: '/admin/facebook-queue', component: { template: '<div />' } },
      { path: '/admin/instagram-queue', component: { template: '<div />' } },
      { path: '/admin/tiktok-queue', component: { template: '<div />' } },
    ],
  })
}

async function mountTabs(props) {
  const router = makeRouter()
  await router.push('/admin/linkedin-posts')
  await router.isReady()

  return mount(SocialPlatformTabs, {
    props,
    global: { plugins: [router] },
  })
}

describe('SocialPlatformTabs', () => {
  it('renders exactly 4 platform tabs in canonical order', async () => {
    const wrapper = await mountTabs({ current: 'linkedin', mode: 'posts' })

    const tabs = wrapper.findAll('[data-platform]')
    expect(tabs).toHaveLength(4)

    const order = tabs.map((t) => t.attributes('data-platform'))
    expect(order).toEqual(['linkedin', 'facebook', 'instagram', 'tiktok'])
  })

  it('marks the active tab via aria-current, aria-selected, and a sentinel data attribute', async () => {
    const wrapper = await mountTabs({ current: 'instagram', mode: 'posts' })

    const igTab = wrapper.find('[data-platform="instagram"]')
    const liTab = wrapper.find('[data-platform="linkedin"]')

    expect(igTab.attributes('data-active')).toBe('true')
    expect(igTab.attributes('aria-current')).toBe('page')
    expect(igTab.attributes('aria-selected')).toBe('true')

    expect(liTab.attributes('data-active')).toBe('false')
    expect(liTab.attributes('aria-current')).toBeUndefined()
    // aria-selected MUST be explicit "false" on inactive tabs (ARIA tabs
    // pattern requires every role="tab" element to declare selected state).
    expect(liTab.attributes('aria-selected')).toBe('false')
  })

  it('mode=posts links every platform to /admin/{platform}-posts', async () => {
    const wrapper = await mountTabs({ current: 'linkedin', mode: 'posts' })

    expect(wrapper.find('[data-platform="linkedin"]').attributes('href')).toBe('/admin/linkedin-posts')
    expect(wrapper.find('[data-platform="facebook"]').attributes('href')).toBe('/admin/facebook-posts')
    expect(wrapper.find('[data-platform="instagram"]').attributes('href')).toBe('/admin/instagram-posts')
    expect(wrapper.find('[data-platform="tiktok"]').attributes('href')).toBe('/admin/tiktok-posts')
  })

  it('mode=queue links every platform to /admin/{platform}-queue', async () => {
    const wrapper = await mountTabs({ current: 'linkedin', mode: 'queue' })

    expect(wrapper.find('[data-platform="linkedin"]').attributes('href')).toBe('/admin/linkedin-queue')
    expect(wrapper.find('[data-platform="facebook"]').attributes('href')).toBe('/admin/facebook-queue')
    expect(wrapper.find('[data-platform="instagram"]').attributes('href')).toBe('/admin/instagram-queue')
    expect(wrapper.find('[data-platform="tiktok"]').attributes('href')).toBe('/admin/tiktok-queue')
  })

  it('renders a label for each platform readable by screen readers', async () => {
    const wrapper = await mountTabs({ current: 'linkedin', mode: 'posts' })

    expect(wrapper.find('[data-platform="linkedin"]').text()).toMatch(/LinkedIn/i)
    expect(wrapper.find('[data-platform="facebook"]').text()).toMatch(/Facebook/i)
    expect(wrapper.find('[data-platform="instagram"]').text()).toMatch(/Instagram/i)
    expect(wrapper.find('[data-platform="tiktok"]').text()).toMatch(/TikTok/i)
  })

  it('throws/falls back gracefully when given an unknown platform key as current', async () => {
    // Unknown current platform should not crash the render — all 4 tabs
    // render with data-active="false". This protects against bugs where
    // a parent passes a typo or a stale platform value.
    const wrapper = await mountTabs({ current: 'snapchat', mode: 'posts' })

    const tabs = wrapper.findAll('[data-platform]')
    expect(tabs).toHaveLength(4)

    const activeTabs = tabs.filter((t) => t.attributes('data-active') === 'true')
    expect(activeTabs).toHaveLength(0)
  })
})
