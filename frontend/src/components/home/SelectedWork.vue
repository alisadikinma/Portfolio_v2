<template>
  <section
    class="selected-work relative w-full overflow-hidden bg-[var(--bg-deep,#050506)] px-6 py-24 lg:px-20 lg:py-32"
    aria-label="Selected work"
  >
    <div class="relative z-10 mx-auto w-full max-w-7xl">
      <!-- Header -->
      <div class="mb-12 flex flex-wrap items-end justify-between gap-6">
        <div>
          <p
            class="mb-3 text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-gold,#D4A843)]"
            style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
          >
            selected work
          </p>
          <h2
            class="max-w-3xl text-3xl font-bold leading-tight text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-5xl"
            style="font-family: 'Space Grotesk', sans-serif;"
          >
            What I've
            <span class="text-[var(--accent-gold,#D4A843)]">actually shipped.</span>
          </h2>
        </div>
        <RouterLink
          to="/projects"
          class="hidden items-center gap-2 rounded-[10px] border border-[var(--fg-muted,#8A8F98)]/45 px-5 py-3 text-sm font-medium text-[var(--fg-primary,#EDEDEF)] transition-colors duration-200 hover:border-[var(--accent-gold,#D4A843)] hover:bg-white/5 md:inline-flex"
          style="font-family: 'Inter', sans-serif;"
        >
          All {{ allProjectsLabel }} projects →
        </RouterLink>
      </div>

      <!-- Loading skeleton -->
      <div v-if="isLoading" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="n in 3"
          :key="n"
          class="h-80 animate-pulse rounded-2xl border border-white/5 bg-[var(--bg-elevated,#0C0C0F)]"
        ></div>
      </div>

      <!-- Empty / error fallback -->
      <p
        v-else-if="!projects.length"
        class="text-[var(--fg-muted,#8A8F98)]"
        style="font-family: 'Inter', sans-serif;"
      >
        Projects are loading — meanwhile, browse the
        <RouterLink to="/projects" class="text-[var(--accent-gold,#D4A843)] underline">full catalogue</RouterLink>.
      </p>

      <!-- Project cards -->
      <div v-else class="card-rail -mx-6 flex snap-x snap-mandatory gap-6 overflow-x-auto px-6 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-3">
        <RouterLink
          v-for="project in projects"
          :key="project.id"
          :to="`/projects/${project.slug}`"
          class="work-card group relative flex w-[82%] shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-white/10 bg-[var(--bg-elevated,#0C0C0F)] sm:w-auto sm:shrink"
        >
          <!-- Image -->
          <div class="relative aspect-[16/10] w-full overflow-hidden bg-black/40">
            <BaseImage
              v-if="project.image || project.featured_image"
              :src="project.image || project.featured_image"
              :variants="project.image_variants"
              :alt="project.title"
              class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.04]"
            />
            <div
              v-else
              class="flex h-full w-full items-center justify-center text-[var(--fg-muted,#8A8F98)]"
              aria-hidden="true"
            >▦</div>
          </div>

          <!-- Body -->
          <div class="flex flex-1 flex-col p-5 lg:p-6">
            <span
              v-if="categoryLabel(project)"
              class="mb-2 text-[0.66rem] uppercase tracking-[0.18em] text-[var(--accent-cyan,#06B6D4)]"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ categoryLabel(project) }}
            </span>

            <h3
              class="mb-2 text-lg font-semibold leading-snug text-[var(--fg-primary,#EDEDEF)] lg:text-xl"
              style="font-family: 'Space Grotesk', sans-serif;"
            >
              {{ project.title }}
            </h3>

            <p
              v-if="excerpt(project)"
              class="mb-4 line-clamp-3 text-sm leading-relaxed text-[var(--fg-muted,#8A8F98)]"
              style="font-family: 'Inter', sans-serif; font-weight: 300;"
            >
              {{ excerpt(project) }}
            </p>

            <!-- Tech chips -->
            <ul
              v-if="techOf(project).length"
              class="mt-auto flex flex-wrap gap-2 pt-1"
              aria-label="Tech stack"
            >
              <li
                v-for="tech in techOf(project)"
                :key="tech"
                class="rounded-full border border-white/10 bg-white/[0.03] px-2.5 py-1 text-[0.66rem] tracking-[0.06em] text-[var(--fg-primary,#EDEDEF)]/75"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
              >
                {{ tech }}
              </li>
            </ul>
          </div>
        </RouterLink>
      </div>

      <!-- Mobile all-projects link -->
      <RouterLink
        to="/projects"
        class="mt-8 flex items-center justify-center gap-2 rounded-[10px] border border-[var(--fg-muted,#8A8F98)]/45 px-5 py-3 text-sm font-medium text-[var(--fg-primary,#EDEDEF)] md:hidden"
        style="font-family: 'Inter', sans-serif;"
      >
        All {{ allProjectsLabel }} projects →
      </RouterLink>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useHomepageFeatured } from '@/composables/useHomepageFeatured'
import BaseImage from '@/components/base/BaseImage.vue'

const { data, isLoading } = useHomepageFeatured()

const projects = computed(() => data.value?.featured_projects ?? [])

// "All 56 projects →" — real count from stats; falls back to "all" if absent.
const projectsCount = computed(() => data.value?.stats?.projects_count ?? null)
const allProjectsLabel = computed(() =>
  projectsCount.value ? `${projectsCount.value}` : 'all'
)

function categoryLabel(p) {
  const c = p?.category
  if (!c) return ''
  return typeof c === 'string' ? c : (c.name ?? '')
}

function excerpt(p) {
  const raw = (p?.description || '').replace(/<[^>]*>/g, '').trim()
  return raw
}

function techOf(p) {
  const t = p?.technologies
  if (!Array.isArray(t)) return []
  return t.slice(0, 4).map((x) => (typeof x === 'string' ? x : x?.name)).filter(Boolean)
}
</script>

<style scoped>
/* Mobile horizontal card rail — hide scrollbar (snap-scroll affords the gesture) */
.card-rail::-webkit-scrollbar { display: none; }
.card-rail { -ms-overflow-style: none; scrollbar-width: none; }
.work-card {
  transition: transform 200ms ease-out, border-color 200ms ease-out;
}
.work-card:hover {
  transform: translateY(-3px);
  border-color: rgba(212, 168, 67, 0.35);
}
@media (prefers-reduced-motion: reduce) {
  .work-card { transition: none; }
  .work-card:hover { transform: none; }
}
</style>
