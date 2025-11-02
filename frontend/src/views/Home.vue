<template>
  <div class="min-h-screen">
    <!-- Hero Section - Clean & Professional -->
    <section
      v-if="showHeroSection"
      class="relative pt-20 pb-8 md:pt-24 md:pb-12 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950"
    >
      <!-- Subtle Background Pattern -->
      <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #6366f1 1px, transparent 1px); background-size: 30px 30px;"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-6xl mx-auto">
          <!-- Grid Layout: Photo + Content -->
          <div class="grid md:grid-cols-2 gap-8 items-center">
            <!-- Left: Profile Photo -->
            <div class="order-2 md:order-1">
              <div class="relative">
                <!-- Photo Container -->
                <div class="relative w-full max-w-md mx-auto aspect-square rounded-2xl overflow-hidden bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/20 dark:to-secondary-900/20 shadow-2xl">
                  <img 
                    v-if="heroAvatar" 
                    :src="getProfilePhotoUrl(heroAvatar)" 
                    :alt="heroName" 
                    class="w-full h-full object-cover"
                    @error="handleAvatarError"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    <svg class="w-32 h-32 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                </div>
                
                <!-- Decorative Elements -->
                <div class="absolute -top-4 -right-4 w-24 h-24 bg-accent-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-6 -left-6 w-32 h-32 bg-primary-500/10 rounded-full blur-3xl"></div>
              </div>
            </div>

            <!-- Right: Content -->
            <div class="order-1 md:order-2 text-center md:text-left">
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
              <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold text-gray-900 dark:text-white mb-4 leading-tight animate-fade-in-up">
                {{ heroName }}
              </h1>
              
              <h2 v-if="heroTitle" class="text-2xl md:text-3xl font-display font-bold text-gradient mb-6 animate-fade-in-up animate-delay-100">
                {{ heroTitle }}
              </h2>

              <!-- Subtitle -->
              <p class="text-base md:text-lg text-gray-600 dark:text-gray-400 mb-6 leading-relaxed animate-fade-in-up animate-delay-200">
                {{ heroBio }}
              </p>

              <!-- CTA Buttons -->
              <div class="flex flex-wrap gap-3 mb-6 animate-fade-in-up animate-delay-300 justify-center md:justify-start">
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

              <!-- Tech Stack / Skills - RESPONSIVE (MAX 2 LINES) -->
              <div v-if="heroSkills.length > 0" class="animate-fade-in-up animate-delay-400">
                <p class="text-xs text-gray-500 dark:text-gray-500 mb-2 uppercase tracking-wider font-semibold">
                  SKILLS
                </p>
                <!-- Mobile: Max 3 skills + See More -->
                <div class="md:hidden">
                  <div class="flex flex-wrap gap-1.5 justify-center">
                    <span
                      v-for="skill in (showAllSkills ? heroSkills : heroSkills.slice(0, 3))"
                      :key="skill"
                      class="px-2.5 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded text-xs font-medium text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
                    >
                      {{ skill }}
                    </span>
                  </div>
                  <button
                    v-if="heroSkills.length > 3 && !showAllSkills"
                    @click="showAllSkills = true"
                    class="mt-2 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold"
                  >
                    See More ({{ heroSkills.length - 3 }}+)
                  </button>
                </div>
                <!-- Desktop: Max 6 skills (2 lines max) + See More -->
                <div class="hidden md:block">
                  <div class="flex flex-wrap gap-1.5 justify-start max-h-[52px] overflow-hidden">
                    <span
                      v-for="skill in (showAllSkills ? heroSkills : heroSkills.slice(0, 6))"
                      :key="skill"
                      class="px-2.5 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded text-xs font-medium text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
                    >
                      {{ skill }}
                    </span>
                  </div>
                  <button
                    v-if="heroSkills.length > 6 && !showAllSkills"
                    @click="showAllSkills = true"
                    class="mt-2 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold"
                  >
                    See More ({{ heroSkills.length - 6 }}+)
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Section - Clean Cards -->
    <section v-if="stats.length > 0" class="py-8 bg-white dark:bg-gray-950">
      <div class="container-custom">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
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

    <!-- Awards & Recognition Section - 3D COVERFLOW + MAGNETIC -->
    <section
      v-if="showAwardsSection"
      class="py-20 bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden"
    >
      <!-- Animated background particles -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute w-96 h-96 bg-purple-500/20 rounded-full blur-3xl top-0 -left-20 animate-blob"></div>
        <div class="absolute w-96 h-96 bg-pink-500/20 rounded-full blur-3xl top-0 -right-20 animate-blob animation-delay-2000"></div>
        <div class="absolute w-96 h-96 bg-blue-500/20 rounded-full blur-3xl -bottom-20 left-1/2 animate-blob animation-delay-4000"></div>
      </div>

      <div class="container-custom relative z-10">
        <!-- Section Header -->
        <div class="text-center mb-16">
          <p class="text-purple-400 font-semibold mb-3 uppercase tracking-wider text-sm flex items-center justify-center gap-2">
            <svg class="w-5 h-5 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
            Recognition & Achievements
          </p>
          <h2 class="text-5xl md:text-6xl font-display font-bold text-white mb-4">
            Awards & <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-pink-400 to-purple-400 animate-gradient">Honors</span>
          </h2>
          <p class="text-xl text-gray-300 max-w-2xl mx-auto">
            Celebrating milestones and industry recognition
          </p>
        </div>

        <BaseLoader v-if="awardsLoading" text="Loading awards..." />

        <!-- 3D Coverflow Carousel -->
        <div v-else-if="awards.length > 0" class="relative">
          <!-- Carousel container with perspective -->
          <div 
            ref="carouselContainer"
            class="relative h-[600px] md:h-[650px]"
            style="perspective: 2000px"
            @mousemove="handleMouseMove"
            @mouseleave="handleMouseLeave"
            @touchstart="handleTouchStart"
            @touchmove="handleTouchMove"
            @touchend="handleTouchEnd"
          >
            <div class="absolute inset-0 flex items-center justify-center">
              <!-- Award Cards in 3D -->
              <div
                v-for="(award, index) in awards"
                :key="award.id"
                class="award-3d-card absolute transition-all duration-700 ease-out"
                :class="{
                  'award-active': index === activeAwardIndex,
                  'award-prev': index < activeAwardIndex,
                  'award-next': index > activeAwardIndex
                }"
                :style="getCardStyle(index)"
              >
                <!-- Card with glass morphism -->
                <div 
                  class="relative w-[320px] md:w-[380px] h-[500px] md:h-[550px] rounded-2xl overflow-hidden backdrop-blur-xl border shadow-2xl group"
                  :class="[
                    index === activeAwardIndex 
                      ? 'bg-white/60 border-white/50' 
                      : 'bg-white/20 border-white/25'
                  ]"
                >
                  <!-- Gradient overlay -->
                  <div class="absolute inset-0 bg-gradient-to-br from-purple-500/20 via-transparent to-pink-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                  
                  <!-- Content -->
                  <div class="relative z-10 p-8 h-full flex flex-col">
                    <!-- Top section -->
                    <div class="flex items-start justify-between mb-6">
                      <!-- Award Icon with glow -->
                      <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-2xl blur-xl opacity-60 group-hover:opacity-100 transition-opacity animate-pulse"></div>
                        <div v-if="award.image" class="relative w-20 h-20 rounded-2xl overflow-hidden shadow-2xl transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                          <img :src="award.image" :alt="award.award_title" class="w-full h-full object-cover" />
                        </div>
                        <div v-else class="relative w-20 h-20 bg-gradient-to-br from-yellow-400 via-orange-500 to-red-500 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                          <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                          </svg>
                        </div>
                      </div>

                      <!-- Year badge -->
                      <div class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full shadow-lg">
                        <span class="text-white font-bold text-sm">{{ formatYear(award.award_date) }}</span>
                      </div>
                    </div>

                    <!-- Award title -->
                    <h3 
                      class="text-2xl font-bold mb-3 line-clamp-2 transition-colors"
                      :class="[
                        index === activeAwardIndex
                          ? 'text-gray-900 hover:text-purple-600'
                          : 'text-white hover:text-purple-300'
                      ]"
                    >
                      {{ award.award_title }}
                    </h3>

                    <!-- Organization -->
                    <div class="flex items-center gap-2 mb-4 pb-4 border-b border-white/10">
                      <svg 
                        class="w-5 h-5" 
                        :class="index === activeAwardIndex ? 'text-purple-600' : 'text-purple-400'"
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                      </svg>
                      <p 
                        class="font-semibold text-sm uppercase tracking-wide"
                        :class="index === activeAwardIndex ? 'text-purple-700' : 'text-purple-300'"
                      >
                        {{ award.issuing_organization }}
                      </p>
                    </div>

                    <!-- Description -->
                    <p 
                      v-if="award.description" 
                      class="text-sm leading-relaxed line-clamp-3 mb-6 flex-1"
                      :class="index === activeAwardIndex ? 'text-gray-700' : 'text-gray-300'"
                    >
                      {{ stripHtml(award.description) }}
                    </p>

                    <!-- Bottom badges -->
                    <div class="flex items-center gap-3 mb-4">
                      <div v-if="award.credential_id" class="flex items-center gap-1.5 px-3 py-1.5 bg-white/10 rounded-lg backdrop-blur-sm">
                        <svg 
                          class="w-4 h-4" 
                          :class="index === activeAwardIndex ? 'text-purple-600' : 'text-purple-400'"
                          fill="none" 
                          stroke="currentColor" 
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <span 
                          class="text-xs font-medium"
                          :class="index === activeAwardIndex ? 'text-gray-900' : 'text-white'"
                        >
                          {{ award.credential_id }}
                        </span>
                      </div>
                      <div v-if="award.total_photos > 0" class="flex items-center gap-1.5 px-3 py-1.5 bg-purple-500/30 rounded-lg backdrop-blur-sm">
                        <svg 
                          class="w-4 h-4" 
                          :class="index === activeAwardIndex ? 'text-purple-600' : 'text-purple-300'"
                          fill="none" 
                          stroke="currentColor" 
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span 
                          class="text-xs font-semibold"
                          :class="index === activeAwardIndex ? 'text-gray-900' : 'text-white'"
                        >
                          {{ award.total_photos }} Photos
                        </span>
                      </div>
                    </div>

                    <!-- View button -->
                    <button
                      v-if="award.total_photos > 0"
                      @click="openGalleryModal(award)"
                      class="w-full relative overflow-hidden px-6 py-4 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 rounded-xl font-bold text-white shadow-xl shadow-purple-500/50 hover:shadow-2xl hover:shadow-purple-500/70 transition-all duration-300 transform hover:scale-105 flex items-center justify-center gap-2 group"
                    >
                      <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                      <svg class="w-5 h-5 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                      </svg>
                      <span class="relative z-10">VIEW GALLERY</span>
                    </button>
                  </div>

                  <!-- Reflection effect -->
                  <div class="absolute inset-0 bg-gradient-to-t from-white/5 to-transparent pointer-events-none"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Navigation arrows -->
          <button
            v-if="awards.length > 1"
            @click="previousAward"
            class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-14 h-14 bg-white/10 backdrop-blur-xl rounded-full flex items-center justify-center text-white hover:bg-white/20 transition-all hover:scale-110 shadow-xl"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"></path>
            </svg>
          </button>
          <button
            v-if="awards.length > 1"
            @click="nextAward"
            class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-14 h-14 bg-white/10 backdrop-blur-xl rounded-full flex items-center justify-center text-white hover:bg-white/20 transition-all hover:scale-110 shadow-xl"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>

          <!-- Dots indicator -->
          <div class="flex justify-center gap-2 mt-8">
            <button
              v-for="(award, index) in awards"
              :key="`dot-${award.id}`"
              @click="activeAwardIndex = index"
              class="transition-all duration-300"
              :class="[
                index === activeAwardIndex 
                  ? 'w-8 h-3 bg-gradient-to-r from-purple-500 to-pink-500' 
                  : 'w-3 h-3 bg-white/30 hover:bg-white/50'
              ]"
              style="border-radius: 9999px"
            ></button>
          </div>
        </div>

        <div v-else class="text-center py-20">
          <svg class="w-20 h-20 mx-auto text-white/20 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
          </svg>
          <p class="text-white/60 text-lg">No awards to display yet.</p>
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

        <div v-else-if="galleries.length > 0">
          <!-- Gallery Carousel (Mobile) -->
          <div class="md:hidden">
            <MobileCarousel :items="galleries.slice(0, 6)" :show-arrows="true" :show-dots="true">
              <template #default="{ item }">
              <div
                class="group cursor-pointer card-elevated overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 mx-1"
                @click="openGalleryItemsModal(item)"
              >
                <!-- Gallery Thumbnail -->
                <div class="relative aspect-video bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/20 dark:to-secondary-900/20 overflow-hidden">
                  <img
                    v-if="item.thumbnail"
                    :src="item.thumbnail"
                    :alt="item.title"
                    class="w-full h-full object-contain"
                    loading="lazy"
                  />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                  <!-- Items Count Badge -->
                  <div class="absolute top-4 right-4 px-3 py-1 bg-black/70 backdrop-blur-sm text-white text-xs font-semibold rounded-lg">
                    {{ item.items_count || 0 }} Photos
                  </div>
                </div>

                <!-- Content -->
                <div class="p-6">
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                    {{ item.title }}
                  </h3>
                  <p v-if="item.description" class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2 mb-3">
                    {{ stripHtml(item.description) }}
                  </p>
                  <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-500">
                    <span v-if="item.company">{{ item.company }}</span>
                    <span v-if="item.company && item.period">•</span>
                    <span v-if="item.period">{{ item.period }}</span>
                  </div>
                </div>
              </div>
            </template>
          </MobileCarousel>
        </div>

          <!-- Gallery Grid (Desktop) -->
          <div class="hidden md:grid grid-cols-2 lg:grid-cols-3 gap-6">
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

        <div v-else>
          <!-- Projects Carousel (Mobile) -->
          <div class="md:hidden">
            <MobileCarousel :items="featuredProjects.slice(0, 6)" :show-arrows="true" :show-dots="true">
              <template #default="{ item }">
              <div
                @click="$router.push(`/projects/${item.slug}`)"
                class="group relative cursor-pointer bg-white dark:bg-gray-800 rounded-2xl overflow-hidden hover:shadow-2xl transition-all duration-500 mx-1"
              >
                <!-- Premium Badge -->
                <div class="absolute top-4 left-4 z-10 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold rounded-full shadow-lg flex items-center gap-1.5">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                  FEATURED
                </div>

                <!-- Image Section with Parallax Effect -->
                <div class="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-gray-900 dark:to-gray-800">
                  <div class="absolute inset-0 bg-gradient-to-br from-primary-500/10 to-secondary-500/10 group-hover:opacity-0 transition-opacity duration-500"></div>
                  <img
                    v-if="item.featured_image"
                    :src="item.featured_image"
                    :alt="item.title"
                    class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700 ease-out"
                  />
                  <!-- Gradient Overlay -->
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent opacity-60 group-hover:opacity-90 transition-opacity duration-300"></div>
                  
                  <!-- Hover CTA -->
                  <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 transform group-hover:scale-100 scale-95">
                    <div class="px-6 py-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm rounded-xl shadow-2xl flex items-center gap-2 border border-white/20">
                      <span class="text-primary-600 dark:text-primary-400 font-bold text-sm">View Case Study</span>
                      <svg class="w-5 h-5 text-primary-600 dark:text-primary-400 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- Content Section -->
                <div class="p-6 space-y-4">
                  <!-- Title -->
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">
                    {{ item.title }}
                  </h3>
                  
                  <!-- Description -->
                  <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 leading-relaxed">
                    {{ item.description }}
                  </p>
                  
                  <!-- Tech Stack -->
                  <div class="flex flex-wrap gap-2 pt-2">
                    <span
                      v-for="tech in item.technologies?.slice(0, 3)"
                      :key="tech"
                      class="px-3 py-1.5 bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/20 dark:to-secondary-900/20 text-primary-700 dark:text-primary-300 rounded-lg text-xs font-semibold border border-primary-100 dark:border-primary-800/50 hover:border-primary-300 dark:hover:border-primary-600 transition-colors"
                    >
                      {{ tech }}
                    </span>
                    <span
                      v-if="item.technologies?.length > 3"
                      class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-xs font-semibold"
                    >
                      +{{ item.technologies.length - 3 }}
                    </span>
                  </div>
                </div>

                <!-- Bottom Glow Effect -->
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-500 via-secondary-500 to-accent-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
              </div>
            </template>
          </MobileCarousel>
        </div>

          <!-- Projects Grid (Desktop) -->
          <div class="hidden md:grid grid-cols-3 gap-6">
          <div
            v-for="(project, index) in featuredProjects.slice(0, 6)"
            :key="project.id"
            @click="$router.push(`/projects/${project.slug}`)"
            class="group relative cursor-pointer bg-white dark:bg-gray-800 rounded-xl overflow-hidden hover:shadow-2xl transition-all duration-500 hover:-translate-y-2"
          >
            <!-- Premium Badge -->
            <div class="absolute top-4 left-4 z-10 px-3 py-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold rounded-full shadow-lg flex items-center gap-1.5">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              FEATURED
            </div>

            <!-- Image Section -->
            <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-gray-900 dark:to-gray-800">
              <div class="absolute inset-0 bg-gradient-to-br from-primary-500/10 to-secondary-500/10 group-hover:opacity-0 transition-opacity duration-500"></div>
              <img
                v-if="project.featured_image"
                :src="project.featured_image"
                :alt="project.title"
                class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700 ease-out"
              />
              <!-- Gradient Overlay -->
              <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent opacity-60 group-hover:opacity-90 transition-opacity duration-300"></div>
              
              <!-- Hover CTA -->
              <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 transform group-hover:scale-100 scale-95">
                <div class="px-6 py-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm rounded-xl shadow-2xl flex items-center gap-2 border border-white/20">
                  <span class="text-primary-600 dark:text-primary-400 font-bold text-sm">View Details</span>
                  <svg class="w-4 h-4 text-primary-600 dark:text-primary-400 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                  </svg>
                </div>
              </div>
            </div>

            <!-- Content Section -->
            <div class="p-5 space-y-3">
              <!-- Title -->
              <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">
                {{ project.title }}
              </h3>
              
              <!-- Description -->
              <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 leading-relaxed">
                {{ project.description }}
              </p>
              
              <!-- Tech Stack -->
              <div class="flex flex-wrap gap-1.5 pt-2">
                <span
                  v-for="tech in project.technologies?.slice(0, 3)"
                  :key="tech"
                  class="px-2.5 py-1 bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/20 dark:to-secondary-900/20 text-primary-700 dark:text-primary-300 rounded-md text-xs font-semibold border border-primary-100 dark:border-primary-800/50 hover:border-primary-300 dark:hover:border-primary-600 transition-colors"
                >
                  {{ tech }}
                </span>
                <span
                  v-if="project.technologies?.length > 3"
                  class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-md text-xs font-semibold"
                >
                  +{{ project.technologies.length - 3 }}
                </span>
              </div>
            </div>

            <!-- Bottom Glow Effect -->
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-500 via-secondary-500 to-accent-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
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

        <div v-else>
          <!-- Posts Carousel (Mobile) -->
          <div class="md:hidden">
            <MobileCarousel :items="latestPosts" :show-arrows="true" :show-dots="true">
              <template #default="{ item }">
              <div
                @click="$router.push(`/blog/${item.slug}`)"
                class="group cursor-pointer card-elevated overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col mx-1"
              >
                <!-- Image -->
                <div class="relative aspect-video bg-gradient-to-br from-secondary-100 to-accent-100 dark:from-secondary-900/20 dark:to-accent-900/20">
                  <img
                    v-if="item.featured_image"
                    :src="item.featured_image"
                    :alt="item.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                  />
                </div>

                <!-- Content -->
                <div class="p-6 flex-1 flex flex-col">
                  <div class="flex items-center gap-3 mb-4">
                    <span class="px-3 py-1 bg-secondary-50 dark:bg-secondary-900/20 text-secondary-700 dark:text-secondary-300 rounded-lg text-xs font-semibold uppercase">
                      {{ item.category?.name }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-500">
                      {{ formatDate(item.published_at) }}
                    </span>
                  </div>

                  <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-secondary-600 dark:group-hover:text-secondary-400 transition-colors line-clamp-2">
                    {{ item.title }}
                  </h3>

                  <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3 flex-1 mb-4">
                    {{ item.excerpt }}
                  </p>

                  <div class="flex items-center text-secondary-600 dark:text-secondary-400 font-semibold text-sm">
                    <span>Read Article</span>
                    <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                  </div>
                </div>
              </div>
            </template>
          </MobileCarousel>
        </div>

          <!-- Posts Grid (Desktop) -->
          <div class="hidden md:grid grid-cols-3 gap-8">
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

    <!-- CTA Section - AI Automation Expert (100% AI-Built Proof) -->
    <section
      v-if="showCTASection"
      class="relative py-20 bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 overflow-hidden"
    >
      <!-- Subtle Pattern -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 30px 30px;"></div>
      </div>

      <div class="container-custom text-center relative z-10">
        <!-- Heading with AI Proof Angle -->
        <h2 class="text-4xl md:text-5xl font-display font-bold text-white mb-6 leading-tight">
          Don't Believe AI Can Build A Site This Good?
        </h2>
        
        <!-- Copy with AI Tech Stack -->
        <p class="text-xl text-white/90 mb-8 max-w-3xl mx-auto leading-relaxed">
          This website you're viewing is <strong class="text-white">100% built by AI Automation</strong>. Laravel + Vue.js + n8n + AI Agents. <span class="font-semibold text-white">ZERO manual coding</span>. 300+ hours of work automated to 3 days. If a complex website like this can be fully AI-powered, imagine how easy automating your business will be.
        </p>

        <!-- Stats Badge -->
        <div class="flex flex-wrap items-center justify-center gap-4 mb-10">
          <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20">
            <span class="text-2xl">⚡</span>
            <span class="text-white font-semibold text-sm">300+ hours saved</span>
          </div>
          <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20">
            <span class="text-2xl">🤖</span>
            <span class="text-white font-semibold text-sm">100% AI-built</span>
          </div>
          <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20">
            <span class="text-2xl">💰</span>
            <span class="text-white font-semibold text-sm">95% cost reduction</span>
          </div>
        </div>

        <!-- CTA Text -->
        <p class="text-lg text-white/95 mb-8 font-medium">
          Want to see how? <span class="text-white font-bold">FREE 30-minute consultation!</span>
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-4">
          <!-- Primary CTA: WhatsApp -->
          <a
            :href="contactWhatsApp"
            target="_blank"
            rel="noopener noreferrer"
            class="group px-10 py-5 bg-white text-primary-600 font-bold text-lg rounded-xl hover:shadow-2xl hover:scale-105 transition-all duration-300 flex items-center gap-3"
          >
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
            </svg>
            <span>WhatsApp Me Now</span>
          </a>

          <!-- Secondary CTA: Contact Form -->
          <button
            @click="$router.push('/contact')"
            class="px-10 py-5 bg-white/10 backdrop-blur-sm border-2 border-white text-white font-bold text-lg rounded-xl hover:bg-white hover:text-primary-600 hover:shadow-2xl hover:scale-105 transition-all duration-300"
          >
            Schedule Consultation
          </button>
        </div>
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
import { useSettings } from '@/composables/useSettings'
import { BaseLoader, MobileCarousel } from '@/components/base'
import api from '@/services/api'

const { projects, isLoading: projectsLoading, fetchProjects } = useProjects()

// Featured projects computed (backend already filters, but we create alias for clarity)
const featuredProjects = computed(() => projects.value)
const { posts: latestPosts, isLoading: postsLoading, fetchPosts } = usePosts()
const { awards, isLoading: awardsLoading, fetchAwards } = useAwards()
const { testimonials, isLoading: testimonialsLoading, fetchTestimonials } = useTestimonials()
const { galleries, loading: galleriesLoading, fetchGalleries, fetchGalleryItems } = useGallery()
const { aboutSettings, loading: loadingAbout, heroName, heroTitle, heroBio, heroAvatar, heroSkills } = useAboutSettings()
const { sections, fetchActiveSections } = usePageSections()
const { settings, fetchSettings, getSettingValue } = useSettings()
const currentTestimonialIndex = ref(0)
const showAllSkills = ref(false)

// WhatsApp Contact computed - gets phone from settings (profile.phone or contact.phone)
const contactWhatsApp = computed(() => {
  // Try profile.phone first, then contact.phone, then default
  const phone = getSettingValue('profile.phone') || getSettingValue('contact.phone') || '+6281234567890'
  const message = encodeURIComponent('Hi! I saw your AI-powered website and I\'m interested in discussing AI Automation for my business. Can we schedule a free consultation?')
  return `https://wa.me/${phone.replace(/[^0-9]/g, '')}?text=${message}`
})

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

// Stats computed from aboutSettings
const stats = computed(() => {
  if (!aboutSettings.value?.statistics) {
    return [
      { value: '16+', label: 'Years Experience' },
      { value: '1K', label: 'Followers' },
      { value: '50+', label: 'Projects Delivered' },
      { value: '$2M+', label: 'Cost Savings' },
      { value: '95%', label: 'Success Rate' }
    ]
  }
  
  const s = aboutSettings.value.statistics
  return [
    { value: s.years_experience || '16+', label: 'Years Experience' },
    { value: s.followers || '1K', label: 'Followers' },
    { value: s.projects_delivered || '50+', label: 'Projects Delivered' },
    { value: s.cost_savings || '$2M+', label: 'Cost Savings' },
    { value: s.success_rate || '95%', label: 'Success Rate' }
  ].filter(stat => stat.value) // Only show stats with values
})

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

// Awards 3D Coverflow state
const activeAwardIndex = ref(0)
const carouselContainer = ref(null)
const mouseX = ref(0)
const mouseY = ref(0)
const isHoveringCarousel = ref(false)
const screenSize = ref('desktop') // 'mobile' | 'tablet' | 'desktop'
const windowWidth = ref(window.innerWidth)

// Screen breakpoints
const isMobile = computed(() => windowWidth.value < 768)
const isTablet = computed(() => windowWidth.value >= 768 && windowWidth.value < 1024)
const isDesktop = computed(() => windowWidth.value >= 1024)

// Cards to show based on screen size
const visibleCardsCount = computed(() => {
  if (isMobile.value) return 1 // Mobile: 1 card (active only)
  if (isTablet.value) return 2 // Tablet: 2 cards (active + 1 side)
  return 3 // Desktop: 3 cards (active + 2 sides)
})

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

const getProfilePhotoUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/uploads/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/uploads/' + path
}

