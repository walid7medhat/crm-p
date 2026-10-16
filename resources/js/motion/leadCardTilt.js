/**
 * Pointer tilt for desktop Kanban lead cards.
 * One shared animation frame, CSS variables only, idle when the pointer leaves.
 */
const FINE_POINTER = '(hover: hover) and (pointer: fine)'
const REDUCED_MOTION = '(prefers-reduced-motion: reduce)'

const MAX_TILT = 6.5
const MAX_SHIFT = 2.4
const LIFT = -4

let frame = 0
let pending = null
let dragImage = null
let releaseTimer = 0

function motionOff() {
    if (typeof window === 'undefined') return true
    return window.matchMedia(REDUCED_MOTION).matches || !window.matchMedia(FINE_POINTER).matches
}

function canTilt(card) {
    if (!card || motionOff()) return false
    if (card.classList.contains('kanban-card--mobile')) return false
    if (card.classList.contains('kanban-card--motion-hold')) return false
    if (card.classList.contains('ghost')) return false
    if (card.classList.contains('dragging')) return false
    if (card.classList.contains('kanban-card--settle')) return false
    return true
}

function paint() {
    frame = 0
    const next = pending
    pending = null
    if (!next) return
    const { card, clientX, clientY } = next
    if (!canTilt(card)) {
        releaseLeadCardTilt(card)
        return
    }
    const rect = card.getBoundingClientRect()
    if (!rect.width || !rect.height) return
    const x = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width))
    const y = Math.min(1, Math.max(0, (clientY - rect.top) / rect.height))
    const dx = x - 0.5
    const dy = y - 0.5
    const lift = card.classList.contains('is-pressed') ? -1 : LIFT
    card.style.setProperty('--rx', `${(-dy * MAX_TILT).toFixed(2)}deg`)
    card.style.setProperty('--ry', `${(dx * MAX_TILT).toFixed(2)}deg`)
    card.style.setProperty('--px', `${(dx * MAX_SHIFT * 2).toFixed(2)}px`)
    card.style.setProperty('--py', `${(dy * MAX_SHIFT * 2 + lift).toFixed(2)}px`)
    card.style.setProperty('--gx', `${(x * 100).toFixed(1)}%`)
    card.style.setProperty('--gy', `${(y * 100).toFixed(1)}%`)
    card.classList.add('is-tilting')
    card.classList.remove('is-releasing')
}

function queue(card, event) {
    pending = { card, clientX: event.clientX, clientY: event.clientY }
    if (!frame) frame = requestAnimationFrame(paint)
}

export function onLeadCardTilt(event) {
    const card = event.currentTarget
    if (!canTilt(card)) return
    if (event.pointerType && event.pointerType !== 'mouse') return
    queue(card, event)
}

export function onLeadCardTiltDown(event) {
    const card = event.currentTarget
    if (!canTilt(card)) return
    if (event.pointerType && event.pointerType !== 'mouse') return
    card.classList.add('is-pressed')
    queue(card, event)
}

export function onLeadCardTiltUp(event) {
    const card = event.currentTarget
    if (!card) return
    card.classList.remove('is-pressed')
    if (card.classList.contains('is-tilting') && event?.clientX != null) queue(card, event)
}

export function releaseLeadCardTilt(card) {
    if (!card?.style) return
    const wasActive = card.classList.contains('is-tilting') || card.classList.contains('is-pressed')
    card.classList.remove('is-tilting', 'is-pressed')
    card.style.removeProperty('--rx')
    card.style.removeProperty('--ry')
    card.style.removeProperty('--px')
    card.style.removeProperty('--py')
    card.style.removeProperty('--lz')
    card.style.removeProperty('--gx')
    card.style.removeProperty('--gy')
    if (!wasActive || motionOff()) {
        card.classList.remove('is-releasing')
        return
    }
    card.classList.add('is-releasing')
    const clear = (event) => {
        if (event && event.propertyName && event.propertyName !== 'transform') return
        card.classList.remove('is-releasing')
    }
    card.addEventListener('transitionend', clear, { once: true })
    window.clearTimeout(releaseTimer)
    releaseTimer = window.setTimeout(() => card.classList.remove('is-releasing'), 520)
}

export function mountLeadDragImage(event) {
    const item = event?.item
    const native = event?.originalEvent
    if (!item || !native?.dataTransfer || motionOff()) return
    clearLeadDragImage()
    const clone = item.cloneNode(true)
    clone.classList.remove('ghost', 'is-tilting', 'is-pressed', 'is-releasing', 'kanban-card--settle')
    clone.classList.add('kanban-card--drag-image')
    clone.style.width = `${item.offsetWidth}px`
    document.body.appendChild(clone)
    const rect = item.getBoundingClientRect()
    const offsetX = Math.max(0, (native.clientX ?? rect.left) - rect.left)
    const offsetY = Math.max(0, (native.clientY ?? rect.top) - rect.top)
    try {
        native.dataTransfer.setDragImage(clone, offsetX, offsetY)
    } catch (_) {
        clone.remove()
        return
    }
    dragImage = clone
}

export function clearLeadDragImage() {
    dragImage?.remove()
    dragImage = null
}

export function settleLeadCard(card) {
    if (!card || motionOff()) return
    releaseLeadCardTilt(card)
    card.classList.add('kanban-card--settle')
    const clear = () => card.classList.remove('kanban-card--settle')
    card.addEventListener('animationend', clear, { once: true })
    window.setTimeout(clear, 640)
}
