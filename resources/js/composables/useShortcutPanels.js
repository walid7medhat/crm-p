import { ref } from 'vue'

/** Shared open flags so Chat / Next Actions / My Day never stack. */
export const chatPanelOpen = ref(false)
export const myDayPanelOpen = ref(false)
export const salesActionCenterOpen = ref(false)

/** Bumped when Daily Message should force-close (dock collapse or another panel). */
export const dailyMessageCloseTick = ref(0)

/**
 * Close every other shortcut panel before opening one.
 * @param {'chat' | 'next-actions' | 'my-day' | 'daily-message'} panel
 */
export function claimShortcutPanel(panel) {
  if (panel !== 'chat') chatPanelOpen.value = false
  if (panel !== 'my-day') myDayPanelOpen.value = false
  if (panel !== 'next-actions') salesActionCenterOpen.value = false
  if (panel !== 'daily-message') dailyMessageCloseTick.value += 1
}

/** Collapse the dock / X: hide Chat, Next Actions stages, My Day, Daily Message. */
export function closeAllShortcutPanels() {
  chatPanelOpen.value = false
  myDayPanelOpen.value = false
  salesActionCenterOpen.value = false
  dailyMessageCloseTick.value += 1
}
