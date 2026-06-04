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

        <div
          class="lfs-pills"
          :class="{ 'lfs-pills--segmented': group.segmented }"
        >
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
              width="15"
              height="15"
              aria-hidden="true"
            />
            <span class="lfs-pill__label">{{ chip.label }}</span>
            <span class="lfs-pill__count">{{ formatValue(metrics[chip.metricKey]) }}</span>
            <iconify-icon
              v-if="activeFilter === chip.key"
              icon="lucide:check-circle-2"
              class="lfs-pill__check"
              width="14"
              height="14"
              aria-hidden="true"
            />
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
          <iconify-icon icon="lucide:x" width="14" height="14" aria-hidden="true" />
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
    label: 'Temp',
    segmented: true,
    chips: [
      { key: 'temp_cold', metricKey: 'tempCold', label: 'Cold', tone: 'cold', icon: 'lucide:snowflake', hint: 'Cold leads' },
      { key: 'temp_warm', metricKey: 'tempWarm', label: 'Warm', tone: 'warm', icon: 'lucide:thermometer', hint: 'Warm leads' },
      { key: 'temp_hot', metricKey: 'tempHot', label: 'Hot', tone: 'hot', icon: 'lucide:flame', hint: 'Hot leads' },
    ],
  },
  {
    id: 'calls',
    label: 'Calls',
    segmented: true,
    chips: [
      { key: 'call_answered', metricKey: 'callAnswered', label: 'Answered', tone: 'answered', icon: 'lucide:phone-call', hint: 'Call answered' },
      { key: 'call_no_answer', metricKey: 'callNoAnswer', label: 'No answer', tone: 'no-answer', icon: 'lucide:phone-off', hint: 'No answer' },
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
  margin-bottom: 12px;
  padding: 0 6px;
  box-sizing: border-box;
  font-family: Montserrat, Inter, system-ui, sans-serif;
}

.lfs-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 16px;
  width: 100%;
  min-width: 0;
  padding: 12px 14px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(11, 7, 54, 0.42);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
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
  height: 32px;
  background: rgba(255, 255, 255, 0.22);
  flex-shrink: 0;
  margin: 0 6px;
}

.lfs-segment__label {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.75);
  white-space: nowrap;
  flex-shrink: 0;
  min-width: 42px;
}

.lfs-pills {
  display: inline-flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 6px;
  min-width: 0;
}

