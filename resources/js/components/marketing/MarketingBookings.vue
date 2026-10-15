<template>
  <div class="mk-bk">
    <div class="mk-bk__bar">
      <!-- Segmented status tabs with a pill that slides behind the active one -->
      <div ref="tabsEl" class="sg" role="tablist" aria-label="Booking status">
        <span class="sg__pill" :style="pillStyle" aria-hidden="true" />
        <button
          v-for="s in STATUS_TABS"
          :key="s.key"
          :ref="(el) => (tabEls[s.key] = el)"
          type="button"
          role="tab"
          class="sg__tab"
          :aria-selected="status === s.key"
          :class="{ active: status === s.key, 'has-new': s.key === 'new' && counts.new > 0 }"
          :style="{ '--c': s.color }"
          @click="status = s.key"
        >
          <span class="sg__icon"><iconify-icon :icon="s.icon" /></span>
          <span class="sg__label">{{ s.label }}</span>
          <b class="sg__count">{{ counts[s.key] ?? 0 }}</b>
        </button>
      </div>
    </div>

    <div v-if="loading && !bookings.length" class="mk-empty"><iconify-icon icon="lucide:loader-2" class="mk-spin" /></div>

    <div v-else-if="!bookings.length" class="mk-bk__empty">
      <span class="mk-bk__empty-icon"><iconify-icon icon="lucide:calendar-check-2" /></span>
      <b>{{ status === 'all' && !search ? 'No bookings yet' : 'Nothing here' }}</b>
      <p v-if="status === 'all' && !search">Colleagues request shoots from <b>Photo Shoots</b> in the sidebar — new requests appear here and the team gets notified.</p>
    </div>

    <div v-else class="mk-bk__list" :class="{ 'is-loading': loading }">
      <!-- Each booking is a "ticket": date stub · perforation · details -->
      <article
        v-for="b in bookings"
        :key="b.id"
        class="tk"
        :class="[`is-${b.status}`, { 'is-flash': b.id === highlightId || b.id === flashId, 'is-past': b.is_past && b.status !== 'confirmed' }]"
      >
        <div class="tk__stub">
          <span class="tk__wd">{{ weekday(b.date) }}</span>
          <span class="tk__day">{{ dayNum(b.date) }}</span>
          <span class="tk__mon">{{ monShort(b.date) }}</span>
          <span class="tk__time"><iconify-icon icon="lucide:clock-3" /> {{ b.time_label }}</span>
          <span class="tk__rel">{{ relative(b) }}</span>
        </div>

        <div class="tk__main">
          <header class="tk__head">
            <span class="tk__avatar">
              <img v-if="b.requester?.avatar_url" :src="b.requester.avatar_url" :alt="b.name" @error="(e) => (e.target.style.display = 'none')" />
              <span>{{ initials(b.name) }}</span>
            </span>
            <div class="tk__who">
              <b>{{ b.name }}</b>
              <small>{{ b.reference }} · requested {{ timeAgo(b.created_at) }}</small>
            </div>
            <span class="tk__status"><span class="tk__status-dot" /> {{ STATUS_LABEL[b.status] }}</span>
          </header>

          <!-- Where & what -->
            <div v-if="b.location || b.units?.length" class="tk__place">
              <iconify-icon icon="lucide:map-pin" />
              <b>{{ b.location || 'Location not set' }}</b>
            </div>
            <div v-if="b.units?.length" class="tk__units">
              <span v-for="(u, i) in b.units" :key="i" class="tk__tag">
                <iconify-icon icon="lucide:door-open" /> {{ u.unit_number }}
                <template v-if="u.bedrooms_label"> · <iconify-icon icon="lucide:bed-double" /> {{ u.bedrooms_label }}</template>
              </span>
            </div>

          <div class="tk__chips">
            <span class="tk__chip tk__chip--type"><iconify-icon :icon="TYPE_ICONS[b.shoot_type]" /> {{ b.shoot_type_label }}</span>
            <template v-if="b.phone">
              <a class="tk__chip" :href="`tel:${b.phone.replace(/\s+/g, '')}`" title="Call"><iconify-icon icon="lucide:phone" /> {{ b.phone }}</a>
              <button type="button" class="tk__chip tk__chip--btn" title="Copy phone" @click="copyText(b.phone, 'Phone copied')">
                <iconify-icon icon="lucide:copy" />
              </button>
            </template>
          </div>
          <a v-if="b.email" class="tk__mail" :href="`mailto:${b.email}`" title="Email">
            <iconify-icon icon="lucide:mail" /> {{ b.email }}
          </a>

          <blockquote v-if="b.notes" class="tk__notes">
            <iconify-icon icon="lucide:quote" />
            <span>{{ b.notes }}</span>
          </blockquote>

          <div class="tk__team">
            <iconify-icon icon="lucide:sticky-note" />
            <input
              :value="b.internal_note || ''"
              maxlength="2000"
              placeholder="Add a team note — only the team sees this"
              @change="save(b, { internal_note: $event.target.value })"
            />
          </div>

          <footer class="tk__foot">
            <small v-if="b.handled_by" class="tk__handled">
              <iconify-icon icon="lucide:user-check" /> {{ STATUS_LABEL[b.status] }} by {{ b.handled_by }} · {{ timeAgo(b.status_changed_at) }}
            </small>
            <span v-else class="tk__handled tk__handled--new"><iconify-icon icon="lucide:bell-ring" /> Waiting for a call back</span>

            <div class="tk__actions">
              <!-- تغيير الحالة: الأدمن والفريق -->
              <MkSelect
                :model-value="b.status"
                :options="statusOptions(b)"
                aria-label="Booking status"
                align="right"
                @change="(v) => save(b, { status: v })"
              />

              <!-- الأدمن بس: قبول + كارد في خطوة واحدة -->
              <button v-if="canManage && !b.task && b.status !== 'cancelled'" type="button" class="mk-btn mk-btn--primary mk-btn--sm" :disabled="busyId === b.id" @click="convert(b)">
                <iconify-icon icon="lucide:badge-check" /> Accept &amp; create card
              </button>

              <button v-if="b.task" type="button" class="mk-btn mk-btn--ghost mk-btn--sm" @click="$emit('open-task', b.task.id, b.task.category)">
                <iconify-icon icon="lucide:external-link" /> Open card
              </button>

              <!-- الأدمن بس -->
              <button v-if="canManage" type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon tk__del" title="Delete booking" @click="remove(b)">
                <iconify-icon icon="lucide:trash-2" />
              </button>
            </div>
          </footer>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkSelect from './shared/MkSelect.vue'
