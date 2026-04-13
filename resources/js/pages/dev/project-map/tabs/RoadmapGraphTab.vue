<template>
  <div class="graph-tab">
    <p class="graph-lead">
      Roadmap graph: <strong>entities</strong>, sample <strong>pages</strong>, <strong>component imports</strong>, and
      <strong>API</strong> samples. Pan (drag background) and zoom (scroll). Click a node for source detail.
    </p>

    <div class="flow-host">
      <VueFlow
        :nodes="nodes"
        :edges="edges"
        :node-types="nodeTypes"
        :fit-view-on-init="true"
        :min-zoom="0.15"
        :max-zoom="1.6"
        :nodes-draggable="false"
        :nodes-connectable="false"
        :edges-updatable="false"
        class="vf-root"
        @node-click="onNodeClick"
      >
        <Background :gap="18" pattern-color="#30363d" />
        <Controls />
        <MiniMap
          :node-stroke-color="'#30363d'"
          :node-color="'#21262d'"
          pannable
          zoomable
        />
      </VueFlow>
    </div>

    <div v-if="selected" class="node-detail">
      <button type="button" class="close" @click="selected = null">×</button>
      <h4>{{ selected.data?.label }}</h4>
      <p class="meta"><span class="pill">{{ selected.data?.group }}</span></p>
      <p v-if="selected.data?.detail" class="row"><strong>Detail</strong><br />{{ selected.data.detail }}</p>
      <p v-if="selected.data?.rel" class="row"><strong>File / source</strong><br /><code>{{ selected.data.rel }}</code></p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, markRaw, inject } from 'vue'
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

watch(
  () => pm.data.value?.roadmapGraph,
  (g) => {
    selected.value = null
    if (!g?.nodes?.length) {
      nodes.value = []
      edges.value = []
      return
    }
    nodes.value = g.nodes.map((n) => ({
      ...n,
      type: n.type || 'roadmap',
    }))
    edges.value = (g.edges || []).map((e) => ({
      id: e.id,
      source: e.source,
      target: e.target,
      label: e.label,
      animated: !!e.animated,
    }))
  },
  { immediate: true },
)

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
  max-width: 640px;
}
.node-detail h4 {
  margin: 0 0 0.35rem;
  font-size: 14px;
  color: #58a6ff;
}
.meta {
  margin: 0 0 0.5rem;
}
.pill {
  display: inline-block;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  background: #21262d;
  font-size: 11px;
  color: #8b949e;
}
.row {
  margin: 0.5rem 0 0;
  color: #c9d1d9;
}
.row code {
  font-size: 11px;
  color: #79c0ff;
  word-break: break-all;
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
  line-height: 1;
}
</style>
