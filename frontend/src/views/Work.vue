<template>
  <div class="min-h-screen pt-16 sm:pt-20 pb-16">
    <div class="container-custom">
      <!-- Header with search toggle -->
      <div class="flex items-start justify-between mb-3 sm:mb-6">
        <div>
          <span class="eyebrow-tag text-accent-gold mb-1 inline-flex">Portfolio</span>
          <h1 class="section-heading text-3xl md:text-5xl lg:text-7xl text-gradient mt-2 mb-1">My Work</h1>
          <p class="text-fg-muted text-xs sm:text-lg font-light max-w-lg">Projects, awards, and case studies from 17+ years of building.</p>
        </div>
        <!-- Search icon toggle -->
        <button
          v-if="activeTab === 'projects'"
          @click="searchOpen = !searchOpen"
          class="mt-2 w-9 h-9 sm:w-10 sm:h-10 flex-shrink-0 flex items-center justify-center rounded-full transition-all duration-300"
          :class="searchOpen ? 'bg-accent-gold/15 text-accent-gold border border-accent-gold/25' : 'text-fg-muted bg-white/4 border border-border-hairline hover:text-fg-primary hover:border-border-hover'"
          aria-label="Toggle search"
        >
          <svg v-if="!searchOpen" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Search Bar — expandable -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2 max-h-0"
        enter-to-class="opacity-100 translate-y-0 max-h-24"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 max-h-24"
        leave-to-class="opacity-0 -translate-y-2 max-h-0"
      >
        <div v-if="searchOpen && activeTab === 'projects'" class="mb-3 sm:mb-4 overflow-hidden">
          <div class="max-w-2xl mx-auto">
            <div class="relative">
              <input
                ref="searchInputRef"
                v-model="searchQuery"
                type="text"
                placeholder="Search projects..."
                class="w-full px-4 py-2.5 pr-10 bg-bg-elevated border border-border-hairline rounded-xl text-fg-primary placeholder:text-fg-dim text-sm focus:outline-none focus:border-accent-gold/40 transition-colors"
              />
              <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fg-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <p v-if="searchQuery" class="mt-1 text-xs text-fg-muted text-center">
              {{ filteredProjects.length }} {{ filteredProjects.length === 1 ? 'result' : 'results' }} found
            </p>
          </div>
        </div>
      </Transition>

      <!-- Tab Bar — Pill buttons -->
      <div class="flex gap-2 mb-3 sm:mb-5">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="switchTab(tab.key)"
          class="px-5 py-2.5 rounded-full text-sm font-medium transition-all duration-700 ease-spring"
          :class="[
            activeTab === tab.key
              ? 'bg-accent-gold/10 text-accent-gold border border-accent-gold/25'
              : 'text-fg-muted hover:text-fg-primary bg-white/4 border border-border-hairline hover:border-border-hover'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ═══ Projects Tab ═══ -->
      <div v-if="activeTab === 'projects'">

        <!-- Category Filters -->
        <div class="flex flex-wrap gap-1.5 sm:gap-2.5 justify-center mb-4 sm:mb-8">
          <button
            @click="selectedCategory = ''"
            class="px-4 py-2 rounded-full text-xs font-semibold transition-all duration-300"
            :class="selectedCategory === '' ? 'bg-accent-gold text-bg-deep' : 'text-fg-muted bg-white/4 border border-border-hairline hover:border-border-hover'"
          >All Projects</button>
          <button
            v-for="cat in categories"
            :key="cat"
            @click="selectedCategory = selectedCategory === cat ? '' : cat"
            class="px-4 py-2 rounded-full text-xs font-semibold transition-all duration-300"
            :class="selectedCategory === cat ? 'bg-accent-gold text-bg-deep' : 'text-fg-muted bg-white/4 border border-border-hairline hover:border-border-hover'"
          >{{ cat }}</button>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="projectsLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="i in 6" :key="i" class="rounded-xl overflow-hidden bg-bg-elevated animate-pulse">
            <div class="aspect-[4/3] bg-white/5"></div>
            <div class="p-5 space-y-3">
              <div class="h-3 bg-white/5 rounded w-1/3"></div>
              <div class="h-4 bg-white/5 rounded w-3/4"></div>
              <div class="h-3 bg-white/5 rounded w-full"></div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredProjects.length === 0" class="text-center py-20">
          <svg class="w-12 h-12 text-fg-dim/30 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <p class="text-fg-muted">No projects found.</p>
        </div>

        <!-- Projects Grid — 3 column -->
        <div v-else>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="project in paginatedProjects"
              :key="project.id"
              @click="$router.push(`/projects/${project.slug}`)"
              class="group relative cursor-pointer bg-bg-elevated rounded-xl overflow-hidden hover:shadow-2xl hover:shadow-accent-gold/5 transition-all duration-500 hover:-translate-y-1 border border-border-hairline hover:border-accent-gold/20"
            >
              <!-- Category Badge -->
              <div v-if="project.category" class="absolute top-3 right-3 z-10 px-2.5 py-1 text-white text-[10px] font-bold rounded-full shadow-lg"
                :class="getCategoryBadgeClass(project.category?.name || project.category)">
                {{ project.category?.name || project.category }}
              </div>

              <!-- Image -->
              <div class="relative aspect-[4/3] overflow-hidden">
                <img
                  v-if="project.image || project.featured_image"
                  :src="project.image || project.featured_image"
                  :alt="project.title"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                  loading="lazy"
                />
                <div v-else class="w-full h-full bg-gradient-to-br from-accent-gold/5 to-accent-cyan/5 flex items-center justify-center">
                  <svg class="w-10 h-10 text-fg-dim/15" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25z"/></svg>
                </div>
                <!-- Hover overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-bg-deep/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                  <div class="px-4 py-2 bg-white/10 backdrop-blur-sm rounded-lg border border-white/15 flex items-center gap-1.5 translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                    <span class="text-white text-xs font-semibold">View Details</span>
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                  </div>
                </div>
              </div>

              <!-- Content -->
              <div class="p-4 space-y-2">
                <div v-if="project.client" class="flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5 text-accent-gold flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                  <span class="text-xs font-semibold text-accent-gold truncate">{{ project.client }}</span>
                </div>
                <h3 class="text-sm font-bold text-fg-primary group-hover:text-accent-gold transition-colors line-clamp-2 leading-snug">
                  {{ project.title }}
                </h3>
                <p class="text-xs text-fg-muted line-clamp-2 leading-relaxed">{{ project.description }}</p>
              </div>

              <!-- Bottom accent line -->
              <div class="h-0.5 bg-gradient-to-r from-accent-gold via-accent-cyan to-accent-indigo transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="flex flex-wrap justify-center items-center gap-2 mt-12">
            <button
              :disabled="currentProjectPage === 1"
              @click="goToPage(currentProjectPage - 1)"
              class="px-4 py-2 rounded-lg text-xs font-medium border border-border-hairline text-fg-muted hover:border-border-hover disabled:opacity-30 disabled:cursor-not-allowed transition-all"
            >Previous</button>
            <button
              v-for="page in totalPages"
              :key="page"
              @click="goToPage(page)"
              class="w-9 h-9 rounded-lg text-xs font-semibold transition-all duration-300"
              :class="page === currentProjectPage ? 'bg-accent-gold text-bg-deep' : 'text-fg-muted border border-border-hairline hover:border-border-hover'"
            >{{ page }}</button>
            <button
              :disabled="currentProjectPage === totalPages"
              @click="goToPage(currentProjectPage + 1)"
              class="px-4 py-2 rounded-lg text-xs font-medium border border-border-hairline text-fg-muted hover:border-border-hover disabled:opacity-30 disabled:cursor-not-allowed transition-all"
            >Next</button>
          </div>

          <!-- Results count -->
          <p class="text-center mt-4 text-xs text-fg-dim">
            Showing {{ (currentProjectPage - 1) * projectsPerPage + 1 }}-{{ Math.min(currentProjectPage * projectsPerPage, filteredProjects.length) }} of {{ filteredProjects.length }} projects
          </p>
        </div>
      </div>

      <!-- ═══ Awards Tab ═══ -->
      <div v-else-if="activeTab === 'awards'" class="awards-section">
        <!-- Full-bleed purple gradient -->
        <div class="awards-gradient-bg">
          <!-- Header -->
          <div class="text-center pt-4 sm:pt-8 pb-4 sm:pb-6 px-4">
            <span class="inline-flex items-center gap-2 text-[11px] tracking-[0.2em] uppercase font-mono text-purple-300/70 mb-3">
              <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              Recognition & Achievements
            </span>
            <h2 class="text-3xl md:text-4xl font-display font-bold text-white mb-1.5">
              Awards & <span class="awards-accent">Honors</span>
            </h2>
            <p class="text-purple-200/40 text-sm max-w-sm mx-auto">Celebrating milestones and industry recognition</p>
          </div>

          <!-- Skeleton loader -->
          <div v-if="awardsLoading" class="flex justify-center py-24">
            <div class="awards-card awards-card--skeleton animate-pulse">
              <div class="h-52 bg-purple-300/20 rounded-t-2xl"></div>
              <div class="p-4 space-y-3">
                <div class="h-4 bg-purple-300/20 rounded w-3/4"></div>
                <div class="h-3 bg-purple-300/20 rounded w-1/2"></div>
                <div class="h-9 bg-purple-300/20 rounded-xl"></div>
              </div>
            </div>
          </div>

          <!-- Coverflow Carousel -->
          <div v-else-if="awards.length > 0" class="awards-carousel">
            <!-- Nav arrows -->
            <button v-if="activeAwardIndex > 0" @click="prevAward" class="awards-arrow awards-arrow--left" aria-label="Previous">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button v-if="activeAwardIndex < awards.length - 1" @click="nextAward" class="awards-arrow awards-arrow--right" aria-label="Next">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- Track -->
            <div class="awards-track">
              <div
                v-for="(award, i) in awards"
                :key="award.id"
                class="awards-slide"
                :style="getSlideStyle(i)"
                @click="handleAwardCardClick(award, i)"
              >
                <div class="awards-card" :class="{ 'awards-card--active': i === activeAwardIndex }">
                  <!-- Image -->
                  <div class="awards-card__image">
                    <img v-if="award.thumbnail || award.image" :src="award.thumbnail || award.image" :alt="award.award_title || award.title" />
                    <div v-else class="awards-card__placeholder">
                      <svg class="w-12 h-12 text-purple-400/30" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    </div>
                    <!-- Badges -->
                    <span v-if="award.total_photos > 0" class="awards-badge awards-badge--photos">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                      {{ award.total_photos }}
                    </span>
                    <span v-if="formatAwardYear(award.award_date || award.received_at)" class="awards-badge awards-badge--year">
                      {{ formatAwardYear(award.award_date || award.received_at) }}
                    </span>
                  </div>
                  <!-- Body -->
                  <div class="awards-card__body">
                    <h3 class="awards-card__title">{{ award.award_title || award.title }}</h3>
                    <p class="awards-card__org">
                      <svg class="w-3 h-3 text-fuchsia-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                      {{ (award.issuing_organization || award.organization || '').toUpperCase() }}
                    </p>
                    <button v-if="award.total_photos > 0" @click.stop="openGalleryModal(award)" class="awards-card__cta">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                      VIEW GALLERY
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Dots -->
            <div class="flex justify-center gap-1.5 pb-10 pt-5">
              <button v-for="(a, i) in awards" :key="'d'+a.id" @click="activeAwardIndex = i"
                class="rounded-full transition-all duration-300"
                :class="i === activeAwardIndex ? 'w-5 h-1.5 bg-white' : 'w-1.5 h-1.5 bg-white/25 hover:bg-white/50'" />
            </div>
          </div>

          <!-- Empty -->
          <div v-else class="text-center py-24 px-4">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center">
              <svg class="w-8 h-8 text-purple-400/40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-purple-200 mb-1">No Awards Yet</h3>
            <p class="text-purple-300/40 text-sm">Check back later for updates.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gallery Modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="showGalleryModal && selectedAward"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/95 backdrop-blur-md"
        @click.self="closeGalleryModal"
      >
        <div class="relative w-full max-w-5xl max-h-[90vh] bg-zinc-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col border border-white/10">
          <!-- Header -->
          <div class="flex-shrink-0 px-6 py-5 border-b border-white/10">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-white mb-1 line-clamp-1">
                  {{ selectedAward.award_title || selectedAward.title }}
                </h3>
                <p class="text-sm text-white/50">
                  {{ selectedAward.issuing_organization || selectedAward.organization }}
                </p>
              </div>
              <button
                @click="closeGalleryModal"
                class="flex-shrink-0 p-2 bg-white/5 hover:bg-white/10 rounded-lg transition-all duration-200"
              >
                <svg class="w-5 h-5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-6">
            <div v-if="loadingGallery" class="flex items-center justify-center py-20">
              <div class="w-10 h-10 border-2 border-purple-400/30 border-t-purple-400 rounded-full animate-spin"></div>
            </div>
            <div v-else-if="galleryPhotos.length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-3">
              <div
                v-for="(photo, index) in galleryPhotos"
                :key="photo.id"
                class="relative group cursor-pointer aspect-square rounded-xl overflow-hidden bg-zinc-800 hover:ring-2 hover:ring-fuchsia-500/50 transition-all duration-300"
                @click="openLightbox(index)"
              >
                <img
                  :src="photo.image"
                  :alt="photo.title || 'Gallery photo'"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                />
              </div>
            </div>
            <div v-else class="text-center py-20">
              <p class="text-white/40">No photos available</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Lightbox -->
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="showLightbox" class="fixed inset-0 z-[60] flex flex-col bg-zinc-950">
        <div class="flex-shrink-0 flex items-center justify-between px-6 py-4">
          <span class="text-white/60 text-sm font-mono">{{ currentPhotoIndex + 1 }} / {{ galleryPhotos.length }}</span>
          <button @click="closeLightbox" class="p-2 hover:bg-white/10 rounded-lg transition-colors">
            <svg class="w-5 h-5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="flex-1 flex items-center justify-center p-8 relative">
          <button v-if="currentPhotoIndex > 0" @click="previousPhoto" class="absolute left-6 p-3 bg-white/5 hover:bg-white/10 rounded-full transition-colors">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <img :src="galleryPhotos[currentPhotoIndex]?.image" class="max-w-full max-h-[75vh] object-contain rounded-lg" />
          <button v-if="currentPhotoIndex < galleryPhotos.length - 1" @click="nextPhoto" class="absolute right-6 p-3 bg-white/5 hover:bg-white/10 rounded-full transition-colors">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

