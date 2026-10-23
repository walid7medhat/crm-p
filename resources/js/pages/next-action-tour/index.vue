<template>
  <div class="nat-admin">
    <header class="nat-admin__bar">
      <div>
        <p class="nat-admin__title">Next Action</p>
        <p class="nat-admin__status">Campaigns for this workspace</p>
      </div>
    </header>

    <p class="nat-admin__note" :class="{ 'is-error': noticeError }" role="status">{{ notice }}</p>

    <section class="nat-admin__campaigns" aria-label="Campaigns">
      <article class="nat-admin__card">
        <div class="nat-admin__campaign-top">
          <div>
            <p class="nat-admin__kicker">Campaign</p>
            <p class="nat-admin__card-title">Next Actions Tour</p>
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
        </div>
        <ul>
          <li><strong>Preview</strong> — only on your screen (no notifications to others)</li>
          <li><strong>Start Campaign</strong> — sends the tour live to all active CRM users who are connected</li>
          <li>Each user can see the tour up to <strong>3 times</strong> per campaign launch</li>
          <li><strong>Stop Campaign</strong> — ends delivery and closes open tours for connected users</li>
        </ul>
      </article>

      <article class="nat-admin__card">
        <div class="nat-admin__campaign-top">
          <div>
            <p class="nat-admin__kicker">Campaign</p>
            <p class="nat-admin__card-title">Property Compare — Compare Properties Like a Pro</p>
            <p class="nat-admin__status">{{ compareStatusLine }}</p>
          </div>
          <div class="nat-admin__actions">
            <button type="button" class="nat-admin__btn nat-admin__btn--preview" :disabled="busy" @click="openComparePreview">
              Preview
            </button>
            <button type="button" class="nat-admin__btn nat-admin__btn--start" :disabled="busy || compareLiveForAll" @click="askCompareStart">
              Start Campaign
            </button>
            <button type="button" class="nat-admin__btn nat-admin__btn--stop" :disabled="busy || !compareStatus?.is_active" @click="askCompareStop">
              Stop Campaign
            </button>
          </div>
        </div>
        <div class="nat-admin__test">
          <input
            v-model="userQuery"
            type="search"
            placeholder="Test with one person"
            :disabled="busy"
            @input="searchUsers"
          />
          <button type="button" class="nat-admin__btn" :disabled="busy || !pickedUser" @click="askCompareTest">
            Start for this person
          </button>
          <ul v-if="userResults.length" class="nat-admin__people">
            <li v-for="person in userResults" :key="person.id">
              <button type="button" :class="{ 'is-on': pickedUser?.id === person.id }" @click="pickUser(person)">
                {{ person.name }}
              </button>
            </li>
          </ul>
        </div>
        <ul>
          <li><strong>Preview</strong> and <strong>Start</strong> both open with the New Feature intro, then the real All Listings compare flow</li>
          <li><strong>Start for this person</strong> sends it only to the person you pick, so you can test first</li>
          <li><strong>Start Campaign</strong> then shows it once to every connected active user</li>
          <li>PNG and PDF are pointed at only. Nothing is downloaded</li>
          <li>When it finishes, the overlay closes and the normal page stays</li>
        </ul>
      </article>
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
import { endNextActionTourPreview, isTourPreviewActive, startNextActionTourPreview } from '@/composables/useNextActionTourPreview.js'
import { closePropertyCompareCampaignPreview, startPropertyCompareCampaignLive, startPropertyCompareCampaignPreview } from '@/composables/usePropertyCompareCampaignPreview.js'

const status = ref(null)
const compareStatus = ref(null)
const busy = ref(false)
const notice = ref('Load status, then Preview locally or Start Campaign for everyone.')
const noticeError = ref(false)
const dialog = ref(null)
const userQuery = ref('')
const userResults = ref([])
const pickedUser = ref(null)
let searchTimer = null

const compareLiveForAll = computed(() => !!compareStatus.value?.is_active && !compareStatus.value?.target_user_id)

const statusLine = computed(() => {
  if (!status.value) return 'Loading campaign status…'
  if (status.value.is_active) {
    return `Campaign LIVE · launch ${String(status.value.launch_id || '').slice(0, 8)}… · audience ~${status.value.audience_count || 0}`
  }
  return 'Campaign stopped · Preview still works on this browser only'
})

const compareStatusLine = computed(() => {
  if (!compareStatus.value) return 'Loading campaign status…'
  if (compareStatus.value.is_active && compareStatus.value.target_user_id) {
    return `Testing with ${compareStatus.value.target_name || 'one person'} only`
  }
  if (compareStatus.value.is_active) {
    return `Campaign LIVE · each person sees it once · audience ~${compareStatus.value.audience_count || 0}`
  }
  return 'Campaign stopped · Preview still works on this browser only'
})

