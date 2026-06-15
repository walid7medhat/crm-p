<template>
  <div class="dh-date-range-panel">
    <p class="dh-date-range-title">Custom date range</p>

    <div class="dh-date-range-presets" role="group" aria-label="Quick ranges">
      <button
        v-for="preset in presets"
        :key="preset.id"
        type="button"
        class="dh-date-range-preset"
        @click="$emit('preset', preset)"
      >
        {{ preset.label }}
      </button>
    </div>

    <div class="dh-date-range-fields">
      <div class="dh-date-range-field">
        <div class="dh-date-range-field-head">
          <span class="dh-date-range-label">From</span>
          <span v-if="fromPreview" class="dh-date-range-preview">{{ fromPreview }}</span>
        </div>
        <DateYmdSelect
          id-prefix="dh-from"
          :model-value="draftFrom"
          :invalid="!!error && !draftFrom"
          @update:model-value="$emit('update:draftFrom', $event)"
        />
      </div>
      <div class="dh-date-range-field">
        <div class="dh-date-range-field-head">
          <span class="dh-date-range-label">To</span>
          <span v-if="toPreview" class="dh-date-range-preview">{{ toPreview }}</span>
        </div>
        <DateYmdSelect
          id-prefix="dh-to"
          :model-value="draftTo"
          :invalid="!!error && !draftTo"
          @update:model-value="$emit('update:draftTo', $event)"
        />
      </div>
    </div>

    <p v-if="error" class="dh-date-range-error">{{ error }}</p>

    <div class="dh-date-range-actions">
      <button type="button" class="dh-date-range-btn dh-date-range-btn--ghost" @click="$emit('cancel')">
        Cancel
      </button>
      <button type="button" class="dh-date-range-btn dh-date-range-btn--primary" @click="$emit('apply')">
        Apply
      </button>
    </div>
  </div>
</template>

<script setup>
import DateYmdSelect from '@/components/dashboard/home/DateYmdSelect.vue'

defineProps({
  presets: { type: Array, required: true },
  draftFrom: { type: String, default: '' },
  draftTo: { type: String, default: '' },
  error: { type: String, default: '' },
  fromPreview: { type: String, default: '' },
  toPreview: { type: String, default: '' },
})

defineEmits(['preset', 'update:draftFrom', 'update:draftTo', 'cancel', 'apply'])
</script>
