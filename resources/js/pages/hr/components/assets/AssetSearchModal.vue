<template>
  <div v-if="visible" class="edit-overlay" @click.self="$emit('close')">
    <div class="employee-filter-modal asset-search-modal">
      <button type="button" class="employee-filter-close" @click="$emit('close')"><iconify-icon icon="lucide:x" /></button>
      <div class="asset-search-left"><button v-for="chip in options.assetSearchChips" :key="chip" type="button" class="asset-search-chip" :class="{ active: state.selectedAssetSearchChip === chip }" @click="state.selectedAssetSearchChip = chip">{{ chip }}</button></div>
      <div class="asset-search-right">
        <div class="asset-search-section"><h6>Asset Details</h6><div class="add-grid-two">
          <div class="add-field"><label>Asset Type</label><SearchableSelect v-model="state.assetSearchFilters.assetType" :options="options.assetTypeOptions" placeholder="Select Asset Type" /></div>
          <div class="add-field"><label>Asset Name</label><input v-model="state.assetSearchFilters.assetName" type="text" placeholder="Search Asset Name" /></div>
          <div class="add-field"><label>Created On</label><input :value="helpers.formatDateDisplay(state.assetSearchFilters.createdOn)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('assetSearchFilters.createdOn')" /></div>
          <div class="add-field"><label>Serial Number</label><input v-model="state.assetSearchFilters.serialNumber" type="text" placeholder="Enter Number" /></div>
        </div></div>
        <div class="asset-search-section"><h6>User Details</h6><div class="add-grid-two">
          <div class="add-field"><label>Asset User</label><SearchableSelect v-model="state.assetSearchFilters.assetUser" :options="options.assetUserOptions" placeholder="Select Person" /></div>
          <div class="add-field"><label>Department</label><SearchableSelect v-model="state.assetSearchFilters.department" :options="options.departmentOptions" placeholder="Not Selected" /></div>
          <div class="add-field"><label>Branch Location</label><SearchableSelect v-model="state.assetSearchFilters.branchLocation" :options="options.branchOptions" placeholder="Not Selected" /></div>
          <div class="add-field"><label>Status</label><SearchableSelect v-model="state.assetSearchFilters.status" :options="options.assetStatusOptions" placeholder="Not Selected" /></div>
        </div></div>
        <div class="asset-search-section"><h6>Purchase Details</h6><div class="add-grid-two">
          <div class="add-field"><label>Purchase Date</label><input :value="helpers.formatDateDisplay(state.assetSearchFilters.purchaseDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('assetSearchFilters.purchaseDate')" /></div>
          <div class="add-field"><label>Supplier Name</label><input v-model="state.assetSearchFilters.supplierName" type="text" placeholder="Enter Supplier Name" /></div>
          <div class="add-field"><label>Condition</label><SearchableSelect v-model="state.assetSearchFilters.condition" :options="options.assetConditionOptions" placeholder="Not Selected" /></div>
        </div></div>
        <div class="employee-filter-actions mt-2"><button type="button" class="employee-filter-btn ghost" @click="handlers.resetAssetSearchFilters">Reset</button><button type="button" class="employee-filter-btn primary" @click="handlers.applyAssetSearchFilters">Search</button></div>
      </div>
    </div>
  </div>
</template>
<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object, helpers: Object })
defineEmits(['close'])
</script>
