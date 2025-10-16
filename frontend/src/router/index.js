import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/Home.vue'),
    meta: {
      title: 'Home - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/about',
    name: 'about',
    component: () => import('@/views/About.vue'),
    meta: {
      title: 'About - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/projects',
    name: 'projects',
    component: () => import('@/views/Projects.vue'),
    meta: {
      title: 'Projects - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/projects/:slug',
    name: 'project-detail',
    component: () => import('@/views/ProjectDetail.vue'),
    meta: {
      title: 'Project - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/awards',
    name: 'awards',
    component: () => import('@/views/Awards.vue'),
    meta: {
      title: 'Awards & Recognition - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/blog',
    name: 'blog',
    component: () => import('@/views/Blog.vue'),
    meta: {
      title: 'Blog - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/blog/:slug',
    name: 'blog-detail',
    component: () => import('@/views/BlogDetail.vue'),
    meta: {
      title: 'Blog Post - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/blog/category/:slug',
    name: 'blog-category',
    component: () => import('@/views/BlogCategory.vue'),
    meta: {
      title: 'Category - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/gallery',
    name: 'gallery',
    component: () => import('@/views/Gallery.vue'),
    meta: {
      title: 'Gallery - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/contact',
    name: 'contact',
    component: () => import('@/views/Contact.vue'),
    meta: {
      title: 'Contact - Portfolio V2',
      requiresAuth: false
    }
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/Login.vue'),
    meta: {
      title: 'Login - Portfolio V2',
      requiresAuth: false,
      layout: 'auth'
    }
  },
  {
    path: '/admin',
    name: 'admin',
    component: () => import('@/views/admin/Dashboard.vue'),
    meta: {
      title: 'Admin Dashboard - Portfolio V2',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  // ============================================
  // BLOG POSTS ROUTES
  // ============================================
  {
    path: '/admin/posts',
    name: 'admin-posts',
    component: () => import('@/views/admin/PostsList.vue'),
    meta: {
      title: 'Manage Posts - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/posts/create',
    name: 'admin-posts-create',
    component: () => import('@/views/admin/PostCreate.vue'),
    meta: {
      title: 'Create Post - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/posts/:id/edit',
    name: 'admin-posts-edit',
    component: () => import('@/views/admin/PostEdit.vue'),
    meta: {
      title: 'Edit Post - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  
  // ============================================
  // ADMIN ROUTES
  // ============================================
  
  // Projects Management
  {
    path: '/admin/projects',
    name: 'admin-projects',
    component: () => import('@/views/admin/ProjectsList.vue'),
    meta: {
      title: 'Manage Projects - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/projects/create',
    name: 'admin-projects-create',
    component: () => import('@/views/admin/ProjectCreate.vue'),
    meta: {
      title: 'Create Project - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/projects/:id/edit',
    name: 'admin-projects-edit',
    component: () => import('@/views/admin/ProjectEdit.vue'),
    meta: {
      title: 'Edit Project - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  
  // Awards Management
  {
    path: '/admin/awards',
    name: 'admin-awards',
    component: () => import('@/views/admin/AwardsList.vue'),
    meta: {
      title: 'Manage Awards - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/awards/create',
    name: 'admin-awards-create',
    component: () => import('@/views/admin/AwardCreate.vue'),
    meta: {
      title: 'Create Award - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/awards/:id/edit',
    name: 'admin-awards-edit',
    component: () => import('@/views/admin/AwardEdit.vue'),
    meta: {
      title: 'Edit Award - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  
  // Galleries Management
  {
    path: '/admin/galleries',
    name: 'admin-galleries',
    component: () => import('@/views/admin/GalleriesList.vue'),
    meta: {
      title: 'Manage Galleries - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  
  // Testimonials Management
  {
    path: '/admin/testimonials',
    name: 'admin-testimonials',
    component: () => import('@/views/admin/TestimonialsList.vue'),
    meta: {
      title: 'Manage Testimonials - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/testimonials/create',
    name: 'admin-testimonials-create',
    component: () => import('@/views/admin/TestimonialCreate.vue'),
    meta: {
      title: 'Create Testimonial - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/testimonials/:id/edit',
    name: 'admin-testimonials-edit',
    component: () => import('@/views/admin/TestimonialEdit.vue'),
    meta: {
      title: 'Edit Testimonial - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  
  // Contacts Management (Read-only)
  {
    path: '/admin/contacts',
    name: 'admin-contacts',
    component: () => import('@/views/admin/ContactsList.vue'),
    meta: {
      title: 'Contact Messages - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  
  // About Settings
  {
    path: '/admin/about',
    name: 'admin-about',
    component: () => import('@/views/admin/AboutSettings.vue'),
    meta: {
      title: 'About Settings - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  
  // Settings
  {
    path: '/admin/settings',
    name: 'admin-settings',
    component: () => import('@/views/admin/SettingsForm.vue'),
    meta: {
      title: 'Site Settings - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },

  // Automation (n8n Integration)
  {
    path: '/admin/automation/tokens',
    name: 'admin-automation-tokens',
    component: () => import('@/views/admin/AutomationTokens.vue'),
    meta: {
      title: 'API Tokens - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/automation/logs',
    name: 'admin-automation-logs',
    component: () => import('@/views/admin/AutomationLogs.vue'),
    meta: {
      title: 'Automation Logs - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/automation/docs',
    name: 'admin-automation-docs',
    component: () => import('@/views/admin/AutomationDocs.vue'),
    meta: {
      title: 'API Documentation - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },

  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFound.vue'),
    meta: {
      title: '404 - Page Not Found',
      requiresAuth: false
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else if (to.hash) {
      return {
        el: to.hash,
        behavior: 'smooth'
      }
    } else {
      return { top: 0, behavior: 'smooth' }
    }
  }
})

// Navigation guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  // Set page title
  document.title = to.meta.title || 'Portfolio V2'

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
