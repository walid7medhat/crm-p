<template>
  <div class="ua-admin">
    <header class="ua-bar">
      <div>
        <p class="ua-kicker">Animation control</p>
        <h1 class="ua-title">Target one user</h1>
        <p class="ua-lead">Start or stop plays only for the selected user, and it stays on until you stop it.</p>
      </div>
    </header>

    <p v-if="notice" class="ua-notice" :class="{ 'ua-notice--error': noticeError }">{{ notice }}</p>

    <section class="ua-grid">
      <div class="ua-card">
        <label class="ua-label" for="ua-search">Find a user</label>
        <input
          id="ua-search"
          v-model="search"
          class="ua-input"
          type="search"
          placeholder="Name, email, or ID"
          autocomplete="off"
          @input="onSearchInput"
        >
        <p v-if="searching" class="ua-muted">Searching…</p>
        <p v-else-if="searchError" class="ua-muted ua-muted--error">{{ searchError }}</p>
        <ul v-else-if="results.length" class="ua-results">
          <li v-for="user in results" :key="user.id">
            <button type="button" class="ua-result" :class="{ 'is-selected': selected?.id === user.id }" @click="selectUser(user)">
              <strong>{{ user.name }}</strong>
              <span>{{ user.email }} · ID {{ user.id }}</span>
            </button>
          </li>
        </ul>
        <p v-else-if="search.trim()" class="ua-muted">No users match that search.</p>

        <div v-if="selected" class="ua-target">
          <p class="ua-label">Selected user</p>
          <p class="ua-target-name">{{ selected.name }}</p>
          <p class="ua-muted">ID {{ selected.id }}<span v-if="selected.email"> · {{ selected.email }}</span></p>
          <p class="ua-status">
            <span class="ua-dot" :class="statusClass" />
            {{ statusLabel }}
          </p>
          <p class="ua-muted">{{ presenceLabel }}</p>
          <p class="ua-muted">{{ acceptanceLabel }}</p>
        </div>
      </div>

      <div class="ua-card">
        <p class="ua-label">Animation</p>
        <p v-if="loadingCatalog" class="ua-muted">Loading animations…</p>
        <div v-else class="ua-anims">
          <button
            v-for="item in catalog"
            :key="item.id"
            type="button"
            class="ua-anim"
            :class="{ 'is-selected': animationId === item.id }"
            @click="animationId = item.id"
          >
            <span class="ua-preview">
              <UserAnimationStage
                :motion="item.motion || item.id"
                :kind="item.kind"
                :src="item.src || ''"
                :active="true"
                :width="120"
                :height="88"
              />
            </span>
            <span>{{ item.label }}</span>
          </button>
        </div>

        <div class="ua-actions">
          <button type="button" class="ua-btn ua-btn--start" :disabled="!canStart" @click="ask('start')">
            Start
          </button>
          <button type="button" class="ua-btn ua-btn--stop" :disabled="!canStop" @click="ask('stop')">
            Stop
          </button>
        </div>
        <p v-if="busy" class="ua-muted">Sending command…</p>
      </div>
    </section>

    <div v-if="pending" class="ua-modal" role="dialog" aria-modal="true" aria-labelledby="ua-confirm-title">
      <div class="ua-modal-card">
        <h2 id="ua-confirm-title">{{ pending === 'start' ? 'Start animation' : 'Stop animation' }}</h2>
        <p>
          {{ pending === 'start' ? 'Start' : 'Stop' }}
          <strong>{{ selectedAnimationLabel }}</strong>
          for <strong>{{ selected.name }}</strong> (ID {{ selected.id }}) only.
        </p>
        <div class="ua-actions">
          <button type="button" class="ua-btn ua-btn--start" :disabled="busy" @click="confirm">Confirm</button>
          <button type="button" class="ua-btn ua-btn--ghost" :disabled="busy" @click="pending = null">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/plugins/axios'
import UserAnimationStage from '@/components/animation/UserAnimationStage.vue'

const search = ref('')
const results = ref([])
const searching = ref(false)
const searchError = ref('')
const selected = ref(null)
const detail = ref(null)
const catalog = ref([])
const loadingCatalog = ref(true)
const animationId = ref('cat-dancing')
const busy = ref(false)
const pending = ref(null)
const notice = ref('')
const noticeError = ref(false)
let searchTimer = 0
let searchToken = 0

const desired = computed(() => detail.value?.animation || null)
const running = computed(() => !!desired.value?.enabled)
const canStart = computed(() => !!selected.value && !!animationId.value && !busy.value && !running.value)
const canStop = computed(() => !!selected.value && !busy.value && running.value)
const selectedAnimationLabel = computed(() => catalog.value.find((item) => item.id === animationId.value)?.label || animationId.value)

const statusLabel = computed(() => (running.value ? 'Running' : 'Stopped'))
const statusClass = computed(() => (running.value ? 'is-running' : 'is-stopped'))
const presenceLabel = computed(() => {
  const presence = detail.value?.presence
  if (presence === 'online') return 'Connected'
  if (presence === 'offline') return 'Offline / Not Connected'
  return 'Connection not confirmed'
})
const acceptanceLabel = computed(() => {
  if (detail.value?.command_accepted) return 'Last command accepted. Playback on the target screen is not confirmed from here.'
  return 'Playback confirmation is not collected from the target browser.'
})

function flash(message, isError = false) {
  notice.value = message
  noticeError.value = isError
}

function onSearchInput() {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(runSearch, 300)
}

