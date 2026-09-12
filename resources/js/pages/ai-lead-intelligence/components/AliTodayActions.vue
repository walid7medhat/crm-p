<template>
  <AliSection section-key="actions" emoji="🎯" title="Today's Actions" subtitle="What your team should do first" :state="sectionState" :error-message="errorMessage" empty-message="No recommended actions for your visible leads right now." @retry="$emit('retry')">
    <ol class="ali-actions">
      <li v-for="action in items" :key="action.id || action.rank" class="ali-action">
        <span class="ali-action__rank">{{ action.rank ?? '•' }}</span>
        <div class="ali-action__body">
          <strong class="ali-action__label">{{ action.label || 'Recommended action' }}</strong>
          <p v-if="action.reason" class="ali-action__reason">{{ action.reason }}</p>
          <p v-if="action.lead_name" class="ali-action__lead">{{ action.lead_name }}</p>
        </div>
        <button v-if="action.lead_id" type="button" class="ali-btn ali-btn--primary" @click="$emit('open-lead', action.lead_id)">Open Lead</button>
      </li>
    </ol>
  </AliSection>
</template>

<script setup>
import { computed } from 'vue'
import AliSection from './AliSection.vue'
const props = defineProps({
  status: { type: String, default: 'not_available' },
  items: { type: Array, default: () => [] },
  errorMessage: { type: String, default: '' },
  pageState: { type: String, default: '' },
})
defineEmits(['retry', 'open-lead'])

const sectionState = computed(() => {
  if (props.pageState === 'loading') return 'loading'
  if (props.pageState === 'refreshing') return 'refreshing'
  if (props.pageState === 'forbidden') return 'forbidden'
  if (props.pageState === 'error') return 'error'
  if (props.status === 'not_available') return 'unavailable'
  if (props.status === 'empty' || (props.status === 'ready' && props.items.length === 0)) return 'empty'
  if (props.status === 'ready') return 'ready'
  return 'unavailable'
})

</script>
