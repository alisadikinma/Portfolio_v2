<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  Listbox,
  ListboxButton,
  ListboxOptions,
  ListboxOption,
  ListboxLabel
} from '@headlessui/vue'
import { useCategories } from '@/composables/useCategories'

const props = defineProps({
  modelValue: {
    type: [Number, null],
    default: null
  },
  label: {
    type: String,
    default: 'Category'
  },
  placeholder: {
    type: String,
    default: 'Select a category'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  required: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// Use categories composable
const { categories, loading, error: categoriesError, fetchCategories } = useCategories()

// Selected category ID
const selectedId = ref(props.modelValue)

// Find selected category object
const selectedCategory = computed(() => {
  if (!selectedId.value) return null
  return categories.value.find(cat => cat.id === selectedId.value) || null
})

// Handle selection change
const handleChange = (categoryId) => {
  selectedId.value = categoryId
  emit('update:modelValue', categoryId)

  const category = categories.value.find(cat => cat.id === categoryId)
  emit('change', category)
}

// Watch for external changes
watch(() => props.modelValue, (newVal) => {
  selectedId.value = newVal
}, { immediate: true })

// Fetch categories on mount
onMounted(() => {
  if (categories.value.length === 0) {
    fetchCategories()
  }
})

// Display value in button
const displayValue = computed(() => {
  if (selectedCategory.value) {
    return selectedCategory.value.name
  }
  return props.placeholder
})

// Has error
const hasError = computed(() => !!(props.error || categoriesError.value))

// Error message
const errorMessage = computed(() => {
  return props.error || categoriesError.value
})
</script>

<template>
  <div class="category-select">
    <Listbox
      :model-value="selectedId"
      :disabled="disabled || loading"
      @update:model-value="handleChange"
    >
      <ListboxLabel
        v-if="label"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
      >
        {{ label }}
        <span v-if="required" class="text-red-500">*</span>
      </ListboxLabel>

      <div class="relative">
        <!-- Button -->
        <ListboxButton
          class="relative w-full cursor-pointer rounded-lg py-2.5 pl-3 pr-10 text-left border transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
          :class="{
            'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500': !hasError,
            'bg-red-50 dark:bg-red-900/20 border-red-500 focus:ring-red-500 focus:border-red-500': hasError,
            'opacity-50 cursor-not-allowed': disabled || loading
          }"
        >
          <!-- Loading State -->
          <span
            v-if="loading"
            class="flex items-center text-gray-500 dark:text-gray-400"
          >
            <svg
              class="animate-spin h-4 w-4 mr-2"
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
            Loading categories...
          </span>

          <!-- Selected Value or Placeholder -->
          <span
            v-else
            class="block truncate"
            :class="{
              'text-gray-900 dark:text-white': selectedCategory,
              'text-gray-500 dark:text-gray-400': !selectedCategory
            }"
          >
            {{ displayValue }}
          </span>

          <!-- Dropdown Icon -->
          <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
            <svg
              class="h-5 w-5 text-gray-400"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
              aria-hidden="true"
            >
              <path
                fill-rule="evenodd"
                d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"
                clip-rule="evenodd"
              />
            </svg>
          </span>
        </ListboxButton>

        <!-- Dropdown Options -->
        <transition
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <ListboxOptions
            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white dark:bg-gray-800 py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
          >
            <!-- Empty State -->
            <div
              v-if="categories.length === 0 && !loading"
              class="px-4 py-6 text-center text-gray-500 dark:text-gray-400"
            >
              <svg
                class="mx-auto h-12 w-12 mb-2 text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                />
              </svg>
              <p class="text-sm">No categories available</p>
              <p class="text-xs mt-1">Create a category first</p>
            </div>

            <!-- Category Options -->
            <ListboxOption
              v-for="category in categories"
              :key="category.id"
              v-slot="{ active, selected }"
              :value="category.id"
              as="template"
            >
              <li
                class="relative cursor-pointer select-none py-2.5 pl-10 pr-4 transition-colors"
                :class="{
                  'bg-blue-600 text-white': active,
                  'text-gray-900 dark:text-white': !active
                }"
              >
                <!-- Category Name -->
                <span
                  class="block truncate"
                  :class="{ 'font-semibold': selected, 'font-normal': !selected }"
                >
                  {{ category.name }}
                </span>

                <!-- Category Description (if exists) -->
                <span
                  v-if="category.description"
                  class="block text-xs truncate mt-0.5 opacity-75"
                >
                  {{ category.description }}
                </span>

                <!-- Checkmark Icon -->
                <span
                  v-if="selected"
                  class="absolute inset-y-0 left-0 flex items-center pl-3"
                  :class="{ 'text-white': active, 'text-blue-600': !active }"
                >
                  <svg
                    class="h-5 w-5"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </span>
              </li>
            </ListboxOption>
          </ListboxOptions>
        </transition>
      </div>
    </Listbox>

    <!-- Error Message -->
    <p
      v-if="errorMessage"
      class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-start"
    >
      <svg
        class="h-5 w-5 mr-1 flex-shrink-0"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
          clip-rule="evenodd"
        />
      </svg>
      <span>{{ errorMessage }}</span>
    </p>

    <!-- Post Count Badge (if category selected) -->
    <div
      v-if="selectedCategory && selectedCategory.posts_count !== undefined"
      class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200"
    >
      {{ selectedCategory.posts_count }}
      {{ selectedCategory.posts_count === 1 ? 'post' : 'posts' }}
    </div>
  </div>
</template>

<style scoped>
/* Headless UI transitions are handled inline */
</style>
