<template>
  <Teleport to="#shortcut-dock">
    <!-- Only when a client has homes waiting; stays while the panel is open. -->
    <button
      v-if="waitingCount > 0 || open"
      ref="floatRef"
      type="button"
      class="mm-root shortcut-item shortcut-item--matches"
      :class="{ 'is-open': open, 'has-waiting': waitingCount > 0 }"
      :aria-expanded="open"
      @click="toggle"
    >
      <iconify-icon icon="lucide:sparkles" />
      <span>Matches</span>
      <b v-if="waitingCount > 0" class="mm-badge">{{ waitingCount > 9 ? '9+' : waitingCount }}</b>
    </button>
  </Teleport>

  <Teleport to="body">
    <svg v-if="open" class="mm-wires" aria-hidden="true">
      <path
        v-for="(wire, index) in wires"
        :key="index"
        :d="wire.d"
        class="mm-wire"
        pathLength="1"
        :style="{ animationDelay: wire.delay + 'ms' }"
      />
    </svg>

    <p v-if="open && state === 'error'" class="mm-banner">{{ errorMessage }}</p>
    <p v-else-if="open && state === 'ready' && !clients.length" class="mm-banner">No leads with matches right now.</p>

    <!-- First tree: leads that have matching homes -->
    <button
      v-for="(client, index) in (open ? clients : [])"
      :key="client.lead.id"
      :ref="(el) => setNodeRef(el, index)"
      type="button"
      class="mm-node"
      :class="{ 'is-hot': hoveredId === client.lead.id, 'is-grown': treeReady, 'is-compact': isCompact }"
      :style="nodeStyle(index)"
      @click="toggleLead(client, $event)"
    >
      <iconify-icon icon="lucide:chevron-right" class="mm-node__chevron" />
      <span class="mm-node__label" :title="client.lead.name">{{ client.lead.name }}</span>
      <span class="mm-node__score">{{ client.best_score }}%</span>
      <span class="mm-node__count">{{ displayCount(client.matches_count) }}</span>
    </button>

    <!-- Second stage: matching properties for the selected lead -->
    <aside
      v-if="open && (homes.length || hoverState === 'empty')"
      ref="popRef"
      class="mm-pop"
      :style="popStyle"
    >
      <template v-if="homes.length">
        <p class="mm-pop__kicker">
          {{ homes.length }} {{ homes.length === 1 ? 'home' : 'homes' }}
          · {{ activeLead?.lead?.name || 'Lead' }}
        </p>
        <div class="mm-homes">
          <article v-for="home in homes" :key="home.id" class="mm-home">
            <button type="button" class="mm-home__main" @click="openHome(home)">
              <span class="mm-home__media">
                <img v-if="home.main_image" :src="home.main_image" alt="" loading="lazy" />
                <iconify-icon v-else icon="lucide:image-off" width="16" height="16" />
                <b v-if="home.score != null" class="mm-home__score">{{ home.score }}%</b>
              </span>
              <span class="mm-home__text">
                <span class="mm-home__name">{{ home.title || 'Property' }}</span>
                <span v-if="home.area" class="mm-home__line">{{ home.area }}</span>
                <span class="mm-home__line">{{ formatPrice(home.price) }}</span>
              </span>
            </button>
            <div class="mm-pop__actions">
              <button type="button" aria-label="Open matching on lead" @click="openHome(home)">
                <iconify-icon icon="lucide:arrow-up-right" />
              </button>
            </div>
          </article>
        </div>
      </template>
      <p v-else class="mm-pop__status">No open homes for this lead.</p>
    </aside>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import { matchesPanelOpen, openMatchesPanel } from '@/composables/useShortcutPanels.js'
import { matchStudioVersion } from '@/composables/useMatchStudio.js'
import { openLeadMatching } from '@/composables/useLeadViewModal.js'

/**
 * Matches in the same Next Actions tree style:
 * 1) Click Matches → lead nodes that have matching homes
 * 2) Click a lead → pop of matching properties for that lead
 * 3) Click a home → open the lead scrolled to matching
 */
