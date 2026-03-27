<template>
  <div class="notify-me-page">
    <Teleport to="body">
      <div class="notifyme-overlay" @click="close">
        <div class="notifyme-modal" role="dialog" aria-modal="true" @click.stop>
          <div class="notifyme-header">
            <div>
              <h6 class="notifyme-title">Notify me</h6>
              <p class="notifyme-subtitle">
                Choose your search criteria. We’ll email you when something related is available.
              </p>
            </div>
            <button type="button" class="notifyme-close" @click="close" aria-label="Close">
              <iconify-icon icon="lucide:x" class="icon" />
            </button>
          </div>

          <div class="notifyme-body">
            <div class="notifyme-card">
              <SearchBar
                :initial-filters="null"
                :hide-agent="true"
                layout-variant="notify-me"
                @filters-changed="onFiltersChanged"
              />
            </div>

            <div class="notifyme-summary">
              <div class="notifyme-summary-title">Your alert criteria</div>
              <div v-if="activeChips.length" class="notifyme-chips">
                <span v-for="chip in activeChips" :key="chip" class="notifyme-chip">
                  {{ chip }}
                </span>
              </div>
              <div v-else class="notifyme-summary-empty">
                Select at least one filter to start.
              </div>
            </div>
          </div>

          <div class="notifyme-footer">
            <button type="button" class="btn btn-outline-secondary notifyme-cancel" @click="close">
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary notifyme-submit"
              :disabled="submitting"
              @click="submit"
            >
              <span v-if="submitting">Submitting...</span>
              <span v-else>Submit Notify me</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import SearchBar from '@/components/alllisting/SearchBar.vue'

const router = useRouter()
const submitting = ref(false)

const defaultFilters = {
  saleRent: 'All',
  completionStatus: null,
  status: 'All',
  area: null,
  propertyType: null,
  project: null,
  beds: '',
  priceFrom: 0,
  priceTo: 10000000,
  sizeFrom: 0,
  sizeTo: 10000,
  sort: 'created_at_desc',
  referenceNumber: '',
}

const notifyFilters = ref({ ...defaultFilters })

function onFiltersChanged(filters) {
  notifyFilters.value = { ...defaultFilters, ...(filters || {}) }
}

const hasAnyCriteria = computed(() => {
  const f = notifyFilters.value || {}
  const ref = (f.referenceNumber || '').trim()
  const priceFrom = Number(f.priceFrom ?? 0)
  const priceTo = Number(f.priceTo ?? 10000000)
  const sizeFrom = Number(f.sizeFrom ?? 0)
  const sizeTo = Number(f.sizeTo ?? 10000)

  return !!(
    f.area ||
    f.project ||
    f.propertyType ||
    (f.beds !== '' && f.beds != null) ||
    (f.saleRent && f.saleRent !== 'All') ||
    (f.completionStatus && f.completionStatus.value != null) ||
    priceFrom > 0 ||
    priceTo < 10000000 ||
    sizeFrom > 0 ||
    sizeTo < 10000 ||
    ref.length > 0
  )
})

const activeChips = computed(() => {
  const f = notifyFilters.value || {}
  const chips = []

  if (f.area?.name) chips.push(`Location: ${f.area.name}`)
  if (f.project?.name) chips.push(`Project: ${f.project.name}`)
  if (f.propertyType?.name) chips.push(`Property Type: ${f.propertyType.name}`)

  if (f.saleRent && f.saleRent !== 'All') chips.push(`Type: ${f.saleRent}`)

  if (f.completionStatus?.label && f.completionStatus.value != null) chips.push(`Project Status: ${f.completionStatus.label}`)

  if (f.beds) chips.push(`Bedrooms: ${f.beds}`)

  const priceFrom = Number(f.priceFrom ?? 0)
  const priceTo = Number(f.priceTo ?? 10000000)
  if (priceFrom > 0 || priceTo < 10000000) {
    chips.push(`Price: ${priceFrom} - ${priceTo} AED`)
  }

  const sizeFrom = Number(f.sizeFrom ?? 0)
  const sizeTo = Number(f.sizeTo ?? 10000)
  if (sizeFrom > 0 || sizeTo < 10000) {
    chips.push(`Size: ${sizeFrom} - ${sizeTo} sqft`)
  }

  const ref = (f.referenceNumber || '').trim()
  if (ref) chips.push(`Reference: ${ref}`)

  return chips
})

function close() {
  router.back()
}

async function submit() {
  if (!hasAnyCriteria.value) {
    Swal.fire({
      icon: 'info',
      title: 'Select your criteria',
      text: 'Please choose at least one filter before submitting.',
    })
    return
  }

  submitting.value = true
  try {
    await api.post('/search-alerts', notifyFilters.value)
     close()
    await Swal.fire({
      icon: 'success',
      title: 'Notify me is set',
      text: 'Great! We’ll email you when new listings match your criteria.',
      confirmButtonText: 'Done',
    })
    // Navigate only after the user closes the alert (prevents the page from unmounting early).
   
    // router.back()
  } catch (e) {
    const message = e?.response?.data?.message || e?.message || 'Failed to save alert'
    Swal.fire({
      icon: 'error',
      title: 'Could not save',
      text: message,
      confirmButtonText: 'OK',
    })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.notifyme-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 60000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
}

.notifyme-modal {
  width: 1120px;
  max-width: 96vw;
  max-height: 96vh;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 20px 70px rgba(0, 0, 0, 0.22);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.notifyme-header {
  padding: 18px 18px 14px;
  border-bottom: 1px solid #eef2f7;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.notifyme-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: #01062c;
}

.notifyme-subtitle {
  margin: 6px 0 0;
  font-size: 12.5px;
  color: #64748b;
}

.notifyme-close {
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 6px;
  border-radius: 10px;
}

.notifyme-close:hover {
  background: #f1f5f9;
}

.notifyme-body {
  padding: 14px 18px 10px;
  overflow: auto;
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
  flex: 1;
}

.notifyme-card {
  border: 1px solid #edf2f7;
  border-radius: 14px;
  padding: 10px;
  background: #fff;
}

.notifyme-summary {
  border: 1px solid #edf2f7;
  border-radius: 14px;
  padding: 12px;
  background: #fbfdff;
}

.notifyme-summary-title {
  font-size: 12px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 10px;
}

.notifyme-chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.notifyme-chip {
  background: #eaf3ff;
  color: #1d4ed8;
  border: 1px solid #cfe4ff;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.notifyme-summary-empty {
  font-size: 12.5px;
  color: #64748b;
}

.notifyme-footer {
  padding: 14px 18px;
  border-top: 1px solid #eef2f7;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.notifyme-cancel {
  min-width: 110px;
}

.notifyme-submit {
  min-width: 190px;
}

/* Hide unwanted parts of the existing SearchBar inside this modal */
:deep(.secondary-filters) {
  display: none !important;
}

:deep(.unified-search-btn) {
  display: none !important;
}

:deep(.sort-select) {
  display: none !important;
}
</style>

