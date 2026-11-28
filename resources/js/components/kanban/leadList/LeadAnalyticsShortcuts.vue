<template>
  <section
    class="lead-analytics-row lfs"
    :class="{ 'lfs--mobile-temp': isMobileTemp }"
    role="toolbar"
    aria-label="Lead quick filters"
  >
    <div class="lfs-bar">
      <div
        v-for="(group, groupIndex) in visibleGroups"
        :key="group.id"
        class="lfs-segment"
        :class="`lfs-segment--${group.id}`"
      >
        <span v-if="!isMobileTemp && groupIndex > 0" class="lfs-divider" aria-hidden="true" />

        <span v-if="!isMobileTemp" class="lfs-segment__label">{{ group.label }}</span>

        <div class="lfs-pills">
          <button
            v-for="chip in group.chips"
            :key="chip.key"
            type="button"
            class="lfs-pill"
            :class="[
              `lfs-pill--${chip.tone}`,
              {
                'is-active': activeFilter === chip.key,
                'is-zero': !metrics[chip.metricKey],
              },
            ]"
            :aria-pressed="activeFilter === chip.key"
            :aria-label="`${chip.label}, ${formatValue(metrics[chip.metricKey])} leads${activeFilter === chip.key ? ', selected' : ''}`"
            :title="chip.hint || chip.label"
            @click="onChipClick(chip.key)"
          >
            <iconify-icon
              v-if="chip.icon"
              :icon="chip.icon"
              class="lfs-pill__icon"
              :width="isMobileTemp ? 12 : 14"
              :height="isMobileTemp ? 12 : 14"
              aria-hidden="true"
            />
            <span class="lfs-pill__label">{{ chip.label }}</span>
            <span class="lfs-pill__count">{{ formatValue(metrics[chip.metricKey]) }}</span>
          </button>
        </div>
      </div>

      <div v-if="activeFilter && !isMobileTemp" class="lfs-active-banner">
        <span class="lfs-active-banner__text">
          Filtering:
          <strong>{{ activeFilterLabel }}</strong>
        </span>
        <button
          type="button"
          class="lfs-pill lfs-pill--clear"
          aria-label="Clear active filter"
          @click="onChipClick(null)"
        >
          <iconify-icon icon="lucide:x" width="12" height="12" aria-hidden="true" />
          Clear
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  metrics: {
    type: Object,
    default: () => ({}),
  },
  activeFilter: {
    type: String,
    default: null,
  },
  /** `mobile-temp` = compact Hot/Warm/Cold row for the mobile board. */
  variant: {
    type: String,
    default: 'default',
  },
})

const emit = defineEmits(['toggle-filter'])

const isMobileTemp = computed(() => props.variant === 'mobile-temp')

const filterLabelByKey = Object.fromEntries(
  [
    { key: 'temp_cold', label: 'Cold' },
    { key: 'temp_warm', label: 'Warm' },
    { key: 'temp_hot', label: 'Hot' },
    { key: 'call_answered', label: 'Answered' },
    { key: 'call_no_answer', label: 'No answer' },
  ].map((item) => [item.key, item.label])
)

const activeFilterLabel = computed(() => {
  if (!props.activeFilter) return ''
  return filterLabelByKey[props.activeFilter] || props.activeFilter
})

const temperatureChips = [
  { key: 'temp_cold', metricKey: 'tempCold', label: 'Cold', tone: 'cold', icon: 'lucide:snowflake', hint: 'Cold leads' },
  { key: 'temp_warm', metricKey: 'tempWarm', label: 'Warm', tone: 'warm', icon: 'lucide:thermometer', hint: 'Warm leads' },
  { key: 'temp_hot', metricKey: 'tempHot', label: 'Hot', tone: 'hot', icon: 'lucide:flame', hint: 'Hot leads' },
]

/** Mobile compact row follows the reference order: Hot → Warm → Cold. */
const mobileTemperatureChips = [
  { key: 'temp_hot', metricKey: 'tempHot', label: 'Hot', tone: 'hot', icon: 'lucide:flame', hint: 'Hot leads' },
  { key: 'temp_warm', metricKey: 'tempWarm', label: 'Warm', tone: 'warm', icon: 'lucide:sun', hint: 'Warm leads' },
  { key: 'temp_cold', metricKey: 'tempCold', label: 'Cold', tone: 'cold', icon: 'lucide:snowflake', hint: 'Cold leads' },
]

