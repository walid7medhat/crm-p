import api from '@/plugins/axios'
import {
  endNextActionTourPreview,
  startNextActionTourLive,
  tourPhase,
} from '@/composables/useNextActionTourPreview.js'

let channel = null
let listening = false

async function maybeShowFromServer() {
  try {
    const response = await api.get('/next-action-tour/me')
    const data = response.data?.data
    if (data?.should_show && data?.launch_id && tourPhase.value === 'idle') {
      startNextActionTourLive(data.launch_id)
    }
  } catch {
    // Ignore — campaign listen is optional if the API is unavailable.
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
  if (!data) return
  if (data.feature && data.feature !== 'next_action') return
  if (data.active && data.launch_id) {
    if (tourPhase.value === 'idle') startNextActionTourLive(data.launch_id)
    return
  }
  if (!data.active) endNextActionTourPreview()
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

/** Call once from the tour layer: Echo kickoff + due check on load. */
export function initNextActionTourLive() {
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