const handleAvatarError = (event) => {
  console.warn('[Home] Failed to load avatar:', event.target.src)
  event.target.style.display = 'none'
}

// 3D Coverflow + Magnetic Hover Functions
const getCardStyle = (index) => {
  const diff = index - activeAwardIndex.value
  const isCurrent = diff === 0
  const isPrev = diff < 0
  const isNext = diff > 0
  const absDiff = Math.abs(diff)
  
  // Visibility based on screen size
  let maxVisible = 1 // Default: show center + 1 on each side (3 total)
  if (isMobile.value) {
    maxVisible = 0 // Mobile: only center (1 total)
  } else if (isTablet.value) {
    maxVisible = 1 // Tablet: center + 1 per side (up to 3 total, but we'll limit to 2)
  } else {
    maxVisible = 1 // Desktop: center + 1 per side (3 total)
  }
  
  // Hide cards too far outside visible range
  if (absDiff > maxVisible) {
    return {
      transform: `translateX(${diff > 0 ? '150%' : '-150%'}) scale(0.3)`,
      opacity: 0,
      zIndex: 0,
      pointerEvents: 'none',
      visibility: 'hidden'
    }
  }
  
  let translateX = diff * 100
  let translateZ = isCurrent ? 0 : -300
  let rotateY = 0
  let scale = isCurrent ? 1 : 0.75
  let opacity = isCurrent ? 1 : 0.85 // Increased from 0.5 to 0.85
  let zIndex = isCurrent ? 30 : (20 - absDiff)
  
  // Responsive positioning adjustments
  if (isMobile.value) {
    // Mobile: Only show active card, hide all others
    if (!isCurrent) {
      return {
        transform: `translateX(${diff > 0 ? '150%' : '-150%'}) scale(0.5)`,
        opacity: 0,
        zIndex: 0,
        pointerEvents: 'none',
        visibility: 'hidden'
      }
    }
  } else if (isTablet.value) {
    // Tablet: Show 2 cards (active + 1 side peek on right only for cleaner look)
    if (absDiff > 1 || isPrev) {
      // Hide cards on left and far right
      return {
        transform: `translateX(${diff > 0 ? '180%' : '-180%'}) scale(0.6)`,
        opacity: 0,
        zIndex: 0,
        pointerEvents: 'none',
        visibility: 'hidden'
      }
    }
    // Show only next card (right side) as peek
    if (isNext && absDiff === 1) {
      rotateY = -35
      translateX = 50
      translateZ = -200
      scale = 0.75
      opacity = 0.8 // Increased from 0.6 to 0.8
    }
  } else {
    // Desktop: Show 3 cards (active + 2 sides)
    // Adjust spacing to keep cards in viewport
    if (absDiff === 1) {
      // Immediate neighbors (±1)
      if (isPrev) {
        rotateY = 45
        translateX = -45 // Closer positioning
        translateZ = -250
        scale = 0.8
      } else if (isNext) {
        rotateY = -45
        translateX = 45 // Closer positioning
        translateZ = -250
        scale = 0.8
      }
    } else if (absDiff > 1) {
      // Cards beyond immediate neighbors - fade out more gently
      opacity = 0.4 // Increased from 0.2 to 0.4
      scale = 0.6
      if (isPrev) {
        rotateY = 60
        translateX = -80
        translateZ = -400
      } else if (isNext) {
        rotateY = -60
        translateX = 80
        translateZ = -400
      }
    }
  }
  
  // Magnetic hover effect (only on active card and desktop)
  if (isCurrent && isHoveringCarousel.value && carouselContainer.value && isDesktop.value) {
    const rect = carouselContainer.value.getBoundingClientRect()
    const centerX = rect.width / 2
    const centerY = rect.height / 2
    const deltaX = (mouseX.value - centerX) / 20
    const deltaY = (mouseY.value - centerY) / 20
    
    translateX += deltaX
    rotateY = -deltaX / 5
  }
  
  return {
    transform: `translateX(${translateX}%) translateZ(${translateZ}px) rotateY(${rotateY}deg) scale(${scale})`,
    opacity,
    zIndex,
    pointerEvents: isCurrent ? 'auto' : 'none',
    visibility: 'visible'
  }
}

