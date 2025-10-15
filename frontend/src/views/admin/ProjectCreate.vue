<template>
  <div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center gap-3 mb-2">
        <router-link
          to="/admin/projects"
          class="p-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
          aria-label="Back to projects"
        >
          <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Create New Project
        </h1>
      </div>
      <p class="text-neutral-600 dark:text-neutral-400 ml-14">
        Add a new project to your portfolio
      </p>
    </div>

    <!-- Form -->
    <BaseCard>
      <ProjectForm
        submit-label="Create Project"
        :is-submitting="isSubmitting"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </BaseCard>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectsStore } from '@/stores/projects'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import ProjectForm from '@/components/projects/ProjectForm.vue'

const router = useRouter()
const projectsStore = useProjectsStore()
const uiStore = useUiStore()

const isSubmitting = ref(false)

async function handleSubmit(projectData) {
  isSubmitting.value = true

  try {
    const createdProject = await projectsStore.createProject(projectData)

    uiStore.showSuccess(
      `"${createdProject.title}" has been created successfully.`,
      'Project Created'
    )

    router.push('/admin/projects')
  } catch (error) {
    console.error('Failed to create project:', error)

    uiStore.showError(
      error.response?.data?.message || 'Failed to create project. Please try again.',
      'Creation Failed',
      0 // Persistent
    )
  } finally {
    isSubmitting.value = false
  }
}

function handleCancel() {
  router.push('/admin/projects')
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
