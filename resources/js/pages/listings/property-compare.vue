<template>
  <div class="property-compare">
    <header class="property-compare__head">
      <div>
        <h1>Compare properties</h1>
        <p>Pick two listings you can already open. Differences are marked. A blank field stays “Not set” and is not treated as a match.</p>
      </div>
      <button v-if="leftId || rightId" type="button" class="property-compare__text-btn" @click="clearBoth">
        Start over
      </button>
    </header>

    <div class="property-compare__pickers">
      <section v-for="slot in slots" :key="slot" class="property-compare__picker">
        <div class="property-compare__picker-label">{{ slot === 'left' ? 'Property A' : 'Property B' }}</div>

        <article v-if="listings[slot]" class="property-compare__card">
          <div class="property-compare__media">
            <img
              v-if="headlines[slot].image"
              :src="headlines[slot].image"
              alt=""
              @error="onImageError"
            />
            <div v-else class="property-compare__media-empty">No image</div>
          </div>
          <div class="property-compare__card-body">
            <h2>{{ headlines[slot].name }}</h2>
            <p v-if="headlines[slot].reference" class="property-compare__ref">{{ headlines[slot].reference }}</p>
            <p class="property-compare__meta">
              <span>{{ headlines[slot].price || 'Price not set' }}</span>
              <span v-if="headlines[slot].type">{{ headlines[slot].type }}</span>
              <span v-if="headlines[slot].bedrooms">{{ headlines[slot].bedrooms }}</span>
            </p>
            <div class="property-compare__card-actions">
              <button type="button" class="property-compare__text-btn" @click="replace(slot)">Change</button>
              <router-link :to="`/property-details/${listings[slot].id}`">Open listing</router-link>
            </div>
          </div>
        </article>

        <div v-else class="property-compare__search">
          <label class="visually-hidden" :for="`compare-search-${slot}`">Search {{ slot === 'left' ? 'property A' : 'property B' }}</label>
          <input
            :id="`compare-search-${slot}`"
            :ref="(el) => setInput(slot, el)"
            v-model="queries[slot]"
            type="search"
            autocomplete="off"
            placeholder="Search by project, reference, or area"
            @focus="activeSlot = slot"
            @input="onSearchInput(slot)"
          />
          <p v-if="loadingSearch[slot]" class="property-compare__hint">Searching listings…</p>
          <p v-else-if="errors[slot]" class="property-compare__hint property-compare__hint--error">{{ errors[slot] }}</p>
          <ul v-if="activeSlot === slot && results[slot].length" class="property-compare__results" role="listbox">
            <li v-for="item in results[slot]" :key="item.id">
              <button type="button" @click="choose(slot, item)">
                <span class="property-compare__result-title">{{ item.title || item.reference_number || 'Listing' }}</span>
                <span class="property-compare__result-meta">
                  {{ resultMeta(item) }}
                </span>
              </button>
            </li>
          </ul>
          <p v-else-if="activeSlot === slot && searched[slot] && !loadingSearch[slot]" class="property-compare__hint">
            No listings match that search.
          </p>
        </div>

        <p v-if="loadErrors[slot]" class="property-compare__hint property-compare__hint--error">{{ loadErrors[slot] }}</p>
        <p v-else-if="loadingDetail[slot]" class="property-compare__hint">Loading listing…</p>
      </section>
    </div>

    <p v-if="sameListing" class="property-compare__banner">Choose two different listings.</p>

    <section v-else-if="comparison" class="property-compare__board" aria-live="polite">
      <div class="property-compare__toolbar">
        <p>
          <strong>{{ comparison.counts.different }}</strong> different
          <span>·</span>
          <strong>{{ comparison.counts.missing }}</strong> not set on both
          <span>·</span>
          <strong>{{ comparison.counts.same }}</strong> the same
        </p>
        <div class="property-compare__filters" role="group" aria-label="Which rows to show">
          <button type="button" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">All fields</button>
          <button type="button" :class="{ 'is-active': filter === 'differences' }" @click="filter = 'differences'">Differences only</button>
        </div>
      </div>

      <div v-for="group in visibleGroups" :key="group.title" class="property-compare__group">
        <h3>{{ group.title }}</h3>
        <div class="property-compare__table" role="table">
          <div class="property-compare__row property-compare__row--head" role="row">
            <div role="columnheader">Field</div>
            <div role="columnheader">{{ headlines.left.name }}</div>
            <div role="columnheader">{{ headlines.right.name }}</div>
          </div>
          <div
            v-for="item in group.rows"
            :key="item.id"
            class="property-compare__row"
            :class="`is-${item.state}`"
            role="row"
          >
            <div role="rowheader">
              {{ item.label }}
              <span v-if="item.state === 'different'" class="property-compare__tag">Different</span>
              <span v-else-if="item.state === 'missing'" class="property-compare__tag property-compare__tag--missing">Not set</span>
            </div>
            <div role="cell" :class="{ 'is-blank': item.left === 'Not set' || item.left === 'Not available' }">{{ item.left }}</div>
            <div role="cell" :class="{ 'is-blank': item.right === 'Not set' || item.right === 'Not available' }">{{ item.right }}</div>
          </div>
        </div>
      </div>

      <p v-if="!visibleGroups.length" class="property-compare__hint">These two listings match on every compared field.</p>
    </section>

    <p v-else-if="leftId && rightId && (loadingDetail.left || loadingDetail.right)" class="property-compare__hint">Loading both listings…</p>
    <p v-else class="property-compare__hint">Search above and select one listing for each side.</p>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { compareProperties, listingHeadline } from '@/composables/propertyComparison.js'

