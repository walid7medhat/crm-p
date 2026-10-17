<template>
  <Teleport to="body">
    <Transition name="edge-slide">
      <div
        v-if="activePayload || myDayOpen"
        key="dock"
        class="edge-dock"
        :class="{ 'edge-dock--day': myDayOpen && !activePayload, 'edge-dock--stack': activePayload && myDayOpen }"
      >
        <DailyEdgeCard
          v-if="activePayload"
          :date-label="activePayload.date_label"
          :message="activePayload.message"
          dismissible
          @close="closeActive"
        />
        <article v-if="myDayOpen" class="myday" role="dialog" aria-modal="false" aria-label="My Day">
          <header class="myday__top">
            <div class="myday__mark" aria-hidden="true">
              <iconify-icon icon="lucide:sun" />
            </div>
            <div>
              <p class="myday__kicker">My Day</p>
              <p class="myday__date">{{ dayDate || 'Today' }}</p>
            </div>
            <button type="button" class="myday__close" aria-label="Close My Day" @click="closeMyDay">
              <iconify-icon icon="lucide:x" />
            </button>
          </header>
          <p v-if="actionsState === 'loading'" class="myday__note">Loading today's activity…</p>
          <p v-else-if="actionsState === 'error'" class="myday__note">Couldn't load today's activity.</p>
          <p v-else-if="!actions.length" class="myday__note">Your activity will appear here as you work today</p>
          <div v-else class="myday__list">
            <button
              v-for="item in actions"
              :key="item.id"
              type="button"
              class="myday-card"
              @click="openAction(item)"
            >
              <strong class="myday-card__title">{{ item.title }}</strong>
              <span class="myday-card__detail">{{ item.detail }}</span>
              <span class="myday-card__meta">
                <span class="myday-chip">{{ item.time }}</span>
                <span class="myday-chip myday-chip--kind">{{ item.kind }}</span>
              </span>
            </button>
          </div>
        </article>
      </div>
    </Transition>
    <button
      v-if="showPill"
      type="button"
      class="edge-pill"
      @click="reopen"
    >
      <span class="edge-pill__mark" aria-hidden="true"></span>
      Daily Message
    </button>
    <button
      v-if="showDayPill"
      ref="dayPillEl"
      type="button"
      class="edge-pill edge-pill--day"
      :class="{ 'edge-pill--dragging': dayDragging }"
      :style="dayPillStyle"
      @pointerdown="onDayPointerDown"
      @pointermove="onDayPointerMove"
      @pointerup="onDayPointerUp"
      @pointercancel="onDayPointerUp"
      @click="onDayClick"
    >
      <span class="edge-pill__mark" aria-hidden="true"></span>
      My Day
    </button>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { resolveAuthToken } from '@/plugins/axios'
import DailyEdgeCard from './DailyEdgeCard.vue'
import { dismissToday, fetchToday } from '@/services/dailyMotivationApi'
import { closeDailyEdgeTest, openDailyEdgeTest, useDailyEdgeTest } from '@/composables/useDailyEdgePreview.js'
import { openLeadView } from '@/composables/useLeadViewModal.js'
import { openPropertyDetails } from '@/composables/usePropertyDetailsModal.js'

const router = useRouter()

const { testPayload, testOpen } = useDailyEdgeTest()
const production = ref(null)
const productionOpen = ref(false)
const actions = ref([])
const actionsState = ref('ready')
const dayDate = ref('')
const myDayOpen = ref(false)
const sessionReady = ref(false)
const lastClosed = ref(null)
const dayPillEl = ref(null)
const dayPillTop = ref(null)
const dayDragging = ref(false)
const viewportHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0)
let dismissing = false
let dayPointerId = null
let dayDragStartY = 0
let dayDragStartTop = 0
let dayDragMoved = false
let ignoreDayClick = false
const DAY_PILL_KEY = 'edge-pill-day-top'
const DAY_PILL_MARGIN = 12

