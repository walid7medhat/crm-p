<template>
  <div class="dashboard-main-body hr-screen">
    <div class="hr-frame">
      <div class="hr-topbar">
        <div v-if="isMobileViewport" class="hr-mobile-head">
          <button type="button" class="hr-mobile-back-btn" @click="activeTab = 'Overview'">
            <iconify-icon icon="lucide:chevron-left" />
          </button>
          <span class="hr-mobile-title">HRM</span>
          <div class="hr-mobile-head-right">
            <img class="hr-mobile-avatar" src="https://i.pravatar.cc/40?img=33" alt="User avatar" />
            <button type="button" class="hr-mobile-more-btn">
              <iconify-icon icon="lucide:more-vertical" />
            </button>
          </div>
        </div>

        <div class="hr-topbar-tabs" ref="topbarTabsRef">
          <div v-for="tab in headerTabs" :key="tab" class="hr-tab-wrap">
            <button
              type="button"
              class="hr-tab"
              :class="{ active: tab === activeTab || openHeaderMenu === tab }"
              :aria-expanded="openHeaderMenu === tab"
              @click="onHeaderTabClick(tab)"
            >
              {{ tab }}
              <iconify-icon v-if="tab !== 'Overview'" icon="lucide:chevron-down" class="hr-tab-chevron" />
            </button>
            <div v-if="!isMobileViewport && tab !== 'Overview' && openHeaderMenu === tab" class="hr-tab-menu">
              <button
                v-for="item in headerTabMenus[tab]"
                :key="item"
                type="button"
                class="hr-tab-menu-item"
                @click="onHeaderMenuSelect(tab, item)"
              >
                {{ item }}
              </button>
            </div>
          </div>
        </div>
        <div v-if="isMobileViewport && openHeaderMenu && openHeaderMenu !== 'Overview'" class="hr-mobile-tab-sheet">
          <button
            v-for="item in headerTabMenus[openHeaderMenu] || []"
            :key="item"
            type="button"
            class="hr-mobile-tab-sheet-item"
            @click="onHeaderMenuSelect(openHeaderMenu, item)"
          >
            <span class="hr-mobile-tab-sheet-item-left">
              <iconify-icon :icon="menuItemIcon(item)" />
              <span>{{ item }}</span>
            </span>
            <iconify-icon icon="lucide:chevron-right" />
          </button>
        </div>

        <div class="hr-topbar-actions">
          <template v-if="isMobileViewport">
            <div class="hr-overview-search hr-overview-search--mobile">
              <input v-model="overviewSearch" type="text" placeholder="Search and advanced filter" />
              <iconify-icon icon="lucide:search" />
            </div>
          </template>
          <template v-else-if="activeTab === 'Overview'">
            <div class="hr-overview-search">
              <iconify-icon icon="lucide:plus" />
              <input v-model="overviewSearch" type="text" placeholder="Filter and search" />
              <iconify-icon icon="lucide:search" />
            </div>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
          <template v-else-if="activeTab === 'Employees'">
            <button type="button" class="hr-generate-btn">
              Add Employee
              <iconify-icon icon="lucide:plus" />
            </button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
          <template v-else>
            <button type="button" class="hr-generate-btn">
              Generate Leave
              <iconify-icon icon="lucide:plus" />
            </button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
        </div>
      </div>

      <div class="hr-content-card overview-card" v-if="activeTab === 'Overview'">
        <div class="hr-content-shell overview-shell">
          <StatsCards :stats="overviewStats" />

          <div class="overview-analytics">
            <div class="overview-department-card">
              <h6 class="overview-section-title">Department Wise Employees</h6>
              <div class="overview-bars">
                <div v-for="entry in departmentSeries" :key="entry.department" class="overview-bar-item">
                  <div class="overview-bar-track">
                    <span class="overview-bar-fill" :style="{ height: `${entry.value}%` }"></span>
                  </div>
                  <span>{{ entry.department }}</span>
                </div>
              </div>
            </div>

            <div class="overview-attendance-card">
              <div class="overview-attendance-head">
                <h6 class="overview-section-title">Employee Attendance</h6>
                <button type="button" class="overview-month-btn">
                  Last Month
                  <iconify-icon icon="lucide:chevron-down" />
                </button>
              </div>
              <div class="overview-attendance-body">
                <div class="overview-ring-shell">
                  <div class="overview-ring">
                    <span>{{ filteredOverviewEmployees.length }}</span>
                    <small>Total Employees</small>
                  </div>
                </div>
                <div class="overview-legend">
                  <p><i class="present"></i> Present <strong>{{ attendanceLegend.present }}</strong></p>
                  <p><i class="onleave"></i> On Leave <strong>{{ attendanceLegend.onLeave }}</strong></p>
                  <p><i class="holiday"></i> On Holiday <strong>{{ attendanceLegend.holiday }}</strong></p>
                  <p><i class="others"></i> Others <strong>{{ attendanceLegend.others }}</strong></p>
                </div>
              </div>
              <button type="button" class="overview-details-btn">View Full Details</button>
            </div>
          </div>

          <EmployeesTable
            :employees="filteredOverviewEmployees"
            :selected-employee-id="selectedOverviewEmployee ? selectedOverviewEmployee.id : null"
            @select-all="selectAllOverviewEmployees"
            @select-employee="selectOverviewEmployee"
          />
          <EmployeeDetails v-if="selectedOverviewEmployee" :employee="selectedOverviewEmployee" />
        </div>
      </div>

      <div class="hr-content-card" v-else-if="activeTab === 'Employees'">
        <div class="hr-content-shell overview-shell">
          <StatsCards :stats="employeeStats" />
          <div class="employee-overview-card">
            <div class="employee-overview-head">
              <h6 class="overview-section-title">Manage Employees</h6>
              <div class="employee-overview-actions">
                <button type="button" class="employee-search-btn" @click="openEmployeeFilters = true">
                  <iconify-icon icon="lucide:plus" />
                  Filter and search Employees
                  <iconify-icon icon="lucide:search" />
                </button>
                <button type="button" class="employee-export-btn">
                  Export Excel
                  <iconify-icon icon="lucide:file-down" />
                </button>
              </div>
            </div>

            <div class="employee-overview-table-wrap">
              <table class="table employee-overview-table align-middle mb-0">
                <thead>
                  <tr>
                    <th class="checkbox-col"><input type="checkbox" /></th>
                    <th class="col-id">ID</th>
                    <th class="col-person">Responsible Person</th>
                    <th class="col-designation">Designation</th>
                    <th class="col-email">Email</th>
                    <th class="col-department">Department</th>
                    <th class="employee-extra-col">Joining Date</th>
                    <th class="employee-extra-col">Visa Validity</th>
                    <th class="employee-extra-col">Nationality</th>
                    <th class="employee-extra-col">Passport Number</th>
                    <th class="employee-extra-col">Status</th>
                    <th class="col-action">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in filteredEmployeeRows"
                    :key="`emp-row-${row.id}`"
                    @click="selectOverviewEmployee(row)"
                  >
                    <td class="checkbox-col"><input type="checkbox" /></td>
                    <td class="emp-id col-id">#EMP-{{ row.id }}</td>
                    <td class="col-person">
                      <div class="employee-cell">
                        <img :src="row.avatar" :alt="row.name" class="employee-thumb" />
                        <span>{{ row.name }}</span>
                      </div>
                    </td>
                    <td class="col-designation">{{ row.designation }}</td>
                    <td class="col-email">{{ row.email }}</td>
                    <td class="col-department">{{ row.department }}</td>
                    <td class="employee-extra-col">{{ row.joiningDate }}</td>
                    <td class="employee-extra-col">{{ row.visaValidity }}</td>
                    <td class="employee-extra-col">{{ row.nationality }}</td>
                    <td class="employee-extra-col">{{ row.passportNumber }}</td>
                    <td class="employee-extra-col">
                      <span class="emp-status-pill" :class="row.statusType === 'active' ? 'active' : 'inactive'">
                        <i></i>{{ row.statusText }}
                      </span>
                    </td>
                    <td class="employee-row-action-cell col-action">
                      <button type="button" class="row-action-btn" @click.stop="toggleEmployeeRowMenu(row.id)">
                        <iconify-icon icon="lucide:more-vertical" />
                      </button>
                      <div v-if="openEmployeeRowMenuId === row.id" class="employee-row-menu">
                        <button type="button" class="employee-row-menu-item">
                          <iconify-icon icon="lucide:pencil" /> Edit Employee
                        </button>
                        <button type="button" class="employee-row-menu-item active">
                          <iconify-icon icon="lucide:eye" /> View Detail
                        </button>
                        <button type="button" class="employee-row-menu-item danger">
                          <iconify-icon icon="lucide:trash-2" /> Delete Employee
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="hr-footer">
              <span>Showing {{ filteredEmployeeRows.length }} Entries</span>
            </div>
          </div>
        </div>
      </div>

      <div class="hr-content-card" v-else-if="activeTab === 'Leave / Attendance'">
        <div class="hr-content-shell" :class="{ 'hr-content-shell--team': hrSectionTab === 'team' }">
          <div class="hr-content-head">
            <h6 class="hr-heading">Manage Attendance</h6>
            <div class="hr-head-actions">
              <div class="hr-date-filter">
                <label for="hr-attendance-date">Date</label>
                <input
                  id="hr-attendance-date"
                  v-model="dateFilter"
                  type="date"
                  class="form-control form-control-sm hr-date-input"
                  @change="onAttendanceDateChange"
                />
              </div>
              <div class="hr-search-wrap">
                <iconify-icon icon="lucide:plus" />
                <input
                  v-model="searchKeyword"
                  type="text"
                  class="hr-search-input"
                  placeholder="Filter and search Attendance"
                />
                <iconify-icon icon="lucide:search" />
              </div>
              <button type="button" class="hr-export-btn" @click="exportAttendance">
                Export Excel
                <iconify-icon icon="lucide:file-down" />
              </button>
            </div>
          </div>

          <div class="hr-inner-tabs">
            <button type="button" class="hr-inner-tab" :class="{ active: hrSectionTab === 'attendance' }" @click="hrSectionTab = 'attendance'">
              Attendance
            </button>
            <button type="button" class="hr-inner-tab" :class="{ active: hrSectionTab === 'team' }" @click="hrSectionTab = 'team'">
              TEAM VIEW
            </button>
          </div>

          <template v-if="hrSectionTab === 'attendance'">
          <div class="hr-summary-row">
            <div class="hr-stat-card">
              <span>Total Employees</span>
              <strong>{{ summary.total_employees }}</strong>
            </div>
            <div class="hr-stat-card present">
              <span>Present</span>
              <strong>{{ summary.present_today }}</strong>
            </div>
            <div class="hr-stat-card absent">
              <span>Absent</span>
              <strong>{{ summary.absent_today }}</strong>
            </div>
            <div class="hr-stat-card late">
              <span>Late</span>
              <strong>{{ summary.late_today }}</strong>
            </div>
            <div class="hr-chart-card">
              <ApexCharts type="donut" height="90" :options="chartOptions" :series="chartSeries" />
            </div>
          </div>

          <div class="hr-table-wrap">
            <table class="table hr-table align-middle mb-0">
              <thead>
                <tr>
                  <th class="checkbox-col"><input type="checkbox" /></th>
                  <th>Date</th>
                  <th>EMP ID</th>
                  <th>Employee Name</th>
                  <th>Status</th>
                  <th>Check In &amp; Check Out</th>
                  <th>Break</th>
                  <th>OT</th>
                  <th>Action</th>
                </tr>
              </thead>

              <tbody v-if="loading">
                <tr v-for="i in 10" :key="`sk-${i}`">
                  <td colspan="9"><div class="hr-skeleton"></div></td>
                </tr>
              </tbody>

              <tbody v-else-if="filteredRows.length === 0">
                <tr>
                  <td colspan="9">
                    <div class="hr-empty">
                      <div class="hr-empty-title">No attendance records found</div>
                      <div class="hr-empty-text">Try another date or filter keyword.</div>
                    </div>
                  </td>
                </tr>
              </tbody>

              <tbody v-else>
                <tr v-for="row in pagedRows" :key="`${row.employee_id}-${row.date}`">
                  <td class="checkbox-col"><input type="checkbox" /></td>
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="emp-id">#EMP{{ formatEmpId(row.employee_id) }}</td>
                  <td>
                    <div class="employee-cell">
                      <span class="avatar-circle">{{ initials(row.employee_name) }}</span>
                      <span>{{ row.employee_name }}</span>
                    </div>
                  </td>
                  <td><span class="status-badge" :class="`status-${row.status}`">{{ row.status }}</span></td>
                  <td>
                    <div class="check-flow">
                      <span class="check-time">{{ formatTime(row.check_in) }}</span>
                      <span class="check-duration-wrap">
                        <span class="dur-dot"></span>
                        <span class="dur-line"></span>
                        <span class="dur-text">{{ formatDuration(row.check_in, row.check_out) }}</span>
                        <span class="dur-line"></span>
                        <span class="dur-dot"></span>
                      </span>
                      <span class="check-time">{{ formatTime(row.check_out) }}</span>
                    </div>
                  </td>
                  <td class="text-muted break-col">{{ formatBreakDisplay(row) }}</td>
                  <td class="text-muted ot-col">{{ formatOtDisplay(row) }}</td>
                  <td>
                    <button type="button" class="row-action-btn" @click="openEdit(row)">
                      <iconify-icon icon="lucide:more-vertical" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="hr-footer">
            <span>Showing {{ startEntry }} to {{ endEntry }} of {{ filteredRows.length }} Entries</span>
            <div class="hr-pagination">
              <button type="button" class="page-btn" :disabled="page === 1" @click="page = Math.max(1, page - 1)">Previous</button>
              <template v-for="(item, idx) in paginationItems" :key="item.type === 'page' ? `p-${item.n}` : `d-${idx}`">
                <span v-if="item.type === 'dots'" class="page-dots">...</span>
                <button
                  v-else
                  type="button"
                  class="page-number"
                  :class="{ active: page === item.n }"
                  @click="page = item.n"
                >
                  {{ item.n }}
                </button>
              </template>
              <button type="button" class="page-btn" :disabled="page >= totalPages" @click="page = Math.min(totalPages, page + 1)">Next &gt;</button>
            </div>
          </div>
          </template>

          <template v-else>
            <div class="team-view-controls">
              <div class="team-control">
                <label>Search</label>
                <input v-model="teamSearch" type="text" class="form-control form-control-sm" placeholder="Search by name or ID" />
              </div>
              <div class="team-control">
                <label>Team Filter</label>
                <select v-model="teamFilter" class="form-select form-select-sm">
                  <option v-for="option in teamOptions" :key="option" :value="option">
                    {{ option === 'all' ? 'All Teams' : option }}
                  </option>
                </select>
              </div>
              <div class="team-control">
                <label>Status</label>
                <select v-model="treeStatusFilter" class="form-select form-select-sm">
                  <option value="all">All Status</option>
                  <option value="present">Present</option>
                  <option value="late">Late</option>
                  <option value="absent">Absent</option>
                </select>
              </div>
            </div>

            <template v-if="hrDebugUi">
              <div class="hr-pipeline-debug font-monospace small p-2 mb-2 bg-warning bg-opacity-25 rounded text-start">
                <div>attendance: {{ attendance.length }} | tree roots: {{ hrAttendanceTeamTree.length }} | agents: {{ mergedData.length }}</div>
              </div>
            </template>

            <div class="card border-0 shadow-sm hr-team-tree-card mt-2" v-if="!loading && !loadingAgents">
              <div class="card-body p-0 hr-team-tree-card-body">
                <div class="team-tree-container hr-team-tree-container">
                  <HrTeamTreePanel :roots="hrAttendanceTeamTree" :team-filter="teamFilter" />
                </div>
              </div>
            </div>
          </template>

          <div v-if="error" class="alert alert-danger mt-3 mb-0 py-2">{{ error }}</div>
        </div>
      </div>

      <div v-else class="hr-content-card">
        <div class="hr-content-shell hr-empty-tab"></div>
      </div>
    </div>

    <div v-if="editingRow" class="edit-overlay" @click.self="editingRow = null">
      <div class="edit-modal">
        <div class="edit-modal-head">
          <h6>Edit Attendance</h6>
          <button type="button" class="row-action-btn" @click="editingRow = null">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>
        <div class="edit-modal-body">
          <p><strong>Employee:</strong> {{ editingRow.employee_name }}</p>
          <p><strong>Date:</strong> {{ formatDate(editingRow.date) }}</p>
          <p><strong>Check In:</strong> {{ formatTime(editingRow.check_in) }}</p>
          <p><strong>Check Out:</strong> {{ formatTime(editingRow.check_out) }}</p>
        </div>
      </div>
    </div>

    <div v-if="openEmployeeFilters" class="edit-overlay" @click.self="openEmployeeFilters = false">
      <div class="employee-filter-modal">
        <button type="button" class="employee-filter-close" @click="openEmployeeFilters = false">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="employee-filter-left">
          <button
            v-for="chip in employeeFilterChips"
            :key="chip"
            type="button"
            class="employee-filter-chip"
            :class="{ active: selectedFilterChip === chip }"
            @click="selectedFilterChip = chip"
          >
            {{ chip }}
          </button>
        </div>
        <div class="employee-filter-right">
          <div class="employee-filter-field">
            <label>Employee Name</label>
            <input v-model="employeeFilters.name" type="text" placeholder="Enter Employee Name" />
          </div>
          <div class="employee-filter-field">
            <label>Department</label>
            <SearchableSelect :options="departmentOptions" v-model="employeeFilters.department" placeholder="Select Department" />
          </div>
          <div class="employee-filter-field">
            <label>Designation</label>
            <SearchableSelect :options="designationOptions" v-model="employeeFilters.designation" placeholder="Select Designation" />
          </div>
          <div class="employee-filter-field">
            <label>Joining Date</label>
            <input v-model="employeeFilters.joiningDate" type="date" />
          </div>
          <div class="employee-filter-field">
            <label>Visa Validity</label>
            <input v-model="employeeFilters.visaValidity" type="date" />
          </div>
          <div class="employee-filter-field">
            <label>Employee Status</label>
            <SearchableSelect :options="statusOptions" v-model="employeeFilters.status" placeholder="Select Status" />
          </div>
          <div class="employee-filter-actions">
            <button type="button" class="employee-filter-btn ghost" @click="resetEmployeeFilters">Reset</button>
            <button type="button" class="employee-filter-btn primary" @click="openEmployeeFilters = false">Search</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ApexCharts from 'vue3-apexcharts'
