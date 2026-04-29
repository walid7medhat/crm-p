<template>
  <div v-if="visible" class="edit-overlay" @click.self="$emit('close')">
    <div class="employee-filter-modal leave-search-modal announcement-search-modal">
      <button type="button" class="employee-filter-close" @click="$emit('close')"><iconify-icon icon="lucide:x" /></button>
      <div class="asset-search-right w-100">
        <div class="asset-search-section"><h6>Announcement Title</h6><div class="add-grid-one"><div class="add-field"><input v-model="state.announcementSearchFilters.title" type="text" placeholder="Search title" /></div></div></div>
        <div class="asset-search-section"><h6>Branch</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.announcementSearchFilters.branch" :options="options.announcementBranchOptions" placeholder="Not Selected" /></div></div></div>
        <div class="asset-search-section"><h6>Department</h6><div class="add-grid-one"><div class="add-field"><SearchableSelect v-model="state.announcementSearchFilters.department" :options="options.announcementDepartmentOptions" placeholder="Not Selected" /></div></div></div>
        <div class="employee-filter-actions mt-2"><button type="button" class="employee-filter-btn ghost" @click="handlers.resetAnnouncementSearchFilters">Reset</button><button type="button" class="employee-filter-btn primary" @click="handlers.applyAnnouncementSearchFilters">Search</button></div>
      </div>
    </div>
  </div>
</template>
<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object })
defineEmits(['close'])
</script>
