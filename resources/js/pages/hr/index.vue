<template>
  <div class="dashboard-main-body hr-screen">
    <div class="hr-frame">
      <div class="hr-topbar" ref="hrTopbarRef">
        <div v-if="isMobileViewport" class="hr-mobile-head">
          <button type="button" class="hr-mobile-back-btn" @click="activeTab = 'Employees'">
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
              @click.stop="onHeaderTabClick(tab, $event)"
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
        <div v-if="!isMobileViewport" class="hr-topbar-actions">
          <template v-if="activeTab === 'Overview'">
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
          <template v-else-if="activeTab === 'Document Requests'">
            <button type="button" class="hr-generate-btn" @click="openRequestDocumentModal">
              Request Document
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
            <button type="button" class="hr-generate-btn" @click="openAssetsPrimaryAction">
              Add New Asset
              <iconify-icon icon="lucide:plus" />
            </button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
          <template v-else-if="activeTab === 'Leave / Attendance'">
            <button type="button" class="hr-generate-btn" @click="openLeaveAttendancePrimaryAction">
              {{ leaveSectionMode === 'attendance' ? 'Create Attendance' : leaveSectionMode === 'announcements' ? 'Add Announcements' : 'Generate Leave' }}
              <iconify-icon icon="lucide:plus" />
            </button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
          <template v-else-if="activeTab === 'Career'">
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
            <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
          </template>
          <template v-else>
            <button type="button" class="hr-generate-btn">
              Generate
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

      <div class="hr-content-card hr-employees-card" v-else-if="activeTab === 'Employees'">
        <div class="hr-content-shell overview-shell hr-employees-shell">
          <EmployeesManagement :key="employeesRefreshKey" embedded @add="showAddEmployeeModal = true" @edit="openEditEmployee" @delete="deleteEmployee"  @view="openEmployeeDetails" />
        </div>
      </div>

      <div class="hr-content-card" v-else-if="activeTab === 'Employee Details'">
        <div class="hr-content-shell employee-detail-page" v-if="selectedEmployeeDetail && employeeDetailView === 'details'">
          <div class="employee-detail-breadcrumb-row">
            <div class="employee-detail-breadcrumb">Employee <iconify-icon icon="lucide:chevron-right" /> Manage Employee <iconify-icon icon="lucide:chevron-right" /> {{ selectedEmployeeDetail.name }}</div>
            <button type="button" class="employee-detail-action-chip employee-detail-action-chip--light" @click="employeeDetailView = 'requested-documents'">
              <span class="request-doc-btn-text">Requested Documents</span>
              <iconify-icon icon="lucide:file-text" />
            </button>
          </div>
          <div class="employee-detail-title-row mb-2">
            <h6 class="overview-section-title employee-detail-page-title mb-0">Employee Details</h6>
          </div>
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
                <p><span>Email</span><strong>{{ selectedEmployeeDetail.email_personal || selectedEmployeeDetail.email }}</strong></p>
                <p><span>Phone Number</span><strong>{{ selectedEmployeeDetail.phone_company || selectedEmployeeDetail.phone }}</strong></p>
                <p><span>Phone Country Phone Number</span><strong>{{ selectedEmployeeDetail.phone_personal || selectedEmployeeDetail.phone }}</strong></p>
                <p><span>Date Of Birth</span><strong>{{ selectedEmployeeDetail.dob }}</strong></p>
                <p><span>Address Inside UAE</span><strong>{{ selectedEmployeeDetail.address_inside_uae || selectedEmployeeDetail.address }}</strong></p>
                <p><span>Address Outside UAE</span><strong>{{ selectedEmployeeDetail.address_outside_uae || selectedEmployeeDetail.address }}</strong></p>
                <p><span>Nationality</span><strong>{{ selectedEmployeeDetail.nationality }}</strong></p>
                <p><span>Salary Type</span><strong>{{ selectedEmployeeDetail.salary_type }}</strong></p>
                <p><span>Basic Salary</span><strong>{{ selectedEmployeeDetail.salary }} AED</strong></p>
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

        <div class="hr-content-shell employee-detail-page" v-else-if="selectedEmployeeDetail && employeeDetailView === 'requested-documents'">
          <div class="employee-detail-breadcrumb-row">
            <div class="employee-detail-breadcrumb">
              Employee <iconify-icon icon="lucide:chevron-right" /> Manage Employee <iconify-icon icon="lucide:chevron-right" /> {{ selectedEmployeeDetail.name }} <iconify-icon icon="lucide:chevron-right" /> Requested Documents
            </div>
            <button type="button" class="employee-detail-action-chip employee-detail-action-chip--light" @click="openRequestDocumentModal">
              Request New Document
              <iconify-icon icon="lucide:file-plus-2" />
            </button>
          </div>
          <div class="employee-detail-title-row mb-2">
            <h6 class="overview-section-title mb-0">Requested Documents</h6>
          </div>
          <div class="requested-documents-card">
            <h6>My Documents</h6>
            <div class="requested-document-list">
              <div v-for="doc in requestedDocuments" :key="doc.id" class="requested-document-row">
                <div>
                  <strong>{{ doc.documentType }}</strong>
                  <small>Document Name</small>
                </div>
                <div>
                  <strong>{{ doc.description || '--' }}</strong>
                  <small>Description</small>
                </div>
                <div>
                  <strong>{{ doc.requestedDate }}</strong>
                  <small>Requested On</small>
                </div>
                <div>
                  <strong :class="`doc-status-${String(doc.status).toLowerCase()}`">{{ doc.status }}</strong>
                  <small>Status</small>
                </div>
                <div>
                  <strong>{{ doc.rejectionReason || '--' }}</strong>
                  <small>Rejection Reason</small>
                </div>
                <div class="requested-document-actions">
                  <button type="button" class="row-action-btn" @click="openDocumentDetail(doc)">
                    <iconify-icon icon="lucide:eye" />
                  </button>
                  <button v-if="doc.status === 'Pending'" type="button" class="row-action-btn" @click="openEditDocumentRequest(doc)">
                    <iconify-icon icon="lucide:pencil" />
                  </button>
                  <button type="button" class="row-action-btn" @click="deleteRequestedDocument(doc)">
                    <iconify-icon icon="lucide:trash-2" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="hr-content-card" v-else-if="activeTab === 'Document Requests'">
          <div class="hr-content-shell overview-shell">
            <div class="employee-overview-head">
              <h6 class="overview-section-title">Document Requests</h6>
              <div class="employee-overview-actions">
                <div class="hr-search-wrap" style="min-width:260px;">
                  <iconify-icon icon="lucide:search" class="hr-search-icon" />
                  <input v-model="documentRequestsSearch" type="text" class="hr-search-input" placeholder="Search employee or document type" />
                </div>
              </div>
            </div>

            <div class="requested-documents-card mt-2">
              <div class="requested-document-list">
                <div
                  v-for="doc in filteredDocumentRequests"
                  :key="`global-doc-${doc.id}`"
                  class="requested-document-row requested-document-row--with-employee"
                >
                  <div>
                    <strong>{{ doc.employeeName }}</strong>
                    <small>Employee</small>
                  </div>
                  <div>
                    <strong>{{ doc.documentType }}</strong>
                    <small>Document Name</small>
                  </div>
                  <div>
                    <strong>{{ doc.description || '--' }}</strong>
                    <small>Description</small>
                  </div>
                  <div>
                    <strong>{{ doc.requestedDate }}</strong>
                    <small>Requested On</small>
                  </div>
                  <div>
                    <strong :class="`doc-status-${String(doc.status).toLowerCase()}`">{{ doc.status }}</strong>
                    <small>Status</small>
                  </div>
                  <div>
                    <strong>{{ doc.rejectionReason || '--' }}</strong>
                    <small>Rejection Reason</small>
                  </div>
                  <div class="requested-document-actions">
                    <button type="button" class="row-action-btn" @click="openDocumentDetail(doc)">
                      <iconify-icon icon="lucide:eye" />
                    </button>
                    <button v-if="doc.status === 'Pending'" type="button" class="row-action-btn" @click="openEditDocumentRequest(doc)">
                      <iconify-icon icon="lucide:pencil" />
                    </button>
                    <button type="button" class="row-action-btn" @click="deleteRequestedDocument(doc)">
                      <iconify-icon icon="lucide:trash-2" />
                    </button>
                  </div>
                </div>
                <div v-if="!loadingRequestedDocuments && !filteredDocumentRequests.length" class="text-center text-muted py-4">
                  No document requests found
                </div>
              </div>
            </div>
          </div>
        </div>

      <div class="hr-content-card hr-la-card" v-else-if="activeTab === 'Leave / Attendance'">
        <div class="hr-content-shell hr-la-shell">
          <LeaveAttendanceManagement
            v-if="leaveSectionMode === 'leave' || leaveSectionMode === 'attendance'"
            :key="leaveSectionMode"
            embedded
            :initial-view="leaveSectionMode === 'attendance' ? 'records' : 'leave'"
            @apply-leave="showApplyLeaveModal = true"
            @create-attendance="showCreateAttendanceModal = true"
            @edit-attendance="openAttendanceEdit"
            @view-history="openAttendanceDetails"
            @view-leave="(leave) => openLeaveDetails(mapLeaveForModal(leave))"
          />
          <template v-else-if="leaveSectionMode === 'announcements'">
            <div class="employee-overview-card leave-overview-card announcement-overview-card">
              <div class="employee-overview-head">
                <h6 class="overview-section-title">Manage Announcements</h6>
                <div class="employee-overview-actions">
                  <button type="button" class="employee-export-btn" @click="exportAnnouncements">
                    Export Excel
                    <iconify-icon icon="lucide:file-down" />
                  </button>
                </div>
              </div>

              <div class="leave-table-wrap announcement-table-wrap">
                <table class="table leave-table align-middle mb-0 announcement-table">
                  <thead>
                    <tr>
                      <th class="checkbox-col"><input type="checkbox" /></th>
                      <th>Title</th>
                      <th>Start Date</th>
                      <th>End Date</th>
                      <th>Branch</th>
                      <th>Department</th>
                      <th class="announcement-description-col">Description</th>
                      <th class="col-action sticky-action-col">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in pagedAnnouncementRows" :key="`announcement-row-${item.id}`">
                      <td class="checkbox-col"><input type="checkbox" /></td>
                      <td>{{ item.title }}</td>
                      <td>{{ item.startDate }}</td>
                      <td>{{ item.endDate || '--' }}</td>
                      <td>{{ item.branch }}</td>
                      <td>{{ item.department }}</td>
                      <td class="announcement-description-col">{{ item.description }}</td>
                      <td class="col-action sticky-action-col">
                        <button type="button" class="row-action-btn" @click.stop="toggleAnnouncementRowMenu(item.id, $event)">
                          <iconify-icon icon="lucide:more-vertical" />
                        </button>
                        <teleport to="body">
                          <div
                            v-if="openAnnouncementRowMenuId === item.id"
                            class="leave-row-menu announcement-row-menu"
                            :style="announcementRowMenuStyle"
                            @click.stop
                          >
                            <button type="button" class="leave-row-menu-item" @click.stop="openEditAnnouncement(item)">
                              <iconify-icon icon="lucide:pencil" /> Edit
                            </button>
                            <button type="button" class="leave-row-menu-item danger" @click.stop="deleteAnnouncement(item)">
                              <iconify-icon icon="lucide:trash-2" /> Delete
                            </button>
                          </div>
                        </teleport>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="hr-footer">
                <span>Showing {{ announcementsStartEntry }} to {{ announcementsEndEntry }} of {{ filteredAnnouncementRows.length }} Entries</span>
                <div class="hr-pagination">
                  <button type="button" class="page-btn" :disabled="announcementsPage === 1" @click="announcementsPage = Math.max(1, announcementsPage - 1)">Previous</button>
                  <template v-for="(item, idx) in announcementsPaginationItems" :key="item.type === 'page' ? `anp-${item.n}` : `and-${idx}`">
                    <span v-if="item.type === 'dots'" class="page-dots">...</span>
                    <button
                      v-else
                      type="button"
                      class="page-number"
                      :class="{ active: announcementsPage === item.n }"
                      @click="announcementsPage = item.n"
                    >
                      {{ item.n }}
                    </button>
                  </template>
                  <button type="button" class="page-btn" :disabled="announcementsPage >= announcementsTotalPages" @click="announcementsPage = Math.min(announcementsTotalPages, announcementsPage + 1)">Next</button>
                </div>
              </div>
            </div>
          </template>

          <div v-if="error" class="alert alert-danger mt-3 mb-0 py-2">{{ error }}</div>
        </div>
      </div>

      <div class="hr-content-card hr-assets-card" v-else-if="activeTab === 'Assets'">
        <div class="hr-content-shell overview-shell hr-assets-shell">
          <AssetsManagement ref="assetsMgmtRef" embedded />
        </div>
      </div>

      <div class="hr-content-card hr-recruitment-card" v-else-if="activeTab === 'Career'">
        <div class="hr-content-shell overview-shell hr-recruitment-shell">
          <CareerRecruitmentManagement embedded />
        </div>
      </div>

      <div class="hr-content-card" v-else-if="activeTab === 'Payroll'">
        <div class="hr-content-shell">
          <div class="hr-payroll-placeholder">
            <h6>{{ payrollSectionLabel }}</h6>
            <p>Payroll tools are available from the menu above. Full payroll views are coming soon.</p>
          </div>
        </div>
      </div>

      <div v-else class="hr-content-card">
        <div class="hr-content-shell hr-empty-tab"></div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="isMobileViewport && openHeaderMenu && openHeaderMenu !== 'Overview'"
        class="hr-mob-nav-sheet"
        role="dialog"
        aria-modal="true"
        :aria-label="`${openHeaderMenu} sections`"
      >
        <button type="button" class="hr-mob-nav-sheet__backdrop" aria-label="Close menu" @click="openHeaderMenu = null" />
        <div class="hr-mob-nav-sheet__panel">
          <div class="hr-mob-nav-sheet__head">
            <h2 class="hr-mob-nav-sheet__title">{{ openHeaderMenu }}</h2>
            <button type="button" class="hr-mob-nav-sheet__close" aria-label="Close" @click="openHeaderMenu = null">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
          <div class="hr-mob-nav-sheet__list">
            <button
              v-for="item in headerTabMenus[openHeaderMenu] || []"
              :key="item"
              type="button"
              class="hr-mob-nav-sheet__item"
              @click="onHeaderMenuSelect(openHeaderMenu, item)"
            >
              <span class="hr-mob-nav-sheet__item-left">
                <iconify-icon :icon="menuItemIcon(item)" />
                <span>{{ item }}</span>
              </span>
              <iconify-icon icon="lucide:chevron-right" />
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="isMobileViewport && showAttendanceSearchModal"
        class="hr-attendance-mob-sheet"
        role="dialog"
        aria-modal="true"
        aria-label="Attendance filters"
      >
        <button type="button" class="hr-attendance-mob-sheet__backdrop" aria-label="Close filters" @click="showAttendanceSearchModal = false" />
        <div class="hr-attendance-mob-sheet__panel" @mousedown.prevent>
          <HrAttendanceSearchDropdown
            :filters="attendanceSearchFilters"
            :chips="attendanceSearchChips"
            :selected-chip="selectedAttendanceSearchChip"
            :employee-options="attendanceEmployeeOptions"
            :department-options="attendanceDepartmentOptions"
            :type-options="attendanceTypeOptions"
            :status-options="attendanceStatusOptions"
            :date-display="formatDateDisplay(attendanceSearchFilters.attendanceDate)"
            :select-append-to-body="true"
            @close="showAttendanceSearchModal = false"
            @reset="resetAttendanceSearchFilters"
            @apply="applyAttendanceSearchFilters"
            @select-chip="selectAttendanceSearchChip"
            @open-date-picker="openDatePicker('attendanceSearchFilters.attendanceDate')"
            @update:filters="onAttendanceSearchFiltersPatch"
          />
        </div>
      </div>
    </Teleport>

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

    <div v-if="showAnnouncementSearchModal" class="edit-overlay" @click.self="showAnnouncementSearchModal = false">
      <div class="employee-filter-modal leave-search-modal announcement-search-modal">
        <button type="button" class="employee-filter-close" @click="showAnnouncementSearchModal = false">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="asset-search-right w-100">
          <div class="asset-search-section">
            <h6>Announcement Title</h6>
            <div class="add-grid-one">
              <div class="add-field">
                <input v-model="announcementSearchFilters.title" type="text" placeholder="Search title" />
              </div>
            </div>
          </div>
          <div class="asset-search-section">
            <h6>Branch</h6>
            <div class="add-grid-one">
              <div class="add-field">
                <SearchableSelect v-model="announcementSearchFilters.branch" :options="announcementBranchOptions" placeholder="Not Selected" />
              </div>
            </div>
          </div>
          <div class="asset-search-section">
            <h6>Department</h6>
            <div class="add-grid-one">
              <div class="add-field">
                <SearchableSelect v-model="announcementSearchFilters.department" :options="announcementDepartmentOptions" placeholder="Not Selected" />
              </div>
            </div>
          </div>
          <div class="employee-filter-actions mt-2">
            <button type="button" class="employee-filter-btn ghost" @click="resetAnnouncementSearchFilters">Reset</button>
            <button type="button" class="employee-filter-btn primary" @click="applyAnnouncementSearchFilters">Search</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showAnnouncementModal" class="edit-overlay add-employee-overlay" @click.self="closeAnnouncementModal">
      <div class="add-employee-modal leave-apply-modal announcement-modal">
        <div class="add-employee-head">
          <h6>Add Announcements</h6>
          <button type="button" class="add-employee-close" @click="closeAnnouncementModal">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>

        <div class="add-employee-body">
          <section class="add-employee-section">
            <div class="add-grid-two">
              <div class="add-field add-field-full">
                <label>Announcement Tittle *</label>
                <input
                  v-model="announcementForm.title"
                  type="text"
                  placeholder="Enter Announcement Title"
                />
              </div>
              <div class="add-field">
                <label>Branch *</label>
                <SearchableSelect
                  v-model="announcementForm.branch"
                  :options="branchOptions"
                  placeholder="Not Selected"
                />
              </div>
              <div class="add-field">
                <label>Department *</label>
                <SearchableSelect
                  v-model="announcementForm.department"
                  :options="announcementDepartmentOptions"
                  placeholder="Not Selected"
                />
              </div>
              <div class="add-field">
                <label>Start Date</label>
                <input :value="formatDateDisplay(announcementForm.startDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('announcementForm.startDate')" />
              </div>
              <div class="add-field">
                <label>End Date</label>
                <input :value="formatDateDisplay(announcementForm.endDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('announcementForm.endDate')" />
              </div>
              <div class="add-field add-field-full">
                <label>Description</label>
                <textarea v-model="announcementForm.description" placeholder="Enter text"></textarea>
              </div>
            </div>
          </section>
        </div>

        <div class="add-employee-footer">
          <button type="button" class="add-employee-clear-btn" @click="clearAnnouncementForm">Clear</button>
          <button type="button" class="add-employee-save-btn" @click="saveAnnouncement">Confirm</button>
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
          <p><span>Start Date</span><strong>{{ formatDate(selectedLeaveRow.startDate) }}</strong></p>
          <p><span>End Date</span><strong>{{ formatDate(selectedLeaveRow.endDate) }}</strong></p>
          <p><span>Leave Days</span><strong>{{ selectedLeaveRow.days }} Day(s)</strong></p>
          <p><span>Status</span><strong :class="`leave-txt-${selectedLeaveRow.status.toLowerCase()}`">{{ selectedLeaveRow.status }}</strong></p>
          <p><span>Leave Type</span><strong>{{ selectedLeaveRow.leaveType }}</strong></p>
          <p><span>Applied On</span><strong>{{ selectedLeaveRow.appliedDate }}</strong></p>
        </div>
        <div class="leave-detail-reason">
          <span>Leave Reason</span>
          <p>{{ selectedLeaveRow.reason }}</p>
        </div>
        <div v-if="selectedLeaveRow.status.toLowerCase() === 'pending_hr'" class="leave-detail-actions">
          <button type="button" class="leave-approve-btn" @click="openApproveLeaveModal(selectedLeaveRow)">Approve Leave</button>
          <button type="button" class="leave-reject-btn" @click="openRejectLeaveModal(selectedLeaveRow)">Reject Leave</button>
        </div>
        <div v-else class="leave-detail-status-message">
          <p>This leave request has been <strong>{{ selectedLeaveRow.status }}</strong></p>
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

    <div v-if="showAttendanceDetailModal && selectedAttendanceRow" class="edit-overlay" @click.self="closeAttendanceDetailModal">
      <div class="attendance-detail-modal">
        <button type="button" class="employee-filter-close" @click="closeAttendanceDetailModal"><iconify-icon icon="lucide:x" /></button>
        <button
          type="button"
          class="attendance-detail-edit-link"
          @click="switchAttendanceDetailToEdit"
        >
          <iconify-icon icon="lucide:pencil" />
          <span>Edit</span>
        </button>

        <div class="attendance-detail-hero">
          <div class="attendance-detail-icon">
            <iconify-icon icon="lucide:calendar-check-2" />
          </div>
          <h6>{{ attendanceDetailMode === 'edit' ? 'Edit Attendance' : 'Attendance Details' }}</h6>
          <p>
            View the complete attendance information for the selected date. This includes check-in, check-out,
            working hours, and status.
          </p>
        </div>

        <div class="attendance-detail-grid-card">
          <div class="attendance-detail-grid">
            <p><span>Employee ID</span><strong>#EMP{{ formatEmpId(selectedAttendanceRow.employee_id) }}</strong></p>
            <p><span>Employee Name</span><strong>{{ selectedAttendanceRow.employee_name || '--' }}</strong></p>
            <p v-if="attendanceDetailMode === 'view'"><span>Date</span><strong>{{ formatDate(selectedAttendanceRow.date) }}</strong></p>
            <div v-else class="attendance-detail-field">
              <span>Date</span>
              <input :value="formatDateDisplay(attendanceEditForm.date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('attendanceEditForm.date')" />
            </div>
            <p v-if="attendanceDetailMode === 'view'">
              <span>Check in &amp; Check out</span>
              <strong>{{ formatTime(selectedAttendanceRow.check_in) }} - {{ formatTime(selectedAttendanceRow.check_out) }}</strong>
            </p>
            <div v-else class="attendance-detail-field attendance-time-row">
              <span>Check in &amp; Check out</span>
              <div class="attendance-time-grid">
                <input v-model="attendanceEditForm.checkIn" type="time" />
                <input v-model="attendanceEditForm.checkOut" type="time" />
              </div>
            </div>
            <p><span>Hours</span><strong>{{ attendanceDetailMode === 'view' ? formatDuration(selectedAttendanceRow.check_in, selectedAttendanceRow.check_out) : attendanceEditDuration }}</strong></p>
            <p v-if="attendanceDetailMode === 'view'">
              <span>Status</span>
              <strong class="attendance-status-text" :class="`status-${String(selectedAttendanceRow.status || '').toLowerCase().replace(/\s+/g, '-')}`">{{ selectedAttendanceRow.status || '--' }}</strong>
            </p>
            <div v-else class="attendance-detail-field">
              <span>Status</span>
              <SearchableSelect v-model="attendanceEditForm.status" :options="attendanceStatusOptions" placeholder="Select Status" />
            </div>
            <p><span>Break</span><strong>{{ attendanceDetailMode === 'view' ? formatBreakDisplay(selectedAttendanceRow) : attendanceEditForm.breakLabel || '--' }}</strong></p>
            <p><span>Overtime (OT)</span><strong>{{ attendanceDetailMode === 'view' ? formatOtDisplay(selectedAttendanceRow) : attendanceEditForm.otLabel || '--' }}</strong></p>
            <p><span>Attachments</span><strong>--</strong></p>
            <p><span>Image</span><strong>No Media</strong></p>
            <p class="full"><span>Description</span><strong>{{ attendanceDetailMode === 'view' ? '--' : (attendanceEditForm.description || '--') }}</strong></p>
          </div>
        </div>

        <div v-if="attendanceDetailMode === 'edit'" class="attendance-detail-actions">
          <button type="button" class="employee-filter-btn ghost" @click="closeAttendanceDetailModal">Cancel</button>
          <button type="button" class="employee-filter-btn primary" @click="saveAttendanceEdit">Save</button>
        </div>
      </div>
    </div>

    <div v-if="showAssetEditModal" class="edit-overlay add-employee-overlay" @click.self="closeAssetEditModal">
      <div class="add-employee-modal asset-create-modal">
        <div class="add-employee-head">
          <h6>Edit Asset</h6>
          <button type="button" class="add-employee-close" @click="closeAssetEditModal">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>

        <div class="add-employee-body">
          <section class="add-employee-section">
            <h6>Asset Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Asset Type *</label><SearchableSelect v-model="assetEditForm.assetType" :options="assetTypeOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Asset Name *</label><input v-model="assetEditForm.assetName" type="text" placeholder="Enter Asset Name" /></div>
              <div class="add-field"><label>Serial Number</label><input v-model="assetEditForm.serialNumber" type="text" placeholder="Enter Serial Number" /></div>
              <div class="add-field"><label>Model Number</label><input v-model="assetEditForm.modelNumber" type="text" placeholder="Enter Model Number" /></div>
              <div class="add-field"><label>RDP Number</label><input v-model="assetEditForm.rdpNumber" type="text" placeholder="Enter reference number" /></div>
              <div class="add-field"><label>Remarks</label><input v-model="assetEditForm.remarks" type="text" placeholder="Enter Remarks" /></div>
              <div class="add-field add-field-full"><label>Description</label><textarea v-model="assetEditForm.description" placeholder="Enter Description"></textarea></div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>User Details</h6>
            <div class="add-grid-two">
              <div ref="assetUserEditPickerRef" class="add-field asset-user-picker-field">
                <label>Asset User</label>
                <button type="button" class="asset-user-trigger" @click.stop="toggleAssetUserEditPicker">
                  <span>{{ selectedAssetResponsiblePersonEdit?.name || 'Not Selected' }}</span>
                  <iconify-icon icon="lucide:chevron-down" />
                </button>
                <div v-if="showAssetUserEditPicker" class="asset-user-dropdown" @click.stop>
                  <div class="asset-user-dropdown-head">
                    <span>Person</span>
                    <button type="button" class="asset-user-close-btn" @click="closeAssetUserEditPicker">
                      <iconify-icon icon="lucide:x" />
                    </button>
                  </div>
                  <div class="search-input-wrapper mb-2">
                    <input v-model="assetUserEditSearchQuery" type="text" class="asset-user-search-input" placeholder="Search Responsible Person" />
                    <iconify-icon icon="lucide:search" class="search-icon" />
                  </div>
                  <div class="asset-user-list-scroll">
                    <button
                      v-for="person in filteredAssetEditResponsiblePersons"
                      :key="person.id"
                      type="button"
                      class="asset-user-item"
                      :class="{ selected: Number(assetEditForm.assetUser) === Number(person.id) }"
                      @click="selectAssetEditResponsiblePerson(person)"
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
                    <div v-if="!filteredAssetEditResponsiblePersons.length" class="text-center text-muted py-2">No persons found</div>
                  </div>
                </div>
              </div>
              <div class="add-field"><label>Date Of Handover</label><input :value="formatDateDisplay(assetEditForm.handoverDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetEditForm.handoverDate')" /></div>
              <div class="add-field"><label>Branch Location</label><SearchableSelect v-model="assetEditForm.branchLocation" :options="branchOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Department</label><SearchableSelect v-model="assetEditForm.department" :options="departmentOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Status *</label><SearchableSelect v-model="assetEditForm.status" :options="assetStatusOptions" placeholder="Not Selected" /></div>
              <div class="add-field"><label>Date Of Return</label><input :value="formatDateDisplay(assetEditForm.returnDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetEditForm.returnDate')" /></div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Purchase Details</h6>
            <div class="add-grid-two">
              <div class="add-field"><label>Purchase Date *</label><input :value="formatDateDisplay(assetEditForm.purchaseDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetEditForm.purchaseDate')" /></div>
              <div class="add-field"><label>Supplier Name</label><input v-model="assetEditForm.supplierName" type="text" placeholder="Enter Supplier Name" /></div>
              <div class="add-field"><label>Warranty Date</label><input :value="formatDateDisplay(assetEditForm.warrantyDate)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('assetEditForm.warrantyDate')" /></div>
              <div class="add-field"><label>Condition *</label><SearchableSelect v-model="assetEditForm.condition" :options="assetConditionOptions" placeholder="Not Selected" /></div>
              <div class="add-field">
                <label>Unit Price</label>
                <div class="asset-price-group">
                  <input v-model="assetEditForm.unitPrice" type="text" placeholder="Enter Amount" />
                  <select v-model="assetEditForm.currency">
                    <option value="UAE Dirham">UAE Dirham</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                  </select>
                </div>
              </div>
              <div class="add-field">
                <label>QTY *</label>
                <div class="asset-qty-group">
                  <input v-model.number="assetEditForm.qty" type="number" min="1" placeholder="Enter item quantity" />
                  <button type="button" class="asset-qty-btn" @click="decrementAssetEditQty">-</button>
                  <button type="button" class="asset-qty-btn" @click="incrementAssetEditQty">+</button>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="add-employee-footer">
          <button type="button" class="add-employee-clear-btn" @click="closeAssetEditModal">Cancel</button>
          <button type="button" class="add-employee-save-btn" @click="saveAssetEdit">Save</button>
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
                  <div class="leave-half-day-checkbox">
                    <label class="checkbox-label">
                      <input 
                        type="checkbox" 
                        v-model="applyLeaveForm.isHalfDay" 
                        @change="onHalfDayChange"
                      />
                      <span>Half Day Leaves</span>
                    </label>
                  </div>
                </div>

                <!-- Half Day Type (checkbox) -->
                <div v-if="applyLeaveForm.isHalfDay" class="add-field add-field-full">
                  <label>Half Day Type *</label>
                  <SearchableSelect
                    v-model="applyLeaveForm.halfDayType"
                    :options="halfDayTypeOptions"
                    placeholder="Select Half Day Type"
                  />
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
          <button type="button" class="add-employee-clear-btn" @click="cancelApplyLeave">Cancel</button>
          <button type="button" class="add-employee-save-btn" @click="submitApplyLeave">Apply</button>
        </div>
      </div>
    </div>

    <div v-if="showCreateAttendanceModal" class="edit-overlay add-employee-overlay" @click.self="closeCreateAttendanceModal">
      <div class="add-employee-modal leave-apply-modal attendance-create-modal">
        <div class="add-employee-head">
          <h6>Create New Attendance</h6>
          <button type="button" class="add-employee-close" @click="closeCreateAttendanceModal">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>

        <div class="add-employee-body">
          <section class="add-employee-section">
            <div class="add-grid-two">
              <div class="add-field add-field-full">
                <label>Employee *</label>
                <SearchableSelect
                  v-model="createAttendanceForm.employee"
                  :options="applyLeaveEmployeeOptions"
                  placeholder="Select Employee"
                />
              </div>
              <div class="add-field add-field-full">
                <label>Type *</label>
                <SearchableSelect
                  v-model="createAttendanceForm.type"
                  :options="attendanceCreateTypeOptions"
                  placeholder="Select Attendance Type"
                />
              </div>
              <div class="add-field">
                <label>Status *</label>
                <SearchableSelect
                  v-model="createAttendanceForm.status"
                  :options="attendanceStatusOptions"
                  placeholder="Select Status"
                />
              </div>
              <div class="add-field">
                <label>Date *</label>
                <input :value="formatDateDisplay(createAttendanceForm.date)" type="text" placeholder="--/--/--" readonly @click="openDatePicker('createAttendanceForm.date')" />
              </div>
              <div class="add-field">
                <label>Check In *</label>
                <input v-model="createAttendanceForm.checkIn" type="time" />
              </div>
              <div class="add-field">
                <label>Check Out *</label>
                <input v-model="createAttendanceForm.checkOut" type="time" />
              </div>
              <div class="add-field">
                <label>Break *</label>
                <SearchableSelect
                  v-model="createAttendanceForm.breakLabel"
                  :options="attendanceBreakOptions"
                  placeholder="Select Break"
                />
              </div>
              <div class="add-field">
                <label>Overtime (OT) *</label>
                <SearchableSelect
                  v-model="createAttendanceForm.otLabel"
                  :options="attendanceOtOptions"
                  placeholder="Select overtime"
                />
              </div>
              <div class="add-field add-field-full">
                <label>Description</label>
                <textarea v-model="createAttendanceForm.description" placeholder="Enter Text"></textarea>
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
                <input type="file" class="d-none" @change="handleCreateAttendanceFileChange" />
              </label>
            </div>
            <div v-if="createAttendanceAttachment" class="uploaded-doc-card">
              <iconify-icon icon="lucide:file-text" />
              <div>
                <p>{{ createAttendanceAttachment.name }}</p>
                <small>{{ `${Math.max(1, Math.round(createAttendanceAttachment.size / 1024))}KB` }}</small>
              </div>
              <button type="button" @click="removeCreateAttendanceFile">
                <iconify-icon icon="lucide:x-circle" />
              </button>
            </div>
          </section>
        </div>

        <div class="add-employee-footer">
          <button type="button" class="add-employee-clear-btn" @click="cancelCreateAttendance">Cancel</button>
          <button type="button" class="add-employee-save-btn" @click="submitCreateAttendance">Submit</button>
        </div>
      </div>
    </div>

    <div v-if="openEmployeeFilters" class="edit-overlay" @click.self="openEmployeeFilters = false">
      <div class="employee-filter-modal employee-search-filter-modal">
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
            <div class="employee-filter-date-wrap">
              <input :value="formatDateDisplay(employeeFilters.joiningDate)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('employeeFilters.joiningDate')" />
              <iconify-icon icon="lucide:calendar-days" />
            </div>
          </div>
          <div class="employee-filter-field">
            <label>Visa Validity</label>
            <div class="employee-filter-date-wrap">
              <input :value="formatDateDisplay(employeeFilters.visaValidity)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('employeeFilters.visaValidity')" />
              <iconify-icon icon="lucide:calendar-days" />
            </div>
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
                <div class="profile-photo-wrap">
                  <button type="button" class="profile-photo-avatar" @click="triggerProfileImageUpload">
                    <img v-if="addEmployeeProfilePreview" :src="addEmployeeProfilePreview" alt="Profile preview" />
                    <iconify-icon v-else icon="lucide:user-round" />
                  </button>
                  <button type="button" class="profile-photo-edit-btn" @click="triggerProfileImageUpload">
                    <iconify-icon icon="lucide:camera" />
                  </button>
                </div>
                <span>Profile Photo</span>
                <small class="profile-photo-hint">Click photo to upload</small>
              </div>
              <input ref="profileImageInputRef" type="file" class="d-none" accept="image/*" @change="handleProfileImageChange" />

              <div class="profile-form-grid">
                <div class="add-field">
                  <label>Full Name *</label>
                  <input v-model="addEmployeeForm.full_name" type="text" placeholder="Enter Employee Full Name" />
                </div>
                <div class="add-field">
                  <label>Phone Number *</label>
                  <input v-model="addEmployeeForm.phone" type="text" placeholder="Enter Phone Number" />
                </div>
                <div class="add-field">
                  <label>Home Country Phone Number</label>
                  <input v-model="addEmployeeForm.home_country_phone_number" type="text" placeholder="Enter Phone Number" />
                </div>
                <div class="add-field">
                  <label>Email *</label>
                  <input v-model="addEmployeeForm.email" type="email" placeholder="Enter Your Email" />
                </div>
                <div class="add-field">
                  <label>Address Inside UAE</label>
                  <input v-model="addEmployeeForm.address_inside_uae" type="text" placeholder="Enter Address" />
                </div>
                <div class="add-field">
                  <label>Address Outside UAE</label>
                  <input v-model="addEmployeeForm.address_outside_uae" type="text" placeholder="Enter Address" />
                </div>
                <div class="add-field">
                  <label>Nationality *</label>
                  <SearchableSelect v-model="addEmployeeForm.nationality" :options="nationalityOptions" placeholder="Not Selected" />
                </div>
                <div class="add-field">
                  <label>Salary Type *</label>
                  <SearchableSelect v-model="addEmployeeForm.salary_type" :options="salaryTypeOptions" placeholder="Not Selected" />
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
            <h6>Additional Details</h6>
            <div class="add-grid-two">
              <div class="add-field">
                <label>Father Name</label>
                <input v-model="addEmployeeForm.father_name" type="text" placeholder="Enter Father Name" />
              </div>
              <div class="add-field">
                <label>Mother Name</label>
                <input v-model="addEmployeeForm.mother_name" type="text" placeholder="Enter Mother Name" />
              </div>
              <div class="add-field">
                <label>Marital Status *</label>
                <SearchableSelect v-model="addEmployeeForm.marital_status" :options="maritalStatusOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Religion</label>
                <input v-model="addEmployeeForm.religion" type="text" placeholder="Enter Religion" />
              </div>
              <div class="add-field">
                <label>Emergency Contact Name</label>
                <input v-model="addEmployeeForm.emergency_contact_name" type="text" placeholder="Enter Name" />
              </div>
              <div class="add-field">
                <label>Emergency Email</label>
                <input v-model="addEmployeeForm.emergency_email" type="email" placeholder="Enter Your Email" />
              </div>
              <div class="add-field">
                <label>Emergency Phone Number</label>
                <input v-model="addEmployeeForm.emergency_phone_number" type="text" placeholder="Enter Phone Number" />
              </div>
            </div>
          </section>

          <section class="add-employee-section">
            <h6>Company Details</h6>
            <div class="add-grid-two">
              <div class="add-field">
                <label>Branch *</label>
                <SearchableSelect v-model="addEmployeeForm.branch_id" :options="branchOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Designation *</label>
                <SearchableSelect v-model="addEmployeeForm.designation_id" :options="designationOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Department *</label>
                <SearchableSelect v-model="addEmployeeForm.department_id" :options="departmentOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>Supervisor *</label>
                <SearchableSelect v-model="addEmployeeForm.supervisor_id" :options="supervisorOptions" placeholder="Not Selected" />
              </div>
              <div class="add-field">
                <label>User Type</label>
                <input v-model="addEmployeeForm.user_type" type="text" placeholder="Enter User Type" />
              </div>
              <div class="add-field">
                <label>Probation End Date *</label>
                <input :value="formatDateDisplay(addEmployeeForm.probation_end_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.probation_end_date')" />
              </div>
              <div class="add-field">
                <label>Joining Date *</label>
                <input :value="formatDateDisplay(addEmployeeForm.joining_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.joining_date')" />
              </div>
              <div class="add-field">
                <label>Visa Validity *</label>
                <input :value="formatDateDisplay(addEmployeeForm.visa_validity)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.visa_validity')" />
              </div>
              <div class="add-field">
                <label>Sponsor</label>
                <input v-model="addEmployeeForm.sponsor" type="text" placeholder="Enter Sponsor Name" />
              </div>
              <div class="add-field">
                <label>Contract Joining Date *</label>
                <input :value="formatDateDisplay(addEmployeeForm.contract_joining_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.contract_joining_date')" />
              </div>
              <div class="add-field">
                <label>Visa Quota</label>
                <input v-model="addEmployeeForm.visa_quota" type="text" placeholder="Enter Sponsor Name" />
              </div>
              <div class="add-field">
                <label>Gratuity Termination</label>
                <input :value="formatDateDisplay(addEmployeeForm.gratuity_termination)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.gratuity_termination')" />
              </div>
              <div class="add-field">
                <label>Vehicle</label>
                <input v-model="addEmployeeForm.vehicle" type="text" placeholder="Enter Vehicle Number" />
              </div>
            </div>
          </section>

          <section class="add-employee-section">
                <h6>Upload Employee Documents</h6>
                <div class="doc-chip-row">
                  <button
                    v-for="doc in employeeDocumentTypes"
                    :key="doc.value"
                    type="button"
                    class="doc-chip"
                    :class="{
                      active: selectedDocumentType === doc.value,
                      'has-file': hasDocumentFile(doc.value),
                    }"
                    @click="selectedDocumentType = doc.value"
                  >
                    {{ doc.label }}
                  </button>
                </div>

                <!-- 🔥 Emirates ID -->
                <template v-if="selectedDocumentType === 'emirates_id'">
                  <div class="add-field">
                    <label>Emirates ID Number *</label>
                    <input v-model="addEmployeeForm.emirates_id_number" type="text" placeholder="Enter Emirates ID Number" />
                  </div>
                  <div class="add-field mt-2">
                    <label>Expiry Date *</label>
                    <input :value="formatDateDisplay(addEmployeeForm.documents_expiry_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.documents_expiry_date')" />
                  </div>
                </template>

                <!-- 🔥 Labor Card -->
                <template v-else-if="selectedDocumentType === 'labor_card'">
                  <div class="add-field">
                    <label>Labor Card Number *</label>
                    <input v-model="addEmployeeForm.labor_card_number" type="text" placeholder="Enter Labor Card Number" />
                  </div>
                  <div class="add-field mt-2">
                    <label>Expiry Date *</label>
                    <input :value="formatDateDisplay(addEmployeeForm.labor_card_expiry_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.labor_card_expiry_date')" />
                  </div>
                </template>

                <!-- 🔥 Passport -->
                <template v-else-if="selectedDocumentType === 'passport'">
                  <div class="add-field">
                    <label>Passport Number *</label>
                    <input v-model="addEmployeeForm.passport_number" type="text" placeholder="Enter Passport Number" />
                  </div>
                  <div class="add-field mt-2">
                    <label>Expiry Date *</label>
                    <input :value="formatDateDisplay(addEmployeeForm.passport_expiry_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.passport_expiry_date')" />
                  </div>
                </template>

                <!-- 🔥 Visa -->
                <template v-else-if="selectedDocumentType === 'visa'">
                  <div class="add-field">
                    <label>Visa Number</label>
                    <input v-model="addEmployeeForm.visa_number" type="text" placeholder="Enter Visa Number" />
                  </div>
                  <div class="add-field mt-2">
                    <label>Expiry Date</label>
                    <input :value="formatDateDisplay(addEmployeeForm.visa_expiry_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.visa_expiry_date')" />
                  </div>
                </template>

       

                <!-- 🔥 Insurance Card -->
                <template v-else-if="selectedDocumentType === 'insurance_card'">
                  <div class="add-field">
                    <label>Insurance Provider</label>
                    <input v-model="addEmployeeForm.insurance_provider" type="text" placeholder="Enter Insurance Provider" />
                  </div>
                  <div class="add-field mt-2">
                    <label>Policy Number</label>
                    <input v-model="addEmployeeForm.policy_number" type="text" placeholder="Enter Policy Number" />
                  </div>
                  <div class="add-field mt-2">
                    <label>Expiry Date</label>
                    <input :value="formatDateDisplay(addEmployeeForm.insurance_expiry_date)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('addEmployeeForm.insurance_expiry_date')" />
                  </div>
                </template>

              


                

             

             


                <!-- 🔥 Attested Certificates -->
                <template v-else-if="selectedDocumentType === 'attested_certificate'">
                  <div class="add-field">
                    <label>Certificate Name *</label>
                    <input v-model="addEmployeeForm.certificate_name" type="text" placeholder="Enter Certificate Name" />
                  </div>
                  <div class="add-field mt-2">
                    <label>Attestation Status *</label>
                    <SearchableSelect v-model="addEmployeeForm.attestation_status" :options="['Attested', 'Not Attested', 'In Progress']" placeholder="Select Status" />
                  </div>
                </template>

                <EmployeeDocumentDropzone
                  :key="selectedDocumentType"
                  v-model="currentDocumentFile"
                  class="mt-3"
                  @error="showNotification($event, 'error')"
                />
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
              <div class="add-field"><label>Health Card Number *</label><input v-model="addEmployeeForm.policy_number" type="text" placeholder="Enter Policy Number" /></div>
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

    <div v-if="showRequestDocumentModal" class="edit-overlay" @click.self="closeRequestDocumentModal">
      <div class="employee-filter-modal section-edit-modal request-doc-modal">
        <button type="button" class="employee-filter-close" @click="closeRequestDocumentModal">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="employee-filter-right w-100">
          <p class="request-doc-title">Request New Document</p>
          <div class="request-doc-grid">
            <div class="add-field" v-if="activeTab === 'Document Requests' && !editingRequestedDocumentId">
              <label>Employee *</label>
              <SearchableSelect v-model="requestDocumentForm.employee" :options="documentRequestEmployeeOptions" placeholder="Select Employee" />
            </div>
            <div class="add-field">
              <label>Document Type *</label>
              <SearchableSelect v-model="requestDocumentForm.documentType" :options="requestDocumentOptions" placeholder="Select Document" />
            </div>
            <div class="add-field">
              <label>Description</label>
              <textarea v-model="requestDocumentForm.description" placeholder="Enter Description"></textarea>
            </div>
          </div>
          <div class="employee-filter-actions mt-2">
            <button type="button" class="employee-filter-btn ghost" @click="closeRequestDocumentModal">Cancel</button>
            <button type="button" class="employee-filter-btn primary" @click="submitRequestDocument">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showDocumentDetailModal && selectedRequestedDocument" class="edit-overlay" @click.self="closeDocumentDetailModal">
      <div class="employee-filter-modal section-edit-modal request-doc-detail-modal">
        <button type="button" class="employee-filter-close" @click="closeDocumentDetailModal">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="employee-filter-right w-100">
          <h6 class="mb-2">Document Detail</h6>
          <div class="requested-detail-grid">
            <p><span>Document Type</span><strong>{{ selectedRequestedDocument.documentType }}</strong></p>
            <p><span>Requested Date</span><strong>{{ selectedRequestedDocument.requestedDate }}</strong></p>
            <p><span>Status</span><strong :class="`doc-status-${String(selectedRequestedDocument.status).toLowerCase()}`">{{ selectedRequestedDocument.status }}</strong></p>
          </div>
          <div class="add-field mt-2">
            <label>Description</label>
            <textarea :value="selectedRequestedDocument.description || '--'" readonly></textarea>
          </div>
          <div v-if="selectedRequestedDocument.status === 'Rejected'" class="add-field mt-2">
            <label>Rejection Reason</label>
            <textarea :value="selectedRequestedDocument.rejectionReason || '--'" readonly></textarea>
          </div>
          <div v-if="selectedRequestedDocument.status === 'Pending'" class="request-doc-detail-actions">
            <button type="button" class="request-doc-approve-btn" @click="openApproveDocumentModal">Approve Document</button>
            <button type="button" class="request-doc-reject-btn" @click="openRejectDocumentModal">Reject Document</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showApproveDocumentModal && selectedRequestedDocument" class="edit-overlay" @click.self="closeApproveDocumentModal">
      <div class="employee-filter-modal section-edit-modal request-doc-approve-modal">
        <button type="button" class="employee-filter-close" @click="closeApproveDocumentModal">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="employee-filter-right w-100">
          <h6 class="mb-2">Approve Document</h6>
          <div class="add-field">
            <label>Attach Document *</label>
          </div>
          <div class="upload-dropzone">
            <div>
              <strong>Drag and drop your files</strong>
              <small>JPEG, PND and PDF formats, up to 50MB</small>
            </div>
            <label class="select-file-btn">
              Select File
              <input type="file" class="d-none" @change="handleApproveDocumentFileChange" />
            </label>
          </div>
          <div v-if="approveDocumentFile" class="uploaded-doc-card mt-2">
            <iconify-icon icon="lucide:file-text" />
            <div>
              <p>{{ approveDocumentFile.name }}</p>
              <small>{{ `${Math.max(1, Math.round(approveDocumentFile.size / 1024))}KB` }}</small>
            </div>
            <button type="button" @click="approveDocumentFile = null">
              <iconify-icon icon="lucide:x-circle" />
            </button>
          </div>
          <div class="request-doc-confirm-wrap">
            <button type="button" class="request-doc-confirm-btn" @click="confirmApproveDocument">Confirm</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showRejectDocumentModal && selectedRequestedDocument" class="edit-overlay" @click.self="closeRejectDocumentModal">
      <div class="employee-filter-modal section-edit-modal request-doc-reject-modal">
        <button type="button" class="employee-filter-close" @click="closeRejectDocumentModal">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="employee-filter-right w-100">
          <h6 class="mb-2">Reject Document</h6>
          <div class="add-field">
            <label>Rejection Reason</label>
            <textarea v-model="rejectDocumentReason" placeholder="Enter Reason"></textarea>
          </div>
          <div class="request-doc-confirm-wrap">
            <button type="button" class="request-doc-confirm-btn" @click="confirmRejectDocument">Confirm</button>
          </div>
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
            <EmployeeDocumentDropzone
              :key="`section-${selectedDocumentType}`"
              v-model="currentDocumentFile"
              class="mt-2"
              @error="showNotification($event, 'error')"
            />
          </template>
          <template v-else>
            <div class="add-grid-two">
              <div class="add-field"><label>Full Name *</label><input v-model="sectionEditForm.name" type="text" /></div>
              <div class="add-field"><label>Email *</label><input v-model="sectionEditForm.email_personal" type="email" /></div>
              <div class="add-field"><label>Phone Number *</label><input v-model="sectionEditForm.phone_company" type="text" /></div>
              <div class="add-field"><label>Phone Country Phone Number *</label><input v-model="sectionEditForm.phone_personal" type="text" /></div>
              <div class="add-field"><label>Date Of Birth *</label><input :value="formatDateDisplay(sectionEditForm.dob)" type="text" placeholder="dd/mm/yyyy" readonly @click="openDatePicker('sectionEditForm.dob')" /></div>
              <div class="add-field"><label>Address Inside UAE *</label><input v-model="sectionEditForm.address_inside_uae" type="text" /></div>
              <div class="add-field"><label>Address Outside UAE *</label><input v-model="sectionEditForm.address_outside_uae" type="text" /></div>
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
import Swal from 'sweetalert2'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import HrTeamTreePanel from '@/components/hr/HrTeamTreePanel.vue'
import HrAttendanceSearchDropdown from '@/components/hr/HrAttendanceSearchDropdown.vue'
import StatsCards from '@/components/hr/overview/StatsCards.vue'
import EmployeesTable from '@/components/hr/overview/EmployeesTable.vue'
import EmployeeDetails from '@/components/hr/overview/EmployeeDetails.vue'
import EmployeesManagement from '@/pages/hr/employees/EmployeesManagement.vue'
import EmployeeDocumentDropzone from '@/components/hr/employees/EmployeeDocumentDropzone.vue'
import LeaveAttendanceManagement from '@/pages/hr/leave-attendance/LeaveAttendanceManagement.vue'
import CareerRecruitmentManagement from '@/pages/hr/recruitment/CareerRecruitmentManagement.vue'
import AssetsManagement from '@/pages/hr/assets/AssetsManagement.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import DateTimePicker from '@/components/kanban/shared/DateTimePicker.vue'
import { hrPipelineDebugEnabled, useHrDashboard } from '@/composables/useHrDashboard'
import {
  fetchDocumentRequests,
  createDocumentRequest,
  updateDocumentRequest,
  deleteDocumentRequest,
  approveDocumentRequest,
  rejectDocumentRequest,
  fetchDocumentTypes,
} from '@/services/documentRequestsApi'

