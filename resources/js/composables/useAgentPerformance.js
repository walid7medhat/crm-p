import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

function currentMonthRange() {
  const now = new Date()
  const toStr = (d) => d.toISOString().slice(0, 10)
  const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
  return { from: toStr(firstOfMonth), to: toStr(now) }
}

export function useAgentPerformance() {
  const agents = ref([])
  const summary = ref(null)
  const loading = ref(false)
  const error = ref('')
  const expanded = ref(new Set())
  const agentOptions = ref([])
  const areaOptions = ref([])

  const defaultRange = currentMonthRange()
  const filters = reactive({
    from_date: defaultRange.from,
    to_date: defaultRange.to,
    agent_id: '',
    area_id: '',
    status: 'completed',
  })

  const selectedAgent = computed(() => {
    if (!filters.agent_id) return null
    return agents.value.find((a) => String(a.agent_id) === String(filters.agent_id)) || null
  })

  const focusAgent = computed(() => selectedAgent.value || agents.value[0] || null)

  const teamAvgLeadScore = computed(() => {
    const scores = agents.value.map((a) => a.avg_lead_score).filter((s) => s != null)
    if (!scores.length) return null
    return Math.round((scores.reduce((sum, s) => sum + s, 0) / scores.length) * 10) / 10
  })

  const displayLeadScore = computed(() => {
    if (selectedAgent.value) return selectedAgent.value.avg_lead_score
    return teamAvgLeadScore.value
  })

  const avgDealValue = computed(() => {
    const count = summary.value?.deals_count || 0
    if (!count) return null
    return Math.round((summary.value.total_amount / count) * 100) / 100
  })

  const avgCommissionPerDeal = computed(() => {
    const count = summary.value?.deals_count || 0
    if (!count) return null
    return Math.round((summary.value.total_commission / count) * 100) / 100
  })

  const avgAgentCommissionPerDeal = computed(() => {
    const count = summary.value?.deals_count || 0
    if (!count) return null
    return Math.round((summary.value.total_agent_commission / count) * 100) / 100
  })

  const avgCompanyCommissionPerDeal = computed(() => {
    const count = summary.value?.deals_count || 0
    if (!count) return null
    return Math.round((summary.value.total_company_commission / count) * 100) / 100
  })

  const dateRangeLabel = computed(() => {
    if (!filters.from_date || !filters.to_date) return ''
    return `${filters.from_date} — ${filters.to_date}`
  })

  const breakdownItems = computed(() => {
    const agent = focusAgent.value
    if (!agent) return []

    const maxDeals = Math.max(...agents.value.map((a) => a.converted_count), 1)
    const maxAmount = Math.max(...agents.value.map((a) => a.total_amount), 1)
    const maxAgentCommission = Math.max(...agents.value.map((a) => a.total_agent_commission ?? 0), 1)
    const maxCompanyCommission = Math.max(...agents.value.map((a) => a.total_company_commission ?? 0), 1)

    return [
      {
        key: 'deals',
        label: 'Converted Deals',
        value: agent.converted_count,
        display: String(agent.converted_count),
        percent: Math.round((agent.converted_count / maxDeals) * 100),
        icon: 'lucide:handshake',
      },
      {
        key: 'value',
        label: 'Deal Value',
        value: agent.total_amount,
        display: formatMoney(agent.total_amount),
        percent: Math.round((agent.total_amount / maxAmount) * 100),
        icon: 'lucide:building-2',
      },
      {
        key: 'agent_commission',
        label: 'Agent Commission',
        value: agent.total_agent_commission ?? 0,
        display: formatMoney(agent.total_agent_commission ?? 0),
        percent: Math.round(((agent.total_agent_commission ?? 0) / maxAgentCommission) * 100),
        icon: 'lucide:coins',
      },
      {
        key: 'company_commission',
        label: 'Company Commission',
        value: agent.total_company_commission ?? 0,
        display: formatMoney(agent.total_company_commission ?? 0),
        percent: Math.round(((agent.total_company_commission ?? 0) / maxCompanyCommission) * 100),
        icon: 'lucide:building',
      },
      {
        key: 'score',
        label: 'Avg Lead Score',
        value: agent.avg_lead_score ?? 0,
        display: agent.avg_lead_score != null ? String(agent.avg_lead_score) : '—',
        percent: agent.avg_lead_score != null ? Math.min(100, Math.round(agent.avg_lead_score)) : 0,
        icon: 'lucide:target',
      },
    ]
  })

  const commissionChart = computed(() => {
    const list = agents.value.slice(0, 12)
    return {
      categories: list.map((a) => a.agent_name),
      series: [{ name: 'Commission', data: list.map((a) => a.total_commission) }],
    }
  })

  const dealsChart = computed(() => {
    const list = agents.value.slice(0, 12)
    return {
      categories: list.map((a) => a.agent_name),
      series: [{ name: 'Deals', data: list.map((a) => a.converted_count) }],
    }
  })

  const timelineChart = computed(() => {
    const buckets = new Map()
    for (const agent of agents.value) {
      for (const deal of agent.deals || []) {
        const date = deal.converted_at
        if (!date) continue
        buckets.set(date, (buckets.get(date) || 0) + 1)
      }
    }
    const sorted = [...buckets.entries()].sort(([a], [b]) => a.localeCompare(b))
    return {
      categories: sorted.map(([d]) => d),
      series: [{ name: 'Conversions', data: sorted.map(([, v]) => v) }],
    }
  })

  function formatMoney(value, currency = 'AED') {
    if (value === null || value === undefined) return '—'
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency || 'AED',
      maximumFractionDigits: 0,
    }).format(value)
  }

  function scoreLevel(score) {
    if (score === null || score === undefined) return { label: 'No data', tone: 'muted' }
    if (score >= 70) return { label: 'Excellent', tone: 'excellent' }
    if (score >= 40) return { label: 'Good', tone: 'good' }
    return { label: 'Needs Improvement', tone: 'low' }
  }

  function scoreClass(score) {
    if (score === null || score === undefined) return ''
    if (score >= 70) return 'score-pill--hot'
    if (score >= 40) return 'score-pill--warm'
    return 'score-pill--cold'
  }

  function toggle(agentId) {
    expanded.value.has(agentId) ? expanded.value.delete(agentId) : expanded.value.add(agentId)
    expanded.value = new Set(expanded.value)
  }

  function resetFilters() {
    const range = currentMonthRange()
    filters.from_date = range.from
    filters.to_date = range.to
    filters.agent_id = ''
    filters.area_id = ''
    fetchReport()
  }

  async function fetchAgents() {
    try {
      const { data } = await api.get('listings/agents')
      const list = Array.isArray(data) ? data : data.data || []
      agentOptions.value = list.map((a) => ({ agent_id: a.id, agent_name: a.name }))
    } catch (e) {
      console.error('Failed to load agents', e)
    }
  }

  async function fetchAreas() {
    try {
      const { data } = await api.get('listings/areas')
      areaOptions.value = Array.isArray(data) ? data : data.data || []
    } catch (e) {
      console.error('Failed to load areas', e)
    }
  }

  async function fetchReport() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('leads/reports/agent-performance', { params: filters })
      agents.value = Array.isArray(data?.data) ? data.data : []
      summary.value = data?.summary ?? {
        agents_count: 0,
        deals_count: 0,
        total_amount: 0,
        total_commission: 0,
        total_agent_commission: 0,
        total_company_commission: 0,
      }
      if (agents.value.length && expanded.value.size === 0) {
        expanded.value = new Set([agents.value[0].agent_id])
      }
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to load the report.'
    } finally {
      loading.value = false
    }
  }

  async function init() {
    await Promise.all([fetchAgents(), fetchAreas()])
    await fetchReport()
  }

  return {
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
    fetchReport,
    init,
  }
}