import api from '@/plugins/axios'
import HrTeamTreePanel from '@/components/hr/HrTeamTreePanel.vue'
import StatsCards from '@/components/hr/overview/StatsCards.vue'
import EmployeesTable from '@/components/hr/overview/EmployeesTable.vue'
import EmployeeDetails from '@/components/hr/overview/EmployeeDetails.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import { hrPipelineDebugEnabled, useHrDashboard } from '@/composables/useHrDashboard'

const {
  loading,
  error,
  dateFilter,
  employees,
  attendance,
  mergedData,
  filteredMergedEmployees,
  summary,
  chartSeries,
  loadAttendance,
  loadAgentData,
  loadingAgents,
  teamSearch,
  teamFilter,
  treeStatusFilter,
  groupedTeams,
  hrAttendanceTeamTree,
  teamOptions,
} = useHrDashboard()

const route = useRoute()
/** True in Vite dev, when `VITE_HR_PIPELINE_DEBUG=1` (rebuild), or `?hr_debug=1` in the URL. */
const hrDebugUi = computed(() => {
  void route.fullPath
  return hrPipelineDebugEnabled()
})

const headerTabs = ['Overview', 'Employees', 'Payroll', 'Leave / Attendance', 'Career', 'Assets']
const activeTab = ref('Overview')
const openHeaderMenu = ref(null)
const topbarTabsRef = ref(null)
const isMobileViewport = ref(false)
const overviewSearch = ref('')
const selectedOverviewEmployee = ref(null)
const searchKeyword = ref('')
const page = ref(1)
const perPage = 10
const openEmployeeFilters = ref(false)
const openEmployeeRowMenuId = ref(null)
const selectedFilterChip = ref('Marketing')
const employeeFilters = ref({
  name: '',
  department: '',
  designation: '',
  joiningDate: '',
  visaValidity: '',
  status: '',
})
const editingRow = ref(null)
const hrSectionTab = ref('attendance')
const headerTabMenus = {
  Employees: ['Manage Employees', 'Employee Assets'],
  Payroll: ['Manage Salary', 'Manage Pay Slip'],
  'Leave / Attendance': ['Leave Management', 'Attendance Management', 'Announcements'],
  Career: ['Manage Recruitments', 'Interviews', 'Career Lists'],
  Assets: ['Asset Directory', 'Asset Requests'],
}

