<template>
  <div v-if="visible" class="edit-overlay add-employee-overlay" @click.self="handlers.closeAnnouncementModal">
    <div class="add-employee-modal leave-apply-modal announcement-modal">
      <div class="add-employee-head"><h6>Add Announcements</h6><button type="button" class="add-employee-close" @click="handlers.closeAnnouncementModal"><iconify-icon icon="lucide:x" /></button></div>
      <div class="add-employee-body"><section class="add-employee-section"><div class="add-grid-two">
        <div class="add-field add-field-full"><label>Announcement Tittle *</label><SearchableSelect v-model="state.announcementForm.title" :options="options.announcementTitleOptions" placeholder="Enter Announcement Tittle" /></div>
        <div class="add-field"><label>Branch *</label><SearchableSelect v-model="state.announcementForm.branch" :options="options.announcementBranchOptions" placeholder="Not Selected" /></div>
        <div class="add-field"><label>Department *</label><SearchableSelect v-model="state.announcementForm.department" :options="options.announcementDepartmentOptions" placeholder="Not Selected" /></div>
        <div class="add-field"><label>Start Date</label><input :value="helpers.formatDateDisplay(state.announcementForm.startDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('announcementForm.startDate')" /></div>
        <div class="add-field"><label>End Date</label><input :value="helpers.formatDateDisplay(state.announcementForm.endDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('announcementForm.endDate')" /></div>
        <div class="add-field add-field-full"><label>Description</label><textarea v-model="state.announcementForm.description" placeholder="Enter text"></textarea></div>
      </div></section></div>
      <div class="add-employee-footer"><button type="button" class="add-employee-clear-btn" @click="handlers.clearAnnouncementForm">Clear</button><button type="button" class="add-employee-save-btn" @click="handlers.saveAnnouncement">Confirm</button></div>
    </div>
  </div>
</template>
<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object, helpers: Object })
</script>
