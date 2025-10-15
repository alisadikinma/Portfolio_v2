import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // State
  const isDark = ref(false)
  const colorScheme = ref('system') // 'light', 'dark', 'system'

  // Initialize theme from localStorage or system preference
  const initTheme = () => {
    console.log('🎨 Initializing theme...')
    const savedTheme = localStorage.getItem('theme')
    console.log('Saved theme from localStorage:', savedTheme)

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

    console.log('Theme initialized:', { isDark: isDark.value, colorScheme: colorScheme.value })
    applyTheme()
  }

  // Apply theme to document
  const applyTheme = () => {
    console.log('🎨 Applying theme:', isDark.value ? 'dark' : 'light')
    
    // Remove any existing class first
    document.documentElement.classList.remove('dark', 'light')
    
    // Add the appropriate class
    if (isDark.value) {
      document.documentElement.classList.add('dark')
      document.documentElement.setAttribute('data-theme', 'dark')
      console.log('✅ Dark mode enabled')
    } else {
      document.documentElement.classList.add('light')
      document.documentElement.setAttribute('data-theme', 'light')
      console.log('✅ Light mode enabled')
    }
    
    // Log the current classes for debugging
    console.log('📋 Current HTML classes:', document.documentElement.className)
  }

  // Toggle dark mode
  const toggleDark = () => {
    console.log('🔄 Toggle dark mode clicked!')
    console.log('Before toggle - isDark:', isDark.value)
    
    isDark.value = !isDark.value
    colorScheme.value = isDark.value ? 'dark' : 'light'
    localStorage.setItem('theme', colorScheme.value)
    
    console.log('After toggle - isDark:', isDark.value)
    console.log('Color scheme:', colorScheme.value)
    
    // Apply theme immediately
    applyTheme()
  }

  // Set specific theme
  const setTheme = (theme) => {
    console.log('🎯 Setting theme to:', theme)
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

    const handleChange = (e) => {
      console.log('🔔 System theme changed:', e.matches ? 'dark' : 'light')
      if (colorScheme.value === 'system') {
        isDark.value = e.matches
        applyTheme()
      }
    }

    mediaQuery.addEventListener('change', handleChange)
    
    // Return cleanup function
    return () => mediaQuery.removeEventListener('change', handleChange)
  }

  // Watch for theme changes (redundant with direct applyTheme call, but keep for safety)
  watch(isDark, () => {
    console.log('👀 isDark watcher triggered:', isDark.value)
  })

  return {
    // State
    isDark,
    colorScheme,

    // Actions
    initTheme,
    toggleDark,
    setTheme,
    listenToSystemTheme,
    applyTheme // Export for manual use if needed
  }
})
