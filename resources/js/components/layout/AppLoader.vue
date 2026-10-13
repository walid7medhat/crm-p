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
        <div class="app-loader__panel">
          <div class="alt-boot__mark" aria-hidden="true">
            <span class="alt-boot__ring" />
            <span class="alt-boot__core">A</span>
          </div>
          <p class="app-loader__brand"><span>ALT</span> CRM</p>
          <p class="app-loader__text">{{ label }}</p>
          <div class="app-loader__progress" aria-hidden="true">
            <span class="app-loader__progress-line" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineOptions({ name: 'AppLoader' })

defineProps({
  show: {
    type: Boolean,
    default: true,
  },
  label: {
    type: String,
    default: 'Loading...',
  },
})

const emit = defineEmits(['hidden'])

function onAfterLeave() {
  emit('hidden')
}
</script>

<style scoped>
.app-loader {
  --loader-primary: var(--color-primary);
  --loader-secondary: var(--color-primary);
  --loader-accent: var(--color-primary-bright);
  --loader-white: #171717;
  --loader-gradient: var(--gradient-primary);
  --loader-border: rgba(var(--color-primary-rgb), 0.16);

  position: fixed;
  inset: 0;
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f6f7f6;
  font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  -webkit-font-smoothing: antialiased;
  overflow: hidden;
  pointer-events: none;
}

.app-loader__bg {
  display: none;
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
  background: radial-gradient(circle, rgba(167, 139, 250, 0.4) 0%, transparent 70%);
  animation: loader-drift-a 14s ease-in-out infinite;
}

.app-loader__gradient--b {
  width: min(50vw, 380px);
  height: min(50vw, 380px);
  bottom: -15%;
  right: -10%;
  background: radial-gradient(circle, rgba(196, 181, 253, 0.35) 0%, transparent 72%);
  animation: loader-drift-b 16s ease-in-out infinite;
}

.app-loader__gradient--c {
  width: min(40vw, 300px);
  height: min(40vw, 300px);
  top: 42%;
  left: 38%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.95) 0%, transparent 68%);
  animation: loader-drift-c 12s ease-in-out infinite;
}

.app-loader__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(var(--color-purple-rgb), 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(var(--color-purple-rgb), 0.05) 1px, transparent 1px);
  background-size: 48px 48px;
  mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, #000 20%, transparent 75%);
  opacity: 0.55;
}

.app-loader__particle {
  position: absolute;
  border-radius: 50%;
  background: var(--loader-gradient);
  box-shadow: 0 0 12px rgba(var(--color-purple-rgb), 0.45);
  animation: loader-float linear infinite;
}

.app-loader__panel {
  position: relative;
  z-index: 2;
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 0 !important;
  background: transparent !important;
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  animation: loader-panel-in 0.28s ease both;
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
  display: none;
}

.app-loader__glow--outer {
  inset: -8px;
  background: radial-gradient(
    circle,
    rgba(var(--color-purple-rgb), 0.45) 0%,
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
  width: min(72vw, 320px);
  height: auto;
  object-fit: contain;
}

.alt-boot__mark {
  position: relative;
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  margin: 0;
}

.alt-boot__ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: conic-gradient(from 0deg, rgba(var(--color-primary-rgb), 0.08), var(--color-primary) 40%, rgba(var(--color-primary-rgb), 0));
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 1.5px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 1.5px));
  animation: alt-boot-spin 0.7s linear infinite;
}

.alt-boot__core {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: transparent;
  color: var(--color-primary);
  font-size: 12px !important;
  font-weight: 800;
  letter-spacing: -0.04em;
  box-shadow: none;
}

.app-loader__brand {
  margin: 0;
  font-size: 12px !important;
  font-weight: 700 !important;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #171717;
  line-height: 1.2;
  animation: loader-text-in 0.28s ease both;
}

.app-loader__brand span {
  color: var(--color-primary);
}

@keyframes alt-boot-spin {
  to { transform: rotate(360deg); }
}

.app-loader__text {
  margin: 0;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  letter-spacing: 0.04em;
  animation: loader-text-in 0.28s ease both;
}

.app-loader__progress {
  width: 64px;
  height: 2px;
  border-radius: 999px;
  background: var(--color-primary-soft);
  overflow: hidden;
  margin: 0;
  animation: loader-text-in 0.28s ease both;
}

.app-loader__progress-line {
  display: block;
  height: 100%;
  width: 40%;
  border-radius: inherit;
  background: var(--gradient-primary);
  animation: loader-progress 1.1s ease-in-out infinite;
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
  box-shadow: 0 0 10px rgba(var(--color-purple-rgb), 0.55);
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
  transition: opacity 0.22s ease;
}

.app-loader-leave-active {
  transition: opacity 0.2s ease;
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
    padding: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  .app-loader__logo-stage {
    width: 128px;
    height: 128px;
  }

  .app-loader__brand {
    font-size: 12px !important;
    letter-spacing: 0.14em;
  }

  .app-loader__text {
    font-size: 11px;
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
