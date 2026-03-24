<template>
  <div class="dashboard-main-body lead-reports-page">
    <div class="lead-reports-shell">
      <LeadReportsTopBar v-model:activeTab="activeTab" />
      <template v-if="activeTab === 'leads'">
        <LeadReportsFilters
          v-model:searchQuery="searchQuery"
          v-model:branch="branch"
          v-model:stage="selectedStage"
          v-model:dateRange="dateRange"
          :branch-options="branchOptions"
          :stage-options="stageList.map((item) => item.name)"
          @open-advanced="showAdvanced = true"
          @submit="applyFilters"
          @clear="clearFilters"
          @reset="resetFilters"
        />
        <template v-if="!detailsMode">
          <LeadReportsKpiCards :cards="kpiCardsData" @select-stage="openStageDetails" />
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
          <option v-for="item in stageList" :key="item.id || item.name" :value="item.name">{{ item.name }}</option>
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import LeadReportsTopBar from '@/components/lead-reports/LeadReportsTopBar.vue'
import LeadReportsFilters from '@/components/lead-reports/LeadReportsFilters.vue'
import LeadReportsKpiCards from '@/components/lead-reports/LeadReportsKpiCards.vue'
import LeadReportsTable from '@/components/lead-reports/LeadReportsTable.vue'
import { stages as mockStages } from '@/components/lead-reports/mockData'

const activeTab = ref('leads')
const branch = ref('All Team')
const dateRange = ref('')
const selectedStage = ref('')
const searchQuery = ref('')
const tableSearch = ref('')
const currentPage = ref(1)
const pageSize = 10
const detailsMode = ref(false)
const showAdvanced = ref(false)
const rows = ref([])
const allLeads = ref([])
const branchOptions = ref(['All Team'])
const stageList = ref([])
let searchDebounceTimer = null

const filteredRows = computed(() => {
  const q = tableSearch.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter((row) =>
    `${row.leadName} ${row.responsibleName} ${row.source}`.toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / pageSize)))
const paginatedRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredRows.value.slice(start, start + pageSize)
})

const kpiCardsData = computed(() => {
  const countBy = (matcher) => allLeads.value.filter((lead) => matcher((lead.stage?.name || '').toLowerCase())).length
  return [
    { key: 'total', stageName: '', title: 'Total leads', value: allLeads.value.length, trend: 'Live', delta: '100%', positive: true, icon: 'lucide:users' },
    { key: 'follow', stageName: 'Follow Up / Contacted', title: 'Follow Ups', value: countBy((s) => s.includes('follow')), trend: 'Live', delta: 'API', positive: true, icon: 'lucide:message-circle' },
    { key: 'qualified', stageName: 'Qualified', title: 'Qualified Leads', value: countBy((s) => s.includes('qualified')), trend: 'Live', delta: 'API', positive: true, icon: 'lucide:user-check' },
    { key: 'unqualified', stageName: 'Unqualified', title: 'Unqualified Leads', value: countBy((s) => s.includes('unqualified') || s.includes('lost')), trend: 'Live', delta: 'API', positive: false, icon: 'lucide:user-x' },
    { key: 'converted', stageName: 'Converted', title: 'Converted Leads', value: countBy((s) => s.includes('converted')), trend: 'Live', delta: 'API', positive: true, icon: 'lucide:refresh-cw' }
  ]
})

const stageHeadlineValue = computed(() => filteredRows.value.length)

const parseDateRange = (value) => {
  const now = new Date()
  const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const endOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const asYmd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  if (!value) return {}

  if (value.includes(' to ')) {
    const [from, to] = value.split(' to ').map((v) => v.trim())
    return { created_from: from, created_to: to }
  }

  let from = null
  let to = null
  if (value === 'Today') {
    from = startOfDay(now); to = endOfDay(now)
  } else if (value === 'Yesterday') {
    const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1)
    from = startOfDay(d); to = endOfDay(d)
  } else if (value === 'This Week') {
    from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay())
    to = new Date(from.getFullYear(), from.getMonth(), from.getDate() + 6)
  } else if (value === 'Last Week') {
    to = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay() - 1)
    from = new Date(to.getFullYear(), to.getMonth(), to.getDate() - 6)
  } else if (value === 'This Month') {
    from = new Date(now.getFullYear(), now.getMonth(), 1)
    to = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  } else if (value === 'Last Month') {
    from = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    to = new Date(now.getFullYear(), now.getMonth(), 0)
  } else if (value === 'This Year') {
    from = new Date(now.getFullYear(), 0, 1)
    to = new Date(now.getFullYear(), 11, 31)
  } else if (value === 'Last Year') {
    from = new Date(now.getFullYear() - 1, 0, 1)
    to = new Date(now.getFullYear() - 1, 11, 31)
  }

  return from && to ? { created_from: asYmd(from), created_to: asYmd(to) } : {}
}

