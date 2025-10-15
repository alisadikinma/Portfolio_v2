<template>
  <div class="w-full">
    <!-- Label -->
    <label
      v-if="label"
      :for="inputId"
      class="block text-sm font-medium mb-1"
      :class="labelClass"
    >
      {{ label }}
      <span v-if="required" class="text-error-500">*</span>
    </label>

    <!-- Input Wrapper -->
    <div class="relative">
      <!-- Prefix Icon/Text -->
      <div
        v-if="$slots.prefix || prefixIcon"
        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
      >
        <slot name="prefix">
          <component v-if="prefixIcon" :is="prefixIcon" class="h-5 w-5 text-neutral-400" />
        </slot>
      </div>

      <!-- Input Element -->
      <input
        v-if="type !== 'textarea'"
        :id="inputId"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :autocomplete="autocomplete"
        :class="inputClasses"
        @input="handleInput"
        @blur="handleBlur"
        @focus="handleFocus"
      />

      <!-- Textarea Element -->
      <textarea
        v-else
        :id="inputId"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :rows="rows"
        :class="inputClasses"
        @input="handleInput"
        @blur="handleBlur"
        @focus="handleFocus"
      />

      <!-- Suffix Icon/Text -->
      <div
        v-if="$slots.suffix || suffixIcon || clearable"
        class="absolute inset-y-0 right-0 pr-3 flex items-center"
      >
        <button
          v-if="clearable && modelValue"
          type="button"
          class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
          @click="handleClear"
        >
          <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </button>
        <slot v-else name="suffix">
          <component v-if="suffixIcon" :is="suffixIcon" class="h-5 w-5 text-neutral-400" />
        </slot>
      </div>
    </div>

    <!-- Helper Text / Error Message -->
    <p
      v-if="helperText || errorMessage"
      class="mt-1 text-sm"
      :class="errorMessage ? 'text-error-600 dark:text-error-400' : 'text-neutral-500 dark:text-neutral-400'"
    >
      {{ errorMessage || helperText }}
    </p>
  </div>
</template>

<script setup>
import { computed, ref, useSlots } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  type: {
    type: String,
    default: 'text'
  },
  label: {
    type: String,
    default: null
  },
  placeholder: {
    type: String,
    default: ''
  },
  helperText: {
    type: String,
    default: null
  },
  errorMessage: {
    type: String,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  },
  readonly: {
    type: Boolean,
    default: false
  },
  required: {
    type: Boolean,
    default: false
  },
  clearable: {
    type: Boolean,
    default: false
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  rounded: {
    type: String,
    default: 'lg',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'xl', 'full'].includes(value)
  },
  prefixIcon: {
    type: Object,
    default: null
  },
  suffixIcon: {
    type: Object,
    default: null
  },
  autocomplete: {
    type: String,
    default: 'off'
  },
  rows: {
    type: Number,
    default: 4
  },
  labelClass: {
    type: String,
    default: 'text-neutral-700 dark:text-neutral-300'
  }
})

const emit = defineEmits(['update:modelValue', 'blur', 'focus', 'clear'])
const slots = useSlots()

const inputId = ref(`input-${Math.random().toString(36).substring(7)}`)

const handleInput = (event) => {
  emit('update:modelValue', event.target.value)
}

const handleBlur = (event) => {
  emit('blur', event)
}

const handleFocus = (event) => {
  emit('focus', event)
}

const handleClear = () => {
  emit('update:modelValue', '')
  emit('clear')
}

const inputClasses = computed(() => {
  const classes = [
    'w-full border transition-all duration-200',
    'bg-white dark:bg-neutral-800',
    'text-neutral-900 dark:text-neutral-100',
    'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
    'focus:outline-none focus:ring-2 focus:border-transparent'
  ]

  // Size
  const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-base',
    lg: 'px-5 py-3 text-lg'
  }
  classes.push(sizeClasses[props.size])

  // Padding adjustments for icons
  if (slots.prefix || props.prefixIcon) {
    classes.push('pl-10')
  }
  if (slots.suffix || props.suffixIcon || props.clearable) {
    classes.push('pr-10')
  }

  // Border Radius
  const roundedClasses = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    xl: 'rounded-xl',
    full: 'rounded-full'
  }
  classes.push(roundedClasses[props.rounded])

  // State classes
  if (props.errorMessage) {
    classes.push('border-error-500 focus:ring-error-500')
  } else {
    classes.push('border-neutral-300 dark:border-neutral-600 focus:ring-primary-500')
  }

  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed bg-neutral-100 dark:bg-neutral-900')
  }

  if (props.readonly) {
    classes.push('bg-neutral-50 dark:bg-neutral-900 cursor-default')
  }

  return classes.join(' ')
})
</script>
