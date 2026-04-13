<template>
  <div class="vf-card" :class="`vf-${kind}`">
    <Handle type="target" :position="Position.Top" class="vf-handle" />
    <div class="vf-title">{{ data.label }}</div>
    <div v-if="data.detail && String(data.detail).length && data.detail !== data.label" class="vf-sub">
      {{ data.detail }}
    </div>
    <div v-if="data.rel" class="vf-path" title="Source">{{ data.rel }}</div>
    <Handle type="source" :position="Position.Bottom" class="vf-handle" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'

const props = defineProps({
  data: {
    type: Object,
    default: () => ({}),
  },
})

const kind = computed(() => {
  const g = props.data?.group || 'default'
  return typeof g === 'string' ? g.replace(/[^a-z0-9_-]/gi, '') : 'default'
})
</script>

<style scoped>
.vf-card {
  min-width: 140px;
  max-width: 260px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #30363d;
  background: #161b22;
  font-size: 11px;
  color: #e6edf3;
}
.vf-title {
  font-weight: 600;
  font-size: 12px;
  line-height: 1.3;
}
.vf-sub {
  margin-top: 4px;
  color: #8b949e;
  font-size: 10px;
  word-break: break-word;
}
.vf-path {
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid #21262d;
  font-family: ui-monospace, monospace;
  font-size: 9px;
  color: #79c0ff;
  max-height: 4.5em;
  overflow: hidden;
}
.vf-handle {
  width: 6px !important;
  height: 6px !important;
  background: #58a6ff !important;
  border: none !important;
}
.vf-entity {
  border-color: #a371f7;
  background: linear-gradient(145deg, #1c1628 0%, #161b22 100%);
}
.vf-page {
  border-color: #3fb950;
  background: linear-gradient(145deg, #0d2818 0%, #161b22 100%);
}
.vf-component {
  border-color: #58a6ff;
}
.vf-api {
  border-color: #ffa657;
}
.vf-route {
  border-color: #f0883e;
  background: linear-gradient(145deg, #281a0d 0%, #161b22 100%);
}
.vf-default {
  border-color: #484f58;
}
</style>
