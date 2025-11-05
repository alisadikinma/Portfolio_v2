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

    <!-- Skills Section -->
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

    <!-- Work Experience Section - NEW -->
    <section class="py-20 bg-gradient-to-b from-white to-neutral-50 dark:from-neutral-800 dark:to-neutral-900">
      <div class="container-custom">
        <div class="text-center mb-16">
          <p class="text-primary-600 dark:text-primary-400 font-semibold mb-3 uppercase tracking-wider text-sm">
            Professional Journey
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold mb-2 text-neutral-900 dark:text-white">
            Work Experience
          </h2>
          <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mb-4">
            17 Years Experience
          </p>
          <p class="text-xl text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Building impactful solutions across industries and technologies
          </p>
        </div>

        <div v-if="experienceData && experienceData.length > 0" class="max-w-5xl mx-auto">
          <!-- Experience Items Container -->
          <div class="relative">
            <!-- Timeline Line (only for experience items) -->
            <div class="absolute left-[31px] md:left-1/2 top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary-500 via-secondary-500 to-accent-500 hidden md:block"></div>
            <div class="absolute left-[31px] top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary-500 via-secondary-500 to-accent-500 md:hidden"></div>

            <!-- Experience Items -->
            <div class="space-y-12">
            <div
              v-for="(exp, index) in displayedExperience"
              :key="index"
              class="relative"
              :class="index % 2 === 0 ? 'md:pr-[50%]' : 'md:pl-[50%] md:text-right'"
            >
              <!-- Timeline Dot with Country Flag -->
              <div 
                class="absolute left-0 md:left-1/2 w-16 h-16 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-2xl flex items-center justify-center shadow-xl z-10 transform md:-translate-x-1/2 p-2"
                :class="{
                  'animate-pulse-slow': index === 0
                }"
              >
                <img :src="getCountryFlag(exp.location)" :alt="exp.location" class="w-full h-full object-contain rounded-lg" />
              </div>

              <!-- Duration Badge (Conditional Position) -->
              <div 
                class="absolute z-20 hidden md:block"
                :class="{
                  'left-20 md:left-1/2 md:ml-12 top-4': index % 2 === 0,
                  'right-20 md:right-1/2 md:mr-12 top-4': index % 2 !== 0
                }"
              >
                <div class="inline-flex flex-col gap-1 px-4 py-2 bg-white dark:bg-neutral-800 rounded-lg shadow-md border-2 border-primary-200 dark:border-primary-700">
                  <span class="text-xs font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">{{ exp.start_date }} - {{ formatEndDate(exp.end_date) }}</span>
                  <span class="text-xs text-neutral-600 dark:text-neutral-400 whitespace-nowrap">{{ formatDuration(calculateDuration(exp.start_date, exp.end_date)) }}</span>
                </div>
              </div>

              <!-- Content Card -->
              <div 
                class="ml-24 md:ml-0 group"
                :class="index % 2 === 0 ? 'md:mr-12' : 'md:ml-12'"
              >
                <div class="bg-white dark:bg-neutral-800 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border-2 border-neutral-200 dark:border-neutral-700 hover:border-primary-500 dark:hover:border-primary-500 hover:-translate-y-1 relative">
                  <!-- Organization Icon (Conditional Position) -->
                  <div 
                    v-if="getOrganizationIcon(exp.company)"
                    class="absolute top-6 w-12 h-12"
                    :class="{
                      'right-6': index % 2 === 0,
                      'left-6': index % 2 !== 0
                    }"
                  >
                    <img 
                      :src="getOrganizationIcon(exp.company)" 
                      :alt="exp.company" 
                      class="w-full h-full object-contain opacity-30 group-hover:opacity-60 transition-opacity"
                    />
                  </div>

                  <!-- Header -->
                  <div class="mb-6">
                    <h3 
                      class="text-xl font-bold text-neutral-900 dark:text-white mb-3 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2"
                      :class="{
                        'pr-16': index % 2 === 0 && getOrganizationIcon(exp.company),
                        'pl-16': index % 2 !== 0 && getOrganizationIcon(exp.company)
                      }"
                    >
                      {{ exp.title || exp.position }}
                    </h3>
                    <div 
                      class="flex items-center gap-2"
                      :class="{
                        'justify-start': index % 2 === 0,
                        'justify-end': index % 2 !== 0
                      }"
                    >
                      <svg class="w-5 h-5 text-primary-600 dark:text-primary-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                      </svg>
                      <a 
                        v-if="exp.company_url" 
                        :href="exp.company_url" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="text-base font-semibold text-neutral-700 dark:text-neutral-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors underline decoration-transparent hover:decoration-primary-600 dark:hover:decoration-primary-400"
                      >
                        {{ exp.company }}
                      </a>
                      <span v-else class="text-base font-semibold text-neutral-700 dark:text-neutral-300">{{ exp.company }}</span>
                    </div>
                    
                    <!-- Mobile Duration Info -->
                    <div class="md:hidden mt-2 flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                      <img :src="getCountryFlag(exp.location)" :alt="exp.location" class="w-4 h-4 object-contain rounded" />
                      <span class="font-semibold">{{ exp.start_date }} - {{ formatEndDate(exp.end_date) }}</span>
                      <span class="text-neutral-400 dark:text-neutral-500">•</span>
                      <span>{{ formatDuration(calculateDuration(exp.start_date, exp.end_date)) }}</span>
                    </div>
                  </div>

                  <!-- Job Description Header -->
                  <div class="mb-3">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 flex items-center gap-2">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <span>Job Description</span>
                    </h4>
                  </div>

                  <!-- Description -->
                  <div class="relative">
                    <div 
                      class="overflow-hidden transition-all duration-500 ease-in-out"
                      :class="{
                        'max-h-24': !isExpanded(index),
                        'max-h-[2000px]': isExpanded(index)
                      }"
                    >
                      <div 
                        v-html="exp.description" 
                        class="prose dark:prose-invert max-w-none text-neutral-600 dark:text-neutral-400 leading-relaxed"
                        :class="{
                          'text-left': index % 2 === 0,
                          'text-right': index % 2 !== 0
                        }"
                      ></div>
                    </div>
                    
                    <!-- Gradient Overlay (when collapsed) -->
                    <div 
                      v-if="!isExpanded(index) && isDescriptionLong(exp.description)"
                      class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-white dark:from-neutral-800 to-transparent pointer-events-none"
                    ></div>
                  </div>

                  <!-- See More/Less Button -->
                  <button
                    v-if="isDescriptionLong(exp.description)"
                    @click="toggleExpand(index)"
                    class="mt-4 inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold hover:text-primary-700 dark:hover:text-primary-300 transition-colors group/btn"
                    :class="{
                      '': index % 2 === 0,
                      'ml-auto flex-row-reverse': index % 2 !== 0
                    }"
                  >
                    <span>{{ isExpanded(index) ? 'Show Less' : 'See More' }}</span>
                    <svg 
                      class="w-5 h-5 transition-transform duration-300" 
                      :class="{
                        'rotate-180': isExpanded(index),
                        'group-hover/btn:translate-y-1': !isExpanded(index),
                        'group-hover/btn:-translate-y-1': isExpanded(index)
                      }"
                      fill="none" 
                      stroke="currentColor" 
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
          </div>

          <!-- See More Button (Outside Timeline) -->
          <div v-if="hasMoreExperience" class="mt-16 text-center">
            <div class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
              Showing {{ displayedExperience.length }} of {{ experienceData.length }} experiences
            </div>
            <button
              @click="showAllExperience = !showAllExperience"
              class="inline-flex items-center gap-3 px-8 py-4 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 text-neutral-900 dark:text-white font-semibold rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl border-2 border-neutral-200 dark:border-neutral-700 hover:border-primary-500 dark:hover:border-primary-500 group"
            >
              <span class="text-lg">{{ showAllExperience ? 'Show Less' : `See More (${experienceData.length - 4} more)` }}</span>
              <svg 
                class="w-6 h-6 transition-transform duration-300"
                :class="{
                  'rotate-180': showAllExperience
                }"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- No Data State -->
        <div v-else class="text-center py-12">
          <svg class="w-20 h-20 mx-auto text-neutral-400 dark:text-neutral-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <p class="text-lg text-neutral-600 dark:text-neutral-400">No work experience data available</p>
        </div>
      </div>
    </section>

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

    <!-- Collaboration Modes Section - REVISED -->
    <section v-if="aboutSettings?.collaboration_modes && aboutSettings.collaboration_modes.length" class="py-20 bg-gradient-to-b from-neutral-50 to-white dark:from-neutral-900 dark:to-neutral-800">
      <div class="container-custom">
        <div class="text-center mb-12">
          <p class="text-accent-600 dark:text-accent-400 font-semibold mb-3 uppercase tracking-wider text-sm">
            Flexible Partnership
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold mb-4 text-neutral-900 dark:text-white">
            Let's Build Results Together
          </h2>
          <p class="text-xl text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Collaboration models designed for <strong class="text-primary-600 dark:text-primary-400">long-term impact</strong> and <strong class="text-primary-600 dark:text-primary-400">real business growth</strong>
          </p>
        </div>

        <!-- Trust Badges -->
        <div class="flex flex-wrap justify-center gap-4 md:gap-6 mb-12 max-w-4xl mx-auto">
          <div class="flex items-center gap-3 px-4 md:px-5 py-3 bg-white dark:bg-neutral-800 rounded-xl shadow-md border border-neutral-200 dark:border-neutral-700">
            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-500 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div>
              <p class="text-xl md:text-2xl font-bold text-neutral-900 dark:text-white">56+</p>
              <p class="text-xs md:text-sm text-neutral-600 dark:text-neutral-400">Projects Delivered</p>
            </div>
          </div>

          <div class="flex items-center gap-3 px-4 md:px-5 py-3 bg-white dark:bg-neutral-800 rounded-xl shadow-md border border-neutral-200 dark:border-neutral-700">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div>
              <p class="text-xl md:text-2xl font-bold text-neutral-900 dark:text-white">24/7</p>
              <p class="text-xs md:text-sm text-neutral-600 dark:text-neutral-400">Available US/Asia</p>
            </div>
          </div>

          <div class="flex items-center gap-3 px-4 md:px-5 py-3 bg-white dark:bg-neutral-800 rounded-xl shadow-md border border-neutral-200 dark:border-neutral-700">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
            </div>
            <div>
              <p class="text-xl md:text-2xl font-bold text-neutral-900 dark:text-white">100%</p>
              <p class="text-xs md:text-sm text-neutral-600 dark:text-neutral-400">Client Satisfaction</p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
          <div
            v-for="(mode, index) in getEnhancedModes"
            :key="index"
            @click="scrollToCTA"
            class="group relative bg-white dark:bg-neutral-800 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border-2 border-neutral-200 dark:border-neutral-700 hover:border-primary-500 dark:hover:border-primary-500 cursor-pointer"
          >
            <div class="absolute -top-4 -left-4 w-12 h-12 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">
              {{ index + 1 }}
            </div>

            <div class="mt-4">
              <h3 class="text-2xl font-bold text-neutral-900 dark:text-white mb-4 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ mode.mode }}
              </h3>
              <p class="text-neutral-600 dark:text-neutral-400 leading-relaxed mb-4">
                {{ mode.description }}
              </p>
              <!-- Enhanced Value Proposition -->
              <div class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                <p class="text-sm font-semibold text-primary-600 dark:text-primary-400 leading-relaxed">
                  {{ mode.value }}
                </p>
              </div>
            </div>

            <div class="mt-6 flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold opacity-0 group-hover:opacity-100 transition-opacity">
              <span class="text-sm">Let's discuss this</span>
              <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <CTASection
      ref="ctaSectionRef"
      v-if="showCTASection"
      heading="Ready for more than just a consultant?"
      description="I'm invested in seeing your business win. Let's discuss how I can <strong>accelerate your next milestone</strong> - reach out now for candid, actionable insight."
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

