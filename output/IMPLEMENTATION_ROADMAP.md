# Portfolio V2 - Implementation Roadmap & Task Assignment

**Created:** October 9, 2025  
**Status:** Ready for Implementation  
**Total Timeline:** 6-8 weeks

---

## 📋 QUICK REFERENCE

### Documentation Files
- **PROJECT_STATUS_ANALYSIS.md** - Full status analysis
- **CLAUDE_CODE_PROMPTS_BACKEND.md** - Backend API tasks (Prompts 1-3)
- **CLAUDE_CODE_PROMPTS_FRONTEND.md** - Frontend tasks (Prompts 4-7)
- **00_INDEX.md** - Master navigation for all specs
- **MASTER_DESIGN_SYSTEM.md** - Complete design system
- **MASTER_COMPONENTS.md** - Component specifications
- **API_ENDPOINTS.md** - API contracts
- **I18N_IMPLEMENTATION.md** - i18n guide

### Project Directory
- **Backend:** `C:\xampp\htdocs\Portfolio_v2\backend`
- **Frontend:** `C:\xampp\htdocs\Portfolio_v2\frontend`
- **Documentation:** `C:\Users\ali.sadikin\.claude\agents\output`
- **Subagents:** `C:\Users\ali.sadikin\.claude\agents\CLAUDE.md`

---

## 🎯 WEEK-BY-WEEK TASK ASSIGNMENT

### WEEK 1: Backend API Completion 🔴 CRITICAL

#### Day 1-2: Projects API
**Prompt:** #1 in CLAUDE_CODE_PROMPTS_BACKEND.md  
**Subagent:** `backend-developer` + `laravel-specialist`  
**Deliverables:**
- [ ] ProjectController with full CRUD
- [ ] ProjectResource with i18n support
- [ ] Routes configured
- [ ] All endpoints tested

#### Day 3-4: Blog/Posts API
**Prompt:** #2 in CLAUDE_CODE_PROMPTS_BACKEND.md  
**Subagent:** `backend-developer` + `laravel-specialist`  
**Deliverables:**
- [ ] PostController with CRUD
- [ ] CategoryController
- [ ] i18n support
- [ ] All endpoints tested

#### Day 5: Gallery & Contact API
**Prompt:** #3 in CLAUDE_CODE_PROMPTS_BACKEND.md  
**Subagent:** `backend-developer`  
**Deliverables:**
- [ ] GalleryController with bulk upload
- [ ] ContactController with validation
- [ ] Email notifications
- [ ] All endpoints tested

#### Day 6-7: API Testing & Documentation
**Subagent:** `test-automator`  
**Tasks:**
- [ ] API integration tests
- [ ] Postman collection
- [ ] Test edge cases
- [ ] Update API docs if needed

---

### WEEK 2: Frontend Foundation 🔴 CRITICAL

#### Day 1-2: Tailwind Setup
**Prompt:** #4 in CLAUDE_CODE_PROMPTS_FRONTEND.md  
**Subagent:** `frontend-developer` + `dx-optimizer`  
**Deliverables:**
- [ ] Tailwind configured with design tokens
- [ ] Base CSS with component classes
- [ ] Dark mode setup
- [ ] Test page with design system

#### Day 3-4: Core UI Components
**Prompt:** #5 in CLAUDE_CODE_PROMPTS_FRONTEND.md  
**Subagent:** `vue-expert` + `frontend-developer`  
**Deliverables:**
- [ ] Button (all variants)
- [ ] Input fields (Text, Textarea, Select, Checkbox, Radio)
- [ ] Card, Modal, Dropdown
- [ ] Toast notifications
- [ ] Test page showcasing all components

#### Day 5: State Management (Pinia)
**Prompt:** #6 in CLAUDE_CODE_PROMPTS_FRONTEND.md  
**Subagent:** `vue-expert`  
**Deliverables:**
- [ ] Pinia installed & configured
- [ ] 6 stores created (i18n, theme, projects, blog, awards, gallery)
- [ ] API integration in stores
- [ ] Test with mock data

#### Day 6-7: Vue Router
**Prompt:** #7 in CLAUDE_CODE_PROMPTS_FRONTEND.md  
**Subagent:** `vue-expert`  
**Deliverables:**
- [ ] Vue Router configured
- [ ] All routes defined
- [ ] Placeholder pages created
- [ ] Route guards implemented
- [ ] Navigation working

---

### WEEK 3-4: Public Portfolio Pages 🟡 HIGH

#### Homepage Components
**Subagent:** `frontend-developer` + `vue-expert`  
**Reference:** MASTER_COMPONENTS.md, MASTER_WIREFRAMES.md  
**Components to Build:**
- [ ] HeroSection.vue (gradient, CTA buttons)
- [ ] FeaturedProjects.vue (card grid)
- [ ] AwardsList.vue (4 featured awards)
- [ ] TestimonialCarousel.vue (with Swiper.js)
- [ ] RecentBlogPosts.vue
- [ ] ContactCTA.vue
- [ ] NavigationBar.vue (responsive)
- [ ] FooterSection.vue

