<template>
  <div class="arch-tab">
    <p class="arch-lead">
      Logical <strong>feature domains</strong> (not only folders). Expand a domain to see pages (entry points), components, composables, services, and sample API calls.
      Toggle <strong>Show paths</strong> to reveal file paths.
    </p>

    <label class="arch-toggle">
      <input v-model="showPaths" type="checkbox" />
      Show file paths
    </label>

    <div v-for="domain in filteredDomains" :key="domain.id" class="domain-block">
      <button type="button" class="domain-head" @click="toggle(domain.id)">
        <span class="chev">{{ open[domain.id] === false ? '▶' : '▼' }}</span>
        <span class="domain-title">{{ domain.title }}</span>
        <span class="domain-id">{{ domain.id }}</span>
        <span class="counts">
          <span v-if="domain.pages?.length" class="c">{{ domain.pages.length }} pages</span>
          <span v-if="domain.components?.length" class="c">{{ domain.components.length }} cmp</span>
          <span v-if="domain.composables?.length" class="c">{{ domain.composables.length }} comp</span>
        </span>
      </button>

      <div v-show="open[domain.id] !== false" class="domain-body">
        <section v-if="domain.pages?.length" class="bucket">
          <h4 class="bucket-title entry">Pages & entry routes</h4>
          <ul class="item-list">
            <li v-for="p in domain.pages" :key="p.id" class="item entry">
              <span class="badge entry">page</span>
              <span class="lbl">{{ p.label }}</span>
              <span v-if="p.shared" class="shared">shared</span>
              <code v-if="showPaths" class="path">{{ p.rel }}</code>
            </li>
          </ul>
        </section>

        <section v-if="domain.components?.length" class="bucket">
          <h4 class="bucket-title">Components</h4>
          <ul class="item-list">
            <li v-for="c in domain.components" :key="c.id" class="item">
              <span class="badge cmp">ui</span>
              <span class="lbl">{{ c.label }}</span>
              <span v-if="c.shared" class="shared">shared</span>
              <code v-if="showPaths" class="path">{{ c.rel }}</code>
            </li>
          </ul>
        </section>

        <section v-if="domain.composables?.length" class="bucket">
          <h4 class="bucket-title">Composables / hooks</h4>
          <ul class="item-list">
            <li v-for="c in domain.composables" :key="c.id" class="item">
              <span class="badge hook">hook</span>
              <span class="lbl">{{ c.label }}</span>
              <code v-if="showPaths" class="path">{{ c.rel }}</code>
            </li>
          </ul>
        </section>

        <section v-if="domain.services?.length" class="bucket">
          <h4 class="bucket-title">Services / API helpers</h4>
          <ul class="item-list">
            <li v-for="s in domain.services" :key="s.id" class="item">
              <span class="badge svc">svc</span>
              <span class="lbl">{{ s.label }}</span>
              <code v-if="showPaths" class="path">{{ s.rel }}</code>
            </li>
          </ul>
        </section>

        <section v-if="domain.apiSamples?.length" class="bucket">
          <h4 class="bucket-title">Sample API usage (from this area)</h4>
          <ul class="api-mini">
            <li v-for="(a, i) in domain.apiSamples" :key="i">
              <code class="meth">{{ a.method }}</code>
              <span class="u">{{ a.url }}</span>
              <code v-if="showPaths" class="path sm">{{ a.file }}:{{ a.line }}</code>
            </li>
          </ul>
        </section>
      </div>
    </div>

    <p v-if="!filteredDomains.length" class="empty">No domains match the current filter.</p>
  </div>
</template>

<script setup>
import { inject, computed, ref, reactive, watch } from 'vue'

const pm = inject('projectMap')
const data = computed(() => pm.data.value)
const q = computed(() => pm.query.value.trim().toLowerCase())

const showPaths = ref(false)
const open = reactive({})

const domains = computed(() => data.value?.architecture?.domains || [])

function matchesItem(item) {
  if (!q.value) return true
  return (
    String(item.label || '')
      .toLowerCase()
      .includes(q.value) ||
    String(item.rel || '')
      .toLowerCase()
      .includes(q.value)
  )
}

