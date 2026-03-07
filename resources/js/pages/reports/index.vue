<template>
  <div class="reports-page-wrap">
    <div class="reports-page">
      <!-- Single white container: tabs, controls, filters, cards, charts -->
      <div class="reports-panel">
        <header class="reports-header">
          <div class="reports-tabs">
            <button type="button" class="reports-tab" :class="{ active: activeReportTab === 'leads' }" @click="activeReportTab = 'leads'">
              Leads Reports
            </button>
            <button type="button" class="reports-tab" :class="{ active: activeReportTab === 'deals' }" @click="activeReportTab = 'deals'">
              Deals Reports
            </button>
          </div>
          <div class="reports-header-actions">
            <div class="reports-search-box">
              <input type="text" class="reports-search-input" placeholder="Filter and search agents" />
              <iconify-icon icon="lucide:plus" class="reports-search-plus" />
              <iconify-icon icon="lucide:search" class="reports-search-icon" />
            </div>
            <button type="button" class="reports-btn reports-btn-excel">
              <iconify-icon icon="mdi:file-excel-outline" />
              Export Excel
            </button>
            <button type="button" class="reports-btn reports-btn-icon" aria-label="More">
              <iconify-icon icon="lucide:more-vertical" />
            </button>
            <button type="button" class="reports-btn reports-btn-icon" aria-label="Settings">
              <iconify-icon icon="lucide:settings" />
            </button>
          </div>
        </header>
        <div class="reports-tabs-border"></div>

        <!-- Team filters + dropdowns -->
      <div class="reports-filters-row">
        <div class="reports-team-tabs">
          <button type="button" class="team-tab" :class="{ active: activeTeam === 'all' }" @click="activeTeam = 'all'">
            <iconify-icon icon="lucide:users" class="team-tab-icon" />
            All Team
          </button>
          <button type="button" class="team-tab" :class="{ active: activeTeam === 'abu-dhabi' }" @click="activeTeam = 'abu-dhabi'">
            <iconify-icon icon="lucide:users" class="team-tab-icon" />
            Abu Dhabi Team
          </button>
          <button type="button" class="team-tab" :class="{ active: activeTeam === 'dubai' }" @click="activeTeam = 'dubai'">
            <iconify-icon icon="lucide:users" class="team-tab-icon" />
            Dubai Team
          </button>
        </div>
        <div class="reports-dropdowns">
          <div class="reports-select-box">
            <iconify-icon icon="lucide:users" class="select-box-icon" />
            <select class="reports-select">
              <option>Select Agents / Team</option>
            </select>
          </div>
          <div class="reports-select-box">
            <iconify-icon icon="lucide:calendar" class="select-box-icon" />
            <select v-model="dateRange" class="reports-select">
              <option value="7">Last 7 Days</option>
              <option value="30">Last 30 Days</option>
              <option value="90">Last 90 Days</option>
            </select>
          </div>
        </div>
      </div>

      <!-- KPI cards: number top, label, icon top-right, trend with arrow -->
      <div class="reports-kpi-row">
        <div class="reports-kpi-card">
          <div class="kpi-icon kpi-icon-total"><iconify-icon icon="lucide:users-round" /></div>
          <div class="kpi-body">
            <div class="kpi-number">155</div>
            <div class="kpi-name">Total leads</div>
            <div class="kpi-delta negative"><iconify-icon icon="lucide:trending-down" class="kpi-delta-arrow" /> 7.2% <span>-5% Last 7 Days</span></div>
          </div>
        </div>
        <div class="reports-kpi-card">
          <div class="kpi-icon kpi-icon-follow"><iconify-icon icon="lucide:message-circle" /></div>
          <div class="kpi-body">
            <div class="kpi-number">155</div>
            <div class="kpi-name">Follow Ups</div>
            <div class="kpi-delta positive"><iconify-icon icon="lucide:trending-up" class="kpi-delta-arrow" /> 18.2% <span>+2% Last 7 Days</span></div>
          </div>
        </div>
        <div class="reports-kpi-card">
          <div class="kpi-icon kpi-icon-qualified"><iconify-icon icon="lucide:check-circle" /></div>
          <div class="kpi-body">
            <div class="kpi-number">155</div>
            <div class="kpi-name">Qualified Leads</div>
            <div class="kpi-delta positive"><iconify-icon icon="lucide:trending-up" class="kpi-delta-arrow" /> 10.2% <span>+4% Last 7 Days</span></div>
          </div>
        </div>
        <div class="reports-kpi-card">
          <div class="kpi-icon kpi-icon-unqualified"><iconify-icon icon="lucide:user-x" /></div>
          <div class="kpi-body">
            <div class="kpi-number">155</div>
            <div class="kpi-name">Unqualified Leads</div>
            <div class="kpi-delta negative"><iconify-icon icon="lucide:trending-down" class="kpi-delta-arrow" /> 9.2% <span>-5% Last 7 Days</span></div>
          </div>
        </div>
        <div class="reports-kpi-card">
          <div class="kpi-icon kpi-icon-converted"><iconify-icon icon="lucide:refresh-cw" /></div>
          <div class="kpi-body">
            <div class="kpi-number">155</div>
            <div class="kpi-name">Converted Leads</div>
            <div class="kpi-delta positive"><iconify-icon icon="lucide:trending-up" class="kpi-delta-arrow" /> 15.2% <span>+5% Last 7 Days</span></div>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="reports-charts-row">
        <div class="reports-chart-card reports-chart-trend">
          <div class="chart-card-head">
            <h6 class="chart-card-title">Leads Trend Chart</h6>
            <div class="chart-card-select-wrap">
              <iconify-icon icon="lucide:calendar" class="chart-card-select-icon" />
              <select class="chart-card-select">
                <option>Yearly</option>
                <option>Monthly</option>
                <option>Weekly</option>
              </select>
            </div>
          </div>
          <div class="chart-card-body">
            <apexchart type="area" height="280" :options="leadsTrendOptions" :series="leadsTrendSeries" />
          </div>
        </div>
        <div class="reports-chart-card reports-chart-status">
          <div class="chart-card-head">
            <h6 class="chart-card-title">Leads By Status</h6>
            <div class="chart-card-select-wrap">
              <iconify-icon icon="lucide:calendar" class="chart-card-select-icon" />
              <select class="chart-card-select">
                <option>Last 7 Days</option>
                <option>Last 30 Days</option>
              </select>
            </div>
          </div>
          <div class="chart-card-body">
            <apexchart type="area" height="280" :options="leadsByStatusOptions" :series="leadsByStatusSeries" />
          </div>
        </div>
      </div>

      <!-- Bottom performance cards -->
      <div class="reports-perf-row">
        <div class="reports-perf-card">
          <div class="perf-icon perf-icon-green"><iconify-icon icon="lucide:users" /></div>
          <div class="perf-body">
            <div class="perf-label">Best Performing Team</div>
            <div class="perf-name">Ahmad al Daghash</div>
            <div class="perf-meta">Department</div>
          </div>
          <iconify-icon icon="lucide:arrow-up-right" class="perf-arrow" />
        </div>
        <div class="reports-perf-card">
          <div class="perf-icon perf-icon-avatar"><iconify-icon icon="lucide:user" /></div>
          <div class="perf-body">
            <div class="perf-label">Best Performing Agent</div>
            <div class="perf-name">Maria Guan</div>
            <div class="perf-meta">mariaguan@gmail.com</div>
          </div>
          <iconify-icon icon="lucide:arrow-up-right" class="perf-arrow" />
        </div>
        <div class="reports-perf-card">
          <div class="perf-icon perf-icon-blue"><iconify-icon icon="lucide:building-2" /></div>
          <div class="perf-body">
            <div class="perf-label">Top Performing Branch</div>
            <div class="perf-name">Abu Dhabi</div>
            <div class="perf-meta">Branch</div>
          </div>
          <iconify-icon icon="lucide:arrow-up-right" class="perf-arrow" />
        </div>
        <div class="reports-perf-card">
          <div class="perf-icon perf-icon-blue"><iconify-icon icon="lucide:globe" /></div>
          <div class="perf-body">
            <div class="perf-label">Most Qualified Leads Source</div>
            <div class="perf-name">Meta Ads - Lead Form</div>
            <div class="perf-meta">Lead Source</div>
          </div>
          <iconify-icon icon="lucide:arrow-up-right" class="perf-arrow" />
        </div>
      </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const activeReportTab = ref('leads')
