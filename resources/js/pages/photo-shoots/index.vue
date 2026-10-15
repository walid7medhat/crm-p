<template>
  <div class="ps">
    <!-- Header -->
    <header class="ps-hero">
      <div class="ps-hero__title">
        <span class="ps-hero__icon"><iconify-icon icon="lucide:camera" /></span>
        <div>
          <h4>Photo Shoots</h4>
          <p>Request a photoshoot for a unit — the marketing team will arrange it and keep you posted.</p>
        </div>
      </div>
      <button v-if="!formOpen" type="button" class="ps-btn ps-btn--primary" @click="openForm">
        <iconify-icon icon="lucide:plus" /> New request
      </button>
    </header>

    <!-- New request form -->
    <Transition name="ps-drop">
      <form v-if="formOpen" class="ps-form" novalidate autocomplete="off" @submit.prevent="submit">
        <div class="ps-form__head">
          <h5><iconify-icon icon="lucide:camera" /> New photoshoot request</h5>
          <button type="button" class="ps-icon-btn" aria-label="Close" @click="closeForm"><iconify-icon icon="lucide:x" /></button>
        </div>

        <div class="ps-form__grid">
          <!-- Left: what & where -->
          <div class="ps-col">
            <section class="ps-sec" :class="{ 'has-error': errors.shoot_type }">
              <span class="ps-label">What should we shoot?</span>
              <div class="ps-types" role="radiogroup" aria-label="Shoot type">
                <button
                  v-for="t in TYPES"
                  :key="t.value"
                  type="button"
                  role="radio"
                  class="ps-type"
                  :class="{ on: form.shoot_type === t.value }"
                  :aria-checked="form.shoot_type === t.value"
                  @click="form.shoot_type = t.value"
                >
                  <span class="ps-type__icon"><iconify-icon :icon="t.icon" /></span>
                  <span class="ps-type__text"><b>{{ t.label }}</b><small>{{ t.hint }}</small></span>
                </button>
              </div>
              <small v-if="errors.shoot_type" class="ps-err"><iconify-icon icon="lucide:alert-circle" /> {{ errors.shoot_type }}</small>
            </section>

            <section class="ps-sec" :class="{ 'has-error': errors.area_id }">
              <label class="ps-label" for="ps-area">Location</label>
              <v-select
                input-id="ps-area"
                v-model="form.area_id"
                :options="areas"
                :reduce="(a) => a.id"
                :loading="areasLoading"
                label="name"
                placeholder="Select area"
                class="ps-vselect"
                :append-to-body="true"
              >
                <template #option="option">
                  <div class="ps-loc">
                    <iconify-icon icon="lucide:map-pin" />
                    <span><b>{{ option.name }}</b><small v-if="option.parent">{{ option.parent }}</small></span>
                  </div>
                </template>
                <template #selected-option="option">
                  <div class="ps-loc ps-loc--selected">
                    <iconify-icon icon="lucide:map-pin" />
                    <span><b>{{ option.name }}</b><small v-if="option.parent">{{ option.parent }}</small></span>
                  </div>
                </template>
                <template #no-options>No matching area</template>
              </v-select>
              <small v-if="errors.area_id" class="ps-err"><iconify-icon icon="lucide:alert-circle" /> {{ errors.area_id }}</small>
            </section>

            <section class="ps-sec" :class="{ 'has-error': errors.units }">
              <span class="ps-label">Units</span>

              <div v-for="(u, i) in form.units" :key="u.key" class="ps-unit">
                <div class="ps-unit__top">
                  <span class="ps-unit__n">{{ i + 1 }}</span>
                  <input
                    v-model.trim="u.unit_number"
                    class="ps-input"
                    maxlength="60"
                    placeholder="Unit number, e.g. A-1204"
                    :aria-label="`Unit number ${i + 1}`"
                    :class="{ 'is-invalid': unitErrors[`${i}.unit_number`] }"
                    @input="delete unitErrors[`${i}.unit_number`]"
                  />
                  <button v-if="form.units.length > 1" type="button" class="ps-icon-btn" :aria-label="`Remove unit ${i + 1}`" @click="removeUnit(i)">
                    <iconify-icon icon="lucide:trash-2" />
                  </button>
                </div>
                <div class="ps-beds" role="radiogroup" :aria-label="`Bedrooms for unit ${i + 1}`">
                  <button
                    v-for="b in BEDROOMS"
                    :key="b.value"
                    type="button"
                    role="radio"
                    class="ps-bed"
                    :class="{ on: u.bedrooms === b.value }"
                    :aria-checked="u.bedrooms === b.value"
                    @click="u.bedrooms = b.value; delete unitErrors[`${i}.bedrooms`]"
                  >{{ b.text }}</button>
                </div>
                <small v-if="unitErrors[`${i}.unit_number`] || unitErrors[`${i}.bedrooms`]" class="ps-err">
                  <iconify-icon icon="lucide:alert-circle" /> {{ unitErrors[`${i}.unit_number`] || unitErrors[`${i}.bedrooms`] }}
                </small>
              </div>

              <button type="button" class="ps-btn ps-btn--ghost ps-add" :disabled="form.units.length >= 20" @click="addUnit">
                <iconify-icon icon="lucide:plus" /> Add another unit
              </button>
              <small v-if="errors.units" class="ps-err"><iconify-icon icon="lucide:alert-circle" /> {{ errors.units }}</small>
            </section>

            <section class="ps-sec">
              <label class="ps-label" for="ps-notes">Notes <em>optional</em></label>
              <textarea id="ps-notes" v-model="form.notes" class="ps-input" rows="2" maxlength="2000" placeholder="Access details, furnished or not, special shots…" />
            </section>
          </div>

          <!-- Right: when -->
          <div class="ps-col">
            <section class="ps-sec" :class="{ 'has-error': errors.booking_date || errors.booking_time }">
              <span class="ps-label">Date &amp; time</span>
              <div class="ps-cal">
                <div class="ps-cal__head">
                  <button type="button" class="ps-cal__nav" :disabled="!canPrevMonth" aria-label="Previous month" @click="shiftMonth(-1)"><iconify-icon icon="lucide:chevron-left" /></button>
                  <b>{{ monthLabel }}</b>
                  <button type="button" class="ps-cal__nav" :disabled="!canNextMonth" aria-label="Next month" @click="shiftMonth(1)"><iconify-icon icon="lucide:chevron-right" /></button>
                </div>
                <div class="ps-cal__dow"><span v-for="d in DOW" :key="d">{{ d }}</span></div>
                <div class="ps-cal__grid">
                  <span v-for="n in leadingBlanks" :key="`b${n}`" />
                  <button
                    v-for="day in monthDays"
                    :key="day.iso"
                    type="button"
                    class="ps-day"
                    :class="{ on: form.booking_date === day.iso, today: day.iso === todayIso }"
                    :disabled="day.disabled"
                    :aria-label="day.label"
                    :aria-pressed="form.booking_date === day.iso"
                    @click="pickDate(day.iso)"
                  >{{ day.n }}</button>
                </div>
              </div>

              <div class="ps-times">
                <p v-if="!form.booking_date" class="ps-times__empty"><iconify-icon icon="lucide:calendar-days" /> Choose a date to see times</p>
                <template v-else>
                  <div v-for="g in timeGroups" :key="g.label" class="ps-times__group">
                    <span class="ps-times__label"><iconify-icon :icon="g.icon" /> {{ g.label }}</span>
                    <div class="ps-slots">
                      <button
                        v-for="s in g.slots"
                        :key="s.value"
                        type="button"
                        class="ps-slot"
                        :class="{ on: form.booking_time === s.value }"
                        :disabled="s.disabled"
                        :aria-pressed="form.booking_time === s.value"
                        @click="form.booking_time = s.value"
                      >{{ s.label }}</button>
                    </div>
                  </div>
                </template>
              </div>
              <small v-if="errors.booking_date || errors.booking_time" class="ps-err"><iconify-icon icon="lucide:alert-circle" /> {{ errors.booking_date || errors.booking_time }}</small>
            </section>
          </div>
        </div>

        <footer class="ps-form__foot">
          <div v-if="errors.general" class="ps-alert" role="alert">
            <iconify-icon icon="lucide:alert-triangle" /> <span>{{ errors.general }}</span>
          </div>
          <div class="ps-summary" :class="{ ready: summaryReady }">
            <iconify-icon :icon="summaryReady ? 'lucide:calendar-check' : 'lucide:calendar-clock'" />
            <span v-if="summaryReady"><b>{{ typeLabel }}</b> · {{ prettyDate(form.booking_date) }} · {{ timeLabel(form.booking_time) }}</span>
            <span v-else>Pick the shoot type, location, unit, date and time</span>
          </div>
          <div class="ps-form__actions">
            <button type="button" class="ps-btn ps-btn--ghost" @click="closeForm">Cancel</button>
            <button type="submit" class="ps-btn ps-btn--primary" :disabled="sending">
              <iconify-icon :icon="sending ? 'lucide:loader-2' : 'lucide:send'" :class="{ 'ps-spin': sending }" />
              {{ sending ? 'Sending…' : 'Send request' }}
            </button>
          </div>
        </footer>
      </form>
    </Transition>

    <!-- My requests -->
    <section class="ps-list">
      <div class="ps-list__head">
        <h5>My requests</h5>
        <div class="ps-filter" role="tablist" aria-label="Filter requests">
          <button v-for="f in FILTERS" :key="f.key" type="button" role="tab" :aria-selected="filter === f.key" :class="{ active: filter === f.key }" @click="filter = f.key">
            {{ f.label }} <b>{{ countFor(f.key) }}</b>
          </button>
        </div>
      </div>

      <div v-if="loading && !bookings.length" class="ps-empty"><iconify-icon icon="lucide:loader-2" class="ps-spin" /></div>

      <div v-else-if="!visible.length" class="ps-empty">
        <span class="ps-empty__icon"><iconify-icon icon="lucide:camera" /></span>
        <b>{{ bookings.length ? 'Nothing in this list' : 'No photoshoot requests yet' }}</b>
        <p v-if="!bookings.length">Use <em>New request</em> to book a shoot for a unit.</p>
      </div>

      <div v-else class="ps-cards">
        <article
          v-for="b in visible"
          :key="b.id"
          class="ps-card"
          :class="[`is-${b.status}`, { 'is-flash': b.id === highlightId }]"
        >
          <header class="ps-card__head">
            <div class="ps-card__date">
              <b>{{ Number(b.date.slice(8, 10)) }}</b>
              <span>{{ monShort(b.date) }}</span>
            </div>
            <div class="ps-card__headtext">
              <b>{{ b.shoot_type_label }}</b>
              <small>{{ weekday(b.date) }} · {{ b.time_label }}</small>
            </div>
            <span class="ps-status" :title="STATUS[b.status].label"><span class="ps-status__dot" /> {{ STATUS[b.status].label }}</span>
          </header>

          <p class="ps-card__where" :title="b.location || ''">
            <iconify-icon icon="lucide:map-pin" /> <span>{{ b.location || 'Location not set' }}</span>
          </p>
          <div v-if="b.units?.length" class="ps-card__tags">
            <span v-for="(u, i) in b.units" :key="i" class="ps-tag">
              <iconify-icon icon="lucide:door-open" /> {{ u.unit_number }}
              <template v-if="u.bedrooms_label"> · {{ u.bedrooms_label }}</template>
            </span>
          </div>
          <p v-if="b.notes" class="ps-card__notes" :title="b.notes">“{{ b.notes }}”</p>

          <!-- Progress: Requested → Contacted → Confirmed -->
          <ol v-if="b.status !== 'cancelled'" class="ps-steps">
            <li v-for="(s, i) in STEPS" :key="s.key" :class="{ done: stepIndex(b.status) >= i, now: stepIndex(b.status) === i }" :title="s.label">
              <span class="ps-steps__dot"><iconify-icon :icon="stepIndex(b.status) > i ? 'lucide:check' : s.icon" /></span>
              <small>{{ s.short }}</small>
            </li>
          </ol>
          <p v-else class="ps-card__cancelled"><iconify-icon icon="lucide:circle-slash" /> Cancelled{{ b.handled_by ? ` by ${b.handled_by}` : '' }}</p>

          <footer class="ps-card__foot">
            <small :title="b.handled_by && b.status !== 'cancelled' ? `Handled by ${b.handled_by}` : ''">{{ b.reference }} · {{ timeAgo(b.created_at) }}</small>
            <button v-if="['new', 'contacted'].includes(b.status)" type="button" class="ps-link" @click="cancel(b)">Cancel</button>
          </footer>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { usePhotoBookingLive } from '@/composables/usePhotoBookingLive.js'
