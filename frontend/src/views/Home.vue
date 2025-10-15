<template>
  <div class="min-h-screen">
    <!-- Hero Section - Clean & Professional -->
    <section class="relative pt-32 pb-8 md:pt-40 md:pb-12 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
      <!-- Subtle Background Pattern -->
      <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #6366f1 1px, transparent 1px); background-size: 30px 30px;"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-4xl mx-auto text-center">
          <!-- Badge -->
          <div class="inline-flex items-center gap-2 px-4 py-2 glass rounded-full mb-6 animate-fade-in-down">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-500"></span>
            </span>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
              Available for Freelance Work
            </span>
          </div>

          <!-- Main Heading -->
          <h1 class="text-5xl md:text-6xl lg:text-7xl font-display font-bold text-gray-900 dark:text-white mb-6 leading-tight animate-fade-in-up">
            Creative Developer & <br />
            <span class="text-gradient">Digital Designer</span>
          </h1>

          <!-- Subtitle -->
          <p class="text-xl md:text-2xl text-gray-600 dark:text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed animate-fade-in-up animate-delay-100">
            I craft exceptional digital experiences through modern design
            and innovative solutions that drive real results.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-wrap justify-center gap-4 mb-16 animate-fade-in-up animate-delay-200">
            <button
              @click="$router.push('/projects')"
              class="group px-8 py-4 bg-gradient-to-r from-primary-600 to-secondary-600 text-white font-semibold rounded-xl hover:shadow-xl hover:scale-105 transition-all duration-300"
            >
              <span class="flex items-center gap-2">
                View My Work
                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
              </span>
            </button>
            <button
              @click="$router.push('/contact')"
              class="px-8 py-4 glass text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:shadow-lg transition-all duration-300"
            >
              Let's Talk
            </button>
          </div>

          <!-- Tech Stack -->
          <div class="animate-fade-in-up animate-delay-300">
            <p class="text-sm text-gray-500 dark:text-gray-500 mb-4 uppercase tracking-wider">
              Tech Stack
            </p>
            <div class="flex flex-wrap justify-center gap-3">
              <span
                v-for="tech in techStack"
                :key="tech"
                class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
              >
                {{ tech }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Section - Clean Cards -->
    <section class="py-12 bg-white dark:bg-gray-950">
      <div class="container-custom">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div
            v-for="(stat, index) in stats"
            :key="stat.label"
            class="card-elevated p-8 text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
            :class="`animate-fade-in-up animate-delay-${index * 100}`"
          >
            <div class="text-4xl md:text-5xl font-bold text-gradient mb-2">
              {{ stat.value }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400 uppercase tracking-wider">
              {{ stat.label }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Awards & Recognition Section -->
    <section class="py-20 bg-gray-50 dark:bg-gray-900">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
          <p class="text-accent-600 dark:text-accent-400 font-semibold mb-2 uppercase tracking-wider text-sm">
            Recognition & Achievements
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold text-gray-900 dark:text-white mb-4">
            Awards & Honors
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            Celebrating milestones and industry recognition
          </p>
        </div>

        <BaseLoader v-if="awardsLoading" text="Loading awards..." />

        <!-- Awards Grid -->
        <div v-else-if="awards.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="award in awards"
            :key="award.id"
            class="award-card group relative bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-xl transition-all duration-300"
          >
            <!-- Award Icon/Image -->
            <div class="relative mb-6">
              <div v-if="award.image" class="relative w-16 h-16 rounded-xl overflow-hidden bg-gradient-to-br from-primary-400 to-secondary-400">
                <img :src="award.image" :alt="award.award_title" class="w-full h-full object-cover" />
                <!-- Award Label -->
                <div class="absolute top-1 left-1 right-1 bg-purple-600/90 backdrop-blur-sm rounded-md px-2 py-0.5">
                  <span class="text-[10px] text-white font-semibold leading-tight line-clamp-2">
                    {{ award.award_title.split(' ').slice(0, 3).join(' ') }}
                  </span>
                </div>
              </div>
              <div v-else class="relative w-16 h-16 bg-gradient-to-br from-primary-400 to-secondary-400 rounded-xl flex items-center justify-center">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
                <!-- Award Label -->
                <div class="absolute top-1 left-1 right-1 bg-purple-600/90 backdrop-blur-sm rounded-md px-2 py-0.5">
                  <span class="text-[10px] text-white font-semibold leading-tight line-clamp-2">
                    {{ award.award_title.split(' ').slice(0, 3).join(' ') }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Award Info -->
            <div class="mb-6">
              <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ award.award_title }}
              </h3>
              <p class="text-sm text-purple-600 dark:text-purple-400 font-semibold mb-3 uppercase tracking-wide">
                {{ award.issuing_organization }} • {{ formatYear(award.award_date) }}
              </p>
              <p v-if="award.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-3 mb-4">
                {{ award.description }}
              </p>

              <!-- Credential Info -->
              <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-500 mb-4">
                <div v-if="award.credential_id" class="flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                  ID: {{ award.credential_id }}
                </div>
                <div v-if="award.total_photos > 0" class="flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                  {{ award.total_photos }} {{ award.total_photos === 1 ? 'Photo' : 'Photos' }}
                </div>
              </div>
            </div>

            <!-- Action Button -->
            <button
              v-if="award.total_photos > 0"
              @click="openGalleryModal(award)"
              class="w-full px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg transition-colors duration-300 flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
              VIEW GALLERY
            </button>
          </div>
        </div>

        <div v-else class="text-center py-12">
          <p class="text-gray-500 dark:text-gray-400">No awards to display yet.</p>
        </div>
      </div>
    </section>

    <!-- Featured Projects - Modern Grid -->
    <section class="py-20 bg-gray-50 dark:bg-gray-900">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
          <p class="text-primary-600 dark:text-primary-400 font-semibold mb-2 uppercase tracking-wider text-sm">
            Featured Work
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold text-gray-900 dark:text-white mb-4">
            Selected Projects
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            Showcasing innovative solutions and creative implementations
          </p>
        </div>

        <BaseLoader v-if="projectsLoading" text="Loading projects..." />

        <!-- Projects Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div
            v-for="(project, index) in featuredProjects"
            :key="project.id"
            @click="$router.push(`/projects/${project.slug}`)"
            class="group cursor-pointer card-elevated overflow-hidden hover:shadow-2xl hover:-translate-y-2 transition-all duration-300"
          >
            <!-- Project Image -->
            <div class="relative aspect-video bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/20 dark:to-secondary-900/20 overflow-hidden">
              <img
                v-if="project.featured_image"
                :src="project.featured_image"
                :alt="project.title"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

              <!-- View Button Overlay -->
              <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <span class="px-6 py-3 glass text-white font-semibold rounded-xl flex items-center gap-2">
                  View Project
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                  </svg>
                </span>
              </div>
            </div>

            <!-- Content -->
            <div class="p-6">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ project.title }}
              </h3>
              <p class="text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                {{ project.description }}
              </p>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="tech in project.technologies?.slice(0, 4)"
                  :key="tech"
                  class="px-3 py-1 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 rounded-lg text-xs font-semibold"
                >
                  {{ tech }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-12">
          <button
            @click="$router.push('/projects')"
            class="px-8 py-4 glass text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:shadow-lg transition-all duration-300"
          >
            View All Projects
          </button>
        </div>
      </div>
    </section>

    <!-- Latest Blog -->
    <section class="py-20 bg-white dark:bg-gray-950">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
          <p class="text-secondary-600 dark:text-secondary-400 font-semibold mb-2 uppercase tracking-wider text-sm">
            Insights & Articles
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold text-gray-900 dark:text-white mb-4">
            Latest Thoughts
          </h2>
        </div>

        <BaseLoader v-if="postsLoading" text="Loading posts..." />

        <!-- Posts Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div
            v-for="post in latestPosts"
            :key="post.id"
            @click="$router.push(`/blog/${post.slug}`)"
            class="group cursor-pointer card-elevated overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col"
          >
            <!-- Image -->
            <div class="relative aspect-video bg-gradient-to-br from-secondary-100 to-accent-100 dark:from-secondary-900/20 dark:to-accent-900/20">
              <img
                v-if="post.featured_image"
                :src="post.featured_image"
                :alt="post.title"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
              />
            </div>

            <!-- Content -->
            <div class="p-6 flex-1 flex flex-col">
              <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 bg-secondary-50 dark:bg-secondary-900/20 text-secondary-700 dark:text-secondary-300 rounded-lg text-xs font-semibold uppercase">
                  {{ post.category?.name }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-500">
                  {{ formatDate(post.published_at) }}
                </span>
              </div>

              <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-secondary-600 dark:group-hover:text-secondary-400 transition-colors line-clamp-2">
                {{ post.title }}
              </h3>

              <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3 flex-1 mb-4">
                {{ post.excerpt }}
              </p>

              <div class="flex items-center text-secondary-600 dark:text-secondary-400 font-semibold text-sm">
                <span>Read Article</span>
                <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-12">
          <button
            @click="$router.push('/blog')"
            class="px-8 py-4 glass text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:shadow-lg transition-all duration-300"
          >
            Read All Articles
          </button>
        </div>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20 bg-gray-50 dark:bg-gray-900">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="max-w-2xl mb-16 text-center mx-auto">
          <p class="text-accent-600 dark:text-accent-400 font-semibold mb-2 uppercase tracking-wider text-sm">
            Client Feedback
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold text-gray-900 dark:text-white mb-4">
            What People Say
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            Trusted by amazing clients worldwide
          </p>
        </div>

        <BaseLoader v-if="testimonialsLoading" text="Loading testimonials..." />

        <!-- Testimonials Carousel -->
        <div v-else-if="testimonials.length > 0" class="max-w-4xl mx-auto">
          <div class="relative">
            <!-- Testimonial Card -->
            <div class="card-elevated p-8 md:p-12 text-center">
              <!-- Quote Icon -->
              <div class="flex justify-center mb-6">
                <div class="w-16 h-16 bg-gradient-to-br from-accent-500 to-secondary-500 rounded-full flex items-center justify-center">
                  <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                  </svg>
                </div>
              </div>

              <!-- Stars -->
              <div class="flex justify-center gap-1 mb-6">
                <svg
                  v-for="star in 5"
                  :key="star"
                  :class="star <= testimonials[currentTestimonialIndex].star_rating ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600'"
                  class="w-6 h-6"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
              </div>

              <!-- Testimonial Text -->
              <p class="text-xl md:text-2xl text-gray-700 dark:text-gray-300 mb-8 leading-relaxed italic">
                "{{ testimonials[currentTestimonialIndex].testimonial_text }}"
              </p>

              <!-- Client Info -->
              <div class="flex items-center justify-center gap-4">
                <img
                  v-if="testimonials[currentTestimonialIndex].client_photo"
                  :src="testimonials[currentTestimonialIndex].client_photo"
                  :alt="testimonials[currentTestimonialIndex].client_name"
                  class="w-16 h-16 rounded-full object-cover border-4 border-white dark:border-gray-700"
                />
                <div class="flex-shrink-0 w-16 h-16 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-full flex items-center justify-center border-4 border-white dark:border-gray-700" v-else>
                  <span class="text-2xl font-bold text-white">
                    {{ testimonials[currentTestimonialIndex].client_name.charAt(0) }}
                  </span>
                </div>
                <div class="text-left">
                  <p class="font-bold text-gray-900 dark:text-white text-lg">
                    {{ testimonials[currentTestimonialIndex].client_name }}
                  </p>
                  <p class="text-gray-600 dark:text-gray-400 text-sm">
                    {{ testimonials[currentTestimonialIndex].client_company }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Navigation Dots -->
            <div class="flex justify-center gap-2 mt-8">
              <button
                v-for="(testimonial, index) in testimonials"
                :key="testimonial.id"
                @click="currentTestimonialIndex = index"
                :class="[
                  'w-3 h-3 rounded-full transition-all duration-300',
                  index === currentTestimonialIndex
                    ? 'bg-accent-600 w-8'
                    : 'bg-gray-300 dark:bg-gray-600 hover:bg-accent-400'
                ]"
                :aria-label="`View testimonial ${index + 1}`"
              ></button>
            </div>

            <!-- Navigation Arrows -->
            <button
              @click="currentTestimonialIndex = currentTestimonialIndex > 0 ? currentTestimonialIndex - 1 : testimonials.length - 1"
              class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-12 w-12 h-12 bg-white dark:bg-gray-800 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center text-gray-700 dark:text-gray-300 hover:text-accent-600 dark:hover:text-accent-400"
              aria-label="Previous testimonial"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
              </svg>
            </button>
            <button
              @click="currentTestimonialIndex = (currentTestimonialIndex + 1) % testimonials.length"
              class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-12 w-12 h-12 bg-white dark:bg-gray-800 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center text-gray-700 dark:text-gray-300 hover:text-accent-600 dark:hover:text-accent-400"
              aria-label="Next testimonial"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
          </div>
        </div>

        <div v-else class="text-center py-12">
          <p class="text-gray-500 dark:text-gray-400">No testimonials to display yet.</p>
        </div>
      </div>
    </section>

    <!-- Gallery Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showGalleryModal && selectedAward"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
          @click.self="closeGalleryModal"
        >
          <div class="relative w-full max-w-6xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-800">
              <div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ selectedAward.award_title }}
                </h3>
                <p class="text-sm text-purple-600 dark:text-purple-400 mt-1">
                  {{ selectedAward.issuing_organization }} • {{ formatYear(selectedAward.award_date) }}
                </p>
              </div>
              <button
                @click="closeGalleryModal"
                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
              >
                <svg class="w-6 h-6 text-gray-400 hover:text-gray-900 dark:hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>

            <!-- Modal Body - Gallery Grid -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
              <BaseLoader v-if="loadingGallery" text="Loading gallery..." class="py-20" />

              <div v-else-if="galleryPhotos.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                  v-for="photo in galleryPhotos"
                  :key="photo.id"
                  class="relative group cursor-pointer aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800"
                >
                  <img
                    :src="photo.image"
                    :alt="photo.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                  />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="absolute bottom-0 left-0 right-0 p-3">
                      <p class="text-white text-sm font-semibold truncate">
                        {{ photo.title }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-20">
                <p class="text-gray-400">No photos available in this gallery.</p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- CTA Section - Clean & Direct -->
    <section class="relative py-20 bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 overflow-hidden">
      <!-- Subtle Pattern -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 30px 30px;"></div>
      </div>

      <div class="container-custom text-center relative z-10">
        <h2 class="text-4xl md:text-5xl font-display font-bold text-white mb-6">
          Let's Build Something Great
        </h2>
        <p class="text-xl text-white/90 mb-10 max-w-2xl mx-auto">
          Ready to start your next project? Let's create something extraordinary together.
        </p>
        <button
          @click="$router.push('/contact')"
          class="px-10 py-5 bg-white text-primary-600 font-bold text-lg rounded-xl hover:shadow-2xl hover:scale-105 transition-all duration-300"
        >
          Start a Project
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useProjects } from '@/composables/useProjects'
import { usePosts } from '@/composables/usePosts'
import { useAwards } from '@/composables/useAwards'
import { useTestimonials } from '@/composables/useTestimonials'
import { BaseLoader } from '@/components/base'
import api from '@/services/api'

