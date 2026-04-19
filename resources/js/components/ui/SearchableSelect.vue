<template>
  <div class="crm-field" :class="{ 'crm-field--inline': inline }">
    <label v-if="label" :for="inputId" class="crm-field__label">{{ label }}</label>
    <v-select
      :id="inputId"
      :model-value="modelValue"
      class="crm-searchable-select"
      :class="[{ 'is-invalid': !!error }, inputClass]"
      :style="inputStyle"
      :options="resolvedOptions"
      :label="resolvedLabelKey"
      :reduce="resolvedReduce"
      :placeholder="placeholder"
      :disabled="disabled"
      :clearable="clearable"
      :filterable="filterable"
      :append-to-body="appendToBody"
      @update:model-value="onInput"
    >
      <template #open-indicator="{ attributes }">
        <span v-bind="attributes">
          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon" />
        </span>
      </template>
    </v-select>
    <p v-if="hint && !error" class="crm-field__hint">{{ hint }}</p>
    <p v-if="error" class="crm-field__error" role="alert">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const props = defineProps({
  modelValue: { type: [String, Number, Array, Object, Boolean, null], default: null },
  /** Built-in option sets (keeps templates small). */
  preset: { type: String, default: '' },
  /** Full option list when not using preset. */
  options: { type: Array, default: () => [] },
  optionLabel: { type: String, default: 'label' },
  optionValue: { type: String, default: 'value' },
  /** Optional custom reducer; defaults to reading optionValue from objects. */
  reduce: { type: Function, default: null },
  placeholder: { type: String, default: 'Search or select…' },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  filterable: { type: Boolean, default: true },
  appendToBody: { type: Boolean, default: true },
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  inputId: { type: String, default: () => `crm-sel-${Math.random().toString(36).slice(2, 9)}` },
  inputClass: { type: [String, Object, Array], default: '' },
  inputStyle: { type: [String, Object], default: '' },
  /** Compact toolbar layout (no extra vertical rhythm). */
  inline: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const PRESETS = {
  perPage10_15_20: [
    { value: 10, label: '10' },
    { value: 15, label: '15' },
    { value: 20, label: '20' },
  ],
  perPage10_15_20_all: [
    { value: 10, label: '10' },
    { value: 15, label: '15' },
    { value: 20, label: '20' },
    { value: 'all', label: 'All' },
  ],
  userEntries5101520: [5, 10, 15, 20].map((n) => ({ value: n, label: String(n) })),
  userGridPerPage1_12: Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: String(i + 1) })),
  userStatusFilter: [
    { value: 'Status', label: 'Status' },
    { value: 'Active', label: 'Active' },
    { value: 'Inactive', label: 'Inactive' },
  ],
  dashboardPeriod: [
    { value: 'today', label: 'Today' },
    { value: 'week', label: 'This week' },
    { value: 'month', label: 'This month' },
    { value: 'year', label: 'This year' },
  ],
  hrTeamFilter: [
    { value: 'all', label: 'All teams' },
    { value: 'mine', label: 'My team' },
  ],
  hrTreeStatus: [
    { value: 'all', label: 'All statuses' },
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'late', label: 'Late' },
  ],
  areasType: [
    { value: '', label: 'All Types' },
    { value: 'country', label: 'Country' },
    { value: 'city', label: 'City' },
    { value: 'area', label: 'Area' },
    { value: 'community', label: 'Community' },
    { value: 'sub_community', label: 'Sub Community' },
    { value: 'cluster', label: 'Cluster' },
    { value: 'building', label: 'Building' },
    { value: 'phaces', label: 'Phaces' },
  ],
  rolesPerPage: [
    { value: 5, label: '5' },
    { value: 10, label: '10' },
    { value: 25, label: '25' },
    { value: 50, label: '50' },
  ],
  rolesStatus: [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
  ],
  languagesPageSize: [
    { value: 5, label: '5' },
    { value: 10, label: '10' },
    { value: 25, label: '25' },
    { value: 50, label: '50' },
  ],
  languagesStatus: [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
  ],
  currenciesPageSize: [
    { value: 5, label: '5' },
    { value: 10, label: '10' },
    { value: 25, label: '25' },
    { value: 50, label: '50' },
  ],
  currencySymbol: ['$', '€', '£', '₹', 'د.إ', 'AED', 'USD', 'EUR', 'GBP'].map((s) => ({ value: s, label: s })),
  currencyCode: ['USD', 'EUR', 'GBP', 'AED', 'INR', 'SAR'].map((s) => ({ value: s, label: s })),
  yesNo: [
    { value: 1, label: 'Yes' },
    { value: 0, label: 'No' },
  ],
  ownerSalutation: [
    { value: 'Mr.', label: 'Mr.' },
    { value: 'Mrs.', label: 'Mrs.' },
    { value: 'Ms.', label: 'Ms.' },
    { value: 'Dr.', label: 'Dr.' },
  ],
  earningPeriodLower: [
    { value: 'yearly', label: 'Yearly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'today', label: 'Today' },
  ],
  salesListingsPeriod3: [
    { value: 'yearly', label: 'Yearly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'weekly', label: 'Weekly' },
  ],
  campaignsPeriodTitle: [
    { value: 'Yearly', label: 'Yearly' },
    { value: 'Monthly', label: 'Monthly' },
    { value: 'Weekly', label: 'Weekly' },
    { value: 'Today', label: 'Today' },
  ],
  overviewTimeframeCapitalized: [
    { value: 'Yearly', label: 'Yearly' },
    { value: 'Monthly', label: 'Monthly' },
    { value: 'Weekly', label: 'Weekly' },
    { value: 'Today', label: 'Today' },
  ],
  aiPeriodLabels: [
    { value: 'Today', label: 'Today' },
    { value: 'Weekly', label: 'Weekly' },
    { value: 'Monthly', label: 'Monthly' },
    { value: 'Yearly', label: 'Yearly' },
  ],
  userOverviewTimeframe: [
    { value: 'today', label: 'Today' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
  ],
  generatedContentPeriodLabels: [
    { value: 'Today', label: 'Today' },
    { value: 'Weekly', label: 'Weekly' },
    { value: 'Monthly', label: 'Monthly' },
    { value: 'Yearly', label: 'Yearly' },
  ],
  assignRolePerPage1_10: Array.from({ length: 10 }, (_, i) => ({ value: i + 1, label: String(i + 1) })),
  assignRoleUserFilterStatus: [
    { value: '', label: 'Status' },
    { value: 'Active', label: 'Active' },
    { value: 'Inactive', label: 'Inactive' },
  ],
  roleToolbarStatusLabels: [
    { value: 'Status', label: 'Status' },
    { value: 'Active', label: 'Active' },
    { value: 'Inactive', label: 'Inactive' },
  ],
  leadAssignmentMode: [
    { value: 'realtime', label: 'Realtime' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'manual', label: 'Manual' },
  ],
  leadAssignmentStrategy: [
    { value: 'ai_hybrid', label: 'AI hybrid' },
    { value: 'attendance_priority', label: 'Attendance priority' },
    { value: 'performance', label: 'Performance' },
    { value: 'round_robin', label: 'Round robin' },
  ],
  leadReportStage: [
    { value: '', label: 'All stages' },
    { value: 'new', label: 'New' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'qualified', label: 'Qualified' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'negotiation', label: 'Negotiation' },
    { value: 'converted', label: 'Converted' },
    { value: 'lost', label: 'Lost' },
  ],
}

const resolvedOptions = computed(() => {
  if (props.preset && PRESETS[props.preset]) {
    return PRESETS[props.preset]
  }
  return props.options || []
})

const resolvedLabelKey = computed(() => props.optionLabel || 'label')

const resolvedReduce = computed(() => {
  if (props.reduce) {
    return props.reduce
  }
  return (opt) => {
    if (opt && typeof opt === 'object' && props.optionValue in opt) {
      return opt[props.optionValue]
    }
    return opt
  }
})

function onInput(v) {
  emit('update:modelValue', v)
}
</script>

<style scoped>
.crm-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
}
.crm-field--inline {
  width: auto;
}
.crm-field__label {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin: 0;
}
.crm-field__hint {
  margin: 0;
  font-size: 11px;
  color: #94a3b8;
}
.crm-field__error {
  margin: 0;
  font-size: 11px;
  font-weight: 600;
  color: #b91c1c;
}
</style>
