<template>
  <section
    class="join-build relative w-full overflow-hidden bg-[var(--bg-deep,#050506)] px-6 py-24 lg:px-20 lg:py-32"
    aria-label="Join the build"
  >
    <div class="join-glow pointer-events-none absolute inset-0" aria-hidden="true"></div>

    <div class="relative z-10 mx-auto grid w-full max-w-7xl grid-cols-1 gap-14 lg:grid-cols-12 lg:gap-16">
      <!-- Left: invitation + socials -->
      <div class="lg:col-span-6">
        <p
          class="mb-3 text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-gold,#D4A843)]"
          style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
        >
          join the build
        </p>
        <h2
          class="mb-5 text-3xl font-bold leading-tight text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-5xl"
          style="font-family: 'Space Grotesk', sans-serif;"
        >
          I build in public.
          <span class="text-[var(--accent-gold,#D4A843)]">Build with me.</span>
        </h2>
        <p
          class="mb-8 max-w-xl text-base leading-relaxed text-[var(--fg-muted,#8A8F98)] lg:text-lg"
          style="font-family: 'Inter', sans-serif; font-weight: 300;"
        >
          Follow <span class="text-[var(--fg-primary,#EDEDEF)]">@alisadikinma</span> for what I'm
          shipping — AI agents, vibe coding, and generative video — in real time.
        </p>

        <!-- Social follow row -->
        <ul class="mb-10 flex flex-wrap gap-3" aria-label="Follow on social media">
          <li v-for="s in socials" :key="s.platform">
            <a
              :href="s.url"
              target="_blank"
              rel="noopener"
              class="social-pill group inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-4 py-2.5 text-sm text-[var(--fg-primary,#EDEDEF)] transition-colors duration-200 hover:border-[var(--accent-gold,#D4A843)] hover:bg-white/5"
              style="font-family: 'Inter', sans-serif;"
            >
              <span class="text-base" aria-hidden="true">{{ s.icon }}</span>
              <span class="capitalize">{{ s.platform }}</span>
            </a>
          </li>
        </ul>

        <!-- Soft secondary CTA: WhatsApp -->
        <a
          :href="whatsappUrl"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-2 text-sm text-[var(--fg-muted,#8A8F98)] transition-colors duration-200 hover:text-[var(--accent-gold,#D4A843)]"
          style="font-family: 'Inter', sans-serif;"
        >
          💬 Got an AI problem? Let's talk on WhatsApp →
        </a>
      </div>

      <!-- Right: newsletter signup -->
      <div class="lg:col-span-6">
        <div class="rounded-2xl border border-white/10 bg-[var(--bg-elevated,#0C0C0F)] p-7 lg:p-9">
          <h3
            class="mb-2 text-xl font-semibold text-[var(--fg-primary,#EDEDEF)] lg:text-2xl"
            style="font-family: 'Space Grotesk', sans-serif;"
          >
            The Friday build log
          </h3>
          <p
            class="mb-6 text-sm leading-relaxed text-[var(--fg-muted,#8A8F98)]"
            style="font-family: 'Inter', sans-serif; font-weight: 300;"
          >
            One email a week — what I shipped, what broke, and what I'm learning. No spam.
          </p>

          <!-- Success state -->
          <div
            v-if="success"
            class="rounded-xl border border-[var(--accent-gold,#D4A843)]/40 bg-[var(--accent-gold,#D4A843)]/10 p-5 text-sm text-[var(--fg-primary,#EDEDEF)]"
            style="font-family: 'Inter', sans-serif;"
          >
            ✓ You're in. Check your inbox this Friday.
          </div>

          <!-- Form -->
          <form v-else class="space-y-4" @submit.prevent="onSubmit">
            <div>
              <label for="jtb-name" class="sr-only">Name</label>
              <input
                id="jtb-name"
                v-model="name"
                type="text"
                required
                placeholder="Your name"
                class="form-field"
              />
            </div>
            <div>
              <label for="jtb-email" class="sr-only">Email</label>
              <input
                id="jtb-email"
                v-model="email"
                type="email"
                required
                placeholder="you@example.com"
                class="form-field"
              />
            </div>
            <div>
              <label for="jtb-wa" class="sr-only">WhatsApp number</label>
              <input
                id="jtb-wa"
                v-model="whatsapp"
                type="tel"
                required
                pattern="^\+[1-9]\d{6,14}$"
                placeholder="+62 8xx xxxx xxxx"
                class="form-field"
                @blur="validateWa"
              />
              <p class="mt-1.5 text-[0.72rem] text-[var(--fg-muted,#8A8F98)]" style="font-family: 'Inter', sans-serif;">
                International format, starts with + (e.g. +6281234567890).
              </p>
            </div>

            <p
              v-if="localError || error"
              class="text-sm text-red-400"
              style="font-family: 'Inter', sans-serif;"
            >
              {{ localError || error }}
            </p>

            <button
              type="submit"
              :disabled="loading"
              class="w-full rounded-[10px] bg-[var(--accent-gold,#D4A843)] px-6 py-3.5 text-sm font-medium text-[#0A0A0C] transition-transform duration-200 hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
              style="font-family: 'Inter', sans-serif;"
            >
              {{ loading ? 'Joining…' : 'Join the build log' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useNewsletter } from '@/composables/useNewsletter'
import { useAboutSettings } from '@/composables/useAboutSettings'
import { useSiteSettings } from '@/composables/useSiteSettings'

const { subscribe, loading, error, success } = useNewsletter()
const { aboutSettings } = useAboutSettings()
const { siteSettings, fetchSiteSettings } = useSiteSettings()

onMounted(() => { fetchSiteSettings() })

// Form state
const name = ref('')
const email = ref('')
const whatsapp = ref('')
const localError = ref(null)

const WA_RE = /^\+[1-9]\d{6,14}$/
function validateWa() {
  localError.value = whatsapp.value && !WA_RE.test(whatsapp.value)
    ? 'Use international format, starting with + (e.g. +6281234567890).'
    : null
}

async function onSubmit() {
  validateWa()
  if (localError.value) return
  await subscribe({
    name: name.value,
    email: email.value,
    whatsappNumber: whatsapp.value,
    source: 'homepage_join',
  })
}

// Social follow links — live from settings.about.social_links, with a curated
// @alisadikinma fallback so the row is never empty (handles are stable identity).
const ICONS = { linkedin: 'in', instagram: '◉', tiktok: '♪', youtube: '▶', github: '⌘' }
const FALLBACK_SOCIALS = [
  { platform: 'linkedin', url: 'https://www.linkedin.com/in/alisadikinma/' },
  { platform: 'instagram', url: 'https://www.instagram.com/alisadikinma' },
  { platform: 'tiktok', url: 'https://www.tiktok.com/@alisadikinma' },
  { platform: 'youtube', url: 'https://www.youtube.com/@alisadikinma' },
]

const socials = computed(() => {
  const live = aboutSettings.value?.social_links
  const rows = Array.isArray(live) && live.length
    ? live
        .filter((l) => l && l.url && l.platform)
        .map((l) => ({ platform: String(l.platform).toLowerCase(), url: l.url }))
    : FALLBACK_SOCIALS
  return rows.map((r) => ({ ...r, icon: ICONS[r.platform] ?? '↗' }))
})

// WhatsApp soft CTA — from site contact phone; safe fallback to the handle's bio link.
const whatsappUrl = computed(() => {
  const phone = siteSettings.value?.contact_phone || ''
  const digits = phone.replace(/\D/g, '')
  if (!digits) return 'https://wa.me/'
  const normalized = digits.startsWith('0') ? `62${digits.slice(1)}` : digits
  return `https://wa.me/${normalized}`
})
</script>

<style scoped>
.join-glow {
  background:
    radial-gradient(55% 60% at 80% 25%, rgba(212, 168, 67, 0.12), transparent 70%),
    radial-gradient(45% 50% at 15% 80%, rgba(6, 182, 212, 0.07), transparent 70%);
}
.form-field {
  width: 100%;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: var(--bg-deep, #050506);
  padding: 0.75rem 1rem;
  font-family: 'Inter', sans-serif;
  font-size: 0.95rem;
  color: var(--fg-primary, #EDEDEF);
  transition: border-color 180ms ease-out;
}
.form-field::placeholder { color: var(--fg-muted, #8A8F98); }
.form-field:focus {
  outline: none;
  border-color: var(--accent-gold, #D4A843);
}
.social-pill { transition: transform 180ms ease-out, border-color 180ms ease-out; }
.social-pill:hover { transform: translateY(-1px); }
@media (prefers-reduced-motion: reduce) {
  .social-pill, .form-field { transition: none; }
  .social-pill:hover { transform: none; }
}
</style>
