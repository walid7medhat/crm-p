import { ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/plugins/axios'


export const showLeadViewModal = ref(false)
export const leadViewModalId = ref(null)
/** Optional kanban/card payload so View Lead can paint before GET /leads/{id} returns. */
export const leadViewModalSeed = ref(null)
/** Lead Pool leads shouldn't be movable from inside the modal — set per-open by the caller. */
export const leadViewModalDisableStageChange = ref(false)
const leadUpdatedListeners = new Set()

let router = null
let route = null

export function initLeadViewModal(r, rt) {
  router = r
  route = rt
}


export function useLeadViewModal() {
  return {
    showLeadViewModal,
    leadViewModalId,
    leadViewModalSeed,
    leadViewModalDisableStageChange,
    openLeadView,
    closeLeadView,
    onLeadViewUpdated,
    notifyLeadViewUpdated,
    openLeadFromNotification,
     openLeadViewWithUrl,
    closeLeadViewWithUrl,
    checkUrlForLead,
  }
}

/**
 * @param {number|string} leadId
 * @param {object|null} [leadData] Local card/lead object for instant paint
 * @param {object} [options]
 * @param {boolean} [options.disableStageChange] Lead Pool leads shouldn't be movable from the modal
 */
export function openLeadView(leadId, leadData = null, options = {}) {
  const id = Number(leadId)
  if (!id) return

  leadViewModalId.value = id
  leadViewModalSeed.value = leadData && typeof leadData === 'object'
    ? { ...leadData, id: leadData.id || id }
    : null
  leadViewModalDisableStageChange.value = !!options.disableStageChange
  showLeadViewModal.value = true
  api.get(`/leads/${id}/history/view`).catch(() => {})

  // Stay on the current page. Sending the user to /kanban first left a blank
  // screen until that page finished loading, then the lead appeared.
  if (router && route && String(route.query.lead || '') !== String(id)) {
    router.replace({ query: { ...route.query, lead: String(id) } }).catch(() => {})
  }
}
export function openLeadViewWithUrl(leadId, leadData = null) {
  openLeadView(leadId, leadData)
}

function clearLeadQuery() {
  if (!router || !route?.query?.lead) return
  const query = { ...route.query }
  delete query.lead
  router.replace({ query }).catch(() => {})
}

export function closeLeadViewWithUrl() {
  showLeadViewModal.value = false
  leadViewModalSeed.value = null
  leadViewModalDisableStageChange.value = false
  clearLeadQuery()
}

export function closeLeadView() {
  showLeadViewModal.value = false
  leadViewModalSeed.value = null
  leadViewModalDisableStageChange.value = false
  clearLeadQuery()
}

export function checkUrlForLead() {
  if (!route) return null
  
  const leadId = route.query.lead
  if (leadId && !showLeadViewModal.value) {
    const id = Number(leadId)
    if (!isNaN(id) && id > 0) {
      openLeadView(id)
      return id
    }
  }
  return null
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
