import { ref } from 'vue'
import api from '@/plugins/axios'

const showDealViewModal = ref(false)
const dealViewPayload = ref(null)
const dealViewAutoEditSection = ref(null)

export function useDealViewModal() {
  return {
    showDealViewModal,
    dealViewPayload,
    dealViewAutoEditSection,
    openDealView,
    closeDealView,
  }
}

function resolveAutoEditSection(deal, requested) {
  if (requested) return requested
  const dealType = deal?.deal_type || deal?.type
  if (dealType === 'rental') return 'tenant_details'
  return 'buyer_details'
}

function normalizeDealPayload(dealOrId) {
  if (dealOrId == null) return null
  if (typeof dealOrId === 'number' || typeof dealOrId === 'string') {
    const id = Number(dealOrId)
    return Number.isFinite(id) && id > 0 ? { id } : null
  }
  if (typeof dealOrId === 'object') {
    const id = dealOrId.id ?? dealOrId.deal_id ?? null
    return {
      ...dealOrId,
      id: id ?? null,
      deal_type: dealOrId.deal_type || dealOrId.type || null,
    }
  }
  return null
}

export async function openDealView(dealOrId, options = {}) {
  let deal = normalizeDealPayload(dealOrId)
  if (!deal?.id) return false

  if (!deal.deal_type) {
    try {
      const res = await api.get(`/deals/${deal.id}`)
      const full = res.data?.data ?? res.data
      if (full && typeof full === 'object') {
        deal = { ...deal, ...full }
      }
    } catch (error) {
      console.warn('Could not preload deal before opening modal', error)
    }
  }

  dealViewPayload.value = deal
  dealViewAutoEditSection.value = resolveAutoEditSection(deal, options.autoEditSection)
  showDealViewModal.value = true
  return true
}

export function closeDealView() {
  showDealViewModal.value = false
  dealViewAutoEditSection.value = null
}
