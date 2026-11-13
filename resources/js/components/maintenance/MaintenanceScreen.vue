<template>
  <div class="maint" role="alertdialog" aria-modal="true" aria-labelledby="maint-heading">
    <div class="maint__bg" aria-hidden="true" />
    <div class="maint__glow" aria-hidden="true" />
    <div class="maint__card">
      <div class="maint__icon-wrap" aria-hidden="true">
        <svg class="maint__gear maint__gear--a" viewBox="0 0 24 24" fill="none">
          <path
            d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"
            stroke="currentColor"
            stroke-width="1.6"
          />
          <path
            d="M19.4 13.5a7.6 7.6 0 0 0 .05-1.5l1.7-1.3-1.6-2.8-2 .5a7.7 7.7 0 0 0-1.3-.8l-.4-2.1h-3.2l-.4 2.1c-.45.2-.88.47-1.28.78l-2-.5-1.6 2.8 1.7 1.3c-.03.5-.03 1 .05 1.5l-1.7 1.3 1.6 2.8 2-.5c.4.31.83.58 1.28.78l.4 2.1h3.2l.4-2.1c.45-.2.88-.47 1.28-.78l2 .5 1.6-2.8-1.7-1.3Z"
            stroke="currentColor"
            stroke-width="1.2"
            opacity=".35"
          />
        </svg>
        <svg class="maint__gear maint__gear--b" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" />
          <path
            d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M5.6 18.4l1.6-1.6M16.8 7.2l1.6-1.6"
            stroke="currentColor"
            stroke-width="1.4"
            stroke-linecap="round"
          />
        </svg>
      </div>

      <span class="maint__badge">Under Maintenance</span>
      <h1 id="maint-heading" class="maint__heading">We'll Be Back Soon</h1>
      <p v-if="announcementTitle" class="maint__announce">{{ announcementTitle }}</p>
      <p class="maint__message">{{ displayMessage }}</p>
      <p v-if="estimatedReturn" class="maint__eta">
        <span class="maint__eta-label">Estimated return</span>
        {{ estimatedReturn }}
      </p>
      <p class="maint__hint">Please check back shortly. Your data is safe.</p>
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
  position: fixed;
  inset: 0;
  z-index: 2147483000;
  display: grid;
  place-items: center;
  padding: 24px;
  color: #0f172a;
  font-family: 'Montserrat', 'Segoe UI', system-ui, sans-serif;
  overflow: auto;
}

.maint__bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(1200px 600px at 10% -10%, color-mix(in srgb, var(--color-primary, #733e87) 22%, transparent), transparent 60%),
    radial-gradient(900px 500px at 100% 0%, color-mix(in srgb, var(--color-primary, #733e87) 16%, transparent), transparent 55%),
    linear-gradient(160deg, #f7f5fb 0%, #eef2f7 48%, #f3f2f6 100%);
}

.maint__glow {
  position: absolute;
  width: min(520px, 80vw);
  height: min(520px, 80vw);
  border-radius: 50%;
  background: color-mix(in srgb, var(--color-primary, #733e87) 18%, transparent);
  filter: blur(48px);
  animation: maint-pulse 5.5s ease-in-out infinite;
  pointer-events: none;
}

.maint__card {
  position: relative;
  z-index: 1;
  width: min(520px, 100%);
  text-align: center;
  padding: clamp(28px, 5vw, 44px) clamp(22px, 4vw, 36px);
}

.maint__icon-wrap {
  position: relative;
  width: 88px;
  height: 88px;
  margin: 0 auto 22px;
  color: var(--color-primary, #733e87);
}

.maint__gear {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.maint__gear--a {
  animation: maint-spin 14s linear infinite;
  opacity: 0.9;
}

.maint__gear--b {
  width: 54%;
  height: 54%;
  inset: auto 0 0 auto;
  animation: maint-spin-rev 9s linear infinite;
  opacity: 0.75;
}

.maint__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--color-primary, #733e87);
  background: color-mix(in srgb, var(--color-primary, #733e87) 12%, #fff);
  border: 1px solid color-mix(in srgb, var(--color-primary, #733e87) 28%, transparent);
  animation: maint-fade-up 0.7s ease both;
}

.maint__heading {
  margin: 18px 0 10px;
  font-size: clamp(1.75rem, 4vw, 2.35rem);
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.15;
  animation: maint-fade-up 0.85s ease both;
}

.maint__announce {
  margin: 0 0 8px;
  font-size: 1.05rem;
  font-weight: 600;
  color: color-mix(in srgb, var(--color-primary, #733e87) 85%, #0f172a);
  animation: maint-fade-up 0.95s ease both;
}

.maint__message {
  margin: 0 auto;
  max-width: 38ch;
  font-size: 0.98rem;
  line-height: 1.55;
  color: #475569;
  animation: maint-fade-up 1.05s ease both;
}

.maint__eta {
  margin: 22px auto 0;
  max-width: 34ch;
  padding: 12px 16px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(15, 23, 42, 0.06);
  font-size: 0.92rem;
  font-weight: 600;
  color: #1e293b;
  animation: maint-fade-up 1.15s ease both;
}

.maint__eta-label {
  display: block;
  margin-bottom: 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #64748b;
}

.maint__hint {
  margin: 20px 0 0;
  font-size: 0.8rem;
  color: #94a3b8;
  animation: maint-fade-up 1.25s ease both;
}

@keyframes maint-spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes maint-spin-rev {
  to {
    transform: rotate(-360deg);
  }
}

@keyframes maint-pulse {
  0%,
  100% {
    transform: scale(0.92);
    opacity: 0.45;
  }
  50% {
    transform: scale(1.08);
    opacity: 0.7;
  }
}

@keyframes maint-fade-up {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 480px) {
  .maint {
    padding: 16px;
  }
  .maint__icon-wrap {
    width: 72px;
    height: 72px;
  }
}
</style>
