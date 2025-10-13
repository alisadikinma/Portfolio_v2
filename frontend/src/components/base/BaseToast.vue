<template>
  <Teleport to="body">
    <TransitionGroup
      name="toast"
      tag="div"
      :class="containerClasses"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="toastClasses(toast)"
        @mouseenter="pauseTimer(toast.id)"
        @mouseleave="resumeTimer(toast.id)"
      >
        <!-- Icon -->
        <div class="flex-shrink-0">
          <component :is="getIcon(toast.type)" :class="iconClasses(toast.type)" />
        </div>

        <!-- Content -->
        <div class="flex-1 ml-3">
          <p v-if="toast.title" class="font-semibold text-neutral-900 dark:text-neutral-100">
            {{ toast.title }}
          </p>
          <p class="text-sm" :class="toast.title ? 'text-neutral-600 dark:text-neutral-400' : 'text-neutral-900 dark:text-neutral-100'">
            {{ toast.message }}
          </p>
        </div>

        <!-- Close Button -->
        <button
          v-if="toast.closable !== false"
          type="button"
          class="ml-4 flex-shrink-0 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
          @click="removeToast(toast.id)"
        >
          <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<script setup>
import { ref, computed, h } from 'vue'

const props = defineProps({
  position: {
    type: String,
    default: 'top-right',
    validator: (value) => [
      'top-left', 'top-center', 'top-right',
      'bottom-left', 'bottom-center', 'bottom-right'
    ].includes(value)
  }
})

const toasts = ref([])
const timers = ref({})

const containerClasses = computed(() => {
  const baseClasses = 'fixed z-50 p-4 space-y-4 pointer-events-none'

  const positionClasses = {
    'top-left': 'top-0 left-0',
    'top-center': 'top-0 left-1/2 -translate-x-1/2',
    'top-right': 'top-0 right-0',
    'bottom-left': 'bottom-0 left-0',
    'bottom-center': 'bottom-0 left-1/2 -translate-x-1/2',
    'bottom-right': 'bottom-0 right-0'
  }

  return `${baseClasses} ${positionClasses[props.position]}`
})

const toastClasses = (toast) => {
  const baseClasses = [
    'flex items-start max-w-sm w-full p-4 rounded-lg shadow-lg',
    'bg-white dark:bg-neutral-800 border',
    'pointer-events-auto transition-all duration-300'
  ]

  const typeClasses = {
    success: 'border-success-200 dark:border-success-800',
    error: 'border-error-200 dark:border-error-800',
    warning: 'border-warning-200 dark:border-warning-800',
    info: 'border-primary-200 dark:border-primary-800',
    default: 'border-neutral-200 dark:border-neutral-700'
  }

  baseClasses.push(typeClasses[toast.type || 'default'])

  return baseClasses.join(' ')
}

const iconClasses = (type) => {
  const baseClasses = 'h-6 w-6'

  const colorClasses = {
    success: 'text-success-500',
    error: 'text-error-500',
    warning: 'text-warning-500',
    info: 'text-primary-500',
    default: 'text-neutral-500'
  }

  return `${baseClasses} ${colorClasses[type || 'default']}`
}

const getIcon = (type) => {
  const icons = {
    success: () => h('svg', { class: 'h-6 w-6', fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', {
        'fill-rule': 'evenodd',
        d: 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z',
        'clip-rule': 'evenodd'
      })
    ]),
    error: () => h('svg', { class: 'h-6 w-6', fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', {
        'fill-rule': 'evenodd',
        d: 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z',
        'clip-rule': 'evenodd'
      })
    ]),
    warning: () => h('svg', { class: 'h-6 w-6', fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', {
        'fill-rule': 'evenodd',
        d: 'M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z',
        'clip-rule': 'evenodd'
      })
    ]),
    info: () => h('svg', { class: 'h-6 w-6', fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', {
        'fill-rule': 'evenodd',
        d: 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z',
        'clip-rule': 'evenodd'
      })
    ]),
    default: () => h('svg', { class: 'h-6 w-6', fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', {
        'fill-rule': 'evenodd',
        d: 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z',
        'clip-rule': 'evenodd'
      })
    ])
  }

  return icons[type || 'default']
}

const addToast = (options) => {
  const id = Date.now() + Math.random()
  const toast = {
    id,
    type: options.type || 'default',
    title: options.title,
    message: options.message,
    duration: options.duration ?? 5000,
    closable: options.closable !== false
  }

  toasts.value.push(toast)

  if (toast.duration > 0) {
    timers.value[id] = setTimeout(() => {
      removeToast(id)
    }, toast.duration)
  }

  return id
}

const removeToast = (id) => {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index !== -1) {
    toasts.value.splice(index, 1)
  }
  if (timers.value[id]) {
    clearTimeout(timers.value[id])
    delete timers.value[id]
  }
}

const pauseTimer = (id) => {
  if (timers.value[id]) {
    clearTimeout(timers.value[id])
  }
}

const resumeTimer = (id) => {
  const toast = toasts.value.find(t => t.id === id)
  if (toast && toast.duration > 0) {
    timers.value[id] = setTimeout(() => {
      removeToast(id)
    }, 1000) // Resume with 1 second
  }
}

defineExpose({
  addToast,
  removeToast
})
</script>

<style scoped>
/* Toast Transition */
.toast-enter-active {
  transition: all 0.3s ease-out;
}

.toast-leave-active {
  transition: all 0.2s ease-in;
}

.toast-enter-from {
  transform: translateX(100%);
  opacity: 0;
}

.toast-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>
