> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# AI Portfolio Total Redesign

## Goal

Reposition Ali Sadikin from "Project Manager" to **AI Builder & Automation Architect** targeting US/AU/EU job markets. Redesign the homepage with 9 scroll-animated sections showcasing AI skills (Vibe Coding, AI Automation, AI Agents, AI Video Gen) each with VEO 3.1 video, asymmetric layouts, and admin-togglable sections. Integrate chatbot widget using existing OpenRouter + Gemini 2.5 Flash Lite backend.

## Architecture Context (from CLAUDE.md)

**Backend:**
- `page_sections` table exists with `page_type`, `section_type`, `is_active`, `sequence` fields
- `PageSectionController.php` handles CRUD + reorder
- `ChatbotController.php` already uses OpenRouter API with `config/ai.php`
- `useChatbot.js` composable exists and works
- `usePageSections.js` composable exists with TanStack Query caching

**Frontend:**
- GSAP 3.14.2 already installed, `useScrollReveal.js` composable exists
- `CinematicHero.vue` has VEO video with hero-bg.mp4 ready
- Current homepage has 9 sections but wrong components (old skill demos, activity feed)
- Menu items fetched from API via `useMenuItems()` + `menu_items` table

**Current page_sections table is missing:** `title`, `description`, `video_url`, `content` JSON fields - needs migration.

## Tech Stack

- Vue 3.5 (Composition API, `<script setup>`)
- Tailwind CSS 4 (Rolldown-Vite 7.1)
- GSAP 3.14 + ScrollTrigger (already installed)
- OpenRouter API + google/gemini-2.5-flash-lite (already configured)
- Geist font family (replacing Plus Jakarta Sans)

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Hero Video | `/videos/hero-bg.mp4` | Static | Yes | Keep as-is |
| Skills Reel | Static data array | None needed | N/A | Hardcode skills list |
| Skill Showcases | `page_sections` API | `usePageSections()` | Partial | Add title/desc/video_url to table |
| Section Toggle | `page_sections.is_active` | `usePageSections()` | Yes | Use existing |
| Section Order | `page_sections.sequence` | `usePageSections()` | Yes | Use existing |
| Projects Bento | `/api/projects?featured=1` | `api.get('/projects')` | Yes | Use existing |
| Latest Blog | `/api/posts?per_page=3` | `api.get('/posts')` | Yes | Use existing |
| Stats Data | Static in component | None needed | N/A | Hardcode stats |
| Chatbot Backend | `POST /api/chatbot/ask` | `ChatbotController` | Yes | Minor update |
| Chatbot Frontend | `useChatbot()` composable | `useChatbot.js` | Yes | Upgrade widget UI |
| Nav Menu | `/api/menu-items` | `useMenuItems()` | Yes | Update DB entries to 4 items |

---

## Phase 1: Foundation Cleanup (10 min)

**Files:**
- Delete: `frontend/src/components/CustomCursor.vue`
- Modify: `frontend/src/layouts/DefaultLayout.vue`
- Modify: `frontend/src/style.css`
- Modify: `frontend/tailwind.config.js`
- Modify: `frontend/index.html`

**Steps:**
1. Delete `frontend/src/components/CustomCursor.vue`
2. Remove CustomCursor import and `<CustomCursor />` from `DefaultLayout.vue`
3. In `style.css`: replace `Plus Jakarta Sans` with `Geist` in @import URL and --font-sans
4. In `tailwind.config.js`: replace `Plus Jakarta Sans` with `Geist` in fontFamily.sans
5. In `index.html`: update font preload to Geist
6. Search all `.vue` files for `h-screen` in public-facing views, replace with `min-h-[100dvh]` (skip admin views)

**Verification:**
- [ ] `CustomCursor.vue` deleted, no import errors
- [ ] `npm run build` passes
- [ ] Geist font loads in browser (check Network tab)
- [ ] No `h-screen` in public view files (Home, About, Work, Blog, Contact, CinematicHero)

---

## Phase 2: Database Migration — Extend page_sections (5 min)

**Files:**
- Create: `backend/database/migrations/XXXX_add_content_fields_to_page_sections_table.php`
- Modify: `backend/app/Models/PageSection.php`