import { useRoute, useRouter } from 'vue-router'
import vSelect from 'vue-select'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import { showCrmToast } from '@/utils/crmToast.js'
import { apiError, timeAgo } from '@/config/marketingConfig.js'

const TYPES = [
  { value: 'photo', label: 'Photography', hint: 'Interiors & exteriors', icon: 'lucide:camera' },
  { value: 'video', label: 'Videography', hint: 'Walkthrough film', icon: 'lucide:video' },
  { value: 'photo_video', label: 'Photo + Video', hint: 'The full package', icon: 'lucide:clapperboard' },
]
// Same bedroom options as the lead form.
const BEDROOMS = [{ value: 0, text: 'Studio' }, ...Array.from({ length: 9 }, (_, i) => ({ value: i + 1, text: String(i + 1) }))]
const STATUS = {
  new: { label: 'Requested', color: '#2563eb' },
  contacted: { label: 'In progress', color: '#d97706' },
  confirmed: { label: 'Confirmed', color: '#16a34a' },
  cancelled: { label: 'Cancelled', color: '#94a3b8' },
}
const STEPS = [
  { key: 'new', label: 'Requested', short: 'Sent', icon: 'lucide:send' },
  { key: 'contacted', label: 'Team contacted', short: 'Contacted', icon: 'lucide:phone-call' },
  { key: 'confirmed', label: 'Confirmed', short: 'Confirmed', icon: 'lucide:badge-check' },
]
const FILTERS = [
  { key: 'open', label: 'Open' },
  { key: 'confirmed', label: 'Confirmed' },
  { key: 'cancelled', label: 'Cancelled' },
  { key: 'all', label: 'All' },
]
const DOW = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']

