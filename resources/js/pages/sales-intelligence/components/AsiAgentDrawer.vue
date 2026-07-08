<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="drawer-backdrop" @click.self="close">
        <aside class="drawer" role="dialog" aria-modal="true" aria-label="Agent intelligence profile">
          <header class="drawer__hero" :class="`drawer__hero--${agent?.status || 'default'}`">
            <button type="button" class="drawer__close" aria-label="Close" @click="close">
              <Icon icon="lucide:x" />
            </button>

            <div v-if="loading" class="drawer__skel">
              <div class="drawer__skel-avatar" />
              <div class="drawer__skel-lines" />
            </div>

            <template v-else-if="agent">
              <div class="drawer__profile">
                <div class="drawer__avatar">{{ initials(agent.user?.name) }}</div>
                <div>
                  <h2>{{ agent.user?.name || `Agent #${agent.user_id}` }}</h2>
                  <p>{{ agent.user?.email }}</p>
                </div>
              </div>

              <div class="drawer__score-row">
                <div class="drawer__score-main">
                  <span class="drawer__score-label">AI Score</span>
                  <span class="drawer__score-num" :class="scoreClass(agent.overall_ai_score)">
                    {{ Math.round(agent.overall_ai_score ?? 0) }}
                  </span>
                </div>
                <span class="drawer__badge" :class="`drawer__badge--${agent.status}`">{{ formatStatus(agent.status) }}</span>
                <span class="drawer__risk" :class="`drawer__risk--${agent.risk_level}`">{{ agent.risk_level }} risk</span>
              </div>

              <div class="drawer__subscores">
                <div v-for="s in subscoreItems" :key="s.key" class="drawer__subscore">
                  <span class="drawer__subscore-label">{{ s.label }}</span>
                  <div class="drawer__subscore-bar">
                    <div class="drawer__subscore-fill" :style="{ width: `${Math.min(100, s.value)}%`, background: s.color }" />
                  </div>
                  <span class="drawer__subscore-val">{{ s.value }}</span>
                </div>
              </div>
            </template>
          </header>

          <div v-if="!loading && agent" class="drawer__body">
            <!-- Executive summary -->
            <section v-if="agent.executive_summary" class="drawer__section drawer__section--highlight">
              <h3><Icon icon="lucide:sparkles" /> Executive summary</h3>
              <p>{{ agent.executive_summary }}</p>
            </section>

            <!-- Coaching -->
            <section v-if="coachingCards.length" class="drawer__section">
              <h3><Icon icon="lucide:lightbulb" /> Coaching</h3>
              <div class="drawer__coaching">
                <article v-for="(card, i) in coachingCards" :key="i" class="drawer__coach" :class="`drawer__coach--${card.priority}`">
                  <h4>{{ card.title }}</h4>
                  <p>{{ card.body }}</p>
                </article>
              </div>
            </section>

            <!-- Observations -->
            <section v-if="observations.length" class="drawer__section">
              <h3><Icon icon="lucide:eye" /> Behavior analysis</h3>
              <ul class="drawer__obs">
                <li v-for="(obs, i) in observations" :key="i" :class="`drawer__obs--${obs.severity}`">
                  {{ obs.observation }}
                </li>
              </ul>
            </section>

            <!-- Metric blocks -->
            <section class="drawer__section">
              <h3><Icon icon="lucide:bar-chart-3" /> Performance metrics</h3>
              <div class="drawer__metrics-grid">
                <div v-for="block in metricBlocks" :key="block.key" class="drawer__metric-card">
                  <h4>{{ block.title }}</h4>
                  <ul>
                    <li v-for="item in block.items" :key="item.label">
                      <span>{{ item.label }}</span>
                      <strong>{{ item.value }}</strong>
                    </li>
                  </ul>
                </div>
              </div>
            </section>

            <!-- Neglected leads -->
            <section v-if="neglectedLeads.length" class="drawer__section">
              <h3><Icon icon="lucide:alert-circle" /> Neglected leads ({{ neglectedLeads.length }})</h3>
              <div class="drawer__leads">
                <div v-for="lead in neglectedLeads.slice(0, 8)" :key="lead.lead_id" class="drawer__lead">
                  <span class="drawer__lead-name">{{ lead.lead_name || lead.lead_number }}</span>
                  <span class="drawer__lead-stage">{{ lead.stage_name }}</span>
                  <span v-for="r in (lead.reasons || [])" :key="r" class="drawer__lead-tag">{{ r }}</span>
                </div>
              </div>
            </section>

            <!-- Weekly chart -->
            <section v-if="weeklyWeeks.length" class="drawer__section">
              <h3><Icon icon="lucide:trending-up" /> Weekly trends</h3>
              <ApexChart type="bar" height="220" :options="chartOptions" :series="chartSeries" />
            </section>
          </div>

          <footer v-if="agent" class="drawer__footer">
            <button type="button" class="drawer__btn drawer__btn--ghost" @click="close">Close</button>
            <button type="button" class="drawer__btn drawer__btn--primary" :disabled="recalculating" @click="$emit('recalculate', agent.user_id)">
              <Icon icon="lucide:refresh-cw" :class="{ 'spin': recalculating }" />
              {{ recalculating ? 'Recalculating…' : 'Recalculate agent' }}
            </button>
          </footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import ApexChart from 'vue3-apexcharts'

