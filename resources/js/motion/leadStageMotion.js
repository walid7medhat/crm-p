/**
 * Stage-aware lead motion.
 *
 * Scenes are presentational only. Stage identity comes from the board columns
 * (name + order) that the CRM already rendered. This module does not decide
 * which stage a lead belongs in.
 *
 * Live pipeline (this database), matched by name and order, not by guessing:
 *   1 New Lead · 2 Assigned · 3 Contacted · 4 Qualified · 6 Converted
 *   7 Shared Leads · 8 Lost Lead · 9 Lead Pool · 10 Unqualified
 * Contacted call outcome is the existing interaction_result: answered | no_answer.
 */
import { reactive } from 'vue'
import gsap from 'gsap'
import { Flip } from 'gsap/Flip'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl.js'

gsap.registerPlugin(Flip)

export const motionHeld = reactive({})

const SCENE_DURATION = {
    arrival: 2.02,
    arrivalNoAnswer: 2.08,
    qualify: 1.18,
    deliver: 2.9,
    contact: 0.9,
    handover: 2.8,
    convert: 2.15,
    depart: 0.78,
    reject: 1.28,
    travel: 0.7,
}

const PALETTE = {
    navy: '#1c1730',
    navyDeep: '#0f172a',
    purple: 'var(--color-purple)',
    purpleDeep: '#4c1d6e',
    lilac: '#c084fc',
    orange: '#ff8a00',
    gold: '#f5c542',
    amber: '#eec207',
}

const jobs = new Map()
const stashes = new Map()
const dragOrigins = new Map()
let stylesReady = false
let rootEl = null
const heldIds = new Set()

function hold(id) {
    heldIds.add(id)
    motionHeld[id] = true
}

function releaseHold(id) {
    heldIds.delete(id)
    delete motionHeld[id]
    const card = cardEl(id)
    if (!card) return
    card.style.opacity = ''
    card.style.transform = ''
    try {
        gsap.set(card, { clearProps: 'opacity,transform,filter' })
    } catch (_) {
        /* The stage change has already landed. A style reset must not trap the card. */
    }
}

function clearJobTimer(job) {
    if (!job?.safetyTimer) return
    clearTimeout(job.safetyTimer)
    job.safetyTimer = null
}

export function prefersReducedMotion() {
    return typeof window !== 'undefined'
        && window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
}

