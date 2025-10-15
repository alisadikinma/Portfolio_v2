# Phase 6: Production Ready Version - Sprint-Based Approach

## Overview
Complete Portfolio v2 from skeleton to production-ready application using sprint methodology. Each sprint focuses on delivering one complete feature (admin CRUD or public page) with full testing and integration.

**Timeline:** 1 sprint per menu/feature  
**Approach:** One complete vertical slice per sprint (backend + frontend + tests)

---

## Project Context

### Tech Stack
- **Backend:** Laravel 10 API (XAMPP Port 80)
- **Frontend:** Vue 3 SPA (Vite Port 5173)
- **Database:** MySQL 8 (portfolio_v2)
- **Styling:** Tailwind CSS 4

### Critical URLs
```
Backend:  http://localhost/Portfolio_v2/backend/public/api
Frontend: http://localhost:5173
Database: localhost:3306 (user: ali, pass: aL1889900@@@)
```

### Mandatory Reading (Before Each Sprint)
```
C:\xampp\htdocs\Portfolio_v2\README.md
C:\xampp\htdocs\Portfolio_v2\PROJECT_STATUS.md
C:\xampp\htdocs\Portfolio_v2\PROJECT_INSTRUCTIONS.md
C:\xampp\htdocs\Portfolio_v2\.claude\agents\orchestrator.md
```

### Reference Patterns
**Backend:**
- `backend/app/Http/Controllers/Api/AwardController.php`
- `backend/app/Http/Requests/StorePostRequest.php`
- `backend/app/Http/Resources/PostResource.php`
- `backend/app/Models/Award.php`

**Frontend:**
- `frontend/src/views/admin/PostCreate.vue`
- `frontend/src/views/admin/PostsList.vue`
- `frontend/src/components/blog/BlogPostForm.vue`
- `frontend/src/stores/posts.js`

---

## Sprint Status

| Sprint | Feature | Status | Completion Date |
|--------|---------|--------|-----------------|
| 1 | Projects Management | ✅ COMPLETED | Oct 15, 2025 |
| 2 | Awards Management | 🔲 Pending | - |
| 3 | Gallery Management | 🔲 Pending | - |
| 4 | Testimonials Management | 🔲 Pending | - |
| 5 | Contact Messages | 🔲 Pending | - |
| 6 | About Settings | 🔲 Pending | - |
| 7 | Site Settings | 🔲 Pending | - |
| 8 | Home Hero Section | 🔲 Pending | - |
| 9 | About Page | 🔲 Pending | - |
| 10 | Blog Detail Page | 🔲 Pending | - |
| 11 | Contact Page | 🔲 Pending | - |

---

## Sprint 1: Projects Management ✅ COMPLETED

**Status:** ✅ COMPLETED (Oct 15, 2025)

**Delivered:**
- ✅ Backend API endpoints (CRUD)
- ✅ Form validation (StoreProjectRequest, UpdateProjectRequest)
- ✅ Admin list view with search, filters, pagination
- ✅ Admin create form (ProjectForm.vue component)
- ✅ Admin edit form
- ✅ Image upload with preview
- ✅ Technologies array input
- ✅ SEO fields (collapsible section)
- ✅ Pinia store (projects.js)

**Routes:**
- GET `/admin/projects` - List projects
- GET `/admin/projects/:id` - Get single project
- POST `/admin/projects` - Create project
- PUT `/admin/projects/:id` - Update project
- DELETE `/admin/projects/:id` - Delete project

---

## Sprint 2: Awards Management 🔲

**Objective:** Complete awards CRUD with gallery relationship

**Backend Deliverables:**
- ✅ AwardController (already exists, verify completeness)
- ⚠️ Verify validation rules
- ⚠️ Test gallery relationship endpoints

**Frontend Deliverables:**
- 🔲 Admin list view (`src/views/admin/AwardsList.vue`)
  - Search, filters, pagination
  - Display: title, organization, date, credential, gallery count
  - Actions: edit, delete
- 🔲 Admin create form (`src/views/admin/AwardCreate.vue`)
  - Fields: award_title, issuing_organization, award_date, credential_id, credential_url, description
  - Image upload with preview
- 🔲 Admin edit form (`src/views/admin/AwardEdit.vue`)
- 🔲 Gallery relationship UI
  - Link/unlink gallery photos
  - Display gallery count
