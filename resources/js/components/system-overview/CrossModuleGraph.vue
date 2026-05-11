<template>
  <section class="cmg">
    <header class="cmg-head">
      <div class="cmg-title" role="heading" aria-level="2">{{ title }}</div>
      <div class="cmg-sub">{{ subtitle }}</div>
    </header>
    <div class="cmg-canvas">
      <div class="cmg-hub">
        <span class="cmg-hub-label">{{ hubLabel }}</span>
        <iconify-icon icon="lucide:cpu" class="cmg-hub-icon" />
      </div>
      <div class="cmg-nodes">
        <div
          v-for="s in nodeStages"
          :key="s.id"
          class="cmg-node"
          :class="'cmg-tone-' + s.tone"
        >
          <iconify-icon :icon="s.icon" />
          <span>{{ s.label }}</span>
        </div>
      </div>
      <ul class="cmg-edges" :aria-label="connectionsAria">
        <li v-for="(e, i) in edgeRows" :key="i" class="cmg-edge">
          <span class="cmg-edge-from">{{ e.fromLabel }}</span>
          <span class="cmg-edge-line" />
          <span class="cmg-edge-to">{{ e.toLabel }}</span>
          <span class="cmg-edge-cap">{{ e.cap }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { pipelineStages, crossModuleEdges } from '@/data/systemOverviewPresentation.js'

const props = defineProps({
  title: { type: String, default: 'Cross-module relationships' },
  subtitle: {
    type: String,
    default: 'How data and automation flow across the platform',
  },
  hubLabel: { type: String, default: 'Intelligence' },
  connectionsAria: { type: String, default: 'Connections' },
  /** Full edge rows { fromLabel, toLabel, cap } — overrides structural edges when set */
  edgesDisplay: { type: Array, default: null },
  /** Pipeline chips (label translated) */
  nodesOverride: { type: Array, default: null },
})

const nodeStages = computed(() => props.nodesOverride ?? pipelineStages)

const pipelineLocal = pipelineStages
const structuralEdges = crossModuleEdges

function labelFor(id) {
  if (id === 'intelligence') return 'Intelligence'
  const s = pipelineLocal.find((x) => x.id === id)
  return s ? s.label : id
}

const edgeRows = computed(() => {
  if (props.edgesDisplay?.length) return props.edgesDisplay
  return structuralEdges.map((e) => ({
    fromLabel: labelFor(e.from),
    toLabel: labelFor(e.to),
    cap: e.label,
  }))
})
</script>

<style scoped>
.cmg {
  padding: 12px 14px;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.03) 0%, rgba(99, 102, 241, 0.04) 100%);
  border: 1px solid rgba(15, 23, 42, 0.08);
  margin-bottom: 16px;
}
.cmg-head {
  margin-bottom: 10px;
}
.cmg-title {
  margin: 0 0 3px;
  font-size: 13px;
  font-weight: 800;
  color: #0f172a;
}
.cmg-sub {
  margin: 0;
  font-size: 11px;
  color: #64748b;
  line-height: 1.4;
}
.cmg-canvas {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.cmg-hub {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  align-self: flex-start;
  padding: 10px 16px;
  border-radius: 12px;
  background: linear-gradient(135deg, #312e81, #4f46e5);
  color: #fff;
  box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
}
.cmg-hub-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.9;
}
.cmg-hub-icon {
  font-size: 15px;
}
.cmg-nodes {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.cmg-node {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  color: #0f172a;
  border: 1px solid rgba(15, 23, 42, 0.1);
  background: #fff;
}
.cmg-tone-amber {
  border-color: rgba(245, 158, 11, 0.35);
  background: rgba(254, 243, 199, 0.5);
}
.cmg-tone-violet {
  border-color: rgba(139, 92, 246, 0.35);
  background: rgba(237, 233, 254, 0.6);
}
.cmg-tone-emerald {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(209, 250, 229, 0.5);
}
.cmg-edges {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.cmg-edge {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 8px;
  font-size: 11px;
  padding: 6px 10px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(15, 23, 42, 0.06);
}
.cmg-edge-from,
.cmg-edge-to {
  font-weight: 600;
  color: #334155;
}
.cmg-edge-line {
  height: 2px;
  background: linear-gradient(90deg, #c7d2fe, #a5b4fc);
  border-radius: 2px;
  min-width: 24px;
}
.cmg-edge-cap {
  grid-column: 1 / -1;
  font-size: 11px;
  color: #6366f1;
  font-weight: 600;
}
@media (min-width: 640px) {
  .cmg-edge {
    grid-template-columns: auto 1fr auto;
  }
  .cmg-edge-cap {
    grid-column: auto;
    font-size: 11px;
  }
}
</style>