const props = defineProps({
  open: Boolean,
  agent: { type: Object, default: null },
  observations: { type: Array, default: () => [] },
  loading: Boolean,
  recalculating: Boolean,
})

const emit = defineEmits(['close', 'recalculate'])

const coachingCards = computed(() => props.agent?.coaching_cards || [])
const neglectedLeads = computed(() => props.agent?.neglect_metrics?.neglected_leads || [])

const SUBSCORE_COLORS = {
  pipeline: '#6366f1',
  response: '#8b5cf6',
  followup: '#a855f7',
  qualification: '#10b981',
  communication: '#06b6d4',
  behavior: '#f59e0b',
  neglect: '#ef4444',
}

const subscoreItems = computed(() => {
  const scores = props.agent?.scores || {}
  const labels = {
    behavior: 'Behavior',
    pipeline: 'Pipeline',
    response: 'Response',
    followup: 'Follow-up',
    qualification: 'Qualification',
    communication: 'Communication',
    neglect: 'Neglect',
  }
  return Object.entries(labels)
    .map(([key, label]) => ({
      key,
      label,
      value: Math.round(Number(scores[key]) || 0),
      color: SUBSCORE_COLORS[key] || '#6366f1',
    }))
    .filter((s) => s.value > 0)
})

const metricBlocks = computed(() => {
  const a = props.agent
  if (!a) return []
  const pm = a.pipeline_metrics || {}
  const rm = a.response_metrics || {}
  const fm = a.followup_metrics || {}
  const qm = a.qualification_metrics || {}
  const cm = a.communication_metrics || {}

  return [
    { key: 'pipeline', title: 'Pipeline', items: [
      { label: 'Assigned', value: pm.assigned_leads ?? 0 },
      { label: 'Active', value: pm.active_leads ?? 0 },
      { label: 'Stuck', value: pm.stuck_leads ?? 0 },
      { label: 'Cleanliness', value: pct(pm.pipeline_cleanliness_score) },
    ]},
    { key: 'response', title: 'Response', items: [
      { label: 'First activity', value: mins(rm.avg_minutes_to_first_activity) },
      { label: 'Not contacted', value: rm.not_contacted_count ?? 0 },
    ]},
    { key: 'followup', title: 'Follow-up', items: [
      { label: 'Overdue', value: fm.overdue_followups ?? 0 },
      { label: 'Completion', value: pct(fm.reminder_completion_rate) },
    ]},
    { key: 'qualification', title: 'Qualification', items: [
      { label: 'Qualified rate', value: pct(qm.qualified_rate) },
      { label: 'To deal', value: pct(qm.qualified_to_deal_rate) },
    ]},
    { key: 'communication', title: 'Communication', items: [
      { label: 'Comments/lead', value: cm.comments_per_lead ?? 0 },
      { label: 'Answered', value: pct(cm.answered_rate) },
    ]},
  ]
})

