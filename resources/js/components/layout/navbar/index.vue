<template>
  <div
    class="navbar-header"
    :class="{
      'navbar-header--mobile-compact': isMobileViewport,
      'navbar-header--kanban-mobile': isKanbanRoute && isMobileViewport,
      'navbar-header--dashboard-home': isDashboardHome,
      'navbar-header--property-detail': isPropertyDetailRoute && isMobileViewport,
      'navbar-header--agent-detail': isAgentDetailRoute && isMobileViewport,
    }"
  >
    <div
      class="navbar-header-toolbar"
    >
      <div v-if="showMobileCompactHeader" class="mob-module-toolbar">
        <div class="kanban-mob-toolbar__main">
          <button
            v-if="!showMobileHeaderBack"
            type="button"
            class="mob-header-menu"
            aria-label="Open navigation menu"
            @click="toggleMobileMenu"
          >
            <iconify-icon icon="heroicons:bars-3-solid" />
          </button>
          <button
            v-if="showMobileHeaderBack"
            type="button"
            class="mob-header-back"
            :aria-label="mobileHeaderBackLabel"
            @click="onMobileHeaderBack"
          >
            <iconify-icon icon="lucide:chevron-left" />
          </button>
          <div
            class="kanban-mob-lead-select-wrap"
            :class="{ 'kanban-mob-lead-select-wrap--detail': showMobileHeaderBack }"
          >
            <span v-if="showMobileHeaderBack" class="mob-module-title mob-module-title--detail">{{ mobileDetailTitle }}</span>
            <select
              v-else-if="moduleHeaderTabs.length"
              class="kanban-mob-lead-select"
              :value="mobileHeaderTabValue"
              aria-label="Switch section view"
              @change="onMobileModuleTabChange"
            >
              <option
                v-for="tab in moduleHeaderTabs"
                :key="tab.id"
                :value="tab.id"
              >
                {{ tab.label }}
              </option>
            </select>
            <span v-else class="mob-module-title">{{ mobileModuleLabel }}</span>
          </div>
          <div class="kanban-mob-toolbar__actions">
            <button
              v-if="isKanbanRoute"
              type="button"
              class="kanban-mob-create"
              aria-label="Create new"
              @click="handleKanbanCreateNew"
            >
              <iconify-icon icon="lucide:plus" />
            </button>
            <button
              v-if="isKanbanRoute && isSuperAdmin"
              type="button"
              class="kanban-mob-icon-btn"
              aria-label="Settings"
              @click="openSettingsHub"
            >
              <iconify-icon icon="lucide:settings" />
            </button>
            <NotificationBell
              ref="notificationBellMob"
              class="kanban-mob-notification"
              :sound-enabled="soundEnabled"
              :browser-notifications-enabled="browserNotificationsEnabled"
              @toggle="handleNotificationToggle"
            />
            <div class="kanban-mob-profile" ref="profileDropdownMob">
              <button
                type="button"
                class="kanban-mob-profile-btn"
                aria-label="Profile"
                @click.stop="openProfilePanel"
              >
                <img
                  v-if="user && user.avatar"
                  :src="user.avatar"
                  alt=""
                  class="kanban-mob-profile-img"
                >
                <img
                  v-else
                  :src="userPlaceholder"
                  alt=""
                  class="kanban-mob-profile-img"
                >
              </button>
            </div>
          </div>
        </div>

        <div v-if="isKanbanRoute" class="kanban-mob-toolbar__search">
          <div
            class="search-area-column kanban-mob-toolbar__search-col"
            ref="searchDropdownAnchorRef"
          >
              <div
                class="search-wrapper kanban-mob-toolbar__search-bar d-flex align-items-center"
                :class="{
                  'search-wrapper-expanded': hasAnySearchCriteria,
                  'search-wrapper-has-selection': hasAnySearchCriteria,
                }"
                @click="canUseLeadSearchModal && openSearchModal()"
              >
              <!-- <button
                type="button"
                class="search-icon-btn"
                aria-label="Search"
                @click.stop="openSearchModal"
              >
                
              </button> -->
              <div
                v-if="resolvedActiveFilters.length"
                class="search-filters-pills d-flex align-items-center flex-shrink-1"
                @click.stop
              >
                <div
                  v-for="f in visibleFilterPillsResolved"
                  :key="f.id"
                  class="search-tag d-flex align-items-center gap-2"
                >
                  <span>{{ f.label }}: {{ f.value }}</span>
                  <iconify-icon icon="lucide:x" class="close-tag-icon" @click.stop="removeFilter(f)" />
                </div>
                <div
                  v-if="moreFiltersCountResolved > 0"
                  class="search-tag search-tag-more d-flex align-items-center gap-2"
                >
                  <span class="search-tag-more-text" @click.stop="showSearchModal = true">+{{ moreFiltersCountResolved }} more</span>
                </div>
              </div>
              <div
                class="search-input-container flex-grow-1"
                @click.stop="canUseLeadSearchModal && openSearchModal()"
              >
                <b-form-input
                  :placeholder="searchInputPlaceholder"
                  :model-value="searchInputDisplay"
                  class="search-input"
                  :class="{ 'search-input--has-selection': hasAnySearchCriteria, 'search-input--loading': isSearchLoading }"
                  :readonly="!!resolvedActiveFilters.length"
                  @update:model-value="onSearchInputUpdate"
                  @focus="onSearchFocus"
                  @blur="onSearchBlur"
                  @click.stop="canUseLeadSearchModal && openSearchModal()"
                />
                <span
                  v-if="isSearchLoading"
                  class="search-input-spinner"
                  role="status"
                  aria-label="Searching"
                />
              </div>
              <button
                v-if="canUseLeadSearchModal"
                type="button"
                class="search-filter-btn"
                title="Advanced search"
                aria-label="Open search filters"
                @mousedown.prevent.stop="openSearchModal"
                @click.prevent.stop="openSearchModal"
              >
                <iconify-icon icon="lucide:list-filter" />
              </button>
              <button
                v-if="hasAnySearchCriteria"
                type="button"
                class="search-clear-btn"
                aria-label="Clear search"
                @click.stop="clearSearchFilter"
              >
                <iconify-icon icon="lucide:x" />
              </button>
            </div>
            <Teleport to="body">
              <div
                v-if="searchModalMounted"
                v-show="showSearchModal"
                ref="searchDropdownPanelRef"
                class="lead-search-dropdown-outer lead-search-dropdown-outer--teleport"
                :style="searchDropdownStyle"
                @mousedown.stop
                @click.stop
              >
                <DealSearchModal
                  v-if="activeKanbanTab === 'deals'"
                  :model-value="showSearchModal"
                  :as-dropdown="true"
                  :current-query="lastQuery"
                  :deal-type="kanbanDealType"
                  @update:model-value="onSearchModalModelUpdate"
                  @search="onDealSearch"
                />
                <LeadSearchModal
                  v-else
                  :model-value="showSearchModal"
                  :as-dropdown="true"
                  :initial-active-pill="activeFilter?.id"
                  :has-active-filters="(activeFilters && activeFilters.length) > 0"
                  :current-query="lastQuery"
                  @update:model-value="onSearchModalModelUpdate"
                  @search="onLeadSearch"
                />
              </div>
            </Teleport>
          </div>
        </div>
      </div>

      <template v-else>
      <div class="navbar-header-left">
        <div class="navbar-header-left-row">
          <button
            v-if="showBackButton && !isDashboardHome"
            type="button"
            class="sidebar-toggle back-button"
            @click="goBack"
            title="Go back"
            aria-label="Go back"
          >
            <iconify-icon icon="lucide:arrow-left" class="icon navbar-header-back-icon text-white" />
          </button>
          <button
            v-if="!isMobileViewport"
            type="button"
            class="sidebar-mobile-toggle"
            aria-label="Open navigation menu"
            @click="toggleMobileMenu"
          >
            <iconify-icon icon="heroicons:bars-3-solid" class="icon navbar-header-menu-icon" />
          </button>
          <!-- <nav
            v-if="showTopModuleNav && topModuleNavItems.length"
            class="top-module-nav"
            aria-label="Main modules"
          >
            <router-link
              v-for="item in topModuleNavItems"
              :key="item.id"
              :to="item.path"
              custom
              v-slot="{ navigate, href }"
            >
              <a
                :href="href"
                class="top-module-btn"
                :class="{ active: isTopModuleItemActive(item) }"
                @click="navigate"
              >
                {{ item.label }}
              </a>
            </router-link>
          </nav> -->
          <select
            v-if="isMobileViewport && moduleHeaderTabs.length"
            class="module-tab-select"
            :value="mobileHeaderTabValue"
            aria-label="Switch section view"
            @change="onMobileModuleTabChange"
          >
            <option
              v-for="tab in moduleHeaderTabs"
              :key="tab.id"
              :value="tab.id"
            >
              {{ tab.label }}{{ tab.count > 0 ? ` (${tab.count})` : '' }}
            </option>
          </select>
          <nav
            v-if="!isDashboardHome && moduleHeaderTabs.length"
            class="module-tabs-nav module-tabs-nav--sub"
            :class="{ 'module-tabs-nav--hide-on-mobile': isMobileViewport }"
            aria-label="Section navigation"
          >
            <template v-for="tab in moduleHeaderTabs" :key="tab.id">
              <button
                v-if="tab.type === 'event'"
                type="button"
                class="module-tab-btn"
                :class="{ active: activeKanbanTab === tab.id }"
                @click="setActiveKanbanTab(tab.id)"
              >
                {{ tab.label }}
                <span v-if="tab.count > 0" class="module-tab-count">{{ tab.count }}</span>
              </button>
              <button
                v-else-if="tab.type === 'deal-type'"
                type="button"
                class="module-tab-btn"
                :class="{ active: activeDealType === tab.id }"
                @click="setActiveDealType(tab.id)"
              >
                {{ tab.label }}
                <span v-if="tab.count > 0" class="module-tab-count">{{ tab.count }}</span>
              </button>
              <router-link
                v-else
                :to="tab.path"
                custom
                v-slot="{ navigate, href }"
              >
                <a
                  :href="href"
                  class="module-tab-btn"
                  :class="{ active: isModuleTabActive(tab) }"
                  @click="navigate"
                >
                  {{ tab.label }}
                  <span v-if="tab.count > 0" class="module-tab-count">{{ tab.count }}</span>
                </a>
              </router-link>
            </template>
          </nav>
        </div>
      </div>

      <div class="navbar-header-right">
        <!-- ========== KANBAN SEARCH & CREATE BUTTON (تظهر فقط في صفحة الكانبان) ========== -->
      <template v-if="isKanbanRoute">
              <!-- في navbar-header-right، استبدلي الـ kanban-search-wrapper بالكود ده -->
        <div class="search-area-column d-flex flex-column align-items-end position-relative" ref="searchDropdownAnchorRef" v-if="isKanbanRoute">
            <div
                class="search-wrapper d-flex align-items-center"
                :class="{
                    'search-wrapper-expanded': hasAnySearchCriteria,
                    'search-wrapper-has-selection': hasAnySearchCriteria,
                    'search-wrapper-tall': searchInputFocused
                }"
                @click="canUseLeadSearchModal && openSearchModal()"
            >
                <!-- <button
                    type="button"
                    class="search-icon-btn"
                    aria-label="Open search filters"
                    @click.stop="openSearchModal"
                >
                    <iconify-icon icon="lucide:search" />
                </button> -->
                <div
                    v-if="resolvedActiveFilters.length"
                    class="search-filters-pills d-flex align-items-center"
                    @click.stop
                >
                    <div
                        v-for="f in visibleFilterPillsResolved"
                        :key="f.id"
                        class="search-tag d-flex align-items-center gap-2"
                    >
                        <span>{{ f.label }}: {{ f.value }}</span>
                        <iconify-icon icon="lucide:x" class="close-tag-icon" @click.stop="removeFilter(f)" />
                    </div>
                    <div
                        v-if="moreFiltersCountResolved > 0"
                        class="search-tag search-tag-more d-flex align-items-center gap-2"
                    >
                        <span class="search-tag-more-text" @click.stop="showSearchModal = true">+{{ moreFiltersCountResolved }} more</span>
                    </div>
                </div>
                <div
                    class="search-input-container d-flex align-items-center"
                    :class="{ 'search-input-container-tall': searchInputFocused }"
                    @click.stop="canUseLeadSearchModal && openSearchModal()"
                >
                    <b-form-input
                        :placeholder="searchInputPlaceholder"
                        :model-value="searchInputDisplay"
                        class="search-input"
                        :class="{ 'search-input--has-selection': hasAnySearchCriteria, 'search-input--loading': isSearchLoading }"
                        :readonly="!!resolvedActiveFilters.length"
                        @update:model-value="onSearchInputUpdate"
                        @focus="onSearchFocus"
                        @blur="onSearchBlur"
                        @click.stop="canUseLeadSearchModal && openSearchModal()"
                    />
                    <span
                        v-if="isSearchLoading"
                        class="search-input-spinner"
                        role="status"
                        aria-label="Searching"
                    />
                </div>
                <button
                    v-if="canUseLeadSearchModal"
                    type="button"
                    class="search-filter-btn"
                    title="Advanced search"
                    aria-label="Add filter"
                    @mousedown.prevent.stop="openSearchModal"
                    @click.prevent.stop="openSearchModal"
                >
                    <iconify-icon icon="lucide:list-filter" />
                </button>
                <button
                    v-if="hasAnySearchCriteria"
                    type="button"
                    class="search-clear-btn"
                    aria-label="Clear search"
                    @click.stop="clearSearchFilter"
                >
                    <iconify-icon icon="lucide:x" />
                </button>
            </div>
            <Teleport to="body">
                <div
                    v-if="searchModalMounted"
                    v-show="showSearchModal"
                    ref="searchDropdownPanelRef"
                    class="lead-search-dropdown-outer lead-search-dropdown-outer--teleport"
                    :style="searchDropdownStyle"
                    @mousedown.stop
                    @click.stop
                >
                    <DealSearchModal
                        v-if="activeKanbanTab === 'deals'"
                        :model-value="showSearchModal"
                        :as-dropdown="true"
                        :current-query="lastQuery"
                        :deal-type="kanbanDealType"
                        @update:model-value="onSearchModalModelUpdate"
                        @search="onDealSearch"
                    />
                    <LeadSearchModal
                        v-else
                        :model-value="showSearchModal"
                        :as-dropdown="true"
                        :initial-active-pill="activeFilter?.id"
                        :has-active-filters="(activeFilters && activeFilters.length) > 0"
                        :current-query="lastQuery"
                        @update:model-value="onSearchModalModelUpdate"
                        @search="onLeadSearch"
                    />
                </div>
            </Teleport>
        </div>

        <!-- Create New Button -->
        <button v-if="isLeadRoute" class="btn-create-new btn-primary d-flex align-items-center" @click="handleKanbanCreateNew">
            <span class="btn-create-new-text">Create New</span>
            <iconify-icon icon="lucide:plus" width="18" height="18" class="btn-create-new-icon flex-shrink-0" aria-hidden="true"></iconify-icon>
        </button>
          <button 
              v-if="isSuperAdmin"
              @click="openSettingsHub"
              class="action-icon-btn d-flex align-items-center justify-content-center radius-circle border"
              style="width: 34px; height: 34px;"
          >
              <iconify-icon icon="lucide:settings" class="text-lg font-weight-bold" style="font-size: 18px;" />
          </button>
      </template>
        <router-link
          v-if="showCreateListingButton"
          to="/property-form"
          class="btn btn-primary btn-sm create-property-btn navbar-create-listing d-flex align-items-center gap-1"
        >
          <i class="ri-add-line"></i>
          <span class="d-none d-sm-inline">Create Listing</span>
        </router-link>
        <button
          v-if="isDashboardHome && isSuperAdmin"
          type="button"
          class="action-icon-btn d-flex align-items-center justify-content-center radius-circle border navbar-settings-btn"
          aria-label="Settings"
          @click="router.push('/system-overview')"
        >
          <iconify-icon icon="lucide:settings" style="font-size: 18px;" />
        </button>
        <SystemOverviewLangToggle />
        <NotificationBell 
          ref="notificationBell"
          :sound-enabled="soundEnabled"
          :browser-notifications-enabled="browserNotificationsEnabled"
          @toggle="handleNotificationToggle"
        />
        <div class="profile-trigger-wrap" ref="profileDropdown">
          <button 
            class="profile-avatar-btn rounded-circle" 
            type="button"
            @click.stop="openProfilePanel"
          >
            <img 
              v-if="user && user.avatar" 
              :src="user.avatar" 
              alt="User Avatar" 
              class="navbar-profile-img object-fit-cover rounded-circle"
            >
            <img 
              v-else 
              :src="userPlaceholder" 
              alt="User Avatar" 
              class="navbar-profile-img object-fit-cover rounded-circle"
            >
          </button>
        </div>
      </div>
      </template>

      <!-- BIG Profile Details modal (like the picture) -->
      <Teleport to="body">
            <Transition name="profile-panel">
              <div v-if="isProfilePanelOpen" class="profile-panel-backdrop" @click="closeProfilePanel">
                <div ref="profilePanel" class="profile-panel" @click.stop>
                  <header class="profile-panel-header">
                    <h2 class="profile-panel-title">Profile Details</h2>
                    <button type="button" class="profile-panel-close" @click="closeProfilePanel" aria-label="Close">
                      <iconify-icon icon="lucide:x" class="icon"></iconify-icon>
                    </button>
                  </header>

                  <div class="profile-panel-body">
                    <div v-if="profileLoading" class="profile-panel-loading">
                      <span class="profile-panel-spinner"></span>
                      <span>Loading...</span>
                    </div>
                    <template v-else>
                    <div class="profile-summary-card">
                      <div class="profile-summary-left">
                        <div class="profile-avatar-wrap">
                          <img v-if="user && user.avatar" :src="user.avatar" alt="Avatar" class="profile-summary-avatar">
                          <img v-else :src="userPlaceholder" alt="Avatar" class="profile-summary-avatar">
                          <label class="profile-avatar-camera">
                            <input
                              ref="avatarInput"
                              type="file"
                              accept="image/*"
                              class="profile-avatar-file-input"
                              @change="onAvatarChange"
                            >
                            <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                          </label>
                        </div>
                        <div class="profile-summary-info">
                          <h6 class="profile-summary-name">{{ user ? user.name : 'User' }}</h6>
                          <p class="profile-summary-email">{{ user && user.email ? user.email : '—' }}</p>
                          <p class="profile-summary-role" v-if="!isShowOnlyListing">{{ user && user.role_name ? user.role_name : 'User' }}</p>
                        </div>
                      </div>
                      <div class="profile-summary-right">
                        <div class="profile-status-row">
                          <span class="profile-status-dot" :class="user && user.status === 'active' ? 'status-online' : 'status-offline'"></span>
                          <span class="profile-status-text">{{ user && user.status === 'active' ? 'Online' : 'Offline' }}</span>
                          <button type="button" class="profile-more-btn" aria-label="Options">
                            <iconify-icon icon="lucide:more-vertical" class="icon"></iconify-icon>
                          </button>
                        </div>
                        <p class="profile-last-active">Last Active : {{ lastActiveText }}</p>
                      </div>
                    </div>

                    <section class="profile-section profile-section-contact">
                      <div class="profile-section-head">
                        <h4 class="profile-section-title">Contact Information</h4>
                        <template v-if="!isPersonalInfoEditing">
                          <button type="button" class="profile-edit-icon" aria-label="Edit" @click="startPersonalInfoEdit">
                            <iconify-icon icon="lucide:pencil" class="icon"></iconify-icon>
                          </button>
                        </template>
                        <div v-else class="profile-contact-actions">
                          <button type="button" class="profile-contact-btn profile-contact-cancel" @click="cancelPersonalInfoEdit">Cancel</button>
                          <button type="button" class="profile-contact-btn profile-contact-save" @click="savePersonalInfoEdit">Save</button>
                        </div>
                      </div>
                      <div class="profile-contact-grid profile-contact-two-cols">
                        <div class="profile-contact-item">
                          <span class="profile-contact-label">First Name</span>
                          <input
                            v-if="isPersonalInfoEditing"
                            v-model="personalInfoEdit.first_name"
                            type="text"
                            class="profile-contact-input"
                            placeholder="First Name"
                          >
                          <span v-else class="profile-contact-value">{{ firstName }}</span>
                        </div>
                        <div class="profile-contact-item" v-if="!isShowOnlyListing">
                          <span class="profile-contact-label">Departments</span>
                          <span class="profile-contact-value profile-contact-readonly">{{ user && user.role_name ? user.role_name : '—' }}</span>
                        </div>
                        <div class="profile-contact-item">
                          <span class="profile-contact-label">Last Name</span>
                          <input
                            v-if="isPersonalInfoEditing"
                            v-model="personalInfoEdit.last_name"
                            type="text"
                            class="profile-contact-input"
                            placeholder="Last Name"
                          >
                          <span v-else class="profile-contact-value">{{ lastName }}</span>
                        </div>
                        <div class="profile-contact-item">
                          <span class="profile-contact-label">Notification Language</span>
                          <span class="profile-contact-value profile-contact-readonly">{{ notificationLanguage }}</span>
                        </div>
                        <div class="profile-contact-item">
                          <span class="profile-contact-label">Email</span>
                          <input
                            v-if="isPersonalInfoEditing"
                            v-model="personalInfoEdit.email"
                            type="email"
                            class="profile-contact-input"
                            placeholder="Email"
                          >
                          <span v-else class="profile-contact-value">{{ user && user.email ? user.email : '—' }}</span>
                        </div>
                        <div class="profile-contact-item">
                          <span class="profile-contact-label">Phone Number</span>
                          <input
                            v-if="isPersonalInfoEditing"
                            v-model="personalInfoEdit.phone"
                            type="tel"
                            class="profile-contact-input"
                            placeholder="Phone Number"
                          >
                          <span v-else class="profile-contact-value">{{ user && user.phone ? user.phone : '—' }}</span>
                        </div>
                      </div>
                    </section>
                    <section class="profile-section profile-section-team" v-if="!isShowOnlyListing">
                      <div class="profile-section-head">
                        <h4 class="profile-section-title">Your Team</h4>
                        <span class="profile-section-badge"># TEAM</span>
                      </div>
                      <div class="profile-team-grid">
                        <div
                          v-for="member in visibleTeamMembers"
                          :key="member.id"
                          class="profile-team-card profile-team-pill"
                        >
                          <div class="profile-team-avatar-wrap">
                            <img v-if="member.avatar" :src="member.avatar" :alt="member.name" class="profile-team-avatar">
                            <span v-else class="profile-team-agent-icon">
                              <iconify-icon icon="lucide:user-round" class="icon"></iconify-icon>
                            </span>
                            <span
                              class="profile-team-status-dot"
                              :class="member.status === 'away' ? 'status-away' : member.online ? 'status-online' : 'status-offline'"
                            ></span>
                          </div>
                          <div class="profile-team-info">
                            <span class="profile-team-name">{{ teamMemberDisplayName(member) }}</span>
                            <span class="profile-team-role">{{ member.role_name || member.role || '—' }}</span>
                          </div>
                        </div>
                      </div>
                      <div v-if="teamMembersList.length > teamPageSize" class="profile-team-see-more-wrap">
                        <button
                          v-if="hasMoreTeamMembers"
                          type="button"
                          class="profile-show-all-team profile-see-more-btn"
                          @click="loadMoreTeamMembers"
                        >
                          Show All Team
                        </button>
                      </div>
                      <p v-if="teamMembersList.length === 0 && !profileLoading" class="profile-team-empty">No team members under you.</p>
                    </section>

                    <div class="profile-quick-menu">
                      <button type="button" class="profile-quick-menu-item" @click="openThemeModal">
                        <iconify-icon icon="lucide:palette" class="profile-quick-menu-icon" />
                        <span class="profile-quick-menu-label">Visual theme</span>
                        <iconify-icon icon="lucide:chevron-right" class="profile-quick-menu-chevron" />
                      </button>
                      <router-link
                        v-if="!isShowOnlyListing"
                        to="/view-profile"
                        class="profile-quick-menu-item"
                        @click="closeProfilePanel"
                      >
                        <iconify-icon icon="solar:user-linear" class="profile-quick-menu-icon" />
                        <span class="profile-quick-menu-label">My profile</span>
                        <iconify-icon icon="lucide:chevron-right" class="profile-quick-menu-chevron" />
                      </router-link>
                      <button type="button" class="profile-quick-menu-item profile-quick-menu-item--logout" @click="logout">
                        <iconify-icon icon="lucide:power" class="profile-quick-menu-icon" />
                        <span class="profile-quick-menu-label">Log out</span>
                        <iconify-icon icon="lucide:chevron-right" class="profile-quick-menu-chevron" />
                      </button>
                    </div>
                    </template>
                  </div>
                </div>
              </div>
            </Transition>
          </Teleport>

      <ProfileThemeModal v-model="showThemeModal" />
         
    </div>
  </div>
