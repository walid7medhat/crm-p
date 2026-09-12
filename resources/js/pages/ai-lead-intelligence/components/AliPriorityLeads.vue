<template>
  <AliSection section-key="priority" emoji="🔥" title="Priority Leads" subtitle="Leads that need action now" :state="sectionState" :error-message="errorMessage" empty-message="No high-priority leads in your visible pipeline right now." @retry="$emit('retry')">
    <div class="ali-scroll">
      <article v-for="lead in items" :key="lead.id" class="ali-lead-card ali-lead-card--priority">
        <header class="ali-lead-card__head">
          <div>
            <h3 class="ali-lead-card__name">{{ lead.lead_name || 'Unnamed lead' }}</h3>
            <div class="ali-lead-card__tags">
              <span v-if="lead.intent" class="ali-chip ali-chip--intent">{{ formatIntent(lead.intent) }}</span>
              <span v-if="lead.stage_name" class="ali-chip">{{ lead.stage_name }}</span>
            </div>
          </div>
          <div v-if="lead.score != null" class="ali-score">
            <span class="ali-score__label">Score</span>
            <strong>{{ lead.score }}</strong><span class="ali-score__max">/100</span>
          </div>
        </header>
        <dl class="ali-meta-grid">
          <div><dt>Area</dt><dd>{{ lead.area || '—' }}</dd></div>
          <div><dt>Type</dt><dd>{{ lead.property_type || '—' }}</dd></div>
          <div><dt>Budget</dt><dd>{{ lead.budget_display || '—' }}</dd></div>
          <div><dt>Last contact</dt><dd>{{ lead.last_contact_label || '—' }}</dd></div>
          <div><dt>Matches</dt><dd>{{ lead.matching_listings_count != null ? lead.matching_listings_count : '—' }}</dd></div>
          <div><dt>Agent</dt><dd>{{ lead.responsible_person?.name || '—' }}</dd></div>
        </dl>
        <div v-if="lead.why_text || (lead.why && lead.why.length)" class="ali-why">
          <strong>Why this matters</strong>
          <p v-if="lead.why_text">{{ lead.why_text }}</p>
          <ul v-else class="ali-why__list"><li v-for="(reason, i) in lead.why" :key="i">{{ reason }}</li></ul>
        </div>
        <div v-if="lead.recommended_action || (lead.recommended_actions && lead.recommended_actions.length)" class="ali-rec">
          <strong>Recommended action</strong>
          <p v-if="lead.recommended_action">{{ lead.recommended_action }}</p>
          <ul v-else class="ali-why__list"><li v-for="(action, i) in lead.recommended_actions" :key="i">{{ action }}</li></ul>
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

function formatIntent(intent) {
  if (!intent) return ''
  return String(intent).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) + ' Intent'
}
</script>
