<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="login-welcome"
      :class="{ 'is-leaving': leaving, 'is-reduced': reduced }"
      role="status"
      aria-live="polite"
      :aria-label="ariaLabel"
      @click="dismiss"
      @keydown.escape.prevent="dismiss"
    >
      <div class="login-welcome__wash" aria-hidden="true" />
      <span
        v-for="orb in orbs"
        :key="orb.id"
        class="login-welcome__orb"
        :style="orb.style"
        aria-hidden="true"
      />
      <span
        v-for="spark in sparks"
        :key="spark.id"
        class="login-welcome__spark"
        :style="spark.style"
        aria-hidden="true"
      />

      <div class="login-welcome__stage">
        <div class="login-welcome__mark" aria-hidden="true">
          <span class="login-welcome__ring" />
          <span class="login-welcome__ring login-welcome__ring--inner" />
          <span class="login-welcome__gem" />
        </div>
        <p class="login-welcome__eyebrow">Welcome</p>
        <p class="login-welcome__hello">{{ copy.hello }}</p>
        <h1 v-if="copy.first" class="login-welcome__name">{{ copy.first }}</h1>
        <p class="login-welcome__line">{{ copy.line }}</p>
        <div class="login-welcome__track" aria-hidden="true">
          <span class="login-welcome__track-fill" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { takeLoginWelcome, welcomeCopy } from '@/composables/useLoginWelcome'

defineOptions({ name: 'LoginWelcomeSplash' })

const HOLD_MS = 2600
const LEAVE_MS = 420
const REDUCED_HOLD_MS = 1200

const payload = takeLoginWelcome()
const open = ref(!!payload)
const leaving = ref(false)
const reduced = ref(
  typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches,
)
const copy = welcomeCopy(payload?.name || '')

const ariaLabel = computed(() => {
  const who = copy.first ? `${copy.hello}, ${copy.first}.` : `${copy.hello}.`
  return `${who} ${copy.line}`
})

const orbs = [
  { id: 1, style: { width: '42vw', height: '42vw', left: '-12vw', top: '-14vw', animationDelay: '0s' } },
  { id: 2, style: { width: '36vw', height: '36vw', right: '-10vw', bottom: '-16vw', animationDelay: '-1.4s' } },
  { id: 3, style: { width: '18vw', height: '18vw', right: '18vw', top: '8vh', animationDelay: '-0.7s' } },
]

const sparks = Array.from({ length: 14 }, (_, index) => {
  const left = 8 + ((index * 37) % 84)
  const top = 10 + ((index * 53) % 78)
  const delay = (index % 7) * 0.18
  const size = index % 3 === 0 ? 7 : 4
  return {
    id: index,
    style: {
      left: `${left}%`,
      top: `${top}%`,
      width: `${size}px`,
      height: `${size}px`,
      animationDelay: `${delay}s`,
    },
  }
})

let holdTimer = 0
let leaveTimer = 0
let finished = false

function finish() {
  if (finished) return
  finished = true
  window.clearTimeout(holdTimer)
  window.clearTimeout(leaveTimer)
  open.value = false
  window.removeEventListener('keydown', onKey)
}

function dismiss() {
  if (!open.value || leaving.value) {
    finish()
    return
  }
  leaving.value = true
  window.clearTimeout(holdTimer)
  leaveTimer = window.setTimeout(finish, LEAVE_MS)
}

function onKey(event) {
  if (event.key === 'Escape') dismiss()
}

onMounted(() => {
  if (!open.value) return
  window.addEventListener('keydown', onKey)
  holdTimer = window.setTimeout(dismiss, reduced.value ? REDUCED_HOLD_MS : HOLD_MS)
})

onUnmounted(() => {
  window.clearTimeout(holdTimer)
  window.clearTimeout(leaveTimer)
  window.removeEventListener('keydown', onKey)
})
</script>

<style scoped>
.login-welcome {
  position: fixed;
  inset: 0;
  z-index: 100000;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: #fff;
  cursor: pointer;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background-color: #3f2148;
  background-image:
    radial-gradient(120% 80% at 50% 42%, rgba(143, 90, 163, 0.55) 0%, rgba(63, 33, 72, 0.2) 42%, transparent 70%),
    linear-gradient(160deg, #2a1434 0%, #4a285c 48%, #733e87 100%);
  animation: login-welcome-in 0.28s ease both;
}

.login-welcome.is-leaving {
  animation: login-welcome-out 0.42s ease forwards;
  pointer-events: none;
}

.login-welcome__wash {
  position: absolute;
  inset: -20%;
  background: linear-gradient(115deg, transparent 35%, rgba(243, 226, 165, 0.16) 48%, transparent 62%);
  animation: login-welcome-sheen 2.4s ease both;
  pointer-events: none;
}

.login-welcome__orb {
  position: absolute;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(212, 175, 55, 0.45) 0%, rgba(212, 175, 55, 0) 68%);
  filter: blur(8px);
  animation: login-welcome-float 3.2s ease-in-out infinite;
  pointer-events: none;
}

