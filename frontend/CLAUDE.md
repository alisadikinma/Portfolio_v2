# CLAUDE.md - Frontend SPA

This file provides guidance to Claude Code when working with the Vue 3 frontend application.

**Context:** Read root `/CLAUDE.md` first for general project architecture.

## Quick Reference

**Framework:** Vue 3.5 (Composition API) | **Build:** Vite 7 (Rolldown) | **Styling:** Tailwind CSS 4
**State:** Pinia 3 | **Routing:** Vue Router 4.5 | **HTTP:** Axios 1.12
**Dev Server:** `http://localhost:5173` (Vite HMR)

## Architecture Overview

### Core Pattern: Composable-First SPA

This frontend implements a **modern Vue 3 SPA** with:

1. **Composition API** - All components use `<script setup>` syntax
2. **Composables** - Reusable logic in `composables/` (usePosts, useProjects, etc.)
3. **Layout system** - Route-based layouts (DefaultLayout, AdminLayout, AuthLayout)
4. **API abstraction** - Axios instance with interceptors in `services/api.js`
5. **State management** - Pinia stores for global state (auth, ui, theme)
6. **Utility-first CSS** - Tailwind CSS, minimal custom CSS

### Component Hierarchy
```
App.vue
└── Layout (DefaultLayout | AdminLayout | AuthLayout)
    └── View (Home, Blog, Projects, etc.)
        └── Components (Base + Feature-specific)
```

## Component Structure Pattern

### Standard Setup Syntax

```vue
<script setup>
// 1. Imports
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePosts } from '@/composables/usePosts'
import BaseButton from '@/components/base/BaseButton.vue'

// 2. Props & Emits
const props = defineProps({
  id: {
    type: Number,
    required: true
  },
  title: {
    type: String,
    default: 'Untitled'
  }
})

const emit = defineEmits(['update', 'delete'])

// 3. Composables & Router
const router = useRouter()
const route = useRoute()
const { posts, loading, error, fetchPosts } = usePosts()

// 4. Reactive State
const localData = ref(null)
const isOpen = ref(false)

// 5. Computed Properties
const filteredPosts = computed(() => {
  return posts.value.filter(p => p.published)
})

const hasData = computed(() => localData.value !== null)

// 6. Methods
function handleSubmit() {
  emit('update', localData.value)
}

function navigateToDetail(slug) {
  router.push({ name: 'blog-detail', params: { slug } })
}

// 7. Watchers
watch(() => props.id, (newId) => {
  if (newId) fetchData(newId)
})

// 8. Lifecycle Hooks
onMounted(async () => {
  await fetchPosts()
})
</script>

<template>
  <!-- Use Tailwind utility classes -->
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-4xl font-bold text-gray-900 dark:text-white">
      {{ title }}
    </h1>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <BaseLoader />
    </div>

    <div v-else-if="error" class="rounded-lg bg-red-50 p-4 text-red-800">
      {{ error }}
    </div>

    <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="post in filteredPosts"
        :key="post.id"
        @click="navigateToDetail(post.slug)"
        class="cursor-pointer rounded-lg bg-white p-6 shadow-md transition hover:shadow-xl"
      >
        <h2 class="text-xl font-semibold">{{ post.title }}</h2>
        <p class="mt-2 text-gray-600">{{ post.excerpt }}</p>
      </div>
    </div>

    <BaseButton
      @click="handleSubmit"
      variant="primary"
      class="mt-6"
    >
      Submit
    </BaseButton>
  </div>
</template>

<style scoped>
/* Minimal custom CSS - prefer Tailwind */
.custom-animation {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>
```

## Composables Pattern

Composables encapsulate reusable logic. Located in `src/composables/`.

### Example: usePosts.js

```javascript
import { ref } from 'vue'
import api from '@/services/api'

export function usePosts() {
  // State
  const posts = ref([])
  const post = ref(null)
  const isLoading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Fetch all posts
  const fetchPosts = async (params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/posts', { params })
      posts.value = response.data.data

      if (response.data.meta) {
        pagination.value = {
          currentPage: response.data.meta.current_page,
          perPage: response.data.meta.per_page,
          total: response.data.meta.total,
          lastPage: response.data.meta.last_page
        }
      }

      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch posts'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single post by slug
  const fetchPost = async (slug, lang = 'en') => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/posts/${slug}`, {
        params: { lang }
      })
      post.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch post'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Create post (admin)
  const createPost = async (postData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/admin/posts', postData)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create post'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    posts,
    post,
    isLoading,
    error,
    pagination,

    // Methods
    fetchPosts,
    fetchPost,
    createPost
  }
}
```

### Using Composables in Components

```vue
<script setup>
import { onMounted } from 'vue'
import { usePosts } from '@/composables/usePosts'