**Steps:**
1. Create migration: add `title` (string nullable), `description` (text nullable), `video_url` (string nullable), `content` (json nullable) to `page_sections`
2. Update `PageSection.php` model: add new fields to `$fillable` and `$casts` (content => array)
3. Run migration: `D:\xampp\php\php.exe artisan migrate`
4. Seed homepage sections: insert 9 rows for page_type='homepage' with section_types: hero, skills-reel, skill-vibe-coding, skill-ai-automation, skill-ai-agents, skill-ai-video, featured-projects, latest-blog, stats-cta

**Verification:**
- [ ] Migration runs without error
- [ ] `page_sections` table has title, description, video_url, content columns
- [ ] 9 homepage section rows exist in DB
- [ ] `D:\xampp\php\php.exe artisan tinker` -> `PageSection::where('page_type','homepage')->count()` returns 9

---

## Phase 3: Video Reveal Composable (5 min)

**Files:**
- Create: `frontend/src/composables/useVideoReveal.js`

**Steps:**
1. Create `useVideoReveal.js` composable:
   - Accept a template ref (video element)
   - Use IntersectionObserver (threshold 0.3)
   - On enter viewport: `video.play()`
   - On leave viewport: `video.pause()`
   - Cleanup observer on unmount
2. Export: `{ setupVideoReveal }` function

**Verification:**
- [ ] Composable exports `setupVideoReveal(videoRef)` function
- [ ] Uses IntersectionObserver, not scroll event listener
- [ ] Calls `play()` on enter, `pause()` on leave
- [ ] Cleanup via `onUnmounted`

---

## Phase 4: SkillShowcase.vue — Reusable Zig-Zag Component (10 min)

**Files:**
- Create: `frontend/src/components/home/SkillShowcase.vue`

**Steps:**
1. Create `SkillShowcase.vue` with props:
   - `title: String` — section heading
   - `subtitle: String` — eyebrow tag text
   - `description: String` — body text
   - `videoSrc: String` — path to VEO mp4
   - `links: Array` — `[{ label, url, icon? }]`
   - `reversed: Boolean` — flip layout direction
   - `accentColor: String` — 'gold' | 'cyan' | 'indigo' (default: 'gold')
2. Layout: CSS Grid `grid-cols-1 md:grid-cols-12` with 7/5 split
   - Normal: video cols 1-7, text cols 8-12
   - Reversed: text cols 1-5, video cols 6-12
3. Video element with `useVideoReveal` auto-play on scroll
4. Text side: eyebrow tag + heading + description + link buttons
5. Mobile: single column, video on top
6. All transitions use `cubic-bezier(0.32, 0.72, 0, 1)` at 700ms
7. Scroll reveal via `useScrollReveal` on mount

**Verification:**
- [ ] Component renders with all props
- [ ] `reversed` prop flips layout correctly
- [ ] Video auto-plays on scroll enter, pauses on leave
- [ ] Mobile collapses to single column at `< 768px`
- [ ] No emoji icons, SVG only
- [ ] `npm run build` passes

---

## Phase 5: SkillsReel.vue — Kinetic Marquee (8 min)

**Files:**
- Create: `frontend/src/components/home/SkillsReel.vue`

**Steps:**
1. Create `SkillsReel.vue` with infinite horizontal marquee
2. Skills array (hardcoded): Vibe Coding, AI Automation, AI Agents, AI Video Generation, Full-Stack Development, Prompt Engineering, No-Code Building, Claude Code Plugins
3. Each skill: SVG icon + label in a bezel-shell-sm pill
4. Duplicate array 2x for seamless loop
5. CSS animation: `translateX(0)` to `translateX(-50%)` infinite linear 30s
6. Pause on hover (animation-play-state: paused)
7. py-12 section with subtle top/bottom borders

**Verification:**
- [ ] Marquee scrolls infinitely without jump/gap
- [ ] Pauses on hover
- [ ] No emoji, SVG icons only
- [ ] Mobile: marquee still works, text readable
- [ ] `npm run build` passes

---

## Phase 6: ProjectsBento.vue — Asymmetric Grid (8 min)

