# Frontend Application - Vue 3

Modern, responsive frontend for Portfolio Website built with Vue 3, Vite, and Tailwind CSS.

## 🎨 Features

- **Responsive Design**: Mobile-first, works seamlessly on all devices
- **Modern UI/UX**: Clean interface with smooth animations
- **Component-Based**: Modular, reusable Vue 3 components with Composition API
- **Fast Performance**: Vite for lightning-fast HMR and optimized builds
- **SEO Optimized**: Meta tags management with vue-meta or useHead
- **Accessibility**: WCAG 2.1 compliant with proper ARIA labels
- **Dark Mode**: Light/dark theme support (optional)
- **Progressive**: Can be enhanced to PWA

## 🚀 Tech Stack

### Core
- **Vue 3** (v3.5+) - Progressive JavaScript framework
- **Vite** (v7+) - Next generation frontend tooling
- **Vue Router** (v4.5+) - Official router for Vue.js

### Styling
- **Tailwind CSS 4** - Utility-first CSS framework
- **PostCSS** - CSS transformations
- **Autoprefixer** - CSS vendor prefixing

### UI Components
- **Headless UI** - Unstyled, accessible components
- **Heroicons** - Beautiful hand-crafted SVG icons

### State Management
- **Pinia** (v3+) - Vue official state management
- **Composition API** - Built-in reactive state

### HTTP Client
- **Axios** - Promise-based HTTP client

### Testing
- **Playwright** - End-to-end testing framework

## 📋 Prerequisites

- Node.js v18 or higher
- NPM or Yarn or PNPM
- Modern web browser (Chrome, Firefox, Edge, Safari)

## 🛠️ Installation

### 1. Install Dependencies

```bash
npm install
```

### 2. Environment Configuration

Copy the example environment file:

```bash
copy .env.example .env
```

Edit `.env` with your configuration:

```env
# API Configuration
VITE_API_URL=http://localhost/Portfolio_v2/backend/public/api
VITE_API_TIMEOUT=30000

# Application
VITE_APP_NAME="Portfolio v2"
VITE_APP_TITLE="Portfolio & Blog"
VITE_APP_VERSION=2.0.0

# Features
VITE_ENABLE_DARK_MODE=true
VITE_ENABLE_ANALYTICS=false

# Contact
VITE_CONTACT_EMAIL=ali.sadikincom85@gmail.com
```

**Environment Variable Naming:**
- Vite requires all public env vars to be prefixed with `VITE_`
- Variables are exposed to client-side code
- Never put secrets in client-side env vars

## 🚦 Running the Application

### Development Mode

```bash
npm run dev
```

Application will be available at:
- **Dev Server**: http://localhost:5173 (default Vite port)
- **Network**: http://[your-ip]:5173 (for mobile testing)

**Prerequisites:**
- Ensure XAMPP Apache is running (backend API must be accessible)
- Backend API at: http://localhost/Portfolio_v2/backend/public/api

Features enabled in development:
- **Hot Module Replacement (HMR)**: Instant updates without page reload
- **Source Maps**: Easy debugging with Vue DevTools
- **Detailed Error Messages**: Stack traces and warnings
- **API Proxy**: Configured for XAMPP backend

### Production Build

```bash
# Build for production
npm run build

# Preview production build locally
npm run preview
```

Build output will be in `dist/` folder with:
- Minified JavaScript
- Optimized CSS
- Compressed assets
- Tree-shaken code

## 📁 Project Structure

