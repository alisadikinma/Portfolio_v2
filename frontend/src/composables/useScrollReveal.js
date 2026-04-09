import { onMounted, onUnmounted } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

gsap.registerPlugin(ScrollTrigger)

/**
 * Composable for scroll-driven reveal animations using GSAP ScrollTrigger.
 *
 * Usage:
 *   const { revealOnScroll, revealStaggerChildren } = useScrollReveal()
 *
 *   onMounted(() => {
 *     revealOnScroll(sectionRef.value)
 *     revealStaggerChildren(gridRef.value, '.card')
 *   })
 */
export function useScrollReveal() {
  const triggers = []
  const prefersReduced = typeof window !== 'undefined'
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false

  /**
   * Reveal a single element on scroll into view.
   */
  function revealOnScroll(element, options = {}) {
    if (!element || prefersReduced) return

    const {
      y = 30,
      opacity = 0,
      duration = 0.6,
      ease = 'power2.out',
      delay = 0,
      trigger,
      start = 'top 85%',
    } = options

    gsap.set(element, { y, opacity })

    const st = ScrollTrigger.create({
      trigger: trigger || element,
      start,
      once: true,
      onEnter: () => {
        gsap.to(element, { y: 0, opacity: 1, duration, ease, delay })
      }
    })

    triggers.push(st)
    return st
  }

  /**
   * Stagger-reveal child elements within a container.
   */
  function revealStaggerChildren(container, childSelector = ':scope > *', options = {}) {
    if (!container || prefersReduced) return

    const {
      y = 30,
      opacity = 0,
      duration = 0.5,
      ease = 'power2.out',
      stagger = 0.08,
      start = 'top 85%',
    } = options

    const children = container.querySelectorAll(childSelector)
    if (!children.length) return

    gsap.set(children, { y, opacity })

    const st = ScrollTrigger.create({
      trigger: container,
      start,
      once: true,
      onEnter: () => {
        gsap.to(children, { y: 0, opacity: 1, duration, ease, stagger })
      }
    })

    triggers.push(st)
    return st
  }

  onUnmounted(() => {
    triggers.forEach(st => st.kill())
  })

  return { revealOnScroll, revealStaggerChildren }
}
