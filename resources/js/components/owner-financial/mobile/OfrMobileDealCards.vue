<template>
  <section class="om-deals">
    <div class="om-deals-head">
      <h2 class="om-h2">Deals</h2>
      <div class="om-deals-meta">
        <span class="om-total">{{ pagination.total.toLocaleString('en-US') }}</span>
        <div class="om-sort" :class="{ 'is-open': sortOpen }">
          <button
            type="button"
            class="om-sort-btn"
            :aria-expanded="sortOpen"
            aria-haspopup="listbox"
            @click.stop="sortOpen = !sortOpen"
          >
            <iconify-icon icon="lucide:arrow-up-down" width="11" height="11" />
            <span>{{ sortLabel }}</span>
            <iconify-icon icon="lucide:chevron-down" width="12" height="12" class="om-sort-chev" />
          </button>
          <div v-if="sortOpen" class="om-sort-menu" role="listbox">
            <button
              v-for="opt in SORT_OPTIONS"
              :key="opt.value"
              type="button"
              class="om-sort-opt"
              :class="{ 'is-on': sortKey === opt.value }"
              role="option"
              :aria-selected="sortKey === opt.value"
              @click.stop="pickSort(opt.value)"
            >{{ opt.label }}</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="error" class="om-error">
      <span>{{ error }}</span>
      <button type="button" class="om-link" @click="$emit('retry')">Retry</button>
    </div>

    <div class="om-cards" :class="{ 'is-loading': loading }">
      <div v-if="!loading && !rows.length" class="om-empty">
        <iconify-icon icon="lucide:search-x" width="22" height="22" />
        <span>No deals match these filters.</span>
      </div>

      <button
        v-for="row in rows"
        :key="row.id"
        type="button"
        class="om-card"
        :class="{ 'is-incomplete': row.is_incomplete, 'is-selected': selectedId === row.id }"
        @click="$emit('open', row.id)"
      >
        <div class="om-card-top">
          <span class="om-deal-no" @click.stop="$emit('open-deal', row)">{{ row.deal_number || `#${row.id}` }}</span>
          <strong v-if="!row.is_incomplete" class="om-net">{{ aedShort(row.net_revenue) }}</strong>
          <span v-else class="om-pill">
            <iconify-icon icon="lucide:triangle-alert" width="11" height="11" />
            Incomplete
          </span>
        </div>
        <div class="om-prop" :title="row.property || ''">{{ row.property || '—' }}</div>
        <div class="om-card-row">
          <span>{{ row.client?.name || '—' }}</span>
          <span class="om-sep">·</span>
          <span>{{ row.agent?.name || '—' }}</span>
        </div>
        <div class="om-card-foot">
          <span>{{ formatDate(row.deal_date) }}</span>
          <span v-if="row.deal_type_label" class="om-type">{{ row.deal_type_label }}</span>
        </div>
      </button>

      <div v-if="loading" class="om-loading"><span class="om-spinner" /> Loading…</div>
    </div>

    <div v-if="pagination.total" class="om-pager">
      <button type="button" class="om-page-btn" :disabled="page <= 1 || loading" @click="$emit('page', page - 1)">
        <iconify-icon icon="lucide:chevron-left" width="16" height="16" />
      </button>
      <span class="om-pager-info">{{ rangeStart }}–{{ rangeEnd }} of {{ pagination.total.toLocaleString('en-US') }}</span>
      <button type="button" class="om-page-btn" :disabled="page >= pagination.last_page || loading" @click="$emit('page', page + 1)">
        <iconify-icon icon="lucide:chevron-right" width="16" height="16" />
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { aedShort, formatDate } from '@/components/owner-financial/format.js'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  pagination: { type: Object, required: true },
  page: { type: Number, required: true },
  loading: Boolean,
  error: { type: String, default: '' },
  selectedId: { type: [Number, null], default: null },
  sort: { type: String, default: 'deal_date' },
})

const emit = defineEmits(['open', 'open-deal', 'page', 'sort', 'retry'])

