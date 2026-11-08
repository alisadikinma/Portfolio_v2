import { computed, unref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Training waitlist admin (PF-2 Phase 4D) - TanStack Query composable.
 * Same convention as useNewsletterAdmin: 30s staleTime + refetchOnMount 'always'.
 */

const KEY = 'training-waitlist'

export const INTEREST_LABELS = {
  inhouse: 'Inhouse',
  workshop: 'Workshop',
  public_class: 'Kelas umum',
}

export const STATUS_LABELS = {
  new: 'Baru',
  contacted: 'Sudah dikontak',
  cancelled: 'Batal',
}

function buildParams(filters = {}) {
  const params = {}
  for (const k of ['search', 'interest', 'locale', 'status', 'per_page', 'page']) {
    if (filters[k]) params[k] = filters[k]
  }
  return params
}

export function useWaitlistList(filters) {
  const query = useQuery({
    queryKey: [KEY, filters],
    queryFn: () =>
      api
        .get('/admin/training-waitlist', { params: buildParams(unref(filters) || {}) })
        .then(r => r.data),
    staleTime: 30_000,
    refetchOnMount: 'always',
  })

  return {
    rows: computed(() => query.data.value?.data || []),
    pagination: computed(() => query.data.value?.meta || null),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

/** Mutation args: { id, status?, admin_note? } */
export function useUpdateWaitlist() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...body }) =>
      api.patch(`/admin/training-waitlist/${id}`, body).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [KEY] })
    },
  })
}

/** CSV export through authenticated axios blob (Bearer token stays out of the URL). */
export async function exportWaitlistCsv(filters = {}) {
  const { page, per_page, ...rest } = filters // eslint-disable-line no-unused-vars
  const res = await api.get('/admin/training-waitlist/export', {
    params: buildParams(rest),
    responseType: 'blob',
  })
  const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `training-waitlist-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
