<template>
  <section class="at">
    <header class="at__head">
      <div class="at__head-l">
        <h6 class="at__title">Agents</h6>
        <p v-if="total" class="at__meta">{{ total }} match · p {{ page }}/{{ totalPages }}</p>
      </div>
      <div class="at__toolbar">
        <iconify-icon icon="lucide:filter" class="at__ic" aria-hidden="true" />
        <input
          v-model="filter"
          type="search"
          class="at__search"
          placeholder="Filter…"
          aria-label="Filter agents"
          autocomplete="off"
        />
        <button v-if="filter" type="button" class="at__clear" @click="filter = ''">Clear</button>
      </div>
    </header>

    <div v-if="loading" class="at__skel">
      <div v-for="n in 8" :key="n" class="at__sk" />
    </div>

    <div v-else class="at__wrap" @mouseleave="onLeaveWrap">
      <table class="at__tbl" role="grid" aria-label="Agents">
        <thead>
          <tr>
            <th class="at__th at__th--sort" scope="col">
              <button type="button" class="at__sort" @click="toggleSort('name')">
                Agent
                <span v-if="sortKey === 'name'" class="at__caret">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </button>
            </th>
            <th class="at__th at__th--num at__th--sort" scope="col">
              <button type="button" class="at__sort" @click="toggleSort('score')">
                Score
                <span v-if="sortKey === 'score'" class="at__caret">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </button>
            </th>
            <th class="at__th at__th--sort" scope="col">
              <button type="button" class="at__sort" @click="toggleSort('tier')">
                Tier
                <span v-if="sortKey === 'tier'" class="at__caret">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </button>
            </th>
            <th class="at__th at__th--sort" scope="col">
              <button type="button" class="at__sort" @click="toggleSort('at')">
                Updated
                <span v-if="sortKey === 'at'" class="at__caret">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="agent in agents"
            :key="agent.id"
            class="at__tr"
            :class="{
              'at__tr--rec': recommendedId != null && agent.id === recommendedId,
              'at__tr--sel': selectedId != null && agent.id === selectedId,
              'at__tr--pulse': pulseIds.includes(agent.id),
            }"
            tabindex="0"
            @click="emit('select', agent)"
            @keydown.enter.prevent="emit('select', agent)"
            @mouseenter="(e) => onEnterRow(e, agent)"
          >
            <td class="at__td">
              <div class="at__who">
                <img class="at__av" :src="avatarUrl(agent.avatar)" :alt="''" />
                <div class="at__nm">
                  <span class="at__name">{{ agent.name }}</span>
                  <span class="at__em">{{ agent.email }}</span>
                </div>
              </div>
            </td>
            <td class="at__td at__td--num">
              <SiAnimatedScore :value="agent.score" />
            </td>
            <td class="at__td">
              <span
                :key="`${agent.id}-${agent.rank}`"
                class="at__tier at__tier--live"
                :data-tier="String(agent.rank || 'cold').toLowerCase()"
              >{{ tierLabel(agent.rank) }}</span>
            </td>
            <td class="at__td at__muted">{{ shortDate(agent.calculated_at) }}</td>
          </tr>
        </tbody>
      </table>

      <aside v-if="hoverAgent" class="at__fly" :style="{ top: hoverTop + 'px' }" @mouseenter="cancelHide">
        <div class="at__fly-h">Preview</div>
        <div class="at__fly-n">{{ hoverAgent.name }}</div>
        <div class="at__fly-r">
          <span>Score</span> <strong>{{ scoreCell(hoverAgent.score) }}</strong>
        </div>
        <div class="at__fly-r">
          <span>Tier</span> <strong>{{ tierLabel(hoverAgent.rank) }}</strong>
        </div>
        <p v-if="recommendedId != null && hoverAgent?.id === recommendedId" class="at__fly-assist">
          Assist: highest composite in current pool — good default when routing hot leads.
        </p>
        <p v-else-if="assistLine && hoverAgent?.id === selectedId" class="at__fly-assist">{{ assistLine }}</p>
        <p class="at__fly-hint">Click row for full breakdown</p>
      </aside>
    </div>

    <footer v-if="totalPages > 1" class="at__pager">
      <button type="button" class="at__pg" :disabled="page <= 1" @click="emit('page-change', page - 1)">Prev</button>
      <span class="at__pgx">{{ page }} / {{ totalPages }}</span>
      <button type="button" class="at__pg" :disabled="page >= totalPages" @click="emit('page-change', page + 1)">
        Next
      </button>
    </footer>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { avatarUrl, tierLabel } from '../utils'
import SiAnimatedScore from './SiAnimatedScore.vue'

const filter = defineModel('filter', { type: String, default: '' })

const props = defineProps({
  agents: { type: Array, default: () => [] },
  loading: Boolean,
  page: { type: Number, default: 1 },
  totalPages: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
  sortKey: { type: String, default: 'score' },
  sortDir: { type: String, default: 'desc' },
  /** Heuristic “best” agent in loaded pool */
  recommendedId: { type: [Number, String], default: null },
  /** Row matching open drawer (comparison context) */
  selectedId: { type: [Number, String], default: null },
  /** Agent ids to flash after score drift */
  pulseIds: { type: Array, default: () => [] },
  /** Inline compare when hovering selected row */
  assistLine: { type: String, default: '' },
})

