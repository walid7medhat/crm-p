<template>
  <div class="col-xxl-3 col-xl-6">
    <div class="card h-100 radius-8 border">
      <div class="card-body p-24">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-16">
          <h6 class="fw-semibold text-lg mb-0">Property Type</h6>
          <!--<div class="dropdown">-->
          <!--  <button class="btn btn-sm btn-outline-light border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown">-->
          <!--    <iconify-icon icon="lucide:bar-chart-3"></iconify-icon>-->
          <!--  </button>-->
          <!--  <ul class="dropdown-menu">-->
          <!--    <li><a class="dropdown-item" href="#" @click="changeBarType('vertical')">Vertical Bar</a></li>-->
          <!--    <li><a class="dropdown-item" href="#" @click="changeBarType('horizontal')">Horizontal Bar</a></li>-->
          <!--  </ul>-->
          <!--</div>-->
        </div>

        <!-- Statistics Section - أرقام صحيحة -->
        <!--<div class="d-flex align-items-center gap-2 mb-20">-->
        <!--  <div class="d-flex align-items-center gap-2">-->
        <!--    <iconify-icon icon="lucide:building" class="text-primary fs-5"></iconify-icon>-->
        <!--    <div>-->
        <!--      <h6 class="fw-semibold mb-0">{{ formatNumber(totalPropertyTypes) }}</h6>-->
        <!--      <p class="text-xs text-muted mb-0">Property Types</p>-->
        <!--    </div>-->
        <!--  </div>-->
          
        <!--  <div class="vr mx-2"></div>-->
          
        <!--  <div class="d-flex align-items-center gap-2">-->
        <!--    <iconify-icon icon="mingcute:storage-line" class="text-success fs-5"></iconify-icon>-->
        <!--    <div>-->
        <!--      <h6 class="fw-semibold mb-0">{{ formatNumber(totalListings) }}</h6>-->
        <!--      <p class="text-xs text-muted mb-0">Total Listings</p>-->
        <!--    </div>-->
        <!--  </div>-->
        <!--</div>-->

        <!-- Chart Area -->
        <div id="propertyTypesChart"></div>

        <!-- Data Labels - تظهر الأرقام على البارات -->
        <div v-if="propertyTypes.length > 0" class="mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="text-sm text-muted">
              Total: <strong>{{ formatNumber(totalListings) }}</strong> listings
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && propertyTypes.length === 0" class="text-center py-4">
          <iconify-icon icon="lucide:bar-chart-3" class="text-muted mb-2" style="font-size: 32px;"></iconify-icon>
          <p class="text-muted mb-0">No active listings found</p>
          <p class="text-sm text-muted mt-1">Create listings to see them categorized by property type</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';

