<template>
  <AliSection section-key="at-risk" emoji="⚠️" title="Leads At Risk" subtitle="Warning signals that need follow-up" :state="sectionState" :error-message="errorMessage" empty-message="No at-risk leads detected in your visible pipeline." @retry="$emit('retry')">
    <div class="ali-list">
      <article v-for="lead in items" :key="lead.id" class="ali-risk-row">
        <div class="ali-risk-row__main">
          <h3>{{ lead.lead_name || 'Unnamed lead' }}</h3>
          <p class="ali-risk-row__reason">{{ lead.risk_reason || lead.why_text || 'Risk reason will appear from CRM signals.' }}</p>
          <div class="ali-lead-card__tags">
            <span v-if="lead.stage_name" class="ali-chip">{{ lead.stage_name }}</span>
            <span v-if="lead.risk_level" class="ali-chip ali-chip--risk">{{ lead.risk_level }}</span>
            <span v-if="lead.responsible_person?.name" class="ali-chip">{{ lead.responsible_person.name }}</span>
          </div>
        </div>
        <AliLeadActions :lead-id="lead.id" @open-lead="$emit('open-lead', $event)" @view-matches="$emit('view-matches', $event)" />
      </article>
    </div>
  </AliSection>
</template>

<script setup>
import { computed } from 'vue'
import AliSection from './AliSection.vue'
import AliLeadActions from './AliLeadActions.vue'
const props = defineProps({
  status: { type: String, default: 'not_available' },
  items: { type: Array, default: () => [] },
  errorMessage: { type: String, default: '' },
  pageState: { type: String, default: '' },
})
defineEmits(['retry', 'open-lead', 'view-matches'])

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