- 🔲 Awards store (`src/stores/awards.js`)

**Success Criteria:**
- ✅ Create award with image upload
- ✅ Edit award with image replacement
- ✅ Delete award with confirmation
- ✅ Link/unlink gallery photos
- ✅ Search and filter awards
- ✅ Pagination works correctly

**Routes:**
```
GET    /admin/awards
GET    /admin/awards/:id
POST   /admin/awards
PUT    /admin/awards/:id
DELETE /admin/awards/:id
POST   /admin/awards/:id/galleries
DELETE /admin/awards/:id/galleries/:galleryId
```

**Expected Timeline:** 45-60 minutes

---

## Sprint 3: Gallery Management 🔲

**Objective:** Complete gallery CRUD with bulk upload

**Backend Deliverables:**
- 🔲 GalleryController with bulk operations
- 🔲 Validation rules for multiple image upload
- 🔲 Image optimization/resizing

**Frontend Deliverables:**
- 🔲 Admin list view (`src/views/admin/GalleriesList.vue`)
  - Grid view with thumbnails
  - Search by title, category, tags
  - Bulk selection
  - Pagination
- 🔲 Upload interface (`src/views/admin/GalleryUpload.vue`)
  - Drag & drop multiple files
  - Preview before upload
  - Progress indicators
  - Batch upload
- 🔲 Edit modal/page (`src/views/admin/GalleryEdit.vue`)
  - Title, description, category, tags
  - Replace image
- 🔲 Bulk delete with confirmation
- 🔲 Gallery store (`src/stores/gallery.js`)

**Success Criteria:**
- ✅ Upload single image
- ✅ Upload multiple images (drag-drop)
- ✅ Edit image metadata
- ✅ Delete single image
- ✅ Bulk delete images
- ✅ Search and filter by category/tags
- ✅ Pagination works correctly

**Routes:**
```
GET    /admin/gallery
GET    /admin/gallery/:id
POST   /admin/gallery (single upload)
POST   /admin/gallery/bulk-upload
PUT    /admin/gallery/:id
DELETE /admin/gallery/:id
POST   /admin/gallery/bulk-delete
```

**Expected Timeline:** 60-75 minutes

---

## Sprint 4: Testimonials Management 🔲

**Objective:** Complete testimonials CRUD

**Backend Deliverables:**
- 🔲 TestimonialController (verify exists)
- 🔲 Validation rules
- 🔲 Featured flag logic

**Frontend Deliverables:**
- 🔲 Admin list view (`src/views/admin/TestimonialsList.vue`)
  - Display: client name, company, rating, featured status
  - Search and filters
  - Pagination
- 🔲 Admin create form (`src/views/admin/TestimonialCreate.vue`)
  - Fields: client_name, client_company, client_photo, testimonial_text, star_rating, featured
  - Star rating component
  - Image upload for client photo
- 🔲 Admin edit form (`src/views/admin/TestimonialEdit.vue`)
- 🔲 Testimonials store (`src/stores/testimonials.js`)

**Success Criteria:**
- ✅ Create testimonial with client photo
- ✅ Edit testimonial
- ✅ Delete with confirmation
- ✅ Star rating (1-5) works
- ✅ Featured flag toggle
- ✅ Search and filter
- ✅ Pagination works

**Routes:**
```
GET    /admin/testimonials
GET    /admin/testimonials/:id
POST   /admin/testimonials
PUT    /admin/testimonials/:id
DELETE /admin/testimonials/:id
```

**Expected Timeline:** 45-60 minutes

---

## Sprint 5: Contact Messages 🔲

**Objective:** Complete contact messages management (read-only list)

**Backend Deliverables:**
- 🔲 ContactController admin endpoints
- 🔲 Mark as read/unread logic
- 🔲 Export to CSV functionality

**Frontend Deliverables:**
- 🔲 Admin list view (`src/views/admin/ContactsList.vue`)
  - Display: name, email, subject, date, read status
  - Filters: read/unread, date range
  - Search
  - Pagination
- 🔲 View detail modal
  - Full message display
  - Sender info
  - Mark as read/unread button
- 🔲 Delete with confirmation
- 🔲 Export to CSV button
- 🔲 Contacts store (`src/stores/contacts.js`)

