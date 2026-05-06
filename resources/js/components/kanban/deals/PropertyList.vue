<template>
  <div class="property-list">
    <!-- <div class="mb-3">
      <button 
        type="button" 
        class="btn-add-property"
        @click="addProperty"
      >
        <iconify-icon icon="lucide:plus" class="me-1"></iconify-icon>
        Add Another Property
      </button>
    </div> -->
    
    <PropertyCard
      v-for="(property, index) in localProperties"
      :key="property.id"
      :deal-id="dealId"
      :property="property"
      :index="index"
      :is-main="index === 0"
      :property-types="propertyTypes"
      :areas="areas"
      :developers="developers"
      :show-errors="showErrors"
      :required-fields="requiredFields"
      :deal-type="dealType"
      :selected-stage-name="selectedStageName"
      :show-property-commission="showPropertyCommission"
      :show-purchase-price="showPurchasePrice"
      :show-property-documents="showPropertyDocuments"
      :property-doc-types="propertyDocTypes"
      @update:property="updateProperty"
      @remove="removeProperty"
      @search-areas="(search) => emit('search-areas', search)"
      :inline-mode="props.inlineMode"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import PropertyCard from './PropertyCard.vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  dealId: { type: [Number, String], default: null },
  propertyTypes: { type: Array, default: () => [] },
  areas: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  showErrors: { type: Boolean, default: false },
  requiredFields: { type: Array, default: () => [] },
  dealType: { type: String, default: 'primary' },
  selectedStageName: { type: String, default: '' },
  showPropertyCommission: { type: Boolean, default: false },
  showPurchasePrice: { type: Boolean, default: false },
  showPropertyDocuments: { type: Boolean, default: false },
  propertyDocTypes: { type: Array, default: () => [] },
  inlineMode:{type: Boolean, default: false }

})

const emit = defineEmits(['update:modelValue', 'search-areas'])

const localProperties = ref([...props.modelValue])

// Generate unique ID
function generateId() {
  return Date.now() + Math.random().toString(36).substr(2, 9)
}

// Add new property
function addProperty() {
  const newProperty = {
    id: generateId(),
    sort_order: localProperties.value.length,
    unit_no: '',
    property_type_id: null,
    bedrooms: null,
    unit_size: '',
    area_id: null,
    project_id: null,
    developer_id: null,
    developer_name: '',
    developer_phone: '',
    budget_from: null,
    budget_to: null,
    purchase_price: null,
    commission: null,
    rental_price: null,
    payment_proof: [],
    spa_document: [],
    contract_document: [],
    ejari_document: [],
    listing_id: null,
    listing_status: null,
  }
  localProperties.value.push(newProperty)
  emitUpdate()
}

// Update property at index
function updateProperty({ index, property }) {
  localProperties.value[index] = { ...property }
  emitUpdate()
}

// Remove property at index
function removeProperty(index) {
  if (localProperties.value.length > 1) {
    localProperties.value.splice(index, 1)
    localProperties.value.forEach((prop, idx) => {
      prop.sort_order = idx
    })
    emitUpdate()
  }
}

// Emit update to parent
function emitUpdate() {
  emit('update:modelValue', localProperties.value)
}

// Watch for external changes
watch(() => props.modelValue, (newVal) => {
  if (JSON.stringify(newVal) !== JSON.stringify(localProperties.value)) {
    localProperties.value = [...newVal]
  }
}, { deep: true })

// Initialize if empty
if (localProperties.value.length === 0) {
  addProperty()
}
</script>

<style scoped>
.btn-add-property {
  background: transparent;
  border: 1px solid #01062C;
  border-radius: 100px;
  padding: 8px 20px;
  font-size: 13px;
  font-weight: 500;
  color: #01062C;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s;
}

.btn-add-property:hover {
  background: #01062C;
  color: #fff;
}

.property-list {
  width: 100%;
}
</style>