// Import language flags
import idFlag from '@/assets/language/ID.png'
import gbFlag from '@/assets/language/GB.png'
import cnFlag from '@/assets/language/CN.png'
import sgFlag from '@/assets/language/SG.png'

// Import organization icons
import universityIcon from '@/assets/organization/university.png'
import supplyChainIcon from '@/assets/organization/supply-chain-management.png'
import cyberSecurityIcon from '@/assets/organization/cyber-security.png'
import maritimeIcon from '@/assets/organization/maritime.png'
import startupIcon from '@/assets/organization/startup.png'
import manufacturingIcon from '@/assets/organization/manufacturing.png'

// Composables
const { aboutSettings, loading } = useAboutSettings()
const { sections, fetchActiveSections } = usePageSections()
const { settings, fetchSettings, getSettingValue } = useSettings()

// Fetch sections on mount
fetchActiveSections('about')
fetchSettings() // Fetch settings for WhatsApp phone

// Work Experience Data
const experienceData = ref([])
const expandedStates = ref({})
const showAllExperience = ref(false)

// Computed: Show only 4 latest or all
const displayedExperience = computed(() => {
  if (showAllExperience.value) {
    return experienceData.value
  }
  return experienceData.value.slice(0, 4)
})

// Check if there are more than 4 experiences
const hasMoreExperience = computed(() => {
  return experienceData.value.length > 4
})

