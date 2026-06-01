<template>
  <div class="ai-panel ai-panel--chart-kpi">
    <div class="ai-panel__body ai-chart-kpi__body">
      <div class="ai-panel__head">
        <h2 class="ai-panel__title">Listings Statistics</h2>
        <label class="ai-period-select">
          <iconify-icon icon="lucide:calendar" width="15" height="15" class="ai-period-select__icon" />
          <select v-model="selectedPeriod" class="ai-period-select__native" @change="fetchData">
            <option value="yearly">Yearly</option>
            <option value="monthly">Monthly</option>
            <option value="weekly">Weekly</option>
          </select>
          <iconify-icon icon="lucide:chevrons-up-down" width="14" height="14" class="ai-period-select__chev" />
        </label>
      </div>
      <div ref="chartRef" class="ai-chart ai-chart--line-kpi" />
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

function resolveYAxisMax(maxValue) {
  const max = Number(maxValue) || 0
  if (max <= 0) return 100
  if (max <= 600) return 600
  const step = max <= 1200 ? 200 : 500
  return Math.ceil(max / step) * step
}

export default {
  name: 'ListingsStatistics',
  setup() {
    const selectedPeriod = ref('yearly')
    const chartRef = ref(null)
    let chart = null

    const fetchData = async () => {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(`/api/dashboard/listings-statistics?period=${selectedPeriod.value}`, {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        })
        updateChart(response.data.chart_data)
      } catch (error) {
        console.error('Error fetching listings statistics:', error)
      }
    }

    const updateChart = (chartData) => {
      if (!chartRef.value || !chartData?.values) return
      if (chart) chart.destroy()

      const yMax = resolveYAxisMax(Math.max(...chartData.values, 0))

      chart = new ApexCharts(chartRef.value, {
        series: [{ name: 'Listings', data: chartData.values }],
        chart: {
          height: 268,
          type: 'area',
          width: '100%',
          toolbar: { show: false },
          zoom: { enabled: false },
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        colors: ['#733E87'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#733E87'] },
        fill: {
          type: 'gradient',
          gradient: {
            type: 'vertical',
            shadeIntensity: 0.2,
            opacityFrom: 0.5,
            opacityTo: 0.02,
            stops: [0, 85, 100],
            colorStops: [
              { offset: 0, color: '#A78BFA', opacity: 0.55 },
              { offset: 100, color: '#733E87', opacity: 0.05 },
            ],
          },
        },
        markers: {
          size: 4,
          colors: ['#733E87'],
          strokeColors: '#fff',
          strokeWidth: 2,
          hover: { size: 6 },
        },
        tooltip: {
          theme: 'light',
          x: { show: true },
          y: { formatter: (v) => `${Math.round(v)} listing${v === 1 ? '' : 's'}` },
        },
        grid: {
          borderColor: 'transparent',
          strokeDashArray: 4,
          xaxis: { lines: { show: false } },
          yaxis: { lines: { show: true } },
          padding: { left: 8, right: 12 },
        },
        yaxis: {
          min: 0,
          max: yMax,
          tickAmount: yMax <= 600 ? 6 : 5,
          labels: {
            style: { fontSize: '11px', colors: '#94a3b8' },
            formatter: (v) => Math.round(v),
          },
        },
        xaxis: {
          categories: chartData.labels,
          labels: {
            style: { fontSize: '11px', colors: '#64748b', fontWeight: 500 },
            rotate: 0,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
      })
      chart.render()
    }

    onMounted(fetchData)
    onBeforeUnmount(() => {
      if (chart) chart.destroy()
    })

    return { selectedPeriod, chartRef, fetchData }
  },
}
</script>