export function stageKind(stage) {
    const name = String(stage?.name || stage?.title || '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '')
    const order = Number(stage?.order)
    if (name.includes('unqualified') || order === 10) return 'unqualified'
    if (name.includes('lost') || order === 8) return 'lost'
    if (name.includes('converted') || order === 6) return 'converted'
    if (name.includes('qualified') || order === 4) return 'qualified'
    if (name.includes('contacted') || name.includes('followup') || order === 3) return 'contacted'
    if (name.includes('assign') || order === 2) return 'assigned'
    if (name.includes('newlead') || name === 'new' || order === 1) return 'new'
    if (name.includes('leadpool') || order === 9) return 'pool'
    if (name.includes('shared') || order === 7) return 'shared'
    if (name.includes('future') || name.includes('prospect') || order === 5) return 'future'
    return name ? 'other' : 'unknown'
}

function stageRank(stage) {
    const order = Number(stage?.order)
    if (Number.isFinite(order) && order > 0) return order
    const ranks = {
        new: 1,
        assigned: 2,
        contacted: 3,
        qualified: 4,
        future: 5,
        converted: 6,
        shared: 7,
        lost: 8,
        pool: 9,
        unqualified: 10,
    }
    const rank = ranks[stageKind(stage)]
    return Number.isFinite(rank) ? rank : null
}

export function classifyLeadTransition(from, to, interactionResult) {
    if (!from || !to) return null
    if (String(from.id) === String(to.id)) return null
    const source = stageKind(from)
    const target = stageKind(to)
    if (source === target) return null
    const sourceRank = stageRank(from)
    const targetRank = stageRank(to)
    // Moving back to an earlier column just lands the card. No scene.
    if (sourceRank != null && targetRank != null && targetRank < sourceRank) return null
    const result = interactionResult === 'no_answer' || interactionResult === 'answered'
        ? interactionResult
        : null

    // The dropped column picks the scene. Source only keeps a variant that
    // destination already has (new-lead arrival, call result, qualified car).
    if (target === 'contacted' && source === 'assigned') {
        return { scene: 'contact', variant: null, duration: SCENE_DURATION.contact }
    }
    if (target === 'contacted' && (source === 'new' || result)) {
        const variant = result === 'no_answer' ? 'no_answer' : 'answered'
        return {
            scene: 'arrival',
            variant,
            duration: variant === 'no_answer' ? SCENE_DURATION.arrivalNoAnswer : SCENE_DURATION.arrival,
        }
    }
    if (target === 'contacted') {
        return { scene: 'contact', variant: null, duration: SCENE_DURATION.contact }
    }
    if (target === 'qualified' && source === 'contacted') {
        return { scene: 'deliver', variant: null, duration: SCENE_DURATION.deliver }
    }
    if (target === 'assigned') {
        return { scene: 'handover', variant: null, duration: SCENE_DURATION.handover }
    }
    if (target === 'qualified') {
        return { scene: 'qualify', variant: null, duration: SCENE_DURATION.qualify }
    }
    if (target === 'converted') {
        return { scene: 'convert', variant: null, duration: SCENE_DURATION.convert }
    }
    if (target === 'unqualified') {
        return { scene: 'reject', variant: null, duration: SCENE_DURATION.reject }
    }
    if (target === 'lost') {
        return { scene: 'depart', variant: 'lost', duration: SCENE_DURATION.depart }
    }
    return { scene: 'travel', variant: null, duration: SCENE_DURATION.travel }
}

export function noteLeadDragOrigin(event) {
    const el = event?.item
    const id = Number(el?.getAttribute?.('data-lead-id'))
    if (!id || !el?.getBoundingClientRect) return
    dragOrigins.set(id, el.getBoundingClientRect())
}

function cardEl(id) {
    const value = String(id)
    const nodes = document.querySelectorAll('[data-lead-id]')
    for (const node of nodes) {
        if (node.getAttribute('data-lead-id') === value) return node
    }
    return null
}

function stageEl(id) {
    if (id == null || id === '') return null
    const value = String(id)
    const nodes = document.querySelectorAll('[data-stage-id]')
    for (const node of nodes) {
        if (node.getAttribute('data-stage-id') === value) return node
    }
    return null
}

function stageByKind(kind) {
    const nodes = document.querySelectorAll('[data-stage-id]')
    for (const node of nodes) {
        const meta = readStageEl(node)
        if (stageKind(meta) === kind) return node
    }
    return null
}

function readStageEl(el) {
    if (!el) return null
    return {
        id: el.getAttribute('data-stage-id'),
        name: el.getAttribute('data-stage-name') || '',
        order: Number(el.getAttribute('data-stage-order')),
    }
}

function hydrateStage(stage) {
    if (!stage) return null
    const el = stageEl(stage.id)
    const live = readStageEl(el)
    return {
        id: stage.id ?? live?.id,
        name: stage.name || stage.title || live?.name || '',
        order: Number(stage.order ?? live?.order ?? 0),
    }
}

function leadLabel(explicit, id) {
    const fromCard = cardEl(id)?.querySelector?.('.task-title, .lead-list-name')?.textContent?.trim()
    const name = String(explicit || fromCard || 'Lead').trim()
    return name || 'Lead'
}

function ensureStyles() {
    if (stylesReady || typeof document === 'undefined') return
    stylesReady = true
    const style = document.createElement('style')
    style.id = 'lead-stage-motion-css'
    style.textContent = `
        .kanban-card--motion-hold,
        tr.lead-row--motion-hold { opacity: 0 !important; pointer-events: none !important; }
        .lsm-root { position: fixed; inset: 0; z-index: 8000; pointer-events: none; overflow: visible; }
        .lsm-canvas { position: absolute; inset: 0; width: 100%; height: 100%; }
        .lsm-haul, .lsm-flight, .lsm-ghost, .lsm-ring, .lsm-seal, .lsm-portal, .lsm-dust, .lsm-stamp, .lsm-slash {
            position: fixed; left: 0; top: 0; pointer-events: none; will-change: transform;
        }
        .lsm-haul { width: 420px; height: 160px; overflow: visible; transform-origin: 200px 148px; }
        .lsm-haul svg { display: block; width: 420px; height: 160px; overflow: visible; filter: drop-shadow(0 18px 18px rgba(12,10,8,.42)); }
        .lsm-trail {
            position: absolute; left: 8px; top: 146px; width: 250px; height: 8px; border-radius: 99px;
            background: linear-gradient(90deg, transparent, rgba(238,194,7,.2) 30%, rgba(245,197,66,.95));
            filter: blur(1px); opacity: 0; transform-origin: right center;
        }
        .lsm-roof { position: absolute; left: 196px; top: 28px; width: 0; height: 0; }
        .lsm-cargo { position: absolute; left: -103px; top: -78px; width: 206px; transform-origin: 103px 78px; }
        .lsm-cargo.is-free { position: fixed; left: 0; top: 0; margin: 0; }
        .lsm-wheel { transform-box: view-box; }
        .lsm-haul.is-rough .lsm-wheel { animation-duration: .22s; }
        @keyframes lsm-roll { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .lsm-wheel { animation: none; } }
        .lsm-flight { width: 214px; transform-origin: 50% 78%; }
        .lsm-card {
            margin: -14px auto 0; width: 214px; min-height: 72px; padding: 10px 12px 11px;
            border-radius: 14px; background: #fff; color: #1c1730;
            box-shadow: 0 16px 34px rgba(28,23,48,.22), 0 0 0 1px rgba(var(--color-purple-rgb),.12);
            border-left: 5px solid var(--color-primary); font-family: Montserrat, sans-serif;
        }
        .lsm-card__kicker {
            font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--color-purple);
        }
        .lsm-card__name {
            margin-top: 2px; font-size: 15px; font-weight: 700; line-height: 1.25; color: #1c1730;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .lsm-card__meta { margin-top: 3px; font-size: 11px; font-weight: 600; color: #64748b; }
        .lsm-cargo .lsm-card { margin-top: 0; }
        .lsm-ring {
            border-radius: 16px; border: 3px solid rgba(245,197,66,.9);
            box-shadow: 0 0 0 8px rgba(var(--color-purple-rgb),.12), inset 0 0 24px rgba(255,138,0,.18);
        }
        .lsm-portal {
            border-radius: 999px;
            background: radial-gradient(circle, rgba(245,197,66,.85) 0%, rgba(255,138,0,.35) 42%, rgba(var(--color-purple-rgb),0) 70%);
        }
        .lsm-seal {
            width: 74px; height: 74px; margin-left: -37px; margin-top: -37px;
            filter: drop-shadow(0 8px 12px rgba(28,23,48,.28));
        }
        .lsm-dust { width: 90px; height: 18px; margin-left: -45px; border-radius: 999px; background: rgba(28,23,48,.16); filter: blur(2px); }
        .lsm-reject .lsm-card { border-left-color: #c23b4a; }
        .lsm-reject .lsm-card__kicker, .lsm-reject .lsm-card__meta { color: #9f1239; }
        .lsm-stamp {
            width: 92px; height: 92px; margin-left: -46px; margin-top: -46px;
            filter: drop-shadow(0 8px 10px rgba(127,29,29,.32));
        }
        .lsm-slash {
            width: 168px; height: 5px; margin-top: -2.5px; border-radius: 99px;
            background: linear-gradient(90deg, transparent, #c23b4a 12%, #9f1239 88%, transparent);
            transform-origin: left center;
        }
        .lsm-deliver, .lsm-mascot, .lsm-deliver-card {
            position: fixed; left: 0; top: 0; pointer-events: none; will-change: transform;
        }
        .lsm-deliver { width: 280px; height: 108px; overflow: visible; z-index: 1; transform-origin: left top; }
        .lsm-deliver svg { display: block; width: 280px; height: 108px; overflow: visible; filter: drop-shadow(0 16px 14px rgba(1,6,45,.38)); }
        .lsm-deliver-trail {
            position: absolute; left: -70px; top: 96px; width: 120px; height: 5px; border-radius: 99px;
            background: linear-gradient(90deg, transparent, rgba(250,163,0,.15) 20%, #FAA300);
            filter: blur(.6px); opacity: 0; transform-origin: right center;
        }
        .lsm-deliver-wheel { transform-box: fill-box; transform-origin: center; animation: lsm-roll .36s linear infinite; }
        .lsm-mascot { width: 74px; height: 112px; z-index: 5; transform-origin: 50% 100%; filter: drop-shadow(0 10px 8px rgba(1,6,45,.28)); }
        .lsm-mascot svg { display: block; width: 74px; height: 112px; overflow: visible; }
        .lsm-wink, .lsm-thumb, .lsm-spark { transform-box: fill-box; transform-origin: center; }
        .lsm-thumb { transform-origin: 18% 78%; opacity: 0; }
        .lsm-spark { opacity: 0; transform-origin: center; }
        .lsm-mascot.is-wink .lsm-wink { animation: lsm-wink .5s ease; }
        .lsm-mascot.is-cheer .lsm-thumb { animation: lsm-thumb .36s cubic-bezier(.2,1.35,.36,1) forwards; }
        .lsm-mascot.is-cheer .lsm-spark { animation: lsm-spark .7s ease; }
        .lsm-deliver-card { z-index: 4; }
        .lsm-deliver-card .lsm-card { width: 100%; margin: 0; }
        .lsm-deliver-card .kanban-card { margin: 0 !important; width: 100%; pointer-events: none !important; }
        @keyframes lsm-wink { 0%, 100% { transform: scaleY(1); } 38%, 58% { transform: scaleY(.1); } }
        @keyframes lsm-thumb { from { opacity: 0; transform: translateY(5px) rotate(16deg) scale(.65); } to { opacity: 1; transform: none; } }
        @keyframes lsm-spark { 0% { opacity: 0; transform: scale(.5); } 35% { opacity: 1; transform: scale(1); } 100% { opacity: 0; transform: scale(1.15); } }
        .lsm-jeep { position: fixed; left: 0; top: 0; width: 420px; height: 160px; pointer-events: none; overflow: visible; z-index: 1; transform-origin: left top; }
        .lsm-jeep svg { display: block; width: 420px; height: 160px; overflow: visible; filter: drop-shadow(0 16px 14px rgba(12,10,8,.35)); }
        .lsm-cabin-hole {
            position: absolute; left: 188px; top: 70px; width: 64px; height: 42px; z-index: 2;
            background: #070605; border-radius: 2px; opacity: 0;
            box-shadow: inset 0 0 0 1px rgba(201,162,39,.5);
        }
        .lsm-door-leaf {
            position: absolute; left: 186px; top: 66px; width: 68px; height: 48px; z-index: 3;
            transform-origin: 100% 8%; opacity: 0;
        }
        .lsm-door-leaf svg { display: block; width: 68px; height: 48px; overflow: visible; }
        .lsm-agent { position: fixed; left: 0; top: 0; width: 68px; height: 140px; pointer-events: none; z-index: 5; transform-origin: 50% 100%; filter: drop-shadow(0 8px 8px rgba(1,6,45,.22)); }
        .lsm-agent svg { display: block; width: 68px; height: 140px; overflow: visible; }
        .lsm-agent.is-receive .lsm-agent-figure { transform: scaleX(-1); }
        .lsm-agent.is-photo .lsm-head { display: none; }
        .lsm-agent__face {
            position: absolute; left: 50%; top: 7px; width: 30px; height: 30px; margin-left: -15px;
            border-radius: 50%; object-fit: cover; border: 1.5px solid #C4A15A; background: #e7eef8; z-index: 2;
        }
        .lsm-agent__meta {
            position: absolute; left: 58px; top: 4px; width: 132px; text-align: left;
            font-family: Montserrat, sans-serif; z-index: 3;
        }
        .lsm-agent.is-receive .lsm-agent__meta { left: auto; right: 62px; text-align: right; }
        .lsm-agent__kicker {
            font-size: 9px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: #8a6410;
        }
        .lsm-agent__name {
            display: inline-block; max-width: 132px; margin-top: 2px; padding: 2px 8px;
            font-size: 12px; font-weight: 700; line-height: 1.3; color: #01062d;
            background: rgba(255,255,255,.96); border: 1px solid rgba(196,161,90,.7); border-radius: 7px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .lsm-agent .lsm-thumb { transform-box: fill-box; transform-origin: 40% 90%; opacity: 0; }
        .lsm-agent.is-cheer .lsm-thumb { animation: lsm-thumb .32s cubic-bezier(.2,1.2,.36,1) forwards; }
        .lsm-check { position: fixed; left: 0; top: 0; width: 28px; height: 28px; pointer-events: none; z-index: 6; }
        .lsm-pusher {
            position: fixed; left: 0; top: 0; width: 130px; height: 140px; pointer-events: none;
            z-index: 6; overflow: visible; transform-origin: 0 100%;
            filter: drop-shadow(0 8px 7px rgba(1,6,45,.22));
        }
        .lsm-pusher svg { display: block; width: 100%; height: 100%; overflow: visible; }
        .lsm-push-mouth { transform-box: fill-box; transform-origin: center; }
        @media (prefers-reduced-motion: reduce) {
            .lsm-deliver-wheel, .lsm-mascot.is-wink .lsm-wink, .lsm-mascot.is-cheer .lsm-thumb, .lsm-mascot.is-cheer .lsm-spark, .lsm-agent.is-cheer .lsm-thumb { animation: none; }
        }
    `
    document.head.appendChild(style)
}

function overlay() {
    ensureStyles()
    if (rootEl?.isConnected) return rootEl
    rootEl = document.createElement('div')
    rootEl.className = 'lsm-root'
    rootEl.setAttribute('aria-hidden', 'true')
    document.body.appendChild(rootEl)
    return rootEl
}

function destroyRootIfIdle() {
    if (jobs.size || !rootEl) return
    rootEl.remove()
    rootEl = null
}

function cardHtml(name, meta) {
    const safeName = escapeHtml(name)
    const safeMeta = escapeHtml(meta)
    return `<div class="lsm-card"><div class="lsm-card__kicker">Lead</div><div class="lsm-card__name">${safeName}</div><div class="lsm-card__meta">${safeMeta}</div></div>`
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

const ROOF = { x: 196, y: 28 }
const ROOF_CARD_H = 78

function wheelMarkup(cx, extra = '') {
    const cy = 112
    const spokes = Array.from({ length: 5 }, (_, i) => {
        const a = (i / 5) * Math.PI
        const line = (angle, inner, outer) => {
            const x1 = (cx + Math.cos(angle) * inner).toFixed(1)
            const y1 = (cy + Math.sin(angle) * inner).toFixed(1)
            const x2 = (cx + Math.cos(angle) * outer).toFixed(1)
            const y2 = (cy + Math.sin(angle) * outer).toFixed(1)
            return `M${x1} ${y1} L${x2} ${y2}`
        }
        return `<path d="${line(a, 6, 15)} ${line(a + Math.PI, 6, 15)}" stroke="#1a140c" stroke-width="2.6" stroke-linecap="round"/>`
    }).join('')
    return `
        <g class="lsm-wheel${extra}">
            <circle cx="${cx}" cy="${cy}" r="27" fill="#0e0c0a"/>
            <circle cx="${cx}" cy="${cy}" r="23.5" fill="none" stroke="#d4af37" stroke-width="2.4"/>
            <circle cx="${cx}" cy="${cy}" r="18" fill="url(#lsm-rim)"/>
            <circle cx="${cx}" cy="${cy}" r="14" fill="#14110c"/>
            ${spokes}
            <circle cx="${cx}" cy="${cy}" r="6.2" fill="#1a140c" stroke="${PALETTE.gold}" stroke-width="1.6"/>
            <circle cx="${cx}" cy="${cy}" r="2.3" fill="${PALETTE.gold}"/>
        </g>`
}

function spinWheels(root) {
    const tweens = []
    root.querySelectorAll('.lsm-wheel').forEach((wheel) => {
        const circle = wheel.querySelector('circle')
        const cx = circle?.getAttribute('cx') || '0'
        const cy = circle?.getAttribute('cy') || '0'
        tweens.push(gsap.to(wheel, {
            rotation: 360,
            svgOrigin: `${cx} ${cy}`,
            duration: 0.48,
            ease: 'none',
            repeat: -1,
        }))
    })
    return tweens
}

function carMarkup() {
    return `
        <svg class="lsm-car" width="420" height="160" viewBox="0 0 420 160" fill="none" aria-hidden="true">
            <defs>
                <linearGradient id="lsm-paint" x1="90" y1="30" x2="90" y2="120" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#5c5144"/>
                    <stop offset=".16" stop-color="#2a241c"/>
                    <stop offset=".42" stop-color="#14110e"/>
                    <stop offset="1" stop-color="#050403"/>
                </linearGradient>
                <linearGradient id="lsm-glass" x1="0" y1="42" x2="0" y2="74" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#e7f3fb"/>
                    <stop offset=".4" stop-color="#8eacbf"/>
                    <stop offset="1" stop-color="#1b2c38"/>
                </linearGradient>
                <radialGradient id="lsm-rim" cx="42%" cy="38%" r="62%">
                    <stop offset="0" stop-color="#fff6d4"/>
                    <stop offset=".45" stop-color="#e0b33a"/>
                    <stop offset="1" stop-color="#6e5010"/>
                </radialGradient>
                <radialGradient id="lsm-lamp" cx="40%" cy="40%" r="60%">
                    <stop offset="0" stop-color="#fffef8"/>
                    <stop offset=".55" stop-color="#f3e2a4"/>
                    <stop offset="1" stop-color="#9a7a28"/>
                </radialGradient>
            </defs>
            <ellipse cx="210" cy="148" rx="132" ry="6" fill="${PALETTE.gold}" opacity=".3"/>
            <rect x="112" y="74" width="20" height="10" rx="2" fill="#1a1612"/>
            <circle cx="108" cy="80" r="33" fill="#16130f" stroke="#3a3228" stroke-width="3"/>
            <circle cx="108" cy="80" r="21" fill="#0c0b0a" stroke="#c9a227" stroke-width="2.4"/>
            <circle cx="108" cy="80" r="7" fill="#c9a227"/>
            <path d="M150 112 A22 20 0 0 1 194 112 Z" fill="#050403"/>
            <path d="M266 112 A22 20 0 0 1 310 112 Z" fill="#050403"/>
            <path d="M124 112 V46 Q124 40 132 40 H254 L266 72 H324 L336 78 V104 H324 V112 H316 A28 26 0 0 0 260 112 H200 A28 26 0 0 0 144 112 Z" fill="url(#lsm-paint)" stroke="#6a5428" stroke-width="1.35"/>
            <path d="M138 46 H192 V70 H138 Z" fill="url(#lsm-glass)" stroke="#c9a227" stroke-width="1.35"/>
            <path d="M202 46 H242 V70 H202 Z" fill="url(#lsm-glass)" stroke="#c9a227" stroke-width="1.35"/>
            <path d="M250 46 H258 L266 70 H250 Z" fill="url(#lsm-glass)" stroke="#c9a227" stroke-width="1.35"/>
            <path d="M144 49 H186 M208 49 H236" stroke="#fff" stroke-width="1.3" opacity=".4"/>
            <path d="M136 40 H248" stroke="#8d7040" stroke-width="2"/>
            <path d="M150 32 H236" stroke="#c9a227" stroke-width="2.6" stroke-linecap="round"/>
            <path d="M150 32 V40 M193 32 V40 M236 32 V40" stroke="#8a7020" stroke-width="2.2"/>
            <path d="M128 73 H318" stroke="${PALETTE.gold}" stroke-width="1.8"/>
            <rect x="196" y="76" width="5" height="4" rx=".6" fill="#d4af37"/>
            <rect x="196" y="90" width="5" height="4" rx=".6" fill="#d4af37"/>
            <rect x="248" y="76" width="5" height="4" rx=".6" fill="#d4af37"/>
            <rect x="248" y="92" width="5" height="4" rx=".6" fill="#d4af37"/>
            <rect x="168" y="86" width="15" height="3.2" rx="1" fill="${PALETTE.gold}"/>
            <rect x="274" y="86" width="15" height="3.2" rx="1" fill="${PALETTE.gold}"/>
            <text x="198" y="98" fill="${PALETTE.gold}" font-family="Georgia, serif" font-size="16" font-weight="700" letter-spacing="3">OIA</text>
            <path d="M144 112 A28 26 0 0 1 200 112" stroke="#c9a227" stroke-width="1.6" fill="none"/>
            <path d="M260 112 A28 26 0 0 1 316 112" stroke="#c9a227" stroke-width="1.6" fill="none"/>
            <rect x="188" y="114" width="84" height="5" rx="1.5" fill="#1a1612"/>
            <path d="M192 115 H268" stroke="#c9a227" stroke-width="1"/>
            <rect x="124" y="74" width="6" height="24" rx="1" fill="#c23b4a"/>
            <rect x="326" y="74" width="10" height="30" rx="1.2" fill="#0a0908" stroke="#d4af37" stroke-width="1.2"/>
            <path d="M328 82 H334 M328 89 H334 M328 96 H334" stroke="#c9a227" stroke-width="1.5" stroke-linecap="round"/>
            <circle cx="316" cy="84" r="8" fill="#1a140c" stroke="#d4af37" stroke-width="1.6"/>
            <circle cx="316" cy="84" r="4.8" fill="url(#lsm-lamp)"/>
            <rect x="292" y="90" width="12" height="4.5" rx="1" fill="#e7a317"/>
            ${wheelMarkup(172)}
            ${wheelMarkup(288, ' lsm-wheel-front')}
        </svg>`
}

let agentSeq = 0

function agentMarkup() {
    agentSeq += 1
    const suit = `lsm-suit-${agentSeq}`
    const skin = `lsm-skin-${agentSeq}`
    return `
        <svg class="lsm-agent-figure" width="68" height="140" viewBox="0 0 86 176" fill="none" aria-hidden="true">
            <defs>
                <linearGradient id="${suit}" x1="24" y1="52" x2="70" y2="128" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#24356f"/>
                    <stop offset=".5" stop-color="#101a40"/>
                    <stop offset="1" stop-color="#01062d"/>
                </linearGradient>
                <radialGradient id="${skin}" cx="40%" cy="34%" r="70%">
                    <stop offset="0" stop-color="#f0d0b4"/>
                    <stop offset="1" stop-color="#c99572"/>
                </radialGradient>
            </defs>
            <ellipse cx="43" cy="172" rx="16" ry="2.6" fill="#01062d" opacity=".16"/>
            <path d="M30 118 h9 l1.4 42 h-12 z" fill="#01062d"/>
            <path d="M46 118 h9 l.6 42 h-12 z" fill="#0c1738"/>
            <path d="M27 158 h16 v5.5 h-18.5 z" fill="#01062d"/>
            <path d="M43 158 h16 v5.5 h-18 z" fill="#01062d"/>
            <path d="M20 64 C20 52 28 48 40 48 H50 C64 48 68 56 68 66 V120 H20 Z" fill="url(#${suit})"/>
            <path d="M38 50 L44 86 L32 86 Z" fill="#f7f4ee"/>
            <path d="M40.2 58 h2.4 l-1.2 16 z" fill="#C4A15A"/>
            <rect x="52" y="78" width="8" height="5" rx=".7" fill="#C4A15A"/>
            <path d="M20 68 C12 72 12 90 15 104 C17 108 24 106 23 98 V72 Z" fill="#0c1844"/>
            <path d="M66 66 C74 74 80 86 77 98 C75 103 68 101 69 94 C72 84 70 74 62 68 Z" fill="#162652"/>
            <ellipse cx="78" cy="101" rx="5.4" ry="3.8" fill="#d7b396"/>
            <path d="M38 46 h10 v8 h-10 z" fill="#e6c6aa"/>
            <g class="lsm-head">
                <ellipse cx="43" cy="32" rx="13" ry="15" fill="url(#${skin})"/>
                <path d="M30 30 C30 16 36 12 43 12 C54 12 58 18 58 31 C54 22 49 18 43 18 C36 18 32 22 30 30 Z" fill="#1a140f"/>
                <ellipse cx="38" cy="33" rx="1.15" ry="1.35" fill="#1c140e"/>
                <ellipse cx="48" cy="33" rx="1.15" ry="1.35" fill="#1c140e"/>
                <path d="M35.5 29.2 h5.2 M45.4 29.2 h5.2" stroke="#5a4034" stroke-width=".7" stroke-linecap="round"/>
                <path d="M39 41.5 Q43 43.4 47 41.5" stroke="#a56d58" stroke-width="1.05" stroke-linecap="round"/>
            </g>
            <g class="lsm-thumb" opacity="0">
                <path d="M58 24 c1.6-6 6.2-8 8.2-5.2 c1.6 2.2.2 5.6-2.2 8.4 l-4.2 3.2 z" fill="#e2b894"/>
                <path d="M57 30 c4 1.2 8 2.6 8.6 5.2 c.4 2-2 3.2-5.2 2.6 z" fill="#c99572"/>
            </g>
        </svg>`
}

function checkMarkup() {
    return `
        <svg class="lsm-check-mark" width="28" height="28" viewBox="0 0 28 28" aria-hidden="true">
            <circle cx="14" cy="14" r="12" fill="#01062d"/>
            <path d="M8 14.5 L12 18.5 L20 10" stroke="#FAA300" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>`
}

function rejectStampMarkup() {
    return `
        <svg width="92" height="92" viewBox="0 0 92 92" aria-hidden="true">
            <circle cx="46" cy="46" r="38" fill="rgba(255,255,255,.92)" stroke="#c23b4a" stroke-width="4"/>
            <circle cx="46" cy="46" r="30" fill="none" stroke="#9f1239" stroke-width="1.4" stroke-dasharray="3 3"/>
            <path d="M30 30 L62 62 M62 30 L30 62" stroke="#c23b4a" stroke-width="6" stroke-linecap="round"/>
        </svg>`
}

function sealMarkup() {
    return `
        <svg width="74" height="74" viewBox="0 0 74 74" aria-hidden="true">
            <circle cx="37" cy="37" r="34" fill="${PALETTE.gold}" stroke="${PALETTE.navy}" stroke-width="3"/>
            <circle cx="37" cy="37" r="26" fill="none" stroke="${PALETTE.purple}" stroke-width="1.5"/>
            <path d="M37 18 L52 30 V52 H22 V30 Z" fill="${PALETTE.navy}"/>
            <path d="M37 22 L48 30 V48 H26 V30 Z" fill="${PALETTE.purple}"/>
            <rect x="33" y="38" width="8" height="10" rx="1" fill="${PALETTE.gold}"/>
        </svg>`
}

function makeParticleField() {
    const canvas = document.createElement('canvas')
    canvas.className = 'lsm-canvas'
    const ctx = canvas.getContext('2d')
    const particles = []
    let raf = 0
    let running = false
    let last = 0

    function resize() {
        const dpr = Math.min(window.devicePixelRatio || 1, 2)
        canvas.width = Math.floor(window.innerWidth * dpr)
        canvas.height = Math.floor(window.innerHeight * dpr)
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    }

    function frame(now) {
        if (!running) return
        const dt = Math.min(0.034, (now - last) / 1000 || 0.016)
        last = now
        ctx.clearRect(0, 0, window.innerWidth, window.innerHeight)
        let alive = false
        particles.forEach((p) => {
            if (p.life <= 0) return
            alive = true
            p.vy += p.g * dt
            p.x += p.vx * dt
            p.y += p.vy * dt
            p.rot += p.vr * dt
            p.life -= dt * p.fade
            ctx.save()
            ctx.translate(p.x, p.y)
            ctx.rotate(p.rot)
            ctx.globalAlpha = Math.max(0, Math.min(1, p.life))
            ctx.fillStyle = p.color
            if (p.shape === 'star') {
                drawStar(ctx, 0, 0, p.w)
            } else {
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h)
            }
            ctx.restore()
        })
        if (alive) raf = requestAnimationFrame(frame)
        else {
            running = false
            ctx.clearRect(0, 0, window.innerWidth, window.innerHeight)
        }
    }

    function burst(origin, options) {
        resize()
        const count = options.count
        for (let i = 0; i < count; i += 1) {
            const aim = options.aim ?? -Math.PI / 2
            const angle = options.radial
                ? (Math.PI * 2 * i) / count + Math.random() * 0.2
                : aim + (Math.random() - 0.5) * options.spread
            const speed = options.speed * (0.45 + Math.random())
            particles.push({
                x: origin.x + (Math.random() - 0.5) * (options.jitter || 8),
                y: origin.y + (Math.random() - 0.5) * (options.jitter || 8),
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                g: options.gravity,
                w: options.minSize + Math.random() * options.size,
                h: options.shape === 'star' ? 0 : options.minSize + Math.random() * options.size * 1.8,
                rot: Math.random() * 6,
                vr: (Math.random() - 0.5) * 7,
                color: options.colors[i % options.colors.length],
                life: 1,
                fade: options.fade,
                shape: options.shape || 'rect',
            })
        }
        if (!running) {
            running = true
            last = performance.now()
            raf = requestAnimationFrame(frame)
        }
    }

    return {
        canvas,
        burst,
        stop() {
            running = false
            cancelAnimationFrame(raf)
            particles.length = 0
        },
    }
}

function drawStar(ctx, x, y, r) {
    ctx.beginPath()
    for (let i = 0; i < 5; i += 1) {
        const a = -Math.PI / 2 + (i * 2 * Math.PI) / 5
        const b = a + Math.PI / 5
        ctx.lineTo(x + Math.cos(a) * r, y + Math.sin(a) * r)
        ctx.lineTo(x + Math.cos(b) * r * 0.42, y + Math.sin(b) * r * 0.42)
    }
    ctx.closePath()
    ctx.fill()
}

function boardScroller() {
    return document.querySelector('.kanban-container')
}

function kanbanBoardVisible() {
    const board = boardScroller()
    if (!board || board.classList.contains('kanban-container--list')) return false
    const rect = board.getBoundingClientRect?.()
    return !!rect && rect.width > 40 && rect.height > 40
}

function destinationPlayable(stageId) {
    if (!kanbanBoardVisible()) return false
    const column = stageEl(stageId)
    if (!column) return false
    const rect = columnBodyRect(column) || column.getBoundingClientRect?.()
    return !!rect && rect.width > 8 && rect.height > 8
}

function waitForScroll(tweens) {
    if (!tweens?.length) return Promise.resolve()
    const settled = Promise.all(tweens.map((tween) => {
        if (!tween?.then) return Promise.resolve()
        return Promise.resolve(tween).catch(() => {})
    }))
    return Promise.race([
        settled,
        new Promise((resolve) => setTimeout(resolve, 700)),
    ])
}

function scrollIntoView(column, card) {
    const board = boardScroller()
    const tweens = []
    if (board && column) {
        const boardRect = board.getBoundingClientRect()
        const colRect = column.getBoundingClientRect()
        const offLeft = colRect.left < boardRect.left + 16
        const offRight = colRect.right > boardRect.right - 16
        if (offLeft || offRight) {
            const delta = (colRect.left + colRect.width / 2) - (boardRect.left + boardRect.width / 2)
            tweens.push(gsap.to(board, {
                scrollLeft: board.scrollLeft + delta,
                duration: 0.42,
                ease: 'power2.inOut',
            }))
        }
    }
    const scroller = card?.closest?.('.column-content-scrollable')
    if (scroller && card) {
        const cardRect = card.getBoundingClientRect()
        const box = scroller.getBoundingClientRect()
        const visible = cardRect.top >= box.top + 8 && cardRect.bottom <= box.bottom - 8
        if (!visible && cardRect.height > 0) {
            const delta = cardRect.top - box.top - 16
            tweens.push(gsap.to(scroller, {
                scrollTop: Math.max(0, scroller.scrollTop + delta),
                duration: 0.35,
                ease: 'power2.out',
            }))
        }
    }
    return tweens
}

function visibleBoardRect() {
    const el = document.querySelector('.kanban-container') || document.querySelector('.kanban-wrapper')
    const rect = el?.getBoundingClientRect?.()
    if (rect && rect.width > 40) return rect
    return { left: 80, top: 150, width: 1100, height: 640, right: 1180, bottom: 790 }
}

function columnBodyRect(column) {
    const body = column?.querySelector?.('.column-content-scrollable')
    const rect = body?.getBoundingClientRect?.()
    if (rect && rect.width > 20 && rect.height > 40) return rect
    return column?.getBoundingClientRect?.() || null
}

function firstLeadSlot(column, sample) {
    const scroller = column?.querySelector?.('.column-content-scrollable')
    const host = scroller || column
    if (!host?.getBoundingClientRect) return null
    const body = host.getBoundingClientRect()
    if (body.width < 20 || body.height < 40) return null
    const card = host.querySelector('.kanban-card')
    const cardRect = card?.getBoundingClientRect?.()
    const width = Math.min(
        (cardRect?.width > 8 ? cardRect.width : 0) || sample?.width || 220,
        Math.max(120, body.width - 16),
    )
    const height = (cardRect?.height > 8 ? cardRect.height : 0) || sample?.height || 120
    const atTop = cardRect
        && cardRect.height > 8
        && cardRect.top >= body.top - 4
        && cardRect.top <= body.top + 36
        && cardRect.bottom > body.top
    return {
        x: atTop ? cardRect.left : body.left + 8,
        y: atTop ? cardRect.top : body.top + 8,
        width,
        height,
    }
}

function usesFirstCardLane(job) {
    return job?.scene === 'contact' || job?.scene === 'deliver'
}

function alignToFirstCards(job) {
    if (!usesFirstCardLane(job)) return
    const slot = firstLeadSlot(stageEl(job.from?.id), job.sourceRect)
    if (!slot) return
    job.sourceRect = {
        left: slot.x,
        top: slot.y,
        width: slot.width,
        height: slot.height,
        right: slot.x + slot.width,
        bottom: slot.y + slot.height,
    }
    if (job.parked) {
        job.parked.style.left = `${slot.x}px`
        job.parked.style.top = `${slot.y}px`
        job.parked.style.width = `${Math.max(180, slot.width)}px`
    }
}

function landOnFirstCard(job, dest) {
    if (!usesFirstCardLane(job) || !dest?.rect) return dest
    const slot = firstLeadSlot(dest.column, dest.rect)
    if (!slot) return dest
    return {
        ...dest,
        rect: {
            ...dest.rect,
            left: slot.x,
            top: slot.y,
            width: slot.width || dest.rect.width,
            height: slot.height || dest.rect.height,
            right: slot.x + (slot.width || dest.rect.width),
            bottom: slot.y + (slot.height || dest.rect.height),
        },
    }
}

function measureDest(job) {
    const column = stageEl(job.to?.id) || (job.scene === 'convert' ? stageByKind('converted') : null)
    const card = cardEl(job.leadId)
    const cardStageId = card?.closest?.('[data-stage-id]')?.getAttribute?.('data-stage-id')
    const cardInDest = column && cardStageId != null && String(cardStageId) === String(job.to?.id)
    const rect = cardInDest ? card.getBoundingClientRect() : null
    const colRect = columnBodyRect(column) || column?.getBoundingClientRect?.()
    if (rect && rect.width > 8 && rect.height > 8) return { column, card, rect }
    if (colRect && colRect.width > 8) {
        return {
            column,
            card,
            rect: {
                left: colRect.left + 12,
                top: colRect.top + 58,
                width: Math.min(240, colRect.width - 24),
                height: 86,
                right: colRect.left + colRect.width - 12,
                bottom: colRect.top + 144,
            },
        }
    }
    return { column, card, rect: null }
}

function jobIsCurrent(job) {
    return !!job && !job.dead && !job.finished && jobs.get(job.leadId) === job
}

// ---- Motion log: every step, why it stopped, and where it lagged. ----
// On by default; turn off in the browser console with localStorage.lead_motion_log = '0'.
const LAG_MS = { start: 400, scroll: 700, total: 6000 }

function motionLogOn() {
    try {
        return localStorage.getItem('lead_motion_log') !== '0'
    } catch (_) {
        return true
    }
}

function msSince(t) {
    return typeof t === 'number' ? Math.round(performance.now() - t) : null
}

function motionLog(level, message, job, extra) {
    if (!motionLogOn()) return
    try {
        const info = {
            lead: job?.leadId ?? extra?.leadId,
            scene: job?.scene,
            move: job ? `${job.from?.name || job.from?.id || '?'} → ${job.to?.name || job.to?.id || '?'}` : undefined,
            sinceArm: job ? `${msSince(job.armedAt)}ms` : undefined,
            ...(extra || {}),
        }
        const fn = level === 'warn' ? console.warn : console.info
        fn(`[lead-motion] ${message}`, info)
    } catch (_) { /* logging must never break the motion */ }
}

function abortJob(job, { release = true } = {}) {
    if (!job || job.dead) return
    if (!job.finished) {
        motionLog('warn', 'STOPPED: cancelled before it finished', job)
        if (motionLogOn()) console.trace('[lead-motion] cancelled by')
    }
    job.dead = true
    clearJobTimer(job)
    job.wheelSpin?.forEach((tween) => tween.kill())
    job.scrollTweens?.forEach((tween) => tween.kill())
    job.timeline?.kill()
    job.cargoTween?.kill()
    job.particles?.stop()
    job.parked?.remove()
    job.layer?.remove()
    if (release) {
        if (jobs.get(job.leadId) === job) jobs.delete(job.leadId)
        if (!jobs.has(job.leadId)) releaseHold(job.leadId)
    }
    destroyRootIfIdle()
}

function finishJob(job) {
    if (!job || job.finished) return
    if (job.playStartedAt != null) {
        const total = msSince(job.playStartedAt)
        if (job.animating) {
            motionLog(total > LAG_MS.total ? 'warn' : 'info', total > LAG_MS.total ? `LAG: animation took ${total}ms` : `done in ${total}ms`, job)
        }
    }
    job.finished = true
    clearJobTimer(job)
    job.wheelSpin?.forEach((tween) => tween.kill())
    job.scrollTweens?.forEach((tween) => tween.kill())
    job.cargoTween?.kill()
    job.parked?.remove()
    job.particles?.stop()
    job.layer?.remove()
    const current = jobs.get(job.leadId)
    const superseded = job.dead || (current && current !== job)
    const resolve = job.resolve
    job.resolve = null
    if (current === job) jobs.delete(job.leadId)
    if (!jobs.has(job.leadId)) releaseHold(job.leadId)
    if (superseded) {
        resolve?.(false)
        destroyRootIfIdle()
        return
    }
    resolve?.()
    destroyRootIfIdle()
}

function sourcePoint(job) {
    const rect = job.sourceRect
    if (rect && rect.width) {
        return {
            x: rect.left,
            y: rect.top,
            width: rect.width,
            height: rect.height,
        }
    }
    const board = boardScroller()?.getBoundingClientRect()
    return {
        x: (board?.left || 80) + 12,
        y: (board?.top || 120) + 80,
        width: 200,
        height: 86,
    }
}

function spawnGhost(job, parent) {
    const from = sourcePoint(job)
    const ghost = document.createElement('div')
    ghost.className = 'lsm-ghost'
    ghost.innerHTML = cardHtml(job.leadName, job.meta)
    ghost.style.left = `${from.x}px`
    ghost.style.top = `${from.y}px`
    ghost.style.width = `${Math.max(180, from.width)}px`
    parent.appendChild(ghost)
    return ghost
}

function bezier(t, a, b, c) {
    const u = 1 - t
    return (u * u * a) + (2 * u * t * b) + (t * t * c)
}

function playArrival(job, dest) {
    const parent = job.layer
    const bumpy = job.variant === 'no_answer'
    const ghost = spawnGhost(job, parent)
    const from = sourcePoint(job)
    const board = visibleBoardRect()
    const body = columnBodyRect(dest.column)
    const bodyTop = body?.top ?? dest.rect.top
    const bodyBottom = body?.bottom ?? (dest.rect.top + 320)
    const cardW = 206
    const srcX = from.x + Math.min(from.width, 220) / 2
    const srcY = from.y
    const landX = dest.rect.left + (Math.min(dest.rect.width, 230) - cardW) / 2
    const landY = Math.min(Math.max(dest.rect.top + 8, bodyTop + 12), bodyBottom - 150)
    const rideY = Math.max(bodyTop + 4, Math.min(bodyBottom - 170, srcY + ROOF_CARD_H - ROOF.y))
    const approach = { x: Math.max(4, board.left - 280), y: rideY }
    const pickup = { x: srcX - ROOF.x, y: rideY }
    const cruiseEnd = { x: landX + cardW / 2 - ROOF.x, y: rideY }
    const away = { x: cruiseEnd.x + 170, y: rideY }

    const haul = document.createElement('div')
    haul.className = 'lsm-haul'
    haul.innerHTML = `<div class="lsm-trail"></div>${carMarkup()}<div class="lsm-roof"><div class="lsm-cargo">${cardHtml(job.leadName, bumpy ? 'No answer · rough ride' : 'Answered · on board')}</div></div>`
    haul.style.opacity = '0'
    parent.appendChild(haul)
    job.wheelSpin = spinWheels(haul)
    const trail = haul.querySelector('.lsm-trail')
    const cargo = haul.querySelector('.lsm-cargo')
    const state = { x: approach.x, y: approach.y, pitch: 0, seat: 12, sway: 0, cargoOp: 0, trail: 0 }
    const render = () => {
        haul.style.transform = `translate(${state.x}px, ${state.y}px) rotate(${state.pitch}deg)`
        trail.style.opacity = String(state.trail)
        trail.style.transform = `scaleX(${0.35 + state.trail})`
        if (cargo.classList.contains('is-free')) return
        cargo.style.top = `${-ROOF_CARD_H + state.seat}px`
        cargo.style.transform = `rotate(${state.sway}deg)`
        cargo.style.opacity = String(state.cargoOp)
    }
    render()

    const ring = document.createElement('div')
    ring.className = 'lsm-ring'
    ring.style.opacity = '0'
    parent.appendChild(ring)
    const dust = document.createElement('div')
    dust.className = 'lsm-dust'
    dust.style.opacity = '0'
    parent.appendChild(dust)

    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    job.parked?.remove()
    if (bumpy) haul.classList.add('is-rough')

    const approachProxy = { t: 0 }
    tl.to(haul, { opacity: 1, duration: 0.14 }, 0)
    tl.to(approachProxy, {
        t: 1,
        duration: 0.42,
        ease: 'power2.out',
        onUpdate: () => {
            const t = approachProxy.t
            state.x = bezier(t, approach.x, (approach.x + pickup.x) / 2, pickup.x)
            state.y = rideY + Math.sin(t * Math.PI) * (bumpy ? 3 : 1)
            state.trail = t * 0.85
            state.pitch = Math.sin(t * Math.PI) * (bumpy ? 1.4 : 0.4)
            render()
        },
    }, 0)
    tl.to(ghost, { y: 10, duration: 0.18, ease: 'power1.in' }, 0.32)
    tl.to(state, {
        seat: 0,
        cargoOp: 1,
        duration: 0.2,
        ease: 'power2.out',
        onUpdate: render,
    }, 0.36)
    tl.to(ghost, { opacity: 0, duration: 0.12 }, 0.46)

    const cruise = { t: 0 }
    tl.to(cruise, {
        t: 1,
        duration: 0.7,
        ease: 'power1.inOut',
        onUpdate: () => {
            const t = cruise.t
            state.x = bezier(t, pickup.x, (pickup.x + cruiseEnd.x) / 2, cruiseEnd.x)
            const bob = Math.sin(t * Math.PI * (bumpy ? 4 : 2))
            state.y = rideY + bob * (bumpy ? 6 : 2)
            state.pitch = bob * (bumpy ? 1.8 : 0.5)
            state.sway = bob * (bumpy ? 5.5 : 1.6)
            state.seat = 0
            state.trail = 1
            render()
        },
    }, 0.56)

    tl.to(state, {
        sway: 0,
        pitch: 0,
        y: rideY,
        trail: 0.4,
        duration: 0.16,
        ease: 'power2.out',
        onUpdate: render,
    }, 1.26)

    tl.add(() => {
        if (cargo.classList.contains('is-free')) return
        const rect = cargo.getBoundingClientRect()
        cargo.classList.add('is-free')
        cargo.style.top = '0px'
        cargo.style.left = '0px'
        cargo.style.transform = ''
        parent.appendChild(cargo)
        gsap.set(cargo, {
            x: rect.left,
            y: rect.top,
            rotation: bumpy ? 6 : 0,
            opacity: 1,
        })
        render()
        job.cargoTween = bumpy
            ? gsap.timeline()
                .to(cargo, { x: landX + 16, y: landY + 10, rotation: 8, duration: 0.1, ease: 'power1.out' })
                .to(cargo, { x: landX - 10, rotation: -6, duration: 0.1 })
                .to(cargo, { x: landX, y: landY, rotation: 0, duration: 0.16, ease: 'bounce.out' })
            : gsap.to(cargo, { x: landX, y: landY, rotation: 0, duration: 0.24, ease: 'power2.out' })
    }, 1.4)

    tl.to(state, {
        x: away.x,
        y: away.y,
        pitch: 0,
        sway: 0,
        trail: 0,
        duration: 0.4,
        ease: 'power2.in',
        onUpdate: render,
    }, 1.48)
    tl.to(haul, { opacity: 0, duration: 0.22 }, 1.7)

    const impact = bumpy ? 1.86 : 1.7
    tl.fromTo(ring, {
        opacity: 0.9,
        x: dest.rect.left - 6,
        y: dest.rect.top - 6,
        width: dest.rect.width + 12,
        height: Math.max(dest.rect.height, 72) + 12,
        scale: 0.92,
    }, { opacity: 0, scale: 1.08, duration: 0.32, ease: 'power2.out' }, impact)
    tl.fromTo(dust, {
        opacity: 0.45,
        x: dest.rect.left + dest.rect.width / 2,
        y: dest.rect.top + Math.min(dest.rect.height, 86),
    }, { opacity: 0, scaleX: 1.8, duration: 0.24 }, impact)
    tl.add(() => revealCard(job), impact)
    tl.to(cargo, { opacity: 0, duration: 0.16 }, impact)
}

function playQualify(job, dest) {
    const parent = job.layer
    const from = sourcePoint(job)
    const ghost = spawnGhost(job, parent)
    const portal = document.createElement('div')
    portal.className = 'lsm-portal'
    portal.style.opacity = '0'
    parent.appendChild(portal)
    const flight = document.createElement('div')
    flight.className = 'lsm-flight'
    flight.innerHTML = cardHtml(job.leadName, 'Qualified')
    flight.style.opacity = '0'
    parent.appendChild(flight)
    const ring = document.createElement('div')
    ring.className = 'lsm-ring'
    ring.style.borderColor = PALETTE.gold
    parent.appendChild(ring)

    const cx = dest.rect.left + dest.rect.width / 2
    const cy = dest.rect.top + dest.rect.height / 2
    job.particles.burst({ x: cx, y: cy }, {
        count: 42,
        radial: true,
        spread: Math.PI * 2,
        speed: 280,
        gravity: 80,
        minSize: 4,
        size: 7,
        fade: 0.7,
        jitter: 12,
        shape: 'star',
        colors: [PALETTE.gold, PALETTE.orange, PALETTE.amber, '#fff4d2', PALETTE.lilac],
    })

    const landX = dest.rect.left + (dest.rect.width - 214) / 2
    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    job.parked?.remove()
    tl.to(ghost, { scale: 0.7, opacity: 0, duration: 0.22, ease: 'power2.in' }, 0)
    tl.fromTo(portal, {
        opacity: 0.95,
        x: cx - 20,
        y: cy - 20,
        width: 40,
        height: 40,
    }, {
        opacity: 0,
        x: cx - 130,
        y: cy - 130,
        width: 260,
        height: 260,
        duration: 0.7,
        ease: 'power2.out',
    }, 0.05)
    tl.fromTo(flight, {
        opacity: 0,
        x: from.x,
        y: from.y,
        scale: 0.55,
        rotation: -8,
    }, {
        opacity: 1,
        x: landX,
        y: dest.rect.top,
        scale: 1,
        rotation: 0,
        duration: 0.62,
        ease: 'power3.out',
    }, 0.16)
    tl.fromTo(ring, {
        opacity: 0.85,
        x: dest.rect.left - 8,
        y: dest.rect.top - 8,
        width: dest.rect.width + 16,
        height: Math.max(dest.rect.height, 70) + 16,
    }, { opacity: 0, duration: 0.4 }, 0.55)
    tl.to(flight, { opacity: 0, duration: 0.2 }, 0.92)
    tl.add(() => revealCard(job), 0.9)
}

function snapshotRealCard(real) {
    const clone = real.cloneNode(true)
    clone.removeAttribute('id')
    clone.removeAttribute('data-lead-id')
    clone.classList.remove('kanban-card--motion-hold')
    clone.querySelectorAll('[id]').forEach((node) => node.removeAttribute('id'))
    clone.style.opacity = '1'
    clone.style.pointerEvents = 'none'
    clone.style.width = '100%'
    clone.style.maxWidth = 'none'
    clone.style.transform = 'none'
    clone.style.margin = '0'
    return clone
}

function movingCard(job, parent, fallbackMeta) {
    const card = document.createElement('div')
    card.className = 'lsm-deliver-card'
    const real = cardEl(job.leadId)
    if (real) {
        card.appendChild(snapshotRealCard(real))
    } else {
        card.innerHTML = cardHtml(job.leadName, fallbackMeta)
    }
    job.movingCardEl = card
    parent.appendChild(card)
    return card
}

/**
 * The motion starts before the server answers, so the flying card is a snapshot of the
 * card as it was in the old stage. Call this once the board has the server's fresh lead
 * to swap the flying card's content for the updated card (the motion keeps going).
 */
export function refreshLeadMotionCard(leadId) {
    const job = jobs.get(Number(leadId))
    if (!jobIsCurrent(job) || !job.movingCardEl) return false
    const real = cardEl(job.leadId)
    if (!real) return false
    job.movingCardEl.replaceChildren(snapshotRealCard(real))
    motionLog('info', 'flying card refreshed with the server data', job)
    return true
}

function placeRealCard(job) {
    if (!jobIsCurrent(job)) return
    releaseHold(job.leadId)
}

function sanitizeAssignee(person) {
    const name = String(person?.name || '').trim()
    if (!name) return null
    const raw = person.avatar || person.photo_url || ''
    const avatar = raw
        ? (normalizePublicStorageUrl(raw) || String(raw))
        : (normalizePublicStorageUrl('users/user.png') || '')
    return { name, avatar }
}

function assigneeFromCard(id) {
    const card = cardEl(id)
    const name = card?.querySelector('.responsible-info .info-value')?.textContent?.trim() || ''
    if (!name) return null
    const avatar = card.querySelector('.responsible-info img')?.getAttribute('src') || ''
    return sanitizeAssignee({ name, avatar })
}

const CRM_AVATAR_FALLBACK = '/assets/images/user.png'

function bindAvatar(img, raw) {
    const primary = raw ? (normalizePublicStorageUrl(raw) || String(raw)) : ''
    const storageFallback = normalizePublicStorageUrl('users/user.png') || ''
    const chain = [primary, storageFallback, CRM_AVATAR_FALLBACK].filter((url, index, all) => url && all.indexOf(url) === index)
    let step = 0
    img.alt = ''
    img.src = chain[0] || CRM_AVATAR_FALLBACK
    img.addEventListener('error', () => {
        step += 1
        if (chain[step]) img.src = chain[step]
        else img.onerror = null
    })
}

function standingFigure(extraClass) {
    const el = document.createElement('div')
    el.className = extraClass ? `lsm-agent ${extraClass}` : 'lsm-agent'
    el.innerHTML = agentMarkup()
    return el
}

function attachAssigneeFace(el, person) {
    const photo = document.createElement('img')
    photo.className = 'lsm-agent__face'
    bindAvatar(photo, person?.avatar || '')
    el.classList.add('is-photo')
    const meta = document.createElement('div')
    meta.className = 'lsm-agent__meta'
    const kicker = document.createElement('div')
    kicker.className = 'lsm-agent__kicker'
    kicker.textContent = 'Assigned to'
    const name = document.createElement('div')
    name.className = 'lsm-agent__name'
    name.textContent = person.name
    meta.append(kicker, name)
    el.append(photo, meta)
}

function doorLeafMarkup() {
    return `
        <svg viewBox="0 0 68 48" aria-hidden="true">
            <path d="M2 4 H64 V46 H2 Z" fill="#14110e" stroke="#6a5428" stroke-width="1.2"/>
            <path d="M6 8 H60 V24 H6 Z" fill="#1b2c38" stroke="#c9a227" stroke-width="1"/>
            <path d="M2 4 H64" stroke="#c9a227" stroke-width="1.6"/>
            <rect x="40" y="32" width="12" height="2.6" rx="1" fill="#c9a227"/>
        </svg>`
}

function playDeliver(job, dest) {
    const parent = job.layer
    const from = sourcePoint(job)
    const land = dest.rect
    const board = visibleBoardRect()
    const body = columnBodyRect(dest.column)
    const narrow = window.innerWidth < 760
    const jeepScale = narrow ? 0.46 : 0.54
    const jeepW = 420 * jeepScale
    const jeepH = 160 * jeepScale
    const bodyTop = body?.top ?? board.top
    const bodyBottom = body?.bottom ?? board.bottom
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value))
    const cardW0 = Math.max(150, from.width || land.width || 214)
    const cardH = Math.max(96, from.height || land.height || 180)
    const doorFrac = 224 / 420
    const cabinYFrac = 86 / 160

    let stopX = from.x + Math.min(cardW0, 220) * 0.28 - jeepW * doorFrac
    stopX = clamp(stopX, board.left - jeepW * 0.08, Math.max(board.left, board.right - jeepW - 8))
    const floor = clamp(from.y + Math.min(cardH, 160) * 0.42, bodyTop + jeepH * 0.45, bodyBottom - 6)
    const stopY = floor - jeepH
    const startX = Math.min(stopX - 170, board.left - jeepW * 0.4)
    let dropX = land.left + Math.min(land.width || cardW0, 220) * 0.22 - jeepW * doorFrac
    dropX = clamp(dropX, board.left + 4, board.right - jeepW * 0.2)
    const dropY = clamp(stopY + (land.top - from.y), bodyTop + 4, Math.max(bodyTop + 4, bodyBottom - jeepH - 4))

    const card = movingCard(job, parent, job.to?.name || 'Qualified')
    const jeep = document.createElement('div')
    jeep.className = 'lsm-jeep'
    jeep.innerHTML = carMarkup()
    job.wheelSpin = spinWheels(jeep)
    const hole = document.createElement('div')
    hole.className = 'lsm-cabin-hole'
    const leaf = document.createElement('div')
    leaf.className = 'lsm-door-leaf'
    leaf.innerHTML = doorLeafMarkup()
    jeep.append(hole, leaf)
    parent.appendChild(jeep)

    const check = document.createElement('div')
    check.className = 'lsm-check'
    check.innerHTML = checkMarkup()
    parent.appendChild(check)

    const cabinAt = (x, y) => ({
        x: x + jeepW * doorFrac - cardW0 * 0.08,
        y: y + jeepH * cabinYFrac,
    })
    const pickup = cabinAt(stopX, stopY)
    const unload = cabinAt(dropX, dropY)

    if (job.parked) gsap.set(job.parked, { opacity: 0 })
    gsap.set(card, {
        x: from.x, y: from.y, width: cardW0, scale: 1, rotation: 0, opacity: 1, transformOrigin: '40% 40%',
    })
    gsap.set(jeep, { x: startX, y: stopY, scale: jeepScale, opacity: 0, transformOrigin: '0 0' })
    gsap.set(leaf, { rotation: 0, opacity: 0, transformOrigin: '100% 8%' })
    gsap.set(hole, { opacity: 0 })
    gsap.set(check, { x: land.left + Math.min(land.width || cardW0, 180) - 18, y: land.top - 8, scale: 0.7, opacity: 0 })

    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    tl.to(jeep, { opacity: 1, duration: 0.1 }, 0)
    tl.to(jeep, { x: stopX, duration: 0.52, ease: 'power2.inOut' }, 0)
    tl.to(hole, { opacity: 1, duration: 0.08 }, 0.52)
    tl.to(leaf, { opacity: 1, duration: 0.06 }, 0.52)
    tl.to(leaf, { rotation: 70, duration: 0.2, ease: 'power2.out' }, 0.56)
    tl.to(card, {
        x: pickup.x,
        y: pickup.y,
        scale: 0.2,
        rotation: -6,
        duration: 0.28,
        ease: 'power2.in',
    }, 0.7)
    tl.to(card, { opacity: 0, duration: 0.08 }, 0.94)
    tl.to(leaf, { rotation: 0, duration: 0.16, ease: 'power2.in' }, 1.02)
    tl.to([leaf, hole], { opacity: 0, duration: 0.08 }, 1.18)
    tl.to(jeep, { x: dropX, y: dropY, duration: 0.55, ease: 'power2.inOut' }, 1.2)
    tl.to(hole, { opacity: 1, duration: 0.06 }, 1.76)
    tl.to(leaf, { opacity: 1, duration: 0.05 }, 1.76)
    tl.to(leaf, { rotation: 70, duration: 0.16, ease: 'power2.out' }, 1.8)
    tl.set(card, { x: unload.x, y: unload.y, scale: 0.2, rotation: 4, opacity: 0 }, 1.8)
    tl.to(card, { opacity: 1, duration: 0.06 }, 1.86)
    tl.to(card, {
        x: land.left,
        y: land.top,
        width: Math.max(140, land.width || cardW0),
        scale: 1,
        rotation: 0,
        duration: 0.32,
        ease: 'power2.out',
    }, 1.9)
    tl.add(() => {
        gsap.set(card, { x: land.left, y: land.top, scale: 1, rotation: 0 })
        placeRealCard(job)
        job.particles?.burst({
            x: land.left + Math.min(land.width || cardW0, 180) * 0.5,
            y: land.top + 18,
        }, {
            count: 5,
            radial: true,
            spread: Math.PI * 2,
            speed: 42,
            gravity: 16,
            minSize: 1.5,
            size: 2,
            fade: 1.5,
            jitter: 4,
            shape: 'star',
            colors: ['#C4A15A', '#ffe0a8'],
        })
    }, 2.24)
    tl.to(card, { opacity: 0, duration: 0.1 }, 2.24)
    tl.fromTo(check, { opacity: 0, scale: 0.6 }, { opacity: 1, scale: 1, duration: 0.14, ease: 'power2.out' }, 2.26)
    tl.to(leaf, { rotation: 0, duration: 0.14, ease: 'power2.in' }, 2.28)
    tl.to([leaf, hole, check], { opacity: 0, duration: 0.12 }, 2.44)
    tl.to(jeep, { x: dropX + Math.min(220, Math.max(90, board.right - dropX)), duration: 0.28, ease: 'power2.in' }, 2.46)
    tl.to(jeep, { opacity: 0, duration: 0.14 }, 2.66)
}