import { showCrmToast } from '@/utils/crmToast.js'
import { usePhotoBookingLive } from '@/composables/usePhotoBookingLive.js'
import { apiError, timeAgo } from '@/config/marketingConfig.js'

const props = defineProps({
  search: { type: String, default: '' },
  highlightId: { type: Number, default: null },
})
const emit = defineEmits(['open-task'])

const STATUS_LABEL = { new: 'New', contacted: 'Contacted', confirmed: 'Confirmed', cancelled: 'Cancelled' }
const STATUS_TABS = [
  { key: 'all', label: 'All', color: 'var(--color-primary)', icon: 'lucide:layers' },
  { key: 'new', label: 'New', color: '#2563eb', icon: 'lucide:sparkles' },
  { key: 'contacted', label: 'Contacted', color: '#d97706', icon: 'lucide:phone-call' },
  { key: 'confirmed', label: 'Confirmed', color: '#16a34a', icon: 'lucide:badge-check' },
  { key: 'cancelled', label: 'Cancelled', color: '#94a3b8', icon: 'lucide:circle-slash' },
]
const STATUS_OPTIONS = STATUS_TABS.filter((s) => s.key !== 'all').map((s) => ({ value: s.key, label: s.label, dot: s.color }))
const TYPE_ICONS = { photo: 'lucide:camera', video: 'lucide:video', photo_video: 'lucide:clapperboard', drone: 'lucide:plane' }

const status = ref('all')
const flashId = ref(null)

