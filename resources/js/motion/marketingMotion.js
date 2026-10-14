/**
 * Marketing workspace motion — playful, in the CRM theme colors (brand purple + gold).
 * Presentational only — never decides data. Everything is skipped when the user
 * prefers reduced motion.
 */
import gsap from 'gsap'

export function prefersReducedMotion() {
  return typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
}

/** Theme palette read from the CRM tokens (falls back to the brand defaults). */
function themeColors() {
  const css = getComputedStyle(document.documentElement)
  const v = (name, fallback) => css.getPropertyValue(name).trim() || fallback
  return {
    primary: v('--color-primary', '#733E87'),
    bright: v('--color-primary-bright', '#8f5aa3'),
    muted: v('--color-primary-muted', '#d4c0de'),
    gold: v('--color-primary-glow', '#d4af37'),
    rgb: v('--color-primary-rgb', '115, 62, 135'),
  }
}

/**
 * Which little reaction a stage gets, from its name / done flag.
 * Purely cosmetic — the move itself is already decided by the board.
 */
export function stageMood(stage) {
  if (stage?.is_done) return 'done'
  const name = String(stage?.name || '').toLowerCase()
  if (name.includes('revision')) return 'revision'
  if (name.includes('cancel')) return 'cancelled'
  if (name.includes('pause')) return 'paused'
  if (name.includes('work') || name.includes('progress') || name.includes('post prod')) return 'working'
  return 'default'
}

/** Stages pop in left → right like dominoes, then cards drop in with a tiny bounce. */
export function revealBoard(root) {
  if (!root || prefersReducedMotion()) return
  const cols = root.querySelectorAll('.mk-col')
  const cards = Array.from(root.querySelectorAll('.mk-card')).slice(0, 40)
  const icons = root.querySelectorAll('.mk-col__icon')
  gsap.killTweensOf([...cols, ...cards, ...icons])
  const tl = gsap.timeline({ defaults: { ease: 'back.out(1.7)' } })
  tl.from(cols, { y: 30, rotation: (i) => (i % 2 ? 1.5 : -1.5), opacity: 0, duration: 0.55, stagger: 0.07, clearProps: 'transform,opacity' })
  tl.from(icons, { scale: 0, rotation: -120, duration: 0.5, stagger: 0.07, ease: 'back.out(2.5)', clearProps: 'transform' }, 0.2)
  if (cards.length) {
    tl.from(cards, { y: -16, opacity: 0, scale: 0.94, duration: 0.45, stagger: 0.03, ease: 'back.out(2)', clearProps: 'transform,opacity' }, '-=0.35')
  }
}

/**
 * A card that just landed in a stage reacts to where it went:
 *  done      → happy jump + theme confetti
 *  revision  → "nope" head-shake
 *  cancelled → droops and greys out for a beat
 *  paused    → sleepy sway
 *  working   → quick spin-up wobble
 *  default   → jelly squish
 * Every landing also gets a brand-colored ring pulse, and the stage's own icon pops out
 * of the card as a badge and flies up into the stage header (see flyStageBadge).
 */
export function landCard(el, { mood = 'default', stageIcon = null } = {}) {
  if (el && !prefersReducedMotion()) flyStageBadge(el, stageIcon, mood)
  landCardBody(el, mood)
}

