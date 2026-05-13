<template>
  <div
    class="property-card-readonly border rounded-3 p-3 mb-3"
    :class="{ 'property-card-readonly--editing': isEditing }"
  >
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center gap-2">
        <span class="badge" :class="isEditing ? 'bg-warning text-dark' : 'bg-secondary'">
          Property {{ index + 1 }}
        </span>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <button
          v-if="!readonly && !isEditing"
          type="button"
          class="section-edit-btn"
          title="Edit property"
          @click="startEdit"
        >
          <iconify-icon icon="lucide:pencil" />
        </button>
      </div>
    </div>

    <!-- ========== VIEW MODE (one edit icon in header → in-place edit) ========== -->
    <div v-if="!isEditing" key="prop-view" class="property-card-view">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="info-group">
            <label class="info-label">Property Address</label>
            <p class="info-value mb-0">{{ property.area_name || '----' }}</p>
          </div>
        </div>
       
        <div class="col-md-6">
          <div class="info-group">
            <label class="info-label">Property Type</label>
            <p class="info-value mb-0">{{ property.property_type_name || '----' }}</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.bedrooms">
          <div class="info-group">
            <label class="info-label">Bedrooms</label>
            <p class="info-value mb-0">{{ formatBedrooms(property.bedrooms) }}</p>
          </div>
        </div>
        
         <div class="col-md-6" v-if="(property.budget_from || property.budget_to) && showBudgetFields">
            <div class="info-group">
                <label class="info-label">Budget (AED)</label>
                <p class="info-value mb-0">
                    <span v-if="property.budget_from && property.budget_to">
                        {{ formatNumber(property.budget_from) }} - {{ formatNumber(property.budget_to) }}
                    </span>
                    <span v-else-if="property.budget_from">
                        From {{ formatNumber(property.budget_from) }}
                    </span>
                    <span v-else-if="property.budget_to">
                        To {{ formatNumber(property.budget_to) }}
                    </span>
                    <span v-else>----</span>
                    AED
                </p>
            </div>
        </div>
        <div class="col-md-6" v-if="showPurchasePrice || property.purchase_price">
          <div class="info-group">
            <label class="info-label">Purchase Price</label>
            <p class="info-value mb-0">
              <template v-if="property.purchase_price">{{ formatNumber(property.purchase_price) }} AED</template>
              <template v-else>----</template>
            </p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-group">
            <label class="info-label">Developer Name</label>
            <p class="info-value mb-0">{{ getDeveloperName(property.developer_id) || property.developer_name || '----' }}</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.developer_name">
          <div class="info-group">
            <label class="info-label">Developer Sales Person Name</label>
            <p class="info-value mb-0">{{ property.developer_name }}</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.developer_phone">
          <div class="info-group">
            <label class="info-label">Developer Sales Person Phone</label>
            <p class="info-value mb-0">{{ property.developer_phone }}</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.commission">
          <div class="info-group">
            <label class="info-label">Property Commission</label>
            <p class="info-value mb-0">{{ property.commission }}%</p>
          </div>
        </div>
         <div class="col-md-6">
          <div class="info-group">
            <label class="info-label">Unit No</label>
            <p class="info-value mb-0">{{ property.unit_no || '----' }}</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-group">
            <label class="info-label">Unit Size (sq.ft)</label>
            <p class="info-value mb-0">{{ property.unit_size || '----' }}</p>
          </div>
        </div>
      </div>

      <!-- Property documents — show only sections relevant to the stage (or already-uploaded sections). -->
      <template v-if="showStagePropertyDocs">
        <div v-if="shouldShowDocSection('eoi', 'eoi_documents')" class="row mt-3">
          <div class="col-12">
            <div class="info-group">
              <label class="info-label">Eoi Document</label>
              <div v-if="hasPropertyDocs(property, 'eoi_documents')" class="documents-grid">
                <div
                  v-for="(doc, idx) in getPropertyDocsList(property, 'eoi_documents')"
                  :key="idx"
                  class="document-card"
                >
                  <div class="document-preview" @click="previewDocument(doc)">
                    <img
                      v-if="isImageDocument(doc)"
                      :src="getDocumentUrl(doc)"
                      :alt="doc.original_name || doc.name"
                      class="document-thumbnail"
                      @error="handleImageError"
                    />
                    <div v-else class="document-icon-placeholder">
                      <iconify-icon :icon="getFileIcon(doc)" class="document-icon-large" />
                    </div>
                    <div class="document-name">{{ truncateName(doc.original_name || doc.name) }}</div>
                  </div>
                  <div class="document-actions">
                    <button class="doc-action-btn view" @click.stop="previewDocument(doc)">View</button>
                    <button class="doc-action-btn delete" @click.stop="deleteDocument(doc, 'eoi_documents')">Delete</button>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted small mb-0">No documents uploaded.</p>
            </div>
          </div>
        </div>
        <div v-if="shouldShowDocSection('booking', 'booking_documents')" class="row mt-3">
          <div class="col-12">
            <div class="info-group">
              <label class="info-label">Booking Form</label>
              <div v-if="hasPropertyDocs(property, 'booking_documents')" class="documents-grid">
                <div
                  v-for="(doc, idx) in getPropertyDocsList(property, 'booking_documents')"
                  :key="idx"
                  class="document-card"
                >
                  <div class="document-preview" @click="previewDocument(doc)">
                    <img
                      v-if="isImageDocument(doc)"
                      :src="getDocumentUrl(doc)"
                      :alt="doc.original_name || doc.name"
                      class="document-thumbnail"
                      @error="handleImageError"
                    />
                    <div v-else class="document-icon-placeholder">
                      <iconify-icon :icon="getFileIcon(doc)" class="document-icon-large" />
                    </div>
                    <div class="document-name">{{ truncateName(doc.original_name || doc.name) }}</div>
                  </div>
                  <div class="document-actions">
                    <button class="doc-action-btn view" @click.stop="previewDocument(doc)">View</button>
                    <button class="doc-action-btn delete" @click.stop="deleteDocument(doc, 'booking_documents')">Delete</button>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted small mb-0">No documents uploaded.</p>
            </div>
          </div>
        </div>
        <div v-if="shouldShowDocSection('mou', 'mou_documents')" class="row mt-3">
          <div class="col-12">
            <div class="info-group">
              <label class="info-label">MOU Document</label>
              <div v-if="hasPropertyDocs(property, 'mou_documents')" class="documents-grid">
                <div
                  v-for="(doc, idx) in getPropertyDocsList(property, 'mou_documents')"
                  :key="idx"
                  class="document-card"
                >
                  <div class="document-preview" @click="previewDocument(doc)">
                    <img
                      v-if="isImageDocument(doc)"
                      :src="getDocumentUrl(doc)"
                      :alt="doc.original_name || doc.name"
                      class="document-thumbnail"
                      @error="handleImageError"
                    />
                    <div v-else class="document-icon-placeholder">
                      <iconify-icon :icon="getFileIcon(doc)" class="document-icon-large" />
                    </div>
                    <div class="document-name">{{ truncateName(doc.original_name || doc.name) }}</div>
                  </div>
                  <div class="document-actions">
                    <button class="doc-action-btn view" @click.stop="previewDocument(doc)">View</button>
                    <button class="doc-action-btn delete" @click.stop="deleteDocument(doc, 'mou_documents')">Delete</button>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted small mb-0">No documents uploaded.</p>
            </div>
          </div>
        </div>
        <div v-if="shouldShowDocSection('noc', 'noc_documents')" class="row mt-3">
          <div class="col-12">
            <div class="info-group">
              <label class="info-label">NOC Document</label>
              <div v-if="hasPropertyDocs(property, 'noc_documents')" class="documents-grid">
                <div
                  v-for="(doc, idx) in getPropertyDocsList(property, 'noc_documents')"
                  :key="idx"
                  class="document-card"
                >
                  <div class="document-preview" @click="previewDocument(doc)">
                    <img
                      v-if="isImageDocument(doc)"
                      :src="getDocumentUrl(doc)"
                      :alt="doc.original_name || doc.name"
                      class="document-thumbnail"
                      @error="handleImageError"
                    />
                    <div v-else class="document-icon-placeholder">
                      <iconify-icon :icon="getFileIcon(doc)" class="document-icon-large" />
                    </div>
                    <div class="document-name">{{ truncateName(doc.original_name || doc.name) }}</div>
                  </div>
                  <div class="document-actions">
                    <button class="doc-action-btn view" @click.stop="previewDocument(doc)">View</button>
                    <button class="doc-action-btn delete" @click.stop="deleteDocument(doc, 'noc_documents')">Delete</button>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted small mb-0">No documents uploaded.</p>
            </div>
          </div>
        </div>
        <div v-if="shouldShowDocSection('payment_proof', 'payment_proof')" class="row mt-3">
          <div class="col-12">
            <div class="info-group">
              <label class="info-label">Payment Proof</label>
              <div v-if="hasPropertyDocs(property, 'payment_proof')" class="documents-grid">
                <div
                  v-for="(doc, idx) in getPropertyDocsList(property, 'payment_proof')"
                  :key="idx"
                  class="document-card"
                >
                  <div class="document-preview" @click="previewDocument(doc)">
                    <img
                      v-if="isImageDocument(doc)"
                      :src="getDocumentUrl(doc)"
                      :alt="doc.original_name || doc.name"
                      class="document-thumbnail"
                      @error="handleImageError"
                    />
                    <div v-else class="document-icon-placeholder">
                      <iconify-icon :icon="getFileIcon(doc)" class="document-icon-large" />
                    </div>
                    <div class="document-name">{{ truncateName(doc.original_name || doc.name) }}</div>
                  </div>
                  <div class="document-actions">
                    <button class="doc-action-btn view" @click.stop="previewDocument(doc)">View</button>
                    <button class="doc-action-btn delete" @click.stop="deleteDocument(doc, 'payment_proof')">Delete</button>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted small mb-0">No documents uploaded.</p>
            </div>
          </div>
        </div>

        <div v-if="shouldShowDocSection('spa', 'spa_document')" class="row mt-3">
          <div class="col-12">
            <div class="info-group">
              <label class="info-label">SPA Document</label>
              <div v-if="hasPropertyDocs(property, 'spa_document')" class="documents-grid">
                <div
                  v-for="(doc, idx) in getPropertyDocsList(property, 'spa_document')"
                  :key="idx"
                  class="document-card"
                >
                  <div class="document-preview" @click="previewDocument(doc)">
                    <img
                      v-if="isImageDocument(doc)"
                      :src="getDocumentUrl(doc)"
                      :alt="doc.original_name || doc.name"
                      class="document-thumbnail"
                      @error="handleImageError"
                    />
                    <div v-else class="document-icon-placeholder">
                      <iconify-icon :icon="getFileIcon(doc)" class="document-icon-large" />
                    </div>
                    <div class="document-name">{{ truncateName(doc.original_name || doc.name) }}</div>
                  </div>
                  <div class="document-actions">
                    <button class="doc-action-btn view" @click.stop="previewDocument(doc)">View</button>
                    <button class="doc-action-btn delete" @click.stop="deleteDocument(doc, 'spa_document')">Delete</button>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted small mb-0">No documents uploaded.</p>
            </div>
          </div>
        </div>
        
      </template>
    </div>

    <!-- ========== EDIT MODE (same card, replaces view) ========== -->
    <div v-else key="prop-edit" class="property-card-edit">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label-custom">Property Address</label>
          <v-select
            v-model="editData.area_id"
            :options="areas"
            :reduce="item => item.id"
            label="name"
            placeholder="Select Address..."
            class="custom-v-select"
             @update:modelValue="onAreaSelected"
          >
            <template #open-indicator="{ attributes }">
              <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
            </template>
            <template #option="option">
              <div class="location-option">
                <iconify-icon icon="lucide:map-pin" class="location-icon" />
                <div>
                  <div class="fw-semibold">{{ option.name }}</div>
                  <div class="small text-muted">{{ option.area_parents_title }}</div>
                </div>
              </div>
            </template>
          </v-select>
        </div>

     

        <div class="col-md-6">
          <label class="form-label-custom">Property Type</label>
          <v-select
            v-model="editData.property_type_id"
            :options="propertyTypes"
            :reduce="item => item.id"
            label="name"
            placeholder="Select Type"
            class="custom-v-select"
          >
            <template #open-indicator="{ attributes }">
              <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
            </template>
          </v-select>
        </div>

        <div class="col-md-6" v-if="showBedroomsField">
          <label class="form-label-custom">Bedrooms</label>
          <v-select
            v-model="editData.bedrooms"
            :options="bedroomOptions"
            :reduce="o => o.value"
            label="text"
            placeholder="Select Bedrooms"
            class="custom-v-select"
          >
            <template #open-indicator="{ attributes }">
              <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
            </template>
          </v-select>
        </div>

        
         <div v-if="showBudgetFields" class="col-md-6">
            <label class="form-label-custom">
                Budget (AED)
            </label>
            <div
                ref="budgetTriggerRef"
                class="budget-field-wrap"
            >
                <button
                    type="button"
                    class="custom-date-trigger"
                    @click.stop="toggleBudgetDropdown"
                >
                    <span>{{ budgetDisplay }}</span>
                    <iconify-icon icon="lucide:chevron-down" />
                </button>
            </div>
            <!-- Budget Dropdown -->
            <div
                v-if="showBudgetDropdown"
                ref="budgetDropdownPanelRef"
                class="budget-dropdown budget-dropdown--portal"
                :style="budgetDropdownStyle"
                @click.stop
                @mousedown.stop
            >
                <div class="budget-from-to-row" @click.stop @mousedown.stop>
                    <div class="budget-col">
                        <label class="budget-input-label">From (AED)</label>
                        <input
                            :value="budgetFromDisplay"
                            placeholder="0"
                            inputmode="numeric"
                            @click.stop
                            @mousedown.stop
                            class="custom-input budget-dropdown-input"
                            @keypress="onMoneyKeypress"
                            @input="(e) => setBudgetValue('budget_from', e.target.value)"
                        />
                    </div>
                    <div class="budget-col">
                        <label class="budget-input-label">To (AED)</label>
                        <input
                            :value="budgetToDisplay"
                            placeholder="0"
                            inputmode="numeric"
                            @click.stop
                            @mousedown.stop
                            class="custom-input budget-dropdown-input"
                            @keypress="onMoneyKeypress"
                            @input="(e) => setBudgetValue('budget_to', e.target.value)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6" v-if="showPurchasePrice">
          <label class="form-label-custom">Purchase Price</label>
          <div class="input-group">
            <b-form-input :model-value="purchasePriceDisplay" @update:model-value="setPurchasePrice" type="text" inputmode="numeric" placeholder="Amount" class="custom-input" @keypress="onMoneyKeypress" />
            <span class="input-group-text">AED</span>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label-custom">Developer</label>
          <v-select
            v-model="editData.developer_id"
            :options="developers"
            :reduce="item => item.id"
            label="name"
            placeholder="Select Developer"
            class="custom-v-select"
          >
            <template #open-indicator="{ attributes }">
              <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
            </template>
          </v-select>
        </div>

        <div class="col-md-6">
          <label class="form-label-custom">Developer Sales Person Name</label>
          <b-form-input v-model="editData.developer_name" placeholder="Contact Person" class="custom-input" />
        </div>

        <div class="col-md-6">
          <label class="form-label-custom">Developer Sales Person Phone</label>
          <CrmPhoneInput v-model="editData.developer_phone" placeholder="Phone Number" />
        </div>

        <div class="col-md-6" v-if="showPropertyCommission">
          <label class="form-label-custom">Property Commission %</label>
          <div class="input-group">
            <b-form-input v-model="editData.commission" type="number" step="0.01" placeholder="Commission %" class="custom-input" />
            <span class="input-group-text">%</span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label-custom">Unit Size (sq.ft)</label>
          <b-form-input v-model="editData.unit_size" type="number" placeholder="Size" class="custom-input" />
        </div>
        <div class="col-md-6">
          <label class="form-label-custom">Unit No</label>
          <b-form-input v-model="editData.unit_no" placeholder="Enter Unit No" class="custom-input" />
        </div>

        <!-- Same as Create Deal: Payment Proof + SPA, multi-file, always in edit -->
        <div class="col-12 mt-3 pt-3 property-edit-documents">
          <div class="property-documents-heading">Property Documents</div>
          <DocumentUpload
            v-model="propertyEditDocs"
            category="property"
            :document-types="propertyEditDocTypes"
            :deal-id="dealId"
            :property-id="property.id"
          />
          <div class="col-12 text-muted small mt-2 px-0">
            <iconify-icon icon="lucide:info" /> Add multiple files per type. Existing files remain unless removed in view mode.
          </div>
        </div>
      </div>
      <!-- Space for fixed Save/Cancel bar (same idea as ViewDealModal edit-lead-bottom-bar) -->
      <div v-if="isEditing" class="property-edit-bar-spacer" aria-hidden="true" />
    </div>

    <!-- Fixed bottom bar while editing (matches deal / buyer modal save row) -->
    <div v-if="isEditing" class="property-card-edit-bottom-bar">
      <button type="button" class="edit-bar-btn edit-bar-cancel" @click="cancelEdit">
        Cancel
      </button>
      <button type="button" class="edit-bar-btn edit-bar-save" :disabled="saving" @click="saveEdit">
        <span v-if="saving"><b-spinner small class="align-middle me-1" /> Saving...</span>
        <span v-else>Save</span>
      </button>
    </div>

    <!-- Preview Modal -->
    <div v-if="previewDoc" class="doc-preview-backdrop" @click.self="closePreview">
      <div class="doc-preview-modal">
        <button type="button" class="doc-preview-close" @click="closePreview">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="doc-preview-body">
          <img
            v-if="isImageDocument(previewDoc)"
            :src="getDocumentUrl(previewDoc)"
            alt="Document preview"
            class="doc-preview-image"
          />
          <iframe
            v-else-if="previewDoc.mime_type === 'application/pdf' || previewDoc.mime === 'application/pdf'"
            :src="getDocumentUrl(previewDoc)"
            class="doc-preview-iframe"
            title="Document preview"
          />
          <div v-else class="doc-preview-empty">
            <p>Preview not available</p>
            <a :href="getDocumentUrl(previewDoc)" target="_blank" class="btn-download">Download</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed ,watch,onMounted , onBeforeUnmount, nextTick } from 'vue'
