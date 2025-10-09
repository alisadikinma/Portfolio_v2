# MASTER ORCHESTRATION PROMPT - Portfolio V2 Complete Implementation

**Orchestration Agent:** `workflow-orchestrator` + `multi-agent-coordinator`  
**Project:** Portfolio V2 - Complete Build (Backend + Frontend)  
**Duration:** 6-8 weeks  
**Complexity:** High (7 sequential prompts with dependencies)

---

## 🎯 MISSION OBJECTIVE

Orchestrate and coordinate the **complete implementation** of Portfolio V2 project by executing 7 prompts in sequence, managing dependencies, tracking progress, and ensuring quality at each stage.

---

## 📋 PROJECT CONTEXT

### Current Status
- ✅ Backend structure: 100% ready (Laravel, database, models)
- ⚠️ API endpoints: 15% complete (only Awards partial)
- ❌ Frontend: 5% complete (basic Vue skeleton only)
- ❌ Components: 0% built (0 of 50+ components)

### Target State
- ✅ Complete RESTful API with i18n support
- ✅ Full Vue 3 frontend with design system
- ✅ 50+ production-ready components
- ✅ Admin panel with CRUD functionality
- ✅ Responsive, accessible, performant application

### Project Structure
```
C:\xampp\htdocs\Portfolio_v2\
├── backend/          (Laravel 11)
├── frontend/         (Vue 3 + Vite)
└── documentation/    (C:\Users\ali.sadikin\.claude\agents\output)
```

---

## 🗺️ ORCHESTRATION WORKFLOW

### Phase Overview

```
PHASE 1: Backend API (Week 1)
  ├─ Prompt #1: Projects API
  ├─ Prompt #2: Blog/Posts API
  └─ Prompt #3: Gallery & Contact API
      │
      └─ CHECKPOINT: All APIs tested ✓
          │
PHASE 2: Frontend Foundation (Week 2)
  ├─ Prompt #4: Tailwind Setup
  ├─ Prompt #5: Core UI Components
  ├─ Prompt #6: Pinia State Management
  └─ Prompt #7: Vue Router Setup
      │
      └─ CHECKPOINT: Foundation ready ✓
          │
PHASE 3-5: Remaining Implementation (Weeks 3-8)
  └─ Follow IMPLEMENTATION_ROADMAP.md
```

---

## 📝 DETAILED EXECUTION PLAN

### WEEK 1: Backend API Completion (PHASE 1)

