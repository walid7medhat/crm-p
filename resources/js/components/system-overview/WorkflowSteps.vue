<template>
  <ol class="workflow">
    <li v-for="(step, i) in steps" :key="i" class="workflow-step">
      <div class="step-index">{{ demoPrefix }}{{ i + 1 }}</div>
      <div class="step-body">
        <div class="step-title">{{ step.title }}</div>
        <div class="step-desc">{{ step.body }}</div>
      </div>
    </li>
  </ol>
</template>

<script setup>
defineProps({
  steps: { type: Array, default: () => [] },
  demoPrefix: { type: String, default: '' },
})
</script>

<style scoped>
.workflow {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.workflow-step {
  display: flex;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px dashed rgba(15, 23, 42, 0.08);
}
.workflow-step:last-child {
  border-bottom: none;
}
.step-index {
  flex-shrink: 0;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(145deg, #4f46e5, #7c3aed);
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.step-body {
  flex: 1;
  min-width: 0;
}
.step-title {
  font-weight: 600;
  font-size: 12px;
  color: var(--so-text, #0f172a);
  margin-bottom: 3px;
}
.step-desc {
  margin: 0;
  font-size: 11px;
  line-height: 1.45;
  color: var(--so-muted, #64748b);
}
</style>
