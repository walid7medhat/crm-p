let releaseAt = 0
let releaseTimer = null
let waiters = []
/** Aborts in-flight non-critical API calls so lead-open can use a free connection. */
let backgroundAbort = typeof AbortController !== 'undefined' ? new AbortController() : null
/** When true, only an explicit releaseBackgroundApis() (or 45s safety) clears the hold. */
let holdUntilExplicitRelease = false

function finishWaiters() {
  const pending = waiters
  waiters = []
  pending.forEach((finish) => finish())
}

function armReleaseTimer(ms) {
  if (releaseTimer) clearTimeout(releaseTimer)
  releaseTimer = setTimeout(() => releaseBackgroundApis(), ms)
}

/**
 * Keep other API calls waiting so the Lead board / lead-open can use the server first.
 * @param {number|null} [ms] Milliseconds until auto-release. Null / omitted = hold until
 *   releaseBackgroundApis() (safety cap 45s so a hung board cannot block forever).
 */
export function holdBackgroundApis(ms = null) {
  if (ms == null || !Number.isFinite(ms) || ms < 0) {
    holdUntilExplicitRelease = true
    releaseAt = Date.now() + 45_000
    armReleaseTimer(45_000)
    return
  }
  // Never shorten an active board-long hold.
  if (holdUntilExplicitRelease && releaseAt > Date.now()) {
    return
  }
  const target = Date.now() + ms
  if (target <= releaseAt) {
    return
  }
  holdUntilExplicitRelease = false
  releaseAt = target
  armReleaseTimer(ms)
}

export function releaseBackgroundApis() {
  holdUntilExplicitRelease = false
  releaseAt = 0
  if (releaseTimer) {
    clearTimeout(releaseTimer)
    releaseTimer = null
  }
  finishWaiters()
}

/**
 * Board finished: keep shell quiet briefly so the first card click gets free HTTP slots
 * before SAC / notifications / config flood in.
 */
export function releaseBackgroundApisAfterBoard(delayMs = 1200) {
  holdUntilExplicitRelease = false
  const ms = Math.max(0, Number(delayMs) || 0)
  releaseAt = Date.now() + ms
  armReleaseTimer(ms)
  if (ms === 0) {
    releaseBackgroundApis()
  }
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
 * Cancel in-flight background XHRs so GET /leads/{id} is not stuck behind
 * sales-action-center / dashboard / config. Never shortens an active board hold.
 */
export function prioritizeLeadOpen(ms = 4000) {
  if (backgroundAbort) {
    try {
      backgroundAbort.abort()
    } catch {
      /* ignore */
    }
    backgroundAbort = new AbortController()
  }
  // If the board hold is still active, only abort — do not replace it with a shorter timer.
  if (holdUntilExplicitRelease && releaseAt > Date.now()) {
    return
  }
  const remaining = releaseAt - Date.now()
  if (remaining >= ms) {
    return
  }
  holdBackgroundApis(ms)
}
