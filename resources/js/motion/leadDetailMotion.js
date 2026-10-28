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

export function playLeadDetailEntrance(root, origin, onDone) {
    if (!root || prefersReducedMotion()) {
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
        duration: 0.42,
        ease: 'power3.out',
    }, 0)

    const identity = root.querySelector('[data-motion="identity"]')
    const stages = root.querySelector('[data-motion="stages"]')
    const tabs = root.querySelector('[data-motion="tabs"]')
    const main = root.querySelector('.lead-view-main')
    const side = root.querySelector('.lead-view-side')
    if (identity) tl.to(identity, { opacity: 1, y: 0, duration: 0.24, ease: 'power2.out' }, 0.1)
    if (tabs) tl.to(tabs, { opacity: 1, y: 0, duration: 0.22, ease: 'power2.out' }, 0.16)
    if (stages) tl.to(stages, { opacity: 1, y: 0, duration: 0.24, ease: 'power2.out' }, 0.18)
    if (main) tl.to(main, { opacity: 1, y: 0, duration: 0.28, ease: 'power2.out' }, 0.22)
    if (side) tl.to(side, { opacity: 1, y: 0, duration: 0.28, ease: 'power2.out' }, 0.32)
    if (!nodes.length) tl.set({}, {}, 0.42)
}

export function playLeadDetailData(root) {
    if (!root || prefersReducedMotion()) return
    const nodes = [root.querySelector('.lead-view-main'), root.querySelector('.lead-view-side')].filter(Boolean)
    if (!nodes.length) return
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
