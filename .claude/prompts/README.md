# Portfolio_v2 - Development Prompts

**Location:** `C:\xampp\htdocs\Portfolio_v2\.claude\prompts`  
**Created:** October 13, 2025 12:44 PM  
**Purpose:** Comprehensive prompts for delegating Portfolio_v2 development to Claude Code

---

## 📋 Overview

This folder contains **4 comprehensive prompts** for building Portfolio_v2 from 28% to 95% completion. Each prompt is a complete, ready-to-use delegation for Claude Code following the CTO-style template.

**Development Philosophy:**
- Think like a CTO: Define WHAT and WHY, let specialists decide HOW
- Don't micromanage: Trust subagents with implementation details
- Clear objectives: Every phase has measurable success criteria
- Integration checkpoints: Verify data flows between components

---

## 📁 Available Prompts

### 1️⃣ Phase 1: Frontend Initialization (FOUNDATION)

**File:** `phase-1_frontend-init_20251013-1244.md`  
**Status:** Ready to use  
**Priority:** HIGH (Frontend at 15%, needs structure)  
**Estimated Time:** 3-4 hours

**Objective:**
Initialize complete Vue 3.5 application structure with routing, state management, base components, and layouts.

**What Gets Built:**
- ✅ Vue Router with all main routes
- ✅ Pinia stores (auth, posts, projects, ui)
- ✅ 10+ base components (Button, Input, Card, Modal, etc.)
- ✅ Layout components (Header, Footer, Sidebar)
- ✅ Page components (Home, About, Blog, Projects, Contact, Login, 404)
- ✅ Composables (useAuth, useApi, useToast, useModal)
- ✅ Axios client configured
- ✅ Tailwind CSS setup
- ✅ Responsive design (mobile-first)

**Completion:**
- Frontend: 15% → 50%
- Overall: 28% → 39%

**Agent:**
- 🟢 **vue-expert** (primary)

---

### 2️⃣ Phase 2: Backend Controllers (BACKEND CORE)

**File:** `phase-2_backend-controllers_20251013-1244.md`  
**Status:** Ready to use  
**Priority:** HIGH (Backend at 40%, needs controllers)  
**Estimated Time:** 4-5 hours

**Objective:**
Create 8 remaining Laravel controllers with complete CRUD operations, validation, API resources, and routes.

**What Gets Built:**
- ✅ 8 controllers (Project, Post, Category, Testimonial, Contact, Page, Skill, Experience)
- ✅ 15 FormRequest validation classes
- ✅ 8 API Resource transformers
- ✅ Routes in api.php
- ✅ Image upload for posts/projects
- ✅ Search and pagination
- ✅ RESTful API conventions
- ✅ Error handling

**Completion:**
- Backend: 40% → 75%
- Overall: 39% → 57%

**Agents:**
- 🔵 **laravel-specialist** (primary)
- 🟦 **database-administrator** (supporting)

---

### 3️⃣ Phase 3: Blog Management System (FULL FEATURE)

**File:** `phase-3_blog-system_20251013-1244.md`  
**Status:** Ready to use  
**Priority:** MEDIUM (Full feature integration)  
**Estimated Time:** 5-6 hours

**Objective:**
Build complete blog management system with admin interface, rich text editor, image upload, search, and publishing workflow.

**What Gets Built:**
- ✅ Admin pages (blog list, create, edit, category management)
- ✅ Public pages (blog list, single post)
- ✅ Rich text editor (TinyMCE)
- ✅ Image upload with preview
- ✅ Category selector
- ✅ Search and filtering
- ✅ Pagination (admin + public)
- ✅ Draft/Published workflow
- ✅ SEO fields integration
- ✅ Responsive design

**Completion:**
- Frontend: 50% → 70%
- Backend: 75% → 75% (already done in Phase 2)
- Overall: 57% → 75%

**Agents:**
- 🔴 **orchestrator** (coordinator)
- 🟢 **vue-expert** (primary)
- 🔵 **laravel-specialist** (support)
- 🟦 **database-administrator** (optimization)
- 🟨 **qa-expert** (manual testing)
- ⚪ **documentation-engineer** (docs)