const dayPillStyle = computed(() => {
  if (dayPillTop.value == null) return null
  const px = clampDayTop(dayPillTop.value * (viewportHeight.value || window.innerHeight))
  return {
    top: `${px}px`,
    bottom: 'auto',
  }
})

const showingTest = computed(() => testOpen.value && !!testPayload.value?.message)
const showingProduction = computed(() => !showingTest.value && productionOpen.value && !!production.value?.message)
const activePayload = computed(() => {
  if (showingTest.value) return testPayload.value
  if (showingProduction.value) return production.value
  return null
})

const showPill = computed(() => {
  if (activePayload.value) return false
  if (testPayload.value?.message && lastClosed.value === 'test') return true
  return !!production.value?.message && !productionOpen.value
})

const showDayPill = computed(() => sessionReady.value && !myDayOpen.value)

async function onDelivered() {
  closeDailyEdgeTest()
  await load()
  if (production.value?.message) productionOpen.value = true
}

function onTestEvent(event) {
  const data = event?.detail || {}
  if (!data.body_en && !data.body_ar) return
  openDailyEdgeTest({
    date_label: production.value?.date_label || '',
    message: {
      number: data.number,
      position: data.number,
      cycle_length: 120,
      body_en: data.body_en,
      body_ar: data.body_ar,
      subtitle_en: data.subtitle_en,
      subtitle_ar: data.subtitle_ar,
      test: true,
    },
  })
}

function onKeydown(event) {
  if (event.key !== 'Escape') return
  const tag = document.activeElement?.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable) return
  if (myDayOpen.value) {
    closeMyDay()
    return
  }
  if (!activePayload.value) return
  closeActive()
}

async function loadActions() {
  if (!resolveAuthToken()) {
    actionsState.value = 'error'
    return
  }
  actionsState.value = 'loading'
  try {
    const response = await api.get('/dashboard/my-day')
    const list = response.data?.data?.actions
    actions.value = Array.isArray(list) ? list : []
    dayDate.value = response.data?.data?.date || ''
    actionsState.value = 'ready'
  } catch {
    actionsState.value = 'error'
  }
}

async function load() {
  if (!resolveAuthToken()) return
  try {
    const response = await fetchToday()
    const payload = response.data?.data
    production.value = payload
    productionOpen.value = !!payload?.auto_open
    if (payload?.message && !payload.auto_open) lastClosed.value = 'production'
  } catch {
    production.value = null
    productionOpen.value = false
  }
  sessionReady.value = !!resolveAuthToken()
}

async function closeActive() {
  if (showingTest.value) {
    closeDailyEdgeTest()
    lastClosed.value = 'test'
    return
  }
  if (!showingProduction.value || dismissing) return
  productionOpen.value = false
  lastClosed.value = 'production'
  if (production.value) production.value = { ...production.value, auto_open: false, dismissed: true }
  dismissing = true
  try {
    const response = await dismissToday()
    if (response.data?.data) production.value = response.data.data
  } catch {
    // The card is already closed. A failed dismiss must not trap the user.
  } finally {
    dismissing = false
  }
}

function closeMyDay() {
  myDayOpen.value = false
}

function openAction(item) {
  const leadId = item?.lead_id
  const dealId = item?.deal_id
  const listingId = item?.listing_id
  closeMyDay()
  if (listingId) {
    openPropertyDetails(listingId)
    return
  }
  // Marketing: open the card on its board (or the calendar for a free calendar post).
  if (item?.marketing_booking_id && !item?.marketing_task_id) {
    router.push({ path: '/marketing', query: { tab: 'bookings', booking: String(item.marketing_booking_id) } })
    return
  }
  if (item?.marketing_task_id) {
    router.push({ path: '/marketing', query: { task: String(item.marketing_task_id), tab: item.marketing_category || undefined } })
    return
  }
  if (item?.kind === 'Calendar') {
    router.push({ path: '/marketing', query: { tab: 'calendar' } })
    return
  }
  if (item?.kind === 'Deal' && dealId) {
    router.push({ path: '/kanban_deal', query: { deal: String(dealId) } })
    return
  }
  if (leadId) openLeadView(leadId, { id: leadId, lead_name: item.title || '' })
}

