<template>
  <div class="dashboard-main-body">
    <Breadcrumb title="My Viewings" :breadcrumbs="[{ name: 'My Viewings' }]" />

    <div class="row gy-4">
      <div class="col-xxl-9 col-lg-8">
        <div class="card h-100 p-0">
          <div class="card-body p-24">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
              <h4 class="calendar-title mb-0">Viewings Calendar</h4>

              <div class="calendar-controls align-items-center d-flex gap-2">
                <div class="view-buttons">
                  <button
                    class="fc-button"
                    :class="{ active: currentView === 'dayGridMonth' }"
                    @click="changeView('dayGridMonth')"
                  >
                    month
                  </button>
                  <button
                    class="fc-button"
                    :class="{ active: currentView === 'timeGridWeek' }"
                    @click="changeView('timeGridWeek')"
                  >
                    week
                  </button>
                  <button
                    class="fc-button"
                    :class="{ active: currentView === 'timeGridDay' }"
                    @click="changeView('timeGridDay')"
                  >
                    day
                  </button>
                </div>

                <div class="nav-buttons">
                  <button class="fc-button" @click="prev">‹</button>
                  <button class="fc-button" @click="next">›</button>
                </div>

                <button class="fc-button today-button" @click="today">today</button>
              </div>
            </div>

            <FullCalendar ref="fullCalendar" :options="calendarOptions" />
          </div>
        </div>
      </div>

      <!-- ===== SIDEBAR ===== -->
      <div class="col-xxl-3 col-lg-4">
        <div class="card h-100">
          <div class="card-body p-16 d-flex flex-column" style="height: 100%;">

            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-2" style="flex-shrink: 0;">
              <h6 class="mb-0" style="font-size: 14px !important;">
                {{ selectedDate ? formatSelectedDate(selectedDate) : 'Sorted by Date' }}
              </h6>
              <div class="d-flex align-items-center gap-2">
                <button
                  v-if="selectedDate"
                  class="btn btn-sm btn-outline-secondary clear-btn"
                  @click="clearDateFilter"
                >
                  Show All
                </button>
                <span v-if="loading" class="text-muted small">Loading...</span>
              </div>
            </div>

            <!-- Count badge -->
            <div v-if="!loading" class="mb-2" style="flex-shrink: 0;">
              <span class="badge bg-primary" style="font-size: 10px; padding: 3px 8px;">
                {{ sortedViewings.length }} viewing{{ sortedViewings.length !== 1 ? 's' : '' }}
                {{ selectedDate ? 'on this day' : 'total' }}
              </span>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="py-3" style="flex-shrink: 0;">
              <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>

            <!-- Scrollable List -->
            <div v-else class="sidebar-scroll-area">
              <div
                v-if="sortedViewings.length === 0"
                class="text-muted"
                style="font-size: 12px !important;"
              >
                {{ selectedDate ? 'No viewings on this day.' : 'No viewing appointments found.' }}
              </div>

              <div
                v-for="req in sortedViewings.slice(0, 30)"
                :key="req.id"
                class="viewing-card mb-2"
              >
                <div class="d-flex align-items-start gap-2">
                  <div class="viewing-date" style="min-width: 85px !important;">
                    <div class="fw-bold" style="font-size: 12px !important;">{{ req.formatted_date }}</div>
                    <div class="text-muted" style="font-size: 10px !important;">{{ req.formatted_time }}</div>
                  </div>

                  <div class="flex-grow-1">
                    <div class="fw-semibold line-clamp-2" style="font-size: 12px !important;">
                      {{ req.property_title || 'Property' }}
                    </div>

                    <div class="text-muted" v-if="req.sales_person_name" style="font-size: 10px !important; margin-top: 2px !important;">
                      <span class="fw-medium">listing:</span> {{ req.sales_person_name }}
                    </div>
                    <div class="text-muted" v-if="req.request_person_name" style="font-size: 10px !important; margin-top: 2px !important;">
                      <span class="fw-medium">Request By:</span> {{ req.request_person_name }}
                    </div>

                    <div class="mt-1">
                      <span class="badge bg-light text-dark border" style="font-size: 10px !important; padding: 2px 6px !important;">
                        {{ req.status }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="sortedViewings.length > 30" class="text-muted" style="font-size: 10px !important;">
                Showing first 30 items. (sorted by date)
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'

import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'

const loading = ref(true)
const viewings = ref([])
const selectedDate = ref(null) // e.g. "2025-07-15"

const fullCalendar = ref(null)
const currentView = ref('dayGridMonth')

// ─── Helpers ───────────────────────────────────────────────────────────────

const toISODateTime = (fullDatetime) => {
  if (!fullDatetime) return null
  const s = String(fullDatetime).trim()
  if (!s) return null
  if (s.includes('T') && /\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s)) return s
  const dateMatch = s.match(/\d{4}-\d{2}-\d{2}/)
  if (!dateMatch) return null
  const date = dateMatch[0]
  const timeTokens = s.match(/\d{2}:\d{2}(?::\d{2})?/g) || []
  if (!timeTokens.length) return null
  const lastTime = timeTokens[timeTokens.length - 1]
  const timeWithSeconds = /:\d{2}$/.test(lastTime) ? lastTime : `${lastTime}:00`
  return `${date}T${timeWithSeconds}`
}

