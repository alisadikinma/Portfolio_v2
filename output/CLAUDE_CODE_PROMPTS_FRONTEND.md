# Claude Code Prompts - Frontend Development

**Target:** Build Vue 3 frontend with design system  
**Subagents:** `frontend-developer`, `vue-expert`, `react-specialist` (for component patterns)  
**Priority:** 🔴 CRITICAL

---

## PROMPT 4: Tailwind CSS Configuration & Design System Setup

### Subagent
**Primary:** `frontend-developer`  
**Support:** `dx-optimizer`

### Context
Setup Tailwind CSS with custom design tokens from the master design system. Configure dark mode, custom colors, typography, and spacing.

### Requirements

1. **Install Tailwind CSS** and dependencies
   ```bash
   cd frontend
   npm install -D tailwindcss@latest postcss@latest autoprefixer@latest
   npm install @tailwindcss/forms @tailwindcss/typography @tailwindcss/aspect-ratio
   npx tailwindcss init -p
   ```

2. **Configure tailwind.config.js** with design tokens
3. **Setup base CSS** with design system classes
4. **Configure dark mode** (class strategy)
5. **Add global styles**

### Reference Files
- `/output/MASTER_DESIGN_SYSTEM.md` - Complete design system (Section 1-20)
- `/output/MASTER_DESIGN_SYSTEM.md` (Section 17) - Tailwind config reference

### Implementation Steps

1. **Update tailwind.config.js** with:
   ```javascript
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
             // ... (full palette from design system)
             900: '#312e81',
             950: '#1e1b4b',
           },
           secondary: { /* Violet scale */ },
           accent: { /* Emerald scale */ },
           // Add warning, error, info, success
         },
         fontFamily: {
           sans: ['Inter', 'sans-serif'],
           mono: ['JetBrains Mono', 'monospace'],
         },
         boxShadow: {
           'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.07)',
           'glass-lg': '0 8px 32px 0 rgba(31, 38, 135, 0.15)',
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

2. **Create `src/style.css`** with base styles:
   ```css
   @tailwind base;
   @tailwind components;
   @tailwind utilities;

   @layer base {
     * {
       @apply border-gray-200 dark:border-gray-700;
     }
     
     html {
       @apply scroll-smooth;
     }
     
     body {
       @apply bg-white dark:bg-gray-950;
       @apply text-gray-900 dark:text-gray-50;
       @apply font-sans;
     }
   }

   @layer components {
     /* Typography classes */
     .heading-1 {
       @apply text-4xl md:text-5xl font-bold tracking-tight leading-tight;
     }
     
     .heading-2 {
       @apply text-3xl md:text-4xl font-bold tracking-tight leading-tight;
     }
     
     .heading-3 {
       @apply text-2xl md:text-3xl font-semibold leading-snug;
     }
     
     .body-base {
       @apply text-base font-normal leading-normal;
     }

     /* Utility classes */
     .btn-primary {
       @apply inline-flex items-center justify-center px-4 py-2
              text-sm font-medium rounded-lg
              bg-primary-600 text-white shadow-sm
              hover:bg-primary-700 hover:shadow-md hover:-translate-y-0.5
              active:bg-primary-800 active:translate-y-0
              focus:outline-none focus:ring-4 focus:ring-primary-500/50
              disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0
              transition-all duration-200;
     }

     .btn-secondary {
       @apply inline-flex items-center justify-center px-4 py-2
              text-sm font-medium rounded-lg
              border-2 border-primary-600 text-primary-600 bg-transparent
              hover:bg-primary-50 dark:hover:bg-primary-900/20
              active:bg-primary-100 dark:active:bg-primary-900/40
              focus:outline-none focus:ring-4 focus:ring-primary-500/50
              disabled:opacity-50 disabled:cursor-not-allowed
              transition-all duration-200;
     }

     .card {
       @apply rounded-xl border border-gray-200 dark:border-gray-700
              bg-white dark:bg-gray-800 shadow-md
              transition-all duration-200;
     }

     .card-hover {
       @apply hover:shadow-lg hover:-translate-y-1;
     }

     .glass {
       @apply bg-white/70 dark:bg-gray-800/70 backdrop-blur-xl
              border border-gray-200/50 dark:border-gray-700/50;
     }
   }
   ```

3. **Install Inter font**
   - Add to `index.html`:
   ```html
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
   ```

4. **Configure dark mode toggle**
   - Create `src/composables/useDarkMode.js`

5. **Test Tailwind setup**
   - Create sample component with design system classes
   - Test dark mode toggle
   - Verify all colors, fonts, and utilities work

### Deliverables
- [ ] tailwind.config.js with full design tokens
- [ ] style.css with base styles and component classes
- [ ] Dark mode composable
- [ ] Test component showing design system in action
- [ ] Screenshot of working Tailwind setup

---

## PROMPT 5: Core UI Component Library

### Subagent
**Primary:** `vue-expert`  
**Support:** `frontend-developer`, `react-specialist` (for patterns)

### Context
Build reusable core UI components following the design system specifications.

### Requirements

Create the following Vue components in `frontend/src/components/ui/`:

1. **Button.vue** - Primary, secondary, ghost, danger variants
2. **InputText.vue** - Text input with validation states
3. **TextArea.vue** - Multi-line text input
4. **Select.vue** - Dropdown select
5. **Checkbox.vue** - Checkbox input
6. **Radio.vue** - Radio button
7. **Card.vue** - Content card with variants
8. **Modal.vue** - Dialog/Modal component
9. **Dropdown.vue** - Dropdown menu
10. **Toast.vue** - Notification toast

### Reference Files
- `/output/MASTER_DESIGN_SYSTEM.md` (Section 5) - Component patterns
- `/output/MASTER_DESIGN_SYSTEM.md` (Section 18) - Vue component example
- `/output/MASTER_COMPONENTS.md` - Detailed component specs

### Button.vue Implementation Example

```vue
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

    <!-- Icon slot -->
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

