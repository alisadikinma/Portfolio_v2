import { ref, onMounted } from 'vue'
import api from '@/services/api'

export function useActivityFeed() {
  const activities = ref([])
  const isLoading = ref(false)
  const error = ref(null)

  async function fetchActivities() {
    isLoading.value = true
    error.value = null
    try {
      const res = await api.get('/activity-feed')
      if (res.data.success) {
        activities.value = res.data.data || []
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch activity feed'
    } finally {
      isLoading.value = false
    }
  }

  return { activities, isLoading, error, fetchActivities }
}
