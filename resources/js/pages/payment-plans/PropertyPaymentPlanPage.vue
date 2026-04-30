<template>
  <div class="pp-root">
    <div v-if="store.loadingDetails" class="pp-loading">Loading payment plan…</div>
    <div v-else-if="store.error" class="pp-error">{{ store.error }}</div>

    <template v-else-if="store.propertyDetails">
      <div class="pp-shell">
        <div class="pp-card">
          <div class="pp-box pp-top-box">
            <div>
              <div class="pp-label">SELECTED PRICE:</div>
              <div class="pp-price">{{ formatPlainNumber(selectedPriceBasis) }} AED</div>
            </div>
            <div>
              <div class="pp-label pp-right">Plan Progress</div>
              <div class="pp-progress">
                <span v-for="i in progressPillCount" :key="i" :class="['pp-progress-pill', i <= filledProgressPills ? 'active' : '']"></span>
              </div>
            </div>
          </div>

          <div class="pp-box pp-down-box">
            <h6 class="pp-title">Down Payment</h6>
            <div class="pp-down-row">
              <div class="pp-down-input-wrap">
                <input v-model.number="pricing.ownerPaid" type="number" min="0" step="1" class="pp-down-input" />
                <span class="pp-down-currency">AED</span>
              </div>
              <div class="pp-status">
                <span class="pp-status-dot" :class="Number(pricing.ownerPaid || 0) > 0 ? 'on' : ''"></span>
                Status: {{ Number(pricing.ownerPaid || 0) > 0 ? 'Paid' : 'Pending' }}
              </div>
            </div>
          </div>

          <div v-if="installmentFormError" class="pp-error-inline">{{ installmentFormError }}</div>

          <div class="pp-section">
            <h6 class="pp-section-heading">Installments Timeline</h6>
            <div class="pp-timeline-head">
              <span>Due Date</span>
              <span>Step Name</span>
            </div>
            <div class="pp-timeline-body">
              <div class="pp-timeline-line"></div>
              <div v-for="(item, idx) in scheduleInstallments" :key="`tl-${item.id}`" class="pp-timeline-row">
                <span class="pp-step-dot">{{ idx + 1 }}</span>
                <span class="pp-step-date">{{ item.due_date }}</span>
                <span class="pp-step-name">{{ item.name }}</span>
              </div>
              <p v-if="scheduleInstallments.length === 0" class="pp-empty">No installments yet.</p>
            </div>
          </div>

          <div class="pp-section">
            <h6 class="pp-section-heading">Dynamic Installments Table</h6>
            <div class="pp-table-wrap">
              <table class="pp-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Installment Name</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in scheduleInstallments" :key="`row-${item.id}`">
                    <td>{{ item.due_date }}</td>
                    <td>
                      {{ item.name }}
                      <span v-if="Number(item.remaining_amount || 0) <= 0 || item.status === 'paid'" class="pp-paid-pill">Paid</span>
                    </td>
                    <td class="text-end">{{ formatPlainNumber(item.amount) }}</td>
                    <td class="text-end">
                      <div class="pp-actions">
                        <button type="button" class="pp-icon-btn" @click="openEditModal(item)" title="Edit"><i class="ri-settings-3-line"></i></button>
                        <button type="button" class="pp-icon-btn" @click="openPaymentModal(item)" title="Pay"><i class="ri-bank-card-line"></i></button>
                        <button type="button" class="pp-icon-btn danger" :disabled="Number(item.paid_amount || 0) > 0" @click="confirmRemoveInstallment(item.id)" title="Delete"><i class="ri-delete-bin-line"></i></button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="scheduleInstallments.length === 0">
                    <td colspan="4" class="pp-empty">No rows yet.</td>
                  </tr>
                </tbody>
              </table>
              <button type="button" class="pp-add-row" @click="openAddModal">Add Installment</button>
            </div>
          </div>

          <div class="pp-summary">
          <div class="px-3 py-4 text-center sm:px-2">
            <p class="pp-label pp-micro mb-1 font-bold uppercase leading-snug text-slate-500">Total percent accounted for</p>
            <p class="pp-h6 font-bold tabular-nums" :class="isPlanFullyAccounted ? 'text-emerald-600' : 'text-amber-600'">{{ totalPercentAccounted }}%</p>
          </div>
          <div class="px-3 py-4 text-center sm:px-2">
            <p class="pp-label pp-micro mb-1 font-bold uppercase text-slate-500">Remaining balance</p>
            <p class="pp-h6 mb-2 font-bold tabular-nums" :class="isPlanFullyAccounted ? 'text-emerald-600' : 'text-slate-900'">
              {{ formatPlainNumber(remainingBalanceDisplay) }} AED
            </p>
            <div
              class="mx-auto flex h-10 w-10 items-center justify-center rounded-full border-2 text-white"
              :class="isPlanFullyAccounted ? 'border-[#2D7A88] bg-[#2D7A88]' : 'border-slate-300 bg-slate-200 text-slate-400'"
            >
              <i class="ri-check-line text-lg font-bold" aria-hidden="true" />
            </div>
          </div>
          <div class="px-3 py-4 text-center sm:px-2">
            <p class="pp-label pp-micro mb-2 font-bold uppercase text-slate-500">Load plan templates</p>
            <div class="flex flex-wrap justify-center gap-2">
              <button
                type="button"
                class="pp-pill-template rounded-full border border-slate-300 bg-white px-4 py-1.5 font-bold text-slate-900 shadow-sm transition hover:bg-slate-50"
                :class="{ 'ring-2 ring-[#2D7A88] ring-offset-1': selectedTemplate === '30_70' }"
                @click="applyTemplateQuick('30_70')"
              >
                30/70
              </button>
              <button
                type="button"
                class="pp-pill-template rounded-full border border-slate-300 bg-white px-4 py-1.5 font-bold text-slate-900 shadow-sm transition hover:bg-slate-50"
                :class="{ 'ring-2 ring-[#2D7A88] ring-offset-1': selectedTemplate === '50_50' }"
                @click="applyTemplateQuick('50_50')"
              >
                50/50
              </button>
            </div>
          </div>
          </div>

          <div class="pp-save-wrap">
          <div
            v-if="!isPlanFullyAccounted && selectedPriceBasis > 0"
            class="pp-warn"
          >
            <i class="ri-error-warning-line me-1 align-middle" aria-hidden="true" />
            Totals must equal selected price. Gap {{ formatPlainNumber(accountedDifferenceAbs) }} AED.
          </div>

          <span class="pp-save-block" :title="saveButtonTooltip">
            <button
              type="button"
              class="pp-save-btn"
              :class="canSave ? 'bg-[#2D7A88] hover:brightness-[0.97]' : 'bg-slate-400'"
              :disabled="!canSave"
              @click="onSavePaymentPlan"
            >
              <span>{{ store.processing ? 'Saving…' : 'Save payment plan' }}</span>
              <i class="ri-arrow-right-line pp-save-arrow" aria-hidden="true" />
            </button>
          </span>
          </div>
        </div>
      </div>
    </template>

    <InstallmentModal
      :open="installmentModalOpen"
      :installment="selectedInstallment"
      :payment-plan-id="paymentPlanId || 0"
      :property-id="Number(propertyId)"
      :processing="store.processing"
      @close="closeInstallmentModal"
      @submit="submitInstallment"
    />

    <PaymentModal
      :open="paymentModalOpen"
      :processing="store.processing"
      @close="closePaymentModal"
      @submit="submitPayment"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePaymentPlansStore } from '@/stores/paymentPlans'
