/**
 * JSON-LD Schema generators for GEO optimization.
 */

export function personSchema(overrides = {}) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Person',
    name: 'Ali Sadikin',
    jobTitle: 'AI Generalist Expert',
    url: 'https://alisadikinma.com',
    sameAs: [],
    knowsAbout: [
      'Artificial Intelligence',
      'Prompt Engineering',
      'RAG (Retrieval-Augmented Generation)',
      'AI Agents',
      'No-Code Development',
      'Full-Stack Development',
      'Vue.js',
      'Laravel'
    ],
    award: [
      'Demo Day Champion - Outskill AI Generalist Fellowship',
      'Outskill AI Generalist Fellowship Graduate'
    ],
    ...overrides
  }
}

export function websiteSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'Ali Sadikin - AI Generalist Expert',
    url: 'https://alisadikinma.com',
    potentialAction: {
      '@type': 'SearchAction',
      target: 'https://alisadikinma.com/blog?search={search_term_string}',
      'query-input': 'required name=search_term_string'
    }
  }
}

export function articleSchema(post) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: post.title,
    description: post.excerpt || post.meta_description || '',
    image: post.featured_image || '',
    author: {
      '@type': 'Person',
      name: 'Ali Sadikin',
      url: 'https://alisadikinma.com'
    },
    publisher: {
      '@type': 'Person',
      name: 'Ali Sadikin'
    },
    datePublished: post.published_at || post.created_at,
    dateModified: post.updated_at || post.created_at,
    mainEntityOfPage: {
      '@type': 'WebPage',
      '@id': `https://alisadikinma.com/blog/${post.slug}`
    }
  }
}

export function projectSchema(project) {
  return {
    '@context': 'https://schema.org',
    '@type': 'CreativeWork',
    name: project.title,
    description: project.description || project.excerpt || '',
    image: project.thumbnail || '',
    author: {
      '@type': 'Person',
      name: 'Ali Sadikin'
    },
    url: `https://alisadikinma.com/projects/${project.slug}`
  }
}

/**
 * Injects a JSON-LD script tag into the document head.
 * Returns a cleanup function.
 */
export function setJsonLd(schema) {
  const existing = document.querySelector('script[data-jsonld]')
  if (existing) existing.remove()

  const script = document.createElement('script')
  script.type = 'application/ld+json'
  script.setAttribute('data-jsonld', 'true')
  script.textContent = JSON.stringify(schema)
  document.head.appendChild(script)

  return () => script.remove()
}

/**
 * Set multiple JSON-LD schemas.
 */
export function setMultipleJsonLd(schemas) {
  removeAllJsonLd()
  schemas.forEach((schema, i) => {
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.setAttribute('data-jsonld', `schema-${i}`)
    script.textContent = JSON.stringify(schema)
    document.head.appendChild(script)
  })
}

export function removeAllJsonLd() {
  document.querySelectorAll('script[data-jsonld]').forEach(el => el.remove())
}
