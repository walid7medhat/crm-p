<template>
  <div class="plan-page">
    <div class="plan-shell">
      <header class="plan-top">
        <div>
          <p class="title-text">Payment Plan</p>
          <p>Create & customize payment plan for your property</p>
        </div>
        <div class="top-actions">
          <button class="btn light">Save Plan</button>
          <button class="btn primary" :disabled="exporting" @click="exportClientSchedule">
            {{ exporting ? 'Exporting...' : 'Export Schedule' }}
          </button>
        </div>
      </header>

      <section class="card input-strip">
        <div class="property-cell">
          <p class="label">Property / Unit</p>
          <div class="property-row">
            <div class="thumb"></div>
            <div>
              <p class="unit">Unit A-101</p>
              <p class="meta">Building A · 150 m²</p>
            </div>
          </div>
        </div>
        <label class="field">
          <span class="label">Original Price (OP)</span>
          <input v-model.number="originalPrice" min="0" type="number">
          <small>Base price used for plan calculation</small>
        </label>
        <label class="field">
          <span class="label">Selling Price (SP)</span>
          <input v-model.number="sellingPrice" min="0" type="number">
          <small>Client final selling price</small>
        </label>
        <label class="field plan-selector-field">
          <span class="sr-only">Choose Payment Plan</span>
          <div class="plan-select-wrap">
            <select v-model="selectedPaymentPlan" class="plan-select">
              <option disabled value="">Choose Payment Plan</option>
              <option v-for="plan in paymentPlanOptions" :key="plan" :value="plan">{{ plan }}</option>
            </select>
            <span class="plan-select-arrow">▼</span>
          </div>
          <small class="plan-selector-hint">{{ downPaymentPercent.toFixed(0) }}/{{ installmentPercent.toFixed(0) }} Initial Payment / Installments</small>
        </label>
      </section>

      <div class="main-grid">
        <section class="card calc-card">
          <div class="calc-head">
            <div class="calc-head-left">
              <span class="calc-head-icon">⚡</span>
              <p class="section-title">Automatic Calculation (Real-time)</p>
            </div>
            <span>Calculated Automatically</span>
          </div>
          <div class="calc-body">
            <article class="metric-card">
              <p class="label">Original Price (OP)</p>
            <p class="amount-text">{{ money(originalPrice) }}</p>
              <div class="mini-box">
                <strong>100%</strong>
                <span>Total Value</span>
              </div>
            </article>

            <article class="metric-card">
              <p class="label">Initial Payment ({{ downPaymentPercent.toFixed(0) }}%)</p>
              <p class="amount-text">{{ money(downPaymentTarget) }}</p>
              <div class="dp-note">
                <span>Paid Amount ({{ paidInitialPercent.toFixed(2) }}%)</span>
                <strong>{{ money(totalDownPaid) }}</strong>
              </div>
              <div class="dp-note">
                <span>Remaining to Complete</span>
                <strong>{{ money(remainingDownPayment) }}</strong>
              </div>
            </article>

            <article class="metric-card">
              <p class="label">Add Installments (Before {{ installmentPercent.toFixed(0) }}%)</p>
              <p class="amount-text">{{ money(totalDownPaid) }}</p>
              <div class="list-stack">
                <div v-for="entry in downPaymentBreakdown" :key="entry.id" class="list-item">
                  <div>
                    <b>{{ breakdownLabel(entry) }}</b>
                    <span>{{ formatDate(entry.date) }}</span>
                  </div>
                  <button @click="removeBreakdownItem(entry.id)">Remove</button>
                </div>
              </div>
              <button class="btn add" @click="showAddInstallmentModal = true">+ Add Installment</button>
            </article>

            <article class="metric-card">
              <p class="label">Installments ({{ installmentPercent.toFixed(0) }}%)</p>
              <p class="amount-text">{{ money(installmentPool) }}</p>
              <div class="mini-stat" v-if="premiumAmount > 0">
                <span>Premium (Selling - Original)</span>
                <strong>{{ money(premiumAmount) }}</strong>
              </div>
            </article>
          </div>
        </section>

        <aside class="side-column">
          <section class="card progress-card">
            <div class="progress-title">
              <p class="section-title">Payment Progress</p>
              <strong>{{ progressPercent.toFixed(0) }}%</strong>
            </div>
            <div class="bar"><div class="fill" :style="{ width: `${progressPercent}%` }"></div></div>
            <dl>
              <div><dt>Paid</dt><dd class="ok">{{ money(totalPaidOverall) }}</dd></div>
              <div><dt>Remaining</dt><dd class="warn">{{ money(remainingBalance) }}</dd></div>
              <div><dt>Total</dt><dd>{{ money(sellingPrice) }}</dd></div>
            </dl>
            <div class="next-pay">
              <small>Next Payment</small>
              <b>{{ formatDate(nextInstallmentDate) }}</b>
              <span>{{ money(monthlyInstallmentAmount) }}</span>
            </div>
          </section>

          <section class="card handover-card">
            <p class="section-title">Handover Date (Remaining {{ installmentPercent.toFixed(0) }}%)</p>
            <label class="handover-label">
              <span>Select handover date for final remaining percentage</span>
              <input v-model="handoverDate" type="date">
            </label>
            <div class="handover-amount">
              <small>Remaining amount on handover</small>
              <strong>{{ money(installmentPool) }}</strong>
            </div>
          </section>
        </aside>
      </div>

      <section class="card timeline-card">
        <p class="section-title">Payment Timeline</p>
        <div class="timeline-line">
          <div v-for="point in timelinePoints" :key="point.label" class="timeline-point">
            <div class="dot">{{ point.badge }}</div>
            <b>{{ point.label }}</b>
            <span>{{ point.value }}</span>
            <small>{{ point.date }}</small>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Type</th>
                <th>Percentage</th>
                <th>Amount (AED)</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in installmentRows" :key="row.id">
                <td>{{ row.id }}</td>
                <td>{{ row.type }}</td>
                <td>{{ row.type === 'Premium' ? '-' : `${row.percentage}%` }}</td>
                <td><b>{{ money(row.amount) }}</b></td>
                <td>{{ row.type === 'Premium' ? '-' : formatDate(row.date) }}</td>
                <td>
                  <span class="tag" :class="row.status === 'Paid' ? 'paid' : 'upcoming'">{{ row.status }}</span>
                </td>
              </tr>
              <tr v-if="installmentRows.length === 0">
                <td colspan="6" style="text-align:center; color:#64748b;">No installments yet. Add installment to create rows.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="table-footer">
          <button class="btn light">View Full Schedule</button>
        </div>
      </section>
    </div>

    <transition name="fade-scale">
      <div v-if="showAddInstallmentModal" class="modal-backdrop">
        <div class="modal-box">
          <div class="modal-head">
            <p class="section-title">Add Installment</p>
            <button @click="closeModal">Close</button>
          </div>
          <label>
            <span>Type</span>
            <select v-model="draftInstallment.type">
              <option value="amount">Amount</option>
              <option value="percentage">Percentage</option>
            </select>
          </label>
          <label>
            <span>{{ draftInstallment.type === 'percentage' ? 'Percentage' : 'Amount' }}</span>
            <input v-model.number="draftInstallment.value" min="0" :max="draftInstallment.type === 'percentage' ? 100 : null" type="number">
          </label>
          <label>
            <span>Date</span>
            <input v-model="draftInstallment.date" type="date">
          </label>
          <p v-if="draftError" class="err">{{ draftError }}</p>
          <div class="modal-actions">
            <button class="btn light" @click="closeModal">Cancel</button>
            <button class="btn primary" @click="addInstallment">Add</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Dedicated PDF layout (different from on-screen design) -->
    <div class="pdf-export-root">
      <div ref="pdfExportRef" class="pdf-sheet">
        <header class="pdf-header">
          <h2>Client Payment Schedule</h2>
          <p>Generated on {{ formatDate(new Date()) }}</p>
        </header>

        <section class="pdf-summary-grid">
          <article class="pdf-card">
            <small>Property Value</small>
            <strong>{{ money(sellingPrice) }}</strong>
          </article>
          <article class="pdf-card">
            <small>Selected Plan</small>
            <strong>{{ selectedPaymentPlan }}</strong>
          </article>
          <article class="pdf-card">
            <small>Initial Payment Target</small>
            <strong>{{ money(downPaymentTarget) }}</strong>
          </article>
          <article class="pdf-card" v-if="premiumAmount > 0">
            <small>Premium</small>
            <strong>{{ money(premiumAmount) }}</strong>
          </article>
          <article class="pdf-card">
            <small>Remaining On Handover</small>
            <strong>{{ money(installmentPool) }}</strong>
          </article>
        </section>

        <section class="pdf-timeline">
          <h3>Payment Timeline</h3>
          <div class="pdf-timeline-row">
            <div v-for="point in timelinePoints" :key="`pdf-${point.label}`" class="pdf-timeline-item">
              <b>{{ point.label }}</b>
              <span>{{ point.value }}</span>
              <small>{{ point.date }}</small>
            </div>
          </div>
        </section>

        <section class="pdf-table-wrap">
          <h3>Installment Schedule</h3>
          <table class="pdf-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Type</th>
                <th>Percentage</th>
                <th>Amount (AED)</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in installmentRows" :key="`pdf-row-${row.id}`">
                <td>{{ row.id }}</td>
                <td>{{ row.type }}</td>
                <td>{{ row.type === 'Premium' ? '-' : `${row.percentage}%` }}</td>
                <td>{{ money(row.amount) }}</td>
                <td>{{ row.type === 'Premium' ? '-' : formatDate(row.date) }}</td>
                <td>{{ row.status }}</td>
              </tr>
              <tr v-if="installmentRows.length === 0">
                <td colspan="6">No installments yet.</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'

