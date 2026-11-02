import { ref } from 'vue'
import api from '@/plugins/axios'
import { renderListingOffer } from '@/composables/useOfferPdf.js'

/**
 * Match helpers shared by the dock panel, lead matching carousel, and offer PDF toast.
 * The full-screen Match Studio overlay was removed; openLeadMatching() opens the lead instead.
 */
export const matchStudioOpen = ref(false)
/** [{ id, name, first_name, initials, matches_count }] in review order. */
export const matchStudioQueue = ref([])
export const matchStudioIndex = ref(0)
export const matchStudioQueueMode = ref(false)
/** Bumped whenever the studio sends or decides something, so pages can reload. */
export const matchStudioVersion = ref(0)
/** Lead to open once the studio closes; set by match notifications. */
export const matchStudioThenLead = ref(null)

/**
 * @param {object|number} lead
 * @param {{ thenOpenLead?: boolean }} [options] thenOpenLead: open the lead after the studio
 *   closes (match notifications: studio first, the lead after).
 */
export function openMatchStudio(lead, { thenOpenLead = false } = {}) {
  const id = Number(lead?.id ?? lead)
  if (!id) return
  matchStudioQueue.value = [typeof lead === 'object' ? { ...lead, id } : { id }]
  matchStudioIndex.value = 0
  matchStudioQueueMode.value = false
  matchStudioThenLead.value = thenOpenLead ? id : null
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

/**
 * The finished offer waiting for the agent's "send" click (MatchAlertToast shows it).
 * { mode: 'share' | 'whatsapp', file, filename, text, whatsappUrl, offerNumber, clientName }
 * Browsers only open WhatsApp or the share sheet right after a click, and building the
 * PDF takes longer than that, so the last step is always the agent's own click.
 */
export const pendingPdfShare = ref(null)

/** Phones and tablets: the share sheet can hand the PDF file straight to WhatsApp. */
function canShareFiles() {
  const touch = window.matchMedia?.('(pointer: coarse)').matches
  return !!(touch && typeof navigator.share === 'function' && typeof navigator.canShare === 'function')
}

function downloadFile(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 10000)
}

/**
 * Send this matched home to the client as the sales offer PDF on WhatsApp.
 * 1. Builds the PDF with the property page's own "Generate Offer" (saved to the
 *    listing's offer history, like that button).
 * 2. Logs it on the lead and marks the home sent, only once the PDF exists.
 * 3. Shows the "ready" card (pendingPdfShare); the agent's click there opens the
 *    phone share sheet with the file, or on desktop the client's WhatsApp chat plus
 *    the PDF download. No tab is opened before the PDF is ready.
 */
export async function sendMatchOffer(matchId, { listingId, clientName, onProgress } = {}) {
  const pdf = await renderListingOffer(listingId, { clientName, onProgress })
  const res = await api.post(`/lead-matches/${matchId}/offer`, { offer_number: pdf.offerNumber })
  const data = res.data?.data || {}
  const file = new File([pdf.blob], pdf.filename, { type: 'application/pdf' })
  const share = canShareFiles() && navigator.canShare({ files: [file] })

  pendingPdfShare.value = {
    mode: share ? 'share' : 'whatsapp',
    file,
    filename: pdf.filename,
    text: data.message,
    whatsappUrl: data.whatsapp_url,
    offerNumber: pdf.offerNumber,
    clientName,
  }
  touchMatchStudio()
  return { ...data, offer_number: pdf.offerNumber, ready: true, share }
}

/** One line for the agent after sendMatchOffer, the same on every screen. */
export function offerResultText(data) {
  const offer = data.offer_number ? `Sales offer ${data.offer_number}` : 'The sales offer'
  return data.share
    ? `${offer} is ready. Tap "Share PDF" to send it.`
    : `${offer} is ready. Click "Send on WhatsApp" to open the chat.`
}

/**
 * The agent's click on the ready card. Phone: share sheet with the PDF. Desktop: the
 * PDF downloads and the client's WhatsApp chat opens with the message (or, with no
 * number on the lead, the message is copied).
 * @returns {Promise<'shared'|'opened'|'copied'|'cancelled'>}
 */
export async function sharePendingPdf() {
  const pending = pendingPdfShare.value
  if (!pending) return null
  if (pending.mode === 'share') {
    try {
      await navigator.share({ files: [pending.file], text: pending.text })
      pendingPdfShare.value = null
      return 'shared'
    } catch {
      return 'cancelled' // keep the card so they can try again
    }
  }

  downloadFile(pending.file, pending.filename)
  pendingPdfShare.value = null
  if (pending.whatsappUrl) {
    window.open(pending.whatsappUrl, '_blank', 'noopener')
    return 'opened'
  }
  try {
    await navigator.clipboard.writeText(pending.text || '')
  } catch { /* the message is still on the lead's timeline */ }
  return 'copied'
}

/** Open the client's WhatsApp chat with nothing typed yet. */
export function openWhatsAppChat(phone) {
  if (phone) window.open(`https://wa.me/${phone}`, '_blank', 'noopener')
}
