# Phase 7: Menu & Section Management + Project CTA
**Sprint:** Menu + Homepage Sections Control + Project CTAs  
**Start Date:** October 16, 2025  
**Target Duration:** 2 sprints (complex cross-domain)  
**Complexity:** High (Database + Backend + Frontend Integration)  

---

## 🎯 Objective

Enable admin to:
1. **Control Navigation Menu** - Aktifin/non-aktifin menu items, reorder di navbar
2. **Control Homepage Sections** - Aktifin/non-aktifin sections, drag-n-drop reorder with preview
3. **Add Project CTAs** - Each project detail shows contextual CTA for contact/inquiry

---

## 📋 Requirements

### Functional Requirements

**Menu Management:**
- ✅ Admin dapat view semua menu items (Home, About, Projects, Awards, Blog, Gallery, Contact)
- ✅ Toggle active/inactive per item
- ✅ Drag-n-drop reorder menu sequence
- ✅ Edit title, url, icon per menu item
- ✅ Support custom external URLs
- ✅ Changes reflect immediately di navbar

**Homepage Sections Management:**
- ✅ Seed initial sections (Hero, Featured Projects, Latest Blog, Testimonials, CTA)
- ✅ Admin dapat toggle active/inactive
- ✅ Drag-n-drop reorder sections
- ✅ **Preview Modal** - show homepage preview dengan active sections hanya
- ✅ Save reorder changes to database
- ✅ Homepage renders dinamis berdasarkan `is_active` & `sequence`

**Project CTA:**
- ✅ Add CTA fields: `cta_title`, `cta_description`, `cta_button_text`, `cta_phone_number` ke projects
- ✅ Admin dapat edit CTA saat create/edit project
- ✅ Project detail page menampilkan CTA section dengan contact info
- ✅ CTA design: professional, clear call-to-action

### Non-Functional Requirements
- ✅ TDD workflow (test first)
- ✅ Drag-n-drop smooth UX (no page refresh)
- ✅ Preview updates real-time
- ✅ Responsive design (mobile-friendly admin pages)
- ✅ Error handling + validation
- ✅ Optimistic UI updates

---

## 🏗️ Architecture

### Backend Structure

```
database/
├── migrations/
│   ├── create_menu_items_table.php
│   ├── create_page_sections_table.php
│   └── add_cta_fields_to_projects_table.php

app/
├── Models/
│   ├── MenuItem.php
│   └── PageSection.php
├── Http/Controllers/Api/
│   ├── MenuItemController.php
│   └── PageSectionController.php
└── Http/Requests/
    ├── StoreMenuItemRequest.php
    ├── UpdateMenuItemRequest.php
    ├── UpdatePageSectionRequest.php
    └── ReorderRequest.php
```

### Frontend Structure

```
src/
├── views/admin/
│   ├── MenuItemsList.vue          # Menu management page
│   └── PageSectionsManager.vue    # Homepage sections manager
├── components/
│   ├── admin/
│   │   ├── DragDropList.vue       # Reusable drag-drop component
│   │   └── SectionPreview.vue     # Preview modal
│   └── project/
│       └── ProjectCTA.vue         # CTA section for project detail
├── composables/
│   ├── useMenuItems.js            # Menu items CRUD + reorder
│   ├── usePageSections.js         # Page sections CRUD + reorder
│   └── useProjectCTA.js           # Project CTA
└── stores/
    └── menuItems.js               # Menu items state (optional - can use API directly)
```

---

## 📊 Database Schema

**SQL File:** `backend/database/sql/phase-7-menu-sections-cta-schema.sql`

### Schema Breakdown:

**menu_items table:**
- id, title, slug (unique), url, icon, is_active, sequence
- Indexes: is_active, sequence
- Seed: 7 default menu items (Home, About, Projects, Awards, Blog, Gallery, Contact)

**page_sections table:**
- id, page_type, section_type, is_active, sequence
- Unique constraint: (page_type, section_type)
- Indexes: is_active, page_type, sequence
- Seed: 5 homepage sections (hero, featured_projects, latest_blog, testimonials, cta)

**projects table (add columns):**
- cta_title, cta_description, cta_button_text, cta_phone_number (all nullable)

---

## 🔌 API Endpoints