import { BFormInput, BSpinner } from 'bootstrap-vue-3'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import vSelect from 'vue-select'
import DocumentUpload from './DocumentUpload.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl'

const props = defineProps({
  property: { type: Object, required: true },
  index: { type: Number, required: true },
  dealId: { type: Number, required: true },
  areas: { type: Array, default: () => [] },
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  selectedStageName: { type: String, default: '' },
  selectedStageOrder: { type: [Number, String], default: 0 },
  dealType: { type: String, default: 'primary' },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['property-updated', 'refresh-deal'])

const isEditing = ref(false)
const saving = ref(false)
const editData = ref({})
/** Hydrated existing + new picks for SPA / Payment Proof (same shape as Create Deal PropertyCard). */
const propertyEditDocs = ref([])
const previewDoc = ref(null)
const selectedListing = ref(null)

// Stage detection
const showBudgetFields = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  console.log(stageName);
  return stageName.includes('eoi')
})
const showPurchasePrice = computed(() => {
  const dt = props.dealType
  if (dt !== 'primary' && dt !== 'secondary') return false
  const order = Number(props.selectedStageOrder) || 0
  if (order >= 3) return true
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('booking') || stageName.includes('mou') || stageName.includes('spa') || stageName.includes('won')
})
const showPropertyCommission = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('won') || stageName.includes('deal won')
})