const route = useRoute()
const router = useRouter()

const pad = (n) => String(n).padStart(2, '0')
const isoOf = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
const today = new Date()
const todayIso = isoOf(today)

// ---------- list ----------
const bookings = ref([])
const loading = ref(false)
const filter = ref('open')
const highlightId = ref(null)

const inFilter = (b, key) => key === 'all'
  || (key === 'open' && ['new', 'contacted'].includes(b.status))
  || b.status === key
const visible = computed(() => bookings.value.filter((b) => inFilter(b, filter.value)))
const countFor = (key) => bookings.value.filter((b) => inFilter(b, key)).length
const stepIndex = (status) => ({ new: 0, contacted: 1, confirmed: 2 }[status] ?? 0)

/** `silent` = background refresh (live update / fallback poll): no spinner, no error pop-up. */
async function load(silent = false) {
  if (!silent) loading.value = true
  try {
    const { data } = await api.get('/photo-bookings')
    bookings.value = data.bookings
  } catch (err) {
    if (!silent) Swal.fire({ icon: 'error', title: 'Could not load your requests', text: apiError(err) })
  } finally {
    if (!silent) loading.value = false
  }
}

// Live: the team changed one of my requests (or I did in another tab) → refresh, flash it.
usePhotoBookingLive(async (e) => {
  const before = new Map(bookings.value.map((b) => [b.id, b.status]))
  await load(true)
  const changed = e?.booking_id
    ? Number(e.booking_id)
    : bookings.value.find((b) => before.has(b.id) && before.get(b.id) !== b.status)?.id
  if (changed && bookings.value.some((b) => b.id === changed)) {
    highlightId.value = null
    nextTick(() => { highlightId.value = changed })
  }
})