// ---- sliding pill behind the active status tab ----
const tabsEl = ref(null)
const tabEls = {}
const pillStyle = ref({ opacity: 0 })
function placePill() {
  const el = tabEls[status.value]
  if (!el || !tabsEl.value) return
  pillStyle.value = {
    opacity: 1,
    width: `${el.offsetWidth}px`,
    height: `${el.offsetHeight}px`,
    transform: `translate(${el.offsetLeft}px, ${el.offsetTop}px)`,
  }
}
let pillObserver = null
const bookings = ref([])
const counts = ref({})
const canManage = ref(false)
const loading = ref(false)
const busyId = ref(null)
// "confirmed" بيجي بس من زرار Accept & create card، فمش بتظهر لحجز من غير كارد
const statusOptions = (b) => (b.task ? STATUS_OPTIONS : STATUS_OPTIONS.filter((o) => o.value !== 'confirmed'))
let seq = 0
/** `silent` = live/background refresh: no dimming, no error pop-up. */
async function load(silent = false) {
  const mine = ++seq
  if (silent !== true) loading.value = true
  try {
    const { data } = await api.get('/marketing/bookings', {
      params: { status: status.value === 'all' ? undefined : status.value, search: props.search?.trim() || undefined },
    })
    if (mine !== seq) return
    bookings.value = data.bookings
    counts.value = data.counts
    canManage.value = !!data.can_manage
  } catch (err) {
    if (mine === seq && silent !== true) Swal.fire({ icon: 'error', title: 'Could not load bookings', text: apiError(err) })
  } finally {
    if (mine === seq) loading.value = false
  }
}

async function save(b, patch) {
  try {
    const { data } = await api.put(`/marketing/bookings/${b.id}`, patch)
    if (patch.status) {
      showCrmToast({
        kind: patch.status === 'cancelled' ? 'deleted' : 'updated',
        headline: `Marked ${STATUS_LABEL[patch.status].toLowerCase()}`,
        subtitle: b.name,
        chip: b.reference,
      })
      load()
    } else {
      Object.assign(b, data.booking)
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not update booking', text: apiError(err) })
  }
}
async function convert(b) {
  busyId.value = b.id
  try {
    const { data } = await api.post(`/marketing/bookings/${b.id}/convert`)
    Object.assign(b, data.booking)
    showCrmToast({ kind: 'created', headline: 'Production card created', subtitle: data.booking.task?.title || b.name, chip: 'Production' })
    load()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not create card', text: apiError(err) })
  } finally {
    busyId.value = null
  }
}

async function remove(b) {
  const res = await Swal.fire({ icon: 'warning', title: 'Delete this booking?', text: `${b.name} · ${b.reference}`, showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' })
  if (!res.isConfirmed) return
  try {
    await api.delete(`/marketing/bookings/${b.id}`)
    showCrmToast({ kind: 'deleted', headline: 'Booking deleted', subtitle: b.name, chip: b.reference })
    load()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not delete booking', text: apiError(err) })
  }
}

const dayNum = (iso) => Number(iso.slice(8, 10))
const monShort = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', { month: 'short' })
const weekday = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short' })
const initials = (name) => String(name || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase()

/** "Today", "Tomorrow", "In 5 days", "3 days ago" — relative to the shoot date. */
function relative(b) {
  const day = new Date(`${b.date}T00:00:00`)
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const diff = Math.round((day - today) / 86400000)
  if (diff === 0) return b.is_past ? 'Earlier today' : 'Today'
  if (diff === 1) return 'Tomorrow'
  if (diff > 1) return `In ${diff} days`
  if (diff === -1) return 'Yesterday'
  return `${-diff} days ago`
}

async function copyText(text, headline) {
  try {
    await navigator.clipboard.writeText(text)
    showCrmToast({ kind: 'copied', headline, subtitle: text })
  } catch {
    /* clipboard unavailable */
  }
}

let searchTimer = null
watch(() => props.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 300)
})
watch(status, load)

// Live: new requests, requester cancellations and teammates' changes appear without a reload.
// A brand-new request flashes so it's easy to spot.
usePhotoBookingLive(async (e) => {
  const known = new Set(bookings.value.map((b) => b.id))
  await load(true)
  const fresh = e?.action === 'created' ? Number(e.booking_id) : bookings.value.find((b) => !known.has(b.id))?.id
  if (fresh && known.size) {
    flashId.value = null
    nextTick(() => { flashId.value = fresh })
  }
})
// Counts change tab widths, and the bar can wrap on small screens — keep the pill aligned.
watch([status, counts], () => nextTick(placePill), { deep: true })

onMounted(() => {
  load()
  nextTick(placePill)
  if (typeof ResizeObserver !== 'undefined' && tabsEl.value) {
    pillObserver = new ResizeObserver(() => placePill())
    pillObserver.observe(tabsEl.value)
  }
})
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  pillObserver?.disconnect()
})
</script>

