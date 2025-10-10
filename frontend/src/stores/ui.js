import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUIStore = defineStore('ui', () => {
  // State
  const isSidebarOpen = ref(false)
  const isMobileMenuOpen = ref(false)
  const activeModal = ref(null)
  const modals = ref({})
  const toasts = ref([])
  const isLoading = ref(false)
  const loadingMessage = ref('')

  // Sidebar Actions
  const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value
  }

  const openSidebar = () => {
    isSidebarOpen.value = true
  }

  const closeSidebar = () => {
    isSidebarOpen.value = false
  }

  // Mobile Menu Actions
  const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value
  }

  const openMobileMenu = () => {
    isMobileMenuOpen.value = true
  }

  const closeMobileMenu = () => {
    isMobileMenuOpen.value = false
  }

  // Modal Actions
  const openModal = (modalName, data = null) => {
    activeModal.value = modalName
    modals.value[modalName] = {
      isOpen: true,
      data
    }
  }

  const closeModal = (modalName) => {
    if (modalName) {
      modals.value[modalName] = {
        isOpen: false,
        data: null
      }
      if (activeModal.value === modalName) {
        activeModal.value = null
      }
    } else {
      // Close all modals
      Object.keys(modals.value).forEach(key => {
        modals.value[key] = { isOpen: false, data: null }
      })
      activeModal.value = null
    }
  }

  const isModalOpen = (modalName) => {
    return modals.value[modalName]?.isOpen || false
  }

  const getModalData = (modalName) => {
    return modals.value[modalName]?.data || null
  }

  // Toast Actions
  const addToast = (toast) => {
    const id = Date.now() + Math.random()
    const newToast = {
      id,
      type: toast.type || 'info',
      title: toast.title || null,
      message: toast.message,
      duration: toast.duration ?? 5000,
      closable: toast.closable !== false
    }

    toasts.value.push(newToast)

    // Auto-remove toast after duration
    if (newToast.duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, newToast.duration)
    }

    return id
  }

  const removeToast = (id) => {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  const clearToasts = () => {
    toasts.value = []
  }

  // Loading Actions
  const startLoading = (message = 'Loading...') => {
    isLoading.value = true
    loadingMessage.value = message
  }

  const stopLoading = () => {
    isLoading.value = false
    loadingMessage.value = ''
  }

  // Convenience toast methods
  const showSuccess = (message, title = null, duration = 5000) => {
    return addToast({
      type: 'success',
      title,
      message,
      duration
    })
  }

  const showError = (message, title = null, duration = 0) => {
    return addToast({
      type: 'error',
      title,
      message,
      duration // 0 = persistent
    })
  }

  const showWarning = (message, title = null, duration = 5000) => {
    return addToast({
      type: 'warning',
      title,
      message,
      duration
    })
  }

  const showInfo = (message, title = null, duration = 5000) => {
    return addToast({
      type: 'info',
      title,
      message,
      duration
    })
  }

  return {
    // State
    isSidebarOpen,
    isMobileMenuOpen,
    activeModal,
    modals,
    toasts,
    isLoading,
    loadingMessage,

    // Sidebar Actions
    toggleSidebar,
    openSidebar,
    closeSidebar,

    // Mobile Menu Actions
    toggleMobileMenu,
    openMobileMenu,
    closeMobileMenu,

    // Modal Actions
    openModal,
    closeModal,
    isModalOpen,
    getModalData,

    // Toast Actions
    addToast,
    removeToast,
    clearToasts,
    showSuccess,
    showError,
    showWarning,
    showInfo,

    // Loading Actions
    startLoading,
    stopLoading
  }
})
