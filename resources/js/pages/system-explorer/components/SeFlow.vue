<template>
  <div class="se-flow">
    <ol v-if="steps?.length" class="se-flow-steps">
      <li v-for="(s, i) in steps" :key="s.id || i">
        <span class="se-flow-num">{{ i + 1 }}</span>
        <div>
          <div class="se-flow-label">{{ labelOf(s) }}</div>
          <div v-if="s.paths?.length" class="se-flow-paths">
            <code v-for="p in s.paths" :key="p">{{ p }}</code>
          </div>
        </div>
      </li>
    </ol>
    <details v-if="mermaid" class="se-mermaid">
      <summary>{{ note }}</summary>
      <pre>{{ mermaid }}</pre>
    </details>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSystemExplorerLang } from '../composables/useSystemExplorerLang.js'

defineProps({
  steps: { type: Array, default: () => [] },
  mermaid: { type: String, default: '' },
})

const { t, L } = useSystemExplorerLang()
const note = computed(() => t.value.mermaidNote)

function labelOf(s) {
  return L(s)
}
</script>
