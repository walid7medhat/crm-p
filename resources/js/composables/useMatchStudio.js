import { ref } from 'vue'

/**
 * Global Match Studio overlay (mounted once in App.vue).
 * - openMatchStudio(leadId): one client, from the lead modal's Matches tab.
 * - startMatchQueue(clients): the My Matches review, one client after another.
 */
export const matchStudioOpen = ref(false)
/** [{ id, name, first_name, initials, matches_count }] in review order. */
export const matchStudioQueue = ref([])
export const matchStudioIndex = ref(0)
export const matchStudioQueueMode = ref(false)
/** Bumped whenever the studio sends or decides something, so pages can reload. */
export const matchStudioVersion = ref(0)

export function openMatchStudio(lead) {
  const id = Number(lead?.id ?? lead)
  if (!id) return
  matchStudioQueue.value = [typeof lead === 'object' ? { ...lead, id } : { id }]
  matchStudioIndex.value = 0
  matchStudioQueueMode.value = false
  matchStudioOpen.value = true
}

export function startMatchQueue(clients, startIndex = 0) {
  const list = (clients || []).filter((c) => Number(c?.id))
  if (!list.length) return
  matchStudioQueue.value = list.map((c) => ({ ...c, id: Number(c.id) }))
  matchStudioIndex.value = Math.min(Math.max(0, startIndex), list.length - 1)
  matchStudioQueueMode.value = true
  matchStudioOpen.value = true
}

export function closeMatchStudio() {
  matchStudioOpen.value = false
}

export function touchMatchStudio() {
  matchStudioVersion.value++
}
