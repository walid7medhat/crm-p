<template>
  <ul class="feature-list">
    <li v-for="(item, i) in items" :key="i" class="feature-item">
      <span class="bullet" aria-hidden="true" />
      <span>{{ item }}</span>
    </li>
  </ul>
</template>

<script setup>
defineProps({
  items: { type: Array, default: () => [] },
})
</script>

<style scoped>
.feature-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.feature-item {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 11px;
  line-height: 1.45;
  color: var(--so-text, #0f172a);
}
.bullet {
  flex-shrink: 0;
  width: 6px;
  height: 6px;
  margin-top: 8px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
</style>
