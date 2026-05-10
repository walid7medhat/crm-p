<template>
  <article :id="anchorId" class="mpc" :class="'mpc-tone-' + tone">
    <header class="mpc-head">
      <div class="mpc-icon-wrap">
        <iconify-icon :icon="icon" class="mpc-icon" />
      </div>
      <div class="mpc-titles">
        <div class="mpc-title" role="heading" aria-level="2">{{ title }}</div>
        <div class="mpc-sub">{{ subtitle }}</div>
      </div>
      <div class="mpc-badges">
        <span v-for="b in badges" :key="b" class="mpc-badge">{{ b }}</span>
      </div>
    </header>

    <div class="mpc-kpis">
      <div
        v-for="k in kpis"
        :key="k.label"
        class="mpc-kpi"
      >
        <span class="mpc-kpi-lab">{{ k.label }}</span>
        <span class="mpc-kpi-val">{{ k.value }}</span>
        <span
          v-if="k.delta"
          class="mpc-kpi-delta"
          :class="{ up: k.up === true, down: k.up === false, flat: k.up === null }"
        >
          {{ k.delta }}
        </span>
      </div>
    </div>

    <div class="mpc-section">
      <span class="mpc-sec-title">{{ titleFlow }}</span>
      <div class="mpc-flow">
        <template v-for="(step, i) in microFlow" :key="step.key">
          <div class="mpc-step">
            <span class="mpc-step-ix">{{ i + 1 }}</span>
            <span class="mpc-step-lab">{{ step.label }}</span>
          </div>
          <div v-if="i < microFlow.length - 1" class="mpc-flow-chev" aria-hidden="true">
            <iconify-icon icon="lucide:chevron-right" />
          </div>
        </template>
      </div>
    </div>

    <div class="mpc-section">
      <span class="mpc-sec-title">{{ titleActions }}</span>
      <div class="mpc-chips">
        <span v-for="a in actions" :key="a" class="mpc-chip">{{ a }}</span>
      </div>
    </div>

    <div class="mpc-section">
      <span class="mpc-sec-title">{{ titleDeps }}</span>
      <ul class="mpc-deps">
        <li v-for="d in dependencies" :key="d.target + d.relation">
          <span class="mpc-dep-to">{{ depLabel(d.target) }}</span>
          <span class="mpc-dep-rel">{{ d.relation }}</span>
        </li>
      </ul>
    </div>

    <div class="mpc-slot">
      <slot />
    </div>
  </article>
</template>

<script setup>
import { computed, inject } from 'vue'

const soI18n = inject('soI18n', null)

const titleFlow = computed(
  () => soI18n?.t('mpc.moduleFlow') ?? 'Module flow'
)
const titleActions = computed(
  () => soI18n?.t('mpc.keyActions') ?? 'Key actions'
)
const titleDeps = computed(
  () => soI18n?.t('mpc.dependencies') ?? 'Dependencies'
)

defineProps({
  anchorId: { type: String, required: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: 'lucide:box' },
  badges: { type: Array, default: () => [] },
  tone: { type: String, default: 'amber' },
  kpis: { type: Array, default: () => [] },
  microFlow: { type: Array, default: () => [] },
  actions: { type: Array, default: () => [] },
  dependencies: { type: Array, default: () => [] },
})

const depNames = {
  listings: 'Listings',
  leads: 'Leads',
  deals: 'Deals',
  intelligence: 'Intelligence',
}

function depLabel(target) {
  const key = `mpc.depTargets.${target}`
  if (soI18n) {
    const tr = soI18n.t(key)
    if (tr !== key) return tr
  }
  return depNames[target] || target
}
</script>

<style scoped>
.mpc {
  border-radius: 20px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: linear-gradient(165deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 20px 40px -20px rgba(15, 23, 42, 0.1);
  margin-bottom: 24px;
  scroll-margin-top: 100px;
  overflow: hidden;
  position: relative;
}
.mpc::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--mpc-accent, #6366f1);
  opacity: 0.9;
}
.mpc-tone-amber {
  --mpc-accent: linear-gradient(90deg, #f59e0b, #fbbf24);
}
.mpc-tone-violet {
  --mpc-accent: linear-gradient(90deg, #8b5cf6, #a78bfa);
}
.mpc-tone-emerald {
  --mpc-accent: linear-gradient(90deg, #10b981, #34d399);
}
.mpc-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}
.mpc-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.04);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}
.mpc-icon {
  font-size: 15px;
  color: #0f172a;
}
.mpc-titles {
  flex: 1;
  min-width: 160px;
}
.mpc-title {
  margin: 0 0 3px;
  font-size: 15px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #0f172a;
}
.mpc-sub {
  margin: 0;
  font-size: 11px;
  line-height: 1.4;
  color: #64748b;
}
.mpc-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.mpc-badge {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.06);
  color: #475569;
}

.mpc-kpis {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;
  background: rgba(15, 23, 42, 0.08);
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}
.mpc-kpi {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px 16px;
  background: rgba(255, 255, 255, 0.85);
}
.mpc-kpi-lab {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
}
.mpc-kpi-val {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #0f172a;
}
.mpc-kpi-delta {
  font-size: 11px;
  font-weight: 700;
}
.mpc-kpi-delta.up {
  color: #059669;
}
.mpc-kpi-delta.down {
  color: #dc2626;
}
.mpc-kpi-delta.flat {
  color: #94a3b8;
}

.mpc-section {
  padding: 16px 22px 0;
}
.mpc-sec-title {
  display: block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #94a3b8;
  margin-bottom: 10px;
}

.mpc-flow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 4px;
}
.mpc-step {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  background: rgba(99, 102, 241, 0.06);
  border: 1px solid rgba(99, 102, 241, 0.12);
}
.mpc-step-ix {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  background: #4f46e5;
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
}
.mpc-step-lab {
  font-size: 11px;
  font-weight: 700;
  color: #312e81;
}
.mpc-flow-chev {
  color: #cbd5e1;
  display: flex;
  align-items: center;
}

.mpc-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.mpc-chip {
  font-size: 11px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
  color: #334155;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.mpc-deps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.mpc-deps li {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.03);
  border: 1px solid rgba(15, 23, 42, 0.06);
}
.mpc-dep-to {
  font-size: 11px;
  font-weight: 800;
  color: #4f46e5;
}
.mpc-dep-rel {
  font-size: 11px;
  color: #64748b;
  line-height: 1.4;
}

.mpc-slot {
  padding: 12px 16px 16px;
}

@media (max-width: 720px) {
  .mpc-kpis {
    grid-template-columns: 1fr;
  }
}
</style>