function landCardBody(el, mood) {
  if (!el || prefersReducedMotion()) return
  const c = themeColors()
  gsap.killTweensOf(el)
  gsap.fromTo(el,
    { boxShadow: `0 0 0 0 rgba(${c.rgb}, 0.55)` },
    { boxShadow: `0 0 0 12px rgba(${c.rgb}, 0)`, duration: 0.9, ease: 'power2.out', clearProps: 'boxShadow' })

  const done = { clearProps: 'transform,filter,opacity' }
  switch (mood) {
    case 'done':
      gsap.timeline({ onComplete: () => gsap.set(el, done) })
        .to(el, { y: -18, scaleY: 1.06, scaleX: 0.95, duration: 0.18, ease: 'power2.out' })
        .to(el, { y: 0, scaleY: 0.92, scaleX: 1.06, duration: 0.16, ease: 'power2.in' })
        .to(el, { scaleY: 1, scaleX: 1, duration: 0.45, ease: 'elastic.out(1.2, 0.4)' })
      burst(el)
      break
    case 'revision':
      gsap.timeline({ onComplete: () => gsap.set(el, done) })
        .to(el, { x: -9, rotation: -2, duration: 0.07 })
        .to(el, { x: 9, rotation: 2, duration: 0.07, repeat: 3, yoyo: true })
        .to(el, { x: 0, rotation: 0, duration: 0.2, ease: 'elastic.out(1, 0.4)' })
      break
    case 'cancelled':
      gsap.timeline({ onComplete: () => gsap.set(el, done) })
        .to(el, { rotation: 4, y: 6, transformOrigin: 'left top', filter: 'grayscale(1)', opacity: 0.6, duration: 0.35, ease: 'power2.out' })
        .to(el, { rotation: 0, y: 0, filter: 'grayscale(0)', opacity: 1, duration: 0.5, delay: 0.35, ease: 'back.out(2)' })
      break
    case 'paused':
      gsap.timeline({ onComplete: () => gsap.set(el, done) })
        .to(el, { rotation: -2.5, duration: 0.45, ease: 'sine.inOut' })
        .to(el, { rotation: 2, duration: 0.5, ease: 'sine.inOut' })
        .to(el, { rotation: 0, duration: 0.4, ease: 'sine.inOut' })
      break
    case 'working':
      gsap.timeline({ onComplete: () => gsap.set(el, done) })
        .fromTo(el, { scale: 0.9, rotation: -3 }, { scale: 1.04, rotation: 2, duration: 0.18, ease: 'power2.out' })
        .to(el, { scale: 1, rotation: 0, duration: 0.5, ease: 'elastic.out(1.1, 0.4)' })
      break
    default:
      gsap.timeline({ onComplete: () => gsap.set(el, done) })
        .fromTo(el, { scaleX: 1.08, scaleY: 0.9 }, { scaleX: 0.96, scaleY: 1.05, duration: 0.14, ease: 'power2.out' })
        .to(el, { scaleX: 1, scaleY: 1, duration: 0.55, ease: 'elastic.out(1.2, 0.35)' })
  }
}

/** Picked-up card gets a little "boing". */
export function pickUp(el) {
  if (!el || prefersReducedMotion()) return
  gsap.fromTo(el, { scale: 1 }, { scale: 1.04, rotation: -1.5, duration: 0.15, yoyo: true, repeat: 1, ease: 'power1.out', clearProps: 'transform' })
}

/** Confetti burst in theme colors (purple shades + gold), with a little gravity. */
export function burst(el, count = 22) {
  if (!el || prefersReducedMotion()) return
  const c = themeColors()
  const colors = [c.primary, c.gold, c.bright, c.muted, c.gold]
  const r = el.getBoundingClientRect()
  const cx = r.left + r.width / 2
  const cy = r.top + r.height / 3

  for (let i = 0; i < count; i += 1) {
    const piece = document.createElement('span')
    const w = 5 + Math.random() * 6
    const isStreamer = Math.random() > 0.55
    Object.assign(piece.style, {
      position: 'fixed',
      left: `${cx}px`,
      top: `${cy}px`,
      width: `${w}px`,
      height: `${isStreamer ? w * 0.4 : w}px`,
      borderRadius: isStreamer ? '2px' : '50%',
      background: colors[i % colors.length],
      pointerEvents: 'none',
      zIndex: 3000,
    })
    document.body.appendChild(piece)
    const angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.4
    const power = 70 + Math.random() * 80
    gsap.timeline({ onComplete: () => piece.remove() })
      .to(piece, { x: Math.cos(angle) * power, y: Math.sin(angle) * power, rotation: Math.random() * 540, duration: 0.45, ease: 'power2.out' })
      .to(piece, { y: `+=${80 + Math.random() * 60}`, x: `+=${(Math.random() - 0.5) * 40}`, rotation: '+=180', opacity: 0, duration: 0.8, ease: 'power1.in' })
  }
}