import {
  fetchJobs,
  createJob,
  updateJob,
  deleteJob,
  fetchApplicants,
  getApplicant,
  updateApplicantStatus as updateApplicantStatusApi,
  scheduleInterview as scheduleInterviewApi,
  fetchRecruitmentStatistics,
} from '@/services/recruitmentApi'
import {
  fetchAnnouncements,
  createAnnouncement,
  updateAnnouncement,
  deleteAnnouncement as deleteAnnouncementApi,
} from '@/services/announcementsApi'
import {
  createEmployee,
  updateEmployee,
  fetchDepartments,
  fetchDesignations,
  fetchBranches,
  fetchManagers,fetchEmployee,  
} from '@/services/employeesApi'

import {
  createAttendance,
  updateAttendance,
  deleteAttendance,
  fetchAttendance,
  fetchEmployeeAttendance,
  fetchAttendanceSummary,
  getAttendance,
  recordCheckInOut,
  fetchDailyAttendanceStats,
} from '@/services/attendancesApi'
import {
  fetchLeaveTypes,
  createLeaveType,
  updateLeaveType,
  deleteLeaveType,
  fetchMyLeaveBalance,
  fetchEmployeeLeaveBalance,
  fetchLeaveRequests,
  createLeaveRequest,
  updateLeaveRequest,
  getLeaveRequest,
  cancelLeaveRequest,
  approveByParent,
  rejectByParent,
  approveByHr,
  rejectByHr,
  fetchLeaveStatistics,
  exportLeaveRequests
} from '@/services/leaveApi'

