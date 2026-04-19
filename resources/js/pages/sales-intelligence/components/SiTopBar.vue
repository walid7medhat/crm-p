<template>
  <header class="tb" :class="{ 'tb--busy': systemBusy }">
    <div class="tb__left">
      <span class="tb__title">Sales Intelligence</span>
      <span v-if="dataStale" class="tb__stale" title="Scores may be outdated">Stale</span>
    </div>

    <div class="tb__center">
      <button type="button" class="tb__pal-btn" aria-haspopup="dialog" @click="openPalette">
        <iconify-icon icon="lucide:terminal" class="tb__pal-ic" aria-hidden="true" />
        <span class="tb__pal-t">Command…</span>
        <kbd class="tb__kbd-inline" aria-hidden="true">⌘K</kbd>
      </button>
    </div>

    <div class="tb__right">
      <button type="button" class="tb__btn tb__btn--ghost" @click="$emit('distribute')">Distribute</button>
      <button type="button" class="tb__btn tb__btn--ghost" @click="$emit('ai-suggest')">AI suggest</button>
      <span class="tb__badge" title="Access level">{{ roleLabel }}</span>
    </div>

    <Teleport to="body">
      <div
        v-if="paletteOpen"
        class="si-pal-root"
        role="dialog"
        aria-modal="true"
        aria-label="Command palette"
        @keydown="onPaletteRootKey"
      >
        <div class="si-pal-back" @click="closePalette" />
        <div class="si-pal" @click.stop>
          <div class="si-pal__head">
            <iconify-icon icon="lucide:terminal" class="si-pal__head-ic" aria-hidden="true" />
            <input
              ref="palInputRef"
              v-model="localQuery"
              type="search"
              class="si-pal__input"
              placeholder="Navigate, run actions, search data…"
              autocomplete="off"
              aria-autocomplete="list"
              :aria-activedescendant="activeDescendant"
              @input="onPalInput"
              @keydown="onPaletteInputKey"
            />
            <button type="button" class="si-pal__close" aria-label="Close" @click="closePalette">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
          <div ref="listRef" class="si-pal__list" role="listbox">
            <p v-if="loading" class="si-pal__hint">Searching data…</p>
            <template v-else-if="flatItems.length">
              <div
                v-for="(it, i) in flatItems"
                :id="'si-pal-opt-' + i"
                :key="it.key"
                role="option"
                :class="['si-pal__row', { 'si-pal__row--hi': i === highlightIdx }]"
                :aria-selected="i === highlightIdx"
                @mouseenter="highlightIdx = i"
                @mousedown.prevent="pickItem(it)"
              >
                <span class="si-pal__tag">{{ it.group }}</span>
                <span class="si-pal__main">
                  <span class="si-pal__t">{{ it.title }}</span>
                  <span class="si-pal__s">{{ it.sub }}</span>
                </span>
                <kbd v-if="it.shortcut" class="si-pal__kbd">{{ it.shortcut }}</kbd>
              </div>
            </template>
            <div v-else class="si-pal__empty">
              <p class="si-pal__hint">No matches</p>
              <button type="button" class="si-pal__link" @mousedown.prevent="clearQuery">Clear query</button>
            </div>
            <p v-if="!localQuery.trim() && !flatItems.length && !loading" class="si-pal__hint">
              ↑↓ move · Enter run · Esc close
            </p>
          </div>
        </div>
      </div>
    </Teleport>
  </header>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useSiGlobalSearch } from '@/composables/useSiGlobalSearch'
import { fuzzyScore } from '@/composables/siFuzzy'
import { clearSiCommandHistory, getSiCommandHistory, pushSiCommandHistory } from '@/composables/siCommandHistory'

const props = defineProps({
  rules: { type: Array, default: () => [] },
  activeTab: { type: String, default: 'overview' },
  systemBusy: { type: Boolean, default: false },
  dataStale: { type: Boolean, default: false },
})

const emit = defineEmits(['distribute', 'ai-suggest', 'pick-agent', 'pick-lead', 'pick-rule', 'palette-exec'])

