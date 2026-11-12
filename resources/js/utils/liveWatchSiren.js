/**
 * Original ~3s comedic police-style siren via Web Audio (no binary asset).
 * Cross-tab dedupe by transition_id. Respects mute preference + autoplay unlock.
 */

const MUTE_KEY = 'live_watch_siren_muted'
const CLAIM_KEY = 'live_watch_siren_claims'
const claimed = new Set()

let audioCtx = null
let unlockBound = false
let playing = false

export function isLiveWatchSirenMuted() {
  try {
    return localStorage.getItem(MUTE_KEY) === '1'
  } catch {
    return true
  }
}

export function setLiveWatchSirenMuted(muted) {
  try {
    localStorage.setItem(MUTE_KEY, muted ? '1' : '0')
  } catch {
    /* private mode */
  }
}

function rememberClaim(id) {
  claimed.add(id)
  if (claimed.size > 80) {
    claimed.delete(claimed.values().next().value)
  }
}

/** Returns true only once per transition_id across Super Admin tabs. */
export function claimLiveWatchSiren(transitionId) {
  if (!transitionId || claimed.has(transitionId)) return false
  const now = Date.now()
  try {
    const raw = JSON.parse(localStorage.getItem(CLAIM_KEY) || '{}')
    const claims = raw && typeof raw === 'object' ? raw : {}
    if (claims[transitionId] && now - Number(claims[transitionId]) < 120000) {
      rememberClaim(transitionId)
      return false
    }
    claims[transitionId] = now
    for (const key of Object.keys(claims)) {
      if (now - Number(claims[key]) > 120000) delete claims[key]
    }
    localStorage.setItem(CLAIM_KEY, JSON.stringify(claims))
  } catch {
    /* still allow once in this tab */
  }
  rememberClaim(transitionId)
  return true
}

function ensureContext() {
  if (typeof window === 'undefined') return null
  const Ctx = window.AudioContext || window.webkitAudioContext
  if (!Ctx) return null
  if (!audioCtx) audioCtx = new Ctx()
  return audioCtx
}

/** Bind a one-time user gesture unlock so later online alerts can play. */
export function bindLiveWatchSirenUnlock() {
  if (typeof window === 'undefined' || unlockBound) return
  unlockBound = true
  const unlock = () => {
    const ctx = ensureContext()
    if (ctx && ctx.state === 'suspended') {
      ctx.resume().catch(() => {})
    }
  }
  window.addEventListener('pointerdown', unlock, { once: true, passive: true })
  window.addEventListener('keydown', unlock, { once: true })
}

/**
 * Play a short two-tone wailing siren (~3 seconds). No-ops when muted or already playing.
 */
export async function playLiveWatchSiren(transitionId) {
  if (isLiveWatchSirenMuted()) return false
  if (!claimLiveWatchSiren(transitionId)) return false
  if (playing) return false

  const ctx = ensureContext()
  if (!ctx) return false

  try {
    if (ctx.state === 'suspended') await ctx.resume()
  } catch {
    return false
  }

  playing = true
  const duration = 3.0
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(0.0001, now)
  master.gain.exponentialRampToValueAtTime(0.22, now + 0.08)
  master.gain.exponentialRampToValueAtTime(0.0001, now + duration)
  master.connect(ctx.destination)

  const oscA = ctx.createOscillator()
  const oscB = ctx.createOscillator()
  oscA.type = 'square'
  oscB.type = 'sawtooth'
  oscA.frequency.setValueAtTime(660, now)
  oscB.frequency.setValueAtTime(440, now)

  // Comedic two-tone sweep (not a real emergency tone).
  for (let i = 0; i < 6; i++) {
    const t = now + i * 0.5
    const high = i % 2 === 0
    oscA.frequency.setValueAtTime(high ? 780 : 520, t)
    oscB.frequency.setValueAtTime(high ? 520 : 340, t)
  }

  const trem = ctx.createGain()
  trem.gain.setValueAtTime(0.55, now)
  oscA.connect(trem)
  oscB.connect(trem)
  trem.connect(master)

  oscA.start(now)
  oscB.start(now)
  oscA.stop(now + duration)
  oscB.stop(now + duration)

  window.setTimeout(() => {
    playing = false
  }, Math.ceil(duration * 1000) + 50)

  return true
}
