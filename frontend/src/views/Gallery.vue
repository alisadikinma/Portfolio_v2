<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            <span class="text-gradient">Gallery</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            A visual showcase of creative work and inspiration
          </p>
        </div>
      </div>
    </section>

    <!-- Filters Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="flex flex-wrap gap-4 justify-center mb-8">
          <BaseButton
            v-for="filter in filters"
            :key="filter.value"
            :variant="activeFilter === filter.value ? 'primary' : 'outline'"
            @click="activeFilter = filter.value"
          >
            {{ filter.label }}
          </BaseButton>
        </div>

        <BaseLoader v-if="isLoading" text="Loading gallery..." />

        <div v-else-if="filteredItems.length === 0" class="text-center py-12">
          <p class="text-neutral-600 dark:text-neutral-400">No gallery items found.</p>
        </div>

        <div v-else>
          <!-- Masonry Grid -->
          <div class="columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6">
            <div
              v-for="item in paginatedItems"
              :key="item.id"
              class="break-inside-avoid"
            >
              <BaseCard
                hover
                class="cursor-pointer overflow-hidden"
                @click="openLightbox(item)"
              >
                <div
                  class="bg-neutral-200 dark:bg-neutral-700 rounded-lg"
                  :style="{ aspectRatio: item.aspectRatio || '1/1' }"
                ></div>
                <div class="p-4">
                  <div class="flex items-center gap-2 mb-2">
                    <BaseBadge :variant="getCategoryVariant(item.category)" size="sm">
                      {{ item.category }}
                    </BaseBadge>
                    <span class="text-sm text-neutral-500">{{ formatDate(item.created_at) }}</span>
                  </div>
                  <h3 class="font-semibold mb-1">{{ item.title }}</h3>
                  <p class="text-neutral-600 dark:text-neutral-400 text-sm">
                    {{ item.description }}
                  </p>
                </div>
              </BaseCard>
            </div>
          </div>

          <!-- Load More -->
          <div v-if="hasMore" class="text-center mt-12">
            <BaseButton variant="outline" @click="loadMore">
              Load More
            </BaseButton>
          </div>
        </div>
      </div>
    </section>

    <!-- Lightbox Modal -->
    <Transition name="fade">
      <div
        v-if="lightboxItem"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
        @click="closeLightbox"
      >
        <div class="relative max-w-6xl w-full">
          <button
            class="absolute -top-12 right-0 text-white hover:text-neutral-300 transition-colors"
            @click="closeLightbox"
          >
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <div
            class="bg-white dark:bg-neutral-800 rounded-2xl overflow-hidden"
            @click.stop
          >
            <div class="aspect-video bg-neutral-200 dark:bg-neutral-700"></div>
            <div class="p-6">
              <div class="flex items-center gap-2 mb-4">
                <BaseBadge :variant="getCategoryVariant(lightboxItem.category)" size="sm">
                  {{ lightboxItem.category }}
                </BaseBadge>
                <span class="text-sm text-neutral-500">{{ formatDate(lightboxItem.created_at) }}</span>
              </div>
              <h2 class="text-2xl font-bold mb-2">{{ lightboxItem.title }}</h2>
              <p class="text-neutral-600 dark:text-neutral-400">{{ lightboxItem.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'

const isLoading = ref(false)
const activeFilter = ref('all')
const lightboxItem = ref(null)
const currentPage = ref(1)
const perPage = 12

const filters = [
  { label: 'All', value: 'all' },
  { label: 'Design', value: 'design' },
  { label: 'Photography', value: 'photography' },
  { label: 'Illustrations', value: 'illustrations' },
  { label: 'UI/UX', value: 'ui-ux' }
]

// Mock gallery items
const galleryItems = ref([
  { id: 1, title: 'Modern Dashboard', description: 'Clean and intuitive dashboard design', category: 'UI/UX', created_at: '2024-01-15', aspectRatio: '16/9' },
  { id: 2, title: 'Sunset Landscape', description: 'Beautiful sunset photography', category: 'Photography', created_at: '2024-01-14', aspectRatio: '4/3' },
  { id: 3, title: 'Character Sketch', description: 'Digital character illustration', category: 'Illustrations', created_at: '2024-01-13', aspectRatio: '3/4' },
  { id: 4, title: 'Brand Identity', description: 'Complete brand identity design', category: 'Design', created_at: '2024-01-12', aspectRatio: '1/1' },
  { id: 5, title: 'Mobile App UI', description: 'Mobile application interface', category: 'UI/UX', created_at: '2024-01-11', aspectRatio: '9/16' },
  { id: 6, title: 'Urban Architecture', description: 'Modern building photography', category: 'Photography', created_at: '2024-01-10', aspectRatio: '16/9' },
  { id: 7, title: 'Vector Art', description: 'Geometric vector illustration', category: 'Illustrations', created_at: '2024-01-09', aspectRatio: '1/1' },
  { id: 8, title: 'Logo Collection', description: 'Various logo designs', category: 'Design', created_at: '2024-01-08', aspectRatio: '4/3' },
  { id: 9, title: 'Web Interface', description: 'Responsive web design', category: 'UI/UX', created_at: '2024-01-07', aspectRatio: '16/9' },
  { id: 10, title: 'Nature Scene', description: 'Forest landscape photo', category: 'Photography', created_at: '2024-01-06', aspectRatio: '3/2' },
  { id: 11, title: 'Portrait Art', description: 'Digital portrait illustration', category: 'Illustrations', created_at: '2024-01-05', aspectRatio: '3/4' },
  { id: 12, title: 'Poster Design', description: 'Event poster design', category: 'Design', created_at: '2024-01-04', aspectRatio: '2/3' }
])

const filteredItems = computed(() => {
  if (activeFilter.value === 'all') {
    return galleryItems.value
  }
  return galleryItems.value.filter(item =>
    item.category.toLowerCase().replace(/\//g, '-') === activeFilter.value
  )
})

const paginatedItems = computed(() => {
  return filteredItems.value.slice(0, currentPage.value * perPage)
})

const hasMore = computed(() => {
  return paginatedItems.value.length < filteredItems.value.length
})

const getCategoryVariant = (category) => {
  const variants = {
    'Design': 'primary',
    'Photography': 'success',
    'Illustrations': 'warning',
    'UI/UX': 'info'
  }
  return variants[category] || 'outline'
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const openLightbox = (item) => {
  lightboxItem.value = item
}

const closeLightbox = () => {
  lightboxItem.value = null
}

const loadMore = () => {
  currentPage.value++
}

onMounted(async () => {
  isLoading.value = true
  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 500))
  isLoading.value = false
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
