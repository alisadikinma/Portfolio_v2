<template>
  <span :class="badgeClasses">
    <component v-if="icon" :is="icon" class="h-3 w-3 mr-1" />
    <slot />
    <button
      v-if="closable"
      type="button"
      class="ml-1 hover:opacity-75 transition-opacity"
      @click="handleClose"
    >
      <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
      </svg>
    </button>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => [
      'primary', 'secondary', 'success', 'error', 'warning', 'info', 'neutral'
    ].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  rounded: {
    type: String,
    default: 'full',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'full'].includes(value)
  },
  outlined: {
    type: Boolean,
    default: false
  },
  closable: {
    type: Boolean,
    default: false
  },
  icon: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close'])

const handleClose = () => {
  emit('close')
}

const badgeClasses = computed(() => {
  const classes = [
    'inline-flex items-center font-medium transition-all duration-200'
  ]

  // Size
  const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-0.5 text-sm',
    lg: 'px-3 py-1 text-base'
  }
  classes.push(sizeClasses[props.size])

  // Border Radius
  const roundedClasses = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    full: 'rounded-full'
  }
  classes.push(roundedClasses[props.rounded])

  // Variant colors
  if (props.outlined) {
    const outlinedClasses = {
      primary: 'border border-primary-500 text-primary-700 dark:text-primary-300',
      secondary: 'border border-neutral-400 text-neutral-700 dark:text-neutral-300',
      success: 'border border-success-500 text-success-700 dark:text-success-300',
      error: 'border border-error-500 text-error-700 dark:text-error-300',
      warning: 'border border-warning-500 text-warning-700 dark:text-warning-300',
      info: 'border border-primary-400 text-primary-600 dark:text-primary-400',
      neutral: 'border border-neutral-300 text-neutral-600 dark:text-neutral-400'
    }
    classes.push(outlinedClasses[props.variant])
  } else {
    const variantClasses = {
      primary: 'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200',
      secondary: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200',
      success: 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200',
      error: 'bg-error-100 text-error-800 dark:bg-error-900 dark:text-error-200',
      warning: 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200',
      info: 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300',
      neutral: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300'
    }
    classes.push(variantClasses[props.variant])
  }

  return classes.join(' ')
})
</script>
