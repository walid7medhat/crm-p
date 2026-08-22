<template>
  <div class="vp-page">
    <!-- Profile Details hero -->
    <section class="vp-hero">
      <div class="vp-hero__top">
        <h2 class="vp-hero__title">Profile Details</h2>
        <span class="vp-presence" :class="isOnline ? 'is-online' : 'is-offline'">
          <span class="vp-presence__dot" />
          {{ isOnline ? 'Online' : 'Offline' }}
        </span>
      </div>

      <div class="vp-hero__body">
        <div class="vp-hero__avatar-wrap">
          <img
            v-if="displayAvatar"
            :src="displayAvatar"
            :alt="user.name || 'Profile'"
            class="vp-hero__avatar"
          />
          <div v-else class="vp-hero__avatar vp-hero__avatar--placeholder">
            {{ userInitials }}
          </div>
          <label class="vp-hero__camera" for="imageUpload" title="Change photo">
            <iconify-icon icon="lucide:camera" />
          </label>
          <input
            id="imageUpload"
            type="file"
            accept=".png, .jpg, .jpeg, .gif"
            hidden
            @change="onImageChange"
          />
        </div>

        <div class="vp-hero__meta">
          <h3 class="vp-hero__name">{{ user.name || 'User' }}</h3>
          <p class="vp-hero__email">{{ user.email || '—' }}</p>
          <p class="vp-hero__role">{{ user.role_name || 'User' }}</p>
        </div>

        <p class="vp-hero__last-active">
          Last Active :
          <span>{{ lastActiveText }}</span>
        </p>
      </div>
    </section>

    <!-- Personal Information -->
    <section class="vp-personal">
      <h2 class="vp-personal__title">Personal Information</h2>
      <div class="vp-personal__grid">
        <div class="vp-personal__item">
          <iconify-icon icon="lucide:user" class="vp-personal__icon" />
          <div>
            <span class="vp-personal__label">Full Name</span>
            <span class="vp-personal__value">{{ user.name || '—' }}</span>
          </div>
        </div>
        <div class="vp-personal__item">
          <iconify-icon icon="lucide:phone" class="vp-personal__icon" />
          <div>
            <span class="vp-personal__label">Phone Number</span>
            <span class="vp-personal__value">{{ user.phone || '—' }}</span>
          </div>
        </div>
        <div class="vp-personal__item">
          <iconify-icon icon="lucide:mail" class="vp-personal__icon" />
          <div>
            <span class="vp-personal__label">Email</span>
            <span class="vp-personal__value">{{ user.email || '—' }}</span>
          </div>
        </div>
        <div class="vp-personal__item">
          <iconify-icon icon="lucide:shield" class="vp-personal__icon" />
          <div>
            <span class="vp-personal__label">Role</span>
            <span class="vp-personal__value">{{ user.role_name || 'User' }}</span>
          </div>
        </div>
        <div class="vp-personal__item">
          <iconify-icon icon="lucide:calendar" class="vp-personal__icon" />
          <div>
            <span class="vp-personal__label">Member Since</span>
            <span class="vp-personal__value">{{ user.created_at || '—' }}</span>
          </div>
        </div>
        <div class="vp-personal__item">
          <iconify-icon icon="lucide:user-check" class="vp-personal__icon" />
          <div>
            <span class="vp-personal__label">Supervisor</span>
            <span class="vp-personal__value">{{ user.parent_name || '—' }}</span>
          </div>
        </div>
        <div class="vp-personal__item">
          <iconify-icon icon="lucide:clock" class="vp-personal__icon" />
          <div>
            <span class="vp-personal__label">Status</span>
            <span class="vp-personal__value">{{ statusLabel }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Nav + content (single card) -->
    <div class="vp-body">
      <aside class="vp-nav" role="tablist">
        <button
          type="button"
          class="vp-nav__item"
          :class="{ 'is-active': activeTab === 'edit-profile' }"
          role="tab"
          @click="activeTab = 'edit-profile'"
        >
          <span class="vp-nav__left">
            <iconify-icon icon="lucide:user" />
            Edit Profile
          </span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button
          type="button"
          class="vp-nav__item"
          :class="{ 'is-active': activeTab === 'change-password' }"
          role="tab"
          @click="activeTab = 'change-password'"
        >
          <span class="vp-nav__left">
            <iconify-icon icon="lucide:lock" />
            Change Password
          </span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button
          type="button"
          class="vp-nav__item"
          :class="{ 'is-active': activeTab === 'vacation' }"
          role="tab"
          @click="activeTab = 'vacation'"
        >
          <span class="vp-nav__left">
            <iconify-icon icon="lucide:umbrella" />
            Vacation
          </span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button
          type="button"
          class="vp-nav__item"
          :class="{ 'is-active': activeTab === 'attendance' }"
          role="tab"
          @click="activeTab = 'attendance'"
        >
          <span class="vp-nav__left">
            <iconify-icon icon="lucide:calendar-check" />
            Attendance
          </span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button
          v-if="isSalesProfile"
          type="button"
          class="vp-nav__item"
          :class="{ 'is-active': activeTab === 'sales-performance' }"
          role="tab"
          @click="activeTab = 'sales-performance'; loadSalesPerformance(); loadAiIntelligence()"
        >
          <span class="vp-nav__left">
            <iconify-icon icon="lucide:chart-column" />
            Sales Performance
          </span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button
          v-if="canViewTeamPerformance"
          type="button"
          class="vp-nav__item"
          :class="{ 'is-active': activeTab === 'team-performance' }"
          role="tab"
          @click="activeTab = 'team-performance'; loadTeamPerformance()"
        >
          <span class="vp-nav__left">
            <iconify-icon icon="lucide:users" />
            Team Performance
          </span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>

        <button type="button" class="vp-nav__item" :class="{ 'is-active': activeTab === 'my-documents' }" role="tab" @click="activeTab = 'my-documents'; loadMyDocuments()">
          <span class="vp-nav__left"><iconify-icon icon="lucide:file-text" /> My Documents</span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button type="button" class="vp-nav__item" :class="{ 'is-active': activeTab === 'my-assets' }" role="tab" @click="activeTab = 'my-assets'; loadMyAssets()">
          <span class="vp-nav__left"><iconify-icon icon="lucide:briefcase" /> My Assets</span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button type="button" class="vp-nav__item" :class="{ 'is-active': activeTab === 'my-leave' }" role="tab" @click="activeTab = 'my-leave'; loadMyLeave()">
          <span class="vp-nav__left"><iconify-icon icon="lucide:calendar-off" /> My Leave</span>
          <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
        </button>
        <button
            v-if="isTeamLead || isSuperAdmin"
            type="button"
            class="vp-nav__item"
            :class="{ 'is-active': activeTab === 'team-leave' }"
            role="tab"
            @click="activeTab = 'team-leave'; loadTeamLeaveRequests()"
          >
            <span class="vp-nav__left"><iconify-icon icon="lucide:users" /> Team Leave Requests</span>
            <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
            <span v-if="teamLeavePendingCount > 0" class="vp-nav__badge">{{ teamLeavePendingCount }}</span>
          </button>
          <button
              type="button"
              class="vp-nav__item"
              :class="{ 'is-active': activeTab === 'announcements' }"
              role="tab"
              @click="activeTab = 'announcements'; loadMyAnnouncements()"
            >
              <span class="vp-nav__left"><iconify-icon icon="lucide:megaphone" /> Announcements</span>
              <iconify-icon icon="lucide:chevron-right" class="vp-nav__chevron" />
              <span v-if="unreadAnnouncementsCount > 0" class="vp-nav__badge">{{ unreadAnnouncementsCount }}</span>
            </button>

      </aside>

      <main class="vp-content">
        <!-- Edit Profile -->
        <div v-if="activeTab === 'edit-profile'" class="vp-panel">
          <div class="vp-panel__head">
            <h3 class="vp-panel__title">Edit Profile</h3>
          </div>

          <form class="vp-edit-form" @submit.prevent="updateProfile">
            <div class="vp-field">
              <label class="vp-field__label">First Name</label>
              <input
                v-model="formData.name"
                type="text"
                class="vp-field__input"
                placeholder="Enter First Name"
                required
              />
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Email</label>
              <input
                v-model="formData.email"
                type="email"
                class="vp-field__input"
                placeholder="Enter email address"
                required
              />
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Phone Number</label>
              <div class="vp-phone-input">
                <span class="vp-phone-input__prefix" title="UAE">
                  <span class="vp-phone-input__flag" aria-hidden="true">🇦🇪</span>
                  <iconify-icon icon="lucide:chevron-down" />
                </span>
                <input
                  v-model="formData.phone"
                  type="text"
                  class="vp-field__input vp-phone-input__field"
                  placeholder="+971 00 000 0000"
                />
              </div>
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Department</label>
              <input
                type="text"
                class="vp-field__input"
                :value="user.department || ''"
                placeholder="Add Department"
                readonly
              />
            </div>
            <div class="vp-form-actions">
              <button type="button" class="vp-btn-ghost" @click="resetForm">Clear</button>
              <button type="submit" class="vp-btn-primary" :disabled="loading">
                <span v-if="loading">Saving...</span>
                <span v-else>Save Changes</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Change Password -->
        <div v-if="activeTab === 'change-password'" class="vp-panel">
          <div class="vp-panel__head">
            <h3 class="vp-panel__title">Change Password</h3>
          </div>
          <form class="vp-password-form" @submit.prevent="changePassword">
            <div class="vp-field">
              <label class="vp-field__label">Current Password</label>
              <div class="vp-input-wrap">
                <input
                  v-model="passwordData.current_password"
                  :type="currentPasswordVisible ? 'text' : 'password'"
                  class="vp-field__input"
                  placeholder="Enter Password"
                  required
                />
                <button
                  type="button"
                  class="vp-input-wrap__toggle"
                  :aria-label="currentPasswordVisible ? 'Hide password' : 'Show password'"
                  @click="currentPasswordVisible = !currentPasswordVisible"
                >
                  <iconify-icon :icon="currentPasswordVisible ? 'lucide:eye-off' : 'lucide:eye'" />
                </button>
              </div>
            </div>
            <div class="vp-field">
              <label class="vp-field__label">New Password</label>
              <div class="vp-input-wrap">
                <input
                  v-model="passwordData.new_password"
                  :type="newPasswordVisible ? 'text' : 'password'"
                  class="vp-field__input"
                  placeholder="Enter New Password"
                  required
                />
                <button
                  type="button"
                  class="vp-input-wrap__toggle"
                  @click="newPasswordVisible = !newPasswordVisible"
                >
                  <iconify-icon :icon="newPasswordVisible ? 'lucide:eye-off' : 'lucide:eye'" />
                </button>
              </div>
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Confirm Password</label>
              <div class="vp-input-wrap">
                <input
                  v-model="passwordData.confirm_password"
                  :type="confirmPasswordVisible ? 'text' : 'password'"
                  class="vp-field__input"
                  placeholder="Confirm Password"
                  required
                />
                <button
                  type="button"
                  class="vp-input-wrap__toggle"
                  @click="confirmPasswordVisible = !confirmPasswordVisible"
                >
                  <iconify-icon :icon="confirmPasswordVisible ? 'lucide:eye-off' : 'lucide:eye'" />
                </button>
              </div>
            </div>
            <div class="vp-form-actions">
              <button type="button" class="vp-btn-ghost" @click="clearPasswordForm">Clear</button>
              <button type="submit" class="vp-btn-primary" :disabled="passwordLoading">
                <span v-if="passwordLoading">Updating...</span>
                <span v-else>Update Password</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Vacation -->
        <div v-if="activeTab === 'vacation'" class="vp-panel">
          <div class="vp-panel__head">
            <h3 class="vp-panel__title">Vacation Mode</h3>
          </div>

          <div class="vp-vacation-toggle-card">
            <div class="vp-vacation-toggle-card__left">
              <span class="vp-vacation-toggle-card__icon" aria-hidden="true">
                <iconify-icon icon="lucide:umbrella" />
              </span>
              <div>
                <div class="vp-vacation-toggle__title">Activate Vacation Mode</div>
                <p>When activated, new requests will be assigned to selected agent</p>
              </div>
            </div>
            <label class="vp-switch">
              <input
                id="vacationSwitch"
                v-model="vacationData.active"
                type="checkbox"
                :disabled="vacationLoading"
              />
              <span class="vp-switch__slider" />
            </label>
          </div>

          <div v-if="vacationData.active" class="vp-vacation-active">
            <div class="vp-field">
              <label class="vp-field__label">Select Agent to handle your requests</label>
              <select
                v-model="vacationData.delegate_id"
                class="vp-field__input"
                :disabled="vacationLoading"
              >
                <option value="">Choose an agent...</option>
                <option v-for="agent in agentsList" :key="agent.id" :value="agent.id">
                  {{ agent.name }} ({{ agent.email }})
                </option>
              </select>
              <span class="vp-field__hint">This agent will receive all new requests while you're on vacation</span>
            </div>
            <div class="vp-form-actions" style="margin-top: 16px">
              <button
                type="button"
                class="vp-btn-primary"
                :disabled="vacationLoading || !vacationData.delegate_id"
                @click="saveVacationMode"
              >
                <span v-if="vacationLoading">Saving...</span>
                <span v-else>Save Changes</span>
              </button>
            </div>
          </div>

          <div v-else class="vp-vacation-empty">
            <iconify-icon icon="lucide:info" />
            <p>Vacation mode is currently inactive. Turn it on to delegate your requests.</p>
          </div>

          <div class="vp-status-card">
            <div class="vp-status-card__meta">
              <span class="vp-status-card__eyebrow">Current Status</span>
              <span class="vp-status-card__label">Vacation Mode</span>
            </div>
            <span
              class="vp-badge"
              :class="vacationData.active ? 'vp-badge--vacation' : 'vp-badge--active'"
            >
              <span class="vp-badge__dot" />
              {{ vacationData.active ? 'On Vacation' : 'Active' }}
            </span>
          </div>
        </div>

        <!-- Attendance -->
        <div v-if="activeTab === 'attendance'" class="vp-panel vp-panel--attendance">
          <UserAttendanceCarousel />

          <section v-if="isSuperAdmin" class="vp-schedule">
            <div class="vp-schedule__head">
              <div class="vp-schedule__donut" aria-hidden="true" />
              <div class="vp-schedule__title">Attendance Schedule</div>
            </div>
            <div class="vp-schedule__field">
              <label class="vp-schedule__label">Day</label>
              <select
                v-model.number="attendanceSettings.day_of_week"
                class="vp-schedule__select"
                :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
              >
                <option v-for="day in dayOptions" :key="day.value" :value="day.value">{{ day.label }}</option>
              </select>
            </div>
            <div class="vp-schedule__row vp-schedule__field">
              <div>
                <label class="vp-schedule__label">From</label>
                <input
                  v-model="attendanceSettings.start_time"
                  type="time"
                  class="vp-schedule__input"
                  :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
                />
              </div>
              <div>
                <label class="vp-schedule__label">To</label>
                <input
                  v-model="attendanceSettings.end_time"
                  type="time"
                  class="vp-schedule__input"
                  :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
                />
              </div>
            </div>
            <div class="vp-schedule__field">
              <label class="vp-schedule__label">Departments (required check-in)</label>
              <div
                class="vp-schedule__depts"
                :class="{ 'is-disabled': attendanceSettingsLoading || attendanceSettingsSaving }"
              >
                <div class="vp-schedule__chips">
                  <template v-if="selectedDepartmentLabels.length">
                    <span v-for="label in selectedDepartmentLabels" :key="label" class="vp-dept-tag">{{ label }}</span>
                  </template>
                  <span v-else class="vp-schedule__hint" style="margin: 0">All departments selected</span>
                </div>
                <label
                  v-for="dept in departmentOptions"
                  :key="dept.value"
                  class="vp-schedule__dept-item"
                >
                  <input
                    type="checkbox"
                    :value="dept.value"
                    :checked="attendanceSettings.department_ids.includes(dept.value)"
                    :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
                    @change="toggleDepartmentSelection(dept.value)"
                  />
                  <span>{{ dept.label }}</span>
                </label>
              </div>
              <p class="vp-schedule__hint">
                Select one or more departments. Leave empty to apply to all departments.
              </p>
              <button
                type="button"
                class="vp-btn-save-sm"
                :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
                @click="saveAttendanceSettings"
              >
                <span v-if="attendanceSettingsSaving">Saving...</span>
                <span v-else>Save Schedule</span>
              </button>
            </div>
          </section>
        </div>

        <div v-if="activeTab === 'sales-performance'" class="vp-panel vp-hub">
          <div class="vp-panel__head">
            <div>
              <h3 class="vp-panel__title">Sales Performance</h3>
              <p class="vp-panel__subtitle">Converted deals, lead quality, deal value, and commission for this sales profile.</p>
            </div>
            <div class="vp-sales-toolbar">
              <div class="vp-sales-date">
                <label>From</label>
                <input v-model="salesPerformanceFilters.from_date" type="date" @change="loadSalesPerformance" />
              </div>
              <div class="vp-sales-date">
                <label>To</label>
                <input v-model="salesPerformanceFilters.to_date" type="date" @change="loadSalesPerformance" />
              </div>
              <button type="button" class="vp-btn-primary vp-btn-primary--sm" :disabled="salesPerformanceLoading" @click="loadSalesPerformance">
                <span v-if="salesPerformanceLoading">Refreshing...</span>
                <span v-else>Refresh</span>
              </button>
            </div>
          </div>

          <div v-if="salesPerformanceError" class="vp-sales-state vp-sales-state--error">
            {{ salesPerformanceError }}
          </div>

          <div class="vp-sales-kpis">
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Converted Deals</span>
              <strong class="vp-sales-kpi__value">{{ salesPerformanceSummary.converted_count }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Avg Lead Score</span>
              <strong class="vp-sales-kpi__value">{{ salesPerformanceSummary.avg_lead_score ?? '—' }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Deal Value</span>
              <strong class="vp-sales-kpi__value">{{ formatCurrency(salesPerformanceSummary.total_amount) }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Agent Commission</span>
              <strong class="vp-sales-kpi__value">{{ formatCurrency(salesPerformanceSummary.total_agent_commission) }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Company Commission</span>
              <strong class="vp-sales-kpi__value">{{ formatCurrency(salesPerformanceSummary.total_company_commission) }}</strong>
            </article>
          </div>

          <div class="vp-sales-card">
            <div class="vp-sales-card__head">
              <div>
                <h4 class="vp-section-title vp-section-title--sm">Deal Performance</h4>
                <p class="vp-panel__subtitle">Based on the existing agent performance report data.</p>
              </div>
            </div>

            <div v-if="salesPerformanceLoading" class="vp-sales-state">Loading performance data...</div>
            <div v-else-if="!salesPerformanceDeals.length" class="vp-sales-state">
              No converted deals found in the selected date range.
            </div>
            <div v-else class="vp-sales-table-wrap">
              <table class="vp-sales-table">
                <thead>
                  <tr>
                    <th>Lead</th>
                    <th>Deal #</th>
                    <th>Location</th>
                    <th class="is-right">Lead Score</th>
                    <th class="is-right">Amount</th>
                    <th class="is-right">Agent Comm.</th>
                    <th class="is-right">Company Comm.</th>
                    <th>Converted</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="deal in salesPerformanceDeals" :key="deal.deal_id">
                    <td>{{ deal.lead_name || '—' }}</td>
                    <td>{{ deal.deal_number || `#${deal.deal_id}` }}</td>
                    <td>
                      {{ deal.location || '—' }}
                      <span v-if="deal.location_extra" class="vp-sales-location-extra">+{{ deal.location_extra }} more</span>
                    </td>
                    <td class="is-right">
                      <span class="vp-sales-score" :class="scoreClass(deal.lead_score)">
                        {{ deal.lead_score ?? '—' }}
                      </span>
                    </td>
                    <td class="is-right">{{ formatCurrency(deal.deal_amount, deal.currency) }}</td>
                    <td class="is-right is-accent">
                      {{ formatCurrency(deal.agent_commission, deal.currency) }}
                      <span v-if="deal.commission_percentage != null" class="vp-sales-location-extra">({{ deal.commission_percentage }}%)</span>
                    </td>
                    <td class="is-right">{{ formatCurrency(deal.company_commission, deal.currency) }}</td>
                    <td>{{ formatDateShort(deal.converted_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="vp-sales-card vp-ai-card">
            <div class="vp-sales-card__head">
              <div>
                <h4 class="vp-section-title vp-section-title--sm">AI Sales Intelligence</h4>
                <p class="vp-panel__subtitle">Behavior score, pipeline discipline, and coaching insights from the AI engine.</p>
              </div>
              <button type="button" class="vp-btn-primary vp-btn-primary--sm" :disabled="aiIntelLoading || aiIntelRecalculating" @click="recalculateAiIntelligence">
                <span v-if="aiIntelRecalculating">Recalculating...</span>
                <span v-else>Recalculate</span>
              </button>
            </div>

            <div v-if="aiIntelLoading" class="vp-sales-state">Loading AI intelligence...</div>
            <div v-else-if="aiIntelError" class="vp-sales-state">{{ aiIntelError }}</div>
            <template v-else-if="aiIntelAgent">
              <div class="vp-ai-kpis">
                <article class="vp-ai-kpi">
                  <span class="vp-sales-kpi__label">AI Score</span>
                  <strong class="vp-ai-kpi__value" :class="aiScoreClass(aiOverallScore)">{{ aiOverallScore }}</strong>
                </article>
                <article class="vp-ai-kpi">
                  <span class="vp-sales-kpi__label">Status</span>
                  <strong class="vp-ai-kpi__text">{{ formatStatusLabel(aiIntelAgent.status) }}</strong>
                </article>
                <article class="vp-ai-kpi">
                  <span class="vp-sales-kpi__label">Risk Level</span>
                  <strong class="vp-ai-kpi__text">{{ formatStatusLabel(aiIntelAgent.risk_level) }}</strong>
                </article>
                <article class="vp-ai-kpi">
                  <span class="vp-sales-kpi__label">Updated</span>
                  <strong class="vp-ai-kpi__text">{{ formatDateShort(aiIntelAgent.computed_at) }}</strong>
                </article>
              </div>

              <div class="vp-ai-subscores">
                <div v-for="s in aiSubscoreItems" :key="s.key" class="vp-ai-subscore">
                  <span class="vp-ai-subscore__label">{{ s.label }}</span>
                  <div class="vp-ai-subscore__bar"><div :style="{ width: s.value + '%', background: s.color }" /></div>
                  <span class="vp-ai-subscore__value">{{ s.value }}</span>
                </div>
                <p v-if="!aiSubscoreItems.length" class="vp-muted-note">No subscore data available.</p>
              </div>

              <div v-if="aiIntelAgent.executive_summary" class="vp-ai-summary">
                <h5>Executive Summary</h5>
                <p>{{ aiIntelAgent.executive_summary }}</p>
              </div>

              <div v-if="(aiIntelAgent.coaching_cards || []).length" class="vp-ai-coaching">
                <h5>Coaching</h5>
                <article v-for="(card, i) in aiIntelAgent.coaching_cards" :key="i" class="vp-ai-coach" :class="`is-${card.priority}`">
                  <strong>{{ card.title }}</strong>
                  <p>{{ card.body }}</p>
                </article>
              </div>

              <div v-if="aiIntelObservations.length" class="vp-ai-observations-wrap">
                <h5>Behavior Observations</h5>
                <ul class="vp-ai-observations">
                  <li v-for="(obs, i) in aiIntelObservations.slice(0, 8)" :key="i" :class="`is-${obs.severity}`">
                    {{ obs.observation }}
                  </li>
                </ul>
              </div>
            </template>
            <div v-else class="vp-sales-state">No AI intelligence data available yet.</div>
          </div>
        </div>

        <!-- Team Performance -->
        <div v-if="activeTab === 'team-performance'" class="vp-panel vp-hub">
          <div class="vp-panel__head">
            <div>
              <h3 class="vp-panel__title">Team Performance</h3>
              <p class="vp-panel__subtitle">Converted deals and commission split for your team hierarchy.</p>
            </div>
            <div class="vp-sales-toolbar">
              <div class="vp-sales-date">
                <label>From</label>
                <input v-model="teamPerformanceFilters.from_date" type="date" @change="loadTeamPerformance" />
              </div>
              <div class="vp-sales-date">
                <label>To</label>
                <input v-model="teamPerformanceFilters.to_date" type="date" @change="loadTeamPerformance" />
              </div>
              <button type="button" class="vp-btn-primary vp-btn-primary--sm" :disabled="teamPerformanceLoading" @click="loadTeamPerformance">
                <span v-if="teamPerformanceLoading">Refreshing...</span>
                <span v-else>Refresh</span>
              </button>
            </div>
          </div>

          <div v-if="teamPerformanceError" class="vp-sales-state vp-sales-state--error">
            {{ teamPerformanceError }}
          </div>

          <div class="vp-sales-kpis">
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Team Members</span>
              <strong class="vp-sales-kpi__value">{{ teamPerformanceSummary.agents_count }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Converted Deals</span>
              <strong class="vp-sales-kpi__value">{{ teamPerformanceSummary.deals_count }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Team Deal Value</span>
              <strong class="vp-sales-kpi__value">{{ formatCurrency(teamPerformanceSummary.total_amount) }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Agent Commission</span>
              <strong class="vp-sales-kpi__value">{{ formatCurrency(teamPerformanceSummary.total_agent_commission) }}</strong>
            </article>
            <article class="vp-sales-kpi">
              <span class="vp-sales-kpi__label">Company Commission</span>
              <strong class="vp-sales-kpi__value">{{ formatCurrency(teamPerformanceSummary.total_company_commission) }}</strong>
            </article>
          </div>

          <div class="vp-sales-card">
            <div class="vp-sales-card__head">
              <div>
                <h4 class="vp-section-title vp-section-title--sm">Team Members</h4>
                <p class="vp-panel__subtitle">Every direct and indirect report with converted deals in range.</p>
              </div>
            </div>

            <div v-if="teamPerformanceLoading" class="vp-sales-state">Loading team performance...</div>
            <div v-else-if="!teamPerformanceAgents.length" class="vp-sales-state">
              No converted deals found for your team in the selected date range.
            </div>
            <div v-else class="vp-sales-table-wrap">
              <table class="vp-sales-table">
                <thead>
                  <tr>
                    <th>Agent</th>
                    <th class="is-right">Deals</th>
                    <th class="is-right">Avg Lead Score</th>
                    <th class="is-right">Deal Value</th>
                    <th class="is-right">Agent Comm.</th>
                    <th class="is-right">Company Comm.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="agent in teamPerformanceAgents" :key="agent.agent_id">
                    <td>
                      {{ agent.agent_name }}
                      <span v-if="agent.agent_email" class="vp-sales-location-extra">{{ agent.agent_email }}</span>
                    </td>
                    <td class="is-right">{{ agent.converted_count }}</td>
                    <td class="is-right">
                      <span class="vp-sales-score" :class="scoreClass(agent.avg_lead_score)">
                        {{ agent.avg_lead_score ?? '—' }}
                      </span>
                    </td>
                    <td class="is-right">{{ formatCurrency(agent.total_amount) }}</td>
                    <td class="is-right is-accent">{{ formatCurrency(agent.total_agent_commission) }}</td>
                    <td class="is-right">{{ formatCurrency(agent.total_company_commission) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>


        <!-- My Documents -->
        <div v-if="activeTab === 'my-documents'" class="vp-panel vp-hub">
          <div class="vp-panel__head vp-hub__head">
            <div>
              <h3 class="vp-panel__title">My Document Requests</h3>
              <p class="vp-panel__subtitle">Request certificates and track approval in one place.</p>
            </div>
            <button type="button" class="vp-btn-primary vp-hub__cta" @click="openDocRequestModal()">
              Request Document
              <iconify-icon icon="lucide:plus" />
            </button>
          </div>

          <div class="vp-stat-row">
            <div class="vp-stat-chip"><span>{{ docRequestStats.total }}</span> Total</div>
            <div class="vp-stat-chip is-pending"><span>{{ docRequestStats.pending }}</span> Pending</div>
            <div class="vp-stat-chip is-approved"><span>{{ docRequestStats.approved }}</span> Approved</div>
            <div class="vp-stat-chip is-rejected"><span>{{ docRequestStats.rejected }}</span> Rejected</div>
          </div>

          <div class="vp-hub__toolbar">
            <label class="vp-search">
              <iconify-icon icon="lucide:search" />
              <input v-model="documentSearch" type="text" placeholder="Search document type or description" />
            </label>
          </div>

          <div v-if="docRequestsLoading" class="vp-loading">Loading document requests...</div>
          <div v-else-if="!filteredDocumentRequests.length" class="vp-empty">
            <div class="vp-empty__icon"><iconify-icon icon="lucide:file-text" /></div>
            <h4>{{ documentSearch ? 'No matching requests' : 'No document requests yet' }}</h4>
            <p>{{ documentSearch ? 'Try a different search term.' : 'Request a salary certificate, experience letter, and more.' }}</p>
            <button v-if="!documentSearch" type="button" class="vp-btn-primary" @click="openDocRequestModal()">Request Document</button>
          </div>
          <div v-else class="vp-req-list">
            <article v-for="doc in filteredDocumentRequests" :key="doc.id" class="vp-req-row">
              <div class="vp-req-col">
                <strong>{{ doc.document_type?.name || '—' }}</strong>
                <small>Document Name</small>
              </div>
              <div class="vp-req-col">
                <strong>{{ doc.description || '--' }}</strong>
                <small>Description</small>
              </div>
              <div class="vp-req-col vp-req-col--date">
                <strong>{{ formatDateShort(doc.requested_date || doc.created_at) }}</strong>
                <small>Requested On</small>
              </div>
              <div class="vp-req-col vp-req-col--status">
                <span class="vp-status-text" :class="`is-${statusTone(doc.status)}`">{{ formatStatusLabel(doc.status) }}</span>
                <small>Status</small>
              </div>
              <div class="vp-req-col">
                <strong>{{ doc.rejection_reason || '--' }}</strong>
                <small>Rejection Reason</small>
              </div>
              <div class="vp-req-actions">
                <button type="button" class="vp-row-btn" title="View Details" @click="openDocumentDetail(doc)">
                  <iconify-icon icon="lucide:eye" />
                </button>
                <a
                  v-if="String(doc.status).toLowerCase() === 'approved' && doc.file_url"
                  :href="doc.file_url"
                  target="_blank"
                  rel="noopener"
                  class="vp-row-btn"
                  title="Download"
                >
                  <iconify-icon icon="lucide:download" />
                </a>
                <button v-if="String(doc.status).toLowerCase() === 'pending'" type="button" class="vp-row-btn" title="Edit" @click="openDocRequestModal(doc)">
                  <iconify-icon icon="lucide:pencil" />
                </button>
                <button type="button" class="vp-row-btn" title="Delete" @click="deleteMyDocumentRequest(doc)">
                  <iconify-icon icon="lucide:trash-2" />
                </button>
              </div>
            </article>
          </div>
        </div>

        <!-- My Assets -->
        <div v-if="activeTab === 'my-assets'" class="vp-panel vp-hub">
          <div class="vp-panel__head vp-hub__head">
            <div>
              <h3 class="vp-panel__title">My Assets</h3>
              <p class="vp-panel__subtitle">Assigned equipment and your open asset requests.</p>
            </div>
            <button type="button" class="vp-btn-primary vp-hub__cta" @click="openAssetRequestModal()">
              Request Asset
              <iconify-icon icon="lucide:plus" />
            </button>
          </div>

          <h4 class="vp-section-label">Assigned to me</h4>
          <div v-if="assetsLoading" class="vp-loading">Loading assets...</div>
          <div v-else-if="myAssignedAssets.length" class="vp-entity-grid">
            <article v-for="asset in myAssignedAssets" :key="asset.id" class="vp-entity-card">
              <div class="vp-entity-card__icon">
                <iconify-icon icon="lucide:laptop" />
              </div>
              <div class="vp-entity-card__body">
                <strong>{{ asset.name }}</strong>
                <span>{{ asset.asset_type?.name || 'Asset' }}</span>
                <span class="vp-entity-card__meta">SN {{ asset.serial_number || '—' }}</span>
              </div>
              <span class="vp-status-pill vp-status-assigned">{{ formatStatusLabel(asset.status) }}</span>
            </article>
          </div>
          <div v-else class="vp-empty vp-empty--compact">
            <div class="vp-empty__icon"><iconify-icon icon="lucide:briefcase" /></div>
            <h4>No assets assigned</h4>
            <p>When HR assigns equipment, it will appear here.</p>
          </div>

          <div class="vp-hub__toolbar vp-hub__toolbar--spaced">
            <h4 class="vp-section-label mb-0">Asset requests</h4>
            <label class="vp-search">
              <iconify-icon icon="lucide:search" />
              <input v-model="assetSearch" type="text" placeholder="Search asset item or description" />
            </label>
          </div>

          <div v-if="!assetsLoading && !filteredAssetRequests.length" class="vp-empty vp-empty--compact">
            <div class="vp-empty__icon"><iconify-icon icon="lucide:package-plus" /></div>
            <h4>{{ assetSearch ? 'No matching requests' : 'No asset requests yet' }}</h4>
            <p>{{ assetSearch ? 'Try a different search term.' : 'Need a laptop, phone, or other equipment? Submit a request.' }}</p>
          </div>
          <div v-else-if="!assetsLoading" class="vp-req-list">
            <article v-for="req in filteredAssetRequests" :key="req.id" class="vp-req-row">
              <div class="vp-req-col">
                <strong>{{ req.asset_item }}</strong>
                <small>Asset Item</small>
              </div>
              <div class="vp-req-col vp-req-col--qty">
                <strong>{{ req.qty }}</strong>
                <small>Qty</small>
              </div>
              <div class="vp-req-col">
                <strong>{{ req.description || '--' }}</strong>
                <small>Description</small>
              </div>
              <div class="vp-req-col vp-req-col--date">
                <strong>{{ formatDateShort(req.applied_at) }}</strong>
                <small>Requested On</small>
              </div>
              <div class="vp-req-col vp-req-col--status">
                <span class="vp-status-text" :class="`is-${statusTone(req.status)}`">{{ formatStatusLabel(req.status) }}</span>
                <small>Status</small>
              </div>
              <div class="vp-req-actions">
                <button v-if="String(req.status).toLowerCase() === 'pending'" type="button" class="vp-row-btn" title="Edit" @click="openAssetRequestModal(req)">
                  <iconify-icon icon="lucide:pencil" />
                </button>
                <button type="button" class="vp-row-btn" title="Delete" @click="deleteMyAssetRequest(req)">
                  <iconify-icon icon="lucide:trash-2" />
                </button>
              </div>
            </article>
          </div>
        </div>

        <!-- My Leave -->
        <div v-if="activeTab === 'my-leave'" class="vp-panel vp-hub">
          <div class="vp-panel__head vp-hub__head">
            <div>
              <h3 class="vp-panel__title">My Leave</h3>
              <p class="vp-panel__subtitle">Check your balance and apply for time off.</p>
            </div>
            <button type="button" class="vp-btn-primary vp-hub__cta" @click="openLeaveRequestModal()">
              Apply Leave
              <iconify-icon icon="lucide:plus" />
            </button>
          </div>

          <h4 class="vp-section-label">Leave balance</h4>
          <div v-if="leaveLoading" class="vp-loading">Loading leave data...</div>
          <div v-else-if="myLeaveBalance.length" class="vp-balance-grid">
            <article v-for="b in myLeaveBalance" :key="b.id" class="vp-balance-card">
              <div class="vp-balance-card__top">
                <strong>{{ b.leave_type?.name || 'Leave' }}</strong>
                <span>{{ b.remaining_days ?? 0 }}/{{ b.total_days ?? 0 }}</span>
              </div>
              <div class="vp-balance-bar">
                <div class="vp-balance-bar__fill" :style="{ width: leaveBalancePercent(b) + '%' }"></div>
              </div>
              <small>{{ b.remaining_days ?? 0 }} days remaining</small>
            </article>
          </div>
          <p v-else class="vp-muted-note">No leave balances available.</p>

          <div class="vp-hub__toolbar vp-hub__toolbar--spaced">
            <h4 class="vp-section-label mb-0">Leave requests</h4>
            <label class="vp-search">
              <iconify-icon icon="lucide:search" />
              <input v-model="leaveSearch" type="text" placeholder="Search leave type or status" />
            </label>
          </div>

          <div v-if="!leaveLoading && !filteredLeaveRequests.length" class="vp-empty vp-empty--compact">
            <div class="vp-empty__icon"><iconify-icon icon="lucide:calendar-off" /></div>
            <h4>{{ leaveSearch ? 'No matching requests' : 'No leave requests yet' }}</h4>
            <p>{{ leaveSearch ? 'Try a different search term.' : 'Apply for annual, sick, or other leave from here.' }}</p>
          </div>
          <div v-else-if="!leaveLoading" class="vp-req-list">
            <article v-for="lv in filteredLeaveRequests" :key="lv.id" class="vp-req-row">
              <div class="vp-req-col">
                <strong>{{ lv.leave_type?.name || '—' }}</strong>
                <small>Leave Type</small>
              </div>
              <div class="vp-req-col vp-req-col--date">
                <strong>{{ formatDateShort(lv.start_date) }}</strong>
                <small>Start Date</small>
              </div>
              <div class="vp-req-col vp-req-col--date">
                <strong>{{ formatDateShort(lv.end_date) }}</strong>
                <small>End Date</small>
              </div>
              <div class="vp-req-col vp-req-col--qty">
                <strong>{{ lv.days ?? '—' }}</strong>
                <small>Days</small>
              </div>
              <div class="vp-req-col vp-req-col--status">
                <span class="vp-status-text" :class="`is-${statusTone(lv.status)}`">{{ formatStatusLabel(lv.status) }}</span>
                <small>Status</small>
              </div>
              <div class="vp-req-actions">
                 <button type="button" class="vp-row-btn" title="View Details" @click="openLeaveDetail(lv)">
                    <iconify-icon icon="lucide:eye" />
                  </button>
                <button v-if="lv.status === 'pending_parent'" type="button" class="vp-row-btn" title="Cancel request" @click="cancelMyLeave(lv)">
                  <iconify-icon icon="lucide:x-circle" />
                </button>
              </div>
            </article>
          </div>
        </div>
        <!-- Team Leave Requests -->
        <div v-if="activeTab === 'team-leave'" class="vp-panel vp-hub">
          <div class="vp-panel__head vp-hub__head">
            <div>
              <h3 class="vp-panel__title">Team Leave Requests</h3>
              <p class="vp-panel__subtitle">Review and act on leave requests from your direct reports.</p>
            </div>
          </div>

          <div class="vp-stat-row">
            <div class="vp-stat-chip"><span>{{ teamLeaveStats.total }}</span> Total</div>
            <div class="vp-stat-chip is-pending"><span>{{ teamLeaveStats.pending }}</span> Pending</div>
            <div class="vp-stat-chip is-approved"><span>{{ teamLeaveStats.approved }}</span> Approved</div>
            <div class="vp-stat-chip is-rejected"><span>{{ teamLeaveStats.rejected }}</span> Rejected</div>
          </div>

          <div class="vp-hub__toolbar">
            <div class="vp-filter-chips">
              <button type="button" class="vp-chip" :class="{ 'is-active': teamLeaveStatusFilter === 'all' }" @click="teamLeaveStatusFilter = 'all'">All</button>
              <button type="button" class="vp-chip" :class="{ 'is-active': teamLeaveStatusFilter === 'pending_parent' }" @click="teamLeaveStatusFilter = 'pending_parent'">Pending</button>
              <button type="button" class="vp-chip" :class="{ 'is-active': teamLeaveStatusFilter === 'approved' }" @click="teamLeaveStatusFilter = 'approved'">Approved</button>
              <button type="button" class="vp-chip" :class="{ 'is-active': teamLeaveStatusFilter === 'rejected' }" @click="teamLeaveStatusFilter = 'rejected'">Rejected</button>
            </div>
            <label class="vp-search">
              <iconify-icon icon="lucide:search" />
              <input v-model="teamLeaveSearch" type="text" placeholder="Search employee or leave type" />
            </label>
          </div>

          <div v-if="teamLeaveLoading" class="vp-loading">Loading team requests...</div>
          <div v-else-if="!filteredTeamLeaveRequests.length" class="vp-empty">
            <div class="vp-empty__icon"><iconify-icon icon="lucide:calendar-check" /></div>
            <h4>{{ teamLeaveSearch ? 'No matching requests' : 'No pending requests' }}</h4>
            <p>{{ teamLeaveSearch ? 'Try a different search term.' : 'Your team is all caught up.' }}</p>
          </div>
          <div v-else class="vp-req-list">
              <article v-for="lv in filteredTeamLeaveRequests" :key="lv.id" class="vp-req-row">
                <div class="vp-req-col">
                  <strong>{{ lv.user?.name || '—' }}</strong>
                  <small>Employee</small>
                </div>
                <div class="vp-req-col">
                  <strong>{{ lv.leave_type?.name || '—' }}</strong>
                  <small>Leave Type</small>
                </div>
                <div class="vp-req-col vp-req-col--date">
                  <strong>{{ formatDateShort(lv.start_date) }}</strong>
                  <small>Start Date</small>
                </div>
                <div class="vp-req-col vp-req-col--date">
                  <strong>{{ formatDateShort(lv.end_date) }}</strong>
                  <small>End Date</small>
                </div>
                <div class="vp-req-col vp-req-col--qty">
                  <strong>{{ lv.days ?? '—' }}</strong>
                  <small>Days</small>
                </div>
                <div class="vp-req-col">
                  <strong>{{ lv.reason || '--' }}</strong>
                  <small>Reason</small>
                </div>
                <div class="vp-req-col vp-req-col--status">
                  <span class="vp-status-text" :class="`is-${statusTone(lv.status)}`">{{ formatStatusLabel(lv.status) }}</span>
                  <small v-if="lv.status === 'rejected' && lv.rejection_reason">{{ lv.rejection_reason }}</small>
                  <small v-else>Status</small>
                </div>
                <div class="vp-req-actions">
                  <button type="button" class="vp-row-btn" title="View Details" @click="openLeaveDetail(lv)">
                    <iconify-icon icon="lucide:eye" />
                  </button>
                  <template v-if="lv.status === 'pending_parent'">
                    <button type="button" class="vp-row-btn vp-row-btn--approve" title="Approve" :disabled="teamLeaveActingId === lv.id" @click="approveTeamLeave(lv)">
                      <iconify-icon icon="lucide:check" />
                    </button>
                    <button type="button" class="vp-row-btn vp-row-btn--reject" title="Reject" :disabled="teamLeaveActingId === lv.id" @click="rejectTeamLeave(lv)">
                      <iconify-icon icon="lucide:x" />
                    </button>
                  </template>
                  <span v-else class="vp-muted-note" style="font-size:11px;">No action needed</span>
                </div>
              </article>
            </div>
        </div>
        <!-- Announcements -->
        <div v-if="activeTab === 'announcements'" class="vp-panel vp-hub">
          <div class="vp-panel__head vp-hub__head">
            <div>
              <h3 class="vp-panel__title">Announcements</h3>
              <p class="vp-panel__subtitle">Company and department updates.</p>
            </div>
            <button
              v-if="unreadAnnouncementsCount > 0"
              type="button"
              class="vp-btn-ghost vp-hub__cta"
              @click="markAllAnnouncementsRead"
            >
              Mark All as Read
            </button>
          </div>

          <div class="vp-hub__toolbar">
            <label class="vp-search">
              <iconify-icon icon="lucide:search" />
              <input v-model="announcementSearch" type="text" placeholder="Search announcements" />
            </label>
          </div>

          <div v-if="announcementsLoading" class="vp-loading">Loading announcements...</div>
          <div v-else-if="!filteredAnnouncements.length" class="vp-empty">
            <div class="vp-empty__icon"><iconify-icon icon="lucide:megaphone" /></div>
            <h4>{{ announcementSearch ? 'No matching announcements' : 'No announcements yet' }}</h4>
            <p>{{ announcementSearch ? 'Try a different search term.' : 'Check back later for company updates.' }}</p>
          </div>
          <div v-else class="vp-announcement-list">
            <article
              v-for="item in filteredAnnouncements"
              :key="item.id"
              class="vp-announcement-card"
              :class="{ 'is-unread': !item.is_viewed }"
              @click="openAnnouncement(item)"
            >
              <div class="vp-announcement-card__top">
                <span v-if="!item.is_viewed" class="vp-unread-dot" aria-hidden="true"></span>
                <strong>{{ item.title }}</strong>
                <span class="vp-announcement-card__date">{{ formatDateShort(item.start_date || item.created_at) }}</span>
              </div>
              <p class="vp-announcement-card__desc">{{ item.description || 'No description provided.' }}</p>
              <div class="vp-announcement-card__meta">
                <span v-if="item.branch?.name"><iconify-icon icon="lucide:map-pin" /> {{ item.branch.name }}</span>
                <span v-if="item.department?.name"><iconify-icon icon="lucide:building-2" /> {{ item.department.name }}</span>
                <span v-if="item.creator?.name"><iconify-icon icon="lucide:user" /> {{ item.creator.name }}</span>
              </div>
            </article>
          </div>
        </div>


      </main>
    </div>
    <!-- Document Request Modal -->
    <div v-if="showDocRequestModal" class="vp-modal-overlay" @click.self="showDocRequestModal = false">
      <div class="vp-modal vp-modal--form">
        <div class="vp-modal__head">
          <div class="vp-modal__head-copy">
            <span class="vp-modal__badge"><iconify-icon icon="lucide:file-plus-2" /></span>
            <div>
              <h4>{{ editingDocRequestId ? 'Edit Document Request' : 'Request New Document' }}</h4>
              <p>Choose the document type and add any details HR should know.</p>
            </div>
          </div>
          <button type="button" class="vp-modal__close" @click="showDocRequestModal = false"><iconify-icon icon="lucide:x" /></button>
        </div>
        <div class="vp-modal__body">
          <div class="vp-field">
            <label class="vp-field__label">Document Type <em class="required">*</em></label>
            <SearchableSelect
              v-model="docRequestForm.document_type_id"
              :options="documentTypeOptions"
              placeholder="Select document type"
            />
          </div>
          <div class="vp-field">
            <label class="vp-field__label">Description</label>
            <textarea
              v-model="docRequestForm.description"
              class="vp-field__input vp-field__textarea"
              rows="5"
              maxlength="500"
              placeholder="e.g. Needed for visa renewal, embassy, or bank application"
            ></textarea>
            <span class="vp-field__hint">{{ (docRequestForm.description || '').length }}/500 · Optional, but helps HR process faster</span>
          </div>
        </div>
        <div class="vp-modal__footer">
          <button type="button" class="vp-btn-ghost" @click="showDocRequestModal = false">Cancel</button>
          <button type="button" class="vp-btn-primary" :disabled="docRequestSaving" @click="submitDocRequest">
            {{ docRequestSaving ? 'Submitting...' : (editingDocRequestId ? 'Save Changes' : 'Submit Request') }}
          </button>
        </div>
      </div>
    </div>
    <!-- Document Detail Modal -->
<div v-if="showDocumentDetailModal && viewingDocument" class="vp-modal-overlay" @click.self="showDocumentDetailModal = false">
  <div class="vp-modal vp-modal--form">
    <div class="vp-modal__head">
      <div class="vp-modal__head-copy">
        <span class="vp-modal__badge"><iconify-icon icon="lucide:file-text" /></span>
        <div>
          <h4>{{ viewingDocument.document_type?.name || 'Document Request' }}</h4>
          <p>Requested {{ formatDate(viewingDocument.requested_date || viewingDocument.created_at) }}</p>
        </div>
      </div>
      <button type="button" class="vp-modal__close" @click="showDocumentDetailModal = false"><iconify-icon icon="lucide:x" /></button>
    </div>
    <div class="vp-modal__body">
      <div class="vp-detail-grid">
        <p><span>Document Type</span><strong>{{ viewingDocument.document_type?.name || '—' }}</strong></p>
        <p><span>Status</span><strong class="vp-status-text" :class="`is-${statusTone(viewingDocument.status)}`">{{ formatStatusLabel(viewingDocument.status) }}</strong></p>
        <p><span>Requested On</span><strong>{{ formatDateShort(viewingDocument.requested_date || viewingDocument.created_at) }}</strong></p>
        <p v-if="viewingDocument.hr_user?.name"><span>Processed By</span><strong>{{ viewingDocument.hr_user.name }}</strong></p>
      </div>
      <div class="vp-field" style="margin-top: 14px;">
        <label class="vp-field__label">Description</label>
        <p class="vp-detail-note">{{ viewingDocument.description || '--' }}</p>
      </div>
      <div v-if="String(viewingDocument.status).toLowerCase() === 'rejected'" class="vp-field">
        <label class="vp-field__label">Rejection Reason</label>
        <p class="vp-detail-note vp-detail-note--danger">{{ viewingDocument.rejection_reason || '--' }}</p>
      </div>
      <a
        v-if="String(viewingDocument.status).toLowerCase() === 'approved' && viewingDocument.file_url"
        :href="viewingDocument.file_url"
        target="_blank"
        rel="noopener"
        class="vp-btn-primary"
        style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; margin-top: 10px;"
      >
        <iconify-icon icon="lucide:download" /> Download Document
      </a>
    </div>
    <div class="vp-modal__footer">
      <button type="button" class="vp-btn-primary" @click="showDocumentDetailModal = false">Close</button>
    </div>
  </div>
</div>

    <!-- Leave Detail Modal -->
    <div v-if="showLeaveDetailModal && viewingLeave" class="vp-modal-overlay" @click.self="showLeaveDetailModal = false">
      <div class="vp-modal vp-modal--form">
        <div class="vp-modal__head">
          <div class="vp-modal__head-copy">
            <span class="vp-modal__badge"><iconify-icon icon="lucide:calendar-days" /></span>
            <div>
              <h4>{{ viewingLeave.leave_type?.name || 'Leave Request' }}</h4>
              <p v-if="viewingLeave.user?.name">{{ viewingLeave.user.name }}</p>
            </div>
          </div>
          <button type="button" class="vp-modal__close" @click="showLeaveDetailModal = false"><iconify-icon icon="lucide:x" /></button>
        </div>
        <div class="vp-modal__body">
          <div class="vp-detail-grid">
            <p><span>Leave Type</span><strong>{{ viewingLeave.leave_type?.name || '—' }}</strong></p>
            <p><span>Status</span><strong class="vp-status-text" :class="`is-${statusTone(viewingLeave.status)}`">{{ formatStatusLabel(viewingLeave.status) }}</strong></p>
            <p><span>Start Date</span><strong>{{ formatDateShort(viewingLeave.start_date) }}</strong></p>
            <p><span>End Date</span><strong>{{ formatDateShort(viewingLeave.end_date) }}</strong></p>
            <p><span>Days</span><strong>{{ viewingLeave.days ?? '—' }}</strong></p>
            <p v-if="viewingLeave.is_half_day"><span>Half Day</span><strong>{{ formatStatusLabel(viewingLeave.half_day_type) }}</strong></p>
            <p v-if="viewingLeave.parent?.name"><span>Manager</span><strong>{{ viewingLeave.parent.name }}</strong></p>
            <p v-if="viewingLeave.hr?.name"><span>HR Reviewer</span><strong>{{ viewingLeave.hr.name }}</strong></p>
          </div>
          <div class="vp-field" style="margin-top: 14px;">
            <label class="vp-field__label">Reason</label>
            <p class="vp-detail-note">{{ viewingLeave.reason || '--' }}</p>
          </div>
          <div v-if="String(viewingLeave.status).toLowerCase() === 'rejected'" class="vp-field">
            <label class="vp-field__label">Rejection Reason</label>
            <p class="vp-detail-note vp-detail-note--danger">{{ viewingLeave.rejection_reason || '--' }}</p>
          </div>
          <a
            v-if="viewingLeave.attachment_url"
            :href="viewingLeave.attachment_url"
            target="_blank"
            rel="noopener"
            class="vp-btn-primary"
            style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; margin-top: 10px;"
          >
            <iconify-icon icon="lucide:paperclip" /> View Attachment
          </a>
        </div>
        <div class="vp-modal__footer">
          <template v-if="viewingLeave.status === 'pending_parent' && (isTeamLead || isSuperAdmin) && viewingLeave.user_id !== user.id">
            <button type="button" class="vp-btn-ghost" style="color:#dc2626;" :disabled="teamLeaveActingId === viewingLeave.id" @click="rejectTeamLeave(viewingLeave); showLeaveDetailModal = false">Reject</button>
            <button type="button" class="vp-btn-primary" style="background:#16a34a;" :disabled="teamLeaveActingId === viewingLeave.id" @click="approveTeamLeave(viewingLeave); showLeaveDetailModal = false">Approve</button>
          </template>
          <button v-else type="button" class="vp-btn-primary" @click="showLeaveDetailModal = false">Close</button>
        </div>
      </div>
    </div>
    <!-- Asset Request Modal -->
    <div v-if="showAssetRequestModal" class="vp-modal-overlay" @click.self="showAssetRequestModal = false">
      <div class="vp-modal vp-modal--form vp-modal--wide">
        <div class="vp-modal__head">
          <div class="vp-modal__head-copy">
            <span class="vp-modal__badge"><iconify-icon icon="lucide:briefcase" /></span>
            <div>
              <h4>{{ editingAssetRequestId ? 'Edit Asset Request' : 'Request New Asset' }}</h4>
              <p>Tell us what you need and where it should be assigned.</p>
            </div>
          </div>
          <button type="button" class="vp-modal__close" @click="showAssetRequestModal = false"><iconify-icon icon="lucide:x" /></button>
        </div>
        <div class="vp-modal__body">
          <div class="vp-modal__grid">
            <div class="vp-field vp-field--full">
              <label class="vp-field__label">Asset Item <em class="required">*</em></label>
              <input v-model="assetRequestForm.asset_item" type="text" class="vp-field__input" placeholder="e.g. MacBook Pro, iPhone, Monitor" />
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Company Name <em class="required">*</em></label>
              <input v-model="assetRequestForm.company_name" type="text" class="vp-field__input" placeholder="Enter company name" />
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Quantity <em class="required">*</em></label>
              <input v-model.number="assetRequestForm.qty" type="number" min="1" class="vp-field__input" />
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Branch <em class="required">*</em></label>
              <SearchableSelect
                v-model="assetRequestForm.branch_id"
                :options="branchSelectOptions"
                placeholder="Select branch"
              />
            </div>
            <div class="vp-field">
              <label class="vp-field__label">Department <em class="required">*</em></label>
              <SearchableSelect
                v-model="assetRequestForm.department_id"
                :options="assetDepartmentOptions"
                placeholder="Select department"
              />
            </div>
            <div class="vp-field vp-field--full">
              <label class="vp-field__label">Description</label>
              <textarea
                v-model="assetRequestForm.description"
                class="vp-field__input vp-field__textarea"
                rows="4"
                maxlength="500"
                placeholder="Why do you need this asset? Any specs or preferences?"
              ></textarea>
              <span class="vp-field__hint">{{ (assetRequestForm.description || '').length }}/500</span>
            </div>
          </div>
        </div>
        <div class="vp-modal__footer">
          <button type="button" class="vp-btn-ghost" @click="showAssetRequestModal = false">Cancel</button>
          <button type="button" class="vp-btn-primary" :disabled="assetRequestSaving" @click="submitAssetRequest">
            {{ assetRequestSaving ? 'Submitting...' : (editingAssetRequestId ? 'Save Changes' : 'Submit Request') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Leave Request Modal -->
    <div v-if="showLeaveRequestModal" class="vp-modal-overlay" @click.self="showLeaveRequestModal = false">
      <div class="vp-modal vp-modal--form">
        <div class="vp-modal__head">
          <div class="vp-modal__head-copy">
            <span class="vp-modal__badge"><iconify-icon icon="lucide:calendar-plus" /></span>
            <div>
              <h4>Apply Leave</h4>
              <p>Select dates and a leave type. Your manager will review the request.</p>
            </div>
          </div>
          <button type="button" class="vp-modal__close" @click="showLeaveRequestModal = false"><iconify-icon icon="lucide:x" /></button>
        </div>
        <div class="vp-modal__body">
          <div class="vp-field">
            <label class="vp-field__label">Leave Type <em class="required">*</em></label>
            <SearchableSelect
              v-model="leaveRequestForm.leave_type_id"
              :options="leaveTypeOptions"
              placeholder="Select leave type"
            />
          </div>
          <div class="vp-modal__grid">
            <div class="vp-field">
              <label class="vp-field__label">Start Date <em class="required">*</em></label>
              <input v-model="leaveRequestForm.start_date" type="date" class="vp-field__input" />
            </div>
            <div class="vp-field">
              <label class="vp-field__label">End Date <em class="required">*</em></label>
              <input v-model="leaveRequestForm.end_date" type="date" class="vp-field__input" />
            </div>
          </div>
          <label class="vp-toggle">
            <input type="checkbox" v-model="leaveRequestForm.is_half_day" />
            <span class="vp-toggle__track"></span>
            <span class="vp-toggle__copy">
              <strong>Half Day</strong>
              <small>Use this for a morning or afternoon only</small>
            </span>
          </label>
          <div class="vp-field" v-if="leaveRequestForm.is_half_day">
            <label class="vp-field__label">Half Day Type</label>
            <SearchableSelect
              v-model="leaveRequestForm.half_day_type"
              :options="halfDayTypeOptions"
              placeholder="Select period"
              :clearable="false"
            />
          </div>
          <div class="vp-field">
            <label class="vp-field__label">Reason</label>
            <textarea
              v-model="leaveRequestForm.reason"
              class="vp-field__input vp-field__textarea"
              rows="4"
              maxlength="500"
              placeholder="Add a short reason for your leave"
            ></textarea>
            <span class="vp-field__hint">{{ (leaveRequestForm.reason || '').length }}/500</span>
          </div>
        </div>
        <div class="vp-modal__footer">
          <button type="button" class="vp-btn-ghost" @click="showLeaveRequestModal = false">Cancel</button>
          <button type="button" class="vp-btn-primary" :disabled="leaveRequestSaving" @click="submitLeaveRequest">
            {{ leaveRequestSaving ? 'Submitting...' : 'Submit Request' }}
          </button>
        </div>
      </div>
    </div>
    <!-- Announcement Detail Modal -->
    <div v-if="showAnnouncementModal && viewingAnnouncement" class="vp-modal-overlay" @click.self="showAnnouncementModal = false">
      <div class="vp-modal vp-modal--form">
        <div class="vp-modal__head">
          <div class="vp-modal__head-copy">
            <span class="vp-modal__badge"><iconify-icon icon="lucide:megaphone" /></span>
            <div>
              <h4>{{ viewingAnnouncement.title }}</h4>
              <p>{{ formatDate(viewingAnnouncement.start_date || viewingAnnouncement.created_at) }}</p>
            </div>
          </div>
          <button type="button" class="vp-modal__close" @click="showAnnouncementModal = false"><iconify-icon icon="lucide:x" /></button>
        </div>
        <div class="vp-modal__body">
          <p style="white-space: pre-wrap; line-height: 1.6;">{{ viewingAnnouncement.description || 'No description provided.' }}</p>
          <div class="vp-announcement-card__meta" style="margin-top: 12px;">
            <span v-if="viewingAnnouncement.branch?.name"><iconify-icon icon="lucide:map-pin" /> {{ viewingAnnouncement.branch.name }}</span>
            <span v-if="viewingAnnouncement.department?.name"><iconify-icon icon="lucide:building-2" /> {{ viewingAnnouncement.department.name }}</span>
            <span v-if="viewingAnnouncement.end_date"><iconify-icon icon="lucide:calendar-x" /> Ends {{ formatDateShort(viewingAnnouncement.end_date) }}</span>
          </div>
        </div>
        <div class="vp-modal__footer">
          <button type="button" class="vp-btn-primary" @click="showAnnouncementModal = false">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted ,onBeforeUnmount, reactive, getCurrentInstance, computed, watch } from 'vue';
import api from '@/plugins/axios';
import defaultAvatar from "@/assets/images/user-grid/user-grid-img14.png";
import user1 from "@/assets/images/user-grid/user-grid-img13.png";
import UserAttendanceCarousel from '@/components/Users/UserAttendanceCarousel.vue';
import SearchableSelect from '@/components/ui/SearchableSelect.vue';
import { aiSalesIntelligenceApi } from '@/services/aiSalesIntelligenceApi';
import Swal from 'sweetalert2';
export default {
  name: 'UserProfile',
  components: {
    UserAttendanceCarousel,
    SearchableSelect,
  },
  setup() {
    const instance = getCurrentInstance();
    const user = ref({});
    const activeTab = ref('edit-profile');
    const profileImage = ref(user1);
    const defaultAvatarImg = ref(defaultAvatar);

    const readStoredUser = () => {
      try {
        const raw = localStorage.getItem('user');
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : null;
      } catch (_) {
        return null;
      }
    };
    
    const vacationLoading = ref(false);
    const attendanceSettingsLoading = ref(false);
    const attendanceSettingsSaving = ref(false);
    const attendanceSettings = reactive({
      day_of_week: 6,
      start_time: '09:00',
      end_time: '10:00',
      department_ids: [],
    });
    const attendanceStatus = reactive({
      is_active_day: false,
      is_department_active: true,
      is_within_time_window: false,
      already_checked_in: false,
      status: 'Closed',
      window_label: 'Not configured',
      check_in_at:'',
      today_code: '',
    });
    const departmentOptions = ref([]);
    const checkinSubmitting = ref(false);
    const checkinCode = ref('');
    const dayOptions = [
      { value: 0, label: 'Sunday' },
      { value: 1, label: 'Monday' },
      { value: 2, label: 'Tuesday' },
      { value: 3, label: 'Wednesday' },
      { value: 4, label: 'Thursday' },
      { value: 5, label: 'Friday' },
      { value: 6, label: 'Saturday' },
    ];
    const agentsList = ref([]);
    const vacationData = reactive({
      active: false,
      delegate_id: '',
      last_updated: ''
    });
    
    const loadAgents = async () => {
      try {
        const agentsResponse = await api.get('/listings/agents/', {
          params: {
            role: 'sales',
            active: true
          }
        });

        if (agentsResponse.data.status || agentsResponse.data.success) {
          agentsList.value = agentsResponse.data.data || [];
        }

      } catch (error) {
        console.error('Error loading agents:', error);
      }
    };

    const loadVacationData = async () => {
      vacationLoading.value = true;
      try {
        const vacationResponse = await api.get('/listings/agent/vacation-mode');

        if (vacationResponse.data.status || vacationResponse.data.success) {
          const data = vacationResponse.data.data || {};
          vacationData.active = data.on_vacation || false;
          vacationData.delegate_id = data.delegate_agent_id || '';
          vacationData.last_updated = data.updated_at || '';
        }

        await loadAgents();

      } catch (error) {
        console.error('Error loading vacation data:', error);
        showNotification('Failed to load vacation data', 'error');
      } finally {
        vacationLoading.value = false;
      }
    };

    const saveVacationMode = async () => {
      vacationLoading.value = true;
      try {
        const response = await api.post('/listings/agent/vacation', {
          active: vacationData.active,
          delegate_id: vacationData.active ? vacationData.delegate_id : null
        });

        if (response.data.status || response.data.success) {
          showNotification('Vacation mode updated successfully!', 'success');
          if (response.data.data) {
            Object.assign(vacationData, response.data.data);
            vacationData.active = response.data.data.on_vacation || false;
            vacationData.delegate_id = response.data.data.delegate_agent_id || '';
          }
        } else {
          showNotification(response.data.message || 'Failed to update vacation mode', 'error');
        }
      } catch (error) {
        console.error('Error saving vacation mode:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to update vacation mode', 'error');
        } else if (error.response?.data?.message) {
          showNotification(error.response.data.message, 'error');
        } else {
          showNotification('Failed to update vacation mode', 'error');
        }
      } finally {
        vacationLoading.value = false;
      }
    };
    
    const formatDate = (dateString) => {
      if (!dateString) return 'N/A';
      const datePart = String(dateString).slice(0, 10);
      const [year, month, day] = datePart.split('-').map(Number);
      if (!year || !month || !day) return String(dateString);
      const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      return `${months[month - 1]} ${day}, ${year}`;
    };

    const formatDateShort = (dateString) => {
        if (!dateString) return '—';
        const datePart = String(dateString).slice(0, 10); // "2026-08-17" — grab YYYY-MM-DD before any timezone conversion happens
        const [year, month, day] = datePart.split('-').map(Number);
        if (!year || !month || !day) return String(dateString);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return `${String(day).padStart(2, '0')} ${months[month - 1]} ${year}`;
      };

    const formatStatusLabel = (status) => {
      if (!status) return '—';
      return String(status)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
    };

    const statusTone = (status) => {
      const value = String(status || '').toLowerCase();
      if (value.includes('approv') || value === 'active') return 'approved';
      if (value.includes('reject') || value.includes('cancel')) return 'rejected';
      if (value.includes('assign')) return 'assigned';
      return 'pending';
    };

    const leaveBalancePercent = (balance) => {
      const total = Number(balance?.total_days) || 0;
      const remaining = Number(balance?.remaining_days) || 0;
      if (total <= 0) return 0;
      return Math.max(0, Math.min(100, Math.round((remaining / total) * 100)));
    };
    
    const currentDelegate = computed(() => {
      if (!vacationData.delegate_id) return null;
      return agentsList.value.find(agent => agent.id == vacationData.delegate_id);
    });
    const isSuperAdmin = computed(() => {
      const roleName = String(user.value?.role_name || '').toLowerCase();
      if (roleName === 'super_admin' || roleName === 'super admin') return true;
      const roles = Array.isArray(user.value?.roles) ? user.value.roles : [];
      return roles.some((role) => {
        const name = String(role?.name || role || '').toLowerCase();
        return name === 'super_admin' || name === 'super admin';
      });
    });
    const isTeamLead = computed(() => {
        const roleName = String(user.value?.role_name || '').toLowerCase();
        if (['team_lead', 'team lead', 'manager'].includes(roleName)) return true;
        const roles = Array.isArray(user.value?.roles) ? user.value.roles : [];
        return roles.some((role) => {
          const name = String(role?.name || role || '').toLowerCase();
          return ['team_lead', 'team lead', 'manager'].includes(name);
        });
      });
    const isSalesProfile = computed(() => {
      return !!user.value?.id;
    });
    const canViewTeamPerformance = computed(() => {
      const permissions = Array.isArray(user.value?.permissions) ? user.value.permissions : [];
      return permissions.includes('view-team-performance');
    });
    const userInitials = computed(() => {
      const name = String(user.value?.name || 'U').trim();
      const parts = name.split(/\s+/).filter(Boolean);
      if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
      return name.slice(0, 2).toUpperCase();
    });
    const displayAvatar = computed(() => {
      if (user.value?.avatar) return user.value.avatar;
      if (profileImage.value && profileImage.value !== user1) return profileImage.value;
      return null;
    });
    const isOnline = computed(() => {
      const raw = user.value?.last_login_at || user.value?.last_active;
      if (!raw) return false;
      const lastLogin = new Date(raw);
      if (Number.isNaN(lastLogin.getTime())) return false;
      const diffMinutes = (Date.now() - lastLogin.getTime()) / 60000;
      return diffMinutes <= 15;
    });
    const lastActiveText = computed(() => {
      const raw = user.value?.last_login_at || user.value?.last_active;
      if (!raw) return '—';
      const d = new Date(raw);
      if (Number.isNaN(d.getTime())) return '—';
      const diffMins = Math.floor((Date.now() - d.getTime()) / 60000);
      if (diffMins < 1) return 'Just now';
      if (diffMins < 60) return `${diffMins} min ago`;
      const diffHours = Math.floor(diffMins / 60);
      if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
      const diffDays = Math.floor(diffHours / 24);
      return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
    });
    const statusLabel = computed(() => {
      const raw = String(user.value?.status || '').trim();
      if (raw) return raw.charAt(0).toUpperCase() + raw.slice(1);
      return user.value?.can_login ? 'Active' : 'Inactive';
    });
    const isCheckinCodeComplete = computed(() => String(checkinCode.value || '').trim().length === 4);
    const selectedDepartmentLabels = computed(() => {
      const selectedIds = attendanceSettings.department_ids.map((id) => Number(id));
      return departmentOptions.value
        .filter((dept) => selectedIds.includes(Number(dept.value)))
        .map((dept) => dept.label);
    });
    const checkinBadgeClass = computed(() => {
      if (attendanceStatus.status === 'Checked In') return 'bg-success text-white';
      if (attendanceStatus.status === 'Not Checked In') return 'bg-warning text-dark';
      return 'bg-secondary text-white';
    });
    const currentMonthRange = () => {
      const now = new Date();
      const toStr = (d) => d.toISOString().slice(0, 10);
      const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
      return { from: toStr(firstOfMonth), to: toStr(now) };
    };
    const defaultSalesRange = currentMonthRange();
    const salesPerformanceLoading = ref(false);
    const salesPerformanceError = ref('');
    const salesPerformanceDeals = ref([]);
    const salesPerformanceSummary = reactive({
      converted_count: 0,
      total_amount: 0,
      total_commission: 0,
      total_agent_commission: 0,
      total_company_commission: 0,
      avg_lead_score: null,
    });
    const salesPerformanceFilters = reactive({
      from_date: defaultSalesRange.from,
      to_date: defaultSalesRange.to,
    });
    const formatCurrency = (value, currency = 'AED') => {
      if (value === null || value === undefined || value === '') return '—';
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'AED',
        maximumFractionDigits: 0,
      }).format(Number(value) || 0);
    };
    const scoreClass = (score) => {
      if (score === null || score === undefined) return '';
      if (Number(score) >= 70) return 'is-hot';
      if (Number(score) >= 40) return 'is-warm';
      return 'is-cold';
    };
    const loadSalesPerformance = async () => {
      if (!user.value?.id) return;
      salesPerformanceLoading.value = true;
      salesPerformanceError.value = '';
      try {
        const { data } = await api.get('/leads/reports/agent-performance', {
          params: {
            from_date: salesPerformanceFilters.from_date,
            to_date: salesPerformanceFilters.to_date,
            agent_id: user.value.id,
            status: 'completed',
          },
        });
        const agentData = Array.isArray(data?.data) ? data.data[0] : null;
        salesPerformanceDeals.value = Array.isArray(agentData?.deals) ? agentData.deals : [];
        salesPerformanceSummary.converted_count = Number(agentData?.converted_count) || 0;
        salesPerformanceSummary.total_amount = Number(agentData?.total_amount) || 0;
        salesPerformanceSummary.total_commission = Number(agentData?.total_commission) || 0;
        salesPerformanceSummary.total_agent_commission = Number(agentData?.total_agent_commission) || 0;
        salesPerformanceSummary.total_company_commission = Number(agentData?.total_company_commission) || 0;
        salesPerformanceSummary.avg_lead_score = agentData?.avg_lead_score ?? null;
      } catch (error) {
        console.error('Error loading sales performance:', error);
        salesPerformanceDeals.value = [];
        salesPerformanceSummary.converted_count = 0;
        salesPerformanceSummary.total_amount = 0;
        salesPerformanceSummary.total_commission = 0;
        salesPerformanceSummary.total_agent_commission = 0;
        salesPerformanceSummary.total_company_commission = 0;
        salesPerformanceSummary.avg_lead_score = null;
        salesPerformanceError.value = error?.response?.data?.message || 'Failed to load sales performance.';
      } finally {
        salesPerformanceLoading.value = false;
      }
    };

    const aiIntelLoading = ref(false);
    const aiIntelRecalculating = ref(false);
    const aiIntelError = ref('');
    const aiIntelAgent = ref(null);
    const aiIntelObservations = ref([]);

    const AI_SUBSCORE_LABELS = {
      behavior: 'Behavior',
      pipeline: 'Pipeline',
      response: 'Response',
      followup: 'Follow-up',
      qualification: 'Qualification',
      communication: 'Communication',
    };
    const AI_SUBSCORE_COLORS = {
      behavior: '#f59e0b',
      pipeline: '#6366f1',
      response: '#8b5cf6',
      followup: '#a855f7',
      qualification: '#10b981',
      communication: '#06b6d4',
    };

    const aiOverallScore = computed(() => Math.round(Number(aiIntelAgent.value?.overall_ai_score) || 0));

    const aiSubscoreItems = computed(() => {
      const scores = aiIntelAgent.value?.scores || {};
      return Object.entries(AI_SUBSCORE_LABELS)
        .map(([key, label]) => ({
          key,
          label,
          value: Math.round(Number(scores[key]) || 0),
          color: AI_SUBSCORE_COLORS[key],
        }))
        .filter((s) => s.value > 0);
    });

    const aiScoreClass = (score) => {
      const value = Number(score) || 0;
      if (value >= 85) return 'is-excellent';
      if (value >= 70) return 'is-good';
      if (value >= 50) return 'is-warn';
      return 'is-critical';
    };

    const loadAiIntelligence = async () => {
      if (!user.value?.id) return;
      aiIntelLoading.value = true;
      aiIntelError.value = '';
      try {
        const data = await aiSalesIntelligenceApi.showAgent(user.value.id);
        aiIntelAgent.value = data?.agent || null;
        aiIntelObservations.value = Array.isArray(data?.observations) ? data.observations : [];
      } catch (error) {
        aiIntelAgent.value = null;
        aiIntelObservations.value = [];
        aiIntelError.value = error?.status === 404
          ? 'No AI intelligence data yet for this agent. Click Recalculate to generate it.'
          : (error?.message || 'Failed to load AI intelligence data.');
      } finally {
        aiIntelLoading.value = false;
      }
    };

    const recalculateAiIntelligence = async () => {
      if (!user.value?.id) return;
      aiIntelRecalculating.value = true;
      try {
        await aiSalesIntelligenceApi.recalculate({ user_id: user.value.id });
        await loadAiIntelligence();
      } catch (error) {
        showNotification(error?.message || 'Failed to recalculate AI intelligence', 'error');
      } finally {
        aiIntelRecalculating.value = false;
      }
    };

    const teamPerformanceLoading = ref(false);
    const teamPerformanceError = ref('');
    const teamPerformanceAgents = ref([]);
    const teamPerformanceSummary = reactive({
      agents_count: 0,
      deals_count: 0,
      total_amount: 0,
      total_commission: 0,
      total_agent_commission: 0,
      total_company_commission: 0,
    });
    const teamPerformanceFilters = reactive({
      from_date: defaultSalesRange.from,
      to_date: defaultSalesRange.to,
    });

    const loadTeamPerformance = async () => {
      if (!canViewTeamPerformance.value) return;
      teamPerformanceLoading.value = true;
      teamPerformanceError.value = '';
      try {
        const { data } = await api.get('/leads/reports/team-performance', {
          params: {
            from_date: teamPerformanceFilters.from_date,
            to_date: teamPerformanceFilters.to_date,
          },
        });
        teamPerformanceAgents.value = Array.isArray(data?.data) ? data.data : [];
        Object.assign(teamPerformanceSummary, data?.summary || {});
      } catch (error) {
        console.error('Error loading team performance:', error);
        teamPerformanceAgents.value = [];
        Object.assign(teamPerformanceSummary, {
          agents_count: 0,
          deals_count: 0,
          total_amount: 0,
          total_commission: 0,
          total_agent_commission: 0,
          total_company_commission: 0,
        });
        teamPerformanceError.value = error?.response?.data?.message || 'Failed to load team performance.';
      } finally {
        teamPerformanceLoading.value = false;
      }
    };

    const currentPasswordVisible = ref(false);
    const newPasswordVisible = ref(false);
    const confirmPasswordVisible = ref(false);
    
    const loading = ref(false);
    const passwordLoading = ref(false);
    
    const formData = reactive({
      name: '',
      email: '',
      phone: '',
    });
    
    const passwordData = reactive({
      current_password: '',
      new_password: '',
      confirm_password: ''
    });
    
    const applyUserToForm = (profile) => {
      if (!profile || typeof profile !== 'object') return;
      const data = profile.data && typeof profile.data === 'object' && !profile.id
        ? profile.data
        : profile;

      user.value = data;
      formData.name = data.name || '';
      formData.email = data.email || '';
      formData.phone = data.phone || '';

      if (data.avatar) {
        profileImage.value = data.avatar;
      }

      try {
        localStorage.setItem('user', JSON.stringify(data));
      } catch (_) {
        // ignore storage failures
      }
    };

    const loadUserData = async () => {
      const cached = readStoredUser();
      if (cached?.id || cached?.email || cached?.name) {
        applyUserToForm(cached);
      }

      try {
        const response = await api.get('/profile');
        const payload = response.data || {};
        const ok = payload.success !== false && payload.status !== false;
        const profile = payload.data ?? payload;

        if (ok && profile && (profile.id || profile.email || profile.name)) {
          applyUserToForm(profile);
        } else if (!user.value?.id) {
          showNotification(payload.message || 'Failed to load profile data', 'error');
        }
      } catch (error) {
        console.error('Error loading user data:', error);
        if (!user.value?.id) {
          showNotification(
            error?.response?.data?.message || 'Failed to load profile data',
            'error'
          );
        }
      }
    };

    const showNotification = (message, type = 'info') => {
      if (instance && instance.appContext.config.globalProperties.$showNotification) {
        instance.appContext.config.globalProperties.$showNotification(message, type);
      } else {
        alert(message);
      }
    };

    const updateProfile = async () => {
      loading.value = true;
      try {
        const response = await api.put('/profile', formData);

        if (response.data.success || response.data.status) {
          applyUserToForm(response.data.data || response.data);
          showNotification('Profile updated successfully!', 'success');
        } else {
          showNotification(response.data.message || 'Failed to update profile', 'error');
        }
      } catch (error) {
        console.error('Error updating profile:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to update profile', 'error');
        } else {
          showNotification(error?.response?.data?.message || 'Failed to update profile', 'error');
        }
      } finally {
        loading.value = false;
      }
    };
    
    const changePassword = async () => {
      if (passwordData.new_password !== passwordData.confirm_password) {
        showNotification('New password and confirmation do not match!', 'warning');
        return;
      }

      if (passwordData.new_password.length < 6) {
        showNotification('Password must be at least 6 characters long!', 'warning');
        return;
      }

      passwordLoading.value = true;
      try {
        const response = await api.post('/profile/change-password', {
          current_password: passwordData.current_password,
          new_password: passwordData.new_password,
          new_password_confirmation: passwordData.confirm_password
        });

        if (response.data.success || response.data.status) {
          passwordData.current_password = '';
          passwordData.new_password = '';
          passwordData.confirm_password = '';
          showNotification('Password changed successfully!', 'success');
        } else {
          showNotification(response.data.message || 'Failed to change password', 'error');
        }
      } catch (error) {
        console.error('Error changing password:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to change password', 'error');
        } else {
          showNotification(error?.response?.data?.message || 'Failed to change password', 'error');
        }
      } finally {
        passwordLoading.value = false;
      }
    };

    const updateAvatar = async (file) => {
      try {
        const formDataObj = new FormData();
        formDataObj.append('avatar', file);

        const response = await api.post('/profile/avatar', formDataObj, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });

        if (response.data.success || response.data.status) {
          applyUserToForm(response.data.data || response.data);
          showNotification('Profile image updated successfully!', 'success');
        } else {
          showNotification(response.data.message || 'Failed to update avatar', 'error');
        }
      } catch (error) {
        console.error('Error updating avatar:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to update avatar', 'error');
        } else {
          showNotification(error?.response?.data?.message || 'Failed to update avatar', 'error');
        }
      }
    };
    
    const onImageChange = (event) => {
      const file = event.target.files[0];
      if (file) {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
          showNotification('Please select a valid image file (JPEG, JPG, PNG, GIF)', 'warning');
          return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
          showNotification('Image size should be less than 2MB', 'warning');
          return;
        }
        
        const reader = new FileReader();
        reader.onload = e => {
          profileImage.value = e.target.result;
          updateAvatar(file);
        };
        reader.readAsDataURL(file);
      }
    };
    
    const resetForm = () => {
      loadUserData();
      showNotification('Form reset to original values', 'info');
    };

    const clearPasswordForm = () => {
      passwordData.current_password = '';
      passwordData.new_password = '';
      passwordData.confirm_password = '';
    };

    const loadAttendanceSettings = async () => {
      if (!isSuperAdmin.value) return;
      attendanceSettingsLoading.value = true;
      try {
        const response = await api.get('/attendance/settings');

        attendanceSettings.day_of_week = Number.isInteger(response.data?.day_of_week) ? response.data.day_of_week : 6;
        attendanceSettings.start_time = String(response.data?.start_time || '09:00:00').slice(0, 5);
        attendanceSettings.end_time = String(response.data?.end_time || '10:00:00').slice(0, 5);
        attendanceSettings.department_ids = Array.isArray(response.data?.department_ids)
          ? response.data.department_ids.map((id) => Number(id)).filter((id) => Number.isInteger(id))
          : [];
      } catch (error) {
        console.error('Error loading attendance settings:', error);
      } finally {
        attendanceSettingsLoading.value = false;
      }
    };

    const loadDepartmentOptions = async () => {
      if (!isSuperAdmin.value) return;
      try {
        const response = await api.get('/attendance/departments');
        const rows = Array.isArray(response.data) ? response.data : (response.data?.data || []);

        departmentOptions.value = rows
          .map((dept) => ({
            value: Number(dept?.id),
            label: String(dept?.name || `Department #${dept?.id || ''}`)
          }))
          .filter((dept) => Number.isInteger(dept.value));
      } catch (error) {
        console.error('Error loading department options:', error);
        departmentOptions.value = [];
      }
    };

    const loadAttendanceStatus = async () => {
      try {
        const response = await api.get('/attendance/status');

        attendanceStatus.is_active_day = !!response.data?.is_active_day;
        attendanceStatus.is_department_active = response.data?.is_department_active !== false;
        attendanceStatus.is_within_time_window = !!response.data?.is_within_time_window;
        attendanceStatus.already_checked_in = !!response.data?.already_checked_in;
        attendanceStatus.check_in_at = response.data?.check_in_at;
        attendanceStatus.status = response.data?.status || 'Closed';
        attendanceStatus.window_label = response.data?.window_label || 'Not configured';
        attendanceStatus.today_code = response.data?.today_code || '';
      } catch (error) {
        console.error('Error loading attendance status:', error);
      }
    };

    const saveAttendanceSettings = async () => {
      if (!isSuperAdmin.value) return;
      attendanceSettingsSaving.value = true;
      try {
        const response = await api.put('/attendance/settings', {
          day_of_week: Number(attendanceSettings.day_of_week),
          start_time: attendanceSettings.start_time,
          end_time: attendanceSettings.end_time,
          department_ids: attendanceSettings.department_ids.map((id) => Number(id)).filter((id) => Number.isInteger(id)),
        });

        if (response.data?.success || response.data?.status) {
          showNotification('Attendance settings saved successfully!', 'success');
          await loadAttendanceStatus();
        }
      } catch (error) {
        showNotification(error?.response?.data?.message || 'Failed to save attendance settings', 'error');
      } finally {
        attendanceSettingsSaving.value = false;
      }
    };

    const toggleDepartmentSelection = (departmentId) => {
      const id = Number(departmentId);
      if (!Number.isInteger(id)) return;

      const current = attendanceSettings.department_ids.map((item) => Number(item)).filter((item) => Number.isInteger(item));
      const exists = current.includes(id);
      attendanceSettings.department_ids = exists
        ? current.filter((item) => item !== id)
        : [...current, id];
    };

    const submitCheckin = async () => {
      if (checkinSubmitting.value || !isCheckinCodeComplete.value) return;
      checkinSubmitting.value = true;
      try {
        const response = await api.post('/attendance/check-in', {
          code: String(checkinCode.value || '').trim().toUpperCase(),
        });

        if (response.data?.success || response.data?.status) {
          showNotification(response.data?.message || 'Checked in successfully', 'success');
          checkinCode.value = '';
          await loadAttendanceStatus();
        } else {
          showNotification(response.data?.message || 'Check-in failed', 'error');
        }
      } catch (error) {
        showNotification(error?.response?.data?.message || 'Check-in failed', 'error');
      } finally {
        checkinSubmitting.value = false;
      }
    };
    function handleAppNotification(event) {
      const n = event.detail || {}
      if (n.type === 'document_request_status') {
        if (activeTab.value === 'my-documents') loadMyDocuments()
      }
      if (n.type === 'asset_request_status') {
        if (activeTab.value === 'my-assets') loadMyAssets()
      }
      if (n.type === 'leave_request_status') {
        if (activeTab.value === 'my-leave') loadMyLeave()
        showNotification(n.message || 'Your leave request status changed', n.status === 'approved' ? 'success' : 'error')
      }
      if (n.type === 'leave_request') {
        if (isTeamLead.value) {
          loadTeamLeaveRequests() // always refresh, not gated on tab
          showNotification(n.message || 'New leave request awaiting your approval', 'info')
        }
      }
      if (n.type === 'leave_request_parent_status') {
        if (isTeamLead.value) {
          loadTeamLeaveRequests() // always refresh, not gated on tab
          showNotification(
            n.message || 'HR finalized a leave request',
            n.status === 'approved' ? 'success' : 'error'
          )
        }
      }
      if (n.type === 'announcement') {
        loadMyAnnouncements() // always refresh, badge should update even off-tab
        showNotification(n.message || 'New announcement posted', 'info')
      }
    }

onMounted(() => {
  loadUserData()
  loadVacationData()
  loadAttendanceStatus()
  loadMyAnnouncements() 
  window.addEventListener('app-notification', handleAppNotification)
})

onBeforeUnmount(() => {
  window.removeEventListener('app-notification', handleAppNotification)
})
  

    watch(isSuperAdmin, (value) => {
      if (value) {
        loadDepartmentOptions();
        loadAttendanceSettings();
      }
    }, { immediate: true });
    
    watch(() => vacationData.active, (newVal) => {
      if (newVal && agentsList.value.length === 0) {
        loadAgents();
      }
    });
    watch(activeTab, (value) => {
      if (value === 'sales-performance' && isSalesProfile.value) {
        loadSalesPerformance();
      }
    });
    


    // ===== My Documents =====
const myDocumentRequests = ref([]);
const docRequestsLoading = ref(false);
const documentTypes = ref([]);
const showDocRequestModal = ref(false);
const editingDocRequestId = ref(null);
const docRequestForm = reactive({ document_type_id: '', description: '' });
const docRequestSaving = ref(false);
const documentSearch = ref('');

const documentTypeOptions = computed(() => {
  const list = Array.isArray(documentTypes.value)
    ? documentTypes.value
    : Array.isArray(documentTypes.value?.data)
      ? documentTypes.value.data
      : [];
  return list.map((type) => ({ value: type.id, label: type.name }));
});

const docRequestStats = computed(() => {
  const list = myDocumentRequests.value || [];
  const tone = (item) => String(item.status || '').toLowerCase();
  return {
    total: list.length,
    pending: list.filter((item) => tone(item) === 'pending').length,
    approved: list.filter((item) => tone(item) === 'approved').length,
    rejected: list.filter((item) => tone(item) === 'rejected').length,
  };
});

const filteredDocumentRequests = computed(() => {
  const query = documentSearch.value.trim().toLowerCase();
  if (!query) return myDocumentRequests.value;
  return myDocumentRequests.value.filter((doc) =>
    [doc.document_type?.name, doc.description, doc.status]
      .some((value) => String(value || '').toLowerCase().includes(query)),
  );
});

const loadDocumentTypes = async () => {
  try {
    const res = await api.get('/document-types');
    documentTypes.value = res.data?.data || res.data || [];
  } catch (e) { console.error(e); }
};

const loadMyDocuments = async () => {
  docRequestsLoading.value = true;
  try {
    const res = await api.get('/document-requests');
    const payload = res.data?.data;
    myDocumentRequests.value = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
  } catch (e) {
    console.error(e);
    showNotification('Failed to load document requests', 'error');
  } finally {
    docRequestsLoading.value = false;
  }
  if (!documentTypes.value.length) await loadDocumentTypes();
};

const openDocRequestModal = (doc = null) => {
  if (doc) {
    editingDocRequestId.value = doc.id;
    docRequestForm.document_type_id = doc.document_type_id || doc.document_type?.id || '';
    docRequestForm.description = doc.description === '--' ? '' : (doc.description || '');
  } else {
    editingDocRequestId.value = null;
    docRequestForm.document_type_id = '';
    docRequestForm.description = '';
  }
  showDocRequestModal.value = true;
};

const submitDocRequest = async () => {
  if (!docRequestForm.document_type_id) {
    showNotification('Please select a document type', 'error');
    return;
  }
  docRequestSaving.value = true;
  try {
    const payload = {
      document_type_id: docRequestForm.document_type_id,
      description: docRequestForm.description || '',
    };
    if (editingDocRequestId.value) {
      await api.put(`/document-requests/${editingDocRequestId.value}`, payload);
      showNotification('Document request updated', 'success');
    } else {
      await api.post('/document-requests/store/new', payload);
      showNotification('Document request submitted', 'success');
    }
    showDocRequestModal.value = false;
    await loadMyDocuments();
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to submit request', 'error');
  } finally {
    docRequestSaving.value = false;
  }
};

const deleteMyDocumentRequest = async (doc) => {
  if (!confirm('Delete this document request?')) return;
  try {
    await api.delete(`/document-requests/${doc.id}`);
    showNotification('Document request deleted', 'success');
    await loadMyDocuments();
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to delete', 'error');
  }
};

// ===== My Assets =====
const myAssignedAssets = ref([]);
const myAssetRequests = ref([]);
const assetsLoading = ref(false);
const showAssetRequestModal = ref(false);
const editingAssetRequestId = ref(null);
const assetRequestForm = reactive({ asset_item: '', company_name: '', branch_id: '', department_id: '', qty: 1, description: '' });
const assetRequestSaving = ref(false);
const branchesForRequest = ref([]);
const departmentsForRequest = ref([]);
const assetSearch = ref('');

const branchSelectOptions = computed(() =>
  (branchesForRequest.value || []).map((branch) => ({ value: branch.id, label: branch.name })),
);
const assetDepartmentOptions = computed(() =>
  (departmentsForRequest.value || []).map((dept) => ({ value: dept.id, label: dept.name })),
);
const filteredAssetRequests = computed(() => {
  const query = assetSearch.value.trim().toLowerCase();
  if (!query) return myAssetRequests.value;
  return myAssetRequests.value.filter((req) =>
    [req.asset_item, req.description, req.status]
      .some((value) => String(value || '').toLowerCase().includes(query)),
  );
});

const loadAssetRequestOptions = async () => {
  try {
    const [branchesRes, deptsRes] = await Promise.all([
      api.get('/company-branches'),
      api.get('/departments'),
    ]);

    const branchesPayload = branchesRes.data?.data;
    branchesForRequest.value = Array.isArray(branchesPayload)
      ? branchesPayload
      : Array.isArray(branchesPayload?.data)
        ? branchesPayload.data
        : [];

    const deptsPayload = deptsRes.data?.data;
    departmentsForRequest.value = Array.isArray(deptsPayload)
      ? deptsPayload
      : Array.isArray(deptsPayload?.data)
        ? deptsPayload.data
        : [];
  } catch (e) {
    console.error(e);
    branchesForRequest.value = [];
    departmentsForRequest.value = [];
  }
};

const loadMyAssets = async () => {
  assetsLoading.value = true;
  try {
    const [assetsRes, requestsRes] = await Promise.all([
      api.get('/assets', { params: { user_id: user.value.id } }),
      api.get('/asset-requests', { params: { user_id: user.value.id } }),
    ]);
    const assetsPayload = assetsRes.data?.data;
    myAssignedAssets.value = Array.isArray(assetsPayload?.data) ? assetsPayload.data : (Array.isArray(assetsPayload) ? assetsPayload : []);
    const reqPayload = requestsRes.data?.data;
    myAssetRequests.value = Array.isArray(reqPayload?.data) ? reqPayload.data : (Array.isArray(reqPayload) ? reqPayload : []);
  } catch (e) {
    console.error(e);
    showNotification('Failed to load assets', 'error');
  } finally {
    assetsLoading.value = false;
  }
  if (!branchesForRequest.value.length) await loadAssetRequestOptions();
};

const openAssetRequestModal = (req = null) => {
  if (req) {
    editingAssetRequestId.value = req.id;
    assetRequestForm.asset_item = req.asset_item || '';
    assetRequestForm.company_name = req.company_name || '';
    assetRequestForm.branch_id = req.branch_id || '';
    assetRequestForm.department_id = req.department_id || '';
    assetRequestForm.qty = req.qty || 1;
    assetRequestForm.description = req.description || '';
  } else {
    editingAssetRequestId.value = null;
    Object.assign(assetRequestForm, { asset_item: '', company_name: '', branch_id: '', department_id: '', qty: 1, description: '' });
  }
  showAssetRequestModal.value = true;
};

const submitAssetRequest = async () => {
  if (!assetRequestForm.asset_item || !assetRequestForm.company_name || !assetRequestForm.branch_id || !assetRequestForm.department_id || !assetRequestForm.qty) {
    showNotification('Please fill all required fields', 'error');
    return;
  }
  assetRequestSaving.value = true;
  try {
    const payload = { ...assetRequestForm, user_id: user.value.id };
    if (editingAssetRequestId.value) {
      await api.put(`/asset-requests/${editingAssetRequestId.value}`, payload);
      showNotification('Asset request updated', 'success');
    } else {
      await api.post('/asset-requests', payload);
      showNotification('Asset request submitted', 'success');
    }
    showAssetRequestModal.value = false;
    await loadMyAssets();
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to submit request', 'error');
  } finally {
    assetRequestSaving.value = false;
  }
};

const deleteMyAssetRequest = async (req) => {
  if (!confirm('Delete this asset request?')) return;
  try {
    await api.delete(`/asset-requests/${req.id}`);
    showNotification('Asset request deleted', 'success');
    await loadMyAssets();
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to delete', 'error');
  }
};

// ===== My Leave =====
const myLeaveRequests = ref([]);
const myLeaveBalance = ref([]);
const myLeaveTypes = ref([]);
const leaveLoading = ref(false);
const showLeaveRequestModal = ref(false);
const leaveRequestForm = reactive({ leave_type_id: '', start_date: '', end_date: '', reason: '', is_half_day: false, half_day_type: 'morning' });
const leaveRequestSaving = ref(false);
const leaveSearch = ref('');
const halfDayTypeOptions = [
  { value: 'morning', label: 'Morning' },
  { value: 'afternoon', label: 'Afternoon' },
];

const leaveTypeOptions = computed(() =>
  (myLeaveTypes.value || []).map((type) => ({ value: type.id, label: type.name })),
);
const filteredLeaveRequests = computed(() => {
  const query = leaveSearch.value.trim().toLowerCase();
  if (!query) return myLeaveRequests.value;
  return myLeaveRequests.value.filter((lv) =>
    [lv.leave_type?.name, lv.status]
      .some((value) => String(value || '').toLowerCase().includes(query)),
  );
});

const loadMyLeave = async () => {
  leaveLoading.value = true;
  try {
    const [reqRes, balanceRes, typesRes] = await Promise.all([
      api.get('/leaves'),
      api.get('/leaves/my-balance'),
      api.get('/leaves/types'),
    ]);
    const reqPayload = reqRes.data?.data;
    myLeaveRequests.value = Array.isArray(reqPayload?.data) ? reqPayload.data : (Array.isArray(reqPayload) ? reqPayload : []);
    myLeaveBalance.value = balanceRes.data?.data || [];
    myLeaveTypes.value = typesRes.data?.data || [];
  } catch (e) {
    console.error(e);
    showNotification('Failed to load leave data', 'error');
  } finally {
    leaveLoading.value = false;
  }
};

const openLeaveRequestModal = () => {
  Object.assign(leaveRequestForm, { leave_type_id: '', start_date: '', end_date: '', reason: '', is_half_day: false, half_day_type: 'morning' });
  showLeaveRequestModal.value = true;
};

const submitLeaveRequest = async () => {
  if (!leaveRequestForm.leave_type_id || !leaveRequestForm.start_date || !leaveRequestForm.end_date) {
    showNotification('Please fill all required fields', 'error');
    return;
  }
  leaveRequestSaving.value = true;
  try {
    await api.post('/leaves', { ...leaveRequestForm });
    showNotification('Leave request submitted', 'success');
    showLeaveRequestModal.value = false;
    await loadMyLeave();
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to submit leave request', 'error');
  } finally {
    leaveRequestSaving.value = false;
  }
};

const cancelMyLeave = async (lv) => {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel this leave request?',
    text: 'This action cannot be undone.',
    showCancelButton: true,
    confirmButtonText: 'Yes, cancel it',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Keep it',
  });
  if (!result.isConfirmed) return;

  try {
    await api.post(`/leaves/${lv.id}/cancel`);
    Swal.fire({
      icon: 'success',
      title: 'Leave request cancelled',
      timer: 1600,
      showConfirmButton: false,
      toast: true,
      position: 'top-end',
    });
    await loadMyLeave();
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Failed to cancel',
      text: error.response?.data?.message || error.message,
    });
  }
};
// ===== Team Leave Requests (team lead / manager) =====
const teamLeaveRequests = ref([]);
const teamLeaveLoading = ref(false);
const teamLeaveSearch = ref('');
const teamLeaveActingId = ref(null);


const teamLeaveStatusFilter = ref('all');

const filteredTeamLeaveRequests = computed(() => {
  const query = teamLeaveSearch.value.trim().toLowerCase();
  let list = teamLeaveRequests.value;
  if (teamLeaveStatusFilter.value !== 'all') {
    list = list.filter((lv) => lv.status === teamLeaveStatusFilter.value);
  }
  if (!query) return list;
  return list.filter((lv) =>
    [lv.user?.name, lv.leave_type?.name]
      .some((value) => String(value || '').toLowerCase().includes(query)),
  );
});

const loadTeamLeaveRequests = async () => {
  teamLeaveLoading.value = true;
  try {
    // No status filter — backend index() already scopes team_lead to their direct reports;
    // we want the full history here, not just pending ones.
    const res = await api.get('/leaves');
    const payload = res.data?.data;
    teamLeaveRequests.value = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
  } catch (e) {
    console.error(e);
    showNotification('Failed to load team leave requests', 'error');
    teamLeaveRequests.value = [];
  } finally {
    teamLeaveLoading.value = false;
  }
};
const teamLeavePendingCount = computed(() =>
  teamLeaveRequests.value.filter((lv) => lv.status === 'pending_parent').length
);

const teamLeaveStats = computed(() => {
  const list = teamLeaveRequests.value || [];
  return {
    total: list.length,
    pending: list.filter((lv) => lv.status === 'pending_parent' || lv.status === 'pending_hr').length,
    approved: list.filter((lv) => lv.status === 'approved').length,
    rejected: list.filter((lv) => lv.status === 'rejected').length,
  };
});

const approveTeamLeave = async (lv) => {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Approve this leave request?',
    html: `You are approving <strong>${lv.user?.name || 'this employee'}</strong>'s ${lv.leave_type?.name || ''} leave request.`,
    showCancelButton: true,
    confirmButtonText: 'Approve',
    confirmButtonColor: '#16a34a',
    cancelButtonText: 'Cancel',
  });
  if (!result.isConfirmed) return;

  teamLeaveActingId.value = lv.id;
  try {
    await api.post(`/leaves/${lv.id}/approve-parent`);
    Swal.fire({
      icon: 'success',
      title: 'Request approved',
      timer: 1600,
      showConfirmButton: false,
      toast: true,
      position: 'top-end',
    });
    await loadTeamLeaveRequests();
    window.dispatchEvent(new CustomEvent('app-notification', {
      detail: { type: 'leave_request_status', status: 'approved' },
    }));
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Failed to approve',
      text: error.response?.data?.message || error.message,
    });
  } finally {
    teamLeaveActingId.value = null;
  }
};