const BATCH = 12
const REFRESH_MS = 5 * 60 * 1000

const open = matchesPanelOpen
const floatRef = ref(null)
const popRef = ref(null)
const queue = ref(null)
const state = ref('ready')
const errorMessage = ref('')
const treeReady = ref(false)
const wires = ref([])
const nodeRefs = ref([])
const layoutTick = ref(0)
const hoveredId = ref(null)
const homes = ref([])
const hoverState = ref('idle')
const popStyle = ref({})
const homesCache = new Map()
let refreshTimer = null
let ignoreCloseUntil = 0
let previewReq = 0

const clients = computed(() => (queue.value?.clients || []).slice(0, BATCH))
const waitingCount = computed(() => queue.value?.summary?.clients_waiting || 0)
const activeLead = computed(() => clients.value.find((c) => c.lead.id === hoveredId.value) || null)
const isCompact = computed(() => {
  layoutTick.value
  return typeof window !== 'undefined' && window.innerWidth <= 1024
})

watch(open, (isOpen) => {
  if (!isOpen) {
    treeReady.value = false
    hoveredId.value = null
    homes.value = []
    hoverState.value = 'idle'
    wires.value = []
    window.removeEventListener('keydown', onKey)
    document.removeEventListener('pointerdown', onPointerDown, true)
    return
  }
  window.addEventListener('keydown', onKey)
  ignoreCloseUntil = Date.now() + 500
  setTimeout(() => {
    if (open.value) document.addEventListener('pointerdown', onPointerDown, true)
  }, 0)
  if (clients.value.length) {
    state.value = 'ready'
    treeReady.value = true
    nextTick(() => drawWires())
  }
  load({ soft: clients.value.length > 0 })
})

watch(matchStudioVersion, () => {
  homesCache.clear()
  load({ soft: true })
})

function displayCount(count) {
  const n = Number(count) || 0
  return Math.min(n, 99)
}

function formatPrice(n) {
  if (n == null || n === '') return '—'
  return `AED ${Number(n).toLocaleString('en-US')}`
}

function toggle() {
  if (open.value) close()
  else openMatchesPanel()
}

function close() {
  matchesPanelOpen.value = false
}

function onKey(event) {
  if (event.key === 'Escape') close()
}

function onPointerDown(event) {
  if (Date.now() < ignoreCloseUntil) return
  const path = typeof event.composedPath === 'function' ? event.composedPath() : [event.target]
  const inside = path.some((node) => node instanceof Element && node.closest('.mm-root, .mm-node, .mm-pop, #shortcut-dock, .shortcut-dock'))
  if (!inside) close()
}

function onResize() {
  layoutTick.value += 1
  if (!open.value) return
  nextTick(() => {
    drawWires()
    placeOpenPop()
  })
}

function onNotification(event) {
  if (event?.detail?.type !== 'lead_matches') return
  homesCache.clear()
  load({ soft: true })
}

async function load({ soft = false } = {}) {
  if (!soft) state.value = 'loading'
  errorMessage.value = ''
  try {
    const res = await api.get('/lead-matches')
    queue.value = res.data?.data || null
    state.value = 'ready'
    if (open.value) {
      treeReady.value = true
      await nextTick()
      drawWires()
    }
  } catch {
    if (!soft || !clients.value.length) {
      state.value = 'error'
      errorMessage.value = "Couldn't load your matches."
    }
  }
}

function setNodeRef(el, index) {
  if (el) nodeRefs.value[index] = el
  else nodeRefs.value[index] = null
}

function anchorBox() {
  const el = floatRef.value
  if (!el) {
    return {
      left: window.innerWidth - 170,
      top: window.innerHeight / 2,
      right: window.innerWidth - 18,
      bottom: window.innerHeight / 2 + 44,
      width: 152,
      height: 44,
    }
  }
  const rect = el.getBoundingClientRect()
  return { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height }
}

