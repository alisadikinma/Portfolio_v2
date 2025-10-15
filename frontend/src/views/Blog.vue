<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            <span class="text-gradient">Blog</span> & Articles
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            Thoughts, tutorials, and insights on web development
          </p>
        </div>
      </div>
    </section>

    <!-- Filters Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-8">
          <div class="flex flex-wrap gap-2 justify-center md:justify-start">
            <BaseButton
              variant="outline"
              size="sm"
              :class="{ 'bg-primary-50 dark:bg-primary-900/20': !selectedCategory }"
              @click="selectedCategory = null"
            >
              All Posts
            </BaseButton>
            <BaseButton
              v-for="category in categories"
              :key="category.id"
              variant="outline"
              size="sm"
              :class="{ 'bg-primary-50 dark:bg-primary-900/20': selectedCategory === category.id }"
              @click="selectedCategory = category.id"
            >
              {{ category.name }}
            </BaseButton>
          </div>

          <div class="relative w-full md:w-64">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search posts..."
              class="w-full px-4 py-2 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <BaseLoader v-if="isLoading" text="Loading posts..." />

        <div v-else-if="filteredPosts.length === 0" class="text-center py-12">
          <p class="text-neutral-600 dark:text-neutral-400">No posts found.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <BaseCard
            v-for="post in paginatedPosts"
            :key="post.id"
            hover
            class="cursor-pointer"
            @click="$router.push(`/blog/${post.slug}`)"
          >
            <div class="aspect-video bg-neutral-200 dark:bg-neutral-700 rounded-lg mb-4"></div>
            <div class="flex items-center gap-2 mb-3">
              <BaseBadge variant="info" size="sm">{{ post.category?.name }}</BaseBadge>
              <span class="text-sm text-neutral-500">{{ formatDate(post.published_at) }}</span>
            </div>
            <h3 class="text-xl font-semibold mb-2">{{ post.title }}</h3>
            <p class="text-neutral-600 dark:text-neutral-400 text-sm line-clamp-3 mb-4">
              {{ post.excerpt }}
            </p>
            <div class="flex items-center gap-3 text-sm text-neutral-500">
              <span>{{ post.read_time }} min read</span>
              <span>•</span>
              <span>{{ post.views || 0 }} views</span>
            </div>
          </BaseCard>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex justify-center gap-2 mt-12">
          <BaseButton
            variant="outline"
            size="sm"
            :disabled="currentPage === 1"
            @click="currentPage--"
          >
            Previous
          </BaseButton>
          <BaseButton
            v-for="page in displayedPages"
            :key="page"
            :variant="currentPage === page ? 'primary' : 'outline'"
            size="sm"
            @click="currentPage = page"
          >
            {{ page }}
          </BaseButton>
          <BaseButton
            variant="outline"
            size="sm"
            :disabled="currentPage === totalPages"
            @click="currentPage++"
          >
            Next
          </BaseButton>
        </div>
      </div>
    </section>
    <div class="h-10"></div>
    <!-- Newsletter Section -->
    <section class="section bg-gradient-to-r from-primary-600 to-accent-600 text-white">
      <div class="h-10"></div>
      <div class="container-custom text-center mt-8">
        <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Stay Updated</h2>
        <p class="text-lg md:text-xl mb-8 opacity-90 max-w-2xl mx-auto">
          Get the latest articles and tutorials delivered to your inbox.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 max-w-md mx-auto">
          <input
            type="email"
            placeholder="Enter your email"
            class="flex-1 px-4 py-3 rounded-lg text-neutral-900 focus:outline-none focus:ring-2 focus:ring-white"
          />
          <BaseButton variant="secondary" size="lg">
            Subscribe
          </BaseButton>
        </div>
      </div>
      <div class="h-10"></div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePosts } from '@/composables/usePosts'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'

const { posts, isLoading, fetchPosts } = usePosts()

const selectedCategory = ref(null)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 9

const categories = [
  { id: 1, name: 'Web Development' },
  { id: 2, name: 'JavaScript' },
  { id: 3, name: 'Vue.js' },
  { id: 4, name: 'Design' },
  { id: 5, name: 'Career' }
]

const filteredPosts = computed(() => {
  let filtered = posts.value

  if (selectedCategory.value) {
    filtered = filtered.filter(p => p.category?.id === selectedCategory.value)
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(p =>
      p.title.toLowerCase().includes(query) ||
      p.excerpt?.toLowerCase().includes(query)
    )
  }

  return filtered
})

const totalPages = computed(() => {
  return Math.ceil(filteredPosts.value.length / perPage)
})

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage
  const end = start + perPage
  return filteredPosts.value.slice(start, end)
})

const displayedPages = computed(() => {
  const pages = []
  const maxPages = 5
  let startPage = Math.max(1, currentPage.value - 2)
  let endPage = Math.min(totalPages.value, startPage + maxPages - 1)

  if (endPage - startPage < maxPages - 1) {
    startPage = Math.max(1, endPage - maxPages + 1)
  }

  for (let i = startPage; i <= endPage; i++) {
    pages.push(i)
  }
  return pages
})

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

watch([selectedCategory, searchQuery], () => {
  currentPage.value = 1
})

onMounted(async () => {
  await fetchPosts()
})
</script>
