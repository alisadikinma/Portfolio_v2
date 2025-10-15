<template>
  <div class="max-w-5xl mx-auto">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="py-12">
      <BaseCard>
        <div class="text-center">
          <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100 mb-2">
            Failed to Load Project
          </h2>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            {{ error }}
          </p>
          <BaseButton @click="router.push('/admin/projects')">
            Back to Projects
          </BaseButton>
        </div>
      </BaseCard>
    </div>

    <!-- Edit Form -->
    <div v-else-if="project">
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
            Edit Project
          </h1>
        </div>
        <p class="text-neutral-600 dark:text-neutral-400 ml-14">
          Update project details for "{{ project.title }}"
        </p>
      </div>

      <!-- Form -->
      <BaseCard>
        <ProjectForm
          :project="project"
          submit-label="Update Project"
          :is-submitting="isSubmitting"
          @submit="handleSubmit"
          @cancel="handleCancel"
        />
      </BaseCard>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProjectsStore } from '@/stores/projects'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import ProjectForm from '@/components/projects/ProjectForm.vue'

const route = useRoute()
const router = useRouter()
const projectsStore = useProjectsStore()
const uiStore = useUiStore()

const project = ref(null)
const loading = ref(true)
const error = ref(null)
const isSubmitting = ref(false)

onMounted(async () => {
  await fetchProject()
})

async function fetchProject() {
  loading.value = true
  error.value = null

  try {
    const projectId = route.params.id
    project.value = await projectsStore.fetchProject(projectId)
  } catch (err) {
    console.error('Failed to fetch project:', err)
    error.value = err.response?.data?.message || 'Failed to load project. Please try again.'
  } finally {
    loading.value = false
  }
}

async function handleSubmit(projectData) {
  isSubmitting.value = true

  try {
    const projectId = route.params.id
    const updatedProject = await projectsStore.updateProject(projectId, projectData)

    uiStore.showSuccess(
      `"${updatedProject.title}" has been updated successfully.`,
      'Project Updated'
    )

    router.push('/admin/projects')
  } catch (err) {
    console.error('Failed to update project:', err)

    uiStore.showError(
      err.response?.data?.message || 'Failed to update project. Please try again.',
      'Update Failed',
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