function clampDayTop(px) {
  const height = dayPillEl.value?.offsetHeight || 40
  const max = Math.max(DAY_PILL_MARGIN, window.innerHeight - height - DAY_PILL_MARGIN)
  return Math.min(max, Math.max(DAY_PILL_MARGIN, px))
}

function rememberDayTop(px) {
  const ratio = clampDayTop(px) / window.innerHeight
  dayPillTop.value = ratio
  try {
    localStorage.setItem(DAY_PILL_KEY, String(ratio))
  } catch {
    // Position still applies for this visit if storage is blocked.
  }
}

function restoreDayTop() {
  try {
    const ratio = Number(localStorage.getItem(DAY_PILL_KEY))
    if (!Number.isFinite(ratio)) return
    dayPillTop.value = ratio
    requestAnimationFrame(() => {
      if (dayPillTop.value == null) return
      rememberDayTop(dayPillTop.value * window.innerHeight)
    })
  } catch {
    dayPillTop.value = null
  }
}

function onDayPointerDown(event) {
  if (event.button != null && event.button !== 0) return
  const el = event.currentTarget
  ignoreDayClick = false
  dayPointerId = event.pointerId
  dayDragStartY = event.clientY
  dayDragStartTop = el.getBoundingClientRect().top
  dayDragMoved = false
  dayDragging.value = true
  el.setPointerCapture?.(event.pointerId)
}

function onDayPointerMove(event) {
  if (dayPointerId !== event.pointerId) return
  const next = dayDragStartTop + (event.clientY - dayDragStartY)
  if (Math.abs(event.clientY - dayDragStartY) > 4) dayDragMoved = true
  if (!dayDragMoved) return
  dayPillTop.value = clampDayTop(next) / window.innerHeight
}

function onDayPointerUp(event) {
  if (dayPointerId !== event.pointerId) return
  dayPointerId = null
  dayDragging.value = false
  if (!dayDragMoved || dayPillTop.value == null) return
  ignoreDayClick = true
  rememberDayTop(dayPillTop.value * window.innerHeight)
}

function onDayClick() {
  if (ignoreDayClick || dayDragMoved) {
    ignoreDayClick = false
    dayDragMoved = false
    return
  }
  openMyDay()
}

function openMyDay() {
  myDayOpen.value = true
  loadActions()
}

function reopen() {
  if (lastClosed.value === 'test' && testPayload.value?.message) {
    testOpen.value = true
    return
  }
  if (production.value?.message) {
    productionOpen.value = true
    return
  }
  if (testPayload.value?.message) testOpen.value = true
}

function onResize() {
  viewportHeight.value = window.innerHeight
  if (dayPillTop.value == null || dayDragging.value) return
  rememberDayTop(dayPillTop.value * window.innerHeight)
}

onMounted(() => {
  load()
  window.addEventListener('daily-edge:test', onTestEvent)
  window.addEventListener('daily-edge:open', onDelivered)
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('resize', onResize)
})

onUnmounted(() => {
  window.removeEventListener('daily-edge:test', onTestEvent)
  window.removeEventListener('daily-edge:open', onDelivered)
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', onResize)
})
</script>

<style scoped>
.edge-dock {
  position: fixed;
  z-index: 99998;
  top: 0;
  right: 16px;
  bottom: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: stretch;
  gap: 12px;
  width: min(340px, calc(100vw - 32px));
  pointer-events: none;
}

.edge-dock :deep(.edge-card),
.edge-dock--day .myday {
  pointer-events: auto;
}

.edge-dock--day,
.edge-dock--stack {
  justify-content: center;
  padding: 88px 0 24px;
}

