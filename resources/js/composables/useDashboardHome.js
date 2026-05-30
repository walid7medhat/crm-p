import { ref, computed } from 'vue'
import api from '@/plugins/axios'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl.js'
import { toDateOnlyApiString, parseToDate } from '@/composables/useAdvancedDateModel.js'

const defaultStats = () => ({
  total_agents: 0,
  agents_change: 0,
  owners: 0,
  owners_change: 0,
  my_requests: 0,
  my_orders: 0,
  orders_change: 0,
  requests_change: 0,
  total_listings: 0,
})

function defaultDateRange() {
  const end = new Date()
  const start = new Date()
  start.setMonth(start.getMonth() - 2)
  return {
    from: toDateOnlyApiString(start),
    to: toDateOnlyApiString(end),
  }
}

const initialRange = defaultDateRange()

/** Shared dashboard state (navbar date picker + home page) */
const loading = ref(true)
const stats = ref(defaultStats())
const listingsChart = ref({ labels: [], values: [] })
const performanceChart = ref({
  categories: [],
  values: [],
  points: [],
  y_max: 250,
  x_title: '(Agents)',
})
const listingsRing = ref({ sold_out: 0, active: 0, inactive: 0, total: 0 })
const taskSummary = ref({ new: 0, assigned: 0, deal_won: 0 })
const topAgents = ref([])
const schedule = ref({ label: '', items: [] })
const chartPeriod = ref('monthly')
const dateFrom = ref(initialRange.from)
const dateTo = ref(initialRange.to)

export function useDashboardHome() {
  const user = computed(() => {
    try {
      return JSON.parse(localStorage.getItem('user') || 'null')
    } catch {
      return null
    }
  })

  const greetingName = computed(() => {
    const name = user.value?.name || 'User'
    return name.split(' ')[0]
  })

  const isAdminUser = computed(() => {
    const role = user.value?.role_name || ''
    const roles = user.value?.roles || []
    return role === 'admin' || role === 'super_admin' || roles.includes('admin') || roles.includes('super_admin')
  })

  const dateRangeLabel = computed(() => {
    const fmt = (ymd) => {
      const d = parseToDate(ymd)
      if (!d) return ''
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    }
    const a = fmt(dateFrom.value)
    const b = fmt(dateTo.value)
    if (a && b) return `${a} - ${b}`
    return 'Select date range'
  })

  const dateParams = computed(() => ({
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }))

  const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)

  const trendPercent = (change, total) => {
    const c = Number(change) || 0
    const t = Number(total) || 0
    const previous = Math.max(t - c, 0)
    if (previous <= 0) return c > 0 ? '100.00' : '0.00'
    return ((c / previous) * 100).toFixed(2)
  }

  const avatarUrl = (raw) => normalizePublicStorageUrl(raw) || normalizePublicStorageUrl('users/user.png')

  const fetchStats = async () => {
    const res = await api.get('/dashboard/stats', { params: dateParams.value })
    stats.value = { ...defaultStats(), ...(res.data?.data || {}) }
  }

  const fetchListingsChart = async () => {
    const res = await api.get('/dashboard/listings-statistics', {
      params: { period: chartPeriod.value, ...dateParams.value },
    })
    listingsChart.value = res.data?.chart_data || { labels: [], values: [] }
    performanceChart.value = {
      categories: [],
      values: [],
      points: [],
      y_max: 250,
      x_title: '(Agents)',
      ...(res.data?.performance_chart || {}),
    }
  }

  const fetchListingsRing = async () => {
    const res = await api.get('/dashboard/listings-status-summary', { params: dateParams.value })
    listingsRing.value = { sold_out: 0, active: 0, inactive: 0, total: 0, ...(res.data?.data || {}) }
  }

  const fetchTaskSummary = async () => {
    const res = await api.get('/dashboard/kanban-task-summary', { params: dateParams.value })
    const d = res.data?.data || {}
    taskSummary.value = {
      new: d.new ?? 0,
      assigned: d.assigned ?? 0,
      deal_won: d.deal_won ?? 0,
    }
  }

  const fetchTopAgents = async () => {
    const res = await api.get('/dashboard/top-agent-performance', { params: dateParams.value })
    topAgents.value = Array.isArray(res.data?.data) ? res.data.data : []
  }

  const fetchSchedule = async () => {
    const res = await api.get('/dashboard/schedule', {
      params: { date: dateTo.value, ...dateParams.value },
    })
    schedule.value = {
      label: res.data?.data?.label || '',
      items: res.data?.data?.items || [],
    }
  }

  const loadAll = async () => {
    loading.value = true
    try {
      await Promise.all([
        fetchStats(),
        fetchListingsChart(),
        fetchListingsRing(),
        fetchTaskSummary(),
        fetchTopAgents(),
        fetchSchedule(),
      ])
    } catch (e) {
      console.error('Dashboard load failed', e)
    } finally {
      loading.value = false
    }
  }

  const applyDateRange = async () => {
    await loadAll()
  }

  const reloadChart = async () => {
    await fetchListingsChart()
  }

  return {
    loading,
    stats,
    listingsChart,
    performanceChart,
    listingsRing,
    taskSummary,
    topAgents,
    schedule,
    chartPeriod,
    dateFrom,
    dateTo,
    dateRangeLabel,
    greetingName,
    isAdminUser,
    formatNumber,
    trendPercent,
    avatarUrl,
    reloadChart,
    applyDateRange,
    loadAll,
  }
}
