import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'

import api from '@/services/api'
import {
  useWaitlistList,
  useUpdateWaitlist,
  exportWaitlistCsv,
  INTEREST_LABELS,
  STATUS_LABELS,
} from './useTrainingWaitlistAdmin'

vi.mock('@/services/api', () => ({
  default: { get: vi.fn(), patch: vi.fn() },
}))

function harness(setupFn) {
  let exposed
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } })
  const Comp = defineComponent({
    setup() {
      exposed = setupFn()
      return () => h('div')
    },
  })
  mount(Comp, { global: { plugins: [[VueQueryPlugin, { queryClient }]] } })
  return { get: () => exposed, queryClient }
}

beforeEach(() => {
  api.get.mockReset()
  api.patch.mockReset()
})

describe('useTrainingWaitlistAdmin', () => {
  it('lists with filters as query params and exposes rows + meta', async () => {
    api.get.mockResolvedValue({
      data: { success: true, data: [{ id: 1, name: 'A' }], meta: { total: 1, last_page: 1, current_page: 1 } },
    })
    const filters = ref({ search: 'ali', interest: 'workshop', status: '', per_page: 20, page: 2 })
    const h1 = harness(() => useWaitlistList(filters))
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/admin/training-waitlist', {
      params: { search: 'ali', interest: 'workshop', per_page: 20, page: 2 },
    })
    expect(h1.get().rows.value).toHaveLength(1)
    expect(h1.get().pagination.value.total).toBe(1)
  })

  it('query key includes filters (refetch on filter change)', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })
    const filters = ref({ status: 'new' })
    harness(() => useWaitlistList(filters))
    await flushPromises()
    filters.value = { status: 'contacted' }
    await flushPromises()
    expect(api.get).toHaveBeenCalledTimes(2)
    expect(api.get.mock.calls[1][1].params).toEqual({ status: 'contacted' })
  })

  it('updateStatus PATCHes and invalidates the list', async () => {
    api.patch.mockResolvedValue({ data: { success: true } })
    const h1 = harness(() => useUpdateWaitlist())
    const spy = vi.spyOn(h1.queryClient, 'invalidateQueries')
    await h1.get().mutateAsync({ id: 5, status: 'contacted' })
    expect(api.patch).toHaveBeenCalledWith('/admin/training-waitlist/5', { status: 'contacted' })
    expect(spy).toHaveBeenCalledWith({ queryKey: ['training-waitlist'] })
  })

  it('exports CSV via authenticated blob with current filters', async () => {
    api.get.mockResolvedValue({ data: 'a,b' })
    window.URL.createObjectURL = vi.fn(() => 'blob:x')
    window.URL.revokeObjectURL = vi.fn()
    await exportWaitlistCsv({ status: 'new', page: 3 })
    expect(api.get).toHaveBeenCalledWith('/admin/training-waitlist/export', {
      params: { status: 'new' },
      responseType: 'blob',
    })
    expect(window.URL.revokeObjectURL).toHaveBeenCalled()
  })

  it('has Indonesian labels', () => {
    expect(INTEREST_LABELS).toEqual({ inhouse: 'Inhouse', workshop: 'Workshop', public_class: 'Kelas umum' })
    expect(STATUS_LABELS).toEqual({ new: 'Baru', contacted: 'Sudah dikontak', cancelled: 'Batal' })
  })
})