const originalPrice = ref(1_000_000)
const sellingPrice = ref(1_000_000)
const downPaymentPercent = ref(30)
const durationMonths = ref(36)
const paymentPlanOptions = [
  '90/10', '85/15', '80/20', '75/25', '70/30', '65/35',
  '60/40', '55/45', '50/50', '45/55', '40/60', '35/65',
  '30/70', '25/75', '20/80', '15/85', '10/90',
]
const selectedPaymentPlan = ref('30/70')
const showAddInstallmentModal = ref(false)
const draftError = ref('')
const pdfExportRef = ref(null)
const exporting = ref(false)
const downPaymentBreakdown = ref([])
const handoverDate = ref(new Date(new Date().setMonth(new Date().getMonth() + 12)).toISOString().slice(0, 10))
const route = useRoute()

const draftInstallment = reactive({
  type: 'percentage',
  value: 5,
  date: new Date().toISOString().slice(0, 10),
})

const installmentPercent = computed(() => Math.max(0, 100 - Number(downPaymentPercent.value || 0)))
const downPaymentTarget = computed(() => (Number(originalPrice.value) * Number(downPaymentPercent.value || 0)) / 100)
const installmentPool = computed(() => Math.max(0, Number(originalPrice.value) - downPaymentTarget.value))
const premiumAmount = computed(() => Math.max(0, Number(sellingPrice.value) - Number(originalPrice.value)))
const premiumDate = computed(() => {
  if (!handoverDate.value) return null
  const d = new Date(handoverDate.value)
  if (Number.isNaN(d.getTime())) return null
  d.setDate(d.getDate() - 1)
  return d
})

