import { computed, unref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Newsletter admin — TanStack Query composable.
 *
 * Mirrors the 30s staleTime + refetchOnMount:'always' convention used elsewhere
 * so operator actions (delete subscriber, send-test, send-now) reflect on next
 * navigation instead of staying stale.
 */

const SUBS_KEY = 'newsletter-subscribers'
const SENDS_KEY = 'newsletter-sends'
const PREVIEW_KEY = 'newsletter-preview'

function listSubscribers(filters = {}) {
  const params = {}
  if (filters.search) params.search = filters.search
  if (filters.source) params.source = filters.source
  if (filters.per_page) params.per_page = filters.per_page
  if (filters.page) params.page = filters.page
  return api.get('/admin/newsletter', { params }).then(r => r.data)
}

function listSends(filters = {}) {
  const params = {}
  if (filters.status) params.status = filters.status
  if (filters.per_page) params.per_page = filters.per_page
  if (filters.page) params.page = filters.page
  return api.get('/admin/newsletter/sends', { params }).then(r => r.data)
}

function fetchPreview() {
  return api.get('/admin/newsletter/digest-preview').then(r => r.data)
}

export function useSubscribersList(filters) {
  const query = useQuery({
    queryKey: [SUBS_KEY, filters],
    queryFn: () => listSubscribers(unref(filters) || {}),
    staleTime: 30_000,
    refetchOnMount: 'always',
  })

  return {
    subscribers: computed(() => query.data.value?.data || []),
    pagination: computed(() => query.data.value?.meta || null),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

export function useDeleteSubscriber() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.delete(`/admin/newsletter/${id}`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [SUBS_KEY] })
    },
  })
}

export function useDigestPreview(enabled) {
  return useQuery({
    queryKey: [PREVIEW_KEY],
    queryFn: fetchPreview,
    enabled,
    staleTime: 60_000,
    refetchOnMount: 'always',
  })
}

export function useSendTest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ recipient }) => api
      .post('/admin/newsletter/send-test', recipient ? { recipient } : {})
      .then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [SENDS_KEY] })
    },
  })
}

export function useSendNow() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.post('/admin/newsletter/send-now').then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [SENDS_KEY] })
    },
  })
}

export function useSendsList(filters) {
  const query = useQuery({
    queryKey: [SENDS_KEY, filters],
    queryFn: () => listSends(unref(filters) || {}),
    staleTime: 30_000,
    refetchOnMount: 'always',
  })

  return {
    sends: computed(() => query.data.value?.data || []),
    pagination: computed(() => query.data.value?.meta || null),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

/**
 * CSV export — direct browser download via auth-token query.
 *
 * Axios blob approach: keeps Authorization header (in-memory token doesn't
 * leak to URL bar) and lets the browser handle the download via Blob URL.
 */
export async function exportSubscribersCsv() {
  const res = await api.get('/admin/newsletter/export', { responseType: 'blob' })

  const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  const today = new Date().toISOString().slice(0, 10)
  link.setAttribute('download', `newsletter-subscribers-${today}.csv`)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
