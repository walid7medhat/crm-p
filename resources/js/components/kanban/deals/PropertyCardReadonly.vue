<template>
  <div class="property-card-readonly border rounded-3 p-3 mb-3" :class="{ 'border-warning bg-light': isEditing }">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center gap-2">
        <span class="badge" :class="isEditing ? 'bg-warning text-dark' : 'bg-secondary'">
          Property {{ index + 1 }}
        </span>
        <!-- <span v-if="isEditing" class="badge bg-warning text-dark">Editing Mode</span> -->
      </div>
      <div class="d-flex gap-2">
        <button 
          v-if="!isEditing && !readonly" 
          type="button" 
          class="section-edit-btn" 
          @click="startEdit"
          title="Edit this Property"
        >
          <iconify-icon icon="lucide:pencil" />
        </button>
        <button 
          v-if="isEditing" 
          type="button" 
          class="btn btn-sm btn-success" 
          @click="saveEdit"
          :disabled="saving"
        >
          <span v-if="saving"><b-spinner small></b-spinner> Saving...</span>
          <span v-else><iconify-icon icon="lucide:check" /> Save</span>
        </button>
        <button 
          v-if="isEditing" 
          type="button" 
          class="btn btn-sm btn-secondary" 
          @click="cancelEdit"
        >
          <iconify-icon icon="lucide:x" /> Cancel
        </button>
      </div>
    </div>

    <!-- ========== VIEW MODE ========== -->
    <div v-if="!isEditing">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="info-group">
            <label class="info-label">Property Address</label>
            <p class="info-value mb-0">{{property.area_name||'----' }}</p>
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
        <div class="col-md-6">
          <div class="info-group">
            <label class="info-label">Unit Size (sq.ft)</label>
            <p class="info-value mb-0">{{ property.unit_size || '----' }}</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.budget_from">
          <div class="info-group">
            <label class="info-label">Budget From</label>
            <p class="info-value mb-0">{{ formatNumber(property.budget_from) }} AED</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.budget_to">
          <div class="info-group">
            <label class="info-label">Budget To</label>
            <p class="info-value mb-0">{{ formatNumber(property.budget_to) }} AED</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.purchase_price">
          <div class="info-group">
            <label class="info-label">Purchase Price</label>
            <p class="info-value mb-0">{{ formatNumber(property.purchase_price) }} AED</p>
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
            <label class="info-label">Developer Contact Name</label>
            <p class="info-value mb-0">{{ property.developer_name }}</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.developer_phone">
          <div class="info-group">
            <label class="info-label">Developer Contact Phone</label>
            <p class="info-value mb-0">{{ property.developer_phone }}</p>
          </div>
        </div>
        <div class="col-md-6" v-if="property.commission">
          <div class="info-group">
            <label class="info-label">Property Commission</label>
            <p class="info-value mb-0">{{ property.commission }}%</p>
          </div>
        </div>
      </div>

      <!-- Payment Proof Documents -->
      <div class="row mt-3" v-if="hasDocuments(property.payment_proof)">
        <div class="col-12">
          <div class="info-group">
            <label class="info-label">Payment Proof</label>
            <div class="documents-grid">
              <div 
                v-for="(doc, idx) in getDocumentsArray(property.payment_proof)" 
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
          </div>
        </div>
      </div>

      <!-- SPA Documents -->
      <div class="row mt-3" v-if="hasDocuments(property.spa_document)">
        <div class="col-12">
          <div class="info-group">
            <label class="info-label">SPA Document</label>
            <div class="documents-grid">
              <div 
                v-for="(doc, idx) in getDocumentsArray(property.spa_document)" 
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
          </div>
        </div>
      </div>
    </div>

    <!-- ========== EDIT MODE (كل Property لوحدها) ========== -->
    <div v-else>
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
          <label class="form-label-custom">Unit No</label>
          <b-form-input v-model="editData.unit_no" placeholder="Enter Unit No" class="custom-input" />
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

        <div class="col-md-6">
          <label class="form-label-custom">Unit Size (sq.ft)</label>
          <b-form-input v-model="editData.unit_size" type="number" placeholder="Size" class="custom-input" />
        </div>

        <div class="col-md-6" v-if="showBudgetFields">
          <label class="form-label-custom">Budget From</label>
          <div class="input-group">
            <b-form-input v-model="editData.budget_from" type="number" placeholder="Min" class="custom-input" />
            <span class="input-group-text">AED</span>
          </div>
        </div>

        <div class="col-md-6" v-if="showBudgetFields">
          <label class="form-label-custom">Budget To</label>
          <div class="input-group">
            <b-form-input v-model="editData.budget_to" type="number" placeholder="Max" class="custom-input" />
            <span class="input-group-text">AED</span>
          </div>
        </div>

        <div class="col-md-6" v-if="showPurchasePrice">
          <label class="form-label-custom">Purchase Price</label>
          <div class="input-group">
            <b-form-input v-model="editData.purchase_price" type="number" placeholder="Amount" class="custom-input" />
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
          <label class="form-label-custom">Developer Contact Name</label>
          <b-form-input v-model="editData.developer_name" placeholder="Contact Person" class="custom-input" />
        </div>

        <div class="col-md-6">
          <label class="form-label-custom">Developer Contact Phone</label>
          <b-form-input v-model="editData.developer_phone" placeholder="Phone Number" class="custom-input" />
        </div>

        <div class="col-md-6" v-if="showPropertyCommission">
          <label class="form-label-custom">Property Commission %</label>
          <div class="input-group">
            <b-form-input v-model="editData.commission" type="number" step="0.01" placeholder="Commission %" class="custom-input" />
            <span class="input-group-text">%</span>
          </div>
        </div>

        <!-- Documents Upload in Edit Mode -->
        <div class="col-12 mt-3">
          <label class="form-label-custom">Payment Proof</label>
          <DocumentUpload
            v-model="paymentProofCombined"
            category="property"
            :document-types="[{ id: 'payment_proof', name: 'Payment Proof' }]"
            :compact="true"
          />
        </div>

        <div class="col-12">
          <label class="form-label-custom">SPA Document</label>
          <DocumentUpload
            v-model="spaDocumentCombined"
            category="property"
            :document-types="[{ id: 'spa', name: 'SPA Document' }]"
            :compact="true"
          />
        </div>

        <div class="col-12 text-muted small mt-2">
          <iconify-icon icon="lucide:info" /> Upload new documents to replace existing ones
        </div>
      </div>
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
import { ref, computed ,watch,onMounted} from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import DocumentUpload from './DocumentUpload.vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  property: { type: Object, required: true },
  index: { type: Number, required: true },
  dealId: { type: Number, required: true },
  areas: { type: Array, default: () => [] },
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  selectedStageName: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['property-updated', 'refresh-deal'])

