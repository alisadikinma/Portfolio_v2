<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'

const { locale } = useI18n()
const router = useRouter()
const route = useRoute()

const languages = [
  { code: 'en', label: 'EN' },
  { code: 'id', label: 'ID' }
]

function switchLanguage(lang) {
  if (locale.value === lang) return

  // Replace the lang segment in the current path
  const currentLang = route.params.lang || locale.value
  const newPath = route.fullPath.replace(`/${currentLang}`, `/${lang}`)

  locale.value = lang
  localStorage.setItem('locale', lang)
  router.replace(newPath !== route.fullPath ? newPath : `/${lang}`)
}
</script>

<template>
  <div class="flex items-center gap-0.5 rounded-full bg-white/5 border border-white/10 p-0.5">
    <button
      v-for="lang in languages"
      :key="lang.code"
      @click="switchLanguage(lang.code)"
      class="px-2.5 py-1 rounded-full text-xs font-mono font-medium tracking-wider transition-all duration-300"
      :class="[
        locale === lang.code
          ? 'bg-accent-gold/15 text-accent-gold border border-accent-gold/30'
          : 'text-fg-muted hover:text-fg-primary hover:bg-white/5 border border-transparent'
      ]"
    >
      {{ lang.label }}
    </button>
  </div>
</template>
