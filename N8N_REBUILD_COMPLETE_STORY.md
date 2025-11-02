# 📖 COMPLETE STORY: AI Blog Automation System

**For:** n8n MCP - Workflow Rebuild  
**Date:** November 2, 2025  
**Project:** Portfolio_v2 - AI Blog Automation dengan Laravel

---

## 🎬 BACKSTORY - Dari Mana Workflow Ini?

### **Original Source:**
Workflow ini **BUKAN buatan sendiri**, tapi **inspired by YouTuber** yang membuat tutorial AI blog automation menggunakan:
- n8n workflow automation
- Google Sheets sebagai content planner/trigger
- WordPress sebagai blog platform
- AI (OpenAI GPT + DALL-E) untuk generate content + images

### **Why We Have These Files:**

**1. Blog_System.json**
- File workflow n8n **dari YouTuber** (original)
- Sudah tested dan working **untuk WordPress**
- Kita dapat ini sebagai **reference/template**
- **Problem:** Pakai WordPress, kita butuh Laravel

**2. Screenshot Google Sheet (image 1762076563356_image.png)**
- Contoh **Google Sheet structure** dari tutorial
- Kolom A: Keyword untuk blog topic
- Kolom C (Create): Checkbox untuk trigger "generate blog"
- Kolom D (Social Media): Checkbox untuk trigger "post to social"
- **Purpose:** User manage content pipeline via spreadsheet

**3. Google Apps Script (gsheet_app_script.gs)**
- Script yang **attach ke Google Sheet**
- Function: Detect changes (onEdit trigger)
- Send webhook payload ke n8n ketika checkbox di-check
- **Critical:** Ini yang connect Sheet → n8n

**4. Screenshot n8n Workflow (image 1762075967278_image.png)**
- Visual flow dari workflow YouTuber
- Menunjukkan node structure dan connections
- Kita pakai ini untuk **understand logic flow**

---

## 🎯 THE PROBLEM WE'RE SOLVING

**Original Workflow (YouTuber):**
```
Google Sheet → n8n Webhook → AI Generate → WordPress Post → Update Sheet
✅ Works perfectly... for WordPress
```

**Our Situation:**
```
Portfolio_v2 = Laravel Backend + Vue Frontend (NOT WordPress!)
❌ Can't use WordPress nodes
✅ Have Laravel REST API ready
🔄 Need: Replace WordPress nodes with Laravel API calls
```

---

## 📸 IMAGE TYPES - 2 DIFFERENT THINGS

### **Type 1: Featured Image (OG Image)**
- **Count:** Always 1 per blog
- **Purpose:** Social media thumbnail, blog card preview
- **Location:** Post metadata (featured_image field)
- **Example:** The hero image at top of blog post

### **Type 2: Content Images (In-body)**
- **Variable count** (0, 1, 2, 5, 10, etc.) - NOT hardcoded!
- **Purpose:** Illustrate blog content sections
- **Location:** INSIDE blog content HTML via placeholders
- **Placeholders:** `[IMAGE_1]`, `[IMAGE_2]`, `[IMAGE_3]`, ... `[IMAGE_N]`
- **Example:** `<img src="...">` tags replaced in content

**IMPORTANT - Dynamic Image Count:**
Each blog post can have DIFFERENT number of images:
- Blog A: 0 images (text only)
- Blog B: 2 images (`[IMAGE_1]`, `[IMAGE_2]`)
- Blog C: 5 images (`[IMAGE_1]` through `[IMAGE_5]`)
- Blog D: 1 image (`[IMAGE_1]`)
- No maximum limit (API accepts up to 20 per request)

---

## 🔄 ACTUAL WORKFLOW - WordPress vs Laravel

### **Original WordPress Workflow:**

```
1. AI Agent → Generate 1000+ words blog
   Output: {
     title,
     content: "<h2>Section 1</h2><p>Text...</p>[IMAGE_1]<h2>Section 2</h2>..."
   }

2. Generate N images (DALL-E) - N is variable (2, 4, 5, etc)
   Output: [url1, url2, url3, ...urlN]

3. Upload EACH image to WordPress (N iterations)
   FOR EACH url in [url1, url2, ...urlN]:
     POST /wp-json/wp/v2/media
     Response: { id: 123 }
   Result: [id_1, id_2, ...id_N]

4. Replace placeholders in content
   content = content.replace('[IMAGE_1]', '<img src="wp-url-1">')
   content = content.replace('[IMAGE_2]', '<img src="wp-url-2">')
   ...repeat for N images

5. Create post
   POST /wp-json/wp/v2/posts
   Body: {
     content: "<h2>...</h2><img src='wp-url-1'><p>...",
     featured_media: id_1  // Use first image as thumbnail
   }
```