let pushSeq = 0

function createPusher() {
    pushSeq += 1
    const skin = `lsm-call-skin-${pushSeq}`
    const suit = `lsm-call-suit-${pushSeq}`
    const el = document.createElement('div')
    el.className = 'lsm-pusher'
    el.innerHTML = `
        <svg viewBox="0 0 190 204" aria-hidden="true">
            <defs>
                <radialGradient id="${skin}" cx="36%" cy="32%" r="70%">
                    <stop offset="0" stop-color="#f6d3b8"/>
                    <stop offset=".62" stop-color="#e0ad88"/>
                    <stop offset="1" stop-color="#c48462"/>
                </radialGradient>
                <linearGradient id="${suit}" x1="86" y1="70" x2="128" y2="136" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#2a3d78"/>
                    <stop offset=".45" stop-color="#121c40"/>
                    <stop offset="1" stop-color="#01062d"/>
                </linearGradient>
            </defs>
            <ellipse cx="98" cy="196" rx="34" ry="3.6" fill="#01062d" opacity=".16"/>
            <g class="lsm-push-leg lsm-push-leg--back">
                <path d="M86 126 L68 154 L62 176 L50 180 L50 190 L72 190 L76 178 L80 156 L96 128 Z" fill="#0c1738"/>
            </g>
            <g class="lsm-push-body">
                <path d="M86 130 C84 108 92 82 106 72 C118 66 126 72 126 86 L118 132 C110 138 92 138 86 130 Z" fill="url(#${suit})"/>
                <path d="M108 74 L114 98 L122 76 Z" fill="#f7f4ee"/>
                <path d="M110 78 L113 108 L117 78 Z" fill="#FAA300"/>
                <path d="M94 96 l6 1 -1 4.2 -6 -1 z" fill="#FAA300"/>
                <path d="M108 92 C124 94 142 100 156 106 L160 114 L146 118 L104 100 Z" fill="#101a40"/>
                <rect x="146" y="104" width="14" height="7" rx="1.6" fill="#f7f4ee"/>
                <ellipse cx="163" cy="112" rx="8" ry="8.5" fill="#f3d0b4"/>
                <rect x="158" y="103" width="26" height="5.2" rx="2.4" fill="#f0c7a6"/>
                <rect x="158" y="109.2" width="26" height="5.2" rx="2.4" fill="#e8b894"/>
                <rect x="158" y="115.4" width="24" height="5.2" rx="2.4" fill="#e2b08d"/>
                <rect x="158" y="121.2" width="20" height="4.8" rx="2.2" fill="#d7a888"/>
                <g class="lsm-push-phone-arm">
                    <path d="M100 92 L78 70 L88 62 L110 84 Z" fill="#162652"/>
                    <path d="M80 68 L118 36 L128 46 L94 76 Z" fill="#101a40"/>
                    <path d="M132 68 L138 50 L148 52 L140 68 Z" fill="#e2b08d"/>
                    <circle cx="142" cy="44" r="13.5" fill="url(#${skin})"/>
                    <ellipse cx="130" cy="46" rx="3" ry="4.6" fill="#e2b08d"/>
                    <path d="M130 43 c.7 1.8 .7 4 0 5.8" stroke="#c48462" stroke-width=".8" fill="none" stroke-linecap="round"/>
                    <path d="M130 38 C132 22 148 20 156 30 C158 34 156 40 152 40 C150 30 140 28 134 36 Z" fill="#2c211c"/>
                    <path d="M148 39 L156 40.5" stroke="#3a2a22" stroke-width="1.4" stroke-linecap="round" fill="none"/>
                    <ellipse cx="154" cy="45" rx="2.2" ry="1.8" fill="#fbf7f2"/>
                    <circle cx="154.7" cy="45.1" r="1" fill="#2a211c"/>
                    <path d="M158 47 c2.2 1 3.2 3.6 .8 5.4" fill="#e8bc98" stroke="#c48462" stroke-width=".5"/>
                    <ellipse class="lsm-push-mouth" cx="152" cy="53" rx="2.6" ry="1.5" fill="#8d4544"/>
                    <rect class="lsm-push-phone" x="120" y="32" width="18" height="28" rx="3" fill="#14161e" stroke="#FAA300" stroke-width="1.5"/>
                    <rect x="122.3" y="34.6" width="10" height="4.4" rx="1" fill="#FAA300"/>
                    <rect x="124" y="48" width="6.4" height="1.3" rx=".4" fill="#ddd"/>
                    <circle cx="127.2" cy="54.5" r="1" fill="#9a9a9a"/>
                    <ellipse cx="118" cy="40" rx="5.5" ry="4.2" fill="#e8b894"/>
                    <ellipse cx="116" cy="50" rx="5.2" ry="4" fill="#f0c7a6"/>
                </g>
            </g>
            <g class="lsm-push-leg lsm-push-leg--front">
                <path d="M98 126 L116 156 L110 176 L98 180 L98 190 L120 190 L120 180 L114 176 L122 154 L108 128 Z" fill="#1a2748"/>
            </g>
        </svg>`
    return el
}