---

### 4️⃣ Phase 4: QA & Testing with Playwright MCP (QUALITY ASSURANCE)

**File:** `phase-4_qa-testing_20251013-1244.md`  
**Status:** Ready to use  
**Priority:** HIGH (Critical for production)  
**Estimated Time:** 4-5 hours

**Objective:**
Comprehensive testing using Laravel tests (backend) and Playwright MCP (frontend browser automation) with screenshot evidence.

**What Gets Built:**
- ✅ Laravel feature tests (9 controllers, all CRUD)
- ✅ Playwright browser tests (all user flows)
- ✅ Screenshot evidence (60+ screenshots)
- ✅ Responsive tests (mobile, tablet, desktop)
- ✅ Performance benchmarks
- ✅ Bug reports
- ✅ Test documentation
- ✅ >80% test coverage

**Completion:**
- Tests: 0% → 95%
- Overall: 75% → 95%

**Agents:**
- 🟨 **qa-expert** (primary)
- 🔵 **laravel-specialist** (support)
- 🟢 **vue-expert** (support)
- 🟦 **database-administrator** (support)
- ⚪ **documentation-engineer** (docs)

---

## 🚀 How to Use These Prompts

### Method 1: Copy-Paste to Claude Code (Recommended)

1. **Start Claude Code in project root:**
   ```bash
   cd C:\xampp\htdocs\Portfolio_v2
   code .
   # Press Ctrl+Shift+P → "Claude Code: Start"
   ```

2. **Open prompt file:**
   ```
   Open: .claude/prompts/phase-1_frontend-init_20251013-1244.md
   ```

3. **Copy entire content** (Ctrl+A, Ctrl+C)

4. **Paste into Claude Code chat** (Ctrl+V, Enter)

5. **Claude Code will:**
   - Read all referenced files
   - Load appropriate subagent(s)
   - Execute the plan
   - Create all deliverables
   - Update PROJECT_STATUS.md

---

### Method 2: Reference in Chat

Instead of copy-pasting, you can reference:

```
Execute phase 1 frontend initialization. 
Use prompt at: .claude/prompts/phase-1_frontend-init_20251013-1244.md
```

Claude Code will read and follow the prompt.

---

### Method 3: Sequential Execution

Run all phases in order:

```
Execute all 4 phases sequentially:
1. phase-1_frontend-init_20251013-1244.md
2. phase-2_backend-controllers_20251013-1244.md
3. phase-3_blog-system_20251013-1244.md
4. phase-4_qa-testing_20251013-1244.md

Wait for each phase to complete before starting next.
Update PROJECT_STATUS.md after each phase.
```

---

## 📊 Progress Tracking

### Before Starting
- Overall: 28% (Backend 40%, Frontend 15%)

### After Phase 1
- Overall: 39% (Backend 40%, Frontend 50%)

### After Phase 2
- Overall: 57% (Backend 75%, Frontend 50%)

### After Phase 3
- Overall: 75% (Backend 75%, Frontend 70%)

### After Phase 4
- Overall: 95% (Backend 75%, Frontend 70%, Tests 95%)

---

## ✅ Success Criteria

Each phase has detailed success criteria in its prompt file. At a high level:

**Phase 1 Success:**
- [ ] Vue app runs without errors
- [ ] All base components working
- [ ] Router navigates correctly
- [ ] Stores initialized
- [ ] Responsive on all breakpoints

**Phase 2 Success:**
- [ ] All 8 controllers created
- [ ] All CRUD operations work
- [ ] Validation comprehensive
- [ ] Image upload works
- [ ] Routes tested with Postman

**Phase 3 Success:**
- [ ] Admin blog pages functional
- [ ] Public blog pages functional
- [ ] Rich text editor integrated
- [ ] Image upload UI working
- [ ] Search and pagination work
- [ ] Backend ↔ Frontend integrated

