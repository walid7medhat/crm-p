<template>
  <div class="ps-actions-sheet" role="dialog" aria-modal="true" aria-label="Property actions">
    <button type="button" class="ps-actions-sheet__backdrop" aria-label="Close actions" @click="emit('close')" />
    <div class="ps-actions-sheet__panel">
      <div class="ps-actions-sheet__head">
        <div class="ps-actions-sheet__head-text">
          <h2 class="ps-actions-sheet__title">Property Actions</h2>
          <p class="ps-actions-sheet__subtitle">Manage listing, requests, and status</p>
        </div>
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

<style scoped>
.ps-actions-sheet {
  position: fixed;
  inset: 0;
  z-index: 10060;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.ps-actions-sheet__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  padding: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
  cursor: pointer;
}

.ps-actions-sheet__panel {
  position: relative;
  width: 100%;
  max-width: 520px;
  max-height: min(88vh, 640px);
  background: #fff;
  border-radius: 20px 20px 0 0;
  box-shadow: 0 -12px 40px rgba(15, 23, 42, 0.18);
  display: flex;
  flex-direction: column;
  animation: ps-actions-slide-up 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes ps-actions-slide-up {
  from {
    transform: translateY(100%);
    opacity: 0.6;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.ps-actions-sheet__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 18px 12px;
  border-bottom: 1px solid #eef2f7;
}

.ps-actions-sheet__title {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.25;
}

.ps-actions-sheet__subtitle {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.35;
}

.ps-actions-sheet__close {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 10px;
  background: #f1f5f9;
  color: #475569;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  font-size: 18px;
  transition: background 0.15s ease;
}

.ps-actions-sheet__close:hover {
  background: #e2e8f0;
}

.ps-actions-sheet__body {
  overflow-y: auto;
  padding: 8px 14px calc(16px + env(safe-area-inset-bottom, 0px));
  -webkit-overflow-scrolling: touch;
}
</style>