<style scoped>
.mk-bk__bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
/* ===== Segmented status tabs ===== */
.sg {
  position: relative;
  display: inline-flex;
  flex-wrap: wrap;
  gap: 2px;
  padding: 5px;
  border-radius: 16px;
  background: var(--neutral-100);
  box-shadow: inset 0 1px 2px rgba(15, 10, 30, 0.06);
}
.sg__pill {
  position: absolute;
  top: 0;
  left: 0;
  border-radius: 12px;
  background: var(--white);
  box-shadow: 0 6px 16px -8px rgba(15, 10, 30, 0.35), 0 1px 2px rgba(15, 10, 30, 0.06);
  transition: transform 0.35s cubic-bezier(.3, 1.3, .5, 1), width 0.35s cubic-bezier(.3, 1.3, .5, 1), opacity 0.2s;
  pointer-events: none;
}
.sg__tab {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  margin: 0 !important;
  padding: 7px 12px 7px 8px !important;
  border: 0;
  border-radius: 12px;
  background: transparent;
  font-size: 13px !important;
  font-weight: 600 !important;
  line-height: 1.2 !important;
  color: var(--text-secondary-light);
  cursor: pointer;
  transition: color 0.2s;
}
.sg__tab:hover:not(.active) { color: var(--text-primary-light); }
.sg__tab:focus-visible { outline: 2px solid var(--c); outline-offset: 1px; }
.sg__icon {
  width: 24px;
  height: 24px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  font-size: 13px !important;
  color: var(--c);
  background: color-mix(in srgb, var(--c) 12%, transparent);
  transition: transform 0.3s cubic-bezier(.3, 1.6, .5, 1), background 0.2s, color 0.2s;
}
.sg__tab.active { color: var(--text-primary-light); }
.sg__tab.active .sg__icon { color: #fff; background: var(--c); transform: rotate(-8deg) scale(1.05); box-shadow: 0 4px 10px -4px var(--c); }
.sg__tab:hover .sg__icon { transform: translateY(-1px); }
.sg__count {
  min-width: 22px;
  padding: 1px 7px;
  border-radius: 999px;
  text-align: center;
  font-size: 11px !important;
  font-weight: 700 !important;
  font-variant-numeric: tabular-nums;
  color: var(--text-secondary-light);
  background: var(--neutral-200);
  transition: background 0.2s, color 0.2s;
}
.sg__tab.active .sg__count { color: #fff; background: var(--c); }
/* New requests waiting → the New count gently pulses */
.sg__tab.has-new .sg__count { color: #fff; background: var(--c); animation: sg-pulse 1.8s ease-out infinite; }
@keyframes sg-pulse {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--c) 55%, transparent); }
  100% { box-shadow: 0 0 0 7px color-mix(in srgb, var(--c) 0%, transparent); }
}
@media (max-width: 640px) {
  .sg { display: flex; width: 100%; }
  .sg__tab { flex: 1 1 auto; justify-content: center; }
  .sg__label { display: none; }
  .sg__tab.active .sg__label { display: inline; }
}
@media (prefers-reduced-motion: reduce) {
  .sg__pill, .sg__icon { transition: none; }
  .sg__tab.has-new .sg__count { animation: none; }
}

.mk-bk__empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 48px 16px; text-align: center; color: var(--text-secondary-light); }
.mk-bk__empty b { font-size: 15px !important; color: var(--text-primary-light); }
.mk-bk__empty p { margin: 0; max-width: 44ch; font-size: 13px !important; }
.mk-bk__empty-icon { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; font-size: 24px !important; color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); }

/* ===== Booking tickets ===== */
.mk-bk__list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 490px), 1fr));
  gap: 14px;
  transition: opacity 0.2s;
}
.mk-bk__list.is-loading { opacity: 0.6; }

