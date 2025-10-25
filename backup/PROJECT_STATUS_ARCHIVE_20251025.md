# PROJECT STATUS ARCHIVE - Portfolio v2

**Archive Date:** October 25, 2025  
**Archived From:** Phase 6 Complete (Oct 16, 2025)  
**Final Progress:** 67% Complete

> This file contains detailed history of all completed sprints.  
> For current status, see: [PROJECT_STATUS.md](./PROJECT_STATUS.md)

---

## 📊 Phase 6: Production Ready Version - COMPLETE ✅

**Methodology:** Sprint-based (1 sprint = 1 complete feature)  
**Total Sprints:** 12 (8 Admin Features + 4 Public Pages)  
**Completion:** 12/12 (100%) ✅  
**Duration:** October 15-16, 2025

---

## ✅ Sprint 1: Projects Management - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅
- ✅ **ProjectController** - Full CRUD implementation
  - `index()` - List projects with search, filters, pagination
  - `indexForAdmin()` - Admin list (includes unpublished)
  - `show($slug)` - Get project by slug
  - `showById($id)` - Get project by ID (admin)
  - `store()` - Create project
  - `update($id)` - Update project
  - `destroy($id)` - Delete project

- ✅ **Form Validation**
  - `StoreProjectRequest.php` - Create validation rules
  - `UpdateProjectRequest.php` - Update validation rules

- ✅ **API Routes**
  ```
  GET    /admin/projects
  GET    /admin/projects/:id
  POST   /admin/projects
  PUT    /admin/projects/:id
  DELETE /admin/projects/:id
  ```

### Frontend Deliverables ✅
- ✅ **ProjectForm Component**
- ✅ **Admin Views** (ProjectCreate, ProjectEdit)
- ✅ **Projects Store** (Pinia)
- ✅ **Routes Configured**

### Features Delivered ✅
- ✅ Full CRUD operations
- ✅ Image upload with preview (5MB max)
- ✅ Technologies array input
- ✅ Auto-slug generation
- ✅ SEO fields (collapsible)
- ✅ Form validation (client + server)
- ✅ Dark mode support

---

## ✅ Sprint 2: Awards Management - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅
- ✅ **AwardController** - Full CRUD + Gallery relationships
- ✅ **Form Validation** (StoreAwardRequest, UpdateAwardRequest)
- ✅ **API Routes** (CRUD + gallery linking)

### Frontend Deliverables ✅
- ✅ **AwardForm Component**
- ✅ **GalleryManager Component** (link galleries to award)
- ✅ **Admin Views** (AwardsList, AwardCreate, AwardEdit)
- ✅ **Awards Store**

### Features Delivered ✅
- ✅ Full CRUD operations
- ✅ Image upload with preview
- ✅ **Gallery Relationship Management**
  - View linked galleries
  - Link new galleries via modal
  - Unlink galleries with confirmation
  - Gallery count display
- ✅ Search and filters
- ✅ Pagination

---

## ✅ Sprint 3: Gallery Management - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅ (Pre-existing)
- ✅ **GalleryController** - Full CRUD + Bulk Operations
- ✅ **Form Validation** (StoreGalleryRequest, UpdateGalleryRequest)
- ✅ **API Resource** (GalleryResource)
- ✅ **API Routes**

### Frontend Deliverables ✅
- ✅ **Galleries Store** (converted to Options API)

### Features Delivered ✅
- ✅ Full CRUD operations
- ✅ Image upload (5MB max)
- ✅ **Bulk Operations**
  - Bulk upload (up to 20 images)
  - Bulk delete
- ✅ Category filtering
- ✅ Search functionality
- ✅ Pagination (12 items/page)

---

## ✅ Sprint 4: Testimonials Management - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅
- ✅ **TestimonialController** - Full CRUD
- ✅ **Form Validation** (StoreTestimonialRequest, UpdateTestimonialRequest)
- ✅ **API Routes**

### Frontend Deliverables ✅
- ✅ **TestimonialForm Component** (with 5-star rating UI)
- ✅ **Admin Views** (TestimonialsList, TestimonialCreate, TestimonialEdit)
- ✅ **Testimonials Store**
- ✅ **Routes Configured**

