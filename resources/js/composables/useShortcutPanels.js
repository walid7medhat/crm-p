import { ref } from 'vue'

/** Shared open flags so Chat / Next Actions / My Day never stack. */
export const chatPanelOpen = ref(false)
export const myDayPanelOpen = ref(false)
export const salesActionCenterOpen = ref(false)
export const matchesPanelOpen = ref(false)

/** Bumped when Daily Message should force-close (dock collapse or another panel). */
export const dailyMessageCloseTick = ref(0)

/**
 * Close every other shortcut panel before opening one.
 * @param {'chat' | 'next-actions' | 'my-day' | 'matches' | 'daily-message'} panel
 */
export function claimShortcutPanel(panel) {
  if (panel !== 'chat') chatPanelOpen.value = false
  if (panel !== 'my-day') myDayPanelOpen.value = false
  if (panel !== 'next-actions') salesActionCenterOpen.value = false
  if (panel !== 'matches') matchesPanelOpen.value = false
  if (panel !== 'daily-message') dailyMessageCloseTick.value += 1
}

/** Open the dock's Matches panel (match alerts that cover several clients land here). */
export function openMatchesPanel() {
  claimShortcutPanel('matches')
  matchesPanelOpen.value = true
}

/** Collapse the dock / X: hide Chat, Next Actions stages, My Day, Matches, Daily Message. */
export function closeAllShortcutPanels() {
  chatPanelOpen.value = false
  myDayPanelOpen.value = false
  salesActionCenterOpen.value = false
  matchesPanelOpen.value = false
  dailyMessageCloseTick.value += 1
}
