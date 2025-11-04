<template>
  <div>
    <!-- Introduction Section -->
    <section class="section bg-white dark:bg-neutral-800 pt-20">
      <div class="container-custom">
        <div class="max-w-4xl mx-auto">
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="aspect-square bg-neutral-200 dark:bg-neutral-700 rounded-2xl overflow-hidden">
              <img
                v-if="about?.profile_photo"
                :src="getProfilePhotoUrl(about.profile_photo)"
                :alt="about.name || 'Profile Image'"
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

              <BaseButton variant="primary" @click="$router.push('/contact')">
                Get in Touch
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="h-10"></div>

    <!-- Mission Section - REDESIGNED (Nov 3, 2025) -->
    <section v-if="about?.mission" class="relative py-20 bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 overflow-hidden">
      <!-- Animated background -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute w-96 h-96 bg-white rounded-full blur-3xl top-0 -left-20 animate-blob"></div>
        <div class="absolute w-96 h-96 bg-white rounded-full blur-3xl top-0 -right-20 animate-blob animation-delay-2000"></div>
        <div class="absolute w-96 h-96 bg-white rounded-full blur-3xl -bottom-20 left-1/2 animate-blob animation-delay-4000"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-4xl mx-auto text-center">
          <!-- Icon -->
          <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
            </div>
          </div>

          <h2 class="text-4xl md:text-5xl font-display font-bold text-white mb-6">Our Mission</h2>
          <p class="text-xl md:text-2xl text-white/95 leading-relaxed font-medium">
            {{ about.mission }}
          </p>

          <!-- Decorative line -->
          <div class="flex justify-center mt-8">
            <div class="w-24 h-1 bg-white/30 rounded-full"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- What I Do Section - REDESIGNED (Nov 3, 2025) -->
    <section v-if="about?.what_i_do && about.what_i_do.length" class="py-20 bg-neutral-50 dark:bg-neutral-900">
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
            v-for="(service, index) in about.what_i_do"
            :key="index"
            class="group relative bg-white dark:bg-neutral-800 rounded-2xl p-8 hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-neutral-200 dark:border-neutral-700"
          >
            <!-- Gradient background on hover -->
            <div class="absolute inset-0 bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-primary-900/10 dark:to-secondary-900/10 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>

            <div class="relative z-10">
              <!-- Icon - BIGGER & CLEARER -->
              <div class="w-20 h-20 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform shadow-xl">
                <!-- Robot icon for AI Automation -->
                <svg v-if="service.icon === '🤖' || service.title.includes('Automation')" class="w-11 h-11 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2a2 2 0 012 2v1h4a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h4V4a2 2 0 012-2zm0 5a1 1 0 00-1 1v2a1 1 0 102 0V8a1 1 0 00-1-1zm-3 4a1 1 0 100 2h6a1 1 0 100-2H9z"/>
                </svg>
                
                <!-- Code icon for Development -->
                <svg v-else-if="service.icon === '💻' || service.title.includes('Development')" class="w-11 h-11 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                
                <!-- Consulting icon -->
                <svg v-else class="w-11 h-11 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>

              <!-- Title -->
              <h3 class="text-2xl font-bold text-neutral-900 dark:text-white mb-4 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ service.title }}
              </h3>

              <!-- Description -->
              <p class="text-neutral-600 dark:text-neutral-400 leading-relaxed">
                {{ service.description }}
              </p>
            </div>

            <!-- Bottom accent line -->
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-500 via-secondary-500 to-accent-500 rounded-b-2xl transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Approach Section - REDESIGNED (Nov 3, 2025) -->
    <section v-if="about?.approach" class="relative py-20 bg-white dark:bg-neutral-800 overflow-hidden">
      <!-- Background decoration -->
      <div class="absolute inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 40px 40px;"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-5xl mx-auto">
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <!-- Left: Icon + Title -->
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

            <!-- Right: Content -->
            <div>
              <p class="text-lg md:text-xl text-neutral-600 dark:text-neutral-300 leading-relaxed">
                {{ about.approach }}
              </p>

              <!-- Decorative quote marks -->
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

    <!-- Collaboration Modes Section - REDESIGNED (Nov 3, 2025) -->
    <section v-if="about?.collaboration_modes && about.collaboration_modes.length" class="py-20 bg-gradient-to-b from-neutral-50 to-white dark:from-neutral-900 dark:to-neutral-800">
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
            v-for="(mode, index) in about.collaboration_modes"
            :key="index"
            class="group relative bg-white dark:bg-neutral-800 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-neutral-200 dark:border-neutral-700"
          >
            <!-- Number badge -->
            <div class="absolute -top-4 -left-4 w-12 h-12 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">
              {{ index + 1 }}
            </div>

            <!-- Content -->
            <div class="mt-4">
              <h3 class="text-2xl font-bold text-neutral-900 dark:text-white mb-4 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ mode.mode }}
              </h3>
              <p class="text-neutral-600 dark:text-neutral-400 leading-relaxed">
                {{ mode.description }}
              </p>
            </div>

            <!-- Hover arrow -->
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

        <div v-if="about?.skills && Array.isArray(about.skills) && about.skills.length > 0" class="max-w-6xl mx-auto">
          <div class="flex flex-wrap justify-center gap-2">
            <span
              v-for="skill in about.skills"
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

    <!-- Experience Section - IMPROVED UI -->
    <section v-if="displayExperiences && displayExperiences.length > 0" class="section bg-neutral-50 dark:bg-neutral-900">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Experience</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            My professional journey
          </p>
        </div>

        <div class="max-w-4xl mx-auto space-y-6">
          <div 
            v-for="(exp, index) in displayExperiences" 
            :key="index" 
            class="bg-white dark:bg-neutral-800 rounded-xl p-6 hover:shadow-lg transition-shadow"
          >
            <div class="flex items-start gap-6">
              <!-- Company Logo -->
              <div v-if="exp.company_logo" class="flex-shrink-0">
                <img 
                  :src="exp.company_logo" 
                  :alt="exp.company"
                  class="w-16 h-16 object-contain rounded-lg bg-neutral-50 dark:bg-neutral-700 p-2"
                  @error="handleLogoError"
                />
              </div>
              <div v-else class="flex-shrink-0 w-16 h-16 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-lg flex items-center justify-center">
                <span class="text-2xl font-bold text-white">
                  {{ exp.company.charAt(0) }}
                </span>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <div>
                    <h3 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                      {{ exp.title }}
                    </h3>
                    <a 
                      v-if="exp.company_url" 
                      :href="exp.company_url" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      class="text-neutral-600 dark:text-neutral-400 font-medium hover:text-primary-600 dark:hover:text-primary-400 transition-colors inline-flex items-center gap-1"
                    >
                      {{ exp.company }}
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                      </svg>
                    </a>
                    <p v-else class="text-neutral-600 dark:text-neutral-400 font-medium">
                      {{ exp.company }}
                    </p>
                  </div>
                  <BaseBadge variant="info" size="sm">
                    {{ formatPeriod(exp.start_date, exp.end_date, exp.current) }}
                  </BaseBadge>
                </div>

                <!-- Location -->
                <p v-if="exp.location" class="text-sm text-neutral-500 dark:text-neutral-400 mb-3 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  </svg>
                  {{ exp.location }}
                </p>

                <!-- Description -->
                <div v-if="exp.description">
                  <p 
                    v-if="!expandedExperience[index]" 
                    class="text-neutral-600 dark:text-neutral-400 text-sm"
                  >
                    {{ truncateText(exp.description, 200) }}
                    <button 
                      v-if="exp.description.length > 200"
                      @click="toggleExperience(index)"
                      class="text-primary-600 dark:text-primary-400 hover:underline ml-1 font-medium"
                    >
                      See more
                    </button>
                  </p>
                  <div v-else>
                    <p class="text-neutral-600 dark:text-neutral-400 text-sm whitespace-pre-line">
                      {{ exp.description }}
                    </p>
                    <button 
                      @click="toggleExperience(index)"
                      class="text-primary-600 dark:text-primary-400 hover:underline mt-2 font-medium text-sm"
                    >
                      See less
                    </button>
                  </div>
                </div>

                <!-- Gallery Button -->
                <div v-if="exp.gallery_ids && exp.gallery_ids.length > 0" class="mt-4">
                  <button
                    @click="openWorkGallery(exp.gallery_ids)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors text-sm font-medium"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    View Gallery ({{ exp.gallery_ids.length }})
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="h-10"></div>

    <!-- Education Section -->
    <section v-if="displayEducation && displayEducation.length > 0" class="section bg-white dark:bg-neutral-800">
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

    <!-- CTA Section (Component) -->
    <CTASection
      v-if="showCTASection"
      heading="Let's Work Together"
      description="Ready to transform your business with <strong>AI Automation & Custom Development</strong>? Let's discuss how I can help you achieve your goals."
      whatsapp-message="Hi! I saw your portfolio and I'd like to discuss a project with you."
      :social-links="about?.social_links"
      :show-social-links="true"
    />

    <!-- Work Gallery Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showWorkGalleryModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
          @click.self="closeWorkGalleryModal"
        >
          <div class="relative w-full max-w-6xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-800">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                Work Gallery
              </h3>
              <button
                @click="closeWorkGalleryModal"
                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
              >
                <svg class="w-6 h-6 text-gray-400 hover:text-gray-900 dark:hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>

            <!-- Modal Body - Gallery Grid -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
              <!-- Loading State -->
              <div v-if="loadingWorkGallery" class="flex items-center justify-center py-20">
                <svg class="animate-spin h-12 w-12 text-primary-600" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>

              <!-- Gallery Grid -->
              <div v-else-if="workGalleryItems.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                  v-for="item in workGalleryItems"
                  :key="item.id"
                  class="relative group cursor-pointer aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800"
                >
                  <img
                    :src="item.file_url || getImageUrl(item.file_path)"
                    :alt="item.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                  />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="absolute bottom-0 left-0 right-0 p-3">
                      <p class="text-white text-sm font-semibold truncate">
                        {{ item.title }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-else class="text-center py-20">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="mt-4 text-gray-500 dark:text-gray-400">No photos available.</p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { BaseButton, BaseCard, BaseBadge } from '@/components/base'
import CTASection from '@/components/CTASection.vue'
import { usePageSections } from '@/composables/usePageSections'
import api from '@/services/api'
import { useRouter } from 'vue-router'

// Import language flags
import idFlag from '@/assets/language/ID.png'
import gbFlag from '@/assets/language/GB.png'
import cnFlag from '@/assets/language/CN.png'

const about = ref(null)
const loading = ref(true)
const error = ref(null)
const expandedExperience = ref({})
const router = useRouter()
const { sections, fetchActiveSections } = usePageSections()

// CTA visibility
const showCTASection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'cta')
  return section ? section.is_active : false
})

