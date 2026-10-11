<template>
  <div
    class="auth-mobile-stack"
    role="group"
    aria-label="Product previews"
    @pointerdown="onPointerDown"
    @pointerup="onPointerUp"
    @pointercancel="onPointerUp"
    @pointerleave="onPointerUp"
  >
    <div class="auth-mobile-stack__stage">
      <article
        v-for="(card, index) in cards"
        :key="card.id"
        class="auth-mobile-stack__card"
        :class="placementClass(index)"
      >
        <img :src="card.image" :alt="card.title" draggable="false" />
      </article>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const cards = [
  {
    id: 'pipeline',
    title: 'Your sales pipeline, simplified',
    image: '/assets/images/auth/mockup-pipeline.png?v=5',
  },
  {
    id: 'analytics',
    title: 'Analyze sales and team performance easily',
    image: '/assets/images/auth/mockup-analytics.png?v=5',
  },
  {
    id: 'mobile',
    title: 'Mobile CRM built for faster teamwork',
    image: '/assets/images/auth/mockup-mobile.png?v=5',
  },
];

const active = ref(0);
let pointerStartX = 0;
let pointerStartY = 0;
let tracking = false;
let cycleTimer = null;

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function isMobileAuth() {
  return window.matchMedia('(max-width: 768px)').matches;
}

function placementClass(index) {
  const depth = (index - active.value + cards.length) % cards.length;
  if (depth === 0) return 'is-front';
  if (depth === 1) return 'is-mid';
  return 'is-back';
}

function step(direction) {
  active.value = (active.value + direction + cards.length) % cards.length;
}

function onPointerDown(event) {
  if (!isMobileAuth()) return;
  tracking = true;
  pointerStartX = event.clientX;
  pointerStartY = event.clientY;
  stopCycle();
}

function onPointerUp(event) {
  if (!tracking) return;
  tracking = false;
  const dx = event.clientX - pointerStartX;
  const dy = event.clientY - pointerStartY;
  if (Math.abs(dx) > 28 && Math.abs(dx) > Math.abs(dy)) {
    step(dx < 0 ? 1 : -1);
  }
  startCycle();
}

function startCycle() {
  stopCycle();
  if (!isMobileAuth() || prefersReducedMotion()) return;
  cycleTimer = window.setInterval(() => step(1), 4200);
}

function stopCycle() {
  if (cycleTimer) {
    clearInterval(cycleTimer);
    cycleTimer = null;
  }
}

onMounted(startCycle);
onUnmounted(stopCycle);
</script>

<style scoped>
.auth-mobile-stack {
  display: none;
}

@media (max-width: 768px) {
  .auth-mobile-stack {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 156px;
    margin: 4px 0 0;
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
  }

  .auth-mobile-stack__stage {
    position: relative;
    width: 230px;
    height: 140px;
    animation: auth-stack-float 5.6s ease-in-out infinite;
  }

  .auth-mobile-stack__card {
    position: absolute;
    left: 50%;
    top: 52%;
    width: 168px;
    height: 108px;
    margin-left: -84px;
    margin-top: -54px;
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    border: 3px solid #fff;
    box-shadow: 0 16px 30px rgba(1, 6, 45, 0.16);
    transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
  }

  .auth-mobile-stack__card img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    pointer-events: none;
  }

  .auth-mobile-stack__card.is-front {
    z-index: 3;
    transform: translateY(12px) rotate(-6deg) scale(1);
  }

  .auth-mobile-stack__card.is-mid {
    z-index: 2;
    transform: translate(28px, -6px) rotate(9deg) scale(0.9);
  }

  .auth-mobile-stack__card.is-back {
    z-index: 1;
    transform: translate(-30px, -14px) rotate(-13deg) scale(0.82);
  }
}

@media (max-width: 768px) and (prefers-reduced-motion: reduce) {
  .auth-mobile-stack__stage {
    animation: none;
  }

  .auth-mobile-stack__card {
    transition: none;
  }
}

@keyframes auth-stack-float {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}
</style>
