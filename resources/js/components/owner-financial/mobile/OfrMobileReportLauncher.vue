<template>
  <Teleport to="body">
    <div v-if="open" class="om-sheet-root">
      <button type="button" class="om-sheet-backdrop" aria-label="Close" @click="$emit('close')" />
      <div class="om-sheet" role="dialog" aria-modal="true" aria-label="Generate analysis">
        <div class="om-sheet-handle" />
        <header class="om-sheet-head">
          <h3>Generate analysis</h3>
          <button type="button" class="om-icon-btn" aria-label="Close" @click="$emit('close')">
            <iconify-icon icon="lucide:x" width="18" height="18" />
          </button>
        </header>
        <div class="om-sheet-body">
          <p class="om-hint">Uses your current filters. Tap to apply a focused view.</p>
          <button
            v-for="item in ITEMS"
            :key="item.id"
            type="button"
            class="om-report"
            @click="$emit('select', item.id)"
          >
            <span class="om-report-ico">
              <iconify-icon :icon="item.icon" width="18" height="18" />
            </span>
            <span class="om-report-text">
              <strong>{{ item.label }}</strong>
              <small>{{ item.desc }}</small>
            </span>
            <iconify-icon icon="lucide:chevron-right" width="16" height="16" class="om-chev" />
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({ open: Boolean })
defineEmits(['close', 'select'])

const ITEMS = [
  { id: 'overview', label: 'Financial overview', desc: 'KPIs and insights for the current filters', icon: 'lucide:layout-dashboard' },
  { id: 'deals', label: 'Deals report', desc: 'Deal list sorted by date', icon: 'lucide:briefcase-business' },
  { id: 'commission', label: 'Commission report', desc: 'Highest gross commission first', icon: 'lucide:circle-dollar-sign' },
  { id: 'missing', label: 'Missing data', desc: 'Deals with incomplete financials', icon: 'lucide:triangle-alert' },
  { id: 'month', label: 'This month', desc: 'Closed deals from month start', icon: 'lucide:calendar' },
  { id: 'quarter', label: 'This quarter', desc: 'Closed deals this quarter', icon: 'lucide:calendar-range' },
  { id: 'year', label: 'This year', desc: 'Closed deals year to date', icon: 'lucide:calendar-days' },
  { id: 'sale', label: 'Sale analysis', desc: 'Primary + secondary sales', icon: 'lucide:building-2' },
  { id: 'rental', label: 'Rental analysis', desc: 'Rental deals only', icon: 'lucide:key-round' },
  { id: 'agent', label: 'Agent performance', desc: 'Top agents by net revenue', icon: 'lucide:users' },
  { id: 'branch', label: 'Branch performance', desc: 'Net revenue by branch', icon: 'lucide:map-pin' },
]
</script>

<style scoped>
.om-sheet-root {
  position: fixed; inset: 0; z-index: 13000;
  display: flex; align-items: flex-end; justify-content: center;
}
.om-sheet-backdrop {
  position: absolute; inset: 0; border: none; background: rgba(30, 27, 46, 0.45);
}
.om-sheet {
  position: relative; width: 100%; max-width: 560px; max-height: min(88vh, 680px);
  background: #fff; border-radius: 18px 18px 0 0;
  display: flex; flex-direction: column;
  box-shadow: 0 -12px 40px rgba(115, 62, 135, 0.18);
  animation: om-up .22s ease;
}
@keyframes om-up {
  from { transform: translateY(24px); opacity: .6; }
  to { transform: none; opacity: 1; }
}
.om-sheet-handle {
  width: 36px; height: 4px; border-radius: 999px; background: #e7dcec; margin: 8px auto 0;
}
.om-sheet-head {
  display: flex; align-items: center; justify-content: space-between; padding: 10px 14px 6px;
}
.om-sheet-head h3 {
  margin: 0 !important; font-size: 11px !important; font-weight: 650 !important;
  color: #3f2148 !important; letter-spacing: 0 !important; text-transform: none !important;
}
.om-icon-btn {
  width: 36px; height: 36px; border: none; border-radius: 10px;
  background: #f7f2f8; color: #733E87; display: grid; place-items: center; cursor: pointer;
}
.om-sheet-body { overflow: auto; padding: 4px 12px calc(18px + env(safe-area-inset-bottom, 0px)); }
.om-hint { margin: 0 4px 10px; font-size: 12px; color: #8b8798; }
.om-report {
  width: 100%; display: flex; align-items: center; gap: 10px;
  border: 1px solid #e7dcec; background: #fff; border-radius: 12px;
  padding: 11px 10px; margin-bottom: 8px; text-align: left; cursor: pointer;
  font: inherit; color: inherit;
}
.om-report:active { background: #f7f2f8; }
.om-report-ico {
  width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
  display: grid; place-items: center; background: #f7f2f8; color: #733E87;
  border: 1px solid #e7dcec;
}
.om-report-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.om-report-text strong { font-size: 13.5px; font-weight: 700; color: #1e1b2e; }
.om-report-text small { font-size: 11.5px; color: #8b8798; }
.om-chev { color: #8b8798; flex-shrink: 0; }
</style>