const nextAward = () => {
  if (activeAwardIndex.value < awards.value.length - 1) {
    activeAwardIndex.value++
  }
}

const previousAward = () => {
  if (activeAwardIndex.value > 0) {
    activeAwardIndex.value--
  }
}

const handleMouseMove = (event) => {
  if (!carouselContainer.value) return
  const rect = carouselContainer.value.getBoundingClientRect()
  mouseX.value = event.clientX - rect.left
  mouseY.value = event.clientY - rect.top
  isHoveringCarousel.value = true
}

const handleMouseLeave = () => {
  isHoveringCarousel.value = false
}

// Touch/Swipe support for mobile
let touchStartX = 0
let touchEndX = 0

const handleTouchStart = (event) => {
  touchStartX = event.touches[0].clientX
}

const handleTouchMove = (event) => {
  touchEndX = event.touches[0].clientX
}

const handleTouchEnd = () => {
  const swipeThreshold = 50
  const diff = touchStartX - touchEndX
  
  if (Math.abs(diff) > swipeThreshold) {
    if (diff > 0) {
      // Swipe left - next
      nextAward()
    } else {
      // Swipe right - previous
      previousAward()
    }
  }
}

// Keyboard navigation for lightbox and carousel
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
  } else if (showAwardsSection.value && awards.value.length > 1) {
    // Awards carousel keyboard navigation (when no modal is open)
    if (e.key === 'ArrowRight') nextAward()
    if (e.key === 'ArrowLeft') previousAward()
  }
}