### Features Delivered ✅
- ✅ Full CRUD operations
- ✅ **5-Star Rating System** (interactive selector)
- ✅ Client photo upload
- ✅ Search filters (name, company, job, text)
- ✅ Rating filter
- ✅ Active status filter
- ✅ Sort order management

---

## ✅ Sprint 5: Contact Messages - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅
- ✅ **ContactController** - Read-only CRUD + Export
  - `index()` - List with search & filters
  - `show($id)` - Get contact (auto-marks as read)
  - `markAsRead($id)` - Manual mark as read
  - `destroy($id)` - Delete
  - `export()` - Export to CSV

- ✅ **Contact Model** (with markAsRead method)
- ✅ **ContactResource**
- ✅ **API Routes**

### Frontend Deliverables ✅
- ✅ **ContactsList View**
  - List with read/unread badges
  - Search (multi-field)
  - Read status filter
  - Pagination (20/page)
  - **View Detail Modal**
  - Mark as read button
  - Delete with confirmation
  - **Export to CSV button**
  - Unread count display

- ✅ **Contacts Store** (with export method)

### Features Delivered ✅
- ✅ **Read-Only Management**
- ✅ View detail modal
- ✅ Mark as read (auto + manual)
- ✅ Delete with confirmation
- ✅ **Export to CSV**
- ✅ Search (multi-field)
- ✅ Read status filter
- ✅ Pagination

---

## ✅ Sprint 6: About Settings - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅
- ✅ **SettingsController** - About settings management
  - `getAboutSettings()` - Get all about group
  - `updateAboutSettings()` - Update with complex arrays

- ✅ **Form Validation**
  - `UpdateAboutSettingsRequest.php`
  - `prepareForValidation()` - Decode JSON arrays

- ✅ **API Routes**
  ```
  GET /admin/settings/about
  PUT /admin/settings/about
  ```

### Frontend Deliverables ✅
- ✅ **AboutSettings Component**
  - **Basic Info Card** (name, title, bio, profile photo)
  - **Skills Card** (dynamic array)
  - **Experience Card** (dynamic array, 7 fields each)
  - **Education Card** (dynamic array, 6 fields each)
  - **Social Links Card** (dynamic array, 3 fields each)

- ✅ **Settings Store** (FormData + JSON arrays)

### Features Delivered ✅
- ✅ **Dynamic Array Management**
  - Skills (simple strings)
  - Experience (complex objects, 7 fields)
  - Education (complex objects, 6 fields)
  - Social links (objects, 3 fields)
- ✅ Profile photo upload
- ✅ Photo removal
- ✅ File size validation (5MB)
- ✅ **FormData with JSON.stringify()**
- ✅ **JSON decoding in backend**

### Technical Implementation
**FormData + JSON Pattern:**
```javascript
// Frontend
data.append('skills', JSON.stringify(skills))
data.append('experience', JSON.stringify(experience))
```

```php
// Backend
protected function prepareForValidation(): void {
    if (is_string($this->input('skills'))) {
        $this->merge(['skills' => json_decode($this->input('skills'), true)]);
    }
}
```

---

## ✅ Sprint 7: Site Settings - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅
- ✅ **SettingsController** - Site settings management
  - `getSiteSettings()` - Get site group
  - `updateSiteSettings()` - Update site config

- ✅ **Form Validation**
  - `UpdateSiteSettingsRequest.php`

- ✅ **API Routes**
  ```
  GET /admin/settings/site
  PUT /admin/settings/site
  ```

### Frontend Deliverables ✅
- ✅ **SettingsForm Component**
  - **Site Information Card** (name, description, logo)
  - **Contact Information Card** (email, phone, address)
  - **Social Media Links Card** (dynamic array, platform dropdown)
  - **SEO Settings Card** (keywords, author, analytics ID)

- ✅ **Settings Store** (already updated in Sprint 6)

### Features Delivered ✅
- ✅ **Site Configuration**
  - Site name and description
  - Logo upload with preview
  - Logo removal
  - File size validation (5MB)
- ✅ **Contact Information**
  - Email, phone, address fields
- ✅ **Dynamic Social Media Links**
  - Add/remove links
  - Platform dropdown
  - URL validation
- ✅ **SEO Configuration**
  - Meta keywords array
  - Meta author
  - Google Analytics integration

