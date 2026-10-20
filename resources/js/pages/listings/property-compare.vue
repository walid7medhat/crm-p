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
        <div id="property-compare-title" class="property-compare__title">Property Comparison</div>
        <div class="property-compare__bar-actions">
          <button type="button" class="property-compare__download" :disabled="scanning || !!exporting || !brief" @click="downloadFile('png')">
            {{ exporting === 'png' ? 'Preparing…' : 'PNG' }}
          </button>
          <button type="button" class="property-compare__pdf" :disabled="scanning || !!exporting || !brief" @click="downloadFile('pdf')">
            {{ exporting === 'pdf' ? 'Preparing…' : 'PDF' }}
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
          <article v-for="(listing, index) in readyListings" :key="listing.id" :style="{ '--i': index }">
            <div class="property-compare__photo">
              <img v-if="cardImage(listing) && !brokenImages[listing.id]" :src="cardImage(listing)" alt="" @error="brokenImages[listing.id] = true" />
              <div v-else class="property-compare__thumb-empty">No image</div>
            </div>
            <strong>{{ cardName(listing) }}</strong>
            <small class="property-compare__price">{{ cardPrice(listing) }}</small>
            <small v-if="cardPlace(listing)" class="property-compare__place">{{ cardPlace(listing) }}</small>
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
            <div role="rowheader">{{ shortLabel(item) }}</div>
            <div
              v-for="(value, index) in item.values"
              :key="`${item.id}-${index}`"
              role="cell"
              :class="{ 'is-blank': value === 'Not set' || value === 'Not available' }"
            >
              {{ cellText(value) }}
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
const SHORT_LABELS = {
  price: 'Price',
  'price-sqft': 'Per sqft',
  purpose: 'Purpose',
  type: 'Type',
  bedrooms: 'Beds',
  bathrooms: 'Baths',
  'size-sqft': 'Size',
  location: 'Area',
  furnishing: 'Furnishing',
}