function treeLayout() {
  layoutTick.value
  const vw = window.innerWidth
  const vh = window.innerHeight
  const anchor = anchorBox()
  const count = Math.max(clients.value.length, 1)
  const compact = vw <= 800
  const inset = 12
  const topSafe = compact ? 64 : 16
  const bottomSafe = compact ? 72 : 28
  const toRight = anchor.left < vw * 0.45
  const stem = compact ? 20 : 46
  const sidePos = toRight ? anchor.right + stem : vw - anchor.left + stem
  const sideWidth = vw - sidePos - inset
  const stacked = sideWidth < 200
  const spaceAbove = anchor.top - topSafe
  const spaceBelow = vh - anchor.bottom - (compact ? 92 : bottomSafe)
  const stackUp = spaceAbove >= spaceBelow
  const regionTop = stacked ? (stackUp ? topSafe : anchor.bottom + 12) : topSafe
  const regionBottom = stacked
    ? (stackUp ? Math.max(regionTop + 32, anchor.top - 12) : vh - (compact ? 88 : bottomSafe))
    : vh - bottomSafe
  const availableH = Math.max(32, regionBottom - regionTop)
  let nodeH = compact ? 38 : 44
  let gap = nodeH + 8
  if (count > 1) {
    if ((count - 1) * gap + nodeH > availableH) {
      nodeH = Math.min(nodeH, availableH / count)
      gap = (availableH - nodeH) / (count - 1)
    }
  } else {
    gap = 0
  }
  const span = Math.max(0, (count - 1) * gap)
  const mid = anchor.top + anchor.height / 2
  const openUp = mid > vh * 0.5
  let start
  if (stacked) {
    start = stackUp ? regionBottom - span - nodeH : regionTop
  } else if (openUp) {
    start = mid - span - nodeH / 2
  } else {
    start = mid - nodeH / 2
  }
  const maxStart = regionBottom - span - nodeH
  start = Math.min(Math.max(regionTop, start), Math.max(regionTop, maxStart))
  const width = Math.max(176, Math.min(stacked ? 248 : 248, stacked ? vw - inset * 2 : sideWidth))
  return { vw, vh, anchor, count, compact, stacked, stackUp, openUp, toRight, width, sidePos, nodeH, gap, start, inset, mid }
}

function nodeStyle(index) {
  const layout = treeLayout()
  const top = layout.start + index * layout.gap
  const fromY = `${layout.mid - (top + layout.nodeH / 2)}px`
  const style = {
    top: `${top}px`,
    width: `${layout.width}px`,
    height: `${layout.nodeH}px`,
    animationDelay: `${index * 18}ms`,
    '--from-y': fromY,
  }
  if (layout.stacked) {
    style.left = `${layout.inset}px`
    style['--origin'] = 'center top'
    style['--from-x'] = '0px'
    style['--from-y'] = '-18px'
    return style
  }
  if (layout.toRight) {
    style.left = `${Math.min(layout.sidePos, layout.vw - layout.width - layout.inset)}px`
    style['--origin'] = 'left center'
    style['--from-x'] = '-48px'
    return style
  }
  style.right = `${Math.max(layout.inset, layout.sidePos)}px`
  style['--origin'] = 'right center'
  style['--from-x'] = '48px'
  return style
}

function drawWires() {
  const layout = treeLayout()
  const anchor = layout.anchor
  let x1 = layout.toRight ? anchor.right : anchor.left
  let y1 = anchor.top + anchor.height / 2
  if (layout.stacked) {
    x1 = anchor.left + anchor.width / 2
    y1 = layout.stackUp ? anchor.top : anchor.bottom
  }
  wires.value = clients.value.map((_, index) => {
    const top = layout.start + index * layout.gap
    let x2 = layout.toRight ? layout.sidePos : layout.vw - layout.sidePos
    let y2 = top + layout.nodeH / 2
    if (layout.stacked) {
      x2 = layout.inset + layout.width / 2
      y2 = layout.stackUp ? top + layout.nodeH : top
    } else if (!layout.toRight) {
      x2 = layout.vw - Math.max(layout.inset, layout.sidePos)
    } else {
      x2 = Math.min(layout.sidePos, layout.vw - layout.width - layout.inset)
    }
    const bendX = x1 + (x2 - x1) * 0.55
    const bendY = y1 + (y2 - y1) * 0.2
    const d = layout.stacked
      ? `M ${x1} ${y1} C ${x1} ${bendY}, ${x2} ${y1}, ${x2} ${y2}`
      : `M ${x1} ${y1} C ${bendX} ${y1}, ${bendX} ${y2}, ${x2} ${y2}`
    return { d, delay: index * 18 }
  })
}

