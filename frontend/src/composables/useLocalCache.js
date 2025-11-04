/**
 * localStorage Cache Utility
 * Persistent caching with expiry for instant page loads
 */

const CACHE_PREFIX = 'portfolio_cache_'
const DEFAULT_TTL = 5 * 60 * 1000 // 5 minutes

export function useLocalCache() {
  /**
   * Set cache with expiry
   */
  const setCache = (key, data, ttl = DEFAULT_TTL) => {
    try {
      const item = {
        data,
        expiry: Date.now() + ttl,
        timestamp: Date.now()
      }
      localStorage.setItem(CACHE_PREFIX + key, JSON.stringify(item))
      return true
    } catch (error) {
      console.warn('Cache set failed:', error)
      return false
    }
  }

  /**
   * Get cache if valid
   */
  const getCache = (key) => {
    try {
      const itemStr = localStorage.getItem(CACHE_PREFIX + key)
      if (!itemStr) return null

      const item = JSON.parse(itemStr)
      
      // Check expiry
      if (Date.now() > item.expiry) {
        localStorage.removeItem(CACHE_PREFIX + key)
        return null
      }

      return item.data
    } catch (error) {
      console.warn('Cache get failed:', error)
      return null
    }
  }

  /**
   * Remove specific cache
   */
  const removeCache = (key) => {
    try {
      localStorage.removeItem(CACHE_PREFIX + key)
      return true
    } catch (error) {
      console.warn('Cache remove failed:', error)
      return false
    }
  }

  /**
   * Clear all portfolio caches
   */
  const clearAllCache = () => {
    try {
      const keys = Object.keys(localStorage)
      keys.forEach(key => {
        if (key.startsWith(CACHE_PREFIX)) {
          localStorage.removeItem(key)
        }
      })
      return true
    } catch (error) {
      console.warn('Cache clear failed:', error)
      return false
    }
  }

  /**
   * Check if cache exists and valid
   */
  const hasValidCache = (key) => {
    return getCache(key) !== null
  }

  return {
    setCache,
    getCache,
    removeCache,
    clearAllCache,
    hasValidCache
  }
}
