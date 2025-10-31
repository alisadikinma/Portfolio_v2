<template>
  <div class="min-h-screen">
    <!-- Hero Section - Clean & Professional -->
    <section
      v-if="showHeroSection"
      class="relative pt-32 pb-8 md:pt-40 md:pb-12 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950"
    >
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
            {{ heroName }} <br v-if="heroTitle" />
            <span v-if="heroTitle" class="text-gradient">{{ heroTitle }}</span>
          </h1>

          <!-- Subtitle -->
          <p class="text-xl md:text-2xl text-gray-600 dark:text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed animate-fade-in-up animate-delay-100">
            {{ heroBio }}
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

          <!-- Tech Stack / Skills -->
          <div v-if="heroSkills.length > 0" class="animate-fade-in-up animate-delay-300">
            <p class="text-sm text-gray-500 dark:text-gray-500 mb-4 uppercase tracking-wider">
              {{ aboutSettings?.skills ? 'Skills' : 'Tech Stack' }}
            </p>
            <div class="flex flex-wrap justify-center gap-3">
              <span
                v-for="skill in heroSkills.slice(0, 8)"
                :key="skill"
                class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
              >
                {{ skill }}
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
    <section
      v-if="showAwardsSection"
      class="py-20 bg-gray-50 dark:bg-gray-900"
    >
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
              <div v-if="award.image" class="w-16 h-16 rounded-xl overflow-hidden bg-gradient-to-br from-primary-400 to-secondary-400">
                <img :src="award.image" :alt="award.award_title" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-16 h-16 bg-gradient-to-br from-primary-400 to-secondary-400 rounded-xl flex items-center justify-center">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
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
                {{ stripHtml(award.description) }}
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

    <!-- Gallery Section -->
    <section
      v-if="showGallerySection"
      class="py-20 bg-white dark:bg-gray-950"
    >
      <div class="container-custom">
        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
          <p class="text-primary-600 dark:text-primary-400 font-semibold mb-2 uppercase tracking-wider text-sm">
            Visual Stories
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold text-gray-900 dark:text-white mb-4">
            Gallery
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            Capturing moments and showcasing creative work
          </p>
        </div>

        <!-- Loading State -->
        <BaseLoader v-if="galleriesLoading" text="Loading gallery..." />

        <!-- Gallery Grid -->
        <div v-else-if="galleries.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="gallery in galleries.slice(0, 6)"
            :key="gallery.id"
            class="group cursor-pointer card-elevated overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
            @click="openGalleryItemsModal(gallery)"
          >
            <!-- Gallery Thumbnail -->
            <div class="relative aspect-video bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/20 dark:to-secondary-900/20 overflow-hidden">
              <img
                v-if="gallery.thumbnail"
                :src="gallery.thumbnail"
                :alt="gallery.title"
                class="w-full h-full object-contain"
                loading="lazy"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

              <!-- View Button Overlay -->
              <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <span class="px-6 py-3 glass text-white font-semibold rounded-xl flex items-center gap-2">
                  View Gallery
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </span>
              </div>

              <!-- Items Count Badge -->
              <div class="absolute top-4 right-4 px-3 py-1 bg-black/70 backdrop-blur-sm text-white text-xs font-semibold rounded-lg">
                {{ gallery.items_count || 0 }} Photos
              </div>
            </div>

            <!-- Content -->
            <div class="p-6">
              <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ gallery.title }}
              </h3>
              <p v-if="gallery.description" class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2 mb-3">
                {{ stripHtml(gallery.description) }}
              </p>
              <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-500">
                <span v-if="gallery.company">{{ gallery.company }}</span>
                <span v-if="gallery.company && gallery.period">•</span>
                <span v-if="gallery.period">{{ gallery.period }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
          <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
          </svg>
          <p class="text-gray-500 dark:text-gray-400 mb-2">No galleries to display</p>
          <p class="text-xs text-gray-400">Check console for API response debug info</p>
        </div>

        <!-- View All Button -->
        <div v-if="galleries.length > 6" class="text-center mt-12">
          <button
            @click="$router.push('/gallery')"
            class="px-8 py-4 glass text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:shadow-lg transition-all duration-300"
          >
            View All Galleries
          </button>
        </div>
      </div>
    </section>

    <!-- Featured Projects - Modern Grid -->
    <section
      v-if="showFeaturedProjectsSection"
      class="py-20 bg-gray-50 dark:bg-gray-900"
    >
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
    <section
      v-if="showLatestBlogSection"
      class="py-20 bg-white dark:bg-gray-950"
    >
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
    <section
      v-if="showTestimonialsSection"
      class="py-20 bg-gray-50 dark:bg-gray-900"
    >
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

    <!-- Gallery Items Modal (for galleries section) -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showGalleryItemsModal && selectedGallery"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
          @click.self="closeGalleryItemsModal"
        >
          <div class="relative w-full max-w-6xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-800">
              <div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ selectedGallery.title }}
                </h3>
                <p class="text-sm text-primary-600 dark:text-primary-400 mt-1">
                  <span v-if="selectedGallery.company">{{ selectedGallery.company }}</span>
                  <span v-if="selectedGallery.company && selectedGallery.period"> • </span>
                  <span v-if="selectedGallery.period">{{ selectedGallery.period }}</span>
                </p>
              </div>
              <button
                @click="closeGalleryItemsModal"
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
              <div v-if="loadingGalleryItems" class="flex items-center justify-center py-20">
                <svg class="animate-spin h-12 w-12 text-primary-600" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>

              <!-- Gallery Grid -->
              <div v-else-if="galleryItems.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                  v-for="(item, index) in galleryItems"
                  :key="item.id"
                  class="relative group cursor-pointer aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800"
                  @click="openGalleryLightbox(index)"
                >
                  <img
                    :src="item.file_url || getImageUrl(item.file_path)"
                    :alt="item.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                    @error="handleImageError"
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
                <p class="mt-4 text-gray-500 dark:text-gray-400">No photos available in this gallery.</p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Lightbox for gallery items -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showGalleryLightbox"
          class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black"
          @click.self="closeGalleryLightbox"
        >
          <button
            @click="closeGalleryLightbox"
            class="absolute top-4 right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors z-10"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>

          <button
            v-if="currentGalleryPhotoIndex > 0"
            @click="previousGalleryPhoto"
            class="absolute left-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
          </button>

          <div class="max-w-6xl max-h-full">
            <img
              :src="galleryItems[currentGalleryPhotoIndex]?.file_url || getImageUrl(galleryItems[currentGalleryPhotoIndex]?.file_path)"
              :alt="galleryItems[currentGalleryPhotoIndex]?.title"
              class="max-w-full max-h-[90vh] object-contain"
            />
            <p v-if="galleryItems[currentGalleryPhotoIndex]?.title" class="text-center text-white mt-4 text-lg">
              {{ galleryItems[currentGalleryPhotoIndex].title }}
            </p>
          </div>

          <button
            v-if="currentGalleryPhotoIndex < galleryItems.length - 1"
            @click="nextGalleryPhoto"
            class="absolute right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>

          <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-sm">
            {{ currentGalleryPhotoIndex + 1 }} / {{ galleryItems.length }}
          </div>
        </div>
      </Transition>
    </Teleport>

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
                  v-for="(photo, index) in galleryPhotos"
                  :key="photo.id"
                  class="relative group cursor-pointer aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800"
                  @click="openLightbox(index)"
                >
                  <img
                    :src="photo.image"
                    :alt="photo.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                    @error="handleImageError"
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

    <!-- Lightbox for full image view -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showLightbox"
          class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black"
          @click.self="closeLightbox"
        >
          <button
            @click="closeLightbox"
            class="absolute top-4 right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors z-10"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>

          <button
            v-if="currentPhotoIndex > 0"
            @click="previousPhoto"
            class="absolute left-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
          </button>

          <div class="max-w-6xl max-h-full">
            <img
              :src="galleryPhotos[currentPhotoIndex]?.image"
              :alt="galleryPhotos[currentPhotoIndex]?.title"
              class="max-w-full max-h-[90vh] object-contain"
            />
            <p v-if="galleryPhotos[currentPhotoIndex]?.title" class="text-center text-white mt-4 text-lg">
              {{ galleryPhotos[currentPhotoIndex].title }}
            </p>
          </div>

          <button
            v-if="currentPhotoIndex < galleryPhotos.length - 1"
            @click="nextPhoto"
            class="absolute right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>

          <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-sm">
            {{ currentPhotoIndex + 1 }} / {{ galleryPhotos.length }}
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- CTA Section - Clean & Direct -->
    <section
      v-if="showCTASection"
      class="relative py-20 bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 overflow-hidden"
    >
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
import { ref, onMounted, computed } from 'vue'
import { useProjects } from '@/composables/useProjects'
import { usePosts } from '@/composables/usePosts'
import { useAwards } from '@/composables/useAwards'
import { useTestimonials } from '@/composables/useTestimonials'
import { useGallery } from '@/composables/useGallery'
import { useAboutSettings } from '@/composables/useAboutSettings'
import { usePageSections } from '@/composables/usePageSections'
import { BaseLoader } from '@/components/base'
import api from '@/services/api'

