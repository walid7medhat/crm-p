<template>
  <div
    ref="rootRef"
    class="oia-ssl"
    :class="{ 'is-open': isOpen, 'is-busy': loading }"
  >
    <input
      ref="inputRef"
      v-model="query"
      class="oia-ssl__input"
      type="search"
      data-smart-search-input
      dir="auto"
      :placeholder="placeholder"
      autocomplete="off"
      spellcheck="false"
      aria-label="Search"
      aria-autocomplete="list"
      aria-controls="oia-search-results"
      :aria-expanded="isOpen"
      @focus="onFocus"
      @keydown.down.prevent="smartSearchMove(1)"
      @keydown.up.prevent="smartSearchMove(-1)"
      @keydown.enter.prevent="smartSearchActivate()"
      @keydown.esc.stop.prevent="onEscape"
    />
    <button
      v-if="isOpen || query"
      type="button"
      class="oia-ssl__clear"
      aria-label="Close search"
      @mousedown.prevent
      @click="onClear"
    >
      <iconify-icon icon="lucide:x" width="14" height="14" aria-hidden="true" />
    </button>
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import {
  closeSmartSearch,
  openSmartSearch,
  setSmartSearchAnchor,
  smartSearchActivate,
  smartSearchLoading,
  smartSearchMove,
  smartSearchOpen,
  smartSearchQuery,
} from '@/composables/useSmartSearch.js'

defineProps({
  placeholder: {
    type: String,
    default: 'Search',
  },
})

const rootRef = ref(null)
const inputRef = ref(null)
const isOpen = smartSearchOpen
const query = smartSearchQuery
const loading = smartSearchLoading

watch(isOpen, async (open) => {
  setSmartSearchAnchor(rootRef.value)
  if (open) {
    await nextTick()
    inputRef.value?.focus()
  }
})

function onFocus() {
  openSmartSearch()
  setSmartSearchAnchor(rootRef.value)
}

function onEscape() {
  closeSmartSearch()
  inputRef.value?.blur()
}

function onClear() {
  closeSmartSearch()
  inputRef.value?.blur()
}

onMounted(() => {
  setSmartSearchAnchor(rootRef.value)
})

onUnmounted(() => {
  setSmartSearchAnchor(null)
})
</script>

<style scoped>
.oia-ssl {
  --ssl-ease: cubic-bezier(0.22, 1, 0.36, 1);
  position: relative;
  display: flex;
  align-items: center;
  width: 132px;
  min-height: 34px;
  height: 34px;
  padding: 0 10px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid rgba(27, 36, 56, 0.12);
  box-shadow: 0 1px 2px rgba(16, 22, 36, 0.04);
  transition:
    width 200ms var(--ssl-ease),
    border-color 160ms var(--ssl-ease),
    box-shadow 160ms var(--ssl-ease),
    background 160ms var(--ssl-ease);
}

.oia-ssl.is-open,
.oia-ssl:focus-within {
  width: min(420px, calc(100vw - 168px));
  border-color: rgba(var(--color-primary-rgb, 115, 62, 135), 0.35);
  box-shadow:
    0 0 0 3px rgba(var(--color-primary-rgb, 115, 62, 135), 0.1),
    0 8px 22px rgba(16, 22, 36, 0.08);
}

.oia-ssl.is-busy {
  background: rgba(var(--color-primary-rgb, 115, 62, 135), 0.03);
}

.oia-ssl__input {
  flex: 1;
  min-width: 0;
  height: 100%;
  border: 0;
  outline: none;
  background: transparent;
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  color: #1b2438;
  letter-spacing: -0.01em;
}

.oia-ssl__input::placeholder {
  color: #98a0b4;
  font-weight: 500;
}

.oia-ssl__input::-webkit-search-decoration,
.oia-ssl__input::-webkit-search-cancel-button {
  -webkit-appearance: none;
  appearance: none;
}

.oia-ssl__clear {
  flex: none;
  width: 22px;
  height: 22px;
  margin-inline-start: 4px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 999px;
  background: rgba(27, 36, 56, 0.06);
  color: #5b6478;
  cursor: pointer;
  padding: 0;
}

.oia-ssl__clear:hover {
  background: rgba(220, 38, 38, 0.08);
  color: #b91c1c;
}

@media (max-width: 720px) {
  .oia-ssl {
    width: 120px;
  }

  .oia-ssl.is-open,
  .oia-ssl:focus-within {
    width: min(280px, calc(100vw - 140px));
  }
}

@media (max-width: 430px) {
  .oia-ssl,
  .oia-ssl.is-open,
  .oia-ssl:focus-within {
    width: min(210px, calc(100vw - 120px));
  }

  .oia-ssl__input {
    font-size: 16px; /* avoid iOS zoom */
  }
}

@media (prefers-reduced-motion: reduce) {
  .oia-ssl {
    transition: none;
  }
}
</style>
