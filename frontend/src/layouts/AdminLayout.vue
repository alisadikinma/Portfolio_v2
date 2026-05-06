<template>
  <div class="dark min-h-screen bg-neutral-100 dark:bg-neutral-900">
    <div class="flex">
      <!-- Sidebar -->
      <aside
        class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-neutral-800 border-r border-neutral-200 dark:border-neutral-700 transform transition-transform duration-300"
        :class="{ '-translate-x-full': !uiStore.isSidebarOpen, 'translate-x-0': uiStore.isSidebarOpen }"
      >
        <div class="h-full flex flex-col">
          <!-- Sidebar Header — Branding -->
          <div class="h-16 flex items-center justify-between px-5 border-b border-neutral-200 dark:border-neutral-700">
            <router-link to="/admin" class="flex items-center gap-2.5 group">
              <div class="w-8 h-8 rounded-lg overflow-hidden flex-shrink-0 bg-gradient-to-br from-amber-500 to-cyan-500">
                <img
                  v-if="siteLogoUrl"
                  :src="siteLogoUrl"
                  alt="Ali Sadikin Ma"
                  class="w-full h-full object-cover"
                />
              </div>
              <div class="flex flex-col leading-none">
                <span class="text-sm font-bold text-neutral-100 tracking-tight">ALI SADIKIN MA</span>
                <span class="text-[10px] text-neutral-500 font-medium tracking-wider uppercase">Admin Panel</span>
              </div>
            </router-link>
            <button
              @click="uiStore.toggleSidebar"
              class="lg:hidden text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Sidebar Navigation -->
          <nav class="flex-1 overflow-y-auto p-4">
            <div class="space-y-1">
              <router-link
                to="/admin"
                exact
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Dashboard
              </router-link>

              <router-link
                to="/admin/posts"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Blog
              </router-link>

              <router-link
                to="/admin/projects"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Projects
              </router-link>

              <router-link
                to="/admin/awards"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                Awards
              </router-link>

              <router-link
                to="/admin/galleries"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Gallery
              </router-link>

              <router-link
                to="/admin/content-engine"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-neutral-100 dark:bg-neutral-700 !text-amber-600 dark:!text-amber-400 font-medium"
              >
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                </svg>
                Content Engine
              </router-link>

              <!-- LinkedIn section — blog→LinkedIn publishing admin -->
              <div class="my-4 border-t border-neutral-200 dark:border-neutral-700"></div>
              <p class="px-4 text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                LinkedIn
              </p>

              <router-link
                to="/admin/linkedin-posts"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
                Posts
              </router-link>

              <router-link
                to="/admin/linkedin-queue"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                </svg>
                Queue
              </router-link>

              <!-- End LinkedIn section -->
              <div class="my-4 border-t border-neutral-200 dark:border-neutral-700"></div>

              <router-link
                to="/admin/testimonials"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
                Testimonials
              </router-link>

              <router-link
                to="/admin/newsletter"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
                </svg>
                Newsletter
              </router-link>

              <router-link
                to="/admin/contacts"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Contact
              </router-link>

              <router-link
                to="/admin/about"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                About
              </router-link>

              <router-link
                to="/admin/settings"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Settings
              </router-link>

              <!-- Section Divider -->
              <div class="my-4 border-t border-neutral-200 dark:border-neutral-700"></div>
              <p class="px-4 text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Automation
              </p>

              <router-link
                to="/admin/automation/tokens"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                API Tokens
              </router-link>

              <router-link
                to="/admin/automation/logs"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                Activity Logs
              </router-link>

              <router-link
                to="/admin/automation/docs"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                API Docs
              </router-link>

              <!-- Section Divider -->
              <div class="my-4 border-t border-neutral-200 dark:border-neutral-700"></div>
              <p class="px-4 text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Site Structure
              </p>

              <router-link
                to="/admin/menu-items"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                Menu Items
              </router-link>

              <router-link
                to="/admin/page-sections"
                class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-semibold"
              >
                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                </svg>
                Page Sections
              </router-link>
            </div>
          </nav>

          <!-- Sidebar Footer -->
          <div class="p-4 border-t border-neutral-200 dark:border-neutral-700">
            <button
              @click="handleLogout"
              class="w-full px-4 py-2 text-sm text-error-600 hover:bg-error-50 dark:hover:bg-error-900/20 rounded-lg"
            >
              Logout
            </button>
          </div>
        </div>
      </aside>

      <!-- Main Content — dynamic margin based on sidebar state -->
      <div class="flex-1 transition-all duration-300" :class="uiStore.isSidebarOpen ? 'lg:ml-64' : 'lg:ml-0'">
        <!-- Top Bar — sticky so the hamburger + dark toggle stay reachable on long admin pages -->
        <header class="sticky top-0 z-30 h-16 bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 flex items-center justify-between px-6 backdrop-blur supports-[backdrop-filter]:bg-white/95 supports-[backdrop-filter]:dark:bg-neutral-800/95">
          <div class="flex items-center gap-3">
            <button
              @click="uiStore.toggleSidebar"
              class="text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
            >
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>

            <!-- Branding in topbar when sidebar is hidden -->
            <transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="opacity-0 -translate-x-4"
              enter-to-class="opacity-100 translate-x-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="opacity-100 translate-x-0"
              leave-to-class="opacity-0 -translate-x-4"
            >
              <router-link v-if="!uiStore.isSidebarOpen" to="/admin" class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg overflow-hidden flex-shrink-0 bg-gradient-to-br from-amber-500 to-cyan-500">
                  <img v-if="siteLogoUrl" :src="siteLogoUrl" alt="Ali Sadikin Ma" class="w-full h-full object-cover" />
                </div>
                <div class="flex flex-col leading-none">
                  <span class="text-sm font-bold text-neutral-100 tracking-tight">ALI SADIKIN MA</span>
                  <span class="text-[9px] text-neutral-500 font-medium tracking-wider uppercase">Admin Panel</span>
                </div>
              </router-link>
            </transition>
          </div>

          <div class="flex items-center gap-4">
            <button
              @click="themeStore.toggleDark"
              class="p-2 rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700"
            >
              <span v-if="themeStore.isDark">☀️</span>
              <span v-else>🌙</span>
            </button>
          </div>
        </header>

        <!-- Page Content -->
        <main class="p-6">
          <router-view />
        </main>
      </div>
    </div>

    <!-- Global Toast -->
    <BaseToast ref="toastRef" position="top-right" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useThemeStore } from '@/stores/theme'
import { useUIStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { useSiteSettings } from '@/composables/useSiteSettings'
import { BaseToast } from '@/components/base'

const router = useRouter()
const themeStore = useThemeStore()
const uiStore = useUIStore()
const authStore = useAuthStore()
const { siteLogo, fetchSiteSettings } = useSiteSettings()

const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost/Portfolio_v2/backend/public/api'
const backendBase = apiBase.replace('/api', '')
const siteLogoUrl = computed(() => {
  if (!siteLogo.value) return null
  if (siteLogo.value.startsWith('http')) return siteLogo.value
  return backendBase + siteLogo.value
})
const toastRef = ref(null)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}

onMounted(async () => {
  if (toastRef.value) {
    uiStore.toastRef = toastRef.value
  }
  await fetchSiteSettings()
})
</script>
