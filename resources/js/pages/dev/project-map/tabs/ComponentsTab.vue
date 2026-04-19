<template>
  <div class="tab-panel">
    <p class="tab-lead">
      Import graph between <code>.vue</code> files (static analysis). Hub file + sample importers.
    </p>

    <div class="grid">
      <section class="card">
        <h6 class="ui-h-sub card-title">Hub</h6>
        <p class="mono">{{ graph?.hub }}</p>
        <h6 class="ui-h-mini">Imports from hub</h6>
        <ul class="list">
          <li v-for="(imp, i) in filteredImports" :key="i">
            <code>{{ imp }}</code>
          </li>
        </ul>
      </section>

      <section class="card">
        <h6 class="ui-h-sub card-title">Importers (string match)</h6>
        <ul class="list compact">
          <li v-for="(f, i) in filteredImporters" :key="i">
            <code>{{ f }}</code>
          </li>
        </ul>
      </section>

      <section class="card wide">
        <h6 class="ui-h-sub card-title">
          Vue import edges
          <span class="badge">{{ filteredEdges.length }} / {{ edges.length }}</span>
        </h6>
        <div class="edges">
          <div v-for="(e, i) in filteredEdges" :key="i" class="edge">
            <code class="from">{{ e.from }}</code>
            <span class="ar">→</span>
            <code class="to">{{ e.to }}</code>
            <span class="k">{{ e.kind }}</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { inject, computed } from 'vue'

const pm = inject('projectMap')
const data = computed(() => pm.data.value)
const q = computed(() => pm.query.value.trim().toLowerCase())

const graph = computed(() => data.value?.componentGraph)

const edges = computed(() => graph.value?.vueImportGraph?.edges || [])

function match(s) {
  if (!q.value) return true
  return String(s || '').toLowerCase().includes(q.value)
}

const filteredEdges = computed(() =>
  edges.value.filter((e) => match(e.from) || match(e.to)),
)

const filteredImports = computed(() => {
  const list = graph.value?.importsFromHub || []
  if (!q.value) return list
  return list.filter(match)
})

const filteredImporters = computed(() => {
  const list = graph.value?.sampleImporters || []
  if (!q.value) return list
  return list.filter(match)
})
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
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.wide {
  grid-column: 1 / -1;
}
@media (max-width: 900px) {
  .grid {
    grid-template-columns: 1fr;
  }
  .wide {
    grid-column: auto;
  }
}
.card {
  background: var(--dm-panel2, #0d1117);
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  padding: 0.75rem 1rem;
}
.card-title {
  margin: 0 0 0.5rem;
  font-size: 12px;
  font-weight: 600;
  color: var(--dm-muted, #8b949e);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.badge {
  font-size: 11px;
  font-weight: 500;
  color: var(--dm-accent, #58a6ff);
  text-transform: none;
}
.mono {
  font-family: ui-monospace, monospace;
  font-size: 12px;
  color: #a5d6ff;
  margin: 0 0 0.75rem;
  word-break: break-all;
}
h4 {
  margin: 0.75rem 0 0.35rem;
  font-size: 12px;
  color: #c9d1d9;
}
.list {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 12px;
  color: #c9d1d9;
}
.list.compact {
  font-size: 11px;
  max-height: 240px;
  overflow: auto;
}
.list code {
  color: #79c0ff;
}
.edges {
  max-height: min(50vh, 520px);
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.edge {
  display: grid;
  grid-template-columns: 1fr auto 1fr auto;
  gap: 0.35rem;
  align-items: start;
  font-size: 11px;
  padding: 0.35rem 0.5rem;
  background: rgba(1, 4, 9, 0.55);
  border-radius: 4px;
  border: 1px solid #21262d;
}
.from {
  color: #ffa657;
  word-break: break-all;
}
.to {
  color: #7ee787;
  word-break: break-all;
}
.ar {
  color: #6e7681;
  padding: 0 0.15rem;
}
.k {
  color: #8b949e;
  font-size: 10px;
}
</style>