/**
 * Always show property document rows in deal view. Pipeline stage titles vary (e.g. "Reservation", "Deposit")
 * and must not hide files the user already uploaded.
 */
const showStagePropertyDocs = computed(() => true)

/** Required for DocumentUpload: must be defined (was missing — only header showed, no Payment Proof / SPA rows). */
const propertyEditDocTypes = computed(() => {
  const s = (props.selectedStageName || '').toLowerCase()
  const dt = props.dealType
  const order = Number(props.selectedStageOrder) || 0

  // SECONDARY: all property documents are OPTIONAL. Surface MOU from stage 3, NOC from stage 4.
  if (dt === 'secondary') {
    const docs = [{ id: 'payment_proof', name: 'Payment Proof', required: false }]
    if (order >= 3 || s.includes('mou')) docs.unshift({ id: 'mou', name: 'MOU Document', required: false })
    if (order >= 4 || s.includes('noc')) docs.splice(1, 0, { id: 'noc', name: 'NOC Document', required: false })
    if (order >= 5 || s.includes('won') || s.includes('spa')) docs.push({ id: 'spa', name: 'SPA Document', required: false })
    return docs
  }

  // PRIMARY / others — keep existing name-based logic.
  const showEoi = s.includes('eoi') || s.includes('booking') || s.includes('spa') || s.includes('won')
  const showBooking = s.includes('booking') || s.includes('spa') || s.includes('won')
  const showPayment = s.includes('spa') || s.includes('won')
  const showSpa = s.includes('spa') || s.includes('won')

  return [
    { id: 'eoi', name: 'EOI Document', required: showEoi },
    { id: 'booking', name: 'Booking Form', required: showBooking },
    { id: 'payment_proof', name: 'Payment Proof', required: showPayment },
    { id: 'spa', name: 'SPA Document', required: showSpa },
  ]
})

