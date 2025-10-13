import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // State
  const isDark = ref(false)
  const colorScheme = ref('system') // 'light', 'dark', 'system'

  // Initialize theme from localStorage or system preference
  const initTheme = () => {
    const savedTheme = localStorage.getItem('theme')

    if (savedTheme) {
      colorScheme.value = savedTheme

      if (savedTheme === 'system') {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      } else {
        isDark.value = savedTheme === 'dark'
      }
    } else {
      // Default to system preference
      isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      colorScheme.value = 'system'
    }

    applyTheme()
  }

  // Apply theme to document
  const applyTheme = () => {
    if (isDark.value) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }

  // Toggle dark mode
  const toggleDark = () => {
    isDark.value = !isDark.value
    colorScheme.value = isDark.value ? 'dark' : 'light'
    localStorage.setItem('theme', colorScheme.value)
    applyTheme()
  }

  // Set specific theme
  const setTheme = (theme) => {
    colorScheme.value = theme
    localStorage.setItem('theme', theme)

    if (theme === 'system') {
      isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
    } else {
      isDark.value = theme === 'dark'
    }

    applyTheme()
  }

  // Listen for system theme changes
  const listenToSystemTheme = () => {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')

    mediaQuery.addEventListener('change', (e) => {
      if (colorScheme.value === 'system') {
        isDark.value = e.matches
        applyTheme()
      }
    })
  }

  // Watch for theme changes
  watch(isDark, () => {
    applyTheme()
  })

  return {
    // State
    isDark,
    colorScheme,

    // Actions
    initTheme,
    toggleDark,
    setTheme,
    listenToSystemTheme
  }
})
