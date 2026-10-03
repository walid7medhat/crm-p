<template>
  <div class="ps-actions-sheet" role="dialog" aria-modal="true" aria-label="Property actions">
    <button type="button" class="ps-actions-sheet__backdrop" aria-label="Close actions" @click="emit('close')" />
    <div class="ps-actions-sheet__panel">
      <span class="ps-actions-sheet__grab" aria-hidden="true"></span>
      <button type="button" class="ps-actions-sheet__close" aria-label="Close" @click="emit('close')">
        <i class="ri-close-line"></i>
      </button>
      <div class="ps-actions-sheet__body">
        <div class="ps-actions-sheet__card">
          <slot />
        </div>
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
  z-index: 12500;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 12px 12px calc(86px + env(safe-area-inset-bottom, 0px));
  box-sizing: border-box;
}

.ps-actions-sheet__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  padding: 0;
  background: rgba(30, 16, 48, 0.42);
  backdrop-filter: blur(3px);
  cursor: pointer;
}

.ps-actions-sheet__panel {
  position: relative;
  z-index: 1;
  width: min(440px, 100%);
  max-height: min(74dvh, 680px);
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #fcfbfe 0%, #ffffff 72px);
  border: 1px solid rgba(115, 62, 135, 0.08);
  border-radius: 26px;
  box-shadow:
    0 20px 50px rgba(49, 20, 72, 0.22),
    0 2px 8px rgba(49, 20, 72, 0.06);
  overflow: hidden;
  animation: ps-actions-slide-up 0.22s ease;
}

.ps-actions-sheet__grab {
  width: 36px;
  height: 4px;
  margin: 8px auto 0;
  border-radius: 999px;
  background: #e4dceb;
  flex-shrink: 0;
}

.ps-actions-sheet__close {
  position: absolute;
  top: 8px;
  right: 10px;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 50%;
  background: #f4f0f8;
  color: #5b2170;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  cursor: pointer;
  z-index: 1;
}

.ps-actions-sheet__body {
  overflow-y: auto;
  padding: 8px 10px 12px;
  -webkit-overflow-scrolling: touch;
  flex: 1 1 auto;
  min-height: 0;
}

.ps-actions-sheet__card {
  background: #fff;
  border: 1px solid #f0eaf6;
  border-radius: 16px;
  padding: 4px;
  box-shadow: 0 1px 2px rgba(49, 20, 72, 0.04);
}

@keyframes ps-actions-slide-up {
  from {
    transform: translateY(16px);
    opacity: 0.7;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
</style>
