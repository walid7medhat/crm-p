<template>
  <Teleport to="#shortcut-dock">
    <button
      ref="floatRef"
      type="button"
      class="sac-root shortcut-item shortcut-item--actions"
      data-tour="next-actions-pill"
      :class="{ 'is-open': open }"
      @click="onPillClick"
    >
      <iconify-icon icon="lucide:list-tree" />
      <span>Next Actions</span>
    </button>
  </Teleport>
  <Teleport to="body">
    <svg v-if="open" class="sac-wires" data-tour="next-actions-tree" aria-hidden="true">
      <path
        v-for="(wire, index) in wires"
        :key="index"
        :d="wire.d"
        class="sac-wire"
        pathLength="1"
        :style="{ animationDelay: wire.delay + 'ms' }"
      />
    </svg>

    <p v-if="open && state === 'error'" class="sac-banner">{{ errorMessage }}</p>
    <p v-else-if="open && state === 'ready' && !categories.length" class="sac-banner">Nothing needs action right now.</p>

    <button
      v-for="(category, index) in (open ? categories : [])"
      :key="category.key"
      :ref="(el) => setNodeRef(el, index)"
      type="button"
      class="sac-node"
      data-tour="next-actions-stage"
      :data-tour-stage="category.key"
      :class="{ 'is-hot': hoveredKey === category.key, 'is-grown': treeReady, 'is-compact': isCompact }"
      :style="nodeStyle(index)"
      @click="toggleCategory(category, $event)"
    >
      <iconify-icon icon="lucide:chevron-right" class="sac-node__chevron" />
      <span class="sac-node__label">{{ category.title }}</span>
      <span class="sac-node__count">{{ displayCount(category.count) }}</span>
    </button>

    <aside
      v-if="open && (hoverList.length || hoverState === 'empty')"
      class="sac-pop"
      data-tour="next-actions-cards"
      :style="popStyle"
    >
      <template v-if="hoverList.length">
        <p class="sac-pop__kicker">{{ hoverList.length }} {{ hoverList.length === 1 ? 'lead' : 'leads' }}</p>
        <p class="sac-pop__task">{{ hoverList[0].title }}</p>
        <div class="sac-leads">
          <article v-for="item in hoverList" :key="item.lead_id || item.id" class="sac-lead">
            <button type="button" class="sac-lead__main" @click="openItem(item)">
              <span class="sac-lead__name">{{ item.card?.name || item.lead_name || 'Lead' }}</span>
              <span v-if="item.card?.reason" class="sac-lead__reason">{{ item.card.reason }}</span>
              <span class="sac-lead__line">{{ item.card?.need || 'Requirements are not saved yet.' }}</span>
              <span class="sac-lead__line">{{ item.card?.budget || 'Budget not saved' }}</span>
              <span class="sac-lead__line">{{ item.card?.note || 'No saved note' }}</span>
            </button>
            <div class="sac-pop__actions">
              <a v-if="phoneHrefFor(item)" :href="phoneHrefFor(item)" aria-label="Call"><iconify-icon icon="lucide:phone" /></a>
              <a v-if="mailHrefFor(item)" :href="mailHrefFor(item)" aria-label="Email"><iconify-icon icon="lucide:mail" /></a>
              <a v-if="whatsappHrefFor(item)" :href="whatsappHrefFor(item)" target="_blank" rel="noopener" aria-label="WhatsApp"><iconify-icon icon="lucide:message-circle" /></a>
              <button type="button" aria-label="Open lead" @click="openItem(item)">
                <iconify-icon icon="lucide:arrow-up-right" />
              </button>
            </div>
          </article>
        </div>
      </template>
      <p v-else class="sac-pop__status">Nothing to do in this stage.</p>
    </aside>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import { clampFloatingTop } from '@/composables/useFloatingDock.js'