/**
 * Which document types should be visible in VIEW mode for this stage + deal type.
 * Any section that already has uploaded files is also visible so existing data isn't hidden.
 */
const visibleDocTypeIds = computed(() => {
  const s = (props.selectedStageName || '').toLowerCase()
  const dt = props.dealType
  const order = Number(props.selectedStageOrder) || 0
  const ids = new Set()

  if (dt === 'secondary') {
    // payment_proof is always relevant for secondary uploads
    ids.add('payment_proof')
    if (order >= 3 || s.includes('mou')) ids.add('mou')
    if (order >= 4 || s.includes('noc')) ids.add('noc')
    if (order >= 5 || s.includes('won') || s.includes('spa')) ids.add('spa')
  } else if (dt === 'primary') {
    if (s.includes('eoi') || s.includes('booking') || s.includes('spa') || s.includes('won')) ids.add('eoi')
    if (s.includes('booking') || s.includes('spa') || s.includes('won')) ids.add('booking')
    if (s.includes('booking') || s.includes('spa') || s.includes('won')) ids.add('payment_proof')
    if (s.includes('spa') || s.includes('won')) ids.add('spa')
  } else {
    // rental / other — show all by default (no specific filter)
    ids.add('payment_proof')
    ids.add('spa')
  }

  return ids
})

function shouldShowDocSection(docId, propertyDocField) {
  // Stage-relevant OR has uploaded files (preserve existing data).
  return visibleDocTypeIds.value.has(docId) || hasPropertyDocs(props.property, propertyDocField)
}

