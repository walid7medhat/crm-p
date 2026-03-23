<template>
  <div class="lr-topbar">
    <div class="lr-tabs">
      <button type="button" :class="['lr-tab', { active: activeTab === 'leads' }]" @click="$emit('update:activeTab', 'leads')">
        Leads Reports
      </button>
      <button type="button" :class="['lr-tab', { active: activeTab === 'deals' }]" @click="$emit('update:activeTab', 'deals')">
        Deals Reports
      </button>
    </div>
    <div class="lr-actions">
      <button class="lr-action-btn">Export Excel</button>
      <button class="lr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
      <button class="lr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  activeTab: { type: String, default: 'leads' }
})
defineEmits(['update:activeTab'])
</script>

<style scoped>
.lr-topbar { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #ebeef3; }
.lr-tabs { display: flex; gap: 6px; }
.lr-tab { border: none; background: transparent; font-size: 13px; color: #8a8f9e; padding: 10px 12px; border-bottom: 2px solid transparent; }
.lr-tab.active { color: #10152f; border-bottom-color: #f8ae2f; font-weight: 600; }
.lr-actions { display: flex; gap: 8px; align-items: center; }
.lr-action-btn { height: 34px; border: 1px solid #ebeef3; background: #fff; border-radius: 20px; padding: 0 16px; font-size: 12px; color: #10152f; }
.lr-icon-btn { width: 34px; height: 34px; border: 1px solid #ebeef3; background: #fff; border-radius: 50%; color: #6f7282; }
</style>