import InstallmentModal from '@/components/payment-plans/InstallmentModal.vue'
import PaymentModal from '@/components/payment-plans/PaymentModal.vue'

const progressPillCount = 7

const route = useRoute()
const store = usePaymentPlansStore()
const propertyId = route.params.id

const installmentModalOpen = ref(false)
const paymentModalOpen = ref(false)
const selectedInstallment = ref(null)
const pricing = ref({
  op: 0,
  sp: 0,
  ownerPaid: 0
})

function isDownPaymentInstallmentName(name) {
  const raw = String(name ?? '').trim().toLowerCase()
  if (!raw) return false
  const compact = raw.replace(/\s+/g, '')
  return compact === 'downpayment' || raw === 'down payment'
}

const paymentPlanId = computed(() => Number(store.propertyDetails?.payment_plan?.id || 0))
const installments = computed(() => {
  const rows = store.propertyDetails?.payment_plan?.installments || []
  const map = new Map()
  rows.forEach((row) => {
    map.set(Number(row.id), row)
  })
  return Array.from(map.values())
})

const scheduleInstallments = computed(() => installments.value.filter((row) => !isDownPaymentInstallmentName(row.name)))

const hasLegacyDownInstallmentRow = computed(() => installments.value.some((row) => isDownPaymentInstallmentName(row.name)))

