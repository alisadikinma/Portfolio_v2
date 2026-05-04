# Homepage Redesign — Executable Plan

**Created:** 2026-05-04
**Source brief:** Brainstorm session 2026-05-04 (alisadikinma.com/en redesign)
**Owner:** Ali Sadikin (alisadikinma)
**Target branch:** `main` (no feature branches per project convention — direct commit, CI/CD auto-deploy)

---

## 1. Goal & Success Criteria

### Goal
Transform alisadikinma.com homepage from 10-section scroll-fatigue layout into a 6-section conversion-focused proof-of-AI-mastery flagship.

### Success Criteria (measurable)

| Metric | Baseline | Target | Verification |
|---|---|---|---|
| Sections visible above-fold | 0 (only hero name) | 1 hero + dual CTA + 3 stats | Visual inspection |
| Total sections | 10 | 6 | DB `page_sections` count |
| Lighthouse Performance | TBD | ≥ 85 | Production audit |
| Lighthouse Accessibility | TBD | ≥ 95 | Production audit |
| Time-to-Interactive (mobile) | TBD | ≤ 3.5s | Lighthouse mobile |
| Hero video LCP | N/A | ≤ 2.5s | Lighthouse |
| WhatsApp CTA click rate | TBD baseline | +50% from baseline | GA4 event tracking |
| Bounce rate | TBD | ≤ 50% | GA4 |
| Zero AI-slop violations | check current | 0 | `rules/anti-ai-slop.md` audit |

### Locked Decisions (from brainstorm)

| # | Decision | Detail |
|---|---|---|
| Positioning | Opsi 2 — Problem Solver | "Tell me your business problem. I'll architect the AI that solves it." |
| Hero Video | Direction B — Genesis Triptych (abstract metaphorical) | 15s seamless loop, Seedance 2.0 image-to-video, no human face |
| Stats Source | Live DB via `/api/homepage/stats` | 17 years (config) + dynamic awards + projects |
| Language | Geo-IP + sticky cookie preference | ID for Indonesia, EN for ROW, user choice persists 1yr |
| Hero 3rd stat | Enterprise brands name-drop | `Telkomsel · XIAOMI · MPA SG · ...` |
| Awards layout | Named cards (6 cards) | NOT grayscale logos; lead with Outskill (newest, most relevant) |
| Testimonials | Path C: LinkedIn-sourced | 4 recommendations from screenshot, manual entry via admin panel |
| CTA copy | "Got an AI problem? Let's diagnose it" | NOT "Let's Build Something Amazing" |

### Real Production Data (verified 2026-05-04)

- Awards: **6** (Alibaba eFounders, Google Startup Grind SV, Fenox World Cup, Nextdev Top 1, IDBYTE Top 8, **Outskill AI Generalist Fellowship Demo Day 2026 — 1st Place / 26 startups / 16 countries** ⭐ NEW)
- Projects: **56** (table `projects`, soft-deletes excluded)
- Published posts: **52** (Content Engine pipeline)
- Testimonials: 0 → will be 4 after Path C completion
- Brand name-drops from testimonials + awards combined: Outskill · Telkomsel · BOTIKA · MPA Singapore · XIAOMI · Indonesian Government · Satnusa · Alibaba · Google · Fenox · Nextdev

---

## 2. Architecture Overview

### Backend (Laravel 12)

**New components:**
- `app/Http/Middleware/SetLocaleByGeoIP.php` — geo-IP detection + cookie write
- `app/Http/Controllers/Api/HomepageController.php` — bundles stats + featured payload
- `app/Http/Resources/HomepageStatsResource.php`
- `app/Http/Resources/HomepageFeaturedResource.php`

**Modified components:**
- `app/Http/Controllers/Api/TestimonialController.php` — accept new `source` + `source_url` fields
- `app/Http/Resources/TestimonialResource.php` — expose new fields
- `app/Http/Resources/AwardResource.php` — add `is_featured` flag exposure
- `app/Models/Testimonial.php` — add fillable + cast for new fields
- `database/seeders/PageSectionSeeder.php` — replace 10 homepage sections with 6
- `database/seeders/TestimonialSeeder.php` — seed 4 LinkedIn-sourced testimonials
- `routes/api.php` — register homepage routes + apply locale middleware to public routes

**New migrations:**
- `2026_05_04_000001_add_source_to_testimonials.php` — `source` enum + `source_url` string
- `2026_05_04_000002_add_is_featured_to_awards.php` — boolean flag

### Frontend (Vue 3.5)

**New components:**
- `src/components/home/HomeHero.vue` — sequential video + positioning + dual CTA + inline stats
- `src/components/home/WhatISolveTabs.vue` — tabbed switcher (Vibe Coding / AI Agents / Video Gen)
- `src/components/home/MagazineGrid.vue` — 7+5 asymmetric editorial layout
- `src/components/home/ReceiptsBento.vue` — metric-led project cards (refactor of existing `ProjectsBento` with `display="metric"` prop)
- `src/components/home/AwardsNamedCards.vue` — 6 named cards, Outskill leads
- `src/components/home/TestimonialsCarousel.vue` — LinkedIn-sourced quotes with "via LinkedIn" badge
- `src/components/home/CTADiagnosis.vue` — "Got an AI problem?" + WhatsApp + Calendly
- `src/components/base/LanguageToggle.vue` — ID/EN switch, writes cookie
- `src/composables/useHomepage.js` — TanStack Query for stats + featured bundle
- `src/composables/useLocale.js` — locale detection + persistence

**Modified components:**
- `src/views/Home.vue` — full restructure to consume new components
- `src/components/projects/ProjectsBento.vue` — add `display="metric"` prop variant
- `src/components/blog/LatestBlog.vue` — add Content Engine badge + Playfair headline option
- `src/router/index.js` — wire LanguageToggle, redirect logic
- `src/stores/ui.js` — locale state for cross-component access

**Deleted components:**
- `src/components/home/SkillsReel.vue` (or its current name)
- `src/components/home/SkillShowcase.vue` (×4 instances merged)
- `src/components/home/StatsBar.vue` (merged into hero)

### Database Schema Changes

```sql
-- Migration 1: testimonials source tracking
ALTER TABLE testimonials
  ADD COLUMN source ENUM('linkedin','direct','video') DEFAULT 'direct' AFTER content,
  ADD COLUMN source_url VARCHAR(500) NULL AFTER source;

-- Migration 2: awards featured flag
ALTER TABLE awards
  ADD COLUMN is_featured BOOLEAN DEFAULT FALSE AFTER sort_order;
```

### API Contracts

```typescript
// GET /api/homepage/stats
{
  success: true,
  data: {
    years_experience: 17,
    awards_count: 6,
    projects_count: 56,
    enterprise_brands: ["Outskill", "Telkomsel", "XIAOMI", "MPA Singapore", "Satnusa", "BOTIKA"]
  }
}

// GET /api/homepage/featured
{
  success: true,
  data: {
    stats: { /* same as above */ },
    featured_awards: [ /* 6 Award resources, ordered by is_featured DESC, year DESC */ ],
    featured_testimonials: [ /* 4 Testimonial resources where source='linkedin' */ ],
    featured_projects: [ /* 5 Project resources, ordered by sort_order */ ],
    latest_articles: [ /* 5 most recent published Posts */ ]
  }
}
```

### Geo-IP Middleware Flow

```
Request hits /
  ↓
SetLocaleByGeoIP middleware:
  1. Has cookie `lang_preference`? → use it, App::setLocale()
  2. Else → read CF-IPCountry header (Cloudflare) OR fallback ip-api.com
  3. Country=ID → setLocale('id'), set cookie 1yr
     Else → setLocale('en'), set cookie 1yr
  4. Continue to controller
```

### Environment Variables

```env
# Geo-IP fallback when CF-IPCountry header absent
GEOIP_FALLBACK_PROVIDER=ip-api  # or "none" to skip fallback
GEOIP_FALLBACK_TIMEOUT=2        # seconds, fail-open to default 'en'

# Calendly integration (Section 6 CTA)
CALENDLY_30MIN_URL=https://calendly.com/alisadikinma/30min
```

---

## 3. Phase-by-Phase Implementation

### Phase 0 — Pre-flight (parallel, non-blocking)

**Owner:** Ali Sadikin (manual, not code)

| Task | Owner | Effort | Blocking? |
|---|---|---|---|
| LinkedIn DM 4 recommenders for permission to use quotes | Ali | 30 min | Soft-blocks Phase 6 launch |
| Schedule VEO 3.1 + Kling video render slot for hero | Ali | 1 hour | Soft-blocks final Phase 9 |
| Create Calendly 30-min link | Ali | 15 min | Soft-blocks Phase 7 |
| Storyboard hero video 15s Genesis Triptych (brief approval) | Ali | ✅ DONE 2026-05-04 — see hero-video/strategic-brief.md | unblocked |

**Acceptance:** 4 LinkedIn permissions received (or 3+ acceptable for launch).

---

### Phase 1 — Foundation (Geo-IP + API + Migrations)

**Effort:** 1 day
**Dependencies:** None
**Branch:** direct to main, single commit

#### Files

**Create:**
- `backend/database/migrations/2026_05_04_000001_add_source_to_testimonials.php`
- `backend/database/migrations/2026_05_04_000002_add_is_featured_to_awards.php`
- `backend/app/Http/Middleware/SetLocaleByGeoIP.php`
- `backend/app/Http/Controllers/Api/HomepageController.php`
- `backend/app/Http/Resources/HomepageStatsResource.php`
- `backend/app/Http/Resources/HomepageFeaturedResource.php`