/** contextTab: palette prioritizes these when query empty and tab matches */
const COMMAND_DEFS = [
  { id: 'nav:overview', group: 'Navigation', title: 'Overview', sub: 'Scores snapshot & KPIs', kw: 'home dashboard', shortcut: '', contextTab: 'overview' },
  { id: 'nav:agents', group: 'Navigation', title: 'Agents', sub: 'Terminal table & sort', kw: 'sales team', shortcut: '', contextTab: 'agents' },
  { id: 'nav:rules', group: 'Navigation', title: 'Rules', sub: 'Scoring control panel', kw: 'weights factors', shortcut: '', contextTab: 'rules' },
  { id: 'nav:distribution', group: 'Navigation', title: 'Distribution', sub: 'Route simulator', kw: 'assign lead', shortcut: '', contextTab: 'distribution' },
  { id: 'nav:ai', group: 'Navigation', title: 'AI mode', sub: 'Suggestions workspace', kw: 'suggest heuristic', shortcut: '', contextTab: 'ai' },
  { id: 'nav:logs', group: 'Navigation', title: 'Logs', sub: 'Assignment history', kw: 'audit trail', shortcut: '', contextTab: 'logs' },
  { id: 'action:open-top-agent', group: 'Agents', title: 'Open top-scoring agent', sub: 'Heuristic pick in pool', kw: 'best score winner', shortcut: '', contextTab: 'agents' },
  { id: 'action:distribute', group: 'Actions', title: 'Open distribute', sub: 'Jump to simulator', kw: 'route lead', shortcut: '', contextTab: 'distribution' },
  { id: 'action:chain-dist-logs', group: 'Actions', title: 'Queue: Distribution → reload logs', sub: 'Multi-step', kw: 'chain queue', shortcut: '', contextTab: 'distribution' },
  { id: 'action:ai', group: 'AI', title: 'Open AI suggest', sub: 'Jump to AI workspace', kw: 'recommendation', shortcut: '', contextTab: 'ai' },
  { id: 'action:reload-logs', group: 'Data', title: 'Reload distribution logs', sub: 'Refresh last page', kw: 'refresh sync', shortcut: '', contextTab: 'logs' },
  { id: 'action:clear-history', group: 'Data', title: 'Clear command history', sub: 'Local palette memory', kw: 'reset', shortcut: '' },
]

const rulesRef = computed(() => props.rules || [])
const gs = useSiGlobalSearch(rulesRef)

const localQuery = ref('')
const paletteOpen = ref(false)
const palInputRef = ref(null)
const highlightIdx = ref(0)

const loading = computed(() => gs.loading.value)
const agents = computed(() => gs.agents.value)
const leads = computed(() => gs.leads.value)
const rulesHits = computed(() => gs.rules.value)

function hayCmd(c) {
  return `${c.title} ${c.sub} ${c.kw || ''} ${c.group}`
}

const GROUP_ORDER = ['Agents', 'Navigation', 'Actions', 'AI', 'Data']

function contextRank(cmd) {
  const tab = props.activeTab
  const t = cmd.contextTab
  if (t && t === tab) return 0
  if (!t) return 4
  return 2
}

function filteredCommands(q) {
  const s = q.trim()
  if (!s) {
    return [...COMMAND_DEFS].sort((a, b) => {
      const cr = contextRank(a) - contextRank(b)
      if (cr !== 0) return cr
      return GROUP_ORDER.indexOf(a.group) - GROUP_ORDER.indexOf(b.group)
    })
  }
  return COMMAND_DEFS.filter((c) => fuzzyScore(hayCmd(c), s) > 0).sort((a, b) => fuzzyScore(hayCmd(b), s) - fuzzyScore(hayCmd(a), s))
}

