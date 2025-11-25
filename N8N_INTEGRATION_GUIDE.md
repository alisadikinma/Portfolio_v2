# n8n Blog API Integration Guide

> **Dokumen ini untuk tim n8n integration**  
> Last updated: November 2025

---

## 📋 Authentication

| Key | Value |
|-----|-------|
| **Base URL (Local)** | `http://localhost/Portfolio_v2/backend/public/api/automation` |
| **Base URL (Production)** | `https://alisadikinma.com/api/automation` |
| **Token** | `69\|FhHvYCm27LVmK38gVVpbH8rDNuWtgtEv2YugbK4rac0ccb98` |
| **Rate Limit** | 60 requests/minute |

### Headers (Wajib untuk semua request)

```
Authorization: Bearer 69|FhHvYCm27LVmK38gVVpbH8rDNuWtgtEv2YugbK4rac0ccb98
Content-Type: application/json
Accept: application/json
```

---

## 📚 Categories Reference

**Wajib gunakan `category_id` yang valid saat create post:**

| ID | Name | Slug |
|----|------|------|
| 1 | Web Development | web-development |
| 2 | Design | design |
| 3 | Technology | technology |
| 4 | Tutorial | tutorial |
| 5 | Career | career |
| 6 | Personal | personal |

---

## 📡 API Endpoints

### 1. Check Duplicate Post ⭐ **NEW**

**Endpoint untuk validasi duplicate sebelum create post.**

```
POST /posts/check-duplicate
```

**⚠️ IMPORTANT:** Endpoint ini **PUBLIC** (tidak perlu token), gunakan sebelum create post.

**Request Body:**
```json
{
  "title": "AI in Healthcare Industry",
  "slug": "ai-in-healthcare-industry",
  "similarity_threshold": 85
}
```

**Parameters:**

| Field | Type | Required | Default | Keterangan |
|-------|------|----------|---------|------------|
| `title` | string | ✅ Yes | - | Judul post yang akan dicek |
| `slug` | string | ❌ No | null | Slug untuk cek collision |
| `similarity_threshold` | integer | ❌ No | 85 | Range: 70-100 |

**Response - No Duplicate (200):**
```json
{
  "is_duplicate": false,
  "duplicate_type": null,
  "exact_match": null,
  "slug_match": null,
  "similar_posts": [
    {
      "id": 42,
      "title": "The Future of AI in Healthcare",
      "slug": "future-ai-healthcare",
      "similarity": 87.5,
      "published": true,
      "created_at": "2024-01-15T10:30:00.000Z"
    }
  ],
  "message": "No exact duplicates found."
}
```

**Response - Duplicate Found (200):**
```json
{
  "is_duplicate": true,
  "duplicate_type": "exact_title",
  "exact_match": {
    "id": 123,
    "title": "AI in Healthcare Industry",
    "slug": "ai-in-healthcare-industry",
    "published": true,
    "created_at": "2024-10-20T14:22:00.000Z"
  },
  "slug_match": null,
  "similar_posts": [],
  "message": "Duplicate post found. Please review before creating."
}
```

**Use Cases:**
- ✅ Prevent duplicate article creation
- ✅ Find similar existing articles
- ✅ Validate slug availability
- ✅ Automation workflow validation

---

### 2. Get Categories

```
GET /categories
```

