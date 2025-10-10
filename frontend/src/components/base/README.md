# Base UI Components

A comprehensive collection of reusable Vue 3 components built with Tailwind CSS for the Portfolio V2 project.

## Components

### BaseButton

A versatile button component with multiple variants, sizes, and states.

**Props:**
- `variant` (String): Button style variant - `primary`, `secondary`, `outline`, `ghost`, `danger`, `success` (default: `primary`)
- `size` (String): Button size - `sm`, `md`, `lg`, `xl` (default: `md`)
- `tag` (String): HTML tag to render - `button`, `a`, etc. (default: `button`)
- `nativeType` (String): Native button type - `button`, `submit`, `reset` (default: `button`)
- `disabled` (Boolean): Disable button (default: `false`)
- `loading` (Boolean): Show loading spinner (default: `false`)
- `icon` (Component): Icon component to display
- `iconPosition` (String): Icon position - `left`, `right` (default: `left`)
- `block` (Boolean): Full width button (default: `false`)
- `rounded` (String): Border radius - `none`, `sm`, `md`, `lg`, `xl`, `full` (default: `lg`)

**Events:**
- `click` - Emitted when button is clicked

**Usage:**
```vue
<template>
  <!-- Primary Button -->
  <BaseButton @click="handleClick">Click Me</BaseButton>

  <!-- Secondary with Icon -->
  <BaseButton variant="secondary" :icon="PlusIcon">Add Item</BaseButton>

  <!-- Loading State -->
  <BaseButton :loading="isLoading">Submit</BaseButton>

  <!-- Danger Button -->
  <BaseButton variant="danger" size="lg">Delete</BaseButton>
</template>

<script setup>
import { BaseButton } from '@/components/base'
import { PlusIcon } from '@heroicons/vue/24/outline'
</script>
```

---

### BaseCard

A flexible card component with header, body, and footer slots.

**Props:**
- `tag` (String): HTML tag to render (default: `div`)
- `title` (String): Card title
- `variant` (String): Card style - `default`, `bordered`, `elevated`, `flat` (default: `default`)
- `padding` (String): Card padding - `none`, `sm`, `md`, `lg`, `xl` (default: `md`)
- `hover` (Boolean): Enable hover effect (default: `false`)
- `clickable` (Boolean): Show pointer cursor (default: `false`)
- `rounded` (String): Border radius - `none`, `sm`, `md`, `lg`, `xl`, `2xl`, `3xl` (default: `lg`)
- `bodyClass` (String): Custom class for body

**Slots:**
- `header` - Card header content
- `default` - Card body content
- `footer` - Card footer content

**Usage:**
```vue
<template>
  <!-- Basic Card -->
  <BaseCard title="Card Title">
    <p>Card content goes here</p>
  </BaseCard>

  <!-- Card with Custom Header and Footer -->
  <BaseCard hover>
    <template #header>
      <h3>Custom Header</h3>
    </template>
    <p>Main content</p>
    <template #footer>
      <BaseButton>Action</BaseButton>
    </template>
  </BaseCard>
</template>

<script setup>
import { BaseCard, BaseButton } from '@/components/base'
</script>
```

---

### BaseInput

A comprehensive input component with validation states, icons, and helper text.

**Props:**
- `modelValue` (String|Number): Input value (v-model)
- `type` (String): Input type - `text`, `email`, `password`, `number`, `textarea`, etc. (default: `text`)
- `label` (String): Input label
- `placeholder` (String): Placeholder text
- `helperText` (String): Helper text below input
- `errorMessage` (String): Error message (shows error state)
- `disabled` (Boolean): Disable input (default: `false`)
- `readonly` (Boolean): Read-only input (default: `false`)
- `required` (Boolean): Required field indicator (default: `false`)
- `clearable` (Boolean): Show clear button (default: `false`)
- `size` (String): Input size - `sm`, `md`, `lg` (default: `md`)
- `rounded` (String): Border radius - `none`, `sm`, `md`, `lg`, `xl`, `full` (default: `lg`)
- `prefixIcon` (Component): Icon before input
- `suffixIcon` (Component): Icon after input
- `autocomplete` (String): Autocomplete attribute (default: `off`)
- `rows` (Number): Textarea rows (default: `4`)

**Events:**
- `update:modelValue` - V-model update
- `blur` - Input blur event
- `focus` - Input focus event
- `clear` - Clear button clicked

**Slots:**
- `prefix` - Custom prefix content
- `suffix` - Custom suffix content

**Usage:**
```vue
<template>
  <!-- Text Input with Label -->
  <BaseInput
    v-model="email"
    label="Email"
    type="email"
    placeholder="you@example.com"
    required
  />

  <!-- Input with Icon -->
  <BaseInput
    v-model="search"
    placeholder="Search..."
    :prefixIcon="MagnifyingGlassIcon"
    clearable
  />

  <!-- Textarea with Error -->
  <BaseInput
    v-model="message"
    type="textarea"
    label="Message"
    :rows="5"
    :errorMessage="validationError"
    helperText="Max 500 characters"
  />
</template>

<script setup>
import { ref } from 'vue'
import { BaseInput } from '@/components/base'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const email = ref('')
const search = ref('')
const message = ref('')
const validationError = ref(null)
</script>
```

---

### BaseBadge

A badge component for labels, tags, and status indicators.

**Props:**
- `variant` (String): Badge color - `primary`, `secondary`, `success`, `error`, `warning`, `info`, `neutral` (default: `primary`)
- `size` (String): Badge size - `sm`, `md`, `lg` (default: `md`)
- `rounded` (String): Border radius - `none`, `sm`, `md`, `lg`, `full` (default: `full`)
- `outlined` (Boolean): Outlined style (default: `false`)
- `closable` (Boolean): Show close button (default: `false`)
- `icon` (Component): Icon to display