const toDayStart = (value) => {
  const d = new Date(value)
  d.setHours(0, 0, 0, 0)
  return d
}

const isDatePaid = (dateLike) => {
  if (!dateLike) return false
  const paymentDate = toDayStart(dateLike)
  if (Number.isNaN(paymentDate.getTime())) return false
  const today = toDayStart(new Date())
  return paymentDate <= today
}

const toAmount = (item) => {
  if (item.type === 'percentage') return (Number(originalPrice.value) * Number(item.value || 0)) / 100
  return Number(item.value || 0)
}

const totalDownPaid = computed(() =>
  downPaymentBreakdown.value.reduce((sum, item) => {
    if (!isDatePaid(item.date)) return sum
    return sum + toAmount(item)
  }, 0),
)
const paidInitialPercent = computed(() =>
  Math.max(0, Math.min(100, (totalDownPaid.value / Math.max(1, Number(originalPrice.value))) * 100)),
)
const latestPaidInstallmentDate = computed(() => {
  const paidDates = downPaymentBreakdown.value
    .filter((item) => isDatePaid(item.date))
    .map((item) => new Date(item.date))
    .filter((d) => !Number.isNaN(d.getTime()))

  if (!paidDates.length) return new Date()
  return paidDates.sort((a, b) => b.getTime() - a.getTime())[0]
})
const remainingDownPayment = computed(() => Math.max(0, downPaymentTarget.value - totalDownPaid.value))
const paidPremium = computed(() => (premiumAmount.value > 0 && isDatePaid(premiumDate.value) ? premiumAmount.value : 0))
const paidHandover = computed(() => (isDatePaid(handoverDate.value) ? installmentPool.value : 0))
const totalPaidOverall = computed(() => totalDownPaid.value + paidPremium.value + paidHandover.value)
const remainingBalance = computed(() => Math.max(0, Number(sellingPrice.value) - totalPaidOverall.value))
const paidOverallPercent = computed(() =>
  Math.max(0, Math.min(100, (totalPaidOverall.value / Math.max(1, Number(sellingPrice.value))) * 100)),
)
const monthlyInstallmentAmount = computed(() => {
  const months = Math.max(1, Number(durationMonths.value || 1))
  return installmentPool.value / months
})

