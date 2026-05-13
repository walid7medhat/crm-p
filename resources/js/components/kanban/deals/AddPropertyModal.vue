<template>
  <b-modal
    v-model="show"
    title="Add New Property"
    size="lg"
    centered
    hide-footer
    @hidden="onModalHidden"
  >
    <div class="p-3">
      <AddPropertyForm
        ref="formRef"
        :deal-id="dealId"
        :areas="areas"
        :property-types="propertyTypes"
        :developers="developers"
        :selected-stage-name="selectedStageName"
        :selected-stage-order="selectedStageOrder"
        :deal-type="dealType"
        @property-added="onPropertyAdded"
        @cancel="closeModal"
      />
    </div>
  </b-modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import AddPropertyForm from './AddPropertyForm.vue'

const props = defineProps({
  modelValue: Boolean,
  dealId: { type: Number, required: true },
  areas: { type: Array, default: () => [] },
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  selectedStageName: { type: String, default: '' },
  selectedStageOrder: { type: [Number, String], default: 0 },
  dealType: { type: String, default: 'primary' }
})

const emit = defineEmits(['update:modelValue', 'property-added', 'refresh'])

const show = ref(!!props.modelValue)
const formRef = ref(null)

function onModalHidden() {
  formRef.value?.resetForm?.()
}

function onPropertyAdded(data) {
  emit('property-added', data)
  emit('refresh')
  show.value = false
}

function closeModal() {
  show.value = false
}

watch(() => props.modelValue, (val) => {
  show.value = val
})

watch(show, (val) => {
  emit('update:modelValue', val)
})
</script>