---

## ✅ Sprint 8: Blog Management - COMPLETED (Oct 15, 2025)

### Backend Deliverables ✅
- ✅ **PostController** - Full CRUD for admin
  - `indexForAdmin()` - With search, filters, pagination
  - `index()` - Public list (published only)
  - `showById($id)` - Get by ID (admin)
  - `show($slug)` - Get by slug (public)
  - `store()` - Create with image upload
  - `update($id)` - Update with image
  - `destroy($id)` - Delete

- ✅ **CategoryController** - Full CRUD
  - `indexForAdmin()` - With search, pagination
  - `index()` - Public list
  - `showById($id)` - Get by ID (admin)
  - `show($slug)` - Get by slug (public)
  - `store()` - Create
  - `update($id)` - Update
  - `destroy($id)` - Delete (with validation)

- ✅ **Form Validation**
  - `StoreCategoryRequest.php`
  - `UpdateCategoryRequest.php`

- ✅ **API Routes**
  ```
  GET    /admin/posts
  GET    /admin/posts/:id
  POST   /admin/posts
  PUT    /admin/posts/:id
  DELETE /admin/posts/:id

  GET    /admin/categories
  GET    /admin/categories/:id
  POST   /admin/categories
  PUT    /admin/categories/:id
  DELETE /admin/categories/:id
  ```

### Frontend Deliverables ✅
- ✅ **PostsList View**
  - Search (title, content, excerpt)
  - Filters (category, status)
  - Posts table (thumbnail, title, category, status, date)
  - Actions (edit, delete)
  - Pagination
  - Delete confirmation modal
  - Empty states
  - Loading states

- ✅ **Posts Store Updates**
  - `fetchPosts()` - Uses `/admin/posts`
  - `fetchPostById(id)` - Admin edit
  - Filter support (search, category, published)

### Features Delivered ✅
- ✅ **Full Blog CRUD**
  - Create posts with image
  - Edit posts
  - Delete with confirmation
  - View list with filters
- ✅ **Category Management**
  - Full CRUD
  - Color picker
  - Slug auto-generation
  - Post count display
  - Delete protection
- ✅ **Advanced Search & Filters**
  - Real-time search (300ms debounce)
  - Category filter
  - Status filter (Published/Draft)
- ✅ **Pagination** (10 posts/page)
- ✅ **UI Features**
  - Category color badges
  - Status badges (green/yellow)
  - Responsive table
  - Dark mode support

---

## ✅ Sprint 9: Automation API (n8n) - COMPLETED (Oct 16, 2025)

### Backend Deliverables ✅
- ✅ **AutomationController** - Dedicated endpoints
  - `getPosts()` - List with advanced filters
  - `getPost($id)` - Get single
  - `createPost()` - Create (simplified validation)
  - `updatePost($id)` - Update
  - `deletePost($id)` - Delete
  - `bulkCreatePosts()` - Batch create (up to 50)
  - `getCategories()` - List categories
  - `postPublishedWebhook()` - Webhook trigger

- ✅ **AutomationPostRequest** - Flexible validation
  - Auto-slug, auto-excerpt, auto-published_at

- ✅ **TokenController** - API token management
  - `index()` - List tokens
  - `store()` - Create with abilities
  - `destroy($id)` - Revoke
  - `logs()` - Get logs
  - `clearLogs()` - Clear logs

- ✅ **Automation Logs Table**
  - Migration: `2025_10_16_051922_create_automation_logs_table`
  - Fields: user_id, token_id, action, ip_address, metadata

- ✅ **API Routes** (Rate limited: 60 req/min)
  ```
  GET    /automation/posts
  GET    /automation/posts/:id
  POST   /automation/posts
  PUT    /automation/posts/:id
  DELETE /automation/posts/:id
  POST   /automation/posts/bulk
  GET    /automation/categories
  POST   /automation/webhook/published

  GET    /admin/automation/tokens
  POST   /admin/automation/tokens
  DELETE /admin/automation/tokens/:id
  GET    /admin/automation/logs
  DELETE /admin/automation/logs
  ```

### Frontend Deliverables ✅
- ✅ **Automation Store** (stores/automation.js)
  - Token management
  - Logs with pagination
  - Getters (activeTokens, revokedTokens, totalActiveTokens)

