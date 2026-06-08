<script setup>
/**
 * Responsive image component consuming the backend's image_variants pipeline
 * (Phase C: backend/app/Services/ImageVariantService.php).
 *
 * Renders <picture><source srcset> + <img> with:
 *   - WebP variants (320/640/1024/1920w) when `variants` prop is supplied
 *   - LQIP base64 blur placeholder until full image loads (8px blur fade-in)
 *   - loading="lazy" + decoding="async" by default (use `eager` for hero)
 *   - `aspectRatio` prop pre-reserves layout space (zero CLS)
 *   - graceful fallback to plain <img src> when variants are null/empty
 *
 * Backend may return `image_variants` as null until the queued
 * GenerateImageVariantsJob populates them — this component renders correctly
 * either way.
 */
import { computed, ref } from 'vue'

const props = defineProps({
  src: { type: String, required: true },
  variants: { type: [Object, null], default: null },
  alt: { type: String, default: '' },
  /**
   * `sizes` attribute for the responsive image. Tells the browser how wide
   * the image will render at each breakpoint so it picks the right srcset
   * candidate. Examples:
   *   - hero/full-bleed: '100vw'
   *   - 2-column grid card: '(max-width: 640px) 100vw, 50vw'
   *   - 3-column grid card: '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw'
   */
  sizes: { type: String, default: '100vw' },
  /** Eager-load (above-the-fold). Defaults to lazy. */
  eager: { type: Boolean, default: false },
  /** 'high' for hero, 'low' for off-screen, 'auto' otherwise. */
  fetchpriority: { type: String, default: 'auto' },
  /** CSS aspect-ratio (e.g. "16/9", "4/5", "1/1"). Prevents CLS. */
  aspectRatio: { type: String, default: null },
  /** Object-fit value for the inner <img>. Defaults to cover. */
  objectFit: { type: String, default: 'cover' },
})

const loaded = ref(false)

const hasVariants = computed(() => {
  if (!props.variants) return false
  // Need at least one width key (320w/640w/1024w/1920w) to be useful
  return ['320w', '640w', '1024w', '1920w'].some((k) => props.variants[k])
})

const srcsetWebp = computed(() => {
  if (!hasVariants.value) return null
  const widths = ['320w', '640w', '1024w', '1920w']
  return widths
    .filter((k) => props.variants[k])
    .map((k) => `${props.variants[k]} ${k}`)
    .join(', ')
})

const lqipStyle = computed(() => {
  const lqip = props.variants?.lqip
  if (!lqip || loaded.value) return null
  // Inline base64 dataURI as background; blur until <img> reports load
  return {
    backgroundImage: `url("${lqip}")`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    filter: 'blur(8px)',
  }
})

const wrapperStyle = computed(() => {
  const style = {}
  if (props.aspectRatio) {
    style.aspectRatio = props.aspectRatio.replace('/', ' / ')
  }
  return style
})

const imgStyle = computed(() => ({
  objectFit: props.objectFit,
  // Smooth fade-in once full image loaded (LQIP visible until then)
  opacity: loaded.value ? 1 : 0,
  transition: 'opacity 280ms ease-out',
}))

function onLoad() {
  loaded.value = true
}

function onError() {
  // If anything goes wrong with the picture/source path, mark as "loaded"
  // so we don't sit on a blurred placeholder forever.
  loaded.value = true
}
</script>

<template>
  <div class="base-image-wrapper" :style="wrapperStyle">
    <div v-if="lqipStyle" class="base-image-lqip" :style="lqipStyle" aria-hidden="true" />

    <picture>
      <source
        v-if="srcsetWebp"
        type="image/webp"
        :srcset="srcsetWebp"
        :sizes="sizes"
      />
      <img
        :src="src"
        :alt="alt"
        :loading="eager ? 'eager' : 'lazy'"
        :decoding="eager ? 'sync' : 'async'"
        :fetchpriority="fetchpriority"
        :style="imgStyle"
        @load="onLoad"
        @error="onError"
      />
    </picture>
  </div>
</template>

<style scoped>
.base-image-wrapper {
  position: relative;
  overflow: hidden;
  width: 100%;
  height: 100%;
  /* Dark placeholder so lazy-loading images blend with the dark theme instead
     of flashing a light box before the <img> fades in (opacity 0 → 1 on load). */
  background: var(--bg-elevated, #0c0c0f);
}

.base-image-lqip {
  position: absolute;
  inset: 0;
  z-index: 0;
  /* Slightly oversized so the blur edges don't show through */
  transform: scale(1.05);
}

picture,
picture img {
  position: relative;
  z-index: 1;
  display: block;
  width: 100%;
  height: 100%;
}

@media (prefers-reduced-motion: reduce) {
  picture img {
    transition: none !important;
  }
}
</style>