function toggleLead(client, event) {
  if (hoveredId.value === client.lead.id) {
    closeLeadHomes()
    return
  }
  previewLead(client, event)
}

function closeLeadHomes() {
  hoveredId.value = null
  homes.value = []
  hoverState.value = 'idle'
}

async function previewLead(client, event) {
  const id = Number(client.lead.id)
  hoveredId.value = id
  const node = event.currentTarget

  if (homesCache.has(id)) {
    homes.value = homesCache.get(id)
    hoverState.value = homes.value.length ? 'ready' : 'empty'
    placePop(node)
    await nextTick()
    placePop(node)
    return
  }

  homes.value = []
  hoverState.value = 'idle'
  const req = ++previewReq
  try {
    const res = await api.get(`/leads/${id}/matches`)
    if (req !== previewReq || hoveredId.value !== id) return
    const rows = (res.data?.data?.matches || []).slice(0, 12).map((row) => ({
      id: row.id,
      listing_id: row.listing?.id,
      main_image: row.listing?.main_image,
      title: row.listing?.title,
      area: row.listing?.area,
      price: row.listing?.price,
      score: row.score,
    })).filter((h) => h.listing_id)
    homesCache.set(id, rows)
    homes.value = rows
    hoverState.value = rows.length ? 'ready' : 'empty'
    placePop(node)
    await nextTick()
    placePop(node)
  } catch {
    if (hoveredId.value === id) hoverState.value = 'empty'
  }
}

function placeOpenPop() {
  const index = clients.value.findIndex((c) => c.lead.id === hoveredId.value)
  const node = index >= 0 ? nodeRefs.value[index] : null
  if (node) placePop(node)
}

function obstacleBoxes() {
  return [...document.querySelectorAll('.mm-root, .sac-root, .edge-pill, .chat-floating-btn')]
    .filter((el) => el.getClientRects().length)
    .map((el) => el.getBoundingClientRect())
}

function placePop(node) {
  if (!node) return
  const box = node.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const margin = 10
  const gap = 8
  const preferredW = 292
  const spaceLeft = box.left - margin
  const spaceRight = vw - box.right - margin
  let left
  let width

  if (spaceLeft >= 180 || spaceRight >= 180) {
    if (spaceLeft >= spaceRight) {
      width = Math.min(preferredW, spaceLeft - gap)
      left = box.left - gap - width
    } else {
      width = Math.min(preferredW, spaceRight - gap)
      left = box.right + gap
    }
  } else {
    width = Math.min(preferredW, Math.max(200, vw - margin * 2))
    left = Math.max(margin, Math.min(box.left, vw - width - margin))
  }

  const beside = spaceLeft >= 180 || spaceRight >= 180
  const maxHeight = Math.min(460, Math.round(vh * 0.58))
  const height = Math.min(popRef.value?.offsetHeight || 180, maxHeight)
  let top = beside ? box.bottom - height : box.top - gap - height
  if (!beside && top < margin) top = box.bottom + gap
  if (top < margin) top = margin
  if (top + height > vh - margin) top = Math.max(margin, vh - margin - height)

  for (const item of obstacleBoxes()) {
    const hitsX = left + width > item.left && left < item.right
    const hitsY = top + height > item.top && top < item.bottom
    if (!hitsX || !hitsY) continue
    if (item.left - gap - left >= 160) width = item.left - gap - left
  }

  popStyle.value = {
    top: `${Math.round(top)}px`,
    left: `${Math.round(Math.max(margin, left))}px`,
    width: `${Math.round(width)}px`,
    maxHeight: `${Math.round(maxHeight)}px`,
  }
}

