<script setup>
import { ref, computed } from 'vue'
import { useContentEngine } from '@/composables/useContentEngine'

const props = defineProps({
  ideaId: { type: [Number, String], required: true },
  entityName: { type: String, required: true },
  entityType: { type: String, required: true },
  status: { type: String, required: true },
  fetchedUrl: { type: String, default: null },
  license: { type: String, default: null },
  reason: { type: String, default: null },
  required: { type: Boolean, default: true },
})

const emit = defineEmits(['resolved', 'skipped'])

const { uploadEntityReference, skipEntityReference, isLoading } = useContentEngine()

const fileInput = ref(null)
const uploadError = ref(null)

const entityIcon = computed(() => ({
  person: '👤',
  landmark: '🏛️',
  logo: '🏢',
  product: '📦',
}[props.entityType] || '•'))

const statusClass = computed(() => {
  if (props.status === 'fetched' || props.status === 'resolved') {
    return 'border-green-500/40 bg-green-500/10'
  }
  if (props.status === 'skipped') {
    return 'border-amber-500/40 bg-amber-500/10'
  }
  return 'border-red-500/50 bg-red-500/10'
})

async function handleFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) return

  uploadError.value = null
  const result = await uploadEntityReference(props.ideaId, props.entityName, props.entityType, file)

  if (result.success) {
    emit('resolved', {
      entityName: props.entityName,
      stillBlocking: result.data?.still_blocking ?? false,
    })
  } else {
    uploadError.value = result.error
  }

  if (fileInput.value) fileInput.value.value = ''
}

async function handleSkip() {
  if (!confirm(`Skip ${props.entityName}? The cover will fall back to using your face instead of the named entity.`)) return

  uploadError.value = null
  const result = await skipEntityReference(props.ideaId, props.entityName)

  if (result.success) {
    emit('skipped', {
      entityName: props.entityName,
      stillBlocking: result.data?.still_blocking ?? false,
    })
  } else {
    uploadError.value = result.error
  }
}

function openFilePicker() {
  fileInput.value?.click()
}
</script>

<template>
  <div
    :class="['rounded-lg border px-4 py-3 transition', statusClass]"
    class="flex flex-wrap items-center gap-3"
  >
    <span class="text-xl" :title="entityType">{{ entityIcon }}</span>

    <div class="min-w-0 flex-1">
      <div class="font-medium text-sm text-gray-900 dark:text-white">
        {{ entityName }}
      </div>

      <template v-if="status === 'fetched' || status === 'resolved'">
        <div class="text-xs text-green-700 dark:text-green-300 mt-0.5">
          ✓ Auto-fetched · {{ license || 'licensed' }}
        </div>
      </template>

      <template v-else-if="status === 'skipped'">
        <div class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
          ⏭️ Skipped — will use creator face
        </div>
      </template>

      <template v-else>
        <div class="text-xs text-red-700 dark:text-red-300 mt-0.5">
          ⚠️ {{ reason || 'Reference unavailable — upload required' }}
        </div>
      </template>

      <div v-if="uploadError" class="text-xs text-red-600 mt-1">
        {{ uploadError }}
      </div>
    </div>

    <template v-if="status !== 'fetched' && status !== 'resolved' && status !== 'skipped'">
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="handleFileChange"
      >
      <button
        type="button"
        :disabled="isLoading"
        class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
        @click="openFilePicker"
      >
        📤 Upload
      </button>
      <button
        type="button"
        :disabled="isLoading"
        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        @click="handleSkip"
      >
        ⏭️ Skip
      </button>
    </template>

    <img
      v-if="(status === 'fetched' || status === 'resolved') && fetchedUrl"
      :src="fetchedUrl"
      :alt="entityName"
      class="h-10 w-10 rounded object-cover ring-1 ring-black/10"
    >
  </div>
</template>
