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
            <button type="button" class="emp-mgmt__toolbar-btn" @click="goAttendance">
              <iconify-icon icon="lucide:clock" /> Attendance
            </button>
          </div>
        </aside>

        <main class="emp-profile-page__main">
          <div class="emp-profile-page__grid">
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
                  <h6>Bank details</h6>
                  <div class="emp-profile-page__fields">
                    <div class="emp-profile-page__field">
                      <label>Bank</label>
                      <span>{{ employee.raw?.employee_profile?.bank_details?.bank_name || '—' }}</span>
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

            <section v-if="activeTab === 'leave' || !activeTab" class="emp-profile-page__card">
              <h6>Leave summary</h6>
              <div v-if="leaveLoading" class="emp-skeleton" style="min-height:60px;" />
              <div v-else-if="!leaveBalance.length" class="emp-profile-page__empty">No leave balance data</div>
              <div v-else class="emp-profile-page__fields">
                <div v-for="bal in leaveBalance" :key="bal.id" class="emp-profile-page__field">
                  <label>{{ bal.leave_type?.name || 'Leave' }}</label>
                  <span>{{ bal.remaining_days ?? bal.balance ?? 0 }} days left</span>
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

            <!-- ✅ Documents (FIXED 🔥) -->
            <section class="emp-profile-page__card">
              <h6>Documents</h6>

              <div v-if="employee.raw?.employee_profile?.documents">
                <div
                  v-for="(docs, type) in employee.raw.employee_profile.documents"
                  :key="type"
                  class="emp-profile-page__field"
                >
                  <label>{{ formatDocType(type) }}</label>

                  <div v-if="docs.length">
                    <div v-for="doc in docs" :key="doc.id">
                      <a :href="doc.file_url" target="_blank">
                        {{ doc.original_name }}
                      </a>
                    </div>
                  </div>

                  <span v-else>—</span>
                </div>
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
} from '@/services/employeesApi'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const employee = ref(null)
const leaveBalance = ref([])
const assets = ref([])
const leaveLoading = ref(false)
const assetsLoading = ref(false)

const activeTab = computed(() => route.query.tab || '')

const statusLabel = computed(() => {
  const map = { active: 'Active', on_leave: 'On Leave', terminated: 'Terminated', suspended: 'Suspended' }
  return map[employee.value?.employmentStatus] || 'Active'
})

const badgeClass = computed(() => `emp-card__badge--${employee.value?.employmentStatus || 'active'}`)

const documentGroups = computed(() => {
  const docs = employee.value?.raw?.employee_profile?.documents || {}
  return Object.entries(docs).map(([type, list]) => ({
    type,
    count: Array.isArray(list) ? list.length : 0,
  })).filter((g) => g.count > 0)
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

async function loadExtras(id) {
  leaveLoading.value = true
  assetsLoading.value = true
  try {
    const [leave, assetList] = await Promise.all([
      fetchEmployeeLeaveBalance(id).catch(() => []),
      fetchEmployeeAssets(id).catch(() => []),
    ])
    leaveBalance.value = Array.isArray(leave) ? leave : []
    assets.value = Array.isArray(assetList) ? assetList : (assetList?.data || [])
  } finally {
    leaveLoading.value = false
    assetsLoading.value = false
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
  router.push({ path: '/hr', query: { tab: 'Leave / Attendance', mode: 'attendance', employee: employee.value.id } })
}

onMounted(load)
watch(() => route.params.id, load)
</script>

<style>
@import '../../../../css/hr-employees.css';
</style>
