<template>
  <div class="kbs" role="status" aria-live="polite" :aria-label="label">
    <div
      v-for="(col, index) in shownColumns"
      :key="index"
      class="kbs-col"
    >
      <div class="kbs-head" :style="col.color ? { background: col.color } : null">
        <span v-if="col.title" class="kbs-head__title">{{ col.title }}</span>
        <span v-else class="kbs-head__bar"></span>
      </div>
      <div class="kbs-body">
        <div v-for="n in cards" :key="n" class="kbs-card">
          <span class="kbs-line kbs-line--title"></span>
          <span class="kbs-line"></span>
          <span class="kbs-line kbs-line--short"></span>
        </div>
      </div>
    </div>
    <span class="kbs-sr">{{ label }}…</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Board-shaped loading placeholder (columns + shimmering cards). Self-contained styles so it
 * can render as an async component's loadingComponent before the board chunk has loaded.
 */
const props = defineProps({
  label: { type: String, default: 'Loading' },
  // Either a number of columns or [{ title, color }] (e.g. stage names from a previous visit).
  columns: { type: [Number, Array], default: 6 },
  cards: { type: Number, default: 4 },
})

const shownColumns = computed(() =>
  Array.isArray(props.columns) && props.columns.length
    ? props.columns
    : Array.from({ length: Number(props.columns) || 6 }, () => ({ title: '', color: '' })),
)
</script>

<style scoped>
.kbs {
  display: flex;
  gap: 14px;
  width: 100%;
  min-height: calc(100dvh - 220px);
  padding: 4px 2px 12px;
  overflow: hidden;
}
.kbs-col {
  flex: 0 0 280px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 8px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.35);
  border: 1px solid rgba(30, 27, 46, 0.06);
}
.kbs-head {
  display: flex;
  align-items: center;
  height: 40px;
  padding: 0 16px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.75);
  border: 1px solid rgba(30, 27, 46, 0.08);
}
.kbs-head__title {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #1e1b2e;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kbs-head__bar {
  display: block;
  width: 46%;
  height: 10px;
  border-radius: 999px;
  background: rgba(15, 15, 18, 0.16);
}
.kbs-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.kbs-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  height: 140px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid rgba(30, 27, 46, 0.08);
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.5) 0%, rgba(255, 255, 255, 0.95) 50%, rgba(255, 255, 255, 0.5) 100%);
  background-size: 200% 100%;
  animation: kbs-shimmer 1.2s ease-in-out infinite;
}
.kbs-line {
  display: block;
  height: 9px;
  width: 80%;
  border-radius: 999px;
  background: rgba(15, 15, 18, 0.08);
}
.kbs-line--title {
  height: 12px;
  width: 65%;
  background: rgba(15, 15, 18, 0.13);
}
.kbs-line--short {
  width: 45%;
}
.kbs-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
@keyframes kbs-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}
@media (max-width: 768px) {
  .kbs-col { flex-basis: 82vw; }
}
@media (prefers-reduced-motion: reduce) {
  .kbs-card { animation: none; }
}
</style>