function historyRows(q) {
  const s = q.trim().toLowerCase()
  return getSiCommandHistory()
    .filter((h) => !s || String(h.label || '').toLowerCase().includes(s) || String(h.group || '').toLowerCase().includes(s))
    .map((h, i) => ({
      key: `hist-${h.ts}-${i}`,
      group: 'Recent',
      title: h.label || 'Command',
      sub: h.group || 'Replay',
      kind: 'history',
      hist: h,
      shortcut: '',
    }))
}

const flatItems = computed(() => {
  const q = localQuery.value
  const rows = []
  filteredCommands(q).forEach((c) => {
    rows.push({
      key: `cmd-${c.id}`,
      group: c.group,
      title: c.title,
      sub: c.sub,
      kind: 'command',
      cmdId: c.id,
      shortcut: c.shortcut,
    })
  })
  historyRows(q).forEach((h) => rows.push(h))
  agents.value.forEach((a) => {
    if (!q.trim() || fuzzyScore(`${a.title} ${a.subtitle}`, q) > 0) {
      rows.push({
        key: `a-${a.id}`,
        group: 'Agents',
        title: a.title,
        sub: a.subtitle || '',
        kind: 'agent',
        row: a,
        shortcut: '',
      })
    }
  })
  leads.value.forEach((l) => {
    if (!q.trim() || fuzzyScore(`${l.title} ${l.subtitle}`, q) > 0) {
      rows.push({
        key: `l-${l.id}`,
        group: 'Leads',
        title: l.title,
        sub: l.subtitle ? `#${l.subtitle}` : `#${l.id}`,
        kind: 'lead',
        row: l,
        shortcut: '',
      })
    }
  })
  rulesHits.value.forEach((r) => {
    rows.push({
      key: `r-${r.id}`,
      group: 'Rules',
      title: r.title,
      sub: r.subtitle || '',
      kind: 'rule',
      row: r,
      shortcut: '',
    })
  })
  return rows
})

const activeDescendant = computed(() =>
  highlightIdx.value >= 0 ? `si-pal-opt-${highlightIdx.value}` : undefined
)

const roleLabel = computed(() => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return 'Admin'
    const u = JSON.parse(raw)
    return Array.isArray(u.roles) && u.roles.includes('super_admin') ? 'super_admin' : 'Admin'
  } catch {
    return 'Admin'
  }
})

watch(flatItems, (list) => {
  if (highlightIdx.value >= list.length) highlightIdx.value = Math.max(0, list.length - 1)
})

watch(localQuery, (q) => {
  gs.scheduleSearch(q)
  highlightIdx.value = 0
})

function onPalInput() {
  highlightIdx.value = 0
}

function openPalette() {
  paletteOpen.value = true
  nextTick(() => {
    palInputRef.value?.focus()
    palInputRef.value?.select?.()
    highlightIdx.value = flatItems.value.length ? 0 : -1
  })
}

function closePalette() {
  paletteOpen.value = false
  highlightIdx.value = 0
}

function clearQuery() {
  localQuery.value = ''
  highlightIdx.value = 0
}

function recordHistory(entry) {
  pushSiCommandHistory(entry)
}

function execCommand(cmdId) {
  if (cmdId.startsWith('nav:')) {
    const tab = cmdId.slice(4)
    recordHistory({ kind: 'nav', tab, label: `Go ${tab}`, group: 'Navigation' })
    emit('palette-exec', { type: 'nav', tab })
    closePalette()
    return
  }
  if (cmdId === 'action:distribute') {
    recordHistory({ kind: 'action', id: cmdId, label: 'Distribute', group: 'Actions' })
    emit('palette-exec', { type: 'distribute' })
    closePalette()
    return
  }
  if (cmdId === 'action:ai') {
    recordHistory({ kind: 'action', id: cmdId, label: 'AI suggest', group: 'Actions' })
    emit('palette-exec', { type: 'ai' })
    closePalette()
    return
  }
  if (cmdId === 'action:reload-logs') {
    recordHistory({ kind: 'action', id: cmdId, label: 'Reload logs', group: 'Data' })
    emit('palette-exec', { type: 'reload-logs' })
    closePalette()
    return
  }
  if (cmdId === 'action:open-top-agent') {
    recordHistory({ kind: 'action', id: cmdId, label: 'Open top agent', group: 'Agents' })
    emit('palette-exec', { type: 'open-top-agent' })
    closePalette()
    return
  }
  if (cmdId === 'action:chain-dist-logs') {
    recordHistory({ kind: 'action', id: cmdId, label: 'Queue dist→logs', group: 'Actions' })
    emit('palette-exec', {
      type: 'chain',
      steps: [
        { type: 'nav', tab: 'distribution' },
        { type: 'reload-logs' },
      ],
    })
    closePalette()
    return
  }
  if (cmdId === 'action:clear-history') {
    clearSiCommandHistory()
    closePalette()
    return
  }
}

