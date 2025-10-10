<template>
  <div :class="loaderWrapperClasses">
    <!-- Spinner Loader -->
    <div v-if="type === 'spinner'" :class="spinnerClasses">
      <svg
        class="animate-spin"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        ></circle>
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        ></path>
      </svg>
    </div>

    <!-- Dots Loader -->
    <div v-else-if="type === 'dots'" class="flex space-x-2">
      <div v-for="i in 3" :key="i" :class="dotClasses" :style="{ animationDelay: `${i * 0.15}s` }"></div>
    </div>

    <!-- Pulse Loader -->
    <div v-else-if="type === 'pulse'" :class="pulseClasses">
      <div class="absolute w-full h-full rounded-full animate-ping opacity-75"></div>
      <div class="relative w-full h-full rounded-full"></div>
    </div>

    <!-- Bar Loader -->
    <div v-else-if="type === 'bar'" class="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden" :class="barHeightClass">
      <div
        class="h-full rounded-full animate-pulse"
        :class="colorClass"
        :style="{ width: progress ? `${progress}%` : '100%' }"
      ></div>
    </div>

    <!-- Text Label -->
    <p v-if="text" class="mt-3 text-sm font-medium" :class="textColorClass">
      {{ text }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'spinner',
    validator: (value) => ['spinner', 'dots', 'pulse', 'bar'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  color: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'success', 'error', 'warning', 'neutral'].includes(value)
  },
  text: {
    type: String,
    default: null
  },
  progress: {
    type: Number,
    default: null,
    validator: (value) => value >= 0 && value <= 100
  },
  fullscreen: {
    type: Boolean,
    default: false
  },
  overlay: {
    type: Boolean,
    default: false
  }
})

const loaderWrapperClasses = computed(() => {
  const classes = ['flex flex-col items-center justify-center']

  if (props.fullscreen) {
    classes.push('fixed inset-0 z-50')
  }

  if (props.overlay) {
    classes.push('bg-white/80 dark:bg-neutral-900/80 backdrop-blur-sm')
  }

  return classes.join(' ')
})

const sizeClasses = {
  xs: 'h-4 w-4',
  sm: 'h-6 w-6',
  md: 'h-8 w-8',
  lg: 'h-12 w-12',
  xl: 'h-16 w-16'
}

const dotSizeClasses = {
  xs: 'h-1 w-1',
  sm: 'h-1.5 w-1.5',
  md: 'h-2 w-2',
  lg: 'h-3 w-3',
  xl: 'h-4 w-4'
}

const colorClasses = {
  primary: 'text-primary-600 dark:text-primary-500',
  secondary: 'text-neutral-600 dark:text-neutral-400',
  success: 'text-success-600 dark:text-success-500',
  error: 'text-error-600 dark:text-error-500',
  warning: 'text-warning-600 dark:text-warning-500',
  neutral: 'text-neutral-500 dark:text-neutral-400'
}

const bgColorClasses = {
  primary: 'bg-primary-600 dark:bg-primary-500',
  secondary: 'bg-neutral-600 dark:bg-neutral-400',
  success: 'bg-success-600 dark:bg-success-500',
  error: 'bg-error-600 dark:bg-error-500',
  warning: 'bg-warning-600 dark:bg-warning-500',
  neutral: 'bg-neutral-500 dark:bg-neutral-400'
}

const spinnerClasses = computed(() => {
  return [sizeClasses[props.size], colorClasses[props.color]].join(' ')
})

const dotClasses = computed(() => {
  return [
    dotSizeClasses[props.size],
    bgColorClasses[props.color],
    'rounded-full animate-bounce'
  ].join(' ')
})

const pulseClasses = computed(() => {
  return [
    sizeClasses[props.size],
    bgColorClasses[props.color],
    'relative'
  ].join(' ')
})

const barHeightClass = computed(() => {
  const heights = {
    xs: 'h-1',
    sm: 'h-1.5',
    md: 'h-2',
    lg: 'h-3',
    xl: 'h-4'
  }
  return heights[props.size]
})

const colorClass = computed(() => {
  return bgColorClasses[props.color]
})

const textColorClass = computed(() => {
  return colorClasses[props.color]
})
</script>