// ── Awards Carousel State ────────────────────────────────────
const activeAwardIndex = ref(0)
const showGalleryModal = ref(false)
const selectedAward = ref(null)
const galleryPhotos = ref([])
const loadingGallery = ref(false)
const showLightbox = ref(false)
const currentPhotoIndex = ref(0)

const route = useRoute()
const router = useRouter()

const tabs = [
  { key: 'projects', label: 'Projects' },
  { key: 'awards', label: 'Awards' }
]

const activeTab = ref(route.query.tab || 'projects')
const searchQuery = ref('')
const searchOpen = ref(false)
const searchInputRef = ref(null)
const selectedCategory = ref('')

const allProjects = ref([])
const projectsLoading = ref(true)
const currentProjectPage = ref(1)
const projectsPerPage = 9

const awards = ref([])
const awardsLoading = ref(true)

const categories = computed(() => {
  const cats = new Set()
  allProjects.value.forEach(p => {
    const catName = p.category?.name || p.category
    if (catName) cats.add(catName)
  })
  return Array.from(cats).slice(0, 6)
})

const filteredProjects = computed(() => {
  let result = allProjects.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(p =>
      p.title.toLowerCase().includes(q) ||
      (p.description || '').toLowerCase().includes(q) ||
      (p.client || '').toLowerCase().includes(q)
    )
  }
  if (selectedCategory.value) {
    result = result.filter(p =>
      (p.category?.name || p.category) === selectedCategory.value
    )
  }
  return result
})

