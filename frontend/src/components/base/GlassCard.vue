<template>
  <component
    :is="tag"
    :class="[
      'glass-card',
      paddingClasses[padding],
      { 'cursor-pointer': clickable }
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
