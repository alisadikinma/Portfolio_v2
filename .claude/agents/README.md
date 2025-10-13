# Portfolio_v2 Subagents

**Location:** `C:\xampp\htdocs\Portfolio_v2\.claude\agents`

This folder contains project-specific Claude Code subagents for Portfolio_v2 development.

---

## 📋 Subagent Overview

### Why Project-Specific Subagents?

Instead of using global subagents (`~/.claude/agents`), this project has its own specialized subagents that:
- ✅ Know Portfolio_v2 project structure
- ✅ Follow project-specific conventions
- ✅ Use correct tech stack versions (Laravel 10, Vue 3.5, etc.)
- ✅ Understand XAMPP port 80 configuration
- ✅ Use Windows paths and Filesystem tools only

---

## 👥 Required Subagents

### ✅ All Subagents Complete!
- [x] **orchestrator.md** - Multi-agent coordinator ✅
- [x] **laravel-specialist.md** - Laravel 10 backend expert ✅
- [x] **vue-expert.md** - Vue 3 Composition API expert ✅
- [x] **database-administrator.md** - MySQL optimization expert ✅
- [x] **qa-expert.md** - Testing and QA expert ✅
- [x] **documentation-engineer.md** - Documentation expert ✅

---

## 🔴 orchestrator

**Purpose:** Coordinate multiple specialized agents for complex tasks

**When to use:**
- Task spans 3+ domains (backend + frontend + database + testing + docs)
- Need to ensure consistency across different parts of project
- Complex feature implementation
- Multi-step workflows

**Responsibilities:**
- Read PROJECT_STATUS.md to understand current state
- Delegate tasks to appropriate specialists
- Verify integration points between domains
- Ensure code consistency across project
- Final QA verification
- Update PROJECT_STATUS.md after completion

**Example tasks:**
- "Build complete blog system with CRUD, search, admin interface, and tests"
- "Implement authentication flow across backend and frontend"
- "Add SEO features to all content types"

---

## 🔵 laravel-specialist

**Purpose:** Expert in Laravel 10 backend development

**When to use:**
- Building API endpoints
- Creating controllers, validation, resources
- Laravel-specific features
- Backend logic and business rules

**Expertise:**
- Laravel 10 best practices
- RESTful API design
- FormRequest validation
- API Resource transformers
- Eloquent relationships and scopes
- Sanctum authentication
- File uploads and storage
- Query optimization

**Must know:**
- Backend runs on XAMPP Port 80 (NOT php artisan serve)
- Backend URL: http://localhost/Portfolio_v2/backend/public/api
- Follow patterns in existing AwardController
- Use HasSeoFields trait for SEO features
- Database credentials: ali / aL1889900@@@

**Example tasks:**
- "Create PostController with CRUD, validation, and API resources"
- "Add search and pagination to Projects API"
- "Implement image upload for posts with optimization"

---

## 🟢 vue-expert

**Purpose:** Expert in Vue 3 Composition API and frontend development

**When to use:**
- Building Vue components
- Setting up Pinia stores
- Configuring Vue Router
- Frontend state management
- UI/UX implementation

**Expertise:**
- Vue 3 Composition API (setup script)
- Pinia 3.0 state management
- Vue Router 4.5 configuration
- Composables and hooks
- Tailwind CSS 4.1 styling
- Axios integration
- Form handling and validation
- Responsive design

**Must know:**
- Frontend runs on Vite port 3000
- API URL: http://localhost/Portfolio_v2/backend/public/api
- Use Tailwind utility classes ONLY (no custom CSS)
- Mobile-first responsive design
- Use Headless UI components
- Heroicons for icons

**Example tasks:**
- "Create blog post list component with pagination"
- "Build Pinia store for authentication"
- "Create reusable form components with validation"

---

## 🟦 database-administrator

**Purpose:** MySQL database optimization and schema management

**When to use:**
- Adding database indexes
- Optimizing slow queries
- Complex relationships
- Migration modifications
- Performance issues

**Expertise:**
- MySQL 8.x optimization
- Index strategy
- Query performance tuning
- Foreign key relationships
- Data integrity
- Migration best practices

**Must know:**
- Database: portfolio_v2
- Username: ali
- Password: aL1889900@@@
- All tables have SEO fields (posts, projects, categories)
- Use Eloquent ORM (not raw SQL)

**Example tasks:**
- "Add indexes for search optimization on posts table"
- "Optimize query performance for project filtering"
- "Review and improve database schema"

---

## 🟨 qa-expert

**Purpose:** Quality assurance, testing, and verification

**When to use:**
- Writing feature tests
- Browser automation testing
- Verifying CRUD operations
- Integration testing
- Bug verification

