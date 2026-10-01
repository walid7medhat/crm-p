<template>
  <div
    class="brand-loader"
    :class="variant === 'overlay' ? 'brand-loader--overlay' : 'brand-loader--inline'"
    role="status"
    aria-live="polite"
    :aria-label="label"
  >
    <div class="brand-loader__card">
      <div class="brand-loader__ring" aria-hidden="true" />
      <img
        :src="logoSrc"
        alt=""
        class="brand-loader__logo"
        width="72"
        height="72"
        decoding="async"
      />
      <p class="brand-loader__label">{{ label }}</p>
    </div>
  </div>
</template>

<script setup>
defineOptions({ name: 'BrandLoader' })

const logoSrc = '/assets/images/pwa/icon-512.png'

defineProps({
  label: {
    type: String,
    default: 'Loading',
  },
  variant: {
    type: String,
    default: 'overlay',
  },
})
</script>

<style scoped>
.brand-loader--overlay {
  position: fixed;
  inset: 0;
  z-index: 10040;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #000000;
}

.brand-loader--inline {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 220px;
  background: #000000;
}

.brand-loader__card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
  padding: 0;
  background: transparent;
  border: 0;
  box-shadow: none;
}

.brand-loader__ring {
  display: none;
}

.brand-loader__logo {
  position: relative;
  z-index: 1;
  width: min(64vw, 240px);
  height: auto;
  object-fit: contain;
}

.brand-loader__label {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #d4d4d8;
}

@keyframes brand-loader-spin {
  to { transform: rotate(360deg); }
}

@keyframes brand-loader-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}
</style>
