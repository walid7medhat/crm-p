<template>
  <section class="lr-filters">
    <div class="lr-filter-title">Search Filter</div>
    <div class="lr-grid">
      <div class="lr-field">
        <label>Search</label>
        <div class="lr-input-wrap">
          <input :value="searchQuery" type="text" placeholder="Enter lead name etc..." @input="$emit('update:searchQuery', $event.target.value)" />
          <iconify-icon icon="lucide:search" />
        </div>
      </div>
      <div class="lr-field">
        <label>Select Branch</label>
        <select :value="branch" @change="$emit('update:branch', $event.target.value)">
          <option v-for="item in branchOptions" :key="item" :value="item">{{ item }}</option>
        </select>
      </div>
      <div class="lr-field">
        <label>Date</label>
        <select :value="dateRange" @change="$emit('update:dateRange', $event.target.value)">
          <option>Today</option>
          <option>Yesterday</option>
          <option>This Week</option>
          <option>Last Week</option>
          <option>This Month</option>
          <option>Last Month</option>
          <option>This Year</option>
          <option>Last Year</option>
        </select>
      </div>
      <div class="lr-field">
        <label>Advanced Filter</label>
        <div class="lr-input-wrap clickable" @click="$emit('openAdvanced')">
          <input :value="stage" type="text" placeholder="Search lead Stage, etc..." readonly />
          <iconify-icon icon="lucide:chevron-down" />
        </div>
      </div>
      <button class="lr-search-btn" @click="$emit('submit')">Search</button>
    </div>
    <div class="lr-reset-row">
      <button class="lr-link" @click="$emit('clear')">Clear</button>
      <button class="lr-link danger" @click="$emit('reset')">Reset Filter</button>
    </div>
  </section>
</template>

<script setup>
defineProps({
  searchQuery: { type: String, default: '' },
  branch: { type: String, default: 'All Team' },
  stage: { type: String, default: '' },
  dateRange: { type: String, default: 'Last Month' },
  branchOptions: { type: Array, default: () => [] }
})
defineEmits(['update:searchQuery', 'update:branch', 'update:dateRange', 'openAdvanced', 'submit', 'clear', 'reset'])
</script>

<style scoped>
.lr-filters { border: 1px solid #ebeef3; border-radius: 14px; background: #fff; margin: 14px 16px; padding: 14px; }
.lr-filter-title { font-size: 18px; font-weight: 600; color: #10152f; margin-bottom: 10px; }
.lr-grid { display: grid; gap: 12px; grid-template-columns: 1.3fr 1.1fr 1fr 1.5fr auto; align-items: end; }
.lr-field label { display: block; font-size: 12px; margin-bottom: 6px; color: #10152f; font-weight: 600; }
.lr-input-wrap, select { height: 42px; border: 1px solid #ebeef3; border-radius: 10px; background: #fff; display: flex; align-items: center; padding: 0 12px; color: #6f7282; }
.lr-input-wrap input { border: none; width: 100%; outline: none; font-size: 13px; }
.lr-input-wrap iconify-icon { color: #9aa0ad; font-size: 14px; }
select { width: 100%; font-size: 13px; outline: none; }
.clickable { cursor: pointer; }
.lr-search-btn { height: 42px; border: none; border-radius: 22px; background: #020b38; color: #fff; padding: 0 22px; font-size: 13px; }
.lr-reset-row { text-align: right; margin-top: 8px; display: flex; justify-content: flex-end; gap: 12px; }
.lr-link { border: none; background: transparent; color: #9ca2ae; font-size: 12px; }
.lr-link.danger { color: #df525c; }
@media (max-width: 1200px) { .lr-grid { grid-template-columns: 1fr 1fr; } }
</style>
