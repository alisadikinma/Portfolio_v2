# Portfolio V2 - MASTER WIREFRAMES

**Version:** 2.1 (Updated)
**Framework:** Vue 3 + Tailwind CSS
**Design System Reference:** DESIGN_SYSTEM.md
**Total Pages:** 16 (10 Public + 6 Admin)
**Date:** October 3, 2025

---

## Table of Contents

### PUBLIC PAGES (10)
1. [Homepage](#1-homepage)
2. [About](#2-about)
3. [Projects List](#3-projects-list)
4. [Project Detail](#4-project-detail)
5. [Blog List](#5-blog-list)
6. [Blog Detail](#6-blog-detail)
7. [Contact](#7-contact)
8. [Awards Page](#8-awards-page-new)
9. [Gallery Page](#9-gallery-page-new)
10. [Testimonials (Integrated)](#testimonials-section-integrated)

### ADMIN PAGES (6)
11. [Dashboard](#10-admin-dashboard)
12. [Projects Management](#11-projects-management)
13. [Project Editor](#12-project-editor)
14. [Blog Management](#13-blog-management)
15. [Blog Editor](#14-blog-editor)
16. [Settings](#15-settings)
17. [Awards Management](#16-admin-awards-management-new)
18. [Testimonials Management](#17-admin-testimonials-management-new)
19. [Gallery Management](#18-admin-gallery-management-new)

### ADDITIONAL FEATURES
- [Common UI Patterns](#common-ui-patterns-across-pages)
- [Responsive Strategy](#responsive-breakpoint-strategy)
- [Accessibility Considerations](#accessibility-considerations)
- [Component Reusability Matrix](#component-reusability-matrix)
- [Performance Optimization Notes](#performance-optimization-notes)
- [Design System Component Mapping](#design-system-component-mapping)
- [Color Application by Page Type](#color-application-by-page-type)
- [Interaction States Summary](#interaction-states-summary)
- [Implementation Priority](#implementation-priority)

---

## PUBLIC PAGES (10)

### 1. Homepage

**Purpose**: Landing page showcasing key work and converting visitors to explore portfolio.

**Sections**:
- Hero with gradient background, display heading, subheading, dual CTAs (View Projects + Contact)
- Featured Projects grid (3 cards with thumbnails, titles, tech badges)
- Skills showcase (badge grid or icon cards with categories)
- **Awards & Recognition section (3-4 featured awards)** ← NEW
- **Testimonials carousel (5-7 featured testimonials)** ← NEW
- Recent Blog Posts (2-3 cards with preview)
- Footer CTA section with contact prompt

**Components**: Hero gradient section, card-project (3x), skill-badge grid, **awards-grid (3-4x)**, **testimonial-carousel**, card-blog (2-3x), button-primary, button-secondary, footer

**Responsive**: Single column mobile stacking, 2-column tablet for projects/blog, 3-column desktop for projects, hero 2-column (text + image) on desktop, awards 2-col tablet/3-col desktop, testimonials carousel 1-2-3 cards

**Updated Homepage Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Navigation Bar                      [EN/ID] 🌙          Theme Toggle│
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                         HERO SECTION                        │   │
│  │                                                             │   │
│  │         Display Heading                    [Profile Photo]  │   │
│  │         Subheading text                                     │   │
│  │         [View Projects] [Contact Me]                        │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  FEATURED PROJECTS                                                  │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                          │
│  │  Image   │  │  Image   │  │  Image   │                          │
│  │  Title   │  │  Title   │  │  Title   │                          │
│  │  [Tags]  │  │  [Tags]  │  │  [Tags]  │                          │
│  └──────────┘  └──────────┘  └──────────┘                          │
│                                                                     │
│  SKILLS                                                             │
│  [Badge] [Badge] [Badge] [Badge] [Badge] [Badge]                   │
│                                                                     │
│  AWARDS & RECOGNITION ← NEW                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │  🏆          │  │  🎖️          │  │  🏅          │              │
│  │  Award Name  │  │  Cert Name   │  │  Honor Name  │              │
│  │  Org Name    │  │  Institute   │  │  Foundation  │              │
│  │  Jan 2024    │  │  Mar 2024    │  │  Jun 2024    │              │
│  └──────────────┘  └──────────────┘  └──────────────┘              │
│                    [View All Awards →]                              │
│                                                                     │
│  WHAT CLIENTS SAY ← NEW                                             │
│  ◄  ┌───────────┐    ┌───────────┐    ┌───────────┐  ►            │
│     │ "Quote    │    │ "Quote    │    │ "Quote    │                │
│     │  text..." │    │  text..." │    │  text..." │                │
│     │ ★★★★★     │    │ ★★★★★     │    │ ★★★★☆     │                │
│     │ Author    │    │ Author    │    │ Author    │                │
│     └───────────┘    └───────────┘    └───────────┘                │
│                          ● ○ ○ ○                                   │
│                                                                     │
│  RECENT BLOG POSTS                                                  │
│  ┌──────────────────────┐  ┌──────────────────────┐                │
│  │  Blog Card 1         │  │  Blog Card 2         │                │
│  └──────────────────────┘  └──────────────────────┘                │
│                                                                     │
│  FOOTER                                                             │
└─────────────────────────────────────────────────────────────────────┘
```

---

### 2. About

**Purpose**: Tell personal story, showcase expertise, build credibility through experience and skills.

**Sections**:
- Page header with gradient text title
- Bio section (2-column: text + professional photo)
- Timeline/Experience section (vertical timeline with job cards)
- Skills grid organized by categories (Frontend, Backend, Tools)
- Education/Certifications section (card layout)
- Personal interests/hobbies (optional footer section)

**Components**: heading-gradient, card-timeline, skill-badge groups, card-certification, avatar-large

**Responsive**: Stack to single column on mobile, 2-column bio on tablet+, skills grid 2-col mobile, 3-col tablet, 4-col desktop

---

### 3. Projects List

**Purpose**: Portfolio showcase with filtering and search capabilities.

**Sections**:
- Page header with title and description
- Filter bar (category tags, search input, sort dropdown)
- Projects grid (card-based with hover effects)
- Pagination controls at bottom
- Stats summary (total projects, featured count)

**Components**: input-search, dropdown-filter, badge-filter (multi), card-project (grid), pagination, stat-badge

**Responsive**: 1-col mobile, 2-col tablet, 3-col desktop grid, filter bar stacks on mobile

---

### 4. Project Detail

**Purpose**: In-depth case study for individual project with media, description, and technical details.

**Sections**:
- Hero with large project image/video and title
- Project metadata (date, category, tech stack badges, live/github links)
- Problem/Solution sections (2-column alternating layout)
- Screenshots gallery (grid with lightbox)
- Technologies used (icon grid with labels)
- Related projects carousel (3-4 cards)
- **Related testimonials (if applicable)** ← NEW
- CTA to view more projects or contact

**Components**: hero-image, badge-tech (multi), button-link (external), card-content, gallery-grid, carousel-projects, **testimonial-card**

**Responsive**: Hero image full-width all breakpoints, 2-col sections stack on mobile, gallery 1-col mobile, 2-col tablet, 3-col desktop

---

### 5. Blog List

**Purpose**: Article listing with search, filtering by tags/categories, and featured posts.

**Sections**:
- Page header with title
- Search and filter bar (search input, category dropdown, tag badges)
- Featured post card (larger, top position)
- Blog posts grid (cards with thumbnail, title, excerpt, meta)
- Sidebar (optional desktop): categories list, popular posts, tag cloud
- Pagination

**Components**: input-search, dropdown-category, badge-tag (multi), card-blog-featured, card-blog (grid), sidebar-widget, pagination

**Responsive**: Featured post full-width all breakpoints, blog cards 1-col mobile, 2-col desktop, sidebar below content on mobile

---

### 6. Blog Detail

**Purpose**: Full article with rich content, code blocks, images, and engagement features.

**Sections**:
- Article header (title, date, author, read time, category badges)
- Featured image (wide aspect ratio)
- Article content (typography system, code blocks, images, lists)
- Table of contents (sticky sidebar desktop, collapsible mobile)
- Author bio card at bottom
- Related posts section (3-4 cards)
- Comments/discussion section (optional)
- Social share buttons (sticky or fixed)

**Components**: heading-1, body-large (article text), code-block-syntax, image-caption, card-author, card-blog-related, button-social-share

**Responsive**: TOC sidebar on desktop (sticky), collapsible accordion on mobile, max-w-3xl content width, full-width code blocks with horizontal scroll

---

### 7. Contact

**Purpose**: Contact form with validation, social links, and alternative contact methods.

**Sections**:
- Page header with title and brief description
- Contact form (name, email, subject, message fields)
- Form validation with inline errors
- Success/error toast notifications
- Contact information sidebar (email, phone, location)
- **Social media links section (prominent, with icons and hover effects)** ← UPDATED
- Optional: Google Maps embed or illustration

**ASCII Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Navigation Bar                    [EN/ID] 🌙              Menu ☰   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│                          GET IN TOUCH                               │
│              Have a project in mind? Let's talk!                    │
│                                                                     │
│  ┌─────────────────────────────┐  ┌────────────────────────────┐  │
│  │ CONTACT FORM                │  │ CONTACT INFORMATION        │  │
│  │                             │  │                            │  │
│  │ Name                        │  │ 📧 Email                   │  │
│  │ [________________]          │  │ hello@portfolio.com        │  │
│  │                             │  │                            │  │
│  │ Email                       │  │ 📱 Phone                   │  │
│  │ [________________]          │  │ +62 812 3456 7890          │  │
│  │                             │  │                            │  │
│  │ Subject                     │  │ 📍 Location                │  │
│  │ [________________]          │  │ Batam, Indonesia           │  │
│  │                             │  │                            │  │
│  │ Message                     │  │ ⏰ Response Time           │  │
│  │ [________________]          │  │ Within 24 hours            │  │
│  │ [________________]          │  │                            │  │
│  │ [________________]          │  │ ──────────────────────     │  │
│  │ [________________]          │  │                            │  │
│  │                             │  │ CONNECT WITH ME            │  │
│  │ [ Send Message ]            │  │                            │  │
│  │                             │  │ ┌───┐ ┌───┐ ┌───┐ ┌───┐   │  │
│  └─────────────────────────────┘  │ │ f │ │ 𝕏 │ │ in│ │ GH│   │  │
│                                    │ └───┘ └───┘ └───┘ └───┘   │  │
│                                    │ ┌───┐ ┌───┐ ┌───┐ ┌───┐   │  │
│                                    │ │ IG│ │ YT│ │ BE│ │ DR│   │  │
│                                    │ └───┘ └───┘ └───┘ └───┘   │  │
│                                    │                            │  │
│                                    └────────────────────────────┘  │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                      [Optional Map/Illustration]               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│  Footer                                      [Language] [Theme]     │
└─────────────────────────────────────────────────────────────────────┘
```

**ASCII Wireframe (Mobile):**

```
┌──────────────────────────┐
│  ☰  Portfolio    🌙 EN  │
├──────────────────────────┤
│                          │
│     GET IN TOUCH         │
│  Have a project in mind? │
│                          │
│  CONTACT FORM            │
│  ┌────────────────────┐  │
│  │ Name               │  │
│  │ [_______________]  │  │
│  │                    │  │
│  │ Email              │  │
│  │ [_______________]  │  │
│  │                    │  │
│  │ Subject            │  │
│  │ [_______________]  │  │
│  │                    │  │
│  │ Message            │  │
│  │ [_______________]  │  │
│  │ [_______________]  │  │
│  │ [_______________]  │  │
│  │                    │  │
│  │  [ Send Message ]  │  │
│  └────────────────────┘  │
│                          │
│  CONTACT INFO            │
│  ┌────────────────────┐  │
│  │ 📧 Email           │  │
│  │ hello@example.com  │  │
│  │                    │  │
│  │ 📱 Phone           │  │
│  │ +62 812 3456 7890  │  │
│  │                    │  │
│  │ 📍 Location        │  │
│  │ Batam, Indonesia   │  │
│  └────────────────────┘  │
│                          │
│  CONNECT WITH ME         │
│  ┌──┐ ┌──┐ ┌──┐ ┌──┐    │
│  │f │ │𝕏 │ │in│ │GH│    │
│  └──┘ └──┘ └──┘ └──┘    │
│  ┌──┐ ┌──┐ ┌──┐ ┌──┐    │
│  │IG│ │YT│ │BE│ │DR│    │
│  └──┘ └──┘ └──┘ └──┘    │
│                          │
├──────────────────────────┤
│  Footer                  │
└──────────────────────────┘
```

**Components**: 
- `PageHeader`
- `ContactForm`
  - `InputText` (name, email, subject)
  - `Textarea` (message)
  - `ButtonPrimary` (submit)
  - `ValidationError`
- `ContactInfoSidebar`
  - Contact details list
  - **`SocialMediaGrid`** ← NEW
    - `SocialMediaButton` × N (8 social platforms)
- `ToastNotification`
- Optional: `MapEmbed` or illustration

**Social Media Platforms:**
1. Facebook (facebook.com/username)
2. Twitter/X (twitter.com/username)
3. LinkedIn (linkedin.com/in/username)
4. GitHub (github.com/username)
5. Instagram (instagram.com/username)
6. YouTube (youtube.com/@username)
7. Behance (behance.net/username)
8. Dribbble (dribbble.com/username)

**SocialMediaButton Component:**

```vue
<template>
  <a
    :href="url"
    target="_blank"
    rel="noopener noreferrer"
    :class="[
      'flex items-center justify-center',
      'w-12 h-12 rounded-lg',
      'border-2 border-gray-200 dark:border-gray-700',
      'bg-white dark:bg-gray-800',
      'hover:border-primary-500 dark:hover:border-primary-400',
      'hover:bg-primary-50 dark:hover:bg-primary-900/20',
      'hover:-translate-y-1 hover:shadow-lg',
      'transition-all duration-200',
      'focus:outline-none focus:ring-2 focus:ring-primary-500'
    ]"
    :aria-label="`Visit ${platform} profile`"
  >
    <component :is="icon" class="w-6 h-6 text-gray-600 dark:text-gray-400" />
  </a>
</template>
```

**SocialMediaGrid Layout:**

```vue
<template>
  <div class="mt-6">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">
      CONNECT WITH ME
    </h3>
    <div class="grid grid-cols-4 gap-3">
      <SocialMediaButton
        v-for="social in socialLinks"
        :key="social.platform"
        :platform="social.platform"
        :url="social.url"
        :icon="social.icon"
      />
    </div>
  </div>
</template>
```

**Responsive**: 
- Form max-w-2xl centered
- Sidebar stacks below form on mobile
- Full-width inputs on mobile
- Form grid 2-col for name/email on desktop (>= 768px)
- Social media grid: 4 columns all breakpoints
- Social icons scale slightly larger on tablet/desktop

**Interactions:**
- Form validation: Real-time on blur, display errors inline
- Submit: Show loading state, disable button during submission
- Success: Toast notification + form reset + scroll to top
- Error: Toast notification + highlight error fields
- Social buttons: Hover lift effect + shadow
- Social buttons: Open in new tab (target="_blank")

**States:**
- Form idle: Normal borders
- Form focus: Primary border + ring
- Form error: Error border + message below field
- Form submitting: Loading spinner on button, disabled inputs
- Form success: Green checkmark toast + reset
- Social button hover: Lift -1px + primary border + shadow-lg
- Social button focus: Ring-2 primary

**Accessibility:**
- Form labels associated with inputs
- Error messages announced to screen readers
- Required fields indicated with asterisk
- Social buttons have descriptive aria-labels
- Focus management: Tab through form then social buttons
- Success/error toasts have role="alert"

---

### 8. Awards Page (NEW)

**Purpose:** Comprehensive showcase of all awards, certifications, and recognitions with filtering and search.

**Key Sections:**
- Page header with gradient title
- Filter bar (category pills, search input)
- Awards grid (all awards, paginated or infinite scroll)
- Stats summary (total count, categories)

**ASCII Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Navigation Bar                      [EN/ID] 🌙          Theme Toggle│
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│                     AWARDS & RECOGNITION                            │
│           A collection of achievements and milestones               │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │  [ All ]  [ Awards ]  [ Certifications ]  [ Honors ]          │ │
│  │                                           🔍 [Search awards...] │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  Showing 12 awards                           Sort by: [ Latest ▼ ] │
│                                                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌────────┐ │
│  │  ┌────────┐  │  │  ┌────────┐  │  │  ┌────────┐  │  │  ...   │ │
│  │  │ Trophy │  │  │  │ Medal  │  │  │  │ Badge  │  │  │        │ │
│  │  │  Icon  │  │  │  │  Icon  │  │  │  │  Icon  │  │  │        │ │
│  │  └────────┘  │  │  └────────┘  │  │  └────────┘  │  │        │ │
│  │              │  │              │  │              │  │        │ │
│  │ Award Title  │  │ Award Title  │  │ Award Title  │  │        │ │
│  │ Organization │  │ Organization │  │ Organization │  │        │ │
│  │ 📅 Jan 2024  │  │ 📅 Mar 2024  │  │ 📅 Jun 2024  │  │        │ │
│  │ [Badge]      │  │ [Badge]      │  │ [Badge]      │  │        │ │
│  │ Description  │  │ Description  │  │ Description  │  │        │ │
│  └──────────────┘  └──────────────┘  └──────────────┘  └────────┘ │
│                                                                     │
│  [Second row of cards...]                                          │
│  [Third row of cards...]                                           │
│                                                                     │
│                    [ Load More Awards ]                             │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│  Footer                                                             │
└─────────────────────────────────────────────────────────────────────┘
```

**Component Stack:**
- `PageHeader` (title with gradient text, subtitle)
- `FilterBar`
  - `CategoryPills` (All, Awards, Certifications, Honors, Recognition)
  - `SearchInput`
  - `SortDropdown`
- Stats bar (`ResultsCount` + `SortControl`)
- `AwardsGrid`
  - `AwardCard` × N (all awards, filtered)
- `LoadMoreButton` or `Pagination`
- `Footer`

**Responsive Behavior:**
- **Mobile:** Single column cards, filter pills horizontal scroll, search full-width
- **Tablet:** 2 column grid
- **Desktop:** 3-4 column grid
- Sticky filter bar on scroll (optional)

**Interactions:**
- Category filter: Click pill to filter, highlight active category
- Search: Real-time filter as user types (debounced 300ms)
- Sort: Dropdown to sort by Latest, Oldest, A-Z, Category
- Card click: Expand inline or open modal with full details
- Load More: Fetch next batch (if paginated), smooth scroll to new items
- Scroll animation: Stagger fade-in for newly loaded items

**States:**
- Loading: Skeleton grid (12 skeleton cards)
- Empty (no results): "No awards found matching your search" with clear filters button
- Error: "Unable to load awards" with retry button

---

### 9. Gallery Page (NEW)

**Purpose:** Visual showcase of work samples, screenshots, behind-the-scenes photos organized by category.

**Key Sections:**
- Page header
- Category filter bar
- Gallery grid (uniform aspect ratio)
- Lightbox modal (triggered on image click)

**ASCII Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Navigation Bar                      [EN/ID] 🌙          Theme Toggle│
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│                           GALLERY                                   │
│             Visual showcase of projects and moments                 │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ [ All ] [ UI/UX ] [ Web Dev ] [ Mobile ] [ Branding ] [ Photo ]│ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  48 images                                  View: [ ⊞ Grid ] [ ≡ ] │
│                                                                     │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐                  │
│  │ ┌─────┐ │ │ ┌─────┐ │ │ ┌─────┐ │ │ ┌─────┐ │                  │
│  │ │Image│ │ │ │Image│ │ │ │Image│ │ │ │Image│ │                  │
│  │ │     │ │ │ │     │ │ │ │     │ │ │ │     │ │                  │
│  │ └─────┘ │ │ └─────┘ │ │ └─────┘ │ │ └─────┘ │                  │
│  │  [Tag]  │ │  [Tag]  │ │  [Tag]  │ │  [Tag]  │                  │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘                  │
│                                                                     │
│  [More rows...]                                                    │
│                                                                     │
│                    [ Load More (24 more) ]                          │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│  Footer                                                             │
└─────────────────────────────────────────────────────────────────────┘
```

**Lightbox Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  [X Close]                                               3 / 48     │
│                                                                     │
│                                                                     │
│  ◄                    ┌─────────────────┐                       ►  │
│                       │                 │                           │
│                       │                 │                           │
│                       │  Full Image     │                           │
│                       │  (Centered)     │                           │
│                       │                 │                           │
│                       │                 │                           │
│                       └─────────────────┘                           │
│                                                                     │
│                     Caption text goes here                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
    (Dark backdrop with backdrop-blur)
```

**Component Stack:**
- `PageHeader`
- `FilterBar`
  - `CategoryPills` (horizontal scrollable)
  - `ViewToggle` (Grid/List - optional)
- Stats bar (`ImageCount` + `ViewToggle`)
- `GalleryGrid`
  - `GalleryItem` × N (images)
- `LoadMoreButton`
- `GalleryLightbox` (modal component)
  - Backdrop
  - Image container
  - Navigation arrows
  - Close button
  - Counter
  - Caption

**Responsive Behavior:**
- **Mobile:** 1 column, smaller gaps, swipe to open lightbox
- **Tablet:** 2 columns
- **Desktop:** 3-4 columns (user toggle between 3 and 4)
- Lightbox: Full-screen on all devices, touch gestures on mobile

**Interactions:**
- Category filter: Click to filter, active state highlighted
- Image click: Open lightbox at clicked image index
- Lightbox navigation:
  - Arrow buttons: Prev/Next image (smooth fade transition)
  - Keyboard: Left/Right arrows navigate, ESC closes
  - Touch: Swipe left/right to navigate (mobile)
  - Click backdrop: Close lightbox
- Load More: Fetch next 24 images, append to grid
- Lazy loading: Images load as they enter viewport

**States:**
- Loading: Skeleton grid items (pulse animation)
- Empty: "No images in this category" with explore all button
- Lightbox loading: Spinner while large image loads
- Error: "Unable to load gallery" with retry

**Accessibility:**
- Gallery items: Keyboard focusable, Enter to open lightbox
- Lightbox: Focus trap, keyboard navigation, ESC to close
- Images: Descriptive alt text
- Counter: Screen reader announces "Image 3 of 48"

---

### 10. Testimonials Section (Integrated)

**Purpose:** Display client testimonials as a carousel on homepage and related pages.

**Implementation:**
- Integrated into Homepage (Section 5)
- Can be added to About page (optional)
- Project Detail pages (related testimonials)

**ASCII Wireframe (Desktop - Homepage Section):**

```
┌─────────────────────────────────────────────────────────────────────┐
│                        WHAT CLIENTS SAY                             │
│                  Don't just take my word for it                     │
│                                                                     │
│  ◄                                                                ► │
│  ┌────────────────┐    ┌────────────────┐    ┌────────────────┐   │
│  │  "             │    │  "             │    │  "             │   │
│  │                │    │                │    │                │   │
│  │  Testimonial   │    │  Testimonial   │    │  Testimonial   │   │
│  │  text here.    │    │  text here.    │    │  text here.    │   │
│  │  Lorem ipsum   │    │  Lorem ipsum   │    │  Lorem ipsum   │   │
│  │  dolor sit."   │    │  dolor sit."   │    │  dolor sit."   │   │
│  │                │    │                │    │                │   │
│  │  ★★★★★         │    │  ★★★★★         │    │  ★★★★☆         │   │
│  │  ──────────    │    │  ──────────    │    │  ──────────    │   │
│  │  ┌──┐          │    │  ┌──┐          │    │  ┌──┐          │   │
│  │  │  │ John Doe │    │  │  │ Jane S.  │    │  │  │ Bob K.   │   │
│  │  └──┘ CEO      │    │  └──┘ Designer │    │  └──┘ Dev Lead │   │
│  │       Company  │    │       Studio   │    │       Tech Co. │   │
│  └────────────────┘    └────────────────┘    └────────────────┘   │
│                                                                     │
│                          ● ○ ○ ○ ○                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Component Stack:**
- `Section` container
- `SectionHeader`
- `TestimonialsCarousel`
  - Carousel wrapper
  - `TestimonialCard` × N (3 visible desktop, 1 mobile)
  - Navigation buttons (prev/next)
  - Pagination dots
- Swiper.js or custom carousel logic

**Responsive Behavior:**
- **Mobile (< 640px):** 1 card visible, swipe navigation, hide arrow buttons, show dots
- **Tablet (640-1024px):** 2 cards visible, show arrow buttons
- **Desktop (1024px+):** 3 cards visible, large arrow buttons outside container
- Auto-play: 5 second interval, pause on hover, disable on mobile

**Interactions:**
- Arrow buttons: Click to slide prev/next, smooth transition (500ms ease-out)
- Pagination dots: Click to jump to specific testimonial
- Swipe: Touch drag on mobile (momentum scrolling)
- Keyboard: Arrow keys navigate, Tab focuses controls
- Hover card: Lift slightly (optional subtle effect)
- Auto-play indicator: Subtle progress bar on active dot (optional)

**States:**
- Loading: Skeleton cards with pulse animation
- Empty: Show placeholder with message "No testimonials yet"
- Single testimonial: Hide navigation, show single centered card
- Error: Show error message

**Accessibility:**
- Carousel region: `role="region" aria-label="Client testimonials"`
- Auto-play: User can pause with controls
- Focus management: Tab through cards and controls
- Screen reader: Announce slide changes

---

## ADMIN PAGES (6)

### 11. Admin Dashboard

**Purpose**: Overview of portfolio metrics, recent activity, and quick actions.

**Sections**:
- Page header with breadcrumbs and date
- Metric cards row (4 cards: Total Projects, Published Blogs, Page Views, Messages)
- Recent activity feed (list of latest edits/publishes)
- Quick actions panel (Create Project, New Post, View Messages buttons)
- Chart/analytics section (optional: views over time line chart)
- Draft items list (unpublished projects/posts)

**Components**: breadcrumbs, card-metric (4x with icons and trend indicators), list-activity, button-primary (actions), card-draft-item, chart-line (optional)

**Responsive**: Metrics 1-col mobile, 2-col tablet, 4-col desktop, activity feed full-width, charts stack on mobile

---

### 12. Projects Management

**Purpose**: Table view of all projects with CRUD operations, search, and bulk actions.

**Sections**:
- Page header with breadcrumbs
- Action bar (search input, filter dropdown, Create New button)
- Data table (columns: Thumbnail, Title, Status, Category, Date, Actions)
- Table features: sortable columns, row selection checkboxes, pagination
- Bulk actions toolbar (appears when rows selected: Delete, Change Status)
- Empty state (when no projects)

**Components**: breadcrumbs, input-search, dropdown-filter, button-primary (create), table-data, checkbox-row, dropdown-actions (per row), pagination, empty-state

**Responsive**: Table horizontal scroll on mobile/tablet, sticky first column, action buttons collapse to dropdown menu on mobile

---

### 13. Project Editor

**Purpose**: Form to create/edit project details with image upload and rich content editing.

**Sections**:
- Page header with breadcrumbs and Save/Cancel buttons
- Form sections (organized in tabs or accordion):
  - Basic Info (title, slug, category, status dropdown)
  - Content (description rich text editor, problem/solution fields)
  - Media (thumbnail upload, gallery image uploads with drag-drop)
  - Technical Details (tech stack multi-select, links: live URL, GitHub)
  - SEO (meta title, meta description, tags)
- Preview button (opens modal showing public view)
- Auto-save indicator
- Validation errors summary panel

**Components**: breadcrumbs, button-primary (save), button-secondary (cancel), input-text, textarea-rich, file-upload-drag, multi-select-tags, modal-preview, toast-autosave, validation-summary

**Responsive**: Full-width form sections, 2-col grid for related fields on desktop, single col mobile, sticky header with save buttons

---

### 14. Blog Management

**Purpose**: Table view of all blog posts with filtering, search, and content management.

**Sections**:
- Page header with breadcrumbs
- Action bar (search, category filter, status filter, Create New Post button)
- Data table (columns: Title, Author, Category, Status, Published Date, Views, Actions)
- Table features: sortable, row selection, inline status toggle
- Bulk actions (Delete, Change Category, Publish/Unpublish)
- Pagination and items per page selector
- Empty state with illustration

**Components**: breadcrumbs, input-search, dropdown-filter (multi), button-primary, table-data, badge-status, toggle-publish, dropdown-actions, pagination, empty-state

**Responsive**: Same table responsive pattern as Projects Management, filters stack on mobile

---

### 15. Blog Editor

**Purpose**: Rich text editor for creating/editing blog posts with formatting, media, and metadata.

**Sections**:
- Page header with breadcrumbs, Save Draft, Publish buttons
- Two-column layout (content editor + sidebar)
- Main editor area:
  - Title input (large text)
  - Featured image upload
  - Rich text editor (toolbar: formatting, headings, lists, links, code blocks, images)
  - Excerpt textarea
- Sidebar panels (collapsible):
  - Publish settings (status, visibility, publish date)
  - Categories and tags (checkboxes, tag input)
  - Featured image settings
  - SEO panel (meta fields)
- Preview mode toggle (side-by-side or full-screen)
- Word count and reading time indicator
- Auto-save status

**Components**: breadcrumbs, button-primary (publish), button-secondary (draft), input-text-large, rich-text-editor-toolbar, file-upload-image, checkbox-group, input-tags, date-picker, modal-preview, indicator-autosave

**Responsive**: Sidebar collapses to accordion panels on mobile, editor full-width mobile, 70/30 split desktop (editor/sidebar)

---

### 16. Settings

**Purpose**: Configuration panel for portfolio settings, profile, and preferences.

**Sections**:
- Page header with breadcrumbs
- Tabbed interface or sidebar navigation for settings categories:
  - **Profile**: Bio, profile photo, social links (form fields)
  - **Site Settings**: Site title, tagline, logo upload, favicon
  - **Contact Info**: Email, phone, location, business hours
  - **SEO Defaults**: Default meta description, keywords, OG image
  - **Integrations**: Analytics ID, contact form settings, API keys
  - **Appearance**: Theme preferences (light/dark default), accent color picker
  - **Account**: Change password, email settings, session management
- Save button for each section (or global save)
- Success/error notifications

**Components**: breadcrumbs, tabs-navigation, input-text, textarea, file-upload, color-picker, toggle-switch, button-primary (save), toast-notification, card-section (per settings group)

**Responsive**: Tabs to vertical pills on mobile, form fields full-width mobile, 2-col grid for related fields desktop, sections stack vertically

---

### 17. Admin - Awards Management (NEW)

**Purpose:** Manage all awards with CRUD operations, search, filtering, and bulk actions.

**Key Sections:**
- Page header with breadcrumbs
- Action bar (search, filter, create button)
- Data table (awards list)
- Bulk actions toolbar (when rows selected)

**ASCII Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Dashboard > Awards                                    Admin User ▼ │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Awards Management                                                  │
│                                                                     │
│  🔍 [Search awards...]  [ Category ▼ ] [ Status ▼ ] [+ New Award] │
│                                                                     │
│  ☑ [Bulk Actions: Delete ▼]                      Showing 1-20 of 47│
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ ☑ │ Icon │ Title        │ Organization │ Category │ Date │ ⚙ │ │
│  ├───────────────────────────────────────────────────────────────┤ │
│  │ ☐ │ 🏆   │ Award Title  │ Company Inc  │ Award    │ 2024 │ ⋮ │ │
│  │ ☐ │ 🎖️   │ Certification│ Institute    │ Cert     │ 2024 │ ⋮ │ │
│  │ ☐ │ 🏅   │ Recognition  │ Association  │ Honor    │ 2023 │ ⋮ │ │
│  │ ☐ │ 📜   │ Honor Award  │ Foundation   │ Award    │ 2023 │ ⋮ │ │
│  │   │ ...  │ ...          │ ...          │ ...      │ ...  │   │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│                    ◄ 1 2 3 4 5 ►                                    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Component Stack:**
- `AdminLayout` (sidebar + main content)
- `Breadcrumbs` (Dashboard > Awards)
- `PageHeader` (title)
- `ActionBar`
  - `SearchInput`
  - `FilterDropdown` (Category)
  - `FilterDropdown` (Status)
  - `ButtonPrimary` (New Award)
- `BulkActionsToolbar` (conditional, when rows selected)
- `DataTable`
  - Table header (sortable columns)
  - Table rows (`AwardRow` × N)
  - Checkbox column
  - Actions dropdown per row
- `Pagination`

**Responsive Behavior:**
- **Mobile:** Table converts to card view, action bar stacks, filters in drawer
- **Tablet:** Horizontal scroll for table, condensed columns
- **Desktop:** Full table width, all columns visible

**Interactions:**
- Search: Real-time filter (debounced)
- Filter dropdowns: Multi-select, apply on change
- Create button: Open award editor modal
- Checkbox: Select individual rows
- Header checkbox: Select/deselect all
- Sort: Click column header to sort (toggle asc/desc)
- Actions dropdown (per row): Edit, Duplicate, Delete
- Bulk actions: Delete selected, Change status
- Pagination: Navigate pages, items per page selector

**States:**
- Loading: Skeleton table rows
- Empty: "No awards yet. Create your first award!" with CTA
- Empty search: "No awards match your search" with clear button
- Selected rows: Highlight with primary-50 background, show bulk toolbar

---

### 18. Admin - Testimonials Management (NEW)

**Purpose:** Manage client testimonials with CRUD operations, reordering, and rating filters.

**ASCII Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Dashboard > Testimonials                              Admin User ▼ │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Testimonials Management                                            │
│                                                                     │
│  🔍 [Search...]  [ Rating ▼ ] [ Status ▼ ]  [+ New Testimonial]   │
│                                                                     │
│  View: [ Table ] ( Grid )                           Showing 1-15 of 32│
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ ☐│Avatar│Author       │Title/Company │Rating│Status │Date│⚙  │ │
│  ├───────────────────────────────────────────────────────────────┤ │
│  │ ☐│ 👤  │John Doe     │CEO, Company  │★★★★★│Active│2024│⋮  │ │
│  │ ☐│ 👤  │Jane Smith   │Designer, Co  │★★★★☆│Active│2024│⋮  │ │
│  │ ☐│ 👤  │Bob Johnson  │Dev Lead, Tech│★★★★★│Draft │2023│⋮  │ │
│  │  │ ... │...          │...           │...  │...   │... │   │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│                    ◄ 1 2 3 ►                                        │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Grid View Alternative:**

```
│  View: ( Table ) [ Grid ]                                          │
│                                                                     │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐  │
│  │ ☐  "Quote  │  │ ☐  "Quote  │  │ ☐  "Quote  │  │ ☐  "Quote  │  │
│  │    text    │  │    text    │  │    text    │  │    text    │  │
│  │    here"   │  │    here"   │  │    here"   │  │    here"   │  │
│  │            │  │            │  │            │  │            │  │
│  │  ★★★★★     │  │  ★★★★☆     │  │  ★★★★★     │  │  ★★★★★     │  │
│  │  👤 John D.│  │  👤 Jane S.│  │  👤 Bob J. │  │  👤 Alice  │  │
│  │  CEO, Co   │  │  Designer  │  │  Dev Lead  │  │  Manager   │  │
│  │            │  │            │  │            │  │            │  │
│  │  [ Edit ]  │  │  [ Edit ]  │  │  [ Edit ]  │  │  [ Edit ]  │  │
│  └────────────┘  └────────────┘  └────────────┘  └────────────┘  │
```

**Component Stack:**
- `AdminLayout`
- `Breadcrumbs`
- `PageHeader`
- `ActionBar`
  - `SearchInput`
  - `FilterDropdown` (Rating: All, 5-star, 4-star, etc.)
  - `FilterDropdown` (Status)
  - `ButtonPrimary` (New Testimonial)
- `ViewToggle` (Table/Grid)
- Conditional rendering:
  - Table view: `DataTable` component
  - Grid view: `TestimonialCardGrid` component
- `Pagination`

**Responsive Behavior:**
- Mobile: Force grid view, single column
- Tablet: Grid view 2 columns, table with scroll
- Desktop: Both views available, grid 3 columns

**Interactions:**
- View toggle: Switch between table and grid instantly
- Search: Filter by author name or company
- Rating filter: Show only testimonials with specific rating
- Status filter: Active/Draft/Archived
- Drag to reorder (in grid view): Change display order
- Actions dropdown: Edit, Duplicate, Delete, Change Status
- Bulk select: Select multiple, bulk delete

---

### 19. Admin - Gallery Management (NEW)

**Purpose:** Upload, organize, and manage gallery images with bulk operations and category management.

**ASCII Wireframe (Desktop):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Dashboard > Gallery                                   Admin User ▼ │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Gallery Management                                                 │
│                                                                     │
│  🔍 [Search images...]  [ Category ▼ ]  [⬆ Upload Images]         │
│                                                                     │
│  ☑ [Bulk: Delete ▼]  Size: [○ Small • Medium ○ Large]  124 images │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ ☐    ☐    ☐    ☐    ☐    ☐    ☐    ☐    ☐    ☐    ☐    ☐   │ │
│  │ ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐  ┌─┐ │ │
│  │ │ │  │ │  │ │  │ │  │ │  │ │  │ │  │ │  │ │  │ │  │ │  │ │ │ │
│  │ │I│  │I│  │I│  │I│  │I│  │I│  │I│  │I│  │I│  │I│  │I│  │I│ │ │
│  │ │M│  │M│  │M│  │M│  │M│  │M│  │M│  │M│  │M│  │M│  │M│  │M│ │ │
│  │ │G│  │G│  │G│  │G│  │G│  │G│  │G│  │G│  │G│  │G│  │G│  │G│ │ │
│  │ └─┘  └─┘  └─┘  └─┘  └─┘  └─┘  └─┘  └─┘  └─┘  └─┘  └─┘  └─┘ │ │
│  │ UI/UX Web  Mobile Photo Brand Event ...                       │ │
│  │ Edit Edit  Edit  Edit  Edit  Edit                             │ │
│  │                                                                │ │
│  │ [More rows of thumbnails...]                                  │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│                    [ Load More (60 more) ]                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Upload Modal:**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Upload Images                                            [X Close] │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │  ┌────────────────────────────────────────────────────────┐   │ │
│  │  │         ⬆                                              │   │ │
│  │  │                                                        │   │ │
│  │  │    Click to upload or drag and drop                   │   │ │
│  │  │    PNG, JPG, WebP up to 5MB each                      │   │ │
│  │  │    You can select multiple files                      │   │ │
│  │  │                                                        │   │ │
│  │  │         [Browse Files]                                │   │ │
│  │  └────────────────────────────────────────────────────────┘   │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  Selected Files (3)                              [ Clear All ]     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ ┌──┐  project-screenshot.png  1.2 MB  ■■■■■■■■■■ 100%   [×] │ │
│  │ │  │                                                          │ │
│  │ └──┘  Uploaded successfully ✓                                │ │
│  ├───────────────────────────────────────────────────────────────┤ │
│  │ ┌──┐  design-mockup.jpg       2.4 MB  ■■■■■□□□□□ 60%    [×] │ │
│  │ │  │                                                          │ │
│  │ └──┘  Uploading...                                           │ │
│  ├───────────────────────────────────────────────────────────────┤ │
│  │ ┌──┐  mobile-app.png          800 KB  □□□□□□□□□□ 0%     [×] │ │
│  │ │  │                                                          │ │
│  │ └──┘  Waiting...                                             │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│                                           [ Close ]  [ Upload All ] │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Component Stack:**
- `AdminLayout`
- `Breadcrumbs`
- `PageHeader`
- `ActionBar`
  - `SearchInput`
  - `FilterDropdown` (Category)
  - `ButtonPrimary` (Upload Images - opens modal)
- `BulkActionsToolbar`
  - Bulk dropdown (Delete, Move to Category, Download)
- `ViewOptions`
  - Size toggle (Small/Medium/Large thumbnails)
- `GalleryGrid` (thumbnail grid)
  - `GalleryThumbnail` × N
    - Checkbox overlay
    - Image thumbnail
    - Category tag
    - Edit button
- `LoadMoreButton`
- `UploadModal` (opens on Upload button click)
  - `BulkUploadZone`
  - `FileList` with progress bars

**Responsive Behavior:**
- Mobile: 2 columns, smaller thumbnails, simplified controls
- Tablet: 4 columns
- Desktop: 6-8 columns (based on size toggle)

**Interactions:**
- Upload button: Open upload modal
- Drag-drop: Highlight drop zone on drag over, accept multiple files
- File validation: Check type and size, show errors
- Progress bars: Update in real-time during upload
- Thumbnail click: Open image editor modal
- Checkbox: Select individual images
- Select all: Header checkbox selects all visible
- Bulk actions: Delete selected, move to category, download zip
- Size toggle: Change thumbnail size (grid layout adjusts)
- Search: Filter by filename or caption
- Category filter: Show images in selected category only

**States:**
- Loading: Skeleton grid
- Uploading: Progress bars, disable interactions
- Success: Green checkmarks, success toast
- Error: Red error icons, error messages
- Empty: "No images yet" with upload CTA
- Empty search: "No images match" with clear button

---

## Common UI Patterns Across Pages

### Navigation Components
- **Public Header**: Sticky glass navbar (logo left, **LanguageSwitcher [EN/ID]**, links center/right, theme toggle, mobile hamburger)
- **Admin Sidebar**: Fixed left sidebar (collapsible, dark theme, icon + label items, active state highlight)
- **Mobile Admin**: Slide-in drawer with overlay backdrop

### Modals/Overlays
- Confirmation dialogs (delete actions)
- Preview modals (project/blog preview)
- Image lightbox (gallery viewing)
- Quick create forms (lightweight project/post creation)
- **Award editor modal** ← NEW
- **Testimonial editor modal** ← NEW
- **Image upload modal** ← NEW

### Feedback Elements
- Toast notifications (success, error, warning, info - top-right position)
- Loading states (skeleton screens for cards/tables, spinner for buttons)
- Empty states (illustrations + CTA for empty lists)
- Error pages (404, 500 with illustration and navigation)

### Form Patterns
- Inline validation (error messages below fields)
- Required field indicators (asterisk)
- Helper text (subtle gray below inputs)
- Floating labels or top-aligned labels
- Auto-save indicators for editors
- Dirty form warnings (unsaved changes)

---

## Responsive Breakpoint Strategy

**Mobile First (320px - 640px)**:
- Single column layouts
- Stacked cards and sections
- Full-width components
- Hamburger navigation
- Bottom action bars for key CTAs
- Simplified data tables (card view alternative)
- **Awards: 1 column** ← NEW
- **Testimonials: 1 card, swipe navigation** ← NEW
- **Gallery: 1 column** ← NEW

**Tablet (640px - 1024px)**:
- 2-column grids for cards
- Side-by-side form fields
- Expanded navigation (may still collapse)
- Table views with horizontal scroll
- Moderate spacing increases
- **Awards: 2 columns** ← NEW
- **Testimonials: 2 cards visible** ← NEW
- **Gallery: 2 columns** ← NEW

**Desktop (1024px+)**:
- 3-4 column grids
- Sidebar layouts (content + sidebar)
- Full horizontal navigation
- Data tables full-featured
- Hover states active
- Generous whitespace
- **Awards: 3-4 columns** ← NEW
- **Testimonials: 3 cards visible** ← NEW
- **Gallery: 3-4 columns** ← NEW

---

## Accessibility Considerations

- **Keyboard Navigation**: All interactive elements accessible via Tab, Enter, Escape
- **Focus Indicators**: Visible focus rings (4px primary color with 50% opacity)
- **ARIA Labels**: Icon buttons, navigation landmarks, form labels
- **Color Contrast**: WCAG AA compliance (4.5:1 for text, 3:1 for UI components)
- **Touch Targets**: Minimum 44x44px for mobile interactive elements
- **Screen Reader**: Semantic HTML (nav, main, aside, header, footer), skip links
- **Motion**: Respect prefers-reduced-motion for users with vestibular disorders
- **Carousel controls** (NEW): Arrow key navigation, pause controls
- **Lightbox** (NEW): ESC to close, focus trap, keyboard navigation
- **Star ratings** (NEW): Aria-label with rating value
- **Language switcher** (NEW): Aria-pressed states, clear language indication

---

## Component Reusability Matrix

| Component | Public Pages | Admin Pages | Variants |
|-----------|--------------|-------------|----------|
| Button | All | All | Primary, Secondary, Ghost, Danger |
| Card | All | Dashboard, Management | Project, Blog, Metric, Timeline, **Award**, **Testimonial**, **Gallery** |
| Input | Contact | All Admin | Text, Email, Textarea, Search |
| Badge | Projects, Blog | All Admin | Status, Tag, Tech, Category |
| Modal | - | All Admin | Confirm, Preview, Create, **Award Editor**, **Testimonial Editor**, **Upload** |
| Table | - | Management | Projects, Blog Posts, **Awards**, **Testimonials** |
| Navigation | All Public | All Admin | Public Header, Admin Sidebar |
| Toast | Contact | All Admin | Success, Error, Warning, Info |
| **Carousel** | **Homepage** | - | **Testimonials** |
| **Lightbox** | **Gallery** | - | **Image Viewer** |
| **Upload Zone** | - | **Gallery Admin** | **Drag-Drop** |
| **LanguageSwitcher** | **All Public** | - | **Icon/Text/Combined** |
| **SocialMediaButton** | **Contact** | - | **8 Platforms** |

---

## Performance Optimization Notes

- **Lazy Loading**: Below-fold images, admin route chunks, blog content, **gallery images**, **carousel slides**
- **Code Splitting**: Separate bundles for public and admin sections
- **Asset Optimization**: WebP images with JPG fallback, SVG icons, font subsetting
- **Critical CSS**: Inline critical styles for above-fold content
- **Skeleton Screens**: Show layout structure while content loads (projects grid, blog list, **awards grid**, **gallery grid**)
- **Infinite Scroll**: Optional for blog/project lists (alternative to pagination), **gallery**
- **Carousel Preloading**: Preload adjacent slides for smooth navigation
- **Lightbox Optimization**: Use thumbnail versions, preload prev/next images

---

## Design System Component Mapping

**Public Pages Primary Components**:
- hero-gradient
- card-project (with hover elevation and border highlight)
- card-blog (horizontal desktop, vertical mobile)
- skill-badge (rounded-full pills)
- **card-award** (with icon, category badge) ← NEW
- **card-testimonial** (glassmorphic, with quote mark) ← NEW
- **language-switcher** (flag icons, active state) ← NEW
- **social-media-button** (hover lift, 8 platforms) ← NEW
- gallery-grid (with lightbox modal)
- code-block-syntax (with copy button)
- footer (multi-column with social links)

**Admin Pages Primary Components**:
- card-metric (with icon, number, trend indicator)
- table-data (sortable, selectable, with action dropdown)
- rich-text-editor (toolbar-based with markdown support)
- file-upload-drag (with preview and progress)
- **bulk-image-upload** (multi-file drag-drop) ← NEW
- sidebar-nav (collapsible with active state)
- breadcrumbs (path navigation)
- dropdown-actions (edit, delete, duplicate options)

**Shared Components**:
- button (all variants)
- input-text/email/textarea
- modal (centered overlay with backdrop)
- toast-notification (slide-in from right)
- badge (colored pills for status/tags)
- pagination (centered controls)
- empty-state (illustration + message + CTA)
- loading-spinner/skeleton

---

## Color Application by Page Type

**Public Pages**:
- Primary background: white (light) / gray-950 (dark)
- Accent usage: CTAs, links, active states
- Gradient: Hero sections, featured elements
- Shadows: Subtle (shadow-sm to shadow-md)
- **Award category icons**: Primary, Accent, Secondary, Warning ← NEW
- **Testimonial stars**: Warning-500 (filled) ← NEW
- **Gallery overlays**: Black/60 ← NEW
- **Language switcher active**: Primary-500 ring, white bg ← NEW
- **Social buttons hover**: Primary-50 bg, Primary-500 border ← NEW

**Admin Pages**:
- Sidebar: gray-900/gray-950 (dark theme preferred)
- Content area: white/gray-50 (light) or gray-900 (dark)
- Metric cards: Colored icon circles (success, info, warning)
- Status badges: Semantic colors (success, warning, error)
- Table hover: gray-100 (light) / gray-800 (dark)
- **Upload zone active**: Primary-100/Primary-900 ← NEW

---

## Interaction States Summary

**Buttons**: Default → Hover (elevate -1px, shadow-md) → Active (return to 0) → Focus (ring-4) → Disabled (opacity-50)

**Cards**: Default (shadow-sm) → Hover (shadow-lg, border-primary, translateY(-2px)) → Active (shadow-md, translateY(0))

**Inputs**: Default (border-gray-300) → Focus (border-primary, ring-4) → Error (border-error, ring-error) → Success (border-success, checkmark)

**Links**: Default (primary-600, underline-offset-4) → Hover (primary-700, underline) → Active (primary-800)

**Table Rows**: Default → Hover (bg-gray-100) → Selected (border-l-4 primary, bg-primary-50)

**Carousel Arrows** (NEW): Default → Hover (bg-primary-50, border-primary-500) → Disabled (opacity-50)

**Gallery Items** (NEW): Default → Hover (scale-105, overlay opacity-100, shadow-xl)

**Upload Zone** (NEW): Default → Drag Over (border-primary-500, bg-primary-50) → Dragging (scale-102, bg-primary-100)

**Language Buttons** (NEW): Default (opacity-60) → Active (opacity-100, ring-1 primary) → Hover (bg-white)

**Social Buttons** (NEW): Default → Hover (translateY(-1px), shadow-lg, border-primary) → Focus (ring-2 primary)

---

## Implementation Priority

**Phase 1 - Foundation** (Week 1):
- Homepage, About, Contact (public)
- Dashboard (admin)
- Core components (button, input, card, navigation)
- **LanguageSwitcher component** ← NEW
- **SocialMediaButton component** ← NEW

**Phase 2 - Content Display** (Week 2):
- Projects List, Project Detail
- Blog List, Blog Detail
- Admin Projects Management
- **Awards Page** ← NEW
- **Homepage Awards Section** ← NEW

**Phase 3 - Content Management** (Week 3):
- Project Editor, Blog Editor
- Blog Management
- Settings
- **Awards Management (Admin)** ← NEW
- **Testimonials Management (Admin)** ← NEW

**Phase 4 - Media & Engagement** (Week 4):
- **Gallery Page** ← NEW
- **Gallery Management (Admin)** ← NEW
- **Testimonials Carousel (Homepage)** ← NEW
- Lightbox component
- Upload components

**Phase 5 - Polish** (Week 5):
- Loading states, error handling
- Animations and transitions
- Accessibility audit
- Performance optimization
- Cross-browser testing
- **i18n testing (EN/ID switching)** ← NEW

---

## Empty States

**Awards (No Awards):**
```
┌─────────────────────────┐
│      🏆                 │
│                         │
│   No awards yet         │
│   Start adding your     │
│   achievements          │
│                         │
│   [+ Add First Award]   │
└─────────────────────────┘
```

**Testimonials (No Testimonials):**
```
┌─────────────────────────┐
│      💬                 │
│                         │
│   No testimonials       │
│   Add client feedback   │
│   to build trust        │
│                         │
│   [+ Add Testimonial]   │
└─────────────────────────┘
```

**Gallery (No Images):**
```
┌─────────────────────────┐
│      📷                 │
│                         │
│   Gallery is empty      │
│   Upload your first     │
│   images to showcase    │
│                         │
│   [⬆ Upload Images]     │
└─────────────────────────┘
```

**Search Results (No Match):**
```
┌─────────────────────────┐
│      🔍                 │
│                         │
│   No results found      │
│   Try different         │
│   keywords              │
│                         │
│   [ Clear Search ]      │
└─────────────────────────┘
```

---

## Loading States

**Skeleton Screens:**

**Award Card Skeleton:**
```
┌──────────────────┐
│  ┌────────────┐  │
│  │ ░░░░░░░░░░ │  │ (animated pulse)
│  │ ░░░░░░░░░░ │  │
│  └────────────┘  │
│                  │
│  ░░░░░░░░░░░░    │ (title line)
│  ░░░░░░░░        │ (org line)
│  ░░░░            │ (date line)
│  ░░░░░░          │ (badge)
└──────────────────┘
```

**Testimonial Card Skeleton:**
```
┌────────────────────┐
│  ░░░░░░░░░░░░░░░░  │
│  ░░░░░░░░░░░░░░░░  │
│  ░░░░░░░░░░░░      │
│                    │
│  ┌──┐              │
│  │░░│ ░░░░░░░░░    │
│  └──┘ ░░░░░░       │
└────────────────────┘
```

**Gallery Skeleton:**
```
┌─┐ ┌─┐ ┌─┐ ┌─┐
│░│ │░│ │░│ │░│
│░│ │░│ │░│ │░│
└─┘ └─┘ └─┘ └─┘
(grid of pulsing rectangles)
```

---

## Error States

**General Error:**
```
┌─────────────────────────┐
│      ⚠️                 │
│                         │
│   Something went wrong  │
│   Unable to load data   │
│                         │
│   [ Retry ]             │
└─────────────────────────┘
```

**Upload Error:**
```
File: large-image.jpg
❌ File too large (8MB). Max size is 5MB.
[Remove]
```

**Form Validation Error:**
```
Title
[Input field with red border]
⚠️ Title is required
```

---

**End of Master Wireframes Documentation (Version 2.1 - Updated)**

Total estimated components: **47-52 reusable Vue components** (35-40 core + 12-17 new including LanguageSwitcher and SocialMediaButton)
Total pages: **16 (10 public + 6 admin)**
Design system compliance: 100%
Accessibility target: WCAG 2.1 AA
Responsive breakpoints: Mobile (320px+), Tablet (640px+), Desktop (1024px+)
**i18n Support**: English/Indonesian language switching

These comprehensive wireframes provide complete layouts for all pages across public and admin interfaces, including the new Awards, Testimonials, Gallery features, plus LanguageSwitcher and enhanced Contact page with social media grid. Each wireframe includes detailed component breakdowns, responsive behaviors, and interaction specifications ready for frontend implementation.
