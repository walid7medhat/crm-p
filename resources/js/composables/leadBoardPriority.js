let releaseAt = 0
let releaseTimer = null
let waiters = []
/** Aborts in-flight non-critical API calls so lead-open can use a free connection. */
let backgroundAbort = typeof AbortController !== 'undefined' ? new AbortController() : null

function finishWaiters() {
  const pending = waiters
  waiters = []
  pending.forEach((finish) => finish())
}

/**
 * Keep other API calls waiting so the Lead board / lead-open can use the server first.
 * @param {number|null} [ms] Milliseconds until auto-release. Null / omitted = hold until
 *   releaseBackgroundApis() (safety cap 45s so a hung board cannot block forever).
 */
export function holdBackgroundApis(ms = null) {
  if (ms == null || !Number.isFinite(ms) || ms < 0) {
    releaseAt = Date.now() + 45_000
    if (releaseTimer) clearTimeout(releaseTimer)
    releaseTimer = setTimeout(() => releaseBackgroundApis(), 45_000)
    return
  }
  releaseAt = Date.now() + ms
  if (releaseTimer) clearTimeout(releaseTimer)
  releaseTimer = setTimeout(() => releaseBackgroundApis(), ms)
}

export function releaseBackgroundApis() {
  releaseAt = 0
  if (releaseTimer) {
    clearTimeout(releaseTimer)
    releaseTimer = null
  }
  finishWaiters()
}

export function waitForLeadBoardPriority() {
  if (releaseAt <= Date.now()) return Promise.resolve()
  return new Promise((resolve) => {
    let done = false
    const finish = () => {
      if (done) return
      done = true
      resolve()
    }
    waiters.push(finish)
    setTimeout(finish, Math.max(0, releaseAt - Date.now()))
  })
}

export function isLeadBoardPriorityHeld() {
  return releaseAt > Date.now()
}

/** AbortSignal for deferrable shell APIs (SAC, dashboard, config, polls). */
export function getBackgroundAbortSignal() {
  return backgroundAbort?.signal || undefined
}

/**
 * Cancel in-flight background XHRs and hold new ones briefly so GET /leads/{id}
 * is not stuck behind sales-action-center / dashboard / config (often 4–8s).
 */
export function prioritizeLeadOpen(ms = 3500) {
  if (backgroundAbort) {
    try {
      backgroundAbort.abort()
    } catch {
      /* ignore */
    }
    backgroundAbort = new AbortController()
  }
  holdBackgroundApis(ms)
}