const { projects: featuredProjects, isLoading: projectsLoading, fetchProjects } = useProjects()
const { posts: latestPosts, isLoading: postsLoading, fetchPosts } = usePosts()
const { awards, isLoading: awardsLoading, fetchAwards } = useAwards()
const { testimonials, isLoading: testimonialsLoading, fetchTestimonials } = useTestimonials()
const currentTestimonialIndex = ref(0)

const stats = [
  { value: '50+', label: 'Projects' },
  { value: '100+', label: 'Articles' },
  { value: '200+', label: 'Clients' },
  { value: '5+', label: 'Years' }
]

const techStack = [
  'Vue.js', 'React', 'Laravel', 'Node.js', 'TypeScript', 'TailwindCSS', 'MySQL', 'Docker'
]

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatYear = (date) => {
  if (!date) return ''
  return new Date(date).getFullYear()
}

// Gallery modal state
const showGalleryModal = ref(false)
const selectedAward = ref(null)
const galleryPhotos = ref([])
const loadingGallery = ref(false)

const openGalleryModal = async (award) => {
  selectedAward.value = award
  showGalleryModal.value = true
  loadingGallery.value = true
  galleryPhotos.value = []

  try {
    const response = await api.get(`/awards/${award.id}/galleries`)
    if (response.data.success && response.data.data.galleries) {
      galleryPhotos.value = response.data.data.galleries
    }
  } catch (err) {
    console.error('Failed to load gallery:', err)
  } finally {
    loadingGallery.value = false
  }
}

const closeGalleryModal = () => {
  showGalleryModal.value = false
  selectedAward.value = null
  galleryPhotos.value = []
}

// Auto-rotate testimonials
const rotateTestimonials = () => {
  if (testimonials.value.length > 0) {
    currentTestimonialIndex.value = (currentTestimonialIndex.value + 1) % testimonials.value.length
  }
}

let testimonialInterval

onMounted(async () => {
  await fetchProjects({ featured: true, limit: 4 })
  await fetchPosts({ limit: 3 })
  await fetchAwards({ featured: true, limit: 6 })
  await fetchTestimonials({ featured: true, limit: 5 })
  
  // Start testimonial rotation
  testimonialInterval = setInterval(rotateTestimonials, 5000)
})

// Cleanup on unmount
import { onUnmounted } from 'vue'
onUnmounted(() => {
  if (testimonialInterval) {
    clearInterval(testimonialInterval)
  }
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.award-card {
  transition: all 0.3s ease;
}

.award-card:hover {
  transform: translateY(-4px);
}

/* Modal transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
