<template>
  <div class="mk-page">
    <!-- Hero / toolbar -->
    <header class="mk-hero">
      <div class="mk-hero__title">
        <span class="mk-hero__icon"><iconify-icon :icon="heroIcon" /></span>
        <div>
          <h4>{{ heroTitle }}</h4>
          <p>{{ heroHint }}</p>
        </div>
      </div>

      <div class="mk-hero__tools">
        <label class="mk-search">
          <iconify-icon icon="lucide:search" />
          <!-- readonly until focused + autocomplete off: stops the browser autofilling the saved login into search -->
          <input
            v-model="search"
            type="search"
            name="mk-board-query"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            data-lpignore="true"
            data-1p-ignore
            data-form-type="other"
            readonly
            :placeholder="isBoard ? 'Search title, people, notes, links…' : (activeTab === 'bookings' ? 'Search name, phone, email, reference…' : 'Search posts…')"
            aria-label="Search"
            @pointerdown="$event.target.removeAttribute('readonly')"
            @focus="$event.target.removeAttribute('readonly')"
            @blur="$event.target.setAttribute('readonly', '')"
          />
          <button v-if="search" type="button" aria-label="Clear search" @click="search = ''"><iconify-icon icon="lucide:x" /></button>
        </label>

        <template v-if="isBoard">
          <MkSelect v-model="filters.assignee" :options="personOptions" icon="lucide:users" aria-label="Filter by person" searchable highlight />
          <MkSelect v-if="activeTab === 'production'" v-model="filters.type" :options="typeOptions" icon="lucide:shapes" aria-label="Filter by type" highlight />
          <MkSelect v-if="activeTab === 'design'" v-model="filters.deliverable" :options="deliverableOptions" icon="lucide:layout-template" aria-label="Filter by deliverable" highlight />
          <MkSelect v-model="filters.due" :options="dueOptions" icon="lucide:calendar-clock" aria-label="Filter by due date" highlight align="right" />
          <button v-if="canManage" type="button" class="mk-btn mk-btn--ghost" title="Manage stages" @click="stageManagerOpen = true">
            <iconify-icon icon="lucide:columns-3" /> <span class="mk-hide-sm">Stages</span>
          </button>
          <button type="button" class="mk-btn mk-btn--primary" @click="openCreate(null)">
            <iconify-icon icon="lucide:plus" /> New card
          </button>
        </template>

        <template v-else-if="activeTab === 'calendar'">
          <MkSelect v-model="calendarPlatform" :options="platformOptions" icon="lucide:share-2" aria-label="Filter by platform" highlight align="right" />
        </template>

        <!-- Admins: team daily report (popup + PDF) -->
        <button v-if="canManage" type="button" class="mk-btn mk-btn--ghost" title="Daily report" @click="reportOpen = true">
          <iconify-icon icon="lucide:file-bar-chart" /> <span class="mk-hide-sm">Daily report</span>
        </button>
      </div>
    </header>

    <div v-if="isBoard && activeFilterCount" class="mk-active-filters">
      <span>{{ totalCards }} card{{ totalCards === 1 ? '' : 's' }} match</span>
      <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm" @click="clearFilters"><iconify-icon icon="lucide:filter-x" /> Clear filters</button>
    </div>

    <!-- Views (cross-fade + slight lift when switching tabs) -->
    <Transition name="mk-view" mode="out-in">
    <MarketingBoard
      v-if="isBoard"
      :key="activeTab"
      :stages="stages"
      :category="activeTab"
      :loading="boardLoading"
      @move="onMove"
      @open="openTask($event.id)"
      @create="openCreate"
      @add-link="openQuickLink"
    />
    <MarketingCalendar
      v-else-if="activeTab === 'calendar'"
      :search="search"
      :platform="calendarPlatform"
      :reload-key="calendarReloadKey"
      @open-task="openTask"
    />
    <MarketingBookings
      v-else-if="activeTab === 'bookings'"
      :search="search"
      :highlight-id="highlightBookingId"
      @open-task="openTask"
    />
    <MarketingAnalytics v-else-if="activeTab === 'analytics'" />
    </Transition>

    <!-- Overlays -->
    <TaskFormModal
      v-model="formOpen"
      :category="formTask?.category || activeTab"
      :task="formTask"
      :members="members"
      :stages="stages"
      :initial-stage-id="formStageId"
      @saved="onSaved"
    />
    <TaskDrawer
      ref="drawer"
      v-model="drawerOpen"
      :task-id="drawerTaskId"
      :stages="drawerStages"
      :can-manage="canManage"
      @updated="replaceTask"
      @deleted="removeTask"
      @moved="onDrawerMoved"
      @edit="openEdit"
      @schedule="openSchedule"
    />
    <DoneLinkModal v-model="linkOpen" :task="linkTask" :celebrate="linkCelebrate" @saved="onLinkSaved" />
    <SchedulePostModal v-model="scheduleOpen" :task="scheduleTask" @saved="onScheduled" />
    <DailyReportModal v-if="canManage" v-model="reportOpen" />
    <StageManagerModal v-if="canManage && isBoard" v-model="stageManagerOpen" :category="activeTab" :stages="stages" @changed="loadBoard" />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { landCard } from '@/motion/marketingMotion.js'
