import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAboutSettings } from '@/composables/useAboutSettings'
import i18n from '@/i18n'
import { resolveLocale } from '@/utils/resolveLocale'

const SUPPORTED_LANGS = ['en', 'id']

const routes = [
  // ============================================
  // LOCALE-AWARE PUBLIC ROUTES (/:lang prefix)
  // ============================================
  {
    path: '/:lang',
    name: 'home',
    component: () => import('@/views/Home.vue'),
    meta: {
      title: 'Ali Sadikin Ma - AI Generalist Expert',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/about',
    name: 'about',
    component: () => import('@/views/About.vue'),
    meta: {
      title: 'About - Ali Sadikin Ma',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/projects',
    name: 'projects',
    component: () => import('@/views/Projects.vue'),
    meta: {
      title: 'Projects - Ali Sadikin Ma',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/projects/:slug',
    name: 'project-detail',
    component: () => import('@/views/ProjectDetail.vue'),
    meta: {
      title: 'Project - Ali Sadikin Ma',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/work',
    name: 'work',
    component: () => import('@/views/Work.vue'),
    meta: {
      title: 'My Work - Ali Sadikin Ma',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/awards',
    name: 'awards',
    redirect: to => `/${to.params.lang}/work?tab=awards`
  },
  {
    path: '/:lang/awards-debug',
    name: 'awards-debug',
    component: () => import('@/views/Awards-DEBUG.vue'),
    meta: {
      title: 'Awards Debug',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/blog',
    name: 'blog',
    component: () => import('@/views/Blog.vue'),
    meta: {
      title: 'Blog - Ali Sadikin Ma',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/blog/:slug',
    name: 'blog-detail',
    component: () => import('@/views/BlogDetail.vue'),
    meta: {
      title: 'Blog - Ali Sadikin Ma',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/blog/category/:slug',
    name: 'blog-category',
    component: () => import('@/views/BlogCategory.vue'),
    meta: {
      title: 'Category - Ali Sadikin Ma',
      requiresAuth: false
    }
  },
  {
    path: '/:lang/gallery',
    name: 'gallery',
    redirect: to => `/${to.params.lang}/work?tab=awards`
  },
  {
    path: '/:lang/contact',
    name: 'contact',
    component: () => import('@/views/Contact.vue'),
    meta: {
      title: 'Contact - Ali Sadikin Ma',
      requiresAuth: false
    }
  },

  // ============================================
  // LEGACY URL REDIRECTS (no lang prefix → add it)
  // ============================================
  {
    path: '/',
    // Uses resolveLocale so a first-time visitor in Indonesia lands on
    // /id, not /en. Falls through to saved preference or browser-timezone
    // detection. See frontend/src/utils/resolveLocale.js for the full rules.
    redirect: () => `/${resolveLocale()}`
  },
  { path: '/about', redirect: '/en/about' },
  { path: '/projects', redirect: '/en/projects' },
  { path: '/projects/:slug', redirect: to => `/en/projects/${to.params.slug}` },
  { path: '/work', redirect: '/en/work' },
  { path: '/blog', redirect: '/en/blog' },
  { path: '/blog/:slug', redirect: to => `/en/blog/${to.params.slug}` },
  { path: '/blog/category/:slug', redirect: to => `/en/blog/category/${to.params.slug}` },
  { path: '/contact', redirect: '/en/contact' },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/Login.vue'),
    meta: {
      title: 'Login - Ali Sadikin Ma',
      requiresAuth: false,
      layout: 'auth'
    }
  },
  {
    path: '/newsletter/unsubscribe',
    name: 'newsletter-unsubscribe',
    component: () => import('@/views/NewsletterUnsubscribe.vue'),
    meta: {
      title: 'Unsubscribe — Ali Sadikin Ma',
      requiresAuth: false,
    }
  },
  {
    path: '/faq',
    name: 'faq',
    component: () => import('@/views/FaqView.vue'),
    meta: {
      title: 'FAQ — Ali Sadikin Ma',
      requiresAuth: false,
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
    path: '/admin/newsletter',
    name: 'admin-newsletter',
    component: () => import('@/views/admin/NewsletterSubscribers.vue'),
    meta: {
      title: 'Newsletter — Admin',
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

  // Menu Items Management
  {
    path: '/admin/menu-items',
    name: 'admin-menu-items',
    component: () => import('@/views/admin/MenuItemsList.vue'),
    meta: {
      title: 'Menu Items - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },

  // Page Sections Management
  {
    path: '/admin/page-sections',
    name: 'admin-page-sections',
    component: () => import('@/views/admin/PageSectionsManager.vue'),
    meta: {
      title: 'Page Sections - Admin',
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

  // Carousel Drafts Management
  {
    path: '/admin/carousel-drafts',
    name: 'admin-carousel-drafts',
    component: () => import('@/views/admin/CarouselDraftsList.vue'),
    meta: {
      title: 'Carousel Drafts - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/carousel-drafts/:id',
    name: 'admin-carousel-drafts-detail',
    component: () => import('@/views/admin/CarouselDraftDetail.vue'),
    meta: {
      title: 'Carousel Draft - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },

  // Content Engine
  {
    path: '/admin/content-engine',
    name: 'admin-content-engine',
    component: () => import('@/views/admin/ContentEngine.vue'),
    meta: {
      title: 'Content Engine - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/content-engine/:id/preview',
    name: 'admin-content-engine-preview',
    component: () => import('@/views/admin/ArticlePreview.vue'),
    meta: {
      title: 'Article Preview - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/content-engine/:id/images',
    name: 'admin-content-engine-images',
    component: () => import('@/views/admin/ImageGeneration.vue'),
    meta: {
      title: 'Image Generation - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/content-engine/:id/finalize',
    name: 'admin-content-engine-finalize',
    component: () => import('@/views/admin/ArticleFinalize.vue'),
    meta: {
      title: 'Finalize Article - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },

  // Social media admin views — drafts (pre-publish triage) + posts
  // (calendar of scheduled / shipped). Routes were renamed from
  // linkedin-* to match the menu labels operators actually see.
  // Old paths kept as redirects so existing bookmarks still resolve.
  {
    path: '/admin/sosmed-posts',
    name: 'admin-sosmed-posts',
    component: () => import('@/views/admin/LinkedInPostsCalendar.vue'),
    meta: {
      title: 'Content Calendar - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  // Social Studio — merged work surface (blog drafts + IG repurpose jobs).
  // Targets of the collapsed menu item; old list paths redirect here.
  {
    path: '/admin/social-studio',
    name: 'admin-social-studio',
    component: () => import('@/views/admin/SocialStudio.vue'),
    meta: {
      title: 'Social Studio - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  // The old "Draft Posts" list now redirects into Social Studio (bookmarks survive).
  {
    path: '/admin/draft-posts',
    redirect: { name: 'admin-social-studio' },
  },
  // Legacy aliases — redirect to the renamed routes so bookmarks +
  // the back-navigation origin sentinel ('feed' / 'studio') keep working.
  {
    path: '/admin/linkedin-posts',
    redirect: { name: 'admin-sosmed-posts' },
  },
  {
    path: '/admin/linkedin-queue',
    redirect: { name: 'admin-social-studio' },
  },
  {
    path: '/admin/sosmed-drafts/:id',
    name: 'admin-sosmed-draft-detail',
    component: () => import('@/views/admin/LinkedInDraftDetail.vue'),
    meta: {
      title: 'SOSMED Draft - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  // Legacy alias — redirect old detail URLs (bookmarks, Telegram pings,
  // session-storage breadcrumbs from before May 9 rename) to the new path.
  {
    path: '/admin/linkedin-drafts/:id',
    redirect: (to) => ({ name: 'admin-sosmed-draft-detail', params: { id: to.params.id } }),
  },

  // IG repurpose — the list is merged into Social Studio; the path redirects
  // there (bookmarks survive). The per-job detail route is kept (2-pane view).
  {
    path: '/admin/repurpose',
    redirect: { name: 'admin-social-studio' },
  },
  {
    path: '/admin/repurpose/:id',
    name: 'admin-repurpose-detail',
    component: () => import('@/views/admin/RepurposeJobDetail.vue'),
    meta: { title: 'Repurpose Job - Admin', requiresAuth: true, layout: 'admin' }
  },
  {
    path: '/admin/video-full',
    name: 'admin-video-full',
    component: () => import('@/views/admin/VideoFullList.vue'),
    meta: { title: 'Video 60s - Admin', requiresAuth: true, layout: 'admin' }
  },
  {
    path: '/admin/video-full/:id',
    name: 'admin-video-full-detail',
    component: () => import('@/views/admin/VideoFullDetail.vue'),
    meta: { title: 'Video 60s Job - Admin', requiresAuth: true, layout: 'admin' }
  },

  // Cross-post admin (Facebook + Instagram + TikTok).
  //
  // Phase 3+4 unification: /admin/{platform}-posts → calendar (same
  // LinkedInPostsCalendar component, platform from props). Queue moves
  // to its own dedicated route /admin/{platform}-queue, served by
  // CrossPostList in queue scope. Both routes pull from the same
  // platform tabs (SocialPlatformTabs) so operators can hop between
  // calendar / queue / platform freely.
  {
    path: '/admin/:platform(facebook|instagram|tiktok)-posts',
    name: 'admin-cross-post-calendar',
    component: () => import('@/views/admin/LinkedInPostsCalendar.vue'),
    props: true,
    meta: {
      title: 'Cross-Post - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/:platform(facebook|instagram|tiktok)-queue',
    name: 'admin-cross-post-queue',
    component: () => import('@/views/admin/CrossPostList.vue'),
    meta: {
      title: 'Cross-Post Queue - Admin',
      requiresAuth: true,
      layout: 'admin'
    }
  },
  {
    path: '/admin/:platform(facebook|instagram|tiktok)-drafts/:id',
    name: 'admin-cross-post-detail',
    component: () => import('@/views/admin/CrossPostDraftDetail.vue'),
    meta: {
      title: 'Cross-Post Draft - Admin',
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

// View Transitions API (Chrome/Edge)
router.beforeResolve(async (to, from) => {
  if (document.startViewTransition && to.path !== from.path) {
    try {
      await document.startViewTransition().ready
    } catch {
      // Fallback: no animation in unsupported browsers
    }
  }
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Sync locale from route lang param (public routes only)
  const lang = to.params.lang
  if (lang) {
    if (!SUPPORTED_LANGS.includes(lang)) {
      // Unsupported language → redirect to English version
      const pathWithoutLang = to.fullPath.replace(`/${lang}`, '/en')
      return next(pathWithoutLang)
    }
    // Sync i18n locale with route
    if (i18n.global.locale.value !== lang) {
      i18n.global.locale.value = lang
      localStorage.setItem('locale', lang)
    }
  }

  // Prefetch critical data for homepage BEFORE navigation
  if (to.name === 'home') {
    const { prefetchAboutSettings } = useAboutSettings()
    await prefetchAboutSettings()
  }

  // Set page title ONLY for admin/auth pages (not public pages)
  if (to.meta.layout === 'admin' || to.meta.layout === 'auth') {
    document.title = to.meta.title || 'Admin - Portfolio'
  }

  // Check authentication
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login', query: { redirect: to.fullPath } })
  } else if (to.name === 'login' && authStore.isAuthenticated) {
    next({ name: 'admin' })
  } else {
    next()
  }
})

// Stale-chunk recovery after deploy.
// CI/CD rebuilds the frontend on every push, emitting new hashed chunk
// names; tabs opened before the deploy still reference the old chunks
// in their entry script, so Vue Router's lazy-import 404s. Force-reload
// to fetch a fresh index.html. sessionStorage guard prevents an infinite
// reload loop if the reload itself fails for some other reason.
const CHUNK_RELOAD_KEY = 'router:chunkReloadAt'
router.onError((error, to) => {
  const message = error?.message || ''
  const isChunkLoadError =
    message.includes('Failed to fetch dynamically imported module') ||
    message.includes('Importing a module script failed') ||
    message.includes('error loading dynamically imported module')

  if (!isChunkLoadError) return

  const lastReload = Number(sessionStorage.getItem(CHUNK_RELOAD_KEY) || 0)
  if (Date.now() - lastReload < 10000) return // already reloaded recently

  sessionStorage.setItem(CHUNK_RELOAD_KEY, String(Date.now()))
  const target = to?.fullPath || window.location.pathname + window.location.search
  window.location.assign(target)
})

export default router
