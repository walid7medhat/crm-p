<template>
  <button type="button" class="se-path" :title="ui.copyPath" @click="copy">
    <code>{{ path }}</code>
    <span class="se-path-hint">{{ copied ? ui.copied : ui.copyPath }}</span>
  </button>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useSystemExplorerLang } from '../composables/useSystemExplorerLang.js'

defineProps({
  path: { type: String, required: true },
})

const { t } = useSystemExplorerLang()
const ui = computed(() => t.value)
const copied = ref(false)

async function copy(e) {
  const text = e.currentTarget.querySelector('code')?.textContent || ''
  try {
    await navigator.clipboard.writeText(text)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1200)
  } catch {
    /* ignore */
  }
}
</script>
