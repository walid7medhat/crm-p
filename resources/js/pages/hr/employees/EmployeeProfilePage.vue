<template>
  <div class="dashboard-main-body hr-screen emp-profile-page">
    <div class="emp-profile">
      <button type="button" class="emp-mgmt__toolbar-btn" style="margin-bottom:12px;" @click="goBack">
        <iconify-icon icon="lucide:arrow-left" />
        <span>Back to employees</span>
      </button>

      <div v-if="loading" class="emp-skeleton" style="min-height:120px;margin-bottom:16px;" />
      <div v-else-if="error" class="emp-error">
        <h3>{{ error }}</h3>
        <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="load">Retry</button>
      </div>

      <template v-else-if="employee">
        <header class="emp-profile__hero">
          <img :src="employee.avatar" :alt="employee.name" class="emp-profile__hero-avatar" />
          <div>
            <h1>{{ employee.name }}</h1>
            <p>{{ employee.employeeCode }} · {{ employee.designation }} · {{ employee.department }}</p>
            <span class="emp-card__badge" :class="badgeClass" style="margin-top:8px;">{{ statusLabel }}</span>
          </div>
        </header>

        <div class="emp-profile__sections">
          <section class="emp-profile__section">
            <h2>Personal information</h2>
            <div class="emp-profile__grid">
              <div class="emp-profile__field"><label>Full name</label><span>{{ employee.name }}</span></div>
              <div class="emp-profile__field"><label>Nationality</label><span>{{ employee.nationality }}</span></div>
              <div class="emp-profile__field"><label>Employee ID</label><span>{{ employee.employeeCode }}</span></div>
              <div class="emp-profile__field"><label>Role</label><span>{{ employee.role }}</span></div>
            </div>
          </section>

          <section class="emp-profile__section">
            <h2>Contact information</h2>
            <div class="emp-profile__grid">
              <div class="emp-profile__field"><label>Work email</label><span>{{ employee.email }}</span></div>
              <div class="emp-profile__field"><label>Phone</label><span>{{ employee.phone }}</span></div>
              <div class="emp-profile__field"><label>Personal phone</label><span>{{ employee.personalPhone }}</span></div>
              <div class="emp-profile__field"><label>Manager</label><span>{{ employee.manager }}</span></div>
            </div>
          </section>

          <section class="emp-profile__section">
            <h2>Employment details</h2>
            <div class="emp-profile__grid">
              <div class="emp-profile__field"><label>Department</label><span>{{ employee.department }}</span></div>
              <div class="emp-profile__field"><label>Position</label><span>{{ employee.designation }}</span></div>
              <div class="emp-profile__field"><label>Branch</label><span>{{ employee.branch }}</span></div>
              <div class="emp-profile__field"><label>Joining date</label><span>{{ formatDate(employee.joiningDate) }}</span></div>
              <div class="emp-profile__field"><label>Employment type</label><span>{{ employee.salaryType || '—' }}</span></div>
              <div class="emp-profile__field"><label>Status</label><span>{{ statusLabel }}</span></div>
            </div>
          </section>

          <section v-if="activeTab === 'leave' || !activeTab" class="emp-profile__section">
            <h2>Leave summary</h2>
            <div v-if="leaveLoading" class="emp-skeleton" style="min-height:60px;" />
            <div v-else-if="!leaveBalance.length" class="emp-empty" style="padding:20px;">
              <p style="margin:0;color:#64748b;">No leave balance data</p>
            </div>
            <div v-else class="emp-profile__grid">
              <div v-for="bal in leaveBalance" :key="bal.id" class="emp-profile__field">
                <label>{{ bal.leave_type?.name || 'Leave' }}</label>
                <span>{{ bal.remaining_days ?? bal.balance ?? 0 }} days left</span>
              </div>
            </div>
          </section>

          <section v-if="activeTab === 'assets' || !activeTab" class="emp-profile__section">
            <h2>Assigned assets</h2>
            <div v-if="assetsLoading" class="emp-skeleton" style="min-height:60px;" />
            <div v-else-if="!assets.length" class="emp-empty" style="padding:20px;">
              <p style="margin:0;color:#64748b;">No assets assigned</p>
            </div>
            <ul v-else style="margin:0;padding:0;list-style:none;display:grid;gap:8px;">
              <li
                v-for="asset in assets"
                :key="asset.id"
                style="padding:10px 12px;border:1px solid #e8edf3;border-radius:10px;font-size:13px;"
              >
                <strong>{{ asset.name || asset.asset_name }}</strong>
                <span style="color:#64748b;display:block;font-size:12px;">{{ asset.asset_type?.name || asset.type }}</span>
              </li>
            </ul>
          </section>

          <section class="emp-profile__section">
            <h2>Documents</h2>
            <div v-if="documentGroups.length" class="emp-profile__grid">
              <div v-for="group in documentGroups" :key="group.type" class="emp-profile__field">
                <label>{{ formatDocType(group.type) }}</label>
                <span>{{ group.count }} file(s)</span>
              </div>
            </div>
            <p v-else style="margin:0;color:#64748b;font-size:13px;">No documents uploaded</p>
          </section>

          <section class="emp-profile__section">
            <h2>Activity timeline</h2>
            <p style="margin:0;color:#64748b;font-size:13px;">
              Joined {{ formatDate(employee.joiningDate) }}
              <span v-if="employee.raw?.updated_at"> · Last updated {{ formatDateTime(employee.raw.updated_at) }}</span>
            </p>
          </section>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;">
          <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="editEmployee">
            <iconify-icon icon="lucide:pencil" /> Edit employee
          </button>
          <button type="button" class="emp-mgmt__toolbar-btn" @click="goAttendance">
            <iconify-icon icon="lucide:clock" /> Attendance
          </button>
        </div>
      </template>
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

.emp-profile-page {
  padding: 16px;
  max-width: 960px;
  margin: 0 auto;
}
</style>