const bedroomOptions = [
  { value: 'studio', text: 'Studio' },
  { value: '1', text: '1 Bedroom' },
  { value: '2', text: '2 Bedrooms' },
  { value: '3', text: '3 Bedrooms' },
  { value: '4', text: '4 Bedrooms' },
  { value: '5', text: '5 Bedrooms' },
  { value: '5+', text: '5+ Bedrooms' }
]

// ========== Document Helper Functions ==========
function getDocumentsArray(docs) {
  if (!docs) return []
  if (Array.isArray(docs)) return docs
  if (typeof docs === 'string') {
    try {
      const parsed = JSON.parse(docs)
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  }
  if (typeof docs === 'object' && docs !== null) {
    // إذا كان الكائن يحتوي على ملفات
    if (docs.file || docs.path || docs.url) {
      return [docs]
    }
  }
  return []
}

function hasDocuments(docs) {
  return getDocumentsArray(docs).length > 0
}

/** Prefer API `payment_proof` / `spa_document`; fall back to `*_raw` if ever empty or missing. */
function getPropertyDocsList(property, field) {
  if (!property) return []

  // ✅ دعم الحقول الجديدة
  const allowedFields = ['eoi_documents', 'booking_documents', 'mou_documents', 'noc_documents', 'payment_proof', 'spa_document']
  if (!allowedFields.includes(field)) return []

  const primary = getDocumentsArray(property[field])
  if (primary.length > 0) return primary
  return getDocumentsArray(property[`${field}_raw`])
}

function hasPropertyDocs(property, field) {
  return getPropertyDocsList(property, field).length > 0
}


/** Map API property JSON attachments into DocumentUpload items */
function mapPropertyDocsForEditor(docs, documentTypeSlug) {
  const arr = getDocumentsArray(docs)
  // Map slug to proper document_type
  let dt = documentTypeSlug
  if (documentTypeSlug === 'eoi') dt = 'eoi'
  if (documentTypeSlug === 'booking') dt = 'booking'
  if (documentTypeSlug === 'mou') dt = 'mou'
  if (documentTypeSlug === 'noc') dt = 'noc'
  if (documentTypeSlug === 'payment_proof') dt = 'payment_proof'
  if (documentTypeSlug === 'spa') dt = 'spa'
  
  return arr.map((doc, idx) => ({
    id: doc.id || `existing-${dt}-${idx}`,
    name: doc.original_name || doc.file_name || doc.name || `File ${idx + 1}`,
    url: doc.url || null,
    path: doc.path || null,
    mime_type: doc.mime_type || doc.type || '',
    type: doc.mime_type || doc.type || '',
    document_type: dt,
    category: 'property',
    is_existing: true,
    status: 'existing',
    raw: doc,
  }))
}

function getDocumentUrl(doc) {
  if (!doc) return null
  if (doc.url && String(doc.url).startsWith('blob:')) return doc.url

  for (const key of ['path', 'file_url', 'url']) {
    const v = doc[key]
    if (v && typeof v === 'string') {
      const n = normalizePublicStorageUrl(v)
      if (n) return n
    }
  }
  return null
}

function isImageDocument(doc) {
  if (!doc) return false
  if (doc.mime_type && doc.mime_type.startsWith('image/')) return true
  if (doc.mime && doc.mime.startsWith('image/')) return true
  const name = doc.original_name || doc.name || ''
  const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']
  const ext = name.split('.').pop()?.toLowerCase()
  return imageExts.includes(ext)
}

function getFileIcon(doc) {
  if (isImageDocument(doc)) return 'lucide:image'
  if (doc.mime_type === 'application/pdf' || doc.mime === 'application/pdf') return 'lucide:file-text'
  return 'lucide:file'
}

function truncateName(name, maxLength = 30) {
  if (!name) return 'Document'
  if (name.length <= maxLength) return name
  return name.substring(0, maxLength - 3) + '...'
}

function handleImageError(e) {
  e.target.style.display = 'none'
}

function previewDocument(doc) {
  const url = getDocumentUrl(doc)
  if (url) {
    previewDoc.value = { ...doc, url }
  }
}

function closePreview() {
  previewDoc.value = null
}

async function deleteDocument(doc, type) {
  const result = await Swal.fire({
    title: 'Delete Document?',
    text: `Are you sure you want to delete "${doc.original_name || doc.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  })

  if (!result.isConfirmed) return

  try {
    // ✅ دعم جميع أنواع المستندات
    const validPropertyDocTypes = ['payment_proof', 'spa_document', 'eoi_documents', 'booking_documents', 'mou_documents', 'noc_documents']
    const isPropertyDocType = validPropertyDocTypes.includes(type)
    
    if (isPropertyDocType) {
      const filePath =
        doc.path ||
        doc.file_path ||
        doc.raw?.path ||
        doc.raw?.file_path ||
        doc.url ||
        doc.file_url ||
        doc.raw?.url ||
        doc.raw?.file_url ||
        ''

      let documentTypeForApi = type
      if (type === 'eoi_documents') documentTypeForApi = 'eoi_documents'
      if (type === 'booking_documents') documentTypeForApi = 'booking_documents'
      if (type === 'mou_documents') documentTypeForApi = 'mou_documents'
      if (type === 'noc_documents') documentTypeForApi = 'noc_documents'
      if (type === 'payment_proof') documentTypeForApi = 'payment_proof'
      if (type === 'spa_document') documentTypeForApi = 'spa_document'

      await axios.delete('/api/deals/property-document', {
        data: {
          deal_id: props.dealId,
          property_id: props.property?.id ?? null,
          document_type: documentTypeForApi,
          file_path: filePath,
        },
      })
      
      // ✅ تحديث الحالة محلياً - حذف المستند من الـ property
      if (props.property && props.property[type]) {
        let currentDocs = props.property[type]
        if (typeof currentDocs === 'string') {
          try {
            currentDocs = JSON.parse(currentDocs)
          } catch {
            currentDocs = []
          }
        }
        if (Array.isArray(currentDocs)) {
          // تصفية المستندات وإزالة المستند المحذوف
          const filteredDocs = currentDocs.filter(d => {
            const dPath = d.path || d.file_path || ''
            const dUrl = d.url || d.file_url || ''
            const dName = d.original_name || d.file_name || d.name || ''
            
            return dPath !== filePath && 
                   dUrl !== filePath && 
                   dName !== (doc.original_name || doc.name)
          })
          
          // تحديث الـ property
          props.property[type] = filteredDocs
          
          // إذا كنا في وضع التحرير، تحديث propertyEditDocs أيضاً
          if (isEditing.value) {
            const docTypeSlug = type === 'eoi_documents' ? 'eoi' :
                               type === 'booking_documents' ? 'booking' :
                               type === 'mou_documents' ? 'mou' :
                               type === 'noc_documents' ? 'noc' :
                               type === 'payment_proof' ? 'payment_proof' : 'spa'
            propertyEditDocs.value = propertyEditDocs.value.filter(d => 
              !(d.document_type === docTypeSlug && 
                (d.path === filePath || d.url === filePath || d.name === (doc.original_name || doc.name)))
            )
          }
        }
      }
    } else if (doc.id && /^\d+$/.test(String(doc.id))) {
      await axios.delete(`/api/deals/documents/${doc.id}`)
    } else {
      throw new Error('Missing identifier for delete')
    }
    
    // ✅ تحديث الـ parent بعد الحذف المحلي
    emit('refresh-deal')
    emit('property-updated', props.property)
    
    Swal.fire({
      icon: 'success',
      title: 'Deleted',
      text: 'Document deleted successfully',
      timer: 1500,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Error deleting document:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to delete document'
    })
  }
}
// ========== Edit Functions ==========
function startEdit() {
  editData.value = {
    id: props.property.id,
    unit_no: props.property.unit_no || '',
    property_type_id: props.property.property_type_id || null,
    bedrooms: props.property.bedrooms || null,
    unit_size: props.property.unit_size || '',
    area_id: props.property.area_id || null,
    developer_id: props.property.developer_id || null,
    developer_name: props.property.developer_name || '',
    developer_phone: props.property.developer_phone || '',
    budget_from: props.property.budget_from || null,
    budget_to: props.property.budget_to || null,
    purchase_price: props.property.purchase_price || null,
    commission: props.property.commission || null,
  }
   propertyEditDocs.value = [
    ...mapPropertyDocsForEditor(getPropertyDocsList(props.property, 'eoi_documents'), 'eoi'),
    ...mapPropertyDocsForEditor(getPropertyDocsList(props.property, 'booking_documents'), 'booking'),
    ...mapPropertyDocsForEditor(getPropertyDocsList(props.property, 'mou_documents'), 'mou'),
    ...mapPropertyDocsForEditor(getPropertyDocsList(props.property, 'noc_documents'), 'noc'),
    ...mapPropertyDocsForEditor(getPropertyDocsList(props.property, 'payment_proof'), 'payment_proof'),
    ...mapPropertyDocsForEditor(getPropertyDocsList(props.property, 'spa_document'), 'spa'),
  ]
  isEditing.value = true
}
const onAreaSelected = (areaId) => {
  selectedListing.value = null

  if (editData.value) {
    editData.value.unit_no = ''
    editData.value.property_type_id = null
    editData.value.bedrooms = null
    editData.value.unit_size = ''
    editData.value.developer_id = null
    editData.value.developer_name = null
  }

  const selectedArea = props.areas.find(a => a.id === areaId)
  if (!selectedArea) return

  editData.value.area_id = areaId

  const project = selectedArea.project

  if (project?.developer_id) {
    editData.value.developer_id = project.developer_id
  } 
  else if (selectedArea.developer_id) {
    editData.value.developer_id = selectedArea.developer_id
  } 
  else {
    editData.value.developer_id = null
  }
}

function cancelEdit() {
  isEditing.value = false
  editData.value = {}
  propertyEditDocs.value = []
}

async function saveEdit() {
  saving.value = true
  
  try {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    
    Object.keys(editData.value).forEach(key => {
      const val = editData.value[key]
      if (val !== null && val !== undefined && val !== '') {
        formData.append(key, val)
      }
    })
    
    let eoiIdx = 0
    let bookingIdx = 0
    let ppIdx = 0
    let spaIdx = 0
    
    let mouIdx = 0
    let nocIdx = 0
    propertyEditDocs.value.forEach((doc) => {
      const docType = doc.document_type || ''
      // ✅ فقط الملفات الجديدة (وليس الموجودة)
      if (doc.file instanceof File) {
        if (docType === 'eoi') {
          formData.append(`eoi_documents[${eoiIdx++}]`, doc.file)
        }
        if (docType === 'booking') {
          formData.append(`booking_documents[${bookingIdx++}]`, doc.file)
        }
        if (docType === 'mou') {
          formData.append(`mou_documents[${mouIdx++}]`, doc.file)
        }
        if (docType === 'noc') {
          formData.append(`noc_documents[${nocIdx++}]`, doc.file)
        }
        if (docType === 'payment_proof') {
          formData.append(`payment_proof[${ppIdx++}]`, doc.file)
        }
        if (docType === 'spa') {
          formData.append(`spa_document[${spaIdx++}]`, doc.file)
        }
      }
    })
    
    const response = await axios.post(`/api/deals/${props.dealId}/properties/${props.property.id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    if (response.data.success || response.data.data) {
      const updatedProperty = response.data.data || response.data.property
      
      // ✅ تحديث الـ property المحلي
      Object.assign(props.property, updatedProperty)
      
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Property updated successfully',
        timer: 1500,
        showConfirmButton: false
      })
      
      emit('property-updated', updatedProperty)
      emit('refresh-deal')
      isEditing.value = false
    }
  } catch (error) {
    console.error('Error updating property:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to update property'
    })
  } finally {
    saving.value = false
  }
}

