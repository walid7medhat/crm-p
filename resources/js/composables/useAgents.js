import { ref, shallowRef } from 'vue'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'

export function useAgents() {
  const agents = shallowRef([])
  const loading = ref(false)
  const error = ref(null)

  async function fetchAgents(query = '') {
    loading.value = true
    error.value = null
    try {
      const q = typeof query === 'string' ? query.trim() : ''
      agents.value = await salesIntelligenceApi.agents(q ? { q } : {})
    } catch (e) {
      error.value = e?.message || 'Failed to load agents'
      agents.value = []
    } finally {
      loading.value = false
    }
  }

  return {
    agents,
    loading,
    error,
    fetchAgents,
  }
}
