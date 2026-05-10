<template>
  <div class="so-table-wrap">
    <table class="so-table">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, ri) in displayRows" :key="ri">
          <td v-for="col in columns" :key="col.key">
            <code v-if="col.mono">{{ row[col.key] }}</code>
            <span v-else>{{ row[col.key] }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, required: true },
})

const displayRows = computed(() => props.rows || [])
</script>

<style scoped>
.so-table-wrap {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid var(--so-border, rgba(15, 23, 42, 0.08));
}
.so-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}
.so-table th,
.so-table td {
  padding: 6px 10px;
  text-align: left;
  border-bottom: 1px solid var(--so-border, rgba(15, 23, 42, 0.06));
}
.so-table th {
  font-weight: 600;
  color: var(--so-muted, #64748b);
  background: var(--so-th, rgba(248, 250, 252, 0.9));
  white-space: nowrap;
}
.so-table tbody tr:hover {
  background: rgba(99, 102, 241, 0.04);
}
.so-table code {
  font-size: 10px;
  background: rgba(15, 23, 42, 0.06);
  padding: 2px 6px;
  border-radius: 6px;
}
</style>
