<template>
  <div class="dashboard-main-body daily-leads-report-page">
    <Breadcrumb title="Daily Leads Report" :breadcrumbs="[{ name: 'Daily Leads Report' }]" />

    <div class="dlr-shell">
      <div class="dlr-toolbar">
        <div class="dlr-filters">
          <label class="dlr-field">
            <span class="dlr-label">Day</span>
            <input v-model="date" type="date" class="dlr-input" :max="todayUae" @change="load" />
          </label>
          <label class="dlr-field dlr-field--title">
            <span class="dlr-label">File title</span>
            <input v-model="title" type="text" class="dlr-input" maxlength="150" />
          </label>
          <label class="dlr-check">
            <input v-model="hideEmpty" type="checkbox" />
            Hide agents with no leads
          </label>
        </div>
        <div class="dlr-actions">
          <button type="button" class="dlr-btn dlr-btn--ghost" :disabled="loading" @click="load">
            <iconify-icon icon="lucide:rotate-ccw" width="16" height="16" />
            Reset edits
          </button>
          <button type="button" class="dlr-btn dlr-btn--primary" :disabled="exporting || loading || !visibleRows.length" @click="exportExcel">
            <iconify-icon :icon="exporting ? 'lucide:loader-2' : 'lucide:download'" :class="{ spin: exporting }" width="16" height="16" />
            {{ exporting ? 'Exporting...' : 'Export Excel' }}
          </button>
        </div>
      </div>
      <p class="dlr-hint">
        Leads a branch admin assigned to each active sales agent on this day (UAE time), by source.
        You can fix any number or agent name, remove a row, then export — the Excel file has exactly what you see.
      </p>

      <div v-if="error" class="dlr-error">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
      </div>

      <div v-if="loading" class="dlr-empty">Loading...</div>
      <div v-else-if="!rows.length" class="dlr-empty">No agents to show.</div>

      <div v-else class="dlr-table-wrap">
        <table class="dlr-table">
          <thead>
            <tr class="dlr-sum-row">
              <th></th>
              <th class="dlr-agents">{{ visibleRows.length }} agents</th>
              <th v-for="col in columns" :key="'sum-' + col.key">{{ columnTotal(col.key) }}</th>
              <th class="dlr-total">{{ grandTotal }}</th>
              <th></th>
            </tr>
            <tr class="dlr-head-row">
              <th class="dlr-head--navy">#</th>
              <th class="dlr-head--navy dlr-name-col">Agent Name</th>
              <th v-for="col in columns" :key="col.key" :class="headClass(col.key)">{{ col.label }}</th>
              <th class="dlr-total">Total</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in visibleRows" :key="row._uid" :class="{ 'dlr-row--selected': selected.has(row._uid) }">
              <td
                class="dlr-num"
                role="button"
                tabindex="0"
                :title="selected.has(row._uid) ? 'Unselect row' : 'Select row'"
                @click="toggleRow(row)"
                @keydown.enter.prevent="toggleRow(row)"
                @keydown.space.prevent="toggleRow(row)"
              >{{ index + 1 }}</td>
              <td class="dlr-name-col">
                <input v-model="row.name" type="text" class="dlr-cell dlr-cell--name" maxlength="150" />
              </td>
              <td v-for="col in columns" :key="col.key">
                <input
                  v-model.number="row.counts[col.key]"
                  type="number"
                  min="0"
                  step="1"
                  class="dlr-cell"
                  :class="{ 'dlr-cell--edited': isEdited(row, col.key), 'dlr-cell--zero': !row.counts[col.key] }"
                  @blur="normalizeCount(row, col.key)"
                />
              </td>
              <td class="dlr-total">{{ rowTotal(row) }}</td>
              <td>
                <button type="button" class="dlr-remove" title="Remove this row from the export" @click="removeRow(row)">
                  <iconify-icon icon="lucide:x" width="14" height="14" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const todayUae = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Dubai' })

