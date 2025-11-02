# TASK: Implement Dynamic Blog Image Upload + Update API Documentation

## 🎯 OBJECTIVES

1. Implement batch image upload endpoint (variable count, tidak hardcoded)
2. Add slug uniqueness check dengan auto-increment
3. Update API documentation untuk reflect changes

## 📍 CONTEXT

**Project:** Portfolio_v2 Laravel Backend
**Location:** `C:\xampp\htdocs\Portfolio_v2\backend`

**Important Context:**
- ❌ DO NOT hardcode image count (bukan selalu 4 images)
- ✅ Support any number of images (0, 1, 2, 5, 10, etc per blog)
- ✅ Each blog post dapat memiliki different image count
- ✅ Use batch upload untuk efficiency

---

## 📋 PART 1: IMPLEMENTATION

### **Task 1.1: Create Batch Upload Endpoint**

**File:** `app/Http/Controllers/Api/AutomationController.php`

**Add this method:**

```php
/**
 * Upload multiple images from URLs (for blog content images)
 * Supports variable number of images (0-20 per request)
 * 
 * @param Request $request
 * {
 *   "images": [
 *     { "url": "https://dalle-url-1" },
 *     { "url": "https://dalle-url-2" },
 *     ...variable count
 *   ]
 * }
 * 
 * @return JsonResponse
 * {
 *   "success": true,
 *   "data": {
 *     "uploaded": [
 *       { "index": 0, "url": "storage-url-1", "filename": "..." },
 *       { "index": 1, "url": "storage-url-2", "filename": "..." }
 *     ],
 *     "failed": [
 *       { "index": 2, "error": "..." }
 *     ]
 *   },
 *   "summary": { "total": 3, "uploaded": 2, "failed": 1 }
 * }
 */
public function uploadImages(Request $request): JsonResponse
{
    $request->validate([
        'images' => 'required|array|min:1|max:20', // Max 20 images per request
        'images.*.url' => 'required|url'
    ]);

    $images = $request->input('images');
    $uploaded = [];
    $failed = [];

    foreach ($images as $index => $imageData) {
        try {
            $imageUrl = $imageData['url'];
            
            // Download dengan timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0'
                ]
            ]);
            
            $imageContent = @file_get_contents($imageUrl, false, $context);
            
            if ($imageContent === false) {
                $failed[] = [
                    'index' => $index,
                    'url' => $imageUrl,
                    'error' => 'Failed to download image from URL'
                ];
                continue;
            }

            // Validate mime type (security)
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedTypes)) {
                $failed[] = [
                    'index' => $index,
                    'url' => $imageUrl,
                    'error' => "Invalid image type: {$mimeType}. Allowed: jpg, png, gif, webp"
                ];
                continue;
            }

            // Generate filename dengan extension yang benar
            $extension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'png'
            };
            
            // Include index in filename untuk maintain order
            $filename = 'content_' . time() . '_' . $index . '_' . Str::random(6) . '.' . $extension;
            $path = 'images/' . $filename;
            
            // Save to storage/app/public/images/
            Storage::disk('public')->put($path, $imageContent);
            
            $fullUrl = url('storage/' . $path);
            
            $uploaded[] = [
                'index' => $index,
                'url' => $fullUrl,
                'filename' => $filename,
                'size' => strlen($imageContent),
                'mime_type' => $mimeType
            ];

        } catch (\Exception $e) {
            $failed[] = [
                'index' => $index,
                'url' => $imageData['url'] ?? 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    // Log batch upload untuk audit
    $this->logAutomationRequest($request, 'images.batch_upload', [
        'total_requested' => count($images),
        'uploaded' => count($uploaded),
        'failed' => count($failed)
    ]);

    // 207 Multi-Status jika ada yang failed
    $statusCode = count($failed) > 0 ? 207 : 200;

    return response()->json([
        'success' => count($uploaded) > 0, // Success if at least 1 uploaded
        'data' => [
            'uploaded' => $uploaded,
            'failed' => $failed
        ],
        'summary' => [
            'total' => count($images),
            'uploaded' => count($uploaded),
            'failed' => count($failed)
        ],
        'message' => sprintf(
            'Batch upload completed: %d uploaded, %d failed',
            count($uploaded),
            count($failed)
        )
    ], $statusCode);
}
```

### **Task 1.2: Create Single Upload Endpoint (Fallback)**

**Add this method right after uploadImages():**