const isEditing = ref(false)
const saving = ref(false)
const editData = ref({})
const paymentProofCombined = ref([])
const spaDocumentCombined = ref([])
const previewDoc = ref(null)

// Stage detection
const showBudgetFields = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('eoi')
})
const showPurchasePrice = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('booking') || stageName.includes('spa') || stageName.includes('won')
})
const showPropertyCommission = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('won') || stageName.includes('deal won')
})

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
  if (typeof docs === 'string') {
    try {
      const parsed = JSON.parse(docs)
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  }
  return Array.isArray(docs) ? docs : []
}

function hasDocuments(docs) {
  return getDocumentsArray(docs).length > 0
}

function getDocumentUrl(doc) {
  if (!doc) return null
  return doc.url || doc.path || doc.file_url || null
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
    // ✅ الحالة 1: لو في ID (يعني من deal_documents)
    if (doc.id) {
      await axios.delete(`/api/deals/documents/${doc.id}`)
    } 
    // ✅ الحالة 2: لو من Property Documents (payment_proof أو spa_document)
    else {
      // حذف الملف من التخزين
      if (doc.path) {
        await axios.delete('/api/deals/property-document', {
          data: {
            deal_id: props.dealId,
            property_id: props.property.id,
            document_type: type,  // 'payment_proof' or 'spa_document'
            file_path: doc.path
          }
        })
      }
    }
    
    // تحديث الـ parent
    emit('refresh-deal')
    
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
  paymentProofCombined.value = []
  spaDocumentCombined.value = []
  isEditing.value = true
}

function cancelEdit() {
  isEditing.value = false
  editData.value = {}
  paymentProofCombined.value = []
  spaDocumentCombined.value = []
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
    
    if (paymentProofCombined.value && paymentProofCombined.value.length) {
      paymentProofCombined.value.forEach((doc, idx) => {
        if (doc.file) {
          formData.append(`payment_proof[${idx}]`, doc.file)
        }
      })
    }
    
    if (spaDocumentCombined.value && spaDocumentCombined.value.length) {
      spaDocumentCombined.value.forEach((doc, idx) => {
        if (doc.file) {
          formData.append(`spa_document[${idx}]`, doc.file)
        }
      })
    }
    
    const response = await axios.post(`/api/deals/${props.dealId}/properties/${props.property.id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    if (response.data.success || response.data.data) {
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Property updated successfully',
        timer: 1500,
        showConfirmButton: false
      })
      
      const updatedProperty = response.data.data || response.data.property
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
  const propertyTypeId = props.property.property_type_id
  if (!propertyTypeId) return true
  
  const selectedType = props.propertyTypes.find(t => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''
  
  // لو الاسم يحتوي على land أو plot، نخفي الـ Bedrooms
  if (typeName.includes('land') || typeName.includes('plot')) {
    return false
  }
  
  return true
})

// كمان لو اختار Land أو Plot، نحذف قيمة الـ bedrooms
watch(() => props.property.property_type_id, (newTypeId) => {
  if (!showBedroomsField.value) {
    props.property.bedrooms = null
  }
})
</script>

<style scoped>
.property-card-readonly {
  background: #fff;
  border-color: #e5e7eb !important;
  transition: all 0.2s;
}
.property-card-readonly.border-warning {
  border-color: #faa300 !important;
  background: #fffbeb !important;
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
  font-size: 13px !important;
  width: 100%;
  padding: 0 12px;
}
:deep(.custom-v-select .vs__dropdown-toggle) {
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  min-height: 40px !important;
  height: 40px !important;
  font-size: 13px;
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
</style>