.edge-dock--stack .myday,
.edge-dock--day .myday {
  flex: 0 1 auto;
  max-height: min(520px, calc(100vh - 280px));
}

.myday {
  display: flex;
  flex-direction: column;
  width: 100%;
  min-height: 0;
  max-height: 100%;
  padding: 14px;
  color: #0b0736;
  background: #ffffff;
  border: 1px solid #ebecef;
  border-radius: 18px;
  box-shadow: 0 18px 40px rgba(11, 7, 54, 0.14);
  font-family: Inter, system-ui, sans-serif;
}

.myday__top {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
}

.myday__mark {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 12px;
  color: #ffffff;
  background: var(--gradient-primary);
  font-size: 18px;
}

.myday__kicker {
  margin: 0;
  color: #733e87;
  font-size: 12px;
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.myday__date {
  margin: 2px 0 0;
  color: #6b6580;
  font-size: 12px;
}

.myday__close {
  margin-left: auto;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 999px;
  color: #3b3558;
  background: #f4f1f7;
  cursor: pointer;
}

.myday__note {
  margin: 16px 0 4px;
  color: #6b6580;
  font-size: 14px;
  line-height: 1.45;
}

.myday__list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 14px;
  min-height: 0;
  overflow: auto;
  padding-right: 2px;
}

.myday-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  width: 100%;
  padding: 12px 12px 10px;
  text-align: left;
  color: inherit;
  background: #fffdfb;
  border: 1px solid #f0e6dc;
  border-radius: 16px;
  cursor: pointer;
}

.myday-card:hover {
  border-color: #e4d2c2;
  background: #fff8f3;
}

.myday-card__title {
  color: #1a1433;
  font-size: 15px;
  font-weight: 750;
  line-height: 1.3;
}

.myday-card__detail {
  color: #6d657c;
  font-size: 13px;
  line-height: 1.4;
}

.myday-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 2px;
}

.myday-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  color: #6a5a4a;
  background: #f6eee6;
  font-size: 11px;
  font-weight: 650;
}

.myday-chip--kind {
  color: #733e87;
  background: #f3eaf6;
}

.edge-pill {
  position: fixed;
  z-index: 1100;
  right: 16px;
  top: calc(50% + 168px);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border: 0;
  border-radius: 999px;
  color: #ffffff !important;
  background: var(--gradient-primary) !important;
  box-shadow: 0 10px 24px rgba(var(--color-purple-rgb), 0.28);
  font-family: Inter, system-ui, sans-serif;
  font-size: 13px;
  font-weight: 650;
  letter-spacing: 0.04em;
  cursor: pointer;
}

.edge-pill--day {
  top: calc(50% + 216px);
  touch-action: none;
  cursor: grab;
  user-select: none;
}

.edge-pill--dragging {
  cursor: grabbing;
}

.edge-pill__mark {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #ffffff;
}

.edge-slide-enter-active {
  transition: opacity 0.3s ease, transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}

.edge-slide-leave-active {
  transition: opacity 0.2s ease, transform 0.28s ease;
}

.edge-slide-enter-from,
.edge-slide-leave-to {
  opacity: 0;
  transform: translateX(110%);
}

@media (max-width: 1024px) {
  .edge-dock {
    align-items: flex-end;
    right: 12px;
    padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px));
  }

  .edge-dock--day,
  .edge-dock--stack {
    justify-content: flex-end;
    padding-top: 72px;
    padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px));
  }

  .edge-pill {
    top: auto;
    right: 12px;
    bottom: calc(96px + env(safe-area-inset-bottom, 0px));
  }

  .edge-pill--day {
    bottom: calc(148px + env(safe-area-inset-bottom, 0px));
  }
}

@media (prefers-reduced-motion: reduce) {
  .edge-slide-enter-active,
  .edge-slide-leave-active {
    transition: none;
  }
}
</style>
