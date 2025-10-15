<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, File, null],
    default: null
  },
  accept: {
    type: String,
    default: 'image/jpeg,image/png,image/gif,image/webp'
  },
  maxSize: {
    type: Number,
    default: 5 * 1024 * 1024 // 5MB
  },
  aspectRatio: {
    type: String,
    default: null // e.g., '16:9', '4:3', '1:1'
  },
  label: {
    type: String,
    default: 'Upload Image'
  },
  description: {
    type: String,
    default: 'PNG, JPG, GIF or WEBP (max 5MB)'
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'error', 'upload'])

const fileInput = ref(null)
const isDragging = ref(false)
const previewUrl = ref(null)
const fileName = ref('')
const fileSize = ref(0)
const error = ref(null)

// Format file size
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

// Formatted file size
const formattedSize = computed(() => formatFileSize(fileSize.value))

// Has image
const hasImage = computed(() => !!previewUrl.value)

// Validate file
const validateFile = (file) => {
  error.value = null

  // Check file type
  const acceptedTypes = props.accept.split(',').map(type => type.trim())
  const fileType = file.type

  if (!acceptedTypes.includes(fileType)) {
    error.value = `Invalid file type. Accepted types: ${props.accept}`
    emit('error', { type: 'invalid_type', message: error.value })
    return false
  }

  // Check file size
  if (file.size > props.maxSize) {
    error.value = `File size exceeds ${formatFileSize(props.maxSize)}`
    emit('error', { type: 'size_exceeded', message: error.value })
    return false
  }

  return true
}

// Handle file selection
const handleFileSelect = (file) => {
  if (!validateFile(file)) {
    return
  }

  fileName.value = file.name
  fileSize.value = file.size

  // Create preview URL
  const reader = new FileReader()
  reader.onload = (e) => {
    previewUrl.value = e.target.result
  }
  reader.readAsDataURL(file)

  // Emit the file
  emit('update:modelValue', file)
  emit('upload', file)
}

// Handle input change
const handleInputChange = (event) => {
  const file = event.target.files?.[0]
  if (file) {
    handleFileSelect(file)
  }
}

// Handle drag events
const handleDragEnter = (e) => {
  e.preventDefault()
  e.stopPropagation()
  if (!props.disabled) {
    isDragging.value = true
  }
}

const handleDragLeave = (e) => {
  e.preventDefault()
  e.stopPropagation()
  isDragging.value = false
}

const handleDragOver = (e) => {
  e.preventDefault()
  e.stopPropagation()
}

const handleDrop = (e) => {
  e.preventDefault()
  e.stopPropagation()
  isDragging.value = false

  if (props.disabled) return

  const file = e.dataTransfer?.files?.[0]
  if (file) {
    handleFileSelect(file)
  }
}

// Trigger file input click
const triggerFileInput = () => {
  if (!props.disabled) {
    fileInput.value?.click()
  }
}

// Remove image
const removeImage = () => {
  previewUrl.value = null
  fileName.value = ''
  fileSize.value = 0
  error.value = null

  if (fileInput.value) {
    fileInput.value.value = ''
  }

  emit('update:modelValue', null)
}

// Watch for external changes (e.g., when editing existing post)
watch(() => props.modelValue, (newVal) => {
  if (typeof newVal === 'string' && newVal) {
    // It's a URL (existing image)
    previewUrl.value = newVal
    fileName.value = newVal.split('/').pop()
  } else if (!newVal) {
    // Cleared externally
    removeImage()
  }
}, { immediate: true })

// Expose methods
defineExpose({
  removeImage,
  triggerFileInput
})
</script>

<template>
  <div class="image-uploader">
    <!-- Label -->
    <label
      v-if="label"
      class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
    >
      {{ label }}
    </label>

    <!-- Upload Area -->
    <div
      v-if="!hasImage"
      class="border-2 border-dashed rounded-lg transition-all duration-200"
      :class="{
        'border-blue-500 bg-blue-50 dark:bg-blue-900/20': isDragging,
        'border-gray-300 dark:border-gray-600': !isDragging && !error,
        'border-red-500 bg-red-50 dark:bg-red-900/20': error,
        'opacity-50 cursor-not-allowed': disabled,
        'cursor-pointer hover:border-blue-400 dark:hover:border-blue-500': !disabled && !error
      }"
      @click="triggerFileInput"
      @dragenter="handleDragEnter"
      @dragleave="handleDragLeave"
      @dragover="handleDragOver"
      @drop="handleDrop"
    >
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        :accept="accept"
        :disabled="disabled"
        @change="handleInputChange"
      />

      <div class="text-center p-6">
        <!-- Upload Icon -->
        <svg
          class="mx-auto h-12 w-12 mb-4"
          :class="error ? 'text-red-400' : 'text-gray-400'"
          stroke="currentColor"
          fill="none"
          viewBox="0 0 48 48"
          aria-hidden="true"
        >
          <path
            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>

        <!-- Instructions -->
        <div class="text-sm">
          <p class="font-semibold text-gray-900 dark:text-white mb-1">
            <span v-if="!disabled">
              <span class="text-blue-600 dark:text-blue-400">Click to upload</span>
              or drag and drop
            </span>
            <span v-else class="text-gray-500">Upload disabled</span>
          </p>
          <p class="text-gray-500 dark:text-gray-400 text-xs">
            {{ description }}
          </p>
          <p
            v-if="aspectRatio"
            class="text-gray-500 dark:text-gray-400 text-xs mt-1"
          >
            Recommended aspect ratio: {{ aspectRatio }}
          </p>
        </div>
      </div>
    </div>

    <!-- Preview Area -->
    <div
      v-else
      class="relative rounded-lg border-2 border-gray-300 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-800 transition-all duration-200"
    >
      <!-- Image Preview -->
      <div class="relative">
        <img
          :src="previewUrl"
          :alt="fileName"
          class="w-full h-auto object-cover"
          :class="aspectRatio ? 'aspect-[16/9]' : 'max-h-96'"
        />

        <!-- Remove Button -->
        <button
          v-if="!disabled"
          type="button"
          class="absolute top-2 right-2 p-2 bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
          @click.stop="removeImage"
        >
          <svg
            class="h-5 w-5"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>

        <!-- Disabled Overlay -->
        <div
          v-if="disabled"
          class="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center"
        >
          <span class="text-white text-sm font-medium">
            Preview Only
          </span>
        </div>
      </div>

      <!-- File Info -->
      <div class="p-3 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
          {{ fileName }}
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          {{ formattedSize }}
        </p>
      </div>
    </div>

    <!-- Error Message -->
    <p
      v-if="error"
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
      <span>{{ error }}</span>
    </p>
  </div>
</template>
