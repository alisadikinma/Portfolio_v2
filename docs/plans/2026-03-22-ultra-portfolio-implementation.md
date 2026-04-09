> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Transform Portfolio v2 from a standard portfolio site into an immersive, dark cinematic experience branded around "AI Generalist Expert." 12 features across 6 phases: design system overhaul, cinematic scroll-driven hero, page rebuilds, 3D globe, AI chatbot, interactive skills demo, auto-blog, GEO optimization, i18n, activity feed, case studies, and newsletter automation.

## Architecture Context (from CLAUDE.md)

**Backend:** Laravel 12 + MySQL 8 + Sanctum 4 + Filament 4.1
**Frontend:** Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + Vue Router 4.5 + TanStack Query 5.90 + Tailwind CSS 4

**Existing key files:**
- `frontend/src/style.css` — Tailwind v4 @theme config, custom @layer components
- `frontend/tailwind.config.js` — Extended theme (colors, fonts, animations)
- `frontend/src/layouts/DefaultLayout.vue` — Main layout wrapper
- `frontend/src/components/TheNavigation.vue` — Dynamic nav from API
- `frontend/src/components/TheFooter.vue` — Dynamic footer from API
- `frontend/src/components/HeroSectionWOW.vue` — Current hero (654 lines)
- `frontend/src/views/Home.vue` — Current homepage (2073 lines)
- `frontend/src/views/About.vue` — Current about page (800+ lines)
- `frontend/src/router/index.js` — 50+ routes with lazy loading
- `frontend/src/composables/` — 20 composables (usePosts, useProjects, useAwards, etc.)
- `frontend/src/stores/` — 14 Pinia stores
- `backend/routes/api.php` — 120+ endpoints
- `backend/app/Traits/HasSeoFields.php` — SEO trait with ai_summary, faq_schema

**Current design tokens (style.css @theme):**
- Colors: primary=indigo, secondary=violet, accent=emerald, gray=slate
- Fonts: Inter (sans), Poppins (display), Merriweather (serif), JetBrains Mono (mono)
- Glass class: `bg-white/80 backdrop-blur-lg`
- Dark mode: class-based (.dark)

**Existing API endpoints used by frontend:**
- GET /api/posts, /api/projects, /api/awards, /api/testimonials
- GET /api/settings/about, /api/settings/site, /api/menu-items, /api/page-sections
- GET /api/categories, /api/galleries, /api/galleries/{id}/items
- POST /api/contact, /api/automation/posts (auth)

**Existing composables to reuse:**
- `useApi.js` — Base Axios wrapper
- `usePosts.js`, `useProjects.js`, `useAwards.js` — TanStack Query cached
- `useSettings.js`, `useSiteSettings.js`, `useAboutSettings.js` — Settings
- `useMenuItems.js` — Dynamic nav
- `useMetaTags.js` — Dynamic SEO meta
- `useContact.js`, `useTestimonials.js`, `useGallery.js`

## Tech Stack

**Keep:** Vue 3.5, Vite 7.1, Pinia 3, TanStack Query 5.90, Tailwind 4, Axios
**Add:** GSAP 3 + ScrollTrigger, TresJS 4 + Three.js, vue-i18n 10
**Backend Add:** anthropic/sdk (PHP) for chatbot
**Python Add:** pytrends, anthropic, schedule (auto-blog service)

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Hero Stats | GET /api/settings/site | useSiteSettings() | Yes | Use existing, update values in admin |
| Featured Projects | GET /api/projects?featured=1 | useProjects() | Yes | Add `featured` filter param |
| Awards/Achievements | GET /api/awards | useAwards() | Yes | Add Outskill + Demo Day via admin |
| Blog Posts | GET /api/posts | usePosts() | Yes | Auto-populated by Python service |
| Testimonials | GET /api/testimonials | useTestimonials() | Yes | Use existing |
| Skills/Expertise | GET /api/settings/about | useAboutSettings() | Yes | Update values in admin |
| Work Experience | GET /api/settings/about | useAboutSettings() | Yes | Use existing |
| Menu Items | GET /api/menu-items | useMenuItems() | Yes | Update in admin to 5 items |
| 3D Globe Countries | Static JSON file | New: useGlobe() | No | Create `src/data/globe-countries.json` |
| AI Chatbot | POST /api/chatbot/ask | New: useChatbot() | No | Create ChatbotController + composable |
| Activity Feed | GET /api/activity-feed | New: useActivityFeed() | No | Create endpoint from existing tables |
| Newsletter Subscribe | POST /api/newsletter/subscribe | New: useNewsletter() | No | Create NewsletterController |
| i18n Static Text | JSON files in src/i18n/ | vue-i18n | No | Create en.json + id.json |
| i18n Dynamic Content | Translation tables | Existing API ?lang= param | Yes | Already in axios interceptor |
| llms.txt | GET /llms.txt | New Laravel route | No | Create GeoController |
| JSON-LD | Computed in composable | useMetaTags() extended | Partial | Extend existing composable |
| Auto Blog | Python + POST /api/automation/posts | Existing automation API | Yes | Create Python service |
| Prompt Demo | Static examples JSON | None | No | Create `src/data/prompt-examples.json` |
| Image Showcase | Static gallery JSON | None | No | Create `src/data/image-showcase.json` |
| Automation Viz | Static SVG data | None | No | Inline SVG in component |
| RAG Demo | POST /api/chatbot/ask (reuse) | useChatbot() | No | Reuse chatbot with RAG context |

