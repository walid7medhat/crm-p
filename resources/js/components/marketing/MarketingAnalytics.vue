<template>
  <div class="mk-an">
    <div class="mk-an__bar">
      <div class="mk-an__ranges" role="tablist" aria-label="Date range">
        <button v-for="r in RANGES" :key="r.days" type="button" :class="{ active: rangeDays === r.days }" @click="rangeDays = r.days">{{ r.label }}</button>
      </div>
      <div class="mk-an__ranges" role="tablist" aria-label="Board">
        <button type="button" :class="{ active: !category }" @click="category = ''">All boards</button>
        <button v-for="(c, key) in MARKETING_CATEGORIES" :key="key" type="button" :class="{ active: category === key }" @click="category = key">
          <iconify-icon :icon="c.icon" /> {{ c.label }}
        </button>
      </div>
    </div>

    <div v-if="loading && !data" class="mk-empty"><iconify-icon icon="lucide:loader-2" class="mk-spin" /></div>

    <div v-else-if="data" ref="root" class="mk-an__body" :class="{ 'is-loading': loading }">
      <!-- ===== Hero: headline + facts, in the theme color ===== -->
      <section class="mk-hero-an">
        <span class="mk-hero-an__orb mk-hero-an__orb--1" />
        <span class="mk-hero-an__orb mk-hero-an__orb--2" />
        <span class="mk-hero-an__orb mk-hero-an__orb--3" />

        <div class="mk-hero-an__main">
          <span class="mk-hero-an__eyebrow"><iconify-icon icon="lucide:sparkles" /> {{ rangeLabel }}</span>
          <h3>
            <b data-count-to="kpis.done">{{ k.done }}</b>
            {{ k.done === 1 ? 'piece' : 'pieces' }} shipped
          </h3>
          <p>
            <span v-if="delta(k.done, prev.done)" class="mk-delta mk-delta--light" :class="delta(k.done, prev.done).dir">
              <iconify-icon :icon="delta(k.done, prev.done).icon" /> {{ delta(k.done, prev.done).label }}
            </span>
            vs the previous {{ rangeDays === 365 ? '12 months' : `${rangeDays} days` }} · {{ k.created }} new cards created
          </p>
        </div>

        <div class="mk-hero-an__facts">
          <div class="mk-fact">
            <span class="mk-fact__icon">
              <img v-if="champion?.avatar_url" :src="champion.avatar_url" alt="" />
              <iconify-icon v-else icon="lucide:trophy" />
            </span>
            <div><small>Top performer</small><b>{{ champion ? firstName(champion.name) : '—' }}</b></div>
          </div>
          <div class="mk-fact">
            <span class="mk-fact__icon"><iconify-icon icon="lucide:calendar-heart" /></span>
            <div><small>Busiest posting day</small><b>{{ busiestDay || '—' }}</b></div>
          </div>
          <div class="mk-fact">
            <span class="mk-fact__icon"><iconify-icon :icon="topPlatform ? PLATFORMS[topPlatform.key].icon : 'lucide:share-2'" /></span>
            <div><small>Top platform</small><b>{{ topPlatform ? PLATFORMS[topPlatform.key].label : '—' }}</b></div>
          </div>
        </div>
      </section>

      <!-- ===== KPI tiles ===== -->
      <div class="mk-tiles">
        <div class="mk-tile">
          <div class="mk-tile__head"><span class="mk-tile__icon"><iconify-icon icon="lucide:layers" /></span> Active cards</div>
          <b class="mk-tile__num" :data-count="k.active">{{ k.active }}</b>
          <small>{{ k.total }} on the board{{ category ? '' : 's' }}</small>
        </div>

        <div class="mk-tile">
          <div class="mk-tile__head"><span class="mk-tile__icon"><iconify-icon icon="lucide:check-check" /></span> Done</div>
          <div class="mk-tile__row">
            <b class="mk-tile__num" :data-count="k.done">{{ k.done }}</b>
            <span v-if="delta(k.done, prev.done)" class="mk-delta" :class="delta(k.done, prev.done).dir">
              <iconify-icon :icon="delta(k.done, prev.done).icon" /> {{ delta(k.done, prev.done).label }}
            </span>
          </div>
          <apexchart class="mk-tile__spark" type="area" height="46" :options="sparkOptions" :series="[{ name: 'Done', data: data.weekly.map((w) => w.done) }]" />
        </div>

        <div class="mk-tile">
          <div class="mk-tile__head"><span class="mk-tile__icon"><iconify-icon icon="lucide:timer" /></span> Avg. days to done</div>
          <div class="mk-tile__row">
            <b class="mk-tile__num" :data-count="k.avg_days_to_done ?? null">{{ k.avg_days_to_done ?? '—' }}</b>
            <span v-if="delta(k.avg_days_to_done, prev.avg_days_to_done, true)" class="mk-delta" :class="delta(k.avg_days_to_done, prev.avg_days_to_done, true).dir">
              <iconify-icon :icon="delta(k.avg_days_to_done, prev.avg_days_to_done, true).icon" /> {{ delta(k.avg_days_to_done, prev.avg_days_to_done, true).label }}
            </span>
          </div>
          <small>{{ k.in_revision }} card{{ k.in_revision === 1 ? '' : 's' }} on revision now</small>
        </div>

        <div class="mk-tile mk-tile--ring">
          <div>
            <div class="mk-tile__head"><span class="mk-tile__icon"><iconify-icon icon="lucide:send" /></span> Posted on time</div>
            <b class="mk-tile__num">{{ k.posts_posted }}<span class="mk-tile__of">/{{ k.posts_scheduled }}</span></b>
            <small>scheduled posts went live</small>
          </div>
          <svg class="mk-ring" viewBox="0 0 64 64" role="img" :aria-label="`${postRate}% posted`">
            <circle cx="32" cy="32" r="26" class="mk-ring__track" />
            <circle cx="32" cy="32" r="26" class="mk-ring__value" :style="{ '--off': RING - (RING * postRate) / 100 }" />
            <text x="32" y="36" text-anchor="middle">{{ postRate }}%</text>
          </svg>
        </div>
      </div>

      <div class="mk-an__grid">
        <!-- ===== Pipeline flow ===== -->
        <section class="mk-panel">
          <header><h6>Pipeline right now</h6><small>{{ pipelineTotal }} cards</small></header>
          <div v-if="pipelineTotal" class="mk-flow" role="img" :aria-label="data.by_stage.map((s) => `${s.name} ${s.count}`).join(', ')">
            <span
              v-for="(s, i) in data.by_stage.filter((x) => x.count)"
              :key="s.name"
              class="mk-flow__seg"
              :style="{ flexGrow: s.count, background: s.color, animationDelay: `${i * 70}ms` }"
              :title="`${s.name}: ${s.count}`"
            />
          </div>
          <ul class="mk-flow__legend">
            <li v-for="s in data.by_stage" :key="s.name" :title="`${s.name}: ${s.count}`">
              <span class="mk-flow__dot" :style="{ background: s.color }" />
              <span class="mk-flow__name">{{ s.name }}</span>
              <b>{{ s.count }}</b>
              <small>{{ pct(s.count, pipelineTotal) }}%</small>
            </li>
          </ul>
        </section>

        <!-- ===== Content mix ===== -->
        <section class="mk-panel">
          <header><h6>{{ mixTitle }}</h6><small>right now</small></header>
          <div class="mk-mix">
            <div v-for="(r, i) in mixRows" :key="r.label" class="mk-mix__item" :title="`${r.label}: ${r.count}`">
              <span class="mk-mix__icon"><iconify-icon :icon="r.icon" /></span>
              <div class="mk-mix__body">
                <div class="mk-mix__top"><span>{{ r.label }}</span><b>{{ r.count }}</b></div>
                <div class="mk-bar"><span :style="{ width: `${pct(r.count, mixMax)}%`, animationDelay: `${i * 80}ms` }" /></div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===== Weekly trend ===== -->
        <section class="mk-panel mk-panel--wide">
          <header><h6>Created vs done per week</h6></header>
          <apexchart type="area" height="260" :options="weeklyOptions" :series="weeklySeries" />
        </section>

        <!-- ===== Leaderboard ===== -->
        <section class="mk-panel">
          <header><h6>Team leaderboard</h6><small>ranked by cards done</small></header>
          <template v-if="data.workload.length">
            <div class="mk-podium">
              <div
                v-for="p in podium"
                :key="p.user_id"
                class="mk-podium__spot"
                :class="`is-${p.rank}`"
                :title="`${p.name}: ${p.done} done · ${p.total} worked on`"
              >
                <span class="mk-podium__medal">{{ ['🥇', '🥈', '🥉'][p.rank - 1] }}</span>
                <span class="mk-podium__avatar">
                  <img v-if="p.avatar_url" :src="p.avatar_url" :alt="p.name" />
                </span>
                <b>{{ firstName(p.name) }}</b>
                <small>{{ p.done }} done</small>
                <span class="mk-podium__step">{{ p.rank }}</span>
              </div>
            </div>
            <ul class="mk-legend">
              <li><span :style="{ background: series[0] }" /> Shot</li>
              <li><span :style="{ background: series[1] }" /> Edited</li>
              <li><span :style="{ background: series[2] }" /> Designed / assigned</li>
            </ul>
            <ul class="mk-rank">
              <li v-for="(w, i) in data.workload" :key="w.user_id" :title="`${w.name}: ${w.shot} shot · ${w.edited} edited · ${w.assigned} assigned`">
                <span class="mk-rank__pos">{{ i + 1 }}</span>
                <span class="mk-rank__name">{{ w.name }}</span>
                <span class="mk-stack">
                  <span :style="{ width: `${pct(w.shot, workMax)}%`, background: series[0] }" />
                  <span :style="{ width: `${pct(w.edited, workMax)}%`, background: series[1] }" />
                  <span :style="{ width: `${pct(w.assigned, workMax)}%`, background: series[2] }" />
                </span>
                <b>{{ w.total }}</b>
              </li>
            </ul>
          </template>
          <div v-else class="mk-empty"><iconify-icon icon="lucide:users" />No assigned cards in this range</div>
        </section>

        <!-- ===== Revisions ===== -->
        <section class="mk-panel">
          <header><h6>Revision rounds</h6><small>moves into “On Revision”</small></header>
          <ul v-if="data.revisions.length" class="mk-rev">
            <li v-for="(r, i) in data.revisions" :key="r.user_id" :title="`${r.name}: ${r.count} revision${r.count === 1 ? '' : 's'}`">
              <span class="mk-rev__name">{{ r.name }}</span>
              <div class="mk-bar mk-bar--thick"><span :style="{ width: `${pct(r.count, revMax)}%`, animationDelay: `${i * 70}ms` }" /></div>
              <b>{{ r.count }}</b>
            </li>
          </ul>
          <div v-else class="mk-empty mk-empty--happy">
            <span class="mk-empty__badge"><iconify-icon icon="lucide:party-popper" /></span>
            <b>Zero revisions</b>
            Everything was right first time in this range.
          </div>
        </section>

        <!-- ===== Posting heatmap ===== -->
        <section class="mk-panel mk-panel--wide">
          <header><h6>Posting heatmap</h6><small>scheduled posts by weekday · darker = busier</small></header>
          <div class="mk-heat">
            <span />
            <span v-for="d in WEEKDAYS" :key="d" class="mk-heat__day">{{ d }}</span>
            <span class="mk-heat__day">Posted</span>
            <template v-for="(row, ri) in data.heatmap" :key="row.key">
              <span class="mk-heat__platform"><iconify-icon :icon="PLATFORMS[row.key].icon" /> {{ PLATFORMS[row.key].label }}</span>
              <span
                v-for="(cell, di) in row.days"
                :key="di"
                class="mk-heat__cell"
                :class="{ 'is-empty': !cell.scheduled, 'is-strong': heatAlpha(cell.scheduled) >= 0.5 }"
                :style="{ '--a': heatAlpha(cell.scheduled), animationDelay: `${(ri * 7 + di) * 12}ms` }"
                :title="`${PLATFORMS[row.key].label} · ${WEEKDAYS[di]}: ${cell.scheduled} scheduled, ${cell.posted} posted`"
              >{{ cell.scheduled || '' }}</span>
              <span class="mk-heat__total">
                {{ rowTotals[ri].posted }}/{{ rowTotals[ri].scheduled }}
              </span>
            </template>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import apexchart from 'vue3-apexcharts'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import { countUp, staggerIn } from '@/motion/marketingMotion.js'
