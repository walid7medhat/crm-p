<template>
  <div v-if="visible" class="edit-overlay add-employee-overlay" @click.self="handlers.closeAssetCreateModal">
    <div class="add-employee-modal asset-create-modal">
      <div class="add-employee-head"><h6>Create New Asset</h6><button type="button" class="add-employee-close" @click="handlers.closeAssetCreateModal"><iconify-icon icon="lucide:x" /></button></div>
      <div class="add-employee-body"><slot /></div>
      <div class="add-employee-footer"><button type="button" class="add-employee-clear-btn" @click="handlers.resetAssetCreateForm">Clear</button><button type="button" class="add-employee-save-btn" @click="handlers.saveAssetCreate">Save</button></div>
    </div>
  </div>
</template>
<script setup>
defineProps({ visible: Boolean, handlers: Object })
</script>
