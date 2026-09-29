const SOUND_STORAGE_KEY = 'crm_chat_incoming_sound'
const claimedKeys = new Set()

let viewedConversationId = null
let audioElement = null
let audioContext = null
let unlockBound = false
let lastSoundAt = 0

const SOUND_URL = '/assets/notification-sound.mp3?v=3'
const SOUND_VOLUME = 0.85
const SOUND_GAP_MS = 900

export function messageAlertKey(event) {
  if (event?.id != null && event.id !== '') return `id:${event.id}`
  return [
    'fallback',
    event?.conversation_id ?? '',
    event?.sender_id ?? '',
    event?.created_at ?? '',
    event?.message ?? '',
  ].join(':')
}

export function setViewedChatConversation(id) {
  if (id == null || id === '') {
    viewedConversationId = null
    return
  }
  const n = Number(id)
  viewedConversationId = Number.isFinite(n) ? n : null
}

export function isConversationOpen(conversationId) {
  if (viewedConversationId == null || conversationId == null || conversationId === '') return false
  return Number(viewedConversationId) === Number(conversationId)
}

/** True only when this exact thread is open and the CRM tab is in the foreground. */
export function isActivelyViewingConversation(conversationId) {
  if (!isConversationOpen(conversationId)) return false
  if (typeof document === 'undefined') return true
  return document.visibilityState === 'visible'
}

function rememberClaim(key) {
  claimedKeys.add(key)
  if (claimedKeys.size <= 200) return
  const first = claimedKeys.values().next().value
  claimedKeys.delete(first)
}

function claimIncomingSound(key) {
  if (!key || claimedKeys.has(key)) return false
  const now = Date.now()
  try {
    const raw = JSON.parse(localStorage.getItem(SOUND_STORAGE_KEY) || '{}')
    const claims = raw && typeof raw === 'object' ? raw : {}
    if (claims[key] && now - Number(claims[key]) < 20000) {
      rememberClaim(key)
      return false
    }
    claims[key] = now
    const ids = Object.keys(claims)
    for (const id of ids) {
      if (now - Number(claims[id]) > 20000) delete claims[id]
    }
    const remaining = Object.keys(claims)
    if (remaining.length > 60) {
      remaining.sort((a, b) => Number(claims[a]) - Number(claims[b]))
      for (const id of remaining.slice(0, remaining.length - 60)) delete claims[id]
    }
    localStorage.setItem(SOUND_STORAGE_KEY, JSON.stringify(claims))
  } catch (_) {
    // Private mode or blocked storage: still allow a single alert in this tab.
  }
  rememberClaim(key)
  return true
}

/**
 * Returns true for the single tab that should play the sound for this message.
 * Synchronous so the audible alert is not deferred behind a lock or timer.
 */
export function claimIncomingSoundAcrossTabs(key) {
  if (!key || claimedKeys.has(key)) return false
  return claimIncomingSound(key)
}

/** Run callback once Laravel Echo has been created. Echo starts after idle time. */
export function whenEchoReady(callback) {
  if (typeof window === 'undefined') return () => {}
  if (window.Echo) {
    callback()
    return () => {}
  }
  const handler = () => {
    window.removeEventListener('echo-ready', handler)
    callback()
  }
  window.addEventListener('echo-ready', handler)
  return () => window.removeEventListener('echo-ready', handler)
}

function getAudioElement() {
  if (!audioElement) {
    audioElement = new Audio(SOUND_URL)
    audioElement.preload = 'auto'
    audioElement.volume = SOUND_VOLUME
  }
  return audioElement
}

function playFallbackTone(retried = false) {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext
    if (!AudioCtx) return
    if (!audioContext) audioContext = new AudioCtx()
    if (audioContext.state === 'suspended') {
      if (retried) return
      audioContext.resume().then(() => playFallbackTone(true)).catch(() => {})
      return
    }
    const now = audioContext.currentTime
    const osc = audioContext.createOscillator()
    const gain = audioContext.createGain()
    osc.type = 'sine'
    osc.frequency.setValueAtTime(880, now)
    osc.frequency.exponentialRampToValueAtTime(660, now + 0.18)
    gain.gain.setValueAtTime(0.0001, now)
    gain.gain.exponentialRampToValueAtTime(0.2, now + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.28)
    osc.connect(gain)
    gain.connect(audioContext.destination)
    osc.start(now)
    osc.stop(now + 0.3)
  } catch (_) {}
}

/** Unlock audio on the first click or keypress, before a message arrives. */
export function prepareIncomingChatSound() {
  if (unlockBound || typeof window === 'undefined') return
  unlockBound = true
  const unlock = () => {
    try {
      const el = getAudioElement()
      el.volume = 0
      const pending = el.play()
      const restore = () => {
        try {
          el.pause()
          el.currentTime = 0
        } catch (_) {}
        el.volume = SOUND_VOLUME
      }
      if (pending && typeof pending.then === 'function') {
        pending.then(restore).catch(() => {
          el.volume = SOUND_VOLUME
        })
      } else {
        restore()
      }
      const AudioCtx = window.AudioContext || window.webkitAudioContext
      if (AudioCtx) {
        if (!audioContext) audioContext = new AudioCtx()
        if (audioContext.state === 'suspended') audioContext.resume().catch(() => {})
      }
    } catch (_) {}
  }
  window.addEventListener('pointerdown', unlock, { once: true, capture: true })
  window.addEventListener('keydown', unlock, { once: true, capture: true })
}

export function playIncomingChatSound() {
  try {
    if (typeof window === 'undefined') return
    const now = Date.now()
    if (now - lastSoundAt < SOUND_GAP_MS) return
    lastSoundAt = now
    prepareIncomingChatSound()
    const el = getAudioElement()
    el.volume = SOUND_VOLUME
    try { el.currentTime = 0 } catch (_) {}
    const pending = el.play()
    if (pending && typeof pending.catch === 'function') {
      pending.catch(() => playFallbackTone())
    }
  } catch (_) {}
}

if (typeof window !== 'undefined') {
  prepareIncomingChatSound()
}

export function releaseIncomingChatSound() {
  if (audioElement) {
    try { audioElement.pause() } catch (_) {}
    audioElement = null
  }
  if (audioContext && typeof audioContext.close === 'function') {
    audioContext.close().catch(() => {})
    audioContext = null
  }
}
