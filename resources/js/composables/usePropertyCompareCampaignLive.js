import api from '@/plugins/axios'
import {
  closePropertyCompareCampaignPreview,
  propertyCompareCampaignOpen,
  startPropertyCompareCampaignLive,
} from '@/composables/usePropertyCompareCampaignPreview.js'

let channel = null
let listening = false

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

function campaignPayload(payload) {
  if (!payload || typeof payload !== 'object') return null
  if (payload.launch_id || payload.feature) return payload
  if (payload.data && typeof payload.data === 'object') return payload.data
  return payload
}

function onCampaignEvent(payload) {
  const data = campaignPayload(payload)
  if (!data || data.feature !== 'property_compare') return
  if (data.active && data.launch_id) {
    startPropertyCompareCampaignLive(data.launch_id)
    return
  }
  if (!data.active) closePropertyCompareCampaignPreview()
}

function bindChannel() {
  if (!window.Echo || listening) return
  channel = window.Echo.private('crm-campaigns')
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
