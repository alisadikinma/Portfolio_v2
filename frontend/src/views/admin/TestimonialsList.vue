<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-display font-bold">Manage Testimonials</h1>
      <BaseButton>
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Testimonial
      </BaseButton>
    </div>

    <BaseCard>
      <p class="text-neutral-600 dark:text-neutral-400">
        Testimonials management interface coming soon...
      </p>
    </BaseCard>
  </div>
</template>

<script setup>
import { BaseCard, BaseButton } from '@/components/base'
</script>
