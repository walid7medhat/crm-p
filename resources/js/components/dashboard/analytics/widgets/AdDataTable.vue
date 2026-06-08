<template>
  <div class="ex-table-wrap">
    <table class="ex-table">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, idx) in rows" :key="row.id ?? idx">
          <td v-for="col in columns" :key="col.key">{{ formatCell(row[col.key], col) }}</td>
        </tr>
        <tr v-if="!rows?.length">
          <td :colspan="columns.length" class="ex-table__empty">No records</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
})

function formatCell(value, col) {
  if (value == null) return '—'
  if (col.format === 'currency') {
    return new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(value)
  }
  if (col.format === 'percent') return `${value}%`
  return value
}
</script>
