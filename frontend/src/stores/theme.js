import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // State - ALWAYS LIGHT MODE
  const isDark = ref(false)
  const colorScheme = ref('light')

  // Initialize theme - PERMANENT LIGHT MODE
  const initTheme = () => {
    console.log('🎨 Setting permanent light mode...')
    isDark.value = false
    colorScheme.value = 'light'
    localStorage.setItem('theme', 'light')
    applyTheme()
  }

  // Apply theme to document - ALWAYS LIGHT
  const applyTheme = () => {
    const html = document.documentElement
    html.classList.remove('dark')
    html.classList.add('light')
    html.setAttribute('data-theme', 'light')
    updateMetaThemeColor('#ffffff')
  }

  // Update mobile browser theme color
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
    // State (read-only)
    isDark,
    colorScheme,

    // Actions
    initTheme,
    applyTheme
  }
})
