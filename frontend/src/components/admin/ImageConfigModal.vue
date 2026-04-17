<script setup>
import { ref, watch, computed } from 'vue'
import StockImageSearch from './StockImageSearch.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const uploading = ref(false)

const props = defineProps({
  visible: { type: Boolean, default: false },
  segment: { type: Object, default: null },
})
const emit = defineEmits(['apply', 'close'])

// ── Form state ──
const additionalNotes = ref('')
const faceRefs = ref([])
const styleRefs = ref([])
const brandRefs = ref([])  // brand/product images from manifest
const selectedModel = ref('nano-banana-pro')
const selectedStyle = ref('Photorealistic')

// ── Face ref upload ──
const faceFileInput = ref(null)
const facePasteUrl = ref('')
const showFacePaste = ref(false)

// ── Brand ref upload ──
const brandFileInput = ref(null)
const brandUploading = ref(false)

// ── Options ──
const modelOptions = ['nano-banana-pro', 'nano-banana-2', 'imagen-4']
const styleOptions = ['Photorealistic', 'Cinematic', 'Portrait Cinematic', 'Minimal', 'Abstract', 'Illustration']
const maxRefs = 3

// ── Reset when modal opens with new segment ──
// Filter out any stale blob: URLs (browser-only, can't be downloaded by GeminiGen)
const isUsableRef = (u) => typeof u === 'string' && u.length > 0 && !u.startsWith('blob:')
watch(() => [props.visible, props.segment?.index], ([vis]) => {
  if (vis && props.segment) {
    additionalNotes.value = props.segment.additional_notes || ''
    faceRefs.value = (props.segment.face_refs || []).filter(isUsableRef)
    styleRefs.value = (props.segment.style_refs || []).filter(isUsableRef)
    // brand_refs are {filename, url} objects — normalize legacy flat URLs
    brandRefs.value = (props.segment.brand_refs || []).map(ref => {
      if (typeof ref === 'string') return { filename: '', url: ref }
      return ref
    }).filter(ref => ref.url && !ref.url.startsWith('blob:'))
    selectedModel.value = props.segment.model || 'nano-banana-pro'
    selectedStyle.value = props.segment.style || 'Photorealistic'
    facePasteUrl.value = ''
    showFacePaste.value = false
  }
})

const segLabel = computed(() => props.segment?.label || 'Image')
const segConcept = computed(() => props.segment?.concept || '')