async function cancel(b) {
  const res = await Swal.fire({
    icon: 'warning',
    title: 'Cancel this request?',
    text: `${b.shoot_type_label} on ${b.date_label} at ${b.time_label}`,
    showCancelButton: true,
    confirmButtonText: 'Cancel request',
    cancelButtonText: 'Keep it',
    confirmButtonColor: '#dc2626',
  })
  if (!res.isConfirmed) return
  try {
    const { data } = await api.post(`/photo-bookings/${b.id}/cancel`)
    Object.assign(b, data.booking)
    showCrmToast({ kind: 'deleted', headline: 'Request cancelled', subtitle: `${b.shoot_type_label} · ${b.date_label}`, chip: b.reference })
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not cancel', text: apiError(err) })
  }
}

// ---------- form ----------
const formOpen = ref(false)
const sending = ref(false)
const areas = ref([])
const areasLoading = ref(false)
let unitKey = 0
const newUnit = () => ({ key: ++unitKey, unit_number: '', bedrooms: null })
const blank = () => ({ shoot_type: '', area_id: null, units: [newUnit()], booking_date: '', booking_time: '', notes: '' })
const unitErrors = reactive({})

function addUnit() { if (form.units.length < 20) form.units.push(newUnit()) }
function removeUnit(i) {
  form.units.splice(i, 1)
  Object.keys(unitErrors).forEach((k) => delete unitErrors[k])
}
const form = reactive(blank())
const errors = reactive({})
const view = ref(new Date(today.getFullYear(), today.getMonth(), 1))

async function loadAreas() {
  if (areas.value.length || areasLoading.value) return
  areasLoading.value = true
  try {
    // Same source and shape as the lead / deal location picker.
    const res = await api.get('/listings/areas/?has_listings=true')
    const list = res.data?.data || res.data || []
    areas.value = list.map((a) => ({ id: a.id, name: a.name || a.title, parent: a.area_parents_title || null }))
  } catch {
    areas.value = []
  } finally {
    areasLoading.value = false
  }
}

function openForm() {
  formOpen.value = true
  loadAreas()
  nextTick(() => document.querySelector('.ps-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
}
function closeForm() {
  formOpen.value = false
  Object.assign(form, blank())
  Object.keys(errors).forEach((k) => delete errors[k])
  Object.keys(unitErrors).forEach((k) => delete unitErrors[k])
}

// calendar
const monthLabel = computed(() => view.value.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }))
const leadingBlanks = computed(() => (view.value.getDay() + 6) % 7)
const monthDays = computed(() => {
  const y = view.value.getFullYear()
  const m = view.value.getMonth()
  const count = new Date(y, m + 1, 0).getDate()
  return Array.from({ length: count }, (_, i) => {
    const d = new Date(y, m, i + 1)
    const iso = isoOf(d)
    return { n: i + 1, iso, disabled: iso < todayIso, label: d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }) }
  })
})
const canPrevMonth = computed(() => view.value > new Date(today.getFullYear(), today.getMonth(), 1))
const canNextMonth = computed(() => view.value < new Date(today.getFullYear(), today.getMonth() + 11, 1))
function shiftMonth(n) { view.value = new Date(view.value.getFullYear(), view.value.getMonth() + n, 1) }
function pickDate(iso) {
  form.booking_date = iso
  if (form.booking_time && slotPast(iso, form.booking_time)) form.booking_time = ''
}

// time slots: every 30 min, 9:00–19:00
function slotPast(iso, value) {
  if (iso !== todayIso) return false
  const [h, m] = value.split(':').map(Number)
  const now = new Date()
  return h * 60 + m <= now.getHours() * 60 + now.getMinutes() + 30
}
function timeLabel(value) {
  if (!value) return ''
  const [h, m] = value.split(':').map(Number)
  return `${((h + 11) % 12) + 1}:${pad(m)} ${h < 12 ? 'AM' : 'PM'}`
}
const timeGroups = computed(() => {
  const make = (from, to) => {
    const out = []
    for (let t = from * 60; t <= to * 60; t += 30) {
      const value = `${pad(Math.floor(t / 60))}:${pad(t % 60)}`
      out.push({ value, label: timeLabel(value), disabled: slotPast(form.booking_date, value) })
    }
    return out
  }
  return [
    { label: 'Morning', icon: 'lucide:sunrise', slots: make(9, 11.5) },
    { label: 'Afternoon', icon: 'lucide:sun', slots: make(12, 15.5) },
    { label: 'Evening', icon: 'lucide:sunset', slots: make(16, 19) },
  ]
})

