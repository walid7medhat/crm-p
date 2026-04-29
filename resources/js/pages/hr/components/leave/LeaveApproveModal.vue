<template>
  <div v-if="visible && row" class="edit-overlay" @click.self="$emit('close')">
    <div class="leave-confirm-modal">
      <button type="button" class="employee-filter-close" @click="$emit('close')"><iconify-icon icon="lucide:x" /></button>
      <div class="confirm-icon success"><iconify-icon icon="lucide:check" /></div>
      <h6>Leave Approval Confirmation !</h6>
      <p>Are you sure you want to approve this leave request?<br/>This action will update the leave status as approved.</p>
      <button type="button" class="leave-confirm-btn" @click="handlers.confirmLeaveApproval">Confirm</button>
    </div>
  </div>
</template>
<script setup>
defineProps({ visible: Boolean, row: Object, handlers: Object })
defineEmits(['close'])
</script>
