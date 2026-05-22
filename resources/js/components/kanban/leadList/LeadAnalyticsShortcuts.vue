<template>
  <div class="lead-analytics-row" role="toolbar" aria-label="Lead analytics and shortcuts">
    <div class="lead-analytics-track">
      <button
        v-for="card in cardDefs"
        :key="card.key"
        type="button"
        class="lead-kpi-card"
        :class="[
          `lead-kpi-card--${card.tone}`,
          {
            'is-active': activeFilter === card.key || (card.key === 'total' && !activeFilter),
            'is-zero': !metrics[card.metricKey],
          },
        ]"
        :style="{ '--kpi-accent': card.accent }"
        :aria-pressed="card.key === 'total' ? !activeFilter : activeFilter === card.key"
        :title="card.label"
        @click="onCardClick(card.key)"
      >
        <span class="lead-kpi-card__glow" aria-hidden="true" />
        <span class="lead-kpi-card__accent" aria-hidden="true" />

        <span class="lead-kpi-card__icon" aria-hidden="true">
          <img
            :src="card.iconSrc"
            alt=""
            class="lead-kpi-card__img"
            width="20"
            height="20"
            loading="lazy"
            decoding="async"
          />
        </span>

        <span class="lead-kpi-card__content">
          <span class="lead-kpi-card__label">{{ card.shortLabel }}</span>
          <span class="lead-kpi-card__value">{{ formatValue(metrics[card.metricKey]) }}</span>
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
  {
    key: 'total',
    metricKey: 'total',
    label: 'Total Leads',
    shortLabel: 'Total Leads',
    iconSrc: `${LEAD_SHORTCUT_ICONS}/total-leads.svg`,
    tone: 'primary',
    accent: '#00A7FA',
  },
  {
    key: 'new_unassigned',
    metricKey: 'newUnassigned',
    label: 'New / Unassigned',
    shortLabel: 'New Leads',
    iconSrc: `${LEAD_SHORTCUT_ICONS}/new-leads.svg`,
    tone: 'new',
    accent: '#17C3B2',
  },
  {
    key: 'qualified',
    metricKey: 'qualified',
    label: 'Total Qualified',
    shortLabel: 'Qualified',
    iconSrc: `${LEAD_SHORTCUT_ICONS}/qualified.svg`,
    tone: 'qualified',
    accent: '#A5E835',
  },
  {
    key: 'follow_today',
    metricKey: 'followUpsToday',
    label: 'Follow-ups Today',
    shortLabel: 'Follow-ups',
    iconSrc: `${LEAD_SHORTCUT_ICONS}/follow-ups.svg`,
    tone: 'follow',
    accent: '#22C55E',
  },
  {
    key: 'cold',
    metricKey: 'cold',
    label: 'Cold Leads (No Action > 48h)',
    shortLabel: 'Cold Leads',
    iconSrc: `${LEAD_SHORTCUT_ICONS}/cold-leads.svg`,
    tone: 'cold',
    accent: '#F97316',
  },
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
  padding: 0 4px 4px;
  margin-bottom: 10px;
  width: 100%;
  box-sizing: border-box;
}

.lead-analytics-track {
  display: flex;
  flex-wrap: nowrap;
  align-items: stretch;
  gap: 8px;
  width: 100%;
  min-width: 0;
}

.lead-kpi-card {
  --kpi-accent: #733e87;
  position: relative;
  flex: 1 1 0;
  min-width: 118px;
  max-width: 200px;
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 10px;
  min-height: 72px;
  margin: 0;
  padding: 10px 12px 10px 14px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: linear-gradient(
    145deg,
    rgba(255, 255, 255, 0.12) 0%,
    rgba(11, 7, 54, 0.55) 48%,
    rgba(115, 62, 135, 0.42) 100%
  );
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow:
    0 1px 2px rgba(11, 7, 54, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.08);
  color: #fff;
  cursor: pointer;
  overflow: hidden;
  text-align: left;
  font-family: Montserrat, Inter, system-ui, sans-serif;
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease;
  -webkit-appearance: none;
  appearance: none;
}