const date = ref(todayUae)
const title = ref('')
const columns = ref([])
const rows = ref([])
const hideEmpty = ref(false)
const loading = ref(false)
const exporting = ref(false)
const error = ref('')
let uid = 0

// Same header colours as the team's Excel (Bayut green, Property Finder pink, rest blue).
const headClass = (key) => ({
  'dlr-head--bayut': key === 'bayut',
  'dlr-head--pf': key === 'property_finder',
  'dlr-head--blue': key !== 'bayut' && key !== 'property_finder',
})

const toInt = (v) => {
  const n = Math.floor(Number(v))
  return Number.isFinite(n) && n > 0 ? n : 0
}

const rowTotal = (row) => columns.value.reduce((sum, col) => sum + toInt(row.counts[col.key]), 0)

const visibleRows = computed(() => (hideEmpty.value ? rows.value.filter((r) => rowTotal(r) > 0) : rows.value))

const columnTotal = (key) => visibleRows.value.reduce((sum, r) => sum + toInt(r.counts[key]), 0)
const grandTotal = computed(() => visibleRows.value.reduce((sum, r) => sum + rowTotal(r), 0))

const isEdited = (row, key) => toInt(row.counts[key]) !== toInt(row._original[key])

function normalizeCount(row, key) {
  row.counts[key] = toInt(row.counts[key])
}

function removeRow(row) {
  rows.value = rows.value.filter((r) => r._uid !== row._uid)
  if (selected.value.has(row._uid)) toggleRow(row)
}

