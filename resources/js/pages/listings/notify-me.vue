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

            <div class="notifyme-mine">
              <div class="notifyme-summary-title">Your saved alerts</div>

              <div v-if="loadingAlerts" class="notifyme-summary-empty">
                Loading your alerts…
              </div>

              <div v-else-if="!myAlerts.length" class="notifyme-summary-empty">
                You don’t have any saved alerts yet.
              </div>

              <div v-else class="notifyme-alert-list">
                <div v-for="alert in myAlerts" :key="alert.id" class="notifyme-alert-item">
                  <div class="notifyme-alert-main">
                    <div class="notifyme-alert-head">
                      <span
                        class="notifyme-alert-status"
                        :class="alert.is_active ? 'is-active' : 'is-inactive'"
                      >
                        {{ alert.is_active ? 'Active' : 'Notified' }}
                      </span>
                      <span class="notifyme-alert-date">{{ formatDate(alert.created_at) }}</span>
                    </div>
                    <div v-if="alert.chips.length" class="notifyme-chips">
                      <span v-for="chip in alert.chips" :key="chip" class="notifyme-chip">
                        {{ chip }}
                      </span>
                    </div>
                    <div v-else class="notifyme-summary-empty">No specific criteria.</div>

                    <button v-if="alert.chips.length"
                      type="button"
                      class="notifyme-alert-toggle"
                      @click="toggleDetails(alert.id)"
                    >
                      <iconify-icon
                        :icon="expandedId === alert.id ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                        class="icon"
                      />
                      {{ expandedId === alert.id ? 'Hide details' : 'Show details' }}
                    </button>

                    <div v-if="expandedId === alert.id" class="notifyme-alert-details">
                      <div
                        v-for="row in detailRows(alert)"
                        :key="row.label"
                        class="notifyme-detail-row"
                      >
                        <span class="notifyme-detail-label">{{ row.label }}</span>
                        <span class="notifyme-detail-value">{{ row.value }}</span>
                      </div>
                      <div class="notifyme-detail-row">
                        <span class="notifyme-detail-label">Status</span>
                        <span class="notifyme-detail-value">
                          {{ alert.is_active ? 'Active — watching for matches' : 'Notified — a match was already sent' }}
                        </span>
                      </div>
                      <div class="notifyme-detail-row">
                        <span class="notifyme-detail-label">Created</span>
                        <span class="notifyme-detail-value">{{ formatDate(alert.created_at) }}</span>
                      </div>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="notifyme-alert-delete"
                    :disabled="deletingId === alert.id"
                    @click="deleteAlert(alert)"
                    aria-label="Delete alert"
                  >
                    <iconify-icon icon="lucide:trash-2" class="icon" />
                  </button>
                </div>
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
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import SearchBar from '@/components/alllisting/SearchBar.vue'

const router = useRouter()
const submitting = ref(false)

const myAlerts = ref([])
const loadingAlerts = ref(false)
const deletingId = ref(null)
const expandedId = ref(null)

function toggleDetails(id) {
  expandedId.value = expandedId.value === id ? null : id
}

// Each chip is a "Label: value" string from the API — split it into label/value rows.
function detailRows(alert) {
  return (alert.chips || []).map((chip) => {
    const idx = chip.indexOf(':')
    if (idx === -1) return { label: chip, value: '' }
    return {
      label: chip.slice(0, idx).trim(),
      value: chip.slice(idx + 1).trim(),
    }
  })
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function loadAlerts() {
  loadingAlerts.value = true
  try {
    const { data } = await api.get('/search-alerts')
    myAlerts.value = data?.data || []
  } catch (e) {
    myAlerts.value = []
  } finally {
    loadingAlerts.value = false
  }
}

async function deleteAlert(alert) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Delete this alert?',
    text: 'You will no longer be notified for these criteria.',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#dc2626',
    customClass: { container: 'notifyme-swal-top' },
  })
  if (!result.isConfirmed) return

  deletingId.value = alert.id
  try {
    await api.delete(`/search-alerts/${alert.id}`)
    myAlerts.value = myAlerts.value.filter((a) => a.id !== alert.id)
  } catch (e) {
    const message = e?.response?.data?.message || e?.message || 'Failed to delete alert'
    Swal.fire({ icon: 'error', title: 'Could not delete', text: message, customClass: { container: 'notifyme-swal-top' } })
  } finally {
    deletingId.value = null
  }
}

