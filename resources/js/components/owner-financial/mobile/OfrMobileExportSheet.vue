<template>
  <Teleport to="body">
    <div v-if="open" class="om-sheet-root">
      <button type="button" class="om-sheet-backdrop" aria-label="Close" @click="$emit('close')" />
      <div class="om-sheet" role="dialog" aria-modal="true" aria-label="Export report">
        <div class="om-sheet-handle" />
        <header class="om-sheet-head">
          <h3>Export</h3>
          <button type="button" class="om-icon-btn" aria-label="Close" @click="$emit('close')">
            <iconify-icon icon="lucide:x" width="18" height="18" />
          </button>
        </header>

        <div class="om-sheet-body">
          <div class="om-summary">
            <div class="om-summary-lab">Current view</div>
            <div class="om-summary-row"><span>Deals</span><strong>{{ total.toLocaleString('en-US') }}</strong></div>
            <div v-for="(v, k) in filterSummary" :key="k" class="om-summary-row">
              <span>{{ k }}</span><strong>{{ v }}</strong>
            </div>
            <p v-if="!Object.keys(filterSummary).length" class="om-muted">Default filters · closed deals</p>
          </div>

          <button
            type="button"
            class="om-export-opt"
            :disabled="exporting || !total"
            @click="$emit('excel')"
          >
            <span class="om-export-ico"><iconify-icon icon="lucide:file-spreadsheet" width="20" height="20" /></span>
            <span class="om-export-text">
              <strong>Excel spreadsheet</strong>
              <small>All matching deals + KPI summary sheet</small>
            </span>
            <iconify-icon v-if="exporting === 'excel'" icon="lucide:loader-2" class="om-spin" width="18" height="18" />
          </button>

          <button
            type="button"
            class="om-export-opt"
            :disabled="exporting || !total"
            @click="$emit('pdf')"
          >
            <span class="om-export-ico om-export-ico--pdf"><iconify-icon icon="lucide:file-text" width="20" height="20" /></span>
            <span class="om-export-text">
              <strong>PDF report</strong>
              <small>Branded summary, filters &amp; deal table</small>
            </span>
            <iconify-icon v-if="exporting === 'pdf'" icon="lucide:loader-2" class="om-spin" width="18" height="18" />
          </button>

          <p v-if="status" class="om-status" :class="{ 'is-err': statusError }">{{ status }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  open: Boolean,
  total: { type: Number, default: 0 },
  filterSummary: { type: Object, default: () => ({}) },
  exporting: { type: [Boolean, String], default: false },
  status: { type: String, default: '' },
  statusError: Boolean,
})

defineEmits(['close', 'excel', 'pdf'])
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
  position: relative; width: 100%; max-width: 560px;
  background: #fff; border-radius: 18px 18px 0 0;
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
.om-sheet-body { padding: 6px 14px calc(18px + env(safe-area-inset-bottom, 0px)); }
.om-summary {
  background: #f7f2f8; border: 1px solid #e7dcec; border-radius: 12px;
  padding: 10px 12px; margin-bottom: 12px;
}
.om-summary-lab {
  font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: #8b8798; margin-bottom: 6px;
}
.om-summary-row {
  display: flex; justify-content: space-between; gap: 10px;
  font-size: 12px; padding: 3px 0; color: #6b6678;
}
.om-summary-row strong {
  color: #3f2148; font-weight: 700; text-align: right;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;
}
.om-muted { margin: 0; font-size: 12px; color: #8b8798; }
.om-export-opt {
  width: 100%; display: flex; align-items: center; gap: 10px;
  border: 1px solid #e7dcec; background: #fff; border-radius: 12px;
  padding: 12px; margin-bottom: 8px; text-align: left; cursor: pointer;
  font: inherit; color: inherit;
}
.om-export-opt:disabled { opacity: .55; cursor: not-allowed; }
.om-export-opt:not(:disabled):active { background: #f7f2f8; }
.om-export-ico {
  width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
  display: grid; place-items: center; background: #efe6f3; color: #733E87;
}
.om-export-ico--pdf { background: #fdecec; color: #c2283a; }
.om-export-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.om-export-text strong { font-size: 13.5px; font-weight: 700; color: #1e1b2e; }
.om-export-text small { font-size: 11.5px; color: #8b8798; }
.om-spin { animation: om-spin .8s linear infinite; color: #733E87; }
@keyframes om-spin { to { transform: rotate(360deg); } }
.om-status { margin: 8px 0 0; font-size: 12.5px; font-weight: 600; color: #2f7a4a; }
.om-status.is-err { color: #b3261e; }
</style>