// Format end date - if empty show "Current Position"
const formatEndDate = (endDate) => {
  if (!endDate || endDate.trim() === '') {
    return 'Current Position'
  }
  return endDate
}

// Parse month name to number
const monthToNumber = (monthName) => {
  const months = {
    'jan': 0, 'january': 0,
    'feb': 1, 'february': 1,
    'mar': 2, 'march': 2,
    'apr': 3, 'april': 3,
    'may': 4,
    'jun': 5, 'june': 5,
    'jul': 6, 'july': 6,
    'aug': 7, 'august': 7,
    'sep': 8, 'september': 8,
    'oct': 9, 'october': 9,
    'nov': 10, 'november': 10,
    'dec': 11, 'december': 11
  }
  return months[monthName.toLowerCase()] || 0
}

// Parse date string like "Apr 2008" to Date object
const parseExperienceDate = (dateStr) => {
  if (!dateStr || dateStr.trim() === '') return new Date()
  
  // Handle "Present" or "Current Position" or similar
  if (dateStr.toLowerCase().includes('present') || dateStr.toLowerCase().includes('current')) {
    return new Date()
  }
  
  const parts = dateStr.trim().split(' ')
  if (parts.length === 2) {
    const month = monthToNumber(parts[0])
    const year = parseInt(parts[1])
    return new Date(year, month)
  }
  
  return new Date()
}