**Expertise:**
- Laravel testing (PHPUnit/Pest)
- Playwright MCP browser testing
- API endpoint testing
- Integration testing
- Test coverage analysis
- Screenshot documentation

**Must know:**
- Use Playwright MCP for browser testing
- Screenshot evidence REQUIRED for all CRUD operations
- Test both backend API and frontend UI
- Backend URL: http://localhost/Portfolio_v2/backend/public/api
- Frontend URL: http://localhost:3000

**Example tasks:**
- "Write feature tests for post CRUD operations"
- "Verify blog system with Playwright browser tests"
- "Test authentication flow end-to-end"

---

## ⚪ documentation-engineer

**Purpose:** Documentation and API documentation

**When to use:**
- Documenting new endpoints
- Updating README files
- Writing component guides
- Architecture documentation

**Expertise:**
- API documentation (OpenAPI/Swagger style)
- Component documentation
- README maintenance
- Code comments
- Architecture decision records

**Must know:**
- Update README.md for new features
- Update PROJECT_STATUS.md for progress tracking
- Document API endpoints with request/response examples
- Document component props, events, slots
- Use clear, concise language

**Example tasks:**
- "Document all post API endpoints with examples"
- "Update README with new blog feature"
- "Document Vue components usage"

---

## 🎯 How to Use Subagents

### Single Specialist (Simple Tasks)

For tasks that only affect one domain:

```
Task: "Create PostController with validation"
→ Read: laravel-specialist.md
→ Delegate to: laravel-specialist
```

### Multiple Specialists (Complex Tasks)

For tasks spanning multiple domains, use orchestrator:

```
Task: "Build complete blog system"
→ Read: orchestrator.md
→ Orchestrator coordinates:
   - laravel-specialist (backend API)
   - database-administrator (indexes)
   - vue-expert (frontend UI)
   - qa-expert (testing)
   - documentation-engineer (docs)
```

---

## 📝 Subagent Template Structure

Each subagent file should follow this structure:

```markdown
# [Agent Name]

## Role & Expertise
[What this agent specializes in]

## When to Use
[Specific scenarios to use this agent]

## Project Context
[Portfolio_v2 specific information]

## Tech Stack
[Relevant technologies and versions]

## Key Responsibilities
[What this agent should do]

## Must Follow
[Project-specific rules and conventions]

## Common Tasks
[Examples of typical tasks]

## Integration Points
[How this agent coordinates with others]

## Success Criteria
[What defines successful completion]
```

---

## 🚨 Critical Rules for All Subagents

### Windows Environment
- ✅ Use Filesystem:* tools ONLY
- ❌ NEVER use view, str_replace, bash_tool
- ✅ Use Windows paths: C:\xampp\htdocs\Portfolio_v2
- ❌ NEVER use Linux paths: /mnt/c/ or /C:/

### Project Configuration
- Backend: XAMPP Port 80 (NOT php artisan serve)
- Frontend: Vite Port 3000
- Database: localhost:3306 (MySQL via XAMPP)

### Required Reading
Every subagent MUST read these files before starting:
1. C:\xampp\htdocs\Portfolio_v2\README.md
2. C:\xampp\htdocs\Portfolio_v2\backend\README.md (if backend work)
3. C:\xampp\htdocs\Portfolio_v2\frontend\README.md (if frontend work)
4. C:\xampp\htdocs\Portfolio_v2\PROJECT_STATUS.md

### Quality Standards
- Follow PSR-12 for PHP
- Follow Vue 3 Style Guide for Vue
- Write comprehensive tests
- Update documentation
- No breaking changes without approval

---

## 📊 Progress Tracking

After completing tasks, subagents should:
1. ✅ Update PROJECT_STATUS.md (mark completed items)
2. ✅ Update README.md (if new features added)
3. ✅ Document API endpoints (if backend changes)
4. ✅ Write/update tests
5. ✅ Verify no breaking changes

---

## 🔗 Related Documentation

- **Project Instructions:** C:\xampp\htdocs\Portfolio_v2\PROJECT_INSTRUCTIONS.md
- **Project Status:** C:\xampp\htdocs\Portfolio_v2\PROJECT_STATUS.md
- **Main README:** C:\xampp\htdocs\Portfolio_v2\README.md
- **Backend README:** C:\xampp\htdocs\Portfolio_v2\backend\README.md
- **Frontend README:** C:\xampp\htdocs\Portfolio_v2\frontend\README.md

---

**Last Updated:** October 13, 2025  
**Project:** Portfolio v2  
**Maintainer:** Ali Sadikin