- ✅ **AutomationTokens View**
  - Stats cards (active, revoked, total requests)
  - Tokens list table
  - Create token modal (name, abilities)
  - Token created modal (show once)
  - Revoke confirmation
  - Empty state
  - Dark mode

- ✅ **AutomationLogs View**
  - Filters (action, date range)
  - Logs table (timestamp, action, token, IP, metadata)
  - Action badges (color-coded)
  - View details modal (formatted JSON)
  - Clear all logs
  - Pagination (20/page)
  - Empty state

- ✅ **AutomationDocs View**
  - Quick start guide (4 steps)
  - Authentication section
  - Endpoints reference (complete with examples)
  - n8n workflow templates (3 patterns)
  - Code examples with syntax highlighting

- ✅ **Routes Configured**
  ```
  /admin/automation/tokens
  /admin/automation/logs
  /admin/automation/docs
  ```

### Features Delivered ✅
- ✅ **Token-Based Authentication** (Sanctum)
- ✅ **Rate Limiting** (60 req/min)
- ✅ **Simplified API** (auto-fill slug, excerpt, dates)
- ✅ **Bulk Operations** (up to 50 posts)
- ✅ **Audit Logging** (all requests logged)
- ✅ **Token Management UI**
- ✅ **Activity Logs UI**
- ✅ **API Documentation**
- ✅ **n8n Templates**
- ✅ **Base64 Image Support**
- ✅ **Markdown Support**
- ✅ **Dark Mode**

### Use Cases Enabled
1. **RSS to Blog** - Auto-publish from RSS feeds
2. **Notion to Blog** - Sync from Notion databases
3. **Email to Draft** - Convert emails to drafts
4. **AI Content** - Generate and publish AI posts
5. **Social Cross-posting** - Publish + cross-post

---

## ✅ Sprint 10: Home Hero Section - COMPLETED (Oct 16, 2025)

### Frontend Deliverables ✅
- ✅ **Updated Home Page** (views/Home.vue)
  - Fetch About settings from `/settings/about`
  - Display real name and title
  - Display real bio text
  - Display real skills
  - Fallback to defaults if API fails
  - Maintain animations & responsive design

### Features Delivered ✅
- ✅ Real data from About settings API
- ✅ Name and title display
- ✅ Bio text rendering
- ✅ Skills display (from API or defaults)
- ✅ CTA buttons link to routes
- ✅ Animations work smoothly
- ✅ Responsive (mobile/tablet)
- ✅ Dark mode supported

### Implementation
**Computed Properties with Fallbacks:**
```javascript
const heroName = computed(() => aboutSettings.value?.name || 'Creative Developer')
const heroTitle = computed(() => aboutSettings.value?.title || 'Digital Designer')
const heroBio = computed(() => aboutSettings.value?.bio || 'Default bio...')
const heroSkills = computed(() => aboutSettings.value?.skills || ['Vue.js', ...])
```

---

## ✅ Sprint 11: About Page - COMPLETED (Oct 16, 2025)

### Frontend Deliverables ✅
- ✅ **Updated About Page** (views/About.vue)
  - **Hero Section** (name, title from API)
  - **Bio Section** (profile image + rich HTML bio)
  - **Skills Grid** (2/3/4 columns responsive)
  - **Experience Timeline** (position, company, period, description)
  - **Education Cards** (degree, institution, period)
  - **Social Links** (GitHub, LinkedIn, Twitter with icons)
  - **Computed Properties** (displayExperiences, displayEducation, displaySocialLinks)
  - **Conditional Rendering** (show only if data exists)
  - **Graceful Fallbacks**

### Features Delivered ✅
- ✅ Display real name and title
- ✅ Bio content with HTML rendering
- ✅ Profile image display
- ✅ Skills grid
- ✅ Experience timeline
- ✅ Education cards
- ✅ Social links with icons
- ✅ Responsive on all devices
- ✅ Dark mode support
- ✅ Loading states
- ✅ Error handling with fallback

### Implementation
**Computed Properties Pattern:**
```javascript
const displayExperiences = computed(() => {
  if (about.value?.experiences && Array.isArray(about.value.experiences)) {
    return about.value.experiences
  }
  return []
})
```