const weeklyWeeks = computed(() => props.agent?.weekly_trends?.weeks || [])

const chartSeries = computed(() => {
  const weeks = weeklyWeeks.value
  return [
    { name: 'Comments', data: weeks.map((w) => w.comments) },
    { name: 'Activities', data: weeks.map((w) => w.activities) },
    { name: 'Follow-ups', data: weeks.map((w) => w.followups_completed) },
  ]
})

const chartOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'Inter, system-ui, sans-serif' },
  colors: ['#6366f1', '#8b5cf6', '#10b981'],
  dataLabels: { enabled: false },
  xaxis: { categories: weeklyWeeks.value.map((w) => w.week) },
  legend: { position: 'top', fontSize: '11px' },
  grid: { borderColor: '#f3f4f6' },
}))

function close() { emit('close') }
function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()
}
function formatStatus(s) { return (s || '').replace(/_/g, ' ') }
function scoreClass(score) {
  if (score >= 85) return 'drawer__score-num--excellent'
  if (score >= 70) return 'drawer__score-num--good'
  if (score >= 50) return 'drawer__score-num--warn'
  return 'drawer__score-num--critical'
}
function pct(v) { return v == null ? '—' : `${v}%` }
function mins(v) {
  if (v == null) return '—'
  if (v < 60) return `${Math.round(v)}m`
  return `${(v / 60).toFixed(1)}h`
}
</script>

<style scoped>
/* Override global CRM heading scales */
.drawer :is(h1, h2, h3, h4, h5, h6) {
  font-weight: 600 !important;
  line-height: 1.3 !important;
  letter-spacing: normal !important;
  text-transform: none !important;
}

.drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: flex-end;
}

.drawer {
  width: min(520px, 100vw);
  height: 100%;
  background: #fff;
  display: flex;
  flex-direction: column;
  box-shadow: -8px 0 40px rgba(0, 0, 0, 0.12);
  overflow: hidden;
}

.drawer-enter-active,
.drawer-leave-active { transition: opacity 0.2s ease; }
.drawer-enter-active .drawer,
.drawer-leave-active .drawer { transition: transform 0.25s ease; }
.drawer-enter-from,
.drawer-leave-to { opacity: 0; }
.drawer-enter-from .drawer,
.drawer-leave-to .drawer { transform: translateX(100%); }