function openHome(home) {
  const lead = activeLead.value
  if (!lead?.lead?.id) return
  close()
  openLeadMatching(lead.lead.id, {
    highlightListingId: home.listing_id || null,
    leadData: { id: lead.lead.id, lead_name: lead.lead.name },
  })
}

onMounted(() => {
  load({ soft: false })
  refreshTimer = window.setInterval(() => {
    if (!document.hidden) load({ soft: true })
  }, REFRESH_MS)
  window.addEventListener('resize', onResize)
  window.addEventListener('app-notification', onNotification)
})

onUnmounted(() => {
  window.clearInterval(refreshTimer)
  window.removeEventListener('resize', onResize)
  window.removeEventListener('app-notification', onNotification)
  window.removeEventListener('keydown', onKey)
  document.removeEventListener('pointerdown', onPointerDown, true)
})
</script>

<style scoped>
.mm-root {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  gap: 8px;
  width: 100%;
  height: 36px;
  padding: 0 12px;
  border: 0;
  border-radius: 999px;
  background: var(--gradient-primary);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.mm-root.is-open {
  box-shadow: inset 0 0 0 1px rgba(var(--color-primary-rgb, 115, 62, 135), 0.55);
}
.mm-root.has-waiting {
  animation: mm-glow 2.6s ease-in-out infinite;
}
@keyframes mm-glow {
  0%, 100% { outline: 2px solid rgba(var(--color-primary-rgb, 115, 62, 135), 0); outline-offset: 2px; }
  50% { outline: 2px solid rgba(var(--color-primary-rgb, 115, 62, 135), 0.85); outline-offset: 4px; }
}
.mm-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  z-index: 2;
  min-width: 19px;
  height: 19px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--color-primary, #733e87);
  color: #fff;
  font-size: 10.5px;
  font-weight: 800;
  line-height: 19px;
  text-align: center;
  box-shadow: 0 0 0 2px #fff;
}

.mm-wires {
  position: fixed;
  inset: 0;
  z-index: 1240;
  width: 100%;
  height: 100%;
  overflow: visible;
  pointer-events: none;
}
.mm-wire {
  fill: none;
  stroke: rgba(176, 150, 214, 0.95);
  stroke-width: 1.75;
  stroke-linecap: round;
  stroke-dasharray: 1;
  stroke-dashoffset: 1;
  animation: mm-draw 0.22s ease forwards;
}

.mm-node {
  position: fixed;
  z-index: 1250;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  gap: 8px;
  max-width: calc(100vw - 24px);
  padding: 0 8px 0 12px;
  border: 1px solid rgba(255, 255, 255, 0.95);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.92);
  color: #1c1730;
  text-align: left;
  box-shadow: 0 10px 24px rgba(92, 70, 130, 0.1);
  backdrop-filter: blur(10px);
  opacity: 0;
  transform-origin: var(--origin, right center);
  transform: translate(var(--from-x), var(--from-y)) scale(0.15);
  cursor: pointer;
}
.mm-node.is-grown {
  opacity: 1;
  animation: mm-from-icon 0.2s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}
