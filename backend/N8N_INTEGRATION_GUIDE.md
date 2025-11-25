# n8n Integration Guide - Portfolio API

Complete guide for integrating n8n workflows with Portfolio API.

---

## 🔑 Authentication

**Base URL:** `http://localhost/Portfolio_v2/backend/public/api` (local) or `https://alisadikinma.com/api` (production)

**Authentication:** Bearer Token (Laravel Sanctum)

```http
Authorization: Bearer {your_token_here}
```

**Generate Token:**
1. Login to admin panel: `/admin/automation/tokens`
2. Click "Generate New Token"
3. Copy token and save securely

---

## 📝 Create Blog Post

### Endpoint
```
POST /automation/posts
```

### Required Headers
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Request Body Structure

#### ✅ REQUIRED Fields

```json
{
  "title": "Your Post Title Here",
  "content": "<p>Your HTML content here...</p>",
  "category_id": 1
}
```

#### 🎨 OPTIONAL Fields (Recommended)

```json
{
  "title": "Scaling Innovation in Manufacturing with AI",
  "content": "<p>Manufacturing is getting a major system upgrade...</p>",
  "category_id": 3,
  
  "slug": "scaling-innovation-manufacturing-ai",
  "excerpt": "Brief summary of the post (max 500 chars)",
  
  "featured_image": "https://dalle-image-url.com/image.png",
  
  "tags": ["AI", "Manufacturing", "Innovation"],
  
  "published": true,
  "published_at": "2025-11-25T10:30:00Z",
  
  "is_premium": false,
  
  "translations": [
    {
      "language": "en",
      "title": "Scaling Innovation in Manufacturing with AI",
      "slug": "scaling-innovation-manufacturing-ai",
      "excerpt": "Brief summary in English",
      "content": "<p>English content...</p>",
      "meta_title": "SEO Title (max 60 chars)",
      "meta_description": "SEO description for search engines (max 160 chars)",
      "og_title": "Social media title (max 60 chars)",
      "og_description": "Social media description (max 500 chars)",
      "canonical_url": "https://original-source.com/article",
      "ai_summary": "AI-generated summary in English"
    },
    {
      "language": "id",
      "title": "Meningkatkan Inovasi di Manufaktur dengan AI",
      "slug": "meningkatkan-inovasi-manufaktur-ai",
      "excerpt": "Ringkasan dalam Bahasa Indonesia",
      "content": "<p>Konten Bahasa Indonesia...</p>",
      "meta_title": "Judul SEO (max 60 chars)",
      "meta_description": "Deskripsi SEO untuk mesin pencari (max 160 chars)",
      "og_title": "Judul media sosial (max 60 chars)",
      "og_description": "Deskripsi media sosial (max 500 chars)",
      "canonical_url": "https://sumber-asli.com/artikel",
      "ai_summary": "Ringkasan AI dalam Bahasa Indonesia"
    }
  ],
  
  "meta_keywords": "AI, manufacturing, automation, industry 4.0",
  "og_image": "https://image-url.com/og-image.png",
  "schema_markup": {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "Scaling Innovation in Manufacturing with AI",
    "author": {
      "@type": "Person",
      "name": "Ali Sadikin"
    },
    "datePublished": "2025-11-25T10:30:00Z"
  },
  "faq_schema": {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
      {
        "@type": "Question",
        "name": "What is AI in manufacturing?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "AI in manufacturing refers to..."
        }
      }
    ]
  },
  "seo_score": 85,
  "index_follow": true
}
```

---

## 📋 Field Specifications

### Main Post Fields

| Field | Type | Required | Max Length | Default | Description |
|-------|------|----------|------------|---------|-------------|
| **title** | string | ✅ Yes | 255 | - | Post title |
| **content** | string | ✅ Yes | - | - | HTML content (min 10 chars) |
| **category_id** | integer | ✅ Yes | - | - | Must exist in categories table |
| **slug** | string | ❌ No | 255 | auto-generated | URL-friendly slug |
| **excerpt** | string | ❌ No | 500 | auto-generated | Short summary |
| **featured_image** | string | ❌ No | - | null | Image URL or base64 |
| **tags** | array | ❌ No | - | [] | Array of strings |
| **published** | boolean | ❌ No | - | false | Publish status |
| **published_at** | datetime | ❌ No | - | auto-set | ISO 8601 format |
| **is_premium** | boolean | ❌ No | - | false | Premium content flag |