const filterGroups = [
  {
    id: 'temperature',
    label: 'TEMP',
    chips: temperatureChips,
  },
  {
    id: 'calls',
    label: 'CALLS',
    chips: [
      { key: 'call_answered', metricKey: 'callAnswered', label: 'Answered', tone: 'answered', icon: 'lucide:phone', hint: 'Call answered' },
      { key: 'call_no_answer', metricKey: 'callNoAnswer', label: 'No Answer', tone: 'no-answer', icon: 'lucide:phone-off', hint: 'No answer' },
    ],
  },
]

const visibleGroups = computed(() => {
  if (!isMobileTemp.value) return filterGroups
  return [{ id: 'temperature', label: 'TEMP', chips: mobileTemperatureChips }]
})

function formatValue(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0'
  return n.toLocaleString()
}

function onChipClick(key) {
  if (!key) {
    emit('toggle-filter', null)
    return
  }
  emit('toggle-filter', props.activeFilter === key ? null : key)
}
</script>

<style scoped>
.lfs {
  flex-shrink: 0;
  width: 100%;
  margin-bottom: 10px;
  padding: 0 2px;
  box-sizing: border-box;
  font-family: Montserrat, Inter, system-ui, sans-serif;
}

.lfs-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
  width: fit-content;
  max-width: 100%;
  min-width: 0;
  padding: 8px 12px;
  border: 1px solid rgba(226, 224, 236, 0.9);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.82);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.9),
    0 8px 18px -14px rgba(40, 20, 70, 0.35);
}

.lfs-segment {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex-shrink: 0;
}

.lfs-divider {
  width: 1px;
  height: 22px;
  background: #e6e4ee;
  flex-shrink: 0;
  margin: 0 2px 0 4px;
}

.lfs-segment__label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #b0acb8;
  white-space: nowrap;
  flex-shrink: 0;
}

.lfs-pills {
  display: inline-flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 8px;
  min-width: 0;
}

.lfs-pill {
  --pill-accent: var(--color-primary);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  height: 32px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid #eceaf1;
  background: #ffffff;
  color: #1c1917;
  font-size: 13px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 0 1px 1px rgba(30, 20, 50, 0.04);
  transition:
    color 0.15s ease,
    border-color 0.15s ease,
    background 0.15s ease,
    box-shadow 0.15s ease;
  -webkit-appearance: none;
  appearance: none;
  transform: none !important;
}

.lfs-pill__icon {
  flex-shrink: 0;
  width: auto;
  height: auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 0;
  background: transparent !important;
  color: var(--pill-accent) !important;
  opacity: 1;
}

.lfs-pill__label {
  color: var(--pill-accent);
  font-weight: 600;
}

.lfs-pill__count {
  font-size: 13px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #1c1917;
  min-width: 0;
  text-align: left;
}

.lfs-pill.is-zero .lfs-pill__count {
  opacity: 0.7;
}

.lfs-pill--cold {
  --pill-accent: #3b82f6;
}

.lfs-pill--warm {
  --pill-accent: #f59e0b;
}

.lfs-pill--hot {
  --pill-accent: #f97316;
}

.lfs-pill--answered {
  --pill-accent: #22c55e;
}

.lfs-pill--no-answer {
  --pill-accent: #ef4444;
}

.lfs-pill:hover {
  transform: none !important;
  border-color: color-mix(in srgb, var(--pill-accent) 35%, #e5e7eb);
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
  background: #ffffff;
}

.lfs-pill:focus-visible {
  outline: 2px solid var(--pill-accent);
  outline-offset: 2px;
}

.lfs-pill.is-active {
  transform: none !important;
  border-color: var(--pill-accent) !important;
  background: color-mix(in srgb, var(--pill-accent) 10%, #ffffff) !important;
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--pill-accent) 18%, transparent);
}

.lfs-pill.is-active .lfs-pill__icon,
.lfs-pill.is-active .lfs-pill__label {
  color: var(--pill-accent) !important;
}

.lfs-pill.is-active .lfs-pill__count {
  color: var(--color-primary-ink);
  opacity: 1;
}

.lfs-active-banner {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  padding: 4px 8px 4px 12px;
  border-radius: 999px;
  border: 1px solid rgba(var(--color-primary-rgb), 0.18);
  background: var(--gradient-primary);
  flex-shrink: 0;
}

.lfs-active-banner__text {
  font-size: 12px;
  font-weight: 600;
  color: #ffffff !important;
  -webkit-text-fill-color: #ffffff !important;
  white-space: nowrap;
}

.lfs-active-banner__text strong {
  color: #ffffff !important;
  -webkit-text-fill-color: #ffffff !important;
  font-weight: 700;
}

