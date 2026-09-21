<template>
  <div
    class="nav-progress"
    :class="{ 'nav-progress--active': show }"
    aria-hidden="true"
  >
    <div class="nav-progress__bar" />
  </div>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
})
</script>

<style scoped>
.nav-progress {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  z-index: 10050;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.12s ease;
}

.nav-progress--active {
  opacity: 1;
}

.nav-progress__bar {
  height: 100%;
  width: 0;
  background: linear-gradient(90deg, #3b82f6, #60a5fa, #93c5fd);
  box-shadow: 0 0 8px rgba(59, 130, 246, 0.45);
}

.nav-progress--active .nav-progress__bar {
  animation: nav-progress-run 1.1s ease-in-out infinite;
}

@keyframes nav-progress-run {
  0% {
    width: 0;
    margin-left: 0;
  }
  50% {
    width: 70%;
    margin-left: 15%;
  }
  100% {
    width: 0;
    margin-left: 100%;
  }
}
</style>
