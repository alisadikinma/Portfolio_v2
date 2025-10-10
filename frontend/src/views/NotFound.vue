<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 px-4">
    <div class="text-center max-w-2xl">
      <!-- 404 Illustration -->
      <div class="mb-8">
        <h1 class="text-9xl md:text-[12rem] font-display font-bold text-gradient leading-none">
          404
        </h1>
      </div>

      <!-- Content -->
      <div class="mb-8">
        <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">
          Page Not Found
        </h2>
        <p class="text-lg text-neutral-600 dark:text-neutral-400 mb-2">
          Oops! The page you're looking for doesn't exist.
        </p>
        <p class="text-neutral-600 dark:text-neutral-400">
          It might have been moved or deleted.
        </p>
      </div>

      <!-- Actions -->
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <BaseButton variant="primary" size="lg" @click="$router.push('/')">
          Go Home
        </BaseButton>
        <BaseButton variant="outline" size="lg" @click="$router.back()">
          Go Back
        </BaseButton>
      </div>

      <!-- Helpful Links -->
      <div class="mt-12 pt-8 border-t border-neutral-200 dark:border-neutral-700">
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
          Or try one of these pages:
        </p>
        <div class="flex flex-wrap gap-3 justify-center">
          <router-link
            v-for="link in helpfulLinks"
            :key="link.path"
            :to="link.path"
            class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors"
          >
            {{ link.name }}
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { BaseButton } from '@/components/base'

const helpfulLinks = [
  { name: 'Home', path: '/' },
  { name: 'About', path: '/about' },
  { name: 'Projects', path: '/projects' },
  { name: 'Blog', path: '/blog' },
  { name: 'Gallery', path: '/gallery' },
  { name: 'Contact', path: '/contact' }
]
</script>
