<template>
  <article :id="anchorId" class="module-card" :class="`tone-${tone}`">
    <header class="mc-head">
      <div class="mc-icon-wrap">
        <iconify-icon :icon="icon" class="mc-icon" />
      </div>
      <div class="mc-titles">
        <div class="mc-title" role="heading" aria-level="2">{{ title }}</div>
        <div class="mc-sub">{{ subtitle }}</div>
      </div>
      <div class="mc-badges">
        <span v-for="b in badges" :key="b" class="mc-badge">{{ b }}</span>
      </div>
    </header>
    <div class="mc-body">
      <slot />
    </div>
  </article>
</template>

<script setup>
defineProps({
  anchorId: { type: String, required: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: 'lucide:box' },
  badges: { type: Array, default: () => [] },
  tone: { type: String, default: 'slate' }, // amber | violet | emerald | slate
})
</script>

<style scoped>
.module-card {
  border-radius: 20px;
  border: 1px solid var(--so-border, rgba(15, 23, 42, 0.08));
  background: linear-gradient(
    165deg,
    rgba(255, 255, 255, 0.95) 0%,
    rgba(248, 250, 252, 0.92) 100%
  );
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 24px 48px -24px rgba(15, 23, 42, 0.12);
  margin-bottom: 28px;
  scroll-margin-top: 100px;
  animation: card-in 0.5s ease both;
}
@keyframes card-in {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.tone-amber {
  --mc-accent: #f59e0b;
  --mc-accent-soft: rgba(245, 158, 11, 0.12);
}
.tone-violet {
  --mc-accent: #8b5cf6;
  --mc-accent-soft: rgba(139, 92, 246, 0.12);
}
.tone-emerald {
  --mc-accent: #10b981;
  --mc-accent-soft: rgba(16, 185, 129, 0.12);
}
.mc-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}
.mc-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--mc-accent-soft, rgba(99, 102, 241, 0.12));
  color: var(--mc-accent, #6366f1);
}
.mc-icon {
  font-size: 15px;
}
.mc-titles {
  flex: 1;
  min-width: 200px;
}
.mc-title {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #0f172a;
}
.mc-sub {
  margin: 0;
  font-size: 11px;
  line-height: 1.45;
  color: #64748b;
}
.mc-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-start;
}
.mc-badge {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.06);
  color: #475569;
}
.mc-body {
  padding: 12px 16px 16px;
}
</style>
