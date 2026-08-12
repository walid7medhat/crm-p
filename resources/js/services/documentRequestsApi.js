import api from '@/plugins/axios'

export async function fetchDocumentTypes(params = {}) {
  const { data } = await api.get('/document-types', { params })
  return data?.data ?? data
}

export async function createDocumentType(payload) {
  const { data } = await api.post('/document-types', payload)
  return data?.data ?? data
}

export async function updateDocumentType(id, payload) {
  const { data } = await api.put(`/document-types/${id}`, payload)
  return data?.data ?? data
}

export async function deleteDocumentType(id) {
  const { data } = await api.delete(`/document-types/${id}`)
  return data
}

export async function fetchDocumentRequests(params = {}) {
  const { data } = await api.get('/document-requests', { params })
  return data?.data ?? data
}

export async function getDocumentRequest(id) {
  const { data } = await api.get(`/document-requests/${id}`)
  return data?.data ?? data
}

export async function createDocumentRequest(payload) {
  const { data } = await api.post('/document-requests/store/new', payload)
  return data?.data ?? data
}

export async function updateDocumentRequest(id, payload) {
  const { data } = await api.put(`/document-requests/${id}`, payload)
  return data?.data ?? data
}

export async function deleteDocumentRequest(id) {
  const { data } = await api.delete(`/document-requests/${id}`)
  return data
}

export async function approveDocumentRequest(id, file) {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await api.post(`/document-requests/${id}/approve`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data?.data ?? data
}

export async function rejectDocumentRequest(id, rejectionReason) {
  const { data } = await api.post(`/document-requests/${id}/reject`, {
    rejection_reason: rejectionReason,
  })
  return data?.data ?? data
}

export async function fetchDocumentRequestStatistics() {
  const { data } = await api.get('/document-requests/statistics')
  return data?.data ?? data
}