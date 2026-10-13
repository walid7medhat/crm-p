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
      <span class="brand-loader__bar" aria-hidden="true"><i /></span>
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
  background: #f6f7f6;
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
  gap: 8px;
  padding: 0;
  background: transparent;
  border: 0;
  box-shadow: none;
}

.brand-loader__mark {
  position: relative;
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
}

.brand-loader__ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: conic-gradient(from 0deg, rgba(var(--color-primary-rgb), 0.08), var(--color-primary) 40%, rgba(var(--color-primary-rgb), 0));
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 1.5px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 1.5px));
  animation: brand-loader-spin 0.7s linear infinite;
}

.brand-loader__core {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: transparent;
  color: var(--color-primary);
  font-size: 12px !important;
  font-weight: 800;
  box-shadow: none;
}

.brand-loader__name {
  margin: 0;
  font-size: 12px !important;
  font-weight: 700 !important;
  letter-spacing: 0.14em;
  color: #171717;
  line-height: 1;
}

.brand-loader__name span {
  color: var(--color-primary);
}

.brand-loader__label {
  margin: 0;
  font-size: 11px !important;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.brand-loader__bar {
  width: 64px;
  height: 2px;
  border-radius: 999px;
  background: var(--color-primary-soft);
  overflow: hidden;
}

.brand-loader__bar i {
  display: block;
  width: 40%;
  height: 100%;
  border-radius: inherit;
  background: var(--gradient-primary);
  animation: brand-loader-bar 1.1s ease-in-out infinite;
}

@keyframes brand-loader-spin {
  to { transform: rotate(360deg); }
}

@keyframes brand-loader-bar {
  0% { transform: translateX(-120%); }
  100% { transform: translateX(280%); }
}

@media (prefers-reduced-motion: reduce) {
  .brand-loader__ring,
  .brand-loader__bar i {
    animation: none;
  }
}
</style>
