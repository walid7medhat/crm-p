import api from '@/plugins/axios'
import {
  endNextActionTourPreview,
  startNextActionTourLive,
  tourPhase,
} from '@/composables/useNextActionTourPreview.js'

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
    const response = await api.get('/next-action-tour/me')
    const data = response.data?.data
    if (data?.should_show && data?.launch_id && tourPhase.value === 'idle') {
      startNextActionTourLive(data.launch_id)
    }
  } catch {
    // Ignore — campaign listen is optional if the API is unavailable.
  }
}

function onCampaignEvent(payload) {
  if (!payload) return
  if (payload.active && payload.launch_id) {
    if (tourPhase.value === 'idle') {
      // Re-check views so refresh / restart still respects the 3-view cap.
      maybeShowFromServer()
    }
    return
  }
  if (!payload.active) {
    endNextActionTourPreview()
  }
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
    channel.stopListening('.next-action-tour')
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
