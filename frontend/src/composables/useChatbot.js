import { ref } from 'vue'
import api from '@/services/api'

export function useChatbot() {
  const messages = ref([])
  const isLoading = ref(false)
  const error = ref(null)

  async function sendMessage(text) {
    if (!text.trim() || isLoading.value) return

    // Add user message
    messages.value.push({ role: 'user', content: text })
    isLoading.value = true
    error.value = null

    try {
      const res = await api.post('/chatbot/ask', { message: text })
      if (res.data.success) {
        messages.value.push({ role: 'assistant', content: res.data.data.answer })
      } else {
        error.value = res.data.error?.message || 'Failed to get response'
        messages.value.push({ role: 'assistant', content: 'Sorry, I couldn\'t process that request.' })
      }
    } catch (err) {
      const msg = err.response?.status === 429
        ? 'Too many requests. Please wait a moment.'
        : 'AI service is currently unavailable.'
      error.value = msg
      messages.value.push({ role: 'assistant', content: msg })
    } finally {
      isLoading.value = false
    }
  }

  function clearMessages() {
    messages.value = []
    error.value = null
  }

  return { messages, isLoading, error, sendMessage, clearMessages }
}
