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
            <button type="button" class="hr-generate-btn" @click="showAddEmployeeModal = true">
              Add Employee
              <iconify-icon icon="lucide:plus" />
            </button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
          <template v-else-if="activeTab === 'Employee Details'">
            <button type="button" class="employee-detail-action-chip">Activity</button>
            <button type="button" class="employee-detail-action-chip">Deactivate</button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:pencil" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:trash-2" /></button>
          </template>
          <template v-else-if="activeTab === 'Assets'">
            <button type="button" class="hr-generate-btn" @click="showAssetCreateModal = true">
              Add New Asset
              <iconify-icon icon="lucide:plus" />
            </button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
          <template v-else>
            <button type="button" class="hr-generate-btn" @click="showApplyLeaveModal = true">
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
                      <button type="button" class="row-action-btn" @click.stop="toggleEmployeeRowMenu(row.id, $event)">
                        <iconify-icon icon="lucide:more-vertical" />
                      </button>
                      <teleport to="body">
                        <div v-if="openEmployeeRowMenuId === row.id" class="employee-row-menu" :style="employeeRowMenuStyle" @click.stop>
                          <button type="button" class="employee-row-menu-item" @click.stop="openEditEmployee(row)">
                            <iconify-icon icon="lucide:pencil" /> Edit Employee
                          </button>
                          <button type="button" class="employee-row-menu-item active" @click.stop="openEmployeeDetails(row)">
                            <iconify-icon icon="lucide:eye" /> View Detail
                          </button>
                          <button type="button" class="employee-row-menu-item danger" @click.stop="confirmDeleteEmployee(row)">
                            <iconify-icon icon="lucide:trash-2" /> Delete Employee
                          </button>
                        </div>
                      </teleport>
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

      <div class="hr-content-card" v-else-if="activeTab === 'Employee Details'">
        <div class="hr-content-shell employee-detail-page" v-if="selectedEmployeeDetail">
          <div class="employee-detail-breadcrumb">Employee <iconify-icon icon="lucide:chevron-right" /> Manage Employee <iconify-icon icon="lucide:chevron-right" /> {{ selectedEmployeeDetail.name }}</div>
          <h6 class="overview-section-title mb-2">Employee Details</h6>
          <div class="employee-detail-layout">
            <aside class="employee-detail-side">
              <div class="employee-detail-user-head">
                <img :src="selectedEmployeeDetail.avatar" :alt="selectedEmployeeDetail.name" />
                <div>
                  <strong>{{ selectedEmployeeDetail.name }}</strong>
                  <p>ID : #EMP-{{ selectedEmployeeDetail.id }}</p>
                </div>
                <button type="button" class="mini-edit-btn" @click="openSectionEdit('profile')"><iconify-icon icon="lucide:pencil" /></button>
              </div>
              <div class="employee-detail-side-list">
                <p><span>Gmail</span><strong>{{ selectedEmployeeDetail.email }}</strong></p>
                <p><span>Phone</span><strong>{{ selectedEmployeeDetail.phone }}</strong></p>
                <p><span>Date Of Birth</span><strong>{{ selectedEmployeeDetail.dob }}</strong></p>
                <p><span>Address</span><strong>{{ selectedEmployeeDetail.address }}</strong></p>
                <p><span>Nationality</span><strong>{{ selectedEmployeeDetail.nationality }}</strong></p>
                <p><span>Salary Type</span><strong>{{ selectedEmployeeDetail.salary_type }}</strong></p>
                <p><span>Salary</span><strong>{{ selectedEmployeeDetail.salary }} AED</strong></p>
              </div>
            </aside>
            <section ref="employeeDetailMainRef" class="employee-detail-main">
              <div class="employee-detail-tabs">
                <button type="button" :class="{active: employeeDetailTab === 'company'}" @click="scrollEmployeeDetailSection('company')">Company Details</button>
                <button type="button" :class="{active: employeeDetailTab === 'documents'}" @click="scrollEmployeeDetailSection('documents')">Document Details</button>
                <button type="button" :class="{active: employeeDetailTab === 'bank'}" @click="scrollEmployeeDetailSection('bank')">Bank Account Details</button>
                <button type="button" :class="{active: employeeDetailTab === 'assets'}" @click="scrollEmployeeDetailSection('assets')">Asset Details</button>
                <button type="button" :class="{active: employeeDetailTab === 'insurance'}" @click="scrollEmployeeDetailSection('insurance')">Insurance Details</button>
              </div>

              <div ref="employeeCompanySectionRef" class="employee-detail-section">
                <div class="employee-detail-section-head">
                  <h6>Company Details</h6>
                  <button type="button" class="mini-edit-btn" @click="openSectionEdit('company')"><iconify-icon icon="lucide:pencil" /></button>
                </div>
                <div class="employee-mini-grid">
                  <p><span>Branch</span><strong>{{ selectedEmployeeDetail.branch }}</strong></p>
                  <p><span>Designation</span><strong>{{ selectedEmployeeDetail.designation }}</strong></p>
                  <p><span>Department</span><strong>{{ selectedEmployeeDetail.department }}</strong></p>
                  <p><span>Supervisor</span><strong>{{ selectedEmployeeDetail.supervisor }}</strong></p>
                  <p><span>Joining Date</span><strong>{{ selectedEmployeeDetail.joiningDate }}</strong></p>
                  <p><span>Visa Validity</span><strong>{{ selectedEmployeeDetail.visaValidity }}</strong></p>
                </div>
              </div>

              <div ref="employeeDocumentsSectionRef" class="employee-detail-section">
                <div class="employee-detail-section-head">
                  <h6>Document Details</h6>
                  <button type="button" class="mini-edit-btn" @click="openSectionEdit('documents')"><iconify-icon icon="lucide:pencil" /></button>
                </div>
                <div class="employee-mini-grid">
                  <p><span>Emirates ID</span><strong>{{ selectedEmployeeDetail.emiratesId }}</strong></p>
                  <p><span>Labor Card</span><strong>{{ selectedEmployeeDetail.laborCard }}</strong></p>
                  <p><span>Passport</span><strong>{{ selectedEmployeeDetail.passportNumber }}</strong></p>
                  <p><span>Visa</span><strong>{{ selectedEmployeeDetail.visaNumber }}</strong></p>
                  <p><span>Attested Certificate</span><strong>{{ selectedEmployeeDetail.attestedCertificate }}</strong></p>
                </div>
              </div>

              <div ref="employeeBankSectionRef" class="employee-detail-section">
                <div class="employee-detail-section-head">
                  <h6>Bank Account Details</h6>
                  <button type="button" class="mini-edit-btn" @click="openSectionEdit('bank')"><iconify-icon icon="lucide:pencil" /></button>
                </div>
                <div class="employee-mini-grid">
                  <p><span>Account Holder Name</span><strong>{{ selectedEmployeeDetail.account_holder_name }}</strong></p>
                  <p><span>Bank Name</span><strong>{{ selectedEmployeeDetail.bank_name }}</strong></p>
                  <p><span>Bank Branch</span><strong>{{ selectedEmployeeDetail.branch_location }}</strong></p>
                  <p><span>Account Number</span><strong>{{ selectedEmployeeDetail.account_number }}</strong></p>
                  <p><span>IBAN Number</span><strong>{{ selectedEmployeeDetail.iban_number }}</strong></p>
                  <p><span>SWIFT Code</span><strong>{{ selectedEmployeeDetail.swift_code }}</strong></p>
                </div>
              </div>

              <div ref="employeeAssetsSectionRef" class="employee-detail-section">
                <div class="employee-detail-section-head"><h6>Asset Details</h6></div>
                <div class="employee-mini-grid">
                  <p><span>HP Laptop</span><strong>ASSET ID : AST-001</strong></p>
                  <p><span>Laptop Charger</span><strong>ASSET ID : AST-002</strong></p>
                  <p><span>Company SIM</span><strong>ASSET ID : AST-004</strong></p>
                  <p><span>Company Name Badge</span><strong>ASSET ID : AST-006</strong></p>
                </div>
              </div>

              <div ref="employeeInsuranceSectionRef" class="employee-detail-section">
                <div class="employee-detail-section-head">
                  <h6>Insurance Details</h6>
                  <button type="button" class="mini-edit-btn" @click="openSectionEdit('insurance')"><iconify-icon icon="lucide:pencil" /></button>
                </div>
                <div class="employee-mini-grid">
                  <p><span>Insurance Provider</span><strong>{{ selectedEmployeeDetail.insurance_provider }}</strong></p>
                  <p><span>Policy Number</span><strong>{{ selectedEmployeeDetail.policy_number }}</strong></p>
                  <p><span>Insurance Type</span><strong>{{ selectedEmployeeDetail.policy_type }}</strong></p>
                  <p><span>Start Date</span><strong>{{ selectedEmployeeDetail.insurance_start_date }}</strong></p>
                  <p><span>Expiry Date</span><strong>{{ selectedEmployeeDetail.insurance_expiry_date }}</strong></p>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>

      <div class="hr-content-card" v-else-if="activeTab === 'Leave / Attendance'">
        <div class="hr-content-shell" :class="{ 'hr-content-shell--team': hrSectionTab === 'team' }">
          <template v-if="leaveSectionMode === 'leave'">
            <div class="employee-overview-card leave-overview-card">
              <div class="employee-overview-head">
                <h6 class="overview-section-title">Manage Leaves</h6>
                <div class="employee-overview-actions">
                  <button type="button" class="employee-search-btn assets-search-wrap" @click="showLeaveSearchModal = true">
                    <iconify-icon icon="lucide:plus" />
                    <span>{{ leaveSearchSummary }}</span>
                    <iconify-icon icon="lucide:search" />
                  </button>
                  <button type="button" class="employee-export-btn" @click="exportLeaves">
                    Export Excel
                    <iconify-icon icon="lucide:file-down" />
                  </button>
                </div>
              </div>

              <div class="leave-table-wrap">
                <table class="table leave-table align-middle mb-0">
                  <thead>
                    <tr>
                      <th class="checkbox-col"><input type="checkbox" /></th>
                      <th class="col-leave-id">EMP ID</th>
                      <th class="col-leave-name">Employee Name</th>
                      <th class="col-leave-type">Leave Type</th>
                      <th class="col-leave-date">Start Date</th>
                      <th class="col-leave-date">End Date</th>
                      <th class="col-leave-days">Days</th>
                      <th class="col-leave-reason">Reason</th>
                      <th class="col-action sticky-action-col">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="leave in pagedLeaveRows" :key="`leave-row-${leave.id}`">
                      <td class="checkbox-col"><input type="checkbox" /></td>
                      <td class="emp-id col-leave-id">{{ leave.empId }}</td>
                      <td class="col-leave-name">
                        <div class="employee-cell">
                          <img :src="leave.avatar" :alt="leave.employeeName" class="employee-thumb" />
                          <span>{{ leave.employeeName }}</span>
                        </div>
                      </td>
                      <td class="col-leave-type">{{ leave.leaveType }}</td>
                      <td class="col-leave-date">{{ leave.startDate }}</td>
                      <td class="col-leave-date">{{ leave.endDate }}</td>
                      <td class="col-leave-days">{{ leave.days }}</td>
                      <td class="col-leave-reason">{{ leave.reason }}</td>
                      <td class="col-action sticky-action-col">
                        <button type="button" class="row-action-btn" @click.stop="toggleLeaveRowMenu(leave.id, $event)">
                          <iconify-icon icon="lucide:more-vertical" />
                        </button>
                        <teleport to="body">
                          <div
                            v-if="openLeaveRowMenuId === leave.id"
                            class="leave-row-menu"
                            :style="leaveRowMenuStyle"
                            @click.stop
                          >
                            <button type="button" class="leave-row-menu-item" @click.stop="openLeaveEdit(leave)">
                              <iconify-icon icon="lucide:pencil" /> Edit Leave
                            </button>
                            <button type="button" class="leave-row-menu-item active" @click.stop="openLeaveDetails(leave)">
                              <iconify-icon icon="lucide:eye" /> View Details
                            </button>
                            <button type="button" class="leave-row-menu-item danger" @click.stop="confirmDeleteLeave(leave)">
                              <iconify-icon icon="lucide:trash-2" /> Delete Leave
                            </button>
                            <button type="button" class="leave-row-menu-item approve" @click.stop="openApproveLeaveModal(leave)">
                              <iconify-icon icon="lucide:badge-check" /> Approve Leave
                            </button>
                            <button type="button" class="leave-row-menu-item reject" @click.stop="openRejectLeaveModal(leave)">
                              <iconify-icon icon="lucide:ban" /> Reject Leave
                            </button>
                          </div>
                        </teleport>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="hr-footer">
                <span>Showing {{ leavesStartEntry }} to {{ leavesEndEntry }} of {{ filteredLeaveRows.length }} Entries</span>
                <div class="hr-pagination">
                  <button type="button" class="page-btn" :disabled="leavePage === 1" @click="leavePage = Math.max(1, leavePage - 1)">Previous</button>
                  <template v-for="(item, idx) in leavePaginationItems" :key="item.type === 'page' ? `lp-${item.n}` : `ld-${idx}`">
                    <span v-if="item.type === 'dots'" class="page-dots">...</span>
                    <button
                      v-else
                      type="button"
                      class="page-number"
                      :class="{ active: leavePage === item.n }"
                      @click="leavePage = item.n"
                    >
                      {{ item.n }}
                    </button>
                  </template>
                  <button type="button" class="page-btn" :disabled="leavePage >= leaveTotalPages" @click="leavePage = Math.min(leaveTotalPages, leavePage + 1)">Next</button>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="leaveSectionMode === 'attendance'">
          <div class="hr-content-head">
            <h6 class="hr-heading">Manage Attendance</h6>
            <div class="hr-head-actions">
              <div class="hr-date-filter">
                <label for="hr-attendance-date">Date</label>
                <input
                  id="hr-attendance-date"
                  :value="formatDateDisplay(dateFilter)"
                  type="text"
                  placeholder="dd/mm/yyyy"
                  class="form-control form-control-sm hr-date-input"
                  readonly
                  @click="openDatePicker('dateFilter')"
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
            <div class="hr-empty-tab leave-announcement-card">
              <h6 class="overview-section-title">Announcements</h6>
              <p>Announcements module will appear here.</p>
            </div>
          </template>
          </template>

          <div v-if="error" class="alert alert-danger mt-3 mb-0 py-2">{{ error }}</div>
        </div>
      </div>

      <div class="hr-content-card" v-else-if="activeTab === 'Assets'">
        <div class="hr-content-shell overview-shell">
          <div class="employee-overview-card assets-overview-card">
            <div class="employee-overview-head">
              <h6 class="overview-section-title">Manage Assets</h6>
              <div class="employee-overview-actions">
                <button type="button" class="employee-search-btn assets-search-wrap" @click="showAssetSearchModal = true">
                  <iconify-icon icon="lucide:plus" />
                  <span>{{ assetSearchSummary }}</span>
                  <iconify-icon icon="lucide:search" />
                </button>
                <button type="button" class="employee-export-btn" @click="exportAssets">
                  Export Excel
                  <iconify-icon icon="lucide:file-down" />
                </button>
              </div>
            </div>

            <div class="assets-table-wrap">
              <table class="table assets-table align-middle mb-0">
                <thead>
                  <tr>
                    <th class="checkbox-col"><input type="checkbox" /></th>
                    <th class="col-asset-id">Asset ID</th>
                    <th class="col-asset-type">Type</th>
                    <th class="col-asset-name">Asset Name</th>
                    <th class="col-asset-user">Users</th>
                    <th class="col-handover">Date Of Handover</th>
                    <th class="asset-extra-col">Brand</th>
                    <th class="asset-extra-col">Category</th>
                    <th class="asset-extra-col">Handover To</th>
                    <th class="asset-extra-col">Serial Number</th>
                    <th class="asset-extra-col">Status</th>
                    <th class="col-action sticky-action-col">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="asset in pagedAssetsRows" :key="`asset-row-${asset.id}`">
                    <td class="checkbox-col"><input type="checkbox" /></td>
                    <td class="emp-id col-asset-id">{{ asset.assetId }}</td>
                    <td class="col-asset-type">{{ asset.type }}</td>
                    <td class="col-asset-name">{{ asset.assetName }}</td>
                    <td class="col-asset-user">
                      <div class="employee-cell">
                        <img :src="asset.userAvatar" :alt="asset.userName" class="employee-thumb" />
                        <span>
                          {{ asset.userName }}
                          <small>ID :#{{ asset.userRef }}</small>
                        </span>
                      </div>
                    </td>
                    <td class="col-handover">{{ asset.handoverDate }}</td>
                    <td class="asset-extra-col">{{ asset.brand }}</td>
                    <td class="asset-extra-col">{{ asset.category }}</td>
                    <td class="asset-extra-col">{{ asset.handoverTo }}</td>
                    <td class="asset-extra-col">{{ asset.serial }}</td>
                    <td class="asset-extra-col">{{ asset.status }}</td>
                    <td class="col-action sticky-action-col">
                      <button type="button" class="row-action-btn" @click.stop="toggleAssetRowMenu(asset.id, $event)">
                        <iconify-icon icon="lucide:more-vertical" />
                      </button>
                      <teleport to="body">
                        <div
                          v-if="openAssetRowMenuId === asset.id"
                          class="asset-row-menu"
                          :style="assetRowMenuStyle"
                          @click.stop
                        >
                          <button type="button" class="asset-row-menu-item" @click.stop="openEditAsset(asset)">
                            <iconify-icon icon="lucide:pencil" /> Edit Asset
                          </button>
                          <button type="button" class="asset-row-menu-item active" @click.stop="openAssignAssetUser(asset)">
                            <iconify-icon icon="lucide:user-round-plus" /> Assign User
                          </button>
                          <button type="button" class="asset-row-menu-item danger" @click.stop="confirmDeleteAsset(asset)">
                            <iconify-icon icon="lucide:trash-2" /> Delete Asset
                          </button>
                        </div>
                      </teleport>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="hr-footer">
              <span>Showing {{ assetsStartEntry }} to {{ assetsEndEntry }} of {{ filteredAssetsRows.length }} Entries</span>
              <div class="hr-pagination">
                <button type="button" class="page-btn" :disabled="assetsPage === 1" @click="assetsPage = Math.max(1, assetsPage - 1)">Previous</button>
                <template v-for="(item, idx) in assetsPaginationItems" :key="item.type === 'page' ? `ap-${item.n}` : `ad-${idx}`">
                  <span v-if="item.type === 'dots'" class="page-dots">...</span>
                  <button
                    v-else
                    type="button"
                    class="page-number"
                    :class="{ active: assetsPage === item.n }"
                    @click="assetsPage = item.n"
                  >
                    {{ item.n }}
                  </button>
                </template>
                <button type="button" class="page-btn" :disabled="assetsPage >= assetsTotalPages" @click="assetsPage = Math.min(assetsTotalPages, assetsPage + 1)">Next</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="hr-content-card">
        <div class="hr-content-shell hr-empty-tab"></div>
      </div>
    </div>

    <div v-if="showLeaveSearchModal" class="edit-overlay" @click.self="showLeaveSearchModal = false">
      <div class="employee-filter-modal leave-search-modal">
        <button type="button" class="employee-filter-close" @click="showLeaveSearchModal = false">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="asset-search-left">
          <button
            v-for="chip in leaveSearchChips"
            :key="chip"
            type="button"
            class="asset-search-chip"
            :class="{ active: selectedLeaveSearchChip === chip }"
            @click="selectedLeaveSearchChip = chip"
          >
            {{ chip }}
          </button>
        </div>
        <div class="asset-search-right">
          <div class="asset-search-section">
            <h6>Select Employee</h6>
            <div class="add-grid-one">
              <div class="add-field">
                <SearchableSelect v-model="leaveSearchFilters.employee" :options="leaveEmployeeOptions" placeholder="Search Employee or id" />
              </div>
            </div>
          </div>
          <div class="asset-search-section">
            <h6>Leave Type</h6>
            <div class="add-grid-one">
              <div class="add-field">
                <SearchableSelect v-model="leaveSearchFilters.leaveType" :options="leaveTypeFilterOptions" placeholder="Select Type" />
              </div>
            </div>
          </div>
          <div class="asset-search-section">
            <h6>Applied Date</h6>
            <div class="add-grid-one">
              <div class="add-field">
                <input :value="formatDateDisplay(leaveSearchFilters.appliedDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('leaveSearchFilters.appliedDate')" />
              </div>
            </div>
          </div>
          <div class="asset-search-section">
            <h6>Status</h6>
            <div class="add-grid-one">
              <div class="add-field">
                <SearchableSelect v-model="leaveSearchFilters.status" :options="leaveStatusOptions" placeholder="Select Status" />
              </div>
            </div>
          </div>
          <div class="employee-filter-actions mt-2">
            <button type="button" class="employee-filter-btn ghost" @click="resetLeaveSearchFilters">Reset</button>
            <button type="button" class="employee-filter-btn primary" @click="applyLeaveSearchFilters">Search</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showLeaveDetailModal && selectedLeaveRow" class="edit-overlay" @click.self="closeLeaveDetails">
      <div class="leave-detail-modal">
        <button type="button" class="employee-filter-close" @click="closeLeaveDetails"><iconify-icon icon="lucide:x" /></button>
        <h6>Leave Details</h6>
        <div class="leave-detail-grid">
          <p><span>Employee</span><strong>{{ selectedLeaveRow.employeeName }}</strong></p>
          <p><span>Designation</span><strong>{{ selectedLeaveRow.designation }}</strong></p>
          <p><span>Start Date</span><strong>{{ selectedLeaveRow.startDate }}</strong></p>
          <p><span>End Date</span><strong>{{ selectedLeaveRow.endDate }}</strong></p>
          <p><span>Leave Days</span><strong>{{ selectedLeaveRow.days }} Day(s)</strong></p>
          <p><span>Status</span><strong :class="`leave-txt-${selectedLeaveRow.status.toLowerCase()}`">{{ selectedLeaveRow.status }}</strong></p>
          <p><span>Leave Type</span><strong>{{ selectedLeaveRow.leaveType }}</strong></p>
          <p><span>Applied On</span><strong>{{ selectedLeaveRow.appliedDate }}</strong></p>
        </div>
        <div class="leave-detail-reason">
          <span>Leave Reason</span>
          <p>{{ selectedLeaveRow.reason }}</p>
        </div>
        <div class="leave-detail-actions">
          <button type="button" class="leave-approve-btn" @click="openApproveLeaveModal(selectedLeaveRow)">Approve Leave</button>
          <button type="button" class="leave-reject-btn" @click="openRejectLeaveModal(selectedLeaveRow)">Reject Leave</button>
        </div>
      </div>
    </div>

    <div v-if="showLeaveApproveModal && selectedLeaveRow" class="edit-overlay" @click.self="showLeaveApproveModal = false">
      <div class="leave-confirm-modal">
        <button type="button" class="employee-filter-close" @click="showLeaveApproveModal = false"><iconify-icon icon="lucide:x" /></button>
        <div class="confirm-icon success"><iconify-icon icon="lucide:check" /></div>
        <h6>Leave Approval Confirmation !</h6>
        <p>Are you sure you want to approve this leave request?<br/>This action will update the leave status as approved.</p>
        <button type="button" class="leave-confirm-btn" @click="confirmLeaveApproval">Confirm</button>
      </div>
    </div>

    <div v-if="showLeaveRejectModal && selectedLeaveRow" class="edit-overlay" @click.self="showLeaveRejectModal = false">
      <div class="leave-confirm-modal">
        <button type="button" class="employee-filter-close" @click="showLeaveRejectModal = false"><iconify-icon icon="lucide:x" /></button>
        <div class="confirm-icon danger"><iconify-icon icon="lucide:ban" /></div>
        <h6>Leave Rejection Confirmation !</h6>
        <p>Please confirm that you want to reject this leave request.<br/>The employee will be notified after rejection.</p>
        <button type="button" class="leave-confirm-btn" @click="confirmLeaveRejection">Confirm</button>
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

    <div v-if="showAssetEditModal" class="edit-overlay" @click.self="closeAssetEditModal">
      <div class="employee-filter-modal section-edit-modal asset-edit-modal">
        <button type="button" class="employee-filter-close" @click="closeAssetEditModal">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="employee-filter-right w-100">
          <h6 class="mb-2">Edit Asset</h6>
          <div class="add-grid-two">
            <div class="add-field"><label>Asset ID</label><input v-model="assetEditForm.assetId" type="text" /></div>
            <div class="add-field"><label>Type</label><input v-model="assetEditForm.type" type="text" /></div>
            <div class="add-field"><label>Asset Name</label><input v-model="assetEditForm.assetName" type="text" /></div>
            <div class="add-field"><label>User Name</label><input v-model="assetEditForm.userName" type="text" /></div>
            <div class="add-field"><label>User Ref</label><input v-model="assetEditForm.userRef" type="text" /></div>
            <div class="add-field"><label>Date Of Handover</label><input v-model="assetEditForm.handoverDate" type="text" /></div>
            <div class="add-field"><label>Brand</label><input v-model="assetEditForm.brand" type="text" /></div>
            <div class="add-field"><label>Category</label><input v-model="assetEditForm.category" type="text" /></div>
            <div class="add-field"><label>Handover To</label><input v-model="assetEditForm.handoverTo" type="text" /></div>
            <div class="add-field"><label>Serial Number</label><input v-model="assetEditForm.serial" type="text" /></div>
            <div class="add-field"><label>Status</label><input v-model="assetEditForm.status" type="text" /></div>
          </div>
          <div class="employee-filter-actions mt-2">
            <button type="button" class="employee-filter-btn ghost" @click="closeAssetEditModal">Cancel</button>
            <button type="button" class="employee-filter-btn primary" @click="saveAssetEdit">Save</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showAssetSearchModal" class="edit-overlay" @click.self="showAssetSearchModal = false">
      <div class="employee-filter-modal asset-search-modal">
        <button type="button" class="employee-filter-close" @click="showAssetSearchModal = false">
          <iconify-icon icon="lucide:x" />
        </button>

        <div class="asset-search-left">
          <button
            v-for="chip in assetSearchChips"
            :key="chip"
            type="button"
            class="asset-search-chip"
            :class="{ active: selectedAssetSearchChip === chip }"
            @click="selectedAssetSearchChip = chip"
          >
            {{ chip }}
          </button>
        </div>

        <div class="asset-search-right">
          <div class="asset-search-section">
            <h6>Asset Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Asset Type</label><SearchableSelect v-model="assetSearchFilters.assetType" :options="assetTypeOptions" placeholder="Select Asset Type" /></div>
              <div class="add-field"><label>Asset Name</label><input v-model="assetSearchFilters.assetName" type="text" placeholder="Search Asset Name" /></div>
              <div class="add-field"><label>Created On</label><input :value="formatDateDisplay(assetSearchFilters.createdOn)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('assetSearchFilters.createdOn')" /></div>
              <div class="add-field"><label>Serial Number</label><input v-model="assetSearchFilters.serialNumber" type="text" placeholder="Enter Number" /></div>
            </div>
          </div>

          <div class="asset-search-section">
            <h6>User Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Asset User</label><SearchableSelect v-model="assetSearchFilters.assetUser" :options="assetUserOptions" placeholder="Select Person" /></div>
              <div class="add-field"><label>Department</label><SearchableSelect v-model="assetSearchFilters.department" :options="departmentOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Branch Location</label><SearchableSelect v-model="assetSearchFilters.branchLocation" :options="branchOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Status</label><SearchableSelect v-model="assetSearchFilters.status" :options="assetStatusOptions" placeholder="Not Selected" /></div>
            </div>
          </div>

          <div class="asset-search-section">
            <h6>Purchase Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Purchase Date</label><input :value="formatDateDisplay(assetSearchFilters.purchaseDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('assetSearchFilters.purchaseDate')" /></div>
              <div class="add-field"><label>Supplier Name</label><input v-model="assetSearchFilters.supplierName" type="text" placeholder="Enter Supplier Name" /></div>
              <div class="add-field"><label>Condition</label><SearchableSelect v-model="assetSearchFilters.condition" :options="assetConditionOptions" placeholder="Not Selected" /></div>
            </div>
          </div>

          <div class="employee-filter-actions mt-2">
            <button type="button" class="employee-filter-btn ghost" @click="resetAssetSearchFilters">Reset</button>
            <button type="button" class="employee-filter-btn primary" @click="applyAssetSearchFilters">Search</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showAssetCreateModal" class="edit-overlay add-employee-overlay" @click.self="closeAssetCreateModal">
      <div class="add-employee-modal asset-create-modal">
        <div class="add-employee-head">
          <h6>Create New Asset</h6>
          <button type="button" class="add-employee-close" @click="closeAssetCreateModal">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>

        <div class="add-employee-body">
          <section class="add-employee-section">
            <h6>Asset Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Asset Type *</label><SearchableSelect v-model="assetCreateForm.assetType" :options="assetTypeOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Asset Name *</label><input v-model="assetCreateForm.assetName" type="text" placeholder="Enter Asset Name" /></div>
              <div class="add-field"><label>Serial Number</label><input v-model="assetCreateForm.serialNumber" type="text" placeholder="Enter Serial Number" /></div>
              <div class="add-field"><label>Model Number</label><input v-model="assetCreateForm.modelNumber" type="text" placeholder="Enter Model Number" /></div>
              <div class="add-field"><label>RDP Number</label><input v-model="assetCreateForm.rdpNumber" type="text" placeholder="Enter reference number" /></div>
              <div class="add-field"><label>Remarks</label><input v-model="assetCreateForm.remarks" type="text" placeholder="Enter Remarks" /></div>
              <div class="add-field add-field-full"><label>Description</label><textarea v-model="assetCreateForm.description" placeholder="Enter Description"></textarea></div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>User Details</h6>
            <div class="add-grid-two">
              <div ref="assetUserPickerRef" class="add-field asset-user-picker-field">
                <label>Asset User</label>
                <button type="button" class="asset-user-trigger" @click.stop="toggleAssetUserPicker">
                  <span>{{ selectedAssetResponsiblePerson?.name || 'Not Selected' }}</span>
                  <iconify-icon icon="lucide:chevron-down" />
                </button>
                <div v-if="showAssetUserPicker" class="asset-user-dropdown" @click.stop>
                  <div class="asset-user-dropdown-head">
                    <span>Person</span>
                    <button type="button" class="asset-user-close-btn" @click="closeAssetUserPicker">
                      <iconify-icon icon="lucide:x" />
                    </button>
                  </div>
                  <div class="search-input-wrapper mb-2">
                    <input v-model="assetUserSearchQuery" type="text" class="asset-user-search-input" placeholder="Search Responsible Person" />
                    <iconify-icon icon="lucide:search" class="search-icon" />
                  </div>
                  <div class="asset-user-list-scroll">
                    <button
                      v-for="person in filteredAssetResponsiblePersons"
                      :key="person.id"
                      type="button"
                      class="asset-user-item"
                      :class="{ selected: Number(assetCreateForm.assetUser) === Number(person.id) }"
                      @click="selectAssetResponsiblePerson(person)"
                    >
                      <img :src="person.avatar || defaultPersonAvatar" class="asset-user-avatar" alt="user avatar" />
                      <div class="asset-user-info">
                        <div class="asset-user-head">
                          <span class="asset-user-name">{{ person.name }}</span>
                          <span v-if="person.role_name" class="user-position-badge">{{ person.role_name }}</span>
                        </div>
                        <div class="user-item-meta-line">
                          <span class="meta-value">{{ person.parent_name || person.team_lead_name || '—' }}</span>
                          <span class="meta-divider">|</span>
                          <span class="meta-value">{{ person.branch_name || person.office_name || '—' }}</span>
                        </div>
                      </div>
                    </button>
                    <div v-if="!filteredAssetResponsiblePersons.length" class="text-center text-muted py-2">No persons found</div>
                  </div>
                </div>
              </div>
              <div class="add-field"><label>Date Of Handover</label><input :value="formatDateDisplay(assetCreateForm.handoverDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetCreateForm.handoverDate')" /></div>
              <div class="add-field"><label>Branch Location</label><SearchableSelect v-model="assetCreateForm.branchLocation" :options="branchOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Department</label><SearchableSelect v-model="assetCreateForm.department" :options="departmentOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Status *</label><SearchableSelect v-model="assetCreateForm.status" :options="assetStatusOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Date Of Return</label><input :value="formatDateDisplay(assetCreateForm.returnDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetCreateForm.returnDate')" /></div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Purchase Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Purchase Date *</label><input :value="formatDateDisplay(assetCreateForm.purchaseDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetCreateForm.purchaseDate')" /></div>
              <div class="add-field"><label>Supplier Name</label><input v-model="assetCreateForm.supplierName" type="text" placeholder="Enter Supplier Name" /></div>
              <div class="add-field"><label>Warranty Date</label><input :value="formatDateDisplay(assetCreateForm.warrantyDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetCreateForm.warrantyDate')" /></div>
              <div class="add-field"><label>Condition *</label><SearchableSelect v-model="assetCreateForm.condition" :options="assetConditionOptions" placeholder="Not Selected" /></div>
              <div class="add-field">
                <label>Unit Price</label>
                <div class="asset-price-group">
                  <input v-model="assetCreateForm.unitPrice" type="text" placeholder="Enter Amount" />
                  <select v-model="assetCreateForm.currency">
                    <option value="UAE Dirham">UAE Dirham</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                  </select>
                </div>
              </div>
              <div class="add-field">
                <label>QTY *</label>
                <div class="asset-qty-group">
                  <input v-model.number="assetCreateForm.qty" type="number" min="1" placeholder="Enter item quantity" />
                  <button type="button" class="asset-qty-btn" @click="decrementAssetQty">-</button>
                  <button type="button" class="asset-qty-btn" @click="incrementAssetQty">+</button>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="add-employee-footer">
          <button type="button" class="add-employee-clear-btn" @click="resetAssetCreateForm">Clear</button>
          <button type="button" class="add-employee-save-btn" @click="saveAssetCreate">Save</button>
        </div>
      </div>
    </div>

    <div v-if="showApplyLeaveModal" class="edit-overlay add-employee-overlay" @click.self="closeApplyLeaveModal">
      <div class="add-employee-modal leave-apply-modal">
        <div class="add-employee-head">
          <h6>Apply Leave</h6>
          <button type="button" class="add-employee-close" @click="closeApplyLeaveModal">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>

        <div class="add-employee-body">
          <section class="add-employee-section">
            <div class="add-grid-two">
              <div class="add-field add-field-full">
                <label>Employee *</label>
                <SearchableSelect
                  v-model="applyLeaveForm.employee"
                  :options="applyLeaveEmployeeOptions"
                  placeholder="Search Employee or ID"
                />
              </div>
              <div class="add-field add-field-full">
                <label>Leave Type *</label>
                <SearchableSelect
                  v-model="applyLeaveForm.leaveType"
                  :options="leaveTypeOptions"
                  placeholder="Select Type"
                />
              </div>
              <div class="add-field">
                <label>Start Date</label>
                <input :value="formatDateDisplay(applyLeaveForm.startDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('applyLeaveForm.startDate')" />
              </div>
              <div class="add-field">
                <label>End Date</label>
                <input :value="formatDateDisplay(applyLeaveForm.endDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('applyLeaveForm.endDate')" />
              </div>
              <div class="add-field add-field-full">
                <label>Leave Reason</label>
                <textarea v-model="applyLeaveForm.reason" placeholder="Enter Reason"></textarea>
              </div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Attachments</h6>
            <div class="upload-dropzone leave-upload-dropzone">
              <div>
                <strong>Upload documents</strong>
                <small>JPEG, PNG and PDF formats, up to 50MB</small>
              </div>
              <label class="select-file-btn">
                Select File
                <input type="file" class="d-none" @change="handleApplyLeaveFileChange" />
              </label>
            </div>
            <div v-if="applyLeaveAttachment" class="uploaded-doc-card">
              <iconify-icon icon="lucide:file-text" />
              <div>
                <p>{{ applyLeaveAttachment.name }}</p>
                <small>{{ `${Math.max(1, Math.round(applyLeaveAttachment.size / 1024))}KB` }}</small>
              </div>
              <button type="button" @click="removeApplyLeaveFile">
                <iconify-icon icon="lucide:x-circle" />
              </button>
            </div>
          </section>
        </div>

        <div class="add-employee-footer">
          <button type="button" class="add-employee-clear-btn" @click="resetApplyLeaveForm">Cancel</button>
          <button type="button" class="add-employee-save-btn" @click="submitApplyLeave">Apply</button>
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
            <input :value="formatDateDisplay(employeeFilters.joiningDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('employeeFilters.joiningDate')" />
          </div>
          <div class="employee-filter-field">
            <label>Visa Validity</label>
            <input :value="formatDateDisplay(employeeFilters.visaValidity)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('employeeFilters.visaValidity')" />
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

    <div v-if="showAddEmployeeModal" class="edit-overlay add-employee-overlay" @click.self="closeAddEmployeeModal">
      <div class="add-employee-modal">
        <div class="add-employee-head">
          <h6>{{ isEditEmployeeMode ? 'Edit Employee Details' : 'Create New Employee' }}</h6>
          <button type="button" class="add-employee-close" @click="closeAddEmployeeModal">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>

        <div class="add-employee-body">
          <section class="add-employee-section">
            <h6>Profile Details</h6>
            <div class="add-employee-profile-grid">
              <div class="profile-photo-block">
                <div class="profile-photo-avatar">
                  <img v-if="addEmployeeProfilePreview" :src="addEmployeeProfilePreview" alt="Profile preview" />
                  <iconify-icon v-else icon="lucide:user-round" />
                </div>
                <button type="button" class="profile-photo-edit-btn" @click="triggerProfileImageUpload">
                  <iconify-icon icon="lucide:camera" />
                </button>
                <input ref="profileImageInputRef" type="file" class="d-none" accept="image/*" @change="handleProfileImageChange" />
                <span>Profile Photo</span>
              </div>

              <div class="profile-form-grid">
                <div class="add-field">
                  <label>Full Name *</label>
                  <input v-model="addEmployeeForm.full_name" type="text" placeholder="Enter Employee Full Name" />
                </div>
                <div class="add-field">
                  <label>Nationality *</label>
                  <SearchableSelect v-model="addEmployeeForm.nationality" :options="nationalityOptions" placeholder="Not Selected" />
                </div>
                <div class="add-field">
                  <label>Phone Number *</label>
                  <input v-model="addEmployeeForm.phone" type="text" placeholder="Enter Phone Number" />
                </div>
                <div class="add-field">
                  <label>Salary Type *</label>
                  <SearchableSelect v-model="addEmployeeForm.salary_type" :options="salaryTypeOptions" placeholder="Not Selected" />
                </div>
                <div class="add-field">
                  <label>Email *</label>
                  <input v-model="addEmployeeForm.email" type="email" placeholder="Enter Your Email" />
                </div>
                <div class="add-field">
                  <label>Salary *</label>
                  <div class="salary-input-group">
                    <input v-model="addEmployeeForm.salary" type="text" placeholder="Enter Amount" />
                    <span>UAE Dirham</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Company Details</h6>
            <div class="add-grid-two">
              <div class="add-field">
                <label>Branch *</label>
                <SearchableSelect v-model="addEmployeeForm.branch" :options="branchOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Designation *</label>
                <SearchableSelect v-model="addEmployeeForm.designation" :options="designationOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Department *</label>
                <SearchableSelect v-model="addEmployeeForm.department" :options="departmentOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Supervisor *</label>
                <SearchableSelect v-model="addEmployeeForm.supervisor" :options="supervisorOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Joining Date *</label>
                <input :value="formatDateDisplay(addEmployeeForm.joining_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.joining_date')" />
              </div>
              <div class="add-field">
                <label>Visa Validity *</label>
                <input :value="formatDateDisplay(addEmployeeForm.visa_validity)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.visa_validity')" />
              </div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Upload Employee Documents</h6>
            <div class="doc-chip-row">
              <button
                v-for="doc in employeeDocumentTypes"
                :key="doc"
                type="button"
                class="doc-chip"
                :class="{ active: selectedDocumentType === doc }"
                @click="selectedDocumentType = doc"
              >
                {{ doc }}
              </button>
            </div>
            <div class="add-field">
              <label>Emirates ID Number *</label>
              <input v-model="addEmployeeForm.emirates_id_number" type="text" placeholder="Enter Emirates ID Number" />
            </div>
            <div class="upload-dropzone">
              <div>
                <strong>Drag and drop your files</strong>
                <small>JPEG, PNG and PDF formats, up to 50MB</small>
              </div>
              <label class="select-file-btn">
                Select File
                <input type="file" class="d-none" @change="handleAddEmployeeFileChange" />
              </label>
            </div>
            <div v-if="addEmployeeUploadedFile" class="uploaded-doc-card">
              <iconify-icon icon="lucide:file-text" />
              <div>
                <p>{{ addEmployeeUploadedFile.name }}</p>
                <small>{{ `${Math.max(1, Math.round(addEmployeeUploadedFile.size / 1024))}KB` }}</small>
              </div>
              <button type="button" @click="removeAddEmployeeFile">
                <iconify-icon icon="lucide:x-circle" />
              </button>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Bank Account Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Account Holder Name</label><input v-model="addEmployeeForm.account_holder_name" type="text" placeholder="Enter Account Holder Name" /></div>
              <div class="add-field"><label>Bank Name</label><SearchableSelect v-model="addEmployeeForm.bank_name" :options="bankNameOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Branch Location</label><input v-model="addEmployeeForm.branch_location" type="text" placeholder="Enter Bank branch location" /></div>
              <div class="add-field"><label>Account Number</label><input v-model="addEmployeeForm.account_number" type="text" placeholder="Enter Bank account number" /></div>
              <div class="add-field"><label>IBAN Number</label><input v-model="addEmployeeForm.iban_number" type="text" placeholder="Enter IBAN Number" /></div>
              <div class="add-field"><label>SWIFT Code</label><input v-model="addEmployeeForm.swift_code" type="text" placeholder="Enter SWIFT Code" /></div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Insurance Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Policy Type *</label><SearchableSelect v-model="addEmployeeForm.policy_type" :options="policyTypeOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Insurance Provider *</label><input v-model="addEmployeeForm.insurance_provider" type="text" placeholder="Enter Insurance Provider Name" /></div>
              <div class="add-field"><label>Policy Number *</label><input v-model="addEmployeeForm.policy_number" type="text" placeholder="Enter Policy Number" /></div>
              <div class="add-field"><label>Start Date *</label><input :value="formatDateDisplay(addEmployeeForm.insurance_start_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.insurance_start_date')" /></div>
              <div class="add-field"><label>Expiry Date *</label><input :value="formatDateDisplay(addEmployeeForm.insurance_expiry_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.insurance_expiry_date')" /></div>
            </div>
          </section>
        </div>

        <div class="add-employee-footer">
          <button type="button" class="add-employee-clear-btn" @click="resetAddEmployeeForm">{{ isEditEmployeeMode ? 'Cancel' : 'Clear' }}</button>
          <button type="button" class="add-employee-save-btn" @click="saveEmployeeForm">{{ isEditEmployeeMode ? 'Save' : 'Save' }}</button>
        </div>
      </div>
    </div>

    <div v-if="showSectionEditModal" class="edit-overlay" @click.self="showSectionEditModal = false">
      <div class="employee-filter-modal section-edit-modal">
        <button type="button" class="employee-filter-close" @click="showSectionEditModal = false">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="employee-filter-right w-100">
          <h6 class="mb-2">{{ sectionEditTitle }}</h6>
          <template v-if="editingSection === 'company'">
            <div class="add-grid-two">
              <div class="add-field"><label>Branch *</label><SearchableSelect v-model="sectionEditForm.branch" :options="branchOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Designation *</label><SearchableSelect v-model="sectionEditForm.designation" :options="designationOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Department *</label><SearchableSelect v-model="sectionEditForm.department" :options="departmentOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Supervisor *</label><SearchableSelect v-model="sectionEditForm.supervisor" :options="supervisorOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Joining Date *</label><input :value="formatDateDisplay(sectionEditForm.joiningDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('sectionEditForm.joiningDate')" /></div>
              <div class="add-field"><label>Visa Validity *</label><input :value="formatDateDisplay(sectionEditForm.visaValidity)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('sectionEditForm.visaValidity')" /></div>
            </div>
          </template>
          <template v-else-if="editingSection === 'bank'">
            <div class="add-grid-two">
              <div class="add-field"><label>Account Holder Name *</label><input v-model="sectionEditForm.account_holder_name" type="text" /></div>
              <div class="add-field"><label>Bank Name *</label><SearchableSelect v-model="sectionEditForm.bank_name" :options="bankNameOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Branch Location</label><input v-model="sectionEditForm.branch_location" type="text" /></div>
              <div class="add-field"><label>Account Number *</label><input v-model="sectionEditForm.account_number" type="text" /></div>
              <div class="add-field"><label>IBAN Number *</label><input v-model="sectionEditForm.iban_number" type="text" /></div>
              <div class="add-field"><label>SWIFT Code</label><input v-model="sectionEditForm.swift_code" type="text" /></div>
            </div>
          </template>
          <template v-else-if="editingSection === 'insurance'">
            <div class="add-grid-two">
              <div class="add-field"><label>Policy Type *</label><SearchableSelect v-model="sectionEditForm.policy_type" :options="policyTypeOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Insurance Provider *</label><input v-model="sectionEditForm.insurance_provider" type="text" /></div>
              <div class="add-field"><label>Policy Number *</label><input v-model="sectionEditForm.policy_number" type="text" /></div>
              <div class="add-field"><label>Start Date *</label><input :value="formatDateDisplay(sectionEditForm.insurance_start_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('sectionEditForm.insurance_start_date')" /></div>
              <div class="add-field"><label>Expiry Date *</label><input :value="formatDateDisplay(sectionEditForm.insurance_expiry_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('sectionEditForm.insurance_expiry_date')" /></div>
            </div>
          </template>
          <template v-else-if="editingSection === 'documents'">
            <div class="add-grid-two">
              <div class="add-field"><label>Emirates ID Number *</label><input v-model="sectionEditForm.emiratesId" type="text" /></div>
              <div class="add-field"><label>Attested Certificate *</label><SearchableSelect v-model="sectionEditForm.attestedCertificate" :options="['Yes','No']" placeholder="Not Selected" /></div>
            </div>
            <div class="upload-dropzone mt-2">
              <div>
                <strong>Drag and drop your files</strong>
                <small>JPEG, PNG and PDF formats, up to 50MB</small>
              </div>
              <label class="select-file-btn">
                Select File
                <input type="file" class="d-none" @change="handleAddEmployeeFileChange" />
              </label>
            </div>
          </template>
          <template v-else>
            <div class="add-grid-two">
              <div class="add-field"><label>Full Name *</label><input v-model="sectionEditForm.name" type="text" /></div>
              <div class="add-field"><label>Phone Number *</label><input v-model="sectionEditForm.phone" type="text" /></div>
              <div class="add-field"><label>Email *</label><input v-model="sectionEditForm.email" type="email" /></div>
              <div class="add-field"><label>Nationality *</label><SearchableSelect v-model="sectionEditForm.nationality" :options="nationalityOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Salary Type *</label><SearchableSelect v-model="sectionEditForm.salary_type" :options="salaryTypeOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Basic Salary *</label><input v-model="sectionEditForm.salary" type="text" /></div>
            </div>
          </template>
          <div class="employee-filter-actions mt-2">
            <button type="button" class="employee-filter-btn ghost" @click="showSectionEditModal = false">Cancel</button>
            <button type="button" class="employee-filter-btn primary" @click="saveSectionEdit">Save</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <DateTimePicker
    :show="showUnifiedDatePicker"
    :model-value="datePickerValue"
    :date-only="true"
    @update:show="showUnifiedDatePicker = $event"
    @apply="handleDatePickerApply"
  />
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ApexCharts from 'vue3-apexcharts'
import api from '@/plugins/axios'
import HrTeamTreePanel from '@/components/hr/HrTeamTreePanel.vue'
import StatsCards from '@/components/hr/overview/StatsCards.vue'
import EmployeesTable from '@/components/hr/overview/EmployeesTable.vue'
import EmployeeDetails from '@/components/hr/overview/EmployeeDetails.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import DateTimePicker from '@/components/kanban/shared/DateTimePicker.vue'
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
const hrDebugUi = computed(() => {
  void route.fullPath
  return hrPipelineDebugEnabled()
})