### Menu Items Endpoints
```
GET    /api/admin/menu-items                    # List all menu items
POST   /api/admin/menu-items                    # Create new menu item
PUT    /api/admin/menu-items/{id}               # Update menu item
DELETE /api/admin/menu-items/{id}               # Delete menu item
PUT    /api/admin/menu-items/reorder            # Bulk reorder (POST body: [{id, sequence}])

# Public endpoint
GET    /api/menu-items                          # Get active menu items (for navbar)
```

### Page Sections Endpoints
```
GET    /api/admin/page-sections?page=homepage   # List sections for page
PUT    /api/admin/page-sections/{id}            # Update section (toggle active, change sequence)
PUT    /api/admin/page-sections/reorder         # Bulk reorder (POST body: [{id, sequence}])

# Public endpoints
GET    /api/page-sections?page=homepage         # Get active homepage sections (for rendering)
```

### Project Endpoints (updated)
```
GET    /api/projects/{slug}                     # Include cta_* fields
PUT    /api/admin/projects/{id}                 # Can update cta_* fields
```

---

## 👥 SPECIALIST TASK BREAKDOWN

**Subagents Involved:**
- @laravel-specialist.md - Backend API
- @vue-expert.md - Frontend admin pages
- @qa-expert.md - Testing & quality
- @documentation-engineer.md - API & component docs

---

### 🔵 Laravel Specialist - Backend API Implementation

**Subagent:** @laravel-specialist.md

**Primary Responsibility:** Create complete backend API for menu, sections, and project CTA management

**Tasks:**

#### 1. Models (2 hours)
- [ ] Create `MenuItem.php` model
  - Fillable: title, slug, url, icon, is_active, sequence
  - Cast: is_active (boolean)
  - Scopes: `active()`, `ordered()`
  - Methods: none required
  
- [ ] Create `PageSection.php` model
  - Fillable: page_type, section_type, is_active, sequence
  - Cast: is_active (boolean)
  - Scopes: `forPage($page)`, `active()`, `ordered()`
  - Methods: none required

- [ ] Update `Project.php` model
  - Add to fillable: cta_title, cta_description, cta_button_text, cta_phone_number
  - No casts needed

#### 2. Controllers (4 hours)
- [ ] Create `MenuItemController.php`
  - `index()` - List all menu items with auth
  - `store(StoreMenuItemRequest)` - Create menu item
  - `update(UpdateMenuItemRequest, $id)` - Update menu item
  - `destroy($id)` - Delete menu item
  - `reorder(ReorderRequest)` - Bulk reorder (accept array of [{id, sequence}])
  - Add public `publicMenuItems()` method for navbar (GET `/api/menu-items`)
  
- [ ] Create `PageSectionController.php`
  - `index($page)` - List sections for page with auth (?page=homepage)
  - `update(UpdatePageSectionRequest, $id)` - Toggle active/inactive
  - `reorder(ReorderRequest)` - Bulk reorder sections
  - Add public `publicSections($page)` method (GET `/api/page-sections?page=homepage`)

- [ ] Update `ProjectController.php`
  - Include CTA fields in `show()` response (public)
  - Include CTA fields in `update()` (admin)
  - Follow existing pattern from show/update methods

#### 3. Form Requests (1.5 hours)
- [ ] Create `StoreMenuItemRequest.php`
  - Validate: title (required, max 100), slug (required, unique), url (required, max 255), icon (nullable), sequence (nullable, integer)
  
- [ ] Create `UpdateMenuItemRequest.php`
  - Same as Store but slug unique except current item
  - Validate is_active (boolean)
  
- [ ] Create `UpdatePageSectionRequest.php`
  - Validate: is_active (boolean), sequence (integer)
  
- [ ] Create `ReorderRequest.php`
  - Validate: items array with id and sequence fields

#### 4. API Resources (1 hour)
- [ ] Create `MenuItemResource.php`
  - Return: id, title, slug, url, icon, is_active, sequence
  
- [ ] Create `PageSectionResource.php`
  - Return: id, page_type, section_type, is_active, sequence

