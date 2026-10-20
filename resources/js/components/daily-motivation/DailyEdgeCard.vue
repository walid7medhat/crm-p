<template>
  <article
    class="edge-card"
    :class="{ 'is-open': revealed, 'is-test': isTest }"
    dir="ltr"
    role="dialog"
    aria-modal="false"
    :aria-label="isTest ? 'Daily Message private test' : 'Daily Message'"
  >
    <header class="edge-card__top">
      <div class="edge-card__mark" aria-hidden="true">
        <iconify-icon icon="lucide:sparkles" />
      </div>
      <div class="edge-card__intro">
        <p class="edge-card__kicker">Daily Message</p>
        <p class="edge-card__date">{{ dateLabel || 'Today' }}</p>
      </div>
      <button
        v-if="dismissible"
        type="button"
        class="edge-card__close"
        aria-label="Close today's message"
        @click.stop="$emit('close')"
      >
        <iconify-icon icon="lucide:x" />
      </button>
    </header>

    <button
      type="button"
      class="flip"
      :class="{ 'is-open': revealed }"
      @click="reveal"
    >
      <span class="flip__inner">
        <span class="face face--front">
          <span class="face__glow" aria-hidden="true"></span>
          <iconify-icon icon="lucide:sparkles" />
          <strong>Today's message</strong>
          <em>Tap to open</em>
        </span>
        <span class="face face--back">
          <span v-if="isTest" class="edge-card__test">Private test</span>
          <blockquote>{{ englishText }}</blockquote>
          <span class="face__shine" aria-hidden="true"></span>
        </span>
      </span>
    </button>
  </article>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  dateLabel: { type: String, default: '' },
  message: { type: Object, default: null },
  embedded: { type: Boolean, default: false },
  dismissible: { type: Boolean, default: true },
})

defineEmits(['close'])

const revealed = ref(false)
const isTest = computed(() => !!props.message?.test)
const englishText = computed(() => props.message?.body_en || '')

function reveal() {
  revealed.value = true
}
</script>

<style scoped>
.edge-card {
  width: 100%;
  padding: 8px;
  color: #0b0736;
  background: #ffffff;
  border: 1px solid #ebecef;
  border-radius: 14px;
  box-shadow: 0 18px 40px rgba(11, 7, 54, 0.14);
  font-family: Inter, system-ui, sans-serif;
}

.edge-card.is-open {
  animation: edge-lift 0.7s cubic-bezier(0.22, 1, 0.36, 1);
}

.edge-card__top {
  display: flex;
  align-items: center;
  gap: 6px;
}

.edge-card__mark {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  flex: none;
  border-radius: 8px;
  color: #ffffff !important;
  background: var(--gradient-primary) !important;
  font-size: 13px;
}

.edge-card__intro {
  min-width: 0;
  flex: 1;
}

.edge-card__kicker {
  margin: 0;
  color: var(--color-primary);
  font-size: 10px !important;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.edge-card__date {
  margin: 1px 0 0;
  color: #6b7280;
  font-size: 10px !important;
  line-height: 1.3;
}

.edge-card__close {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  font-size: 12px;
  flex: none;
  border: 0;
  border-radius: 999px;
  color: var(--color-primary);
  background: #f5f6fa;
  cursor: pointer;
}

.edge-card__close:focus-visible,
.flip:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.flip {
  display: block;
  width: 100%;
  margin-top: 8px;
  padding: 0;
  border: 0;
  background: transparent;
  perspective: 1100px;
  cursor: pointer;
  text-align: left;
}

.flip.is-open {
  cursor: default;
}

.flip__inner {
  display: grid;
  min-height: 108px;
  transform-style: preserve-3d;
  transition: transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}

.flip.is-open .flip__inner {
  transform: rotateY(180deg);
}

.face {
  grid-area: 1 / 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 108px;
  padding: 12px;
  border-radius: 10px;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
}

.face--front {
  position: relative;
  align-items: flex-start;
  overflow: hidden;
  color: var(--color-primary);
  background:
    radial-gradient(80% 80% at 100% 0%, rgba(255, 255, 255, 0.22), transparent 46%),
    var(--color-purple-light);
}

.face--front iconify-icon {
  font-size: 16px;
}

.face--front strong {
  margin-top: 8px;
  font-size: 14px !important;
  font-weight: 650;
  letter-spacing: -0.02em;
}

.face--front em {
  margin-top: 4px;
  color: rgba(255, 255, 255, 0.82);
  font-size: 11px !important;
  font-style: normal;
}

.face__glow {
  position: absolute;
  width: 90px;
  height: 90px;
  right: -30px;
  bottom: -46px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  animation: edge-pulse 2.4s ease-in-out infinite;
}

.face--back {
  position: relative;
  overflow: hidden;
  color: #0b0736;
  background: #f7f4fa;
  transform: rotateY(180deg);
}

.face--back blockquote {
  margin: 0;
  font-size: 12px !important;
  font-weight: 600;
  line-height: 1.35;
}

.face__shine {
  position: absolute;
  inset: 0;
  background: linear-gradient(115deg, transparent 28%, rgba(255, 255, 255, 0.7) 48%, transparent 68%);
  transform: translateX(-130%);
  pointer-events: none;
}

.flip.is-open .face__shine {
  animation: edge-shine 0.85s 0.35s ease forwards;
}

.edge-card__test {
  align-self: flex-start;
  margin: 0 0 8px;
  padding: 2px 8px;
  border-radius: 999px;
  color: #ffffff !important;
  background: var(--gradient-primary) !important;
  font-size: 11px !important;
  font-weight: 700;
}

@keyframes edge-lift {
  0% { transform: scale(1); }
  35% { transform: scale(1.035); }
  100% { transform: scale(1); }
}

@keyframes edge-pulse {
  0%, 100% { transform: scale(1); opacity: 0.7; }
  50% { transform: scale(1.12); opacity: 1; }
}

@keyframes edge-shine {
  to { transform: translateX(130%); }
}

@media (prefers-reduced-motion: reduce) {
  .flip__inner,
  .face__glow,
  .edge-card.is-open {
    animation: none;
    transition: none;
  }

  .flip.is-open .flip__inner {
    transform: none;
  }

  .face--front {
    display: none;
  }

  .face--back {
    transform: none;
  }
}
</style>
