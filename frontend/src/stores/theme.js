import { defineStore } from 'pinia'
import { ref } from 'vue'

const STORAGE_KEY = 'theme'

export const useThemeStore = defineStore('theme', () => {
  // State — dark by default (matches the OLED Deep design system)
  const isDark = ref(true)
  const colorScheme = ref('dark')

  // Resolve the persisted preference; default to dark when none is stored.
  const resolvePersisted = () => {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored === 'light' ? false : true
  }

  // Apply current theme to <html> + mobile browser chrome.
  const applyTheme = () => {
    const html = document.documentElement
    if (isDark.value) {
      html.classList.add('dark')
      html.classList.remove('light')
    } else {
      html.classList.add('light')
      html.classList.remove('dark')
    }
    // Inline style wins over the @layer base `color-scheme: dark` rule.
    html.style.colorScheme = isDark.value ? 'dark' : 'light'
    html.setAttribute('data-theme', isDark.value ? 'dark' : 'light')
    updateMetaThemeColor(isDark.value ? '#050505' : '#ffffff')
  }

  const setDark = (value) => {
    isDark.value = value
    colorScheme.value = value ? 'dark' : 'light'
    localStorage.setItem(STORAGE_KEY, colorScheme.value)
    applyTheme()
  }

  // Initialize from persisted preference (or dark default).
  const initTheme = () => {
    setDark(resolvePersisted())
  }

  // Toggle between dark and light, persisting the choice.
  const toggleDark = () => {
    setDark(!isDark.value)
  }

  // Update mobile browser theme color.
  const updateMetaThemeColor = (color) => {
    let metaTheme = document.querySelector('meta[name="theme-color"]')
    if (!metaTheme) {
      metaTheme = document.createElement('meta')
      metaTheme.name = 'theme-color'
      document.head.appendChild(metaTheme)
    }
    metaTheme.content = color
  }

  return {
    // State
    isDark,
    colorScheme,

    // Actions
    initTheme,
    applyTheme,
    toggleDark,
    setDark,
  }
})
