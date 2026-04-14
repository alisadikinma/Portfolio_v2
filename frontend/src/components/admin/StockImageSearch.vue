<script setup>
import { ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps({
  modelValue: { type: String, default: '' },
  orientation: { type: String, default: 'landscape' },
  multiple: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'select'])

const searchQuery = ref('')
const results = ref([])
const isSearching = ref(false)
const searchError = ref('')
const showUrlInput = ref(false)
const pasteUrl = ref('')
const fileInput = ref(null)

const CACHE_PREFIX = 'stock_search_'
const CACHE_TTL = 7 * 24 * 60 * 60 * 1000 // 7 days

function getCached(key) {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (Date.now() - parsed.ts > CACHE_TTL) {
      localStorage.removeItem(key)
      return null
    }
    return parsed.data
  } catch { return null }
}

function setCache(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify({ ts: Date.now(), data }))
  } catch { /* localStorage full */ }
}

async function handleSearch() {
  const q = searchQuery.value.trim()
  if (!q) return

  const cacheKey = `${CACHE_PREFIX}${q}_${props.orientation}`
  const cached = getCached(cacheKey)
  if (cached) {
    results.value = cached
    return
  }

  isSearching.value = true
  searchError.value = ''
  try {
    const response = await api.get('/admin/content-engine/stock-images/search', {
      params: { q, orientation: props.orientation, per_page: 20 },
    })
    const data = response.data
    if (data.success && data.data?.results) {
      results.value = data.data.results
      if (data.data.results.length > 0) {
        setCache(cacheKey, data.data.results)
      }
    } else {
      results.value = []
    }
  } catch (err) {
    searchError.value = err.response?.data?.message || 'Search failed'
    results.value = []
  } finally {
    isSearching.value = false
  }
}

function selectImage(url) {
  if (props.multiple) {
    emit('select', url)
  } else {
    emit('update:modelValue', url)
  }
}

function removeSelected() {
  emit('update:modelValue', '')
}

function handleFileUpload(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const url = URL.createObjectURL(file)
  emit('update:modelValue', url)
}

function handlePasteUrl() {
  const url = pasteUrl.value.trim()
  if (url) {
    emit('update:modelValue', url)
    pasteUrl.value = ''
    showUrlInput.value = false
  }
}
</script>

<template>
  <div class="space-y-3">
    <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">Reference Image</label>

    <!-- Selected reference preview (single mode only) -->
    <div v-if="modelValue && !multiple" class="flex items-start gap-3">
      <img :src="modelValue" alt="Reference" class="w-20 h-14 object-cover rounded-lg border border-neutral-200 dark:border-neutral-700" />
      <button @click="removeSelected" class="text-xs text-red-500 hover:text-red-600 mt-1">Remove</button>
    </div>

    <!-- Search + actions (always visible in multi mode) -->
    <div v-if="!modelValue || multiple" class="space-y-2">
      <!-- Search bar -->
      <div class="flex gap-2">
        <input
          v-model="searchQuery"
          @keydown.enter="handleSearch"
          type="text"
          placeholder="Search stock photos..."
          class="flex-1 px-3 py-1.5 text-sm rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400"
        />
        <button @click="handleSearch" :disabled="isSearching || !searchQuery.trim()" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white disabled:opacity-50 transition-colors whitespace-nowrap">
          {{ isSearching ? '...' : 'Search' }}
        </button>
      </div>

      <!-- Upload + URL buttons -->
      <div class="flex items-center gap-2 text-xs">
        <button @click="fileInput?.click()" class="px-2 py-1 rounded border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
          Upload
        </button>
        <button @click="showUrlInput = !showUrlInput" class="px-2 py-1 rounded border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
          Paste URL
        </button>
        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleFileUpload" />
      </div>

      <!-- Paste URL input -->
      <div v-if="showUrlInput" class="flex gap-2">
        <input
          v-model="pasteUrl"
          @keydown.enter="handlePasteUrl"
          type="url"
          placeholder="https://example.com/image.jpg"
          class="flex-1 px-3 py-1.5 text-sm rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400"
        />
        <button @click="handlePasteUrl" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-neutral-600 hover:bg-neutral-700 text-white transition-colors">
          Use
        </button>
      </div>

      <!-- Error -->
      <p v-if="searchError" class="text-xs text-red-500">{{ searchError }}</p>

      <!-- Results grid -->
      <div v-if="results.length > 0" class="grid grid-cols-4 gap-1.5 max-h-48 overflow-y-auto">
        <button
          v-for="img in results"
          :key="img.id"
          @click="selectImage(img.url_regular)"
          class="relative aspect-[4/3] rounded-lg overflow-hidden border border-neutral-200 dark:border-neutral-700 hover:border-amber-400 dark:hover:border-amber-500 transition-colors group"
        >
          <img :src="img.url_thumb" :alt="img.alt" class="w-full h-full object-cover" loading="lazy" />
          <div class="absolute bottom-0 inset-x-0 bg-black/50 text-white text-[10px] px-1 py-0.5 truncate opacity-0 group-hover:opacity-100 transition-opacity">
            {{ img.photographer }}
          </div>
        </button>
      </div>

      <!-- Empty state -->
      <p v-else-if="searchQuery && !isSearching && results.length === 0" class="text-xs text-neutral-400 italic">
        No results found
      </p>
    </div>
  </div>
</template>
