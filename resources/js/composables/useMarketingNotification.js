/**
 * Marketing notifications (card activity + reminders) — open the card they are about.
 * Mirrors openLeadFromNotification / openDealFromNotification.
 */

/** Works for both the stored shape (notification.data.*) and the live broadcast shape. */
export function marketingFromNotification(notification) {
  const d = notification?.data ?? notification ?? {}
  const type = d.type ?? notification?.type
  if (type === 'marketing_booking') {
    return { taskId: null, category: null, isReminder: false, bookingId: Number(d.booking_id) || null }
  }
  if (type !== 'marketing' && type !== 'marketing_reminder') return null
  const id = Number(d.marketing_task_id)
  return {
    taskId: Number.isFinite(id) && id > 0 ? id : null,
    category: d.marketing_category || null,
    isReminder: type === 'marketing_reminder',
    drawerTab: d.drawer_tab || null,
  }
}

/** Status update on one of *my* photoshoot requests (requester side). */
export function photoBookingFromNotification(notification) {
  const d = notification?.data ?? notification ?? {}
  if ((d.type ?? notification?.type) !== 'photo_booking_status') return null
  return { bookingId: Number(d.booking_id) || null }
}

/** Opens the card on its board (or the calendar for a free post). Returns false if not marketing. */
export function openMarketingFromNotification(notification) {
  const mine = photoBookingFromNotification(notification)
  if (mine) {
    import('@/router').then(({ default: router }) => {
      router.push({ path: '/photo-shoots', query: mine.bookingId ? { booking: String(mine.bookingId) } : {} }).catch(() => {})
    })
    return true
  }

  const m = marketingFromNotification(notification)
  if (!m) return false

  const query = m.bookingId !== undefined
    ? { tab: 'bookings', ...(m.bookingId ? { booking: String(m.bookingId) } : {}) }
    : m.taskId
      ? {
          task: String(m.taskId),
          ...(m.category ? { tab: m.category } : {}),
          ...(m.drawerTab ? { drawer: m.drawerTab } : {}),
        }
      : { tab: 'calendar' }

  import('@/router').then(({ default: router }) => {
    router.push({ path: '/marketing', query }).catch(() => {})
  })
  return true
}
