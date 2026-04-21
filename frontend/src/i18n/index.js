import { createI18n } from 'vue-i18n'
import en from './en.json'
import id from './id.json'
import { resolveLocale, persistDetectedLocale } from '@/utils/resolveLocale'

// Cache the first-visit auto-detection so we don't re-detect on every boot.
// Never overrides a manual user choice — see resolveLocale.js for the rules.
persistDetectedLocale()

const i18n = createI18n({
  legacy: false,
  locale: resolveLocale(),
  fallbackLocale: 'en',
  messages: { en, id }
})

export default i18n
