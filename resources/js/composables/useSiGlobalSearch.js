import { ref, unref } from 'vue'
import api from '@/plugins/axios'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'
import { flattenLeadsFromStages, unwrapLeadsResponse } from '@/pages/sales-intelligence/siSearchUtils'

export function useSiGlobalSearch(rulesRef) {
  const query = ref('')
  const open = ref(false)
  const loading = ref(false)
  const agents = ref([])
  const leads = ref([])
  const ruleHits = ref([])
  let timer = null

  function close() {
    open.value = false
    query.value = ''
    agents.value = []
    leads.value = []
    ruleHits.value = []
  }

  async function runSearch(q) {
    const s = (q || '').trim()
    if (s.length < 1) {
      agents.value = []
      leads.value = []
      ruleHits.value = []
      return
    }
    loading.value = true
    try {
      const [agentList, leadRes] = await Promise.all([
        salesIntelligenceApi.agents({ q: s }).catch(() => []),
        api.get('/leads', { params: { search: s, per_page: 40 } }).catch(() => ({ data: {} })),
      ])
      agents.value = (agentList || []).slice(0, 8).map((a) => ({
        type: 'agent',
        id: a.id,
        title: a.name,
        subtitle: a.email || '',
        rank: a.rank,
        score: a.score,
      }))

      const raw = unwrapLeadsResponse(leadRes)
      leads.value = flattenLeadsFromStages(raw)
        .filter((l) => {
          const hay = `${l.label} ${l.subtitle}`.toLowerCase()
          return hay.includes(s.toLowerCase())
        })
        .slice(0, 8)
        .map((l) => ({
          type: 'lead',
          id: l.id,
          title: l.label,
          subtitle: l.subtitle,
        }))

      const rl = unref(rulesRef) || []
      const low = s.toLowerCase()
      ruleHits.value = rl
        .filter((r) => {
          const name = String(r.factor_name || '').toLowerCase().replace(/_/g, ' ')
          return name.includes(low)
        })
        .slice(0, 8)
        .map((r) => ({
          type: 'rule',
          id: r.id,
          title: String(r.factor_name || '').replace(/_/g, ' '),
          subtitle: `weight ${Math.round(Number(r.weight) * 1000) / 10}%`,
        }))
    } finally {
      loading.value = false
    }
  }

  function scheduleSearch(q) {
    clearTimeout(timer)
    timer = setTimeout(() => runSearch(q), 280)
  }

  return {
    query,
    open,
    loading,
    agents,
    leads,
    rules: ruleHits,
    scheduleSearch,
    runSearch,
    close,
  }
}
