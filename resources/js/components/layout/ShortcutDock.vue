<template>
  <div id="shortcut-dock" class="shortcut-dock" :style="dockStyle">
    <button
      type="button"
      class="shortcut-dock__grip"
      aria-label="Move shortcuts up or down"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
    >
      <span></span>
      <span></span>
    </button>
    <slot />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

const KEY = 'shortcut-dock-top'
const top = ref(null)
const dragging = ref(false)
let pointerId = null
let startY = 0
let startTop = 0
let observer = null

const dockStyle = computed(() => {
  if (top.value == null) return null
  return { top: `${top.value}px`, bottom: 'auto' }
})

function limits() {
  const dock = document.getElementById('shortcut-dock')
  const height = dock?.offsetHeight || 220
  const mobile = window.innerWidth <= 800
  const minTop = 8
  const maxTop = Math.max(minTop, window.innerHeight - height - (mobile ? 84 : 12))
  return { minTop, maxTop }
}

function clamp(px) {
  const { minTop, maxTop } = limits()
  return Math.min(maxTop, Math.max(minTop, px))
}

function remember(px) {
  const next = clamp(px)
  top.value = next
  try {
    localStorage.setItem(KEY, String(next / window.innerHeight))
  } catch {
    // The position still applies for this visit.
  }
}

function onPointerDown(event) {
  if (event.button != null && event.button !== 0) return
  const dock = document.getElementById('shortcut-dock')
  pointerId = event.pointerId
  startY = event.clientY
  startTop = dock?.getBoundingClientRect().top || 0
  dragging.value = true
  event.currentTarget.setPointerCapture?.(event.pointerId)
}

function onPointerMove(event) {
  if (pointerId !== event.pointerId) return
  top.value = clamp(startTop + (event.clientY - startY))
}

function onPointerUp(event) {
  if (pointerId !== event.pointerId) return
  pointerId = null
  dragging.value = false
  if (top.value != null) remember(top.value)
}

function onResize() {
  if (top.value == null || dragging.value) return
  remember(top.value)
}

onMounted(() => {
  try {
    const ratio = Number(localStorage.getItem(KEY))
    if (Number.isFinite(ratio)) remember(ratio * window.innerHeight)
  } catch {
    top.value = null
  }
  const dock = document.getElementById('shortcut-dock')
  if (dock && typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(() => {
      if (top.value == null || dragging.value) return
      remember(top.value)
    })
    observer.observe(dock)
  }
  window.addEventListener('resize', onResize)
})

onUnmounted(() => {
  observer?.disconnect()
  window.removeEventListener('resize', onResize)
})
</script>

<style>
.shortcut-dock {
  position: fixed;
  z-index: 1400;
  right: 16px;
  bottom: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 72px;
  padding: 10px 8px 12px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
  box-shadow:
    0 18px 40px rgba(40, 20, 70, 0.18),
    inset 0 1px 0 rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(16px);
  perspective: 900px;
  transform-style: preserve-3d;
  overflow: visible;
}

.shortcut-dock__grip {
  display: grid;
  gap: 3px;
  width: 28px;
  height: 18px;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: grab;
  touch-action: none;
}

.shortcut-dock__grip span {
  display: block;
  width: 16px;
  height: 2px;
  margin: 0 auto;
  border-radius: 99px;
  background: #b7adc4;
}

.shortcut-dock__grip:active { cursor: grabbing; }

.shortcut-dock .shortcut-item--chat { order: 1; }
.shortcut-dock .shortcut-item--actions { order: 2; }
.shortcut-dock .shortcut-item--day { order: 3; }
.shortcut-dock .shortcut-item--message { order: 4; }

.shortcut-dock .sac-root,
.shortcut-dock .edge-pill,
.shortcut-dock .edge-pill--day,
.shortcut-dock .chat-floating-btn {
  position: relative !important;
  top: auto !important;
  right: auto !important;
  bottom: auto !important;
  left: auto !important;
  width: 48px !important;
  height: 48px !important;
  min-width: 48px !important;
  padding: 0 !important;
  border-radius: 50% !important;
  justify-content: center !important;
  gap: 0 !important;
  overflow: visible !important;
  color: #fff !important;
  background: var(--gradient-primary) !important;
  transform-style: preserve-3d;
  transform: translate3d(0, 0, 0) rotateX(12deg);
  box-shadow:
    0 10px 16px rgba(115, 62, 135, 0.28),
    inset 0 -6px 10px rgba(74, 40, 92, 0.28),
    inset 0 6px 8px rgba(255, 255, 255, 0.28);
  transition: transform 0.45s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.45s ease, filter 0.45s ease;
}

