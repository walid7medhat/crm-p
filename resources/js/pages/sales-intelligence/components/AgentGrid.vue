<template>
  <section class="ag">
    <header class="ag__head">
      <div class="ag__head-l">
        <h6 class="ag__title">Agents</h6>
        <p v-if="total" class="ag__meta">{{ total }} match · {{ page }}/{{ totalPages }}</p>
      </div>
      <div class="ag__toolbar">
        <iconify-icon icon="lucide:filter" class="ag__fil-ic" aria-hidden="true" />
        <input
          v-model="filter"
          type="search"
          class="ag__filter"
          placeholder="Filter name or email…"
          aria-label="Filter agents"
          autocomplete="off"
        />
        <button v-if="filter" type="button" class="ag__fil-clear" aria-label="Clear filter" @click="filter = ''">Clear</button>
      </div>
    </header>

    <div v-if="loading" class="ag__skeleton">
      <div v-for="n in 8" :key="n" class="ag-sk" />
    </div>

    <div v-else class="ag__grid">
      <button
        v-for="agent in agents"
        :key="agent.id"
        type="button"
        class="ag-card"
        :title="cardTitle(agent)"
        @click="emit('select', agent)"
      >
        <div class="ag-card__row">
          <img class="ag-card__avatar" :src="avatarUrl(agent.avatar)" :alt="agent.name" />
          <div class="ag-card__meta">
            <span class="ag-card__name">{{ agent.name }}</span>
            <span class="ag-badge" :data-tier="String(agent.rank || 'cold').toLowerCase()">{{ tierLabel(agent.rank) }}</span>
          </div>
        </div>
        <div class="ag-card__ring-wrap">
          <svg class="ag-ring" viewBox="0 0 100 100" aria-hidden="true">
            <defs>
              <linearGradient :id="'ag-grad-' + agent.id" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#6366f1" />
                <stop offset="100%" stop-color="#38bdf8" />
              </linearGradient>
            </defs>
            <circle class="ag-ring__bg" cx="50" cy="50" r="32" />
            <circle
              class="ag-ring__fg"
              cx="50"
              cy="50"
              r="32"
              :stroke="'url(#ag-grad-' + agent.id + ')'"
              :style="ringStyle(agent.score)"
            />
          </svg>
          <div class="ag-ring__label">
            <span class="ag-ring__score">{{ scoreDisplay(agent.score) }}</span>
          </div>
        </div>
        <div class="ag-stats">
          <span v-if="agent.calculated_at" class="ag-stats__i">{{ shortDate(agent.calculated_at) }}</span>
        </div>
      </button>
    </div>

    <footer v-if="totalPages > 1" class="ag__pager">
      <button type="button" class="ag__pg" :disabled="page <= 1" @click="emit('page-change', page - 1)">Prev</button>
      <span class="ag__pg-txt">{{ page }} / {{ totalPages }}</span>
      <button type="button" class="ag__pg" :disabled="page >= totalPages" @click="emit('page-change', page + 1)">
        Next
      </button>
    </footer>
  </section>
</template>

<script setup>
import { avatarUrl, tierLabel } from '../utils'

const filter = defineModel('filter', { type: String, default: '' })

const props = defineProps({
  agents: { type: Array, default: () => [] },
  loading: Boolean,
  page: { type: Number, default: 1 },
  totalPages: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
})

const emit = defineEmits(['select', 'page-change'])

const R = 32
const C = 2 * Math.PI * R

function ringStyle(score) {
  const s = Math.max(0, Math.min(100, Number(score) || 0))
  const dash = (s / 100) * C
  return { strokeDasharray: `${dash} ${C - dash}` }
}

function scoreDisplay(score) {
  if (score == null || Number.isNaN(Number(score))) return '—'
  return Math.round(Number(score))
}

function shortDate(iso) {
  try {
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  } catch {
    return ''
  }
}

function cardTitle(agent) {
  const bits = [agent.name, agent.email, agent.score != null ? `Score ${Math.round(Number(agent.score))}` : ''].filter(Boolean)
  return bits.join(' · ')
}
</script>

<style scoped>
.ag__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.ag__head-l {
  min-width: 0;
}

.ag__toolbar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  justify-content: flex-end;
  min-width: 160px;
  max-width: 280px;
}

.ag__fil-ic {
  font-size: 14px;
  color: #9ca3af;
  flex-shrink: 0;
}

.ag__filter {
  flex: 1;
  min-width: 0;
  height: 30px;
  padding: 0 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
  font-size: 12px;
  color: #111827;
  transition: border-color 0.12s ease, background 0.12s ease;
}

.ag__filter:focus {
  outline: none;
  border-color: #c7d2fe;
  background: #fff;
}

.ag__fil-clear {
  flex-shrink: 0;
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  padding: 4px 6px;
}

.ag__fil-clear:hover {
  color: #111827;
}

.ag__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.ag__meta {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.ag__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 8px;
}

.ag-card {
  text-align: left;
  cursor: pointer;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  background: #fff;
  transition: border-color 0.12s ease, background 0.12s ease, transform 0.12s ease;
}

.ag-card:hover {
  border-color: #d1d5db;
  background: #fafafa;
  transform: translateY(-1px);
}

.ag-card__row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ag-card__avatar {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
}

.ag-card__meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ag-card__name {
  font-size: 12px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ag-badge {
  align-self: flex-start;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 2px 6px;
  border-radius: 999px;
}

.ag-badge[data-tier='hot'] {
  color: #059669;
  background: #d1fae5;
}

.ag-badge[data-tier='warm'] {
  color: #b45309;
  background: #fef3c7;
}

.ag-badge[data-tier='cold'] {
  color: #b91c1c;
  background: #fee2e2;
}

.ag-card__ring-wrap {
  position: relative;
  height: 72px;
  margin-top: 4px;
}

.ag-ring {
  width: 72px;
  height: 72px;
  transform: rotate(-90deg);
}

.ag-ring__bg {
  fill: none;
  stroke: #f3f4f6;
  stroke-width: 7;
}

.ag-ring__fg {
  fill: none;
  stroke-width: 7;
  stroke-linecap: round;
}

.ag-ring__label {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ag-ring__score {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}

.ag-stats {
  margin-top: 4px;
}

.ag-stats__i {
  font-size: 10px;
  color: #9ca3af;
}

.ag__skeleton {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 8px;
}

.ag-sk {
  height: 112px;
  border-radius: 8px;
  background: linear-gradient(110deg, #f3f4f6 8%, #e5e7eb 18%, #f3f4f6 33%);
  background-size: 200% 100%;
  animation: ag-shimmer 1.1s ease-in-out infinite;
}

@keyframes ag-shimmer {
  to {
    background-position-x: -200%;
  }
}

.ag__pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 8px;
}

.ag__pg {
  height: 32px;
  padding: 0 14px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
}

.ag__pg:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.ag__pg-txt {
  font-size: 12px;
  color: #6b7280;
}
</style>
