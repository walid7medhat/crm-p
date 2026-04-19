<template>
  <div class="tab-panel">
    <p class="tab-lead">Key CRM entities and how they relate (conceptual map).</p>

    <div class="diagram">
      <svg class="svg" viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs>
          <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
            <path d="M0,0 L8,4 L0,8 z" fill="#484f58" />
          </marker>
        </defs>
        <g v-for="(e, i) in svgEdges" :key="'e' + i">
          <line
            :x1="e.x1"
            :y1="e.y1"
            :x2="e.x2"
            :y2="e.y2"
            stroke="#484f58"
            stroke-width="1.5"
            marker-end="url(#arrow)"
          />
          <text :x="e.tx" :y="e.ty" fill="#8b949e" font-size="10" font-family="system-ui, sans-serif">
            {{ e.label }}
          </text>
        </g>
        <g v-for="n in layoutNodes" :key="n.id">
          <rect
            :x="n.x - n.w / 2"
            :y="n.y - n.h / 2"
            :width="n.w"
            :height="n.h"
            :rx="6"
            :fill="n.fill"
            stroke="#30363d"
            stroke-width="1"
          />
          <text
            :x="n.x"
            :y="n.y + 4"
            text-anchor="middle"
            fill="#e6edf3"
            font-size="11"
            font-family="system-ui, sans-serif"
            font-weight="500"
          >
            {{ n.label }}
          </text>
        </g>
      </svg>
    </div>

    <h3 class="sub">Entity notes</h3>
    <ul class="notes">
      <li v-for="(line, i) in filteredNotes" :key="i">{{ line }}</li>
    </ul>
  </div>
</template>

<script setup>
import { inject, computed } from 'vue'

const pm = inject('projectMap')
const data = computed(() => pm.data.value)
const q = computed(() => pm.query.value.trim().toLowerCase())

const g = computed(() => data.value?.dataFlowGraph || { nodes: [], edges: [] })

/** Fixed layout by entity id (conceptual diagram). */
const LAYOUT = {
  lead: { x: 400, y: 52, w: 230, h: 42, fill: '#1c2128' },
  requirement: { x: 155, y: 205, w: 250, h: 42, fill: '#1a2332' },
  meta: { x: 645, y: 205, w: 230, h: 42, fill: '#1a2332' },
  property: { x: 255, y: 355, w: 210, h: 40, fill: '#21262d' },
  user: { x: 545, y: 355, w: 200, h: 40, fill: '#21262d' },
}

const layoutNodes = computed(() => {
  const nodes = g.value.nodes || []
  return nodes.map((n) => {
    const box = LAYOUT[n.id] || { x: 120, y: 120, w: 160, h: 40, fill: '#21262d' }
    return {
      id: n.id,
      label: n.label,
      group: n.group,
      ...box,
    }
  })
})

const nodePos = computed(() => {
  const m = {}
  for (const n of layoutNodes.value) m[n.id] = n
  return m
})

const svgEdges = computed(() => {
  const edges = g.value.edges || []
  const np = nodePos.value
  const out = []
  for (const e of edges) {
    const a = np[e.from]
    const b = np[e.to]
    if (!a || !b) continue
    const x1 = a.x
    const y1 = a.y + a.h / 2
    const x2 = b.x
    const y2 = b.y - b.h / 2
    const mx = (x1 + x2) / 2
    const my = (y1 + y2) / 2
    out.push({
      x1,
      y1,
      x2,
      y2,
      tx: mx - Math.min(80, Math.abs(x2 - x1) / 4),
      ty: my - 8,
      label: e.label,
    })
  }
  return out
})

const filteredNotes = computed(() => {
  const list = data.value?.dataFlow || []
  if (!q.value) return list
  return list.filter((l) => String(l).toLowerCase().includes(q.value))
})
</script>

<style scoped>
.tab-lead {
  margin: 0 0 1rem;
  color: var(--dm-muted, #8b949e);
  font-size: 13px;
}
.diagram {
  background: var(--dm-panel2, #0d1117);
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 1rem;
}
.svg {
  width: 100%;
  height: auto;
  display: block;
}
.sub {
  margin: 0 0 0.5rem;
  font-size: 13px;
  color: var(--dm-accent, #58a6ff);
}
.notes {
  margin: 0;
  padding-left: 1.2rem;
  color: #c9d1d9;
  font-size: 13px;
}
</style>
