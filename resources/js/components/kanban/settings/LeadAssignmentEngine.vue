<template>
  <div class="lae-page">
    <div class="lae-header">
      <div>
        <h6 class="ui-h-page">Lead Assignment Engine</h6>
        <p class="lae-header-tagline">
          Simple mode uses today’s attendance only (earliest check-in first, round-robin). Advanced AI remains available below.
        </p>
      </div>
      <div class="lae-header-actions">
        <button type="button" class="btn btn-light" :disabled="loading || saving" @click="reloadAll">Refresh</button>
        <button type="button" class="btn btn-dark" :disabled="saving" @click="saveSettings">{{ saving ? 'Saving…' : 'Save' }}</button>
      </div>
    </div>

    <div v-if="loading" class="lae-skeleton-wrap">
      <div v-for="n in 6" :key="n" class="lae-skel-line" />
    </div>

    <div v-else class="lae-layout">
      <section class="lae-panel lae-panel--simple lae-span2">
        <h6 class="ui-h-section">Assignment control</h6>
        <p class="lae-desc">Master switch for automatic assignment from the New stage. When off, the worker stops on the next tick.</p>
        <div class="lae-simple-toolbar">
          <button
            type="button"
            class="lae-toggle lae-toggle--accent lae-toggle--btn"
            :class="{ 'is-on-active': form.auto_assign && !form.system_disabled }"
            :aria-pressed="form.auto_assign ? 'true' : 'false'"
            @click="form.auto_assign = !form.auto_assign"
          >
            <span class="lae-switch-track" :class="{ on: form.auto_assign }" aria-hidden="true">
              <span class="lae-switch-thumb" />
            </span>
            <span>Auto assign</span>
          </button>
          <span class="lae-badge lae-badge--lifecycle" :class="mainLifecycleBadgeClass">{{ mainLifecycleLabel }}</span>
        </div>
        <p class="lae-desc lae-save-hint">Turn switches on/off, then click <b>Save</b> (top right) to apply on the server.</p>
        <div class="lae-grid4 lae-basic-grid">
          <button
            type="button"
            class="lae-toggle lae-toggle--btn"
            :class="{ 'is-on-active': form.simple_mode_enabled }"
            :aria-pressed="form.simple_mode_enabled ? 'true' : 'false'"
            @click="form.simple_mode_enabled = !form.simple_mode_enabled"
          >
            <span class="lae-switch-track" :class="{ on: form.simple_mode_enabled }" aria-hidden="true">
              <span class="lae-switch-thumb" />
            </span>
            <span>Use attendance only (simple)</span>
          </button>
          <button
            type="button"
            class="lae-toggle lae-toggle--btn"
            :class="{ 'is-on-active': form.realtime_assignment_enabled }"
            :aria-pressed="form.realtime_assignment_enabled ? 'true' : 'false'"
            @click="form.realtime_assignment_enabled = !form.realtime_assignment_enabled"
          >
            <span class="lae-switch-track" :class="{ on: form.realtime_assignment_enabled }" aria-hidden="true">
              <span class="lae-switch-thumb" />
            </span>
            <span>Realtime loop</span>
          </button>
          <label class="lae-field">
            <span>Simple batch size (per tick)</span>
            <input v-model.number="form.simple_mode_batch_size" type="number" min="1" max="500" class="lae-input" />
          </label>
          <label class="lae-field">
            <span>Simple auto interval (seconds)</span>
            <input v-model.number="form.simple_mode_auto_interval_seconds" type="number" min="2" max="300" class="lae-input" />
          </label>
        </div>
        <label class="lae-toggle danger">
          <input v-model="form.system_disabled" type="checkbox" />
          <span>Disable entire system</span>
        </label>
        <p v-if="!form.simple_mode_enabled && form.realtime_assignment_enabled" class="lae-desc lae-realtime-hint">
          AI realtime: adaptive intervals (queue &gt; 50 → 1s, &lt; 10 → 5s). Open <b>Advanced</b> for weights and exploration.
        </p>
        <div v-if="form.simple_mode_enabled && form.auto_assign && !form.system_disabled" class="lae-realtime-health">
          <h6 class="ui-h-section lae-subhead">Worker metrics</h6>
          <div class="lae-health-grid">
            <div class="lae-health-card">
              <span class="lae-health-label">Tick status</span>
              <span class="lae-health-val" :class="realtimeStatusClass">{{ realtimeStatusText }}</span>
            </div>
            <div class="lae-health-card">
              <span class="lae-health-label">Leads / sec (last tick)</span>
              <span class="lae-health-val">{{ realtimeLeadsPerSecDisplay }}</span>
            </div>
            <div class="lae-health-card">
              <span class="lae-health-label">Present (engine)</span>
              <span class="lae-health-val">{{ form.realtime_active_sales_count ?? 0 }}</span>
            </div>
            <div class="lae-health-card">
              <span class="lae-health-label">Last sleep</span>
              <span class="lae-health-val">{{
                form.realtime_last_interval_applied != null ? form.realtime_last_interval_applied + 's' : '—'
              }}</span>
            </div>
          </div>
        </div>
      </section>

      <section class="lae-panel lae-span2 lae-live-section">
        <h6 class="ui-h-section">Live status</h6>
        <div class="lae-live-grid">
          <div class="lae-live-block">
            <h6 class="ui-h-section lae-mini-h">Present sales (check-in order)</h6>
            <div v-if="attLoading" class="lae-mini-skel" />
            <ul v-else class="lae-live-list">
              <li v-for="(row, idx) in presentSalesRows" :key="row.key">
                <span class="lae-live-ord">{{ idx + 1 }}.</span>
                <span class="lae-live-name">{{ row.employee_name || row.name || '—' }}</span>
                <span class="lae-live-time">{{ formatDt(row.check_in) }}</span>
              </li>
              <li v-if="!presentSalesRows.length" class="lae-empty">No present attendees with check-in yet.</li>
            </ul>
          </div>
          <div class="lae-live-block lae-live-queue">
            <h6 class="ui-h-section lae-mini-h">New queue</h6>
            <div v-if="queueLoading" class="lae-mini-skel" />
            <p v-else class="lae-big-num">{{ queue.length }}</p>
            <p class="lae-desc">leads in New stage</p>
          </div>
          <div class="lae-live-block">
            <h6 class="ui-h-section lae-mini-h">Last assignment</h6>
            <p class="lae-last-assign">{{ form.simple_last_assignment_label || '—' }}</p>
          </div>
        </div>
      </section>

      <details class="lae-details lae-span2">
        <summary class="lae-details-summary">
          <h6 class="ui-h-section">Scheduling &amp; routing</h6>
        </summary>
        <div class="lae-details-body">
          <div class="lae-grid2">
            <label class="lae-field">
              <span>Mode</span>
              <SearchableSelect
                v-model="form.mode"
                preset="leadAssignmentMode"
                option-label="label"
                option-value="value"
                :clearable="false"
                inline
                class="lae-input"
                placeholder="Mode"
              />
            </label>
            <label class="lae-field">
              <span>Strategy</span>
              <SearchableSelect
                v-model="form.strategy"
                preset="leadAssignmentStrategy"
                option-label="label"
                option-value="value"
                :clearable="false"
                inline
                class="lae-input"
                placeholder="Strategy"
              />
            </label>
            <label class="lae-field">
              <span>Max leads / user</span>
              <input v-model.number="form.max_leads_per_user" type="number" min="1" max="500" class="lae-input" />
            </label>
            <label class="lae-toggle" :class="{ 'is-on-active': form.require_attendance }">
              <input v-model="form.require_attendance" type="checkbox" />
              <span>Require clock-in</span>
            </label>
          </div>
          <h6 class="ui-h-section lae-subhead">Scheduler</h6>
          <p class="lae-desc">When mode is Scheduled, runs at these times (server timezone from working hours).</p>
          <div class="lae-schedule-list">
            <div v-for="(t, idx) in form.schedule_times" :key="idx" class="lae-schedule-row">
              <input v-model="form.schedule_times[idx]" type="time" class="lae-input" />
              <button type="button" class="btn btn-light" @click="removeTime(idx)">Remove</button>
            </div>
            <button type="button" class="btn btn-light" @click="addTime">Add time</button>
          </div>
          <div class="lae-grid3">
            <label class="lae-field">
              <span>Work start</span>
              <input v-model="form.working_hours.start" type="time" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Work end</span>
              <input v-model="form.working_hours.end" type="time" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Timezone</span>
              <input v-model="form.working_hours.timezone" type="text" class="lae-input" />
            </label>
          </div>
        </div>
      </details>

      <details class="lae-details lae-span2">
        <summary class="lae-details-summary">
          <h6 class="ui-h-section">Fairness, SLA &amp; recovery</h6>
        </summary>
        <div class="lae-details-body">
          <div class="lae-grid4">
            <label class="lae-toggle" :class="{ 'is-on-active': form.stuck_recovery_enabled }">
              <input v-model="form.stuck_recovery_enabled" type="checkbox" />
              <span>Stuck recovery</span>
            </label>
            <label class="lae-field">
              <span>Stuck after (minutes)</span>
              <input v-model.number="form.stuck_lead_minutes" type="number" min="15" max="10080" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Assign cooldown (minutes)</span>
              <input v-model.number="form.assign_cooldown_minutes" type="number" min="1" max="1440" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>High priority score ≥</span>
              <input v-model.number="form.high_priority_score_threshold" type="number" min="1" max="100" class="lae-input" />
            </label>
            <label class="lae-toggle" :class="{ 'is-on-active': form.sla_escalation_enabled }">
              <input v-model="form.sla_escalation_enabled" type="checkbox" />
              <span>SLA escalation</span>
            </label>
            <label class="lae-field">
              <span>SLA minutes (0 = off)</span>
              <input v-model.number="form.sla_minutes" type="number" min="0" max="10080" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Fallback sales user ID</span>
              <input v-model.number="form.fallback_user_id" type="number" min="0" class="lae-input" placeholder="optional" />
            </label>
          </div>
        </div>
      </details>

      <details class="lae-details lae-span2">
        <summary class="lae-details-summary">
          <h6 class="ui-h-section">Advanced AI — weights &amp; learning</h6>
        </summary>
        <div class="lae-details-body">
          <p class="lae-desc">
            Used when <b>Use attendance only</b> is off. Includes hybrid weights, ε exploration, cold-start, and adaptive factor tuning.
          </p>
          <div class="lae-grid4">
            <label class="lae-field"><span>Attendance weight</span><input v-model.number="form.weight_attendance" step="0.05" type="number" class="lae-input" /></label>
            <label class="lae-field"><span>Performance</span><input v-model.number="form.weight_performance" step="0.05" type="number" class="lae-input" /></label>
            <label class="lae-field"><span>Availability</span><input v-model.number="form.weight_availability" step="0.05" type="number" class="lae-input" /></label>
            <label class="lae-field"><span>Fairness</span><input v-model.number="form.weight_fairness" step="0.05" type="number" class="lae-input" /></label>
          </div>
          <h6 class="ui-h-section lae-subhead">Exploration &amp; adaptive factors</h6>
          <div class="lae-grid4">
            <label class="lae-field">
              <span>Exploration ε (0–0.45)</span>
              <input v-model.number="form.exploration_epsilon" type="number" min="0" max="0.45" step="0.01" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Cold-start max samples</span>
              <input v-model.number="form.cold_start_max_samples" type="number" min="1" max="100" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Cold explore ratio</span>
              <input v-model.number="form.cold_start_explore_ratio" type="number" min="0" max="0.5" step="0.01" class="lae-input" />
            </label>
            <label class="lae-toggle" :class="{ 'is-on-active': form.adaptive_weights_enabled }">
              <input v-model="form.adaptive_weights_enabled" type="checkbox" />
              <span>Adaptive factor weights</span>
            </label>
            <label class="lae-field">
              <span>Factor · attendance</span>
              <input v-model.number="form.factor_weight_attendance" type="number" min="0.12" max="0.55" step="0.01" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Factor · performance</span>
              <input v-model.number="form.factor_weight_performance" type="number" min="0.12" max="0.55" step="0.01" class="lae-input" />
            </label>
            <label class="lae-field">
              <span>Factor · skill</span>
              <input v-model.number="form.factor_weight_skill" type="number" min="0.12" max="0.55" step="0.01" class="lae-input" />
            </label>
          </div>
        </div>
      </details>

      <section class="lae-panel lae-span2">
        <h6 class="ui-h-section">Actions &amp; tools</h6>
        <div class="lae-actions-row">
          <button type="button" class="btn btn-dark" :disabled="running" @click="runNow">{{ running ? 'Running…' : 'Run now' }}</button>
          <div class="lae-reassign">
            <input v-model.number="reassignLeadId" type="number" class="lae-input sm" placeholder="Lead ID" />
            <button type="button" class="btn btn-light" :disabled="reassigning" @click="reassign">Reassign</button>
          </div>
        </div>
        <div class="lae-actions-row lae-tools-row">
          <div class="lae-reassign">
            <span class="lae-tool-label">Simulate</span>
            <input v-model.number="simulateLeadId" type="number" class="lae-input sm" placeholder="Lead ID" />
            <button type="button" class="btn btn-light" :disabled="simulating" @click="runSimulate">{{ simulating ? '…' : 'Run' }}</button>
          </div>
          <div class="lae-reassign">
            <span class="lae-tool-label">Admin override</span>
            <input v-model.number="overrideLeadId" type="number" class="lae-input sm" placeholder="Lead" />
            <input v-model.number="overrideUserId" type="number" class="lae-input sm" placeholder="Sales user" />
            <button type="button" class="btn btn-light" :disabled="overriding" @click="runOverride">{{ overriding ? '…' : 'Apply' }}</button>
          </div>
          <div class="lae-reassign">
            <span class="lae-tool-label">Revert stage assignments</span>
            <select v-model.number="revertStageId" class="lae-input sm">
              <option :value="null">Use assigned stage</option>
              <option v-for="st in stages" :key="st.id" :value="Number(st.id)">
                {{ st.name }} (#{{ st.id }})
              </option>
            </select>
            <button type="button" class="btn btn-light" :disabled="revertingStage" @click="runRevertStageAssignments">
              {{ revertingStage ? 'Reverting…' : 'Revert Stage Assignments' }}
            </button>
          </div>
        </div>
        <pre v-if="simulateResult" class="lae-json">{{ simulateResult }}</pre>
      </section>

      <section class="lae-panel">
        <h6 class="ui-h-section">Today’s attendance</h6>
        <p class="lae-desc">Pulled from the HR attendance API (same source as the HR dashboard).</p>
        <div v-if="attLoading" class="lae-mini-skel" />
        <div v-else class="lae-table-wrap">
          <table class="lae-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Check-in</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in attendanceRows" :key="row.employee_id + String(row.check_in)">
                <td>{{ row.employee_name }}</td>
                <td><span class="lae-pill" :class="statusClass(row.status)">{{ row.status || '—' }}</span></td>
                <td>{{ formatDt(row.check_in) }}</td>
              </tr>
              <tr v-if="!attendanceRows.length">
                <td colspan="3" class="lae-empty">No rows for today.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="lae-panel">
        <h6 class="ui-h-section">New leads queue</h6>
        <p class="lae-desc">Leads currently in the first “New” stage.</p>
        <div v-if="queueLoading" class="lae-mini-skel" />
        <div v-else class="lae-table-wrap">
          <table class="lae-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Lead</th>
                <th>Score</th>
                <th>Responsible</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="q in queue" :key="q.id">
                <td>{{ q.lead_number }}</td>
                <td>{{ q.lead_name }}</td>
                <td>{{ q.computed_assignment_score }}</td>
                <td>{{ q.responsible || '—' }}</td>
                <td>{{ formatDt(q.created_at) }}</td>
              </tr>
              <tr v-if="!queue.length">
                <td colspan="5" class="lae-empty">No leads waiting in New.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="statsData" class="lae-panel lae-span2">
        <h6 class="ui-h-section">Monitoring (today)</h6>
        <p class="lae-desc">From <code>GET /api/lead-assignment/stats</code> — date {{ statsData.date }} ({{ statsData.timezone }}).</p>
        <div class="lae-stats-grid">
          <div class="lae-stat-card">
            <span class="lae-stat-label">Assigned today</span>
            <span class="lae-stat-val">{{ statsData.total_assigned_today }}</span>
          </div>
          <div class="lae-stat-card">
            <span class="lae-stat-label">Avg minutes to assign</span>
            <span class="lae-stat-val">{{ statsData.avg_minutes_lead_create_to_assignment ?? '—' }}</span>
          </div>
        </div>
        <div class="lae-stats-cols">
          <div>
            <h6 class="ui-h-section lae-mini-h">Top sales</h6>
            <ul class="lae-stat-list">
              <li v-for="r in statsData.top_sales_today" :key="r.user_id">{{ r.name || r.user_id }} — {{ r.assignments_today }}</li>
              <li v-if="!statsData.top_sales_today?.length" class="lae-empty">No assignments yet.</li>
            </ul>
          </div>
          <div>
            <h6 class="ui-h-section lae-mini-h">Load (open leads)</h6>
            <ul class="lae-stat-list">
              <li v-for="r in statsData.load_distribution" :key="r.user_id">{{ r.name || r.user_id }} — {{ r.open_leads }}</li>
            </ul>
          </div>
        </div>
      </section>

      <section v-if="insightsData" class="lae-panel lae-span2">
        <h6 class="ui-h-section">AI insights</h6>
        <p class="lae-desc">From <code>GET /api/lead-assignment/insights</code> · {{ insightsData.generated_at }}</p>
        <div class="lae-stats-cols">
          <div>
            <h6 class="ui-h-section lae-mini-h">Best sales by context (sample)</h6>
            <ul class="lae-stat-list lae-compact">
              <li v-for="r in insightsData.best_sales_by_context" :key="r.context_fingerprint">
                {{ r.context_source || '—' }} → {{ r.best_sales_name || r.best_sales_id }} ({{ (r.success_rate * 100).toFixed(1) }}%, n={{ r.samples }})
              </li>
              <li v-if="!insightsData.best_sales_by_context?.length" class="lae-empty">Not enough pattern data yet.</li>
            </ul>
          </div>
          <div>
            <h6 class="ui-h-section lae-mini-h">Strongest assignment hours (win rate)</h6>
            <p class="lae-insight-hours">{{ insightsData.best_assignment_hours?.join(', ') || '—' }}</p>
            <h6 class="ui-h-section lae-mini-h">14-day trend (last days)</h6>
            <ul class="lae-stat-list lae-compact">
              <li v-for="t in insightsData.conversion_trends_14d?.slice(-5)" :key="t.date">
                {{ t.date }}: won {{ t.deals_won }}, lost {{ t.deals_lost }}, assigns {{ t.assignments_logged }}
              </li>
            </ul>
          </div>
        </div>
      </section>

      <section class="lae-panel lae-span2">
        <h6 class="ui-h-section">Assignment logs</h6>
        <p class="lae-desc">History of engine decisions including composite score and reason string.</p>
        <div v-if="logsLoading" class="lae-mini-skel" />
        <div v-else class="lae-table-wrap">
          <table class="lae-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Lead</th>
                <th>Assigned to</th>
                <th>Final</th>
                <th>Att</th>
                <th>Perf</th>
                <th>Load</th>
                <th>Fair</th>
                <th>P(close)</th>
                <th>Explore</th>
                <th>Method</th>
                <th>Why</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td>{{ formatDt(log.created_at) }}</td>
                <td>{{ log.lead?.lead_number }} — {{ log.lead?.lead_name }}</td>
                <td>{{ log.assignee?.name }}</td>
                <td>{{ fmt4(log.score_used) }}</td>
                <td>{{ fmt4(log.attendance_score) }}</td>
                <td>{{ fmt4(log.performance_score) }}</td>
                <td>{{ fmt4(log.load_score) }}</td>
                <td>{{ fmt4(log.fairness_score) }}</td>
                <td>{{ log.probability_of_close != null ? Number(log.probability_of_close).toFixed(3) : '—' }}</td>
                <td>{{ log.was_exploration ? 'yes' : '—' }}</td>
                <td>{{ log.method }}</td>
                <td class="lae-reason">{{ explainSummary(log) }}</td>
              </tr>
              <tr v-if="!logs.length">
                <td colspan="12" class="lae-empty">No logs yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import api from '@/plugins/axios'

const loading = ref(true)
const saving = ref(false)
const running = ref(false)
const reassigning = ref(false)
const simulating = ref(false)
const overriding = ref(false)
const revertingStage = ref(false)
const attLoading = ref(true)
const queueLoading = ref(true)
const logsLoading = ref(true)
const reassignLeadId = ref(null)
const simulateLeadId = ref(null)
const simulateResult = ref('')
const overrideLeadId = ref(null)
const overrideUserId = ref(null)
const stages = ref([])
const revertStageId = ref(null)

const queue = ref([])
const logs = ref([])
const attendanceRows = ref([])
const statsData = ref(null)
const insightsData = ref(null)

const form = reactive({
  auto_assign: false,
  system_disabled: false,
  mode: 'manual',
  strategy: 'ai_hybrid',
  schedule_times: ['09:30', '14:00'],
  max_leads_per_user: 25,
  working_hours: { start: '09:00', end: '18:00', timezone: 'Asia/Dubai' },
  weight_attendance: 0.35,
  weight_performance: 0.3,
  weight_availability: 0.2,
  weight_fairness: 0.15,
  require_attendance: true,
  assigned_stage_id: null,
  stuck_recovery_enabled: false,
  stuck_lead_minutes: 120,
  assign_cooldown_minutes: 10,
  high_priority_score_threshold: 70,
  sla_minutes: 1440,
  sla_escalation_enabled: true,
  fallback_user_id: null,
  exploration_epsilon: 0.1,
  cold_start_max_samples: 8,
  cold_start_explore_ratio: 0.15,
  adaptive_weights_enabled: true,
  factor_weight_attendance: 0.3333,
  factor_weight_performance: 0.3333,
  factor_weight_skill: 0.3334,
  realtime_assignment_enabled: false,
  realtime_interval_seconds: 3,
  realtime_status: 'stopped',
  realtime_last_run_at: null,
  realtime_last_tick_assigned: 0,
  realtime_last_tick_duration_ms: null,
  realtime_last_queue_depth: 0,
  realtime_active_sales_count: 0,
  realtime_last_interval_applied: null,
  simple_mode_enabled: true,
  simple_mode_batch_size: 25,
  simple_mode_auto_interval_seconds: 10,
  simple_last_assignment_label: null,
})

/** RUNNING when auto + realtime worker enabled and system on */
const mainLifecycleLabel = computed(() => {
  if (form.system_disabled) return 'DISABLED'
  if (!form.auto_assign) return 'STOPPED'
  if (form.auto_assign && (form.simple_mode_enabled || form.realtime_assignment_enabled)) return 'RUNNING'
  return 'STOPPED'
})

const mainLifecycleBadgeClass = computed(() => {
  if (form.system_disabled) return 'is-off'
  if (form.auto_assign && (form.simple_mode_enabled || form.realtime_assignment_enabled) && !form.system_disabled) return 'is-running'
  return 'is-stopped'
})

const presentSalesRows = computed(() => {
  const rows = attendanceRows.value || []
  const filtered = rows.filter((r) => String(r.status || '').toLowerCase() === 'present')
  return [...filtered].sort((a, b) => {
    const ta = a.check_in ? new Date(a.check_in).getTime() : 0
    const tb = b.check_in ? new Date(b.check_in).getTime() : 0
    return ta - tb
  }).map((r, i) => ({
    ...r,
    key: `${r.employee_id || i}-${r.check_in || ''}`,
  }))
})

const realtimeStatusText = computed(() => {
  if (form.system_disabled) return 'Stopped'
  if (!form.realtime_assignment_enabled) return 'Stopped'
  const s = String(form.realtime_status || 'idle').toLowerCase()
  if (s === 'high_load') return 'High load'
  if (s === 'running') return 'Running'
  if (s === 'idle') return 'Idle'
  if (s === 'stopped') return 'Stopped'
  return s
})

const realtimeStatusClass = computed(() => {
  const s = String(form.realtime_status || '').toLowerCase()
  if (s === 'high_load') return 'is-warn'
  if (s === 'running') return 'is-ok'
  if (s === 'idle') return 'is-muted'
  return ''
})

const realtimeLeadsPerSecDisplay = computed(() => {
  const ms = Number(form.realtime_last_tick_duration_ms)
  const n = Number(form.realtime_last_tick_assigned)
  if (!ms || ms <= 0 || !n || Number.isNaN(n)) return '—'
  const v = (n * 1000) / ms
  if (!Number.isFinite(v)) return '—'
  return v.toFixed(2)
})

const formatLastRunAt = (v) => {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleString()
  } catch {
    return String(v)
  }
}

