<template>
  <div class="maint" role="alertdialog" aria-modal="true" aria-labelledby="maint-heading">
    <div class="maint__atmosphere" aria-hidden="true">
      <span class="maint__orb maint__orb--a" />
      <span class="maint__orb maint__orb--b" />
      <span class="maint__grid" />
    </div>

    <div class="maint__panel">
      <div class="maint__mark" aria-hidden="true">
        <span class="maint__ring" />
        <span class="maint__ring maint__ring--delay" />
        <svg class="maint__icon" viewBox="0 0 48 48" fill="none">
          <path
            d="M24 14v10l6 4"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <circle cx="24" cy="24" r="14" stroke="currentColor" stroke-width="1.6" opacity="0.35" />
          <circle cx="24" cy="24" r="18" stroke="currentColor" stroke-width="1" opacity="0.16" />
        </svg>
      </div>

      <span class="maint__badge">Under maintenance</span>
      <h1 id="maint-heading" class="maint__heading">We'll be back soon</h1>
      <p v-if="announcementTitle" class="maint__announce">{{ announcementTitle }}</p>
      <p class="maint__message">{{ displayMessage }}</p>

      <div v-if="estimatedReturn" class="maint__eta">
        <span class="maint__eta-label">Estimated return</span>
        <span class="maint__eta-value">{{ estimatedReturn }}</span>
      </div>

      <p class="maint__hint">Your data is safe. Please check back shortly.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useMaintenanceMode } from '@/composables/useMaintenanceMode'

const { title, message, estimatedReturn } = useMaintenanceMode()

const announcementTitle = computed(() => {
  const t = String(title.value || '').trim()
  if (!t || t.toLowerCase() === "we'll be back soon") return ''
  return t
})

const displayMessage = computed(
  () =>
    String(message.value || '').trim() ||
    'The CRM is temporarily unavailable while we perform scheduled maintenance.'
)
</script>

<style scoped>
.maint {
  --maint-ink: #1c2430;
  --maint-muted: #6b7280;
  --maint-soft: #94a3b8;
  --maint-line: rgba(28, 36, 48, 0.08);
  --maint-accent: var(--color-primary, #003c38);

  position: fixed;
  inset: 0;
  z-index: 2147483000;
  display: grid;
  place-items: center;
  padding: 20px;
  overflow: auto;
  color: var(--maint-ink);
  font-family: 'Montserrat', 'Segoe UI', system-ui, sans-serif;
  background: #f4f6f8;
}

.maint__atmosphere {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
}

.maint__orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(40px);
  opacity: 0.55;
  animation: maint-float 10s ease-in-out infinite;
}

.maint__orb--a {
  width: min(420px, 70vw);
  height: min(420px, 70vw);
  top: -12%;
  left: -8%;
  background: color-mix(in srgb, var(--maint-accent) 22%, #ffffff);
}

.maint__orb--b {
  width: min(360px, 60vw);
  height: min(360px, 60vw);
  right: -10%;
  bottom: -16%;
  background: color-mix(in srgb, var(--maint-accent) 14%, #dbe4ea);
  animation-delay: -4s;
}

.maint__grid {
  position: absolute;
  inset: 0;
  opacity: 0.35;
  background-image:
    linear-gradient(rgba(28, 36, 48, 0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(28, 36, 48, 0.035) 1px, transparent 1px);
  background-size: 28px 28px;
  mask-image: radial-gradient(circle at center, black 20%, transparent 75%);
}

.maint__panel {
  position: relative;
  z-index: 1;
  width: min(380px, 100%);
  text-align: center;
  padding: 28px 22px 24px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow:
    0 1px 0 rgba(255, 255, 255, 0.8) inset,
    0 18px 50px rgba(28, 36, 48, 0.08);
  backdrop-filter: blur(16px);
  animation: maint-rise 0.55s ease both;
}

.maint__mark {
  position: relative;
  width: 64px;
  height: 64px;
  margin: 0 auto 14px;
  color: var(--maint-accent);
  display: grid;
  place-items: center;
}

.maint__ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 1px solid color-mix(in srgb, var(--maint-accent) 35%, transparent);
  animation: maint-ping 2.8s ease-out infinite;
}

.maint__ring--delay {
  animation-delay: 1.1s;
}

.maint__icon {
  width: 42px;
  height: 42px;
  position: relative;
  z-index: 1;
}

.maint__badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--maint-accent);
  background: color-mix(in srgb, var(--maint-accent) 10%, #fff);
  border: 1px solid color-mix(in srgb, var(--maint-accent) 22%, transparent);
}

.maint__heading {
  margin: 12px 0 6px;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.25;
}

.maint__announce {
  margin: 0 0 6px;
  font-size: 0.78rem;
  font-weight: 600;
  color: color-mix(in srgb, var(--maint-accent) 80%, var(--maint-ink));
}

.maint__message {
  margin: 0 auto;
  max-width: 34ch;
  font-size: 0.78rem;
  line-height: 1.55;
  color: var(--maint-muted);
  font-weight: 500;
}

.maint__eta {
  margin: 16px auto 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--maint-accent) 6%, #fff);
  border: 1px solid color-mix(in srgb, var(--maint-accent) 14%, transparent);
}

.maint__eta-label {
  display: block;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--maint-soft);
  margin-bottom: 3px;
}

.maint__eta-value {
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--maint-ink);
}

.maint__hint {
  margin: 14px 0 0;
  font-size: 0.68rem;
  color: var(--maint-soft);
  letter-spacing: 0.01em;
}

@keyframes maint-rise {
  from {
    opacity: 0;
    transform: translateY(12px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes maint-float {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(18px);
  }
}

@keyframes maint-ping {
  0% {
    transform: scale(0.82);
    opacity: 0.7;
  }
  100% {
    transform: scale(1.35);
    opacity: 0;
  }
}

@media (max-width: 480px) {
  .maint {
    padding: 14px;
  }

  .maint__panel {
    padding: 22px 16px 18px;
    border-radius: 16px;
  }

  .maint__heading {
    font-size: 1.05rem;
  }

  .maint__message {
    font-size: 0.74rem;
  }
}
</style>