**Success Criteria:**
- ✅ View contact messages list
- ✅ View full message in modal
- ✅ Mark as read/unread
- ✅ Delete message
- ✅ Export to CSV works
- ✅ Filter by read/unread status
- ✅ Search by name, email, subject
- ✅ Pagination works

**Routes:**
```
GET    /admin/contacts
GET    /admin/contacts/:id
PATCH  /admin/contacts/:id/mark-as-read
DELETE /admin/contacts/:id
GET    /admin/contacts/export
```

**Expected Timeline:** 45-60 minutes

---

## Sprint 6: About Settings 🔲

**Objective:** Complete about settings management (single form)

**Backend Deliverables:**
- 🔲 SettingController with about group
- 🔲 Validation rules for complex fields (arrays, JSON)
- 🔲 Update logic for settings

**Frontend Deliverables:**
- 🔲 About settings page (`src/views/admin/AboutSettings.vue`)
  - Rich text editor for bio
  - Dynamic skills array (add/remove)
  - Dynamic experience array (add/remove)
  - Dynamic education array (add/remove)
  - Social links object (key-value pairs)
  - Image upload for profile photo
  - Save/Update button
- 🔲 Settings store (`src/stores/settings.js`)

**Success Criteria:**
- ✅ Edit bio with rich text
- ✅ Add/remove skills dynamically
- ✅ Add/remove experience entries
- ✅ Add/remove education entries
- ✅ Edit social links (add/remove/modify)
- ✅ Upload profile photo
- ✅ Save settings successfully
- ✅ Form validation works

**Routes:**
```
GET  /admin/settings/about
PUT  /admin/settings/about
```

**Expected Timeline:** 60-75 minutes

---

## Sprint 7: Site Settings 🔲

**Objective:** Complete site settings management (single form)

**Backend Deliverables:**
- 🔲 SettingController with site group
- 🔲 Validation rules
- 🔲 Logo upload handling

**Frontend Deliverables:**
- 🔲 Site settings page (`src/views/admin/SiteSettings.vue`)
  - Site name, description
  - Logo upload with preview
  - Contact email, phone
  - Social media links (JSON editor or key-value)
  - Meta tags (JSON editor)
  - Analytics code (textarea)
  - Save/Update button

**Success Criteria:**
- ✅ Edit site basic info
- ✅ Upload/replace logo
- ✅ Edit contact info
- ✅ Edit social media links
- ✅ Edit meta tags
- ✅ Add analytics code
- ✅ Save settings successfully
- ✅ Form validation works

**Routes:**
```
GET  /admin/settings/site
PUT  /admin/settings/site
```

**Expected Timeline:** 45-60 minutes

---

## Sprint 8: Home Hero Section 🔲

**Objective:** Complete home hero section with real data

**Backend Deliverables:**
- ✅ About settings API (already exists)
- ⚠️ Verify response format

**Frontend Deliverables:**
- 🔲 Update Hero component (`src/components/home/Hero.vue`)
  - Pull data from About settings API
  - Display: name, tagline, avatar
  - CTA buttons (link to projects, contact)
  - Animated entrance
  - Responsive design
  - Dark mode support

**Success Criteria:**
- ✅ Display real data from API
- ✅ Avatar/photo displays correctly
- ✅ CTA buttons link correctly
- ✅ Animations work smoothly
- ✅ Responsive on mobile/tablet
- ✅ Dark mode supported

**Expected Timeline:** 30-45 minutes

---

## Sprint 9: About Page 🔲

**Objective:** Complete about page with real data

**Backend Deliverables:**
- ✅ About settings API (already exists)

**Frontend Deliverables:**
- 🔲 Update About page (`src/views/About.vue`)
  - Bio section (rich content rendering)
  - Skills grid with icons
  - Experience timeline
  - Education cards
  - Social links section
  - Pull all data from About settings API
  - Responsive design
  - Dark mode support

**Success Criteria:**
- ✅ Display real bio content
- ✅ Skills grid displays correctly
- ✅ Experience timeline works
- ✅ Education cards display
- ✅ Social links functional
- ✅ Responsive on all devices
- ✅ Dark mode supported

**Expected Timeline:** 45-60 minutes

---

## Sprint 10: Blog Detail Page 🔲

**Objective:** Complete blog detail page with full features

**Backend Deliverables:**
- ✅ PostController show endpoint (already exists)
- ⚠️ Verify related posts logic

