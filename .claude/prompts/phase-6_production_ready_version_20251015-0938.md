# Complete Portfolio v2 - Production Ready

## Objective
Transform Portfolio v2 from skeleton to production-ready application. Complete all placeholder admin CRUD interfaces and frontend public pages. All features must be functional, tested, and integrated.

## Current State
- Database: ✅ Complete (migrations, seeders working)
- Backend API: ⚠️ Partial (AwardController done, need 7 more)
- Admin Panel: ⚠️ Skeleton only (PostCreate/Edit done, rest placeholder)
- Frontend Public: ⚠️ Placeholders (Home hero, About, BlogDetail, Contact)

## Architecture
**Stack:** Laravel 10 API + Vue 3 SPA + MySQL 8 + Tailwind CSS  
**Backend:** http://localhost/Portfolio_v2/backend/public/api (XAMPP Port 80)  
**Frontend:** http://localhost:5173 (Vite)  
**Database:** portfolio_v2 (user: ali, pass: aL1889900@@@)

## Deliverables

### Part 1: Admin Panel CRUD (Priority 1)
Complete working CRUD interfaces for:

1. **Projects Management**
   - List with search, filters, pagination
   - Create form: title, description, featured_image, technologies[], client, project_url, github_url, status, featured flag
   - Edit form (same fields)
   - Delete with confirmation
   - Image upload with preview

2. **Awards Management**
   - List with search, filters, pagination
   - Create form: award_title, issuing_organization, award_date, credential_id, credential_url, description, image upload
   - Edit form (same fields)
   - Delete with confirmation
   - Gallery photos relationship (display count, link to gallery)

3. **Gallery Management**
   - List with thumbnails, search, filters
   - Upload multiple images interface
   - Edit: title, description, category, tags
   - Delete with confirmation
   - Drag-drop upload support

4. **Testimonials Management**
   - List with search, filters, pagination
   - Create form: client_name, client_company, client_photo, testimonial_text, star_rating (1-5), featured flag
   - Edit form (same fields)
   - Delete with confirmation

5. **Contact Messages**
   - Read-only list with filters (read/unread, date range)
   - View detail modal
   - Mark as read/unread
   - Delete with confirmation
   - Export to CSV

6. **About Settings**
   - Single form: bio, skills[], experience[], education[], social_links{}
   - Rich text editor for bio
   - Dynamic fields for skills/experience/education
   - Save/Update

7. **Site Settings**
   - Single form: site_name, site_description, logo, contact_email, social_media{}, meta_tags{}, analytics_code
   - Image upload for logo
   - JSON editor for complex fields

### Part 2: Frontend Public Pages (Priority 2)

1. **Home Hero Section**
   - Display: name, tagline, CTA buttons, avatar/photo
   - Pull data from About settings
   - Animated entrance
   - Responsive design

2. **About Page**
   - Bio section with rich content
   - Skills grid with icons
   - Experience timeline
   - Education cards
   - Social links
   - Pull from About settings API

3. **Blog Detail Page**
   - Full post content with rich text rendering
   - Featured image display
   - Category badge, publish date, read time
   - Author info
   - Related posts section
   - Social share buttons
   - Comments section (placeholder for now)
   - SEO meta tags

4. **Contact Page**
   - Working form: name, email, subject, message
   - Form validation
   - Submit to backend API
   - Success/error toast notifications
   - Contact info display (email, phone from settings)
   - Google Maps embed (optional)

## Success Criteria

**Functional:**
- ✅ All admin CRUD operations work end-to-end
- ✅ All public pages display real data from API
- ✅ Forms validate properly (client + server side)
- ✅ Image uploads work with preview
- ✅ Search, filters, pagination functional
- ✅ No placeholder content remains

**Technical:**
- ✅ Follow existing patterns (AwardController, PostCreate.vue)
- ✅ API responses match standard format (success/data/message)
- ✅ Use existing components (BaseButton, BaseCard, BaseInput, etc.)
- ✅ Pinia stores for state management
- ✅ Vue Router integrated
- ✅ Responsive design (mobile-first)
- ✅ Dark mode support throughout

**Quality:**
- ✅ Laravel feature tests for all endpoints
- ✅ No console errors
- ✅ Loading states during API calls
- ✅ Error handling with user-friendly messages
- ✅ Toast notifications for actions

## Reference Patterns

**Backend (Follow these):**
- Controller: `C:\xampp\htdocs\Portfolio_v2\backend\app\Http\Controllers\Api\AwardController.php`
- Validation: `C:\xampp\htdocs\Portfolio_v2\backend\app\Http\Requests\StorePostRequest.php`
- Resource: `C:\xampp\htdocs\Portfolio_v2\backend\app\Http\Resources\PostResource.php`
- Model: `C:\xampp\htdocs\Portfolio_v2\backend\app\Models\Award.php`

**Frontend (Follow these):**
- Admin Form: `C:\xampp\htdocs\Portfolio_v2\frontend\src\views\admin\PostCreate.vue`
- List View: `C:\xampp\htdocs\Portfolio_v2\frontend\src\views\admin\PostsList.vue`
- Components: `C:\xampp\htdocs\Portfolio_v2\frontend\src\components\blog\BlogPostForm.vue`
- Store: `C:\xampp\htdocs\Portfolio_v2\frontend\src\stores\posts.js`

## Integration Points

**Backend → Frontend:**
- All APIs return JSON: `{success: bool, data: object, message: string}`
- Use Laravel Resources for response transformation
- Image uploads return storage path
- Pagination: `{data: [], current_page, last_page, per_page, total}`

**Frontend → Backend:**
- Axios instance in `src/services/api.js`
- Auth token in headers: `Authorization: Bearer {token}`
- FormData for file uploads
- Pinia stores handle API calls

## Constraints

**MUST:**
- Use Filesystem:* tools ONLY (Windows environment)
- Backend on XAMPP Port 80 (NOT php artisan serve)
- Follow PROJECT_INSTRUCTIONS.md conventions
- Update PROJECT_STATUS.md after completion
- No breaking changes to existing working features

**DON'T:**
- Use view/str_replace/bash_tool (use Filesystem:*)
- Create test files (test.*, debug.*, dummy.*)
- Skip validation (client + server)
- Hardcode API URLs (use env variables)

## Execution Strategy

**Use orchestrator + subagents:**
1. Read PROJECT_STATUS.md for current state
2. laravel-specialist: Complete backend controllers/validation/resources
3. database-administrator: Verify indexes, optimize queries
4. vue-expert: Build all admin/public components
5. qa-expert: Test all CRUD flows, capture screenshots
6. documentation-engineer: Update README, PROJECT_STATUS

**Verification checklist:**
- All CRUD operations work via browser
- Public pages display real data
- No 404 errors
- No console errors
- Forms validate properly
- Images upload successfully

## Context Files

**Read these FIRST:**
- `C:\xampp\htdocs\Portfolio_v2\README.md`
- `C:\xampp\htdocs\Portfolio_v2\PROJECT_STATUS.md`
- `C:\xampp\htdocs\Portfolio_v2\PROJECT_INSTRUCTIONS.md`
- `C:\xampp\htdocs\Portfolio_v2\.claude\agents\orchestrator.md`

## Expected Timeline
1 hour autonomous execution (hands-off)

## Deliverable Format
When complete:
1. Summary of changes (files created/modified)
2. Updated PROJECT_STATUS.md with new completion %
3. Test execution results
4. Screenshots of key features
5. Any blockers encountered

---

**GO: Complete Portfolio v2 to production-ready state.**