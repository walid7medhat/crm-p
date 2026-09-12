<template>
  <div class="ali-lead-actions">
    <button type="button" class="ali-btn ali-btn--primary" :disabled="!leadId" @click="$emit('open-lead', leadId)">
      <Icon icon="lucide:external-link" />
      Open Lead
    </button>
    <button type="button" class="ali-btn ali-btn--ghost" :disabled="!leadId" @click="$emit('view-matches', leadId)">
      <Icon icon="lucide:home" />
      View Matches
    </button>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
defineProps({ leadId: { type: [Number, String], default: null } })
defineEmits(['open-lead', 'view-matches'])
</script>