</template>

<script setup>
import { useSidebar, resetSidebarLayout } from '@/composables/useSidebar.js';
import { ref, onMounted, computed, onUnmounted, watch, nextTick, getCurrentInstance } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import {
  buildHeaderTabs,
  buildTopModuleNav,
  isTabActive,
  KANBAN_ACTIVE_TAB_KEY,
  DEAL_TYPE_KEY,
  CRM_PREFIXES,
  CRM_SECTIONS,
  resolveCrmSection,
  rememberCrmSection,
  getListingsEntryPath,
} from '@/composables/useLayoutNavigation.js';
import { useLayoutActiveState } from '@/composables/useLayoutActiveState.js';
import { useTheme } from '@/composables/useTheme.js';
import { useMobileNavigation } from '@/composables/useMobileNavigation.js';
import NotificationBell from '@/components/NotificationBell.vue';
import ProfileThemeModal from '@/components/shared/ProfileThemeModal.vue';
import SystemOverviewLangToggle from '@/components/system-overview/SystemOverviewLangToggle.vue';
import userAvatarPlaceholder from '@/assets/images/users/user1.png';
import DealSearchModal from '@/components/kanban/deals/DealSearchModal.vue';
import LeadSearchModal from '@/components/kanban/leadList/LeadSearchModal.vue';
const { isMobileOpen, openMobileSidebar } = useSidebar();
const { isMobileViewport, toggleMobileMenu } = useMobileNavigation();
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { BFormInput } from 'bootstrap-vue-3';
const userPlaceholder = userAvatarPlaceholder;
const { theme, toggleTheme } = useTheme();
const router = useRouter();
const route = useRoute();