const selectedTemplate = ref('custom')
const quickPay = ref({
  installmentId: '',
  amount: '',
  date: new Date().toISOString().slice(0, 10)
})
const installmentFormError = ref('')

const round2 = (value) => Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100
const formatPlainNumber = (value) => {
  const num = round2(value)
  const isInteger = Math.abs(num % 1) < 0.0000001
  return new Intl.NumberFormat('en-AE', {
    minimumFractionDigits: isInteger ? 0 : 2,
    maximumFractionDigits: isInteger ? 0 : 2
  }).format(num)
}

const selectedPriceBasis = computed(() => round2(Number(pricing.value.sp || pricing.value.op || 0)))
const downPaymentAed = computed(() => round2(Number(pricing.value.ownerPaid || 0)))
const scheduleTotalAed = computed(() =>
  round2(scheduleInstallments.value.reduce((carry, item) => carry + Number(item.amount || 0), 0))
)
const combinedTotalAed = computed(() => round2(downPaymentAed.value + scheduleTotalAed.value))
const accountedDifference = computed(() => round2(selectedPriceBasis.value - combinedTotalAed.value))
const accountedDifferenceAbs = computed(() => round2(Math.abs(accountedDifference.value)))
const isPlanFullyAccounted = computed(
  () => selectedPriceBasis.value > 0 && accountedDifferenceAbs.value <= 0.02
)
const totalPercentAccounted = computed(() => {
  const selected = Number(selectedPriceBasis.value || 0)
  if (!selected) return '0.00'
  return round2((combinedTotalAed.value / selected) * 100).toFixed(2)
})

const filledProgressPills = computed(() => {
  const pct = Number(totalPercentAccounted.value) || 0
  return Math.min(progressPillCount, Math.max(0, Math.round((pct / 100) * progressPillCount)))
})

const remainingBalanceDisplay = computed(() => {
  if (isPlanFullyAccounted.value) return 0
  return accountedDifferenceAbs.value
})

const downPaymentPercentOfSelected = computed(() => {
  const s = Number(selectedPriceBasis.value || 0)
  if (!s) return '0.00'
  return round2((downPaymentAed.value / s) * 100).toFixed(2)
})

const canSave = computed(() => isPlanFullyAccounted.value && !store.processing && selectedPriceBasis.value > 0)

const saveButtonTooltip = computed(() => {
  if (store.processing) return ''
  if (selectedPriceBasis.value <= 0) return 'Set a selling price greater than zero.'
  if (!isPlanFullyAccounted.value) {
    return `Down payment plus installments must equal the selected price (100%). Current: ${totalPercentAccounted.value}% (gap ${formatPlainNumber(accountedDifferenceAbs.value)} AED).`
  }
  return ''
})

const openAddModal = async () => {
  installmentFormError.value = ''
  if (!paymentPlanId.value) {
    try {
      await store.initializePaymentPlan(Number(propertyId))
      await nextTick()
    } catch {
      installmentFormError.value =
        store.error || 'Could not create a payment plan. Set selling price or use a template (30/70, 50/50).'
      return
    }
  }
  if (!paymentPlanId.value) {
    installmentFormError.value = 'Payment plan was not created. Try again or apply a template.'
    return
  }
  selectedInstallment.value = null
  installmentModalOpen.value = true
}

