<template>
  <div class="dashboard-main-body rm-page">
    <div class="rm-shell">
      <!-- Header -->
      <div class="rm-top">
        <div>
          <h1 class="rm-title">Request Monitor</h1>
          <p class="rm-hint">
            Who is using the server right now, and what they are asking for.
            <span v-if="snapshot"> Updated {{ formatTime(snapshot.generated_at) }}.</span>
          </p>
        </div>
        <div class="rm-actions">
          <div class="rm-seg" role="group" aria-label="Time window">
            <button
              v-for="w in windows"
              :key="w"
              type="button"
              class="rm-seg__btn"
              :class="{ 'is-active': minutes === w }"
              :disabled="loading"
              @click="setWindow(w)"
            >
              Last {{ w === 60 ? 'hour' : `${w} min` }}
            </button>
          </div>
          <label class="rm-auto">
            <input v-model="autoRefresh" type="checkbox" />
            Auto-refresh
          </label>
          <button type="button" class="rm-btn" :disabled="loading" @click="load">
            <iconify-icon icon="lucide:refresh-cw" width="15" height="15" :class="{ 'rm-spin': loading }" />
            Refresh
          </button>
        </div>
      </div>

      <div v-if="error" class="rm-banner rm-banner--err">
        <span>{{ error }}</span>
        <button type="button" class="rm-link" @click="load">Retry</button>
      </div>

      <div v-if="!snapshot && loading" class="rm-empty">Reading server logs…</div>

      <template v-if="snapshot">
        <div v-if="!access.available" class="rm-banner rm-banner--warn">
          {{ access.message || 'The server access log is not available, so only lead-board timings are shown.' }}
        </div>
        <div v-else-if="access.truncated" class="rm-banner rm-banner--warn">
          Very high traffic: only the most recent part of this window was read. Pick a shorter window for exact numbers.
        </div>

        <!-- Plain-language summary -->
        <section class="rm-summary" :class="`is-${health.level}`">
          <div class="rm-summary__head">
            <iconify-icon :icon="health.icon" width="22" height="22" />
            <span>{{ health.title }}</span>
          </div>
          <ul class="rm-summary__list">
            <li v-for="(line, i) in summaryLines" :key="i" v-html="line" />
          </ul>
        </section>

        <!-- Key numbers -->
        <div class="rm-metrics">
          <div class="rm-metric" :class="{ 'is-warn': access.per_minute_avg >= 600 }">
            <span class="rm-metric__n">{{ fmtNum(access.per_minute_avg) }}</span>
            <span class="rm-metric__l">requests per minute</span>
          </div>
          <div class="rm-metric">
            <span class="rm-metric__n">{{ fmtNum(access.unique_ips) }}</span>
            <span class="rm-metric__l">different IPs (offices / devices)</span>
          </div>
          <div class="rm-metric" :class="msClass(board.median_ms)">
            <span class="rm-metric__n">{{ fmtMs(board.median_ms) }}</span>
            <span class="rm-metric__l">typical lead-board load</span>
          </div>
          <div class="rm-metric" :class="msClass(board.p90_ms)">
            <span class="rm-metric__n">{{ fmtMs(board.p90_ms) }}</span>
            <span class="rm-metric__l">slow lead-board load (1 in 10)</span>
          </div>
          <div class="rm-metric" :class="{ 'is-alert': access.status['5xx'] > 0 }">
            <span class="rm-metric__n">{{ fmtNum(access.status['5xx']) }}</span>
            <span class="rm-metric__l">server errors</span>
          </div>
          <div class="rm-metric" :class="loadClass">
            <span class="rm-metric__n">
              {{ server.load ? server.load[0] : '—' }}<small v-if="server.cpus"> / {{ server.cpus }}</small>
            </span>
            <span class="rm-metric__l">server load / CPUs</span>
          </div>
        </div>

        <!-- Timeline -->
        <section v-if="access.available && access.timeline.length" class="rm-card">
          <div class="rm-card__head">
            <h2 class="rm-card__title">Requests per minute</h2>
            <span class="rm-legend">
              <i class="rm-dot rm-dot--users" /> Everyone else
              <i class="rm-dot rm-dot--top" /> Biggest user
              <i class="rm-dot rm-dot--err" /> Errors
            </span>
          </div>
          <div class="rm-chart" :style="{ '--bars': access.timeline.length }">
            <div
              v-for="pt in access.timeline"
              :key="pt.minute"
              class="rm-bar"
              :title="`${formatTime(pt.minute)} — ${pt.total} requests (${pt.top} from the biggest user)${pt.errors ? `, ${pt.errors} errors` : ''}`"
            >
              <div class="rm-bar__fill" :style="{ height: barHeight(pt.total) }">
                <div v-if="pt.errors" class="rm-bar__err" :style="{ height: `${Math.max(4, (pt.errors / pt.total) * 100)}%` }" />
                <div class="rm-bar__top" :style="{ height: `${pt.total ? (pt.top / pt.total) * 100 : 0}%` }" />
              </div>
            </div>
          </div>
          <div class="rm-chart__axis">
            <span>{{ formatTime(access.timeline[0]?.minute) }}</span>
            <span>peak {{ fmtNum(timelinePeak) }} / min</span>
            <span>{{ formatTime(access.timeline[access.timeline.length - 1]?.minute) }}</span>
          </div>
        </section>

        <!-- Biggest users: what they call -->
        <section v-if="access.available" class="rm-card">
          <div class="rm-card__head">
            <h2 class="rm-card__title">Biggest users and what they use</h2>
            <span class="rm-dim">Grouped by IP: everyone in one office shares one IP.</span>
          </div>

          <div v-if="!bigSources.length" class="rm-empty">No requests in this window.</div>

          <div class="rm-sources">
            <article
              v-for="(src, idx) in bigSources"
              :key="src.ip"
              class="rm-source"
              :class="{ 'is-heavy': src.share >= 30, 'is-auto': looksAutomated(src) }"
            >
              <header class="rm-source__head">
                <div class="rm-source__rank">#{{ idx + 1 }}</div>
                <div class="rm-source__who">
                  <div class="rm-source__name">
                    {{ sourceName(src) }}
                    <span v-if="looksAutomated(src)" class="rm-tag rm-tag--warn">Looks automated</span>
                    <span v-else-if="src.share >= 30" class="rm-tag rm-tag--warn">Heavy</span>
                  </div>
                  <div class="rm-dim">
                    IP {{ src.ip }} · {{ deviceLabel(src.user_agent) }}
                  </div>
                </div>
                <div class="rm-source__stats">
                  <div class="rm-source__big">{{ src.share }}%</div>
                  <div class="rm-dim">of all traffic</div>
                </div>
              </header>

              <div class="rm-source__line">
                <strong>{{ fmtNum(src.count) }}</strong> requests ·
                <strong>{{ src.per_minute }}</strong> per minute
                <span v-if="src.per_minute >= 1"> · about one every <strong>{{ everySeconds(src.per_minute) }}</strong></span>
              </div>

              <div class="rm-uses">
                <div v-for="r in src.routes" :key="r.route" class="rm-use">
                  <div class="rm-use__label">
                    <span class="rm-use__name">{{ friendly(r.route).label }}</span>
                    <span class="rm-use__route">{{ r.route }}</span>
                  </div>
                  <div class="rm-use__bar">
                    <div class="rm-use__fill" :class="{ 'is-heavy': friendly(r.route).heavy }" :style="{ width: `${r.share}%` }" />
                  </div>
                  <div class="rm-use__n">
                    {{ fmtNum(r.count) }}
                    <span class="rm-dim">({{ r.share }}%)</span>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </section>

        <!-- Lead board per user -->
        <section class="rm-card">
          <div class="rm-card__head">
            <h2 class="rm-card__title">Lead board: who loads it and how long it takes</h2>
            <span class="rm-dim">
              {{ fmtNum(board.loads) }} loads in this window. The CRM screen asks for 12–20 leads per stage;
              other sizes come from scripts or integrations.
            </span>
          </div>
          <div class="rm-table-wrap">
            <table class="rm-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th class="rm-num">Loads</th>
                  <th class="rm-num">Per minute</th>
                  <th class="rm-num">Typical</th>
                  <th class="rm-num">Slow (1 in 10)</th>
                  <th class="rm-num">Slowest</th>
                  <th class="rm-num">Leads / stage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="u in board.users" :key="u.user_id" :class="{ 'is-heavy': isSuspicious(u) }">
                  <td>
                    <div class="rm-name">
                      {{ u.name }}
                      <span v-if="isSuspicious(u)" class="rm-tag rm-tag--warn">Looks automated</span>
                    </div>
                    <div class="rm-dim">#{{ u.user_id }} · {{ u.role || '—' }}</div>
                  </td>
                  <td class="rm-num">{{ fmtNum(u.loads) }}</td>
                  <td class="rm-num">{{ (u.loads / minutes).toFixed(1) }}</td>
                  <td class="rm-num" :class="msTextClass(u.avg_ms)">{{ fmtMs(u.avg_ms) }}</td>
                  <td class="rm-num" :class="msTextClass(u.p90_ms)">{{ fmtMs(u.p90_ms) }}</td>
                  <td class="rm-num" :class="msTextClass(u.max_ms)">{{ fmtMs(u.max_ms) }}</td>
                  <td class="rm-num">{{ u.per_page || '—' }}</td>
                </tr>
                <tr v-if="!board.users.length"><td colspan="7" class="rm-dim">No lead-board loads in this window.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- All endpoints -->
        <section v-if="access.available" class="rm-card">
          <div class="rm-card__head">
            <h2 class="rm-card__title">Most requested features (everyone)</h2>
          </div>
          <div class="rm-uses">
            <div v-for="r in access.top_urls.slice(0, 15)" :key="r.route" class="rm-use">
              <div class="rm-use__label">
                <span class="rm-use__name">{{ friendly(r.route).label }}</span>
                <span class="rm-use__route">{{ r.route }}</span>
              </div>
              <div class="rm-use__bar">
                <div class="rm-use__fill" :class="{ 'is-heavy': friendly(r.route).heavy }" :style="{ width: `${r.share}%` }" />
              </div>
              <div class="rm-use__n">
                {{ r.per_minute }}/min
                <span class="rm-dim">({{ r.share }}%)</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Errors -->
        <section v-if="access.recent_errors?.length" class="rm-card">
          <div class="rm-card__head">
            <h2 class="rm-card__title">Recent server errors</h2>
          </div>
          <div class="rm-table-wrap">
            <table class="rm-table">
              <thead>
                <tr><th>Time</th><th>Code</th><th>Feature</th><th>IP</th></tr>
              </thead>
              <tbody>
                <tr v-for="(e, i) in access.recent_errors" :key="i">
                  <td>{{ formatTime(e.at) }}</td>
                  <td><span class="rm-tag rm-tag--err">{{ e.status }}</span></td>
                  <td>
                    <div>{{ friendly(e.route).label }}</div>
                    <div class="rm-dim rm-mono">{{ e.route }}</div>
                  </td>
                  <td class="rm-mono">{{ e.ip }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import api from '@/plugins/axios'

const windows = [1, 5, 15, 60]
const STORAGE_KEY = 'request-monitor:prefs'
const AUTO_MS = 30_000
// Page sizes the CRM board itself sends; anything else is an outside caller.
const CRM_PAGE_SIZES = new Set(['12', '15', '20'])

// Human names for endpoints. `heavy` marks expensive ones (shown orange).
const FRIENDLY = [
  [/stages-with-leads/, 'Lead board (Kanban)', true],
  [/kanban\/stage-counts/, 'Lead board counts', true],
  [/^GET \/api\/stages$/, 'Stage list'],
  [/sidebar\/counts/, 'Sidebar badges', true],
  [/live-watch\/client-config/, 'Live Watch check'],
  [/live-watch\/heartbeat/, 'Live Watch heartbeat'],
  [/chat\/unread-count/, 'Chat unread badge'],
  [/\/chat\//, 'Chat'],
  [/maintenance\/status/, 'Maintenance check'],
  [/image\/watermark/, 'Listing photo (watermarked)', true],
  [/announcements/, 'Announcements'],
  [/auth\/notifications/, 'Notifications'],
  [/broadcasting\/auth/, 'Live updates sign-in'],
  [/lead-matches/, 'Lead matches'],
  [/system-campaigns/, 'Campaign popups'],
  [/birthdays/, 'Birthdays'],
  [/sales-action-center/, 'Sales action center', true],
  [/auth\/location/, 'Location tracking'],
  [/auth\/login/, 'Login'],
  [/^(GET|POST|PUT|PATCH) \/api\/leads\/\{id\}/, 'Open / edit a lead'],
  [/^GET \/api\/leads/, 'Lead list / search', true],
  [/^(POST|PUT|PATCH) \/api\/leads/, 'Create / update lead'],
  [/listings\/areas/, 'Areas list'],
  [/listings\/property-types/, 'Property types'],
  [/\/api\/listings/, 'Listings'],
  [/\/api\/deals/, 'Deals'],
  [/daily-motivation/, 'Daily motivation'],
  [/user-animations/, 'User animations'],
  [/\.(js|css|woff2?|png|jpe?g|svg|webp|ico)$/, 'Website files (JS / CSS / images)'],
]

const minutes = ref(5)
const autoRefresh = ref(true)
const loading = ref(false)
const error = ref('')
const snapshot = ref(null)
let timer = null

try {
  const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
  if (windows.includes(saved.minutes)) minutes.value = saved.minutes
  if (typeof saved.auto === 'boolean') autoRefresh.value = saved.auto
} catch {
  /* ignore */
}

const emptyAccess = { available: false, total: 0, per_minute_avg: 0, unique_ips: 0, status: { '5xx': 0, '429': 0 }, timeline: [], top_urls: [], top_ips: [], recent_errors: [] }
const emptyBoard = { loads: 0, avg_ms: 0, median_ms: 0, p90_ms: 0, max_ms: 0, users: [] }

const access = computed(() => snapshot.value?.access || emptyAccess)
const board = computed(() => snapshot.value?.board || emptyBoard)
const server = computed(() => snapshot.value?.server || {})
const timelinePeak = computed(() => Math.max(1, ...access.value.timeline.map((p) => p.total)))
const bigSources = computed(() => access.value.top_ips.slice(0, 6))

const loadClass = computed(() => {
  const l = server.value.load?.[0]
  const cpus = server.value.cpus || 1
  if (l == null) return ''
  if (l > cpus * 1.5) return 'is-alert'
  if (l > cpus) return 'is-warn'
  return ''
})

const health = computed(() => {
  const top = bigSources.value[0]
  if (access.value.status['5xx'] > 0 || board.value.median_ms >= 8000) {
    return { level: 'alert', icon: 'lucide:circle-alert', title: 'The CRM is under strain' }
  }
  if ((top && top.share >= 30) || board.value.median_ms >= 3000) {
    return { level: 'warn', icon: 'lucide:triangle-alert', title: 'The CRM is busy — something needs attention' }
  }
  return { level: 'ok', icon: 'lucide:circle-check', title: 'The CRM looks healthy' }
})

const summaryLines = computed(() => {
  const lines = []
  const a = access.value
  const b = board.value
  const win = minutes.value === 60 ? 'the last hour' : `the last ${minutes.value} minute${minutes.value > 1 ? 's' : ''}`

  if (a.available) {
    lines.push(`<b>${fmtNum(a.total)}</b> requests in ${win} — about <b>${fmtNum(a.per_minute_avg)}</b> per minute from <b>${fmtNum(a.unique_ips)}</b> different IPs.`)
  }
  const top = bigSources.value[0]
  if (top && top.share >= 20) {
    const main = top.routes?.[0]
    lines.push(
      `<b>${esc(sourceName(top))}</b> alone makes <b>${top.share}%</b> of all requests` +
      (main ? `, mostly <b>${esc(friendly(main.route).label)}</b> (${fmtNum(main.count)} times)` : '') +
      (looksAutomated(top) ? ' — this looks like a script or integration, not a person.' : '.'),
    )
  }
  if (b.loads) {
    lines.push(`The lead board was loaded <b>${fmtNum(b.loads)}</b> times. A typical load takes <b>${fmtMs(b.median_ms)}</b>; 1 in 10 takes over <b>${fmtMs(b.p90_ms)}</b>.`)
  }
  const auto = b.users.filter(isSuspicious)
  if (auto.length) {
    lines.push(`Possible automated board callers: ${auto.slice(0, 3).map((u) => `<b>${esc(u.name)}</b> (#${u.user_id}, ${fmtNum(u.loads)} loads)`).join(', ')}.`)
  }
  if (a.status['5xx'] > 0) {
    lines.push(`<b>${a.status['5xx']}</b> requests failed with a server error — see the list at the bottom.`)
  }
  if (server.value.opcache === false) {
    lines.push('PHP OPcache is <b>off</b>. Turning it on in cPanel → Select PHP Version → Extensions makes every request faster.')
  }
  return lines
})

function savePrefs() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ minutes: minutes.value, auto: autoRefresh.value }))
  } catch {
    /* ignore */
  }
}

