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

const logoSrc = '/assets/images/altcrm-logo.png'

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
  background: rgba(243, 242, 246, 0.55);
  backdrop-filter: blur(8px);
}

.brand-loader--inline {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 220px;
}

.brand-loader__card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 28px 36px 22px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(107, 33, 168, 0.12);
  box-shadow: 0 18px 48px rgba(30, 27, 46, 0.12);
}

.brand-loader__ring {
  position: absolute;
  top: 22px;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  border: 2px solid rgba(107, 33, 168, 0.15);
  border-top-color: #7c3aed;
  animation: brand-loader-spin 0.8s linear infinite;
}

.brand-loader__logo {
  position: relative;
  z-index: 1;
  width: 64px;
  height: 64px;
  object-fit: contain;
  animation: brand-loader-pulse 1.4s ease-in-out infinite;
}

.brand-loader__label {
  margin: 8px 0 0;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #4c1d95;
}

@keyframes brand-loader-spin {
  to { transform: rotate(360deg); }
}

@keyframes brand-loader-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}
</style>
