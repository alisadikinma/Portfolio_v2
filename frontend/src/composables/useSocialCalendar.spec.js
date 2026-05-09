import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, watch } from 'vue'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'

import api from '@/services/api'
import { useSocialCalendar } from './useSocialCalendar'

/**
 * useSocialCalendar — single composable spanning LinkedIn + Facebook +
 * Instagram + TikTok. Two response-shape conventions exist on the
 * backend:
 *   - LinkedIn calendar: { data: { items: [...], from, to, count } }
 *     (LinkedInDraftController::calendar)
 *   - Cross-post calendar: { data: [...rows], meta: { from, to } }
 *     (HandlesCrossPostDraftActions::calendar)
 *
 * The composable normalizes both to a flat `items` array so the view
 * doesn't need to branch.
 */

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

function buildHarness(platformRef, rangeRef) {
  // Mount a tiny harness component so vue-query plugin is wired and the
  // composable runs in a Vue setup() context.
  let exposed
  const Harness = defineComponent({
    setup() {
      exposed = useSocialCalendar(platformRef, rangeRef)
      return () => h('div')
    },
  })
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0 } },
  })
  const wrapper = mount(Harness, {
    global: {
      plugins: [[VueQueryPlugin, { queryClient }]],
    },
  })
  return { wrapper, get: () => exposed }
}

beforeEach(() => {
  api.get.mockReset()
})

describe('useSocialCalendar', () => {
  it('queries the LinkedIn calendar endpoint when platform=linkedin', async () => {
    api.get.mockResolvedValue({
      data: {
        success: true,
        data: {
          items: [
            { id: 1, status: 'published', post_title: 'LI post', pin_at: '2026-05-09T10:00:00Z' },
          ],
          from: '2026-05-01',
          to: '2026-05-31',
          count: 1,
        },
      },
    })

    const platform = ref('linkedin')
    const range = ref({ from: '2026-05-01', to: '2026-05-31' })
    const { get } = buildHarness(platform, range)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/linkedin-posts/calendar', {
      params: { from: '2026-05-01', to: '2026-05-31' },
    })

    const exposed = get()
    expect(exposed.items.value).toHaveLength(1)
    expect(exposed.items.value[0].post_title).toBe('LI post')
  })

  it('queries the cross-post calendar endpoint for facebook + normalizes flat-array shape', async () => {
    api.get.mockResolvedValue({
      data: {
        success: true,
        data: [
          { id: 7, status: 'awaiting_review', post_title: 'FB carousel', pin_at: null },
          { id: 8, status: 'published', post_title: 'FB another', pin_at: '2026-05-10T08:00:00Z' },
        ],
        meta: { from: '2026-05-01', to: '2026-05-31' },
      },
    })

    const platform = ref('facebook')
    const range = ref({ from: '2026-05-01', to: '2026-05-31' })
    const { get } = buildHarness(platform, range)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/facebook-drafts/calendar', {
      params: { from: '2026-05-01', to: '2026-05-31' },
    })

    expect(get().items.value).toHaveLength(2)
    expect(get().items.value.map((r) => r.id)).toEqual([7, 8])
  })

  it('queries instagram + tiktok endpoints with their own URLs', async () => {
    api.get.mockResolvedValue({ data: { success: true, data: [], meta: {} } })

    const platform = ref('instagram')
    const range = ref({ from: '2026-05-01', to: '2026-05-31' })
    const harness1 = buildHarness(platform, range)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/instagram-drafts/calendar', expect.any(Object))

    api.get.mockReset()
    api.get.mockResolvedValue({ data: { success: true, data: [], meta: {} } })

    const platform2 = ref('tiktok')
    const range2 = ref({ from: '2026-05-01', to: '2026-05-31' })
    buildHarness(platform2, range2)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/tiktok-drafts/calendar', expect.any(Object))
  })

  it('forwards optional status filter as a query param', async () => {
    api.get.mockResolvedValue({ data: { success: true, data: [], meta: {} } })

    const platform = ref('facebook')
    const range = ref({ from: '2026-05-01', to: '2026-05-31', status: 'awaiting_review' })
    buildHarness(platform, range)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/facebook-drafts/calendar', {
      params: { from: '2026-05-01', to: '2026-05-31', status: 'awaiting_review' },
    })
  })

  it('returns an empty items array when range is missing (no fetch fired)', async () => {
    const platform = ref('linkedin')
    const range = ref(null)
    const { get } = buildHarness(platform, range)
    await flushPromises()

    expect(api.get).not.toHaveBeenCalled()
    expect(get().items.value).toEqual([])
  })

  it('refetches when the platform reactively changes', async () => {
    api.get.mockResolvedValue({ data: { success: true, data: { items: [], from: null, to: null, count: 0 } } })

    const platform = ref('linkedin')
    const range = ref({ from: '2026-05-01', to: '2026-05-31' })
    buildHarness(platform, range)
    await flushPromises()

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(api.get).toHaveBeenLastCalledWith('/admin/linkedin-posts/calendar', expect.any(Object))

    api.get.mockResolvedValue({ data: { success: true, data: [], meta: {} } })
    platform.value = 'tiktok'
    await flushPromises()

    expect(api.get).toHaveBeenCalledTimes(2)
    expect(api.get).toHaveBeenLastCalledWith('/admin/tiktok-drafts/calendar', expect.any(Object))
  })
})