**Response:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Web Development", "slug": "web-development" },
    { "id": 2, "name": "Design", "slug": "design" }
  ]
}
```

---

### 3. Create Post ⭐

```
POST /posts
```

**Request Body:**
```json
{
  "title": "Judul Artikel Anda",
  "content": "<p>Konten artikel dalam format HTML...</p>",
  "category_id": 3,
  "published": true
}
```

**Optional Fields:**

| Field | Type | Default | Keterangan |
|-------|------|---------|------------|
| `slug` | string | auto | Otomatis dari title jika kosong |
| `excerpt` | string | auto | Otomatis dari content (150 karakter) |
| `featured_image` | string | null | URL gambar atau base64 |
| `tags` | array | [] | Contoh: `["ai", "tutorial"]` |
| `is_premium` | boolean | false | Konten premium/berbayar |
| `published_at` | datetime | now | Format ISO 8601 |

**Response Success (201):**
```json
{
  "success": true,
  "data": {
    "id": 45,
    "title": "Judul Artikel Anda",
    "slug": "judul-artikel-anda",
    "excerpt": "Konten artikel dalam format HTML...",
    "content": "<p>Konten artikel dalam format HTML...</p>",
    "category_id": 3,
    "published": true,
    "published_at": "2025-11-25T10:30:00.000000Z",
    "reading_time": 3,
    "views": 0,
    "category": {
      "id": 3,
      "name": "Technology",
      "slug": "technology"
    }
  },
  "message": "Post created successfully"
}
```

---

### 4. Upload Images ⭐

Untuk upload gambar dari URL eksternal (DALL-E, Midjourney, dll).

```
POST /upload-images
```

**Request Body:**
```json
{
  "images": [
    { "url": "https://oaidalleapiprodscus.blob.core.windows.net/..." },
    { "url": "https://another-image-url.com/image.png" }
  ]
}
```

**Limits:** Max 20 images per request, max 10MB per image

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "uploaded": [
      {
        "index": 0,
        "url": "http://localhost/storage/images/content_1732521234_0_x7k9m2.png",
        "filename": "content_1732521234_0_x7k9m2.png",
        "size": 245678,
        "mime_type": "image/png"
      }
    ],
    "failed": []
  },
  "summary": {
    "total": 2,
    "uploaded": 2,
    "failed": 0
  }
}
```

**Cara pakai:** Ambil `url` dari response, masukkan ke `<img>` di content post.

---

### 5. Get Posts

```
GET /posts
```

**Query Parameters:**

| Param | Type | Contoh |
|-------|------|--------|
| `published` | boolean | `true` |
| `category_id` | integer | `3` |
| `is_premium` | boolean | `false` |
| `search` | string | `laravel` |
| `date_from` | date | `2025-01-01` |
| `date_to` | date | `2025-12-31` |
| `sort_by` | string | `created_at`, `title`, `views` |
| `sort_order` | string | `asc`, `desc` |
| `per_page` | integer | `10` (max 100) |
| `page` | integer | `1` |

**Contoh:** `GET /posts?published=true&category_id=3&per_page=5`

---

### 6. Get Single Post

```
GET /posts/{id}
```

---

### 7. Update Post

```
PUT /posts/{id}
```

**Request Body:** Same as Create Post (semua field optional)

---

### 8. Delete Post

```
DELETE /posts/{id}
```

---

### 9. Bulk Create Posts

```
POST /posts/bulk
```

**Request Body:**
```json
{
  "posts": [
    {
      "title": "Post Pertama",
      "content": "<p>Konten 1</p>",
      "category_id": 1,
      "published": true
    },
    {
      "title": "Post Kedua",
      "content": "<p>Konten 2</p>",
      "category_id": 2,
      "published": true
    }
  ]
}
```

**Limit:** Max 50 posts per request

---

## ⚠️ Error Handling

### 401 Unauthorized
```json
{ "message": "Unauthenticated." }
```
**Solusi:** Cek token di header `Authorization`

### 422 Validation Error
```json
{
  "message": "The category id field is required.",
  "errors": {
    "category_id": ["The category id field is required."]
  }
}
```
**Solusi:** Lengkapi required fields (`title`, `content`, `category_id`)

### 429 Too Many Requests
```json
{ "message": "Too Many Attempts." }
```
**Solusi:** Rate limit exceeded, tunggu 1 menit

### 404 Not Found
```json
{
  "success": false,
  "error": {
    "code": "POST_NOT_FOUND",
    "message": "The requested post does not exist."
  }
}
```

---

## 🔄 n8n Workflow Setup

### Recommended Workflow (with Duplicate Check) ⭐

```
┌─────────────┐     ┌──────────────┐     ┌───────────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────┐
│   Trigger   │────▶│ AI Generate  │────▶│ Check         │────▶│ Upload       │────▶│ Create Post │────▶│  Notify  │
│  (Schedule) │     │   Content    │     │ Duplicate     │     │ Images       │     │    (API)    │     │ (Slack)  │
└─────────────┘     └──────────────┘     └───────────────┘     └──────────────┘     └─────────────┘     └──────────┘
                                                 │
                                                 │ is_duplicate = true
                                                 ▼
                                          ┌──────────────┐
                                          │  Stop &      │
                                          │  Alert User  │
                                          └──────────────┘
```

### Node Configuration Details:

