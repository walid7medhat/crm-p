<template>
  <Teleport to="body">
    <div v-if="dealId" class="ofb-overlay" @mousedown.self="$emit('close')">
      <div class="ofb-modal" role="dialog" aria-modal="true" aria-labelledby="ofb-title">
        <header class="ofb-head">
          <h2 id="ofb-title" class="ofb-title">
            Deal commissions breakdown<span v-if="deal">: {{ deal.deal_number || `#${deal.id}` }}</span>
          </h2>
          <button type="button" class="ofb-close" aria-label="Close" @click="$emit('close')">
            <iconify-icon icon="lucide:x" width="18" height="18" />
          </button>
        </header>

        <div class="ofb-body">
          <div v-if="loading" class="ofb-state">
            <span class="ofb-spinner" /> Loading breakdown…
          </div>
          <div v-else-if="error" class="ofb-error">
            <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
            {{ error }}
          </div>

          <template v-else-if="deal">
            <!-- Donut + legend -->
            <section v-if="chart" class="ofb-chart">
              <svg viewBox="0 0 160 160" class="ofb-donut" role="img" :aria-label="`Gross commission ${aed(deal.gross_commission)}`">
                <g transform="rotate(-90 80 80)">
                  <circle cx="80" cy="80" :r="R" class="ofb-donut-track" />
                  <circle
                    v-for="seg in chart.segments"
                    :key="seg.key"
                    cx="80" cy="80" :r="R"
                    class="ofb-donut-seg"
                    :style="{ stroke: seg.color }"
                    :stroke-dasharray="`${seg.len} ${C - seg.len}`"
                    :stroke-dashoffset="-seg.offset"
                  />
                </g>
                <text x="80" y="76" text-anchor="middle" class="ofb-donut-cap">Gross</text>
                <text x="80" y="94" text-anchor="middle" class="ofb-donut-val">{{ compact(deal.gross_commission) }}</text>
              </svg>
              <ul class="ofb-legend">
                <li v-for="seg in chart.segments" :key="seg.key" :class="{ 'is-main': seg.key === 'net' }">
                  <span class="ofb-swatch" :style="{ background: seg.color }" />
                  <span class="ofb-legend-label">{{ seg.label }}</span>
                  <span class="ofb-legend-pct">{{ seg.pct }}%</span>
                  <span class="ofb-legend-val">{{ aed(seg.value) }}</span>
                </li>
              </ul>
            </section>

            <!-- Missing data -->
            <div v-if="deal.issues.length" class="ofb-alert">
              <div class="ofb-alert-title">
                <iconify-icon icon="lucide:triangle-alert" width="16" height="16" />
                Incomplete financial data: agent, external and net figures for this deal are not included in the report totals.
              </div>
              <ul>
                <li v-for="issue in deal.issues" :key="issue.code">{{ issue.label }}</li>
              </ul>
            </div>

            <!-- Commission calculation -->
            <section class="ofb-section">
              <h3 class="ofb-h3">Commission calculation</h3>
              <table class="ofb-calc">
                <tbody>
                  <tr>
                    <td>Deal amount</td>
                    <td class="ofb-num" :class="{ 'ofb-missing': deal.deal_amount === null }">
                      {{ deal.deal_amount === null ? 'Missing' : aed(deal.deal_amount) }}
                    </td>
                  </tr>
                  <tr>
                    <td>× Commission rate</td>
                    <td class="ofb-num" :class="{ 'ofb-missing': deal.commission_rate === null }">
                      {{ deal.commission_rate === null ? 'Missing' : `${deal.commission_rate}%` }}
                    </td>
                  </tr>
                  <tr class="ofb-calc-total">
                    <td>Gross commission</td>
                    <td class="ofb-num">{{ deal.gross_commission === null ? 'Cannot calculate' : aed(deal.gross_commission) }}</td>
                  </tr>

                  <template v-if="b">
                    <tr v-if="b.external_agency.enabled">
                      <td>
                        − External agency
                        <span class="ofb-sub">
                          {{ b.external_agency.name || 'Name missing' }} · {{ b.external_agency.split_percent }}% of gross
                          <template v-if="b.external_agency.representing"> · representing {{ b.external_agency.representing }}</template>
                        </span>
                      </td>
                      <td class="ofb-num">{{ aed(b.external_agency.amount) }}</td>
                    </tr>
                    <tr v-else>
                      <td>− External agency <span class="ofb-sub">No external agency on this deal</span></td>
                      <td class="ofb-num ofb-muted">{{ aed(0) }}</td>
                    </tr>

                    <tr v-for="(agent, i) in b.agents" :key="'a' + i">
                      <td>
                        − Agent: {{ agent.name || 'No agent selected' }}
                        <span class="ofb-sub">{{ agent.split_percent }}% of commission after the agency</span>
                      </td>
                      <td class="ofb-num">{{ aed(agent.amount) }}</td>
                    </tr>
                    <tr v-if="!b.agents.length">
                      <td>− Agents <span class="ofb-sub ofb-missing">No agent allocation recorded</span></td>
                      <td class="ofb-num ofb-muted">—</td>
                    </tr>
                  </template>
                  <template v-else-if="deal.gross_commission > 0">
                    <tr>
                      <td>− External agency <span class="ofb-sub">No commission breakdown saved on the deal, so no external agency</span></td>
                      <td class="ofb-num ofb-muted">{{ aed(0) }}</td>
                    </tr>
                    <tr>
                      <td>
                        − Agent: {{ deal.agent?.name || 'No responsible agent' }}
                        <span class="ofb-sub" :class="{ 'ofb-missing': deal.agent_rate === null }">
                          {{ deal.agent_rate === null ? 'No commission % on the agent\'s profile' : `${deal.agent_rate}% of gross, from the agent's user profile` }}
                        </span>
                      </td>
                      <td class="ofb-num">{{ deal.agent_commission === null ? '—' : aed(deal.agent_commission) }}</td>
                    </tr>
                  </template>

                  <tr class="ofb-calc-net">
                    <td>Net company revenue</td>
                    <td class="ofb-num">{{ deal.net_revenue === null ? 'Incomplete' : aed(deal.net_revenue) }}</td>
                  </tr>
                </tbody>
              </table>

              <p v-if="!deal.is_incomplete && deal.gross_commission > 0" class="ofb-note">
                Split source:
                <strong>{{ deal.commission_source === 'breakdown' ? 'commission breakdown saved on the deal' : 'agent commission % from the user profile (same as the profile’s Sales Performance)' }}</strong>
              </p>
              <p v-if="b && deal.issues.some((i) => i.code === 'stale_breakdown')" class="ofb-note">
                The saved breakdown was calculated on a gross of {{ aed(b.recorded_gross) }}; the deal's current amount × rate gives
                {{ aed(deal.gross_commission) }}. Re-save the commission distribution on the deal to update it.
              </p>
            </section>

            <!-- Deal information -->
            <section class="ofb-section">
              <h3 class="ofb-h3">Deal information</h3>
              <dl class="ofb-grid">
                <div><dt>Deal number</dt><dd>{{ deal.deal_number || '—' }}</dd></div>
                <div>
                  <dt>Lead name</dt>
                  <dd>
                    <template v-if="deal.lead_id && deal.lead_archived">
                      {{ deal.lead_name || `Lead #${deal.lead_id}` }}
                      <span class="ofb-sub">Archived lead; can't be opened</span>
                    </template>
                    <button
                      v-else-if="deal.lead_id"
                      type="button"
                      class="ofb-lead-link"
                      @click="$emit('open-lead', deal.lead_id)"
                    >{{ deal.lead_name || `Lead #${deal.lead_id}` }}</button>
                    <span v-else class="ofb-muted">No lead (deal created directly)</span>
                  </dd>
                </div>
                <div><dt>Property</dt><dd>{{ deal.property || '—' }}</dd></div>
                <div>
                  <dt>Client</dt>
                  <dd>
                    {{ deal.client?.name || '—' }}
                    <span v-if="deal.client?.phone" class="ofb-sub">{{ deal.client.phone }}</span>
                  </dd>
                </div>
                <div><dt>Agent</dt><dd>{{ deal.agent?.name || '—' }}</dd></div>
                <div><dt>Branch</dt><dd>{{ deal.branch || '—' }}</dd></div>
                <div><dt>Lead source</dt><dd>{{ deal.source || '—' }}</dd></div>
                <div><dt>Deal type</dt><dd>{{ deal.deal_type_label || '—' }}</dd></div>
                <div>
                  <dt>Deal date</dt>
                  <dd>
                    {{ formatDate(deal.deal_date) }}
                    <span v-if="!deal.has_won_date" class="ofb-sub">Won date not recorded; showing the created date</span>
                  </dd>
                </div>
                <div>
                  <dt>Deal amount</dt>
                  <dd :class="{ 'ofb-missing': deal.deal_amount === null }">{{ deal.deal_amount === null ? 'Missing' : aed(deal.deal_amount) }}</dd>
                </div>
                <div>
                  <dt>Deal status</dt>
                  <dd>
                    <span class="ofb-stage">
                      <span class="ofb-dot" :style="{ background: deal.stage?.color || '#ccc' }" />
                      {{ deal.stage?.name || '—' }}
                    </span>
                    <span class="ofb-sub">{{ deal.is_closed ? 'Closed' : 'Open' }}</span>
                  </dd>
                </div>
              </dl>

              <div v-if="deal.properties?.length > 1" class="ofb-props">
                <span class="ofb-props-label">{{ deal.properties.length }} properties on this deal</span>
                <ul>
                  <li v-for="(p, i) in deal.properties" :key="i">
                    <span>{{ p.label }}</span>
                    <span class="ofb-num">{{ p.price === null ? '—' : aed(p.price) }}</span>
                  </li>
                </ul>
              </div>
            </section>
          </template>
        </div>

        <footer v-if="deal" class="ofb-foot">
          <button
            v-if="deal.lead_id && !deal.lead_archived"
            type="button"
            class="ofb-btn ofb-btn--ghost"
            @click="$emit('open-lead', deal.lead_id)"
          >
            <iconify-icon icon="lucide:user-round" width="15" height="15" />
            Open lead
          </button>
          <button
            type="button"
            class="ofb-btn ofb-btn--primary"
            @click="$emit('open-deal', { id: deal.id, deal_type: deal.deal_type })"
          >
            <iconify-icon icon="lucide:external-link" width="15" height="15" />
            Open deal
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import api from '@/plugins/axios'
import { aed, compact, formatDate } from './format.js'