### Global SEO Fields (posts table)

| Field | Type | Required | Max Length | Default | Description |
|-------|------|----------|------------|---------|-------------|
| **meta_keywords** | string | ❌ No | 255 | null | SEO keywords (comma-separated) |
| **og_image** | string | ❌ No | 255 | null | Open Graph image URL |
| **schema_markup** | JSON | ❌ No | - | null | JSON-LD structured data |
| **faq_schema** | JSON | ❌ No | - | null | FAQ structured data |
| **seo_score** | integer | ❌ No | 0-100 | 0 | SEO quality score |
| **index_follow** | boolean | ❌ No | - | true | Search engine indexing |

### Per-Language SEO Fields (post_translations table)

| Field | Type | Required | Max Length | Default | Description |
|-------|------|----------|------------|---------|-------------|
| **language** | string | ✅ Yes | 5 | - | Language code (en, id) |
| **title** | string | ✅ Yes | 255 | - | Translated title |
| **slug** | string | ✅ Yes | 255 | - | Translated slug |
| **excerpt** | string | ❌ No | - | null | Translated excerpt |
| **content** | string | ✅ Yes | - | - | Translated content |
| **meta_title** | string | ❌ No | 255 | null | SEO title for language |
| **meta_description** | string | ❌ No | 500 | null | SEO description for language |
| **og_title** | string | ❌ No | 255 | null | Open Graph title for language |
| **og_description** | string | ❌ No | 500 | null | Open Graph description for language |
| **canonical_url** | string | ❌ No | 255 | null | Canonical URL for language |
| **ai_summary** | string | ❌ No | - | null | AI-generated summary for language |

---

## 🔄 Auto-Generated Fields

If not provided, these fields are auto-generated:

### 1. **slug**
- Generated from `title` using lowercase + hyphens
- Auto-increments if duplicate exists
- Example: `"Scaling Innovation"` → `"scaling-innovation"`

### 2. **excerpt**
- Auto-generated from first 150 chars of `content` (HTML stripped)
- Only if `excerpt` is empty

### 3. **published_at**
- Auto-set to current datetime if `published: true` and `published_at` is empty

### 4. **translations**
- If not provided, auto-creates **English (en)** translation
- Uses same values as main post fields
- Ensures compatibility with admin panel and multi-language support

### 5. **seo_score**
- Auto-calculated based on:
  - Title length (optimal: 50-60 chars)
  - Meta description length (optimal: 150-160 chars)
  - Keyword density
  - Content length (optimal: 1000+ words)
  - Header usage (H1, H2, H3)
  - Image alt tags

---

## 🌍 Multi-Language Support

### Simplified Approach (Auto-Translation)

If you only provide main fields, the API creates an English translation automatically:

```json
{
  "title": "Innovation in AI",
  "content": "<p>Content here...</p>",
  "category_id": 1
}
```

**Result:** Creates 1 translation (en) with same content.

---

### Full Multi-Language Control

Provide `translations` array for complete control:

```json
{
  "title": "Innovation in AI",
  "content": "<p>Main content...</p>",
  "category_id": 1,
  "translations": [
    {
      "language": "en",
      "title": "Innovation in AI",
      "slug": "innovation-ai",
      "content": "<p>English content...</p>",
      "meta_title": "AI Innovation Guide",
      "meta_description": "Complete guide to AI innovation",
      "ai_summary": "This article explores AI innovation..."
    },
    {
      "language": "id",
      "title": "Inovasi dalam AI",
      "slug": "inovasi-ai",
      "content": "<p>Konten Bahasa Indonesia...</p>",
      "meta_title": "Panduan Inovasi AI",
      "meta_description": "Panduan lengkap inovasi AI",
      "ai_summary": "Artikel ini membahas inovasi AI..."
    }
  ]
}
```

**Benefits:**
- ✅ Better SEO for each language
- ✅ Localized slugs and meta tags
- ✅ Native language content quality
- ✅ Regional search engine optimization

---

## 🖼️ Featured Image Handling

### Option 1: External URL (Recommended for n8n + DALL-E)
```json
{
  "featured_image": "https://oaidalleapiprodscus.blob.core.windows.net/private/..."
}
```

### Option 2: Base64 Data
```json
{
  "featured_image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
}
```

**Upload Process:**
- External URLs are validated and saved as-is
- Base64 images are decoded and saved to `/uploads/posts/`
- Supports: JPG, PNG, GIF, WEBP
- Max size: 5MB (recommended)