#### PROMPT #1: Projects API Implementation
**File:** `CLAUDE_CODE_PROMPTS_BACKEND.md` (Prompt #1)  
**Subagent:** `backend-developer` + `laravel-specialist`  
**Duration:** 2 days  
**Priority:** 🔴 CRITICAL

**Prerequisites:**
- Laravel backend ready
- Database migrations complete
- Project & ProjectTranslation models exist

**Tasks:**
1. Create ProjectController with full CRUD
2. Create ProjectResource with i18n support
3. Implement public endpoints:
   - `GET /api/projects` (pagination, filters, i18n)
   - `GET /api/projects/{slug}` (translations)
4. Implement admin endpoints:
   - `POST /api/admin/projects`
   - `PUT /api/admin/projects/{id}`
   - `DELETE /api/admin/projects/{id}`
5. Add routes to `routes/api.php`
6. Test all endpoints

**Deliverables:**
- [ ] ProjectController.php
- [ ] ProjectResource.php
- [ ] ProjectTranslation model (if not exists)
- [ ] Updated routes/api.php
- [ ] API test results (screenshots/output)

**Quality Checks:**
- [ ] All CRUD operations work
- [ ] i18n support works (EN/ID)
- [ ] Pagination works correctly
- [ ] Filtering and search work
- [ ] Validation rules enforced
- [ ] Error handling proper (404, 422, 500)

**Success Criteria:**
```bash
# Test commands that should work:
GET http://localhost/Portfolio_v2/backend/public/api/projects
GET http://localhost/Portfolio_v2/backend/public/api/projects?lang=id
GET http://localhost/Portfolio_v2/backend/public/api/projects?category=web-development
GET http://localhost/Portfolio_v2/backend/public/api/projects/ecommerce-platform
POST http://localhost/Portfolio_v2/backend/public/api/admin/projects (with auth)
```

**Blockers:**
- If ProjectTranslation model missing → Create it first
- If relationships broken → Fix model relationships

**Next Step Dependencies:**
→ Prompt #2 requires this API pattern as reference

---

#### PROMPT #2: Blog/Posts API Implementation
**File:** `CLAUDE_CODE_PROMPTS_BACKEND.md` (Prompt #2)  
**Subagent:** `backend-developer` + `laravel-specialist`  
**Duration:** 2 days  
**Priority:** 🔴 CRITICAL

**Prerequisites:**
- ✅ Prompt #1 completed (use as reference pattern)
- Post & Category models ready
- PostTranslation model ready

**Tasks:**
1. Create PostController with full CRUD
2. Create CategoryController with CRUD
3. Create PostResource with i18n support
4. Implement public endpoints:
   - `GET /api/posts` (pagination, filters, tags, i18n)
   - `GET /api/posts/{slug}` (full content with translations)
   - `GET /api/categories`
5. Implement admin endpoints:
   - Full CRUD for posts and categories
6. Add routes
7. Test all endpoints

**Deliverables:**
- [ ] PostController.php
- [ ] CategoryController.php
- [ ] PostResource.php
- [ ] CategoryResource.php
- [ ] PostTranslation model (if not exists)
- [ ] Updated routes
- [ ] API test results

**Quality Checks:**
- [ ] All CRUD operations work
- [ ] i18n support works
- [ ] Category filtering works
- [ ] Tag filtering works
- [ ] Search functionality works
- [ ] Read time calculation accurate
- [ ] Rich text content handled properly

**Success Criteria:**
```bash
GET http://localhost/Portfolio_v2/backend/public/api/posts
GET http://localhost/Portfolio_v2/backend/public/api/posts?lang=id&category=tutorials
GET http://localhost/Portfolio_v2/backend/public/api/posts/getting-started-vue3
GET http://localhost/Portfolio_v2/backend/public/api/categories
```

**Next Step Dependencies:**
→ Frontend can start after this (Prompt #4)

---

#### PROMPT #3: Gallery & Contact API Implementation
**File:** `CLAUDE_CODE_PROMPTS_BACKEND.md` (Prompt #3)  
**Subagent:** `backend-developer`  
**Duration:** 1 day  
**Priority:** 🔴 CRITICAL

**Prerequisites:**
- ✅ Prompt #1 & #2 completed (pattern established)
- Gallery & Contact models ready

**Tasks:**
1. Complete GalleryController:
   - `GET /api/gallery` (pagination, filters)
   - `POST /api/admin/gallery/upload` (bulk upload)
   - `PUT /api/admin/gallery/{id}` (metadata update)
   - `DELETE /api/admin/gallery/{id}`
   - `POST /api/admin/gallery/bulk-delete`
2. Create ContactController:
   - `POST /api/contact` (with validation)
   - Email notification setup
3. Implement file upload handling
4. Generate thumbnails for images
5. Test all endpoints

**Deliverables:**
- [ ] GalleryController.php
- [ ] ContactController.php
- [ ] Image upload & thumbnail generation working
- [ ] Email notifications configured
- [ ] API test results

**Quality Checks:**
- [ ] Bulk upload works (multiple files)
- [ ] Thumbnails generated correctly
- [ ] Image validation works (type, size)
- [ ] Contact form validation works
- [ ] Email notifications sent
- [ ] Bulk delete works

**Success Criteria:**
```bash
GET http://localhost/Portfolio_v2/backend/public/api/gallery
POST http://localhost/Portfolio_v2/backend/public/api/admin/gallery/upload (multipart)
POST http://localhost/Portfolio_v2/backend/public/api/contact (form data)
```

**PHASE 1 CHECKPOINT:**
✅ All API endpoints complete and tested  
✅ Backend ready for frontend integration  
→ Proceed to Phase 2

---

### WEEK 2: Frontend Foundation (PHASE 2)

#### PROMPT #4: Tailwind CSS Configuration
**File:** `CLAUDE_CODE_PROMPTS_FRONTEND.md` (Prompt #4)  
**Subagent:** `frontend-developer` + `dx-optimizer`  
**Duration:** 2 days  
**Priority:** 🔴 CRITICAL

**Prerequisites:**
- Frontend Vue project exists
- npm/pnpm available

**Tasks:**
1. Install Tailwind CSS and plugins
2. Configure tailwind.config.js with design tokens
3. Setup base CSS with component classes
4. Configure dark mode (class strategy)
5. Install Inter font
6. Create dark mode composable
7. Create test page with design system showcase

**Deliverables:**
- [ ] tailwind.config.js (with full color palette)
- [ ] src/style.css (base styles + utilities)
- [ ] src/composables/useDarkMode.js
- [ ] Test page showing all design tokens
- [ ] Screenshot of design system in action
- [ ] Dark mode toggle working

**Quality Checks:**
- [ ] All colors from design system available
- [ ] Typography scales work correctly
- [ ] Spacing utilities work
- [ ] Dark mode toggle works
- [ ] Custom animations work
- [ ] Glassmorphic effects work
- [ ] Component utility classes work (.btn-primary, .card, etc.)

**Success Criteria:**
- Run `npm run dev` successfully
- Design system test page loads
- All Tailwind classes working
- Dark mode toggle functional
- No CSS errors in console

**Reference Files:**
- MASTER_DESIGN_SYSTEM.md (Section 1-20)
- MASTER_DESIGN_SYSTEM.md (Section 17 - Tailwind config)

**Next Step Dependencies:**
→ Prompt #5 uses these design tokens

---

#### PROMPT #5: Core UI Component Library
**File:** `CLAUDE_CODE_PROMPTS_FRONTEND.md` (Prompt #5)  
**Subagent:** `vue-expert` + `frontend-developer`  
**Duration:** 2 days  
**Priority:** 🔴 CRITICAL

**Prerequisites:**
- ✅ Prompt #4 completed (Tailwind configured)
- Vue 3 with Composition API

**Tasks:**
Create 10 core UI components in `src/components/ui/`:
1. Button.vue (primary, secondary, ghost, danger variants)
2. InputText.vue (with validation states)
3. TextArea.vue
4. Select.vue (custom styled)
5. Checkbox.vue (custom styled)
6. Radio.vue (custom styled)
7. Card.vue (with variants)
8. Modal.vue (dialog with backdrop)
9. Dropdown.vue (menu)
10. Toast.vue (notification system)

**Deliverables:**
- [ ] 10 components in src/components/ui/
- [ ] Test page showcasing all components
- [ ] Props, emits, and slots documented
- [ ] Light & dark mode screenshots
- [ ] Component usage examples

**Quality Checks:**
Per component:
- [ ] Props validation works
- [ ] Events emit correctly
- [ ] All variants display correctly
- [ ] Dark mode works
- [ ] Keyboard navigation works
- [ ] Focus states visible
- [ ] Disabled states work
- [ ] Loading states work (where applicable)
- [ ] Accessible (ARIA labels)

**Success Criteria:**
- All 10 components render correctly
- Test page shows all variants
- Dark mode works for all components
- No console errors
- Components reusable

**Reference Files:**
- MASTER_DESIGN_SYSTEM.md (Section 5 - Component patterns)
- MASTER_COMPONENTS.md (Core UI section)

**Next Step Dependencies:**
→ These components used in all future pages

---

#### PROMPT #6: Pinia State Management
**File:** `CLAUDE_CODE_PROMPTS_FRONTEND.md` (Prompt #6)  
**Subagent:** `vue-expert`  
**Duration:** 1 day  
**Priority:** 🔴 CRITICAL

**Prerequisites:**
- ✅ Prompt #1-3 completed (APIs ready)
- ✅ Prompt #4 completed (for API base URL)
- Vue 3 project ready

**Tasks:**
1. Install Pinia
2. Configure Pinia in main.js
3. Create 6 stores in `src/stores/`:
   - i18n.js (language switching)
   - theme.js (dark mode)
   - projects.js (projects data & API calls)
   - blog.js (blog posts & API calls)
   - awards.js (awards data)
   - gallery.js (gallery images)
4. Implement API integration in stores
5. Test stores with mock data

**Deliverables:**
- [ ] Pinia installed and configured
- [ ] 6 stores created with proper structure
- [ ] API integration in stores
- [ ] Error handling in API calls
- [ ] Loading states in stores
- [ ] Test file showing store usage

**Quality Checks:**
- [ ] Stores initialize correctly
- [ ] State updates work
- [ ] Getters return correct values
- [ ] Actions execute successfully
- [ ] API calls work (test with backend)
- [ ] Error handling works
- [ ] Loading states work
- [ ] Persistence works (i18n, theme)

**Success Criteria:**
```javascript
// Test in browser console:
import { useProjectsStore } from '@/stores/projects'
const store = useProjectsStore()
await store.fetchProjects() // Should fetch from API
console.log(store.projects) // Should show data
```

**Reference Files:**
- I18N_IMPLEMENTATION.md (for i18n store)
- API_ENDPOINTS.md (for API structure)

**Next Step Dependencies:**
→ All pages will use these stores

---

#### PROMPT #7: Vue Router Setup
**File:** `CLAUDE_CODE_PROMPTS_FRONTEND.md` (Prompt #7)  
**Subagent:** `vue-expert`  
**Duration:** 1 day  
**Priority:** 🔴 CRITICAL

**Prerequisites:**
- ✅ Prompt #5 completed (components ready)
- Vue 3 project ready

**Tasks:**
1. Install Vue Router
2. Create router configuration in `src/router/index.js`
3. Define routes for:
   - Public pages (Home, Projects, Blog, Gallery, Contact)
   - Project detail page
   - Blog post detail page
   - Admin pages (Dashboard, Management panels)
4. Create placeholder page components in `src/views/`
5. Configure route guards
6. Setup 404 page
7. Test navigation

**Deliverables:**
- [ ] Vue Router installed and configured
- [ ] All routes defined in router/index.js
- [ ] Placeholder components in src/views/
- [ ] Route guards implemented
- [ ] 404 page created
- [ ] Navigation working
- [ ] Scroll behavior configured

**Quality Checks:**
- [ ] All routes accessible
- [ ] Navigation between pages works
- [ ] Route guards work (admin auth)
- [ ] 404 page shows for invalid routes
- [ ] Page titles update correctly
- [ ] Scroll to top on route change works
- [ ] Browser back/forward works
- [ ] Deep linking works

**Success Criteria:**
- Can navigate to all routes
- Admin routes require auth
- 404 shows for invalid URLs
- No console errors
- Smooth page transitions

**Reference Files:**
- MASTER_WIREFRAMES.md (for page structure)

**PHASE 2 CHECKPOINT:**
✅ Frontend foundation complete  
✅ Ready to build actual pages  
→ Proceed to Phase 3 (follow IMPLEMENTATION_ROADMAP.md)

---

## 🎛️ ORCHESTRATION CONTROLS

### Execution Strategy

**Sequential Execution with Validation:**
```
FOR each prompt (1 to 7):
    1. READ prompt file
    2. VERIFY prerequisites met
    3. ASSIGN to subagent(s)
    4. EXECUTE tasks
    5. RUN quality checks
    6. VALIDATE success criteria
    7. IF failed:
         - LOG errors
         - HALT execution
         - REPORT blockers
       ELSE:
         - MARK complete
         - PROCEED to next
```

### Checkpoint Protocol

**After Prompt #3 (Backend Complete):**
```yaml
Checks:
  - All API endpoints tested: true/false
  - No critical bugs: true/false
  - Documentation updated: true/false

If all true:
  - Status: "Phase 1 Complete ✓"
  - Action: "Proceed to Prompt #4"
Else:
  - Status: "Phase 1 Blocked ❌"
  - Action: "Fix issues before continuing"
```

**After Prompt #7 (Frontend Foundation Complete):**
```yaml
Checks:
  - All components render: true/false
  - All stores work: true/false
  - All routes accessible: true/false
  - No console errors: true/false

If all true:
  - Status: "Phase 2 Complete ✓"
  - Action: "Follow IMPLEMENTATION_ROADMAP.md for remaining work"
Else:
  - Status: "Phase 2 Blocked ❌"
  - Action: "Fix issues before continuing"
```

### Error Handling

**On Blocker:**
1. **HALT** execution immediately
2. **DOCUMENT** the blocker:
   - Which prompt failed
   - What task failed
   - Error messages
   - Prerequisites missing
3. **NOTIFY** with clear action items
4. **WAIT** for blocker resolution
5. **RESUME** from failed prompt (not restart from #1)

**Common Blockers:**
- Missing dependencies (npm packages)
- Database connection issues
- Missing environment variables
- Model relationships broken
- Port conflicts (8000, 3000, 5173)

---

## 📊 PROGRESS TRACKING

### Status Dashboard

```markdown
## Portfolio V2 - Execution Status

### Phase 1: Backend API
- [⬜] Prompt #1: Projects API - Not Started
- [⬜] Prompt #2: Blog API - Not Started  
- [⬜] Prompt #3: Gallery & Contact - Not Started
- [⬜] **Checkpoint 1:** Backend Complete

### Phase 2: Frontend Foundation
- [⬜] Prompt #4: Tailwind Setup - Not Started
- [⬜] Prompt #5: Core Components - Not Started
- [⬜] Prompt #6: Pinia Stores - Not Started
- [⬜] Prompt #7: Vue Router - Not Started
- [⬜] **Checkpoint 2:** Foundation Complete

### Overall Progress: 0/7 prompts complete (0%)
```

**Update After Each Prompt:**
```
- [🔄] In Progress
- [✅] Complete
- [❌] Failed/Blocked
```

---

## 🎯 SUBAGENT COORDINATION

### Team Assembly

**Backend Team:**
- Lead: `backend-developer`
- Support: `laravel-specialist`, `api-designer`
- QA: `test-automator`

**Frontend Team:**
- Lead: `vue-expert`
- Support: `frontend-developer`, `dx-optimizer`
- QA: `test-automator`

**Cross-functional:**
- Orchestration: `workflow-orchestrator` (YOU)
- Coordination: `multi-agent-coordinator`
- Error handling: `error-coordinator`

### Communication Protocol

**Daily Standup (After Each Prompt):**
```
✅ Completed: [List tasks]
🔄 In Progress: [Current task]
⏭️ Next: [Next prompt]
🚫 Blockers: [Issues if any]
📊 Quality: [Test results]
```

**Handoff Between Teams:**
```
Backend → Frontend:
  - APIs documented: ✓
  - Test endpoints provided: ✓
  - Postman collection shared: ✓
  
Frontend Foundation → Page Building:
  - Components library complete: ✓
  - Stores functional: ✓
  - Router configured: ✓
```

---

## 📈 SUCCESS METRICS

### Phase 1 Success (Backend):
- ✅ 100% API endpoints implemented
- ✅ All endpoints tested and working
- ✅ i18n support functional (EN/ID)
- ✅ No critical bugs
- ✅ Response times < 200ms (p95)

### Phase 2 Success (Frontend Foundation):
- ✅ Design system fully implemented
- ✅ 10 core components built and tested
- ✅ All stores functional with API integration
- ✅ All routes accessible
- ✅ No console errors
- ✅ Dark mode working

### Overall Project Success (After All Phases):
- ✅ All 7 prompts completed
- ✅ Backend + Frontend fully functional
- ✅ Responsive on all devices
- ✅ Accessible (WCAG 2.1 AA)
- ✅ Performance: Lighthouse > 90
- ✅ i18n working (EN/ID)
- ✅ Dark mode working
- ✅ Ready for production

---

## 🚀 EXECUTION COMMAND

**To Start Orchestration, Execute:**

```markdown
I am the Workflow Orchestrator for Portfolio V2 project.

My mission: Execute all 7 prompts sequentially with quality validation.

Current Phase: Phase 1 - Backend API
Current Prompt: #1 - Projects API

Action: Beginning execution of Prompt #1...

[Read CLAUDE_CODE_PROMPTS_BACKEND.md - Prompt #1]
[Assign to: backend-developer + laravel-specialist]
[Execute tasks...]
```

---

## 📚 REFERENCE DOCUMENTATION

**Prompt Files:**
- `CLAUDE_CODE_PROMPTS_BACKEND.md` (Prompts 1-3)
- `CLAUDE_CODE_PROMPTS_FRONTEND.md` (Prompts 4-7)

**Specification Files:**
- `00_INDEX.md` - Master navigation
- `MASTER_DESIGN_SYSTEM.md` - Design specs
- `MASTER_COMPONENTS.md` - Component specs
- `API_ENDPOINTS.md` - API contracts
- `I18N_IMPLEMENTATION.md` - i18n guide
- `MASTER_WIREFRAMES.md` - Page layouts

**Implementation Files:**
- `PROJECT_STATUS_ANALYSIS.md` - Current status
- `IMPLEMENTATION_ROADMAP.md` - Full roadmap (Weeks 1-8)

**Project Locations:**
- Backend: `C:\xampp\htdocs\Portfolio_v2\backend`
- Frontend: `C:\xampp\htdocs\Portfolio_v2\frontend`
- Docs: `C:\Users\ali.sadikin\.claude\agents\output`

---

## 🎬 FINAL INSTRUCTIONS TO ORCHESTRATOR

You are the **Workflow Orchestrator**. Your responsibilities:

1. ✅ **Execute prompts 1-7 in sequence**
2. ✅ **Validate each step before proceeding**
3. ✅ **Coordinate multiple subagents**
4. ✅ **Track progress and update status**
5. ✅ **Handle errors and blockers**
6. ✅ **Ensure quality at each checkpoint**
7. ✅ **Report completion or issues**

**Start with Prompt #1 and work through to Prompt #7.**

**After Prompt #7, hand off to IMPLEMENTATION_ROADMAP.md for remaining work.**

---

**STATUS: READY TO EXECUTE** 🚀  
**AWAITING ORCHESTRATOR ACTIVATION** ⏳

---

*Generated for Workflow Orchestration - October 9, 2025*  
*Master Orchestration Prompt for Complete Portfolio V2 Implementation*
