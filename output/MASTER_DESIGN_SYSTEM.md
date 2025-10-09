# Portfolio V2 Design System - MASTER DOCUMENT

**Version:** 2.0 (Consolidated)
**Framework:** Vue 3 + Tailwind CSS
**Date:** October 2, 2025
**Status:** Master Reference - All Design Specifications

---

## Table of Contents

### Section 1: Core Design System
1. [Design Philosophy](#design-philosophy)
2. [Color Palette](#1-color-palette)
3. [Typography System](#2-typography-system)
4. [Spacing & Layout](#3-spacing--layout)
5. [Component Patterns](#4-component-patterns)
6. [Component Library](#5-component-library)
7. [Interactive States](#6-interactive-states)
8. [Accessibility Guidelines](#7-accessibility-guidelines)
9. [Responsive Design Strategy](#8-responsive-design-strategy)
10. [Animation & Transitions](#9-animation--transitions)
11. [Dark Mode Implementation](#10-dark-mode-implementation)
12. [Gradient Patterns](#11-gradient-patterns)
13. [Icons & Assets](#12-icons--assets)
14. [Code Syntax Highlighting](#13-code-syntax-highlighting)
15. [Form Patterns](#14-form-patterns)
16. [Performance Guidelines](#15-performance-guidelines)
17. [Implementation Checklist](#16-implementation-checklist)
18. [Tailwind Config Reference](#17-tailwind-config-reference)
19. [Vue Component Example](#18-vue-component-example)
20. [Resources & References](#19-resources--references)

### Section 2: Supplemental Components (Awards, Testimonials, Gallery)
21. [New Component Patterns](#new-component-patterns)
    - Awards & Recognition Components
    - Testimonials Components
    - Gallery Components
    - Admin-Specific Components
22. [Color Usage Summary](#color-usage-summary)
23. [Spacing Additions](#spacing-additions)
24. [Animation Enhancements](#animation-enhancements)
25. [Responsive Behavior Summary](#responsive-behavior-summary)
26. [Accessibility Checklist](#accessibility-checklist)
27. [Dark Mode Implementation (Extended)](#dark-mode-implementation-extended)
28. [Component Dependencies](#component-dependencies)
29. [Usage Guidelines](#usage-guidelines)
30. [Performance Considerations](#performance-considerations)
31. [Testing Checklist](#testing-checklist)
32. [Migration Notes](#migration-notes)

---

# SECTION 1: CORE DESIGN SYSTEM

## Design Philosophy

### Aesthetic Direction
**Modern Minimal with Purposeful Accents**

- Clean, spacious layouts with intentional white space
- Subtle animations that enhance rather than distract
- Professional polish with contemporary feel
- Glassmorphic effects for elevated surfaces
- Bold typography hierarchy
- Strategic use of gradients for visual interest

### Core Principles

1. **Clarity First** - Every element serves a clear purpose
2. **Accessible by Default** - WCAG 2.1 AA compliance minimum
3. **Mobile-First** - Design and develop for small screens first
4. **Performance Conscious** - Optimize assets and animations
5. **Consistent Patterns** - Reuse components and interactions
6. **Dark Mode Native** - Both themes are first-class citizens

### Animation Philosophy
**Subtle & Purposeful**

- Micro-interactions for user feedback (150-250ms)
- Page transitions that feel smooth (300-400ms)
- Hover states with slight elevation or color shift
- Loading states with skeleton screens
- No gratuitous animations that slow interaction

---

## 1. Color Palette

### Primary Colors
**Indigo Scale** - Professional, trustworthy, modern

```css
primary-50:  #eef2ff
primary-100: #e0e7ff
primary-200: #c7d2fe
primary-300: #a5b4fc
primary-400: #818cf8
primary-500: #6366f1  /* Main brand color */
primary-600: #4f46e5
primary-700: #4338ca
primary-800: #3730a3
primary-900: #312e81
primary-950: #1e1b4b
```

### Secondary Colors
**Violet Scale** - Complementary accent

```css
secondary-50:  #faf5ff
secondary-100: #f3e8ff
secondary-200: #e9d5ff
secondary-300: #d8b4fe
secondary-400: #c084fc
secondary-500: #a855f7  /* Main secondary */
secondary-600: #9333ea
secondary-700: #7e22ce
secondary-800: #6b21a8
secondary-900: #581c87
```

### Accent Colors
**Emerald Scale** - Success, highlights, CTAs

```css
accent-50:  #ecfdf5
accent-100: #d1fae5
accent-200: #a7f3d0
accent-300: #6ee7b7
accent-400: #34d399
accent-500: #10b981  /* Main accent */
accent-600: #059669
accent-700: #047857
accent-800: #065f46
accent-900: #064e3b
```

### Neutral Grays
**Slate Scale** - Base for text, backgrounds, borders

```css
gray-50:  #f8fafc
gray-100: #f1f5f9
gray-200: #e2e8f0
gray-300: #cbd5e1
gray-400: #94a3b8
gray-500: #64748b
gray-600: #475569
gray-700: #334155
gray-800: #1e293b
gray-900: #0f172a
gray-950: #020617
```

### Semantic Colors

**Success** (Emerald)
```css
success-light: #10b981
success-dark:  #34d399
```

**Warning** (Amber)
```css
warning-light: #f59e0b
warning-dark:  #fbbf24
warning-50:  #fffbeb
warning-100: #fef3c7
warning-500: #f59e0b
warning-900: #78350f
```

**Error** (Red)
```css
error-light: #ef4444
error-dark:  #f87171
error-50:  #fef2f2
error-100: #fee2e2
error-500: #ef4444
error-900: #7f1d1d
```

**Info** (Sky)
```css
info-light: #0ea5e9
info-dark:  #38bdf8
info-50:  #f0f9ff
info-100: #e0f2fe
info-500: #0ea5e9
info-900: #0c4a6e
```

### Theme Configurations

**Light Mode**
```javascript
{
  background: {
    primary: 'white',
    secondary: 'gray-50',
    elevated: 'white',
    muted: 'gray-100'
  },
  text: {
    primary: 'gray-900',
    secondary: 'gray-600',
    muted: 'gray-500',
    inverse: 'white'
  },
  border: {
    default: 'gray-200',
    strong: 'gray-300',
    muted: 'gray-100'
  }
}
```

**Dark Mode**
```javascript
{
  background: {
    primary: 'gray-950',
    secondary: 'gray-900',
    elevated: 'gray-800',
    muted: 'gray-800'
  },
  text: {
    primary: 'gray-50',
    secondary: 'gray-300',
    muted: 'gray-400',
    inverse: 'gray-900'
  },
  border: {
    default: 'gray-700',
    strong: 'gray-600',
    muted: 'gray-800'
  }
}
```

---

## 2. Typography System

### Font Families

**Headings**
```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
font-feature-settings: 'cv11', 'ss01'; /* Stylistic alternates */
```

**Body Text**
```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```

**Code/Monospace**
```css
font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
```

### Font Size Scale

```css
text-xs:   0.75rem    /* 12px - Captions, labels */
text-sm:   0.875rem   /* 14px - Small body text */
text-base: 1rem       /* 16px - Base body text */
text-lg:   1.125rem   /* 18px - Large body text */
text-xl:   1.25rem    /* 20px - Small headings */
text-2xl:  1.5rem     /* 24px - H4 */
text-3xl:  1.875rem   /* 30px - H3 */
text-4xl:  2.25rem    /* 36px - H2 */
text-5xl:  3rem       /* 48px - H1 */
text-6xl:  3.75rem    /* 60px - Hero headings */
text-7xl:  4.5rem     /* 72px - Display headings */
text-8xl:  6rem       /* 96px - Large display */
```

### Font Weights

```css
font-thin:       100  /* Rarely used */
font-extralight: 200  /* Special effects only */
font-light:      300  /* Subtle text */
font-normal:     400  /* Body text */
font-medium:     500  /* Emphasized text, buttons */
font-semibold:   600  /* Subheadings, strong emphasis */
font-bold:       700  /* Headings */
font-extrabold:  800  /* Hero text, important headings */
font-black:      900  /* Display text only */
```

### Line Heights

```css
leading-none:    1      /* Tight headings */
leading-tight:   1.25   /* H1-H2 */
leading-snug:    1.375  /* H3-H6 */
leading-normal:  1.5    /* Body text */
leading-relaxed: 1.625  /* Long-form content */
leading-loose:   2      /* Special layouts */
```

### Letter Spacing

```css
tracking-tighter: -0.05em  /* Large display text */
tracking-tight:   -0.025em /* Headings */
tracking-normal:   0em     /* Body text */
tracking-wide:     0.025em /* Button text, labels */
tracking-wider:    0.05em  /* All-caps text */
tracking-widest:   0.1em   /* Spaced-out headings */
```

### Typography Component Classes

**Heading Styles**
```css
.heading-display {
  @apply text-6xl md:text-7xl font-extrabold tracking-tighter leading-tight;
}

.heading-1 {
  @apply text-4xl md:text-5xl font-bold tracking-tight leading-tight;
}

.heading-2 {
  @apply text-3xl md:text-4xl font-bold tracking-tight leading-tight;
}

.heading-3 {
  @apply text-2xl md:text-3xl font-semibold leading-snug;
}

.heading-4 {
  @apply text-xl md:text-2xl font-semibold leading-snug;
}

.heading-5 {
  @apply text-lg md:text-xl font-semibold leading-snug;
}

.heading-6 {
  @apply text-base md:text-lg font-semibold leading-snug;
}
```

**Body Text Styles**
```css
.body-large {
  @apply text-lg font-normal leading-relaxed;
}

.body-base {
  @apply text-base font-normal leading-normal;
}

.body-small {
  @apply text-sm font-normal leading-normal;
}

.caption {
  @apply text-xs font-medium leading-normal text-gray-600 dark:text-gray-400;
}
```

**Special Styles**
```css
.label {
  @apply text-sm font-medium tracking-wide uppercase;
}

.button-text {
  @apply text-sm font-medium tracking-wide;
}

.link {
  @apply text-primary-600 dark:text-primary-400 hover:text-primary-700
         dark:hover:text-primary-300 underline-offset-4 hover:underline;
}
```

---

## 3. Spacing & Layout

### Spacing Scale
Based on Tailwind's 4px base unit

```css
0:    0px      /* No space */
0.5:  2px      /* Micro spacing */
1:    4px      /* Tight spacing */
1.5:  6px      /* Between 1-2 */
2:    8px      /* Small spacing */
2.5:  10px     /* Between 2-3 */
3:    12px     /* Base spacing */
4:    16px     /* Medium spacing */
5:    20px     /* Between 4-6 */
6:    24px     /* Large spacing */
8:    32px     /* Section spacing */
10:   40px     /* Large section spacing */
12:   48px     /* Major sections */
16:   64px     /* Hero sections */
20:   80px     /* Extra large spacing */
24:   96px     /* Maximum spacing */
32:   128px    /* Massive spacing */
```

### Common Spacing Patterns

**Component Padding**
```css
Tight:   p-2 sm:p-3
Default: p-4 sm:p-6
Loose:   p-6 sm:p-8
Hero:    p-8 sm:p-12 lg:p-16
```

**Stack Spacing (Vertical)**
```css
Tight:   space-y-2
Default: space-y-4
Loose:   space-y-6
Section: space-y-8
Major:   space-y-12
```

**Inline Spacing (Horizontal)**
```css
Tight:   space-x-2
Default: space-x-4
Loose:   space-x-6
```

### Container Widths

```css
max-w-sm:    24rem   /* 384px - Cards, small forms */
max-w-md:    28rem   /* 448px - Medium forms */
max-w-lg:    32rem   /* 512px - Modals */
max-w-xl:    36rem   /* 576px - Article width */
max-w-2xl:   42rem   /* 672px - Blog posts */
max-w-3xl:   48rem   /* 768px - Long content */
max-w-4xl:   56rem   /* 896px - Standard pages */
max-w-5xl:   64rem   /* 1024px - Wide content */
max-w-6xl:   72rem   /* 1152px - Dashboard */
max-w-7xl:   80rem   /* 1280px - Maximum width */
max-w-full:  100%    /* Full width */
```

**Recommended Container Usage**
- **Public Site**: max-w-7xl with px-4 sm:px-6 lg:px-8
- **Blog Posts**: max-w-3xl with px-4
- **Admin Dashboard**: max-w-full with px-4 sm:px-6 lg:px-8
- **Forms/Modals**: max-w-lg to max-w-2xl

### Grid System

**Standard Grid**
```css
/* 12-column responsive grid */
grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6
```

**Project Grid**
```css
/* Projects showcase */
grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8
```

**Dashboard Grid**
```css
/* Admin dashboard cards */
grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6
```

**Auto-fit Grid**
```css
/* Responsive auto-fit */
grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-6
```

### Breakpoints

```css
sm:  640px   /* Small tablets, large phones landscape */
md:  768px   /* Tablets */
lg:  1024px  /* Laptops, desktops */
xl:  1280px  /* Large desktops */
2xl: 1536px  /* Extra large screens */
```

**Responsive Design Strategy**
1. Design for mobile (320px-640px) first
2. Enhance for tablets (768px)
3. Optimize for desktop (1024px+)
4. Polish for large screens (1280px+)

---

## 4. Component Patterns

### Border Radius

```css
rounded-none:   0px      /* Sharp corners */
rounded-sm:     2px      /* Subtle rounding */
rounded:        4px      /* Default buttons, inputs */
rounded-md:     6px      /* Cards, panels */
rounded-lg:     8px      /* Large cards */
rounded-xl:     12px     /* Hero cards, modals */
rounded-2xl:    16px     /* Feature sections */
rounded-3xl:    24px     /* Large decorative elements */
rounded-full:   9999px   /* Pills, avatars, icon buttons */
```

**Component Radius Guide**
- Buttons: `rounded-lg`
- Input fields: `rounded-lg`
- Cards: `rounded-xl`
- Modals: `rounded-2xl`
- Badges: `rounded-full`
- Images: `rounded-lg` to `rounded-xl`

### Shadow System

```css
shadow-sm:   0 1px 2px 0 rgb(0 0 0 / 0.05)
shadow:      0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)
shadow-md:   0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)
shadow-lg:   0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)
shadow-xl:   0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)
shadow-2xl:  0 25px 50px -12px rgb(0 0 0 / 0.25)
```

**Shadow Usage**
- Subtle cards: `shadow-sm`
- Default cards: `shadow-md`
- Elevated elements: `shadow-lg`
- Modals, dropdowns: `shadow-xl`
- Hero elements: `shadow-2xl`

**Dark Mode Shadows**
```css
dark:shadow-xl dark:shadow-gray-900/50
```

### Glassmorphic Effects

**Light Glassmorphism**
```css
bg-white/70 dark:bg-gray-800/70
backdrop-blur-xl
border border-gray-200/50 dark:border-gray-700/50
```

**Strong Glassmorphism**
```css
bg-white/90 dark:bg-gray-900/90
backdrop-blur-2xl
border border-white/20 dark:border-gray-700/30
shadow-xl
```

**Navigation Bar Glass**
```css
bg-white/80 dark:bg-gray-950/80
backdrop-blur-md
border-b border-gray-200/50 dark:border-gray-800/50
```

---

## 5. Component Library

### Public Portfolio Components

#### Hero Section
**Style:** Bold, modern with gradient accents
```
Visual Elements:
- Large display typography (text-6xl to text-8xl)
- Gradient text or background accent
- Subtle animation on scroll
- Professional headshot with gradient border
- CTA buttons with hover elevation

Layout:
- Full viewport height optional
- Two-column on desktop (content + image)
- Centered on mobile
- Background: gradient or subtle pattern

States:
- Default: Shadow-md, subtle gradient
- Hover (CTA): Lift with shadow-lg, slight scale
```

#### About Section
**Style:** Clean, readable, approachable
```
Visual Elements:
- Two-column layout (text + skills/image)
- Body text: text-lg with relaxed leading
- Timeline or skills grid
- Rounded-xl cards for skill categories

Background:
- Alternate background color (gray-50/gray-900)
- Optional subtle pattern overlay

States:
- Skill cards hover: Border color shift, slight scale
```

#### Projects Grid
**Style:** Card-based with image preview
```
Card Design:
- Aspect ratio: 16:9 for project thumbnail
- Rounded-xl with shadow-md
- Overlay on hover with project details
- Tech stack badges at bottom
- Link icon appears on hover

Grid:
- 1 column mobile, 2 tablet, 3 desktop
- Gap: gap-8
- Hover: Lift with shadow-xl, scale-105

States:
- Default: shadow-md, border subtle
- Hover: shadow-xl, border-primary-500, transform
- Active: slight press effect
```

#### Skills Display
**Style:** Badge grid or category cards
```
Option A - Badges:
- Pill-shaped (rounded-full)
- Background: gray-100 dark:gray-800
- Hover: background color shift to primary

Option B - Category Cards:
- Icon + label per category
- Grid layout with gap-6
- Rounded-lg cards with subtle borders

Icon Size: 24px-32px
Text: text-sm font-medium
```

#### Contact Form
**Style:** Clean, accessible, validated
```
Form Design:
- Max width: max-w-2xl
- Floating labels or top-aligned
- Rounded-lg inputs with focus ring
- Submit button: Full width on mobile, auto on desktop

Input States:
- Default: border-gray-300, focus:border-primary-500
- Error: border-error-500, text-error-600
- Success: border-success-500, checkmark icon
- Disabled: opacity-50, cursor-not-allowed

Validation:
- Inline error messages below fields
- Error icon in input field
- Success confirmation with animation
```

#### Blog List
**Style:** Card-based with metadata
```
Card Design:
- Horizontal on desktop, vertical on mobile
- Featured image (rounded-lg)
- Title (text-2xl font-bold)
- Excerpt (text-gray-600, 2-3 lines)
- Meta: date, read time, tags
- Hover: shadow-lg, slight border color

Layout:
- Stack on mobile
- Optional featured post (larger first card)
- Pagination at bottom

Tags:
- Small badges (rounded-full, text-xs)
- Color-coded by category
```

#### Navigation
**Style:** Sticky glass navbar
```
Desktop:
- Horizontal menu
- Logo left, links center/right
- Glassmorphic background
- Active link indicator (underline or pill)

Mobile:
- Hamburger menu
- Slide-in drawer (right or left)
- Overlay backdrop
- Smooth animation (300ms)

States:
- Scrolled: backdrop-blur, border-b
- Active link: primary color, font-semibold
- Hover: text color shift, underline
```

#### Footer
**Style:** Multi-column with social links
```
Layout:
- 3-4 columns on desktop
- Stack on mobile
- Darker background (gray-900/gray-950)
- Light text

Elements:
- Social icons (hover color shift)
- Quick links
- Copyright text (text-sm, muted)
- Optional newsletter signup
```

---

### Admin CMS Components

#### Dashboard Cards
**Style:** Metric cards with icons
```
Card Design:
- Rounded-xl with shadow-sm
- Icon in colored circle (top-left)
- Large metric number (text-3xl font-bold)
- Label below (text-sm text-muted)
- Trend indicator (arrow + percentage)

Grid:
- 1 column mobile, 2 tablet, 4 desktop
- Equal height cards

States:
- Default: border subtle
- Hover: shadow-md, border color
- Clickable: cursor-pointer, active state

Color Coding:
- Success: green circle
- Warning: amber circle
- Info: blue circle
- Neutral: gray circle
```

#### Data Tables
**Style:** Clean, sortable, actionable
```
Table Design:
- Striped rows (alternate bg-gray-50)
- Sticky header on scroll
- Rounded-lg container with border
- Hover row: bg-gray-100 dark:bg-gray-800

Header:
- Font-semibold, text-xs uppercase
- Sort icons on sortable columns
- Border-b-2

Cells:
- Padding: px-6 py-4
- Text-sm
- Align right for numbers
- Truncate long text with tooltip

Actions:
- Icon buttons (edit, delete, view)
- Dropdown menu for bulk actions
- Checkbox for row selection

States:
- Hover row: background highlight
- Selected: border-l-4 border-primary-500
- Loading: skeleton placeholders
```

#### Forms
**Style:** Organized, validated, accessible
```
Form Layout:
- Single column default
- Two-column for related fields
- Fieldset grouping with borders
- Clear visual hierarchy

Input Fields:
- Label above or floating
- Placeholder text (lighter)
- Helper text below (text-sm)
- Required indicator (*)
- Icon support (left/right)

Field Types:
- Text inputs: rounded-lg, border, focus ring
- Textareas: min-height, resize-y
- Selects: Custom styled with chevron
- Checkboxes: Custom styled, rounded
- Radio: Custom styled, rounded-full
- File upload: Drag-drop area
- Rich text: Toolbar + editor area

Validation:
- Real-time or on blur
- Error state: border-error, text-error
- Success state: border-success, checkmark
- Error messages: text-sm, text-error-600

Buttons:
- Primary: full color, shadow
- Secondary: outline style
- Destructive: red color
- Submit: loading spinner state
```

#### Modals
**Style:** Centered overlay with backdrop
```
Modal Design:
- Max-w-lg to max-w-4xl depending on content
- Rounded-2xl
- Shadow-2xl
- Backdrop: bg-black/50 backdrop-blur-sm

Header:
- Border-b
- Title (text-xl font-semibold)
- Close button (top-right, rounded-full)

Body:
- Padding: p-6
- Scrollable if content exceeds viewport

Footer:
- Border-t
- Action buttons (right-aligned)
- Cancel + Confirm pattern

Animation:
- Fade in backdrop (200ms)
- Scale + fade modal (300ms)
- Smooth exit

States:
- Opening: scale-95 to scale-100, opacity 0 to 1
- Closing: reverse animation
```

#### Sidebar Navigation
**Style:** Collapsible sidebar with icons
```
Desktop Sidebar:
- Fixed left, full height
- Width: 240px-280px (expanded), 64px (collapsed)
- Dark background (gray-900)
- Logo at top
- Collapsible toggle button

Navigation Items:
- Icon + label
- Rounded-lg hover background
- Active: primary background + border-l-4
- Grouped by section (dividers)
- Badge support (notifications)

Mobile:
- Slide-in drawer from left
- Overlay backdrop
- Full width or 80% width
- Touch-friendly target sizes (min 44px)

States:
- Default: text-gray-400
- Hover: bg-gray-800, text-white
- Active: bg-primary-900, text-primary-300, border-primary-500
- Collapsed: show icons only, tooltip on hover
```

#### Notifications/Toasts
**Style:** Slide-in alerts
```
Toast Design:
- Fixed position (top-right or bottom-right)
- Rounded-xl with shadow-lg
- Icon + message + close button
- Auto-dismiss (5s default)
- Stack multiple toasts

Variants:
- Success: bg-success-50, border-success-500, icon green
- Error: bg-error-50, border-error-500, icon red
- Warning: bg-warning-50, border-warning-500, icon amber
- Info: bg-info-50, border-info-500, icon blue

Animation:
- Slide in from right (300ms)
- Progress bar at bottom (auto-dismiss)
- Fade out on dismiss

States:
- Entering: translate-x-full to translate-x-0
- Exiting: fade + slide right
```

#### Dropdowns
**Style:** Floating menu with pointer
```
Dropdown Design:
- Rounded-lg with shadow-xl
- Border subtle
- Max height with scroll
- Pointer arrow optional

Menu Items:
- Padding: px-4 py-2
- Hover: bg-gray-100 dark:bg-gray-800
- Icons + labels
- Dividers between groups
- Destructive items (red text)

Trigger:
- Button with chevron icon
- Active state when open

Animation:
- Scale + fade (150ms)
- Origin from trigger position

States:
- Hover item: background highlight
- Active item: checkmark or color
- Disabled: opacity-50, no hover
```

#### Breadcrumbs
**Style:** Path navigation with separators
```
Design:
- Text-sm
- Separator: slash or chevron icon
- Current page: font-semibold, no link
- Previous pages: links with hover

Layout:
- Horizontal flex
- Truncate middle items if too many
- Show first, last, and nearby items

States:
- Link: text-gray-600, hover:text-primary-600
- Current: text-gray-900, font-semibold
```

#### Pagination
**Style:** Centered page controls
```
Design:
- Flex row centered
- Rounded-lg buttons
- Current page highlighted
- Previous/Next buttons with arrows
- Show page numbers (1, 2, 3, ..., 10)

States:
- Default: border, hover:bg-gray-100
- Active: bg-primary-600, text-white
- Disabled: opacity-50, cursor-not-allowed

Mobile:
- Show fewer page numbers
- Previous/Next more prominent
```

#### Badge
**Style:** Small pill indicators
```
Sizes:
- Small: px-2 py-0.5 text-xs
- Medium: px-3 py-1 text-sm
- Large: px-4 py-1.5 text-base

Variants:
- Solid: colored background, white text
- Subtle: light background, dark text
- Outline: border only

Colors:
- Primary, secondary, success, warning, error, info, gray

Shape:
- Default: rounded-full
- Alternative: rounded-md
```

#### Avatar
**Style:** User profile images
```
Sizes:
- xs: 24px
- sm: 32px
- md: 40px
- lg: 48px
- xl: 64px
- 2xl: 96px

Design:
- Rounded-full
- Border optional (2px white)
- Fallback: initials on colored background
- Status indicator (dot, bottom-right)

States:
- Default: image or initials
- Hover: opacity shift or border
- Clickable: cursor-pointer, ring on hover
```

---

## 6. Interactive States

### Button States

**Primary Button**
```css
Default:
- bg-primary-600 text-white rounded-lg px-4 py-2
- shadow-sm

Hover:
- bg-primary-700
- shadow-md
- transform: translateY(-1px)

Active:
- bg-primary-800
- shadow-sm
- transform: translateY(0)

Focus:
- ring-4 ring-primary-300/50

Disabled:
- opacity-50
- cursor-not-allowed
- No hover effects

Loading:
- opacity-75
- Spinner icon
- cursor-wait
```

**Secondary Button (Outline)**
```css
Default:
- border-2 border-primary-600 text-primary-600
- bg-transparent

Hover:
- bg-primary-50 dark:bg-primary-900/20
- border-primary-700

Active:
- bg-primary-100 dark:bg-primary-900/40
```

**Ghost Button**
```css
Default:
- text-gray-700 dark:text-gray-300
- No border, no background

Hover:
- bg-gray-100 dark:bg-gray-800
```

### Input States

```css
Default:
- border border-gray-300 dark:border-gray-700
- rounded-lg px-4 py-2

Focus:
- border-primary-500
- ring-4 ring-primary-500/20
- outline-none

Error:
- border-error-500
- ring-4 ring-error-500/20

Success:
- border-success-500
- Checkmark icon (right)

Disabled:
- bg-gray-100 dark:bg-gray-800
- cursor-not-allowed
- opacity-60
```

### Link States

```css
Default:
- text-primary-600 dark:text-primary-400
- underline-offset-4

Hover:
- text-primary-700 dark:text-primary-300
- underline

Active:
- text-primary-800 dark:text-primary-200

Visited:
- Optional: text-purple-600
```

### Card States

```css
Default:
- bg-white dark:bg-gray-800
- border border-gray-200 dark:border-gray-700
- rounded-xl shadow-sm

Hover (Interactive):
- shadow-lg
- border-primary-500/50
- transform: translateY(-2px)
- Transition: all 200ms

Active:
- shadow-md
- transform: translateY(0)
```

---

## 7. Accessibility Guidelines

### Color Contrast

**WCAG 2.1 AA Requirements**
- Normal text (< 18px): Minimum 4.5:1 contrast ratio
- Large text (>= 18px or 14px bold): Minimum 3:1 contrast ratio
- UI components and graphics: Minimum 3:1 contrast ratio

**Verification**
- All text on primary backgrounds meets AA standards
- Error states have sufficient contrast
- Disabled states are perceivable but clearly disabled
- Focus indicators are highly visible

### Focus Indicators

**Keyboard Navigation**
```css
/* Strong visible focus ring */
focus:ring-4 focus:ring-primary-500/50 focus:outline-none

/* Skip to content link */
<a href="#main" class="sr-only focus:not-sr-only">Skip to main content</a>

/* Focus visible only (not on click) */
focus-visible:ring-4 focus-visible:ring-primary-500/50
```

**Focus Order**
- Logical tab order matching visual layout
- Skip links for keyboard users
- Focus trap in modals
- Return focus after modal close

### Screen Reader Support

**Semantic HTML**
- Use proper heading hierarchy (h1 > h2 > h3)
- Landmark regions (header, nav, main, aside, footer)
- Lists for navigation items
- Buttons vs links (actions vs navigation)

**ARIA Labels**
```html
<!-- Icon buttons -->
<button aria-label="Close modal">
  <XIcon />
</button>

<!-- Status messages -->
<div role="status" aria-live="polite">
  Loading...
</div>

<!-- Hidden content -->
<span class="sr-only">Error:</span>
<span class="text-error-600">Invalid email</span>
```

### Touch Targets

**Minimum Size**
- 44x44px minimum touch target
- 48x48px recommended for important actions
- Adequate spacing between interactive elements (8px min)

### Motion & Animation

**Respect Reduced Motion**
```css
/* Default animation */
.card {
  @apply transition-all duration-200;
}

/* Disable for users who prefer reduced motion */
@media (prefers-reduced-motion: reduce) {
  .card {
    @apply transition-none;
  }
}
```

**Safe Animation**
- Avoid seizure-inducing flashes
- No autoplay for video/audio
- Pause/stop controls for animations
- Subtle, purposeful motion only

---

## 8. Responsive Design Strategy

### Mobile-First Approach

**Base Styles (320px - 640px)**
```css
- Single column layouts
- Full-width components
- Larger touch targets (min 44px)
- Simplified navigation (hamburger)
- Stack cards vertically
- Hide secondary information
- Bottom navigation for key actions
```

**Tablet (640px - 1024px)**
```css
- Two-column layouts where appropriate
- Side-by-side forms
- Expanded navigation (still may collapse)
- Grid layouts for cards (2 columns)
- Moderate spacing increases
```

**Desktop (1024px+)**
```css
- Multi-column layouts
- Sidebar + main content
- Horizontal navigation
- Grid layouts (3-4 columns)
- Hover states active
- Generous spacing
- Decorative elements visible
```

### Breakpoint Usage Patterns

**Container Padding**
```css
px-4 sm:px-6 lg:px-8
```

**Typography Scaling**
```css
text-2xl sm:text-3xl lg:text-4xl
```

**Layout Changes**
```css
flex flex-col md:flex-row
grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3
```

**Visibility Toggles**
```css
hidden lg:block        /* Hide on mobile, show on desktop */
block lg:hidden        /* Show on mobile, hide on desktop */
```

### Content Priority

**Mobile**
- Core content first
- Critical actions prominent
- Minimal navigation
- Progressive disclosure

**Desktop**
- Full navigation
- Secondary content visible
- Multiple columns
- Enhanced interactions

---

## 9. Animation & Transitions

### Timing Functions

```css
ease-linear:     cubic-bezier(0, 0, 1, 1)
ease-in:         cubic-bezier(0.4, 0, 1, 1)
ease-out:        cubic-bezier(0, 0, 0.2, 1)
ease-in-out:     cubic-bezier(0.4, 0, 0.2, 1)
```

**Custom Timing**
```css
/* Smooth deceleration */
transition-timing-function: cubic-bezier(0.4, 0.0, 0.2, 1);

/* Snappy bounce */
transition-timing-function: cubic-bezier(0.68, -0.55, 0.265, 1.55);
```

### Duration Scale

```css
duration-75:   75ms    /* Instant feedback */
duration-100:  100ms   /* Quick transitions */
duration-150:  150ms   /* Snappy interactions */
duration-200:  200ms   /* Default transitions */
duration-300:  300ms   /* Moderate animations */
duration-500:  500ms   /* Slower reveals */
duration-700:  700ms   /* Dramatic entrances */
duration-1000: 1000ms  /* Long transitions */
```

**Usage Guidelines**
- Micro-interactions: 100-200ms
- Component state changes: 200-300ms
- Page transitions: 300-500ms
- Loading animations: 500-1000ms (looped)

### Common Animations

**Fade In**
```css
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.fade-in {
  animation: fadeIn 300ms ease-out;
}
```

**Slide Up**
```css
@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
```

**Scale Pop**
```css
@keyframes scalePop {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
```

**Skeleton Pulse**
```css
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
.skeleton {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
```

### Transition Classes

```css
/* Hover elevate */
.hover-elevate {
  @apply transition-all duration-200 hover:shadow-lg hover:-translate-y-1;
}

/* Smooth color */
.smooth-color {
  @apply transition-colors duration-200;
}

/* Scale on hover */
.hover-scale {
  @apply transition-transform duration-200 hover:scale-105;
}

/* Fade transition */
.fade-transition {
  @apply transition-opacity duration-300;
}
```

### Loading States

**Spinner**
```css
@keyframes spin {
  to { transform: rotate(360deg); }
}
.spinner {
  animation: spin 1s linear infinite;
}
```

**Skeleton Screens**
- Background pulse animation
- Match layout of loaded content
- Subtle gray gradient
- Duration: 2s infinite loop

**Progress Indicators**
- Linear progress bar
- Circular spinner
- Step indicators for multi-step forms

---

## 10. Dark Mode Implementation

### Strategy

**System Preference Detection**
```javascript
// Detect system preference
const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

// Listen for changes
window.matchMedia('(prefers-color-scheme: dark)')
  .addEventListener('change', e => {
    const isDark = e.matches;
    // Update theme
  });
```

**User Override**
```javascript
// Store preference
localStorage.setItem('theme', 'dark'); // or 'light' or 'system'

// Apply theme
if (theme === 'dark' ||
    (theme === 'system' && systemPrefersDark)) {
  document.documentElement.classList.add('dark');
}
```

### Color Adaptations

**Background Layers**
```
Light Mode:
- Base: white
- Elevated: white with shadow
- Recessed: gray-50
- Overlay: white with backdrop

Dark Mode:
- Base: gray-950
- Elevated: gray-900 with stronger shadow
- Recessed: gray-900
- Overlay: gray-800 with backdrop
```

**Text Hierarchy**
```
Light Mode:
- Primary: gray-900
- Secondary: gray-600
- Tertiary: gray-500
- Disabled: gray-400

Dark Mode:
- Primary: gray-50
- Secondary: gray-300
- Tertiary: gray-400
- Disabled: gray-600
```

**Border Strategy**
```
Light Mode:
- Subtle: gray-200
- Default: gray-300
- Strong: gray-400

Dark Mode:
- Subtle: gray-800
- Default: gray-700
- Strong: gray-600
```

### Image Handling

**Invert on Dark Mode**
```css
/* For logos, icons */
.dark-invert {
  @apply dark:invert dark:brightness-0 dark:contrast-200;
}
```

**Separate Images**
```html
<!-- Provide separate images for each theme -->
<img src="/logo-light.svg" class="dark:hidden" />
<img src="/logo-dark.svg" class="hidden dark:block" />
```

**Overlay for Photos**
```css
/* Slightly dim photos in dark mode */
.photo-dark {
  @apply dark:brightness-90 dark:contrast-110;
}
```

### Shadow Adjustments

**Dark Mode Shadows**
```css
/* Stronger shadows with more opacity in dark mode */
.card {
  @apply shadow-md dark:shadow-xl dark:shadow-gray-900/50;
}

/* Colored glow effects */
.glow-primary {
  @apply dark:shadow-primary-900/30;
}
```

---

## 11. Gradient Patterns

### Background Gradients

**Hero Gradient**
```css
bg-gradient-to-br from-primary-500 via-secondary-600 to-accent-600
```

**Subtle Gradient**
```css
bg-gradient-to-b from-gray-50 to-white dark:from-gray-950 dark:to-gray-900
```

**Mesh Gradient (CSS)**
```css
background:
  radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.2) 0, transparent 50%),
  radial-gradient(at 100% 100%, rgba(168, 85, 247, 0.2) 0, transparent 50%);
```

### Text Gradients

```css
.gradient-text {
  @apply bg-clip-text text-transparent bg-gradient-to-r
         from-primary-600 via-secondary-600 to-accent-600;
}
```

### Border Gradients

```css
/* Gradient border effect */
.gradient-border {
  @apply relative bg-gradient-to-r from-primary-500 to-secondary-500 p-[2px] rounded-lg;
}
.gradient-border > div {
  @apply bg-white dark:bg-gray-900 rounded-lg;
}
```

---

## 12. Icons & Assets

### Icon System

**Icon Library:** Heroicons (recommended) or Lucide Icons
- Size: 16px, 20px, 24px, 32px
- Stroke width: 1.5-2
- Style: Outline for default, solid for active states

**Icon Usage**
```html
<!-- Small icons in buttons -->
<svg class="w-4 h-4">...</svg>

<!-- Default icons -->
<svg class="w-5 h-5">...</svg>

<!-- Large feature icons -->
<svg class="w-8 h-8">...</svg>
```

**Icon Colors**
```css
/* Inherit text color */
text-gray-600 dark:text-gray-400

/* Colored icons */
text-primary-600 dark:text-primary-400

/* Icon in button */
text-white (if on colored button)
```

### Image Optimization

**Formats**
- WebP for photos (with JPG fallback)
- SVG for logos, icons, illustrations
- PNG for screenshots (optimize with TinyPNG)

**Responsive Images**
```html
<img
  src="/image-800w.webp"
  srcset="
    /image-400w.webp 400w,
    /image-800w.webp 800w,
    /image-1200w.webp 1200w
  "
  sizes="(max-width: 640px) 400px, (max-width: 1024px) 800px, 1200px"
  alt="Description"
  loading="lazy"
/>
```

**Aspect Ratios**
```css
/* Common ratios */
aspect-square:    1:1   /* Avatars, thumbnails */
aspect-video:     16:9  /* Project previews */
aspect-[4/3]:     4:3   /* Traditional photos */
aspect-[21/9]:    21:9  /* Ultrawide banners */
```

---

## 13. Code Syntax Highlighting

**Theme:** One Dark Pro or GitHub Dark/Light

**Syntax Colors**
```css
Light Mode:
- Background: gray-50
- Text: gray-900
- Keywords: primary-600
- Strings: accent-600
- Numbers: secondary-600
- Comments: gray-500

Dark Mode:
- Background: gray-900
- Text: gray-100
- Keywords: primary-400
- Strings: accent-400
- Numbers: secondary-400
- Comments: gray-500
```

**Code Block Design**
```css
- Rounded-lg container
- Border subtle
- Padding: p-4
- Font: JetBrains Mono
- Line numbers optional (gray-500)
- Copy button (top-right, absolute)
- Language badge (top-left)
```

---

## 14. Form Patterns

### Input Field Anatomy

```html
<div class="space-y-1">
  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
    Email Address
    <span class="text-error-500">*</span>
  </label>

  <div class="relative">
    <input
      type="email"
      class="block w-full rounded-lg border-gray-300
             focus:border-primary-500 focus:ring-4
             focus:ring-primary-500/20 px-4 py-2"
      placeholder="you@example.com"
    />
    <!-- Icon (optional) -->
    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
      <MailIcon class="w-5 h-5 text-gray-400" />
    </div>
  </div>

  <p class="text-xs text-gray-500">
    We'll never share your email.
  </p>

  <!-- Error state -->
  <p class="text-sm text-error-600 flex items-center gap-1">
    <ExclamationIcon class="w-4 h-4" />
    Please enter a valid email address.
  </p>
</div>
```

### Checkbox & Radio

```html
<!-- Custom styled checkbox -->
<label class="flex items-center gap-2 cursor-pointer">
  <input
    type="checkbox"
    class="w-4 h-4 text-primary-600 border-gray-300 rounded
           focus:ring-4 focus:ring-primary-500/20"
  />
  <span class="text-sm text-gray-700 dark:text-gray-300">
    I agree to the terms
  </span>
</label>

<!-- Radio group -->
<div class="space-y-2">
  <label class="flex items-center gap-2 cursor-pointer">
    <input type="radio" name="plan" value="free"
           class="w-4 h-4 text-primary-600 border-gray-300
                  focus:ring-4 focus:ring-primary-500/20" />
    <span class="text-sm">Free Plan</span>
  </label>
  <!-- More radio options -->
</div>
```

### Select Dropdown

```html
<!-- Native select with custom styling -->
<select class="block w-full rounded-lg border-gray-300
               focus:border-primary-500 focus:ring-4
               focus:ring-primary-500/20 px-4 py-2
               bg-white dark:bg-gray-800
               appearance-none cursor-pointer">
  <option>Select an option</option>
  <option value="1">Option 1</option>
  <option value="2">Option 2</option>
</select>

<!-- Custom chevron icon -->
<div class="relative">
  <select class="..."></select>
  <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
    <ChevronDownIcon class="w-5 h-5 text-gray-400" />
  </div>
</div>
```

### File Upload

```html
<!-- Drag-drop area -->
<div class="border-2 border-dashed border-gray-300 rounded-lg p-8
            hover:border-primary-500 transition-colors cursor-pointer
            text-center">
  <input type="file" class="sr-only" id="file-upload" />
  <label for="file-upload" class="cursor-pointer">
    <UploadIcon class="mx-auto w-12 h-12 text-gray-400 mb-3" />
    <p class="text-sm text-gray-600">
      <span class="text-primary-600 font-medium">Click to upload</span>
      or drag and drop
    </p>
    <p class="text-xs text-gray-500 mt-1">
      PNG, JPG, GIF up to 10MB
    </p>
  </label>
</div>
```

---

## 15. Performance Guidelines

### Asset Loading

**Images**
- Use lazy loading: `loading="lazy"`
- Implement blur-up placeholders
- Serve WebP with fallbacks
- Use CDN for static assets

**Fonts**
```html
<!-- Preload critical fonts -->
<link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>

<!-- Use font-display swap -->
@font-face {
  font-family: 'Inter';
  font-display: swap;
  src: url('/fonts/inter.woff2') format('woff2');
}
```

**CSS**
- Critical CSS inline in `<head>`
- Defer non-critical CSS
- Use Tailwind's JIT mode
- Purge unused styles

**JavaScript**
- Code splitting by route
- Lazy load components
- Defer non-critical scripts
- Use async/defer attributes

### Animation Performance

**Hardware Accelerated Properties**
- Use `transform` instead of `top/left`
- Use `opacity` for fades
- Avoid animating `width`, `height`, `margin`
- Use `will-change` sparingly

**Example**
```css
/* Good: Hardware accelerated */
.slide-in {
  transform: translateX(0);
  transition: transform 300ms;
}

/* Avoid: Triggers layout */
.slide-in {
  left: 0;
  transition: left 300ms;
}
```

### Bundle Size

**Target Metrics**
- Initial JS bundle: < 200KB gzipped
- CSS bundle: < 50KB gzipped
- First Contentful Paint: < 1.5s
- Time to Interactive: < 3.5s

---

## 16. Implementation Checklist

### Phase 1: Foundation
- [ ] Install Tailwind CSS and configure
- [ ] Set up design tokens in `tailwind.config.js`
- [ ] Configure dark mode (class strategy)
- [ ] Install icon library (Heroicons)
- [ ] Load Inter font from Google Fonts or local
- [ ] Set up base styles and CSS reset
- [ ] Configure responsive breakpoints

### Phase 2: Core Components
- [ ] Button variants (primary, secondary, ghost, icon)
- [ ] Input fields (text, email, password, textarea)
- [ ] Form validation states
- [ ] Card component
- [ ] Modal/Dialog component
- [ ] Navigation (desktop + mobile)
- [ ] Footer component

### Phase 3: Public Portfolio
- [ ] Hero section with gradient
- [ ] About section with timeline/skills
- [ ] Projects grid with hover effects
- [ ] Skills display (badges or cards)
- [ ] Contact form with validation
- [ ] Blog list/grid layout
- [ ] Responsive navigation
- [ ] Dark mode toggle

### Phase 4: Admin CMS
- [ ] Dashboard metric cards
- [ ] Data table with sorting
- [ ] Sidebar navigation (collapsible)
- [ ] Form components (all field types)
- [ ] Modal for create/edit
- [ ] Dropdown menus
- [ ] Notification/toast system
- [ ] Breadcrumbs and pagination

### Phase 5: Polish
- [ ] Loading states (skeletons, spinners)
- [ ] Empty states
- [ ] Error states (404, 500)
- [ ] Transitions and animations
- [ ] Accessibility audit (WCAG AA)
- [ ] Cross-browser testing
- [ ] Performance optimization
- [ ] Documentation for components

---

## 17. Tailwind Config Reference

```javascript
// tailwind.config.js
module.exports = {
  darkMode: 'class',
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          800: '#3730a3',
          900: '#312e81',
          950: '#1e1b4b',
        },
        secondary: {
          50: '#faf5ff',
          100: '#f3e8ff',
          200: '#e9d5ff',
          300: '#d8b4fe',
          400: '#c084fc',
          500: '#a855f7',
          600: '#9333ea',
          700: '#7e22ce',
          800: '#6b21a8',
          900: '#581c87',
        },
        accent: {
          50: '#ecfdf5',
          100: '#d1fae5',
          200: '#a7f3d0',
          300: '#6ee7b7',
          400: '#34d399',
          500: '#10b981',
          600: '#059669',
          700: '#047857',
          800: '#065f46',
          900: '#064e3b',
        },
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      boxShadow: {
        'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.07)',
        'glass-lg': '0 8px 32px 0 rgba(31, 38, 135, 0.15)',
      },
      backdropBlur: {
        xs: '2px',
      },
      animation: {
        'fade-in': 'fadeIn 300ms ease-out',
        'slide-up': 'slideUp 300ms ease-out',
        'scale-pop': 'scalePop 200ms ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        scalePop: {
          '0%': { opacity: '0', transform: 'scale(0.95)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
  ],
}
```

---

## 18. Vue Component Example

```vue
<!-- Example: PrimaryButton.vue -->
<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="buttonClasses"
    @click="$emit('click', $event)"
  >
    <!-- Loading spinner -->
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-2 h-4 w-4"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      />
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      />
    </svg>

    <!-- Icon (optional) -->
    <slot name="icon" />

    <!-- Button text -->
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  type: {
    type: String,
    default: 'button',
  },
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'ghost', 'danger'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-medium rounded-lg transition-all focus:outline-none focus:ring-4';

  const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base',
  };

  const variants = {
    primary: `
      bg-primary-600 text-white shadow-sm
      hover:bg-primary-700 hover:shadow-md hover:-translate-y-0.5
      active:bg-primary-800 active:translate-y-0
      focus:ring-primary-500/50
      disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0
    `,
    secondary: `
      border-2 border-primary-600 text-primary-600 bg-transparent
      hover:bg-primary-50 dark:hover:bg-primary-900/20
      active:bg-primary-100 dark:active:bg-primary-900/40
      focus:ring-primary-500/50
      disabled:opacity-50 disabled:cursor-not-allowed
    `,
    ghost: `
      text-gray-700 dark:text-gray-300
      hover:bg-gray-100 dark:hover:bg-gray-800
      focus:ring-gray-500/50
      disabled:opacity-50 disabled:cursor-not-allowed
    `,
    danger: `
      bg-error-600 text-white shadow-sm
      hover:bg-error-700 hover:shadow-md hover:-translate-y-0.5
      active:bg-error-800 active:translate-y-0
      focus:ring-error-500/50
      disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0
    `,
  };

  return [
    base,
    sizes[props.size],
    variants[props.variant],
  ].join(' ');
});
</script>
```

---

## 19. Resources & References

### Design Inspiration
- **Tailwind UI**: https://tailwindui.com
- **Shadcn UI**: https://ui.shadcn.com
- **DaisyUI**: https://daisyui.com
- **Flowbite**: https://flowbite.com
- **Headless UI**: https://headlessui.com

### Color Tools
- **Tailwind Color Palette**: https://tailwindcss.com/docs/customizing-colors
- **Coolors**: https://coolors.co
- **Color Contrast Checker**: https://webaim.org/resources/contrastchecker

### Typography
- **Google Fonts**: https://fonts.google.com
- **Font Pair**: https://www.fontpair.co
- **Typescale**: https://typescale.com

### Icons
- **Heroicons**: https://heroicons.com
- **Lucide**: https://lucide.dev
- **Phosphor Icons**: https://phosphoricons.com

### Accessibility
- **WCAG Guidelines**: https://www.w3.org/WAI/WCAG21/quickref
- **A11y Project**: https://www.a11yproject.com
- **ARIA Authoring Practices**: https://www.w3.org/WAI/ARIA/apg

### Tools
- **Tailwind CSS IntelliSense**: VSCode extension
- **Prettier Plugin Tailwind**: Code formatting
- **Headless UI**: Unstyled accessible components
- **VueUse**: Vue composition utilities

---

## Next Steps

1. **Review and Approve**: Review this design system with stakeholders
2. **Configure Tailwind**: Set up custom colors and theme in `tailwind.config.js`
3. **Build Core Components**: Start with button, input, card components
4. **Create Component Library**: Build reusable Vue components
5. **Develop Pages**: Apply design system to public and admin pages
6. **Test Accessibility**: Run accessibility audits
7. **Optimize Performance**: Measure and optimize bundle sizes
8. **Document Components**: Create Storybook or component documentation

---

# SECTION 2: SUPPLEMENTAL COMPONENTS

## New Component Patterns

This section extends the core design system with specifications for three additional feature sets:

1. Awards & Recognition
2. Testimonials
3. Gallery

All specifications maintain consistency with the established design system (colors, typography, spacing, and interaction patterns).

---

### 1. Awards & Recognition Components

#### card-award

**Purpose:** Display individual award/recognition with visual hierarchy and category indication.

**Structure:**
```
Card Container (rounded-xl, shadow-md, hover:shadow-lg)
├── Icon/Badge Section (colored circle background)
│   └── Trophy/Medal/Certificate Icon (32px)
├── Content Section
│   ├── Award Title (text-xl font-semibold)
│   ├── Organization Name (text-sm text-muted)
│   ├── Date (text-xs text-muted, with calendar icon)
│   ├── Category Badge (rounded-full pill)
│   └── Description (text-sm, 2-3 lines, optional)
└── Action Link (optional: external link icon)
```

**Variants:**
- **Default:** Standard card with subtle shadow
- **Featured:** Gradient border, larger size, shadow-xl
- **Compact:** Icon + title only, smaller padding

**Color Usage:**
- Icon circle backgrounds:
  - Award: `bg-primary-100 dark:bg-primary-900/30`, icon: `text-primary-600 dark:text-primary-400`
  - Certification: `bg-accent-100 dark:bg-accent-900/30`, icon: `text-accent-600 dark:text-accent-400`
  - Recognition: `bg-secondary-100 dark:bg-secondary-900/30`, icon: `text-secondary-600 dark:text-secondary-400`
  - Honor: `bg-warning-100 dark:bg-warning-900/30`, icon: `text-warning-600 dark:text-warning-400`

**Spacing:**
- Container padding: `p-6 sm:p-8`
- Icon section: `mb-4`
- Title to organization: `mt-2`
- Badge: `mt-3`
- Description: `mt-3`

**Sizing:**
- Standard: `min-h-[280px]`
- Featured: `min-h-[320px]`
- Compact: `min-h-[180px]`

**Border Radius:** `rounded-xl`

**Hover State:**
```css
transition-all duration-200
hover:shadow-lg
hover:-translate-y-1
hover:border-primary-500/50
```

**Dark Mode:**
- Background: `bg-white dark:bg-gray-800`
- Border: `border border-gray-200 dark:border-gray-700`
- Icon circles: Use dark variants from color usage above
- Text: Follow existing text hierarchy (gray-900/gray-50 for titles)

**Responsive:**
- Mobile: Full width, p-6
- Tablet: Same as desktop
- Desktop: p-8, fixed aspect ratio

**Accessibility:**
- Award cards should be focusable (tabindex="0")
- Icon should have aria-label describing award type
- External links should indicate "opens in new window"

---

#### awards-grid

**Purpose:** Container for award cards with responsive grid layout.

**Layout:**
```css
grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8
```

**Container Classes:**
- Max width: `max-w-7xl mx-auto`
- Padding: `px-4 sm:px-6 lg:px-8`
- Section spacing: `py-12 lg:py-16`

**Section Header:**
```
Header Container (mb-12)
├── Title (heading-2, text-center)
├── Subtitle (text-lg text-muted, max-w-2xl mx-auto, mt-4)
└── Category Filter Pills (mt-8, optional)
```

**Filter Pills (Category Selection):**
- Layout: `flex flex-wrap justify-center gap-2`
- Pill style: `rounded-full px-4 py-2 text-sm font-medium transition-colors`
- Default: `bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700`
- Active: `bg-primary-600 text-white hover:bg-primary-700`

**Empty State:**
```
Container (text-center py-16)
├── Icon (Trophy, w-16 h-16, text-gray-400, mx-auto)
├── Title (text-xl font-semibold text-gray-900 dark:text-gray-100, mt-4)
├── Message (text-gray-600 dark:text-gray-400, mt-2)
└── CTA Button (optional, mt-6)
```

**Loading State:**
- Skeleton cards: `animate-pulse` with gray rectangles
- Show 6 skeleton cards in grid layout
- Icon circle: `bg-gray-200 dark:bg-gray-700 rounded-full w-16 h-16`
- Text lines: `bg-gray-200 dark:bg-gray-700 rounded h-4 w-3/4`

---

### 2. Testimonials Components

#### card-testimonial

**Purpose:** Display client/user testimonial with author information and optional rating.

**Structure:**
```
Card Container (rounded-2xl, glassmorphic background)
├── Quotation Mark Icon (decorative, large, bg element)
├── Rating Stars (5-star display, top or after quote)
├── Quote Text (text-lg, italic, leading-relaxed)
├── Divider (subtle, mt-6, mb-4)
└── Author Section (flex items-center)
    ├── Avatar (rounded-full, 56px)
    └── Author Info
        ├── Name (text-base font-semibold)
        ├── Title/Position (text-sm text-muted)
        └── Company (text-sm text-primary-600)
```

**Variants:**
- **Standard:** Full card with all elements
- **Compact:** Smaller avatar (40px), shorter quote (max 2 lines)
- **Featured:** Larger card, gradient background, shadow-xl

**Color Usage:**
- Card background: `bg-white/70 dark:bg-gray-800/70 backdrop-blur-xl`
- Border: `border border-gray-200/50 dark:border-gray-700/50`
- Quote mark: `text-primary-200 dark:text-primary-900/30` (very large, absolute positioned)
- Stars: Filled `text-warning-500`, empty `text-gray-300 dark:text-gray-600`

**Spacing:**
- Container padding: `p-8 sm:p-10`
- Quote to author: `mt-8`
- Avatar to info: `ml-4`

**Typography:**
- Quote text: `text-lg font-normal leading-relaxed italic text-gray-700 dark:text-gray-300`
- Name: `text-base font-semibold text-gray-900 dark:text-gray-100`
- Title: `text-sm text-gray-600 dark:text-gray-400`
- Company: `text-sm text-primary-600 dark:text-primary-400 hover:underline`

**Border Radius:** `rounded-2xl`

**Shadow:** `shadow-md hover:shadow-xl`

**Hover State:**
```css
transition-all duration-300
hover:shadow-xl
hover:-translate-y-1
```

**Dark Mode:**
- Background: Use glassmorphic effect with dark variant
- Quote mark: Lower opacity in dark mode
- All text follows dark mode hierarchy

**Quotation Mark Design:**
- Position: `absolute top-6 left-6 opacity-10`
- Size: `text-8xl` or `w-24 h-24` SVG
- Color: `text-primary-500 dark:text-primary-400`
- z-index: Behind text content

**Rating Stars Component:**
- Container: `flex items-center gap-1`
- Star size: `w-5 h-5`
- Filled: `text-warning-500`
- Empty: `text-gray-300 dark:text-gray-600`
- Half-star support: Use two overlapping icons

**Accessibility:**
- Quote should be wrapped in `<blockquote>`
- Avatar should have alt text with author name
- Rating should have aria-label "Rated X out of 5 stars"
- Company link (if clickable) should indicate external

---

#### carousel-testimonial

**Purpose:** Carousel/slider component for testimonials with navigation controls.

**Structure:**
```
Carousel Container
├── Carousel Track (slides container)
│   └── Testimonial Cards (card-testimonial × N)
├── Navigation Controls
│   ├── Previous Button (absolute left, rounded-full)
│   └── Next Button (absolute right, rounded-full)
└── Pagination Dots (bottom center)
    └── Dot × N (rounded-full indicators)
```

**Layout:**
- Desktop: Show 3 cards at once, slide 1 at a time
- Tablet: Show 2 cards at once
- Mobile: Show 1 card at a time, swipe gesture

**Container Classes:**
- Wrapper: `relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16`
- Track: `flex gap-6 lg:gap-8 overflow-hidden`

**Navigation Buttons:**
- Style: `absolute top-1/2 -translate-y-1/2 z-10`
- Button: `bg-white dark:bg-gray-800 rounded-full p-3 shadow-lg border border-gray-200 dark:border-gray-700`
- Icon: `w-6 h-6 text-gray-700 dark:text-gray-300`
- Position: Left button `left-0 lg:-left-6`, Right button `right-0 lg:-right-6`
- Hover: `bg-primary-50 dark:bg-primary-900/20 border-primary-500`
- Disabled: `opacity-50 cursor-not-allowed`

**Pagination Dots:**
- Container: `flex justify-center gap-2 mt-8`
- Dot: `w-2 h-2 rounded-full transition-all`
- Inactive: `bg-gray-300 dark:bg-gray-600`
- Active: `bg-primary-600 w-8` (elongated pill shape)
- Hover: `bg-gray-400 dark:bg-gray-500`

**Animation:**
- Slide transition: `transition-transform duration-500 ease-out`
- Fade between slides: `transition-opacity duration-300`
- Auto-play: Optional, 5s interval, pause on hover
- Respect `prefers-reduced-motion`: Disable auto-play, use crossfade instead of slide

**Touch Gestures (Mobile):**
- Swipe left/right to navigate
- Snap to card boundaries
- Momentum scrolling

**Keyboard Navigation:**
- Arrow keys: Navigate prev/next
- Tab: Focus through cards and controls
- Enter/Space: Activate focused navigation button

**Accessibility:**
- Container: `role="region" aria-label="Client testimonials carousel"`
- Buttons: `aria-label="Previous testimonial"` / `"Next testimonial"`
- Dots: `aria-label="Go to testimonial X of Y"`
- Current slide: `aria-current="true"`

---

### 3. Gallery Components

#### gallery-grid

**Purpose:** Responsive grid layout for gallery images with category filtering.

**Structure:**
```
Gallery Container
├── Filter Bar
│   ├── Category Pills (horizontal scrollable)
│   └── View Toggle (grid/list, optional)
├── Gallery Grid
│   └── Gallery Items (card-gallery-item × N)
└── Load More Button (if pagination enabled)
```

**Layout:**
```css
grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6
```

**Container Classes:**
- Max width: `max-w-7xl mx-auto`
- Padding: `px-4 sm:px-6 lg:px-8`
- Section spacing: `py-12`

**Filter Bar:**
- Container: `flex items-center gap-4 mb-8 overflow-x-auto pb-2`
- Pills: Same style as awards filter pills
- Active indicator: Background color change + border-b-2
- Responsive: Horizontal scroll on mobile, centered on desktop

**Grid Spacing:**
- Mobile: `gap-4`
- Tablet/Desktop: `gap-6`

**Aspect Ratio:**
- Fixed ratio for uniformity: `aspect-video` (16:9) or `aspect-square` (1:1)
- Image: `object-cover w-full h-full`

**Empty State:**
```
Container (col-span-full text-center py-16)
├── Icon (Photo, w-16 h-16, text-gray-400)
├── Title (text-xl font-semibold, mt-4)
├── Message (text-gray-600, mt-2)
└── Upload Button (admin) or Explore Button (public)
```

**Loading State:**
- Skeleton grid items: `animate-pulse bg-gray-200 dark:bg-gray-700`
- Maintain aspect ratio with skeletons
- Show 12 skeleton items

---

#### card-gallery-item

**Purpose:** Individual gallery image card with hover overlay and metadata.

**Structure:**
```
Card Container (rounded-lg, overflow-hidden)
├── Image (aspect-video or aspect-square)
├── Hover Overlay (absolute inset-0)
│   ├── Dark Backdrop (bg-black/60)
│   ├── Title (text-lg font-semibold text-white)
│   ├── Category Badge (rounded-full, bottom-left)
│   └── View Icon (center, w-10 h-10)
└── Skeleton (loading state)
```

**Card Styles:**
- Container: `relative group cursor-pointer rounded-lg overflow-hidden`
- Border: `border border-gray-200 dark:border-gray-700`
- Shadow: `shadow-sm hover:shadow-xl`

**Image:**
- Classes: `w-full h-full object-cover transition-transform duration-300 group-hover:scale-105`
- Lazy loading: `loading="lazy"`
- Alt text required

**Hover Overlay:**
```css
absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100
transition-opacity duration-300
flex flex-col items-center justify-center
```

**Overlay Content:**
- Title: `text-lg font-semibold text-white text-center px-4`
- View Icon: `w-10 h-10 text-white mb-2`
- Category badge: `absolute bottom-4 left-4 bg-primary-600 text-white text-xs px-3 py-1 rounded-full`

**Hover State:**
```css
Image: scale-105
Overlay: opacity-0 to opacity-100
Shadow: shadow-sm to shadow-xl
Border: border-gray-200 to border-primary-500
```

**Dark Mode:**
- Border: `dark:border-gray-700 dark:hover:border-primary-500`
- Overlay stays dark (black/60) in both modes

**Mobile (No Hover):**
- Show overlay partially visible (bg-black/30 always)
- Title always visible at bottom
- Tap to open lightbox

**Accessibility:**
- Card: `role="button" tabindex="0"`
- Image: Descriptive alt text
- Keyboard: Enter/Space to open lightbox
- Focus: Visible ring indicator

---

#### lightbox-image

**Purpose:** Full-screen image viewer modal with navigation and controls.

**Structure:**
```
Lightbox Overlay (fixed inset-0)
├── Backdrop (bg-black/95, backdrop-blur-sm)
├── Close Button (top-right, rounded-full)
├── Image Container (centered, max-w-6xl)
│   ├── Image (object-contain, max-h-screen)
│   └── Caption (below image, optional)
├── Navigation
│   ├── Previous Arrow (left edge, rounded-full button)
│   └── Next Arrow (right edge, rounded-full button)
└── Counter (bottom-center, "X / Total")
```

**Overlay Styles:**
- Container: `fixed inset-0 z-50 flex items-center justify-center`
- Backdrop: `bg-black/95 backdrop-blur-sm`
- Click backdrop to close

**Close Button:**
- Position: `absolute top-4 right-4 md:top-8 md:right-8 z-10`
- Style: `bg-white/10 hover:bg-white/20 rounded-full p-3 text-white transition-colors`
- Icon: X icon, `w-6 h-6`
- Hover: Slight background lightening
- Focus: Ring indicator

**Image Container:**
- Max width: `max-w-6xl`
- Max height: `max-h-[90vh]`
- Padding: `px-4 py-8 md:px-8`
- Image: `object-contain w-full h-full`

**Caption (Optional):**
- Position: `mt-4 text-center`
- Style: `text-white text-base max-w-2xl mx-auto`
- Background: `bg-black/50 rounded-lg px-4 py-2`

**Navigation Arrows:**
- Position: `absolute top-1/2 -translate-y-1/2`
- Left: `left-4 md:left-8`
- Right: `right-4 md:right-8`
- Style: `bg-white/10 hover:bg-white/20 rounded-full p-4 text-white transition-all`
- Icon: ChevronLeft/Right, `w-8 h-8`
- Disabled: `opacity-30 cursor-not-allowed`
- Mobile: Smaller padding (p-3), smaller icons (w-6 h-6)

**Counter:**
- Position: `absolute bottom-8 left-1/2 -translate-x-1/2`
- Style: `bg-black/50 rounded-full px-4 py-2 text-white text-sm font-medium`
- Format: "3 / 24"

**Animations:**
- Open: `fade-in` + `scale-pop` (from 95% to 100%)
- Close: Reverse animation
- Image change: `fade-transition` (300ms)
- Duration: 300ms ease-out

**Keyboard Controls:**
- ESC: Close lightbox
- Arrow Left: Previous image
- Arrow Right: Next image
- Tab: Cycle through controls (close, prev, next)

**Touch Gestures (Mobile):**
- Swipe left/right: Navigate images
- Pinch to zoom (optional enhancement)
- Tap backdrop: Close

**Accessibility:**
- Container: `role="dialog" aria-modal="true" aria-label="Image lightbox"`
- Focus trap: Keep focus within lightbox
- Image: `alt` text from gallery item
- Buttons: Clear aria-labels
- Counter: `aria-live="polite"` for screen readers

**Performance:**
- Lazy load adjacent images (preload next/prev)
- Use smaller image versions if available
- Prevent body scroll when open

---

## Admin-Specific Components

### upload-bulk

**Purpose:** Drag-drop interface for uploading multiple images to gallery.

**Structure:**
```
Upload Container (border-dashed, rounded-xl)
├── Drop Zone Area
│   ├── Upload Icon (large, centered)
│   ├── Primary Text ("Click to upload or drag and drop")
│   ├── Secondary Text ("PNG, JPG, WebP up to 5MB each")
│   └── Browse Button (styled as primary button)
├── File List (when files selected)
│   └── File Item × N
│       ├── Thumbnail Preview
│       ├── File Name
│       ├── File Size
│       ├── Progress Bar (during upload)
│       └── Remove Button (X icon)
└── Action Bar
    ├── Clear All Button
    └── Upload All Button (primary)
```

**Drop Zone Styles:**
- Default: `border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl p-12 text-center`
- Hover: `border-primary-500 bg-primary-50 dark:bg-primary-900/10`
- Active (dragging): `border-primary-600 bg-primary-100 dark:bg-primary-900/20 scale-[1.02]`

**Icons:**
- Upload icon: `w-16 h-16 text-gray-400 mx-auto mb-4`
- Active state: `text-primary-600`

**Typography:**
- Primary text: `text-base font-medium text-gray-700 dark:text-gray-300`
- Secondary text: `text-sm text-gray-500 dark:text-gray-400 mt-2`
- Browse button: `text-primary-600 font-semibold hover:text-primary-700`

**File Item Card:**
- Layout: `flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg`
- Thumbnail: `w-16 h-16 object-cover rounded`
- Name: `text-sm font-medium truncate`
- Size: `text-xs text-gray-500`
- Remove: `ml-auto text-error-600 hover:text-error-700`

**Progress Bar:**
- Container: `h-1 bg-gray-200 dark:bg-gray-700 rounded-full mt-2 overflow-hidden`
- Fill: `h-full bg-primary-600 transition-all duration-300`
- Percentage text: `text-xs text-gray-600 dark:text-gray-400 mt-1`

**States:**
- **Idle:** Default drop zone
- **Hover:** Border and background color change
- **Dragging:** Scale and color change
- **Uploading:** Progress bars visible
- **Success:** Green checkmark icons
- **Error:** Red error icon, error message below file

**Validation:**
- File type check: Show error for invalid types
- File size check: Show error for oversized files
- Max files: Show warning if limit exceeded

**Accessibility:**
- Hidden file input: `sr-only`
- Label: Clear instructions
- Keyboard: Tab to browse button, Enter to open file picker
- Screen reader: Announce upload progress

---

### form-editor (Awards & Testimonials)

**Purpose:** Modal or page-based editor forms for creating/editing awards and testimonials.

**Common Form Patterns:**

**Section Grouping:**
- Use fieldset with legend
- Visual separator between sections: `border-t border-gray-200 dark:border-gray-700 pt-6 mt-6`

**Field Layout:**
- Single column default: `space-y-6`
- Two-column for related fields: `grid grid-cols-1 md:grid-cols-2 gap-6`
- Full-width for rich text: `col-span-full`

**Input Field Structure:**
```
Field Container (space-y-1)
├── Label (text-sm font-medium, with required indicator *)
├── Input/Textarea/Select (rounded-lg, border, focus ring)
├── Helper Text (text-xs text-gray-500, optional)
└── Error Message (text-sm text-error-600, with icon)
```

**File Upload Fields:**
- Thumbnail preview: `w-24 h-24 rounded-lg object-cover`
- Change button: Secondary button style
- Remove button: Ghost danger button

**Toggle Switches:**
- Featured toggle: `bg-gray-200 dark:bg-gray-700` (off), `bg-primary-600` (on)
- Status toggle: Same pattern
- Size: `w-11 h-6 rounded-full`

**Button Group (Footer):**
- Layout: `flex justify-end gap-3 pt-6 mt-6 border-t`
- Cancel: Secondary button
- Save: Primary button (with loading spinner when saving)

**Specific Fields:**

**Awards Editor:**
- Title: Text input (required)
- Organization: Text input (required)
- Date: Date picker
- Category: Select dropdown
- Description: Textarea (max 500 chars, char counter)
- Icon/Badge: Image upload (recommended size display)
- External Link: URL input (validation)
- Featured: Toggle switch
- Display Order: Number input

**Testimonials Editor:**
- Author Name: Text input (required)
- Title/Position: Text input
- Company: Text input
- Avatar: Image upload (circular crop preview)
- Testimonial: Textarea (max 1000 chars, char counter)
- Rating: Star rating selector (1-5 clickable stars)
- Date Received: Date picker
- Related Project: Select dropdown (optional)
- Featured: Toggle switch
- Display Order: Number input

**Validation States:**
- Error: Red border, error icon, error message
- Success: Green border, checkmark icon (optional)
- Loading: Disabled state, spinner

**Auto-save (Optional):**
- Indicator: Small text "Saved at HH:MM" or "Saving..."
- Position: Top-right of form or near submit button
- Auto-save every 30s of inactivity

---

## Color Usage Summary

**New Color Applications:**

**Awards:**
- Award category icons: Primary (blue), Accent (green), Secondary (purple), Warning (amber)
- Use existing semantic colors, no new colors introduced

**Testimonials:**
- Star ratings: `warning-500` (filled), `gray-300` (empty)
- Quote mark: `primary-200/primary-900` with low opacity
- Glassmorphic backgrounds: white/gray-800 with 70% opacity + backdrop-blur

**Gallery:**
- Hover overlays: `bg-black/60`
- Lightbox backdrop: `bg-black/95`
- Category badges: `primary-600` background
- No new colors, uses existing palette

---

## Spacing Additions

**No new spacing values.** Use existing scale:
- Card padding: `p-6 md:p-8`
- Section spacing: `py-12 lg:py-16`
- Grid gaps: `gap-4 md:gap-6 lg:gap-8`
- Icon margins: `mb-4 mt-3`

---

## Animation Enhancements

**New Animations:**

**Stagger Fade-In (Awards Grid):**
```css
@keyframes staggerFadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Apply with staggered delays */
.award-card:nth-child(1) { animation-delay: 0ms; }
.award-card:nth-child(2) { animation-delay: 100ms; }
.award-card:nth-child(3) { animation-delay: 200ms; }
/* etc. */
```

**Carousel Slide:**
```css
.carousel-slide-enter {
  opacity: 0;
  transform: translateX(100%);
}
.carousel-slide-enter-active {
  transition: all 500ms ease-out;
}
.carousel-slide-leave-active {
  transition: all 500ms ease-out;
}
.carousel-slide-leave-to {
  opacity: 0;
  transform: translateX(-100%);
}
```

**Lightbox Animations:**
- Backdrop: `fade-in` (300ms)
- Content: `scale-pop` + `fade-in` (300ms)
- Image transition: `fade-transition` (300ms)

**Respect Reduced Motion:**
```css
@media (prefers-reduced-motion: reduce) {
  .carousel-slide-enter,
  .carousel-slide-leave-to {
    transform: none;
    transition: opacity 300ms;
  }

  .lightbox-enter,
  .lightbox-leave-to {
    transform: none;
    transition: opacity 200ms;
  }
}
```

---

## Responsive Behavior Summary

**Awards Grid:**
- Mobile: 1 column, full-width cards
- Tablet: 2 columns, moderate gaps
- Desktop: 3 columns, generous gaps
- Large Desktop: 4 columns (optional)

**Testimonials Carousel:**
- Mobile: 1 card visible, swipe navigation
- Tablet: 2 cards visible
- Desktop: 3 cards visible
- Navigation buttons: Hidden on mobile (swipe only), visible on tablet+

**Gallery Grid:**
- Mobile: 1 column, small gaps
- Tablet: 2 columns, moderate gaps
- Desktop: 3 columns, larger gaps
- Extra Large: 4 columns

**Lightbox:**
- All viewports: Full-screen overlay
- Mobile: Smaller navigation buttons, smaller padding
- Desktop: Larger buttons, generous padding

---

## Accessibility Checklist

**Awards:**
- [ ] Award cards keyboard navigable
- [ ] Category filters keyboard selectable
- [ ] Focus indicators visible on all interactive elements
- [ ] Screen reader announces award details
- [ ] External links indicate new window

**Testimonials:**
- [ ] Carousel keyboard navigable (arrow keys)
- [ ] Rating has aria-label with number
- [ ] Auto-play can be paused
- [ ] Quote properly marked with blockquote
- [ ] Avatar has descriptive alt text

**Gallery:**
- [ ] Gallery items keyboard navigable
- [ ] Filter pills keyboard selectable
- [ ] Lightbox has focus trap
- [ ] Lightbox keyboard controls (ESC, arrows)
- [ ] Image alt text descriptive
- [ ] Counter announces to screen readers

**Forms (Admin):**
- [ ] All inputs have labels
- [ ] Required fields indicated
- [ ] Error messages associated with inputs
- [ ] File upload keyboard accessible
- [ ] Form submission accessible via keyboard

---

## Dark Mode Implementation (Extended)

**All new components support dark mode via class-based strategy:**

```css
/* Light mode (default) */
.card-award {
  @apply bg-white border-gray-200 text-gray-900;
}

/* Dark mode */
.dark .card-award {
  @apply bg-gray-800 border-gray-700 text-gray-100;
}
```

**Glassmorphic Elements (Testimonials):**
```css
bg-white/70 dark:bg-gray-800/70
backdrop-blur-xl
border-gray-200/50 dark:border-gray-700/50
```

**Overlay Elements (Gallery Lightbox):**
- Overlays stay dark in both modes (better contrast)
- Controls use white with opacity adjustments

---

## Component Dependencies

**Required Libraries:**

**Carousel (Testimonials):**
- Option 1: Swiper.js (recommended)
  - Lightweight, touch-friendly
  - Built-in keyboard navigation
  - Accessibility features
- Option 2: Vue Carousel library (vue3-carousel)
- Option 3: Custom implementation with Vue transitions

**Lightbox (Gallery):**
- Option 1: Custom Vue component (recommended for full control)
- Option 2: vue-easy-lightbox
- Must support keyboard navigation and focus trap

**File Upload (Admin):**
- Option 1: Custom drag-drop with Vue
- Option 2: vue-upload-component
- Must validate file types and sizes

**Date Picker (Admin Forms):**
- Use existing date input with native picker
- Optional: @vuepic/vue-datepicker for enhanced UX

---

## Usage Guidelines

**When to Use Each Component:**

**Awards Section:**
- Use on Homepage (featured awards only)
- Use on About page (full awards list)
- Use in footer (award badges)

**Testimonials:**
- Use on Homepage (carousel, 5-7 testimonials)
- Use on Project Detail pages (related testimonials)
- Use on About page (optional)

**Gallery:**
- Dedicated Gallery page (full showcase)
- Project Detail pages (project-specific gallery)
- Admin: Upload and manage all images

---

## Performance Considerations

**Awards:**
- Lazy load award images (icons can be inline SVG)
- Limit homepage to 3-6 featured awards
- Cache award data (low change frequency)

**Testimonials:**
- Preload adjacent carousel slides
- Lazy load avatar images
- Auto-play: Pause when not visible (Intersection Observer)

**Gallery:**
- Critical: Lazy load images (native `loading="lazy"`)
- Use thumbnail sizes for grid (not full-res)
- Lightbox: Preload prev/next images
- Infinite scroll (optional): Load 20-30 at a time
- Use WebP format with JPG fallback

---

## Testing Checklist

**Visual Testing:**
- [ ] Components match design specs across breakpoints
- [ ] Dark mode renders correctly
- [ ] Hover states work on desktop
- [ ] Touch interactions work on mobile

**Functional Testing:**
- [ ] Carousel navigation works (arrows, dots, swipe)
- [ ] Gallery filters work correctly
- [ ] Lightbox navigation works
- [ ] File upload validates correctly
- [ ] Forms submit successfully

**Accessibility Testing:**
- [ ] Keyboard navigation works
- [ ] Screen reader announces content
- [ ] Focus indicators visible
- [ ] Color contrast passes WCAG AA
- [ ] Touch targets minimum 44px

**Performance Testing:**
- [ ] Images lazy load correctly
- [ ] No layout shift (CLS)
- [ ] Smooth animations (60fps)
- [ ] Bundle size acceptable

---

## Migration Notes

**Adding to Existing Design System:**

1. Add new component classes to Tailwind config (if custom utilities needed)
2. Create Vue components in `src/components/` directory
3. Update component library documentation
4. Add to Storybook (if using)
5. Test across all breakpoints and themes

**No Breaking Changes:**
- All new components use existing design tokens
- No modifications to core design system
- Fully compatible with existing components

---

**End of Master Design System Documentation (Version 2.0)**

This consolidated design system provides a complete reference for building a modern, accessible, and performant portfolio website with admin CMS using Vue 3 and Tailwind CSS. All original specifications and supplemental components are preserved with enhanced organization and cross-referencing.
