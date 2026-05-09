import { describe, it, expect, vi, beforeEach } from 'vitest'
import { computed, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Mock data composables BEFORE importing the SUT so the component picks
// up the stubs at setup time. Each mocked composable returns refs that
// match the real API surface — empty/zero state.
vi.mock('@/composables/useSocialCalendar', () => ({
  useSocialCalendar: () => ({
    items: computed(() => []),
    isLoading: ref(false),
    isFetching: ref(false),
    error: ref(null),
    refetch: vi.fn(),
  }),
}))
vi.mock('@/composables/useLinkedInDrafts', () => ({
  // Calendar still uses usePostingRules + useRefreshPostingRules from
  // useLinkedInDrafts; both accept a platform key already.
  usePostingRules: () => ({
    daySummaries: computed(() => []),
    lastResearchedAt: ref(null),
    sources: computed(() => []),
    ruleCount: ref(0),
  }),
  useRefreshPostingRules: () => ({
    mutateAsync: vi.fn(),
    isPending: ref(false),
  }),
}))

import LinkedInPostsCalendar from './LinkedInPostsCalendar.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/linkedin-posts', component: { template: '<div />' } },
      { path: '/admin/linkedin-queue', component: { template: '<div />' } },
      { path: '/admin/facebook-posts', component: { template: '<div />' } },
      { path: '/admin/instagram-posts', component: { template: '<div />' } },
      { path: '/admin/tiktok-posts', component: { template: '<div />' } },
      { path: '/admin/linkedin-drafts/:id', name: 'admin-linkedin-draft-detail', component: { template: '<div />' } },
      { path: '/admin/:platform(facebook|instagram|tiktok)-drafts/:id', name: 'admin-cross-post-detail', component: { template: '<div />' } },
    ],
  })
}

async function mountCalendar(props = {}) {
  const router = makeRouter()
  await router.push('/admin/linkedin-posts')
  await router.isReady()
  return mount(LinkedInPostsCalendar, {
    props,
    global: { plugins: [router] },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('LinkedInPostsCalendar — Phase 3 generalization', () => {
  it('embeds the SocialPlatformTabs component with current=linkedin by default', async () => {
    const wrapper = await mountCalendar()
    // Tabs exist and the LinkedIn tab is marked active.
    const tabs = wrapper.findAll('[data-platform]')
    expect(tabs.length).toBe(4)
    const liTab = wrapper.find('[data-platform="linkedin"]')
    expect(liTab.attributes('data-active')).toBe('true')
  })

  it('switches the active platform tab when :platform prop is set to facebook', async () => {
    const wrapper = await mountCalendar({ platform: 'facebook' })
    expect(wrapper.find('[data-platform="facebook"]').attributes('data-active')).toBe('true')
    expect(wrapper.find('[data-platform="linkedin"]').attributes('data-active')).toBe('false')
  })

  it('renders a posting-rules empty-state hint when ruleCount=0', async () => {
    const wrapper = await mountCalendar({ platform: 'instagram' })
    const html = wrapper.html()
    // The hint must mention the artisan command so the operator knows
    // how to seed rules. Surface platform name so it points at the
    // current tab, not always at LinkedIn.
    expect(html).toMatch(/posting-rules:research/i)
    expect(html).toMatch(/instagram/i)
  })

  it('renders a Posts header that is platform-agnostic (no hardcoded "LinkedIn" eyebrow)', async () => {
    const wrapper = await mountCalendar({ platform: 'tiktok' })
    const html = wrapper.html()
    // The page heading should still say "Posts" (generic), and the
    // eyebrow should reflect the active platform — NOT the hardcoded
    // "LinkedIn pipeline" string from the pre-generalization template.
    expect(html).toMatch(/>\s*Posts\s*</)
    expect(html).not.toMatch(/LinkedIn pipeline/i)
    // Eyebrow uses <span class="capitalize">{{ platform }}</span> pipeline.
    // Use wrapper.text() so HTML tags between platform name and "pipeline"
    // are stripped — the visible text reads "tiktok pipeline".
    expect(wrapper.text()).toMatch(/tiktok pipeline/i)
  })
})
