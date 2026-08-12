import api from '@/plugins/axios'

export async function fetchAnnouncements(params = {}) {
  const response = await api.get('/announcements', { params })
  return response?.data?.data
}

export async function getAnnouncement(id) {
  const response = await api.get(`/announcements/${id}`)
  return response?.data?.data
}

export async function createAnnouncement(data) {
  const response = await api.post('/announcements', data)
  return response?.data?.data
}

export async function updateAnnouncement(id, data) {
  const response = await api.put(`/announcements/${id}`, data)
  return response?.data?.data
}

export async function deleteAnnouncement(id) {
  const response = await api.delete(`/announcements/${id}`)
  return response?.data
}

export async function fetchAnnouncementStatistics() {
  const response = await api.get('/announcements/statistics')
  return response?.data?.data
}