// Calculate duration in months between two dates
const calculateDuration = (startDate, endDate) => {
  const start = parseExperienceDate(startDate)
  const end = parseExperienceDate(endDate)
  
  const years = end.getFullYear() - start.getFullYear()
  const months = end.getMonth() - start.getMonth()
  
  return (years * 12) + months
}

// Format duration to human readable
const formatDuration = (months) => {
  const years = Math.floor(months / 12)
  const remainingMonths = months % 12
  
  if (years === 0) {
    return `${remainingMonths} ${remainingMonths === 1 ? 'month' : 'months'}`
  }
  
  if (remainingMonths === 0) {
    return `${years} ${years === 1 ? 'year' : 'years'}`
  }
  
  return `${years} ${years === 1 ? 'year' : 'years'}, ${remainingMonths} ${remainingMonths === 1 ? 'month' : 'months'}`
}

// Get country flag image based on location
const getCountryFlag = (location) => {
  if (!location) return idFlag // Default Indonesia
  
  const loc = location.toLowerCase()
  
  if (loc.includes('singapore')) return sgFlag
  if (loc.includes('indonesia') || loc.includes('batam') || loc.includes('jakarta')) return idFlag
  if (loc.includes('china')) return cnFlag
  if (loc.includes('uk') || loc.includes('united kingdom') || loc.includes('london')) return gbFlag
  
  return idFlag // Default Indonesia
}

// Get organization icon based on company name
const getOrganizationIcon = (company) => {
  if (!company) return null
  
  const companyLower = company.toLowerCase()
  
  // exsys → university
  if (companyLower.includes('exsys') || companyLower.includes('ex sys')) {
    return universityIcon
  }
  // DHL → supply chain
  if (companyLower.includes('dhl') || companyLower.includes('supply chain')) {
    return supplyChainIcon
  }
  // Thales → cyber security
  if (companyLower.includes('thales')) {
    return cyberSecurityIcon
  }
  // MPA → maritime
  if (companyLower.includes('mpa') || companyLower.includes('maritime')) {
    return maritimeIcon
  }
  // Marlin → startup
  if (companyLower.includes('marlin')) {
    return startupIcon
  }
  // SATNUSA → manufacturing
  if (companyLower.includes('satnusa') || companyLower.includes('sat nusa') || companyLower.includes('nusapersada')) {
    return manufacturingIcon
  }
  
  return null
}

// Calculate total experience in years
const totalExperienceYears = computed(() => {
  if (!experienceData.value || experienceData.value.length === 0) return 0
  
  const totalMonths = experienceData.value.reduce((sum, exp) => {
    const duration = calculateDuration(exp.start_date, exp.end_date)
    return sum + duration
  }, 0)
  
  return Math.floor(totalMonths / 12)
})

// Force load settings on mount
onMounted(async () => {
  await fetchSettings(true) // Force refresh
})

