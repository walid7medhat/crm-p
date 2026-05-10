<template>
  <section class="mod-section" :class="{ open: expanded, bilingual }">
    <button type="button" class="mod-section-head" @click="toggle">
      <span class="chev" :class="{ rotated: expanded }" aria-hidden="true">
        <iconify-icon icon="lucide:chevron-down" />
      </span>
      <span class="label">{{ label }}</span>
      <span v-if="badge" class="mini-badge">{{ badge }}</span>
    </button>
    <transition name="so-collapse">
      <div v-show="expanded" class="mod-section-body">
        <transition name="so-lang-fade" mode="out-in">
          <div
            :key="bilingual ? bodyLang : 'single'"
            class="mod-section-inner"
            :class="{ 'is-ar': bilingual && bodyLang === 'ar' }"
            :dir="bilingual && bodyLang === 'ar' ? 'rtl' : 'ltr'"
          >
            <slot v-if="!bilingual" />
            <template v-else>
              <slot v-if="bodyLang === 'en'" name="en" />
              <slot v-else name="ar" />
            </template>
          </div>
        </transition>
      </div>
    </transition>
  </section>
</template>

<script setup>
import { ref, watch, inject } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  badge: { type: String, default: '' },
  defaultOpen: { type: Boolean, default: false },
  /** Dual #en / #ar slots; visible slot follows global system-overview language */
  bilingual: { type: Boolean, default: false },
})

const soI18n = inject('soI18n', null)

const expanded = ref(props.defaultOpen)

const bodyLang = ref(soI18n?.lang?.value ?? 'en')

watch(
  () => soI18n?.lang?.value,
  (v) => {
    if (v === 'en' || v === 'ar') bodyLang.value = v
  }
)

function toggle() {
  expanded.value = !expanded.value
}

watch(
  () => props.defaultOpen,
  (v) => {
    expanded.value = v
  }
)
</script>

<style scoped>
.mod-section {
  border: 1px solid var(--so-border, rgba(15, 23, 42, 0.08));
  border-radius: 14px;
  margin-bottom: 10px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.65);
  backdrop-filter: blur(8px);
}
.mod-section-head {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  color: var(--so-text, #0f172a);
  text-align: start;
  transition: background 0.15s ease;
}
.mod-section-head:hover {
  background: rgba(99, 102, 241, 0.06);
}
.chev {
  display: inline-flex;
  transition: transform 0.2s ease;
  color: #64748b;
}
.chev.rotated {
  transform: rotate(-180deg);
}
.mini-badge {
  margin-inline-start: auto;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.12);
  color: #4f46e5;
}
.mod-section-body {
  padding-block: 0 16px;
  padding-inline: 44px 16px;
}
.mod-section-inner.is-ar :deep(.feature-item),
.mod-section-inner.is-ar :deep(.workflow-step),
.mod-section-inner.is-ar :deep(.so-table) {
  text-align: start;
}
.so-collapse-enter-active,
.so-collapse-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.so-collapse-enter-from,
.so-collapse-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
.so-lang-fade-enter-active,
.so-lang-fade-leave-active {
  transition: opacity 0.2s ease;
}
.so-lang-fade-enter-from,
.so-lang-fade-leave-to {
  opacity: 0;
}
</style>
