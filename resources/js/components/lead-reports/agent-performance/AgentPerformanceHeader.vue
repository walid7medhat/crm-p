<template>
  <header class="ap-page-header">
    <div class="ap-page-header__main">
      <div class="ap-page-header__icon">
        <iconify-icon icon="lucide:bar-chart-3" width="22" height="22" />
      </div>
      <div>
        <h1 class="ap-page-header__title">Agent Performance</h1>
        <p class="ap-page-header__desc">
          Track converted deals, lead quality scores, deal values, and commissions per agent.
        </p>
      </div>
    </div>
    <div class="ap-page-header__actions">
      <span v-if="dateRangeLabel" class="ap-page-header__range">
        <iconify-icon icon="lucide:calendar-range" width="14" height="14" />
        {{ dateRangeLabel }}
      </span>
      <button type="button" class="ap-btn ap-btn--ghost" :disabled="loading" @click="$emit('refresh')">
        <iconify-icon icon="lucide:refresh-cw" width="14" height="14" :class="{ 'ap-spin': loading }" />
        <span>{{ loading ? 'Refreshing…' : 'Refresh' }}</span>
      </button>
    </div>
  </header>
</template>

<script setup>
defineProps({
  dateRangeLabel: { type: String, default: '' },
  loading: { type: Boolean, default: false },
})
defineEmits(['refresh'])
</script>