let pollTimer = null
let realtimeHealthTimer = null
let echoChannel = null
let echoLaChannel = null

const notify = (msg, type = 'success') => {
  window.$showNotification?.(msg, type)
}

const mapSettings = (row) => {
  if (!row) return
  form.auto_assign = !!row.auto_assign
  form.system_disabled = !!row.system_disabled
  form.mode = row.mode || 'manual'
  form.strategy = row.strategy || 'ai_hybrid'
  form.schedule_times = Array.isArray(row.schedule_times) && row.schedule_times.length ? row.schedule_times.map((t) => String(t).slice(0, 5)) : ['09:30']
  form.max_leads_per_user = row.max_leads_per_user ?? 25
  form.working_hours = {
    start: row.working_hours?.start || '09:00',
    end: row.working_hours?.end || '18:00',
    timezone: row.working_hours?.timezone || 'Asia/Dubai',
  }
  form.weight_attendance = Number(row.weight_attendance ?? 0.35)
  form.weight_performance = Number(row.weight_performance ?? 0.3)
  form.weight_availability = Number(row.weight_availability ?? 0.2)
  form.weight_fairness = Number(row.weight_fairness ?? 0.15)
  form.require_attendance = row.require_attendance !== false
  form.assigned_stage_id = row.assigned_stage_id ?? null
  form.stuck_recovery_enabled = !!row.stuck_recovery_enabled
  form.stuck_lead_minutes = row.stuck_lead_minutes ?? 120
  form.assign_cooldown_minutes = row.assign_cooldown_minutes ?? 10
  form.high_priority_score_threshold = row.high_priority_score_threshold ?? 70
  form.sla_minutes = row.sla_minutes ?? 1440
  form.sla_escalation_enabled = row.sla_escalation_enabled !== false
  form.fallback_user_id = row.fallback_user_id ?? null
  form.exploration_epsilon = row.exploration_epsilon != null ? Number(row.exploration_epsilon) : 0.1
  form.cold_start_max_samples = row.cold_start_max_samples ?? 8
  form.cold_start_explore_ratio = row.cold_start_explore_ratio != null ? Number(row.cold_start_explore_ratio) : 0.15
  form.adaptive_weights_enabled = row.adaptive_weights_enabled !== false
  form.factor_weight_attendance = Number(row.factor_weight_attendance ?? 0.3333)
  form.factor_weight_performance = Number(row.factor_weight_performance ?? 0.3333)
  form.factor_weight_skill = Number(row.factor_weight_skill ?? 0.3334)
  form.realtime_assignment_enabled = !!row.realtime_assignment_enabled
  form.realtime_interval_seconds = row.realtime_interval_seconds != null ? Number(row.realtime_interval_seconds) : 3
  form.realtime_status = row.realtime_status || 'stopped'
  form.realtime_last_run_at = row.realtime_last_run_at || null
  form.realtime_last_tick_assigned = row.realtime_last_tick_assigned ?? 0
  form.realtime_last_tick_duration_ms = row.realtime_last_tick_duration_ms ?? null
  form.realtime_last_queue_depth = row.realtime_last_queue_depth ?? 0
  form.realtime_active_sales_count = row.realtime_active_sales_count ?? 0
  form.realtime_last_interval_applied =
    row.realtime_last_interval_applied != null ? Number(row.realtime_last_interval_applied) : null
  form.simple_mode_enabled = row.simple_mode_enabled !== false
  form.simple_mode_batch_size = row.simple_mode_batch_size != null ? Number(row.simple_mode_batch_size) : 25
  form.simple_mode_auto_interval_seconds =
    row.simple_mode_auto_interval_seconds != null ? Number(row.simple_mode_auto_interval_seconds) : 10
  form.simple_last_assignment_label = row.simple_last_assignment_label ?? null
}

