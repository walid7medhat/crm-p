<template>
  <div class="dashboard-main-body agent-performance-page">
    <Breadcrumb title="Agent Performance" :breadcrumbs="[{ name: 'Agent Performance' }]" />

    <div class="ap-shell">
      <AgentPerformanceHeader
        :date-range-label="dateRangeLabel"
        :loading="loading"
        @refresh="fetchReport"
      />

      <AgentPerformanceFilters
        :filters="filters"
        :agent-options="agentOptions"
        :area-options="areaOptions"
        @update:filters="onFiltersUpdate"
        @change="fetchReport"
        @reset="resetFilters"
      />

      <div v-if="error" class="ap-error-banner">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
        <button type="button" class="ap-link" @click="fetchReport">Retry</button>
      </div>

      <div v-if="loading && !hasLoadedOnce" class="ap-state ap-state--loading">
        <div class="ap-skeleton-grid">
          <div v-for="n in 5" :key="n" class="ap-skeleton ap-skeleton--kpi" />
        </div>
        <div class="ap-skeleton ap-skeleton--panel" />
      </div>

      <template v-else>
        <div v-if="!agents.length" class="ap-empty-banner">
          <iconify-icon icon="lucide:info" width="16" height="16" />
          <span>No converted deals found for the selected filters. Try expanding the date range or clearing filters.</span>
        </div>

        <AgentPerformanceKpiGrid
          :summary="displaySummary"
          :avg-lead-score="teamAvgLeadScore"
          :avg-deal-value="avgDealValue"
          :avg-commission="avgCommissionPerDeal"
          :avg-agent-commission="avgAgentCommissionPerDeal"
          :avg-company-commission="avgCompanyCommissionPerDeal"
          :format-money="formatMoney"
        />

        <div class="ap-hero-grid">
          <AgentPerformanceScorePanel
            :score="displayLeadScore"
            :agent-name="selectedAgent ? focusAgent?.agent_name : ''"
            :agent-deals="focusAgent?.converted_count ?? 0"
            :agent-commission="formatMoney(focusAgent?.total_commission)"
            :score-level="scoreLevel"
          />
          <AgentPerformanceBreakdown
            :items="breakdownItems"
            :agent-name="focusAgent?.agent_name ?? ''"
          />
        </div>

        <AgentPerformanceCharts
          :commission-chart="commissionChart"
          :deals-chart="dealsChart"
          :timeline-chart="timelineChart"
        />

        <AgentPerformanceRanking
          v-if="agents.length"
          :agents="agents"
          :format-money="formatMoney"
          :score-class="scoreClass"
        />

        <AgentPerformanceDetailTable
          v-if="agents.length"
          :expanded="expanded"
          :format-money="formatMoney"
          :score-class="scoreClass"
          @toggle="toggle"
        />
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import { useAgentPerformance } from '@/composables/useAgentPerformance.js'
import AgentPerformanceHeader from './agent-performance/AgentPerformanceHeader.vue'
import AgentPerformanceFilters from './agent-performance/AgentPerformanceFilters.vue'
import AgentPerformanceKpiGrid from './agent-performance/AgentPerformanceKpiGrid.vue'
import AgentPerformanceScorePanel from './agent-performance/AgentPerformanceScorePanel.vue'
import AgentPerformanceBreakdown from './agent-performance/AgentPerformanceBreakdown.vue'
import AgentPerformanceCharts from './agent-performance/AgentPerformanceCharts.vue'
import AgentPerformanceRanking from './agent-performance/AgentPerformanceRanking.vue'
import AgentPerformanceDetailTable from './agent-performance/AgentPerformanceDetailTable.vue'

const hasLoadedOnce = ref(false)

const {
  agents,
  summary,
  loading,
  error,
  expanded,
  agentOptions,
  areaOptions,
  filters,
  selectedAgent,
  focusAgent,
  teamAvgLeadScore,
  displayLeadScore,
  avgDealValue,
  avgCommissionPerDeal,
  avgAgentCommissionPerDeal,
  avgCompanyCommissionPerDeal,
  dateRangeLabel,
  breakdownItems,
  commissionChart,
  dealsChart,
  timelineChart,
  formatMoney,
  scoreLevel,
  scoreClass,
  toggle,
  resetFilters,
  fetchReport: loadReport,
  init,
} = useAgentPerformance()

const displaySummary = computed(() => summary.value ?? {
  agents_count: 0,
  deals_count: 0,
  total_amount: 0,
  total_commission: 0,
  total_agent_commission: 0,
  total_company_commission: 0,
})

function onFiltersUpdate(next) {
  Object.assign(filters, next)
}

async function fetchReport() {
  await loadReport()
  hasLoadedOnce.value = true
}

onMounted(() => {
  init().finally(() => {
    hasLoadedOnce.value = true
  })
})
</script>