const progressPercent = computed(() => {
  if (!sellingPrice.value) return 0
  return Math.min(100, Math.max(0, (totalPaidOverall.value / Number(sellingPrice.value)) * 100))
})

const validationError = computed(() => {
  if (totalDownPaid.value > downPaymentTarget.value) {
    return 'Initial payment breakdown exceeds target initial payment.'
  }
  if (totalDownPaid.value > Number(originalPrice.value)) {
    return 'Initial payment cannot exceed original price.'
  }
  return ''
})

const nextInstallmentDate = computed(() => {
  const start = new Date()
  start.setMonth(start.getMonth() + 1)
  return start
})

const timelinePoints = computed(() => [
  {
    badge: `${paidOverallPercent.value.toFixed(0)}%`,
    label: 'Paid',
    value: money(totalPaidOverall.value),
    date: formatDate(latestPaidInstallmentDate.value),
    color: 'text-slate-700',
  },
  ...(premiumAmount.value > 0
    ? [{
        badge: 'Premium',
        label: 'Premium',
        value: money(premiumAmount.value),
        date: '-',
        color: 'text-slate-700',
      }]
    : []),
  {
    badge: `${installmentPercent.value.toFixed(0)}%`,
    label: 'Installments',
    value: money(installmentPool.value),
    date: formatDate(nextInstallmentDate.value),
    color: 'text-[#4F46E5]',
  },
])

const installmentRows = computed(() => {
  const rows = []
  let id = 1

  const sortedInstallments = downPaymentBreakdown.value
    .slice()
    .sort((a, b) => new Date(a.date) - new Date(b.date))

  const paidInstallments = sortedInstallments.filter((entry) => isDatePaid(entry.date))
  const upcomingInstallments = sortedInstallments.filter((entry) => !isDatePaid(entry.date))

  // Show all paid installments as a single cumulative line.
  if (paidInstallments.length) {
    const paidAmount = paidInstallments.reduce((sum, entry) => sum + toAmount(entry), 0)
    const paidDate = paidInstallments[0]?.date || new Date()
    rows.push({
      id: id++,
      type: 'Add Installment',
      percentage: ((paidAmount / Number(originalPrice.value || 1)) * 100).toFixed(2),
      amount: paidAmount,
      date: paidDate,
      status: 'Paid',
    })
  }

  upcomingInstallments.forEach((entry) => {
    const amount = toAmount(entry)
    rows.push({
      id: id++,
      type: 'Add Installment',
      percentage: ((amount / Number(originalPrice.value || 1)) * 100).toFixed(2),
      amount,
      date: entry.date,
      status: 'Upcoming',
    })
  })

  if (premiumAmount.value > 0) {
    rows.push({
      id: id++,
      type: 'Premium',
      percentage: ((premiumAmount.value / Number(sellingPrice.value || 1)) * 100).toFixed(2),
      amount: premiumAmount.value,
      date: premiumDate.value,
      status: isDatePaid(premiumDate.value) ? 'Paid' : 'Upcoming',
    })
  }

  if (installmentPool.value > 0) {
    rows.push({
      id: id++,
      type: `Installments (${installmentPercent.value.toFixed(0)}%)`,
      percentage: installmentPercent.value.toFixed(2),
      amount: installmentPool.value,
      date: handoverDate.value,
      status: isDatePaid(handoverDate.value) ? 'Paid' : 'Upcoming',
    })
  }

  return rows
})