const loadSettings = async () => {
  const res = await api.get('/lead-assignment/settings')
  mapSettings(res?.data?.data)
}

/** Refresh worker + last simple assignment (polling) without clobbering the whole form. */
const patchLiveHealth = (row) => {
  if (!row) return
  form.realtime_status = row.realtime_status || form.realtime_status
  form.realtime_last_run_at = row.realtime_last_run_at
  form.realtime_last_tick_assigned = row.realtime_last_tick_assigned ?? 0
  form.realtime_last_tick_duration_ms = row.realtime_last_tick_duration_ms
  form.realtime_last_queue_depth = row.realtime_last_queue_depth ?? 0
  form.realtime_active_sales_count = row.realtime_active_sales_count ?? 0
  form.realtime_last_interval_applied =
    row.realtime_last_interval_applied != null ? Number(row.realtime_last_interval_applied) : null
  form.simple_last_assignment_label = row.simple_last_assignment_label ?? form.simple_last_assignment_label
}

const fetchLiveHealth = async () => {
  if (!form.auto_assign || (!form.simple_mode_enabled && !form.realtime_assignment_enabled) || form.system_disabled || saving.value) return
  try {
    const res = await api.get('/lead-assignment/settings')
    patchLiveHealth(res?.data?.data)
  } catch {
    /* ignore */
  }
}

