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
    return uiStore.success(message, duration)
  }

  /**
   * Show error toast
   * @param {string} message - Toast message
   * @param {number} duration - Duration in ms
   */
  const error = (message, duration = 5000) => {
    return uiStore.error(message, duration)
  }

  /**
   * Show warning toast
   * @param {string} message - Toast message
   * @param {number} duration - Duration in ms
   */
  const warning = (message, duration = 4000) => {
    return uiStore.warning(message, duration)
  }

  /**
   * Show info toast
   * @param {string} message - Toast message
   * @param {number} duration - Duration in ms
   */
  const info = (message, duration = 3000) => {
    return uiStore.info(message, duration)
  }

  /**
   * Show custom toast
   * @param {Object} options - Toast configuration
   */
  const show = (options) => {
    return uiStore.showToast(options)
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
