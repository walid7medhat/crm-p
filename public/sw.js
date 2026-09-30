/* Push-only service worker. No fetch handler, so it does not cache or change CRM assets. */
self.addEventListener('push', (event) => {
  const fallback = {
    title: 'New Lead Assigned',
    body: 'You have a new lead assigned to you.',
    url: '/',
  }

  let payload = fallback
  try {
    if (event.data) {
      const parsed = event.data.json()
      payload = {
        title: typeof parsed.title === 'string' && parsed.title ? parsed.title : fallback.title,
        body: typeof parsed.body === 'string' && parsed.body ? parsed.body : fallback.body,
        url: typeof parsed.url === 'string' && parsed.url.startsWith('/') ? parsed.url : fallback.url,
      }
    }
  } catch (_) {
    payload = fallback
  }

  event.waitUntil(self.registration.showNotification(payload.title, {
    body: payload.body,
    icon: '/assets/images/altcrm-logo.png',
    data: { url: payload.url },
  }))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const path = event.notification.data && event.notification.data.url ? event.notification.data.url : '/'
  const target = new URL(path, self.location.origin).href

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    for (const client of windows) {
      if (client.url.startsWith(self.location.origin) && 'focus' in client) {
        await client.focus()
        if ('navigate' in client) {
          try {
            await client.navigate(target)
          } catch (_) {
            /* The focused window is enough for this test. */
          }
        }
        return
      }
    }
    if (self.clients.openWindow) {
      await self.clients.openWindow(target)
    }
  })())
})
