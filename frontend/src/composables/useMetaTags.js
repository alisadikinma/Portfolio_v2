/**
 * Composable for managing dynamic meta tags and SEO
 * All meta tags loaded from database settings (no hardcoded values)
 */
import { buildPersonSchema, buildFaqSchema } from '@/utils/personSchema'

export function useMetaTags() {
  // Track if observer is already set up
  let metaObserver = null
  
  /**
   * Setup MutationObserver to protect meta tags from unwanted changes
   */
  const setupMetaProtection = () => {
    if (metaObserver) return // Already set up
    
    metaObserver = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'content') {
          const target = mutation.target
          const key = target.getAttribute('name') || target.getAttribute('property')
          
          // Check if title was changed to description value
          if (key === 'title') {
            const titleValue = target.getAttribute('content')
            const descTag = document.querySelector('meta[name="description"]')
            
            if (descTag && titleValue === descTag.getAttribute('content')) {
              console.error('❌ [MetaProtection] DETECTED: Title was overwritten with description!', {
                bad_title: titleValue,
                description: descTag.getAttribute('content')
              })
              // We can't auto-fix here as we don't know the correct title
              // User needs to check their code
            }
          }
        }
      })
    })
    
    // Observe all meta tags in <head>
    const metaTags = document.head.querySelectorAll('meta')
    metaTags.forEach(tag => {
      metaObserver.observe(tag, { attributes: true, attributeFilter: ['content'] })
    })
    
    console.log('🔒 [MetaProtection] Observer active')
  }
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
        const parsed = typeof siteSettings.meta_tags === 'string' 
          ? JSON.parse(siteSettings.meta_tags) 
          : siteSettings.meta_tags
        
        // CRITICAL: Handle both array and object formats (Nov 4, 2025)
        if (Array.isArray(parsed)) {
          // Database format: [{name: "title", content: "..."}, ...]
          // Convert to object: {title: "...", description: "..."}
          parsed.forEach(item => {
            if (item.name && item.content) {
              metaTags[item.name] = item.content
            }
          })
          console.log('🔄 [setMetaFromSettings] Converted array to object:', metaTags)
        } else {
          // Already object format: {title: "...", description: "..."}
          metaTags = parsed
        }
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
    
    // Meta title from database (priority: meta_tags.title > default pattern)
    const metaTitle = metaTags.title || `${siteTitle} - ${siteDesc}`

    // Update page title
    document.title = metaTitle

    // Update favicon dynamically
    if (logoUrl) {
      updateFavicon(logoUrl)
    }

    // Primary Meta Tags
    updateMetaTag('name', 'title', metaTitle)
    updateMetaTag('name', 'description', metaTags.description || siteDesc)
    updateMetaTag('name', 'keywords', metaTags.keywords || '')
    updateMetaTag('name', 'author', metaTags.author || siteTitle)
    updateMetaTag('name', 'robots', metaTags.robots || 'index, follow')

    // Open Graph / Facebook
    updateMetaTag('property', 'og:type', 'website')
    updateMetaTag('property', 'og:url', window.location.href)
    updateMetaTag('property', 'og:site_name', siteTitle)
    updateMetaTag('property', 'og:title', metaTags.og_title || metaTitle)
    updateMetaTag('property', 'og:description', metaTags.og_description || metaTags.description || siteDesc)
    updateMetaTag('property', 'og:image', ogImage)
    updateMetaTag('property', 'og:image:width', '1200')
    updateMetaTag('property', 'og:image:height', '630')
    updateMetaTag('property', 'og:locale', metaTags.locale || 'en_US')

    // Twitter (CRITICAL: Use 'name' not 'property' for Twitter cards)
    updateMetaTag('name', 'twitter:card', 'summary_large_image')
    updateMetaTag('name', 'twitter:url', window.location.href)
    updateMetaTag('name', 'twitter:title', metaTags.twitter_title || metaTags.og_title || metaTitle)
    updateMetaTag('name', 'twitter:description', metaTags.twitter_description || metaTags.og_description || metaTags.description || siteDesc)
    updateMetaTag('name', 'twitter:image', ogImage)
    
    // Twitter username if available
    if (siteSettings.social_media?.twitter) {
      updateMetaTag('name', 'twitter:site', siteSettings.social_media.twitter)
      updateMetaTag('name', 'twitter:creator', siteSettings.social_media.twitter)
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
    
    // Setup protection observer after meta tags are set
    setTimeout(() => setupMetaProtection(), 500)
  }

  /**
   * Reset page-specific meta tags to site defaults
   * Call this when navigating away from detail pages
   */
  const resetPageMeta = () => {
    // Remove page-specific meta tags that might override site defaults
    const pageSpecificTags = [
      // Open Graph
      { attr: 'property', key: 'og:type' },
      
      // No need to reset these as they'll be overwritten by setMetaFromSettings:
      // - og:title, og:description, og:image
      // - twitter:title, twitter:description, twitter:image
      // - canonical URL
    ]

    pageSpecificTags.forEach(({ attr, key }) => {
      const element = document.querySelector(`meta[${attr}="${key}"]`)
      if (element && element.getAttribute('content') === 'article') {
        element.setAttribute('content', 'website')
      }
    })

    console.log('🧹 [useMetaTags] Page-specific meta tags reset')
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
      updateMetaTag('name', 'twitter:title', title)
    }

    if (description) {
      updateMetaTag('name', 'description', description)
      updateMetaTag('property', 'og:description', description)
      updateMetaTag('name', 'twitter:description', description)
    }

    if (image) {
      const absoluteImage = image.startsWith('http') ? image : `${window.location.origin}${image}`
      updateMetaTag('property', 'og:image', absoluteImage)
      updateMetaTag('name', 'twitter:image', absoluteImage)
    }

    if (url) {
      const absoluteUrl = url.startsWith('http') ? url : `${window.location.origin}${url}`
      updateMetaTag('property', 'og:url', absoluteUrl)
      updateMetaTag('name', 'twitter:url', absoluteUrl)
      updateLinkTag('canonical', absoluteUrl)
    }

    updateMetaTag('property', 'og:type', type)

    if (keywords) {
      updateMetaTag('name', 'keywords', keywords)
    }
  }

  /**
   * Update or create a meta tag
   * CRITICAL: Remove old tags with wrong attribute type first
   */
  const updateMetaTag = (attribute, key, value) => {
    if (!value) return

    // DEFENSIVE: Prevent title from being set to description value
    if (key === 'title' && value.length > 200) {
      console.warn(`⚠️ Meta title too long (${value.length} chars), truncating...`)
      value = value.substring(0, 200)
    }

    // DEFENSIVE: Ensure title and description are different
    if (key === 'title') {
      const descTag = document.querySelector('meta[name="description"]')
      if (descTag && descTag.getAttribute('content') === value) {
        console.error('❌ ERROR: Title cannot be same as description!', {
          title: value,
          description: descTag.getAttribute('content')
        })
        return // Abort if trying to set title = description
      }
    }

    // Remove old tag with OPPOSITE attribute (e.g., property when we want name)
    const oppositeAttr = attribute === 'name' ? 'property' : 'name'
    const oldElement = document.querySelector(`meta[${oppositeAttr}="${key}"]`)
    if (oldElement) {
      oldElement.remove()
      // Only log for Twitter tags to avoid spam
      if (key.startsWith('twitter:')) {
        console.log(`🗑️ Removed old meta tag: ${oppositeAttr}="${key}"`)
      }
    }

    // Get or create the correct element
    let element = document.querySelector(`meta[${attribute}="${key}"]`)
    
    if (!element) {
      element = document.createElement('meta')
      element.setAttribute(attribute, key)
      document.head.appendChild(element)
    }
    
    element.setAttribute('content', value)
    
    // Verify after setting (defensive check)
    if (key === 'title') {
      console.log('✅ [updateMetaTag] Title set:', value.substring(0, 100))
    }
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

  /**
   * Inject BreadcrumbList JSON-LD schema
   * @param {Array} items - [{name, url}, ...] breadcrumb items
   */
  const injectBreadcrumbSchema = (items) => {
    if (!items || items.length === 0) return

    const schema = {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": items.map((item, index) => ({
        "@type": "ListItem",
        "position": index + 1,
        "name": item.name,
        "item": item.url
      }))
    }

    // Append as additional schema (don't remove existing)
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.dataset.schema = 'breadcrumb'
    // Remove old breadcrumb schema first
    const old = document.querySelector('script[data-schema="breadcrumb"]')
    if (old) old.remove()
    script.textContent = JSON.stringify(schema)
    document.head.appendChild(script)
  }

  /**
   * Inject Article/BlogPosting JSON-LD schema
   */
  /**
   * Inject hreflang alternate link tags for multilingual pages
   */
  const updateHreflang = (currentLang, slug, availableLanguages = []) => {
    // Remove existing hreflang links
    document.querySelectorAll('link[hreflang]').forEach(el => el.remove())

    if (!slug || availableLanguages.length < 2) return

    const base = 'https://alisadikinma.com'
    availableLanguages.forEach(lang => {
      const link = document.createElement('link')
      link.rel = 'alternate'
      link.hreflang = lang
      link.href = `${base}/${lang}/blog/${slug}`
      document.head.appendChild(link)
    })

    // x-default always points to English
    const xdefault = document.createElement('link')
    xdefault.rel = 'alternate'
    xdefault.hreflang = 'x-default'
    xdefault.href = `${base}/en/blog/${slug}`
    document.head.appendChild(xdefault)
  }

  const injectArticleSchema = (post, lang = 'en') => {
    if (!post) return

    const schema = {
      "@context": "https://schema.org",
      "@type": "BlogPosting",
      "headline": post.title,
      "description": post.excerpt || post.meta_description || '',
      "image": post.featured_image || '',
      "inLanguage": lang,
      "datePublished": post.published_at,
      "dateModified": post.updated_at || post.published_at,
      "author": {
        "@type": "Person",
        "name": post.author?.name || "Ali Sadikin Ma",
        "url": window.location.origin + '/about'
      },
      "publisher": {
        "@type": "Person",
        "name": "Ali Sadikin Ma",
        "url": window.location.origin
      },
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": window.location.href
      }
    }

    if (post.tags?.length) {
      schema.keywords = post.tags.join(', ')
    }
    if (post.category?.name) {
      schema.articleSection = post.category.name
    }

    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.dataset.schema = 'article'
    const old = document.querySelector('script[data-schema="article"]')
    if (old) old.remove()
    script.textContent = JSON.stringify(schema)
    document.head.appendChild(script)
  }

  /**
   * Inject CreativeWork JSON-LD schema for projects
   */
  const injectProjectSchema = (project) => {
    if (!project) return

    const schema = {
      "@context": "https://schema.org",
      "@type": "CreativeWork",
      "name": project.title,
      "description": project.description || project.meta_description || '',
      "image": project.featured_image || '',
      "author": {
        "@type": "Person",
        "name": "Ali Sadikin Ma",
        "url": window.location.origin + '/about'
      },
      "url": window.location.href
    }

    if (project.technologies?.length) {
      schema.keywords = project.technologies.join(', ')
    }
    if (project.url) {
      schema.url = project.url
    }

    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.dataset.schema = 'project'
    const old = document.querySelector('script[data-schema="project"]')
    if (old) old.remove()
    script.textContent = JSON.stringify(schema)
    document.head.appendChild(script)
  }

  /**
   * Inject the homepage Person entity JSON-LD (GEO lever G2). Scoped via
   * data-schema="person" so it coexists with article/breadcrumb/FAQ schemas.
   * @param {object} [opts] forwarded to buildPersonSchema ({ image, sameAs })
   */
  const injectPersonSchema = (opts = {}) => {
    const schema = buildPersonSchema(opts)
    const old = document.querySelector('script[data-schema="person"]')
    if (old) old.remove()
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.dataset.schema = 'person'
    script.textContent = JSON.stringify(schema)
    document.head.appendChild(script)
  }

  /**
   * Inject the homepage FAQPage JSON-LD (GEO lever G3). Scoped via
   * data-schema="faq".
   */
  const injectFaqSchema = () => {
    const schema = buildFaqSchema()
    const old = document.querySelector('script[data-schema="faq"]')
    if (old) old.remove()
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.dataset.schema = 'faq'
    script.textContent = JSON.stringify(schema)
    document.head.appendChild(script)
  }

  return {
    setMetaFromSettings,
    updatePageMeta,
    resetPageMeta,
    updateMetaTag,
    updateLinkTag,
    updateFavicon,
    injectBreadcrumbSchema,
    injectArticleSchema,
    injectProjectSchema,
    injectStructuredData,
    injectPersonSchema,
    injectFaqSchema,
    updateHreflang
  }
}
