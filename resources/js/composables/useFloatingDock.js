const GAP = 12

export function floatingBands(exceptEl) {
  if (typeof document === 'undefined') return []
  return [...document.querySelectorAll('.chat-floating-btn, .edge-pill, .sac-root')]
    .filter((el) => el !== exceptEl && el.getClientRects().length)
    .map((el) => {
      const box = el.getBoundingClientRect()
      return { top: box.top, bottom: box.bottom }
    })
}

export function clampFloatingTop(top, height, exceptEl) {
  const vh = window.innerHeight
  const mobile = window.innerWidth <= 1024
  const minTop = 8
  const bottomReserve = mobile ? 72 : 12
  const maxTop = Math.max(minTop, vh - height - bottomReserve)
  let y = Math.min(maxTop, Math.max(minTop, top))
  const bands = floatingBands(exceptEl)
  for (let pass = 0; pass < bands.length + 1; pass += 1) {
    const hit = bands.find((band) => y + height > band.top - GAP && y < band.bottom + GAP)
    if (!hit) break
    const up = hit.top - GAP - height
    const down = hit.bottom + GAP
    const preferUp = Math.abs(top - up) <= Math.abs(down - top)
    const first = preferUp ? up : down
    const second = preferUp ? down : up
    if (first >= minTop && first <= maxTop) y = first
    else if (second >= minTop && second <= maxTop) y = second
    else break
  }
  return y
}
