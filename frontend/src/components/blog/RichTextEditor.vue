<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Start writing your post...'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  minHeight: {
    type: String,
    default: '400px'
  }
})

const emit = defineEmits(['update:modelValue', 'ready', 'error'])

const editorElement = ref(null)
const editorInstance = ref(null)
const isReady = ref(false)
const error = ref(null)

// CKEditor configuration
const editorConfig = {
  toolbar: {
    items: [
      'heading',
      '|',
      'bold',
      'italic',
      'link',
      'bulletedList',
      'numberedList',
      '|',
      'outdent',
      'indent',
      '|',
      'blockQuote',
      'insertTable',
      'mediaEmbed',
      'undo',
      'redo',
      '|',
      'code',
      'codeBlock',
      'horizontalLine',
      'highlight'
    ],
    shouldNotGroupWhenFull: true
  },
  placeholder: props.placeholder,
  heading: {
    options: [
      { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
      { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
      { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
      { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
    ]
  },
  link: {
    decorators: {
      openInNewTab: {
        mode: 'manual',
        label: 'Open in a new tab',
        attributes: {
          target: '_blank',
          rel: 'noopener noreferrer'
        }
      }
    }
  },
  table: {
    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
  },
  codeBlock: {
    languages: [
      { language: 'plaintext', label: 'Plain text' },
      { language: 'javascript', label: 'JavaScript' },
      { language: 'typescript', label: 'TypeScript' },
      { language: 'php', label: 'PHP' },
      { language: 'python', label: 'Python' },
      { language: 'css', label: 'CSS' },
      { language: 'html', label: 'HTML' },
      { language: 'sql', label: 'SQL' },
      { language: 'bash', label: 'Bash' }
    ]
  }
}

// Initialize CKEditor
const initializeEditor = async () => {
  try {
    if (!window.ClassicEditor) {
      throw new Error('CKEditor ClassicEditor not loaded. Please ensure the CDN script is included.')
    }

    const editor = await window.ClassicEditor.create(
      editorElement.value,
      editorConfig
    )

    editorInstance.value = editor

    // Set initial content
    if (props.modelValue) {
      editor.setData(props.modelValue)
    }

    // Listen for changes
    editor.model.document.on('change:data', () => {
      const data = editor.getData()
      emit('update:modelValue', data)
    })

    // Handle disabled state
    if (props.disabled) {
      editor.enableReadOnlyMode('disabled')
    }

    isReady.value = true
    emit('ready', editor)
  } catch (err) {
    error.value = err.message
    emit('error', err)
    console.error('CKEditor initialization error:', err)
  }
}

// Destroy editor instance
const destroyEditor = () => {
  if (editorInstance.value) {
    editorInstance.value.destroy()
      .then(() => {
        editorInstance.value = null
        isReady.value = false
      })
      .catch(err => {
        console.error('Error destroying CKEditor:', err)
      })
  }
}

// Watch for disabled state changes
watch(() => props.disabled, (newVal) => {
  if (editorInstance.value) {
    if (newVal) {
      editorInstance.value.enableReadOnlyMode('disabled')
    } else {
      editorInstance.value.disableReadOnlyMode('disabled')
    }
  }
})

// Watch for external content changes (when not caused by user input)
watch(() => props.modelValue, (newVal) => {
  if (editorInstance.value && isReady.value) {
    const currentData = editorInstance.value.getData()
    if (newVal !== currentData) {
      editorInstance.value.setData(newVal || '')
    }
  }
})

// Lifecycle hooks
onMounted(() => {
  // Wait for CKEditor CDN to load
  const checkCKEditorLoaded = setInterval(() => {
    if (window.ClassicEditor) {
      clearInterval(checkCKEditorLoaded)
      initializeEditor()
    }
  }, 100)

  // Timeout after 10 seconds
  setTimeout(() => {
    clearInterval(checkCKEditorLoaded)
    if (!window.ClassicEditor) {
      error.value = 'CKEditor failed to load. Please check your internet connection.'
      emit('error', new Error(error.value))
    }
  }, 10000)
})

onBeforeUnmount(() => {
  destroyEditor()
})

// Expose methods for parent components
defineExpose({
  getEditor: () => editorInstance.value,
  setData: (data) => {
    if (editorInstance.value) {
      editorInstance.value.setData(data)
    }
  },
  getData: () => {
    return editorInstance.value ? editorInstance.value.getData() : ''
  },
  focus: () => {
    if (editorInstance.value) {
      editorInstance.value.editing.view.focus()
    }
  }
})
</script>

<template>
  <div class="rich-text-editor">
    <!-- Loading State -->
    <div
      v-if="!isReady && !error"
      class="flex items-center justify-center p-8 bg-gray-50 dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600"
      :style="{ minHeight: minHeight }"
    >
      <div class="text-center">
        <svg
          class="animate-spin h-8 w-8 mx-auto mb-4 text-blue-600"
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
        <p class="text-sm text-gray-600 dark:text-gray-400">Loading editor...</p>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="p-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg"
    >
      <div class="flex">
        <svg
          class="h-5 w-5 text-red-400"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clip-rule="evenodd"
          />
        </svg>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
            Editor Error
          </h3>
          <p class="mt-1 text-sm text-red-700 dark:text-red-300">
            {{ error }}
          </p>
        </div>
      </div>
    </div>

    <!-- Editor Element -->
    <div
      ref="editorElement"
      class="ckeditor-content"
      :style="{ minHeight: minHeight }"
    ></div>
  </div>
</template>

<style>
/* CKEditor 5 Custom Styles */
.ck-editor__editable {
  min-height: 400px;
  border-radius: 0.5rem;
}

.ck-editor__editable_inline {
  border: 1px solid #e5e7eb;
  padding: 1rem;
}

/* Dark mode support */
.dark .ck-editor__editable_inline {
  background-color: #1f2937;
  color: #f3f4f6;
  border-color: #374151;
}

.dark .ck.ck-toolbar {
  background-color: #111827;
  border-color: #374151;
}

.dark .ck.ck-toolbar .ck-button:not(.ck-on):hover {
  background-color: #374151;
}

.dark .ck.ck-toolbar .ck-button.ck-on {
  background-color: #3b82f6;
  color: #ffffff;
}

/* Content styles for better readability */
.ck-content {
  line-height: 1.75;
}

.ck-content h2 {
  font-size: 1.875rem;
  font-weight: 700;
  margin-top: 1.5rem;
  margin-bottom: 1rem;
}

.ck-content h3 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-top: 1.25rem;
  margin-bottom: 0.75rem;
}

.ck-content h4 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}

.ck-content p {
  margin-bottom: 1rem;
}

.ck-content ul,
.ck-content ol {
  padding-left: 2rem;
  margin-bottom: 1rem;
}

.ck-content li {
  margin-bottom: 0.5rem;
}

.ck-content blockquote {
  border-left: 4px solid #3b82f6;
  padding-left: 1rem;
  margin: 1.5rem 0;
  font-style: italic;
  color: #6b7280;
}

.dark .ck-content blockquote {
  color: #9ca3af;
  border-left-color: #60a5fa;
}

.ck-content code {
  background-color: #f3f4f6;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-family: 'Monaco', 'Courier New', monospace;
  font-size: 0.875rem;
}

.dark .ck-content code {
  background-color: #374151;
  color: #f3f4f6;
}

.ck-content pre {
  background-color: #1f2937;
  color: #f3f4f6;
  padding: 1rem;
  border-radius: 0.5rem;
  overflow-x: auto;
  margin: 1rem 0;
}

.ck-content pre code {
  background-color: transparent;
  padding: 0;
  color: inherit;
}

.ck-content table {
  border-collapse: collapse;
  width: 100%;
  margin: 1.5rem 0;
}

.ck-content table td,
.ck-content table th {
  border: 1px solid #e5e7eb;
  padding: 0.75rem;
}

.dark .ck-content table td,
.dark .ck-content table th {
  border-color: #374151;
}

.ck-content table th {
  background-color: #f3f4f6;
  font-weight: 600;
}

.dark .ck-content table th {
  background-color: #374151;
}

.ck-content a {
  color: #3b82f6;
  text-decoration: underline;
}

.ck-content a:hover {
  color: #2563eb;
}

.dark .ck-content a {
  color: #60a5fa;
}

.dark .ck-content a:hover {
  color: #3b82f6;
}
</style>