**Files:**
- Create: `frontend/src/components/home/ProjectsBento.vue`

**Steps:**
1. Create `ProjectsBento.vue` that fetches from `/api/projects?featured=1&per_page=6`
2. Asymmetric CSS Grid layout:
   - Row 1: `grid-cols-12` — first project `col-span-7`, second `col-span-5`
   - Row 2: `grid-cols-12` — third `col-span-5`, fourth `col-span-7`
   - Row 3 (if 5-6 projects): `grid-cols-12` — `col-span-6` each
3. Each card: bezel-shell-sm with thumbnail, eyebrow category, title, 1-line description, "View" arrow
4. Mobile: single column `grid-cols-1`
5. Section header with eyebrow tag + "View All Work" button linking to `/work`
6. Loading skeleton matching grid dimensions
7. Scroll reveal via `useScrollReveal`

**Verification:**
- [ ] Grid is asymmetric (7/5 and 5/7 alternating), NOT 3-equal-column
- [ ] Data fetched from real API `/api/projects`
- [ ] Loading skeleton shown during fetch
- [ ] Mobile collapses to single column
- [ ] "View All Work" links to `/work`
- [ ] `npm run build` passes

---

## Phase 7: Home.vue Rewrite (12 min)

**Files:**
- Modify: `frontend/src/views/Home.vue`

**Steps:**
1. Rewrite `Home.vue` to compose 9 sections with admin toggle:
   - Import `usePageSections` composable
   - Fetch active sections on mount: `fetchActiveSections('homepage')`
   - Helper: `isSectionActive(type)` checks `sections.value`
2. Section 1: `<CinematicHero />` — keep existing, wrap in `v-if="isSectionActive('hero')"`
3. Section 2: `<SkillsReel />` — new component
4. Sections 3-6: `<SkillShowcase />` x4 with different props:
   - Vibe Coding (reversed=false, accent=gold, videoSrc=/videos/vibe-coding.mp4)
   - AI Automation (reversed=true, accent=cyan, videoSrc=/videos/ai-automation.mp4)
   - AI Agents (reversed=false, accent=indigo, videoSrc=/videos/ai-agents.mp4)
   - AI Video Gen (reversed=true, accent=gold, videoSrc=/videos/ai-video.mp4)
5. Section 7: `<ProjectsBento />` — new component
6. Section 8: `<LatestBlog />` — refactored (1 large + 2 small, not 3-equal)
7. Section 9: `<StatsBar />` + `<CTASection />` combined
8. Remove old imports: SkillsDemoSection, AchievementsHighlight, ActivityFeed, old testimonials
9. Each section wrapped in `v-if="isSectionActive('section-type')"`

**Verification:**
- [ ] All 9 sections render on homepage
- [ ] Sections respect `is_active` toggle from page_sections API
- [ ] Skill showcase videos will auto-play on scroll (placeholder videos OK for now)
- [ ] No old/unused component imports remain
- [ ] `npm run build` passes
- [ ] No 3-equal-column layouts on the page

---

## Phase 8: LatestBlog.vue Refactor — Asymmetric Layout (5 min)

**Files:**
- Modify: `frontend/src/components/home/LatestBlog.vue`

**Steps:**
1. Refactor grid from 3-equal-column to asymmetric:
   - First post: `col-span-7` large card with image + full excerpt
   - Posts 2-3: stacked in `col-span-5` as smaller cards
2. Mobile: single column stack
3. Keep existing API fetch logic `/api/posts?per_page=3`
4. Ensure bezel-shell-sm card architecture

**Verification:**
- [ ] Layout is asymmetric (7/5 split), NOT 3-equal-column
- [ ] First post visually larger than posts 2-3
- [ ] Data from real API
- [ ] Mobile: single column
- [ ] `npm run build` passes

---

## Phase 9: AskAliChatbot.vue — Widget Upgrade (10 min)

**Files:**
- Modify: `frontend/src/components/AskAliChatbot.vue`

**Steps:**
1. Rewrite chatbot widget UI:
   - Floating button: bottom-right, `w-12 h-12` rounded-full, accent-gold bg, SVG chat icon (no emoji)
   - Click toggles chat panel with spring transition
