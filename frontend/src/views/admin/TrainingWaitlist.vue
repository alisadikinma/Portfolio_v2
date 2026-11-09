<script setup>
import { ref, computed, reactive } from 'vue'
import {
  useWaitlistList,
  useUpdateWaitlist,
  exportWaitlistCsv,
  INTEREST_LABELS,
  STATUS_LABELS,
} from '@/composables/useTrainingWaitlistAdmin'
import { useUiStore } from '@/stores/ui'
import { formatWib } from '@/utils/wibTime'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'

const uiStore = useUiStore()

const searchQuery = ref('')
const search = ref('')
const interest = ref('')
const locale = ref('')
const status = ref('')
const currentPage = ref(1)

const filters = computed(() => ({
  search: search.value || undefined,
  interest: interest.value || undefined,
  locale: locale.value || undefined,
  status: status.value || undefined,
  per_page: 20,
  page: currentPage.value,
}))

const { rows, pagination, isLoading, error, refetch } = useWaitlistList(filters)
const updateMutation = useUpdateWaitlist()

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    search.value = searchQuery.value.trim()
    currentPage.value = 1
  }, 300)
}

function resetPage() {
  currentPage.value = 1
}

// Per-row note drafts (id -> text); absent = unchanged from server value
const noteDrafts = reactive({})
const noteValue = (row) => (row.id in noteDrafts ? noteDrafts[row.id] : row.admin_note || '')
const noteDirty = (row) => row.id in noteDrafts && noteDrafts[row.id] !== (row.admin_note || '')

async function changeStatus(row, newStatus) {
  if (newStatus === row.status) return
  try {
    await updateMutation.mutateAsync({ id: row.id, status: newStatus })
    uiStore.showSuccess(`Status ${row.name}: ${STATUS_LABELS[newStatus]}`, 'Tersimpan')
  } catch (err) {
    uiStore.showError(err.response?.data?.error?.message || 'Gagal menyimpan status.', 'Error')
  }
}

async function saveNote(row) {
  try {
    await updateMutation.mutateAsync({ id: row.id, admin_note: noteDrafts[row.id] })
    delete noteDrafts[row.id]
    uiStore.showSuccess('Catatan tersimpan.', 'Tersimpan')
  } catch (err) {
    uiStore.showError(err.response?.data?.error?.message || 'Gagal menyimpan catatan.', 'Error')
  }
}

const exporting = ref(false)
async function handleExport() {
  exporting.value = true
  try {
    await exportWaitlistCsv({
      search: search.value || undefined,
      interest: interest.value || undefined,
      locale: locale.value || undefined,
      status: status.value || undefined,
    })
  } catch (err) {
    uiStore.showError('Ekspor CSV gagal.', 'Error')
  } finally {
    exporting.value = false
  }
}

const lastPage = computed(() => pagination.value?.last_page || 1)
const selectClass =
  'w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500'
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Waitlist Pelatihan
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          {{ pagination?.total ?? 0 }} pendaftar
        </p>
      </div>
      <BaseButton button-type="secondary" :disabled="exporting || (pagination?.total ?? 0) === 0" @click="handleExport">
        {{ exporting ? 'Mengekspor…' : 'Ekspor CSV' }}
      </BaseButton>
    </div>

    <BaseCard class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <BaseInput v-model="searchQuery" type="text" placeholder="Cari nama, email, perusahaan…" @input="onSearchInput" />
        <select v-model="interest" :class="selectClass" aria-label="Filter minat" @change="resetPage">
          <option value="">Semua minat</option>
          <option v-for="(label, key) in INTEREST_LABELS" :key="key" :value="key">{{ label }}</option>
        </select>
        <select v-model="locale" :class="selectClass" aria-label="Filter bahasa" @change="resetPage">
          <option value="">Semua bahasa</option>
          <option value="id">id</option>
          <option value="en">en</option>
        </select>
        <select v-model="status" :class="selectClass" aria-label="Filter status" @change="resetPage">
          <option value="">Semua status</option>
          <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
    </BaseCard>

    <BaseCard>
      <div v-if="isLoading" class="py-12 text-center text-neutral-500" role="status">Memuat…</div>

      <div v-else-if="error" class="py-12 text-center" role="alert">
        <p class="text-red-600 mb-3">Gagal memuat data waitlist.</p>
        <BaseButton button-type="secondary" @click="refetch()">Coba lagi</BaseButton>
      </div>

      <div v-else-if="rows.length === 0" class="py-12 text-center text-neutral-500">
        Belum ada pendaftar yang cocok.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-neutral-500 border-b border-neutral-200 dark:border-neutral-700">
              <th class="py-2 pr-4">Tanggal</th>
              <th class="py-2 pr-4">Nama</th>
              <th class="py-2 pr-4">Perusahaan</th>
              <th class="py-2 pr-4">Email</th>
              <th class="py-2 pr-4">WhatsApp</th>
              <th class="py-2 pr-4">Minat</th>
              <th class="py-2 pr-4">Bahasa</th>
              <th class="py-2 pr-4">Status</th>
              <th class="py-2">Catatan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-b border-neutral-100 dark:border-neutral-800 align-top">
              <td class="py-2 pr-4 whitespace-nowrap">{{ formatWib(row.created_at) }}</td>
              <td class="py-2 pr-4">{{ row.name }}</td>
              <td class="py-2 pr-4">{{ row.company || '—' }}</td>
              <td class="py-2 pr-4">{{ row.email }}</td>
              <td class="py-2 pr-4 whitespace-nowrap">{{ row.whatsapp_number || '—' }}</td>
              <td class="py-2 pr-4">{{ INTEREST_LABELS[row.interest] || row.interest }}</td>
              <td class="py-2 pr-4 uppercase">{{ row.locale }}</td>
              <td class="py-2 pr-4">
                <select
                  :value="row.status"
                  :class="selectClass"
                  :aria-label="`Status ${row.name}`"
                  :disabled="updateMutation.isPending.value"
                  @change="changeStatus(row, $event.target.value)"
                >
                  <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
                </select>
              </td>
              <td class="py-2 min-w-[14rem]">
                <textarea
                  :value="noteValue(row)"
                  rows="2"
                  maxlength="2000"
                  :aria-label="`Catatan ${row.name}`"
                  :class="selectClass"
                  @input="noteDrafts[row.id] = $event.target.value"
                />
                <button
                  v-if="noteDirty(row)"
                  type="button"
                  class="mt-1 text-xs font-medium text-primary-600 hover:underline"
                  :disabled="updateMutation.isPending.value"
                  @click="saveNote(row)"
                >
                  Simpan catatan
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="lastPage > 1" class="flex items-center justify-between mt-4">
          <BaseButton button-type="secondary" :disabled="currentPage <= 1" @click="currentPage--">Sebelumnya</BaseButton>
          <span class="text-sm text-neutral-600 dark:text-neutral-400">Halaman {{ currentPage }} dari {{ lastPage }}</span>
          <BaseButton button-type="secondary" :disabled="currentPage >= lastPage" @click="currentPage++">Berikutnya</BaseButton>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