// Helper functions
function getAreaName(areaId) {
  if (!areaId) return '----'
  // ✅ تجيب الاسم من مصفوفة areas اللي جاية من الـ props
  const area = props.areas.find(a => a.id == areaId)
  return area?.name || '----'
}

function getPropertyTypeName(typeId) {
  if (!typeId) return '----'
  // ✅ تجيب الاسم من مصفوفة propertyTypes
  const type = props.propertyTypes.find(t => t.id == typeId)
  return type?.name || '----'
}
function getDeveloperName(devId) {
  if (!devId) return null
  const dev = props.developers.find(d => d.id === devId)
  return dev?.name
}

function formatBedrooms(value) {
  if (!value) return '----'
  if (value === 'studio') return 'Studio'
  return `${value} Bed${value > 1 ? 's' : ''}`
}

function formatNumber(value) {
  if (!value) return '----'
  return new Intl.NumberFormat().format(value)
}
// بعد الـ computed الموجودة
const showBedroomsField = computed(() => {
  const propertyTypeId = isEditing.value
    ? editData.value.property_type_id
    : props.property.property_type_id

  if (!propertyTypeId) return true

  const selectedType = props.propertyTypes.find(t => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''

  return !(typeName.includes('land') || typeName.includes('plot'))
})

// كمان لو اختار Land أو Plot، نحذف قيمة الـ bedrooms
watch(() => editData.value.property_type_id, (newTypeId) => {
  if (!newTypeId) return

  const selectedType = props.propertyTypes.find(t => t.id === newTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''

  if (typeName.includes('land') || typeName.includes('plot')) {
    editData.value.bedrooms = null
    props.property.bedrooms=null
    editData.value = {
      ...editData.value,
      bedrooms: null
    }
  }
})
// ========== Budget Dropdown for Edit Mode ==========
const showBudgetDropdown = ref(false)
const budgetTriggerRef = ref(null)
const budgetDropdownPanelRef = ref(null)
const budgetDropdownStyle = ref({})

const budgetFromDisplay = computed(() => {
    return editData.value.budget_from ? formatBudgetWithCommas(editData.value.budget_from) : ''
})

const budgetToDisplay = computed(() => {
    return editData.value.budget_to ? formatBudgetWithCommas(editData.value.budget_to) : ''
})

const budgetDisplay = computed(() => {
    const from = budgetFromDisplay.value
    const to = budgetToDisplay.value
    if (!from && !to) return 'Select budget range'
    if (from && to) return `${from} - ${to}`
    if (from) return `From ${from}`
    return `To ${to}`
})

function normalizeBudgetString(value) {
    return String(value ?? '').replace(/[^\d]/g, '')
}

function formatBudgetWithCommas(value) {
    if (!value && value !== 0) return ''
    const digits = normalizeBudgetString(value)
    if (!digits) return ''
    return Number(digits).toLocaleString('en-US')
}

function setBudgetValue(key, value) {
    const digits = normalizeBudgetString(value)
    editData.value[key] = digits ? Number(digits) : null
}

const purchasePriceDisplay = computed(() => {
    return editData.value.purchase_price
        ? formatBudgetWithCommas(editData.value.purchase_price)
        : ''
})

function setPurchasePrice(value) {
    const digits = normalizeBudgetString(value)
    editData.value.purchase_price = digits ? Number(digits) : null
}

function onMoneyKeypress(e) {
  if (!/^\d$/.test(e.key)) e.preventDefault()
}

function getBudgetTriggerElement() {
    let el = budgetTriggerRef.value
    if (Array.isArray(el)) el = el.find(Boolean)
    if (el && typeof el.getBoundingClientRect === 'function') return el
    if (el?.$el && typeof el.$el.getBoundingClientRect === 'function') return el.$el
    return null
}

function updateBudgetDropdownPosition() {
    const el = getBudgetTriggerElement()
    if (!el) return
    const r = el.getBoundingClientRect()
    budgetDropdownStyle.value = {
        position: 'fixed',
        top: `${Math.round(r.bottom + 6)}px`,
        left: `${Math.round(r.left)}px`,
        width: `${Math.max(Math.round(r.width), 220)}px`,
        zIndex: '10060'
    }
}

function removeBudgetDropdownListeners() {
    window.removeEventListener('scroll', updateBudgetDropdownPosition, true)
    window.removeEventListener('resize', updateBudgetDropdownPosition)
}

async function toggleBudgetDropdown(event) {
    if (event) {
        event.stopPropagation()
        event.preventDefault()
    }
    
    const next = !showBudgetDropdown.value
    showBudgetDropdown.value = next
    if (next) {
        document.body.style.overflow = 'hidden'
        const scrollY = window.scrollY
        await nextTick()
        updateBudgetDropdownPosition()
        window.scrollTo(0, scrollY)
        window.addEventListener('scroll', updateBudgetDropdownPosition, true)
        window.addEventListener('resize', updateBudgetDropdownPosition)
    } else {
        document.body.style.overflow = ''
        removeBudgetDropdownListeners()
    }
}

function onDocumentClick(event) {
    if (!showBudgetDropdown.value) return
    const t = event.target
    const triggerEl = getBudgetTriggerElement()
    const dropdownEl = budgetDropdownPanelRef.value
    if (triggerEl?.contains(t) || dropdownEl?.contains(t)) return
    
    showBudgetDropdown.value = false
    removeBudgetDropdownListeners()
}
onMounted(() => {
    document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    removeBudgetDropdownListeners()
})
</script>

<style scoped>
.property-card-readonly {
  background: #fff;
  border-color: #e5e7eb !important;
  transition: box-shadow 0.2s, outline 0.2s, border-color 0.2s, background 0.2s;
}

.property-card-readonly--editing {
  outline: 2px solid #fcb600;
  box-shadow: 0 0 0 6px rgba(252, 182, 0, 0.14);
  background: #fffef7 !important;
  border-color: #f59e0b !important;
}

.property-edit-documents {
  border-top: 1px solid #e2e8f0;
}

.property-documents-heading {
  font-size: 16px;
  font-weight: 600;
  color: #01062c;
  margin-bottom: 10px;
}

/* Match Buyer / Deal section edit (gold pencil) — easy to spot inside the card */
.section-edit-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #fcb600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s;
}
.section-edit-btn:hover {
  background: rgba(252, 182, 0, 0.15);
}
.info-label {
  font-size: 12px !important;
  font-weight: 500;
  color: #64748B;
  margin-bottom: 6px;
  display: block;
}
.info-value {
  font-size: 14px !important;
  font-weight: 500;
  color: #01062C;
}
.form-label-custom {
  font-size: 12px !important;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 4px;
  display: block;
}
.custom-input {
  height: 40px !important;
  min-height: 40px;
  border-radius: 8px !important;
  border: 1px solid #E2E8F0 !important;
  font-size: 12px !important;
  width: 100%;
  padding: 0 12px;
  box-sizing: border-box;
}

/* Edit form: center text in box; keep placeholders visually smaller than labels */
.property-card-edit .custom-input {
  line-height: 40px !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}
.property-card-edit .custom-input::placeholder {
  font-size: 10px !important;
  line-height: normal !important;
  color: #94a3b8 !important;
}

.property-card-edit :deep(.custom-v-select.v-select) {
  --vs-actions-padding: 0 4px;
}
.property-card-edit :deep(.custom-v-select .vs__dropdown-toggle) {
  padding: 0 10px !important;
}
.property-card-edit :deep(.custom-v-select.vs--single .vs__selected-options) {
  align-items: stretch !important;
  align-self: stretch !important;
  flex-wrap: nowrap !important;
  min-height: 0;
  height: 100% !important;
}
.property-card-edit :deep(.custom-v-select.vs--single .vs__selected) {
  margin: 0 !important;
  align-self: stretch !important;
  height: 100% !important;
  display: flex !important;
  align-items: center !important;
}
.property-card-edit :deep(.custom-v-select .vs__search) {
  margin: 0 !important;
}
.property-card-edit :deep(.custom-v-select .vs__actions) {
  padding: 0 4px !important;
}

:deep(.custom-v-select .vs__dropdown-toggle) {
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  min-height: 40px !important;
  height: 40px !important;
  font-size: 12px !important;
  display: flex !important;
  align-items: stretch !important;
}
.section-edit-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #fcb600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.section-edit-btn:hover {
  background: #fef3c7;
  border-color: #faa300;
}
.input-group-text {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 12px;
  color: #64748b;
}
.btn-sm {
  padding: 4px 12px;
  font-size: 12px;
}