// ========== EMPLOYEES DATA FROM API ==========
const employeesDirectory = ref([])
const loadingEmployees = ref(false)

// Fetch real employees from API
const fetchRealEmployees = async () => {
  loadingEmployees.value = true
  try {
    const response = await api.get('/employees', {
      params: {
        per_page: 1000
      }
    })
    
    if (response.data && response.data.data) {
      employeesDirectory.value = response.data.data.map(emp => ({
        id: emp.id,
        name: emp.name,
        email: emp.email,
        phone: emp.phone || '-',
        avatar: emp.avatar || `https://i.pravatar.cc/80?img=${emp.id % 70}`,
        designation: emp.employee_profile?.designation?.name || emp.designation_name || '-',
        department: emp.employee_profile?.department?.name || emp.department_name || '-',
        branch: emp.employee_profile?.branch_name || emp.branch_name || '-',
        joiningDate: emp.employee_profile?.joining_date || '-',
        visaValidity: emp.employee_profile?.contract_end_date || '-',
        passportNumber: emp.employee_profile?.passport_number || '-',
        emiratesId: emp.employee_profile?.emirates_id_number || '-',
        employment_status: emp.employee_profile?.employment_status || 'active',
        status: emp.status || 'active',
        statusText: emp.status === 'active' ? 'Active' : emp.status === 'in_active' ? 'In Active' : 'Blocked',
        statusType: emp.status === 'active' ? 'active' : 'inactive',
        nationality: emp.nationality || '-',
        salary: emp.salary || '-',
        salary_type: emp.salary_type || 'Monthly',
        supervisor: emp.parent?.name || '-',
        role_name: emp.role_name || '-',
        employee_code: emp.employee_profile?.employee_code || `EMP-${emp.id}`,
        email_work: emp.email,
        email_personal: emp.personal_email || '-',
        phone_company: emp.company_mobile || '-',
        phone_personal: emp.phone || '-',
        gender: emp.gender || '-',
        birth_date: emp.birth_date || '-',
        marital_status: emp.marital_status || '-',
      }))
      
      console.log('Employees loaded from API:', employeesDirectory.value.length)
    }
  } catch (error) {
    console.error('Error fetching employees:', error)
    employeesDirectory.value = []
  } finally {
    loadingEmployees.value = false
  }
}