const totalPages = computed(() => Math.ceil(filteredProjects.value.length / projectsPerPage))

const paginatedProjects = computed(() => {
  const start = (currentProjectPage.value - 1) * projectsPerPage
  return filteredProjects.value.slice(start, start + projectsPerPage)
})

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentProjectPage.value = page
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

// Reset page when filters change
watch([searchQuery, selectedCategory], () => { currentProjectPage.value = 1 })

watch(searchOpen, (open) => {
  if (open) {
    nextTick(() => searchInputRef.value?.focus())
  } else {
    searchQuery.value = ''
  }
})

function getCategoryBadgeClass(cat) {
  const map = {
    'AI & Machine Learning': 'bg-gradient-to-r from-purple-500 to-indigo-600',
    'AI': 'bg-gradient-to-r from-purple-500 to-indigo-600',
    'Automation': 'bg-gradient-to-r from-blue-500 to-cyan-600',
    'RPA': 'bg-gradient-to-r from-blue-500 to-cyan-600',
    'IoT': 'bg-gradient-to-r from-green-500 to-emerald-600',
    'Web Development': 'bg-gradient-to-r from-orange-500 to-red-600',
    'Web': 'bg-gradient-to-r from-orange-500 to-red-600',
    'Mobile Apps': 'bg-gradient-to-r from-pink-500 to-rose-600',
    'Mobile': 'bg-gradient-to-r from-pink-500 to-rose-600'
  }
  return map[cat] || 'bg-gradient-to-r from-zinc-500 to-zinc-600'
}