2. Chat panel: `w-80 h-[28rem]` fixed bottom-right, bezel-shell with bezel-core
   - Header: "Ask Ali" + close button
   - Message area: scrollable, user bubbles (right, gold bg), AI bubbles (left, glass bg)
   - Typing indicator: 3 pulsing dots when loading
   - Input: bezel-shell-sm input + send button
3. Suggested questions on empty state (3 pills):
   - "What AI skills does Ali have?"
   - "Tell me about Ali's projects"
   - "Can Ali build automation for my business?"
4. Use existing `useChatbot()` composable — no changes needed (already calls `/api/chatbot/ask`)
5. Session persistence: messages survive page navigation (composable state is reactive)

**Verification:**
- [ ] Floating button renders bottom-right
- [ ] Click opens panel with smooth transition
- [ ] Messages send to `/api/chatbot/ask` and display response
- [ ] Typing indicator shows during API call
- [ ] Suggested questions pre-fill input on click
- [ ] No emoji anywhere, SVG icons only
- [ ] Mobile: panel takes full width `w-full` below `md:`
- [ ] `npm run build` passes

---

## Phase 10: Work.vue — 2-Tab Cleanup (5 min)

**Files:**
- Modify: `frontend/src/views/Work.vue`

**Steps:**
1. Remove "Case Studies" tab — keep only Projects | Awards
2. Ensure projects grid uses asymmetric bento (not 3-equal-column):
   - `grid-cols-1 md:grid-cols-12` with alternating 7/5 spans
3. Keep search + category filter logic
4. Ensure all cards use bezel-shell-sm architecture

**Verification:**
- [ ] Only 2 tabs: Projects, Awards
- [ ] Project grid is NOT 3-equal-column
- [ ] Search/filter still works
- [ ] `npm run build` passes

---

## Phase 11: Final Cleanup (8 min)

**Files:**
- Delete: `frontend/src/components/skills/PromptDemo.vue` (if exists)
- Delete: `frontend/src/components/skills/AutomationViz.vue` (if exists)
- Delete: `frontend/src/components/skills/RagDemo.vue` (if exists)
- Delete: `frontend/src/components/skills/ImageShowcase.vue` (if exists)
- Delete: `frontend/src/components/skills/SkillsDemoSection.vue`
- Delete: `frontend/src/components/home/AchievementsHighlight.vue` (if not used elsewhere)
- Delete: `frontend/src/components/home/ActivityFeed.vue` (if not used elsewhere)
- Modify: `CLAUDE.md`

**Steps:**
1. Delete old skill demo components no longer imported
2. Verify no remaining imports reference deleted files
3. Run `npm run build` — fix any errors
4. Update CLAUDE.md:
   - Update homepage section list (9 new sections)
   - Add new components to component map
   - Note chatbot uses OpenRouter + Gemini 2.5 Flash Lite
   - Update nav: Home | Work | About | Contact
5. Run final build to confirm zero errors

**Verification:**
- [ ] No dead imports in any file
- [ ] `npm run build` passes with zero errors
- [ ] CLAUDE.md reflects new architecture
- [ ] All deleted files confirmed not imported elsewhere

---

## Design Rules Summary (Enforced Across All Phases)

| Rule | Enforcement |
|------|-------------|
| No CustomCursor | Deleted in Phase 1 |
| No 3-equal-column grids | Asymmetric bento / zig-zag only |
| No centered hero text | Existing hero exempt; skill sections use split layout |
| No emoji icons | SVG only throughout |
| `min-h-[100dvh]` not `h-screen` | Fixed in Phase 1 |
| Spring transitions | `cubic-bezier(0.32, 0.72, 0, 1)` at 700ms |
| Video auto-play on scroll | IntersectionObserver via `useVideoReveal.js` |
| Mobile collapse | All grids → single column `w-full px-4` below 768px |
| Admin toggle | All sections via `page_sections` `is_active` field |

---

**Total estimated time:** ~86 minutes across 11 phases
**Parallel candidates:** Phase 3 (composable) + Phase 5 (SkillsReel) + Phase 6 (ProjectsBento) are independent