**Modify:**
- `backend/app/Models/Testimonial.php` — `$fillable` + `$casts`
- `backend/app/Http/Resources/TestimonialResource.php` — expose `source`, `source_url`
- `backend/app/Http/Resources/AwardResource.php` — expose `is_featured`
- `backend/app/Http/Kernel.php` — register middleware
- `backend/routes/api.php` — register homepage routes + apply middleware to public routes
- `backend/config/services.php` — add geoip config

#### Migration Details

```php
// 2026_05_04_000001_add_source_to_testimonials.php
public function up(): void {
    Schema::table('testimonials', function (Blueprint $table) {
        $table->enum('source', ['linkedin', 'direct', 'video'])
              ->default('direct')->after('content');
        $table->string('source_url', 500)->nullable()->after('source');
    });
}

// 2026_05_04_000002_add_is_featured_to_awards.php
public function up(): void {
    Schema::table('awards', function (Blueprint $table) {
        $table->boolean('is_featured')->default(false)->after('sort_order');
    });
    // Mark Outskill as featured #1, then existing 5 by recency
    // Done via separate seeder, not in migration
}
```

#### Middleware Implementation Outline

```php
// SetLocaleByGeoIP.php
public function handle(Request $request, Closure $next): Response
{
    $cookieLang = $request->cookie('lang_preference');
    if (in_array($cookieLang, ['id', 'en'])) {
        App::setLocale($cookieLang);
        return $next($request);
    }

    $country = $request->header('CF-IPCountry');
    if (!$country && config('services.geoip.fallback') === 'ip-api') {
        $country = $this->resolveCountryViaIpApi($request->ip());
    }

    $locale = ($country === 'ID') ? 'id' : 'en';
    App::setLocale($locale);

    $response = $next($request);
    if (method_exists($response, 'cookie')) {
        $response->cookie('lang_preference', $locale, 60 * 24 * 365);
    }
    return $response;
}

private function resolveCountryViaIpApi(string $ip): ?string
{
    try {
        $r = Http::timeout(config('services.geoip.timeout', 2))
            ->get("http://ip-api.com/json/{$ip}", ['fields' => 'countryCode']);
        return $r->successful() ? ($r->json('countryCode') ?: null) : null;
    } catch (\Throwable $e) {
        Log::warning('[GeoIP] fallback failed', ['ip' => $ip, 'err' => $e->getMessage()]);
        return null;
    }
}
```

#### HomepageController Implementation

```php
public function stats(): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => [
            'years_experience' => config('app.years_experience', 17),
            'awards_count' => Award::count(),
            'projects_count' => Project::count(),
            'enterprise_brands' => config('homepage.enterprise_brands', [
                'Outskill', 'Telkomsel', 'XIAOMI', 'MPA Singapore', 'Satnusa', 'BOTIKA',
            ]),
        ],
    ]);
}

public function featured(): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => [
            'stats' => $this->stats()->getData()->data,
            'featured_awards' => AwardResource::collection(
                Award::orderByDesc('is_featured')->orderByDesc('id')->limit(6)->get()
            ),
            'featured_testimonials' => TestimonialResource::collection(
                Testimonial::where('source', 'linkedin')->limit(4)->get()
            ),
            'featured_projects' => ProjectResource::collection(
                Project::orderBy('sort_order')->limit(5)->get()
            ),
            'latest_articles' => PostResource::collection(
                Post::where('status', 'published')->latest('published_at')->limit(5)->get()
            ),
        ],
    ]);
}
```

#### Routes

```php
// routes/api.php (top of public group)
Route::middleware(['set.locale.by.geoip'])->group(function () {
    Route::get('/homepage/stats', [HomepageController::class, 'stats']);
    Route::get('/homepage/featured', [HomepageController::class, 'featured']);
    // ... existing public routes
});
```

#### Acceptance Criteria

- [ ] `php artisan migrate` runs clean on fresh DB and existing prod
- [ ] `GET /api/homepage/stats` returns 200 with shape `{ success, data: { years_experience: 17, awards_count: 6, projects_count: 56, enterprise_brands: [6 items] } }`
- [ ] `GET /api/homepage/featured` returns 200 with all 5 keys populated
- [ ] First request without cookie + ID country → response sets `lang_preference=id` cookie
- [ ] First request without cookie + non-ID country → cookie `lang_preference=en`
- [ ] Subsequent request with `lang_preference=en` cookie + ID country → still EN (cookie respected)
- [ ] Cloudflare CF-IPCountry header consumed correctly
- [ ] ip-api.com fallback fires only when CF-IPCountry absent
- [ ] Fallback timeout fails-open to `en` (not 500)

#### Tests (PHPUnit/Pest)

```php
// backend/tests/Feature/HomepageApiTest.php
test('stats endpoint returns expected shape')
test('featured endpoint returns 6 awards including Outskill first')
test('locale defaults to id when CF-IPCountry=ID and no cookie')
test('locale defaults to en when CF-IPCountry=US and no cookie')
test('locale respects cookie over geo-ip')
test('locale falls open to en when geo-ip fails')
```

#### Rollback

- `php artisan migrate:rollback --step=2`
- Revert commit
- API consumers gracefully degrade (frontend defaults to hardcoded fallbacks if endpoints 404)

---

### Phase 2 — Hero Section Rebuild

**Effort:** 1 day
**Dependencies:** Phase 1 (stats endpoint must work)

#### Files

**Create:**
- `frontend/src/components/home/HomeHero.vue`
- `frontend/src/composables/useHomepage.js`
- `frontend/public/videos/hero-loop.mp4` (placeholder until Phase 9 video ships)
- `frontend/public/videos/hero-loop.webm` (AV1 fallback)
- `frontend/public/videos/hero-poster.jpg` (static fallback for reduced-motion)

**Modify:**
- `frontend/src/views/Home.vue` — replace existing CinematicHero usage
- `frontend/src/components/CinematicHero.vue` — DEPRECATED, keep file for now (delete in Phase 8)
- `frontend/public/sw.js` — add hero-loop.mp4 + .webm to pre-cache list

#### HomeHero.vue Spec

```vue
<template>
  <section class="hero" aria-label="AI Generalist hero">
    <video v-if="!reducedMotion" autoplay loop muted playsinline
           poster="/videos/hero-poster.jpg" class="hero-video">
      <source src="/videos/hero-loop.webm" type="video/webm" />
      <source src="/videos/hero-loop.mp4" type="video/mp4" />
    </video>
    <img v-else src="/videos/hero-poster.jpg" alt="" class="hero-video" />
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <p class="hero-eyebrow">ai-generalist · since 2008</p>
      <h1 class="hero-headline">
        Tell me your business problem.<br>
        I'll architect the AI that solves it.
      </h1>
      <p class="hero-sub">
        17 years digital transformation + Vibe Coding, AI Agents & Generative Video —
        full-stack AI solutioning for operators who need shipped, not pitched.
      </p>
      <div class="hero-cta">
        <BaseButton variant="gold" size="lg" :href="whatsappUrl" external>
          ▸ Diagnose My AI Problem
        </BaseButton>
        <BaseButton variant="glass" size="lg" @click="scrollToWork">
          See Live Work →
        </BaseButton>
      </div>
      <div class="hero-stats" v-if="stats">
        <StatItem label="YEARS" value="17" sublabel="Digital Transformation" />
        <StatItem :label="`${stats.awards_count} GLOBAL AWARDS`"
                  :value="stats.awards_count"
                  sublabel="Outskill · Alibaba · Google · Fenox" />
        <StatItem :label="`${stats.enterprise_brands.length}+ ENTERPRISE BRANDS`"
                  :value="`${stats.enterprise_brands.length}+`"
                  :sublabel="stats.enterprise_brands.slice(0,3).join(' · ')" />
      </div>
    </div>
  </section>
</template>
```

#### Service Worker Pre-cache

```javascript
// frontend/public/sw.js — add to existing pre-cache list
const VIDEOS = [
  '/videos/hero-loop.mp4',
  '/videos/hero-loop.webm',
  '/videos/hero-poster.jpg',
];
```

#### Acceptance Criteria

- [ ] Hero renders without scroll above 100vh on desktop and mobile
- [ ] Video autoplays muted, loops seamlessly
- [ ] `prefers-reduced-motion: reduce` users see static poster, no video download
- [ ] Slow connection (effectiveType=2g) → poster only
- [ ] Stats fetch from `/api/homepage/stats` via TanStack Query (60min staleTime)
- [ ] Stats numbers count up from 0 on viewport intersection (1.2s ease-out)
- [ ] WhatsApp CTA opens `https://wa.me/{number}?text=Halo%20Ali...` (number from settings)
- [ ] "See Live Work →" smooth-scrolls to Section 2
- [ ] Lighthouse mobile LCP ≤ 2.5s
- [ ] All hero text passes WCAG AA contrast on top of video overlay
- [ ] Keyboard tab order: eyebrow → headline (skipped, decorative) → CTAs → stats

#### Tests

- Cypress E2E: hero loads, both CTAs click correctly
- Visual regression: hero matches Figma spec at 1920×1080, 1366×768, 414×896

---

### Phase 3 — "What I Solve" Tabbed Switcher

