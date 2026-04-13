<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  currentStep: { type: Number, required: true },
  ideaId: { type: [Number, String], required: true },
  ideaStatus: { type: String, default: '' },
})

const router = useRouter()

const steps = [
  { num: 1, label: 'Article', route: 'preview' },
  { num: 2, label: 'Images', route: 'images' },
  { num: 3, label: 'Finalize', route: 'finalize' },
]

const statusToStep = {
  article_ready: 1,
  generating_images: 2,
  images_ready: 2,
  completed: 3,
}

const completedUpTo = computed(() => {
  const s = statusToStep[props.ideaStatus] ?? 0
  // Current step is active, previous steps are completed
  return Math.max(s, props.currentStep) - 1
})

function stepState(stepNum) {
  if (stepNum === props.currentStep) return 'active'
  if (stepNum <= completedUpTo.value) return 'completed'
  return 'pending'
}

function isClickable(stepNum) {
  return stepState(stepNum) === 'completed'
}

function navigate(step) {
  if (!isClickable(step.num)) return
  router.push(`/admin/content-engine/${props.ideaId}/${step.route}`)
}
</script>

<template>
  <div class="bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-4 sm:px-6 py-3">
    <div class="max-w-3xl mx-auto flex items-center justify-center gap-0">
      <template v-for="(step, i) in steps" :key="step.num">
        <!-- Step circle + label -->
        <button
          @click="navigate(step)"
          :disabled="!isClickable(step.num)"
          :class="[
            'flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium transition-colors',
            stepState(step.num) === 'active'
              ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
              : stepState(step.num) === 'completed'
                ? 'text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 cursor-pointer'
                : 'text-neutral-400 dark:text-neutral-500 cursor-default',
          ]"
        >
          <!-- Icon -->
          <span v-if="stepState(step.num) === 'completed'" class="w-5 h-5 flex items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400 text-xs">&#10003;</span>
          <span v-else-if="stepState(step.num) === 'active'" class="w-5 h-5 flex items-center justify-center rounded-full bg-amber-500 text-white text-xs font-bold">{{ step.num }}</span>
          <span v-else class="w-5 h-5 flex items-center justify-center rounded-full border border-neutral-300 dark:border-neutral-600 text-neutral-400 text-xs">{{ step.num }}</span>
          {{ step.label }}
        </button>

        <!-- Connector line -->
        <div v-if="i < steps.length - 1" :class="['flex-1 h-px mx-2 max-w-16', stepState(steps[i + 1].num) !== 'pending' ? 'bg-green-300 dark:bg-green-700' : 'bg-neutral-200 dark:bg-neutral-700']"></div>
      </template>
    </div>
  </div>
</template>
