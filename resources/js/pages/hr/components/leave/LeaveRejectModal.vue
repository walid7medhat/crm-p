<template>
  <div v-if="visible && row" class="edit-overlay" @click.self="$emit('close')">
    <div class="la-reject-modal">
      <button type="button" class="la-reject-modal__close" aria-label="Close" @click="$emit('close')">
        <iconify-icon icon="lucide:x" />
      </button>
      <div class="la-reject-modal__icon">
        <iconify-icon icon="lucide:ban" />
      </div>
      <h6>Reject leave request?</h6>
      <p>Please confirm that you want to reject this leave request. The employee will be notified.</p>
      <button type="button" class="la-reject-modal__confirm" style="width:100%;" @click="handlers.confirmLeaveRejection">Reject</button>
      <button type="button" class="la-reject-modal__cancel" style="width:100%;margin-top:10px;" @click="$emit('close')">Cancel</button>
    </div>
  </div>
</template>
<script setup>
defineProps({ visible: Boolean, row: Object, handlers: Object })
defineEmits(['close'])
</script>