**Phase 4 Success:**
- [ ] All backend tests pass
- [ ] All frontend tests pass
- [ ] Screenshots captured
- [ ] Performance benchmarks documented
- [ ] Bug reports created
- [ ] Test coverage >80%

---

## 🎯 Execution Order

**Recommended:** Sequential (Phase 1 → 2 → 3 → 4)

**Why Sequential?**
- Phase 2 needs Phase 1 (frontend structure ready)
- Phase 3 needs Phase 1 + 2 (both backend and frontend ready)
- Phase 4 needs Phase 1 + 2 + 3 (everything built, now test)

**Can Skip Phases?**
- ⚠️ No, dependencies between phases
- ✅ But you can run Phase 1 + 2 in parallel (different domains)

---

## 🔧 Troubleshooting

### If Claude Code doesn't start:

1. **Check .claude/agents/ folder exists:**
   ```
   Dir: C:\xampp\htdocs\Portfolio_v2\.claude\agents
   Files: orchestrator.md, laravel-specialist.md, vue-expert.md, etc.
   ```

2. **Read orchestrator.md first:**
   ```
   File: .claude/agents/orchestrator.md
   Verify: Contains agent coordination patterns
   ```

3. **Check PROJECT_STATUS.md exists:**
   ```
   File: C:\xampp\htdocs\Portfolio_v2\PROJECT_STATUS.md
   Contains: Current completion percentages
   ```

### If prompt is too long for chat:

**Option A:** Break into sections
- Copy only "OBJECTIVE" + "REQUIREMENTS" + "DELIVERABLES"
- Let Claude Code read full file itself

**Option B:** Use file reference
```
Read and execute: .claude/prompts/phase-1_frontend-init_20251013-1244.md
```

### If subagent not found:

1. **Verify subagent file exists:**
   ```
   Check: .claude/agents/vue-expert.md
   Check: .claude/agents/laravel-specialist.md
   ```

2. **Verify orchestrator.md exists:**
   ```
   Check: .claude/agents/orchestrator.md
   ```

3. **Read .claude/agents/README.md:**
   ```
   File explains all available subagents
   ```

---

## 📝 Customization

**Want to modify a phase?**

1. Copy prompt file:
   ```
   Copy: phase-1_frontend-init_20251013-1244.md
   To: phase-1_custom_20251013-1500.md
   ```

2. Edit sections:
   - OBJECTIVE (what you want)
   - REQUIREMENTS (specific features)
   - CONSTRAINTS (limitations)
   - SUCCESS CRITERIA (when done)

3. Keep:
   - ARCHITECTURE (structure)
   - AGENTS NEEDED (who does what)
   - TECHNICAL CONTEXT (how to implement)

4. Use custom prompt:
   ```
   Execute: .claude/prompts/phase-1_custom_20251013-1500.md
   ```

---

## 🎨 Prompt Template Structure

All prompts follow this structure:

```markdown
# Phase X: [Feature Name] - Implementation

## 🎯 OBJECTIVE
Clear, measurable goal

## 📊 CURRENT STATE
What exists, what's missing

## 🏗️ ARCHITECTURE
High-level structure, file tree

## 👥 AGENTS NEEDED
Which subagents, their responsibilities

## ✅ REQUIREMENTS
Functional, validation, UI/UX, performance, accessibility

## 🚫 CONSTRAINTS
Technical, project, development constraints

## 🎯 SUCCESS CRITERIA
Detailed checklist of completion

## 📝 TECHNICAL CONTEXT
Code examples, patterns, configuration

## 📋 DELIVERABLES
List of files to create/update

## 🔗 INTEGRATION CHECKPOINTS
How components integrate

## 📚 DOCUMENTATION REQUIREMENTS
What to document

## ⚠️ CRITICAL REMINDERS
Windows paths, URLs, conventions

## 🎯 NEXT PHASE
What comes after
```

**Why this structure?**
- CTO-style delegation (WHAT, not HOW)
- Clear objectives and success criteria
- Complete context for subagents
- Integration verification
- Documentation requirements
- Critical reminders

---

## 💡 Best Practices

### Before Starting a Phase:

