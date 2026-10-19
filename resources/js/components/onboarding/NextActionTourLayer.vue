<template>
  <Teleport to="body">
    <!-- 3D “New Feature” intro (video-like bumper) -->
    <Transition name="nat-fade">
      <div
        v-if="isIntro"
        class="nat-intro"
        role="dialog"
        aria-modal="true"
        aria-label="New feature"
        @keydown.esc.prevent="endNextActionTourPreview"
      >
        <div class="nat-intro__stage">
          <div class="nat-intro__orbit" aria-hidden="true">
            <span /><span /><span />
          </div>
          <div class="nat-intro__card">
            <p class="nat-intro__badge">New feature</p>
            <p class="nat-intro__name">Next Action</p>
            <p class="nat-intro__tag">Your personal work queue · live preview</p>
          </div>
          <button type="button" class="nat-intro__skip" @click="endNextActionTourPreview">Close</button>
        </div>
      </div>
    </Transition>

    <!-- Walkthrough on the real tool -->
    <div v-if="isTourWalkthrough && tourStep" class="nat-tour" role="dialog" aria-modal="true" :aria-label="tourStep.label">
      <div class="nat-tour__shade" :style="shadeStyle" @click="endNextActionTourPreview" />
      <div v-if="hole" class="nat-tour__spot" :style="spotStyle" aria-hidden="true" />

      <aside class="nat-tour__panel" :style="panelStyle">
        <div class="nat-tour__head">
          <p class="nat-tour__meta">
            {{ tourAutoPlaying ? 'Auto preview' : 'Preview' }}
            · {{ tourStepIndex + 1 }}/{{ tourSteps.length || 1 }}
          </p>
          <button type="button" class="nat-tour__x" aria-label="Close tour" @click="endNextActionTourPreview">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>
        <p class="nat-tour__label">{{ tourStep.label }}</p>
        <p class="nat-tour__body">{{ tourStep.body }}</p>
        <div class="nat-tour__nav">
          <button type="button" class="nat-btn" @click="endNextActionTourPreview">Close</button>
        </div>
      </aside>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import {
  endNextActionTourPreview,
  isIntro,
  isTourWalkthrough,
  tourAutoPlaying,
  tourStep,
  tourStepIndex,
  tourSteps,
} from '@/composables/useNextActionTourPreview.js'
import { initNextActionTourLive } from '@/composables/useNextActionTourLive.js'

let teardownLive = null

const hole = ref(null)
const viewport = ref({ w: 0, h: 0 })

const shadeStyle = computed(() => {
  const h = hole.value
  if (!h) {
    return { clipPath: 'none', background: 'rgba(11, 7, 54, 0.48)' }
  }
  const { top, left, width, height } = h
  const path = `polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 0%, ${left}px ${top}px, ${left}px ${top + height}px, ${left + width}px ${top + height}px, ${left + width}px ${top}px, ${left}px ${top}px)`
  return { clipPath: path, background: 'rgba(11, 7, 54, 0.52)' }
})

const spotStyle = computed(() => {
  const h = hole.value
  if (!h) return { display: 'none' }
  return {
    top: `${h.top}px`,
    left: `${h.left}px`,
    width: `${h.width}px`,
    height: `${h.height}px`,
  }
})

const panelStyle = computed(() => {
  const mobile = viewport.value.w <= 800
  if (mobile) {
    return {
      left: '12px',
      right: '12px',
      bottom: 'calc(14px + env(safe-area-inset-bottom, 0px))',
      top: 'auto',
      width: 'auto',
    }
  }
  const h = hole.value
  const panelW = 280
  let left = 20
  let top = 80
  if (h) {
    left = Math.min(viewport.value.w - panelW - 14, Math.max(12, h.left - panelW - 16))
    if (left < 12 || h.left < panelW + 36) {
      left = Math.min(viewport.value.w - panelW - 14, h.left + h.width + 16)
    }
    top = Math.max(12, Math.min(h.top, viewport.value.h - 200))
  }
  return {
    top: `${top}px`,
    left: `${left}px`,
    width: `${panelW}px`,
    right: 'auto',
    bottom: 'auto',
  }
})

function unionRect(nodes) {
  let top = Infinity
  let left = Infinity
  let right = -Infinity
  let bottom = -Infinity
  let found = false
  for (const el of nodes) {
    const r = el.getBoundingClientRect()
    if (!r.width && !r.height) continue
    found = true
    top = Math.min(top, r.top)
    left = Math.min(left, r.left)
    right = Math.max(right, r.right)
    bottom = Math.max(bottom, r.bottom)
  }
  if (!found) return null
  return { top, left, width: right - left, height: bottom - top }
}

