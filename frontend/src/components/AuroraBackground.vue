<template>
  <div
    ref="auroraRef"
    class="fixed inset-0 -z-10 overflow-hidden pointer-events-none"
    aria-hidden="true"
  >
    <!-- Gold Orb — top left -->
    <div
      class="aurora-orb aurora-orb--gold"
      :style="{ transform: `translate(${offsets.x * 0.3}px, ${offsets.y * 0.3}px)` }"
    />
    <!-- Cyan Orb — middle right -->
    <div
      class="aurora-orb aurora-orb--cyan"
      :style="{ transform: `translate(${offsets.x * -0.2}px, ${offsets.y * 0.25}px)` }"
    />
    <!-- Indigo Orb — bottom center -->
    <div
      class="aurora-orb aurora-orb--indigo"
      :style="{ transform: `translate(${offsets.x * 0.15}px, ${offsets.y * -0.2}px)` }"
    />
    <!-- Subtle emerald accent — deep bottom -->
    <div
      class="aurora-orb aurora-orb--emerald"
      :style="{ transform: `translate(${offsets.x * -0.1}px, ${offsets.y * 0.15}px)` }"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'

const auroraRef = ref(null)
const offsets = reactive({ x: 0, y: 0 })

let animationFrame = null
const target = { x: 0, y: 0 }
const lerp = 0.06

const prefersReducedMotion = typeof window !== 'undefined'
  ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
  : false

function onMouseMove(e) {
  if (prefersReducedMotion) return
  const cx = window.innerWidth / 2
  const cy = window.innerHeight / 2
  target.x = ((e.clientX - cx) / cx) * 25
  target.y = ((e.clientY - cy) / cy) * 25
}

function animate() {
  if (prefersReducedMotion) return
  offsets.x += (target.x - offsets.x) * lerp
  offsets.y += (target.y - offsets.y) * lerp
  animationFrame = requestAnimationFrame(animate)
}

onMounted(() => {
  if (!prefersReducedMotion) {
    window.addEventListener('mousemove', onMouseMove, { passive: true })
    animationFrame = requestAnimationFrame(animate)
  }
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onMouseMove)
  if (animationFrame) {
    cancelAnimationFrame(animationFrame)
  }
})
</script>

<style scoped>
.aurora-orb {
  position: absolute;
  border-radius: 9999px;
  filter: blur(140px);
  opacity: 0.06;
  will-change: transform;
}

.aurora-orb--gold {
  width: 600px;
  height: 600px;
  top: -15%;
  left: -8%;
  background: radial-gradient(circle, #D4A843 0%, transparent 70%);
  animation: auroraFloat 22s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}

.aurora-orb--cyan {
  width: 500px;
  height: 500px;
  top: 35%;
  right: -12%;
  background: radial-gradient(circle, #06B6D4 0%, transparent 70%);
  animation: auroraFloat 28s cubic-bezier(0.45, 0, 0.55, 1) infinite reverse;
}

.aurora-orb--indigo {
  width: 450px;
  height: 450px;
  bottom: -18%;
  left: 25%;
  background: radial-gradient(circle, #5E6AD2 0%, transparent 70%);
  animation: auroraFloat 32s cubic-bezier(0.45, 0, 0.55, 1) infinite;
  animation-delay: -12s;
}

.aurora-orb--emerald {
  width: 350px;
  height: 350px;
  bottom: 5%;
  right: 15%;
  background: radial-gradient(circle, #10B981 0%, transparent 70%);
  opacity: 0.03;
  animation: auroraFloat 26s cubic-bezier(0.45, 0, 0.55, 1) infinite;
  animation-delay: -6s;
}

@keyframes auroraFloat {
  0%, 100% { transform: translate(0, 0) scale(1); }
  25% { transform: translate(60px, -40px) scale(1.05); }
  50% { transform: translate(-30px, 50px) scale(0.95); }
  75% { transform: translate(40px, 20px) scale(1.02); }
}

@media (prefers-reduced-motion: reduce) {
  .aurora-orb {
    animation: none !important;
  }
}
</style>
