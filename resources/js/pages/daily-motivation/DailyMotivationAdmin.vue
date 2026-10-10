<template>
  <div class="edge-admin">
    <header class="edge-bar">
      <div>
        <p class="edge-title">Daily Message</p>
        <p class="edge-status">{{ statusLine }}</p>
      </div>
      <div class="edge-actions">
        <button type="button" class="edge-btn edge-btn--start" :disabled="busy || !canStart" @click="askStart">
          Start
        </button>
        <button type="button" class="edge-btn edge-btn--stop" :disabled="busy || !overview?.is_enabled" @click="askStop">
          Stop
        </button>
        <button type="button" class="edge-btn edge-btn--test" :disabled="busy || !messages.length" @click="runTest">
          Test
        </button>
        <button type="button" class="edge-btn edge-btn--test" :disabled="busy" @click="pickWorkbook">
          Import
        </button>
        <input
          ref="workbookInput"
          type="file"
          class="edge-file"
          accept=".xlsx,.xls,.csv"
          @change="onWorkbookChosen"
        >
      </div>
    </header>

    <p v-if="notice" class="edge-notice" :class="{ 'is-error': noticeError }" role="status">{{ notice }}</p>

    <section class="edge-list">
      <p class="edge-list-title">Active sales · {{ sales.length }}</p>
      <p v-if="!sales.length" class="edge-empty">No active sales people.</p>
      <ul v-else class="edge-sales">
        <li v-for="person in sales" :key="person.id">{{ person.name }}</li>
      </ul>
    </section>

    <section class="edge-list">
      <p class="edge-list-title">All messages</p>
      <p v-if="!messages.length" class="edge-empty">
        No messages in the database yet. Git push only deploys the page — import
        <strong>OIA_Daily_Sales_Motivation_120.xlsx</strong> here, or run
        <code>php artisan motivation:import</code> on the server.
      </p>
      <button
        v-for="item in pageMessages"
        :key="item.id"
        type="button"
        class="edge-message"
        :class="{ 'is-selected': Number(item.number) === Number(previewNumber) }"
        @click="selectMessage(item)"
      >
        <span>{{ item.number }}</span>
        <span>{{ item.body_en }}</span>
      </button>
      <div v-if="pageCount > 1" class="edge-pages">
        <button type="button" class="edge-btn edge-btn--test" :disabled="page === 1" @click="page -= 1">Previous</button>
        <button
          v-for="n in pageCount"
          :key="n"
          type="button"
          class="edge-page"
          :class="{ 'is-current': n === page }"
          @click="page = n"
        >
          {{ n }}
        </button>
        <button type="button" class="edge-btn edge-btn--test" :disabled="page === pageCount" @click="page += 1">Next</button>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="dialog" class="edge-modal" @click.self="dialog = null">
        <div class="edge-modal__card" role="dialog" aria-modal="true" :aria-label="dialog.title">
          <p class="edge-title">{{ dialog.title }}</p>
          <p>{{ dialog.body }}</p>
          <div class="edge-actions">
            <button type="button" class="edge-btn edge-btn--start" :disabled="busy" @click="confirmDialog">{{ dialog.confirm }}</button>
            <button type="button" class="edge-btn edge-btn--test" :disabled="busy" @click="dialog = null">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { openDailyEdgeTest } from '@/composables/useDailyEdgePreview.js'
import {
  fetchMessages,
  fetchOverview,
  importWorkbook,
  motivationError,
  previewMessage,
  sendToday,
  setDelivery,
} from '@/services/dailyMotivationApi'

const overview = ref(null)
const messages = ref([])
const previewNumber = ref(1)
const page = ref(1)
const pageSize = 20
const notice = ref('')
const noticeError = ref(false)
const busy = ref(false)
const dialog = ref(null)
const workbookInput = ref(null)

const canStart = computed(() => !!overview.value?.messages?.ready && !overview.value?.is_enabled)
const pageCount = computed(() => Math.max(1, Math.ceil(messages.value.length / pageSize)))
const pageMessages = computed(() => {
  const start = (page.value - 1) * pageSize
  return messages.value.slice(start, start + pageSize)
})
const sales = computed(() => overview.value?.sales || [])
const statusLine = computed(() => {
  if (overview.value?.is_enabled) return 'Sending one English message to active sales every day at 9:00 AM.'
  return 'Stopped. Nothing is sent until you press Start.'
})

function flash(message, isError = false) {
  notice.value = message
  noticeError.value = isError
}

async function load() {
  const [overviewResult, messageResult] = await Promise.all([fetchOverview(), fetchMessages()])
  overview.value = overviewResult.data?.data || null
  messages.value = messageResult.data?.data?.messages || []
  if (page.value > pageCount.value) page.value = pageCount.value
}

function selectMessage(item) {
  const number = Number(item.number)
  previewNumber.value = number
  openDailyEdgeTest({
    date_label: overview.value?.date_label || '',
    message: {
      number,
      position: number,
      cycle_length: 120,
      body_en: item.body_en,
      test: true,
    },
  })
}

function askStart() {
  dialog.value = {
    kind: 'start',
    title: 'Start the daily 9:00 AM send?',
    body: 'Each active sales person gets one English message every day at 9:00 AM Dubai time. If it is already past 9:00, today’s message goes out now.',
    confirm: 'Start',
  }
}

