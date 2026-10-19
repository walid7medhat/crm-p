<template>
  <div class="nat-admin">
    <header class="nat-admin__bar">
      <div>
        <p class="nat-admin__title">Next Actions Tour</p>
        <p class="nat-admin__status">{{ statusLine }}</p>
      </div>
      <div class="nat-admin__actions">
        <button type="button" class="nat-admin__btn nat-admin__btn--preview" :disabled="busy" @click="runPreview">
          Preview
        </button>
        <button type="button" class="nat-admin__btn nat-admin__btn--start" :disabled="busy || status?.is_active" @click="askStart">
          Start Campaign
        </button>
        <button type="button" class="nat-admin__btn nat-admin__btn--stop" :disabled="busy || !status?.is_active" @click="askStop">
          Stop Campaign
        </button>
      </div>
    </header>

    <p class="nat-admin__note" :class="{ 'is-error': noticeError }" role="status">{{ notice }}</p>

    <section class="nat-admin__card">
      <p class="nat-admin__card-title">How it works</p>
      <ul>
        <li><strong>Preview</strong> — only on your screen (no notifications to others)</li>
        <li><strong>Start Campaign</strong> — sends the tour live to all active CRM users who are connected</li>
        <li>Each user can see the tour up to <strong>3 times</strong> per campaign launch</li>
        <li><strong>Stop Campaign</strong> — ends delivery and closes open tours for connected users</li>
      </ul>
    </section>

    <Teleport to="body">
      <div v-if="dialog" class="nat-admin__modal" @click.self="dialog = null">
        <div class="nat-admin__modal-card" role="dialog" aria-modal="true">
          <p class="nat-admin__card-title">{{ dialog.title }}</p>
          <p class="nat-admin__modal-body">{{ dialog.body }}</p>
          <div class="nat-admin__actions">
            <button type="button" class="nat-admin__btn nat-admin__btn--start" :disabled="busy" @click="confirmDialog">
              {{ dialog.confirm }}
            </button>
            <button type="button" class="nat-admin__btn" :disabled="busy" @click="dialog = null">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/plugins/axios'
import { startNextActionTourPreview } from '@/composables/useNextActionTourPreview.js'

const status = ref(null)
const busy = ref(false)
const notice = ref('Load status, then Preview locally or Start Campaign for everyone.')
const noticeError = ref(false)
const dialog = ref(null)

const statusLine = computed(() => {
  if (!status.value) return 'Loading campaign status…'
  if (status.value.is_active) {
    return `Campaign LIVE · launch ${String(status.value.launch_id || '').slice(0, 8)}… · audience ~${status.value.audience_count || 0}`
  }
  return 'Campaign stopped · Preview still works on this browser only'
})

async function loadStatus() {
  try {
    const response = await api.get('/next-action-tour/admin/status')
    status.value = response.data?.data || null
  } catch (error) {
    noticeError.value = true
    notice.value = error?.response?.data?.message || 'Could not load campaign status.'
  }
}

function runPreview() {
  noticeError.value = false
  startNextActionTourPreview()
  notice.value = 'Local Preview running. Other users are not notified.'
}

function askStart() {
  dialog.value = {
    kind: 'start',
    title: 'Start campaign?',
    body: 'Connected active CRM users will get the New Feature intro and Next Actions tour (max 3 views each).',
    confirm: 'Start Campaign',
  }
}

function askStop() {
  dialog.value = {
    kind: 'stop',
    title: 'Stop campaign?',
    body: 'New deliveries stop. Open tours on connected users will close.',
    confirm: 'Stop Campaign',
  }
}

async function confirmDialog() {
  if (!dialog.value) return
  const kind = dialog.value.kind
  dialog.value = null
  busy.value = true
  noticeError.value = false
  try {
    const response = await api.post(`/next-action-tour/admin/${kind}`)
    status.value = response.data?.data || status.value
    notice.value = kind === 'start'
      ? 'Campaign started. Connected users should receive the tour now.'
      : 'Campaign stopped.'
  } catch (error) {
    noticeError.value = true
    notice.value = error?.response?.data?.message || `Could not ${kind} the campaign.`
  } finally {
    busy.value = false
  }
}

onMounted(loadStatus)
</script>

<style scoped>
.nat-admin {
  max-width: 880px;
  margin: 0 auto;
  padding: 24px 20px 48px;
}
.nat-admin__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}
.nat-admin__title {
  margin: 0;
  color: #0b0736;
  font-size: 22px;
  font-weight: 800;
}
.nat-admin__status {
  margin: 6px 0 0;
  color: #6b6580;
  font-size: 13px;
}
.nat-admin__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.nat-admin__btn {
  appearance: none;
  border: 0;
  border-radius: 999px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 700;
  color: #3b3558;
  background: #f4f1f7;
  cursor: pointer;
}
.nat-admin__btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.nat-admin__btn--preview {
  color: #fff;
  background: var(--gradient-primary, linear-gradient(135deg, #733e87, #9b59b6));
  box-shadow: 0 8px 18px rgba(115, 62, 135, 0.25);
}
.nat-admin__btn--start {
  color: #fff;
  background: #1a7f4b;
}
.nat-admin__btn--stop {
  color: #fff;
  background: #a33b3b;
}
.nat-admin__note {
  margin: 0 0 18px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #f8f5fb;
  color: #4a4460;
  font-size: 13px;
}
.nat-admin__note.is-error {
  background: #fff1f1;
  color: #8a1f1f;
}
.nat-admin__card {
  padding: 18px 18px 8px;
  border-radius: 16px;
  border: 1px solid #ebecef;
  background: #fff;
  box-shadow: 0 10px 28px rgba(11, 7, 54, 0.06);
}
.nat-admin__card-title {
  margin: 0 0 8px;
  color: #0b0736;
  font-size: 14px;
  font-weight: 750;
}
.nat-admin__card ul {
  margin: 0;
  padding: 0 0 8px 18px;
  color: #5b5572;
  font-size: 14px;
  line-height: 1.55;
}
.nat-admin__modal {
  position: fixed;
  inset: 0;
  z-index: 100080;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(11, 7, 54, 0.45);
}
.nat-admin__modal-card {
  width: min(420px, 100%);
  padding: 18px;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 20px 48px rgba(11, 7, 54, 0.22);
}
.nat-admin__modal-body {
  margin: 0 0 16px;
  color: #5b5572;
  font-size: 14px;
  line-height: 1.45;
}
</style>
