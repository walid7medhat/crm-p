<template>
  <section class="ap-detail">
    <header class="ap-detail__head">
      <div>
        <h3 class="ap-section-title">Detailed Performance</h3>
        <p class="ap-section-sub">Expand an agent to view individual deal records</p>
      </div>
    </header>

    <div class="ap-detail__agents">
      <article v-for="agent in agents" :key="agent.agent_id" class="ap-agent-card">
        <button type="button" class="ap-agent-card__head" @click="$emit('toggle', agent.agent_id)">
          <div class="ap-agent-card__who">
            <div class="ap-agent-card__avatar">{{ initials(agent.agent_name) }}</div>
            <div>
              <span class="ap-agent-card__name">{{ agent.agent_name }}</span>
              <span v-if="agent.agent_email" class="ap-agent-card__email">{{ agent.agent_email }}</span>
            </div>
          </div>

          <div class="ap-agent-card__stats">
            <div class="ap-agent-card__stat">
              <span class="ap-agent-card__stat-label">Converted</span>
              <span class="ap-agent-card__stat-value">{{ agent.converted_count }}</span>
            </div>
            <div class="ap-agent-card__stat">
              <span class="ap-agent-card__stat-label">Avg Score</span>
              <span class="ap-agent-card__stat-value">
                <span class="score-pill" :class="scoreClass(agent.avg_lead_score)">
                  {{ agent.avg_lead_score ?? '—' }}
                </span>
              </span>
            </div>
            <div class="ap-agent-card__stat ap-agent-card__stat--hide-mobile">
              <span class="ap-agent-card__stat-label">Deal Value</span>
              <span class="ap-agent-card__stat-value">{{ formatMoney(agent.total_amount) }}</span>
            </div>
            <div class="ap-agent-card__stat ap-agent-card__stat--accent">
              <span class="ap-agent-card__stat-label">Agent Commission</span>
              <span class="ap-agent-card__stat-value">{{ formatMoney(agent.total_agent_commission) }}</span>
            </div>
            <div class="ap-agent-card__stat ap-agent-card__stat--hide-mobile">
              <span class="ap-agent-card__stat-label">Company Commission</span>
              <span class="ap-agent-card__stat-value">{{ formatMoney(agent.total_company_commission) }}</span>
            </div>
          </div>

          <span class="ap-agent-card__chevron" :class="{ 'ap-agent-card__chevron--open': expanded.has(agent.agent_id) }">
            <iconify-icon icon="lucide:chevron-down" width="18" height="18" />
          </span>
        </button>

        <div v-show="expanded.has(agent.agent_id)" class="ap-agent-card__body">
          <!-- Desktop table -->
          <div class="ap-deals-table-wrap ap-deals-table-wrap--desktop">
            <table class="ap-deals-table">
              <thead>
                <tr>
                  <th>Lead</th>
                  <th class="num">Lead Score</th>
                  <th>Deal #</th>
                  <th>Location</th>
                  <th class="num">Amount</th>
                  <th class="num">Agent Comm.</th>
                  <th class="num">Company Comm.</th>
                  <th>Converted</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in agent.deals" :key="row.deal_id">
                  <td>{{ row.lead_name || '—' }}</td>
                  <td class="num">
                    <span class="score-pill" :class="scoreClass(row.lead_score)">
                      {{ row.lead_score ?? '—' }}
                    </span>
                  </td>
                  <td>{{ row.deal_number || '#' + row.deal_id }}</td>
                  <td>
                    {{ row.location || '—' }}
                    <span v-if="row.location_extra" class="location-extra">+{{ row.location_extra }} more</span>
                  </td>
                  <td class="num">{{ formatMoney(row.deal_amount, row.currency) }}</td>
                  <td class="num num--accent">
                    {{ formatMoney(row.agent_commission, row.currency) }}
                    <span v-if="row.commission_percentage != null" class="location-extra">({{ row.commission_percentage }}%)</span>
                  </td>
                  <td class="num">{{ formatMoney(row.company_commission, row.currency) }}</td>
                  <td>{{ row.converted_at || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile cards -->
          <div class="ap-deals-cards ap-deals-cards--mobile">
            <article v-for="row in agent.deals" :key="'m-' + row.deal_id" class="ap-deal-card">
              <div class="ap-deal-card__top">
                <span class="ap-deal-card__lead">{{ row.lead_name || '—' }}</span>
                <span class="score-pill" :class="scoreClass(row.lead_score)">{{ row.lead_score ?? '—' }}</span>
              </div>
              <div class="ap-deal-card__grid">
                <div>
                  <span class="ap-deal-card__label">Deal #</span>
                  <span>{{ row.deal_number || '#' + row.deal_id }}</span>
                </div>
                <div>
                  <span class="ap-deal-card__label">Converted</span>
                  <span>{{ row.converted_at || '—' }}</span>
                </div>
                <div class="ap-deal-card__full">
                  <span class="ap-deal-card__label">Location</span>
                  <span>
                    {{ row.location || '—' }}
                    <span v-if="row.location_extra" class="location-extra">+{{ row.location_extra }} more</span>
                  </span>
                </div>
                <div>
                  <span class="ap-deal-card__label">Amount</span>
                  <span>{{ formatMoney(row.deal_amount, row.currency) }}</span>
                </div>
                <div>
                  <span class="ap-deal-card__label">Agent Comm.</span>
                  <span class="num--accent">{{ formatMoney(row.agent_commission, row.currency) }}</span>
                </div>
                <div>
                  <span class="ap-deal-card__label">Company Comm.</span>
                  <span>{{ formatMoney(row.company_commission, row.currency) }}</span>
                </div>
              </div>
            </article>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
defineProps({
  agents: { type: Array, default: () => [] },
  expanded: { type: Object, required: true },
  formatMoney: { type: Function, required: true },
  scoreClass: { type: Function, required: true },
})

defineEmits(['toggle'])

function initials(name = '') {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('') || '?'
}
</script>
