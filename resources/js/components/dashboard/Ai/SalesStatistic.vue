<template>
  <div class="col-xxl-6 col-xl-12">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
          <h6 class="text-lg mb-0">Listings Statistics</h6>
          <select v-model="selectedPeriod" @change="fetchData" class="form-select bg-base form-select-sm w-auto radius-8">
            <option value="yearly">Yearly</option>
            <option value="monthly">Monthly</option>
            <option value="weekly">Weekly</option>
          </select>
        </div>
        <div id="chart" class="pt-28 apexcharts-tooltip-style-1"></div>
      </div>
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts';
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
  name: 'ListingsStatistics',
  setup() {
    const selectedPeriod = ref('yearly');
    const stats = ref({
      total_listings: 0,
      growth_percentage: 0,
      daily_change: 0
    });
    let chart = null;

    const fetchData = async () => {
      try {
        const token = localStorage.getItem('token');
        const response = await axios.get(`/api/dashboard/listings-statistics?period=${selectedPeriod.value}`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        stats.value = response.data.data;
        updateChart(response.data.chart_data);
      } catch (error) {
        console.error('Error fetching listings statistics:', error);
      }
    };

    const updateChart = (chartData) => {
      if (chart) {
        chart.destroy();
      }

      // حساب الحد الأقصى للقيمة للـ Y-axis
      const maxValue = Math.max(...chartData.values);
      const yAxisMax = maxValue > 0 ? Math.ceil(maxValue * 1.1) : 10;

      const options = {
        series: [{
          name: 'Listings Count',
          data: chartData.values
        }],
        chart: {
          height: 264,
          type: 'line',
          toolbar: { show: false },
          zoom: { enabled: false },
          dropShadow: {
            enabled: true,
            top: 6,
            left: 0,
            blur: 4,
            color: '#000',
            opacity: 0.1,
          },
        },
        colors: ['#FAA300'],
        dataLabels: { enabled: false },
        stroke: {
          curve: 'smooth',
          colors: ['#FAA300'],
          width: 3,
        },
        markers: {
          size: 5,
          colors: ['#FAA300'],
          strokeColors: '#fff',
          strokeWidth: 2,
          hover: { 
            size: 7,
            sizeOffset: 1
          },
        },
        tooltip: {
          enabled: true,
          theme: 'light',
          x: { 
            show: true,
            format: 'dd MMM'
          },
          y: { 
            formatter: function(value) {
              return value + (value === 1 ? ' Listing' : ' Listings');
            },
            title: {
              formatter: function() {
                return 'Count: ';
              }
            }
          },
          marker: {
            show: true,
            fillColors: ['#FAA300']
          },
          style: {
            fontSize: '12px',
            fontFamily: 'inherit'
          }
        },
        grid: {
          row: {
            colors: ['transparent', 'transparent'],
            opacity: 0.5,
          },
          borderColor: '#E5E7EB',
          strokeDashArray: 3,
          xaxis: {
            lines: {
              show: true
            }
          },
          yaxis: {
            lines: {
              show: true
            }
          }
        },
        yaxis: {
          min: 0,
          max: yAxisMax,
          tickAmount: 5,
          labels: {
            formatter: function (value) {
              return Math.round(value);
            },
            style: { 
              fontSize: '12px',
              colors: '#6B7280',
              fontFamily: 'inherit'
            },
          },
          title: {
            text: 'Number of Listings',
            style: {
              fontSize: '12px',
              fontWeight: 400,
              color: '#6B7280',
              fontFamily: 'inherit'
            }
          }
        },
        xaxis: {
          categories: chartData.labels,
          tooltip: { enabled: false },
          labels: { 
            style: { 
              fontSize: '11px',
              colors: '#6B7280',
              fontFamily: 'inherit'
            },
            rotate: selectedPeriod.value === 'monthly' ? -45 : 0,
            trim: true,
            hideOverlappingLabels: true
          },
          axisBorder: { 
            show: false 
          },
          axisTicks: {
            show: true,
            color: '#E5E7EB'
          },
          crosshairs: {
            show: true,
            width: 1,
            position: 'back',
            stroke: {
              color: '#FAA300',
              width: 1,
              dashArray: 0
            },
            fill: {
              type: 'solid',
              color: '#FEF3CD',
              gradient: {
                colorFrom: '#FEF3CD',
                colorTo: '#FEF3CD',
                stops: [0, 100],
                opacityFrom: 0.4,
                opacityTo: 0.5,
              }
            },
          },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.2,
            stops: [0, 80, 100],
            colorStops: [
              {
                offset: 0,
                color: '#FAA300',
                opacity: 0.4
              },
              {
                offset: 100,
                color: '#FAA300',
                opacity: 0.1
              }
            ]
          }
        }
      };

      chart = new ApexCharts(document.querySelector('#chart'), options);
      chart.render();
    };

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num);
    };

    onMounted(() => {
      fetchData();
    });

    return {
      selectedPeriod,
      stats,
      fetchData,
      formatNumber
    };
  },
};
</script>

<style scoped>
/* يمكن إضافة ألوان مخصصة إذا لزم الأمر */
:deep(.apexcharts-tooltip) {
  border-color: #FAA300 !important;
}

:deep(.apexcharts-tooltip-title) {
  background-color: #FAA300 !important;
  color: white !important;
  border-color: #FAA300 !important;
}

:deep(.apexcharts-xcrosshairs) {
  stroke: #FAA300 !important;
}

:deep(.apexcharts-ycrosshairs) {
  stroke: #FAA300 !important;
}
</style>