---

## 🎯 n8n Node Configuration

### HTTP Request Node Settings

**Method:** `POST`

**URL:** `{{$env.PORTFOLIO_API_URL}}/automation/posts`

**Authentication:** 
- Type: `Generic Credential Type`
- Credential Type: `Header Auth`
- Name: `Authorization`
- Value: `Bearer {{$env.PORTFOLIO_API_TOKEN}}`

**Body Content Type:** `JSON`

**JSON/RAW Parameters:**

```json
{
  "title": "{{ $json.title }}",
  "content": "{{ $json.content }}",
  "category_id": {{ $json.category_id }},
  "excerpt": "{{ $json.excerpt }}",
  "featured_image": "{{ $json.featured_image }}",
  "tags": {{ $json.tags }},
  "published": true,
  "published_at": "{{ $now.toISO() }}",
  
  "meta_keywords": "{{ $json.keywords }}",
  "og_image": "{{ $json.og_image }}",
  "seo_score": {{ $json.seo_score || 0 }},
  "index_follow": true,
  
  "translations": [
    {
      "language": "en",
      "title": "{{ $json.title }}",
      "slug": "{{ $json.slug }}",
      "content": "{{ $json.content }}",
      "excerpt": "{{ $json.excerpt }}",
      "meta_title": "{{ $json.meta_title }}",
      "meta_description": "{{ $json.meta_description }}",
      "og_title": "{{ $json.og_title }}",
      "og_description": "{{ $json.og_description }}",
      "canonical_url": "{{ $json.canonical_url }}",
      "ai_summary": "{{ $json.ai_summary }}"
    }
  ]
}
```

---

## 📊 Categories Reference

Get available categories:

```http
GET /automation/categories
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Technology",
      "slug": "technology"
    },
    {
      "id": 2,
      "name": "Business",
      "slug": "business"
    },
    {
      "id": 3,
      "name": "AI & Machine Learning",
      "slug": "ai-machine-learning"
    }
  ]
}
```

---

## ✅ Success Response

**Status:** `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 123,
    "title": "Scaling Innovation in Manufacturing with AI",
    "slug": "scaling-innovation-manufacturing-ai",
    "content": "<p>Manufacturing is getting...</p>",
    "excerpt": "Brief summary...",
    "featured_image": "http://localhost/uploads/posts/1732512345_image.png",
    "category_id": 3,
    "category": {
      "id": 3,
      "name": "AI & Machine Learning",
      "slug": "ai-machine-learning"
    },
    "tags": ["AI", "Manufacturing"],
    "published": true,
    "published_at": "2025-11-25T10:30:00.000000Z",
    "views": 0,
    "reading_time": 8,
    "seo": {
      "meta_title": "Scaling Innovation in Manufacturing with AI",
      "meta_description": "Learn how AI is transforming...",
      "meta_keywords": "AI, manufacturing, automation",
      "og_title": "Scaling Innovation in Manufacturing",
      "og_description": "Discover AI innovation strategies...",
      "og_image": "http://localhost/uploads/posts/og-image.png",
      "canonical_url": "https://alisadikinma.com/blog/scaling-innovation-manufacturing-ai",
      "ai_summary": "This article explores how artificial intelligence...",
      "schema_markup": {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "Scaling Innovation in Manufacturing with AI"
      },
      "faq_schema": null,
      "seo_score": 85,
      "index_follow": true
    },
    "translations": [
      {
        "id": 456,
        "language": "en",
        "title": "Scaling Innovation in Manufacturing with AI",
        "slug": "scaling-innovation-manufacturing-ai",
        "excerpt": "Brief summary...",
        "content": "<p>Manufacturing is getting...</p>",
        "meta_title": "Scaling Innovation in Manufacturing with AI",
        "meta_description": "Learn how AI is transforming...",
        "og_title": "Scaling Innovation in Manufacturing",
        "og_description": "Discover AI innovation strategies...",
        "canonical_url": "https://alisadikinma.com/blog/scaling-innovation-manufacturing-ai",
        "ai_summary": "This article explores..."
      }
    ],
    "created_at": "2025-11-25T10:30:00.000000Z",
    "updated_at": "2025-11-25T10:30:00.000000Z"
  },
  "message": "Post created successfully",
  "meta": {
    "processed_at": "2025-11-25T10:30:05.123456Z",
    "word_count": 450,
    "seo_analysis": {
      "title_score": 90,
      "content_score": 85,
      "meta_score": 80,
      "overall_score": 85
    }
  }
}
```

