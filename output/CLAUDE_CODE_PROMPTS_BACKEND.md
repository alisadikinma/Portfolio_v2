# Claude Code Prompts - Backend API Completion

**Target:** Complete missing API endpoints  
**Subagents:** `backend-developer`, `laravel-specialist`, `api-designer`  
**Priority:** 🔴 CRITICAL

---

## PROMPT 1: Projects API Implementation

### Subagent
**Primary:** `backend-developer`  
**Support:** `laravel-specialist`, `api-designer`

### Context
Portfolio V2 project needs complete Projects API with internationalization support. The database structure is ready with `projects` and `project_translations` tables.

### Requirements

1. **Create ProjectController** at `backend/app/Http/Controllers/Api/ProjectController.php`
2. **Implement Public Endpoints:**
   - `GET /api/projects` - List all projects with pagination, filters, i18n
   - `GET /api/projects/{slug}` - Get single project by slug with translations
3. **Implement Admin Endpoints:**
   - `POST /api/admin/projects` - Create new project
   - `PUT /api/admin/projects/{id}` - Update project
   - `DELETE /api/admin/projects/{id}` - Delete project
4. **Features:**
   - i18n support with `?lang=en` or `?lang=id` parameter
   - Pagination (default 12 per page)
   - Filtering by category
   - Search in title/description
   - Sort by latest/oldest/title
   - Return translations with `available_languages` field

### Reference Files
- `/output/API_ENDPOINTS.md` - API specification (Projects section)
- `/output/I18N_IMPLEMENTATION.md` - i18n patterns
- `backend/app/Models/Project.php` - Existing model
- `backend/database/migrations/*_create_projects_table.php`
- `backend/database/migrations/*_create_project_translations_table.php`

### Implementation Steps

1. **Create ProjectTranslation model** if not exists
   ```php
   php artisan make:model ProjectTranslation
   ```

2. **Update Project model** with translation relationships
   ```php
   public function translations() {
       return $this->hasMany(ProjectTranslation::class);
   }
   
   public function translate($language = 'en') {
       return $this->translation($language) ?? $this->translation('en');
   }
   ```

3. **Create API Resource** `ProjectResource.php`
   - Handle language parameter
   - Return translated fields
   - Include `available_languages` array

4. **Create ProjectController** with methods:
   - `index()` - Public listing
   - `show($slug)` - Public detail
   - `store()` - Admin create
   - `update($id)` - Admin update
   - `destroy($id)` - Admin delete

5. **Add routes** in `routes/api.php`
   ```php
   // Public
   Route::get('/projects', [ProjectController::class, 'index']);
   Route::get('/projects/{slug}', [ProjectController::class, 'show']);
   
   // Admin
   Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
       Route::post('/projects', [ProjectController::class, 'store']);
       Route::put('/projects/{id}', [ProjectController::class, 'update']);
       Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
   });
   ```

6. **Test endpoints** with Postman/curl

### Expected Output

**GET /api/projects Response:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "ecommerce-platform",
      "language": "en",
      "available_languages": ["en", "id"],
      "title": "E-commerce Platform",
      "description": "Full-stack e-commerce solution...",
      "thumbnail_url": "...",
      "category": "web-development",
      "tech_stack": ["Vue.js", "Node.js"],
      "featured": true,
      "published_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 47,
    "per_page": 12
  }
}
```

### Validation Rules

**Create/Update Project:**
- title: required, max:255
- slug: required, unique, max:255
- description: required
- category: required
- tech_stack: required, array
- thumbnail_url: nullable, url
- live_url: nullable, url
- github_url: nullable, url
- featured: boolean
- status: in:draft,published

### Testing Checklist
- [ ] GET /api/projects returns paginated list
- [ ] GET /api/projects?lang=id returns Indonesian translations
- [ ] GET /api/projects?category=web-development filters correctly
- [ ] GET /api/projects?search=ecommerce searches correctly
- [ ] GET /api/projects/{slug} returns single project
- [ ] GET /api/projects/{slug}?lang=id returns Indonesian version
- [ ] POST /api/admin/projects creates new project (auth required)
- [ ] PUT /api/admin/projects/{id} updates project
- [ ] DELETE /api/admin/projects/{id} deletes project
- [ ] API returns proper error codes (404, 422, 500)

---

## PROMPT 2: Blog/Posts API Implementation

### Subagent
**Primary:** `backend-developer`  
**Support:** `laravel-specialist`

### Context
Need complete Blog API with category support and internationalization.

### Requirements

1. **Create PostController** at `backend/app/Http/Controllers/Api/PostController.php`
2. **Create CategoryController** at `backend/app/Http/Controllers/Api/CategoryController.php`
3. **Public Endpoints:**
   - `GET /api/posts` - List posts with pagination, filters, i18n
   - `GET /api/posts/{slug}` - Get single post with translations
   - `GET /api/categories` - List all categories
4. **Admin Endpoints:**
   - Full CRUD for posts
   - Full CRUD for categories
5. **Features:**
   - i18n support (EN/ID)
   - Category filtering
   - Tag filtering
   - Search in title/content
   - Sort by latest/oldest
   - Read time calculation

### Reference Files
- `/output/API_ENDPOINTS.md` - Blog API specification
- `/output/I18N_IMPLEMENTATION.md` - i18n guide
- `backend/app/Models/Post.php`
- `backend/app/Models/Category.php`
- Database migrations for posts and post_translations

### Implementation Steps

1. **Create PostTranslation model**
2. **Update Post model** with relationships
3. **Create PostResource** with i18n support
4. **Create CategoryResource**
5. **Implement PostController**
6. **Implement CategoryController**
7. **Add routes**
8. **Test all endpoints**

### Expected Output

**GET /api/posts Response:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "getting-started-vue3",
      "language": "en",
      "available_languages": ["en", "id"],
      "title": "Getting Started with Vue 3",
      "excerpt": "Learn the basics...",
      "featured_image": "...",
      "category": "tutorials",
      "tags": ["vue", "javascript"],
      "read_time": 5,
      "published_at": "2024-02-01T12:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 8,
    "total_items": 76,
    "per_page": 10
  }
}
```