function domainMatches(d) {
  if (!q.value) return true
  if (String(d.title).toLowerCase().includes(q.value) || String(d.id).toLowerCase().includes(q.value))
    return true
  const buckets = [...(d.pages || []), ...(d.components || []), ...(d.composables || []), ...(d.services || [])]
  if (buckets.some(matchesItem)) return true
  const samples = d.apiSamples || []
  return samples.some((a) => {
    const u = String(a.url || '').toLowerCase()
    const f = String(a.file || '').toLowerCase()
    return u.includes(q.value) || f.includes(q.value)
  })
}

const filteredDomains = computed(() => (domains.value || []).filter(domainMatches))

function toggle(id) {
  open[id] = open[id] === false
}

watch(
  filteredDomains,
  (list) => {
    for (const d of list) {
      if (open[d.id] === undefined) open[d.id] = true
    }
  },
  { immediate: true },
)
</script>

<style scoped>
.arch-tab {
  max-width: 1100px;
}
.arch-lead {
  margin: 0 0 0.75rem;
  color: var(--dm-muted, #8b949e);
  font-size: 13px;
  line-height: 1.5;
}
.arch-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 12px;
  color: #c9d1d9;
  margin-bottom: 1rem;
  cursor: pointer;
  user-select: none;
}
.domain-block {
  margin-bottom: 0.5rem;
  border: 1px solid var(--dm-border, #30363d);
  border-radius: 8px;
  overflow: hidden;
  background: var(--dm-panel2, #0d1117);
}
.domain-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
  width: 100%;
  text-align: left;
  padding: 0.55rem 0.75rem;
  background: rgba(22, 27, 34, 0.95);
  border: none;
  color: inherit;
  cursor: pointer;
  font: inherit;
}
.domain-head:hover {
  background: rgba(56, 139, 253, 0.06);
}
.chev {
  color: #8b949e;
  width: 1rem;
  font-size: 10px;
}
.domain-title {
  font-weight: 600;
  font-size: 14px;
  color: var(--dm-accent, #58a6ff);
}
.domain-id {
  font-size: 11px;
  color: #6e7681;
  font-family: ui-monospace, monospace;
}
.counts {
  margin-left: auto;
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}
.counts .c {
  font-size: 10px;
  color: #8b949e;
  background: #21262d;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
}
.domain-body {
  padding: 0.5rem 0.75rem 0.85rem;
  border-top: 1px solid #21262d;
}
.bucket {
  margin-bottom: 0.85rem;
}
.bucket-title {
  margin: 0 0 0.35rem;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #8b949e;
}
.bucket-title.entry {
  color: #3fb950;
}
.item-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.item {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem 0.5rem;
  padding: 0.3rem 0.35rem;
  border-radius: 4px;
  font-size: 12px;
}
.item:hover {
  background: rgba(255, 255, 255, 0.03);
}
.item.entry {
  border-left: 2px solid #238636;
  padding-left: 0.5rem;
}
.badge {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.1rem 0.35rem;
  border-radius: 3px;
  background: #21262d;
  color: #8b949e;
}
.badge.entry {
  background: #1f3d2c;
  color: #3fb950;
}
.badge.cmp {
  color: #58a6ff;
}
.badge.hook {
  color: #d2a8ff;
}
.badge.svc {
  color: #ffa657;
}
.lbl {
  font-weight: 500;
  color: #e6edf3;
}
.shared {
  font-size: 10px;
  color: #ffa657;
  border: 1px solid #6e3f00;
  border-radius: 3px;
  padding: 0 0.25rem;
}
.path {
  width: 100%;
  flex-basis: 100%;
  font-size: 10px;
  color: #79c0ff;
  margin-top: 0.15rem;
}
.api-mini {
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 11px;
}
.api-mini li {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: 0.35rem;
  padding: 0.25rem 0;
  border-bottom: 1px solid #21262d;
}
.api-mini .meth {
  color: #ff7b72;
}
.api-mini .u {
  color: #c9d1d9;
  word-break: break-all;
}
.path.sm {
  grid-column: 1 / -1;
  font-size: 9px;
}
.empty {
  color: #8b949e;
  padding: 1rem;
}
</style>
