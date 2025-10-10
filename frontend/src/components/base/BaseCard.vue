<template>
  <component
    :is="tag"
    :class="cardClasses"
  >
    <!-- Card Header -->
    <div v-if="$slots.header || title" class="border-b border-neutral-200 dark:border-neutral-700 pb-4 mb-4">
      <slot name="header">
        <h3 v-if="title" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
          {{ title }}
        </h3>
      </slot>
    </div>

    <!-- Card Body -->
    <div :class="bodyClass">
      <slot />
    </div>

    <!-- Card Footer -->
    <div v-if="$slots.footer" class="border-t border-neutral-200 dark:border-neutral-700 pt-4 mt-4">
      <slot name="footer" />
    </div>
  </component>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  tag: {
    type: String,
    default: 'div'
  },
  title: {
    type: String,
    default: null
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'bordered', 'elevated', 'flat'].includes(value)
  },
  padding: {
    type: String,
    default: 'md',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  hover: {
    type: Boolean,
    default: false
  },
  clickable: {
    type: Boolean,
    default: false
  },
  rounded: {
    type: String,
    default: 'lg',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'xl', '2xl', '3xl'].includes(value)
  },
  bodyClass: {
    type: String,
    default: ''
  }
})

const cardClasses = computed(() => {
  const classes = ['bg-white dark:bg-neutral-800 transition-all duration-300']

  // Padding
  const paddingClasses = {
    none: '',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
    xl: 'p-10'
  }
  if (props.padding !== 'none') {
    classes.push(paddingClasses[props.padding])
  }

  // Border Radius
  const roundedClasses = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    xl: 'rounded-xl',
    '2xl': 'rounded-2xl',
    '3xl': 'rounded-3xl'
  }
  classes.push(roundedClasses[props.rounded])

  // Variants
  const variantClasses = {
    default: 'shadow-md border border-neutral-200 dark:border-neutral-700',
    bordered: 'border-2 border-neutral-300 dark:border-neutral-600',
    elevated: 'shadow-lg',
    flat: 'shadow-none'
  }
  classes.push(variantClasses[props.variant])

  // Hover effect
  if (props.hover) {
    classes.push('hover:shadow-lg hover:-translate-y-1')
  }

  // Clickable cursor
  if (props.clickable) {
    classes.push('cursor-pointer')
  }

  return classes.join(' ')
})
</script>
