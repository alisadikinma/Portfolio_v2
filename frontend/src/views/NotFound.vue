<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 px-4">
    <div class="text-center max-w-2xl">
      <h1 class="text-9xl md:text-[12rem] font-display font-bold text-gradient leading-none mb-8">
        404
      </h1>
      <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">
        Page Not Found
      </h2>
      <p class="text-lg text-neutral-600 dark:text-neutral-400 mb-8">
        This page does not exist in the admin app.
      </p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <BaseButton variant="primary" size="lg" @click="$router.push('/admin')">
          Go to Admin
        </BaseButton>
        <BaseButton variant="outline" size="lg" @click="$router.back()">
          Go Back
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { BaseButton } from '@/components/base'
</script>