import {
  fetchAssetTypes,
  createAssetType,
  updateAssetType,
  deleteAssetType,
  fetchAssets,
  createAsset,
  getAsset,
  updateAsset,
  deleteAsset,
  assignAsset,
  returnAsset,
  transferAsset,
  markAssetMaintenance,
  getAssetHistory,
  getEmployeeAssets,
  fetchAssetStatistics,
  fetchResponsiblePersons,
} from '@/services/assetsApi'
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
const router = useRouter()
const departmentsList = ref([])
const designationsList = ref([])
const branchesList = ref([])
const managersList = ref([])
const filterOptionsLoading = ref(false)
const route = useRoute()
const HR_ACTIVE_TAB_STORAGE_KEY = 'hr.activeTab'
const HR_LEAVE_MODE_STORAGE_KEY = 'hr.leaveSectionMode'
const HR_LEAVE_INNER_TAB_STORAGE_KEY = 'hr.leaveInnerTab'
const hrDebugUi = computed(() => {
  void route.fullPath
  return hrPipelineDebugEnabled()
})
const onHalfDayChange = () => {
  if (!applyLeaveForm.value.isHalfDay) {
    applyLeaveForm.value.halfDayType = null
  }
}
const halfDayTypeOptions = [
  { value: 'morning', label: 'Morning' },
  { value: 'afternoon', label: 'Afternoon' },
]
const attendanceStats = ref({
  total_employees: 0,
  present: 0,
  absent: 0,
  late: 0,
  on_leave: 0,
  half_day: 0,
  holiday: 0,
})

const dailyStats = ref({
  check_in_count: 0,
  check_out_count: 0,
  average_check_in: '--',
  average_check_out: '--',
})

const attendanceSummaryData  = ref({
  total_employees: 0,
  present_today: 0,
  absent_today: 0,
  late_today: 0,
  half_day: 0,
})
const submitAttendanceEdit = () => {
  saveAttendanceEdit()
}
const leaveTypesData = ref([])
const loadLeaveTypes = async () => {
  try {
    const result = await fetchLeaveTypes()
    leaveTypesData.value = Array.isArray(result) ? result : []
    console.log('✅ Leave types loaded:', leaveTypesData.value.length)
  } catch (error) {
    console.error('❌ Failed to load leave types:', error)
    leaveTypesData.value = []
  }
}

const assetTypesData = ref([])
const loadAssetTypes = async () => {
  try {
    const result = await fetchAssetTypes()
    assetTypesData.value = Array.isArray(result) ? result : []
    console.log('✅ Asset types loaded:', assetTypesData.value.length)
  } catch (error) {
    console.error('❌ Failed to load asset types:', error)
    assetTypesData.value = []
  }
}

// ========== EMPLOYEES DATA FROM API ==========
const employeesDirectory = ref([])
const loadingEmployees = ref(false)

// Fetch real employees from API
const fetchRealEmployees = async () => {
  loadingEmployees.value = true
  const fallbackEmployees = () => ([
    {
      id: 340,
      name: 'Maria Guan',
      email: 'mariajoun@gmail.com',
      phone: '+971 56125 4568',
      avatar: 'https://i.pravatar.cc/80?img=47',
      designation: 'Senior Accountant',
      department: 'Finance',
      branch: 'Abu Dhabi',
      joiningDate: '14 Jan 2024',
      visaValidity: '14 Jan 2027',
      passportNumber: 'N12345678',
      employment_status: 'active',
      status: 'active',
      statusText: 'Active',
      statusType: 'active',
      nationality: 'Indian',
      salary: '2000.00',
      salary_type: 'monthly',
      supervisor: 'Khalid Al Mazrouei',
      role_name: 'Employee',
      employee_code: 'EMP-340',
      email_work: 'mariajoun@gmail.com',
      email_personal: 'mariajoun@gmail.com',
      phone_company: '+971 56125 4568',
      phone_personal: '+91 8136548745',
      address_inside_uae: 'Al Wahda, Near Bus Station, Abu Dhabi, United Arab Emirates',
      address_outside_uae: 'Al Wahda, Near Bus Station, Abu Dhabi, United Arab Emirates',
      birth_date: '1997-01-14',
      dob: '14 Jan 1997',
      marital_status: 'Single',
    },
  ])
  try {
    const response = await api.get('/employees', {
      params: {
        per_page: 1000
      }
    })

    const payload = response?.data
    const employeesPayload = Array.isArray(payload?.data?.data)
      ? payload.data.data
      : Array.isArray(payload?.data)
        ? payload.data
        : Array.isArray(payload)
          ? payload
          : []

    if (employeesPayload.length > 0) {
      employeesDirectory.value = employeesPayload.map(emp => ({
        id: emp.id,
        name: emp.name || emp.full_name || '-',
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
        salary_type: emp.salary_type || 'monthly',
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
    } else {
      console.warn('No employees array found in /employees response:', payload)
      employeesDirectory.value = fallbackEmployees()
    }
  } catch (error) {
    console.error('Error fetching employees:', error)
    employeesDirectory.value = fallbackEmployees()
  } finally {
    loadingEmployees.value = false
  }
}

// ========== UI State ==========
const headerTabs = ['Employees', 'Payroll', 'Leave / Attendance', 'Career', 'Assets']
const activeTab = ref('Employees')
const openHeaderMenu = ref(null)
const topbarTabsRef = ref(null)
const hrTopbarRef = ref(null)
const payrollSectionLabel = ref('Manage Salary')
const isMobileViewport = ref(false)
const overviewSearch = ref('')
const selectedOverviewEmployee = ref(null)
const searchKeyword = ref('')
const page = ref(1)
const perPage = 10
const openEmployeeFilters = ref(false)
const showApplyLeaveModal = ref(false)
const showCreateAttendanceModal = ref(false)
const showUnifiedDatePicker = ref(false)
const datePickerValue = ref(null)
const activeDateField = ref('')
const leaveSectionMode = ref('leave')
const showLeaveSearchModal = ref(false)
const showCareerSearchModal = ref(false)
const showAttendanceSearchModal = ref(false)
const attendanceSearchAnchorRef = ref(null)
const attendanceSearchInputRef = ref(null)
const attendanceSearchInputFocused = ref(false)
let attendanceSearchBlurTimer = null
const showAnnouncementSearchModal = ref(false)
const showAnnouncementModal = ref(false)
const openLeaveRowMenuId = ref(null)
const openAttendanceRowMenuId = ref(null)
const openAnnouncementRowMenuId = ref(null)
const attendanceRowMenuStyle = ref({})
const leaveRowMenuStyle = ref({})
const announcementRowMenuStyle = ref({})
const editingAnnouncementId = ref(null)
const selectedLeaveRow = ref(null)
const showLeaveDetailModal = ref(false)
const showLeaveApproveModal = ref(false)
const showLeaveRejectModal = ref(false)
const leavePage = ref(1)
const leavePerPage = 10
const assetsSearch = ref('')
const showAssetSearchModal = ref(false)
const showAssetCreateModal = ref(false)
const assetsMgmtRef = ref(null)
const assetsPage = ref(1)
const assetsPerPage = 10
const careerSectionMode = ref('manage-recruitments')
const careerSearchKeyword = ref('')
const careerPage = ref(1)
const careerPerPage = 10
const openEmployeeRowMenuId = ref(null)
const openAssetRowMenuId = ref(null)
const openCareerRowMenuId = ref(null)
const employeeFilterChips = ['Finance', 'Marketing', 'HR Department', 'Sales', 'Operations', 'Active', 'In Active']
const selectedFilterChip = ref('Marketing')
const showAddEmployeeModal = ref(false)
const employeesRefreshKey = ref(0)
const isEditEmployeeMode = ref(false)
const editingEmployeeId = ref(null)
const profileImageInputRef = ref(null)
const addEmployeeProfilePreview = ref('')
const addEmployeeProfileFile = ref(null)
const employeeRowMenuStyle = ref({})
const assetRowMenuStyle = ref({})
const careerRowMenuStyle = ref({})
const selectedEmployeeDetail = ref(null)
const employeeDetailTab = ref('company')
const employeeDetailView = ref('details')
const employeeDetailMainRef = ref(null)
const employeeCompanySectionRef = ref(null)
const employeeDocumentsSectionRef = ref(null)
const employeeBankSectionRef = ref(null)
const employeeAssetsSectionRef = ref(null)
const employeeInsuranceSectionRef = ref(null)
const showSectionEditModal = ref(false)
const editingSection = ref('')
const sectionEditForm = ref({})
const selectedDocumentType = ref('emirates_id')
const addEmployeeUploadedFiles = ref({})

const currentDocumentFile = computed({
  get: () => addEmployeeUploadedFiles.value[selectedDocumentType.value] ?? null,
  set: (file) => setDocumentFile(selectedDocumentType.value, file),
})

function setDocumentFile(docType, file) {
  const next = { ...addEmployeeUploadedFiles.value }
  if (!file) {
    delete next[docType]
  } else {
    next[docType] = file
  }
  addEmployeeUploadedFiles.value = next
}

function hasDocumentFile(docType) {
  return !!addEmployeeUploadedFiles.value[docType]
}
const showRequestDocumentModal = ref(false)
const showDocumentDetailModal = ref(false)
const showApproveDocumentModal = ref(false)
const showRejectDocumentModal = ref(false)
const selectedRequestedDocument = ref(null)
const requestDocumentForm = ref({
  documentType: '',
  description: '',
  employee: '',
})

const requestedDocuments = ref([])
const loadingRequestedDocuments = ref(false)
const documentTypesList = ref([])

const requestDocumentOptions = computed(() => {
  if (documentTypesList.value.length > 0) {
    return documentTypesList.value.map((t) => ({ value: t.id, label: t.name }))
  }
  return []
})

function mapDocumentRequestToRow(item) {
  return {
    id: item.id,
    employeeName: item.user?.name || item.employee?.name || item.user_name || '—',
    user_id: item.user_id,
    documentType: item.document_type?.name || '—',
    document_type_id: item.document_type_id,
    description: item.description || '--',
    requestedDate: formatDate(item.requested_date || item.created_at),
    status: item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'Pending',
    rejectionReason: item.rejection_reason || '',
    file_url: item.file_url || '',
    raw: item,
  }
}

const documentRequestsSearch = ref('')

const filteredDocumentRequests = computed(() => {
  const q = documentRequestsSearch.value.trim().toLowerCase()
  if (!q) return requestedDocuments.value
  return requestedDocuments.value.filter((doc) =>
    [doc.employeeName, doc.documentType, doc.description, doc.status]
      .some((v) => String(v || '').toLowerCase().includes(q)),
  )
})

const documentRequestEmployeeOptions = computed(() =>
  employeesDirectory.value.map((e) => ({ value: e.id, label: e.name })),
)

const loadDocumentTypesList = async () => {
  try {
    const result = await fetchDocumentTypes()
    documentTypesList.value = Array.isArray(result?.data) ? result.data : Array.isArray(result) ? result : []
  } catch (error) {
    console.error('Failed to load document types:', error)
    documentTypesList.value = []
  }
}

const loadRequestedDocuments = async (employeeId = null) => {
  loadingRequestedDocuments.value = true
  try {
    const params = {}
    if (employeeId) params.user_id = employeeId
    const result = await fetchDocumentRequests(params)
    const items = Array.isArray(result?.data) ? result.data : Array.isArray(result) ? result : []
    requestedDocuments.value = items.map(mapDocumentRequestToRow)
  } catch (error) {
    console.error('Failed to load document requests:', error)
    showNotification('Failed to load requested documents', 'error')
    requestedDocuments.value = []
  } finally {
    loadingRequestedDocuments.value = false
  }
}
const editingRequestedDocumentId = ref(null)
const approveDocumentFile = ref(null)
const rejectDocumentReason = ref('')
const employeeFilters = ref({
  name: '',
  department: '',
  designation: '',
  joiningDate: '',
  visaValidity: '',
  status: '',
})
const showAttendanceDetailModal = ref(false)
const selectedAttendanceRow = ref(null)
const attendanceDetailMode = ref('view')
const attendanceEditForm = ref({
  date: '',
  checkIn: '',
  checkOut: '',
  status: '',
  breakLabel: '',
  otLabel: '',
  description: '',
})
const careerSearchFilters = ref({
  jobTitle: '',
  postedDate: '',
  closingDate: '',
  department: '',
  type: '',
  status: '',
})

const careerFilterChips = ['Abu Dhabi', 'Dubai', 'Marketing', 'HR Department', 'Finance', 'Operations', 'Sales']
const selectedCareerFilterChip = ref('Marketing')

const careerJobTitleOptions = computed(() => Array.from(new Set(careerRows.value.map((j) => j.title))).filter(Boolean))
const careerDepartmentOptions = ['Marketing', 'HR Department', 'Finance', 'Operations', 'Sales']
const careerTypeOptions = ['Full-time', 'Part-time', 'Contract']
const careerStatusOptions = ['Open', 'On Hold', 'Closed']


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
const assetTypeOptions = computed(() => {
  if (assetTypesData.value.length > 0) {
    return assetTypesData.value.map(type => ({
      id: type.id,
      name: type.name || type.label || type
    }))
  }
  return [
    { id: 'laptop', name: 'Laptop' },
    { id: 'phone', name: 'Phone' },
    { id: 'printer', name: 'Printer' },
    { id: 'sim', name: 'SIM' },
    { id: 'charger', name: 'Charger' },
    { id: 'desktop', name: 'DeskTop' },
  ]
})
const assetStatusOptions = ['Assigned', 'Not Assigned', 'Working', 'In Repair', 'Used', 'New']
const assetConditionOptions = ['New', 'Used', 'Working']

const assetsRows = ref([])
const assetResponsiblePersons = ref([])
const showAssetUserPicker = ref(false)
const assetUserSearchQuery = ref('')
const assetUserPickerRef = ref(null)
const showAssetUserEditPicker = ref(false)
const assetUserEditSearchQuery = ref('')
const assetUserEditPickerRef = ref(null)
const assetUserOptions = computed(() => {
  if (!assetsRows.value || !Array.isArray(assetsRows.value)) {
    return []
  }
  
  if (assetsRows.value.length === 0) {
    return []
  }
  
  return Array.from(
    new Set(
      assetsRows.value
        .map((row) => row.userName)
        .filter((name) => name && name !== '—' && name !== '')
    )
  )
})
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
  if (!Array.isArray(assetResponsiblePersons.value) || assetResponsiblePersons.value.length === 0) {
    return []
  }
  
  const query = assetUserSearchQuery.value.trim().toLowerCase()
  
  if (!query) {
    return assetResponsiblePersons.value
  }
  console.log('DEBUG:', assetResponsiblePersons.value)
  return assetResponsiblePersons.value.filter((person) =>
    String(person.name || '').toLowerCase().includes(query) ||
    String(person.email || '').toLowerCase().includes(query)
  )
})
const selectedAssetResponsiblePersonEdit = computed(() =>
  assetResponsiblePersons.value.find((person) => Number(person.id) === Number(assetEditForm.value.assetUser)) || null,
)
const filteredAssetEditResponsiblePersons = computed(() => {
  if (!Array.isArray(assetResponsiblePersons.value) || assetResponsiblePersons.value.length === 0) {
    return []
  }
  
  const query = assetUserEditSearchQuery.value.trim().toLowerCase()
  
  if (!query) {
    return assetResponsiblePersons.value
  }
  console.log('DEBUG:', assetResponsiblePersons.value)
  return assetResponsiblePersons.value.filter((person) =>
    String(person.name || '').toLowerCase().includes(query) ||
    String(person.email || '').toLowerCase().includes(query)
  )
})
const assetEditForm = ref({
  assetId: '',
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
const hrSectionTab = ref('attendance')

const headerTabMenus = {
  Employees: ['Manage Employees', 'Document Requests'],
  Payroll: ['Manage Salary', 'Manage Pay Slip'],
  'Leave / Attendance': ['Leave Management', 'Attendance Management', 'Announcements'],
  Career: ['Manage Recruitments', 'Interviews', 'Career Lists'],
  Assets: ['Asset Directory', 'Asset Requests'],
}

const careerRows = ref([])
const loadingCareerJobs = ref(false)

function mapJobToRow(job) {
  return {
    id: job.id,
    title: job.title,
    department: job.department?.name || '—',
    department_id: job.department_id,
    branch: job.branch?.name || '—',
    branch_id: job.branch_id,
    type: job.job_type,
    openings: String(job.openings ?? '0').padStart(2, '0'),
    postedDate: formatDate(job.posted_date),
    closingDate: job.closing_date ? formatDate(job.closing_date) : '--',
    hiringManager: job.hiring_manager?.name || '—',
    hiringManagerAvatar: job.hiring_manager?.avatar || 'https://i.pravatar.cc/80?img=1',
    applicants: String(job.applicants_count ?? 0),
    status: job.status === 'open' ? 'Open' : job.status === 'on_hold' ? 'On Hold' : 'Closed',
    raw: job,
  }
}

const loadCareerJobs = async () => {
  loadingCareerJobs.value = true
  try {
    const params = { per_page: 100 }
    if (careerSearchKeyword.value) params.search = careerSearchKeyword.value
    const result = await fetchJobs(params)
    const items = Array.isArray(result?.data) ? result.data : Array.isArray(result) ? result : []
    careerRows.value = items.map(mapJobToRow)
  } catch (error) {
    console.error('Failed to load jobs:', error)
    showNotification('Failed to load jobs', 'error')
    careerRows.value = []
  } finally {
    loadingCareerJobs.value = false
  }
}
const selectedCareerJob = ref(null)
const careerApplicantsSearch = ref('')
const selectedCareerApplicantId = ref(1)
const careerApplicantSectionsOpen = ref({
  details: true,
  resume: true,
  questions: true,
  notes: true,
})
const careerApplicantsRows = ref([
  {
    id: 1,
    name: 'Emmanual Martin',
    email: 'emmanualmartinjos@gmail.com',
    location: 'Abu Dhabi, United Arab Emirates',
    avatar: 'https://i.pravatar.cc/80?img=12',
    decision: 'Selected',
    appliedAgo: '2 Weeks Ago',
    appliedAt: '12 February 2026',
    availabilityStatus: 'Pending',
    hiringStatus: 'Onboarding',
    interviewStatus: 'Not Scheduled',
    visaStatus: 'Visit Visa',
    visaExpiry: '14 / 11 / 2026',
    phone: '+971 56 123 4569',
    gender: 'Mail',
    noticePeriod: '30 Days',
    dob: '11 / 12 / 2001',
    currentSalary: '5000 AED',
    expectedSalary: '7000 AED',
    nationality: 'Indian',
    totalExperience: '3',
    uaeExperience: '2',
    notes: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean lorem quam, eleifend vitae commodo vitae.',
    questions: [
      { question: 'A team member is frustrated with a project. What’s your approach?', answer: 'Yes', idealAnswer: 'No' },
      { question: 'If a customer is angry and yelling at you, what is the best response?', answer: 'Candidate Not Answered', idealAnswer: 'No' },
      { question: 'How many years of administrative experience do you currently have?', answer: '3', idealAnswer: '3' },
    ],
    resumeImage: 'https://placehold.co/920x1200/ffffff/0f172a?text=Resume+Preview',
  },
  {
    id: 2,
    name: 'Emmanual Martin',
    email: 'emmanualmartinjos@gmail.com',
    location: 'Abu Dhabi, United Arab Emirates',
    avatar: 'https://i.pravatar.cc/80?img=14',
    decision: 'Rejected',
    appliedAgo: '2 Weeks Ago',
    appliedAt: '10 February 2026',
    availabilityStatus: 'Available',
    hiringStatus: 'Screening',
    interviewStatus: 'Scheduled',
    visaStatus: 'Employment Visa',
    visaExpiry: '01 / 03 / 2027',
    phone: '+971 56 111 4569',
    gender: 'Mail',
    noticePeriod: '15 Days',
    dob: '14 / 03 / 1999',
    currentSalary: '4500 AED',
    expectedSalary: '6000 AED',
    nationality: 'Jordanian',
    totalExperience: '4',
    uaeExperience: '1',
    notes: 'Candidate profile rejected after technical screening.',
    questions: [],
    resumeImage: 'https://placehold.co/920x1200/ffffff/0f172a?text=Resume+Preview',
  },
])

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
    { key: 'employees', label: 'Total Employees', value: total, icon: 'lucide:users', bgColor: 'rgba(115, 62, 135, 0.12)', iconColor: '#733E87' },
    { key: 'applications', label: 'Job Applications', value: 352, icon: 'lucide:file-text', bgColor: '#f4e8ff', iconColor: '#9333ea' },
    { key: 'new-employees', label: 'New Employees', value: 56, icon: 'lucide:user-round-plus', bgColor: '#e8f8ed', iconColor: '#16a34a' },
    { key: 'attendance', label: 'Todays Attendance', value: active, icon: 'lucide:calendar-check-2', bgColor: 'rgba(115, 62, 135, 0.1)', iconColor: '#733E87' },
  ]
})

