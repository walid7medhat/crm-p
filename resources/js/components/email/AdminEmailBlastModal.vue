<template>
  <div>
    <button v-if="canUse" type="button" class="btn btn-primary" @click="open">
      Send Email
    </button>

    <Teleport to="body">
      <div v-if="show" class="email-modal-overlay" @click.self="close">
        <div class="email-modal">
          <div class="email-modal-head">
            <div>
              <div class="email-modal-title">Send Notification Email</div>
              <div class="email-modal-sub">SUPER ADMIN only</div>
            </div>
            <button type="button" class="email-modal-close" @click="close" aria-label="Close">
              <i class="ri-close-line"></i>
            </button>
          </div>

          <div class="email-modal-body">
            <div class="form-row">
              <label class="form-label">Subject *</label>
              <input v-model.trim="subject" type="text" class="form-control" placeholder="Email subject" />
            </div>

            <div class="form-row">
              <label class="form-label">Agents *</label>
              <div class="small-muted" style="margin: -4px 0 8px 0;">Choose who will receive the email.</div>
              <div v-if="loadingAgents" class="small-muted">Loading agents…</div>
              <div v-else class="recipients-box">
                <div class="recipients-search">
                  <i class="ri-search-line"></i>
                  <input v-model.trim="agentSearch" type="text" placeholder="Search by name/email…" />
                </div>
                <div class="recipients-list">
                  <div class="d-flex align-items-center justify-content-between gap-2 px-1 pb-2" style="position: sticky; top: 0; background: #fff; z-index: 1;">
                    <div class="small-muted mb-0">Quick select</div>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-sm btn-light" @click="toggleSelectAll" :disabled="loadingAgents">
                        {{ allSelected ? 'Clear All' : 'Select All' }}
                      </button>
                    </div>
                  </div>
                  <label v-for="a in filteredAgents" :key="a.email" class="recipient-item">
                    <input type="checkbox" :value="a.email" v-model="recipients" />
                    <span class="recipient-name">{{ a.name || a.email }}</span>
                    <span class="recipient-email">{{ a.email }}</span>
                  </label>
                  <div v-if="filteredAgents.length === 0" class="small-muted" style="padding: 10px 2px;">
                    No agents found.
                  </div>
                </div>
              </div>
            </div>

            <div class="form-row">
              <label class="form-label">URL</label>
              <input v-model.trim="ctaUrl" type="text" class="form-control" placeholder="https://..." />
            </div>

            <div class="form-row">
              <label class="form-label">Body *</label>
              <textarea
                v-model="body"
                class="form-control"
                rows="7"
                placeholder="Write your message…"
              />
              <div class="small-muted">Tip: Use short paragraphs. Each new line becomes a paragraph in the email.</div>
            </div>

            <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
            <div v-if="success" class="alert alert-success py-2">{{ success }}</div>
          </div>

          <div class="email-modal-foot">
            <button type="button" class="btn btn-light" @click="close" :disabled="sending">Cancel</button>
            <button type="button" class="btn btn-primary" @click="send" :disabled="sending || !isValid">
              <span v-if="sending" class="spinner-border spinner-border-sm me-2"></span>
              Send
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/plugins/axios'

const show = ref(false)
const loadingAgents = ref(false)
const agents = ref([])
const agentSearch = ref('')
const recipients = ref([])
const subject = ref('')
const subtitle = ref('New Feature Available')
const ctaUrl = ref('')
const body = ref('')
const sending = ref(false)
const error = ref('')
const success = ref('')

const canUse = computed(() => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    const roles = Array.isArray(u?.roles) ? u.roles : []
    return roles.includes('super_admin')
  } catch {
    return false
  }
})

const filteredAgents = computed(() => {
  const q = agentSearch.value.toLowerCase().trim()
  if (!q) return agents.value
  return agents.value.filter((a) => {
    const name = (a.name || '').toLowerCase()
    const email = (a.email || '').toLowerCase()
    return name.includes(q) || email.includes(q)
  })
})

