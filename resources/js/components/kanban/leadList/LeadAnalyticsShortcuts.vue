<template>
  <section class="lead-analytics-row lfs" role="toolbar" aria-label="Lead quick filters">
    <div class="lfs-bar">
      <div
        v-for="(group, groupIndex) in filterGroups"
        :key="group.id"
        class="lfs-segment"
        :class="`lfs-segment--${group.id}`"
      >
        <span v-if="groupIndex > 0" class="lfs-divider" aria-hidden="true" />

        <span class="lfs-segment__label">{{ group.label }}</span>

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
              width="14"
              height="14"
              aria-hidden="true"
            />
            <span class="lfs-pill__label">{{ chip.label }}</span>
            <span class="lfs-pill__count">{{ formatValue(metrics[chip.metricKey]) }}</span>
          </button>
        </div>
      </div>

      <div v-if="activeFilter" class="lfs-active-banner">
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
})

const emit = defineEmits(['toggle-filter'])

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

const filterGroups = [
  {
    id: 'temperature',
    label: 'TEMP',
    chips: [
      { key: 'temp_cold', metricKey: 'tempCold', label: 'Cold', tone: 'cold', icon: 'lucide:snowflake', hint: 'Cold leads' },
      { key: 'temp_warm', metricKey: 'tempWarm', label: 'Warm', tone: 'warm', icon: 'lucide:thermometer', hint: 'Warm leads' },
      { key: 'temp_hot', metricKey: 'tempHot', label: 'Hot', tone: 'hot', icon: 'lucide:flame', hint: 'Hot leads' },
    ],
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
  gap: 10px 16px;
  width: 100%;
  min-width: 0;
  padding: 4px 2px 4px;
  border: none;
  border-bottom: none;
  background: transparent;
  box-shadow: none;
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
  height: 24px;
  background: rgba(30, 27, 46, 0.12);
  flex-shrink: 0;
  margin: 0 4px;
}

.lfs-segment__label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #9ca3af;
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
  --pill-accent: #6b21a8;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #ffffff;
  color: #111827;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
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
  font-size: 12px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #4c1d95;
  min-width: 0;
  text-align: left;
}

.lfs-pill.is-zero .lfs-pill__count {
  opacity: 0.7;
}

.lfs-pill--cold {
  --pill-accent: #0284c7;
}

.lfs-pill--warm {
  --pill-accent: #b45309;
}

.lfs-pill--hot {
  --pill-accent: #ea580c;
}

.lfs-pill--answered {
  --pill-accent: #16a34a;
}

.lfs-pill--no-answer {
  --pill-accent: #dc2626;
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
  color: #4c1d95;
  opacity: 1;
}

.lfs-active-banner {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  padding: 4px 8px 4px 12px;
  border-radius: 999px;
  border: 1px solid rgba(107, 33, 168, 0.18);
  background: #f3e8ff;
  flex-shrink: 0;
}

.lfs-active-banner__text {
  font-size: 12px;
  font-weight: 600;
  color: #5b21b6;
  white-space: nowrap;
}

.lfs-active-banner__text strong {
  color: #6b21a8;
  font-weight: 700;
}

.lfs-pill--clear {
  border-color: rgba(107, 33, 168, 0.2);
  background: #fff;
  color: #6b21a8;
  --pill-accent: #6b21a8;
  gap: 4px;
  padding: 5px 10px;
  font-size: 12px;
}

.lfs-pill--clear .lfs-pill__icon,
.lfs-pill--clear {
  color: #6b21a8;
}

.lfs-pill--clear:hover {
  border-color: #6b21a8;
  background: #f3e8ff;
  color: #6b21a8;
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
  .lfs {
    padding: 0 4px;
    margin-bottom: 8px;
  }

  .lfs-segment {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .lfs-divider {
    display: none;
  }

  .lfs-bar {
    flex-wrap: wrap;
    overflow-x: visible;
  }
}
</style>