const { posts, isLoading, error, fetchPosts } = usePosts()

onMounted(async () => {
  await fetchPosts({ per_page: 20, category: 'tech' })
})
</script>

<template>
  <div v-if="isLoading">Loading...</div>
  <div v-else-if="error">Error: {{ error }}</div>
  <div v-else>
    <div v-for="post in posts" :key="post.id">
      {{ post.title }}
    </div>
  </div>
</template>
```

## Pinia Store Pattern

Stores manage global state. Located in `src/stores/`.

### Example: auth.js

```javascript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token'))
  const isLoading = ref(false)
  const error = ref(null)

  // Getters
  const isAuthenticated = computed(() => !!token.value)
  const userName = computed(() => user.value?.name || 'Guest')

  // Actions
  async function login(credentials) {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/auth/login', credentials)

      token.value = response.data.token
      user.value = response.data.user

      localStorage.setItem('auth_token', token.value)
      localStorage.setItem('auth_user', JSON.stringify(user.value))

      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Login failed'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      user.value = null
      token.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
    }
  }

  function initializeAuth() {
    const storedToken = localStorage.getItem('auth_token')
    const storedUser = localStorage.getItem('auth_user')

    if (storedToken && storedUser) {
      token.value = storedToken
      user.value = JSON.parse(storedUser)
    }
  }

  return {
    // State
    user,
    token,
    isLoading,
    error,

    // Getters
    isAuthenticated,
    userName,

    // Actions
    login,
    logout,
    initializeAuth
  }
})
```

### Using Stores in Components

```vue
<script setup>
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

async function handleLogin() {
  const result = await authStore.login({
    email: 'user@example.com',
    password: 'password'
  })

  if (result.success) {
    router.push('/admin')
  }
}
</script>

<template>
  <div v-if="authStore.isAuthenticated">
    Welcome, {{ authStore.userName }}!
    <button @click="authStore.logout">Logout</button>
  </div>
  <div v-else>
    <button @click="handleLogin">Login</button>
  </div>
</template>
```

## Router Pattern

Located in `src/router/index.js`.

### Key Features:

1. **Lazy loading** - Components loaded on demand
2. **Meta fields** - Page titles, auth requirements, layouts
3. **Navigation guards** - Auth checks, page titles
4. **Slug-based routes** - `/blog/:slug`, `/projects/:slug`

```javascript
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/Home.vue'),
    meta: {
      title: 'Home - Portfolio',
      requiresAuth: false
    }
  },
  {
    path: '/blog/:slug',
    name: 'blog-detail',
    component: () => import('@/views/BlogDetail.vue'),
    meta: {
      title: 'Blog Post - Portfolio',
      requiresAuth: false
    }
  },
  {
    path: '/admin',
    name: 'admin',
    component: () => import('@/views/admin/Dashboard.vue'),
    meta: {
      title: 'Admin Dashboard',
      requiresAuth: true,
      layout: 'admin'
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0, behavior: 'smooth' }
  }
})

// Navigation guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  // Set page title
  document.title = to.meta.title || 'Portfolio'

  // Check authentication
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login', query: { redirect: to.fullPath } })
  } else if (to.name === 'login' && authStore.isAuthenticated) {
    next({ name: 'admin' })
  } else {
    next()
  }
})

export default router
```

## API Service Layer

Located in `src/services/api.js`.

### Axios Configuration

```javascript
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ||
    'http://localhost/Portfolio_v2/backend/public/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor - Add auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    const language = localStorage.getItem('language') || 'en'
    config.headers['Accept-Language'] = language

    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor - Handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      switch (error.response.status) {
        case 401:
          // Unauthorized - redirect to login
          localStorage.removeItem('auth_token')
          window.location.href = '/login'
          break
        case 422:
          // Validation error
          console.error('Validation:', error.response.data)
          break
        case 500:
          console.error('Server error')
          break
      }
    } else if (error.request) {
      console.error('Network error')
    }

    return Promise.reject(error)
  }
)

