import { ref, computed, watch } from 'vue'
import {
  fetchAnalyticsCrm,
  fetchAnalyticsDeals,
  fetchAnalyticsListing,
  fetchAnalyticsHr,
  buildPeriodParams,
} from '@/services/analyticsDashboardApi.js'

const crm = ref({})
const deals = ref({})
const listing = ref({})
const hr = ref({})

const crmLoading = ref(true)
const dealsLoading = ref(true)
const listingLoading = ref(true)
const hrLoading = ref(true)

const error = ref('')
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

  // True while any section is still on its first load — drives the top-level error/retry banner.
  const loading = computed(() => crmLoading.value || dealsLoading.value || listingLoading.value || hrLoading.value)

  // Each section loads and renders independently — a slow Listings query no longer
  // holds up the Leads band (or vice versa).
  async function loadCrm(force = false) {
    const gen = fetchGeneration
    if (!force && Object.keys(crm.value).length && !crmLoading.value) return crm.value
    crmLoading.value = true
    try {
      const payload = await fetchAnalyticsCrm(periodParams.value)
      if (gen !== fetchGeneration) return payload
      crm.value = payload
      return payload
    } catch (e) {
      if (gen !== fetchGeneration) return null
      error.value = e?.response?.data?.message || 'Failed to load analytics'
      return null
    } finally {
      if (gen === fetchGeneration) crmLoading.value = false
    }
  }

  async function loadDeals(force = false) {
    const gen = fetchGeneration
    if (!force && Object.keys(deals.value).length && !dealsLoading.value) return deals.value
    dealsLoading.value = true
    try {
      const payload = await fetchAnalyticsDeals(periodParams.value)
      if (gen !== fetchGeneration) return payload
      deals.value = payload
      return payload
    } catch (e) {
      if (gen !== fetchGeneration) return null
      error.value = e?.response?.data?.message || 'Failed to load analytics'
      return null
    } finally {
      if (gen === fetchGeneration) dealsLoading.value = false
    }
  }

  async function loadListing(force = false) {
    const gen = fetchGeneration
    if (!force && Object.keys(listing.value).length && !listingLoading.value) return listing.value
    listingLoading.value = true
    try {
      const payload = await fetchAnalyticsListing(periodParams.value)
      if (gen !== fetchGeneration) return payload
      listing.value = payload
      return payload
    } catch (e) {
      if (gen !== fetchGeneration) return null
      error.value = e?.response?.data?.message || 'Failed to load analytics'
      return null
    } finally {
      if (gen === fetchGeneration) listingLoading.value = false
    }
  }

  async function loadHr(force = false) {
    const gen = fetchGeneration
    if (!force && Object.keys(hr.value).length && !hrLoading.value) return hr.value
    hrLoading.value = true
    try {
      const payload = await fetchAnalyticsHr(periodParams.value)
      if (gen !== fetchGeneration) return payload
      hr.value = payload
      return payload
    } catch (e) {
      if (gen !== fetchGeneration) return null
      error.value = e?.response?.data?.message || 'Failed to load analytics'
      return null
    } finally {
      if (gen === fetchGeneration) hrLoading.value = false
    }
  }

  function load(force = false) {
    fetchGeneration += 1
    error.value = ''
    lastFetchedAt.value = Date.now()
    // Fire independently — each section's skeleton clears as soon as its own data lands.
    return Promise.all([
      loadCrm(force),
      loadDeals(force),
      loadListing(force),
      loadHr(force),
    ])
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
    crmLoading,
    dealsLoading,
    listingLoading,
    hrLoading,
    error,
    crm,
    deals,
    listing,
    hr,
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
