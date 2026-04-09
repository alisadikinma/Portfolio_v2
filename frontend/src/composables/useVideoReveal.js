import { onUnmounted } from 'vue'

/**
 * Auto-play/pause video on scroll enter/leave using IntersectionObserver.
 *
 * Usage:
 *   const videoRef = ref(null)
 *   onMounted(() => setupVideoReveal(videoRef.value))
 */
export function useVideoReveal() {
  const observers = []

  function setupVideoReveal(videoElement, options = {}) {
    if (!videoElement) return
    if (typeof window === 'undefined') return

    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    if (prefersReduced) return

    const { threshold = 0.3 } = options

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          videoElement.play().catch(() => {})
        } else {
          videoElement.pause()
        }
      },
      { threshold }
    )

    observer.observe(videoElement)
    observers.push(observer)

    return observer
  }

  onUnmounted(() => {
    observers.forEach(obs => obs.disconnect())
  })

  return { setupVideoReveal }
}