.lfs-pill--clear {
  border-color: rgba(var(--color-primary-rgb), 0.2);
  background: #fff;
  color: var(--color-primary);
  --pill-accent: var(--color-primary);
  gap: 4px;
  padding: 5px 10px;
  font-size: 12px;
}

.lfs-pill--clear .lfs-pill__icon,
.lfs-pill--clear {
  color: var(--color-primary);
}

.lfs-pill--clear:hover {
  border-color: var(--color-primary);
  background: var(--gradient-primary);
  color: #ffffff;
}

@media (max-width: 1200px) {
  .lfs-bar {
    overflow-x: auto;
    flex-wrap: nowrap;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }

  .lfs-bar::-webkit-scrollbar {
    display: none;
  }

  .lfs-segment {
    flex-shrink: 0;
  }

  .lfs-pill--clear {
    margin-left: 0;
    flex-shrink: 0;
  }
}

@media (max-width: 768px) {
  .lfs:not(.lfs--mobile-temp) {
    padding: 0 4px;
    margin-bottom: 8px;
  }

  .lfs:not(.lfs--mobile-temp) .lfs-segment {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .lfs:not(.lfs--mobile-temp) .lfs-divider {
    display: none;
  }

  .lfs:not(.lfs--mobile-temp) .lfs-bar {
    flex-wrap: wrap;
    overflow-x: visible;
  }
}

/* Compact Hot / Warm / Cold row for the mobile board (above stage pills). */
.lfs--mobile-temp {
  width: 100%;
  margin: 0 0 6px;
  padding: 0;
}

.lfs--mobile-temp .lfs-bar {
  display: flex;
  width: 100%;
  max-width: none;
  padding: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  gap: 0;
}

.lfs--mobile-temp .lfs-segment,
.lfs--mobile-temp .lfs-pills {
  display: flex;
  width: 100%;
  gap: 6px;
  min-width: 0;
}

.lfs--mobile-temp .lfs-pill {
  flex: 1 1 0;
  justify-content: space-between;
  height: 28px;
  padding: 0 8px;
  gap: 4px;
  border: none;
  box-shadow: none;
  font-size: 11px;
}

.lfs--mobile-temp .lfs-pill__label {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 11px;
  font-weight: 700;
}

.lfs--mobile-temp .lfs-pill__count {
  font-size: 11px;
  font-weight: 700;
  color: inherit;
}

.lfs--mobile-temp .lfs-pill--hot {
  background: #fee2e2;
  color: #991b1b;
  --pill-accent: #991b1b;
}

.lfs--mobile-temp .lfs-pill--warm {
  background: #ffedd5;
  color: #c2410c;
  --pill-accent: #c2410c;
}

.lfs--mobile-temp .lfs-pill--cold {
  background: #e0f2fe;
  color: #0369a1;
  --pill-accent: #0369a1;
}

.lfs--mobile-temp .lfs-pill--hot .lfs-pill__icon,
.lfs--mobile-temp .lfs-pill--hot .lfs-pill__label,
.lfs--mobile-temp .lfs-pill--hot .lfs-pill__count,
.lfs--mobile-temp .lfs-pill--warm .lfs-pill__icon,
.lfs--mobile-temp .lfs-pill--warm .lfs-pill__label,
.lfs--mobile-temp .lfs-pill--warm .lfs-pill__count,
.lfs--mobile-temp .lfs-pill--cold .lfs-pill__icon,
.lfs--mobile-temp .lfs-pill--cold .lfs-pill__label,
.lfs--mobile-temp .lfs-pill--cold .lfs-pill__count {
  color: inherit !important;
}

.lfs--mobile-temp .lfs-pill.is-active {
  box-shadow: inset 0 0 0 1.5px currentColor;
}

.lfs--mobile-temp .lfs-pill--hot.is-active,
.lfs--mobile-temp .lfs-pill--hot:hover {
  background: #fecaca !important;
  color: #7f1d1d;
}

.lfs--mobile-temp .lfs-pill--warm.is-active,
.lfs--mobile-temp .lfs-pill--warm:hover {
  background: #fed7aa !important;
  color: #9a3412;
}

.lfs--mobile-temp .lfs-pill--cold.is-active,
.lfs--mobile-temp .lfs-pill--cold:hover {
  background: #bae6fd !important;
  color: #075985;
}

.lfs--mobile-temp .lfs-pill:hover {
  box-shadow: none;
  filter: none;
}
</style>