#### 1. Trigger Node
- Type: Schedule (cron) atau Webhook
- Example: Daily at 9 AM

#### 2. AI Generate Content Node
- Type: OpenAI/Claude
- Generate: title, content, slug
- Optional: Generate featured image with DALL-E

#### 3. Check Duplicate Node ⭐ **NEW**

**HTTP Request Node:**
- Method: `POST`
- URL: `http://localhost/Portfolio_v2/backend/public/api/posts/check-duplicate`
- Authentication: None (public endpoint)
- Headers:
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- Body (JSON):
  ```json
  {
    "title": "={{ $json.title }}",
    "slug": "={{ $json.slug }}",
    "similarity_threshold": 85
  }
  ```

#### 4. IF Node - Check Duplicate Result

**Condition:**
```javascript
{{ $json.is_duplicate }} === false
```

**True Branch:** Continue to Upload Images → Create Post  
**False Branch:** Stop workflow & send alert

**Alert Message Example:**
```
⚠️ Duplicate Article Detected!

Title: {{ $json.exact_match.title }}
Existing Post ID: {{ $json.exact_match.id }}
Published: {{ $json.exact_match.published }}
Created: {{ $json.exact_match.created_at }}

Action: Workflow stopped. Please review manually.
```

#### 5. Upload Images Node (if not duplicate)
- Method: `POST`
- URL: `/automation/upload-images`
- Body: Array of DALL-E URLs

#### 6. Create Post Node (if not duplicate)
- Method: `POST`
- URL: `/automation/posts`
- Body: Post data with uploaded image URLs

#### 7. Notify Node
- Type: Slack/Email/Discord
- Message: Success notification with post URL

---

### HTTP Request Node Configuration (Create Post)

**Method:** POST  
**URL:** `http://localhost/Portfolio_v2/backend/public/api/automation/posts`

**Authentication:**
- Type: Header Auth
- Name: `Authorization`
- Value: `Bearer 69|FhHvYCm27LVmK38gVVpbH8rDNuWtgtEv2YugbK4rac0ccb98`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
  "title": "={{ $json.title }}",
  "content": "={{ $json.content }}",
  "category_id": 3,
  "featured_image": "={{ $json.image_url }}",
  "published": true
}
```

---

## ✅ Testing Checklist

```
[ ] POST /posts/check-duplicate → Test dengan title existing (expect: is_duplicate = true)
[ ] POST /posts/check-duplicate → Test dengan title baru (expect: is_duplicate = false)
[ ] POST /posts/check-duplicate → Test dengan slug collision
[ ] POST /posts/check-duplicate → Test dengan similarity_threshold = 90
[ ] GET /categories → Dapat list kategori
[ ] POST /posts → Create post dengan minimal fields
[ ] POST /upload-images → Upload 1 test image
[ ] GET /posts → Verify post ter-create
[ ] GET /posts/{id} → Get detail post
[ ] PUT /posts/{id} → Update post
[ ] DELETE /posts/{id} → Delete post
[ ] Test error: missing category_id → 422 error
[ ] Test error: invalid token → 401 error
[ ] Test workflow: AI Generate → Check Duplicate → Upload → Create (full flow)
```

---

## 🧪 cURL Test Commands

**1. Check Duplicate (No Auth Required):**
```bash
curl -X POST "http://localhost/Portfolio_v2/backend/public/api/posts/check-duplicate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "AI in Healthcare Industry",
    "slug": "ai-in-healthcare-industry",
    "similarity_threshold": 85
  }'
```

**Expected Response (No Duplicate):**
```json
{
  "is_duplicate": false,
  "duplicate_type": null,
  "similar_posts": [...],
  "message": "No exact duplicates found."
}
```

**Expected Response (Duplicate Found):**
```json
{
  "is_duplicate": true,
  "duplicate_type": "exact_title",
  "exact_match": {...},
  "message": "Duplicate post found. Please review before creating."
}
```

---

**2. Get Categories:**
```bash
curl -X GET "http://localhost/Portfolio_v2/backend/public/api/automation/categories" \
  -H "Authorization: Bearer 69|FhHvYCm27LVmK38gVVpbH8rDNuWtgtEv2YugbK4rac0ccb98" \
  -H "Accept: application/json"