---

## ❌ Error Responses

### 422 Validation Error
```json
{
  "success": false,
  "error": {
    "code": "POST_CREATION_FAILED",
    "message": "Failed to create post",
    "details": {
      "title": ["The title field is required."],
      "content": ["The content must be at least 10 characters."],
      "category_id": ["The selected category id is invalid."],
      "translations.0.language": ["The language must be either en or id."],
      "meta_keywords": ["The meta keywords must not exceed 255 characters."],
      "seo_score": ["The seo score must be between 0 and 100."]
    }
  }
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 429 Rate Limit
```json
{
  "message": "Too many requests. Please try again later.",
  "retry_after": 60
}
```

---

## 🔍 Check for Duplicates (Optional)

Before creating a post, check for duplicates:

```http
POST /posts/check-duplicate
Content-Type: application/json
```

**Request:**
```json
{
  "title": "Scaling Innovation in Manufacturing with AI",
  "slug": "scaling-innovation-manufacturing-ai",
  "similarity_threshold": 85
}
```

**Response:**
```json
{
  "is_duplicate": false,
  "duplicate_type": null,
  "exact_match": null,
  "slug_match": null,
  "similar_posts": [],
  "message": "No exact duplicates found."
}
```

---

## 🎨 Example: Complete n8n Workflow

### Workflow Structure

```
1. RSS Feed Trigger
   ↓
2. AI Node (ChatGPT) - Content Generation
   ↓
3. AI Node (ChatGPT) - SEO Optimization
   ↓
4. DALL-E Node - Featured Image
   ↓
5. Code Node - Prepare Payload
   ↓
6. HTTP Request - Create Post
   ↓
7. IF Node - Check Success
   ↓
8. Slack/Email Notification
```

---

### 1. RSS Feed Trigger
**Node:** RSS Read
- URL: `https://source-blog.com/feed`
- Poll interval: Every 30 minutes

---

### 2. AI Node - Content Generation
**Node:** OpenAI
**Model:** GPT-4
**Prompt:**
```
Rewrite the following article for my portfolio blog.
Make it professional, engaging, and SEO-optimized.

Original Title: {{ $json.title }}
Original Content: {{ $json.content }}

Requirements:
- Keep technical accuracy
- Add personal insights
- Use clear headings (H2, H3)
- Target 1000-1500 words
- Include actionable takeaways

Format as HTML with proper tags.
```

---

### 3. AI Node - SEO Optimization
**Node:** OpenAI
**Model:** GPT-4
**Prompt:**
```
Create comprehensive SEO metadata for this blog post:

Title: {{ $json.title }}
Content: {{ $json.content }}

Generate:
1. meta_title (50-60 chars, include primary keyword)
2. meta_description (150-160 chars, compelling CTA)
3. meta_keywords (5-10 keywords, comma-separated)
4. og_title (optimized for social sharing)
5. og_description (compelling social media description)
6. ai_summary (2-3 sentence summary)
7. FAQ section with 3-5 common questions

Return as JSON object.
```

---

### 4. DALL-E Node
**Node:** OpenAI DALL-E
**Prompt:**
```
Create a professional featured image for a blog post about:
{{ $json.title }}

Style: Modern, tech-focused, clean design, 16:9 aspect ratio
Colors: Blue and white professional palette
```

---

### 5. Code Node - Prepare Payload
**Node:** Code (JavaScript)