import { MARKETING_CATEGORIES, CONTENT_TYPES, PLATFORMS, isoDate, apiError, formatDate } from '@/config/marketingConfig.js'

const RANGES = [
  { days: 7, label: '7 days' },
  { days: 30, label: '30 days' },
  { days: 90, label: '90 days' },
  { days: 365, label: '12 months' },
]
const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const RING = 2 * Math.PI * 26

// Validated categorical slots 1–3 (blue, orange, aqua) — light and dark steps.
const SERIES = {
  light: ['#2a78d6', '#eb6834', '#1baf7a'],
  dark: ['#3987e5', '#d95926', '#199e70'],
}

const rangeDays = ref(30)
const category = ref('')
const data = ref(null)
const loading = ref(false)
const root = ref(null)
const isDark = ref(document.documentElement.getAttribute('data-theme') === 'dark')

const series = computed(() => (isDark.value ? SERIES.dark : SERIES.light))
const ink = computed(() => (isDark.value ? { text: '#D1D5DB', grid: 'rgba(209,213,219,0.12)' } : { text: '#52514e', grid: 'rgba(17,24,39,0.07)' }))
const brand = computed(() => getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#733E87')

const k = computed(() => data.value?.kpis || {})
const prev = computed(() => data.value?.previous || {})
const rangeLabel = computed(() => (data.value
  ? `${formatDate(data.value.range.from, { day: '2-digit', month: 'short' })} – ${formatDate(data.value.range.to, { day: '2-digit', month: 'short', year: 'numeric' })}`
  : ''))

let seq = 0
async function load() {
  const mine = ++seq
  loading.value = true
  const to = new Date()
  const from = new Date()
  from.setDate(from.getDate() - (rangeDays.value - 1))
  try {
    const { data: res } = await api.get('/marketing/analytics', {
      params: { from: isoDate(from), to: isoDate(to), category: category.value || undefined },
    })
    if (mine === seq) {
      const first = !data.value
      data.value = res
      nextTick(() => animateIn(first))
    }
  } catch (err) {
    if (mine === seq) Swal.fire({ icon: 'error', title: 'Could not load analytics', text: apiError(err) })
  } finally {
    if (mine === seq) loading.value = false
  }
}
watch([rangeDays, category], load)

/** Panels rise in on first load; numbers count up on every load. */
function animateIn(first) {
  const el = root.value
  if (!el) return
  if (first) staggerIn(el.querySelectorAll('.mk-hero-an, .mk-tile, .mk-panel'), { y: 18, stagger: 0.05 })
  el.querySelectorAll('[data-count]').forEach((n) => {
    const raw = n.dataset.count
    if (raw === '' || raw === undefined) return
    const v = Number(raw)
    countUp(n, v, { decimals: Number.isInteger(v) ? 0 : 1 })
  })
  const hero = el.querySelector('[data-count-to]')
  if (hero) countUp(hero, k.value.done ?? 0, { duration: 1.1 })
}

let themeObserver = null
onMounted(() => {
  load()
  themeObserver = new MutationObserver(() => {
    isDark.value = document.documentElement.getAttribute('data-theme') === 'dark'
  })
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] })
})
onBeforeUnmount(() => themeObserver?.disconnect())

