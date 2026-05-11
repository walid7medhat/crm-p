<template>
  <div
    v-if="visible"
    class="so-global-lang"
    role="group"
    aria-label="System overview language"
  >
    <span class="so-global-lang-badge">{{ lang === 'en' ? 'EN' : 'AR' }}</span>
    <div class="so-global-lang-toggle">
      <button
        type="button"
        class="so-global-lang-btn"
        :class="{ active: lang === 'en' }"
        @click="setLang('en')"
      >
        EN
      </button>
      <button
        type="button"
        class="so-global-lang-btn"
        :class="{ active: lang === 'ar' }"
        @click="setLang('ar')"
      >
        AR
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useSystemOverviewLang } from '@/composables/useSystemOverviewLang'

const route = useRoute()
const { lang, setLang } = useSystemOverviewLang()

const visible = computed(() => route.path === '/system-overview')
</script>

<style scoped>
.so-global-lang {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-inline-end: 10px;
}
.so-global-lang-badge {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.15);
  color: rgba(255, 255, 255, 0.95);
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.so-global-lang-toggle {
  display: inline-flex;
  padding: 3px;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.12);
  gap: 2px;
}
.so-global-lang-btn {
  border: none;
  background: transparent;
  color: rgba(255, 255, 255, 0.75);
  font-size: 11px;
  font-weight: 700;
  padding: 5px 10px;
  border-radius: 8px;
  cursor: pointer;
  transition:
    background 0.15s,
    color 0.15s;
}
.so-global-lang-btn:hover {
  color: #fff;
}
.so-global-lang-btn.active {
  background: rgba(255, 255, 255, 0.95);
  color: #1e293b;
}
</style>
