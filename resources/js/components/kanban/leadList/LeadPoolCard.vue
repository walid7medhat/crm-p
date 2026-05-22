<template>
  <div
    class="lp-card"
    :class="{
      'lp-card--selected': selected,
      'lp-card--select-mode': selectMode,
    }"
    @click="onCardClick"
  >
    <div v-if="selectMode" class="lp-card__check" aria-hidden="true">
      <span class="lp-card__circle" :class="{ 'lp-card__circle--on': selected }">
        <iconify-icon v-if="selected" icon="lucide:check" />
      </span>
    </div>

    <div class="lp-card__content" @click.stop="onContentClick">
      <slot />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  selected: { type: Boolean, default: false },
  selectMode: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle', 'open'])

function onCardClick(event) {
  if (!props.selectMode) return
  if (event.target.closest('.duplicate-badge, .person-hover-anchor')) return
  emit('toggle', event)
}

function onContentClick(event) {
  if (props.selectMode) {
    if (event.target.closest('.duplicate-badge, .person-hover-anchor')) return
    emit('toggle', event)
    return
  }
  if (event.target.closest('button, .duplicate-badge, .person-hover-anchor')) return
  emit('open', event)
}
</script>

<style scoped>
.lp-card {
  display: flex;
  align-items: stretch;
  gap: 0;
  height: 100%;
  width: 100%;
  border-radius: 12px;
  cursor: default;
  transition: box-shadow 0.2s ease, background 0.2s ease;
}

.lp-card--select-mode {
  cursor: pointer;
}

.lp-card--select-mode.lp-card--selected {
  background: rgba(0, 167, 250, 0.08);
  box-shadow: inset 4px 0 0 #00a7fa;
}

.lp-card__check {
  flex: 0 0 36px;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 14px;
  padding-left: 4px;
}

.lp-card__circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 2px solid #cbd5e1;
  background: #fff;
  color: transparent;
  font-size: 13px;
  transition:
    border-color 0.15s ease,
    background 0.15s ease,
    color 0.15s ease,
    transform 0.15s ease;
}

.lp-card--select-mode:hover .lp-card__circle:not(.lp-card__circle--on) {
  border-color: #00a7fa;
}

.lp-card__circle--on {
  border-color: #00a7fa;
  background: #00a7fa;
  color: #fff;
  transform: scale(1.05);
}

.lp-card__content {
  flex: 1 1 auto;
  min-width: 0;
  cursor: pointer;
}

.lp-card--select-mode .lp-card__content {
  cursor: pointer;
}

.lp-card--select-mode.lp-card--selected .lp-card__content :deep(.kanban-card) {
  border-color: #93c5fd !important;
  background: #f8fafc !important;
}

.lp-card__content :deep(.kanban-card) {
  width: 100%;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.lp-card:not(.lp-card--select-mode):hover .lp-card__content :deep(.kanban-card) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08) !important;
}
</style>
