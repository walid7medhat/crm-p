<template>
  <section class="om-kpis" aria-label="Financial overview">
    <div class="om-kpi om-kpi--net">
      <div class="om-kpi-lab">
        <iconify-icon icon="lucide:wallet" width="12" height="12" />
        Net company revenue
      </div>
      <strong class="om-kpi-val" :title="aedShort(summary.net_revenue)">
        <small>AED</small>{{ figure(summary.net_revenue) }}
      </strong>
      <span class="om-kpi-meta">{{ foot }}</span>
    </div>

    <div class="om-kpi-grid">
      <div class="om-kpi">
        <div class="om-kpi-lab">Gross commission</div>
        <strong class="om-kpi-val" :title="aedShort(summary.gross_commission)">
          <small>AED</small>{{ figure(summary.gross_commission) }}
        </strong>
      </div>
      <div class="om-kpi">
        <div class="om-kpi-lab">Agent commission</div>
        <strong class="om-kpi-val" :title="aedShort(summary.agent_commission)">
          <small>AED</small>{{ figure(summary.agent_commission) }}
        </strong>
      </div>
      <div class="om-kpi">
        <div class="om-kpi-lab">Closed deals</div>
        <strong class="om-kpi-val">
          {{ Number(summary.closed_deals || 0).toLocaleString('en-US') }}
        </strong>
      </div>
      <button
        type="button"
        class="om-kpi om-kpi--btn"
        :class="{ 'is-active': dataStatus === 'missing', 'is-zero': !summary.incomplete_deals }"
        @click="$emit('toggle-missing')"
      >
        <div class="om-kpi-lab">Missing data</div>
        <strong class="om-kpi-val om-kpi-val--warn">
          {{ Number(summary.incomplete_deals || 0).toLocaleString('en-US') }}
        </strong>
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { aedShort } from '@/components/owner-financial/format.js'

const props = defineProps({
  summary: { type: Object, required: true },
  dataStatus: { type: String, default: 'all' },
})

defineEmits(['toggle-missing'])

const figure = (v) => aedShort(v).replace(/^AED\s/, '')

const foot = computed(() => {
  const s = props.summary
  if (!s.deals) return 'No deals in this view'
  return s.incomplete_deals
    ? `From ${s.complete_deals} of ${s.deals} complete deals`
    : `From all ${s.deals} deals`
})
</script>

<style scoped>
.om-kpis {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.om-kpi {
  background: #fff;
  border: 1px solid var(--ofr-border, #e7dcec);
  border-radius: 12px;
  padding: 10px 12px;
  min-width: 0;
  box-shadow: 0 1px 2px rgba(var(--ofr-p-rgb, 115, 62, 135), 0.04);
}
.om-kpi--net {
  background: linear-gradient(135deg, #fff 0%, var(--ofr-soft, #f7f2f8) 100%);
  border-color: var(--ofr-muted-line, #d4c0de);
  position: relative;
  overflow: hidden;
  padding-left: 14px;
}
.om-kpi--net::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: var(--gradient-primary, var(--ofr-p, #733E87));
}
.om-kpi-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.om-kpi-lab {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: var(--om-fs-title, 11px) !important;
  font-weight: 650 !important;
  color: var(--ofr-text-2, #6b6678);
  letter-spacing: 0;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.om-kpi--net .om-kpi-lab { color: var(--ofr-ink, #3f2148); }
.om-kpi-val {
  display: flex;
  align-items: baseline;
  gap: 4px;
  margin: 0 !important;
  font-size: var(--om-fs-body, 12px) !important;
  font-weight: 700 !important;
  color: var(--ofr-ink, #3f2148);
  font-variant-numeric: tabular-nums;
  letter-spacing: 0;
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.om-kpi--net .om-kpi-val { color: var(--ofr-p, #733E87); }
.om-kpi-val--warn { color: var(--ofr-warn, #c2283a) !important; }
.om-kpi-val small {
  font-size: inherit !important;
  font-weight: 650 !important;
  color: var(--ofr-text-3, #8b8798);
  letter-spacing: 0;
}
.om-kpi-meta {
  display: block;
  margin-top: 4px;
  font-size: var(--om-fs-body, 12px) !important;
  color: var(--ofr-text-3, #8b8798);
  line-height: 1.3;
}
.om-kpi--btn {
  font: inherit;
  text-align: left;
  cursor: pointer;
  color: inherit;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.om-kpi--btn.is-active {
  border-color: var(--ofr-warn, #c2283a);
  box-shadow: 0 0 0 2px rgba(194, 40, 58, 0.12);
}
.om-kpi--btn.is-zero .om-kpi-val--warn { color: var(--ofr-text-2, #6b6678) !important; }
</style>