const activeTeam = ref('all')
const dateRange = ref('7')

const leadsTrendOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.12 } },
  colors: ['#f59e0b'],
  xaxis: {
    categories: ['2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025', '2026'],
    labels: { style: { fontSize: '11px', colors: '#64748b' } }
  },
  yaxis: { labels: { style: { fontSize: '11px', colors: '#64748b' } } },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4, xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
  tooltip: { theme: 'light' }
}))

const leadsTrendSeries = ref([{ name: 'Leads', data: [30, 45, 55, 70, 85, 95, 110, 120, 135, 145, 150] }])

const leadsByStatusOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.08 } },
  colors: ['#64748b'],
  xaxis: {
    categories: ['New Leads', 'Assigned', 'Follow Up / Contacted', 'Qualified', 'Future Prospected'],
    labels: { style: { fontSize: '10px', colors: '#64748b' }, rotate: -20 }
  },
  yaxis: { labels: { style: { fontSize: '11px', colors: '#64748b' } } },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4, xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
  tooltip: { theme: 'light' }
}))

const leadsByStatusSeries = ref([{ name: 'Leads', data: [80, 120, 200, 150, 90] }])
</script>

<style scoped>
.reports-page-wrap {
  min-height: 100vh;
  position: relative;
}
.reports-page {
  position: relative;
  z-index: 1;
  padding: 1rem 1.25rem 2rem;
  max-width: 1600px;
  margin: 0 auto;
  font-size: 13px;
}

