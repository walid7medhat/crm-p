import api from '@/plugins/axios'

const emptyPayload = () => ({
  scope: { role: 'personal', team_size: 0, period: 'monthly' },
  crm: {},
  deals: {},
  listing: {},
  hr: {},
  finance: {},
  support: {},
  ai_insights: [],
  notifications: [],
})

/**
 * Primary analytics endpoint — backed by DashboardController::getAnalyticsOverview.
 * Falls back to empty structure if the request fails (UI still renders skeletons/empty states).
 */
export async function fetchAnalyticsOverview(params = {}) {
  try {
    const res = await api.get('/dashboard/analytics-overview', { params })
    return res.data?.success ? res.data : { ...emptyPayload(), ...res.data }
  } catch (error) {
    console.error('[analytics] overview failed', error)
    throw error
  }
}

/**
 * Per-section analytics endpoints — same underlying query logic as the overview above,
 * but fetched independently so each dashboard band can render as soon as its own
 * (fast) data is ready instead of waiting on the slowest section in one combined call.
 */
export async function fetchAnalyticsCrm(params = {}) {
  const res = await api.get('/dashboard/analytics-overview/crm', { params })
  return res.data?.crm || {}
}

export async function fetchAnalyticsDeals(params = {}) {
  const res = await api.get('/dashboard/analytics-overview/deals', { params })
  return res.data?.deals || {}
}

export async function fetchAnalyticsListing(params = {}) {
  const res = await api.get('/dashboard/analytics-overview/listing', { params })
  return res.data?.listing || {}
}

export async function fetchAnalyticsHr(params = {}) {
  const res = await api.get('/dashboard/analytics-overview/hr', { params })
  return res.data?.hr || {}
}

export function buildPeriodParams({ period, dateFrom, dateTo }) {
  const params = { period: period || 'monthly' }
  if (period === 'custom' && dateFrom) params.date_from = dateFrom
  if (period === 'custom' && dateTo) params.date_to = dateTo
  return params
}