import { showCrmToast } from '@/utils/crmToast.js'
import { useRoute, useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import '@/components/marketing/marketing.css'
import MarketingBoard from '@/components/marketing/MarketingBoard.vue'
import MarketingCalendar from '@/components/marketing/MarketingCalendar.vue'
import MarketingAnalytics from '@/components/marketing/MarketingAnalytics.vue'
import MarketingBookings from '@/components/marketing/MarketingBookings.vue'
import TaskFormModal from '@/components/marketing/TaskFormModal.vue'
import TaskDrawer from '@/components/marketing/TaskDrawer.vue'
import DoneLinkModal from '@/components/marketing/DoneLinkModal.vue'
import SchedulePostModal from '@/components/marketing/SchedulePostModal.vue'
import StageManagerModal from '@/components/marketing/StageManagerModal.vue'
import MkSelect from '@/components/marketing/shared/MkSelect.vue'
import DailyReportModal from '@/components/marketing/DailyReportModal.vue'
import { MARKETING_TAB_KEY, MARKETING_TAB_IDS } from '@/composables/useLayoutNavigation.js'
import { MARKETING_CATEGORIES, CONTENT_TYPES, DELIVERABLES, PLATFORMS, apiError } from '@/config/marketingConfig.js'

const BOARD_TABS = ['production', 'design', 'content']
const route = useRoute()
const router = useRouter()

function storedTab() {
  try {
    const t = localStorage.getItem(MARKETING_TAB_KEY)
    if (t && MARKETING_TAB_IDS.includes(t)) return t
  } catch {
    /* ignore */
  }
  return 'production'
}

const activeTab = ref(storedTab())
const isBoard = computed(() => BOARD_TABS.includes(activeTab.value))

const members = ref([])
const canManage = ref(false)
const stages = ref([])
const boardLoading = ref(false)
const search = ref('')
const filters = reactive({ assignee: null, type: '', deliverable: '', due: '' })
const calendarPlatform = ref('')
const calendarReloadKey = ref(0)

// Overlays
const formOpen = ref(false)
const formTask = ref(null)
const formStageId = ref(null)
const drawer = ref(null)
const drawerOpen = ref(false)
const drawerTaskId = ref(null)
const drawerStages = ref([])
const linkOpen = ref(false)
const linkTask = ref(null)
const linkCelebrate = ref(true)
const scheduleOpen = ref(false)
const scheduleTask = ref(null)
const stageManagerOpen = ref(false)
const reportOpen = ref(false)
const highlightBookingId = ref(null)

/** Stage lists per board, so the drawer can offer stage moves for cards opened from the calendar. */
const stagesCache = {}

const HERO = {
  bookings: { title: 'Photoshoot Bookings', hint: 'Requests from the public booking page — call, confirm, turn into a card', icon: 'lucide:calendar-check-2', color: '#733E87' },
  calendar: { title: 'Content Calendar', hint: 'What goes out, where and when — tick posts as they go live', icon: 'lucide:calendar-days', color: '#9333ea' },
  analytics: { title: 'Marketing Analytics', hint: 'Throughput, workload, revisions and posting', icon: 'lucide:bar-chart-3', color: '#2563eb' },
}
const heroTitle = computed(() => (isBoard.value ? `${MARKETING_CATEGORIES[activeTab.value].label} board` : HERO[activeTab.value].title))
const heroHint = computed(() => (isBoard.value ? MARKETING_CATEGORIES[activeTab.value].hint : HERO[activeTab.value].hint))
const heroIcon = computed(() => (isBoard.value ? MARKETING_CATEGORIES[activeTab.value].icon : HERO[activeTab.value].icon))

// Filter dropdown options (first option = "no filter")
const personOptions = computed(() => [
  { value: null, label: 'Everyone' },
  ...members.value.map((m) => ({ value: m.id, label: m.name, avatar: m })),
])
const typeOptions = [{ value: '', label: 'All types' }, ...Object.entries(CONTENT_TYPES).map(([value, t]) => ({ value, label: t.label, icon: t.icon }))]
const deliverableOptions = [{ value: '', label: 'All deliverables' }, ...Object.entries(DELIVERABLES).map(([value, d]) => ({ value, label: d.label }))]
const dueOptions = [
  { value: '', label: 'Any due date' },
  { value: 'overdue', label: 'Overdue', icon: 'lucide:alarm-clock-off' },
  { value: 'week', label: 'Due this week', icon: 'lucide:calendar-range' },
]
const platformOptions = [{ value: '', label: 'All platforms' }, ...Object.entries(PLATFORMS).map(([value, p]) => ({ value, label: p.label, icon: p.icon }))]

const totalCards = computed(() => stages.value.reduce((n, s) => n + s.tasks.length, 0))
const activeFilterCount = computed(() => [search.value, filters.assignee, filters.type, filters.deliverable, filters.due].filter(Boolean).length)


// ---------- Data ----------
async function loadMembers() {
  try {
    const { data } = await api.get('/marketing/members')
    members.value = data.members
    canManage.value = !!data.can_manage
  } catch {
    members.value = []
  }
}

let boardSeq = 0
async function loadBoard() {
  if (!isBoard.value) return
  const mine = ++boardSeq
  const category = activeTab.value
  boardLoading.value = true
  try {
    const { data } = await api.get('/marketing/board', {
      params: {
        category,
        search: search.value.trim() || undefined,
        assignee: filters.assignee || undefined,
        type: category === 'production' ? filters.type || undefined : undefined,
        deliverable: category === 'design' ? filters.deliverable || undefined : undefined,
        due: filters.due || undefined,
      },
    })
    if (mine !== boardSeq) return
    stages.value = data.stages
    canManage.value = !!data.can_manage
    stagesCache[category] = data.stages
  } catch (err) {
    if (mine === boardSeq) Swal.fire({ icon: 'error', title: 'Could not load board', text: apiError(err) })
  } finally {
    if (mine === boardSeq) boardLoading.value = false
  }
}

function clearFilters() {
  search.value = ''
  Object.assign(filters, { assignee: null, type: '', deliverable: '', due: '' })
}

// ---------- Tabs (driven by the header, like Deal types) ----------
function setTab(tab) {
  if (!MARKETING_TAB_IDS.includes(tab) || tab === activeTab.value) return
  activeTab.value = tab
}
function onTabChange(e) { setTab(e?.detail) }

watch(activeTab, (tab) => {
  try { localStorage.setItem(MARKETING_TAB_KEY, tab) } catch { /* ignore */ }
  search.value = ''
  Object.assign(filters, { type: '', deliverable: '', due: '' })
  stages.value = []
  loadBoard()
})

let searchTimer = null
watch(search, () => {
  if (!isBoard.value) return
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadBoard, 300)
})
watch(() => ({ ...filters }), loadBoard)

