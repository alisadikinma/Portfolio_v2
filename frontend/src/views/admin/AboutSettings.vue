<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-display font-bold">About Page Settings</h1>
      <BaseButton>
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
        </svg>
        Save Changes
      </BaseButton>
    </div>

    <BaseCard>
      <p class="text-neutral-600 dark:text-neutral-400">
        About page configuration interface coming soon...
      </p>
    </BaseCard>
  </div>
</template>

<script setup>
import { BaseCard, BaseButton } from '@/components/base'
</script>
