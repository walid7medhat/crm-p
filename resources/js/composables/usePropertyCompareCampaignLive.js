import api from '@/plugins/axios'
import {
  closePropertyCompareCampaignPreview,
  propertyCompareCampaignOpen,
  startPropertyCompareCampaignLive,
} from '@/composables/usePropertyCompareCampaignPreview.js'

let channel = null
let listening = false

function getStoredUserId() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return null
    const user = JSON.parse(raw)
    return user?.id ? Number(user.id) : null
  } catch {
    return null
  }
}

async function maybeShowFromServer() {
  try {
    const response = await api.get('/next-action-tour/me', { params: { feature: 'property_compare' } })
    const data = response.data?.data
    if (data?.should_show && data?.launch_id && !propertyCompareCampaignOpen.value) {
      startPropertyCompareCampaignLive(data.launch_id)
    }
  } catch {
    // Campaign listen is optional when the API is unavailable.
  }
}

function onCampaignEvent(payload) {
  if (!payload || payload.feature !== 'property_compare') return
  if (payload.active && payload.launch_id) {
    if (!propertyCompareCampaignOpen.value) maybeShowFromServer()
    return
  }
  if (!payload.active) closePropertyCompareCampaignPreview()
}

function bindChannel() {
  const id = getStoredUserId()
  if (!id || !window.Echo || listening) return
  channel = window.Echo.private(`user.${id}`)
  channel.listen('.next-action-tour', onCampaignEvent)
  listening = true
}

function unbindChannel() {
  if (channel) {
    channel.stopListening('.next-action-tour', onCampaignEvent)
    channel = null
  }
  listening = false
}

export function initPropertyCompareCampaignLive() {
  bindChannel()
  maybeShowFromServer()

  const onReady = () => {
    bindChannel()
    maybeShowFromServer()
  }
  window.addEventListener('echo-ready', onReady)

  return () => {
    window.removeEventListener('echo-ready', onReady)
    unbindChannel()
  }
}
