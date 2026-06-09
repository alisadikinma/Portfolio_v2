> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan is a frontend-only simplification. Dead config fields are
> HIDDEN (removed from render) but their keys stay in `linkedinFormData` so the save
> payload is byte-identical — backend untouched, fully reversible. NEVER delete the
> backend settings rows or validation as part of this plan.

## Goal

Simplify two over-loaded admin tabs (`/admin/settings?tab=sosmed` and `?tab=scheduler`)
that confuse the operator. Two moves: (1) **cut 3 dead/superseded fields** from the
Sosmed→LinkedIn form (cancel window, format-mix governor ×3, depth threshold — all
inert under the June-9 force-carousel ship), and (2) **tier the Scheduler** so the ~8
operator-tunable cron rows show by default while ~9 self-healing internals + 4 locked
placeholders collapse into accordions. No backend change, no migration, reversible.

## Architecture Context

Per CLAUDE.md:
- `linkedin_force_carousel='true'` (June 9) **supersedes the format-mix governor** —
  "plugin ignores `format_preference`", force-carousel always wins → the 3 governor
  fields (`linkedin_format_carousel_target_ratio/_lookback_window/_governor_enabled`)
  no longer affect anything.
- `linkedin_cancel_window_minutes` is labelled in-UI "legacy, ignored post May-12"
  (fixed-slot scheduler replaced it).
- `linkedin_depth_score_threshold` gates auto-publish of **text** posts only; under
  force-carousel there are no text posts → moot.
- Scheduler is DB-driven (`scheduled_commands` + `DynamicScheduleRegistrar`); rows
  already carry `is_placeholder`. Frontend [SchedulerSettings.vue](../../frontend/src/views/admin/SchedulerSettings.vue)
  groups by `category` via `SUB_TABS` + `visibleGroups`.
- Tests: project uses Node `.test.mjs` smoke tests (e.g.
  [linkedinHelpers.test.mjs](../../frontend/src/views/admin/linkedinHelpers.test.mjs),
  [postsStale.test.mjs](../../frontend/src/views/admin/postsStale.test.mjs)) — assert
  against exported pure functions or component source strings.

## Tech Stack

