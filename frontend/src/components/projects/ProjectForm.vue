<template>
  <form @submit.prevent="handleSubmit" novalidate class="space-y-6">
    <!-- Title and Slug -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <BaseInput
          v-model="formData.title"
          label="Project Title"
          type="text"
          placeholder="Enter project title"
          required
          :error="errors.title"
          @blur="validateField('title')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.slug"
          label="URL Slug"
          type="text"
          placeholder="auto-generated-from-title"
          :error="errors.slug"
          @blur="validateField('slug')"
        >
          <template #help>
            Auto-generated from title. Edit only if needed.
          </template>
        </BaseInput>
      </div>
    </div>

    <!-- Description (Simple Textarea) -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Description <span class="text-red-500">*</span>
      </label>
      <textarea
        v-model="formData.description"
        rows="2"
        placeholder="Brief description of the project (short summary)"
        class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
        :class="{ 'border-red-500': errors.description }"
        @blur="validateField('description')"
      ></textarea>
      <p v-if="errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.description }}
      </p>
      <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
        Short summary for project cards and listings
      </p>
    </div>

    <!-- Content (Rich Text Editor) -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Content
      </label>
      <RichTextEditor
        v-model="formData.content"
        placeholder="Write detailed project content with formatting..."
        min-height="400px"
        :error="errors.content"
        @blur="validateField('content')"
      />
      <p v-if="errors.content" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.content }}
      </p>
      <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
        Full project details with HTML formatting for the detail page (optional)
      </p>
    </div>

    <!-- Image Thumbnail with Preview (Side by Side Layout) -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Image Thumbnail
      </label>
      
      <div class="flex gap-4">
        <!-- Image Preview -->
        <div class="flex-shrink-0">
          <!-- Current Image -->
          <div v-if="formData.current_featured_image && !imagePreview">
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-2">Current Image:</p>
            <div class="relative inline-block">
              <img 
                :src="formData.current_featured_image" 
                alt="Current thumbnail"
                class="w-48 h-32 object-cover rounded-lg border border-neutral-300 dark:border-neutral-600"
              />
              <button
                type="button"
                @click="removeCurrentImage"
                class="absolute -top-2 -right-2 p-1.5 bg-red-500 hover:bg-red-600 text-white rounded-full transition-colors shadow-lg"
                aria-label="Remove current image"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- New Image Preview -->
          <div v-if="imagePreview">
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-2">New Image:</p>
            <div class="relative inline-block">
              <img 
                :src="imagePreview" 
                alt="New thumbnail preview"
                class="w-48 h-32 object-cover rounded-lg border border-neutral-300 dark:border-neutral-600"
              />
              <button
                type="button"
                @click="removeNewImage"
                class="absolute -top-2 -right-2 p-1.5 bg-red-500 hover:bg-red-600 text-white rounded-full transition-colors shadow-lg"
                aria-label="Remove new image"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Image Uploader -->
        <div class="flex-1">
          <ImageUploader
            v-model="formData.featured_image"
            :current-image="formData.current_featured_image"
            :error="errors.featured_image"
            @blur="validateField('featured_image')"
            @update:modelValue="handleImageUpload"
          />
          <p v-if="errors.featured_image" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ errors.featured_image }}
          </p>
        </div>
      </div>
    </div>

    <!-- Technologies -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Technologies
      </label>
      <div class="space-y-3">
        <!-- Technology Input -->
        <div class="flex gap-2">
          <BaseInput
            v-model="newTechnology"
            type="text"
            placeholder="e.g., Vue.js, Laravel, MySQL"
            @keydown.enter.prevent="addTechnology"
            class="flex-1"
          />
          <BaseButton
            type="button"
            @click="addTechnology"
            button-type="secondary"
            :disabled="!newTechnology.trim()"
          >
            Add
          </BaseButton>
        </div>

        <!-- Technology Chips -->
        <div v-if="formData.technologies.length > 0" class="flex flex-wrap gap-2">
          <div
            v-for="(tech, index) in formData.technologies"
            :key="index"
            class="inline-flex items-center gap-2 px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-sm"
          >
            <span>{{ tech }}</span>
            <button
              type="button"
              @click="removeTechnology(index)"
              class="hover:text-primary-900 dark:hover:text-primary-100 transition-colors"
              aria-label="Remove technology"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <p v-if="errors.technologies" class="text-sm text-red-600 dark:text-red-400">
          {{ errors.technologies }}
        </p>
      </div>
    </div>

    <!-- Client and URLs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <BaseInput
          v-model="formData.client_name"
          label="Client Name"
          type="text"
          placeholder="Client or company name"
          :error="errors.client_name"
          @blur="validateField('client_name')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.project_url"
          label="Project URL"
          type="url"
          placeholder="https://example.com"
          :error="errors.project_url"
          @blur="validateField('project_url')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.github_url"
          label="GitHub URL"
          type="url"
          placeholder="https://github.com/user/repo"
          :error="errors.github_url"
          @blur="validateField('github_url')"
        />
      </div>
    </div>

    <!-- Status and Dates -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
          Status <span class="text-red-500">*</span>
        </label>
        <select
          v-model="formData.status"
          class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          :class="{ 'border-red-500': errors.status }"
          @blur="validateField('status')"
        >
          <option value="planning">Planning</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
        </select>
        <p v-if="errors.status" class="mt-1 text-sm text-red-600 dark:text-red-400">
          {{ errors.status }}
        </p>
      </div>

      <div>
        <BaseInput
          v-model="formData.start_date"
          label="Start Date"
          type="date"
          :error="errors.start_date"
          @blur="validateField('start_date')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.end_date"
          label="End Date"
          type="date"
          :error="errors.end_date"
          @blur="validateField('end_date')"
        />
      </div>
    </div>

    <!-- Featured Checkbox -->
    <div class="flex items-center gap-3">
      <input
        id="featured"
        v-model="formData.is_featured"
        type="checkbox"
        class="w-4 h-4 text-primary-600 bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-600 rounded focus:ring-primary-500 focus:ring-2"
      />
      <label for="featured" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
        Feature this project (display prominently on homepage)
      </label>
    </div>

    <!-- SEO Settings (Collapsible) -->
    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg overflow-hidden">
      <button
        type="button"
        @click="showSeoSection = !showSeoSection"
        class="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-800 flex items-center justify-between hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
      >
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="font-medium text-neutral-900 dark:text-neutral-100">SEO Settings</span>
          <span class="text-sm text-neutral-500 dark:text-neutral-400">(Optional)</span>
        </div>
        <svg
          class="w-5 h-5 text-neutral-600 dark:text-neutral-400 transition-transform"
          :class="{ 'rotate-180': showSeoSection }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div v-show="showSeoSection" class="p-4 space-y-4 bg-white dark:bg-neutral-900">
        <!-- Meta Title -->
        <div>
          <BaseInput
            v-model="formData.meta_title"
            label="Meta Title"
            type="text"
            placeholder="Leave empty to use project title"
            :error="errors.meta_title"
            @blur="validateField('meta_title')"
          >
            <template #help>
              <span :class="metaTitleClass">
                {{ formData.meta_title.length }}/60 characters (optimal: 50-60)
              </span>
            </template>
          </BaseInput>
        </div>

        <!-- Meta Description -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Meta Description
          </label>
          <textarea
            v-model="formData.meta_description"
            rows="3"
            placeholder="Brief description for search engines (leave empty to auto-generate)"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            :class="{ 'border-red-500': errors.meta_description }"
            @blur="validateField('meta_description')"
          ></textarea>
          <div class="flex justify-between items-center mt-1">
            <p v-if="errors.meta_description" class="text-sm text-red-600 dark:text-red-400">
              {{ errors.meta_description }}
            </p>
            <p class="text-sm" :class="metaDescriptionClass">
              {{ formData.meta_description.length }}/160 characters (optimal: 150-160)
            </p>
          </div>
        </div>

        <!-- Focus Keyword -->
        <div>
          <BaseInput
            v-model="formData.focus_keyword"
            label="Focus Keyword"
            type="text"
            placeholder="Main SEO keyword/phrase"
            :error="errors.focus_keyword"
            @blur="validateField('focus_keyword')"
          />
        </div>

        <!-- Canonical URL -->
        <div>
          <BaseInput
            v-model="formData.canonical_url"
            label="Canonical URL"
            type="text"
            placeholder="/projects/project-name or https://example.com/path"
            :error="errors.canonical_url"
            @blur="validateField('canonical_url')"
          >
            <template #help>
              Leave empty to use default URL. Accepts relative paths or full URLs.
            </template>
          </BaseInput>
        </div>
      </div>
    </div>

    <!-- Related Projects Section (Collapsible) with Autocomplete -->
    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg">
      <button
        type="button"
        @click="showRelatedProjectsSection = !showRelatedProjectsSection"
        class="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-800 flex items-center justify-between hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
      >
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          <span class="font-medium text-neutral-900 dark:text-neutral-100">Related Projects</span>
          <span class="text-sm text-neutral-500 dark:text-neutral-400">(Optional)</span>
        </div>
        <svg
          class="w-5 h-5 text-neutral-600 dark:text-neutral-400 transition-transform"
          :class="{ 'rotate-180': showRelatedProjectsSection }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div v-show="showRelatedProjectsSection" class="p-4 space-y-4 bg-white dark:bg-neutral-900">
        <!-- Autocomplete Search -->
        <div class="relative">
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Search Projects
          </label>
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Type at least 3 characters to search..."
              class="w-full px-4 py-2 pr-10 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              @input="handleSearchInput"
            />
            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Search Results Dropdown -->
          <div
            v-if="showSearchResults && searchResults.length > 0"
            class="absolute z-50 w-full mt-1 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-600 rounded-lg shadow-xl max-h-80 overflow-y-auto"
          >
            <button
              v-for="project in searchResults"
              :key="project.id"
              type="button"
              @click="addRelatedProject(project)"
              class="w-full px-4 py-3 text-left hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors border-b border-neutral-200 dark:border-neutral-700 last:border-b-0"
              :class="{ 'opacity-50 cursor-not-allowed': isProjectSelected(project.id) }"
              :disabled="isProjectSelected(project.id)"
            >
              <div class="font-medium text-neutral-900 dark:text-neutral-100">
                {{ project.title }}
              </div>
              <div class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                {{ project.status }} • {{ project.technologies?.slice(0, 3).join(', ') }}
              </div>
            </button>
          </div>

          <!-- No Results Message -->
          <div
            v-else-if="showSearchResults && searchQuery.length >= 3 && searchResults.length === 0"
            class="absolute z-50 w-full mt-1 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-600 rounded-lg shadow-lg p-4 text-center text-sm text-neutral-500 dark:text-neutral-400"
          >
            No projects found
          </div>
        </div>

        <!-- Selected Related Projects -->
        <div v-if="selectedProjects.length > 0" class="space-y-2">
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
            Selected Projects ({{ selectedProjects.length }}/5)
          </label>
          <!-- Compact horizontal chips layout -->
          <div class="flex flex-wrap gap-2">
            <div
              v-for="project in selectedProjects"
              :key="project.id"
              class="inline-flex items-center gap-2 px-3 py-2 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg text-sm"
            >
              <span class="font-medium text-primary-900 dark:text-primary-100">{{ project.title }}</span>
              <button
                type="button"
                @click="removeRelatedProject(project.id)"
                class="p-0.5 hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400 rounded transition-colors"
                aria-label="Remove project"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">
            Maximum 5 related projects recommended
          </p>
        </div>

        <div v-else class="text-sm text-neutral-500 dark:text-neutral-400 text-center py-4">
          No related projects selected. Use the search above to add projects.
        </div>
      </div>
    </div>

    <!-- Case Study Fields Section (Collapsible) -->
    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg">
      <button
        type="button"
        @click="showCaseStudySection = !showCaseStudySection"
        class="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-800 flex items-center justify-between hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
      >
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="font-medium text-neutral-900 dark:text-neutral-100">Case Study Details</span>
          <span class="text-sm text-neutral-500 dark:text-neutral-400">(Optional)</span>
        </div>
        <svg
          class="w-5 h-5 text-neutral-600 dark:text-neutral-400 transition-transform"
          :class="{ 'rotate-180': showCaseStudySection }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div v-show="showCaseStudySection" class="p-4 space-y-4 bg-white dark:bg-neutral-900">
        <!-- Category -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Category
          </label>
          <select
            v-model="formData.category"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          >
            <option value="">Select category...</option>
            <option value="AI">AI</option>
            <option value="RPA">RPA</option>
            <option value="IoT">IoT</option>
            <option value="Web">Web</option>
            <option value="Mobile">Mobile</option>
          </select>
        </div>

        <!-- Impact Statement -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Impact Statement <span class="text-xs text-neutral-500">(One-line summary, max 255 chars)</span>
          </label>
          <input
            v-model="formData.impact_statement"
            type="text"
            maxlength="255"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            placeholder="e.g., Reduced processing time by 70% and saved $100K annually"
          />
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
            {{ formData.impact_statement?.length || 0 }}/255 characters
          </p>
        </div>

        <!-- Context -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Context / Background
          </label>
          <textarea
            v-model="formData.context"
            rows="3"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            placeholder="Describe the business background and why this project was needed..."
          ></textarea>
        </div>

        <!-- Role -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Your Role
          </label>
          <input
            v-model="formData.role"
            type="text"
            maxlength="100"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            placeholder="e.g., Lead AI Architect & Implementation Lead"
          />
        </div>

        <!-- Problem -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Problem / Challenge
          </label>
          <textarea
            v-model="formData.problem"
            rows="3"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            placeholder="Describe the specific challenges or problems that needed to be solved..."
          ></textarea>
        </div>

        <!-- Solution -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Solution Implemented
          </label>
          <textarea
            v-model="formData.solution"
            rows="4"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            placeholder="Explain the technical solution you implemented..."
          ></textarea>
        </div>

        <!-- Integration -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Integration & Tech Stack
          </label>
          <textarea
            v-model="formData.integration"
            rows="3"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            placeholder="List systems integrated and technologies used..."
          ></textarea>
        </div>

        <!-- Result -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Results & Outcomes
          </label>
          <textarea
            v-model="formData.result"
            rows="3"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            placeholder="Quantify the measurable results achieved (e.g., 80% faster, $100K saved, 95% accuracy)"
          ></textarea>
        </div>
      </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4 pt-6 border-t border-neutral-200 dark:border-neutral-700">
      <BaseButton
        type="button"
        button-type="secondary"
        @click="handleCancel"
        :disabled="isSubmitting"
      >
        Cancel
      </BaseButton>

      <BaseButton
        type="submit"
        button-type="primary"
        :disabled="isSubmitting"
        :loading="isSubmitting"
      >
        {{ submitLabel }}
      </BaseButton>
    </div>
  </form>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import RichTextEditor from '@/components/blog/RichTextEditor.vue'
