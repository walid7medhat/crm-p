<template>
  <div class="tab-panel">
    <p class="tab-lead">HTTP calls detected from <code>axios.*(</code> patterns, grouped by module folder.</p>

    <div v-for="(rows, mod) in filteredGroups" :key="mod" class="module-block">
      <button type="button" class="module-h" @click="toggle(mod)">
        <span class="chev">{{ expanded[mod] === false ? '▶' : '▼' }}</span>
        <span class="mod-name">{{ mod }}</span>
        <span class="cnt">{{ rows.length }}</span>
      </button>
      <div v-show="expanded[mod] !== false" class="module-body">
        <div v-for="(row, i) in rows" :key="mod + i" class="api-line">
          <span class="meth">{{ row.method }}</span>
          <code class="url">{{ row.url }}</code>
          <span class="loc">{{ row.file }}:{{ row.line }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { inject, computed, reactive, watch } from 'vue'

const pm = inject('projectMap')
const data = computed(() => pm.data.value)
const q = computed(() => pm.query.value.trim().toLowerCase())

const expanded = reactive({})

const groups = computed(() => data.value?.apiByModule || {})

function match(row) {
  if (!q.value) return true
  return (
    String(row.url || '')
      .toLowerCase()
      .includes(q.value) ||
    String(row.file || '')
      .toLowerCase()
      .includes(q.value) ||
    String(row.method || '')
      .toLowerCase()
      .includes(q.value)
  )
}

const filteredGroups = computed(() => {
  const g = groups.value
  const out = {}
  for (const mod of Object.keys(g).sort()) {
    const rows = (g[mod] || []).filter(match)
    if (rows.length) out[mod] = rows
  }
  return out
})

watch(
  () => filteredGroups.value,
  (g) => {
    for (const mod of Object.keys(g)) {
      if (expanded[mod] === undefined) expanded[mod] = true
    }
  },
  { immediate: true },
)

function toggle(mod) {
  expanded[mod] = expanded[mod] === false
}
</script>

<style scoped>
.tab-lead {
  margin: 0 0 1rem;
  color: var(--dm-muted, #8b949e);
  font-size: 13px;
}
.tab-lead code {
  color: var(--dm-accent2, #79c0ff);
}
.module-block {
  margin-bottom: 0.5rem;
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  overflow: hidden;
  background: var(--dm-panel2, #0d1117);
}
.module-h {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  text-align: left;
  padding: 0.55rem 0.75rem;
  background: rgba(22, 27, 34, 0.9);
  border: none;
  color: #e6edf3;
  cursor: pointer;
  font: inherit;
}
.module-h:hover {
  background: rgba(56, 139, 253, 0.06);
}
.chev {
  color: #8b949e;
  font-size: 10px;
  width: 1rem;
}
.mod-name {
  font-weight: 600;
  font-size: 13px;
  color: #58a6ff;
}
.cnt {
  margin-left: auto;
  font-size: 11px;
  color: #8b949e;
  background: #21262d;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
}
.module-body {
  padding: 0.35rem 0.5rem 0.65rem;
  max-height: 280px;
  overflow: auto;
}
.api-line {
  display: grid;
  grid-template-columns: 56px 1fr auto;
  gap: 0.5rem;
  align-items: start;
  font-size: 11px;
  padding: 0.35rem 0.45rem;
  border-radius: 4px;
  margin-bottom: 0.25rem;
  background: rgba(1, 4, 9, 0.45);
}
.meth {
  color: #ff7b72;
  font-weight: 600;
}
.url {
  color: #79c0ff;
  word-break: break-all;
}
.loc {
  color: #8b949e;
  white-space: nowrap;
  font-size: 10px;
}
</style>