Vue 3 `<script setup>` + Tailwind 4. Reuse existing tab/`<details>` accordion patterns.
No new deps. Pure helper extracted to a `.js` module for real TDD.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| LinkedIn settings form | `settings` group `linkedin` | `SettingsController::getLinkedInSettings` → `linkedinFormData` | Yes | Use existing — only stop rendering 3 field groups; keep keys in formData + save payload |
| Scheduler rows | `scheduled_commands` table | `useScheduler()` TanStack | Yes | Use existing — classify client-side, no schema change |
| Tier classifier | hardcoded `OPERATOR_SIGNATURES` allowlist | new `schedulerTiers.js` pure module | No | Create (pure fn, TDD'd) |

## Implementation Plan

### Phase A: Scheduler tiering (pure classifier + collapse)

**Estimated time:** ~12 min

**Files:**
- Create: `frontend/src/views/admin/schedulerTiers.js` (pure: `OPERATOR_SIGNATURES`, `classifyTier(signature, isPlaceholder)`)
- Create: `frontend/src/views/admin/schedulerTiers.test.mjs`
- Modify: `frontend/src/views/admin/SchedulerSettings.vue`

| Code Deliverable | Design Deliverable | Verification |
|------------------|--------------------|--------------|
| `classifyTier` + collapse UI | Reuse existing table + `<details>` accordion tokens | Tests + build |

**Steps:**
1. Write failing test for `classifyTier`. Expected error: `Error: Cannot find module './schedulerTiers.js'` (then assertions: `social-cross-post:scan`→`operator`, `linkedin:reap-stuck`→`system`, `placeholder:tiktok-publish`→`placeholder`, unknown→`system`).
2. Run `node src/views/admin/schedulerTiers.test.mjs`, confirm it fails for the expected reason.
3. Implement `schedulerTiers.js`: `OPERATOR_SIGNATURES` Set = {`content:auto-pipeline`, `content:pull-trending-daily`, `content:flag-stale-posts`, `social:publish-slot`, `linkedin:scan-blog`, `linkedin:auto-schedule`, `social-cross-post:scan`, `newsletter:send-weekly`}; `classifyTier` returns `placeholder` if `isPlaceholder`, else `operator` if in set, else `system`.
4. Run test, confirm all pass.
5. In `SchedulerSettings.vue`: import `classifyTier`; within each category group split `rows` into `operatorRows` / `systemRows` / `placeholderRows`; render operator rows in the existing table; wrap system rows in `<details>` "⚙️ System internals (auto-managed) — N" (closed) and placeholder rows in `<details>` "🔒 Coming soon — N" (closed). Empty subsets render nothing.
6. `npm run build`, confirm clean.
7. Commit: "feat(admin): tier scheduler — operator rows default, internals+placeholders collapsed"

**Verification:**
- [ ] `node src/views/admin/schedulerTiers.test.mjs` passes (4+ cases)
- [ ] `classifyTier` is pure (no Vue/DOM imports) — real data from `useScheduler()` rows unchanged
- [ ] Default view shows only operator-tier rows expanded; internals/placeholders behind closed `<details>`
- [ ] No placeholder/TODO comments in new code
- [ ] `npm run build` clean

### Phase B: Sosmed/LinkedIn dead-field cut (hide-not-delete) + grouping

**Estimated time:** ~12 min

**Files:**
- Modify: `frontend/src/views/admin/SettingsForm.vue`
- Create: `frontend/src/views/admin/settingsFormSosmed.test.mjs`

| Code Deliverable | Design Deliverable | Verification |
|------------------|--------------------|--------------|
| Remove 3 dead field render-blocks + section labels | Reuse existing form section/label tokens | Source-assertion test + build |

**Steps:**
1. Write failing test for the Sosmed form source. Expected error: `AssertionError: depth_score_threshold input still rendered`. Test reads `SettingsForm.vue` source and asserts: (a) NO `v-model="linkedinFormData.linkedin_depth_score_threshold"`, `..._cancel_window_minutes`, `..._format_carousel_target_ratio`, `..._format_lookback_window`, `..._format_governor_enabled` bindings remain; (b) the 5 keys STILL exist in the `linkedinFormData` ref default block AND in the save payload block (payload unchanged); (c) section headers `>Connection<`, `>Publishing<`, `>First comment<` present.
2. Run `node src/views/admin/settingsFormSosmed.test.mjs`, confirm it fails (fields still rendered).
3. In `SettingsForm.vue`: delete the render blocks for depth threshold (~510-525), cancel window (~528-545), format governor + target ratio + lookback (~610-665). Do NOT touch the `linkedinFormData` default block (2128-2141), the loader (2185-2195), or the save payload (2217-2225) — those keys stay so the payload is byte-identical.
4. Add three section subheaders ("Connection", "Publishing", "First comment") wrapping the surviving LinkedIn fields.
5. Run test, confirm all pass.
6. `npm run build`, confirm clean.
7. Commit: "feat(admin): cut 3 dead LinkedIn settings fields (force-carousel supersedes) + group form"

**Verification:**
- [ ] `node src/views/admin/settingsFormSosmed.test.mjs` passes
- [ ] Dead-field `<input>` bindings gone from render; the 5 keys retained in formData + save payload (reversible, backend untouched)
- [ ] Surviving fields grouped under Connection / Publishing / First comment
- [ ] No placeholder/TODO comments in new code
- [ ] `npm run build` clean

## Notes / Out of Scope

- Backend cleanup (dropping the now-unused `linkedin_format_*` / `linkedin_cancel_window_minutes`
  settings rows + `LinkedInFormatMixGovernor` service) is a **separate** follow-up — NOT this plan.
- No `scheduled_commands` schema change; tiering is purely a frontend display concern.