**Frontend Deliverables:**
- 🔲 Update BlogDetail page (`src/views/BlogDetail.vue`)
  - Featured image display
  - Rich text content rendering
  - Category badge, publish date, read time
  - Author info section
  - Related posts section (carousel)
  - Social share buttons
  - Comments section placeholder
  - SEO meta tags
  - Responsive design
  - Dark mode support

**Success Criteria:**
- ✅ Display full post content
- ✅ Rich text renders correctly
- ✅ Related posts display
- ✅ Social share works
- ✅ SEO meta tags present
- ✅ Responsive design
- ✅ Dark mode supported

**Expected Timeline:** 45-60 minutes

---

## Sprint 11: Contact Page 🔲

**Objective:** Complete contact page with working form

**Backend Deliverables:**
- ✅ ContactController store endpoint (already exists)
- ⚠️ Verify validation rules
- ⚠️ Test rate limiting

**Frontend Deliverables:**
- 🔲 Update Contact page (`src/views/Contact.vue`)
  - Working form: name, email, subject, message
  - Client-side validation
  - Submit to backend API
  - Success/error toast notifications
  - Contact info display (from site settings)
  - Google Maps embed (optional)
  - Responsive design
  - Dark mode support

**Success Criteria:**
- ✅ Form validates correctly
- ✅ Submit to API works
- ✅ Success toast shows
- ✅ Error handling works
- ✅ Rate limiting works (5/min)
- ✅ Contact info displays
- ✅ Responsive design
- ✅ Dark mode supported

**Expected Timeline:** 45-60 minutes

---

## Sprint Execution Guidelines

### Pre-Sprint Checklist
- [ ] Read PROJECT_STATUS.md for current state
- [ ] Review reference patterns
- [ ] Verify database schema
- [ ] Check existing API endpoints

### Sprint Workflow
1. **Backend First**
   - Controller methods
   - Validation rules
   - API resources
   - Test endpoints via Postman/Insomnia

2. **Frontend Next**
   - Pinia store
   - Admin views/components
   - Form validation
   - API integration

3. **Testing**
   - Manual testing in browser
   - Check console for errors
   - Test all CRUD operations
   - Verify responsive design

4. **Documentation**
   - Update PROJECT_STATUS.md
   - Add completion date
   - Note any blockers

### Post-Sprint Checklist
- [ ] All success criteria met
- [ ] No console errors
- [ ] Forms validate properly
- [ ] Loading states present
- [ ] Error handling works
- [ ] Responsive design verified
- [ ] Dark mode supported
- [ ] PROJECT_STATUS.md updated

---

## Technical Standards

### API Response Format
```json
{
  "success": true,
  "data": {},
  "message": "Operation successful"
}
```

### Pagination Response
```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 50
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

### Form Validation
- Client-side: Real-time with error messages
- Server-side: Laravel Form Requests
- Display: Scroll to first error

### Image Upload
- Max size: 5MB
- Formats: jpg, jpeg, png, gif, webp
- Preview before upload
- Progress indicator

---

## Constraints

### MUST
- Use Filesystem:* tools ONLY (Windows)
- Backend on XAMPP Port 80
- Follow PROJECT_INSTRUCTIONS.md conventions
- Update PROJECT_STATUS.md after each sprint
- No breaking changes to existing features

### DON'T
- Use view/str_replace/bash_tool
- Create test files (test.*, debug.*, dummy.*)
- Skip validation
- Hardcode API URLs

---

## Subagent Strategy

For each sprint, use orchestrator to coordinate:
- **laravel-specialist:** Backend controllers, validation, resources
- **vue-expert:** Frontend components, views, stores
- **database-administrator:** Verify queries, indexes
- **qa-expert:** Test all operations, capture issues
- **documentation-engineer:** Update docs

---

## Current Progress: Sprint 1 ✅

**Projects Management - COMPLETED**
- ✅ Backend API (CRUD endpoints)
- ✅ Form validation (StoreProjectRequest, UpdateProjectRequest)
- ✅ ProjectForm component with image upload
- ✅ ProjectCreate view
- ✅ ProjectEdit view
- ✅ Projects Pinia store
- ✅ Routes configured

**Completion Date:** October 15, 2025

**Next Sprint:** Awards Management

---

**Last Updated:** October 15, 2025  
**Current Sprint:** 1 of 11 (9% Complete)