const employeeStats = computed(() => {
  const total = employeesDirectory.value.length
  const active = employeesDirectory.value.filter(e => e.status === 'active').length
  const inactive = employeesDirectory.value.filter(e => e.status === 'in_active').length
  
  return [
    { key: 'employees', label: 'Total Employees', value: total, icon: 'lucide:users', bgColor: 'rgba(115, 62, 135, 0.12)', iconColor: '#733E87' },
    { key: 'applications', label: 'New Employees', value: 25, icon: 'lucide:file-text', bgColor: '#f4e8ff', iconColor: '#9333ea' },
    { key: 'new-employees', label: 'Resigned Employees', value: 5, icon: 'lucide:user-round-plus', bgColor: '#e8f8ed', iconColor: '#16a34a' },
    { key: 'attendance', label: 'Active Employees', value: active, icon: 'lucide:calendar-check-2', bgColor: 'rgba(115, 62, 135, 0.1)', iconColor: '#733E87' },
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


const branchOptions = computed(() => {
  return branchesList.value.map(b => ({
    value: b.id,
    label: b.name || b
  }))
})

const designationOptions = computed(() => {
  return designationsList.value.map(d => ({
    value: d.id,
    label: d.name || d
  }))
})

const departmentOptions = computed(() => {
  return departmentsList.value.map(d => ({
    value: d.id,
    label: d.name || d
  }))
})

const supervisorOptions = computed(() => {
  return managersList.value.map(m => ({
    value: m.id,
    label: m.name || m
  }))
})
const nationalityOptions = ['UAE', 'Egypt', 'India', 'Pakistan', 'Morocco', 'Jordan', 'Philippines']
const salaryTypeOptions = ['daily', 'monthly', 'yearly']
const maritalStatusOptions = ['Single', 'Married', 'Divorced', 'Widowed']
const bankNameOptions = ['Emirates NBD', 'ADCB', 'Mashreq', 'FAB', 'RAKBANK']
const policyTypeOptions = ['Basic Health', 'Standard Health', 'Premium Health', 'Life Insurance']
const employeeDocumentTypes = [
  { label: 'Emirates ID', value: 'emirates_id' },
  { label: 'Labor Card', value: 'labor_card' },
  { label: 'Passport', value: 'passport' },
  { label: 'Visa', value: 'visa' },
  { label: 'Passport Photo', value: 'passport_photo' },
  { label: 'Insurance Card', value: 'insurance_card' },
  { label: 'Broker License', value: 'broker_license' },
  { label: 'ILOE Certificate', value: 'iloe_certificate' },
  { label: 'Company Stamp', value: 'company_stamp' },
  { label: 'Labor Contract', value: 'labor_contract' },
  { label: 'Job Offer Letter', value: 'job_offer_letter' },
  { label: 'Signature', value: 'signature' },
  { label: 'Attested Certificates', value: 'attested_certificate' },
]
const statusOptions = ['Active', 'In Active', 'Blocked']

const leaveTypeOptions = computed(() => {
  if (leaveTypesData.value.length > 0) {
    return leaveTypesData.value.map(t => ({
    value: t.id,
    label: t.name || t
  }))
  }
 
})

const leaveTypeFilterOptions = computed(() => {
  if (leaveTypesData.value.length > 0) {
    return leaveTypesData.value.map(type => ({
      id: type.id,
      name: type.name || type.label || type
    }))
  }
  return [
    { id: 'annual', name: 'Annual' },
    { id: 'sick', name: 'Sick' },
    { id: 'casual', name: 'Casual' },
    { id: 'maternity', name: 'Maternity' },
    { id: 'paternity', name: 'Paternity' },
  ]
})

const leaveStatusOptions = ['Approved', 'Pending', 'Rejected']
const announcementBranchOptions = computed(() => {
  return branchesList.value.map(b => ({
    value: b.id,
    label: b.name || b
  }))
})
const announcementDepartmentOptions = computed(() => {
  return departmentsList.value.map(d => ({
    value: d.id,
    label: d.name || d
  }))
})
const leaveSearchChips = ['Approved', 'Pending', 'Rejected']
const selectedLeaveSearchChip = ref('Rejected')
const defaultLeaveSearchFilters = () => ({
  employee: '',
  leaveType: '',
  appliedDate: '',
  status: '',
})
const leaveSearchFilters = ref(defaultLeaveSearchFilters())
const attendanceSearchChips = ['Present', 'Absent', 'Late', 'Half Day']
const selectedAttendanceSearchChip = ref('')
const attendanceTypeOptions = ['Present', 'Absent', 'Late', 'Half Day']
const attendanceStatusOptions = ['Present', 'Absent', 'Late', 'Half Day']
const attendanceCreateTypeOptions = ['Visit', 'Office', 'Call', 'Work From Home', 'Out Of Office', 'Official Deputation', 'Paid Time Off', 'Remote Work']
const attendanceBreakOptions = ['0 Mnts', '30 Mnts', '1 hr', '1 hr 30 mnts', '2 hr 30 mnts']
const attendanceOtOptions = ['0 Mnts', '30 Mnts', '1 hr', '1 hr 30 mnts', '2 hr']
const defaultAttendanceSearchFilters = () => ({
  employee: '',
  department: '',
  attendanceDate: '',
  type: '',
  status: '',
})
const attendanceSearchFilters = ref(defaultAttendanceSearchFilters())
const hasActiveAttendanceFilters = computed(() => {
  const f = attendanceSearchFilters.value
  return !!(
    f.employee ||
    f.department ||
    f.attendanceDate ||
    f.type ||
    f.status ||
    selectedAttendanceSearchChip.value
  )
})
const attendanceEmployeeOptions = computed(() => {
  const seen = new Set()
  const options = []
  const add = (id, code, name) => {
    const label = code && name ? `#${code} ${name}` : (name || (id ? `#EMP${formatEmpId(id)}` : ''))
    const key = String(id || label).trim()
    if (!label || seen.has(key)) return
    seen.add(key)
    options.push(label)
  }
  for (const emp of employeesDirectory.value || []) {
    add(emp.id, emp.employee_code, emp.name)
  }
  for (const row of employees.value || []) {
    const code = String(row.employee_id || '').startsWith('EMP') ? row.employee_id : `EMP-${row.employee_id}`
    add(row.employee_id, code, row.employee_name)
  }
  return options.sort((a, b) => a.localeCompare(b))
})
const attendanceDepartmentOptions = computed(() => {
  const set = new Set()
  for (const emp of employeesDirectory.value || []) {
    const d = String(emp.department || '').trim()
    if (d && d !== '-') set.add(d)
  }
  for (const row of employees.value || []) {
    const d = String(row.department || '').trim()
    if (d && d !== '-') set.add(d)
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b))
})
const defaultApplyLeaveForm = () => ({
  employee: '',
  leaveType: '',
  startDate: '',
  endDate: '',
  reason: '',
  isHalfDay: false,  
  halfDayType: null,
})
const applyLeaveForm = ref(defaultApplyLeaveForm())
const applyLeaveAttachment = ref(null)
const defaultAnnouncementForm = () => ({
  title: '',
  branch: '',
  department: '',
  startDate: '',
  endDate: '',
  description: '',
})
const announcementForm = ref(defaultAnnouncementForm())
const announcementSearchFilters = ref({
  title: '',
  branch: '',
  department: '',
})
const defaultCreateAttendanceForm = () => ({
  employee: '',
  type: '',
  status: '',
  date: '',
  checkIn: '',
  checkOut: '',
  breakLabel: '',
  otLabel: '',
  description: '',
})
const createAttendanceForm = ref(defaultCreateAttendanceForm())
const createAttendanceAttachment = ref(null)
const applyLeaveEmployeeOptions = computed(() =>
  employeesDirectory.value.map((employee) => `#${employee.employee_code} ${employee.name}`),
)

// ========== LEAVE ROWS (Mock for now, can be replaced with API) ==========
const leaveRows = ref([
  { id: 1, empId: '#EMPO01', employeeName: 'Maria Guan', avatar: 'https://i.pravatar.cc/80?img=47', designation: 'Senior Accountant', leaveType: 'Annual', startDate: '05 Feb 2026', endDate: '05 Feb 2026', days: '25', reason: 'Family Trip', appliedDate: '15 Jan 2026', status: 'Approved', approvedBy: 'HR Manager' },
  { id: 2, empId: '#EMPO02', employeeName: 'Ahmad Al Daghash', avatar: 'https://i.pravatar.cc/80?img=12', designation: 'UI/UX Designer', leaveType: 'Sick', startDate: '10 Feb 2026', endDate: '11 Feb 2026', days: '02', reason: 'Fever', appliedDate: '10 Feb 2026', status: 'Approved', approvedBy: 'HR Manager' },
  { id: 3, empId: '#EMPO03', employeeName: 'Adeel Malik', avatar: 'https://i.pravatar.cc/80?img=33', designation: 'Sales Executive', leaveType: 'Casual', startDate: '21 Apr 2026', endDate: '21 Apr 2026', days: '01', reason: 'Personal Errand', appliedDate: '19 Apr 2026', status: 'Rejected', approvedBy: '--' },
])
const announcementRows = ref([])
const loadingAnnouncements = ref(false)
const announcementsPage = ref(1)
const announcementsPerPage = 10

// ========== ASSETS ROWS ==========

const defaultAddEmployeeForm = () => ({
  full_name: '',
  nationality: '',
  phone: '',
  home_country_phone_number: '',
  salary_type: '',
  email: '',
  address_inside_uae: '',
  address_outside_uae: '',
  salary: '',
  father_name: '',
  mother_name: '',
  marital_status: '',
  religion: '',
  emergency_contact_name: '',
  emergency_email: '',
  emergency_phone_number: '',
  branch: '',
  designation: '',
  department: '',
  supervisor: '',
  user_type: '',
  probation_end_date: '',
  joining_date: '',
  visa_validity: '',
  sponsor: '',
  contract_joining_date: '',
  visa_quota: '',
  gratuity_termination: '',
  vehicle: '',
  emirates_id_number: '',
  documents_expiry_date: '',
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
   emirates_id_number: '',
  documents_expiry_date: '',
  labor_card_number: '',
  labor_card_expiry_date: '',
  passport_number: '',
  passport_expiry_date: '',
  visa_number: '',
  visa_expiry_date: '',
  policy_number: '',
  insurance_expiry_date: '',

  contract_number: '',

  certificate_name: '',
  attestation_status: '',
  branch_id: '',    
  designation: '',
  designation_id: '',
  department: '',
  department_id: '',
  supervisor: '',
  supervisor_id: '',
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
  if (path === 'addEmployeeForm.probation_end_date') return addEmployeeForm.value.probation_end_date
  if (path === 'addEmployeeForm.contract_joining_date') return addEmployeeForm.value.contract_joining_date
  if (path === 'addEmployeeForm.gratuity_termination') return addEmployeeForm.value.gratuity_termination
  if (path === 'addEmployeeForm.documents_expiry_date') return addEmployeeForm.value.documents_expiry_date
  if (path === 'addEmployeeForm.insurance_start_date') return addEmployeeForm.value.insurance_start_date
  if (path === 'addEmployeeForm.insurance_expiry_date') return addEmployeeForm.value.insurance_expiry_date
  if (path === 'sectionEditForm.joiningDate') return sectionEditForm.value.joiningDate
  if (path === 'sectionEditForm.visaValidity') return sectionEditForm.value.visaValidity
  if (path === 'sectionEditForm.dob') return sectionEditForm.value.dob
  if (path === 'sectionEditForm.insurance_start_date') return sectionEditForm.value.insurance_start_date
  if (path === 'sectionEditForm.insurance_expiry_date') return sectionEditForm.value.insurance_expiry_date
  if (path === 'assetSearchFilters.createdOn') return assetSearchFilters.value.createdOn
  if (path === 'assetSearchFilters.purchaseDate') return assetSearchFilters.value.purchaseDate
  if (path === 'assetCreateForm.handoverDate') return assetCreateForm.value.handoverDate
  if (path === 'assetCreateForm.returnDate') return assetCreateForm.value.returnDate
  if (path === 'assetCreateForm.purchaseDate') return assetCreateForm.value.purchaseDate
  if (path === 'assetCreateForm.warrantyDate') return assetCreateForm.value.warrantyDate
  if (path === 'assetEditForm.handoverDate') return assetEditForm.value.handoverDate
  if (path === 'assetEditForm.returnDate') return assetEditForm.value.returnDate
  if (path === 'assetEditForm.purchaseDate') return assetEditForm.value.purchaseDate
  if (path === 'assetEditForm.warrantyDate') return assetEditForm.value.warrantyDate
  if (path === 'applyLeaveForm.startDate') return applyLeaveForm.value.startDate
  if (path === 'applyLeaveForm.endDate') return applyLeaveForm.value.endDate
  if (path === 'announcementForm.startDate') return announcementForm.value.startDate
  if (path === 'announcementForm.endDate') return announcementForm.value.endDate
  if (path === 'createAttendanceForm.date') return createAttendanceForm.value.date
  if (path === 'leaveSearchFilters.appliedDate') return leaveSearchFilters.value.appliedDate
  if (path === 'attendanceSearchFilters.attendanceDate') return attendanceSearchFilters.value.attendanceDate
  if (path === 'attendanceEditForm.date') return attendanceEditForm.value.date
  if (path === 'careerSearchFilters.postedDate') return careerSearchFilters.value.postedDate
  if (path === 'careerSearchFilters.closingDate') return careerSearchFilters.value.closingDate
  return ''
}

function setFieldValueByPath(path, value) {
  if (path === 'dateFilter') dateFilter.value = value
  else if (path === 'employeeFilters.joiningDate') employeeFilters.value.joiningDate = value
  else if (path === 'employeeFilters.visaValidity') employeeFilters.value.visaValidity = value
  else if (path === 'addEmployeeForm.joining_date') addEmployeeForm.value.joining_date = value
  else if (path === 'addEmployeeForm.visa_validity') addEmployeeForm.value.visa_validity = value
  else if (path === 'addEmployeeForm.probation_end_date') addEmployeeForm.value.probation_end_date = value
  else if (path === 'addEmployeeForm.contract_joining_date') addEmployeeForm.value.contract_joining_date = value
  else if (path === 'addEmployeeForm.gratuity_termination') addEmployeeForm.value.gratuity_termination = value
  else if (path === 'addEmployeeForm.documents_expiry_date') addEmployeeForm.value.documents_expiry_date = value
  else if (path === 'addEmployeeForm.insurance_start_date') addEmployeeForm.value.insurance_start_date = value
  else if (path === 'addEmployeeForm.insurance_expiry_date') addEmployeeForm.value.insurance_expiry_date = value
  else if (path === 'sectionEditForm.joiningDate') sectionEditForm.value.joiningDate = value
  else if (path === 'sectionEditForm.visaValidity') sectionEditForm.value.visaValidity = value
  else if (path === 'sectionEditForm.dob') sectionEditForm.value.dob = value
  else if (path === 'sectionEditForm.insurance_start_date') sectionEditForm.value.insurance_start_date = value
  else if (path === 'sectionEditForm.insurance_expiry_date') sectionEditForm.value.insurance_expiry_date = value
  else if (path === 'assetSearchFilters.createdOn') assetSearchFilters.value.createdOn = value
  else if (path === 'assetSearchFilters.purchaseDate') assetSearchFilters.value.purchaseDate = value
  else if (path === 'assetCreateForm.handoverDate') assetCreateForm.value.handoverDate = value
  else if (path === 'assetCreateForm.returnDate') assetCreateForm.value.returnDate = value
  else if (path === 'assetCreateForm.purchaseDate') assetCreateForm.value.purchaseDate = value
  else if (path === 'assetCreateForm.warrantyDate') assetCreateForm.value.warrantyDate = value
  else if (path === 'assetEditForm.handoverDate') assetEditForm.value.handoverDate = value
  else if (path === 'assetEditForm.returnDate') assetEditForm.value.returnDate = value
  else if (path === 'assetEditForm.purchaseDate') assetEditForm.value.purchaseDate = value
  else if (path === 'assetEditForm.warrantyDate') assetEditForm.value.warrantyDate = value
  else if (path === 'applyLeaveForm.startDate') applyLeaveForm.value.startDate = value
  else if (path === 'applyLeaveForm.endDate') applyLeaveForm.value.endDate = value
  else if (path === 'announcementForm.startDate') announcementForm.value.startDate = value
  else if (path === 'announcementForm.endDate') announcementForm.value.endDate = value
  else if (path === 'createAttendanceForm.date') createAttendanceForm.value.date = value
  else if (path === 'leaveSearchFilters.appliedDate') leaveSearchFilters.value.appliedDate = value
  else if (path === 'attendanceSearchFilters.attendanceDate') attendanceSearchFilters.value.attendanceDate = value
  else if (path === 'attendanceEditForm.date') attendanceEditForm.value.date = value
  else if (path === 'careerSearchFilters.postedDate') careerSearchFilters.value.postedDate = value
  else if (path === 'careerSearchFilters.closingDate') careerSearchFilters.value.closingDate = value
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
 const rows = Array.isArray(assetsRows.value) ? assetsRows.value : []
  
  if (!rows.length) {
    return []
  }
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

const filteredAnnouncementRows = computed(() => {
  const f = announcementSearchFilters.value
  return announcementRows.value.filter((row) => {
    if (f.title && !String(row.title || '').toLowerCase().includes(String(f.title).toLowerCase())) return false
    if (f.branch && String(row.branch || '').toLowerCase() !== String(f.branch).toLowerCase()) return false
    if (f.department && String(row.department || '').toLowerCase() !== String(f.department).toLowerCase()) return false
    return true
  })
})
const announcementsTotalPages = computed(() => Math.max(1, Math.ceil(filteredAnnouncementRows.value.length / announcementsPerPage)))
const pagedAnnouncementRows = computed(() => {
  const start = (announcementsPage.value - 1) * announcementsPerPage
  return filteredAnnouncementRows.value.slice(start, start + announcementsPerPage)
})
const announcementsStartEntry = computed(() => (filteredAnnouncementRows.value.length ? (announcementsPage.value - 1) * announcementsPerPage + 1 : 0))
const announcementsEndEntry = computed(() => Math.min(announcementsPage.value * announcementsPerPage, filteredAnnouncementRows.value.length))
const announcementsPaginationItems = computed(() => {
  const total = announcementsTotalPages.value
  const current = announcementsPage.value
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
function mapAnnouncementToRow(item) {
  return {
    id: item.id,
    title: item.title,
    startDate: formatDate(item.start_date),
    endDate: item.end_date ? formatDate(item.end_date) : '--',
    branch: item.branch?.name || 'All',
    department: item.department?.name || 'All',
    branch_id: item.branch_id || '',
    department_id: item.department_id || '',
    description: item.description || '--',
    raw: item,
  }
}
const loadAnnouncementsData = async () => {
  loadingAnnouncements.value = true
  try {
    const params = {
      per_page: announcementsPerPage,
      page: announcementsPage.value,
    }
    if (announcementSearchFilters.value.title) params.search = announcementSearchFilters.value.title
    if (announcementSearchFilters.value.branch) params.branch_id = announcementSearchFilters.value.branch
    if (announcementSearchFilters.value.department) params.department_id = announcementSearchFilters.value.department

    const result = await fetchAnnouncements(params)
    const items = Array.isArray(result?.data) ? result.data : Array.isArray(result) ? result : []
    announcementRows.value = items.map(mapAnnouncementToRow)
  } catch (error) {
    console.error('Failed to load announcements:', error)
    showNotification('Failed to load announcements', 'error')
    announcementRows.value = []
  } finally {
    loadingAnnouncements.value = false
  }
}
const filteredCareerRows = computed(() => {
  const keyword = careerSearchKeyword.value.trim().toLowerCase()
  if (!keyword) return careerRows.value
  return careerRows.value.filter((row) =>
    [row.title, row.department, row.branch, row.type, row.postedDate]
      .some((v) => String(v || '').toLowerCase().includes(keyword)),
  )
})
const filteredCareerApplicants = computed(() => {
  const keyword = careerApplicantsSearch.value.trim().toLowerCase()
  if (!keyword) return careerApplicantsRows.value
  return careerApplicantsRows.value.filter((row) =>
    [row.name, row.email, row.location, row.decision].some((v) => String(v || '').toLowerCase().includes(keyword)),
  )
})
const selectedCareerApplicant = computed(() =>
  careerApplicantsRows.value.find((row) => row.id === selectedCareerApplicantId.value) || filteredCareerApplicants.value[0] || null,
)
const careerApplicantsCount = computed(() => filteredCareerApplicants.value.length)
const careerTotalPages = computed(() => Math.max(1, Math.ceil(filteredCareerRows.value.length / careerPerPage)))
const pagedCareerRows = computed(() => {
  const start = (careerPage.value - 1) * careerPerPage
  return filteredCareerRows.value.slice(start, start + careerPerPage)
})
const careerStartEntry = computed(() => (filteredCareerRows.value.length ? (careerPage.value - 1) * careerPerPage + 1 : 0))
const careerEndEntry = computed(() => Math.min(careerPage.value * careerPerPage, filteredCareerRows.value.length))
const careerPaginationItems = computed(() => {
  const total = careerTotalPages.value
  const current = careerPage.value
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

const assetsTotalPages = computed(() => {
  if (!filteredAssetsRows.value || !Array.isArray(filteredAssetsRows.value)) {
    return 1
  }
  return Math.max(1, Math.ceil(filteredAssetsRows.value.length / assetsPerPage))
})
const pagedAssetsRows = computed(() => {
    if (!filteredAssetsRows.value || !Array.isArray(filteredAssetsRows.value)) {
    return []
  }
  const start = (assetsPage.value - 1) * assetsPerPage
  return filteredAssetsRows.value.slice(start, start + assetsPerPage)
})
const assetsStartEntry = computed(() => {
  if (!filteredAssetsRows.value || !Array.isArray(filteredAssetsRows.value)) {
    return 0
  }
  return filteredAssetsRows.value.length ? (assetsPage.value - 1) * assetsPerPage + 1 : 0
})

const assetsEndEntry = computed(() => {
  if (!filteredAssetsRows.value || !Array.isArray(filteredAssetsRows.value)) {
    return 0
  }
  return Math.min(assetsPage.value * assetsPerPage, filteredAssetsRows.value.length)
})
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
function normalizeAttendanceStatus(value) {
  return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ')
}

function getAttendanceRowDepartment(row) {
  const fromRow = String(row?.department || '').trim()
  if (fromRow && fromRow !== '-') return fromRow
  return String(employeeDepartmentById.value.get(String(row?.employee_id || '').trim()) || '').trim()
}

function matchAttendanceEmployeeFilter(row, filterLabel) {
  const q = String(filterLabel || '').trim().toLowerCase()
  if (!q) return true
  const name = String(row.employee_name || '').toLowerCase()
  const id = String(row.employee_id || '').toLowerCase()
  const empId = `emp${formatEmpId(row.employee_id)}`.toLowerCase()
  const directory = employeesDirectory.value.find((emp) => {
    const label = `#${emp.employee_code} ${emp.name}`.toLowerCase()
    return label === q || label.includes(q)
  })
  if (directory && String(directory.id) === String(row.employee_id)) return true
  return name.includes(q) || id.includes(q) || empId.includes(q.replace(/\s/g, '')) || q.includes(name)
}

function attendanceRowSearchBlob(row) {
  return [
    row.employee_name,
    row.employee_id,
    `EMP${formatEmpId(row.employee_id)}`,
    row.status,
    row.department,
    getAttendanceRowDepartment(row),
    formatDate(row.date),
    formatTime(row.check_in),
    formatTime(row.check_out),
    formatBreakDisplay(row),
    formatOtDisplay(row),
  ]
    .map((v) => String(v || '').toLowerCase())
    .join(' ')
}

const filteredRows = computed(() => {
  const f = attendanceSearchFilters.value
  const chip = selectedAttendanceSearchChip.value
  const keyword = searchKeyword.value.trim().toLowerCase()

  return (employees.value || []).filter((row) => {
    if (chip && normalizeAttendanceStatus(row.status) !== normalizeAttendanceStatus(chip)) return false
    if (f.employee && !matchAttendanceEmployeeFilter(row, f.employee)) return false
    if (f.department) {
      const dept = getAttendanceRowDepartment(row).toLowerCase()
      if (dept !== String(f.department).trim().toLowerCase()) return false
    }
    if (f.attendanceDate) {
      const rowDate = row.date ? String(row.date).slice(0, 10) : ''
      if (rowDate !== String(f.attendanceDate).slice(0, 10)) return false
    }
    if (f.type && normalizeAttendanceStatus(row.status) !== normalizeAttendanceStatus(f.type)) return false
    if (f.status && normalizeAttendanceStatus(row.status) !== normalizeAttendanceStatus(f.status)) return false
    if (keyword && !attendanceRowSearchBlob(row).includes(keyword)) return false
    return true
  })
})

const timeSortValue = (value) => {
  if (!value) return Number.MAX_SAFE_INTEGER
  const parsed = new Date(value).getTime()
  return Number.isNaN(parsed) ? Number.MAX_SAFE_INTEGER : parsed
}

const employeeManagerById = computed(() => {
  const map = new Map()
  for (const emp of employeesDirectory.value || []) {
    const id = String(emp?.id || '').trim()
    const manager = String(emp?.supervisor || '').trim()
    if (id && manager && manager !== '-') {
      map.set(id, manager)
    }
  }
  return map
})

const employeePositionById = computed(() => {
  const map = new Map()
  for (const emp of employeesDirectory.value || []) {
    const id = String(emp?.id || '').trim()
    const position = String(emp?.designation || '').trim()
    if (id && position && position !== '-') {
      map.set(id, position)
    }
  }
  return map
})

const salesPositionFilter = ref('')
const selectedDepartmentView = ref('')

const attendancePositionName = (member) => {
  const directoryPosition = employeePositionById.value.get(String(member?.employee_id || '').trim())
  const fromAgent = member?.agent_record?.role_name || member?.agent_record?.designation
  return String(directoryPosition || fromAgent || '').trim()
}

const isSalesPosition = (position) => String(position || '').toLowerCase().includes('sales')

const salesPositionOptions = computed(() => {
  const set = new Set()
  for (const member of mergedData.value || []) {
    const position = attendancePositionName(member)
    if (isSalesPosition(position)) set.add(position)
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b))
})

const attendanceManagerName = (member) => {
  const directoryManager = employeeManagerById.value.get(String(member?.employee_id || '').trim())
  const fromAgent = member?.agent_record?.parent_name || member?.agent_record?.team_lead_name || member?.agent_record?.manager_name
  return String(directoryManager || fromAgent || 'Unassigned').trim() || 'Unassigned'
}

const matchesSalesPositionFilter = (member) => {
  const position = attendancePositionName(member)
  if (!isSalesPosition(position)) return false
  if (!salesPositionFilter.value) return true
  return String(position).toLowerCase() === String(salesPositionFilter.value).toLowerCase()
}

const compareAttendanceMembers = (a, b) => {
  const managerCompare = attendanceManagerName(a).localeCompare(attendanceManagerName(b))
  if (managerCompare !== 0) return managerCompare
  const timeCompare = timeSortValue(a.check_in) - timeSortValue(b.check_in)
  if (timeCompare !== 0) return timeCompare
  return String(a.employee_name || '').localeCompare(String(b.employee_name || ''))
}

const groupMembersByManager = (members) => {
  const grouped = new Map()
  for (const member of members || []) {
    const managerName = attendanceManagerName(member)
    if (!grouped.has(managerName)) grouped.set(managerName, [])
    grouped.get(managerName).push(member)
  }
  return Array.from(grouped.entries())
    .map(([manager_name, list]) => ({
      manager_name,
      members: [...list].sort(compareAttendanceMembers),
    }))
    .sort((a, b) => String(a.manager_name || '').localeCompare(String(b.manager_name || '')))
}

const teamAttendanceGroups = computed(() =>
  groupedTeams.value
    .map((team) => ({
      ...team,
      members: [...(team.members || [])].filter(matchesSalesPositionFilter).sort(compareAttendanceMembers),
      manager_groups: groupMembersByManager((team.members || []).filter(matchesSalesPositionFilter)),
    }))
    .filter((team) => team.members.length > 0)
    .sort((a, b) => String(a.team_name || '').localeCompare(String(b.team_name || ''))),
)

const employeeDepartmentById = computed(() => {
  const map = new Map()
  for (const emp of employeesDirectory.value || []) {
    const id = String(emp?.id || '').trim()
    const department = String(emp?.department || '').trim()
    if (id && department && department !== '-') {
      map.set(id, department)
    }
  }
  return map
})

const attendanceDepartmentName = (member) => {
  const directoryDepartment = employeeDepartmentById.value.get(String(member?.employee_id || '').trim())
  const fromAgent = member?.agent_record?.department || member?.agent_record?.department_name
  const fromAttendance = member?.attendance_record?.department || member?.department
  return String(directoryDepartment || fromAgent || fromAttendance || 'Unassigned').trim() || 'Unassigned'
}

const departmentAttendanceGroups = computed(() => {
  const grouped = new Map()
  for (const member of mergedData.value || []) {
    if (!matchesSalesPositionFilter(member)) continue
    const key = attendanceDepartmentName(member)
    if (!grouped.has(key)) grouped.set(key, [])
    grouped.get(key).push(member)
  }
  return Array.from(grouped.entries())
    .map(([department_name, members]) => ({
      department_name,
      members: [...members].sort(compareAttendanceMembers),
      manager_groups: groupMembersByManager(members),
    }))
    .sort((a, b) => String(a.department_name || '').localeCompare(String(b.department_name || '')))
})

const selectedDepartmentData = computed(() =>
  departmentAttendanceGroups.value.find((d) => d.department_name === selectedDepartmentView.value) || null,
)

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

const attendanceEditDuration = computed(() => {
  if (!attendanceEditForm.value.checkIn || !attendanceEditForm.value.checkOut) return '--'
  const inParts = attendanceEditForm.value.checkIn.split(':').map(Number)
  const outParts = attendanceEditForm.value.checkOut.split(':').map(Number)
  if (inParts.length < 2 || outParts.length < 2) return '--'
  const start = (inParts[0] * 60) + inParts[1]
  const end = (outParts[0] * 60) + outParts[1]
  const diff = Math.max(0, end - start)
  const hours = Math.floor(diff / 60)
  const minutes = diff % 60
  return `${hours}h ${minutes}m`
})

watch(searchKeyword, () => {
  page.value = 1
})

watch(
  () => [
    attendanceSearchFilters.value.employee,
    attendanceSearchFilters.value.department,
    attendanceSearchFilters.value.attendanceDate,
    attendanceSearchFilters.value.type,
    attendanceSearchFilters.value.status,
    selectedAttendanceSearchChip.value,
  ],
  () => {
    page.value = 1
  },
)

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

watch(announcementsTotalPages, (tp) => {
  if (announcementsPage.value > tp) announcementsPage.value = tp
})

watch(careerSearchKeyword, () => {
  careerPage.value = 1
})

watch(careerTotalPages, (tp) => {
  if (careerPage.value > tp) careerPage.value = tp
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

function attendanceRowKey(row) {
  return `${row.employee_id}-${row.date}`
}

function toTimeInputValue(value) {
  if (!value) return ''
  if (typeof value === 'string') {
    const trimmed = value.trim()
    const hhmmMatch = trimmed.match(/^([01]?\d|2[0-3]):([0-5]\d)$/)
    if (hhmmMatch) return `${String(hhmmMatch[1]).padStart(2, '0')}:${hhmmMatch[2]}`
    const ampmMatch = trimmed.match(/^(\d{1,2}):([0-5]\d)\s*([AaPp][Mm])$/)
    if (ampmMatch) {
      let hour = Number(ampmMatch[1])
      const minute = ampmMatch[2]
      const suffix = ampmMatch[3].toUpperCase()
      if (suffix === 'PM' && hour < 12) hour += 12
      if (suffix === 'AM' && hour === 12) hour = 0
      return `${String(hour).padStart(2, '0')}:${minute}`
    }
  }
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${hh}:${mm}`
}

function resolveAttendanceDate(row) {
  if (row?.date) return row.date
  if (row?.attendance_date) return row.attendance_date
  if (row?.check_in) return String(row.check_in).slice(0, 10)
  if (row?.check_out) return String(row.check_out).slice(0, 10)
  return ''
}

const populateAttendanceEditForm = (row) => {
  const resolvedDate = resolveAttendanceDate(row)
  attendanceEditForm.value = {
    date: resolvedDate,
    checkIn: toTimeInputValue(row?.check_in),
    checkOut: toTimeInputValue(row?.check_out),
    status: row?.status || '',
    breakLabel: row?.break_label || row?.break_duration || '0',
    otLabel: row?.ot_label || row?.overtime || '0',
    description: row?.description || '',
  }
}

function withTime(dateIso, timeValue, fallbackDateTime) {
  if (!timeValue) return fallbackDateTime || null
  const base = dateIso || (fallbackDateTime ? String(fallbackDateTime).slice(0, 10) : '')
  if (!base) return fallbackDateTime || null
  return `${base}T${timeValue}:00`
}

function toggleAttendanceRowMenu(id, row, event) {
  if (openAttendanceRowMenuId.value === id) {
    openAttendanceRowMenuId.value = null
    return
  }
  const rect = event?.currentTarget?.getBoundingClientRect?.()
  if (rect) {
    const menuWidth = 260
    const menuHeight = 170
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight
    const spaceBelow = viewportHeight - rect.bottom
    const shouldOpenUp = spaceBelow < menuHeight + 12
    const top = shouldOpenUp
      ? Math.max(12, rect.top - menuHeight - 6)
      : Math.min(viewportHeight - menuHeight - 12, rect.bottom + 4)
    const left = Math.min(
      viewportWidth - menuWidth - 12,
      Math.max(12, rect.right - menuWidth + 4),
    )
    attendanceRowMenuStyle.value = {
      top: `${top}px`,
      left: `${left}px`,
    }
  }
  selectedAttendanceRow.value = row
  openAttendanceRowMenuId.value = id
}

function openAttendanceDetails(row) {
  selectedAttendanceRow.value = row
  attendanceDetailMode.value = 'view'
  showAttendanceDetailModal.value = true
  openAttendanceRowMenuId.value = null
}

const openAttendanceEdit = async (row) => {
  try {
    const result = await getAttendance(row.id)
    
    if (result) {
      selectedAttendanceRow.value = {
        ...row,
        ...result
      }
      populateAttendanceEditForm(result)
      attendanceDetailMode.value = 'edit'
      showAttendanceDetailModal.value = true
      openAttendanceRowMenuId.value = null
    }
  } catch (error) {
    console.error('Failed to fetch attendance details:', error)
    showNotification('Failed to load attendance details', 'error')
  }
}

function switchAttendanceDetailToEdit() {
  if (selectedAttendanceRow.value) {
    populateAttendanceEditForm(selectedAttendanceRow.value)
  }
  attendanceDetailMode.value = 'edit'
}

function closeAttendanceDetailModal() {
  showAttendanceDetailModal.value = false
  attendanceDetailMode.value = 'view'
}

const saveAttendanceEdit = async () => {
  if (!selectedAttendanceRow.value) return
  
  try {
    // التحقق من صحة البيانات
    if (!attendanceEditForm.value.date) {
      showNotification('Please select a date', 'error')
      return
    }
    
    if (!attendanceEditForm.value.checkIn) {
      showNotification('Please enter check-in time', 'error')
      return
    }
    
    if (!attendanceEditForm.value.checkOut) {
      showNotification('Please enter check-out time', 'error')
      return
    }
    
    const data = {
      date: attendanceEditForm.value.date,
      check_in: attendanceEditForm.value.checkIn,
      check_out: attendanceEditForm.value.checkOut,
      status: String(attendanceEditForm.value.status || 'present').toLowerCase(),
      break_duration: attendanceEditForm.value.breakLabel || '0',
      overtime: attendanceEditForm.value.otLabel || '0',
      description: attendanceEditForm.value.description || '',
    }
    
    const result = await updateAttendance(selectedAttendanceRow.value.id, data)
    
    if (result) {
      showNotification('Attendance record updated successfully', 'success')
      closeAttendanceDetailModal()
      await fetchAttendanceData()
      await loadAttendanceSummary()
    }
  } catch (error) {
    console.error('Failed to update attendance:', error)
    showNotification(error.response?.data?.message || 'Failed to update attendance', 'error')
  }
}


const onAttendanceDateChange = async () => {
  page.value = 1
  await fetchAttendanceData()
  await loadAttendanceSummary()
  await loadDailyAttendanceStats()
}
// ========== LOAD FILTER OPTIONS ==========
async function loadFilterOptions() {
  filterOptionsLoading.value = true
  try {
    const [depts, desigs, brs, mgrs] = await Promise.all([
      fetchDepartments(),
      fetchDesignations(),
      fetchBranches(),
      fetchManagers(),
    ])
    
    departmentsList.value = depts || []
    designationsList.value = desigs || []
    branchesList.value = brs || []
    managersList.value = mgrs || []
    
    console.log('✅ Filter options loaded:', {
      departments: departmentsList.value.length,
      designations: designationsList.value.length,
      branches: branchesList.value.length,
      managers: managersList.value.length,
    })
  } catch (error) {
    console.error('❌ Failed to load filter options:', error)
    departmentsList.value = []
    designationsList.value = []
    branchesList.value = []
    managersList.value = []
  } finally {
    filterOptionsLoading.value = false
  }
}
const deleteAttendanceRecord = async (row) => {
  const confirmed = await new Promise((resolve) => {
    if (window.Swal) {
      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete attendance record for ${row.employee_name} on ${formatDate(row.date)}. This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => resolve(result.isConfirmed))
    } else {
      resolve(window.confirm(`Delete attendance record for ${row.employee_name}?`))
    }
  })
  
  if (!confirmed) return
  
  try {
    await deleteAttendance(row.id)
    showNotification('Attendance record deleted successfully', 'success')
    openAttendanceRowMenuId.value = null
    await fetchAttendanceData()
    await loadAttendanceSummary()
  } catch (error) {
    console.error('Failed to delete attendance:', error)
    showNotification(error.response?.data?.message || 'Failed to delete attendance', 'error')
  }
}
const loadAttendanceSummary = async () => {
  try {
    const summary = await fetchAttendanceSummary(dateFilter.value)
    if (summary) {
      attendanceStats.value = {
        total_employees: summary.total_employees || 0,
        present: summary.present || 0,
        absent: summary.absent || 0,
        late: summary.late || 0,
        on_leave: summary.on_leave || 0,
        half_day: summary.half_day || 0,
        holiday: summary.holiday || 0,
      }
    }
  } catch (error) {
    console.error('Failed to load attendance summary:', error)
  }
}
const loadDailyAttendanceStats = async () => {
  try {
    const stats = await fetchDailyAttendanceStats(dateFilter.value)
    if (stats) {
      dailyStats.value = stats
    }
  } catch (error) {
    console.error('Failed to load daily attendance stats:', error)
  }
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

function isSalesAttendanceMember(member) {
  return matchesSalesPositionFilter(member)
}

function csvEscape(cell) {
  return `"${String(cell ?? '').replace(/"/g, '""')}"`
}

function exportSalesAttendanceByManager() {
  const baseTeams = selectedDepartmentView.value && selectedDepartmentData.value
    ? [{
        team_name: selectedDepartmentData.value.department_name,
        manager_groups: selectedDepartmentData.value.manager_groups || [],
      }]
    : teamAttendanceGroups.value

  const salesTeams = baseTeams
    .map((team) => ({
      ...team,
      manager_groups: (team.manager_groups || [])
        .map((group) => ({
          ...group,
          members: (group.members || []).filter(isSalesAttendanceMember),
        }))
        .filter((group) => group.members.length > 0),
    }))
    .filter((team) => (team.manager_groups || []).length > 0)

  if (!salesTeams.length) {
    if (window.$showNotification) window.$showNotification('No sales attendance data to export', 'warning')
    return
  }

  const headers = ['Row Type', 'Team', 'Manager', 'Employee Name', 'EMP ID', 'Status', 'Check In', 'Check Out', 'Duration', 'Break', 'OT']
  const rows = []

  for (const team of salesTeams) {
    rows.push(['TEAM_HEADER', team.team_name, '', '', '', '', '', '', '', '', ''])
    for (const managerGroup of team.manager_groups) {
      rows.push(['MANAGER_HIGHLIGHT', team.team_name, `*** ${managerGroup.manager_name} ***`, '', '', '', '', '', '', '', ''])
      for (const member of managerGroup.members) {
        rows.push([
          'EMPLOYEE',
          team.team_name,
          managerGroup.manager_name,
          member.employee_name || '',
          `EMP${formatEmpId(member.employee_id)}`,
          member.status || '',
          formatTime(member.check_in),
          formatTime(member.check_out),
          formatDuration(member.check_in, member.check_out),
          formatBreakDisplay(member),
          formatOtDisplay(member),
        ])
      }
    }
  }

  const csv = [headers, ...rows]
    .map((line) => line.map(csvEscape).join(','))
    .join('\n')

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `sales-attendance-by-manager-${new Date().toISOString().slice(0, 10)}.csv`
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

function onHeaderTabClick(tab, event) {
  const hasDropdown = !!headerTabMenus[tab]

  if (isMobileViewport.value) {
    activeTab.value = tab
    if (!hasDropdown) {
      if (tab === 'Leave / Attendance') leaveSectionMode.value = 'leave'
      openHeaderMenu.value = null
      return
    }
    event?.stopPropagation?.()
    openHeaderMenu.value = openHeaderMenu.value === tab ? null : tab
    return
  }

  if (!hasDropdown) {
    activeTab.value = tab
    if (tab === 'Leave / Attendance') leaveSectionMode.value = 'leave'
    openHeaderMenu.value = null
    return
  }

  openHeaderMenu.value = openHeaderMenu.value === tab ? null : tab
}

function onHeaderMenuSelect(tab, item) {
  // تعيين التبويب النشط بناءً على العنصر المحدد
  if (tab === 'Employees') {
    if (item === 'Document Requests') {
      activeTab.value = 'Document Requests'
      loadRequestedDocuments()
    } else {
      activeTab.value = 'Employees'
    }
  } else if (tab === 'Leave / Attendance') {
    if (item === 'Leave Management') {
      activeTab.value = 'Leave / Attendance'
      leaveSectionMode.value = 'leave'
    } else if (item === 'Attendance Management') {
      activeTab.value = 'Leave / Attendance'
      leaveSectionMode.value = 'attendance'
    } else if (item === 'Announcements') {
      activeTab.value = 'Leave / Attendance'
      leaveSectionMode.value = 'announcements'
    }
  } else if (tab === 'Career') {
    if (item === 'Manage Recruitments') {
      activeTab.value = 'Career'
      careerSectionMode.value = 'manage-recruitments'
    } else if (item === 'Interviews') {
      activeTab.value = 'Career'
      careerSectionMode.value = 'interviews'
    } else if (item === 'Career Lists') {
      activeTab.value = 'Career'
      careerSectionMode.value = 'career-lists'
    }
  } else if (tab === 'Payroll') {
    activeTab.value = 'Payroll'
    payrollSectionLabel.value = item
  } else if (tab === 'Assets') {
    if (item === 'Asset Directory') {
      activeTab.value = 'Assets'
    } else if (item === 'Asset Requests') {
      activeTab.value = 'Assets'
      // يمكن إضافة منطق لعرض طلبات الأصول هنا
    }
  }
  
  // إغلاق القائمة المنسدلة
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
  const label = String(item).toLowerCase()
  if (label.includes('asset')) return 'lucide:briefcase-business'
  if (label.includes('attendance')) return 'lucide:calendar-check'
  if (label.includes('leave')) return 'lucide:calendar-off'
  if (label.includes('announcement')) return 'lucide:megaphone'
  if (label.includes('salary') || label.includes('pay')) return 'lucide:wallet'
  if (label.includes('recruit') || label.includes('career') || label.includes('interview')) return 'lucide:briefcase'
  if (label.includes('employee')) return 'lucide:users'
  return 'lucide:layout-grid'
}

function onDocumentClick(event) {
  if (showAttendanceSearchModal.value) {
    if (event.target.closest?.('.lr-date-modal, .lead-search-date-backdrop, .flatpickr-calendar')) return
    if (event.target.closest?.('.hr-attendance-mob-sheet')) return
    const anchor = attendanceSearchAnchorRef.value
    if (anchor && !anchor.contains(event.target)) {
      showAttendanceSearchModal.value = false
    }
  }
  if (!topbarTabsRef.value && !hrTopbarRef.value) return
  const inTopbar = hrTopbarRef.value?.contains(event.target) || topbarTabsRef.value?.contains(event.target)
  const inMobSheet = event.target.closest?.('.hr-mob-nav-sheet__panel, .hr-attendance-mob-sheet__panel')
  if (!inTopbar && !inMobSheet) {
    openHeaderMenu.value = null
  }
  if (assetUserPickerRef.value && !assetUserPickerRef.value.contains(event.target)) {
    closeAssetUserPicker()
  }
  if (assetUserEditPickerRef.value && !assetUserEditPickerRef.value.contains(event.target)) {
    closeAssetUserEditPicker()
  }
  openEmployeeRowMenuId.value = null
  openAssetRowMenuId.value = null
  openLeaveRowMenuId.value = null
  openAttendanceRowMenuId.value = null
  openAnnouncementRowMenuId.value = null
  openCareerRowMenuId.value = null
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

function mapLeaveForModal(leave) {
  if (!leave) return leave
  return {
    id: leave.id,
    employeeName: leave.employeeName || leave.employee_name,
    designation: leave.designation || '—',
     startDate: formatDateDisplay(leave.startDate || leave.start_date),
    endDate: formatDateDisplay(leave.endDate || leave.end_date),
    days: leave.duration ?? leave.days,
    status: leave.statusLabel || leave.status,
    leaveType: leave.leaveType || leave.leave_type,
    appliedDate: leave.raw?.created_at || '—',
    reason: leave.reason || '—',
    ...leave,
  }
}

function openLeaveDetails(leave) {
  selectedLeaveRow.value = mapLeaveForModal(leave)
  showLeaveDetailModal.value = true
  openLeaveRowMenuId.value = null
}

function closeLeaveDetails() {
  showLeaveDetailModal.value = false
}

async function confirmDeleteLeave(leave) {
  const shouldDelete = await new Promise((resolve) => {
    if (window.Swal) {
      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete this leave request for "${leave.employeeName}". This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
      }).then((result) => resolve(result.isConfirmed))
    } else {
      resolve(window.confirm(`Delete leave request for "${leave.employeeName}"?`))
    }
  })

  if (!shouldDelete) return

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

function toggleAnnouncementRowMenu(id, event) {
  if (openAnnouncementRowMenuId.value === id) {
    openAnnouncementRowMenuId.value = null
    return
  }
  const rect = event?.currentTarget?.getBoundingClientRect?.()
  if (rect) {
    const menuWidth = 260
    const menuHeight = 126
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
    announcementRowMenuStyle.value = {
      top: `${top}px`,
      left: `${left}px`,
    }
  }
  openAnnouncementRowMenuId.value = id
}

function openAnnouncementModalForCreate() {
  editingAnnouncementId.value = null
  announcementForm.value = defaultAnnouncementForm()
  showAnnouncementModal.value = true
}

function openEditAnnouncement(item) {
  editingAnnouncementId.value = item.id
  announcementForm.value = {
    title: item.title || '',
    branch: item.branch_id || '',
    department: item.department_id || '',
    startDate: normalizeDateInput(item.raw?.start_date),
    endDate: item.raw?.end_date ? normalizeDateInput(item.raw.end_date) : '',
    description: item.raw?.description || '',
  }
  showAnnouncementModal.value = true
  openAnnouncementRowMenuId.value = null
}

function closeAnnouncementModal() {
  showAnnouncementModal.value = false
}

function clearAnnouncementForm() {
  announcementForm.value = defaultAnnouncementForm()
}

const saveAnnouncement = async () => {
  if (!announcementForm.value.title) {
    showNotification('Please enter an announcement title', 'error')
    return
  }
  if (!announcementForm.value.startDate) {
    showNotification('Please select a start date', 'error')
    return
  }

  const payload = {
    title: announcementForm.value.title,
    description: announcementForm.value.description || '',
    start_date: announcementForm.value.startDate,
    end_date: announcementForm.value.endDate || null,
    branch_id: announcementForm.value.branch || null,
    department_id: announcementForm.value.department || null,
  }

  try {
    if (editingAnnouncementId.value) {
      await updateAnnouncement(editingAnnouncementId.value, payload)
      showNotification('Announcement updated successfully', 'success')
    } else {
      await createAnnouncement(payload)
      showNotification('Announcement created successfully', 'success')
    }
    showAnnouncementModal.value = false
    editingAnnouncementId.value = null
    announcementForm.value = defaultAnnouncementForm()
    await loadAnnouncementsData()
  } catch (error) {
    console.error('Failed to save announcement:', error)

    if (error.response?.status === 422 && error.response?.data?.errors) {
      const errors = error.response.data.errors
      const messages = Object.values(errors)
        .flat()
        .map((msg) => `• ${msg}`)
        .join('\n')
      showNotification(messages, 'error')
    } else {
      const message = error.response?.data?.message || 'Failed to save announcement'
      showNotification(message, 'error')
    }
  }
}

async function deleteAnnouncement(item) {
  const confirmed = await new Promise((resolve) => {
    if (window.Swal) {
      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete "${item.title}". This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
      }).then((result) => resolve(result.isConfirmed))
    } else {
      resolve(window.confirm(`Delete announcement "${item.title}"?`))
    }
  })
  if (!confirmed) return

  try {
    await deleteAnnouncementApi(item.id)
    showNotification('Announcement deleted successfully', 'success')
    openAnnouncementRowMenuId.value = null
    await loadAnnouncementsData()
  } catch (error) {
    console.error('Failed to delete announcement:', error)
    showNotification(error.response?.data?.message || 'Failed to delete announcement', 'error')
  }
}

async function applyAnnouncementSearchFilters() {
  announcementsPage.value = 1
  showAnnouncementSearchModal.value = false
  await loadAnnouncementsData()
}

async function resetAnnouncementSearchFilters() {
  announcementSearchFilters.value = { title: '', branch: '', department: '' }
  announcementsPage.value = 1
  await loadAnnouncementsData()
}

watch(announcementsPage, () => {
  loadAnnouncementsData()
})

function exportAnnouncements() {
  const headers = ['Title', 'Start Date', 'End Date', 'Branch', 'Department', 'Description']
  const rows = filteredAnnouncementRows.value.map((row) => [
    row.title,
    row.startDate,
    row.endDate,
    row.branch,
    row.department,
    row.description,
  ])
  const csv = [headers, ...rows]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `announcements-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

function toggleCareerRowMenu(id, event) {
  if (openCareerRowMenuId.value === id) {
    openCareerRowMenuId.value = null
    return
  }
  const rect = event?.currentTarget?.getBoundingClientRect?.()
  if (rect) {
    const menuWidth = 230
    const menuHeight = 170
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
    careerRowMenuStyle.value = {
      top: `${top}px`,
      left: `${left}px`,
    }
  }
  openCareerRowMenuId.value = id
}

function openCareerApplicants(job) {
  selectedCareerJob.value = job
  careerSectionMode.value = 'view-applicants'
  openCareerRowMenuId.value = null
  loadCareerApplicants(job.id)
}

const setCareerApplicantDecision = async (nextDecision) => {
  if (!selectedCareerApplicant.value) return
  const statusMap = { Selected: 'shortlisted', Rejected: 'rejected', Maybe: 'pending' }
  const apiStatus = statusMap[nextDecision] || 'pending'

  let rejectionReason = null
  if (apiStatus === 'rejected') {
    rejectionReason = 'Rejected by HR'
  }

  try {
    await updateApplicantStatusApi(selectedCareerApplicant.value.id, apiStatus, rejectionReason)
    careerApplicantsRows.value = careerApplicantsRows.value.map((row) =>
      row.id === selectedCareerApplicant.value.id ? { ...row, decision: nextDecision, status: apiStatus } : row,
    )
    showNotification('Applicant status updated', 'success')
  } catch (error) {
    console.error('Failed to update applicant status:', error)
    showNotification(error.response?.data?.message || 'Failed to update status', 'error')
  }
}

function toggleCareerApplicantSection(sectionKey) {
  careerApplicantSectionsOpen.value[sectionKey] = !careerApplicantSectionsOpen.value[sectionKey]
}

function exportCareerJobs() {
  const headers = ['Job Tittle', 'Department', 'Branch', 'Type', 'Openings', 'Posted Date', 'Closing Date', 'Hiring Manager', 'Applicants', 'Status']
  const rows = filteredCareerRows.value.map((row) => [
    row.title,
    row.department,
    row.branch,
    row.type,
    row.openings,
    row.postedDate,
    row.closingDate,
    row.hiringManager,
    row.applicants,
    row.status,
  ])
  const csv = [headers, ...rows]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `career-jobs-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

function openAttendanceSearchDropdown(focusInput = false) {
  if (!attendanceSearchFilters.value.attendanceDate && dateFilter.value) {
    attendanceSearchFilters.value.attendanceDate = dateFilter.value
  }
  showAttendanceSearchModal.value = true
  attendanceSearchInputFocused.value = true
  if (focusInput) {
    nextTick(() => {
      attendanceSearchInputRef.value?.focus?.()
    })
  }
}

function onAttendanceSearchFocus() {
  if (attendanceSearchBlurTimer) {
    clearTimeout(attendanceSearchBlurTimer)
    attendanceSearchBlurTimer = null
  }
  attendanceSearchInputFocused.value = true
  openAttendanceSearchDropdown()
}

function onAttendanceSearchBlur() {
  attendanceSearchBlurTimer = setTimeout(() => {
    attendanceSearchInputFocused.value = false
    attendanceSearchBlurTimer = null
  }, 200)
}

function onAttendanceQuickSearchInput() {
  page.value = 1
}

function onAttendanceSearchFiltersPatch(next) {
  attendanceSearchFilters.value = { ...defaultAttendanceSearchFilters(), ...next }
  page.value = 1
}

function selectAttendanceSearchChip(chip) {
  selectedAttendanceSearchChip.value = chip
  attendanceSearchFilters.value.status = chip || ''
  page.value = 1
}

function clearAttendanceSearch() {
  resetAttendanceSearchFilters()
  showAttendanceSearchModal.value = false
}

function resetAttendanceSearchFilters() {
  attendanceSearchFilters.value = defaultAttendanceSearchFilters()
  selectedAttendanceSearchChip.value = ''
  searchKeyword.value = ''
  page.value = 1
}

const applyAttendanceSearchFilters = async () => {
  const filterDate = String(attendanceSearchFilters.value.attendanceDate || '').trim()
  if (filterDate && filterDate !== dateFilter.value) {
    dateFilter.value = filterDate
  }
  page.value = 1
  showAttendanceSearchModal.value = false
  await fetchAttendanceData()
  await loadAttendanceSummary()
}
function resetCareerSearchFilters() {
  careerSearchFilters.value = {
    jobTitle: '',
    postedDate: '',
    closingDate: '',
    department: '',
    type: '',
    status: '',
  }
  selectedCareerFilterChip.value = 'Marketing'
}

function applyCareerSearchFilters() {
  // for now: just close modal; filtering step comes next
  showCareerSearchModal.value = false
  careerPage.value = 1
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

const openEditAsset = async (asset) => {
  try {
    editingAssetId.value = asset.id
    
    const result = await getAsset(asset.id)
    
    if (result) {
      assetEditForm.value = {
        assetId: result.asset_code || asset.assetId || '',
        assetType: result.asset_type_id || '',
        assetName: result.name || asset.assetName || '',
        serialNumber: result.serial_number || asset.serial || '',
        modelNumber: result.model_number || asset.brand || '',
        rdpNumber: result.rdp_number || '',
        remarks: result.remarks || '',
        description: result.description || '',
        assetUser: result.current_assignment?.user_id || null,
        handoverDate: result.current_assignment?.handover_date || '',
        returnDate: result.current_assignment?.return_date || '',
        branchLocation: result.branch_id || '',
        department: result.department_id || '',
        status: result.status || 'available',
        purchaseDate: result.purchase_date || '',
        supplierName: result.supplier_name || '',
        warrantyDate: result.warranty_date || '',
        condition: result.condition || 'new',
        unitPrice: result.unit_price || '',
        currency: 'UAE Dirham',
        qty: result.quantity || 1,
      }
      
      if (result.current_assignment?.user_id) {
        const user = assetResponsiblePersons.value.find(
          p => Number(p.id) === Number(result.current_assignment.user_id)
        )
        if (user) {
          assetEditForm.value.assetUser = Number(user.id)
        }
      }
    }
    
    showAssetEditModal.value = true
    openAssetRowMenuId.value = null
  } catch (error) {
    console.error('Failed to fetch asset details:', error)
    showNotification('Failed to load asset details', 'error')
  }
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

function closeAssetUserEditPicker() {
  showAssetUserEditPicker.value = false
  assetUserEditSearchQuery.value = ''
}

async function toggleAssetUserEditPicker() {
  if (!showAssetUserEditPicker.value) {
    await fetchAssetResponsiblePersons()
  }
  showAssetUserEditPicker.value = !showAssetUserEditPicker.value
}

function selectAssetEditResponsiblePerson(person) {
  assetEditForm.value.assetUser = Number(person.id)
  if (!assetEditForm.value.department && person.department_name) {
    assetEditForm.value.department = person.department_name
  }
  if (!assetEditForm.value.branchLocation) {
    assetEditForm.value.branchLocation =
      person.branch_name || person.branch?.name || person.office_name || person.office?.name || ''
  }
  closeAssetUserEditPicker()
}

function decrementAssetEditQty() {
  const current = Number(assetEditForm.value.qty) || 1
  assetEditForm.value.qty = Math.max(1, current - 1)
}

function incrementAssetEditQty() {
  const current = Number(assetEditForm.value.qty) || 1
  assetEditForm.value.qty = current + 1
}



const saveAssetCreate = async () => {
  try {
    if (!assetCreateForm.value.assetType) {
      showNotification('Please select an asset type', 'error')
      return
    }
    
    if (!assetCreateForm.value.assetName) {
      showNotification('Please enter an asset name', 'error')
      return
    }
    
    if (!assetCreateForm.value.status) {
      showNotification('Please select a status', 'error')
      return
    }
    
    const data = {
      name: assetCreateForm.value.assetName,
      asset_type_id: Number(assetCreateForm.value.assetType),
      serial_number: assetCreateForm.value.serialNumber || null,
      model_number: assetCreateForm.value.modelNumber || null,
      rdp_number: assetCreateForm.value.rdpNumber || null,
      description: assetCreateForm.value.description || null,
      remarks: assetCreateForm.value.remarks || null,
      purchase_date: assetCreateForm.value.purchaseDate || null,
      warranty_date: assetCreateForm.value.warrantyDate || null,
      unit_price: assetCreateForm.value.unitPrice || null,
      supplier_name: assetCreateForm.value.supplierName || null,
      quantity: Number(assetCreateForm.value.qty) || 1,
      condition: assetCreateForm.value.condition || 'new',
      branch_id: assetCreateForm.value.branchLocation || null,
      department_id: assetCreateForm.value.department || null,
    }
    
    console.log('📤 Creating asset:', data)
    
    const result = await createAsset(data)
    
    if (result) {
      showNotification('Asset created successfully!', 'success')
      closeAssetCreateModal()
      resetAssetCreateForm()
      await fetchAssetsData()
    }
  } catch (error) {
    console.error('❌ Failed to create asset:', error)
    
    let errorMessage = 'Failed to create asset'
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors
      const messages = Object.values(errors).flat()
      errorMessage = messages.join('\n')
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message
    } else if (error.message) {
      errorMessage = error.message
    }
    
    showNotification(errorMessage, 'error')
  }
}

function closeApplyLeaveModal() {
  showApplyLeaveModal.value = false
}

function resetApplyLeaveForm() {
  applyLeaveForm.value = {
    employee: '',
    leaveType: '',
    startDate: '',
    endDate: '',
    reason: '',
    isHalfDay: false,
    halfDayType: null,
  }
  applyLeaveAttachment.value = null
}

function cancelApplyLeave() {
  closeApplyLeaveModal()
  resetApplyLeaveForm()
}

function handleApplyLeaveFileChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  applyLeaveAttachment.value = file
}

function removeApplyLeaveFile() {
  applyLeaveAttachment.value = null
}

const submitApplyLeave = async () => {
  try {
    if (!applyLeaveForm.value.employee) {
      showNotification('Please select an employee', 'error')
      return
    }
    
    if (!applyLeaveForm.value.leaveType) {
      showNotification('Please select a leave type', 'error')
      return
    }
    
    if (!applyLeaveForm.value.startDate) {
      showNotification('Please select a start date', 'error')
      return
    }
    
    if (!applyLeaveForm.value.endDate) {
      showNotification('Please select an end date', 'error')
      return
    }
    
    const selectedEmployeeText = String(applyLeaveForm.value.employee || '')
    let linkedEmployee = null
    
    linkedEmployee = employeesDirectory.value.find(
      (emp) => `#${emp.employee_code} ${emp.name}` === selectedEmployeeText
    )
    
    if (!linkedEmployee) {
      const employeeName = selectedEmployeeText.replace(/^#\w+\s+/, '').trim()
      linkedEmployee = employeesDirectory.value.find(
        (emp) => emp.name === employeeName
      )
    }
    
    if (!linkedEmployee) {
      showNotification('Employee not found. Please select a valid employee.', 'error')
      return
    }
    
    const data = {
       user_id: linkedEmployee.id,
      leave_type_id: Number(applyLeaveForm.value.leaveType), 
      start_date: applyLeaveForm.value.startDate,
      end_date: applyLeaveForm.value.endDate,
      reason: applyLeaveForm.value.reason || '',
      is_half_day: false, 
      half_day_type: null, 
    }
    
    console.log('📤 Sending leave request data:', data)
    
    if (applyLeaveAttachment.value) {
      data.attachment = applyLeaveAttachment.value
    }
    
    const result = await createLeaveRequest(data)
    
    console.log('✅ Leave request result:', result)
    
    if (result) {
      showNotification('Leave request submitted successfully!', 'success')
      closeApplyLeaveModal()
      resetApplyLeaveForm()
      await fetchLeaveRequestsData()
    }
  } catch (error) {
    console.error('❌ Failed to submit leave request:', error)
    
    let errorMessage = 'Failed to submit leave request'
    
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors
      const messages = Object.values(errors).flat()
      errorMessage = messages.join('\n')
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message
    } else if (error.message) {
      errorMessage = error.message
    }
    
    showNotification(errorMessage, 'error')
  }
}
function openLeaveAttendancePrimaryAction() {
  if (leaveSectionMode.value === 'attendance') {
    createAttendanceForm.value.date = dateFilter.value || createAttendanceForm.value.date
    showCreateAttendanceModal.value = true
    return
  }
  if (leaveSectionMode.value === 'announcements') {
    openAnnouncementModalForCreate()
    return
  }
  showApplyLeaveModal.value = true
}

function openAssetsPrimaryAction() {
  assetsMgmtRef.value?.openCreate?.()
}

function closeCreateAttendanceModal() {
  showCreateAttendanceModal.value = false
}

function resetCreateAttendanceForm() {
  createAttendanceForm.value = defaultCreateAttendanceForm()
  createAttendanceAttachment.value = null
  createAttendanceForm.value.date = dateFilter.value || ''
}

function cancelCreateAttendance() {
  closeCreateAttendanceModal()
  resetCreateAttendanceForm()
}

function handleCreateAttendanceFileChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  createAttendanceAttachment.value = file
}

function removeCreateAttendanceFile() {
  createAttendanceAttachment.value = null
}

const refreshAttendanceSummaryFromRows = () => {
  const rows = Array.isArray(employees.value) ? employees.value : []
  attendanceSummaryData.value = {
    total_employees: rows.length,
    present_today: rows.filter((e) => String(e.status || '').toLowerCase() === 'present').length,
    absent_today: rows.filter((e) => String(e.status || '').toLowerCase() === 'absent').length,
    late_today: rows.filter((e) => String(e.status || '').toLowerCase() === 'late').length,
    half_day: rows.filter((e) => String(e.status || '').toLowerCase() === 'half_day').length,
  }
}
const fetchAttendanceData = async () => {
  try {
    const params = {
      per_page: perPage,
      page: page.value,
      date: dateFilter.value || undefined,
    }
    
    // إضافة الفلاتر
    if (attendanceSearchFilters.value.employee) {
      // البحث عن الموظف
      const employeeMatch = attendanceSearchFilters.value.employee.match(/#([^\s]+)\s+(.+)/)
      if (employeeMatch) {
        params.search = employeeMatch[2]
      }
    }
    
    if (attendanceSearchFilters.value.department) {
      params.department = attendanceSearchFilters.value.department
    }
    
    if (attendanceSearchFilters.value.status) {
      params.status = attendanceSearchFilters.value.status.toLowerCase()
    }
    
    if (selectedAttendanceSearchChip.value) {
      params.status = selectedAttendanceSearchChip.value.toLowerCase()
    }
    
    const result = await fetchAttendance(params)
    const rows = Array.isArray(result?.employees) ? result.employees : []

    employees.value = rows.map((row) => ({
      employee_id: row.employee_id ?? row.user_id,
      employee_name: row.employee_name ?? row.user?.name ?? 'Unknown',
      avatar: row.user?.avatar || row.avatar || 'https://i.pravatar.cc/40?img=1',
      status: row.status || 'present',
      date: row.date,
      check_in: row.check_in,
      check_out: row.check_out,
      break_label: row.break_duration || row.break_label,
      ot_label: row.overtime || row.ot_label,
      attendance_type: row.attendance_type || 'office',
      department: row.department ?? row.user?.employee_profile?.department?.name ?? '—',
      description: row.description || '—',
      raw: row,
      id: row.id,
      break_minutes: row.break_minutes || null,
      overtime_minutes: row.overtime_minutes || null,
    }))

    if (result?.meta) {
      totalPages.value = result.meta.last_page || 1
    }
    
    refreshAttendanceSummaryFromRows()
  } catch (error) {
    console.error('Failed to fetch attendance:', error)
    showNotification('Failed to load attendance data', 'error')
  }
}
const submitCreateAttendance = async () => {
  try {
    if (!createAttendanceForm.value.employee) {
      showNotification('Please select an employee', 'error')
      return
    }
    
    if (!createAttendanceForm.value.date) {
      showNotification('Please select a date', 'error')
      return
    }
    
    if (!createAttendanceForm.value.checkIn) {
      showNotification('Please enter check-in time', 'error')
      return
    }
    
    if (!createAttendanceForm.value.checkOut) {
      showNotification('Please enter check-out time', 'error')
      return
    }
    
    const selectedEmployeeText = String(createAttendanceForm.value.employee || '')
    const linkedEmployee = employeesDirectory.value.find(
      (emp) => `#${emp.employee_code} ${emp.name}` === selectedEmployeeText
    )
    
    if (!linkedEmployee) {
      showNotification('Employee not found', 'error')
      return
    }
    
    const data = {
      user_id: linkedEmployee.id,
      date: createAttendanceForm.value.date,
      check_in: createAttendanceForm.value.checkIn,
      check_out: createAttendanceForm.value.checkOut,
      status: String(createAttendanceForm.value.status || 'present').toLowerCase(),
      attendance_type: createAttendanceForm.value.type || 'office',
      break_duration: createAttendanceForm.value.breakLabel || '0',
      overtime: createAttendanceForm.value.otLabel || '0',
      description: createAttendanceForm.value.description || '',
    }
    
    if (createAttendanceAttachment.value) {
      data.attachment = createAttendanceAttachment.value
    }
    
    const result = await createAttendance(data)
    
    if (result) {
      showNotification('Attendance record created successfully', 'success')
      closeCreateAttendanceModal()
      resetCreateAttendanceForm()
      await fetchAttendanceData()
      await loadAttendanceSummary()
    }
  } catch (error) {
    console.error('Failed to create attendance:', error)
    const message = error.response?.data?.message || 'Failed to create attendance record'
    showNotification(message, 'error')
  }
}

function closeAssetEditModal() {
  showAssetEditModal.value = false
  editingAssetId.value = null
  closeAssetUserEditPicker()
}

const saveAssetEdit = async () => {
  try {
    if (!editingAssetId.value) {
      showNotification('No asset selected for editing', 'error')
      return
    }
    
    if (!assetEditForm.value.assetType) {
      showNotification('Please select an asset type', 'error')
      return
    }
    
    if (!assetEditForm.value.assetName) {
      showNotification('Please enter an asset name', 'error')
      return
    }
    
    if (!assetEditForm.value.status) {
      showNotification('Please select a status', 'error')
      return
    }
    
    const data = {
      name: assetEditForm.value.assetName,
      asset_type_id: Number(assetEditForm.value.assetType),
      serial_number: assetEditForm.value.serialNumber || null,
      model_number: assetEditForm.value.modelNumber || null,
      description: assetEditForm.value.description || null,
      remarks: assetEditForm.value.remarks || null,
      unit_price: assetEditForm.value.unitPrice || null,
      quantity: Number(assetEditForm.value.qty) || 1,
      condition: assetEditForm.value.condition || 'new',
      status: assetEditForm.value.status?.toLowerCase() || 'available',
      branch_id: assetEditForm.value.branchLocation || null,
      department_id: assetEditForm.value.department || null,
      purchase_date: assetEditForm.value.purchaseDate || null,
      warranty_date: assetEditForm.value.warrantyDate || null,
      supplier_name: assetEditForm.value.supplierName || null,
    }
    
    console.log('📤 Updating asset:', data)
    
    const result = await updateAsset(editingAssetId.value, data)
    
    if (result) {
       if (assetEditForm.value.assetUser) {
        await assignAsset(editingAssetId.value, {
          user_id: Number(assetEditForm.value.assetUser),
          handover_date: assetEditForm.value.handoverDate || new Date().toISOString().slice(0, 10),
          notes: 'Assigned via edit form',
        })
      }
      showNotification('Asset updated successfully!', 'success')
      closeAssetEditModal()
      await fetchAssetsData()
    }
  } catch (error) {
    console.error('❌ Failed to update asset:', error)
    
    let errorMessage = 'Failed to update asset'
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors
      const messages = Object.values(errors).flat()
      errorMessage = messages.join('\n')
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message
    } else if (error.message) {
      errorMessage = error.message
    }
    
    showNotification(errorMessage, 'error')
  }
}

const confirmDeleteAsset = async (asset) => {
  const shouldDelete = await new Promise((resolve) => {
    if (window.Swal) {
      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete asset "${asset.assetName}". This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => resolve(result.isConfirmed))
    } else {
      resolve(window.confirm(`Are you sure you want to delete asset "${asset.assetName}"?`))
    }
  })
  
  if (!shouldDelete) return
  
  try {
    await deleteAsset(asset.id)
    showNotification('Asset deleted successfully', 'success')
    openAssetRowMenuId.value = null
    await fetchAssetsData()
  } catch (error) {
    console.error('Failed to delete asset:', error)
    showNotification(error.response?.data?.message || 'Failed to delete asset', 'error')
  }
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

function triggerProfileImageUpload() {
  profileImageInputRef.value?.click()
}

function handleProfileImageChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  addEmployeeProfileFile.value = file
  addEmployeeProfilePreview.value = URL.createObjectURL(file)
}

function resetAddEmployeeForm() {
  addEmployeeForm.value = defaultAddEmployeeForm()
  addEmployeeUploadedFiles.value = {}
  addEmployeeProfileFile.value = null
  addEmployeeProfilePreview.value = ''
  selectedDocumentType.value = 'emirates_id'
    removeEditQueryParam()

}

function appendEmployeeDocumentFields(formData) {
  formData.append('emirates_id_number', addEmployeeForm.value.emirates_id_number || '')
  formData.append('documents_expiry_date', addEmployeeForm.value.documents_expiry_date || '')
  formData.append('labor_card_number', addEmployeeForm.value.labor_card_number || '')
  formData.append('labor_card_expiry_date', addEmployeeForm.value.labor_card_expiry_date || '')
  formData.append('passport_number', addEmployeeForm.value.passport_number || '')
  formData.append('passport_expiry_date', addEmployeeForm.value.passport_expiry_date || '')
  formData.append('visa_number', addEmployeeForm.value.visa_number || '')
  formData.append('visa_expiry_date', addEmployeeForm.value.visa_expiry_date || '')
  formData.append('iloe_expiry_date', addEmployeeForm.value.iloe_expiry_date || '')
  formData.append('certificate_name', addEmployeeForm.value.certificate_name || '')
  formData.append('attestation_status', addEmployeeForm.value.attestation_status || '')
}

function appendEmployeeDocumentFiles(formData) {
  Object.entries(addEmployeeUploadedFiles.value).forEach(([docType, file]) => {
    if (!file) return

    formData.append(`documents[${docType}][]`, file)
    formData.append(`documents[${docType}_names][]`, file.name)
  })
}

// In your setup script
function mapRowToEmployeeForm(row) {
  // Get the raw API data (either from row.raw or use row itself)
  const apiData = row.raw || row;
  const profile = apiData.employee_profile || {};
  const bank = profile.bank_details || {};
  const insurance = profile.insurance_details || {};
  const addresses = profile.addresses || {};
  const emergency = profile.emergency_contact || {};
  const salary = apiData.salary || {};
  
  console.log('🔍 Mapping API Data:', {
    apiData,
    profile,
    bank,
    insurance,
    addresses,
    emergency,
    salary
  });

  return {
    // ===== Basic Info =====
    full_name: apiData.name || row.name || '',
    nationality: apiData.nationality || row.nationality || '',
    phone: apiData.phone || row.phone || '',
    home_country_phone_number: apiData.home_country_phone_number || row.home_country_phone_number || '',
    email: apiData.email || row.email || '',
    salary: salary.amount || row.salaryAmount || row.salary || '',
    salary_type: salary.type || row.salaryType || row.salary_type || '',
    
    // ===== Addresses =====
    address_inside_uae: addresses.inside_uae || row.address_inside_uae || '',
    address_outside_uae: addresses.outside_uae || row.address_outside_uae || '',
    
    // ===== Personal Info =====
    father_name: profile.father_name || row.father_name || '',
    mother_name: profile.mother_name || row.mother_name || '',
    marital_status: row.marital_status || '',
    religion: profile.religion || row.religion || '',
    emergency_contact_name: emergency.name || row.emergency_contact_name || '',
    emergency_email: emergency.email || row.emergency_email || '',
    emergency_phone_number: emergency.phone || row.emergency_phone_number || '',
    
    // ===== Company Details (IDs) =====
    branch_id: profile.company_branch_id || row.branchId || '',
    designation_id: profile.designation?.id || row.designationId || '',
    department_id: profile.department?.id || row.departmentId || '',
    supervisor_id: apiData.parent?.id || row.managerId || '',
    user_type: row.user_type || '',
    
    // ===== Company Dates =====
    joining_date: formatDateForInput(profile.joining_date || row.joiningDate),
    visa_validity: formatDateForInput(profile.visa_validity || row.visa_validity),
    probation_end_date: formatDateForInput(profile.probation_end_date || row.probation_end_date),
    contract_joining_date: formatDateForInput(profile.contract_joining_date || row.contract_joining_date),
    gratuity_termination: formatDateForInput(profile.gratuity_termination || row.gratuity_termination),
    
    // ===== Company Info =====
    sponsor: profile.sponsor || row.sponsor || '',
    visa_quota: profile.visa_quota || row.visa_quota || '',
    vehicle: profile.vehicle || row.vehicle || '',
    
    // ===== Documents =====
    emirates_id_number: profile.emirates_id_number || row.emirates_id_number || '',
    documents_expiry_date: formatDateForInput(profile.documents_expiry_date || row.documents_expiry_date),
    labor_card_number: profile.labor_card_number || row.labor_card_number || '',
    labor_card_expiry_date: formatDateForInput(profile.labor_card_expiry_date || row.labor_card_expiry_date),
    passport_number: profile.passport_number || row.passport_number || '',
    passport_expiry_date: formatDateForInput(profile.passport_expiry_date || row.passport_expiry_date),
    visa_number: profile.visa_number || row.visa_number || '',
    visa_expiry_date: formatDateForInput(profile.visa_expiry_date || row.visa_expiry_date),
    iloe_expiry_date: formatDateForInput(profile.iloe_expiry_date || row.iloe_expiry_date),
    certificate_name: profile.certificate_name || row.certificate_name || '',
    attestation_status: row.attestation_status || '',
    
    // ===== Bank Details =====
    account_holder_name: bank.bank_account_holder_name || row.account_holder_name || '',
    bank_name: bank.bank_name || row.bank_name || '',
    branch_location: bank.branch_location || row.branch_location || '',
    account_number: bank.account_number || row.account_number || '',
    iban_number: bank.iban_number || row.iban_number || '',
    swift_code: bank.swift_code || row.swift_code || '',
    
    // ===== Insurance =====
    policy_type: insurance.policy_type || row.policy_type || '',
    insurance_provider: insurance.provider || row.insurance_provider || '',
    policy_number: insurance.policy_number || row.policy_number || '',
    insurance_start_date: formatDateForInput(insurance.start_date || row.insurance_start_date),
    insurance_expiry_date: formatDateForInput(insurance.expiry_date || row.insurance_expiry_date),
    
    // ===== Extra Fields =====
    photo_description: '',
    broker_license_number: '',
    broker_license_expiry_date: '',
    iloe_number: '',
    stamp_description: '',
    contract_number: '',
    offer_letter_description: '',
    signature_description: '',
  };
}

// Helper function to format dates for input fields
function formatDateForInput(value) {
  if (!value) return '';
  
  // Check if it's already in YYYY-MM-DD format
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return value;
  }
  
  // Handle date strings like "2026-07-04T20:00:00.000000Z"
  try {
    const date = new Date(value);
    if (!isNaN(date.getTime())) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }
  } catch (e) {
    console.warn('Failed to parse date:', value);
  }
  
  return value;
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
    email_personal: row.email_personal || row.email || 'mariajoun@gmail.com',
    phone_company: row.phone_company || row.phone || '+971 56125 4568',
    phone_personal: row.phone_personal || row.phone || '+91 8136548745',
    dob: row.dob || '14 Jan 1997',
    address: row.address || 'Al Wahda, Near Bus Station, Abu Dhabi, United Arab Emirates',
    address_inside_uae: row.address_inside_uae || row.address || 'Al Wahda, Near Bus Station, Abu Dhabi, United Arab Emirates',
    address_outside_uae: row.address_outside_uae || row.address || 'Al Wahda, Near Bus Station, Abu Dhabi, United Arab Emirates',
    salary_type: row.salary_type || 'monthly',
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

async function openEditEmployee(row) {
  try {
    isEditEmployeeMode.value = true
    editingEmployeeId.value = row.id
    
    const employeeData = await fetchEmployee(row.id)
    console.log('🔍 Employee Data from API:', employeeData)
    
    if (employeeData) {
      const mappedData = mapRowToEmployeeForm(employeeData)
      console.log('📝 Mapped Form Data:', mappedData)
      
      addEmployeeForm.value = mappedData
      addEmployeeUploadedFiles.value = {}
      addEmployeeProfileFile.value = null
      addEmployeeProfilePreview.value = employeeData.avatar || ''
      selectedDocumentType.value = 'emirates_id'
    }
    
    showAddEmployeeModal.value = true
    openEmployeeRowMenuId.value = null
  } catch (error) {
    console.error('Error fetching employee data:', error)
    showNotification('Failed to load employee data', 'error')
  }
}
// ========== DELETE EMPLOYEE ==========
const deleteEmployee = async (employee) => {
  // Show confirmation dialog
  const confirmed = await new Promise((resolve) => {
    if (window.Swal) {
      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete employee "${employee.name}". This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        resolve(result.isConfirmed)
      })
    } else {
      resolve(window.confirm(`Are you sure you want to delete "${employee.name}"?`))
    }
  })

  if (!confirmed) return

  try {
    // Call API to delete employee
    await api.delete(`/employees/${employee.id}`)
    
    // Remove from local array
    employeesDirectory.value = employeesDirectory.value.filter(
      (emp) => String(emp.id) !== String(employee.id)
    )
    
    // If this employee was selected as detail, clear it
    if (selectedEmployeeDetail.value && String(selectedEmployeeDetail.value.id) === String(employee.id)) {
      selectedEmployeeDetail.value = null
      if (activeTab.value === 'Employee Details') {
        activeTab.value = 'Employees'
      }
    }
    
    // Show success notification
    showNotification(`Employee "${employee.name}" deleted successfully`, 'success')
    
    // Close any open menus
    openEmployeeRowMenuId.value = null
    employeesRefreshKey.value++
    
  } catch (error) {
    console.error('Error deleting employee:', error)
    showNotification(error.response?.data?.message || 'Failed to delete employee', 'error')
  }
}


function openEmployeeDetails(row) {
  selectedEmployeeDetail.value = enrichEmployeeDetail(row)
  employeeDetailTab.value = 'company'
  employeeDetailView.value = 'details'
  activeTab.value = 'Employee Details'
  openEmployeeRowMenuId.value = null
}

function closeRequestDocumentModal() {
  showRequestDocumentModal.value = false
  requestDocumentForm.value = { documentType: '', description: '', employee: '' }
  editingRequestedDocumentId.value = null
}

function openRequestDocumentModal() {
  editingRequestedDocumentId.value = null
  requestDocumentForm.value = { documentType: '', description: '', employee: '' }
  showRequestDocumentModal.value = true
}

const submitRequestDocument = async () => {
  if (!requestDocumentForm.value.documentType) {
    showNotification('Please select a document type', 'error')
    return
  }
  if (
    activeTab.value === 'Document Requests' &&
    !editingRequestedDocumentId.value &&
    !requestDocumentForm.value.employee
  ) {
    showNotification('Please select an employee', 'error')
    return
  }

  try {
    const payload = {
      document_type_id: requestDocumentForm.value.documentType,
      description: requestDocumentForm.value.description || '',
    }
    if (requestDocumentForm.value.employee) {
      payload.user_id = requestDocumentForm.value.employee
    }

    if (editingRequestedDocumentId.value) {
      await updateDocumentRequest(editingRequestedDocumentId.value, payload)
      showNotification('Document request updated successfully', 'success')
    } else {
      await createDocumentRequest(payload)
      showNotification('Document request submitted successfully', 'success')
    }

    closeRequestDocumentModal()
    await loadRequestedDocuments(selectedEmployeeDetail.value?.id)
  } catch (error) {
    console.error('Failed to submit document request:', error)
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const messages = Object.values(error.response.data.errors).flat().map((m) => `• ${m}`).join('\n')
      showNotification(messages, 'error')
    } else {
      showNotification(error.response?.data?.message || 'Failed to submit request', 'error')
    }
  }
}

function openEditDocumentRequest(doc) {
  editingRequestedDocumentId.value = doc.id

  requestDocumentForm.value = {
    documentType: doc.document_type_id ?? doc.raw?.document_type_id ?? '',
    description: doc.description === '--' ? '' : doc.description,
    // employee: doc.user_id ?? doc.raw?.user_id ?? '',
  }

  showRequestDocumentModal.value = true
}

async function deleteRequestedDocument(doc) {
  const confirmed = await new Promise((resolve) => {
    if (window.Swal) {
      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete this document request. This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
      }).then((result) => resolve(result.isConfirmed))
    } else {
      resolve(window.confirm('Delete this document request?'))
    }
  })
  if (!confirmed) return

  try {
    await deleteDocumentRequest(doc.id)
    showNotification('Document request deleted successfully', 'success')
    if (selectedRequestedDocument.value?.id === doc.id) {
      selectedRequestedDocument.value = null
      showDocumentDetailModal.value = false
    }
    await loadRequestedDocuments(selectedEmployeeDetail.value?.id)
  } catch (error) {
    console.error('Failed to delete document request:', error)
    showNotification(error.response?.data?.message || 'Failed to delete request', 'error')
  }
}

