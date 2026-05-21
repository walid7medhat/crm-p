<template>
  <div class="hr-attendance-search-dropdown">
    <button type="button" class="hr-attendance-search-dropdown__close" aria-label="Close" @click="$emit('close')">
      <iconify-icon icon="lucide:x" />
    </button>
    <div class="hr-attendance-search-dropdown__layout">
      <div class="hr-attendance-search-dropdown__chips">
        <button
          v-for="chip in chips"
          :key="chip"
          type="button"
          class="asset-search-chip"
          :class="{ active: selectedChip === chip }"
          @click="$emit('select-chip', chip)"
        >
          {{ chip }}
        </button>
        <button
          type="button"
          class="asset-search-chip"
          :class="{ active: !selectedChip }"
          @click="$emit('select-chip', '')"
        >
          All
        </button>
      </div>
      <div class="hr-attendance-search-dropdown__form">
        <div class="asset-search-section hr-attendance-search-field">
          <label class="hr-attendance-search-field__label">Employee</label>
          <SearchableSelect
            :model-value="filters.employee"
            :options="employeeOptions"
            placeholder="Search by name or employee ID"
            :append-to-body="false"
            @update:model-value="patch('employee', $event)"
          />
        </div>
        <div class="asset-search-section hr-attendance-search-field">
          <label class="hr-attendance-search-field__label">Department</label>
          <SearchableSelect
            :model-value="filters.department"
            :options="departmentOptions"
            placeholder="All departments"
            :append-to-body="false"
            @update:model-value="patch('department', $event)"
          />
        </div>
        <div class="asset-search-section hr-attendance-search-field">
          <label class="hr-attendance-search-field__label">Attendance Date</label>
          <input
            :value="dateDisplay"
            type="text"
            placeholder="dd/mm/yyyy"
            readonly
            @click="$emit('open-date-picker')"
          />
        </div>
        <div class="asset-search-section hr-attendance-search-field">
          <label class="hr-attendance-search-field__label">Type</label>
          <SearchableSelect
            :model-value="filters.type"
            :options="typeOptions"
            placeholder="All types"
            :append-to-body="false"
            @update:model-value="patch('type', $event)"
          />
        </div>
        <div class="asset-search-section hr-attendance-search-field">
          <label class="hr-attendance-search-field__label">Status</label>
          <SearchableSelect
            :model-value="filters.status"
            :options="statusOptions"
            placeholder="All statuses"
            :append-to-body="false"
            @update:model-value="patch('status', $event)"
          />
        </div>
        <div class="employee-filter-actions mt-2">
          <button type="button" class="employee-filter-btn ghost" @click="$emit('reset')">Reset</button>
          <button type="button" class="employee-filter-btn primary" @click="$emit('apply')">Search</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'

const props = defineProps({
  filters: { type: Object, required: true },
  chips: { type: Array, default: () => [] },
  selectedChip: { type: String, default: '' },
  employeeOptions: { type: Array, default: () => [] },
  departmentOptions: { type: Array, default: () => [] },
  typeOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  dateDisplay: { type: String, default: '' },
})

const emit = defineEmits(['close', 'reset', 'apply', 'select-chip', 'open-date-picker', 'update:filters'])

function patch(key, value) {
  emit('update:filters', { ...props.filters, [key]: value ?? '' })
}
</script>

<style scoped>
.hr-attendance-search-dropdown {
  position: relative;
  width: min(920px, calc(100vw - 32px));
  background: #fff;
  border: 1px solid #e5eaf3;
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
  padding: 14px 16px 16px;
  z-index: 1;
}
.hr-attendance-search-dropdown__close {
  position: absolute;
  top: 10px;
  right: 10px;
  border: none;
  background: transparent;
  color: #64748b;
  padding: 4px;
  line-height: 1;
  z-index: 2;
}
.hr-attendance-search-dropdown__layout {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.hr-attendance-search-dropdown__chips {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 4px;
}
.hr-attendance-search-dropdown__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 14px;
}
.hr-attendance-search-field__label {
  display: block;
  margin: 0 0 4px;
  font-size: 10px !important;
  font-weight: 500 !important;
  line-height: 1.2;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: #6b7280 !important;
}
.hr-attendance-search-field {
  padding: 8px 10px;
}
.asset-search-section input {
  width: 100%;
  min-height: 36px;
  border: 1px solid #d8dde8;
  border-radius: 10px;
  padding: 0 10px;
  font-size: 12px;
}
.asset-search-section :deep(.vs__dropdown-toggle) {
  min-height: 36px;
  border-radius: 10px;
}
.employee-filter-actions {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
@media (max-width: 768px) {
  .hr-attendance-search-dropdown__layout {
    grid-template-columns: 1fr;
  }
  .hr-attendance-search-dropdown__chips {
    flex-direction: row;
    flex-wrap: wrap;
  }
  .hr-attendance-search-dropdown__form {
    grid-template-columns: 1fr;
  }
}
</style>