const money = (value) =>
  new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(
    Number(value || 0),
  )

const formatDate = (dateLike) => {
  const date = new Date(dateLike)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const breakdownLabel = (entry) =>
  entry.type === 'percentage'
    ? `${Number(entry.value || 0).toFixed(2)}% (${money(toAmount(entry))})`
    : money(toAmount(entry))

const removeBreakdownItem = (id) => {
  downPaymentBreakdown.value = downPaymentBreakdown.value.filter((item) => item.id !== id)
}

const closeModal = () => {
  showAddInstallmentModal.value = false
  draftError.value = ''
}

const addInstallment = () => {
  draftError.value = ''
  const value = Number(draftInstallment.value || 0)
  if (value <= 0) {
    draftError.value = 'Please enter a valid amount or percentage.'
    return
  }
  if (!draftInstallment.date) {
    draftError.value = 'Please select a date.'
    return
  }

  const newItem = {
    id: Date.now(),
    type: draftInstallment.type,
    value,
    date: draftInstallment.date,
  }

  const projectedTotal = totalDownPaid.value + toAmount(newItem)
  if (projectedTotal > downPaymentTarget.value) {
    draftError.value = 'This installment exceeds the down payment target.'
    return
  }

  downPaymentBreakdown.value.push(newItem)
  closeModal()
}

const exportClientSchedule = async () => {
  if (!pdfExportRef.value || exporting.value) return

  try {
    exporting.value = true

    const html2pdf = (await import('html2pdf.js')).default
    const now = new Date()
    const stamp = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
    const filename = `payment-plan-${selectedPaymentPlan.value || 'custom'}-${stamp}.pdf`

    const options = {
      margin: [8, 8, 8, 8],
      filename,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale: 2,
        useCORS: true,
        backgroundColor: '#f2f3f7',
      },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
      pagebreak: { mode: ['css', 'legacy'] },
    }

    await html2pdf().set(options).from(pdfExportRef.value).save()
  } catch (error) {
    console.error('Error exporting schedule:', error)
    alert('Could not export PDF. Please try again.')
  } finally {
    exporting.value = false
  }
}

const extractPropertyPayload = (responseData) => {
  if (!responseData) return null
  if (responseData?.data?.data) return responseData.data.data
  if (responseData?.data) return responseData.data
  return responseData
}

const toNumeric = (value) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const hydratePricesFromProperty = (property) => {
  if (!property) return
  const op =
    toNumeric(property.original_price) ??
    toNumeric(property.originalPrice) ??
    toNumeric(property.price) ??
    null

  const sp =
    toNumeric(property.selling_price) ??
    toNumeric(property.sellingPrice) ??
    toNumeric(property.price) ??
    null

  if (op !== null) originalPrice.value = op
  if (sp !== null) sellingPrice.value = sp
}

const loadPropertyPricing = async () => {
  const propertyId = route.params?.id
  if (!propertyId) return

  try {
    const response = await api.get(`/listings/properties/${propertyId}`)
    const property = extractPropertyPayload(response.data)
    hydratePricesFromProperty(property)
  } catch (error) {
    console.error('Could not load property pricing for payment breakdown:', error)
  }
}