const openEditModal = (item) => {
  selectedInstallment.value = item
  installmentModalOpen.value = true
}

const closeInstallmentModal = () => {
  installmentModalOpen.value = false
}

const submitInstallment = async (payload) => {
  installmentFormError.value = ''
  try {
    await savePricing()
    await store.saveInstallment(payload)
    await Promise.all([store.fetchPropertyDetails(propertyId), store.fetchClientSummary(propertyId)])
    closeInstallmentModal()
  } catch {
    installmentFormError.value = store.error || 'Failed to save installment.'
  }
}

const confirmRemoveInstallment = async (installmentId) => {
  if (!window.confirm('Delete this installment?')) return
  installmentFormError.value = ''
  try {
    await store.deleteInstallment(installmentId, Number(propertyId))
    await Promise.all([store.fetchPropertyDetails(propertyId), store.fetchClientSummary(propertyId)])
  } catch {
    installmentFormError.value = store.error || 'Failed to delete installment.'
  }
}

const openPaymentModal = (item) => {
  selectedInstallment.value = item
  paymentModalOpen.value = true
}

const closePaymentModal = () => {
  paymentModalOpen.value = false
}

const submitPayment = async (payload) => {
  if (!selectedInstallment.value?.id) return
  installmentFormError.value = ''
  try {
    await store.addPayment(selectedInstallment.value.id, payload, Number(propertyId))
    await Promise.all([store.fetchPropertyDetails(propertyId), store.fetchClientSummary(propertyId)])
    closePaymentModal()
  } catch {
    installmentFormError.value = store.error || 'Failed to record payment.'
  }
}

const applyTemplateQuick = async (templateName) => {
  selectedTemplate.value = templateName
  const sp = round2(Number(pricing.value.sp || 0))
  if (sp <= 0) {
    installmentFormError.value = 'Set a selling price before applying a template.'
    return
  }

  const today = new Date()
  const addMonths = (base, months) => {
    const date = new Date(base)
    date.setMonth(date.getMonth() + months)
    return date.toISOString().slice(0, 10)
  }

  let downRatio = 0
  let planRows = []
  if (templateName === '30_70') {
    downRatio = 0.3
    planRows = [
      { name: 'Construction', percentage: '28.571429', due_date: addMonths(today, 3) },
      { name: 'Handover', percentage: '71.428571', due_date: addMonths(today, 6) }
    ]
  } else if (templateName === '50_50') {
    downRatio = 0.5
    planRows = [{ name: 'Handover', percentage: '100.000000', due_date: addMonths(today, 6) }]
  } else {
    return
  }

  pricing.value.ownerPaid = round2(sp * downRatio)
  installmentFormError.value = ''
  try {
    await savePricing()
    await store.createPaymentPlan(Number(propertyId), planRows)
    await Promise.all([store.fetchPropertyDetails(propertyId), store.fetchClientSummary(propertyId)])
  } catch {
    installmentFormError.value = store.error || 'Failed to apply template.'
  }
}

const savePricing = async () => {
  const payload = {
    original_price: Number(pricing.value.op || 0).toFixed(2),
    selling_price: Number(pricing.value.sp || 0).toFixed(2),
    down_payment_percentage: '0.000000',
    down_payment_fixed_amount: Number(pricing.value.ownerPaid || 0).toFixed(2),
    owner_paid_amount: Number(pricing.value.ownerPaid || 0).toFixed(2)
  }
  await store.updatePropertyPricing(Number(propertyId), payload)
}

const onSavePaymentPlan = async () => {
  if (!canSave.value) return
  installmentFormError.value = ''
  try {
    await savePricing()
    await Promise.all([store.fetchPropertyDetails(propertyId), store.fetchClientSummary(propertyId)])
  } catch {
    installmentFormError.value = store.error || 'Failed to save payment plan.'
  }
}