```javascript
// Extract data from previous nodes
const content = $input.item.json.content;
const seoData = JSON.parse($input.item.json.seo_metadata);
const imageUrl = $input.item.json.dalle_image_url;

// Calculate SEO score (simplified)
function calculateSeoScore(data) {
  let score = 0;
  
  // Title length check (50-60 chars = optimal)
  if (data.meta_title.length >= 50 && data.meta_title.length <= 60) {
    score += 25;
  } else if (data.meta_title.length >= 40) {
    score += 15;
  }
  
  // Meta description check (150-160 chars = optimal)
  if (data.meta_description.length >= 150 && data.meta_description.length <= 160) {
    score += 25;
  } else if (data.meta_description.length >= 120) {
    score += 15;
  }
  
  // Content length check
  const wordCount = content.split(/\s+/).length;
  if (wordCount >= 1000) {
    score += 25;
  } else if (wordCount >= 500) {
    score += 15;
  }
  
  // Has keywords
  if (data.meta_keywords && data.meta_keywords.length > 0) {
    score += 15;
  }
  
  // Has FAQ
  if (data.faq_schema) {
    score += 10;
  }
  
  return Math.min(score, 100);
}

// Generate slug from title
function generateSlug(text) {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}

const slug = generateSlug(seoData.meta_title);
const seoScore = calculateSeoScore(seoData);

// Prepare FAQ schema (Schema.org format)
const faqSchema = seoData.faq ? {
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": seoData.faq.map(q => ({
    "@type": "Question",
    "name": q.question,
    "acceptedAnswer": {
      "@type": "Answer",
      "text": q.answer
    }
  }))
} : null;

// Prepare Article schema
const schemaMarkup = {
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": seoData.meta_title,
  "description": seoData.meta_description,
  "image": imageUrl,
  "author": {
    "@type": "Person",
    "name": "Ali Sadikin",
    "url": "https://alisadikinma.com"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Ali Sadikin Portfolio",
    "logo": {
      "@type": "ImageObject",
      "url": "https://alisadikinma.com/logo.png"
    }
  },
  "datePublished": new Date().toISOString(),
  "dateModified": new Date().toISOString()
};

// Return payload for API
return {
  title: seoData.meta_title,
  content: content,
  category_id: 3, // AI & Machine Learning
  slug: slug,
  excerpt: seoData.meta_description.substring(0, 150),
  featured_image: imageUrl,
  tags: seoData.meta_keywords.split(',').map(k => k.trim()),
  published: true,
  published_at: new Date().toISOString(),
  is_premium: false,
  
  // Global SEO fields
  meta_keywords: seoData.meta_keywords,
  og_image: imageUrl,
  schema_markup: schemaMarkup,
  faq_schema: faqSchema,
  seo_score: seoScore,
  index_follow: true,
  
  // Translations (English only for now)
  translations: [
    {
      language: 'en',
      title: seoData.meta_title,
      slug: slug,
      excerpt: seoData.meta_description.substring(0, 150),
      content: content,
      meta_title: seoData.meta_title,
      meta_description: seoData.meta_description,
      og_title: seoData.og_title,
      og_description: seoData.og_description,
      canonical_url: `https://alisadikinma.com/blog/${slug}`,
      ai_summary: seoData.ai_summary
    }
  ]
};
```

---

### 6. HTTP Request Node
**Settings:** (As shown in earlier section)

---

### 7. IF Node - Check Success
**Condition:** `{{ $json.success }} equals true`

**True Branch:** Send success notification
**False Branch:** Send error notification with details

---

### 8. Notification Nodes

**Success Notification (Slack):**
```
✅ New blog post published!

Title: {{ $json.data.title }}
URL: https://alisadikinma.com/blog/{{ $json.data.slug }}
SEO Score: {{ $json.data.seo.seo_score }}/100
Views: 0 (new)

Published at: {{ $json.data.published_at }}
```

**Error Notification (Slack):**
```
❌ Failed to publish blog post

Title: {{ $json.title }}
Error: {{ $json.error.message }}

Details:
{{ JSON.stringify($json.error.details, null, 2) }}
```

---

## 🚀 Rate Limits

**Automation API:**
- **60 requests per minute** per token
- Exceeding limit returns `429 Too Many Requests`

**Best Practice:**
- Add 1-2 second delay between requests
- Use bulk endpoints when available (`/automation/posts/bulk`)
- Cache category data to reduce API calls

---

## 🧪 Testing

### Using Postman/Insomnia

**Complete Test Request:**

```json
POST http://localhost/Portfolio_v2/backend/public/api/automation/posts
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json