**Effort:** 1.5 days
**Dependencies:** None (parallel-able with Phase 2)

#### Files

**Create:**
- `frontend/src/components/home/WhatISolveTabs.vue`
- `frontend/src/data/whatISolve.js` (static content for 3 tabs — copy lives here, not API)

**Modify:**
- `frontend/src/views/Home.vue` — mount new component
- `backend/database/seeders/PageSectionSeeder.php` — replace 4 skill sections with single `what-i-solve` row

**Delete (move to Phase 8 cleanup):**
- `frontend/src/components/home/SkillsReel.vue`
- `frontend/src/components/home/SkillShowcase.vue`

#### Tab Content (whatISolve.js)

```javascript
export const tabs = [
  {
    id: 'vibe-coding',
    label: 'Vibe Coding',
    icon: '⚡',
    headline: 'I ship production code at AI speed — without sacrificing architecture quality.',
    metrics: [
      { value: '3 days', label: 'Idea → deployed' },
      { value: '27', label: 'Plugins shipped' },
      { value: '0', label: 'Prod regressions' },
    ],
    visual: '/images/showcases/vibe-coding-cursor.png',
    cta: { label: 'Case study', href: '/work#cursor-portfolio-v2' },
  },
  {
    id: 'ai-agents',
    label: 'AI Agents',
    icon: '🤖',
    headline: 'Multi-agent systems that handle complex business workflows autonomously.',
    metrics: [
      { value: '8', label: 'Agents in production' },
      { value: '200+ hrs', label: 'Saved per week' },
      { value: '99.2%', label: 'Task completion' },
    ],
    visual: '/images/showcases/openclaw-orchestrator.png',
    cta: { label: 'See live demo', href: '/work#openclaw' },
  },
  {
    id: 'video-gen',
    label: 'Video Generation',
    icon: '🎬',
    headline: 'Cinematic AI video at scale — for products, ads, and brand storytelling.',
    metrics: [
      { value: '50+', label: 'Videos shipped' },
      { value: 'VEO 3.1', label: '+ Kling AI + Seedance 2.0' },
      { value: '4K', label: 'Production quality' },
    ],
    visual: '/images/showcases/veo-kling-reel.png',
    cta: { label: 'Watch reel', href: '/work#video-reel' },
  },
];
```

#### Acceptance Criteria

- [ ] All 3 tabs render in single viewport (no scroll within section on desktop ≥1024px)
- [ ] Tab click triggers 120ms fade-out + 200ms fade-in, no harsh cut
- [ ] Default tab: Vibe Coding
- [ ] Mobile (<768px): tabs become horizontal scroll with snap-x
- [ ] ARIA: `role="tablist"`, `role="tab"` (with `aria-selected`), `role="tabpanel"`
- [ ] Keyboard: ←/→ cycles tabs, Tab follows DOM order
- [ ] Tab hover: gold underline grows left→right (180ms ease-out)
- [ ] Reduced motion: all transitions instant
- [ ] Old 4 skill sections completely removed from rendered Home.vue
- [ ] PageSectionSeeder updated, `php artisan db:seed --class=PageSectionSeeder` produces 6 sections (down from 10)

---

### Phase 4 — Magazine Grid (Editorial Latest Articles)

**Effort:** 0.5 day
**Dependencies:** Phase 1 (featured endpoint)

#### Files

**Create:**
- `frontend/src/components/home/MagazineGrid.vue`

**Modify:**
- `frontend/src/components/blog/LatestBlog.vue` — accept new prop `variant: 'standard' | 'magazine'`, magazine adds Playfair headline + Content Engine badge
- `frontend/src/views/Home.vue` — replace LatestBlog usage with MagazineGrid

#### MagazineGrid Layout

- Desktop: 7-col hero card (left) + 5-col stack of 3 secondary (right)
- Mobile: hero stacked first, then 3 secondary below
- Hero card: Playfair Display 2.5rem italic headline, gold border on hover
- Badge: "Powered by my own AI Content Engine" — JetBrains Mono micro-text top-right corner

#### Acceptance Criteria

- [ ] 4 articles render (1 hero + 3 secondary)
- [ ] Hero hover: scale 1.02 + gold glow (220ms)
- [ ] All cards link to `/blog/{slug}`
- [ ] Loading state: 4 skeleton cards (BlogSkeleton variant)
- [ ] Empty state: friendly message
- [ ] Content Engine badge visible
- [ ] Mobile stack respects reading order
- [ ] `<time datetime>` semantic element on each card

---

### Phase 5 — Receipts Bento (Metric-Led Projects)

**Effort:** 1 day
**Dependencies:** Phase 1 (featured endpoint includes featured_projects)

#### Files

**Create:**
- `frontend/src/components/home/ReceiptsBento.vue`

**Modify:**
- `frontend/src/components/projects/ProjectsBento.vue` — add `display="metric"` prop, render outcome metric overlay instead of face overlay

#### Bento Layout

- 2 large cards (6-col each), heights asymmetric (one taller)
- 3 small cards (4-col each) below
- Each card: project hero image + industry chip + headline metric (e.g., "Rp 8.3T digitalization") + "Case study →" CTA
- NO face shots on cards (kill stock-photo overuse pattern)

#### Acceptance Criteria

- [ ] 5 projects render with outcome metric prominent
- [ ] Hover: lift -4px + accent border (180ms ease-out)
- [ ] Mobile: 1-col stack, all 5 visible
- [ ] "All 56 projects" CTA below grid links to `/projects`
- [ ] Existing `/projects` and `/work` pages unaffected (still use face variant)

---

### Phase 6 — Awards Named Cards + Testimonials Carousel

**Effort:** 1.75 days (combined)
**Dependencies:** Phase 1 (migrations + featured endpoint), Phase 0 (LinkedIn permissions)

#### Files

**Create:**
- `frontend/src/components/home/AwardsNamedCards.vue`
- `frontend/src/components/home/TestimonialsCarousel.vue`
- `backend/database/seeders/TestimonialLinkedInSeeder.php`
- `backend/database/seeders/AwardFeaturedFlagsSeeder.php`

**Modify:**
- `frontend/src/views/admin/TestimonialCreate.vue` — add `source` + `source_url` form fields
- `frontend/src/views/admin/TestimonialEdit.vue` — same

#### Awards Section Layout

- 6 cards in 3-col grid (desktop) → 2-col (tablet) → 1-col (mobile)
- **Outskill leads** (top-left, slightly larger card with subtle gold pulse animation)
- Each card: award logo/icon + title (caps) + organization + year/place + 1 sentence proof

```javascript
// AwardsNamedCards default order (overridable via DB is_featured + sort)
const awards = [
  {
    title: 'AI GENERALIST FELLOWSHIP',
    org: 'Outskill (Bengaluru, India)',
    place: '1st Place · Demo Day 2026',
    proof: 'Beat 26 startups from 16 countries',
    badge: 'NEWEST',
    leadCard: true,  // larger + animated
  },
  {
    title: 'eFOUNDERS FELLOWSHIP',
    org: 'Alibaba (Hangzhou, China)',
    place: 'Selected Fellow',
    proof: 'Cross-border ecommerce program',
  },
  {
    title: 'STARTUP GRIND',
    org: 'Google (Silicon Valley)',
    place: 'Featured Speaker · 2018',
    proof: 'Global Conference attendee',
  },
  {
    title: 'STARTUP WORLD CUP',
    org: 'Fenox VC',
    place: 'Wild Card 1st Place',
    proof: 'Indonesia regional final',
  },
  {
    title: 'NEXTDEV STARTUP',
    org: 'Telkomsel',
    place: '1st Place Winner',
    proof: 'National competition champion',
  },
  {
    title: 'IDBYTE 2017 CONNECTED',
    org: 'IDBYTE Conference',
    place: 'Top 8 Finalist',
    proof: 'Indonesian tech ecosystem',
  },
];
```

#### Testimonials Seed Data

Source: 4 LinkedIn recommendations (already drafted in brainstorm)

```php
// TestimonialLinkedInSeeder.php
[
  [
    'name' => 'Galuh Koco Sadewo',
    'role' => 'Co-Founder & Chief Business Dev',
    'company' => 'BOTIKA',
    'content' => 'Ali demonstrated strong founder-level leadership in steering Marlin Booking from early-stage startup into a platform that successfully supported the Indonesian government in digitizing passenger transport.',
    'source' => 'linkedin',
    'source_url' => 'https://linkedin.com/in/alisadikinma/details/recommendations/',
    'avatar_url' => '/storage/testimonials/galuh.jpg',
  ],
  [
    'name' => 'Andria Gutama',
    'role' => 'IT Manager (former direct report)',
    'company' => 'Digital Operation Transformation',
    'content' => 'Pak Ali is an exceptional leader and a highly respected expert in Artificial Intelligence. His strategic thinking, clarity in direction, and deep technical insight have been instrumental in shaping the foundation of our department.',
    'source' => 'linkedin',
    'source_url' => 'https://linkedin.com/in/alisadikinma/details/recommendations/',
    'avatar_url' => '/storage/testimonials/andria.jpg',
  ],
  [
    'name' => 'Jairo Launio',
    'role' => 'Senior Software Engineer',
    'company' => 'Advantive (former teammate at MPA Singapore)',
    'content' => 'During our time at MPA Singapore, Ali consistently demonstrated strong work ethic, dedication, and a genuine commitment to delivering high-quality results. Exceptional team player, always proactive, reliable, and ready to support others.',
    'source' => 'linkedin',
    'source_url' => 'https://linkedin.com/in/alisadikinma/details/recommendations/',
    'avatar_url' => '/storage/testimonials/jairo.jpg',
  ],
  [
    'name' => 'Megawati Megawati',
    'role' => 'ICF Associate Certified Coach',
    'company' => 'Six Seconds EQCC (former senior at Satnusa)',
    'content' => 'Ali managed ISC Project Integration for XIAOMI and oversaw initiatives like the MySatnusa Super App, our first AI implementation, and RPA deployment. These projects helped improve internal efficiency and modernize processes.',
    'source' => 'linkedin',
    'source_url' => 'https://linkedin.com/in/alisadikinma/details/recommendations/',
    'avatar_url' => '/storage/testimonials/megawati.jpg',
  ],
];
```

