<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <BaseButton variant="outline" size="sm" @click="$router.push('/blog')" class="mb-6">
            ← All Posts
          </BaseButton>
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            <span class="text-gradient">{{ categoryName }}</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            {{ categoryDescription }}
          </p>
        </div>
      </div>
    </section>

    <!-- Posts Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <BaseLoader v-if="isLoading" text="Loading posts..." />

        <div v-else-if="filteredPosts.length === 0" class="text-center py-12">
          <BaseCard class="max-w-md mx-auto">
            <p class="text-neutral-600 dark:text-neutral-400 mb-6">
              No posts found in this category yet.
            </p>
            <BaseButton variant="primary" @click="$router.push('/blog')">
              Browse All Posts
            </BaseButton>
          </BaseCard>
        </div>

        <div v-else>
          <div class="flex items-center justify-between mb-8">
            <p class="text-neutral-600 dark:text-neutral-400">
              {{ filteredPosts.length }} {{ filteredPosts.length === 1 ? 'post' : 'posts' }} found
            </p>
            <div class="flex gap-2">
              <BaseButton
                variant="outline"
                size="sm"
                :class="{ 'bg-primary-50 dark:bg-primary-900/20': sortBy === 'latest' }"
                @click="sortBy = 'latest'"
              >
                Latest
              </BaseButton>
              <BaseButton
                variant="outline"
                size="sm"
                :class="{ 'bg-primary-50 dark:bg-primary-900/20': sortBy === 'popular' }"
                @click="sortBy = 'popular'"
              >
                Popular
              </BaseButton>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <BaseCard
              v-for="post in paginatedPosts"
              :key="post.id"
              hover
              class="cursor-pointer"
              @click="$router.push(`/blog/${post.slug}`)"
            >
              <div class="aspect-video bg-neutral-200 dark:bg-neutral-700 rounded-lg mb-4"></div>
              <div class="flex items-center gap-2 mb-3">
                <BaseBadge variant="info" size="sm">{{ categoryName }}</BaseBadge>
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
      </div>
    </section>

    <!-- Other Categories Section -->
    <section class="section bg-neutral-50 dark:bg-neutral-900">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Explore Other Categories</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 max-w-4xl mx-auto">
          <BaseCard
            v-for="category in otherCategories"
            :key="category.slug"
            hover
            class="cursor-pointer text-center"
            @click="$router.push(`/blog/category/${category.slug}`)"
          >
            <h3 class="font-semibold mb-1">{{ category.name }}</h3>
            <p class="text-sm text-neutral-500">{{ category.count }} posts</p>
          </BaseCard>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePosts } from '@/composables/usePosts'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'

const route = useRoute()
const router = useRouter()
const { posts, isLoading, fetchPosts } = usePosts()

const sortBy = ref('latest')
const currentPage = ref(1)
const perPage = 9

const categorySlug = computed(() => route.params.category)

const categories = {
  'web-development': {
    name: 'Web Development',
    description: 'Articles about modern web development, frameworks, and best practices'
  },
  'javascript': {
    name: 'JavaScript',
    description: 'Deep dives into JavaScript, ES6+, and modern JS patterns'
  },
  'vue-js': {
    name: 'Vue.js',
    description: 'Vue.js tutorials, tips, and advanced techniques'
  },
  'design': {
    name: 'Design',
    description: 'UI/UX design principles, trends, and inspiration'
  },
  'career': {
    name: 'Career',
    description: 'Career advice, productivity tips, and professional growth'
  }
}

const categoryName = computed(() => {
  return categories[categorySlug.value]?.name || 'Category'
})

const categoryDescription = computed(() => {
  return categories[categorySlug.value]?.description || 'Explore articles in this category'
})

const filteredPosts = computed(() => {
  let filtered = posts.value.filter(post =>
    post.category?.slug === categorySlug.value ||
    post.category?.name.toLowerCase().replace(/\s+/g, '-') === categorySlug.value
  )

  if (sortBy.value === 'popular') {
    filtered = [...filtered].sort((a, b) => (b.views || 0) - (a.views || 0))
  } else {
    filtered = [...filtered].sort((a, b) =>
      new Date(b.published_at) - new Date(a.published_at)
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

const otherCategories = computed(() => {
  return Object.entries(categories)
    .filter(([slug]) => slug !== categorySlug.value)
    .map(([slug, data]) => ({
      slug,
      name: data.name,
      count: Math.floor(Math.random() * 20) + 5 // Mock count
    }))
})

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

watch(categorySlug, () => {
  currentPage.value = 1
})

onMounted(async () => {
  await fetchPosts()
})
</script>
