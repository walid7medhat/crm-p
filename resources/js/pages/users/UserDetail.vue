<template>
    <div class="dashboard-main-body">
        <!-- Breadcrumb -->
        <Breadcrumb 
            :title="user ? user.name : 'User Details'" 
            :breadcrumbs="[
                { name: 'Dashboard', path: '/' },
                { name: 'Users', path: '/users' },
                { name: user ? user.name : 'Loading...' }
            ]" 
        />
        
        <!-- Main Content Container -->
        <div class="user-details-container">
            <!-- Loading State -->
            <div v-if="loading" class="card mb-4">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="mb-0">Loading user information...</p>
                </div>
            </div>

            <!-- User Data -->
            <div v-else-if="user" class="ud-layout">
                <!-- Profile Sidebar -->
                <aside class="ud-side">
                    <section class="ud-card ud-profile">
                        <div class="ud-profile__cover">
                            <span class="ud-profile__eyebrow">
                                <i class="ri-user-star-line"></i>
                                Team Member
                            </span>
                            <span
                                v-if="isSuperAdminViewer"
                                class="ud-presence"
                                :class="isUserOnline ? 'is-online' : 'is-offline'"
                            >
                                <span class="ud-presence__dot"></span>
                                {{ isUserOnline ? 'Online' : 'Offline' }}
                            </span>
                        </div>

                        <div class="ud-profile__head">
                            <div class="ud-avatar">
                                <img :src="user.avatar || userPlaceholder"
                                     alt="User Avatar"
                                     @error="handleImageError">
                                <span
                                    v-if="isSuperAdminViewer && isUserOnline"
                                    class="ud-avatar__online"
                                    title="Online"
                                ></span>
                            </div>
                            <h2 class="ud-profile__name">{{ user.name }}</h2>
                            <div class="ud-chips">
                                <span v-if="user.role_name" class="ud-chip ud-chip--brand">
                                    <i class="ri-shield-user-line"></i>{{ user.role_name }}
                                </span>
                                <span class="ud-chip" :class="user.status === 'active' ? 'ud-chip--success' : 'ud-chip--danger'">
                                    <span class="ud-chip__dot"></span>
                                    {{ user.status === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <div class="ud-actions">
                            <button class="ud-btn ud-btn--icon" title="Back" @click="$router.back()">
                                <i class="ri-arrow-left-line"></i>
                            </button>
                            <button v-if="$hasPermission('users-edit')"
                                    class="ud-btn ud-btn--primary"
                                    @click="$router.push(`/users/${user.id}/edit`)">
                                <i class="ri-edit-line"></i>
                                <span>Edit User</span>
                            </button>
                            <button v-if="canManageVacation"
                                    class="ud-btn ud-btn--soft"
                                    @click="openVacationModal">
                                <i class="ri-suitcase-line"></i>
                                <span>Vacation</span>
                            </button>
                        </div>

                        <div v-if="isAgent && !agentPropertiesLoading && totalAgentProperties" class="ud-stats">
                            <div class="ud-stat">
                                <span class="ud-stat__value">{{ totalAgentProperties }}</span>
                                <span class="ud-stat__label">Listings</span>
                            </div>
                            <div class="ud-stat">
                                <span class="ud-stat__value">{{ activeAgentProperties }}</span>
                                <span class="ud-stat__label">Active</span>
                            </div>
                            <div class="ud-stat">
                                <span class="ud-stat__value">{{ soldAgentProperties }}</span>
                                <span class="ud-stat__label">Sold</span>
                            </div>
                        </div>

                        <ul class="ud-info">
                            <li v-if="user.email">
                                <span class="ud-info__icon"><i class="ri-mail-line"></i></span>
                                <span class="ud-info__text">
                                    <span class="ud-info__label">Email</span>
                                    <a :href="`mailto:${user.email}`" class="ud-info__value" :title="user.email">{{ user.email }}</a>
                                </span>
                            </li>
                            <li v-if="user.phone">
                                <span class="ud-info__icon"><i class="ri-phone-line"></i></span>
                                <span class="ud-info__text">
                                    <span class="ud-info__label">Phone</span>
                                    <a :href="`tel:${user.phone}`" class="ud-info__value">{{ user.phone }}</a>
                                </span>
                            </li>
                            <li v-if="user.admin_parent_name">
                                <span class="ud-info__icon"><i class="ri-building-2-line"></i></span>
                                <span class="ud-info__text">
                                    <span class="ud-info__label">Branch</span>
                                    <span class="ud-info__value">{{ user.admin_parent_name }}</span>
                                </span>
                            </li>
                            <li v-if="user.admin_parent_name">
                                <span class="ud-info__icon"><i class="ri-user-follow-line"></i></span>
                                <span class="ud-info__text">
                                    <span class="ud-info__label">{{ user.parent_role ? user.parent_role.replace(/_/g, ' ') : 'System Admin' }}</span>
                                    <span class="ud-info__value">{{ user.parent_name || '—' }}</span>
                                </span>
                            </li>
                            <li>
                                <span class="ud-info__icon"><i class="ri-time-line"></i></span>
                                <span class="ud-info__text">
                                    <span class="ud-info__label">Last Login</span>
                                    <span class="ud-info__value">{{ formatLastLoginDetail(user.last_login_at) }}</span>
                                </span>
                            </li>
                        </ul>
                    </section>

                    <section v-if="isSuperAdminViewer" class="ud-card ud-location">
                        <UserDeviceLocation
                            :latitude="user.last_login_lat"
                            :longitude="user.last_login_lng"
                            :accuracy="user.last_login_accuracy"
                            :captured-at="user.last_login_location_at"
                            :address="user.last_login_location"
                            :source="user.last_login_location_source"
                        />
                    </section>

                    <!-- Section menu (Bootstrap tabs) -->
                    <!-- On desktop these tabs show in the top navbar; kept in the DOM so they still drive Bootstrap -->
                    <nav v-show="isMobileViewport" class="ud-card ud-nav">
                        <div class="nav ud-nav__list" id="userTab" role="tablist">
                            <button v-if="isAgent" class="nav-link active" id="overview-tab" data-bs-toggle="tab"
                                    data-bs-target="#overview" type="button" role="tab">
                                <span class="ud-nav__icon"><i class="ri-dashboard-3-line"></i></span>
                                <span class="ud-nav__label">Overview</span>
                                <i class="ri-arrow-right-s-line ud-nav__chevron"></i>
                            </button>
                            <button v-if="isAgent" class="nav-link" id="properties-tab" data-bs-toggle="tab"
                                    data-bs-target="#properties" type="button" role="tab">
                                <span class="ud-nav__icon"><i class="ri-home-4-line"></i></span>
                                <span class="ud-nav__label">Properties</span>
                                <span v-if="agentProperties.length > 0" class="ud-nav__badge">{{ agentProperties.length }}</span>
                                <i class="ri-arrow-right-s-line ud-nav__chevron"></i>
                            </button>
                            <!-- <button v-if="isAgent && canViewAgentPerformance" class="nav-link" id="performance-tab" data-bs-toggle="tab"
                                    data-bs-target="#performance" type="button" role="tab" @click="loadSalesPerformance">
                                <i class="ri-line-chart-line me-2"></i>
                                <span>Performance</span>
                            </button> -->
                            <button v-if="canViewAgentAttendance" class="nav-link" :class="{ active: !isAgent }" id="attendance-tab" data-bs-toggle="tab"
                                    data-bs-target="#attendance" type="button" role="tab">
                                <span class="ud-nav__icon"><i class="ri-calendar-check-line"></i></span>
                                <span class="ud-nav__label">Attendance</span>
                                <i class="ri-arrow-right-s-line ud-nav__chevron"></i>
                            </button>
                            <button v-if="canViewEvaluations" class="nav-link" id="evaluations-tab" data-bs-toggle="tab"
                                    data-bs-target="#evaluations" type="button" role="tab" @click="loadUserEvaluations">
                                <span class="ud-nav__icon"><i class="ri-clipboard-line"></i></span>
                                <span class="ud-nav__label">Evaluations</span>
                                <span v-if="userEvaluations.length > 0" class="ud-nav__badge">{{ userEvaluations.length }}</span>
                                <i class="ri-arrow-right-s-line ud-nav__chevron"></i>
                            </button>
                        </div>
                    </nav>
                </aside>

                <!-- Tab Content -->
                <div class="tab-content ud-main" id="userTabContent">

                    <!-- Overview Tab: analytics for this agent -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel" v-if="isAgent">
                        <div v-if="agentPropertiesLoading" class="ud-card text-center text-muted py-5">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <p class="mb-0">Loading analytics...</p>
                        </div>
                        <div v-else class="ua">
                            <div class="ua-kpis">
                                <div class="ud-card ua-kpi">
                                    <span class="ua-kpi__label"><i class="ri-home-4-line"></i>Total Listings</span>
                                    <strong class="ua-kpi__value">{{ totalAgentProperties }}</strong>
                                    <span class="ua-kpi__sub">{{ activeAgentProperties }} active now</span>
                                </div>
                                <div class="ud-card ua-kpi">
                                    <span class="ua-kpi__label"><i class="ri-money-dollar-circle-line"></i>Portfolio Value</span>
                                    <strong class="ua-kpi__value">{{ formatCompactAed(listingAnalytics.portfolioValue) }}</strong>
                                    <span class="ua-kpi__sub">Active listings</span>
                                </div>
                                <div class="ud-card ua-kpi">
                                    <span class="ua-kpi__label"><i class="ri-price-tag-3-line"></i>Average Price</span>
                                    <strong class="ua-kpi__value">{{ formatCompactAed(listingAnalytics.avgPrice) }}</strong>
                                    <span class="ua-kpi__sub">Across all listings</span>
                                </div>
                                <div class="ud-card ua-kpi">
                                    <span class="ua-kpi__label"><i class="ri-checkbox-circle-line"></i>Sold</span>
                                    <strong class="ua-kpi__value">{{ soldAgentProperties }}</strong>
                                    <span class="ua-kpi__sub">{{ listingAnalytics.hotDeals }} hot deals · {{ listingAnalytics.needsApproval }} awaiting approval</span>
                                </div>
                            </div>

                            <div class="ua-grid">
                                <section class="ud-card ua-card ua-card--wide">
                                    <header class="ua-card__head">
                                        <h3 class="ua-card__title">Listings added</h3>
                                        <span class="ua-card__meta">Last 12 months</span>
                                    </header>
                                    <apexchart
                                        type="bar"
                                        height="220"
                                        :options="monthlyChartOptions"
                                        :series="monthlyChartSeries"
                                    />
                                </section>

                                <section class="ud-card ua-card">
                                    <header class="ua-card__head">
                                        <h3 class="ua-card__title">Listing status</h3>
                                    </header>
                                    <ul class="ua-bars">
                                        <li v-for="row in listingAnalytics.status" :key="row.label" :title="`${row.label}: ${row.value}`">
                                            <span class="ua-bars__label">{{ row.label }}</span>
                                            <span class="ua-bars__track"><span class="ua-bars__fill" :style="{ width: barWidth(row.value, listingAnalytics.statusMax) }"></span></span>
                                            <span class="ua-bars__value">{{ row.value }}</span>
                                        </li>
                                    </ul>
                                </section>

                                <section class="ud-card ua-card">
                                    <header class="ua-card__head">
                                        <h3 class="ua-card__title">Sale vs Rent</h3>
                                    </header>
                                    <div class="ua-split" :title="`For Sale: ${listingAnalytics.forSale} · For Rent: ${listingAnalytics.forRent}`">
                                        <span class="ua-split__part ua-split__part--sale" :style="{ flexGrow: listingAnalytics.forSale }"></span>
                                        <span class="ua-split__part ua-split__part--rent" :style="{ flexGrow: listingAnalytics.forRent }"></span>
                                    </div>
                                    <div class="ua-split__legend">
                                        <span><i class="ua-dot ua-dot--sale"></i>For Sale <strong>{{ listingAnalytics.forSale }}</strong></span>
                                        <span><i class="ua-dot ua-dot--rent"></i>For Rent <strong>{{ listingAnalytics.forRent }}</strong></span>
                                    </div>

                                    <h4 class="ua-card__subtitle">Top property types</h4>
                                    <ul v-if="listingAnalytics.types.length" class="ua-bars">
                                        <li v-for="row in listingAnalytics.types" :key="row.label" :title="`${row.label}: ${row.value}`">
                                            <span class="ua-bars__label">{{ row.label }}</span>
                                            <span class="ua-bars__track"><span class="ua-bars__fill" :style="{ width: barWidth(row.value, listingAnalytics.typesMax) }"></span></span>
                                            <span class="ua-bars__value">{{ row.value }}</span>
                                        </li>
                                    </ul>
                                    <p v-else class="ua-empty">No listings yet.</p>
                                </section>
                            </div>

                            <section v-if="canViewLeadAnalytics" class="ud-card ua-card">
                                <header class="ua-card__head ua-card__head--tools">
                                    <h3 class="ua-card__title">Leads</h3>
                                    <div class="ua-range">
                                        <input v-model="leadReportFilters.date_from" type="date" aria-label="From date" @change="loadLeadReport" />
                                        <span>to</span>
                                        <input v-model="leadReportFilters.date_to" type="date" aria-label="To date" @change="loadLeadReport" />
                                    </div>
                                </header>
                                <div v-if="leadReportError" class="alert alert-danger py-2 px-3 mb-2">{{ leadReportError }}</div>
                                <div v-if="leadReportLoading" class="ua-empty">Loading leads...</div>
                                <template v-else-if="leadReport">
                                    <div class="ua-perf">
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Assigned</span>
                                            <strong>{{ leadReport.total_assigned_leads }}</strong>
                                        </div>
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Active</span>
                                            <strong>{{ leadReport.active_leads }}</strong>
                                        </div>
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Converted</span>
                                            <strong>{{ leadReport.converted_leads }}</strong>
                                        </div>
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Conversion Rate</span>
                                            <strong>{{ leadConversionRate }}</strong>
                                        </div>
                                    </div>

                                    <div class="ua-grid ua-grid--flush">
                                        <div>
                                            <h4 class="ua-card__subtitle">Leads by stage</h4>
                                            <ul v-if="leadStageRows.length" class="ua-bars">
                                                <li v-for="row in leadStageRows" :key="row.stage_id" :title="`${row.stage_name}: ${row.count}`">
                                                    <span class="ua-bars__label">
                                                        <i class="ua-dot" :style="{ background: row.stage_color || 'var(--color-primary-muted)' }"></i>
                                                        {{ row.stage_name }}
                                                    </span>
                                                    <span class="ua-bars__track"><span class="ua-bars__fill" :style="{ width: barWidth(row.count, leadStageMax) }"></span></span>
                                                    <span class="ua-bars__value">{{ row.count }}</span>
                                                </li>
                                            </ul>
                                            <p v-else class="ua-empty">No leads in this date range.</p>
                                        </div>
                                        <div>
                                            <h4 class="ua-card__subtitle">Latest leads</h4>
                                            <ul v-if="recentLeads.length" class="ua-recent">
                                                <li v-for="lead in recentLeads" :key="lead.id">
                                                    <span class="ua-recent__main">
                                                        <strong>{{ lead.lead_name || '—' }}</strong>
                                                        <small>{{ lead.lead_number }} · {{ formatDate(lead.created_at) }}</small>
                                                    </span>
                                                    <span v-if="lead.stage" class="ua-recent__stage">
                                                        <i class="ua-dot" :style="{ background: lead.stage.color || 'var(--color-primary-muted)' }"></i>
                                                        {{ lead.stage.name }}
                                                    </span>
                                                </li>
                                            </ul>
                                            <p v-else class="ua-empty">No leads yet.</p>
                                        </div>
                                    </div>
                                </template>
                            </section>

                            <section v-if="canViewAgentPerformance" class="ud-card ua-card">
                                <header class="ua-card__head ua-card__head--tools">
                                    <h3 class="ua-card__title">Sales performance</h3>
                                    <div class="ua-range">
                                        <input v-model="salesPerformanceFilters.from_date" type="date" aria-label="From date" @change="loadSalesPerformance" />
                                        <span>to</span>
                                        <input v-model="salesPerformanceFilters.to_date" type="date" aria-label="To date" @change="loadSalesPerformance" />
                                    </div>
                                </header>
                                <div v-if="salesPerformanceError" class="alert alert-danger py-2 px-3 mb-2">{{ salesPerformanceError }}</div>
                                <div v-if="salesPerformanceLoading" class="ua-empty">Loading performance...</div>
                                <template v-else>
                                    <div class="ua-perf">
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Converted Deals</span>
                                            <strong>{{ salesPerformanceSummary.converted_count }}</strong>
                                        </div>
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Deal Value</span>
                                            <strong>{{ formatCurrency(salesPerformanceSummary.total_amount) }}</strong>
                                        </div>
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Agent Commission</span>
                                            <strong>{{ formatCurrency(salesPerformanceSummary.total_agent_commission) }}</strong>
                                        </div>
                                        <div class="ua-perf__item">
                                            <span class="ua-kpi__label">Avg Lead Score</span>
                                            <strong>{{ salesPerformanceSummary.avg_lead_score ?? '—' }}</strong>
                                        </div>
                                    </div>
                                    <h4 class="ua-card__subtitle">Lead quality of converted deals</h4>
                                    <ul v-if="salesPerformanceDeals.length" class="ua-bars">
                                        <li v-for="row in leadQualityRows" :key="row.label" :title="`${row.label}: ${row.value}`">
                                            <span class="ua-bars__label">{{ row.label }}</span>
                                            <span class="ua-bars__track"><span class="ua-bars__fill" :style="{ width: barWidth(row.value, leadQualityMax) }"></span></span>
                                            <span class="ua-bars__value">{{ row.value }}</span>
                                        </li>
                                    </ul>
                                    <p v-else class="ua-empty">No converted deals in this date range.</p>
                                </template>
                            </section>
                        </div>
                    </div>

                    <!-- Properties Tab -->
                    <div class="tab-pane fade" id="properties" role="tabpanel" v-if="isAgent">
                        <div class="properties-section">
                            <div class="card border-0 shadow-sm">
                              
                                <div class="card-body">
                                    <!-- Loading State -->
                                    <div v-if="agentPropertiesLoading" class="text-center py-5">
                                        <div class="spinner-border text-primary mb-3" role="status"></div>
                                        <p class="text-muted">Loading properties...</p>
                                    </div>
                                    
                                    <!-- Properties Grid -->
                                    <div v-else-if="agentProperties.length > 0">
                                        

                                        <div class="ud-prop-grid">
                                            <div
                                                v-for="property in filteredProperties"
                                                :key="property.id"
                                            >
                                                <MobileListingCard
                                                    v-if="isMobileViewport"
                                                    :property="property"
                                                    fallback-image="/assets/images/a.jpeg"
                                                />
                                                <PropertyListingCard
                                                    v-else
                                                    :property="property"
                                                    @open="(event) => openPropertyDetailsFromClick(event, property.id)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- No Properties -->
                                    <div v-else class="text-center py-5">
                                        <div class="mb-3">
                                            <i class="ri-home-4-line display-1 text-muted"></i>
                                        </div>
                                        <h5 class="text-muted">No Properties Found</h5>
                                        <p class="text-muted">This agent doesn't have any properties assigned yet.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Tab -->
                    <!-- <div class="tab-pane fade" id="performance" role="tabpanel" v-if="isAgent && canViewAgentPerformance">
                        <div class="card border-0 shadow-sm perf-card">
                            <div class="card-body">
                                <div class="perf-toolbar">
                                    <div class="perf-date">
                                        <label>From</label>
                                        <input v-model="salesPerformanceFilters.from_date" type="date" @change="loadSalesPerformance" />
                                    </div>
                                    <div class="perf-date">
                                        <label>To</label>
                                        <input v-model="salesPerformanceFilters.to_date" type="date" @change="loadSalesPerformance" />
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" :disabled="salesPerformanceLoading" @click="loadSalesPerformance">
                                        <span v-if="salesPerformanceLoading">Refreshing...</span>
                                        <span v-else>Refresh</span>
                                    </button>
                                </div>

                                <div v-if="salesPerformanceError" class="alert alert-danger py-2 px-3 mt-3 mb-0">
                                    {{ salesPerformanceError }}
                                </div>

                                <div class="perf-kpis">
                                    <div class="perf-kpi">
                                        <span class="perf-kpi-label">Converted Deals</span>
                                        <strong class="perf-kpi-value">{{ salesPerformanceSummary.converted_count }}</strong>
                                    </div>
                                    <div class="perf-kpi">
                                        <span class="perf-kpi-label">Avg Lead Score</span>
                                        <strong class="perf-kpi-value">{{ salesPerformanceSummary.avg_lead_score ?? '—' }}</strong>
                                    </div>
                                    <div class="perf-kpi">
                                        <span class="perf-kpi-label">Deal Value</span>
                                        <strong class="perf-kpi-value">{{ formatCurrency(salesPerformanceSummary.total_amount) }}</strong>
                                    </div>
                                    <div class="perf-kpi">
                                        <span class="perf-kpi-label">Agent Commission</span>
                                        <strong class="perf-kpi-value">{{ formatCurrency(salesPerformanceSummary.total_agent_commission) }}</strong>
                                    </div>
                                </div>

                                <div v-if="salesPerformanceDeals.length" class="perf-chart-row">
                                    <apexchart type="donut" height="220" :options="perfDonutOptions" :series="perfDonutSeries" />
                                </div>

                                <div v-if="salesPerformanceLoading" class="text-center text-muted py-4">Loading performance data...</div>
                                <div v-else-if="!salesPerformanceDeals.length" class="text-center text-muted py-4">
                                    No converted deals found in the selected date range.
                                </div>
                                <div v-else class="table-responsive mt-3">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Lead</th>
                                                <th>Deal #</th>
                                                <th class="text-end">Lead Score</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">Agent Commission</th>
                                                <th>Converted</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="deal in salesPerformanceDeals" :key="deal.deal_id">
                                                <td>{{ deal.lead_name || '—' }}</td>
                                                <td>{{ deal.deal_number || `#${deal.deal_id}` }}</td>
                                                <td class="text-end">{{ deal.lead_score ?? '—' }}</td>
                                                <td class="text-end">{{ formatCurrency(deal.deal_amount, deal.currency) }}</td>
                                                <td class="text-end">{{ formatCurrency(deal.agent_commission, deal.currency) }}</td>
                                                <td>{{ formatDate(deal.converted_at) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div> -->

                    <!-- Attendance Tab -->
                    <div class="tab-pane fade" :class="{ 'show active': !isAgent }" id="attendance" role="tabpanel" v-if="canViewAgentAttendance">
                        <div class="card border-0 shadow-sm perf-card">
                            <div class="card-body">
                                <UserAttendanceCarousel :user-id="user.id" />
                            </div>
                        </div>
                    </div>

                    <!-- Evaluations Tab -->
                    <div class="tab-pane fade" id="evaluations" role="tabpanel" v-if="canViewEvaluations">
                        <div class="card border-0 shadow-sm perf-card">
                            <div class="card-body">
                                <div v-if="userEvaluationsLoading" class="text-center text-muted py-4">Loading evaluations...</div>
                                <div v-else-if="!userEvaluations.length" class="text-center text-muted py-4">
                                    No submitted evaluations yet.
                                </div>
                                <div v-else class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Milestone</th>
                                                <th>Submitted</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="ev in userEvaluations" :key="ev.id">
                                                <td>{{ ev.milestone_months }}-month review</td>
                                                <td>{{ formatDate(ev.submitted_at) }}</td>
                                                <td class="text-end">
                                                    <a v-if="ev.pdf_url" :href="ev.pdf_url" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                                        <i class="ri-download-line me-1"></i>PDF
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <div v-else class="card">
                <div class="card-body text-center py-5">
                    <i class="ri-user-unfollow-line display-1 text-danger mb-3"></i>
                    <h4>User Not Found</h4>
                    <p class="text-muted mb-4">The user you're looking for doesn't exist or has been removed.</p>
                    <button class="btn btn-primary" @click="$router.push('/users')">
                        <i class="ri-arrow-left-line me-2"></i>
                        Back to Users List
                    </button>
                </div>
            </div>
        </div>

        <!-- Vacation Mode Modal -->
        <div v-if="showVacationModal" class="vacation-modal-overlay" @click.self="closeVacationModal">
            <div class="vacation-modal" @click.stop>
                <div class="vacation-modal-head">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-suitcase-line"></i>
                        <h6 class="mb-0">Vacation Mode</h6>
                        <span :class="['vacation-badge ms-2', vacationData.active ? 'on' : 'off']">
                            {{ vacationData.active ? 'On Vacation' : 'Active' }}
                        </span>
                    </div>
                    <button class="vacation-modal-close" @click="closeVacationModal" aria-label="Close">&times;</button>
                </div>

                <div class="vacation-modal-body">
                    <div v-if="vacationLoading" class="vacation-loading">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span class="ms-2">Loading...</span>
                    </div>

                    <div v-else>
                        <p class="text-muted small mb-3" v-if="user">
                            Managing vacation for <strong>{{ user.name }}</strong>.
                        </p>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox"
                                   v-model="vacationData.active"
                                   :disabled="vacationSaving"
                                   id="userVacationSwitch">
                            <label class="form-check-label" for="userVacationSwitch">
                                {{ vacationData.active ? 'Active' : 'Inactive' }}
                            </label>
                        </div>

                        <div v-if="vacationData.active" class="mb-3">
                            <label class="form-label">Delegate Agent <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="vacationData.delegate_id" :disabled="vacationSaving">
                                <option value="">Select a delegate</option>
                                <option v-for="a in delegateOptions" :key="a.id" :value="a.id">
                                    {{ a.name }}
                                </option>
                            </select>
                            <small class="text-muted">This agent will receive new requests while {{ user.name }} is on vacation.</small>
                        </div>

                        <div v-else class="alert alert-light mb-0">
                            Vacation mode is off. Toggle it on to assign a delegate for this user.
                        </div>
                    </div>
                </div>

                <div class="vacation-modal-foot">
                    <button class="btn btn-secondary btn-sm" @click="closeVacationModal" :disabled="vacationSaving">
                        Cancel
                    </button>
                    <button class="btn btn-primary btn-sm"
                            @click="saveUserVacation"
                            :disabled="vacationSaving || vacationLoading || (vacationData.active && !vacationData.delegate_id)">
                        {{ vacationSaving ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import UserAttendanceCarousel from '@/components/Users/UserAttendanceCarousel.vue';
import UserDeviceLocation from '@/components/Users/UserDeviceLocation.vue';
import api from '@/plugins/axios';
import apexchart from 'vue3-apexcharts';
import { openPropertyDetailsFromClick } from '@/composables/usePropertyDetailsModal';
import { hidesBedsBaths } from '@/composables/listingPropertyType';
import { useMobileNavigation } from '@/composables/useMobileNavigation.js';
import { publishPageHeaderTabs, clearPageHeaderTabs } from '@/composables/usePageHeaderTabs.js';
import MobileListingCard from '@/components/listings/MobileListingCard.vue';
import PropertyListingCard from '@/components/listings/PropertyListingCard.vue';

export default {
    name: 'ViewUser',
    components: {
        Breadcrumb,
        UserAttendanceCarousel,
        UserDeviceLocation,
        apexchart,
        MobileListingCard,
        PropertyListingCard,
    },
    setup() {
        const { isMobileViewport } = useMobileNavigation();
        return { isMobileViewport };
    },
    data() {
        return {
            loading: false,
            user: null,
            teamMembers: [],
            userPermissions: [],
            error: null,

            showAllTeamMembers: false,

            // Agent Properties
            agentProperties: [],
            agentPropertiesLoading: false,
            propertyFilter: 'all',
            sortBy: 'newest',
             propertyIcon : '/assets/icons/property-icon.svg',
            bedIcon : '/assets/icons/bedroom-icon.svg',
            bathIcon : '/assets/icons/bathroom-icon.svg',
            sqftIcon : '/assets/icons/area-size.svg',
            userPlaceholder:'/assets/images/user.png',

            // Vacation mode (manager-of-listing-team flow)
            showVacationModal: false,
            vacationLoading: false,
            vacationSaving: false,
            vacationData: {
                active: false,
                delegate_id: '',
            },
            delegateOptions: [],

            // Sales Performance (agents only)
            salesPerformanceLoading: false,
            salesPerformanceError: '',
            salesPerformanceDeals: [],
            salesPerformanceSummary: {
                converted_count: 0,
                total_amount: 0,
                total_agent_commission: 0,
                avg_lead_score: null,
            },
            salesPerformanceFilters: this.defaultSalesRange(),

            // Evaluations
            userEvaluations: [],
            userEvaluationsLoading: false,

            // Section tab currently shown (mirrors the Bootstrap tabs)
            activeSection: null,

            // Lead report (Overview tab)
            leadReport: null,
            leadReportLoading: false,
            leadReportError: '',
            leadReportFilters: (() => {
                const { from_date, to_date } = this.defaultSalesRange();
                return { date_from: from_date, date_to: to_date };
            })(),
        };
    },
    computed: {
        /** Overview tab: listing figures derived from the agent's properties. */
        listingAnalytics() {
            const list = this.agentProperties;
            // Buckets are exclusive, in the same priority the property badges use
            const status = { Active: 0, Sold: 0, Draft: 0, Archived: 0, Inactive: 0 };
            const types = {};
            let forSale = 0;
            let forRent = 0;
            let hotDeals = 0;
            let needsApproval = 0;
            let portfolioValue = 0;
            let priced = 0;
            let priceSum = 0;

            for (const p of list) {
                if (p.status === 'converted') status.Sold += 1;
                else if (p.status === 'draft') status.Draft += 1;
                else if (p.is_archived) status.Archived += 1;
                else if (!p.is_active) status.Inactive += 1;
                else {
                    status.Active += 1;
                    portfolioValue += Number(p.price) || 0;
                }

                const purpose = String(p.listing_status || '').toLowerCase();
                if (purpose === 'sale') forSale += 1;
                else if (purpose === 'rent') forRent += 1;

                if (p.is_hot_deal === 'Yes') hotDeals += 1;
                if (!p.approved && p.status === 'published') needsApproval += 1;

                const price = Number(p.price);
                if (price > 0) {
                    priced += 1;
                    priceSum += price;
                }

                const type = p.property_type || p.type || 'Other';
                types[type] = (types[type] || 0) + 1;
            }

            const statusRows = Object.entries(status).map(([label, value]) => ({ label, value }));
            const sortedTypes = Object.entries(types)
                .map(([label, value]) => ({ label, value }))
                .sort((a, b) => b.value - a.value);
            // Keep the list short: top five, the rest folded into "Other"
            const typeRows = sortedTypes.slice(0, 5);
            const rest = sortedTypes.slice(5).reduce((sum, row) => sum + row.value, 0);
            if (rest) typeRows.push({ label: 'Other', value: rest });

            return {
                status: statusRows,
                statusMax: Math.max(0, ...statusRows.map((r) => r.value)),
                types: typeRows,
                typesMax: Math.max(0, ...typeRows.map((r) => r.value)),
                forSale,
                forRent,
                hotDeals,
                needsApproval,
                portfolioValue,
                avgPrice: priced ? priceSum / priced : 0,
            };
        },

        /** Listings created per month over the last 12 months. */
        monthlyListings() {
            const now = new Date();
            const months = [];
            for (let i = 11; i >= 0; i -= 1) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                months.push({
                    key: `${d.getFullYear()}-${d.getMonth()}`,
                    label: d.toLocaleDateString('en-US', { month: 'short' }),
                    title: d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
                    count: 0,
                });
            }
            const byKey = Object.fromEntries(months.map((m) => [m.key, m]));
            for (const p of this.agentProperties) {
                const created = new Date(p.created_at);
                if (Number.isNaN(created.getTime())) continue;
                const bucket = byKey[`${created.getFullYear()}-${created.getMonth()}`];
                if (bucket) bucket.count += 1;
            }
            return months;
        },

        monthlyChartSeries() {
            return [{ name: 'Listings added', data: this.monthlyListings.map((m) => m.count) }];
        },

        monthlyChartOptions() {
            const css = getComputedStyle(document.documentElement);
            const brand = css.getPropertyValue('--color-primary').trim() || '#733E87';
            const muted = css.getPropertyValue('--color-text-muted').trim() || '#6b7280';
            const grid = css.getPropertyValue('--color-border').trim() || '#e8e6ee';
            const months = this.monthlyListings;
            return {
                chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: muted, parentHeightOffset: 0 },
                colors: [brand],
                plotOptions: {
                    bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end' },
                },
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: {
                    borderColor: grid,
                    strokeDashArray: 0,
                    xaxis: { lines: { show: false } },
                    padding: { left: 4, right: 4 },
                },
                xaxis: {
                    categories: months.map((m) => m.label),
                    axisTicks: { show: false },
                    axisBorder: { color: grid },
                },
                yaxis: {
                    min: 0,
                    forceNiceScale: true,
                    labels: { formatter: (v) => Math.round(v) },
                },
                states: { hover: { filter: { type: 'darken', value: 0.85 } } },
                tooltip: {
                    x: { formatter: (_, { dataPointIndex }) => months[dataPointIndex]?.title || '' },
                    y: { formatter: (v) => `${v} ${v === 1 ? 'listing' : 'listings'}` },
                },
            };
        },

        /** The lead report API only answers admins / super admins, or the user about themself. */
        canViewLeadAnalytics() {
            if (!this.user || !this.isAgent) return false;
            try {
                const me = JSON.parse(localStorage.getItem('user') || 'null');
                if (!me) return false;
                const roles = me.roles || [];
                return roles.includes('super_admin') || roles.includes('admin') || Number(me.id) === Number(this.user.id);
            } catch {
                return false;
            }
        },

        leadStageRows() {
            // Pipeline order from the API; empty stages left out to keep the list short
            return (this.leadReport?.leads_by_stage || []).filter((row) => row.count > 0);
        },

        leadStageMax() {
            return Math.max(0, ...this.leadStageRows.map((r) => r.count));
        },

        recentLeads() {
            return (this.leadReport?.recent_leads || []).slice(0, 5);
        },

        leadConversionRate() {
            const assigned = Number(this.leadReport?.total_assigned_leads) || 0;
            if (!assigned) return '—';
            const rate = ((Number(this.leadReport.converted_leads) || 0) / assigned) * 100;
            return `${rate.toFixed(1)}%`;
        },

        leadQualityRows() {
            const b = this.perfScoreBuckets;
            return [
                { label: 'Hot (70+)', value: b.hot },
                { label: 'Warm (40–69)', value: b.warm },
                { label: 'Cold (<40)', value: b.cold },
            ];
        },

        leadQualityMax() {
            return Math.max(0, ...this.leadQualityRows.map((r) => r.value));
        },

        /** Same tabs as the in-page menu, for the top navbar on desktop. */
        headerTabs() {
            const tabs = [];
            if (this.isAgent) tabs.push({ id: 'overview', label: 'Overview' });
            if (this.isAgent) tabs.push({ id: 'properties', label: 'Properties', count: this.agentProperties.length });
            if (this.canViewAgentAttendance) tabs.push({ id: 'attendance', label: 'Attendance' });
            if (this.canViewEvaluations) tabs.push({ id: 'evaluations', label: 'Evaluations', count: this.userEvaluations.length });
            return tabs;
        },

        isAgent() {
            if (!this.user) return false;
            
            const role = (this.user.role_name || '').toLowerCase();
            
            const agentRoles = [
                'agent', 
                'sales',
                'sales agent', 
                'property agent', 
                'real estate agent', 
                'listing agent',
                'broker',
                'salesperson'
            ];
            
            return agentRoles.some(agentRole => role.includes(agentRole));
        },
        
        filteredProperties() {
            let properties = [...this.agentProperties];
            
            switch (this.propertyFilter) {
                case 'active':
                    properties = properties.filter(p => p.is_active && !p.is_archived && p.status !== 'converted' && p.status !== 'draft');
                    break;
                case 'sold':
                    properties = properties.filter(p => p.status === 'converted');
                    break;
                case 'draft':
                    properties = properties.filter(p => p.status === 'draft');
                    break;
            }
            
            switch (this.sortBy) {
                case 'newest':
                    properties.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    break;
                case 'oldest':
                    properties.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                    break;
                case 'price_high':
                    properties.sort((a, b) => (b.price || 0) - (a.price || 0));
                    break;
                case 'price_low':
                    properties.sort((a, b) => (a.price || 0) - (b.price || 0));
                    break;
            }
            
            return properties;
        },
        
        totalAgentProperties() {
            return this.agentProperties.length;
        },
        
        activeAgentProperties() {
            return this.agentProperties.filter(p => p.is_active && !p.is_archived && p.status !== 'converted' && p.status !== 'draft').length;
        },
        
        forSaleAgentProperties() {
            return this.agentProperties.filter(p => p.listing_status === 'Sale').length;
        },
        
        soldAgentProperties() {
            return this.agentProperties.filter(p => p.status === 'converted').length;
        },

        /** Device location is Super Admin only (matches API redaction). */
        isSuperAdminViewer() {
            try {
                const me = JSON.parse(localStorage.getItem('user') || 'null');
                return (me?.roles || []).includes('super_admin');
            } catch {
                return false;
            }
        },

        /** Active in the last 15 minutes (from last_login_at / presence touch). */
        isUserOnline() {
            if (!this.user?.last_login_at) return false;
            const raw = String(this.user.last_login_at).trim();
            let normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
            if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(normalized)) normalized += '+04:00';
            const lastLogin = new Date(normalized);
            if (Number.isNaN(lastLogin.getTime())) return false;
            const diffMinutes = (Date.now() - lastLogin.getTime()) / (1000 * 60);
            return diffMinutes >= 0 && diffMinutes <= 15;
        },

        /** Current logged-in user can manage vacation for the viewed user.
         *  Allowed: admin / super_admin, OR manager with listing_team=1 (the
         *  backend additionally verifies the viewed user is in their hierarchy).
         */
        canManageVacation() {
            if (!this.user) return false;
            try {
                const me = JSON.parse(localStorage.getItem('user') || 'null');
                if (!me || Number(me.id) === Number(this.user.id)) return false;
                const roles = me.roles || [];
                if (roles.includes('super_admin') || roles.includes('admin')) return true;
                if (roles.includes('manager') && Number(me.is_listing_team) === 1) return true;
                return false;
            } catch {
                return false;
            }
        },

        /** Attendance tab: admins, the agent's own direct manager (user.parent_id),
         *  or anyone holding the 'view-agent-attendance' permission. Non-agent
         *  profiles are unrestricted (attendance there isn't the "agent" concept).
         */
        canViewAgentAttendance() {
            if (!this.user) return false;
            if (!this.isAgent) return true;
            return this.$hasPermission('view-agent-attendance') || this.isAdminOrDirectManager();
        },

        /** Performance tab: admins, the agent's own direct manager, or anyone
         *  holding the 'view-agent-performance' permission.
         */
        canViewAgentPerformance() {
            if (!this.user) return false;
            return this.$hasPermission('view-agent-performance') || this.isAdminOrDirectManager();
        },

        perfScoreBuckets() {
            const buckets = { hot: 0, warm: 0, cold: 0 };
            for (const deal of this.salesPerformanceDeals) {
                const score = Number(deal.lead_score);
                if (Number.isNaN(score)) continue;
                if (score >= 70) buckets.hot += 1;
                else if (score >= 40) buckets.warm += 1;
                else buckets.cold += 1;
            }
            return buckets;
        },

        perfDonutSeries() {
            const b = this.perfScoreBuckets;
            return [b.hot, b.warm, b.cold];
        },

        perfDonutOptions() {
            return {
                labels: ['Hot Leads', 'Warm Leads', 'Cold Leads'],
                colors: ['#ef4444', '#f59e0b', '#3b82f6'],
                legend: { position: 'bottom', fontSize: '12px' },
                dataLabels: { enabled: true },
            };
        },

        /** Evaluations tab: admins, the viewed user's own direct manager, or anyone
         *  holding the 'view-evaluations' permission — same pattern as
         *  canViewAgentAttendance/canViewAgentPerformance.
         */
        canViewEvaluations() {
            if (!this.user) return false;
            return this.$hasPermission('view-evaluations') || this.isAdminOrDirectManager();
        },
    },
    watch: {
        user(value) {
            // Matches the tab marked active in the template
            if (value && !this.activeSection) {
                this.activeSection = this.isAgent ? 'overview' : (this.canViewAgentAttendance ? 'attendance' : null);
            }
        },
        headerTabs() {
            this.publishHeaderTabs();
        },
        activeSection() {
            this.publishHeaderTabs();
        },
    },
    created() {
        this.headerTabsOwner = Symbol('user-detail');
    },
    mounted() {
        this.fetchUser();
        document.addEventListener('shown.bs.tab', this.onSectionTabShown);
    },
    beforeUnmount() {
        document.removeEventListener('shown.bs.tab', this.onSectionTabShown);
        clearPageHeaderTabs(this.headerTabsOwner);
    },
    methods: {
        hidesBedsBaths,
        barWidth(value, max) {
            if (!max || !value) return '0%';
            return `${Math.max(2, (value / max) * 100)}%`;
        },
        formatCompactAed(value) {
            if (!value) return 'AED 0';
            return `AED ${new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(value)}`;
        },
        publishHeaderTabs() {
            publishPageHeaderTabs(this.headerTabsOwner, {
                tabs: this.headerTabs,
                active: this.activeSection,
                select: this.selectSection,
            });
        },
        /** Navbar pick: click the real tab button so Bootstrap and its @click run as usual. */
        selectSection(id) {
            document.getElementById(`${id}-tab`)?.click();
        },
        onSectionTabShown(event) {
            const tab = event.target;
            if (!tab?.closest?.('#userTab')) return;
            this.activeSection = tab.id.replace(/-tab$/, '');
        },
        openPropertyDetailsFromClick,
        formatLastLoginDetail(timestamp) {
            if (!timestamp) return 'Never';
            const raw = String(timestamp).trim();
            let normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
            if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(normalized)) normalized += '+04:00';
            const loginTime = new Date(normalized);
            if (Number.isNaN(loginTime.getTime())) return 'Never';
            const now = new Date();
            const diffMins = Math.floor((now - loginTime) / 60000);
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h ago`;
            return loginTime.toLocaleString('en-US', {
                timeZone: 'Asia/Dubai',
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
        },
        async fetchUser() {
            try {
                this.loading = true;
                this.error = null;
                
                const token = localStorage.getItem('token');
                const userId = this.$route.params.id;
                
                const response = await fetch(API_ENDPOINTS.USER_BY_ID(userId), {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.user = data.data || data;
                    
                    const promises = [];
                    
                    if (this.isManager(this.user) || this.isTeamLead(this.user)) {
                        promises.push(this.fetchTeamMembers(userId).catch(e => {
                            console.warn('⚠️ Team members fetch failed:', e);
                        }));
                    }
                    
                    promises.push(this.fetchUserPermissions(userId));
                    
                    if (this.isAgent) {
                        promises.push(this.fetchAgentProperties());
                        if (this.canViewAgentPerformance) {
                            promises.push(this.loadSalesPerformance());
                        }
                        if (this.canViewLeadAnalytics) {
                            promises.push(this.loadLeadReport());
                        }
                    }

                    await Promise.allSettled(promises);
                    
                } else if (response.status === 404) {
                    this.error = 'User not found';
                } else {
                    throw new Error(`Failed to fetch user: ${response.status} ${response.statusText}`);
                }
            } catch (error) {
                console.error('❌ Error fetching user:', error);
                this.error = error.message;
                this.showNotification('❌ Failed to load user data', 'error');
            } finally {
                this.loading = false;
            }
        },

        async fetchTeamMembers(managerId) {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${API_ENDPOINTS.USERS}?parent_id=${managerId}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.teamMembers = data.data || data;
                }
            } catch (error) {
                console.error('Error fetching team members:', error);
            }
        },

        async fetchUserPermissions(userId) {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${API_ENDPOINTS.USER_PERMISSIONS(userId)}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.userPermissions = data.data || data.permissions || [];
                } else {
                    this.userPermissions = [];
                }
            } catch (error) {
                this.userPermissions = [];
            }
        },

        async fetchAgentProperties() {
            if (!this.isAgent) return;
            
            try {
                this.agentPropertiesLoading = true;
                const token = localStorage.getItem('token');
                const agentId = this.user.id;

                const endpoint = `/api/listings/properties?agent_id=${agentId}`;
                const response = await fetch(endpoint, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    this.agentProperties = [];
                    return;
                }

                const data = await response.json();

                if (data.status && Array.isArray(data.data)) {
                    this.agentProperties = data.data;
                } else if (Array.isArray(data)) {
                    this.agentProperties = data;
                } else {
                    this.agentProperties = [];
                }

            } catch (error) {
                console.error('❌ Error in fetchAgentProperties:', error);
                this.agentProperties = [];
            } finally {
                this.agentPropertiesLoading = false;
            }
        },

        /** Open the modal and lazily load vacation state + delegate options. */
        async openVacationModal() {
            this.showVacationModal = true;
            await Promise.allSettled([
                this.fetchUserVacation(),
                this.delegateOptions.length === 0 ? this.fetchDelegateOptions() : Promise.resolve(),
            ]);
        },

        closeVacationModal() {
            if (this.vacationSaving) return;
            this.showVacationModal = false;
        },

        /** Read the viewed user's current vacation state. */
        async fetchUserVacation() {
            if (!this.user?.id) return;
            try {
                this.vacationLoading = true;
                const token = localStorage.getItem('token');
                const resp = await fetch(`/api/listings/agent/${this.user.id}/vacation-mode`, {
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
                });
                if (!resp.ok) return;
                const json = await resp.json();
                const d = json.data || {};
                this.vacationData.active = !!d.on_vacation;
                this.vacationData.delegate_id = d.delegate_agent_id || '';
            } catch (e) {
                console.error('Failed to load vacation:', e);
            } finally {
                this.vacationLoading = false;
            }
        },

        /** Load sales agents available as delegates (manager's hierarchy). */
        async fetchDelegateOptions() {
            try {
                const token = localStorage.getItem('token');
                const resp = await fetch('/api/listings/agents?role=sales&listings=true', {
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
                });
                if (!resp.ok) return;
                const json = await resp.json();
                const all = Array.isArray(json.data) ? json.data : [];
                // Don't let the manager set the viewed user as their own delegate.
                this.delegateOptions = all.filter(a => Number(a.id) !== Number(this.user.id));
            } catch (e) {
                this.delegateOptions = [];
            }
        },

        /** Save vacation toggle / delegate for the viewed user. */
        async saveUserVacation() {
            if (this.vacationData.active && !this.vacationData.delegate_id) {
                this.showNotification('Please select a delegate agent', 'warning');
                return;
            }
            try {
                this.vacationSaving = true;
                const token = localStorage.getItem('token');
                const resp = await fetch(`/api/listings/agent/${this.user.id}/vacation`, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        active: this.vacationData.active,
                        delegate_id: this.vacationData.active ? this.vacationData.delegate_id : null,
                    }),
                });
                const json = await resp.json();
                if (!resp.ok || json.status === false) {
                    throw new Error(json.message || 'Failed to update vacation');
                }
                this.showNotification('Vacation mode updated', 'success');
                this.showVacationModal = false;
            } catch (e) {
                console.error('Failed to save vacation:', e);
                this.showNotification(e.message || 'Failed to update vacation', 'error');
            } finally {
                this.vacationSaving = false;
            }
        },

        getPropertyImage(property) {
            if (!property) return this.getDefaultPropertyImage();
            
            if (property.main_image) {
                return property.main_image;
            }
            
            if (property.hero_image_path) {
                return property.hero_image_path.includes('http') 
                    ? property.hero_image_path 
                    : `/storage/${property.hero_image_path}`;
            }
            
            if (property.hero_image_url) {
                return property.hero_image_url;
            }
            
            if (property.gallery_images && Array.isArray(property.gallery_images) && property.gallery_images.length > 0) {
                const firstImage = property.gallery_images[0];
                return firstImage.image_url || firstImage.url || firstImage;
            }
            
            return this.getDefaultPropertyImage();
        },
        
        getDefaultPropertyImage() {
            return 'https://via.placeholder.com/400x300/e9ecef/6c757d?text=Property';
        },
        
        handlePropertyImageError(event) {
            event.target.src = this.getDefaultPropertyImage();
        },
        
        handlePropertyImageLoad(event) {
            event.target.classList.add('loaded');
        },
        
        getPropertyLocation(property) {
            if (!property) return 'Location not specified';
            
            if (property.area && property.area.name) {
                return property.area.name;
            }
            
            if (property.area_name) {
                return property.area_name;
            }
            
            if (property.area) {
                return property.area;
            }
            
            if (property.location) {
                return property.location;
            }
            
            return 'Location not specified';
        },
        
        getPropertyType(property) {
            if (property.property_type ) {
                const type = property.property_type;
                return type.length > 8 ? type.substring(0, 8) + '...' : type;
            }
            if (property.type) {
                const type = property.type;
                return type.length > 8 ? type.substring(0, 8) + '...' : type;
            }
            return 'Property';
        },
        
        getAreaUnit(property) {
            return property.size_sqft ? 'Sqft' : 'Sqm';
        },
        
        formatPrice(price) {
            if (!price || price === 0) return '0';
            return new Intl.NumberFormat().format(price);
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        isTeamLead(user) {
            return user.role_name?.toLowerCase().includes('team') || 
                   user.roles?.some(role => role.toLowerCase().includes('team'));
        },

        isManager(user) {
            return user.role_name?.toLowerCase().includes('manager') || 
                   user.roles?.some(role => role.toLowerCase().includes('manager'));
        },

        getSupervisorType(user) {
            if (this.isManager(user)) return 'Manager';
            if (this.isTeamLead(user)) return 'Team Lead';
            return 'Supervisor';
        },

        handleImageError(event) {
            event.target.src = this.userPlaceholder;
        },

        showNotification(message, type = 'info') {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            }
        },

        /** True if the current logged-in user is admin/super_admin, or the
         *  viewed user's own direct manager (user.parent_id).
         */
        isAdminOrDirectManager() {
            if (!this.user) return false;
            try {
                const me = JSON.parse(localStorage.getItem('user') || 'null');
                if (!me) return false;
                const roles = me.roles || [];
                if (roles.includes('super_admin') || roles.includes('admin')) return true;
                return Number(me.id) === Number(this.user.parent_id);
            } catch {
                return false;
            }
        },

        defaultSalesRange() {
            const now = new Date();
            const toStr = (d) => d.toISOString().slice(0, 10);
            const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
            return { from_date: toStr(firstOfMonth), to_date: toStr(now) };
        },

        formatCurrency(value, currency = 'AED') {
            if (value === null || value === undefined || value === '') return '—';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency || 'AED',
                maximumFractionDigits: 0,
            }).format(Number(value) || 0);
        },

        async loadSalesPerformance() {
            if (!this.user?.id) return;
            this.salesPerformanceLoading = true;
            this.salesPerformanceError = '';
            try {
                const { data } = await api.get('/leads/reports/agent-performance', {
                    params: {
                        from_date: this.salesPerformanceFilters.from_date,
                        to_date: this.salesPerformanceFilters.to_date,
                        agent_id: this.user.id,
                        status: 'completed',
                    },
                });
                const agentData = Array.isArray(data?.data) ? data.data[0] : null;
                this.salesPerformanceDeals = Array.isArray(agentData?.deals) ? agentData.deals : [];
                this.salesPerformanceSummary = {
                    converted_count: Number(agentData?.converted_count) || 0,
                    total_amount: Number(agentData?.total_amount) || 0,
                    total_agent_commission: Number(agentData?.total_agent_commission) || 0,
                    avg_lead_score: agentData?.avg_lead_score ?? null,
                };
            } catch (error) {
                console.error('Error loading sales performance:', error);
                this.salesPerformanceDeals = [];
                this.salesPerformanceSummary = {
                    converted_count: 0,
                    total_amount: 0,
                    total_agent_commission: 0,
                    avg_lead_score: null,
                };
                this.salesPerformanceError = error?.response?.data?.message || 'Failed to load sales performance.';
            } finally {
                this.salesPerformanceLoading = false;
            }
        },

        async loadLeadReport() {
            if (!this.user?.id) return;
            this.leadReportLoading = true;
            this.leadReportError = '';
            try {
                const { data } = await api.get(`/leads/reports/users/${this.user.id}`, {
                    params: {
                        date_from: this.leadReportFilters.date_from,
                        date_to: this.leadReportFilters.date_to,
                    },
                });
                this.leadReport = data?.data?.statistics || null;
            } catch (error) {
                console.error('Error loading lead report:', error);
                this.leadReport = null;
                this.leadReportError = error?.response?.data?.message || 'Failed to load leads.';
            } finally {
                this.leadReportLoading = false;
            }
        },

        async loadUserEvaluations() {
            if (!this.user?.id) return;
            this.userEvaluationsLoading = true;
            try {
                const { data } = await api.get(`/evaluations/for-user/${this.user.id}`);
                this.userEvaluations = data?.data || [];
            } catch (error) {
                console.error('Error loading evaluations:', error);
                this.userEvaluations = [];
            } finally {
                this.userEvaluationsLoading = false;
            }
        },
    }
};
</script>

<style scoped>
/* ============ BASE STYLES ============ */
.dashboard-main-body {
    padding: 1rem;
    min-height: calc(100vh - 60px);
    background: var(--color-background);
}

.user-details-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* ============ LAYOUT: PROFILE SIDEBAR + CONTENT ============ */
.ud-layout {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.ud-side {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
}

.tab-content.ud-main {
    min-width: 0;
    padding-top: 0;
}

.ud-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 6px 20px rgba(var(--color-primary-rgb), 0.06);
}

/* ============ PROFILE CARD ============ */
.ud-profile {
    overflow: hidden;
}

.ud-profile__cover {
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    height: 88px;
    padding: 10px 12px;
    background:
        radial-gradient(circle at 90% -40%, rgba(212, 175, 55, 0.6), transparent 50%),
        radial-gradient(circle at 0% 140%, rgba(255, 255, 255, 0.22), transparent 45%),
        var(--gradient-primary);
    color: var(--color-on-primary);
}

/* Soft dot texture fading in from the right edge */
.ud-profile__cover::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.18) 1px, transparent 1px);
    background-size: 14px 14px;
    -webkit-mask-image: linear-gradient(to left, #000, transparent 70%);
    mask-image: linear-gradient(to left, #000, transparent 70%);
    pointer-events: none;
}

.ud-profile__eyebrow,
.ud-presence {
    position: relative;
    z-index: 1;
}

.ud-profile__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    line-height: 1.6;
    text-transform: uppercase;
    opacity: 0.92;
}

.ud-presence {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.28);
    backdrop-filter: blur(6px);
}

.ud-presence__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.ud-presence.is-online .ud-presence__dot {
    background: #4ade80;
    box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.3);
}

.ud-presence.is-offline .ud-presence__dot {
    background: #fca5a5;
}

.ud-profile__head {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin-top: -48px;
    padding: 0 14px 12px;
    text-align: center;
}

/* Gradient ring around the avatar */
.ud-avatar {
    position: relative;
    padding: 3px;
    border-radius: 50%;
    background: conic-gradient(
        from 210deg,
        var(--color-primary-glow),
        var(--color-primary),
        var(--color-primary-bright),
        var(--color-primary-glow)
    );
    box-shadow: 0 8px 20px rgba(var(--color-primary-rgb), 0.25);
}

.ud-avatar img {
    display: block;
    width: 92px;
    height: 92px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid var(--color-surface);
    background: var(--color-surface);
}

.ud-avatar__online {
    position: absolute;
    right: 6px;
    bottom: 6px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #22c55e;
    border: 2px solid var(--color-surface);
}

.ud-profile__name {
    margin: 4px 0 0 !important;
    font-size: 1.125rem !important;
    font-weight: 700;
    line-height: 1.25 !important;
    color: var(--color-primary-ink);
    word-break: break-word;
}

.ud-chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 5px;
}

.ud-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: capitalize;
    color: var(--color-text-nav);
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
}

.ud-chip i {
    font-size: 0.8125rem;
}

.ud-chip--brand {
    color: var(--color-on-primary);
    background: var(--gradient-primary);
    border-color: transparent;
}

.ud-chip--success {
    color: #166534;
    background: #dcfce7;
    border-color: #bbf7d0;
}

.ud-chip--danger {
    color: #991b1b;
    background: #fee2e2;
    border-color: #fecaca;
}

.ud-chip__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.ud-actions {
    display: flex;
    gap: 6px;
    padding: 0 14px 12px;
}

.ud-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex: 1;
    height: 34px;
    padding: 0 12px;
    border-radius: 9px;
    border: 1px solid transparent;
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.2s;
}

.ud-btn i {
    font-size: 1rem;
}

.ud-btn--icon {
    flex: 0 0 34px;
    padding: 0;
    color: var(--color-text-nav);
    background: var(--color-surface);
    border-color: var(--color-border);
}

.ud-btn--icon:hover {
    color: var(--color-primary);
    background: var(--color-primary-soft);
    border-color: var(--color-primary-border);
}

.ud-btn--soft {
    color: var(--color-primary);
    background: var(--color-primary-soft);
    border-color: var(--color-primary-border);
}

.ud-btn--soft:hover {
    background: var(--color-primary-border);
}

.ud-btn--primary {
    color: var(--color-on-primary);
    background: var(--gradient-primary);
    box-shadow: 0 6px 14px rgba(var(--color-primary-rgb), 0.22);
}

.ud-btn--primary:hover {
    background: var(--gradient-primary-hover);
    transform: translateY(-1px);
}

.ud-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
    padding: 0 14px 12px;
}

.ud-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 7px 4px;
    border-radius: 10px;
    background: var(--color-primary-soft);
    border: 1px solid var(--color-primary-border);
}

.ud-stat__value {
    font-size: 1.0625rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--color-primary);
}

.ud-stat__label {
    font-size: 0.6875rem;
    color: var(--color-text-muted);
}

.ud-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin: 0;
    padding: 6px;
    list-style: none;
    border-top: 1px solid var(--color-border);
}

.ud-info li {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 7px 8px;
}

.ud-info li + li {
    border-top: 1px dashed var(--color-border);
}



.ud-info__icon {
    display: inline-flex;
    justify-content: center;
    flex-shrink: 0;
    width: 18px;
    font-size: 1rem;
    color: var(--color-primary);
}



.ud-info__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.ud-info__label {
    font-size: 0.625rem;
    font-weight: 600;
    line-height: 1.4;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.ud-info__value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-primary-ink);
    text-decoration: none;
}

a.ud-info__value:hover {
    color: var(--color-primary);
}

.ud-location {
    padding: 12px;
}

/* ============ CONTENT CARDS ============ */
.ud-main .card {
    border: 1px solid var(--color-border) !important;
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 6px 20px rgba(var(--color-primary-rgb), 0.06) !important;
}

.ud-main .card:hover {
    transform: none;
}

.ud-main .card-body {
    padding: 14px;
}

@media (max-width: 1199px) {
    .ud-layout {
        grid-template-columns: 270px minmax(0, 1fr);
    }
}

@media (max-width: 991px) {
    .ud-layout {
        grid-template-columns: 1fr;
    }

    .ud-info {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .ud-info {
        grid-template-columns: 1fr;
    }
}

/* ============ SECTION MENU (TABS) ============ */
.ud-nav {
    padding: 6px;
}

.ud-nav__list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ud-nav__list .nav-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    margin: 0;
    padding: 6px 8px 6px 6px;
    border: none;
    border-radius: 10px;
    background: transparent;
    color: var(--color-text-nav);
    font-size: 0.8125rem;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
    transition: background 0.2s, color 0.2s;
}

.ud-nav__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    font-size: 1rem;
    color: var(--color-primary);
    background: var(--color-surface-muted);
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
}

.ud-nav__label {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ud-nav__badge {
    min-width: 20px;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1.5;
    text-align: center;
    color: var(--color-primary);
    background: var(--color-primary-soft);
    border: 1px solid var(--color-primary-border);
}

.ud-nav__chevron {
    font-size: 1rem;
    color: var(--color-text-muted);
    transition: transform 0.2s, color 0.2s;
}

.ud-nav__list .nav-link:hover {
    background: var(--color-surface-hover);
    color: var(--color-primary);
}

/* Active: soft tint, gradient accent bar and gradient icon tile */
.ud-nav__list .nav-link.active {
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.ud-nav__list .nav-link.active::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 8px;
    bottom: 8px;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: var(--gradient-primary);
}

.ud-nav__list .nav-link.active .ud-nav__icon {
    color: var(--color-on-primary);
    background: var(--gradient-primary);
    box-shadow: 0 4px 10px rgba(var(--color-primary-rgb), 0.25);
}

.ud-nav__list .nav-link.active .ud-nav__badge {
    color: var(--color-on-primary);
    background: var(--gradient-primary);
    border-color: transparent;
}

.ud-nav__list .nav-link.active .ud-nav__chevron {
    color: var(--color-primary);
    transform: translateX(2px);
}

@media (max-width: 991px) {
    .ud-profile { order: 0; }
    .ud-location { order: 1; }

    /* Menu becomes a swipeable pill row right above the content */
    .ud-nav {
        order: 2;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .ud-nav::-webkit-scrollbar {
        display: none;
    }

    .ud-nav__list {
        flex-direction: row;
        gap: 4px;
        min-width: max-content;
    }

    .ud-nav__list .nav-link {
        width: auto;
        padding: 5px 12px 5px 5px;
    }

    .ud-nav__list .nav-link.active::before,
    .ud-nav__chevron {
        display: none;
    }
}

/* ============ AGENT ANALYTICS (OVERVIEW TAB) ============ */
.ua {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ua-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.ua-kpi {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    padding: 12px 14px;
}

.ua-kpi__label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.ua-kpi__label i {
    font-size: 0.9375rem;
    color: var(--color-primary);
}

.ua-kpi__value {
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.25;
    color: var(--color-primary-ink);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ua-kpi__sub {
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.ua-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.ua-card {
    min-width: 0;
    padding: 14px;
}

.ua-card--wide {
    grid-column: 1 / -1;
}

.ua-card__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}

.ua-card__head--tools {
    align-items: center;
    flex-wrap: wrap;
}

.ua-card__title {
    margin: 0 !important;
    font-size: 0.875rem !important;
    font-weight: 700;
    line-height: 1.4 !important;
    color: var(--color-primary-ink);
}

.ua-card__meta {
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.ua-card__subtitle {
    margin: 14px 0 8px !important;
    font-size: 0.75rem !important;
    font-weight: 600;
    line-height: 1.4 !important;
    color: var(--color-text-nav);
}

/* Horizontal bar list: label · track · value */
.ua-bars {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.ua-bars li {
    display: grid;
    grid-template-columns: minmax(70px, 30%) minmax(0, 1fr) 32px;
    align-items: center;
    gap: 10px;
}

.ua-bars__label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8125rem;
    color: var(--color-text-nav);
}

.ua-bars__track {
    height: 8px;
    border-radius: 4px;
    background: var(--color-surface-muted);
    overflow: hidden;
}

.ua-bars__fill {
    display: block;
    height: 100%;
    border-radius: 4px;
    background: var(--color-primary);
    transition: width 0.4s ease;
}

.ua-bars li:hover .ua-bars__fill {
    background: var(--color-primary-hover);
}

.ua-bars__value {
    text-align: right;
    font-size: 0.8125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--color-primary-ink);
}

/* Sale vs Rent split bar (2px gap between parts) */
.ua-split {
    display: flex;
    gap: 2px;
    height: 12px;
    border-radius: 4px;
    overflow: hidden;
    background: var(--color-surface-muted);
}

.ua-split__part {
    flex-basis: 0;
    min-width: 0;
}

.ua-split__part--sale,
.ua-dot--sale {
    background: #733E87;
}

.ua-split__part--rent,
.ua-dot--rent {
    background: #c9a227;
}

.ua-split__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 8px;
    font-size: 0.8125rem;
    color: var(--color-text-nav);
}

.ua-split__legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.ua-split__legend strong {
    color: var(--color-primary-ink);
}

.ua-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
}

.ua-range {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.ua-range input {
    height: 32px;
    padding: 0 8px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 0.8125rem;
    color: var(--color-primary-ink);
    background: var(--color-surface);
}

.ua-perf {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}

.ua-perf__item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    padding: 10px 12px;
    border-radius: 10px;
    background: var(--color-primary-soft);
}

.ua-perf__item strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 1.0625rem;
    color: var(--color-primary-ink);
}

.ua-grid--flush {
    gap: 20px;
}

.ua-bars__label .ua-dot {
    width: 8px;
    height: 8px;
    margin-right: 4px;
    border-radius: 50%;
}

.ua-recent {
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    list-style: none;
}

.ua-recent li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 0;
}

.ua-recent li + li {
    border-top: 1px dashed var(--color-border);
}

.ua-recent__main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.ua-recent__main strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8125rem;
    color: var(--color-primary-ink);
}

.ua-recent__main small {
    font-size: 0.6875rem;
    color: var(--color-text-muted);
}

.ua-recent__stage {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--color-text-nav);
    background: var(--color-surface-muted);
}

.ua-recent__stage .ua-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.ua-empty {
    margin: 0;
    padding: 12px 0;
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}

/* Properties tab: three cards per row on desktop */
.ud-prop-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

@media (max-width: 1199px) {
    .ua-kpis,
    .ua-perf {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 991px) {
    .ua-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767px) {
    .ud-prop-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 575px) {
    .ua-kpis,
    .ua-perf {
        grid-template-columns: 1fr;
    }
}

/* ============ OVERVIEW TAB ============ */
.tab-content {
    padding-top: 1rem;
}

.card {
    border-radius: 0.75rem;
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1rem;
}

.card-body {
    padding: 1rem;
}

.info-item {
    margin-bottom: 0.5rem;
}

.info-label {
    color: #6c757d;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.125rem;
}

.info-value {
    color: #2c3e50;
    font-size: 0.875rem;
    font-weight: 600;
}

.supervisor-avatar {
    width: 3rem;
    height: 3rem;
    object-fit: cover;
}

/* ============ STAT CARDS ============ */
.stat-card {
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.08);
    transition: all 0.3s;
    height: 100%;
}

.stat-card:hover {
    border-color: var(--color-primary);
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.15);
}

.stat-icon {
    opacity: 0.9;
}

.stat-icon i {
    font-size: 2rem;
}

.stat-value {
    color: #2c3e50;
    font-weight: 700;
    font-size: 1.5rem;
}

.stat-label {
    font-size: 0.75rem;
}

/* ============ PROPERTIES SECTION ============ */
.properties-section .card-header {
    padding: 1rem;
}

.stat-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 3rem;
}

.stat-badge .badge {
    width: 2.25rem;
    height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 600;
}

.stat-badge small {
    font-size: 0.625rem;
    margin-top: 0.25rem;
}

.properties-filter .form-select {
    border-radius: 0.5rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* ============ TEAM SECTION ============ */
.team-member-card {
    border-radius: 0.75rem;
    transition: all 0.3s;
    height: 100%;
}

.team-member-card:hover {
    border-color: var(--color-primary);
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.1);
}

.team-avatar {
    width: 3rem;
    height: 3rem;
    object-fit: cover;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.role-badge {
    background: #f1f3f5;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.team-member-card .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
}

/* ============ ERROR STATE ============ */
.error-state .card {
    border-radius: 1rem;
    border: none;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
}

/* ============ RESPONSIVE DESIGN ============ */

/* Extra Small Mobile (≤375px) */
@media (max-width: 375px) {
    .dashboard-main-body {
        padding: 0.75rem;
    }
    
    .user-actions .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .user-actions .btn i {
        margin-right: 0;
    }
    
    .nav-tabs .nav-link {
        padding: 0.375rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .nav-tabs .nav-link i {
        font-size: 0.875rem;
        margin-right: 0.25rem;
    }
    
    .card {
        margin-bottom: 0.75rem;
    }
    
    .card-header, .card-body {
        padding: 0.75rem;
    }
    
    .info-value {
        font-size: 0.8125rem;
    }
    
    .stat-card {
        padding: 0.75rem;
    }
    
    .stat-icon i {
        font-size: 1.5rem;
    }
    
    .stat-value {
        font-size: 1.25rem;
    }
    
    .detail-item i {
        font-size: 0.875rem;
    }
    
    .detail-item small {
        font-size: 0.5625rem;
    }
    
    .team-avatar {
        width: 2.5rem;
        height: 2.5rem;
    }
    
    .team-member-card .btn {
        font-size: 0.6875rem;
        padding: 0.1875rem 0.375rem;
    }
}

/* Small Mobile (376px - 575px) */
@media (min-width: 376px) and (max-width: 575px) {
    .user-actions .btn {
        font-size: 0.8125rem;
    }
    
    .detail-item small {
        font-size: 0.6875rem;
    }
}

/* Medium Mobile (576px - 767px) */
@media (min-width: 576px) and (max-width: 767px) {
    .dashboard-main-body {
        padding: 1.25rem;
    }
    
    .nav-tabs .nav-link {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
}
/* Desktop (992px - 1199px) */
@media (min-width: 992px) {
    .dashboard-main-body {
        padding: 1.5rem;
    }
}

/* Fix for very small screens */
@media (max-width: 320px) {
    .user-actions .btn span {
        display: none;
    }
    
    .user-actions .btn i {
        margin-right: 0;
    }
    
    .nav-tabs .nav-link span {
        display: none;
    }
    
    .nav-tabs .nav-link i {
        margin-right: 0;
    }
}
/* ============ PERFORMANCE & ATTENDANCE TABS ============ */
.perf-card {
    border-radius: 0.75rem;
}

.perf-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}

.perf-date {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.perf-date label {
    font-size: 0.75rem;
    color: #6c757d;
    margin: 0;
}

.perf-date input {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 0.5rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.8125rem;
}

.perf-kpis {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
}

.perf-kpi {
    background: #f8f9fa;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
}

.perf-kpi-label {
    display: block;
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.perf-kpi-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2c3e50;
}

.perf-chart-row {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
}

/* Pin every text element in the Performance tab to one scale: 0.75rem for
   labels/meta, 0.8125rem for body/table/controls, 1.25rem for the one KPI
   emphasis size. Bootstrap's untouched defaults (table, alert, btn-sm) would
   otherwise render around 1rem and stand out next to the smaller labels. */
.perf-card,
.perf-card .btn,
.perf-card .alert,
.perf-card .table {
    font-size: 0.8125rem;
}

.perf-card .table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6c757d;
    font-weight: 600;
}

/* Vacation Mode modal (opened from "Vacation" button next to Edit User) */
.vacation-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1060;
    padding: 16px;
}
.vacation-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.25);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    overflow: hidden;
}
.vacation-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}
.vacation-modal-head h6 {
    font-size: 0.95rem;
    color: #0B0736;
    font-weight: 700;
}
.vacation-modal-head i {
    color: var(--color-primary);
    font-size: 1.1rem;
}
.vacation-modal-close {
    background: transparent;
    border: none;
    font-size: 20px;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
}
.vacation-modal-body {
    padding: 16px;
    overflow-y: auto;
}
.vacation-modal-body .form-label {
    font-weight: 600;
    font-size: 0.8rem;
    color: #495057;
}
.vacation-modal-body .alert-light {
    background: #f8f9fa;
    border: 1px dashed #ced4da;
    color: #6c757d;
    font-size: 0.85rem;
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
}
.vacation-modal-foot {
    padding: 12px 16px;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.vacation-badge {
    font-size: 0.7rem;
    padding: 3px 10px;
    border-radius: 999px;
    font-weight: 600;
}
.vacation-badge.on {
    background: #fff3cd;
    color: #856404;
}
.vacation-badge.off {
    background: #d1e7dd;
    color: #0f5132;
}
.vacation-loading {
    display: flex;
    align-items: center;
    color: #6c757d;
    font-size: 0.85rem;
}
</style>