async function openDocumentDetail(doc) {
  try {
    const full = await getDocumentRequest ? null : null 
  } catch (e) {}
  selectedRequestedDocument.value = { ...doc }
  showDocumentDetailModal.value = true
}

function closeDocumentDetailModal() {
  showDocumentDetailModal.value = false
  selectedRequestedDocument.value = null
}

function openApproveDocumentModal() {
  showApproveDocumentModal.value = true
}

function closeApproveDocumentModal() {
  showApproveDocumentModal.value = false
  approveDocumentFile.value = null
}

function openRejectDocumentModal() {
  showRejectDocumentModal.value = true
}

function closeRejectDocumentModal() {
  showRejectDocumentModal.value = false
  rejectDocumentReason.value = ''
}

function handleApproveDocumentFileChange(event) {
  const [file] = event.target.files || []
  approveDocumentFile.value = file || null
}

const confirmApproveDocument = async () => {
  if (!selectedRequestedDocument.value) return
  if (!approveDocumentFile.value) {
    showNotification('Please attach a file', 'error')
    return
  }

  try {
    await approveDocumentRequest(selectedRequestedDocument.value.id, approveDocumentFile.value)
    showNotification('Document request approved successfully', 'success')
    closeApproveDocumentModal()
    showDocumentDetailModal.value = false
    await loadRequestedDocuments(selectedEmployeeDetail.value?.id)
  } catch (error) {
    console.error('Failed to approve document request:', error)
    showNotification(error.response?.data?.message || 'Failed to approve request', 'error')
  }
}

