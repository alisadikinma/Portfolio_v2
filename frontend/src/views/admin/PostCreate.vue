<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import BlogPostForm from '@/components/blog/BlogPostForm.vue'
import { usePosts } from '@/stores/posts'

const router = useRouter()
const postsStore = usePosts()

const isSubmitting = ref(false)

const handleSubmit = async (postData) => {
  isSubmitting.value = true

  try {
    const createdPost = await postsStore.createPost(postData)

    // Show success message
    alert(`Post "${createdPost.title}" ${postData.status === 'published' ? 'published' : 'saved as draft'} successfully!`)

    // Redirect to posts list or edit page
    router.push({ name: 'admin-posts' })
  } catch (error) {
    console.error('Error creating post:', error)
    alert('Failed to create post. Please try again.')
  } finally {
    isSubmitting.value = false
  }
}

const handleCancel = () => {
  if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
    router.push({ name: 'admin-posts' })
  }
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            Create New Post
          </h1>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Write and publish your blog post
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

    <!-- Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
      <BlogPostForm
        submit-label="Publish Post"
        cancel-label="Cancel"
        :is-submitting="isSubmitting"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>
  </div>
</template>