async function loadStatus() {
  try {
    const [next, compare] = await Promise.all([
      api.get('/next-action-tour/admin/status'),
      api.get('/next-action-tour/admin/status', { params: { feature: 'property_compare' } }),
    ])
    status.value = next.data?.data || null
    compareStatus.value = compare.data?.data || null
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

function openComparePreview() {
  noticeError.value = false
  if (isTourPreviewActive.value) endNextActionTourPreview()
  startPropertyCompareCampaignPreview()
  notice.value = 'Property Compare preview opens All Listings. Nothing is downloaded.'
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
    feature: 'next_action',
    title: 'Stop campaign?',
    body: 'New deliveries stop. Open tours on connected users will close.',
    confirm: 'Stop Campaign',
  }
}

function currentUserId() {
  try {
    return Number(JSON.parse(localStorage.getItem('user') || 'null')?.id) || null
  } catch {
    return null
  }
}

function searchUsers() {
  pickedUser.value = null
  clearTimeout(searchTimer)
  const query = userQuery.value.trim()
  if (query.length < 2) {
    userResults.value = []
    return
  }
  searchTimer = setTimeout(async () => {
    try {
      const response = await api.get('/next-action-tour/admin/users', { params: { search: query } })
      userResults.value = response.data?.data || []
    } catch {
      userResults.value = []
    }
  }, 200)
}

function pickUser(person) {
  pickedUser.value = person
  userQuery.value = person.name
  userResults.value = []
}

function askCompareTest() {
  if (!pickedUser.value) return
  dialog.value = {
    kind: 'start',
    feature: 'property_compare',
    user_id: pickedUser.value.id,
    title: `Start for ${pickedUser.value.name}?`,
    body: 'Only this person sees the compare tour. Everyone else stays on the normal page until you start the campaign for all.',
    confirm: 'Start for this person',
  }
}

function askCompareStart() {
  dialog.value = {
    kind: 'start',
    feature: 'property_compare',
    title: 'Start Property Compare?',
    body: 'Every connected active user sees the New Feature intro and the compare walkthrough once for this start. Nothing is downloaded.',
    confirm: 'Start Campaign',
  }
}

function askCompareStop() {
  dialog.value = {
    kind: 'stop',
    feature: 'property_compare',
    title: 'Stop Property Compare?',
    body: 'New deliveries stop. An open compare tour on a connected user will close.',
    confirm: 'Stop Campaign',
  }
}

async function confirmDialog() {
  if (!dialog.value) return
  const kind = dialog.value.kind
  const feature = dialog.value.feature || 'next_action'
  const userId = dialog.value.user_id || null
  dialog.value = null
  busy.value = true
  noticeError.value = false
  try {
    const response = await api.post(`/next-action-tour/admin/${kind}`, {
      feature,
      ...(userId ? { user_id: userId } : {}),
    })
    const data = response.data?.data || null
    if (feature === 'property_compare') {
      compareStatus.value = data || compareStatus.value
      const mine = !data?.target_user_id || Number(data.target_user_id) === currentUserId()
      if (kind === 'start' && data?.launch_id && mine) startPropertyCompareCampaignLive(data.launch_id)
      if (kind === 'stop') closePropertyCompareCampaignPreview()
      notice.value = kind === 'stop'
        ? 'Property Compare campaign stopped.'
        : data?.target_name
          ? `Sent to ${data.target_name} only. Start Campaign when you want everyone.`
          : 'Property Compare started. Each connected person sees it once.'
    } else {
      status.value = data || status.value
      notice.value = kind === 'start'
        ? 'Campaign started. Connected users should receive the tour now.'
        : 'Campaign stopped.'
    }
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
.nat-admin__test {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 12px 0 4px;
}
.nat-admin__test input {
  min-width: min(240px, 100%);
  border: 1px solid #ebecef;
  border-radius: 999px;
  padding: 10px 14px;
  font: inherit;
  font-size: 13px;
}
.nat-admin__people {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  width: 100%;
  margin: 0;
  padding: 0;
  list-style: none;
}
.nat-admin__people button {
  border: 0;
  border-radius: 999px;
  padding: 6px 10px;
  background: #f4f1f7;
  color: #3b3558;
  font: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.nat-admin__people button.is-on {
  color: #fff;
  background: #733e87;
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
.nat-admin__campaigns {
  display: grid;
  gap: 14px;
}
.nat-admin__campaign-top {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.nat-admin__kicker {
  margin: 0 0 4px;
  color: #733e87;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.nat-admin__card {
  padding: 18px 18px 8px;
  border-radius: 16px;
  border: 1px solid #ebecef;
  background: #fff;
  box-shadow: 0 10px 28px rgba(11, 7, 54, 0.06);
}
@media (max-width: 767px) {
  .nat-admin__actions,
  .nat-admin__campaign-top {
    width: 100%;
  }
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