#### 5. Routes (1 hour)
- [ ] Add to `routes/api.php`:
  ```php
  // Public menu items
  Route::get('/menu-items', [MenuItemController::class, 'publicMenuItems']);
  Route::get('/page-sections', [PageSectionController::class, 'publicSections']);
  
  // Admin menu items
  Route::middleware(['auth:sanctum'])->prefix('admin/menu-items')->group(function () {
      Route::get('/', [MenuItemController::class, 'index']);
      Route::post('/', [MenuItemController::class, 'store']);
      Route::put('/{id}', [MenuItemController::class, 'update']);
      Route::delete('/{id}', [MenuItemController::class, 'destroy']);
      Route::put('/reorder', [MenuItemController::class, 'reorder']);
  });
  
  // Admin page sections
  Route::middleware(['auth:sanctum'])->prefix('admin/page-sections')->group(function () {
      Route::get('/', [PageSectionController::class, 'index']);
      Route::put('/{id}', [PageSectionController::class, 'update']);
      Route::put('/reorder', [PageSectionController::class, 'reorder']);
  });
  ```

#### 6. Tests (3 hours)
- [ ] Create `tests/Feature/MenuItemTest.php` - TDD approach
  - test('can list menu items')
  - test('can create menu item')
  - test('can update menu item')
  - test('can delete menu item')
  - test('can reorder menu items')
  - test('public menu items only returns active')
  
- [ ] Create `tests/Feature/PageSectionTest.php`
  - test('can list page sections')
  - test('can toggle section active/inactive')
  - test('can reorder sections')
  - test('public sections only returns active')
  
- [ ] Update `tests/Feature/ProjectTest.php`
  - Add test('project includes cta fields')
  - Add test('can update project cta fields')

**Success Criteria:**
- ✅ All CRUD endpoints work correctly
- ✅ Reorder endpoints update sequence in database
- ✅ Public endpoints return only active items/sections
- ✅ All tests pass (green)
- ✅ 80%+ code coverage
- ✅ No console errors

**Reference:** Follow `AwardController` pattern for consistency

---

### 🟢 Vue Expert - Frontend Admin Pages & Components

**Subagent:** @vue-expert.md

**Primary Responsibility:** Create admin UI for menu/section management and integrate into homepage

**Tasks:**

#### 1. Reusable Components (3 hours)
- [ ] Create `DragDropList.vue` component
  - Props: items (array), itemKey (string), sequence-key (string)
  - Events: reorder (emit array of reordered items)
  - Uses SortableJS library
  - Smooth animations
  - Fallback for non-touch devices
  - Mobile-friendly drag handles
  
- [ ] Create `SectionPreview.vue` modal component
  - Props: sections (array), activeOnly (boolean)
  - Display homepage preview
  - Show only active sections in correct sequence
  - Responsive breakpoints (mobile/desktop)
  - Update real-time when parent changes sections

#### 2. Admin Pages (5 hours)
- [ ] Create `MenuItemsList.vue` page
  - Table layout: Title | URL | Icon | Active (toggle) | Sequence | Actions
  - Drag-drop rows to reorder
  - Edit/Delete buttons
  - Create new menu item form/modal
  - Save reorder changes to backend
  - Real-time sync feedback
  - Validation error display
  - Success/error toasts
  
- [ ] Create `PageSectionsManager.vue` page
  - Table layout: Section Type | Active (toggle) | Sequence | Preview
  - Drag-drop rows to reorder
  - Toggle active/inactive with instant feedback
  - **Preview Button** - Opens SectionPreview modal
  - Save changes button
  - Real-time preview updates
  - Success/error toasts
  - Show current sequence numbers

#### 3. Project Component (1.5 hours)
- [ ] Create `ProjectCTA.vue` component
  - Props: project (object with cta_* fields)
  - Display CTA section if data exists
  - Card design with title, description, button
  - Button links to contact (phone or email)
  - Mobile responsive
  - Professional styling
  - Gracefully hide if no CTA data

#### 4. Composables (2 hours)
- [ ] Create `useMenuItems.js` composable
  - Methods: getMenuItems(), createMenuItem(), updateMenuItem(), deleteMenuItem(), reorderMenuItems()
  - State: menu items, loading, error
  - Handle API calls to `/api/admin/menu-items`
  
- [ ] Create `usePageSections.js` composable
  - Methods: getSections(), updateSection(), reorderSections()
  - State: sections, loading, error
  - Handle API calls to `/api/admin/page-sections`
  - Support filtering by page_type

#### 5. Pages Integration (2 hours)
- [ ] Update `DefaultLayout.vue` (navbar)
  - Fetch active menu items from `/api/menu-items`
  - Render navbar dynamically
  - Filter out inactive items
  - Order by sequence
  - Support icons if provided
  
