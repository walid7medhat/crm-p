<template>
  <div class="df-commission">
    <!-- Live summary -->
    <div class="df-kpi-strip">
      <div class="df-kpi df-kpi--gross">
        <span class="df-kpi__label">Gross commission</span>
        <span class="df-kpi__value">{{ formatMoney(grossAmount) }}</span>
        <span class="df-kpi__unit">AED</span>
      </div>
      <div class="df-kpi df-kpi--out">
        <span class="df-kpi__label">Distributed</span>
        <span class="df-kpi__value">{{ formatMoney(distributedTotal) }}</span>
        <span class="df-kpi__unit">AED</span>
      </div>
      <div class="df-kpi df-kpi--oia" :class="{ 'is-warn': oiaRetained < -0.01 }">
        <span class="df-kpi__label">OIA retained</span>
        <span class="df-kpi__value">{{ formatMoney(oiaRetained) }}</span>
        <span class="df-kpi__unit">AED</span>
      </div>
    </div>

    <div class="df-bar-row" aria-hidden="true">
      <div class="df-bar">
        <div class="df-bar__seg df-bar__seg--agency" :style="{ width: barAgencyPct + '%' }" />
        <div class="df-bar__seg df-bar__seg--agents" :style="{ width: barAgentsPct + '%' }" />
        <div class="df-bar__seg df-bar__seg--oia" :style="{ width: barOiaPct + '%' }" />
      </div>
      <div class="df-bar-legend">
        <span><i class="dot dot-agency" /> Agency</span>
        <span><i class="dot dot-agents" /> Agents</span>
        <span><i class="dot dot-oia" /> OIA</span>
      </div>
    </div>

    <!-- External agency -->
    <div class="df-card" :class="{ 'is-active': local.external_agency.enabled }">
      <div class="df-card__head">
        <div class="df-card__title-wrap">
          <div class="df-card__icon">
            <iconify-icon icon="lucide:building-2" />
          </div>
          <div>
            <div class="df-card__title">External agency</div>
            <div class="df-card__sub">Optional · % of gross</div>
          </div>
        </div>
        <label class="df-switch">
          <input
            v-model="local.external_agency.enabled"
            type="checkbox"
            @change="emitUpdate"
          />
          <span class="df-switch__track"><span class="df-switch__thumb" /></span>
          <span class="df-switch__text">{{ local.external_agency.enabled ? 'On' : 'Off' }}</span>
        </label>
      </div>

      <div v-if="local.external_agency.enabled" class="df-card__body">
        <div class="df-grid df-grid--3">
          <div class="df-field df-field--span2">
            <label class="df-label">Agency name</label>
            <input
              v-model="local.external_agency.name"
              type="text"
              class="df-input"
              placeholder="e.g. Aaban Real Estate"
              @input="emitUpdate"
            />
          </div>
          <div class="df-field">
            <label class="df-label">Representing</label>
            <select
              v-model="local.external_agency.representing"
              class="df-input df-select"
              @change="emitUpdate"
            >
              <option value="">Select…</option>
              <option value="buyer">Buyer</option>
              <option value="seller">Seller</option>
            </select>
          </div>
          <div class="df-field">
            <label class="df-label">Split of gross</label>
            <div class="df-affix">
              <input
                v-model.number="local.external_agency.split_percent"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="df-input"
                placeholder="0"
                @input="emitUpdate"
              />
              <span class="df-affix__suffix">%</span>
            </div>
          </div>
          <div class="df-field">
            <label class="df-label">Agency amount</label>
            <div class="df-affix df-affix--readonly">
              <input :value="formatMoney(externalAmount)" type="text" class="df-input" readonly tabindex="-1" />
              <span class="df-affix__suffix">AED</span>
            </div>
          </div>
          <div class="df-field">
            <label class="df-label">Referral <span class="df-optional">optional</span></label>
            <input
              v-model="local.external_agency.referral"
              type="text"
              class="df-input"
              placeholder="Referral name"
              @input="emitUpdate"
            />
          </div>
          <div class="df-field df-field--span2">
            <label class="df-label">MOU issued by <span class="df-optional">optional</span></label>
            <input
              v-model="local.external_agency.mou_issued_by"
              type="text"
              class="df-input"
              placeholder="Developer / Agency / …"
              @input="emitUpdate"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Internal agents -->
    <div class="df-card">
      <div class="df-card__head">
        <div class="df-card__title-wrap">
          <div class="df-card__icon df-card__icon--agents">
            <iconify-icon icon="lucide:users" />
          </div>
          <div>
            <div class="df-card__title">Internal agents</div>
            <div class="df-card__sub">
              Remaining <strong>{{ formatMoney(availableAfterAgency) }} AED</strong>
            </div>
          </div>
        </div>
        <button type="button" class="df-btn-add" @click="addAgent">
          <iconify-icon icon="lucide:plus" />
          Add
        </button>
      </div>

      <div class="df-card__body" :class="{ 'is-empty': !local.agents.length }">
        <div v-if="!local.agents.length" class="df-empty">
          <span class="df-empty__text">No agents — remaining stays with OIA.</span>
          <button type="button" class="df-btn-add df-btn-add--ghost" @click="addAgent">
            <iconify-icon icon="lucide:plus" />
            Add agent
          </button>
        </div>

        <div v-else class="df-agent-list">
          <div class="df-agent-head">
            <span>Agent</span>
            <span>Share %</span>
            <span>Amount</span>
            <span />
          </div>
          <div
            v-for="(agent, index) in local.agents"
            :key="'agent-' + index"
            class="df-agent-row"
          >
            <div class="df-agent-row__who">
              <v-select
                :model-value="agent.user_id"
                :options="agentOptionsForRow(index)"
                :reduce="(u) => u.id"
                :get-option-label="agentLabel"
                :filter-by="filterAgentOption"
                :searchable="true"
                :clearable="true"
                :append-to-body="true"
                :loading="agentSearchLoading"
                placeholder="Type to search agent…"
                class="df-agent-select"
                @update:model-value="(id) => onAgentIdChange(index, id)"
                @search="onAgentSearch"
                @open="ensureAgentOptionsLoaded"
              >
                <template #selected-option="option">
                  <div v-if="option" class="df-agent-opt df-agent-opt--selected">
                    <img :src="agentAvatar(option)" alt="" class="df-agent-opt__avatar" />
                    <span class="df-agent-opt__name">{{ agentLabel(option) }}</span>
                  </div>
                </template>
                <template #option="option">
                  <div class="df-agent-opt">
                    <img :src="agentAvatar(option)" alt="" class="df-agent-opt__avatar" />
                    <div class="df-agent-opt__meta">
                      <span class="df-agent-opt__name">{{ agentLabel(option) }}</span>
                      <span class="df-agent-opt__sub">
                        {{ option.email || '—' }}
                        <template v-if="agentPosition(option)"> · {{ agentPosition(option) }}</template>
                      </span>
                    </div>
                  </div>
                </template>
                <template #no-options="{ search }">
                  <div class="df-agent-empty">
                    <iconify-icon icon="lucide:search-x" />
                    <span v-if="search">No agents match “{{ search }}”</span>
                    <span v-else>Start typing to search agents</span>
                  </div>
                </template>
                <template #open-indicator="{ attributes }">
                  <span v-bind="attributes" class="df-agent-caret">
                    <iconify-icon icon="lucide:chevrons-up-down" />
                  </span>
                </template>
              </v-select>
            </div>
            <div class="df-affix">
              <input
                v-model.number="agent.split_percent"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="df-input"
                placeholder="0"
                @input="emitUpdate"
              />
              <span class="df-affix__suffix">%</span>
            </div>
            <div class="df-affix df-affix--readonly">
              <input :value="formatMoney(agentAmount(agent))" type="text" class="df-input" readonly tabindex="-1" />
              <span class="df-affix__suffix">AED</span>
            </div>
            <button
              type="button"
              class="df-btn-remove"
              title="Remove agent"
              @click="removeAgent(index)"
            >
              <iconify-icon icon="lucide:trash-2" />
            </button>
          </div>
        </div>

        <div v-if="agentSplitWarning" class="df-alert" :class="agentSplitWarningClass">
          <iconify-icon :icon="agentsPercentTotal > 100.01 ? 'lucide:alert-circle' : 'lucide:info'" />
          {{ agentSplitWarning }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

const DEFAULT_AVATAR = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'

const props = defineProps({
  modelValue: { type: Object, default: null },
  dealAmount: { type: [Number, String], default: null },
  dealCommission: { type: [Number, String], default: null },
  users: { type: Array, default: () => [] },
  defaultUserId: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:modelValue'])

const searchableUsers = ref([])
const agentSearchLoading = ref(false)
const knownUsersById = reactive({})
let agentSearchTimer = null

watch(
  () => props.users,
  (list) => {
    const users = Array.isArray(list) ? list : []
    users.forEach(rememberUser)
    if (!searchableUsers.value.length) {
      searchableUsers.value = users.slice()
    }
  },
  { immediate: true, deep: true }
)

function rememberUser(user) {
  if (!user?.id) return
  knownUsersById[Number(user.id)] = user
}

function agentLabel(user) {
  if (!user) return ''
  return user.name || user.display_name || user.email || `User #${user.id}`
}

function agentAvatar(user) {
  return user?.avatar || user?.profile_photo_url || DEFAULT_AVATAR
}

function agentPosition(user) {
  return user?.position || user?.designation || user?.job_title || user?.role_name || ''
}

function filterAgentOption(option, label, search) {
  const q = String(search || '').trim().toLowerCase()
  if (!q) return true
  const hay = [
    option?.name,
    option?.display_name,
    option?.email,
    agentPosition(option),
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
  return hay.includes(q)
}

function agentOptionsForRow(index) {
  const taken = new Set(
    local.agents
      .map((a, i) => (i === index ? null : Number(a.user_id)))
      .filter((id) => Number.isFinite(id) && id > 0)
  )
  const map = new Map()
  searchableUsers.value.forEach((u) => {
    if (!u?.id || taken.has(Number(u.id))) return
    map.set(Number(u.id), u)
  })
  const currentId = Number(local.agents[index]?.user_id)
  if (Number.isFinite(currentId) && currentId > 0 && !map.has(currentId)) {
    const known = knownUsersById[currentId]
    if (known) map.set(currentId, known)
    else if (local.agents[index]?.name) {
      map.set(currentId, {
        id: currentId,
        name: local.agents[index].name,
      })
    }
  }
  return Array.from(map.values())
}

async function fetchAgents(term = '') {
  agentSearchLoading.value = true
  try {
    const params = { limit: 30 }
    const q = String(term || '').trim()
    if (q) params.search = q
    const response = await api.get('/available-responsible-persons', { params })
    const rows = response.data?.data || response.data || []
    const list = Array.isArray(rows) ? rows : []
    list.forEach(rememberUser)
    searchableUsers.value = list.length ? list : (Array.isArray(props.users) ? props.users : [])
  } catch (e) {
    // keep local list; local filter still works
    if (!searchableUsers.value.length) {
      searchableUsers.value = Array.isArray(props.users) ? props.users.slice() : []
    }
  } finally {
    agentSearchLoading.value = false
  }
}

function onAgentSearch(search, loading) {
  clearTimeout(agentSearchTimer)
  const term = String(search || '').trim()
  if (!term) {
    searchableUsers.value = Array.isArray(props.users) ? props.users.slice() : searchableUsers.value
    return
  }
  loading(true)
  agentSearchTimer = setTimeout(async () => {
    await fetchAgents(term)
    loading(false)
  }, 280)
}

function ensureAgentOptionsLoaded() {
  if (!searchableUsers.value.length) {
    fetchAgents('')
  }
}

function onAgentIdChange(index, id) {
  const agent = local.agents[index]
  if (!agent) return
  agent.user_id = id ?? null
  const user = knownUsersById[Number(id)]
    || searchableUsers.value.find((u) => Number(u.id) === Number(id))
    || props.users.find((u) => Number(u.id) === Number(id))
  agent.name = user ? agentLabel(user) : ''
  emitUpdate()
}

function emptyDistribution() {
  return {
    external_agency: {
      enabled: false,
      name: '',
      representing: '',
      split_percent: 0,
      referral: '',
      mou_issued_by: '',
    },
    agents: [],
  }
}

function normalizeIncoming(value) {
  const base = emptyDistribution()
  if (!value || typeof value !== 'object') return base
  return {
    external_agency: {
      ...base.external_agency,
      ...(value.external_agency || {}),
      enabled: !!(value.external_agency && value.external_agency.enabled),
      split_percent: Number(value.external_agency?.split_percent || 0),
    },
    agents: Array.isArray(value.agents)
      ? value.agents.map((a) => ({
          user_id: a.user_id ?? null,
          name: a.name || '',
          split_percent: Number(a.split_percent || 0),
        }))
      : [],
  }
}

const local = reactive(normalizeIncoming(props.modelValue))
let syncingFromParent = false
let lastEmittedJson = ''

watch(
  () => props.modelValue,
  (val) => {
    const nextJson = JSON.stringify(val || null)
    if (nextJson === lastEmittedJson) return
    syncingFromParent = true
    const next = normalizeIncoming(val)
    local.external_agency = next.external_agency
    local.agents = next.agents
    syncingFromParent = false
  },
  { deep: true }
)

const numericAmount = computed(() => {
  const raw = String(props.dealAmount ?? '').replace(/,/g, '')
  const n = Number(raw)
  return Number.isFinite(n) ? n : 0
})

const numericCommission = computed(() => {
  const n = Number(props.dealCommission)
  return Number.isFinite(n) ? n : 0
})

const grossAmount = computed(() =>
  round2(numericAmount.value * numericCommission.value / 100)
)

const externalAmount = computed(() => {
  if (!local.external_agency.enabled) return 0
  const pct = Number(local.external_agency.split_percent) || 0
  return round2(grossAmount.value * pct / 100)
})

const availableAfterAgency = computed(() =>
  round2(Math.max(0, grossAmount.value - externalAmount.value))
)

function agentAmount(agent) {
  const pct = Number(agent.split_percent) || 0
  return round2(availableAfterAgency.value * pct / 100)
}

const agentsTotal = computed(() =>
  round2(local.agents.reduce((sum, a) => sum + agentAmount(a), 0))
)

const agentsPercentTotal = computed(() =>
  round2(local.agents.reduce((sum, a) => sum + (Number(a.split_percent) || 0), 0))
)

const oiaRetained = computed(() =>
  round2(availableAfterAgency.value - agentsTotal.value)
)

const distributedTotal = computed(() =>
  round2(externalAmount.value + agentsTotal.value)
)

const barAgencyPct = computed(() => pctOfGross(externalAmount.value))
const barAgentsPct = computed(() => pctOfGross(agentsTotal.value))
const barOiaPct = computed(() => {
  if (grossAmount.value <= 0) return 0
  return Math.max(0, 100 - barAgencyPct.value - barAgentsPct.value)
})

function pctOfGross(amount) {
  if (grossAmount.value <= 0) return 0
  return Math.min(100, round2((amount / grossAmount.value) * 100))
}

const agentSplitWarning = computed(() => {
  if (!local.agents.length) return ''
  if (agentsPercentTotal.value > 100.01) {
    return `Agent splits total ${agentsPercentTotal.value}% — cannot exceed 100% of remaining.`
  }
  if (agentsPercentTotal.value > 0 && agentsPercentTotal.value < 99.99) {
    return `${round2(100 - agentsPercentTotal.value)}% unallocated stays with OIA.`
  }
  return ''
})

const agentSplitWarningClass = computed(() =>
  agentsPercentTotal.value > 100.01 ? 'is-danger' : 'is-info'
)

function round2(n) {
  return Math.round((Number(n) || 0) * 100) / 100
}

function formatMoney(n) {
  const v = round2(n)
  return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function buildPayload() {
  const agents = local.agents.map((a) => {
    const user = knownUsersById[Number(a.user_id)]
      || props.users.find((u) => Number(u.id) === Number(a.user_id))
      || searchableUsers.value.find((u) => Number(u.id) === Number(a.user_id))
    return {
      user_id: a.user_id ? Number(a.user_id) : null,
      name: a.name || (user ? agentLabel(user) : ''),
      split_percent: Number(a.split_percent) || 0,
      amount: agentAmount(a),
    }
  })

  return {
    gross_amount: grossAmount.value,
    external_agency: {
      enabled: !!local.external_agency.enabled,
      name: local.external_agency.name || '',
      representing: local.external_agency.representing || '',
      split_percent: Number(local.external_agency.split_percent) || 0,
      amount: externalAmount.value,
      referral: local.external_agency.referral || '',
      mou_issued_by: local.external_agency.mou_issued_by || '',
    },
    agents,
    agents_total_amount: agentsTotal.value,
    oia_retained_amount: oiaRetained.value,
    available_after_agency: availableAfterAgency.value,
  }
}

function emitUpdate() {
  if (syncingFromParent) return
  const payload = buildPayload()
  const json = JSON.stringify(payload)
  if (json === lastEmittedJson) return
  lastEmittedJson = json
  emit('update:modelValue', payload)
}

function addAgent() {
  const defaultId = props.defaultUserId ? Number(props.defaultUserId) : null
  const used = new Set(local.agents.map((a) => Number(a.user_id)).filter(Boolean))
  const pick = defaultId && !used.has(defaultId) ? defaultId : null
  local.agents.push({
    user_id: pick,
    name: '',
    split_percent: local.agents.length === 0 ? 100 : 0,
  })
  if (pick) onAgentIdChange(local.agents.length - 1, pick)
  else emitUpdate()
  ensureAgentOptionsLoaded()
}

function removeAgent(index) {
  local.agents.splice(index, 1)
  emitUpdate()
}

watch([grossAmount, availableAfterAgency], () => {
  emitUpdate()
})
</script>

<style scoped>
.df-commission {
  --df-border: var(--color-border);
  --df-muted: var(--color-text-secondary);
  --df-text: var(--color-text-primary);
  --df-soft: var(--color-primary-soft);
  --df-primary: var(--color-primary);
  --df-primary-soft: rgba(var(--color-primary-rgb), 0.08);
  --df-agency: var(--color-primary-bright);
  --df-agents: var(--color-primary);
  --df-oia: var(--color-primary-ink);
  margin-top: 0.65rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.df-kpi-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.4rem;
}

.df-kpi {
  position: relative;
  border-radius: 10px;
  padding: 0.45rem 0.65rem 0.45rem 0.75rem;
  background: var(--color-surface);
  border: 1px solid var(--color-primary-border);
  overflow: hidden;
  min-height: 0;
}

.df-kpi::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 3px;
  border-radius: 10px 0 0 10px;
}

.df-kpi--gross::before { background: var(--color-primary); }
.df-kpi--out::before { background: var(--color-primary-bright); }
.df-kpi--oia::before { background: var(--color-primary-ink); }

.df-kpi.is-warn {
  border-color: rgba(220, 53, 69, 0.35);
  background: #fff7f7;
}

.df-kpi__label {
  display: block;
  font-size: 0.62rem;
  font-weight: 650;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--df-muted);
  margin-bottom: 0.12rem;
}

.df-kpi__value {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--df-text);
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.df-kpi__unit {
  margin-left: 0.2rem;
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.df-bar-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.df-bar {
  display: flex;
  flex: 1;
  height: 5px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--color-primary-soft);
  min-width: 0;
}

.df-bar__seg { height: 100%; transition: width 0.25s ease; }
.df-bar__seg--agency { background: var(--color-primary-bright); }
.df-bar__seg--agents { background: var(--color-primary); }
.df-bar__seg--oia { background: var(--color-primary-ink); }

.df-bar-legend {
  display: flex;
  gap: 0.55rem;
  font-size: 0.62rem;
  color: var(--df-muted);
  flex-shrink: 0;
  white-space: nowrap;
}

.df-bar-legend .dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 0.2rem;
  vertical-align: middle;
}

.dot-agency { background: var(--color-primary-bright); }
.dot-agents { background: var(--color-primary); }
.dot-oia { background: var(--color-primary-ink); }

.df-card {
  background: var(--color-surface);
  border: 1px solid var(--color-primary-border);
  border-radius: 10px;
  overflow: hidden;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.df-card.is-active {
  border-color: var(--color-primary-muted);
  box-shadow: 0 0 0 2px rgba(var(--color-primary-rgb), 0.08);
}

.df-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.45rem 0.65rem;
  background: var(--color-primary-soft);
  border-bottom: 1px solid transparent;
}

.df-card.is-active .df-card__head,
.df-card:has(.df-card__body:not(.is-empty)) .df-card__head {
  border-bottom-color: var(--color-primary-border);
}

.df-card__title-wrap {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
}

.df-card__icon {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  display: grid;
  place-items: center;
  background: rgba(var(--color-primary-rgb), 0.1);
  color: var(--color-primary);
  font-size: 0.85rem;
  flex-shrink: 0;
}

.df-card__icon--agents {
  background: rgba(var(--color-primary-rgb), 0.12);
  color: var(--color-primary);
}

.df-card__title {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--df-text);
  line-height: 1.15;
}

.df-card__sub {
  font-size: 0.65rem;
  color: var(--df-muted);
  margin-top: 0;
}

.df-card__sub strong {
  color: var(--color-primary-ink);
  font-weight: 650;
}

.df-card__body {
  padding: 0.65rem;
}

.df-card__body.is-empty {
  padding: 0.45rem 0.65rem;
}

.df-grid {
  display: grid;
  gap: 0.5rem 0.55rem;
}

.df-grid--3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.df-field--span2 {
  grid-column: span 2;
}

.df-label {
  display: block;
  font-size: 0.66rem;
  font-weight: 600;
  color: var(--color-text-secondary);
  margin-bottom: 0.22rem;
}

.df-optional {
  font-weight: 500;
  color: var(--color-text-muted);
  text-transform: lowercase;
}

.df-input {
  width: 100%;
  height: 36px;
  border: 1px solid var(--color-border-strong);
  border-radius: 8px;
  padding: 0 0.65rem;
  font-size: 0.82rem;
  color: var(--df-text);
  background: var(--color-surface);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.df-input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-focus-ring);
}

