/**
 * Coordinates non-critical shell polls (announcements / birthdays) so multiple
 * UI layers do not each hammer the same endpoints. Pauses while the tab is hidden.
 */
import api from '@/plugins/axios'
import { getUnreadAnnouncements } from '@/services/announcementsApi'

const ANNOUNCEMENTS_INTERVAL_MS = 90_000
const BIRTHDAYS_INTERVAL_MS = 5 * 60_000

let announcementTimer = null
let birthdayTimer = null
let visibilityHandler = null
let started = false

let announcementInflight = null
let birthdayInflight = null
let lastAnnouncementsAt = 0
let lastBirthdaysAt = 0
let lastBirthdaysPayload = null

const announcementListeners = new Set()
const birthdayListeners = new Set()

function isVisible() {
  return typeof document === 'undefined' ? true : !document.hidden
}

async function runAnnouncements() {
  if (!isVisible()) return null
  if (announcementInflight) return announcementInflight
  announcementInflight = (async () => {
    try {
      const items = await getUnreadAnnouncements()
      lastAnnouncementsAt = Date.now()
      const list = Array.isArray(items) ? items : []
      announcementListeners.forEach((fn) => {
        try {
          fn(list)
        } catch {
          /* listener errors must not break the poll loop */
        }
      })
      return list
    } finally {
      announcementInflight = null
    }
  })()
  return announcementInflight
}

async function runBirthdays() {
  if (!isVisible()) return lastBirthdaysPayload
  if (birthdayInflight) return birthdayInflight
  birthdayInflight = (async () => {
    try {
      const response = await api.get('/auth/birthdays/today')
      const data = response?.data?.data ?? response?.data ?? {}
      lastBirthdaysAt = Date.now()
      lastBirthdaysPayload = data
      birthdayListeners.forEach((fn) => {
        try {
          fn(data)
        } catch {
          /* ignore */
        }
      })
      return data
    } finally {
      birthdayInflight = null
    }
  })()
  return birthdayInflight
}

function clearTimers() {
  if (announcementTimer) {
    clearInterval(announcementTimer)
    announcementTimer = null
  }
  if (birthdayTimer) {
    clearInterval(birthdayTimer)
    birthdayTimer = null
  }
}

function armTimers() {
  clearTimers()
  if (!isVisible() || !started) return
  announcementTimer = window.setInterval(runAnnouncements, ANNOUNCEMENTS_INTERVAL_MS)
  birthdayTimer = window.setInterval(runBirthdays, BIRTHDAYS_INTERVAL_MS)
}

function ensureStarted() {
  if (started || typeof window === 'undefined') return
  started = true
  visibilityHandler = () => {
    if (document.hidden) {
      clearTimers()
      return
    }
    // Refresh if stale when returning to the tab.
    if (Date.now() - lastAnnouncementsAt > ANNOUNCEMENTS_INTERVAL_MS) {
      runAnnouncements()
    }
    if (Date.now() - lastBirthdaysAt > BIRTHDAYS_INTERVAL_MS) {
      runBirthdays()
    }
    armTimers()
  }
  document.addEventListener('visibilitychange', visibilityHandler)
  armTimers()
}

export function subscribeUnreadAnnouncements(listener, { immediate = true } = {}) {
  ensureStarted()
  announcementListeners.add(listener)
  if (immediate) runAnnouncements()
  return () => announcementListeners.delete(listener)
}

export function subscribeTodaysBirthdays(listener, { immediate = true } = {}) {
  ensureStarted()
  birthdayListeners.add(listener)
  if (immediate) {
    if (lastBirthdaysPayload) {
      try {
        listener(lastBirthdaysPayload)
      } catch {
        /* ignore */
      }
    }
    runBirthdays()
  }
  return () => birthdayListeners.delete(listener)
}

/** Shared birthday payload for navbar popup (avoids a second /auth/birthdays/today). */
export function getCachedTodaysBirthdays() {
  return lastBirthdaysPayload
}

export function peekTodaysBirthdaysPromise() {
  return birthdayInflight
}