- [ ] Update `Home.vue` (homepage)
  - Fetch active sections from `/api/page-sections?page=homepage`
  - Render sections in sequence order
  - Only render active sections
  - Render in correct order: Hero → Featured Projects → Latest Blog → Testimonials → CTA
  - Fallback if all sections inactive (show message or default)

#### 6. Router Configuration (1 hour)
- [ ] Update `router/index.js`
  - Add route: `/admin/menu-items` → MenuItemsList
  - Add route: `/admin/page-sections` → PageSectionsManager
  - Both protected by auth middleware

#### 7. Tests (2 hours)
- [ ] Create `tests/e2e/MenuManagement.spec.js` (Playwright)
  - Load MenuItemsList page
  - Toggle menu item active/inactive
  - Drag-drop reorder items
  - Verify changes persist after reload
  
- [ ] Create `tests/e2e/SectionsManager.spec.js` (Playwright)
  - Load PageSectionsManager
  - Toggle sections active/inactive
  - Drag-drop reorder sections
  - Click preview button
  - Verify preview shows active sections only
  - Close preview
  - Save changes
  - Verify homepage renders updated order

**Success Criteria:**
- ✅ Admin pages load without errors
- ✅ Drag-drop works smoothly
- ✅ Preview modal updates real-time
- ✅ Changes persist after page reload
- ✅ Homepage renders sections dynamically
- ✅ Navbar shows only active menu items
- ✅ Mobile-friendly UI
- ✅ All tests pass

**References:** Check existing components in `src/components/admin/` for patterns

---

### 🟨 QA Expert - Testing & Quality Assurance

**Subagent:** @qa-expert.md

**Primary Responsibility:** Comprehensive testing with screenshots and verification

**Tasks:**

#### 1. Backend Feature Tests (3 hours)
- [ ] Run TDD workflow:
  1. Read existing test patterns (e.g., AwardControllerTest)
  2. Write tests FIRST (red phase)
  3. Laravel Specialist implements code (green phase)
  4. Refactor if needed (refactor phase)

- [ ] Test Menu Items API
  - List, create, update, delete operations
  - Reorder functionality
  - Public endpoint (active only)
  - Validation errors
  - Unauthorized access blocked
  
- [ ] Test Page Sections API
  - Toggle active/inactive
  - Reorder functionality
  - Public endpoint (active only)
  - Page type filtering
  
- [ ] Test Project CTA Fields
  - CTA fields included in responses
  - CTA fields updateable
  - Graceful handling when NULL

#### 2. Frontend Browser Tests (3 hours)
- [ ] Playwright tests for Menu Management
  - Open MenuItemsList page
  - Verify table renders correctly
  - Toggle menu item active → verify in table + API call
  - Drag-drop item → verify new order + persists
  - Delete item → verify removed
  - Create new item → verify appears in list
  - Test on mobile viewport
  - Capture screenshots for each action
  
- [ ] Playwright tests for Sections Manager
  - Open PageSectionsManager
  - Verify all 5 sections visible
  - Toggle section active → verify visual feedback + API call
  - Drag-drop reorder → verify new sequence
  - Click preview button → verify modal opens
  - Verify preview shows active sections only in correct order
  - Toggle sections while preview open → verify preview updates real-time
  - Close preview → verify modal closes
  - Save changes → verify persists
  - Test on mobile viewport
  - Capture screenshots for each action
  
- [ ] Playwright tests for Homepage Integration
  - Visit homepage
  - Verify active sections only appear
  - Verify sections in correct sequence order
  - Check navbar shows only active menu items
  - Check navbar items in correct order
  - Test after changing menu/sections in admin
  - Verify no console errors
  - Test on multiple viewports

#### 3. Integration Tests (2 hours)
- [ ] End-to-end flows
  - Admin toggles section inactive → homepage hides it
  - Admin reorders sections → homepage reflects new order
  - Admin hides menu item → navbar doesn't show it
  - Admin creates new menu item → appears in navbar
  - Project with CTA → project detail shows CTA

#### 4. Performance Testing (1 hour)
- [ ] Verify performance requirements:
  - Menu items load < 200ms
  - Sections reorder without page refresh
  - Preview modal renders < 500ms
  - Homepage loads with new sections < 1s
  - Benchmark before/after optimization

