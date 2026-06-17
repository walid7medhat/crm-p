<template>
  <div class="roi-calc">
    <header class="roi-calc__nav">
      <div class="roi-calc__brand">
        <div class="roi-calc__brand-icon">
          <iconify-icon icon="lucide:percent" />
        </div>
        <div>
          <div class="roi-calc__logo">
            <span class="roi-calc__logo-acronym">ROI</span>
            <span class="roi-calc__logo-name">Calculator</span>
          </div>
          <div class="roi-calc__tag">Oia Properties · UAE Investment Analysis</div>
        </div>
      </div>
      <div class="roi-calc__nav-right">
        <b>Return on Investment Analysis</b>
        <span>UAE Ready Property · Cash / Mortgage</span>
        <div class="roi-calc__nav-pills">
          <span class="roi-calc__pill"><iconify-icon icon="lucide:building-2" /> Dubai / Abu Dhabi</span>
          <span class="roi-calc__pill"><iconify-icon icon="lucide:trending-up" /> 5% Rent Escalation</span>
        </div>
      </div>
    </header>

    <div class="roi-calc__wrap">
      <aside class="roi-calc__side">
        <div class="roi-calc__toggle">
          <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': form.paymentType === 'mortgage' }" @click="form.paymentType = 'mortgage'">
            <iconify-icon icon="lucide:landmark" class="roi-calc__tg-icon" />
            <div class="roi-calc__tg-name">Mortgage</div>
            <div class="roi-calc__tg-sub">Finance with a bank</div>
          </button>
          <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': form.paymentType === 'cash' }" @click="form.paymentType = 'cash'">
            <iconify-icon icon="lucide:banknote" class="roi-calc__tg-icon" />
            <div class="roi-calc__tg-name">Cash</div>
            <div class="roi-calc__tg-sub">Full cash purchase</div>
          </button>
        </div>

        <RoiSection title="Client &amp; Property" />
        <RoiField label="Client Name"><input v-model="form.clientName" type="text" placeholder="Client full name" /></RoiField>
        <RoiField label="Project"><input v-model="form.projectName" type="text" placeholder="Project / development" /></RoiField>
        <RoiField label="Unit Reference"><input v-model="form.unitRef" type="text" placeholder="e.g. 1BR · Tower A" /></RoiField>
        <RoiField label="Agent Name"><input v-model="form.agentName" type="text" placeholder="Your name" /></RoiField>
        <div class="roi-calc__fld">
          <div class="roi-calc__lbl">Currency</div>
          <select v-model="form.currency">
            <option>AED</option><option>USD</option><option>GBP</option><option>EUR</option>
          </select>
        </div>

        <RoiSection title="Purchase Price" />
        <RoiField label="Property Price" :prefix="form.currency"><input v-model="form.pp" type="number" min="0" placeholder="0" /></RoiField>

        <template v-if="form.paymentType === 'mortgage'">
          <RoiSection title="Mortgage Details" />
          <RoiField label="Down Payment %" suffix="%" hint="UAE minimum: 20% expats · 15% nationals (1st property)"><input v-model="form.downPct" type="number" min="0" max="100" /></RoiField>
          <RoiField label="Interest Rate" suffix="% p.a." hint="Ref: ADIB Home Finance rate"><input v-model="form.mortgageRate" type="number" step="0.01" /></RoiField>
          <RoiField label="Loan Term" suffix="yrs" hint="Max 25 yrs expats · 30 yrs nationals"><input v-model="form.mortgageTerm" type="number" min="1" max="30" /></RoiField>
        </template>

        <RoiSection title="Emirate" />
        <div class="roi-calc__toggle">
          <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': form.emirate === 'dubai' }" @click="form.emirate = 'dubai'">
            <iconify-icon icon="lucide:building-2" class="roi-calc__tg-icon" />
            <div class="roi-calc__tg-name">Dubai</div>
            <div class="roi-calc__tg-sub">4% transfer fee · DLD</div>
          </button>
          <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': form.emirate === 'abudhabi' }" @click="form.emirate = 'abudhabi'">
            <iconify-icon icon="lucide:landmark" class="roi-calc__tg-icon" />
            <div class="roi-calc__tg-name">Abu Dhabi</div>
            <div class="roi-calc__tg-sub">2% transfer fee · ADM</div>
          </button>
        </div>

        <RoiSection title="Acquisition Costs" />
        <RoiField label="Agency Commission" suffix="%" hint="Typically 2% in UAE"><input v-model="form.agencyPct" type="number" step="0.1" /></RoiField>
        <RoiField label="Other Costs" :prefix="form.currency" hint="NOC, conveyancing, admin"><input v-model="form.otherAcqCosts" type="number" min="0" /></RoiField>
        <div class="roi-calc__infobox">
          <b>Auto-included: </b>{{ em.transferFeeLabel }} {{ em.transferFeePct }}%
          <template v-if="form.paymentType === 'mortgage'"> · Mortgage Arrangement ~1% · {{ em.mortgageRegLabel }} {{ em.mortgageRegFee.toLocaleString() }}</template>
          <template v-else> · No mortgage fees</template>
        </div>

        <RoiSection title="Rental Income" />
        <RoiField label="Annual Gross Rent" :prefix="form.currency" :hint="`Ref: ${em.rentalAuth} · 5% annual escalation applied`"><input v-model="form.annualRent" type="number" min="0" /></RoiField>
        <RoiField label="Vacancy Rate" suffix="%" hint="Typically 5–10%"><input v-model="form.vacancyRate" type="number" min="0" max="100" /></RoiField>

        <RoiSection title="Annual Operating Expenses" />
        <RoiField label="Service Charge" :prefix="form.currency" :hint="`From ${em.svcAuth}`"><input v-model="form.serviceCharge" type="number" min="0" /></RoiField>
        <RoiField label="Property Mgmt Fee" suffix="% of net rent" hint="Max 5% — UAE market standard"><input v-model="form.mgmtPct" type="number" min="0" max="5" step="0.5" /></RoiField>
        <RoiField label="Maintenance &amp; Repairs" :prefix="form.currency"><input v-model="form.maintenance" type="number" min="0" /></RoiField>
        <RoiField label="Insurance" :prefix="form.currency"><input v-model="form.insurance" type="number" min="0" /></RoiField>

        <RoiSection title="Investment Horizon" />
        <div class="roi-calc__grid2">
          <RoiField label="Annual Appreciation" suffix="% p.a."><input v-model="form.appRate" type="number" step="0.1" /></RoiField>
          <RoiField label="Hold Period" suffix="yrs"><input v-model="form.holdYears" type="number" min="1" max="30" /></RoiField>
        </div>

        <div v-if="err" class="roi-calc__err">{{ err }}</div>
        <div class="roi-calc__actions">
          <button type="button" class="roi-calc__btn" @click="run">Generate Report</button>
          <button v-if="result" type="button" class="roi-calc__btn roi-calc__btn--ghost roi-calc__btn--gold" :disabled="exportingPdf" @click="downloadPdf">
            <iconify-icon :icon="exportingPdf ? 'lucide:loader-circle' : 'lucide:download'" :class="{ 'roi-calc__spin': exportingPdf }" style="margin-right:4px;vertical-align:-2px" />
            {{ exportingPdf ? 'Generating PDF…' : 'Download PDF Report' }}
          </button>
        </div>
        <p class="roi-calc__credit">Oia Properties · 27 years UAE real estate<br>oiaproperties.com · Abu Dhabi, UAE</p>
      </aside>

      <main class="roi-calc__panel">
        <div v-if="!result" class="roi-calc__empty">
          <div class="roi-calc__empty-icon-wrap">
            <iconify-icon icon="lucide:line-chart" class="roi-calc__empty-icon" />
          </div>
          <p>Fill in the property details on the left<br>and click <strong>Generate Report</strong></p>
        </div>

        <div v-else class="roi-calc__report">
          <header class="roi-calc__rhead">
            <div>
              <div class="roi-calc__eyebrow">ROI Analysis · {{ result.isCash ? 'Cash Purchase' : 'Mortgage Finance' }} · {{ result.em.label }}</div>
              <div class="roi-calc__client">{{ form.clientName || 'Client' }}</div>
              <p class="roi-calc__meta">{{ form.projectName }}<span v-if="form.unitRef"> · {{ form.unitRef }}</span></p>
              <p class="roi-calc__meta2">{{ fmt(result.pp, result.cur) }} · {{ form.agentName || 'Oia Properties' }} · {{ today }}</p>
            </div>
            <div class="roi-calc__rhead-icon-wrap">
              <iconify-icon icon="lucide:building-2" class="roi-calc__rhead-icon" />
            </div>
          </header>

          <div class="roi-calc__kpis">
            <div
              v-for="(k, i) in kpis"
              :key="i"
              class="roi-calc__kpi"
              :class="{ 'roi-calc__kpi--acc': k.accent }"
            >
              <div class="roi-calc__kpi-l">{{ k.l }}</div>
              <div class="roi-calc__kpi-v" :class="k.tone ? `roi-calc__kpi-v--${k.tone}` : ''">{{ k.v }}</div>
              <div v-if="k.s" class="roi-calc__kpi-s">{{ k.s }}</div>
            </div>
          </div>

          <section class="roi-calc__block">
            <div class="roi-calc__block-title">Acquisition Cost Breakdown</div>
            <div class="roi-calc__table-wrap">
              <table class="roi-calc__table">
                <thead><tr><th>Cost Item</th><th class="roi-calc__th-gold">Amount</th><th>Note</th></tr></thead>
                <tbody>
                  <tr v-for="(row, i) in acqRows" :key="i" :class="{ 'roi-calc__tr-total': row.total }">
                    <td :class="{ 'roi-calc__td-bold': row.bold || row.total }">{{ row.item }}</td>
                    <td :class="['roi-calc__td-gold', { 'roi-calc__td-bold': row.amountBold, 'roi-calc__td-lg': row.big }]">{{ row.amount }}</td>
                    <td class="roi-calc__td-muted">{{ row.note }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section v-if="!result.isCash" class="roi-calc__block">
            <div class="roi-calc__block-title">Mortgage Summary</div>
            <div class="roi-calc__mortgrid">
              <div v-for="(m, i) in mortItems" :key="i" class="roi-calc__mort-item">
                <div class="roi-calc__mort-l">{{ m[0] }}</div>
                <div class="roi-calc__mort-v">{{ m[1] }}</div>
              </div>
            </div>
          </section>

          <section class="roi-calc__block">
            <div class="roi-calc__block-title">Annual Income &amp; Expense Analysis</div>
            <div class="roi-calc__table-wrap">
              <table class="roi-calc__table">
                <thead><tr><th>Item</th><th class="roi-calc__th-green">Annual</th><th class="roi-calc__th-green">Monthly</th></tr></thead>
                <tbody>
                  <tr v-for="(row, i) in incomeRows" :key="i" :class="rowClass(row.bg)">
                    <td :class="{ 'roi-calc__td-bold': row.bold, 'roi-calc__td-gold': row.emphasis }">{{ row.label }}</td>
                    <td :class="[valClass(row.valTone), { 'roi-calc__td-bold': row.bold }]">{{ row.col1 }}</td>
                    <td :class="[valClass(row.col2Tone || row.valTone), { 'roi-calc__td-muted': !row.bold && !row.col2Tone }]">{{ row.col2 }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <div class="roi-calc__ratios">
            <div v-for="(rt, i) in ratios" :key="i" class="roi-calc__ratio">
              <div class="roi-calc__ratio-l">{{ rt.l }}</div>
              <div class="roi-calc__ratio-v" :class="`roi-calc__ratio-v--${rt.tone}`">{{ rt.v }}</div>
              <div class="roi-calc__ratio-h">{{ rt.h }}</div>
            </div>
          </div>

          <section class="roi-calc__block">
            <div class="roi-calc__block-title">{{ result.holdYrs }}-Year Investment Projection · {{ fmtp(result.appPct) }} p.a. appreciation · 5% annual rent escalation</div>
            <div class="roi-calc__table-wrap">
              <table class="roi-calc__table roi-calc__table--compact">
                <thead>
                  <tr>
                    <th>Year</th>
                    <th class="roi-calc__th-green">Property Value</th>
                    <th class="roi-calc__th-green">Capital Gain</th>
                    <template v-if="!result.isCash">
                      <th>Loan Balance</th>
                      <th class="roi-calc__th-blue">Net Equity</th>
                    </template>
                    <th class="roi-calc__th-blue">Annual Rent</th>
                    <th class="roi-calc__th-blue">NOI</th>
                    <th class="roi-calc__th-blue">Cumul. NOI</th>
                    <th class="roi-calc__th-green">ROE Capital</th>
                    <th class="roi-calc__th-gold">Total ROI</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in result.yearRows" :key="row.yr" :class="{ 'roi-calc__tr-total': row.isLast }">
                    <td :class="{ 'roi-calc__td-bold': row.isLast, 'roi-calc__td-gold': row.isLast }">Year {{ row.yr }}</td>
                    <td class="roi-calc__td-green roi-calc__td-bold">{{ fmt(row.val, result.cur) }}</td>
                    <td class="roi-calc__td-green">+{{ fmt(row.capitalGain, result.cur) }}</td>
                    <template v-if="!result.isCash">
                      <td class="roi-calc__td-muted">{{ fmt(row.balance, result.cur) }}</td>
                      <td class="roi-calc__td-blue roi-calc__td-bold">{{ fmt(row.equity, result.cur) }}</td>
                    </template>
                    <td class="roi-calc__td-blue">{{ fmt(row.yGross, result.cur) }}</td>
                    <td :class="row.yNOI >= 0 ? 'roi-calc__td-green' : 'roi-calc__td-red'">{{ fmt(row.yNOI, result.cur) }}</td>
                    <td class="roi-calc__td-blue roi-calc__td-bold">{{ fmt(row.cum, result.cur) }}</td>
                    <td class="roi-calc__td-green roi-calc__td-bold">{{ fmtp(row.roeCapital) }}</td>
                    <td :class="['roi-calc__td-bold', row.isLast ? 'roi-calc__td-gold roi-calc__td-lg' : 'roi-calc__td-muted']">{{ fmtp(row.totalROI) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <p class="roi-calc__disc">
            <b>Disclaimer:</b> Prepared by Oia Properties for illustrative purposes only. All figures are estimates — not guaranteed. Transfer fees, agency commissions, mortgage fees, and service charges are indicative. Figures exclude VAT. Not financial, legal, or investment advice.
          </p>
        </div>
      </main>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="exportingPdf" class="roi-pdf-capture-wrap" aria-hidden="true">
      <div ref="pdfExportRef" class="roi-pdf-capture" />
    </div>
  </Teleport>
</template>

<script setup>
import { onMounted, ref, nextTick } from 'vue'
import RoiSection from './RoiSection.vue'
import RoiField from './RoiField.vue'
import { useRoiCalculations } from './useRoiCalculations.js'
import { downloadRoiPdf } from './roiPdfExport.js'

const {
  form, result, err, em, today,
  kpis, acqRows, mortItems, incomeRows, ratios,
  rowClass, valClass,
  run, hydrateAgentName, fmt, fmtp,
} = useRoiCalculations()

onMounted(() => hydrateAgentName())

const exportingPdf = ref(false)
const pdfExportRef = ref(null)

async function downloadPdf() {
  if (!result.value || exportingPdf.value) return
  exportingPdf.value = true
  try {
    await nextTick()
    const ok = await downloadRoiPdf(result.value, today.value, pdfExportRef.value)
    if (!ok) alert('Could not download PDF. Please try again.')
  } finally {
    exportingPdf.value = false
    if (pdfExportRef.value) pdfExportRef.value.innerHTML = ''
  }
}
</script>