---

## Phase 1: Foundation — Design System + Core UI

**Estimated time:** 60 minutes
**Status:** IN PROGRESS (Phase 1A complete, 1B-1E pending)

### Phase 1A: Design Tokens Overhaul — COMPLETED 2026-03-22

**Files:**
- Modify: `frontend/src/style.css`
- Modify: `frontend/tailwind.config.js`
- Modify: `frontend/index.html` (font preloads)

**Steps:**
1. Replace Google Fonts import in `style.css` with Space Grotesk + Inter + JetBrains Mono + Playfair Display
2. Add `<link rel="preload">` for Space Grotesk 700 and Inter 400 in `index.html`
3. Replace `@theme` color tokens in `style.css`:
   - Remove primary/secondary/accent indigo/violet/emerald
   - Add: `--color-bg-deep: #050506`, `--color-bg-elevated: #0C0C0F`
   - Add: `--color-fg-primary: #EDEDEF`, `--color-fg-muted: #8A8F98`
   - Add: `--color-accent-gold: #D4A843`, `--color-accent-cyan: #06B6D4`, `--color-accent-indigo: #5E6AD2`
   - Add: `--color-border-hairline: rgba(255,255,255,0.08)`
4. Update font families in `@theme`:
   - `--font-display: 'Space Grotesk', sans-serif`
   - `--font-sans: 'Inter', system-ui, sans-serif`
   - `--font-mono: 'JetBrains Mono', monospace`
   - `--font-serif: 'Playfair Display', serif`
5. Update `tailwind.config.js` colors and fontFamily to match new tokens
6. Update `.glass` component class: `bg-white/5 backdrop-blur-[40px] saturate-[180%] border border-white/[0.08]`
7. Update `.text-gradient` class: `from-[#D4A843] to-[#06B6D4]`
8. Update `.card-elevated` class for dark glass cards
9. Add new keyframes: `auroraFloat` (slow position oscillation), `gradientRotate` (border rotation), `countUp` (counter spring)
10. Add `.glass-card`, `.gradient-border`, `.glow-gold`, `.glow-cyan` utility classes
11. Update body base styles: `bg-[#050506] text-[#EDEDEF]`
12. Update scrollbar styles for dark theme

**Verification:**
- [ ] `npm run dev` starts without CSS errors
- [ ] Body background is `#050506`, text is `#EDEDEF`
- [ ] Space Grotesk loads for headings
- [ ] `.glass` class produces dark frosted glass effect
- [ ] `.text-gradient` produces gold→cyan gradient text
- [ ] No remaining indigo/violet/emerald color references in style.css

### Phase 1B: Aurora Background Component

**Files:**
- Create: `frontend/src/components/AuroraBackground.vue`

**Steps:**
1. Create `AuroraBackground.vue` with 3 absolutely-positioned gradient blobs
2. Each blob: `w-[500px] h-[500px] rounded-full blur-[120px] opacity-[0.08]`
3. Colors: blob1=accent-gold, blob2=accent-cyan, blob3=accent-indigo
4. CSS animation: `auroraFloat` keyframe with different durations (20s, 25s, 30s)
5. Add mouse-reactive parallax: `@mousemove` on window shifts blob positions by ±30px based on cursor
6. Wrap in `<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">`
7. Add `prefers-reduced-motion` media query — disable animations, show static blobs

**Verification:**
- [ ] 3 blobs visible on dark background
- [ ] Blobs slowly float/animate
- [ ] Mouse movement shifts blob positions subtly
- [ ] No pointer events (clicks pass through)
- [ ] `prefers-reduced-motion` disables animation

### Phase 1C: Liquid Glass Card Component

**Files:**
- Modify: `frontend/src/components/base/BaseCard.vue`
- Create: `frontend/src/components/base/GlassCard.vue`
- Create: `frontend/src/components/base/GradientBorderCard.vue`

**Steps:**
1. Create `GlassCard.vue` with `backdrop-filter: blur(40px) saturate(180%)` + `bg-white/5` + `border border-white/[0.08]`
2. Add hover state: `border-white/[0.15]` + `scale(1.02)` transition 300ms
3. Create `GradientBorderCard.vue` with animated conic-gradient border using CSS `@property --angle`
4. Gradient colors: gold→indigo→cyan rotating continuously
5. Add gold glow `box-shadow` variant via `glow` prop
6. Export both from `components/base/index.js`

**Verification:**
- [ ] GlassCard renders with frosted glass effect on dark background
- [ ] Hover produces border brightness + subtle scale
- [ ] GradientBorderCard has rotating gradient border
- [ ] Both components are exported from base/index.js

### Phase 1D: Layout + Navigation Overhaul

**Files:**
- Modify: `frontend/src/layouts/DefaultLayout.vue`
- Modify: `frontend/src/components/TheNavigation.vue`
- Modify: `frontend/src/components/TheFooter.vue`
- Modify: `frontend/src/router/index.js`

**Steps:**
1. Update `DefaultLayout.vue`: change `bg-white dark:bg-neutral-900` → `bg-[#050506]`, add `<AuroraBackground />` as first child
2. Update `TheNavigation.vue`:
   - Background: glass on dark `bg-[#050506]/80 backdrop-blur-xl border-b border-white/[0.08]`
   - Logo: "Ali Sadikin Ma" in Space Grotesk font-display
   - Active item: gold underline accent (`border-b-2 border-[#D4A843]`)
   - Text color: `text-[#EDEDEF]` default, `text-[#D4A843]` active
   - Mobile menu: full-screen glass overlay
