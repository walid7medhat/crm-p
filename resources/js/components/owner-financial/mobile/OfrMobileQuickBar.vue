<template>
  <div class="om-quick">
    <button type="button" class="om-quick-chip" @click="$emit('open-dates')">
      <iconify-icon icon="lucide:calendar-days" width="14" height="14" />
      <span class="om-quick-text">{{ periodLabel }}</span>
      <iconify-icon icon="lucide:chevron-down" width="14" height="14" class="om-quick-chev" />
    </button>

    <button type="button" class="om-quick-chip" :class="{ 'is-active': sourceCount }" @click="$emit('open-sources')">
      <iconify-icon icon="lucide:radio" width="14" height="14" />
      <span class="om-quick-text">{{ sourceLabel }}</span>
      <em v-if="sourceCount" class="om-quick-count">{{ sourceCount }}</em>
      <iconify-icon icon="lucide:chevron-down" width="14" height="14" class="om-quick-chev" />
    </button>

    <button
      type="button"
      class="om-quick-more"
      :class="{ 'is-active': extraCount }"
      aria-label="More filters"
      @click="$emit('open-filters')"
    >
      <iconify-icon icon="lucide:sliders-horizontal" width="15" height="15" />
      <em v-if="extraCount" class="om-quick-count">{{ extraCount }}</em>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatPeriodLabel, formatSourceLabel } from './useOfrMobile.js'

const props = defineProps({
  applied: { type: Object, required: true },
  activeCount: { type: Number, default: 0 },
})

defineEmits(['open-dates', 'open-sources', 'open-filters'])

const periodLabel = computed(() => formatPeriodLabel(props.applied.date_from, props.applied.date_to))
const sourceLabel = computed(() => formatSourceLabel(props.applied.source))
const sourceCount = computed(() => (props.applied.source || []).length)

/** Active filters excluding the period + source chips already shown. */
const extraCount = computed(() => {
  const keys = ['search', 'agent_id', 'client', 'deal_number', 'branch_id', 'deal_type',
    'deal_status', 'min_amount', 'max_amount', 'min_commission', 'max_commission', 'data_status']
  const defaults = {
    search: '', agent_id: '', client: '', deal_number: '', branch_id: '', deal_type: '',
    deal_status: 'closed', min_amount: '', max_amount: '', min_commission: '', max_commission: '',
    data_status: 'all',
  }
  return keys.filter((k) => String(props.applied[k] ?? '') !== String(defaults[k] ?? '')).length
})
</script>

<style scoped>
.om-quick {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}
.om-quick-chip {
  flex: 1;
  min-width: 0;
  height: 34px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 8px 0 10px;
  border-radius: 999px;
  border: 1px solid var(--ofr-border, #e7dcec);
  background: #fff;
  color: var(--ofr-ink, #3f2148);
  font-size: var(--om-fs-body, 12px);
  font-weight: 650;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.om-quick-chip.is-active {
  border-color: var(--ofr-p, #733E87);
  background: var(--ofr-soft, #f7f2f8);
  color: var(--ofr-p, #733E87);
}
.om-quick-chip iconify-icon:first-child { color: var(--ofr-p, #733E87); flex-shrink: 0; }
.om-quick-text {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
}
.om-quick-chev { color: var(--ofr-text-3, #8b8798); flex-shrink: 0; }
.om-quick-count {
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--ofr-p, #733E87);
  color: #fff;
  font-size: 9px;
  font-weight: 800;
  font-style: normal;
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
}
.om-quick-more {
  position: relative;
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  border-radius: 10px;
  border: 1px solid var(--ofr-border, #e7dcec);
  background: #fff;
  color: var(--ofr-p, #733E87);
  display: grid;
  place-items: center;
  cursor: pointer;
}
.om-quick-more.is-active {
  border-color: var(--ofr-p, #733E87);
  background: var(--ofr-soft, #f7f2f8);
}
.om-quick-more .om-quick-count {
  position: absolute;
  top: -4px;
  right: -4px;
}
</style>