.lfs-pills--segmented {
  gap: 0;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  overflow: hidden;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.lfs-pills--segmented .lfs-pill {
  border-radius: 0;
  border: none;
  border-right: 1px solid rgba(255, 255, 255, 0.14);
}

.lfs-pills--segmented .lfs-pill:last-child {
  border-right: none;
}

.lfs-pill {
  --pill-accent: #a78bfa;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  margin: 0;
  padding: 9px 14px;
  border-radius: 11px;
  border: 1.5px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.2;
  white-space: nowrap;
  cursor: pointer;
  transition:
    transform 0.15s ease,
    color 0.15s ease,
    border-color 0.15s ease,
    background 0.15s ease,
    box-shadow 0.15s ease;
  -webkit-appearance: none;
  appearance: none;
}

.lfs-pill__icon {
  flex-shrink: 0;
  opacity: 1;
}

.lfs-pill__label {
  color: #fff;
}

.lfs-pill__count {
  font-size: 12px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  padding: 3px 8px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.28);
  color: #fff;
  min-width: 1.6em;
  text-align: center;
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.lfs-pill.is-zero .lfs-pill__count {
  opacity: 0.65;
}

.lfs-pill__check {
  flex-shrink: 0;
  color: #fff;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
}

/* Default tinted states (visible before click) */
.lfs-pill--cold {
  --pill-accent: #94a3b8;
  border-color: rgba(148, 163, 184, 0.45);
  background: rgba(148, 163, 184, 0.18);
}
.lfs-pill--cold .lfs-pill__icon {
  color: #cbd5e1;
}

.lfs-pill--warm {
  --pill-accent: #fbbf24;
  border-color: rgba(251, 191, 36, 0.5);
  background: rgba(251, 191, 36, 0.16);
}
.lfs-pill--warm .lfs-pill__icon {
  color: #fde68a;
}

.lfs-pill--hot {
  --pill-accent: #f87171;
  border-color: rgba(248, 113, 113, 0.5);
  background: rgba(248, 113, 113, 0.16);
}
.lfs-pill--hot .lfs-pill__icon {
  color: #fecaca;
}

.lfs-pill--answered {
  --pill-accent: #4ade80;
  border-color: rgba(74, 222, 128, 0.5);
  background: rgba(74, 222, 128, 0.14);
}
.lfs-pill--answered .lfs-pill__icon {
  color: #bbf7d0;
}

.lfs-pill--no-answer {
  --pill-accent: #fb923c;
  border-color: rgba(251, 146, 60, 0.5);
  background: rgba(251, 146, 60, 0.16);
}
.lfs-pill--no-answer .lfs-pill__icon {
  color: #fed7aa;
}

.lfs-pill:hover {
  transform: translateY(-1px);
  border-color: rgba(255, 255, 255, 0.4);
  background: rgba(255, 255, 255, 0.16);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.lfs-pill:focus-visible {
  outline: 2px solid #fbbf24;
  outline-offset: 2px;
}

/* Selected — strong ring + fill */
.lfs-pill.is-active {
  transform: translateY(-1px);
  border-width: 2px;
  border-color: var(--pill-accent) !important;
  color: #fff !important;
  box-shadow:
    0 0 0 2px rgba(255, 255, 255, 0.25),
    0 0 0 4px color-mix(in srgb, var(--pill-accent) 45%, transparent),
    0 6px 16px rgba(0, 0, 0, 0.28);
}

.lfs-pill--cold.is-active {
  background: linear-gradient(135deg, rgba(148, 163, 184, 0.55) 0%, rgba(71, 85, 105, 0.65) 100%) !important;
}
.lfs-pill--warm.is-active {
  background: linear-gradient(135deg, rgba(251, 191, 36, 0.5) 0%, rgba(180, 83, 9, 0.55) 100%) !important;
}
.lfs-pill--hot.is-active {
  background: linear-gradient(135deg, rgba(248, 113, 113, 0.55) 0%, rgba(185, 28, 28, 0.6) 100%) !important;
}
.lfs-pill--answered.is-active {
  background: linear-gradient(135deg, rgba(74, 222, 128, 0.45) 0%, rgba(21, 128, 61, 0.55) 100%) !important;
}
.lfs-pill--no-answer.is-active {
  background: linear-gradient(135deg, rgba(251, 146, 60, 0.5) 0%, rgba(194, 65, 12, 0.55) 100%) !important;
}

.lfs-pill.is-active .lfs-pill__label {
  color: #fff;
  font-weight: 800;
}

.lfs-pill.is-active .lfs-pill__count {
  background: rgba(0, 0, 0, 0.35);
  border-color: rgba(255, 255, 255, 0.35);
  color: #fff;
  opacity: 1;
}

.lfs-active-banner {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
  padding: 6px 10px 6px 14px;
  border-radius: 999px;
  border: 1.5px solid rgba(251, 191, 36, 0.55);
  background: rgba(251, 191, 36, 0.15);
  flex-shrink: 0;
}

.lfs-active-banner__text {
  font-size: 12px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.9);
  white-space: nowrap;
}

.lfs-active-banner__text strong {
  color: #fde68a;
  font-weight: 800;
}

.lfs-pill--clear {
  border-style: solid;
  border-color: rgba(255, 255, 255, 0.35);
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  gap: 5px;
  padding: 7px 12px;
  font-size: 12px;
}

.lfs-pill--clear:hover {
  border-color: #fff;
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}

@media (max-width: 1200px) {
  .lfs-bar {
    overflow-x: auto;
    flex-wrap: nowrap;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 4px;
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