const pad2 = (n) => String(n).padStart(2, '0')

const isViewingRequest = (r) => String(r.request_type || '').trim().toLowerCase() === 'viewing'

const getViewingStartISO = (r) => {
  const fromFull = toISODateTime(r.full_datetime)
  if (fromFull) return fromFull
  const fd = r.formatted_date
  const ft = r.formatted_time
  if (!fd || !ft) return null
  const d = new Date(`${String(fd).trim()} ${String(ft).trim()}`)
  if (Number.isNaN(d.getTime())) return null
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`
}

const viewingSortTime = (r) => {
  const iso = getViewingStartISO(r)
  if (iso) return new Date(iso).getTime()
  return r.created_at ? new Date(r.created_at).getTime() : 0
}

// ─── Format selected date label ────────────────────────────────────────────

function formatSelectedDate(dateStr) {
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function clearDateFilter() {
  selectedDate.value = null
}

// ─── Computed ──────────────────────────────────────────────────────────────

const normalizedEvents = computed(() => {
  return viewings.value
    .filter((r) => isViewingRequest(r) && getViewingStartISO(r))
    .map((r) => ({
      id: String(r.id),
      title: r.property_title || r.listing?.title || 'Viewing',
      start: getViewingStartISO(r),
      extendedProps: { request: r },
      classNames: ['viewing-event'],
    }))
})

const sortedViewings = computed(() => {
  let arr = [...viewings.value].filter((r) => isViewingRequest(r))

  // Filter by selected date if any
  if (selectedDate.value) {
    arr = arr.filter((r) => {
      const iso = getViewingStartISO(r)
      if (!iso) return false
      return iso.startsWith(selectedDate.value)
    })
  }

  arr.sort((a, b) => viewingSortTime(b) - viewingSortTime(a))

  return arr.map((r) => ({
    ...r,
    property_title: r.listing?.title || r.property_title || 'Property',
    formatted_date: r.formatted_date || '',
    formatted_time: r.formatted_time || '',
    sales_person_name: r.listing?.agent || '',
    request_person_name: r.requested_by?.name || '',
  }))
})

// ─── Calendar ──────────────────────────────────────────────────────────────

const calendarOptions = ref({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  editable: false,
  selectable: false,
  droppable: false,
  allDaySlot: false,
  headerToolbar: false,
  slotMinTime: '06:00:00',
  slotMaxTime: '20:00:00',
  nowIndicator: true,
  events: [],
  datesSet: () => {},

  // Click on a day cell → filter sidebar
  dateClick: (info) => {
    if (selectedDate.value === info.dateStr) {
      // Clicking the same day again clears the filter
      selectedDate.value = null
    } else {
      selectedDate.value = info.dateStr
    }
  },

  // Click on an event → also filter to that day
  eventClick: (info) => {
    const req = info?.event?.extendedProps?.request
    if (!req) return
    const iso = getViewingStartISO(req)
    if (iso) {
      const dateStr = iso.split('T')[0]
      if (selectedDate.value === dateStr) {
        selectedDate.value = null
      } else {
        selectedDate.value = dateStr
      }
    }
  },
})

// ─── Sync events to calendar ───────────────────────────────────────────────

const syncRetryTimer = ref(null)

function syncEventsToCalendar() {
  const apiObj = fullCalendar.value?.getApi?.()
  if (!apiObj) {
    if (syncRetryTimer.value) window.clearTimeout(syncRetryTimer.value)
    syncRetryTimer.value = window.setTimeout(() => syncEventsToCalendar(), 250)
    return
  }
  if (syncRetryTimer.value) {
    window.clearTimeout(syncRetryTimer.value)
    syncRetryTimer.value = null
  }
  apiObj.removeAllEvents()
  normalizedEvents.value.forEach((ev) => apiObj.addEvent(ev))
}

watch(normalizedEvents, () => { nextTick(() => syncEventsToCalendar()) }, { deep: true })

// ─── Calendar nav ──────────────────────────────────────────────────────────

function changeView(view) {
  currentView.value = view
  fullCalendar.value?.getApi?.()?.changeView?.(view)
}
function prev()  { fullCalendar.value?.getApi?.()?.prev?.() }
function next()  { fullCalendar.value?.getApi?.()?.next?.() }
function today() { fullCalendar.value?.getApi?.()?.today?.() }

// ─── Data fetching ─────────────────────────────────────────────────────────

function mapAccessRequestRow(r) {
  return {
    ...r,
    request_type: r.request_type,
    property_title: r.listing?.title || 'Property',
    formatted_date: r.formatted_date,
    formatted_time: r.formatted_time,
  }
}

async function fetchViewings() {
  try {
    loading.value = true
    const [inbound, outbound] = await Promise.all([
      api.get('/listings/access-requests/my-requests'),
      api.get('/listings/access-requests/my-orders'),
    ])
    const merged = []
    const takeRows = (resp) => {
      if (resp?.data?.status && Array.isArray(resp.data.data)) {
        merged.push(...resp.data.data)
      }
    }
    takeRows(inbound)
    takeRows(outbound)
    const byId = new Map()
    for (const r of merged) byId.set(r.id, mapAccessRequestRow(r))
    viewings.value = [...byId.values()]
  } catch {
    viewings.value = []
  } finally {
    loading.value = false
  }
}

const pollingId = ref(null)

onMounted(async () => {
  await fetchViewings()
  nextTick(() => syncEventsToCalendar())
  pollingId.value = window.setInterval(() => fetchViewings(), 60000)
})

onUnmounted(() => {
  if (pollingId.value) window.clearInterval(pollingId.value)
  if (syncRetryTimer.value) window.clearTimeout(syncRetryTimer.value)
})
</script>

<style scoped>
.calendar-title {
  font-size: 20px !important;
  font-weight: 700 !important;
}

.calendar-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.view-buttons {
  display: flex;
  border: 1px solid #487fff;
  border-radius: 6px;
  overflow: hidden;
  height: 32px;
}

.fc-button {
  color: #487fff;
  border: none;
  padding: 0 12px;
  font-size: 15px !important;
  font-weight: 500;
  height: 32px;
  line-height: 32px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
}

.fc-button:hover {
  background-color: rgb(225, 238, 255);
}

.fc-button.active {
  background-color: #487fff;
  color: #fff;
}

.nav-buttons {
  display: flex;
  border: 1px solid #487fff;
  border-radius: 6px;
  overflow: hidden;
  height: 32px;
}

.nav-buttons .fc-button {
  padding: 0 10px;
}

.today-button {
  background-color: #487fff;
  color: #fff;
  border-radius: 6px;
  border: none;
}

.today-button:hover {
  background-color: #2563eb;
}

.viewing-event {
  background: #0ea5e9 !important;
  border-color: #0ea5e9 !important;
}

/* ── Sidebar scroll area ── */
.sidebar-scroll-area {
  flex: 1 1 0;
  min-height: 0;           /* important for flex children to scroll */
  max-height: 600px;       /* fallback max height */
  overflow-y: auto;
  padding-right: 4px;
}

/* thin custom scrollbar */
.sidebar-scroll-area::-webkit-scrollbar {
  width: 4px;
}
.sidebar-scroll-area::-webkit-scrollbar-track {
  background: transparent;
}
.sidebar-scroll-area::-webkit-scrollbar-thumb {
  background: #c5d5ff;
  border-radius: 4px;
}
.sidebar-scroll-area::-webkit-scrollbar-thumb:hover {
  background: #487fff;
}

.viewing-card {
  border: 1px solid rgba(1, 6, 45, 0.08);
  border-radius: 10px;
  padding: 8px 10px;
  background: rgba(245, 248, 255, 0.6);
  transition: background 0.15s ease, box-shadow 0.15s ease;
}

.viewing-card:hover {
  background: rgba(72, 127, 255, 0.06);
  box-shadow: 0 2px 8px rgba(72, 127, 255, 0.1);
}

.viewing-date {
  min-width: 85px;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.p-16 {
  padding: 16px !important;
}

.mb-2 {
  margin-bottom: 8px !important;
}

.clear-btn {
  font-size: 10px !important;
  padding: 2px 8px !important;
  line-height: 1.4;
}
.fc .fc-daygrid-day-frame{
       cursor: pointer;
}
</style>