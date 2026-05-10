<template>
  <div class="sam">
    <div class="sam-ribbon">
      <div class="sam-ribbon-inner">
        <span class="sam-ribbon-k">{{ ribbonK }}</span>
        <div class="sam-ribbon-h" role="heading" aria-level="2">{{ ribbonH }}</div>
        <div class="sam-ribbon-p">
          {{ ribbonP }}
        </div>
      </div>
    </div>

    <div class="sam-canvas">
      <!-- Intelligence band -->
      <div class="sam-band sam-band-intel">
        <div class="sam-band-head">
          <iconify-icon icon="lucide:cpu" class="sam-band-ic" />
          <span>{{ bandHead }}</span>
        </div>
        <div class="sam-engines">
          <div
            v-for="eng in enginesList"
            :key="eng.id"
            class="sam-engine"
            :style="{ '--e': eng.accent }"
          >
            <iconify-icon :icon="eng.icon" class="sam-engine-ic" />
            <span class="sam-engine-t">{{ eng.title }}</span>
          </div>
        </div>
      </div>

      <!-- Core pipeline -->
      <div class="sam-pipeline-row">
        <template v-for="(stage, i) in stagesList" :key="stage.id">
          <div class="sam-stage" :class="'sam-stage-' + stage.tone">
            <div class="sam-stage-num">{{ String(i + 1).padStart(2, '0') }}</div>
            <iconify-icon :icon="stage.icon" class="sam-stage-ic" />
            <div class="sam-stage-text">
              <span class="sam-stage-title">{{ stage.label }}</span>
              <span class="sam-stage-tag">{{ stage.tagline }}</span>
            </div>
          </div>
          <div v-if="i < stagesList.length - 1" class="sam-stage-arrow">
            <svg viewBox="0 0 64 32" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                :stroke="'url(#sam-grad-' + i + ')'"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M8 16h40M44 16l-10-8m10 8l-10 8"
              />
              <defs>
                <linearGradient
                  :id="'sam-grad-' + i"
                  x1="8"
                  y1="16"
                  x2="48"
                  y2="16"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#a5b4fc" />
                  <stop offset="1" stop-color="#6366f1" />
                </linearGradient>
              </defs>
            </svg>
          </div>
        </template>
      </div>

      <!-- Relationship matrix -->
      <div class="sam-matrix">
        <div class="sam-matrix-title" role="heading" aria-level="3">{{ matrixTitle }}</div>
        <ul class="sam-matrix-list">
          <li v-for="(row, idx) in matrixRowsList" :key="idx">
            <span class="sam-m-from">{{ row.from }}</span>
            <span class="sam-m-arrow">{{ row.arrow }}</span>
            <span class="sam-m-to">{{ row.to }}</span>
            <span class="sam-m-cap">{{ row.cap }}</span>
          </li>
        </ul>
      </div>

      <div class="sam-foot">
        <span class="sam-foot-pill">{{ footPill }}</span>
        <span class="sam-foot-note">{{ footNote }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { intelligenceEngines, pipelineStages, crossModuleEdges } from '@/data/systemOverviewPresentation.js'

const props = defineProps({
  ribbonK: { type: String, default: 'Architecture mode' },
  ribbonH: { type: String, default: 'Commercial operating system' },
  ribbonP: {
    type: String,
    default:
      'Inventory, pipeline, and revenue — unified by an intelligence layer that scores, routes, matches, and validates.',
  },
  bandHead: { type: String, default: 'System intelligence' },
  enginesDisplay: { type: Array, default: null },
  stagesDisplay: { type: Array, default: null },
  matrixTitle: { type: String, default: 'Cross-module data flow' },
  matrixRows: { type: Array, default: null },
  footPill: { type: String, default: 'Investor-ready map' },
  footNote: {
    type: String,
    default: 'Mock KPIs and labels are illustrative when demo mode is on.',
  },
})

function labelFor(id) {
  if (id === 'intelligence') return 'Intelligence'
  const s = pipelineStages.find((x) => x.id === id)
  return s ? s.label : id
}

const enginesList = computed(() => props.enginesDisplay ?? intelligenceEngines)

const stagesList = computed(() => props.stagesDisplay ?? pipelineStages)

const matrixRowsList = computed(() => {
  if (props.matrixRows?.length) return props.matrixRows
  return crossModuleEdges.map((e) => ({
    from: labelFor(e.from),
    arrow: '→',
    to: labelFor(e.to),
    cap: e.label,
  }))
})
</script>

<style scoped>
.sam {
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid rgba(15, 23, 42, 0.1);
  background: linear-gradient(180deg, #0f172a 0%, #1e1b4b 45%, #312e81 100%);
  box-shadow: 0 24px 64px rgba(15, 23, 42, 0.35);
  color: #e2e8f0;
}
.sam-ribbon {
  padding: 16px 18px 12px;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.35), rgba(15, 23, 42, 0.2));
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.sam-ribbon-k {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: #a5b4fc;
  display: block;
  margin-bottom: 8px;
}
.sam-ribbon-h {
  margin: 0 0 6px;
  font-size: 15px;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.3;
  color: #fff;
}
.sam-ribbon-p {
  margin: 0;
  max-width: 560px;
  font-size: 11px;
  line-height: 1.45;
  color: rgba(226, 232, 240, 0.85);
}

.sam-canvas {
  padding: 14px 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.sam-band-intel {
  padding: 12px 14px;
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.1);
}
.sam-band-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #c7d2fe;
}
.sam-band-ic {
  font-size: 14px;
  color: #a5b4fc;
}
.sam-engines {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
}
.sam-engine {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  transition:
    transform 0.2s,
    background 0.2s;
}
.sam-engine:hover {
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 0.1);
}
.sam-engine-ic {
  font-size: 14px;
  color: var(--e, #a78bfa);
  flex-shrink: 0;
}
.sam-engine-t {
  font-size: 11px;
  font-weight: 700;
  color: #f1f5f9;
  line-height: 1.25;
}

.sam-pipeline-row {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  justify-content: center;
  gap: 8px;
  position: relative;
}
.sam-stage {
  flex: 1 1 140px;
  max-width: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 6px;
  padding: 12px 10px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  position: relative;
}
.sam-stage-num {
  position: absolute;
  top: 10px;
  left: 12px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: rgba(255, 255, 255, 0.35);
}
.sam-stage-amber {
  background: linear-gradient(165deg, rgba(245, 158, 11, 0.25), rgba(15, 23, 42, 0.5));
}
.sam-stage-violet {
  background: linear-gradient(165deg, rgba(139, 92, 246, 0.3), rgba(15, 23, 42, 0.5));
}
.sam-stage-emerald {
  background: linear-gradient(165deg, rgba(16, 185, 129, 0.28), rgba(15, 23, 42, 0.5));
}
.sam-stage-ic {
  font-size: 15px;
  color: #fff;
  opacity: 0.95;
}
.sam-stage-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.sam-stage-title {
  font-size: 13px;
  font-weight: 800;
  color: #fff;
}
.sam-stage-tag {
  font-size: 10px;
  font-weight: 600;
  color: rgba(226, 232, 240, 0.75);
}
.sam-stage-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  min-height: 56px;
  flex-shrink: 0;
}
.sam-stage-arrow svg {
  width: 100%;
  height: 28px;
}

.sam-matrix {
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.sam-matrix-title {
  margin: 0 0 10px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #a5b4fc;
}
.sam-matrix-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sam-matrix-list li {
  display: grid;
  grid-template-columns: auto auto auto 1fr;
  align-items: center;
  gap: 6px 8px;
  font-size: 11px;
  padding: 8px 10px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.04);
}
.sam-m-from,
.sam-m-to {
  font-weight: 700;
  color: #f8fafc;
}
.sam-m-arrow {
  color: #818cf8;
  font-weight: 600;
}
.sam-m-cap {
  grid-column: 1 / -1;
  font-size: 11px;
  font-weight: 600;
  color: #c4b5fd;
}
@media (min-width: 520px) {
  .sam-m-cap {
    grid-column: auto;
  }
}

.sam-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding-top: 8px;
  border-top: 1px dashed rgba(255, 255, 255, 0.12);
}
.sam-foot-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.2);
  color: #6ee7b7;
  border: 1px solid rgba(52, 211, 153, 0.35);
}
.sam-foot-note {
  font-size: 11px;
  color: rgba(226, 232, 240, 0.55);
}

.sam-connectors {
  display: none;
}
</style>
