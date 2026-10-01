/* Push-only service worker. No fetch handler, so it does not cache or change CRM assets. */
const LEAD_VIEW_URL = /^\/\?lead=(\d+)$/
const ALT_CRM_ICON = '/assets/images/pwa/icon-192.png'

function leadViewPath(data) {
  const url = data && typeof data.url === 'string' ? data.url : ''
  const match = url.match(LEAD_VIEW_URL)
  const leadId = Number(data && data.lead_id)
  if (!match) return ''
  if (!Number.isInteger(leadId) || leadId < 1 || Number(match[1]) !== leadId) return ''
  return url
}

self.addEventListener('push', (event) => {
  const fallback = {
    title: 'New Lead Assigned',
    body: 'A new lead has been assigned to you.',
  }

  let payload = fallback
  let leadPath = ''
  let leadId = null
  try {
    if (event.data) {
      const parsed = event.data.json()
      leadPath = leadViewPath(parsed)
      leadId = leadPath ? Number(parsed.lead_id) : null
      payload = {
        title: typeof parsed.title === 'string' && parsed.title ? parsed.title : fallback.title,
        body: typeof parsed.body === 'string' && parsed.body ? parsed.body : fallback.body,
      }
    }
  } catch (_) {
    payload = fallback
  }

  event.waitUntil((async () => {
    const existing = await self.registration.getNotifications()
    const count = existing.length + 1
    await self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: ALT_CRM_ICON,
      badge: ALT_CRM_ICON,
      tag: leadId ? `lead-assignment-${leadId}` : `lead-assignment-${Date.now()}`,
      data: { url: leadPath, lead_id: leadId, type: 'lead_assignment', count },
    })
    if (typeof navigator.setAppBadge === 'function') {
      await navigator.setAppBadge(count)
    }
  })())
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const data = event.notification.data || {}
  const path = leadViewPath(data)
  if (!path) return
  const target = new URL(path, self.location.origin).href

  event.waitUntil((async () => {
    if (typeof navigator.clearAppBadge === 'function') {
      await navigator.clearAppBadge()
    }
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    for (const client of windows) {
      if (!client.url.startsWith(self.location.origin)) continue
      if ('focus' in client) await client.focus()
      if ('navigate' in client) {
        try {
          await client.navigate(target)
          return
        } catch (_) {
          /* Fall through to a message the open CRM can handle. */
        }
      }
      client.postMessage({ type: 'lead_assignment', lead_id: Number(data.lead_id), url: path })
      return
    }
    if (self.clients.openWindow) {
      await self.clients.openWindow(target)
    }
  })())
})