// ---------- helpers ----------
const pct = (n, total) => (total ? Math.round((n / total) * 100) : 0)
const firstName = (name) => String(name || '').split(/\s+/)[0]

/** Change vs previous period. `lowerIsBetter` flips the good/bad color (e.g. days to done). */
function delta(cur, before, lowerIsBetter = false) {
  if (cur === null || cur === undefined || before === null || before === undefined) return null
  if (!before && !cur) return null
  if (!before) return { dir: lowerIsBetter ? 'is-bad' : 'is-good', icon: 'lucide:sparkles', label: 'new' }
  const change = Math.round(((cur - before) / before) * 100)
  if (change === 0) return { dir: 'is-flat', icon: 'lucide:minus', label: '0%' }
  const up = change > 0
  return {
    dir: (up !== lowerIsBetter) ? 'is-good' : 'is-bad',
    icon: up ? 'lucide:trending-up' : 'lucide:trending-down',
    label: `${up ? '+' : ''}${change}%`,
  }
}

const postRate = computed(() => pct(k.value.posts_posted ?? 0, k.value.posts_scheduled ?? 0))

const champion = computed(() => (data.value?.workload?.[0]?.done ? data.value.workload[0] : data.value?.workload?.[0]))

const rowTotals = computed(() => (data.value?.heatmap || []).map((r) => ({
  scheduled: r.days.reduce((n, d) => n + d.scheduled, 0),
  posted: r.days.reduce((n, d) => n + d.posted, 0),
})))
const heatMax = computed(() => Math.max(1, ...(data.value?.heatmap || []).flatMap((r) => r.days.map((d) => d.scheduled))))
const heatAlpha = (n) => (n ? 0.12 + 0.78 * (n / heatMax.value) : 0)

