# Portfolio V2 - API Endpoints

**Version:** 2.0
**Date:** October 2, 2025
**Framework:** RESTful API
**Authentication:** Bearer Token (Admin endpoints)

---

## Table of Contents

1. [Overview](#overview)
2. [Base URLs](#base-urls)
3. [Authentication](#authentication)
4. [Public Endpoints](#public-endpoints)
5. [Admin Endpoints](#admin-endpoints)
6. [Error Responses](#error-responses)
7. [Rate Limiting](#rate-limiting)

---

## Overview

This document defines the RESTful API contract for the Portfolio V2 application. All endpoints follow REST principles with consistent naming conventions, HTTP methods, and response structures.

### API Design Principles

- **RESTful Resources**: Endpoints represent resources (nouns, not verbs)
- **Consistent Naming**: kebab-case for multi-word resources
- **Standard HTTP Methods**: GET, POST, PUT, PATCH, DELETE
- **Predictable Structure**: `/api/{resource}` for public, `/api/admin/{resource}` for admin
- **Versioning Ready**: Future versions can use `/api/v2/` prefix

---

## Base URLs

### Development
```
http://localhost:8000/api
```

### Production
```
https://alisadikinma.com/api
```

---

## Authentication

### Internationalization (i18n)

### Language Support

**Supported Languages:**
- 🇬🇧 English (`en`) - Default
- 🇮🇩 Bahasa Indonesia (`id`)

**Resources with i18n:**
- Blog Posts (title, excerpt, content, SEO fields)
- Projects (title, description, content, SEO fields)

**Resources without i18n:**
- Awards, Services, Gallery, Contact (English only)

### Using Language Parameter

All blog and project endpoints accept a `lang` query parameter:

```http
GET /api/posts?lang=en          # English (default)
GET /api/posts?lang=id          # Bahasa Indonesia
GET /api/posts/{slug}?lang=id   # Get Indonesian version
```

**Language Fallback:**
If translation not available → Falls back to English

**Response includes:**
```json
{
  "id": 1,
  "language": "id",
  "available_languages": ["en", "id"],
  "title": "Memulai dengan Vue 3",
  ...
}
```

---

## Public Endpoints
No authentication required.

### Admin Endpoints
All admin endpoints require Bearer token authentication.

**Header:**
```http
Authorization: Bearer {access_token}
```

**Token Acquisition:**
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@alisadikinma.com",
  "password": "secure_password"
}

Response:
{
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

**Token Refresh:**
```http
POST /api/auth/refresh
Authorization: Bearer {refresh_token}

Response:
{
  "access_token": "new_token_here",
  "expires_in": 3600
}
```

---

## Public Endpoints

### Projects

#### Get All Projects
```http
GET /api/projects
```

**Query Parameters:**
- `page` (integer, default: 1) - Page number
- `per_page` (integer, default: 12) - Items per page
- `category` (string, optional) - Filter by category
- `search` (string, optional) - Search in title/description
- `sort` (string, default: "latest") - Sort by: latest, oldest, title
- `lang` (string, default: "en") - Language code (en, id)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "ecommerce-platform",
      "title": "E-commerce Platform",
      "description": "Full-stack e-commerce solution...",
      "thumbnail_url": "https://cdn.alisadikinma.com/projects/thumb-1.jpg",
      "category": "web-development",
      "tech_stack": ["Vue.js", "Node.js", "MongoDB"],
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

#### Get Single Project
```http
GET /api/projects/{slug}
```

**Path Parameters:**
- `slug` (string) - Project slug

**Query Parameters:**
- `lang` (string, default: "en") - Language code

**Response:**
```json
{
  "id": 1,
  "slug": "ecommerce-platform",
  "title": "E-commerce Platform",
  "description": "Full-stack e-commerce solution...",
  "content": "## Overview\nDetailed project description...",
  "thumbnail_url": "https://cdn.alisadikinma.com/projects/thumb-1.jpg",
  "images": [
    {
      "id": 1,
      "url": "https://cdn.alisadikinma.com/projects/img-1.jpg",
      "caption": "Homepage design"
    }
  ],
  "category": "web-development",
  "tech_stack": ["Vue.js", "Node.js", "MongoDB"],
  "live_url": "https://example.com",
  "github_url": "https://github.com/user/repo",
  "featured": true,
  "published_at": "2024-01-15T10:30:00Z",
  "created_at": "2024-01-10T08:00:00Z",
  "updated_at": "2024-01-15T10:30:00Z"
}
```

### Blog Posts

#### Get All Posts
```http
GET /api/posts
```

**Query Parameters:**
- `page` (integer, default: 1)
- `per_page` (integer, default: 10)
- `category` (string, optional)
- `tag` (string, optional)
- `search` (string, optional)
- `lang` (string, default: "en") - Language code (en, id)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "getting-started-vue3",
      "title": "Getting Started with Vue 3",
      "excerpt": "Learn the basics of Vue 3...",
      "featured_image": "https://cdn.alisadikinma.com/posts/vue3.jpg",
      "category": "tutorials",
      "tags": ["vue", "javascript", "frontend"],
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

#### Get Single Post
```http
GET /api/posts/{slug}
```

**Query Parameters:**
- `lang` (string, default: "en") - Language code

**Response:**
```json
{
  "id": 1,
  "slug": "getting-started-vue3",
  "title": "Getting Started with Vue 3",
  "excerpt": "Learn the basics of Vue 3...",
  "content": "# Getting Started\n\nVue 3 brings...",
  "featured_image": "https://cdn.alisadikinma.com/posts/vue3.jpg",
  "category": "tutorials",
  "tags": ["vue", "javascript", "frontend"],
  "author": {
    "name": "John Doe",
    "avatar": "https://cdn.alisadikinma.com/avatars/john.jpg"
  },
  "read_time": 5,
  "views": 1247,
  "published_at": "2024-02-01T12:00:00Z",
  "created_at": "2024-01-28T10:00:00Z",
  "updated_at": "2024-02-01T12:00:00Z"
}
```

### Awards

#### Get All Awards
```http
GET /api/awards
```

**Query Parameters:**
- `category` (string, optional) - Filter: award, certification, recognition, honor
- `featured` (boolean, optional) - Filter featured only

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Best Web Design Award",
      "organization": "Design Institute",
      "date": "2024-01-15",
      "category": "award",
      "description": "Recognized for outstanding work...",
      "icon_url": "https://cdn.alisadikinma.com/awards/trophy.png",
      "external_link": "https://institute.com/awards/2024",
      "featured": true
    }
  ]
}
```

### Testimonials

#### Get All Testimonials
```http
GET /api/testimonials
```

**Query Parameters:**
- `featured` (boolean, optional) - Filter featured only
- `rating` (integer, optional) - Filter by rating (1-5)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "quote": "Excellent work! Highly professional...",
      "author_name": "John Doe",
      "author_title": "CEO",
      "author_company": "Tech Company",
      "author_avatar": "https://cdn.alisadikinma.com/testimonials/john.jpg",
      "rating": 5,
      "featured": true
    }
  ]
}
```

### Gallery

#### Get All Gallery Images
```http
GET /api/gallery
```

**Query Parameters:**
- `category` (string, optional) - Filter by category
- `featured` (boolean, optional)
- `page` (integer, default: 1)
- `per_page` (integer, default: 24)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "url": "https://cdn.alisadikinma.com/gallery/img-1.jpg",
      "thumbnail_url": "https://cdn.alisadikinma.com/gallery/thumb-1.jpg",
      "title": "Project Screenshot",
      "caption": "Homepage design mockup",
      "alt_text": "E-commerce homepage screenshot",
      "category": "web-design",
      "featured": false
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 124,
    "per_page": 24
  }
}
```

### Contact

#### Submit Contact Form
```http
POST /api/contact
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Project Inquiry",
  "message": "I would like to discuss..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Your message has been sent successfully"
}
```

---

## Admin Endpoints

### Projects Management

#### Get All Projects (Admin)
```http
GET /api/admin/projects
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`, `per_page`, `status` (draft, published, archived)

**Response:** Same structure as public endpoint but includes draft/archived items

#### Create Project
```http
POST /api/admin/projects
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "New Project",
  "slug": "new-project",
  "description": "Short description...",
  "content": "## Full project details...",
  "thumbnail_url": "https://cdn.alisadikinma.com/uploads/thumb.jpg",
  "category": "web-development",
  "tech_stack": ["Vue.js", "Node.js"],
  "live_url": "https://example.com",
  "github_url": "https://github.com/user/repo",
  "featured": false,
  "status": "published"
}
```

**Response:**
```json
{
  "id": 42,
  "slug": "new-project",
  "title": "New Project",
  ...
  "created_at": "2024-10-02T14:30:00Z"
}
```

#### Update Project
```http
PUT /api/admin/projects/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:** Same as create (partial updates allowed)

