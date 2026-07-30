<template>
  <div class="voice-wave" :class="{ 'voice-wave--active': active }" aria-hidden="true">
    <span v-for="n in bars" :key="n" class="voice-wave__bar" :style="barStyle(n)" />
  </div>
</template>

<script setup>
defineProps({
  active: {
    type: Boolean,
    default: false,
  },
  bars: {
    type: Number,
    default: 5,
  },
});

function barStyle(n) {
  const delay = ((n - 1) * 0.12).toFixed(2);
  return { animationDelay: `${delay}s` };
}
</script>

<style scoped>
.voice-wave {
  display: inline-flex;
  align-items: flex-end;
  justify-content: center;
  gap: 4px;
  height: 28px;
  min-width: 40px;
}

.voice-wave__bar {
  width: 4px;
  height: 8px;
  border-radius: 999px;
  background: #c4b5d4;
  transition: background 0.2s ease;
}

.voice-wave--active .voice-wave__bar {
  background: #733e87;
  animation: voice-wave-pulse 0.9s ease-in-out infinite;
}

@keyframes voice-wave-pulse {
  0%,
  100% {
    height: 8px;
    opacity: 0.55;
  }
  50% {
    height: 26px;
    opacity: 1;
  }
}
</style>
