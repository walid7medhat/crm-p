import api from '@/plugins/axios'

export async function fetchDocumentExpirySettings() {
  const response = await api.get('/document-expiry-settings')
  const data = response.data?.data ?? response.data ?? {}
  return {
    passport_days: Number(data.passport_days ?? 15),
    labor_card_days: Number(data.labor_card_days ?? 15),
    emirates_id_days: Number(data.emirates_id_days ?? 15),
    residency_days: Number(data.residency_days ?? 15),
  }
}

export async function updateDocumentExpirySettings(payload) {
  const response = await api.put('/document-expiry-settings', payload)
  return response.data?.data ?? response.data
}