export default api
```

## Tailwind CSS Patterns

### Utility-First Approach

```vue
<template>
  <!-- Container & Spacing -->
  <div class="container mx-auto px-4 py-8">

    <!-- Typography -->
    <h1 class="text-4xl font-bold text-gray-900 dark:text-white">
      Title
    </h1>
    <p class="mt-4 text-lg text-gray-600 dark:text-gray-300">
      Paragraph
    </p>

    <!-- Responsive Grid -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div class="rounded-lg bg-white p-6 shadow-md">
        Card
      </div>
    </div>

    <!-- Flexbox Layout -->
    <div class="flex items-center justify-between">
      <span>Left</span>
      <span>Right</span>
    </div>

    <!-- Buttons -->
    <button class="rounded-lg bg-blue-600 px-6 py-3 text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
      Click Me
    </button>

    <!-- Forms -->
    <input
      type="text"
      class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
      placeholder="Enter text..."
    />

    <!-- Hover & States -->
    <div class="cursor-pointer transition hover:scale-105 hover:shadow-xl">
      Hover me
    </div>
  </div>
</template>
```

### Custom Tailwind Classes (Rare)

```css
/* src/assets/styles/main.css */
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Only when absolutely necessary */
@layer components {
  .btn-primary {
    @apply rounded-lg bg-blue-600 px-6 py-3 text-white transition hover:bg-blue-700;
  }
}
```

## Essential Commands

```bash
# Development
npm run dev              # Start Vite dev server (port 5173)
npm run build            # Production build
npm run preview          # Preview production build

# Force refresh (if HMR not working)
npm run dev -- --force

# Dependencies
npm install              # Install dependencies
npm update               # Update dependencies
```

## Environment Variables

Located in `.env` (must be prefixed with `VITE_`):

```env
VITE_API_BASE_URL=http://localhost/Portfolio_v2/backend/public/api
VITE_APP_NAME=Portfolio
VITE_APP_ENV=development
```

**Access in code:**
```javascript
const apiUrl = import.meta.env.VITE_API_BASE_URL
const appName = import.meta.env.VITE_APP_NAME

// Check environment
if (import.meta.env.DEV) {
  console.log('Development mode')
}
```

## File Organization

```
src/
├── views/              # Page components
│   ├── Home.vue
│   ├── Blog.vue
│   ├── BlogDetail.vue
│   └── admin/
│       └── Dashboard.vue
├── layouts/            # Layout wrappers
│   ├── DefaultLayout.vue
│   ├── AdminLayout.vue
│   └── AuthLayout.vue
├── components/
│   ├── base/          # Reusable UI components
│   │   ├── BaseButton.vue
│   │   ├── BaseCard.vue
│   │   ├── BaseInput.vue
│   │   └── BaseLoader.vue
│   ├── TheNavigation.vue
│   └── TheFooter.vue
├── composables/        # Reusable logic
│   ├── usePosts.js
│   ├── useProjects.js
│   └── useAuth.js
├── stores/             # Pinia stores
│   ├── auth.js
│   ├── ui.js
│   └── theme.js
├── services/           # API layer
│   └── api.js
├── router/             # Vue Router
│   └── index.js
├── utils/              # Helper functions
│   └── errorHandler.js
├── assets/             # Static assets
│   └── styles/
│       └── main.css
├── App.vue             # Root component
└── main.js             # Entry point
```

## Best Practices Checklist

**Components:**
- [ ] Use `<script setup>` syntax
- [ ] Order: imports → props/emits → composables → state → computed → methods → watchers → lifecycle
- [ ] Use Tailwind classes (minimal custom CSS)
- [ ] Emit events for parent communication
- [ ] Destructure composable returns

**Composables:**
- [ ] Return state and methods as object
- [ ] Use `ref()` for reactive state
- [ ] Handle loading/error states
- [ ] Return success/error from async functions

**Pinia Stores:**
- [ ] Use setup syntax with arrow functions
- [ ] Define getters as computed properties
- [ ] Actions handle async logic
- [ ] Persist critical data to localStorage

**API Calls:**
- [ ] Always handle loading state
- [ ] Always handle error state
- [ ] Use try/catch blocks
- [ ] Return success/error indicators

**Tailwind:**
- [ ] Mobile-first (base → sm → md → lg → xl)
- [ ] Use utility classes
- [ ] Dark mode support: `dark:text-white`
- [ ] Hover/focus states for interactive elements

---

**Last Updated:** October 13, 2025
**See also:** `/CLAUDE.md` (root), `backend/CLAUDE.md`