3. Update `TheFooter.vue`: dark theme matching new design system
4. Update `router/index.js`:
   - Rename `/gallery` route → redirect to `/work?tab=awards`
   - Add `/work` route → new Work.vue (lazy loaded)
   - Add `/blog` route (already exists, ensure in nav)
   - Keep `/about`, `/contact`, `/projects/:slug`, `/blog/:slug`
   - Update meta titles

**Verification:**
- [ ] Layout has dark background + aurora blobs visible
- [ ] Navigation shows 5 items: Home, Work, Blog, About, Contact
- [ ] Nav uses glass effect on dark background
- [ ] Active nav item has gold underline
- [ ] Mobile hamburger menu works with glass overlay
- [ ] Footer matches dark theme
- [ ] `/gallery` redirects to `/work?tab=awards`

### Phase 1E: Custom Cursor + Base Interaction Effects

**Files:**
- Create: `frontend/src/components/CustomCursor.vue`
- Modify: `frontend/src/layouts/DefaultLayout.vue`

**Steps:**
1. Create `CustomCursor.vue`: luminous dot (12px circle, accent-gold, blur-sm glow) that follows mouse with slight lag (lerp 0.15)
2. Add trailing dots (3 smaller circles with increasing delay)
3. Only render on desktop (media query `pointer: fine`)
4. Hide default cursor: `cursor: none` on body when component is mounted
5. Restore cursor on mobile / on unmount
6. Add to DefaultLayout after AuroraBackground

**Verification:**
- [ ] Custom cursor visible on desktop, follows mouse smoothly
- [ ] Trail effect (3 fading dots) behind main cursor
- [ ] Default cursor hidden on desktop
- [ ] Default cursor restored on mobile / tablet
- [ ] No performance jank (uses requestAnimationFrame)

---

## Phase 2: Hero + Homepage Sections

**Estimated time:** 90 minutes

### Phase 2A: Cinematic Scroll-Driven Hero

**Files:**
- Create: `frontend/src/components/CinematicHero.vue`
- Create: `frontend/public/frames/hero/` (placeholder directory)
- Modify: `frontend/src/views/Home.vue`

**Steps:**
1. Create `CinematicHero.vue` with full-screen `<canvas>` element (100vh)
2. Implement frame preloader: load JPEG frames from `/frames/hero/frame_XXXX.jpg`
3. On scroll: map `scrollY` (0 to hero-height) → frame index (0 to totalFrames-1)
4. Draw current frame to canvas using `ctx.drawImage()`
5. Preload strategy: load first 30 frames immediately, then lazy-load ±30 around current position
6. Overlay content on canvas: name "ALI SADIKIN" in `font-display text-7xl md:text-8xl lg:text-9xl` with gradient text fill
7. Subtitle "AI Generalist Expert" in Inter font-sans
8. Achievement badges: "#1 Champion | 16 Countries | 26 Startups" — spring-animate in on scroll progress 60%
9. Scroll indicator at bottom: animated chevron
10. **Fallback:** If no frames exist yet, show CSS gradient animation background with same text overlay
11. Add `prefers-reduced-motion`: show static first frame or gradient

**Note:** Actual Kling JPEG frames are generated externally. For now, implement the component with CSS gradient fallback. The frame directory will be populated later.

**Verification:**
- [ ] Hero section is 100vh
- [ ] Gradient text "ALI SADIKIN" renders with gold→cyan fill
- [ ] "AI Generalist Expert" subtitle visible
- [ ] Scroll indicator animates at bottom
- [ ] Fallback gradient background works when no frames exist
- [ ] Canvas renders frames when they exist (test with 1 placeholder frame)
- [ ] Achievement badges animate in on scroll

### Phase 2B: Stats Section

**Files:**
- Create: `frontend/src/components/home/StatsBar.vue`

**Steps:**
1. Create `StatsBar.vue` with 4-column grid (responsive: 2 cols mobile, 4 desktop)
2. Stats data from `useSiteSettings()` or hardcoded initial values: `17+ Years`, `56+ Projects`, `#1 Champion`, `16 Countries`
3. Numbers in `font-display text-5xl font-bold text-[#D4A843]`
4. Labels in `font-mono text-xs uppercase tracking-widest text-[#8A8F98]`
5. Implement animated counter: use Intersection Observer, on enter → spring-physics count from 0 to target over 1.5s
6. Each stat in a GlassCard

**Verification:**
- [ ] 4 stats render in grid
- [ ] Numbers animate from 0 to target on scroll into view
- [ ] Gold accent on numbers, muted labels
- [ ] Only animates once (not on re-enter)

### Phase 2C: Interactive Skills Demo Section

**Files:**
- Create: `frontend/src/components/skills/SkillsDemoSection.vue`
- Create: `frontend/src/components/skills/PromptDemo.vue`
- Create: `frontend/src/components/skills/ImageShowcase.vue`
- Create: `frontend/src/components/skills/AutomationViz.vue`
- Create: `frontend/src/components/skills/RagDemo.vue`
- Create: `frontend/src/data/prompt-examples.json`
- Create: `frontend/src/data/image-showcase.json`

