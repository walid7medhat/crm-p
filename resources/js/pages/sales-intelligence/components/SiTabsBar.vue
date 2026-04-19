<template>
  <nav class="tabs" aria-label="Sales intelligence sections">
    <button
      v-for="t in items"
      :key="t.id"
      type="button"
      class="tabs__btn"
      :class="{
        'tabs__btn--active': modelValue === t.id,
        'tabs__btn--dim': modelValue !== t.id,
      }"
      @click="$emit('update:modelValue', t.id)"
    >
      {{ t.label }}
    </button>
  </nav>
</template>

<script setup>
defineProps({
  modelValue: { type: String, required: true },
})

defineEmits(['update:modelValue'])

const items = [
  { id: 'overview', label: 'Overview' },
  { id: 'agents', label: 'Agents' },
  { id: 'rules', label: 'Rules' },
  { id: 'distribution', label: 'Distribution' },
  { id: 'ai', label: 'AI mode' },
  { id: 'logs', label: 'Logs' },
]
</script>

<style scoped>
.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
  padding: 0 2px 8px;
  border-bottom: 1px solid #e5e7eb;
  margin-bottom: 8px;
}

.tabs__btn {
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 12px;
  font-weight: 500;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition:
    color var(--si-ease, 0.16s ease),
    background var(--si-ease, 0.16s ease),
    opacity var(--si-ease, 0.16s ease),
    transform var(--si-ease, 0.16s ease);
}

.tabs__btn--dim {
  opacity: 0.52;
  background: transparent;
}

.tabs__btn:hover {
  color: #111827;
  background: #f3f4f6;
  opacity: 1;
}

.tabs__btn:focus-visible {
  outline: none;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
}

.tabs__btn--active {
  color: #111827;
  background: #f3f4f6;
  box-shadow: inset 0 -2px 0 #111827;
  opacity: 1;
}
</style>
