/**
 * Formatting utility functions
 * @module utils/formatters
 */

/**
 * Format date to readable string
 * @param {string|Date} date - Date
 * @param {string} format - Format type
 * @returns {string} Formatted date
 */
export const formatDate = (date, format = 'long') => {
  if (!date) return ''

  const d = new Date(date)

  const options = format === 'short'
    ? { year: 'numeric', month: 'short', day: 'numeric' }
    : { year: 'numeric', month: 'long', day: 'numeric' }

  return d.toLocaleDateString('en-US', options)
}

/**
 * Format date to relative time
 * @param {Date} date - Date
 * @returns {string} Relative time string
 */
export const formatRelativeTime = (date) => {
  const now = new Date()
  const diffInSeconds = Math.floor((now - date) / 1000)

  if (diffInSeconds < 60) return 'just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`

  return formatDate(date, 'short')
}

/**
 * Truncate text with ellipsis
 * @param {string} text - Text
 * @param {number} length - Maximum length
 * @param {string} suffix - Suffix
 * @returns {string} Truncated text
 */
export const truncate = (text, length, suffix = '...') => {
  if (!text || text.length <= length) return text
  return text.substring(0, length).trim() + suffix
}

/**
 * Capitalize first letter
 * @param {string} str - String
 * @returns {string} Capitalized string
 */
export const capitalize = (str) => {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase()
}

/**
 * Strip HTML tags
 * @param {string} html - HTML string
 * @returns {string} Plain text
 */
export const stripHtml = (html) => {
  if (!html) return ''
  return html.replace(/<[^>]*>/g, '')
}
