<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  title: { type: String, default: '' },
  // optional element to watch — when it scrolls into view, the floating rail
  // hides so it doesn't overlap with the inline share bar at the end of the
  // article. Pass the inline share container ref via .value.
  hideNear: { type: Object, default: null },
})

const visible = ref(false)
const isMobile = ref(false)
const copied = ref(false)
const inlineVisible = ref(false)

let scrollHandler = null
let resizeHandler = null
let observer = null

const SHOW_AFTER_PX = 360 // past the hero / first paragraph

const shareTwitter = () => {
  const url = encodeURIComponent(window.location.href)
  const text = encodeURIComponent(props.title || document.title || '')
  window.open(
    `https://twitter.com/intent/tweet?url=${url}&text=${text}`,
    '_blank',
    'noopener,noreferrer'
  )
}

const shareLinkedIn = () => {
  const url = encodeURIComponent(window.location.href)
  window.open(
    `https://www.linkedin.com/sharing/share-offsite/?url=${url}`,
    '_blank',
    'noopener,noreferrer'
  )
}

const copyUrl = async () => {
  try {
    await navigator.clipboard.writeText(window.location.href)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2200)
  } catch {
    // clipboard API unavailable — silent fallback (inline bar still works)
  }
}

const updateVisibility = () => {
  visible.value = window.scrollY > SHOW_AFTER_PX && !inlineVisible.value
}

const updateBreakpoint = () => {
  isMobile.value = window.innerWidth < 1024
}

onMounted(() => {
  updateBreakpoint()

  scrollHandler = () => updateVisibility()
  resizeHandler = () => updateBreakpoint()
  window.addEventListener('scroll', scrollHandler, { passive: true })
  window.addEventListener('resize', resizeHandler, { passive: true })

  // Hide rail when the inline share bar is visible (avoids redundancy)
  if (props.hideNear && typeof IntersectionObserver !== 'undefined') {
    observer = new IntersectionObserver(
      ([entry]) => {
        inlineVisible.value = entry.isIntersecting
        updateVisibility()
      },
      { rootMargin: '0px 0px -10% 0px' }
    )
    observer.observe(props.hideNear)
  }

  updateVisibility()
})

onBeforeUnmount(() => {
  if (scrollHandler) window.removeEventListener('scroll', scrollHandler)
  if (resizeHandler) window.removeEventListener('resize', resizeHandler)
  if (observer) observer.disconnect()
})
</script>

<template>
  <Teleport to="body">
    <transition name="rail">
      <aside
        v-if="visible"
        :class="['share-rail', isMobile ? 'share-rail--mobile' : 'share-rail--desktop']"
        role="complementary"
        aria-label="Share this article"
      >
        <span v-if="!isMobile" class="share-rail__label">Share</span>

        <button
          type="button"
          class="share-btn share-btn--cyan"
          @click="shareTwitter"
          aria-label="Share on X"
          title="Share on X"
        >
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
          </svg>
        </button>

        <button
          type="button"
          class="share-btn share-btn--cyan"
          @click="shareLinkedIn"
          aria-label="Share on LinkedIn"
          title="Share on LinkedIn"
        >
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
          </svg>
        </button>

        <button
          type="button"
          class="share-btn share-btn--gold"
          @click="copyUrl"
          :aria-label="copied ? 'Link copied' : 'Copy link'"
          :title="copied ? 'Link copied' : 'Copy link'"
        >
          <svg v-if="!copied" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="text-emerald-400" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          </svg>
        </button>
      </aside>
    </transition>
  </Teleport>
</template>

<style scoped>
.share-rail {
  position: fixed;
  z-index: 30;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px;
  border-radius: 16px;
  background: rgba(12, 12, 15, 0.72);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.45);
}

/* Desktop — vertical rail pinned to the right of the viewport,
   vertically centered. clamp() keeps it visible on narrow desktop
   viewports without colliding with the article content. */
.share-rail--desktop {
  right: clamp(16px, calc((100vw - 1100px) / 2 - 60px), 64px);
  top: 50%;
  flex-direction: column;
  transform: translateY(-50%);
  padding: 12px 10px;
}

/* Mobile — horizontal pill at the bottom-center, above safe-area */
.share-rail--mobile {
  left: 50%;
  bottom: calc(env(safe-area-inset-bottom, 0px) + 16px);
  flex-direction: row;
  transform: translateX(-50%);
}

.share-rail__label {
  font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
  font-size: 9px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: rgba(138, 143, 152, 0.9);
  margin-bottom: 2px;
}

.share-btn {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: rgba(138, 143, 152, 0.9);
  cursor: pointer;
  transition:
    background-color 200ms ease,
    border-color 200ms ease,
    color 200ms ease,
    transform 200ms ease;
}

.share-btn svg {
  width: 18px;
  height: 18px;
}

.share-btn:hover {
  transform: translateY(-1px);
}

.share-btn--cyan:hover {
  background: rgba(6, 182, 212, 0.10);
  border-color: rgba(6, 182, 212, 0.45);
  color: #06B6D4;
}

.share-btn--gold:hover {
  background: rgba(212, 168, 67, 0.10);
  border-color: rgba(212, 168, 67, 0.45);
  color: #D4A843;
}

.share-btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px rgba(212, 168, 67, 0.55);
}

/* Transition — fade with a subtle slide that respects rail orientation */
.rail-enter-active,
.rail-leave-active {
  transition: opacity 240ms ease, transform 240ms cubic-bezier(0.22, 1, 0.36, 1);
}

.rail-enter-from.share-rail--desktop,
.rail-leave-to.share-rail--desktop {
  opacity: 0;
  transform: translateY(-50%) translateX(14px);
}

.rail-enter-from.share-rail--mobile,
.rail-leave-to.share-rail--mobile {
  opacity: 0;
  transform: translateX(-50%) translateY(18px);
}

@media (prefers-reduced-motion: reduce) {
  .rail-enter-active,
  .rail-leave-active {
    transition: opacity 120ms ease;
  }
  .rail-enter-from.share-rail--desktop,
  .rail-leave-to.share-rail--desktop,
  .rail-enter-from.share-rail--mobile,
  .rail-leave-to.share-rail--mobile {
    transform: translateY(-50%);
  }
  .rail-enter-from.share-rail--mobile,
  .rail-leave-to.share-rail--mobile {
    transform: translateX(-50%);
  }
  .share-btn:hover {
    transform: none;
  }
}
</style>
