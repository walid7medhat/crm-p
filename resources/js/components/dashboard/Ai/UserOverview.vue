<template>
  <div class="col-xxl-3 col-xl-6">
    <div class="card h-100 radius-8 border-0 overflow-hidden">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-3">
          <h6 class="mb-0 fw-bold text-lg">Requests Overview</h6>
          <div>
            <select 
              class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8"
              v-model="selectedTimeframe"
              @change="fetchData">
              <option value="today">Today</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>
        </div>

        <!-- حاوية الشارت والتسميات -->
        <div class="chart-with-labels-container d-flex flex-column align-items-center">
          <!-- الشارت أكبر -->
          <div ref="chartRef" class="apexcharts-tooltip-z-none medium-chart"></div>
          
          <!-- التسميات أكبر -->
          <div class="medium-labels mt-3 text-center">
            <div class="d-flex justify-content-center gap-4 flex-wrap">
              <div class="d-flex align-items-center gap-2">
                <span class="medium-dot dot-orange"></span>
                <span class="medium-label">
                  New: <strong class="medium-number">{{ stats.new_leads }}</strong>
                </span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="medium-dot dot-green"></span>
                <span class="medium-label">
                  Approved: <strong class="medium-number">{{ stats.approved_leads }}</strong>
                </span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="medium-dot dot-red"></span>
                <span class="medium-label">
                  Rejected: <strong class="medium-number">{{ stats.rejected_leads }}</strong>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

export default {
  name: 'LeadsOverview',
  setup() {
    const selectedTimeframe = ref('weekly');
    const chartRef = ref(null);
    const stats = ref({
      new_leads: 0,
      approved_leads: 0,
      rejected_leads: 0,
      total_leads: 0
    });
    let chart = null;

    const fetchData = async () => {
      try {
        const token = localStorage.getItem('token');
        const response = await axios.get(`/api/dashboard/leads-overview?timeframe=${selectedTimeframe.value}`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        stats.value = response.data.data;
      } catch (error) {
        console.error('Error fetching leads overview:', error);
      }
    };

    const renderChart = () => {
      if (!chartRef.value) return;
      
      if (chart) {
        chart.destroy();
      }

      const options = {
        series: [stats.value.new_leads, stats.value.approved_leads, stats.value.rejected_leads],
        colors: ['#733E87', '#00BE23', '#DC3645'],
        labels: ['New', 'Approved', 'Rejected'],
        legend: { 
          show: false
        },
        chart: {
          type: 'donut',
          height: 200, // أكبر من 150
          width: 200,
          sparkline: { enabled: false },
          toolbar: { show: false },
          animations: {
            enabled: true,
            speed: 500
          }
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              background: 'transparent',
              labels: {
                show: true,
                total: {
                  show: true,
                  showAlways: true,
                  label: 'Total',
                  fontSize: '14px', // أكبر
                  fontFamily: 'inherit',
                  fontWeight: 600, // أثقل
                  color: '#2c3e50',
                  formatter: function (w) {
                    const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                    return total;
                  }
                },
                value: {
                  show: false
                },
                name: {
                  show: false
                }
              }
            }
          }
        },
        stroke: { 
          width: 1,
          colors: ['#fff']
        },
        dataLabels: { 
          enabled: false 
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: function(val) {
              return val + ' requests';
            }
          }
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: {
                height: 180,
                width: 180
              }
            }
          },
          {
            breakpoint: 480,
            options: {
              chart: {
                height: 160,
                width: 160
              }
            }
          }
        ]
      };

      chart = new ApexCharts(chartRef.value, options);
      chart.render();
    };

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num);
    };

    // watch للتغيرات في stats علشان نupdate الchart
    watch(stats, () => {
      renderChart();
    }, { deep: true });

    onMounted(() => {
      fetchData();
    });

    return {
      selectedTimeframe,
      chartRef,
      stats,
      fetchData,
      formatNumber
    };
  },
};
</script>
<style scoped>


/* الشارت أكبر */
.medium-chart {
  width: 200px !important;
  height: 200px !important;
  margin: 0 auto;
}

/* التسميات أكبر */
.medium-labels {
  width: 100%;
}

.medium-label {
  font-size: 13px !important; /* أكبر من 11 */
  color: #6c757d;
  font-weight: 400;
}

.medium-number {
  font-size: 13px !important; /* أكبر من 11 */
  color: #2c3e50;
  font-weight: 700; /* أثقل */
}

/* النقاط الملونة أكبر */
.medium-dot {
  width: 10px; /* أكبر من 8 */
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.dot-orange {
  background-color: #733E87;
}

.dot-green {
  background-color: #00BE23;
}

.dot-red {
  background-color: #DC3645;
}

/* تحسين المظهر على الشاشات الصغيرة */
@media (max-width: 768px) {
  .medium-chart {
    width: 180px !important;
    height: 180px !important;
  }
  
  .medium-label {
    font-size: 12px !important;
  }
  
  .medium-number {
    font-size: 12px !important;
  }
  
  .medium-labels .d-flex {
    gap: 20px !important;
  }
}

@media (max-width: 480px) {
  .medium-chart {
    width: 160px !important;
    height: 160px !important;
  }
  
  .medium-label {
    font-size: 11px !important;
  }
  
  .medium-number {
    font-size: 11px !important;
  }
  
  .medium-labels .d-flex {
    gap: 15px !important;
  }
}

/* تحسين مظهر التحديد */
.form-select:focus {
  border-color: #733E87;
  box-shadow: 0 0 0 0.25rem rgba(250, 163, 0, 0.25);
}

/* تحسين العنوان */
.text-lg {
  font-size: 1.1rem;
  color: #2c3e50;
}

/* مسافة أفضل */
.mt-3 {
  margin-top: 1rem !important;
}

/* تحسين التباعد */
.gap-4 {
  gap: 1.5rem !important;
}

/* ضمان أن كل شيء في المنتصف */
.d-flex.flex-column.align-items-center {
  justify-content: center;
}
</style>