const busiestDay = computed(() => {
  const totals = WEEKDAYS.map((_, i) => (data.value?.heatmap || []).reduce((n, r) => n + r.days[i].scheduled, 0))
  const max = Math.max(...totals)
  return max ? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][totals.indexOf(max)] : ''
})
const topPlatform = computed(() => {
  const rows = (data.value?.posts_by_platform || []).filter((p) => p.posted || p.scheduled)
  if (!rows.length) return null
  return [...rows].sort((a, b) => b.posted - a.posted || b.scheduled - a.scheduled)[0]
})

const pipelineTotal = computed(() => (data.value?.by_stage || []).reduce((n, s) => n + s.count, 0))

const mixTitle = computed(() => (category.value === 'production' || !category.value ? 'Production by type' : 'Cards by board'))
const mixRows = computed(() => {
  if (!data.value) return []
  if (category.value === 'production' || !category.value) {
    return data.value.by_type.map((t) => ({ label: CONTENT_TYPES[t.key]?.label || t.key, icon: CONTENT_TYPES[t.key]?.icon, count: t.count }))
  }
  return data.value.by_category.map((c) => ({ label: MARKETING_CATEGORIES[c.key]?.label || c.key, icon: MARKETING_CATEGORIES[c.key]?.icon, count: c.count }))
})
const mixMax = computed(() => Math.max(1, ...mixRows.value.map((r) => r.count)))