const typeLabel = computed(() => TYPES.find((t) => t.value === form.shoot_type)?.label || '')
const summaryReady = computed(() => !!(form.shoot_type && form.booking_date && form.booking_time))
const prettyDate = (iso) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '')
const weekday = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short' })
const monShort = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', { month: 'short' })

// errors clear as fields are fixed
watch(() => form.shoot_type, () => delete errors.shoot_type)
watch(() => form.area_id, () => delete errors.area_id)
watch(() => form.unit_number, () => delete errors.unit_number)
watch(() => form.bedrooms, () => delete errors.bedrooms)
watch(() => [form.booking_date, form.booking_time], () => { delete errors.booking_date; delete errors.booking_time })

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k])
  Object.keys(unitErrors).forEach((k) => delete unitErrors[k])
  if (!form.shoot_type) errors.shoot_type = 'Please choose what we should shoot.'
  if (!form.area_id) errors.area_id = 'Please choose the location.'

  const seen = new Set()
  form.units.forEach((u, i) => {
    const num = (u.unit_number || '').trim().toLowerCase()
    if (!num) unitErrors[`${i}.unit_number`] = 'Please enter the unit number.'
    else if (seen.has(num)) unitErrors[`${i}.unit_number`] = 'This unit number is entered twice.'
    seen.add(num)
    if (u.bedrooms === null) unitErrors[`${i}.bedrooms`] ||= 'Please choose the number of bedrooms.'
  })
  if (Object.keys(unitErrors).length) errors.units = 'Please complete every unit.'

  if (!form.booking_date) errors.booking_date = 'Please choose a date.'
  else if (!form.booking_time) errors.booking_time = 'Please choose a time.'
  return !Object.keys(errors).length
}