function playFirstContact(job, dest) {
    const parent = job.layer
    const from = sourcePoint(job)
    const land = dest.rect
    const board = visibleBoardRect()
    const column = columnBodyRect(dest.column)
    const narrow = window.innerWidth < 760
    const scale = narrow ? 0.84 : 1
    const pusherW = 130 * scale
    const pusherH = 140 * scale
    const bodyTop = column?.top ?? board.top
    const bodyBottom = column?.bottom ?? board.bottom
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value))
    const cardW0 = Math.max(150, from.width || land.width || 214)
    const cardH = Math.max(96, from.height || land.height || 180)
    const landW = Math.max(140, land.width || cardW0)
    const pushingLeft = (land.left + landW / 2) < (from.x + cardW0 / 2)
    const shove = pushingLeft ? -1 : 1
    const handFrac = 184 / 190
    const overlap = 14 * scale

    const anchor = (cardX, cardY, cardW) => {
        const y = clamp(
            cardY + Math.min(cardH, 220) * 0.34 - pusherH * 0.48,
            bodyTop + 2,
            Math.max(bodyTop + 2, bodyBottom - pusherH - 2),
        )
        const x = pushingLeft
            ? cardX + cardW - overlap + handFrac * pusherW
            : cardX + overlap - handFrac * pusherW
        return { x, y }
    }

    const pusher = createPusher()
    pusher.style.width = `${pusherW}px`
    pusher.style.height = `${pusherH}px`
    parent.appendChild(pusher)

    const card = movingCard(job, parent, job.to?.name || 'Contacted')
    card.style.zIndex = '5'

    const torso = pusher.querySelector('.lsm-push-body')
    const backLeg = pusher.querySelector('.lsm-push-leg--back')
    const frontLeg = pusher.querySelector('.lsm-push-leg--front')
    const phoneArm = pusher.querySelector('.lsm-push-phone-arm')
    const mouth = pusher.querySelector('.lsm-push-mouth')
    const planted = anchor(from.x, from.y, cardW0)
    const approach = { x: planted.x - shove * 22, y: planted.y }

    if (job.parked) gsap.set(job.parked, { opacity: 0 })
    gsap.set(card, {
        x: from.x,
        y: from.y,
        width: cardW0,
        scale: 1,
        rotation: 0,
        opacity: 1,
        transformOrigin: pushingLeft ? '100% 50%' : '0% 50%',
    })
    gsap.set(pusher, {
        x: approach.x,
        y: approach.y,
        scaleX: pushingLeft ? -1 : 1,
        scaleY: 1,
        opacity: 0,
        transformOrigin: '0% 100%',
    })
    gsap.set(torso, { svgOrigin: '100 132', rotation: 0 })
    gsap.set(backLeg, { svgOrigin: '90 126', rotation: 0 })
    gsap.set(frontLeg, { svgOrigin: '104 126', rotation: 0 })
    gsap.set(phoneArm, { svgOrigin: '100 86', rotation: 0 })
    gsap.set(mouth, { transformOrigin: '50% 50%', scaleY: 1 })

    const lockPusher = () => {
        const spot = anchor(
            Number(gsap.getProperty(card, 'x')),
            Number(gsap.getProperty(card, 'y')),
            parseFloat(gsap.getProperty(card, 'width')) || cardW0,
        )
        gsap.set(pusher, { x: spot.x, y: spot.y })
    }
    const appear = 0.12
    const pushAt = 0.18
    const pushDur = 0.48
    const landAt = pushAt + pushDur

    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    tl.to(pusher, { opacity: 1, x: planted.x, duration: appear, ease: 'power2.out' }, 0)
    tl.to(mouth, { scaleY: 1.7, duration: 0.08, yoyo: true, repeat: 7, ease: 'sine.inOut' }, 0.04)
    tl.to(phoneArm, { rotation: -4, duration: 0.1, yoyo: true, repeat: 5, ease: 'sine.inOut' }, 0.04)
    tl.to(torso, { rotation: 8, duration: 0.14, ease: 'power2.out' }, 0.06)
    tl.to(card, {
        x: land.left,
        y: land.top,
        width: landW,
        duration: pushDur,
        ease: 'power3.out',
        onUpdate: lockPusher,
    }, pushAt)
    tl.to(card, { rotation: shove * 2.2, duration: 0.12, ease: 'power2.out' }, pushAt)
    tl.to(card, { rotation: 0, duration: 0.2, ease: 'power2.inOut' }, pushAt + 0.24)
    tl.to(torso, { rotation: 14, duration: 0.12, yoyo: true, repeat: 1, ease: 'sine.inOut' }, pushAt)
    tl.to(backLeg, { rotation: -6, duration: 0.12, yoyo: true, repeat: 1, ease: 'sine.inOut' }, pushAt)
    tl.to(frontLeg, { rotation: 5, duration: 0.12, yoyo: true, repeat: 1, ease: 'sine.inOut' }, pushAt + 0.04)
    tl.add(() => {
        gsap.set(card, { x: land.left, y: land.top, scale: 1, rotation: 0 })
        placeRealCard(job)
    }, landAt)
    tl.to(card, { opacity: 0, duration: 0.06 }, landAt)
    tl.to(torso, { rotation: -6, duration: 0.16, ease: 'power2.inOut' }, landAt)
    tl.to(pusher, {
        x: () => Number(gsap.getProperty(pusher, 'x')) - shove * 22,
        opacity: 0,
        duration: 0.2,
        ease: 'power2.in',
    }, landAt + 0.02)
}