const props = defineProps({
  dealId: { type: Number, default: null },
})
const emit = defineEmits(['close', 'open-deal', 'open-lead'])

const R = 58
const C = 2 * Math.PI * R
// Theme purples: primary = company, mid = agents, light = external.
const COLORS = { net: 'var(--color-primary)', agents: '#a46cba', external: '#dcc6e5' }

const deal = ref(null)
const loading = ref(false)
const error = ref('')
const b = computed(() => deal.value?.breakdown || null)

/** Donut only for complete deals with a gross > 0 — the split is then exact. */
const chart = computed(() => {
  const d = deal.value
  if (!d || d.is_incomplete || !(d.gross_commission > 0)) return null
  const gross = d.gross_commission
  const parts = [
    { key: 'net', label: 'Net company revenue', value: d.net_revenue || 0 },
    { key: 'agents', label: 'Agent commission', value: d.agent_commission || 0 },
    { key: 'external', label: 'External commission', value: d.external_commission || 0 },
  ]
  let offset = 0
  const segments = parts.map((p) => {
    const len = Math.max(0, (p.value / gross) * C)
    const seg = { ...p, color: COLORS[p.key], len, offset, pct: Math.round((p.value / gross) * 1000) / 10 }
    offset += len
    return seg
  })
  return { segments }
})