// ---------- Board actions ----------
async function onMove({ task, stage, position }) {
  try {
    const { data } = await api.post(`/marketing/tasks/${task.id}/move`, { stage_id: stage.id, position })
    replaceTask(data.task)
    if (data.suggest_link) openLinkPrompt(data.task, true)
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not move card', text: apiError(err) })
    loadBoard()
  }
}

function replaceTask(task) {
  for (const s of stages.value) {
    const i = s.tasks.findIndex((t) => t.id === task.id)
    if (i !== -1) {
      if (s.id === task.stage_id) s.tasks.splice(i, 1, { ...s.tasks[i], ...task, comments: undefined, histories: undefined, posts: undefined })
      else loadBoard()
      return
    }
  }
}

function removeTask(id) {
  for (const s of stages.value) {
    const i = s.tasks.findIndex((t) => t.id === id)
    if (i !== -1) s.tasks.splice(i, 1)
  }
}

function openCreate(stageId) {
  formTask.value = null
  formStageId.value = stageId
  formOpen.value = true
}

function openEdit(task) {
  formTask.value = task
  formOpen.value = true
}

async function onSaved(task) {
  if (formTask.value) {
    replaceTask(task)
    drawer.value?.refresh()
  } else if (task.category === activeTab.value) {
    await loadBoard()
    // Draw the eye to the new card.
    nextTick(() => {
      const el = document.querySelector(`.mk-card[data-task-id="${task.id}"]`)
      el?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' })
      landCard(el)
    })
  }
}

