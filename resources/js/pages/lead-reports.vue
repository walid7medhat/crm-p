<template>
  <div class="dashboard-main-body lead-reports-page">
    <div class="lead-reports-shell">
      <LeadReportsTopBar v-model:activeTab="activeTab" />
      <template v-if="activeTab === 'leads'">
        <LeadReportsFilters
          v-model:searchQuery="searchQuery"
          v-model:branch="branch"
          v-model:dateRange="dateRange"
          :stage="selectedStage"
          :branch-options="branches"
          @open-advanced="showAdvanced = true"
          @submit="applyFilters"
          @clear="clearFilters"
          @reset="resetFilters"
        />
        <template v-if="!detailsMode">
          <LeadReportsKpiCards :cards="kpiCards" @select-stage="openStageDetails" />
          <LeadReportsTable
            title="Leads Reports"
            v-model:search="tableSearch"
            :rows="paginatedRows"
            :current-page="currentPage"
            :total-pages="totalPages"
            @update:page="currentPage = $event"
            @refresh="applyFilters"
          />
        </template>
        <template v-else>
          <div class="stage-back">
            <button type="button" @click="detailsMode = false">
              <iconify-icon icon="lucide:chevron-left" /> Go Back
            </button>
          </div>
          <div class="stage-head">
            <div class="stage-value">{{ stageHeadlineValue }}</div>
            <h3>{{ selectedStage }} Leads</h3>
          </div>
          <LeadReportsTable
            :title="`${selectedStage} Leads Reports`"
            v-model:search="tableSearch"
            :rows="paginatedRows"
            :current-page="currentPage"
            :total-pages="totalPages"
            @update:page="currentPage = $event"
            @refresh="applyFilters"
          />
        </template>
      </template>
      <section v-else class="deal-placeholder">
        <h3>Deals Reports</h3>
        <p>Use the same shell and filters; bind real deals data when API is ready.</p>
      </section>
    </div>

    <div v-if="showAdvanced" class="advanced-backdrop" @click.self="showAdvanced = false">
      <div class="advanced-modal">
        <header>
          <h4>Advanced Filter</h4>
          <button type="button" @click="showAdvanced = false"><iconify-icon icon="lucide:x" /></button>
        </header>
        <label>Select Stage</label>
        <select v-model="selectedStage">
          <option value="">Not Selected</option>
          <option v-for="item in stages" :key="item" :value="item">{{ item }}</option>
        </select>
        <div class="modal-actions">
          <button class="cancel" @click="showAdvanced = false">Cancel</button>
          <button class="apply" @click="applyAdvanced">Apply</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import LeadReportsTopBar from '@/components/lead-reports/LeadReportsTopBar.vue'
import LeadReportsFilters from '@/components/lead-reports/LeadReportsFilters.vue'
import LeadReportsKpiCards from '@/components/lead-reports/LeadReportsKpiCards.vue'
import LeadReportsTable from '@/components/lead-reports/LeadReportsTable.vue'
import { branches, kpiCards, reportRows, stages } from '@/components/lead-reports/mockData'

const activeTab = ref('leads')
const branch = ref('All Team')
const dateRange = ref('Last Month')
const selectedStage = ref('')
const searchQuery = ref('')
const tableSearch = ref('')
const currentPage = ref(1)
const pageSize = 10
const detailsMode = ref(false)
const showAdvanced = ref(false)

const filteredRows = computed(() => {
  const q = `${searchQuery.value} ${tableSearch.value}`.trim().toLowerCase()
  return reportRows.filter((row) => {
    const branchMatch = branch.value === 'All Team' || row.branch === branch.value
    const stageMatch = !selectedStage.value || row.stage === selectedStage.value
    const text = `${row.leadName} ${row.responsibleName} ${row.source}`.toLowerCase()
    const textMatch = !q || text.includes(q)
    return branchMatch && stageMatch && textMatch
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / pageSize)))
const paginatedRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredRows.value.slice(start, start + pageSize)
})

const stageHeadlineValue = computed(() => {
  const card = kpiCards.find((item) => item.title.toLowerCase().includes(selectedStage.value.toLowerCase()))
  return card?.value ?? filteredRows.value.length
})

const applyFilters = () => { currentPage.value = 1 }
const clearFilters = () => { searchQuery.value = ''; tableSearch.value = '' }
const resetFilters = () => {
  branch.value = 'All Team'
  dateRange.value = 'Last Month'
  selectedStage.value = ''
  clearFilters()
  detailsMode.value = false
  applyFilters()
}
const applyAdvanced = () => { showAdvanced.value = false; applyFilters() }
const openStageDetails = (card) => {
  selectedStage.value = card.title.replace(' Leads', '')
  detailsMode.value = true
  applyFilters()
}
</script>

<style scoped>
.lead-reports-page { background: #dfe2ee; min-height: 100vh; padding: 12px; }
.lead-reports-shell { border: 1px solid #d9deea; background: #f8f9fc; border-radius: 14px; overflow: hidden; }
.stage-back { padding: 0 16px; margin-bottom: 12px; }
.stage-back button { border: none; background: transparent; color: #8390a7; font-size: 13px; display: flex; align-items: center; gap: 6px; }
.stage-head { border: 1px solid #ebeef3; background: #fff; border-radius: 14px; margin: 0 16px 12px; padding: 14px; }
.stage-head .stage-value { font-size: 44px; line-height: 1; font-weight: 700; color: #10152f; }
.stage-head h3 { margin: 4px 0 0; font-size: 22px; color: #10152f; }
.deal-placeholder { margin: 16px; border: 1px solid #ebeef3; border-radius: 14px; background: #fff; padding: 24px; }
.advanced-backdrop { position: fixed; inset: 0; background: rgba(9, 14, 34, 0.42); display: grid; place-items: center; z-index: 1000; }
.advanced-modal { width: 420px; border-radius: 12px; background: #fff; padding: 12px; border: 1px solid #ebeef3; }
.advanced-modal header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.advanced-modal h4 { margin: 0; font-size: 16px; color: #10152f; }
.advanced-modal header button { border: none; background: transparent; color: #7d8393; }
.advanced-modal label { display: block; font-size: 12px; color: #10152f; margin-bottom: 5px; font-weight: 600; }
.advanced-modal select { width: 100%; height: 40px; border: 1px solid #ebeef3; border-radius: 10px; padding: 0 10px; color: #5f6678; }
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
.modal-actions button { border-radius: 18px; height: 34px; border: 1px solid #ebeef3; padding: 0 14px; font-size: 12px; }
.modal-actions .apply { background: #020b38; color: #fff; border-color: #020b38; }
</style>