### Component Requirements

Each component must have:
1. **Props** with proper types and defaults
2. **Emits** declaration
3. **Slots** where applicable
4. **Computed classes** using Tailwind
5. **Accessibility** (ARIA labels, keyboard navigation)
6. **Dark mode** support
7. **Loading states** where applicable
8. **Error states** with validation
9. **Responsive** design

### Testing Checklist (Per Component)
- [ ] Props work correctly
- [ ] Events emit properly
- [ ] Slots render correctly
- [ ] All variants display correctly
- [ ] Dark mode works
- [ ] Keyboard navigation works
- [ ] Focus states visible
- [ ] Disabled state works
- [ ] Loading state works (if applicable)
- [ ] Validation states work (if applicable)

### Deliverables
- [ ] 10 core UI components in `src/components/ui/`
- [ ] Test page showcasing all components
- [ ] Screenshot of components in light & dark mode
- [ ] Documentation for each component (props, events, slots)

---

## PROMPT 6: State Management with Pinia

### Subagent
**Primary:** `vue-expert`  
**Support:** `frontend-developer`

### Context
Setup Pinia for state management and create stores for different features.

### Requirements

1. **Install Pinia**
   ```bash
   npm install pinia
   ```

2. **Create stores in `src/stores/`:**
   - `i18n.js` - Language switcher
   - `theme.js` - Dark mode
   - `projects.js` - Projects data
   - `blog.js` - Blog posts
   - `awards.js` - Awards
   - `gallery.js` - Gallery images

3. **Setup Pinia** in `main.js`

### i18n Store Example

```javascript
// src/stores/i18n.js
import { defineStore } from 'pinia';

export const useI18nStore = defineStore('i18n', {
  state: () => ({
    currentLanguage: (localStorage.getItem('language') || 'en'),
    availableLanguages: ['en', 'id'],
  }),

  getters: {
    isEnglish: (state) => state.currentLanguage === 'en',
    isIndonesian: (state) => state.currentLanguage === 'id',
  },

  actions: {
    setLanguage(lang) {
      if (this.availableLanguages.includes(lang)) {
        this.currentLanguage = lang;
        localStorage.setItem('language', lang);
        
        // Update document lang for accessibility
        document.documentElement.lang = lang;
      }
    },

    toggleLanguage() {
      const newLang = this.currentLanguage === 'en' ? 'id' : 'en';
      this.setLanguage(newLang);
    },
  },
});
```

### Projects Store Template