.lead-kpi-card__glow {
  position: absolute;
  inset: 0;
  background: radial-gradient(
    120px 80px at 0% 100%,
    color-mix(in srgb, var(--kpi-accent) 35%, transparent),
    transparent 70%
  );
  opacity: 0.55;
  pointer-events: none;
  transition: opacity 0.18s ease;
}

.lead-kpi-card__accent {
  position: absolute;
  left: 0;
  top: 10px;
  bottom: 10px;
  width: 3px;
  border-radius: 0 4px 4px 0;
  background: var(--kpi-accent);
  box-shadow: 0 0 12px color-mix(in srgb, var(--kpi-accent) 65%, transparent);
}

.lead-kpi-card__icon {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--kpi-accent) 22%, rgba(255, 255, 255, 0.06));
  border: 1px solid color-mix(in srgb, var(--kpi-accent) 40%, transparent);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.lead-kpi-card__img {
  display: block;
  width: 20px;
  height: 20px;
  object-fit: contain;
  pointer-events: none;
}

.lead-kpi-card__content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 4px;
  min-width: 0;
}

.lead-kpi-card__label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.72);
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.lead-kpi-card__value {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
  color: #fff;
  line-height: 1;
}

.lead-kpi-card.is-zero .lead-kpi-card__value {
  color: rgba(255, 255, 255, 0.55);
}

.lead-kpi-card:hover {
  transform: translateY(-1px);
  border-color: color-mix(in srgb, var(--kpi-accent) 45%, rgba(255, 255, 255, 0.2));
  box-shadow:
    0 4px 14px rgba(11, 7, 54, 0.28),
    inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.lead-kpi-card:hover .lead-kpi-card__glow {
  opacity: 0.85;
}

.lead-kpi-card:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--kpi-accent) 70%, #fff);
  outline-offset: 2px;
}

.lead-kpi-card.is-active {
  border-color: color-mix(in srgb, var(--kpi-accent) 55%, rgba(255, 255, 255, 0.35));
  background: linear-gradient(
    145deg,
    rgba(255, 255, 255, 0.18) 0%,
    rgba(11, 7, 54, 0.62) 45%,
    color-mix(in srgb, var(--kpi-accent) 28%, rgba(115, 62, 135, 0.5)) 100%
  );
  box-shadow:
    0 0 0 1px color-mix(in srgb, var(--kpi-accent) 40%, transparent),
    0 6px 18px rgba(11, 7, 54, 0.32),
    inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.lead-kpi-card.is-active .lead-kpi-card__glow {
  opacity: 1;
}

.lead-kpi-card.is-active .lead-kpi-card__label {
  color: rgba(255, 255, 255, 0.92);
}

.lead-kpi-card.is-active .lead-kpi-card__value {
  color: #fff;
}

@media (max-width: 1024px) and (min-width: 769px) {
  .lead-analytics-track {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }

  .lead-kpi-card {
    max-width: none;
    width: 100%;
  }
}

@media (max-width: 768px) {
  .lead-analytics-row {
    padding: 2px 6px 4px;
    margin-bottom: 8px;
    overflow: hidden;
  }

  .lead-analytics-track {
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

  .lead-kpi-card {
    flex: 0 0 auto;
    scroll-snap-align: start;
    min-width: 128px;
    max-width: 148px;
    min-height: 68px;
    padding: 8px 10px 8px 12px;
  }

  .lead-kpi-card__icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
  }

  .lead-kpi-card__img {
    width: 18px;
    height: 18px;
  }

  .lead-kpi-card__value {
    font-size: 20px;
  }

  .lead-kpi-card__label {
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

  .lead-kpi-card {
    width: 100%;
    max-width: none;
    min-width: 0;
  }
}
</style>
