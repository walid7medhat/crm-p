<template>
  <div class="dashboard-main-body lead-export-page">
    <Breadcrumb title="Export Leads" :breadcrumbs="[{ name: 'Export Leads' }]" />

    <div class="lex-shell">
      <form class="lex-toolbar" @submit.prevent="searchNow">
        <div class="lex-search">
          <iconify-icon
            :icon="loading ? 'lucide:loader-2' : 'lucide:search'"
            :class="['lex-search-icon', { spin: loading }]"
            width="16"
            height="16"
          />
          <input
            v-model="term"
            type="text"
            class="lex-input"
            placeholder="Search leads, e.g. ohana"
            autocomplete="off"
          />
          <button v-if="term" type="button" class="lex-search-clear" aria-label="Clear search" @click="term = ''">
            <iconify-icon icon="lucide:x" width="14" height="14" />
          </button>
        </div>
        <button
          type="button"
          class="lex-btn lex-btn--primary"
          :disabled="downloading || !searchedTerm || !total"
          @click="downloadExcel"
        >
          <iconify-icon :icon="downloading ? 'lucide:loader-2' : 'lucide:download'" :class="{ spin: downloading }" width="16" height="16" />
          {{ downloading ? 'Exporting...' : 'Export Excel' }}
        </button>
      </form>
      <p class="lex-hint">
        Matches the text in: lead name, first name, last name, source, referral client name, source information and comment.
        The Excel file has: Lead Name, Name, Email.
      </p>

      <div v-if="error" class="lex-error">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
      </div>

      <div v-if="loading" class="lex-empty">Searching...</div>

      <template v-else-if="searchedTerm">
        <div class="lex-summary">
          <strong>{{ formatNumber(total) }}</strong>
          lead{{ total === 1 ? '' : 's' }} match "{{ searchedTerm }}"
          <span v-if="total > rows.length" class="lex-muted">— showing the newest {{ rows.length }}; the Excel file has all {{ formatNumber(total) }}.</span>
        </div>

        <div v-if="!rows.length" class="lex-empty">No leads match this search.</div>

        <div v-else class="lex-table-wrap">
          <table class="lex-table">
            <thead>
              <tr>
                <th>Lead Name</th>
                <th>Name</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" class="lex-row" @click="openLeadView(row.id)">
                <td>{{ row.lead_name || '—' }}</td>
                <td>{{ row.name || '—' }}</td>
                <td>{{ row.email || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div v-else class="lex-empty">Type at least 2 characters to search.</div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
import { openLeadView } from '@/composables/useLeadViewModal.js'

const term = ref('')
const searchedTerm = ref('')
const rows = ref([])
const total = ref(0)
const loading = ref(false)
const downloading = ref(false)
const error = ref('')

const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)

// Searches as you type (400 ms after the last keystroke) — no Search button.
// `seq` drops replies from older keystrokes that arrive after a newer one.
let debounceTimer = null
let seq = 0

function resetResults() {
  rows.value = []
  total.value = 0
  searchedTerm.value = ''
  error.value = ''
}

async function search() {
  const q = term.value.trim()
  if (q.length < 2) {
    seq++
    loading.value = false
    resetResults()
    return
  }
  const mySeq = ++seq
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/leads/reports/lead-search', { params: { q } })
    if (mySeq !== seq) return
    const data = res?.data?.data || {}
    rows.value = data.rows || []
    total.value = Number(data.total) || 0
    searchedTerm.value = q
  } catch (err) {
    if (mySeq !== seq) return
    error.value = err?.response?.data?.message || 'Search failed'
    rows.value = []
    total.value = 0
    searchedTerm.value = ''
  } finally {
    if (mySeq === seq) loading.value = false
  }
}

// Enter searches right away (skips the wait).
function searchNow() {
  clearTimeout(debounceTimer)
  search()
}

watch(term, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(search, 400)
})

onBeforeUnmount(() => clearTimeout(debounceTimer))

async function downloadExcel() {
  if (!searchedTerm.value) return
  downloading.value = true
  try {
    const res = await api.get('/leads/reports/lead-search/export', {
      params: { q: searchedTerm.value },
      responseType: 'blob',
    })
    const blob = new Blob([res.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const slug = searchedTerm.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'search'
    link.download = `leads-${slug}.xlsx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Export failed',
      text: err?.response?.data?.message || 'Could not export the leads',
    })
  } finally {
    downloading.value = false
  }
}
</script>

<style scoped>
.lead-export-page.lead-export-page {
  --lex-primary: #733e87;
  --lex-primary-soft: #f4eef7;
  --lex-text: #1e1b2e;
  --lex-muted: #8b8798;
  --lex-line: #ece9f1;
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.lex-shell { border: 1px solid var(--lex-line); background: #fff; border-radius: 14px; padding: 16px; }

.lex-toolbar { display: flex; flex-direction: column; gap: 8px; }
.lex-search { position: relative; display: flex; align-items: center; width: 100%; max-width: 320px; }
.lex-search-icon { position: absolute; left: 10px; color: var(--lex-muted); pointer-events: none; }
.lex-input { height: 38px; width: 100%; border: 1px solid var(--lex-line); border-radius: 8px; padding: 0 30px 0 32px; color: var(--lex-text); background: #fff; box-sizing: border-box; }
.lex-search-clear { position: absolute; right: 6px; display: inline-flex; border: none; background: transparent; color: var(--lex-muted); cursor: pointer; padding: 4px; }
.lex-search-clear:hover { color: var(--lex-text); }
.lex-input:focus { outline: none; border-color: var(--lex-primary); }
.lex-hint { margin: 8px 0 16px; color: var(--lex-muted); font-size: 12px; }

.lex-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 38px; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; }
.lex-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.lex-btn--ghost { background: #fff; border-color: #e3d6ea; color: var(--lex-primary); }
.lex-btn--ghost:hover:not(:disabled) { background: var(--lex-primary-soft); }
.lex-btn--primary { background: var(--lex-primary); color: #fff; }
.spin { animation: lex-spin 0.9s linear infinite; }
@keyframes lex-spin { to { transform: rotate(360deg); } }

.lex-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.lex-empty { padding: 24px; text-align: center; color: var(--lex-muted); }
.lex-summary { margin-bottom: 12px; font-size: 13px; color: var(--lex-text); }
.lex-summary strong { font-size: 16px; color: var(--lex-primary); }
.lex-muted { color: var(--lex-muted); }

.lex-table-wrap { overflow-x: auto; }
.lex-table { width: 100%; border-collapse: collapse; min-width: 520px; }
.lex-table th { text-align: left; font-size: 12px; color: var(--lex-muted); text-transform: uppercase; padding: 8px 10px; border-bottom: 1px solid var(--lex-line); }
.lex-table td { padding: 10px; border-bottom: 1px solid #f4f2f7; font-size: 13px; color: var(--lex-text); }
.lex-row { cursor: pointer; }
.lex-row:hover td { background: #faf8fc; }

@media (min-width: 768px) {
  .lead-export-page.lead-export-page { padding: 20px; }
  .lex-toolbar { flex-direction: row; align-items: center; justify-content: space-between; }
}
</style>