#### Testimonials Carousel Spec

- Glass card (existing token)
- Single large quote rotating every 8s (auto, pause on hover)
- Manual nav via dots (4 visible)
- Keyboard: ← / → cycle
- Quote in Inter italic 1.125rem
- Attribution in JetBrains Mono uppercase 0.75rem
- "via LinkedIn ↗" badge, link opens new tab to `source_url`
- Mobile: same single-card pattern, full-width

#### Acceptance Criteria — Awards

- [ ] 6 cards render in correct order (Outskill first)
- [ ] Outskill card visibly distinct (lead card treatment)
- [ ] All cards have title + org + place + proof
- [ ] Hover: card lift -4px + gold underline grow
- [ ] Mobile: 1-col stack, lead card stays first
- [ ] ARIA: section has `aria-label="Recognition"`
- [ ] Each card is `<article>` semantic

#### Acceptance Criteria — Testimonials

- [ ] 4 testimonials seeded from `TestimonialLinkedInSeeder` after `php artisan db:seed`
- [ ] Carousel auto-rotates every 8s
- [ ] Hover pauses rotation
- [ ] Dots reflect current index, click to jump
- [ ] "via LinkedIn ↗" badge opens new tab (target=_blank rel=noopener)
- [ ] Empty state if 0 LinkedIn testimonials: section hides entirely (not blank space)
- [ ] Admin panel can edit `source` + `source_url` fields

#### Tests

```php
// backend/tests/Feature/HomepageFeaturedTest.php
test('featured testimonials only includes source=linkedin')
test('featured awards leads with is_featured=true ordered by id desc')
```

---

### Phase 7 — CTA Diagnosis Section + Calendly Integration

**Effort:** 0.5 day
**Dependencies:** Phase 0 (Calendly URL)

#### Files

**Create:**
- `frontend/src/components/home/CTADiagnosis.vue`

**Modify:**
- `frontend/src/components/CTASection.vue` — DEPRECATED for home, keep file for /about, /projects, /gallery, /blog-detail
- `frontend/src/views/Home.vue` — replace CTASection with CTADiagnosis at bottom

#### CTADiagnosis Spec

```vue
<template>
  <section class="cta-diagnosis" aria-label="Get in touch">
    <div class="cta-aurora"></div>
    <div class="cta-content">
      <h2 class="cta-headline">Got an AI problem? Let's diagnose it.</h2>
      <p class="cta-sub">
        Free 30-min call. Walk away with a plan, even if we don't work together.
      </p>
      <div class="cta-actions">
        <BaseButton variant="gold" size="lg" :href="whatsappUrl" external>
          💬 WhatsApp Now
        </BaseButton>
        <BaseButton variant="glass" size="lg" :href="calendlyUrl" external>
          📅 Book 30-min slot
        </BaseButton>
      </div>
      <p class="cta-microtext">
        Typically replies within 4 hours · UTC+7 (Jakarta)
      </p>
    </div>
  </section>
</template>
```

#### Acceptance Criteria

- [ ] No "Amazing", "Cutting-edge", "Revolutionary" buzzwords (anti-AI-slop)
- [ ] Specific microcopy ("30-min call", "4 hours", "UTC+7")
- [ ] Risk reversal copy ("walk away with a plan, even if we don't work together")
- [ ] WhatsApp link opens with pre-filled greeting
- [ ] Calendly link opens new tab
- [ ] Aurora background pulses 8s loop, respects reduced-motion
- [ ] Other pages (/about, /projects, /gallery, /blog-detail) still use original CTASection unchanged

---

### Phase 8 — Polish, Cleanup, A11y, Performance

**Effort:** 1 day
**Dependencies:** Phases 2-7 complete

#### Tasks

**Code cleanup:**
- [ ] Delete `frontend/src/components/CinematicHero.vue` (deprecated in Phase 2)
- [ ] Delete `frontend/src/components/home/SkillsReel.vue`
- [ ] Delete `frontend/src/components/home/SkillShowcase.vue` (×4 if separate files)
- [ ] Delete `frontend/src/components/home/StatsBar.vue`
- [ ] Remove unused imports in Home.vue
- [ ] Run `npm run build` and verify zero unused-export warnings

**A11y audit:**
- [ ] Run axe DevTools on local build → 0 critical / 0 serious violations
- [ ] Keyboard nav: Tab through entire page, all interactive elements reachable + visible focus
- [ ] Screen reader walkthrough (NVDA or VoiceOver): logical reading order, all images alt-tagged
- [ ] Color contrast: every text/bg combo ≥ WCAG AA (4.5:1 body, 3:1 large)

**Performance:**
- [ ] Lighthouse mobile: Performance ≥ 85, Accessibility ≥ 95, Best Practices ≥ 95, SEO ≥ 95
- [ ] Hero LCP ≤ 2.5s (poster only counts toward LCP, not video)
- [ ] CLS ≤ 0.1 (reserve image dimensions, no late-loading shifts)
- [ ] Total JS bundle: ≤ 350 KB gzipped (current baseline TBD)
- [ ] Verify no console errors on production
- [ ] Verify service worker pre-caches new hero video assets

**Anti-AI-slop final pass:**
- [ ] grep for banned phrases: "Amazing", "Cutting-edge", "Revolutionary", "Game-changing", "Innovative" → 0 hits
- [ ] No purple-pink gradients
- [ ] No Inter as display font (Space Grotesk only)
- [ ] All sections have intentional asymmetry or data-driven layout
- [ ] All motion 150-300ms (per reasoning rules)

**Browser matrix:**
- [ ] Chrome 120+ ✓
- [ ] Safari 17+ ✓
- [ ] Firefox 120+ ✓
- [ ] Edge 120+ ✓
- [ ] Mobile Safari iOS 16+ ✓
- [ ] Chrome Android ✓

---

### Phase 9 — Hero Video Production (parallel)

**Effort:** Variable (1-2 days production + iteration)
**Dependencies:** Phase 0 storyboard approved
**Detailed brief:** [docs/plans/hero-video/strategic-brief.md](hero-video/strategic-brief.md) (Phase 1 brainstorm output via `/video-brainstorm`)
**Cast:** [docs/plans/hero-video/cast-profile.md](hero-video/cast-profile.md)
**Concept:** Genesis Triptych (abstract metaphorical, no human face) — locked via brainstorm 2026-05-04
**Generation mode:** Image-to-video via Seedance 2.0 @Image references (Method 2: Full Omni-Reference)

#### Storyboard (15s seamless loop — Genesis Triptych)

| Beat | Time | Visual | Source Tool |
|---|---|---|---|
| 1 — HOOK | 0-2s | Gold particles converge to glyph | Seedance 2.0 (image-to-video from NB2 keyframe @Image1) |
| 2 — TENSION | 2-4s | Glyph splits into 3 columns (cyan/GOLD/cyan) | Seedance 2.0 (@Image2) |
| 4-6 — REVEALS | 4-11.5s | Slow circular dolly through Vibe Coding · AI Agents · Video Gen columns | Seedance 2.0 (@Image3 mid-orbit + @Video1 motion ref) |
| 7a — RESOLUTION | 11.5-13.5s | Columns merge → "AI GENERALIST" wordmark | Seedance 2.0 (@Image4) |
| 7b — KICKER | 13.5-15s | Micro-text: `1ST · OUTSKILL DEMO DAY 2026 · 26 STARTUPS · 16 COUNTRIES` + particle dispersion → fade black | Seedance 2.0 (@Image5) |

#### Output Specs

- Format: MP4 (H.264, baseline profile) + WebM (AV1)
- Resolution: 2048×1152 (desktop landscape) + 1152×2048 (mobile portrait native)
- Duration: 15s exact loop (= Seedance 2.0 single-clip max, no chaining)
- Frame rate: 24fps (cinematic)
- Audio: NONE (autoplay muted hero)
- File size: ≤ 7MB MP4, ≤ 4.5MB WebM landscape
- Loop: seamless fade-to-black at frame 15 = frame 0
- Generation cost: ~$0.33 per render × 10 iterations = ~$3.30 total (Seedance pricing)

#### Acceptance Criteria

- [ ] Video files placed at `frontend/public/videos/hero-loop.{mp4,webm}`
- [ ] Poster frame extracted as `hero-poster.jpg`
- [ ] Loop tested seamless on Chrome/Safari/Firefox desktop + mobile
- [ ] File sizes within budget
- [ ] Service worker pre-cache list updated
- [ ] Replaces placeholder video committed in Phase 2