const confirmRejectDocument = async () => {
  if (!selectedRequestedDocument.value) return
  if (!rejectDocumentReason.value || rejectDocumentReason.value.trim().length < 5) {
    showNotification('Rejection reason must be at least 5 characters', 'error')
    return
  }

  try {
    await rejectDocumentRequest(selectedRequestedDocument.value.id, rejectDocumentReason.value)
    showNotification('Document request rejected successfully', 'success')
    closeRejectDocumentModal()
    showDocumentDetailModal.value = false
    await loadRequestedDocuments(selectedEmployeeDetail.value?.id)
  } catch (error) {
    console.error('Failed to reject document request:', error)
    showNotification(error.response?.data?.message || 'Failed to reject request', 'error')
  }
}

function showNotification(message, type = 'success') {
  if (window.$showNotification) {
    window.$showNotification(message, type)
  } else {
    Swal.fire({
      icon: type,
      title: message,
      timer: 2000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end',
    })
  }
}
async function saveEmployeeForm() {
  try {
    const formData = new FormData()
    
    formData.append('name', addEmployeeForm.value.full_name)
    formData.append('email', addEmployeeForm.value.email)
    formData.append('phone', addEmployeeForm.value.phone)
    formData.append('personal_phone', addEmployeeForm.value.phone)
    formData.append('home_country_phone_number', addEmployeeForm.value.home_country_phone_number || '')
    formData.append('nationality', addEmployeeForm.value.nationality || '')
    formData.append('salary_type', addEmployeeForm.value.salary_type || '')
    formData.append('salary_amount', addEmployeeForm.value.salary || 0)
    formData.append('password', 'password123') // مؤقت
    
    formData.append('father_name', addEmployeeForm.value.father_name || '')
    formData.append('mother_name', addEmployeeForm.value.mother_name || '')
    formData.append('religion', addEmployeeForm.value.religion || '')
    formData.append('emergency_contact_name', addEmployeeForm.value.emergency_contact_name || '')
    formData.append('emergency_email', addEmployeeForm.value.emergency_email || '')
    formData.append('emergency_phone', addEmployeeForm.value.emergency_phone_number || '')
    formData.append('address_inside_uae', addEmployeeForm.value.address_inside_uae || '')
    formData.append('address_outside_uae', addEmployeeForm.value.address_outside_uae || '')
    formData.append('employee_name', addEmployeeForm.value.full_name)
    
    formData.append('designation_id', addEmployeeForm.value.designation_id || '')
    formData.append('department_id', addEmployeeForm.value.department_id || '')
    formData.append('company_branch_id', addEmployeeForm.value.branch_id  || '')
    formData.append('parent_id', addEmployeeForm.value.supervisor_id || '')
    formData.append('joining_date', addEmployeeForm.value.joining_date || '')
    formData.append('contract_end_date', addEmployeeForm.value.visa_validity || '')
    formData.append('probation_end_date', addEmployeeForm.value.probation_end_date || '')
    formData.append('contract_joining_date', addEmployeeForm.value.contract_joining_date || '')
    formData.append('gratuity_termination', addEmployeeForm.value.gratuity_termination || '')
    formData.append('visa_validity', addEmployeeForm.value.visa_validity || '')
    formData.append('sponsor', addEmployeeForm.value.sponsor || '')
    formData.append('visa_quota', addEmployeeForm.value.visa_quota || '')
    formData.append('vehicle', addEmployeeForm.value.vehicle || '')
    
    appendEmployeeDocumentFields(formData)
    
    formData.append('bank_account_holder_name', addEmployeeForm.value.account_holder_name || '')
    formData.append('bank_name', addEmployeeForm.value.bank_name || '')
    formData.append('bank_account_number', addEmployeeForm.value.account_number || '')
    formData.append('branch_location', addEmployeeForm.value.branch_location || '')
    formData.append('swift_code', addEmployeeForm.value.swift_code || '')
    formData.append('iban_number', addEmployeeForm.value.iban_number || '')
    
    formData.append('insurance_policy_type', addEmployeeForm.value.policy_type || '')
    formData.append('insurance_policy_number', addEmployeeForm.value.policy_number || '')
    formData.append('insurance_provider', addEmployeeForm.value.insurance_provider || '')
    formData.append('insurance_start_date', addEmployeeForm.value.insurance_start_date || '')
    formData.append('insurance_expiry_date', addEmployeeForm.value.insurance_expiry_date || '')
    
    if (addEmployeeProfileFile.value) {
      formData.append('avatar', addEmployeeProfileFile.value)
    }

    appendEmployeeDocumentFiles(formData)
    
    let result
    if (isEditEmployeeMode.value && editingEmployeeId.value) {
      result = await updateEmployee(editingEmployeeId.value, formData)
      showNotification('Employee updated successfully', 'success')
    } else {
      result = await createEmployee(formData)
      showNotification('Employee created successfully', 'success')
    }
    
    closeAddEmployeeModal()
    
    await fetchRealEmployees()
    employeesRefreshKey.value++
  } catch (error) {
  console.error('Error saving employee:', error)

  if (error.response?.status === 422) {
    const errors = error.response.data.errors

    const messages = Object.values(errors)
      .flat()
      .map(msg => `• ${msg}`)
      .join('\n')
    showNotification(messages, 'error')
  } else {
    showNotification(error.message || 'Failed to save employee', 'error')
  }
}
}
function removeEditQueryParam() {
  if (route.query.edit) {
    const newQuery = { ...route.query }
    delete newQuery.edit
    router.replace({ query: newQuery })
  }
}
function closeAddEmployeeModal() {
  showAddEmployeeModal.value = false
  isEditEmployeeMode.value = false
  editingEmployeeId.value = null
  resetAddEmployeeForm()
   removeEditQueryParam()
}