watch(
  installments,
  (rows) => {
    if (!rows?.length) {
      quickPay.value.installmentId = ''
      return
    }
    const sched = rows.filter((row) => !isDownPaymentInstallmentName(row.name))
    const exists = sched.some((row) => Number(row.id) === Number(quickPay.value.installmentId))
    if (!exists) {
      const firstUnpaid = sched.find((row) => Number(row.remaining_amount || 0) > 0) || sched[0]
      quickPay.value.installmentId = firstUnpaid?.id || ''
    }
  },
  { immediate: true }
)

onMounted(async () => {
  await Promise.all([store.fetchPropertyDetails(propertyId), store.fetchClientSummary(propertyId)])
  pricing.value.op = Number(store.propertyDetails?.original_price || 0)
  pricing.value.sp = Number(store.propertyDetails?.selling_price || store.propertyDetails?.selected_price || 0)
  const initDown = Number(
    store.propertyDetails?.initial_paid_amount ??
      store.propertyDetails?.down_payment_fixed_amount ??
      store.propertyDetails?.owner_paid_amount ??
      0
  )
  pricing.value.ownerPaid = round2(initDown)
  selectedTemplate.value = store.selectedTemplateName || 'custom'
})
</script>

<style scoped>
.pp-root {
  min-height: 100%;
  width: 100%;
  background: #edf2f6;
  font-family: 'Montserrat', 'Inter', ui-sans-serif, system-ui, sans-serif;
  font-size: 13px;
  line-height: 1.4;
  color: #0f172a;
  padding: 18px 12px 28px;
}
.pp-shell {
  max-width: 1180px;
  margin: 0 auto;
}
.pp-card {
  background: #fff;
  border: 1px solid #d7e1e8;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
}
.pp-loading {
  text-align: center;
  color: #64748b;
  padding: 64px 0;
}
.pp-error {
  max-width: 960px;
  margin: 20px auto;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
  border-radius: 12px;
  padding: 10px 12px;
  text-align: center;
}
.pp-box {
  margin: 14px 14px 0;
  border: 1px solid #d7e1e8;
  border-radius: 14px;
  background: #fff;
  padding: 14px 16px;
}
.pp-top-box {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}
.pp-label {
  font-size: 11px;
  letter-spacing: 0.04em;
  font-weight: 700;
  color: #334155;
}
.pp-label.pp-right {
  text-align: right;
}
.pp-price {
  font-family: Georgia, 'Times New Roman', Times, serif;
  font-size: 52px;
  line-height: 1;
  font-weight: 700;
  color: #0f172a;
  margin-top: 2px;
}
.pp-progress {
  display: flex;
  gap: 6px;
  margin-top: 8px;
}
.pp-progress-pill {
  width: 58px;
  height: 8px;
  border-radius: 999px;
  background: #e2e8f0;
  border: 1px solid #d7e1e8;
}
.pp-progress-pill.active {
  background: #2d7a88;
  border-color: #2d7a88;
}
.pp-down-box .pp-title {
  margin: 0 0 10px;
}
.pp-title,
.pp-section-heading,
.pp-h6 {
  font-size: 42px;
  line-height: 1.1;
  font-weight: 800;
  margin: 0;
  color: #0f172a;
}
.pp-down-row {
  display: flex;
  gap: 10px;
  align-items: stretch;
}
.pp-down-input-wrap {
  flex: 1;
  display: flex;
  border: 1px solid #d7e1e8;
  border-radius: 10px;
  overflow: hidden;
}
.pp-down-input {
  flex: 1;
  border: 0;
  padding: 11px 12px;
  font-size: 20px;
  font-weight: 600;
  outline: none;
}
.pp-down-currency {
  border-left: 1px solid #d7e1e8;
  background: #f8fafc;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 68px;
  font-size: 18px;
  font-weight: 700;
  color: #334155;
}
.pp-status {
  min-width: 184px;
  border-radius: 10px;
  background: #2d7a88;
  color: #fff;
  font-size: 20px;
  font-weight: 700;
  padding: 10px 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.pp-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #cbd5e1;
}
.pp-status-dot.on {
  background: #4ade80;
}
.pp-error-inline {
  margin: 12px 14px 0;
  border: 1px solid #fecaca;
  background: #fef2f2;
  border-radius: 10px;
  padding: 8px 10px;
  color: #991b1b;
}
.pp-section {
  margin: 14px 14px 0;
}
.pp-section-heading {
  margin-bottom: 8px;
}
.pp-timeline-head {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  padding: 8px 12px;
  font-size: 15px;
  font-weight: 700;
  color: #334155;
}
.pp-timeline-body {
  position: relative;
  padding: 6px 0 0;
}
.pp-timeline-line {
  position: absolute;
  top: 20px;
  bottom: 14px;
  left: 36px;
  width: 2px;
  background: #2d7a88;
  opacity: 0.35;
}
.pp-timeline-row {
  position: relative;
  display: grid;
  grid-template-columns: 60px 130px 1fr;
  align-items: center;
  gap: 12px;
  padding: 7px 0;
}
.pp-step-dot {
  width: 36px;
  height: 36px;
  border-radius: 999px;
  background: #2d7a88;
  color: #fff;
  font-size: 17px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.pp-step-date,
.pp-step-name {
  font-size: 23px;
  font-weight: 600;
  color: #1f2937;
}
.pp-table-wrap {
  border: 1px solid #d7e1e8;
  border-radius: 10px;
  overflow: hidden;
}
.pp-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 19px;
}
.pp-table th,
.pp-table td {
  border-bottom: 1px solid #e2e8f0;
  padding: 8px 10px;
}
.pp-table thead th {
  background: #f8fafc;
  font-weight: 700;
  color: #334155;
  font-size: 15px;
}
.pp-actions {
  display: inline-flex;
  gap: 6px;
}
.pp-icon-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  border: 1px solid rgba(45, 122, 136, 0.35);
  color: #2d7a88;
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.pp-icon-btn i {
  font-size: 16px;
}
.pp-icon-btn.danger {
  border-color: rgba(244, 63, 94, 0.4);
  color: #e11d48;
}
.pp-icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.pp-paid-pill {
  margin-left: 6px;
  font-size: 11px;
  border-radius: 999px;
  padding: 2px 6px;
  background: #dcfce7;
  color: #166534;
  font-weight: 700;
}
.pp-add-row {
  width: 100%;
  border: 0;
  border-top: 1px solid #d7e1e8;
  background: #f8fafc;
  color: #334155;
  font-size: 19px;
  font-weight: 700;
  padding: 9px 10px;
  text-align: left;
}
.pp-summary {
  margin: 14px 14px 0;
  border: 1px solid #d7e1e8;
  border-radius: 10px;
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  overflow: hidden;
}
.pp-summary > div {
  padding: 12px 10px;
  text-align: center;
}
.pp-summary > div + div {
  border-left: 1px solid #d7e1e8;
}
.pp-save-wrap {
  margin: 12px 14px 14px;
}
.pp-warn {
  border: 1px solid #fde68a;
  background: #fefce8;
  border-radius: 10px;
  color: #854d0e;
  padding: 8px 10px;
  text-align: center;
}
.pp-save-block {
  display: block;
  width: 100%;
}
.pp-save-btn {
  width: 100%;
  border: 0;
  border-radius: 10px;
  background: #2d7a88;
  color: #fff;
  font-size: 20px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 14px 16px;
  position: relative;
}
.pp-save-arrow {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 24px;
}
.pp-empty {
  text-align: center;
  color: #64748b;
  padding: 16px 8px;
}

@media (max-width: 992px) {
  .pp-price {
    font-size: 34px;
  }
  .pp-title,
  .pp-section-heading,
  .pp-h6 {
    font-size: 28px;
  }
  .pp-table {
    font-size: 15px;
  }
  .pp-step-date,
  .pp-step-name {
    font-size: 16px;
  }
}
</style>
