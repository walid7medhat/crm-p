import { ref, computed, watch } from 'vue'
import { fetchAnalyticsOverview, buildPeriodParams } from '@/services/analyticsDashboardApi.js'

const loading = ref(true)
const error = ref('')
const data = ref(null)
const period = ref('monthly')
const dateFrom = ref('')
const dateTo = ref('')
const lastFetchedAt = ref(null)

let fetchGeneration = 0

export function useAnalyticsDashboard() {
  const periodParams = computed(() => buildPeriodParams({
    period: period.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
  }))

  const periodLabel = computed(() => {
    const labels = {
      today: 'Today',
      weekly: 'This week',
      monthly: 'This month',
      yearly: 'This year',
      custom: 'Custom range',
    }
    return labels[period.value] || 'This month'
  })

  async function load(force = false) {
    const gen = ++fetchGeneration
    if (!force && data.value && !loading.value) return data.value
    loading.value = true
    error.value = ''
    try {
      const payload = await fetchAnalyticsOverview(periodParams.value)
      if (gen !== fetchGeneration) return payload
      data.value = payload
      lastFetchedAt.value = Date.now()
      return payload
    } catch (e) {
      if (gen !== fetchGeneration) return null
      error.value = e?.response?.data?.message || 'Failed to load analytics'
      return null
    } finally {
      if (gen === fetchGeneration) loading.value = false
    }
  }

  function setPeriod(next) {
    period.value = next
    if (next !== 'custom') {
      dateFrom.value = ''
      dateTo.value = ''
    }
  }

  function setCustomRange(from, to) {
    period.value = 'custom'
    dateFrom.value = from || ''
    dateTo.value = to || ''
  }

  watch(periodParams, () => {
    load(true)
  })

  return {
    loading,
    error,
    data,
    period,
    dateFrom,
    dateTo,
    periodLabel,
    periodParams,
    lastFetchedAt,
    load,
    setPeriod,
    setCustomRange,
  }
}
