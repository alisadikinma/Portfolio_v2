<template>
  <div>
    <BaseLoader v-if="isLoading" text="Loading post..." class="min-h-screen" />

    <div v-else-if="error" class="min-h-screen flex items-center justify-center">
      <BaseCard class="max-w-md text-center">
        <h2 class="text-2xl font-bold mb-4">Post Not Found</h2>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">
          The blog post you're looking for doesn't exist or has been removed.
        </p>
        <BaseButton variant="primary" @click="$router.push('/blog')">
          Back to Blog
        </BaseButton>
      </BaseCard>
    </div>

    <article v-else-if="post">
      <!-- Hero Section -->
      <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
        <div class="container-custom relative py-20">
          <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-2 mb-4">
              <BaseButton variant="outline" size="sm" @click="$router.push('/blog')">
                ← Back
              </BaseButton>
              <BaseBadge variant="info" size="sm">{{ post.category?.name }}</BaseBadge>
            </div>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6">
              {{ post.title }}
            </h1>
            <div class="flex items-center gap-4 text-neutral-600 dark:text-neutral-400">
              <span>{{ formatDate(post.published_at) }}</span>
              <span>•</span>
              <span>{{ post.read_time }} min read</span>
              <span>•</span>
              <span>{{ post.views || 0 }} views</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Featured Image -->
      <section class="section bg-neutral-50 dark:bg-neutral-900">
        <div class="container-custom">
          <div class="max-w-5xl mx-auto">
            <div class="aspect-video bg-neutral-200 dark:bg-neutral-700 rounded-2xl"></div>
          </div>
        </div>
      </section>

      <!-- Post Content -->
      <section class="section bg-white dark:bg-neutral-800">
        <div class="container-custom">
          <div class="max-w-4xl mx-auto grid md:grid-cols-4 gap-12">
            <!-- Share Sidebar -->
            <div class="md:col-span-1 order-2 md:order-1">
              <div class="sticky top-24 space-y-4">
                <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mb-3">Share this post</p>
                <div class="flex md:flex-col gap-3">
                  <button class="p-3 rounded-lg bg-neutral-100 dark:bg-neutral-700 hover:bg-neutral-200 dark:hover:bg-neutral-600 transition-colors">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                  </button>
                  <button class="p-3 rounded-lg bg-neutral-100 dark:bg-neutral-700 hover:bg-neutral-200 dark:hover:bg-neutral-600 transition-colors">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                  </button>
                  <button class="p-3 rounded-lg bg-neutral-100 dark:bg-neutral-700 hover:bg-neutral-200 dark:hover:bg-neutral-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Main Content -->
            <div class="md:col-span-3 order-1 md:order-2">
              <div class="prose prose-neutral dark:prose-invert max-w-none">
                <p class="lead text-xl text-neutral-600 dark:text-neutral-400 mb-8">
                  {{ post.excerpt }}
                </p>

                <div v-html="post.content || defaultContent" class="blog-content"></div>

                <!-- Tags -->
                <div v-if="post.tags?.length" class="mt-12 pt-8 border-t border-neutral-200 dark:border-neutral-700">
                  <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mb-3">Tags</p>
                  <div class="flex flex-wrap gap-2">
                    <BaseBadge v-for="tag in post.tags" :key="tag" variant="outline" size="sm">
                      {{ tag }}
                    </BaseBadge>
                  </div>
                </div>
              </div>

              <!-- Author Card -->
              <div class="mt-12 p-6 bg-neutral-50 dark:bg-neutral-900 rounded-2xl">
                <div class="flex items-start gap-4">
                  <div class="w-16 h-16 rounded-full bg-neutral-200 dark:bg-neutral-700 flex-shrink-0"></div>
                  <div>
                    <h3 class="text-lg font-semibold mb-1">{{ post.author?.name || 'Author Name' }}</h3>
                    <p class="text-neutral-600 dark:text-neutral-400 text-sm mb-3">
                      {{ post.author?.bio || 'Passionate developer and writer sharing knowledge through articles and tutorials.' }}
                    </p>
                    <BaseButton variant="outline" size="sm" @click="goToProfile">
                      View Profile
                    </BaseButton>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Related Posts -->
      <section class="section bg-neutral-50 dark:bg-neutral-900">
        <div class="container-custom">
          <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Related Posts</h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <BaseCard 
              v-for="i in 3" 
              :key="i" 
              hover 
              class="cursor-pointer"
              @click="goToRelatedPost(i)"
            >
              <div class="aspect-video bg-neutral-200 dark:bg-neutral-700 rounded-lg mb-4"></div>
              <BaseBadge variant="info" size="sm" class="mb-3">Category</BaseBadge>
              <h3 class="text-lg font-semibold mb-2">Related Post {{ i }}</h3>
              <p class="text-neutral-600 dark:text-neutral-400 text-sm">
                Another interesting article you might enjoy reading
              </p>
            </BaseCard>
          </div>
        </div>
      </section>
    </article>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePosts } from '@/composables/usePosts'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'