const SORT_OPTIONS = [
  { value: 'deal_date', label: 'Date' },
  { value: 'net_revenue', label: 'Net revenue' },
  { value: 'gross_commission', label: 'Gross' },
  { value: 'deal_amount', label: 'Deal amount' },
]

const sortOpen = ref(false)
const sortKey = computed(() => props.sort)
const sortLabel = computed(() => SORT_OPTIONS.find((o) => o.value === sortKey.value)?.label || 'Date')
const rangeStart = computed(() => (props.pagination.current_page - 1) * props.pagination.per_page + 1)
const rangeEnd = computed(() => Math.min(props.pagination.total, props.pagination.current_page * props.pagination.per_page))

function pickSort(value) {
  sortOpen.value = false
  if (value !== sortKey.value) emit('sort', value)
}

function onDocPointer(e) {
  if (!sortOpen.value) return
  const root = e.target?.closest?.('.om-sort')
  if (!root) sortOpen.value = false
}

onMounted(() => document.addEventListener('pointerdown', onDocPointer, true))
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocPointer, true))
</script>

<style scoped>
.om-deals { display: flex; flex-direction: column; gap: 10px; }
.om-deals-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.om-h2 {
  margin: 0 !important;
  font-size: var(--om-fs-title, 11px) !important;
  font-weight: 650 !important;
  letter-spacing: 0 !important;
  text-transform: none !important;
  color: var(--ofr-ink, #3f2148) !important;
}
.om-deals-meta { display: flex; align-items: center; gap: 8px; }
.om-total {
  font-size: var(--om-fs-body, 12px); font-weight: 700; color: var(--ofr-p, #733E87);
  background: var(--ofr-soft, #f7f2f8); border: 1px solid var(--ofr-border, #e7dcec);
  border-radius: 999px; padding: 2px 8px;
}
.om-sort { position: relative; }
.om-sort-btn {
  height: 26px;
  max-width: 132px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0 8px 0 7px;
  border: 1px solid var(--ofr-border, #e7dcec);
  border-radius: 999px;
  background: #fff;
  color: var(--ofr-text-2, #6b6678);
  font-size: 11px !important;
  font-weight: 600 !important;
  line-height: 1 !important;
  letter-spacing: 0 !important;
  text-transform: none !important;
  white-space: nowrap;
  cursor: pointer;
  -webkit-appearance: none;
  appearance: none;
}
.om-sort-btn span {
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 78px;
  font-size: inherit !important;
  font-weight: inherit !important;
}
.om-sort-btn iconify-icon { color: var(--ofr-p, #733E87); flex-shrink: 0; }
.om-sort-chev { color: var(--ofr-text-3, #8b8798) !important; }
.om-sort.is-open .om-sort-btn {
  border-color: var(--ofr-p, #733E87);
  background: var(--ofr-soft, #f7f2f8);
  color: var(--ofr-p, #733E87);
}
.om-sort-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 25;
  min-width: 140px;
  padding: 4px;
  background: #fff;
  border: 1px solid var(--ofr-border, #e7dcec);
  border-radius: 10px;
  box-shadow: 0 10px 24px rgba(var(--ofr-p-rgb, 115, 62, 135), 0.14);
}
.om-sort-opt {
  width: 100%;
  display: block;
  border: none;
  background: transparent;
  text-align: left;
  border-radius: 7px;
  padding: 7px 9px;
  font-size: 11px !important;
  font-weight: 600 !important;
  color: var(--ofr-text, #1e1b2e);
  cursor: pointer;
  line-height: 1.2 !important;
}
.om-sort-opt.is-on,
.om-sort-opt:active {
  background: var(--ofr-soft, #f7f2f8);
  color: var(--ofr-p, #733E87);
}
.om-cards { position: relative; display: flex; flex-direction: column; gap: 8px; min-height: 80px; }
.om-cards.is-loading { opacity: .55; pointer-events: none; }
.om-card {
  width: 100%; text-align: left; font: inherit; color: inherit; cursor: pointer;
  background: #fff; border: 1px solid var(--ofr-border, #e7dcec); border-radius: 12px;
  padding: 11px 12px; display: flex; flex-direction: column; gap: 4px;
  transition: border-color .15s ease, box-shadow .15s ease;
  -webkit-tap-highlight-color: transparent;
}
.om-card:active { border-color: var(--ofr-muted-line, #d4c0de); }
.om-card.is-selected { border-color: var(--ofr-p, #733E87); background: var(--ofr-soft, #f7f2f8); }
.om-card.is-incomplete { box-shadow: inset 3px 0 0 var(--ofr-warn, #c2283a); }
.om-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.om-deal-no {
  font-size: var(--om-fs-body, 12px) !important; font-weight: 700; color: var(--ofr-p, #733E87);
  text-decoration: underline; text-decoration-color: var(--ofr-muted-line, #d4c0de);
  text-underline-offset: 2px;
}
.om-net {
  font-size: var(--om-fs-body, 12px) !important; font-weight: 700; color: var(--ofr-ink, #3f2148);
  font-variant-numeric: tabular-nums; white-space: nowrap;
}
.om-pill {
  display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 999px;
  font-size: var(--om-fs-body, 12px); font-weight: 650; color: var(--ofr-warn, #c2283a);
  background: var(--ofr-warn-soft, #fdf0f2); border: 1px solid var(--ofr-warn-line, #f5c6cd);
}
.om-prop {
  font-size: var(--om-fs-body, 12px) !important; font-weight: 600; color: var(--ofr-text, #1e1b2e);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.om-card-row {
  font-size: var(--om-fs-body, 12px) !important; color: var(--ofr-text-2, #6b6678);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.om-sep { opacity: .5; margin: 0 2px; }
.om-card-foot {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  margin-top: 2px; font-size: var(--om-fs-body, 12px) !important; color: var(--ofr-text-3, #8b8798);
}
.om-type {
  font-size: var(--om-fs-body, 12px); font-weight: 650; letter-spacing: 0; text-transform: none;
  color: var(--ofr-p, #733E87); background: var(--ofr-soft, #f7f2f8);
  border-radius: 6px; padding: 2px 6px;
}
.om-empty {
  text-align: center; padding: 28px 10px; color: var(--ofr-text-3, #8b8798);
  font-size: var(--om-fs-body, 12px);
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.om-error {
  display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e;
  border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px;
  font-size: var(--om-fs-body, 12px);
}
.om-link { margin-left: auto; border: none; background: transparent; color: #b3261e; text-decoration: underline; font-weight: 600; cursor: pointer; }
.om-loading {
  position: absolute; top: 20px; left: 50%; transform: translateX(-50%);
  display: inline-flex; align-items: center; gap: 8px; background: #fff;
  border: 1px solid var(--ofr-border, #e7dcec); border-radius: 999px; padding: 6px 14px;
  font-size: var(--om-fs-body, 12px); font-weight: 600; color: var(--ofr-text-2, #6b6678);
  box-shadow: 0 6px 16px rgba(var(--ofr-p-rgb, 115, 62, 135), 0.12);
}
.om-spinner {
  width: 14px; height: 14px; border-radius: 50%;
  border: 2px solid var(--ofr-border, #e7dcec); border-top-color: var(--ofr-p, #733E87);
  animation: om-spin .8s linear infinite;
}
@keyframes om-spin { to { transform: rotate(360deg); } }
.om-pager { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 4px 0 8px; }
.om-pager-info { font-size: var(--om-fs-body, 12px); color: var(--ofr-text-2, #6b6678); font-variant-numeric: tabular-nums; }
.om-page-btn {
  width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--ofr-border, #e7dcec);
  background: #fff; color: var(--ofr-p, #733E87); display: grid; place-items: center; cursor: pointer;
}
.om-page-btn:disabled { opacity: .4; cursor: not-allowed; }
</style>
