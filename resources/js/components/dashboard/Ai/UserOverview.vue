<template>
  <div class="ai-panel ai-panel--chart-kpi">
    <div class="ai-panel__body ai-chart-kpi__body">
      <div class="ai-panel__head">
        <h2 class="ai-panel__title">Request Overview</h2>
        <label class="ai-period-select">
          <iconify-icon icon="lucide:calendar" width="15" height="15" class="ai-period-select__icon" />
          <select v-model="selectedTimeframe" class="ai-period-select__native" @change="onTimeframeChange">
            <option value="today">Today</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
             <option value="yearly">>Yearly</option>
          </select>
          <iconify-icon icon="lucide:chevrons-up-down" width="14" height="14" class="ai-period-select__chev" />
        </label>
      </div>

      <div class="ai-requests-radial">
        <div ref="chartRef" class="ai-requests-radial__chart" />
        <div class="ai-chart-legend ai-chart-legend--requests">
          <div class="ai-legend-item">
            <span class="ai-legend-dot ai-legend-dot--purple" />
            <span>New: <strong>{{ stats.new_leads }}</strong></span>
          </div>
          <div class="ai-legend-item">
            <span class="ai-legend-dot ai-legend-dot--green" />
            <span>Approved: <strong>{{ stats.approved_leads }}</strong></span>
          </div>
          <div class="ai-legend-item">
            <span class="ai-legend-dot ai-legend-dot--red" />
            <span>Rejected: <strong>{{ stats.rejected_leads }}</strong></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

export default {
  name: 'LeadsOverview',
  setup() {
    const selectedTimeframe = ref('weekly')
    const chartRef = ref(null)
    const stats = ref({
      new_leads: 0,
      approved_leads: 0,
      rejected_leads: 0,
    })
    let chart = null

    const ringPercents = () => {
      const n = Number(stats.value.new_leads) || 0
      const a = Number(stats.value.approved_leads) || 0
      const r = Number(stats.value.rejected_leads) || 0
      const max = Math.max(n, a, r, 1)
      return [(n / max) * 100, (a / max) * 100, (r / max) * 100]
    }

    const fetchData = async () => {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(
          `/api/dashboard/leads-overview?timeframe=${selectedTimeframe.value}`,
          { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
        )
        stats.value = response.data.data || stats.value
        renderChart()
      } catch (error) {
        console.error('Error fetching leads overview:', error)
      }
    }

    const renderChart = () => {
      if (!chartRef.value) return
      if (chart) chart.destroy()

      const series = ringPercents()

      chart = new ApexCharts(chartRef.value, {
        series,
        chart: {
          type: 'radialBar',
          height: 240,
          width: 240,
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        colors: ['#733E87', '#00BE23', '#DC3645'],
        labels: ['New', 'Approved', 'Rejected'],
        legend: { show: false },
        plotOptions: {
          radialBar: {
            offsetY: 0,
            startAngle: 0,
            endAngle: 270,
            hollow: {
              margin: 0,
              size: '28%',
              background: 'transparent',
            },
            track: {
              show: true,
              background: '#f1f5f9',
              strokeWidth: '100%',
              margin: 6,
            },
            dataLabels: {
              show: false,
            },
          },
        },
        stroke: {
          lineCap: 'round',
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: (_val, opts) => {
              const keys = ['new_leads', 'approved_leads', 'rejected_leads']
              const count = stats.value[keys[opts.seriesIndex]] ?? 0
              return `${count} request${count === 1 ? '' : 's'}`
            },
          },
        },
      })
      chart.render()
    }

    const onTimeframeChange = () => {
      fetchData()
    }

    onMounted(fetchData)
    onBeforeUnmount(() => {
      if (chart) chart.destroy()
    })

    return {
      selectedTimeframe,
      chartRef,
      stats,
      onTimeframeChange,
    }
  },
}
</script>