// Gallery modal state
const showWorkGalleryModal = ref(false)
const workGalleryItems = ref([])
const loadingWorkGallery = ref(false)

// Default skills as fallback (flat array)
const defaultSkills = [
  'Vue.js', 'React', 'TypeScript', 'Tailwind CSS', 'HTML5', 'CSS3',
  'Node.js', 'Express', 'Laravel', 'PHP', 'MySQL', 'MongoDB',
  'Git', 'Docker', 'AWS', 'CI/CD', 'Linux', 'Nginx'
]

// Computed properties for conditional rendering
const displayExperiences = computed(() => {
  if (about.value?.experience && Array.isArray(about.value.experience) && about.value.experience.length > 0) {
    // Sort by start_date descending (most recent first)
    return [...about.value.experience].sort((a, b) => {
      // Parse date strings like "Oct 2011" or "Jan 2020"
      const parseDate = (dateStr) => {
        if (!dateStr) return new Date(0) // Epoch for missing dates
        const date = new Date(dateStr)
        return isNaN(date.getTime()) ? new Date(0) : date
      }
      
      const dateA = parseDate(a.start_date)
      const dateB = parseDate(b.start_date)
      
      return dateB - dateA // Descending (newest first)
    })
  }
  return []
})

const displayEducation = computed(() => {
  if (about.value?.education && Array.isArray(about.value.education) && about.value.education.length > 0) {
    return about.value.education
  }
  return []
})