### **New Laravel Workflow (2 Options):**

#### **Option A: Batch Upload (Recommended)**
```
1. AI Agent → Generate blog with N placeholders
2. DALL-E → Generate N images
3. Single batch upload:
   POST /automation/upload-images
   Body: {
     "images": [
       {"url": "dalle-url-1"},
       {"url": "dalle-url-2"},
       ...N items
     ]
   }
   Response: {
     "uploaded": [
       {"index": 0, "url": "storage-url-1"},
       {"index": 1, "url": "storage-url-2"},
       ...N items
     ]
   }
4. Replace placeholders (n8n loop)
5. Create post with processed content
```

#### **Option B: Sequential Upload (Fallback)**
```
1-2. Same as Option A
3. Loop N times:
   POST /automation/upload-image { "url": "dalle-url-X" }
   Response: { "url": "storage-url-X" }
4-5. Same as Option A
```

**Recommendation:** Use Option A (batch) for efficiency.

---

## 🔄 KEY DIFFERENCES FROM WORDPRESS

### **1. Image Count - Dynamic vs Hardcoded**
```
WordPress Tutorial: Assumes 4 images always
Our Implementation: Supports 0 to 20 images per blog
```

**Why Important:**
- Blog about "5 Tips" → needs 5 images
- Blog about "Top 3" → needs 3 images
- Tutorial blog → might need 10+ screenshots
- Opinion piece → might need 0 images

### **2. Upload Method - Batch vs Sequential**
```
WordPress: Upload images one by one (4 API calls)
Laravel Option A: Batch upload (1 API call) ✅ Recommended
Laravel Option B: Single upload (N API calls) ✅ Fallback
```

**Performance Comparison:**
- 5 images via WordPress: 5 API calls, ~10 seconds
- 5 images via Laravel batch: 1 API call, ~6 seconds
- 10 images via WordPress: 10 API calls, ~20 seconds
- 10 images via Laravel batch: 1 API call, ~12 seconds

### **3. Slug Handling - Auto-Increment**
```
WordPress: Auto-suffix on duplicate (post-1, post-2)
Laravel: Same behavior (blog-post-1, blog-post-2)
```

**Example:**
- First post: slug = "ai-automation"
- Second post with same title: slug = "ai-automation-1"
- Third post: slug = "ai-automation-2"

---

## 📋 GOOGLE SHEET STRUCTURE (From Screenshot)

### **Columns Layout:**

| Column | Header | Type | Purpose |
|--------|--------|------|---------|
| **A** | Keyword | Text | Blog topic/keyword (e.g., "Plumbing price in Toronto") |
| **B** | Description | Text | Optional notes/context |
| **C** | Create | Checkbox | ✅ = Trigger blog generation workflow |
| **D** | Social Media | Checkbox | ✅ = Trigger social media posting |
| **E** | Image Link | URL | Auto-filled by n8n (generated image URL) |
| **F** | Blog Link | URL | Auto-filled by n8n (published blog URL) |
| **G** | Social Media Link | URL | Auto-filled by n8n (social post URL) |

### **User Workflow:**
```
1. User enters keyword di column A (e.g., "AI trends 2025")
2. User check ✅ column C (Create)
3. Google Apps Script detects change
4. Script sends webhook to n8n with:
   - Keyword from column A
   - Row number
   - Which column was checked (C or D)
5. n8n processes:
   - IF column C checked → Generate blog + post
   - IF column D checked → Share existing blog to social
6. n8n updates Sheet:
   - Column E: Image URL
   - Column F: Blog URL  
   - Column G: Social URL
```

---

## 🌐 API ENDPOINTS