// Auto-rotate testimonials
const rotateTestimonials = () => {
  if (testimonials.value.length > 0) {
    currentTestimonialIndex.value = (currentTestimonialIndex.value + 1) % testimonials.value.length
  }
}

// Auto-rotate awards carousel
const rotateAwards = () => {
  if (awards.value.length > 1) {
    activeAwardIndex.value = (activeAwardIndex.value + 1) % awards.value.length
  }
}

let testimonialInterval
let awardsInterval

onMounted(async () => {
  // Performance tracking START
  const startTime = performance.now()
  
  // Window resize listener for responsive carousel
  const handleResize = () => {
    windowWidth.value = window.innerWidth
  }
  window.addEventListener('resize', handleResize)
  
  // Fetch page sections configuration
  console.log('🔄 Fetching page sections...')
  await fetchActiveSections('homepage')
  console.log('📋 Sections loaded:', sections.value)

  // Fetch settings for WhatsApp number
  console.log('🔄 Fetching settings...')
  await fetchSettings()
  console.log('⚙️ Settings loaded:', settings.value.length, 'items')

  // About settings auto-loads via TanStack Query (instant with placeholderData)
  // No need to await - composable handles loading state automatically

  console.log('⏱️ Starting PARALLEL data fetch...')
  
  const fetchStart = performance.now()
  
  // PARALLEL fetch instead of sequential (FASTEST!)
  await Promise.all([
    fetchProjects({ featured: true, limit: 6 }).then(() => {
      console.log('🎯 Featured Projects fetched:', projects.value.length, 'items')
      console.log('📦 Projects data:', projects.value)
    }),
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
  
  // Start awards carousel rotation
  if (showAwardsSection.value && awards.value.length > 1) {
    awardsInterval = setInterval(rotateAwards, 6000) // 6s interval (slower than testimonials)
  }
  
  // Add keyboard event listener
  window.addEventListener('keydown', handleKeydown)
  
  // Store resize handler for cleanup
  window._resizeHandler = handleResize
})

// Cleanup on unmount
import { onUnmounted } from 'vue'
onUnmounted(() => {
  if (testimonialInterval) {
    clearInterval(testimonialInterval)
  }
  if (awardsInterval) {
    clearInterval(awardsInterval)
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

/* Premium Award Card Animations */
.award-premium-card {
  animation: fadeInUp 0.6s ease-out forwards;
  opacity: 0;
  transform: translateY(30px);
}

@keyframes fadeInUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Shine animation */
@keyframes shine {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.animate-shine {
  animation: shine 2s ease-in-out infinite;
}

/* 3D Coverflow Styles */
.award-3d-card {
  position: absolute;
  transform-style: preserve-3d;
  will-change: transform, opacity;
  transition: all 0.7s cubic-bezier(0.165, 0.84, 0.44, 1);
}

/* Active card (center) */
.award-active {
  transform: translateX(0) translateZ(0) rotateY(0deg) scale(1) !important;
  opacity: 1 !important;
  z-index: 30 !important;
}

/* Previous cards (left side) */
.award-prev {
  /* Removed excessive darkening - let opacity handle visibility */
}

/* Next cards (right side) */
.award-next {
  /* Removed excessive darkening - let opacity handle visibility */
}

/* Hover 3D tilt effect */
.award-premium-card:hover {
  transform: translateY(-8px) scale(1.02);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

/* Blob animations for background */
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

/* Gradient animation */
@keyframes gradient {
  0%, 100% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
}

.animate-gradient {
  background-size: 200% 200%;
  animation: gradient 3s ease infinite;
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
