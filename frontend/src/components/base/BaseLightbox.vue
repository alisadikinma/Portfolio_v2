<template>
  <!-- Lightbox -->
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="show"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black"
        @click.self="$emit('close')"
      >
        <!-- Close Button -->
        <button
          @click="$emit('close')"
          class="absolute top-6 right-6 p-4 bg-white/10 hover:bg-white/20 rounded-full transition-all duration-300 backdrop-blur-md z-10 group"
        >
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>

        <!-- Previous Button -->
        <button
          v-if="canGoPrev"
          @click="$emit('prev')"
          class="absolute left-6 p-4 bg-white/10 hover:bg-white/20 rounded-full transition-all duration-300 backdrop-blur-md"
        >
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>
        </button>

        <!-- Image -->
        <div class="max-w-7xl max-h-full flex flex-col items-center">
          <img
            :src="currentImage"
            :alt="currentTitle"
            class="max-w-full max-h-[85vh] object-contain rounded-2xl shadow-2xl"
          />
          <div v-if="currentTitle" class="mt-6 text-center">
            <p class="text-white text-lg font-semibold">
              {{ currentTitle }}
            </p>
          </div>
        </div>

        <!-- Next Button -->
        <button
          v-if="canGoNext"
          @click="$emit('next')"
          class="absolute right-6 p-4 bg-white/10 hover:bg-white/20 rounded-full transition-all duration-300 backdrop-blur-md"
        >
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
          </svg>
        </button>

        <!-- Counter -->
        <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 px-6 py-3 bg-white/10 backdrop-blur-md rounded-full text-white text-sm font-medium">
          {{ currentIndex + 1 }} / {{ totalItems }}
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  currentImage: {
    type: String,
    required: true
  },
  currentTitle: {
    type: String,
    default: ''
  },
  currentIndex: {
    type: Number,
    required: true
  },
  totalItems: {
    type: Number,
    required: true
  }
})

defineEmits(['close', 'prev', 'next'])

const canGoPrev = computed(() => props.currentIndex > 0)
const canGoNext = computed(() => props.currentIndex < props.totalItems - 1)
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
