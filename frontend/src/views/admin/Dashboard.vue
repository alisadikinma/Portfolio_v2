<template>
  <div class="min-h-screen bg-gradient-to-br from-neutral-50 via-white to-primary-50/20 dark:from-neutral-950 dark:via-neutral-900 dark:to-primary-950/20">
    <!-- Welcome Section with Notification Bell -->
    <div class="mb-8 flex items-start justify-between">
      <div>
        <h1 class="text-4xl font-display font-bold mb-3 bg-gradient-to-r from-primary-600 to-accent-600 bg-clip-text text-transparent animate-fade-in">
          Welcome back, {{ authStore.user?.name || 'Admin' }}!
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 text-lg animate-fade-in animation-delay-100">
          Here's what's happening with your portfolio today.
        </p>
      </div>
      
      <!-- Notification Bell -->
      <div class="relative animate-fade-in animation-delay-200">
        <button 
          @click="toggleNotifications"
          class="relative p-4 bg-white dark:bg-neutral-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-neutral-200 dark:border-neutral-700 hover:scale-105"
          :class="{ 'ring-2 ring-primary-500': showNotifications }"
        >
          <svg class="w-6 h-6 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span v-if="unreadContactsCount > 0" class="absolute -top-1 -right-1 flex h-6 w-6 items-center justify-center">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-6 w-6 bg-red-500 text-white text-xs font-bold items-center justify-center">
              {{ unreadContactsCount }}
            </span>
          </span>
        </button>

        <!-- Notifications Dropdown -->
        <Transition name="dropdown">
          <div 
            v-if="showNotifications" 
            class="absolute right-0 mt-2 w-96 bg-white dark:bg-neutral-800 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-700 overflow-hidden z-50"
          >
            <div class="p-4 border-b border-neutral-200 dark:border-neutral-700 bg-gradient-to-r from-primary-50 to-accent-50 dark:from-primary-950/50 dark:to-accent-950/50">
              <h3 class="font-semibold text-lg">New Messages</h3>
              <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ unreadContactsCount }} unread</p>
            </div>
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
              <div v-if="dashboardData.recentContacts && dashboardData.recentContacts.length > 0" class="divide-y divide-neutral-200 dark:divide-neutral-700">
                <router-link
                  v-for="contact in dashboardData.recentContacts"
                  :key="contact.id"
                  :to="`/admin/contacts`"
                  class="block p-4 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors"
                  @click="showNotifications = false"
                >
                  <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-primary-500 to-accent-500 rounded-full flex items-center justify-center text-white font-semibold">
                      {{ contact.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="font-semibold text-sm truncate">{{ contact.name }}</p>
                      <p class="text-sm text-neutral-600 dark:text-neutral-400 truncate">{{ contact.subject }}</p>
                      <p class="text-xs text-neutral-500 mt-1">{{ contact.time }}</p>
                    </div>
                  </div>
                </router-link>
              </div>
              <div v-else class="p-8 text-center text-neutral-500">
                <svg class="mx-auto w-12 h-12 mb-2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p class="text-sm">No new messages</p>
              </div>
            </div>
            <div class="p-3 border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900/50">
              <router-link
                to="/admin/contacts"
                class="block text-center text-sm font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
                @click="showNotifications = false"
              >
                View All Messages →
              </router-link>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="relative">
        <div class="w-20 h-20 border-4 border-primary-200 dark:border-primary-900 rounded-full"></div>
        <div class="absolute inset-0 w-20 h-20 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-950/20 dark:to-orange-950/20 border-2 border-red-200 dark:border-red-800 rounded-2xl p-6 mb-8">
      <div class="flex items-center gap-3 mb-2">
        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-red-600 dark:text-red-400 font-semibold">{{ error }}</p>
      </div>
      <BaseButton variant="outline" size="sm" @click="fetchDashboardData">
        Try Again
      </BaseButton>
    </div>

    <!-- Dashboard Content -->
    <template v-else>
      <!-- Stats Cards - Glassmorphism Style -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div
          v-for="(stat, index) in statsCards"
          :key="stat.label"
          class="stat-card group"
          :style="{ animationDelay: `${index * 100}ms` }"
        >
          <div class="relative h-full rounded-2xl overflow-hidden bg-white/80 dark:bg-neutral-900/80 backdrop-blur-xl border border-white/20 dark:border-neutral-700/30 shadow-xl hover:shadow-2xl transition-all duration-500 p-6 hover:scale-105">
            <!-- Gradient Background -->
            <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500" :class="stat.gradientBg"></div>
            
            <div class="relative z-10 flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-2">{{ stat.label }}</p>
                <p class="text-4xl font-bold mb-2" :class="stat.textColor">{{ stat.value }}</p>
                <div class="flex items-center gap-1 text-xs">
                  <svg v-if="stat.trend === 'up'" class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                  </svg>
                  <svg v-else class="w-4 h-4 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd"/>
                  </svg>
                  <span :class="stat.trend === 'up' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                    {{ stat.change }}%
                  </span>
                  <span class="text-neutral-500">vs last month</span>
                </div>
              </div>
              <div class="p-4 rounded-2xl group-hover:scale-110 transition-transform duration-500" :class="stat.iconBg">
                <component :is="stat.icon" class="h-8 w-8" :class="stat.iconColor" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Contacts - NEW & HIGHLIGHTED -->
        <div class="lg:col-span-2">
          <div class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-primary-500 via-secondary-500 to-accent-500 p-[2px] shadow-2xl">
            <div class="relative h-full rounded-2xl bg-white dark:bg-neutral-900 p-6">
              <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                  <div class="p-3 bg-gradient-to-br from-primary-500 to-accent-500 rounded-xl">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-2xl font-bold">Contact Messages</h2>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ unreadContactsCount }} unread messages</p>
                  </div>
                </div>
                <BaseButton variant="primary" @click="$router.push('/admin/contacts')">
                  View All
                </BaseButton>
              </div>
              
              <div v-if="dashboardData.recentContacts && dashboardData.recentContacts.length > 0" class="grid gap-4">
                <div
                  v-for="contact in dashboardData.recentContacts"
                  :key="contact.id"
                  class="group p-4 rounded-xl border-2 border-neutral-200 dark:border-neutral-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-lg transition-all duration-300 cursor-pointer"
                  @click="$router.push('/admin/contacts')"
                >
                  <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                      <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-accent-500 rounded-full flex items-center justify-center text-white font-bold text-lg">
                        {{ contact.name.charAt(0).toUpperCase() }}
                      </div>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="font-semibold text-lg group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                          {{ contact.name }}
                        </h3>
                        <span class="px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-semibold rounded-full">
                          NEW
                        </span>
                      </div>
                      <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-1">{{ contact.email }}</p>
                      <p class="font-medium text-neutral-900 dark:text-white mb-2">{{ contact.subject }}</p>
                      <div class="flex items-center gap-2 text-xs text-neutral-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ contact.time }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-12">
                <svg class="mx-auto w-16 h-16 text-neutral-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p class="text-neutral-600 dark:text-neutral-400 text-lg font-medium">No new messages</p>
                <p class="text-sm text-neutral-500 mt-1">All caught up!</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <BaseCard class="glassmorphism">
          <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            Recent Activity
          </h2>
          <div v-if="dashboardData.recentActivity && dashboardData.recentActivity.length > 0" class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
            <div v-for="activity in dashboardData.recentActivity" :key="activity.id" class="flex items-start gap-3 pb-4 border-b border-neutral-200 dark:border-neutral-700 last:border-0">
              <div class="p-2 rounded-lg" :class="getActivityStyle(activity.type).bgColor">
                <component :is="getActivityIcon(activity.type)" class="h-4 w-4" :class="getActivityStyle(activity.type).iconColor" />
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium">{{ activity.title }}</p>
                <p class="text-xs text-neutral-500">{{ activity.description }}</p>
                <p class="text-xs text-neutral-400 mt-1">{{ activity.time }}</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-neutral-500">
            No recent activity
          </div>
        </BaseCard>

        <!-- Quick Actions -->
        <BaseCard class="glassmorphism">
          <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            Quick Actions
          </h2>
          <div class="grid grid-cols-2 gap-4">
            <router-link
              v-for="action in quickActions"
              :key="action.label"
              :to="action.route"
              class="group p-4 rounded-xl border-2 border-neutral-200 dark:border-neutral-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-lg transition-all duration-300 text-left"
            >
              <component :is="action.icon" class="h-6 w-6 mb-2 text-primary-600 dark:text-primary-400 group-hover:scale-110 transition-transform" />
              <p class="font-medium text-sm">{{ action.label }}</p>
              <p class="text-xs text-neutral-500">{{ action.description }}</p>
            </router-link>
          </div>
        </BaseCard>
      </div>

      <!-- Content Tables -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Projects -->
        <BaseCard class="glassmorphism">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Recent Projects</h2>
            <BaseButton variant="outline" size="sm" @click="$router.push('/admin/projects')">View All</BaseButton>
          </div>
          <div v-if="dashboardData.recentProjects && dashboardData.recentProjects.length > 0" class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-neutral-200 dark:border-neutral-700">
                  <th class="text-left py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">Title</th>
                  <th class="text-left py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">Status</th>
                  <th class="text-left py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="project in dashboardData.recentProjects" :key="project.id" class="border-b border-neutral-200 dark:border-neutral-700 last:border-0 hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td class="py-3 text-sm font-medium">{{ project.title }}</td>
                  <td class="py-3">
                    <BaseBadge :variant="project.statusVariant" size="sm">
                      {{ project.status }}
                    </BaseBadge>
                  </td>
                  <td class="py-3 text-sm text-neutral-500">{{ project.date }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center py-8 text-neutral-500">
            No projects yet
          </div>
        </BaseCard>

        <!-- Recent Posts -->
        <BaseCard class="glassmorphism">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Recent Posts</h2>
            <BaseButton variant="outline" size="sm" @click="$router.push('/admin/posts')">View All</BaseButton>
          </div>
          <div v-if="dashboardData.recentPosts && dashboardData.recentPosts.length > 0" class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-neutral-200 dark:border-neutral-700">
                  <th class="text-left py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">Title</th>
                  <th class="text-left py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">Views</th>
                  <th class="text-left py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="post in dashboardData.recentPosts" :key="post.id" class="border-b border-neutral-200 dark:border-neutral-700 last:border-0 hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td class="py-3 text-sm font-medium">{{ post.title }}</td>
                  <td class="py-3 text-sm text-neutral-500">{{ post.views }}</td>
                  <td class="py-3 text-sm text-neutral-500">{{ post.date }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center py-8 text-neutral-500">
            No posts yet
          </div>
        </BaseCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { BaseCard, BaseButton, BaseBadge } from '@/components/base'
import api from '@/services/api'

const authStore = useAuthStore()

// State
const loading = ref(true)
const error = ref(null)
const showNotifications = ref(false)
const dashboardData = ref({
  stats: {
    projects: { total: 0, published: 0, draft: 0, change: { percent: 0, trend: 'neutral' } },
    posts: { total: 0, published: 0, draft: 0, change: { percent: 0, trend: 'neutral' } },
    gallery: { total: 0, change: { percent: 0, trend: 'neutral' } },
    views: { total: '0', change: { percent: 0, trend: 'neutral' } },
    contacts: { total: 0, unread: 0, change: { percent: 0, trend: 'neutral' } },
  },
  recentProjects: [],
  recentPosts: [],
  recentActivity: [],
  recentContacts: [],
})

// Auto-refresh interval
let refreshInterval = null

// Toggle notifications dropdown
const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
}

// Close notifications when clicking outside
const handleClickOutside = (event) => {
  const notificationBtn = event.target.closest('button')
  const notificationDropdown = event.target.closest('.absolute.right-0')
  
  if (!notificationBtn && !notificationDropdown && showNotifications.value) {
    showNotifications.value = false
  }
}

// Icon components
const ProjectIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>`
}
const BlogIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`
}
const GalleryIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`
}
const ViewsIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`
}
const ContactIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>`
}
const PlusIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>`
}
const EditIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>`
}
const UploadIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>`
}
const SettingsIcon = {
  template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`
}

// Computed
const unreadContactsCount = computed(() => dashboardData.value.stats.contacts.unread)

const statsCards = computed(() => [
  {
    label: 'Total Projects',
    value: dashboardData.value.stats.projects.total,
    change: dashboardData.value.stats.projects.change.percent,
    trend: dashboardData.value.stats.projects.change.trend,
    icon: ProjectIcon,
    iconBg: 'bg-blue-100 dark:bg-blue-900/30',
    iconColor: 'text-blue-600 dark:text-blue-400',
    textColor: 'text-blue-600 dark:text-blue-400',
    gradientBg: 'bg-gradient-to-br from-blue-500/10 to-indigo-500/10'
  },
  {
    label: 'Blog Posts',
    value: dashboardData.value.stats.posts.total,
    change: dashboardData.value.stats.posts.change.percent,
    trend: dashboardData.value.stats.posts.change.trend,
    icon: BlogIcon,
    iconBg: 'bg-green-100 dark:bg-green-900/30',
    iconColor: 'text-green-600 dark:text-green-400',
    textColor: 'text-green-600 dark:text-green-400',
    gradientBg: 'bg-gradient-to-br from-green-500/10 to-emerald-500/10'
  },
  {
    label: 'Gallery Items',
    value: dashboardData.value.stats.gallery.total,
    change: dashboardData.value.stats.gallery.change.percent,
    trend: dashboardData.value.stats.gallery.change.trend,
    icon: GalleryIcon,
    iconBg: 'bg-purple-100 dark:bg-purple-900/30',
    iconColor: 'text-purple-600 dark:text-purple-400',
    textColor: 'text-purple-600 dark:text-purple-400',
    gradientBg: 'bg-gradient-to-br from-purple-500/10 to-pink-500/10'
  },
  {
    label: 'Total Views',
    value: dashboardData.value.stats.views.total,
    change: dashboardData.value.stats.views.change.percent,
    trend: dashboardData.value.stats.views.change.trend,
    icon: ViewsIcon,
    iconBg: 'bg-orange-100 dark:bg-orange-900/30',
    iconColor: 'text-orange-600 dark:text-orange-400',
    textColor: 'text-orange-600 dark:text-orange-400',
    gradientBg: 'bg-gradient-to-br from-orange-500/10 to-amber-500/10'
  }
])

const quickActions = [
  {
    label: 'New Project',
    description: 'Create a new project',
    icon: PlusIcon,
    route: '/admin/projects/create'
  },
  {
    label: 'New Post',
    description: 'Write a blog post',
    icon: BlogIcon,
    route: '/admin/posts/create'
  },
  {
    label: 'Upload Image',
    description: 'Add to gallery',
    icon: UploadIcon,
    route: '/admin/gallery'
  },
  {
    label: 'Settings',
    description: 'Manage your site',
    icon: SettingsIcon,
    route: '/admin/settings'
  }
]

// Methods
const fetchDashboardData = async () => {
  try {
    loading.value = true
    error.value = null
    
    const response = await api.get('/admin/dashboard/stats')
    
    if (response.data.success) {
      dashboardData.value = response.data.data
    } else {
      error.value = response.data.error?.message || 'Failed to fetch dashboard data'
    }
  } catch (err) {
    error.value = err.response?.data?.error?.message || err.message || 'Failed to fetch dashboard data'
  } finally {
    loading.value = false
  }
}

const getActivityIcon = (type) => {
  switch (type) {
    case 'project':
      return ProjectIcon
    case 'post':
      return BlogIcon
    case 'gallery':
      return UploadIcon
    case 'contact':
      return ContactIcon
    default:
      return EditIcon
  }
}

const getActivityStyle = (type) => {
  switch (type) {
    case 'project':
      return {
        bgColor: 'bg-blue-50 dark:bg-blue-900/20',
        iconColor: 'text-blue-600 dark:text-blue-400'
      }
    case 'post':
      return {
        bgColor: 'bg-green-50 dark:bg-green-900/20',
        iconColor: 'text-green-600 dark:text-green-400'
      }
    case 'gallery':
      return {
        bgColor: 'bg-purple-50 dark:bg-purple-900/20',
        iconColor: 'text-purple-600 dark:text-purple-400'
      }
    case 'contact':
      return {
        bgColor: 'bg-red-50 dark:bg-red-900/20',
        iconColor: 'text-red-600 dark:text-red-400'
      }
    default:
      return {
        bgColor: 'bg-gray-50 dark:bg-gray-900/20',
        iconColor: 'text-gray-600 dark:text-gray-400'
      }
  }
}

// Lifecycle
onMounted(() => {
  fetchDashboardData()
  
  // Auto-refresh every 30 seconds
  refreshInterval = setInterval(fetchDashboardData, 30000)
  
  // Add click outside listener
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  // Clean up interval
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
  
  // Remove listener
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.6s ease-out forwards;
}

.animation-delay-100 {
  animation-delay: 100ms;
}

.animation-delay-200 {
  animation-delay: 200ms;
}

.stat-card {
  animation: fadeIn 0.6s ease-out forwards;
  opacity: 0;
}

/* Glassmorphism */
.glassmorphism {
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.dark .glassmorphism {
  background: rgba(23, 23, 23, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Dropdown Transition */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.3s ease;
}

.dropdown-enter-from {
  opacity: 0;
  transform: translateY(-10px) scale(0.95);
}

.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px) scale(0.95);
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.3);
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}
</style>
