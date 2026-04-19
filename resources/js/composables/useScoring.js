import { ref } from 'vue'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'

export function cloneRules(rules) {
  return (rules || []).map((r) => ({
    id: r.id,
    factor_name: r.factor_name,
    weight: Number(r.weight),
    low_value: r.low_value != null ? Number(r.low_value) : null,
    medium_value: r.medium_value != null ? Number(r.medium_value) : null,
    high_value: r.high_value != null ? Number(r.high_value) : null,
    direction: r.direction || 'higher_better',
  }))
}

export function useScoring() {
  const rules = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function fetchRules() {
    loading.value = true
    error.value = null
    try {
      const data = await salesIntelligenceApi.scoringRules()
      rules.value = cloneRules(data)
    } catch (e) {
      error.value = e?.message || 'Failed to load scoring rules'
      rules.value = []
    } finally {
      loading.value = false
    }
  }

  return {
    rules,
    loading,
    error,
    fetchRules,
  }
}