async function confirmDeleteEmployee(row) {
  const shouldDelete = await new Promise((resolve) => {
    if (window.Swal) {
      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete employee "${row.name}". This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
      }).then((result) => resolve(result.isConfirmed))
    } else {
      resolve(window.confirm(`Are you sure you want to delete employee "${row.name}"?`))
    }
  })

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
  // Use the same full employee modal (create modal in edit mode)
  // so edit experience matches create layout/style.
  openEditEmployee(selectedEmployeeDetail.value)
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

function restoreHrPageState() {
  if (typeof window === 'undefined') return
  try {
    const savedActiveTab = window.localStorage.getItem(HR_ACTIVE_TAB_STORAGE_KEY)
if (savedActiveTab && (headerTabs.includes(savedActiveTab) || savedActiveTab === 'Document Requests')) {
  activeTab.value = savedActiveTab
  if (savedActiveTab === 'Document Requests') loadRequestedDocuments()
}

    const savedLeaveMode = window.localStorage.getItem(HR_LEAVE_MODE_STORAGE_KEY)
    if (savedLeaveMode && ['leave', 'attendance', 'announcements'].includes(savedLeaveMode)) {
      leaveSectionMode.value = savedLeaveMode
    }

    const savedLeaveInnerTab = window.localStorage.getItem(HR_LEAVE_INNER_TAB_STORAGE_KEY)
    if (savedLeaveInnerTab && ['attendance', 'team'].includes(savedLeaveInnerTab)) {
      hrSectionTab.value = savedLeaveInnerTab
    }
  } catch (error) {
    console.warn('Unable to restore HR page state', error)
  }
}

watch(activeTab, (value) => {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(HR_ACTIVE_TAB_STORAGE_KEY, value)
})

watch(leaveSectionMode, (value) => {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(HR_LEAVE_MODE_STORAGE_KEY, value)
})

watch(hrSectionTab, (value) => {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(HR_LEAVE_INNER_TAB_STORAGE_KEY, value)
})

// ==================== LEAVE REQUESTS DATA ====================
const fetchLeaveRequestsData = async () => {
  try {
    const params = {
      per_page: leavePerPage,
      page: leavePage.value
    }
    
    if (selectedLeaveSearchChip.value) {
      params.status = selectedLeaveSearchChip.value.toLowerCase()
    }
    
    if (leaveSearchFilters.value.employee) {
      const match = leaveSearchFilters.value.employee.match(/#([^\s]+)\s+(.+)/)
      if (match) {
        params.search = match[2]
      }
    }
    
    if (leaveSearchFilters.value.leaveType) {
      params.leave_type_id = leaveSearchFilters.value.leaveType
    }
    
    const result = await fetchLeaveRequests(params)
    
    if (result?.data) {
      leaveRows.value = result.data.map(row => ({
        id: row.id,
        empId: `#EMP${String(row.user_id).padStart(4, '0')}`,
        employeeName: row.user?.name || 'Unknown',
        avatar: row.user?.avatar || 'https://i.pravatar.cc/80?img=1',
        designation: row.user?.employee_profile?.designation?.name || '—',
        leaveType: row.leave_type?.name || '—',
        startDate: formatDate(row.start_date),
        endDate: formatDate(row.end_date),
        days: row.days,
        reason: row.reason || '—',
        appliedDate: formatDate(row.created_at),
        status: row.status_label || row.status,
        approvedBy: row.hr?.name || row.parent?.name || '—',
        raw: row
      }))
    }
  } catch (error) {
    console.error('Failed to fetch leave requests:', error)
    showNotification('Failed to load leave requests', 'error')
  }
}

// ==================== ASSETS DATA ====================
const fetchAssetsData = async () => {
  try {
    const params = {
      per_page: assetsPerPage,
      page: assetsPage.value
    }
    
    if (assetSearchFilters.value.assetType) {
      params.asset_type_id = assetSearchFilters.value.assetType
    }
    
    if (assetSearchFilters.value.assetName) {
      params.search = assetSearchFilters.value.assetName
    }
    
    if (assetSearchFilters.value.status) {
      params.status = assetSearchFilters.value.status.toLowerCase()
    }
    
    if (assetSearchFilters.value.branchLocation) {
      params.branch_id = assetSearchFilters.value.branchLocation
    }
    
    if (assetSearchFilters.value.department) {
      params.department_id = assetSearchFilters.value.department
    }
    
    const result = await fetchAssets(params)
    const assetsData = Array.isArray(result?.items) ? result.items : []

    if (assetsData.length > 0) {
      assetsRows.value = assetsData.map((item) => {
        const row = item.raw || item
        return {
        id: row.id,
        assetId: row.asset_code || `#AST-${String(row.id).padStart(3, '0')}`,
        type: row.asset_type?.name || '—',
        assetName: row.name || '—',
        userName: row.current_user?.name || '—',
        userRef: row.current_user?.employee_code || '—',
        userAvatar: row.current_user?.avatar || 'https://i.pravatar.cc/80?img=1',
        handoverDate: formatDate(row.current_assignment?.handover_date),
        returnDate: formatDate(row.current_assignment?.return_date),
        brand: row.model_number || '—',
        category: row.condition || '—',
        handoverTo: row.current_user?.name || '—',
        serial: row.serial_number || '—',
        status: row.status_label || row.status || 'Available',
        branchLocation: row.branch?.name || '—',
        department: row.department?.name || '—',
        createdOn: formatDate(row.created_at),
        purchaseDate: formatDate(row.purchase_date),
        supplierName: row.supplier_name || '—',
        condition: row.condition || '—',
        unitPrice: row.unit_price || '—',
        currency: 'UAE Dirham',
        qty: row.quantity || 1,
        warrantyDate: formatDate(row.warranty_date),
        description: row.description || '—',
        remarks: row.remarks || '—',
        raw: row,
        asset_type_id: row.asset_type_id,
        branch_id: row.branch_id,
        department_id: row.department_id,
        current_assignment: row.current_assignment,
        current_user: row.current_user,
      }
      })
    } else {
      assetsRows.value = []
    }
    
    console.log("✅ Assets loaded:", assetsRows.value.length)
  } catch (error) {
    assetsRows.value = []
    console.error('❌ Failed to fetch assets:', error)
    showNotification('Failed to load assets', 'error')
  }
}

// ==================== LEAVE STATISTICS ====================
const loadLeaveStatistics = async () => {
  try {
    const stats = await fetchLeaveStatistics()
    console.log('Leave statistics:', stats)
  } catch (error) {
    console.error('Failed to load leave statistics:', error)
  }
}

// ==================== ASSET STATISTICS ====================
const loadAssetStatistics = async () => {
  try {
    const stats = await fetchAssetStatistics()
    console.log('Asset statistics:', stats)
  } catch (error) {
    console.error('Failed to load asset statistics:', error)
  }
}

// ==================== FETCH RESPONSIBLE PERSONS ====================
const fetchAssetResponsiblePersons = async () => {
  if (assetResponsiblePersons.value.length) return
  try {
    const persons = await fetchResponsiblePersons()
    assetResponsiblePersons.value = Array.isArray(persons) ? persons : []
  } catch (error) {
    console.error('Failed to load responsible persons:', error)
    assetResponsiblePersons.value = []
  }
}

// ========== ON MOUNTED ==========
onMounted(async () => {
  console.log('BASE URL:', api.defaults.baseURL)
  restoreHrPageState()
  const d = new Date()
  dateFilter.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  
  await loadFilterOptions()
  await fetchRealEmployees()
  await Promise.all([loadLeaveTypes(), loadAssetTypes()])

  // Load leave and asset data
  await fetchLeaveRequestsData()
  await fetchAssetsData()
  await loadLeaveStatistics()
  await loadAssetStatistics()
  await fetchAssetResponsiblePersons()
  await loadAnnouncementsData()
  // Load attendance data
  await fetchAttendanceData()
  await loadAttendanceSummary()
  await loadDailyAttendanceStats()

   await loadDocumentTypesList()
  await loadRequestedDocuments()
  await loadCareerJobs()

  await loadAgentData()
  syncMobileViewport()
  window.addEventListener('resize', syncMobileViewport)
  document.addEventListener('click', onDocumentClick)

  const editId = route.query.edit
  if (editId) {
    activeTab.value = 'Employees'
    await openEditEmployee({ id: editId })
  }
})
onBeforeUnmount(() => {
  if (attendanceSearchBlurTimer) clearTimeout(attendanceSearchBlurTimer)
  window.removeEventListener('resize', syncMobileViewport)
  document.removeEventListener('click', onDocumentClick)
})
</script>

<style scoped>
.hr-screen {
  padding-top: 8px;
  --hr-primary: #0b0736;
  --hr-secondary: #733e87;
  --hr-gradient: linear-gradient(135deg, var(--hr-primary) 0%, var(--hr-secondary) 100%);
  --hr-gradient-vertical: linear-gradient(180deg, var(--hr-primary) 0%, var(--hr-secondary) 100%);
  --hr-border: rgba(115, 62, 135, 0.45);
  --hr-surface-tint: #f4f0f8;
  --hr-surface-border: #e8ddf0;
}
.hr-frame {
  background: transparent;
  border-radius: 18px;
  border: none;
  padding: 10px;
  box-shadow: none;
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
  border-bottom: 2px solid var(--hr-secondary);
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
  background: var(--hr-gradient);
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
.employee-detail-action-chip.employee-detail-action-chip--light {
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(255, 255, 255, 0.55);
  color: #fff !important;
}
.employee-detail-action-chip.employee-detail-action-chip--light,
.employee-detail-action-chip.employee-detail-action-chip--light * {
  color: #fff !important;
}
.employee-detail-action-chip.employee-detail-action-chip--light :deep(svg) {
  color: #fff !important;
  fill: currentColor;
  stroke: currentColor;
}
.request-doc-btn-text {
  color: #fff !important;
}

.hr-content-card {
  margin-top: 12px;
  border: 1px solid var(--hr-border);
  border-radius: 14px;
  padding: 12px;
}
.hr-content-shell {
  background: #fff;
  border: 1px solid var(--hr-surface-border);
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
  border: 1px solid #EDE7F3;
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
  background: #F4F0F8;
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
  border: 1px solid #EDE7F3;
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
  background: #F4F0F8;
  z-index: 7;
}
.assets-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #F4F0F8;
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
  border: 1px solid #EDE7F3;
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
  background: #F4F0F8;
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
  background: #F4F0F8;
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
.announcement-overview-card .employee-overview-head {
  align-items: center;
}
.career-summary-row {
  margin-bottom: 12px;
}
.career-summary-row .hr-stat-card {
  background: #fff;
}
.career-overview-card .employee-search-btn input {
  font-size: 12px;
  color: #6b7280;
  outline: none;
}
.career-overview-card .employee-search-btn input::placeholder {
  color: #9ca3af;
}
.career-table-wrap {
  max-width: 100%;
}
.career-table {
  min-width: 1720px;
}
.career-table .sticky-action-col {
  position: sticky;
  right: 0;
  z-index: 6;
  background: #fff;
  box-shadow: -10px 0 16px -12px rgba(15, 23, 42, 0.35);
}
.career-table thead .sticky-action-col {
  background: #F4F0F8;
  z-index: 7;
}
.career-manager-cell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.career-status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  padding: 3px 10px;
  font-size: 11px;
  font-weight: 500;
  line-height: 1;
}
.career-status-open { color: #16a34a; }
.career-status-on-hold { color: #a16207; }
.career-status-closed { color: #6b7280; }
.career-applicants-view {
  display: grid;
  gap: 12px;
}
.career-applicants-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  padding: 2px 4px 0;
}
.career-applicants-head-left {
  display: grid;
  gap: 3px;
}
.career-applicants-breadcrumb {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: rgba(255, 255, 255, 0.86);
  font-size: 12px;
  font-weight: 500;
}
.career-applicants-breadcrumb iconify-icon {
  color: rgba(198, 210, 255, 0.9);
}
.career-crumb-link {
  border: none;
  background: transparent;
  color: inherit;
  padding: 0;
}
.career-applicants-breadcrumb span {
  color: #ffffff;
  font-weight: 600;
}
.career-applicants-page-title {
  margin: 0;
  color: #fff;
  font-size: 30px;
  font-weight: 700;
  line-height: 1.05;
}
.career-applicants-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transform: translateY(-3px);
}
.career-circle-btn {
  width: 36px;
  height: 36px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #6b7280;
}
.career-applicants-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #dbe4ff;
  padding: 14px;
}
.career-applicants-title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}
.career-company-avatar {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: #0a1f84;
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
}
.career-applicants-title-wrap h6 {
  margin: 0;
  font-size: 20px;
}
.career-applicants-title-wrap p {
  margin: 0;
  color: #6b7280;
  font-size: 13px;
}
.career-applicants-meta-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
}
.career-meta-pill {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 12px;
  color: #4b5563;
}
.career-count-text {
  margin-left: auto;
  color: #d3a326;
  font-weight: 600;
  font-size: 14px;
}
.career-applicants-body {
  display: grid;
  grid-template-columns: 290px minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}