export default {
  name: 'PropertyTypesWithListings',
  setup() {
    const propertyTypes = ref([]);
    const loading = ref(true);
    const barType = ref('vertical'); // 'vertical' or 'horizontal'
    let chart = null;

    // Color gradient for bars
    const colorGradient = [
      '#3B82F6', '#2563EB', '#1D4ED8', '#1E40AF',
      '#10B981', '#059669', '#047857', '#065F46',
      '#F59E0B', '#D97706', '#B45309', '#92400E',
      '#EF4444', '#DC2626', '#B91C1C', '#991B1B'
    ];

    const fetchPropertyTypes = async () => {
      try {
        loading.value = true;
        const token = localStorage.getItem('token');
        
        const response = await axios.get('/api/dashboard/property-types-with-listings', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        if (response.data.success) {
          propertyTypes.value = response.data.data;
          if (propertyTypes.value.length > 0) {
            renderChart();
          }
        }
      } catch (error) {
        console.error('Error fetching property types:', error);
      } finally {
        loading.value = false;
      }
    };

    const renderChart = () => {
      if (chart) {
        chart.destroy();
      }

      const isHorizontal = barType.value === 'horizontal';
      
      const options = {
        series: [{
          name: 'Listings',
          data: propertyTypes.value.map(pt => pt.listings_count)
        }],
        chart: {
          type: 'bar',
          height: 220,
          toolbar: { show: false },
          animations: {
            enabled: true,
            speed: 800,
            animateGradually: {
              enabled: true,
              delay: 150
            }
          }
        },
        colors: propertyTypes.value.map((_, index) => getColor(index)),
        plotOptions: {
          bar: {
            horizontal: isHorizontal,
            borderRadius: isHorizontal ? 0 : 6,
            columnWidth: isHorizontal ? '60%' : '45%',
            distributed: false,
            dataLabels: {
              position: isHorizontal ? 'center' : 'top',
              hideOverflowingLabels: false
            }
          }
        },
        dataLabels: {
          enabled: true,
          formatter: function(val) {
            // عرض الأرقام كأرقام صحيحة
            return Math.round(val).toString();
          },
          style: {
            fontSize: '11px',
            fontWeight: '600',
            colors: ['#fff']
          },
          offsetY: isHorizontal ? 0 : -20,
          background: {
            enabled: true,
            foreColor: '#fff',
            padding: 4,
            borderRadius: 2,
            borderWidth: 1,
            borderColor: '#fff',
            opacity: 0.9
          }
        },
        legend: {
          show: false
        },
        grid: {
          show: false,
          padding: {
            top: 0,
            right: 0,
            bottom: 0,
            left: 0
          }
        },
        xaxis: {
          categories: propertyTypes.value.map(pt => truncateLabel(pt.name, 10)),
          labels: {
            style: {
              fontSize: '11px',
              fontWeight: '500'
            },
            rotate: isHorizontal ? 0 : -45,
            formatter: function(value) {
              // عرض الأرقام كأرقام صحيحة على المحور X إذا كان شارت أفقي
              if (isHorizontal) {
                return Math.round(parseFloat(value)).toString();
              }
              return value;
            }
          },
          axisBorder: {
            show: false
          },
          axisTicks: {
            show: false
          }
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '11px',
              fontWeight: '500'
            },
            formatter: function(value) {
              // عرض الأرقام كأرقام صحيحة على المحور Y
              return Math.round(parseFloat(value)).toString();
            }
          },
          title: {
            text: isHorizontal ? 'Property Types' : 'Listings Count',
            style: {
              fontSize: '12px',
              fontWeight: '500'
            }
          }
        },
        tooltip: {
          y: {
            formatter: function(val) {
              // عرض الأرقام كأرقام صحيحة في الـ tooltip
              return Math.round(val) + ' listings';
            }
          },
          theme: 'light',
          custom: function({ series, seriesIndex, dataPointIndex, w }) {
            const propertyType = propertyTypes.value[dataPointIndex];
            return `<div class="apexcharts-tooltip-title">${propertyType.name}</div>
                    <div class="apexcharts-tooltip-series-group">
                      <span class="apexcharts-tooltip-marker" style="background-color: ${colorGradient[dataPointIndex % colorGradient.length]}"></span>
                      <div class="apexcharts-tooltip-text">
                        <div class="apexcharts-tooltip-y-group">
                          <span class="apexcharts-tooltip-text-label">Listings: </span>
                          <span class="apexcharts-tooltip-text-value">${Math.round(propertyType.listings_count)}</span>
                        </div>
                      </div>
                    </div>`;
          }
        }
      };

      chart = new ApexCharts(document.querySelector("#propertyTypesChart"), options);
      chart.render();
    };

    const getColor = (index) => {
      return colorGradient[index % colorGradient.length];
    };

    const truncateLabel = (label, maxLength) => {
      if (label.length <= maxLength) return label;
      return label.substring(0, maxLength) + '...';
    };

    const changeBarType = (type) => {
      barType.value = type;
      if (propertyTypes.value.length > 0) {
        renderChart();
      }
    };

    // دالة لتنسيق الأرقام (إضافة فاصلات للأرقام الكبيرة)
    const formatNumber = (num) => {
      if (typeof num !== 'number') {
        num = parseInt(num) || 0;
      }
      return num.toLocaleString('en-US', {
        maximumFractionDigits: 0
      });
    };

    // Computed properties - أرقام صحيحة
    const totalPropertyTypes = computed(() => {
      return propertyTypes.value.length;
    });

    const totalListings = computed(() => {
      const sum = propertyTypes.value.reduce((sum, pt) => sum + pt.listings_count, 0);
      return Math.round(sum); // تأكد من أن النتيجة عدد صحيح
    });

    onMounted(() => {
      fetchPropertyTypes();
    });

    watch(barType, () => {
      if (propertyTypes.value.length > 0) {
        renderChart();
      }
    });

    return {
      propertyTypes,
      loading,
      barType,
      totalPropertyTypes,
      totalListings,
      formatNumber,
      changeBarType
    };
  },
};
</script>

<style scoped>
.card {
  transition: all 0.3s ease;
}

.card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

/* Dropdown styling */
.dropdown-toggle {
  padding: 4px 8px;
  border-radius: 6px;
  background-color: #F9FAFB;
  border: 1px solid #E5E7EB;
  color: #6B7280;
}

.dropdown-toggle:hover {
  background-color: #F3F4F6;
  color: #374151;
}

/* Loading spinner */
.spinner-border {
  width: 1.5rem;
  height: 1.5rem;
}

/* Custom tooltip styling */
:deep(.apexcharts-tooltip) {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
  border-radius: 8px !important;
  border: 1px solid #E5E7EB !important;
}

:deep(.apexcharts-tooltip-title) {
  font-weight: 600 !important;
  font-size: 14px !important;
  margin-bottom: 4px !important;
  color: #111827 !important;
  background-color: transparent !important;
  border-bottom: 1px solid #F3F4F6 !important;
  padding-bottom: 4px !important;
}

:deep(.apexcharts-tooltip-series-group) {
  padding: 4px 8px !important;
}

:deep(.apexcharts-tooltip-text-label) {
  font-weight: 500 !important;
  color: #6B7280 !important;
}

:deep(.apexcharts-tooltip-text-value) {
  font-weight: 600 !important;
  color: #111827 !important;
}

:deep(.apexcharts-tooltip-marker) {
  width: 8px !important;
  height: 8px !important;
}

/* إحصائيات علوية محسنة */
.vr {
  height: 24px;
  opacity: 0.3;
}

.text-xs {
  font-size: 0.75rem;
}
</style>