---

## 4. Page Sections Mapping (DB sync — CRITICAL per CLAUDE.md gotcha)

After Phase 8, `page_sections` table state:

```sql
-- DELETE
DELETE FROM page_sections WHERE page_type='homepage' AND section_type IN (
  'skills-reel',
  'skill-vibe-coding', 'skill-ai-automation', 'skill-ai-agents', 'skill-ai-video',
  'stats-cta',
  -- legacy snake_case ghosts (per CLAUDE.md)
  'featured_projects', 'latest_blog', 'cta'
);

-- INSERT
INSERT INTO page_sections (page_type, section_type, title, is_active, sort_order) VALUES
  ('homepage', 'hero',           'Hero — AI Triptych Video',         1, 1),
  ('homepage', 'what-i-solve',   'What I Solve — 3 Disciplines',      1, 2),
  ('homepage', 'magazine',       'Latest Thinking',                   1, 3),
  ('homepage', 'receipts',       'Receipts — Real Projects',          1, 4),
  ('homepage', 'awards',         'Recognition — Global Stages',       1, 5),
  ('homepage', 'testimonials',   'People Who Worked With Me',         1, 6),
  ('homepage', 'cta-diagnosis',  'Got an AI Problem',                 1, 7);
```

Done via `PageSectionSeeder` update in Phase 1 commit (idempotent: delete-then-insert, gated by `php artisan db:seed --class=PageSectionSeeder`).

**Update CLAUDE.md "Page Sections Mapping" table** after Phase 8 to reflect new section_types ↔ component map.

---

## 5. Risk Register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | Hero video render quality below standard (cheesy, AI-slop) | Medium | High | Phase 9 iterates 2-3 takes; storyboard approval gate before render |
| R2 | LinkedIn permission denied by 1+ recommender | Low | Medium | Need ≥3 of 4 to launch carousel; if 2-3, reduce to single rotating quote |
| R3 | Cloudflare CF-IPCountry header absent in some regions | Low | Low | ip-api.com fallback handles, fail-open to EN safe default |
| R4 | Service worker caches OLD video on update | Medium | Medium | sw.js cache-busting via version increment in Phase 2 |
| R5 | Lighthouse score regression vs current | Medium | High | Performance budget enforced in Phase 8, CI Lighthouse audit pre-merge |
| R6 | Page Sections DB drift between dev/prod | Medium | Medium | Idempotent seeder run as part of `deploy.sh` step 4 |
| R7 | Mobile portrait video file size blows budget | Medium | Low | Use lower bitrate or skip portrait variant, fall back to poster on portrait |
| R8 | Calendly URL expires / Ali changes link | Low | Low | Read from env var, easy hotfix |
| R9 | Old skill components still imported somewhere unexpected | Low | Medium | Phase 8 cleanup runs `grep -r "SkillsReel\|SkillShowcase"` before delete |
| R10 | Outskill award image (lead card) not yet uploaded | High | Low | Phase 0 manual: Ali uploads Outskill image to /storage/awards/ before Phase 6 |

---

## 6. Verification Chain

Per gaspol-finish convention, after Phase 8:

1. **gaspol-verify Layer 1-4** — type check, tests, design compliance, anti-AI-slop
2. **gaspol-review** — code-reviewer subagent
3. **plan-verifier** — confirm all phase acceptance criteria met
4. **gaspol-sync-docs** — update CLAUDE.md "Page Sections Mapping" + add new section
5. **Lighthouse production audit** — confirm ≥85 perf / ≥95 a11y on alisadikinma.com
6. **Browser matrix QA** — manual smoke on 6 browsers × 2 form factors

---

## 6.5 Phase 10 — CV Master API for jobhunter Consumption

**Effort:** 1 day
**Dependencies:** None (can run parallel with Phases 2-7)
**Consumer:** `D:\Projects\jobhunter` platform (CV tailoring service)

### Context

jobhunter's `cv-tailor` skill takes a master CV in JSON Resume schema (with `summary_variants` + `relevance_hint` extensions) and produces variant-specific tailored CVs per job. Currently the master CV lives static at `D:\Projects\jobhunter\docs\seed\master-cv.template.json`. Portfolio_v2 is the **live source-of-truth** for projects (56), awards (6), articles (52), and basics — so jobhunter should pull from API instead of maintaining stale static JSON.

### Scope Boundary (deliberate)

**Portfolio_v2 owns:**
- `basics` (name, email, url, location, profiles) — from `settings` group=about
- `projects` (56 rows) — Project model mapped to JSON Resume `projects[]`
- `awards` (6 rows including Outskill ⭐) — Award model
- `thought_leadership` (top 5 published posts) — Post model, NEW field beyond JSON Resume spec

**jobhunter retains:**
- `work` history (structured employment) — too editorial / ATS-tuned to live in Portfolio
- `education` — static, never changes
- `skills_categorized` — needs hand-curation for ATS keyword density
- `summary_variants` — these are CV strategy, not portfolio content

**Merge strategy:** jobhunter `cv-tailor` fetches `/api/cv/export` from Portfolio_v2, merges with its local `master-cv.template.json`, fields from Portfolio override matching keys in template (so projects/awards/basics are always live).

### Files

**Create:**
- `backend/app/Http/Controllers/Api/CvExportController.php`
- `backend/app/Http/Resources/Cv/CvBasicsResource.php`
- `backend/app/Http/Resources/Cv/CvProjectResource.php`
- `backend/app/Http/Resources/Cv/CvAwardResource.php`
- `backend/app/Http/Resources/Cv/CvThoughtResource.php`

**Modify:**
- `backend/routes/api.php` — register `/api/cv/export` under `auth:sanctum` + `throttle:30,1`
- `backend/app/Models/Project.php` — add `toJsonResumeProject()` mapper method (or use Resource)

### API Contract

```http
GET /api/cv/export
Authorization: Bearer {jobhunter_service_token}
Accept: application/json

Response 200:
{
  "success": true,
  "data": {
    "schema_version": "1.0.0",
    "generated_at": "2026-05-04T14:30:00Z",
    "basics": {
      "name": "Ali Sadikin",
      "label": "AI Generalist Expert",
      "email": "ali.sadikincom85@gmail.com",
      "phone": "+62-...",
      "url": "https://alisadikinma.com",
      "summary": "...",
      "location": { "city": "Batam", "country": "Indonesia", "remote": true },
      "profiles": [
        { "network": "LinkedIn", "url": "https://linkedin.com/in/alisadikin" },
        { "network": "GitHub", "url": "https://github.com/alisadikinma" }
      ]
    },
    "projects": [
      {
        "name": "MySatnusa Super App",
        "description": "First AI implementation + RPA across departments",
        "url": "https://alisadikinma.com/projects/mysatnusa",
        "industry": "Manufacturing (Xiaomi ISC)",
        "metrics": { "departments": 7, "rpa_processes": 12 },
        "tags": ["ai-implementation", "rpa", "enterprise", "mobile"],
        "highlights": ["Led integration with Xiaomi ISC"],
        "relevance_hint": ["ai_automation", "vibe_coding"],
        "start_date": "2023-06",
        "end_date": "2024-08"
      }
      // ... 55 more
    ],
    "awards": [
      {
        "title": "1st Place — AI Generalist Fellowship Demo Day 2026",
        "awarder": "Outskill (Bengaluru, India)",
        "date": "2026-01-17",
        "summary": "Beat 26 startups from 16 countries with SparkFluence — AI-Powered Platform for Viral Content Creation",
        "tags": ["ai-generalist", "demo-day", "global-competition"],
        "is_featured": true
      }
      // ... 5 more
    ],
    "thought_leadership": [
      {
        "title": "...",
        "url": "https://alisadikinma.com/blog/...",
        "published_at": "2026-04-20",
        "topics": ["AI Agents", "Production Patterns"],
        "excerpt": "..."
      }
      // top 5 by recency, status=published
    ]
  }
}
```

### Authentication

Use existing `personal_access_tokens` (Sanctum) infrastructure:

```bash
# One-time on Portfolio_v2 prod
php artisan tinker
> $u = User::find(1);
> $token = $u->createToken('jobhunter-cv-export', ['cv:read'])->plainTextToken;
> // Save to jobhunter .env as PORTFOLIO_CV_TOKEN
```

Route guard:

```php
Route::middleware(['auth:sanctum', 'ability:cv:read', 'throttle:30,1'])
    ->get('/cv/export', [CvExportController::class, 'export']);
```

Rate limit 30 req/min ample (jobhunter typical pattern: 1 fetch per CV-tailor invocation, cached in jobhunter side for 1 hour).

### CvExportController Implementation Outline

