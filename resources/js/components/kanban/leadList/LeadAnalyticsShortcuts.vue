<template>
  <div class="lead-analytics-row" role="toolbar" aria-label="Lead analytics and shortcuts">
    <div class="lead-analytics-track">
      <button
        v-for="card in cardDefs"
        :key="card.key"
        type="button"
        class="lead-analytics-card"
        :class="[
          `lead-analytics-card--${card.tone}`,
          { 'is-active': activeFilter === card.key },
        ]"
        :aria-pressed="activeFilter === card.key"
        :title="card.label"
        @click="onCardClick(card.key)"
      >
        <span class="lead-analytics-card__icon" aria-hidden="true">
          <iconify-icon :icon="card.icon" />
        </span>
        <span class="lead-analytics-card__line">
          <span class="lead-analytics-card__value">{{ formatValue(metrics[card.metricKey]) }}</span>
          <span class="lead-analytics-card__label">{{ card.shortLabel }}</span>
        </span>
      </button>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  metrics: {
    type: Object,
    default: () => ({
      total: 0,
      newUnassigned: 0,
      qualified: 0,
      followUpsToday: 0,
      cold: 0,
    }),
  },
  activeFilter: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['toggle-filter'])

const cardDefs = [
  { key: 'total', metricKey: 'total', label: 'Total Leads', shortLabel: 'Total Leads', icon: 'lucide:folder', tone: 'primary' },
  { key: 'new_unassigned', metricKey: 'newUnassigned', label: 'New / Unassigned', shortLabel: 'New Leads', icon: 'lucide:user-plus', tone: 'warning' },
  { key: 'qualified', metricKey: 'qualified', label: 'Total Qualified', shortLabel: 'Qualified', icon: 'lucide:circle-check', tone: 'success' },
  { key: 'follow_today', metricKey: 'followUpsToday', label: 'Follow-ups Today', shortLabel: 'Follow-ups', icon: 'lucide:clock', tone: 'urgent' },
  { key: 'cold', metricKey: 'cold', label: 'Cold Leads (No Action > 48h)', shortLabel: 'Cold Leads', icon: 'lucide:snowflake', tone: 'muted' },
]

function formatValue(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0'
  return n.toLocaleString()
}

function onCardClick(key) {
  if (key === 'total') {
    emit('toggle-filter', null)
    return
  }
  emit('toggle-filter', props.activeFilter === key ? null : key)
}
</script>

<style scoped>
.lead-analytics-row {
  flex-shrink: 0;
  padding: 2px 10px 4px;
  width: 100%;
  box-sizing: border-box;
}

.lead-analytics-track {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  width: 100%;
  min-width: 0;
}

.lead-analytics-card {
  flex: 0 0 auto;
  width: auto;
  max-width: max-content;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 32px;
  margin: 0;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: linear-gradient(
    135deg,
    rgba(11, 7, 54, 0.72) 0%,
    rgba(115, 62, 135, 0.58) 100%
  );
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow: 0 1px 3px rgba(11, 7, 54, 0.2);
  color: #fff;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
  font-family: Montserrat, Inter, system-ui, sans-serif;
  -webkit-appearance: none;
  appearance: none;
}

.lead-analytics-card:hover {
  border-color: rgba(255, 255, 255, 0.38);
  filter: brightness(1.06);
}

.lead-analytics-card:focus-visible {
  outline: 2px solid var(--crm-secondary, #733e87);
  outline-offset: 1px;
}

.lead-analytics-card.is-active {
  border-color: rgba(255, 255, 255, 0.55);
  box-shadow: 0 0 0 1px rgba(115, 62, 135, 0.65), 0 2px 6px rgba(11, 7, 54, 0.25);
  filter: brightness(1.1);
}

.lead-analytics-card__icon {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  font-size: 12px;
  line-height: 1;
}

.lead-analytics-card__line {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
  line-height: 1;
}

.lead-analytics-card__value {
  flex-shrink: 0;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #fff;
}

.lead-analytics-card__label {
  font-size: 12px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.92);
}

/* Icon accents */
.lead-analytics-card--primary .lead-analytics-card__icon {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
}

.lead-analytics-card--warning .lead-analytics-card__icon {
  background: rgba(251, 191, 36, 0.35);
  color: #fde68a;
}

.lead-analytics-card--warning.is-active {
  border-color: rgba(251, 191, 36, 0.55);
}

.lead-analytics-card--success .lead-analytics-card__icon {
  background: rgba(52, 211, 153, 0.28);
  color: #a7f3d0;
}

.lead-analytics-card--urgent .lead-analytics-card__icon {
  background: rgba(56, 189, 248, 0.28);
  color: #bae6fd;
}

.lead-analytics-card--muted {
  background: linear-gradient(
    135deg,
    rgba(11, 7, 54, 0.55) 0%,
    rgba(71, 85, 105, 0.45) 100%
  );
}

.lead-analytics-card--muted .lead-analytics-card__icon {
  background: rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.65);
}

.lead-analytics-card--muted .lead-analytics-card__value,
.lead-analytics-card--muted .lead-analytics-card__label {
  color: rgba(255, 255, 255, 0.72);
}

@media (max-width: 1024px) and (min-width: 769px) {
  .lead-analytics-track {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }

  .lead-analytics-card {
    width: 100%;
    max-width: none;
  }
}

@media (max-width: 768px) {
  .lead-analytics-row {
    padding: 4px 8px 6px;
    overflow: hidden;
  }

  .lead-analytics-track {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 2px;
  }

  .lead-analytics-track::-webkit-scrollbar {
    display: none;
  }

  .lead-analytics-card {
    flex: 0 0 auto;
    scroll-snap-align: start;
    min-height: 40px;
    height: 40px;
    padding: 0 10px;
    max-width: none;
  }

  .lead-analytics-card__value {
    font-size: 13px;
  }

  .lead-analytics-card__label {
    font-size: 10px;
  }
}

@media (max-width: 380px) {
  .lead-analytics-track {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow-x: visible;
    scroll-snap-type: none;
  }

  .lead-analytics-card {
    width: 100%;
    min-height: 38px;
    height: 38px;
  }
}
</style>