function goBack() {
  router.back();
}

function goBackToListings() {
  router.push(getListingsEntryPath('/alllisting'));
}

function goBackToAgentsList() {
  router.push('/users');
}

const isPropertyDetailRoute = computed(
  () => route.path.startsWith('/property-details/'),
);

const isAgentDetailRoute = computed(
  () => /^\/users\/\d+$/.test(route.path),
);

const showMobileHeaderBack = computed(
  () => isPropertyDetailRoute.value || isAgentDetailRoute.value,
);

const mobileDetailTitle = computed(() => {
  if (isPropertyDetailRoute.value) return 'Property';
  if (isAgentDetailRoute.value) return 'Agent';
  return '';
});

const mobileHeaderBackLabel = computed(() => {
  if (isPropertyDetailRoute.value) return 'Back to listings';
  if (isAgentDetailRoute.value) return 'Back to agents list';
  return 'Go back';
});

function onMobileHeaderBack() {
  if (isPropertyDetailRoute.value) {
    goBackToListings();
    return;
  }
  if (isAgentDetailRoute.value) {
    goBackToAgentsList();
    return;
  }
  router.back();
}

const {
  isDashboardHome,
  activeLayoutModule,
  activeCrmSection,
  isTopModuleItemActive,
} = useLayoutActiveState();
const { proxy } = getCurrentInstance();
const user = ref(null);

const isAdmin = computed(() => {
  if (!user.value) return false;
  return (
    user.value.roles?.includes('super_admin') ||
    user.value.roles?.includes('admin') ||
    proxy?.$hasPermission?.('admin')
  );
});

const isSuperAdmin = computed(() => user.value?.roles?.includes('super_admin') ?? false);

const isCustomAdmin = computed(() => {
  if (!user.value) return false;
  const userId = Number(user.value.id);
  return (
    user.value.roles?.includes('super_admin') ||
    (user.value.roles?.includes('admin') && (userId === 30 || userId === 33))
  );
});

const isShowOnlyListingNav = computed(() => user.value?.roles?.includes('only show listings') ?? false);

const listingTabCounts = ref({ listings: 0, requests: 0, viewings: 0 });

async function fetchListingTabCounts() {
  try {
    const response = await api.get('/sidebar/counts');
    if (!response.data?.success) return;
    const counts = response.data.data || {};
    listingTabCounts.value = {
      listings: isAdmin.value
        ? (counts.listings?.all || 0)
        : (counts.listings?.my || 0),
      requests: isAdmin.value
        ? (counts.orders?.all || 0)
        : ((counts.requests?.all || 0) + (counts.orders?.all || 0)),
      viewings: 0,
    };
  } catch {
    /* ignore */
  }
}

watch(
  () => activeCrmSection.value,
  (section) => {
    if (section === CRM_SECTIONS.LISTINGS) {
      fetchListingTabCounts();
    }
  },
);

const moduleHeaderTabs = computed(() => {
  if (isDashboardHome.value) return [];
  const ctx = {
    isAdmin: isAdmin.value,
    isSuperAdmin: isSuperAdmin.value,
    isCustomAdmin: isCustomAdmin.value,
    isShowOnlyListing: isShowOnlyListingNav.value,
    hasPermission: (p) => proxy?.$hasPermission?.(p) ?? true,
    listingTabCounts: listingTabCounts.value,
       user: user.value,
  };
  if (activeLayoutModule.value === 'crm') {
    return buildHeaderTabs('crm', ctx, activeCrmSection.value);
  }
  return buildHeaderTabs(activeLayoutModule.value, ctx);
});

const showTopModuleNav = computed(() => isDashboardHome.value && !isMobileViewport.value);
const topModuleNavItems = computed(() => {
  void route.path;
  return buildTopModuleNav({
    isAdmin: isAdmin.value,
    isSuperAdmin: isSuperAdmin.value,
    isShowOnlyListing: isShowOnlyListingNav.value,
    userId: Number(user.value?.id) || 0,
    canAccessListings: isAdmin.value || isShowOnlyListingNav.value,
    hasPermission: (p) => proxy?.$hasPermission?.(p) ?? true,
  });
});

function isModuleTabActive(tab) {
  return isTabActive(route.path, tab);
}

// إعدادات الإشعارات
const soundEnabled = ref(true);
const browserNotificationsEnabled = ref(true);

// computed property للتحقق من الصلاحيات وعرض الزر
const showCreateListingButton = computed(() => {
  if (activeCrmSection.value !== CRM_SECTIONS.LISTINGS) return false;
  if (isShowOnlyListingNav.value) return false;
  return !proxy?.$hasPermission || proxy.$hasPermission('listings-create');
});
// ========== KANBAN STATE ==========
const activeKanbanTab = ref('leads')
const activeDealType = ref('primary')
const showKanbanSearchModal = ref(false)
const kanbanLastQuery = ref(null)
const kanbanDealType = ref('primary')
const kanbanActiveFiltersCount = ref(0)
const search = ref(null);
const activeFilters = ref([]);
const lastQuery = ref(null);
const activeFilter = ref(null);
const showSearchModal = ref(false);
const searchInputFocused = ref(false);
const searchDropdownAnchorRef = ref(null);
const searchDropdownPanelRef = ref(null);
const searchDropdownStyle = ref({});
const searchDebounceTimer = ref(null);
const SEARCH_DEBOUNCE_MS = 300;
const suppressSearchWatcher = ref(false);
const isSearchLoading = ref(false);
const leadsRef = ref(null);
const dealsRef = ref(null);
const openSettingsHub = (section = null) => {
    window.dispatchEvent(
        new CustomEvent('kanban-open-settings', {
            detail: section ? { section } : {},
        })
    )
}
const defaultFilter = { id: 'leads-in-progress', label: 'Leads In Progress' }

function applySearchToApi() {
    const term = (search.value || '').trim()
    let query = null

    if (lastQuery.value && Object.keys(lastQuery.value).length) {
        query = { ...lastQuery.value }
        if (term) {
            query.search = term
        } else {
            delete query.search
        }
    } else if (term) {
        query = { search: term }
    }

    const payload = {
        query,
        activeFilters: activeFilters.value || [],
    }

    // Immediate feedback in the search box while the board fetches.
    if (query && Object.keys(query).length) {
        isSearchLoading.value = true
    } else {
        isSearchLoading.value = false
    }
    
    if (isDealRoute.value || activeKanbanTab.value === 'deals') {
        window.dispatchEvent(new CustomEvent('kanban-deal-search', { detail: payload }))
    } else if (isLeadRoute.value || activeKanbanTab.value === 'leads' || activeKanbanTab.value === 'lead-pool') {
        window.dispatchEvent(new CustomEvent('kanban-lead-search', { detail: payload }))
    }
}

// Watch على search لتطبيق البحث مع debounce
watch(search, () => {
    if (suppressSearchWatcher.value) return
    console.log('Search value changed:', search.value)
    if (searchDebounceTimer.value) {
        clearTimeout(searchDebounceTimer.value)
        searchDebounceTimer.value = null
    }
    searchDebounceTimer.value = setTimeout(() => {
        searchDebounceTimer.value = null
        applySearchToApi()
    }, SEARCH_DEBOUNCE_MS)
})

watch(activeKanbanTab, (newTab) => {
    // Reset search when tab changes so the previous tab's filter (typed text + pills + query)
    // doesn't bleed into the new tab. E.g. searching "John" in Lead Pool and switching to Leads
    // must NOT carry "John" over — Leads should load unfiltered.
    setSearchSilently('')
    activeFilters.value = []
    lastQuery.value = null
    activeFilter.value = null

    // Cancel any pending debounced applySearchToApi from the previous tab so it can't fire late
    // and re-apply the old search term to the new tab.
    if (searchDebounceTimer.value) {
        clearTimeout(searchDebounceTimer.value)
        searchDebounceTimer.value = null
    }

    // Tell the underlying boards to actively clear their applied query — clearing only the
    // navbar's local refs leaves leads.vue / LeadPool.vue still rendering filtered results
    // until the next user action. kanban listens for null payload and resets the active board.
    const clearPayload = { query: null, activeFilters: [] }
    if (newTab === 'deals') {
        window.dispatchEvent(new CustomEvent('kanban-deal-search', { detail: clearPayload }))
    } else if (newTab === 'leads' || newTab === 'lead-pool') {
        window.dispatchEvent(new CustomEvent('kanban-lead-search', { detail: clearPayload }))
    }
})
const isKanbanRoute = computed(() => {
  if (isDashboardHome.value) return false;
  return activeCrmSection.value === CRM_SECTIONS.LEAD || activeCrmSection.value === CRM_SECTIONS.DEAL;
});
const isLeadRoute = computed(() => activeCrmSection.value === CRM_SECTIONS.LEAD);
const isDealRoute = computed(() => activeCrmSection.value === CRM_SECTIONS.DEAL);
const isListingsCrmRoute = computed(() => activeCrmSection.value === CRM_SECTIONS.LISTINGS);
const showMobileCompactHeader = computed(() => isMobileViewport.value);

const mobileModuleLabel = computed(() => {
  const labels = {
    dashboard: 'Home',
    crm: 'CRM',
    hr: 'HR',
    agents: 'Agents',
    listings: 'Listings',
    settings: 'Settings',
  };
  return labels[activeLayoutModule.value] ?? 'Menu';
});

const mobileHeaderTabValue = computed(() => {
  if (!moduleHeaderTabs.value.length) return '';
  if (isDealRoute.value) return activeDealType.value;
  if (isLeadRoute.value) return activeKanbanTab.value;
  const active = moduleHeaderTabs.value.find((tab) => isModuleTabActive(tab));
  return active?.id ?? moduleHeaderTabs.value[0]?.id ?? '';
});

// وظائف الكانبان
const setActiveKanbanTab = (tabId) => {
  activeKanbanTab.value = tabId
  localStorage.setItem(KANBAN_ACTIVE_TAB_KEY, tabId)
  window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: tabId }))
}

const setActiveDealType = (typeId) => {
  activeDealType.value = typeId
  localStorage.setItem(DEAL_TYPE_KEY, typeId)
  window.dispatchEvent(new CustomEvent('kanban-deal-type-change', { detail: typeId }))
}

function loadStoredDealType() {
  const stored = localStorage.getItem(DEAL_TYPE_KEY)
  if (stored && ['primary', 'secondary', 'rental'].includes(stored)) {
    activeDealType.value = stored
  }
}

const onDealTypeChangeFromPage = (e) => {
  const next = e?.detail
  if (!next || activeDealType.value === next) return
  activeDealType.value = next
}

// Sync from kanban_deal.vue when its activeTab changes (route-forced or in-page tab switch).
// Without this, the navbar's activeKanbanTab drifts and applySearchToApi dispatches the wrong
// event (e.g. kanban-lead-search while viewing the Deals board), so filters appear inert.
const onKanbanTabChangeFromPage = (e) => {
  const next = e?.detail
  if (!next || activeKanbanTab.value === next) return
  activeKanbanTab.value = next
}