#### Delete Project
```http
DELETE /api/admin/projects/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Project deleted successfully"
}
```

### Blog Management

#### Get All Posts (Admin)
```http
GET /api/admin/posts
Authorization: Bearer {token}
```

#### Create Post
```http
POST /api/admin/posts
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "title": "New Blog Post",
  "slug": "new-blog-post",
  "excerpt": "Short excerpt...",
  "content": "# Full content...",
  "featured_image": "https://cdn.alisadikinma.com/uploads/img.jpg",
  "category": "tutorials",
  "tags": ["vue", "javascript"],
  "status": "published"
}
```

#### Update Post
```http
PUT /api/admin/posts/{id}
Authorization: Bearer {token}
```

#### Delete Post
```http
DELETE /api/admin/posts/{id}
Authorization: Bearer {token}
```

### Awards Management

#### Get All Awards (Admin)
```http
GET /api/admin/awards
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`, `per_page`, `category`, `status`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Best Web Design Award",
      "organization": "Design Institute",
      "date": "2024-01-15",
      "category": "award",
      "description": "Recognized for outstanding work...",
      "icon_url": "https://cdn.alisadikinma.com/awards/trophy.png",
      "external_link": "https://institute.com/awards/2024",
      "featured": true,
      "display_order": 1,
      "status": "published",
      "created_at": "2024-01-10T10:00:00Z",
      "updated_at": "2024-01-15T12:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 3,
    "total_items": 28,
    "per_page": 10
  }
}
```

#### Create Award
```http
POST /api/admin/awards
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "Outstanding Developer Award",
  "organization": "Tech Association",
  "date": "2024-03-15",
  "category": "award",
  "description": "Awarded for exceptional contributions...",
  "icon_url": "https://cdn.alisadikinma.com/awards/medal.png",
  "external_link": "https://tech-assoc.com/awards",
  "featured": true,
  "display_order": 1,
  "status": "published"
}
```

**Validation Rules:**
- `title`: required, string, max 200 chars
- `organization`: required, string, max 200 chars
- `date`: required, date format (YYYY-MM-DD)
- `category`: required, enum (award, certification, recognition, honor)
- `description`: optional, string, max 1000 chars
- `icon_url`: optional, valid URL
- `external_link`: optional, valid URL
- `featured`: boolean, default false
- `display_order`: integer, default 0
- `status`: enum (draft, published), default draft

**Response:**
```json
{
  "id": 15,
  "title": "Outstanding Developer Award",
  ...
  "created_at": "2024-10-02T15:00:00Z"
}
```

#### Update Award
```http
PUT /api/admin/awards/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:** Same as create (partial updates supported)