const normalizeArray = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (payload && typeof payload === 'object') return Object.values(payload)
  return []
}

const fetchFilterOptions = async () => {
  try {
    const [stagesRes, branchesRes] = await Promise.all([
      api.get('/stages'),
      api.get('/get/lead/branch_source')
    ])
    const stagesData = normalizeArray(stagesRes?.data?.data || stagesRes?.data)
    const leadStages = stagesData.filter((item) => !item.stage_type || item.stage_type === 'lead')
    stageList.value = leadStages
    const branchData = normalizeArray(branchesRes?.data?.data || branchesRes?.data)
    const branchNames = branchData.map((item) => item?.name).filter(Boolean)
    branchOptions.value = ['All Team', ...branchNames]
  } catch (error) {
    stageList.value = mockStages.map((name, idx) => ({ id: idx + 1, name }))
    branchOptions.value = ['All Team']
  }
}

const flattenLeadsFromGroups = (groups) => {
  if (!Array.isArray(groups)) return []
  return groups.flatMap((group) => normalizeArray(group?.leads))
}

const fetchLeadRows = async () => {
  const search = `${searchQuery.value} ${tableSearch.value}`.trim()
  const selectedStageObj = stageList.value.find((item) => item.name === selectedStage.value)
  const dateParams = parseDateRange(dateRange.value)
  const params = {
    ...(search ? { search } : {}),
    ...(selectedStageObj?.id ? { stage_id: selectedStageObj.id } : {}),
    ...dateParams
  }

  const response = await api.get('/leads', { params })
  let groups = normalizeArray(response?.data?.data || response?.data)
  let leads = flattenLeadsFromGroups(groups)

  // If a date range preset returns no data, fallback to all-time so table is not blank by default.
  if (leads.length === 0 && Object.keys(dateParams).length > 0) {
    const retry = await api.get('/leads', {
      params: {
        ...(search ? { search } : {}),
        ...(selectedStageObj?.id ? { stage_id: selectedStageObj.id } : {})
      }
    })
    groups = normalizeArray(retry?.data?.data || retry?.data)
    leads = flattenLeadsFromGroups(groups)
  }
  allLeads.value = leads

  const mapped = leads.map((lead) => {
    const created = lead?.created_at ? new Date(lead.created_at) : null
    const createdOn = created && !Number.isNaN(created.getTime())
      ? created.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
      : '-'
    return {
      id: lead.id,
      createdOn,
      leadName: lead.lead_name || '-',
      responsibleName: lead?.responsible_person?.name || '-',
      responsibleEmail: lead?.responsible_person?.email || '-',
      closingDate: createdOn,
      source: lead.lead_source || '-',
      stage: lead?.stage?.name || '',
      branch: lead.lead_branch_source || 'All Team'
    }
  })

  rows.value = mapped.filter((row) => {
    const branchMatch = branch.value === 'All Team' || row.branch === branch.value
    const stageMatch = !selectedStage.value || row.stage === selectedStage.value
    return branchMatch && stageMatch
  })
}

const applyFilters = async () => {
  currentPage.value = 1
  await fetchLeadRows()
}
const clearFilters = async () => {
  searchQuery.value = ''
  tableSearch.value = ''
  await applyFilters()
}
const resetFilters = () => {
  branch.value = 'All Team'
  dateRange.value = ''
  selectedStage.value = ''
  rows.value = []
  allLeads.value = []
  detailsMode.value = false
  clearFilters()
}
const applyAdvanced = async () => { showAdvanced.value = false; await applyFilters() }
const openStageDetails = (card) => {
  selectedStage.value = card.stageName || card.title.replace(' Leads', '')
  detailsMode.value = true
  applyFilters()
}

onMounted(async () => {
  await fetchFilterOptions()
  await applyFilters()
})

watch(tableSearch, () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    applyFilters()
  }, 350)
})

onBeforeUnmount(() => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
})
</script>

<style scoped>
.lead-reports-page { background: #dfe2ee; min-height: 100vh; padding: 12px; }
.lead-reports-shell { border: 1px solid #d9deea; background: #f8f9fc; border-radius: 14px; overflow: visible; position: relative; }
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