const setupLivePoll = () => {
  if (realtimeHealthTimer) {
    clearInterval(realtimeHealthTimer)
    realtimeHealthTimer = null
  }
  if (!form.auto_assign || (!form.simple_mode_enabled && !form.realtime_assignment_enabled) || form.system_disabled) return
  fetchLiveHealth()
  realtimeHealthTimer = setInterval(fetchLiveHealth, 4000)
}

const loadQueue = async () => {
  queueLoading.value = true
  try {
    const res = await api.get('/lead-assignment/queue')
    queue.value = res?.data?.data || []
  } finally {
    queueLoading.value = false
  }
}

const loadLogs = async () => {
  logsLoading.value = true
  try {
    const res = await api.get('/lead-assignment/logs?per_page=30')
    logs.value = res?.data?.data?.data || []
  } finally {
    logsLoading.value = false
  }
}

const loadStats = async () => {
  try {
    const res = await api.get('/lead-assignment/stats')
    statsData.value = res?.data?.data || null
  } catch {
    statsData.value = null
  }
}

const loadInsights = async () => {
  try {
    const res = await api.get('/lead-assignment/insights')
    insightsData.value = res?.data?.data || null
  } catch {
    insightsData.value = null
  }
}

const loadStages = async () => {
  try {
    const res = await api.get('/stages', { params: { stage_type: 'lead' } })
    const rows = res?.data?.data?.data || res?.data?.data || []
    stages.value = Array.isArray(rows) ? rows : []
    if (!revertStageId.value && form.assigned_stage_id) {
      revertStageId.value = Number(form.assigned_stage_id)
    }
  } catch {
    stages.value = []
  }
}

