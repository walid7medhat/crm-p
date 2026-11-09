<template>
  <div class="om-search" :class="{ 'is-open': focused || query }">
    <div class="om-search-bar">
      <iconify-icon icon="lucide:search" width="16" height="16" class="om-search-ico" />
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="om-search-input"
        placeholder="Deals, agents, clients…"
        enterkeyhint="search"
        autocomplete="off"
        autocorrect="off"
        spellcheck="false"
        @focus="focused = true"
        @blur="onBlur"
        @keydown.enter.prevent="commit"
      />
      <button
        v-if="query"
        type="button"
        class="om-search-clear"
        aria-label="Clear search"
        @mousedown.prevent="clear"
      >
        <iconify-icon icon="lucide:x" width="14" height="14" />
      </button>
    </div>

    <div v-if="showPanel" class="om-search-panel">
      <div v-if="suggestions.length" class="om-search-group">
        <div class="om-search-label">Agents</div>
        <button
          v-for="a in suggestions"
          :key="a.id"
          type="button"
          class="om-search-item"
          @mousedown.prevent="pickAgent(a)"
        >
          <img :src="avatarUrl(a.avatar)" alt="" class="om-avatar" @error="onAvatarError" />
          <span>{{ a.name }}</span>
        </button>
      </div>

      <div v-if="recent.length && !query.trim()" class="om-search-group">
        <div class="om-search-label">Recent</div>
        <button
          v-for="r in recent"
          :key="r"
          type="button"
          class="om-search-item"
          @mousedown.prevent="pickRecent(r)"
        >
          <iconify-icon icon="lucide:clock-3" width="14" height="14" />
          <span>{{ r }}</span>
        </button>
      </div>

      <button
        v-if="query.trim()"
        type="button"
        class="om-search-go"
        @mousedown.prevent="commit"
      >
        <iconify-icon icon="lucide:search" width="14" height="14" />
        Search “{{ query.trim() }}”
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { loadRecentSearches, pushRecentSearch } from './useOfrMobile.js'

const props = defineProps({
  modelValue: { type: String, default: '' },
  agents: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'search', 'select-agent', 'clear'])

const DEFAULT_AVATAR = '/storage/users/user.png'
const avatarUrl = (url) => url || DEFAULT_AVATAR
const onAvatarError = (e) => { if (!e.target.src.endsWith(DEFAULT_AVATAR)) e.target.src = DEFAULT_AVATAR }

const query = ref(props.modelValue || '')
const focused = ref(false)
const recent = ref(loadRecentSearches())
const inputRef = ref(null)
let syncing = false
let timer = null

watch(() => props.modelValue, (v) => {
  if (v === query.value) return
  syncing = true
  query.value = v || ''
  nextTick(() => { syncing = false })
})

watch(query, (v) => {
  emit('update:modelValue', v)
  if (syncing) return
  clearTimeout(timer)
  timer = setTimeout(() => {
    emit('search', String(v || '').trim())
  }, 300)
})

const suggestions = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (q.length < 1) return []
  return props.agents
    .filter((a) => String(a.name || '').toLowerCase().includes(q))
    .slice(0, 5)
})

const showPanel = computed(() => focused.value && (suggestions.value.length || (recent.value.length && !query.value.trim()) || query.value.trim()))

function commit() {
  const q = query.value.trim()
  if (q) recent.value = pushRecentSearch(q)
  emit('search', q)
  focused.value = false
  inputRef.value?.blur()
}

function clear() {
  query.value = ''
  emit('update:modelValue', '')
  emit('clear')
  focused.value = false
}

function pickAgent(a) {
  recent.value = pushRecentSearch(a.name)
  emit('select-agent', a)
  focused.value = false
}

function pickRecent(r) {
  query.value = r
  emit('update:modelValue', r)
  emit('search', r)
  focused.value = false
}

function onBlur() {
  setTimeout(() => { focused.value = false }, 120)
}

defineExpose({ focus: () => inputRef.value?.focus() })
</script>

<style scoped>
.om-search { position: relative; z-index: 20; }
.om-search-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 10px;
  border: 1px solid var(--ofr-border, #e7dcec);
  border-radius: 12px;
  background: #fff;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.om-search.is-open .om-search-bar,
.om-search-bar:focus-within {
  border-color: var(--ofr-p, #733E87);
  box-shadow: 0 0 0 3px rgba(var(--ofr-p-rgb, 115, 62, 135), 0.14);
}
.om-search-ico { color: var(--ofr-text-3, #8b8798); flex-shrink: 0; }
.om-search-input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: transparent;
  font-size: var(--om-fs-body, 12px);
  color: var(--ofr-text, #1e1b2e);
  height: 100%;
}
.om-search-input::placeholder { color: var(--ofr-text-3, #8b8798); }
.om-search-clear {
  width: 28px; height: 28px;
  border: none; border-radius: 8px;
  background: var(--ofr-soft, #f7f2f8);
  color: var(--ofr-p, #733E87);
  display: grid; place-items: center;
  cursor: pointer;
  flex-shrink: 0;
}
.om-search-panel {
  position: absolute;
  left: 0; right: 0; top: calc(100% + 6px);
  background: #fff;
  border: 1px solid var(--ofr-border, #e7dcec);
  border-radius: 12px;
  box-shadow: 0 12px 28px rgba(var(--ofr-p-rgb, 115, 62, 135), 0.16);
  padding: 8px;
  max-height: 260px;
  overflow: auto;
}
.om-search-group + .om-search-group { margin-top: 6px; }
.om-search-label {
  font-size: var(--om-fs-title, 11px);
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ofr-text-3, #8b8798);
  padding: 4px 8px;
}
.om-search-item, .om-search-go {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
  border: none;
  background: transparent;
  text-align: left;
  padding: 8px;
  border-radius: 8px;
  font-size: var(--om-fs-body, 12px);
  font-weight: 600;
  color: var(--ofr-text, #1e1b2e);
  cursor: pointer;
}
.om-search-item:active, .om-search-go:active { background: var(--ofr-soft, #f7f2f8); }
.om-search-go { color: var(--ofr-p, #733E87); }
.om-avatar {
  width: 22px; height: 22px; border-radius: 50%;
  object-fit: cover; border: 1px solid var(--ofr-border, #e7dcec); flex-shrink: 0;
}
</style>
