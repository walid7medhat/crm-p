<template>
  <div v-if="visible" class="edit-overlay add-employee-overlay" @click.self="handlers.closeApplyLeaveModal">
    <div class="add-employee-modal leave-apply-modal">
      <div class="add-employee-head"><h6>Apply Leave</h6><button type="button" class="add-employee-close" @click="handlers.closeApplyLeaveModal"><iconify-icon icon="lucide:x" /></button></div>
      <div class="add-employee-body">
        <section class="add-employee-section"><div class="add-grid-two">
          <div class="add-field add-field-full"><label>Employee *</label><SearchableSelect v-model="state.applyLeaveForm.employee" :options="options.applyLeaveEmployeeOptions" placeholder="Search Employee or ID" /></div>
          <div class="add-field add-field-full"><label>Leave Type *</label><SearchableSelect v-model="state.applyLeaveForm.leaveType" :options="options.leaveTypeOptions" placeholder="Select Type" /></div>
          <div class="add-field"><label>Start Date</label><input :value="helpers.formatDateDisplay(state.applyLeaveForm.startDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('applyLeaveForm.startDate')" /></div>
          <div class="add-field"><label>End Date</label><input :value="helpers.formatDateDisplay(state.applyLeaveForm.endDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('applyLeaveForm.endDate')" /></div>
          <div class="add-field add-field-full"><label>Leave Reason</label><textarea v-model="state.applyLeaveForm.reason" placeholder="Enter Reason"></textarea></div>
        </div></section>
        <section class="add-employee-section">
          <h6>Attachments</h6><div class="upload-dropzone leave-upload-dropzone"><div><strong>Upload documents</strong><small>JPEG, PNG and PDF formats, up to 50MB</small></div><label class="select-file-btn">Select File<input type="file" class="d-none" @change="handlers.handleApplyLeaveFileChange" /></label></div>
          <div v-if="state.applyLeaveAttachment" class="uploaded-doc-card"><iconify-icon icon="lucide:file-text" /><div><p>{{ state.applyLeaveAttachment.name }}</p><small>{{ `${Math.max(1, Math.round(state.applyLeaveAttachment.size / 1024))}KB` }}</small></div><button type="button" @click="handlers.removeApplyLeaveFile"><iconify-icon icon="lucide:x-circle" /></button></div>
        </section>
      </div>
      <div class="add-employee-footer"><button type="button" class="add-employee-clear-btn" @click="handlers.cancelApplyLeave">Cancel</button><button type="button" class="add-employee-save-btn" @click="handlers.submitApplyLeave">Apply</button></div>
    </div>
  </div>
</template>
<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object, helpers: Object })
</script>