**Base URL:** `http://localhost/Portfolio_v2/backend/public/api/automation`
**Auth:** Bearer Token (Header: `Authorization: Bearer 6|UTo1Id5g1WtiUQH0fbV4vY2Dv4DMFin0pceBXAHCb4b7965d`)

---

### **NEW: Batch Image Upload (Recommended)**
```
POST /automation/upload-images
```

**Purpose:** Upload multiple content images in one request

**Request Body:**
```json
{
  "images": [
    { "url": "https://dalle-image-url-1" },
    { "url": "https://dalle-image-url-2" },
    { "url": "https://dalle-image-url-3" }
  ]
}
```

**Limits:**
- Min: 1 image
- Max: 20 images per request
- Max image size: 10MB per image
- Allowed types: JPG, PNG, GIF, WebP
- Timeout: 30 seconds per image

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "uploaded": [
      {
        "index": 0,
        "url": "http://localhost/Portfolio_v2/backend/storage/images/content_1730555555_0_abc123.jpg",
        "filename": "content_1730555555_0_abc123.jpg",
        "size": 245678,
        "mime_type": "image/jpeg"
      },
      {
        "index": 1,
        "url": "http://localhost/Portfolio_v2/backend/storage/images/content_1730555556_1_def456.png",
        "filename": "content_1730555556_1_def456.png",
        "size": 189234,
        "mime_type": "image/png"
      }
    ],
    "failed": []
  },
  "summary": {
    "total": 2,
    "uploaded": 2,
    "failed": 0
  },
  "message": "Batch upload completed: 2 uploaded, 0 failed"
}
```

**Partial Success Response (207 Multi-Status):**
```json
{
  "success": true,
  "data": {
    "uploaded": [
      {
        "index": 0,
        "url": "http://localhost/storage/images/content_123.jpg",
        "filename": "content_123.jpg"
      }
    ],
    "failed": [
      {
        "index": 1,
        "url": "https://invalid-url.com/image.jpg",
        "error": "Failed to download image from URL"
      },
      {
        "index": 2,
        "url": "https://example.com/huge-image.jpg",
        "error": "Image too large. Max 10MB"
      },
      {
        "index": 3,
        "url": "https://example.com/malware.exe",
        "error": "Invalid image type: application/x-executable. Allowed: jpg, png, gif, webp"
      }
    ]
  },
  "summary": {
    "total": 4,
    "uploaded": 1,
    "failed": 3
  },
  "message": "Batch upload completed: 1 uploaded, 3 failed"
}
```

**Error Response (422 Validation Error):**
```json
{
  "message": "The images field is required.",
  "errors": {
    "images": ["The images field is required."],
    "images.0.url": ["The images.0.url field is required."]
  }
}
```

---

### **NEW: Single Image Upload (Fallback)**
```
POST /automation/upload-image
```

**Purpose:** Upload single image (internally uses batch endpoint)

**Request Body:**
```json
{
  "url": "https://dalle-image-url"
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "url": "http://localhost/storage/images/content_123.jpg",
    "filename": "content_123.jpg",
    "size": 245678,
    "mime_type": "image/jpeg"
  },
  "message": "Image uploaded successfully"
}
```

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "error": {
    "code": "UPLOAD_FAILED",
    "message": "Failed to download image from URL"
  }
}
```

---

### **Existing: Create Blog Post**
```
POST /automation/posts
```

**Request Body:**
```json
{
  "title": "AI Automation Trends 2025",
  "slug": "ai-automation-trends-2025",
  "content": "<p>Content with <img src='storage-url-1'> embedded</p>",
  "excerpt": "Short summary...",
  "category_id": 1,
  "featured_image": "http://localhost/storage/images/content_123.jpg",
  "published": true,
  "meta_title": "AI Automation Trends 2025",
  "focus_keyword": "ai automation"
}
```

**Success Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "slug": "ai-automation-trends-2025",
    "title": "AI Automation Trends 2025",
    "category": { "id": 1, "name": "Technology" }
  },
  "message": "Post created successfully"
}
```

**Note:** Slug auto-increments if duplicate (ai-automation-1, ai-automation-2, etc.)

---

## 🔌 GOOGLE APPS SCRIPT INTEGRATION

### **File:** gsheet_app_script.gs

**Purpose:**
- Installed as **onEdit trigger** di Google Sheet
- Watches for checkbox changes in columns C & D
- Sends webhook payload ke n8n

### **Key Configuration:**

```javascript
// Line 9: Webhook URL (user configures this)
const WATCH_CHANGE_WEBHOOK_URL = 'https://n8n.example.com/webhook/blog-system';

