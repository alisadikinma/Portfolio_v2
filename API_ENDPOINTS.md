# API Endpoints Documentation

**Base URL:** `http://localhost/Portfolio_v2/backend/public/api`
**Authentication:** Laravel Sanctum (Bearer Token)
**Last Updated:** October 25, 2025

---

## Table of Contents

1. [Authentication](#authentication)
2. [Awards](#awards)
3. [Projects](#projects)
4. [Blog Posts](#blog-posts)
5. [Categories](#categories)
6. [Galleries](#galleries)
7. [Services](#services)
8. [Testimonials](#testimonials)
9. [Contact](#contact)
10. [Settings](#settings)
11. [Menu Items](#menu-items)
12. [Page Sections](#page-sections)
13. [Dashboard](#dashboard)
14. [Automation API](#automation-api)

---

## Authentication

### Login
```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "Admin", "email": "admin@example.com" },
    "token": "1|abc123..."
  }
}
```

### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

### Get Authenticated User
```http
GET /auth/me
Authorization: Bearer {token}
```

---

## Awards

### List All Awards (Public)
```http
GET /awards
Query Parameters:
  - featured (boolean): Filter by featured status
  - search (string): Search by title or description
  - per_page (integer): Items per page (default: 12)
```

### Get Single Award (Public)
```http
GET /awards/{id}
```

### Get Award Galleries (Public)
```http
GET /awards/{id}/galleries
```

### Admin: List Awards
```http
GET /admin/awards
Authorization: Bearer {token}
Query Parameters: Same as public + admin filters
```

### Admin: Create Award
```http
POST /admin/awards
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Best Website 2024",
  "category": "Web Design",
  "organization": "Awwwards",
  "year": 2024,
  "description": "Award description",
  "credential_id": "ABC123",
  "credential_url": "https://example.com/award",
  "date_received": "2024-01-15",
  "featured": true,
  "sort_order": 1
}
```

### Admin: Update Award
```http
PUT /admin/awards/{id}
Authorization: Bearer {token}
```

### Admin: Delete Award
```http
DELETE /admin/awards/{id}
Authorization: Bearer {token}
```

---

## Projects

### List All Projects (Public)
```http
GET /projects
Query Parameters:
  - status (string): published, draft, archived
  - featured (boolean): Filter by featured status
  - search (string): Search by title or description
  - per_page (integer): Items per page (default: 12)
```

### Get Single Project (Public)
```http
GET /projects/{slug}
```

### Admin: List Projects
```http
GET /admin/projects
Authorization: Bearer {token}
```

### Admin: Create Project
```http
POST /admin/projects
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "title": "Project Title",
  "slug": "project-title",
  "description": "Short description",
  "content": "Full project content",
  "client": "Client Name",
  "category": "Web Development",
  "technologies": ["Laravel", "Vue.js", "Tailwind"],
  "project_url": "https://example.com",
  "github_url": "https://github.com/user/repo",
  "featured_image": (file),
  "gallery_images": [(file), (file)],
  "status": "published",
  "featured": true,
  "start_date": "2024-01-01",
  "end_date": "2024-06-30",
  "sort_order": 1,
  "meta_title": "SEO Title",
  "meta_description": "SEO Description"
}
```

### Admin: Update Project
```http
PUT /admin/projects/{id}
Authorization: Bearer {token}
```

### Admin: Delete Project
```http
DELETE /admin/projects/{id}
Authorization: Bearer {token}
```

---

## Blog Posts

### List All Posts (Public)
```http
GET /posts
Query Parameters:
  - status (string): published, draft
  - category_id (integer): Filter by category
  - search (string): Search by title or content
  - per_page (integer): Items per page (default: 12)
```

### Get Single Post (Public)
```http
GET /posts/{slug}
```

### Admin: List Posts
```http
GET /admin/posts
Authorization: Bearer {token}
```

### Admin: Create Post
```http
POST /admin/posts
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "title": "Post Title",
  "slug": "post-title",
  "excerpt": "Short excerpt",
  "content": "Full post content",
  "category_id": 1,
  "featured_image": (file),
  "tags": ["Laravel", "Vue.js"],
  "status": "published",
  "featured": true,
  "published_at": "2024-01-15 10:00:00",
  "meta_title": "SEO Title",
  "meta_description": "SEO Description"
}
```

### Admin: Update Post
```http
PUT /admin/posts/{id}
Authorization: Bearer {token}
```

### Admin: Delete Post
```http
DELETE /admin/posts/{id}
Authorization: Bearer {token}
```

---

## Categories

### List All Categories (Public)
```http
GET /categories
Query Parameters:
  - type (string): post, project
  - search (string): Search by name
```

### Get Single Category (Public)
```http
GET /categories/{slug}
```

### Admin: List Categories
```http
GET /admin/categories
Authorization: Bearer {token}
```

### Admin: Create Category
```http
POST /admin/categories
Authorization: Bearer {token}

{
  "name": "Category Name",
  "slug": "category-name",
  "description": "Category description",
  "type": "post",
  "color": "#3B82F6",
  "icon": "folder",
  "parent_id": null
}
```

### Admin: Update Category
```http
PUT /admin/categories/{id}
Authorization: Bearer {token}
```

### Admin: Delete Category
```http
DELETE /admin/categories/{id}
Authorization: Bearer {token}
```

---

## Galleries

### List All Galleries (Public)
```http
GET /galleries
Query Parameters:
  - award_id (integer): Filter by award
  - company (string): Filter by company
  - period (string): Filter by period
  - is_active (boolean): Filter by active status
  - search (string): Search by title or description
  - per_page (integer): Items per page (default: 12)
```

### Get Single Gallery (Public)
```http
GET /galleries/{id}
```

### Get Gallery Items (Public)
```http
GET /galleries/{galleryId}/items
```

### Admin: List Galleries
```http
GET /admin/galleries
Authorization: Bearer {token}
```

### Admin: Create Gallery
```http
POST /admin/galleries
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "title": "Gallery Title",
  "description": "Gallery description",
  "company": "ACME Corp",
  "period": "2024",
  "thumbnail": (file),
  "award_id": 1,
  "is_active": true,
  "sort_order": 1
}
```

### Admin: Update Gallery
```http
PUT /admin/galleries/{id}
Authorization: Bearer {token}
```

### Admin: Delete Gallery
```http
DELETE /admin/galleries/{id}
Authorization: Bearer {token}
```

### Admin: Add Gallery Item
```http
POST /admin/galleries/{galleryId}/items
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "file": (file),
  "type": "image",
  "title": "Item Title",
  "description": "Item description",
  "sequence": 1
}
```

### Admin: Bulk Upload Gallery Items
```http
POST /admin/galleries/{galleryId}/items/bulk-upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "files": [(file), (file), ...],
  "titles": ["Title 1", "Title 2", ...],
  "descriptions": ["Desc 1", "Desc 2", ...]
}
```

**Validation:**
- Max 20 files per upload
- Max 5MB per file
- Allowed types: jpeg, jpg, png, gif, webp

### Admin: Update Gallery Item
```http
PUT /admin/galleries/{galleryId}/items/{id}
Authorization: Bearer {token}
```

### Admin: Delete Gallery Item
```http
DELETE /admin/galleries/{galleryId}/items/{id}
Authorization: Bearer {token}
```

---

## Services

### List All Services (Public)
```http
GET /services
Query Parameters:
  - active (boolean): Filter by active status
  - search (string): Search by title or description
  - order_by (string): Field to order by (default: order)
  - order_dir (string): asc or desc (default: asc)
  - per_page (integer): Items per page (default: 12)
  - all (boolean): Return all without pagination
```

### Get Single Service (Public)
```http
GET /services/{slug}
```

### Admin: List Services
```http
GET /admin/services
Authorization: Bearer {token}
```

### Admin: Create Service
```http
POST /admin/services
Authorization: Bearer {token}

{
  "title": "Web Development",
  "slug": "web-development",
  "description": "Professional web development services",
  "content": "Full service description and details",
  "icon": "code",
  "features": [
    "Responsive Design",
    "SEO Optimization",
    "Fast Performance"
  ],
  "active": true,
  "order": 1
}
```

**Validation:**
- title: required, max 255 characters
- slug: unique, max 255 characters (auto-generated if not provided)
- description: max 1000 characters
- icon: max 100 characters
- features: array of strings (max 500 chars each)
- order: auto-incremented if not provided

### Admin: Update Service
```http
PUT /admin/services/{slug}
Authorization: Bearer {token}
```

### Admin: Delete Service
```http
DELETE /admin/services/{slug}
Authorization: Bearer {token}
```

---

## Testimonials

### List All Testimonials (Public)
```http
GET /testimonials
Query Parameters:
  - featured (boolean): Filter by featured status
  - rating (integer): Filter by rating
  - per_page (integer): Items per page (default: 12)
```

### Get Single Testimonial (Public)
```http
GET /testimonials/{id}
```

### Admin: List Testimonials
```http
GET /admin/testimonials
Authorization: Bearer {token}
```

### Admin: Create Testimonial
```http
POST /admin/testimonials
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "client_name": "John Doe",
  "client_position": "CEO",
  "client_company": "ACME Corp",
  "content": "Testimonial content",
  "rating": 5,
  "avatar": (file),
  "featured": true,
  "project_id": 1
}
```

### Admin: Update Testimonial
```http
PUT /admin/testimonials/{id}
Authorization: Bearer {token}
```

### Admin: Delete Testimonial
```http
DELETE /admin/testimonials/{id}
Authorization: Bearer {token}
```

---

## Contact

### Submit Contact Form (Public)
```http
POST /contact
Content-Type: application/json
Rate Limit: 5 requests per minute

{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Project Inquiry",
  "message": "Message content"
}
```

### Admin: List Contact Submissions
```http
GET /admin/contacts
Authorization: Bearer {token}
```

### Admin: Get Single Contact
```http
GET /admin/contacts/{id}
Authorization: Bearer {token}
```

### Admin: Mark as Read
```http
PATCH /admin/contacts/{id}/mark-as-read
Authorization: Bearer {token}
```

### Admin: Export Contacts
```http
GET /admin/contacts/export
Authorization: Bearer {token}
Query Parameters:
  - format (string): csv, xlsx (default: csv)
```

### Admin: Delete Contact
```http
DELETE /admin/contacts/{id}
Authorization: Bearer {token}
```

---

## Settings

### Get Settings by Group (Public)
```http
GET /settings/{group}
Groups: site, about, social
```

### Admin: Get About Settings
```http
GET /admin/settings/about
Authorization: Bearer {token}
```

### Admin: Update About Settings
```http
PUT /admin/settings/about
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "name": "Your Name",
  "tagline": "Your tagline",
  "bio": "Your bio",
  "avatar": (file),
  "resume": (file),
  "skills": [
    {"name": "Laravel", "level": 95},
    {"name": "Vue.js", "level": 90}
  ],
  "experience": [
    {
      "company": "Company Name",
      "position": "Position",
      "period": "2020-2024",
      "description": "Description"
    }
  ],
  "education": [
    {
      "institution": "University",
      "degree": "Bachelor of Computer Science",
      "period": "2016-2020"
    }
  ]
}
```

### Admin: Get Site Settings
```http
GET /admin/settings/site
Authorization: Bearer {token}
```

### Admin: Update Site Settings
```http
PUT /admin/settings/site
Authorization: Bearer {token}

{
  "site_name": "Portfolio",
  "site_title": "My Portfolio",
  "site_description": "Description",
  "contact_email": "contact@example.com",
  "phone": "+1234567890",
  "address": "123 Street, City",
  "social_links": [
    {"platform": "github", "url": "https://github.com/user"},
    {"platform": "linkedin", "url": "https://linkedin.com/in/user"}
  ]
}
```

---

## Menu Items

### Get Public Menu Items
```http
GET /menu-items
```

### Admin: List Menu Items
```http
GET /admin/menu-items
Authorization: Bearer {token}
```

### Admin: Create Menu Item
```http
POST /admin/menu-items
Authorization: Bearer {token}

{
  "label": "About",
  "url": "/about",
  "order": 1,
  "parent_id": null,
  "is_active": true,
  "target": "_self"
}
```

### Admin: Update Menu Item
```http
PUT /admin/menu-items/{id}
Authorization: Bearer {token}
```

### Admin: Reorder Menu Items
```http
PUT /admin/menu-items/reorder
Authorization: Bearer {token}

{
  "items": [
    {"id": 1, "order": 1},
    {"id": 2, "order": 2},
    {"id": 3, "order": 3}
  ]
}
```

### Admin: Delete Menu Item
```http
DELETE /admin/menu-items/{id}
Authorization: Bearer {token}
```

---

## Page Sections

### Get Public Page Sections
```http
GET /page-sections
Query Parameters:
  - page (string): home, about, contact
```

### Admin: List Page Sections
```http
GET /admin/page-sections
Authorization: Bearer {token}
```

### Admin: Update Page Section
```http
PUT /admin/page-sections/{id}
Authorization: Bearer {token}

{
  "title": "Section Title",
  "content": "Section content",
  "is_active": true,
  "order": 1
}
```

### Admin: Reorder Page Sections
```http
PUT /admin/page-sections/reorder
Authorization: Bearer {token}

{
  "sections": [
    {"id": 1, "order": 1},
    {"id": 2, "order": 2}
  ]
}
```

---

## Dashboard

### Get Dashboard Stats
```http
GET /admin/dashboard/stats
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "stats": {
      "projects": { "total": 15, "published": 12, "draft": 3 },
      "posts": { "total": 25, "published": 20, "draft": 5 },
      "gallery": { "total": 30 },
      "views": { "total": "1.2K" }
    },
    "recentProjects": [...],
    "recentPosts": [...],
    "recentActivity": [...]
  }
}
```

---

## Automation API

**Rate Limit:** 60 requests per minute
**Authentication:** Bearer Token (automation-specific tokens)

### Get Posts
```http
GET /automation/posts
Authorization: Bearer {automation_token}
```

### Get Single Post
```http
GET /automation/posts/{id}
Authorization: Bearer {automation_token}
```

### Create Post
```http
POST /automation/posts
Authorization: Bearer {automation_token}

{
  "title": "Post Title",
  "content": "Post content",
  "category_id": 1,
  "status": "draft",
  "published_at": "2024-01-15 10:00:00"
}
```

### Update Post
```http
PUT /automation/posts/{id}
Authorization: Bearer {automation_token}
```

### Delete Post
```http
DELETE /automation/posts/{id}
Authorization: Bearer {automation_token}
```

### Bulk Create Posts
```http
POST /automation/posts/bulk
Authorization: Bearer {automation_token}

{
  "posts": [
    {"title": "Post 1", "content": "Content 1", "category_id": 1},
    {"title": "Post 2", "content": "Content 2", "category_id": 2}
  ]
}
```

### Get Categories
```http
GET /automation/categories
Authorization: Bearer {automation_token}
```

### Webhook: Post Published
```http
POST /automation/webhook/published
Authorization: Bearer {automation_token}

{
  "post_id": 1,
  "webhook_url": "https://n8n.example.com/webhook/post-published"
}
```

### Admin: List Automation Tokens
```http
GET /admin/automation/tokens
Authorization: Bearer {token}
```

### Admin: Create Automation Token
```http
POST /admin/automation/tokens
Authorization: Bearer {token}

{
  "name": "n8n Integration",
  "description": "Token for n8n automation",
  "expires_at": "2025-12-31"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "n8n Integration",
    "token": "atk_abc123...",
    "expires_at": "2025-12-31"
  },
  "message": "Token created successfully. Save this token as it won't be shown again."
}
```

### Admin: Delete Automation Token
```http
DELETE /admin/automation/tokens/{id}
Authorization: Bearer {token}
```

### Admin: Get Automation Logs
```http
GET /admin/automation/logs
Authorization: Bearer {token}
Query Parameters:
  - per_page (integer): Items per page (default: 20)
  - date_from (date): Filter from date
  - date_to (date): Filter to date
```

### Admin: Clear Automation Logs
```http
DELETE /admin/automation/logs
Authorization: Bearer {token}
```

---

## SEO & Sitemap

### Get Sitemap Index
```http
GET /sitemap-index.xml
```

### Get Posts Sitemap
```http
GET /sitemap-posts.xml
```

### Get Projects Sitemap
```http
GET /sitemap-projects.xml
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

### 429 Too Many Requests
```json
{
  "message": "Too Many Attempts."
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "An error occurred",
  "error": "Error details (only in debug mode)"
}
```

---

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/contact` | 5 requests per minute |
| `/automation/*` | 60 requests per minute |
| All other endpoints | 60 requests per minute |

---

## Testing

All endpoints can be tested using:
- **Postman Collection:** Available in `postman/Portfolio_v2.json`
- **API Testing:** `php artisan test --filter=ApiTest`
- **Local Base URL:** `http://localhost/Portfolio_v2/backend/public/api`

---

**Last Updated:** October 25, 2025
**Version:** 2.0
**Maintainer:** Ali Sadikin
