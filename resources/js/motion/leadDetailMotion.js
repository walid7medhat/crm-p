/**
 * Fast spatial entrance for the lead detail modal.
 * Uses the clicked card as the origin when one was recorded.
 * Does not delay data loading.
 */
import gsap from 'gsap'
import { prefersReducedMotion } from './leadStageMotion'

let originRect = null
let activeTween = null

export function rememberLeadDetailOrigin(el) {
    if (!el?.getBoundingClientRect) {
        originRect = null
        return
    }
    originRect = el.getBoundingClientRect()
}

export function takeLeadDetailOrigin() {
    const rect = originRect
    originRect = null
    return rect
}

function groups(root) {
    return [
        root.querySelector('[data-motion="identity"]'),
        root.querySelector('[data-motion="stages"]'),
        root.querySelector('[data-motion="tabs"]'),
        root.querySelector('.lead-view-main'),
        root.querySelector('.lead-view-side'),
    ].filter(Boolean)
}

/**
 * @param {HTMLElement} root
 * @param {DOMRect|null} origin
 * @param {Function} [onDone]
 * @param {{ instant?: boolean }} [opts] When instant, skip opacity:0 hide (seeded kanban open).
 */
export function playLeadDetailEntrance(root, origin, onDone, opts = {}) {
    if (!root || prefersReducedMotion() || opts.instant) {
        if (root && opts.instant) {
            gsap.set([root, ...groups(root)], { clearProps: 'opacity,transform' })
        }
        onDone?.()
        return
    }
    activeTween?.kill()
    const nodes = groups(root)
    const box = root.getBoundingClientRect()
    const dx = origin
        ? (origin.left + origin.width / 2) - (box.left + box.width / 2)
        : 0
    const dy = origin
        ? (origin.top + origin.height / 2) - (box.top + box.height / 2)
        : 28

    gsap.set(nodes, { opacity: 0, y: 16 })
    const tl = gsap.timeline({
        onComplete: () => {
            onDone?.()
            requestAnimationFrame(() => {
                gsap.set([root, ...nodes], { clearProps: 'all' })
            })
        },
    })
    activeTween = tl
    tl.fromTo(root, {
        x: dx,
        y: dy,
        scale: origin ? 0.28 : 0.96,
        opacity: origin ? 0.55 : 0.4,
        transformOrigin: 'center center',
    }, {
        x: 0,
        y: 0,
        scale: 1,
        opacity: 1,
        duration: 0.32,
        ease: 'power3.out',
    }, 0)

    const identity = root.querySelector('[data-motion="identity"]')
    const stages = root.querySelector('[data-motion="stages"]')
    const tabs = root.querySelector('[data-motion="tabs"]')
    const main = root.querySelector('.lead-view-main')
    const side = root.querySelector('.lead-view-side')
    if (identity) tl.to(identity, { opacity: 1, y: 0, duration: 0.18, ease: 'power2.out' }, 0.06)
    if (tabs) tl.to(tabs, { opacity: 1, y: 0, duration: 0.16, ease: 'power2.out' }, 0.1)
    if (stages) tl.to(stages, { opacity: 1, y: 0, duration: 0.18, ease: 'power2.out' }, 0.12)
    if (main) tl.to(main, { opacity: 1, y: 0, duration: 0.2, ease: 'power2.out' }, 0.14)
    if (side) tl.to(side, { opacity: 1, y: 0, duration: 0.2, ease: 'power2.out' }, 0.2)
    if (!nodes.length) tl.set({}, {}, 0.32)
}

export function playLeadDetailData(root) {
    if (!root || prefersReducedMotion()) return
    const nodes = [root.querySelector('.lead-view-main'), root.querySelector('.lead-view-side')].filter(Boolean)
    if (!nodes.length) return
    // Already painted (e.g. from card seed) — don't flash opacity:0 over visible content.
    const alreadyVisible = nodes.every((n) => {
        const o = window.getComputedStyle(n).opacity
        return o === '' || Number(o) > 0.5
    })
    if (alreadyVisible) return
    gsap.fromTo(nodes, { opacity: 0, y: 14 }, {
        opacity: 1,
        y: 0,
        duration: 0.28,
        stagger: 0.06,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
    })
}

export function playLeadDetailExit(root) {
    if (!root || prefersReducedMotion()) return Promise.resolve()
    activeTween?.kill()
    return new Promise((resolve) => {
        activeTween = gsap.to(root, {
            y: 12,
            scale: 0.98,
            opacity: 0,
            duration: 0.2,
            ease: 'power2.in',
            onComplete: resolve,
        })
    })
}

export function stopLeadDetailMotion() {
    activeTween?.kill()
    activeTween = null
}

/**
 * Kill any running tween and drop the inline opacity/transform it left behind.
 * An interrupted entrance leaves every section at opacity 0, so the next lead
 * "opened" into an invisible modal.
 */
export function resetLeadDetailMotion(root) {
    stopLeadDetailMotion()
    if (!root) return
    gsap.set([root, ...groups(root)], { clearProps: 'opacity,transform' })
}
