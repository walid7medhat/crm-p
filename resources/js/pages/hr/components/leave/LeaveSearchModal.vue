<template>
  <div v-if="visible" class="edit-overlay" @click.self="$emit('close')">
    <div class="employee-filter-modal leave-search-modal">
      <button type="button" class="employee-filter-close" @click="$emit('close')"><iconify-icon icon="lucide:x" /></button>
      <div class="asset-search-left">
        <button v-for="chip in options.leaveSearchChips" :key="chip" type="button" class="asset-search-chip" :class="{ active: state.selectedLeaveSearchChip === chip }" @click="state.selectedLeaveSearchChip = chip">{{ chip }}</button>
      </div>
      <div class="asset-search-right">
        <div class="asset-search-section"><h6>Select Employee</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.leaveSearchFilters.employee" :options="options.leaveEmployeeOptions" placeholder="Search Employee or id" /></div></div></div>
        <div class="asset-search-section"><h6>Leave Type</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.leaveSearchFilters.leaveType" :options="options.leaveTypeFilterOptions" placeholder="Select Type" /></div></div></div>
        <div class="asset-search-section"><h6>Applied Date</h6><div class="add-grid-one"><div class="add-field"><input :value="helpers.formatDateDisplay(state.leaveSearchFilters.appliedDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('leaveSearchFilters.appliedDate')" /></div></div></div>
        <div class="asset-search-section"><h6>Status</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.leaveSearchFilters.status" :options="options.leaveStatusOptions" placeholder="Select Status" /></div></div></div>
        <div class="employee-filter-actions mt-2">
          <button type="button" class="employee-filter-btn ghost" @click="handlers.resetLeaveSearchFilters">Reset</button>
          <button type="button" class="employee-filter-btn primary" @click="handlers.applyLeaveSearchFilters">Search</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object, helpers: Object })
defineEmits(['close'])
</script>
