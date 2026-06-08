<template>
  <section class="ex-section">
    <div class="ex-section__head">
      <span class="ex-section__title">HR Analytics</span>
    </div>

    <div v-if="loading" class="ex-kpi-grid">
      <AdKpiSkeleton v-for="i in 8" :key="i" />
    </div>
    <div v-else class="ex-kpi-grid ex-kpi-grid--wide">
      <AdKpiCard label="Total employees" :value="hr.total_employees" icon="lucide:users" />
      <AdKpiCard label="Active" :value="hr.active_employees" icon="lucide:user-check" tone="green" />
      <AdKpiCard label="Late" :value="hr.late_employees" icon="lucide:clock-alert" tone="orange" />
      <AdKpiCard label="Absent" :value="hr.absent_employees" icon="lucide:user-x" tone="red" />
      <AdKpiCard label="On leave" :value="hr.on_leave" icon="lucide:palm-tree" />
      <AdKpiCard label="Vacation requests" :value="hr.vacation_requests" icon="lucide:calendar-days" />
      <AdKpiCard label="Productivity" :value="hr.productivity_score" suffix="%" icon="lucide:gauge" tone="green" />
    </div>

    <div class="ex-charts-row">
      <AdPanel title="Attendance" subtitle="Present vs absent">
        <AdLazyChart v-if="!loading && presentSeries[0]?.data?.length" type="bar" :height="180" :series="presentSeries" :options="attOpts" :dark="dark" />
        <AdChartSkeleton v-else />
      </AdPanel>
      <AdPanel title="Payroll status">
        <div class="ex-closer-card">
          <iconify-icon icon="lucide:wallet" width="20" height="20" />
          <div>
            <strong>{{ payrollLabel }}</strong>
            <span>Current payroll cycle</span>
          </div>
        </div>
      </AdPanel>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import AdKpiCard from '../widgets/AdKpiCard.vue'
import AdKpiSkeleton from '../widgets/AdKpiSkeleton.vue'
import AdPanel from '../widgets/AdPanel.vue'
import AdLazyChart from '../widgets/AdLazyChart.vue'
import AdChartSkeleton from '../widgets/AdChartSkeleton.vue'

const props = defineProps({
  hr: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  dark: { type: Boolean, default: false },
})

const presentSeries = computed(() => {
  const t = props.hr?.attendance_trend || []
  return [
    { name: 'Present', data: t.map((r) => r.present) },
    { name: 'Absent', data: t.map((r) => r.absent) },
  ]
})

const attOpts = computed(() => ({
  colors: ['#16a34a', '#dc2626'],
  xaxis: { categories: (props.hr?.attendance_trend || []).map((r) => r.label), labels: { style: { fontSize: '9px' } } },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
}))

const payrollLabel = computed(() => {
  const s = props.hr?.payroll_status || 'unknown'
  return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
})
</script>
