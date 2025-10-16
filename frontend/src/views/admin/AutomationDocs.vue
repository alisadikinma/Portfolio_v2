<template>
  <div class="automation-docs-page">
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
        Automation API Documentation
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Complete API reference for n8n, Zapier, and Make.com integrations
      </p>
    </div>

    <!-- Quick Start -->
    <div class="mb-8 rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-900 dark:bg-blue-950">
      <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-100">Quick Start</h2>
      <ol class="mt-4 space-y-2 text-sm text-blue-800 dark:text-blue-200">
        <li>1. Create an API token in the <router-link to="/admin/automation/tokens" class="underline">Tokens page</router-link></li>
        <li>2. Copy the token (shown only once!)</li>
        <li>3. Use the token in your automation platform headers</li>
        <li>4. Start making API requests to the endpoints below</li>
      </ol>
    </div>

    <!-- Authentication -->
    <div class="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Authentication</h2>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        All requests must include the API token in the Authorization header:
      </p>
      <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>Authorization: Bearer YOUR_API_TOKEN</code></pre>

      <div class="mt-4 rounded-lg bg-yellow-50 p-4 dark:bg-yellow-950">
        <p class="text-sm text-yellow-800 dark:text-yellow-200">
          <strong>Rate Limit:</strong> 60 requests per minute per token
        </p>
      </div>
    </div>

    <!-- Endpoints -->
    <div class="space-y-6">
      <!-- Get Posts -->
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center space-x-3">
          <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">GET</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts</h3>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Get list of posts with filters</p>

        <div class="mt-4">
          <h4 class="font-medium text-gray-900 dark:text-white">Query Parameters:</h4>
          <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li><code class="text-xs">published</code> - true/false</li>
            <li><code class="text-xs">category_id</code> - integer</li>
            <li><code class="text-xs">search</code> - string</li>
            <li><code class="text-xs">per_page</code> - integer (max 100)</li>
            <li><code class="text-xs">page</code> - integer</li>
          </ul>
        </div>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ getPostsExample }}</code></pre>
      </div>

      <!-- Create Post -->
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center space-x-3">
          <span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">POST</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts</h3>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Create a new post (simplified validation)</p>

        <div class="mt-4">
          <h4 class="font-medium text-gray-900 dark:text-white">Required Fields:</h4>
          <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li><code class="text-xs">title</code> - string (max 255)</li>
            <li><code class="text-xs">content</code> - string</li>
            <li><code class="text-xs">category_id</code> - integer</li>
          </ul>

          <h4 class="mt-4 font-medium text-gray-900 dark:text-white">Optional Fields:</h4>
          <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li><code class="text-xs">slug</code> - string (auto-generated if not provided)</li>
            <li><code class="text-xs">excerpt</code> - string (auto-generated if not provided)</li>
            <li><code class="text-xs">featured_image</code> - string (URL or base64)</li>
            <li><code class="text-xs">tags</code> - array of strings</li>
            <li><code class="text-xs">published</code> - boolean (default: false)</li>
            <li><code class="text-xs">published_at</code> - datetime (auto-set if published)</li>
          </ul>
        </div>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ createPostExample }}</code></pre>
      </div>

      <!-- Bulk Create -->
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center space-x-3">
          <span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">POST</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts/bulk</h3>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Create multiple posts at once (up to 50)</p>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ bulkCreateExample }}</code></pre>
      </div>

      <!-- Update Post -->
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center space-x-3">
          <span class="rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">PUT</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts/:id</h3>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Update an existing post</p>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ updatePostExample }}</code></pre>
      </div>

      <!-- Delete Post -->
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center space-x-3">
          <span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-800 dark:bg-red-900 dark:text-red-200">DELETE</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts/:id</h3>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Delete a post</p>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ deletePostExample }}</code></pre>
      </div>

      <!-- Get Categories -->
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center space-x-3">
          <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">GET</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/categories</h3>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Get all blog categories</p>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ getCategoriesExample }}</code></pre>
      </div>
    </div>

    <!-- n8n Workflow Templates -->
    <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">n8n Workflow Templates</h2>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Common workflow patterns for n8n automation platform
      </p>

      <div class="mt-6 space-y-4">
        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
          <h3 class="font-semibold text-gray-900 dark:text-white">1. RSS Feed to Blog</h3>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Trigger: RSS Feed Read → HTTP Request (POST /automation/posts)
          </p>
        </div>

        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
          <h3 class="font-semibold text-gray-900 dark:text-white">2. Email to Draft Post</h3>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Trigger: Gmail → Parse Email → HTTP Request (status: draft)
          </p>
        </div>

        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
          <h3 class="font-semibold text-gray-900 dark:text-white">3. AI Content Generation</h3>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Trigger: Schedule → OpenAI → HTTP Request (create post)
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const baseUrl = 'http://localhost/Portfolio_v2/backend/public/api'

const getPostsExample = `GET ${baseUrl}/automation/posts?published=true&per_page=10

Response:
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 50
  }
}`

const createPostExample = `POST ${baseUrl}/automation/posts
Content-Type: application/json

{
  "title": "My New Post",
  "content": "<p>Post content here...</p>",
  "category_id": 1,
  "excerpt": "Short description",
  "tags": ["tag1", "tag2"],
  "published": true
}

Response:
{
  "success": true,
  "data": { ... },
  "message": "Post created successfully"
}`

const bulkCreateExample = `POST ${baseUrl}/automation/posts/bulk
Content-Type: application/json

{
  "posts": [
    {
      "title": "Post 1",
      "content": "Content 1",
      "category_id": 1
    },
    {
      "title": "Post 2",
      "content": "Content 2",
      "category_id": 2
    }
  ]
}

Response:
{
  "success": true,
  "data": {
    "created": [...],
    "errors": [...]
  },
  "meta": {
    "total_created": 2,
    "total_failed": 0
  }
}`

const updatePostExample = `PUT ${baseUrl}/automation/posts/123
Content-Type: application/json

{
  "title": "Updated Title",
  "published": true
}

Response:
{
  "success": true,
  "data": { ... },
  "message": "Post updated successfully"
}`

const deletePostExample = `DELETE ${baseUrl}/automation/posts/123

Response:
{
  "success": true,
  "message": "Post deleted successfully"
}`

const getCategoriesExample = `GET ${baseUrl}/automation/categories

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Technology",
      "slug": "technology"
    },
    ...
  ]
}`
</script>
</template>
