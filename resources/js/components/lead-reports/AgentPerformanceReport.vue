<template>
  <div class="report">
    <header class="report__header">
      <div>
        <h1>Agent Performance</h1>
        <p class="report__sub">Converted leads, lead score, deal value and commission per agent</p>
      </div>
      <button class="btn btn--ghost" @click="fetchReport" :disabled="loading">
        <span v-if="loading">Refreshing…</span>
        <span v-else>Refresh</span>
      </button>
    </header>

    <!-- Filters -->
    <div class="filters">
      <div class="field">
        <label>From</label>
        <input type="date" v-model="filters.from_date" @change="fetchReport" />
      </div>
      <div class="field">
        <label>To</label>
        <input type="date" v-model="filters.to_date" @change="fetchReport" />
      </div>
      <div class="field">
        <label>Agent</label>
        <select v-model="filters.agent_id" @change="fetchReport">
          <option value="">All agents</option>
          <option v-for="a in agentOptions" :key="a.agent_id" :value="a.agent_id">
            {{ a.agent_name }}
          </option>
        </select>
      </div>
      <div class="field">
        <label>Location</label>
        <select v-model="filters.area_id" @change="fetchReport">
          <option value="">All locations</option>
          <option v-for="area in areaOptions" :key="area.id" :value="area.id">
            {{ area.name }}
          </option>
        </select>
      </div>
      <!-- <div class="field">
        <label>Status</label>
        <select v-model="filters.status" @change="fetchReport">
          <option value="completed">Completed (won)</option>
          <option value="approved">Approved</option>
          <option value="">Any</option>
        </select>
      </div> -->
    </div>

    <!-- Summary -->
    <div class="summary" v-if="summary">
      <div class="summary__card">
        <span class="summary__label">Agents</span>
        <span class="summary__value">{{ summary.agents_count }}</span>
      </div>
      <div class="summary__card">
        <span class="summary__label">Converted deals</span>
        <span class="summary__value">{{ summary.deals_count }}</span>
      </div>
      <div class="summary__card">
        <span class="summary__label">Total deal value</span>
        <span class="summary__value">{{ formatMoney(summary.total_amount) }}</span>
      </div>
      <div class="summary__card summary__card--accent">
        <span class="summary__label">Total commission</span>
        <span class="summary__value">{{ formatMoney(summary.total_commission) }}</span>
      </div>
    </div>

    <!-- Loading / empty states -->
    <div v-if="loading" class="state">Loading report…</div>
    <div v-else-if="error" class="state state--error">{{ error }}</div>
    <div v-else-if="!agents.length" class="state">No converted deals in this range.</div>

    <!-- Agent groups -->
    <div v-else class="agents">
      <div v-for="agent in agents" :key="agent.agent_id" class="agent-card">
        <button class="agent-card__head" @click="toggle(agent.agent_id)">
          <div class="agent-card__who">
            <span class="agent-card__name">{{ agent.agent_name }}</span>
            <span class="agent-card__email" v-if="agent.agent_email">{{ agent.agent_email }}</span>
          </div>

          <div class="agent-card__stats">
            <div class="stat">
              <span class="stat__label">Converted</span>
              <span class="stat__value">{{ agent.converted_count }}</span>
            </div>
            <div class="stat">
              <span class="stat__label">Avg score</span>
              <span class="stat__value">{{ agent.avg_lead_score ?? '—' }}</span>
            </div>
            <div class="stat">
              <span class="stat__label">Deal value</span>
              <span class="stat__value">{{ formatMoney(agent.total_amount) }}</span>
            </div>
            <div class="stat stat--accent">
              <span class="stat__label">Commission</span>
              <span class="stat__value">{{ formatMoney(agent.total_commission) }}</span>
            </div>
          </div>

          <span class="chevron" :class="{ 'chevron--open': expanded.has(agent.agent_id) }">›</span>
        </button>

        <div v-show="expanded.has(agent.agent_id)" class="agent-card__body">
          <table class="deals-table">
            <thead>
              <tr>
                <th>Lead</th>
                <th class="num">Lead score</th>
                <th>Deal #</th>
                <th>Location</th>
                <th class="num">Amount</th>
                <th class="num">Commission</th>
                <th>Converted</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in agent.deals" :key="row.deal_id">
                <td>{{ row.lead_name || '—' }}</td>
                <td class="num">
                  <span class="score-pill" :class="scoreClass(row.lead_score)">
                    {{ row.lead_score ?? '—' }}
                  </span>
                </td>
                <td>{{ row.deal_number || '#' + row.deal_id }}</td>
                <td>
                  {{ row.location || '—' }}
                  <span v-if="row.location_extra" class="location-extra">+{{ row.location_extra }} more</span>
                </td>
                <td class="num">{{ formatMoney(row.deal_amount, row.currency) }}</td>
                <td class="num num--accent">{{ formatMoney(row.commission, row.currency) }}</td>
                <td>{{ row.converted_at  || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const agents = ref([])
const summary = ref(null)
const loading = ref(false)
const error = ref('')
const expanded = ref(new Set())

const filters = reactive({
  from_date: '',
  to_date: '',
  agent_id: '',
  area_id: '',
  status: 'completed',
})

// Default range: first day of the current month through today.
function currentMonthRange() {
  const now = new Date()
  const toStr = (d) => d.toISOString().slice(0, 10)
  const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
  return { from: toStr(firstOfMonth), to: toStr(now) }
}
const defaultRange = currentMonthRange()
filters.from_date = defaultRange.from
filters.to_date = defaultRange.to

// Agents and locations come from their own endpoints (not derived from
// report results), so the dropdowns are always complete regardless of
// what filters are currently applied.
const agentOptions = ref([])
const areaOptions = ref([])

function toggle(agentId) {
  expanded.value.has(agentId) ? expanded.value.delete(agentId) : expanded.value.add(agentId)
  expanded.value = new Set(expanded.value)
}

function formatMoney(value, currency = 'AED') {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'AED',
    maximumFractionDigits: 0,
  }).format(value)
}

