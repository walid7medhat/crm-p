<template>
  <div class="graph-tab">
    <p class="graph-lead">
      <strong>Route flow</strong>: registered paths and <strong>redirect</strong> chains (e.g. alias → canonical URL). This is
      navigation structure, not runtime user journeys. Use the search bar to filter nodes.
    </p>

    <div class="flow-host">
      <VueFlow
        :nodes="nodes"
        :edges="edges"
        :node-types="nodeTypes"
        :fit-view-on-init="true"
        :min-zoom="0.12"
        :max-zoom="1.4"
        :nodes-draggable="false"
        :nodes-connectable="false"
        class="vf-root"
        @node-click="onNodeClick"
      >
        <Background :gap="16" pattern-color="#30363d" />
        <Controls />
        <MiniMap pannable zoomable />
      </VueFlow>
    </div>

    <div v-if="selected" class="node-detail">
      <button type="button" class="close" @click="selected = null">×</button>
      <h4>{{ selected.data?.label }}</h4>
      <p class="row"><strong>Path</strong><br /><code>{{ selected.data?.detail }}</code></p>
      <p v-if="selected.data?.meta" class="row"><strong>Component / redirect</strong><br />{{ selected.data.meta }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, markRaw, inject, computed } from 'vue'
import { VueFlow } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import { MiniMap } from '@vue-flow/minimap'
import RoadmapNode from '../nodes/RoadmapNode.vue'

import '@vue-flow/core/dist/style.css'
import '@vue-flow/controls/dist/style.css'
import '@vue-flow/minimap/dist/style.css'

const pm = inject('projectMap')
const nodes = ref([])
const edges = ref([])
const selected = ref(null)
const nodeTypes = { roadmap: markRaw(RoadmapNode) }

const q = computed(() => pm.query.value.trim().toLowerCase())

function applyRouteFlow() {
  const g = pm.data.value?.routeFlowGraph
  selected.value = null
  if (!g?.nodes?.length) {
    nodes.value = []
    edges.value = []
    return
  }
  let nlist = g.nodes.map((n) => ({ ...n, type: n.type || 'roadmap' }))
  const qq = q.value
  if (qq) {
    nlist = nlist.filter(
      (n) =>
        String(n.data?.label || '')
          .toLowerCase()
          .includes(qq) ||
        String(n.data?.detail || '')
          .toLowerCase()
          .includes(qq) ||
        String(n.data?.meta || '')
          .toLowerCase()
          .includes(qq),
    )
  }
  const keep = new Set(nlist.map((n) => n.id))
  nodes.value = nlist
  edges.value = (g.edges || []).filter((e) => keep.has(e.source) && keep.has(e.target))
}

watch([() => pm.data.value?.routeFlowGraph, q], applyRouteFlow, { immediate: true })

function onNodeClick({ node }) {
  selected.value = node
}
</script>

<style scoped>
.graph-tab {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 560px;
}
.graph-lead {
  margin: 0;
  font-size: 13px;
  color: var(--dm-muted, #8b949e);
  max-width: 900px;
  line-height: 1.45;
}
.flow-host {
  flex: 1;
  min-height: 520px;
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  overflow: hidden;
  background: #0d1117;
}
.vf-root {
  width: 100%;
  height: 520px;
}
.node-detail {
  position: relative;
  padding: 0.75rem 1rem;
  background: #161b22;
  border: 1px solid #30363d;
  border-radius: 8px;
  font-size: 12px;
  max-width: 560px;
}
.node-detail h4 {
  margin: 0 0 0.35rem;
  font-size: 14px;
  color: #f0883e;
}
.row {
  margin: 0.35rem 0 0;
  color: #c9d1d9;
}
.row code {
  font-size: 11px;
  color: #79c0ff;
}
.close {
  position: absolute;
  top: 0.35rem;
  right: 0.5rem;
  border: none;
  background: transparent;
  color: #8b949e;
  cursor: pointer;
  font-size: 1.1rem;
}
</style>
