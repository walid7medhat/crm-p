<template>
  <article class="dh-panel dh-panel--highlight">
    <div class="dh-panel-head">
      <p class="dh-panel-title">{{ title }}</p>
    </div>
    <div v-if="loading" class="dh-highlight-body dh-skeleton" style="min-height: 120px" />
    <div v-else-if="item" class="dh-highlight-body">
      <div class="dh-highlight-avatar">{{ initials }}</div>
      <div class="dh-highlight-info">
        <p class="dh-highlight-name">{{ item.name }}</p>
        <p class="dh-highlight-meta">{{ metaLine }}</p>
      </div>
      <p class="dh-highlight-stat">{{ statLine }}</p>
    </div>
    <div v-else class="dh-empty dh-empty--compact">
      <p class="dh-empty-text">{{ emptyText }}</p>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: { type: String, default: 'Highlight' },
  item: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  emptyText: { type: String, default: 'No data yet.' },
  metaKeys: { type: Array, default: () => ['leads', 'converted'] },
  statKey: { type: String, default: 'rate' },
  statSuffix: { type: String, default: '%' },
})

const initials = computed(() => {
  const n = props.item?.name || '?'
  return n.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase()
})

const metaLine = computed(() => {
  if (!props.item) return ''
  const parts = props.metaKeys.map((k) => {
    const labels = { leads: 'leads', converted: 'won', views: 'views', count: 'count' }
    return `${props.item[k] ?? 0} ${labels[k] || k}`
  })
  return parts.join(' · ')
})

const statLine = computed(() => {
  if (!props.item) return ''
  const v = props.item[props.statKey]
  return `${v}${props.statSuffix}`
})
</script>
