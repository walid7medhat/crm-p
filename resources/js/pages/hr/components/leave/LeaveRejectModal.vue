<template>
  <div v-if="visible && row" class="edit-overlay" @click.self="$emit('close')">
    <div class="leave-confirm-modal">
      <button type="button" class="employee-filter-close" @click="$emit('close')"><iconify-icon icon="lucide:x" /></button>
      <div class="confirm-icon danger"><iconify-icon icon="lucide:ban" /></div>
      <h6>Leave Rejection Confirmation !</h6>
      <p>Please confirm that you want to reject this leave request.<br/>The employee will be notified after rejection.</p>
      <button type="button" class="leave-confirm-btn" @click="handlers.confirmLeaveRejection">Confirm</button>
    </div>
  </div>
</template>
<script setup>
defineProps({ visible: Boolean, row: Object, handlers: Object })
defineEmits(['close'])
</script>