.career-applicant-list-card,
.career-applicant-detail-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #dbe4ff;
  padding: 12px;
}
.career-applicant-list-card {
  position: sticky;
  top: 12px;
  max-height: calc(100vh - 210px);
  overflow: hidden;
}
.career-applicant-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.career-check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
}
.career-list-delete {
  border: none;
  background: transparent;
  color: #9ca3af;
}
.career-applicant-list-scroll {
  max-height: calc(100vh - 290px);
  overflow: auto;
  margin-top: 8px;
  padding-right: 4px;
}
.career-applicant-list-item {
  border: none;
  border-bottom: 1px solid #eef2f7;
  background: transparent;
  width: 100%;
  text-align: left;
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) auto;
  gap: 8px;
  align-items: start;
  padding: 10px 4px;
}
.career-applicant-list-item.active {
  background: #F4F0F8;
  border-radius: 10px;
}
.career-applicant-list-item img {
  width: 30px;
  height: 30px;
  border-radius: 999px;
}
.career-applicant-list-info strong {
  font-size: 15px;
}
.career-applicant-list-info p,
.career-applicant-list-info small {
  margin: 0;
  color: #6b7280;
  font-size: 12px;
}
.career-applicant-row-foot {
  margin-top: 2px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}
.career-decision-selected { color: #13a552; }
.career-decision-rejected { color: #d73939; }
.career-decision-maybe { color: #d3a326; }
.career-detail-top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
.career-applicant-detail-card {
  max-height: calc(100vh - 210px);
  overflow: auto;
  padding-right: 8px;
}
.career-applicant-profile {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.career-applicant-profile img {
  width: 34px;
  height: 34px;
  border-radius: 999px;
}
.career-applicant-profile p {
  margin: 0;
  color: #6b7280;
  font-size: 13px;
}
.career-decision-chips {
  display: inline-flex;
  gap: 8px;
}
.career-decision-chip {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 12px;
  background: #fff;
}
.career-decision-chip.active.selected { background: #16a34a; color: #fff; border-color: #16a34a; }
.career-decision-chip.active.rejected { background: #ef4444; color: #fff; border-color: #ef4444; }
.career-decision-chip.active.maybe { background: #f59e0b; color: #fff; border-color: #f59e0b; }
.career-detail-stat-grid {
  margin-top: 10px;
  border: 1px solid #eef2f7;
  border-radius: 10px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.career-detail-stat-grid > div {
  padding: 10px;
  border-right: 1px solid #eef2f7;
}
.career-detail-stat-grid > div:last-child {
  border-right: 0;
}
.career-detail-stat-grid span {
  color: #9ca3af;
  font-size: 12px;
  display: block;
}
.career-detail-stat-grid strong {
  font-size: 14px;
}
.career-detail-quick-actions {
  margin-top: 10px;
  display: flex;
  gap: 10px;
}
.career-detail-quick-actions button {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: 7px 14px;
  background: #fff;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.career-accordion-block {
  margin-top: 10px;
  border: 1px solid #eef2f7;
  border-radius: 10px;
}
.career-accordion-title {
  border: none;
  background: #fff;
  width: 100%;
  min-height: 46px;
  padding: 0 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
}
.career-details-grid {
  border-top: 1px solid #eef2f7;
  padding: 12px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 16px;
}
.career-details-grid p {
  margin: 0;
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.career-details-grid p span {
  color: #9ca3af;
}
.career-generic-box {
  border-top: 1px solid #eef2f7;
  padding: 12px;
}
.career-generic-box img {
  width: 100%;
  border-radius: 8px;
}
.career-qna-list {
  border-top: 1px solid #eef2f7;
  padding: 12px;
  display: grid;
  gap: 8px;
}
.career-qna-item {
  border: 1px solid #eef2f7;
  border-radius: 10px;
  padding: 10px;
}
.career-qna-item p {
  margin: 6px 0 0;
  font-size: 13px;
}
.career-qna-item p span {
  color: #9ca3af;
  margin-left: 10px;
}
.announcement-table-wrap {
  max-height: 520px;
}
.announcement-table .announcement-description-col {
  min-width: 360px;
  width: 360px;
  color: #6b7280;
}
.announcement-row-menu {
  width: 230px;
}
.announcement-search-modal {
  width: min(720px, 94vw);
  min-height: auto;
  grid-template-columns: 1fr;
}
.announcement-modal {
  width: min(760px, 94vw) !important;
}
.announcement-modal .add-field :deep(.vs__dropdown-toggle) {
  position: relative;
  display: flex;
  align-items: center;
  padding-right: 34px;
}
.announcement-modal .add-field :deep(.vs__actions) {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%) !important;
  height: 100% !important;
  min-height: 50px;
  display: inline-flex;
  align-items: center !important;
  justify-content: center;
  padding-right: 0;
  margin: 0;
}
.announcement-modal .add-field :deep(.vs__open-indicator) {
  margin: 0 !important;
  transform: none !important;
  align-self: center;
  display: inline-flex;
}
.announcement-modal .add-field textarea {
  min-height: 104px;
}
.attendance-row-action-cell {
  position: relative;
  overflow: visible;
}
.hr-table {
  min-width: 1220px;
}
.hr-table .sticky-action-col {
  position: sticky;
  right: 0;
  z-index: 6;
  background: #fff;
  box-shadow: -10px 0 16px -12px rgba(15, 23, 42, 0.35);
}
.hr-table thead .sticky-action-col {
  background: #F4F0F8;
  z-index: 7;
}
.attendance-row-menu {
  position: fixed;
  z-index: 21000;
  width: min(260px, 88vw);
  border-radius: 22px;
  background: #fff;
  border: 1px solid #eceff5;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16);
  padding: 14px;
  display: grid;
  gap: 8px;
}
.attendance-row-menu-item {
  width: 100%;
  min-height: 58px;
  border: none;
  border-radius: 14px;
  background: #fff;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 0 16px;
  font-size: 15px;
  font-weight: 500;
  line-height: 1;
}
.attendance-row-menu-item iconify-icon {
  font-size: 26px;
}
.attendance-row-menu-item.active {
  color: #111827;
  background: #f7f7f8;
}
.attendance-row-menu-item.active iconify-icon {
  color: #f59e0b;
}
.attendance-detail-modal {
  width: min(560px, 88vw);
  border-radius: 16px;
  border: 1px solid #eceff5;
  background: #fff;
  box-shadow: 0 22px 38px rgba(15, 23, 42, 0.2);
  padding: 18px 22px 20px;
  position: relative;
}
.attendance-detail-edit-link {
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: underline;
}
.attendance-detail-edit-link iconify-icon {
  color: #f59e0b;
}
.attendance-detail-hero {
  text-align: center;
}
.attendance-detail-icon {
  width: 84px;
  height: 84px;
  border-radius: 999px;
  margin: 4px auto 12px;
  background: radial-gradient(circle at 50% 40%, #2942a4 0%, #0b1459 70%);
  box-shadow: 0 0 26px rgba(11, 20, 89, 0.45);
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 30px;
}
.attendance-detail-hero h6 {
  margin: 0;
  font-size: 15px !important;
  font-weight: 700;
  color: #0f1a48;
}
.attendance-detail-hero p {
  margin: 10px auto 0;
  max-width: 680px;
  font-size: 13px;
  line-height: 1.55;
  color: #111827;
}
.attendance-detail-grid-card {
  margin-top: 14px;
  border: 1px solid #e7eaf2;
  border-radius: 14px;
  background: #fff;
  padding: 10px 14px;
}
.attendance-detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 8px;
}
.attendance-detail-grid p,
.attendance-detail-field {
  margin: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
}
.attendance-detail-grid span,
.attendance-detail-field > span {
  color: #7b8190;
  font-size: 13px;
}
.attendance-detail-grid strong {
  color: #111827;
  font-size: 13px;
  font-weight: 500;
  text-align: right;
}
.attendance-detail-grid p.full {
  grid-template-columns: minmax(0, 1fr) auto;
}
.attendance-status-text.status-present { color: #16a34a; }
.attendance-status-text.status-absent { color: #ef4444; }
.attendance-status-text.status-late { color: #f59e0b; }
.attendance-detail-field input,
.attendance-detail-field :deep(.vs__dropdown-toggle) {
  min-height: 36px;
  border: 1px solid #d8dde8;
  border-radius: 10px;
  width: 240px;
  font-size: 13px;
}
.attendance-detail-field :deep(.vs__selected),
.attendance-detail-field :deep(.vs__search),
.attendance-detail-field :deep(.vs__search::placeholder) {
  font-size: 12px !important;
}
.attendance-time-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  width: 260px;
}
.attendance-time-grid input {
  width: 100% !important;
  min-width: 0;
  padding: 0 10px;
}
.attendance-detail-actions {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.leave-search-modal {
  width: min(1100px, 96vw);
  min-height: 560px;
  grid-template-columns: 180px minmax(0, 1fr);
}
.attendance-search-modal .add-field :deep(.vs__dropdown-toggle) {
  position: relative;
  display: flex;
  align-items: center;
  padding-right: 34px;
}
.attendance-search-modal .add-field :deep(.vs__actions) {
  position: absolute;
  right: 10px;
  top: 50%;
  bottom: auto;
  transform: translateY(-50%) !important;
  height: 100% !important;
  min-height: 36px;
  display: inline-flex;
  align-items: center !important;
  justify-content: center;
  padding-right: 0;
  margin: 0;
}
.attendance-search-modal .add-field :deep(.vs__open-indicator) {
  margin-top: 0 !important;
  align-self: center;
  display: inline-flex;
  transform: none !important;
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
.add-employee-modal.leave-apply-modal {
  width: min(720px, 92vw);
  max-height: calc(100vh - 40px);
}
.attendance-create-modal {
  width: min(680px, 92vw) !important;
}
.attendance-create-modal .add-field :deep(.vs__dropdown-toggle) {
  position: relative;
  display: flex;
  align-items: center;
  padding-right: 34px;
}
.attendance-create-modal .add-field :deep(.vs__actions) {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%) !important;
  height: 100% !important;
  min-height: 50px;
  display: inline-flex;
  align-items: center !important;
  justify-content: center;
  padding-right: 0;
  margin: 0;
}
.attendance-create-modal .add-field :deep(.vs__open-indicator) {
  margin: 0 !important;
  transform: none !important;
  align-self: center;
  display: inline-flex;
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
.leave-apply-modal .add-field-full {
  grid-column: 1 / -1;
}
.leave-search-modal .add-field :deep(.vs__dropdown-toggle) {
  position: relative;
  display: flex;
  align-items: center;
  padding-right: 34px;
}
.leave-search-modal .add-field :deep(.vs__actions) {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%) !important;
  height: 100% !important;
  min-height: 36px;
  display: inline-flex;
  align-items: center !important;
  justify-content: center;
  padding-right: 0;
  margin: 0;
}
.leave-search-modal .add-field :deep(.vs__open-indicator) {
  margin: 0 !important;
  transform: none !important;
  align-self: center;
  display: inline-flex;
}
.career-search-modal .add-field :deep(.vs__dropdown-toggle) {
  position: relative;
  display: flex;
  align-items: center;
  padding-right: 34px;
}
.career-search-modal .add-field :deep(.vs__actions) {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%) !important;
  height: 100% !important;
  min-height: 36px;
  display: inline-flex;
  align-items: center !important;
  justify-content: center;
  padding-right: 0;
  margin: 0;
}
.career-search-modal .add-field :deep(.vs__open-indicator) {
  margin: 0 !important;
  transform: none !important;
  align-self: center;
  display: inline-flex;
}
.employee-detail-page {
  background: var(--hr-gradient);
  border: 1px solid var(--hr-border);
}
.employee-detail-breadcrumb {
  color: rgba(255, 255, 255, 0.82);
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}
.employee-detail-breadcrumb-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 6px;
}
.employee-detail-breadcrumb-row .employee-detail-breadcrumb {
  margin-bottom: 0;
}
.employee-detail-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 36px;
}
.employee-detail-title-row .overview-section-title {
  position: static;
}
.employee-detail-page-title {
  color: #fff !important;
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
.requested-documents-card {
  border: 1px solid #dce7ff;
  border-radius: 12px;
  background: #fff;
  padding: 14px;
  min-height: calc(100vh - 260px);
}
.requested-documents-card h6 {
  margin: 0 0 12px;
  font-size: 22px;
  font-weight: 700;
  color: #1f2a44;
}
.requested-document-list {
  display: grid;
  gap: 12px;
}
.requested-document-row {
  border: 1px solid #edf1f6;
  border-radius: 12px;
  background: #fff;
  padding: 12px 14px;
  display: grid;
  grid-template-columns: minmax(150px, 1.3fr) minmax(200px, 1.6fr) 120px 90px minmax(160px, 1.4fr) 110px;
  gap: 12px;
  align-items: center;
}
.requested-document-row strong {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}
.requested-document-row small {
  font-size: 11px;
  color: #9ca3af;
}
.requested-document-actions {
  display: inline-flex;
  gap: 8px;
  justify-content: flex-end;
}
.doc-status-approved { color: #15803d !important; }
.doc-status-pending { color: #d39b1a !important; }
.doc-status-rejected { color: #dc2626 !important; }

.request-doc-modal,
.request-doc-approve-modal,
.request-doc-reject-modal {
  width: min(640px, 94vw);
}
.request-doc-detail-modal {
  width: min(640px, 94vw);
}
.request-doc-modal .employee-filter-right {
  padding: 16px 18px 14px;
  gap: 10px;
}
.request-doc-title {
  margin: 0 0 6px;
  font-size: 15px;
  line-height: 1.25;
  font-weight: 600;
  color: #0f1438;
}
.request-doc-grid {
  border: 1px solid #edf1f6;
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.request-doc-modal .add-field label {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 6px;
}
.request-doc-modal .add-field :deep(.vs__dropdown-toggle) {
  height: 44px;
  min-height: 44px;
  border-radius: 12px;
  border: 1px solid #d9dee7;
  padding: 0 12px;
}
.request-doc-modal .add-field :deep(.vs__search),
.request-doc-modal .add-field :deep(.vs__selected) {
  font-size: 15px;
  color: #374151;
}
.request-doc-modal .add-field :deep(.vs__search::placeholder) {
  font-size: 15px;
  color: #9ca3af;
}
.request-doc-modal .add-field :deep(.vs__dropdown-menu) {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.14);
  margin-top: 6px;
  padding: 8px;
  max-height: 260px;
}
.request-doc-modal .add-field :deep(.vs__dropdown-option) {
  font-size: 15px;
  padding: 10px 12px;
  border-radius: 8px;
}
.request-doc-modal .add-field :deep(.vs__dropdown-option--highlight) {
  background: #F4F0F8;
  color: #111827;
}
.request-doc-modal .add-field :deep(.vs__dropdown-option--selected) {
  background: #f3f4f6;
  color: #111827;
  font-weight: 500;
}
.request-doc-grid .add-field textarea,
.request-doc-detail-modal .add-field textarea,
.request-doc-reject-modal .add-field textarea {
  width: 100%;
  min-height: 160px;
  border: 1px solid #d9dee7;
  border-radius: 12px;
  padding: 12px;
  font-size: 15px;
  color: #4b5563;
  resize: vertical;
}
.request-doc-modal .employee-filter-actions {
  justify-content: center;
  gap: 14px;
  margin-top: 2px;
}
.request-doc-modal .employee-filter-btn {
  height: 44px;
  min-width: 120px;
  font-size: 15px;
}
.request-doc-modal .employee-filter-btn.ghost {
  background: #f3f4f6;
}
.request-doc-modal .employee-filter-btn.primary {
  background: #01054b;
}
.requested-detail-grid {
  display: grid;
  gap: 10px;
}
.requested-detail-grid p {
  margin: 0;
  border: 1px solid #edf1f6;
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.requested-detail-grid span {
  font-size: 13px;
  color: #6b7280;
}
.requested-detail-grid strong {
  font-size: 14px;
}
.request-doc-detail-actions {
  margin-top: 14px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  justify-content: center;
}
.request-doc-approve-btn,
.request-doc-reject-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  border: none;
  border-radius: 999px;
  height: 40px;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
}
.request-doc-approve-btn { background: #16a34a; }
.request-doc-reject-btn { background: #ef2222; }
.request-doc-confirm-wrap {
  margin-top: 12px;
}
.request-doc-confirm-btn {
  width: 100%;
  border: none;
  border-radius: 999px;
  height: 42px;
  background: #f4a100;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
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

/* Employee search filter modal (match provided sidebar design) */
.employee-search-filter-modal {
  width: min(860px, 96vw);
  min-height: 620px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  grid-template-columns: 250px minmax(0, 1fr);
}

.employee-search-filter-modal .employee-filter-left {
  background: #f8fafc;
  border-right: 1px solid #e5e7eb;
  padding: 20px 18px;
  gap: 14px;
}

.employee-search-filter-modal .employee-filter-chip {
  height: 34px;
  font-size: 14px;
  padding: 0 14px;
  color: #6b7280;
  border: 1px solid #e5e7eb;
  font-weight: 500;
}

.employee-search-filter-modal .employee-filter-chip.active {
  background: #02014f;
  border: 1px solid #02014f;
  color: #fff;
}

.employee-search-filter-modal .employee-filter-right {
  padding: 22px 24px;
  gap: 14px;
}

.employee-search-filter-modal .employee-filter-field label {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 7px;
}

.employee-search-filter-modal .employee-filter-field input {
  height: 46px;
  border-radius: 12px;
  font-size: 14px;
  border-color: #d1d5db;
}

.employee-search-filter-modal .employee-filter-field input::placeholder {
  font-size: 14px;
  color: #9ca3af;
}

.employee-search-filter-modal .employee-filter-field :deep(.vs__dropdown-toggle) {
  height: 46px;
  min-height: 46px;
  border-radius: 12px;
  border-color: #d1d5db;
}

.employee-search-filter-modal .employee-filter-field :deep(.vs__search),
.employee-search-filter-modal .employee-filter-field :deep(.vs__selected),
.employee-search-filter-modal .employee-filter-field :deep(.vs__dropdown-option) {
  font-size: 14px;
}

.employee-search-filter-modal .employee-filter-date-wrap {
  position: relative;
}

.employee-search-filter-modal .employee-filter-date-wrap input {
  padding-right: 40px;
}

.employee-search-filter-modal .employee-filter-date-wrap iconify-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 16px;
  pointer-events: none;
}

.employee-search-filter-modal .employee-filter-actions {
  margin-top: 10px;
  gap: 12px;
}

.employee-search-filter-modal .employee-filter-btn {
  height: 50px;
  min-width: 112px;
  font-size: 15px;
  border-radius: 999px;
}

.employee-search-filter-modal .employee-filter-btn.ghost {
  background: #f3f4f6;
  color: #111827;
}

.employee-search-filter-modal .employee-filter-btn.primary {
  background: #000;
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
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.profile-photo-wrap {
  position: relative;
  width: 110px;
  height: 110px;
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
  border: none;
  padding: 0;
  cursor: pointer;
}
.profile-photo-hint {
  font-size: 10px;
  color: #94a3b8;
}
.profile-photo-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.profile-photo-edit-btn {
  position: absolute;
  right: 0;
  bottom: 0;
  transform: translate(20%, 20%);
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
.doc-chip.has-file:not(.active) {
  border-color: #86efac;
  background: #f0fdf4;
  color: #166534;
}
.doc-chip.has-file.active {
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
}
.upload-dropzone {
  margin-top: 8px;
  border: 1px dashed #d9dee7;
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
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
.hr-search-anchor {
  min-width: 360px;
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
.hr-search-wrap--kanban {
  cursor: text;
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.hr-search-wrap--focused,
.hr-search-wrap--filtered {
  border-color: #d4bfe0;
  box-shadow: 0 6px 18px rgba(115, 62, 135, 0.1);
}
.hr-search-plus,
.hr-search-icon,
.hr-search-clear {
  flex-shrink: 0;
  font-size: 16px;
  cursor: pointer;
}
.hr-search-clear:hover {
  color: #733e87;
}
.hr-search-input {
  border: none;
  outline: none;
  width: 100%;
  min-width: 0;
  font-size: 12px;
  color: #111827;
  background: transparent;
  pointer-events: auto;
  cursor: text;
}
.hr-search-input::placeholder {
  color: #9ca3af;
}
.hr-attendance-search-dropdown-outer {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 1050;
}
.hr-attendance-search-dropdown-outer :deep(.hr-attendance-search-field__label) {
  margin: 0 0 4px !important;
  font-size: 10px !important;
  font-weight: 500 !important;
  line-height: 1.2 !important;
  color: #6b7280 !important;
}
.hr-attendance-search-dropdown-outer :deep(.hr-attendance-search-field) {
  padding: 8px 10px;
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
  border-color: #D4BFE0;
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
  border: 1px solid #EDE7F3;
  border-radius: 12px;
  overflow: hidden;
}
.hr-table thead th {
  background: #F4F0F8;
  border-bottom: 1px solid #EDE7F3;
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  padding: 12px 10px;
  white-space: nowrap;
}
.hr-table tbody td {
  border-bottom: 1px solid #EDE7F3;
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
  color: #733E87;
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
.hr-table tbody tr:hover { background: #F4F0F8; }

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
  background: linear-gradient(90deg, #F4F0F8 25%, #EDE7F3 37%, #F4F0F8 63%);
  background-size: 400px 100%;
  animation: hrShimmer 1.1s infinite linear;
}
@keyframes hrShimmer { 0% { background-position: -400px 0; } 100% { background-position: 400px 0; } }

.hr-empty-tab {
  min-height: 620px;
}

.team-attendance-view {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.team-attendance-card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  overflow: hidden;
}

.team-attendance-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid #EDE7F3;
}

.team-attendance-count {
  font-size: 12px;
  color: #64748b;
  font-weight: 600;
}

.team-attendance-table-wrap {
  overflow-x: auto;
}

.manager-group-row td {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
  font-size: 12px;
  padding-top: 8px;
  padding-bottom: 8px;
}

.manager-group-count {
  color: #64748b;
  font-weight: 500;
}

.team-attendance-section-divider {
  margin-top: 8px;
  padding: 8px 2px;
}

.department-boxes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}

.department-box-card {
  border: 1px solid #E8DDF0;
  border-radius: 16px;
  background: linear-gradient(180deg, #ffffff 0%, #F4F0F8 100%);
  padding: 20px 18px;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 145px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  align-items: center;
  justify-content: center;
}

.department-box-card:hover {
  transform: translateY(-2px);
  border-color: #93c5fd;
  box-shadow: 0 14px 28px rgba(30, 64, 175, 0.14);
}

.department-box-card strong {
  color: #0f172a;
  font-size: 18px;
  line-height: 1.25;
  font-weight: 700;
}

.department-box-card span {
  font-size: 14px;
  color: #1e293b;
  font-weight: 600;
}

.department-box-card small {
  color: #64748b;
  font-size: 12px;
  margin-top: auto;
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 4px 10px;
  border-radius: 999px;
  background: #eaf2ff;
  border: 1px solid #cfe0ff;
}

@media (max-width: 900px) {
  .department-boxes-grid {
    grid-template-columns: 1fr;
  }
}

.hr-sales-position-filter {
  min-width: 230px;
}

.hr-sales-position-filter label {
  display: block;
  margin-bottom: 4px;
  font-size: 12px;
  color: #64748b;
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
  border-bottom: 1px solid #EDE7F3;
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
    background: var(--hr-surface-tint);
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
  .attendance-create-modal {
    width: min(680px, 94vw) !important;
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
/* أضف هذا في نهاية ملف style */
.leave-half-day-checkbox {
  margin-top: 4px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #374151;
  cursor: pointer;
  user-select: none;
}

.checkbox-label input[type="checkbox"] {
  width: 20px;
  height: 20px;
  accent-color: var(--hr-secondary);
  cursor: pointer;
  margin: 0;
  flex-shrink: 0;
}

.checkbox-label input[type="checkbox"]:checked {
  accent-color: var(--hr-secondary);
}

.checkbox-label span {
  font-weight: 500;
  color: #1f2937;
}
.requested-document-row--with-employee {
  grid-template-columns: minmax(140px, 1fr) minmax(150px, 1.3fr) minmax(200px, 1.6fr) 120px 90px minmax(160px, 1.4fr) 110px;
}
</style>

