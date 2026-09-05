<template>
  <div class="dashboard-main-body hr-screen emp-profile-page">
    <div class="emp-profile">
      <button type="button" class="emp-profile-page__back" @click="goBack">
        <iconify-icon icon="lucide:arrow-left" />
        <span>Back to employees</span>
      </button>

      <div v-if="loading" class="emp-profile-page__loading">
        <div class="emp-skeleton" style="min-height:520px;" />
      </div>

      <div v-else-if="error" class="emp-error">
        <h6>{{ error }}</h6>
        <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="load">Retry</button>
      </div>

      <div v-else-if="employee" class="emp-profile-page__layout">
        <aside class="emp-profile-page__side">
          <div class="emp-profile-page__side-head">
            <img :src="employee.avatar" :alt="employee.name" class="emp-profile-page__avatar" />
            <p class="emp-profile-page__name">{{ employee.name }}</p>
            <p class="emp-profile-page__meta">{{ employee.employeeCode }}</p>
            <p class="emp-profile-page__role">{{ employee.designation }} · {{ employee.department }}</p>
            <span class="emp-card__badge" :class="badgeClass">{{ statusLabel }}</span>
          </div>

          <div class="emp-profile-page__side-list">
            <p><span>Work email</span><strong>{{ employee.email }}</strong></p>
            <p><span>Phone</span><strong>{{ employee.phone }}</strong></p>
            <p><span>Personal phone</span><strong>{{ employee.personalPhone }}</strong></p>
            <p><span>Manager</span><strong>{{ employee.manager }}</strong></p>
            <p><span>Branch</span><strong>{{ employee.branch }}</strong></p>
            <p><span>Joining date</span><strong>{{ formatDate(employee.joiningDate) }}</strong></p>
          </div>

          <div class="emp-profile-page__side-actions">
            <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="editEmployee">
              <iconify-icon icon="lucide:pencil" /> Edit employee
            </button>
            <button
              type="button"
              class="emp-mgmt__toolbar-btn"
              :class="{ 'is-active': showingAttendance }"
              @click="goAttendance"
            >
              <iconify-icon icon="lucide:clock" /> Attendance
            </button>
          </div>
        </aside>

        <main class="emp-profile-page__main">
          <div v-if="showingAttendance" class="emp-profile-page__attendance">
            <UserAttendanceCarousel :user-id="employee.id" :key="employee.id" />
          </div>

          <div v-else class="emp-profile-page__grid">
            <section class="emp-profile-page__card">
              <h6>Personal information</h6>
              <div class="emp-profile-page__fields">
                <div class="emp-profile-page__field"><label>Full name</label><span>{{ employee.name }}</span></div>
                <div class="emp-profile-page__field"><label>Nationality</label><span>{{ employee.nationality }}</span></div>
                <div class="emp-profile-page__field"><label>Employee ID</label><span>{{ employee.employeeCode }}</span></div>
                <div class="emp-profile-page__field"><label>Role</label><span>{{ employee.role }}</span></div>
                <div class="emp-profile-page__field"><label>Father name</label><span>{{ employee.raw?.employee_profile?.father_name || '—' }}</span></div>
                <div class="emp-profile-page__field"><label>Mother name</label><span>{{ employee.raw?.employee_profile?.mother_name || '—' }}</span></div>
                <div class="emp-profile-page__field"><label>Religion</label><span>{{ employee.raw?.employee_profile?.religion || '—' }}</span></div>
              </div>
            </section>

            <section class="emp-profile-page__card">
              <h6>Contact information</h6>
              <div class="emp-profile-page__fields">
                <div class="emp-profile-page__field"><label>Work email</label><span>{{ employee.email }}</span></div>
                <div class="emp-profile-page__field"><label>Phone</label><span>{{ employee.phone }}</span></div>
                <div class="emp-profile-page__field"><label>Personal phone</label><span>{{ employee.personalPhone }}</span></div>
                <div class="emp-profile-page__field"><label>Manager</label><span>{{ employee.manager }}</span></div>
                <div class="emp-profile-page__field"><label>Home country phone</label><span>{{ employee.raw?.employee_profile?.home_country_phone || '—' }}</span></div>
              </div>
            </section>
            <section class="emp-profile-page__card">
              <h6>Emergency Contact (UAE)</h6>
              <div class="emp-profile-page__fields">
                <div class="emp-profile-page__field"><label>Name</label><span>{{ employee.raw?.employee_profile?.emergency_contact?.name || '—' }}</span></div>
                <div class="emp-profile-page__field"><label>Phone</label><span>{{ employee.raw?.employee_profile?.emergency_contact?.phone || '—' }}</span></div>
                <div class="emp-profile-page__field"><label>Relation</label><span>{{ employee.raw?.employee_profile?.emergency_contact?.relation || '—' }}</span></div>
              </div>
            </section>

            <section class="emp-profile-page__card emp-profile-page__card--wide">
              <h6>Health Disclosure</h6>
              <p v-if="!checkedHealthItems.length" class="emp-profile-page__empty">No health conditions disclosed</p>
              <div v-else class="emp-profile-page__fields">
                <div v-for="item in checkedHealthItems" :key="item.key" class="emp-profile-page__field">
                  <label>{{ item.label }}</label>
                  <span>{{ item.note || 'Disclosed, no additional notes' }}</span>
                </div>
              </div>
            </section>

            <section class="emp-profile-page__card">
                  <h6>Bank details</h6>
                  <div class="emp-profile-page__fields">
                    <div class="emp-profile-page__field">
                      <label>Account Holder Name</label>
                      <span>{{ employee.raw?.employee_profile?.bank_details?.bank_account_holder_name || '—' }}</span>
                    </div>
                    <div class="emp-profile-page__field">
                      <label>Bank</label>
                      <span>{{ employee.raw?.employee_profile?.bank_details?.bank_name || '—' }}</span>
                    </div>
                    <div class="emp-profile-page__field">
                      <label>Account Number</label>
                      <span>{{ employee.raw?.employee_profile?.bank_details?.account_number || '—' }}</span>
                    </div>
                    <div class="emp-profile-page__field">
                      <label>Branch Location</label>
                      <span>{{ employee.raw?.employee_profile?.bank_details?.branch_location || '—' }}</span>
                    </div>
                    <div class="emp-profile-page__field">
                      <label>Swift Code</label>
                      <span>{{ employee.raw?.employee_profile?.bank_details?.swift_code || '—' }}</span>
                    </div>
                    <div class="emp-profile-page__field">
                      <label>IBAN</label>
                      <span>{{ employee.raw?.employee_profile?.bank_details?.iban_number || '—' }}</span>
                    </div>
                  </div>
                </section>
            <section class="emp-profile-page__card">
              <h6>Employment details</h6>
              <div class="emp-profile-page__fields">
                <div class="emp-profile-page__field"><label>Department</label><span>{{ employee.department }}</span></div>
                <div class="emp-profile-page__field"><label>Position</label><span>{{ employee.designation }}</span></div>
                <div class="emp-profile-page__field"><label>Branch</label><span>{{ employee.branch }}</span></div>
                <div class="emp-profile-page__field"><label>Joining date</label><span>{{ formatDate(employee.joiningDate) }}</span></div>
                <div class="emp-profile-page__field"><label>Employment type</label><span>{{ employee.salaryType || '—' }}</span></div>
                <div class="emp-profile-page__field"><label>Status</label><span>{{ statusLabel }}</span></div>
                <div class="emp-profile-page__field"><label>Contract end</label><span>{{ formatDate(employee.raw?.employee_profile?.contract_end_date) }}</span></div>
                <div class="emp-profile-page__field"><label>Probation end</label><span>{{ formatDate(employee.raw?.employee_profile?.probation_end_date) }}</span></div>
              </div>
            </section>

            <section v-if="activeTab === 'leave' || !activeTab" class="emp-profile-page__card emp-profile-page__card--wide">
              <h6>Leave summary</h6>
              <div v-if="leaveLoading" class="emp-skeleton" style="min-height:60px;" />
              <div v-else-if="!leaveBalance.length" class="emp-profile-page__empty">No leave balance data</div>
              <div v-else class="emp-leave-balance-list">
                <div v-for="bal in leaveBalance" :key="bal.id" class="emp-leave-balance-row">
                  <div class="emp-leave-balance-row__name">{{ bal.leave_type?.name || 'Leave' }}</div>

                  <div v-if="editingBalanceId === bal.id" class="emp-leave-balance-row__edit">
                    <label>Total <input type="number" min="0" step="0.5" v-model.number="balanceEditForm.total_days" /></label>
                    <label>Used <input type="number" min="0" step="0.5" v-model.number="balanceEditForm.used_days" /></label>
                    <span class="emp-leave-balance-row__remaining">
                      = {{ Math.max(0, (balanceEditForm.total_days || 0) - (balanceEditForm.used_days || 0)) }} remaining
                    </span>
                    <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" :disabled="balanceSaving" @click="saveBalanceEdit(bal)">
                      {{ balanceSaving ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" class="emp-mgmt__toolbar-btn" :disabled="balanceSaving" @click="cancelBalanceEdit">Cancel</button>
                  </div>

                  <div v-else class="emp-leave-balance-row__view">
                    <span>{{ bal.total_days ?? 0 }} total &middot; {{ bal.used_days ?? 0 }} used &middot; <strong>{{ bal.remaining_days ?? bal.balance ?? 0 }} left</strong></span>
                    <button type="button" class="emp-profile-docs__delete" title="Edit balance" @click="startBalanceEdit(bal)">
                      <iconify-icon icon="lucide:pencil" />
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <section v-if="activeTab === 'assets' || !activeTab" class="emp-profile-page__card">
              <h6>Assigned assets</h6>
              <div v-if="assetsLoading" class="emp-skeleton" style="min-height:60px;" />
              <div v-else-if="!assets.length" class="emp-profile-page__empty">No assets assigned</div>
              <ul v-else class="emp-profile-page__asset-list">
                <li v-for="asset in assets" :key="asset.id">
                  <strong>{{ asset.name || asset.asset_name }}</strong>
                  <span>{{ asset.asset_type?.name || asset.type }}</span>
                </li>
              </ul>
            </section>

            <section class="emp-profile-page__card emp-profile-page__card--wide">
              <h6>Evaluations</h6>
              <div v-if="evaluationsLoading" class="emp-skeleton" style="min-height:60px;" />
              <p v-else-if="!evaluations.length" class="emp-profile-page__empty">No submitted evaluations yet</p>
              <div v-else class="emp-profile-docs">
                <article class="emp-profile-docs__group">
                  <ul>
                    <li v-for="ev in evaluations" :key="ev.id">
                      <iconify-icon icon="lucide:file-text" />
                      <a v-if="ev.pdf_url" :href="ev.pdf_url" target="_blank" rel="noopener">
                        {{ ev.milestone_months }}-month review — {{ formatDate(ev.submitted_at) }}
                      </a>
                      <span v-else>{{ ev.milestone_months }}-month review — PDF unavailable</span>
                    </li>
                  </ul>
                </article>
              </div>
            </section>

            <section class="emp-profile-page__card emp-profile-page__card--wide">
              <h6>Documents</h6>
              <div v-if="documentGroups.length" class="emp-profile-docs">
                <article v-for="group in documentGroups" :key="group.type" class="emp-profile-docs__group">
                  <p class="emp-profile-docs__type">{{ formatDocType(group.type) }}</p>
                  <ul>
                    <li v-for="doc in group.files" :key="doc.id" class="emp-profile-docs__item">
                      <iconify-icon icon="lucide:file-text" />
                      <a v-if="doc.file_url" :href="doc.file_url" target="_blank" rel="noopener">
                        {{ doc.document_name || doc.original_name || doc.name || 'View file' }}
                      </a>
                      <span v-else>{{ doc.document_name || doc.original_name || doc.name || '—' }}</span>
                      <button
                        type="button"
                        class="emp-profile-docs__delete"
                        title="Delete document"
                        :disabled="deletingDocumentId === doc.id"
                        @click="handleDeleteDocument(doc)"
                      >
                        <iconify-icon icon="lucide:trash-2" />
                      </button>
                    </li>
                  </ul>
                </article>
              </div>
              <p v-else class="emp-profile-page__empty">No documents uploaded</p>
            </section>

            <section class="emp-profile-page__card emp-profile-page__card--wide">
              <h6>Activity timeline</h6>
              <p class="emp-profile-page__timeline">
                Joined {{ formatDate(employee.joiningDate) }}
                <span v-if="employee.raw?.updated_at"> · Last updated {{ formatDateTime(employee.raw.updated_at) }}</span>
              </p>
            </section>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchEmployeeRaw,
  normalizeEmployee,
  fetchEmployeeLeaveBalance,
  fetchEmployeeAssets,
  fetchEmployeeEvaluations,
  deleteEmployeeDocument,
  updateEmployeeLeaveBalance,
} from '@/services/employeesApi'
import UserAttendanceCarousel from '@/components/Users/UserAttendanceCarousel.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const employee = ref(null)
const leaveBalance = ref([])
const assets = ref([])
const evaluations = ref([])
const leaveLoading = ref(false)
const assetsLoading = ref(false)
const evaluationsLoading = ref(false)
const deletingDocumentId = ref(null)
const editingBalanceId = ref(null)
const balanceEditForm = ref({ total_days: 0, used_days: 0 })
const balanceSaving = ref(false)

const activeTab = computed(() => String(route.query.tab || ''))
const showingAttendance = computed(() => activeTab.value === 'attendance')

const statusLabel = computed(() => {
  const map = { active: 'Active', on_leave: 'On Leave', terminated: 'Terminated', suspended: 'Suspended' }
  return map[employee.value?.employmentStatus] || 'Active'
})

const badgeClass = computed(() => `emp-card__badge--${employee.value?.employmentStatus || 'active'}`)

const HEALTH_DISCLOSURE_LABELS = {
  physical_disability: 'Physical Disability',
  major_illness_surgery: 'Major accidents / illnesses / surgery',
  hypertension: 'Hypertension',
  diabetes: 'Diabetes',
  asthma: 'Asthma',
  chronic_kidney_disease: 'Chronic Kidney Disease',
  fatty_liver_disease: 'Fatty Liver Disease',
}

const checkedHealthItems = computed(() => {
  const items = employee.value?.raw?.employee_profile?.health_disclosure
  if (!Array.isArray(items)) return []
  return items
    .filter((item) => item?.checked)
    .map((item) => ({
      key: item.key,
      label: HEALTH_DISCLOSURE_LABELS[item.key] || item.key,
      note: item.note || '',
    }))
})

const documentGroups = computed(() => {
  const docs = employee.value?.raw?.employee_profile?.documents
  if (!docs) return []
  const groups = Array.isArray(docs)
    ? docs.reduce((acc, doc) => {
        const type = doc.document_type || 'other'
        if (!acc[type]) acc[type] = []
        acc[type].push(doc)
        return acc
      }, {})
    : docs
  return Object.entries(groups)
    .map(([type, list]) => {
      const seen = new Set()
      const files = (Array.isArray(list) ? list : []).filter((doc) => {
        const key = `${doc?.file_url || ''}|${doc?.original_name || doc?.name || ''}|${doc?.id || ''}`
        const nameKey = `${doc?.file_url || doc?.original_name || doc?.name || ''}`
        if (!nameKey || seen.has(nameKey)) return false
        seen.add(nameKey)
        seen.add(key)
        return true
      })
      return { type, files }
    })
    .filter((group) => group.files.length > 0)
})

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatDocType(type) {
  return String(type).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

async function handleDeleteDocument(doc) {
  if (!doc?.id) return
  if (!window.confirm(`Delete "${doc.document_name || doc.original_name || 'this document'}"?`)) return

  deletingDocumentId.value = doc.id
  try {
    await deleteEmployeeDocument(doc.id)
    const docs = employee.value?.raw?.employee_profile?.documents
    if (Array.isArray(docs)) {
      employee.value.raw.employee_profile.documents = docs.filter((d) => d.id !== doc.id)
    } else if (docs && typeof docs === 'object') {
      Object.keys(docs).forEach((type) => {
        if (Array.isArray(docs[type])) {
          docs[type] = docs[type].filter((d) => d.id !== doc.id)
        }
      })
    }
  } catch (e) {
    window.alert(e?.response?.data?.message || 'Failed to delete document')
  } finally {
    deletingDocumentId.value = null
  }
}

function startBalanceEdit(bal) {
  editingBalanceId.value = bal.id
  balanceEditForm.value = {
    total_days: Number(bal.total_days) || 0,
    used_days: Number(bal.used_days) || 0,
  }
}

function cancelBalanceEdit() {
  editingBalanceId.value = null
}

async function saveBalanceEdit(bal) {
  balanceSaving.value = true
  try {
    const updated = await updateEmployeeLeaveBalance(employee.value.id, bal.leave_type_id, {
      total_days: balanceEditForm.value.total_days,
      used_days: balanceEditForm.value.used_days,
    })
    const idx = leaveBalance.value.findIndex((b) => b.id === bal.id)
    if (idx !== -1) {
      leaveBalance.value[idx] = { ...leaveBalance.value[idx], ...updated }
    }
    editingBalanceId.value = null
  } catch (e) {
    window.alert(e?.response?.data?.message || 'Failed to update leave balance')
  } finally {
    balanceSaving.value = false
  }
}

async function loadExtras(id) {
  leaveLoading.value = true
  assetsLoading.value = true
  evaluationsLoading.value = true
  try {
    const [leave, assetList, evaluationList] = await Promise.all([
      fetchEmployeeLeaveBalance(id).catch(() => []),
      fetchEmployeeAssets(id).catch(() => []),
      fetchEmployeeEvaluations(id).catch(() => []),
    ])
    leaveBalance.value = Array.isArray(leave) ? leave : []
    assets.value = Array.isArray(assetList) ? assetList : (assetList?.data || [])
    evaluations.value = Array.isArray(evaluationList) ? evaluationList : []
  } finally {
    leaveLoading.value = false
    assetsLoading.value = false
    evaluationsLoading.value = false
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const raw = await fetchEmployeeRaw(route.params.id)
    employee.value = normalizeEmployee(raw)
    await loadExtras(route.params.id)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Employee not found'
    employee.value = null
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/hr')
}

function editEmployee() {
  router.push({ path: '/hr', query: { edit: employee.value.id } })
}

function goAttendance() {
  if (!employee.value?.id) return
  if (showingAttendance.value) {
    router.replace({ path: route.path })
    return
  }
  router.replace({ path: route.path, query: { tab: 'attendance' } })
}

onMounted(load)
watch(() => route.params.id, load)
</script>

<style>
@import '../../../../css/hr-employees.css';
</style>