### Testing Checklist
- [ ] GET /api/posts returns paginated list
- [ ] GET /api/posts?lang=id returns Indonesian translations
- [ ] GET /api/posts?category=tutorials filters by category
- [ ] GET /api/posts?tag=vue filters by tag
- [ ] GET /api/posts?search=vue searches correctly
- [ ] GET /api/posts/{slug} returns full post with content
- [ ] GET /api/categories returns all categories
- [ ] Admin CRUD endpoints work with authentication
- [ ] Translations are saved correctly

---

## PROMPT 3: Gallery & Contact API

### Subagent
**Primary:** `backend-developer`

### Context
Need Gallery API with bulk upload and Contact form API with validation.

### Requirements

**Gallery API:**
1. `GET /api/gallery` - List images with filters
2. `POST /api/admin/gallery/upload` - Bulk image upload
3. `PUT /api/admin/gallery/{id}` - Update image metadata
4. `DELETE /api/admin/gallery/{id}` - Delete image
5. `POST /api/admin/gallery/bulk-delete` - Delete multiple images

**Contact API:**
1. `POST /api/contact` - Submit contact form
2. Email notification to admin
3. Save to database

### Implementation Steps

**GalleryController:**
1. Implement index() with pagination & filtering
2. Implement upload() with validation
   - Accept multiple files
   - Validate: jpeg, png, webp, max 5MB
   - Generate thumbnails
   - Return upload progress
3. Implement update() for metadata
4. Implement destroy() and bulkDelete()

**ContactController:**
1. Implement store() method
2. Validate input:
   - name: required, max:100
   - email: required, email
   - subject: required, max:200
   - message: required, max:2000
3. Save to database
4. Send email notification
5. Return success response

### Testing Checklist
- [ ] GET /api/gallery returns image list
- [ ] POST /api/admin/gallery/upload handles multiple files
- [ ] Thumbnails are generated correctly
- [ ] PUT /api/admin/gallery/{id} updates metadata
- [ ] DELETE operations work correctly
- [ ] POST /api/contact validates input
- [ ] Email notifications are sent
- [ ] Contact submissions are saved to database

---

## GENERAL GUIDELINES FOR ALL PROMPTS

### Code Quality
- Follow PSR-12 coding standards
- Use type hints and return types
- Add docblocks for all methods
- Handle exceptions properly
- Use Laravel best practices

### Security
- Validate all input
- Sanitize user data
- Use parameterized queries (Eloquent)
- Implement rate limiting for public endpoints
- Check authentication for admin routes

### Testing
- Test happy path
- Test validation errors
- Test authentication requirements
- Test pagination
- Test i18n functionality
- Test edge cases

### Documentation
- Comment complex logic
- Update API documentation if needed
- Provide example requests/responses

### Performance
- Use eager loading for relationships
- Add database indexes if needed
- Cache frequent queries
- Optimize N+1 query problems

---

## DELIVERY

For each prompt, deliver:
1. ✅ Working code files
2. ✅ Route definitions
3. ✅ Test results (screenshots or output)
4. ✅ Brief summary of changes made
5. ✅ Any issues or blockers encountered

---

*Generated for Claude Code - October 9, 2025*