// Display languages with auto-conversion from string to object
const displayLanguages = computed(() => {
  if (!about.value?.languages || !Array.isArray(about.value.languages) || about.value.languages.length === 0) {
    return []
  }

  // Mapping language name to flag emoji and code
  const languageMap = {
    'bahasa': { code: 'id', flag: '🇮🇩', name: 'Bahasa' },
    'indonesia': { code: 'id', flag: '🇮🇩', name: 'Indonesia' },
    'indonesian': { code: 'id', flag: '🇮🇩', name: 'Indonesian' },
    'english': { code: 'en', flag: '🇬🇧', name: 'English' },
    'mandarin': { code: 'zh', flag: '🇨🇳', name: 'Mandarin' },
    'chinese': { code: 'zh', flag: '🇨🇳', name: 'Chinese' },
    'japanese': { code: 'jp', flag: '🇯🇵', name: 'Japanese' },
    'korean': { code: 'kr', flag: '🇰🇷', name: 'Korean' },
    'spanish': { code: 'es', flag: '🇪🇸', name: 'Spanish' },
    'french': { code: 'fr', flag: '🇫🇷', name: 'French' },
    'german': { code: 'de', flag: '🇩🇪', name: 'German' },
    'arabic': { code: 'ar', flag: '🇸🇦', name: 'Arabic' },
    'russian': { code: 'ru', flag: '🇷🇺', name: 'Russian' },
    'portuguese': { code: 'pt', flag: '🇵🇹', name: 'Portuguese' },
    'dutch': { code: 'nl', flag: '🇳🇱', name: 'Dutch' },
    'italian': { code: 'it', flag: '🇮🇹', name: 'Italian' },
    'thai': { code: 'th', flag: '🇹🇭', name: 'Thai' },
    'vietnamese': { code: 'vi', flag: '🇻🇳', name: 'Vietnamese' },
    'malay': { code: 'ms', flag: '🇲🇾', name: 'Malay' },
    'tagalog': { code: 'tl', flag: '🇵🇭', name: 'Tagalog' },
    'filipino': { code: 'tl', flag: '🇵🇭', name: 'Filipino' }
  }

  return about.value.languages.map(lang => {
    // If already an object with required fields, return as is
    if (typeof lang === 'object' && lang.code && lang.name && lang.flag) {
      return lang
    }

    // If string, convert to object
    if (typeof lang === 'string') {
      const langLower = lang.toLowerCase().trim()
      const mapped = languageMap[langLower]
      
      if (mapped) {
        return mapped
      }

      // Fallback: use original string with generic flag
      return {
        code: langLower.substring(0, 2),
        name: lang,
        flag: '🌐' // Generic globe emoji
      }
    }

    // Unknown format, return with generic flag
    return {
      code: 'xx',
      name: String(lang),
      flag: '🌐'
    }
  })
})