function switchTab(key) {
  activeTab.value = key
  router.replace({ query: { tab: key } })
}

watch(() => route.query.tab, (newTab) => {
  if (newTab && newTab !== activeTab.value) {
    activeTab.value = newTab
  }
})

async function fetchProjects() {
  projectsLoading.value = true
  try {
    const res = await api.get('/projects', { params: { per_page: 100 } })
    allProjects.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch projects:', err)
  } finally {
    projectsLoading.value = false
  }
}

async function fetchAwards() {
  awardsLoading.value = true
  try {
    const res = await api.get('/awards')
    awards.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch awards:', err)
  } finally {
    awardsLoading.value = false
  }
}

// ── Awards Carousel ──────────────────────────────────────────
function prevAward() {
  if (activeAwardIndex.value > 0) activeAwardIndex.value--
}
function nextAward() {
  if (activeAwardIndex.value < awards.value.length - 1) activeAwardIndex.value++
}

function getSlideStyle(index) {
  const offset = index - activeAwardIndex.value
  const abs = Math.abs(offset)
  const dir = offset < 0 ? -1 : 1
  if (abs > 2) return { opacity: '0', pointerEvents: 'none', transform: 'translateX(0) scale(0.6)', zIndex: 0 }
  // Pixel-tuned coverflow: tight overlap, center dominates
  const tx = abs === 0 ? 0 : dir * (160 + (abs - 1) * 120)
  const sc = [1, 0.85, 0.72][abs]
  const op = [1, 0.6, 0.3][abs]
  const br = [1, 0.5, 0.35][abs]
  return {
    transform: `translateX(${tx}px) scale(${sc})`,
    zIndex: 10 - abs,
    opacity: String(op),
    filter: `brightness(${br})`,
    pointerEvents: abs === 0 ? 'auto' : 'auto'
  }
}

