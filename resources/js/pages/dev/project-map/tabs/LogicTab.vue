<template>
  <div class="tab-panel">
    <p class="tab-lead">Curated pointers into Kanban / lead requirement logic (search filters blocks below).</p>

    <div v-for="(lines, key) in filteredBlocks" :key="key" class="logic-card">
      <h6 class="ui-h-sub logic-title">{{ formatTitle(key) }}</h6>
      <ul>
        <li v-for="(line, i) in lines" :key="key + '-' + i">{{ line }}</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { inject, computed } from 'vue'

const pm = inject('projectMap')
const data = computed(() => pm.data.value)
const q = computed(() => pm.query.value.trim().toLowerCase())

const blocks = computed(() => data.value?.businessLogic || {})

function formatTitle(key) {
  return String(key).replace(/([A-Z])/g, ' $1').replace(/^./, (s) => s.toUpperCase())
}

const filteredBlocks = computed(() => {
  const b = blocks.value
  const out = {}
  for (const key of Object.keys(b)) {
    const lines = (b[key] || []).filter((line) => {
      if (!q.value) return true
      return String(line).toLowerCase().includes(q.value)
    })
    if (lines.length) out[key] = lines
  }
  return out
})
</script>

<style scoped>
.tab-lead {
  margin: 0 0 1rem;
  color: var(--dm-muted, #8b949e);
  font-size: 13px;
}
.logic-card {
  background: var(--dm-panel2, #0d1117);
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  padding: 0.85rem 1rem;
  margin-bottom: 0.75rem;
}
.logic-title {
  margin: 0 0 0.5rem;
  font-size: 14px;
  font-weight: 600;
  color: var(--dm-accent, #58a6ff);
}
ul {
  margin: 0;
  padding-left: 1.2rem;
  color: #c9d1d9;
  font-size: 13px;
  line-height: 1.55;
}
</style>
