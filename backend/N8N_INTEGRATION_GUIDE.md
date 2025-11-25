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
  
  "meta_title": "SEO Title (max 60 chars)",
  "meta_description": "SEO description for search engines (max 160 chars)",
  "meta_keywords": "AI, manufacturing, automation",
  
  "og_title": "Social media title (max 60 chars)",
  "og_description": "Social media description (max 500 chars)",
  "og_image": "https://image-url.com/og-image.png",
  
  "canonical_url": "https://original-source.com/article",
  "ai_summary": "AI-generated summary of the article"
}
```

---

## 📋 Field Specifications

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
| **meta_title** | string | ❌ No | 60 | null | SEO title |
| **meta_description** | string | ❌ No | 160 | null | SEO description |
| **meta_keywords** | string | ❌ No | 255 | null | SEO keywords |
| **og_title** | string | ❌ No | 60 | null | Open Graph title |
| **og_description** | string | ❌ No | 500 | null | Open Graph description |
| **og_image** | string | ❌ No | 255 | null | Open Graph image URL |
| **canonical_url** | string | ❌ No | 255 | null | Canonical URL |
| **ai_summary** | string | ❌ No | - | null | AI-generated summary |

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
- Auto-creates English translation in `post_translations` table
- Uses same values as main post
- Ensures compatibility with admin panel

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
  "meta_title": "{{ $json.meta_title }}",
  "meta_description": "{{ $json.meta_description }}",
  "og_image": "{{ $json.og_image }}"
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
    "created_at": "2025-11-25T10:30:00.000000Z",
    "updated_at": "2025-11-25T10:30:00.000000Z"
  },
  "message": "Post created successfully",
  "meta": {
    "processed_at": "2025-11-25T10:30:05.123456Z",
    "word_count": 450
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
      "category_id": ["The selected category id is invalid."]
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

### 1. RSS Feed Trigger
→ Monitors blog RSS feed for new articles

### 2. AI Node (ChatGPT)
→ Generates SEO-optimized content
- Prompt: "Rewrite this article for SEO..."
- Output: `title`, `content`, `meta_description`

### 3. DALL-E Node
→ Generates featured image
- Output: `featured_image` URL

### 4. Code Node (JavaScript)
→ Prepare API payload
```javascript
return {
  title: $input.item.json.title,
  content: $input.item.json.content,
  category_id: 3,
  excerpt: $input.item.json.meta_description.substring(0, 150),
  featured_image: $input.item.json.featured_image,
  tags: ["AI", "Automation"],
  published: true,
  published_at: new Date().toISOString(),
  meta_title: $input.item.json.title.substring(0, 60),
  meta_description: $input.item.json.meta_description.substring(0, 160),
  og_image: $input.item.json.featured_image
};
```

### 5. HTTP Request Node
→ POST to `/automation/posts`

### 6. IF Node (Check Success)
→ Branch on success/failure

### 7. Slack/Email Notification
→ Notify on success or error

---

## 🚀 Rate Limits

**Automation API:**
- **60 requests per minute** per token
- Exceeding limit returns `429 Too Many Requests`

**Best Practice:**
- Add 1-2 second delay between requests
- Use bulk endpoints when available (`/automation/posts/bulk`)

---

## 🧪 Testing

### Using Postman/Insomnia

1. Create new POST request
2. URL: `http://localhost/Portfolio_v2/backend/public/api/automation/posts`
3. Headers:
   - `Authorization: Bearer {token}`
   - `Content-Type: application/json`
4. Body (raw JSON):
```json
{
  "title": "Test Post from Postman",
  "content": "<p>This is a test post with minimum required fields.</p>",
  "category_id": 1,
  "published": true
}
```
5. Send → Should return 201 with post data

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
    "published": true
  }'
```

---

## 🐛 Troubleshooting

### Issue: "The category id field is required"
**Solution:** Ensure `category_id` is an integer, not a string:
```json
"category_id": 1  ✅
"category_id": "1"  ❌
```

### Issue: "The content must be at least 10 characters"
**Solution:** Ensure content has actual text:
```json
"content": "<p>Real content here</p>"  ✅
"content": ""  ❌
"content": "<p></p>"  ❌
```

### Issue: "Unauthenticated"
**Solution:** 
1. Check token is valid (not expired)
2. Verify header format: `Authorization: Bearer {token}`
3. Regenerate token if needed

### Issue: Featured image not showing
**Solution:**
1. Use full URL: `https://...` not `//...`
2. Ensure image is publicly accessible
3. Check CORS if using external domain

### Issue: Post appears in database but not in admin panel
**Solution:** This is now fixed! The API auto-creates translations.
- Refresh admin panel
- Check `post_translations` table has matching record

---

## 📞 Support

**Issues or Questions?**
- Check logs: `backend/storage/logs/laravel.log`
- Enable debug mode: Set `VITE_ENABLE_DEBUG=true` in `.env`
- Contact: ali.sadikincom85@gmail.com

---

**Last Updated:** November 25, 2025
**API Version:** 2.0
**Maintainer:** Ali Sadikin