.mm-node.is-hot {
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.96), #f6e7b2);
  box-shadow: 0 0 0 1px rgba(214, 184, 92, 0.7), 0 12px 28px rgba(180, 140, 40, 0.12);
}
.mm-node__chevron {
  color: #9a8fb0;
  flex: none;
  font-size: 13px;
  transition: transform 0.16s ease;
}
.mm-node.is-hot .mm-node__chevron {
  transform: rotate(90deg);
  color: #6b21a8;
}
.mm-node__label {
  flex: 1;
  min-width: 0;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  letter-spacing: 0.01em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mm-node.is-compact .mm-node__label { font-size: 10px; }
.mm-node__score {
  flex: none;
  padding: 2px 6px;
  border-radius: 999px;
  background: #24152b;
  color: #ede4ff;
  font-size: 9px;
  font-weight: 800;
}
.mm-node__count {
  width: 22px;
  height: 22px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #f3eef8;
  color: #3b3348;
  font-size: 9px;
  font-weight: 700;
}
.mm-node.is-compact .mm-node__count {
  width: 20px;
  height: 20px;
  font-size: 9px;
}

.mm-pop {
  position: fixed;
  z-index: 1500;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  width: min(292px, calc(100vw - 16px));
  max-height: min(58vh, 460px);
  overflow: hidden;
  padding: 10px 8px 8px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.97);
  box-shadow: 0 16px 36px rgba(70, 50, 110, 0.16);
}
.mm-homes {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 0;
  max-height: min(50vh, 400px);
  margin: 0 -2px;
  padding: 2px 6px 8px 2px;
  overflow: auto;
  scroll-snap-type: y mandatory;
  scroll-padding: 8px;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: rgba(115, 62, 135, 0.45) transparent;
}
.mm-homes::-webkit-scrollbar { width: 4px; }
.mm-homes::-webkit-scrollbar-thumb {
  border-radius: 99px;
  background: rgba(115, 62, 135, 0.4);
}
.mm-home {
  flex: none;
  scroll-snap-align: start;
  padding: 10px 10px 8px;
  border: 1px solid #efe8f6;
  border-radius: 14px;
  background:
    linear-gradient(90deg, rgba(115, 62, 135, 0.9), rgba(115, 62, 135, 0.9)) left / 3px 100% no-repeat,
    linear-gradient(180deg, #fff, #fbf8fd);
  box-shadow: 0 6px 14px rgba(92, 70, 130, 0.06);
}
.mm-home__main {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}
.mm-home__media {
  position: relative;
  width: 56px;
  height: 42px;
  flex: none;
  display: grid;
  place-items: center;
  overflow: hidden;
  border-radius: 10px;
  background: #f1f5f9;
  color: #94a3b8;
}
.mm-home__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.mm-home__score {
  position: absolute;
  top: 3px;
  right: 3px;
  padding: 1px 4px;
  border-radius: 999px;
  background: rgba(33, 150, 83, 0.92);
  color: #fff;
  font-size: 8px;
  font-weight: 800;
}
.mm-home__text {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.mm-home__name,
.mm-home__line {
  max-width: 100%;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.mm-home__name {
  color: #1c1730;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.25;
  -webkit-line-clamp: 2;
}
.mm-home__line {
  color: #6b6578;
  font-size: 10px;
  line-height: 1.3;
  -webkit-line-clamp: 1;
}
.mm-pop__kicker {
  flex: none;
  margin: 0 0 8px;
  padding: 0 4px;
  color: #8b8498;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.04em;
  line-height: 1.2;
  text-transform: uppercase;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mm-pop__status {
  margin: 0;
  color: #5c5668;
  font-size: 13px;
  line-height: 1.4;
}
.mm-pop__actions {
  display: flex;
  gap: 6px;
  margin-top: 8px;
}
.mm-pop__actions button {
  width: 22px;
  height: 22px;
  font-size: 11px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 50%;
  background: #f4f1f8;
  color: #2a2438;
}

.mm-banner {
  position: fixed;
  z-index: 1260;
  top: auto;
  right: 88px;
  bottom: 28px;
  left: auto;
  margin: 0;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  color: #4c1d6e;
  font-size: 13px;
  box-shadow: 0 8px 20px rgba(70, 50, 110, 0.08);
}

@keyframes mm-from-icon {
  to { opacity: 1; transform: translate(0, 0) scale(1); }
}
@keyframes mm-draw {
  to { stroke-dashoffset: 0; }
}

@media (max-width: 1024px) {
  .mm-banner {
    top: calc(48px + env(safe-area-inset-top, 0px));
    right: 12px;
    bottom: auto;
    left: 12px;
    max-width: none;
  }
  .mm-pop { max-height: min(52vh, 420px); }
  .mm-homes { max-height: min(44vh, 360px); }
}
@media (prefers-reduced-motion: reduce) {
  .mm-root.has-waiting { animation: none; }
  .mm-wire,
  .mm-node.is-grown { animation: none; }
  .mm-node { opacity: 1; transform: none; }
  .mm-wire { stroke-dashoffset: 0; }
}
</style>