const overviewEmployees = ref([
  { id: 340, name: 'Maria Guan', designation: 'Senior Accountant', email: 'mariagaun@gmail.com', department: 'Finance', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=47' },
  { id: 123, name: 'Ahmad Al Daghash', designation: 'UI/UX Designer', email: 'ahmadaldagash@gmail.com', department: 'Marketing', status: 'On Leave', attendanceType: 'leave', avatar: 'https://i.pravatar.cc/80?img=12' },
  { id: 112, name: 'Omar Moraden', designation: 'Backend Developer', email: 'omarmordan@gmail.com', department: 'Marketing', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=15' },
  { id: 150, name: 'Ahmad Al Adaway', designation: 'Sales Manager', email: 'ahamdaladaway@gmail.com', department: 'Sales', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=11' },
  { id: 175, name: 'Tarek Mahmoud', designation: 'Electrical Engineer', email: 'tarak.mahmmed@gmail.com', department: 'Operations', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=20' },
  { id: 182, name: 'Hadi Zain', designation: 'HR Manager', email: 'hadizainoia@gmail.com', department: 'HR', status: 'On Holiday', attendanceType: 'holiday', avatar: 'https://i.pravatar.cc/80?img=32' },
  { id: 185, name: 'Karim Haddad', designation: 'Sales Agent', email: 'karimhaddad@gmail.com', department: 'Sales', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=67' },
  { id: 186, name: 'Omar Al Kaabi', designation: 'Sales Agent', email: 'omaralkaabi@gmail.com', department: 'Sales', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=68' },
  { id: 188, name: 'Khalid Al Mazrouei', designation: 'Graphic Designer', email: 'khalidalmazrouei@gmail.com', department: 'Marketing', status: 'Others', attendanceType: 'other', avatar: 'https://i.pravatar.cc/80?img=69' },
  { id: 189, name: 'Abdullah Al Falasi', designation: 'Frontend Developer', email: 'abdullahalfalasi@gmail.com', department: 'Marketing', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=70' },
  { id: 190, name: 'Rashed Nasser', designation: 'Operations Specialist', email: 'rashednasser@gmail.com', department: 'Operations', status: 'On Leave', attendanceType: 'leave', avatar: 'https://i.pravatar.cc/80?img=21' },
  { id: 191, name: 'Noura Salem', designation: 'Payroll Executive', email: 'nourasalem@gmail.com', department: 'Finance', status: 'Present', attendanceType: 'present', avatar: 'https://i.pravatar.cc/80?img=44' },
])

const employeeFilterChips = ['Finance', 'Marketing', 'HR Department', 'Sales', 'Operations', 'Active', 'In Active']
const departmentOptions = ['Finance', 'Marketing', 'HR Department', 'Sales', 'Operations']
const designationOptions = [
  'Junior Accountant',
  'Accountant',
  'Senior Accountant',
  'Financial Analyst',
  'Cost Accountant',
  'Tax Executive',
  'Payroll Executive',
  'Treasury Analyst',
  'Accounts Manager',
  'Billing Executive',
  'Payroll Manager',
]
const statusOptions = ['Active', 'In Active']

const filteredOverviewEmployees = computed(() => {
  const keyword = overviewSearch.value.trim().toLowerCase()
  return overviewEmployees.value.filter((employee) => {
    const matchSearch =
      !keyword ||
      employee.name.toLowerCase().includes(keyword) ||
      employee.department.toLowerCase().includes(keyword) ||
      employee.designation.toLowerCase().includes(keyword)
    return matchSearch
  })
})

const employeesDirectory = computed(() =>
  overviewEmployees.value.map((employee, index) => ({
    ...employee,
    joiningDate: ['05 Feb 2023', '10 Feb 2027', '15 Mar 2025', '18 Mar 2025', '22 Apr 2025', '25 Oct 2023', '22 Nov 2027', '15 Jan 2026', '25 May 2026', '28 Aug 2027'][index % 10],
    visaValidity: ['05 Feb 2025', '10 Feb 2027', '15 Mar 2027', '18 Mar 2025', '22 Apr 2027', '25 Oct 2027', '22 Nov 2027', '15 Jan 2026', '25 May 2027', '28 Aug 2027'][index % 10],
    nationality: ['Indian', 'Egypt', 'Pakistan', 'Morocco', 'Indian', 'Indian', 'Egypt', 'Egypt', 'Indian', 'Russian'][index % 10],
    passportNumber: ['KER405321', 'EJY987454', 'PA456784', 'MS75751D', 'PA8548644', 'KABG4624', 'EQ7747V415', 'EC7778581', 'TH4584512', 'RS8405548'][index % 10],
    statusText: ['Active', 'Active', 'In Active', 'In Active', 'Active', 'Active', 'Active', 'In Active', 'In Active', 'Active'][index % 10],
    statusType: ['active', 'active', 'inactive', 'inactive', 'active', 'active', 'active', 'inactive', 'inactive', 'active'][index % 10],
  })),
)

const overviewStats = computed(() => [
  { key: 'employees', label: 'Total Employees', value: 245, icon: 'lucide:users', bgColor: '#ebf4ff', iconColor: '#2f65f6' },
  { key: 'applications', label: 'Job Applications', value: 352, icon: 'lucide:file-text', bgColor: '#f4e8ff', iconColor: '#9333ea' },
  { key: 'new-employees', label: 'New Employees', value: 56, icon: 'lucide:user-round-plus', bgColor: '#e8f8ed', iconColor: '#16a34a' },
  { key: 'attendance', label: 'Todays Attendance', value: 182, icon: 'lucide:calendar-check-2', bgColor: '#e8fbff', iconColor: '#0ea5e9' },
])

const employeeStats = computed(() => [
  { key: 'employees', label: 'Total Employees', value: 245, icon: 'lucide:users', bgColor: '#ebf4ff', iconColor: '#2f65f6' },
  { key: 'applications', label: 'New Employees', value: 25, icon: 'lucide:file-text', bgColor: '#f4e8ff', iconColor: '#9333ea' },
  { key: 'new-employees', label: 'Resigned Employees', value: 56, icon: 'lucide:user-round-plus', bgColor: '#e8f8ed', iconColor: '#16a34a' },
  { key: 'attendance', label: 'Active Employees', value: 182, icon: 'lucide:calendar-check-2', bgColor: '#e8fbff', iconColor: '#0ea5e9' },
])

const filteredEmployeeRows = computed(() => {
  return employeesDirectory.value.filter((row) => {
    const nameOk = !employeeFilters.value.name || row.name.toLowerCase().includes(employeeFilters.value.name.toLowerCase())
    const depOk = !employeeFilters.value.department || row.department === employeeFilters.value.department
    const desigOk = !employeeFilters.value.designation || row.designation === employeeFilters.value.designation
    const statusOk = !employeeFilters.value.status || row.statusText === employeeFilters.value.status
    return nameOk && depOk && desigOk && statusOk
  })
})

const departmentSeries = [
  { department: 'HR', value: 34 },
  { department: 'Sales', value: 86 },
  { department: 'Marketing', value: 52 },
  { department: 'Finance', value: 16 },
  { department: 'Operations', value: 70 },
]

const attendanceLegend = computed(() => {
  const tally = { present: 0, onLeave: 0, holiday: 0, others: 0 }
  filteredOverviewEmployees.value.forEach((employee) => {
    if (employee.attendanceType === 'present') tally.present += 1
    if (employee.attendanceType === 'leave') tally.onLeave += 1
    if (employee.attendanceType === 'holiday') tally.holiday += 1
    if (employee.attendanceType === 'other') tally.others += 1
  })
  return tally
})

function selectAllOverviewEmployees() {
  selectedOverviewEmployee.value = null
  activeTab.value = 'Employees'
  openHeaderMenu.value = null
}

function selectOverviewEmployee(employee) {
  selectedOverviewEmployee.value = employee
}
const filteredRows = computed(() => {
  const keyword = searchKeyword.value.trim().toLowerCase()
  if (!keyword) return employees.value
  return employees.value.filter((row) => {
    const name = String(row.employee_name || '').toLowerCase()
    const status = String(row.status || '').toLowerCase()
    const id = String(row.employee_id || '').toLowerCase()
    return name.includes(keyword) || status.includes(keyword) || id.includes(keyword)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage)))
const pagedRows = computed(() => {
  const start = (page.value - 1) * perPage
  return filteredRows.value.slice(start, start + perPage)
})
const startEntry = computed(() => (filteredRows.value.length ? (page.value - 1) * perPage + 1 : 0))
const endEntry = computed(() => Math.min(page.value * perPage, filteredRows.value.length))

/** Standard work minutes before we treat extra time as OT (8h). */
const STANDARD_WORK_DAY_MINUTES = 8 * 60

const paginationItems = computed(() => {
  const total = totalPages.value
  const current = page.value
  if (total <= 1) return [{ type: 'page', n: 1 }]
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => ({ type: 'page', n: i + 1 }))
  }
  const items = []
  const pushDots = () => {
    if (items.length && items[items.length - 1].type === 'dots') return
    items.push({ type: 'dots' })
  }
  items.push({ type: 'page', n: 1 })
  const left = Math.max(2, current - 1)
  const right = Math.min(total - 1, current + 1)
  if (left > 2) pushDots()
  for (let i = left; i <= right; i += 1) items.push({ type: 'page', n: i })
  if (right < total - 1) pushDots()
  items.push({ type: 'page', n: total })
  return items
})

watch(searchKeyword, () => {
  page.value = 1
})

watch(totalPages, (tp) => {
  if (page.value > tp) page.value = tp
})


const chartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  labels: ['Present', 'Absent', 'Late'],
  colors: ['#16a34a', '#dc2626', '#f59e0b'],
  legend: { show: false },
  stroke: { width: 0 },
  dataLabels: { enabled: false },
}))

function initials(name) {
  if (!name) return 'U'
  const parts = String(name).trim().split(/\s+/).slice(0, 2)
  return parts.map((p) => p.charAt(0).toUpperCase()).join('') || 'U'
}

function formatEmpId(value) {
  const num = Number(value)
  if (Number.isNaN(num) || num <= 0) return String(value || '0001')
  return String(num).padStart(4, '0')
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatTime(value) {
  if (!value) return '--'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatDuration(checkIn, checkOut) {
  if (!checkIn || !checkOut) return '--'
  const inDate = new Date(checkIn)
  const outDate = new Date(checkOut)
  if (Number.isNaN(inDate.getTime()) || Number.isNaN(outDate.getTime())) return '--'
  let diff = Math.round((outDate.getTime() - inDate.getTime()) / 60000)
  if (diff < 0) diff = 0
  const hours = Math.floor(diff / 60)
  const minutes = diff % 60
  return `${hours}h ${minutes}m`
}

function formatBreakDisplay(row) {
  if (row.break_label) return row.break_label
  const bm = row.break_minutes
  if (bm != null && Number.isFinite(Number(bm)) && Number(bm) > 0) {
    const m = Math.round(Number(bm))
    if (m >= 60 && m % 60 === 0) return `${m / 60} hr`
    if (m >= 60) {
      const h = Math.floor(m / 60)
      const r = m % 60
      return r ? `${h} hr ${r}m` : `${h} hr`
    }
    return `${m} min`
  }
  return '--'
}

function inferOvertimeMinutes(row) {
  if (row.overtime_minutes != null && Number.isFinite(Number(row.overtime_minutes))) {
    return Math.max(0, Math.round(Number(row.overtime_minutes)))
  }
  const ci = row.check_in
  const co = row.check_out
  if (row.status === 'absent') return 0
  if (!ci || !co) return null
  const inDate = new Date(ci)
  const outDate = new Date(co)
  if (Number.isNaN(inDate.getTime()) || Number.isNaN(outDate.getTime())) return null
  let worked = Math.round((outDate.getTime() - inDate.getTime()) / 60000)
  if (worked < 0) worked = 0
  return Math.max(0, worked - STANDARD_WORK_DAY_MINUTES)
}

function formatOtDisplay(row) {
  if (row.ot_label != null && String(row.ot_label).trim() !== '') return row.ot_label
  const inferred = inferOvertimeMinutes(row)
  if (inferred === null) return '--'
  if (inferred === 0) return '0'
  if (inferred < 60) {
    if (inferred > 0 && inferred % 30 === 0) return String(inferred)
    return `${inferred} M`
  }
  const h = Math.floor(inferred / 60)
  const r = inferred % 60
  if (r === 0) return `${h} hr`
  return `${h} hr ${r} M`
}

function openEdit(row) {
  editingRow.value = row
}

async function onAttendanceDateChange() {
  page.value = 1
  await loadAttendance()
}

function exportAttendance() {
  if (!filteredRows.value.length) {
    if (window.$showNotification) window.$showNotification('No attendance data to export', 'warning')
    return
  }

  const headers = ['Date', 'EMP ID', 'Employee Name', 'Status', 'Check In', 'Check Out', 'Break', 'OT']
  const rows = filteredRows.value.map((row) => [
    formatDate(row.date),
    `EMP${formatEmpId(row.employee_id)}`,
    row.employee_name || '',
    row.status || '',
    formatTime(row.check_in),
    formatTime(row.check_out),
    formatBreakDisplay(row),
    formatOtDisplay(row),
  ])

  const csv = [headers, ...rows]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `attendance-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

function onHeaderTabClick(tab) {
  const hasDropdown = !!headerTabMenus[tab]
  if (!hasDropdown) {
    activeTab.value = tab
    openHeaderMenu.value = null
    return
  }

  openHeaderMenu.value = openHeaderMenu.value === tab ? null : tab
}

function onHeaderMenuSelect(tab, item) {
  void item
  activeTab.value = tab
  openHeaderMenu.value = null
}

function menuItemIcon(item) {
  if (String(item).toLowerCase().includes('asset')) return 'lucide:briefcase-business'
  return 'lucide:clock-3'
}

function onDocumentClick(event) {
  if (!topbarTabsRef.value) return
  if (!topbarTabsRef.value.contains(event.target)) {
    openHeaderMenu.value = null
  }
  openEmployeeRowMenuId.value = null
}

function toggleEmployeeRowMenu(id) {
  openEmployeeRowMenuId.value = openEmployeeRowMenuId.value === id ? null : id
}

function resetEmployeeFilters() {
  employeeFilters.value = {
    name: '',
    department: '',
    designation: '',
    joiningDate: '',
    visaValidity: '',
    status: '',
  }
}

function syncMobileViewport() {
  isMobileViewport.value = typeof window !== 'undefined' && window.matchMedia('(max-width: 768px)').matches
  if (!isMobileViewport.value && activeTab.value !== 'Overview') {
    openHeaderMenu.value = null
  }
}

onMounted(async () => {
  console.log('BASE URL:', api.defaults.baseURL)
  const d = new Date()
  dateFilter.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  await Promise.all([loadAttendance(), loadAgentData()])
  syncMobileViewport()
  window.addEventListener('resize', syncMobileViewport)
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncMobileViewport)
  document.removeEventListener('click', onDocumentClick)
})
</script>

<style scoped>
.hr-screen { padding-top: 8px; }
.hr-frame {
  background: linear-gradient(180deg, #1136c7 0%, #0a29a2 100%);
  border-radius: 18px;
  border: 1px solid #3657d7;
  padding: 10px;
  box-shadow: 0 14px 32px rgba(16, 32, 97, 0.2);
}
.hr-topbar {
  background: #fff;
  border-radius: 14px;
  min-height: 62px;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.hr-mobile-head { display: none; }
.hr-topbar-tabs { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.hr-tab-wrap { position: relative; }
.hr-tab {
  border: none;
  background: transparent;
  padding: 9px 12px;
  font-size: 13px;
  color: #4b5563;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
.hr-tab.active {
  color: #111827;
  font-weight: 600;
  border-bottom: 2px solid #f5c543;
  border-radius: 0;
}
.hr-tab-chevron { font-size: 12px; color: #9ca3af; }
.hr-tab-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 190px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 14px 24px rgba(15, 23, 42, 0.12);
  padding: 6px;
  z-index: 40;
}
.hr-tab-menu-item {
  width: 100%;
  border: none;
  background: #fff;
  border-radius: 8px;
  text-align: left;
  padding: 8px 10px;
  color: #374151;
  font-size: 13px;
}
.hr-tab-menu-item:hover {
  background: #f3f4f6;
}
.hr-topbar-actions { display: flex; align-items: center; gap: 8px; }
.hr-overview-search {
  min-width: 220px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  color: #9ca3af;
}
.hr-overview-search input {
  border: none;
  outline: none;
  width: 100%;
  color: #6b7280;
  font-size: 12px;
}
.hr-generate-btn {
  border: none;
  background: #0d1f77;
  color: #fff;
  border-radius: 24px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hr-icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #6b7280;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.hr-content-card {
  margin-top: 12px;
  border: 1px solid rgba(189, 203, 255, 0.55);
  border-radius: 14px;
  padding: 12px;
}
.hr-content-shell {
  background: #fff;
  border: 1px solid #d6dff8;
  border-radius: 12px;
  padding: 14px;
}
.employee-overview-card {
  border: 1px solid #edf1f6;
  border-radius: 12px;
  background: #fff;
  padding: 10px;
}
.employee-overview-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.employee-overview-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.employee-search-btn,
.employee-export-btn {
  border: 1px solid #e7eaf1;
  border-radius: 999px;
  height: 36px;
  padding: 0 14px;
  background: #fff;
  color: #6b7280;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.employee-export-btn { color: #111827; }
.employee-overview-table-wrap {
  margin-top: 10px;
  border: 1px solid #edf1f8;
  border-radius: 12px;
  overflow-x: auto;
  overflow-y: visible;
  max-height: 560px;
}
.employee-overview-table { min-width: 1860px; }
.employee-overview-table th,
.employee-overview-table td {
  white-space: nowrap;
}
.employee-overview-table .col-id { width: 120px; min-width: 120px; }
.employee-overview-table .col-person { width: 250px; min-width: 250px; }
.employee-overview-table .col-designation { width: 190px; min-width: 190px; }
.employee-overview-table .col-email { width: 240px; min-width: 240px; }
.employee-overview-table .col-department { width: 160px; min-width: 160px; }
.employee-overview-table .col-action { width: 90px; min-width: 90px; text-align: center; }
.employee-overview-table .employee-extra-col { width: 180px; min-width: 180px; }
.employee-overview-table thead th.col-action {
  position: sticky;
  right: 0;
  z-index: 4;
  background: #fafbfe;
  box-shadow: -8px 0 12px -10px rgba(15, 23, 42, 0.35);
}
.employee-overview-table tbody td.col-action {
  position: sticky;
  right: 0;
  z-index: 3;
  background: #fff;
  box-shadow: -8px 0 12px -10px rgba(15, 23, 42, 0.25);
}
.employee-overview-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
}
.employee-thumb {
  width: 26px;
  height: 26px;
  border-radius: 999px;
  object-fit: cover;
}
.emp-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 11px;
  border: 1px solid #e5e7eb;
  line-height: 1;
}
.emp-status-pill i {
  width: 5px;
  height: 5px;
  border-radius: 999px;
  background: currentColor;
}
.emp-status-pill.active { color: #15803d; }
.emp-status-pill.inactive { color: #b91c1c; }
.employee-row-action-cell { position: relative; }
.employee-row-menu {
  position: absolute;
  right: 18px;
  top: 50%;
  transform: translateY(-10%);
  width: 250px;
  background: #fff;
  border: 1px solid #e7eaf1;
  border-radius: 14px;
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
  padding: 10px;
  z-index: 9999;
}
.employee-row-menu-item {
  width: 100%;
  border: none;
  background: #fff;
  border-radius: 10px;
  height: 42px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: #4b5563;
  padding: 0 10px;
}
.employee-row-menu-item.active { color: #111827; background: #f3f4f6; }
.employee-row-menu-item.danger { color: #ef4444; }
.employee-filter-modal {
  width: min(760px, 96vw);
  min-height: 500px;
  background: #fff;
  border-radius: 10px;
  display: grid;
  grid-template-columns: 170px minmax(0, 1fr);
  overflow: hidden;
  position: relative;
}
.employee-filter-close {
  position: absolute;
  top: 10px;
  right: 10px;
  border: none;
  background: transparent;
  color: #111827;
}
.employee-filter-left {
  background: #f8fafc;
  border-right: 1px solid #eef2f7;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.employee-filter-chip {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 999px;
  height: 30px;
  text-align: left;
  padding: 0 12px;
  font-size: 13px;
  color: #6b7280;
}
.employee-filter-chip.active {
  background: #0b1459;
  color: #fff;
  border-color: #0b1459;
}
.employee-filter-right {
  padding: 18px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}
.employee-filter-field label {
  font-size: 13px;
  color: #111827;
  margin-bottom: 4px;
  display: block;
}
.employee-filter-field input {
  width: 100%;
  height: 38px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  padding: 0 12px;
  font-size: 13px;
  background: #fff;
  color: #4b5563;
}
.employee-filter-field input::placeholder {
  font-size: 11px;
  color: #9ca3af;
}
.employee-filter-field :deep(.vs__search::placeholder),
.employee-filter-field :deep(.vs__selected),
.employee-filter-field :deep(.vs__dropdown-option) {
  font-size: 11px;
}
.employee-filter-field :deep(.vs__dropdown-toggle) {
  height: 38px;
  padding: 0 6px;
  min-height: 38px;
  border-color: #d9dee7;
  border-radius: 8px;
  background: #fff;
}
.employee-filter-field :deep(.vs__search),
.employee-filter-field :deep(.vs__selected) {
  color: #4b5563;
}
.employee-filter-field :deep(.vs__dropdown-menu) {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
  padding: 6px;
  margin-top: 4px;
  max-height: 180px;
}
.employee-filter-field :deep(.vs__dropdown-option) {
  border-radius: 8px;
  padding: 8px 10px;
  color: #4b5563;
}
.employee-filter-field :deep(.vs__dropdown-option--highlight) {
  background: #f3f4f6;
  color: #111827;
}
.employee-filter-field :deep(.vs__dropdown-option--selected) {
  background: #ffffff;
  color: #111827;
  font-weight: 600;
}
.employee-filter-field :deep(.vs__clear),
.employee-filter-field :deep(.vs__open-indicator) {
  color: #9ca3af;
}
.employee-filter-field :deep(.vs__actions) {
  padding-right: 4px;
}
.employee-filter-actions {
  margin-top: 6px;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.employee-filter-btn {
  border: none;
  border-radius: 999px;
  height: 34px;
  min-width: 72px;
  padding: 0 14px;
}
.employee-filter-btn.ghost {
  background: #f3f4f6;
  color: #111827;
}
.employee-filter-btn.primary {
  background: #0b1020;
  color: #fff;
}
.overview-shell {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.overview-analytics {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 8px;
}
.overview-department-card,
.overview-attendance-card {
  border: 1px solid #edf1f6;
  border-radius: 12px;
  background: #ffffff;
  padding: 9px;
}
.overview-department-card {
  min-height: 285px;
}
.overview-attendance-card {
  min-height: 285px;
  display: flex;
  flex-direction: column;
}
.overview-section-title {
  margin: 0;
  font-size: 15px !important;
  font-weight: 600;
  color: #111827;
}
.overview-bars {
  margin-top: 8px;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
  align-items: end;
  min-height: 195px;
}
.overview-bar-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.overview-bar-track {
  width: 32px;
  height: 148px;
  border-radius: 7px;
  background: #fff7e0;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: flex-end;
}
.overview-bar-fill {
  width: 100%;
  border-radius: 8px;
  background: linear-gradient(180deg, #facc15 0%, #f59e0b 100%);
}
.overview-bar-item span {
  font-size: 11px;
  color: #4b5563;
}
.overview-attendance-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.overview-month-btn {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #ffffff;
  color: #6b7280;
  padding: 6px 10px;
  font-size: 11px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.overview-attendance-body {
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.overview-ring-shell {
  width: 145px;
  height: 145px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: conic-gradient(#7c3aed 0 62%, #facc15 62% 78%, #84cc16 78% 92%, #c4b5fd 92% 100%);
  border-radius: 999px;
}
.overview-ring {
  width: 104px;
  height: 104px;
  border-radius: 999px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.overview-ring span {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}
.overview-ring small {
  margin-top: 2px;
  font-size: 10px;
  color: #6b7280;
}
.overview-legend {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  flex: 1;
}
.overview-legend p {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 6px;
}
.overview-legend p strong {
  margin-left: auto;
  color: #111827;
  font-size: 15px;
  font-weight: 600;
}
.overview-legend i {
  width: 8px;
  height: 8px;
  border-radius: 3px;
}
.overview-legend i.present { background: #7c3aed; }
.overview-legend i.onleave { background: #facc15; }
.overview-legend i.holiday { background: #84cc16; }
.overview-legend i.others { background: #c4b5fd; }
.overview-details-btn {
  margin-top: auto;
  margin-bottom: 2px;
  width: 100%;
  height: 34px;
  border: 1px solid #eceff5;
  border-radius: 999px;
  background: #f4f5f7;
  color: #1f2937;
  font-size: 14px;
  font-weight: 500;
  line-height: 1;
  padding: 0 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.18s ease, border-color 0.18s ease;
}
.overview-details-btn:hover {
  background: #eef0f4;
  border-color: #e5e7eb;
}
.hr-content-shell--team .hr-content-head {
  padding-bottom: 0;
  margin-bottom: 0;
}
.hr-content-shell--team .hr-heading {
  font-size: 15px;
  font-weight: 600;
}
.hr-content-shell--team .hr-inner-tabs {
  margin-top: 4px;
}
.hr-content-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.hr-heading {
  margin: 0;
  font-size: 22px;
  font-weight: 500;
  color: #374151;
}
.hr-head-actions { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
.hr-date-filter {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 150px;
}
.hr-date-filter label {
  margin: 0;
  font-size: 11px;
  color: #6b7280;
  font-weight: 500;
}
.hr-date-input {
  border-radius: 8px;
  border: 1px solid #eceff5;
  font-size: 12px;
}
.hr-search-wrap {
  min-width: 360px;
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  border: 1px solid #eceff5;
  border-radius: 22px;
  padding: 9px 12px;
  color: #9ca3af;
}
.hr-search-input {
  border: none;
  outline: none;
  width: 100%;
  font-size: 12px;
  color: #4b5563;
}
.hr-export-btn {
  border: 1px solid #eceff5;
  background: #fff;
  border-radius: 22px;
  padding: 9px 14px;
  color: #111827;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.hr-inner-tabs {
  margin-top: 12px;
  display: flex;
  gap: 8px;
}
.hr-inner-tab {
  border: 1px solid #e5eaf3;
  background: #fff;
  border-radius: 10px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
}
.hr-inner-tab.active {
  background: #eef4ff;
  color: #1d4ed8;
  border-color: #cfdcff;
}

.hr-summary-row {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
}
.hr-stat-card {
  background: #f8fafc;
  border: 1px solid #edf2fb;
  border-radius: 12px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.hr-stat-card span { font-size: 11px; color: #64748b; }
.hr-stat-card strong { font-size: 20px; font-weight: 700; color: #111827; }
.hr-stat-card.present strong { color: #15803d; }
.hr-stat-card.absent strong { color: #b91c1c; }
.hr-stat-card.late strong { color: #b45309; }
.hr-chart-card {
  border: 1px solid #edf2fb;
  border-radius: 12px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hr-table-wrap {
  margin-top: 12px;
  border: 1px solid #edf1f8;
  border-radius: 12px;
  overflow: hidden;
}
.hr-table thead th {
  background: #fafbfe;
  border-bottom: 1px solid #edf1f8;
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  padding: 12px 10px;
  white-space: nowrap;
}
.hr-table tbody td {
  border-bottom: 1px solid #edf1f8;
  font-size: 13px;
  color: #374151;
  padding: 12px 10px;
}
.checkbox-col { width: 38px; text-align: center; }
.emp-id { color: #9ca3af; letter-spacing: 0.02em; }
.employee-cell {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-weight: 500;
}
.avatar-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e0ecff;
  color: #2f65f6;
  font-size: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}
.status-badge {
  text-transform: capitalize;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}
.status-present { background: #dcfce7; color: #166534; }
.status-absent { background: #fee2e2; color: #991b1b; }
.status-late { background: #ffedd5; color: #9a3412; }
.row-action-btn {
  border: none;
  background: transparent;
  color: #6b7280;
}
.check-flow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  white-space: nowrap;
}
.check-time {
  color: #111827;
  font-weight: 500;
  letter-spacing: 0.01em;
}
.check-duration-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.dur-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #9ca3af;
}
.dur-line {
  width: 20px;
  height: 1px;
  background: #cfd4dc;
}
.dur-text {
  color: #d69a22;
  font-size: 13px;
  font-weight: 500;
}
.hr-table tbody tr { transition: background-color .18s ease; }
.hr-table tbody tr:hover { background: #f8fbff; }

.hr-footer {
  margin-top: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 12px;
  color: #9ca3af;
}
.hr-pagination { display: flex; align-items: center; gap: 6px; }
.page-btn, .page-number {
  border: 1px solid #eceff5;
  background: #fff;
  color: #4b5563;
  border-radius: 18px;
  padding: 7px 12px;
  font-size: 12px;
}
.page-number {
  width: 32px;
  height: 32px;
  padding: 0;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.page-number.active { background: #f2f4f8; border-color: #f2f4f8; }
.page-dots { color: #9ca3af; font-size: 12px; padding: 0 4px; }

.hr-empty { padding: 28px 12px; text-align: center; }
.hr-empty-title { color: #334155; font-size: 13px; font-weight: 600; }
.hr-empty-text { color: #94a3b8; font-size: 12px; margin-top: 4px; }

.hr-skeleton {
  height: 24px;
  border-radius: 8px;
  background: linear-gradient(90deg, #f5f7fb 25%, #e9edf5 37%, #f5f7fb 63%);
  background-size: 400px 100%;
  animation: hrShimmer 1.1s infinite linear;
}
@keyframes hrShimmer { 0% { background-position: -400px 0; } 100% { background-position: 400px 0; } }

.hr-empty-tab {
  min-height: 620px;
}
.team-view-controls {
  margin-top: 8px;
  margin-bottom: 16px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.team-control label {
  display: block;
  margin-bottom: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
}
.hr-team-tree-card {
  background: #fff;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid #dfe4f1;
  margin-top: 6px;
}
.hr-team-tree-card-body {
  display: flex;
  flex-direction: column;
  min-height: 520px;
  max-height: 74vh;
}
.hr-team-tree-container {
  position: relative;
  width: 100%;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #f4f4f5;
}

.edit-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}
.edit-modal {
  width: min(420px, 95vw);
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 20px 35px rgba(15, 23, 42, 0.15);
}
.edit-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid #edf1f8;
}
.edit-modal-head h6 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}
.edit-modal-body {
  padding: 14px;
  font-size: 13px;
  color: #374151;
}

@media (max-width: 1200px) {
  .overview-analytics {
    grid-template-columns: 1fr;
  }
  .hr-summary-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .hr-search-wrap { min-width: 260px; }
}
@media (max-width: 900px) {
  .overview-bars {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .overview-attendance-body {
    flex-direction: column;
    align-items: flex-start;
  }
  .hr-content-head { flex-direction: column; align-items: stretch; }
  .hr-head-actions { width: 100%; flex-direction: column; align-items: stretch; }
  .hr-search-wrap { min-width: 0; width: 100%; }
  .hr-footer { flex-direction: column; align-items: flex-start; }
  .team-view-controls { grid-template-columns: 1fr; }
  .employee-overview-head {
    flex-direction: column;
    align-items: flex-start;
  }
  .employee-overview-actions {
    width: 100%;
    flex-wrap: wrap;
  }
  .employee-search-btn,
  .employee-export-btn {
    width: 100%;
    justify-content: space-between;
  }
}
@media (max-width: 768px) {
  .hr-screen {
    padding: 0 !important;
  }
  .hr-frame {
    width: 100vw;
    max-width: 100vw;
    margin-left: calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
    border-radius: 0;
    border: none;
    padding: 0;
    box-shadow: none;
    background: #f1f3f6;
  }
  .hr-topbar {
    width: 100%;
    border-radius: 0 0 14px 14px;
    min-height: auto;
    padding: 10px 14px 8px;
    gap: 8px;
    flex-direction: column;
    align-items: stretch;
    border: 1px solid #eceff5;
    border-top: none;
  }
  .hr-mobile-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    order: 1;
  }
  .hr-mobile-back-btn,
  .hr-mobile-more-btn {
    border: none;
    background: transparent;
    color: #111827;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
  }
  .hr-mobile-title {
    margin-left: 2px;
    margin-right: auto;
    font-size: 24px;
    font-weight: 600;
    color: #111827;
  }
  .hr-mobile-head-right {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .hr-mobile-avatar {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    object-fit: cover;
  }
  .hr-topbar-actions {
    order: 2;
    width: 100%;
  }
  .hr-overview-search--mobile {
    width: 100%;
    min-width: 0;
    border-radius: 999px;
    border-color: #dfe3ea;
    padding: 9px 12px;
  }
  .hr-overview-search--mobile iconify-icon:first-child {
    display: none;
  }
  .hr-overview-search--mobile input {
    font-size: 14px;
  }
  .hr-topbar-tabs {
    order: 3;
    width: 100%;
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 8px;
    padding: 2px 0 0;
    border-top: 1px solid #eceff5;
  }
  .hr-tab {
    white-space: nowrap;
    padding: 9px 0;
    font-size: 14px;
    color: #8b94a2;
  }
  .hr-tab.active {
    color: #111827;
    font-weight: 700;
    border-bottom-width: 2px;
  }
  .hr-tab-chevron {
    font-size: 11px;
  }
  .hr-mobile-tab-sheet {
    order: 4;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 4px;
  }
  .hr-mobile-tab-sheet-item {
    width: 100%;
    border: 1px solid #e7eaf0;
    border-radius: 12px;
    background: #fff;
    padding: 12px 14px;
    color: #111827;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .hr-mobile-tab-sheet-item-left {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
  }
  .hr-content-card {
    width: 100%;
    border: none;
    border-radius: 0;
    padding: 10px;
    margin-top: 0;
  }
  .hr-content-shell {
    padding: 10px;
    border-radius: 12px;
    border: 1px solid #e7eaf0;
  }
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .overview-analytics {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .overview-department-card,
  .overview-attendance-card {
    min-height: auto;
    padding: 10px;
    border-radius: 10px;
  }
  .overview-bars {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    min-height: 160px;
    gap: 8px;
  }
  .overview-bar-track {
    width: 24px;
    height: 118px;
  }
  .overview-attendance-body {
    gap: 10px;
    flex-direction: row;
    align-items: flex-start;
  }
  .overview-ring-shell {
    width: 118px;
    height: 118px;
    flex-shrink: 0;
  }
  .overview-ring {
    width: 84px;
    height: 84px;
  }
  .overview-ring span {
    font-size: 20px;
  }
  .overview-legend {
    grid-template-columns: 1fr;
    gap: 6px;
  }
  .overview-details-btn {
    height: 32px;
    font-size: 12px;
  }
  .hr-content-head {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }
  .hr-heading {
    font-size: 17px;
  }
  .hr-head-actions {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .hr-date-filter,
  .hr-search-wrap,
  .hr-export-btn {
    width: 100%;
    min-width: 0;
  }
  .hr-search-wrap {
    padding: 8px 12px;
    border-radius: 12px;
  }
  .hr-export-btn {
    justify-content: center;
    border-radius: 10px;
    padding: 9px 12px;
  }
  .hr-inner-tabs {
    width: 100%;
    overflow-x: auto;
    gap: 6px;
    padding-bottom: 2px;
  }
  .hr-inner-tab {
    white-space: nowrap;
  }
  .hr-summary-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .hr-chart-card {
    min-height: 96px;
  }
  .hr-table-wrap {
    overflow-x: auto;
  }
  .hr-table {
    min-width: 880px;
  }
  .hr-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  .hr-pagination {
    width: 100%;
    overflow-x: auto;
    padding-bottom: 2px;
  }
  .team-view-controls {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .hr-team-tree-card {
    margin-top: 0;
    border-radius: 10px;
  }
  .hr-team-tree-card-body {
    min-height: 380px;
    max-height: none;
  }
  .edit-modal {
    width: min(94vw, 420px);
    border-radius: 10px;
  }
  .edit-modal-head,
  .edit-modal-body {
    padding: 10px 12px;
  }
  .employee-filter-modal {
    grid-template-columns: 1fr;
    min-height: auto;
    max-height: 90vh;
    overflow: auto;
  }
  .employee-filter-left {
    border-right: none;
    border-bottom: 1px solid #eef2f7;
    flex-direction: row;
    flex-wrap: wrap;
  }
}
</style>