.shortcut-dock .chat-floating-btn {
  width: 56px !important;
  height: 56px !important;
  min-width: 56px !important;
}

.shortcut-dock .sac-root::after,
.shortcut-dock .edge-pill::after,
.shortcut-dock .chat-floating-btn::after {
  content: '';
  position: absolute;
  inset: 2px;
  border-radius: 50%;
  background: linear-gradient(115deg, rgba(255, 255, 255, 0.72), transparent 42%, transparent 62%, rgba(255, 255, 255, 0.18));
  transform: translateX(-130%) rotate(18deg);
  pointer-events: none;
  opacity: 0.85;
}

.shortcut-dock:has(.shortcut-item:hover) .shortcut-item {
  transform: translate3d(0, 0, -18px) scale(0.92) rotateX(18deg);
  filter: saturate(0.85);
}

.shortcut-dock .shortcut-item:hover,
.shortcut-dock .shortcut-item:focus-visible {
  transform: translate3d(-18px, -4px, 42px) rotateY(-26deg) rotateX(8deg) scale(1.14) !important;
  filter: none;
  box-shadow:
    0 0 0 1px rgba(212, 175, 55, 0.85),
    18px 22px 28px rgba(115, 62, 135, 0.32),
    inset 0 -8px 12px rgba(74, 40, 92, 0.2),
    inset 0 8px 10px rgba(255, 255, 255, 0.4) !important;
  z-index: 2;
}

.shortcut-dock .shortcut-item:hover::after,
.shortcut-dock .shortcut-item:focus-visible::after {
  animation: shortcut-shine 0.7s ease;
}

.shortcut-dock .sac-root span,
.shortcut-dock .edge-pill span,
.shortcut-dock .chat-floating-btn > span:not(.chat-floating-badge) {
  position: absolute;
  right: calc(100% + 12px);
  top: 50%;
  padding: 7px 12px;
  border-radius: 999px;
  border: 1px solid var(--color-primary-border, #e7dcec);
  background: #fff;
  color: var(--color-primary, #733e87);
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
  white-space: nowrap;
  opacity: 1;
  pointer-events: none;
  transform: translateY(-50%) rotateY(28deg);
  transform-origin: right center;
  box-shadow: 8px 12px 22px rgba(115, 62, 135, 0.14);
  transition: transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.shortcut-dock .shortcut-item:hover span,
.shortcut-dock .shortcut-item:focus-visible span,
.shortcut-dock .chat-floating-btn:hover > span:not(.chat-floating-badge),
.shortcut-dock .chat-floating-btn:focus-visible > span:not(.chat-floating-badge) {
  transform: translateY(-50%) translateX(-8px) rotateY(0deg) scale(1.06);
}

.shortcut-dock .sac-root iconify-icon,
.shortcut-dock .edge-pill iconify-icon,
.shortcut-dock .chat-floating-btn iconify-icon,
.shortcut-dock .chat-floating-btn i {
  position: relative;
  z-index: 1;
  font-size: 22px;
  color: #fff;
  transition: transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.shortcut-dock .shortcut-item:hover iconify-icon,
.shortcut-dock .shortcut-item:hover i {
  transform: translateZ(12px) scale(1.08);
}

@keyframes shortcut-shine {
  from { transform: translateX(-130%) rotate(18deg); }
  to { transform: translateX(130%) rotate(18deg); }
}

@media (max-width: 800px) {
  .shortcut-dock {
    right: 10px;
    bottom: calc(92px + env(safe-area-inset-bottom, 0px));
    width: 58px;
    gap: 8px;
    padding: 8px 6px 10px;
  }

  .shortcut-dock .sac-root,
  .shortcut-dock .edge-pill,
  .shortcut-dock .edge-pill--day,
  .shortcut-dock .chat-floating-btn {
    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;
  }

  .shortcut-dock .chat-floating-btn {
    width: 48px !important;
    height: 48px !important;
    min-width: 48px !important;
  }

  .shortcut-dock .sac-root span,
  .shortcut-dock .edge-pill span,
  .shortcut-dock .chat-floating-btn > span:not(.chat-floating-badge) {
    font-size: 11px;
    padding: 6px 9px;
  }
}
</style>