const route = useRoute()
const router = useRouter()
const slots = ['left', 'right']

const queries = reactive({ left: '', right: '' })
const results = reactive({ left: [], right: [] })
const searched = reactive({ left: false, right: false })
const loadingSearch = reactive({ left: false, right: false })
const loadingDetail = reactive({ left: false, right: false })
const errors = reactive({ left: '', right: '' })
const loadErrors = reactive({ left: '', right: '' })
const listings = reactive({ left: null, right: null })
const inputs = { left: null, right: null }
const activeSlot = ref('left')
const filter = ref('all')
const cache = new Map()
const timers = { left: null, right: null }
let searchToken = { left: 0, right: 0 }

const leftId = computed(() => String(route.query.left || ''))
const rightId = computed(() => String(route.query.right || ''))
const headlines = computed(() => ({
  left: listingHeadline(listings.left),
  right: listingHeadline(listings.right),
}))
const sameListing = computed(() => leftId.value && leftId.value === rightId.value)
const comparison = computed(() => {
  if (!listings.left || !listings.right || sameListing.value) return null
  return compareProperties(listings.left, listings.right)
})
const visibleGroups = computed(() => {
  if (!comparison.value) return []
  return comparison.value.groups
    .map((group) => ({
      ...group,
      rows: filter.value === 'differences'
        ? group.rows.filter((item) => item.state !== 'same')
        : group.rows,
    }))
    .filter((group) => group.rows.length)
})

function setInput(slot, el) {
  inputs[slot] = el
}

function resultMeta(item) {
  const parts = []
  if (item.reference_number) parts.push(item.reference_number)
  if (item.area) parts.push(item.area)
  if (item.price) parts.push(`AED ${Number(item.price).toLocaleString('en-AE', { maximumFractionDigits: 0 })}`)
  return parts.join(' · ') || 'Listing'
}

function onImageError(event) {
  event.target.style.display = 'none'
}

function replace(slot) {
  const query = { ...route.query }
  delete query[slot]
  listings[slot] = null
  queries[slot] = ''
  results[slot] = []
  searched[slot] = false
  loadErrors[slot] = ''
  activeSlot.value = slot
  router.replace({ query })
  requestAnimationFrame(() => inputs[slot]?.focus())
}

function clearBoth() {
  listings.left = null
  listings.right = null
  queries.left = ''
  queries.right = ''
  results.left = []
  results.right = []
  loadErrors.left = ''
  loadErrors.right = ''
  router.replace({ query: {} })
}

function choose(slot, item) {
  const other = slot === 'left' ? rightId.value : leftId.value
  if (other && String(item.id) === other) {
    errors[slot] = 'That listing is already on the other side.'
    return
  }
  errors[slot] = ''
  results[slot] = []
  queries[slot] = ''
  const query = { ...route.query, [slot]: String(item.id) }
  router.replace({ query })
}

function onSearchInput(slot) {
  activeSlot.value = slot
  errors[slot] = ''
  clearTimeout(timers[slot])
  const term = queries[slot].trim()
  if (term.length < 2) {
    results[slot] = []
    searched[slot] = false
    return
  }
  timers[slot] = setTimeout(() => search(slot, term), 280)
}

