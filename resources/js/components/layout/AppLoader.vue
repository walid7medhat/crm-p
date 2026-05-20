<template>
  <Teleport to="body">
    <Transition name="app-loader" @after-leave="onAfterLeave">
      <div
        v-if="show"
        class="app-loader"
        role="status"
        aria-live="polite"
        aria-busy="true"
        aria-label="Loading Alt CRM"
      >
        <div class="app-loader__bg" aria-hidden="true">
          <div class="app-loader__gradient app-loader__gradient--a" />
          <div class="app-loader__gradient app-loader__gradient--b" />
          <div class="app-loader__gradient app-loader__gradient--c" />
          <div class="app-loader__grid" />
          <span
            v-for="particle in particles"
            :key="particle.id"
            class="app-loader__particle"
            :style="particle.style"
          />
        </div>

        <div class="app-loader__panel">
          <div class="app-loader__logo-stage">
            <div class="app-loader__glow app-loader__glow--outer" aria-hidden="true" />
            <div class="app-loader__glow app-loader__glow--inner" aria-hidden="true" />
            <div class="app-loader__logo-wrap">
              <img
                :src="logoSrc"
                alt="Alt CRM"
                class="app-loader__logo"
                width="120"
                height="120"
                decoding="async"
                fetchpriority="high"
              />
            </div>
          </div>

          <p class="app-loader__brand">Alt CRM</p>
          <p class="app-loader__text">Loading Alt CRM...</p>

          <div class="app-loader__progress" aria-hidden="true">
            <span class="app-loader__progress-line" />
          </div>

          <div class="app-loader__dots" aria-hidden="true">
            <span class="app-loader__dot" />
            <span class="app-loader__dot" />
            <span class="app-loader__dot" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({ name: 'AppLoader' })

const props = defineProps({
  show: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['hidden'])

const logoSrc = '/assets/images/altcrm-logo.png'

/** Lightweight CSS-only particles — no canvas / GSAP */
const particles = computed(() =>
  Array.from({ length: 18 }, (_, i) => {
    const left = 8 + ((i * 17) % 84)
    const delay = (i * 0.35) % 4
    const duration = 6 + (i % 5)
    const size = 2 + (i % 3)
    const opacity = 0.25 + (i % 4) * 0.12
    return {
      id: i,
      style: {
        left: `${left}%`,
        top: `${12 + ((i * 23) % 76)}%`,
        width: `${size}px`,
        height: `${size}px`,
        opacity,
        animationDuration: `${duration}s`,
        animationDelay: `${delay}s`,
      },
    }
  })
)

function onAfterLeave() {
  emit('hidden')
}
</script>

<style scoped>
.app-loader {
  /* Alt CRM system tokens (style14.css --crm-primary / --crm-secondary) */
  --loader-bg: #0b0736;
  --loader-bg-mid: #1a0a42;
  --loader-primary: #0b0736;
  --loader-secondary: #733e87;
  --loader-accent: #c026d3;
  --loader-white: #ffffff;
  --loader-gradient: linear-gradient(135deg, #0b0736 0%, #733e87 100%);
  --loader-glass: linear-gradient(
    135deg,
    rgba(11, 7, 54, 0.72) 0%,
    rgba(115, 62, 135, 0.45) 100%
  );
  --loader-border: rgba(255, 255, 255, 0.14);

  position: fixed;
  inset: 0;
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  -webkit-font-smoothing: antialiased;
  overflow: hidden;
  pointer-events: none;
}

.app-loader__bg {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  opacity: 0.35;
}

.app-loader__gradient {
  position: absolute;
  border-radius: 50%;
  filter: blur(72px);
  opacity: 0.55;
  will-change: transform;
}

.app-loader__gradient--a {
  width: min(55vw, 420px);
  height: min(55vw, 420px);
  top: -12%;
  left: -8%;
  background: radial-gradient(circle, rgba(115, 62, 135, 0.5) 0%, transparent 70%);
  animation: loader-drift-a 14s ease-in-out infinite;
}

.app-loader__gradient--b {
  width: min(50vw, 380px);
  height: min(50vw, 380px);
  bottom: -15%;
  right: -10%;
  background: radial-gradient(circle, rgba(192, 38, 211, 0.35) 0%, transparent 72%);
  animation: loader-drift-b 16s ease-in-out infinite;
}

.app-loader__gradient--c {
  width: min(40vw, 300px);
  height: min(40vw, 300px);
  top: 42%;
  left: 38%;
  background: radial-gradient(circle, rgba(26, 10, 66, 0.85) 0%, transparent 68%);
  animation: loader-drift-c 12s ease-in-out infinite;
}

.app-loader__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
  background-size: 48px 48px;
  mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, #000 20%, transparent 75%);
  opacity: 0.5;
}

.app-loader__particle {
  position: absolute;
  border-radius: 50%;
  background: var(--loader-gradient);
  box-shadow: 0 0 12px rgba(115, 62, 135, 0.45);
  animation: loader-float linear infinite;
}

.app-loader__panel {
  position: relative;
  z-index: 2;
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  padding: 2.5rem 2.75rem 2.25rem;
  border-radius: 28px;
  background: var(--loader-glass);
  border: 1px solid var(--loader-border);
  backdrop-filter: blur(20px) saturate(1.4);
  -webkit-backdrop-filter: blur(20px) saturate(1.4);
  box-shadow:
    0 0 0 1px rgba(115, 62, 135, 0.2) inset,
    0 24px 80px rgba(0, 0, 0, 0.45),
    0 0 60px rgba(115, 62, 135, 0.22);
  animation: loader-panel-in 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
  max-width: calc(100vw - 2rem);
}

.app-loader__logo-stage {
  position: relative;
  width: 148px;
  height: 148px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.5rem;
}

.app-loader__glow {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}

.app-loader__glow--outer {
  inset: -8px;
  background: radial-gradient(
    circle,
    rgba(115, 62, 135, 0.45) 0%,
    rgba(192, 38, 211, 0.2) 45%,
    transparent 70%
  );
  animation: loader-glow-pulse 2.8s ease-in-out infinite;
}

.app-loader__glow--inner {
  inset: 12px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, transparent 65%);
  animation: loader-glow-pulse 2.8s ease-in-out infinite 0.4s;
}