function replayHistory(h) {
  if (h.kind === 'nav' && h.tab) emit('palette-exec', { type: 'nav', tab: h.tab })
  else if (h.kind === 'action' && h.id === 'action:distribute') emit('palette-exec', { type: 'distribute' })
  else if (h.kind === 'action' && h.id === 'action:ai') emit('palette-exec', { type: 'ai' })
  else if (h.kind === 'action' && h.id === 'action:reload-logs') emit('palette-exec', { type: 'reload-logs' })
  else if (h.kind === 'action' && h.id === 'action:open-top-agent') emit('palette-exec', { type: 'open-top-agent' })
  else if (h.kind === 'action' && h.id === 'action:chain-dist-logs') {
    emit('palette-exec', {
      type: 'chain',
      steps: [
        { type: 'nav', tab: 'distribution' },
        { type: 'reload-logs' },
      ],
    })
  }
  closePalette()
}

function pickItem(it) {
  if (it.kind === 'command') {
    execCommand(it.cmdId)
    return
  }
  if (it.kind === 'history') {
    replayHistory(it.hist)
    return
  }
  if (it.kind === 'agent') {
    recordHistory({ kind: 'pick', id: it.row.id, label: it.row.title, group: 'Agents' })
    emit('pick-agent', it.row)
  }
  if (it.kind === 'lead') {
    recordHistory({ kind: 'pick', id: it.row.id, label: it.row.title, group: 'Leads' })
    emit('pick-lead', it.row)
  }
  if (it.kind === 'rule') {
    recordHistory({ kind: 'pick', id: it.row.id, label: it.row.title, group: 'Rules' })
    emit('pick-rule', it.row)
  }
  closePalette()
}

function onPaletteInputKey(e) {
  const n = flatItems.value.length
  if (!n) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightIdx.value = (highlightIdx.value + 1) % n
    scrollHi()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightIdx.value = (highlightIdx.value - 1 + n) % n
    scrollHi()
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const it = flatItems.value[highlightIdx.value]
    if (it) pickItem(it)
  } else if (e.key === 'Escape') {
    e.preventDefault()
    closePalette()
  }
}

function onPaletteRootKey(e) {
  if (e.key === 'Escape') {
    e.preventDefault()
    closePalette()
  }
}

function scrollHi() {
  nextTick(() => {
    const el = document.getElementById(`si-pal-opt-${highlightIdx.value}`)
    el?.scrollIntoView({ block: 'nearest' })
  })
}

function onGlobalKey(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    if (paletteOpen.value) closePalette()
    else openPalette()
  }
}

onMounted(() => {
  document.addEventListener('keydown', onGlobalKey)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onGlobalKey)
})

defineExpose({ openPalette, closePalette })
</script>

<style scoped>
.tb {
  position: sticky;
  top: 0;
  z-index: 50;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px 12px;
  min-height: 40px;
  padding: 6px 10px;
  margin: 0 -6px 0;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  transition: box-shadow 0.16s ease;
}

.tb--busy {
  box-shadow: inset 0 -2px 0 #111827;
}