const rejectTeamLeave = async (lv) => {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Reject this leave request?',
    html: `You are rejecting <strong>${lv.user?.name || 'this employee'}</strong>'s ${lv.leave_type?.name || ''} leave request.`,
    input: 'textarea',
    inputPlaceholder: 'Reason for rejection (min 4 characters)',
    inputValidator: (value) => {
      if (!value || value.trim().length < 4) {
        return 'Rejection reason must be at least 4 characters';
      }
    },
    showCancelButton: true,
    confirmButtonText: 'Reject',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Cancel',
  });
  if (!result.isConfirmed) return;

  teamLeaveActingId.value = lv.id;
  try {
    await api.post(`/leaves/${lv.id}/reject-parent`, { rejection_reason: result.value.trim() });
    Swal.fire({
      icon: 'success',
      title: 'Request rejected',
      timer: 1600,
      showConfirmButton: false,
      toast: true,
      position: 'top-end',
    });
    await loadTeamLeaveRequests();
    window.dispatchEvent(new CustomEvent('app-notification', {
      detail: { type: 'leave_request_status', status: 'rejected' },
    }));
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Failed to reject',
      text: error.response?.data?.message || error.message,
    });
  } finally {
    teamLeaveActingId.value = null;
  }
};
// ===== Announcements =====
const myAnnouncements = ref([]);
const announcementsLoading = ref(false);
const announcementSearch = ref('');
const showAnnouncementModal = ref(false);
const viewingAnnouncement = ref(null);