onMounted(loadAlerts)

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
function convertFiltersToAPI(filters) {
  const apiFilters = {}

  if (filters.saleRent && filters.saleRent !== 'All') {
    apiFilters.listing_status = filters.saleRent.toLowerCase()
  }

  if (filters.area?.id) {
    apiFilters.area_id = filters.area.id
  }

  if (filters.project?.id) {
    apiFilters.project_id = filters.project.id
  }

  if (Array.isArray(filters.propertyTypes) && filters.propertyTypes.length) {
    const ids = filters.propertyTypes.map((p) => p?.id).filter(Boolean)
    if (ids.length) {
      apiFilters.property_type_ids = ids
      if (ids.length === 1) {
        apiFilters.property_type_id = ids[0]
      }
    }
  } else if (filters.propertyType?.id) {
    apiFilters.property_type_id = filters.propertyType.id
  }

  if (filters.agent?.id) {
    apiFilters.agent_id = filters.agent.id
  }

  if (filters.completionStatus?.value) {
    apiFilters.completion_status = filters.completionStatus.value
  }

  const bedsList = Array.isArray(filters.bedsList) ? filters.bedsList : (filters.beds ? [filters.beds] : [])
  if (bedsList.length) {
    apiFilters.number_of_bedrooms_in = bedsList
    if (bedsList.length === 1) {
      const firstBed = bedsList[0]
      apiFilters.number_of_bedrooms = firstBed === 'Studio' ? 'Studio' : parseInt(firstBed)
    }
  }

  const bathsList = Array.isArray(filters.bathsList) ? filters.bathsList : (filters.baths ? [filters.baths] : [])
  if (bathsList.length) {
    apiFilters.number_of_bathrooms_in = bathsList
    if (bathsList.length === 1) {
      apiFilters.number_of_bathrooms = parseInt(bathsList[0])
    }
  }

  if (filters.priceFrom > 0) {
    apiFilters.min_price = filters.priceFrom
  }

  if (filters.priceTo < 10000000) {
    apiFilters.max_price = filters.priceTo
  }

  if (filters.sizeFrom > 0) {
    apiFilters.min_size = filters.sizeFrom
  }

  if (filters.sizeTo < 10000) {
    apiFilters.max_size = filters.sizeTo
  }

  if (filters.referenceNumber?.trim()) {
    apiFilters.reference_number = filters.referenceNumber.trim()
  }

  return apiFilters
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
      
      const payload = convertFiltersToAPI(notifyFilters.value)

    await api.post('/search-alerts', payload)
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
  /*overflow: hidden;*/
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
  color: #0B0736;
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
  /*overflow: auto;*/
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

.notifyme-mine {
  border: 1px solid #edf2f7;
  border-radius: 14px;
  padding: 12px;
  background: #fff;
}

.notifyme-alert-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.notifyme-alert-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  border: 1px solid #edf2f7;
  border-radius: 12px;
  padding: 10px 12px;
  background: #fbfdff;
}

.notifyme-alert-main {
  flex: 1;
  min-width: 0;
}

.notifyme-alert-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.notifyme-alert-date {
  font-size: 11px;
  color: #94a3b8;
}

.notifyme-alert-toggle {
  margin-top: 10px;
  border: none;
  background: transparent;
  color: #1d4ed8;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
}

.notifyme-alert-toggle:hover {
  text-decoration: underline;
}

.notifyme-alert-details {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.notifyme-detail-row {
  display: flex;
  align-items: baseline;
  gap: 10px;
  font-size: 12.5px;
}

.notifyme-detail-label {
  flex: 0 0 120px;
  color: #64748b;
  font-weight: 600;
}

.notifyme-detail-value {
  flex: 1;
  color: #0f172a;
}

.notifyme-alert-status {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 999px;
}

.notifyme-alert-status.is-active {
  background: #dcfce7;
  color: #15803d;
}

.notifyme-alert-status.is-inactive {
  background: #f1f5f9;
  color: #64748b;
}

.notifyme-alert-delete {
  border: 1px solid #fee2e2;
  background: #fff;
  color: #dc2626;
  cursor: pointer;
  padding: 6px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notifyme-alert-delete:hover:not(:disabled) {
  background: #fef2f2;
}

.notifyme-alert-delete:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
:deep(.vs__dropdown-menu) {
  max-height: 200px;
  overflow-y: auto;
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

<!-- Unscoped: SweetAlert is teleported to <body>, so it must sit above the notify-me modal (z-index 60000). -->
<style>
.notifyme-swal-top {
  z-index: 70000 !important;
}
</style>

