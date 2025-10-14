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
    path: '/admin',
    name: 'admin',
    component: () => import('@/views/admin/Dashboard.vue'),
    meta: {
      title: 'Admin Dashboard - Portfolio V2',
      requiresAuth: true,
      layout: 'admin'
    }
  },
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
    path: '/admin/gallery',
    name: 'admin-gallery',
    component: () => import('@/views/admin/GalleryList.vue'),
    meta: {
      title: 'Manage Gallery - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
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
    path: '/admin/contact',
    name: 'admin-contact',
    component: () => import('@/views/admin/ContactList.vue'),
    meta: {
      title: 'Contact Messages - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
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
  {
    path: '/admin/settings',
    name: 'admin-settings',
    component: () => import('@/views/admin/Settings.vue'),
    meta: {
      title: 'Site Settings - Admin',
      requiresAuth: true,
      layout: 'admin'
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