function measure() {
  viewport.value = { w: window.innerWidth, h: window.innerHeight }
  const step = tourStep.value
  if (!step?.selector || !isTourWalkthrough.value) {
    hole.value = null
    return
  }
  let rect = null
  if (step.selector === 'tree' || step.id === 'tree') {
    rect = unionRect(document.querySelectorAll('[data-tour="next-actions-stage"], .sac-node'))
  } else {
    const el = document.querySelector(step.selector)
    if (el) {
      const r = el.getBoundingClientRect()
      rect = { top: r.top, left: r.left, width: r.width, height: r.height }
    }
  }
  if (!rect || (!rect.width && !rect.height)) {
    hole.value = null
    return
  }
  const pad = step.pad || 8
  hole.value = {
    top: Math.max(6, rect.top - pad),
    left: Math.max(6, rect.left - pad),
    width: Math.min(viewport.value.w - 12, rect.width + pad * 2),
    height: Math.min(viewport.value.h - 12, rect.height + pad * 2),
  }
}

watch([tourStepIndex, isTourWalkthrough, tourSteps], async () => {
  if (!isTourWalkthrough.value) {
    hole.value = null
    return
  }
  await nextTick()
  measure()
  setTimeout(measure, 280)
  setTimeout(measure, 650)
})

function onKey(event) {
  if (!isIntro.value && !isTourWalkthrough.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    endNextActionTourPreview()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
  window.addEventListener('resize', measure)
  teardownLive = initNextActionTourLive()
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('resize', measure)
  if (typeof teardownLive === 'function') teardownLive()
  teardownLive = null
})
</script>

