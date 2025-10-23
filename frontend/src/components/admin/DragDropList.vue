<script setup>
import { ref, onMounted, watch } from 'vue'
import Sortable from 'sortablejs'

const props = defineProps({
  items: {
    type: Array,
    required: true
  },
  itemKey: {
    type: String,
    default: 'id'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  tag: {
    type: String,
    default: 'div'
  }
})

const emit = defineEmits(['reorder'])

const listRef = ref(null)
let sortableInstance = null

const initSortable = () => {
  if (!listRef.value || props.disabled) return

  // Destroy existing instance if any
  if (sortableInstance) {
    sortableInstance.destroy()
  }

  sortableInstance = Sortable.create(listRef.value, {
    animation: 150,
    handle: '.drag-handle',
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    chosenClass: 'sortable-chosen',
    disabled: props.disabled,
    onEnd: (evt) => {
      // Create new array with updated sequences
      const reorderedItems = [...props.items]
      const movedItem = reorderedItems.splice(evt.oldIndex, 1)[0]
      reorderedItems.splice(evt.newIndex, 0, movedItem)

      // Update sequence numbers
      const itemsWithSequence = reorderedItems.map((item, index) => ({
        [props.itemKey]: item[props.itemKey],
        sequence: index
      }))

      emit('reorder', itemsWithSequence)
    }
  })
}

onMounted(() => {
  initSortable()
})

watch(() => props.disabled, () => {
  initSortable()
})

watch(() => props.items, () => {
  // Reinit when items change significantly
  if (sortableInstance) {
    sortableInstance.destroy()
    initSortable()
  }
}, { deep: false })
</script>

<template>
  <component :is="tag" ref="listRef" :class="tag !== 'tbody' ? 'space-y-2' : ''">
    <slot />
  </component>
</template>

<style scoped>
:deep(.sortable-ghost) {
  opacity: 0.4;
  background: #f3f4f6;
  border: 2px dashed #3b82f6;
}

:deep(.sortable-drag) {
  opacity: 1;
  cursor: move;
}

:deep(.sortable-chosen) {
  background: #eff6ff;
  border-color: #3b82f6;
}

:deep(.drag-handle) {
  cursor: grab;
  touch-action: none;
}

:deep(.drag-handle:active) {
  cursor: grabbing;
}
</style>
