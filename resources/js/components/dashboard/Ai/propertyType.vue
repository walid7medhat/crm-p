<template>
  <div class="ai-panel ai-panel--property-type">
    <div class="ai-panel__body">
      <div class="ai-panel__head ai-property-type__head">
        <h2 class="ai-panel__title">Property Type</h2>
        <span v-if="!loading && propertyTypes.length" class="ai-property-type__count">
          ({{ formatNumber(totalListings) }} Listings)
        </span>
      </div>

      <div v-if="loading" class="ai-skeleton ai-chart ai-chart--property" />
      <div v-else-if="propertyTypes.length === 0" class="ai-empty">
        <iconify-icon icon="lucide:bar-chart-3" width="32" height="32" class="mb-2 opacity-50" />
        <p class="mb-0">No active listings found</p>
      </div>
      <div v-else class="ai-property-chart-scroll">
        <div ref="chartRef" class="ai-chart ai-chart--property" />
      </div>
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts'
import { ref, onMounted, computed, onBeforeUnmount } from 'vue'
import axios from 'axios'

/** Y-axis max with ~6 ticks (0, 100, 200 … or 0, 200, 400 …) */
function resolveYAxisMax(maxValue) {
  const max = Number(maxValue) || 0
  if (max <= 0) return 100
  if (max <= 600) return 600
  const step = max <= 1200 ? 200 : 500
  return Math.ceil(max / step) * step
}

export default {
  name: 'PropertyTypesWithListings',
  setup() {
    const propertyTypes = ref([])
    const loading = ref(true)
    const chartRef = ref(null)
    let chart = null

    const chartRows = computed(() =>
      [...propertyTypes.value]
        .sort((a, b) => (b.listings_count || 0) - (a.listings_count || 0)),
    )

    const totalListings = computed(() =>
      chartRows.value.reduce((sum, pt) => sum + (pt.listings_count || 0), 0),
    )

    const fetchPropertyTypes = async () => {
      try {
        loading.value = true
        const token = localStorage.getItem('token')
        const response = await axios.get('/api/dashboard/property-types-with-listings', {
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        })
        if (response.data.success) {
          propertyTypes.value = response.data.data || []
          if (propertyTypes.value.length > 0) {
            setTimeout(renderChart, 80)
          }
        }
      } catch (error) {
        console.error('Error fetching property types:', error)
      } finally {
        loading.value = false
      }
    }

    const renderChart = () => {
      if (!chartRef.value || !chartRows.value.length) return
      if (chart) chart.destroy()

      const categories = chartRows.value.map((pt) => pt.name)
      const data = chartRows.value.map((pt) => pt.listings_count || 0)
      const yMax = resolveYAxisMax(Math.max(...data))
      const barCount = categories.length
      const chartWidth = barCount > 8 ? barCount * 68 : '100%'

      chart = new ApexCharts(chartRef.value, {
        series: [{ name: 'Listings', data }],
        chart: {
          type: 'bar',
          height: 220,
          width: chartWidth,
          toolbar: { show: false },
          fontFamily: 'Inter, system-ui, sans-serif',
          animations: { enabled: true, speed: 400 },
        },
        plotOptions: {
          bar: {
            borderRadius: 8,
            borderRadiusApplication: 'end',
            columnWidth: barCount > 8 ? '55%' : '42%',
            distributed: false,
          },
        },
        colors: ['#F97316'],
        fill: {
          type: 'gradient',
          gradient: {
            type: 'vertical',
            shadeIntensity: 0.15,
            opacityFrom: 1,
            opacityTo: 1,
            colorStops: [
              { offset: 0, color: '#FEF3C7', opacity: 1 },
              { offset: 55, color: '#FDBA74', opacity: 1 },
              { offset: 100, color: '#F97316', opacity: 1 },
            ],
          },
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
          categories,
          labels: {
            style: {
              fontSize: '12px',
              colors: '#64748b',
              fontWeight: 500,
            },
            rotate: 0,
            trim: true,
            hideOverlappingLabels: false,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
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
        grid: {
          borderColor: 'transparent',
          strokeDashArray: 4,
          xaxis: { lines: { show: false } },
          yaxis: { lines: { show: true } },
          padding: { left: 4, right: 8 },
        },
        tooltip: {
          theme: 'light',
          y: { formatter: (v) => `${v} listings` },
        },
      })
      chart.render()
    }

    const formatNumber = (num) => new Intl.NumberFormat().format(num || 0)

    onMounted(fetchPropertyTypes)
    onBeforeUnmount(() => {
      if (chart) chart.destroy()
    })

    return {
      propertyTypes,
      loading,
      chartRef,
      totalListings,
      formatNumber,
    }
  },
}
</script>
