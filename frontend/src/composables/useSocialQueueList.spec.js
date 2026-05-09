import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'

import api from '@/services/api'
import { useSocialQueueList } from './useSocialQueueList'

/**
 * useSocialQueueList tests — covers per-platform fetch (linkedin /
 * facebook / instagram / tiktok) plus 'all' aggregate mode.
 *
 * Backend endpoints differ by platform:
 *   - linkedin → GET /admin/linkedin-drafts
 *   - cross-post → GET /admin/{facebook|instagram|tiktok}-drafts
 *
 * Both follow Laravel API resource conventions:
 *   { data: [...rows], meta: { current_page, total, ... } }
 *
 * The composable normalizes per-platform rows by tagging each with
 * `_platform: '<key>'` so the All-Platforms grouped table can render
 * a chip column without fetching the platform metadata separately.
 */

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

function makeHarness(platformRef, filtersRef) {
  let exposed
  const Harness = defineComponent({
    setup() {
      exposed = useSocialQueueList(platformRef, filtersRef)
      return () => h('div')
    },
  })
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0 } },
  })
  mount(Harness, {
    global: { plugins: [[VueQueryPlugin, { queryClient }]] },
  })
  return () => exposed
}

beforeEach(() => {
  api.get.mockReset()
})

describe('useSocialQueueList', () => {
  it('fetches /admin/linkedin-drafts when platform=linkedin', async () => {
    api.get.mockResolvedValue({
      data: { data: [{ id: 1, status: 'manual_review' }], meta: { total: 1 } },
    })

    const platform = ref('linkedin')
    const filters = ref({ scope: 'queue', per_page: 100 })
    const get = makeHarness(platform, filters)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/linkedin-drafts', {
      params: { scope: 'queue', per_page: 100 },
    })

    expect(get().drafts.value).toHaveLength(1)
    expect(get().drafts.value[0]._platform).toBe('linkedin')
  })

  it('fetches /admin/facebook-drafts when platform=facebook', async () => {
    api.get.mockResolvedValue({
      data: { data: [{ id: 7, status: 'awaiting_review' }], meta: {} },
    })

    const platform = ref('facebook')
    const filters = ref({ scope: 'queue', per_page: 100 })
    const get = makeHarness(platform, filters)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/facebook-drafts', expect.any(Object))
    expect(get().drafts.value[0]._platform).toBe('facebook')
  })

  it('fetches all 4 endpoints in parallel when platform=all and merges with _platform tags', async () => {
    api.get
      .mockResolvedValueOnce({ data: { data: [{ id: 1, status: 'manual_review' }] } })
      .mockResolvedValueOnce({ data: { data: [{ id: 11, status: 'awaiting_review' }] } })
      .mockResolvedValueOnce({ data: { data: [{ id: 21, status: 'awaiting_review' }] } })
      .mockResolvedValueOnce({ data: { data: [{ id: 31, status: 'awaiting_review' }] } })

    const platform = ref('all')
    const filters = ref({ scope: 'queue', per_page: 100 })
    const get = makeHarness(platform, filters)
    await flushPromises()

    // 4 parallel calls, one per platform.
    expect(api.get).toHaveBeenCalledTimes(4)
    const urls = api.get.mock.calls.map((c) => c[0])
    expect(urls).toContain('/admin/linkedin-drafts')
    expect(urls).toContain('/admin/facebook-drafts')
    expect(urls).toContain('/admin/instagram-drafts')
    expect(urls).toContain('/admin/tiktok-drafts')

    const drafts = get().drafts.value
    expect(drafts).toHaveLength(4)

    // Each row tagged with the platform it came from.
    const tagged = drafts.map((d) => ({ id: d.id, p: d._platform }))
    expect(tagged).toContainEqual({ id: 1, p: 'linkedin' })
    expect(tagged).toContainEqual({ id: 11, p: 'facebook' })
    expect(tagged).toContainEqual({ id: 21, p: 'instagram' })
    expect(tagged).toContainEqual({ id: 31, p: 'tiktok' })
  })

  it('exposes per-platform groups when platform=all so the view can render grouped tables', async () => {
    api.get
      .mockResolvedValueOnce({ data: { data: [{ id: 1, status: 'manual_review' }, { id: 2, status: 'failed' }] } })
      .mockResolvedValueOnce({ data: { data: [{ id: 11, status: 'awaiting_review' }] } })
      .mockResolvedValueOnce({ data: { data: [] } })
      .mockResolvedValueOnce({ data: { data: [{ id: 31, status: 'awaiting_review' }] } })

    const platform = ref('all')
    const filters = ref({ scope: 'queue', per_page: 100 })
    const get = makeHarness(platform, filters)
    await flushPromises()

    const groups = get().groups.value
    expect(Object.keys(groups).sort()).toEqual(['facebook', 'instagram', 'linkedin', 'tiktok'])

    expect(groups.linkedin).toHaveLength(2)
    expect(groups.facebook).toHaveLength(1)
    expect(groups.instagram).toHaveLength(0)
    expect(groups.tiktok).toHaveLength(1)
  })

  it('falls back to empty array (and per-platform empty groups) on API error', async () => {
    api.get.mockRejectedValue(new Error('network down'))

    const platform = ref('linkedin')
    const filters = ref({ scope: 'queue', per_page: 100 })
    const get = makeHarness(platform, filters)
    await flushPromises()

    expect(get().drafts.value).toEqual([])
    // Per-platform groups stay safe-shape even on error so the view
    // doesn't have to special-case undefined.
    const groups = get().groups.value
    expect(groups.linkedin).toEqual([])
  })

  it('passes status filter through to backend params', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const platform = ref('instagram')
    const filters = ref({ scope: 'queue', status: 'awaiting_review', per_page: 100 })
    makeHarness(platform, filters)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/instagram-drafts', {
      params: { scope: 'queue', status: 'awaiting_review', per_page: 100 },
    })
  })
})