const workMax = computed(() => Math.max(1, ...(data.value?.workload || []).map((w) => w.total)))
const revMax = computed(() => Math.max(1, ...(data.value?.revisions || []).map((r) => r.count)))

/** Podium order 2 · 1 · 3 so the winner stands in the middle. */
const podium = computed(() => {
  const top = (data.value?.workload || []).slice(0, 3).map((w, i) => ({ ...w, rank: i + 1 }))
  return [top[1], top[0], top[2]].filter(Boolean)
})

// ---------- charts ----------
function base(extra = {}) {
  return {
    chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: ink.value.text, background: 'transparent', animations: { speed: 500 } },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    grid: { borderColor: ink.value.grid, strokeDashArray: 3 },
    dataLabels: { enabled: false },
    legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', markers: { size: 6 } },
    tooltip: { theme: isDark.value ? 'dark' : 'light' },
    ...extra,
  }
}

const sparkOptions = computed(() => ({
  chart: { sparkline: { enabled: true }, animations: { speed: 600 } },
  colors: [brand.value],
  stroke: { width: 2, curve: 'smooth' },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
  tooltip: { theme: isDark.value ? 'dark' : 'light', x: { show: false }, y: { title: { formatter: () => 'Done' } } },
}))

const weeklySeries = computed(() => [
  { name: 'Created', data: (data.value?.weekly || []).map((w) => w.created) },
  { name: 'Done', data: (data.value?.weekly || []).map((w) => w.done) },
])
const weeklyOptions = computed(() => base({
  colors: series.value.slice(0, 2),
  stroke: { width: 2, curve: 'smooth' },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.28, opacityTo: 0.02 } },
  markers: { size: 0, hover: { size: 5 } },
  xaxis: { categories: (data.value?.weekly || []).map((w) => w.week), tickAmount: 12 },
  yaxis: { labels: { formatter: (v) => Math.round(v) } },
  tooltip: { theme: isDark.value ? 'dark' : 'light', shared: true, intersect: false },
}))
</script>

<style scoped>
.mk-an__bar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; margin-bottom: 16px; }
.mk-an__ranges { display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 3px; border-radius: 12px; background: var(--neutral-100); }
.mk-an__ranges button {
  display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 0; border-radius: 9px;
  background: transparent; font-size: 13px !important; font-weight: 600 !important; color: var(--text-secondary-light);
  transition: background 0.15s, color 0.15s;
}
.mk-an__ranges button.active { color: var(--color-on-primary); background: var(--color-primary); box-shadow: 0 4px 10px -4px rgba(var(--color-primary-rgb), 0.6); }
.mk-an__body { display: flex; flex-direction: column; gap: 14px; transition: opacity 0.2s; }
.is-loading { opacity: 0.55; }