---

## ✅ Sprint 12: Contact Page - COMPLETED (Oct 16, 2025)

### Frontend Deliverables ✅
- ✅ **Updated Contact Page** (views/Contact.vue)
  - **Contact Form** (name, email, subject, message)
  - **Client-side Validation** (real-time errors)
  - **Backend Integration** (POST /contact)
  - **Toast Notifications** (success/error)
  - **Site Settings Integration** (contact info from API)
  - **Dynamic Contact Information** (email, phone, address)
  - **Dynamic Social Links** (GitHub, LinkedIn, Twitter)
  - **Conditional Rendering** (show only if data exists)
  - **Loading States**

### Features Delivered ✅
- ✅ Working contact form (4 fields)
- ✅ Client-side validation (real-time)
- ✅ Form submission to API
- ✅ Success toast notification
- ✅ Error toast notification
- ✅ Form reset after success
- ✅ Contact email (mailto link)
- ✅ Phone number (tel link)
- ✅ Address display
- ✅ Response time info
- ✅ Social media links with icons
- ✅ Responsive design
- ✅ Dark mode support
- ✅ Loading states

### Implementation
**Form Validation:**
```javascript
const validateForm = () => {
  const errors = {}
  // Name validation (min 2 chars)
  // Email validation (regex)
  // Subject validation (min 3 chars)
  // Message validation (min 10 chars)
  return errors
}
```

**Backend Integration:**
```javascript
const handleSubmit = async () => {
  formErrors.value = validateForm()
  if (Object.keys(formErrors.value).length > 0) {
    uiStore.showToast({ type: 'error', ... })
    return
  }
  
  const response = await api.post('/contact', sanitizedForm)
  uiStore.showToast({ type: 'success', ... })
  // Reset form
}
```

---

## 📊 Final Statistics (Phase 6)

### Database - 100% Complete
- ✅ 17 Migrations created
- ✅ 13 Seeders working
- ✅ Fresh install tested (`migrate:fresh --seed`)

### Backend API - 78% Complete
- ✅ 7/9 Controllers complete (78%)
  - ProjectController ✅
  - AwardController ✅
  - GalleryController ✅
  - TestimonialController ✅
  - ContactController ✅
  - SettingsController ✅
  - PostController + CategoryController ✅
  - AutomationController ✅
  - ⚠️ ServiceController (future)
  - ⚠️ NewsletterController (future)

- ✅ 7/9 Form Validation complete (78%)
- ✅ 6/9 API Resources complete (67%)

### Frontend Admin - 80% Complete
- ✅ 8/10 Admin features complete (80%)
  1. ✅ Projects CRUD
  2. ✅ Awards CRUD
  3. ✅ Gallery CRUD
  4. ✅ Testimonials CRUD
  5. ✅ Contact Messages (read-only)
  6. ✅ About Settings
  7. ✅ Site Settings
  8. ✅ Blog Management (Posts + Categories)
  9. ✅ Automation (Tokens + Logs + Docs)
  10. ⚠️ Dashboard (basic stats only)

### Frontend Public - 35% Complete
**Complete Pages (7/9):**
1. ✅ Home (with real API data)
2. ✅ Projects (list + grid)
3. ✅ Awards (with gallery modal)
4. ✅ Blog (list with filters)
5. ✅ About (with real data)
6. ✅ Contact (working form)
7. ✅ Gallery (lightbox viewer)

**Incomplete Pages (2/9):**
8. ⚠️ ProjectDetail (30%)
9. ⚠️ BlogDetail (30%)

---

## 🎯 Phase 6 Achievements

### Admin Panel Features
- ✅ **8 Complete CRUD Modules** - Projects, Awards, Gallery, Testimonials, Contact, About, Settings, Blog, Automation
- ✅ **Advanced Features**
  - Image upload with preview
  - Bulk operations (gallery bulk upload/delete)
  - Dynamic arrays (skills, experience, education, social links)
  - Rich text editor (CKEditor 5)
  - Search and filters
  - Pagination
  - Export to CSV (contacts)
  - 5-star rating system (testimonials)
  - Gallery relationships (award-gallery linking)
  - Category color badges
  - Token-based authentication (automation API)
  - Activity logs (automation)
  - API documentation