const loadAttendance = async () => {
  attLoading.value = true
  try {
    const res = await api.get('/attendance/today')
    const emps = res?.data?.data?.employees || []
    attendanceRows.value = [...emps].sort((a, b) => {
      const ta = a.check_in ? new Date(a.check_in).getTime() : 0
      const tb = b.check_in ? new Date(b.check_in).getTime() : 0
      return ta - tb
    })
  } catch {
    attendanceRows.value = []
  } finally {
    attLoading.value = false
  }
}

const reloadAll = async () => {
  loading.value = true
  try {
    await Promise.all([loadSettings(), loadQueue(), loadLogs(), loadAttendance(), loadStats(), loadInsights(), loadStages()])
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    const payload = {
      auto_assign: form.auto_assign,
      system_disabled: form.system_disabled,
      mode: form.mode,
      strategy: form.strategy,
      schedule_times: form.schedule_times.map((t) => String(t).slice(0, 5)),
      max_leads_per_user: form.max_leads_per_user,
      working_hours: { ...form.working_hours },
      weight_attendance: form.weight_attendance,
      weight_performance: form.weight_performance,
      weight_availability: form.weight_availability,
      weight_fairness: form.weight_fairness,
      require_attendance: form.require_attendance,
      assigned_stage_id: form.assigned_stage_id,
      stuck_recovery_enabled: form.stuck_recovery_enabled,
      stuck_lead_minutes: form.stuck_lead_minutes,
      assign_cooldown_minutes: form.assign_cooldown_minutes,
      high_priority_score_threshold: form.high_priority_score_threshold,
      sla_minutes: form.sla_minutes,
      sla_escalation_enabled: form.sla_escalation_enabled,
      fallback_user_id: form.fallback_user_id > 0 ? form.fallback_user_id : null,
      exploration_epsilon: form.exploration_epsilon,
      cold_start_max_samples: form.cold_start_max_samples,
      cold_start_explore_ratio: form.cold_start_explore_ratio,
      adaptive_weights_enabled: form.adaptive_weights_enabled,
      factor_weight_attendance: form.factor_weight_attendance,
      factor_weight_performance: form.factor_weight_performance,
      factor_weight_skill: form.factor_weight_skill,
      realtime_assignment_enabled: form.realtime_assignment_enabled,
      realtime_interval_seconds: Math.min(30, Math.max(2, Number(form.realtime_interval_seconds) || 5)),
      simple_mode_enabled: form.simple_mode_enabled,
      simple_mode_batch_size: Math.min(500, Math.max(1, Number(form.simple_mode_batch_size) || 25)),
      simple_mode_auto_interval_seconds: Math.min(300, Math.max(2, Number(form.simple_mode_auto_interval_seconds) || 10)),
    }
    const res = await api.put('/lead-assignment/settings', payload)
    mapSettings(res?.data?.data)
    notify('Lead assignment settings saved', 'success')
  } catch (e) {
    notify(e?.response?.data?.message || 'Save failed', 'error')
  } finally {
    saving.value = false
  }
}