#### Projects Page
**Subagent:** `frontend-developer`  
**Components:**
- [ ] ProjectCard.vue
- [ ] ProjectsList.vue (with filtering)
- [ ] ProjectDetail.vue (full detail with translations)
- [ ] Category filters
- [ ] Search functionality

#### Blog Page
**Subagent:** `frontend-developer`  
**Components:**
- [ ] BlogCard.vue
- [ ] BlogList.vue (with categories & tags)
- [ ] BlogPost.vue (full article with translations)
- [ ] CategoryFilter.vue
- [ ] SearchBar.vue

#### Gallery Page
**Subagent:** `frontend-developer`  
**Components:**
- [ ] GalleryGrid.vue (masonry layout)
- [ ] GalleryItem.vue (hover effects)
- [ ] ImageLightbox.vue (full-screen viewer)
- [ ] Category filters

#### About & Contact Pages
**Subagent:** `frontend-developer`  
**Components:**
- [ ] AboutSection.vue (timeline, skills)
- [ ] ContactForm.vue (with validation)
- [ ] Map integration (optional)

---

### WEEK 5: Admin Panel Enhancement 🟡 HIGH

#### Admin Dashboard
**Subagent:** `frontend-developer`  
**Reference:** MASTER_COMPONENTS.md (Admin section)  
**Components:**
- [ ] DashboardCard.vue (metrics)
- [ ] DashboardCharts.vue
- [ ] RecentActivity.vue
- [ ] QuickActions.vue

#### Admin CRUD Panels
**Subagent:** `fullstack-developer` (both backend & frontend)  
**Tasks:**
- [ ] Complete Filament resources for all models
- [ ] Awards editor (icon upload, categories)
- [ ] Testimonials editor (avatar upload, rating)
- [ ] Gallery bulk upload (drag-drop)
- [ ] Translation editor UI

#### Admin Components
**Subagent:** `frontend-developer`  
**Components:**
- [ ] DataTable.vue (sortable, searchable)
- [ ] AdminSidebar.vue (collapsible)
- [ ] Breadcrumbs.vue
- [ ] Pagination.vue
- [ ] StatusBadge.vue
- [ ] ImageUploader.vue
- [ ] RichTextEditor.vue (integrate TinyMCE or similar)

---

### WEEK 6: i18n & Polish 🟢 MEDIUM

#### i18n Integration
**Subagent:** `frontend-developer`  
**Reference:** I18N_IMPLEMENTATION.md  
**Tasks:**
- [ ] Install vue-i18n
- [ ] Create locale files (en.json, id.json)
- [ ] LanguageSwitcher.vue component
- [ ] Integrate with API (lang parameter)
- [ ] Test language switching
- [ ] Translate all UI labels

#### Responsive Testing
**Subagent:** `frontend-developer` + `test-automator`  
**Tasks:**
- [ ] Test mobile (320px-640px)
- [ ] Test tablet (640px-1024px)
- [ ] Test desktop (1024px+)
- [ ] Fix layout issues
- [ ] Touch-friendly interactions

#### Dark Mode Testing
**Subagent:** `frontend-developer`  
**Tasks:**
- [ ] Test all pages in dark mode
- [ ] Fix contrast issues
- [ ] Test theme toggle
- [ ] Persist theme preference

---

### WEEK 7: Testing & Optimization 🟢 MEDIUM

#### Accessibility Audit
**Subagent:** `accessibility-tester` + `frontend-developer`  
**Reference:** MASTER_DESIGN_SYSTEM.md (Section 7)  
**Tasks:**
- [ ] WCAG 2.1 AA compliance check
- [ ] Keyboard navigation test
- [ ] Screen reader test
- [ ] Color contrast verification
- [ ] Focus state improvements
- [ ] ARIA labels audit

#### Performance Optimization
**Subagent:** `performance-engineer` + `frontend-developer`  
**Tasks:**
- [ ] Image optimization (convert to WebP)
- [ ] Lazy loading implementation
- [ ] Code splitting
- [ ] Bundle size analysis
- [ ] Lighthouse audit
- [ ] Fix Core Web Vitals

#### Testing
**Subagent:** `test-automator`  
**Tasks:**
- [ ] Unit tests for critical components
- [ ] Integration tests for API
- [ ] E2E tests for main user flows
- [ ] Fix bugs discovered in testing

---

### WEEK 8: Deployment Preparation 🟢 LOW

