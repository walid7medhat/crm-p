<template>
  <div class="tab-panel">
    <p class="tab-lead">
      Collapsible tree of <code>resources/js</code> ({{ fileCount }} files in view). Use the search bar above to filter.
    </p>
    <div class="explorer">
      <ul class="explorer-tree">
        <ExplorerNode :node="filteredTree" :depth="0" />
      </ul>
    </div>
  </div>
</template>

<script setup>
import { inject, computed, defineComponent, h, ref } from 'vue'

const pm = inject('projectMap')
const data = computed(() => pm.data.value)

const q = computed(() => pm.query.value.trim().toLowerCase())

function match(text) {
  if (!q.value) return true
  return String(text || '').toLowerCase().includes(q.value)
}

function filterNode(node) {
  if (!node) return null
  if (node.type === 'file') {
    return match(node.name) ? { ...node } : null
  }
  const children = (node.children || []).map(filterNode).filter(Boolean)
  if (!q.value) return { ...node, children }
  if (match(node.name)) return { ...node, children: node.children || [] }
  if (children.length) return { ...node, children }
  return null
}

const filteredTree = computed(() => filterNode(data.value?.tree) || data.value?.tree)

const fileCount = computed(() => {
  let n = 0
  function walk(node) {
    if (!node) return
    if (node.type === 'file') n += 1
    ;(node.children || []).forEach(walk)
  }
  walk(filteredTree.value)
  return n
})

const ExplorerNode = defineComponent({
  name: 'ExplorerNode',
  props: { node: Object, depth: Number },
  setup(props) {
    const open = ref(props.depth < 3)
    return () => {
      const { node, depth } = props
      if (!node) return null
      if (node.type === 'file') {
        const ext = (node.name || '').split('.').pop() || ''
        const icon = ext === 'vue' ? '◆' : ext === 'js' ? '◇' : '▫'
        return h('li', { class: 'ex-file' }, [
          h('span', { class: 'ex-ico ex-ico-file', 'aria-hidden': 'true' }, icon),
          h('span', { class: 'ex-name' }, node.name),
        ])
      }
      return h('li', { class: 'ex-dir' }, [
        h(
          'button',
          {
            type: 'button',
            class: 'ex-row',
            onClick: () => {
              open.value = !open.value
            },
          },
          [
            h('span', { class: 'ex-chev' }, open.value ? '▼' : '▶'),
            h('span', { class: 'ex-ico', 'aria-hidden': 'true' }, '📁'),
            h('span', { class: 'ex-label' }, node.name),
          ],
        ),
        open.value && node.children?.length
          ? h(
              'ul',
              { class: 'ex-kids' },
              node.children.map((c) =>
                h(ExplorerNode, { node: c, depth: depth + 1, key: (c.name || '') + (c.type || '') }),
              ),
            )
          : null,
      ])
    }
  },
})
</script>

<style scoped>
.tab-panel {
  min-height: 320px;
}
.tab-lead {
  margin: 0 0 1rem;
  color: var(--dm-muted, #8b949e);
  font-size: 13px;
}
.tab-lead code {
  color: var(--dm-accent2, #79c0ff);
  font-size: 12px;
}
.explorer {
  max-height: min(72vh, 880px);
  overflow: auto;
  background: var(--dm-panel2, #0d1117);
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  padding: 0.5rem 0.25rem;
}
.explorer-tree,
.ex-kids {
  list-style: none;
  margin: 0;
  padding: 0 0 0 0.35rem;
}
.ex-kids {
  padding-left: 1.15rem;
  border-left: 1px solid var(--dm-border, #30363d);
}
.ex-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  width: 100%;
  text-align: left;
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  padding: 0.2rem 0.25rem;
  border-radius: 4px;
  font: inherit;
}
.ex-row:hover {
  background: rgba(56, 139, 253, 0.08);
}
.ex-chev {
  width: 1rem;
  font-size: 9px;
  color: var(--dm-muted, #8b949e);
  flex-shrink: 0;
}
.ex-ico {
  opacity: 0.9;
  flex-shrink: 0;
}
.ex-ico-file {
  color: var(--dm-accent, #58a6ff);
  font-size: 11px;
  width: 1rem;
  text-align: center;
}
.ex-label {
  color: var(--dm-folder, #d29922);
  font-weight: 500;
}
.ex-file {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.15rem 0.25rem;
  font-size: 12px;
}
.ex-name {
  color: var(--dm-fg, #e6edf3);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
