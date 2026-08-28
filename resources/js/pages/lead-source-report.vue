<template>
  <div class="dashboard-main-body lead-source-report-page">
    <Breadcrumb title="Leads by Source" :breadcrumbs="[{ name: 'Leads by Source' }]" />

    <div class="lsr-shell">
      <div class="lsr-toolbar">
        <div class="lsr-filters">
          <input type="date" v-model="dateFrom" class="lsr-input" />
          <span class="lsr-sep">to</span>
          <input type="date" v-model="dateTo" class="lsr-input" />
          <button type="button" class="lsr-btn lsr-btn--ghost" @click="fetchReport" :disabled="loading">
            <iconify-icon icon="lucide:filter" width="16" height="16" />
            Apply
          </button>
          <button type="button" class="lsr-btn lsr-btn--ghost" @click="clearFilters" :disabled="loading">
            Clear
          </button>
        </div>

        <button type="button" class="lsr-btn lsr-btn--primary" @click="downloadReport" :disabled="downloading">
          <iconify-icon :icon="downloading ? 'lucide:loader-2' : 'lucide:download'" :class="{ spin: downloading }" width="16" height="16" />
          {{ downloading ? 'Downloading...' : 'Download Excel' }}
        </button>
      </div>

      <div v-if="error" class="lsr-error">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
        <button type="button" class="lsr-link" @click="fetchReport">Retry</button>
      </div>

      <div v-if="loading" class="lsr-loading">Loading report...</div>

      <template v-else>
        <div v-if="!stages.length" class="lsr-empty">No data found for the selected filters.</div>

        <div v-if="grandSelectedCount > 0" class="lsr-selected-bar">
          <span>{{ grandSelectedCount }} source{{ grandSelectedCount > 1 ? 's' : '' }} selected</span>
          <strong>{{ grandSelectedTotal }} leads</strong>
          <button type="button" class="lsr-link" @click="clearSelection">Clear selection</button>
        </div>

        <div v-for="stage in stages" :key="stage.stage_id" class="lsr-stage-card">
          <div class="lsr-stage-head">
            <span class="lsr-stage-dot" :style="{ background: stage.stage_color || '#7c3aed' }" />
            <h3>{{ stage.stage_name }}</h3>
            <span v-if="stageSelectedCount(stage) > 0" class="lsr-stage-selected">Selected: {{ stageSelectedTotal(stage) }}</span>
            <span class="lsr-stage-total">{{ stage.total }} leads</span>
          </div>

          <table class="lsr-table">
            <thead>
              <tr>
                <th class="lsr-th-check">
                  <input
                    type="checkbox"
                    :checked="isStageAllSelected(stage)"
                    @change="toggleStageAll(stage, $event.target.checked)"
                  />
                </th>
                <th>Source</th>
                <th>Count</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in stage.sources" :key="row.source">
                <td class="lsr-td-check">
                  <input
                    type="checkbox"
                    :checked="isRowSelected(stage.stage_id, row.source)"
                    @change="toggleRow(stage.stage_id, row.source, row.count, $event.target.checked)"
                  />
                </td>
                <td>{{ row.source }}</td>
                <td>{{ row.count }}</td>
              </tr>
              <tr v-if="!stage.sources.length">
                <td colspan="3" class="lsr-no-rows">No leads in this stage</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const stages = ref([])
const loading = ref(false)
const downloading = ref(false)
const error = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const buildParams = () => ({
  ...(dateFrom.value ? { date_from: dateFrom.value } : {}),
  ...(dateTo.value ? { date_to: dateTo.value } : {}),
})

// key: `${stageId}::${source}` -> count, for the currently checked rows
const selected = reactive({})
const rowKey = (stageId, source) => `${stageId}::${source}`

const isRowSelected = (stageId, source) => Object.prototype.hasOwnProperty.call(selected, rowKey(stageId, source))

const toggleRow = (stageId, source, count, checked) => {
  const key = rowKey(stageId, source)
  if (checked) {
    selected[key] = count
  } else {
    delete selected[key]
  }
}

const isStageAllSelected = (stage) => {
  if (!stage.sources.length) return false
  return stage.sources.every((row) => isRowSelected(stage.stage_id, row.source))
}

const toggleStageAll = (stage, checked) => {
  stage.sources.forEach((row) => toggleRow(stage.stage_id, row.source, row.count, checked))
}

const stageSelectedCount = (stage) => stage.sources.filter((row) => isRowSelected(stage.stage_id, row.source)).length