async function load() {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/admin/request-monitor', { params: { minutes: minutes.value } })
    snapshot.value = data?.data || null
  } catch (err) {
    error.value = err?.response?.status === 403
      ? 'Forbidden — Super Admin only'
      : (err?.response?.data?.message || 'Failed to load the request monitor')
  } finally {
    loading.value = false
  }
}

function setWindow(w) {
  minutes.value = w
  savePrefs()
  load()
}

function armTimer() {
  clearInterval(timer)
  timer = null
  if (!autoRefresh.value) return
  timer = setInterval(() => {
    if (document.visibilityState === 'visible') load()
  }, AUTO_MS)
}

function onVisible() {
  if (document.visibilityState === 'visible' && autoRefresh.value) load()
}

watch(autoRefresh, () => {
  savePrefs()
  armTimer()
})

function friendly(route) {
  const r = String(route || '')
  for (const [re, label, heavy] of FRIENDLY) {
    if (re.test(r)) return { label, heavy: !!heavy }
  }
  const path = r.replace(/^\w+ /, '').replace(/^\/api\//, '')
  return { label: path || r, heavy: false }
}

function sourceName(src) {
  if (src.users?.length === 1) return `${src.users[0].name} (#${src.users[0].id})`
  if (src.users?.length > 1) return `${src.users[0].name} + ${src.users.length - 1} more`
  return `Unknown (${src.ip})`
}

function looksAutomated(src) {
  if (/python|curl|axios|node|java|go-http|okhttp|postman|guzzle|bot/i.test(src.user_agent || '')) return true
  const main = src.routes?.[0]
  // One endpoint hit many times a minute from one IP is not human clicking.
  return !!main && main.share >= 80 && main.per_minute >= 5
}

function isSuspicious(u) {
  return (u.per_page && !CRM_PAGE_SIZES.has(String(u.per_page))) || u.loads / minutes.value >= 5
}

function deviceLabel(ua) {
  if (!ua) return 'unknown device'
  if (/python|curl|axios|node|java|go-http|okhttp|postman|guzzle/i.test(ua)) return `script (${ua.slice(0, 30)})`
  const os = /Windows/.test(ua) ? 'Windows' : /Mac OS X/.test(ua) ? 'Mac' : /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iPhone / iPad' : /Linux/.test(ua) ? 'Linux' : ''
  const br = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : ''
  return [br, os].filter(Boolean).join(' on ') || ua.slice(0, 40)
}

function everySeconds(perMinute) {
  const s = 60 / perMinute
  return s >= 1 ? `${Math.round(s)} s` : `${Math.round(s * 1000)} ms`
}

function barHeight(total) {
  return `${Math.max(total ? 2 : 0, (total / timelinePeak.value) * 100)}%`
}

function fmtNum(n) {
  return Number(n || 0).toLocaleString()
}

function fmtMs(ms) {
  if (!ms) return '—'
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)} s` : `${ms} ms`
}

function msClass(ms) {
  if (ms >= 8000) return 'is-alert'
  if (ms >= 3000) return 'is-warn'
  return ''
}

function msTextClass(ms) {
  if (ms >= 8000) return 'rm-txt-alert'
  if (ms >= 3000) return 'rm-txt-warn'
  return ''
}

function formatTime(value) {
  if (!value) return ''
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

onMounted(() => {
  load()
  armTimer()
  document.addEventListener('visibilitychange', onVisible)
})

onUnmounted(() => {
  clearInterval(timer)
  document.removeEventListener('visibilitychange', onVisible)
})
</script>

<style scoped>
.rm-page.rm-page {
  --ink: #151a2b;
  --text: #2a3142;
  --muted: #6b7489;
  --line: #e6eaf1;
  --soft: #f7f9fc;
  --blue: #4f6bed;
  --amber: #f59e0b;
  --red: #e5484d;
  --green: #12a150;
  background: #f4f6fa !important;
  min-height: 100vh;
  padding: 16px;
  font-size: 14px;
  line-height: 1.5;
  color: var(--text);
}
.rm-shell { max-width: 1280px; margin: 0 auto; }

.rm-top { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.rm-title { font-size: 22px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.rm-hint { margin: 0; font-size: 14px; color: var(--muted); }
.rm-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.rm-seg { display: inline-flex; background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 3px; }
.rm-seg__btn {
  height: 32px; padding: 0 12px; border: 0; border-radius: 6px; background: transparent;
  font-size: 13px; font-weight: 600; color: var(--muted); cursor: pointer;
}
.rm-seg__btn.is-active { background: var(--ink); color: #fff; }
.rm-auto { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); cursor: pointer; }
.rm-btn {
  display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 14px;
  border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--ink);
  font-size: 13px; font-weight: 600; cursor: pointer;
}
.rm-btn:disabled, .rm-seg__btn:disabled { opacity: 0.55; cursor: not-allowed; }
.rm-spin { animation: rm-spin 0.9s linear infinite; }
@keyframes rm-spin { to { transform: rotate(360deg); } }

.rm-banner { display: flex; align-items: center; gap: 10px; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; }
.rm-banner--err { background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; }
.rm-banner--warn { background: #fff8e6; color: #7a5200; border: 1px solid #f3dfa8; }
.rm-link { border: none; background: transparent; color: inherit; text-decoration: underline; cursor: pointer; margin-left: auto; }
.rm-empty { padding: 16px 4px; color: var(--muted); }

/* Summary */
.rm-summary { background: #fff; border: 1px solid var(--line); border-left: 5px solid var(--green); border-radius: 12px; padding: 14px 18px; margin-bottom: 14px; }
.rm-summary.is-warn { border-left-color: var(--amber); }
.rm-summary.is-alert { border-left-color: var(--red); }
.rm-summary__head { display: flex; align-items: center; gap: 8px; font-size: 17px; font-weight: 700; color: var(--ink); margin-bottom: 6px; }
.rm-summary.is-ok .rm-summary__head iconify-icon { color: var(--green); }
.rm-summary.is-warn .rm-summary__head iconify-icon { color: var(--amber); }
.rm-summary.is-alert .rm-summary__head iconify-icon { color: var(--red); }
.rm-summary__list { margin: 0; padding-left: 20px; font-size: 15px; }
.rm-summary__list li { margin: 4px 0; }
.rm-summary__list :deep(b) { color: var(--ink); }

/* Metrics */
.rm-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 14px; }
.rm-metric { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
.rm-metric.is-warn { border-color: #f3dfa8; background: #fffaf0; }
.rm-metric.is-alert { border-color: #f5c2c0; background: #fff5f5; }
.rm-metric__n { display: block; font-size: 26px; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; line-height: 1.2; }
.rm-metric__n small { font-size: 14px; font-weight: 600; color: var(--muted); }
.rm-metric__l { display: block; font-size: 13px; color: var(--muted); margin-top: 2px; }

/* Cards */
.rm-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 14px; min-width: 0; }
.rm-card__head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px 16px; margin-bottom: 12px; }
.rm-card__title { font-size: 16px; font-weight: 700; color: var(--ink); margin: 0; }
.rm-dim { color: var(--muted); font-size: 13px; }
.rm-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }

/* Chart */
.rm-legend { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); flex-wrap: wrap; }
.rm-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-left: 8px; }
.rm-dot--users { background: var(--blue); }
.rm-dot--top { background: var(--amber); }
.rm-dot--err { background: var(--red); }
.rm-chart {
  display: grid; grid-template-columns: repeat(var(--bars), minmax(0, 1fr));
  align-items: end; gap: 3px; height: 150px; border-bottom: 1px solid var(--line);
}
.rm-bar { height: 100%; display: flex; align-items: flex-end; }
.rm-bar__fill { position: relative; width: 100%; background: var(--blue); border-radius: 4px 4px 0 0; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-end; }
.rm-bar__top { width: 100%; background: var(--amber); }
.rm-bar__err { position: absolute; top: 0; left: 0; width: 100%; background: var(--red); }
.rm-bar:hover .rm-bar__fill { filter: brightness(0.9); }
.rm-chart__axis { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-top: 6px; }

/* Sources */
.rm-sources { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 12px; }
@media (max-width: 520px) { .rm-sources { grid-template-columns: 1fr; } }
.rm-source { border: 1px solid var(--line); border-radius: 12px; padding: 14px; background: var(--soft); min-width: 0; }
.rm-source.is-heavy { border-color: #f3c97a; background: #fffaf0; }
.rm-source.is-auto { border-color: #f0b14a; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12); }
.rm-source__head { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 6px; }
.rm-source__rank { font-size: 13px; font-weight: 700; color: var(--muted); background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 2px 7px; }
.rm-source__who { flex: 1; min-width: 0; }
.rm-source__name { font-size: 15px; font-weight: 700; color: var(--ink); word-break: break-word; }
.rm-source__stats { text-align: right; }
.rm-source__big { font-size: 24px; font-weight: 800; color: var(--ink); line-height: 1.1; }
.rm-source.is-heavy .rm-source__big { color: #b86e00; }
.rm-source__line { font-size: 14px; margin-bottom: 10px; }
.rm-source__line strong { color: var(--ink); }

.rm-uses { display: flex; flex-direction: column; gap: 8px; }
.rm-use { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(60px, 1fr) auto; align-items: center; gap: 10px; }
.rm-use__label { min-width: 0; display: flex; flex-direction: column; }
.rm-use__name { font-size: 14px; font-weight: 600; color: var(--ink); }
.rm-use__route { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rm-use__bar { height: 8px; border-radius: 4px; background: #e9edf4; overflow: hidden; }
.rm-use__fill { height: 100%; background: var(--blue); border-radius: 4px; }
.rm-use__fill.is-heavy { background: var(--amber); }
.rm-use__n { font-size: 14px; font-weight: 600; color: var(--ink); text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

/* Table */
.rm-table-wrap { overflow-x: auto; }
.rm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.rm-table th { text-align: left; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.02em; padding: 8px 10px; border-bottom: 1px solid var(--line); white-space: nowrap; }
.rm-table td { padding: 10px; border-bottom: 1px solid #f0f2f6; vertical-align: top; }
.rm-table tr.is-heavy { background: #fffaf0; }
.rm-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.rm-name { font-weight: 600; color: var(--ink); }
.rm-txt-warn { color: #a86300; font-weight: 700; }
.rm-txt-alert { color: #c62828; font-weight: 700; }
.rm-tag { display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; vertical-align: middle; }
.rm-tag--warn { background: #fff1d6; color: #8a5a00; }
.rm-tag--err { background: #fdecec; color: #b3261e; margin-left: 0; }
</style>