.tb__left {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.tb__title {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  letter-spacing: -0.02em;
}

.tb__stale {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 6px;
  border-radius: 999px;
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}

.tb__center {
  min-width: 0;
  display: flex;
  justify-content: center;
}

.tb__pal-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  max-width: 420px;
  height: 32px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fafafa;
  color: #6b7280;
  font-size: 12px;
  cursor: pointer;
  transition:
    border-color 0.16s ease,
    background 0.16s ease,
    color 0.16s ease;
}

.tb__pal-btn:hover {
  border-color: #d4d4d4;
  background: #fff;
  color: #374151;
}

.tb__pal-btn:focus-visible {
  outline: none;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
}

.tb__pal-ic {
  font-size: 15px;
  flex-shrink: 0;
}

.tb__pal-t {
  flex: 1;
  min-width: 0;
  text-align: left;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tb__kbd-inline {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 600;
  color: #9ca3af;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  padding: 2px 5px;
  background: #fff;
}

.tb__right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.tb__btn {
  height: 30px;
  padding: 0 10px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: background 0.16s ease, border-color 0.16s ease;
}

.tb__btn--ghost {
  background: #fff;
  border: 1px solid #e5e7eb;
  color: #374151;
}

.tb__btn--ghost:hover {
  background: #fafafa;
  border-color: #d4d4d4;
}

.tb__btn:focus-visible {
  outline: none;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
}

.tb__badge {
  font-size: 10px;
  font-weight: 600;
  padding: 3px 6px;
  border-radius: 6px;
  background: #f3f4f6;
  color: #4b5563;
  border: 1px solid #e5e7eb;
}

.si-pal-root {
  position: fixed;
  inset: 0;
  z-index: 12000;
  display: grid;
  place-items: start center;
  padding-top: 10vh;
  padding-left: 16px;
  padding-right: 16px;
}

.si-pal-back {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
}

.si-pal {
  position: relative;
  width: min(640px, 100%);
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.14);
  overflow: hidden;
  animation: si-pal-in 0.16s ease-out;
}

@keyframes si-pal-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

.si-pal__head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-bottom: 1px solid #f3f4f6;
}

.si-pal__head-ic {
  font-size: 18px;
  color: #9ca3af;
  flex-shrink: 0;
}

.si-pal__input {
  flex: 1;
  min-width: 0;
  border: none;
  font-size: 14px;
  color: #111827;
  outline: none;
  padding: 6px 4px;
  background: transparent;
}

.si-pal__input:focus-visible {
  outline: none;
}

.si-pal__close {
  border: none;
  background: #f3f4f6;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  cursor: pointer;
  color: #6b7280;
}

.si-pal__close:hover {
  background: #e5e7eb;
  color: #111827;
}

.si-pal__list {
  max-height: min(420px, 52vh);
  overflow: auto;
  padding: 4px 0 8px;
}

.si-pal__hint {
  margin: 0;
  padding: 10px 14px;
  font-size: 11px;
  color: #9ca3af;
}

.si-pal__empty {
  padding: 0 12px 8px;
}

.si-pal__link {
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 600;
  color: #4f46e5;
  cursor: pointer;
  padding: 0;
}

.si-pal__row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 6px 12px;
  cursor: pointer;
  border-left: 2px solid transparent;
  transition: background 0.16s ease;
}

.si-pal__row:hover,
.si-pal__row--hi {
  background: #fafafa;
}

.si-pal__row--hi {
  border-left-color: #111827;
}

.si-pal__tag {
  flex-shrink: 0;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  margin-top: 2px;
  width: 72px;
}

.si-pal__main {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.si-pal__t {
  font-size: 13px;
  font-weight: 500;
  color: #111827;
}

.si-pal__s {
  font-size: 11px;
  color: #6b7280;
}

.si-pal__kbd {
  flex-shrink: 0;
  font-size: 9px;
  font-weight: 600;
  color: #9ca3af;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  padding: 2px 5px;
  margin-top: 1px;
}

@media (max-width: 900px) {
  .tb {
    grid-template-columns: 1fr;
  }

  .tb__center {
    order: 2;
  }

  .tb__right {
    order: 3;
    justify-content: flex-start;
  }
}
</style>