async function openTask(id, category = null) {
  const cat = category || activeTab.value
  if (!stagesCache[cat]) {
    try {
      const { data } = await api.get('/marketing/board', { params: { category: cat } })
      stagesCache[cat] = data.stages
    } catch {
      stagesCache[cat] = []
    }
  }
  drawerStages.value = (isBoard.value && cat === activeTab.value ? stages.value : stagesCache[cat])
    .map(({ id: sid, name, color, is_done }) => ({ id: sid, name, color, is_done }))
  drawerTaskId.value = id
  drawerOpen.value = true
}

function onDrawerMoved({ task, suggestLink }) {
  loadBoard()
  if (suggestLink) openLinkPrompt(task, true)
}

function openLinkPrompt(task, celebrate) {
  linkTask.value = task
  linkCelebrate.value = celebrate
  linkOpen.value = true
}
function openQuickLink(task) { openLinkPrompt(task, false) }

function onLinkSaved() {
  showCrmToast({
    kind: 'link',
    headline: linkCelebrate.value ? 'Final link added' : 'Link added',
    subtitle: linkTask.value?.title || '',
    chip: MARKETING_CATEGORIES[linkTask.value?.category]?.label || '',
  })
  loadBoard()
  if (drawerOpen.value) drawer.value?.refresh()
}

function openSchedule(task) {
  scheduleTask.value = task
  scheduleOpen.value = true
}

function onScheduled() {
  calendarReloadKey.value++
  if (drawerOpen.value) drawer.value?.refresh()
  if (isBoard.value) loadBoard()
}

/**
 * Deep link from My Day (or anywhere): /marketing?tab=design&task=12 switches to the tab and
 * opens that card. The query is then cleared so a refresh doesn't reopen it.
 */
async function applyRouteQuery() {
  const tab = typeof route.query.tab === 'string' ? route.query.tab : ''
  const task = Number(route.query.task)
  if (!tab && !task) return
  if (MARKETING_TAB_IDS.includes(tab)) setTab(tab)
  // ?tab=bookings&booking=7 (from a booking notification) — flash that booking.
  const booking = Number(route.query.booking)
  if (booking) highlightBookingId.value = booking
  window.dispatchEvent(new CustomEvent('marketing-tab-change', { detail: activeTab.value }))
  if (task) await openTask(task, BOARD_TABS.includes(tab) ? tab : null)
  router.replace({ path: route.path })
}
watch(() => route.query, applyRouteQuery)

// ---------- Live updates from teammates (Pusher: `.marketing.updated` on user.{id}) ----------
let liveChannel = null
let liveTimer = null

function onMarketingLive(e) {
  if (!e?.task_id) return
  // Board: refresh the open board when a card on it changed (debounced, and never mid-drag).
  if (isBoard.value && e.category === activeTab.value) {
    clearTimeout(liveTimer)
    const reload = () => {
      if (document.querySelector('.mk-chosen')) {
        liveTimer = setTimeout(reload, 800)
        return
      }
      loadBoard()
    }
    liveTimer = setTimeout(reload, 400)
  }
  if (activeTab.value === 'calendar' && ['scheduled', 'posted', 'unposted', 'deleted'].includes(e.action)) {
    calendarReloadKey.value++
  }
  // The card someone else changed is open in the drawer: keep it current.
  if (drawerOpen.value && drawerTaskId.value === e.task_id) {
    if (e.action === 'deleted') {
      drawerOpen.value = false
      showCrmToast({ kind: 'deleted', headline: `${e.user_name || 'Someone'} deleted this card`, subtitle: e.title || '' })
    } else {
      drawer.value?.refresh()
    }
  }
}

function subscribeLive() {
  if (liveChannel || !window.Echo) return
  try {
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    if (!user?.id) return
    liveChannel = window.Echo.private(`user.${user.id}`)
    liveChannel.listen('.marketing.updated', onMarketingLive)
  } catch {
    liveChannel = null
  }
}