watch(
  selectedPaymentPlan,
  (plan) => {
    if (!plan) return
    const [down] = String(plan || '').split('/')
    const parsed = Number(down)
    if (Number.isFinite(parsed)) {
      downPaymentPercent.value = Math.max(0, Math.min(100, parsed))
    }
  },
  { immediate: true },
)

onMounted(() => {
  loadPropertyPricing()
})
</script>

<style scoped>
.plan-page {
  --brand: #0B0736;
  --brand-soft: #eef0f8;
  --brand-border: #cfd5ec;
  background: #f2f3f7;
  min-height: 100vh;
  padding: 20px;
  font-family: Inter, 'Segoe UI', Tahoma, sans-serif;
  color: #1f2937;
  font-size: 12px;
  line-height: 1.35;
}
.plan-shell {
  max-width: 1460px;
  margin: 0 auto;
}
.plan-top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.plan-top p {
  margin: 6px 0 0;
  font-size: 11px;
  color: #6b7280;
}
.title-text {
  margin: 0;
  font-size: 52px;
  line-height: 1.1;
  font-weight: 800;
  color: #111827;
}
.plan-top p:not(.title-text) {
  margin-top: 8px;
  font-size: 15px;
  color: #6b7280;
}
.section-title {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.2;
}
.top-actions {
  display: flex;
  gap: 8px;
}
.btn {
  border-radius: 9px;
  padding: 10px 16px;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid transparent;
}
.btn.light {
  background: #fff;
  color: #374151;
  border-color: #dde1eb;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.btn.primary {
  background: var(--brand);
  color: #fff;
  box-shadow: 0 8px 20px rgba(11, 7, 54, 0.28);
}
.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.card {
  background: #fff;
  border: 1px solid #e3e6ef;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
}
.input-strip {
  padding: 14px;
  margin-bottom: 16px;
  display: grid;
  grid-template-columns: 250px repeat(3, 1fr);
  gap: 10px;
}
.property-cell, .field {
  border: 1px solid #e4e7f0;
  border-radius: 10px;
  padding: 10px;
  background: #fff;
}
.label {
  margin: 0 0 6px;
  font-size: 11px;
  font-weight: 700;
  color: #6b7280;
}
.property-row {
  display: flex;
  gap: 10px;
  align-items: center;
}
.thumb {
  width: 46px;
  height: 46px;
  border-radius: 8px;
  background: linear-gradient(135deg, #d1d5e2 0%, #e5e7ef 100%);
}
.unit {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
}
.meta {
  margin: 4px 0 0;
  font-size: 10px;
  color: #64748b;
}
.field input, .modal-box input, .modal-box select {
  width: 100%;
  height: 38px;
  border: 1px solid #dee3ee;
  border-radius: 8px;
  padding: 0 10px;
  font-size: 12px;
}
.plan-selector-field {
  border: 1px solid var(--brand-border);
  background: var(--brand-soft);
}
.plan-selector-label {
  color: var(--brand);
}
.plan-select-wrap {
  position: relative;
  width: 180px;
  max-width: 100%;
}
.plan-select {
  width: 100%;
  height: 42px;
  border: 2px solid var(--brand);
  border-radius: 10px;
  padding: 0 34px;
  font-size: 13px;
  font-weight: 700;
  color: var(--brand);
  background: #ffffff;
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  cursor: pointer;
  text-align: center;
  text-align-last: center;
}
.plan-select option {
  text-align: center;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
.plan-select:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(11, 7, 54, 0.14);
}
.plan-select-arrow {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 10px;
  color: var(--brand);
  pointer-events: none;
}
.plan-selector-hint {
  color: var(--brand);
  font-weight: 600;
}
.field small {
  display: block;
  margin-top: 6px;
  color: #8a92a6;
  font-size: 10px;
}
.readonly-value {
  width: 100%;
  height: 38px;
  border: 1px solid #dfe3ec;
  border-radius: 8px;
  padding: 0 10px;
  display: flex;
  align-items: center;
  font-size: 12px;
  color: #334155;
  background: #f8fafc;
}
.main-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 12px;
  align-items: start;
}
.calc-card {
  min-width: 0;
  overflow: hidden;
}
.calc-head {
  background: var(--brand);
  color: #fff;
  border-radius: 12px 12px 0 0;
  padding: 10px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.calc-head-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.calc-head-icon {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  color: var(--brand);
  background: #fff;
  line-height: 1;
}
.calc-head .section-title { color: #fff; font-size: 12px; }
.calc-head span {
  font-size: 10px;
  background: rgba(255, 255, 255, 0.28);
  padding: 3px 10px;
  border-radius: 999px;
}
.calc-body {
  padding: 14px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  min-width: 0;
}
.metric-card {
  border: 1px solid #e6e9f2;
  border-radius: 10px;
  padding: 10px;
  min-width: 0;
  overflow: hidden;
}
.amount-text {
  margin: 0;
  font-size: clamp(18px, 1.5vw, 30px);
  font-weight: 700;
  line-height: 1.06;
  color: var(--brand);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mini-box, .mini-stat {
  margin-top: 10px;
  border: 1px solid #e6e9f2;
  border-radius: 8px;
  padding: 7px;
  background: #f8f9fd;
}
.mini-box strong, .mini-stat strong { display: block; font-size: 12px; }
.mini-box span, .mini-stat span { font-size: 10px; color: #64748b; }
.dp-note {
  margin-top: 8px;
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #64748b;
}
.list-stack {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 128px;
  overflow: auto;
}
.list-item {
  border: 1px solid #e7e9f1;
  background: #f8f9fc;
  border-radius: 8px;
  padding: 6px;
  display: flex;
  justify-content: space-between;
  gap: 8px;
  min-width: 0;
}
.list-item b { display: block; font-size: 10px; }
.list-item span { font-size: 9px; color: #64748b; }
.list-item > div {
  min-width: 0;
}
.list-item b {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.list-item button {
  border: none;
  background: transparent;
  color: #ef4444;
  font-size: 11px;
}
.btn.add {
  margin-top: 8px;
  width: 100%;
  padding: 8px 10px;
  background: var(--brand-soft);
  color: var(--brand);
  border: 1px solid var(--brand-border);
}
.side-column { display: flex; flex-direction: column; gap: 14px; }
.progress-card, .actions-card { padding: 14px; }
.side-column .card {
  width: 100%;
}
.progress-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.bar {
  margin-top: 10px;
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: #eceef5;
}
.fill {
  height: 100%;
  background: var(--brand);
}
.progress-card dl {
  margin: 12px 0 0;
}
.progress-card dl div {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  margin-bottom: 8px;
}
.progress-card dt { color: #64748b; }
.progress-card dd { margin: 0; font-weight: 700; }
.progress-card .ok { color: #22c55e; }
.progress-card .warn { color: #ef4444; }
.next-pay {
  margin-top: 10px;
  background: var(--brand-soft);
  border-radius: 10px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.next-pay small { font-size: 10px; color: var(--brand); font-weight: 600; }
.next-pay b { font-size: 12px; }
.next-pay span { font-size: 10px; color: #475569; }
.handover-card {
  padding: 14px;
  background: var(--brand-soft);
  border-color: var(--brand-border);
}
.handover-label {
  display: block;
  margin-top: 10px;
}
.handover-label span {
  display: block;
  margin-bottom: 6px;
  font-size: 11px;
  color: #4b5563;
  font-weight: 600;
}
.handover-label input {
  width: 100%;
  height: 38px;
  border: 1px solid var(--brand-border);
  border-radius: 8px;
  padding: 0 10px;
  background: #fff;
  font-size: 12px;
  color: #1f2937;
}
.handover-amount {
  margin-top: 10px;
  border: 1px dashed var(--brand-border);
  border-radius: 8px;
  padding: 8px 10px;
  background: #fff;
}
.handover-amount small {
  display: block;
  font-size: 10px;
  color: var(--brand);
}
.handover-amount strong {
  display: block;
  margin-top: 2px;
  font-size: 14px;
  color: var(--brand);
}
.timeline-card {
  margin-top: 14px;
  padding: 14px;
}
.timeline-line {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
  position: relative;
}
.timeline-line::before {
  content: '';
  position: absolute;
  left: 8%;
  right: 8%;
  top: 16px;
  height: 2px;
  background: #e5e7ef;
}
.timeline-point {
  position: relative;
  text-align: center;
  z-index: 1;
}
.dot {
  margin: 0 auto 8px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--brand);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.timeline-point b { font-size: 12px; display: block; }
.timeline-point span { display: block; font-size: 10px; color: #374151; font-weight: 600; }
.timeline-point small { font-size: 9px; color: #64748b; }
.table-wrap {
  margin-top: 12px;
  overflow-x: auto;
}
table { width: 100%; border-collapse: collapse; }
th, td {
  border-bottom: 1px solid #edf0f6;
  padding: 10px 8px;
  font-size: 11px;
}
th {
  text-align: left;
  color: #64748b;
  font-weight: 700;
}
.tag {
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 10px;
  font-weight: 700;
}
.tag.paid { background: #dcfce7; color: #15803d; }
.tag.upcoming { background: #e0e7ff; color: #4338ca; }
.tag.upcoming { background: var(--brand-soft); color: var(--brand); }
.table-footer {
  margin-top: 12px;
  display: flex;
  justify-content: center;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.modal-box {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e6e8ef;
  padding: 14px;
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.modal-head h3 {
  margin: 0;
  font-size: 12px;
}
.modal-head button {
  border: none;
  background: #f3f4f8;
  border-radius: 8px;
  padding: 5px 8px;
  font-size: 11px;
}
.modal-box label {
  display: block;
  margin-bottom: 10px;
}
.modal-box label span {
  display: block;
  margin-bottom: 5px;
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}
.err {
  margin: 8px 0 0;
  font-size: 11px;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 8px;
}
.modal-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 1280px) {
  .input-strip {
    grid-template-columns: repeat(2, 1fr);
  }
  .main-grid {
    grid-template-columns: 1fr;
  }
  .metric-card h3 {
    white-space: normal;
    overflow: visible;
    text-overflow: initial;
  }
}

@media (max-width: 900px) {
  .title-text {
    font-size: 34px;
  }
  .calc-body {
    grid-template-columns: 1fr;
  }
  .timeline-line {
    grid-template-columns: 1fr;
    row-gap: 16px;
  }
  .timeline-line::before {
    display: none;
  }
  .input-strip {
    grid-template-columns: 1fr;
  }
}

.fade-scale-enter-active,
.fade-scale-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
  opacity: 0;
  transform: scale(0.97);
}

.pdf-export-root {
  position: fixed;
  left: -99999px;
  top: 0;
  width: 1280px;
  pointer-events: none;
}

.pdf-sheet {
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #e2e8f0;
  padding: 20px;
  border-radius: 12px;
}

.pdf-header h2 {
  margin: 0;
  font-size: 24px;
}

.pdf-header p {
  margin: 4px 0 0;
  font-size: 12px;
  color: #64748b;
}

.pdf-summary-grid {
  margin-top: 14px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.pdf-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
  background: #f8fafc;
}

.pdf-card small {
  display: block;
  color: #64748b;
  font-size: 11px;
}

.pdf-card strong {
  display: block;
  margin-top: 4px;
  font-size: 20px;
  color: #0B0736;
}

.pdf-timeline,
.pdf-table-wrap {
  margin-top: 16px;
}

.pdf-timeline h3,
.pdf-table-wrap h3 {
  margin: 0 0 8px;
  font-size: 15px;
}

.pdf-timeline-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.pdf-timeline-item {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px;
  background: #f8fafc;
}

.pdf-timeline-item b {
  display: block;
  font-size: 12px;
}

.pdf-timeline-item span {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  font-weight: 700;
}

.pdf-timeline-item small {
  display: block;
  margin-top: 2px;
  color: #64748b;
  font-size: 11px;
}

.pdf-table {
  width: 100%;
  border-collapse: collapse;
}

.pdf-table th,
.pdf-table td {
  border: 1px solid #e2e8f0;
  padding: 8px;
  font-size: 11px;
  text-align: left;
}

.pdf-table th {
  background: #f8fafc;
}
</style>