.df-input::placeholder {
  color: var(--color-text-muted);
  font-size: 0.8rem;
}

.df-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2494a3b8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.7rem center;
  padding-right: 2rem;
}

.df-affix {
  display: flex;
  align-items: stretch;
  border: 1px solid var(--color-border-strong);
  border-radius: 10px;
  overflow: hidden;
  background: var(--color-surface);
}

.df-affix:focus-within {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-focus-ring);
}

.df-affix .df-input {
  border: 0;
  border-radius: 0;
  box-shadow: none;
  height: 34px;
}

.df-affix .df-input:focus {
  box-shadow: none;
}

.df-affix__suffix {
  display: grid;
  place-items: center;
  min-width: 44px;
  padding: 0 0.65rem;
  background: var(--color-primary-soft);
  border-left: 1px solid var(--color-primary-border);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.df-affix--readonly {
  background: var(--color-primary-soft);
}

.df-affix--readonly .df-input {
  background: var(--color-primary-soft);
  color: var(--color-primary-ink);
  font-weight: 600;
}

.df-switch {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  cursor: pointer;
  user-select: none;
  margin: 0;
}

.df-switch input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.df-switch__track {
  width: 34px;
  height: 20px;
  border-radius: 999px;
  background: var(--color-primary-muted);
  position: relative;
  transition: background 0.2s ease;
}

.df-switch__thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
  transition: transform 0.2s ease;
}

