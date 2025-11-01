<template>
  <div class="relative">
    <!-- Mobile Carousel (Touch/Swipe) -->
    <div 
      ref="carouselContainer"
      class="overflow-hidden"
      @touchstart="handleTouchStart"
      @touchmove="handleTouchMove"
      @touchend="handleTouchEnd"
    >
      <div 
        class="flex transition-transform duration-300 ease-out"
        :style="{ transform: `translateX(-${currentIndex * 100}%)` }"
      >
        <div 
          v-for="(item, index) in items" 
          :key="index"
          class="w-full flex-shrink-0 px-2"
        >
          <slot :item="item" :index="index"></slot>
        </div>
      </div>
    </div>

    <!-- Navigation Arrows (Optional) -->
    <button
      v-if="showArrows && currentIndex > 0"
      @click="prev"
      class="absolute left-0 top-1/2 -translate-y-1/2 w-10 h-10 bg-white dark:bg-gray-800 rounded-full shadow-lg flex items-center justify-center text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors z-10"
      aria-label="Previous"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
      </svg>
    </button>

    <button
      v-if="showArrows && currentIndex < items.length - 1"
      @click="next"
      class="absolute right-0 top-1/2 -translate-y-1/2 w-10 h-10 bg-white dark:bg-gray-800 rounded-full shadow-lg flex items-center justify-center text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors z-10"
      aria-label="Next"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
      </svg>
    </button>

    <!-- Dots Indicator -->
    <div v-if="showDots" class="flex justify-center gap-2 mt-4">
      <button
        v-for="(item, index) in items"
        :key="index"
        @click="goTo(index)"
        :class="[
          'w-2 h-2 rounded-full transition-all',
          index === currentIndex 
            ? 'bg-primary-600 w-6' 
            : 'bg-gray-300 dark:bg-gray-600'
        ]"
        :aria-label="`Go to slide ${index + 1}`"
      ></button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    required: true
  },
  showArrows: {
    type: Boolean,
    default: true
  },
  showDots: {
    type: Boolean,
    default: true
  }
})

const currentIndex = ref(0)
const carouselContainer = ref(null)

// Touch handling
let touchStartX = 0
let touchEndX = 0

const handleTouchStart = (e) => {
  touchStartX = e.touches[0].clientX
}

const handleTouchMove = (e) => {
  touchEndX = e.touches[0].clientX
}

const handleTouchEnd = () => {
  const swipeThreshold = 50
  const diff = touchStartX - touchEndX

  if (Math.abs(diff) > swipeThreshold) {
    if (diff > 0 && currentIndex.value < props.items.length - 1) {
      next()
    } else if (diff < 0 && currentIndex.value > 0) {
      prev()
    }
  }
}

const next = () => {
  if (currentIndex.value < props.items.length - 1) {
    currentIndex.value++
  }
}

const prev = () => {
  if (currentIndex.value > 0) {
    currentIndex.value--
  }
}

const goTo = (index) => {
  currentIndex.value = index
}
</script>