const runNow = async () => {
  running.value = true
  try {
    const res = await api.post('/lead-assignment/run')
    notify(res?.data?.message || 'Run finished', 'success')
    await Promise.all([loadQueue(), loadLogs()])
  } catch (e) {
    notify(e?.response?.data?.message || 'Run failed', 'error')
  } finally {
    running.value = false
  }
}

const fmt4 = (v) => {
  if (v === null || v === undefined || v === '') return '—'
  const n = Number(v)
  if (Number.isNaN(n)) return '—'
  return n.toFixed(4)
}

const explainSummary = (log) => {
  const ex = log?.explanation
  if (ex && typeof ex === 'object' && ex.reason) return ex.reason
  return log?.reason || '—'
}

const runSimulate = async () => {
  if (!simulateLeadId.value) {
    notify('Enter a lead ID for simulation', 'warning')
    return
  }
  simulating.value = true
  simulateResult.value = ''
  try {
    const res = await api.post('/lead-assignment/simulate', { lead_id: simulateLeadId.value })
    simulateResult.value = JSON.stringify(res?.data?.data ?? res?.data, null, 2)
    notify(res?.data?.message || 'Simulation done', 'success')
  } catch (e) {
    notify(e?.response?.data?.message || 'Simulation failed', 'error')
  } finally {
    simulating.value = false
  }
}

const runOverride = async () => {
  if (!overrideLeadId.value || !overrideUserId.value) {
    notify('Enter lead ID and sales user ID', 'warning')
    return
  }
  overriding.value = true
  try {
    const res = await api.post('/lead-assignment/override', {
      lead_id: overrideLeadId.value,
      assigned_to: overrideUserId.value,
    })
    notify(res?.data?.message || 'Override applied', 'success')
    await Promise.all([loadQueue(), loadLogs()])
  } catch (e) {
    notify(e?.response?.data?.message || 'Override failed', 'error')
  } finally {
    overriding.value = false
  }
}