.df-switch input:checked + .df-switch__track {
  background: var(--color-primary);
}

.df-switch input:checked + .df-switch__track .df-switch__thumb {
  transform: translateX(14px);
}

.df-switch__text {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--df-muted);
  min-width: 22px;
}

.df-btn-add {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  height: 28px;
  padding: 0 0.6rem;
  border-radius: 8px;
  border: 1px solid transparent;
  background: var(--gradient-primary);
  color: var(--color-on-primary);
  font-size: 0.72rem;
  font-weight: 650;
  white-space: nowrap;
  transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.df-btn-add:hover {
  background: var(--gradient-primary-hover);
  box-shadow: 0 4px 10px rgba(var(--color-primary-rgb), 0.22);
}

.df-btn-add--ghost {
  background: var(--color-surface);
  color: var(--color-primary);
  border-color: var(--color-primary-muted);
  height: 26px;
}

.df-btn-add--ghost:hover {
  background: var(--color-primary-soft);
  box-shadow: none;
}

.df-empty {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.15rem 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  text-align: left;
}

.df-empty__text {
  margin: 0;
  font-size: 0.72rem;
  color: var(--df-muted);
}

.df-agent-list {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.df-agent-head {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(80px, 0.7fr) minmax(100px, 0.9fr) 32px;
  gap: 0.4rem;
  padding: 0 0.15rem;
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-muted);
}

.df-agent-row {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(80px, 0.7fr) minmax(100px, 0.9fr) 32px;
  gap: 0.4rem;
  align-items: center;
  padding: 0.35rem;
  border-radius: 9px;
  border: 1px solid var(--color-primary-border);
  background: var(--color-surface);
}

.df-agent-row__who {
  min-width: 0;
}

.df-agent-select {
  width: 100%;
}

.df-agent-select :deep(.vs__dropdown-toggle) {
  min-height: 36px;
  height: auto;
  border: 1px solid var(--color-border-strong);
  border-radius: 8px;
  padding: 1px 4px 1px 6px;
  background: var(--color-surface);
}

.df-agent-select :deep(.vs__dropdown-toggle):focus-within,
.df-agent-select.vs--open :deep(.vs__dropdown-toggle) {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-focus-ring);
}

