<template>
  <div class="inline-section-editor">
    <div v-if="loading" class="text-center py-3 text-muted">Loading form...</div>
    <template v-else>
      <DealForm
        v-model="localFormData"
        :deal-type="dealType"
        :active-edit-section="sectionKey"
        :inline-mode="true"
        :users="lookup.users || []"
        :sources="lookup.sources || []"
        :property-types="lookup.propertyTypes || []"
        :developers="lookup.developers || []"
        :areas="lookup.areas || []"
        :selected-stage-id="selectedStageId"
        :selected-stage-name="selectedStageName || ''"
        :show-errors="showErrors"
        :field-errors="fieldErrors"
        @search-areas="$emit('search-areas', $event)"
        @search-subcommunities="$emit('search-subcommunities', $event)"
        @search-projects="$emit('search-projects', $event)"
      />
      <div class="edit-deal-actions mt-3 pt-3 border-top">
        <button type="button" class="btn-history-cancel me-2" @click="$emit('cancel')">Cancel</button>
        <button type="button" class="btn-save-deal-view" :disabled="saving" @click="$emit('save')">
          <span v-if="saving">Saving...</span>
          <span v-else>Save</span>
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import DealForm from './DealForm.vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  sectionKey: { type: String, default: null },
  dealType: { type: String, default: 'primary' },
  lookup: { type: Object, default: () => ({}) },
  selectedStageId: { type: [Number, String], default: null },
  selectedStageName: { type: String, default: '' },
  showErrors: { type: Boolean, default: false },
  fieldErrors: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'search-areas', 'search-subcommunities', 'search-projects', 'cancel', 'save'])

const localFormData = computed({
  get: () => props.modelValue,
  set: (value) => {
    // bubble up v-model updates
    emit('update:modelValue', value)
  },
})
</script>

<style scoped>
.inline-section-editor {
  margin-top: 2px;
}

:deep(.inline-section-editor .deal-form-container) { padding: 0; }

.edit-deal-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.btn-history-cancel {
  height: 38px;
  min-width: 90px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.btn-save-deal-view {
  height: 38px;
  min-width: 90px;
  border-radius: 999px;
  border: none;
  background: #0f172a;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.btn-save-deal-view:disabled {
  opacity: 0.7;
}
</style>