.login-welcome__spark {
  position: absolute;
  border-radius: 50%;
  background: #f3e2a5;
  box-shadow: 0 0 10px rgba(243, 226, 165, 0.85);
  animation: login-welcome-twinkle 1.6s ease-in-out infinite;
  pointer-events: none;
}

.login-welcome__stage {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: min(640px, calc(100% - 48px));
  text-align: center;
  padding: 12px 8px 8px;
}

.login-welcome__mark {
  position: relative;
  width: 72px;
  height: 72px;
  margin-bottom: 22px;
}

.login-welcome__ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 1.5px solid rgba(243, 226, 165, 0.85);
  animation: login-welcome-ring 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.login-welcome__ring--inner {
  inset: 10px;
  border-color: rgba(255, 255, 255, 0.35);
  animation-delay: 0.12s;
}

.login-welcome__gem {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 12px;
  height: 12px;
  margin: -6px 0 0 -6px;
  background: linear-gradient(135deg, #fff 0%, #f3e2a5 45%, #d4af37 100%);
  transform: rotate(45deg) scale(0);
  animation: login-welcome-gem 0.55s 0.18s cubic-bezier(0.2, 0.8, 0.2, 1.2) both;
  box-shadow: 0 0 18px rgba(212, 175, 55, 0.7);
}

.login-welcome__eyebrow {
  margin: 0 0 10px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.42em;
  text-indent: 0.42em;
  text-transform: uppercase;
  color: #f3e2a5;
  animation: login-welcome-rise 0.5s 0.08s ease both;
}

.login-welcome__hello {
  margin: 0;
  font-size: clamp(18px, 2.4vw, 22px);
  font-weight: 500;
  letter-spacing: 0.01em;
  color: rgba(255, 255, 255, 0.82);
  animation: login-welcome-rise 0.5s 0.16s ease both;
}

.login-welcome__name {
  margin: 6px 0 0;
  max-width: 100%;
  font-size: clamp(40px, 7vw, 72px);
  font-weight: 600;
  line-height: 1.05;
  letter-spacing: -0.035em;
  overflow-wrap: anywhere;
  color: #fff7df;
  animation: login-welcome-name 0.62s 0.22s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

@supports ((-webkit-background-clip: text) or (background-clip: text)) {
  .login-welcome__name {
    background: linear-gradient(180deg, #fff 20%, #f3e2a5 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }
}

.login-welcome__line {
  margin: 16px 0 0;
  max-width: 28rem;
  font-size: clamp(15px, 2vw, 18px);
  line-height: 1.45;
  color: rgba(255, 255, 255, 0.88);
  animation: login-welcome-rise 0.5s 0.38s ease both;
}

.login-welcome__track {
  width: min(180px, 46vw);
  height: 2px;
  margin-top: 28px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.18);
  overflow: hidden;
  animation: login-welcome-rise 0.4s 0.45s ease both;
}

.login-welcome__track-fill {
  display: block;
  height: 100%;
  width: 0;
  border-radius: inherit;
  background: linear-gradient(90deg, #f3e2a5, #d4af37);
  animation: login-welcome-bar 2.6s linear both;
}

@keyframes login-welcome-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes login-welcome-out {
  to { opacity: 0; }
}

@keyframes login-welcome-rise {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes login-welcome-name {
  from { opacity: 0; transform: translateY(14px) scale(0.96); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes login-welcome-ring {
  from { opacity: 0; transform: scale(0.6); }
  to { opacity: 1; transform: scale(1); }
}

@keyframes login-welcome-gem {
  to { transform: rotate(45deg) scale(1); }
}

@keyframes login-welcome-bar {
  to { width: 100%; }
}

@keyframes login-welcome-sheen {
  from { transform: translateX(-30%); }
  to { transform: translateX(30%); }
}

@keyframes login-welcome-float {
  0%, 100% { transform: translate3d(0, 0, 0); }
  50% { transform: translate3d(0, -12px, 0); }
}

@keyframes login-welcome-twinkle {
  0%, 100% { opacity: 0.15; transform: scale(0.6); }
  50% { opacity: 1; transform: scale(1); }
}

.login-welcome.is-reduced,
.login-welcome.is-reduced * {
  animation: none !important;
}

.login-welcome.is-reduced .login-welcome__track-fill {
  width: 100%;
}

@media (max-width: 640px) {
  .login-welcome__mark {
    width: 58px;
    height: 58px;
    margin-bottom: 16px;
  }

  .login-welcome__eyebrow {
    letter-spacing: 0.32em;
    text-indent: 0.32em;
  }
}
</style>