/** Fixed-position helper node appended to <body>. */
function overlayNode(styles) {
  const node = document.createElement('span')
  Object.assign(node.style, { position: 'fixed', pointerEvents: 'none', zIndex: 3001 }, styles)
  document.body.appendChild(node)
  return node
}

/** Expanding brand ring at a point. */
function ripple(x, y, color, size = 34) {
  const ring = overlayNode({
    left: `${x - size / 2}px`,
    top: `${y - size / 2}px`,
    width: `${size}px`,
    height: `${size}px`,
    borderRadius: '50%',
    border: `2px solid ${color}`,
  })
  gsap.fromTo(ring, { scale: 0.6, opacity: 0.9 }, { scale: 2.2, opacity: 0, duration: 0.6, ease: 'power2.out', onComplete: () => ring.remove() })
}

/** Little sparkles radiating from a point. */
function sparkles(x, y, colors, count = 8) {
  for (let i = 0; i < count; i += 1) {
    const s = 3 + Math.random() * 3
    const dot = overlayNode({
      left: `${x - s / 2}px`,
      top: `${y - s / 2}px`,
      width: `${s}px`,
      height: `${s}px`,
      borderRadius: i % 3 ? '50%' : '1px',
      background: colors[i % colors.length],
    })
    const a = (Math.PI * 2 * i) / count
    const d = 22 + Math.random() * 14
    gsap.to(dot, { x: Math.cos(a) * d, y: Math.sin(a) * d, rotation: 180, opacity: 0, scale: 0.4, duration: 0.55, ease: 'power2.out', onComplete: () => dot.remove() })
  }
}

/**
 * The stage's own icon pops out of the card as a round brand badge (ripple + sparkles),
 * hops, then flies in an arc into the stage header icon, which "catches" it with a bounce
 * and a glow. Each stage flavors the flight: Working spins forward, On Revision spins back,
 * Paused floats slowly, Cancelled wobbles, Done arrives with confetti.
 */