```
frontend/
├── public/                     # Static assets (copied as-is)
│   ├── favicon.ico
│   ├── robots.txt
│   └── images/
├── src/
│   ├── assets/                 # Build-time assets
│   │   ├── images/            # Images (optimized by Vite)
│   │   ├── styles/            # Global styles
│   │   │   └── main.css       # Tailwind imports
│   │   └── fonts/             # Custom fonts
│   ├── components/             # Vue components
│   │   ├── common/            # Reusable components
│   │   │   ├── BaseButton.vue
│   │   │   ├── BaseCard.vue
│   │   │   └── BaseModal.vue
│   │   ├── layout/            # Layout components
│   │   │   ├── AppHeader.vue
│   │   │   ├── AppFooter.vue
│   │   │   └── AppSidebar.vue
│   │   └── features/          # Feature-specific components
│   │       ├── ProjectCard.vue
│   │       ├── BlogPost.vue
│   │       └── ContactForm.vue
│   ├── composables/            # Vue composables (reusable logic)
│   │   ├── useApi.js          # API calls
│   │   ├── useAuth.js         # Authentication
│   │   └── useTheme.js        # Theme switching
│   ├── router/                 # Vue Router configuration
│   │   └── index.js           # Routes definition
│   ├── stores/                 # Pinia stores
│   │   ├── auth.js            # Auth store
│   │   ├── projects.js        # Projects store
│   │   └── blog.js            # Blog store
│   ├── views/                  # Page components
│   │   ├── HomeView.vue
│   │   ├── AboutView.vue
│   │   ├── ProjectsView.vue
│   │   ├── ProjectDetailView.vue
│   │   ├── BlogView.vue
│   │   ├── BlogPostView.vue
│   │   └── ContactView.vue
│   ├── utils/                  # Utility functions
│   │   ├── helpers.js         # General helpers
│   │   ├── validators.js      # Form validators
│   │   └── formatters.js      # Data formatters
│   ├── App.vue                 # Root component
│   └── main.js                 # Application entry point
├── tests/                      # Test files
│   ├── unit/                  # Unit tests
│   └── e2e/                   # E2E tests
├── .env                        # Environment variables (not in git)
├── .env.example                # Environment template
├── .gitignore                  # Git ignore rules
├── index.html                  # HTML entry point
├── package.json                # Dependencies & scripts
├── postcss.config.js           # PostCSS configuration
├── tailwind.config.js          # Tailwind CSS configuration
├── vite.config.js              # Vite configuration
└── README.md                   # This file
```

## 🎯 Component Structure

We follow Vue 3 best practices with Composition API:

### Component Example

```vue
<!-- components/features/ProjectCard.vue -->
<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

// Props
const props = defineProps({
  project: {
    type: Object,
    required: true
  }
})

// Emits
const emit = defineEmits(['click'])

// Composables
const router = useRouter()

// Computed
const imageUrl = computed(() => {
  return props.project.image || '/images/placeholder.jpg'
})

// Methods
function viewProject() {
  router.push({ name: 'project-detail', params: { id: props.project.id } })
  emit('click', props.project)
}
</script>

<template>
  <div 
    class="group relative overflow-hidden rounded-lg bg-white shadow-md transition hover:shadow-xl"
    @click="viewProject"
  >
    <img 
      :src="imageUrl" 
      :alt="project.title"
      class="h-48 w-full object-cover transition group-hover:scale-105"
    >
    <div class="p-4">
      <h3 class="text-xl font-bold">{{ project.title }}</h3>
      <p class="mt-2 text-gray-600">{{ project.description }}</p>
    </div>
  </div>
</template>
```

## 🔐 Environment Variables

### Frontend .env Configuration

```env
# API Configuration
VITE_API_URL=http://localhost/Portfolio_v2/backend/public/api
VITE_API_TIMEOUT=30000

# Application
VITE_APP_NAME=Portfolio
VITE_APP_VERSION=2.0.0

# Features
VITE_ENABLE_DARK_MODE=true
VITE_ENABLE_ANALYTICS=false

# Optional: Third-party Services
# VITE_GOOGLE_ANALYTICS_ID=
# VITE_SENTRY_DSN=
```

### Important Notes

