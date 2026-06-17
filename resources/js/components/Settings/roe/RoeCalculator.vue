<template>
  <div class="roi-calc">
    <header class="roi-calc__nav">
      <div class="roi-calc__brand">
        <div class="roi-calc__brand-icon">
          <iconify-icon icon="lucide:trending-up" />
        </div>
        <div>
          <div class="roi-calc__logo">
            <span class="roi-calc__logo-acronym">ROE</span>
            <span class="roi-calc__logo-name">Calculator</span>
          </div>
          <div class="roi-calc__tag">Oia Properties · Return on Equity Analysis</div>
        </div>
      </div>
      <div class="roi-calc__nav-right">
        <b>Property Investment Analysis</b>
        <span>Off-Plan · Ready (Cash / Mortgage)</span>
        <div class="roi-calc__nav-pills">
          <span class="roi-calc__pill"><iconify-icon icon="lucide:hard-hat" /> Off-Plan</span>
          <span class="roi-calc__pill"><iconify-icon icon="lucide:home" /> Ready Property</span>
        </div>
      </div>
    </header>

    <div class="roi-calc__wrap">
      <aside class="roi-calc__side">
        <div class="roi-calc__toggle">
          <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': mode === 'offplan' }" @click="setMode('offplan')">
            <iconify-icon icon="lucide:hard-hat" class="roi-calc__tg-icon" />
            <div class="roi-calc__tg-name">Off-Plan</div>
            <div class="roi-calc__tg-sub">Under construction</div>
          </button>
          <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': mode === 'ready' }" @click="setMode('ready')">
            <iconify-icon icon="lucide:home" class="roi-calc__tg-icon" />
            <div class="roi-calc__tg-name">Ready</div>
            <div class="roi-calc__tg-sub">Cash or mortgage</div>
          </button>
        </div>

        <RoiSection title="Client &amp; Property" />
        <RoiField label="Client Name"><input v-model="form.clientName" type="text" placeholder="Client full name" /></RoiField>
        <RoiField label="Project"><input v-model="form.projectName" type="text" placeholder="e.g. Yas Living by Aldar" /></RoiField>
        <RoiField label="Unit Reference"><input v-model="form.unitRef" type="text" placeholder="e.g. 1BR · Tower B" /></RoiField>
        <RoiField label="Agent Name"><input v-model="form.agentName" type="text" placeholder="Your name" /></RoiField>
        <div class="roi-calc__fld">
          <div class="roi-calc__lbl">Currency</div>
          <select v-model="form.currency"><option>AED</option><option>USD</option><option>GBP</option><option>EUR</option></select>
        </div>

        <RoiSection title="Purchase Price" />
        <RoiField label="Property Price" :prefix="form.currency"><input v-model="form.pp" type="number" min="0" placeholder="0" /></RoiField>

        <template v-if="mode === 'offplan'">
          <RoiSection title="Payment Plan" />
          <div class="roi-calc__grid2">
            <RoiField label="Down Payment %" suffix="%"><input v-model="form.downPct" type="number" min="0" max="100" /></RoiField>
            <RoiField label="Signing Date"><input v-model="form.signingDate" type="date" /></RoiField>
            <RoiField label="Handover %" suffix="%"><input v-model="form.handoverPct" type="number" min="0" max="100" /></RoiField>
            <RoiField label="Handover Date"><input v-model="form.handoverDate" type="date" /></RoiField>
          </div>
          <div class="roi-calc__alloc" :class="{ 'roi-calc__alloc--ok': allocOk, 'roi-calc__alloc--warn': !allocOk }">
            <span>Total allocated</span>
            <strong>{{ fmtp(allocTotal) }} / 100%</strong>
          </div>

          <RoiSection title="Construction Instalments" />
          <div v-for="(inst, idx) in insts" :key="inst.id" class="roi-calc__inst">
            <div class="roi-calc__inst-head">
              <div class="roi-calc__inst-title">
                <span class="roi-calc__inst-num">{{ idx + 1 }}</span>
                <input v-model="inst.label" class="roi-calc__inst-label" type="text" />
              </div>
              <button type="button" class="roi-calc__inst-del" title="Remove" @click="removeInst(inst.id)">×</button>
            </div>
            <div class="roi-calc__grid2">
              <div>
                <div class="roi-calc__mini-lbl">% of Price</div>
                <div class="roi-calc__ig">
                  <input v-model="inst.pct" type="number" placeholder="5" />
                  <span class="roi-calc__suf">%</span>
                </div>
                <div v-if="inst.pct && num(form.pp) > 0" class="roi-calc__inst-amt">{{ fmt(num(form.pp) * num(inst.pct) / 100, form.currency) }}</div>
              </div>
              <div>
                <div class="roi-calc__mini-lbl">Due Date</div>
                <input v-model="inst.date" type="date" class="roi-calc__date-input" />
              </div>
            </div>
          </div>
          <button type="button" class="roi-calc__add-btn" @click="addInst">+ Add Instalment</button>

          <RoiSection title="Appreciation Assumptions" />
          <RoiField label="Scenario A — Pre-Exit Appreciation" hint="Total % when client exits before handover" suffix="%"><input v-model="form.appA" type="number" step="0.01" /></RoiField>
          <RoiField label="Scenario B — Handover Appreciation" hint="Total % if client holds to completion" suffix="%"><input v-model="form.appB" type="number" step="0.01" /></RoiField>
        </template>

        <template v-if="mode === 'ready'">
          <RoiSection title="Payment Method" />
          <div class="roi-calc__toggle">
            <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': paymentType === 'mortgage' }" @click="setPay('mortgage')">
              <iconify-icon icon="lucide:landmark" class="roi-calc__tg-icon" />
              <div class="roi-calc__tg-name">Mortgage</div>
            </button>
            <button type="button" class="roi-calc__tg" :class="{ 'roi-calc__tg--on': paymentType === 'cash' }" @click="setPay('cash')">
              <iconify-icon icon="lucide:banknote" class="roi-calc__tg-icon" />
              <div class="roi-calc__tg-name">Cash</div>
            </button>
          </div>
          <div v-if="paymentType === 'mortgage'" class="roi-calc__grid2">
            <RoiField label="Down Payment %" suffix="%"><input v-model="form.downPctR" type="number" /></RoiField>
            <RoiField label="Interest Rate" suffix="% p.a."><input v-model="form.mortgageRate" type="number" step="0.01" /></RoiField>
            <RoiField label="Loan Term" suffix="yrs"><input v-model="form.mortgageTerm" type="number" /></RoiField>
          </div>

          <RoiSection title="Investment Projections" />
          <div class="roi-calc__grid2">
            <RoiField label="Annual Appreciation" suffix="% p.a."><input v-model="form.appAReady" type="number" step="0.1" /></RoiField>
            <RoiField label="Hold Period" suffix="yrs"><input v-model="form.holdYears" type="number" min="1" max="30" /></RoiField>
          </div>
          <RoiField label="Rental Yield" suffix="% gross"><input v-model="form.rentalYield" type="number" step="0.1" /></RoiField>
          <RoiField label="Annual Service Charge" :prefix="form.currency"><input v-model="form.serviceCharge" type="number" min="0" /></RoiField>
        </template>

        <div v-if="err" class="roi-calc__err">{{ err }}</div>
        <div class="roi-calc__actions">
          <button type="button" class="roi-calc__btn" @click="run">Generate Report</button>
          <button v-if="result" type="button" class="roi-calc__btn roi-calc__btn--ghost roi-calc__btn--gold" :disabled="exportingPdf" @click="downloadPdf">
            <iconify-icon :icon="exportingPdf ? 'lucide:loader-circle' : 'lucide:download'" :class="{ 'roi-calc__spin': exportingPdf }" />
            {{ exportingPdf ? 'Generating PDF…' : 'Download PDF Report' }}
          </button>
        </div>
        <p class="roi-calc__credit">Oia Properties · 27 years UAE real estate<br>oiaproperties.com · Abu Dhabi, UAE</p>
      </aside>

      <main class="roi-calc__panel">
        <div v-if="!result" class="roi-calc__empty">
          <div class="roi-calc__empty-icon-wrap">
            <iconify-icon icon="lucide:percent" class="roi-calc__empty-icon" />
          </div>
          <p>Select a mode, fill in the details<br>and click <strong>Generate Report</strong></p>
        </div>

        <div v-else class="roi-calc__report">
          <header class="roi-calc__rhead">
            <div>
              <div class="roi-calc__eyebrow">{{ result.mode === 'offplan' ? 'Off-Plan Investment Analysis' : `Ready Property · ${result.isCash ? 'Cash Purchase' : 'Mortgage Finance'}` }}</div>
              <div class="roi-calc__client">{{ form.clientName || 'Client' }}</div>
              <p class="roi-calc__meta">{{ form.projectName }}<span v-if="form.unitRef"> · {{ form.unitRef }}</span></p>
              <p class="roi-calc__meta2">{{ fmt(result.pp, result.cur) }} · {{ form.agentName || 'Oia Properties' }} · {{ today }}</p>
            </div>
            <div class="roi-calc__rhead-icon-wrap">
              <iconify-icon icon="lucide:building-2" class="roi-calc__rhead-icon" />
            </div>
          </header>

          <!-- Off-plan report -->
          <template v-if="result.mode === 'offplan'">
            <div class="roi-calc__kpis">
              <div v-for="(k, i) in offplanKpis" :key="i" class="roi-calc__kpi" :class="{ 'roi-calc__kpi--acc': k.accent }">
                <div class="roi-calc__kpi-l">{{ k.l }}</div>
                <div class="roi-calc__kpi-v" :class="k.tone ? `roi-calc__kpi-v--${k.tone}` : ''">{{ k.v }}</div>
                <div v-if="k.s" class="roi-calc__kpi-s">{{ k.s }}</div>
              </div>
            </div>

            <section class="roi-calc__block">
              <div class="roi-calc__block-title">Payment Schedule</div>
              <div class="roi-calc__table-wrap">
                <table class="roi-calc__table">
                  <thead>
                    <tr>
                      <th>Payment</th><th>Date</th><th>%</th><th>Amount</th><th>Cumul. Paid</th>
                      <th class="roi-calc__th-green">Scen A</th><th class="roi-calc__th-blue">Scen B</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="roi-calc__td-bold">Down Payment</td>
                      <td class="roi-calc__td-muted">{{ fmtDt(form.signingDate) }}</td>
                      <td>{{ fmtp(result.dpPct) }}</td>
                      <td class="roi-calc__td-bold">{{ fmt(result.down, result.cur) }}</td>
                      <td class="roi-calc__td-muted">{{ fmt(result.down, result.cur) }}</td>
                      <td class="roi-calc__td-muted">PAID</td>
                      <td class="roi-calc__td-muted">PAID</td>
                    </tr>
                    <tr v-for="row in result.rows" :key="row.label" :class="{ 'roi-calc__tr-total': row.isLast }">
                      <td :class="{ 'roi-calc__td-bold': row.isLast, 'roi-calc__td-green': row.isLast }">{{ row.label }}<span v-if="row.isLast"> ← Exit A</span></td>
                      <td class="roi-calc__td-gold">{{ fmtDt(row.date) }}</td>
                      <td class="roi-calc__td-bold">{{ fmtp(row.pct) }}</td>
                      <td class="roi-calc__td-bold">{{ fmt(row.amount, result.cur) }}</td>
                      <td class="roi-calc__td-muted">{{ fmt(row.cum, result.cur) }}</td>
                      <td :class="row.isLast ? 'roi-calc__td-green roi-calc__td-bold' : 'roi-calc__td-muted'">{{ row.isLast ? 'PAID · EXITS ✓' : 'PAID' }}</td>
                      <td class="roi-calc__td-muted">PAID</td>
                    </tr>
                    <tr class="roi-calc__row--red">
                      <td class="roi-calc__td-bold roi-calc__td-red">Handover Payment</td>
                      <td class="roi-calc__td-muted">{{ fmtDt(form.handoverDate) }}</td>
                      <td class="roi-calc__td-red">{{ fmtp(result.hoPct) }}</td>
                      <td class="roi-calc__td-bold roi-calc__td-red">{{ fmt(result.handoverDue, result.cur) }}</td>
                      <td class="roi-calc__td-muted">{{ fmt(result.pp, result.cur) }}</td>
                      <td class="roi-calc__td-bold roi-calc__td-red">NOT PAID ✗</td>
                      <td class="roi-calc__td-bold roi-calc__td-blue">PAID ✓</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <div class="roi-calc__scenarios">
              <div v-if="scenarioA" class="roi-calc__scenario roi-calc__scenario--green">
                <div class="roi-calc__scenario-head">
                  <div class="roi-calc__scenario-title">{{ scenarioA.title }}</div>
                  <div class="roi-calc__scenario-sub">{{ scenarioA.sub }}</div>
                </div>
                <div class="roi-calc__scenario-body">
                  <div v-for="(it, i) in scenarioA.items" :key="i" class="roi-calc__scenario-row" :class="{ 'roi-calc__scenario-row--bold': it[2] }">
                    <span>{{ it[0] }}</span><strong>{{ it[1] }}</strong>
                  </div>
                </div>
              </div>
              <div v-if="scenarioB" class="roi-calc__scenario roi-calc__scenario--blue">
                <div class="roi-calc__scenario-head">
                  <div class="roi-calc__scenario-title">{{ scenarioB.title }}</div>
                  <div class="roi-calc__scenario-sub">{{ scenarioB.sub }}</div>
                </div>
                <div class="roi-calc__scenario-body">
                  <div v-for="(it, i) in scenarioB.items" :key="i" class="roi-calc__scenario-row" :class="{ 'roi-calc__scenario-row--bold': it[2] }">
                    <span>{{ it[0] }}</span><strong>{{ it[1] }}</strong>
                  </div>
                </div>
              </div>
            </div>

            <section class="roi-calc__block">
              <div class="roi-calc__block-title">Capital Appreciation Per Instalment</div>
              <div class="roi-calc__table-wrap">
                <table class="roi-calc__table roi-calc__table--compact">
                  <thead>
                    <tr>
                      <th>Instalment</th><th>Date</th><th>%</th><th>Cumul. Paid</th>
                      <th class="roi-calc__th-green">Value (A)</th><th class="roi-calc__th-green">Gain (A)</th><th class="roi-calc__th-green">ROE (A)</th>
                      <th class="roi-calc__th-blue">Value (B)</th><th class="roi-calc__th-blue">Gain (B)</th><th class="roi-calc__th-blue">ROE (B)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in result.rows" :key="'cap-' + row.label" :class="{ 'roi-calc__tr-total': row.isLast }">
                      <td :class="{ 'roi-calc__td-bold': row.isLast, 'roi-calc__td-green': row.isLast }">{{ row.label }}<span v-if="row.isLast"> ★</span></td>
                      <td class="roi-calc__td-gold">{{ fmtDt(row.date) }}</td>
                      <td class="roi-calc__td-bold">{{ fmtp(row.pct) }}</td>
                      <td class="roi-calc__td-muted">{{ fmt(row.cum, result.cur) }}</td>
                      <td class="roi-calc__td-green roi-calc__td-bold">{{ fmt(row.vA, result.cur) }}</td>
                      <td class="roi-calc__td-green">+{{ fmt(row.ygA, result.cur) }}</td>
                      <td class="roi-calc__td-green roi-calc__td-bold">{{ fmtp(row.roeA) }}</td>
                      <td class="roi-calc__td-blue roi-calc__td-bold">{{ fmt(row.vB, result.cur) }}</td>
                      <td class="roi-calc__td-blue">+{{ fmt(row.ygB, result.cur) }}</td>
                      <td class="roi-calc__td-blue roi-calc__td-bold">{{ fmtp(row.roeB) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </template>

          <!-- Ready report -->
          <template v-if="result.mode === 'ready'">
            <div class="roi-calc__kpis">
              <div v-for="(k, i) in readyKpis" :key="i" class="roi-calc__kpi" :class="{ 'roi-calc__kpi--acc': k.accent }">
                <div class="roi-calc__kpi-l">{{ k.l }}</div>
                <div class="roi-calc__kpi-v" :class="k.tone ? `roi-calc__kpi-v--${k.tone}` : ''">{{ k.v }}</div>
                <div v-if="k.s" class="roi-calc__kpi-s">{{ k.s }}</div>
              </div>
            </div>

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
              <div class="roi-calc__block-title">{{ result.holdYrs }}-Year Investment Projection</div>
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
                      <th>Cash Invested</th>
                      <th class="roi-calc__th-green">ROE (Capital)</th>
                      <th class="roi-calc__th-blue">Cumul. Net Rental</th>
                      <th class="roi-calc__th-gold">Total ROE</th>
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
                      <td class="roi-calc__td-muted">{{ fmt(row.cashIn, result.cur) }}</td>
                      <td class="roi-calc__td-green roi-calc__td-bold">{{ fmtp(row.roeCapital) }}</td>
                      <td class="roi-calc__td-blue">{{ fmt(row.cumRental, result.cur) }}</td>
                      <td :class="['roi-calc__td-bold', row.isLast ? 'roi-calc__td-gold' : 'roi-calc__td-muted']">{{ fmtp(row.totalRoe) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </template>

          <p class="roi-calc__disc">
            <b>Disclaimer:</b> Prepared by Oia Properties for illustrative purposes only. All figures are estimates — not guaranteed. DLD fees (4%), agency commissions, mortgage fees, and service charges excluded. Not financial or investment advice.
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
import RoiSection from '../roi/RoiSection.vue'
import RoiField from '../roi/RoiField.vue'
import { useRoeCalculations, fmt, fmtp, num } from './useRoeCalculations.js'
import { downloadRoePdf } from './roePdfExport.js'

const {
  mode, paymentType, form, insts, result, err, allocTotal, allocOk, today,
  offplanKpis, readyKpis, scenarioA, scenarioB, mortItems,
  hydrateAgentName, addInst, removeInst, setMode, setPay, run, fmtDt,
} = useRoeCalculations()

onMounted(() => hydrateAgentName())

const exportingPdf = ref(false)
const pdfExportRef = ref(null)

async function downloadPdf() {
  if (!result.value || exportingPdf.value) return
  exportingPdf.value = true
  try {
    await nextTick()
    const ok = await downloadRoePdf(result.value, today.value, pdfExportRef.value)
    if (!ok) alert('Could not download PDF. Please try again.')
  } finally {
    exportingPdf.value = false
    if (pdfExportRef.value) pdfExportRef.value.innerHTML = ''
  }
}
</script>
