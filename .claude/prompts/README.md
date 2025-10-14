# Development Prompts

**Location:** `C:\xampp\htdocs\Portfolio_v2\.claude\prompts`

Comprehensive prompts for Claude Code multi-phase development.

---

## 📋 Available Prompts

| Phase | File | Status | What It Does |
|-------|------|--------|--------------|
| 1 | phase-1_frontend-init | ✅ Complete | Vue 3 + Vite + Tailwind setup |
| 2 | phase-2_backend-controllers | ✅ Complete | API controllers + validation |
| 3 | phase-3_blog-system | ✅ Complete | Blog components (RichTextEditor, etc.) |
| 4 | phase-4_qa-testing | ⏸️ Pending | Playwright testing |
| 5 | phase-5_admin-panel-completion | 🔴 **READY** | Complete admin CRUD |

---

## 🚀 Execute Phase 5

Copy to Claude Code:

```
Filesystem:read_file(path: "C:\xampp\htdocs\Portfolio_v2\.claude\prompts\phase-5_admin-panel-completion_20251014-1030.md")
```

---

## 📝 Prompt Structure

Each prompt has:
1. **Objective** - Clear definition of "done"
2. **Critical Issues** - Bugs to fix
3. **What to Create** - Files and components
4. **Subagent Tasks** - Who does what
5. **Success Criteria** - Checklist
6. **Reference Code** - Examples to copy

---

## ⚡ Phase 5 Quick Stats

- **Lines:** ~300 (reduced from 1200+)
- **Time:** 12-16 hours
- **Subagents:** 6
- **Files Created:** ~30 components
- **Screenshots:** 40+ required

---

## 📚 Related Docs

- `README.md` - Project overview
- `PROJECT_STATUS.md` - Current progress
- `.claude/agents/README.md` - Subagent info

---

**Last Updated:** October 14, 2025 11:00 WIB
