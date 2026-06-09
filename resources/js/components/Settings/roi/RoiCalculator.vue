<template>
  <div class="roi-page">
    <!-- Emirate + actions -->
    <div class="card radius-12 mb-3">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <p class="roi-page__eyebrow mb-1">UAE Market</p>
            <div class="dh-date-range-presets mb-0">
              <button
                v-for="(cfg, key) in emirates"
                :key="key"
                type="button"
                class="dh-date-range-preset"
                :class="{ 'dh-date-range-preset--active': inputs.emirate === key }"
                @click="setEmirate(key)"
              >
                {{ cfg.emoji }} {{ cfg.label }}
              </button>
            </div>
            <p class="roi-page__hint mt-2 mb-0">
              {{ emirateConfig.authority }} · {{ emirateConfig.rentalIndex }}
            </p>
          </div>
          <button
            type="button"
            class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
            :disabled="pdfGenerating"
            @click="exportPdf"
          >
            <iconify-icon :icon="pdfGenerating ? 'lucide:loader-circle' : 'lucide:file-down'" :class="{ 'roi-spin': pdfGenerating }" />
            Download PDF Report
          </button>
        </div>
      </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-3">
      <div v-for="kpi in kpiCards" :key="kpi.id" class="col-6 col-md-4 col-xl">
        <div class="card h-100 radius-12 roi-kpi" :class="{ 'roi-kpi--highlight': kpi.highlight }">
          <div class="card-body p-3">
            <p class="roi-kpi__label mb-1">{{ kpi.label }}</p>
            <p class="roi-kpi__value mb-0">{{ kpi.value }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="roi-layout">
      <!-- Inputs — scroll inside panel on desktop -->
      <div class="roi-layout__inputs">
        <div class="roi-inputs-stack">
          <section class="card radius-12">
            <div class="card-body p-3">
              <h6 class="roi-section-title">Acquisition Costs</h6>
              <p class="roi-section-sub">{{ emirateConfig.registrationFeeLabel }} · {{ emirateConfig.registrationFeePct }}%</p>
              <div class="row g-2 g-md-3">
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Purchase Price (AED)</label>
                  <input v-model.number="inputs.purchasePrice" type="number" min="0" step="10000" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Closing Costs</label>
                  <input v-model.number="inputs.closingCosts" type="number" min="0" step="500" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Rehab Cost</label>
                  <input v-model.number="inputs.rehabCost" type="number" min="0" step="1000" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Agency Fee (%)</label>
                  <input v-model.number="inputs.agencyFeePct" type="number" min="0" max="10" step="0.1" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Down Payment (%)</label>
                  <input v-model.number="inputs.downPaymentPct" type="number" min="0" max="100" step="1" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Mortgage Amount</label>
                  <input :value="formatAed(inputs.mortgageAmount)" readonly class="form-control radius-8 bg-light" />
                </div>
                <div class="col-6">
                  <div class="roi-computed">
                    <span>{{ emirateConfig.registrationFeeLabel }}</span>
                    <strong>{{ formatAed(registrationFee) }}</strong>
                  </div>
                </div>
                <div class="col-6">
                  <div class="roi-computed">
                    <span>Mortgage Registration</span>
                    <strong>{{ formatAed(emirateConfig.mortgageRegistration) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="card radius-12">
            <div class="card-body p-3">
              <h6 class="roi-section-title">Financing</h6>
              <p class="roi-section-sub">Reference: ADIB Home Finance Rates</p>
              <div class="row g-2 g-md-3">
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Interest Rate (%)</label>
                  <input v-model.number="inputs.interestRate" type="number" min="0" max="20" step="0.01" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Mortgage Term (Years)</label>
                  <input v-model.number="inputs.mortgageTermYears" type="number" min="1" :max="maxMortgageTerm" step="1" class="form-control radius-8" />
                  <small v-if="termWarning" class="text-danger">{{ termWarning }}</small>
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input id="roi-uae-national" v-model="inputs.isUaeNational" class="form-check-input" type="checkbox" />
                    <label class="form-check-label text-sm" for="roi-uae-national">UAE National (max {{ MORTGAGE_TERM_NATIONAL }} years)</label>
                  </div>
                </div>
                <div class="col-12">
                  <div class="roi-computed">
                    <span>Monthly Payment</span>
                    <strong>{{ formatAed(monthlyMortgage) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="card radius-12">
            <div class="card-body p-3">
              <h6 class="roi-section-title">Rental Income</h6>
              <p class="roi-section-sub">{{ emirateConfig.rentalHint }}</p>
              <label class="form-label fw-semibold text-primary-light text-sm mb-8">Annual Rental Income (AED)</label>
              <input v-model.number="inputs.annualRentalIncome" type="number" min="0" step="1000" class="form-control radius-8" />
              <small class="text-muted">5% annual rent increase applied automatically</small>
            </div>
          </section>

          <section class="card radius-12">
            <div class="card-body p-3">
              <h6 class="roi-section-title">Operating Expenses</h6>
              <p class="roi-section-sub">{{ emirateConfig.serviceChargeHint }}</p>
              <div class="row g-2 g-md-3">
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Service Charges</label>
                  <input v-model.number="inputs.serviceCharges" type="number" min="0" step="500" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Maintenance</label>
                  <input v-model.number="inputs.maintenance" type="number" min="0" step="500" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Insurance</label>
                  <input v-model.number="inputs.insurance" type="number" min="0" step="100" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Utilities</label>
                  <input v-model.number="inputs.utilities" type="number" min="0" step="100" class="form-control radius-8" />
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Property Management Fee (%)</label>
                  <input v-model.number="inputs.propertyManagementFeePct" type="number" min="0" :max="MAX_MGMT_FEE_PCT" step="0.5" class="form-control radius-8" />
                </div>
              </div>
            </div>
          </section>

          <section class="card radius-12">
            <div class="card-body p-3">
              <h6 class="roi-section-title">Appreciation &amp; Hold</h6>
              <p class="roi-section-sub">{{ emirateConfig.appreciationHint }}</p>
              <div class="row g-2 g-md-3">
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Annual Appreciation (%)</label>
                  <input v-model.number="inputs.annualAppreciationPct" type="number" min="-10" max="30" step="0.1" class="form-control radius-8" />
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold text-primary-light text-sm mb-8">Hold Period (Years)</label>
                  <input v-model.number="inputs.holdPeriodYears" type="number" min="1" max="30" step="1" class="form-control radius-8" />
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>

      <!-- Results — sticky on desktop, fills right side -->
      <div class="roi-layout__results">
        <div class="roi-results-stack">
          <div class="card radius-12">
            <div class="card-body p-3">
              <h6 class="roi-section-title mb-2">Investment Summary</h6>
              <div class="row g-2">
                <div v-for="m in secondaryMetrics" :key="m.label" class="col-6 col-md-4">
                  <div class="roi-mini-metric">
                    <span>{{ m.label }}</span>
                    <strong>{{ m.value }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div v-for="chart in chartConfigs" :key="chart.id" class="col-sm-6">
              <div class="card radius-12 h-100">
                <div class="card-body p-3">
                  <h6 class="roi-section-title mb-1">{{ chart.title }}</h6>
                  <apexchart type="area" height="150" :options="chart.options" :series="chart.series" />
                </div>
              </div>
            </div>
          </div>

          <div class="card radius-12">
            <div class="card-body p-3">
              <h6 class="roi-section-title">Yearly Projection</h6>
              <p class="roi-section-sub mb-2">{{ inputs.holdPeriodYears }}-year hold · 5% rent escalation</p>
              <div class="table-responsive">
                <table class="table table-hover align-middle roi-table mb-0">
                  <thead>
                    <tr>
                      <th>Year</th>
                      <th>Property Value</th>
                      <th>Annual Rent</th>
                      <th>NOI</th>
                      <th>Cum. NOI</th>
                      <th>Appr. Gain</th>
                      <th>Total Equity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in yearlyProjections" :key="row.year">
                      <td><span class="badge bg-primary-50 text-primary-600">{{ row.year }}</span></td>
                      <td>{{ formatAed(row.propertyValue) }}</td>
                      <td>{{ formatAed(row.annualRent) }}</td>
                      <td :class="row.noi >= 0 ? 'text-success-600' : 'text-danger-600'">{{ formatAed(row.noi) }}</td>
                      <td>{{ formatAed(row.cumulativeNoi) }}</td>
                      <td>{{ formatAed(row.appreciationGain) }}</td>
                      <td class="fw-semibold">{{ formatAed(row.totalEquity) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { EMIRATES, MAX_MGMT_FEE_PCT, MORTGAGE_TERM_NATIONAL } from './uaeMarketConfig.js'
import { useRoiCalculations, formatAed, formatPct } from './useRoiCalculations.js'
import { generateRoiReportPdf } from './roiPdfExport.js'

const apexchart = VueApexCharts
const emirates = EMIRATES
const pdfGenerating = ref(false)

const {
  inputs,
  emirateConfig,
  maxMortgageTerm,
  registrationFee,
  agencyFee,
  downPaymentAmount,
  totalAcquisitionCost,
  monthlyMortgage,
  yearlyProjections,
  kpis,
  chartData,
  setEmirate,
} = useRoiCalculations()

const termWarning = computed(() => {
  if (inputs.value.mortgageTermYears > maxMortgageTerm.value) {
    return `Max ${maxMortgageTerm.value} years for ${inputs.value.isUaeNational ? 'UAE nationals' : 'expats'}`
  }
  return null
})

const kpiCards = computed(() => [
  { id: 'total', label: 'Total ROI', value: formatPct(kpis.value.totalRoi), highlight: true },
  { id: 'cash', label: 'Monthly Cash Flow', value: formatAed(kpis.value.monthlyCashFlow, true) },
  { id: 'cap', label: 'Cap Rate', value: formatPct(kpis.value.capRate) },
  { id: 'coc', label: 'Cash-on-Cash', value: formatPct(kpis.value.cashOnCash) },
  { id: 'ann', label: 'Annualized ROI', value: formatPct(kpis.value.annualizedRoi), highlight: true },
])

const secondaryMetrics = computed(() => [
  { label: 'Gross Rental Yield', value: formatPct(kpis.value.grossRentalYield) },
  { label: 'GRM', value: kpis.value.grm.toFixed(2) },
  { label: '1% Rule', value: formatPct(kpis.value.onePctRule) },
  { label: 'NOI (Year 1)', value: formatAed(kpis.value.noi) },
  { label: 'Total Cash Invested', value: formatAed(totalAcquisitionCost.value) },
])

const chartBaseOptions = {
  chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'Inter, system-ui, sans-serif' },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 0.3, opacityFrom: 0.4, opacityTo: 0.05 } },
  grid: { borderColor: '#eeeaf5', strokeDashArray: 4 },
  xaxis: { labels: { style: { colors: '#6b7280', fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis: {
    labels: {
      style: { colors: '#6b7280', fontSize: '11px' },
      formatter: (v) => {
        if (Math.abs(v) >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`
        if (Math.abs(v) >= 1_000) return `${(v / 1_000).toFixed(0)}K`
        return v.toFixed(0)
      },
    },
  },
  tooltip: { theme: 'light' },
  dataLabels: { enabled: false },
}

const chartConfigs = computed(() => {
  const labels = chartData.value.labels
  const baseX = { ...chartBaseOptions.xaxis, categories: labels }

  return [
    {
      id: 'roi',
      title: 'ROI Growth',
      series: [{ name: 'ROI %', data: chartData.value.roiGrowth }],
      options: {
        ...chartBaseOptions,
        colors: ['#5b3d8f'],
        xaxis: baseX,
        yaxis: { ...chartBaseOptions.yaxis, labels: { ...chartBaseOptions.yaxis.labels, formatter: (v) => `${v.toFixed(1)}%` } },
      },
    },
    {
      id: 'value',
      title: 'Property Appreciation',
      series: [{ name: 'Value', data: chartData.value.propertyValue }],
      options: { ...chartBaseOptions, colors: ['#7c5cbf'], xaxis: baseX },
    },
    {
      id: 'rent',
      title: 'Rental Income',
      series: [{ name: 'Rent', data: chartData.value.rentalIncome }],
      options: { ...chartBaseOptions, colors: ['#22c55e'], xaxis: baseX },
    },
    {
      id: 'equity',
      title: 'Equity Growth',
      series: [{ name: 'Equity', data: chartData.value.equity }],
      options: { ...chartBaseOptions, colors: ['#f5c518'], xaxis: baseX },
    },
  ]
})

async function exportPdf() {
  pdfGenerating.value = true
  try {
    generateRoiReportPdf({
      inputs: inputs.value,
      emirateConfig: emirateConfig.value,
      kpis: kpis.value,
      yearlyProjections: yearlyProjections.value,
      registrationFee: registrationFee.value,
      agencyFee: agencyFee.value,
      downPaymentAmount: downPaymentAmount.value,
      totalAcquisitionCost: totalAcquisitionCost.value,
    })
  } finally {
    pdfGenerating.value = false
  }
}
</script>
