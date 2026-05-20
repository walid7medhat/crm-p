const KANBAN_ROUTE_PREFIXES = ['/kanban', '/kanban_deal']

let readyResolve = null
let readyPromise = null
let isKanbanReady = false

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export function isKanbanRoute(path = '') {
  return KANBAN_ROUTE_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))
}

export function resetKanbanReady() {
  isKanbanReady = false
  readyPromise = new Promise((resolve) => {
    readyResolve = resolve
  })
}

export function markKanbanReady() {
  if (isKanbanReady) return
  isKanbanReady = true
  readyResolve?.()
  readyResolve = null
}

export async function waitForKanbanReady(timeoutMs = 15000) {
  if (isKanbanReady) return
  if (!readyPromise) resetKanbanReady()
  await Promise.race([readyPromise, wait(timeoutMs)])
}

resetKanbanReady()
