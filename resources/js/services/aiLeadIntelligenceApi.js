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
  const status = error?.response?.status
  const msg = body?.message || error?.message || 'Request failed'
  const err = new Error(msg)
  err.errors = body?.errors
  err.status = status
  err.response = error?.response
  throw err
}

function request(promise) {
  return promise.then(unwrap).catch(unwrapError)
}

export const aiLeadIntelligenceApi = {
  overview(params = {}) {
    return request(api.get('/ai-lead-intelligence/overview', { params }))
  },
  priorityLeads(params = {}) {
    return request(api.get('/ai-lead-intelligence/priority-leads', { params }))
  },
  atRisk(params = {}) {
    return request(api.get('/ai-lead-intelligence/at-risk', { params }))
  },
  propertyOpportunities(params = {}) {
    return request(api.get('/ai-lead-intelligence/property-opportunities', { params }))
  },
  neglected(params = {}) {
    return request(api.get('/ai-lead-intelligence/neglected', { params }))
  },
  actionsToday(params = {}) {
    return request(api.get('/ai-lead-intelligence/actions-today', { params }))
  },
  refresh(payload = {}) {
    return request(api.post('/ai-lead-intelligence/refresh', payload))
  },
}

export default aiLeadIntelligenceApi
