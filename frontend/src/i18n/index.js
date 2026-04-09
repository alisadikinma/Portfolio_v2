import { createI18n } from 'vue-i18n'
import en from './en.json'
import id from './id.json'

const savedLocale = typeof localStorage !== 'undefined'
  ? localStorage.getItem('locale') || 'en'
  : 'en'

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: { en, id }
})

export default i18n