// ========== UI State ==========
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
const showApplyLeaveModal = ref(false)
const showUnifiedDatePicker = ref(false)
const datePickerValue = ref(null)
const activeDateField = ref('')
const leaveSectionMode = ref('leave')
const showLeaveSearchModal = ref(false)
const openLeaveRowMenuId = ref(null)
const leaveRowMenuStyle = ref({})
const selectedLeaveRow = ref(null)
const showLeaveDetailModal = ref(false)
const showLeaveApproveModal = ref(false)
const showLeaveRejectModal = ref(false)
const leavePage = ref(1)
const leavePerPage = 10
const assetsSearch = ref('')
const showAssetSearchModal = ref(false)
const showAssetCreateModal = ref(false)
const assetsPage = ref(1)
const assetsPerPage = 10
const openEmployeeRowMenuId = ref(null)
const openAssetRowMenuId = ref(null)
const selectedFilterChip = ref('Marketing')
const showAddEmployeeModal = ref(false)
const isEditEmployeeMode = ref(false)
const editingEmployeeId = ref(null)
const profileImageInputRef = ref(null)
const addEmployeeProfilePreview = ref('')
const addEmployeeProfileFile = ref(null)
const employeeRowMenuStyle = ref({})
const assetRowMenuStyle = ref({})
const selectedEmployeeDetail = ref(null)
const employeeDetailTab = ref('company')
const employeeDetailMainRef = ref(null)
const employeeCompanySectionRef = ref(null)
const employeeDocumentsSectionRef = ref(null)
const employeeBankSectionRef = ref(null)
const employeeAssetsSectionRef = ref(null)
const employeeInsuranceSectionRef = ref(null)
const showSectionEditModal = ref(false)
const editingSection = ref('')
const sectionEditForm = ref({})
const selectedDocumentType = ref('Emirates ID')
const addEmployeeUploadedFile = ref(null)
const employeeFilters = ref({
  name: '',
  department: '',
  designation: '',
  joiningDate: '',
  visaValidity: '',
  status: '',
})
const editingRow = ref(null)
const showAssetEditModal = ref(false)
const editingAssetId = ref(null)
const selectedAssetSearchChip = ref('Assigned')
const assetSearchChips = ['Not Assigned', 'Assigned', 'New', 'Used', 'Working', 'Saadiyat, Abu dhabi', 'Muroor, Abu Dhabi', 'Dubai']
const defaultAssetSearchFilters = () => ({
  assetType: '',
  assetName: '',
  createdOn: '',
  serialNumber: '',
  assetUser: '',
  department: '',
  branchLocation: '',
  status: '',
  purchaseDate: '',
  supplierName: '',
  condition: '',
})
const assetSearchFilters = ref(defaultAssetSearchFilters())
const assetTypeOptions = ['Laptop', 'Phone', 'Printer', 'SIM', 'Charger', 'DeskTop']
const assetStatusOptions = ['Assigned', 'Not Assigned', 'Working', 'In Repair', 'Used', 'New']
const assetConditionOptions = ['New', 'Used', 'Working']
const assetUserOptions = computed(() => Array.from(new Set(assetsRows.value.map((row) => row.userName))).filter(Boolean))
const assetResponsiblePersons = ref([])
const showAssetUserPicker = ref(false)
const assetUserSearchQuery = ref('')
const assetUserPickerRef = ref(null)
const defaultPersonAvatar = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
const defaultAssetCreateForm = () => ({
  assetType: '',
  assetName: '',
  serialNumber: '',
  modelNumber: '',
  rdpNumber: '',
  remarks: '',
  description: '',
  assetUser: null,
  handoverDate: '',
  returnDate: '',
  branchLocation: '',
  department: '',
  status: '',
  purchaseDate: '',
  supplierName: '',
  warrantyDate: '',
  condition: '',
  unitPrice: '',
  currency: 'UAE Dirham',
  qty: 1,
})
const assetCreateForm = ref(defaultAssetCreateForm())
const selectedAssetResponsiblePerson = computed(() =>
  assetResponsiblePersons.value.find((person) => Number(person.id) === Number(assetCreateForm.value.assetUser)) || null,
)
const filteredAssetResponsiblePersons = computed(() => {
  const query = assetUserSearchQuery.value.trim().toLowerCase()
  if (!query) return assetResponsiblePersons.value
  return assetResponsiblePersons.value.filter((person) =>
    String(person.name || '').toLowerCase().includes(query) ||
    String(person.email || '').toLowerCase().includes(query),
  )
})
const assetEditForm = ref({
  assetId: '',
  type: '',
  assetName: '',
  userName: '',
  userRef: '',
  handoverDate: '',
  brand: '',
  category: '',
  handoverTo: '',
  serial: '',
  status: '',
})
const hrSectionTab = ref('attendance')

