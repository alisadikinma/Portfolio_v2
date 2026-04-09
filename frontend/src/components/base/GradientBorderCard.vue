<template>
  <component
    :is="tag"
    :class="[
      'gradient-border',
      paddingClasses[padding],
      { 'glow-gold': glow, 'cursor-pointer': clickable }
    ]"
  >
    <slot />
  </component>
</template>

<script setup>
defineProps({
  tag: {
    type: String,
    default: 'div'
  },
  padding: {
    type: String,
    default: 'md',
    validator: (v) => ['none', 'sm', 'md', 'lg', 'xl'].includes(v)
  },
  glow: {
    type: Boolean,
    default: false
  },
  clickable: {
    type: Boolean,
    default: false
  }
})

const paddingClasses = {
  none: '',
  sm: 'p-4',
  md: 'p-6',
  lg: 'p-8',
  xl: 'p-10'
}
</script>
