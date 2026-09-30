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
  // false → open in plain view mode (e.g. from a notification), no edit panel.
  if (requested === false) return null
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

  // Open immediately with what we have; hydrate deal_type in background if missing.
  dealViewPayload.value = deal
  dealViewAutoEditSection.value = resolveAutoEditSection(deal, options.autoEditSection)
  showDealViewModal.value = true

  if (!deal.deal_type) {
    try {
      const res = await api.get(`/deals/${deal.id}`)
      const full = res.data?.data ?? res.data
      if (full && typeof full === 'object' && showDealViewModal.value) {
        dealViewPayload.value = { ...dealViewPayload.value, ...full }
        if (!options.autoEditSection) {
          dealViewAutoEditSection.value = resolveAutoEditSection(dealViewPayload.value, null)
        }
      }
    } catch (error) {
      console.warn('Could not preload deal before opening modal', error)
    }
  }

  return true
}

export function closeDealView() {
  showDealViewModal.value = false
  dealViewAutoEditSection.value = null
}

/** Deal id from a notification — DB shape (data.deal_id) or live broadcast shape (deal.id). */
export function dealIdFromNotification(notification) {
  const id = notification?.data?.deal_id
    ?? notification?.data?.deal?.id
    ?? notification?.deal_id
    ?? notification?.deal?.id
  const n = Number(id)
  return Number.isFinite(n) && n > 0 ? n : null
}

/**
 * Open the deal a notification is about. ViewDealModal only lives on the Kanban page,
 * so go there first when needed; the shared modal state opens it once the page mounts.
 * Returns false when the notification isn't about a deal.
 */
export function openDealFromNotification(notification) {
  const dealId = dealIdFromNotification(notification)
  if (!dealId) return false

  const open = () => openDealView(dealId, { autoEditSection: false })
  import('@/router').then(({ default: router }) => {
    if (router.currentRoute.value.path !== '/kanban') {
      router.push('/kanban').then(open).catch(open)
    } else {
      open()
    }
  }).catch(open)

  return true
}