**Steps:**
1. Create `SkillsDemoSection.vue` with bento grid layout (2x2 on desktop, 1-col on mobile)
2. Section header: "What I Build With AI" in gradient text
3. `PromptDemo.vue`: text input with "before" (naive prompt) and "after" (engineered prompt) display. Use pre-built examples from `prompt-examples.json`. Toggle between examples. Visual diff highlight.
4. `ImageShowcase.vue`: grid of AI-generated images from `image-showcase.json` (prompt + image URL). Lightbox on click via BaseLightbox.
5. `AutomationViz.vue`: animated SVG showing n8n-like workflow. 5 nodes (Trigger → Fetch → Process → Transform → Output) connected by lines. Nodes light up sequentially on loop. Click node for tooltip.
6. `RagDemo.vue`: search input + "Search Ali's Portfolio" button. On submit → POST /api/chatbot/ask with RAG context. Shows retrieved answer. (Uses same chatbot endpoint — built in Phase 4.)
7. Each card is a GlassCard with specific interactive content inside
8. Staggered scroll-reveal using Intersection Observer

**Note:** RagDemo will show "Coming soon" until chatbot endpoint is built in Phase 4.

**Verification:**
- [ ] Bento grid renders 4 cards
- [ ] PromptDemo toggles between examples
- [ ] ImageShowcase shows gallery (placeholder images OK for now)
- [ ] AutomationViz SVG nodes animate sequentially
- [ ] RagDemo has input field and shows placeholder state
- [ ] Responsive: 1-col on mobile, 2x2 on desktop

### Phase 2D: Featured Work, Achievements, Blog, Testimonials, CTA, Footer

**Files:**
- Create: `frontend/src/components/home/FeaturedWork.vue`
- Create: `frontend/src/components/home/AchievementsHighlight.vue`
- Create: `frontend/src/components/home/LatestBlog.vue`
- Create: `frontend/src/components/home/ActivityFeed.vue`
- Create: `frontend/src/components/home/CTASection.vue`
- Modify: `frontend/src/views/Home.vue` (rebuild entirely)

**Steps:**
1. `FeaturedWork.vue`: 6 project GlassCards from `useProjects()`. Hover: chromatic aberration CSS filter + scale(1.02). Category badges. "View All Work →" link.
2. `AchievementsHighlight.vue`: 2 prominent GradientBorderCards for Outskill Fellowship + Demo Day Champion. Gold glow behind. Data from `useAwards()`.
3. `LatestBlog.vue`: 3 latest post GlassCards from `usePosts()`. Excerpt + "Read More →".
4. `ActivityFeed.vue`: Placeholder section "Recent Activity" — will be connected in Phase 5. For now show static example entries.
5. `CTASection.vue`: "Let's Build Something Amazing" in gradient text. WhatsApp + Get in Touch buttons with gold glow. Newsletter subscribe inline form.
6. Rebuild `Home.vue`: compose all sections in order: CinematicHero → StatsBar → SkillsDemoSection → FeaturedWork → AchievementsHighlight → LatestBlog → Testimonials (reuse existing) → ActivityFeed → CTASection

**Verification:**
- [ ] Home page renders all 10 sections in correct order
- [ ] FeaturedWork shows 6 projects from real API
- [ ] AchievementsHighlight shows awards from real API
- [ ] LatestBlog shows posts from real API
- [ ] CTA section has WhatsApp button + newsletter form
- [ ] All sections use dark glass theme
- [ ] Scroll reveals stagger in correctly

---

## Phase 3: Pages Rebuild

**Estimated time:** 90 minutes

### Phase 3A: Work Page (Projects + Awards + Case Studies)

**Files:**
- Create: `frontend/src/views/Work.vue`
- Modify: `frontend/src/router/index.js` (add /work route)

**Steps:**
1. Create `Work.vue` with tabbed layout: Projects | Awards | Case Studies
2. Glass tab bar with gold active indicator
3. **Projects tab:** Reuse data from `useProjects()`. Search bar + category filter pills. GlassCard grid (3-col). Pagination. Each card links to `/projects/:slug`.
4. **Awards tab:** Data from `useAwards()` + `useGallery()`. Masonry/bento layout with GradientBorderCards for top achievements. Gallery items per award with lightbox.
5. **Case Studies tab:** Initially show top 5 projects flagged as case studies. Each links to enhanced ProjectDetail with scroll-driven sections.
6. Read `tab` from `?tab=` query param. Default to "projects".
7. Add route to router: `/work` → `Work.vue`, meta: { title: 'My Work' }

**Verification:**
- [ ] `/work` renders with 3 tabs
- [ ] Tab switching works via URL query param `?tab=projects|awards|case-studies`
- [ ] Projects tab shows 56 projects from real API with search + filters
- [ ] Awards tab shows awards from real API with gallery items
- [ ] Pagination works on projects tab
- [ ] `/gallery` redirects to `/work?tab=awards`

### Phase 3B: About Page Redesign

**Files:**
- Modify: `frontend/src/views/About.vue`

**Steps:**
1. Rebuild About.vue with dark cinematic theme
2. Hero intro: professional photo + bio + "AI Generalist Expert" title + language badges + CTA buttons. All in GlassCard.
3. Globe placeholder: empty `<div id="globe-container">` with "Global Impact" heading — TresJS component added in Phase 4.
4. Skills & Expertise: updated skill tags from `useAboutSettings()`. GlassCard pills.
5. Work Experience Timeline: keep existing data, restyle with dark glass cards, gold timeline line, country flags.
6. Certifications section: Outskill Fellowship, Demo Day Champion, Alibaba eFounders. Certificate images in BaseLightbox.
7. Mission + Approach: keep existing content, restyle dark.
8. Collaboration modes: 3 GlassCards (Project-Based, Retainer, Consulting).
9. Data from: `useAboutSettings()` (existing), hardcoded certifications (new Outskill + Demo Day data).

