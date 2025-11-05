<template>
  <div>
    <!-- Introduction Section -->
    <section class="section bg-white dark:bg-neutral-800 pt-20">
      <div class="container-custom">
        <div class="max-w-4xl mx-auto">
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="aspect-square bg-neutral-200 dark:bg-neutral-700 rounded-2xl overflow-hidden">
              <img
                v-if="aboutSettings?.profile_photo"
                :src="getProfilePhotoUrl(aboutSettings.profile_photo)"
                :alt="aboutSettings.name || 'Profile Image'"
                class="w-full h-full object-cover"
                @error="handleImageError"
              />
              <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-32 h-32 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            </div>
            <div>
              <h2 class="text-3xl md:text-4xl font-display font-bold mb-6">
                Hi, I'm {{ aboutSettings?.name || '[Your Name]' }}
              </h2>
              <div v-if="aboutSettings?.bio" v-html="aboutSettings.bio" class="prose dark:prose-invert text-neutral-600 dark:text-neutral-400 mb-6 max-w-none"></div>
              <div v-else>
                <p class="text-neutral-600 dark:text-neutral-400 mb-4">
                  I'm a full-stack developer with over 5 years of experience building web applications and digital solutions.
                  I specialize in modern JavaScript frameworks, cloud architecture, and creating seamless user experiences.
                </p>
                <p class="text-neutral-600 dark:text-neutral-400 mb-6">
                  My passion lies in solving complex problems with elegant solutions and continuously learning new technologies
                  to stay at the forefront of web development.
                </p>
              </div>

              <!-- Action Buttons -->
              <div class="flex flex-col sm:flex-row gap-4 mb-6">
                <!-- View My Work Button -->
                <button
                  @click="$router.push('/projects')"
                  class="group inline-flex items-center justify-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-full transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105"
                >
                  <span>View My Work</span>
                  <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                  </svg>
                </button>

                <!-- WhatsApp Me Button -->
                <a
                  :href="getWhatsAppLink()"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/30 text-green-700 dark:text-green-400 font-semibold rounded-full transition-all duration-300 border-2 border-green-200 dark:border-green-700 hover:border-green-300 dark:hover:border-green-600 hover:scale-105"
                >
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                  </svg>
                  <span>WhatsApp Me</span>
                </a>
              </div>

              <!-- Languages I Speak Section -->
              <div v-if="displayLanguages.length > 0" class="mb-6">
                <p class="text-xs uppercase tracking-wider text-neutral-500 dark:text-neutral-400 font-semibold mb-3">
                  Languages I Speak
                </p>
                <div class="flex items-center gap-4">
                  <div 
                    v-for="lang in displayLanguages" 
                    :key="lang.code"
                    class="flex items-center gap-2 px-3 py-2 bg-neutral-50 dark:bg-neutral-700/50 rounded-lg border border-neutral-200 dark:border-neutral-600"
                  >
                    <img :src="getLangFlagImage(lang.code)" :alt="lang.name" class="w-6 h-6 object-contain rounded" />
                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ lang.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="h-10"></div>

    <!-- Mission Section -->
    <section v-if="aboutSettings?.mission" class="relative py-20 bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute w-96 h-96 bg-white rounded-full blur-3xl top-0 -left-20 animate-blob"></div>
        <div class="absolute w-96 h-96 bg-white rounded-full blur-3xl top-0 -right-20 animate-blob animation-delay-2000"></div>
        <div class="absolute w-96 h-96 bg-white rounded-full blur-3xl -bottom-20 left-1/2 animate-blob animation-delay-4000"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-4xl mx-auto text-center">
          <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
            </div>
          </div>

          <h2 class="text-4xl md:text-5xl font-display font-bold text-white mb-6">Our Mission</h2>
          <p class="text-xl md:text-2xl text-white/95 leading-relaxed font-medium">
            {{ aboutSettings.mission }}
          </p>

          <div class="flex justify-center mt-8">
            <div class="w-24 h-1 bg-white/30 rounded-full"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- What I Do Section -->
    <section v-if="aboutSettings?.what_i_do && aboutSettings.what_i_do.length" class="py-20 bg-neutral-50 dark:bg-neutral-900">
      <div class="container-custom">
        <div class="text-center mb-16">
          <p class="text-primary-600 dark:text-primary-400 font-semibold mb-3 uppercase tracking-wider text-sm">
            Services & Expertise
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold mb-4 text-neutral-900 dark:text-white">
            What I Do
          </h2>
          <p class="text-xl text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Transform your business with cutting-edge solutions
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
          <div
            v-for="(service, index) in aboutSettings.what_i_do"
            :key="index"
            class="group relative bg-white dark:bg-neutral-800 rounded-2xl p-8 hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-neutral-200 dark:border-neutral-700"
          >
            <div class="absolute inset-0 bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-primary-900/10 dark:to-secondary-900/10 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>

            <div class="relative z-10">
              <div class="w-20 h-20 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform shadow-xl">
                <svg v-if="service.icon === '🤖' || service.title.includes('Automation')" class="w-11 h-11 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2a2 2 0 012 2v1h4a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h4V4a2 2 0 012-2zm0 5a1 1 0 00-1 1v2a1 1 0 102 0V8a1 1 0 00-1-1zm-3 4a1 1 0 100 2h6a1 1 0 100-2H9z"/>
                </svg>
                
                <svg v-else-if="service.icon === '💻' || service.title.includes('Development')" class="w-11 h-11 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                
                <svg v-else class="w-11 h-11 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>

              <h3 class="text-2xl font-bold text-neutral-900 dark:text-white mb-4 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ service.title }}
              </h3>

              <p class="text-neutral-600 dark:text-neutral-400 leading-relaxed">
                {{ service.description }}
              </p>
            </div>

            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-500 via-secondary-500 to-accent-500 rounded-b-2xl transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Approach Section -->
    <section v-if="aboutSettings?.approach" class="relative py-20 bg-white dark:bg-neutral-800 overflow-hidden">
      <div class="absolute inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 40px 40px;"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-5xl mx-auto">
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <div class="inline-flex items-center gap-3 mb-6">
                <div class="w-16 h-16 bg-gradient-to-br from-accent-500 to-secondary-500 rounded-2xl flex items-center justify-center">
                  <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <h2 class="text-4xl md:text-5xl font-display font-bold mb-6 text-neutral-900 dark:text-white">
                My Approach
              </h2>
              <p class="text-sm uppercase tracking-wider text-primary-600 dark:text-primary-400 font-semibold">
                How I deliver results
              </p>
            </div>

            <div>
              <p class="text-lg md:text-xl text-neutral-600 dark:text-neutral-300 leading-relaxed">
                {{ aboutSettings.approach }}
              </p>

              <div class="flex gap-2 mt-6">
                <div class="w-12 h-1 bg-gradient-to-r from-primary-500 to-transparent rounded-full"></div>
                <div class="w-8 h-1 bg-gradient-to-r from-secondary-500 to-transparent rounded-full"></div>
                <div class="w-4 h-1 bg-gradient-to-r from-accent-500 to-transparent rounded-full"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Collaboration Modes Section -->
    <section v-if="aboutSettings?.collaboration_modes && aboutSettings.collaboration_modes.length" class="py-20 bg-gradient-to-b from-neutral-50 to-white dark:from-neutral-900 dark:to-neutral-800">
      <div class="container-custom">
        <div class="text-center mb-16">
          <p class="text-accent-600 dark:text-accent-400 font-semibold mb-3 uppercase tracking-wider text-sm">
            Flexible Partnership
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold mb-4 text-neutral-900 dark:text-white">
            How We Can Work Together
          </h2>
          <p class="text-xl text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Choose the collaboration model that fits your needs
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
          <div
            v-for="(mode, index) in aboutSettings.collaboration_modes"
            :key="index"
            class="group relative bg-white dark:bg-neutral-800 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-neutral-200 dark:border-neutral-700"
          >
            <div class="absolute -top-4 -left-4 w-12 h-12 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">
              {{ index + 1 }}
            </div>

            <div class="mt-4">
              <h3 class="text-2xl font-bold text-neutral-900 dark:text-white mb-4 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ mode.mode }}
              </h3>
              <p class="text-neutral-600 dark:text-neutral-400 leading-relaxed">
                {{ mode.description }}
              </p>
            </div>

            <div class="mt-6 flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold opacity-0 group-hover:opacity-100 transition-opacity">
              <span class="text-sm">Learn more</span>
              <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Skills Section - COMPACT -->
    <section class="py-10 bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="text-center mb-8">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-3">Skills & Expertise</h2>
          <p class="text-neutral-600 dark:text-neutral-400 text-sm">Technologies and tools I work with</p>
        </div>

        <div v-if="aboutSettings?.skills && Array.isArray(aboutSettings.skills) && aboutSettings.skills.length > 0" class="max-w-6xl mx-auto">
          <div class="flex flex-wrap justify-center gap-2">
            <span
              v-for="skill in aboutSettings.skills"
              :key="skill"
              class="px-3 py-1.5 bg-neutral-50 dark:bg-neutral-900 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-colors shadow-sm border border-neutral-200 dark:border-neutral-700"
            >
              {{ skill }}
            </span>
          </div>
        </div>

        <div v-else class="max-w-6xl mx-auto">
          <div class="flex flex-wrap justify-center gap-2">
            <span
              v-for="skill in defaultSkills"
              :key="skill"
              class="px-3 py-1.5 bg-neutral-50 dark:bg-neutral-900 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-colors shadow-sm border border-neutral-200 dark:border-neutral-700"
            >
              {{ skill }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- Experience, Education, CTA sections stay the same -->
    <!-- ... rest of template ... -->

    <!-- CTA Section -->
    <CTASection
      v-if="showCTASection"
      heading="Let's Work Together"
      description="Ready to transform your business with <strong>AI Automation & Custom Development</strong>? Let's discuss how I can help you achieve your goals."
      whatsapp-message="Hi! I saw your portfolio and I'd like to discuss a project with you."
      :social-links="aboutSettings?.social_links"
      :show-social-links="true"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { BaseButton } from '@/components/base'
import CTASection from '@/components/CTASection.vue'
import { usePageSections } from '@/composables/usePageSections'
import { useAboutSettings } from '@/composables/useAboutSettings'

// Import language flags
import idFlag from '@/assets/language/ID.png'
import gbFlag from '@/assets/language/GB.png'
import cnFlag from '@/assets/language/CN.png'

// âœ… PAKAI COMPOSABLE (with localStorage cache!)
const { aboutSettings, loading } = useAboutSettings()
const { sections, fetchActiveSections } = usePageSections()

// Fetch sections on mount (auto-triggers via composable)
fetchActiveSections('about')

// CTA visibility
const showCTASection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'cta')
  return section ? section.is_active : false
})