// Optional filters
const SHEET = '';  // Limit to specific sheet name
const RANGE = '';  // Limit to specific cell range
```

### **Trigger Logic:**

**Function:** `watchChanges(e)`
```javascript
// Called when user edits sheet
// e = edit event object

1. Check if edit is in allowed sheet/range
2. Get edited cell info:
   - Row number
   - Column number (3 = C, 4 = D)
   - New value (TRUE/FALSE)
   - Old value
3. Get entire row data (columns A-G)
4. Build JSON payload
5. POST to n8n webhook
```

### **Webhook Payload Format:**

```json
{
  "spreadsheetId": "abc123...",
  "spreadsheetName": "Blog Sheet Demo",
  "sheetId": 0,
  "sheetName": "Sheet1",
  "rangeA1Notation": "C2",
  "range": {
    "rowStart": 2,
    "rowEnd": 2,
    "columnStart": 3,
    "columnEnd": 3
  },
  "oldValue": null,
  "value": "TRUE",
  "user": "user@example.com",
  "rowValues": {
    "0": "Plumbing price in Toronto",  // Column A (keyword)
    "1": "",                            // Column B (description)
    "2": "TRUE",                        // Column C (create checkbox)
    "3": "",                            // Column D (social checkbox)
    "4": "",                            // Column E (image link)
    "5": "",                            // Column F (blog link)
    "6": ""                             // Column G (social link)
  }
}
```

**Critical Fields for n8n:**
- `range.columnStart`: `3` = Create blog, `4` = Post to social
- `value`: `"TRUE"` = Checkbox checked
- `rowValues[0]`: Keyword untuk blog topic
- `range.rowStart`: Row number untuk update nanti

---

## 🔄 N8N WORKFLOW FLOW (From JSON)

### **Original WordPress Workflow:**

```
1. Webhook (Trigger)
   ↓
2. Filter (value = TRUE?)
   ↓
3. Switch (column 3 or 4?)
   ↓
   ├─ Column 3 (Create Blog):
   │  ↓
   │  4. AI Agent (Generate 1000+ words blog with N placeholders)
   │  ↓
   │  5. Message a model (Tone refinement)
   │  ↓
   │  6. Message a model1 (SEO optimization)
   │  ↓
   │  7. Message a model2 (Format as JSON)
   │  ↓
   │  8. Split Out (Parse JSON)
   │  ↓
   │  9. Generate N images (DALL-E) - N is variable per blog
   │  ↓
   │  10. Batch Upload Images (NEW) ✅ REPLACE WordPress upload
   │      POST /automation/upload-images
   │      Body: { "images": [{"url": "dalle-1"}, {"url": "dalle-2"}, ...] }
   │      Response: { "uploaded": [{"index": 0, "url": "..."}, ...] }
   │  ↓
   │  11. Replace Placeholders (n8n loop)
   │      content = content.replace('[IMAGE_1]', uploaded[0].url)
   │      content = content.replace('[IMAGE_2]', uploaded[1].url)
   │      ...repeat for N images
   │  ↓
   │  12. Create a post (Laravel API) ✅ REPLACE WordPress
   │      POST /automation/posts
   │      Body: {
   │        title,
   │        content: "<p>...<img src='storage-url-1'>...",
   │        featured_image: uploaded[0].url  // First image
   │      }
   │  ↓
   │  13. Update row in sheet (Add blog URL)
   │
   └─ Column 4 (Social Media):
      ↓
      16. HTTP Request1 (Get existing post from WordPress)
      ↓
      17. HTTP Request2 (Post to social - implementation varies)
