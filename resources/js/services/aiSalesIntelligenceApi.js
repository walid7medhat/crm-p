import api from '@/plugins/axios'

function unwrap(response) {
  const body = response?.data
  if (body?.status === true) return body.data
  const msg = body?.message || response?.statusText || 'Request failed'
  const err = new Error(msg)
  err.errors = body?.errors
  err.status = response?.status
  err.response = response
  throw err
}

function unwrapError(error) {
  const body = error?.response?.data
  const msg = body?.message || error?.message || 'Request failed'
  const err = new Error(msg)
  err.errors = body?.errors
  err.status = error?.response?.status
  err.response = error?.response
  throw err
}

function request(promise) {
  return promise.then(unwrap).catch(unwrapError)
}

export const aiSalesIntelligenceApi = {
  dashboard(params = {}) {
    return request(api.get('/ai-sales-intelligence/dashboard', { params }))
  },

  status() {
    return request(api.get('/ai-sales-intelligence/status'))
  },

  agents(params = {}) {
    return request(api.get('/ai-sales-intelligence/agents', { params }))
  },

  agentOptions() {
    return request(api.get('/ai-sales-intelligence/agents/options'))
  },

  showAgent(userId) {
    return request(api.get(`/ai-sales-intelligence/agents/${userId}`))
  },

  neglect(userId) {
    return request(api.get(`/ai-sales-intelligence/agents/${userId}/neglect`))
  },

  drilldown(userId) {
    return request(api.get(`/ai-sales-intelligence/agents/${userId}/drilldown`))
  },

  alerts(params = {}) {
    return request(api.get('/ai-sales-intelligence/alerts', { params }))
  },

  settings() {
    return request(api.get('/ai-sales-intelligence/settings'))
  },

  scoringRules() {
    return request(api.get('/ai-sales-intelligence/scoring-rules'))
  },

  updateScoringRules(payload) {
    return request(api.put('/ai-sales-intelligence/scoring-rules', payload))
  },

  resetScoringRules() {
    return request(api.post('/ai-sales-intelligence/scoring-rules/reset'))
  },

  recalculate(payload = {}) {
    return request(api.post('/ai-sales-intelligence/recalculate', payload, { timeout: 300000 }))
  },
}
