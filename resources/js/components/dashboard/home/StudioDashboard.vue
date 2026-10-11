<template>
  <div class="lf-dashboard">
    <div class="lf-frame">
      <div v-if="error" class="lf-error">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
        <button type="button" @click="load">Retry</button>
      </div>

      <div v-if="loading && !board" class="lf-board" aria-hidden="true">
        <div class="lf-kpis">
          <div v-for="n in 4" :key="n" class="lf-skel" />
        </div>
        <div class="lf-skel lf-remind" />
        <div class="lf-skel lf-perf" style="min-height: 240px" />
        <div class="lf-skel lf-feature" style="min-height: 180px" />
        <div class="lf-skel lf-deals" />
        <div class="lf-skel lf-cal" style="min-height: 260px" />
        <div class="lf-skel lf-table" style="min-height: 220px" />
        <div class="lf-skel lf-people" style="min-height: 220px" />
        <div class="lf-skel lf-sched" style="min-height: 220px" />
      </div>

      <div v-else class="lf-board">
        <section
          ref="kpiDeck"
          class="lf-kpis"
          :class="{ 'is-deck': deckMode, 'is-dragging': deckDragging }"
          aria-label="Key numbers"
          :aria-roledescription="deckMode ? 'carousel' : undefined"
          @pointerdown="onKpiPointerDown"
          @pointermove="onKpiPointerMove"
          @pointerup="onKpiPointerUp"
          @pointercancel="onKpiPointerUp"
        >
          <button
            v-for="(card, index) in kpiCards"
            :key="card.key"
            type="button"
            class="lf-card lf-kpi"
            :class="kpiDepthClass(index)"
            :style="deckCardStyle(index)"
            @click="onKpiClick($event, card, index)"
          >
            <span class="lf-kpi-face">
              <span class="lf-kpi-top">
                <span class="lf-kpi-icon">
                  <iconify-icon :icon="card.icon" width="18" height="18" />
                </span>
                <span class="lf-kpi-label">{{ card.label }}</span>
                <span class="lf-badge" :class="badgeClass(card.change)">
                  {{ formatPct(card.change) }}
                  <iconify-icon :icon="card.change < 0 ? 'lucide:trending-down' : 'lucide:trending-up'" width="12" height="12" />
                </span>
              </span>
              <span class="lf-kpi-value">{{ card.display }}</span>
            </span>
          </button>
          <div v-if="deckMode" class="lf-kpi-dots">
            <button
              v-for="(card, index) in kpiCards"
              :key="`dot-${card.key}`"
              type="button"
              class="lf-kpi-dot"
              :class="{ 'is-on': index === deckIndex }"
              :aria-label="`Show ${card.label}`"
              @click.stop="focusDeckCard(index)"
            />
          </div>
        </section>

        <article class="lf-card lf-remind">
          <div class="lf-card-head">
            <h2 class="lf-card-title">Reminder</h2>
            <router-link to="/kanban" class="lf-card-link" aria-label="Open leads">
              <iconify-icon icon="lucide:chevron-right" width="16" height="16" />
            </router-link>
          </div>
          <button type="button" class="lf-remind-row" @click="go('/kanban')">
            <span class="lf-remind-icon"><iconify-icon icon="lucide:phone-call" width="16" height="16" /></span>
            <span class="lf-remind-copy">
              <strong>Follow-Ups</strong>
              <span>{{ formatNumber(reminders.follow_ups?.count) }} leads need to be followed up</span>
            </span>
            <span v-if="reminders.follow_ups?.avatars?.length" class="lf-faces">
              <template v-for="(face, idx) in reminders.follow_ups.avatars" :key="idx">
                <img v-if="face.avatar" :src="face.avatar" alt="" />
                <span v-else>{{ initials(face.name) }}</span>
              </template>
            </span>
            <span class="lf-ring" aria-hidden="true" />
          </button>
          <button type="button" class="lf-remind-row" @click="go('/need-approve-requests')">
            <span class="lf-remind-icon"><iconify-icon icon="lucide:file-text" width="16" height="16" /></span>
            <span class="lf-remind-copy">
              <strong>Documents</strong>
              <span>{{ formatNumber(reminders.documents?.count) }} listings awaiting approval</span>
            </span>
            <span class="lf-ring" aria-hidden="true" />
          </button>
          <button type="button" class="lf-remind-row" @click="go('/alllisting')">
            <span class="lf-remind-icon"><iconify-icon icon="lucide:timer" width="16" height="16" /></span>
            <span class="lf-remind-copy">
              <strong>Expire Listings</strong>
              <span>{{ expireCopy }}</span>
            </span>
            <span class="lf-ring" aria-hidden="true" />
          </button>
        </article>

        <article class="lf-card lf-perf">
          <div class="lf-card-head">
            <h2 class="lf-card-title">Performance</h2>
            <label class="lf-period">
              <select v-model="period" @change="load">
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
              </select>
              <iconify-icon icon="lucide:chevron-down" width="14" height="14" />
            </label>
          </div>
          <div class="lf-legend">
            <span><i /> Revenue</span>
            <span><i class="is-visit" /> Visit</span>
          </div>
          <div class="lf-chart" @mouseleave="hoverIndex = null">
            <div class="lf-y" aria-hidden="true">
              <span>100%</span><span>75%</span><span>50%</span><span>25%</span><span>0</span>
            </div>
            <svg viewBox="0 0 640 168" role="img" aria-label="Revenue and visits" @mousemove="onChartMove">
              <line
                v-for="tick in 5"
                :key="tick"
                x1="36"
                x2="630"
                :y1="chartY(tick - 1)"
                :y2="chartY(tick - 1)"
                stroke="#efeaf6"
                stroke-dasharray="4 6"
              />
              <path :d="areaPath" fill="url(#lfArea)" />
              <path :d="visitPath" fill="none" stroke="#d8b4fe" stroke-width="2.5" stroke-linecap="round" />
              <path :d="revenuePath" fill="none" stroke="#733e87" stroke-width="2.5" stroke-linecap="round" />
              <line
                v-if="activePoint"
                :x1="activePoint.x"
                :x2="activePoint.x"
                y1="12"
                y2="150"
                stroke="#c4b5fd"
                stroke-dasharray="3 4"
              />
              <circle v-if="activePoint" :cx="activePoint.x" :cy="activePoint.y" r="5" fill="#fff" stroke="#733e87" stroke-width="2.5" />
              <defs>
                <linearGradient id="lfArea" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#733e87" stop-opacity="0.22" />
                  <stop offset="100%" stop-color="#733e87" stop-opacity="0" />
                </linearGradient>
              </defs>
            </svg>
            <div v-if="activePoint" class="lf-tip" :style="{ left: tipLeft }">
              <strong>{{ money(activePoint.revenue) }}</strong>
              <span>{{ formatNumber(activePoint.visits) }} visits · {{ activePoint.label }}</span>
            </div>
            <div class="lf-chart-x">
              <span
                v-for="(point, index) in chartPoints"
                :key="point.label + index"
                :class="{ 'is-on': index === (hoverIndex ?? defaultChartIndex) }"
              >{{ point.label }}</span>
            </div>
          </div>
        </article>

        <article v-if="featured" class="lf-card lf-feature">
          <div class="lf-feature-copy">
            <h2 class="lf-card-title">{{ featured.title }}</h2>
            <p class="lf-feature-type">{{ featured.type }}</p>
            <div class="lf-feature-stats">
              <div><strong>{{ formatNumber(featured.sold) }}</strong><span>Sold</span></div>
              <div><strong>{{ formatNumber(featured.rented) }}</strong><span>Rented</span></div>
              <div><strong>{{ compactCount(featured.views) }}</strong><span>Views</span></div>
            </div>
          </div>
          <div class="lf-feature-photo">
            <img v-if="featured.image && !brokenImages.has('feature')" :src="featured.image" alt="" @error="markBroken('feature')" />
            <router-link :to="`/property-details/${featured.id}`" class="lf-feature-go" aria-label="Open property">
              <iconify-icon icon="lucide:arrow-up-right" width="14" height="14" />
            </router-link>
            <div class="lf-reco">
              <i><iconify-icon icon="lucide:sparkles" width="10" height="10" /></i>
              Recommended to {{ formatNumber(featured.recommended) }} leads
            </div>
          </div>
        </article>
        <article v-else class="lf-card lf-feature">
          <p class="lf-empty">No active listing to feature yet.</p>
        </article>

        <article class="lf-card lf-deals">
          <div class="lf-card-head">
            <h2 class="lf-card-title">Deals</h2>
            <router-link to="/kanban_deal" class="lf-card-link" aria-label="Open deals">
              <iconify-icon icon="lucide:chevron-right" width="16" height="16" />
            </router-link>
          </div>
          <div class="lf-deals-track" role="img" :aria-label="`${deals.closed} closed, ${deals.progress} in progress`">
            <div class="lf-deals-fill" :style="{ width: dealWidth }" />
          </div>
          <div class="lf-deals-meta">
            <span><strong>{{ formatNumber(deals.closed) }}</strong> Closed Deals</span>
            <span><strong>{{ formatNumber(deals.progress) }}</strong> On Progress</span>
          </div>
        </article>

        <article class="lf-card lf-cal">
          <div class="lf-cal-head">
            <h2 class="lf-card-title">{{ monthLabel }}</h2>
            <div class="lf-cal-nav">
              <button type="button" aria-label="Previous month" @click="shiftMonth(-1)">
                <iconify-icon icon="lucide:chevron-left" width="16" height="16" />
              </button>
              <button type="button" aria-label="Next month" @click="shiftMonth(1)">
                <iconify-icon icon="lucide:chevron-right" width="16" height="16" />
              </button>
            </div>
          </div>
          <div class="lf-week">
            <span v-for="day in weekLabels" :key="day">{{ day }}</span>
          </div>
          <div class="lf-days">
            <button
              v-for="(cell, index) in calendarCells"
              :key="index"
              type="button"
              :class="{
                'is-out': !cell,
                'is-selected': cell && cell.iso === selectedDate,
                'is-today': cell && cell.iso === todayIso,
                'has-event': cell && eventDates.has(cell.iso),
              }"
              :disabled="!cell"
              @click="cell && (selectedDate = cell.iso)"
            >
              {{ cell ? cell.day : '' }}
            </button>
          </div>
        </article>

        <article class="lf-card lf-table">
          <div class="lf-card-head">
            <h2 class="lf-card-title">Active Listing</h2>
            <button type="button" class="lf-mini" aria-label="Open listings" @click="go('/alllisting')">
              <iconify-icon icon="lucide:search" width="14" height="14" />
            </button>
          </div>
          <div v-if="visibleListings.length" class="lf-table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Property</th>
                  <th>Type</th>
                  <th>Units</th>
                  <th>Cost</th>
                  <th>Active Leads</th>
                  <th>Views</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in visibleListings" :key="row.id">
                  <td>
                    <button type="button" class="lf-prop" @click="go(`/property-details/${row.id}`)">
                      <img v-if="row.image && !brokenImages.has('listing-' + row.id)" :src="row.image" alt="" class="lf-thumb" @error="markBroken('listing-' + row.id)" />
                      <span v-else class="lf-thumb is-empty"><iconify-icon icon="lucide:building-2" width="16" height="16" /></span>
                      <span>
                        <strong>{{ row.title }}</strong>
                        <span>{{ row.location || '—' }}</span>
                      </span>
                    </button>
                  </td>
                  <td>{{ row.type }}</td>
                  <td>{{ formatNumber(row.units) }}</td>
                  <td>{{ cost(row.cost) }}</td>
                  <td>
                    <span class="lf-leads-pill">
                      <i><iconify-icon icon="lucide:users" width="11" height="11" /></i>
                      {{ formatNumber(row.leads) }}
                    </span>
                  </td>
                  <td>{{ formatNumber(row.views) }}</td>
                  <td>
                    <span class="lf-status" :class="statusClass(row.status)">{{ row.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="lf-empty">No listings match this view.</p>
        </article>

        <article class="lf-card lf-people">
          <div class="lf-card-head">
            <h2 class="lf-card-title">Leads Contact</h2>
            <button type="button" class="lf-mini" aria-label="Open leads" @click="go('/kanban')">
              <iconify-icon icon="lucide:sliders-horizontal" width="14" height="14" />
            </button>
          </div>
          <button
            v-for="person in visibleContacts"
            :key="person.id"
            type="button"
            class="lf-person"
            @click="openContact(person)"
          >
            <img v-if="person.avatar && !brokenImages.has('lead-' + person.id)" :src="person.avatar" alt="" class="lf-avatar" @error="markBroken('lead-' + person.id)" />
            <span v-else class="lf-avatar-fallback">{{ initials(person.name) }}</span>
            <span class="lf-person-copy">
              <strong>{{ person.name }}</strong>
              <span>{{ person.location || 'Lead' }}</span>
            </span>
            <a
              v-if="person.phone"
              class="lf-call"
              :href="`tel:${person.phone}`"
              :aria-label="`Call ${person.name}`"
              @click.stop
            >
              <iconify-icon icon="lucide:phone" width="14" height="14" />
            </a>
          </button>
          <p v-if="!visibleContacts.length" class="lf-empty">No leads to show yet.</p>
        </article>

        <article class="lf-card lf-sched" aria-label="Schedule">
          <div class="lf-tabs" role="tablist">
            <button type="button" :class="{ 'is-on': scheduleTab === 'all' }" @click="scheduleTab = 'all'">All</button>
            <button type="button" :class="{ 'is-on': scheduleTab === 'assigned' }" @click="scheduleTab = 'assigned'">Assigned</button>
            <button type="button" :class="{ 'is-on': scheduleTab === 'mine' }" @click="scheduleTab = 'mine'">My Schedule</button>
          </div>
          <p v-if="scheduleView.caption" class="lf-note">{{ scheduleView.caption }}</p>
          <div v-if="scheduleView.items.length" class="lf-sched-list">
            <component
              :is="item.lead_id ? 'button' : 'div'"
              v-for="item in scheduleView.items"
              :key="item.id"
              class="lf-visit"
              :type="item.lead_id ? 'button' : undefined"
              @click="item.lead_id && openLeadView(item.lead_id)"
            >
              <strong>{{ item.title }}</strong>
              <span>{{ item.detail }}</span>
            </component>
          </div>
          <p v-else class="lf-empty">Nothing scheduled for this day.</p>
        </article>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { openLeadView } from '@/composables/useLeadViewModal.js'

const router = useRouter()

const board = ref(null)
const loading = ref(true)
const error = ref('')
const period = ref('monthly')
const brokenImages = ref(new Set())
const hoverIndex = ref(null)
const scheduleTab = ref('all')
const kpiDeck = ref(null)
const phoneDeckQuery = '(max-width: 767px), (max-height: 500px) and (max-width: 940px)'
const deckMode = ref(typeof window !== 'undefined' && window.matchMedia(phoneDeckQuery).matches)
const deckIndex = ref(0)
const deckDrag = ref(0)
const deckDragging = ref(false)
const kpiDragged = ref(false)
let deckPointerId = null
let deckStartX = 0
let deckStartY = 0
let deckStartT = 0
let deckAxis = null
let deckSettleFrame = 0
let deckEpoch = 0
let deckObserver = null

const now = new Date()
const month = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const todayIso = toIso(now)
const selectedDate = ref(todayIso)

const weekLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const kpis = computed(() => board.value?.kpis || {})
const reminders = computed(() => board.value?.reminders || {})
const featured = computed(() => board.value?.featured || null)
const deals = computed(() => board.value?.deals || { closed: 0, progress: 0 })
const chartPoints = computed(() => board.value?.performance?.points || [])

const kpiCards = computed(() => [
  {
    key: 'leads',
    label: 'Active Leads',
    icon: 'lucide:users',
    display: formatNumber(kpis.value.active_leads?.value),
    change: kpis.value.active_leads?.change,
    to: '/kanban',
  },
  {
    key: 'revenue',
    label: 'Total Revenue',
    icon: 'lucide:badge-dollar-sign',
    display: money(kpis.value.total_revenue?.value),
    change: kpis.value.total_revenue?.change,
    to: '/kanban_deal',
  },
  {
    key: 'listings',
    label: 'Active Listing',
    icon: 'lucide:house',
    display: formatNumber(kpis.value.active_listings?.value),
    change: kpis.value.active_listings?.change,
    to: '/alllisting',
  },
  {
    key: 'closed',
    label: 'Total Closed',
    icon: 'lucide:handshake',
    display: formatNumber(kpis.value.total_closed?.value),
    change: kpis.value.total_closed?.change,
    to: '/kanban_deal',
  },
])

const visibleListings = computed(() => board.value?.listings || [])

const visibleContacts = computed(() => board.value?.contacts || [])

const eventDates = computed(() => new Set(board.value?.calendar?.dates || []))

const monthLabel = computed(() => {
  const [year, mon] = month.value.split('-').map(Number)
  return new Date(year, mon - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const calendarCells = computed(() => {
  const [year, mon] = month.value.split('-').map(Number)
  const first = new Date(year, mon - 1, 1)
  const pad = (first.getDay() + 6) % 7
  const days = new Date(year, mon, 0).getDate()
  const cells = Array.from({ length: pad }, () => null)
  for (let day = 1; day <= days; day += 1) {
    const date = new Date(year, mon - 1, day)
    cells.push({ day, iso: toIso(date) })
  }
  while (cells.length % 7 !== 0) cells.push(null)
  return cells
})

const scheduleView = computed(() => {
  const items = (board.value?.calendar?.items || []).filter((item) => matchesTab(item))
  const todayItems = items.filter((item) => item.date === selectedDate.value)
  if (todayItems.length) return { caption: '', items: todayItems }
  const upcoming = items
    .filter((item) => item.kind === 'visit' && item.date >= selectedDate.value)
    .slice(0, 3)
  return {
    caption: upcoming.length ? 'Upcoming visits' : '',
    items: upcoming,
  }
})

const chartGeometry = computed(() => buildChart(chartPoints.value))
const revenuePath = computed(() => smoothPath(chartGeometry.value.revenue))
const visitPath = computed(() => smoothPath(chartGeometry.value.visits))
const areaPath = computed(() => {
  const points = chartGeometry.value.revenue
  if (!points.length) return ''
  const line = smoothPath(points)
  const last = points[points.length - 1]
  const first = points[0]
  return `${line} L ${last.x} 150 L ${first.x} 150 Z`
})

const defaultChartIndex = computed(() => {
  const points = chartPoints.value
  for (let index = points.length - 1; index >= 0; index -= 1) {
    if ((Number(points[index]?.revenue) || 0) > 0 || (Number(points[index]?.visits) || 0) > 0) return index
  }
  return Math.max(points.length - 1, 0)
})

const activePoint = computed(() => {
  const points = chartGeometry.value.revenue
  if (!points.length) return null
  const index = hoverIndex.value == null ? defaultChartIndex.value : hoverIndex.value
  return points[index] || null
})

const tipLeft = computed(() => {
  if (!activePoint.value) return '50%'
  return `${(activePoint.value.x / 640) * 100}%`
})

const expireCopy = computed(() => {
  const count = Number(reminders.value.expiring?.count) || 0
  const days = reminders.value.expiring?.days || 5
  const noun = count === 1 ? 'listing is' : 'listings are'
  return `${formatNumber(count)} ${noun} about to expire in ${days} days`
})

const dealWidth = computed(() => {
  const closed = Number(deals.value.closed) || 0
  const progress = Number(deals.value.progress) || 0
  const total = closed + progress
  if (!total || closed <= 0) return '0%'
  return `${Math.max(6, Math.round((closed / total) * 100))}%`
})

function toIso(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function formatNumber(n) {
  return new Intl.NumberFormat().format(Number(n) || 0)
}

function money(n) {
  const value = Number(n) || 0
  const abs = Math.abs(value)
  const sign = value < 0 ? '-' : ''
  if (abs >= 1e9) return `${sign}AED ${(abs / 1e9).toFixed(1)}B`
  if (abs >= 1e6) return `${sign}AED ${(abs / 1e6).toFixed(1)}M`
  if (abs >= 1e3) return `${sign}AED ${(abs / 1e3).toFixed(abs >= 10000 ? 0 : 1)}K`
  return new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(value)
}

function cost(n) {
  const value = Number(n) || 0
  if (!value) return '—'
  return money(value)
}

function compactCount(n) {
  const value = Number(n) || 0
  if (value >= 1000) {
    const compact = value / 1000
    return `${compact >= 10 ? Math.round(compact) : compact.toFixed(1).replace(/\.0$/, '')}K+`
  }
  return formatNumber(value)
}

function formatPct(n) {
  const value = Number(n) || 0
  const body = Math.abs(value).toFixed(Math.abs(value) % 1 === 0 ? 0 : 1)
  if (value > 0) return `+${body}%`
  if (value < 0) return `-${body}%`
  return '0%'
}

function badgeClass(n) {
  const value = Number(n) || 0
  if (value > 0) return 'is-up'
  if (value < 0) return 'is-down'
  return 'is-flat'
}

function statusClass(status) {
  if (status === 'Sold Out') return 'is-sold'
  if (status === 'Occupied') return 'is-occupied'
  return ''
}

function initials(name) {
  const parts = String(name || '?').trim().split(/\s+/).filter(Boolean)
  return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('') || '?'
}

function markBroken(id) {
  if (brokenImages.value.has(id)) return
  brokenImages.value = new Set([...brokenImages.value, id])
}

function go(path) {
  router.push(path).catch(() => {})
}

function measureDeck() {
  deckMode.value = window.matchMedia(phoneDeckQuery).matches
}

function bindDeckObserver() {
  deckObserver?.disconnect()
  const el = kpiDeck.value
  if (!el || typeof ResizeObserver === 'undefined') return
  deckObserver = new ResizeObserver(() => measureDeck())
  deckObserver.observe(el)
  measureDeck()
}

function kpiDepthClass(index) {
  if (!deckMode.value) return ''
  const delta = index - deckIndex.value
  if (delta === 0) return 'is-front'
  if (delta === 1) return 'is-next'
  if (delta > 1) return 'is-far'
  return 'is-past'
}

function deckCardStyle(index) {
  if (!deckMode.value) return null
  const width = kpiDeck.value?.clientWidth || 360
  const shift = deckDrag.value / width
  const rel = index - deckIndex.value + shift
  const depth = Math.max(rel, 0)
  const leaving = rel < 0
  const x = leaving ? rel * width : (depth < 1 ? depth * 62 : 62 + (Math.min(depth, 3) - 1) * 14)
  const scale = leaving ? 1 : 1 - Math.min(depth, 3) * 0.03
  return {
    transform: `translate3d(${x}px, 0, 0) scale(${scale})`,
    transformOrigin: 'right center',
    zIndex: leaving ? 30 : 20 - Math.round(Math.min(depth, 3) * 4),
    transition: deckDragging.value ? 'none' : 'transform 0.42s cubic-bezier(0.22, 1, 0.36, 1)',
    pointerEvents: rel < -0.04 ? 'none' : 'auto',
  }
}

function focusDeckCard(index) {
  const last = kpiCards.value.length - 1
  deckEpoch += 1
  if (deckSettleFrame) cancelAnimationFrame(deckSettleFrame)
  deckSettleFrame = 0
  deckIndex.value = Math.min(last, Math.max(0, index))
  deckDrag.value = 0
  deckDragging.value = false
}

function onKpiPointerDown(event) {
  if (!deckMode.value || event.button > 0) return
  deckPointerId = event.pointerId
  deckStartX = event.clientX
  deckStartY = event.clientY
  deckStartT = Date.now()
  deckAxis = null
  kpiDragged.value = false
}

function onKpiPointerMove(event) {
  if (!deckMode.value || event.pointerId !== deckPointerId) return
  const dx = event.clientX - deckStartX
  const dy = event.clientY - deckStartY
  if (!deckAxis) {
    if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return
    deckAxis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y'
    if (deckAxis === 'y') {
      deckPointerId = null
      return
    }
    try { event.currentTarget.setPointerCapture?.(event.pointerId) } catch (_) {}
  }
  const last = kpiCards.value.length - 1
  let drag = dx
  if ((deckIndex.value === 0 && drag > 0) || (deckIndex.value === last && drag < 0)) drag *= 0.32
  deckDragging.value = true
  kpiDragged.value = true
  deckDrag.value = drag
}

function onKpiPointerUp(event) {
  if (event.pointerId !== deckPointerId) return
  const width = kpiDeck.value?.clientWidth || 360
  const dx = deckDrag.value
  const velocity = dx / Math.max(16, Date.now() - deckStartT)
  const threshold = Math.min(64, width * 0.16)
  const last = kpiCards.value.length - 1
  let next = deckIndex.value
  if (deckAxis === 'x') {
    if ((dx < -threshold || velocity < -0.45) && deckIndex.value < last) next += 1
    else if ((dx > threshold || velocity > 0.45) && deckIndex.value > 0) next -= 1
  }
  deckPointerId = null
  deckAxis = null
  const epoch = deckEpoch
  deckDragging.value = false
  if (deckSettleFrame) cancelAnimationFrame(deckSettleFrame)
  deckSettleFrame = requestAnimationFrame(() => {
    deckSettleFrame = 0
    if (epoch !== deckEpoch) return
    deckIndex.value = next
    deckDrag.value = 0
  })
  if (kpiDragged.value) {
    window.setTimeout(() => {
      kpiDragged.value = false
    }, 380)
  }
}

function onKpiClick(event, card, index) {
  if (kpiDragged.value) {
    kpiDragged.value = false
    return
  }
  if (deckMode.value && index !== deckIndex.value) {
    focusDeckCard(index)
    return
  }
  go(card.to)
}

function openContact(person) {
  openLeadView(person.id, { id: person.id, lead_name: person.name })
}

function matchesTab(item) {
  if (scheduleTab.value === 'mine') return !!item.mine
  if (scheduleTab.value === 'assigned') return !item.mine
  return true
}

function chartY(step) {
  return 12 + step * 34.5
}

function buildChart(points) {
  const width = 640
  const height = 168
  const left = 36
  const right = 10
  const top = 12
  const bottom = 18
  const innerW = width - left - right
  const innerH = height - top - bottom
  const revenues = points.map((point) => Number(point.revenue) || 0)
  const visits = points.map((point) => Number(point.visits) || 0)
  const maxRevenue = Math.max(...revenues, 1)
  const maxVisits = Math.max(...visits, 1)
  const count = Math.max(points.length, 1)
  const xAt = (index) => left + (count === 1 ? innerW / 2 : (index / (count - 1)) * innerW)
  const yAt = (value, max) => top + innerH - (value / max) * innerH
  return {
    revenue: points.map((point, index) => ({
      ...point,
      x: xAt(index),
      y: yAt(revenues[index], maxRevenue),
    })),
    visits: points.map((point, index) => ({
      x: xAt(index),
      y: yAt(visits[index], maxVisits),
    })),
  }
}

function smoothPath(points) {
  if (!points.length) return ''
  if (points.length === 1) return `M ${points[0].x} ${points[0].y}`
  let path = `M ${points[0].x} ${points[0].y}`
  for (let index = 0; index < points.length - 1; index += 1) {
    const current = points[index]
    const next = points[index + 1]
    const mid = (current.x + next.x) / 2
    path += ` C ${mid} ${current.y}, ${mid} ${next.y}, ${next.x} ${next.y}`
  }
  return path
}

function onChartMove(event) {
  const rect = event.currentTarget.getBoundingClientRect()
  const x = ((event.clientX - rect.left) / rect.width) * 640
  const points = chartGeometry.value.revenue
  if (!points.length) return
  let nearest = 0
  let best = Infinity
  points.forEach((point, index) => {
    const distance = Math.abs(point.x - x)
    if (distance < best) {
      best = distance
      nearest = index
    }
  })
  hoverIndex.value = nearest
}

async function shiftMonth(delta) {
  const [year, mon] = month.value.split('-').map(Number)
  const next = new Date(year, mon - 1 + delta, 1)
  month.value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`
  if (!selectedDate.value.startsWith(month.value)) {
    selectedDate.value = todayIso.startsWith(month.value) ? todayIso : `${month.value}-01`
  }
  await load()
}

async function load() {
  error.value = ''
  if (!board.value) loading.value = true
  try {
    const res = await api.get('/dashboard/studio', {
      params: { period: period.value, month: month.value },
    })
    board.value = res.data?.data || null
    if (!board.value) error.value = 'Dashboard data did not load.'
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load the dashboard.'
  } finally {
    loading.value = false
    await nextTick()
    bindDeckObserver()
  }
}

onMounted(() => {
  load()
  window.addEventListener('resize', measureDeck)
})

onBeforeUnmount(() => {
  deckObserver?.disconnect()
  window.removeEventListener('resize', measureDeck)
  if (deckSettleFrame) cancelAnimationFrame(deckSettleFrame)
})
</script>
