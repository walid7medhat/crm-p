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
            :title="chip.hint || chip.label"
            @click="onChipClick(chip.key)"
          >
            <iconify-icon
              v-if="chip.icon"
              :icon="chip.icon"
              class="lfs-pill__icon"
              width="13"
              height="13"
              aria-hidden="true"
            />
            <span class="lfs-pill__label">{{ chip.label }}</span>
            <span class="lfs-pill__count">{{ formatValue(metrics[chip.metricKey]) }}</span>
          </button>
        </div>
      </div>

      <button
        v-if="activeFilter"
        type="button"
        class="lfs-pill lfs-pill--clear"
        aria-label="Clear active filter"
        @click="onChipClick(null)"
      >
        <iconify-icon icon="lucide:x" width="13" height="13" aria-hidden="true" />
        Clear
      </button>
    </div>
  </section>
</template>

<script setup>
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
  margin-bottom: 10px;
  padding: 0 4px;
  box-sizing: border-box;
  font-family: Montserrat, Inter, system-ui, sans-serif;
}

.lfs-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 4px;
  width: 100%;
  min-width: 0;
}

.lfs-segment {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex-shrink: 0;
}

.lfs-divider {
  width: 1px;
  height: 22px;
  background: rgba(255, 255, 255, 0.18);
  flex-shrink: 0;
  margin: 0 4px;
}

.lfs-segment__label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.45);
  white-space: nowrap;
  flex-shrink: 0;
}

.lfs-pills {
  display: inline-flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 5px;
  min-width: 0;
}

.lfs-pills--segmented {
  gap: 0;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  overflow: hidden;
}

.lfs-pills--segmented .lfs-pill {
  border-radius: 0;
  border: none;
  border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.lfs-pills--segmented .lfs-pill:last-child {
  border-right: none;
}

.lfs-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin: 0;
  padding: 6px 11px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: transparent;
  color: rgba(255, 255, 255, 0.88);
  font-size: 11px;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  cursor: pointer;
  transition:
    color 0.15s ease,
    border-color 0.15s ease,
    background 0.15s ease;
  -webkit-appearance: none;
  appearance: none;
}

.lfs-pill__icon {
  flex-shrink: 0;
  opacity: 0.9;
}

.lfs-pill__label {
  color: rgba(255, 255, 255, 0.82);
}

.lfs-pill__count {
  font-size: 11px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  padding: 1px 6px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  min-width: 1.25em;
  text-align: center;
}

.lfs-pill.is-zero .lfs-pill__count {
  opacity: 0.5;
}

.lfs-pill:hover {
  border-color: rgba(255, 255, 255, 0.28);
  color: #fff;
}

.lfs-pills--segmented .lfs-pill:hover {
  background: rgba(255, 255, 255, 0.06);
}

.lfs-pill:focus-visible {
  outline: 2px solid rgba(245, 158, 11, 0.8);
  outline-offset: 2px;
}

/* Active states */
.lfs-pill--cold.is-active {
  background: rgba(148, 163, 184, 0.25);
  border-color: #94a3b8;
  color: #e2e8f0;
}
.lfs-pill--warm.is-active {
  background: rgba(251, 191, 36, 0.2);
  border-color: #fbbf24;
  color: #fde68a;
}
.lfs-pill--hot.is-active {
  background: rgba(248, 113, 113, 0.22);
  border-color: #f87171;
  color: #fecaca;
}
.lfs-pill--answered.is-active {
  background: rgba(74, 222, 128, 0.18);
  border-color: #4ade80;
  color: #bbf7d0;
}
.lfs-pill--no-answer.is-active {
  background: rgba(251, 146, 60, 0.2);
  border-color: #fb923c;
  color: #fed7aa;
}
.lfs-pill--live-in.is-active,
.lfs-pill--short-term.is-active,
.lfs-pill--long-term.is-active,
.lfs-pill--unassigned.is-active,
.lfs-pill--high-score.is-active,
.lfs-pill--rent.is-active,
.lfs-pill--sale.is-active {
  background: rgba(124, 92, 191, 0.28);
  border-color: rgba(196, 181, 253, 0.65);
  color: #fff;
}

.lfs-pills--segmented .lfs-pill.is-active {
  background: rgba(255, 255, 255, 0.12);
}

.lfs-pill--clear {
  margin-left: auto;
  border-style: dashed;
  border-color: rgba(252, 211, 77, 0.45);
  color: #fcd34d;
  gap: 4px;
}

.lfs-pill--clear:hover {
  border-color: rgba(252, 211, 77, 0.75);
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