// Click the row number to select / unselect a row (highlight, like an Excel row header).
const selected = ref(new Set())
function toggleRow(row) {
  const next = new Set(selected.value)
  if (next.has(row._uid)) next.delete(row._uid)
  else next.add(row._uid)
  selected.value = next
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/leads/reports/daily-leads', { params: { date: date.value } })
    const data = res?.data?.data || {}
    columns.value = data.columns || []
    title.value = data.title || ''
    selected.value = new Set()
    rows.value = (data.rows || []).map((r) => ({
      _uid: ++uid,
      agent_id: r.agent_id,
      name: r.name,
      counts: { ...r.counts },
      _original: { ...r.counts },
    }))
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load the report'
    rows.value = []
  } finally {
    loading.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  try {
    const payload = {
      title: title.value.trim() || 'Daily Leads Report',
      columns: columns.value.map((c) => ({ key: c.key, label: c.label })),
      rows: visibleRows.value.map((r) => ({
        name: r.name,
        counts: Object.fromEntries(columns.value.map((c) => [c.key, toInt(r.counts[c.key])])),
      })),
    }
    const res = await api.post('/leads/reports/daily-leads/export', payload, { responseType: 'blob' })
    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${payload.title.replace(/[\\/:*?"<>|]+/g, '').trim() || 'Daily Leads Report'}.xlsx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    let message = 'Could not export the report'
    const data = err?.response?.data
    if (data instanceof Blob) {
      try { message = JSON.parse(await data.text())?.message || message } catch (_) { /* keep default */ }
    } else if (data?.message) {
      message = data.message
    }
    Swal.fire({ icon: 'error', title: 'Export failed', text: message })
  } finally {
    exporting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.daily-leads-report-page.daily-leads-report-page {
  --dlr-primary: var(--color-primary);
  --dlr-primary-soft: #f4eef7;
  --dlr-text: #1e1b2e;
  --dlr-muted: #8b8798;
  --dlr-line: #ece9f1;
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.dlr-shell { border: 1px solid var(--dlr-line); background: #fff; border-radius: 14px; padding: 16px; }

.dlr-toolbar { display: flex; flex-direction: column; gap: 12px; }
.dlr-filters { display: flex; flex-direction: column; gap: 10px; }
.dlr-field { display: flex; flex-direction: column; gap: 4px; }
.dlr-label { font-size: 12px; font-weight: 600; color: var(--dlr-muted); }
.dlr-input { height: 38px; border: 1px solid var(--dlr-line); border-radius: 8px; padding: 0 10px; color: var(--dlr-text); background: #fff; }
.dlr-input:focus { outline: none; border-color: var(--dlr-primary); }
.dlr-check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--dlr-text); cursor: pointer; }
.dlr-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.dlr-hint { margin: 10px 0 14px; color: var(--dlr-muted); font-size: 12px; }

.dlr-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 38px; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; }
.dlr-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.dlr-btn--ghost { background: #fff; border-color: #e3d6ea; color: var(--dlr-primary); }
.dlr-btn--ghost:hover:not(:disabled) { background: var(--dlr-primary-soft); }
.dlr-btn--primary { background: var(--dlr-primary); color: #fff; }
.spin { animation: dlr-spin 0.9s linear infinite; }
@keyframes dlr-spin { to { transform: rotate(360deg); } }

.dlr-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.dlr-empty { padding: 24px; text-align: center; color: var(--dlr-muted); }

.dlr-table-wrap { overflow: auto; max-height: calc(100vh - 260px); border: 1px solid var(--dlr-line); border-radius: 10px; }
.dlr-table { border-collapse: collapse; min-width: 100%; font-size: 12px; }
.dlr-table th, .dlr-table td { border: 1px solid #d9d6e0; padding: 0; text-align: center; white-space: nowrap; }
.dlr-table thead th { position: sticky; z-index: 2; }
.dlr-sum-row th { top: 0; background: #faf8fc; color: var(--dlr-text); font-weight: 700; padding: 6px 8px; }
.dlr-head-row th { top: 31px; padding: 6px 8px; font-weight: 700; min-width: 86px; white-space: normal; line-height: 1.2; }
.dlr-agents { text-align: left !important; }
.dlr-head--navy { background: #1f3864; color: #fff; }
.dlr-head--blue { background: #4a86e8; color: #fff; }
.dlr-head--bayut { background: #8dc63f; color: #1e1e1e; }
.dlr-head--pf { background: #e6b8d5; color: #1e1e1e; }
.dlr-total { background: #ffff00 !important; color: #000 !important; font-weight: 700; padding: 4px 10px !important; }
.dlr-name-col { min-width: 190px; text-align: left !important; }
.dlr-num { color: var(--dlr-muted); padding: 4px 8px !important; cursor: pointer; user-select: none; }
.dlr-num:hover { background: var(--dlr-primary-soft); color: var(--dlr-primary); }
.dlr-num:focus-visible { outline: 2px solid var(--dlr-primary); outline-offset: -2px; }

/* Selected row (clicked on its number) */
.dlr-row--selected td { background: #ede4f3; }
.dlr-row--selected .dlr-num { background: var(--dlr-primary); color: #fff; font-weight: 700; }
.dlr-row--selected .dlr-total { background: #f2e600 !important; }

.dlr-cell { width: 100%; min-width: 60px; height: 30px; border: 0; background: transparent; text-align: center; color: var(--dlr-text); font-size: 12px; padding: 0 4px; -moz-appearance: textfield; }
.dlr-cell::-webkit-outer-spin-button, .dlr-cell::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.dlr-cell:focus { outline: 2px solid var(--dlr-primary); outline-offset: -2px; background: #fff; }
.dlr-cell--name { text-align: left; padding: 0 8px; font-weight: 600; }
.dlr-cell--zero { color: #c7c3cf; }
.dlr-cell--edited { background: #fff4d6; font-weight: 700; }
.dlr-remove { display: inline-flex; border: 0; background: transparent; color: var(--dlr-muted); cursor: pointer; padding: 6px; }
.dlr-remove:hover { color: #b3261e; }

@media (min-width: 768px) {
  .daily-leads-report-page.daily-leads-report-page { padding: 20px; }
  .dlr-toolbar { flex-direction: row; align-items: flex-end; justify-content: space-between; }
  .dlr-filters { flex-direction: row; align-items: flex-end; }
  .dlr-field--title .dlr-input { width: 300px; }
}
</style>
