/**
 * Modal composable
 * @module composables/useModal
 */

import { computed } from 'vue'
import { useUiStore } from '@/stores/ui'

/**
 * Modal composable for managing modals
 * @returns {Object} Modal methods and state
 */
export function useModal() {
  const uiStore = useUiStore()

  // Computed properties
  const isOpen = computed(() => uiStore.modalOpen)
  const component = computed(() => uiStore.modalComponent)
  const props = computed(() => uiStore.modalProps)

  /**
   * Open modal with component
   * @param {Object} modalComponent - Vue component
   * @param {Object} modalProps - Component props
   */
  const open = (modalComponent, modalProps = {}) => {
    uiStore.openModal(modalComponent, modalProps)
  }

  /**
   * Close modal
   */
  const close = () => {
    uiStore.closeModal()
  }

  /**
   * Toggle modal
   * @param {Object} modalComponent - Vue component
   * @param {Object} modalProps - Component props
   */
  const toggle = (modalComponent, modalProps = {}) => {
    if (isOpen.value) {
      close()
    } else {
      open(modalComponent, modalProps)
    }
  }

  return {
    // State
    isOpen,
    component,
    props,

    // Methods
    open,
    close,
    toggle,
  }
}