function playHandover(job, dest) {
    const parent = job.layer
    const from = sourcePoint(job)
    const land = dest.rect
    const board = visibleBoardRect()
    const body = columnBodyRect(dest.column)
    const narrow = window.innerWidth < 760
    const agentScale = narrow ? 0.82 : 0.96
    const agentW = 68 * agentScale
    const agentH = 140 * agentScale
    const bodyTop = body?.top ?? board.top
    const bodyBottom = body?.bottom ?? board.bottom
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value))
    const cardW0 = Math.max(150, from.width || land.width || 214)
    const cardH = Math.max(96, from.height || land.height || 180)
    const landH = Math.max(96, land.height || cardH)
    const person = job.assignee || assigneeFromCard(job.leadId)

    const card = movingCard(job, parent, 'Assigned')
    const agent = standingFigure()
    parent.appendChild(agent)

    let recipient = null
    if (person?.name) {
        recipient = standingFigure('is-receive')
        attachAssigneeFace(recipient, person)
        parent.appendChild(recipient)
    }

    const check = document.createElement('div')
    check.className = 'lsm-check'
    check.innerHTML = checkMarkup()
    parent.appendChild(check)

    const floorY = clamp(from.y + Math.min(cardH, 240) - agentH, bodyTop + 8, bodyBottom - agentH - 4)
    const dispatcher = {
        x: clamp(from.x + 6, board.left + 4, from.x + 28),
        y: floorY,
    }
    const meetX = clamp((from.x + cardW0 * 0.45 + land.left) / 2, board.left + agentW + 8, board.right - agentW * 2 - 24)
    const giver = { x: meetX - 8, y: floorY }
    const receiver = {
        x: clamp(meetX + agentW + 46, giver.x + agentW + 28, board.right - agentW - 6),
        y: floorY,
    }
    const heldScale = 0.26
    const handY = (person) => person.y + agentH * 0.32
    const giverHand = { x: dispatcher.x + agentW * 0.48, y: handY(dispatcher) }
    const carryHand = { x: giver.x + agentW * 0.48, y: handY(giver) }
    const receiveHand = { x: receiver.x - cardW0 * heldScale * 0.78, y: handY(receiver) }

    if (job.parked) gsap.set(job.parked, { opacity: 0 })
    gsap.set(card, {
        x: from.x, y: from.y, width: cardW0, scale: 1, rotation: 0, opacity: 1, transformOrigin: '18% 8%',
    })
    gsap.set(agent, {
        x: dispatcher.x, y: dispatcher.y + 8, scale: agentScale, opacity: 0, rotation: 0, transformOrigin: '50% 100%',
    })
    if (recipient) {
        gsap.set(recipient, {
            x: clamp(land.left + 18, receiver.x + 8, board.right - agentW - 4),
            y: receiver.y,
            scale: agentScale,
            opacity: 0,
            rotation: 0,
            transformOrigin: '50% 100%',
        })
    }
    gsap.set(check, { x: receiver.x + agentW * 0.2, y: receiver.y + 4, scale: 0.6, opacity: 0 })

    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    tl.to(agent, { opacity: 1, y: dispatcher.y, duration: 0.14 }, 0)
    tl.to(agent, { rotation: 14, duration: 0.18, ease: 'power2.inOut' }, 0.14)
    tl.to(card, {
        x: giverHand.x,
        y: giverHand.y,
        scale: heldScale,
        rotation: -11,
        duration: 0.28,
        ease: 'power2.inOut',
    }, 0.26)
    tl.to(agent, { rotation: 4, duration: 0.12 }, 0.5)
    if (recipient) tl.to(recipient, { opacity: 1, x: receiver.x, duration: 0.22, ease: 'power2.out' }, 0.48)
    tl.to(agent, { x: giver.x, y: giver.y, rotation: 8, duration: 0.48, ease: 'power2.inOut' }, 0.62)
    tl.to(card, {
        x: carryHand.x,
        y: carryHand.y,
        rotation: -8,
        duration: 0.48,
        ease: 'power2.inOut',
    }, 0.62)
    tl.to(card, {
        x: recipient ? receiveHand.x : land.left,
        y: recipient ? receiveHand.y : land.top,
        rotation: recipient ? 8 : 0,
        duration: 0.22,
        ease: 'power2.inOut',
    }, 1.14)
    tl.to(agent, { x: giver.x - 16, rotation: 0, duration: 0.18, ease: 'power2.out' }, 1.28)
    if (recipient) tl.to(recipient, { rotation: -6, duration: 0.16, ease: 'power2.out' }, 1.14)
    tl.to(card, {
        x: land.left,
        y: land.top,
        width: Math.max(140, land.width || cardW0),
        scale: 1,
        rotation: 0,
        duration: 0.4,
        ease: 'power2.inOut',
    }, 1.42)
    if (recipient) tl.to(recipient, { x: receiver.x + 8, rotation: 0, duration: 0.36, ease: 'power2.inOut' }, 1.46)
    tl.add(() => {
        gsap.set(card, { x: land.left, y: land.top, scale: 1, rotation: 0 })
        placeRealCard(job)
    }, 1.86)
    tl.to(card, { opacity: 0, duration: 0.12 }, 1.86)
    tl.fromTo(check, { opacity: 0, scale: 0.7 }, { opacity: 1, scale: 1, duration: 0.16, ease: 'power2.out' }, 1.92)
    tl.to(check, { opacity: 0, duration: 0.16 }, 2.24)
    tl.to([agent, recipient].filter(Boolean), { opacity: 0, duration: 0.22 }, 2.28)
}