const openKanbanSearch = () => {
  showKanbanSearchModal.value = true
}

const closeKanbanSearch = () => {
  showKanbanSearchModal.value = false
}

const handleKanbanCreateNew = () => {
  window.dispatchEvent(new CustomEvent('kanban-create-new', { detail: activeKanbanTab.value }))
}

const onKanbanLeadSearch = (payload) => {
  kanbanLastQuery.value = payload?.query || null
  kanbanActiveFiltersCount.value = payload?.activeFilters?.length || 0
  showKanbanSearchModal.value = false
  window.dispatchEvent(new CustomEvent('kanban-lead-search', { detail: payload }))
}

const onKanbanDealSearch = (payload) => {
  kanbanLastQuery.value = payload?.query || null
  kanbanActiveFiltersCount.value = payload?.activeFilters?.length || 0
  showKanbanSearchModal.value = false
  window.dispatchEvent(new CustomEvent('kanban-deal-search', { detail: payload }))
}

const loadStoredKanbanTab = () => {
  const stored = localStorage.getItem(KANBAN_ACTIVE_TAB_KEY)
  if (stored && moduleHeaderTabs.value.some((t) => t.id === stored && t.type === 'event')) {
    activeKanbanTab.value = stored
  }
}

function isCrmRoute(path) {
  return CRM_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))
}

function restoreCrmSectionFromStorage() {
  const section = resolveCrmSection(route.path)
  if (section) rememberCrmSection(section)

  if (section === CRM_SECTIONS.DEAL) {
    loadStoredDealType()
    activeKanbanTab.value = 'deals'
    nextTick(() => {
      window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: 'deals' }))
      window.dispatchEvent(new CustomEvent('kanban-deal-type-change', { detail: activeDealType.value }))
    })
    return
  }

  if (section === CRM_SECTIONS.LEAD) {
    loadStoredKanbanTab()
    if (!['leads', 'lead-pool'].includes(activeKanbanTab.value)) {
      activeKanbanTab.value = 'leads'
    }
    nextTick(() => {
      window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: activeKanbanTab.value }))
    })
  }
}
// Search computed properties
const searchInputPlaceholder = computed(() => {
    if (isDealRoute.value || activeKanbanTab.value === 'deals') {
        return 'Search deals, client, phone…';
    }
    if (activeKanbanTab.value === 'lead-pool') {
        return 'Search name, phone, email…';
    }
    return 'Search leads, phone, email…';
});

const visibleFilterPills = computed(() => {
    const list = activeFilters.value || [];
    return list.slice(0, 2);
});

const moreFiltersCount = computed(() => {
    const n = (activeFilters.value || []).length - 2;
    return n > 0 ? n : 0;
});

const QUERY_FILTER_LABELS = {
    lead_name: 'Lead Name',
    first_name: 'Client Name',
    email: 'Email',
    work_phone: 'Phone',
    search: 'Search',
    responsible_person_id: 'Responsible Person',
    team_id: 'Team',
    lead_branch_source: 'Lead Branch Source',
    source: 'Source',
    interaction_result: 'Call Result',
    status_lead: 'Quality Status',
    stage_id: 'Stage',
    lead_type: 'Lead Type',
    property_status: 'Property Status',
    area_id: 'Location',
    property_type_id: 'Property Type',
    purpose_buying: 'Purpose',
    bedrooms: 'Bedrooms',
};

function formatNavbarFilterValue(key, query) {
    if (key === 'created_from' || key === 'created_at') {
        if (query.created_from && query.created_to && query.created_from !== query.created_to) {
            return `${query.created_from} to ${query.created_to}`;
        }
        return query.created_from || query.created_to || query.created_at || '';
    }
    if (key === 'assigned_from' || key === 'assigned_at') {
        if (query.assigned_from && query.assigned_to && query.assigned_from !== query.assigned_to) {
            return `${query.assigned_from} to ${query.assigned_to}`;
        }
        return query.assigned_from || query.assigned_to || query.assigned_at || '';
    }
    if (key === 'office_branch') {
        const v = query.office_branch;
        return Array.isArray(v) ? v.join(', ') : String(v ?? '');
    }
    if (key === 'budget_from' || key === 'budget_to') {
        const from = query.budget_from;
        const to = query.budget_to;
        if (from && to) return `${from} - ${to}`;
        if (from) return `From ${from}`;
        if (to) return `To ${to}`;
        return '';
    }
    const v = query[key];
    if (v == null || v === '') return '';
    return Array.isArray(v) ? v.join(', ') : String(v);
}

function buildNavbarFiltersFromQuery(query) {
    if (!query || typeof query !== 'object') return [];
    const filters = [];
    const seen = new Set();

    const add = (id, queryKey, label, value) => {
        const text = value != null ? String(value).trim() : '';
        if (!text || seen.has(id)) return;
        seen.add(id);
        filters.push({ id, queryKey, label, value: text });
    };

    Object.keys(QUERY_FILTER_LABELS).forEach((key) => {
        if (query[key] !== undefined && query[key] !== '' && query[key] !== null) {
            add(key, key, QUERY_FILTER_LABELS[key], formatNavbarFilterValue(key, query));
        }
    });

    if (query.created_from || query.created_to || query.created_at) {
        add(
            'created_on',
            'created_at',
            'Created On',
            formatNavbarFilterValue('created_from', query) || formatNavbarFilterValue('created_at', query),
        );
    }
    if (query.assigned_from || query.assigned_to || query.assigned_at) {
        add(
            'assigned_on',
            'assigned_at',
            'Assign On',
            formatNavbarFilterValue('assigned_from', query) || formatNavbarFilterValue('assigned_at', query),
        );
    }
    if (query.budget_from || query.budget_to) {
        add('budget_from', 'budget_from', 'Budget', formatNavbarFilterValue('budget_from', query));
    }

    return filters;
}

const resolvedActiveFilters = computed(() => {
    const fromState = activeFilters.value || [];
    if (fromState.length) return fromState;
    return buildNavbarFiltersFromQuery(lastQuery.value);
});

const visibleFilterPillsResolved = computed(() => resolvedActiveFilters.value.slice(0, 2));

const moreFiltersCountResolved = computed(() => {
    const n = resolvedActiveFilters.value.length - 2;
    return n > 0 ? n : 0;
});

const searchBarDisplayValue = computed(() => {
    const filters = resolvedActiveFilters.value;
    if (filters.length) {
        const parts = filters.map((f) => `${f.label}: ${f.value}`);
        if (parts.length <= 2) return parts.join(' · ');
        return `${parts.slice(0, 2).join(' · ')} +${parts.length - 2} more`;
    }
    const term = search.value != null ? String(search.value).trim() : '';
    if (term) return term;
    const qTerm = lastQuery.value?.search;
    if (qTerm != null && String(qTerm).trim()) return String(qTerm).trim();
    return '';
});

const searchInputDisplay = computed(() => searchBarDisplayValue.value || (search.value ?? ''));

const hasAnySearchCriteria = computed(() => {
    const hasTextSearch = search.value != null && String(search.value).trim() !== '';
    const hasPills = resolvedActiveFilters.value.length > 0;
    const hasQuery = !!(lastQuery.value && Object.keys(lastQuery.value).length);
    return hasTextSearch || hasPills || hasQuery;
});

// Lead Pool advanced search (filter modal) is admin/super_admin-only — other users
// get plain text search via the input field only.
const canUseLeadSearchModal = computed(() => {
    if (activeKanbanTab.value !== 'lead-pool') return true;
    return isAdmin.value;
});

function onSearchInputUpdate(val) {
    if (resolvedActiveFilters.value.length) {
        clearSearchFilter();
    }
    // Keep the advanced popup open while typing in the navbar input.
    // Free-text search still applies via the debounced watch(search).
    const next = val == null ? '' : String(val);
    search.value = next;
    if (String(next).trim()) {
        isSearchLoading.value = true;
    }
}

// Search functions (same as Kanban)
const dropLinkedQueryKeys = (query, queryKey) => {
    if (!query || !queryKey) return;
    delete query[queryKey];

    if (queryKey === 'created_at') {
        delete query.created_from;
        delete query.created_to;
        return;
    }
    if (queryKey === 'end_date') {
        delete query.from_date;
        delete query.to_date;
        return;
    }
    if (queryKey === 'assigned_at') {
        delete query.assigned_from;
        delete query.assigned_to;
        return;
    }
    if (queryKey === 'office_branch') {
        delete query.lead_branch_source;
        return;
    }
    if (queryKey === 'source') {
        delete query.source_website;
        return;
    }
    if (queryKey === 'budget_from' || queryKey === 'budget_to') {
        delete query.budget_from;
        delete query.budget_to;
    }
};

function setSearchSilently(value) {
    suppressSearchWatcher.value = true
    search.value = value
    queueMicrotask(() => {
        suppressSearchWatcher.value = false
    })
}

const onLeadSearch = (payload) => {
    console.log('🔍 onLeadSearch called with payload:', payload)
    
    if (payload === null || payload?.query === null) {
        activeFilter.value = null
        activeFilters.value = []
        lastQuery.value = null
        setSearchSilently('')
        
        window.dispatchEvent(new CustomEvent('kanban-lead-search-update', { 
            detail: { query: null, activeFilters: [] }
        }))
        return
    }
    
    const query = payload?.query !== undefined ? payload.query : payload
    const pill = payload?.activePill
    
    console.log('Query:', query)
    console.log('Active pill:', pill)
    
    if (pill) {
        activeFilter.value = { id: pill.id, label: pill.label }
    } else if (!activeFilter.value) {
        activeFilter.value = { ...defaultFilter }
    }
    
    lastQuery.value = query && Object.keys(query).length ? { ...query } : null
    let filters = Array.isArray(payload?.activeFilters) ? [...payload.activeFilters] : []
    if (!filters.length && lastQuery.value) {
        filters = buildNavbarFiltersFromQuery(lastQuery.value)
    }
    activeFilters.value = filters

    if (query?.search != null && String(query.search).trim()) {
        setSearchSilently(String(query.search).trim())
    } else {
        setSearchSilently('')
    }
    
    console.log('Active filters:', activeFilters.value)
    console.log('Last query:', lastQuery.value)
    
    window.dispatchEvent(new CustomEvent('kanban-lead-search-update', { 
        detail: { query: lastQuery.value, activeFilters: activeFilters.value }
    }))
    
}

// قم بتعديل دالة onDealSearch
const onDealSearch = (payload) => {
    console.log('🔍 onDealSearch called with payload:', payload)
    
    if (payload === null || payload?.query === null) {
        activeFilter.value = null
        activeFilters.value = []
        lastQuery.value = null
        
        if (window.__kanbanDealsRef) {
            const dealsComponent = window.__kanbanDealsRef()
            if (dealsComponent && typeof dealsComponent.fetchDeals === 'function') {
                console.log('📞 Calling fetchDeals with null query')
                dealsComponent.fetchDeals(true, null)
            } else {
                console.warn('fetchDeals not found on deals component')
            }
        } else {
            console.warn('window.__kanbanDealsRef is not available')
        }
        
        window.dispatchEvent(new CustomEvent('kanban-deal-search-update', { 
            detail: { query: null, activeFilters: [] }
        }))
        return
    }

    const query = payload?.query !== undefined ? payload.query : payload
    activeFilters.value = Array.isArray(payload?.activeFilters) ? payload.activeFilters : []
    lastQuery.value = query && Object.keys(query).length ? { ...query } : null
    
    console.log('Active filters:', activeFilters.value)
    console.log('Last query:', lastQuery.value)
    
    window.dispatchEvent(new CustomEvent('kanban-deal-search-update', { 
        detail: { query: lastQuery.value, activeFilters: activeFilters.value }
    }))

    if (window.__kanbanDealsRef) {
        const dealsComponent = window.__kanbanDealsRef()
        if (dealsComponent && typeof dealsComponent.fetchDeals === 'function') {
            console.log('📞 Calling fetchDeals with query:', query || null)
            dealsComponent.fetchDeals(true, query || null)
        } else {
            console.warn('fetchDeals not found on deals component')
        }
    } else {
        console.warn('window.__kanbanDealsRef is not available')
    }
}

