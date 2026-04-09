<template>
  <div class="min-h-screen pt-28 pb-24">
    <div class="container-custom">
      <!-- Header -->
      <div class="mb-16">
        <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Portfolio</span>
        <h1 class="section-heading text-5xl md:text-6xl lg:text-7xl text-gradient mt-4 mb-4">My Work</h1>
        <p class="text-fg-muted text-lg font-light max-w-lg">Projects, awards, and case studies from 17+ years of building.</p>
      </div>

      <!-- Tab Bar — Pill buttons -->
      <div class="flex gap-2 mb-14">
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
        <!-- Search & Filters -->
        <div class="flex flex-col md:flex-row gap-4 mb-10">
          <div class="flex-1 bezel-shell-sm">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search projects..."
              class="w-full px-5 py-3 bg-transparent text-fg-primary placeholder:text-fg-dim focus:outline-none rounded-[calc(1.25rem-4px)] text-sm"
            />
          </div>
          <div class="flex gap-2 flex-wrap">
            <button
              v-for="cat in categories"
              :key="cat"
              @click="selectedCategory = selectedCategory === cat ? '' : cat"
              class="px-4 py-2 rounded-full text-xs font-medium transition-all duration-700 ease-spring"
              :class="[
                selectedCategory === cat
                  ? 'bg-accent-cyan/10 text-accent-cyan border border-accent-cyan/25'
                  : 'text-fg-muted bg-white/4 border border-border-hairline hover:border-border-hover'
              ]"
            >
              {{ cat }}
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="projectsLoading" class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div v-for="i in 4" :key="i" :class="i % 2 === 1 ? 'md:col-span-7' : 'md:col-span-5'" class="bezel-shell-sm"><div class="bezel-core-sm h-72 animate-pulse" /></div>
        </div>

        <!-- Project Grid — Asymmetric 7/5 -->
        <div v-else class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <router-link
            v-for="(project, index) in filteredProjects"
            :key="project.id"
            :to="`/projects/${project.slug}`"
            class="group"
            :class="getProjectSpan(index)"
          >
            <div class="bezel-shell-sm h-full">
              <div class="bezel-core-sm p-5 h-full flex flex-col">
                <div v-if="project.thumbnail" class="aspect-video rounded-xl overflow-hidden mb-4 bg-bg-elevated">
                  <img :src="project.thumbnail" :alt="project.title" class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105" loading="lazy" />
                </div>
                <div v-if="project.category" class="mb-2">
                  <span class="eyebrow-tag text-accent-cyan text-[9px]">{{ project.category?.name || project.category }}</span>
                </div>
                <h3 class="font-display text-lg font-semibold text-fg-primary group-hover:text-accent-gold transition-all duration-700 ease-spring">
                  {{ project.title }}
                </h3>
                <p class="text-sm text-fg-muted mt-2 line-clamp-2 font-light">{{ project.excerpt || project.description }}</p>
                <div class="mt-auto pt-4 flex items-center gap-2 text-fg-dim group-hover:text-accent-gold transition-all duration-700 ease-spring">
                  <span class="text-xs font-medium">View</span>
                  <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform duration-700 ease-spring" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
                </div>
              </div>
            </div>
          </router-link>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.lastPage > 1" class="flex justify-center gap-2 mt-12">
          <button
            v-for="page in pagination.lastPage"
            :key="page"
            @click="fetchProjects(page)"
            class="w-10 h-10 rounded-full text-sm font-medium transition-all duration-700 ease-spring"
            :class="[
              page === pagination.currentPage
                ? 'bg-accent-gold text-bg-deep font-bold'
                : 'text-fg-muted bg-white/4 border border-border-hairline hover:border-border-hover'
            ]"
          >
            {{ page }}
          </button>
        </div>
      </div>

      <!-- ═══ Awards Tab ═══ -->
      <div v-else-if="activeTab === 'awards'">
        <div v-if="awardsLoading" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div v-for="i in 4" :key="i" class="bezel-shell"><div class="bezel-core h-48 animate-pulse" /></div>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div
            v-for="(award, index) in awards"
            :key="award.id"
          >
            <div :class="index < 2 ? 'bezel-shell' : 'bezel-shell-sm'" class="h-full">
              <div :class="index < 2 ? 'bezel-core p-8' : 'bezel-core-sm p-8'" class="h-full relative overflow-hidden">
                <div v-if="index < 2" class="absolute -top-20 -right-20 w-40 h-40 rounded-full opacity-10 blur-3xl pointer-events-none" style="background: #D4A843;"></div>
                <div v-if="award.image" class="w-14 h-14 rounded-xl overflow-hidden mb-4 bg-bg-elevated border border-border-hairline">
                  <img :src="award.image" :alt="award.title" class="w-full h-full object-cover" loading="lazy" />
                </div>
                <h3 class="font-display text-xl font-bold mb-2" :class="index < 2 ? 'text-accent-gold' : 'text-fg-primary'">
                  {{ award.title }}
                </h3>
                <p class="text-sm text-fg-muted leading-relaxed font-light">{{ award.description }}</p>
                <div v-if="award.year" class="mt-3">
                  <span class="eyebrow-tag text-accent-cyan text-[9px]">{{ award.year }}</span>
                </div>
                <div v-if="award.galleries && award.galleries.length" class="mt-4 flex gap-2 flex-wrap">
                  <div
                    v-for="gallery in award.galleries"
                    :key="gallery.id"
                    class="w-14 h-14 rounded-lg overflow-hidden bg-bg-elevated border border-border-hairline cursor-pointer hover:border-accent-gold/30 transition-all duration-700 ease-spring"
                  >
                    <img v-if="gallery.cover_image" :src="gallery.cover_image" :alt="gallery.title" class="w-full h-full object-cover" loading="lazy" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

const tabs = [
  { key: 'projects', label: 'Projects' },
  { key: 'awards', label: 'Awards' }
]

const activeTab = ref(route.query.tab || 'projects')
const searchQuery = ref('')
const selectedCategory = ref('')

const allProjects = ref([])
const projectsLoading = ref(true)
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 })

const awards = ref([])
const awardsLoading = ref(true)

// Asymmetric 7/5 alternating grid
function getProjectSpan(index) {
  const cycle = index % 4
  if (cycle === 0) return 'md:col-span-7'
  if (cycle === 1) return 'md:col-span-5'
  if (cycle === 2) return 'md:col-span-5'
  return 'md:col-span-7'
}

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
      (p.description || '').toLowerCase().includes(q)
    )
  }
  if (selectedCategory.value) {
    result = result.filter(p =>
      (p.category?.name || p.category) === selectedCategory.value
    )
  }
  return result
})

function switchTab(key) {
  activeTab.value = key
  router.replace({ query: { tab: key } })
}

watch(() => route.query.tab, (newTab) => {
  if (newTab && newTab !== activeTab.value) {
    activeTab.value = newTab
  }
})

async function fetchProjects(page = 1) {
  projectsLoading.value = true
  try {
    const res = await api.get('/projects', { params: { per_page: 12, page } })
    allProjects.value = res.data.data || []
    if (res.data.meta) {
      pagination.value = {
        currentPage: res.data.meta.current_page,
        lastPage: res.data.meta.last_page,
        total: res.data.meta.total
      }
    }
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

onMounted(() => {
  fetchProjects()
  fetchAwards()
})
</script>
