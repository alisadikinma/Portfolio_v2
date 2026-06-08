<template>
  <section
    class="latest-writing relative w-full overflow-hidden bg-[var(--bg-elevated,#0C0C0F)] px-6 py-24 lg:px-20 lg:py-32"
    aria-label="Latest writing"
  >
    <div class="relative z-10 mx-auto w-full max-w-7xl">
      <!-- Header -->
      <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
        <div>
          <p
            class="mb-3 text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-gold,#D4A843)]"
            style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
          >
            latest writing
          </p>
          <h2
            class="max-w-3xl text-3xl font-bold leading-tight text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-5xl"
            style="font-family: 'Space Grotesk', sans-serif;"
          >
            I write to
            <span class="text-[var(--accent-gold,#D4A843)]">think in public.</span>
          </h2>
        </div>
        <RouterLink
          to="/blog"
          class="hidden items-center gap-2 rounded-[10px] border border-[var(--fg-muted,#8A8F98)]/45 px-5 py-3 text-sm font-medium text-[var(--fg-primary,#EDEDEF)] transition-colors duration-200 hover:border-[var(--accent-gold,#D4A843)] hover:bg-white/5 md:inline-flex"
          style="font-family: 'Inter', sans-serif;"
        >
          Read the blog →
        </RouterLink>
      </div>

      <!-- Content Engine meta-flex -->
      <div
        class="meta-flex mb-12 flex flex-col gap-3 rounded-2xl border border-[var(--accent-cyan,#06B6D4)]/30 p-6 sm:flex-row sm:items-center lg:p-7"
      >
        <span class="text-3xl" aria-hidden="true">⚙️</span>
        <div>
          <p
            class="mb-1 text-[0.66rem] uppercase tracking-[0.2em] text-[var(--accent-cyan,#06B6D4)]"
            style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
          >
            built in public · AI Content Engine
          </p>
          <p
            class="text-sm leading-relaxed text-[var(--fg-primary,#EDEDEF)]/90 lg:text-[0.98rem]"
            style="font-family: 'Inter', sans-serif; font-weight: 300;"
          >
            This blog <span class="font-medium text-[var(--accent-cyan,#06B6D4)]">writes itself.</span>
            Every post is researched, written, scored, and illustrated by my own
            AI Content Engine — the same kind of agentic system I build for clients.
          </p>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="isLoading" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="n in 3"
          :key="n"
          class="h-72 animate-pulse rounded-2xl border border-white/5 bg-[var(--bg-deep,#050506)]"
        ></div>
      </div>

      <!-- Empty / error fallback -->
      <p
        v-else-if="!articles.length"
        class="text-[var(--fg-muted,#8A8F98)]"
        style="font-family: 'Inter', sans-serif;"
      >
        Fresh posts are on the way — head to the
        <RouterLink to="/blog" class="text-[var(--accent-gold,#D4A843)] underline">blog</RouterLink>.
      </p>

      <!-- Article cards -->
      <div v-else class="card-rail -mx-6 flex snap-x snap-mandatory gap-6 overflow-x-auto px-6 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-3">
        <RouterLink
          v-for="post in articles"
          :key="post.id"
          :to="`/blog/${post.slug}`"
          class="article-card group relative flex w-[82%] shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-white/10 bg-[var(--bg-deep,#050506)] sm:w-auto sm:shrink"
        >
          <div class="relative aspect-[16/9] w-full overflow-hidden bg-black/40">
            <BaseImage
              v-if="post.featured_image"
              :src="post.featured_image"
              :variants="post.image_variants"
              :alt="post.title"
              class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.04]"
            />
            <div
              v-else
              class="flex h-full w-full items-center justify-center text-[var(--fg-muted,#8A8F98)]"
              aria-hidden="true"
            >✎</div>
          </div>

          <div class="flex flex-1 flex-col p-5 lg:p-6">
            <div class="mb-2 flex items-center gap-3">
              <span
                v-if="categoryLabel(post)"
                class="text-[0.64rem] uppercase tracking-[0.18em] text-[var(--accent-cyan,#06B6D4)]"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
              >{{ categoryLabel(post) }}</span>
              <time
                v-if="post.published_at || post.created_at"
                :datetime="post.published_at || post.created_at"
                class="text-[0.64rem] tracking-[0.08em] text-[var(--fg-muted,#8A8F98)]"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
              >{{ formatDate(post.published_at || post.created_at) }}</time>
            </div>

            <h3
              class="mb-2 line-clamp-2 text-lg font-semibold leading-snug text-[var(--fg-primary,#EDEDEF)] lg:text-xl"
              style="font-family: 'Space Grotesk', sans-serif;"
            >
              {{ post.title }}
            </h3>

            <p
              v-if="post.excerpt"
              class="line-clamp-3 text-sm leading-relaxed text-[var(--fg-muted,#8A8F98)]"
              style="font-family: 'Inter', sans-serif; font-weight: 300;"
            >
              {{ post.excerpt }}
            </p>
          </div>
        </RouterLink>
      </div>

      <!-- Mobile blog link -->
      <RouterLink
        to="/blog"
        class="mt-8 flex items-center justify-center gap-2 rounded-[10px] border border-[var(--fg-muted,#8A8F98)]/45 px-5 py-3 text-sm font-medium text-[var(--fg-primary,#EDEDEF)] md:hidden"
        style="font-family: 'Inter', sans-serif;"
      >
        Read the blog →
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

const articles = computed(() => (data.value?.latest_articles ?? []).slice(0, 3))

function categoryLabel(p) {
  const c = p?.category
  if (!c) return ''
  return typeof c === 'string' ? c : (c.name ?? '')
}

function formatDate(value) {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<style scoped>
/* Mobile horizontal card rail — hide scrollbar (snap-scroll affords the gesture) */
.card-rail::-webkit-scrollbar { display: none; }
.card-rail { -ms-overflow-style: none; scrollbar-width: none; }
.meta-flex {
  background:
    radial-gradient(110% 100% at 0% 0%, rgba(6, 182, 212, 0.10), transparent 60%),
    var(--bg-deep, #050506);
}
.article-card {
  transition: transform 200ms ease-out, border-color 200ms ease-out;
}
.article-card:hover {
  transform: translateY(-3px);
  border-color: rgba(212, 168, 67, 0.35);
}
@media (prefers-reduced-motion: reduce) {
  .article-card { transition: none; }
  .article-card:hover { transform: none; }
}
</style>
