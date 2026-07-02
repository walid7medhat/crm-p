import api from '@/plugins/axios'

function unwrap(response) {
  const body = response?.data
  if (body?.status === true) return body.data
  const msg = body?.message || 'Request failed'
  const err = new Error(msg)
  err.errors = body?.errors
  err.response = response
  throw err
}

export const aiSalesIntelligenceApi = {
  dashboard(params = {}) {
    return api.get('/ai-sales-intelligence/dashboard', { params }).then(unwrap)
  },

  agents(params = {}) {
    return api.get('/ai-sales-intelligence/agents', { params }).then(unwrap)
  },

  agentOptions() {
    return api.get('/ai-sales-intelligence/agents/options').then(unwrap)
  },

  showAgent(userId) {
    return api.get(`/ai-sales-intelligence/agents/${userId}`).then(unwrap)
  },

  neglect(userId) {
    return api.get(`/ai-sales-intelligence/agents/${userId}/neglect`).then(unwrap)
  },

  drilldown(userId) {
    return api.get(`/ai-sales-intelligence/agents/${userId}/drilldown`).then(unwrap)
  },

  alerts(params = {}) {
    return api.get('/ai-sales-intelligence/alerts', { params }).then(unwrap)
  },

  settings() {
    return api.get('/ai-sales-intelligence/settings').then(unwrap)
  },

  scoringRules() {
    return api.get('/ai-sales-intelligence/scoring-rules').then(unwrap)
  },

  updateScoringRules(payload) {
    return api.put('/ai-sales-intelligence/scoring-rules', payload).then(unwrap)
  },

  resetScoringRules() {
    return api.post('/ai-sales-intelligence/scoring-rules/reset').then(unwrap)
  },

  recalculate(payload = {}) {
    return api.post('/ai-sales-intelligence/recalculate', payload).then(unwrap)
  },
}