- ✅ **UI/UX**
  - Dark mode throughout
  - Responsive design
  - Loading states
  - Toast notifications
  - Confirmation modals
  - Empty states
  - Character counters
  - Collapsible sections

### Public Pages
- ✅ **7 Working Pages** with real API data
- ✅ **Homepage** - Hero, stats, awards, featured projects, latest blog, testimonials, CTA
- ✅ **About Page** - Bio, skills, experience, education, social links
- ✅ **Contact Page** - Working form with site settings integration
- ✅ **Projects, Awards, Gallery, Blog** - List views with filters

### Technical Achievements
- ✅ **Laravel 10 Backend**
  - RESTful API
  - FormRequest validation
  - API Resources
  - Sanctum authentication
  - File upload handling
  - Transaction-safe operations
  - Rate limiting (automation API)
  - Audit logging
- ✅ **Vue 3 Frontend**
  - Composition API
  - Pinia state management
  - Axios integration
  - Tailwind CSS 4.1
  - Headless UI components
  - Heroicons
  - CKEditor 5 via CDN
  - Dark mode toggle
- ✅ **Development Tools**
  - XAMPP (Apache Port 80, MySQL Port 3306)
  - Vite dev server (Port 5173)
  - Windows 11 environment
  - Multi-agent system (orchestrator + 5 specialists)

---

## 📝 Files Created (Phase 6)

### Backend Files (40+)
**Controllers:**
- ProjectController.php (Sprint 1)
- AwardController.php (Sprint 2)
- GalleryController.php (Sprint 3)
- TestimonialController.php (Sprint 4)
- ContactController.php (Sprint 5)
- SettingsController.php (Sprint 6 & 7)
- PostController.php (Sprint 8)
- CategoryController.php (Sprint 8)
- AutomationController.php (Sprint 9)
- TokenController.php (Sprint 9)

**Form Requests:**
- StoreProjectRequest.php, UpdateProjectRequest.php
- StoreAwardRequest.php, UpdateAwardRequest.php
- StoreGalleryRequest.php, UpdateGalleryRequest.php
- StoreTestimonialRequest.php, UpdateTestimonialRequest.php
- UpdateAboutSettingsRequest.php
- UpdateSiteSettingsRequest.php
- StoreCategoryRequest.php, UpdateCategoryRequest.php
- AutomationPostRequest.php

**Resources:**
- GalleryResource.php
- ContactResource.php
- AwardResource.php
- TestimonialResource.php
- PostResource.php
- CategoryResource.php
- GalleryItemResource.php (pending Phase 9)

**Migrations:**
- 2025_10_16_051922_create_automation_logs_table.php

### Frontend Files (50+)
**Admin Views:**
- ProjectCreate.vue, ProjectEdit.vue
- AwardsList.vue, AwardCreate.vue, AwardEdit.vue
- GalleriesList.vue
- TestimonialsList.vue, TestimonialCreate.vue, TestimonialEdit.vue
- ContactsList.vue
- AboutSettings.vue
- SettingsForm.vue
- PostsList.vue
- AutomationTokens.vue, AutomationLogs.vue, AutomationDocs.vue

**Components:**
- ProjectForm.vue
- AwardForm.vue
- GalleryManager.vue
- TestimonialForm.vue
- GalleryItemsSection.vue (pending Phase 9)

**Stores:**
- projects.js
- awards.js
- galleries.js
- testimonials.js
- contacts.js
- settings.js
- posts.js
- categories.js
- automation.js

**Public Views:**
- Home.vue (updated)
- About.vue (updated)
- Contact.vue (updated)

---

## 🚀 Next Phase: Phase 7 - Menu & Section Management

**Status:** Planned (not started)  
**Duration:** 2 Sprints (35-40 hours)  
**Type:** Feature Enhancement

### Sprint 13: Menu Items Management (12-14 hours)
- Admin control navigation menu
- Toggle active/inactive, reorder
- Edit title, URL, icon

### Sprint 14: Homepage Sections Manager (21-24 hours)
- Control homepage sections
- Drag-n-drop reorder with preview
- Project CTA fields

---

**Archive Complete:** October 25, 2025  
**Total Sprints Documented:** 12  
**Project Phase:** Phase 6 Complete → Phase 9 Active
