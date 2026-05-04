<template>
  <div class="add-property-form">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label-custom">Property Address</label>
        <v-select
          v-model="formData.area_id"
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
        <b-form-input v-model="formData.unit_no" placeholder="Enter Unit No" class="custom-input" />
      </div>

      <div class="col-md-6">
        <label class="form-label-custom">Property Type</label>
        <v-select
          v-model="formData.property_type_id"
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
          v-model="formData.bedrooms"
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
        <b-form-input v-model="formData.unit_size" type="number" placeholder="Size" class="custom-input" />
      </div>

      <div class="col-md-6" v-if="showBudgetFields">
        <label class="form-label-custom">Budget From</label>
        <div class="input-group">
          <b-form-input v-model="formData.budget_from" type="number" placeholder="Min" class="custom-input" />
          <span class="input-group-text">AED</span>
        </div>
      </div>

      <div class="col-md-6" v-if="showBudgetFields">
        <label class="form-label-custom">Budget To</label>
        <div class="input-group">
          <b-form-input v-model="formData.budget_to" type="number" placeholder="Max" class="custom-input" />
          <span class="input-group-text">AED</span>
        </div>
      </div>

      <div class="col-md-6" v-if="showPurchasePrice">
        <label class="form-label-custom">Purchase Price</label>
        <div class="input-group">
          <b-form-input v-model="formData.purchase_price" type="number" placeholder="Amount" class="custom-input" />
          <span class="input-group-text">AED</span>
        </div>
      </div>

      <div class="col-md-6" v-if="showPropertyCommission">
        <label class="form-label-custom">Property Commission %</label>
        <div class="input-group">
          <b-form-input v-model="formData.commission" type="number" step="0.01" placeholder="Commission %" class="custom-input" />
          <span class="input-group-text">%</span>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label-custom">Developer</label>
        <v-select
          v-model="formData.developer_id"
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
        <b-form-input v-model="formData.developer_name" placeholder="Contact Person" class="custom-input" />
      </div>

      <div class="col-md-6">
        <label class="form-label-custom">Developer Contact Phone</label>
        <b-form-input v-model="formData.developer_phone" placeholder="Phone Number" class="custom-input" />
      </div>
    </div>

    <div class="d-flex justify-content-end gap-3 mt-4">
      <button type="button" class="btn-cancel" @click="onCancel">Cancel</button>
      <button type="button" class="btn-save" @click="saveProperty" :disabled="saving">
        <span v-if="saving"><b-spinner small></b-spinner> Saving...</span>
        <span v-else>{{ submitLabel }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { BFormInput, BSpinner } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  dealId: { type: Number, required: true },
  areas: { type: Array, default: () => [] },
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  selectedStageName: { type: String, default: '' },
  submitLabel: { type: String, default: 'Add Property' }
})

const emit = defineEmits(['property-added', 'cancel'])

const saving = ref(false)
const formData = ref({
  unit_no: '',
  property_type_id: null,
  bedrooms: null,
  unit_size: '',
  area_id: null,
  developer_id: null,
  developer_name: '',
  developer_phone: '',
  budget_from: null,
  budget_to: null,
  purchase_price: null,
  commission: null,
})

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

const showBedroomsField = computed(() => {
  const propertyTypeId = formData.value.property_type_id
  if (!propertyTypeId) return true
  const selectedType = props.propertyTypes.find(t => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''
  if (typeName.includes('land') || typeName.includes('plot')) return false
  return true
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

function resetForm() {
  formData.value = {
    unit_no: '',
    property_type_id: null,
    bedrooms: null,
    unit_size: '',
    area_id: null,
    developer_id: null,
    developer_name: '',
    developer_phone: '',
    budget_from: null,
    budget_to: null,
    purchase_price: null,
    commission: null,
  }
}

defineExpose({ resetForm })

async function saveProperty() {
  saving.value = true
  try {
    const response = await axios.post(`/api/deals/${props.dealId}/properties`, formData.value)
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Property added successfully',
        timer: 1500,
        showConfirmButton: false
      })
      emit('property-added', response.data.data)
      resetForm()
    }
  } catch (error) {
    console.error('Error adding property:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to add property'
    })
  } finally {
    saving.value = false
  }
}

function onCancel() {
  resetForm()
  emit('cancel')
}

watch(() => formData.value.property_type_id, () => {
  if (!showBedroomsField.value) {
    formData.value.bedrooms = null
  }
})
</script>

<style scoped>
.form-label-custom {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 4px;
  display: block;
}
.custom-input {
  height: 40px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  font-size: 13px;
  width: 100%;
  padding: 0 12px;
}
:deep(.custom-v-select .vs__dropdown-toggle) {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  min-height: 40px;
  height: 40px;
}
.input-group-text {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 12px;
  color: #64748b;
}
.btn-cancel {
  background: #f4f4f4;
  border: none;
  padding: 8px 24px;
  border-radius: 100px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}
.btn-save {
  background: #01062c;
  border: none;
  padding: 8px 24px;
  border-radius: 100px;
  font-size: 14px;
  font-weight: 500;
  color: #fff;
  cursor: pointer;
}
.btn-save:hover:not(:disabled) {
  background: #1e293b;
}
.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.location-option {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}
.location-icon {
  margin-top: 2px;
  font-size: 14px;
  color: #64748b;
}
</style>