/* Single white rounded container */
.reports-panel {
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  padding: 1rem 1.25rem 1.5rem;
}

/* Header: tabs left, controls right */
.reports-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0;
}
.reports-header .reports-tabs {
  border: none;
  margin: 0;
  padding: 0;
}
.reports-header .reports-tab {
  margin-bottom: 0;
}
.reports-tabs-border {
  height: 0;
  border-bottom: 1px solid #e2e8f0;
  margin: 0.5rem 0 1rem;
}
.reports-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.reports-search-box {
  position: relative;
  width: 220px;
}
.reports-search-icon {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14px;
  color: #64748b;
  pointer-events: none;
}
.reports-search-plus {
  position: absolute;
  right: 32px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14px;
  color: #64748b;
  pointer-events: none;
}
.reports-search-input {
  width: 100%;
  height: 36px;
  padding: 0 56px 0 12px;
  font-size: 12px;
  color: #1e293b;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  outline: none;
}
.reports-search-input::placeholder { color: #94a3b8; }
.reports-search-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
.reports-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  height: 34px;
  padding: 0 0.75rem;
  font-size: 12px;
  font-weight: 500;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #1e293b;
  cursor: pointer;
  transition: all 0.2s;
}
.reports-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
.reports-btn-excel { min-width: 100px; background: #f8fafc; color: #1e293b; }
.reports-btn-icon { width: 34px; padding: 0; font-size: 14px; }

/* Main report tabs (inside header, left side) */
.reports-tabs {
  display: flex;
  justify-content: flex-start;
  gap: 0;
  border-bottom: none;
  margin-bottom: 0;
}
.reports-tab {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem 1rem;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  margin-bottom: 0;
  margin-right: 0.5rem;
  cursor: pointer;
  transition: color 0.2s, border-color 0.2s;
  text-align: center;
}
.reports-tab:hover { color: #1e293b; }
.reports-tab.active { color: #0f172a; border-bottom-color: #f59e0b; border-bottom-width: 3px; }

/* Team filters + dropdowns – light purple/grey band, tab titles centered */
.reports-filters-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
  background: #f5f3ff;
  border-radius: 10px;
}
.reports-team-tabs {
  display: flex;
  justify-content: flex-start;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.team-tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  font-size: 12px;
  font-weight: 500;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #e2e8f0;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}
.team-tab-icon { font-size: 14px; }
.team-tab:hover { background: #cbd5e1; color: #1e293b; }
.team-tab.active {
  background: #1e3a8a;
  border-color: #1e3a8a;
  color: #fff;
}
.team-tab.active .team-tab-icon { color: #fff; }
.reports-dropdowns {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.reports-select-box {
  position: relative;
  min-width: 150px;
  display: flex;
  align-items: center;
}
.select-box-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14px;
  color: #475569;
  pointer-events: none;
  z-index: 1;
  line-height: 1;
}
.reports-select {
  width: 100%;
  height: 38px;
  padding: 9px 28px 9px 38px;
  font-size: 13px;
  line-height: 1.25;
  color: #1e293b;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  appearance: auto;
  box-sizing: border-box;
  display: block;
}
.reports-select:focus { outline: none; border-color: #3b82f6; }

/* KPI cards */
.reports-kpi-row {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.75rem;
  margin-bottom: 1rem;
}
@media (max-width: 1200px) { .reports-kpi-row { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .reports-kpi-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .reports-kpi-row { grid-template-columns: 1fr; } }

.reports-kpi-card {
  background: #fff;
  border-radius: 10px;
  padding: 1rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid #e2e8f0;
  position: relative;
}
.kpi-icon {
  position: absolute;
  top: 0.85rem;
  right: 0.85rem;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}
.kpi-icon-total { background: #fef3c7; color: #d97706; }
.kpi-icon-follow { background: #dbeafe; color: #2563eb; }
.kpi-icon-qualified { background: #d1fae5; color: #059669; }
.kpi-icon-unqualified { background: #fee2e2; color: #dc2626; }
.kpi-icon-converted { background: #ccfbf1; color: #0d9488; }
.kpi-body { padding-right: 2.5rem; }
.kpi-number { font-size: 1.5rem; font-weight: 700; color: #1e293b; line-height: 1.2; }
.kpi-name { font-size: 12px; color: #64748b; margin-top: 4px; }
.kpi-delta { font-size: 11px; margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; }
.kpi-delta-arrow { font-size: 14px; }
.kpi-delta span { color: #94a3b8; margin-left: 2px; }
.kpi-delta.positive { color: #059669; }
.kpi-delta.negative { color: #dc2626; }

/* Charts */
.reports-charts-row {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
@media (max-width: 992px) { .reports-charts-row { grid-template-columns: 1fr; } }

.reports-chart-card {
  background: #fff;
  border-radius: 10px;
  padding: 1rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid #e2e8f0;
}
.chart-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}
.chart-card-title {
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}
.chart-card-select-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.chart-card-select-icon {
  font-size: 14px;
  color: #64748b;
}
.chart-card-select {
  font-size: 11px;
  padding: 0.35rem 0.6rem;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  cursor: pointer;
  min-width: 90px;
}
.chart-card-body { min-height: 260px; }

/* Performance cards */
.reports-perf-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
}
@media (max-width: 1200px) { .reports-perf-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px)  { .reports-perf-row { grid-template-columns: 1fr; } }

.reports-perf-card {
  background: #fff;
  border-radius: 10px;
  padding: 0.85rem 1rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 72px;
  position: relative;
}
.perf-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.perf-icon-green { background: #d1fae5; color: #059669; }
.perf-icon-blue { background: #e0f2fe; color: #0284c7; }
.perf-icon-avatar { background: #f1f5f9; color: #64748b; }
.perf-body { flex: 1; min-width: 0; }
.perf-label { font-size: 10px; color: #64748b; }
.perf-name { font-size: 12px; font-weight: 600; color: #1e293b; margin-top: 1px; }
.perf-meta { font-size: 10px; color: #94a3b8; margin-top: 1px; }
.perf-arrow { font-size: 16px; color: #f59e0b; flex-shrink: 0; position: absolute; top: 0.85rem; right: 0.85rem; }
</style>