function playConvert(job, dest) {
    const parent = job.layer
    const from = sourcePoint(job)
    const ghost = spawnGhost(job, parent)
    const flight = document.createElement('div')
    flight.className = 'lsm-flight'
    flight.innerHTML = cardHtml(job.leadName, 'Converted')
    parent.appendChild(flight)
    const seal = document.createElement('div')
    seal.className = 'lsm-seal'
    seal.innerHTML = sealMarkup()
    seal.style.opacity = '0'
    parent.appendChild(seal)
    const ring = document.createElement('div')
    ring.className = 'lsm-ring'
    parent.appendChild(ring)

    const cx = dest.rect.left + dest.rect.width / 2
    const cy = dest.rect.top + Math.min(dest.rect.height, 80) / 2
    const colors = [PALETTE.navy, PALETTE.purple, PALETTE.orange, PALETTE.gold, PALETTE.lilac, '#fff7df', PALETTE.amber]
    const burst = (origin, count, speed, gravity) => {
        job.particles.burst(origin, {
            count,
            radial: false,
            spread: Math.PI * 1.35,
            speed,
            gravity,
            minSize: 4,
            size: 6,
            fade: 0.42,
            jitter: 20,
            colors,
        })
        job.particles.burst(origin, {
            count: Math.round(count * 0.45),
            radial: true,
            spread: Math.PI * 2,
            speed: speed * 0.75,
            gravity: gravity * 0.35,
            minSize: 5,
            size: 6,
            fade: 0.5,
            jitter: 6,
            shape: 'star',
            colors: [PALETTE.gold, PALETTE.amber, '#fff4d2', PALETTE.orange],
        })
    }

    const landX = dest.rect.left + (dest.rect.width - 214) / 2
    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    job.parked?.remove()
    tl.fromTo(flight, {
        x: from.x,
        y: from.y,
        scale: 0.78,
        opacity: 1,
    }, {
        x: landX,
        y: dest.rect.top - 28,
        scale: 1.06,
        duration: 0.42,
        ease: 'power3.in',
    }, 0)
    tl.to(ghost, { opacity: 0, duration: 0.12 }, 0)
    tl.add(() => burst({ x: cx, y: cy }, 78, 520, 680), 0.36)
    tl.to(flight, { y: dest.rect.top, scale: 1, duration: 0.28, ease: 'bounce.out' }, 0.4)
    tl.fromTo(seal, {
        opacity: 0,
        x: cx,
        y: cy - 20,
        scale: 1.6,
        rotation: -18,
    }, {
        opacity: 1,
        scale: 1,
        rotation: 0,
        duration: 0.34,
        ease: 'back.out(1.8)',
    }, 0.48)
    tl.fromTo(ring, {
        opacity: 0.9,
        x: dest.rect.left - 10,
        y: dest.rect.top - 10,
        width: dest.rect.width + 20,
        height: Math.max(dest.rect.height, 78) + 20,
    }, { opacity: 0, scale: 1.06, duration: 0.55 }, 0.42)
    tl.add(() => burst({ x: cx, y: cy - 10 }, 46, 360, 520), 0.95)
    tl.to(seal, { opacity: 0, y: cy - 36, duration: 0.35 }, 1.45)
    tl.to(flight, { opacity: 0, duration: 0.25 }, 1.78)
    tl.add(() => revealCard(job), 1.8)
    tl.set({}, {}, 2.15)
}