```

### **What Needs to Change:**

**❌ REMOVE:**
- Node 10: WordPress media upload
- Node 13: WordPress post creation API

**✅ REPLACE:**
- Node 13 → Laravel API: `POST /automation/posts`

**🔄 MODIFY:**
- Node 15: Update Sheet URL to Laravel blog URL
- Authentication: WordPress Basic Auth → Laravel Bearer Token

**✅ KEEP (No changes):**
- Nodes 1-9: Webhook → Filter → AI generation → Image
- Nodes 11-12: Aggregation and formatting  
- Node 14: Social caption generation
- Node 15: Sheet update logic (just change URL format)

---

## 🎯 WHY WE NEED TO REBUILD

### **Original Setup:**
```
✅ n8n workflow = Working
✅ Google Sheet + Script = Working
✅ AI generation = Working
✅ Image generation = Working
❌ WordPress API = We don't use WordPress!
```

### **Our Setup:**
```
✅ Portfolio_v2 = Laravel + Vue
✅ Laravel API = Ready (/automation/posts)
✅ Bearer Token Auth = Generated (6|UTo1Id...)
🔄 Need: Modify n8n JSON to use Laravel instead
```

---

## 📦 WHAT N8N MCP NEEDS TO DO

### **Task 1: Understand Original Flow**
1. Read `Blog_System.json` untuk understand node structure
2. Identify which nodes interact dengan WordPress
3. Map data flow antar nodes

### **Task 2: Identify Replace Points**
```javascript
// Find these nodes in JSON:
{
  "name": "HTTP Request",   // WordPress media upload
  "type": "n8n-nodes-base.httpRequest",
  "url": "https://djing.ca/wp-json/wp/v2/media"
}

{
  "name": "Create a post",  // WordPress post creation  
  "type": "@n8n/n8n-nodes-langchain.wordpress",
  "url": "https://djing.ca/wp-json/wp/v2/posts"
}
```

### **Task 3: Replace with Laravel**
```javascript
// New node configuration:
{
  "name": "Create a post",
  "type": "n8n-nodes-base.httpRequest",
  "typeVersion": 4.1,
  "method": "POST",
  "url": "http://localhost/Portfolio_v2/backend/public/api/automation/posts",
  "authentication": {
    "type": "genericCredentialType",
    "genericAuthType": "httpHeaderAuth"
  },
  "sendHeaders": true,
  "headerParameters": {
    "parameters": [
      {
        "name": "Authorization",
        "value": "Bearer 6|UTo1Id5g1WtiUQH0fbV4vY2Dv4DMFin0pceBXAHCb4b7965d"
      }
    ]
  },
  "sendBody": true,
  "bodyContentType": "json",
  "bodyParameters": {
    "parameters": [
      {
        "name": "title",
        "value": "={{ $json.title }}"
      },
      {
        "name": "slug",
        "value": "={{ $json.slug }}"
      },
      {
        "name": "content",
        "value": "={{ $json.blog_post }}"
      },
      {
        "name": "excerpt",
        "value": "={{ $json.excerpt || '' }}"
      },
      {
        "name": "category_id",
        "value": 1
      },
      {
        "name": "featured_image",
        "value": "={{ $('Generate an image').item.json.data[0].url }}"
      },
      {
        "name": "published",
        "value": true
      },
      {
        "name": "meta_title",
        "value": "={{ $json.title }}"
      },
      {
        "name": "focus_keyword",
        "value": "={{ $('Webhook').item.json.body.rowValues[0] }}"
      }
    ]
  }
}
```

### **Task 4: Update Sheet URL**
```javascript
// Old (WordPress):
"https://djing.ca/{{ $json.slug }}"

// New (Laravel):
"http://localhost:5173/blog/{{ $json.data.slug }}"
```

### **Task 5: Remove Unnecessary Nodes**
```javascript
// Delete WordPress image upload node
// Laravel handles images inline, no separate upload needed
```

---

## 🗂️ FILES PROVIDED TO N8N MCP

**1. Blog_System.json** (Original workflow)
- Complete n8n workflow configuration
- Node definitions, connections, parameters
- **Use as:** Template/reference structure

**2. Screenshot 1 (n8n flow visual)**
- Visual representation of workflow
- Shows node connections and flow
- **Use as:** Quick reference for logic

**3. Screenshot 2 (Google Sheet structure)**
- Shows column layout and purpose
- Example data format
- **Use as:** Understand trigger source

**4. gsheet_app_script.gs**
- Complete Apps Script code
- Webhook trigger logic
- Payload structure
- **Use as:** Understand webhook input format

**5. This Documentation**
- Complete context and story
- API specifications
- Field mappings
- **Use as:** Implementation guide

---

## ✅ SUCCESS CRITERIA

**Workflow should:**
1. ✅ Receive webhook from Google Sheet (unchanged)
2. ✅ Filter TRUE checkbox (unchanged)
3. ✅ Switch on column 3 vs 4 (unchanged)
4. ✅ Generate blog with AI (unchanged)
5. ✅ Generate image with DALL-E (unchanged)
6. ✅ **POST to Laravel API** (CHANGED)
7. ✅ Update Google Sheet with **Laravel blog URL** (CHANGED)

**Test Case:**
```
1. Enter keyword: "AI automation trends 2025"
2. Check column C (Create)
3. Wait for workflow
4. Expected result:
   - Column E: DALL-E image URL
   - Column F: http://localhost:5173/blog/ai-automation-trends-2025
   - Blog visible at frontend