const { resultOpen, closeResults, selected } = usePropertyCompareSelection()
const dialogRef = ref(null)
const readyListings = ref([])
const scanning = ref(false)
const scanPass = ref(false)
const compareError = ref('')
const exportError = ref('')
const exporting = ref('')
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
function shortLabel(item) {
  return SHORT_LABELS[item.id] || item.label
}
function cellText(value) {
  if (value === 'Not set' || value === 'Not available') return '—'
  return value
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

async function downloadFile(format) {
  if (!brief.value || exporting.value || scanning.value) return
  exporting.value = format
  exportError.value = ''
  try {
    const { downloadComparison } = await import('@/composables/propertyCompareExport.js')
    await downloadComparison({
      listings: readyListings.value.map((listing) => ({
        ...listingHeadline(listing),
        image: cardImage(listing),
        place: cardPlace(listing),
      })),
      groups: [{
        title: 'Compare',
        rows: brief.value.rows.map((item) => ({
          ...item,
          label: shortLabel(item),
          values: item.values.map(cellText),
        })),
      }],
    }, format)
  } catch {
    exportError.value = 'Could not download the comparison. Try again.'
  } finally {
    exporting.value = ''
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
  perspective: 1400px;
  background: color-mix(in srgb, #0f172a 46%, transparent);
  backdrop-filter: blur(6px);
  animation: property-compare-fade 0.28s ease;
}
.property-compare__sheet {
  width: min(980px, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 0;
  border-radius: 22px;
  box-sizing: border-box;
  background: color-mix(in srgb, var(--bg-card, #fff) 92%, #eef2ff);
  color: var(--text-primary, #0f172a);
  border: 1px solid color-mix(in srgb, #fff 70%, transparent);
  box-shadow:
    0 28px 60px color-mix(in srgb, #0f172a 28%, transparent),
    0 1px 0 color-mix(in srgb, #fff 80%, transparent) inset;
  transform-origin: center 20%;
  animation: property-compare-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}
.property-compare__bar {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.7rem 0.9rem;
  border-bottom: 1px solid color-mix(in srgb, var(--border-color, #e2e8f0) 80%, transparent);
  background: color-mix(in srgb, var(--bg-card, #fff) 72%, transparent);
}
.property-compare__title {
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.2;
}
.property-compare__bar-actions {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex: 0 0 auto;
}
.property-compare__download,
.property-compare__pdf,
.property-compare__close {
  font: inherit;
  cursor: pointer;
  border-radius: 999px;
  padding: 0.4rem 0.8rem;
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.property-compare__download:hover,
.property-compare__pdf:hover,
.property-compare__close:hover {
  transform: translateY(-1px);
}
.property-compare__download {
  border: 0;
  background: var(--color-primary, #6d28d9);
  color: #fff;
  box-shadow: 0 8px 16px color-mix(in srgb, var(--color-primary, #6d28d9) 35%, transparent);
}
.property-compare__pdf {
  border: 1px solid color-mix(in srgb, var(--color-primary, #6d28d9) 45%, transparent);
  background: color-mix(in srgb, var(--color-primary, #6d28d9) 8%, var(--bg-card, #fff));
  color: var(--color-primary, #6d28d9);
}
.property-compare__download:disabled,
.property-compare__pdf:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
.property-compare__close {
  border: 1px solid var(--border-color, #e2e8f0);
  background: color-mix(in srgb, var(--bg-card, #fff) 80%, transparent);
  color: var(--text-primary, #0f172a);
}
.property-compare__body {
  overflow: auto;
  padding: 0.85rem 0.9rem 1rem;
}
.property-compare__hint {
  margin: 0.6rem 0 0;
  color: var(--text-secondary, #64748b);
  font-size: 0.82rem;
  text-align: center;
}
.property-compare__hint--error {
  color: #b45309;
}
.property-compare__scanner {
  position: relative;
  min-height: 120px;
  overflow: hidden;
  border-radius: 14px;
  display: grid;
  place-items: center;
  font-size: 0.82rem;
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
  gap: 0.7rem;
  perspective: 900px;
}
.property-compare__cards article {
  min-width: 0;
  text-align: center;
  padding: 0.45rem 0.45rem 0.55rem;
  border-radius: 16px;
  background: color-mix(in srgb, var(--bg-card, #fff) 88%, transparent);
  border: 1px solid color-mix(in srgb, #fff 65%, var(--border-color, #e2e8f0));
  box-shadow: 0 10px 22px color-mix(in srgb, #0f172a 10%, transparent);
  transform-style: preserve-3d;
  animation: property-compare-card 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
  animation-delay: calc(var(--i, 0) * 70ms);
  transition: transform 0.28s ease, box-shadow 0.28s ease;
}
.property-compare__cards article:hover {
  transform: translateY(-6px) rotateX(5deg);
  box-shadow: 0 18px 32px color-mix(in srgb, #0f172a 16%, transparent);
}
.property-compare__photo {
  height: 132px;
  border-radius: 12px;
  overflow: hidden;
  background: color-mix(in srgb, var(--text-secondary, #64748b) 12%, transparent);
  box-shadow: 0 10px 18px color-mix(in srgb, #0f172a 18%, transparent);
  transform: translateZ(16px);
}
.property-compare__photo img,
.property-compare__thumb-empty {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.property-compare__thumb-empty {
  display: grid;
  place-items: center;
  font-size: 0.75rem;
  color: var(--text-secondary, #64748b);
}
.property-compare__cards strong,
.property-compare__cards small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
}
.property-compare__cards strong {
  margin-top: 0.55rem;
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.25;
  white-space: nowrap;
}
.property-compare__cards small {
  color: var(--text-secondary, #64748b);
  font-size: 0.75rem;
  line-height: 1.3;
  white-space: nowrap;
}
.property-compare__price {
  margin-top: 0.15rem;
  color: var(--text-primary, #0f172a) !important;
  font-size: 0.8rem !important;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.property-compare__table {
  margin-top: 0.7rem;
  border: 1px solid color-mix(in srgb, var(--border-color, #e2e8f0) 90%, transparent);
  border-radius: 14px;
  overflow: auto;
  background: color-mix(in srgb, var(--bg-card, #fff) 80%, transparent);
  animation: property-compare-fade 0.45s ease 0.15s both;
}
.property-compare__row {
  display: grid;
  grid-template-columns: 84px repeat(var(--compare-cols, 2), minmax(0, 1fr));
}
.property-compare__row > div {
  padding: 0.42rem 0.55rem;
  border-top: 1px solid color-mix(in srgb, var(--border-color, #e2e8f0) 80%, transparent);
  font-size: 0.78rem;
  line-height: 1.3;
  min-width: 0;
  text-align: center;
  word-break: break-word;
  font-variant-numeric: tabular-nums;
}
.property-compare__row > div[role="rowheader"] {
  text-align: left;
  font-weight: 700;
  letter-spacing: 0.01em;
  color: var(--text-secondary, #64748b);
}
.property-compare__row:first-child > div {
  border-top: 0;
}
.property-compare__row .is-blank {
  color: color-mix(in srgb, var(--text-secondary, #94a3b8) 80%, transparent);
  font-style: normal;
}
@keyframes property-compare-fade {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes property-compare-rise {
  from { opacity: 0; transform: rotateX(10deg) translateY(26px) scale(0.96); }
  to { opacity: 1; transform: none; }
}
@keyframes property-compare-card {
  from { opacity: 0; transform: translateY(16px) rotateX(8deg); }
  to { opacity: 1; transform: none; }
}
@keyframes property-compare-scan {
  from { top: 0; }
  to { top: 100%; }
}
@keyframes property-compare-pass {
  from { top: -20%; }
  to { top: 100%; }
}
@media (max-width: 1024px) {
  .property-compare-modal-overlay {
    padding: max(0.75rem, env(safe-area-inset-top)) 0.75rem max(0.75rem, env(safe-area-inset-bottom));
  }
  .property-compare__sheet {
    width: min(100%, calc(100vw - 1.5rem));
    max-height: calc(100dvh - 1.5rem);
    border-radius: 18px;
  }
  .property-compare__photo {
    height: 112px;
    transform: none;
  }
  .property-compare__cards article:hover {
    transform: none;
  }
  .property-compare__table {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .property-compare__row {
    grid-template-columns: 4.6rem repeat(var(--compare-cols, 2), minmax(8.75rem, 1fr));
    min-width: calc(4.6rem + var(--compare-cols, 2) * 8.75rem);
  }
  .property-compare__row > div[role="rowheader"] {
    position: sticky;
    left: 0;
    z-index: 1;
    background: var(--bg-card, #fff);
  }
}
@media (max-width: 767px) {
  .property-compare-modal-overlay {
    padding: 0;
    align-items: stretch;
  }
  .property-compare__sheet {
    width: 100%;
    height: 100dvh;
    max-height: 100dvh;
    border-radius: 0;
  }
  .property-compare__bar {
    flex-wrap: wrap;
    row-gap: 0.45rem;
    padding: calc(0.7rem + env(safe-area-inset-top)) 0.85rem 0.7rem;
  }
  .property-compare__title {
    flex: 1 1 100%;
    font-size: 0.92rem;
  }
  .property-compare__download,
  .property-compare__pdf,
  .property-compare__close {
    min-height: 2.25rem;
    padding: 0.45rem 0.85rem;
  }
  .property-compare__body {
    padding: 0.75rem 0.75rem calc(0.9rem + env(safe-area-inset-bottom));
  }
  .property-compare__cards {
    display: flex;
    gap: 0.65rem;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    perspective: none;
    -webkit-overflow-scrolling: touch;
  }
  .property-compare__cards article {
    flex: 0 0 min(78%, 280px);
    scroll-snap-align: center;
  }
  .property-compare__photo {
    height: 150px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .property-compare-modal-overlay,
  .property-compare__sheet,
  .property-compare__cards article,
  .property-compare__table,
  .property-compare__scanner-line,
  .property-compare__scan-pass {
    animation: none;
  }
  .property-compare__cards article:hover,
  .property-compare__download:hover,
  .property-compare__close:hover,
  .property-compare__photo {
    transform: none;
  }
}
</style>