```php
/**
 * Upload single image from URL (fallback method)
 * Internally calls uploadImages() for consistency
 * 
 * @param Request $request { "url": "https://image-url" }
 * @return JsonResponse
 */
public function uploadImage(Request $request): JsonResponse
{
    $request->validate([
        'url' => 'required|url'
    ]);
    
    // Convert single URL to batch format
    $request->merge([
        'images' => [
            ['url' => $request->input('url')]
        ]
    ]);
    
    // Call batch upload
    $batchResult = $this->uploadImages($request);
    $data = $batchResult->getData(true);
    
    // Extract single result
    if (!empty($data['data']['uploaded'])) {
        return response()->json([
            'success' => true,
            'data' => $data['data']['uploaded'][0],
            'message' => 'Image uploaded successfully'
        ]);
    }
    
    $error = $data['data']['failed'][0] ?? ['error' => 'Unknown error'];
    return response()->json([
        'success' => false,
        'error' => [
            'code' => 'UPLOAD_FAILED',
            'message' => $error['error']
        ]
    ], 400);
}
```

### **Task 1.3: Add Slug Uniqueness Check**

**In createPost() method, find this code (around line 89-91):**

```php
// Auto-generate slug if not provided
if (empty($postData['slug'])) {
    $postData['slug'] = Str::slug($postData['title']);
}
```

**Replace with:**

```php
// Auto-generate slug if not provided
if (empty($postData['slug'])) {
    $postData['slug'] = Str::slug($postData['title']);
}

// Ensure slug is unique (auto-increment suffix if duplicate)
$originalSlug = $postData['slug'];
$counter = 1;
while (Post::where('slug', $postData['slug'])->exists()) {
    $postData['slug'] = $originalSlug . '-' . $counter;
    $counter++;
}
```

### **Task 1.4: Add Routes**

**File:** `routes/api.php`

