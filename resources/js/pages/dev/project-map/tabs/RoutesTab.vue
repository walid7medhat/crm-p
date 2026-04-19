<template>
  <div class="tab-panel">
    <p class="tab-lead">
      Nested path segments from <code>router.js</code> plus flat path → component mapping.
    </p>

    <div class="split">
      <div class="card">
        <h3 class="card-title">Route tree</h3>
        <div class="tree-box">
          <ul v-if="hierarchy" class="rt-tree">
            <RouteSeg :node="hierarchy" :depth="0" />
          </ul>
        </div>
      </div>
      <div class="card">
        <h3 class="card-title">Flat map ({{ filteredFlat.length }})</h3>
        <div class="flat-list">
          <div v-for="r in filteredFlat" :key="r.path + (r.name || '')" class="flat-row">
            <code class="pth">{{ r.path }}</code>
            <span class="arr">→</span>
            <span class="cmp">{{ r.component || '—' }}</span>
            <span v-if="r.redirect" class="redir">↪ {{ r.redirect }}</span>
            <span v-if="r.requiresSuperAdmin" class="pill sa">super_admin</span>
            <span v-else-if="r.requiresAdmin" class="pill">admin</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { inject, computed, defineComponent, h, ref } from 'vue'

const pm = inject('projectMap')
const data = computed(() => pm.data.value)

const q = computed(() => pm.query.value.trim().toLowerCase())

const hierarchy = computed(() => data.value?.routeHierarchy)

const filteredFlat = computed(() => {
  const list = data.value?.routes || []
  if (!q.value) return list
  return list.filter(
    (r) =>
      match(r.path) ||
      match(r.component) ||
      match(r.name) ||
      match(r.redirect),
  )
})

function match(t) {
  if (!q.value) return true
  return String(t || '').toLowerCase().includes(q.value)
}

const RouteSeg = defineComponent({
  name: 'RouteSeg',
  props: { node: Object, depth: Number },
  setup(props) {
    const open = ref(props.depth < 4)
    return () => {
      const { node, depth } = props
      if (!node) return null
      const label = node.segment === '' ? '/' : node.segment
      const hasKids = (node.children || []).length > 0
      const routesHere = node.routes || []
      return h('li', { class: 'rt-item' }, [
        h(
          'div',
          {
            class: 'rt-head',
          },
          [
            hasKids
              ? h(
                  'button',
                  {
                    type: 'button',
                    class: 'rt-btn',
                    onClick: () => {
                      open.value = !open.value
                    },
                  },
                  [h('span', { class: 'rt-chev' }, open.value ? '▼' : '▶')],
                )
              : h('span', { class: 'rt-sp' }),
            h('code', { class: 'rt-seg' }, label),
            h('span', { class: 'rt-full' }, node.fullPath || ''),
          ],
        ),
        routesHere.length
          ? h(
              'ul',
              { class: 'rt-routes' },
              routesHere.map((r) =>
                h('li', { key: r.path + (r.name || '') }, [
                  h('span', { class: 'rt-kind' }, r.kind === 'redirect' ? '↪' : '●'),
                  h('code', { class: 'rt-p' }, r.path),
                  r.component ? h('span', { class: 'rt-cmp' }, ` → ${r.component}`) : null,
                  r.redirect ? h('span', { class: 'rt-red' }, ` → ${r.redirect}`) : null,
                ]),
              ),
            )
          : null,
        hasKids && open.value
          ? h(
              'ul',
              { class: 'rt-children' },
              node.children.map((c) =>
                h(RouteSeg, { node: c, depth: depth + 1, key: c.segment }),
              ),
            )
          : null,
      ])
    }
  },
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
.split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 1100px) {
  .split {
    grid-template-columns: 1fr;
  }
}
.card {
  background: var(--dm-panel2, #0d1117);
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  padding: 0.75rem;
  min-height: 280px;
}
.card-title {
  margin: 0 0 0.5rem;
  font-size: 12px;
  font-weight: 600;
  color: var(--dm-muted, #8b949e);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.tree-box {
  max-height: min(65vh, 720px);
  overflow: auto;
}
.rt-tree,
.rt-children {
  list-style: none;
  margin: 0;
  padding: 0 0 0 0.5rem;
}
.rt-children {
  border-left: 1px solid var(--dm-border, #30363d);
}
.rt-head {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0.15rem 0;
}
.rt-btn {
  background: transparent;
  border: none;
  color: var(--dm-muted, #8b949e);
  cursor: pointer;
  padding: 0;
  width: 1.25rem;
}
.rt-sp {
  width: 1.25rem;
  display: inline-block;
}
.rt-seg {
  color: var(--dm-accent2, #79c0ff);
  font-size: 12px;
}
.rt-full {
  font-size: 11px;
  color: var(--dm-muted, #6e7681);
}
.rt-routes {
  list-style: none;
  margin: 0.25rem 0 0.5rem 1.5rem;
  padding: 0;
  font-size: 11px;
}
.rt-routes li {
  margin: 0.15rem 0;
  color: var(--dm-fg, #c9d1d9);
}
.rt-p {
  color: #7ee787;
}
.rt-cmp {
  color: #ffa657;
}
.rt-red {
  color: #ff7b72;
}
.flat-list {
  max-height: min(65vh, 720px);
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.flat-row {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem 0.5rem;
  padding: 0.4rem 0.5rem;
  background: rgba(1, 4, 9, 0.6);
  border-radius: 4px;
  border: 1px solid var(--dm-border, #21262d);
  font-size: 12px;
}
.pth {
  color: #79c0ff;
}
.arr {
  color: #6e7681;
}
.cmp {
  color: #7ee787;
  font-weight: 500;
}
.redir {
  color: #ffa657;
  font-size: 11px;
}
.pill {
  font-size: 10px;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  background: #21262d;
  color: #8b949e;
}
.pill.sa {
  background: #3d2f00;
  color: #ffc657;
}
</style>
