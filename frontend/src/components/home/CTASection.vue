<template>
  <section ref="sectionRef" class="py-6 sm:py-12 md:py-20">
    <div class="container-custom">
      <div class="max-w-3xl mx-auto text-center reveal" :class="{ 'is-visible': isVisible }">
        <!-- Eyebrow -->
        <span class="eyebrow-tag text-accent-gold mb-3 sm:mb-6 inline-flex">Let's Connect</span>

        <h2 class="section-heading text-2xl sm:text-4xl md:text-5xl lg:text-6xl text-gradient mb-3 sm:mb-6 mt-2 sm:mt-4">
          Let's Build Something Amazing
        </h2>
        <p class="text-fg-muted text-sm sm:text-lg md:text-xl font-light max-w-xl mx-auto mb-6 sm:mb-10 leading-relaxed">
          Looking for an AI expert to transform your business? Let's discuss how AI can accelerate your goals.
        </p>

        <!-- Button-in-Button CTAs -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 mb-6 sm:mb-12">
          <a
            href="https://wa.me/6281234567890"
            target="_blank"
            rel="noopener noreferrer"
            class="btn-gold text-sm sm:text-base"
          >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            WhatsApp Me
            <span class="btn-icon-island">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
            </span>
          </a>
          <router-link to="/contact" class="btn-glass text-sm sm:text-base">
            Get in Touch
            <span class="btn-icon-island">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
            </span>
          </router-link>
        </div>

      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const email = ref('')
const subscribeMessage = ref('')
const subscribeSuccess = ref(false)
const isVisible = ref(false)
const sectionRef = ref(null)
let observer = null

function handleSubscribe() {
  subscribeMessage.value = 'Newsletter coming soon! Thanks for your interest.'
  subscribeSuccess.value = true
  email.value = ''
}

onMounted(() => {
  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        isVisible.value = true
        observer.disconnect()
      }
    },
    { threshold: 0.1 }
  )
  if (sectionRef.value) observer.observe(sectionRef.value)
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>