import ImageUploader from '@/components/blog/ImageUploader.vue'
import api from '@/services/api'

const props = defineProps({
  project: {
    type: Object,
    default: null
  },
  submitLabel: {
    type: String,
    default: 'Create Project'
  },
  isSubmitting: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submit', 'cancel'])

// Form data
const formData = ref({
  title: '',
  slug: '',
  description: '',
  content: '',
  featured_image: null,
  current_featured_image: null,
  technologies: [],
  client_name: '',
  project_url: '',
  github_url: '',
  status: 'planning',
  start_date: '',
  end_date: '',
  is_featured: false,
  category: '',
  meta_title: '',
  meta_description: '',
  focus_keyword: '',
  canonical_url: '',
  related_project_ids: [],
  // Case Study fields (added Nov 3, 2025)
  impact_statement: '',
  context: '',
  role: '',
  problem: '',
  solution: '',
  integration: '',
  result: ''
})

// Form errors
const errors = ref({})

// UI state
const showSeoSection = ref(false)
const showRelatedProjectsSection = ref(false)
const showCaseStudySection = ref(false)
const newTechnology = ref('')
const imagePreview = ref(null)

// Related Projects Autocomplete
const searchQuery = ref('')
const searchResults = ref([])
const showSearchResults = ref(false)
const selectedProjects = ref([])
const allProjects = ref([])

// Initialize form with project data if editing
if (props.project) {
  formData.value = {
    title: props.project.title || '',
    slug: props.project.slug || '',
    description: props.project.description || '',
    content: props.project.content || '',
    featured_image: null,
    current_featured_image: props.project.featured_image || null,
    technologies: props.project.technologies || [],
    client_name: props.project.client_name || '',
    project_url: props.project.project_url || '',
    github_url: props.project.github_url || '',
    status: props.project.status || 'planning',
    start_date: props.project.start_date || '',
    end_date: props.project.end_date || '',
    is_featured: props.project.is_featured || false,
    category: props.project.category || '',
    meta_title: props.project.meta_title || '',
    meta_description: props.project.meta_description || '',
    focus_keyword: props.project.focus_keyword || '',
    canonical_url: props.project.canonical_url || '',
    related_project_ids: Array.isArray(props.project.related_project_ids)
      ? props.project.related_project_ids
      : [],
    // Case Study fields (added Nov 3, 2025)
    impact_statement: props.project.impact_statement || '',
    context: props.project.context || '',
    role: props.project.role || '',
    problem: props.project.problem || '',
    solution: props.project.solution || '',
    integration: props.project.integration || '',
    result: props.project.result || ''
  }
  
  // Load selected projects for related projects section
  if (props.project.related_projects && Array.isArray(props.project.related_projects)) {
    selectedProjects.value = props.project.related_projects
  }
}

// Auto-generate slug from title
watch(() => formData.value.title, (newTitle) => {
  if (!props.project || formData.value.slug === generateSlug(props.project.title)) {
    formData.value.slug = generateSlug(newTitle)
  }
})

// Generate slug helper
function generateSlug(text) {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

// Image upload handler
function handleImageUpload(file) {
  if (file) {
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  } else {
    imagePreview.value = null
  }
}

// Remove current image
function removeCurrentImage() {
  formData.value.current_featured_image = null
  formData.value.featured_image = null
  imagePreview.value = null
}

// Remove new image
function removeNewImage() {
  formData.value.featured_image = null
  imagePreview.value = null
}

// Meta title character count color
const metaTitleClass = computed(() => {
  const length = formData.value.meta_title.length
  if (length === 0) return 'text-neutral-500 dark:text-neutral-400'
  if (length < 50) return 'text-yellow-600 dark:text-yellow-400'
  if (length <= 60) return 'text-green-600 dark:text-green-400'
  return 'text-red-600 dark:text-red-400'
})

// Meta description character count color
const metaDescriptionClass = computed(() => {
  const length = formData.value.meta_description.length
  if (length === 0) return 'text-neutral-500 dark:text-neutral-400'
  if (length < 150) return 'text-yellow-600 dark:text-yellow-400'
  if (length <= 160) return 'text-green-600 dark:text-green-400'
  return 'text-red-600 dark:text-red-400'
})

// Add technology
function addTechnology() {
  const tech = newTechnology.value.trim()
  if (tech && !formData.value.technologies.includes(tech)) {
    formData.value.technologies.push(tech)
    newTechnology.value = ''
    errors.value.technologies = ''
  }
}

// Remove technology
function removeTechnology(index) {
  formData.value.technologies.splice(index, 1)
}

// Handle search input with debounce
let searchTimeout = null
function handleSearchInput() {
  clearTimeout(searchTimeout)
  
  if (searchQuery.value.length < 3) {
    showSearchResults.value = false
    searchResults.value = []
    return
  }

  searchTimeout = setTimeout(() => {
    performSearch()
  }, 300)
}

// Perform search
async function performSearch() {
  if (searchQuery.value.length < 3) {
    return
  }

  try {
    const response = await api.get('/projects', {
      params: {
        q: searchQuery.value,
        limit: 10
      }
    })

    if (response.data.success) {
      // Filter out current project and already selected projects
      searchResults.value = (response.data.data || []).filter(p => {
        const isCurrentProject = props.project && p.id === props.project.id
        const isAlreadySelected = formData.value.related_project_ids.includes(p.id)
        return !isCurrentProject && !isAlreadySelected
      })
      showSearchResults.value = true
    }
  } catch (error) {
    console.error('Search failed:', error)
    searchResults.value = []
    showSearchResults.value = false
  }
}

// Check if project is selected
function isProjectSelected(projectId) {
  return formData.value.related_project_ids.includes(projectId)
}

// Add related project
function addRelatedProject(project) {
  if (formData.value.related_project_ids.length >= 5) {
    alert('Maximum 5 related projects allowed')
    return
  }

  if (!isProjectSelected(project.id)) {
    formData.value.related_project_ids.push(project.id)
    selectedProjects.value.push(project)
    
    // Clear search
    searchQuery.value = ''
    showSearchResults.value = false
    searchResults.value = []
  }
}

// Remove related project
function removeRelatedProject(projectId) {
  const index = formData.value.related_project_ids.indexOf(projectId)
  if (index > -1) {
    formData.value.related_project_ids.splice(index, 1)
    selectedProjects.value = selectedProjects.value.filter(p => p.id !== projectId)
  }
}

// Load all projects for initial selection
async function loadProjects() {
  try {
    const response = await api.get('/projects', {
      params: { limit: 100 }
    })
    
    if (response.data.success) {
      allProjects.value = response.data.data || []
      
      // Load selected projects if editing
      if (props.project && formData.value.related_project_ids.length > 0) {
        selectedProjects.value = allProjects.value.filter(p => 
          formData.value.related_project_ids.includes(p.id)
        )
      }
    }
  } catch (error) {
    console.error('Failed to load projects:', error)
  }
}

// Validate single field
function validateField(field) {
  errors.value[field] = ''

  switch (field) {
    case 'title':
      if (!formData.value.title.trim()) {
        errors.value.title = 'Project title is required'
      } else if (formData.value.title.length > 200) {
        errors.value.title = 'Title must be less than 200 characters'
      }
      break

    case 'slug':
      if (!formData.value.slug.trim()) {
        errors.value.slug = 'URL slug is required'
      } else if (!/^[a-z0-9-]+$/.test(formData.value.slug)) {
        errors.value.slug = 'Slug can only contain lowercase letters, numbers, and hyphens'
      }
      break

    case 'description':
      if (!formData.value.description.trim()) {
        errors.value.description = 'Project description is required'
      }
      break

    case 'content':
      // Content is optional - no validation needed
      break

    case 'status':
      if (!formData.value.status) {
        errors.value.status = 'Project status is required'
      }
      break

    case 'project_url': {
      const trimmedUrl = (formData.value.project_url || '').trim()
      if (trimmedUrl && !isValidUrl(trimmedUrl)) {
        errors.value.project_url = 'Please enter a valid URL'
      }
      break
    }

    case 'github_url': {
      const trimmedUrl = (formData.value.github_url || '').trim()
      if (trimmedUrl && !isValidUrl(trimmedUrl)) {
        errors.value.github_url = 'Please enter a valid GitHub URL'
      }
      break
    }

    case 'canonical_url':
      // Canonical URL accepts both full URLs and relative paths - no validation
      break

    case 'meta_title':
      if (formData.value.meta_title.length > 60) {
        errors.value.meta_title = 'Meta title should be 60 characters or less'
      }
      break

    case 'meta_description':
      if (formData.value.meta_description.length > 160) {
        errors.value.meta_description = 'Meta description should be 160 characters or less'
      }
      break
  }
}

// Validate URL
function isValidUrl(url) {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}

// Validate entire form
function validateForm() {
  errors.value = {}

  validateField('title')
  validateField('slug')
  validateField('description')
  // content is optional
  validateField('status')
  
  // Only validate optional URL fields if they have values
  if (formData.value.project_url && formData.value.project_url.trim()) {
    validateField('project_url')
  }
  if (formData.value.github_url && formData.value.github_url.trim()) {
    validateField('github_url')
  }
  // canonical_url is completely optional - skip validation in form submit
  
  // Only validate SEO fields if they have values
  if (formData.value.meta_title && formData.value.meta_title.trim()) {
    validateField('meta_title')
  }
  if (formData.value.meta_description && formData.value.meta_description.trim()) {
    validateField('meta_description')
  }

  return Object.keys(errors.value).every(key => !errors.value[key])
}

// Handle submit
function handleSubmit(event) {
  // Prevent default form submission
  event?.preventDefault()
  
  // Clear any existing errors from optional fields
  delete errors.value.canonical_url
  
  if (!validateForm()) {
    // Scroll to first error
    const firstError = Object.keys(errors.value).find(key => errors.value[key])
    if (firstError) {
      console.error('Validation failed:', firstError, errors.value[firstError])
    }
    return
  }

  // Prepare submission data
  const submissionData = new FormData()

  // Add all text fields
  submissionData.append('title', formData.value.title.trim())
  submissionData.append('slug', formData.value.slug.trim())
  submissionData.append('description', formData.value.description.trim())
  submissionData.append('content', formData.value.content.trim())
  
  // Add technologies as array items
  formData.value.technologies.forEach((tech, index) => {
    submissionData.append(`technologies[${index}]`, tech)
  })
  
  submissionData.append('status', formData.value.status)
  submissionData.append('is_featured', formData.value.is_featured ? '1' : '0')

  // Add optional fields (only if not empty)
  const clientName = (formData.value.client_name || '').trim()
  const projectUrl = (formData.value.project_url || '').trim()
  const githubUrl = (formData.value.github_url || '').trim()
  
  if (clientName) {
    submissionData.append('client_name', clientName)
  }
  if (projectUrl) {
    submissionData.append('project_url', projectUrl)
  }
  if (githubUrl) {
    submissionData.append('github_url', githubUrl)
  }
  if (formData.value.start_date) {
    submissionData.append('start_date', formData.value.start_date)
  }
  if (formData.value.end_date) {
    submissionData.append('end_date', formData.value.end_date)
  }

  // Add SEO fields (only if not empty)
  const metaTitle = (formData.value.meta_title || '').trim()
  const metaDescription = (formData.value.meta_description || '').trim()
  const focusKeyword = (formData.value.focus_keyword || '').trim()
  const canonicalUrl = (formData.value.canonical_url || '').trim()
  
  if (metaTitle) {
    submissionData.append('meta_title', metaTitle)
  }
  if (metaDescription) {
    submissionData.append('meta_description', metaDescription)
  }
  if (focusKeyword) {
    submissionData.append('focus_keyword', focusKeyword)
  }
  if (canonicalUrl) {
    submissionData.append('canonical_url', canonicalUrl)
  }

  // Add related project IDs
  if (formData.value.related_project_ids.length > 0) {
    formData.value.related_project_ids.forEach((id, index) => {
      submissionData.append(`related_project_ids[${index}]`, id)
    })
  }

  // Add featured image if changed
  if (formData.value.featured_image) {
    submissionData.append('featured_image', formData.value.featured_image)
  }

  // Add _method for Laravel PUT spoofing (only if editing)
  if (props.project) {
    submissionData.append('_method', 'PUT')
  }

  emit('submit', submissionData)
}

// Handle cancel
function handleCancel() {
  emit('cancel')
}

// Fetch projects on mount
onMounted(() => {
  loadProjects()
})
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
