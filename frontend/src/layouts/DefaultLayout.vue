<template>
  <div class="min-h-screen flex flex-col bg-bg-deep text-fg-primary">
    <!-- Skip Navigation Link -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-accent-gold focus:text-bg-deep focus:rounded-lg focus:shadow-lg"
    >
      Skip to main content
    </a>

    <!-- Aurora Background — Mesh gradient orbs -->
    <AuroraBackground />

    <!-- Film Grain Noise Overlay — fixed, pointer-events-none -->
    <div class="noise-overlay" aria-hidden="true" />

    <!-- Navigation — Floating Glass Pill -->
    <TheNavigation />

    <!-- Main Content -->
    <main id="main-content" class="flex-1">
      <router-view />
    </main>

    <!-- Footer -->
    <TheFooter />

    <!-- Chatbot (public pages only) -->
    <AskAliChatbot />

    <!-- Global Toast -->
    <BaseToast position="top-right" />
  </div>
</template>

<script setup>
import AuroraBackground from '@/components/AuroraBackground.vue'
import TheNavigation from '@/components/TheNavigation.vue'
import TheFooter from '@/components/TheFooter.vue'
import AskAliChatbot from '@/components/AskAliChatbot.vue'
import { BaseToast } from '@/components/base'
</script>
