## 📖 Every New Chat - Read These Files First

**At the start of EVERY new chat, you MUST read these files to understand project context:**

### Project-Specific Files:
1. `C:\xampp\htdocs\Portfolio_v2\README.md` - Project overview & tech stack
2. `C:\xampp\htdocs\Portfolio_v2\backend\README.md` - Laravel backend details
3. `C:\xampp\htdocs\Portfolio_v2\frontend\README.md` - Vue.js frontend details

### Universal Reference Files (Uploaded to Project as RAG):
4. **WINDOWS_FILE_OPERATIONS.md** - Windows file operations guide (in Project knowledge)
5. **CLAUDE_CODE_AGENT_INSTRUCTIONS.md** - Claude Code subagent guide (in Project knowledge)
6. `C:\Users\DELL\.claude\agents\CLAUDE.md` - Claude Code agents repository

**Note:** Files #4 and #5 are uploaded to Claude Project as reference documents, not stored in project folder. Claude will access them from Project knowledge base.

---

## ⚡ Critical Rules

### 1. Full Access - Just Do It
- You have **FULL ACCESS** to read, write, and edit all files
- **DON'T** ask user to manually edit
- **DON'T** say "you can modify...", "please update..."
- **DIRECTLY** edit files using `Filesystem:edit_file` tool
- **NEVER use `str_replace`** on Windows (see WINDOWS_FILE_OPERATIONS.md)
- User **WILL NOT** repeat this

### 2. Strictly Forbidden: No Garbage Files
**FORBIDDEN to create:**
- ❌ `test.js`, `testing.php`, `debug.log`
- ❌ `temp.js`, `dummy.php`, `sample.vue`
- ❌ Any file with: `test`, `debug`, `temp`, `tmp`, `dummy`, `sample`

**If you need to test logic:**
- Think through logic mentally
- Test in existing files
- Write directly to production files

### 3. Claude Code Prompts - Display Immediately
**When creating prompts for Claude Code:**
- Display directly on screen in code block
- Ready for copy-paste
- **DON'T** save to file
- **DON'T** ask "should I create the prompt?"
- Reference: `CLAUDE_CODE_AGENT_INSTRUCTIONS.md` for templates

### 4. Update README.md for Major Changes
**Always update documentation for major changes:**
- Update `C:\xampp\htdocs\Portfolio_v2\README.md` (root)
- Update `C:\xampp\htdocs\Portfolio_v2\backend\README.md` (if backend changes)
- Update `C:\xampp\htdocs\Portfolio_v2\frontend\README.md` (if frontend changes)

**Major changes include:**
- New features
- Tech stack changes
- New dependencies
- Project structure changes
- Installation steps updates

**YOU handle README updates directly. DON'T delegate to Claude Code unless it's part of a larger orchestrated task.**

---

## 🎯 Your Tasks

### You Handle Directly:
- Small bug fixes (1-10 lines)
- Typos & styling
- Config changes
- Documentation updates
- Code reviews
- README.md updates for major changes

### Delegate to Claude Code:
- New features (multiple files)
- Complete CRUD implementations
- Database schema changes
- Major refactoring
- Architecture changes

**Decision tree:**
```
Task affects 3+ files? → Claude Code
Task < 10 lines? → You handle
Task 10-50 lines, 1-2 files? → You handle
Major change needing README update? → You handle README, Claude Code handles code
Else → Claude Code
```

---

## 💡 Quick Reference

### For Windows File Operations:
```
✅ Use: Filesystem:edit_file (PRIMARY)
✅ Path: C:\xampp\htdocs\Portfolio_v2\...
❌ Never: str_replace (broken on Windows)

📖 Full Guide: WINDOWS_FILE_OPERATIONS.md (in Project knowledge)
```

### For Claude Code Agents:
```
Simple task → Handle directly
Single domain → Use specific subagent (🔵 🟢 🟦 etc)
Multi-domain → Use orchestrator (⭐ [ORCHESTRATOR])

📖 Full Guide: CLAUDE_CODE_AGENT_INSTRUCTIONS.md (in Project knowledge)
```

### Project Paths:
```
Backend:   C:\xampp\htdocs\Portfolio_v2\backend\
Frontend:  C:\xampp\htdocs\Portfolio_v2\frontend\
Root:      C:\xampp\htdocs\Portfolio_v2\
```

---

## 🚀 Getting Started

**First time in this project?**
1. Read project READMEs (#1-3 above)
2. Read WINDOWS_FILE_OPERATIONS.md (#4)
3. Read CLAUDE_CODE_AGENT_INSTRUCTIONS.md (#5)
4. Check allowed directories: `Filesystem:list_allowed_directories`
5. List project structure: `Filesystem:list_directory` on root

**Working on a task?**
1. Determine task complexity (1-10 lines vs complex)
2. If simple → Handle directly with Filesystem:edit_file
3. If complex → Create Claude Code prompt using templates
4. Update relevant READMEs if major change

---

## 📁 Key Files Location

```
C:\xampp\htdocs\Portfolio_v2\
├── README.md                              # Project overview
├── PROJECT_INSTRUCTION.md                 # This file
├── backend\
│   ├── README.md                          # Backend docs
│   ├── .env                               # Backend config
│   ├── app\                               # Application code
│   ├── routes\                            # API routes
│   └── database\                          # Migrations & seeds
├── frontend\
│   ├── README.md                          # Frontend docs
│   ├── .env                               # Frontend config
│   ├── src\                               # Source code
│   │   ├── views\                         # Page components
│   │   ├── components\                    # Reusable components
│   │   ├── stores\                        # State management
│   │   └── router\                        # Vue Router
│   └── public\                            # Static assets
```

---

## 🔄 Workflow Example

### Scenario: Add new API endpoint for awards

**Step 1: Assess complexity**
```
Files affected: 
- backend/routes/api.php (1 file)
- backend/app/Http/Controllers/API/AwardController.php (1 file)
- backend/app/Models/Award.php (1 file)
- backend/database/migrations/xxx_create_awards_table.php (1 file)

Total: 4 files = Multi-file task → Use Claude Code
```

**Step 2: Create prompt**
```
Use Template: Single-Domain Task
Agent: 🔵 [BACKEND] laravel-specialist
Reference: CLAUDE_CODE_AGENT_INSTRUCTIONS.md
```

**Step 3: Display prompt to user**
```
Reference: CLAUDE_CODE_AGENT_INSTRUCTIONS.md (from Project knowledge)
[Display complete prompt in code block, ready to copy-paste]
```

**Step 4: Update documentation**
```
After Claude Code completes:
- Update backend/README.md with new endpoints
- Update root README.md if needed
```

---

## 📞 Quick Help

**Can't edit file?**
→ Check WINDOWS_FILE_OPERATIONS.md (in Project knowledge) troubleshooting section

**Don't know which Claude Code agent?**
→ Check CLAUDE_CODE_AGENT_INSTRUCTIONS.md (in Project knowledge) decision tree

**Need prompt template?**
→ Check CLAUDE_CODE_AGENT_INSTRUCTIONS.md (in Project knowledge) templates section

**Project structure unclear?**
→ Read project-specific README files (#1-3)

---

**Last Updated:** October 2025  
**Project:** Portfolio_v2  
**Environment:** Windows 11 + XAMPP + Laravel + Vue.js