function flyStageBadge(card, stageIcon, mood) {
  const c = themeColors()
  const r = card.getBoundingClientRect()
  const sx = r.right - 26
  const sy = r.top + 22
  const size = 34

  const badge = overlayNode({
    left: `${sx - size / 2}px`,
    top: `${sy - size / 2}px`,
    width: `${size}px`,
    height: `${size}px`,
    borderRadius: '50%',
    display: 'grid',
    placeItems: 'center',
    color: '#fff',
    background: getComputedStyle(document.documentElement).getPropertyValue('--gradient-primary').trim() || c.primary,
    boxShadow: `0 10px 22px -8px rgba(${c.rgb}, 0.7), 0 0 0 3px rgba(255,255,255,0.9)`,
    zIndex: 3002,
  })

  // Use the exact icon the stage header shows (already rendered as an svg).
  const svg = stageIcon?.querySelector('svg')?.cloneNode(true)
  if (svg) {
    svg.removeAttribute('class')
    svg.style.cssText = 'width:18px;height:18px;animation:none;transform:none;'
    badge.appendChild(svg)
  } else {
    badge.textContent = '✓'
    badge.style.fontWeight = '700'
  }

  const target = stageIcon?.getBoundingClientRect()
  const visible = target && target.width > 0 && target.bottom > 0 && target.top < window.innerHeight
  const dx = visible ? target.left + target.width / 2 - sx : 0
  const dy = visible ? target.top + target.height / 2 - sy : -60

  const spin = { working: 360, revision: -360, cancelled: 0, paused: 20 }[mood] ?? 180
  const flight = mood === 'paused' ? 0.95 : 0.6

  const tl = gsap.timeline({ onComplete: () => badge.remove() })

  // 1) Pop out of the card
  tl.fromTo(badge, { scale: 0, rotation: -90 }, { scale: 1.2, rotation: 0, duration: 0.3, ease: 'back.out(3)' })
    .call(() => {
      ripple(sx, sy, c.gold)
      sparkles(sx, sy, [c.gold, c.bright, c.muted, '#fff'])
    }, null, 0.05)
    .to(badge, { scale: 1, duration: 0.15 })
    // 2) Little hop (and a wobble for Cancelled)
    .to(badge, { y: -24, duration: 0.2, ease: 'power2.out' })
  if (mood === 'cancelled') {
    tl.to(badge, { rotation: -18, duration: 0.08 })
      .to(badge, { rotation: 18, duration: 0.08, repeat: 3, yoyo: true })
      .to(badge, { rotation: 0, duration: 0.08 })
  }

  if (!visible) {
    // Header is off-screen (scrolled away / collapsed): float up and fade.
    tl.to(badge, { y: dy, opacity: 0, scale: 0.6, duration: 0.5, ease: 'power1.in' })
    return
  }

  // 3) Arc flight into the stage header icon (x eases sideways, y accelerates → curved path)
  tl.addLabel('fly')
    .to(badge, { x: dx, duration: flight, ease: 'sine.inOut' }, 'fly')
    .to(badge, { y: dy, duration: flight, ease: 'back.in(1.4)' }, 'fly')
    .to(badge, { scale: 0.62, rotation: `+=${spin}`, duration: flight, ease: 'power1.in' }, 'fly')
    // 4) The header icon catches it
    .call(() => {
      const tx = target.left + target.width / 2
      const ty = target.top + target.height / 2
      ripple(tx, ty, c.primary, 30)
      gsap.fromTo(stageIcon,
        { scale: 1.45, rotation: -12, boxShadow: `0 0 0 0 rgba(${c.rgb}, 0.45)` },
        { scale: 1, rotation: 0, boxShadow: `0 0 0 10px rgba(${c.rgb}, 0)`, duration: 0.7, ease: 'elastic.out(1.3, 0.4)', clearProps: 'transform,boxShadow' })
      if (mood === 'done') burstAt(tx, ty)
    })
    .to(badge, { scale: 0.1, opacity: 0, duration: 0.15, ease: 'power2.in' })
}

/** Confetti from a screen point (used when the badge reaches the Done header). */
function burstAt(x, y) {
  const fake = { getBoundingClientRect: () => ({ left: x, top: y - 10, width: 0, height: 30 }) }
  burst(fake, 18)
}

/** Animate a number from 0 to its value (KPI tiles), ending with a tiny pop. */
export function countUp(el, to, { decimals = 0, duration = 0.9 } = {}) {
  if (!el) return
  const target = Number(to)
  if (!Number.isFinite(target) || prefersReducedMotion()) {
    el.textContent = to
    return
  }
  const state = { v: 0 }
  gsap.to(state, {
    v: target,
    duration,
    ease: 'power2.out',
    onUpdate: () => { el.textContent = state.v.toFixed(decimals) },
    onComplete: () => {
      el.textContent = target.toFixed(decimals)
      gsap.fromTo(el, { scale: 1.18 }, { scale: 1, duration: 0.4, ease: 'back.out(3)', clearProps: 'transform' })
    },
  })
}

/** Stagger elements into view with a small bounce (KPI tiles, chart cards…). */
export function staggerIn(elements, { y = 16, stagger = 0.06 } = {}) {
  if (!elements?.length || prefersReducedMotion()) return
  gsap.from(elements, { y, opacity: 0, scale: 0.97, duration: 0.5, stagger, ease: 'back.out(1.6)', clearProps: 'transform,opacity' })
}