/* ---------- Hero ---------- */
.mk-hero-an {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 22px 24px;
  border-radius: 22px;
  color: var(--color-on-primary);
  background: var(--gradient-primary);
  overflow: hidden;
  isolation: isolate;
}
.mk-hero-an__orb { position: absolute; z-index: -1; border-radius: 50%; background: rgba(255, 255, 255, 0.08); animation: mk-orb 9s ease-in-out infinite; }
.mk-hero-an__orb--1 { width: 220px; height: 220px; right: -60px; top: -90px; }
.mk-hero-an__orb--2 { width: 140px; height: 140px; right: 28%; bottom: -80px; background: rgba(212, 175, 55, 0.18); animation-delay: -3s; }
.mk-hero-an__orb--3 { width: 70px; height: 70px; left: 42%; top: -30px; animation-delay: -6s; }
@keyframes mk-orb {
  0%, 100% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(-14px, 10px) scale(1.08); }
}
.mk-hero-an__eyebrow {
  display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px;
  font-size: 11.5px !important; font-weight: 600 !important; background: rgba(255, 255, 255, 0.14);
}
.mk-hero-an h3 { margin: 10px 0 6px; font-size: 22px !important; font-weight: 700 !important; color: var(--color-on-primary); }
.mk-hero-an h3 b { display: inline-block; font-size: 40px !important; font-weight: 800 !important; line-height: 1; margin-right: 4px; font-variant-numeric: tabular-nums; }
.mk-hero-an p { margin: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 13px !important; color: rgba(255, 255, 255, 0.85); }
.mk-hero-an__facts { display: flex; flex-wrap: wrap; gap: 10px; }
.mk-fact {
  display: flex; align-items: center; gap: 10px; min-width: 170px; padding: 10px 14px 10px 10px; border-radius: 16px;
  background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.18); backdrop-filter: blur(6px);
  transition: transform 0.2s, background 0.2s;
}
.mk-fact:hover { transform: translateY(-2px); background: rgba(255, 255, 255, 0.18); }
.mk-fact__icon {
  width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; overflow: hidden;
  font-size: 18px !important; color: var(--color-primary); background: #fff; flex-shrink: 0;
}
.mk-fact__icon img { width: 100%; height: 100%; object-fit: cover; }
.mk-fact small { display: block; font-size: 11px !important; color: rgba(255, 255, 255, 0.75); }
.mk-fact b { font-size: 14px !important; font-weight: 700 !important; }

