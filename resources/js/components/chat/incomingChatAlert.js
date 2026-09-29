const SOUND_STORAGE_KEY = 'crm_chat_incoming_sound'
const claimedKeys = new Set()

let viewedConversationId = null
let audioElement = null
let audioContext = null
let unlockBound = false
let lastSoundAt = 0

const SOUND_URL = '/assets/notification-sound.mp3?v=3'
const SOUND_VOLUME = 0.48
const SOUND_GAP_MS = 1200

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

function soundLockName(key) {
  let hash = 0
  const value = String(key)
  for (let i = 0; i < value.length; i += 1) {
    hash = (hash * 31 + value.charCodeAt(i)) >>> 0
  }
  return `crm-chat-sound-${hash}`
}

/**
 * Returns true for the single tab that should play the sound for this message.
 * A background tab waits briefly so a visible CRM tab can claim it first.
 */
export async function claimIncomingSoundAcrossTabs(key) {
  if (!key || claimedKeys.has(key)) return false

  if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
    await new Promise((resolve) => setTimeout(resolve, 220))
    if (claimedKeys.has(key)) return false
  }

  try {
    if (typeof navigator !== 'undefined' && navigator.locks?.request) {
      let won = false
      await navigator.locks.request(soundLockName(key), { ifAvailable: true }, (lock) => {
        if (!lock) {
          rememberClaim(key)
          won = false
          return
        }
        won = claimIncomingSound(key)
      })
      return won
    }
  } catch (_) {}

  return claimIncomingSound(key)
}

function playFallbackTone() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext
    if (!AudioCtx) return
    if (!audioContext) audioContext = new AudioCtx()
    if (audioContext.state === 'suspended') {
      audioContext.resume().catch(() => {})
    }
    const now = audioContext.currentTime
    const osc = audioContext.createOscillator()
    const gain = audioContext.createGain()
    osc.type = 'sine'
    osc.frequency.setValueAtTime(880, now)
    osc.frequency.exponentialRampToValueAtTime(660, now + 0.12)
    gain.gain.setValueAtTime(0.0001, now)
    gain.gain.exponentialRampToValueAtTime(0.07, now + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.2)
    osc.connect(gain)
    gain.connect(audioContext.destination)
    osc.start(now)
    osc.stop(now + 0.22)
  } catch (_) {}
}

function ensureUnlockOnGesture() {
  if (unlockBound || typeof window === 'undefined') return
  unlockBound = true
  window.addEventListener('pointerdown', () => {
    try {
      if (audioContext && audioContext.state === 'suspended') {
        audioContext.resume().catch(() => {})
      }
      if (!audioElement) {
        audioElement = new Audio(SOUND_URL)
        audioElement.preload = 'auto'
      }
      const previous = audioElement.volume
      audioElement.volume = 0
      const pending = audioElement.play()
      if (pending && typeof pending.then === 'function') {
        pending.then(() => {
          audioElement.pause()
          audioElement.currentTime = 0
          audioElement.volume = SOUND_VOLUME || previous
        }).catch(() => {
          if (audioElement) audioElement.volume = SOUND_VOLUME
        })
      }
    } catch (_) {}
  }, { once: true, passive: true })
}

export function playIncomingChatSound() {
  try {
    if (typeof window === 'undefined') return
    const now = Date.now()
    if (now - lastSoundAt < SOUND_GAP_MS) return
    lastSoundAt = now
    ensureUnlockOnGesture()
    if (!audioElement) {
      audioElement = new Audio(SOUND_URL)
      audioElement.preload = 'auto'
      audioElement.volume = SOUND_VOLUME
    }
    audioElement.volume = SOUND_VOLUME
    audioElement.currentTime = 0
    const pending = audioElement.play()
    if (pending && typeof pending.catch === 'function') {
      pending.catch(() => playFallbackTone())
    }
  } catch (_) {}
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