const emit = defineEmits(['select', 'page-change', 'sort-change'])

const hoverAgent = ref(null)
const hoverTop = ref(0)
let hideT = null

function scoreCell(s) {
  if (s == null || Number.isNaN(Number(s))) return '—'
  return Math.round(Number(s))
}

function shortDate(iso) {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return '—'
  }
}

function toggleSort(key) {
  if (props.sortKey === key) {
    emit('sort-change', { key, dir: props.sortDir === 'asc' ? 'desc' : 'asc' })
  } else {
    const dir = key === 'name' || key === 'at' ? 'asc' : 'desc'
    emit('sort-change', { key, dir })
  }
}

function onEnterRow(ev, agent) {
  cancelHide()
  hoverAgent.value = agent
  const tr = ev.currentTarget
  const wrap = tr.closest('.at__wrap')
  if (!wrap) return
  const wr = wrap.getBoundingClientRect()
  const r = tr.getBoundingClientRect()
  hoverTop.value = Math.max(0, r.top - wr.top + wrap.scrollTop)
}

function onLeaveWrap() {
  hideT = setTimeout(() => {
    hoverAgent.value = null
  }, 120)
}

function cancelHide() {
  clearTimeout(hideT)
}
</script>

<style scoped>
.at__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.at__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.at__meta {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.at__toolbar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  justify-content: flex-end;
  min-width: 140px;
  max-width: 280px;
}

.at__ic {
  font-size: 14px;
  color: #9ca3af;
}

.at__search {
  flex: 1;
  min-width: 0;
  height: 30px;
  padding: 0 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
  font-size: 12px;
  transition:
    border-color 0.16s ease,
    box-shadow 0.16s ease;
}

.at__search:focus {
  outline: none;
  border-color: #a3a3a3;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
  background: #fff;
}

.at__clear {
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
}

.at__wrap {
  position: relative;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: auto;
  max-height: min(520px, calc(100vh - 220px));
  background: #fff;
}

.at__tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.at__th {
  text-align: left;
  padding: 6px 10px;
  border-bottom: 1px solid #e5e7eb;
  background: #fafafa;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  position: sticky;
  top: 0;
  z-index: 1;
}

.at__th--num {
  text-align: right;
}

.at__sort {
  border: none;
  background: transparent;
  padding: 0;
  font: inherit;
  color: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.at__sort:hover {
  color: #111827;
}

.at__sort:focus-visible {
  outline: none;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
  border-radius: 4px;
}

.at__caret {
  font-size: 10px;
  color: #111827;
}

.at__tr {
  cursor: pointer;
  transition: background 0.16s ease;
}

.at__tr:hover,
.at__tr:focus-within {
  background: #fafafa;
}

.at__tr:focus-visible {
  outline: none;
  box-shadow: inset 0 0 0 2px #d4d4d4;
}

.at__tr--rec {
  box-shadow: inset 3px 0 0 #4f46e5;
}

.at__tr--sel {
  background: #fafafa;
}

.at__tr--pulse {
  animation: at-row-pulse 0.85s ease-out;
}

@keyframes at-row-pulse {
  0% {
    background: #eef2ff;
  }
  100% {
    background: transparent;
  }
}

.at__td {
  padding: 6px 10px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.at__td--num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.at__who {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.at__av {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.at__nm {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.at__name {
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.at__em {
  font-size: 10px;
  color: #9ca3af;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.at__muted {
  color: #9ca3af;
  font-size: 11px;
}

.at__tier {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px 6px;
  border-radius: 999px;
}

.at__tier[data-tier='hot'] {
  color: #047857;
  background: #d1fae5;
}

.at__tier[data-tier='warm'] {
  color: #b45309;
  background: #fef3c7;
}

.at__tier[data-tier='cold'] {
  color: #b91c1c;
  background: #fee2e2;
}

.at__tier--live {
  transition:
    background 0.4s ease,
    color 0.4s ease,
    border-color 0.4s ease;
}

.at__fly-assist {
  margin: 8px 0 0;
  font-size: 10px;
  line-height: 1.35;
  color: #4f46e5;
  font-weight: 600;
}

.at__fly {
  position: absolute;
  right: 8px;
  width: min(200px, 36%);
  padding: 8px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
  pointer-events: none;
  z-index: 2;
}

.at__fly-h {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  margin-bottom: 4px;
}

.at__fly-n {
  font-size: 12px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 6px;
}

.at__fly-r {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #6b7280;
  margin-bottom: 4px;
}

.at__fly-r strong {
  color: #111827;
}

.at__fly-hint {
  margin: 6px 0 0;
  font-size: 10px;
  color: #9ca3af;
  line-height: 1.3;
}

.at__pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 8px;
}

.at__pg {
  height: 30px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.16s ease;
}

.at__pg:hover:not(:disabled) {
  background: #fafafa;
}

.at__pg:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.at__pgx {
  font-size: 11px;
  color: #6b7280;
}

.at__skel {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.at__sk {
  height: 36px;
  border-radius: 6px;
  background: linear-gradient(110deg, #f3f4f6 8%, #e5e7eb 18%, #f3f4f6 33%);
  background-size: 200% 100%;
  animation: at-sh 1s ease-in-out infinite;
}

@keyframes at-sh {
  to {
    background-position-x: -200%;
  }
}
</style>