import { openLeadView } from '@/composables/useLeadViewModal.js'
import {
  closeSalesActionCenter,
  openSalesActionCenter,
  salesActionAnchor,
  salesActionCenterOpen,
} from '@/composables/useSalesActionCenter.js'
import { isTourWalkthrough } from '@/composables/useNextActionTourPreview.js'

const BATCH = 20

const open = salesActionCenterOpen
const categories = ref([])
const state = ref('ready')
const errorMessage = ref('')
const search = ref('')
const preview = ref(false)
const subjectName = ref('')
const previewUserId = ref('')
const agents = ref([])
const treeReady = ref(false)
const wires = ref([])
const floatRef = ref(null)
const pillTop = ref(null)
const pillDragging = ref(false)
const PILL_KEY = 'sac-pill-top'
let pillPointerId = null
let pillDragStartY = 0
let pillDragStartTop = 0
let pillDragMoved = false
let ignorePillClick = false
const nodeRefs = ref([])
const hoveredKey = ref('')
const hoverList = ref([])
const hoverState = ref('idle')
const popStyle = ref({})
const layoutTick = ref(0)
const lists = ref({})
const WARM_KEY = 'sac-warm-v1'
let searchTimer = null
let ignoreCloseUntil = 0
let summaryRequest = 0
let listsRequest = 0

const isCompact = computed(() => {
  layoutTick.value
  return typeof window !== 'undefined' && window.innerWidth <= 1024
})
const pillStyle = computed(() => {
  layoutTick.value
  if (pillTop.value == null || typeof window === 'undefined') return null
  const height = floatRef.value?.offsetHeight || 44
  const px = clampFloatingTop(pillTop.value * window.innerHeight, height, floatRef.value)
  return { top: `${px}px`, bottom: 'auto' }
})
function phoneHrefFor(item) {
  const digits = String(item?.card?.phone || '').replace(/[^\d+]/g, '')
  return digits ? `tel:${digits}` : ''
}
function mailHrefFor(item) {
  return item?.card?.email ? `mailto:${item.card.email}` : ''
}
function whatsappHrefFor(item) {
  const digits = String(item?.card?.whatsapp || '').replace(/\D/g, '')
  return digits ? `https://wa.me/${digits}` : ''
}

