> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Redesign the homepage with 4 major improvements: (1) full-page mandatory scroll snapping for PPT-like slide transitions, (2) fluid full-width layout eliminating blank space on large screens, (3) bento grid layout for Featured Projects replacing the broken horizontal slider, and (4) interactive hero section with warrior video swap following mouse cursor + gold spark particle trail.

## Architecture Context

**From CLAUDE.md & codebase exploration:**

- **Home.vue** (line 2): Root div `snap-y snap-proximity` — needs `snap-mandatory`
- **style.css** (lines 189-192): `.container-custom { max-width: 1280px }` — needs removal
- **ProjectsBento.vue**: Horizontal slider fetching `api.get('/projects', { params: { per_page: 6, featured: 1 } })` — needs full rewrite to bento grid
- **CinematicHero.vue**: Currently plays `/videos/hero-bg.mp4` with scroll parallax, NO mouse tracking
- **AuroraBackground.vue**: Has mouse tracking pattern with lerp smoothing (reusable)
- **Project model**: `featured` boolean field **already exists** with `scopeFeatured()` — NO migration needed
- **useScrollReveal.js**: GSAP ScrollTrigger with `revealOnScroll()` and `revealStaggerChildren()`
- **Design tokens**: Gold `#D4A843`, Cyan `#06B6D4`, glass-card, bezel-shell patterns in style.css

## Tech Stack

- Vue 3.5 + `<script setup>` (existing)
- Tailwind CSS 4 (existing)
- GSAP 3 + ScrollTrigger (existing, used in ProjectsBento & useScrollReveal)
- CSS Scroll Snap (existing, needs config change)
- requestAnimationFrame (for cursor particles, no new deps)

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Snap scroll | CSS classes in Home.vue | N/A | Yes | Change `snap-proximity` → `snap-mandatory` |
| Fluid layout | `.container-custom` in style.css | N/A | Yes | Remove `max-width: 1280px`, add `clamp()` padding |
| Featured projects data | `/api/projects?featured=1` | `useProjects()` / direct `api.get()` | Yes | Reuse existing endpoint |
| `featured` flag | `projects.featured` column | `scopeFeatured()` on Model | Yes | Already exists, no migration |
| Bento grid layout | CSS Grid in ProjectsBento.vue | N/A | No | Rewrite component template + styles |
| Warrior videos | `/videos/warrior-{front,left,right}.mp4` | N/A | Yes | 3 compressed MP4s in public/videos/ |
| Warrior PNG fallback | `/frames/hero/alisadikin-{front,left,side}-warrior.png` | N/A | Yes | Existing assets |
| Mouse tracking | `mousemove` event + lerp | AuroraBackground.vue pattern | Yes | Reuse lerp pattern inline |
| Gold spark particles | Cursor position | N/A | No | Create `useCursorSparks.js` composable |
| Page sections | `/api/page-sections` | `usePageSections()` | Yes | No changes needed |

---

## Phase A: Full-Page Mandatory Snap Scroll

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/views/Home.vue`

**Steps:**

1. In `Home.vue` line 2, change root div class from `snap-y snap-proximity` to `snap-y snap-mandatory overflow-y-auto h-[100dvh]`
2. For each section with `min-h-[100dvh]`, change to `h-[100dvh]` to enforce exact viewport height (lines 13, 28, 42, 56, 72)
3. Add `overflow-y-auto` to sections that might have content overflow (skill showcases with long bullet lists)
4. Keep SkillsReel section (line 10) WITHOUT snap — it flows naturally between snapped sections
5. Stats+CTA section (line 77): keep `snap-start` but use `min-h-[100dvh]` since it has variable content
6. Visually verify: scroll through all sections, each should snap exactly to viewport

**Verification:**
- [ ] Root div has `snap-y snap-mandatory`
- [ ] Each section snaps to full viewport on scroll
- [ ] SkillsReel has no snap class
- [ ] No section content is cut off (overflow handled)
- [ ] Mobile: sections still scrollable without jank

---

## Phase B: Fluid Full-Width Layout

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/style.css` (lines 189-192)

**Steps:**

1. In `style.css`, find `.container-custom` class (line 189-192)
2. Remove `max-width: 1280px`
3. Replace padding with fluid clamp: `padding-inline: clamp(1rem, 5vw, 6rem)`
4. Keep `mx-auto` for centering
5. Verify on different viewport widths: 1280px, 1440px, 1920px, 2560px
6. Check that text blocks remain readable (not stretched too wide) — add `max-w-prose` to long paragraphs if needed