1. **Read PROJECT_STATUS.md:**
   ```
   Check current completion percentages
   Understand what's done and what's missing
   ```

2. **Read relevant README files:**
   ```
   - README.md (project overview)
   - backend/README.md (if backend work)
   - frontend/README.md (if frontend work)
   ```

3. **Read subagent documentation:**
   ```
   .claude/agents/README.md (overview)
   .claude/agents/[agent-name].md (specific agent)
   ```

### During Phase Execution:

1. **Monitor progress:**
   - Claude Code shows progress in chat
   - Files being created/updated
   - Tests being run

2. **Review checkpoints:**
   - Integration verification
   - Success criteria
   - Documentation updates

3. **Test incrementally:**
   - Don't wait until end
   - Test as features complete
   - Fix issues immediately

### After Phase Completion:

1. **Verify success criteria:**
   - Check all checkboxes
   - Test manually if needed
   - Review generated files

2. **Update PROJECT_STATUS.md:**
   - New completion percentages
   - Features completed
   - Next steps

3. **Commit changes:**
   ```bash
   git add .
   git commit -m "Phase X complete: [Feature Name]"
   ```

4. **Start next phase:**
   - Review dependencies
   - Check prerequisites
   - Execute next prompt

---

## 🔗 Related Documentation

**Project Setup:**
- C:\xampp\htdocs\Portfolio_v2\README.md
- C:\xampp\htdocs\Portfolio_v2\PROJECT_INSTRUCTIONS.md
- C:\xampp\htdocs\Portfolio_v2\PROJECT_STATUS.md

**Subagent System:**
- C:\xampp\htdocs\Portfolio_v2\.claude\agents\README.md
- C:\xampp\htdocs\Portfolio_v2\.claude\agents\orchestrator.md
- C:\xampp\htdocs\Portfolio_v2\.claude\agents\[agent-name].md

**Backend:**
- C:\xampp\htdocs\Portfolio_v2\backend\README.md
- C:\xampp\htdocs\Portfolio_v2\backend\SEO_IMPLEMENTATION.md

**Frontend:**
- C:\xampp\htdocs\Portfolio_v2\frontend\README.md

---

## ❓ FAQ

**Q: Can I run phases in parallel?**  
A: Phase 1 and 2 can run in parallel (different domains). Phase 3 needs both. Phase 4 needs all.

**Q: How long will all phases take?**  
A: 16-20 hours total (Phase 1: 3-4h, Phase 2: 4-5h, Phase 3: 5-6h, Phase 4: 4-5h)

**Q: Can I skip a phase?**  
A: No, phases are sequential with dependencies. Must do in order.

**Q: What if I only want backend?**  
A: Run Phase 2 only. But Phase 1 (frontend) makes Phase 3 (integration) easier.

**Q: What if tests fail in Phase 4?**  
A: Fix critical and major bugs. Document minor bugs for future. Aim for >80% passing.

**Q: Can I customize prompts?**  
A: Yes! Copy and modify. Keep structure, change details.

**Q: Do I need all subagents?**  
A: Yes, they're all in .claude/agents/. Phase 3 uses orchestrator to coordinate all of them.

---

## 🎉 Project Completion

After all 4 phases:
- **Completion:** 95%
- **Status:** Production-ready (after minor bug fixes)
- **Next Steps:**
  1. Deploy to staging server
  2. Final review and testing
  3. Fix remaining minor bugs
  4. Deploy to production
  5. Monitor and iterate

---

**Created:** October 13, 2025 12:44 PM  
**Total Phases:** 4  
**Total Files:** 4 comprehensive prompts  
**Completion Path:** 28% → 95%  
**Estimated Time:** 16-20 hours  
**Maintainer:** Ali Sadikin

---

## 🚀 Ready to Start?

```
Step 1: Read this README ✅ (you're here!)
Step 2: Open Claude Code in project root
Step 3: Copy phase-1_frontend-init_20251013-1244.md
Step 4: Paste into Claude Code chat
Step 5: Watch the magic happen! 🎨
```

**Good luck building Portfolio_v2!** 💪