const unreadAnnouncementsCount = computed(() =>
  myAnnouncements.value.filter((a) => !a.is_viewed).length
);

const filteredAnnouncements = computed(() => {
  const query = announcementSearch.value.trim().toLowerCase();
  if (!query) return myAnnouncements.value;
  return myAnnouncements.value.filter((a) =>
    [a.title, a.description].some((value) => String(value || '').toLowerCase().includes(query)),
  );
});

const loadMyAnnouncements = async () => {
  announcementsLoading.value = true;
  try {
    const res = await api.get('/announcements/active');
    const payload = res.data?.data;
    myAnnouncements.value = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);
  } catch (e) {
    console.error(e);
    showNotification('Failed to load announcements', 'error');
    myAnnouncements.value = [];
  } finally {
    announcementsLoading.value = false;
  }
};

const openAnnouncement = async (item) => {
  viewingAnnouncement.value = item;
  showAnnouncementModal.value = true;
  if (!item.is_viewed) {
    try {
      // GET /announcements/{id} marks it as viewed server-side (per AnnouncementController::show)
      const res = await api.get(`/announcements/${item.id}`);
      const updated = res.data?.data;
      const idx = myAnnouncements.value.findIndex((a) => a.id === item.id);
      if (idx !== -1) {
        myAnnouncements.value[idx] = { ...myAnnouncements.value[idx], is_viewed: true };
      }
      if (updated) viewingAnnouncement.value = updated;
    } catch (e) {
      console.error(e);
    }
  }
};

