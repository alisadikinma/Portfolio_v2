/**
 * Toast notification composable
 * @module composables/useToast
 */

import { useUiStore } from '@/stores/ui'

/**
 * Toast notification composable
 * @returns {Object} Toast methods
 */
export function useToast() {
  const uiStore = useUiStore()

  /**
   * Show success toast
   * @param {string} message - Toast message
   * @param {number} duration - Duration in ms
   */
  const success = (message, duration = 3000) => {
    return uiStore.showSuccess(message, null, duration)
  }

  /**
   * Show error toast
   * @param {string} message - Toast message
   * @param {number} duration - Duration in ms
   */
  const error = (message, duration = 5000) => {
    return uiStore.showError(message, null, duration)
  }

  /**
   * Show warning toast
   * @param {string} message - Toast message
   * @param {number} duration - Duration in ms
   */
  const warning = (message, duration = 4000) => {
    return uiStore.showWarning(message, null, duration)
  }

  /**
   * Show info toast
   * @param {string} message - Toast message
   * @param {number} duration - Duration in ms
   */
  const info = (message, duration = 3000) => {
    return uiStore.showInfo(message, null, duration)
  }

  /**
   * Show custom toast
   * @param {Object} options - Toast configuration
   */
  const show = (options) => {
    return uiStore.addToast(options)
  }

  /**
   * Remove specific toast
   * @param {number} id - Toast ID
   */
  const remove = (id) => {
    return uiStore.removeToast(id)
  }

  /**
   * Clear all toasts
   */
  const clear = () => {
    return uiStore.clearToasts()
  }

  return {
    success,
    error,
    warning,
    info,
    show,
    remove,
    clear,
  }
}
