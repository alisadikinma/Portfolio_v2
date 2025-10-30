# Task: Frontend Performance Optimization

## Problem
Users see "No data" flash on every page load. No caching = wasted server bandwidth + poor UX.

## Solution
Implement TanStack Query for professional caching layer.

## Success Criteria
- Zero "no data" flashes - instant content from cache
- 70% reduction in redundant API calls
- Sub-1s page load on repeat visits

---

## Architecture Decision

**Use TanStack Query** - industry standard caching library.

**Cache Policy:**
- Blog posts: 5min stale / 30min cache
- Projects: 10min stale / 1hr cache
- Awards/Settings: 1hr stale / 24hr cache

**Why:** Battle-tested, handles edge cases, zero backend changes.

---

## Execution

**Delegate to:** `@vue-expert` (primary executor)

**Files to change:**
```
frontend/src/main.js                    # Setup QueryClient
frontend/src/composables/*.js           # Migrate to useQuery
frontend/src/views/{Home,Awards,Blog,Projects}.vue  # Remove manual fetching
```

**Key Requirements:**
1. Zero breaking changes
2. Keep same component APIs
3. Add loading skeletons (not blank states)
4. Update README with caching guide

---

## Deliverables

1. Working TanStack Query integration
2. All composables migrated
3. Documentation updated
4. QA checklist completed

**Estimate:** 1 hour

---

## Context Files
Read first:
- `C:\xampp\htdocs\Portfolio_v2\CLAUDE.md`
- `C:\xampp\htdocs\Portfolio_v2\frontend\README.md`

Execute when ready.