const headerTabMenus = {
  Employees: ['Manage Employees', 'Employee Assets'],
  Payroll: ['Manage Salary', 'Manage Pay Slip'],
  'Leave / Attendance': ['Leave Management', 'Attendance Management', 'Announcements'],
  Career: ['Manage Recruitments', 'Interviews', 'Career Lists'],
  Assets: ['Asset Directory', 'Asset Requests'],
}

// ========== OVERVIEW EMPLOYEES (from API data) ==========
const overviewEmployees = computed(() => {
  if (employeesDirectory.value.length > 0) {
    return employeesDirectory.value.map(emp => ({
      id: emp.id,
      name: emp.name,
      designation: emp.designation,
      email: emp.email,
      department: emp.department,
      status: emp.employment_status === 'active' ? 'Present' : emp.employment_status === 'on_leave' ? 'On Leave' : 'Others',
      attendanceType: emp.employment_status === 'active' ? 'present' : emp.employment_status === 'on_leave' ? 'leave' : 'other',
      avatar: emp.avatar,
    }))
  }
  return []
})

// ========== FILTERED EMPLOYEES FOR TABLE ==========
const filteredEmployeeRows = computed(() => {
  const rows = employeesDirectory.value
  
  return rows.filter((row) => {
    const nameOk = !employeeFilters.value.name || (row.name && row.name.toLowerCase().includes(employeeFilters.value.name.toLowerCase()))
    const depOk = !employeeFilters.value.department || row.department === employeeFilters.value.department
    const desigOk = !employeeFilters.value.designation || row.designation === employeeFilters.value.designation
    const statusOk = !employeeFilters.value.status || row.statusText === employeeFilters.value.status
    return nameOk && depOk && desigOk && statusOk
  })
})

// ========== STATS ==========
const overviewStats = computed(() => {
  const total = employeesDirectory.value.length
  const active = employeesDirectory.value.filter(e => e.employment_status === 'active' || e.status === 'active').length
  
  return [
    { key: 'employees', label: 'Total Employees', value: total, icon: 'lucide:users', bgColor: '#ebf4ff', iconColor: '#2f65f6' },
    { key: 'applications', label: 'Job Applications', value: 352, icon: 'lucide:file-text', bgColor: '#f4e8ff', iconColor: '#9333ea' },
    { key: 'new-employees', label: 'New Employees', value: 56, icon: 'lucide:user-round-plus', bgColor: '#e8f8ed', iconColor: '#16a34a' },
    { key: 'attendance', label: 'Todays Attendance', value: active, icon: 'lucide:calendar-check-2', bgColor: '#e8fbff', iconColor: '#0ea5e9' },
  ]
})

const employeeStats = computed(() => {
  const total = employeesDirectory.value.length
  const active = employeesDirectory.value.filter(e => e.status === 'active').length
  const inactive = employeesDirectory.value.filter(e => e.status === 'in_active').length
  
  return [
    { key: 'employees', label: 'Total Employees', value: total, icon: 'lucide:users', bgColor: '#ebf4ff', iconColor: '#2f65f6' },
    { key: 'applications', label: 'New Employees', value: 25, icon: 'lucide:file-text', bgColor: '#f4e8ff', iconColor: '#9333ea' },
    { key: 'new-employees', label: 'Resigned Employees', value: 5, icon: 'lucide:user-round-plus', bgColor: '#e8f8ed', iconColor: '#16a34a' },
    { key: 'attendance', label: 'Active Employees', value: active, icon: 'lucide:calendar-check-2', bgColor: '#e8fbff', iconColor: '#0ea5e9' },
  ]
})

// ========== DEPARTMENT SERIES ==========
const departmentSeries = computed(() => {
  const deptMap = {}
  employeesDirectory.value.forEach(emp => {
    const dept = emp.department
    if (dept && dept !== '-') {
      deptMap[dept] = (deptMap[dept] || 0) + 1
    }
  })
  
  const total = employeesDirectory.value.length
  return Object.entries(deptMap).map(([dept, count]) => ({
    department: dept,
    value: Math.round((count / total) * 100)
  })).slice(0, 5)
})

// ========== ATTENDANCE LEGEND ==========
const attendanceLegend = computed(() => {
  const tally = { present: 0, onLeave: 0, holiday: 0, others: 0 }
  overviewEmployees.value.forEach((employee) => {
    if (employee.attendanceType === 'present') tally.present += 1
    else if (employee.attendanceType === 'leave') tally.onLeave += 1
    else if (employee.attendanceType === 'holiday') tally.holiday += 1
    else tally.others += 1
  })
  return tally
})

// ========== FILTERED OVERVIEW EMPLOYEES ==========
const filteredOverviewEmployees = computed(() => {
  const keyword = overviewSearch.value.trim().toLowerCase()
  return overviewEmployees.value.filter((employee) => {
    const matchSearch = !keyword ||
      employee.name.toLowerCase().includes(keyword) ||
      employee.department.toLowerCase().includes(keyword) ||
      employee.designation.toLowerCase().includes(keyword)
    return matchSearch
  })
})

// ========== OPTIONS FOR FILTERS ==========
const departmentOptions = computed(() => {
  const depts = new Set()
  employeesDirectory.value.forEach(emp => {
    if (emp.department && emp.department !== '-') depts.add(emp.department)
  })
  return Array.from(depts)
})

const designationOptions = computed(() => {
  const desigs = new Set()
  employeesDirectory.value.forEach(emp => {
    if (emp.designation && emp.designation !== '-') desigs.add(emp.designation)
  })
  return Array.from(desigs)
})

const supervisorOptions = computed(() => {
  const sups = new Set()
  employeesDirectory.value.forEach(emp => {
    if (emp.supervisor && emp.supervisor !== '-') sups.add(emp.supervisor)
  })
  return Array.from(sups)
})