#### Delete Award
```http
DELETE /api/admin/awards/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Award deleted successfully"
}
```

#### Upload Award Icon
```http
POST /api/admin/awards/upload-icon
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
- `icon`: file (image/png, image/jpeg, image/svg+xml, max 2MB)

**Response:**
```json
{
  "url": "https://cdn.alisadikinma.com/awards/icon-123.png"
}
```

### Testimonials Management

#### Get All Testimonials (Admin)
```http
GET /api/admin/testimonials
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`, `per_page`, `rating`, `status`

#### Create Testimonial
```http
POST /api/admin/testimonials
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "quote": "Excellent work! Highly professional and delivered on time.",
  "author_name": "John Doe",
  "author_title": "CEO",
  "author_company": "Tech Company",
  "author_avatar": "https://cdn.alisadikinma.com/testimonials/john.jpg",
  "author_website": "https://techcompany.com",
  "rating": 5,
  "related_project_id": 3,
  "date_received": "2024-02-10",
  "featured": true,
  "display_order": 1,
  "status": "published"
}
```

**Validation Rules:**
- `quote`: required, string, max 1000 chars
- `author_name`: required, string, max 100 chars
- `author_title`: required, string, max 100 chars
- `author_company`: optional, string, max 100 chars
- `author_avatar`: optional, valid URL
- `author_website`: optional, valid URL
- `rating`: required, integer 1-5
- `related_project_id`: optional, integer (valid project ID)
- `date_received`: optional, date format
- `featured`: boolean, default false
- `display_order`: integer, default 0
- `status`: enum (draft, published), default draft

#### Update Testimonial
```http
PUT /api/admin/testimonials/{id}
Authorization: Bearer {token}
```

#### Delete Testimonial
```http
DELETE /api/admin/testimonials/{id}
Authorization: Bearer {token}
```

#### Upload Testimonial Avatar
```http
POST /api/admin/testimonials/upload-avatar
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
- `avatar`: file (image/png, image/jpeg, max 2MB)