/* ---------- Deltas (status color + icon + label, never color alone) ---------- */
.mk-delta {
  display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 999px;
  font-size: 11.5px !important; font-weight: 700 !important; white-space: nowrap;
}
.mk-delta.is-good { color: #15803d; background: #dcfce7; }
.mk-delta.is-bad { color: #b91c1c; background: #fee2e2; }
.mk-delta.is-flat { color: var(--text-secondary-light); background: var(--neutral-100); }
.mk-delta--light.is-good, .mk-delta--light.is-bad, .mk-delta--light.is-flat { color: #fff; background: rgba(255, 255, 255, 0.18); }

/* ---------- KPI tiles ---------- */
.mk-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
.mk-tile {
  position: relative; padding: 14px 16px; border-radius: 18px; background: var(--white); border: 1px solid var(--border-color);
  overflow: hidden; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.mk-tile:hover { transform: translateY(-2px); border-color: rgba(var(--color-primary-rgb), 0.35); box-shadow: 0 14px 28px -18px rgba(var(--color-primary-rgb), 0.55); }
.mk-tile__head { display: flex; align-items: center; gap: 8px; font-size: 12.5px !important; font-weight: 600 !important; color: var(--text-secondary-light); }
.mk-tile__icon {
  width: 28px; height: 28px; border-radius: 9px; display: grid; place-items: center; font-size: 15px !important;
  color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.1); transition: transform 0.3s cubic-bezier(.3,1.6,.5,1);
}
.mk-tile:hover .mk-tile__icon { transform: rotate(-10deg) scale(1.12); }
.mk-tile__row { display: flex; align-items: center; gap: 8px; }
.mk-tile__num { display: block; margin: 8px 0 2px; font-size: 26px !important; font-weight: 800 !important; line-height: 1.1; color: var(--text-primary-light); font-variant-numeric: tabular-nums; }
.mk-tile__of { font-size: 15px !important; font-weight: 600 !important; color: var(--text-secondary-light); }
.mk-tile small { font-size: 11.5px !important; color: var(--text-secondary-light); }
.mk-tile__spark { margin: 2px -8px -10px; }
.mk-tile--ring { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.mk-ring { width: 76px; height: 76px; flex-shrink: 0; transform: rotate(-90deg); }
.mk-ring circle { fill: none; stroke-width: 7; }
.mk-ring__track { stroke: var(--neutral-100); }
.mk-ring__value {
  stroke: var(--color-primary); stroke-linecap: round; stroke-dasharray: 163.36; stroke-dashoffset: var(--off);
  animation: mk-ring 1.1s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes mk-ring { from { stroke-dashoffset: 163.36; } }
.mk-ring text { transform: rotate(90deg); transform-origin: 32px 32px; font-size: 13px; font-weight: 800; fill: var(--text-primary-light); }

/* ---------- Panels ---------- */
.mk-an__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.mk-panel { padding: 16px; border-radius: 18px; background: var(--white); border: 1px solid var(--border-color); min-width: 0; }
.mk-panel--wide { grid-column: 1 / -1; }
.mk-panel header { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.mk-panel h6 { margin: 0; font-size: 13.5px !important; font-weight: 700 !important; color: var(--text-primary-light); }
.mk-panel header small { font-size: 11.5px !important; color: var(--text-secondary-light); }
@media (max-width: 900px) { .mk-an__grid { grid-template-columns: 1fr; } }

/* Pipeline flow strip — stage colors follow each stage (the entity) */
.mk-flow { display: flex; gap: 2px; height: 22px; margin-bottom: 14px; border-radius: 8px; overflow: hidden; }
.mk-flow__seg { min-width: 6px; transform-origin: left center; animation: mk-grow 0.7s cubic-bezier(.2,.8,.2,1) both; transition: filter 0.15s; }
.mk-flow__seg:hover { filter: brightness(1.1) saturate(1.1); }
.mk-flow__legend { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
.mk-flow__legend li { display: flex; align-items: center; gap: 8px; font-size: 12.5px !important; color: var(--text-primary-light); padding: 5px 6px; border-radius: 8px; }
.mk-flow__legend li:hover { background: var(--neutral-50); }
.mk-flow__dot { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; }
.mk-flow__name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mk-flow__legend b { font-weight: 700 !important; font-variant-numeric: tabular-nums; }
.mk-flow__legend small { width: 36px; text-align: right; font-size: 11px !important; color: var(--text-secondary-light); }

/* Generic bar (single series → brand hue) */
.mk-bar { height: 6px; border-radius: 6px; background: var(--neutral-100); overflow: hidden; }
.mk-bar--thick { height: 10px; }
.mk-bar span {
  display: block; height: 100%; min-width: 4px; border-radius: 6px; background: var(--gradient-primary);
  transform-origin: left center; animation: mk-grow 0.8s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes mk-grow { from { transform: scaleX(0); } }

/* Content mix */
.mk-mix { display: flex; flex-direction: column; gap: 12px; }
.mk-mix__item { display: flex; align-items: center; gap: 12px; }
.mk-mix__icon {
  width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font-size: 18px !important;
  color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); flex-shrink: 0; transition: transform 0.3s cubic-bezier(.3,1.6,.5,1);
}
.mk-mix__item:hover .mk-mix__icon { transform: translateY(-2px) rotate(-8deg); }
.mk-mix__body { flex: 1; min-width: 0; }
.mk-mix__top { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12.5px !important; color: var(--text-primary-light); }
.mk-mix__top b { font-weight: 700 !important; font-variant-numeric: tabular-nums; }

/* Leaderboard podium */
.mk-podium { display: flex; align-items: flex-end; justify-content: center; gap: 10px; margin: 4px 0 12px; }
.mk-podium__spot { position: relative; width: 31%; max-width: 130px; display: flex; flex-direction: column; align-items: center; text-align: center; }
.mk-podium__medal { font-size: 20px; line-height: 1; margin-bottom: 4px; animation: mk-medal 2.8s ease-in-out infinite; }
.mk-podium__spot.is-1 .mk-podium__medal { font-size: 26px; }
@keyframes mk-medal { 0%, 100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-4px) rotate(8deg); } }
.mk-podium__avatar {
  width: 46px; height: 46px; border-radius: 50%; overflow: hidden; background: var(--gradient-primary);
  box-shadow: 0 0 0 3px var(--white), 0 0 0 5px rgba(var(--color-primary-rgb), 0.25);
}
.mk-podium__spot.is-1 .mk-podium__avatar { width: 58px; height: 58px; box-shadow: 0 0 0 3px var(--white), 0 0 0 5px var(--color-primary-glow, #d4af37); }
.mk-podium__avatar img { width: 100%; height: 100%; object-fit: cover; }
.mk-podium__spot b { margin-top: 6px; font-size: 12.5px !important; font-weight: 700 !important; color: var(--text-primary-light); max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mk-podium__spot small { font-size: 11px !important; color: var(--text-secondary-light); }
.mk-podium__step {
  width: 100%; margin-top: 8px; display: grid; place-items: center; border-radius: 12px 12px 4px 4px;
  font-size: 18px !important; font-weight: 800 !important; color: var(--color-on-primary); background: var(--gradient-primary);
  transform-origin: bottom center; animation: mk-rise-step 0.7s cubic-bezier(.2,.8,.2,1.2) both;
}
.mk-podium__spot.is-1 .mk-podium__step { height: 64px; animation-delay: 0.15s; }
.mk-podium__spot.is-2 .mk-podium__step { height: 44px; opacity: 0.85; }
.mk-podium__spot.is-3 .mk-podium__step { height: 32px; opacity: 0.7; animation-delay: 0.3s; }
@keyframes mk-rise-step { from { transform: scaleY(0); } }

.mk-legend { list-style: none; display: flex; flex-wrap: wrap; gap: 12px; margin: 0 0 8px; padding: 0; font-size: 11.5px !important; color: var(--text-secondary-light); }
.mk-legend li { display: inline-flex; align-items: center; gap: 5px; }
.mk-legend span { width: 9px; height: 9px; border-radius: 3px; }
.mk-rank { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; max-height: 220px; overflow-y: auto; }
.mk-rank li { display: grid; grid-template-columns: 22px minmax(0, 1fr) 1.2fr 28px; align-items: center; gap: 8px; padding: 5px 6px; border-radius: 8px; font-size: 12.5px !important; }
.mk-rank li:hover { background: var(--neutral-50); }
.mk-rank__pos { font-weight: 700 !important; color: var(--text-secondary-light); text-align: center; }
.mk-rank__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-primary-light); }
.mk-rank b { text-align: right; font-variant-numeric: tabular-nums; }
.mk-stack { display: flex; gap: 2px; height: 8px; }
.mk-stack span { border-radius: 4px; transform-origin: left center; animation: mk-grow 0.8s cubic-bezier(.2,.8,.2,1) both; }

/* Revisions */
.mk-rev { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.mk-rev li { display: grid; grid-template-columns: minmax(0, 0.9fr) 1.4fr 28px; align-items: center; gap: 10px; font-size: 12.5px !important; }
.mk-rev__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-primary-light); }
.mk-rev b { text-align: right; font-variant-numeric: tabular-nums; }
.mk-empty--happy b { font-size: 14px !important; color: var(--text-primary-light); }
.mk-empty__badge {
  width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; font-size: 24px !important;
  color: var(--color-on-primary); background: var(--gradient-primary); animation: mk-medal 2.6s ease-in-out infinite;
}

/* Posting heatmap — one hue (brand), light → dark */
.mk-heat { display: grid; grid-template-columns: minmax(120px, 160px) repeat(7, minmax(34px, 1fr)) 64px; gap: 5px; align-items: center; overflow-x: auto; }
.mk-heat__day { font-size: 11px !important; font-weight: 600 !important; text-align: center; color: var(--text-secondary-light); }
.mk-heat__platform { display: flex; align-items: center; gap: 6px; font-size: 12.5px !important; color: var(--text-primary-light); white-space: nowrap; }
.mk-heat__cell {
  height: 34px; border-radius: 8px; display: grid; place-items: center;
  font-size: 12px !important; font-weight: 700 !important; font-variant-numeric: tabular-nums;
  color: var(--text-primary-light);
  background: rgba(var(--color-primary-rgb), var(--a));
  animation: mk-cell 0.4s cubic-bezier(.2,.8,.2,1.3) both;
  transition: transform 0.15s, box-shadow 0.15s;
}
.mk-heat__cell:not(.is-empty) { color: var(--color-primary); }
.mk-heat__cell.is-strong { color: #fff; }
.mk-heat__cell.is-empty { background: var(--neutral-50); border: 1px dashed var(--border-color); }
.mk-heat__cell:hover { transform: scale(1.12); box-shadow: 0 6px 14px -6px rgba(var(--color-primary-rgb), 0.7); position: relative; z-index: 1; }
@keyframes mk-cell { from { transform: scale(0.4); opacity: 0; } }
.mk-heat__total { font-size: 12px !important; font-weight: 600 !important; text-align: right; color: var(--text-secondary-light); font-variant-numeric: tabular-nums; }

@media (max-width: 576px) {
  .mk-hero-an { padding: 18px; }
  .mk-hero-an h3 b { font-size: 32px !important; }
  .mk-fact { min-width: 0; flex: 1 1 100%; }
  .mk-flow__legend { grid-template-columns: 1fr; }
}

@media (prefers-reduced-motion: reduce) {
  .mk-hero-an__orb, .mk-ring__value, .mk-flow__seg, .mk-bar span, .mk-stack span, .mk-podium__medal,
  .mk-podium__step, .mk-empty__badge, .mk-heat__cell { animation: none !important; }
  .mk-tile, .mk-tile__icon, .mk-fact, .mk-mix__icon, .mk-heat__cell { transition: none; }
  .mk-tile:hover, .mk-fact:hover { transform: none; }
}
</style>