const allSelected = computed(() => recipients.value.length > 0 && recipients.value.length === agents.value.length)

const isValid = computed(() => {
  return subject.value.trim().length > 0 && body.value.trim().length > 0 && recipients.value.length > 0
})

function open() {
  if (!canUse.value) return
  show.value = true
  success.value = ''
  error.value = ''
  if (!agents.value.length) loadAgents()
}

function close() {
  show.value = false
  sending.value = false
}

function toggleSelectAll() {
  if (allSelected.value) {
    recipients.value = []
  } else {
    recipients.value = agents.value.map((a) => a.email)
  }
}

async function loadAgents() {
  loadingAgents.value = true
  try {
    const res = await api.get('/agents-emails')
    agents.value = Array.isArray(res?.data?.data) ? res.data.data : []
  } catch (e) {
    agents.value = []
  } finally {
    loadingAgents.value = false
  }
}

async function send() {
  error.value = ''
  success.value = ''
  if (!isValid.value) {
    error.value = 'Please fill subject, body, and select at least one recipient.'
    return
  }
  sending.value = true
  try {
    const res = await api.post('/send-email', {
      subject: subject.value.trim(),
      subtitle: subtitle.value.trim() || null,
      cta_url: ctaUrl.value.trim() || null,
      body: body.value,
      recipients: recipients.value,
    })
    if (res?.data?.success) {
      success.value = `Sent successfully to ${res.data.sent || recipients.value.length} recipients.`
    } else {
      error.value = res?.data?.message || 'Failed to send email.'
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to send email.'
  } finally {
    sending.value = false
  }
}

watch(show, (v) => {
  if (!v) {
    agentSearch.value = ''
  }
})

onMounted(() => {
  if (canUse.value) loadAgents()
})
</script>

<style scoped>
.email-modal-overlay{
  position: fixed;
  inset: 0;
  background: rgba(2, 6, 23, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
  padding: 18px;
}
.email-modal{
  width: 860px;
  max-width: 100%;
  max-height: calc(100vh - 36px);
  overflow: hidden;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 18px 60px rgba(0,0,0,0.25);
  display: flex;
  flex-direction: column;
}
.email-modal-head{
  display:flex;
  align-items:center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid #e2e8f0;
  background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
}
.email-modal-title{
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
}
.email-modal-sub{
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}
.email-modal-close{
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 18px;
}
.email-modal-close:hover{ background:#f1f5f9; color:#0f172a; }
.email-modal-body{
  padding: 16px;
  overflow: auto;
}
.form-row{ margin-bottom: 12px; }
.form-label{
  display:block;
  font-size: 12px;
  font-weight: 700;
  color:#334155;
  margin-bottom: 6px;
}
.row-head{
  display:flex;
  align-items:center;
  justify-content: space-between;
  gap: 10px;
}
.small-muted{ font-size:12px; color:#64748b; margin-top:6px; }
.recipients-box{
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}
.recipients-search{
  display:flex;
  align-items:center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}
.recipients-search i{ color:#64748b; }
.recipients-search input{
  border:none;
  outline:none;
  background: transparent;
  width:100%;
  font-size: 13px;
}
.recipients-list{
  max-height: 240px;
  overflow: auto;
  background:#fff;
  padding: 6px 10px;
}
.recipient-item{
  display:flex;
  align-items:center;
  gap: 10px;
  padding: 8px 6px;
  border-radius: 10px;
  cursor: pointer;
}
.recipient-item:hover{ background:#f8fafc; }
.recipient-name{ font-size:13px; font-weight:600; color:#0f172a; }
.recipient-email{ margin-left:auto; font-size:12px; color:#64748b; }
.email-modal-foot{
  padding: 12px 16px;
  border-top: 1px solid #e2e8f0;
  display:flex;
  justify-content:flex-end;
  gap: 10px;
  background:#fff;
}
</style>