function scoreClass(score) {
  if (score === null || score === undefined) return ''
  if (score >= 70) return 'score-pill--hot'
  if (score >= 40) return 'score-pill--warm'
  return 'score-pill--cold'
}

async function fetchAgents() {
  try {
    const { data } = await axios.get('listings/agents')
    const list = Array.isArray(data) ? data : data.data || []
    agentOptions.value = list.map(a => ({ agent_id: a.id, agent_name: a.name }))
  } catch (e) {
    console.error('Failed to load agents', e)
  }
}

async function fetchAreas() {
  try {
    const { data } = await axios.get('listings/areas')
    areaOptions.value = Array.isArray(data) ? data : data.data || []
  } catch (e) {
    console.error('Failed to load areas', e)
  }
}

async function fetchReport() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/leads/reports/agent-performance', { params: filters })
    agents.value = data.data
    summary.value = data.summary
    // auto-expand the top performer for a useful default view
    if (agents.value.length && expanded.value.size === 0) {
      expanded.value = new Set([agents.value[0].agent_id])
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load the report.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAgents()
  fetchAreas()
  fetchReport()
})
</script>

<style scoped>
.report {
  --ink: #17202a;
  --muted: #6b7785;
  --line: #e6e9ed;
  --accent: #2a6f4b;
  --accent-bg: #e9f4ee;
  --warn-bg: #fff4e5;
  --warn: #b5641a;
  --cold-bg: #eef1f4;
  --cold: #5b6672;
  font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
  color: var(--ink);
  max-width: 1180px;
  margin: 0 auto;
  padding: 28px 20px 60px;
}

.report__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20px;
}
.report__header h1 { margin: 0; font-size: 22px; font-weight: 700; }
.report__sub { margin: 4px 0 0; color: var(--muted); font-size: 13.5px; }

.btn {
  border: 1px solid var(--line);
  background: #fff;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 13.5px;
  cursor: pointer;
}
.btn:disabled { opacity: .6; cursor: default; }
.btn--ghost:hover:not(:disabled) { background: #f7f8f9; }

.filters {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  padding: 14px 16px;
  background: #fafbfc;
  border: 1px solid var(--line);
  border-radius: 10px;
  margin-bottom: 18px;
}
.field { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
.field label { font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
.field input, .field select {
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 7px 8px;
  font-size: 13.5px;
  background: #fff;
}

.summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 22px;
}
.summary__card {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: #fff;
}
.summary__card--accent { background: var(--accent-bg); border-color: #cfe6d9; }
.summary__label { font-size: 12px; color: var(--muted); }
.summary__value { font-size: 20px; font-weight: 700; }

.state {
  padding: 40px 0;
  text-align: center;
  color: var(--muted);
  font-size: 14px;
}
.state--error { color: #b3261e; }

.agents { display: flex; flex-direction: column; gap: 10px; }

.agent-card {
  border: 1px solid var(--line);
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
}
.agent-card__head {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 14px 16px;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
}
.agent-card__who { display: flex; flex-direction: column; min-width: 160px; }
.agent-card__name { font-weight: 700; font-size: 14.5px; }
.agent-card__email { font-size: 12px; color: var(--muted); }

.agent-card__stats { display: flex; gap: 22px; flex: 1; }
.stat { display: flex; flex-direction: column; gap: 2px; }
.stat__label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
.stat__value { font-size: 14.5px; font-weight: 600; }
.stat--accent .stat__value { color: var(--accent); }

.chevron {
  font-size: 18px;
  color: var(--muted);
  transform: rotate(90deg);
  transition: transform .15s ease;
}
.chevron--open { transform: rotate(-90deg); }

.agent-card__body { border-top: 1px solid var(--line); padding: 4px 16px 12px; }

.deals-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.deals-table th {
  text-align: left;
  font-size: 11.5px;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--muted);
  padding: 10px 8px;
  border-bottom: 1px solid var(--line);
}
.deals-table td { padding: 9px 8px; border-bottom: 1px solid #f0f2f4; }
.deals-table .num { text-align: right; }
.num--accent { color: var(--accent); font-weight: 600; }

.score-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  background: var(--cold-bg);
  color: var(--cold);
}
.score-pill--hot { background: var(--accent-bg); color: var(--accent); }
.score-pill--warm { background: var(--warn-bg); color: var(--warn); }

.location-extra {
  display: inline-block;
  margin-left: 4px;
  font-size: 11px;
  color: var(--muted);
}

@media (max-width: 720px) {
  .summary { grid-template-columns: repeat(2, 1fr); }
  .agent-card__head { flex-wrap: wrap; }
  .agent-card__stats { flex-wrap: wrap; gap: 14px; }
}
</style>