**Events:**
- `close` - Emitted when close button clicked

**Usage:**
```vue
<template>
  <!-- Success Badge -->
  <BaseBadge variant="success">Active</BaseBadge>

  <!-- Error Badge with Close -->
  <BaseBadge variant="error" closable @close="handleClose">Failed</BaseBadge>

  <!-- Outlined Badge -->
  <BaseBadge variant="primary" outlined>New</BaseBadge>

  <!-- Badge with Icon -->
  <BaseBadge variant="warning" :icon="ExclamationIcon">Warning</BaseBadge>
</template>

<script setup>
import { BaseBadge } from '@/components/base'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const handleClose = () => {
  console.log('Badge closed')
}
</script>
```

---

### BaseLoader

A loading indicator component with multiple types and sizes.

**Props:**
- `type` (String): Loader type - `spinner`, `dots`, `pulse`, `bar` (default: `spinner`)
- `size` (String): Loader size - `xs`, `sm`, `md`, `lg`, `xl` (default: `md`)
- `color` (String): Loader color - `primary`, `secondary`, `success`, `error`, `warning`, `neutral` (default: `primary`)
- `text` (String): Loading text to display
- `progress` (Number): Progress percentage for bar type (0-100)
- `fullscreen` (Boolean): Fullscreen loader (default: `false`)
- `overlay` (Boolean): Show backdrop overlay (default: `false`)

**Usage:**
```vue
<template>
  <!-- Spinner Loader -->
  <BaseLoader />

  <!-- Dots Loader with Text -->
  <BaseLoader type="dots" text="Loading..." />

  <!-- Progress Bar -->
  <BaseLoader type="bar" :progress="75" color="success" />

  <!-- Fullscreen Overlay -->
  <BaseLoader fullscreen overlay text="Please wait..." />
</template>

<script setup>
import { BaseLoader } from '@/components/base'
</script>
```

---

### BaseModal

A modal dialog component with transitions and customizable sizes.

**Props:**
- `modelValue` (Boolean): Modal visibility (v-model)
- `title` (String): Modal title
- `size` (String): Modal size - `sm`, `md`, `lg`, `xl`, `full` (default: `md`)
- `closable` (Boolean): Show close button (default: `true`)
- `closeOnOverlay` (Boolean): Close on overlay click (default: `true`)
- `closeOnEsc` (Boolean): Close on ESC key (default: `true`)
- `padding` (String): Body padding - `none`, `sm`, `md`, `lg` (default: `md`)
- `centered` (Boolean): Center modal (default: `true`)

**Events:**
- `update:modelValue` - V-model update
- `close` - Emitted when modal closes

**Slots:**
- `header` - Custom header content
- `default` - Modal body content
- `footer` - Modal footer content

**Usage:**
```vue
<template>
  <BaseButton @click="showModal = true">Open Modal</BaseButton>

  <BaseModal v-model="showModal" title="Confirm Action" size="md">
    <p>Are you sure you want to proceed?</p>

    <template #footer>
      <BaseButton variant="ghost" @click="showModal = false">Cancel</BaseButton>
      <BaseButton variant="primary" @click="handleConfirm">Confirm</BaseButton>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref } from 'vue'
import { BaseModal, BaseButton } from '@/components/base'

const showModal = ref(false)

const handleConfirm = () => {
  console.log('Confirmed')
  showModal.value = false
}
</script>
```

---

### BaseToast

A toast notification component for displaying messages.

**Props:**
- `position` (String): Toast position - `top-left`, `top-center`, `top-right`, `bottom-left`, `bottom-center`, `bottom-right` (default: `top-right`)

**Methods (via ref):**
- `addToast(options)` - Add a new toast notification
  - `options.type` (String): Toast type - `success`, `error`, `warning`, `info`, `default`
  - `options.title` (String): Toast title (optional)
  - `options.message` (String): Toast message
  - `options.duration` (Number): Auto-dismiss duration in ms (default: 5000, set to 0 for persistent)
  - `options.closable` (Boolean): Show close button (default: true)
- `removeToast(id)` - Remove a toast by ID

**Usage:**
```vue
<template>
  <BaseToast ref="toastRef" position="top-right" />

  <BaseButton @click="showSuccess">Show Success</BaseButton>
  <BaseButton @click="showError">Show Error</BaseButton>
</template>

<script setup>
import { ref } from 'vue'
import { BaseToast, BaseButton } from '@/components/base'

const toastRef = ref(null)

const showSuccess = () => {
  toastRef.value.addToast({
    type: 'success',
    title: 'Success!',
    message: 'Operation completed successfully',
    duration: 5000
  })
}

const showError = () => {
  toastRef.value.addToast({
    type: 'error',
    message: 'Something went wrong',
    duration: 0 // Persistent toast
  })
}
</script>
```

---

## Global Registration (Optional)

To use components globally, register them in `main.js`:

```javascript
import { createApp } from 'vue'
import App from './App.vue'
import * as BaseComponents from '@/components/base'

const app = createApp(App)

// Register all base components globally
Object.entries(BaseComponents).forEach(([name, component]) => {
  app.component(name, component)
})

app.mount('#app')
```

---

## Styling

All components use Tailwind CSS classes from the custom configuration and support:
- ✅ Dark mode (via `dark:` prefix)
- ✅ Responsive breakpoints
- ✅ Custom color palette
- ✅ Custom animations
- ✅ Accessibility features

---

## Accessibility

All components follow accessibility best practices:
- Semantic HTML elements
- ARIA attributes where needed
- Keyboard navigation support
- Focus management
- Screen reader friendly

---

## Browser Support

- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)

---

## License

MIT License - Portfolio V2 Project