// Default skills as fallback
const defaultSkills = [
  'Vue.js', 'React', 'TypeScript', 'Tailwind CSS', 'HTML5', 'CSS3',
  'Node.js', 'Express', 'Laravel', 'PHP', 'MySQL', 'MongoDB',
  'Git', 'Docker', 'AWS', 'CI/CD', 'Linux', 'Nginx'
]

// Display languages with auto-conversion
const displayLanguages = computed(() => {
  if (!aboutSettings.value?.languages || !Array.isArray(aboutSettings.value.languages) || aboutSettings.value.languages.length === 0) {
    return []
  }

  const languageMap = {
    'bahasa': { code: 'id', flag: '🇮🇩', name: 'Bahasa' },
    'indonesia': { code: 'id', flag: '🇮🇩', name: 'Indonesia' },
    'indonesian': { code: 'id', flag: '🇮🇩', name: 'Indonesian' },
    'english': { code: 'en', flag: '🇬🇧', name: 'English' },
    'mandarin': { code: 'zh', flag: '🇨🇳', name: 'Mandarin' },
    'chinese': { code: 'zh', flag: '🇨🇳', name: 'Chinese' }
  }

  return aboutSettings.value.languages.map(lang => {
    if (typeof lang === 'object' && lang.code && lang.name && lang.flag) {
      return lang
    }

    if (typeof lang === 'string') {
      const langLower = lang.toLowerCase().trim()
      const mapped = languageMap[langLower]
      
      if (mapped) {
        return mapped
      }

      return {
        code: langLower.substring(0, 2),
        name: lang,
        flag: '🌐'
      }
    }

    return {
      code: 'xx',
      name: String(lang),
      flag: '🌐'
    }
  })
})

const getProfilePhotoUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/uploads/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/uploads/' + path
}

const handleImageError = (event) => {
  console.warn('Failed to load profile photo:', event.target.src)
  event.target.style.display = 'none'
}

const getLangFlagImage = (code) => {
  const codeMap = {
    'id': idFlag,
    'en': gbFlag,
    'zh': cnFlag
  }
  return codeMap[code] || idFlag
}

const getWhatsAppLink = () => {
  // Default WhatsApp number
  const defaultPhone = '+6281380163758'
  const defaultMessage = "Hi! I saw your portfolio and I'd like to discuss a project with you."
  
  // Format phone number (remove +, spaces, and hyphens)
  const formattedPhone = defaultPhone.replace(/[^0-9]/g, '')
  
  // Encode message for URL
  const encodedMessage = encodeURIComponent(defaultMessage)
  
  return `https://wa.me/${formattedPhone}?text=${encodedMessage}`
}
</script>

<style scoped>
@keyframes blob {
  0%, 100% {
    transform: translate(0, 0) scale(1);
  }
  25% {
    transform: translate(20px, -50px) scale(1.1);
  }
  50% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  75% {
    transform: translate(50px, 50px) scale(1.05);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}
</style>