```php
public function export(Request $request): JsonResponse
{
    $about = Setting::group('about')->pluck('value', 'key');
    $profiles = json_decode($about['social_media'] ?? '[]', true);

    return response()->json([
        'success' => true,
        'data' => [
            'schema_version' => '1.0.0',
            'generated_at' => now()->toIso8601ZuluString(),
            'basics' => [
                'name' => $about['full_name'] ?? 'Ali Sadikin',
                'label' => $about['headline'] ?? 'AI Generalist Expert',
                'email' => $about['email'] ?? null,
                'phone' => $about['phone'] ?? null,
                'url' => config('app.url'),
                'summary' => $about['bio'] ?? null,
                'location' => [
                    'city' => $about['city'] ?? null,
                    'country' => $about['country'] ?? 'Indonesia',
                    'remote' => true,
                ],
                'profiles' => collect($profiles)->map(fn ($p) => [
                    'network' => $p['platform'] ?? null,
                    'url' => $p['url'] ?? null,
                ])->filter(fn ($p) => $p['url'])->values(),
            ],
            'projects' => CvProjectResource::collection(
                Project::with('translations')->orderBy('sort_order')->get()
            ),
            'awards' => CvAwardResource::collection(
                Award::orderByDesc('is_featured')->orderByDesc('id')->get()
            ),
            'thought_leadership' => CvThoughtResource::collection(
                Post::with('translations', 'category')
                    ->where('status', 'published')
                    ->latest('published_at')
                    ->limit(5)
                    ->get()
            ),
        ],
    ]);
}
```

### jobhunter Side Integration

Update `D:\Projects\jobhunter\backend\scripts\seed_master_cv.py` (or equivalent) to fetch from Portfolio_v2 + merge with template:

```python
# pseudo-code
import requests, os, json

def build_master_cv():
    # 1. Load static template (work history + education + skills + variants)
    with open('docs/seed/master-cv.template.json') as f:
        master = json.load(f)

    # 2. Fetch live data from Portfolio_v2
    token = os.environ['PORTFOLIO_CV_TOKEN']
    r = requests.get(
        'https://alisadikinma.com/api/cv/export',
        headers={'Authorization': f'Bearer {token}'},
        timeout=10,
    )
    portfolio_data = r.json()['data']

    # 3. Merge: Portfolio fields override template
    master['basics'].update(portfolio_data['basics'])
    master['projects'] = portfolio_data['projects']  # full replace
    master['awards'] = portfolio_data['awards']      # full replace
    master['thought_leadership'] = portfolio_data['thought_leadership']  # NEW field

    return master
```

### Acceptance Criteria

- [ ] `GET /api/cv/export` requires Bearer token with `cv:read` ability
- [ ] Token without ability returns 403
- [ ] Rate limit 30/min enforced
- [ ] Response shape matches contract above
- [ ] All projects mapped (56 rows)
- [ ] All 6 awards present, Outskill flagged `is_featured=true`
- [ ] Top 5 published posts in `thought_leadership`
- [ ] `relevance_hint` field on each project (auto-derived from `tags` or `industry`, mapping rule documented in CvProjectResource docblock)
- [ ] jobhunter `seed_master_cv.py` modified + smoke-tested → produces merged JSON validating against existing JSON Resume schema test
- [ ] Portfolio_v2 CLAUDE.md gains "CV Master Export API" section explaining the contract + how to mint jobhunter token

### Tests

```php
// backend/tests/Feature/CvExportApiTest.php
test('returns 401 without token')
test('returns 403 with token missing cv:read ability')
test('returns full payload with valid token')
test('basics pulled from settings group=about')
test('projects ordered by sort_order')
test('awards ordered by is_featured DESC then date DESC')
test('thought_leadership has exactly 5 most recent published posts')
test('schema_version present')
```

### Risks

| # | Risk | Mitigation |
|---|---|---|
| C1 | Portfolio_v2 settings table doesn't store all `basics` fields jobhunter expects | Inventory `settings` group=about keys before implementation; add migration if needed for missing keys (phone, headline, city) |
| C2 | Token leak risk if .env shared accidentally | Document scoped ability `cv:read` only (no write/delete); rotate quarterly |
| C3 | `relevance_hint` mapping is heuristic, may misclassify | Initial mapping based on `industry` + `tags`; allow manual override via new `cv_relevance_hints` JSON column on projects (defer to Phase 10.5 if needed) |

### Rollback

- Disable route via feature flag `CV_EXPORT_ENABLED=false` in `.env`
- jobhunter falls back to static `master-cv.template.json` (no break)



```
Day 1:  Phase 0 (parallel, async) + Phase 1 (Backend foundation)
Day 2:  Phase 2 (Hero) ←──┐
Day 3:  Phase 3 (What I Solve)        ├── parallel-able
Day 4:  Phase 4 (Magazine) + Phase 5 (Receipts) + Phase 10 (CV Export API) ←──┘
Day 5:  Phase 6 (Awards + Testimonials)
Day 6:  Phase 7 (CTA) + Phase 8 polish starts
Day 7:  Phase 8 polish complete + verification chain
Day 8+: Phase 9 video lands, replaces placeholder, final commit
```

**Total:** 10 working days for code + 1-2 days for video production (parallel).
Phase 10 + Phase 11 fit into Day 4 (parallel-able). Phase 12 spans Day 9-10 (perf hardening, post-launch).

---

## 8. Phase 11 — Work Experience ↔ Gallery Linking (About Page Credibility)

**Effort:** 0.75 day
**Dependencies:** None (parallel-able with all UI phases)
**Page affected:** `/about` and `/en/about`

### Context

The About page Work Experience section currently renders 6 work entries as text-only cards. To boost credibility (operator photos at site, awards from that period, team shots), each entry should optionally link to a Gallery and render thumbnails inline within the card.

### Current State Audit (verified 2026-05-04)