async function search(slot, term) {
  const token = ++searchToken[slot]
  loadingSearch[slot] = true
  searched[slot] = false
  try {
    const response = await api.get('/listings/properties', {
      params: { search: term, per_page: 8 },
    })
    if (token !== searchToken[slot]) return
    const rows = Array.isArray(response.data?.data) ? response.data.data : []
    const blocked = new Set([leftId.value, rightId.value].filter(Boolean))
    results[slot] = rows.filter((item) => !blocked.has(String(item.id)))
    searched[slot] = true
  } catch {
    if (token !== searchToken[slot]) return
    results[slot] = []
    errors[slot] = 'Search failed. Try again.'
  } finally {
    if (token === searchToken[slot]) loadingSearch[slot] = false
  }
}

async function loadSlot(slot, id) {
  if (!id) {
    listings[slot] = null
    loadErrors[slot] = ''
    loadingDetail[slot] = false
    return
  }
  if (listings[slot] && String(listings[slot].id) === id) return
  loadingDetail[slot] = true
  loadErrors[slot] = ''
  try {
    if (!cache.has(id)) {
      const response = await api.get(`/listings/properties/${id}`)
      cache.set(id, response.data?.data || null)
    }
    const listing = cache.get(id)
    if (!listing) {
      listings[slot] = null
      loadErrors[slot] = 'This listing is not available.'
      return
    }
    listings[slot] = listing
  } catch {
    listings[slot] = null
    cache.delete(id)
    loadErrors[slot] = 'This listing is not available to you.'
  } finally {
    loadingDetail[slot] = false
  }
}

function onPointerDown(event) {
  const target = event.target
  if (!(target instanceof Element)) return
  if (target.closest('.property-compare__search')) return
  activeSlot.value = ''
}

watch(leftId, (id) => loadSlot('left', id), { immediate: true })
watch(rightId, (id) => loadSlot('right', id), { immediate: true })

onMounted(() => document.addEventListener('pointerdown', onPointerDown))
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onPointerDown)
  clearTimeout(timers.left)
  clearTimeout(timers.right)
})
</script>

