<template>
  <div class="mk-cal">
    <div class="mk-cal__bar">
      <div class="mk-cal__nav">
        <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" title="Previous 2 weeks" @click="shift(-14)"><iconify-icon icon="lucide:chevron-left" /></button>
        <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm" @click="goToday">Today</button>
        <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" title="Next 2 weeks" @click="shift(14)"><iconify-icon icon="lucide:chevron-right" /></button>
        <h5 class="mk-cal__range">{{ rangeLabel }}</h5>
      </div>
      <div class="mk-cal__stats">
        <span class="mk-pill" :style="{ '--pill': '#9333ea' }"><iconify-icon icon="lucide:calendar" /> {{ posts.length }} scheduled</span>
        <span class="mk-pill" :style="{ '--pill': '#16a34a' }"><iconify-icon icon="lucide:check-circle-2" /> {{ postedCount }} posted</span>
      </div>
    </div>

    <div v-if="loading && !posts.length" class="mk-empty"><iconify-icon icon="lucide:loader-2" class="mk-spin" /></div>

    <!-- Desktop grid: days × platforms -->
    <div v-else-if="!isMobile" class="mk-cal__scroller" :class="{ 'is-loading': loading }">
      <div class="mk-cal__grid" :style="{ gridTemplateColumns: `128px repeat(${visiblePlatforms.length}, minmax(160px, 1fr))` }">
        <div class="mk-cal__corner">Date</div>
        <div v-for="p in visiblePlatforms" :key="p.key" class="mk-cal__phead" :style="{ '--p': p.color }">
          <iconify-icon :icon="p.icon" /> {{ p.label }}
        </div>

        <template v-for="(day, i) in days" :key="day.iso">
          <div v-if="i % 7 === 0" class="mk-cal__week" :style="{ gridColumn: `1 / span ${visiblePlatforms.length + 1}` }">
            Week of {{ formatDate(day.iso, { day: '2-digit', month: 'short' }) }}
          </div>
          <div class="mk-cal__day" :class="{ today: day.iso === todayIso, weekend: day.weekend }">
            <b>{{ formatDate(day.iso, { day: '2-digit', month: '2-digit', year: '2-digit' }).replace(/\//g, '.') }}</b>
            <small>{{ formatDate(day.iso, { weekday: 'long' }) }}</small>
          </div>
          <div
            v-for="p in visiblePlatforms"
            :key="`${day.iso}-${p.key}`"
            class="mk-cal__cell"
            :class="{ today: day.iso === todayIso, 'is-over': dropKey === cellKey(day.iso, p.key) }"
            @dragover.prevent="onCellDragOver(day.iso, p.key)"
            @drop.prevent="onDrop(day.iso, p.key)"
          >
            <div
              v-for="post in cell(day.iso, p.key)"
              :key="post.id"
              class="mk-post"
              :class="{ posted: post.is_posted, 'is-dragging': draggingId === post.id }"
              :style="{ '--p': p.color }"
              draggable="true"
              title="Drag to another day or column"
              @dragstart="onDragStart($event, post)"
              @dragend="onDragEnd"
            >
              <button type="button" class="mk-post__check" draggable="false" :title="post.is_posted ? `Posted by ${post.posted_by || ''}` : 'Mark as posted'" @mousedown.stop @click="togglePosted(post)">
                <iconify-icon :icon="post.is_posted ? 'lucide:check' : 'lucide:circle'" />
              </button>
              <a v-if="post.link" class="mk-post__title" draggable="false" :href="post.link.url" target="_blank" rel="noopener" :title="post.title">
                <iconify-icon :icon="LINK_KINDS[post.link.kind]?.icon || 'lucide:link'" /> {{ post.title }}
              </a>
              <span v-else class="mk-post__title" :title="post.title">{{ post.title }}</span>
              <button v-if="post.task_id" type="button" class="mk-post__btn" draggable="false" title="Open card" @mousedown.stop @click="$emit('open-task', post.task_id, post.task_category)"><iconify-icon icon="lucide:external-link" /></button>
              <button type="button" class="mk-post__btn mk-post__btn--del" draggable="false" title="Remove" @mousedown.stop @click="removePost(post)"><iconify-icon icon="lucide:x" /></button>
            </div>
            <button type="button" class="mk-cal__add" :title="`Add to ${p.label}`" @click="openSchedule(day.iso, p.key)">
              <iconify-icon icon="lucide:plus" />
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- Mobile: day list -->
    <div v-else class="mk-cal__list">
      <section
        v-for="day in days"
        :key="day.iso"
        class="mk-cal__lday"
        :class="{ today: day.iso === todayIso, 'is-over': dropKey === day.iso }"
        @dragover.prevent="onDayDragOver(day.iso)"
        @drop.prevent="onDropDay(day.iso)"
      >
        <header>
          <b>{{ formatDate(day.iso, { weekday: 'short', day: '2-digit', month: 'short' }) }}</b>
          <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" @click="openSchedule(day.iso, '')"><iconify-icon icon="lucide:plus" /></button>
        </header>
        <div
          v-for="post in dayPosts(day.iso)"
          :key="post.id"
          class="mk-post"
          :class="{ posted: post.is_posted, 'is-dragging': draggingId === post.id }"
          :style="{ '--p': PLATFORMS[post.platform]?.color }"
          draggable="true"
          title="Drag to another day"
          @dragstart="onDragStart($event, post)"
          @dragend="onDragEnd"
        >
          <button type="button" class="mk-post__check" draggable="false" @mousedown.stop @click="togglePosted(post)"><iconify-icon :icon="post.is_posted ? 'lucide:check' : 'lucide:circle'" /></button>
          <iconify-icon :icon="PLATFORMS[post.platform]?.icon" class="mk-post__platform" />
          <a v-if="post.link" class="mk-post__title" draggable="false" :href="post.link.url" target="_blank" rel="noopener">
            <iconify-icon :icon="LINK_KINDS[post.link.kind]?.icon || 'lucide:link'" /> {{ post.title }}
          </a>
          <span v-else class="mk-post__title">{{ post.title }}</span>
          <button v-if="post.task_id" type="button" class="mk-post__btn" draggable="false" @mousedown.stop @click="$emit('open-task', post.task_id, post.task_category)"><iconify-icon icon="lucide:external-link" /></button>
        </div>
        <p v-if="!dayPosts(day.iso).length" class="mk-cal__none">Nothing scheduled</p>
      </section>
    </div>

    <SchedulePostModal v-model="scheduleOpen" :date="scheduleDate" :platform="schedulePlatform" @saved="load" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import SchedulePostModal from './SchedulePostModal.vue'
import { PLATFORMS, LINK_KINDS, formatDate, isoDate, apiError } from '@/config/marketingConfig.js'

const props = defineProps({
  search: { type: String, default: '' },
  platform: { type: String, default: '' },
  /** Bumped by the parent to force a reload (e.g. after scheduling from a card). */
  reloadKey: { type: Number, default: 0 },
})
defineEmits(['open-task'])

function mondayOf(d) {
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const day = (x.getDay() + 6) % 7
  x.setDate(x.getDate() - day)
  return x
}

const start = ref(mondayOf(new Date()))
const posts = ref([])
const loading = ref(false)
const isMobile = ref(window.innerWidth <= 768)
const scheduleOpen = ref(false)
const scheduleDate = ref('')
const schedulePlatform = ref('')
const todayIso = isoDate(new Date())
const draggingId = ref(null)
const dropKey = ref('')
/** Survives dragend so the drop handler still knows which post moved. */
let activeDragId = null

const days = computed(() => Array.from({ length: 14 }, (_, i) => {
  const d = new Date(start.value)
  d.setDate(d.getDate() + i)
  return { iso: isoDate(d), weekend: d.getDay() === 0 || d.getDay() === 6 }
}))
const rangeLabel = computed(() => `${formatDate(days.value[0].iso, { day: '2-digit', month: 'short' })} – ${formatDate(days.value[13].iso, { day: '2-digit', month: 'short', year: 'numeric' })}`)
const visiblePlatforms = computed(() => Object.entries(PLATFORMS)
  .filter(([key]) => !props.platform || key === props.platform)
  .map(([key, p]) => ({ key, ...p })))
const postedCount = computed(() => posts.value.filter((p) => p.is_posted).length)

const byDayPlatform = computed(() => {
  const map = {}
  for (const p of posts.value) {
    const k = `${p.scheduled_date}|${p.platform}`
    ;(map[k] ||= []).push(p)
  }
  return map
})
const cellKey = (iso, platform) => `${iso}|${platform}`
const cell = (iso, platform) => byDayPlatform.value[cellKey(iso, platform)] || []
const dayPosts = (iso) => posts.value.filter((p) => p.scheduled_date === iso)

function onDragStart(e, post) {
  if (e.target.closest('button')) {
    e.preventDefault()
    return
  }
  activeDragId = post.id
  draggingId.value = post.id
  dropKey.value = ''
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(post.id))
}
function clearDrag() {
  draggingId.value = null
  dropKey.value = ''
}
function onDragEnd() {
  clearDrag()
  const id = activeDragId
  requestAnimationFrame(() => {
    if (activeDragId === id) activeDragId = null
  })
}
function draggedPost() {
  const id = activeDragId
  activeDragId = null
  clearDrag()
  return posts.value.find((p) => p.id === id) || null
}
function onCellDragOver(iso, platform) {
  if (!activeDragId) return
  const key = cellKey(iso, platform)
  if (dropKey.value !== key) dropKey.value = key
}
function onDayDragOver(iso) {
  if (!activeDragId) return
  if (dropKey.value !== iso) dropKey.value = iso
}
function onDrop(iso, platform) {
  const post = draggedPost()
  if (post) movePost(post, iso, platform)
}
function onDropDay(iso) {
  const post = draggedPost()
  if (post) movePost(post, iso, post.platform)
}

async function movePost(post, iso, platform) {
  if (post.scheduled_date === iso && post.platform === platform) return
  const prev = { scheduled_date: post.scheduled_date, platform: post.platform }
  post.scheduled_date = iso
  post.platform = platform
  try {
    const { data } = await api.put(`/marketing/calendar/${post.id}`, { scheduled_date: iso, platform })
    Object.assign(post, data.post)
  } catch (err) {
    post.scheduled_date = prev.scheduled_date
    post.platform = prev.platform
    Swal.fire({ icon: 'error', title: 'Could not move', text: apiError(err) })
  }
}

let seq = 0
async function load() {
  const mine = ++seq
  loading.value = true
  try {
    const { data } = await api.get('/marketing/calendar', {
      params: { from: days.value[0].iso, to: days.value[13].iso, search: props.search || undefined, platform: props.platform || undefined },
    })
    if (mine === seq) posts.value = data.posts
  } catch (err) {
    if (mine === seq) Swal.fire({ icon: 'error', title: 'Could not load calendar', text: apiError(err) })
  } finally {
    if (mine === seq) loading.value = false
  }
}

function shift(n) {
  const d = new Date(start.value)
  d.setDate(d.getDate() + n)
  start.value = d
}
function goToday() { start.value = mondayOf(new Date()) }

function openSchedule(iso, platform) {
  scheduleDate.value = iso
  schedulePlatform.value = platform
  scheduleOpen.value = true
}

async function togglePosted(post) {
  const next = !post.is_posted
  post.is_posted = next
  try {
    const { data } = await api.post(`/marketing/calendar/${post.id}/posted`, { is_posted: next })
    Object.assign(post, data.post)
  } catch (err) {
    post.is_posted = !next
    Swal.fire({ icon: 'error', title: 'Could not update', text: apiError(err) })
  }
}

async function removePost(post) {
  const res = await Swal.fire({ icon: 'warning', title: 'Remove from calendar?', text: post.title, showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#dc2626' })
  if (!res.isConfirmed) return
  try {
    await api.delete(`/marketing/calendar/${post.id}`)
    posts.value = posts.value.filter((p) => p.id !== post.id)
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not remove', text: apiError(err) })
  }
}

let searchTimer = null
watch(() => props.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 300)
})
watch(() => [start.value, props.platform, props.reloadKey], load)

function onResize() { isMobile.value = window.innerWidth <= 768 }
onMounted(() => {
  load()
  window.addEventListener('resize', onResize)
})
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  window.removeEventListener('resize', onResize)
})
</script>

