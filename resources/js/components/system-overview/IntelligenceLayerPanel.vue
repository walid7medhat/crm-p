<template>
  <section class="ilp">
    <header class="ilp-head">
      <div>
        <div class="ilp-title" role="heading" aria-level="2">{{ title }}</div>
        <div class="ilp-sub">{{ subtitle }}</div>
      </div>
      <span class="ilp-pill">{{ pill }}</span>
    </header>
    <div class="ilp-grid">
      <article
        v-for="eng in engines"
        :key="eng.id"
        class="ilp-card"
        :style="{ '--accent': eng.accent }"
      >
        <div class="ilp-card-top">
          <div class="ilp-ic-wrap">
            <iconify-icon :icon="eng.icon" class="ilp-ic" />
          </div>
          <div class="ilp-feeds">
            <span v-for="f in eng.feeds" :key="f" class="ilp-feed">{{ f }}</span>
          </div>
        </div>
        <div class="ilp-card-title" role="heading" aria-level="3">{{ eng.title }}</div>
        <div class="ilp-card-sub">{{ eng.subtitle }}</div>
        <div class="ilp-card-sum">{{ eng.summary }}</div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { intelligenceEngines } from '@/data/systemOverviewPresentation.js'

const props = defineProps({
  title: { type: String, default: 'System intelligence layer' },
  subtitle: {
    type: String,
    default: 'Automation & decisioning that connects inventory to revenue',
  },
  pill: { type: String, default: 'AI + Rules' },
  enginesOverride: { type: Array, default: null },
})

const engines = computed(() => props.enginesOverride ?? intelligenceEngines)
</script>

<style scoped>
.ilp {
  margin-bottom: 16px;
}
.ilp-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 18px;
}
.ilp-title {
  margin: 0 0 3px;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #0f172a;
}
.ilp-sub {
  margin: 0;
  font-size: 11px;
  color: #64748b;
  line-height: 1.4;
}
.ilp-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 999px;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
  color: #4f46e5;
  white-space: nowrap;
}
.ilp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
}
.ilp-card {
  padding: 12px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.07);
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
  transition:
    box-shadow 0.2s,
    transform 0.2s;
  position: relative;
  overflow: hidden;
}
.ilp-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--accent, #6366f1);
  opacity: 0.85;
}
.ilp-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.1);
}
.ilp-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 10px;
}
.ilp-ic-wrap {
  width: 32px;
  height: 32px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--accent) 12%, white);
}
.ilp-ic {
  font-size: 15px;
  color: var(--accent);
}
.ilp-feeds {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: flex-end;
}
.ilp-feed {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(15, 23, 42, 0.06);
  color: #475569;
}
.ilp-card-title {
  margin: 0 0 2px;
  font-size: 12px;
  font-weight: 800;
  color: #0f172a;
}
.ilp-card-sub {
  margin: 0 0 6px;
  font-size: 10px;
  font-weight: 600;
  color: #64748b;
}
.ilp-card-sum {
  margin: 0;
  font-size: 11px;
  line-height: 1.45;
  color: #475569;
}
</style>