onMounted(() => {
  // Echo may still be booting (it's lazy-loaded) — subscribe as soon as it's ready.
  subscribeLive()
  window.addEventListener('echo-ready', subscribeLive)
  window.addEventListener('marketing-tab-change', onTabChange)
  const fromLink = !!(route.query.tab || route.query.task)
  if (fromLink) applyRouteQuery()
  // Sync the header tabs with the tab this page restored.
  else window.dispatchEvent(new CustomEvent('marketing-tab-change', { detail: activeTab.value }))
  loadMembers()
  loadBoard()
})
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  clearTimeout(liveTimer)
  window.removeEventListener('echo-ready', subscribeLive)
  // Remove only our handler — the same user channel is shared with leads/deals/notifications.
  liveChannel?.stopListening('.marketing.updated', onMarketingLive)
  liveChannel = null
  window.removeEventListener('marketing-tab-change', onTabChange)
})
</script>

<style scoped>
.mk-page { padding: 4px 2px 24px; color: var(--text-primary-light); }

/* Toolbar: plain neutral surface, brand color only as a small accent. */
.mk-hero {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  margin-bottom: 14px;
  border-radius: 16px;
  background: var(--white);
  border: 1px solid var(--border-color);
}
.mk-hero__title { display: flex; align-items: center; gap: 10px; min-width: 0; }
.mk-hero__icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 18px !important;
  color: var(--color-primary);
  background: rgba(var(--color-primary-rgb), 0.08);
  flex-shrink: 0;
}
.mk-hero h4 { margin: 0; font-size: 14px !important; font-weight: 700 !important; color: var(--text-primary-light); }
.mk-hero p { margin: 1px 0 0; font-size: 12px !important; color: var(--text-secondary-light); }
.mk-hero__tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }

.mk-search {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 260px;
  min-height: 38px;
  padding: 0 12px;
  margin: 0;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  background: var(--neutral-50);
  color: var(--text-secondary-light);
  transition: border-color 0.15s, background 0.15s;
}
.mk-search:focus-within { border-color: var(--color-primary); background: var(--white); }
.mk-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-size: 14px !important; color: var(--text-primary-light); }
.mk-search input::-webkit-search-cancel-button { display: none; }
.mk-search button { border: 0; background: transparent; color: var(--text-secondary-light); display: inline-flex; padding: 0; }
.mk-filter {
  width: auto;
  min-height: 38px;
  padding: 6px 30px 6px 12px;
  border-radius: 10px;
  font-size: 13px !important;
  font-weight: 500 !important;
  background-color: var(--neutral-50);
}
.mk-filter:focus { box-shadow: none; }
.mk-hero :deep(.mk-btn) { min-height: 38px; border-radius: 10px; }
.mk-hero :deep(.mk-btn--primary) { background: var(--color-primary); box-shadow: none; }
.mk-hero :deep(.mk-btn--primary:not(:disabled):hover) { background: var(--color-primary-hover); }
.mk-hero :deep(.mk-btn--primary svg) { transition: transform 0.35s cubic-bezier(.2,.8,.2,1.6); }
.mk-hero :deep(.mk-btn--primary:hover svg) { transform: rotate(90deg) scale(1.15); }
.mk-hero__icon { transition: transform 0.4s cubic-bezier(.2,.8,.2,1.6); }
.mk-hero:hover .mk-hero__icon { transform: rotate(-8deg) scale(1.08); }
@media (prefers-reduced-motion: reduce) {
  .mk-hero :deep(.mk-btn--primary:hover svg), .mk-hero:hover .mk-hero__icon { transform: none; }
}

.mk-view-enter-active, .mk-view-leave-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.mk-view-enter-from { opacity: 0; transform: translateY(10px); }
.mk-view-leave-to { opacity: 0; transform: translateY(-6px); }
@media (prefers-reduced-motion: reduce) {
  .mk-view-enter-active, .mk-view-leave-active { transition: none; }
}

.mk-active-filters { display: flex; align-items: center; gap: 10px; margin: -4px 0 12px; font-size: 13px !important; font-weight: 600 !important; color: var(--text-secondary-light); }

@media (max-width: 768px) {
  .mk-hero { padding: 14px; border-radius: 18px; }
  .mk-hero__tools { width: 100%; }
  .mk-search { min-width: 0; flex: 1 1 100%; }
  .mk-filter { flex: 1 1 45%; }
  .mk-hide-sm { display: none; }
}
</style>