{
  "title": "Complete Test with All SEO Fields",
  "content": "<p>This is a comprehensive test post with all SEO fields populated for testing purposes.</p>",
  "category_id": 1,
  "published": true,
  
  "meta_keywords": "test, seo, portfolio, api",
  "og_image": "https://via.placeholder.com/1200x630",
  "schema_markup": {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "Complete Test Article"
  },
  "faq_schema": {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
      {
        "@type": "Question",
        "name": "What is this test?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "This is a comprehensive API test."
        }
      }
    ]
  },
  "seo_score": 85,
  "index_follow": true,
  
  "translations": [
    {
      "language": "en",
      "title": "Complete Test with All SEO Fields",
      "slug": "complete-test-seo-fields",
      "content": "<p>This is a comprehensive test post.</p>",
      "meta_title": "Complete SEO Test",
      "meta_description": "Testing all SEO fields in the API",
      "og_title": "Complete SEO Test Article",
      "og_description": "Comprehensive testing of all SEO capabilities",
      "canonical_url": "https://alisadikinma.com/test",
      "ai_summary": "A comprehensive test of all SEO features"
    }
  ]
}
```

### Using curl

```bash
curl -X POST "http://localhost/Portfolio_v2/backend/public/api/automation/posts" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Test Post from curl",
    "content": "<p>Minimum required content for testing.</p>",
    "category_id": 1,
    "published": true,
    "meta_keywords": "curl, test, api",
    "seo_score": 75,
    "translations": [
      {
        "language": "en",
        "title": "Test Post from curl",
        "slug": "test-post-curl",
        "content": "<p>Minimum required content.</p>",
        "meta_title": "Test Post",
        "meta_description": "Testing via curl command"
      }
    ]
  }'
```

---

## 🐛 Troubleshooting

### Issue: "The translations.0.language field is required"
**Solution:** Ensure translations array includes language field:
```json
"translations": [
  {
    "language": "en",  // ✅ Required
    "title": "...",
    "content": "..."
  }
]
```

### Issue: "The seo score must be between 0 and 100"
**Solution:** Validate SEO score range:
```json
"seo_score": 85  // ✅ Valid (0-100)
"seo_score": 150  // ❌ Invalid
```

### Issue: "schema_markup must be valid JSON"
**Solution:** Ensure proper JSON object format:
```json
"schema_markup": {  // ✅ Valid object
  "@context": "https://schema.org",
  "@type": "Article"
}

"schema_markup": "invalid"  // ❌ Invalid string
```

### Issue: Featured image not showing
**Solution:**
1. Use full URL: `https://...` not `//...`
2. Ensure image is publicly accessible
3. Check CORS if using external domain
4. Verify `og_image` field for social sharing

### Issue: Post appears in database but not in admin panel
**Solution:** This is now fixed! The API auto-creates translations.
- Refresh admin panel
- Check `post_translations` table has matching record
- Verify at least one translation exists

### Issue: SEO score is 0
**Solution:** SEO score is auto-calculated. Improve by:
- Adding meta_title (50-60 chars optimal)
- Adding meta_description (150-160 chars optimal)
- Writing longer content (1000+ words)
- Adding keywords
- Including FAQ schema

---

## 📚 Best Practices

### 1. **Always Provide Translations**
Even if English-only, explicitly define translations for better control:
```json
"translations": [
  {
    "language": "en",
    "title": "...",
    "slug": "...",
    "content": "..."
  }
]
```

### 2. **Optimize SEO Fields**
- **meta_title:** 50-60 characters, include primary keyword
- **meta_description:** 150-160 characters, include CTA
- **meta_keywords:** 5-10 relevant keywords
- **og_image:** 1200x630px for best social media display

### 3. **Use Structured Data**
Always include `schema_markup` for better search visibility:
```json
"schema_markup": {
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Your Title",
  "author": {
    "@type": "Person",
    "name": "Ali Sadikin"
  }
}
```

### 4. **Implement FAQ Schema**
Helps appear in Google's "People also ask" section:
```json
"faq_schema": {
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [...]
}
```

### 5. **Monitor SEO Scores**
Track `seo_score` in response to improve content quality:
- 0-50: Poor (needs improvement)
- 51-75: Fair (optimize meta tags)
- 76-90: Good (minor tweaks)
- 91-100: Excellent (maintain quality)

---

## 📞 Support

**Issues or Questions?**
- Check logs: `backend/storage/logs/laravel.log`
- Enable debug mode: Set `APP_DEBUG=true` in `.env`
- API documentation: `/api/documentation`
- Contact: ali.sadikincom85@gmail.com

---

## 📝 Changelog

### Version 2.1 (November 25, 2025)
- ✅ Added 6 new global SEO fields
- ✅ Enhanced multi-language support
- ✅ Auto SEO score calculation
- ✅ Structured data (Schema.org) support
- ✅ FAQ schema for rich snippets
- ✅ Improved validation rules

### Version 2.0 (October 25, 2025)
- ✅ Multi-language translation system
- ✅ Enhanced SEO metadata
- ✅ Duplicate detection
- ✅ Rate limiting

---

**Last Updated:** November 25, 2025  
**API Version:** 2.1  
**Maintainer:** Ali Sadikin  
**License:** Proprietary
