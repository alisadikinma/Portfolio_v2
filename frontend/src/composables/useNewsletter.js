import { ref } from 'vue'
import api from '@/services/api'
import {
  isDismissed,
  isSubscribed,
  markDismissed,
  markSubscribed,
  clearNewsletterState,
} from '@/utils/newsletterState'

export function useNewsletter() {
  const isLoading = ref(false)
  const error = ref(null)
  const success = ref(false)

  async function subscribe(payload) {
    isLoading.value = true
    error.value = null
    success.value = false

    let body
    let emailForState
    if (typeof payload === 'string') {
      // Backwards-compat shim — will be removed once all 4 touchpoints migrate.
      console.warn('[useNewsletter] subscribe(string) is deprecated; pass {name, email, whatsappNumber, source}')
      body = { email: payload }
      emailForState = payload
    } else {
      body = {
        name: payload.name,
        email: payload.email,
        whatsapp_number: payload.whatsappNumber,
        source: payload.source,
      }
      emailForState = payload.email
    }

    try {
      const res = await api.post('/newsletter/subscribe', body)
      if (res.data.success) {
        success.value = true
        markSubscribed(emailForState)
      }
      return { success: true, message: res.data.message }
    } catch (err) {
      const code = err.response?.data?.error?.code
      const msg = err.response?.data?.error?.message || 'Failed to subscribe'
      error.value = msg
      // 409 duplicate still counts as "subscribed" for dedup purposes.
      if (code === 'DUPLICATE') {
        markSubscribed(emailForState)
        return { success: false, duplicate: true, message: msg }
      }
      return { success: false, message: msg }
    } finally {
      isLoading.value = false
    }
  }

  async function unsubscribe(email) {
    isLoading.value = true
    error.value = null

    try {
      await api.delete('/newsletter/unsubscribe', { data: { email } })
      clearNewsletterState()
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error?.message || 'Failed to unsubscribe'
      return { success: false, message: error.value }
    } finally {
      isLoading.value = false
    }
  }

  async function unsubscribeByToken(token) {
    isLoading.value = true
    error.value = null

    try {
      await api.post('/newsletter/unsubscribe-by-token', { token })
      clearNewsletterState()
      return { success: true }
    } catch (err) {
      const msg = err.response?.data?.error?.message || 'This unsubscribe link is no longer valid.'
      error.value = msg
      return { success: false, message: msg }
    } finally {
      isLoading.value = false
    }
  }

  return {
    isLoading,
    error,
    success,
    subscribe,
    unsubscribe,
    unsubscribeByToken,
    // State-aware helpers (pure, re-exported for convenience)
    isDismissed,
    isSubscribed,
    markDismissed,
  }
}