function askStop() {
  dialog.value = {
    kind: 'stop',
    title: 'Stop the daily send?',
    body: 'The 9:00 AM messages stop. Places in the cycle stay saved.',
    confirm: 'Stop',
  }
}

async function confirmDialog() {
  if (!dialog.value) return
  busy.value = true
  try {
    if (dialog.value.kind === 'start') {
      await setDelivery(true)
      const response = await sendToday()
      const result = response.data?.data || {}
      flash(result.status === 'waiting'
        ? 'Started. The first send is at 9:00 AM.'
        : `Started. Sent to ${result.sent || 0} people. It sends again every day at 9:00 AM.`)
    } else if (dialog.value.kind === 'stop') {
      await setDelivery(false)
      flash('Stopped. The 9:00 AM send will not run.')
    }
    dialog.value = null
    await load()
  } catch (error) {
    flash(motivationError(error, 'That change was not saved.'), true)
  } finally {
    busy.value = false
  }
}

function pickWorkbook() {
  workbookInput.value?.click()
}

async function onWorkbookChosen(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  busy.value = true
  notice.value = ''
  try {
    const response = await importWorkbook(file)
    const result = response.data?.data || {}
    await load()
    flash(`Imported ${result.total || messages.value.length} messages (${result.created || 0} new, ${result.updated || 0} updated).`)
  } catch (error) {
    flash(motivationError(error, 'The workbook could not be imported.'), true)
  } finally {
    busy.value = false
  }
}

async function runTest() {
  if (!messages.value.length) {
    flash('There are no messages to test.', true)
    return
  }
  busy.value = true
  notice.value = ''
  try {
    const response = await previewMessage(previewNumber.value)
    const payload = response.data?.data
    if (payload?.message) openDailyEdgeTest(payload)
    flash('Test opened on your screen only.')
  } catch (error) {
    flash(motivationError(error, 'The test could not be opened.'), true)
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  try {
    await load()
    if (messages.value[0]) previewNumber.value = messages.value[0].number
  } catch (error) {
    flash(motivationError(error, 'Daily Motivation could not be loaded.'), true)
  }
})
</script>

<style scoped>
.edge-admin {
  padding: 8px 4px 48px;
  color: #0b0736;
  font-family: Inter, system-ui, sans-serif;
}

.edge-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.edge-title,
.edge-list-title,
.edge-status,
.edge-btn,
.edge-message,
.edge-page,
.edge-notice,
.edge-empty,
.edge-modal__card p {
  font-size: 13px !important;
  line-height: 1.4;
}

.edge-title,
.edge-list-title {
  margin: 0;
  color: #0b0736;
  font-weight: 650;
}

.edge-status,
.edge-modal__card p,
.edge-empty {
  margin: 2px 0 0;
  color: #5c6573;
}

.edge-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.edge-btn {
  border: 0;
  border-radius: 999px;
  padding: 6px 12px;
  font-weight: 650;
  cursor: pointer;
}

.edge-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.edge-file {
  display: none;
}

.edge-btn--start {
  color: #fff;
  background: #733e87;
}

.edge-btn--stop {
  color: #8a1f11;
  background: #fff1f0;
}

.edge-btn--test {
  color: #0b0736;
  background: #f5f6fa;
  border: 1px solid #ebecef;
}

.edge-notice {
  margin: 0 0 12px;
  padding: 8px 12px;
  border-radius: 10px;
  background: #e8ddf0;
  color: #0b0736;
}

.edge-notice.is-error {
  background: #fff1f0;
  color: #8a1f11;
}

.edge-list-title {
  margin-bottom: 8px;
}

.edge-list + .edge-list {
  margin-top: 18px;
}

.edge-sales {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.edge-sales li {
  padding: 4px 10px;
  border: 1px solid #ebecef;
  border-radius: 999px;
  background: #fff;
  color: #0b0736;
  font-size: 13px !important;
}

.edge-message {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr);
  gap: 10px;
  width: 100%;
  margin: 0 0 6px;
  padding: 8px 10px;
  text-align: left;
  color: #0b0736;
  background: #fff;
  border: 1px solid #ebecef;
  border-radius: 10px;
  cursor: pointer;
  line-height: 1.4;
}

.edge-pages {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
}

.edge-page {
  min-width: 32px;
  height: 32px;
  border: 1px solid #ebecef;
  border-radius: 8px;
  color: #0b0736;
  background: #fff;
  cursor: pointer;
}

.edge-page.is-current {
  color: #fff;
  background: #733e87;
  border-color: #733e87;
}

.edge-message span:first-child {
  color: #733e87;
  font-weight: 700;
}

.edge-message.is-selected {
  outline: 2px solid #733e87;
}

.edge-modal {
  position: fixed;
  inset: 0;
  z-index: 14000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(11, 7, 54, 0.35);
}

.edge-modal__card {
  width: min(480px, 100%);
  padding: 22px;
  background: #fff;
  border-radius: 18px;
}

@media (max-width: 720px) {
  .edge-bar {
    display: block;
  }

  .edge-actions {
    margin-top: 12px;
  }
}
</style>
