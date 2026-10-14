import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Live photoshoot-request sync for "Photo Shoots" (requester) and Marketing → Bookings (team).
 *
 *  - Pusher: `.photo-booking.updated` on the user's private channel (PhotoBookingChanged).
 *  - Fallback: quiet re-check every 30s while the tab is visible, and right away when the
 *    tab regains focus — so it still updates if the queue worker / Pusher is down.
 *
 * `onChange(event | null)` is debounced; event is null for fallback refreshes.
 */
export function usePhotoBookingLive(onChange, { pollMs = 30000 } = {}) {
  let channel = null
  let pollTimer = null
  let debounce = null

  const fire = (e = null) => {
    clearTimeout(debounce)
    debounce = setTimeout(() => onChange(e), 250)
  }

  const handler = (e) => fire(e)

  function subscribe() {
    if (channel || !window.Echo) return
    try {
      const user = JSON.parse(localStorage.getItem('user') || 'null')
      if (!user?.id) return
      channel = window.Echo.private(`user.${user.id}`)
      channel.listen('.photo-booking.updated', handler)
    } catch {
      channel = null
    }
  }

  function onVisible() {
    if (document.visibilityState === 'visible') fire(null)
  }

  onMounted(() => {
    subscribe()
    window.addEventListener('echo-ready', subscribe)
    document.addEventListener('visibilitychange', onVisible)
    window.addEventListener('focus', onVisible)
    pollTimer = setInterval(() => {
      if (document.visibilityState === 'visible') fire(null)
    }, pollMs)
  })

  onBeforeUnmount(() => {
    clearTimeout(debounce)
    clearInterval(pollTimer)
    window.removeEventListener('echo-ready', subscribe)
    document.removeEventListener('visibilitychange', onVisible)
    window.removeEventListener('focus', onVisible)
    // Only our handler — the user channel is shared with leads/deals/notifications.
    channel?.stopListening('.photo-booking.updated', handler)
    channel = null
  })
}