#### Pre-deployment Checklist
**Subagent:** `devops-engineer` + `backend-developer`  
**Tasks:**
- [ ] Environment configuration
- [ ] Database migrations ready
- [ ] API rate limiting configured
- [ ] Error logging setup
- [ ] Backup strategy
- [ ] SSL certificate

#### Final Polish
**Subagent:** `fullstack-developer`  
**Tasks:**
- [ ] Fix remaining bugs
- [ ] Final QA pass
- [ ] Performance verification
- [ ] SEO meta tags
- [ ] Sitemap generation
- [ ] robots.txt

#### Documentation
**Subagent:** `documentation-engineer`  
**Tasks:**
- [ ] README for deployment
- [ ] API documentation
- [ ] Component documentation
- [ ] Admin user guide

---

## 🚀 HOW TO USE CLAUDE CODE

### For Each Task:

1. **Read the Prompt**
   - Open relevant prompt file (CLAUDE_CODE_PROMPTS_*.md)
   - Read context, requirements, and reference files

2. **Open Claude Code**
   - Go to https://claude.ai/code
   - Start new session

3. **Paste the Prompt**
   - Copy the entire prompt section
   - Paste into Claude Code
   - Include this preamble:
   
   ```
   I'm working on Portfolio V2 project. Project directory: C:\xampp\htdocs\Portfolio_v2
   
   Reference documentation in: C:\Users\ali.sadikin\.claude\agents\output
   
   Please read the following task prompt and implement it:
   
   [PASTE PROMPT HERE]
   ```

4. **Specify Subagent (Optional but Recommended)**
   ```
   Use subagent expertise: backend-developer, laravel-specialist
   
   Subagent capabilities available at: C:\Users\ali.sadikin\.claude\agents\CLAUDE.md
   ```

5. **Review & Test**
   - Review generated code
   - Test functionality
   - Fix any issues
   - Mark task as complete

6. **Move to Next Task**
   - Follow week-by-week schedule
   - Update progress in this document

---

## ✅ PROGRESS TRACKING

Copy this checklist to track your progress:

### Week 1: Backend API ⬜
- [ ] Projects API complete
- [ ] Blog API complete
- [ ] Gallery API complete
- [ ] Contact API complete
- [ ] All API tests passing

### Week 2: Frontend Foundation ⬜
- [ ] Tailwind configured
- [ ] Core components built
- [ ] Pinia stores created
- [ ] Vue Router setup
- [ ] Test pages working

### Week 3-4: Public Pages ⬜
- [ ] Homepage complete
- [ ] Projects page complete
- [ ] Blog page complete
- [ ] Gallery page complete
- [ ] Contact page complete

### Week 5: Admin Panel ⬜
- [ ] Dashboard complete
- [ ] CRUD panels complete
- [ ] Admin components built

### Week 6: i18n & Polish ⬜
- [ ] i18n integrated
- [ ] Responsive tested
- [ ] Dark mode tested

### Week 7: Testing ⬜
- [ ] Accessibility audit done
- [ ] Performance optimized
- [ ] Tests written

### Week 8: Deployment ⬜
- [ ] Pre-deployment checklist complete
- [ ] Final polish done
- [ ] Documentation complete

---

## 📞 SUPPORT & RESOURCES

### Subagent Categories (CLAUDE.md)
- **Core Development:** backend-developer, frontend-developer, fullstack-developer
- **Specialists:** vue-expert, laravel-specialist, api-designer
- **Quality:** test-automator, accessibility-tester, performance-engineer
- **Infrastructure:** devops-engineer
- **Documentation:** documentation-engineer

### Documentation Hierarchy
1. **00_INDEX.md** - Start here for navigation
2. **PROJECT_STATUS_ANALYSIS.md** - Current status
3. **MASTER_DESIGN_SYSTEM.md** - Design reference
4. **MASTER_COMPONENTS.md** - Component specs
5. **API_ENDPOINTS.md** - API contracts
6. **I18N_IMPLEMENTATION.md** - i18n guide

### Key Technologies
- Backend: Laravel 11, Filament 3, MySQL
- Frontend: Vue 3, Vite, Tailwind CSS, Pinia
- Icons: Heroicons
- Carousel: Swiper.js
- i18n: vue-i18n

---

## 🎉 SUCCESS CRITERIA

Project is considered complete when:
- ✅ All API endpoints working
- ✅ All frontend components built
- ✅ i18n working (EN/ID)
- ✅ Dark mode working
- ✅ Responsive on all devices
- ✅ WCAG 2.1 AA compliant
- ✅ Performance: Lighthouse score > 90
- ✅ All tests passing
- ✅ Admin panel functional
- ✅ Ready for deployment

---

**Start Date:** [TO BE FILLED]  
**Target Completion:** [6-8 weeks from start]  
**Current Week:** [TO BE FILLED]

---

*Generated by Claude AI - October 9, 2025*  
*Ready for implementation via Claude Code*
