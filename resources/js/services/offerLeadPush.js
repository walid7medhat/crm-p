import api from '@/plugins/axios'

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = window.atob(base64)
  const output = new Uint8Array(raw.length)
  for (let i = 0; i < raw.length; i += 1) output[i] = raw.charCodeAt(i)
  return output
}

function isIos() {
  const ua = window.navigator.userAgent || ''
  return /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
}

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true
}

export function canPromptForLeadPush() {
  if (typeof Notification === 'undefined') return false
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false
  if (Notification.permission === 'denied') return false
  if (isIos() && !isStandalone()) return false
  return true
}

export async function askLeadPushPermission(webPush) {
  if (!webPush?.prompt || !webPush.public_key) return 'default'
  if (!canPromptForLeadPush()) return typeof Notification === 'undefined' ? 'default' : Notification.permission
  if (Notification.permission === 'granted') return 'granted'
  try {
    return await Notification.requestPermission()
  } catch (_) {
    return Notification.permission
  }
}

export async function subscribeLeadPush(publicKey) {
  if (!publicKey || Notification.permission !== 'granted') return
  const registration = await navigator.serviceWorker.register('/sw.js')
  await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(publicKey),
  })
  await api.post('/push-subscriptions', subscription.toJSON())
}
