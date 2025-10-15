<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import BlogPostForm from '@/components/blog/BlogPostForm.vue'
import { usePostsStore } from '@/stores/posts'

const router = useRouter()
const route = useRoute()
const postsStore = usePostsStore()

const isSubmitting = ref(false)
const isLoading = ref(true)
const post = ref(null)
const error = ref(null)

const postId = computed(() => route.params.id)

const fetchPost = async () => {
  isLoading.value = true
  error.value = null

  try {
    post.value = await postsStore.fetchPost(postId.value)
  } catch (err) {
    console.error('Error fetching post:', err)
    error.value = 'Failed to load post. Please try again.'
  } finally {
    isLoading.value = false
  }
}

const handleSubmit = async (postData) => {
  isSubmitting.value = true

  try {
    const updatedPost = await postsStore.updatePost(postId.value, postData)

    // Show success message
    alert(`Post "${updatedPost.title}" ${postData.status === 'published' ? 'published' : 'updated'} successfully!`)

    // Redirect to posts list
    router.push({ name: 'admin-posts' })
  } catch (error) {
    console.error('Error updating post:', error)
    alert('Failed to update post. Please try again.')
  } finally {
    isSubmitting.value = false
  }
}

const handleCancel = () => {
  if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
    router.push({ name: 'admin-posts' })
  }
}

onMounted(() => {
  fetchPost()
})
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            Edit Post
          </h1>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Update your blog post
          </p>
        </div>

        <!-- Back Button -->
        <button
          type="button"
          class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
          @click="handleCancel"
        >
          <svg
            class="h-5 w-5 mr-2"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10 19l-7-7m0 0l7-7m-7 7h18"
            />
          </svg>
          Back to Posts
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-12"
    >
      <div class="flex items-center justify-center">
        <svg
          class="animate-spin h-12 w-12 text-blue-600"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        <span class="ml-4 text-lg text-gray-600 dark:text-gray-400">
          Loading post...
        </span>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-12"
    >
      <div class="text-center">
        <svg
          class="mx-auto h-12 w-12 text-red-500"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
          Failed to Load Post
        </h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          {{ error }}
        </p>
        <div class="mt-6 flex justify-center space-x-4">
          <button
            type="button"
            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
            @click="fetchPost"
          >
            Try Again
          </button>
          <button
            type="button"
            class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            @click="handleCancel"
          >
            Go Back
          </button>
        </div>
      </div>
    </div>

    <!-- Form -->
    <div
      v-else-if="post"
      class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6"
    >
      <BlogPostForm
        :post="post"
        submit-label="Update Post"
        cancel-label="Cancel"
        :is-submitting="isSubmitting"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>
  </div>
</template>