function formatAwardYear(date) {
  if (!date) return ''
  const d = new Date(date)
  if (!isNaN(d.getTime())) return d.getFullYear()
  const yearMatch = String(date).match(/\d{4}/)
  return yearMatch ? yearMatch[0] : date
}

// ── Gallery Modal ────────────────────────────────────────────
// Click anywhere on the active award card opens the gallery; clicking an
// inactive card simply rotates it to active first (so the carousel stays
// navigable). Clicking the explicit VIEW GALLERY button uses @click.stop
// and goes straight to openGalleryModal regardless.
function handleAwardCardClick(award, i) {
  if (i !== activeAwardIndex.value) {
    activeAwardIndex.value = i
    return
  }
  if (award?.total_photos > 0) {
    openGalleryModal(award)
  }
}

async function openGalleryModal(award) {
  selectedAward.value = award
  showGalleryModal.value = true
  loadingGallery.value = true
  galleryPhotos.value = []
  try {
    const response = await api.get(`/awards/${award.id}/galleries`)
    if (response.data.success && response.data.data.galleries) {
      const allPhotos = []
      response.data.data.galleries.forEach(gallery => {
        if (gallery.items && gallery.items.length > 0) allPhotos.push(...gallery.items)
      })
      galleryPhotos.value = allPhotos
    }
  } catch (err) {
    console.error('Failed to load gallery:', err)
  } finally {
    loadingGallery.value = false
  }
}

function closeGalleryModal() {
  showGalleryModal.value = false
  selectedAward.value = null
  galleryPhotos.value = []
}

function openLightbox(index) {
  currentPhotoIndex.value = index
  showLightbox.value = true
}
function closeLightbox() { showLightbox.value = false }
function nextPhoto() { if (currentPhotoIndex.value < galleryPhotos.value.length - 1) currentPhotoIndex.value++ }
function previousPhoto() { if (currentPhotoIndex.value > 0) currentPhotoIndex.value-- }

function handleKeydown(e) {
  if (showLightbox.value) {
    if (e.key === 'ArrowRight') nextPhoto()
    if (e.key === 'ArrowLeft') previousPhoto()
    if (e.key === 'Escape') closeLightbox()
  } else if (showGalleryModal.value && e.key === 'Escape') {
    closeGalleryModal()
  } else if (activeTab.value === 'awards' && !showGalleryModal.value) {
    if (e.key === 'ArrowRight') nextAward()
    if (e.key === 'ArrowLeft') prevAward()
  }
}

