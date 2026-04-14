<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  sections: {
    type: Array,
    required: true,
    // Each: { position: number, title: string, arc_phase: string, image_concept: string|null }
  },
  cover: {
    type: Object,
    default: null,
    // { position: 0, title: string, concept: string }
  },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:concept', 'regenerate'])

const editingPosition = ref(null)
const draftConcept = ref('')

const rows = computed(() => {
  const list = []
  if (props.cover) {
    list.push({
      position: props.cover.position ?? 0,
      title: props.cover.title || 'Cover',
      arc_phase: 'hook',
      image_concept: props.cover.concept || '',
      isCover: true,
    })
  }
  for (const s of props.sections || []) {
    if (!s || s.image_concept == null) continue
    list.push({
      position: s.position,
      title: s.title,
      arc_phase: s.arc_phase || '',
      image_concept: s.image_concept,
      isCover: false,
    })
  }
  return list
})

watch(() => editingPosition.value, (pos) => {
  if (pos == null) return
  const row = rows.value.find(r => r.position === pos)
  draftConcept.value = row?.image_concept || ''
})

function startEdit(position) {
  if (props.disabled) return
  editingPosition.value = position
}

function cancelEdit() {
  editingPosition.value = null
  draftConcept.value = ''
}

function commitEdit() {
  if (editingPosition.value == null) return
  emit('update:concept', {
    position: editingPosition.value,
    concept: draftConcept.value.trim(),
  })
  editingPosition.value = null
  draftConcept.value = ''
}

function regenerateOne(position) {
  if (props.disabled) return
  emit('regenerate', { sections: [position] })
}

function regenerateAll() {
  if (props.disabled) return
  emit('regenerate', { sections: [] })
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h4 class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">
        Image Concepts ({{ rows.length }})
      </h4>
      <button
        type="button"
        :disabled="disabled || rows.length === 0"
        @click="regenerateAll"
        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        Regenerate All
      </button>
    </div>

    <div v-if="rows.length === 0" class="text-sm text-neutral-500 dark:text-neutral-400 italic">
      No image concepts found in outline.
    </div>

    <ul v-else class="divide-y divide-neutral-200 dark:divide-neutral-700 border border-neutral-200 dark:border-neutral-700 rounded-lg overflow-hidden">
      <li
        v-for="row in rows"
        :key="`concept-${row.position}`"
        class="p-3 bg-white dark:bg-neutral-800"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span
                class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded"
                :class="row.isCover
                  ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                  : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'"
              >
                {{ row.isCover ? 'Cover' : `#${row.position}` }}
              </span>
              <span class="text-xs text-neutral-500 dark:text-neutral-400">
                {{ row.title }}
              </span>
              <span
                v-if="row.arc_phase"
                class="text-[10px] uppercase tracking-wider text-neutral-400 dark:text-neutral-500"
              >
                {{ row.arc_phase }}
              </span>
            </div>

            <div v-if="editingPosition === row.position">
              <textarea
                v-model="draftConcept"
                rows="3"
                class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
              <div class="flex gap-2 mt-2">
                <button
                  type="button"
                  @click="commitEdit"
                  class="px-3 py-1 text-xs font-medium rounded bg-green-600 text-white hover:bg-green-700"
                >
                  Save
                </button>
                <button
                  type="button"
                  @click="cancelEdit"
                  class="px-3 py-1 text-xs font-medium rounded border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                >
                  Cancel
                </button>
              </div>
            </div>

            <p
              v-else
              @dblclick="startEdit(row.position)"
              class="text-sm text-neutral-700 dark:text-neutral-300 cursor-text"
              :class="{ 'opacity-50': disabled }"
            >
              {{ row.image_concept || '(empty — double-click to add)' }}
            </p>
          </div>

          <div class="flex flex-col gap-1">
            <button
              v-if="editingPosition !== row.position"
              type="button"
              :disabled="disabled"
              @click="startEdit(row.position)"
              class="px-2 py-1 text-xs font-medium rounded text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700 disabled:opacity-50 disabled:cursor-not-allowed"
              title="Edit concept"
            >
              Edit
            </button>
            <button
              type="button"
              :disabled="disabled"
              @click="regenerateOne(row.position)"
              class="px-2 py-1 text-xs font-medium rounded text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 disabled:opacity-50 disabled:cursor-not-allowed"
              title="Regenerate this section's prompt"
            >
              Regen
            </button>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
