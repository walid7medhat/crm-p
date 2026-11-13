<template>
  <div class="crm-maint" :class="{ 'crm-maint--preview': preview }" role="alertdialog" aria-modal="true">
    <div class="crm-maint__bg" aria-hidden="true" />

    <button
      v-if="preview"
      type="button"
      class="crm-maint__close"
      @click="$emit('close')"
    >
      Close preview
    </button>

    <div class="crm-maint__card">
      <div class="crm-maint__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none">
          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" opacity="0.35" />
          <path d="M12 7.5v5l3.2 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>

      <p class="crm-maint__badge">Under maintenance</p>

      <!-- Must NOT be <h1>: global CRM CSS sets h1 { font-size: var(--h1) !important } -->
      <p class="crm-maint__title" role="heading" aria-level="1">
        {{ headingText }}
      </p>

      <p class="crm-maint__copy">{{ displayMessage }}</p>

      <div v-if="displayEta" class="crm-maint__eta">
        <span class="crm-maint__eta-k">Estimated return</span>
        <span class="crm-maint__eta-v">{{ displayEta }}</span>
      </div>

      <p class="crm-maint__safe">Your data is safe · please check back shortly</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useMaintenanceMode } from '@/composables/useMaintenanceMode'

const props = defineProps({
  preview: { type: Boolean, default: false },
  previewTitle: { type: String, default: '' },
  previewMessage: { type: String, default: '' },
  previewEstimatedReturn: { type: String, default: '' },
})

defineEmits(['close'])

const store = useMaintenanceMode()

const headingText = computed(() => {
  const raw = props.preview
    ? String(props.previewTitle || '').trim()
    : String(store.title.value || '').trim()
  if (!raw) return "We'll be back soon"
  // Avoid duplicating the default phrase as both title + heading.
  return raw
})

const displayMessage = computed(() => {
  const raw = props.preview
    ? String(props.previewMessage || '').trim()
    : String(store.message.value || '').trim()
  return raw || 'The CRM is temporarily unavailable while we perform scheduled maintenance.'
})

const displayEta = computed(() => {
  const raw = props.preview
    ? String(props.previewEstimatedReturn || '').trim()
    : String(store.estimatedReturn.value || '').trim()
  return raw
})
</script>

<style scoped>
.crm-maint {
  --cm-accent: var(--color-primary, #733e87);
  --cm-ink: #243041;
  --cm-muted: #6b7785;
  --cm-soft: #9aa3ad;

  position: fixed;
  inset: 0;
  z-index: 2147483000;
  display: grid;
  place-items: center;
  padding: 16px;
  overflow: auto;
  font-family: 'Montserrat', 'Segoe UI', system-ui, sans-serif;
  color: var(--cm-ink);
}

.crm-maint__bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 50% at 50% -10%, color-mix(in srgb, var(--cm-accent) 16%, transparent), transparent 55%),
    linear-gradient(180deg, #eef1f4 0%, #f7f8fa 48%, #eef1f4 100%);
}

.crm-maint__close {
  position: fixed;
  top: 14px;
  right: 14px;
  z-index: 2;
  border: 0;
  border-radius: 999px;
  padding: 8px 14px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #fff;
  background: rgba(28, 36, 48, 0.82);
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
}

.crm-maint__card {
  position: relative;
  z-index: 1;
  width: min(340px, 100%);
  text-align: center;
  padding: 22px 20px 18px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(36, 48, 65, 0.06);
  box-shadow: 0 12px 40px rgba(28, 36, 48, 0.08);
}

.crm-maint__icon {
  width: 40px;
  height: 40px;
  margin: 0 auto 10px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  color: var(--cm-accent);
  background: color-mix(in srgb, var(--cm-accent) 10%, #fff);
  border: 1px solid color-mix(in srgb, var(--cm-accent) 18%, transparent);
}

.crm-maint__badge {
  margin: 0 0 8px;
  display: inline-block;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 9px !important;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--cm-accent);
  background: color-mix(in srgb, var(--cm-accent) 9%, #fff);
  border: 1px solid color-mix(in srgb, var(--cm-accent) 18%, transparent);
}

.crm-maint__title {
  margin: 0 0 8px;
  padding: 0;
  font-size: 17px !important;
  line-height: 1.3 !important;
  font-weight: 700 !important;
  letter-spacing: -0.01em;
  color: var(--cm-ink) !important;
  white-space: normal;
  word-break: normal;
}

.crm-maint__copy {
  margin: 0 auto;
  max-width: 32ch;
  font-size: 12px !important;
  line-height: 1.5 !important;
  font-weight: 500;
  color: var(--cm-muted) !important;
}

.crm-maint__eta {
  margin: 14px auto 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: #f6f7f9;
  border: 1px solid rgba(36, 48, 65, 0.06);
}

.crm-maint__eta-k {
  display: block;
  font-size: 9px !important;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--cm-soft);
  margin-bottom: 2px;
}

.crm-maint__eta-v {
  font-size: 12px !important;
  font-weight: 650;
  color: var(--cm-ink);
}

.crm-maint__safe {
  margin: 12px 0 0;
  font-size: 10px !important;
  color: var(--cm-soft) !important;
  letter-spacing: 0.01em;
}

@media (max-width: 480px) {
  .crm-maint__card {
    width: min(320px, 100%);
    padding: 18px 16px 16px;
  }

  .crm-maint__title {
    font-size: 16px !important;
  }

  .crm-maint__copy {
    font-size: 11px !important;
  }
}
</style>
