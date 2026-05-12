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

      <div class="col-xxl-3 col-lg-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="mb-0">Sorted by Date</h5>
              <span v-if="loading" class="text-muted small">Loading...</span>
            </div>

            <div v-if="loading" class="py-3">
              <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>

            <div v-else>
              <div
                v-if="sortedViewings.length === 0"
                class="text-muted"
                style="font-size: 13px;"
              >
                No viewing appointments found.
              </div>

              <div
                v-for="req in sortedViewings.slice(0, 30)"
                :key="req.id"
                class="viewing-card mb-3"
              >
                <div class="d-flex align-items-start gap-2">
                  <div class="viewing-date">
                    <div class="fw-bold">{{ req.formatted_date }}</div>
                    <div class="text-muted small">{{ req.formatted_time }}</div>
                  </div>

                  <div class="flex-grow-1">
                    <div class="fw-semibold line-clamp-2">
                      {{ req.property_title || 'Property' }}
                    </div>
                    <div class="text-muted small line-clamp-1">
                      {{ req.requested_by?.name || 'Requester' }}
                    </div>

                    <div class="mt-2">
                      <span class="badge bg-light text-dark border">
                        {{ req.status }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="sortedViewings.length > 30" class="text-muted small">
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

const fullCalendar = ref(null)
const currentView = ref('dayGridMonth')

const toISODateTime = (fullDatetime) => {
  if (!fullDatetime) return null
  const s = String(fullDatetime).trim()
  if (!s) return null

  // If it is already ISO
  if (s.includes('T') && /\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s)) return s

  // Backend may build: "YYYY-MM-DD 1970-01-01 HH:mm:ss" (Carbon __toString)
  const dateMatch = s.match(/\d{4}-\d{2}-\d{2}/)
  if (!dateMatch) return null
  const date = dateMatch[0]

  // Take the *last* time-like token found in the string.
  const timeTokens = s.match(/\d{2}:\d{2}(?::\d{2})?/g) || []
  if (!timeTokens.length) return null
  const lastTime = timeTokens[timeTokens.length - 1] // e.g. "14:30" or "14:30:00"

  // Normalize to ISO seconds
  const timeWithSeconds = /:\d{2}$/.test(lastTime) ? lastTime : `${lastTime}:00`
  return `${date}T${timeWithSeconds}`
}

const pad2 = (n) => String(n).padStart(2, '0')

/** API uses `request_type: 'viewing'`; tolerate casing / whitespace. */
const isViewingRequest = (r) => String(r.request_type || '').trim().toLowerCase() === 'viewing'

/**
 * Prefer normalized `full_datetime`; fall back to `formatted_date` + `formatted_time`
 * (same fields as My Requests) so calendar events still render when full_datetime was null.
 */
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
  const c = r.created_at ? new Date(r.created_at).getTime() : 0
  return c
}

const normalizedEvents = computed(() => {
  return viewings.value
    .filter((r) => isViewingRequest(r) && getViewingStartISO(r))
    .map((r) => ({
      id: String(r.id),
      title: r.property_title || r.listing?.title || 'Viewing',
      start: getViewingStartISO(r),
      extendedProps: {
        request: r,
      },
      classNames: ['viewing-event'],
    }))
})

const sortedViewings = computed(() => {
  const arr = [...viewings.value].filter((r) => isViewingRequest(r))
  arr.sort((a, b) => viewingSortTime(b) - viewingSortTime(a))
  return arr.map((r) => ({
    ...r,
    property_title: r.listing?.title || r.property_title || 'Property',
    formatted_date: r.formatted_date || r.formatted_date?.value || '',
    formatted_time: r.formatted_time || '',
  }))
})

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
  eventClick: (info) => {
    // Keep it simple: clicking highlights nothing disruptive.
    const req = info?.event?.extendedProps?.request
    if (!req) return
    // You can replace this alert with a modal if you want.
    // eslint-disable-next-line no-alert
    // alert(`${req.property_title || 'Property'}\\n${req.formatted_date} ${req.formatted_time}`)
  },
})

const syncRetryTimer = ref(null)

function syncEventsToCalendar() {
  const apiObj = fullCalendar.value?.getApi?.()
  if (!apiObj) {
    if (syncRetryTimer.value) window.clearTimeout(syncRetryTimer.value)
    syncRetryTimer.value = window.setTimeout(() => {
      syncEventsToCalendar()
    }, 250)
    return
  }
  if (syncRetryTimer.value) {
    window.clearTimeout(syncRetryTimer.value)
    syncRetryTimer.value = null
  }

  apiObj.removeAllEvents()
  normalizedEvents.value.forEach((ev) => apiObj.addEvent(ev))
}

watch(
  normalizedEvents,
  () => {
    nextTick(() => syncEventsToCalendar())
  },
  { deep: true },
)

function changeView(view) {
  currentView.value = view
  fullCalendar.value?.getApi?.()?.changeView?.(view)
}

function prev() {
  fullCalendar.value?.getApi?.()?.prev?.()
}

function next() {
  fullCalendar.value?.getApi?.()?.next?.()
}

function today() {
  fullCalendar.value?.getApi?.()?.today?.()
}

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
    for (const r of merged) {
      byId.set(r.id, mapAccessRequestRow(r))
    }

    viewings.value = [...byId.values()]
  } catch {
    viewings.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchViewings()
  // Ensure events are drawn after FullCalendar mounts.
  nextTick(() => syncEventsToCalendar())
  // Keep calendar in sync if user creates a new viewing while staying on this page.
  // (Light polling: backend already has realtime elsewhere, but this keeps it simple/reliable.)
  pollingId.value = window.setInterval(() => {
    fetchViewings()
  }, 60000)
})

const pollingId = ref(null)
onUnmounted(() => {
  if (pollingId.value) window.clearInterval(pollingId.value)
  if (syncRetryTimer.value) window.clearTimeout(syncRetryTimer.value)
})
</script>

<style scoped>
.calendar-title {
  font-size: 23px !important;
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
  font-size: 15px;
  font-weight: 500;
  height: 32px;
  line-height: 32px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
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

.viewing-card {
  border: 1px solid rgba(1, 6, 45, 0.08);
  border-radius: 14px;
  padding: 10px 12px;
  background: rgba(245, 248, 255, 0.6);
}

.viewing-date {
  min-width: 110px;
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
</style>

