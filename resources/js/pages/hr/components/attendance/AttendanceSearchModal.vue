<template>
  <div v-if="visible" class="edit-overlay" @click.self="$emit('close')">
    <div class="employee-filter-modal leave-search-modal attendance-search-modal">
      <button type="button" class="employee-filter-close" @click="$emit('close')"><iconify-icon icon="lucide:x" /></button>
      <div class="asset-search-left">
        <button v-for="chip in options.attendanceSearchChips" :key="chip" type="button" class="asset-search-chip" :class="{ active: state.selectedAttendanceSearchChip === chip }" @click="state.selectedAttendanceSearchChip = chip">{{ chip }}</button>
      </div>
      <div class="asset-search-right">
        <div class="asset-search-section"><h6>Select Employee</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.attendanceSearchFilters.employee" :options="options.leaveEmployeeOptions" placeholder="Search Employee or id" /></div></div></div>
        <div class="asset-search-section"><h6>Attendance Date</h6><div class="add-grid-one"><div class="add-field"><input :value="helpers.formatDateDisplay(state.attendanceSearchFilters.attendanceDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('attendanceSearchFilters.attendanceDate')" /></div></div></div>
        <div class="asset-search-section"><h6>Type</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.attendanceSearchFilters.type" :options="options.attendanceTypeOptions" placeholder="Select Status" /></div></div></div>
        <div class="asset-search-section"><h6>Status</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.attendanceSearchFilters.status" :options="options.attendanceStatusOptions" placeholder="Select Status" /></div></div></div>
        <div class="employee-filter-actions mt-2"><button type="button" class="employee-filter-btn ghost" @click="handlers.resetAttendanceSearchFilters">Reset</button><button type="button" class="employee-filter-btn primary" @click="handlers.applyAttendanceSearchFilters">Search</button></div>
      </div>
    </div>
  </div>
</template>
<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object, helpers: Object })
defineEmits(['close'])
</script>