**Response:**
```json
{
  "url": "https://cdn.alisadikinma.com/testimonials/avatar-456.jpg"
}
```

### Gallery Management

#### Get All Images (Admin)
```http
GET /api/admin/gallery
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`, `per_page`, `category`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "url": "https://cdn.alisadikinma.com/gallery/img-1.jpg",
      "thumbnail_url": "https://cdn.alisadikinma.com/gallery/thumb-1.jpg",
      "title": "Project Screenshot",
      "caption": "Homepage design mockup",
      "alt_text": "E-commerce homepage screenshot",
      "category": "web-design",
      "tags": ["ui", "design", "ecommerce"],
      "featured": false,
      "display_order": 1,
      "file_size": 245678,
      "dimensions": {
        "width": 1920,
        "height": 1080
      },
      "uploaded_at": "2024-03-01T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 12,
    "total_items": 287,
    "per_page": 24
  }
}
```

#### Upload Images (Bulk)
```http
POST /api/admin/gallery/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
- `images[]`: multiple files (image/png, image/jpeg, image/webp, max 5MB each)
- `category`: string (optional)

**Response:**
```json
{
  "uploaded": [
    {
      "id": 288,
      "url": "https://cdn.alisadikinma.com/gallery/img-288.jpg",
      "thumbnail_url": "https://cdn.alisadikinma.com/gallery/thumb-288.jpg",
      "file_size": 345678,
      "dimensions": {
        "width": 1920,
        "height": 1080
      }
    }
  ],
  "failed": []
}
```

#### Update Image Metadata
```http
PUT /api/admin/gallery/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "Updated Title",
  "caption": "Updated caption",
  "alt_text": "Updated alt text",
  "category": "web-design",
  "tags": ["ui", "design"],
  "featured": true
}
```

#### Delete Image
```http
DELETE /api/admin/gallery/{id}
Authorization: Bearer {token}
```

#### Bulk Delete Images
```http
POST /api/admin/gallery/bulk-delete
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "ids": [1, 5, 12, 23]
}
```

**Response:**
```json
{
  "success": true,
  "deleted_count": 4,
  "message": "4 images deleted successfully"
}
```

### Settings Management

#### Get Settings
```http
GET /api/admin/settings
Authorization: Bearer {token}
```

#### Update Settings
```http
PUT /api/admin/settings
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "site_title": "John Doe Portfolio",
  "site_tagline": "Full-stack Developer & Designer",
  "contact_email": "aiagent@alisadikinma.com",
  "social_links": {
    "github": "https://github.com/johndoe",
    "linkedin": "https://linkedin.com/in/johndoe",
    "twitter": "https://twitter.com/johndoe"
  },
  "meta_description": "Portfolio showcasing web development projects...",
  "analytics_id": "GA-XXXXXXXXX"
}
```

---

## Error Responses

### Standard Error Format

All errors follow this structure:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": [
      {
        "field": "email",
        "message": "Email is required"
      }
    ]
  }
}
```

### HTTP Status Codes

- `200 OK` - Success
- `201 Created` - Resource created
- `204 No Content` - Success with no response body
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Missing or invalid authentication
- `403 Forbidden` - Authenticated but not authorized
- `404 Not Found` - Resource not found
- `409 Conflict` - Resource conflict (e.g., duplicate slug)
- `422 Unprocessable Entity` - Semantic validation error
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

### Error Codes

- `VALIDATION_ERROR` - Request validation failed
- `AUTHENTICATION_ERROR` - Invalid or missing credentials
- `AUTHORIZATION_ERROR` - Insufficient permissions
- `NOT_FOUND` - Resource not found
- `CONFLICT` - Resource conflict
- `RATE_LIMIT_EXCEEDED` - Too many requests
- `SERVER_ERROR` - Internal server error

---

## Rate Limiting

### Limits

**Public Endpoints:**
- 100 requests per minute per IP
- 1000 requests per hour per IP

**Admin Endpoints:**
- 500 requests per minute per token
- 5000 requests per hour per token

**Upload Endpoints:**
- 20 uploads per hour per token

### Rate Limit Headers

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1696248000
```

### Rate Limit Exceeded Response

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Please try again later.",
    "retry_after": 60
  }
}
```

---

## API Versioning (Future)

Future versions will use URL versioning:

```
/api/v2/projects
/api/v2/admin/projects
```

Current version (v1) is the default and doesn't require version prefix.

---

**End of API Endpoints Documentation**

This API contract provides a complete, RESTful interface for the Portfolio V2 application with consistent naming, clear structure, and comprehensive documentation for all endpoints.