const branchOptions = ['Dubai HQ', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Saadiyat', 'Reem', 'Main']
const nationalityOptions = ['UAE', 'Egypt', 'India', 'Pakistan', 'Morocco', 'Jordan', 'Philippines']
const salaryTypeOptions = ['Daily', 'Monthly', 'Yearly']
const bankNameOptions = ['Emirates NBD', 'ADCB', 'Mashreq', 'FAB', 'RAKBANK']
const policyTypeOptions = ['Basic Health', 'Standard Health', 'Premium Health', 'Life Insurance']
const employeeDocumentTypes = ['Emirates ID', 'Labor Card', 'Passport', 'Visa', 'Attested Certificates']
const statusOptions = ['Active', 'In Active', 'Blocked']
const leaveTypeOptions = [
  'Annual Leave (Paid Leave)',
  'Sick Leave (2/10)',
  'Casual Leave',
  'Maternity Leave',
  'Paternity Leave',
  'Unpaid Leave (Leave Without Pay - LOP)',
  'Bereavement Leave',
  'Compensatory Off (Comp Off)',
  'Public Holiday / Company Holiday',
  'Study Leave / Training Leave',
]
const leaveTypeFilterOptions = ['Annual', 'Sick', 'Casual', 'Maternity', 'Paternity']
const leaveStatusOptions = ['Approved', 'Pending', 'Rejected']
const leaveSearchChips = ['Approved', 'Pending', 'Rejected']
const selectedLeaveSearchChip = ref('Rejected')
const defaultLeaveSearchFilters = () => ({
  employee: '',
  leaveType: '',
  appliedDate: '',
  status: '',
})
const leaveSearchFilters = ref(defaultLeaveSearchFilters())
const defaultApplyLeaveForm = () => ({
  employee: '',
  leaveType: '',
  startDate: '',
  endDate: '',
  reason: '',
})
const applyLeaveForm = ref(defaultApplyLeaveForm())
const applyLeaveAttachment = ref(null)
const applyLeaveEmployeeOptions = computed(() =>
  employeesDirectory.value.map((employee) => `#${employee.employee_code} ${employee.name}`),
)

// ========== LEAVE ROWS (Mock for now, can be replaced with API) ==========
const leaveRows = ref([
  { id: 1, empId: '#EMPO01', employeeName: 'Maria Guan', avatar: 'https://i.pravatar.cc/80?img=47', designation: 'Senior Accountant', leaveType: 'Annual', startDate: '05 Feb 2026', endDate: '05 Feb 2026', days: '25', reason: 'Family Trip', appliedDate: '15 Jan 2026', status: 'Approved', approvedBy: 'HR Manager' },
  { id: 2, empId: '#EMPO02', employeeName: 'Ahmad Al Daghash', avatar: 'https://i.pravatar.cc/80?img=12', designation: 'UI/UX Designer', leaveType: 'Sick', startDate: '10 Feb 2026', endDate: '11 Feb 2026', days: '02', reason: 'Fever', appliedDate: '10 Feb 2026', status: 'Approved', approvedBy: 'HR Manager' },
])

// ========== ASSETS ROWS ==========
const assetsRows = ref([
  { id: 1, assetId: '#AST-001', type: 'Laptop', assetName: 'Dell Laptop', userName: 'Maria Guan', userRef: '455845', userAvatar: 'https://i.pravatar.cc/80?img=47', handoverDate: '05 Feb 2023', rValue: '--', brand: 'Dell', category: 'IT Equipment', handoverTo: 'Maria Guan', serial: 'DL-ASS-001', status: 'Assigned' },
  { id: 2, assetId: '#AST-002', type: 'Charger', assetName: 'Laptop Charger HP', userName: 'Omar Moradan', userRef: '455845', userAvatar: 'https://i.pravatar.cc/80?img=15', handoverDate: '10 Feb 2023', rValue: '--', brand: 'HP', category: 'Accessory', handoverTo: 'Omar Moradan', serial: 'HP-CHR-002', status: 'Assigned' },
])

const defaultAddEmployeeForm = () => ({
  full_name: '',
  nationality: '',
  phone: '',
  salary_type: '',
  email: '',
  salary: '',
  branch: '',
  designation: '',
  department: '',
  supervisor: '',
  joining_date: '',
  visa_validity: '',
  emirates_id_number: '',
  account_holder_name: '',
  bank_name: '',
  branch_location: '',
  account_number: '',
  iban_number: '',
  swift_code: '',
  policy_type: '',
  insurance_provider: '',
  policy_number: '',
  insurance_start_date: '',
  insurance_expiry_date: '',
})

const addEmployeeForm = ref(defaultAddEmployeeForm())

const sectionEditTitle = computed(() => {
  const titles = {
    profile: 'Edit Profile Details',
    company: 'Edit Company Details',
    documents: 'Edit Document Details',
    bank: 'Bank Account Details',
    insurance: 'Edit Insurance Details',
  }
  return titles[editingSection.value] || 'Edit Details'
})

// ========== HELPER FUNCTIONS ==========
function toDateValue(value) {
  if (!value) return null
  if (value instanceof Date) return value
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return new Date(`${value}T12:00:00`)
  }
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function toIsoDate(value) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return ''
  const y = value.getFullYear()
  const m = String(value.getMonth() + 1).padStart(2, '0')
  const d = String(value.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function formatDateDisplay(value) {
  if (!value) return ''
  const dt = toDateValue(value)
  if (!dt) return ''
  const d = String(dt.getDate()).padStart(2, '0')
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const y = dt.getFullYear()
  return `${d}/${m}/${y}`
}

function getFieldValueByPath(path) {
  if (path === 'dateFilter') return dateFilter.value
  if (path === 'employeeFilters.joiningDate') return employeeFilters.value.joiningDate
  if (path === 'employeeFilters.visaValidity') return employeeFilters.value.visaValidity
  if (path === 'addEmployeeForm.joining_date') return addEmployeeForm.value.joining_date
  if (path === 'addEmployeeForm.visa_validity') return addEmployeeForm.value.visa_validity
  if (path === 'addEmployeeForm.insurance_start_date') return addEmployeeForm.value.insurance_start_date
  if (path === 'addEmployeeForm.insurance_expiry_date') return addEmployeeForm.value.insurance_expiry_date
  if (path === 'sectionEditForm.joiningDate') return sectionEditForm.value.joiningDate
  if (path === 'sectionEditForm.visaValidity') return sectionEditForm.value.visaValidity
  if (path === 'sectionEditForm.insurance_start_date') return sectionEditForm.value.insurance_start_date
  if (path === 'sectionEditForm.insurance_expiry_date') return sectionEditForm.value.insurance_expiry_date
  if (path === 'assetSearchFilters.createdOn') return assetSearchFilters.value.createdOn
  if (path === 'assetSearchFilters.purchaseDate') return assetSearchFilters.value.purchaseDate
  if (path === 'assetCreateForm.handoverDate') return assetCreateForm.value.handoverDate
  if (path === 'assetCreateForm.returnDate') return assetCreateForm.value.returnDate
  if (path === 'assetCreateForm.purchaseDate') return assetCreateForm.value.purchaseDate
  if (path === 'assetCreateForm.warrantyDate') return assetCreateForm.value.warrantyDate
  if (path === 'applyLeaveForm.startDate') return applyLeaveForm.value.startDate
  if (path === 'applyLeaveForm.endDate') return applyLeaveForm.value.endDate
  if (path === 'leaveSearchFilters.appliedDate') return leaveSearchFilters.value.appliedDate
  return ''
}

function setFieldValueByPath(path, value) {
  if (path === 'dateFilter') dateFilter.value = value
  else if (path === 'employeeFilters.joiningDate') employeeFilters.value.joiningDate = value
  else if (path === 'employeeFilters.visaValidity') employeeFilters.value.visaValidity = value
  else if (path === 'addEmployeeForm.joining_date') addEmployeeForm.value.joining_date = value
  else if (path === 'addEmployeeForm.visa_validity') addEmployeeForm.value.visa_validity = value
  else if (path === 'addEmployeeForm.insurance_start_date') addEmployeeForm.value.insurance_start_date = value
  else if (path === 'addEmployeeForm.insurance_expiry_date') addEmployeeForm.value.insurance_expiry_date = value
  else if (path === 'sectionEditForm.joiningDate') sectionEditForm.value.joiningDate = value
  else if (path === 'sectionEditForm.visaValidity') sectionEditForm.value.visaValidity = value
  else if (path === 'sectionEditForm.insurance_start_date') sectionEditForm.value.insurance_start_date = value
  else if (path === 'sectionEditForm.insurance_expiry_date') sectionEditForm.value.insurance_expiry_date = value
  else if (path === 'assetSearchFilters.createdOn') assetSearchFilters.value.createdOn = value
  else if (path === 'assetSearchFilters.purchaseDate') assetSearchFilters.value.purchaseDate = value
  else if (path === 'assetCreateForm.handoverDate') assetCreateForm.value.handoverDate = value
  else if (path === 'assetCreateForm.returnDate') assetCreateForm.value.returnDate = value
  else if (path === 'assetCreateForm.purchaseDate') assetCreateForm.value.purchaseDate = value
  else if (path === 'assetCreateForm.warrantyDate') assetCreateForm.value.warrantyDate = value
  else if (path === 'applyLeaveForm.startDate') applyLeaveForm.value.startDate = value
  else if (path === 'applyLeaveForm.endDate') applyLeaveForm.value.endDate = value
  else if (path === 'leaveSearchFilters.appliedDate') leaveSearchFilters.value.appliedDate = value
}

function openDatePicker(path) {
  activeDateField.value = path
  datePickerValue.value = toDateValue(getFieldValueByPath(path))
  showUnifiedDatePicker.value = true
}

function handleDatePickerApply(date) {
  const targetPath = activeDateField.value
  if (!targetPath) return
  setFieldValueByPath(targetPath, toIsoDate(date))
  if (targetPath === 'dateFilter') onAttendanceDateChange()
}

// ========== FILTERED ASSETS ==========
const filteredAssetsRows = computed(() => {
  const keyword = assetsSearch.value.trim().toLowerCase()
  return assetsRows.value.filter((asset) => {
    const matchesKeyword = !keyword || [
      asset.assetId,
      asset.type,
      asset.assetName,
      asset.userName,
      asset.handoverDate,
      asset.brand,
      asset.category,
      asset.serial,
      asset.status,
    ].some((value) => String(value || '').toLowerCase().includes(keyword))

    if (!matchesKeyword) return false

    const f = assetSearchFilters.value
    if (f.assetType && String(asset.type) !== String(f.assetType)) return false
    if (f.assetName && !String(asset.assetName || '').toLowerCase().includes(f.assetName.toLowerCase())) return false
    if (f.serialNumber && !String(asset.serial || '').toLowerCase().includes(f.serialNumber.toLowerCase())) return false
    if (f.assetUser && String(asset.userName) !== String(f.assetUser)) return false
    if (f.status && String(asset.status) !== String(f.status)) return false

    if (selectedAssetSearchChip.value && !['Assigned', 'Not Assigned', 'New', 'Used', 'Working'].includes(selectedAssetSearchChip.value)) {
      if (!String(asset.branchLocation || '').toLowerCase().includes(selectedAssetSearchChip.value.toLowerCase())) return false
    } else if (selectedAssetSearchChip.value) {
      if (String(asset.status || '').toLowerCase() !== selectedAssetSearchChip.value.toLowerCase()) return false
    }

    return true
  })
})

// ========== LEAVE FUNCTIONS ==========
const leaveEmployeeOptions = computed(() =>
  leaveRows.value.map((row) => `${row.empId} ${row.employeeName}`),
)

const filteredLeaveRows = computed(() => {
  const f = leaveSearchFilters.value
  const chip = selectedLeaveSearchChip.value
  const search = String(searchKeyword.value || '').trim().toLowerCase()
  return leaveRows.value.filter((row) => {
    if (chip && row.status.toLowerCase() !== chip.toLowerCase()) return false
    if (f.employee && !`${row.empId} ${row.employeeName}`.toLowerCase().includes(String(f.employee).toLowerCase())) return false
    if (f.leaveType && String(row.leaveType).toLowerCase() !== String(f.leaveType).toLowerCase()) return false
    if (f.status && String(row.status).toLowerCase() !== String(f.status).toLowerCase()) return false
    if (search) {
      const values = [row.empId, row.employeeName, row.leaveType, row.reason, row.appliedDate, row.status, row.approvedBy]
      return values.some((v) => String(v || '').toLowerCase().includes(search))
    }
    return true
  })
})

const leaveSearchSummary = computed(() => {
  const f = leaveSearchFilters.value
  if (f.employee) return f.employee
  if (f.leaveType) return `Type: ${f.leaveType}`
  return 'Filter and search Leaves'
})

const leaveTotalPages = computed(() => Math.max(1, Math.ceil(filteredLeaveRows.value.length / leavePerPage)))
const pagedLeaveRows = computed(() => {
  const start = (leavePage.value - 1) * leavePerPage
  return filteredLeaveRows.value.slice(start, start + leavePerPage)
})
const leavesStartEntry = computed(() => (filteredLeaveRows.value.length ? (leavePage.value - 1) * leavePerPage + 1 : 0))
const leavesEndEntry = computed(() => Math.min(leavePage.value * leavePerPage, filteredLeaveRows.value.length))
const leavePaginationItems = computed(() => {
  const total = leaveTotalPages.value
  const current = leavePage.value
  if (total <= 1) return [{ type: 'page', n: 1 }]
  if (total <= 7) return Array.from({ length: total }, (_, i) => ({ type: 'page', n: i + 1 }))
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

const assetSearchSummary = computed(() => {
  const f = assetSearchFilters.value
  if (f.assetName) return `Search: ${f.assetName}`
  if (f.assetType) return `Type: ${f.assetType}`
  if (f.assetUser) return `User: ${f.assetUser}`
  return 'Filter and search Assets'
})

const assetsTotalPages = computed(() => Math.max(1, Math.ceil(filteredAssetsRows.value.length / assetsPerPage)))
const pagedAssetsRows = computed(() => {
  const start = (assetsPage.value - 1) * assetsPerPage
  return filteredAssetsRows.value.slice(start, start + assetsPerPage)
})
const assetsStartEntry = computed(() => (filteredAssetsRows.value.length ? (assetsPage.value - 1) * assetsPerPage + 1 : 0))
const assetsEndEntry = computed(() => Math.min(assetsPage.value * assetsPerPage, filteredAssetsRows.value.length))
const assetsPaginationItems = computed(() => {
  const total = assetsTotalPages.value
  const current = assetsPage.value
  if (total <= 1) return [{ type: 'page', n: 1 }]
  if (total <= 7) return Array.from({ length: total }, (_, i) => ({ type: 'page', n: i + 1 }))
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

// ========== ATTENDANCE FUNCTIONS ==========
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

watch(assetsSearch, () => {
  assetsPage.value = 1
})

watch(assetsTotalPages, (tp) => {
  if (assetsPage.value > tp) assetsPage.value = tp
})

watch(showAssetCreateModal, (visible) => {
  if (visible) fetchAssetResponsiblePersons()
})

watch([selectedLeaveSearchChip, () => leaveSearchFilters.value.employee, () => leaveSearchFilters.value.leaveType, () => leaveSearchFilters.value.appliedDate, () => leaveSearchFilters.value.status], () => {
  leavePage.value = 1
})

watch(leaveTotalPages, (tp) => {
  if (leavePage.value > tp) leavePage.value = tp
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

function exportAssets() {
  const headers = ['Asset ID', 'Type', 'Asset Name', 'User', 'Date Of Handover', 'Brand', 'Category', 'Serial Number', 'Status']
  const rows = filteredAssetsRows.value.map((asset) => [
    asset.assetId,
    asset.type,
    asset.assetName,
    asset.userName,
    asset.handoverDate,
    asset.brand,
    asset.category,
    asset.serial,
    asset.status,
  ])
  const csv = [headers, ...rows]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `assets-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

function onHeaderTabClick(tab) {
  const hasDropdown = !!headerTabMenus[tab]
  if (!hasDropdown) {
    activeTab.value = tab
    if (tab === 'Leave / Attendance') leaveSectionMode.value = 'leave'
    openHeaderMenu.value = null
    return
  }

  openHeaderMenu.value = openHeaderMenu.value === tab ? null : tab
}

function onHeaderMenuSelect(tab, item) {
  activeTab.value = tab
  if (tab === 'Leave / Attendance') {
    if (item === 'Leave Management') leaveSectionMode.value = 'leave'
    else if (item === 'Attendance Management') leaveSectionMode.value = 'attendance'
    else leaveSectionMode.value = 'announcements'
  }
  openHeaderMenu.value = null
}

function scrollEmployeeDetailSection(sectionKey) {
  employeeDetailTab.value = sectionKey
  nextTick(() => {
    const sectionMap = {
      company: employeeCompanySectionRef.value,
      documents: employeeDocumentsSectionRef.value,
      bank: employeeBankSectionRef.value,
      assets: employeeAssetsSectionRef.value,
      insurance: employeeInsuranceSectionRef.value,
    }
    const container = employeeDetailMainRef.value
    const target = sectionMap[sectionKey]
    if (!container || !target) return
    container.scrollTo({
      top: Math.max(0, target.offsetTop - 56),
      behavior: 'smooth',
    })
  })
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
  if (assetUserPickerRef.value && !assetUserPickerRef.value.contains(event.target)) {
    closeAssetUserPicker()
  }
  openEmployeeRowMenuId.value = null
  openAssetRowMenuId.value = null
  openLeaveRowMenuId.value = null
}

function toggleEmployeeRowMenu(id, event) {
  if (openEmployeeRowMenuId.value === id) {
    openEmployeeRowMenuId.value = null
    return
  }
  const rect = event?.currentTarget?.getBoundingClientRect?.()
  if (rect) {
    const menuWidth = 250
    const menuHeight = 150
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight
    const spaceBelow = viewportHeight - rect.bottom
    const shouldOpenUp = spaceBelow < menuHeight + 12

    const top = shouldOpenUp
      ? Math.max(12, rect.top - menuHeight - 8)
      : Math.min(viewportHeight - menuHeight - 12, rect.bottom + 8)

    const left = Math.min(
      viewportWidth - menuWidth - 12,
      Math.max(12, rect.right - menuWidth + 6),
    )

    employeeRowMenuStyle.value = {
      top: `${top}px`,
      left: `${left}px`,
    }
  }
  openEmployeeRowMenuId.value = id
}

function toggleAssetRowMenu(id, event) {
  if (openAssetRowMenuId.value === id) {
    openAssetRowMenuId.value = null
    return
  }
  const rect = event?.currentTarget?.getBoundingClientRect?.()
  if (rect) {
    const menuWidth = 290
    const menuHeight = 210
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight
    const spaceBelow = viewportHeight - rect.bottom
    const shouldOpenUp = spaceBelow < menuHeight + 12

    const top = shouldOpenUp
      ? Math.max(12, rect.top - menuHeight - 8)
      : Math.min(viewportHeight - menuHeight - 12, rect.bottom + 8)

    const left = Math.min(
      viewportWidth - menuWidth - 12,
      Math.max(12, rect.right - menuWidth + 10),
    )

    assetRowMenuStyle.value = {
      top: `${top}px`,
      left: `${left}px`,
    }
  }
  openAssetRowMenuId.value = id
}

function toggleLeaveRowMenu(id, event) {
  if (openLeaveRowMenuId.value === id) {
    openLeaveRowMenuId.value = null
    return
  }
  const rect = event?.currentTarget?.getBoundingClientRect?.()
  if (rect) {
    const menuWidth = 280
    const menuHeight = 266
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight
    const spaceBelow = viewportHeight - rect.bottom
    const shouldOpenUp = spaceBelow < menuHeight + 12
    const top = shouldOpenUp
      ? Math.max(12, rect.top - menuHeight - 8)
      : Math.min(viewportHeight - menuHeight - 12, rect.bottom + 8)
    const left = Math.min(
      viewportWidth - menuWidth - 12,
      Math.max(12, rect.right - menuWidth + 10),
    )
    leaveRowMenuStyle.value = {
      top: `${top}px`,
      left: `${left}px`,
    }
  }
  openLeaveRowMenuId.value = id
}

function openLeaveEdit(leave) {
  openLeaveRowMenuId.value = null
  selectedLeaveRow.value = leave
  applyLeaveForm.value = {
    employee: `${leave.empId} ${leave.employeeName}`,
    leaveType: leave.leaveType,
    startDate: normalizeDateInput(leave.startDate),
    endDate: normalizeDateInput(leave.endDate),
    reason: leave.reason === '--' ? '' : leave.reason,
  }
  showApplyLeaveModal.value = true
}

function openLeaveDetails(leave) {
  selectedLeaveRow.value = leave
  showLeaveDetailModal.value = true
  openLeaveRowMenuId.value = null
}

function closeLeaveDetails() {
  showLeaveDetailModal.value = false
}

function confirmDeleteLeave(leave) {
  leaveRows.value = leaveRows.value.filter((row) => row.id !== leave.id)
  openLeaveRowMenuId.value = null
}

function openApproveLeaveModal(leave) {
  selectedLeaveRow.value = leave
  showLeaveApproveModal.value = true
  showLeaveRejectModal.value = false
  openLeaveRowMenuId.value = null
}

function openRejectLeaveModal(leave) {
  selectedLeaveRow.value = leave
  showLeaveRejectModal.value = true
  showLeaveApproveModal.value = false
  openLeaveRowMenuId.value = null
}

function confirmLeaveApproval() {
  if (!selectedLeaveRow.value) return
  leaveRows.value = leaveRows.value.map((row) =>
    row.id === selectedLeaveRow.value.id ? { ...row, status: 'Approved', approvedBy: 'HR Manager' } : row,
  )
  selectedLeaveRow.value = leaveRows.value.find((row) => row.id === selectedLeaveRow.value.id) || null
  showLeaveApproveModal.value = false
  showLeaveDetailModal.value = false
}

function confirmLeaveRejection() {
  if (!selectedLeaveRow.value) return
  leaveRows.value = leaveRows.value.map((row) =>
    row.id === selectedLeaveRow.value.id ? { ...row, status: 'Rejected', approvedBy: '--' } : row,
  )
  selectedLeaveRow.value = leaveRows.value.find((row) => row.id === selectedLeaveRow.value.id) || null
  showLeaveRejectModal.value = false
  showLeaveDetailModal.value = false
}

function resetLeaveSearchFilters() {
  leaveSearchFilters.value = defaultLeaveSearchFilters()
  selectedLeaveSearchChip.value = 'Rejected'
  searchKeyword.value = ''
}

function applyLeaveSearchFilters() {
  leavePage.value = 1
  showLeaveSearchModal.value = false
}

function exportLeaves() {
  const headers = ['EMP ID', 'Employee Name', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Reason', 'Applied Date', 'Status', 'Approved By']
  const rows = filteredLeaveRows.value.map((row) => [
    row.empId,
    row.employeeName,
    row.leaveType,
    row.startDate,
    row.endDate,
    row.days,
    row.reason,
    row.appliedDate,
    row.status,
    row.approvedBy,
  ])
  const csv = [headers, ...rows]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `leaves-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

function openEditAsset(asset) {
  editingAssetId.value = asset.id
  assetEditForm.value = {
    assetId: asset.assetId || '',
    type: asset.type || '',
    assetName: asset.assetName || '',
    userName: asset.userName || '',
    userRef: asset.userRef || '',
    handoverDate: asset.handoverDate || '',
    brand: asset.brand || '',
    category: asset.category || '',
    handoverTo: asset.handoverTo || '',
    serial: asset.serial || '',
    status: asset.status || '',
  }
  showAssetEditModal.value = true
  openAssetRowMenuId.value = null
}

function openAssignAssetUser() {
  openAssetRowMenuId.value = null
}

function resetAssetSearchFilters() {
  assetSearchFilters.value = defaultAssetSearchFilters()
  selectedAssetSearchChip.value = 'Assigned'
  assetsSearch.value = ''
}

function applyAssetSearchFilters() {
  assetsSearch.value = assetSearchFilters.value.assetName || ''
  assetsPage.value = 1
  showAssetSearchModal.value = false
}

function closeAssetCreateModal() {
  showAssetCreateModal.value = false
}

function resetAssetCreateForm() {
  assetCreateForm.value = defaultAssetCreateForm()
  closeAssetUserPicker()
}

function decrementAssetQty() {
  const current = Number(assetCreateForm.value.qty) || 1
  assetCreateForm.value.qty = Math.max(1, current - 1)
}

function incrementAssetQty() {
  const current = Number(assetCreateForm.value.qty) || 1
  assetCreateForm.value.qty = current + 1
}

function closeAssetUserPicker() {
  showAssetUserPicker.value = false
  assetUserSearchQuery.value = ''
}

async function toggleAssetUserPicker() {
  if (!showAssetUserPicker.value) {
    await fetchAssetResponsiblePersons()
  }
  showAssetUserPicker.value = !showAssetUserPicker.value
}

function selectAssetResponsiblePerson(person) {
  assetCreateForm.value.assetUser = Number(person.id)
  handleAssetResponsiblePersonSelected(person)
  closeAssetUserPicker()
}

function handleAssetResponsiblePersonSelected(person) {
  if (!person) return
  if (!assetCreateForm.value.department && person.department_name) {
    assetCreateForm.value.department = person.department_name
  }
  if (!assetCreateForm.value.branchLocation) {
    assetCreateForm.value.branchLocation =
      person.branch_name || person.branch?.name || person.office_name || person.office?.name || ''
  }
}

async function fetchAssetResponsiblePersons() {
  if (assetResponsiblePersons.value.length) return
  try {
    const response = await api.get('/available-responsible-persons')
    const rawPersons = response?.data?.data || response?.data || []
    assetResponsiblePersons.value = Array.isArray(rawPersons)
      ? rawPersons.map((person) => ({
          ...person,
          id: Number(person.id),
          name: person.name || person.full_name || '-',
          email: person.email || '',
        }))
      : []
  } catch (error) {
    console.error('Failed to load responsible persons for asset picker', error)
  }
}

function saveAssetCreate() {
  const selectedUser = selectedAssetResponsiblePerson.value
  const selectedUserName = selectedUser?.name || selectedUser?.full_name || '-'
  const nextId = assetsRows.value.length ? Math.max(...assetsRows.value.map((row) => Number(row.id) || 0)) + 1 : 1
  assetsRows.value.unshift({
    id: nextId,
    assetId: `#AST-${String(nextId).padStart(3, '0')}`,
    type: assetCreateForm.value.assetType || '-',
    assetName: assetCreateForm.value.assetName || '-',
    userName: selectedUserName,
    userRef: String(nextId).padStart(6, '0'),
    userAvatar: 'https://i.pravatar.cc/80?img=68',
    handoverDate: assetCreateForm.value.handoverDate || '-',
    returnDate: assetCreateForm.value.returnDate || '-',
    brand: assetCreateForm.value.modelNumber || '-',
    category: assetCreateForm.value.condition || '-',
    handoverTo: selectedUserName,
    serial: assetCreateForm.value.serialNumber || '-',
    status: assetCreateForm.value.status || 'Not Assigned',
    branchLocation: assetCreateForm.value.branchLocation || '',
    department: assetCreateForm.value.department || '',
    createdOn: '',
    purchaseDate: assetCreateForm.value.purchaseDate || '',
    supplierName: assetCreateForm.value.supplierName || '',
    condition: assetCreateForm.value.condition || '',
    unitPrice: assetCreateForm.value.unitPrice || '',
    currency: assetCreateForm.value.currency || 'UAE Dirham',
    qty: Number(assetCreateForm.value.qty) || 1,
  })
  assetsPage.value = 1
  closeAssetCreateModal()
  resetAssetCreateForm()
}

function closeApplyLeaveModal() {
  showApplyLeaveModal.value = false
}

function resetApplyLeaveForm() {
  applyLeaveForm.value = defaultApplyLeaveForm()
  applyLeaveAttachment.value = null
}

function handleApplyLeaveFileChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  applyLeaveAttachment.value = file
}

function removeApplyLeaveFile() {
  applyLeaveAttachment.value = null
}

function submitApplyLeave() {
  if (selectedLeaveRow.value) {
    leaveRows.value = leaveRows.value.map((row) =>
      row.id === selectedLeaveRow.value.id
        ? {
            ...row,
            employeeName: String(applyLeaveForm.value.employee || row.employeeName).replace(/^#\w+\s+/, '') || row.employeeName,
            leaveType: applyLeaveForm.value.leaveType || row.leaveType,
            startDate: formatDate(applyLeaveForm.value.startDate) || row.startDate,
            endDate: formatDate(applyLeaveForm.value.endDate) || row.endDate,
            reason: applyLeaveForm.value.reason || '--',
          }
        : row,
    )
    selectedLeaveRow.value = null
  }
  closeApplyLeaveModal()
  resetApplyLeaveForm()
}

function closeAssetEditModal() {
  showAssetEditModal.value = false
  editingAssetId.value = null
}

function saveAssetEdit() {
  const idx = assetsRows.value.findIndex((row) => row.id === editingAssetId.value)
  if (idx === -1) {
    closeAssetEditModal()
    return
  }
  assetsRows.value[idx] = {
    ...assetsRows.value[idx],
    ...assetEditForm.value,
  }
  closeAssetEditModal()
}

function confirmDeleteAsset(asset) {
  const shouldDelete = window.confirm(`Are you sure you want to delete asset "${asset.assetName}"?`)
  if (!shouldDelete) return
  assetsRows.value = assetsRows.value.filter((row) => row.id !== asset.id)
  openAssetRowMenuId.value = null
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

function handleAddEmployeeFileChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  addEmployeeUploadedFile.value = file
}

function triggerProfileImageUpload() {
  profileImageInputRef.value?.click()
}

function handleProfileImageChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  addEmployeeProfileFile.value = file
  addEmployeeProfilePreview.value = URL.createObjectURL(file)
}

function removeAddEmployeeFile() {
  addEmployeeUploadedFile.value = null
}

function resetAddEmployeeForm() {
  addEmployeeForm.value = defaultAddEmployeeForm()
  addEmployeeUploadedFile.value = null
  addEmployeeProfileFile.value = null
  addEmployeeProfilePreview.value = ''
  selectedDocumentType.value = 'Emirates ID'
}

function mapRowToEmployeeForm(row) {
  return {
    ...defaultAddEmployeeForm(),
    full_name: row.name || '',
    nationality: row.nationality || '',
    phone: row.phone || '+971 56125 4568',
    salary_type: row.salary_type || 'Monthly',
    email: row.email || '',
    salary: row.salary || '2000.00',
    branch: row.branch || 'Abu Dhabi Head Office',
    designation: row.designation || '',
    department: row.department || '',
    supervisor: row.supervisor || 'Khalid Al Mazrouei',
    joining_date: normalizeDateInput(row.joiningDate),
    visa_validity: normalizeDateInput(row.visaValidity),
    emirates_id_number: row.emiratesId || '784-1990-1234567-1',
    account_holder_name: row.account_holder_name || row.name || '',
    bank_name: row.bank_name || 'Abu Dhabi Commercial Bank (ADCB)',
    branch_location: row.branch_location || 'Abu Dhabi - Madeena zayd',
    account_number: row.account_number || '009876543210',
    iban_number: row.iban_number || 'AE89 203 000456789123456',
    swift_code: row.swift_code || 'ADCBAEAA456',
    policy_type: row.policy_type || 'Health Insurance',
    insurance_provider: row.insurance_provider || 'Daman Insurance',
    policy_number: row.policy_number || 'DAM-2024-123456',
    insurance_start_date: normalizeDateInput(row.insurance_start_date) || '2024-02-17',
    insurance_expiry_date: normalizeDateInput(row.insurance_expiry_date) || '2025-02-17',
  }
}

function normalizeDateInput(value) {
  if (!value) return ''
  const asDate = new Date(value)
  if (!Number.isNaN(asDate.getTime())) {
    return `${asDate.getFullYear()}-${String(asDate.getMonth() + 1).padStart(2, '0')}-${String(asDate.getDate()).padStart(2, '0')}`
  }
  const parsed = String(value).match(/^(\d{2})\s([A-Za-z]{3})\s(\d{4})$/)
  if (!parsed) return ''
  const months = { Jan: '01', Feb: '02', Mar: '03', Apr: '04', May: '05', Jun: '06', Jul: '07', Aug: '08', Sep: '09', Oct: '10', Nov: '11', Dec: '12' }
  return `${parsed[3]}-${months[parsed[2]] || '01'}-${parsed[1]}`
}

function enrichEmployeeDetail(row) {
  return {
    ...row,
    phone: row.phone || '+971 56125 4568',
    dob: row.dob || '14 Jan 1997',
    address: row.address || 'Al Wahda, Near Bus Station, Abu Dhabi, United Arab Emirates',
    salary_type: row.salary_type || 'Monthly',
    salary: row.salary || '2000.00',
    branch: row.branch || 'Abu Dhabi Head Office',
    supervisor: row.supervisor || 'Khalid Al Mazrouei',
    emiratesId: row.emiratesId || '784-1990-1234567-1',
    laborCard: row.laborCard || '321654987012',
    visaNumber: row.visaNumber || '401/2024/9988776',
    attestedCertificate: row.attestedCertificate || 'No',
    account_holder_name: row.account_holder_name || row.name,
    bank_name: row.bank_name || 'Abu Dhabi Commercial Bank (ADCB)',
    branch_location: row.branch_location || 'Abu Dhabi - Madeena zayd',
    account_number: row.account_number || '009876543210',
    iban_number: row.iban_number || 'AE89 203 000456789123456',
    swift_code: row.swift_code || 'ADCBAEAA456',
    policy_type: row.policy_type || 'Health Insurance',
    insurance_provider: row.insurance_provider || 'Daman Insurance',
    policy_number: row.policy_number || 'DAM-2024-123456',
    insurance_start_date: row.insurance_start_date || '17 Feb 2024',
    insurance_expiry_date: row.insurance_expiry_date || '17 Feb 2025',
  }
}

function openEditEmployee(row) {
  isEditEmployeeMode.value = true
  editingEmployeeId.value = row.id
  addEmployeeForm.value = mapRowToEmployeeForm(enrichEmployeeDetail(row))
  addEmployeeProfilePreview.value = row.avatar || ''
  showAddEmployeeModal.value = true
  openEmployeeRowMenuId.value = null
}

function openEmployeeDetails(row) {
  selectedEmployeeDetail.value = enrichEmployeeDetail(row)
  employeeDetailTab.value = 'company'
  activeTab.value = 'Employee Details'
  openEmployeeRowMenuId.value = null
}

function saveEmployeeForm() {
  if (isEditEmployeeMode.value && editingEmployeeId.value) {
    const idx = employeesDirectory.value.findIndex((e) => String(e.id) === String(editingEmployeeId.value))
    if (idx >= 0) {
      employeesDirectory.value[idx] = {
        ...employeesDirectory.value[idx],
        name: addEmployeeForm.value.full_name,
        email: addEmployeeForm.value.email,
        designation: addEmployeeForm.value.designation,
        department: addEmployeeForm.value.department,
        nationality: addEmployeeForm.value.nationality,
        salary_type: addEmployeeForm.value.salary_type,
        salary: addEmployeeForm.value.salary,
        branch: addEmployeeForm.value.branch,
        supervisor: addEmployeeForm.value.supervisor,
        joiningDate: formatDate(addEmployeeForm.value.joining_date),
        visaValidity: formatDate(addEmployeeForm.value.visa_validity),
        avatar: addEmployeeProfilePreview.value || employeesDirectory.value[idx].avatar,
      }
      if (selectedEmployeeDetail.value && String(selectedEmployeeDetail.value.id) === String(editingEmployeeId.value)) {
        selectedEmployeeDetail.value = enrichEmployeeDetail(employeesDirectory.value[idx])
      }
    }
  }
  closeAddEmployeeModal()
}

function closeAddEmployeeModal() {
  showAddEmployeeModal.value = false
  isEditEmployeeMode.value = false
  editingEmployeeId.value = null
  resetAddEmployeeForm()
}

function confirmDeleteEmployee(row) {
  const shouldDelete = window.confirm(`Are you sure you want to delete employee "${row.name}"?`)
  if (!shouldDelete) return
  employeesDirectory.value = employeesDirectory.value.filter((employee) => String(employee.id) !== String(row.id))
  if (selectedEmployeeDetail.value && String(selectedEmployeeDetail.value.id) === String(row.id)) {
    selectedEmployeeDetail.value = null
    activeTab.value = 'Employees'
  }
  openEmployeeRowMenuId.value = null
}

function openSectionEdit(sectionKey) {
  if (!selectedEmployeeDetail.value) return
  editingSection.value = sectionKey
  sectionEditForm.value = { ...selectedEmployeeDetail.value }
  showSectionEditModal.value = true
}

function saveSectionEdit() {
  if (!selectedEmployeeDetail.value) return
  selectedEmployeeDetail.value = { ...selectedEmployeeDetail.value, ...sectionEditForm.value }
  const idx = employeesDirectory.value.findIndex((e) => String(e.id) === String(selectedEmployeeDetail.value.id))
  if (idx >= 0) {
    employeesDirectory.value[idx] = { ...employeesDirectory.value[idx], ...sectionEditForm.value, name: sectionEditForm.value.name || selectedEmployeeDetail.value.name }
  }
  showSectionEditModal.value = false
}

function syncMobileViewport() {
  isMobileViewport.value = typeof window !== 'undefined' && window.matchMedia('(max-width: 768px)').matches
  if (!isMobileViewport.value && activeTab.value !== 'Overview') {
    openHeaderMenu.value = null
  }
}

// ========== ON MOUNTED ==========
onMounted(async () => {
  console.log('BASE URL:', api.defaults.baseURL)
  const d = new Date()
  dateFilter.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  
  // Fetch real employees first
  await fetchRealEmployees()
  
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
.employee-detail-action-chip {
  border: 1px solid #e8eaf1;
  background: #fff;
  color: #111827;
  border-radius: 999px;
  height: 36px;
  padding: 0 14px;
  font-size: 12px;
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
.assets-overview-card {
  padding: 12px;
}
.assets-search-wrap {
  padding: 0 10px;
  min-width: 290px;
  justify-content: space-between;
}
.assets-search-wrap span {
  flex: 1;
  text-align: left;
  font-size: 12px;
  color: #9ca3af;
}
.assets-table-wrap {
  margin-top: 10px;
  border: 1px solid #edf1f8;
  border-radius: 12px;
  overflow-x: auto;
  overflow-y: visible;
  max-width: 100%;
}
.assets-table {
  min-width: 2280px;
}
.assets-table th,
.assets-table td {
  white-space: nowrap;
  vertical-align: middle;
}
.assets-table .col-asset-id { width: 140px; min-width: 140px; }
.assets-table .col-asset-type { width: 160px; min-width: 160px; }
.assets-table .col-asset-name { width: 260px; min-width: 260px; }
.assets-table .col-asset-user { width: 260px; min-width: 260px; }
.assets-table .col-handover { width: 180px; min-width: 180px; }
.assets-table .col-action { width: 90px; min-width: 90px; text-align: center; }
.assets-table .asset-extra-col { width: 280px; min-width: 280px; }
.assets-table .sticky-action-col {
  position: sticky;
  right: 0;
  z-index: 6;
  background: #fff;
  box-shadow: -10px 0 16px -12px rgba(15, 23, 42, 0.35);
}
.assets-table thead .sticky-action-col {
  background: #fafbfe;
  z-index: 7;
}
.assets-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #fafbfe;
}
.assets-table .employee-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}
.assets-table .employee-cell span {
  display: inline-flex;
  flex-direction: column;
  line-height: 1.25;
}
.assets-table .employee-cell span small {
  font-size: 11px;
  color: #9ca3af;
}
.asset-row-menu {
  position: fixed;
  width: 220px;
  background: #fff;
  border: 1px solid #e7eaf1;
  border-radius: 14px;
  box-shadow: 0 10px 20px rgba(15, 23, 42, 0.14);
  padding: 8px;
  z-index: 21000;
}
.asset-row-menu-item {
  width: 100%;
  border: none;
  background: #fff;
  border-radius: 10px;
  height: 38px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #6b7280;
  padding: 0 10px;
}
.asset-row-menu-item svg {
  font-size: 18px;
}
.asset-row-menu-item.active {
  color: #111827;
  background: #f7f7f8;
}
.asset-row-menu-item.active svg {
  color: #f59e0b;
}
.asset-row-menu-item.danger {
  color: #ef4444;
}
.leave-overview-card {
  padding: 12px;
}
.leave-table-wrap {
  margin-top: 10px;
  border: 1px solid #edf1f8;
  border-radius: 12px;
  overflow-x: auto;
  overflow-y: visible;
}
.leave-table {
  min-width: 1320px;
}
.leave-table th,
.leave-table td {
  white-space: nowrap;
  vertical-align: middle;
  font-size: 14px;
  color: #2b3240;
}
.leave-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #fafbfe;
  font-size: 13px;
  font-weight: 600;
  color: #6b7280;
}
.leave-table .col-leave-id { width: 120px; min-width: 120px; }
.leave-table .col-leave-name { width: 260px; min-width: 260px; }
.leave-table .col-leave-type { width: 130px; min-width: 130px; }
.leave-table .col-leave-date { width: 130px; min-width: 130px; }
.leave-table .col-leave-days { width: 80px; min-width: 80px; text-align: center; }
.leave-table .col-leave-reason { width: 260px; min-width: 260px; color: #6b7280; }
.leave-table .sticky-action-col {
  position: sticky;
  right: 0;
  z-index: 6;
  background: #fff;
  box-shadow: -10px 0 16px -12px rgba(15, 23, 42, 0.35);
}
.leave-table thead .sticky-action-col {
  background: #fafbfe;
  z-index: 7;
}
.leave-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  font-size: 11px;
  line-height: 1;
  padding: 4px 8px;
}
.leave-status-badge i {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
}
.leave-status-approved { color: #16a34a; }
.leave-status-pending { color: #f59e0b; }
.leave-status-rejected { color: #ef4444; }
.leave-row-menu {
  position: fixed;
  width: 260px;
  background: #fff;
  border: 1px solid #e7eaf1;
  border-radius: 16px;
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.15);
  padding: 8px;
  z-index: 21000;
}
.leave-row-menu-item {
  width: 100%;
  border: none;
  background: #fff;
  border-radius: 12px;
  min-height: 44px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: #6b7280;
  padding: 0 12px;
}
.leave-row-menu-item svg {
  font-size: 22px;
}
.leave-row-menu-item.active {
  background: #f7f7f8;
  color: #111827;
}
.leave-row-menu-item.active svg { color: #f59e0b; }
.leave-row-menu-item.danger,
.leave-row-menu-item.reject { color: #ef4444; }
.leave-row-menu-item.approve { color: #16a34a; }
.leave-search-modal {
  width: min(1100px, 96vw);
  min-height: 560px;
  grid-template-columns: 180px minmax(0, 1fr);
}
.leave-detail-modal {
  width: min(520px, 94vw);
  background: #fff;
  border-radius: 14px;
  padding: 16px;
  position: relative;
}
.leave-detail-modal h6 {
  margin: 0 0 14px;
  font-size: 15px !important;
  font-weight: 700;
  color: #111827;
}
.leave-detail-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  border: 1px solid #eef2f7;
  border-radius: 10px;
  padding: 10px;
}
.leave-detail-grid p {
  margin: 0;
  display: grid;
  gap: 4px;
}
.leave-detail-grid span {
  font-size: 11px;
  color: #9ca3af;
}
.leave-detail-grid strong {
  font-size: 11px;
  color: #111827;
  font-weight: 600;
}
.leave-txt-approved { color: #16a34a !important; }
.leave-txt-pending { color: #f59e0b !important; }
.leave-txt-rejected { color: #ef4444 !important; }
.leave-detail-reason {
  margin-top: 10px;
  border: 1px solid #eef2f7;
  border-radius: 10px;
  padding: 10px;
}
.leave-detail-reason span {
  font-size: 11px;
  color: #9ca3af;
}
.leave-detail-reason p {
  margin: 4px 0 0;
  font-size: 11px;
  color: #111827;
}
.leave-detail-actions {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px;
}
.leave-approve-btn,
.leave-reject-btn {
  border: none;
  border-radius: 999px;
  min-height: 34px;
  min-width: 170px;
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.leave-approve-btn { background: #22c55e; color: #fff; }
.leave-reject-btn { background: #ef4444; color: #fff; }
.leave-confirm-modal {
  width: min(520px, 94vw);
  background: #fff;
  border-radius: 12px;
  padding: 16px 20px 20px;
  position: relative;
  text-align: center;
}
.confirm-icon {
  width: 78px;
  height: 78px;
  border-radius: 50%;
  margin: 0 auto 14px;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 44px;
}
.confirm-icon.success {
  background: radial-gradient(circle at center, #4ade80 0%, #16a34a 70%);
  box-shadow: 0 0 24px rgba(34, 197, 94, 0.5);
}
.confirm-icon.danger {
  background: radial-gradient(circle at center, #fb7185 0%, #ef4444 70%);
  box-shadow: 0 0 24px rgba(239, 68, 68, 0.45);
}
.leave-confirm-modal h6 {
  margin: 0 0 8px;
  font-size: 15px !important;
  color: #111827;
  font-weight: 700;
}
.leave-confirm-modal p {
  margin: 0 0 14px;
  color: #111827;
  font-size: 13px;
  line-height: 1.6;
}
.leave-confirm-btn {
  border: none;
  border-radius: 999px;
  background: #f59e0b;
  color: #fff;
  font-weight: 600;
  min-height: 44px;
  width: 100%;
}
.leave-announcement-card {
  border: 1px dashed #d7deea;
  border-radius: 12px;
  padding: 18px;
  color: #6b7280;
}
.asset-search-modal {
  width: min(1100px, 96vw);
  min-height: 560px;
  grid-template-columns: 180px minmax(0, 1fr);
}
.asset-search-left {
  background: #f8fafc;
  border-right: 1px solid #eef2f7;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.asset-search-chip {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 999px;
  min-height: 34px;
  text-align: left;
  padding: 0 12px;
  font-size: 13px;
  color: #6b7280;
}
.asset-search-chip.active {
  background: #0b1459;
  border-color: #0b1459;
  color: #fff;
}
.asset-search-right {
  padding: 16px;
  display: grid;
  gap: 12px;
}
.asset-search-section {
  border: 1px solid #edf1f6;
  border-radius: 12px;
  background: #fff;
  padding: 12px;
}
.asset-search-section h6 {
  margin: 0 0 10px;
  font-size: 15px !important;
  font-weight: 600;
  color: #111827;
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
.employee-row-action-cell { position: relative; overflow: visible; }
.employee-row-menu {
  position: fixed;
  width: 250px;
  background: #fff;
  border: 1px solid #e7eaf1;
  border-radius: 14px;
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
  padding: 10px;
  z-index: 20000;
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
.section-edit-modal {
  width: min(720px, 96vw);
  min-height: auto;
  display: block;
}
.asset-edit-modal {
  width: min(860px, 96vw);
}
.asset-edit-modal .add-grid-two {
  gap: 10px 12px;
}
.asset-edit-modal .add-field input {
  background: #fff;
}
.asset-create-modal {
  width: min(1320px, 96vw);
}
.asset-create-modal .add-employee-section h6 {
  font-size: 15px !important;
}
.asset-create-modal textarea {
  width: 100%;
  min-height: 96px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 12px;
  color: #4b5563;
  resize: vertical;
}
.asset-create-modal .add-field-full {
  grid-column: 1 / -1;
}
.asset-create-modal .add-field :deep(.vs__dropdown-toggle) {
  height: 38px;
  min-height: 38px;
}
.asset-create-modal .add-field :deep(.vs__actions) {
  height: 100%;
  min-height: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.asset-create-modal .add-field :deep(.vs__open-indicator) {
  position: static !important;
  top: auto !important;
  margin: 0 !important;
  transform: none !important;
  width: 12px;
  height: 12px;
  line-height: 1;
  color: #9ca3af;
}
.asset-create-modal .asset-user-picker-field {
  position: relative;
}
.asset-create-modal .asset-user-trigger {
  width: 100%;
  height: 38px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  background: #fff;
  padding: 0 10px 0 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: #4b5563;
}
.asset-create-modal .asset-user-dropdown {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 6px);
  z-index: 40;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  padding: 10px;
}
.asset-create-modal .asset-user-dropdown-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 8px;
  margin-bottom: 8px;
}
.asset-create-modal .asset-user-close-btn {
  background: transparent;
  border: none;
  color: #0f172a;
  font-size: 18px;
}
.asset-create-modal .asset-user-search-input {
  width: 100%;
  height: 38px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  padding: 0 38px 0 14px;
  font-size: 12px;
}
.asset-create-modal .asset-user-list-scroll {
  max-height: 280px;
  overflow-y: auto;
  padding-right: 4px;
}
.asset-create-modal .asset-user-item {
  width: 100%;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 10px;
  text-align: left;
  border-radius: 8px;
  padding: 8px;
}
.asset-create-modal .asset-user-item:hover {
  background: #f8fafc;
}
.asset-create-modal .asset-user-item.selected {
  background: #fff7e6;
}
.asset-create-modal .asset-user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  object-fit: cover;
}
.asset-create-modal .asset-user-info {
  min-width: 0;
}
.asset-create-modal .asset-user-head {
  display: flex;
  align-items: center;
  gap: 8px;
}
.asset-create-modal .asset-user-name {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}
.asset-create-modal .asset-price-group {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 140px;
}
.asset-create-modal .asset-price-group input {
  border-radius: 8px 0 0 8px;
}
.asset-create-modal .asset-price-group select {
  border: 1px solid #d9dee7;
  border-left: none;
  border-radius: 0 8px 8px 0;
  padding: 0 10px;
  font-size: 12px;
  color: #4b5563;
  background: #fff;
}
.asset-create-modal .asset-qty-group {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 24px 24px;
  gap: 4px;
  align-items: center;
}
.asset-create-modal .asset-qty-btn {
  width: 24px;
  height: 24px;
  border: 1px solid #d9dee7;
  border-radius: 6px;
  background: #fff;
  color: #6b7280;
  line-height: 1;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.leave-apply-modal {
  width: min(760px, 94vw);
  max-height: calc(100vh - 40px);
}
.leave-apply-modal .add-employee-head h6,
.leave-apply-modal .add-employee-section h6 {
  font-size: 15px !important;
}
.leave-apply-modal .add-employee-body {
  max-height: calc(100vh - 180px);
  overflow-y: auto;
}
.leave-apply-modal .add-field textarea {
  width: 100%;
  min-height: 140px;
  border: 1px solid #d9dee7;
  border-radius: 12px;
  padding: 12px;
  font-size: 13px;
  color: #4b5563;
  resize: vertical;
}
.leave-apply-modal .add-field :deep(.vs__dropdown-toggle) {
  height: 50px;
  min-height: 50px;
  border-radius: 14px;
}
.leave-apply-modal .add-field :deep(.vs__open-indicator) {
  width: 11px;
  height: 11px;
  margin: 0 !important;
  transform: none !important;
}
.leave-apply-modal .add-field :deep(.vs__actions) {
  height: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.leave-upload-dropzone {
  border-radius: 14px;
}

.leave-apply-modal .add-grid-two {
  grid-template-columns: 1fr 1fr;
}
.employee-detail-page {
  background: linear-gradient(135deg, #0c1b88 0%, #0d3ea4 55%, #0a60b8 100%);
  border: 1px solid rgba(191, 213, 255, 0.6);
}
.employee-detail-breadcrumb {
  color: #d1dcff;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}
.employee-detail-layout {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 12px;
}
.employee-detail-side,
.employee-detail-main {
  border: 1px solid #dce7ff;
  border-radius: 12px;
  background: #fff;
  padding: 12px;
}
.employee-detail-main {
  max-height: calc(100vh - 210px);
  overflow: auto;
}
.employee-detail-user-head {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr) 22px;
  gap: 8px;
  align-items: center;
  padding-bottom: 10px;
  border-bottom: 1px solid #edf1f6;
}
.employee-detail-user-head img {
  width: 52px;
  height: 52px;
  border-radius: 999px;
  object-fit: cover;
}
.employee-detail-user-head strong { font-size: 20px; color: #111827; }
.employee-detail-user-head p { margin: 2px 0 0; font-size: 12px; color: #6b7280; }
.mini-edit-btn {
  border: none;
  background: #fff8e8;
  color: #d39b1a;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.employee-detail-side-list {
  margin-top: 10px;
  display: grid;
  gap: 8px;
}
.employee-detail-side-list p,
.employee-mini-grid p {
  margin: 0;
  display: grid;
  gap: 2px;
}
.employee-detail-side-list span,
.employee-mini-grid span { font-size: 12px; color: #6b7280; }
.employee-detail-side-list strong,
.employee-mini-grid strong { font-size: 13px; color: #111827; font-weight: 600; }
.employee-detail-tabs {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 10px;
  padding-bottom: 8px;
  background: #fff;
  border-bottom: 1px solid #edf1f6;
}
.employee-detail-tabs button {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #fff;
  color: #4b5563;
  font-size: 12px;
  padding: 6px 12px;
}
.employee-detail-tabs button.active {
  background: #040a53;
  border-color: #040a53;
  color: #fff;
}
.employee-detail-section {
  border: 1px solid #edf1f6;
  border-radius: 10px;
  background: #fff;
  padding: 10px;
}
.employee-detail-section + .employee-detail-section { margin-top: 10px; }
.employee-detail-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.employee-detail-section-head h6 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}
.employee-mini-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px 14px;
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
  padding-right: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}
.employee-filter-field :deep(.vs__clear) {
  display: none !important;
}
.employee-filter-field :deep(.vs__open-indicator) {
  margin-top: 0;
  transform: none;
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
.add-employee-overlay {
  align-items: flex-start;
  padding: 16px 0;
  overflow-y: auto;
}
.add-employee-modal {
  width: min(1320px, 96vw);
  max-height: calc(100vh - 32px);
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e6eaf2;
  display: flex;
  flex-direction: column;
}
.add-employee-head {
  padding: 12px 18px;
  border-bottom: 1px solid #edf1f6;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.add-employee-modal h6,
.add-employee-head h6 {
  margin: 0;
  font-size: 15px !important;
  line-height: 1.25 !important;
  font-weight: 600;
  color: #111827;
}
.add-employee-close {
  border: none;
  background: transparent;
  color: #6b7280;
}
.add-employee-body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow: auto;
}
.add-employee-section {
  border: 1px solid #edf1f6;
  border-radius: 10px;
  padding: 12px;
}
.add-employee-section h6 {
  margin: 0 0 10px;
  font-size: 15px !important;
  line-height: 1.25 !important;
  font-weight: 600;
  color: #111827;
}
.add-employee-profile-grid {
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr);
  gap: 14px;
}
.profile-photo-block {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.profile-photo-avatar {
  width: 110px;
  height: 110px;
  border-radius: 999px;
  background: #eff2f6;
  color: #b8c0cc;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 44px;
  overflow: hidden;
}
.profile-photo-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.profile-photo-edit-btn {
  position: absolute;
  right: 6px;
  bottom: 28px;
  width: 24px;
  height: 24px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff7e8;
  color: #f59e0b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.profile-photo-block span {
  font-size: 12px;
  color: #6b7280;
}
.profile-form-grid,
.add-grid-two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 14px;
}
.add-field label {
  display: block;
  margin: 0 0 5px;
  font-size: 12px;
  font-weight: 600;
  color: #1f2937;
}
.add-field input {
  width: 100%;
  height: 36px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  padding: 0 12px;
  font-size: 12px;
  color: #4b5563;
}
.add-field input::placeholder,
.add-field :deep(.vs__search::placeholder),
.add-field :deep(.vs__selected),
.add-field :deep(.vs__dropdown-option) {
  font-size: 11px;
  color: #9ca3af;
}
.add-field :deep(.vs__dropdown-toggle) {
  height: 36px;
  min-height: 36px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  padding: 0 8px 0 10px;
  display: flex;
  align-items: center;
}
.add-field :deep(.vs__selected-options) {
  display: inline-flex;
  align-items: center;
  min-height: 100%;
}
.add-field :deep(.vs__actions) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding-right: 4px;
}
.add-field :deep(.vs__clear) {
  display: none !important;
}
.add-field :deep(.vs__open-indicator) {
  margin-top: 0;
  transform: none;
  color: #9ca3af;
}
.salary-input-group {
  display: grid;
  grid-template-columns: 1fr auto;
}
.salary-input-group span {
  border: 1px solid #d9dee7;
  border-left: none;
  border-radius: 0 8px 8px 0;
  padding: 0 10px;
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  color: #6b7280;
  background: #fff;
}
.salary-input-group input {
  border-radius: 8px 0 0 8px;
}
.doc-chip-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.doc-chip {
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 11px;
  color: #6b7280;
}
.doc-chip.active {
  background: #02014f;
  border-color: #02014f;
  color: #fff;
}
.upload-dropzone {
  margin-top: 8px;
  border: 1px dashed #d9dee7;
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.upload-dropzone strong {
  display: block;
  font-size: 13px;
  color: #111827;
  font-weight: 500;
}
.upload-dropzone small {
  font-size: 11px;
  color: #9ca3af;
}
.select-file-btn {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 12px;
  color: #111827;
  background: #fff;
  cursor: pointer;
}
.uploaded-doc-card {
  margin-top: 10px;
  width: 130px;
  border: 1px solid #edf1f6;
  border-radius: 10px;
  padding: 10px;
  position: relative;
}
.uploaded-doc-card > svg {
  font-size: 20px;
  color: #f59e0b;
}
.uploaded-doc-card p {
  margin: 6px 0 2px;
  font-size: 12px;
  color: #111827;
}
.uploaded-doc-card small {
  color: #9ca3af;
  font-size: 11px;
}
.uploaded-doc-card button {
  position: absolute;
  top: 4px;
  right: 4px;
  border: none;
  background: transparent;
  color: #ef4444;
  padding: 0;
}
.add-employee-footer {
  padding: 12px 16px 14px;
  border-top: 1px solid #edf1f6;
  display: flex;
  justify-content: center;
  gap: 12px;
}
.add-employee-clear-btn,
.add-employee-save-btn {
  min-width: 90px;
  height: 36px;
  border-radius: 999px;
  border: none;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  text-align: center;
  padding: 0 16px;
}
.add-employee-clear-btn {
  background: #f3f4f6;
  color: #111827;
}
.add-employee-save-btn {
  background: #02014f;
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
  font-size: 15px;
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
  font-size: 15px;
  font-weight: 600;
}

.employee-filter-right > h6 {
  font-size: 15px;
}

.form-select {
  background-position: right 0.7rem center;
  background-size: 10px 10px;
}

:deep(.vs__actions) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}

:deep(.vs__open-indicator) {
  width: 10px;
  height: 10px;
  display: block;
  flex-shrink: 0;
  align-self: center;
  transform-origin: center;
  margin-top: 0 !important;
  transform: none !important;
}

:deep(.vs__dropdown-toggle) {
  min-height: 36px;
}

:deep(.vs__selected-options) {
  align-items: center;
}

.add-field input[type='date'],
.employee-filter-field input[type='date'],
.hr-date-input,
.leave-apply-modal .add-field input[type='date'],
.asset-create-modal .add-field input[type='date'],
.asset-search-modal .add-field input[type='date'] {
  appearance: auto;
  -webkit-appearance: auto;
  -moz-appearance: auto;
  position: relative;
  padding-right: 34px;
  cursor: pointer;
}

.add-field input[type='date']::-webkit-calendar-picker-indicator,
.employee-filter-field input[type='date']::-webkit-calendar-picker-indicator,
.hr-date-input::-webkit-calendar-picker-indicator,
.leave-apply-modal .add-field input[type='date']::-webkit-calendar-picker-indicator,
.asset-create-modal .add-field input[type='date']::-webkit-calendar-picker-indicator,
.asset-search-modal .add-field input[type='date']::-webkit-calendar-picker-indicator {
  opacity: 1;
  cursor: pointer;
  width: 16px;
  height: 16px;
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
  .leave-apply-modal .add-grid-two {
    grid-template-columns: 1fr;
  }
  .leave-detail-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .leave-search-modal {
    grid-template-columns: 1fr;
  }
  .leave-search-modal .asset-search-left {
    border-right: none;
    border-bottom: 1px solid #eef2f7;
    flex-direction: row;
    flex-wrap: wrap;
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
  .add-employee-modal {
    width: 96vw;
  }
  .profile-form-grid,
  .add-grid-two,
  .add-employee-profile-grid {
    grid-template-columns: 1fr;
  }
  .profile-photo-block {
    align-items: flex-start;
  }
  .employee-detail-layout,
  .employee-mini-grid {
    grid-template-columns: 1fr;
  }
}
</style>

