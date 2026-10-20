<template>
  <div
    v-if="resultOpen"
    class="property-compare-modal-overlay"
    @click.self="closeResults"
  >
    <div
      ref="dialogRef"
      class="property-compare__sheet"
      role="dialog"
      aria-modal="true"
      aria-labelledby="property-compare-title"
      tabindex="-1"
    >
      <div class="property-compare__bar">
        <div id="property-compare-title" class="property-compare__title">Comparison</div>
        <div class="property-compare__bar-actions">
          <button type="button" class="property-compare__download" :disabled="scanning || exporting || !brief" @click="downloadPng">
            {{ exporting ? 'Preparing…' : 'Download' }}
          </button>
          <button type="button" class="property-compare__close" @click="closeResults">Close</button>
        </div>
      </div>

      <div class="property-compare__body">
      <div v-if="scanning" class="property-compare__scanner" role="status">
        <span class="property-compare__scanner-line" aria-hidden="true"></span>
        <p>Comparing the selected listings…</p>
      </div>

      <p v-else-if="compareError" class="property-compare__hint property-compare__hint--error">{{ compareError }}</p>

      <div v-else-if="brief" class="property-compare__board">
        <div v-if="scanPass" class="property-compare__scan-pass" aria-hidden="true" @animationend="scanPass = false"></div>
        <div class="property-compare__cards" :style="{ '--compare-cols': brief.columnCount }">
          <article v-for="listing in readyListings" :key="listing.id">
            <div class="property-compare__photo">
              <img v-if="cardImage(listing) && !brokenImages[listing.id]" :src="cardImage(listing)" alt="" @error="brokenImages[listing.id] = true" />
              <div v-else class="property-compare__thumb-empty">No image</div>
            </div>
            <strong>{{ cardName(listing) }}</strong>
            <small class="property-compare__price">{{ cardPrice(listing) }}</small>
            <small v-if="cardPlace(listing)">{{ cardPlace(listing) }}</small>
          </article>
        </div>
        <div class="property-compare__table" role="table">
          <div
            v-for="item in brief.rows"
            :key="item.id"
            class="property-compare__row"
            :style="{ '--compare-cols': brief.columnCount }"
            role="row"
          >
            <div role="rowheader">{{ item.label }}</div>
            <div
              v-for="(value, index) in item.values"
              :key="`${item.id}-${index}`"
              role="cell"
              :class="{ 'is-blank': value === 'Not set' || value === 'Not available' }"
            >
              {{ value }}
            </div>
          </div>
        </div>
        <p v-if="exportError" class="property-compare__hint property-compare__hint--error">{{ exportError }}</p>
      </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import api from '@/plugins/axios'
import { compareProperties, listingHeadline, listingImages } from '@/composables/propertyComparison.js'
import { isActiveListing, usePropertyCompareSelection } from '@/composables/propertyCompareSelection'

const BRIEF_IDS = ['price', 'price-sqft', 'purpose', 'type', 'bedrooms', 'bathrooms', 'size-sqft', 'location', 'furnishing']

const { resultOpen, closeResults, selected } = usePropertyCompareSelection()
const dialogRef = ref(null)
const readyListings = ref([])
const scanning = ref(false)
const scanPass = ref(false)
const compareError = ref('')
const exportError = ref('')
const exporting = ref(false)
const brokenImages = reactive({})

const comparison = computed(() => (readyListings.value.length >= 2 ? compareProperties(readyListings.value) : null))
const brief = computed(() => {
  if (!comparison.value) return null
  const rows = []
  for (const group of comparison.value.groups) {
    for (const item of group.rows) {
      if (BRIEF_IDS.includes(item.id)) rows.push(item)
    }
  }
  return {
    rows,
    columnCount: comparison.value.columnCount,
  }
})

function cardName(listing) {
  return listingHeadline(listing).name
}
function cardPrice(listing) {
  return listingHeadline(listing).price || 'Price not set'
}
function cardImage(listing) {
  return listing.image || listingImages(listing)[0] || null
}
function cardPlace(listing) {
  const area = listing.area
  if (!area) return listingHeadline(listing).reference
  return typeof area === 'string' ? area : (area.title || area.name || null)
}
function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
}

async function loadListings() {
  scanning.value = true
  scanPass.value = false
  compareError.value = ''
  exportError.value = ''
  Object.keys(brokenImages).forEach((id) => { delete brokenImages[id] })
  const loaded = []
  await Promise.all(selected.value.map(async (item) => {
    try {
      const response = await api.get(`/listings/properties/${item.id}`, { params: { for_compare: 1 } })
      const detail = response.data?.data
      if (detail && isActiveListing(detail)) {
        loaded.push({ ...item, ...detail, image: item.image })
        return
      }
    } catch {
      /* The card already on the page is still safe to show when it is an active unit. */
    }
    if (isActiveListing(item)) loaded.push(item)
  }))
  const order = new Map(selected.value.map((item, index) => [String(item.id), index]))
  readyListings.value = loaded.sort((a, b) => (order.get(String(a.id)) ?? 0) - (order.get(String(b.id)) ?? 0))
  scanning.value = false
  if (readyListings.value.length < 2) {
    compareError.value = 'At least two active units are needed.'
    return
  }
  scanPass.value = !prefersReducedMotion()
}