const runRevertStageAssignments = async () => {
  const stageId = Number(revertStageId.value || form.assigned_stage_id || 0)
  if (!stageId) {
    notify('Select a stage first', 'warning')
    return
  }
  const ok = window.confirm('This will revert all assigned leads in this stage to their original owner. Continue?')
  if (!ok) return

  revertingStage.value = true
  try {
    const res = await api.post('/lead-assignment/revert-stage', { stage_id: stageId })
    const stats = res?.data?.data || {}
    const updated = Number(stats.updated ?? 0)
    const skipped = Number(stats.skipped ?? 0)
    if (updated > 0) {
      notify(`Revert finished: moved ${updated} leads to New with original owner${skipped ? ` (${skipped} skipped)` : ''}.`, 'success')
    } else {
      notify(`No leads were reverted (skipped: ${skipped}). Check selected stage and original-owner data.`, 'warning')
    }
    await Promise.all([loadQueue(), loadLogs(), loadStats()])
  } catch (e) {
    notify(e?.response?.data?.message || 'Stage revert failed', 'error')
  } finally {
    revertingStage.value = false
  }
}

const reassign = async () => {
  if (!reassignLeadId.value) {
    notify('Enter a lead ID', 'warning')
    return
  }
  reassigning.value = true
  try {
    const res = await api.post('/lead-assignment/reassign', { lead_id: reassignLeadId.value })
    notify(res?.data?.message || 'Reassigned', 'success')
    await Promise.all([loadQueue(), loadLogs()])
  } catch (e) {
    notify(e?.response?.data?.message || 'Reassign failed', 'error')
  } finally {
    reassigning.value = false
  }
}

const addTime = () => {
  form.schedule_times.push('10:00')
}

const removeTime = (idx) => {
  form.schedule_times.splice(idx, 1)
  if (!form.schedule_times.length) form.schedule_times.push('09:30')
}

const formatDt = (v) => {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleString()
  } catch {
    return String(v)
  }
}

const statusClass = (s) => {
  const v = String(s || '').toLowerCase()
  if (v === 'present') return 'ok'
  if (v === 'late') return 'warn'
  if (v === 'absent') return 'bad'
  return ''
}

const wireRealtime = () => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    if (!user?.id || !window.Echo) return
    echoChannel = window.Echo.private(`user.${user.id}`).listen('.lead.updated', () => {
      loadQueue()
      loadLogs()
      loadAttendance()
    })
    echoChannel.listen('.lead.assignment.updated', (payload) => {
      loadQueue()
      loadLogs()
      fetchLiveHealth()
      const num = payload?.lead_number ?? payload?.detail?.lead_number
      notify(num ? `Lead assigned (#${num})` : 'Lead assigned', 'success')
    })
    const roles = user.roles || user.role || []
    const roleList = Array.isArray(roles) ? roles.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean) : []
    const isAdmin = roleList.includes('admin') || roleList.includes('super_admin')
    if (isAdmin) {
      echoLaChannel = window.Echo.private('lead-assignment').listen('.lead.assignment.updated', () => {
        loadQueue()
        loadLogs()
        fetchLiveHealth()
      })
    }
  } catch {
    /* ignore */
  }
}

watch(
  () => [form.auto_assign, form.simple_mode_enabled, form.realtime_assignment_enabled, form.system_disabled],
  () => setupLivePoll(),
)

onMounted(async () => {
  await reloadAll()
  wireRealtime()
  setupLivePoll()
  pollTimer = setInterval(() => {
    loadQueue()
    loadLogs()
    loadAttendance()
    loadStats()
    loadInsights()
  }, 25000)
})

onUnmounted(() => {
  if (realtimeHealthTimer) clearInterval(realtimeHealthTimer)
  if (pollTimer) clearInterval(pollTimer)
  try {
    echoChannel?.stopListening?.('.lead.updated')
    echoChannel?.stopListening?.('.lead.assignment.updated')
  } catch {
    /* ignore */
  }
  echoChannel = null
  try {
    echoLaChannel?.stopListening?.('.lead.assignment.updated')
  } catch {
    /* ignore */
  }
  echoLaChannel = null
})
</script>