```

---

**3. Create Post:**
```bash
curl -X POST "http://localhost/Portfolio_v2/backend/public/api/automation/posts" \
  -H "Authorization: Bearer 69|FhHvYCm27LVmK38gVVpbH8rDNuWtgtEv2YugbK4rac0ccb98" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Test Post dari n8n",
    "content": "<p>Ini adalah konten test.</p>",
    "category_id": 3,
    "published": true
  }'
```

---

**4. Upload Image:**
```bash
curl -X POST "http://localhost/Portfolio_v2/backend/public/api/automation/upload-images" \
  -H "Authorization: Bearer 69|FhHvYCm27LVmK38gVVpbH8rDNuWtgtEv2YugbK4rac0ccb98" \
  -H "Content-Type: application/json" \
  -d '{
    "images": [
      {"url": "https://picsum.photos/800/600"}
    ]
  }'
```

---

## 📋 Best Practices

### 1. Always Check Duplicate First
```javascript
// n8n workflow logic
if (checkDuplicateResult.is_duplicate === true) {
  // Send alert to admin
  sendSlackAlert({
    title: "⚠️ Duplicate Article",
    post: checkDuplicateResult.exact_match.title,
    action: "Workflow stopped"
  });
  
  // Stop workflow
  return false;
}
```

### 2. Handle Similar Posts
```javascript
// If no exact duplicate but has similar posts (85%+)
if (checkDuplicateResult.is_duplicate === false && 
    checkDuplicateResult.similar_posts.length > 0) {
  
  // Send warning notification
  sendSlackWarning({
    title: "⚠️ Similar Article Found",
    similarity: checkDuplicateResult.similar_posts[0].similarity + "%",
    existing_post: checkDuplicateResult.similar_posts[0].title,
    action: "Creating new post anyway"
  });
}
```

### 3. Custom Similarity Threshold
```javascript
// For strict duplicate detection (90%+)
{
  "title": "...",
  "similarity_threshold": 90  // More strict
}

// For loose duplicate detection (75%+)
{
  "title": "...",
  "similarity_threshold": 75  // More permissive
}
```

---

## 🎯 Common Use Cases

### Use Case 1: RSS to Blog (with Duplicate Check)
```
RSS Feed Trigger
  ↓
Check Duplicate (title from RSS)
  ↓ (not duplicate)
AI Improve Content
  ↓
Upload Featured Image
  ↓
Create Post
  ↓
Publish to Social Media
```

### Use Case 2: Notion to Blog (with Duplicate Check)
```
Notion Trigger (new page)
  ↓
Check Duplicate (Notion title)
  ↓ (not duplicate)
Format Content (Notion → HTML)
  ↓
Upload Images (Notion images)
  ↓
Create Post
```

### Use Case 3: AI Auto-Publishing (with Duplicate Check)
```
Schedule Trigger (daily)
  ↓
AI Generate Article (topic from list)
  ↓
Check Duplicate (AI-generated title)
  ↓ (not duplicate)
DALL-E Generate Featured Image
  ↓
Upload Image
  ↓
Create Post (with AI content + image)
  ↓
Send Analytics Report
```

---

## 📞 Support

**Developer:** Ali Sadikin  
**Email:** ali.sadikincom85@gmail.com

**Troubleshooting:**
1. Cek response error message
2. Verify token masih aktif
3. Pastikan `category_id` valid (1-6)
4. Cek rate limit (60 req/min)
5. Pastikan content dalam format HTML
6. **NEW:** Test duplicate check sebelum create post untuk prevent duplicates

**Common Issues:**

| Issue | Cause | Solution |
|-------|-------|----------|
| Duplicate not detected | Partial title match | Use full title, not truncated |
| False positive duplicate | High similarity (85%+) | Lower `similarity_threshold` to 90+ |
| Slug collision | Slug already exists | Auto-generate slug by omitting from request |
| Rate limit on duplicate check | Too many checks | Batch checks or add delay |

---

## 🔐 Security Notes

- ✅ `/check-duplicate` is public (no auth needed)
- ✅ All other `/automation/*` endpoints require valid Bearer token
- ✅ Rate limit: 60 requests/minute per token
- ⚠️ Never expose token in public repositories
- ⚠️ Rotate token if compromised
- ✅ Monitor API logs for suspicious activity

---

**Last Updated:** November 25, 2025  
**Version:** 2.0 (Added Check Duplicate endpoint)
