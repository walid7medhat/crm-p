import { ref, computed, unref } from 'vue'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'

export function useDistribution() {
  const logsRaw = ref([])
  const logsMeta = ref({})
  const loadingLogs = ref(false)
  const simulating = ref(false)
  const simulateError = ref(null)
  const lastResult = ref(null)

  async function fetchLogs(perPage = 50) {
    loadingLogs.value = true
    try {
      const paginator = await salesIntelligenceApi.distributionLogs({ per_page: perPage })
      logsRaw.value = paginator?.data || []
      logsMeta.value = {
        current_page: paginator?.current_page,
        last_page: paginator?.last_page,
        total: paginator?.total,
      }
    } catch {
      logsRaw.value = []
      logsMeta.value = {}
    } finally {
      loadingLogs.value = false
    }
  }

  async function simulate({ leadId, mode, manualUserId, dryRun = true }) {
    simulating.value = true
    simulateError.value = null
    lastResult.value = null
    try {
      const body = { lead_id: leadId, dry_run: dryRun }
      if (mode) body.mode = mode
      if (manualUserId) body.manual_user_id = manualUserId
      const data = await salesIntelligenceApi.distribute(body)
      lastResult.value = data
      return data
    } catch (e) {
      simulateError.value = e?.message || 'Simulation failed'
      throw e
    } finally {
      simulating.value = false
    }
  }

  return {
    logsRaw,
    logsMeta,
    loadingLogs,
    simulating,
    simulateError,
    lastResult,
    fetchLogs,
    simulate,
  }
}

/**
 * @param {import('vue').Ref | import('vue').ComputedRef} logsRef
 * @param {import('vue').Ref<{ method?: string, agentId?: string|number, from?: string, to?: string }>} filtersRef
 */
export function useFilteredLogs(logsRef, filtersRef) {
  return computed(() => {
    const f = filtersRef?.value || {}
    let rows = unref(logsRef) || []
    if (f.method) {
      rows = rows.filter((r) => String(r.method || '').toLowerCase() === String(f.method).toLowerCase())
    }
    if (f.agentId) {
      rows = rows.filter((r) => Number(r.assigned_to) === Number(f.agentId))
    }
    if (f.from) {
      const t = new Date(f.from).getTime()
      rows = rows.filter((r) => new Date(r.created_at).getTime() >= t)
    }
    if (f.to) {
      const t = new Date(f.to).getTime()
      rows = rows.filter((r) => new Date(r.created_at).getTime() <= t + 86400000)
    }
    return rows
  })
}
