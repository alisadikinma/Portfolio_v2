# PROJECT_INSTRUCTIONS.md

**AI Behavior Rules - Read this FIRST**

---

## 🚨 MANDATORY FIRST STEP

**Read these 3 files at conversation start:**
```
C:\xampp\htdocs\Portfolio_v2\README.md
C:\xampp\htdocs\Portfolio_v2\backend\README.md
C:\xampp\htdocs\Portfolio_v2\frontend\README.md
```

All project details (structure, URLs, tech stack, credentials) are in README files. Don't ask, just read.

---

## 🚫 BUG FIX RULES

**STOP creating files after bug fixes.**

**DO:**
- Fix the bug
- Show summary in chat
- List modified files

**DON'T:**
- Create BUG_FIX_SUMMARY.md
- Create CHANGES.md
- Create any documentation files

**Exception:** Only if user explicitly asks for documentation.

**Response Format:**
```
Fixed: [issue]
Changed: [what + why]
Files: [list]
Test: [how to verify]
```

---

## 🎯 COMMUNICATION RULES

**Be direct. No fluff.**

❌ **NEVER:**
- "Let me first understand..."
- "I'll analyze step by step..."
- "Before we proceed..."
- Ask permission for obvious next steps
- Explain what you're about to do

✅ **ALWAYS:**
- Read files → Fix → Show results
- Use thinking blocks internally
- Explain AFTER, not BEFORE

---

## 🪟 WINDOWS TOOLS - MANDATORY

**ONLY use Filesystem:* tools**

✅ **CORRECT:**
```
Filesystem:read_file(path: "C:\xampp\...")
Filesystem:edit_file(path: "C:\xampp\...", edits: [...])
Filesystem:write_file(path: "C:\xampp\...", content: "...")
```

❌ **FORBIDDEN:**
```
view(path)           → Use Filesystem:read_file
str_replace(path)    → Use Filesystem:edit_file
bash_tool /mnt/      → Use Filesystem:* with C:\
create_file(path)    → Use Filesystem:write_file
```

**Backend:** XAMPP Port 80 (NOT php artisan serve)
**Frontend:** Vite Port 5173

---

## 👥 SUBAGENT SYSTEM

**Location:** `C:\xampp\htdocs\Portfolio_v2\.claude\agents\`

**Read before delegating:**
```
Filesystem:read_file("C:\xampp\htdocs\Portfolio_v2\.claude\agents\README.md")
```

**Simple tasks (1-2 files):** Handle directly
**Complex tasks (3+ files):** Use subagents

**Available agents:**
- `orchestrator` - Multi-agent coordination
- `laravel-specialist` - Backend APIs
- `vue-expert` - Frontend components
- `database-administrator` - DB optimization
- `qa-expert` - Testing
- `documentation-engineer` - Docs

Details in .claude/agents/README.md - don't repeat them here.

---

## 🎨 DELEGATION STYLE

**High-level directive only:**

✅ **GOOD:**
```
Objective: Complete blog CRUD with search
Architecture: REST API + Vue SPA + pagination
Requirements: [list functional/security/performance needs]
Success: [checklist]
```

❌ **BAD:**
```
Step 1: Create PostController.php at line 10
Step 2: Add method createPost() at line 25
Step 3: Return response with status 201
[... micromanagement ...]
```

**CTO style:** Define WHAT and WHY, let specialists decide HOW.

---

## ⚡ TASK WORKFLOW

**Decision tree:**

```
1-2 files? → Fix directly
Single domain (backend OR frontend)? → Use single specialist
Multiple domains (backend + frontend + DB)? → Use orchestrator
```

**For complex tasks:**
1. Read .claude/agents/README.md
2. Use CTO-style prompt (see template in .claude/agents/)
3. Delegate to Claude Code
4. Review results

---

## 📝 CRITICAL REMINDERS

- **All project info:** See README.md files (structure, URLs, credentials)
- **Windows paths:** Always use `C:\xampp\...`
- **Tools:** Filesystem:* ONLY
- **Backend:** XAMPP Port 80
- **Frontend:** Vite Port 5173
- **Bug fixes:** Summary in chat, NO files
- **Communication:** Direct, no fluff

---

**Last Updated:** October 15, 2025  
**Project:** Portfolio_v2 (52% Complete)