**Verification:**
- [ ] About page renders with dark theme
- [ ] Bio section shows photo + updated title
- [ ] Skills tags load from real API
- [ ] Work Experience timeline renders with dark glass cards
- [ ] Certifications section shows Outskill + Demo Day
- [ ] Globe placeholder exists (empty div with heading)
- [ ] All sections use glass/dark card styling

### Phase 3C: Blog Page + Blog Detail

**Files:**
- Modify: `frontend/src/views/Blog.vue`
- Modify: `frontend/src/views/BlogDetail.vue`

**Steps:**
1. Rebuild `Blog.vue` with dark theme
2. Featured post: large hero GlassCard for latest post
3. Post grid: search + category filters + GlassCard post cards (2-3 col)
4. Pagination from existing usePosts()
5. Newsletter subscribe inline at bottom
6. Rebuild `BlogDetail.vue`:
   - Full article in dark theme
   - AI-generated summary box at top (from `ai_summary` field if populated)
   - Reading time estimate
   - Table of contents sidebar (generated from headings)
   - Share buttons
   - Related posts at bottom
7. All data from existing `usePosts()` composable

**Verification:**
- [ ] Blog page shows posts from real API
- [ ] Featured post section renders latest post prominently
- [ ] Search and category filters work
- [ ] Blog detail shows full article with dark theme
- [ ] Reading time displayed
- [ ] Related posts shown at bottom

### Phase 3D: Contact Page + Newsletter

**Files:**
- Modify: `frontend/src/views/Contact.vue`

**Steps:**
1. Rebuild Contact.vue with dark theme
2. Contact form in GlassCard: name, email, subject, message, WhatsApp number
3. Validation + submit via existing `useContact()` composable
4. Success animation on submit
5. Alternative contact: WhatsApp button (large, gold glow), email link, social links, location
6. Newsletter subscribe section: email input + "Subscribe" button
7. Newsletter subscribe: POST to new endpoint (placeholder until backend Phase 5)

**Verification:**
- [ ] Contact form renders with dark glass theme
- [ ] Form submission works via existing API
- [ ] WhatsApp button links correctly
- [ ] Newsletter subscribe form present (shows "Coming soon" toast until endpoint ready)
- [ ] Responsive layout

---

## Phase 4: Advanced Features

**Estimated time:** 120 minutes

### Phase 4A: Install Frontend Dependencies

**Files:**
- Modify: `frontend/package.json`

**Steps:**
1. `cd frontend && npm install gsap @tresjs/core three vue-i18n`
2. Verify no dependency conflicts
3. Verify build still works: `npm run build`

**Verification:**
- [ ] `npm install` completes without errors
- [ ] `npm run build` completes without errors
- [ ] No peer dependency warnings for TresJS/Three

### Phase 4B: GSAP ScrollTrigger Integration

**Files:**
- Create: `frontend/src/composables/useScrollReveal.js`
- Modify: `frontend/src/views/Home.vue` — apply to sections

**Steps:**
1. Create `useScrollReveal.js` composable: registers GSAP ScrollTrigger plugin
2. Exports `revealOnScroll(element, options)` function
3. Default animation: `opacity: 0 → 1`, `translateY: 30px → 0`, `duration: 0.6`, `ease: power2.out`
4. Stagger option for child elements (30ms per child)
5. Respects `prefers-reduced-motion`: skip animation, show immediately
6. Apply to all Home page sections
7. Apply to other pages as needed

**Verification:**
- [ ] Sections fade/slide in on scroll
- [ ] Stagger works for child elements
- [ ] `prefers-reduced-motion` respected
- [ ] No jank/performance issues

### Phase 4C: 3D Interactive Globe (TresJS)

**Files:**
- Create: `frontend/src/components/Globe3D.vue`
- Create: `frontend/src/data/globe-countries.json`
- Create: `frontend/src/composables/useGlobe.js`
- Modify: `frontend/src/views/About.vue` — mount globe

**Steps:**
1. Create `globe-countries.json` with lat/lng for 18 countries: Singapore (1.35, 103.82), Hong Kong (22.32, 114.17), Japan (35.68, 139.69), Thailand (13.76, 100.50), Indonesia (−6.21, 106.85), India (12.97, 77.59), Calgary (51.05, −114.07), Seattle (47.61, −122.33), Washington DC (38.91, −77.04), Michigan (42.33, −83.05), Virginia (37.43, −79.14), London (51.51, −0.13), Scotland (55.95, −3.19), Paris (48.86, 2.35), Dubai (25.20, 55.27), Riyadh (24.71, 46.67), South Africa (−33.92, 18.42), Mauritius (−20.35, 57.55)
2. Create `Globe3D.vue` using TresJS `<TresCanvas>`:
   - Dark sphere geometry with wireframe overlay
   - Glowing dots at each country position (lat/lng → 3D coordinates conversion)
   - Arc lines (TubeGeometry) connecting Indonesia to other countries
   - Auto-rotate (0.001 rad/frame)
   - OrbitControls for mouse drag
   - Tooltip on hover showing country name