// تأكد من أن دوال removeFilter و clearSearchFilter تستخدم نفس الطريقة
const removeFilter = (f) => {
    if (!lastQuery.value) return;
    const nextQuery = { ...lastQuery.value };
    dropLinkedQueryKeys(nextQuery, f.queryKey);

    activeFilters.value = activeFilters.value.filter(x => x.id !== f.id);
    lastQuery.value = Object.keys(nextQuery).length ? nextQuery : null;
    if (!Object.keys(nextQuery).length) {
          activeFilter.value = null;
            activeFilters.value = [];
            lastQuery.value = null;
            search.value = '';
    }

    const payload = { query: lastQuery.value, activeFilters: activeFilters.value };
    if (activeKanbanTab.value === 'deals') {
        window.dispatchEvent(new CustomEvent('kanban-deal-search', { detail: payload }));
    } else {
        window.dispatchEvent(new CustomEvent('kanban-lead-search', { detail: payload }));
    }
};

const clearMoreFilters = () => {
    const list = activeFilters.value || [];
    if (list.length <= 2) return;
    const keep = list.slice(0, 2);
    const remove = list.slice(2);
    const nextQuery = lastQuery.value ? { ...lastQuery.value } : {};

    remove.forEach(f => {
        dropLinkedQueryKeys(nextQuery, f.queryKey);
    });

    activeFilters.value = keep;
    lastQuery.value = Object.keys(nextQuery).length ? nextQuery : null;
    if (!Object.keys(nextQuery).length) {
        activeFilter.value = null;
        activeFilters.value = [];
        lastQuery.value = null;
        search.value = '';
    }
    
    const payload = { query: lastQuery.value, activeFilters: activeFilters.value };
    if (activeKanbanTab.value === 'deals') {
        window.dispatchEvent(new CustomEvent('kanban-deal-search', { detail: payload }));
    } else {
        window.dispatchEvent(new CustomEvent('kanban-lead-search', { detail: payload }));
    }
};

const clearSearchFilter = () => {
    activeFilter.value = null;
    activeFilters.value = [];
    lastQuery.value = null;
    search.value = '';
    showSearchModal.value = false;
    
    if (activeKanbanTab.value === 'deals') {
        window.dispatchEvent(new CustomEvent('kanban-deal-search', { detail: null }));
    } else {
        window.dispatchEvent(new CustomEvent('kanban-lead-search', { detail: null }));
    }
};

function updateSearchDropdownPosition() {
    const anchor = searchDropdownAnchorRef.value;
    if (!anchor || typeof anchor.getBoundingClientRect !== 'function') return;
    const rect = anchor.getBoundingClientRect();
    if (!rect.width && !rect.height) return;
    searchDropdownStyle.value = {
        position: 'fixed',
        top: `${Math.round(rect.bottom + 8)}px`,
        right: `${Math.round(Math.max(12, window.innerWidth - rect.right))}px`,
        left: 'auto',
        width: 'min(1140px, calc(100vw - 24px))',
        maxWidth: 'calc(100vw - 24px)',
        zIndex: 15000,
    };
}

function onSearchDropdownReposition() {
    if (showSearchModal.value) {
        updateSearchDropdownPosition();
    }
}

// Prevent the same click that opens the popup from immediately closing it.
let ignoreSearchOutsideClick = false;
let ignoreSearchOutsideClickTimer = null;
const searchModalMounted = ref(false);

function armIgnoreOutsideClick(ms = 150) {
    ignoreSearchOutsideClick = true;
    if (ignoreSearchOutsideClickTimer) clearTimeout(ignoreSearchOutsideClickTimer);
    ignoreSearchOutsideClickTimer = setTimeout(() => {
        ignoreSearchOutsideClick = false;
        ignoreSearchOutsideClickTimer = null;
    }, ms);
}

function isInsideSearchUi(target) {
    if (!target || typeof target.closest !== 'function') return false;
    if (target.closest('.lead-search-dropdown-outer, .lead-search-dropdown-panel, .lead-search-date-backdrop, .lr-date-modal')) {
        return true;
    }
    if (target.closest('.search-wrapper, .search-filter-btn, .search-icon-btn, .search-clear-btn')) {
        return true;
    }
    const anchor = searchDropdownAnchorRef.value;
    const panel = searchDropdownPanelRef.value;
    if (anchor?.contains?.(target)) return true;
    if (panel?.contains?.(target)) return true;
    return false;
}

function closeSearchModal() {
    showSearchModal.value = false;
    searchInputFocused.value = false;
}

const openSearchModal = (event) => {
    event?.preventDefault?.();
    event?.stopPropagation?.();

    if (!canUseLeadSearchModal.value) {
        searchInputFocused.value = true;
        nextTick(() => {
            const searchInput = searchDropdownAnchorRef.value?.querySelector?.('.search-input');
            if (searchInput) searchInput.focus();
        });
        return;
    }

    armIgnoreOutsideClick(150);
    updateSearchDropdownPosition();
    searchModalMounted.value = true;
    showSearchModal.value = true;
    searchInputFocused.value = true;
    nextTick(() => updateSearchDropdownPosition());
};

function onSearchModalModelUpdate(val) {
    if (!val) {
        closeSearchModal();
        return;
    }
    showSearchModal.value = true;
}

function onKanbanLeadSearchLoading(e) {
    isSearchLoading.value = !!e?.detail?.loading;
}

let searchBlurTimeout = null;
function onSearchFocus() {
    if (searchBlurTimeout) {
        clearTimeout(searchBlurTimeout);
        searchBlurTimeout = null;
    }
    searchInputFocused.value = true;
    if (!canUseLeadSearchModal.value) return;
    openSearchModal();
}

function onSearchBlur() {
    // If focus leaves search input for a control inside the popup, keep it open.
    // If focus leaves search entirely (click outside), close automatically.
    searchBlurTimeout = setTimeout(() => {
        searchInputFocused.value = false;
        searchBlurTimeout = null;
        if (!showSearchModal.value) return;
        if (ignoreSearchOutsideClick) return;
        const active = document.activeElement;
        if (isInsideSearchUi(active)) return;
        closeSearchModal();
    }, 180);
}

function onDocumentPointerDown(e) {
    if (!showSearchModal.value) return;
    if (ignoreSearchOutsideClick) return;
    if (isInsideSearchUi(e.target)) return;
    closeSearchModal();
}

function onDocumentClick(e) {
    if (!showSearchModal.value) return;
    if (ignoreSearchOutsideClick) return;
    if (isInsideSearchUi(e.target)) return;
    closeSearchModal();
}

watch(showSearchModal, (open) => {
    if (open) {
        searchModalMounted.value = true;
        nextTick(updateSearchDropdownPosition);
    }
});
// BIG Profile Details panel (slide-in from right)
const isProfilePanelOpen = ref(false);
const showThemeModal = ref(false);
const profilePanel = ref(null);

function openThemeModal() {
  showThemeModal.value = true;
}

const notificationBell = ref(null);
const profileDropdown = ref(null);

function openProfilePanel() {
  if (notificationBell.value && notificationBell.value.showDropdown) {
    notificationBell.value.closeNotifications();
  }
  if (isProfilePanelOpen.value) {
    closeProfilePanel();
    return;
  }
  isProfilePanelOpen.value = true;
}

function closeProfilePanel() {
  isProfilePanelOpen.value = false;
}

function handleNotificationToggle() {
  if (isProfilePanelOpen.value) {
    closeProfilePanel();
  }
}

const lastActiveText = computed(() => {
  const raw = user.value?.last_login_at || user.value?.last_active;
  if (!raw) return '—';
  const d = new Date(raw);
  const now = new Date();
  const diffMs = now - d;
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} min ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
});

