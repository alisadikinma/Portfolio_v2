/**
 * Validation utility functions
 * @module utils/validators
 */

/**
 * Validate email format
 * @param {string} email - Email address
 * @returns {boolean} True if valid
 */
export const isValidEmail = (email) => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

/**
 * Validate password strength
 * @param {string} password - Password
 * @returns {Object} Validation result
 */
export const validatePassword = (password) => {
  const result = {
    isValid: false,
    strength: 0,
    errors: [],
  }

  if (password.length < 8) {
    result.errors.push('Password must be at least 8 characters long')
  }

  if (!/[a-z]/.test(password)) {
    result.errors.push('Password must contain at least one lowercase letter')
  }

  if (!/[A-Z]/.test(password)) {
    result.errors.push('Password must contain at least one uppercase letter')
  }

  if (!/\d/.test(password)) {
    result.errors.push('Password must contain at least one number')
  }

  // Calculate strength (0-4)
  result.strength = 4 - result.errors.length
  result.isValid = result.errors.length === 0

  return result
}

/**
 * Validate URL format
 * @param {string} url - URL
 * @returns {boolean} True if valid
 */
export const isValidUrl = (url) => {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}

/**
 * Validate required field
 * @param {any} value - Value
 * @returns {boolean} True if not empty
 */
export const isRequired = (value) => {
  if (typeof value === 'string') {
    return value.trim().length > 0
  }
  return value !== null && value !== undefined && value !== ''
}

/**
 * Validate minimum length
 * @param {string} value - String
 * @param {number} min - Minimum length
 * @returns {boolean} True if meets minimum
 */
export const minLength = (value, min) => {
  return value && value.length >= min
}

/**
 * Validate maximum length
 * @param {string} value - String
 * @param {number} max - Maximum length
 * @returns {boolean} True if within maximum
 */
export const maxLength = (value, max) => {
  return !value || value.length <= max
}