const route = useRoute()
const router = useRouter()
const { post, isLoading, error, fetchPost } = usePosts()

const defaultContent = `
  <h2>Introduction</h2>
  <p>This is a comprehensive guide covering important topics in web development. In this article, we'll explore key concepts, best practices, and practical examples that will help you improve your skills.</p>

  <h2>Key Concepts</h2>
  <p>Understanding the fundamentals is crucial for any developer. Let's dive into the core concepts that form the foundation of modern web development.</p>

  <h3>Performance Optimization</h3>
  <p>Performance is a critical aspect of web development. Users expect fast, responsive applications that provide a smooth experience across all devices.</p>

  <h3>Best Practices</h3>
  <p>Following industry best practices ensures your code is maintainable, scalable, and efficient. This includes proper code organization, testing, and documentation.</p>

  <h2>Practical Examples</h2>
  <p>Let's look at some practical examples that demonstrate these concepts in action. These examples will help you understand how to apply what you've learned.</p>

  <h2>Conclusion</h2>
  <p>By understanding these concepts and applying them in your projects, you'll be able to create better, more efficient applications. Keep learning and experimenting with new technologies to stay ahead in the field.</p>
`

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// Handler for View Profile button
const goToProfile = () => {
  // TODO: Navigate to author profile page
  // For now, navigate to about page or show a toast
  console.log('Navigate to profile:', post.value?.author?.name)
  router.push('/about')
}

// Handler for Related Post cards
const goToRelatedPost = (postIndex) => {
  // TODO: Navigate to actual related post
  // For now, just log and show a placeholder
  console.log('Navigate to related post:', postIndex)
  // You can replace this with actual related post slug when you have the data
  // router.push(`/blog/${relatedPost.slug}`)
}

onMounted(async () => {
  const slug = route.params.slug
  await fetchPost(slug)

  if (error.value) {
    console.error('Failed to load post:', error.value)
  }
})
</script>

<style scoped>
/* Use Tailwind typography plugin styles */
.prose {
  color: #404040;
}

.dark .prose {
  color: #d4d4d4;
}

.prose :deep(h2) {
  font-size: 1.5rem;
  font-weight: 700;
  margin-top: 2rem;
  margin-bottom: 1rem;
  color: #171717;
}

.dark .prose :deep(h2) {
  color: #fafafa;
}

.prose :deep(h3) {
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: #171717;
}

.dark .prose :deep(h3) {
  color: #fafafa;
}

.prose :deep(p) {
  margin-bottom: 1rem;
  line-height: 1.75;
}

.prose :deep(ul), 
.prose :deep(ol) {
  margin-bottom: 1rem;
  padding-left: 1.5rem;
}

.prose :deep(li) {
  margin-bottom: 0.5rem;
}

.prose :deep(a) {
  color: #2563eb;
  text-decoration: underline;
}

.prose :deep(a):hover {
  color: #1d4ed8;
}

.dark .prose :deep(a) {
  color: #60a5fa;
}

.dark .prose :deep(a):hover {
  color: #93c5fd;
}

.prose :deep(code) {
  padding: 0.25rem 0.5rem;
  background-color: #f5f5f5;
  border-radius: 0.25rem;
  font-size: 0.875rem;
}

.dark .prose :deep(code) {
  background-color: #262626;
}

.prose :deep(pre) {
  padding: 1rem;
  background-color: #f5f5f5;
  border-radius: 0.5rem;
  overflow-x: auto;
  margin-bottom: 1rem;
}

.dark .prose :deep(pre) {
  background-color: #262626;
}

.blog-content {
  color: inherit;
}
</style>
