<template>
  <div v-if="visible" class="edit-overlay" @click.self="$emit('close')">
    <div class="employee-filter-modal career-search-modal">
      <button type="button" class="employee-filter-close" @click="$emit('close')">
        <iconify-icon icon="lucide:x" />
      </button>
      <div class="asset-search-left">
        <button
          v-for="chip in options.careerFilterChips"
          :key="chip"
          type="button"
          class="asset-search-chip"
          :class="{ active: state.selectedCareerFilterChip === chip }"
          @click="state.selectedCareerFilterChip = chip"
        >
          {{ chip }}
        </button>
      </div>
      <div class="asset-search-right">
        <div class="asset-search-section">
          <h6>Search Job Tittle</h6>
          <div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.careerSearchFilters.jobTitle" placeholder="Search Job Tittle" :options="options.careerJobTitleOptions" /></div></div>
        </div>
        <div class="asset-search-section">
          <h6>Posted Date</h6>
          <div class="add-grid-one"><div class="add-field"><input :value="helpers.formatDateDisplay(state.careerSearchFilters.postedDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('careerSearchFilters.postedDate')" /></div></div>
        </div>
        <div class="asset-search-section">
          <h6>Closing Date</h6>
          <div class="add-grid-one"><div class="add-field"><input :value="helpers.formatDateDisplay(state.careerSearchFilters.closingDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('careerSearchFilters.closingDate')" /></div></div>
        </div>
        <div class="asset-search-section">
          <h6>Department</h6>
          <div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.careerSearchFilters.department" placeholder="Select Department" :options="options.careerDepartmentOptions" /></div></div>
        </div>
        <div class="asset-search-section">
          <h6>Type</h6>
          <div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.careerSearchFilters.type" placeholder="Select Type" :options="options.careerTypeOptions" /></div></div>
        </div>
        <div class="asset-search-section">
          <h6>Status</h6>
          <div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.careerSearchFilters.status" placeholder="Select Status" :options="options.careerStatusOptions" /></div></div>
        </div>
        <div class="employee-filter-actions mt-2">
          <button type="button" class="employee-filter-btn ghost" @click="handlers.resetCareerSearchFilters">Reset</button>
          <button type="button" class="employee-filter-btn primary" @click="handlers.applyCareerSearchFilters">Search</button>
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

<style scoped>
.career-search-modal :deep(.vs__dropdown-toggle) {
  position: relative;
  display: flex;
  align-items: center;
  padding-right: 34px;
}

.career-search-modal :deep(.vs__actions) {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%) !important;
  height: 100% !important;
  min-height: 36px;
  display: inline-flex;
  align-items: center !important;
  justify-content: center;
  padding-right: 0;
  margin: 0;
}

.career-search-modal :deep(.vs__open-indicator) {
  margin: 0 !important;
  transform: none !important;
  align-self: center;
  display: inline-flex;
}
</style>