function scrollToError() {
  nextTick(() => document.querySelector('.ps-form .has-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
}

async function submit() {
  if (!validate()) return scrollToError()
  sending.value = true
  try {
    const payload = {
      ...form,
      units: form.units.map(({ unit_number, bedrooms }) => ({ unit_number, bedrooms })),
    }
    const { data } = await api.post('/photo-bookings', payload)
    showCrmToast({
      kind: 'created',
      headline: data.duplicate ? 'You already requested this slot' : 'Photoshoot requested',
      subtitle: `${data.booking.shoot_type_label} · ${data.booking.date_label} · ${data.booking.time_label}`,
      chip: data.booking.reference,
    })
    closeForm()
    filter.value = 'open'
    await load()
    highlightId.value = data.booking.id
  } catch (err) {
    const res = err?.response
    if (res?.status === 422 && res.data?.errors) {
      Object.entries(res.data.errors).forEach(([k, v]) => {
        const msg = Array.isArray(v) ? v[0] : v
        const m = k.match(/^units\.(\d+)\.(\w+)$/)
        if (m) {
          unitErrors[`${m[1]}.${m[2]}`] = msg
          errors.units = 'Please complete every unit.'
        } else if (k in blank()) {
          errors[k] = msg
        } else {
          errors.general = msg
        }
      })
      scrollToError()
    } else if (res?.status === 429) {
      errors.general = 'Too many requests — please wait a minute and try again.'
    } else {
      errors.general = apiError(err, 'Could not send your request. Please try again.')
    }
  } finally {
    sending.value = false
  }
}

// Deep link from a notification: /photo-shoots?booking=12
watch(() => route.query.booking, (id) => {
  if (!id) return
  highlightId.value = Number(id)
  filter.value = 'all'
  router.replace({ path: route.path })
}, { immediate: true })

onMounted(() => load())
</script>

<style scoped>
.ps { display: flex; flex-direction: column; gap: 14px; padding: 4px 2px 24px; color: var(--text-primary-light); }
.ps :is(h4, h5, p) { margin: 0; }

/* Buttons */
.ps-btn {
  display: inline-flex; align-items: center; gap: 7px; min-height: 38px; padding: 8px 16px !important; margin: 0 !important;
  border: 1px solid transparent; border-radius: 11px; cursor: pointer; white-space: nowrap;
  font-size: 13.5px !important; font-weight: 600 !important; line-height: 1.2 !important;
  transition: transform 0.12s, background 0.15s, border-color 0.15s;
}
.ps-btn:active:not(:disabled) { transform: scale(0.97); }
.ps-btn:disabled { opacity: 0.6; cursor: wait; }
.ps-btn--primary { color: #fff; background: var(--color-primary); }
.ps-btn--primary:hover:not(:disabled) { background: var(--color-primary-hover); }
.ps-btn--ghost { color: var(--text-primary-light); background: var(--white); border-color: var(--border-color); }
.ps-btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.ps-icon-btn { width: 32px; height: 32px; padding: 0 !important; border: 0; border-radius: 9px; display: grid; place-items: center; background: transparent; color: var(--text-secondary-light); cursor: pointer; }
.ps-icon-btn:hover { background: var(--neutral-100); }
.ps-link { border: 0; background: none; padding: 0 !important; margin: 0 !important; font-size: 12px !important; font-weight: 600 !important; color: #dc2626; cursor: pointer; }
.ps-link:hover { text-decoration: underline; }
.ps-spin { animation: ps-spin 0.9s linear infinite; }
@keyframes ps-spin { to { transform: rotate(360deg); } }

/* Header */
.ps-hero {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 14px; border-radius: 16px; background: var(--white); border: 1px solid var(--border-color);
}
.ps-hero__title { display: flex; align-items: center; gap: 10px; min-width: 0; }
.ps-hero__icon { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 19px; color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); flex-shrink: 0; }
.ps-hero h4 { font-size: 16px !important; font-weight: 700 !important; color: var(--text-primary-light); }
.ps-hero p { font-size: 12.5px !important; color: var(--text-secondary-light); }

/* Form */
.ps-form { padding: 16px; border-radius: 18px; background: var(--white); border: 1px solid rgba(var(--color-primary-rgb), 0.3); box-shadow: 0 18px 40px -30px rgba(var(--color-primary-rgb), 0.6); }
.ps-form__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.ps-form__head h5 { display: flex; align-items: center; gap: 8px; font-size: 15px !important; font-weight: 700 !important; color: var(--text-primary-light); }
.ps-form__head h5 svg { color: var(--color-primary); }
.ps-form__grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; }
.ps-col { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.ps-row { display: grid; grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr); gap: 12px; }
.ps-sec { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.ps-label { margin: 0; font-size: 12px !important; font-weight: 700 !important; color: var(--text-secondary-light); }
.ps-label em { font-style: normal; font-weight: 500; opacity: 0.8; }
.ps-input {
  width: 100%; padding: 9px 12px; border-radius: 10px; border: 1.5px solid var(--border-color);
  background: var(--neutral-50); color: var(--text-primary-light); font: inherit; font-size: 13.5px !important; outline: none;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.ps-input:focus { border-color: var(--color-primary); background: var(--white); box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.12); }
textarea.ps-input { resize: vertical; min-height: 60px; }

.ps-types { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.ps-type {
  display: flex; align-items: center; gap: 8px; padding: 9px 10px !important; margin: 0 !important; text-align: left; min-width: 0;
  border-radius: 12px; border: 1.5px solid var(--border-color); background: var(--white); color: var(--text-primary-light); cursor: pointer;
  transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
.ps-type:hover { border-color: rgba(var(--color-primary-rgb), 0.5); transform: translateY(-1px); }
.ps-type.on { border-color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.05); box-shadow: 0 8px 18px -12px rgba(var(--color-primary-rgb), 0.7); }
.ps-type__icon { width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0; display: grid; place-items: center; font-size: 15px; color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.1); transition: all 0.2s; }
.ps-type.on .ps-type__icon { color: #fff; background: var(--color-primary); transform: rotate(-6deg); }
.ps-type__text { display: flex; flex-direction: column; min-width: 0; }
.ps-type b { font-size: 12.5px !important; font-weight: 700 !important; line-height: 1.25 !important; }
.ps-type small { font-size: 10.5px !important; color: var(--text-secondary-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.ps-beds { display: flex; flex-wrap: wrap; gap: 5px; }
.ps-bed {
  min-width: 36px; height: 34px; padding: 0 10px !important; margin: 0 !important; border-radius: 9px;
  border: 1.5px solid var(--border-color); background: var(--white); color: var(--text-primary-light);
  font-size: 12.5px !important; font-weight: 600 !important; cursor: pointer; transition: all 0.15s;
  display: inline-flex !important; align-items: center !important; justify-content: center !important; line-height: 1 !important;
}
.ps-bed:hover { border-color: var(--color-primary); color: var(--color-primary); }
.ps-bed.on { color: #fff; background: var(--color-primary); border-color: var(--color-primary); }

/* Location select */
.ps-vselect :deep(.vs__dropdown-toggle) { min-height: 44px; padding: 3px 6px; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--neutral-50); }
.ps-vselect.vs--open :deep(.vs__dropdown-toggle) { border-color: var(--color-primary); background: var(--white); }
.ps-vselect :deep(.vs__search::placeholder) { font-size: 13px; color: var(--text-secondary-light); }
.ps-loc { display: flex; align-items: center; gap: 8px; min-width: 0; }
.ps-loc svg { color: var(--color-primary); flex-shrink: 0; }
.ps-loc span { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
.ps-loc b { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ps-loc small { font-size: 11px; color: var(--text-secondary-light); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Calendar */
.ps-cal { padding: 8px; border-radius: 12px; background: var(--neutral-50); border: 1px solid var(--border-color); max-width: 300px; }
.ps-cal__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
.ps-cal__head b { font-size: 12.5px !important; font-weight: 700 !important; }
.ps-cal__nav { width: 24px; height: 24px; padding: 0 !important; margin: 0 !important; border-radius: 7px; border: 1px solid var(--border-color); background: var(--white); display: grid !important; place-items: center !important; color: var(--text-primary-light); cursor: pointer; }
.ps-cal__nav:disabled { opacity: 0.35; cursor: not-allowed; }
.ps-cal__dow, .ps-cal__grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.ps-cal__dow span { display: block; text-align: center !important; font-size: 10px !important; font-weight: 700 !important; color: var(--text-secondary-light); padding: 2px 0; }
.ps-day {
  display: flex !important; align-items: center !important; justify-content: center !important; width: 100%;
  height: 26px; padding: 0 !important; margin: 0 !important; border: 0; border-radius: 7px; background: transparent;
  font-size: 11.5px !important; font-weight: 600 !important; line-height: 1 !important; color: var(--text-primary-light); cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.ps-day:hover:not(:disabled) { background: rgba(var(--color-primary-rgb), 0.1); }
.ps-day.today { box-shadow: inset 0 0 0 1.5px rgba(var(--color-primary-rgb), 0.45); }
.ps-day.on { color: #fff; background: var(--color-primary); }
.ps-day:disabled { color: var(--text-secondary-light); opacity: 0.4; cursor: not-allowed; }

.ps-times { margin-top: 8px; }
.ps-times__empty { display: flex; align-items: center; gap: 7px; padding: 12px; border-radius: 10px; border: 1.5px dashed var(--border-color); font-size: 12px !important; color: var(--text-secondary-light); }
.ps-times__group { margin-bottom: 5px; }
.ps-times__label { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 3px; font-size: 11px !important; font-weight: 600 !important; color: var(--text-secondary-light); }
.ps-slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(64px, 1fr)); gap: 4px; }
.ps-slot {
  display: flex !important; align-items: center !important; justify-content: center !important;
  padding: 4px 2px !important; margin: 0 !important; border-radius: 7px; border: 1.5px solid var(--border-color); background: var(--white);
  font-size: 10.5px !important; font-weight: 600 !important; line-height: 1.2 !important; color: var(--text-primary-light); cursor: pointer;
  font-variant-numeric: tabular-nums; white-space: nowrap; transition: all 0.15s;
}
.ps-slot:hover:not(:disabled) { border-color: var(--color-primary); color: var(--color-primary); }
.ps-slot.on { color: #fff; background: var(--color-primary); border-color: var(--color-primary); }
.ps-slot:disabled { opacity: 0.4; text-decoration: line-through; cursor: not-allowed; }

/* Errors */
.ps-err { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px !important; font-weight: 600 !important; color: #c4320a; }
.ps-sec.has-error .ps-input, .ps-sec.has-error .ps-cal, .ps-sec.has-error :deep(.vs__dropdown-toggle) { border-color: #f4a49b; }
.ps-sec.has-error .ps-type:not(.on), .ps-sec.has-error .ps-bed:not(.on) { border-color: #f4c3bd; }
.ps-alert { flex: 1 1 100%; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 10px; border: 1px solid #fecdca; background: #fef3f2; color: #b42318; font-size: 12.5px !important; font-weight: 600 !important; }

.ps-form__foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border-color); }
.ps-summary { display: flex; align-items: center; gap: 7px; min-width: 0; font-size: 12.5px !important; color: var(--text-secondary-light); }
.ps-summary.ready { color: var(--text-primary-light); }
.ps-summary.ready svg { color: var(--color-primary); }
.ps-form__actions { display: flex; gap: 8px; }

.ps-drop-enter-active, .ps-drop-leave-active { transition: opacity 0.22s, transform 0.22s; }
.ps-drop-enter-from, .ps-drop-leave-to { opacity: 0; transform: translateY(-8px); }

/* List */
.ps-list { display: flex; flex-direction: column; gap: 10px; }
.ps-list__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.ps-list__head h5 { font-size: 14.5px !important; font-weight: 700 !important; color: var(--text-primary-light); }
.ps-filter { display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 3px; border-radius: 11px; background: var(--neutral-100); }
.ps-filter button { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px !important; margin: 0 !important; border: 0; border-radius: 8px; background: transparent; font-size: 12.5px !important; font-weight: 600 !important; color: var(--text-secondary-light); cursor: pointer; }
.ps-filter button.active { color: var(--text-primary-light); background: var(--white); box-shadow: 0 2px 6px -2px rgba(15, 10, 30, 0.2); }
.ps-filter b { min-width: 18px; padding: 0 5px; border-radius: 999px; font-size: 10.5px !important; background: var(--neutral-200); text-align: center; }

.ps-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 40px 16px; text-align: center; border-radius: 16px; background: var(--white); border: 1px dashed var(--border-color); color: var(--text-secondary-light); }
.ps-empty b { font-size: 14.5px !important; color: var(--text-primary-light); }
.ps-empty p { font-size: 12.5px !important; }
.ps-empty__icon { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; font-size: 22px; color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); }

/* Compact request cards: 3–4 per row on a normal screen */
.ps-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr)); gap: 12px; }
.ps-card {
  --st: #2563eb;
  display: flex; flex-direction: column; gap: 8px; min-width: 0;
  padding: 12px; border-radius: 14px;
  background: var(--white); border: 1px solid var(--border-color);
  border-top: 3px solid var(--st);
  transition: box-shadow 0.2s, transform 0.2s;
}
.ps-card:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -20px rgba(var(--color-primary-rgb), 0.55); }
.ps-card.is-contacted { --st: #d97706; }
.ps-card.is-confirmed { --st: #16a34a; }
.ps-card.is-cancelled { --st: #94a3b8; opacity: 0.75; }
.ps-card.is-flash { animation: ps-flash 1.6s ease-out 2; }
@keyframes ps-flash { 0% { box-shadow: 0 0 0 0 rgba(var(--color-primary-rgb), 0.5); } 100% { box-shadow: 0 0 0 12px rgba(var(--color-primary-rgb), 0); } }

.ps-card__head { display: flex; align-items: center; gap: 9px; min-width: 0; }
.ps-card__date {
  width: 42px; height: 44px; flex-shrink: 0; border-radius: 11px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  color: #fff; background: var(--gradient-primary); line-height: 1;
}
.ps-card.is-cancelled .ps-card__date { background: linear-gradient(160deg, #cbd5e1, #94a3b8); }
.ps-card__date b { font-size: 17px !important; font-weight: 800 !important; }
.ps-card__date span { margin-top: 2px; font-size: 9.5px !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: 0.06em; }
.ps-card__headtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ps-card__headtext b { font-size: 13px !important; font-weight: 700 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ps-card__headtext small { font-size: 11px !important; color: var(--text-secondary-light); }
.ps-status {
  display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0; padding: 2px 8px; border-radius: 999px;
  font-size: 10.5px !important; font-weight: 700 !important; color: var(--st);
  background: color-mix(in srgb, var(--st) 12%, transparent); white-space: nowrap;
}
.ps-status__dot { width: 6px; height: 6px; border-radius: 50%; background: var(--st); }

.ps-card__where { display: flex; align-items: center; gap: 5px; min-width: 0; font-size: 12px !important; font-weight: 600 !important; color: var(--text-primary-light); }
.ps-card__where svg { color: var(--color-primary); flex-shrink: 0; }
.ps-card__where span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ps-card__tags { display: flex; flex-wrap: wrap; gap: 4px; }
.ps-tag {
  display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 6px;
  font-size: 11px !important; font-weight: 600 !important; color: var(--text-primary-light);
  background: var(--neutral-50); border: 1px solid var(--border-color);
}
.ps-tag svg { color: var(--text-secondary-light); }
.ps-card__notes {
  padding: 5px 8px; border-radius: 8px; background: var(--neutral-50);
  font-size: 11.5px !important; font-style: italic; color: var(--text-secondary-light);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ps-card__cancelled { display: flex; align-items: center; gap: 6px; font-size: 11.5px !important; color: var(--text-secondary-light); }

.ps-steps { list-style: none; margin: 2px 0 0; padding: 0; display: grid; grid-template-columns: repeat(3, 1fr); position: relative; }
.ps-steps::before { content: ''; position: absolute; top: 9px; left: 17%; right: 17%; height: 2px; background: var(--neutral-200); }
.ps-steps li { position: relative; display: flex; flex-direction: column; align-items: center; gap: 2px; text-align: center; }
.ps-steps__dot { position: relative; z-index: 1; width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; font-size: 10px; color: var(--text-secondary-light); background: var(--neutral-100); border: 2px solid var(--white); }
.ps-steps li.done .ps-steps__dot { color: #fff; background: var(--st); }
.ps-steps li.now .ps-steps__dot { box-shadow: 0 0 0 3px color-mix(in srgb, var(--st) 22%, transparent); }
.ps-steps small { font-size: 9.5px !important; font-weight: 600 !important; color: var(--text-secondary-light); }
.ps-steps li.done small { color: var(--text-primary-light); }

.ps-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: auto; padding-top: 7px; border-top: 1px solid var(--border-color); }
.ps-card__foot small { font-size: 11px !important; color: var(--text-secondary-light); }

@media (max-width: 900px) {
  .ps-form__grid { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
  .ps-types { grid-template-columns: 1fr; }
  .ps-row { grid-template-columns: 1fr; }
  .ps-cal { max-width: none; }

  .ps-form__actions { width: 100%; }
  .ps-form__actions .ps-btn { flex: 1; justify-content: center; }
}
@media (prefers-reduced-motion: reduce) {
  .ps-card, .ps-type, .ps-drop-enter-active, .ps-drop-leave-active { transition: none; }
  .ps-card.is-flash { animation: none; }
}
.ps-unit { display: flex; flex-direction: column; gap: 6px; padding: 10px; border-radius: 12px; background: var(--neutral-50); border: 1px solid var(--border-color); }
.ps-unit__top { display: flex; align-items: center; gap: 8px; }
.ps-unit__n { width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center; font-size: 11px; font-weight: 700; color: #fff; background: var(--color-primary); }
.ps-input.is-invalid { border-color: #f4a49b; }
.ps-add { align-self: flex-start; }
</style>