3. Create `useGlobe.js`: loads country data, converts lat/lng to 3D coords
4. Lazy load: wrap in `<Suspense>` with loading skeleton
5. Intersection Observer: only animate when visible
6. Mobile: reduce segment count, disable OrbitControls (auto-rotate only)
7. Mount in About.vue globe placeholder

**Verification:**
- [ ] Globe renders on About page
- [ ] 18 country dots visible with glow
- [ ] Arc lines connect from Indonesia to other countries
- [ ] Auto-rotates when idle
- [ ] Mouse drag rotates on desktop
- [ ] Tooltip shows country name on hover
- [ ] Lazy loaded (separate chunk)
- [ ] Mobile: auto-rotate only, no drag

### Phase 4D: AI Chatbot Backend

**Files:**
- Create: `backend/app/Http/Controllers/Api/ChatbotController.php`
- Create: `backend/config/ai.php`
- Modify: `backend/routes/api.php` — add chatbot routes
- Modify: `backend/.env.example` — add AI API key vars

**Steps:**
1. Create `config/ai.php`: `'anthropic_key' => env('ANTHROPIC_API_KEY')`, `'model' => env('AI_MODEL', 'claude-sonnet-4-20250514')`
2. Add to `.env.example`: `ANTHROPIC_API_KEY=`, `AI_MODEL=claude-sonnet-4-20250514`
3. Install: `cd backend && composer require anthropic-ai/anthropic`
4. Create `ChatbotController.php`:
   - `ask(Request $request)` method
   - Validate: `message` required string, max 500 chars
   - Rate limit: 10/min per IP (via throttle middleware)
   - Assemble system prompt with portfolio context:
     - Query Project::all() titles + descriptions
     - Query Post::published() latest 10 summaries
     - Query Award::all() titles + descriptions
     - Include About settings
   - Call Anthropic API with assembled messages
   - Return JSON response with `answer` field
5. Add route: `Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->middleware('throttle:10,1')`

**Verification:**
- [ ] `POST /api/chatbot/ask` with `{"message": "What are Ali's skills?"}` returns AI response
- [ ] Rate limiting works (11th request in 1 min returns 429)
- [ ] Response includes portfolio context (mentions projects/skills)
- [ ] Invalid input returns 422

### Phase 4E: AI Chatbot Frontend

**Files:**
- Create: `frontend/src/components/AskAliChatbot.vue`
- Create: `frontend/src/composables/useChatbot.js`
- Modify: `frontend/src/layouts/DefaultLayout.vue` — add chatbot

**Steps:**
1. Create `useChatbot.js`: manages chat state (messages array), sends to `/api/chatbot/ask`, tracks loading
2. Create `AskAliChatbot.vue`:
   - Floating bubble (bottom-right, z-50): gold accent circle with chat icon
   - Click expands to GlassCard chat panel (320px wide, 480px tall)
   - Message list with user/bot messages
   - Input field + send button
   - Typing indicator (3 bouncing dots)
   - Suggested questions: "What are Ali's AI skills?", "Tell me about SparkFluence", "What projects has Ali built?"
   - Close button
   - Animate open/close with scale + fade
3. Add to DefaultLayout after footer, before BaseToast
4. Only show on public pages (not admin)

**Verification:**
- [ ] Floating chat bubble visible on all public pages
- [ ] Click opens glass chat panel
- [ ] Sending message shows typing indicator then response
- [ ] Suggested questions work
- [ ] Close button closes panel
- [ ] Not visible on admin pages

### Phase 4F: View Transitions API