const stageSelectedTotal = (stage) =>
  stage.sources.reduce((sum, row) => (isRowSelected(stage.stage_id, row.source) ? sum + row.count : sum), 0)

const grandSelectedCount = computed(() => Object.keys(selected).length)
const grandSelectedTotal = computed(() => Object.values(selected).reduce((sum, count) => sum + count, 0))

const clearSelection = () => {
  Object.keys(selected).forEach((key) => delete selected[key])
}

const fetchReport = async () => {
  loading.value = true
  error.value = ''
  clearSelection()
  try {
    const response = await api.get('/leads/reports/leads-by-source', { params: buildParams() })
    stages.value = response?.data?.data?.report || []
  } catch (err) {
    error.value = err?.response?.data?.message || 'Failed to load report'
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  dateFrom.value = ''
  dateTo.value = ''
  fetchReport()
}

const downloadReport = async () => {
  downloading.value = true
  try {
    const response = await api.get('/leads/reports/leads-by-source/export', {
      params: buildParams(),
      responseType: 'blob',
    })
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'leads-by-source-report.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Download failed',
      text: err?.response?.data?.message || 'Could not download the report',
    })
  } finally {
    downloading.value = false
  }
}

onMounted(fetchReport)
</script>

<style scoped>
.lead-source-report-page.lead-source-report-page {
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.lsr-shell { border: 1px solid #d9deea; background: #ffffff !important; border-radius: 14px; padding: 16px; }

.lsr-toolbar { display: flex; flex-direction: column; align-items: stretch; gap: 12px; margin-bottom: 16px; }
.lsr-filters { display: flex; flex-direction: column; align-items: stretch; gap: 8px; }
.lsr-input { height: 38px; width: 100%; border: 1px solid #ebeef3; border-radius: 8px; padding: 0 10px; color: #10152f; background: #fff; box-sizing: border-box; }
.lsr-sep { color: #8390a7; font-size: 13px; }

.lsr-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 38px; width: 100%; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; box-sizing: border-box; }
.lsr-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.lsr-btn--ghost { background: #fff; border-color: #ebeef3; color: #10152f; }
.lsr-btn--primary { background: #020b38; color: #fff; }
.spin { animation: lsr-spin 0.9s linear infinite; }
@keyframes lsr-spin { to { transform: rotate(360deg); } }

.lsr-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.lsr-link { border: none; background: transparent; color: #b3261e; text-decoration: underline; cursor: pointer; margin-left: auto; }
.lsr-loading, .lsr-empty { padding: 24px; text-align: center; color: #8390a7; }

.lsr-selected-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #eef1ff; border: 1px solid #d3d9ff; color: #10152f; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; }
.lsr-selected-bar strong { font-size: 15px; }
.lsr-selected-bar .lsr-link { margin-left: auto; color: #3547ff; }

.lsr-stage-card { background: #fff; border: 1px solid #ebeef3; border-radius: 12px; padding: 14px; margin-bottom: 14px; }
.lsr-stage-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.lsr-stage-head h3 { margin: 0; font-size: 16px; color: #10152f; }
.lsr-stage-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.lsr-stage-selected { font-size: 12px; color: #3547ff; font-weight: 600; }
.lsr-stage-total { margin-left: auto; font-size: 12px; color: #8390a7; font-weight: 600; }

.lsr-table { width: 100%; border-collapse: collapse; }
.lsr-table th { text-align: left; font-size: 12px; color: #8390a7; text-transform: uppercase; padding: 8px 10px; border-bottom: 1px solid #ebeef3; }
.lsr-table td { padding: 10px; border-bottom: 1px solid #f2f4f8; font-size: 13px; color: #10152f; }
.lsr-th-check, .lsr-td-check { width: 36px; padding-right: 0 !important; }
.lsr-th-check input, .lsr-td-check input {
  width: 17px;
  height: 17px;
  cursor: pointer;
  accent-color: #3547ff;
  border-radius: 4px;
}
.lsr-th-check input:hover, .lsr-td-check input:hover { accent-color: #263bd6; }
.lsr-no-rows { text-align: center; color: #8390a7; }

@media (min-width: 768px) {
  .lead-source-report-page.lead-source-report-page { padding: 20px; }
  .lsr-toolbar { flex-direction: row; align-items: center; justify-content: space-between; }
  .lsr-filters { flex-direction: row; align-items: center; }
  .lsr-input { width: 160px; }
  .lsr-btn { width: auto; }
  .lsr-sep { padding: 0 2px; }
}
</style>