- ⚠️ All variables must be prefixed with `VITE_`
- ⚠️ Variables are embedded at build time (not runtime)
- ⚠️ Never put secrets in `.env` (they'll be in client code)
- ✅ Only put public configuration

### Accessing Environment Variables

```javascript
// In Vue components or JS files
const apiUrl = import.meta.env.VITE_API_URL
const appName = import.meta.env.VITE_APP_NAME

// Check environment
if (import.meta.env.DEV) {
  console.log('Development mode')
}

if (import.meta.env.PROD) {
  console.log('Production mode')
}
```

## 🧪 Testing

### Run Tests

```bash
# Run all tests
npm test

# Run E2E tests
npm run test:e2e

# Run tests in UI mode
npm run test:ui

# Run tests with coverage (if configured)
npm run test:coverage
```

### Example Test (Playwright)

```javascript
// tests/e2e/home.spec.js
import { test, expect } from '@playwright/test'

test('homepage loads correctly', async ({ page }) => {
  await page.goto('http://localhost:5173')
  
  // Check title
  await expect(page).toHaveTitle(/Portfolio/)
  
  // Check hero section
  const hero = page.locator('section.hero')
  await expect(hero).toBeVisible()
  
  // Check navigation
  const nav = page.locator('nav')
  await expect(nav).toContainText('Home')
  await expect(nav).toContainText('Projects')
  await expect(nav).toContainText('Blog')
  await expect(nav).toContainText('Contact')
})
```

## 🎨 Styling Guide

### Tailwind CSS

Use Tailwind utility classes for styling:

```vue
<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-4xl font-bold text-gray-900 dark:text-white">
      Welcome
    </h1>
    <p class="mt-4 text-lg text-gray-600 dark:text-gray-300">
      This is a paragraph with responsive text sizing.
    </p>
    <button class="mt-4 rounded-lg bg-blue-600 px-6 py-3 text-white transition hover:bg-blue-700">
      Click Me
    </button>
  </div>
</template>
```

### Tailwind Configuration

Customize in `tailwind.config.js`:

```javascript
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0f9ff',
          500: '#3b82f6',
          900: '#1e3a8a',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
```

## 🔌 API Integration

### Using Axios with Composables

```javascript
// composables/useApi.js
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  timeout: import.meta.env.VITE_API_TIMEOUT,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor
api.interceptors.response.use(
  response => response.data,
  error => {
    console.error('API Error:', error)
    return Promise.reject(error)
  }
)

export default function useApi() {
  return { api }
}
```

### Using in Components

```vue
<script setup>
import { ref, onMounted } from 'vue'
import useApi from '@/composables/useApi'

const { api } = useApi()
const projects = ref([])
const loading = ref(false)

async function fetchProjects() {
  loading.value = true
  try {
    const response = await api.get('/projects')
    projects.value = response.data
  } catch (error) {
    console.error('Failed to fetch projects:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchProjects()
})
</script>
```

### Pinia Store Example

```javascript
// stores/projects.js
import { defineStore } from 'pinia'
import useApi from '@/composables/useApi'

export const useProjectsStore = defineStore('projects', {
  state: () => ({
    projects: [],
    currentProject: null,
    loading: false,
    error: null
  }),
  
  actions: {
    async fetchProjects() {
      const { api } = useApi()
      this.loading = true
      try {
        const response = await api.get('/projects')
        this.projects = response.data
      } catch (error) {
        this.error = error.message
      } finally {
        this.loading = false
      }
    },
    
    async fetchProject(id) {
      const { api } = useApi()
      this.loading = true
      try {
        const response = await api.get(`/projects/${id}`)
        this.currentProject = response.data
      } catch (error) {
        this.error = error.message
      } finally {
        this.loading = false
      }
    }
  },
  
  getters: {
    featuredProjects: (state) => {
      return state.projects.filter(p => p.featured)
    }
  }
})
```

## 🚀 Performance Optimization

### Code Splitting

```javascript
// router/index.js
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/HomeView.vue')
  },
  {
    path: '/projects',
    name: 'projects',
    component: () => import('@/views/ProjectsView.vue')
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
```

### Image Optimization

```vue
<template>
  <!-- Use native lazy loading -->
  <img 
    :src="imageUrl" 
    alt="Project"
    loading="lazy"
    class="object-cover"
  >
  
  <!-- Or with responsive images -->
  <img 
    :srcset="`${imageUrl}?w=400 400w, ${imageUrl}?w=800 800w`"
    :sizes="(max-width: 600px) 400px, 800px"
    :src="imageUrl"
    alt="Project"
    loading="lazy"
  >
</template>
```

### Memoization

```vue
<script setup>
import { computed, ref } from 'vue'

const items = ref([...])

// Memoized computation
const sortedItems = computed(() => {
  return [...items.value].sort((a, b) => a.date - b.date)
})
</script>
```

## ♿ Accessibility

### Best Practices

```vue
<template>
  <!-- Semantic HTML -->
  <nav aria-label="Main navigation">
    <ul>
      <li><router-link to="/">Home</router-link></li>
    </ul>
  </nav>

  <!-- Alt text for images -->
  <img src="/logo.png" alt="Company logo">

  <!-- ARIA labels -->
  <button 
    @click="toggleMenu"
    aria-label="Toggle navigation menu"
    :aria-expanded="menuOpen"
  >
    <MenuIcon />
  </button>

  <!-- Focus management -->
  <input
    ref="searchInput"
    type="text"
    placeholder="Search..."
    @focus="onSearchFocus"
  >
</template>
```

## 🔧 Development Scripts

```bash
npm run dev           # Start development server
npm run build         # Build for production
npm run preview       # Preview production build
npm test              # Run tests
npm run test:e2e      # Run E2E tests
npm run lint          # Lint code (if ESLint configured)
npm run format        # Format code (if Prettier configured)
```

## 📦 Build & Deployment

### Build Process

```bash
npm run build
```

Creates `dist/` folder with:
- Minified JavaScript bundles
- Optimized CSS
- Compressed images
- Index HTML with asset links

### Deploy to Netlify

```bash
# Install Netlify CLI
npm install -g netlify-cli

# Login
netlify login

# Deploy
netlify deploy --prod
```

### Deploy to Vercel

```bash
# Install Vercel CLI
npm install -g vercel

# Deploy
vercel --prod
```

### Deploy to Apache (XAMPP/Production)

1. Build the project:
```bash
npm run build
```

2. Copy `dist/*` to your web server
3. Create `.htaccess` for Vue Router:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

## 🐛 Debugging

### Vue DevTools

Install Vue DevTools browser extension for:
- Component inspection
- State management debugging
- Performance profiling
- Event tracking

### Console Debugging

```javascript
// Development only
if (import.meta.env.DEV) {
  console.log('Debug info:', data)
  console.table(users)
}
```

### Network Debugging

Use browser DevTools Network tab to:
- Inspect API calls
- Check request/response headers
- Monitor loading times
- Debug CORS issues

## 📝 Common Issues

### Vite Not Starting

```bash
# Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install

# Check port 5173 is not in use
netstat -ano | findstr :5173
```

### Hot Reload Not Working

- Restart Vite server
- Check file watching limits (Linux/WSL):
```bash
echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

### Build Fails

- Check for TypeScript errors (if using TS)
- Clear Vite cache:
```bash
rm -rf node_modules/.vite
npm run build
```

### API Calls Fail (CORS)

Ensure Laravel backend has CORS configured:
```php
// backend/config/cors.php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:5173'],
```

## 🤝 Contributing

1. Follow Vue.js style guide
2. Use Composition API (not Options API)
3. Write meaningful component names
4. Add proper prop validation
5. Test components before commit
6. Follow existing code style

### Code Style

- Use `<script setup>` syntax
- Prefer Composition API
- Use TypeScript for type safety (optional)
- Follow Vue naming conventions:
  - PascalCase for components
  - camelCase for functions/variables
  - kebab-case for props in templates

## 📚 Additional Resources

- [Vue 3 Documentation](https://vuejs.org/)
- [Vite Documentation](https://vitejs.dev/)
- [Vue Router Documentation](https://router.vuejs.org/)
- [Pinia Documentation](https://pinia.vuejs.org/)
- [Tailwind CSS Documentation](https://tailwindcss.com/)
- [Vue DevTools](https://devtools.vuejs.org/)

## 📞 Support

For frontend issues:

1. Check browser console for errors
2. Review network requests in DevTools
3. Check Vue DevTools for component state
4. Verify API endpoint is correct
5. Check environment variables

---

## 📈 Recent Updates

### Phase 3 Sprint 1 - Blog System Components (October 14, 2025)

**Completed Blog Components:**

#### 1. **RichTextEditor.vue** (465 lines)
CKEditor 5 integration via CDN with comprehensive features:
- Full formatting toolbar (headings, bold, italic, links, lists, tables)
- Code block support with 9 languages (JS, TS, PHP, Python, CSS, HTML, SQL, Bash)
- Media embed support
- Dark mode styling
- Loading & error states
- Auto-save via v-model
- Exposed methods: `getEditor()`, `setData()`, `getData()`, `focus()`

```vue
<!-- Usage Example -->
<RichTextEditor
  v-model="content"
  placeholder="Start writing..."
  min-height="500px"
  :disabled="false"
  @ready="onEditorReady"
  @error="onEditorError"
/>
```

#### 2. **ImageUploader.vue** (336 lines)
Drag & drop image uploader with preview:
- Drag & drop + click to browse
- Image preview with aspect ratio support (16:9, 4:3, 1:1)
- File validation (type: jpeg/png/gif/webp, size: max 5MB)
- Remove image functionality
- File size formatting
- Dark mode support
- Error handling

```vue
<!-- Usage Example -->
<ImageUploader
  v-model="featuredImage"
  label="Featured Image"
  aspect-ratio="16:9"
  :max-size="5242880"
  :disabled="false"
  @error="onUploadError"
/>
```

#### 3. **CategorySelect.vue** (272 lines)
Accessible category selector using Headless UI Listbox:
- Fetches categories from API via Pinia store
- Loading & error states
- Empty state with helpful message
- Selected indicator (checkmark)
- Post count badges
- Accessible keyboard navigation (WAI-ARIA compliant)
- Dark mode support

```vue
<!-- Usage Example -->
<CategorySelect
  v-model="categoryId"
  label="Category"
  :required="true"
  :disabled="false"
  @change="onCategoryChange"
/>
```

#### 4. **BlogPostForm.vue** (628 lines)
Complete integrated blog post form:
- Integrates RichTextEditor, ImageUploader, CategorySelect
- Complete validation (title, slug, content, category, etc.)
- Auto-slug generation from title with manual override
- Character counters with color warnings (75% yellow, 90% red)
- Collapsible advanced SEO section:
  - Meta title (60 chars)
  - Meta description (160 chars)
  - Focus keyword
  - Canonical URL
- Draft & Publish actions
- Loading states during submission
- Scroll to first error on validation fail
- Dark mode support

```vue
<!-- Usage Example -->
<BlogPostForm
  :post="existingPost"
  submit-label="Publish Post"
  :is-submitting="loading"
  @submit="handleSubmit"
  @cancel="handleCancel"
/>
```

**Admin Views Created:**
- ✅ `PostCreate.vue` - Create new blog posts
- ✅ `PostEdit.vue` - Edit existing posts with loading/error states

**Composables Added:**
- ✅ `useCategories.js` - Categories API integration

**Routes Added:**
- `/admin/posts` - Posts list (pending)
- `/admin/posts/create` - Create post ✅
- `/admin/posts/:id/edit` - Edit post ✅

**Pinia Stores:**
- ✅ `posts.js` - Full CRUD operations with pagination
- ✅ `categories.js` - Category management

**Frontend Status:**
- **Admin Panel**: 40% complete (Blog CRUD ✅, other modules pending)
- **Public Pages**: 35% complete (5/9 pages working)
- **Blog Components**: 100% complete (Sprint 1) ✅

**Completed in Sprint 1:**
- ✅ RichTextEditor, ImageUploader, CategorySelect, BlogPostForm
- ✅ PostCreate and PostEdit admin views
- ✅ Admin routing and navigation

**Next Sprint 2:**
- Posts list page with data table
- Search & filter functionality
- Pagination component
- Bulk actions (publish, draft, delete)

**See PROJECT_STATUS.md for overall project progress (45% complete)**

---

**Framework**: Vue 3.5 + Vite 7 (Rolldown)
**Node Version**: v18+
**Dev Server**: http://localhost:5173 (Vite HMR)
**API Backend**: http://localhost/Portfolio_v2/backend/public/api
**Last Updated**: October 15, 2025