#### 5. Accessibility Testing (1 hour)
- [ ] Check accessibility:
  - Keyboard navigation in tables
  - ARIA labels for buttons
  - Focus indicators on drag-drop
  - Mobile touch targets adequate
  - Color contrast for active/inactive states

#### 6. Error Handling Testing (1 hour)
- [ ] Test error scenarios:
  - Delete menu item used in current page
  - Reorder with network error
  - Update section with bad data
  - Concurrent edits
  - Network timeout scenarios

**Success Criteria:**
- ✅ All backend tests pass (green)
- ✅ All frontend tests pass (green)
- ✅ 80%+ code coverage
- ✅ Screenshots captured for all CRUD operations
- ✅ No console errors or warnings
- ✅ Performance benchmarks met
- ✅ Accessibility standards met

**Test Files Location:** Backend: `tests/Feature/`, Frontend: `tests/e2e/`

---

### ⚪ Documentation Engineer - API & Component Documentation

**Subagent:** @documentation-engineer.md

**Primary Responsibility:** Complete documentation for API, components, and integration

**Tasks:**

#### 1. API Documentation (2 hours)
- [ ] Document Menu Items endpoints
  - GET `/api/menu-items` - Public active menu items
  - GET `/api/admin/menu-items` - Admin list all
  - POST `/api/admin/menu-items` - Create with request example
  - PUT `/api/admin/menu-items/{id}` - Update with example
  - DELETE `/api/admin/menu-items/{id}` - Delete
  - PUT `/api/admin/menu-items/reorder` - Reorder with body example
  
- [ ] Document Page Sections endpoints
  - GET `/api/page-sections?page=homepage` - Public
  - GET `/api/admin/page-sections` - Admin
  - PUT `/api/admin/page-sections/{id}` - Update
  - PUT `/api/admin/page-sections/reorder` - Reorder
  
- [ ] Document response formats
  - MenuItemResource structure
  - PageSectionResource structure
  - Error responses

#### 2. Component Documentation (1.5 hours)
- [ ] Document Vue components
  - DragDropList: props, events, usage example
  - SectionPreview: props, modal behavior, responsive behavior
  - MenuItemsList: features, CRUD operations
  - PageSectionsManager: features, drag-drop, preview
  - ProjectCTA: props, styling, mobile behavior
  
- [ ] Document composables
  - useMenuItems methods and usage
  - usePageSections methods and usage

#### 3. Integration Documentation (1 hour)
- [ ] Document how features work together
  - Admin manages menu → navbar updates
  - Admin manages sections → homepage updates
  - Project with CTA → appears on project detail
  - Dynamic rendering based on active/sequence
  
- [ ] Add architecture diagrams if needed

#### 4. README Updates (1 hour)
- [ ] Update `backend/README.md`
  - Add Menu Items section
  - Add Page Sections section
  - Document seeding process
  
- [ ] Update `frontend/README.md`
  - Add admin pages documentation
  - Add component documentation
  - Document drag-drop behavior
  
- [ ] Update `PROJECT_STATUS.md`
  - Mark Phase 7 as completed
  - Update completion percentages
  - Document next steps

#### 5. Developer Guide (1 hour)
- [ ] Create `MENU_SECTION_GUIDE.md`
  - How to add new menu items programmatically
  - How to add new homepage sections
  - How to customize drag-drop behavior
  - How to modify section rendering logic
  - Performance considerations
  - Troubleshooting guide

**Success Criteria:**
- ✅ All API endpoints documented with examples
- ✅ All components documented with props/events
- ✅ README files updated
- ✅ Architecture is clear and understandable
- ✅ Developer can extend features without questions

---

## 🎯 Integration Checkpoints

**After Each Sprint:**

### Sprint 1 End (Database + Backend):
- ✅ All migrations run successfully
- ✅ All seeders populate correct data
- ✅ All backend tests pass (green)
- ✅ All API endpoints work in Postman/Insomnia
- ✅ Backend code follows conventions

### Sprint 2 End (Frontend + Integration):
- ✅ Admin pages load without errors
- ✅ Drag-drop works smoothly
- ✅ Preview modal updates real-time
- ✅ Homepage renders dynamically
- ✅ Navbar shows active items only
- ✅ All frontend tests pass (green)
- ✅ No console errors
- ✅ Mobile-friendly design
- ✅ Project detail shows CTA
- ✅ Documentation complete