/* Documents Grid */
.documents-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
}

.document-card {
  width: 160px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
  transition: all 0.2s;
}

.document-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.document-preview {
  cursor: pointer;
}

.document-thumbnail {
  width: 100%;
  height: 100px;
  object-fit: cover;
  background: #f8fafc;
}

.document-icon-placeholder {
  width: 100%;
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}

.document-icon-large {
  font-size: 40px;
  color: #64748b;
}

.document-name {
  padding: 6px;
  font-size: 10px;
  color: #334155;
  text-align: center;
  word-break: break-word;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.document-actions {
  display: flex;
  gap: 6px;
  padding: 6px;
  border-top: 1px solid #e2e8f0;
}

.doc-action-btn {
  flex: 1;
  padding: 4px 6px;
  font-size: 10px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
}

.doc-action-btn.view {
  color: #3b82f6;
}

.doc-action-btn.delete {
  color: #ef4444;
}

.doc-action-btn:hover {
  background: #f1f5f9;
}

/* Preview Modal */
.doc-preview-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.doc-preview-modal {
  position: relative;
  background: #fff;
  border-radius: 12px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  overflow: hidden;
}

.doc-preview-close {
  position: absolute;
  top: 10px;
  right: 10px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  cursor: pointer;
  z-index: 10;
}

.doc-preview-body {
  padding: 20px;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 400px;
}

.doc-preview-image {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
}

.doc-preview-iframe {
  width: 100%;
  height: 70vh;
  border: none;
}

.btn-download {
  display: inline-block;
  padding: 8px 16px;
  background: #3b82f6;
  color: #fff;
  border-radius: 8px;
  text-decoration: none;
}

/* Fixed Save/Cancel — same pattern as ViewDealModal .edit-lead-bottom-bar */
.property-edit-bar-spacer {
  height: 56px;
  width: 100%;
  flex-shrink: 0;
}

.property-card-edit-bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 1rem;
  padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
  background: #fff;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
  z-index: 1060;
}

.property-card-edit-bottom-bar .edit-bar-btn {
  padding: 8px 20px;
  border-radius: 100px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.property-card-edit-bottom-bar .edit-bar-cancel {
  background: #f4f4f4;
  color: #01062c;
}

.property-card-edit-bottom-bar .edit-bar-cancel:hover {
  background: #e2e8f0;
}

.property-card-edit-bottom-bar .edit-bar-save {
  background: #01062c;
  color: #fff;
}

.property-card-edit-bottom-bar .edit-bar-save:hover:not(:disabled) {
  background: #060a2b;
}

.property-card-edit-bottom-bar .edit-bar-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Budget Dropdown Styles - نفس نظام Lead Search */
.budget-field-wrap {
    position: relative;
}

.budget-dropdown--portal {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
    padding: 10px;
}

.budget-from-to-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.budget-col {
    min-width: 0;
}

.budget-input-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.budget-dropdown-input {
    height: 38px !important;
}

.custom-date-trigger {
    width: 100%;
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    font-size: 13px;
    color: #64748B;
    font-family: 'Montserrat';
    cursor: pointer;
}

.custom-date-trigger:hover {
    border-color: #cbd5e1;
}
</style>