<style scoped>
.property-compare {
  padding: 1.25rem 1rem 2.5rem;
  max-width: 1100px;
  margin: 0 auto;
  color: var(--text-primary, #0f172a);
}
.property-compare__head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  margin-bottom: 1rem;
}
.property-compare h1 {
  margin: 0 0 0.35rem;
  font-size: 1.45rem;
  font-weight: 650;
}
.property-compare__head p,
.property-compare__hint,
.property-compare__banner {
  margin: 0;
  color: var(--text-secondary, #64748b);
  font-size: 0.92rem;
}
.property-compare__banner {
  margin: 0.75rem 0;
  padding: 0.7rem 0.85rem;
  border-radius: 10px;
  background: color-mix(in srgb, #d97706 14%, transparent);
  color: inherit;
}
.property-compare__pickers {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem;
}
.property-compare__picker {
  min-width: 0;
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 14px;
  padding: 0.85rem;
  background: var(--card-bg, #fff);
}
.property-compare__picker-label {
  font-size: 0.75rem;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.55rem;
  color: var(--text-secondary, #64748b);
}
.property-compare__card {
  display: grid;
  grid-template-columns: 112px 1fr;
  gap: 0.75rem;
}
.property-compare__media,
.property-compare__media-empty {
  width: 112px;
  height: 84px;
  border-radius: 10px;
  overflow: hidden;
  background: color-mix(in srgb, var(--text-secondary, #64748b) 12%, transparent);
}
.property-compare__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.property-compare__media-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  color: var(--text-secondary, #64748b);
}
.property-compare__card h2 {
  margin: 0;
  font-size: 1rem;
  line-height: 1.3;
}
.property-compare__ref,
.property-compare__meta {
  margin: 0.2rem 0 0;
  font-size: 0.82rem;
  color: var(--text-secondary, #64748b);
}
.property-compare__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.6rem;
}
.property-compare__card-actions {
  display: flex;
  gap: 0.85rem;
  margin-top: 0.45rem;
  font-size: 0.85rem;
}
.property-compare__card-actions a {
  color: var(--color-primary, #4f46e5);
  text-decoration: none;
}
.property-compare__text-btn,
.property-compare__filters button,
.property-compare__results button {
  border: 0;
  background: transparent;
  color: var(--color-primary, #4f46e5);
  padding: 0;
  cursor: pointer;
  font: inherit;
}
.property-compare__search {
  position: relative;
}
.property-compare__search input {
  width: 100%;
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  padding: 0.65rem 0.75rem;
  background: transparent;
  color: inherit;
}
.property-compare__search input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary, #4f46e5) 45%, transparent);
  border-color: var(--color-primary, #4f46e5);
}
.property-compare__results {
  list-style: none;
  margin: 0.4rem 0 0;
  padding: 0.25rem;
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  max-height: 240px;
  overflow: auto;
  background: var(--card-bg, #fff);
}
.property-compare__results button {
  width: 100%;
  text-align: left;
  color: inherit;
  padding: 0.5rem 0.55rem;
  border-radius: 8px;
}
.property-compare__results button:hover {
  background: color-mix(in srgb, var(--color-primary, #4f46e5) 8%, transparent);
}
.property-compare__result-title,
.property-compare__result-meta {
  display: block;
}
.property-compare__result-meta {
  color: var(--text-secondary, #64748b);
  font-size: 0.78rem;
}
.property-compare__hint {
  margin-top: 0.45rem;
}
.property-compare__hint--error {
  color: #b45309;
}
.property-compare__board {
  margin-top: 1.1rem;
}
.property-compare__toolbar {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}
.property-compare__toolbar p {
  margin: 0;
  font-size: 0.9rem;
}
.property-compare__toolbar span {
  margin: 0 0.25rem;
  color: var(--text-secondary, #64748b);
}
.property-compare__filters {
  display: flex;
  gap: 0.35rem;
}
.property-compare__filters button {
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 999px;
  padding: 0.3rem 0.7rem;
  color: inherit;
}
.property-compare__filters button.is-active {
  background: var(--color-primary, #4f46e5);
  border-color: var(--color-primary, #4f46e5);
  color: #fff;
}
.property-compare__group {
  margin-top: 0.85rem;
}
.property-compare__group h3 {
  margin: 0 0 0.4rem;
  font-size: 0.82rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-secondary, #64748b);
}
.property-compare__table {
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 12px;
  overflow: hidden;
}
.property-compare__row {
  display: grid;
  grid-template-columns: minmax(140px, 0.9fr) 1fr 1fr;
}
.property-compare__row > div {
  padding: 0.65rem 0.75rem;
  border-top: 1px solid var(--border-color, #e2e8f0);
  min-width: 0;
  word-break: break-word;
}
.property-compare__row--head > div {
  border-top: 0;
  font-size: 0.75rem;
  font-weight: 650;
  color: var(--text-secondary, #64748b);
}
.property-compare__row.is-different > div {
  background: color-mix(in srgb, #d97706 14%, transparent);
}
.property-compare__row.is-missing > div {
  background: color-mix(in srgb, var(--text-secondary, #64748b) 8%, transparent);
}
.property-compare__row.is-missing .is-blank,
.property-compare__row .is-blank {
  font-style: italic;
  color: var(--text-secondary, #64748b);
}
.property-compare__tag {
  display: inline-block;
  margin-left: 0.35rem;
  padding: 0.05rem 0.35rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-style: normal;
  letter-spacing: 0;
  text-transform: none;
  background: color-mix(in srgb, #d97706 22%, transparent);
}
.property-compare__tag--missing {
  background: color-mix(in srgb, var(--text-secondary, #64748b) 18%, transparent);
}
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
@media (max-width: 760px) {
  .property-compare {
    padding: 0.85rem 0.7rem 5.5rem;
  }
  .property-compare__head,
  .property-compare__pickers,
  .property-compare__card {
    display: block;
  }
  .property-compare__picker + .property-compare__picker {
    margin-top: 0.75rem;
  }
  .property-compare__media,
  .property-compare__media-empty {
    width: 100%;
    height: 140px;
    margin-bottom: 0.6rem;
  }
  .property-compare__row {
    grid-template-columns: 1fr 1fr;
  }
  .property-compare__row > div:first-child {
    grid-column: 1 / -1;
    padding-bottom: 0.2rem;
    font-weight: 650;
  }
  .property-compare__row--head {
    display: none;
  }
}
</style>