**Find the automation routes block (around line 272):**

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('automation')->group(function () {
```

**Add these routes at the TOP of the group:**

```php
// Image uploads for blog content
Route::post('/upload-images', [AutomationController::class, 'uploadImages']); // Batch (recommended)
Route::post('/upload-image', [AutomationController::class, 'uploadImage']);   // Single (fallback)
```

**Final routes block should look like:**

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('automation')->group(function () {
    // Image uploads for blog content
    Route::post('/upload-images', [AutomationController::class, 'uploadImages']); // Batch
    Route::post('/upload-image', [AutomationController::class, 'uploadImage']);   // Single
    
    // Posts endpoints
    Route::get('/posts', [AutomationController::class, 'getPosts']);
    Route::get('/posts/{id}', [AutomationController::class, 'getPost']);
    Route::post('/posts', [AutomationController::class, 'createPost']);
    // ... rest of existing routes
});
```

---

## 📋 PART 2: UPDATE DOCUMENTATION

### **Task 2.1: Update N8N_REBUILD_COMPLETE_STORY.md**

**File:** `C:\xampp\htdocs\Portfolio_v2\N8N_REBUILD_COMPLETE_STORY.md`

#### **Change 2.1.1: Update IMAGE TYPES Section**

**Find:** `## 📸 IMAGE TYPES - 2 DIFFERENT THINGS`

**Replace the "Type 2: Content Images" section with:**

```markdown
### **Type 2: Content Images (In-body)**
```
- **Variable count** (0, 1, 2, 5, 10, etc.) - NOT hardcoded!
- Purpose: Illustrate blog content sections
- Location: INSIDE blog content HTML via placeholders
- Placeholders: [IMAGE_1], [IMAGE_2], [IMAGE_3], ... [IMAGE_N]
- Example: <img src="..."> tags replaced in content
```

**IMPORTANT - Dynamic Image Count:**
Each blog post can have DIFFERENT number of images:
- Blog A: 0 images (text only)
- Blog B: 2 images ([IMAGE_1], [IMAGE_2])
- Blog C: 5 images ([IMAGE_1] through [IMAGE_5])
- Blog D: 1 image ([IMAGE_1])
- No maximum limit (API accepts up to 20 per request)
```
```

#### **Change 2.1.2: Update ACTUAL WORKFLOW Section**

**Find:** `## 🔄 ACTUAL WORKFLOW (WordPress)`

**Replace entire section with:**

```markdown
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

**Recommendation:** Use Option A (batch) untuk efficiency.
```

#### **Change 2.1.3: Add New API ENDPOINTS Section**

**Find:** `## 🌐 API ENDPOINTS` 

**Add AFTER the "Base URL" and BEFORE "Create Blog Post", insert:**

```markdown
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
- Image size: Auto-handled by server
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
        "url": "https://example.com/malware.exe",
        "error": "Invalid image type: application/x-executable. Allowed: jpg, png, gif, webp"
      }
    ]
  },
  "summary": {
    "total": 3,
    "uploaded": 1,
    "failed": 2
  },
  "message": "Batch upload completed: 1 uploaded, 2 failed"
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
```

#### **Change 2.1.4: Update N8N WORKFLOW FLOW Section**

**Find:** `## 🔄 N8N WORKFLOW FLOW (From JSON)`

**Update the "Column 3 (Create Blog)" flow to:**

```markdown
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
```

#### **Change 2.1.5: Update WHAT NEEDS TO CHANGE Section**

**Find:** `## 🎯 THE PROBLEM WE'RE SOLVING`

**Add a new section AFTER it:**

```markdown
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
```

---

## ✅ VERIFICATION TESTS

After completing all tasks, run these tests:

### **Test 1: Batch Upload 3 Images**
```bash
curl -X POST \
  -H "Authorization: Bearer 6|UTo1Id5g1WtiUQH0fbV4vY2Dv4DMFin0pceBXAHCb4b7965d" \
  -H "Content-Type: application/json" \
  -d '{
    "images": [
      {"url": "https://picsum.photos/800/600?random=1"},
      {"url": "https://picsum.photos/800/600?random=2"},
      {"url": "https://picsum.photos/800/600?random=3"}
    ]
  }' \
  http://localhost/Portfolio_v2/backend/public/api/automation/upload-images
```

**Expected:** 
- Status 200
- 3 uploaded images with URLs
- 0 failed

### **Test 2: Single Image Upload**
```bash
curl -X POST \
  -H "Authorization: Bearer 6|UTo1Id5g1WtiUQH0fbV4vY2Dv4DMFin0pceBXAHCb4b7965d" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://picsum.photos/800/600"}' \
  http://localhost/Portfolio_v2/backend/public/api/automation/upload-image
```

**Expected:**
- Status 200
- 1 uploaded image with URL

### **Test 3: Slug Uniqueness**
```bash
# Create first post
curl -X POST \
  -H "Authorization: Bearer 6|UTo1Id..." \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Blog Post",
    "content": "<p>Content</p>",
    "category_id": 1,
    "published": true
  }' \
  http://localhost/Portfolio_v2/backend/public/api/automation/posts

# Create second post with same title
curl -X POST \
  -H "Authorization: Bearer 6|UTo1Id..." \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Blog Post",
    "content": "<p>Content 2</p>",
    "category_id": 1,
    "published": true
  }' \
  http://localhost/Portfolio_v2/backend/public/api/automation/posts
```

**Expected:**
- First post: slug = "test-blog-post"
- Second post: slug = "test-blog-post-1"

### **Test 4: Partial Upload (Some Fail)**
```bash
curl -X POST \
  -H "Authorization: Bearer 6|UTo1Id..." \
  -H "Content-Type: application/json" \
  -d '{
    "images": [
      {"url": "https://picsum.photos/800/600"},
      {"url": "https://invalid-url-that-will-fail.com/image.jpg"},
      {"url": "https://picsum.photos/800/600?random=2"}
    ]
  }' \
  http://localhost/Portfolio_v2/backend/public/api/automation/upload-images
```

**Expected:**
- Status 207 (Multi-Status)
- 2 uploaded
- 1 failed with error message

---

## 📊 SUCCESS CRITERIA

**Implementation:**
- ✅ Batch upload endpoint accepts 1-20 images dynamically
- ✅ Single upload endpoint works as fallback
- ✅ Returns both successful and failed uploads (partial success OK)
- ✅ Slug auto-increments on duplicate
- ✅ Image mime type validation (security)
- ✅ Proper error handling with try-catch
- ✅ Logging for audit trail

**Documentation:**
- ✅ N8N_REBUILD_COMPLETE_STORY.md updated dengan mekanisme baru
- ✅ Dynamic image count explained clearly
- ✅ Batch upload documented dengan examples
- ✅ Single upload documented sebagai fallback
- ✅ Performance comparison included
- ✅ Slug uniqueness behavior documented

---

## 🚨 CRITICAL NOTES

1. **DO NOT hardcode image count** - must support 0 to 20 images
2. **Use foreach loop** - not fixed array indices
3. **Partial success is OK** - some uploads can fail, others succeed
4. **Log everything** - for debugging n8n workflow
5. **Update documentation** - reflect actual implementation
6. **Keep WordPress comparison** - help n8n MCP understand migration

---

## 📝 EXECUTION ORDER

Execute in this exact order:

1. ✅ Implement `uploadImages()` method (batch)
2. ✅ Implement `uploadImage()` method (single)
3. ✅ Add slug uniqueness check to `createPost()`
4. ✅ Add routes to `routes/api.php`
5. ✅ Test all 4 verification tests
6. ✅ Update `N8N_REBUILD_COMPLETE_STORY.md` documentation
7. ✅ Verify documentation accuracy

After completion, provide summary:
- ✅ Methods implemented
- ✅ Routes added
- ✅ Tests passed
- ✅ Documentation updated

Execute these tasks now.