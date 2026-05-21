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
        <span class="lead-analytics-card__label">{{ card.shortLabel }}</span>
        <span class="lead-analytics-card__row">
          <span
            class="lead-analytics-card__icon"
            :style="{ background: card.iconBg }"
            aria-hidden="true"
          >
            <img
              :src="card.iconSrc"
              alt=""
              class="lead-analytics-card__img"
              width="22"
              height="22"
              loading="lazy"
              decoding="async"
            />
          </span>
          <span class="lead-analytics-card__value">{{ formatValue(metrics[card.metricKey]) }}</span>
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

const LEAD_SHORTCUT_ICONS = '/assets/images/kanban/lead-shortcuts'

const cardDefs = [
  { key: 'total', metricKey: 'total', label: 'Total Leads', shortLabel: 'Total Leads', iconSrc: `${LEAD_SHORTCUT_ICONS}/total-leads.svg`, tone: 'primary', iconBg: '#00A7FA' },
  { key: 'new_unassigned', metricKey: 'newUnassigned', label: 'New / Unassigned', shortLabel: 'New Leads', iconSrc: `${LEAD_SHORTCUT_ICONS}/new-leads.svg`, tone: 'new', iconBg: '#17C3B2' },
  { key: 'qualified', metricKey: 'qualified', label: 'Total Qualified', shortLabel: 'Qualified', iconSrc: `${LEAD_SHORTCUT_ICONS}/qualified.svg`, tone: 'qualified', iconBg: '#A5E835' },
  { key: 'follow_today', metricKey: 'followUpsToday', label: 'Follow-ups Today', shortLabel: 'Follow-ups', iconSrc: `${LEAD_SHORTCUT_ICONS}/follow-ups.svg`, tone: 'follow', iconBg: '#22C55E' },
  { key: 'cold', metricKey: 'cold', label: 'Cold Leads (No Action > 48h)', shortLabel: 'Cold Leads', iconSrc: `${LEAD_SHORTCUT_ICONS}/cold-leads.svg`, tone: 'cold', iconBg: '#F97316' },
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
  padding: 0 6px 2px;
  margin-bottom: 12px;
  width: 100%;
  box-sizing: border-box;
}

.lead-analytics-track {
  display: flex;
  flex-wrap: nowrap;
  align-items: stretch;
  justify-content: flex-start;
  gap: 10px;
  width: 100%;
  min-width: 0;
}

.lead-analytics-card {
  flex: 1 1 0;
  min-width: 132px;
  max-width: 180px;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 80px;
  margin: 0;
  padding: 12px 14px;
  border-radius: 14px;
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
  text-align: center;
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

.lead-analytics-card__row {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
}

.lead-analytics-card__icon {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  line-height: 0;
  box-shadow: 0 1px 2px rgba(11, 7, 54, 0.18);
}

.lead-analytics-card__img {
  display: block;
  width: 22px;
  height: 22px;
  object-fit: contain;
  pointer-events: none;
}

.lead-analytics-card__value {
  flex-shrink: 0;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -0.02em;
  color: #fff;
  line-height: 1;
}

.lead-analytics-card__label {
  font-size: 14px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.92);
  line-height: 1.2;
  text-align: center;
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.lead-analytics-card--primary.is-active {
  border-color: rgba(0, 167, 250, 0.55);
}

.lead-analytics-card--new.is-active {
  border-color: rgba(23, 195, 178, 0.55);
}

.lead-analytics-card--qualified.is-active {
  border-color: rgba(165, 232, 53, 0.55);
}

.lead-analytics-card--follow.is-active {
  border-color: rgba(34, 197, 94, 0.55);
}

.lead-analytics-card--cold.is-active {
  border-color: rgba(249, 115, 22, 0.55);
}

@media (max-width: 1024px) and (min-width: 769px) {
  .lead-analytics-track {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }

  .lead-analytics-card {
    width: 100%;
    max-width: none;
  }
}

@media (max-width: 768px) {
  .lead-analytics-row {
    padding: 4px 8px 6px;
    margin-bottom: 10px;
    overflow: hidden;
  }

  .lead-analytics-track {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 2px;
    align-items: stretch;
  }

  .lead-analytics-track::-webkit-scrollbar {
    display: none;
  }

  .lead-analytics-card {
    flex: 0 0 auto;
    scroll-snap-align: start;
    min-width: 130px;
    min-height: 90px;
    max-width: 160px;
    padding: 10px 12px;
  }

  .lead-analytics-card__icon {
    width: 32px;
    height: 32px;
  }

  .lead-analytics-card__img {
    width: 25px;
    height: 25px;
  }

  .lead-analytics-card__value {
    font-size: 20px;
  }

  .lead-analytics-card__label {
    font-size: 11px;
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
    max-width: none;
    min-height: 86px;
  }
}
</style>