// Parse and watch experience data from settings
watch(
  () => settings.value,
  (settingsArray) => {
    if (!settingsArray || settingsArray.length === 0) {
      return
    }
    
    // Find experience setting
    const experienceSetting = settingsArray.find(s => s.key === 'experience')
    
    if (!experienceSetting) {
      experienceData.value = []
      return
    }
    
    const experienceValue = experienceSetting.value
    
    try {
      let parsedData = []
      
      // Check if it's already an array
      if (Array.isArray(experienceValue)) {
        parsedData = experienceValue
      } 
      // Try parsing if it's a JSON string
      else if (typeof experienceValue === 'string') {
        parsedData = JSON.parse(experienceValue)
      }
      
      // Sort by start_date descending (newest first)
      parsedData.sort((a, b) => {
        const dateA = parseExperienceDate(a.start_date)
        const dateB = parseExperienceDate(b.start_date)
        return dateB - dateA // Descending order
      })
      
      experienceData.value = parsedData
      
      console.log('[About] Total experiences loaded:', parsedData.length)
      console.log('[About] Has more than 4?', parsedData.length > 4)
      console.log('[About] First experience data:', parsedData[0])
      console.log('[About] Field names available:', Object.keys(parsedData[0] || {}))
    } catch (error) {
      console.error('[About] Error parsing experience data:', error)
      experienceData.value = []
    }
  },
  { immediate: true, deep: true }
)

// Toggle expand/collapse for description
const toggleExpand = (index) => {
  expandedStates.value[index] = !expandedStates.value[index]
}

// Check if experience item is expanded
const isExpanded = (index) => {
  return expandedStates.value[index] || false
}

// Check if description is long enough to need expand/collapse
const isDescriptionLong = (description) => {
  if (!description) return false
  // Remove HTML tags for accurate character count
  const textOnly = description.replace(/<[^>]*>/g, '')
  return textOnly.length > 200 // ~3 lines of text
}

// CTA visibility
const showCTASection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'cta')
  return section ? section.is_active : false
})

// CTA Section Ref
const ctaSectionRef = ref(null)

// Scroll to CTA function
const scrollToCTA = () => {
  if (ctaSectionRef.value && ctaSectionRef.value.$el) {
    ctaSectionRef.value.$el.scrollIntoView({ 
      behavior: 'smooth', 
      block: 'start' 
    })
  }
}

// Default skills as fallback
const defaultSkills = [
  'Vue.js', 'React', 'TypeScript', 'Tailwind CSS', 'HTML5', 'CSS3',
  'Node.js', 'Express', 'Laravel', 'PHP', 'MySQL', 'MongoDB',
  'Git', 'Docker', 'AWS', 'CI/CD', 'Linux', 'Nginx'
]

// Enhanced collaboration modes with value propositions
const getEnhancedModes = computed(() => {
  if (!aboutSettings.value?.collaboration_modes) return []
  
  const valuePropositions = {
    'Project-Based': 'Clear deliverables, fast iterations, and full accountability. Perfect for teams that want results - not just reports.',
    'Retainer': 'Continuous optimization with dedicated leadership - more than just hours, it is a partnership for measurable progress every month.',
    'Consulting': 'Strategic guidance by someone who actually builds products. My advice is grounded in execution, not theory - so you get impact and momentum, not just a blueprint.'
  }
  
  return aboutSettings.value.collaboration_modes.map(mode => ({
    ...mode,
    value: valuePropositions[mode.mode] || 'Tailored approach designed for your specific business needs and growth objectives.'
  }))
})

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
    'zh': cnFlag,
    'sg': sgFlag
  }
  return codeMap[code] || idFlag
}

const getWhatsAppLink = () => {
  const phone = getSettingValue('contact_phone') || '+6281380163758'
  const defaultMessage = "Hi! I saw your portfolio and I'd like to discuss a project with you."
  const formattedPhone = phone.replace(/[^0-9]/g, '')
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

@keyframes pulse-slow {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.8;
    transform: scale(1.05);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animate-pulse-slow {
  animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

/* Prose styling for experience descriptions */
.prose :deep(p) {
  margin-bottom: 0.75rem;
}

.prose :deep(ul),
.prose :deep(ol) {
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
  padding-left: 1.5rem;
}

.prose :deep(li) {
  margin-bottom: 0.25rem;
}

.prose :deep(strong) {
  font-weight: 600;
  color: inherit;
}

.prose :deep(a) {
  color: rgb(var(--primary-600));
  text-decoration: underline;
}

.dark .prose :deep(a) {
  color: rgb(var(--primary-400));
}

/* Right-aligned prose */
.prose.text-right :deep(ul),
.prose.text-right :deep(ol) {
  padding-left: 0;
  padding-right: 1.5rem;
  text-align: right;
}

.prose.text-right :deep(li) {
  text-align: right;
}
</style>
