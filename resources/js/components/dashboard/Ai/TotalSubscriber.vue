<template>
  <div class="col-xxl-3 col-xl-6">
    <div class="card h-100 radius-8 border">
      <div class="card-body p-24">
        <h6 class="mb-12 fw-semibold text-lg mb-16">New Accounts</h6>
        <div class="d-flex align-items-center gap-2 mb-20">
          <h6 class="fw-semibold mb-0">{{ formatNumber(stats.total_agents) }}</h6>
          <p class="text-sm mb-0">
            <span :class="`bg-${stats.agents_change >= 0 ? 'success' : 'danger'}-focus border br-${stats.agents_change >= 0 ? 'success' : 'danger'} px-8 py-2 rounded-pill fw-semibold text-${stats.agents_change >= 0 ? 'success' : 'danger'}-main text-sm d-inline-flex align-items-center gap-1`">
              {{ Math.abs(stats.agents_change) }}%
              <iconify-icon :icon="stats.agents_change >= 0 ? 'bxs:up-arrow' : 'iconamoon:arrow-down-2-fill'" class="icon"></iconify-icon>
            </span>
            {{ stats.agents_change >= 0 ? '+' : '-' }} {{ Math.abs(stats.daily_change) }} Per Day
          </p>
        </div>
        <div id="barChart"></div>
      </div>
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts';
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
  name: 'ActiveAgents',
  setup() {
    const stats = ref({
      total_agents: 0,
      agents_change: 0,
      daily_change: 0,
      weekly_data: []
    });
    let chart = null;

    const fetchData = async () => {
      try {
        const token = localStorage.getItem('token');
        const response = await axios.get('/api/dashboard/active-agents', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        stats.value = response.data.data;
        renderChart(stats.value.weekly_data);
      } catch (error) {
        console.error('Error fetching active agents:', error);
      }
    };

    const renderChart = (weeklyData) => {
      if (chart) {
        chart.destroy();
      }

      const options = {
        series: [{
          name: 'Active Agents',
          data: weeklyData
        }],
        chart: {
          type: 'bar',
          height: 235,
          toolbar: { show: false },
        },
        plotOptions: {
          bar: {
            borderRadius: 6,
            horizontal: false,
            columnWidth: '52%',
            endingShape: 'rounded',
          },
        },
        dataLabels: { enabled: false },
        fill: {
          type: 'gradient',
          colors: ['#dae5ff'],
          gradient: {
            shade: 'light',
            type: 'vertical',
            shadeIntensity: 0.5,
            gradientToColors: ['#dae5ff'],
            inverseColors: false,
            opacityFrom: 1,
            opacityTo: 1,
            stops: [0, 100],
          },
        },
        grid: {
          show: false,
          borderColor: '#D1D5DB',
          strokeDashArray: 4,
          position: 'back',
          padding: {
            top: -10,
            right: -10,
            bottom: -10,
            left: -10,
          },
        },
        xaxis: {
          type: 'category',
          categories: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        },
        yaxis: { show: false },
      };

      chart = new ApexCharts(document.querySelector("#barChart"), options);
      chart.render();
    };

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num);
    };

    onMounted(() => {
      fetchData();
    });

    return {
      stats,
      formatNumber
    };
  },
};
</script>