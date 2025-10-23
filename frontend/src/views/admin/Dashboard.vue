<template>
  <div>
    <!-- Welcome Section -->
    <div class="mb-8">
      <h1 class="text-3xl font-display font-bold mb-2">
        Welcome back, {{ authStore.user?.name || 'Admin' }}!
      </h1>
      <p class="text-neutral-600 dark:text-neutral-400">
        Here's what's happening with your portfolio today.
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-8">
      <p class="text-red-600 dark:text-red-400">{{ error }}</p>
      <button @click="fetchDashboardData" class="mt-2 text-sm text-red-600 dark:text-red-400 underline">
        Try Again
      </button>
    </div>

    <!-- Dashboard Content -->
    <template v-else>
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <BaseCard v-for="stat in statsCards" :key="stat.label" class="hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-1">{{ stat.label }}</p>
              <p class="text-3xl font-bold text-gradient">{{ stat.value }}</p>
              <p class="text-xs text-neutral-500 mt-1">
                <span :class="stat.trend === 'up' ? 'text-green-600' : 'text-red-600'">
                  {{ stat.trend === 'up' ? '↑' : '↓' }} {{ stat.change }}%
                </span>
                vs last month
              </p>
            </div>
            <div class="p-3 rounded-lg" :class="stat.bgColor">
              <component :is="stat.icon" class="h-8 w-8" :class="stat.iconColor" />
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Activity -->
        <BaseCard>
          <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
          <div v-if="dashboardData.recentActivity && dashboardData.recentActivity.length > 0" class="space-y-4">
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
        <BaseCard>
          <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
          <div class="grid grid-cols-2 gap-4">
            <router-link
              v-for="action in quickActions"
              :key="action.label"
              :to="action.route"
              class="p-4 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors text-left"
            >
              <component :is="action.icon" class="h-6 w-6 mb-2 text-primary-600 dark:text-primary-400" />
              <p class="font-medium text-sm">{{ action.label }}</p>
              <p class="text-xs text-neutral-500">{{ action.description }}</p>
            </router-link>
          </div>
        </BaseCard>
      </div>

      <!-- Content Tables -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Projects -->
        <BaseCard>
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
                <tr v-for="project in dashboardData.recentProjects" :key="project.id" class="border-b border-neutral-200 dark:border-neutral-700 last:border-0">
                  <td class="py-3 text-sm">{{ project.title }}</td>
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
        <BaseCard>
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
                <tr v-for="post in dashboardData.recentPosts" :key="post.id" class="border-b border-neutral-200 dark:border-neutral-700 last:border-0">
                  <td class="py-3 text-sm">{{ post.title }}</td>
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
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { BaseCard, BaseButton, BaseBadge } from '@/components/base'
import api from '@/services/api'

const authStore = useAuthStore()

// State
const loading = ref(true)
const error = ref(null)
const dashboardData = ref({
  stats: {
    projects: { total: 0, published: 0, draft: 0, change: { percent: 0, trend: 'neutral' } },
    posts: { total: 0, published: 0, draft: 0, change: { percent: 0, trend: 'neutral' } },
    gallery: { total: 0, change: { percent: 0, trend: 'neutral' } },
    views: { total: '0', change: { percent: 0, trend: 'neutral' } },
  },
  recentProjects: [],
  recentPosts: [],
  recentActivity: [],
})

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
const statsCards = computed(() => [
  {
    label: 'Total Projects',
    value: dashboardData.value.stats.projects.total,
    change: dashboardData.value.stats.projects.change.percent,
    trend: dashboardData.value.stats.projects.change.trend,
    icon: ProjectIcon,
    bgColor: 'bg-blue-50 dark:bg-blue-900/20',
    iconColor: 'text-blue-600 dark:text-blue-400'
  },
  {
    label: 'Blog Posts',
    value: dashboardData.value.stats.posts.total,
    change: dashboardData.value.stats.posts.change.percent,
    trend: dashboardData.value.stats.posts.change.trend,
    icon: BlogIcon,
    bgColor: 'bg-green-50 dark:bg-green-900/20',
    iconColor: 'text-green-600 dark:text-green-400'
  },
  {
    label: 'Gallery Items',
    value: dashboardData.value.stats.gallery.total,
    change: dashboardData.value.stats.gallery.change.percent,
    trend: dashboardData.value.stats.gallery.change.trend,
    icon: GalleryIcon,
    bgColor: 'bg-purple-50 dark:bg-purple-900/20',
    iconColor: 'text-purple-600 dark:text-purple-400'
  },
  {
    label: 'Total Views',
    value: dashboardData.value.stats.views.total,
    change: dashboardData.value.stats.views.change.percent,
    trend: dashboardData.value.stats.views.change.trend,
    icon: ViewsIcon,
    bgColor: 'bg-orange-50 dark:bg-orange-900/20',
    iconColor: 'text-orange-600 dark:text-orange-400'
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
})
</script>
