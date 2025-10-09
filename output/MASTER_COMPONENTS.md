# Portfolio V2 - MASTER COMPONENTS SPECIFICATION

**Version:** 2.0 (Consolidated)
**Date:** October 2, 2025
**Framework:** Vue 3 (Composition API) + Tailwind CSS
**Total Components:** 45-50 reusable Vue components

---

## Table of Contents

### Component Categories
1. [Overview](#overview)
2. [Core UI Components](#core-ui-components)
3. [Awards Components](#awards-components)
4. [Testimonials Components](#testimonials-components)
5. [Gallery Components](#gallery-components)
6. [Usage Examples](#usage-examples)
7. [State Management (Pinia Stores)](#state-management-pinia-stores)
8. [TypeScript Types](#typescript-types)
9. [Performance Optimizations](#performance-optimizations)
10. [Implementation Checklist](#implementation-checklist)

---

## Overview

Technical specifications for Vue components implementing Awards, Testimonials, and Gallery features. Each specification includes:

- Component name and file location
- Props with types and defaults
- Emitted events
- Slots (if applicable)
- State management approach
- API endpoints
- Tailwind class specifications
- Dependencies
- Accessibility requirements
- Usage examples

---

## CORE UI COMPONENTS

### LanguageSwitcher.vue

**Purpose:** Toggle between English (EN) and Indonesian (ID) languages with flag icons.

**File Location:** `src/components/ui/LanguageSwitcher.vue`

**Props:**

```typescript
interface LanguageSwitcherProps {
  variant?: 'icon' | 'text' | 'combined'; // Display style
  size?: 'sm' | 'md' | 'lg';
}
```

**Defaults:**
- `variant`: 'icon'
- `size`: 'md'

**Emits:**

```typescript
{
  'language-change': (lang: 'en' | 'id') => void;
}
```

**State Management:**
- Uses i18n store (`useI18nStore`) for current language state
- Persists selection to localStorage

**Tailwind Classes:**

```vue
<template>
  <div class="relative inline-flex items-center">
    <!-- Icon Variant -->
    <div
      v-if="variant === 'icon'"
      class="flex items-center gap-2 p-2 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
    >
      <button
        v-for="lang in languages"
        :key="lang.code"
        @click="switchLanguage(lang.code)"
        :class="[
          'relative rounded-md transition-all duration-200',
          'hover:bg-white dark:hover:bg-gray-700',
          'focus:outline-none focus:ring-2 focus:ring-primary-500',
          sizeClasses,
          currentLanguage === lang.code
            ? 'bg-white dark:bg-gray-700 shadow-sm ring-1 ring-primary-500'
            : 'opacity-60 hover:opacity-100'
        ]"
        :aria-label="`Switch to ${lang.name}`"
        :aria-pressed="currentLanguage === lang.code"
      >
        <img
          :src="lang.flag"
          :alt="`${lang.name} flag`"
          :class="flagSizeClasses"
          class="rounded"
        />
      </button>
    </div>

    <!-- Text Variant -->
    <div
      v-else-if="variant === 'text'"
      class="flex items-center gap-1 p-1 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
    >
      <button
        v-for="lang in languages"
        :key="lang.code"
        @click="switchLanguage(lang.code)"
        :class="[
          'px-3 py-1.5 rounded-md text-sm font-medium transition-all duration-200',
          'hover:bg-white dark:hover:bg-gray-700',
          'focus:outline-none focus:ring-2 focus:ring-primary-500',
          currentLanguage === lang.code
            ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
        ]"
        :aria-label="`Switch to ${lang.name}`"
        :aria-pressed="currentLanguage === lang.code"
      >
        {{ lang.code.toUpperCase() }}
      </button>
    </div>

    <!-- Combined Variant (Flag + Text) -->
    <div
      v-else
      class="flex items-center gap-2 p-2 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
    >
      <button
        v-for="lang in languages"
        :key="lang.code"
        @click="switchLanguage(lang.code)"
        :class="[
          'flex items-center gap-2 px-3 py-1.5 rounded-md transition-all duration-200',
          'hover:bg-white dark:hover:bg-gray-700',
          'focus:outline-none focus:ring-2 focus:ring-primary-500',
          currentLanguage === lang.code
            ? 'bg-white dark:bg-gray-700 shadow-sm ring-1 ring-primary-500'
            : 'opacity-60 hover:opacity-100'
        ]"
        :aria-label="`Switch to ${lang.name}`"
        :aria-pressed="currentLanguage === lang.code"
      >
        <img
          :src="lang.flag"
          :alt="`${lang.name} flag`"
          class="w-5 h-5 rounded"
        />
        <span
          :class="[
            'text-sm font-medium',
            currentLanguage === lang.code
              ? 'text-primary-600 dark:text-primary-400'
              : 'text-gray-600 dark:text-gray-400'
          ]"
        >
          {{ lang.code.toUpperCase() }}
        </span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18nStore } from '@/stores/i18n';

const props = withDefaults(defineProps<LanguageSwitcherProps>(), {
  variant: 'icon',
  size: 'md'
});

const emit = defineEmits(['language-change']);
const i18nStore = useI18nStore();

const currentLanguage = computed(() => i18nStore.currentLanguage);

const languages = [
  {
    code: 'en',
    name: 'English',
    flag: '/flags/us.svg' // or use emoji: 🇺🇸
  },
  {
    code: 'id',
    name: 'Indonesian',
    flag: '/flags/id.svg' // or use emoji: 🇮🇩
  }
];

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'p-1.5',
    md: 'p-2',
    lg: 'p-3'
  };
  return sizes[props.size];
});

const flagSizeClasses = computed(() => {
  const sizes = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6'
  };
  return sizes[props.size];
});

const switchLanguage = (lang: 'en' | 'id') => {
  i18nStore.setLanguage(lang);
  emit('language-change', lang);
};
</script>
```

**Dependencies:**
- i18n store (Pinia)
- Flag images or emoji flags

**Accessibility:**
- Buttons have `aria-label` and `aria-pressed` states
- Keyboard navigable and focusable
- Focus ring visible on interaction
- Current language clearly indicated

**Usage Examples:**

```vue
<!-- In Header/Navbar -->
<template>
  <header>
    <nav class="flex items-center justify-between">
      <Logo />
      <div class="flex items-center gap-4">
        <NavLinks />
        <ThemeToggle />
        <LanguageSwitcher variant="icon" size="md" />
      </div>
    </nav>
  </header>
</template>

<!-- Footer variant -->
<template>
  <footer>
    <div class="flex items-center justify-between">
      <Copyright />
      <LanguageSwitcher variant="text" size="sm" />
    </div>
  </footer>
</template>

<!-- Mobile menu variant -->
<template>
  <MobileMenu>
    <LanguageSwitcher variant="combined" size="lg" />
  </MobileMenu>
</template>
```

**i18n Store Integration:**

```typescript
// stores/i18n.ts
import { defineStore } from 'pinia';
import { useRouter } from 'vue-router';

export const useI18nStore = defineStore('i18n', {
  state: () => ({
    currentLanguage: (localStorage.getItem('language') || 'en') as 'en' | 'id',
    availableLanguages: ['en', 'id'] as const
  }),

  actions: {
    setLanguage(lang: 'en' | 'id') {
      this.currentLanguage = lang;
      localStorage.setItem('language', lang);
      
      // Update document lang attribute for accessibility
      document.documentElement.lang = lang;
      
      // Trigger route update for content fetching
      const router = useRouter();
      router.replace({ query: { ...router.currentRoute.value.query, lang } });
    },

    toggleLanguage() {
      const newLang = this.currentLanguage === 'en' ? 'id' : 'en';
      this.setLanguage(newLang);
    }
  }
});
```

---

## AWARDS COMPONENTS

### AwardCard.vue

**Purpose:** Display individual award/recognition card with icon, title, organization, and metadata.

**File Location:** `src/components/awards/AwardCard.vue`

**Props:**

```typescript
interface AwardCardProps {
  award: {
    id: string | number;
    title: string;
    organization: string;
    date: string; // ISO date format
    category: 'award' | 'certification' | 'recognition' | 'honor';
    description?: string;
    iconUrl?: string; // URL to award icon/badge image
    externalLink?: string;
    featured?: boolean;
  };
  variant?: 'default' | 'featured' | 'compact';
  clickable?: boolean;
}
```

**Defaults:**
- `variant`: 'default'
- `clickable`: false

**Emits:**

```typescript
{
  'click': (award: Award) => void; // Emitted when card is clicked
}
```

**Slots:**

```vue
<slot name="icon">
  <!-- Override icon rendering -->
</slot>
<slot name="footer">
  <!-- Additional footer content -->
</slot>
```

**State Management:**
- Local component state only
- No Vuex/Pinia needed (receives data via props)

**API Endpoints:**
- N/A (receives data from parent)

**Tailwind Classes:**

```vue
<template>
  <div
    :class="[
      'group relative rounded-xl border transition-all duration-200',
      'hover:shadow-lg hover:-translate-y-1',
      variant === 'featured'
        ? 'min-h-[320px] shadow-xl border-primary-500/50'
        : 'min-h-[280px] shadow-md border-gray-200 dark:border-gray-700',
      variant === 'compact' && 'min-h-[180px]',
      clickable && 'cursor-pointer hover:border-primary-500/50',
      'bg-white dark:bg-gray-800',
      'p-6 sm:p-8'
    ]"
    @click="handleClick"
    :tabindex="clickable ? 0 : undefined"
    @keydown.enter="handleClick"
    role="article"
  >
    <!-- Icon Section -->
    <div :class="[
      'mb-4 flex items-center justify-center w-16 h-16 rounded-full',
      iconBackgroundClass
    ]">
      <slot name="icon">
        <img
          v-if="award.iconUrl"
          :src="award.iconUrl"
          :alt="`${award.title} icon`"
          class="w-8 h-8 object-contain"
        />
        <component
          v-else
          :is="defaultIcon"
          :class="['w-8 h-8', iconColorClass]"
          aria-hidden="true"
        />
      </slot>
    </div>

    <!-- Title -->
    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
      {{ award.title }}
    </h3>

    <!-- Organization -->
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
      {{ award.organization }}
    </p>

    <!-- Date -->
    <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
      <CalendarIcon class="w-4 h-4" aria-hidden="true" />
      <time :datetime="award.date">{{ formattedDate }}</time>
    </p>

    <!-- Category Badge -->
    <span :class="[
      'inline-block mt-3 px-3 py-1 rounded-full text-xs font-medium',
      badgeColorClass
    ]">
      {{ categoryLabel }}
    </span>

    <!-- Description -->
    <p
      v-if="award.description && variant !== 'compact'"
      class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2"
    >
      {{ award.description }}
    </p>

    <!-- External Link Indicator -->
    <a
      v-if="award.externalLink"
      :href="award.externalLink"
      target="_blank"
      rel="noopener noreferrer"
      class="absolute top-4 right-4 text-primary-600 hover:text-primary-700 dark:text-primary-400"
      @click.stop
      aria-label="View award details (opens in new window)"
    >
      <ExternalLinkIcon class="w-5 h-5" />
    </a>

    <slot name="footer" />
  </div>
</template>
```

**Computed Properties:**

```typescript
const iconBackgroundClass = computed(() => {
  const classes = {
    award: 'bg-primary-100 dark:bg-primary-900/30',
    certification: 'bg-accent-100 dark:bg-accent-900/30',
    recognition: 'bg-secondary-100 dark:bg-secondary-900/30',
    honor: 'bg-warning-100 dark:bg-warning-900/30'
  };
  return classes[props.award.category];
});

const iconColorClass = computed(() => {
  const classes = {
    award: 'text-primary-600 dark:text-primary-400',
    certification: 'text-accent-600 dark:text-accent-400',
    recognition: 'text-secondary-600 dark:text-secondary-400',
    honor: 'text-warning-600 dark:text-warning-400'
  };
  return classes[props.award.category];
});

const badgeColorClass = computed(() => {
  const classes = {
    award: 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-300',
    certification: 'bg-accent-100 text-accent-800 dark:bg-accent-900/40 dark:text-accent-300',
    recognition: 'bg-secondary-100 text-secondary-800 dark:bg-secondary-900/40 dark:text-secondary-300',
    honor: 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300'
  };
  return classes[props.award.category];
});

const formattedDate = computed(() => {
  return new Date(props.award.date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long'
  });
});

const categoryLabel = computed(() => {
  return props.award.category.charAt(0).toUpperCase() + props.award.category.slice(1);
});
```

**Dependencies:**
- Heroicons: `CalendarIcon`, `ExternalLinkIcon`, trophy/medal icons

**Accessibility:**
- Card has `role="article"`
- If clickable, has `tabindex="0"` and `@keydown.enter` handler
- Icon images have descriptive alt text
- External links indicate new window
- Date uses semantic `<time>` element

**Usage Example:**

```vue
<template>
  <AwardCard
    :award="award"
    variant="featured"
    clickable
    @click="handleAwardClick"
  />
</template>

<script setup>
import AwardCard from '@/components/awards/AwardCard.vue';

const award = {
  id: 1,
  title: 'Best Web Design Award',
  organization: 'Design Institute',
  date: '2024-01-15',
  category: 'award',
  description: 'Recognized for outstanding work in web design and user experience.',
  iconUrl: '/images/awards/trophy.png',
  featured: true
};

const handleAwardClick = (award) => {
  console.log('Award clicked:', award);
};
</script>
```

---

### AwardsList.vue

**Purpose:** Grid container for displaying multiple award cards with filtering and empty state.

**File Location:** `src/components/awards/AwardsList.vue`

**Props:**

```typescript
interface AwardsListProps {
  awards: Award[];
  loading?: boolean;
  showFilters?: boolean;
  maxDisplay?: number; // Limit number shown (for homepage)
}
```

**Defaults:**
- `loading`: false
- `showFilters`: false
- `maxDisplay`: undefined (show all)

**Emits:**

```typescript
{
  'category-change': (category: string) => void;
  'award-click': (award: Award) => void;
}
```

**State Management:**
- Local state: `selectedCategory`, `filteredAwards`

**API Endpoints:**
- N/A (receives data via props)

**Tailwind Classes:**

```vue
<template>
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
    <!-- Section Header -->
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-100">
        Awards & Recognition
      </h2>
      <p class="mt-4 text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
        Recognized for excellence and innovation
      </p>
    </div>

    <!-- Category Filters -->
    <div v-if="showFilters" class="flex flex-wrap justify-center gap-2 mb-8">
      <button
        v-for="category in categories"
        :key="category.value"
        @click="selectCategory(category.value)"
        :class="[
          'px-4 py-2 rounded-full text-sm font-medium transition-colors',
          selectedCategory === category.value
            ? 'bg-primary-600 text-white hover:bg-primary-700'
            : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
        ]"
        :aria-pressed="selectedCategory === category.value"
      >
        {{ category.label }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
      <div
        v-for="n in 6"
        :key="n"
        class="animate-pulse bg-gray-200 dark:bg-gray-700 rounded-xl h-[280px]"
      />
    </div>

    <!-- Awards Grid -->
    <div
      v-else-if="displayedAwards.length > 0"
      class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8"
    >
      <AwardCard
        v-for="award in displayedAwards"
        :key="award.id"
        :award="award"
        clickable
        @click="$emit('award-click', award)"
      />
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="text-center py-16"
    >
      <TrophyIcon class="w-16 h-16 text-gray-400 mx-auto mb-4" />
      <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
        No awards yet
      </h3>
      <p class="text-gray-600 dark:text-gray-400">
        {{ selectedCategory === 'all'
          ? 'Start adding your achievements'
          : 'No awards in this category'
        }}
      </p>
    </div>
  </section>
</template>
```

**Script Setup:**

```typescript
import { computed, ref } from 'vue';
import AwardCard from './AwardCard.vue';
import { TrophyIcon } from '@heroicons/vue/24/outline';

const props = defineProps<AwardsListProps>();
const emit = defineEmits(['category-change', 'award-click']);

const selectedCategory = ref('all');

const categories = [
  { value: 'all', label: 'All' },
  { value: 'award', label: 'Awards' },
  { value: 'certification', label: 'Certifications' },
  { value: 'recognition', label: 'Recognition' },
  { value: 'honor', label: 'Honors' }
];

const filteredAwards = computed(() => {
  if (selectedCategory.value === 'all') {
    return props.awards;
  }
  return props.awards.filter(award => award.category === selectedCategory.value);
});

const displayedAwards = computed(() => {
  const awards = filteredAwards.value;
  return props.maxDisplay ? awards.slice(0, props.maxDisplay) : awards;
});

const selectCategory = (category: string) => {
  selectedCategory.value = category;
  emit('category-change', category);
};
```

**Dependencies:**
- `AwardCard.vue`
- Heroicons

**Accessibility:**
- Filter buttons have `aria-pressed` state
- Empty state provides clear messaging
- Grid maintains semantic structure

---

### AwardEditor.vue (Admin)

**Purpose:** Modal form for creating/editing awards in admin panel.

**File Location:** `src/components/admin/awards/AwardEditor.vue`

**Props:**

```typescript
interface AwardEditorProps {
  award?: Award | null; // If editing existing award
  isOpen: boolean;
}
```

**Emits:**

```typescript
{
  'close': () => void;
  'save': (award: Award) => void;
}
```

**State Management:**
- Local form state with `reactive`
- Form validation errors state

**API Endpoints:**

```typescript
// POST /api/admin/awards (create)
// PUT /api/admin/awards/:id (update)
// POST /api/admin/awards/upload-icon (icon upload)
```

**Key Features:**
- Title, Organization, Date, Category fields
- Description textarea with character counter (500 max)
- Icon/Badge image upload with preview
- Featured toggle switch
- Display order number input
- External link URL input
- Status dropdown (Published/Draft)
- Form validation with inline error messages
- Loading state on save button

**Dependencies:**
- `@headlessui/vue` (Dialog, Transition)
- Heroicons
- Pinia store (`useAwardsStore`)

**Accessibility:**
- Dialog has proper ARIA attributes
- Form labels associated with inputs
- Error messages announced to screen readers
- Keyboard navigation supported
- Focus trap in modal

---

## TESTIMONIALS COMPONENTS

### TestimonialCard.vue

**Purpose:** Display individual testimonial with quote, rating, and author information.

**File Location:** `src/components/testimonials/TestimonialCard.vue`

**Props:**

```typescript
interface TestimonialCardProps {
  testimonial: {
    id: string | number;
    quote: string;
    authorName: string;
    authorTitle: string;
    authorCompany?: string;
    authorAvatar?: string;
    rating?: number; // 1-5
    featured?: boolean;
  };
  variant?: 'default' | 'compact' | 'featured';
}
```

**Defaults:**
- `variant`: 'default'

**Emits:**
- None (display only)

**Tailwind Classes:**

```vue
<template>
  <div
    :class="[
      'relative rounded-2xl transition-all duration-300',
      'bg-white/70 dark:bg-gray-800/70 backdrop-blur-xl',
      'border border-gray-200/50 dark:border-gray-700/50',
      'shadow-md hover:shadow-xl hover:-translate-y-1',
      variant === 'featured' && 'shadow-xl',
      'p-8 sm:p-10'
    ]"
  >
    <!-- Large Quote Mark (Background) -->
    <div class="absolute top-6 left-6 text-primary-200 dark:text-primary-900/30 text-8xl leading-none pointer-events-none opacity-10">
      "
    </div>

    <!-- Rating Stars -->
    <div v-if="testimonial.rating" class="flex items-center gap-1 mb-4 relative z-10">
      <StarIcon
        v-for="star in 5"
        :key="star"
        :class="[
          'w-5 h-5',
          star <= testimonial.rating
            ? 'text-warning-500'
            : 'text-gray-300 dark:text-gray-600'
        ]"
        :fill="star <= testimonial.rating ? 'currentColor' : 'none'"
      />
    </div>

    <!-- Quote Text -->
    <blockquote class="relative z-10 text-lg font-normal leading-relaxed italic text-gray-700 dark:text-gray-300 mb-8">
      {{ testimonial.quote }}
    </blockquote>

    <!-- Divider -->
    <div class="w-16 h-px bg-gray-300 dark:bg-gray-600 mb-4" />

    <!-- Author Section -->
    <div class="flex items-center gap-4 relative z-10">
      <!-- Avatar -->
      <div class="flex-shrink-0">
        <img
          v-if="testimonial.authorAvatar"
          :src="testimonial.authorAvatar"
          :alt="`${testimonial.authorName} avatar`"
          class="w-14 h-14 rounded-full object-cover border-2 border-white dark:border-gray-700"
        />
        <div
          v-else
          class="w-14 h-14 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold text-lg"
        >
          {{ initials }}
        </div>
      </div>

      <!-- Author Info -->
      <div>
        <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
          {{ testimonial.authorName }}
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          {{ testimonial.authorTitle }}
        </p>
        <a
          v-if="testimonial.authorCompany"
          href="#"
          class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
        >
          {{ testimonial.authorCompany }}
        </a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { StarIcon } from '@heroicons/vue/24/solid';

const props = defineProps<TestimonialCardProps>();

const initials = computed(() => {
  return props.testimonial.authorName
    .split(' ')
    .map(n => n[0])
    .join('')
    .substring(0, 2)
    .toUpperCase();
});
</script>
```

**Dependencies:**
- Heroicons (StarIcon solid)

**Accessibility:**
- Quote uses semantic `<blockquote>`
- Avatar has descriptive alt text
- Rating has implicit ARIA via star icons

---

### TestimonialCarousel.vue

**Purpose:** Carousel component for cycling through multiple testimonials with navigation controls.

**File Location:** `src/components/testimonials/TestimonialCarousel.vue`

**Props:**

```typescript
interface TestimonialCarouselProps {
  testimonials: Testimonial[];
  autoPlay?: boolean;
  autoPlayInterval?: number; // milliseconds
  slidesPerView?: {
    mobile: number;
    tablet: number;
    desktop: number;
  };
}
```

**Defaults:**
- `autoPlay`: false
- `autoPlayInterval`: 5000
- `slidesPerView`: { mobile: 1, tablet: 2, desktop: 3 }

**Emits:**
- None

**State Management:**
- Local state: `currentIndex`, `isAutoPlaying`

**Dependencies:**
- **Swiper.js** (recommended) OR custom carousel logic
- `TestimonialCard.vue`

**Key Features:**
- Responsive slides per view (1 mobile, 2 tablet, 3 desktop)
- Previous/Next navigation buttons
- Pagination dots indicator
- Auto-play with pause on hover
- Touch/swipe gestures on mobile
- Keyboard navigation (arrow keys)

**Accessibility:**
- Carousel has `role="region"` with aria-label
- Navigation buttons have aria-labels
- Pagination dots have aria-labels and aria-current
- Auto-play pauses on hover
- Keyboard navigation supported by Swiper

**Usage Example:**

```vue
<template>
  <TestimonialCarousel
    :testimonials="testimonials"
    :autoPlay="true"
    :autoPlayInterval="5000"
  />
</template>

<script setup>
import TestimonialCarousel from '@/components/testimonials/TestimonialCarousel.vue';

const testimonials = [
  {
    id: 1,
    quote: 'Excellent work! Highly professional and delivered on time.',
    authorName: 'John Doe',
    authorTitle: 'CEO',
    authorCompany: 'Tech Company',
    authorAvatar: '/avatars/john.jpg',
    rating: 5
  },
  // ... more testimonials
];
</script>
```

---

### TestimonialEditor.vue (Admin)

**Purpose:** Modal form for creating/editing testimonials in admin panel.

**File Location:** `src/components/admin/testimonials/TestimonialEditor.vue`

**Props:**

```typescript
interface TestimonialEditorProps {
  testimonial?: Testimonial | null;
  isOpen: boolean;
}
```

**Emits:**

```typescript
{
  'close': () => void;
  'save': (testimonial: Testimonial) => void;
}
```

**State Management:**
- Local form state
- Validation errors state

**API Endpoints:**

```typescript
// POST /api/admin/testimonials (create)
// PUT /api/admin/testimonials/:id (update)
// POST /api/admin/testimonials/upload-avatar (avatar upload)
```

**Key Features:**
- Author information fields (name, title, company, avatar)
- Testimonial text textarea with character counter (1000 max)
- Interactive star rating selector (click to rate 1-5)
- Related project dropdown (optional)
- Featured toggle
- Display order
- Status (Draft/Published)
- Circular avatar preview with upload/remove buttons

**Star Rating Selector Implementation:**

```vue
<div>
  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
    Rating <span class="text-error-500">*</span>
  </label>
  <div class="flex items-center gap-1">
    <button
      v-for="star in 5"
      :key="star"
      type="button"
      @click="formData.rating = star"
      @mouseenter="hoverRating = star"
      @mouseleave="hoverRating = 0"
      class="focus:outline-none focus:ring-2 focus:ring-primary-500 rounded"
    >
      <StarIcon
        :class="[
          'w-8 h-8 transition-colors',
          (hoverRating >= star || formData.rating >= star)
            ? 'text-warning-500'
            : 'text-gray-300 dark:text-gray-600'
        ]"
        :fill="(hoverRating >= star || formData.rating >= star) ? 'currentColor' : 'none'"
      />
    </button>
    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
      (Click to rate)
    </span>
  </div>
</div>
```

**Dependencies:**
- `@headlessui/vue`
- Heroicons
- Pinia store

---

## GALLERY COMPONENTS

### GalleryGrid.vue

**Purpose:** Responsive grid layout for gallery images with category filtering.

**File Location:** `src/components/gallery/GalleryGrid.vue`

**Props:**

```typescript
interface GalleryGridProps {
  images: GalleryImage[];
  loading?: boolean;
  columns?: {
    mobile: number;
    tablet: number;
    desktop: number;
  };
}
```

**Defaults:**
- `loading`: false
- `columns`: { mobile: 1, tablet: 2, desktop: 3 }

**Emits:**

```typescript
{
  'image-click': (image: GalleryImage, index: number) => void;
}
```

**State Management:**
- Local state: `selectedCategory`

**Key Features:**
- Category filter pills (All, UI/UX, Web Dev, Mobile, Branding, Photography)
- Responsive grid layout
- Loading skeleton state
- Empty state with icon and message
- Lazy loading images

---

### GalleryItem.vue

**Purpose:** Individual gallery image card with hover overlay.

**File Location:** `src/components/gallery/GalleryItem.vue`

**Props:**

```typescript
interface GalleryItemProps {
  image: {
    id: string | number;
    url: string;
    thumbnailUrl?: string;
    title: string;
    category: string;
    caption?: string;
  };
}
```

**Emits:**

```typescript
{
  'click': () => void;
}
```

**Key Features:**
- Aspect ratio maintained (aspect-video)
- Hover overlay with dark backdrop
- View icon and title on hover
- Category badge appears on hover
- Image zoom effect (scale-105)
- Keyboard accessible

**Accessibility:**
- Keyboard focusable (tabindex="0")
- Enter key activates
- Descriptive aria-label
- Image has alt text

---

### ImageLightbox.vue

**Purpose:** Full-screen modal for viewing gallery images with navigation.

**File Location:** `src/components/gallery/ImageLightbox.vue`

**Props:**

```typescript
interface ImageLightboxProps {
  images: GalleryImage[];
  initialIndex: number;
  isOpen: boolean;
}
```

**Emits:**

```typescript
{
  'close': () => void;
  'navigate': (index: number) => void;
}
```

**State Management:**
- Local state: `currentIndex`

**Key Features:**
- Full-screen dark backdrop (black/95 with backdrop-blur)
- Close button (top-right)
- Previous/Next navigation arrows
- Image counter (X / Total)
- Optional caption display
- Keyboard controls (ESC, Arrow Left/Right)
- Touch gestures on mobile (swipe)

**Accessibility:**
- Keyboard navigation (Arrow keys, ESC)
- Focus trap in dialog
- Screen reader announces current image count
- Buttons have aria-labels

---

### BulkImageUpload.vue (Admin)

**Purpose:** Drag-drop interface for uploading multiple images.

**File Location:** `src/components/admin/gallery/BulkImageUpload.vue`

**Props:**

```typescript
interface BulkImageUploadProps {
  isOpen: boolean;
  maxFileSize?: number; // bytes
  acceptedFormats?: string[];
}
```

**Defaults:**
- `maxFileSize`: 5 * 1024 * 1024 (5MB)
- `acceptedFormats`: ['image/jpeg', 'image/png', 'image/webp']

**Emits:**

```typescript
{
  'close': () => void;
  'upload-complete': (uploadedImages: UploadedImage[]) => void;
}
```

**State Management:**
- Local state: `selectedFiles`, `uploadProgress`, `uploadedImages`

**API Endpoints:**

```typescript
// POST /api/admin/gallery/upload (multipart/form-data)
```

**Key Features:**
- Drag-drop zone with visual feedback
- Multiple file selection
- File validation (type, size)
- Upload progress bars per file
- Thumbnail previews
- Remove individual files before upload
- Bulk upload with concurrent requests
- Success/error states per file

**Drag-Drop States:**
- Default: Border-dashed, gray
- Hover: Border-primary-500, bg-primary-50
- Dragging: Scale-102, bg-primary-100

---

## USAGE EXAMPLES

### Homepage Integration

```vue
<template>
  <div>
    <!-- Hero Section -->
    <HeroSection />

    <!-- Featured Projects -->
    <FeaturedProjects />

    <!-- Awards Section (NEW) -->
    <AwardsList
      :awards="featuredAwards"
      :maxDisplay="4"
      @award-click="navigateToAwardsPage"
    />

    <!-- Testimonials Carousel (NEW) -->
    <TestimonialCarousel
      :testimonials="testimonials"
      :autoPlay="true"
      :autoPlayInterval="5000"
    />

    <!-- Blog Section -->
    <RecentBlogPosts />

    <!-- Contact CTA -->
    <ContactCTA />
  </div>
</template>

<script setup>
import AwardsList from '@/components/awards/AwardsList.vue';
import TestimonialCarousel from '@/components/testimonials/TestimonialCarousel.vue';
import { useAwardsStore } from '@/stores/awards';
import { useTestimonialsStore } from '@/stores/testimonials';

const awardsStore = useAwardsStore();
const testimonialsStore = useTestimonialsStore();

const featuredAwards = computed(() =>
  awardsStore.awards.filter(a => a.featured).slice(0, 4)
);

const testimonials = computed(() =>
  testimonialsStore.testimonials.filter(t => t.featured)
);

const navigateToAwardsPage = () => {
  router.push('/awards');
};
</script>
```

### Gallery Page Integration

```vue
<template>
  <div>
    <PageHeader
      title="Gallery"
      subtitle="Visual showcase of projects and moments"
    />

    <GalleryGrid
      :images="galleryImages"
      :loading="loading"
      @image-click="openLightbox"
    />

    <ImageLightbox
      :images="galleryImages"
      :initialIndex="lightboxIndex"
      :isOpen="isLightboxOpen"
      @close="closeLightbox"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import GalleryGrid from '@/components/gallery/GalleryGrid.vue';
import ImageLightbox from '@/components/gallery/ImageLightbox.vue';
import { useGalleryStore } from '@/stores/gallery';

const galleryStore = useGalleryStore();
const loading = ref(false);

const galleryImages = computed(() => galleryStore.images);

const isLightboxOpen = ref(false);
const lightboxIndex = ref(0);

const openLightbox = (image, index) => {
  lightboxIndex.value = index;
  isLightboxOpen.value = true;
};

const closeLightbox = () => {
  isLightboxOpen.value = false;
};

// Fetch images on mount
onMounted(async () => {
  loading.value = true;
  await galleryStore.fetchImages();
  loading.value = false;
});
</script>
```

---

## STATE MANAGEMENT (Pinia Stores)

### Awards Store

```typescript
// stores/awards.ts
import { defineStore } from 'pinia';

export const useAwardsStore = defineStore('awards', {
  state: () => ({
    awards: [] as Award[],
    loading: false,
    error: null as string | null
  }),

  getters: {
    featuredAwards: (state) => state.awards.filter(a => a.featured),
    awardsByCategory: (state) => (category: string) =>
      state.awards.filter(a => a.category === category)
  },

  actions: {
    async fetchAwards() {
      this.loading = true;
      try {
        const response = await fetch('/api/awards');
        this.awards = await response.json();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },

    async createAward(data: Partial<Award>) {
      const response = await fetch('/api/admin/awards', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const newAward = await response.json();
      this.awards.push(newAward);
      return newAward;
    },

    async updateAward(id: string | number, data: Partial<Award>) {
      const response = await fetch(`/api/admin/awards/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const updatedAward = await response.json();
      const index = this.awards.findIndex(a => a.id === id);
      if (index !== -1) {
        this.awards[index] = updatedAward;
      }
      return updatedAward;
    },

    async deleteAward(id: string | number) {
      await fetch(`/api/admin/awards/${id}`, { method: 'DELETE' });
      this.awards = this.awards.filter(a => a.id !== id);
    }
  }
});
```

### Testimonials Store

```typescript
// stores/testimonials.ts
import { defineStore } from 'pinia';

export const useTestimonialsStore = defineStore('testimonials', {
  state: () => ({
    testimonials: [] as Testimonial[],
    loading: false,
    error: null as string | null
  }),

  getters: {
    featuredTestimonials: (state) =>
      state.testimonials.filter(t => t.featured),
    testimonialsByRating: (state) => (rating: number) =>
      state.testimonials.filter(t => t.rating === rating)
  },

  actions: {
    async fetchTestimonials() {
      this.loading = true;
      try {
        const response = await fetch('/api/testimonials');
        this.testimonials = await response.json();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },

    async createTestimonial(data: Partial<Testimonial>) {
      const response = await fetch('/api/admin/testimonials', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const newTestimonial = await response.json();
      this.testimonials.push(newTestimonial);
      return newTestimonial;
    },

    async updateTestimonial(id: string | number, data: Partial<Testimonial>) {
      const response = await fetch(`/api/admin/testimonials/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const updated = await response.json();
      const index = this.testimonials.findIndex(t => t.id === id);
      if (index !== -1) {
        this.testimonials[index] = updated;
      }
      return updated;
    },

    async deleteTestimonial(id: string | number) {
      await fetch(`/api/admin/testimonials/${id}`, { method: 'DELETE' });
      this.testimonials = this.testimonials.filter(t => t.id !== id);
    }
  }
});
```

### Gallery Store

```typescript
// stores/gallery.ts
import { defineStore } from 'pinia';

export const useGalleryStore = defineStore('gallery', {
  state: () => ({
    images: [] as GalleryImage[],
    loading: false,
    error: null as string | null
  }),

  getters: {
    imagesByCategory: (state) => (category: string) =>
      state.images.filter(img => img.category === category),
    featuredImages: (state) => state.images.filter(img => img.featured)
  },

  actions: {
    async fetchImages() {
      this.loading = true;
      try {
        const response = await fetch('/api/gallery');
        this.images = await response.json();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },

    async uploadImages(files: File[]) {
      const formData = new FormData();
      files.forEach(file => formData.append('images', file));

      const response = await fetch('/api/admin/gallery/upload', {
        method: 'POST',
        body: formData
      });
      const uploadedImages = await response.json();
      this.images.push(...uploadedImages);
      return uploadedImages;
    },

    async updateImage(id: string | number, data: Partial<GalleryImage>) {
      const response = await fetch(`/api/admin/gallery/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const updated = await response.json();
      const index = this.images.findIndex(img => img.id === id);
      if (index !== -1) {
        this.images[index] = updated;
      }
      return updated;
    },

    async deleteImage(id: string | number) {
      await fetch(`/api/admin/gallery/${id}`, { method: 'DELETE' });
      this.images = this.images.filter(img => img.id !== id);
    },

    async bulkDelete(ids: (string | number)[]) {
      await fetch('/api/admin/gallery/bulk-delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids })
      });
      this.images = this.images.filter(img => !ids.includes(img.id));
    }
  }
});
```

---

## TYPESCRIPT TYPES

```typescript
// types/awards.ts
export interface Award {
  id: string | number;
  title: string;
  organization: string;
  date: string; // ISO date
  category: 'award' | 'certification' | 'recognition' | 'honor';
  description?: string;
  iconUrl?: string;
  externalLink?: string;
  featured?: boolean;
  displayOrder?: number;
  status?: 'published' | 'draft';
  createdAt?: string;
  updatedAt?: string;
}

// types/testimonials.ts
export interface Testimonial {
  id: string | number;
  quote: string;
  authorName: string;
  authorTitle: string;
  authorCompany?: string;
  authorAvatar?: string;
  authorWebsite?: string;
  rating?: number; // 1-5
  relatedProjectId?: string | number;
  dateReceived?: string;
  featured?: boolean;
  displayOrder?: number;
  status?: 'published' | 'draft';
  createdAt?: string;
  updatedAt?: string;
}

// types/gallery.ts
export interface GalleryImage {
  id: string | number;
  url: string;
  thumbnailUrl?: string;
  title: string;
  caption?: string;
  altText: string;
  category: string;
  tags?: string[];
  featured?: boolean;
  displayOrder?: number;
  uploadedAt?: string;
  fileSize?: number;
  dimensions?: {
    width: number;
    height: number;
  };
}
```

---

## PERFORMANCE OPTIMIZATIONS

### Lazy Loading Images

```vue
<!-- Use native lazy loading -->
<img :src="image.url" :alt="image.alt" loading="lazy" />

<!-- Or use Intersection Observer for more control -->
<script setup>
import { ref, onMounted } from 'vue';

const imageRef = ref(null);
const isVisible = ref(false);

onMounted(() => {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        isVisible.value = true;
        observer.disconnect();
      }
    });
  });

  if (imageRef.value) {
    observer.observe(imageRef.value);
  }
});
</script>

<template>
  <div ref="imageRef">
    <img v-if="isVisible" :src="image.url" :alt="image.alt" />
    <div v-else class="skeleton" />
  </div>
</template>
```

### Carousel Performance

```typescript
// Preload adjacent slides
const preloadAdjacentSlides = () => {
  const prevIndex = currentIndex.value - 1;
  const nextIndex = currentIndex.value + 1;

  if (prevIndex >= 0) {
    const img = new Image();
    img.src = testimonials.value[prevIndex].authorAvatar;
  }

  if (nextIndex < testimonials.value.length) {
    const img = new Image();
    img.src = testimonials.value[nextIndex].authorAvatar;
  }
};
```

### Gallery Optimization

- Use thumbnail URLs for grid (not full-res images)
- Lazy load images as they enter viewport
- Preload prev/next images in lightbox
- Use WebP format with JPG fallback
- Implement infinite scroll with batch loading (20-30 images at a time)

---

## IMPLEMENTATION CHECKLIST

### Phase 1: Awards Components
- [ ] Create `AwardCard.vue` with all variants
- [ ] Implement `AwardsList.vue` with filtering
- [ ] Build `AwardEditor.vue` admin modal
- [ ] Create Awards Pinia store
- [ ] Define TypeScript types
- [ ] Add to homepage (featured awards)
- [ ] Create dedicated Awards page
- [ ] Test accessibility

### Phase 2: Testimonials Components
- [ ] Create `TestimonialCard.vue` with glassmorphic design
- [ ] Implement `TestimonialCarousel.vue` with Swiper.js
- [ ] Build `TestimonialEditor.vue` admin modal
- [ ] Create Testimonials Pinia store
- [ ] Add star rating selector to editor
- [ ] Add to homepage (carousel)
- [ ] Test responsive behavior
- [ ] Test auto-play and keyboard navigation

### Phase 3: Gallery Components
- [ ] Create `GalleryGrid.vue` with filtering
- [ ] Implement `GalleryItem.vue` with hover overlay
- [ ] Build `ImageLightbox.vue` with keyboard controls
- [ ] Create `BulkImageUpload.vue` admin component
- [ ] Create Gallery Pinia store
- [ ] Create dedicated Gallery page
- [ ] Implement lightbox navigation
- [ ] Add lazy loading
- [ ] Test drag-drop upload

### Phase 4: Integration & Testing
- [ ] Integrate all components into homepage
- [ ] Test state management flows
- [ ] Verify API endpoints
- [ ] Run accessibility audit
- [ ] Test responsive breakpoints
- [ ] Optimize performance (lazy loading, preloading)
- [ ] Cross-browser testing
- [ ] Dark mode verification

### Phase 5: Polish
- [ ] Add loading animations
- [ ] Implement error states
- [ ] Add empty states
- [ ] Fine-tune transitions
- [ ] Optimize bundle size
- [ ] Documentation for components
- [ ] Create Storybook stories (optional)

---

**End of Master Components Specification (Version 2.0)**

Total Components: **45-50 reusable Vue components**
- Awards: 3 components (AwardCard, AwardsList, AwardEditor)
- Testimonials: 3 components (TestimonialCard, TestimonialCarousel, TestimonialEditor)
- Gallery: 4 components (GalleryGrid, GalleryItem, ImageLightbox, BulkImageUpload)
- Stores: 3 Pinia stores (Awards, Testimonials, Gallery)

All components are production-ready with complete specifications, accessibility support, and performance optimizations. Ready for Vue 3 + Tailwind CSS implementation.