<style scoped>
.nat-intro {
  position: fixed;
  inset: 0;
  z-index: 100060;
  display: grid;
  place-items: center;
  background:
    radial-gradient(ellipse at 30% 20%, rgba(155, 89, 182, 0.35), transparent 55%),
    radial-gradient(ellipse at 70% 80%, rgba(115, 62, 135, 0.4), transparent 50%),
    linear-gradient(160deg, #12081f 0%, #1a1028 45%, #0b0736 100%);
  perspective: 1200px;
  overflow: hidden;
}
.nat-intro__stage {
  position: relative;
  width: min(420px, 92vw);
  height: min(280px, 56vh);
  transform-style: preserve-3d;
  animation: nat-cam 4.2s ease-in-out both;
}
.nat-intro__orbit {
  position: absolute;
  inset: 8%;
  border-radius: 28px;
  border: 1px solid rgba(216, 180, 254, 0.28);
  transform: rotateX(58deg) rotateZ(-18deg);
  animation: nat-orbit 4.2s ease-in-out both;
}
.nat-intro__orbit span {
  position: absolute;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #e9d5ff;
  box-shadow: 0 0 18px #d8b4fe;
}
.nat-intro__orbit span:nth-child(1) { top: 12%; left: 18%; animation: nat-dot 1.6s ease-in-out infinite; }
.nat-intro__orbit span:nth-child(2) { top: 48%; right: 14%; animation: nat-dot 1.6s ease-in-out 0.3s infinite; }
.nat-intro__orbit span:nth-child(3) { bottom: 16%; left: 42%; animation: nat-dot 1.6s ease-in-out 0.6s infinite; }

.nat-intro__card {
  position: absolute;
  inset: 18% 10%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 22px;
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow:
    0 30px 60px rgba(0, 0, 0, 0.35),
    inset 0 1px 0 rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(14px);
  transform: translateZ(48px) rotateY(-8deg) rotateX(6deg);
  animation: nat-card 4.2s cubic-bezier(0.2, 0.8, 0.2, 1) both;
  text-align: center;
  padding: 20px;
}
.nat-intro__badge {
  margin: 0;
  padding: 4px 10px;
  border-radius: 999px;
  color: #f5e9ff;
  background: rgba(115, 62, 135, 0.55);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.nat-intro__name {
  margin: 0;
  color: #fff;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-shadow: 0 10px 30px rgba(115, 62, 135, 0.55);
}
.nat-intro__tag {
  margin: 0;
  color: rgba(255, 255, 255, 0.72);
  font-size: 12px;
  font-weight: 500;
}
.nat-intro__skip {
  position: absolute;
  top: -42px;
  right: 0;
  border: 0;
  border-radius: 999px;
  padding: 6px 12px;
  color: #efe6f8;
  background: rgba(255, 255, 255, 0.12);
  font-size: 11px;
  font-weight: 650;
  cursor: pointer;
}

.nat-tour {
  position: fixed;
  inset: 0;
  z-index: 100040;
  pointer-events: none;
}
.nat-tour__shade {
  position: absolute;
  inset: 0;
  pointer-events: auto;
  transition: clip-path 0.28s ease;
}
.nat-tour__spot {
  position: absolute;
  border-radius: 14px;
  box-shadow:
    0 0 0 2px rgba(216, 180, 254, 0.95),
    0 0 0 7px rgba(115, 62, 135, 0.2);
  pointer-events: none;
  animation: nat-pulse 1.5s ease-in-out infinite;
}
.nat-tour__panel {
  position: absolute;
  z-index: 2;
  pointer-events: auto;
  padding: 12px 12px 10px;
  border-radius: 14px;
  background: #fff;
  border: 1px solid #ebecef;
  box-shadow: 0 14px 32px rgba(11, 7, 54, 0.16);
  animation: nat-pop 0.22s ease both;
}
.nat-tour__head {
  display: flex;
  align-items: center;
  gap: 6px;
}
.nat-tour__meta {
  margin: 0;
  flex: 1;
  color: #9a8fb0;
  font-size: 10px;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.nat-tour__x {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border: 0;
  border-radius: 999px;
  background: #f4f1f7;
  color: #3b3558;
  cursor: pointer;
  font-size: 14px;
}
.nat-tour__label {
  margin: 8px 0 4px;
  color: #0b0736;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.3;
}
.nat-tour__body {
  margin: 0 0 10px;
  color: #5b5572;
  font-size: 12px;
  line-height: 1.4;
}
.nat-tour__nav {
  display: flex;
  justify-content: flex-end;
}
.nat-btn {
  appearance: none;
  border: 0;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 11px;
  font-weight: 700;
  color: #3b3558;
  background: #f4f1f7;
  cursor: pointer;
}

.nat-fade-enter-active,
.nat-fade-leave-active { transition: opacity 0.25s ease; }
.nat-fade-enter-from,
.nat-fade-leave-to { opacity: 0; }

@keyframes nat-cam {
  0% { opacity: 0; transform: translateZ(-180px) scale(0.7); }
  18% { opacity: 1; transform: translateZ(0) scale(1); }
  82% { opacity: 1; transform: translateZ(20px) scale(1.02); }
  100% { opacity: 0; transform: translateZ(120px) scale(1.08); }
}
@keyframes nat-card {
  0% { opacity: 0; transform: translateZ(-40px) rotateY(28deg) rotateX(18deg) scale(0.8); }
  22% { opacity: 1; transform: translateZ(48px) rotateY(-8deg) rotateX(6deg) scale(1); }
  78% { opacity: 1; transform: translateZ(56px) rotateY(4deg) rotateX(2deg) scale(1.02); }
  100% { opacity: 0; transform: translateZ(90px) rotateY(-12deg) scale(1.06); }
}
@keyframes nat-orbit {
  0% { opacity: 0; transform: rotateX(58deg) rotateZ(-40deg) scale(0.85); }
  20% { opacity: 1; transform: rotateX(58deg) rotateZ(-18deg) scale(1); }
  100% { opacity: 0.4; transform: rotateX(58deg) rotateZ(12deg) scale(1.05); }
}
@keyframes nat-dot {
  0%, 100% { transform: scale(1); opacity: 0.7; }
  50% { transform: scale(1.35); opacity: 1; }
}
@keyframes nat-pop {
  from { opacity: 0; transform: translateY(8px) scale(0.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes nat-pulse {
  0%, 100% { box-shadow: 0 0 0 2px rgba(216, 180, 254, 0.95), 0 0 0 7px rgba(115, 62, 135, 0.16); }
  50% { box-shadow: 0 0 0 2px rgba(216, 180, 254, 1), 0 0 0 12px rgba(115, 62, 135, 0.1); }
}

@media (max-width: 800px) {
  .nat-intro__name { font-size: 22px; }
  .nat-tour__label { font-size: 12px; }
  .nat-tour__body { font-size: 11px; }
}
</style>
