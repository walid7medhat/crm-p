<template>
  <div ref="root" class="mk-picker" :class="{ 'is-open': open }">
    <button type="button" class="mk-picker__control" @click="toggle">
      <template v-if="selected">
        <MkAvatar :user="selected" :size="24" />
        <span class="mk-picker__name">{{ selected.name }}</span>
      </template>
      <span v-else class="mk-picker__placeholder">
        <iconify-icon :icon="icon" /> {{ placeholder }}
      </span>
      <iconify-icon v-if="selected" icon="lucide:x" class="mk-picker__clear" @click.stop="pick(null)" />
      <iconify-icon v-else icon="lucide:chevron-down" class="mk-picker__chev" />
    </button>

    <div v-if="open" class="mk-picker__menu">
      <div class="mk-picker__search">
        <iconify-icon icon="lucide:search" />
        <input ref="searchInput" v-model="query" type="text" name="mk-member-query" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" placeholder="Search team…" />
      </div>
      <ul>
        <li v-for="m in filtered" :key="m.id" :class="{ active: m.id === modelValue }" @click="pick(m.id)">
          <MkAvatar :user="m" :size="26" />
          <span>{{ m.name }}</span>
          <iconify-icon v-if="m.id === modelValue" icon="lucide:check" class="mk-picker__tick" />
        </li>
        <li v-if="!filtered.length" class="mk-picker__empty">
          {{ members.length ? 'No match' : 'No one has the Marketing permission yet' }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import MkAvatar from './MkAvatar.vue'

const props = defineProps({
  modelValue: { type: [Number, null], default: null },
  members: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select person' },
  icon: { type: String, default: 'lucide:user-round' },
  /** Shown when the saved person is no longer in the member list. */
  fallback: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const query = ref('')
const root = ref(null)
const searchInput = ref(null)

const selected = computed(() => {
  if (!props.modelValue) return null
  return props.members.find((m) => m.id === props.modelValue)
    || (props.fallback?.id === props.modelValue ? props.fallback : null)
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return q ? props.members.filter((m) => m.name.toLowerCase().includes(q)) : props.members
})

function toggle() {
  open.value = !open.value
  if (open.value) {
    query.value = ''
    nextTick(() => searchInput.value?.focus())
  }
}

function pick(id) {
  emit('update:modelValue', id)
  open.value = false
}

function onDocClick(e) {
  if (open.value && root.value && !root.value.contains(e.target)) open.value = false
}

onMounted(() => document.addEventListener('mousedown', onDocClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick))
</script>

<style scoped>
.mk-picker { position: relative; }
.mk-picker__control {
  width: 100%;
  min-height: 42px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--white);
  color: var(--text-primary-light);
  text-align: left;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.mk-picker.is-open .mk-picker__control,
.mk-picker__control:hover { border-color: var(--color-primary); }
.mk-picker.is-open .mk-picker__control { box-shadow: 0 0 0 3px var(--color-focus-ring); }
.mk-picker__name { flex: 1; font-size: 14px !important; font-weight: 600 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mk-picker__placeholder { flex: 1; display: flex; align-items: center; gap: 6px; font-size: 14px !important; color: var(--text-secondary-light); }
.mk-picker__chev, .mk-picker__clear { color: var(--text-secondary-light); }
.mk-picker__clear:hover { color: #dc2626; }
.mk-picker__menu {
  position: absolute;
  z-index: 30;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  padding: 6px;
  border: 1px solid var(--border-color);
  border-radius: 14px;
  background: var(--white);
  box-shadow: 0 18px 40px -12px rgba(15, 10, 30, 0.35);
}
.mk-picker__search {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  margin-bottom: 4px;
  border-radius: 10px;
  background: var(--neutral-50);
  color: var(--text-secondary-light);
}
.mk-picker__search input { flex: 1; border: 0; outline: 0; background: transparent; font-size: 13px !important; color: var(--text-primary-light); }
.mk-picker ul { list-style: none; margin: 0; padding: 0; max-height: 220px; overflow-y: auto; }
.mk-picker li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 8px;
  border-radius: 10px;
  font-size: 14px !important;
  cursor: pointer;
}
.mk-picker li:hover { background: var(--neutral-100); }
.mk-picker li.active { background: rgba(var(--color-primary-rgb), 0.1); font-weight: 600 !important; }
.mk-picker__tick { margin-left: auto; color: var(--color-primary); }
.mk-picker__empty { justify-content: center; color: var(--text-secondary-light); cursor: default !important; font-size: 13px !important; }
</style>
