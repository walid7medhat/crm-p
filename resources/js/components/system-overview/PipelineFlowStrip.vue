<template>
  <div class="pfs" :class="{ compact }">
    <div class="pfs-track">
      <template v-for="(stage, i) in stages" :key="stage.id">
        <div class="pfs-node" :class="'tone-' + stage.tone">
          <div class="pfs-icon-wrap">
            <iconify-icon :icon="stage.icon" class="pfs-icon" />
          </div>
          <div class="pfs-text">
            <span class="pfs-label">{{ stage.label }}</span>
            <span class="pfs-tag">{{ stage.tagline }}</span>
          </div>
        </div>
        <div v-if="i < stages.length - 1" class="pfs-arrow" aria-hidden="true">
          <svg viewBox="0 0 48 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M4 12h32m0 0l-8-8m8 8l-8 8"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              opacity="0.35"
            />
          </svg>
        </div>
      </template>
    </div>
    <div class="pfs-foot">
      <span class="pfs-flow-label">{{ footLabel }}</span>
      <span class="pfs-flow-path">{{ footPath }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { pipelineStages } from '@/data/systemOverviewPresentation.js'

const props = defineProps({
  compact: { type: Boolean, default: false },
  /** Override labels/taglines (i18n). Defaults to static pipelineStages. */
  stagesOverride: { type: Array, default: null },
  footLabel: { type: String, default: 'Revenue architecture' },
  footPath: { type: String, default: 'Inventory → Pipeline → Close' },
})

const stages = computed(() => props.stagesOverride ?? pipelineStages)
</script>

<style scoped>
.pfs {
  padding: 12px 14px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
}
.pfs.compact {
  padding: 10px 12px;
}
.pfs-track {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px 4px;
}
.pfs-node {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  min-width: 120px;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}
.pfs-node:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}
.tone-amber {
  background: linear-gradient(145deg, rgba(251, 191, 36, 0.18), rgba(245, 158, 11, 0.08));
  border: 1px solid rgba(245, 158, 11, 0.25);
}
.tone-violet {
  background: linear-gradient(145deg, rgba(167, 139, 250, 0.2), rgba(139, 92, 246, 0.08));
  border: 1px solid rgba(139, 92, 246, 0.22);
}
.tone-emerald {
  background: linear-gradient(145deg, rgba(52, 211, 153, 0.2), rgba(16, 185, 129, 0.08));
  border: 1px solid rgba(16, 185, 129, 0.22);
}
.pfs-icon-wrap {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.7);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
}
.pfs-icon {
  font-size: 15px;
  color: #0f172a;
}
.pfs-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.pfs-label {
  font-weight: 700;
  font-size: 13px;
  color: #0f172a;
  letter-spacing: -0.02em;
}
.pfs-tag {
  font-size: 10px;
  color: #64748b;
  font-weight: 500;
}
.pfs-arrow {
  color: #94a3b8;
  width: 40px;
  height: 24px;
  flex-shrink: 0;
}
.pfs-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed rgba(15, 23, 42, 0.1);
}
.pfs-flow-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #94a3b8;
}
.pfs-flow-path {
  font-size: 11px;
  font-weight: 600;
  color: #6366f1;
}
</style>