let seq = 0
watch(
  () => props.dealId,
  async (id) => {
    deal.value = null
    error.value = ''
    if (!id) return
    const mine = ++seq
    loading.value = true
    try {
      const res = await api.get(`/reports/owner-financial/deals/${id}`)
      if (mine === seq) deal.value = res.data?.data || null
    } catch (e) {
      if (mine === seq) error.value = e?.response?.data?.message || 'Failed to load the breakdown'
    } finally {
      if (mine === seq) loading.value = false
    }
  },
  { immediate: true },
)

const onKey = (e) => { if (e.key === 'Escape' && props.dealId) emit('close') }
window.addEventListener('keydown', onKey)
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<style scoped>
.ofb-overlay { position: fixed !important; inset: 0 !important; background: rgba(var(--color-primary-active-rgb, 74, 40, 92), 0.28) !important; backdrop-filter: blur(2px) !important; display: flex !important; align-items: flex-start !important; justify-content: center !important; padding: 40px 16px !important; z-index: 2000 !important; overflow-y: auto !important; }
.ofb-modal { width: 100% !important; max-width: 640px !important; background: #fff !important; border-radius: 16px !important; border: 1px solid var(--color-primary-border) !important; box-shadow: 0 24px 60px rgba(var(--color-primary-rgb), 0.22) !important; color: var(--color-text-primary, #1e1b2e) !important; display: flex !important; flex-direction: column !important; max-height: calc(100vh - 80px) !important; }
.ofb-head { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 12px !important; padding: 16px 20px !important; border-bottom: 1px solid var(--color-primary-border) !important; }
.ofb-title { margin: 0 !important; font-size: 14px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: .04em !important; color: var(--color-primary-ink) !important; line-height: 1.3 !important; }
.ofb-close { width: 32px !important; height: 32px !important; flex-shrink: 0 !important; border-radius: 8px !important; border: 1px solid var(--color-primary-border) !important; background: #fff !important; color: var(--color-primary-ink) !important; display: grid !important; place-items: center !important; cursor: pointer !important; }
.ofb-close:hover { background: var(--color-primary-soft) !important; }
.ofb-body { padding: 18px 20px !important; display: flex !important; flex-direction: column !important; gap: 18px !important; overflow-y: auto !important; }
.ofb-foot { display: flex !important; justify-content: flex-end !important; gap: 8px !important; padding: 12px 20px !important; border-top: 1px solid var(--color-primary-border) !important; background: var(--color-primary-soft) !important; border-radius: 0 0 16px 16px !important; }
.ofb-state { display: flex !important; align-items: center !important; justify-content: center !important; gap: 10px !important; padding: 36px !important; color: var(--color-text-secondary, #6b6678) !important; font-size: 13px !important; }
.ofb-spinner { width: 16px !important; height: 16px !important; border-radius: 50% !important; border: 2px solid var(--color-primary-border) !important; border-top-color: var(--color-primary) !important; animation: ofb-spin .8s linear infinite !important; }
@keyframes ofb-spin { to { transform: rotate(360deg) !important; } }
.ofb-error { display: flex !important; gap: 8px !important; align-items: center !important; background: #fdecec !important; color: #b3261e !important; border: 1px solid #f5c2c0 !important; border-radius: 10px !important; padding: 10px 12px !important; font-size: 13px !important; }

.ofb-btn { display: inline-flex !important; align-items: center !important; gap: 6px !important; height: 36px !important; padding: 0 14px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 600 !important; cursor: pointer !important; border: 1px solid transparent !important; }
.ofb-btn--ghost { border-color: var(--color-primary-muted) !important; color: var(--color-primary) !important; background: #fff !important; }
.ofb-btn--ghost:hover { background: #fff !important; border-color: var(--color-primary) !important; }
.ofb-btn--primary { background: var(--gradient-primary, var(--color-primary)) !important; color: #fff !important; }
.ofb-btn--primary:hover { filter: brightness(1.08) !important; }

/* Donut */
.ofb-chart { display: grid !important; grid-template-columns: 170px 1fr !important; align-items: center !important; gap: 20px !important; padding: 14px !important; border: 1px solid var(--color-primary-border) !important; border-radius: 14px !important; background: linear-gradient(180deg, #fff, var(--color-primary-soft)) !important; }
.ofb-donut { width: 170px !important; height: 170px !important; }
.ofb-donut-track { fill: none !important; stroke: var(--color-primary-border) !important; stroke-width: 22 !important; }
.ofb-donut-seg { fill: none !important; stroke-width: 22 !important; transition: stroke-dasharray .4s ease !important; }
.ofb-donut-cap { font-size: 10px !important; fill: var(--color-text-secondary, #6b6678) !important; font-weight: 600 !important; text-transform: uppercase !important; letter-spacing: .05em !important; }
.ofb-donut-val { font-size: 15px !important; fill: var(--color-primary-ink) !important; font-weight: 800 !important; }
.ofb-legend { list-style: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; flex-direction: column !important; gap: 8px !important; }
.ofb-legend li { display: grid !important; grid-template-columns: 12px 1fr auto !important; grid-template-rows: auto auto !important; column-gap: 8px !important; align-items: center !important; padding: 8px 10px !important; border-radius: 10px !important; background: #fff !important; border: 1px solid var(--color-primary-border) !important; }
.ofb-legend li.is-main { border-color: var(--color-primary-muted) !important; box-shadow: 0 0 0 2px rgba(var(--color-primary-rgb), 0.08) !important; }
.ofb-swatch { width: 12px !important; height: 12px !important; border-radius: 3px !important; grid-row: span 2 !important; }
.ofb-legend-label { font-size: 12px !important; font-weight: 600 !important; color: var(--color-text-secondary, #6b6678) !important; }
.ofb-legend-pct { font-size: 11px !important; font-weight: 700 !important; color: var(--color-primary) !important; text-align: right !important; }
.ofb-legend-val { grid-column: 2 / 4 !important; font-size: 14px !important; font-weight: 700 !important; color: var(--color-primary-ink) !important; font-variant-numeric: tabular-nums !important; }

.ofb-alert { background: #fdf0f2 !important; border: 1px solid #f5c6cd !important; border-radius: 12px !important; padding: 12px 14px !important; color: #a61e30 !important; font-size: 13px !important; }
.ofb-alert-title { display: flex !important; gap: 8px !important; align-items: flex-start !important; font-weight: 600 !important; }
.ofb-alert ul { margin: 8px 0 0 !important; padding-left: 32px !important; }
.ofb-alert li { margin: 2px 0 !important; }

.ofb-h3 { margin: 0 0 10px !important; font-size: 12px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: .05em !important; color: var(--color-primary-ink) !important; }
.ofb-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px 18px !important; margin: 0 !important; }
.ofb-grid dt { font-size: 11px !important; color: var(--color-text-secondary, #6b6678) !important; font-weight: 600 !important; }
.ofb-grid dd { margin: 2px 0 0 !important; font-size: 13.5px !important; font-weight: 600 !important; color: var(--color-text-primary, #1e1b2e) !important; word-break: break-word !important; }
.ofb-sub { display: block !important; font-size: 11.5px !important; font-weight: 500 !important; color: var(--color-text-muted, #8b8798) !important; }
.ofb-lead-link { border: none !important; background: transparent !important; padding: 0 !important; font: inherit !important; font-weight: 700 !important; color: var(--color-primary) !important; text-decoration: underline !important; text-underline-offset: 3px !important; cursor: pointer !important; text-align: left !important; }
.ofb-stage { display: inline-flex !important; align-items: center !important; gap: 6px !important; }
.ofb-dot { width: 9px !important; height: 9px !important; border-radius: 50% !important; display: inline-block !important; }
.ofb-missing { color: #c2283a !important; }
.ofb-muted { color: var(--color-text-muted, #8b8798) !important; }

.ofb-props { margin-top: 12px !important; border: 1px solid var(--color-primary-border) !important; border-radius: 10px !important; padding: 8px 12px !important; }
.ofb-props-label { font-size: 12px !important; font-weight: 600 !important; color: var(--color-text-secondary, #6b6678) !important; }
.ofb-props ul { list-style: none !important; margin: 6px 0 0 !important; padding: 0 !important; }
.ofb-props li { display: flex !important; justify-content: space-between !important; gap: 12px !important; font-size: 13px !important; padding: 3px 0 !important; }

.ofb-calc { width: 100% !important; border-collapse: separate !important; border-spacing: 0 !important; font-size: 13.5px !important; border: 1px solid var(--color-primary-border) !important; border-radius: 12px !important; overflow: hidden !important; }
.ofb-calc td { padding: 10px 12px !important; border-bottom: 1px solid var(--color-primary-border) !important; vertical-align: top !important; color: var(--color-text-primary, #1e1b2e) !important; }
.ofb-num { text-align: right !important; white-space: nowrap !important; font-variant-numeric: tabular-nums !important; font-weight: 600 !important; }
.ofb-calc-total td { background: var(--color-primary-soft) !important; font-weight: 700 !important; }
.ofb-calc-net td { background: var(--gradient-primary, var(--color-primary)) !important; color: #fff !important; font-size: 15px !important; font-weight: 700 !important; border-bottom: none !important; }
.ofb-note { margin: 10px 0 0 !important; font-size: 12px !important; color: var(--color-text-secondary, #6b6678) !important; }

@media (max-width: 640px) {
  .ofb-overlay { padding: 12px !important; }
  .ofb-chart { grid-template-columns: 1fr !important; justify-items: center !important; }
  .ofb-legend { width: 100% !important; }
  .ofb-grid { grid-template-columns: 1fr !important; }
  .ofb-foot { flex-direction: column-reverse !important; }
  .ofb-btn { justify-content: center !important; }
}
</style>