```

---

## 🚨 CRITICAL NOTES FOR N8N MCP

### **Authentication Difference:**
```javascript
// WordPress (OLD):
authentication: {
  type: "basicAuth",
  username: "user",
  password: "pass"
}

// Laravel (NEW):
authentication: {
  type: "httpHeaderAuth"
},
headers: {
  "Authorization": "Bearer 6|UTo1Id5g1WtiUQH0fbV4vY2Dv4DMFin0pceBXAHCb4b7965d"
}
```

### **Image Handling Difference:**
```javascript
// WordPress (OLD): 2 steps
1. Upload image → get media_id
2. Create post with featured_media: media_id

// Laravel (NEW): 1 step
Create post with featured_image: "base64..." or "url"
```

### **Category Difference:**
```javascript
// WordPress (OLD): Array
"categories": [1, 2, 3]

// Laravel (NEW): Single integer
"category_id": 1
```

### **Response Structure:**
```javascript
// WordPress response:
{
  "id": 123,
  "slug": "post-slug",
  "link": "https://site.com/post-slug"
}

// Laravel response:
{
  "success": true,
  "data": {
    "id": 123,
    "slug": "post-slug",
    "title": "...",
    "category": {...}
  },
  "message": "Post created successfully"
}

// Access slug: $json.data.slug (not $json.slug)
```

---

## 🎓 FINAL CONTEXT

**Why this workflow exists:**
- Automate blog content creation
- Scale content production with AI
- Manage pipeline via familiar tool (Google Sheets)
- One-click from idea → published blog

**Why we're modifying it:**
- We built custom Laravel blog system
- More control over features (SEO, caching, etc.)
- Integrated with Portfolio_v2 project
- Better performance than WordPress

**What stays the same:**
- Google Sheet interface (users love it)
- AI generation quality (GPT + DALL-E)
- Workflow trigger mechanism
- Content planning approach

**What changes:**
- Backend endpoint (WordPress → Laravel)
- Authentication method (Basic → Bearer)
- Request/response format
- Blog URL structure

---

## 📞 QUESTIONS N8N MCP MIGHT HAVE

**Q: Can I test the Laravel API before rebuilding?**
A: YES! Use these curl commands:
```bash
# Test auth + get posts
curl -H "Authorization: Bearer 6|UTo1Id..." \
  http://localhost/Portfolio_v2/backend/public/api/automation/posts

# Test create post
curl -X POST \
  -H "Authorization: Bearer 6|UTo1Id..." \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","content":"<p>test</p>","category_id":1,"published":true}' \
  http://localhost/Portfolio_v2/backend/public/api/automation/posts
```

**Q: What if AI generates content longer than MySQL can store?**
A: Laravel uses `longtext` type, supports 4GB content ✅

**Q: How to handle rate limiting (60 req/min)?**
A: n8n can add rate limiting node or delay between requests

**Q: What category_id should I use?**
A: For now, hardcode `1` (Technology). Later can make dynamic.

**Q: Should I keep the social media posting (column D)?**
A: Start with column C (blog creation) first. Social media posting is separate feature.

---

**Status:** ✅ Complete story documented  
**Ready for:** n8n MCP workflow rebuild  
**Expected Output:** Modified Blog_System.json with Laravel endpoints

---

**This documentation provides FULL CONTEXT for n8n MCP to rebuild workflow successfully!** 🚀
