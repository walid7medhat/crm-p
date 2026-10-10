import api, { getApiErrorMessage } from '@/plugins/axios'

export function motivationError(error, fallback) {
  return getApiErrorMessage(error, fallback)
}

export function fetchToday() {
  return api.get('/daily-motivation/today')
}

export function dismissToday() {
  return api.post('/daily-motivation/today/dismiss')
}

export function fetchOverview() {
  return api.get('/daily-motivation/admin/overview')
}

export function fetchMessages() {
  return api.get('/daily-motivation/admin/messages')
}

export function updateMessage(id, payload) {
  return api.put(`/daily-motivation/admin/messages/${id}`, payload)
}

export function setMessageEnabled(id, isEnabled) {
  return api.patch(`/daily-motivation/admin/messages/${id}/enabled`, { is_enabled: isEnabled })
}

export function setDelivery(enabled) {
  return api.post('/daily-motivation/admin/delivery', { enabled, confirm: true })
}

export function sendToday() {
  return api.post('/daily-motivation/admin/send-today')
}

export function previewMessage(number) {
  return api.post('/daily-motivation/admin/preview', { number })
}

export function sendTestToMe(number) {
  return api.post('/daily-motivation/admin/send-test', { number })
}

export function importWorkbook(file) {
  const formData = new FormData()
  formData.append('file', file)
  return api.post('/daily-motivation/admin/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function lookupUsers(q) {
  return api.get('/daily-motivation/admin/users', { params: { q } })
}

export function resetUserRotation(userId) {
  return api.post(`/daily-motivation/admin/users/${userId}/reset`, { confirm: true })
}