**Files:**
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/App.vue`

**Steps:**
1. Add View Transitions API support in router:
   ```js
   router.beforeResolve(async (to, from) => {
     if (document.startViewTransition && to.path !== from.path) {
       await document.startViewTransition(() => {
         return new Promise(resolve => {
           router.isReady().then(resolve)
         })
       }).ready
     }
   })
   ```
2. Add CSS for view transitions:
   ```css
   ::view-transition-old(root) { animation: fade-out 0.2s ease-in; }
   ::view-transition-new(root) { animation: fade-in 0.3s ease-out; }
   ```
3. Graceful fallback: feature-detect `document.startViewTransition`

**Verification:**
- [ ] Page transitions animate with crossfade
- [ ] Works in Chrome/Edge (View Transitions supported)
- [ ] Falls back gracefully in Firefox/Safari (no animation, no error)

---

## Phase 5: Automation & GEO

**Estimated time:** 90 minutes

### Phase 5A: GEO — Backend (llms.txt + AI Summary + JSON-LD)

**Files:**
- Create: `backend/app/Http/Controllers/Api/GeoController.php`
- Create: `backend/database/migrations/xxxx_add_ai_summary_to_posts_table.php`
- Create: `backend/database/migrations/xxxx_add_ai_summary_to_projects_table.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Traits/HasSeoFields.php` (if ai_summary not already there)

**Steps:**
1. Check if `ai_summary` column already exists on posts/projects (HasSeoFields trait suggests it may). If not, create migrations.
2. Create `GeoController.php`:
   - `llmsTxt()`: assembles machine-readable summary from: Person info (settings), Achievements (awards), Projects (top 20), Blog (latest 10), Contact info. Returns `text/plain`.
   - `llmsFullTxt()`: comprehensive dump of all content. Returns `text/plain`.
3. Add routes (no auth, public):
   - `GET /llms.txt` → `GeoController@llmsTxt`
   - `GET /llms-full.txt` → `GeoController@llmsFullTxt`
4. Update `robots.txt` in `backend/public/robots.txt`: add AI crawler rules (GPTBot, ChatGPT-User, ClaudeBot, Google-Extended, PerplexityBot)

**Verification:**
- [ ] `GET /api/llms.txt` returns structured text with person info, achievements, projects
- [ ] `GET /api/llms-full.txt` returns comprehensive content
- [ ] `robots.txt` allows AI crawlers
- [ ] ai_summary column exists on posts and projects tables

### Phase 5B: GEO — Frontend (JSON-LD + Meta Tags)

**Files:**
- Modify: `frontend/src/composables/useMetaTags.js`
- Create: `frontend/src/utils/jsonld.js`

**Steps:**
1. Create `jsonld.js` utility with schema generator functions:
   - `personSchema()`: Person + knowsAbout + award + alumniOf
   - `articleSchema(post)`: BlogPosting with author, datePublished, etc.
   - `projectSchema(project)`: SoftwareApplication or CreativeWork
   - `websiteSchema()`: WebSite with SearchAction
2. Extend `useMetaTags.js`:
   - Add `setJsonLd(schema)` method: injects `<script type="application/ld+json">` into head
   - Remove on route change
3. Call from each page view:
   - Home.vue: personSchema + websiteSchema
   - BlogDetail.vue: articleSchema
   - ProjectDetail.vue: projectSchema
   - About.vue: personSchema (detailed)

**Verification:**
- [ ] `<script type="application/ld+json">` present in page source on Home
- [ ] Person schema includes name, jobTitle, knowsAbout
- [ ] Blog post detail includes BlogPosting schema
- [ ] Schema removed on route change (no duplicates)

### Phase 5C: Activity Feed Backend + Frontend

**Files:**
- Create: `backend/app/Http/Controllers/Api/ActivityFeedController.php`
- Modify: `backend/routes/api.php`
- Create: `frontend/src/composables/useActivityFeed.js`
- Modify: `frontend/src/components/home/ActivityFeed.vue` (replace placeholder)

**Steps:**
1. Create `ActivityFeedController.php`:
   - `index()`: query latest 10 activities across posts, projects, awards tables
   - Union query: `SELECT 'post' as type, title, created_at FROM posts UNION SELECT 'project', title, created_at FROM projects UNION ...`
   - Order by created_at desc, limit 10
   - Return with human-readable timestamps
2. Add route: `GET /api/activity-feed` (public, cached 5 min)
3. Create `useActivityFeed.js`: TanStack Query wrapper, 60s stale time
4. Update `ActivityFeed.vue`: show real activity entries from API

**Verification:**
- [ ] `GET /api/activity-feed` returns latest 10 activities
- [ ] Each entry has type, title, created_at
- [ ] Frontend ActivityFeed component shows real data
- [ ] Displays human-readable time ("2 hours ago")

### Phase 5D: Newsletter Backend + Frontend

**Files:**
- Create: `backend/app/Http/Controllers/Api/NewsletterController.php`
- Modify: `backend/routes/api.php`
- Create: `frontend/src/composables/useNewsletter.js`

**Steps:**
1. Create `NewsletterController.php`:
   - `subscribe(Request $request)`: validate email, check duplicate in newsletters table, create record
   - `unsubscribe(Request $request)`: find by email, delete
2. Add routes:
   - `POST /api/newsletter/subscribe` (public, throttle: 5/hour)
   - `DELETE /api/newsletter/unsubscribe` (public)
3. Create `useNewsletter.js` composable: subscribe/unsubscribe methods, loading state
4. Connect newsletter forms in CTASection, Contact, Footer

**Verification:**
- [ ] `POST /api/newsletter/subscribe` with valid email creates record
- [ ] Duplicate email returns friendly error
- [ ] Frontend forms submit successfully with success toast
- [ ] Throttle prevents abuse

### Phase 5E: Auto Blog Python Service

**Files:**
- Create: `scripts/auto_blog/main.py`
- Create: `scripts/auto_blog/trending.py`
- Create: `scripts/auto_blog/generator.py`
- Create: `scripts/auto_blog/publisher.py`
- Create: `scripts/auto_blog/requirements.txt`
- Create: `scripts/auto_blog/.env.example`
- Create: `scripts/auto_blog/README.md`

**Steps:**
1. Create `requirements.txt`: pytrends, anthropic, requests, python-dotenv, schedule
2. Create `.env.example`: ANTHROPIC_API_KEY, PORTFOLIO_API_URL, PORTFOLIO_API_TOKEN
3. `trending.py`: use pytrends to fetch trending topics, filter for tech/AI keywords
4. `generator.py`: call Claude API to generate blog post (title, content, excerpt, meta_title, meta_description, tags)
5. `publisher.py`: POST to /api/automation/posts/check-duplicate first, then POST to /api/automation/posts
6. `main.py`: scheduler loop — every hour, fetch trends, generate if relevant topic found, publish. Max 2 posts/day.
7. Create README with setup instructions

**Verification:**
- [ ] `python main.py` runs without import errors
- [ ] trending.py returns tech/AI topics from Google Trends
- [ ] generator.py generates valid blog post JSON
- [ ] publisher.py successfully posts to automation API (with valid token)
- [ ] Duplicate check prevents double posts

---

## Phase 6: Polish & i18n

**Estimated time:** 60 minutes

### Phase 6A: Multi-Language (vue-i18n)

**Files:**
- Create: `frontend/src/i18n/en.json`
- Create: `frontend/src/i18n/id.json`
- Create: `frontend/src/i18n/index.js`
- Modify: `frontend/src/main.js` — register i18n plugin
- Modify: `frontend/src/components/TheNavigation.vue` — add language toggle

**Steps:**
1. Create `en.json` and `id.json` with translations for all static UI text (nav labels, section headings, button text, form labels, footer text, CTA messages)
2. Create `index.js`: setup vue-i18n with locale from localStorage
3. Register in `main.js`: `app.use(i18n)`
4. Add language toggle button in navbar (EN | ID)
5. On toggle: update localStorage, update axios `Accept-Language` header, reload dynamic content
6. Replace hardcoded strings in components with `$t('key')` calls
7. Dynamic content (posts, projects): use existing `?lang=` query param

**Verification:**
- [ ] Language toggle visible in navbar
- [ ] Switching to ID translates all static text
- [ ] Switching back to EN restores English
- [ ] Language preference persists across page refreshes
- [ ] Dynamic content language updates (posts show Indonesian translations if available)

### Phase 6B: Case Study Deep-Dive

**Files:**
- Modify: `frontend/src/views/ProjectDetail.vue`

**Steps:**
1. Enhance ProjectDetail.vue for case study format:
   - Problem section (from project description)
   - Solution section
   - Tech Stack visualized with icons
   - Process/approach
   - Results with animated counters
2. GSAP ScrollTrigger: each section reveals on scroll
3. Only apply case study layout if project is flagged (check `is_case_study` field or similar)
4. Fallback to standard detail layout for non-case-study projects

**Verification:**
- [ ] Project detail page renders enhanced layout for case study projects
- [ ] Standard projects show normal detail view
- [ ] Scroll-driven section reveals work
- [ ] Tech stack icons display correctly

### Phase 6C: Performance Optimization

**Steps:**
1. Audit bundle size: `npm run build && npx vite-bundle-analyzer`
2. Verify Three.js is code-split (separate chunk, only loaded on About page)
3. Verify GSAP is tree-shaken (only ScrollTrigger imported)
4. Add `loading="lazy"` to all non-hero images
5. Preload hero fonts: Space Grotesk 700, Inter 400
6. Test Lighthouse score on Home, About, Work, Blog pages
7. Add `will-change: transform` to animated elements
8. Ensure all animations use `transform` and `opacity` only (no layout-triggering properties)

**Verification:**
- [ ] Initial bundle < 300KB gzipped
- [ ] Three.js chunk loaded only on About page
- [ ] Lighthouse Performance score > 80 on Home
- [ ] No layout shift (CLS < 0.1)
- [ ] All images lazy-loaded except hero

### Phase 6D: Accessibility + Mobile Polish

**Steps:**
1. Verify all interactive elements have focus-visible states (gold ring)
2. Verify contrast ratios: `#EDEDEF` on `#050506` = 17.4:1 (AAA pass)
3. Verify contrast for muted text: `#8A8F98` on `#050506` = 5.5:1 (AA pass)
4. Add `aria-label` to icon-only buttons (chat bubble, close buttons, nav toggle)
5. Test keyboard navigation: Tab through all interactive elements
6. Test mobile: 375px viewport, all touch targets >= 44px
7. Verify `prefers-reduced-motion` disables: aurora animation, scroll reveals, counter animations, cursor trail, globe rotation
8. Test on mobile: hamburger menu works, no horizontal scroll, all content reachable

