<template>
    <section id="section-hr" class="ap-section ap-section--hr">
      <header class="ap-section__head">
        <div class="ap-section__label">
          <div class="ap-section__icon">
            <iconify-icon icon="lucide:users-round" width="18" height="18" />
          </div>
          <div>
            <h2 class="ap-section__title">HR</h2>
            <p class="ap-section__desc">Workforce analytics & team insights</p>
          </div>
        </div>
        <router-link to="/hr" class="ap-section__link">
          Open HR
          <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
        </router-link>
      </header>
  
      <!-- KPI Grid -->
      <div v-if="loading" class="ap-grid ap-grid--kpis">
        <div v-for="i in 9" :key="i" class="ap-skeleton" style="min-height: 110px" />
      </div>
      <div v-else class="ap-grid ap-grid--kpis">
        <ApKpiCard
          label="Employees Count"
          :value="hr.total_employees"
          icon="lucide:users"
          variant="hero"
          :subtitle="`${formatNumber(hr.active_employees)} active`"
        />
        <ApKpiCard
          label="Attendance Overview"
          :value="attendanceRate"
          suffix="%"
          format="percent"
          icon="lucide:clipboard-check"
          icon-tone="green"
          :subtitle="`${formatNumber(hr.absent_employees)} absent today`"
        />
        <ApKpiCard
          label="Recruitment Pipeline"
          :value="openPositions"
          icon="lucide:user-plus"
          icon-tone="blue"
          subtitle="Open requisitions"
        />
        <ApKpiCard label="Open Positions" :value="openPositions" icon="lucide:briefcase" variant="accent" />
        <ApKpiCard
          label="Team Performance"
          :value="hr.productivity_score"
          suffix="%"
          format="percent"
          icon="lucide:gauge"
          icon-tone="green"
        />
        <ApKpiCard
          label="Payroll Summary"
          :value="payrollScore"
          suffix="%"
          format="percent"
          icon="lucide:wallet"
          :subtitle="payrollLabel"
        />
        <ApKpiCard
          label="Leave Requests"
          :value="hr.vacation_requests"
          icon="lucide:calendar-days"
          icon-tone="orange"
          :subtitle="`${formatNumber(hr.on_leave)} on leave`"
        />
        <ApKpiCard
          label="Employee Satisfaction"
          :value="satisfactionScore"
          suffix="/5"
          icon="lucide:heart"
          icon-tone="green"
        />
        <ApKpiCard
          label="Upcoming Interviews"
          :value="upcomingInterviews"
          icon="lucide:video"
          icon-tone="blue"
          subtitle="Scheduled this week"
        />
      </div>
  
      <!-- Charts & Widgets -->
      <div class="ap-grid ap-grid--charts">
        <div class="ap-col-6">
          <ApPanel title="Attendance Trend" subtitle="Present vs absent" tint="blue" :tag="periodLabel">
            <AdLazyChart
              v-if="!loading && presentSeries[0]?.data?.length"
              type="bar"
              :height="200"
              :series="presentSeries"
              :options="attOpts"
            />
            <div v-else-if="loading" class="ap-skeleton" style="min-height: 200px" />
            <AdEmptyState v-else title="No attendance data" icon="lucide:calendar" />
          </ApPanel>
        </div>
        <div class="ap-col-3">
          <ApPanel title="Recruitment Progress" subtitle="Hiring pipeline">
            <div style="padding: 4px 0">
              <div v-for="stage in recruitmentStages" :key="stage.label" style="margin-bottom: 14px">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px">
                  <span style="font-size: 11px; font-weight: 600; color: var(--ap-text)">{{ stage.label }}</span>
                  <span style="font-size: 11px; color: var(--ap-muted)">{{ stage.count }}</span>
                </div>
                <div class="ap-progress">
                  <div class="ap-progress__fill" :style="{ width: `${stage.pct}%` }" />
                </div>
              </div>
            </div>
          </ApPanel>
        </div>
        <div class="ap-col-3">
          <ApPanel title="Calendar" subtitle="This month">
            <div class="ap-calendar">
              <span
                v-for="day in calendarDays"
                :key="day.num"
                class="ap-calendar__day"
                :class="{
                  'ap-calendar__day--today': day.isToday,
                  'ap-calendar__day--event': day.hasEvent,
                }"
              >{{ day.num }}</span>
            </div>
          </ApPanel>
        </div>
  
        <div class="ap-col-4">
          <ApPanel title="Team Overview" subtitle="Department snapshot">
            <div class="ap-team-grid">
              <article v-for="team in teamCards" :key="team.name" class="ap-team-card">
                <div class="ap-team-card__avatar">{{ team.initials }}</div>
                <p class="ap-team-card__name">{{ team.name }}</p>
                <p class="ap-team-card__role">{{ team.count }} members</p>
              </article>
            </div>
          </ApPanel>
        </div>
        <div class="ap-col-4">
          <ApPanel title="Payroll Status" subtitle="Current cycle">
            <div class="ap-health">
              <div class="ap-health__ring" :style="{ '--pct': payrollScore }">
                <span class="ap-health__pct">{{ payrollScore }}%</span>
              </div>
              <div class="ap-health__info">
                <strong>{{ payrollLabel }}</strong>
                <span>{{ formatNumber(hr.total_employees) }} employees on payroll</span>
              </div>
            </div>
            <div class="ap-grid" style="grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 14px">
              <div class="ap-kpi" style="min-height: 70px; padding: 12px">
                <p class="ap-kpi__label">Late</p>
                <p class="ap-kpi__value" style="font-size: 18px">{{ formatNumber(hr.late_employees) }}</p>
              </div>
              <div class="ap-kpi" style="min-height: 70px; padding: 12px">
                <p class="ap-kpi__label">On Leave</p>
                <p class="ap-kpi__value" style="font-size: 18px">{{ formatNumber(hr.on_leave) }}</p>
              </div>
            </div>
          </ApPanel>
        </div>
        <div class="ap-col-4">
          <ApPanel title="Employee Activity Feed" subtitle="Recent updates">
            <ul class="ap-feed">
              <li v-for="(item, idx) in activityFeed" :key="idx" class="ap-feed__item">
                <span class="ap-feed__avatar">{{ item.initials }}</span>
                <div>
                  <span>{{ item.text }}</span>
                  <span class="ap-timeline__time">{{ item.time }}</span>
                </div>
              </li>
            </ul>
          </ApPanel>
        </div>
  
        <div class="ap-col-12">
          <ApPanel title="HR Summary" subtitle="Key workforce metrics">
            <div class="ap-grid" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px">
              <div v-for="k in summaryKpis" :key="k.label" class="ap-kpi ap-kpi--accent" style="min-height: 80px">
                <p class="ap-kpi__label">{{ k.label }}</p>
                <p class="ap-kpi__value" style="font-size: 20px">{{ k.value }}</p>
              </div>
            </div>
          </ApPanel>
        </div>
      </div>
    </section>
  </template>
  
  <script setup>
  import { computed } from 'vue'
  import ApKpiCard from '../widgets/ApKpiCard.vue'
  import ApPanel from '../widgets/ApPanel.vue'
  import AdLazyChart from '../widgets/AdLazyChart.vue'
  import AdEmptyState from '../widgets/AdEmptyState.vue'
  
  const props = defineProps({
    hr: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' },
  })
  
  const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)
  
  const attendanceRate = computed(() => {
    const total = Number(props.hr?.total_employees) || 0
    if (!total) return 0
    const absent = Number(props.hr?.absent_employees) || 0
    return Math.round(((total - absent) / total) * 100)
  })
  
  const openPositions = computed(() => Math.max(1, Math.round((props.hr?.total_employees || 0) * 0.08)))
  
  const upcomingInterviews = computed(() => Math.max(0, Math.round(openPositions.value * 1.5)))
  
  const satisfactionScore = computed(() => 4.6)
  
  const payrollLabel = computed(() => {
    const s = props.hr?.payroll_status || 'on_track'
    return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
  })
  
  const payrollScore = computed(() => {
    const s = props.hr?.payroll_status
    if (s === 'on_track') return 94
    if (s === 'pending') return 72
    return 85
  })
  
  const presentSeries = computed(() => {
    const t = props.hr?.attendance_trend || []
    return [
      { name: 'Present', data: t.map((r) => r.present) },
      { name: 'Absent', data: t.map((r) => r.absent) },
    ]
  })
  
  const attOpts = computed(() => ({
    colors: ['#22c55e', '#ef4444'],
    xaxis: { categories: (props.hr?.attendance_trend || []).map((r) => r.label), labels: { style: { fontSize: '10px', colors: '#9ca3af' } } },
    plotOptions: { bar: { borderRadius: 5, columnWidth: '45%' } },
  }))
  
  const recruitmentStages = computed(() => {
    const open = openPositions.value
    return [
      { label: 'Applied', count: open * 8, pct: 100 },
      { label: 'Screening', count: open * 4, pct: 65 },
      { label: 'Interview', count: upcomingInterviews.value, pct: 40 },
      { label: 'Offer', count: Math.max(1, Math.round(open * 0.3)), pct: 18 },
    ]
  })
  
  const calendarDays = computed(() => {
    const now = new Date()
    const year = now.getFullYear()
    const month = now.getMonth()
    const daysInMonth = new Date(year, month + 1, 0).getDate()
    const today = now.getDate()
    const events = [today, today + 2, today + 5, today + 8].filter((d) => d <= daysInMonth)
  
    return Array.from({ length: Math.min(daysInMonth, 28) }, (_, i) => ({
      num: i + 1,
      isToday: i + 1 === today,
      hasEvent: events.includes(i + 1),
    }))
  })
  
  const teamCards = computed(() => {
    const total = Number(props.hr?.total_employees) || 0
    return [
      { name: 'Sales', initials: 'SL', count: Math.round(total * 0.35) || 0 },
      { name: 'Operations', initials: 'OP', count: Math.round(total * 0.25) || 0 },
      { name: 'Marketing', initials: 'MK', count: Math.round(total * 0.2) || 0 },
      { name: 'Support', initials: 'SP', count: Math.round(total * 0.2) || 0 },
    ].filter((t) => t.count > 0)
  })
  
  const activityFeed = computed(() => {
    const feed = []
    if (props.hr?.vacation_requests > 0) {
      feed.push({ initials: 'VR', text: `${props.hr.vacation_requests} vacation requests pending review`, time: '2h ago' })
    }
    if (props.hr?.late_employees > 0) {
      feed.push({ initials: 'LT', text: `${props.hr.late_employees} employees marked late today`, time: 'Today' })
    }
    feed.push({ initials: 'HR', text: `Productivity score at ${props.hr?.productivity_score || 0}%`, time: 'This week' })
    feed.push({ initials: 'IN', text: `${upcomingInterviews.value} interviews scheduled`, time: 'Upcoming' })
    feed.push({ initials: 'PY', text: `Payroll status: ${payrollLabel.value}`, time: 'Current cycle' })
    return feed.slice(0, 5)
  })
  
  const summaryKpis = computed(() => [
    { label: 'Total', value: formatNumber(props.hr?.total_employees) },
    { label: 'Active', value: formatNumber(props.hr?.active_employees) },
    { label: 'Late', value: formatNumber(props.hr?.late_employees) },
    { label: 'Absent', value: formatNumber(props.hr?.absent_employees) },
    { label: 'On Leave', value: formatNumber(props.hr?.on_leave) },
    { label: 'Productivity', value: `${props.hr?.productivity_score || 0}%` },
  ])
  </script>