const markAllAnnouncementsRead = async () => {
  try {
    await api.post('/announcements/mark-all-read');
    myAnnouncements.value = myAnnouncements.value.map((a) => ({ ...a, is_viewed: true }));
    showNotification('All announcements marked as read', 'success');
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to mark as read', 'error');
  }
};


// ===== Document Detail Modal =====
const showDocumentDetailModal = ref(false);
const viewingDocument = ref(null);

const openDocumentDetail = (doc) => {
  viewingDocument.value = doc;
  showDocumentDetailModal.value = true;
};

// ===== Leave Detail Modal =====
const showLeaveDetailModal = ref(false);
const viewingLeave = ref(null);

const openLeaveDetail = (lv) => {
  viewingLeave.value = lv;
  showLeaveDetailModal.value = true;
};
    return {
      user,
      activeTab,
      profileImage,
      defaultAvatar: defaultAvatarImg,
      formData,
      passwordData,
      vacationData,
      agentsList,
      loading,
      passwordLoading,
      vacationLoading,
      attendanceSettingsLoading,
      attendanceSettingsSaving,
      attendanceSettings,
      attendanceStatus,
      departmentOptions,
      checkinSubmitting,
      checkinCode,
      dayOptions,
      isSuperAdmin,
      isTeamLead,
      isSalesProfile,
      userInitials,
      displayAvatar,
      isOnline,
      lastActiveText,
      statusLabel,
      isCheckinCodeComplete,
      selectedDepartmentLabels,
      checkinBadgeClass,
      currentPasswordVisible,
      newPasswordVisible,
      confirmPasswordVisible,
      updateProfile,
      changePassword,
      resetForm,
      clearPasswordForm,
      onImageChange,
      loadVacationData,
      saveVacationMode,
      saveAttendanceSettings,
      toggleDepartmentSelection,
      submitCheckin,
      currentDelegate,
      formatDate,
      formatDateShort,
      formatStatusLabel,
      statusTone,
      leaveBalancePercent,
      myDocumentRequests, docRequestsLoading, documentTypes, showDocRequestModal, editingDocRequestId,
      docRequestForm, docRequestSaving, documentSearch, documentTypeOptions, docRequestStats, filteredDocumentRequests,
      loadMyDocuments, openDocRequestModal, submitDocRequest, deleteMyDocumentRequest,
      myAssignedAssets, myAssetRequests, assetsLoading, showAssetRequestModal, editingAssetRequestId, assetRequestForm,
      assetRequestSaving, branchesForRequest, departmentsForRequest, assetSearch, branchSelectOptions, assetDepartmentOptions,
      filteredAssetRequests, loadMyAssets, openAssetRequestModal,
      submitAssetRequest, deleteMyAssetRequest,
      myLeaveRequests, myLeaveBalance, myLeaveTypes, leaveLoading, showLeaveRequestModal, leaveRequestForm, leaveRequestSaving,
      leaveSearch, leaveTypeOptions, halfDayTypeOptions, filteredLeaveRequests,
      loadMyLeave, openLeaveRequestModal, submitLeaveRequest, cancelMyLeave,
      teamLeaveRequests, teamLeaveLoading, teamLeaveSearch, teamLeaveActingId, teamLeavePendingCount,
      teamLeaveStats, teamLeaveStatusFilter,
      filteredTeamLeaveRequests, loadTeamLeaveRequests, approveTeamLeave, rejectTeamLeave,

      myAnnouncements, announcementsLoading, announcementSearch, showAnnouncementModal, viewingAnnouncement,    
      unreadAnnouncementsCount, filteredAnnouncements, loadMyAnnouncements, openAnnouncement, markAllAnnouncementsRead,

      showDocumentDetailModal, viewingDocument, openDocumentDetail,
      showLeaveDetailModal, viewingLeave, openLeaveDetail,
      salesPerformanceLoading, salesPerformanceError, salesPerformanceDeals, salesPerformanceSummary,
      salesPerformanceFilters, loadSalesPerformance, formatCurrency, scoreClass,
      aiIntelLoading, aiIntelRecalculating, aiIntelError, aiIntelAgent, aiIntelObservations,
      aiOverallScore, aiSubscoreItems, aiScoreClass, loadAiIntelligence, recalculateAiIntelligence,
      canViewTeamPerformance, teamPerformanceLoading, teamPerformanceError, teamPerformanceAgents,
      teamPerformanceSummary, teamPerformanceFilters, loadTeamPerformance,
    };
  }
};
</script>
<style scoped>
.vp-loading { padding: 20px; color: #6b7280; }
.vp-row-btn { border: none; background: transparent; color: #6b7280; margin-right: 4px; }
.vp-status-pill {
  display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: capitalize;
}
.vp-status-pending { background: #fef3c7; color: #92400e; }
.vp-status-approved { background: #dcfce7; color: #166534; }
.vp-status-rejected { background: #fee2e2; color: #991b1b; }
.vp-status-pending_parent, .vp-status-pending_hr { background: #fef3c7; color: #92400e; }
.vp-status-assigned { background: #dbeafe; color: #1e40af; }
.vp-modal--form :deep(.crm-field) { margin: 0; }
.vp-modal--form :deep(.vs__dropdown-toggle) {
  height: 46px;
  min-height: 46px;
  border-radius: 10px;
  border: 1px solid #eceff5;
  padding: 0 10px;
}
.vp-modal--form :deep(.vs__search),
.vp-modal--form :deep(.vs__selected) {
  font-size: 14px;
  color: #0b0736;
}
.vp-modal--form :deep(.vs__dropdown-menu) {
  border-radius: 12px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
}

.vp-nav__badge {
  margin-left: auto;
  background: #dc2626;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  border-radius: 999px;
  padding: 1px 7px;
  line-height: 1.6;
}
.vp-row-btn--approve { color: #16a34a; }
.vp-row-btn--reject { color: #dc2626; }
.vp-row-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.vp-filter-chips { display: flex; gap: 6px; }
.vp-chip {
  border: 1px solid #e5e7eb; background: #fff; border-radius: 999px;
  padding: 5px 12px; font-size: 12px; color: #6b7280; cursor: pointer;
}
.vp-chip.is-active { background: #0b0736; border-color: #0b0736; color: #fff; }

.vp-announcement-list { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
.vp-announcement-card {
  border: 1px solid #edf1f6; border-radius: 12px; padding: 14px 16px;
  background: #fff; cursor: pointer; transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.vp-announcement-card:hover { box-shadow: 0 4px 14px rgba(15,23,42,0.08); }
.vp-announcement-card.is-unread { border-color: #0b0736; background: #f8f7ff; }
.vp-announcement-card__top {
  display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
}
.vp-announcement-card__top strong { font-size: 14px; color: #0b0736; flex: 1; }
.vp-announcement-card__date { font-size: 11px; color: #9ca3af; white-space: nowrap; }
.vp-unread-dot {
  width: 8px; height: 8px; border-radius: 999px; background: #dc2626; flex-shrink: 0;
}
.vp-announcement-card__desc {
  margin: 0 0 8px; font-size: 13px; color: #4b5563; line-height: 1.5;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.vp-announcement-card__meta {
  display: flex; flex-wrap: wrap; gap: 12px; font-size: 11px; color: #9ca3af;
}
.vp-announcement-card__meta span { display: inline-flex; align-items: center; gap: 4px; }

.vp-detail-grid {
  display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px;
}
.vp-detail-grid p { margin: 0; display: flex; flex-direction: column; gap: 2px; }
.vp-detail-grid p span { font-size: 11px; color: #9ca3af; }
.vp-detail-grid p strong { font-size: 13px; color: #0b0736; font-weight: 600; }
.vp-detail-note {
  margin: 4px 0 0; font-size: 13px; color: #4b5563; line-height: 1.5;
  background: #f8fafc; border: 1px solid #edf1f6; border-radius: 8px; padding: 10px 12px;
}
.vp-detail-note--danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.vp-sales-toolbar {
  display: flex;
  align-items: end;
  gap: 10px;
  flex-wrap: wrap;
}
.vp-sales-date {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.vp-sales-date label {
  font-size: 11px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .03em;
}
.vp-sales-date input {
  height: 40px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 0 10px;
  color: #0b0736;
}
.vp-btn-primary--sm {
  min-height: 40px;
  padding: 0 16px;
}
.vp-sales-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.vp-sales-kpi {
  border: 1px solid #edf1f6;
  border-radius: 14px;
  background: #fff;
  padding: 16px;
}
.vp-sales-kpi__label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}
.vp-sales-kpi__value {
  font-size: 24px;
  line-height: 1.1;
  font-weight: 700;
  color: #0b0736;
}
.vp-sales-card {
  border: 1px solid #edf1f6;
  border-radius: 16px;
  background: #fff;
  overflow: hidden;
}
.vp-sales-card__head {
  display: flex;
  align-items: start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 20px 0;
}
.vp-sales-state {
  padding: 20px;
  color: #6b7280;
}
.vp-sales-state--error {
  margin-bottom: 14px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fef2f2;
  color: #991b1b;
}
.vp-sales-table-wrap {
  overflow-x: auto;
  padding: 8px 20px 20px;
}
.vp-sales-table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
}
.vp-sales-table th,
.vp-sales-table td {
  padding: 12px 10px;
  border-bottom: 1px solid #edf1f6;
  font-size: 13px;
  color: #0b0736;
}
.vp-sales-table th {
  font-size: 11px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: .03em;
  font-weight: 700;
}
.vp-sales-table .is-right {
  text-align: right;
}
.vp-sales-table .is-accent {
  color: #166534;
  font-weight: 700;
}
.vp-sales-score {
  display: inline-block;
  min-width: 36px;
  padding: 3px 8px;
  border-radius: 999px;
  background: #eef2f7;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
}
.vp-sales-score.is-hot {
  background: #dcfce7;
  color: #166534;
}
.vp-sales-score.is-warm {
  background: #fef3c7;
  color: #92400e;
}
.vp-sales-score.is-cold {
  background: #fee2e2;
  color: #991b1b;
}
.vp-sales-location-extra {
  margin-left: 4px;
  font-size: 11px;
  color: #9ca3af;
}

@media (max-width: 991px) {
  .vp-sales-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .vp-sales-kpis {
    grid-template-columns: 1fr;
  }
  .vp-sales-toolbar {
    align-items: stretch;
  }
}

.vp-ai-card {
  padding: 20px;
}
.vp-ai-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin: 14px 0;
}
.vp-ai-kpi {
  border: 1px solid #edf1f6;
  border-radius: 12px;
  background: #f8fafc;
  padding: 12px 14px;
}
.vp-ai-kpi__value {
  display: inline-block;
  font-size: 22px;
  line-height: 1.1;
  font-weight: 700;
  color: #64748b;
}
.vp-ai-kpi__value.is-excellent { color: #059669; }
.vp-ai-kpi__value.is-good { color: #2563eb; }
.vp-ai-kpi__value.is-warn { color: #d97706; }
.vp-ai-kpi__value.is-critical { color: #dc2626; }
.vp-ai-kpi__text {
  display: block;
  font-size: 15px;
  font-weight: 700;
  color: #0b0736;
  text-transform: capitalize;
}
.vp-ai-subscores {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}
.vp-ai-subscore {
  display: grid;
  grid-template-columns: 110px 1fr 32px;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: #475569;
}
.vp-ai-subscore__bar {
  height: 7px;
  border-radius: 999px;
  background: #eef2f7;
  overflow: hidden;
}
.vp-ai-subscore__bar > div {
  height: 100%;
  border-radius: 999px;
}
.vp-ai-subscore__value {
  text-align: right;
  font-weight: 700;
  color: #0b0736;
}
.vp-ai-summary,
.vp-ai-coaching,
.vp-ai-observations-wrap {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #edf1f6;
}
.vp-ai-summary h5,
.vp-ai-coaching h5,
.vp-ai-observations-wrap h5 {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: #6b7280;
}
.vp-ai-summary p {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: #334155;
}
.vp-ai-coaching {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.vp-ai-coach {
  border: 1px solid #edf1f6;
  border-left: 3px solid #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  padding: 10px 12px;
}
.vp-ai-coach strong {
  display: block;
  font-size: 13px;
  color: #0b0736;
  margin-bottom: 3px;
}
.vp-ai-coach p {
  margin: 0;
  font-size: 12px;
  color: #64748b;
  line-height: 1.4;
}
.vp-ai-coach.is-critical { border-left-color: #ef4444; }
.vp-ai-coach.is-high { border-left-color: #f59e0b; }
.vp-ai-observations {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.vp-ai-observations li {
  padding: 8px 10px;
  border-radius: 8px;
  font-size: 12px;
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  color: #334155;
}
.vp-ai-observations li.is-warning { background: #fffbeb; border-color: #fde68a; }
.vp-ai-observations li.is-critical { background: #fef2f2; border-color: #fecaca; }

@media (max-width: 991px) {
  .vp-ai-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 640px) {
  .vp-ai-kpis {
    grid-template-columns: 1fr;
  }
  .vp-ai-subscore {
    grid-template-columns: 90px 1fr 30px;
  }
}
</style>