.drawer__hero {
  position: relative;
  padding: 1.5rem 1.25rem 1.25rem;
  background: linear-gradient(135deg, #312e81 0%, #4f46e5 100%);
  color: #fff;
}

.drawer__hero--excellent { background: linear-gradient(135deg, #065f46, #10b981); }
.drawer__hero--good { background: linear-gradient(135deg, #1e40af, #3b82f6); }
.drawer__hero--needs_attention { background: linear-gradient(135deg, #b45309, #f59e0b); }
.drawer__hero--critical { background: linear-gradient(135deg, #991b1b, #ef4444); }

.drawer__close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  cursor: pointer;
  display: grid;
  place-items: center;
  font-size: 1.1rem;
}

.drawer__close:hover { background: rgba(255, 255, 255, 0.25); }

.drawer__profile {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-bottom: 1.25rem;
}

.drawer__avatar {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.2);
  display: grid;
  place-items: center;
  font-size: 1rem;
  font-weight: 700;
  flex-shrink: 0;
}

.drawer__profile h2 {
  margin: 0 !important;
  font-size: 0.95rem !important;
  font-weight: 600 !important;
  color: #ffffff !important;
}

.drawer__profile p {
  margin: 0.15rem 0 0;
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.88) !important;
}

.drawer__score-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.drawer__score-main { display: flex; flex-direction: column; }
.drawer__score-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.8; }
.drawer__score-num { font-size: 1.5rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; color: #fff; }

.drawer__badge {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
  text-transform: capitalize;
  background: rgba(255, 255, 255, 0.2);
}

.drawer__risk {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: capitalize;
  margin-left: auto;
}

.drawer__risk--low { color: #a7f3d0; }
.drawer__risk--medium { color: #fde68a; }
.drawer__risk--high { color: #fecaca; }

.drawer__subscores {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.drawer__subscore {
  display: grid;
  grid-template-columns: 80px 1fr 32px;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.72rem;
}

.drawer__subscore-label { opacity: 0.9; }
.drawer__subscore-bar {
  height: 6px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 999px;
  overflow: hidden;
}

.drawer__subscore-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.drawer__subscore-val {
  text-align: right;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.drawer__body {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 1.25rem;
}

.drawer__section {
  margin-bottom: 1.25rem;
}

.drawer__section h3 {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0 0.65rem !important;
  font-size: 0.8125rem !important;
  font-weight: 600 !important;
  color: #111827 !important;
}

.drawer__section--highlight {
  background: linear-gradient(135deg, #eef2ff, #f5f3ff);
  border-radius: 12px;
  padding: 1rem;
  border: 1px solid #e0e7ff;
}

.drawer__section--highlight p {
  margin: 0;
  font-size: 0.82rem;
  line-height: 1.55;
  color: #374151;
}

.drawer__coaching { display: flex; flex-direction: column; gap: 0.5rem; }

.drawer__coach {
  border-radius: 10px;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  background: #fafafa;
}

.drawer__coach h4 { margin: 0 0 0.25rem !important; font-size: 0.78rem !important; font-weight: 600 !important; color: #111827 !important; }
.drawer__coach p { margin: 0; font-size: 0.78rem; color: #6b7280; line-height: 1.4; }
.drawer__coach--critical { border-left: 3px solid #ef4444; }
.drawer__coach--high { border-left: 3px solid #f59e0b; }

.drawer__obs {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.drawer__obs li {
  padding: 0.55rem 0.75rem;
  border-radius: 8px;
  font-size: 0.8rem;
  background: #f9fafb;
  border: 1px solid #f3f4f6;
}

.drawer__obs--warning { background: #fffbeb; border-color: #fde68a; }
.drawer__obs--critical { background: #fef2f2; border-color: #fecaca; }

.drawer__metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 0.65rem;
}

.drawer__metric-card {
  background: #f9fafb;
  border-radius: 10px;
  padding: 0.65rem 0.75rem;
  border: 1px solid #f3f4f6;
}

.drawer__metric-card h4 {
  margin: 0 0 0.4rem !important;
  font-size: 0.68rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280 !important;
}

.drawer__metric-card ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

.drawer__metric-card li {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  padding: 0.2rem 0;
  color: #6b7280;
}

.drawer__metric-card strong { color: #111827; font-weight: 600; }

.drawer__leads { display: flex; flex-direction: column; gap: 0.4rem; }

.drawer__lead {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  padding: 0.5rem 0.65rem;
  background: #fef2f2;
  border-radius: 8px;
  border: 1px solid #fecaca;
  font-size: 0.78rem;
}

.drawer__lead-name { font-weight: 600; color: #111827; }
.drawer__lead-stage { color: #6b7280; font-size: 0.72rem; }
.drawer__lead-tag {
  font-size: 0.65rem;
  padding: 0.1rem 0.35rem;
  background: rgba(239, 68, 68, 0.15);
  border-radius: 4px;
  color: #b91c1c;
}

.drawer__footer {
  display: flex;
  gap: 0.5rem;
  padding: 1rem 1.25rem;
  border-top: 1px solid #f3f4f6;
  background: #fafafa;
}

.drawer__btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 0.6rem 1rem;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.drawer__btn--ghost {
  background: #fff;
  border: 1px solid #e5e7eb;
  color: #374151;
}

.drawer__btn--primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
}

.drawer__btn:disabled { opacity: 0.6; cursor: not-allowed; }

.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.drawer__skel { display: flex; gap: 1rem; }
.drawer__skel-avatar {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: rgba(255,255,255,0.2);
  animation: pulse 1.2s ease infinite;
}
.drawer__skel-lines {
  flex: 1;
  height: 48px;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
  animation: pulse 1.2s ease infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>