---

## ✅ Success Criteria

**Functionality:**
- [ ] Admin dapat manage menu items (create, read, update, delete, reorder)
- [ ] Admin dapat manage homepage sections (toggle, reorder)
- [ ] Preview modal menampilkan homepage real-time
- [ ] Menu navbar reflects active items hanya
- [ ] Homepage renders sections dinamis based on `is_active` & `sequence`
- [ ] Project detail menampilkan CTA section
- [ ] All CRUD operations persist to database

**Quality:**
- [ ] 80%+ test coverage (backend)
- [ ] All tests green
- [ ] No console errors
- [ ] Responsive design (mobile, tablet, desktop)
- [ ] Smooth drag-drop UX
- [ ] Error handling + user feedback

**Performance:**
- [ ] Menu items load < 200ms
- [ ] Sections reorder without page refresh
- [ ] Preview modal renders < 500ms

---

## 🚀 Implementation Notes

### Coordination Between Specialists
- **@laravel-specialist.md** starts: Immediately (Models, Controllers, Routes + Tests)
  - Uses pre-seeded database from SQL schema
  - Implement complete backend API (3-4 hours)
- **@qa-expert.md** starts: TDD in parallel with Backend (3-4 hours)
  - Write tests first, then coordinate with Laravel Specialist
- **@vue-expert.md** starts: Once API endpoints working (5 hours)
  - Admin pages + Components + Integration
- **@qa-expert.md** continues: Frontend tests after components ready (3 hours)
- **@documentation-engineer.md** finalizes: After all code complete (5 hours)

### Key Considerations
1. **Menu ordering**: Use `sequence` field, starts from 0
2. **Drag-drop**: Can use `@vueuse/core` or `SortableJS`
3. **Preview**: Show only active sections in correct order
4. **Seeding**: Create default menu items & sections on migration
5. **Project CTA**: Optional fields (can be NULL), show only if has data

### Potential Challenges
- Real-time sync between manager & preview
- Drag-drop library compatibility with Vue 3
- Mobile drag-drop UX
- Database ordering consistency

### Fallbacks
- If drag-drop fails: Use manual input fields for sequence
- If preview modal too complex: Link to homepage instead
- If mobile drag-drop bad UX: Desktop-only drag-drop

---

## 📅 Timeline Estimate

**Sprint 1 (Backend API):**
- @laravel-specialist.md: Models + Controllers + Routes + Tests (8-10 hours)
- @qa-expert.md: TDD coordination + Backend tests (3-4 hours)
- **Total: ~12-14 hours**
- *(Database schema pre-seeded via SQL script)*

**Sprint 2 (Frontend + Integration):**
- @vue-expert.md: Admin pages + Components + Integration (13-16 hours)
- @qa-expert.md: Frontend tests + Integration tests (3 hours)
- @documentation-engineer.md: API & component docs + READMEs (5 hours)
- **Total: ~21-24 hours**

**Total: 2 Sprints (~35-40 hours)**

---

## 🔗 References

- [Vue 3 Drag & Drop with SortableJS](https://www.npmjs.com/package/sortablejs)
- [Laravel Model Relationships](https://laravel.com/docs/10.x/eloquent-relationships)
- [API Resource Design](https://laravel.com/docs/10.x/eloquent-resources)
- [Playwright Testing](https://playwright.dev/)

---

## 📝 Notes

- **CTA in Projects:** Optional fields, graceful degradation if empty
- **Menu Seeder:** Seeds 7 default items automatically on migration
- **Preview Performance:** Consider memoization for preview calculations
- **Drag-drop:** Test on mobile browsers (iOS Safari, Chrome Mobile)
- **Database Consistency:** Use unique constraint on (page_type, section_type)

---

**Created:** October 16, 2025  
**Status:** Ready for Specialist Coordination  
**Next Steps:**  
1. **SQL Execution:** Run `backend/database/sql/execute-phase7.bat` or `.ps1`
2. **@laravel-specialist.md:** Create models, controllers, API routes
3. **@qa-expert.md:** Write tests in parallel (TDD approach)
4. **@vue-expert.md:** Build admin pages & components (once API ready)
5. **@documentation-engineer.md:** Finalize docs (after all code complete)
