<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-accent-500 rounded-full blur-3xl"></div>
      </div>

      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            About <span class="text-gradient">{{ about?.name || 'Me' }}</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            {{ about?.title || 'Passionate developer crafting digital experiences' }}
          </p>
        </div>
      </div>
    </section>

    <!-- Introduction Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="max-w-4xl mx-auto">
          <div class="h-10"></div>
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="aspect-square bg-neutral-200 dark:bg-neutral-700 rounded-2xl overflow-hidden">
              <img
                v-if="about?.profile_image"
                :src="about.profile_image"
                :alt="about.name || 'Profile Image'"
                class="w-full h-full object-cover"
              />
            </div>
            <div>
              <h2 class="text-3xl md:text-4xl font-display font-bold mb-6">
                Hi, I'm {{ about?.name || '[Your Name]' }}
              </h2>
              <div v-if="about?.bio" v-html="about.bio" class="prose dark:prose-invert text-neutral-600 dark:text-neutral-400 mb-6 max-w-none"></div>
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
              <BaseButton variant="primary" @click="$router.push('/contact')">
                Get in Touch
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
      <div class="h-10"></div>
    </section>
    <!-- Skills Section -->
    <section class="section bg-neutral-50 dark:bg-neutral-900">
      <div class="h-10"></div>
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Skills & Expertise</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Technologies and tools I work with
          </p>
        </div>

        <div v-if="about?.skills && Array.isArray(about.skills) && about.skills.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-w-5xl mx-auto">
          <div
            v-for="skill in about.skills"
            :key="skill"
            class="flex items-center justify-center px-6 py-4 bg-white dark:bg-neutral-800 rounded-lg shadow-sm hover:shadow-md transition-shadow"
          >
            <span class="text-sm md:text-base font-medium text-neutral-700 dark:text-neutral-300">{{ skill }}</span>
          </div>
        </div>

        <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">
          <BaseCard v-for="skill in skillsGrouped" :key="skill.category">
            <h3 class="text-xl font-semibold mb-4">{{ skill.category }}</h3>
            <div class="flex flex-wrap gap-2">
              <BaseBadge v-for="tech in skill.technologies" :key="tech" variant="primary" size="sm">
                {{ tech }}
              </BaseBadge>
            </div>
          </BaseCard>
        </div>
      </div>
    </section>

    <div class="h-10"></div>

    <!-- Experience Section -->
    <section v-if="displayExperiences && displayExperiences.length > 0" class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Experience</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            My professional journey
          </p>
        </div>

        <div class="max-w-3xl mx-auto space-y-8">
          <div v-for="(exp, index) in displayExperiences" :key="index" class="border-l-4 border-primary-500 pl-6 pb-8 relative">
            <div class="absolute -left-2 top-0 w-4 h-4 bg-primary-500 rounded-full"></div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-2">
              <h3 class="text-xl font-semibold">{{ exp.position }}</h3>
              <BaseBadge variant="info" size="sm">{{ exp.period }}</BaseBadge>
            </div>
            <p class="text-neutral-600 dark:text-neutral-400 mb-2 font-medium">{{ exp.company }}</p>
            <p class="text-neutral-600 dark:text-neutral-400 text-sm">{{ exp.description }}</p>
          </div>
        </div>
      </div>
    </section>
    <div class="h-10"></div>

    <!-- Education Section -->
    <section v-if="displayEducation && displayEducation.length > 0" class="section bg-neutral-50 dark:bg-neutral-900">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Education</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Academic background
          </p>
        </div>

        <div class="max-w-4xl mx-auto grid md:grid-cols-2 gap-6">
          <BaseCard v-for="(edu, index) in displayEducation" :key="index">
            <div class="flex items-start justify-between mb-3">
              <h3 class="text-xl font-semibold">{{ edu.degree }}</h3>
              <BaseBadge variant="primary" size="sm">{{ edu.period }}</BaseBadge>
            </div>
            <p class="text-neutral-600 dark:text-neutral-400 font-medium mb-2">{{ edu.institution }}</p>
            <p v-if="edu.description" class="text-neutral-600 dark:text-neutral-400 text-sm">{{ edu.description }}</p>
          </BaseCard>
        </div>
      </div>
    </section>
    <div class="h-10"></div>

    <!-- Social Links Section -->
    <section v-if="displaySocialLinks && displaySocialLinks.length > 0" class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Connect With Me</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Find me on social media
          </p>
        </div>

        <div class="flex flex-wrap justify-center gap-4 max-w-2xl mx-auto">
          <a
            v-for="(link, index) in displaySocialLinks"
            :key="index"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-3 px-6 py-4 bg-neutral-50 dark:bg-neutral-900 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors group"
          >
            <svg v-if="link.platform === 'github'" class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
            </svg>
            <svg v-else-if="link.platform === 'linkedin'" class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
            </svg>
            <svg v-else-if="link.platform === 'twitter'" class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
            </svg>
            <svg v-else class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm0 22c-5.514 0-10-4.486-10-10s4.486-10 10-10 10 4.486 10 10-4.486 10-10 10zm1-16h-2v7h7v-2h-5z"/>
            </svg>
            <span class="font-medium text-neutral-700 dark:text-neutral-300 group-hover:text-primary-600">
              {{ link.platform.charAt(0).toUpperCase() + link.platform.slice(1) }}
            </span>
          </a>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="section bg-gradient-to-r from-primary-600 to-accent-600 text-white">
      <div class="h-10"></div>
      <div class="container-custom text-center">
        <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Let's Work Together</h2>
        <p class="text-lg md:text-xl mb-8 opacity-90 max-w-2xl mx-auto">
          Interested in collaborating? I'd love to hear about your project.
        </p>
        <BaseButton variant="secondary" size="lg" @click="$router.push('/contact')">
          Contact Me
        </BaseButton>
      </div>
      <div class="h-10"></div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { BaseButton, BaseCard, BaseBadge } from '@/components/base'
import api from '@/services/api'

const about = ref(null)
const loading = ref(true)
const error = ref(null)

// Default grouped skills as fallback
const skillsGrouped = ref([
  {
    category: 'Frontend',
    technologies: ['Vue.js', 'React', 'TypeScript', 'Tailwind CSS', 'HTML5', 'CSS3']
  },
  {
    category: 'Backend',
    technologies: ['Node.js', 'Express', 'Laravel', 'PHP', 'MySQL', 'MongoDB']
  },
  {
    category: 'DevOps & Tools',
    technologies: ['Git', 'Docker', 'AWS', 'CI/CD', 'Linux', 'Nginx']
  }
])

// Computed properties for conditional rendering
const displayExperiences = computed(() => {
  if (about.value?.experiences && Array.isArray(about.value.experiences) && about.value.experiences.length > 0) {
    return about.value.experiences
  }
  return []
})

const displayEducation = computed(() => {
  if (about.value?.education && Array.isArray(about.value.education) && about.value.education.length > 0) {
    return about.value.education
  }
  return []
})

const displaySocialLinks = computed(() => {
  if (about.value?.social_links && Array.isArray(about.value.social_links) && about.value.social_links.length > 0) {
    return about.value.social_links
  }
  return []
})

onMounted(async () => {
  await fetchAboutData()
})

async function fetchAboutData() {
  loading.value = true
  error.value = null

  try {
    const response = await api.get('/settings/about')

    if (response.data.success && response.data.data) {
      about.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to fetch about data:', err)
    error.value = err.response?.data?.message || 'Failed to load about information'
    // Continue with default data on error
  } finally {
    loading.value = false
  }
}
</script>