**Current code:**
```css
.container-custom {
  @apply container mx-auto px-4 sm:px-6 lg:px-8;
  max-width: 1280px;
}
```

**New code:**
```css
.container-custom {
  width: 100%;
  margin-inline: auto;
  padding-inline: clamp(1rem, 5vw, 6rem);
}
```

**Verification:**
- [ ] No `max-width` on `.container-custom`
- [ ] Content fills viewport proportionally on 1920px+ screens
- [ ] No horizontal scrollbar appears
- [ ] Text remains readable (not overly wide paragraphs)
- [ ] Mobile layout unchanged (clamp falls back to 1rem)

---

## Phase C: Bento Grid Featured Projects

**Estimated time:** 20 minutes

**Files:**
- Rewrite: `frontend/src/components/home/ProjectsBento.vue`

**Steps:**

1. Keep the existing `<script setup>` data fetching logic (api.get with featured=1 param, GSAP triggers)
2. Replace the slider template (`.slider-wrap`, `.slider-track`, `.slider-card`) with CSS Grid bento layout
3. Grid structure:
   - Desktop (lg+): 3-column grid, first project spans 2 columns + 2 rows
   - Tablet (md): 2-column grid, first project spans 2 columns
   - Mobile: single column stack
4. Each card uses existing `bezel-shell-sm` + `bezel-core-sm` glass architecture
5. Card content: image (aspect-ratio 16/10), category badge, title, excerpt, tech tags
6. First project (index 0 or first with `featured=true`) gets the large card with more detail
7. Hover: gold border glow + subtle lift (reuse existing `.glass-card` hover pattern)
8. Keep "View All" CTA button linking to `/work`
9. Keep GSAP `revealStaggerChildren` for scroll-in animation
10. Remove all horizontal slider CSS (`.slider-wrap`, `.slider-track`, `.slider-card`, `.slider-fade`, `.slider-arrow`)

**Bento Grid CSS:**
```css
.bento-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr;
}

@media (min-width: 768px) {
  .bento-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .bento-grid .bento-hero {
    grid-column: span 2;
  }
}

@media (min-width: 1024px) {
  .bento-grid {
    grid-template-columns: repeat(3, 1fr);
  }
  .bento-grid .bento-hero {
    grid-column: span 2;
    grid-row: span 2;
  }
}
```

**Verification:**
- [ ] Bento grid renders with first project as large card
- [ ] Responsive: 1 col mobile, 2 col tablet, 3 col desktop
- [ ] Cards use glass-card/bezel design system
- [ ] Hover effects work (gold glow, lift)
- [ ] GSAP stagger reveal animates cards on scroll
- [ ] "View All" CTA links to `/work`
- [ ] No horizontal scroll slider remnants
- [ ] Data loads from existing `/api/projects?featured=1` endpoint

---

## Phase D: Hero Warrior Video Swap

**Estimated time:** 20 minutes

**Files:**
- Modify: `frontend/src/components/CinematicHero.vue`

**Steps:**

1. Add 3 `<video>` elements for warrior poses, stacked with `position: absolute`:
   ```html
   <video ref="warriorFrontRef" src="/videos/warrior-front.mp4" loop muted playsinline preload="auto" />
   <video ref="warriorLeftRef" src="/videos/warrior-left.mp4" loop muted playsinline preload="auto" />
   <video ref="warriorRightRef" src="/videos/warrior-right.mp4" loop muted playsinline preload="auto" />
   ```
2. Position warrior videos as overlay on top of hero background, centered, with appropriate sizing
3. Implement mouse tracking (reuse AuroraBackground.vue pattern):
   ```javascript
   const mouseX = ref(0.5) // normalized 0-1
   const target = { x: 0.5 }
   const lerp = 0.06

   function onMouseMove(e) {
     target.x = e.clientX / window.innerWidth // 0 = far left, 1 = far right
   }

   function animate() {
     mouseX.value += (target.x - mouseX.value) * lerp
     rafId = requestAnimationFrame(animate)
   }
   ```
