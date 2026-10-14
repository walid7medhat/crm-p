<template>
  <Teleport to="body">
    <Transition name="mk-modal">
      <div v-if="modelValue" class="mk-modal-overlay" @mousedown.self="close">
        <div class="mk-modal" :style="{ maxWidth: width }" role="dialog" aria-modal="true">
          <header class="mk-modal__head">
            <span v-if="icon" class="mk-modal__icon"><iconify-icon :icon="icon" /></span>
            <div class="mk-modal__titles">
              <h5>{{ title }}</h5>
              <p v-if="subtitle">{{ subtitle }}</p>
            </div>
            <button type="button" class="mk-modal__close" aria-label="Close" @click="close">
              <iconify-icon icon="lucide:x" />
            </button>
          </header>
          <div class="mk-modal__body">
            <slot />
          </div>
          <footer v-if="$slots.footer" class="mk-modal__foot">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  accent: { type: String, default: '' },
  width: { type: String, default: '560px' },
})
const emit = defineEmits(['update:modelValue', 'close'])

function close() {
  emit('update:modelValue', false)
  emit('close')
}

function onKey(e) {
  if (e.key === 'Escape') close()
}

watch(() => props.modelValue, (open) => {
  if (open) window.addEventListener('keydown', onKey)
  else window.removeEventListener('keydown', onKey)
}, { immediate: true })

onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<style scoped>
.mk-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 10, 30, 0.45);
  backdrop-filter: blur(4px);
}
.mk-modal {
  --mk-accent: var(--color-primary);
  width: 100%;
  max-height: calc(100vh - 32px);
  display: flex;
  flex-direction: column;
  background: var(--white);
  color: var(--text-primary-light);
  border: 1px solid var(--border-color);
  border-radius: 20px;
  box-shadow: 0 30px 80px -20px rgba(15, 10, 30, 0.45);
  overflow: hidden;
}
/* Header in the CRM theme color (same brand fill as the app's primary buttons). */
.mk-modal__head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  color: var(--color-on-primary);
  background: var(--gradient-primary);
}
.mk-modal__icon {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  display: grid;
  place-items: center;
  font-size: 19px !important;
  color: var(--color-on-primary);
  background: rgba(255, 255, 255, 0.16);
  border: 1px solid rgba(255, 255, 255, 0.22);
  flex-shrink: 0;
}
.mk-modal__titles { flex: 1; min-width: 0; }
.mk-modal__titles h5 { margin: 0; font-size: 15px !important; font-weight: 700 !important; color: var(--color-on-primary); }
.mk-modal__titles p {
  margin: 2px 0 0;
  font-size: 13px !important;
  color: rgba(255, 255, 255, 0.8);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mk-modal__close {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 0;
  display: grid;
  place-items: center;
  font-size: 18px !important;
  color: var(--color-on-primary);
  background: rgba(255, 255, 255, 0.12);
  transition: background 0.15s;
}
.mk-modal__close:hover { background: rgba(255, 255, 255, 0.24); }
.mk-modal__close:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
.mk-modal__body { padding: 20px; overflow-y: auto; }
.mk-modal__foot {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--border-color);
  background: var(--neutral-50);
}
.mk-modal-enter-active, .mk-modal-leave-active { transition: opacity 0.18s ease; }
.mk-modal-enter-active .mk-modal, .mk-modal-leave-active .mk-modal { transition: transform 0.22s cubic-bezier(.2,.8,.2,1); }
.mk-modal-enter-from, .mk-modal-leave-to { opacity: 0; }
.mk-modal-enter-from .mk-modal { transform: translateY(14px) scale(0.98); }
@media (max-width: 576px) {
  .mk-modal-overlay { align-items: flex-end; padding: 0; }
  .mk-modal { border-radius: 20px 20px 0 0; max-height: 92vh; }
}
@media (prefers-reduced-motion: reduce) {
  .mk-modal-enter-active, .mk-modal-leave-active, .mk-modal-enter-active .mk-modal { transition: none; }
}
</style>