.tk {
  --st: #2563eb;
  --st-soft: rgba(37, 99, 235, 0.1);
  position: relative;
  display: grid;
  grid-template-columns: 108px minmax(0, 1fr);
  border-radius: 20px;
  background: #fff;
  border: 1px solid #eceaf2;
  box-shadow: 0 10px 28px -22px rgba(28, 20, 48, 0.45);
  /* No overflow:hidden — it would clip the status dropdown. The stub rounds its own corners. */
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s;
}
/* A ticket with an open dropdown sits above its neighbours so the menu isn't covered. */
.tk:has(.mk-select.is-open) { z-index: 20; }
.tk:hover {
  transform: translateY(-2px);
  border-color: #ddd6ea;
  box-shadow: 0 18px 36px -20px rgba(var(--color-primary-rgb), 0.4);
}
.tk.is-contacted { --st: #d97706; --st-soft: rgba(217, 119, 6, 0.12); }
.tk.is-confirmed { --st: #16a34a; --st-soft: rgba(22, 163, 74, 0.12); }
.tk.is-cancelled { --st: #64748b; --st-soft: rgba(100, 116, 139, 0.12); }
.tk.is-cancelled .tk__main { opacity: 0.65; }
.tk.is-flash { animation: tk-flash 1.6s ease-out 2; }
@keyframes tk-flash {
  0% { box-shadow: 0 0 0 0 rgba(var(--color-primary-rgb), 0.5); }
  100% { box-shadow: 0 0 0 14px rgba(var(--color-primary-rgb), 0); }
}

/* Date stub — brand gradient, with ticket notches on the perforation line */
.tk__stub {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
  padding: 16px 8px;
  color: #fff;
  text-align: center;
  background: var(--gradient-primary);
  border-radius: 17px 0 0 17px;
}
.tk.is-past .tk__stub { background: linear-gradient(160deg, #94a3b8, #64748b); }
.tk.is-cancelled .tk__stub { background: linear-gradient(160deg, #cbd5e1, #94a3b8); }
.tk__stub::after {
  /* perforation */
  content: '';
  position: absolute;
  top: 10px;
  bottom: 10px;
  right: 0;
  border-right: 2px dashed rgba(255, 255, 255, 0.55);
}
.tk::before,
.tk::after {
  /* the two half-circle notches cut into the ticket edge */
  content: '';
  position: absolute;
  left: 108px;
  width: 16px;
  height: 16px;
  margin-left: -8px;
  border-radius: 50%;
  background: var(--neutral-50);
  z-index: 1;
}
.tk::before { top: -8px; box-shadow: inset 0 -1px 0 var(--border-color); }
.tk::after { bottom: -8px; box-shadow: inset 0 1px 0 var(--border-color); }
.tk__wd { font-size: 11px !important; font-weight: 700 !important; letter-spacing: 0.1em; text-transform: uppercase; opacity: 0.85; }
.tk__day { font-size: 34px !important; font-weight: 800 !important; line-height: 1 !important; font-variant-numeric: tabular-nums; }
.tk__mon { font-size: 12px !important; font-weight: 700 !important; letter-spacing: 0.08em; text-transform: uppercase; }
.tk__time {
  display: inline-flex; align-items: center; gap: 4px; margin-top: 8px; padding: 3px 8px; border-radius: 999px;
  font-size: 11.5px !important; font-weight: 700 !important; white-space: nowrap;
  background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.28);
}
.tk__rel { margin-top: 6px; font-size: 10.5px !important; font-weight: 600 !important; opacity: 0.85; }

/* Details */
.tk__main { display: flex; flex-direction: column; gap: 9px; min-width: 0; padding: 14px 16px 12px 20px; }
.tk__head { display: flex; align-items: center; gap: 10px; min-width: 0; }
.tk__avatar {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center;
  font-size: 12px !important; font-weight: 700 !important; letter-spacing: 0.02em; color: var(--color-primary);
  background: #f3eefb;
}
.tk__avatar { position: relative; overflow: hidden; }
.tk__avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.tk__place {
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
  font-size: 13px !important; color: #241c30;
}
.tk__place > svg { color: var(--color-primary); }
.tk__place b { font-weight: 650 !important; }
.tk__tag {
  display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px;
  font-size: 11.5px !important; font-weight: 600 !important; color: #5c5470;
  background: #f7f5fa; border: 1px solid #eeeaf3;
}
.tk__who { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.tk__who b { font-size: 15px !important; font-weight: 700 !important; color: #241c30; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tk__who small { font-size: 12px !important; color: #8d869c; font-variant-numeric: tabular-nums; }
.tk__status {
  display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; padding: 4px 10px; border-radius: 999px;
  font-size: 11.5px !important; font-weight: 700 !important; color: var(--st); background: var(--st-soft);
}
.tk__status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--st); box-shadow: 0 0 0 3px var(--st-soft); }
.tk.is-new .tk__status-dot { animation: tk-pulse 1.8s ease-out infinite; }
@keyframes tk-pulse {
  0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.5); }
  100% { box-shadow: 0 0 0 7px rgba(37, 99, 235, 0); }
}

.tk__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.tk__chip {
  display: inline-flex; align-items: center; gap: 5px; max-width: 100%; padding: 5px 10px; border-radius: 999px;
  font-size: 12.5px !important; font-weight: 600 !important; color: #3d3550; text-decoration: none;
  background: #fff; border: 1px solid #eceaf2;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}
a.tk__chip:hover, .tk__chip--btn:hover { border-color: #d8ccea; color: var(--color-primary); background: #faf8fc; }
.tk__chip--type { font-weight: 650 !important; color: #5b3d91; background: #f3eefb; border-color: transparent; }
.tk__chip--btn { cursor: pointer; padding: 5px 9px; border-radius: 10px; }
.tk__mail {
  display: inline-flex; align-items: center; gap: 6px; align-self: flex-start; max-width: 100%;
  font-size: 12.5px !important; font-weight: 500 !important; color: #6f6880; text-decoration: none;
}
.tk__mail:hover { color: var(--color-primary); }

.tk__notes {
  display: flex; gap: 8px; margin: 0; padding: 9px 12px; border-radius: 12px;
  font-size: 13px !important; line-height: 1.5; color: var(--text-primary-light);
  background: var(--neutral-50); border-left: 3px solid rgba(var(--color-primary-rgb), 0.35);
}
.tk__notes svg { flex-shrink: 0; margin-top: 2px; color: var(--color-primary); opacity: 0.7; }
.tk__notes span { white-space: pre-wrap; word-break: break-word; }

.tk__team {
  display: flex; align-items: center; gap: 8px; padding: 0 12px; border-radius: 12px;
  border: 1px dashed #e4deee; background: #fcfbfe; color: #8d869c;
  transition: border-color 0.15s, background 0.15s;
}
.tk__team:focus-within { border-style: solid; border-color: var(--color-primary); background: #fff; }
.tk__team input {
  flex: 1; min-width: 0; min-height: 34px; border: 0; outline: 0; background: transparent;
  font-size: 12.5px !important; color: var(--text-primary-light);
}
.tk__team input::placeholder { color: var(--text-secondary-light); opacity: 0.8; }

.tk__foot {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 10px;
  margin-top: 2px; padding-top: 10px; border-top: 1px solid #f0eef4;
}
.tk__handled { display: inline-flex; align-items: center; gap: 5px; min-width: 0; font-size: 12px !important; color: #8d869c; }
.tk__handled--new { color: var(--color-primary); font-weight: 600 !important; }
.tk__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-left: auto; }
.tk__actions :deep(.mk-btn--primary) { box-shadow: none; border-radius: 10px; }
.tk__del:hover { color: #dc2626 !important; border-color: #dc2626 !important; }

@media (max-width: 560px) {
  .tk { grid-template-columns: 1fr; }
  .tk__stub {
    flex-direction: row; flex-wrap: wrap; justify-content: flex-start; gap: 8px; padding: 12px 16px;
    border-radius: 17px 17px 0 0;
  }
  .tk__stub::after { top: auto; bottom: 0; left: 12px; right: 12px; border-right: 0; border-bottom: 2px dashed rgba(255, 255, 255, 0.55); }
  .tk::before, .tk::after { display: none; }
  .tk__day { font-size: 24px !important; }
  .tk__time, .tk__rel { margin-top: 0; }
  .tk__main { padding: 14px; }
  .tk__actions { width: 100%; }
}
@media (prefers-reduced-motion: reduce) {
  .tk, .tk__chip { transition: none; }
  .tk:hover { transform: none; }
  .tk.is-flash, .tk.is-new .tk__status-dot { animation: none; }
}

.tk__units { display: flex; flex-wrap: wrap; gap: 6px; }
</style>
