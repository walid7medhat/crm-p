let releaseAt = 0
let releaseTimer = null
let waiters = []

function finishWaiters() {
  const pending = waiters
  waiters = []
  pending.forEach((finish) => finish())
}

/** Keep other API calls waiting so the Lead board can use the server first. */
export function holdBackgroundApis(ms = 700) {
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
