import api from '@/plugins/axios'

export function fetchSystemCampaigns() {
  return api.get('/system-campaigns')
}

export function createSystemCampaign(formData) {
  return api.post('/system-campaigns', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function updateSystemCampaign(id, formData) {
  return api.post(`/system-campaigns/${id}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function updateSystemCampaignActive(id, isActive) {
  return api.patch(`/system-campaigns/${id}/active`, { is_active: isActive })
}

export function deleteSystemCampaign(id) {
  return api.delete(`/system-campaigns/${id}`)
}
