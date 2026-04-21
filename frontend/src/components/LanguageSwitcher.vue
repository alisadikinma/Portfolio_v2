<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { setManualLocale } from '@/utils/resolveLocale'

const { locale } = useI18n()
const router = useRouter()
const route = useRoute()

const languages = [
  { code: 'en', flag: '🇺🇸' },
  { code: 'id', flag: '🇮🇩' }
]

function switchLanguage(lang) {
  if (locale.value === lang) return

  const currentLang = route.params.lang || locale.value
  const newPath = route.fullPath.replace(`/${currentLang}`, `/${lang}`)

  locale.value = lang
  // Stamps `locale_manual=true` so future visits respect this choice
  // even if the user's timezone says otherwise (Indonesian user who
  // prefers to read in English, for example).
  setManualLocale(lang)
  router.replace(newPath !== route.fullPath ? newPath : `/${lang}`)
}
</script>

<template>
  <div class="flex items-center gap-0.5 rounded-full bg-white/5 border border-white/10 p-0.5">
    <button
      v-for="lang in languages"
      :key="lang.code"
      @click="switchLanguage(lang.code)"
      class="px-1.5 py-0.5 rounded-full text-sm transition-all duration-300"
      :class="[
        locale === lang.code
          ? 'bg-accent-gold/15 border border-accent-gold/30 scale-110'
          : 'opacity-50 hover:opacity-80 hover:bg-white/5 border border-transparent'
      ]"
      :title="lang.code === 'en' ? 'English' : 'Indonesian'"
    >
      {{ lang.flag }}
    </button>
  </div>
</template>
