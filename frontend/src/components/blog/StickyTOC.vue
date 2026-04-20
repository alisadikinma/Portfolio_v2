<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  sections: { type: Array, default: () => [] },
  progress: { type: Number, default: 0 },
})

const activeId = ref(null)
let observer = null

function observe() {
  if (typeof window === 'undefined' || typeof IntersectionObserver === 'undefined') return
  if (observer) {
    observer.disconnect()
    observer = null
  }

  const targets = props.sections
    .map((s) => document.getElementById(s.id))
    .filter(Boolean)

  if (targets.length === 0) return

  observer = new IntersectionObserver(
    (entries) => {
      // Pick the first entry that is intersecting — the top-most visible H2.
      const visible = entries
        .filter((e) => e.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)
      if (visible.length > 0) {
        activeId.value = visible[0].target.id
      }
    },
    {
      rootMargin: '-20% 0px -70% 0px',
      threshold: 0,
    }
  )

  targets.forEach((el) => observer.observe(el))
}

function scrollTo(id) {
  const el = document.getElementById(id)
  if (!el) return
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  el.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' })
  activeId.value = id
}

onMounted(() => {
  // Wait a tick so the parent article content has finished rendering.
  requestAnimationFrame(() => observe())
})

onBeforeUnmount(() => {
  if (observer) {
    observer.disconnect()
    observer = null
  }
})

watch(
  () => props.sections.length,
  () => {
    requestAnimationFrame(() => observe())
  }
)

const pctLabel = () => {
  const p = Math.max(0, Math.min(100, Math.round(Number(props.progress) || 0)))
  return `${p}% read`
}
</script>

<template>
  <nav
    v-if="sections.length > 0"
    class="sticky top-24 rounded-2xl border border-white/5 bg-bg-elevated/40 backdrop-blur-sm p-5"
    role="navigation"
    aria-label="Article outline"
    data-testid="sticky-toc"
  >
    <p class="mono-label text-accent-gold text-xs mb-4">CONTENTS</p>

    <ol class="space-y-2.5">
      <li v-for="s in sections" :key="s.id">
        <button
          type="button"
          class="flex items-start gap-2.5 text-left w-full group transition-colors duration-200"
          :class="activeId === s.id ? 'text-accent-gold' : 'text-fg-muted hover:text-fg-primary'"
          :aria-current="activeId === s.id ? 'location' : undefined"
          @click="scrollTo(s.id)"
        >
          <span
            class="mt-1.5 w-1.5 h-1.5 rounded-full flex-shrink-0 transition-colors duration-200"
            :class="activeId === s.id ? 'bg-accent-gold' : 'bg-fg-dim group-hover:bg-fg-muted'"
            aria-hidden="true"
          ></span>
          <span
            class="text-sm leading-snug"
            :class="activeId === s.id ? 'font-medium' : 'font-light'"
          >{{ s.text }}</span>
        </button>
      </li>
    </ol>

    <div class="mt-5 pt-4 border-t border-white/5">
      <p class="mono-label text-fg-dim text-[10px] mb-2">{{ pctLabel() }}</p>
      <div class="h-1 bg-white/5 rounded-full overflow-hidden" aria-hidden="true">
        <div
          class="h-full bg-accent-gold transition-all duration-300 ease-out"
          :style="{ width: `${Math.max(0, Math.min(100, Number(progress) || 0))}%` }"
        ></div>
      </div>
    </div>
  </nav>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  * {
    transition: none !important;
    animation: none !important;
  }
}
</style>
