<script setup>
import { ref, computed, onMounted } from 'vue'
import { useMenuItems } from '@/composables/useMenuItems'
import { useToast } from '@/composables/useToast'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'
import DragDropList from '@/components/admin/DragDropList.vue'
import IconPicker from '@/components/admin/IconPicker.vue'
import IconDisplay from '@/components/admin/IconDisplay.vue'

const { menuItems, isLoading, error, fetchMenuItems, updateMenuItem, deleteMenuItem, reorderMenuItems, createMenuItem } = useMenuItems()
const toast = useToast()

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingItem = ref(null)
const itemToDelete = ref(null)
const isDeleting = ref(false)
const isSaving = ref(false)

const formData = ref({
  title: '',
  slug: '',
  url: '',
  icon: '',
  is_active: true,
  sequence: 0
})

onMounted(async () => {
  await loadMenuItems()
})

async function loadMenuItems() {
  const result = await fetchMenuItems()
  if (!result.success) {
    toast.error(error.value || 'Failed to load menu items')
  }
}

function openCreateModal() {
  formData.value = {
    title: '',
    slug: '',
    url: '',
    icon: '',
    is_active: true,
    sequence: menuItems.value.length
  }
  showCreateModal.value = true
}

function openEditModal(item) {
  editingItem.value = item
  formData.value = { ...item }
  showEditModal.value = true
}

function closeModals() {
  showCreateModal.value = false
  showEditModal.value = false
  editingItem.value = null
  formData.value = {
    title: '',
    slug: '',
    url: '',
    icon: '',
    is_active: true,
    sequence: 0
  }
}

async function handleCreate() {
  isSaving.value = true

  const result = await createMenuItem(formData.value)

  if (result.success) {
    toast.success('Menu item created successfully')
    closeModals()
    await loadMenuItems()
  } else {
    toast.error(result.error || 'Failed to create menu item')
  }

  isSaving.value = false
}

async function handleUpdate() {
  if (!editingItem.value) return

  isSaving.value = true

  const result = await updateMenuItem(editingItem.value.id, formData.value)

  if (result.success) {
    toast.success('Menu item updated successfully')
    closeModals()
    await loadMenuItems()
  } else {
    toast.error(result.error || 'Failed to update menu item')
  }

  isSaving.value = false
}

async function toggleActive(item) {
  const result = await updateMenuItem(item.id, {
    ...item,
    is_active: !item.is_active
  })

  if (result.success) {
    toast.success(`Menu item ${result.data.is_active ? 'activated' : 'deactivated'}`)
    await loadMenuItems()
  } else {
    toast.error('Failed to update menu item status')
  }
}

function confirmDelete(item) {
  itemToDelete.value = item
}

async function handleDelete() {
  if (!itemToDelete.value) return

  isDeleting.value = true

  const result = await deleteMenuItem(itemToDelete.value.id)

  if (result.success) {
    toast.success('Menu item deleted successfully')
    itemToDelete.value = null
    await loadMenuItems()
  } else {
    toast.error(result.error || 'Failed to delete menu item')
  }

  isDeleting.value = false
}

async function handleReorder(reorderedItems) {
  const result = await reorderMenuItems(reorderedItems)

  if (result.success) {
    toast.success('Menu items reordered successfully')
  } else {
    toast.error('Failed to reorder menu items')
    await loadMenuItems()
  }
}

// Auto-generate slug from title
function generateSlug() {
  if (formData.value.title) {
    formData.value.slug = formData.value.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Menu Items
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          Manage navigation menu items (Drag to reorder)
        </p>
      </div>
      <BaseButton @click="openCreateModal">
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Menu Item
      </BaseButton>
    </div>

    <!-- Menu Items List -->
    <BaseCard>
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-12">
        <p class="text-neutral-600 dark:text-neutral-400 mb-4">{{ error }}</p>
        <BaseButton @click="loadMenuItems">Retry</BaseButton>
      </div>

      <!-- Menu Items Table with Drag & Drop -->
      <div v-else-if="menuItems.length > 0" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
          <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider w-12">

              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Title
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                URL
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Icon
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Status
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <DragDropList
            :items="menuItems"
            item-key="id"
            @reorder="handleReorder"
            tag="tbody"
            class="bg-white dark:bg-neutral-900 divide-y divide-neutral-200 dark:divide-neutral-700"
          >
            <tr
              v-for="item in menuItems"
              :key="item.id"
              class="hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
            >
              <!-- Drag Handle -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="drag-handle cursor-move text-neutral-400 hover:text-neutral-600">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                  </svg>
                </div>
              </td>

              <!-- Title -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                  {{ item.title }}
                </div>
                <div class="text-sm text-neutral-500 dark:text-neutral-400">
                  {{ item.slug }}
                </div>
              </td>

              <!-- URL -->
              <td class="px-6 py-4">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ item.url }}
                </div>
              </td>

              <!-- Icon -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div v-if="item.icon" class="flex items-center gap-2">
                  <IconDisplay :name="item.icon" class="w-5 h-5 text-neutral-600 dark:text-neutral-400" />
                  <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ item.icon }}</span>
                </div>
                <span v-else class="text-sm text-neutral-400 dark:text-neutral-600">-</span>
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <button
                  @click="toggleActive(item)"
                  :class="[
                    'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer',
                    item.is_active
                      ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-400'
                  ]"
                >
                  {{ item.is_active ? 'Active' : 'Inactive' }}
                </button>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="openEditModal(item)"
                    class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                    title="Edit"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    @click="confirmDelete(item)"
                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    title="Delete"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </DragDropList>
        </table>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          No Menu Items
        </h3>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">
          Get started by adding your first menu item
        </p>
        <BaseButton @click="openCreateModal">
          Add Menu Item
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal || showEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="closeModals"
      >
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">
            {{ showEditModal ? 'Edit Menu Item' : 'Create Menu Item' }}
          </h3>

          <form @submit.prevent="showEditModal ? handleUpdate() : handleCreate()" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Title
              </label>
              <BaseInput
                v-model="formData.title"
                type="text"
                required
                @input="generateSlug"
                placeholder="Home"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Slug
              </label>
              <BaseInput
                v-model="formData.slug"
                type="text"
                required
                placeholder="home"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                URL
              </label>
              <BaseInput
                v-model="formData.url"
                type="text"
                required
                placeholder="/"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Icon (optional)
              </label>
              <IconPicker v-model="formData.icon" />
            </div>

            <div class="flex items-center">
              <input
                v-model="formData.is_active"
                type="checkbox"
                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-neutral-300 rounded"
              />
              <label class="ml-2 block text-sm text-neutral-900 dark:text-neutral-100">
                Active
              </label>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6">
              <BaseButton
                button-type="secondary"
                @click="closeModals"
                :disabled="isSaving"
                type="button"
              >
                Cancel
              </BaseButton>
              <BaseButton
                :loading="isSaving"
                :disabled="isSaving"
                type="submit"
              >
                {{ showEditModal ? 'Update' : 'Create' }}
              </BaseButton>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="itemToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="itemToDelete = null"
      >
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
            Delete Menu Item
          </h3>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            Are you sure you want to delete "<strong>{{ itemToDelete.title }}</strong>"? This action cannot be undone.
          </p>
          <div class="flex items-center justify-end gap-3">
            <BaseButton
              button-type="secondary"
              @click="itemToDelete = null"
              :disabled="isDeleting"
            >
              Cancel
            </BaseButton>
            <BaseButton
              button-type="danger"
              @click="handleDelete"
              :loading="isDeleting"
              :disabled="isDeleting"
            >
              Delete
            </BaseButton>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
