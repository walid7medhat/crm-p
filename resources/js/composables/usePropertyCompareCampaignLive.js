import api from '@/plugins/axios'
import {
  closePropertyCompareCampaignPreview,
  propertyCompareCampaignLaunchId,
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

function campaignPayload(payload) {
  if (!payload || typeof payload !== 'object') return null
  if (payload.launch_id || payload.feature) return payload
  if (payload.data && typeof payload.data === 'object') return payload.data
  return payload
}

function onCampaignEvent(payload) {
  const data = campaignPayload(payload)
  if (!data || data.feature !== 'property_compare') return
  if (data.action === 'stop' || data.active === false) {
    const current = propertyCompareCampaignLaunchId.value
    if (!data.launch_id || !current || data.launch_id === current) {
      closePropertyCompareCampaignPreview()
    }
    return
  }
  if (data.launch_id) startPropertyCompareCampaignLive(data.launch_id)
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
  const onVisible = () => {
    if (document.visibilityState === 'visible') maybeShowFromServer()
  }
  const retrySoon = window.setTimeout(maybeShowFromServer, 2500)
  const retryLater = window.setTimeout(maybeShowFromServer, 7000)
  window.addEventListener('echo-ready', onReady)
  document.addEventListener('visibilitychange', onVisible)

  return () => {
    window.clearTimeout(retrySoon)
    window.clearTimeout(retryLater)
    window.removeEventListener('echo-ready', onReady)
    document.removeEventListener('visibilitychange', onVisible)
    unbindChannel()
  }
}