| Layer | Status | Evidence |
|---|---|---|
| Backend schema | ✅ Ready | `settings.about.experience[i].gallery_ids` array exists on all 6 entries (currently `[]`) |
| Admin picker UI | ✅ Ready | [AboutSettings.vue:1039](frontend/src/views/admin/AboutSettings.vue#L1039) has `v-model="exp.gallery_ids"` multi-select |
| Frontend display | ❌ Missing | [About.vue](frontend/src/views/About.vue) ignores `gallery_ids` — never renders thumbnails |
| Data wiring | ❌ Empty | All 6 experience entries have `gallery_ids: []` |

### Available Galleries (8 total, ready to link)

| ID | Title | Map to Experience |
|---|---|---|
| 14 | Singapore Career Journey | eXSYS · DHL · Thales/Gemalto · MPA Singapore |
| 16 | Digital Transformation Leadership | PT. Sat Nusapersada Tbk |
| 9, 10, 11 | Nextdev / Wild Card / IDByte | Marlin Booking era |
| 12, 13, 20 | Alibaba / Google / Outskill | Already in Awards section, optional cross-link |

### Files

**Modify only (no new files except optional helper):**
- `frontend/src/views/About.vue` — render thumbnail row on each experience card; click opens existing `BaseGalleryModal`
- `backend/app/Http/Controllers/Api/SettingsController.php` — `about()` method post-processes experience array, replaces `gallery_ids: [14, 16]` with hydrated `galleries: [{id, title, thumbnail, items_count, preview_items}]` (single round-trip, no N+1)
- `frontend/src/composables/useAboutSettings.js` — surface new hydrated `galleries` field to consumers

**No backend schema migration needed.**

### UX Spec — Per Card

```
┌───────────────────────────────────────────────────┐
│ [📷 ID flag]  JAN 2021 — DEC 2025  ·  4Y 11M       │
│              Head of Digital Transformation Dept   │
│              🏢 PT. Sat Nusapersada Tbk            │
│              - Led digital transformation...       │
│              [Show Less ⌃]                         │
│                                                    │
│              ─── Gallery (5 photos) ───            │
│              ┌──┐ ┌──┐ ┌──┐ ┌──┐ ┌──┐              │
│              │  │ │  │ │  │ │  │ │+1│              │  ← 80x80 thumbs, click → modal
│              └──┘ └──┘ └──┘ └──┘ └──┘              │
└───────────────────────────────────────────────────┘
```

- Max **4 thumbnails inline**, "+N" overflow chip if more
- Section header `─── Gallery (N photos) ───` only when `galleries.length > 0` (silent absence otherwise)
- Reuse existing `BaseGalleryModal` lightbox
- Hover: scale 1.05 + gold border (180ms ease-out)
- Mobile <640px: 3 thumbnails + overflow chip
- Reduced motion: no scale, just border swap

### Backend Hydration Implementation

```php
// SettingsController::about() patch (Option B: backend hydrates)
public function about(): JsonResponse
{
    $settings = Setting::where('group', 'about')->get();
    $expSetting = $settings->firstWhere('key', 'experience');

    if ($expSetting) {
        $experience = json_decode($expSetting->value, true);
        $allIds = collect($experience)
            ->pluck('gallery_ids')->flatten()->unique()->filter()->values();

        if ($allIds->isNotEmpty()) {
            $galleryMap = Gallery::with(['items' => fn ($q) => $q->limit(4)])
                ->whereIn('id', $allIds)
                ->get()
                ->keyBy('id');

            $experience = collect($experience)->map(function ($exp) use ($galleryMap) {
                $exp['galleries'] = collect($exp['gallery_ids'] ?? [])
                    ->map(fn ($id) => $galleryMap->get($id))
                    ->filter()
                    ->map(fn ($g) => [
                        'id' => $g->id,
                        'title' => $g->title,
                        'thumbnail' => $g->thumbnail ? Storage::url($g->thumbnail) : null,
                        'items_count' => $g->items->count(),
                        'preview_items' => $g->items->take(4)->map(fn ($i) => [
                            'id' => $i->id,
                            'thumbnail' => Storage::url($i->file_path),
                        ])->values(),
                    ])
                    ->values()
                    ->all();
                return $exp;
            })->all();

            $expSetting->setAttribute('value', $experience);  // hydrated array, not JSON string
        }
    }

    return response()->json(['success' => true, 'data' => $settings]);
}
```

### Acceptance Criteria

- [ ] `/api/settings/about` returns experience entries with hydrated `galleries[]` array when `gallery_ids` non-empty
- [ ] About.vue renders thumbnail row when `exp.galleries.length > 0`
- [ ] Section header `Gallery (N photos)` shows when galleries present, hidden otherwise
- [ ] Thumbnail click opens `BaseGalleryModal` with full gallery items
- [ ] Mobile breakpoint: 3 thumbnails + overflow chip
- [ ] Hover micro-interaction within 180ms
- [ ] No N+1 request issue (single `/api/settings/about` call)
- [ ] Operator can link/unlink galleries via existing AboutSettings admin UI
- [ ] Works in both `/about` (ID) and `/en/about` (EN)
- [ ] Hydration filters out missing/deleted gallery IDs gracefully (no 500)

### Operator Data Action (post-deploy, not part of code)

Suggested gallery_ids assignment for the 6 existing experiences:

| Experience | gallery_ids |
|---|---|
| eXSYS Pte Ltd (Singapore) | `[14]` |
| DHL Supply Chain PTE LTD | `[14]` |
| Thales/Gemalto | `[14]` |
| MPA Singapore | `[14]` |
| Marlin Booking | `[9, 10, 11]` (Nextdev + Wild Card + IDByte) |
| PT. Sat Nusapersada Tbk | `[16]` (Digital Transformation Leadership) |

### Risks

| # | Risk | Mitigation |
|---|---|---|
| W1 | Storage paths broken on some galleries | Hydration falls back to placeholder URL when `Storage::exists()` fails |
| W2 | Gallery deleted but ID still in `gallery_ids` | Filter out missing IDs gracefully |
| W3 | Thumbnail strip too tall on mobile | Max-height + horizontal scroll if >3 thumbnails |
| W4 | Permission: gallery items public? | All gallery items already public per existing `/api/galleries/*` routes |

---

## 9. Phase 12 — Performance Hardening (Image Optimization + API Caching)

**Effort:** 2 days
**Dependencies:** Phases 1-11 (post-launch hardening, runs Day 9-10)
**Severity:** HIGH (production currently slow per operator report)

### Diagnosis (verified 2026-05-04 from production)

**Storage footprint (750+ MB of unoptimized images):**

| Folder | Size | Note |
|---|---|---|
| `blog-images/` | **377 MB** | 52 articles × ~7MB avg per article (hero + body) |
| `gallery/` | 140 MB | Original-resolution screenshots, no variants |
| `images/` | 81 MB | About settings, site logo, misc |
| `linkedin-carousel/` | 77 MB | Per-slide PNGs (1080×1350) |
| `projects/` | 67 MB | Project hero images |
| **TOTAL** | **~750 MB** | All served as-is, no compression, no WebP |

**Single-file outliers:**

```
28 MB  gallery/items/1777871190_item_20_3.jpg     ← lightbox + 80x80 thumb same source
23 MB  gallery/items/1777870135_item_18_0.png
15 MB  gallery/items/1777871190_item_20_4.jpg
 5.4 MB gallery/items/1777871190_item_20_0.png
```

**API caching gaps:**

```
GET /api/posts          → cache-control: no-cache, private  ❌
GET /api/projects       → likely same (TBD verify)         ❌
GET /api/awards         → likely same                      ❌
GET /api/galleries      → likely same                      ❌
GET /api/settings/about → likely same                      ❌
GET /api/settings/site  → likely same                      ❌
```

Every page navigation triggers fresh DB hit + unindexed query in some cases. With Cloudflare in front, ZERO edge caching benefit because origin says no-cache.

**Service worker:** Currently caches hero videos + media (cache-first), but **not API responses**. Per CLAUDE.md: "Pre-caches hero videos on install, cache-first strategy for all media."

### Strategic Fix Areas (5 sub-phases)

#### 12.A — Image Variants at Upload Time (NEW UPLOADS)

**Goal:** Every uploaded image auto-generates 3 size variants on save.

**Files:**

- `backend/app/Services/ImageVariantService.php` — NEW. Wraps Intervention Image to generate variants.
- `backend/app/Http/Controllers/Api/GalleryController.php` — call ImageVariantService after thumbnail upload
- `backend/app/Http/Controllers/Api/GalleryItemController.php` — call after each item upload
- `backend/app/Http/Controllers/Api/PostController.php` — call after blog hero upload
- `backend/app/Http/Controllers/Api/ProjectController.php` — call after project hero upload
- `backend/app/Services/ImageGenerationService.php` — call after webhook downloads GeminiGen image

**Variant Strategy:**

| Variant | Size | Format | Quality | Use Case |
|---|---|---|---|---|
| `original` | full res | source format | unchanged | Lightbox, social meta |
| `medium` | max 1200px wide | WebP | 85 | Card images, blog body |
| `thumb` | max 400px wide | WebP | 80 | Grid thumbnails, navigation |

**Filename pattern:**

```
gallery/items/1777871190_item_20_3.jpg              ← original
gallery/items/1777871190_item_20_3-medium.webp      ← variant
gallery/items/1777871190_item_20_3-thumb.webp       ← variant
```

**ImageVariantService outline:**

```php
public function generate(string $sourcePath, array $variants = ['medium', 'thumb']): array
{
    $absolute = Storage::disk('public')->path($sourcePath);
    $img = Image::read($absolute);
    $generated = [];

    foreach ($variants as $variant) {
        [$maxW, $quality] = match ($variant) {
            'medium' => [1200, 85],
            'thumb'  => [400, 80],
        };

        $variantPath = preg_replace(
            '/\.(jpg|jpeg|png|webp|gif)$/i',
            "-{$variant}.webp",
            $sourcePath
        );

        $img->scaleDown(width: $maxW)
            ->toWebp($quality)
            ->save(Storage::disk('public')->path($variantPath));

        $generated[$variant] = $variantPath;
    }

    return $generated; // ['medium' => '...', 'thumb' => '...']
}
```

#### 12.B — Backfill Variants for Existing Images (artisan command)

**Goal:** Process the existing 750MB of images, generate variants, free up bandwidth.

**File:** `backend/app/Console/Commands/GenerateImageVariants.php`

**Usage:**

```bash
# Dry run first
php artisan images:generate-variants --dry-run

# Production (~30-60min for 750MB on VPS)
php artisan images:generate-variants
php artisan images:generate-variants --folder=blog-images
php artisan images:generate-variants --folder=gallery
```

**Logic:** Walks `storage/app/public/{blog-images,gallery,images,linkedin-carousel,projects}/`, skips files that already have `-medium.webp` + `-thumb.webp` neighbors (idempotent), generates variants for the rest. Logs progress every 20 files.

**Expected result:**

- 750MB original files preserved (lightbox/SEO meta still works)
- Additional ~250MB of variants generated (medium + thumb at WebP compression)
- Total storage: ~1GB but with massive bandwidth saving for typical browsing

#### 12.C — Frontend BaseImage Component

**Goal:** Replace raw `<img src="...">` with smart `<BaseImage>` that picks the right variant per context.

**File:** `frontend/src/components/base/BaseImage.vue` — NEW

```vue
<template>
  <picture class="base-image">
    <source
      v-if="srcWebp"
      :srcset="srcWebp"
      type="image/webp"
    />
    <img
      :src="srcFallback"
      :alt="alt"
      :loading="lazy ? 'lazy' : 'eager'"
      :decoding="lazy ? 'async' : 'auto'"
      :width="width"
      :height="height"
      class="base-image__img"
    />
  </picture>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  src: { type: String, required: true },          // original URL
  alt: { type: String, required: true },
  variant: { type: String, default: 'medium' },   // 'thumb' | 'medium' | 'original'
  lazy: { type: Boolean, default: true },
  width: Number,
  height: Number,
})

const srcWebp = computed(() => {
  if (props.variant === 'original') return null
  return props.src.replace(/\.(jpg|jpeg|png|webp|gif)$/i, `-${props.variant}.webp`)
})

const srcFallback = computed(() => props.src)
</script>
```

**Migration:** grep & replace `<img src="...storage/...">` patterns in:
- `frontend/src/components/blog/BlogCard.vue`
- `frontend/src/components/projects/ProjectCard.vue`
- `frontend/src/components/awards/AwardCard.vue`
- `frontend/src/views/Gallery.vue`
- `frontend/src/views/BlogDetail.vue`
- `frontend/src/views/Home.vue` (post-Phase 4)
- `frontend/src/components/home/MagazineGrid.vue` (Phase 4 — use thumb variant)
- `frontend/src/components/home/ReceiptsBento.vue` (Phase 5 — use medium variant)
- `frontend/src/components/home/AwardsNamedCards.vue` (Phase 6 — use thumb variant)

**Rule of thumb:**
- Lightbox / hero / OG meta → `variant="original"`
- Blog detail body, project detail hero → `variant="medium"`
- All grid thumbnails, carousels, list views → `variant="thumb"`

#### 12.D — API Response Caching (Laravel + HTTP)

**Goal:** Stop hitting DB for every navigation; let Cloudflare edge-cache where safe.

**Strategy table:**

| Endpoint | Volatility | Cache strategy | TTL |
|---|---|---|---|
| `/api/posts` (list) | Daily | `Cache::remember()` + HTTP `public, max-age=300, s-maxage=600, stale-while-revalidate=1200` | 5min app + 10min CDN |
| `/api/posts/{slug}` | Per-edit | `Cache::remember()` keyed by slug, invalidate on Post saved event | 30min app |
| `/api/projects` (list) | Weekly | Same | 30min app + 1hr CDN |
| `/api/awards` | Monthly | Same | 1hr app + 4hr CDN |
| `/api/galleries` (public) | Weekly | Same | 30min app + 1hr CDN |
| `/api/settings/about` | Rarely | Same | 1hr app + 4hr CDN |
| `/api/settings/site` | Rarely | Same | 1hr app + 4hr CDN |
| `/api/admin/*` | Always live | NO caching | n/a |
| `/api/automation/*` | Always live | NO caching | n/a |

**Files:**

- `backend/app/Http/Middleware/CacheResponse.php` — NEW middleware that:
  1. Wraps controller action in `Cache::remember()` (key = URL + query string + locale)
  2. Adds HTTP `Cache-Control` headers per endpoint config
  3. Skips for authenticated requests (admin context)
- `backend/app/Providers/EventServiceProvider.php` — bind Model `saved`/`deleted` events to `Cache::forget()` for relevant keys
- `backend/config/cache.php` — verify `default => 'database'` is enough OR switch to Redis if not yet (CLAUDE.md mentions Redis available)
- `backend/routes/api.php` — apply middleware to listed read endpoints

**Implementation outline:**

```php
// CacheResponse middleware
public function handle(Request $request, Closure $next, string $ttl = '300')
{
    if ($request->user() || !$request->isMethod('GET')) {
        return $next($request);
    }
    $key = 'api-cache:' . sha1($request->fullUrl() . '|' . app()->getLocale());
    $ttlInt = (int) $ttl;

    $response = Cache::remember($key, $ttlInt, fn () => $next($request));
    return $response
        ->header('Cache-Control', "public, max-age={$ttlInt}, s-maxage=" . ($ttlInt * 2) . ', stale-while-revalidate=' . ($ttlInt * 4))
        ->header('X-Cache-TTL', $ttlInt);
}
```

**Route example:**

```php
Route::get('/posts', [PostController::class, 'index'])->middleware('cache.response:300');
Route::get('/projects', [ProjectController::class, 'index'])->middleware('cache.response:1800');
Route::get('/awards', [AwardController::class, 'index'])->middleware('cache.response:3600');
Route::get('/settings/site', [SettingsController::class, 'site'])->middleware('cache.response:3600');
Route::get('/settings/about', [SettingsController::class, 'about'])->middleware('cache.response:3600');
```

**Cache invalidation hooks:**

```php
// EventServiceProvider boot()
Post::saved(fn () => Cache::flush()); // overkill for now; later use tagged cache
Project::saved(fn () => Cache::flush());
Award::saved(fn () => Cache::flush());
// etc
```

**Note:** With Cloudflare in front, the `s-maxage` header tells Cloudflare to cache at edge (very fast for repeat visitors anywhere in the world). With `stale-while-revalidate=1200`, a stale response can serve while fresh fetch happens in background.

#### 12.E — Service Worker Cache-First for API + Assets

**File:** `frontend/public/sw.js` — extend existing cache-first strategy

**Add to runtime cache list:**

```javascript
const RUNTIME_CACHES = [
  // Existing: media (videos, images)
  // NEW:
  /\/api\/settings\/(site|about)$/,         // 1hr cache, stale-while-revalidate
  /\/api\/posts\?/,                          // network-first, 5min cache fallback
  /\/api\/projects\?/,                       // network-first, 30min cache fallback
  /\/api\/awards/,                           // 1hr cache
  /\/storage\/.*-(medium|thumb)\.webp$/,    // forever cache (hashed paths)
];
```

**Strategies:**

- `/api/settings/*`: stale-while-revalidate (always show cached, fetch fresh in background)
- `/api/posts` `/api/projects`: network-first (try fresh, fall back to cache if offline)
- `/storage/*-{medium,thumb}.webp`: cache-first forever (hash in path)

**Versioning:** Bump `CACHE_VERSION` constant in sw.js on each deploy so old caches invalidate.

### Acceptance Criteria

- [ ] After Phase 12.A: every newly uploaded gallery/blog/project image has `-medium.webp` + `-thumb.webp` neighbor files
- [ ] After Phase 12.B: existing 750MB inventory has variants generated; idempotent re-run skips done files
- [ ] BaseImage component renders `<picture>` with WebP source + JPG fallback
- [ ] All grid/list contexts use `variant="thumb"` (verified via grep)
- [ ] Lighthouse mobile **Network Payload** drops by ≥40% on /, /blog, /projects, /awards
- [ ] `/api/posts` response now has `Cache-Control: public, max-age=300, s-maxage=600, stale-while-revalidate=1200`
- [ ] DB query log shows `<10 queries per /` page load (down from current N+50+ likely)
- [ ] Service worker serves cached `/api/settings/*` while user is offline (manual test)
- [ ] Cache invalidation: editing a Post via admin clears the cached `/api/posts` immediately
- [ ] Lighthouse mobile Performance ≥ 85 (was target in Phase 8, now reinforced)
- [ ] Lighthouse mobile LCP ≤ 2.5s (already target, now achievable with WebP thumbs)

### Tests

```php
// backend/tests/Feature/ImageVariantServiceTest.php
test('variants generated on gallery upload')
test('variants generated on blog hero upload')
test('artisan images:generate-variants is idempotent')
test('artisan images:generate-variants --dry-run touches nothing')

// backend/tests/Feature/ApiCacheMiddlewareTest.php
test('GET /api/posts returns Cache-Control header with public + max-age')
test('cached response served on second hit (no DB query)')
test('Post::saved invalidates cache')
test('authenticated request bypasses cache')
```

```javascript
// frontend/cypress/e2e/image-variants.cy.js
test('blog list serves thumb variant')
test('blog detail body serves medium variant')
test('lightbox opens original variant')
test('webp source preferred when supported')
```

### Risks

| # | Risk | Mitigation |
|---|---|---|
| P1 | Variant generation crashes mid-batch on huge images | Increase `memory_limit` to 512M for the artisan command via `--memory=512M` env override; process in batches of 20 |
| P2 | WebP not supported on old browsers | `<picture>` element + JPG fallback handles automatically |
| P3 | Cache invalidation too aggressive (flushes everything on every Post save) | Phase 12.D iter 2: switch to tagged cache (`Cache::tags(['posts'])->flush()`) instead of `Cache::flush()` |
| P4 | Service worker caches old build assets | Bump `CACHE_VERSION` on every deploy via build hook |
| P5 | Variant URLs don't exist for legacy images until backfill completes | BaseImage component falls back to original on 404 (`<img onerror>` swap) |
| P6 | Storage size goes UP after variants (1GB total) | Acceptable trade — bandwidth saving per request >> storage cost; 750MB already on disk |
| P7 | Cloudflare edge cache stale on hot content | `stale-while-revalidate` + `Cache::forget()` on save event handles |

### Rollback

- 12.A: revert controller hooks; service still callable manually
- 12.B: deletion of `-medium.webp` + `-thumb.webp` files via `find ... -name "*-medium.webp" -delete`
- 12.C: revert BaseImage usage (original `<img>` still works)
- 12.D: remove middleware from routes
- 12.E: bump SW CACHE_VERSION to force cache flush

### Operator Action Required

After Phase 12.B backfill completes (~30-60min on VPS):
- [ ] Manual smoke test on / and /blog and /gallery
- [ ] Run Lighthouse on production, capture before/after numbers in this plan
- [ ] If perf gains insufficient, consider Cloudflare Image Resizing/Polish (separate scope, paid feature)

---

## 10. Open Items to Confirm Before Phase 1

| # | Item | Default | Override Path |
|---|---|---|---|
| O1 | Hero video copy: keep "operators who need shipped, not pitched"? | Yes | Edit Phase 2 spec |
| O2 | Inline stats labels: "GLOBAL AWARDS" or "INTERNATIONAL AWARDS"? | "GLOBAL" | Edit StatItem props |
| O3 | Tab order in WhatISolve: Vibe → Agents → Video, or Agents → Vibe → Video? | Vibe first (alphabetical) | Edit `whatISolve.js` |
| O4 | Magazine "Powered by my own AI Content Engine" badge — visible always or hover-only? | Always (more proof) | Edit MagazineGrid CSS |
| O5 | Awards lead card animation: subtle gold pulse OR static with "NEWEST" ribbon? | Both (pulse + ribbon) | Edit AwardsNamedCards |
| O6 | Testimonials carousel auto-rotate or static grid 2x2? | Auto-rotate (single quote intimate) | Switch to grid variant |
| O7 | CTA section: include video testimonial when available? | No, defer to Phase 10 future | Add as Phase 7 stretch |

---

## 9. Done When

- All 9 phases acceptance criteria checked ✅
- Lighthouse production: Perf ≥85, A11y ≥95
- 6 browser × 2 form factor matrix: zero blockers
- 4 LinkedIn recommendations live in carousel (or 3 minimum)
- Hero video shipped (not placeholder)
- CLAUDE.md updated
- WhatsApp + Calendly CTAs producing GA4 events
- Old homepage components fully deleted
- `git log` shows clean commit history per phase

---

**Plan version:** 1.0
**Next step:** User approves → Phase 1 starts via direct implementation OR `/gaspol-execute` orchestration.