.df-agent-select :deep(.vs__search) {
  font-size: 0.85rem;
  color: var(--color-text-primary);
  margin: 0;
  padding: 6px 4px;
}

.df-agent-select :deep(.vs__search::placeholder),
.df-agent-select :deep(.vs__placeholder) {
  color: var(--color-text-muted);
  font-size: 0.8rem;
  opacity: 1;
}

.df-agent-select :deep(.vs__selected) {
  margin: 2px 0;
  padding: 0;
  border: 0;
  background: transparent;
  max-width: 100%;
}

.df-agent-select :deep(.vs__actions) {
  padding-top: 0;
  padding-bottom: 0;
}

.df-agent-select :deep(.vs__clear) {
  fill: var(--color-text-muted);
}

.df-agent-select :deep(.vs__dropdown-menu) {
  border: 1px solid var(--color-primary-border);
  border-radius: 12px;
  box-shadow: 0 12px 28px rgba(var(--color-primary-rgb), 0.14);
  padding: 6px;
  margin-top: 6px;
  max-height: 260px;
  overflow-y: auto;
  z-index: 3000;
}

.df-agent-select :deep(.vs__dropdown-option) {
  border-radius: 8px;
  padding: 8px 10px;
  margin-bottom: 2px;
}

.df-agent-select :deep(.vs__dropdown-option--highlight) {
  background: var(--gradient-primary) !important;
  color: var(--color-on-primary) !important;
}