<style scoped>
.mk-cal__bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.mk-cal__nav { display: flex; align-items: center; gap: 6px; }
.mk-cal__range { margin: 0 0 0 8px; font-size: 14px !important; font-weight: 700 !important; color: var(--text-primary-light); }
.mk-cal__stats { display: flex; gap: 6px; }

.mk-cal__scroller {
  overflow: auto;
  max-height: calc(100vh - 250px);
  border-radius: 18px;
  border: 1px solid var(--border-color);
  background: var(--white);
  transition: opacity 0.2s;
}
.mk-cal__scroller.is-loading { opacity: 0.6; }
.mk-cal__grid { display: grid; min-width: max-content; }
.mk-cal__corner, .mk-cal__phead {
  position: sticky;
  top: 0;
  z-index: 3;
  padding: 12px;
  font-size: 13px !important;
  font-weight: 800 !important;
  background: var(--white);
  border-bottom: 2px solid var(--border-color);
}
.mk-cal__corner { left: 0; z-index: 4; color: var(--text-secondary-light); }
.mk-cal__phead {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--p);
  border-bottom-color: var(--p);
  background: linear-gradient(180deg, color-mix(in srgb, var(--p) 10%, var(--white)), var(--white));
}
.mk-cal__week {
  padding: 6px 12px;
  font-size: 12px !important;
  font-weight: 800 !important;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #fff;
  background: var(--gradient-primary);
}
.mk-cal__day {
  position: sticky;
  left: 0;
  z-index: 2;
  padding: 10px 12px;
  background: var(--white);
  border-bottom: 1px solid var(--border-color);
  border-right: 1px solid var(--border-color);
}
.mk-cal__day b { display: block; font-size: 14px !important; color: var(--text-primary-light); }
.mk-cal__day small { font-size: 12px !important; color: var(--text-secondary-light); }
.mk-cal__day.weekend { background: var(--neutral-50); }
.mk-cal__day.today { box-shadow: inset 4px 0 0 var(--color-primary); }
.mk-cal__day.today b { color: var(--color-primary); }
.mk-cal__cell {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-height: 62px;
  padding: 7px 7px 26px;
  border-bottom: 1px solid var(--border-color);
  border-right: 1px solid var(--border-color);
}
.mk-cal__cell.today { background: rgba(var(--color-primary-rgb), 0.04); }
.mk-cal__cell.is-over {
  background: color-mix(in srgb, var(--color-primary) 14%, var(--white));
  box-shadow: inset 0 0 0 2px var(--color-primary);
}
.mk-cal__add {
  position: absolute;
  left: 7px;
  bottom: 5px;
  width: 22px;
  height: 18px;
  border: 0;
  border-radius: 6px;
  display: grid;
  place-items: center;
  font-size: 13px !important;
  color: var(--text-secondary-light);
  background: transparent;
  opacity: 0;
  transition: opacity 0.15s, background 0.15s;
}
.mk-cal__cell:hover .mk-cal__add { opacity: 1; }
.mk-cal__add:hover { color: #fff; background: var(--color-primary); }

.mk-post {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 5px;
  border-radius: 9px;
  font-size: 12px !important;
  font-weight: 600 !important;
  background: color-mix(in srgb, var(--p) 10%, var(--white));
  border: 1px solid color-mix(in srgb, var(--p) 25%, transparent);
  cursor: grab;
  transition: all 0.15s;
}
.mk-post:active { cursor: grabbing; }
.mk-post.is-dragging { opacity: 0.4; }
.mk-post.posted { background: color-mix(in srgb, #16a34a 10%, var(--white)); border-color: color-mix(in srgb, #16a34a 35%, transparent); }
.mk-post.posted .mk-post__title { text-decoration: line-through; text-decoration-color: color-mix(in srgb, #16a34a 60%, transparent); }
.mk-post__check {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  border: 1.5px solid color-mix(in srgb, var(--p) 55%, transparent);
  display: grid;
  place-items: center;
  font-size: 12px !important;
  color: transparent;
  background: var(--white);
  flex-shrink: 0;
}
.mk-post.posted .mk-post__check { color: #fff; background: #16a34a; border-color: #16a34a; animation: mk-pop 0.35s cubic-bezier(.2,.8,.2,1.5); }
@keyframes mk-pop {
  0% { transform: scale(0.6); }
  100% { transform: scale(1); }
}
@media (prefers-reduced-motion: reduce) {
  .mk-post.posted .mk-post__check { animation: none; }
}
.mk-post__platform { color: var(--p); flex-shrink: 0; }
.mk-post__title {
  flex: 1;
  min-width: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: var(--text-primary-light);
  text-decoration: none;
}
a.mk-post__title:hover { color: var(--color-primary); }
.mk-post__btn {
  border: 0;
  background: transparent;
  padding: 0 2px;
  font-size: 12px !important;
  color: var(--text-secondary-light);
  display: inline-flex;
  opacity: 0;
  transition: opacity 0.15s;
}
.mk-post:hover .mk-post__btn { opacity: 1; }
.mk-post__btn--del:hover { color: #dc2626; }
@media (hover: none) { .mk-post__btn, .mk-cal__add { opacity: 1; } }

.mk-cal__list { display: flex; flex-direction: column; gap: 10px; }
.mk-cal__lday { padding: 12px; border-radius: 16px; background: var(--white); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 6px; }
.mk-cal__lday.today { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-focus-ring); }
.mk-cal__lday.is-over { box-shadow: inset 0 0 0 2px var(--color-primary); background: color-mix(in srgb, var(--color-primary) 8%, var(--white)); }
.mk-cal__lday header { display: flex; align-items: center; justify-content: space-between; }
.mk-cal__lday .mk-post { font-size: 13px !important; padding: 7px 8px; }
.mk-cal__lday .mk-post__btn { opacity: 1; }
.mk-cal__none { margin: 0; font-size: 12px !important; color: var(--text-secondary-light); }
</style>