const { projects: featuredProjects, isLoading: projectsLoading, fetchProjects } = useProjects()
const { posts: latestPosts, isLoading: postsLoading, fetchPosts } = usePosts()
const { awards, isLoading: awardsLoading, fetchAwards } = useAwards()
const { testimonials, isLoading: testimonialsLoading, fetchTestimonials } = useTestimonials()
const { galleries, loading: galleriesLoading, fetchGalleries, fetchGalleryItems } = useGallery()
const { aboutSettings, loading: loadingAbout, heroName, heroTitle, heroBio, heroAvatar, heroSkills } = useAboutSettings()
const { sections, fetchActiveSections } = usePageSections()
const currentTestimonialIndex = ref(0)

// Section visibility computed properties
const showHeroSection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'hero')
  return section ? section.is_active : false // Default false - hide if not configured
})

const showFeaturedProjectsSection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'featured_projects')
  return section ? section.is_active : false
})

const showLatestBlogSection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'latest_blog')
  return section ? section.is_active : false
})

const showTestimonialsSection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'testimonials')
  return section ? section.is_active : false
})

const showAwardsSection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'awards')
  return section ? section.is_active : false // Default false
})

const showGallerySection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'gallery')
  return section ? section.is_active : false // Default false
})

const showCTASection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'cta')
  return section ? section.is_active : false
})