async function runSearch() {
  const term = search.value.trim()
  results.value = []
  searchError.value = ''
  if (!term) return
  const token = ++searchToken
  searching.value = true
  try {
    const response = await api.get('/user-animations/users', { params: { search: term } })
    if (token !== searchToken) return
    results.value = response.data?.data?.data || []
  } catch (error) {
    if (token !== searchToken) return
    searchError.value = error?.response?.data?.message || 'Search failed.'
  } finally {
    if (token === searchToken) searching.value = false
  }
}

async function selectUser(user) {
  if (busy.value) return
  selected.value = user
  pending.value = null
  notice.value = ''
  await loadUser(user.id)
}

async function loadUser(id) {
  try {
    const response = await api.get(`/user-animations/users/${id}`)
    detail.value = response.data?.data || null
    if (detail.value?.user) {
      selected.value = { ...selected.value, ...detail.value.user }
    }
    if (detail.value?.animation?.animation_id) {
      animationId.value = detail.value.animation.animation_id
    }
  } catch (error) {
    detail.value = null
    flash(error?.response?.data?.message || 'Could not load that user.', true)
  }
}

function ask(action) {
  if (!selected.value || busy.value) return
  pending.value = action
}

async function confirm() {
  if (!selected.value || !pending.value || busy.value) return
  const action = pending.value
  const userId = selected.value.id
  busy.value = true
  try {
    const response = action === 'start'
      ? await api.post(`/user-animations/users/${userId}/start`, { animation_id: animationId.value })
      : await api.post(`/user-animations/users/${userId}/stop`)
    if (selected.value?.id !== userId) return
    detail.value = response.data?.data || null
    pending.value = null
    flash(action === 'start' ? 'Start accepted for this user only.' : 'Stop accepted for this user only.')
  } catch (error) {
    flash(error?.response?.data?.message || 'The command was not accepted.', true)
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  try {
    const response = await api.get('/user-animations/catalog')
    catalog.value = response.data?.data || []
    if (catalog.value[0] && !catalog.value.some((item) => item.id === animationId.value)) {
      animationId.value = catalog.value[0].id
    }
  } catch (error) {
    flash(error?.response?.data?.message || 'Animations could not be loaded.', true)
  } finally {
    loadingCatalog.value = false
  }
})
</script>

<style scoped>
.ua-admin {
  max-width: 920px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  color: #0b0736;
  font-family: Inter, system-ui, sans-serif;
}
.ua-kicker {
  margin: 0;
  font-size: 12px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #5c6573;
}
.ua-title {
  margin: 4px 0;
  font-size: 28px;
  font-weight: 650;
}
.ua-lead,
.ua-muted,
.ua-modal-card p {
  margin: 0;
  color: #5c6573;
  font-size: 13px;
  line-height: 1.45;
}
.ua-muted--error,
.ua-notice--error {
  color: #8a1f11;
}
.ua-notice {
  margin: 16px 0 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: #f5f6fa;
  font-size: 13px;
}
.ua-notice--error {
  background: #fff1f0;
}
.ua-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
  gap: 16px;
  margin-top: 18px;
}
.ua-card,
.ua-modal-card {
  background: #fff;
  border: 1px solid #ebecef;
  border-radius: 16px;
  padding: 16px;
}
.ua-label {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 650;
  color: #5c6573;
}
.ua-input {
  width: 100%;
  border: 1px solid #ebecef;
  border-radius: 12px;
  padding: 10px 12px;
  font: inherit;
  color: #0b0736;
}
.ua-results {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  max-height: 240px;
  overflow: auto;
}
.ua-result {
  width: 100%;
  text-align: left;
  border: 0;
  background: transparent;
  border-radius: 10px;
  padding: 8px;
  cursor: pointer;
  color: inherit;
}
.ua-result span,
.ua-result strong {
  display: block;
}
.ua-result span {
  color: #5c6573;
  font-size: 12px;
}
.ua-result.is-selected,
.ua-result:hover {
  background: #f5f6fa;
}
.ua-target {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #ebecef;
}
.ua-target-name {
  margin: 0;
  font-weight: 650;
}
.ua-status {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 8px 0 4px;
  font-weight: 650;
}
.ua-dot {
  width: 8px;
  height: 8px;
  border-radius: 99px;
  background: #5c6573;
}
.ua-dot.is-running {
  background: #1f7a45;
}
.ua-anims {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ua-anim {
  width: 160px;
  border: 1px solid #ebecef;
  background: #fff;
  border-radius: 12px;
  padding: 8px;
  cursor: pointer;
  color: inherit;
}
.ua-anim.is-selected {
  border-color: #5b3fd6;
}
.ua-preview {
  display: block;
  height: 160px;
  margin-bottom: 6px;
  overflow: hidden;
  border-radius: 8px;
  background: #eceae6;
}
.ua-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}
.ua-btn {
  border: 0;
  border-radius: 999px;
  padding: 8px 14px;
  font-weight: 650;
  cursor: pointer;
}
.ua-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.ua-btn--start {
  color: #fff;
  background: var(--gradient-primary, #5b3fd6);
}
.ua-btn--stop {
  color: #8a1f11;
  background: #fff1f0;
}
.ua-btn--ghost {
  color: #0b0736;
  background: #f5f6fa;
}
.ua-modal {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  background: rgba(11, 7, 54, 0.35);
  padding: 16px;
}
.ua-modal-card {
  width: min(420px, 100%);
}
.ua-modal-card h2 {
  margin: 0 0 8px;
  font-size: 18px;
}
@media (max-width: 800px) {
  .ua-grid {
    grid-template-columns: 1fr;
  }
}
</style>
