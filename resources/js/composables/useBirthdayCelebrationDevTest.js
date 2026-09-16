/**
 * ============================================================================
 * TEMPORARY DEV/TEST ONLY — Birthday celebration overlay preview
 * ============================================================================
 * Purpose: Let Super Admin preview BirthdayCelebrationLayer without changing
 * any user's birth_date or the real birthday API/detection.
 *
 * Remove later by deleting:
 *   1. this file
 *   2. the forceShow wiring in BirthdayCelebrationLayer.vue
 *   3. the DEV/TEST strip in pages/settings/background.vue
 * ============================================================================
 */
import { ref } from 'vue'

const forceShow = ref(false)

function readRoles() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return []
    const u = JSON.parse(raw)
    return Array.isArray(u?.roles) ? u.roles : []
  } catch {
    return []
  }
}

export function isBirthdayCelebrationDevTestAllowed() {
  return readRoles().includes('super_admin')
}

function todayStopKey() {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `birthday_celebration_stopped_${y}-${m}-${day}`
}

/**
 * Super Admin only. Clears today's Stop dismissal so the real overlay can show,
 * then forces BirthdayCelebrationLayer visible (same component / stop behavior).
 */
export function triggerBirthdayCelebrationDevTest() {
  if (!isBirthdayCelebrationDevTestAllowed()) {
    console.warn('[DEV/TEST] Birthday celebration preview is Super Admin only')
    return false
  }
  try {
    localStorage.removeItem(todayStopKey())
  } catch {
    /* ignore */
  }
  forceShow.value = true
  return true
}

export function clearBirthdayCelebrationDevTest() {
  forceShow.value = false
}

export function useBirthdayCelebrationDevTest() {
  return {
    forceShow,
    isAllowed: isBirthdayCelebrationDevTestAllowed,
    trigger: triggerBirthdayCelebrationDevTest,
    clear: clearBirthdayCelebrationDevTest,
  }
}