async function downloadPng() {
  if (!brief.value || exporting.value || scanning.value) return
  exporting.value = true
  exportError.value = ''
  try {
    const { downloadComparisonPng } = await import('@/composables/propertyCompareExport.js')
    await downloadComparisonPng({
      listings: readyListings.value.map((listing) => ({
        ...listingHeadline(listing),
        image: cardImage(listing),
      })),
      groups: [{ title: 'Comparison', rows: brief.value.rows }],
    })
  } catch {
    exportError.value = 'Could not download the comparison. Try again.'
  } finally {
    exporting.value = false
  }
}

function onKeydown(event) {
  if (event.key === 'Escape' && resultOpen.value) closeResults()
}

watch(resultOpen, async (isOpen) => {
  if (!isOpen) {
    scanning.value = false
    scanPass.value = false
    return
  }
  await nextTick()
  dialogRef.value?.focus()
  await loadListings()
})

document.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  scanning.value = false
})
</script>

<style scoped>
.property-compare-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: color-mix(in srgb, #0f172a 35%, transparent);
}
.property-compare__sheet {
  width: min(1180px, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 0;
  border-radius: 18px;
  box-sizing: border-box;
  background: var(--bg-card, #fff);
  color: var(--text-primary, #0f172a);
  box-shadow: 0 18px 50px color-mix(in srgb, #0f172a 22%, transparent);
}
.property-compare__bar {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.95rem 1.15rem;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  background: var(--bg-card, #fff);
}
.property-compare__title {
  font-size: 1.35rem;
  font-weight: 750;
  line-height: 1.2;
}
.property-compare__bar-actions {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex: 0 0 auto;
}
.property-compare__download,
.property-compare__close {
  font: inherit;
  cursor: pointer;
  border-radius: 999px;
  padding: 0.55rem 1.05rem;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1;
}
.property-compare__download {
  border: 0;
  background: var(--color-primary, #6d28d9);
  color: #fff;
}
.property-compare__download:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.property-compare__close {
  border: 1px solid var(--border-color, #e2e8f0);
  background: transparent;
  color: var(--text-primary, #0f172a);
}
.property-compare__body {
  overflow: auto;
  padding: 1.15rem 1.2rem 1.4rem;
}
.property-compare__hint {
  margin: 0.85rem 0 0;
  color: var(--text-secondary, #64748b);
  font-size: 1.05rem;
  text-align: center;
}
.property-compare__hint--error {
  color: #b45309;
}
.property-compare__scanner {
  position: relative;
  min-height: 140px;
  margin-top: 0.8rem;
  overflow: hidden;
  border-radius: 12px;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--text-secondary, #64748b) 8%, transparent);
}
.property-compare__scanner-line,
.property-compare__scan-pass {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background: var(--color-primary, #4f46e5);
}
.property-compare__scanner-line {
  animation: property-compare-scan 1.1s linear infinite;
}
.property-compare__board {
  position: relative;
  margin-top: 0.75rem;
}
.property-compare__scan-pass {
  top: 0;
  height: 28%;
  pointer-events: none;
  background: linear-gradient(180deg, transparent, color-mix(in srgb, var(--color-primary, #4f46e5) 18%, transparent), transparent);
  animation: property-compare-pass 0.7s ease-out 1;
}
.property-compare__cards {
  display: grid;
  grid-template-columns: repeat(var(--compare-cols, 2), minmax(0, 1fr));
  gap: 1rem;
  justify-content: center;
}
.property-compare__cards article {
  min-width: 0;
  text-align: center;
}
.property-compare__photo {
  height: 220px;
  border-radius: 14px;
  overflow: hidden;
  background: color-mix(in srgb, var(--text-secondary, #64748b) 12%, transparent);
}
.property-compare__photo img,
.property-compare__thumb-empty {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.property-compare__thumb-empty {
  display: grid;
  place-items: center;
  font-size: 1rem;
  color: var(--text-secondary, #64748b);
}
.property-compare__cards strong,
.property-compare__cards small {
  display: block;
}
.property-compare__cards strong {
  margin-top: 0.8rem;
  font-size: 1.35rem;
  line-height: 1.3;
}
.property-compare__cards small {
  color: var(--text-secondary, #64748b);
  font-size: 1.05rem;
  line-height: 1.4;
}
.property-compare__price {
  color: var(--text-primary, #0f172a) !important;
  font-size: 1.2rem !important;
  font-weight: 700;
}
.property-compare__table {
  margin-top: 0.75rem;
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 12px;
  overflow: auto;
}
.property-compare__row {
  display: grid;
  grid-template-columns: 168px repeat(var(--compare-cols, 2), minmax(0, 1fr));
}
.property-compare__row > div {
  padding: 0.9rem 1rem;
  border-top: 1px solid var(--border-color, #e2e8f0);
  font-size: 1.12rem;
  line-height: 1.4;
  min-width: 0;
  text-align: center;
  word-break: break-word;
}
.property-compare__row > div[role="rowheader"] {
  text-align: left;
  font-weight: 700;
}
.property-compare__row:first-child > div {
  border-top: 0;
}
.property-compare__row .is-blank {
  color: var(--text-secondary, #64748b);
  font-style: italic;
}
@keyframes property-compare-scan {
  from { top: 0; }
  to { top: 100%; }
}
@keyframes property-compare-pass {
  from { top: -20%; }
  to { top: 100%; }
}
@media (prefers-reduced-motion: reduce) {
  .property-compare__scanner-line,
  .property-compare__scan-pass {
    animation: none;
    display: none;
  }
}
</style>
