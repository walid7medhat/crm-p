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

export function buildPeriodParams({ period, dateFrom, dateTo }) {
  const params = { period: period || 'monthly' }
  if (period === 'custom' && dateFrom) params.date_from = dateFrom
  if (period === 'custom' && dateTo) params.date_to = dateTo
  return params
}