**Verification:**
- [ ] All contrast ratios pass WCAG AA
- [ ] Keyboard navigation works on all pages
- [ ] `prefers-reduced-motion` respected
- [ ] Mobile: no horizontal scroll, touch targets >= 44px
- [ ] Screen reader: all interactive elements labeled

---

## Execution Readiness

### Parallel Execution Opportunities

These phases/sub-phases can run in parallel:
- **Phase 5A** (GEO Backend) + **Phase 5B** (GEO Frontend) — independent after migrations
- **Phase 5C** (Activity Feed) + **Phase 5D** (Newsletter) — fully independent
- **Phase 5E** (Auto Blog Python) — fully independent of all frontend work
- **Phase 4C** (3D Globe) + **Phase 4D** (Chatbot Backend) — fully independent
- **Phase 3A** (Work page) + **Phase 3B** (About page) + **Phase 3C** (Blog page) + **Phase 3D** (Contact page) — all independent after Phase 1

### Sequential Dependencies
```
Phase 1 (Foundation) → Phase 2 (Homepage) → Phase 3 (Pages)
Phase 1 → Phase 4A (Install deps)
Phase 4A → Phase 4B (GSAP) → Phase 4C (Globe)
Phase 4A → Phase 4D (Chatbot Backend) → Phase 4E (Chatbot Frontend)
Phase 4E → Phase 2C (RagDemo can use chatbot)
Phase 5D (Newsletter Backend) → Newsletter forms in Phase 3
```

### Pre-Flight Checklist
- [ ] Frontend `npm run dev` starts successfully
- [ ] Backend API is accessible at localhost/Portfolio_v2/backend/public/api
- [ ] Database has current data (projects, awards, posts, etc.)
- [ ] Node.js 18+ installed
- [ ] Python 3.10+ installed (for auto-blog)
- [ ] ANTHROPIC_API_KEY available (for chatbot + auto-blog)

---

**End of Implementation Plan**