function playReject(job, dest) {
    const parent = job.layer
    const from = sourcePoint(job)
    const ghost = spawnGhost(job, parent)
    const flight = document.createElement('div')
    flight.className = 'lsm-flight lsm-reject'
    flight.innerHTML = cardHtml(job.leadName, 'Unqualified')
    flight.style.opacity = '0'
    parent.appendChild(flight)
    const stamp = document.createElement('div')
    stamp.className = 'lsm-stamp'
    stamp.innerHTML = rejectStampMarkup()
    stamp.style.opacity = '0'
    parent.appendChild(stamp)
    const slash = document.createElement('div')
    slash.className = 'lsm-slash'
    slash.style.opacity = '0'
    parent.appendChild(slash)

    const board = visibleBoardRect()
    const columnRect = dest.column?.getBoundingClientRect?.()
    const colLeft = columnRect?.left ?? dest.rect.left
    const colWidth = columnRect?.width || dest.rect.width || 240
    const cardW = 214
    const maxLeft = Math.min(board.right || window.innerWidth, window.innerWidth) - cardW - 16
    const landX = Math.max(8, Math.min(maxLeft, colLeft + Math.max(8, (colWidth - cardW) / 2)))
    const landY = dest.rect.top
    const fromOnScreen = from.x > (board.left || 0) - 20 && from.x < maxLeft
    const startX = fromOnScreen ? from.x : landX
    const startY = fromOnScreen ? from.y : landY
    const riseX = landX
    const riseY = Math.max(landY - 18, (board.top || 0) + 72)
    const stampX = landX + cardW / 2
    const slashX = landX + 22
    const tone = { g: 0 }

    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    job.parked?.remove()
    tl.to(ghost, { opacity: 0, y: -10, duration: 0.16, ease: 'power1.in' }, 0)
    tl.fromTo(flight, {
        opacity: 1,
        x: startX,
        y: startY,
        rotation: 0,
    }, {
        x: riseX,
        y: riseY,
        rotation: -7,
        duration: 0.4,
        ease: 'power2.out',
    }, 0.06)
    tl.fromTo(stamp, {
        opacity: 0,
        x: stampX + 24,
        y: riseY - 36,
        scale: 1.85,
        rotation: -32,
    }, {
        opacity: 1,
        x: stampX,
        y: riseY + 34,
        scale: 1,
        rotation: -14,
        duration: 0.22,
        ease: 'back.out(2.2)',
    }, 0.4)
    tl.fromTo(slash, {
        opacity: 0,
        x: slashX,
        y: riseY + 46,
        scaleX: 0.08,
        rotation: -16,
    }, {
        opacity: 1,
        scaleX: 1,
        duration: 0.16,
        ease: 'power3.out',
    }, 0.46)
    tl.to(tone, {
        g: 1,
        duration: 0.2,
        ease: 'power1.in',
        onUpdate: () => {
            flight.style.filter = `grayscale(${tone.g})`
        },
    }, 0.48)
    tl.to(flight, {
        x: landX,
        y: landY + 6,
        rotation: 8,
        duration: 0.4,
        ease: 'power2.in',
    }, 0.7)
    tl.to(stamp, {
        x: stampX,
        y: landY + 40,
        rotation: 10,
        duration: 0.4,
        ease: 'power2.in',
    }, 0.7)
    tl.to(slash, {
        x: slashX,
        y: landY + 52,
        rotation: -6,
        duration: 0.4,
        ease: 'power2.in',
    }, 0.7)
    tl.add(() => {
        job.particles.burst({
            x: dest.rect.left + dest.rect.width / 2,
            y: dest.rect.top + 28,
        }, {
            count: 16,
            radial: false,
            aim: Math.PI / 2,
            spread: 1.1,
            speed: 70,
            gravity: 980,
            minSize: 3,
            size: 5,
            fade: 1.35,
            jitter: 36,
            shape: 'rect',
            colors: ['#c23b4a', '#9f1239', '#64748b', '#1c1730'],
        })
    }, 1.02)
    tl.to([flight, stamp, slash], { opacity: 0, duration: 0.16 }, 1.06)
    tl.add(() => revealCard(job, 0.24), 1.08)
    tl.set({}, {}, SCENE_DURATION.reject)
}

function playDepart(job, dest) {
    const parent = job.layer
    const from = sourcePoint(job)
    const ghost = spawnGhost(job, parent)
    gsap.set(ghost, { filter: 'grayscale(.35)' })
    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    job.parked?.remove()
    tl.to(ghost, {
        y: 36,
        opacity: 0,
        rotation: job.variant === 'lost' ? -4 : 3,
        duration: 0.45,
        ease: 'power1.in',
    }, 0)
    tl.fromTo(ghost, { x: from.x }, {
        x: dest.rect.left,
        duration: 0.45,
        ease: 'power1.in',
    }, 0)
    tl.add(() => revealCard(job, 0.28), 0.36)
}

