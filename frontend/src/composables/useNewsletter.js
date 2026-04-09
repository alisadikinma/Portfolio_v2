import { ref } from 'vue'
import api from '@/services/api'

export function useNewsletter() {
  const isLoading = ref(false)
  const error = ref(null)
  const success = ref(false)

  async function subscribe(email) {
    isLoading.value = true
    error.value = null
    success.value = false

    try {
      const res = await api.post('/newsletter/subscribe', { email })
      if (res.data.success) {
        success.value = true
      }
      return { success: true, message: res.data.message }
    } catch (err) {
      const msg = err.response?.data?.error?.message || 'Failed to subscribe'
      error.value = msg
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
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error?.message || 'Failed to unsubscribe'
      return { success: false, message: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return { isLoading, error, success, subscribe, unsubscribe }
}
