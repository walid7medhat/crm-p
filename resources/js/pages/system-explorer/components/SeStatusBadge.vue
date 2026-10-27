<template>
  <span class="se-badge" :class="cls" :title="label">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { useSystemExplorerLang } from '../composables/useSystemExplorerLang.js'

const props = defineProps({
  status: { type: String, default: 'verified' },
})

const { t } = useSystemExplorerLang()

const cls = computed(() => {
  if (props.status === 'partial') return 'is-partial'
  if (props.status === 'needs_verification') return 'is-needs'
  return 'is-ok'
})

const label = computed(() => {
  if (props.status === 'partial') return t.value.partial
  if (props.status === 'needs_verification') return t.value.needs
  return t.value.verified
})
</script>
