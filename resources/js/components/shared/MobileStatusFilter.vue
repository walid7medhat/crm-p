<template>
  <div class="msf">
    <button type="button" class="msf-trigger" :aria-expanded="open" @click="open = true">
      <span class="msf-trigger__label">Status</span>
      <span class="msf-trigger__value">
        <span class="msf-dot" :style="{ background: colorFor(current?.value) }"></span>
        {{ current?.label || 'All' }}
        <span v-if="current && current.count > 0" class="msf-count">{{ current.count }}</span>
      </span>
      <i class="ri-arrow-down-s-line msf-trigger__chevron"></i>
    </button>

    <Teleport to="body">
      <Transition name="msf-fade">
        <div v-if="open" class="msf-backdrop" @click.self="open = false">
          <div class="msf-sheet" role="dialog" aria-modal="true" aria-label="Filter by status">
            <div class="msf-sheet__handle" aria-hidden="true"></div>
            <div class="msf-sheet__header">
              <h6 class="msf-sheet__title">Filter by status</h6>
              <button type="button" class="msf-sheet__close" aria-label="Close" @click="open = false">
                <i class="ri-close-line"></i>
              </button>
            </div>
            <ul class="msf-list">
              <li v-for="option in options" :key="option.value">
                <button
                  type="button"
                  class="msf-option"
                  :class="{ 'msf-option--active': option.value === modelValue }"
                  @click="select(option.value)"
                >
                  <span class="msf-dot" :style="{ background: colorFor(option.value) }"></span>
                  <span class="msf-option__label">{{ option.label }}</span>
                  <span class="msf-option__count">{{ option.count || 0 }}</span>
                  <i v-if="option.value === modelValue" class="ri-check-line msf-option__check"></i>
                </button>
              </li>
            </ul>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
// Mobile replacement for the wrapping status tab buttons on the request pages:
// a single trigger showing the active status + count, opening a bottom sheet.
import { ref, computed, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: 'all' },
  /** [{ label, value, count }] */
  options: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)

const current = computed(() => props.options.find((o) => o.value === props.modelValue))

const STATUS_COLORS = {
  all: '#6b21a8',
  pending: '#f59e0b',
  in_progress: '#0ea5e9',
  approved: '#16a34a',
  converted: '#0d9488',
  rejected: '#dc2626',
  cancelled: '#6b7280',
}
const colorFor = (value) => STATUS_COLORS[value] || '#94a3b8'

const select = (value) => {
  emit('update:modelValue', value)
  open.value = false
}

const onKeydown = (event) => {
  if (event.key === 'Escape') open.value = false
}

watch(open, (isOpen) => {
  document.body.classList.toggle('msf-sheet-open', isOpen)
  if (isOpen) document.addEventListener('keydown', onKeydown)
  else document.removeEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.body.classList.remove('msf-sheet-open')
  document.removeEventListener('keydown', onKeydown)
})
</script>

<style>
body.msf-sheet-open {
  overflow: hidden;
}
</style>

<style scoped>
.msf-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border: 1px solid #e2e5ec;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  text-align: left;
  cursor: pointer;
}

.msf-trigger__label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
}

.msf-trigger__value {
  flex: 1;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  min-width: 0;
}

.msf-trigger__chevron {
  font-size: 20px;
  color: #6b7280;
}

.msf-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.msf-count {
  background: #f3e8ff;
  color: #6b21a8;
  border-radius: 999px;
  padding: 1px 8px;
  font-size: 12px;
  font-weight: 600;
}

/* Bottom sheet — above the mobile tab bar (z-index 12060) */
.msf-backdrop {
  position: fixed;
  inset: 0;
  z-index: 12100;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: flex-end;
}

.msf-sheet {
  width: 100%;
  background: #fff;
  border-radius: 20px 20px 0 0;
  padding: 8px 16px calc(16px + env(safe-area-inset-bottom, 0px));
  box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.15);
  max-height: 80dvh;
  overflow-y: auto;
}

.msf-sheet__handle {
  width: 40px;
  height: 4px;
  border-radius: 2px;
  background: #d1d5db;
  margin: 4px auto 10px;
}

.msf-sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.msf-sheet__title {
  margin: 0;
  font-size: 16px !important;
  font-weight: 600;
  color: #0B0736;
}

.msf-sheet__close {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: #f3f4f6;
  color: #374151;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.msf-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.msf-option {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 12px;
  border: none;
  border-radius: 12px;
  background: transparent;
  font-size: 15px;
  color: #111827;
  text-align: left;
}

.msf-option + .msf-option {
  margin-top: 2px;
}

.msf-option:active {
  background: #f3f4f6;
}

.msf-option--active {
  background: #f5f3ff;
  font-weight: 600;
  color: #6b21a8;
}

.msf-option__label {
  flex: 1;
}

.msf-option__count {
  min-width: 32px;
  text-align: center;
  background: #f3f4f6;
  color: #374151;
  border-radius: 999px;
  padding: 2px 10px;
  font-size: 13px;
  font-weight: 600;
}

.msf-option--active .msf-option__count {
  background: #6b21a8;
  color: #fff;
}

.msf-option__check {
  font-size: 18px;
  color: #6b21a8;
}

.msf-fade-enter-active,
.msf-fade-leave-active {
  transition: opacity 0.2s ease;
}

.msf-fade-enter-active .msf-sheet,
.msf-fade-leave-active .msf-sheet {
  transition: transform 0.25s ease;
}

.msf-fade-enter-from,
.msf-fade-leave-to {
  opacity: 0;
}

.msf-fade-enter-from .msf-sheet,
.msf-fade-leave-to .msf-sheet {
  transform: translateY(100%);
}
</style>
