/**
 * Composable for managing dynamic meta tags and SEO
 * All meta tags loaded from database settings (no hardcoded values)
 */
export function useMetaTags() {
  /**
   * Set meta tags from site settings (from CMS)
   * @param {Object} siteSettings - Settings from database
   */
  const setMetaFromSettings = (siteSettings) => {
    if (!siteSettings) return

    const backendUrl = import.meta.env.VITE_API_BASE_URL?.replace('/api', '') || ''
    
    // Parse meta_tags if it's JSON string
    let metaTags = {}
    if (siteSettings.meta_tags) {
      try {
        metaTags = typeof siteSettings.meta_tags === 'string' 
          ? JSON.parse(siteSettings.meta_tags) 
          : siteSettings.meta_tags
      } catch (e) {
        console.warn('Failed to parse meta_tags:', e)
      }
    }

    // Site logo/favicon
    const logoPath = siteSettings.site_logo
    const logoUrl = logoPath?.startsWith('http') 
      ? logoPath 
      : logoPath?.startsWith('/') 
        ? `${backendUrl}${logoPath}` 
        : null

    // OG Image - priority: meta_tags.og_image > site_logo
    const ogImage = metaTags.og_image || logoUrl || `${window.location.origin}/og-image.jpg`

    // Site title pattern from settings
    const siteTitle = siteSettings.site_name || 'Portfolio'
    const siteDesc = siteSettings.site_description || 'Professional Portfolio Website'

    // Update page title
    document.title = `${siteTitle} - ${siteDesc}`

    // Update favicon dynamically
    if (logoUrl) {
      updateFavicon(logoUrl)
    }

    // Primary Meta Tags
    updateMetaTag('name', 'title', `${siteTitle} - ${siteDesc}`)
    updateMetaTag('name', 'description', siteDesc)
    updateMetaTag('name', 'keywords', metaTags.keywords || '')
    updateMetaTag('name', 'author', metaTags.author || siteTitle)
    updateMetaTag('name', 'robots', metaTags.robots || 'index, follow')

    // Open Graph / Facebook
    updateMetaTag('property', 'og:type', 'website')
    updateMetaTag('property', 'og:url', window.location.href)
    updateMetaTag('property', 'og:site_name', siteTitle)
    updateMetaTag('property', 'og:title', metaTags.og_title || `${siteTitle} - ${siteDesc}`)
    updateMetaTag('property', 'og:description', metaTags.og_description || siteDesc)
    updateMetaTag('property', 'og:image', ogImage)
    updateMetaTag('property', 'og:image:width', '1200')
    updateMetaTag('property', 'og:image:height', '630')
    updateMetaTag('property', 'og:locale', metaTags.locale || 'en_US')

    // Twitter
    updateMetaTag('property', 'twitter:card', 'summary_large_image')
    updateMetaTag('property', 'twitter:url', window.location.href)
    updateMetaTag('property', 'twitter:title', metaTags.twitter_title || metaTags.og_title || `${siteTitle} - ${siteDesc}`)
    updateMetaTag('property', 'twitter:description', metaTags.twitter_description || metaTags.og_description || siteDesc)
    updateMetaTag('property', 'twitter:image', ogImage)
    
    // Twitter username if available
    if (siteSettings.social_media?.twitter) {
      updateMetaTag('property', 'twitter:site', siteSettings.social_media.twitter)
      updateMetaTag('property', 'twitter:creator', siteSettings.social_media.twitter)
    }

    // Canonical URL
    updateLinkTag('canonical', window.location.href)

    // Schema.org structured data (if exists in settings)
    if (metaTags.schema_markup) {
      injectStructuredData(metaTags.schema_markup)
    } else {
      // Default Person schema
      injectDefaultSchema(siteSettings)
    }

    console.log('✅ Meta tags updated from CMS settings:', {
      title: siteTitle,
      description: siteDesc,
      ogImage
    })
  }

  /**
   * Update page-specific meta tags (for detail pages)
   */
  const updatePageMeta = (options = {}) => {
    const {
      title,
      description,
      image,
      url,
      type = 'website',
      keywords
    } = options

    if (title) {
      document.title = title
      updateMetaTag('name', 'title', title)
      updateMetaTag('property', 'og:title', title)
      updateMetaTag('property', 'twitter:title', title)
    }

    if (description) {
      updateMetaTag('name', 'description', description)
      updateMetaTag('property', 'og:description', description)
      updateMetaTag('property', 'twitter:description', description)
    }

    if (image) {
      const absoluteImage = image.startsWith('http') ? image : `${window.location.origin}${image}`
      updateMetaTag('property', 'og:image', absoluteImage)
      updateMetaTag('property', 'twitter:image', absoluteImage)
    }

    if (url) {
      const absoluteUrl = url.startsWith('http') ? url : `${window.location.origin}${url}`
      updateMetaTag('property', 'og:url', absoluteUrl)
      updateMetaTag('property', 'twitter:url', absoluteUrl)
      updateLinkTag('canonical', absoluteUrl)
    }

    updateMetaTag('property', 'og:type', type)

    if (keywords) {
      updateMetaTag('name', 'keywords', keywords)
    }
  }

  /**
   * Update or create a meta tag
   */
  const updateMetaTag = (attribute, key, value) => {
    if (!value) return

    let element = document.querySelector(`meta[${attribute}="${key}"]`)
    
    if (!element) {
      element = document.createElement('meta')
      element.setAttribute(attribute, key)
      document.head.appendChild(element)
    }
    
    element.setAttribute('content', value)
  }

  /**
   * Update or create a link tag
   */
  const updateLinkTag = (rel, href) => {
    if (!href) return

    let element = document.querySelector(`link[rel="${rel}"]`)
    
    if (!element) {
      element = document.createElement('link')
      element.setAttribute('rel', rel)
      document.head.appendChild(element)
    }
    
    element.setAttribute('href', href)
  }

  /**
   * Update favicon dynamically
   */
  const updateFavicon = (iconUrl) => {
    if (!iconUrl) return

    // Update main favicon
    let favicon = document.querySelector('link[rel="icon"]')
    if (!favicon) {
      favicon = document.createElement('link')
      favicon.setAttribute('rel', 'icon')
      document.head.appendChild(favicon)
    }
    favicon.setAttribute('href', iconUrl)

    // Update apple touch icon
    let appleTouchIcon = document.querySelector('link[rel="apple-touch-icon"]')
    if (!appleTouchIcon) {
      appleTouchIcon = document.createElement('link')
      appleTouchIcon.setAttribute('rel', 'apple-touch-icon')
      document.head.appendChild(appleTouchIcon)
    }
    appleTouchIcon.setAttribute('href', iconUrl)
  }

  /**
   * Inject structured data (JSON-LD)
   */
  const injectStructuredData = (schemaMarkup) => {
    // Remove existing schema
    const existing = document.querySelector('script[type="application/ld+json"]')
    if (existing) existing.remove()

    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.textContent = typeof schemaMarkup === 'string' ? schemaMarkup : JSON.stringify(schemaMarkup)
    document.head.appendChild(script)
  }

  /**
   * Default Person schema from settings
   */
  const injectDefaultSchema = (siteSettings) => {
    const schema = {
      "@context": "https://schema.org",
      "@type": "Person",
      "name": siteSettings.site_name || "Portfolio",
      "url": window.location.origin,
      "email": siteSettings.contact_email,
      "telephone": siteSettings.contact_phone,
      "address": {
        "@type": "PostalAddress",
        "addressLocality": siteSettings.location || "",
        "addressCountry": "ID"
      }
    }

    // Add social media
    if (siteSettings.social_media) {
      const socialUrls = []
      Object.entries(siteSettings.social_media).forEach(([platform, url]) => {
        if (url) socialUrls.push(url)
      })
      if (socialUrls.length > 0) {
        schema.sameAs = socialUrls
      }
    }

    injectStructuredData(schema)
  }

  return {
    setMetaFromSettings,
    updatePageMeta,
    updateMetaTag,
    updateLinkTag,
    updateFavicon
  }
}