const memberSinceFormatted = computed(() => {
  const raw = user.value?.created_at || user.value?.member_since || user.value?.created_at;
  if (!raw) return '—';
  const d = new Date(raw);
  if (isNaN(d.getTime())) return raw;
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${day} ${h}:${min}`;
});

const firstName = computed(() => {
  const u = user.value;
  if (!u?.name) return '—';
  const parts = (u.name || '').trim().split(/\s+/);
  return parts[0] || '—';
});
const lastName = computed(() => {
  const u = user.value;
  if (!u?.name) return '—';
  const parts = (u.name || '').trim().split(/\s+/);
  return parts.length > 1 ? parts.slice(1).join(' ') : '—';
});
const notificationLanguage = computed(() => {
  const u = user.value;
  return u?.notification_language || u?.locale || 'English';
});

// Personal Info edit mode (First Name, Last Name, Email, Phone)
const isPersonalInfoEditing = ref(false);
const personalInfoEdit = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
});

function startPersonalInfoEdit() {
  const u = user.value;
  const parts = (u?.name || '').trim().split(/\s+/);
  personalInfoEdit.value = {
    first_name: parts[0] || '',
    last_name: parts.length > 1 ? parts.slice(1).join(' ') : '',
    email: u?.email || '',
    phone: u?.phone || '',
  };
  isPersonalInfoEditing.value = true;
}

function cancelPersonalInfoEdit() {
  isPersonalInfoEditing.value = false;
}

async function savePersonalInfoEdit() {
  const u = user.value;
  if (!u || !u.id) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'User data not found',
      confirmButtonColor: '#3085d6'
    });
    return;
  }

  try {
    // Get form values
    const first = (personalInfoEdit.value.first_name || '').trim();
    const last = (personalInfoEdit.value.last_name || '').trim();
    const email = (personalInfoEdit.value.email || '').trim();
    const phone = (personalInfoEdit.value.phone || '').trim();

    // Basic validation
    if (!first || !email) {
      Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'Please fill in all required fields (First Name and Email)',
        confirmButtonColor: '#3085d6'
      });
      return;
    }

    // Email domain validation - must be @oiaproperties.com
    const emailPattern = /^[a-zA-Z0-9._%+-]+@oiaproperties\.com$/;
    if (!emailPattern.test(email)) {
      Swal.fire({
        icon: 'error',
        title: 'Invalid Email',
        text: 'Email must be from @oiaproperties.com domain',
        confirmButtonColor: '#3085d6'
      });
      return;
    }

    // Create FormData
    const formData = new FormData();
    const fullName = [first, last].filter(Boolean).join(' ');
    
    formData.append('name', fullName);
    formData.append('email', email);
    if (phone) {
      formData.append('phone', phone);
    }
    formData.append('_method', 'PUT'); // For Laravel/PHP backend

    const token = localStorage.getItem('token');
    if (!token) {
      Swal.fire({
        icon: 'error',
        title: 'Authentication Error',
        text: 'Authentication token not found',
        confirmButtonColor: '#3085d6'
      });
      return;
    }

    const url = `${import.meta.env.VITE_API_URL || '/api'}/users/${u.id}`;
    
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      },
      body: formData
    });

    if (response.ok) {
      const result = await response.json();
      const updatedUser = result.data || result.user || result;
      
      // Update local user data
      user.value = {
        ...u,
        name: fullName,
        email: email,
        phone: phone,
        ...updatedUser
      };
      
      // Persist to localStorage
      try {
        localStorage.setItem('user', JSON.stringify(user.value));
      } catch (e) {
        console.warn('Could not persist user to localStorage', e);
      }
      
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Profile information updated successfully!',
        confirmButtonColor: '#3085d6',
        timer: 2000,
        showConfirmButton: true
      });
      
      isPersonalInfoEditing.value = false;
    } else {
      const errorText = await response.text();
      let errorData;
      try {
        errorData = JSON.parse(errorText);
      } catch (e) {
        errorData = { message: errorText };
      }

      if (errorData.errors) {
        const errorMessages = Object.values(errorData.errors).flat().join(', ');
        Swal.fire({
          icon: 'error',
          title: 'Validation Error',
          text: errorMessages,
          confirmButtonColor: '#3085d6'
        });
      } else {
        throw new Error(errorData.message || `Failed to update profile: ${response.status}`);
      }
    }

  } catch (error) {
    console.error("Error saving user profile:", error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: `Failed to update profile: ${error.message}`,
      confirmButtonColor: '#3085d6'
    });
  }
}

function teamMemberDisplayName(member) {
  if (member.first_name != null || member.last_name != null) {
    return [member.first_name, member.last_name].filter(Boolean).join(' ').trim() || member.name || '—';
  }
  return member.name || '—';
}

const avatarInput = ref(null);

function onAvatarChange(event) {
  const file = event.target?.files?.[0];
  if (!file || !file.type.startsWith('image/')) return;
  const reader = new FileReader();
  reader.onload = () => {
    const u = user.value;
    if (!u) return;
    user.value = { ...u, avatar: reader.result };
    try {
      localStorage.setItem('user', JSON.stringify(user.value));
    } catch (e) {
      console.warn('Could not persist user to localStorage', e);
    }
  };
  reader.readAsDataURL(file);
  event.target.value = '';
}

// Fetched from API: current user profile + team members (who is under this user by role)
const profileLoading = ref(false);
const fetchedTeamMembers = ref([]);

async function fetchProfileAndTeam() {
  const currentUser = user.value;
  if (!currentUser?.id) return;
  profileLoading.value = true;
  fetchedTeamMembers.value = [];
  try {
    const [profileRes, teamRes] = await Promise.all([
      api.get(`/users/${currentUser.id}`),
      api.get(`/users/${currentUser.id}/team-members/recursive`).catch(() => ({ data: { data: [] } })),
    ]);
    if (profileRes.data?.data) {
      const apiUser = profileRes.data.data;
      user.value = { ...currentUser, ...apiUser, name: apiUser.name ?? currentUser.name, email: apiUser.email ?? currentUser.email, phone: apiUser.phone ?? currentUser.phone, role_name: apiUser.role_name ?? currentUser.role_name, status: apiUser.status ?? currentUser.status, created_at: apiUser.created_at ?? currentUser.created_at, avatar: apiUser.avatar ?? currentUser.avatar };
    }
    const list = teamRes.data?.data;
    if (Array.isArray(list)) {
      fetchedTeamMembers.value = list.map((m) => ({
        id: m.id,
        name: m.name,
        first_name: m.first_name,
        last_name: m.last_name,
        email: m.email,
        phone: m.phone,
        avatar: m.avatar,
        role_name: m.role_name,
        role: m.role_name,
        status: m.status,
        online: m.status === 'active',
        created_at: m.created_at,
      }));
    }
  } catch (e) {
    console.warn('Profile/team fetch failed:', e);
  } finally {
    profileLoading.value = false;
  }
}

const teamMembersList = computed(() => {
  if (fetchedTeamMembers.value.length > 0) return fetchedTeamMembers.value;
  const list = user.value?.team_members || [];
  if (Array.isArray(list) && list.length) return list;
  return [];
});




const isShowOnlyListing = computed(() => {
  if (!user.value) return false;
  
  const isAdminUser = user.value.roles?.includes('only show listings');
  
  return isAdminUser;
});
const teamPageSize = 6;
const visibleTeamCount = ref(teamPageSize);

const visibleTeamMembers = computed(() => teamMembersList.value.slice(0, visibleTeamCount.value));

const hasMoreTeamMembers = computed(() => teamMembersList.value.length > visibleTeamCount.value);

function loadMoreTeamMembers() {
  visibleTeamCount.value += teamPageSize;
}

function showAllTeamMembers() {
  visibleTeamCount.value = teamMembersList.value.length;
}

// إعداد listener للنقر خارج dropdowns
function setupClickOutsideListener() {
  document.addEventListener('click', handleClickOutside);
}

function handleClickOutside(event) {
  // Notification dropdown: handled inside NotificationBell (Teleport + refs); avoid broken notificationDropdown ref here.

  if (isProfilePanelOpen.value && profilePanel.value && !profilePanel.value.contains(event.target) && profileDropdown.value && !profileDropdown.value.contains(event.target)) {
    closeProfilePanel();
  }
}

watch(isProfilePanelOpen, (open) => {
  if (open && user.value?.id) {
    visibleTeamCount.value = teamPageSize;
    fetchProfileAndTeam();
  }
});

watch(
  () => route.path,
  (path) => {
    if (isCrmRoute(path) || resolveCrmSection(path)) {
      restoreCrmSectionFromStorage()
    }
  },
)

// تحميل بيانات المستخدم والإعدادات
onMounted(() => {
  loadUserData();
  loadNotificationSettings();
  setupClickOutsideListener();
  fetchListingTabCounts();
   if (isCrmRoute(route.path)) {
     restoreCrmSectionFromStorage();
   }
     document.addEventListener('mousedown', onDocumentPointerDown, true);
     document.addEventListener('click', onDocumentClick);
     window.addEventListener('resize', onSearchDropdownReposition);
     window.addEventListener('scroll', onSearchDropdownReposition, true);
     window.addEventListener('kanban-lead-search-loading', onKanbanLeadSearchLoading);

     window.addEventListener('kanban-lead-search-update', (e) => {
    if (e.detail) {
      activeFilters.value = e.detail.activeFilters || []
      lastQuery.value = e.detail.query
      if (!e.detail.query?.search) {
        search.value = ''
      }
    }
  })

  window.addEventListener('kanban-deal-search-update', (e) => {
    if (e.detail) {
      activeFilters.value = e.detail.activeFilters || []
      lastQuery.value = e.detail.query
      if (!e.detail.query?.search) {
        search.value = ''
      }
    }
  })

  window.addEventListener('kanban-tab-change', onKanbanTabChangeFromPage)
  window.addEventListener('kanban-deal-type-change', onDealTypeChangeFromPage)
  loadStoredDealType()
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('mousedown', onDocumentPointerDown, true);
  document.removeEventListener('click', onDocumentClick);
  window.removeEventListener('resize', onSearchDropdownReposition);
  window.removeEventListener('scroll', onSearchDropdownReposition, true);
  window.removeEventListener('kanban-lead-search-loading', onKanbanLeadSearchLoading);
  window.removeEventListener('kanban-lead-search-update', () => {})
  window.removeEventListener('kanban-deal-search-update', () => {})
  window.removeEventListener('kanban-tab-change', onKanbanTabChangeFromPage)
  window.removeEventListener('kanban-deal-type-change', onDealTypeChangeFromPage)
  if (searchDebounceTimer.value) {
    clearTimeout(searchDebounceTimer.value);
    searchDebounceTimer.value = null;
  }
  if (ignoreSearchOutsideClickTimer) {
    clearTimeout(ignoreSearchOutsideClickTimer);
    ignoreSearchOutsideClickTimer = null;
  }
  if (searchBlurTimeout) {
    clearTimeout(searchBlurTimeout);
    searchBlurTimeout = null;
  }
});

function loadUserData() {
  const userData = localStorage.getItem('user');
  if (userData) {
    user.value = JSON.parse(userData);
  }
}

function loadNotificationSettings() {
  const soundSetting = localStorage.getItem('notification_sound');
  const browserSetting = localStorage.getItem('browser_notifications');
  
  if (soundSetting !== null) {
    soundEnabled.value = JSON.parse(soundSetting);
  }
  
  if (browserSetting !== null) {
    browserNotificationsEnabled.value = JSON.parse(browserSetting);
  }
}

function toggleSound() {
  localStorage.setItem('notification_sound', soundEnabled.value);
  console.log('🔊 Sound setting:', soundEnabled.value ? 'Enabled' : 'Disabled');
}

function toggleBrowserNotifications() {
  if (browserNotificationsEnabled.value) {
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          localStorage.setItem('browser_notifications', 'true');
          console.log('📱 Browser notifications enabled');
        } else {
          browserNotificationsEnabled.value = false;
          console.log('📱 Browser notifications permission denied');
        }
      });
    } else if (Notification.permission === 'denied') {
      browserNotificationsEnabled.value = false;
      console.log('📱 Browser notifications blocked by user');
    } else {
      localStorage.setItem('browser_notifications', 'true');
      console.log('📱 Browser notifications enabled');
    }
  } else {
    localStorage.setItem('browser_notifications', 'false');
    console.log('📱 Browser notifications disabled');
  }
}

function onMobileModuleTabChange(event) {
  const tabId = event?.target?.value;
  if (!tabId) return;
  if (moduleHeaderTabs.value.some((t) => t.id === tabId && t.type === 'deal-type')) {
    setActiveDealType(tabId);
    return;
  }
  if (moduleHeaderTabs.value.some((t) => t.id === tabId && t.type === 'event')) {
    setActiveKanbanTab(tabId);
    return;
  }
  const tab = moduleHeaderTabs.value.find((t) => t.id === tabId);
  if (tab?.path) {
    router.push(tab.path);
  }
}

function logout() {
  resetSidebarLayout();
  localStorage.removeItem('token');
  localStorage.removeItem('user');
  localStorage.removeItem('refreshToken');
  localStorage.removeItem('searchFilters');
  sessionStorage.removeItem('token');
  router.push('/sign-in');
}

const showBackButton = computed(() => {
  const hiddenRoutes = ['/kanban', '/kanban_deal'];

  return !hiddenRoutes.some(r => route.path.startsWith(r));
});
</script>

<style scoped>
/* Glass bar: brand gradient (matches style14.css tokens) */
.navbar-header {
  position: relative;
  top: auto;
  left: auto;
  right: auto;
  flex: 0 0 auto;
  width: 100%;
  z-index: 500 !important;
  height: auto;
  min-height: var(--app-topbar-height, 3.75rem);
  margin-bottom: var(--app-header-below-gap, 0.5rem);
  padding: 0.45rem 0.65rem;
  box-sizing: border-box;
  pointer-events: auto;
  display: flex;
  align-items: center;
  overflow: visible;
  border-radius: 12px;
  background: var(--gradient-crm-glass) !important;
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 4px 24px rgba(11, 7, 54, 0.08);
}

.navbar-header::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  border-radius: inherit;
  background: var(--gradient-crm);
  opacity: 0.1;
  pointer-events: none;
}

.navbar-header-toolbar {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  width: 100%;
  gap: 0.5rem;
  min-height: 0;
  flex: 1 1 auto;
  padding: 10px 0;
}

.navbar-header-toolbar--home {
  grid-template-columns: minmax(0, auto) minmax(0, 1fr) minmax(0, auto);
}

.navbar-header-center {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 0;
  padding: 0 0.5rem;
  pointer-events: auto;
}

/* Top shortcut menu: CRM, HRM, Accounts, Listings, Learnings */
.top-module-nav {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 4px;
  border-radius: 999px;
  background: rgba(8, 4, 40, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.14);
  flex-shrink: 0;
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
}

.top-module-nav::-webkit-scrollbar {
  display: none;
}

.top-module-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid transparent;
  color: rgba(255, 255, 255, 0.88);
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.2s ease, color 0.2s ease;
}

.top-module-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
}

.top-module-btn.active {
  background: #fff;
  color: #1a1330;
  border-color: rgba(255, 255, 255, 0.9);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

/* On main dashboard: module shortcuts are links only — no pill highlight */
.navbar-header--dashboard-home .top-module-btn.active {
  background: transparent;
  color: rgba(255, 255, 255, 0.88);
  border-color: transparent;
  box-shadow: none;
}

.navbar-header--dashboard-home .top-module-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
}

.module-tabs-nav--sub {
  margin-left: 4px;
}

.navbar-global-search-wrap {
  width: 100%;
  max-width: 520px;
  margin: 0 auto;
}

.navbar-global-search {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 40px;
  padding: 4px 6px 4px 16px;
  border-radius: 999px;
  background: rgba(8, 4, 40, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.navbar-global-search-input {
  flex: 1 1 auto;
  min-width: 0;
  border: none;
  background: transparent;
  color: #fff;
  font-size: 13px;
  font-weight: 500;
  outline: none;
}

.navbar-global-search-input::placeholder {
  color: rgba(255, 255, 255, 0.55);
}

.navbar-global-search-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background: transparent;
  color: rgba(255, 255, 255, 0.85);
  cursor: pointer;
  flex-shrink: 0;
}

.navbar-global-search-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
}

.navbar-settings-btn {
  width: 34px;
  height: 34px;
  border-color: rgba(255, 255, 255, 0.35) !important;
  color: #fff;
}

.navbar-header-left {
  justify-self: start;
  min-width: 0;
  display: flex;
  align-items: center;
  flex: 1 1 auto;
  overflow: hidden;
}

.navbar-header-left-row {
  display: flex;
  align-items: center;
  gap: 0.375rem 0.5rem;
  min-width: 0;
  flex: 1 1 auto;
  flex-wrap: nowrap;
}

.navbar-header-left-row .back-button {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  padding: 0;
  margin: 0;
}

.navbar-header-right {
  justify-self: end;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: nowrap;
  gap: 0.375rem;
  position: relative;
  z-index: 2;
  pointer-events: auto;
}

.navbar-header-back-icon {
  font-size: 1.125rem !important;
  width: 1.125rem;
  height: 1.125rem;
}

.navbar-header-menu-icon {
  font-size: 1.05rem !important;
  width: 1.125rem;
  height: 1.125rem;
}

.navbar-profile-img {
  width: 1.75rem;
  height: 1.75rem;
}

.navbar-create-listing {
  padding: 0.35rem 0.6rem !important;
  min-height: 32px;
  font-size: 0.75rem !important;
  line-height: 1.2;
}

.sidebar-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
}

.sidebar-mobile-toggle.menu-btn-with-label {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.sidebar-mobile-toggle .menu-btn-label {
  max-width: 0;
  overflow: hidden;
  opacity: 0;
  white-space: nowrap;
  transition: max-width 0.2s ease, opacity 0.2s ease;
  font-size: 0.875rem;
  font-weight: 500;
}

.sidebar-mobile-toggle:hover .menu-btn-label {
  max-width: 200px;
  opacity: 1;
}

.w-40-px {
  width: 32px;
}

.h-40-px {
  height: 32px;
}

.dropdown-menu.show {
  display: block;
  position: absolute;
  top: 100%;
  right: 0;
  left: auto;
  margin-top: 0.5rem;
  z-index: 10050 !important;
}

.hover-text-danger:hover {
  color: #ef4444 !important;
}

.create-property-btn {
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.3s ease;
}

.create-property-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Profile trigger */
.profile-trigger-wrap {
  position: relative;
  z-index: 1;
}

.profile-avatar-btn {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
}

/* BIG Profile Details panel – match design image (font sizes, spacing, colors) */
.profile-panel-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  /* Below Bootstrap modals (100600+); above notification portal (10050) */
  z-index: 50000;
  display: flex;
  justify-content: flex-end;
  font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, sans-serif;
}

.profile-panel {
  width: 713px;
  max-width: 95vw;
  height: 100%;
  background: #ffffff;
  box-shadow: -8px 0 32px rgba(0, 0, 0, 0.15);
  border-radius: 12px 0 0 12px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.profile-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
  background: #fff;
}

.profile-panel-title {
  margin: 0 !important;
  font-size: 18px !important;
  font-weight: 700 !important;
  color: #111827 !important;
  letter-spacing: -0.01em !important;
  line-height: 1.3 !important;
}

.profile-panel-close {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  color: #6b7280;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: color 0.2s, background 0.2s;
}

.profile-panel-close:hover {
  color: #111827;
  background: #f3f4f6;
}

.profile-panel-close .icon {
  font-size: 20px;
}

.profile-panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.profile-panel-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 32px;
  color: #6b7280;
  font-size: 14px;
}

.profile-panel-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid #e5e7eb;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: profile-spin 0.8s linear infinite;
}

@keyframes profile-spin {
  to { transform: rotate(360deg); }
}

/* Summary card – like design image */
.profile-summary-card {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px;
  background: #f3f4f6;
  border-radius: 12px;
  margin-bottom: 28px;
  border: 1px solid #e5e7eb;
}

.profile-summary-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.profile-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.profile-summary-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-avatar-camera {
  position: absolute;
  right: -1px;
  bottom: -1px;
  width: 28px;
  height: 28px;
  background: #22c55e;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #f3f4f6;
  cursor: pointer;
}

.profile-avatar-file-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.profile-avatar-camera .icon {
  font-size: 20px;
}

.profile-summary-info {
  min-width: 0;
}

.profile-summary-name {
  margin: 0 0 4px;
  font-size: 17px;
  font-weight: 700;
  color: #111827;
  line-height: 1.3;
  letter-spacing: -0.01em;
}

.profile-summary-email,
.profile-summary-role {
  margin: 0 !important;
  font-size: 13px !important;
  color: #6b7280 !important;
  line-height: 1.45 !important;
}

.profile-summary-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

.profile-status-row {
  display: flex;
  align-items: center;
  gap: 5px;
}

.profile-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #9ca3af;
}

.profile-status-dot.status-offline {
  background: #ef4444;
}

.profile-status-dot.status-online {
  background: #22c55e;
}

.profile-status-text {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.profile-more-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: #6b7280;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.profile-more-btn:hover {
  background: #e5e7eb;
  color: #111827;
}

.profile-more-btn .icon {
  font-size: 20px;
}

.profile-last-active {
  margin: 0;
  font-size: 14px;
  color: #9ca3af;
}

/* Sections – base */
.profile-section {
  margin-bottom: 0;
}

.profile-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

/* Contact Information – no blue borders */
.profile-section-contact {
  margin-bottom: 28px;
  padding: 20px 22px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.profile-section-contact .profile-section-head {
  margin-bottom: 18px;
}

/* Your Team – no blue borders */
.profile-section-team {
  padding: 20px 22px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.profile-section-team .profile-section-head {
  margin-bottom: 16px;
}

.profile-section-title {
  margin: 0 !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  color: #111827 !important;
  letter-spacing: -0.01em !important;
  line-height: 1.3 !important;
}

.profile-section-badge {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  background: #e5e7eb;
  padding: 4px 8px;
  border-radius: 4px;
  letter-spacing: 0.02em;
}

.profile-edit-icon {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  color: #6b7280;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.profile-edit-icon:hover {
  background: #f3f4f6;
  color: #111827;
}

.profile-edit-icon .icon {
  font-size: 20px;
}

.profile-contact-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px 32px;
}

.profile-contact-two-cols {
  grid-template-columns: 1fr 1fr;
  gap: 18px 32px;
}

.profile-contact-readonly {
  color: #374151;
}

.profile-contact-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.profile-contact-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  line-height: 1.3;
}

.profile-contact-value {
  font-size: 15px;
  color: #111827;
  font-weight: 500;
  line-height: 1.45;
}

.profile-contact-input {
  width: 100%;
  font-size: 15px;
  color: #111827;
  font-weight: 500;
  line-height: 1.45;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  outline: none;
  transition: border-color 0.2s;
}

.profile-contact-input:focus {
  border-color: #60a5fa;
}

.profile-contact-input::placeholder {
  color: #9ca3af;
}

.profile-contact-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.profile-contact-btn {
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  border: none;
  transition: background 0.2s, color 0.2s;
}

.profile-contact-cancel {
  background: #f3f4f6;
  color: #6b7280;
}

.profile-contact-cancel:hover {
  background: #e5e7eb;
  color: #111827;
}

.profile-contact-save {
  background: #2563eb;
  color: #fff;
}

.profile-contact-save:hover {
  background: #1d4ed8;
}

.profile-phone-link {
  color: #2563eb;
  text-decoration: none;
  font-size: 15px;
  font-weight: 500;
}

.profile-phone-link:hover {
  text-decoration: underline;
}

/* Your Team – 3-column grid, oval pill cards, avatar + name + role, status dot */
.profile-team-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px 12px;
  margin-bottom: 20px;
}

.profile-team-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  text-align: left;
  min-width: 0;
}

.profile-team-avatar-wrap {
  position: relative;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}

.profile-team-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-team-agent-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #e5e7eb;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
}

.profile-team-agent-icon .icon {
  font-size: 20px;
}

.profile-team-status-dot {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 10px;
  height: 10px;
  border: 2px solid #ffffff;
  border-radius: 50%;
}

.profile-team-status-dot.status-online {
  background: #22c55e;
}

.profile-team-status-dot.status-offline {
  background: #ef4444;
}

.profile-team-status-dot.status-away {
  background: #38bdf8;
}

.profile-team-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.profile-team-pill .profile-team-name {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-team-role {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-team-see-more-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 4px;
}

.profile-see-more-btn {
  font-size: 14px;
  font-weight: 600;
  color: #2563eb;
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px 0;
  text-align: left;
}

.profile-see-more-btn:hover {
  text-decoration: underline;
}

.profile-show-all-team {
  font-size: 14px;
  font-weight: 600;
  color: #2563eb;
  text-decoration: none;
}

.profile-show-all-team:hover {
  text-decoration: underline;
}

.profile-team-empty {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
  padding: 12px 0;
}

.profile-quick-menu {
  margin-top: 10px;
  padding: 4px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #e8ecf1;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.profile-quick-menu > :not(:first-child) {
  border-top: 1px solid #f1f5f9;
}

.profile-quick-menu-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: none;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  text-align: left;
  text-decoration: none;
  color: inherit;
  transition: background 0.15s ease;
}

.profile-quick-menu-item:hover {
  background: #f8fafc;
}

.profile-quick-menu-item--logout:hover .profile-quick-menu-label,
.profile-quick-menu-item--logout:hover .profile-quick-menu-icon {
  color: #dc2626;
}

.profile-quick-menu-icon {
  font-size: 16px;
  color: #475569;
  flex-shrink: 0;
}

.profile-quick-menu-label {
  flex: 1;
  font-size: 12px;
  font-weight: 500;
  color: #1e293b;
}

.profile-quick-menu-chevron {
  font-size: 14px;
  color: #94a3b8;
  flex-shrink: 0;
}

/* Panel transition */
.profile-panel-enter-active,
.profile-panel-leave-active {
  transition: opacity 0.25s ease;
}

.profile-panel-enter-active .profile-panel,
.profile-panel-leave-active .profile-panel {
  transition: transform 0.25s ease;
}

.profile-panel-enter-from,
.profile-panel-leave-to {
  opacity: 0;
}

.profile-panel-enter-from .profile-panel,
.profile-panel-leave-to .profile-panel {
  transform: translateX(100%);
}

@media (max-width: 768px) {
  .sidebar-mobile-toggle {
    display: none !important;
  }
}

/* ========== KANBAN / MODULE TABS ========== */
.module-tabs-nav,
.kanban-tabs-nav {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 4px;
  flex: 1 1 auto;
  min-width: 0;
  flex-wrap: nowrap;
  overflow-x: auto;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}

.module-tabs-nav::-webkit-scrollbar,
.kanban-tabs-nav::-webkit-scrollbar {
  display: none;
}

.module-tab-btn,
.kanban-tab-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 7px 14px;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 10px;
  color: rgba(255, 255, 255, 0.72);
  font-size: 13px;
  font-weight: 700;
  line-height: 1.2;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  text-decoration: none;
  white-space: nowrap;
}

.module-tab-btn:hover,
.kanban-tab-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.12);
}

/* Unified pill highlight — no underline (CRM, Listings, Settings, Agents) */
.module-tab-btn.active,
.kanban-tab-btn.active {
  color: #fff;
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.28);
  box-shadow:
    0 2px 8px rgba(0, 0, 0, 0.18),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.module-tab-count {
  margin-left: 6px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: #f59e0b;
  color: #1e1b2e;
  font-size: 10px;
  font-weight: 800;
  line-height: 18px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.module-tab-btn.active .module-tab-count {
  background: #fff;
  color: #5b3d8f;
}

.active-indicator {
  display: none;
}

/* Kanban Search */
.kanban-search-wrapper {
  position: relative;
}

.search-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 999px;
  padding: 6px 16px;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.search-trigger:hover {
  background: rgba(255, 255, 255, 0.25);
  border-color: rgba(255, 255, 255, 0.4);
}

.search-icon {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.9);
}

.search-placeholder {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.8);
  font-weight: 500;
}

.search-badge {
  background: #22c55e;
  color: white;
  font-size: 10px;
  font-weight: bold;
  border-radius: 999px;
  padding: 2px 8px;
  margin-left: 4px;
}

/* Kanban Create Button */
.btn-create-new {
    padding: .35rem .6rem !important;
    min-height: 32px;
    font-size: .75rem !important;
    line-height: 1.2;
    font-weight: 500;
    border-radius: 6px;
    text-decoration: none;
    transition: all .3s ease;
}

.btn-create-new:hover {
  background: linear-gradient(90deg, #16a34a, #22c55e);
  transform: translateY(-1px);
}

.btn-create-new-text {
  font-size: 13px;
  font-weight: 600;
  color: #ffffff;
}

.btn-create-new-icon {
  color: #ffffff;
  font-size: 16px;
}

/* Search Modal */

/* Mobile responsive */
@media (max-width: 768px) {
  .top-module-nav {
    display: none;
  }

  .navbar-global-search-wrap {
    display: none;
  }

  .navbar-header:not(.navbar-header--dashboard-home) .navbar-header-center {
    display: none;
  }
  
  .search-placeholder {
    display: none;
  }
  
  .btn-create-new-text {
    display: none;
  }
  
  .btn-create-new {
    padding: 6px 10px;
  }
}
/* ========== SEARCH INPUT STYLES (same as Kanban) ========== */
.search-area-column {
    align-items: flex-end;
    position: relative;
    z-index: 501;
}

.lead-search-dropdown-outer--teleport {
    pointer-events: auto;
}

.lead-search-dropdown-outer--teleport :deep(.lead-search-dropdown-panel) {
    position: relative;
    z-index: 15000;
    box-shadow: 0 16px 48px rgba(11, 7, 54, 0.28);
}

.search-wrapper {
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.32);
    border-radius: 999px;
    height: 38px;
    min-height: 38px;
    gap: 4px;
    padding: 4px 10px 4px 8px;
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    width: max-content;
    max-width: 900px;
    min-width: 460px;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    transition: max-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1), min-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1), border-color 0.2s ease;
    cursor: text;
}

.search-wrapper:hover,
.search-wrapper:focus-within {
    background: rgba(255, 255, 255, 0.26);
    border-color: rgba(255, 255, 255, 0.45);
}

.search-wrapper-expanded {
    max-width: 1080px;
    min-width: 560px;
}

.search-wrapper-tall {
    max-width: 760px;
    min-width: 620px;
}

.search-filters-pills {
    flex: 1 1 0;
    min-width: 0;
    gap: 6px 8px;
    flex-wrap: nowrap;
    overflow: hidden;
}

.search-tag {
    background: rgba(251, 191, 36, 0.24);
    border: 1px solid rgba(251, 191, 36, 0.58);
    border-radius: 999px;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    color: #fef9c3;
    white-space: nowrap;
    width: fit-content;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
}

.search-tag-more {
    flex-shrink: 0;
}

.search-tag-more-text {
    cursor: pointer;
    user-select: none;
}

.close-tag-icon {
    font-size: 12px;
    cursor: pointer;
    color: #78350f;
    background: rgba(251, 191, 36, 0.85);
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-icon-btn,
.search-filter-btn,
.search-clear-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
    margin: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: rgba(255, 255, 255, 0.85);
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    transition: color 0.15s ease, background 0.15s ease;
}

.search-filter-btn {
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
}

.search-icon-btn:hover,
.search-filter-btn:hover,
.search-clear-btn:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
}

.search-input-container {
    color: rgba(255, 255, 255, 0.92);
    height: 28px;
    min-height: 28px;
    display: flex;
    align-items: center;
    flex: 1 1 auto;
    min-width: 200px;
    width: 100%;
    max-width: 100%;
    position: relative;
    transition: min-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1), max-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.search-input-spinner {
    position: absolute;
    right: 8px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.25);
    border-top-color: #fff;
    animation: search-spin 0.7s linear infinite;
    pointer-events: none;
}

.search-input--loading {
    padding-right: 28px !important;
}

@keyframes search-spin {
    to { transform: rotate(360deg); }
}

.search-input-container-tall {
    min-width: 320px;
    max-width: 100%;
}

.search-input {
    width: 100%;
    font-size: 13px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.95) !important;
    padding: 0 4px !important;
    height: 100% !important;
    min-height: 24px;
    border: none !important;
    outline: none !important;
    background: transparent !important;
    box-shadow: none !important;
}

.search-input-container :deep(input)::placeholder,
.search-input-container :deep(.form-control)::placeholder,
.search-input::placeholder {
    color: rgba(255, 255, 255, 0.55) !important;
    font-size: 11px !important;
    font-weight: 400 !important;
    letter-spacing: -0.01em;
    line-height: 1.25;
    opacity: 1;
}

.search-input--has-selection {
    color: #fde68a !important;
    font-weight: 600;
}

.search-wrapper-has-selection {
    border-color: rgba(251, 191, 36, 0.55) !important;
    background: rgba(11, 7, 54, 0.58) !important;
}

/* Search Modal Styles */
.kanban-search-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    z-index: 99999;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 80px;
}

.kanban-search-modal {
    width: 600px;
    max-width: 90vw;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .search-wrapper {
        min-width: 180px;
    }
    
    .search-filters-pills {
        display: none;
    }
    
    .search-input-container {
        max-width: 120px;
    }
    
    .search-placeholder {
        display: none;
    }
    
    .btn-create-new-text {
        display: none;
    }
    
    .btn-create-new {
        padding: 6px 10px;
    }
    
    .lead-search-dropdown-outer--teleport {
        left: 12px !important;
        right: 12px !important;
        width: calc(100vw - 24px) !important;
        max-width: calc(100vw - 24px) !important;
    }
}
.action-icon-btn {
 background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.55) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    color: rgba(255, 255, 255, 0.95);
}

.action-icon-btn:hover {
    background: rgba(255, 255, 255, 0.22);
    border-color: rgba(255, 255, 255, 0.7) !important;
    color: #fff;
}

.action-icon-btn:focus {
    outline: none !important;
    box-shadow: none !important;
}

:deep(.action-icon-btn-dropdown .action-icon-btn) {
        color: rgba(255, 255, 255, 0.95) !important;
}

.radius-circle {
    border-radius: 50%;
}

.module-tab-select {
  display: none;
  flex: 1 1 auto;
  min-width: 0;
  max-width: 148px;
  height: 36px;
  min-height: 36px;
  padding: 0 28px 0 10px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  background: rgba(11, 7, 54, 0.55);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  font-family: Montserrat, Inter, system-ui, sans-serif;
  line-height: 36px;
  box-sizing: border-box;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  cursor: pointer;
}

/* Mobile module toolbar (Kanban + Listings) hidden on desktop */
.mob-module-toolbar,
.kanban-mob-toolbar {
  display: none;
}

@media (max-width: 768px) {
  #app {
    --app-topbar-height: 5.5rem;
  }

  .navbar-header {
    min-height: var(--app-topbar-height, 5.5rem);
    height: auto;
    padding: 6px 8px;
    border-radius: 14px;
  }

  .navbar-header-toolbar {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto;
    gap: 6px;
    align-items: stretch;
  }

  .navbar-header-left,
  .navbar-header-right {
    justify-self: stretch;
    width: 100%;
  }

  .navbar-header-left-row {
    flex-wrap: nowrap;
    gap: 6px;
  }

  .module-tab-select {
    display: block;
    max-width: none;
    height: 44px;
    min-height: 44px;
    line-height: 44px;
    padding: 0 40px 0 16px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.06) 100%);
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.02em;
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.12),
      0 2px 10px rgba(0, 0, 0, 0.18);
  }

  .module-tabs-nav--hide-on-mobile {
    display: none !important;
  }

  .module-tabs-nav:not(.module-tabs-nav--hide-on-mobile) {
    flex: 1 1 auto;
    min-width: 0;
    overflow-x: auto;
    scroll-snap-type: x proximity;
    padding-bottom: 2px;
  }

  .module-tab-btn,
  .kanban-tab-btn {
    flex: 0 0 auto;
    scroll-snap-align: start;
    min-height: 36px;
    padding: 8px 12px;
    font-size: 12px;
  }

  .navbar-header-right {
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
  }

  .search-area-column {
    order: 10;
    width: 100%;
    max-width: 100%;
    align-items: stretch !important;
  }

  .search-wrapper {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    min-height: 40px;
    height: 40px;
  }

  .search-input-container {
    max-width: none;
    flex: 1 1 auto;
  }

  .search-icon-btn,
  .search-filter-btn,
  .search-clear-btn {
    min-width: 36px;
    min-height: 36px;
  }

  .btn-create-new,
  .action-icon-btn,
  .profile-avatar-btn {
    min-width: 40px;
    min-height: 40px;
  }

  .navbar-create-listing {
    min-height: 40px;
  }

  /* Kanban mobile only — compact header (Leads row + search) */
  .navbar-header.navbar-header--kanban-mobile {
    --app-topbar-height: 6rem;
    min-height: var(--app-topbar-height);
    padding: 8px 10px 10px;
    border-radius: 0;
    left: 0;
    right: 0;
  }

  .navbar-header.navbar-header--kanban-mobile .navbar-header-toolbar {
    display: flex;
    flex-direction: column;
    grid-template-columns: unset;
    grid-template-rows: unset;
    gap: 8px;
  }

  .navbar-header.navbar-header--mobile-compact .mob-module-toolbar,
  .navbar-header.navbar-header--kanban-mobile .mob-module-toolbar {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    width: 100%;
    min-width: 0;
  }

  .navbar-header.navbar-header--mobile-compact:not(.navbar-header--kanban-mobile) {
    --app-topbar-height: 3.25rem;
    min-height: var(--app-topbar-height);
    padding: 8px 10px;
    border-radius: 0;
    left: 0;
    right: 0;
  }

  .navbar-header.navbar-header--mobile-compact:not(.navbar-header--kanban-mobile) .navbar-header-toolbar {
    display: flex;
    flex-direction: column;
    grid-template-columns: unset;
    grid-template-rows: unset;
    gap: 0;
  }

  .navbar-header.navbar-header--mobile-compact:not(.navbar-header--kanban-mobile) .mob-module-toolbar {
    gap: 0;
  }

  .mob-module-title {
    display: flex;
    align-items: center;
    width: 100%;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.06) 100%);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    font-family: Montserrat, Inter, system-ui, sans-serif;
    letter-spacing: 0.02em;
    line-height: 1.2;
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.12),
      0 2px 10px rgba(0, 0, 0, 0.18);
    box-sizing: border-box;
  }

  .kanban-mob-toolbar__main {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
  }

  .mob-header-menu {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    border: none;
    border-radius: 10px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    transition: background 0.15s ease;
  }

  .mob-header-menu:active {
    background: rgba(255, 255, 255, 0.22);
  }

  .mob-header-back {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    border: none;
    border-radius: 50%;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    transition: background 0.15s ease;
  }

  .mob-header-back:active {
    background: rgba(255, 255, 255, 0.22);
  }

  .kanban-mob-lead-select-wrap--detail {
    flex: 1;
    min-width: 0;
  }

  .kanban-mob-lead-select-wrap--detail::after {
    display: none;
  }

  .mob-module-title--detail {
    justify-content: flex-start;
    min-height: 40px;
    padding: 0 12px;
    border: none;
    background: transparent;
    box-shadow: none;
    font-size: 16px;
    font-weight: 700;
  }

  .kanban-mob-lead-select-wrap {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
    position: relative;
  }

  .kanban-mob-lead-select-wrap::after {
    content: '';
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 6px solid rgba(255, 255, 255, 0.92);
    pointer-events: none;
  }

  .kanban-mob-lead-select {
    width: 100%;
    min-width: 0;
    height: 44px;
    min-height: 44px;
    padding: 0 40px 0 16px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.06) 100%);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    font-family: Montserrat, Inter, system-ui, sans-serif;
    letter-spacing: 0.02em;
    line-height: 44px;
    box-sizing: border-box;
    appearance: none;
    -webkit-appearance: none;
    background-image: none;
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.12),
      0 2px 10px rgba(0, 0, 0, 0.18);
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  }

  .kanban-mob-lead-select:focus {
    outline: none;
    border-color: rgba(183, 148, 246, 0.65);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.14),
      0 0 0 3px rgba(115, 62, 135, 0.35);
  }

  .kanban-mob-lead-select option {
    font-weight: 600;
    color: #0b0736;
    background: #fff;
    padding: 10px;
  }

  .kanban-mob-toolbar__actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
  }

  .kanban-mob-create {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    border: none;
    border-radius: 50%;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #12b981 0%, #22c55e 100%);
    color: #fff;
    font-size: 20px;
    box-shadow: 0 2px 8px rgba(34, 197, 94, 0.45);
    cursor: pointer;
  }

  .kanban-mob-icon-btn {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    min-width: 38px;
    min-height: 38px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: 18px;
    cursor: pointer;
  }

  .kanban-mob-profile-btn {
    width: 38px;
    height: 38px;
    min-width: 38px;
    min-height: 38px;
    padding: 0;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.15);
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .kanban-mob-profile-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .kanban-mob-toolbar__search .search-area-column {
    width: 100%;
    max-width: 100%;
    align-items: stretch !important;
  }

  .kanban-mob-toolbar__search .search-wrapper {
    width: 100%;
    min-height: 40px;
    height: 40px;
    border-radius: 12px;
    padding: 0 8px;
    gap: 6px;
  }

  .kanban-mob-toolbar__search .search-input-container {
    max-width: none;
    flex: 1 1 auto;
    min-width: 0;
  }

  .kanban-mob-toolbar__search .search-input-container :deep(input)::placeholder,
  .kanban-mob-toolbar__search .search-input::placeholder {
    font-size: 11px !important;
  }

  .kanban-mob-toolbar__search .search-filters-pills {
    flex: 1 1 auto;
    min-width: 0;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .kanban-mob-toolbar__search .search-filters-pills::-webkit-scrollbar {
    display: none;
  }

  .kanban-mob-toolbar__search .search-wrapper-has-filters {
    padding-inline: 6px 4px;
  }

  .kanban-mob-toolbar__search .search-filter-btn {
    display: inline-flex !important;
  }

  .navbar-header.navbar-header--kanban-mobile :deep(.notification-bell-wrap) {
    flex-shrink: 0;
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .module-tabs-nav {
    max-width: min(52vw, 420px);
  }
}

</style>