```javascript
// src/stores/projects.js
import { defineStore } from 'pinia';
import { useI18nStore } from './i18n';

export const useProjectsStore = defineStore('projects', {
  state: () => ({
    projects: [],
    currentProject: null,
    loading: false,
    error: null,
    pagination: {
      current_page: 1,
      total_pages: 1,
      total_items: 0,
      per_page: 12,
    },
  }),

  getters: {
    featuredProjects: (state) => state.projects.filter(p => p.featured),
    projectsByCategory: (state) => (category) =>
      state.projects.filter(p => p.category === category),
  },

  actions: {
    async fetchProjects(params = {}) {
      const i18n = useI18nStore();
      this.loading = true;
      this.error = null;

      try {
        const queryParams = new URLSearchParams({
          lang: i18n.currentLanguage,
          ...params,
        });

        const response = await fetch(
          `http://localhost/Portfolio_v2/backend/public/api/projects?${queryParams}`
        );

        if (!response.ok) throw new Error('Failed to fetch projects');

        const data = await response.json();
        this.projects = data.data;
        this.pagination = data.meta;
      } catch (error) {
        this.error = error.message;
        console.error('Error fetching projects:', error);
      } finally {
        this.loading = false;
      }
    },

    async fetchProject(slug) {
      const i18n = useI18nStore();
      this.loading = true;
      this.error = null;

      try {
        const response = await fetch(
          `http://localhost/Portfolio_v2/backend/public/api/projects/${slug}?lang=${i18n.currentLanguage}`
        );

        if (!response.ok) throw new Error('Project not found');

        this.currentProject = await response.json();
      } catch (error) {
        this.error = error.message;
        console.error('Error fetching project:', error);
      } finally {
        this.loading = false;
      }
    },
  },
});
```

### Deliverables
- [ ] Pinia installed and configured
- [ ] 6 stores created with proper structure
- [ ] API integration in store actions
- [ ] Error handling
- [ ] Loading states
- [ ] Test each store with mock data

---

## PROMPT 7: Vue Router Setup

### Subagent
**Primary:** `vue-expert`

### Context
Setup Vue Router for navigation between pages (public & admin).

### Requirements

1. **Install Vue Router**
   ```bash
   npm install vue-router@4
   ```

2. **Create routes** in `src/router/index.js`
3. **Create page components** in `src/views/`

### Router Configuration

```javascript
// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router';

// Public pages
import Home from '@/views/Home.vue';
import Projects from '@/views/Projects.vue';
import ProjectDetail from '@/views/ProjectDetail.vue';
import Blog from '@/views/Blog.vue';
import BlogPost from '@/views/BlogPost.vue';
import Gallery from '@/views/Gallery.vue';
import Contact from '@/views/Contact.vue';

// Admin pages (lazy loaded)
const AdminDashboard = () => import('@/views/admin/Dashboard.vue');
const AdminProjects = () => import('@/views/admin/Projects.vue');
const AdminBlog = () => import('@/views/admin/Blog.vue');

const routes = [
  {
    path: '/',
    name: 'home',
    component: Home,
    meta: { title: 'Home' },
  },
  {
    path: '/projects',
    name: 'projects',
    component: Projects,
    meta: { title: 'Projects' },
  },
  {
    path: '/projects/:slug',
    name: 'project-detail',
    component: ProjectDetail,
    meta: { title: 'Project' },
  },
  {
    path: '/blog',
    name: 'blog',
    component: Blog,
    meta: { title: 'Blog' },
  },
  {
    path: '/blog/:slug',
    name: 'blog-post',
    component: BlogPost,
    meta: { title: 'Blog Post' },
  },
  {
    path: '/gallery',
    name: 'gallery',
    component: Gallery,
    meta: { title: 'Gallery' },
  },
  {
    path: '/contact',
    name: 'contact',
    component: Contact,
    meta: { title: 'Contact' },
  },
  
  // Admin routes
  {
    path: '/admin',
    name: 'admin',
    redirect: '/admin/dashboard',
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'admin-dashboard',
        component: AdminDashboard,
        meta: { title: 'Dashboard' },
      },
      {
        path: 'projects',
        name: 'admin-projects',
        component: AdminProjects,
        meta: { title: 'Manage Projects' },
      },
      // More admin routes...
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    } else {
      return { top: 0 };
    }
  },
});

// Route guards
router.beforeEach((to, from, next) => {
  // Set page title
  document.title = `${to.meta.title || 'Portfolio'} - Ali Sadikin`;

  // Check auth for admin routes
  if (to.meta.requiresAuth) {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      next({ name: 'home' });
    } else {
      next();
    }
  } else {
    next();
  }
});

export default router;
```

### Page Components Structure

Create placeholder components:

```vue
<!-- src/views/Home.vue -->
<template>
  <div class="home">
    <h1 class="heading-1">Home Page</h1>
    <p class="body-base">Welcome to the portfolio</p>
  </div>
</template>

<script setup>
// Component logic here
</script>
```

### Deliverables
- [ ] Vue Router installed and configured
- [ ] All routes defined
- [ ] Placeholder page components created
- [ ] Route guards implemented
- [ ] Navigation working between pages
- [ ] 404 page created

---

## GENERAL GUIDELINES

### Code Quality
- Use Composition API (`<script setup>`)
- TypeScript optional but encouraged
- ESLint configuration
- Prettier for formatting

### Performance
- Lazy load routes
- Lazy load components where possible
- Use `v-once` for static content
- Optimize images (WebP format)

### Accessibility
- Semantic HTML
- ARIA labels where needed
- Keyboard navigation
- Focus management
- Screen reader support

### Best Practices
- Component composition over inheritance
- Props validation
- Emit event declarations
- Consistent naming conventions
- Comment complex logic

---

*Generated for Claude Code - October 9, 2025*
