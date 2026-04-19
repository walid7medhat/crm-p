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

export const salesIntelligenceApi = {
  overview() {
    return api.get('/sales-intelligence/overview').then(unwrap)
  },

  agents(params = {}) {
    return api.get('/sales-intelligence/agents', { params }).then(unwrap)
  },

  settings() {
    return api.get('/sales-intelligence/settings').then(unwrap)
  },

  scoringRules() {
    return api.get('/sales-intelligence/scoring-rules').then(unwrap)
  },

  previewScore(payload) {
    return api.post('/sales-intelligence/preview-score', payload).then(unwrap)
  },

  distribute(payload) {
    return api.post('/sales-intelligence/distribute', payload).then(unwrap)
  },

  distributionLogs(params = {}) {
    return api.get('/sales-intelligence/distribution-logs', { params }).then(unwrap)
  },

  aiSuggest(payload) {
    return api.post('/sales-intelligence/ai/suggest', payload).then(unwrap)
  },
}
