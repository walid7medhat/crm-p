<template>
  <div
    class="brand-loader"
    :class="variant === 'overlay' ? 'brand-loader--overlay' : 'brand-loader--inline'"
    role="status"
    aria-live="polite"
    :aria-label="label"
  >
    <div class="brand-loader__card">
      <div class="brand-loader__mark" aria-hidden="true">
        <span class="brand-loader__ring" />
        <span class="brand-loader__core">A</span>
      </div>
      <p class="brand-loader__name"><span>ALT</span> CRM</p>
      <p class="brand-loader__label">{{ label }}</p>
    </div>
  </div>
</template>

<script setup>
defineOptions({ name: 'BrandLoader' })

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
  background: #f7f4fb;
}

.brand-loader--inline {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 220px;
  background: #ffffff;
}

.brand-loader__card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 0;
  background: transparent;
  border: 0;
  box-shadow: none;
}

.brand-loader__mark {
  position: relative;
  width: 56px;
  height: 56px;
  display: grid;
  place-items: center;
}

.brand-loader__ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: conic-gradient(from 0deg, rgba(115, 62, 135, 0.08), #733e87 40%, rgba(115, 62, 135, 0));
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 2px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 2px));
  animation: brand-loader-spin 0.7s linear infinite;
}

.brand-loader__core {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #fff;
  color: #733e87;
  font-size: 18px !important;
  font-weight: 800;
  box-shadow: 0 8px 20px rgba(115, 62, 135, 0.16);
}

.brand-loader__name {
  margin: 0;
  font-size: 13px !important;
  font-weight: 800 !important;
  letter-spacing: 0.2em;
  color: #1a1528;
  line-height: 1;
}

.brand-loader__name span {
  color: #733e87;
}

.brand-loader__label {
  margin: 0;
  font-size: 12px !important;
  font-weight: 600;
  letter-spacing: 0.08em;
  color: #6b7280;
}

@keyframes brand-loader-spin {
  to { transform: rotate(360deg); }
}

@keyframes brand-loader-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}
</style>
