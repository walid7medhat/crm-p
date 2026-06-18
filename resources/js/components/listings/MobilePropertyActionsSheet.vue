<template>
  <div class="ps-actions-sheet" role="dialog" aria-modal="true" aria-label="Property actions">
    <button type="button" class="ps-actions-sheet__backdrop" aria-label="Close actions" @click="emit('close')" />
    <div class="ps-actions-sheet__panel">
      <div class="ps-actions-sheet__head">
        <h2 class="ps-actions-sheet__title">Property Actions</h2>
        <button type="button" class="ps-actions-sheet__close" aria-label="Close" @click="emit('close')">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="ps-actions-sheet__body">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
const emit = defineEmits(['close'])
</script>
