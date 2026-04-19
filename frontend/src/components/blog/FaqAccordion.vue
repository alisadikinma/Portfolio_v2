<script setup>
import { ref } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    required: true,
    validator: (arr) =>
      arr.every((i) => typeof i?.question === 'string' && typeof i?.answer === 'string'),
  },
})

const openIndex = ref(0)

function toggle(i) {
  openIndex.value = openIndex.value === i ? -1 : i
}

function onKey(e, i) {
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    toggle(i)
  }
}
</script>

<template>
  <section
    v-if="items.length"
    class="container-custom mb-16"
    aria-label="Frequently asked questions"
  >
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-4 mb-8">
        <p class="mono-label text-accent-gold">FAQ</p>
        <div class="flex-1 h-px bg-white/5"></div>
      </div>

      <ul class="space-y-3">
        <li
          v-for="(item, i) in items"
          :key="i"
          class="rounded-xl border transition-colors duration-200"
          :class="openIndex === i
            ? 'bg-accent-gold/[0.03] border-accent-gold/25'
            : 'bg-bg-elevated/50 border-white/5'"
        >
          <button
            type="button"
            class="w-full flex items-start justify-between gap-4 p-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-gold/40 rounded-xl"
            :aria-expanded="openIndex === i"
            :aria-controls="`faq-answer-${i}`"
            @click="toggle(i)"
            @keydown="onKey($event, i)"
          >
            <span class="font-display font-semibold text-fg-primary text-base md:text-lg leading-snug">
              {{ item.question }}
            </span>
            <svg
              class="w-5 h-5 flex-shrink-0 mt-0.5 text-accent-gold transition-transform duration-300"
              :class="openIndex === i ? 'rotate-180' : ''"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 9l-7 7-7-7"
              />
            </svg>
          </button>

          <div
            :id="`faq-answer-${i}`"
            class="grid transition-[grid-template-rows] duration-300 ease-out"
            :class="openIndex === i ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
            role="region"
          >
            <div class="overflow-hidden">
              <p class="px-5 pb-5 text-fg-muted text-[15px] leading-relaxed">
                {{ item.answer }}
              </p>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