<style scoped>
.lae-page {
  padding: 16px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  border-radius: 16px;
  min-height: 420px;
}
.lae-header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.lae-header h1 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
}
.lae-header p {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 11px;
  line-height: 1.35;
}
.lae-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.lae-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #334155;
}
.lae-badge.is-on {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #15803d;
}
.lae-badge.is-idle {
  border-color: #e2e8f0;
  background: #f8fafc;
}
.lae-badge.is-off {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}
.lae-badge.is-realtime {
  border-color: #a5b4fc;
  background: #eef2ff;
  color: #4338ca;
}
.lae-badge.is-running {
  border-color: #86efac;
  background: #ecfdf3;
  color: #15803d;
  letter-spacing: 0.06em;
}
.lae-badge.is-stopped {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
  letter-spacing: 0.06em;
}
.lae-badge--lifecycle {
  font-size: 11px;
  padding: 6px 14px;
}
.lae-header-tagline {
  max-width: 560px;
}
.lae-simple-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
  position: relative;
  z-index: 2;
}
.lae-toggle.is-on-active {
  border-color: #86efac !important;
  background: #f0fdf4 !important;
}
.lae-live-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}
.lae-live-list {
  margin: 0;
  padding-left: 0;
  list-style: none;
  font-size: 11px;
}
.lae-live-list li {
  display: flex;
  gap: 8px;
  align-items: baseline;
  padding: 4px 0;
  border-bottom: 1px solid #f1f5f9;
}
.lae-live-ord {
  color: #94a3b8;
  font-weight: 700;
  min-width: 18px;
}
.lae-live-name {
  flex: 1;
  font-weight: 600;
  color: #0f172a;
}
.lae-live-time {
  color: #64748b;
  font-size: 10px;
}
.lae-big-num {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  margin: 4px 0;
}
.lae-last-assign {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
  margin: 4px 0 0;
}
.lae-details {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0 12px 12px;
  background: #fff;
}
.lae-details-summary {
  cursor: pointer;
  list-style: none;
  padding: 12px 0;
}
.lae-details-summary::-webkit-details-marker {
  display: none;
}
.lae-details-summary::before {
  content: '▸ ';
  color: #64748b;
}
details[open] .lae-details-summary::before {
  content: '▾ ';
}
.lae-details-body {
  padding-top: 4px;
}
.lae-realtime-hint {
  color: #4338ca;
  font-weight: 600;
}
.lae-realtime-health {
  margin-bottom: 12px;
  padding: 10px 12px;
  border: 1px solid #e0e7ff;
  border-radius: 10px;
  background: #fafaff;
}
.lae-health-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 8px;
}
.lae-health-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.lae-health-label {
  font-size: 9px;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.lae-health-val {
  font-size: 15px;
  font-weight: 800;
  color: #0f172a;
}
.lae-health-val.sm {
  font-size: 11px;
  font-weight: 600;
}
.lae-health-val.is-ok {
  color: #15803d;
}
.lae-health-val.is-warn {
  color: #b45309;
}
.lae-health-val.is-muted {
  color: #64748b;
}
.btn {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  background: #fff;
}
.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn-dark {
  background: #0f172a;
  color: #fff;
  border-color: #0f172a;
}
.btn-light:hover {
  background: #f8fafc;
}
.lae-skeleton-wrap {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 8px 0;
}
.lae-skel-line {
  height: 14px;
  border-radius: 8px;
  background: linear-gradient(90deg, #e2e8f0, #f1f5f9, #e2e8f0);
  background-size: 200% 100%;
  animation: lae-shimmer 1.2s infinite linear;
}
@keyframes lae-shimmer {
  0% {
    background-position: 0 0;
  }
  100% {
    background-position: -200% 0;
  }
}
.lae-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.lae-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 1px 6px rgba(15, 23, 42, 0.04);
}
.lae-panel h2 {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: #0f172a;
}
.lae-desc {
  margin: 4px 0 10px;
  font-size: 11px;
  color: #64748b;
  line-height: 1.35;
}
.lae-save-hint {
  margin-top: 0;
  margin-bottom: 10px;
}
.lae-grid2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.lae-grid3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-top: 10px;
}
.lae-grid4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}
.lae-field,
.lae-toggle {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #334155;
}
.lae-toggle {
  flex-direction: row;
  align-items: center;
  gap: 8px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 10px;
  background: #f8fafc;
}
.lae-toggle.danger {
  border-color: #fecaca;
  background: #fff7f7;
}
.lae-toggle--accent:not(.is-on-active) {
  border-color: #c7d2fe;
  background: #f8fafc;
}
.lae-toggle--btn {
  appearance: none;
  -webkit-appearance: none;
  font: inherit;
  color: inherit;
  cursor: pointer;
  text-align: left;
  width: 100%;
  box-sizing: border-box;
}
.lae-toggle--btn:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}
.lae-switch-track {
  flex-shrink: 0;
  width: 36px;
  height: 20px;
  border-radius: 999px;
  background: #cbd5e1;
  position: relative;
  transition: background 0.15s ease;
}
.lae-switch-track.on {
  background: #22c55e;
}
.lae-switch-thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 999px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
  transition: left 0.15s ease;
}
.lae-switch-track.on .lae-switch-thumb {
  left: 18px;
}
.lae-input {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 6px 8px;
  font-size: 11px;
}
.lae-input.sm {
  max-width: 120px;
}
.lae-actions-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 12px;
  align-items: center;
}
.lae-reassign {
  display: flex;
  gap: 8px;
  align-items: center;
}
.lae-schedule-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.lae-schedule-row {
  display: flex;
  gap: 8px;
  align-items: center;
}
.lae-table-wrap {
  overflow: auto;
  max-height: 260px;
}
.lae-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}
.lae-table th,
.lae-table td {
  border-bottom: 1px solid #eef2f7;
  padding: 6px 8px;
  text-align: left;
  vertical-align: top;
}
.lae-table th {
  color: #64748b;
  font-weight: 700;
}
.lae-empty {
  color: #94a3b8;
  text-align: center;
}
.lae-reason {
  max-width: 320px;
  white-space: pre-wrap;
  word-break: break-word;
}
.lae-subhead {
  margin: 14px 0 0;
  font-size: 12px;
}
.lae-tools-row {
  margin-top: 8px;
}
.lae-tool-label {
  font-size: 10px;
  font-weight: 700;
  color: #64748b;
  margin-right: 4px;
}
.lae-json {
  margin-top: 8px;
  max-height: 200px;
  overflow: auto;
  font-size: 10px;
  background: #0f172a;
  color: #e2e8f0;
  padding: 8px;
  border-radius: 8px;
}
.lae-hint {
  margin-top: 8px;
  font-size: 10px;
}
.lae-stats-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
}
.lae-stat-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 12px;
  min-width: 140px;
  background: #f8fafc;
}
.lae-stat-label {
  display: block;
  font-size: 10px;
  color: #64748b;
  font-weight: 700;
}
.lae-stat-val {
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
}
.lae-stats-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.lae-mini-h {
  margin: 0 0 6px;
  font-size: 11px;
}
.lae-stat-list {
  margin: 0;
  padding-left: 16px;
  font-size: 11px;
  color: #334155;
}
.lae-stat-list.lae-compact {
  max-height: 160px;
  overflow: auto;
}
.lae-insight-hours {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 700;
  color: #0f172a;
}
.lae-pill {
  display: inline-flex;
  padding: 2px 8px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 10px;
  background: #f1f5f9;
}
.lae-pill.ok {
  background: #ecfdf3;
  color: #15803d;
}
.lae-pill.warn {
  background: #fffbeb;
  color: #b45309;
}
.lae-pill.bad {
  background: #fef2f2;
  color: #b91c1c;
}
.lae-mini-skel {
  height: 80px;
  border-radius: 10px;
  background: linear-gradient(90deg, #e2e8f0, #f8fafc, #e2e8f0);
  background-size: 200% 100%;
  animation: lae-shimmer 1.2s infinite linear;
}
.lae-span2 {
  grid-column: 1 / -1;
}
@media (max-width: 960px) {
  .lae-layout {
    grid-template-columns: 1fr;
  }
  .lae-grid2,
  .lae-grid3,
  .lae-grid4 {
    grid-template-columns: 1fr;
  }
}
</style>
