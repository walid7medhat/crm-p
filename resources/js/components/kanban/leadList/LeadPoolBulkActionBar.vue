<template>
  <Teleport to="body">
    <Transition name="lead-pool-bulk-bar">
      <div
        v-if="visible"
        class="lead-pool-bulk-bar"
        role="toolbar"
        aria-label="Bulk lead actions"
      >
        <div class="lead-pool-bulk-bar__inner">
          <div class="lead-pool-bulk-bar__meta">
            <span class="lead-pool-bulk-bar__count">{{ count }}</span>
            <span class="lead-pool-bulk-bar__label">
              {{ count === 1 ? 'lead selected' : 'leads selected' }}
            </span>
          </div>

          <div class="lead-pool-bulk-bar__actions">
            <button
              type="button"
              class="lead-pool-bulk-bar__btn lead-pool-bulk-bar__btn--primary"
              :disabled="assigning"
              @click="$emit('assign-to-me')"
            >
              <iconify-icon
                v-if="assigning"
                icon="lucide:loader-2"
                class="lead-pool-bulk-bar__spin"
              />
              <iconify-icon v-else icon="lucide:user-check" />
              <span>{{ assigning ? 'Assigning…' : 'Assign To Me' }}</span>
            </button>

            <button
              type="button"
              class="lead-pool-bulk-bar__btn lead-pool-bulk-bar__btn--ghost"
              :disabled="assigning"
              @click="$emit('clear')"
            >
              <iconify-icon icon="lucide:x" />
              <span>Clear Selection</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({
  visible: { type: Boolean, default: false },
  count: { type: Number, default: 0 },
  assigning: { type: Boolean, default: false },
})

defineEmits(['assign-to-me', 'clear'])
</script>

<style scoped>
.lead-pool-bulk-bar {
  position: fixed;
  left: 50%;
  bottom: calc(24px + env(safe-area-inset-bottom, 0px));
  transform: translateX(-50%);
  z-index: 12050;
  width: min(560px, calc(100vw - 24px));
  pointer-events: none;
}

.lead-pool-bulk-bar__inner {
  pointer-events: auto;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px 16px;
  padding: 12px 16px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: linear-gradient(
    145deg,
    rgba(11, 7, 54, 0.92) 0%,
    rgba(115, 62, 135, 0.78) 100%
  );
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow:
    0 12px 40px rgba(11, 7, 54, 0.45),
    0 0 0 1px rgba(255, 255, 255, 0.06) inset;
}

.lead-pool-bulk-bar__meta {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}

.lead-pool-bulk-bar__count {
  font-size: 20px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #fff;
  line-height: 1;
}

.lead-pool-bulk-bar__label {
  font-size: 13px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.72);
  white-space: nowrap;
}

.lead-pool-bulk-bar__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.lead-pool-bulk-bar__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  font-family: Montserrat, Inter, system-ui, sans-serif;
  border: 1px solid transparent;
  cursor: pointer;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    transform 0.18s ease,
    opacity 0.18s ease;
  white-space: nowrap;
}

.lead-pool-bulk-bar__btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.lead-pool-bulk-bar__btn--primary {
  color: #fff;
  background: rgba(0, 167, 250, 0.35);
  border-color: rgba(0, 167, 250, 0.55);
  box-shadow: 0 0 20px rgba(0, 167, 250, 0.25);
}

.lead-pool-bulk-bar__btn--primary:hover:not(:disabled) {
  background: rgba(0, 167, 250, 0.48);
  transform: translateY(-1px);
}

.lead-pool-bulk-bar__btn--ghost {
  color: rgba(255, 255, 255, 0.88);
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.14);
}

.lead-pool-bulk-bar__btn--ghost:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.14);
}

.lead-pool-bulk-bar__spin {
  animation: lead-pool-spin 0.8s linear infinite;
}

@keyframes lead-pool-spin {
  to {
    transform: rotate(360deg);
  }
}

.lead-pool-bulk-bar-enter-active {
  transition:
    opacity 0.28s ease,
    transform 0.38s cubic-bezier(0.34, 1.45, 0.64, 1);
}

.lead-pool-bulk-bar-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.22s ease;
}

.lead-pool-bulk-bar-enter-from,
.lead-pool-bulk-bar-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(18px) scale(0.96);
}

.lead-pool-bulk-bar-enter-to,
.lead-pool-bulk-bar-leave-from {
  opacity: 1;
  transform: translateX(-50%) translateY(0) scale(1);
}

@media (max-width: 640px) {
  .lead-pool-bulk-bar {
    width: calc(100vw - 16px);
    bottom: calc(80px + env(safe-area-inset-bottom, 0px));
  }

  .lead-pool-bulk-bar__inner {
    flex-direction: column;
    align-items: stretch;
  }

  .lead-pool-bulk-bar__actions {
    width: 100%;
  }

  .lead-pool-bulk-bar__btn {
    flex: 1 1 auto;
    min-height: 40px;
  }
}
</style>