// Section order (sorted by sequence)
const orderedSections = computed(() => {
  return [...sections.value]
    .filter(s => s.page_type === 'homepage' && s.is_active)
    .sort((a, b) => a.sequence - b.sequence)
    .map(s => s.section_type)
})

// About settings now managed by useAboutSettings composable

const stats = ref([
  { value: '50+', label: 'Projects' },
  { value: '100+', label: 'Articles' },
  { value: '200+', label: 'Clients' },
  { value: '5+', label: 'Years' }
])

// fetchAboutSettings now managed by useAboutSettings composable

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

const stripHtml = (html) => {
  if (!html) return ''
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

// Gallery modal state (for awards)
const showGalleryModal = ref(false)
const selectedAward = ref(null)
const galleryPhotos = ref([])
const loadingGallery = ref(false)

// Gallery items modal state (for galleries section)
const showGalleryItemsModal = ref(false)
const selectedGallery = ref(null)
const galleryItems = ref([])
const loadingGalleryItems = ref(false)

// Lightbox state (for awards)
const showLightbox = ref(false)
const currentPhotoIndex = ref(0)

// Lightbox state (for galleries section)
const showGalleryLightbox = ref(false)
const currentGalleryPhotoIndex = ref(0)

const openGalleryModal = async (award) => {
  selectedAward.value = award
  showGalleryModal.value = true
  loadingGallery.value = true
  galleryPhotos.value = []

  try {
    const response = await api.get(`/awards/${award.id}/galleries`)
    if (response.data.success && response.data.data.galleries) {
      // Extract all photos from all galleries
      const allPhotos = []
      response.data.data.galleries.forEach(gallery => {
        if (gallery.items && gallery.items.length > 0) {
          allPhotos.push(...gallery.items)
        }
      })
      galleryPhotos.value = allPhotos
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

const openGalleryItemsModal = async (gallery) => {
  selectedGallery.value = gallery
  showGalleryItemsModal.value = true
  loadingGalleryItems.value = true
  galleryItems.value = []

  try {
    const items = await fetchGalleryItems(gallery.id)
    if (items && items.length > 0) {
      galleryItems.value = items
    }
  } catch (err) {
    console.error('Failed to load gallery items:', err)
  } finally {
    loadingGalleryItems.value = false
  }
}

const closeGalleryItemsModal = () => {
  showGalleryItemsModal.value = false
  selectedGallery.value = null
  galleryItems.value = []
}

const openGalleryLightbox = (index) => {
  currentGalleryPhotoIndex.value = index
  showGalleryLightbox.value = true
}

const closeGalleryLightbox = () => {
  showGalleryLightbox.value = false
}

const nextGalleryPhoto = () => {
  if (currentGalleryPhotoIndex.value < galleryItems.value.length - 1) {
    currentGalleryPhotoIndex.value++
  }
}

const previousGalleryPhoto = () => {
  if (currentGalleryPhotoIndex.value > 0) {
    currentGalleryPhotoIndex.value--
  }
}

const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/storage/' + path
}

const openLightbox = (index) => {
  currentPhotoIndex.value = index
  showLightbox.value = true
}

const closeLightbox = () => {
  showLightbox.value = false
}

const nextPhoto = () => {
  if (currentPhotoIndex.value < galleryPhotos.value.length - 1) {
    currentPhotoIndex.value++
  }
}

const previousPhoto = () => {
  if (currentPhotoIndex.value > 0) {
    currentPhotoIndex.value--
  }
}

const handleImageError = (event) => {
  console.error('[Home] Image failed to load:', event.target.src)
  event.target.src = 'https://via.placeholder.com/400x300/e5e7eb/6b7280?text=Image+Not+Found'
}

// Keyboard navigation for lightbox
const handleKeydown = (e) => {
  if (showGalleryLightbox.value) {
    if (e.key === 'ArrowRight') nextGalleryPhoto()
    if (e.key === 'ArrowLeft') previousGalleryPhoto()
    if (e.key === 'Escape') closeGalleryLightbox()
  } else if (showLightbox.value) {
    if (e.key === 'ArrowRight') nextPhoto()
    if (e.key === 'ArrowLeft') previousPhoto()
    if (e.key === 'Escape') closeLightbox()
  } else if (showGalleryItemsModal.value && e.key === 'Escape') {
    closeGalleryItemsModal()
  } else if (showGalleryModal.value && e.key === 'Escape') {
    closeGalleryModal()
  }
}

// Auto-rotate testimonials
const rotateTestimonials = () => {
  if (testimonials.value.length > 0) {
    currentTestimonialIndex.value = (currentTestimonialIndex.value + 1) % testimonials.value.length
  }
}

let testimonialInterval

onMounted(async () => {
  // Performance tracking START
  const startTime = performance.now()
  
  // Fetch page sections configuration
  console.log('🔄 Fetching page sections...')
  await fetchActiveSections('homepage')
  console.log('📋 Sections loaded:', sections.value)

  // About settings auto-loads via TanStack Query (instant with placeholderData)
  // No need to await - composable handles loading state automatically

  console.log('⏱️ Starting PARALLEL data fetch...')
  
  const fetchStart = performance.now()
  
  // PARALLEL fetch instead of sequential (FASTEST!)
  await Promise.all([
    fetchProjects({ featured: true, limit: 4 }),
    fetchPosts({ limit: 3 }),
    fetchAwards({ featured: true, limit: 6 }),
    fetchGalleries({ is_active: true, limit: 6 }).then(() => {
      console.log('🖼️ Galleries fetched:', galleries.value.length, 'items')
    }),
    fetchTestimonials({ featured: true, limit: 5 })
  ])
  
  const fetchTime = Math.round(performance.now() - fetchStart)
  console.log('✅ All data fetched in:', fetchTime + 'ms (parallel)')

  // Performance tracking END
  const endTime = performance.now()
  const loadTime = Math.round(endTime - startTime)
  
  console.group('📊 Homepage Performance')
  console.log('⏱️ Data Fetch Time:', loadTime + 'ms')
  console.log('✅ Projects:', projectsLoading.value ? 'Loading...' : 'Cached')
  console.log('✅ Posts:', postsLoading.value ? 'Loading...' : 'Cached')
  console.log('✅ Awards:', awardsLoading.value ? 'Loading...' : 'Cached')
  console.log('✅ Galleries:', galleriesLoading.value ? 'Loading...' : galleries.value.length + ' loaded')
  console.log('✅ Testimonials:', testimonialsLoading.value ? 'Loading...' : 'Cached')
  console.log('\n🎛️ Page Sections:')
  console.log('   Hero:', showHeroSection.value)
  console.log('   Featured Projects:', showFeaturedProjectsSection.value)
  console.log('   Latest Blog:', showLatestBlogSection.value)
  console.log('   Awards:', showAwardsSection.value)
  console.log('   Gallery:', showGallerySection.value)
  console.log('   Testimonials:', showTestimonialsSection.value)
  console.log('   CTA:', showCTASection.value)
  console.groupEnd()
  
  // Image performance on window load
  window.addEventListener('load', () => {
    const resources = performance.getEntriesByType('resource')
    const images = resources.filter(r => r.initiatorType === 'img')
    const cached = images.filter(r => r.transferSize === 0)
    
    console.group('🖼️ Image Loading')
    console.log('Total Images:', images.length)
    console.log('Cached Images:', cached.length)
    console.log('Downloaded:', images.length - cached.length)
    console.log('Avg Time:', Math.round(images.reduce((sum, img) => sum + img.duration, 0) / images.length) + 'ms')
    console.groupEnd()
  })

  // Start testimonial rotation
  testimonialInterval = setInterval(rotateTestimonials, 5000)
  
  // Add keyboard event listener
  window.addEventListener('keydown', handleKeydown)
})

// Cleanup on unmount
import { onUnmounted } from 'vue'
onUnmounted(() => {
  if (testimonialInterval) {
    clearInterval(testimonialInterval)
  }
  window.removeEventListener('keydown', handleKeydown)
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

/* Fade transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