.df-agent-select :deep(.vs__dropdown-option--highlight .df-agent-opt__name),
.df-agent-select :deep(.vs__dropdown-option--highlight .df-agent-opt__sub) {
  color: var(--color-on-primary) !important;
}

.df-agent-select :deep(.vs__dropdown-option--selected) {
  background: var(--color-primary-soft);
  color: var(--color-primary-ink);
  font-weight: 600;
}

.df-agent-opt {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}

.df-agent-opt--selected {
  width: 100%;
}

.df-agent-opt__avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  background: var(--color-primary-soft);
  border: 1px solid var(--color-primary-border);
}

.df-agent-opt__meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.2;
}

.df-agent-opt__name {
  font-size: 0.84rem;
  font-weight: 650;
  color: var(--color-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.df-agent-opt__sub {
  font-size: 0.7rem;
  color: var(--color-text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.df-agent-empty {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.65rem 0.4rem;
  color: var(--color-text-muted);
  font-size: 0.8rem;
}

.df-agent-caret {
  display: inline-flex;
  color: var(--color-primary);
  font-size: 0.95rem;
  padding: 0 2px;
}

.df-btn-remove {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid rgba(220, 53, 69, 0.3);
  background: #fff5f5;
  color: var(--color-danger);
  display: grid;
  place-items: center;
  transition: background 0.15s ease;
  font-size: 0.85rem;
}

.df-btn-remove:hover {
  background: #fee2e2;
}

.df-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  margin-top: 0.45rem;
  padding: 0.4rem 0.55rem;
  border-radius: 8px;
  font-size: 0.7rem;
  line-height: 1.3;
}

.df-alert.is-danger {
  background: #fef2f2;
  color: var(--color-danger);
  border: 1px solid rgba(220, 53, 69, 0.3);
}

.df-alert.is-info {
  background: var(--color-primary-soft);
  color: var(--color-primary-ink);
  border: 1px solid var(--color-primary-border);
}

@media (max-width: 900px) {
  .df-kpi-strip {
    grid-template-columns: 1fr;
  }

  .df-grid--3,
  .df-field--span2 {
    grid-template-columns: 1fr;
    grid-column: auto;
  }

  .df-grid--3 {
    display: flex;
    flex-direction: column;
  }

  .df-agent-head {
    display: none;
  }

  .df-agent-row {
    grid-template-columns: 1fr;
  }

  .df-btn-remove {
    width: 100%;
    height: 38px;
  }
}
</style>