onMounted(() => {
  fetchProjects()
  fetchAwards()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
/* ── Full-bleed Awards Section ─────────────────────────────── */
.awards-section {
  width: 100vw;
  position: relative;
  left: 50%;
  margin-left: -50vw;
}
.awards-gradient-bg {
  background: linear-gradient(160deg, #110825 0%, #2a1160 30%, #4c1d95 55%, #3b1280 80%, #110825 100%);
  overflow: hidden;
}
.awards-accent {
  background: linear-gradient(90deg, #c084fc, #e879f9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

/* ── Carousel Container ────────────────────────────────────── */
.awards-carousel {
  position: relative;
  padding: 0 3rem;
}
.awards-track {
  position: relative;
  height: 420px;
  display: flex;
  align-items: center;
  justify-content: center;
}
@media (min-width: 768px) {
  .awards-track { height: 440px; }
}

/* ── Each Slide (absolute positioned) ──────────────────────── */
.awards-slide {
  position: absolute;
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  will-change: transform, opacity;
}

/* ── Card ──────────────────────────────────────────────────── */
.awards-card {
  width: 270px;
  background: #fff;
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 8px 30px -8px rgba(0,0,0,0.3);
  transition: box-shadow 0.4s ease;
}
@media (min-width: 768px) {
  .awards-card { width: 310px; }
}
.awards-card--active {
  box-shadow: 0 25px 60px -10px rgba(0,0,0,0.55);
  cursor: default;
}
.awards-card--skeleton {
  width: 310px;
  background: rgba(255,255,255,0.05);
  border-radius: 1rem;
  overflow: hidden;
}

/* ── Card Image ────────────────────────────────────────────── */
.awards-card__image {
  position: relative;
  aspect-ratio: 1 / 1;
  overflow: hidden;
  background: linear-gradient(135deg, #ddd6fe 0%, #c4b5fd 100%);
}
.awards-card__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.awards-card__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ── Badges ────────────────────────────────────────────────── */
.awards-badge {
  position: absolute;
  bottom: 0.6rem;
}
.awards-badge--photos {
  left: 0.6rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.5rem;
  background: rgba(0,0,0,0.65);
  backdrop-filter: blur(4px);
  border-radius: 0.4rem;
  font-size: 0.7rem;
  font-weight: 600;
  color: #fff;
}
.awards-badge--year {
  right: 0.6rem;
  width: 2.8rem;
  height: 2.8rem;
  border-radius: 50%;
  background: linear-gradient(135deg, #d946ef, #ec4899);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.68rem;
  font-weight: 800;
  color: #fff;
  box-shadow: 0 4px 12px rgba(217,70,239,0.4);
  border: 2px solid rgba(255,255,255,0.2);
}

/* ── Card Body ─────────────────────────────────────────────── */
.awards-card__body {
  padding: 0.85rem 1rem 1rem;
}
.awards-card__title {
  font-size: 0.85rem;
  font-weight: 700;
  color: #18181b;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: 0.35rem;
}
.awards-card__org {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.7rem;
  font-weight: 600;
  color: #c026d3;
  margin-bottom: 0.65rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.awards-card__cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  width: 100%;
  padding: 0.5rem 0;
  border-radius: 0.7rem;
  background: linear-gradient(90deg, #d946ef, #ec4899);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  border: none;
  cursor: pointer;
  transition: all 0.25s ease;
}
.awards-card__cta:hover {
  filter: brightness(1.1);
  box-shadow: 0 6px 20px -4px rgba(217,70,239,0.4);
}
.awards-card__cta:active {
  transform: scale(0.97);
}

/* ── Arrows ────────────────────────────────────────────────── */
.awards-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 20;
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.2);
  background: rgba(255,255,255,0.06);
  backdrop-filter: blur(8px);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.25s ease;
}
.awards-arrow:hover {
  background: rgba(255,255,255,0.12);
  border-color: rgba(255,255,255,0.4);
}
.awards-arrow:active { transform: translateY(-50%) scale(0.92); }
.awards-arrow--left { left: 0.5rem; }
.awards-arrow--right { right: 0.5rem; }
@media (min-width: 768px) {
  .awards-arrow--left { left: 1.5rem; }
  .awards-arrow--right { right: 1.5rem; }
  .awards-arrow { width: 3.25rem; height: 3.25rem; }
}

/* ── Transitions ───────────────────────────────────────────── */
.modal-enter-active, .modal-leave-active,
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.modal-enter-from, .modal-leave-to,
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
