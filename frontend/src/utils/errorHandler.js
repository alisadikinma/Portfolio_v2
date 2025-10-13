/**
 * Error Handler Utility
 * Centralized error handling for API calls and application errors
 */

import { useUIStore } from '@/stores/ui'

/**
 * Handle API errors
 * @param {Error} error - The error object
 * @param {Object} options - Error handling options
 * @returns {Object} Formatted error object
 */
export function handleApiError(error, options = {}) {
  const uiStore = useUIStore()

  const {
    showToast = true,
    toastDuration = 0, // 0 = persistent
    logError = true,
    customMessage = null
  } = options

  let errorMessage = customMessage || 'An unexpected error occurred'
  let statusCode = null
  let validationErrors = null

  if (error.response) {
    // Server responded with error
    statusCode = error.response.status
    const data = error.response.data

    switch (statusCode) {
      case 400:
        errorMessage = data.message || 'Bad request'
        break
      case 401:
        errorMessage = data.message || 'Unauthorized. Please login again.'
        break
      case 403:
        errorMessage = data.message || 'Access forbidden'
        break
      case 404:
        errorMessage = data.message || 'Resource not found'
        break
      case 422:
        errorMessage = data.message || 'Validation failed'
        validationErrors = data.errors || null
        break
      case 429:
        errorMessage = data.message || 'Too many requests. Please try again later.'
        break
      case 500:
        errorMessage = data.message || 'Internal server error'
        break
      case 503:
        errorMessage = data.message || 'Service unavailable'
        break
      default:
        errorMessage = data.message || `Error ${statusCode}`
    }
  } else if (error.request) {
    // Network error - no response received
    errorMessage = 'Network error. Please check your connection.'
    statusCode = 'NETWORK_ERROR'
  } else {
    // Other error
    errorMessage = error.message || errorMessage
    statusCode = 'UNKNOWN_ERROR'
  }

  // Log error if enabled
  if (logError) {
    console.error('[Error Handler]', {
      message: errorMessage,
      statusCode,
      validationErrors,
      error
    })
  }

  // Show toast notification if enabled
  if (showToast) {
    uiStore.showError(errorMessage, 'Error', toastDuration)
  }

  return {
    message: errorMessage,
    statusCode,
    validationErrors,
    originalError: error
  }
}

/**
 * Handle validation errors
 * @param {Object} errors - Validation errors object
 * @returns {Object} Formatted validation errors
 */
export function handleValidationErrors(errors) {
  if (!errors || typeof errors !== 'object') {
    return {}
  }

  const formattedErrors = {}

  for (const [field, messages] of Object.entries(errors)) {
    // Laravel returns arrays of error messages
    formattedErrors[field] = Array.isArray(messages) ? messages[0] : messages
  }

  return formattedErrors
}

/**
 * Handle success response
 * @param {String} message - Success message
 * @param {Object} options - Success handling options
 */
export function handleSuccess(message, options = {}) {
  const uiStore = useUIStore()

  const {
    showToast = true,
    toastDuration = 5000,
    title = 'Success'
  } = options

  if (showToast) {
    uiStore.showSuccess(message, title, toastDuration)
  }
}

/**
 * Global error handler for uncaught errors
 * @param {Error} error - The error object
 * @param {String} context - Error context
 */
export function globalErrorHandler(error, context = '') {
  const uiStore = useUIStore()

  console.error(`[Global Error${context ? ' - ' + context : ''}]`, error)

  uiStore.showError(
    'An unexpected error occurred. Please try again or contact support.',
    'Application Error',
    0
  )
}

/**
 * Create error message from response
 * @param {Object} response - API response object
 * @returns {String} Error message
 */
export function createErrorMessage(response) {
  if (!response) return 'Unknown error occurred'

  if (response.data) {
    // Check for message
    if (response.data.message) {
      return response.data.message
    }

    // Check for errors object
    if (response.data.errors) {
      const errors = Object.values(response.data.errors).flat()
      return errors.join(', ')
    }

    // Check for error string
    if (response.data.error) {
      return response.data.error
    }
  }

  return 'An error occurred'
}

/**
 * Check if error is network error
 * @param {Error} error - The error object
 * @returns {Boolean}
 */
export function isNetworkError(error) {
  return !error.response && error.request
}

/**
 * Check if error is authentication error
 * @param {Error} error - The error object
 * @returns {Boolean}
 */
export function isAuthError(error) {
  return error.response && [401, 403].includes(error.response.status)
}

/**
 * Check if error is validation error
 * @param {Error} error - The error object
 * @returns {Boolean}
 */
export function isValidationError(error) {
  return error.response && error.response.status === 422
}

/**
 * Retry function with exponential backoff
 * @param {Function} fn - Function to retry
 * @param {Number} maxRetries - Maximum number of retries
 * @param {Number} delay - Initial delay in ms
 * @returns {Promise}
 */
export async function retryWithBackoff(fn, maxRetries = 3, delay = 1000) {
  let lastError

  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fn()
    } catch (error) {
      lastError = error

      // Don't retry on client errors (4xx)
      if (error.response && error.response.status >= 400 && error.response.status < 500) {
        throw error
      }

      // Wait with exponential backoff
      if (i < maxRetries - 1) {
        await new Promise(resolve => setTimeout(resolve, delay * Math.pow(2, i)))
      }
    }
  }

  throw lastError
}

export default {
  handleApiError,
  handleValidationErrors,
  handleSuccess,
  globalErrorHandler,
  createErrorMessage,
  isNetworkError,
  isAuthError,
  isValidationError,
  retryWithBackoff
}