.app-loader__logo-wrap {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: loader-logo-in 1s cubic-bezier(0.22, 1, 0.36, 1) both,
    loader-logo-breathe 3.2s ease-in-out 1s infinite;
}

.app-loader__logo {
  width: clamp(72px, 18vw, 108px);
  height: auto;
  object-fit: contain;
  filter: drop-shadow(0 8px 24px rgba(115, 62, 135, 0.5));
}

.app-loader__brand {
  margin: 0 0 0.25rem;
  font-size: 1.125rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--loader-white);
  opacity: 0.92;
  animation: loader-text-in 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
}

.app-loader__text {
  margin: 0 0 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.55);
  letter-spacing: 0.02em;
  animation: loader-text-in 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.25s both;
}

.app-loader__progress {
  width: min(220px, 70vw);
  height: 3px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  overflow: hidden;
  margin-bottom: 1rem;
  animation: loader-text-in 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.35s both;
}

.app-loader__progress-line {
  display: block;
  height: 100%;
  width: 40%;
  border-radius: inherit;
  background: linear-gradient(
    90deg,
    transparent,
    var(--loader-secondary),
    var(--loader-accent),
    transparent
  );
  animation: loader-progress 1.6s ease-in-out infinite;
}

.app-loader__dots {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  animation: loader-text-in 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.45s both;
}

.app-loader__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--loader-gradient);
  box-shadow: 0 0 10px rgba(115, 62, 135, 0.55);
  animation: loader-dot 1.2s ease-in-out infinite;
}

.app-loader__dot:nth-child(2) {
  animation-delay: 0.15s;
}

.app-loader__dot:nth-child(3) {
  animation-delay: 0.3s;
}

/* Enter / leave transitions */
.app-loader-enter-active {
  transition: opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}

.app-loader-leave-active {
  transition:
    opacity 0.65s cubic-bezier(0.4, 0, 0.2, 1),
    transform 0.65s cubic-bezier(0.4, 0, 0.2, 1);
}

.app-loader-enter-from,
.app-loader-leave-to {
  opacity: 0;
}

.app-loader-leave-to .app-loader__panel {
  transform: scale(0.96) translateY(8px);
  opacity: 0;
  transition:
    transform 0.65s cubic-bezier(0.4, 0, 0.2, 1),
    opacity 0.5s ease;
}

@keyframes loader-panel-in {
  from {
    opacity: 0;
    transform: scale(0.92) translateY(12px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@keyframes loader-logo-in {
  from {
    opacity: 0;
    transform: scale(0.82);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes loader-logo-breathe {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.04);
  }
}

@keyframes loader-glow-pulse {
  0%,
  100% {
    opacity: 0.65;
    transform: scale(1);
  }
  50% {
    opacity: 1;
    transform: scale(1.08);
  }
}

@keyframes loader-text-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes loader-progress {
  0% {
    transform: translateX(-120%);
  }
  100% {
    transform: translateX(320%);
  }
}

@keyframes loader-dot {
  0%,
  80%,
  100% {
    transform: scale(0.75);
    opacity: 0.45;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}

@keyframes loader-drift-a {
  0%,
  100% {
    transform: translate(0, 0) scale(1);
  }
  50% {
    transform: translate(6%, 4%) scale(1.06);
  }
}

@keyframes loader-drift-b {
  0%,
  100% {
    transform: translate(0, 0) scale(1);
  }
  50% {
    transform: translate(-5%, -6%) scale(1.05);
  }
}

@keyframes loader-drift-c {
  0%,
  100% {
    transform: translate(-50%, -50%) scale(1);
  }
  50% {
    transform: translate(-48%, -52%) scale(1.1);
  }
}

@keyframes loader-float {
  0% {
    transform: translateY(0) translateX(0);
  }
  50% {
    transform: translateY(-18px) translateX(6px);
  }
  100% {
    transform: translateY(0) translateX(0);
  }
}

@media (max-width: 768px) {
  .app-loader__panel {
    padding: 2rem 1.75rem 1.75rem;
    border-radius: 22px;
  }

  .app-loader__logo-stage {
    width: 128px;
    height: 128px;
  }

  .app-loader__brand {
    font-size: 1rem;
    letter-spacing: 0.16em;
  }

  .app-loader__text {
    font-size: 0.8125rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .app-loader__gradient,
  .app-loader__particle,
  .app-loader__glow,
  .app-loader__logo-wrap,
  .app-loader__progress-line,
  .app-loader__dot {
    animation: none !important;
  }

  .app-loader__panel,
  .app-loader__brand,
  .app-loader__text,
  .app-loader__progress,
  .app-loader__dots {
    animation: none !important;
    opacity: 1;
    transform: none;
  }
}
</style>
