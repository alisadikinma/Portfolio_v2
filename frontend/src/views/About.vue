<template>
  <div class="min-h-screen bg-bg-deep text-fg-primary">

    <!-- ══════════════════════════════════════
         HERO — Photo + Bio + Title + Languages
         ══════════════════════════════════════ -->
    <section class="pt-16 sm:pt-20 pb-12 sm:pb-16 relative overflow-hidden">
      <div class="container-custom relative z-10">
        <div class="bezel-shell max-w-5xl mx-auto">
          <div class="bezel-core p-8 md:p-12">
            <div class="grid md:grid-cols-2 gap-12 items-center">

              <!-- Profile Photo -->
              <div class="relative group">
                <div class="bezel-shell-sm w-full max-w-sm mx-auto">
                  <div class="bezel-core-sm aspect-square overflow-hidden">
                    <img
                      v-if="heroAvatar"
                      :src="getProfilePhotoUrl(heroAvatar)"
                      :alt="heroName"
                      class="w-full h-full object-cover"
                      @error="handleImageError"
                    />
                    <div v-else class="w-full h-full bg-bg-elevated flex items-center justify-center">
                      <svg class="w-32 h-32 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bio + Title + Buttons -->
              <div>
                <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">AI Generalist Expert</span>

                <h1 class="section-heading text-4xl md:text-5xl text-fg-primary mt-4 mb-2">
                  {{ heroName }}
                </h1>

                <p class="text-xl text-gradient font-semibold mb-6">{{ heroTitle }}</p>

                <div
                  v-if="aboutSettings?.bio"
                  v-html="aboutSettings.bio"
                  class="text-fg-muted leading-relaxed mb-6 prose-dark font-light"
                ></div>
                <p v-else class="text-fg-muted leading-relaxed mb-6 font-light">
                  Crafting intelligent systems and digital experiences that bridge AI capabilities with real business outcomes.
                </p>

                <!-- Language Badges -->
                <div v-if="displayLanguages.length > 0" class="mb-6">
                  <p class="mono-label text-[10px] mb-3">Languages I Speak</p>
                  <div class="flex flex-wrap items-center gap-2">
                    <div
                      v-for="lang in displayLanguages"
                      :key="lang.code"
                      class="flex items-center gap-2 px-3 py-2 bg-white/4 rounded-full border border-border-hairline"
                    >
                      <img :src="getLangFlagImage(lang.code)" :alt="lang.name" class="w-4 h-4 object-contain rounded" />
                      <span class="text-xs font-medium text-fg-primary">{{ lang.name }}</span>
                    </div>
                  </div>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                  <button
                    @click="$router.push('/work')"
                    class="btn-gold group"
                  >
                    View My Work
                    <span class="btn-icon-island">
                      <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform duration-700 ease-spring" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
                    </span>
                  </button>
                  <a
                    :href="getWhatsAppLink()"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-glass"
                  >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    WhatsApp Me
                  </a>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 3D Globe — Phase 4C (hidden until implemented) -->

    <!-- ══════════════════════════════════════
         SKILLS & EXPERTISE
         ══════════════════════════════════════ -->
    <section class="py-12 sm:py-20">
      <div class="container-custom">
        <div class="text-center mb-6 sm:mb-10">
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Technical Stack</span>
          <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4 mb-3">Skills & Expertise</h2>
          <p class="text-fg-muted font-light">Technologies and tools I work with every day</p>
        </div>

        <div class="max-w-4xl mx-auto">
          <div class="flex flex-wrap justify-center gap-2">
            <span
              v-for="skill in displaySkills"
              :key="skill"
              class="px-4 py-2.5 rounded-full text-sm font-medium text-fg-muted bg-white/4 border border-border-hairline hover:text-accent-gold hover:border-accent-gold/20 transition-all duration-700 ease-spring cursor-default"
            >
              {{ skill }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════
         WORK EXPERIENCE TIMELINE
         ══════════════════════════════════════ -->
    <section class="py-12 sm:py-20">
      <div class="container-custom">
        <div class="text-center mb-16">
          <span class="eyebrow-tag text-accent-indigo mb-4 inline-flex">Professional Journey</span>
          <h2 class="section-heading text-3xl md:text-5xl text-fg-primary mt-4 mb-3">Work Experience</h2>
          <p v-if="totalExperienceYears > 0" class="text-2xl font-bold text-gradient">
            {{ totalExperienceYears }} Years Experience
          </p>
          <p class="text-fg-muted mt-2">Building impactful solutions across industries and technologies</p>
        </div>

        <div v-if="experienceData.length > 0" class="max-w-4xl mx-auto">
          <div class="relative">
            <!-- Gold timeline line -->
            <div class="absolute left-6 md:left-8 top-0 bottom-0 w-px" style="background: linear-gradient(to bottom, #D4A843, #5E6AD2, #06B6D4);"></div>

            <div class="space-y-10">
              <div
                v-for="(exp, index) in displayedExperience"
                :key="index"
                class="relative pl-20 md:pl-24"
              >
                <!-- Timeline dot with flag -->
                <div class="absolute left-0 w-12 h-12 md:w-16 md:h-16 rounded-xl flex items-center justify-center z-10 p-2 overflow-hidden border border-border-hairline"
                  style="background: linear-gradient(135deg, rgba(212,168,67,0.15), rgba(6,182,212,0.15));">
                  <img :src="getCountryFlag(exp.location)" :alt="exp.location || 'location'" class="w-full h-full object-contain rounded-lg" />
                </div>

                <!-- Experience card -->
                <div class="bezel-core-sm p-6 group">
                  <!-- Duration badge -->
                  <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="mono-label text-accent-gold border border-accent-gold/30 px-3 py-1 rounded-full">
                      {{ exp.start_date }} — {{ formatEndDate(exp.end_date) }}
                    </span>
                    <span class="mono-label text-fg-dim">
                      {{ formatDuration(calculateDuration(exp.start_date, exp.end_date)) }}
                    </span>
                  </div>

                  <!-- Role + Company -->
                  <h3 class="text-lg md:text-xl font-semibold text-fg-primary mb-2 group-hover:text-accent-gold transition-colors">
                    {{ exp.title || exp.position }}
                  </h3>
                  <div class="flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-accent-cyan flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <a
                      v-if="exp.company_url"
                      :href="exp.company_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-accent-cyan hover:text-accent-gold transition-colors font-medium text-sm"
                    >{{ exp.company }}</a>
                    <span v-else class="text-fg-muted font-medium text-sm">{{ exp.company }}</span>
                  </div>

                  <!-- Description (collapsible) -->
                  <div class="relative">
                    <div
                      class="overflow-hidden transition-all duration-500"
                      :class="isExpanded(index) ? 'max-h-[2000px]' : 'max-h-24'"
                    >
                      <div
                        v-html="exp.description"
                        class="text-fg-muted text-sm leading-relaxed experience-prose"
                      ></div>
                    </div>
                    <div
                      v-if="!isExpanded(index) && isDescriptionLong(exp.description)"
                      class="absolute bottom-0 left-0 right-0 h-12 pointer-events-none"
                      style="background: linear-gradient(to top, rgba(12,12,15,0.95), transparent);"
                    ></div>
                  </div>

                  <button
                    v-if="isDescriptionLong(exp.description)"
                    @click="toggleExpand(index)"
                    class="mt-3 inline-flex items-center gap-2 text-sm text-accent-gold hover:text-accent-cyan transition-colors font-medium"
                  >
                    <span>{{ isExpanded(index) ? 'Show Less' : 'See More' }}</span>
                    <svg
                      class="w-4 h-4 transition-transform duration-300"
                      :class="{ 'rotate-180': isExpanded(index) }"
                      fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Show more / less -->
            <div v-if="hasMoreExperience" class="mt-12 text-center">
              <p class="mono-label text-fg-dim mb-4">
                Showing {{ displayedExperience.length }} of {{ experienceData.length }} positions
              </p>
              <button
                @click="showAllExperience = !showAllExperience"
                class="btn-glass inline-flex items-center gap-2 group"
              >
                <span>{{ showAllExperience ? 'Show Less' : `Show All (${experienceData.length - 3} more)` }}</span>
                <svg
                  class="w-4 h-4 transition-transform duration-300"
                  :class="{ 'rotate-180': showAllExperience }"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-16">
          <p class="text-fg-dim text-lg">No work experience data available yet.</p>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════
         CERTIFICATIONS
         ══════════════════════════════════════ -->
    <section class="py-12 sm:py-20">
      <div class="container-custom">
        <div class="text-center mb-6 sm:mb-10">
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Recognition</span>
          <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4">Certifications & Awards</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 max-w-5xl mx-auto">
          <!-- Outskill Fellowship -->
          <div class="bezel-shell-sm"><div class="bezel-core-sm p-6 group">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, #D4A843, #B8922F);">
              <svg class="w-6 h-6 text-bg-deep" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
              </svg>
            </div>
            <span class="eyebrow-tag text-accent-gold text-[9px] mb-2 inline-flex">Fellowship</span>
            <h3 class="text-lg font-semibold text-fg-primary mt-2 mb-2 group-hover:text-accent-gold transition-all duration-700 ease-spring">Outskill AI Fellowship</h3>
            <p class="text-fg-muted text-sm leading-relaxed font-light">Selected among top AI practitioners in a competitive global program focused on applied artificial intelligence.</p>
          </div></div>

          <!-- Demo Day Champion -->
          <div class="bezel-shell-sm"><div class="bezel-core-sm p-6 group">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, #5E6AD2, #06B6D4);">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/>
              </svg>
            </div>
            <span class="eyebrow-tag text-accent-indigo text-[9px] mb-2 inline-flex">Champion</span>
            <h3 class="text-lg font-semibold text-fg-primary mt-2 mb-2 group-hover:text-accent-cyan transition-all duration-700 ease-spring">Demo Day Champion</h3>
            <p class="text-fg-muted text-sm leading-relaxed font-light">Awarded first place at the Outskill Demo Day, recognized for outstanding AI product demonstration and impact.</p>
          </div></div>

          <!-- Alibaba eFounders -->
          <div class="bezel-shell-sm"><div class="bezel-core-sm p-6 group">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, #06B6D4, #5E6AD2);">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
              </svg>
            </div>
            <span class="eyebrow-tag text-accent-cyan text-[9px] mb-2 inline-flex">Program</span>
            <h3 class="text-lg font-semibold text-fg-primary mt-2 mb-2 group-hover:text-accent-cyan transition-all duration-700 ease-spring">Alibaba eFounders</h3>
            <p class="text-fg-muted text-sm leading-relaxed font-light">Part of Alibaba's eFounders Fellowship, developing digital commerce expertise with leading global technology mentors.</p>
          </div></div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════
         MISSION
         ══════════════════════════════════════ -->
    <section v-if="aboutSettings?.mission" class="py-12 sm:py-20">
      <div class="container-custom">
        <div class="max-w-3xl mx-auto text-center">
          <!-- Icon -->
          <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 glow-gold" style="background: linear-gradient(135deg, rgba(212,168,67,0.2), rgba(6,182,212,0.2));">
            <svg class="w-8 h-8 text-accent-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
          </div>
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">What Drives Me</span>
          <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4 mb-8">Mission</h2>
          <blockquote class="text-xl md:text-2xl text-fg-muted leading-relaxed font-serif italic border-l-2 border-accent-gold pl-6 text-left">
            {{ aboutSettings.mission }}
          </blockquote>
          <div class="flex justify-center mt-8">
            <div class="h-px w-24" style="background: linear-gradient(to right, #D4A843, transparent);"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════
         APPROACH
         ══════════════════════════════════════ -->
    <section v-if="false && aboutSettings?.approach" class="py-12 sm:py-20">
      <div class="container-custom">
        <div class="max-w-5xl mx-auto">
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <span class="eyebrow-tag text-accent-cyan mb-4 inline-flex">How I Deliver</span>
              <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4 mb-4">My Approach</h2>
              <div class="flex gap-2 mt-4">
                <div class="h-0.5 w-12" style="background: linear-gradient(to right, #D4A843, transparent);"></div>
                <div class="h-0.5 w-8" style="background: linear-gradient(to right, #5E6AD2, transparent);"></div>
                <div class="h-0.5 w-4" style="background: linear-gradient(to right, #06B6D4, transparent);"></div>
              </div>
            </div>
            <div class="bezel-shell-sm"><div class="bezel-core-sm p-8">
              <p class="text-lg text-fg-muted leading-relaxed font-light">{{ aboutSettings.approach }}</p>
            </div></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════
         COLLABORATION MODES
         ══════════════════════════════════════ -->
    <section v-if="getEnhancedModes.length > 0" class="py-12 sm:py-20">
      <div class="container-custom">
        <div class="text-center mb-12">
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Flexible Partnership</span>
          <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4 mb-4">Let's Build Together</h2>
          <p class="text-fg-muted max-w-2xl mx-auto">
            Collaboration models designed for
            <span class="text-accent-gold font-semibold">long-term impact</span>
            and
            <span class="text-accent-cyan font-semibold">real business growth</span>
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 max-w-6xl mx-auto">
          <div
            v-for="(mode, index) in getEnhancedModes"
            :key="index"
            @click="scrollToCTA"
            class="bezel-shell-sm cursor-pointer"
          ><div class="bezel-core-sm p-8 group relative overflow-hidden">
            <!-- Index badge -->
            <div
              class="absolute -top-3 -left-3 w-10 h-10 rounded-xl flex items-center justify-center text-bg-deep font-bold text-sm shadow-lg glow-gold"
              style="background: linear-gradient(135deg, #D4A843, #B8922F);"
            >
              {{ index + 1 }}
            </div>

            <div class="mt-4">
              <h3 class="text-xl font-semibold text-fg-primary mb-3 group-hover:text-accent-gold transition-colors">
                {{ mode.mode }}
              </h3>
              <p class="text-fg-muted text-sm leading-relaxed mb-4">{{ mode.description }}</p>
              <div class="pt-4 border-t border-border-hairline">
                <p class="text-sm text-accent-cyan leading-relaxed">{{ mode.value }}</p>
              </div>
            </div>

            <div class="mt-5 flex items-center gap-2 text-accent-gold text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              <span>Let's discuss this</span>
              <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-700 ease-spring" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </div>
          </div></div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════
         CTA SECTION
         ══════════════════════════════════════ -->
    <CTASection
      ref="ctaSectionRef"
      v-if="showCTASection"
      heading="Ready for more than just a consultant?"
      description="I'm invested in seeing your business win. Let's discuss how I can <strong>accelerate your next milestone</strong> — reach out now for candid, actionable insight."
      whatsapp-message="Hi! I saw your portfolio and I'd like to discuss a project with you."
      :social-links="aboutSettings?.social_links"
      :show-social-links="true"
    />

  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import CTASection from '@/components/CTASection.vue'
import { usePageSections } from '@/composables/usePageSections'
import { useAboutSettings } from '@/composables/useAboutSettings'
import { useSettings } from '@/composables/useSettings'

// Language flag assets
import idFlag from '@/assets/language/ID.png'
import gbFlag from '@/assets/language/GB.png'
import cnFlag from '@/assets/language/CN.png'
import sgFlag from '@/assets/language/SG.png'

// Organization icon assets
import universityIcon from '@/assets/organization/university.png'
import supplyChainIcon from '@/assets/organization/supply-chain-management.png'
import cyberSecurityIcon from '@/assets/organization/cyber-security.png'
import maritimeIcon from '@/assets/organization/maritime.png'
import startupIcon from '@/assets/organization/startup.png'
import manufacturingIcon from '@/assets/organization/manufacturing.png'

// ── Composables ────────────────────────────────────────
const { aboutSettings, loading, heroName, heroTitle, heroBio, heroAvatar, heroSkills } = useAboutSettings()
const { sections, fetchActiveSections } = usePageSections()
const { settings, fetchSettings, getSettingValue } = useSettings()

fetchActiveSections('about')
fetchSettings()

// ── Work Experience State ──────────────────────────────
const experienceData = ref([])
const expandedStates = ref({})
const showAllExperience = ref(false)

const displayedExperience = computed(() =>
  showAllExperience.value ? experienceData.value : experienceData.value.slice(0, 3)
)

const hasMoreExperience = computed(() => experienceData.value.length > 3)

// ── Skills ─────────────────────────────────────────────
const defaultSkills = [
  'Vue.js', 'React', 'TypeScript', 'Tailwind CSS', 'HTML5', 'CSS3',
  'Node.js', 'Express', 'Laravel', 'PHP', 'MySQL', 'MongoDB',
  'Git', 'Docker', 'AWS', 'CI/CD', 'Linux', 'Nginx',
  'AI/ML', 'Python', 'OpenAI API', 'n8n', 'Zapier'
]

const displaySkills = computed(() => {
  const skills = aboutSettings.value?.skills
  if (Array.isArray(skills) && skills.length > 0) return skills
  return defaultSkills
})

// ── Duration helpers ───────────────────────────────────
const formatEndDate = (endDate) => {
  if (!endDate || endDate.trim() === '') return 'Current'
  return endDate
}

const monthToNumber = (monthName) => {
  const months = {
    jan: 0, january: 0, feb: 1, february: 1, mar: 2, march: 2,
    apr: 3, april: 3, may: 4, jun: 5, june: 5, jul: 6, july: 6,
    aug: 7, august: 7, sep: 8, september: 8, oct: 9, october: 9,
    nov: 10, november: 10, dec: 11, december: 11
  }
  return months[monthName.toLowerCase()] ?? 0
}

const parseExperienceDate = (dateStr) => {
  if (!dateStr || dateStr.trim() === '') return new Date()
  if (/present|current/i.test(dateStr)) return new Date()
  const parts = dateStr.trim().split(' ')
  if (parts.length === 2) return new Date(parseInt(parts[1]), monthToNumber(parts[0]))
  return new Date()
}

const calculateDuration = (startDate, endDate) => {
  const start = parseExperienceDate(startDate)
  const end = parseExperienceDate(endDate)
  return (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth())
}

const formatDuration = (months) => {
  const years = Math.floor(months / 12)
  const rem = months % 12
  if (years === 0) return `${rem} ${rem === 1 ? 'month' : 'months'}`
  if (rem === 0) return `${years} ${years === 1 ? 'year' : 'years'}`
  return `${years}y ${rem}m`
}

// ── Total years computed ───────────────────────────────
const totalExperienceYears = computed(() => {
  if (!experienceData.value.length) return 0
  const total = experienceData.value.reduce((sum, exp) => sum + calculateDuration(exp.start_date, exp.end_date), 0)
  return Math.floor(total / 12)
})

// ── Country flags ──────────────────────────────────────
const getCountryFlag = (location) => {
  if (!location) return idFlag
  const loc = location.toLowerCase()
  if (loc.includes('singapore')) return sgFlag
  if (loc.includes('china')) return cnFlag
  if (loc.includes('uk') || loc.includes('united kingdom') || loc.includes('london')) return gbFlag
  return idFlag
}

// ── Organization icons ─────────────────────────────────
const getOrganizationIcon = (company) => {
  if (!company) return null
  const c = company.toLowerCase()
  if (c.includes('exsys') || c.includes('ex sys')) return universityIcon
  if (c.includes('dhl') || c.includes('supply chain')) return supplyChainIcon
  if (c.includes('thales')) return cyberSecurityIcon
  if (c.includes('mpa') || c.includes('maritime')) return maritimeIcon
  if (c.includes('marlin')) return startupIcon
  if (c.includes('satnusa') || c.includes('sat nusa') || c.includes('nusapersada')) return manufacturingIcon
  return null
}

// ── Load settings on mount ─────────────────────────────
onMounted(async () => {
  await fetchSettings(true)
})

// ── Parse experience data from settings ───────────────
watch(
  () => settings.value,
  (settingsArray) => {
    if (!settingsArray?.length) return
    const experienceSetting = settingsArray.find(s => s.key === 'experience')
    if (!experienceSetting) { experienceData.value = []; return }
    try {
      let parsed = Array.isArray(experienceSetting.value)
        ? experienceSetting.value
        : JSON.parse(experienceSetting.value)
      parsed.sort((a, b) => parseExperienceDate(b.start_date) - parseExperienceDate(a.start_date))
      experienceData.value = parsed
    } catch {
      experienceData.value = []
    }
  },
  { immediate: true, deep: true }
)

// ── Expand / collapse experience ───────────────────────
const toggleExpand = (index) => { expandedStates.value[index] = !expandedStates.value[index] }
const isExpanded = (index) => !!expandedStates.value[index]
const isDescriptionLong = (description) => {
  if (!description) return false
  return description.replace(/<[^>]*>/g, '').length > 200
}

// ── CTA section visibility ─────────────────────────────
const showCTASection = computed(() => {
  const section = sections.value?.find(s => s.section_type === 'cta')
  return section ? section.is_active : false
})

const ctaSectionRef = ref(null)
const scrollToCTA = () => {
  ctaSectionRef.value?.$el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Collaboration modes with value propositions ────────
const getEnhancedModes = computed(() => {
  if (!aboutSettings.value?.collaboration_modes) return []
  const valuePropositions = {
    'Project-Based': 'Clear deliverables, fast iterations, and full accountability. Perfect for teams that want results — not just reports.',
    'Retainer': 'Continuous optimization with dedicated leadership — more than just hours, it is a partnership for measurable progress every month.',
    'Consulting': 'Strategic guidance grounded in execution, not theory — so you get impact and momentum, not just a blueprint.'
  }
  return aboutSettings.value.collaboration_modes.map(mode => ({
    ...mode,
    value: valuePropositions[mode.mode] || 'Tailored approach designed for your specific business needs and growth objectives.'
  }))
})

// ── Language display ───────────────────────────────────
const displayLanguages = computed(() => {
  const langs = aboutSettings.value?.languages
  if (!Array.isArray(langs) || !langs.length) return []
  const languageMap = {
    bahasa: { code: 'id', name: 'Bahasa' },
    indonesia: { code: 'id', name: 'Indonesia' },
    indonesian: { code: 'id', name: 'Indonesian' },
    english: { code: 'en', name: 'English' },
    mandarin: { code: 'zh', name: 'Mandarin' },
    chinese: { code: 'zh', name: 'Chinese' }
  }
  return langs.map(lang => {
    if (typeof lang === 'object' && lang.code && lang.name) return lang
    if (typeof lang === 'string') {
      const mapped = languageMap[lang.toLowerCase().trim()]
      if (mapped) return mapped
      return { code: lang.substring(0, 2).toLowerCase(), name: lang }
    }
    return { code: 'xx', name: String(lang) }
  })
})

// ── Helpers ────────────────────────────────────────────
const getProfilePhotoUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/uploads/') || path.startsWith('/storage/'))
    return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/uploads/' + path
}

const handleImageError = (event) => {
  event.target.style.display = 'none'
}

const getLangFlagImage = (code) => {
  const map = { id: idFlag, en: gbFlag, zh: cnFlag, sg: sgFlag }
  return map[code] || idFlag
}

const getWhatsAppLink = () => {
  const phone = getSettingValue('contact_phone') || '+6281380163758'
  const msg = "Hi! I saw your portfolio and I'd like to discuss a project with you."
  return `https://wa.me/${phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(msg)}`
}
</script>

<style scoped>
/* Experience prose inside dark glass cards */
.experience-prose :deep(p) {
  margin-bottom: 0.5rem;
}

.experience-prose :deep(ul),
.experience-prose :deep(ol) {
  margin-top: 0.375rem;
  margin-bottom: 0.375rem;
  padding-left: 1.25rem;
}

.experience-prose :deep(li) {
  margin-bottom: 0.2rem;
}

/* Bio prose on dark */
.prose-dark :deep(p) {
  margin-bottom: 0.75rem;
  color: #8A8F98;
}

.prose-dark :deep(strong) {
  color: #EDEDEF;
}

.prose-dark :deep(a) {
  color: #06B6D4;
}
</style>
