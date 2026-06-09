import { ref } from 'vue'
import api from '@/plugins/axios'

const showLeadViewModal = ref(false)
const leadViewModalId = ref(null)
const leadUpdatedListeners = new Set()

export function useLeadViewModal() {
  return {
    showLeadViewModal,
    leadViewModalId,
    openLeadView,
    closeLeadView,
    onLeadViewUpdated,
    notifyLeadViewUpdated,
    openLeadFromNotification,
  }
}

export function openLeadView(leadId) {
  const id = Number(leadId)
  if (!id) return

  leadViewModalId.value = id
  showLeadViewModal.value = true
  api.get(`/leads/${id}/history/view`).catch(() => {})
}

export function closeLeadView() {
  showLeadViewModal.value = false
}

export function onLeadViewUpdated(callback) {
  if (typeof callback !== 'function') return () => {}
  leadUpdatedListeners.add(callback)
  return () => leadUpdatedListeners.delete(callback)
}

export function notifyLeadViewUpdated(updatedLead) {
  leadUpdatedListeners.forEach((callback) => {
    try {
      callback(updatedLead)
    } catch (error) {
      console.error('Lead view update listener failed', error)
    }
  })
}

export function openLeadFromNotification(notification) {
  const leadId = notification?.data?.lead_id
  if (!leadId) return false
  openLeadView(leadId)
  return true
}