function playTravel(job, dest) {
    const parent = job.layer
    const ghost = spawnGhost(job, parent)
    job.parked?.remove()
    const state = Flip.getState(ghost)
    ghost.style.left = `${dest.rect.left}px`
    ghost.style.top = `${dest.rect.top}px`
    ghost.style.width = `${Math.max(160, dest.rect.width)}px`
    const tl = gsap.timeline({ onComplete: () => finishJob(job) })
    job.timeline = tl
    tl.add(Flip.from(state, {
        duration: 0.62,
        ease: 'power2.inOut',
        absolute: true,
    }))
    tl.to(ghost, { opacity: 0, duration: 0.16 }, 0.52)
    tl.add(() => revealCard(job), 0.5)
}

function revealCard(job, duration = 0.22) {
    if (!jobIsCurrent(job)) return
    const card = cardEl(job.leadId)
    releaseHold(job.leadId)
    if (!card || prefersReducedMotion()) return
    try {
        gsap.fromTo(card, { opacity: 0, y: 8, scale: 0.97 }, {
            opacity: 1,
            y: 0,
            scale: 1,
            duration,
            ease: 'power2.out',
            clearProps: 'opacity,transform',
            onInterrupt: () => {
                try { gsap.set(card, { clearProps: 'opacity,transform' }) } catch (_) {}
            },
        })
    } catch (_) {
        card.style.opacity = ''
        card.style.transform = ''
    }
}

async function runJob(job) {
    await new Promise((resolve) => {
        nextFrame(resolve)
    })
    if (!jobIsCurrent(job)) {
        motionLog('warn', 'STOPPED: replaced or cancelled before the first frame', job)
        finishJob(job)
        return
    }
    if (!destinationPlayable(job.to?.id)) {
        motionLog('warn', 'STOPPED: target stage is not shown on this board (or list view)', job)
        finishJob(job)
        return
    }
    alignToFirstCards(job)
    const column = stageEl(job.to?.id) || (job.scene === 'convert' ? stageByKind('converted') : null)
    const card = cardEl(job.leadId)
    if (!column) motionLog('warn', 'target column element not found on the page', job)
    if (!card) motionLog('info', 'lead card not in the DOM yet (will use the column slot)', job)
    const board = boardScroller()
    const scrollBefore = board?.scrollLeft || 0
    const scrollTweens = scrollIntoView(column, card)
    job.scrollTweens = scrollTweens
    const scrollStart = performance.now()
    await waitForScroll(scrollTweens)
    const scrollMs = msSince(scrollStart)
    if (scrollTweens.length) {
        motionLog(scrollMs > LAG_MS.scroll ? 'warn' : 'info', scrollMs > LAG_MS.scroll ? `LAG: scrolling to the column took ${scrollMs}ms` : `scrolled to the column in ${scrollMs}ms`, job)
    }
    const scrollDelta = scrollBefore - (board?.scrollLeft || 0)
    if (scrollDelta && job.sourceRect) {
        job.sourceRect = {
            ...job.sourceRect,
            left: job.sourceRect.left + scrollDelta,
            right: (job.sourceRect.right || job.sourceRect.left) + scrollDelta,
        }
        if (job.parked) job.parked.style.left = `${job.sourceRect.left}px`
    }
    if (!jobIsCurrent(job)) {
        motionLog('warn', 'STOPPED: replaced or cancelled while scrolling', job)
        finishJob(job)
        return
    }
    await new Promise((resolve) => nextFrame(resolve))
    if (!jobIsCurrent(job)) {
        motionLog('warn', 'STOPPED: replaced or cancelled before measuring the target', job)
        finishJob(job)
        return
    }
    const dest = landOnFirstCard(job, measureDest(job))
    if (!dest?.rect || !destinationPlayable(job.to?.id)) {
        motionLog('warn', 'STOPPED: could not measure where the card should land', job, { hasColumn: !!dest?.column, hasCard: !!dest?.card })
        finishJob(job)
        return
    }
    hold(job.leadId)
    const layer = document.createElement('div')
    job.layer = layer
    job.particles = makeParticleField()
    layer.appendChild(job.particles.canvas)
    overlay().appendChild(layer)
    if (!jobIsCurrent(job)) {
        motionLog('warn', 'STOPPED: replaced or cancelled just before animating', job)
        finishJob(job)
        return
    }
    job.animating = true
    motionLog('info', `animating "${job.scene}"`, job, { readyIn: `${msSince(job.playStartedAt)}ms` })
    if (job.scene === 'arrival') playArrival(job, dest)
    else if (job.scene === 'contact') playFirstContact(job, dest)
    else if (job.scene === 'deliver') playDeliver(job, dest)
    else if (job.scene === 'handover') playHandover(job, dest)
    else if (job.scene === 'qualify') playQualify(job, dest)
    else if (job.scene === 'convert') playConvert(job, dest)
    else if (job.scene === 'reject') playReject(job, dest)
    else if (job.scene === 'depart') playDepart(job, dest)
    else playTravel(job, dest)
}

function nextFrame(resolve) {
    requestAnimationFrame(() => requestAnimationFrame(resolve))
}

function createJob(ctx, scene) {
    const id = Number(ctx.leadId)
    const previous = jobs.get(id)
    if (previous) abortJob(previous, { release: false })
    const from = hydrateStage(ctx.from)
    const to = hydrateStage(ctx.to)
    const domStage = readStageEl(cardEl(id)?.closest?.('[data-stage-id]'))
    let sourceRect = ctx.sourceRect || null
    const sameColumn = domStage && from && String(domStage.id) === String(from.id)
    if (!sourceRect && sameColumn) sourceRect = cardEl(id)?.getBoundingClientRect?.() || null
    if (!sourceRect && dragOrigins.has(id)) sourceRect = dragOrigins.get(id)
    dragOrigins.delete(id)

    const job = {
        leadId: id,
        leadName: leadLabel(ctx.leadName, id),
        meta: `${from?.name || 'Stage'} → ${to?.name || 'Stage'}`,
        from,
        to,
        sourceRect,
        scene: scene.scene,
        assignee: sanitizeAssignee(ctx.assignee),
        variant: scene.variant,
        duration: scene.duration,
        gen: (previous?.gen || 0) + 1,
        dead: false,
        finished: false,
        resolve: null,
        armedAt: performance.now(),
    }
    if (previous) motionLog('info', 'replacing an older motion for the same lead', job)
    jobs.set(id, job)
    return job
}

export function armLeadMotion(ctx) {
    if (!ctx?.leadId || !ctx.to) return false
    if (ctx.force === 'stash') {
        const id = Number(ctx.leadId)
        const from = hydrateStage(ctx.from)
        stashes.set(id, {
            rect: cardEl(id)?.getBoundingClientRect?.() || dragOrigins.get(id) || null,
            from,
            leadName: ctx.leadName || leadLabel('', id),
            at: Date.now(),
        })
        return false
    }
    if (prefersReducedMotion()) {
        motionLog('warn', 'NO MOTION: the OS "reduce motion" setting is on', null, { leadId: ctx.leadId })
        return false
    }
    const from = hydrateStage(ctx.from)
    const to = hydrateStage(ctx.to)
    const scene = ctx.force === 'convert'
        ? { scene: 'convert', variant: null, duration: SCENE_DURATION.convert }
        : classifyLeadTransition(from, to, ctx.interactionResult)
    if (!scene) {
        const why = !from || !to ? 'from/to stage unknown' : 'same kind of stage or moving backwards'
        motionLog('warn', `NO MOTION: ${why}`, null, { leadId: ctx.leadId, from, to })
        return false
    }
    // List view and a destination this role does not render have nothing to animate.
    // Leaving the job unarmed keeps the real card in the normal stage update.
    if (!destinationPlayable(to?.id)) {
        motionLog('warn', 'NO MOTION: target stage is not shown on this board (or list view)', null, { leadId: ctx.leadId, to })
        return false
    }
    const job = createJob({ ...ctx, from, to }, scene)
    job.parkedOnly = true
    motionLog('info', `armed "${scene.scene}"`, job)
    return true
}

const moveTokens = new Map()
const supersededStages = new Map()

export function beginLeadMove(leadId) {
    const id = Number(leadId)
    const token = (moveTokens.get(id) || 0) + 1
    moveTokens.set(id, token)
    cancelLeadMotion(id)
    return token
}

export function isLatestLeadMove(leadId, token) {
    return moveTokens.get(Number(leadId)) === token
}

export function rememberSupersededStage(leadId, stageId) {
    if (stageId == null || stageId === '') return
    supersededStages.set(Number(leadId), stageId)
}

export function consumeSupersededStage(leadId) {
    const id = Number(leadId)
    const stageId = supersededStages.get(id) || null
    supersededStages.delete(id)
    return stageId
}

export function playLeadMotion(leadId, extra) {
    const id = Number(leadId)
    const job = jobs.get(id)
    if (!job || job.dead) {
        motionLog('warn', 'NOT PLAYED: no live motion for this lead (never armed, or already cancelled)', null, { leadId: id })
        if (!jobs.has(id)) releaseHold(id)
        return Promise.resolve(false)
    }
    if (extra?.toId != null && String(job.to?.id) !== String(extra.toId)) {
        motionLog('warn', 'NOT PLAYED: the lead was sent to a different stage meanwhile', job, { requestedTo: extra.toId })
        return Promise.resolve(false)
    }
    const person = sanitizeAssignee(extra?.assignee) || job.assignee || assigneeFromCard(id)
    if (person) job.assignee = person
    if (prefersReducedMotion() || !destinationPlayable(job.to?.id)) {
        motionLog('warn', 'NOT PLAYED: reduce-motion on, or target stage not on this board', job)
        finishJob(job)
        return Promise.resolve(false)
    }
    const waited = msSince(job.armedAt)
    motionLog(waited > LAG_MS.start ? 'warn' : 'info', waited > LAG_MS.start ? `LAG: play started ${waited}ms after arming (waiting on the app/server)` : `play (started ${waited}ms after arming)`, job)
    job.playStartedAt = performance.now()
    return new Promise((resolve) => {
        let settled = false
        const settle = (played) => {
            if (settled) return
            settled = true
            resolve(played)
        }
        job.resolve = () => settle(true)
        const limit = Math.min(6000, Math.round(((job.duration || 2) + 1.6) * 1000))
        job.safetyTimer = setTimeout(() => {
            if (!job.finished) {
                motionLog('warn', `STOPPED: safety timeout after ${limit}ms — the animation hung, card landed without it`, job)
                finishJob(job)
            }
            settle(false)
        }, limit)
        runJob(job).catch((error) => {
            motionLog('warn', 'STOPPED: animation threw an error', job, { error: error?.message || String(error) })
            finishJob(job)
            settle(false)
        })
    })
}

export function cancelLeadMotion(leadId) {
    const id = Number(leadId)
    stashes.delete(id)
    const job = jobs.get(id)
    if (!job) {
        releaseHold(id)
        return
    }
    const resolve = job.resolve
    job.resolve = null
    abortJob(job, { release: true })
    resolve?.()
}

export function beginConversionCelebration(ctx) {
    if (!ctx?.leadId || prefersReducedMotion()) return false
    const id = Number(ctx.leadId)
    const el = cardEl(id)
    const current = readStageEl(el?.closest?.('[data-stage-id]'))
    const stash = stashes.get(id)
    if (stash && Date.now() - stash.at > 30000) stashes.delete(id)
    const freshStash = stashes.get(id) || null
    if (current && stageKind(current) === 'converted' && !freshStash) return false
    const from = freshStash?.from || current
    if (from && stageKind(from) === 'converted') {
        stashes.delete(id)
        return false
    }
    const toEl = stageEl(ctx.stageId) || stageByKind('converted')
    const to = readStageEl(toEl) || { id: ctx.stageId, name: 'Converted', order: 6 }
    const sourceRect = (current && stageKind(current) !== 'converted')
        ? el?.getBoundingClientRect?.()
        : (freshStash?.rect || el?.getBoundingClientRect?.() || null)
    stashes.delete(id)
    return armLeadMotion({
        leadId: id,
        leadName: ctx.leadName || freshStash?.leadName,
        from: from || { id: null, name: 'Pipeline', order: 0 },
        to,
        sourceRect,
        force: 'convert',
    })
}

export function destroyLeadMotion() {
    Array.from(jobs.values()).forEach((job) => abortJob(job, { release: true }))
    heldIds.forEach((id) => releaseHold(id))
    stashes.clear()
    dragOrigins.clear()
    destroyRootIfIdle()
}