watch(open, (isOpen) => {
  if (!isOpen) {
    treeReady.value = false
    hoveredKey.value = ''
    hoverList.value = []
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
  // Paint cached stages immediately so the click feels instant.
  if (categories.value.length) {
    state.value = 'ready'
    treeReady.value = true
    nextTick(() => drawWires())
  }
  // Refresh counts in the background — never block the open on lead lists.
  loadSummary({ soft: categories.value.length > 0 })
})

function displayCount(count) {
  const n = Number(count) || 0
  return Math.min(n, BATCH)
}

watch(salesActionAnchor, () => {
  if (!open.value) return
  layoutTick.value += 1
  nextTick(() => drawWires())
})

function onTourAction(event) {
  const action = event?.detail?.action
  if (action === 'list-categories') {
    window.dispatchEvent(new CustomEvent('next-action-tour:categories', {
      detail: {
        categories: categories.value.map((row) => ({
          key: row.key,
          title: row.title,
          count: row.count,
        })),
      },
    }))
    return
  }
  if (action === 'open-stage' || action === 'open-first-stage') {
    const index = Number(event?.detail?.index)
    const key = event?.detail?.key
    let category = Number.isFinite(index) ? categories.value[index] : null
    let node = Number.isFinite(index) ? nodeRefs.value[index] : null
    if (!category && key) {
      const found = categories.value.findIndex((row) => row.key === key)
      if (found >= 0) {
        category = categories.value[found]
        node = nodeRefs.value[found]
      }
    }
    if (!category) {
      category = categories.value[0]
      node = nodeRefs.value[0]
    }
    if (!category || !node) return
    // Expand the stage for the tour — never open a lead.
    previewCategory(category, { currentTarget: node })
    return
  }
  if (action === 'close-stage') {
    closeStageCards()
  }
}

onMounted(() => {
  window.addEventListener('resize', onResize)
  window.addEventListener('next-action-tour:action', onTourAction)
  restorePillTop()
  restoreWarmCache()
  prefetchSummary()
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  window.removeEventListener('next-action-tour:action', onTourAction)
  window.removeEventListener('keydown', onKey)
  document.removeEventListener('pointerdown', onPointerDown, true)
  clearTimeout(searchTimer)
})

function onKey(event) {
  if (event.key === 'Escape') closeSalesActionCenter()
}

function onResize() {
  layoutTick.value += 1
  if (pillTop.value != null && !pillDragging.value) rememberPillTop(pillTop.value * window.innerHeight)
  if (open.value) nextTick(() => drawWires())
}

function rememberPillTop(px) {
  const height = floatRef.value?.offsetHeight || 44
  const ratio = clampFloatingTop(px, height, floatRef.value) / window.innerHeight
  pillTop.value = ratio
  try {
    localStorage.setItem(PILL_KEY, String(ratio))
  } catch {
    // Position still applies for this visit if storage is blocked.
  }
}

function restorePillTop() {
  try {
    const ratio = Number(localStorage.getItem(PILL_KEY))
    if (!Number.isFinite(ratio)) return
    pillTop.value = ratio
    requestAnimationFrame(() => {
      if (pillTop.value == null) return
      rememberPillTop(pillTop.value * window.innerHeight)
    })
  } catch {
    pillTop.value = null
  }
}

function onPillPointerDown(event) {
  if (event.button != null && event.button !== 0) return
  const el = event.currentTarget
  ignorePillClick = false
  pillPointerId = event.pointerId
  pillDragStartY = event.clientY
  pillDragStartTop = el.getBoundingClientRect().top
  pillDragMoved = false
  pillDragging.value = true
  el.setPointerCapture?.(event.pointerId)
}

function onPillPointerMove(event) {
  if (pillPointerId !== event.pointerId) return
  const next = pillDragStartTop + (event.clientY - pillDragStartY)
  if (Math.abs(event.clientY - pillDragStartY) > 4) pillDragMoved = true
  if (!pillDragMoved) return
  const height = floatRef.value?.offsetHeight || 44
  const y = clampFloatingTop(next, height, floatRef.value)
  pillTop.value = y / window.innerHeight
  const el = floatRef.value
  if (open.value && el) {
    const rect = el.getBoundingClientRect()
    salesActionAnchor.value = {
      left: rect.left,
      top: y,
      right: rect.right,
      bottom: y + height,
      width: rect.width,
      height,
    }
  }
}

function onPillPointerUp(event) {
  if (pillPointerId !== event.pointerId) return
  pillPointerId = null
  pillDragging.value = false
  if (!pillDragMoved || pillTop.value == null) return
  ignorePillClick = true
  rememberPillTop(pillTop.value * window.innerHeight)
}

function onPillClick(event) {
  if (ignorePillClick || pillDragMoved) {
    ignorePillClick = false
    pillDragMoved = false
    return
  }
  openSalesActionCenter(event)
}

function onPointerDown(event) {
  if (Date.now() < ignoreCloseUntil) return
  // Keep Next Actions open while the admin Preview tour is guiding the UI.
  if (isTourWalkthrough.value) return
  const path = typeof event.composedPath === 'function' ? event.composedPath() : [event.target]
  const inside = path.some((node) => node instanceof Element && node.closest('.sac-root, .sac-node, .sac-pop, #shortcut-dock, .shortcut-dock, .sidebar-nav-link, .mobile-more-row, .nat-tour, .nat-announce'))
  if (inside) return
  closeSalesActionCenter()
}

function anchorBox() {
  if (salesActionAnchor.value) return salesActionAnchor.value
  const el = floatRef.value
  if (!el) {
    return { left: window.innerWidth - 170, top: window.innerHeight / 2, right: window.innerWidth - 18, bottom: window.innerHeight / 2 + 44, width: 152, height: 44 }
  }
  const rect = el.getBoundingClientRect()
  return { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height }
}

function treeLayout() {
  layoutTick.value
  const vw = window.innerWidth
  const vh = window.innerHeight
  const anchor = anchorBox()
  const count = Math.max(categories.value.length, 1)
  const compact = vw <= 800
  const inset = 12
  const topSafe = compact ? 64 : 16
  // Leave room under the last stage so the lead card can sit above the dock.
  const bottomSafe = compact ? 110 : 120
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
  let nodeH = compact ? 42 : 46
  let gap = nodeH + (compact ? 8 : 14)
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
  // Always grow the tree downward from the shortcut button.
  const openUp = false
  let start
  if (stacked) {
    start = stackUp ? regionBottom - span - nodeH : regionTop
  } else {
    start = Math.max(regionTop, Math.min(anchor.top, regionBottom - span - nodeH))
  }
  const maxStart = regionBottom - span - nodeH
  start = Math.min(Math.max(regionTop, start), Math.max(regionTop, maxStart))
  const width = Math.max(120, Math.min(stacked ? 440 : 420, stacked ? vw - inset * 2 : sideWidth))
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

function params(extra = {}) {
  const query = { ...extra }
  if (search.value.trim()) query.search = search.value.trim()
  if (previewUserId.value) query.preview_user_id = previewUserId.value
  return query
}

function cacheKey(key) {
  return [key, search.value.trim(), previewUserId.value].join('|')
}

function applyListsPayload(payload) {
  const map = payload && typeof payload === 'object' ? payload : {}
  const next = { ...lists.value }
  for (const [key, rows] of Object.entries(map)) {
    next[cacheKey(key)] = Array.isArray(rows) ? rows : []
  }
  lists.value = next
  writeWarmCache()
}

function applyCenterPayload(data, { keepLists = true } = {}) {
  categories.value = (data.categories || []).filter((row) => Number(row?.count) > 0)
  preview.value = !!data.preview
  subjectName.value = data.subject_name || ''
  if (!keepLists) lists.value = {}
  if (data.lists && typeof data.lists === 'object') applyListsPayload(data.lists)
  else writeWarmCache()
  state.value = 'ready'
}

function warmScope() {
  return [search.value.trim(), previewUserId.value].join('|')
}

function writeWarmCache() {
  try {
    localStorage.setItem(WARM_KEY, JSON.stringify({
      scope: warmScope(),
      categories: categories.value,
      lists: lists.value,
      preview: preview.value,
      subjectName: subjectName.value,
      at: Date.now(),
    }))
  } catch {
    // Cache is optional.
  }
}

function restoreWarmCache() {
  try {
    const raw = localStorage.getItem(WARM_KEY)
    if (!raw) return
    const data = JSON.parse(raw)
    // Keep for this browser session day so a refresh still opens instantly.
    if (!data || data.scope !== warmScope() || !Array.isArray(data.categories)) return
    if (data.at && Date.now() - Number(data.at) > 6 * 60 * 60 * 1000) return
    categories.value = data.categories.filter((row) => Number(row?.count) > 0)
    if (data.lists && typeof data.lists === 'object') lists.value = data.lists
    preview.value = !!data.preview
    subjectName.value = data.subjectName || ''
    if (categories.value.length) state.value = 'ready'
  } catch {
    // Ignore broken cache.
  }
}

async function loadAgents() {
  try {
    const response = await api.get('/sales-action-center/agents')
    agents.value = response.data?.data || []
  } catch (_) {
    agents.value = []
  }
}

async function prefetchLists() {
  if (!categories.value.length) return
  const requestId = ++listsRequest
  try {
    const response = await api.get('/sales-action-center', { params: params({ with_lists: 1 }) })
    if (requestId !== listsRequest) return
    const data = response.data?.data || {}
    if (data.lists && typeof data.lists === 'object') applyListsPayload(data.lists)
    // Keep stage counts in sync if this heavier call finishes later.
    if (Array.isArray(data.categories) && requestId === listsRequest) {
      categories.value = data.categories.filter((row) => Number(row?.count) > 0)
      writeWarmCache()
      if (open.value) {
        await nextTick()
        drawWires()
      }
    }
  } catch (_) {
    // Hover can still fetch a single stage if needed.
  }
}

async function prefetchSummary() {
  const requestId = ++summaryRequest
  try {
    const response = await api.get('/sales-action-center', { params: params() })
    if (requestId !== summaryRequest) return
    applyCenterPayload(response.data?.data || {})
    prefetchLists()
  } catch (_) {
    // Prefetch is best-effort; a click will try again.
  }
}

async function loadSummary({ soft = false } = {}) {
  const requestId = ++summaryRequest
  const hadCategories = categories.value.length > 0
  if (!hadCategories && !soft) {
    state.value = 'loading'
    errorMessage.value = ''
  }
  try {
    // Fast path: counts only. Lead lists warm in the background.
    const response = await api.get('/sales-action-center', { params: params() })
    if (requestId !== summaryRequest) return
    applyCenterPayload(response.data?.data || {})
    if (open.value) {
      treeReady.value = true
      await nextTick()
      drawWires()
    }
    prefetchLists()
  } catch (error) {
    if (requestId !== summaryRequest) return
    if (!open.value) return
    if (hadCategories) {
      state.value = 'ready'
      treeReady.value = true
      await nextTick()
      drawWires()
      return
    }
    state.value = 'error'
    errorMessage.value = error?.response?.data?.message || "Couldn't load the action center."
  }
}

function setNodeRef(el, index) {
  if (el) nodeRefs.value[index] = el
  else nodeRefs.value[index] = null
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
  wires.value = categories.value.map((_, index) => {
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

async function previewCategory(category, event) {
  hoveredKey.value = category.key
  const node = event.currentTarget
  const key = cacheKey(category.key)
  if (Object.prototype.hasOwnProperty.call(lists.value, key)) {
    hoverList.value = lists.value[key]
    hoverState.value = hoverList.value.length ? 'ready' : 'empty'
    placePop(node)
    await nextTick()
    placePop(node)
    return
  }
  // Keep the card hidden while a rare miss loads — never flash "Loading…".
  hoverList.value = []
  hoverState.value = 'idle'
  try {
    const response = await api.get('/sales-action-center', { params: params({ category: category.key }) })
    const rows = response.data?.data?.recommendations || []
    lists.value = { ...lists.value, [key]: rows }
    if (hoveredKey.value !== category.key) return
    hoverList.value = rows
    hoverState.value = rows.length ? 'ready' : 'empty'
    placePop(node)
    await nextTick()
    placePop(node)
  } catch (_) {
    if (hoveredKey.value === category.key) hoverState.value = 'empty'
  }
}

function obstacleBoxes() {
  return [...document.querySelectorAll('.sac-root, .edge-pill, .chat-floating-btn')]
    .filter((el) => el.getClientRects().length)
    .map((el) => el.getBoundingClientRect())
}

function placePop(node) {
  if (!node) return
  const box = node.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const margin = 12
  const gap = 12
  const preferredW = 280
  const spaceLeft = box.left - margin
  const spaceRight = vw - box.right - margin
  let left
  let width
  let top = box.top
  let maxHeight = Math.min(Math.round(vh * 0.68), vh - margin * 2)

  if (spaceLeft >= 200 || spaceRight >= 200) {
    if (spaceLeft >= spaceRight) {
      width = Math.min(preferredW, spaceLeft - gap)
      left = box.left - gap - width
    } else {
      width = Math.min(preferredW, spaceRight - gap)
      left = box.right + gap
    }
    // Prefer opening downward; only shrink height if the card would leave the screen.
    if (top + maxHeight > vh - margin) {
      maxHeight = Math.max(160, vh - margin - top)
    }
  } else {
    width = Math.min(preferredW, Math.max(160, vw - margin * 2))
    left = Math.max(margin, Math.min(box.left, vw - width - margin))
    const below = vh - box.bottom - margin
    // Default: open the lead card downward from the stage.
    top = box.bottom + gap
    maxHeight = Math.min(maxHeight, Math.max(160, below - gap))
  }

  if (top < margin) top = margin
  if (top + maxHeight > vh - margin) {
    maxHeight = Math.max(160, vh - margin - top)
  }

  for (const item of obstacleBoxes()) {
    const hitsX = left + width > item.left && left < item.right
    const hitsY = top + maxHeight > item.top && top < item.bottom
    if (!hitsX || !hitsY) continue
    if (item.left - gap - left >= 160) width = item.left - gap - left
    else if (item.top - gap - top >= 140) maxHeight = item.top - gap - top
  }

  popStyle.value = {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
    width: `${Math.round(width)}px`,
    maxHeight: `${Math.round(maxHeight)}px`,
  }
}

function closeStageCards() {
  hoveredKey.value = ''
  hoverList.value = []
  hoverState.value = 'idle'
}

function toggleCategory(category, event) {
  // Click the open stage again to close its cards, then pick another stage.
  if (hoveredKey.value === category.key) {
    closeStageCards()
    return
  }
  previewCategory(category, event)
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    lists.value = {}
    loadSummary({ soft: false })
  }, 250)
}

function openItem(item) {
  const leadId = item?.lead_id || item?.target?.lead_id
  if (!leadId) return
  openLeadView(leadId, { id: leadId, lead_name: item.lead_name }, { focus: item.target?.section || null })
  if (window.innerWidth < 900) closeSalesActionCenter()
}
</script>

<style scoped>
.sac-root {
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
  background: #1a1028;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.sac-root.is-open {
  box-shadow: inset 0 0 0 1px rgba(216, 180, 254, 0.8);
}
.sac-root__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #d8b4fe;
  box-shadow: 0 0 10px #e9d5ff;
}
.sac-wires {
  position: fixed;
  inset: 0;
  z-index: 1240;
  width: 100%;
  height: 100%;
  overflow: visible;
  pointer-events: none;
}
.sac-wire {
  fill: none;
  stroke: rgba(176, 150, 214, 0.95);
  stroke-width: 1.75;
  stroke-linecap: round;
  stroke-dasharray: 1;
  stroke-dashoffset: 1;
  animation: sac-draw 0.22s ease forwards;
}
.sac-node {
  position: fixed;
  z-index: 1250;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  gap: 10px;
  max-width: calc(100vw - 24px);
  padding: 0 12px 0 16px;
  border: 1px solid rgba(255, 255, 255, 0.95);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.86);
  color: #1c1730;
  text-align: left;
  box-shadow: 0 10px 30px rgba(92, 70, 130, 0.1);
  backdrop-filter: blur(8px);
  opacity: 0;
  transform-origin: var(--origin, right center);
  transform: translate(var(--from-x), var(--from-y)) scale(0.15);
}
.sac-node.is-grown {
  opacity: 1;
  animation: sac-from-icon 0.2s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}
.sac-node.is-hot {
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.96), #f6e7b2);
  box-shadow: 0 0 0 1px rgba(214, 184, 92, 0.7), 0 12px 28px rgba(180, 140, 40, 0.12);
}
.sac-node__chevron {
  color: #9a8fb0;
  flex: none;
  transition: transform 0.16s ease;
}
.sac-node.is-hot .sac-node__chevron {
  transform: rotate(90deg);
  color: #6b21a8;
}
.sac-node__label {
  flex: 1;
  min-width: 0;
  font-size: 15px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sac-node.is-compact {
  gap: 6px;
  padding: 0 8px 0 12px;
}
.sac-node.is-compact .sac-node__label { font-size: 13px; }
.sac-node.is-compact .sac-node__count {
  width: 22px;
  height: 22px;
  font-size: 11px;
}
.sac-node__count {
  width: 28px;
  height: 28px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #f3eef8;
  color: #3b3348;
  font-size: 12px;
  font-weight: 700;
}
.sac-pop {
  position: fixed;
  z-index: 1500;
  box-sizing: border-box;
  width: min(300px, calc(100vw - 16px));
  max-height: min(72vh, 640px);
  overflow: auto;
  padding: 16px 16px 12px;
  border-radius: 22px;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 18px 50px rgba(70, 50, 110, 0.16);
}
.sac-pop.is-openable { cursor: pointer; }
.sac-leads {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}
.sac-lead {
  padding: 10px 10px 8px;
  border: 1px solid #efeaf5;
  border-radius: 16px;
  background: #fff;
}
.sac-lead__main {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}
.sac-lead__name {
  color: #1c1730;
  font-size: 14px;
  font-weight: 700;
}
.sac-lead__reason {
  color: #6d28d9;
  font-size: 12px;
  font-weight: 700;
}
.sac-lead__line {
  color: #5c5668;
  font-size: 12px;
  line-height: 1.35;
}
.sac-pop__kicker {
  margin: 0 0 8px;
  color: #8b8498;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.sac-pop__task,
.sac-pop__label,
.sac-pop__value,
.sac-pop__status { margin: 0; }
.sac-pop__task {
  margin-bottom: 10px;
  color: #4c1d6e;
  font-size: 13px;
  font-weight: 700;
}
.sac-pop__label {
  margin-top: 8px;
  color: #1c1730;
  font-size: 13px;
  font-weight: 700;
}
.sac-pop__value,
.sac-pop__status {
  color: #5c5668;
  font-size: 13px;
  line-height: 1.4;
}
.sac-pop__actions {
  display: flex;
  gap: 8px;
  margin-top: 14px;
}
.sac-pop__actions a,
.sac-pop__actions button,
.sac-pop__pager button {
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 50%;
  background: #f4f1f8;
  color: #2a2438;
  text-decoration: none;
}
.sac-pop__pager {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
  color: #6d657c;
  font-size: 12px;
}
.sac-tools {
  position: fixed;
  z-index: 1260;
  top: 16px;
  right: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sac-tools__agent,
.sac-tools__search {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.9);
  color: #6d657c;
  font-size: 12px;
  box-shadow: 0 8px 20px rgba(70, 50, 110, 0.08);
}
.sac-tools__agent select,
.sac-tools__search input {
  border: 0;
  background: transparent;
  color: #1c1730;
  font-size: 13px;
  outline: none;
}
.sac-tools__search input { width: 110px; }
.sac-tools__icon {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 50%;
  background: #fff;
  color: #1c1730;
  box-shadow: 0 8px 20px rgba(70, 50, 110, 0.08);
}
.sac-banner {
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
@keyframes sac-from-icon {
  to { opacity: 1; transform: translate(0, 0) scale(1); }
}
@keyframes sac-draw {
  to { stroke-dashoffset: 0; }
}
@media (max-width: 1024px) {
  .sac-tools {
    top: max(8px, env(safe-area-inset-top, 0px));
    right: max(8px, env(safe-area-inset-right, 0px));
    left: max(8px, env(safe-area-inset-left, 0px));
    justify-content: flex-end;
    flex-wrap: wrap;
  }
  .sac-tools__search input { width: 88px; }
  .sac-tools__agent select { max-width: 42vw; }
  .sac-banner {
    top: calc(48px + env(safe-area-inset-top, 0px));
    right: 12px;
    bottom: auto;
    left: 12px;
    max-width: none;
  }
  .sac-pop { max-height: min(68vh, 560px); }
}
@media (prefers-reduced-motion: reduce) {
  .sac-wire,
  .sac-node.is-grown { animation: none; }
  .sac-node { opacity: 1; transform: none; }
  .sac-wire { stroke-dashoffset: 0; }
}
</style>