const formatPeriod = (startDate, endDate, isCurrent) => {
  if (!startDate) return ''
  const start = startDate
  const end = isCurrent ? 'Present' : (endDate || 'Present')
  return `${start} - ${end}`
}

const truncateText = (text, maxLength) => {
  if (!text || text.length <= maxLength) return text
  return text.substring(0, maxLength).trim() + '...'
}

const toggleExperience = (index) => {
  expandedExperience.value[index] = !expandedExperience.value[index]
}

const handleLogoError = (event) => {
  event.target.style.display = 'none'
}

const openWorkGallery = async (galleryIds) => {
  if (!galleryIds || galleryIds.length === 0) return
  
  showWorkGalleryModal.value = true
  loadingWorkGallery.value = true
  workGalleryItems.value = []

  try {
    // Fetch items from multiple galleries
    const promises = galleryIds.map(id => api.get(`/galleries/${id}/items`))
    const responses = await Promise.all(promises)
    
    // Combine all items
    const allItems = []
    responses.forEach(response => {
      if (response.data.success && response.data.data) {
        allItems.push(...response.data.data)
      }
    })
    
    workGalleryItems.value = allItems
  } catch (err) {
    console.error('Failed to load gallery items:', err)
  } finally {
    loadingWorkGallery.value = false
  }
}

const closeWorkGalleryModal = () => {
  showWorkGalleryModal.value = false
  workGalleryItems.value = []
}

const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/storage/' + path
}

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

// Get language flag image based on code
const getLangFlagImage = (code) => {
  const codeMap = {
    'id': idFlag,
    'en': gbFlag,
    'zh': cnFlag,
    'jp': cnFlag, // fallback
    'kr': idFlag, // fallback
    'es': idFlag,
    'fr': gbFlag,
    'de': gbFlag,
    'ar': idFlag,
    'ru': idFlag,
    'pt': idFlag,
    'nl': gbFlag,
    'it': idFlag,
    'th': idFlag,
    'vi': idFlag,
    'ms': idFlag,
    'tl': idFlag
  }
  return codeMap[code] || idFlag
}

onMounted(async () => {
  await fetchActiveSections('about')
  await fetchAboutData()
})

// Re-fetch data when navigating to this page
watch(() => router.currentRoute.value.path, (newPath) => {
  if (newPath === '/about') {
    fetchAboutData()
  }
})

async function fetchAboutData() {
  loading.value = true
  error.value = null

  try {
    // Add timestamp to prevent caching
    const response = await api.get('/settings/about', {
      params: { _t: Date.now() }
    })

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

<style scoped>
/* Modal transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

/* Blob animation */
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