// ── Face ref handlers ──
async function handleFaceFileUpload(e) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return

  uploading.value = true
  try {
    for (const file of files) {
      if (faceRefs.value.length >= maxRefs) break
      const formData = new FormData()
      formData.append('file', file)
      const { data } = await api.post('/admin/content-engine/upload-reference', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      const url = data?.data?.url
      if (url) {
        faceRefs.value.push(url)
      } else {
        toast.error('Upload failed — no URL returned')
      }
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to upload face reference')
  } finally {
    uploading.value = false
    if (faceFileInput.value) faceFileInput.value.value = ''
  }
}

function handleFacePaste() {
  const url = facePasteUrl.value.trim()
  if (url && faceRefs.value.length < maxRefs) {
    faceRefs.value.push(url)
    facePasteUrl.value = ''
    showFacePaste.value = false
  }
}

function removeFaceRef(index) {
  faceRefs.value.splice(index, 1)
}

// ── Style ref from stock search ──
function handleStyleRefSelected(url) {
  if (url && styleRefs.value.length < maxRefs && !styleRefs.value.includes(url)) {
    styleRefs.value.push(url)
  }
}

function removeStyleRef(index) {
  styleRefs.value.splice(index, 1)
}

// ── Brand ref handlers ──
const brandManifestItems = computed(() => props.segment?.brand_manifest_items || [])

async function handleBrandFileUpload(e, manifestIndex) {
  const file = e.target.files?.[0]
  if (!file) return

  brandUploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await api.post('/admin/content-engine/upload-reference', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const url = data?.data?.url
    if (url) {
      // Map to manifest filename if uploading for a manifest entry
      const manifestItem = manifestIndex !== undefined ? brandManifestItems.value[manifestIndex] : null
      const entry = { filename: manifestItem?.filename || file.name, url }
      // Replace at manifest index position, or append
      if (manifestIndex !== undefined && manifestIndex < brandRefs.value.length) {
        brandRefs.value[manifestIndex] = entry
      } else {
        brandRefs.value.push(entry)
      }
    } else {
      toast.error('Upload failed — no URL returned')
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to upload brand reference')
  } finally {
    brandUploading.value = false
    if (brandFileInput.value) brandFileInput.value.value = ''
  }
}

function removeBrandRef(index) {
  brandRefs.value.splice(index, 1)
}

// ── Apply ──
function handleApply() {
  emit('apply', {
    additionalNotes: additionalNotes.value,
    faceRefs: [...faceRefs.value],
    styleRefs: [...styleRefs.value],
    brandRefs: [...brandRefs.value],
    model: selectedModel.value,
    style: selectedStyle.value,
  })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div @click="$emit('close')" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

      <!-- Modal -->
      <div class="relative w-full max-w-4xl max-h-[90vh] overflow-hidden bg-white dark:bg-neutral-800 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-700 flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 flex items-center justify-between shrink-0">
          <div class="min-w-0">
            <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Configure {{ segLabel }}</h2>
            <p v-if="segConcept" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 truncate max-w-md">"{{ segConcept }}"</p>
          </div>
          <button @click="$emit('close')" class="w-8 h-8 flex items-center justify-center rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Body: two columns -->
        <div class="flex-1 overflow-y-auto">
          <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-neutral-200 dark:divide-neutral-700">
            <!-- LEFT: Notes + Face Refs + Model/Style -->
            <div class="p-5 space-y-5">
              <!-- Additional Notes -->
              <div>
                <label class="block text-[11px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider mb-1.5">Additional Notes (optional)</label>
                <textarea
                  v-model="additionalNotes"
                  rows="3"
                  class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-neutral-200 dark:border-neutral-600/60 bg-neutral-50 dark:bg-neutral-900/50 text-neutral-900 dark:text-neutral-100 focus:ring-1 focus:ring-amber-500/50 focus:border-amber-500/50 placeholder-neutral-400 dark:placeholder-neutral-600 resize-none"
                  placeholder="e.g., More dramatic lighting, golden hour, Indonesian setting..."
                ></textarea>
              </div>

              <!-- Face Reference -->
              <div>
                <label class="block text-[11px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider mb-1.5">
                  <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                    Face Reference (optional)
                  </span>
                </label>
                <p class="text-[10px] text-neutral-400 dark:text-neutral-500 mb-2">Upload face/person photos for identity preservation</p>

                <!-- Face thumbnails -->
                <div v-if="faceRefs.length > 0" class="flex flex-wrap gap-2 mb-2">
                  <div v-for="(url, i) in faceRefs" :key="'face-' + i" class="relative group">
                    <img :src="url" class="w-16 h-16 object-cover rounded-lg border border-neutral-200 dark:border-neutral-700" />
                    <button @click="removeFaceRef(i)" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </div>

                <!-- Upload/paste actions -->
                <div v-if="faceRefs.length < maxRefs" class="flex items-center gap-2">
                  <button @click="faceFileInput?.click()" :disabled="uploading" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span class="inline-flex items-center gap-1">
                      <svg v-if="uploading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                      {{ uploading ? 'Uploading...' : 'Upload' }}
                    </span>
                  </button>
                  <button @click="showFacePaste = !showFacePaste" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
                    Paste URL
                  </button>
                  <input ref="faceFileInput" type="file" accept="image/*" multiple class="hidden" @change="handleFaceFileUpload" />
                </div>
                <p v-else class="text-[10px] text-amber-500">Max {{ maxRefs }} face references</p>

                <!-- Paste URL -->
                <div v-if="showFacePaste" class="flex gap-2 mt-2">
                  <input v-model="facePasteUrl" @keydown.enter="handleFacePaste" type="url" placeholder="https://..." class="flex-1 px-3 py-1.5 text-sm rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400" />
                  <button @click="handleFacePaste" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-neutral-600 hover:bg-neutral-700 text-white">Use</button>
                </div>

                <!-- Auto-instruction hint -->
                <div v-if="faceRefs.length > 0" class="mt-2 px-2.5 py-1.5 rounded-lg bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800/40">
                  <p class="text-[10px] text-cyan-700 dark:text-cyan-400">Auto: "Maintain exact facial identity, appearance, and features"</p>
                </div>
              </div>

              <!-- Model + Style -->
              <div class="flex gap-3">
                <div class="flex-1">
                  <label class="block text-[10px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider mb-1">Model</label>
                  <select v-model="selectedModel" class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-200 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900/50 text-neutral-900 dark:text-neutral-100 focus:ring-amber-500 focus:border-amber-500">
                    <option v-for="m in modelOptions" :key="m" :value="m">{{ m }}</option>
                  </select>
                </div>
                <div class="flex-1">
                  <label class="block text-[10px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider mb-1">Style</label>
                  <select v-model="selectedStyle" class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-200 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900/50 text-neutral-900 dark:text-neutral-100 focus:ring-amber-500 focus:border-amber-500">
                    <option v-for="s in styleOptions" :key="s" :value="s">{{ s }}</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- RIGHT: Style/Environment Reference (Stock Search) -->
            <div class="p-5 space-y-4">
              <label class="block text-[11px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">
                <span class="inline-flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                  Style / Environment Reference (optional)
                </span>
              </label>
              <p class="text-[10px] text-neutral-400 dark:text-neutral-500 -mt-2">Search stock images or upload references for visual consistency</p>

              <!-- Selected style refs -->
              <div v-if="styleRefs.length > 0" class="flex flex-wrap gap-2">
                <div v-for="(url, i) in styleRefs" :key="'style-' + i" class="relative group">
                  <img :src="url" class="w-20 h-14 object-cover rounded-lg border border-neutral-200 dark:border-neutral-700" />
                  <button @click="removeStyleRef(i)" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>

              <!-- Auto-instruction hint -->
              <div v-if="styleRefs.length > 0" class="px-2.5 py-1.5 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800/40">
                <p class="text-[10px] text-purple-700 dark:text-purple-400">Auto: "Maintain visual consistency for environment, style, and composition"</p>
              </div>

              <!-- Stock search (multi-select mode) -->
              <div v-if="styleRefs.length < maxRefs">
                <StockImageSearch
                  :model-value="''"
                  :orientation="segment?.aspect_ratio === '9:16' ? 'portrait' : 'landscape'"
                  multiple
                  @select="handleStyleRefSelected"
                />
              </div>
              <p v-else class="text-[10px] text-amber-500">Max {{ maxRefs }} style references</p>

              <!-- Brand / Product References (from manifest) -->
              <div v-if="brandManifestItems.length > 0 || brandRefs.length > 0" class="pt-4 mt-4 border-t border-neutral-200 dark:border-neutral-700">
                <label class="block text-[11px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider mb-1">
                  <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                    Brand / Product References
                  </span>
                </label>
                <p class="text-[10px] text-neutral-400 dark:text-neutral-500 mb-2">Upload brand logos, product screenshots for accurate rendering</p>

                <!-- Manifest checklist -->
                <div v-if="brandManifestItems.length > 0" class="space-y-2 mb-3">
                  <div v-for="(item, mi) in brandManifestItems" :key="'bm-' + mi" class="flex items-start gap-3 p-2.5 rounded-lg border" :class="brandRefs[mi]?.url ? 'border-green-200 dark:border-green-800/40 bg-green-50/50 dark:bg-green-900/10' : item.required ? 'border-amber-200 dark:border-amber-800/40 bg-amber-50/50 dark:bg-amber-900/10' : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/20'">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-neutral-700 dark:text-neutral-300 truncate">{{ item.filename }}</span>
                        <span :class="item.required ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' : 'bg-neutral-500/10 text-neutral-500 border-neutral-500/20'" class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded border">{{ item.required ? 'Wajib' : 'Opsional' }}</span>
                      </div>
                      <p class="text-[10px] text-neutral-500 dark:text-neutral-400 mt-0.5">{{ item.description }}</p>
                    </div>
                    <!-- Upload state -->
                    <div class="flex-shrink-0">
                      <div v-if="brandRefs[mi]?.url" class="flex items-center gap-1.5">
                        <img :src="brandRefs[mi].url" class="w-10 h-10 object-cover rounded border border-green-300 dark:border-green-700" />
                        <button @click="removeBrandRef(mi)" class="w-5 h-5 rounded-full bg-red-500/80 text-white flex items-center justify-center hover:bg-red-600" title="Remove">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </div>
                      <label v-else class="cursor-pointer px-3 py-1.5 text-xs font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        Upload
                        <input type="file" accept="image/*" class="hidden" @change="(e) => handleBrandFileUpload(e, mi)" />
                      </label>
                    </div>
                  </div>
                </div>

                <!-- Manual brand ref upload (when no manifest, or additional) -->
                <div v-if="brandRefs.length > 0 && brandManifestItems.length === 0" class="flex flex-wrap gap-2 mb-2">
                  <div v-for="(ref, i) in brandRefs" :key="'brand-' + i" class="relative group">
                    <img :src="ref.url || ref" class="w-16 h-12 object-cover rounded-lg border border-neutral-200 dark:border-neutral-700" />
                    <button @click="removeBrandRef(i)" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </div>
                <div v-if="brandRefs.length < maxRefs && brandManifestItems.length === 0">
                  <label class="cursor-pointer px-3 py-1.5 text-xs font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Add Brand Image
                    <input ref="brandFileInput" type="file" accept="image/*" class="hidden" @change="(e) => handleBrandFileUpload(e)" />
                  </label>
                </div>

                <div v-if="brandRefs.length > 0" class="mt-2 px-2.5 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/40">
                  <p class="text-[10px] text-blue-700 dark:text-blue-400">Brand images sent as file_urls for accurate logo/product rendering</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700 flex items-center justify-end gap-3 shrink-0">
          <button @click="$emit('close')" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
            Cancel
          </button>
          <button @click="handleApply" class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            Apply Configuration
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