4. Crossfade logic based on mouseX position (5-zone system):
   ```
   0.0 - 0.2: warrior-right.mp4 (CSS scaleX(-1) flipped = looking left)
   0.2 - 0.4: crossfade right-flipped → front
   0.4 - 0.6: warrior-front.mp4 (center, looking at camera)
   0.6 - 0.8: crossfade front → right
   0.8 - 1.0: warrior-right.mp4 (natural = looking right)
   ```
   - Left side uses `warrior-left.mp4` with CSS `scaleX(-1)` for mirror
   - Opacity transitions smoothly between zones
5. All 3 videos autoplay on mount, loop continuously (they're background animations)
6. Fallback for mobile (no mouse): show front warrior only, static PNG
7. `prefers-reduced-motion`: show front PNG, skip video
8. Cleanup: remove event listener + cancel RAF on unmount

**Warrior video positioning:**
```css
.warrior-video {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  height: 85%;
  width: auto;
  object-fit: contain;
  z-index: 5;
  transition: opacity 0.15s ease;
  pointer-events: none;
}
```

**Verification:**
- [ ] 3 warrior videos load and loop silently
- [ ] Mouse left → warrior faces left (flipped video)
- [ ] Mouse center → warrior faces forward
- [ ] Mouse right → warrior faces right
- [ ] Crossfade is smooth (no flickering between zones)
- [ ] Mobile: shows front warrior PNG (no mouse tracking)
- [ ] `prefers-reduced-motion` respected
- [ ] No memory leaks (RAF + event listener cleanup)
- [ ] Hero background video still plays behind warrior

---

## Phase E: Gold Spark Cursor Particles

**Estimated time:** 15 minutes

**Files:**
- Create: `frontend/src/composables/useCursorSparks.js`
- Modify: `frontend/src/components/CinematicHero.vue` (integrate composable)

**Steps:**

1. Create `useCursorSparks.js` composable:
   ```javascript
   export function useCursorSparks(containerRef, options = {}) {
     const {
       maxParticles = 20,
       color = '#D4A843',
       size = { min: 2, max: 6 },
       lifetime = 800,
       gravity = 0.3,
       spread = 15
     } = options
     // ... particle pool, spawn, animate, cleanup
   }
   ```
2. Particle system:
   - Pool of ~20 DOM elements (small `<div>` with `border-radius: 50%`, gold radial-gradient)
   - On mousemove: spawn particle at cursor position with random velocity
   - Each frame: update position (add velocity), apply gravity (y += gravity), reduce opacity
   - When opacity <= 0 or lifetime exceeded: recycle particle back to pool
   - Use `requestAnimationFrame` for animation loop
3. Particle visual style:
   ```css
   .spark {
     position: absolute;
     border-radius: 50%;
     background: radial-gradient(circle, #D4A843, transparent);
     pointer-events: none;
     will-change: transform, opacity;
   }
   ```
4. Integrate in CinematicHero.vue: call `useCursorSparks(heroRef)` in setup
5. Auto-disable on mobile (check `'ontouchstart' in window` or viewport width < 768)
6. Respect `prefers-reduced-motion`
7. Cleanup on unmount: cancel RAF, remove particles from DOM

**Verification:**
- [ ] Gold particles spawn at cursor position within hero section
- [ ] Particles fade and fall with gravity
- [ ] Max 20 particles at any time (pooled, no DOM bloat)
- [ ] No particles outside hero section boundary
- [ ] Disabled on mobile / touch devices
- [ ] `prefers-reduced-motion` disables particles
- [ ] No memory leaks on unmount
- [ ] Particles don't interfere with click events (pointer-events: none)

---

## Execution Order

```
Phase A (Snap Scroll)     ──┐
Phase B (Fluid Layout)    ──┤── Independent, can run parallel
Phase E (Cursor Sparks)   ──┘
Phase C (Bento Grid)      ──── Independent
Phase D (Warrior Videos)  ──── Independent (but benefits from Phase E being ready)
```

Phases A, B, E are independent CSS/composable work.
Phase C is an independent component rewrite.
Phase D integrates with Phase E (cursor sparks in hero).

**Recommended order:** A → B → C → D → E → integrate E into D

## Commit Strategy

```
feat: full-page mandatory scroll snap on homepage
feat: fluid full-width layout removing container cap
feat: bento grid layout for featured projects section
feat: interactive warrior video swap following cursor
feat: gold spark cursor particle trail in hero section
```
