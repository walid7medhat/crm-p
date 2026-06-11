<template>
    <!-- Modal mode -->
    <b-modal
        v-if="!asDropdown"
        id="lead-search-modal"
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0"
    >
        <div class="lead-search-shell">
        <div v-if="isInitializing" class="lead-search-initial-loader" aria-live="polite">
            <div class="lead-search-initial-loader__spinner"></div>
            <span class="lead-search-initial-loader__text">Restoring search…</span>
        </div>
        <div class="lead-search-container d-flex">
            <div class="sidebar-pills p-4 d-flex flex-column gap-3 border-end">
                <button
                    v-for="pill in sidebarPills"
                    :key="pill.id"
                    class="pill-btn"
                    :class="{ 'active': activePill === pill.id }"
                    @click="handleSidebarPillClick(pill)"
                >
                    <span>{{ pill.label }}</span>
                    <span v-if="pill.type === 'city'" class="pill-count">{{ pill.children.length }}</span>
                    <span v-if="pill.type === 'city' && isCitySelected(pill.id)" class="selected-indicator">✓</span>
                </button>
                <transition name="city-child-list">
                    <div
                        v-if="activeCityPill"
                        class="city-children-wrap"
                    >
                        <div class="city-children-title">Branches in {{ activeCityPill.label }}</div>
                        <button
                            v-for="child in activeCityPill.children"
                            :key="`city_child_${child.value}`"
                            type="button"
                            class="city-child-btn"
                            :class="{ active: isBranchSelected(child.value) }"
                            @click="selectCityBranch(activeCityPill, child)"
                        >
                            {{ child.text }}
                        </button>
                        <div v-if="activeCityPill.children.length === 0" class="city-children-empty">
                            No branches available
                        </div>
                    </div>
                </transition>
            </div>
            <div class="form-content-wrapper flex-grow-1 position-relative">
                <button class="close-btn" @click="show = false">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
                <div class="search-sections-wrap">
                    <div v-for="section in visibleSearchSections" :key="`modal_${section.id}`" class="search-section-card">
                        <div class="search-section-title">{{ section.title }}</div>
                        <div class="row g-4">
                    <template v-for="field in section.fields" :key="field.id">
                        <div class="col-md-6 mt-3">
                            <label class="form-label-custom">{{ field.label }}</label>
                            <button
                                v-if="field.id === 'created_on' || field.id === 'assigned_on'"
                                type="button"
                                class="custom-date-trigger"
                                @click.stop="openDatePicker(field.id, $event)"
                            >
                                <span>{{ field.id === 'assigned_on' ? assignedOnDisplay : createdOnDisplay }}</span>
                                <iconify-icon icon="lucide:calendar-days" />
                            </button>
                            <div
                                v-else-if="field.id === 'budget_from'"
                                ref="budgetTriggerRef"
                                class="budget-field-wrap"
                            >
                                <button
                                    type="button"
                                    class="custom-date-trigger"
                                    @click.stop="toggleBudgetDropdown"
                                >
                                    <span>{{ budgetDisplay }}</span>
                                    <iconify-icon icon="lucide:chevron-down" />
                                </button>
                            </div>
                            <div v-else-if="field.id === 'purpose_purchase' && form.leadType !== 'rent'" class="col-md-6 mt-3">
                                <label class="form-label-custom">{{ field.label }}</label>
                                <v-select
                                    v-model="form[field.formKey]"
                                    :options="field.options"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    :placeholder="field.placeholder || 'Select'"
                                    :clearable="hasValue(form[field.formKey])"
                                    append-to-body
                                    class="custom-v-select"
                                >
                                    <template #open-indicator="{ attributes }">
                                        <span v-bind="attributes">
                                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                        </span>
                                    </template>
                                </v-select>
                            </div>
                            <CrmPhoneInput
                                v-else-if="field.type === 'text' && field.id === 'work_phone'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder || 'Enter Phone'"
                            />
                            <b-form-input
                                v-else-if="field.type === 'text' && field.id !== 'budget_to'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder"
                                class="custom-input"
                            />
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'location'"
                                v-model="form.areaId"
                                :options="areaOptions"
                                :reduce="area => area.id"
                                label="name"
                                placeholder="Select area"
                                :clearable="hasValue(form.areaId)"
                                append-to-body
                                class="custom-v-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                <template #option="option">
                                    <div class="location-option">
                                        <i class="ri-map-pin-line location-option-icon"></i>
                                        <div class="location-option-text">
                                            <span class="location-option-name">{{ locationFirstLine(option) }}</span>
                                            <span class="location-option-subtitle">{{ locationSecondLine(option) }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="option" class="location-selected">
                                        <span class="location-selected-name">{{ locationFirstLine(option) }}</span>
                                        <span class="location-selected-subtitle">{{ locationSecondLine(option) }}</span>
                                    </div>
                                </template>
                            </v-select>
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'responsible_person'"
                                v-model="form.responsible"
                                :options="personOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Person'"
                                :clearable="hasValue(form.responsible)"
                                append-to-body
                                class="custom-v-select lead-search-rp-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                <template #option="option">
                                    <div v-if="option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Person
                                    </div>
                                    <div v-else class="lead-rp-opt d-flex align-items-center gap-2">
                                        <img
                                            :src="option.avatar || DEFAULT_RESPONSIBLE_AVATAR"
                                            alt=""
                                            class="lead-rp-opt-avatar"
                                        />
                                        <div class="lead-rp-opt-info min-w-0 flex-grow-1">
                                            <div class="lead-rp-opt-name-row d-flex align-items-center flex-wrap gap-1">
                                                <span class="user-item-name">{{ option.text }}</span>
                                                <span v-if="option.role_name" class="user-position-badge">{{ option.role_name }}</span>
                                            </div>
                                            <div class="user-item-meta-line">
                                                <span class="meta-value">{{ option.parent_name }}</span>
                                                <span v-if="option.branch_name" class="meta-divider">|</span>
                                                <span v-if="option.branch_name" class="meta-value">{{ option.branch_name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="!option || option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Person
                                    </div>
                                    <div v-else class="lead-rp-sel d-flex align-items-center gap-2 min-w-0">
                                        <img
                                            :src="option.avatar || DEFAULT_RESPONSIBLE_AVATAR"
                                            alt=""
                                            class="lead-rp-sel-avatar"
                                        />
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="lead-rp-sel-name text-truncate fw-semibold">{{ option.text }}</div>
                                            <div
                                                v-if="option.parent_name || option.branch_name"
                                                class="lead-rp-sel-meta text-truncate small text-muted"
                                            >
                                                {{ [option.parent_name, option.branch_name].filter(Boolean).join(' | ') }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-select>
                             <v-select
                                v-else-if="field.type === 'select' && field.id === 'team'"
                                v-model="form.team"
                                :options="computedTeamOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Team'"
                                :clearable="hasValue(form.team)"
                                append-to-body
                                class="custom-v-select lead-search-rp-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                <template #option="option">
                                    <div v-if="option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Team
                                    </div>
                                    <div v-else class="lead-rp-opt d-flex align-items-center gap-2">
                                        <img
                                            :src="option.avatar || DEFAULT_TEAM_AVATAR"
                                            alt=""
                                            class="lead-rp-opt-avatar"
                                        />
                                        <div class="lead-rp-opt-info min-w-0 flex-grow-1">
                                            <div class="lead-rp-opt-name-row d-flex align-items-center flex-wrap gap-1">
                                                <span class="user-item-name">{{ option.text }}</span>
                                                <span v-if="option.role_name" class="user-position-badge">{{ option.role_name }}</span>
                                            </div>
                                            <div class="user-item-meta-line">
                                                <span v-if="option.parent_name" class="meta-value">{{ option.parent_name }}</span>
                                                <span v-if="option.parent_name && option.branch_name" class="meta-divider">|</span>
                                                <span v-if="option.branch_name" class="meta-value">{{ option.branch_name }}</span>
                                                <span v-if="!option.parent_name && !option.branch_name && option.team_size" class="meta-value">{{ option.team_size }} members</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="!option || option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Team
                                    </div>
                                    <div v-else class="lead-rp-sel d-flex align-items-center gap-2 min-w-0">
                                        <img
                                            :src="option.avatar || DEFAULT_TEAM_AVATAR"
                                            alt=""
                                            class="lead-rp-sel-avatar"
                                        />
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="lead-rp-sel-name text-truncate fw-semibold">{{ option.text }}</div>
                                            <div v-if="option.parent_name || option.branch_name || option.team_size" class="lead-rp-sel-meta text-truncate small text-muted">
                                                {{ [option.parent_name, option.branch_name, option.team_size ? `${option.team_size} members` : null].filter(Boolean).join(' | ') }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-select>
                            <v-select
                                v-else-if="field.type === 'select' && field.id !== 'office' && field.id !== 'responsible_person' && field.id !== 'team'"
                                v-model="form[field.formKey]"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select'"
                                :clearable="hasValue(form[field.formKey])"
                                append-to-body
                                class="custom-v-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <v-select
                                v-if="field.id === 'source' && form.source === 'website'"
                                v-model="form.sourceWebsite"
                                :options="websiteSourceOptionsForMulti"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select websites"
                                :clearable="form.sourceWebsite && form.sourceWebsite.length > 0"
                                multiple
                                filterable
                                append-to-body
                                class="custom-v-select mt-2 office-multi-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <v-select
                                v-if="field.id === 'source' && form.source === 'portal'"
                                v-model="form.sourcePortal"
                                :options="portalSourceOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select Portals"
                                :clearable="form.sourcePortal && form.sourcePortal.length > 0"
                                multiple
                                filterable
                                append-to-body
                                class="custom-v-select mt-2 office-multi-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <!-- Multi-select for office/branch -->
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'office'"
                                v-model="form.office"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Branches'"
                                :clearable="form.office && form.office.length > 0"
                                append-to-body
                                multiple
                                class="custom-v-select office-multi-select"
                                @update:model-value="handleOfficeChange"
                                @click.stop
                                @mousedown.stop
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>
                    </template>
                        </div>
                    </div>
                </div>
                <div class="search-modal-footer d-flex align-items-center justify-content-between mt-3 pt-4">
                    <div class="d-flex gap-4">
                        <a href="#" class="footer-link text-decoration-underline" @click.prevent="showFilterSettings = true">Add Field</a>
                        <a href="#" class="footer-link text-secondary" @click.prevent="restoreDefaultFields">Restore default fields</a>
                    </div>
                    <div class="d-flex gap-3">
                        <button class="btn-reset" :disabled="searching" @click="resetForm">Reset</button>
                        <button class="btn-search" :disabled="searching" @click="applySearch">
                            <iconify-icon v-if="searching" icon="lucide:loader-2" class="btn-search-spinner" />
                            <span>{{ searching ? 'Searching…' : 'Search' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </b-modal>

    <!-- Dropdown mode: panel under search input -->
    <div v-else class="lead-search-dropdown-panel">
        <div v-if="isInitializing" class="lead-search-initial-loader" aria-live="polite">
            <div class="lead-search-initial-loader__spinner"></div>
            <span class="lead-search-initial-loader__text">Restoring search…</span>
        </div>
        <div class="lead-search-container d-flex">
            <div class="sidebar-pills p-4 d-flex flex-column gap-3 border-end">
                <button
                    v-for="pill in sidebarPills"
                    :key="pill.id"
                    class="pill-btn"
                    :class="{ 'active': activePill === pill.id }"
                    @click="handleSidebarPillClick(pill)"
                >
                    <span>{{ pill.label }}</span>
                    <span v-if="pill.type === 'city'" class="pill-count">{{ pill.children.length }}</span>
                    <span v-if="pill.type === 'city' && isCitySelected(pill.id)" class="selected-indicator">✓</span>
                </button>
                <transition name="city-child-list">
                    <div
                        v-if="activeCityPill"
                        class="city-children-wrap"
                    >
                        <div class="city-children-title">Branches in {{ activeCityPill.label }}</div>
                        <button
                            v-for="child in activeCityPill.children"
                            :key="`city_child_dropdown_${child.value}`"
                            type="button"
                            class="city-child-btn"
                            :class="{ active: isBranchSelected(child.value) }"
                            @click="selectCityBranch(activeCityPill, child)"
                        >
                            {{ child.text }}
                        </button>
                        <div v-if="activeCityPill.children.length === 0" class="city-children-empty">
                            No branches available
                        </div>
                    </div>
                </transition>
            </div>
            <div class="form-content-wrapper flex-grow-1 position-relative">
                <button class="close-btn" @click="emit('update:modelValue', false)">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
                <div class="search-sections-wrap">
                    <div v-for="section in visibleSearchSections" :key="`dropdown_${section.id}`" class="search-section-card">
                        <div class="search-section-title">{{ section.title }}</div>
                        <div class="row g-4">
                    <template v-for="field in section.fields" :key="field.id">
                        <div class="col-md-6 mt-3">
                            <label class="form-label-custom">{{ field.label }}</label>
                            <button
                                v-if="field.id === 'created_on' || field.id === 'assigned_on'"
                                type="button"
                                class="custom-date-trigger"
                                @click.stop="openDatePicker(field.id, $event)"
                            >
                                <span>{{ field.id === 'assigned_on' ? assignedOnDisplay : createdOnDisplay }}</span>
                                <iconify-icon icon="lucide:calendar-days" />
                            </button>
                            <div
                                v-else-if="field.id === 'budget_from'"
                                ref="budgetTriggerRef"
                                class="budget-field-wrap"
                            >
                                <button
                                    type="button"
                                    class="custom-date-trigger"
                                    @click.stop="toggleBudgetDropdown"
                                >
                                    <span>{{ budgetDisplay }}</span>
                                    <iconify-icon icon="lucide:chevron-down" />
                                </button>
                            </div>
                            <CrmPhoneInput
                                v-else-if="field.type === 'text' && field.id === 'work_phone'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder || 'Enter Phone'"
                            />
                            <b-form-input
                                v-else-if="field.type === 'text' && field.id !== 'budget_to'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder"
                                class="custom-input"
                            />
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'location'"
                                v-model="form.areaId"
                                :options="areaOptions"
                                :reduce="area => area.id"
                                label="name"
                                placeholder="Select area"
                                :clearable="hasValue(form.areaId)"
                                append-to-body
                                class="custom-v-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                <template #option="option">
                                    <div class="location-option">
                                        <i class="ri-map-pin-line location-option-icon"></i>
                                        <div class="location-option-text">
                                            <span class="location-option-name">{{ locationFirstLine(option) }}</span>
                                            <span class="location-option-subtitle">{{ locationSecondLine(option) }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="option" class="location-selected">
                                        <span class="location-selected-name">{{ locationFirstLine(option) }}</span>
                                        <span class="location-selected-subtitle">{{ locationSecondLine(option) }}</span>
                                    </div>
                                </template>
                            </v-select>
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'responsible_person'"
                                v-model="form.responsible"
                                :options="personOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Person'"
                                :clearable="hasValue(form.responsible)"
                                append-to-body
                                class="custom-v-select lead-search-rp-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                <template #option="option">
                                    <div v-if="option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Person
                                    </div>
                                    <div v-else class="lead-rp-opt d-flex align-items-center gap-2">
                                        <img
                                            :src="option.avatar || DEFAULT_RESPONSIBLE_AVATAR"
                                            alt=""
                                            class="lead-rp-opt-avatar"
                                        />
                                        <div class="lead-rp-opt-info min-w-0 flex-grow-1">
                                            <div class="lead-rp-opt-name-row d-flex align-items-center flex-wrap gap-1">
                                                <span class="user-item-name">{{ option.text }}</span>
                                                <span v-if="option.role_name" class="user-position-badge">{{ option.role_name }}</span>
                                            </div>
                                            <div class="user-item-meta-line">
                                                <span class="meta-value">{{ option.parent_name }}</span>
                                                <span v-if="option.branch_name" class="meta-divider">|</span>
                                                <span v-if="option.branch_name" class="meta-value">{{ option.branch_name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="!option || option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Person
                                    </div>
                                    <div v-else class="lead-rp-sel d-flex align-items-center gap-2 min-w-0">
                                        <img
                                            :src="option.avatar || DEFAULT_RESPONSIBLE_AVATAR"
                                            alt=""
                                            class="lead-rp-sel-avatar"
                                        />
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="lead-rp-sel-name text-truncate fw-semibold">{{ option.text }}</div>
                                            <div
                                                v-if="option.parent_name || option.branch_name"
                                                class="lead-rp-sel-meta text-truncate small text-muted"
                                            >
                                                {{ [option.parent_name, option.branch_name].filter(Boolean).join(' | ') }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-select>
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'team'"
                                v-model="form.team"
                                :options="computedTeamOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Team'"
                                :clearable="hasValue(form.team)"
                                append-to-body
                                class="custom-v-select lead-search-rp-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                <template #option="option">
                                    <div v-if="option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Team
                                    </div>
                                    <div v-else class="lead-rp-opt d-flex align-items-center gap-2">
                                        <img
                                            :src="option.avatar || DEFAULT_TEAM_AVATAR"
                                            alt=""
                                            class="lead-rp-opt-avatar"
                                        />
                                        <div class="lead-rp-opt-info min-w-0 flex-grow-1">
                                            <div class="lead-rp-opt-name-row d-flex align-items-center flex-wrap gap-1">
                                                <span class="user-item-name">{{ option.text }}</span>
                                                <span v-if="option.role_name" class="user-position-badge">{{ option.role_name }}</span>
                                            </div>
                                            <div class="user-item-meta-line">
                                                <span v-if="option.parent_name" class="meta-value">{{ option.parent_name }}</span>
                                                <span v-if="option.parent_name && option.branch_name" class="meta-divider">|</span>
                                                <span v-if="option.branch_name" class="meta-value">{{ option.branch_name }}</span>
                                                <span v-if="!option.parent_name && !option.branch_name && option.team_size" class="meta-value">{{ option.team_size }} members</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="!option || option.value == null" class="lead-rp-opt-placeholder text-muted">
                                        Select Team
                                    </div>
                                    <div v-else class="lead-rp-sel d-flex align-items-center gap-2 min-w-0">
                                        <img
                                            :src="option.avatar || DEFAULT_TEAM_AVATAR"
                                            alt=""
                                            class="lead-rp-sel-avatar"
                                        />
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="lead-rp-sel-name text-truncate fw-semibold">{{ option.text }}</div>
                                            <div v-if="option.parent_name || option.branch_name || option.team_size" class="lead-rp-sel-meta text-truncate small text-muted">
                                                {{ [option.parent_name, option.branch_name, option.team_size ? `${option.team_size} members` : null].filter(Boolean).join(' | ') }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-select>
                            <v-select
                                v-else-if="field.type === 'select' && field.id !== 'office' && field.id !== 'responsible_person' && field.id !== 'team'"
                                v-model="form[field.formKey]"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select'"
                                :clearable="hasValue(form[field.formKey])"
                                append-to-body
                                class="custom-v-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <v-select
                                v-if="field.id === 'source' && form.source === 'website'"
                                v-model="form.sourceWebsite"
                                :options="websiteSourceOptionsForMulti"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select websites"
                                :clearable="form.sourceWebsite && form.sourceWebsite.length > 0"
                                multiple
                                filterable
                                append-to-body
                                class="custom-v-select mt-2 office-multi-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                               <v-select
                                v-if="field.id === 'source' && form.source === 'portal'"
                                v-model="form.sourcePortal"
                                :options="portalSourceOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select Portals"
                                :clearable="form.sourcePortal && form.sourcePortal.length > 0"
                                multiple
                                filterable
                                append-to-body
                                class="custom-v-select mt-2 office-multi-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <!-- Multi-select for office/branch -->
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'office'"
                                v-model="form.office"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Branches'"
                                :clearable="form.office && form.office.length > 0"
                                append-to-body
                                multiple
                                class="custom-v-select office-multi-select"
                                @update:model-value="handleOfficeChange"
                                @click.stop
                                @mousedown.stop
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            
                            
                        </div>
                    </template>
                        </div>
                    </div>
                </div>
                <div class="search-modal-footer d-flex align-items-center justify-content-between mt-3 pt-4">
                    <div class="d-flex gap-4">
                        <a href="#" class="footer-link text-decoration-underline" @click.prevent="showFilterSettings = true">Add Field</a>
                        <a href="#" class="footer-link text-secondary" @click.prevent="restoreDefaultFields">Restore default fields</a>
                    </div>
                    <div class="d-flex gap-3">
                        <button class="btn-reset" :disabled="searching" @click="resetForm">Reset</button>
                        <button class="btn-search" :disabled="searching" @click="applySearch">
                            <iconify-icon v-if="searching" icon="lucide:loader-2" class="btn-search-spinner" />
                            <span>{{ searching ? 'Searching…' : 'Search' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <FilterFieldSettingsModal
        v-model="showFilterSettings"
        :initial-selected-lead-ids="selectedLeadFieldIds"
        @apply="onFilterApply"
    />

    <Teleport to="body">
    <div v-if="showDateModal" class="lr-modal-backdrop lead-search-date-backdrop" @click.stop>
        <div class="lr-date-modal">
            <div class="lr-date-left">
                <button
                    v-for="preset in datePresets"
                    :key="preset.value"
                    type="button"
                    class="lr-date-preset"
                    :class="{ active: selectedPreset === preset.value }"
                    @click="selectPresetRange(preset.value)"
                >
                    {{ preset.label }}
                </button>
            </div>

            <div class="lr-date-right">
                <div class="lr-calendar-head">
                    <button type="button" @click="changeMonth(-1)"><iconify-icon icon="lucide:chevron-left" /></button>
                    <div>{{ monthLabel }}</div>
                    <button type="button" @click="changeMonth(1)"><iconify-icon icon="lucide:chevron-right" /></button>
                </div>

                <div class="lr-weekdays">
                    <span v-for="d in weekDays" :key="d">{{ d }}</span>
                </div>

                <div class="lr-calendar-grid">
                    <button
                        v-for="cell in calendarCells"
                        :key="cell.key"
                        type="button"
                        class="lr-day"
                        :class="{
                          muted: !cell.currentMonth,
                          selected: isSelectedDate(cell.date),
                          inrange: isInRange(cell.date)
                        }"
                        @click="pickDate(cell.date)"
                    >
                        {{ cell.day }}
                    </button>
                </div>

                <div class="lr-date-actions large">
                    <button type="button" class="btn-cancel" @click.stop="showDateModal = false">Cancel</button>
                    <button type="button" class="btn-apply" @click.stop="applyDateRange">Apply</button>
                </div>
            </div>
        </div>
    </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="showBudgetDropdown"
            ref="budgetDropdownPanelRef"
            class="budget-dropdown budget-dropdown--portal"
            :style="budgetDropdownStyle"
            @click.stop
        >
            <div class="budget-from-to-row">
                <div class="budget-col">
                    <label class="budget-input-label">From</label>
                    <b-form-input
                        :model-value="form.budgetFrom"
                        placeholder="0"
                        class="custom-input budget-dropdown-input"
                        @update:model-value="(val) => setBudgetValue('budgetFrom', val)"
                    />
                </div>
                <div class="budget-col">
                    <label class="budget-input-label">To</label>
                    <b-form-input
                        :model-value="form.budgetTo"
                        placeholder="0"
                        class="custom-input budget-dropdown-input"
                        @update:model-value="(val) => setBudgetValue('budgetTo', val)"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { BModal, BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import FilterFieldSettingsModal from './FilterFieldSettingsModal.vue'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: Boolean,
    asDropdown: { type: Boolean, default: false },
    initialActivePill: { type: String, default: undefined },
    hasActiveFilters: { type: Boolean, default: true },
    currentQuery: { type: Object, default: null },
    showTeamFilter: { type: Boolean, default: false },
    searching: { type: Boolean, default: false },
    key: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue', 'search'])

const show = ref(props.modelValue)
const showFilterSettings = ref(false)
const FIELD_STORAGE_KEY = 'selectedLeadFields'
const showDateModal = ref(false)
const showBudgetDropdown = ref(false)
const budgetTriggerRef = ref(null)
const budgetDropdownPanelRef = ref(null)
const budgetDropdownStyle = ref({})
const selectedLeadFieldIds = ref(['lead_name','first_name',  'created_on', 'assigned_on', 'work_phone', 'responsible_person', 'office', 'email', 'source', 'lead_branch_source', 'team','stage','quality_status', 'interaction_result'])
const activePill = ref(props.initialActivePill || 'leads-in-progress')
const officeOptions = ref([])
const allResponsiblePersons = ref([])
const allTeams = ref([])
/** Avoid team watcher re-fetch when responsible selection sets team + branch */
const syncingFromResponsible = ref(false)
/** While true, the form is being populated from `props.currentQuery` (modal-open restore).
 *  Cascade watchers (responsible → team → office, team → office, etc.) skip themselves so
 *  they don't re-fetch options and prune the just-hydrated values. */
const hydratingFromQuery = ref(false)
/** True from mount until dropdown options have been fetched AND syncFormFromQuery has run.
 *  We block the form UI behind an overlay during this window — otherwise the user can type
 *  in a text field or pick from a select, then watch their input get wiped when the saved
 *  search hydrates a moment later. */
const isInitializing = ref(true)
const selectedOffice = ref(null)
const selectedPillType = ref(null)
const validationErrors = ref({})

watch(() => props.key, () => {
    console.log('Modal key changed, reloading saved fields')
    if (show.value) {
        restoreSavedFields()
        nextTick(() => {
            // فرض إعادة حساب visibleSearchSections
            const sections = visibleSearchSections.value
            console.log('Sections after key change:', sections.length)
        })
    }
})

const normalizeOfficeId = (value) => {
    if (value === null || value === undefined || value === '') return null
    const numeric = Number(value)
    return Number.isNaN(numeric) ? String(value) : numeric
}

const normalizeOfficeSelection = (value) => {
    const values = Array.isArray(value) ? value : [value]
    const normalized = values
        .flatMap((item) => {
            if (typeof item === 'string' && item.includes(',')) {
                return item.split(',').map(part => part.trim()).filter(Boolean)
            }
            return [item]
        })
        .map(normalizeOfficeId)
        .filter(item => item !== null)

    return [...new Set(normalized)]
}

/** Branch office id for a team row (API may send office_id or admin_parent_id). */
function teamBranchId(team) {
    if (!team) return null
    return normalizeOfficeId(team.office_id ?? team.admin_parent_id ?? null)
}

function pruneTeamAndResponsible() {
    const teamOpts = computedTeamOptions.value.filter((o) => o.value != null)
    if (
        form.value.team &&
        !teamOpts.some((o) => Number(o.value) === Number(form.value.team))
    ) {
        form.value.team = ''
    }
    const personOpts = personOptions.value.filter((o) => o.value != null)
    if (
        form.value.responsible &&
        !personOpts.some((o) => Number(o.value) === Number(form.value.responsible))
    ) {
        form.value.responsible = ''
    }
}

// Helper functions for branch selection
const isBranchSelected = (branchValue) => {
    if (!form.value.office) return false
    const normalizedBranch = normalizeOfficeId(branchValue)
    const selected = normalizeOfficeSelection(form.value.office)
    if (Array.isArray(form.value.office)) {
        return selected.includes(normalizedBranch)
    }
    return normalizeOfficeId(form.value.office) === normalizedBranch
}

const isCitySelected = (cityId) => {
    if (!form.value.office || !Array.isArray(form.value.office)) return false
    const cityPill = sidebarPills.value.find(p => p.id === cityId)
    if (!cityPill) return false
    const selected = normalizeOfficeSelection(form.value.office)
    return cityPill.children.some(child => selected.includes(normalizeOfficeId(child.value)))
}

// Handle office / branch change — refresh API lists, then drop team/responsible if incompatible
const handleOfficeChange = async (newOffice) => {
    console.log('Office changed to:', newOffice)
    const normalizedOffices = normalizeOfficeSelection(newOffice)
    form.value.office = normalizedOffices

    if (normalizedOffices.length) {
        selectedOffice.value = [...normalizedOffices]
    } else {
        selectedOffice.value = null
    }

    await Promise.all([
        fetchResponsiblePersonsWithFilter(),
        fetchTeamsWithFilter()
    ])
    await nextTick()
    pruneTeamAndResponsible()
}

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(() => props.modelValue, (val) => {
    if (val) {
        // Always rehydrate from the last applied query so reopening the modal shows the
        // previously selected filters (mirrors DealSearchModal). syncFormFromQuery resets
        // to defaults internally when the query is empty.
        nextTick(() => syncFormFromQuery(props.currentQuery))
    }
})

const queryToFormKeys = {
    lead_name: 'leadName',
    first_name: 'firstName',
    responsible_person_id: 'responsible',
    created_at: 'createdOn',
    created_from: 'createdFrom',    
    created_to: 'createdTo',
    assigned_at: 'assignedOn',
    assigned_from: 'assignedFrom',
    assigned_to: 'assignedTo',
    lead_branch_source: 'branchSource',
    work_phone: 'workPhone',
    email: 'email',
    bedrooms: 'bedrooms',
    search: 'search',
    source: 'source',
    source_website: 'sourceWebsite',
    interaction_result: 'interactionResult',
    status_lead: 'qualityStatus',
    why_lost_lead: 'qualityStatus',
    team_id: 'team',
    office_branch: 'office',
    lead_type: 'leadType',
    property_status: 'propertyStatus',
    budget_from: 'budgetFrom',
    budget_to: 'budgetTo',
    area_id: 'areaId',
     property_type_id: 'propertyType'
}

function normalizeSourceWebsiteForm(next) {
    const siteValues = websiteSourceOptions.value.map(o => o.value).filter(v => v != null)
    const portalValues = portalSourceOptions.value.map(o => o.value).filter(v => v != null)
    
    // معالجة website
    if (Array.isArray(next.source) && next.source.length) {
        next.sourceWebsite = next.source.filter(Boolean)
        next.source = 'website'
        return
    }
    if (typeof next.source === 'string' && siteValues.includes(next.source)) {
        next.sourceWebsite = [next.source]
        next.source = 'website'
        return
    }
    if (next.source === 'allproperties' || next.source === 'oiaproperties') {
        next.sourceWebsite = next.source ? [next.source] : []
        next.source = 'website'
        return
    }
    if (next.source === 'website') {
        if (Array.isArray(next.sourceWebsite)) {
            next.sourceWebsite = next.sourceWebsite.filter(v => v != null && v !== '')
        } else if (next.sourceWebsite) {
            next.sourceWebsite = [next.sourceWebsite]
        } else {
            next.sourceWebsite = []
        }
    }
    
    // معالجة portal
    if (next.source === 'portal') {
        if (Array.isArray(next.sourcePortal)) {
            next.sourcePortal = next.sourcePortal.filter(v => v != null && v !== '')
        } else if (next.sourcePortal) {
            next.sourcePortal = [next.sourcePortal]
        } else {
            next.sourcePortal = []
        }
    }
}

function syncFormFromQuery(query) {
    if (!query || typeof query !== 'object' || Object.keys(query).length === 0) {
        resetFormValues()
        return
    }
    hydratingFromQuery.value = true
    const next = {
        search: '',
        id: '',
        firstName: '',
        responsible: '',
        createdOn: '',
        createdFrom: '',   
        createdTo: '', 
        assignedOn: '',
        assignedFrom: '',
        assignedTo: '',
        stageChangedBy: '',
        branchSource: '',
        workPhone: '',
        email: '',
        bedrooms: '',
        leadName: '',
        source: '',
        sourceWebsite: [],
        sourcePortal:[],
        interactionResult: '',
        qualityStatus: '',
        team: '',
        office: [],
        leadType: '',
        propertyStatus: '',
        budgetFrom: '',
        budgetTo: '',
        areaId: '',
         propertyType: ''
    }
    Object.keys(queryToFormKeys).forEach(qKey => {
        const formKey = queryToFormKeys[qKey]
        if (query[qKey] !== undefined && query[qKey] !== '') {
             if (formKey === 'office' && query[qKey]) {
                next[formKey] = normalizeOfficeSelection(query[qKey])
            } 
            // أضف هذا الشرط لمعالجة qualityStatus
            else if (qKey === 'status_lead') {
                next.qualityStatus = mapApiStatusToFormValue(query[qKey], next.stageId)
            }
            else {
                next[formKey] = query[qKey]
            }
        }
    })
        if (query.status_lead !== undefined && query.status_lead !== '') {
        next.qualityStatus = mapApiStatusToFormValue(query.status_lead, next.stageId)
    }
    
  
    if ((!next.source || next.source === '') && query.source_website) {
        const sw = query.source_website
        next.sourceWebsite = Array.isArray(sw) ? sw.filter(Boolean) : [sw].filter(Boolean)
        next.source = 'website'
    }
     if (next.source === 'portal' && query.source_portal) {
        const sp = query.source_portal
        next.sourcePortal = Array.isArray(sp) ? sp.filter(Boolean) : [sp].filter(Boolean)
    }
    normalizeSourceWebsiteForm(next)
    if (next.createdFrom || next.createdTo) {
        next.createdOn = 'custom_date'
    } else if (query.created_at) {
        next.createdOn = 'custom_date'
        next.createdFrom = query.created_at
        next.createdTo = query.created_at
    }
    if (next.assignedFrom || next.assignedTo) {
        next.assignedOn = 'custom_date'
    } else if (query.assigned_at) {
        next.assignedOn = 'custom_date'
        next.assignedFrom = query.assigned_at
        next.assignedTo = query.assigned_at
    }
    next.budgetFrom = formatBudgetWithCommas(next.budgetFrom)
    next.budgetTo = formatBudgetWithCommas(next.budgetTo)
    if (next.team !== '' && next.team != null && next.team !== undefined) {
        const tn = Number(next.team)
        if (!Number.isNaN(tn)) next.team = tn
    }
    form.value = next

    // Release the hydration guard after Vue has processed the form assignment so the
    // responsible/team watchers see the new values but don't try to "fix" them.
    nextTick(() => {
        hydratingFromQuery.value = false
    })
}

watch(() => props.initialActivePill, (newVal) => {
    console.log('initialActivePill changed:', newVal)
    if (newVal && show.value) {
        activePill.value = newVal
    }
}, { immediate: true })

watch(show, (val) => {
    emit('update:modelValue', val)
    if (val) {
        if (props.initialActivePill) {
            activePill.value = props.initialActivePill
        }
        // Always hydrate — syncFormFromQuery falls back to reset when query is empty.
        syncFormFromQuery(props.currentQuery)
    }
})

watch(() => props.hasActiveFilters, (val) => {
    if (!val && show.value) resetFormValues()
})

watch(() => props.currentQuery, (query) => {
    // Keep the form in sync while the modal is open even if the active-filters flag lags.
    if (show.value) syncFormFromQuery(query)
}, { deep: true })

const displaySavedFieldValues = () => {
    console.log('Currently selected fields:', selectedLeadFieldIds.value)
    console.log('Form values:', form.value)
    
    // عرض القيم في console للتأكد
    selectedLeadFieldIds.value.forEach(fieldId => {
        const field = searchFieldsConfig.value.find(f => f.id === fieldId)
        if (field && form.value[field.formKey]) {
            console.log(`${field.label}:`, form.value[field.formKey])
        }
    })
}

// استدعائها عند فتح الموديل
watch(() => props.modelValue, (val) => {
    show.value = val
    if (val) {
        restoreSavedFields()
        displaySavedFieldValues() // لعرض القيم في console
        // Always rehydrate so reopening the modal shows the previously selected filters
        // (matches DealSearchModal behavior).
        syncFormFromQuery(props.currentQuery)
    }
})

const normalizeCityText = (value) => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim()

const detectCityKeyFromOffice = (office) => {
    const probes = [
        office?.city,
        office?.city_name,
        office?.branch_source,
        office?.branchSource,
        office?.parent_name,
        office?.parent?.name,
        office?.region,
        office?.office_city,
        office?.text,
        office?.name,
    ]
        .map(normalizeCityText)
        .filter(Boolean)

    const all = probes.join(' | ')
    if (all.includes('dubai')) return 'dubai'
    if (all.includes('abu dhabi') || all.includes('abudhabi') || all.includes('abu-dhabi')) return 'abu-dhabi'
    return ''
}

const cityBranchGroups = computed(() => {
    const options = (officeOptions.value || [])
        .filter(o => o && o.value != null)

    const groups = {
        dubai: { id: 'dubai', label: 'Dubai', options: [] },
        'abu-dhabi': { id: 'abu-dhabi', label: 'Abu Dhabi', options: [] },
    }

    options.forEach((office) => {
        const cityKey = office.cityKey || detectCityKeyFromOffice(office.raw || office)

        if (cityKey === 'dubai') groups.dubai.options.push(office)
        else if (cityKey === 'abu-dhabi') groups['abu-dhabi'].options.push(office)
    })

    return Object.values(groups).filter(group => group.options.length > 0)
})



const activeCityPill = computed(() => {
    return sidebarPills.value.find(p => p.id === activePill.value && p.type === 'city') || null
})

const form = ref({
    search: '',
    id: '',
    firstName: '',
    responsible: '',
    createdOn: '',
    assignedOn: '',
    stageChangedBy: '',
    assignedFrom: '',
    assignedTo: '',
    branchSource: '',
    workPhone: '',
    email: '',
    bedrooms: '',
    leadName: '',
    source: '',
    sourceWebsite: [],
    sourcePortal: [],
    interactionResult: '',
    qualityStatus: '',
    createdFrom: '',    
    createdTo: '',  
    team: '',
    office: [],
    leadType: '',
    propertyStatus: '',
    budgetFrom: '',
    budgetTo: '',
    areaId: '',
     propertyType: '',
     purposePurchase: '', 
})

const responsiblePersons = ref([])
const branchSourceOptions = ref([])
const stageOptions = ref([])

const DEFAULT_RESPONSIBLE_AVATAR =
    'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'

const personOptions = computed(() => {
    const opts = []
    let filteredPersons = [...allResponsiblePersons.value]

    if (form.value.team) {
        const tid = Number(form.value.team)
        filteredPersons = filteredPersons.filter((p) => Number(p.team_id) === tid)
    }

    const officeSel = normalizeOfficeSelection(form.value.office || [])
    if (officeSel.length) {
        filteredPersons = filteredPersons.filter((p) => {
            const bid = normalizeOfficeId(p.branch_id)
            return bid != null && officeSel.includes(bid)
        })
    }

    filteredPersons.forEach((p) => {
        opts.push({
            value: p.id,
            text: p.name || `User ${p.id}`,
            avatar: p.avatar,
            role_name: p.role_name,
            parent_name: p.parent_name,
            branch_name: p.branch_name
        })
    })

    return opts
})

const computedTeamOptions = computed(() => {
    const opts = []
    let filteredTeams = allTeams.value.filter((t) => t && t.id != null)

    const officeSel = normalizeOfficeSelection(form.value.office || [])
    if (officeSel.length) {
        filteredTeams = filteredTeams.filter((team) => {
            const bid = teamBranchId(team)
            return bid != null && officeSel.includes(bid)
        })
    }

    const selectedId =
        form.value.team !== null &&
        form.value.team !== undefined &&
        form.value.team !== ''
            ? Number(form.value.team)
            : null
    if (
        selectedId &&
        !Number.isNaN(selectedId) &&
        !filteredTeams.some((t) => Number(t.id) === selectedId)
    ) {
        const missing = allTeams.value.find((t) => Number(t.id) === selectedId)
        if (missing) filteredTeams = [...filteredTeams, missing]
    }

    filteredTeams.forEach((team) => {
        const id = Number(team.id)
        opts.push({
            value: team.id,
            text: team.name,
            avatar: team.avatar,
            role_name: team.role_name || 'Team',  // ✅ إضافة role_name
            parent_name: team.parent_name || team.admin_parent_name || '',  // ✅ إضافة parent_name
            branch_name: team.branch_name || '',  // ✅ إضافة branch_name
            team_size: team.team_size,
        })
    })

    return opts
})
/**
 * Branch multi-select options: `/get-offices` ids may differ from team `admin_parent_id` (User id).
 * Merge selected branch ids with labels from offices API or team.admin_parent_name so v-select shows names.
 */
const branchSelectOptions = computed(() => {
    const fromApi = (officeOptions.value || []).filter((o) => o.value != null)
    const opts = fromApi.map((o) => ({
        value: normalizeOfficeId(o.value),
        text: o.text || `Office ${o.value}`,
        raw: o.raw,
    }))
    const seen = new Set(opts.map((o) => String(o.value)))

    const ensureOptionForBranchId = (branchId) => {
        const nid = normalizeOfficeId(branchId)
        if (nid == null) return
        const key = String(nid)
        if (seen.has(key)) return
        seen.add(key)

        let text = fromApi.find((o) => normalizeOfficeId(o.value) === nid)?.text || null
        if (!text) {
            const team = allTeams.value.find((t) => {
                const bid = teamBranchId(t)
                return bid != null && String(bid) === key
            })
            text = team?.admin_parent_name || null
        }
        if (!text) text = `Branch #${nid}`
        opts.push({ value: nid, text })
    }

    normalizeOfficeSelection(form.value.office || []).forEach(ensureOptionForBranchId)

    return opts
})

const dateOptions = [
    { value: null, text: 'Any Date' }
]

const yesNoOptions = [
    // { value: null, text: 'Any' },
    { value: 1, text: 'Yes' },
    { value: 0, text: 'No' }
]

const bedroomsOptions = [
    { value: null, text: 'Any' },
    { value: 1, text: '1' },
    { value: 2, text: '2' },
    { value: 3, text: '3' },
    { value: 4, text: '4+' }
]

const createdOnOptions = [
    { value: null, text: 'Any Date' },
    { value: 'today', text: 'Today' },
    { value: 'yesterday', text: 'Yesterday' },
    { value: 'this_week', text: 'This Week' },
    { value: 'this_month', text: 'This Month' },
    { value: 'current_quarter', text: 'Current Quarter' },
    { value: 'last_7_days', text: 'Last 7 Days' },
    { value: 'last_30_days', text: 'Last 30 Days' },
    { value: 'last_60_days', text: 'Last 60 Days' },
    { value: 'last_90_days', text: 'Last 90 Days' },
    { value: 'last_week', text: 'Last Week' },
    { value: 'last_month', text: 'Last Month' },
    { value: 'custom_date', text: 'Custom Date' }
]
// Purpose of Purchase Options
const purposeOptions = [
    // { value: null, text: 'Select Purpose' },
    { value: 'Live in', text: 'Live in' },
    { value: 'Short-term investment', text: 'Short-term investment' },
    { value: 'Long-term investment', text: 'Long-term investment' }
]
const sourceOptions = ref([
    // { value: null, text: 'Select Source' },
    { value: 'Lead Form', text: 'Meta' },
    { value: 'website', text: 'Website' },
    { value: 'portal', text: 'Portal' },
])
const websiteSourceOptions = ref([
    // { value: null, text: 'Select Website' },
    { value: 'Allproperties.ae', text: 'Allproperties.ae' },
    { value: 'Oiaproperties.com', text: 'Oiaproperties.com' },
    
])
const portalSourceOptions = ref([
    // { value: null, text: 'Select Portal' },
    { value: 'propertyfinder', text: 'Property Finder' },
    { value: 'bayut', text: 'Bayut' },
])

const websiteSourceOptionsForMulti = computed(() =>
    websiteSourceOptions.value.filter(o => o.value != null)
)

function getSelectedStageOrder(stageId) {
    if (!stageId) return 0
    const selected = stageOptions.value.find(s => Number(s.value) === Number(stageId))
    return Number(selected?.order || 0)
}
// أضف هذه الدالة المساعدة بعد تعريف qualityStatusOptions
function mapApiStatusToFormValue(apiValue, stageId) {
    if (!apiValue) return ''
    
    const stageOrder = getSelectedStageOrder(stageId)
    let options = []
    
    if (stageOrder === 4) {
        options = ['cold', 'warm', 'hot']
    } else if (stageOrder === 8) {
        options = ['lost_by_other_company', 'lost_by_our_company']
    } else if (stageOrder === 9) {
        options = ['no_answer', 'contacted', 'wrong_person']
    } else if (stageOrder === 10) {
        options = [
          
            'not_interested', 'wrong_contact_details', 'no_answer_multiple_calls',
            'job_seeker', 'broker', 'registered_by_mistake', 'blacklist'
        ]
    }
    
    // إذا كانت القيمة نصية موجودة في الخيارات، أرجعها كما هي
    if (options.includes(apiValue)) return apiValue
    
    // خريطة التحويل من رقم إلى نص (حسب الحاجة)
    const numericToTextMap = {
        1: 'cold',
        2: 'warm',
        3: 'hot',
        // أضف المزيد حسب ما يرسله الـ API
    }
    
    return numericToTextMap[apiValue] || apiValue
}
// Quality Status Options based on selected stage (from database)
const qualityStatusOptions = computed(() => {
    const selectedStageId = form.value.stageId
    if (!selectedStageId) {
        return [{ value: null, text: 'Select Quality Status' }]
    }
    const stageOrder = getSelectedStageOrder(selectedStageId)
    
    // Stage 4 (order 4): Qualified
    if (stageOrder === 4) {
        return [
            // { value: null, text: 'Select Quality Status' },
            { value: 'cold', text: 'Cold Lead' },
            { value: 'warm', text: 'Warm Lead' },
            { value: 'hot', text: 'Hot Lead' }
        ]
    }
    
    // Stage 9 (order 9): Lead Pool
    if (stageOrder === 9) {
        return [
            // { value: null, text: 'Select Quality Status' },
            { value: 'no_answer', text: 'No Answer' },
        ]
    }
    
    // Stage 10 (order 10): Unqualified
    if (stageOrder === 10) {
        return [
            // { value: null, text: 'Select Quality Status' },
            // { value: 'not_interested', text: 'Not Interested' },
             { value: 'wrong_contact_details', text: 'Wrong Contact Details' },
            { value: 'no_answer_multiple_calls', text: 'No Answer — Multiple Calls' },
            { value: 'job_seeker', text: 'Job Seeker' },
            { value: 'broker', text: 'Broker' },
            { value: 'registered_by_mistake', text: 'Registered by Mistake' },
            { value: 'spam_leads', text: 'Spam Leads' },
                { value: 'blacklist', text: 'Black Lists' },
        ]
    }
    
    // Stage 8 (order 8): Lost
    if (stageOrder === 8) {
        return [
            // { value: null, text: 'Select Why Lost' },
              { value: 'already_bought', text: "Already bought" }
        ]
    }

    return [{ value: null, text: 'Select Quality Status' }]
})

const showInteractionResult = computed(() => {
    const selectedStageId = form.value.stageId
    if (!selectedStageId) return false
    const stageOrder = getSelectedStageOrder(selectedStageId)
    // يظهر لـ Stage 2 (Contacted)
    return stageOrder === 2  || stageOrder === 3
})

const interactionResultOptions = [
    // { value: null, text: 'Select Call Result' },
    { value: 'answered', text: 'Answered' },
    { value: 'no_answer', text: 'No Answer' },
]

const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']
const selectedPreset = ref('')
const activeDateField = ref('created_on')
const startDate = ref(null)
const endDate = ref(null)
const calendarMonth = ref(new Date())
const datePresets = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'this_week', label: 'This Week' },
    { value: 'last_week', label: 'Last Week' },
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'last_year', label: 'Last Year' },
    { value: 'custom_date', label: 'Custom Date' },
]
const leadTypeOptions = [
    // { value: null, text: 'Select Lead Type' },
    { value: 'sale', text: 'Sale' },
    { value: 'rent', text: 'Rent' },
     { value: 'both', text: 'Both' },
]

// Property Status Options
const propertyStatusOptions = [
    // { value: null, text: 'Select Property Status' },
    { value: 'ready', text: 'Ready' },
    { value: 'off_plan', text: 'Off Plan' },
    { value: 'both', text: 'Both' }
]
const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}
const propertyTypeOptions = ref([])
const areaOptions = ref([])
const locationFirstLine = (area) => area?.name || 'Unknown Area'
const locationSecondLine = (area) => {
    const parent = area?.parent || area?.area_parents_title || area?.parent_name
    const community = area?.community_name || area?.communityName
    const city = area?.city_name || area?.cityName
    if (parent) return parent
    if (community && city) return `${community}, ${city}`
    return community || city || ''
}
const fetchAreas = async () => {
    try {
        const res = await api.get('/listings/areas/?has_listings=true')
        const data = res.data.data || res.data || []
        areaOptions.value = data.map(area => ({
            id: area.id,
            name: area.name || area.title,
            parent: area.area_parents_title || area.parent || area.parent_name || '',
            community_name: area.community_name || area.communityName || '',
            city_name: area.city_name || area.cityName || '',
        }))
    } catch (error) {
        console.error('Error fetching areas:', error)
        areaOptions.value = []
    }
}
const fetchPropertyTypes = async () => {
    try {
        const res = await api.get('/listings/property-types')
        const data = res.data.data || res.data
        propertyTypeOptions.value = [
            // { value: null, text: 'Select Property Type' },
            ...data.map(type => ({
                value: type.id,
                text: type.name
            }))
        ]
    } catch (error) {
        console.error('Error fetching property types:', error)
        propertyTypeOptions.value = [{ value: null, text: 'Select Property Type' }]
    }
}

const user = ref(getUserFromStorage())

const updateUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        user.value = userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        user.value = null
    }
}

const appliedSearchParams = ref(null)

const isAdminOrSuperAdmin = computed(() => {
    if (!user.value) return false
    
    const isAdminUser = user.value.roles?.includes('super_admin') || 
                       user.value.roles?.includes('admin') || user.value.roles?.includes('manager')
    
    return isAdminUser
})
const sidebarPills = computed(() => {
    const base = [
        { id: 'leads-in-progress', label: 'Leads In Progress', type: 'default' },
        { id: 'my-leads', label: 'My Leads', type: 'default' },
    ]
        const isAdminUser = user.value.roles?.includes('super_admin') || 
                       user.value.roles?.includes('admin')
   if(isAdminUser){
    const cityPills = cityBranchGroups.value.map(group => ({
        id: group.id,
        label: group.label,
        type: 'city',
       children: group.options ?? []
    }))
       return [...base, ...cityPills]
   }
    return [...base]
})
const getCurrentUserBranches = () => {
    const currentUser = user.value
    if (!currentUser) return []
    
    // super_admin يرى كل الفروع
    if (currentUser.roles?.includes('super_admin')) {
        return officeOptions.value.filter(opt => opt.value !== null)
    }
    
    // admin والمستخدم العادي يرى فقط الفروع التي يعيدها الـ API
    // الـ API بالفعل يقوم بتصفية المكاتب حسب صلاحية المستخدم
    return officeOptions.value.filter(opt => opt.value !== null)
}


const searchFieldsConfig = computed(() => {
    const fields = [
        { id: 'lead_name', label: 'Lead Name', formKey: 'leadName', queryKey: 'lead_name', type: 'text', placeholder: 'Enter Lead Name' },
        { id: 'first_name', label: 'Client Name', formKey: 'firstName', queryKey: 'first_name', type: 'text', placeholder: 'Enter client name' },
        { id: 'email', label: 'Email', formKey: 'email', queryKey: 'email', type: 'text', placeholder: 'Enter Email' },
          { id: 'work_phone', label: 'Phone', formKey: 'workPhone', queryKey: 'work_phone', type: 'text', placeholder: 'Enter Phone' },
        { id: 'created_on', label: 'Created On', formKey: 'createdOn', queryKey: 'created_at', type: 'select', options: createdOnOptions },
        { id: 'assigned_on', label: 'Assign On', formKey: 'assignedOn', queryKey: 'assigned_at', type: 'select', options: createdOnOptions },
      
        { id: 'responsible_person', label: 'Responsible Person', formKey: 'responsible', queryKey: 'responsible_person_id', type: 'select', options: [] }
 
    ]
    const currentUser = user.value
    const isSuperAdmin = currentUser?.roles?.includes('super_admin')
     const isAdmin = currentUser?.roles?.includes('admin') 
    if (isAdminOrSuperAdmin.value) {
        fields.push({ id: 'team', label: 'Team', formKey: 'team', queryKey: 'team_id', type: 'select', options: [] })
      
    } 
  
    if(isSuperAdmin || isAdmin)
    {
        
        fields.push(    { id: 'lead_branch_source', label: 'Lead Branch Source', formKey: 'branchSource', queryKey: 'lead_branch_source', type: 'select', options: [] },
        { id: 'office', label: 'Branch', formKey: 'office', queryKey: 'office_branch', type: 'select', options: officeOptions.value, multiple: true })
    }
    
    
    fields.push(
        { id: 'source', label: 'Source', formKey: 'source', queryKey: 'source', type: 'select', options: [] },
        { id: 'stage', label: 'Stage', formKey: 'stageId', queryKey: 'stage_id', type: 'select', options: stageOptions.value },
        { id: 'purpose_purchase', label: 'Purpose of Purchase', formKey: 'purposePurchase', queryKey: 'purpose_buying', type: 'select', options: purposeOptions },
        { id: 'interaction_result', label: 'Call Result', formKey: 'interactionResult', queryKey: 'interaction_result', type: 'select', options: interactionResultOptions },
        { id: 'quality_status', label: 'Quality Status', formKey: 'qualityStatus', queryKey: 'status_lead', type: 'select', options: qualityStatusOptions },
        { id: 'bedrooms', label: 'Bedrooms', formKey: 'bedrooms', queryKey: 'bedrooms', type: 'select', options: bedroomsOptions },
       
        { id: 'location', label: 'Location / Area', formKey: 'areaId', queryKey: 'area_id', type: 'select', options: areaOptions.value },
        { id: 'property_type', label: 'Property Type', formKey: 'propertyType', queryKey: 'property_type_id', type: 'select', options: propertyTypeOptions.value },
        { id: 'lead_type', label: 'Lead Type', formKey: 'leadType', queryKey: 'lead_type', type: 'select', options: leadTypeOptions },
        
        { id: 'property_status', label: 'Property Status', formKey: 'propertyStatus', queryKey: 'property_status', type: 'select', options: propertyStatusOptions },
         
        { id: 'budget_from', label: 'Budget (AED)', formKey: 'budgetFrom', queryKey: 'budget_from', type: 'text', placeholder: 'Select budget range' }
    )
    
    return fields
})


const monthLabel = computed(() => calendarMonth.value.toLocaleString('en-US', { month: 'long', year: 'numeric' }))
const budgetDisplay = computed(() => {
    const from = form.value.budgetFrom || ''
    const to = form.value.budgetTo || ''
    if (!from && !to) return 'Select budget range'
    if (from && to) return `${from} - ${to}`
    if (from) return `From ${from}`
    return `To ${to}`
})
const createdOnDisplay = computed(() => {
    if (form.value.createdOn === 'custom_date' && form.value.createdFrom && form.value.createdTo) {
        // إذا كان من وإلى مختلفين
        if (form.value.createdFrom !== form.value.createdTo) {
            return `${form.value.createdFrom} to ${form.value.createdTo}`
        }
        // إذا كان تاريخ واحد فقط
        return form.value.createdFrom
    }
    const preset = createdOnOptions.find(opt => opt.value === form.value.createdOn)
    return preset?.text || 'Select Date'
})
const assignedOnDisplay = computed(() => {
    if (form.value.assignedOn === 'custom_date' && form.value.assignedFrom && form.value.assignedTo) {
        // إذا كان من وإلى مختلفين
        if (form.value.assignedFrom !== form.value.assignedTo) {
            return `${form.value.assignedFrom} to ${form.value.assignedTo}`
        }
        // إذا كان تاريخ واحد فقط
        return form.value.assignedFrom
    }
    // للقيم الأخرى (today, yesterday, etc.)
    const preset = createdOnOptions.find(opt => opt.value === form.value.assignedOn)
    return preset?.text || 'Select Date'
})



// أضف هذا بعد تعريف selectedLeadFieldIds
const selectedLeadFieldIdsSet = computed(() => new Set(selectedLeadFieldIds.value))

// عدّل visibleSearchFields لاستخدام Set
const visibleSearchFields = computed(() => {
    const stageOrder = getSelectedStageOrder(form.value.stageId)
    const selectedSet = selectedLeadFieldIdsSet.value

    return searchFieldsConfig.value
        .filter(f => {
            // استخدم Set للتحقق السريع
            if (!selectedSet.has(f.id)) return false
            
            if (f.id === 'quality_status' && (stageOrder === 2 || stageOrder === 3)) {
                return false
            }
             if (f.id === 'purpose_purchase' && form.value.leadType === 'rent') {
                return false
            }
            if (f.id === 'interaction_result' && !(stageOrder === 2 || stageOrder === 3)) {
                return false
            }
            
            return true
        })
        .map(f => ({
            ...f,
            options:
                f.formKey === 'responsible' ? personOptions.value :
                f.formKey === 'branchSource' ? branchSourceOptions.value :
                f.formKey === 'source' ? sourceOptions.value :
                f.formKey === 'createdOn' ? createdOnOptions :
                f.formKey === 'assignedOn' ? createdOnOptions :
                f.formKey === 'team' ? computedTeamOptions.value :
                f.formKey === 'office' ? branchSelectOptions.value :
                f.formKey === 'areaId' ? areaOptions.value :
                f.formKey === 'propertyType' ? propertyTypeOptions.value :
                f.formKey === 'purposePurchase' ? purposeOptions :
                f.formKey === 'interactionResult' ? interactionResultOptions :
                f.formKey === 'qualityStatus' ? qualityStatusOptions.value :
                (f.options || []),
            placeholder: f.placeholder || (f.type === 'select' ? 'Select' : '')
        }))
})
const visibleSearchSections = computed(() => {
    // إضافة console.log للتأكد من التحديث
    console.log('Recalculating visibleSearchSections with selected fields:', selectedLeadFieldIds.value)
    
    return searchFieldSections
        .map(section => ({
            ...section,
            fields: section.fieldIds
                .map(id => visibleSearchFields.value.find(field => field.id === id))
                .filter(Boolean)
        }))
        .filter(section => section.fields.length > 0)
})
const searchFieldSections = [
    {
        id: 'lead-info',
        title: 'Lead Information',
        fieldIds: ['lead_name','first_name',  'work_phone', 'email', 'created_on', 'assigned_on']
    },
    {
        id: 'assignment',
        title: 'Assignment',
        fieldIds: ['responsible_person', 'team' , 'office']
    },
    {
        id: 'source',
        title: 'Lead Source',
        fieldIds: ['source', 'lead_branch_source']
    },
    {
        id: 'qualification',
        title: 'Qualification',
        fieldIds: ['stage','quality_status', 'lead_type', 'property_status', 'interaction_result']
    },
    {
        id: 'client-requirement',
        title: 'Client Requirement',
        fieldIds: ['location', 'property_type', 'bedrooms', 'budget_from','purpose_purchase']
    },

]




// أضف هذا بعد تعريف selectedLeadFieldIds
watch(selectedLeadFieldIds, (newVal, oldVal) => {
    console.log('selectedLeadFieldIds changed in LeadSearchModal:', {
        oldLength: oldVal?.length,
        newLength: newVal?.length,
        newValues: newVal
    })
    
    // فرض إعادة حساب visibleSearchSections
    nextTick(() => {
        const sections = visibleSearchSections.value
        console.log('Visible sections recalculated:', sections.length)
        sections.forEach(section => {
            console.log(`Section ${section.title}: ${section.fields.length} fields`)
        })
    })
}, { deep: true })

function onFilterApply(payload) {
    if (payload && Array.isArray(payload.leads)) {
        const fieldsToSave = payload.leads.length ? payload.leads : [...getAllFieldIds.value]
        selectedLeadFieldIds.value = fieldsToSave
        
        localStorage.setItem(FIELD_STORAGE_KEY, JSON.stringify(fieldsToSave))
        console.log('Saved fields to localStorage:', fieldsToSave)
        
        const saved = localStorage.getItem(FIELD_STORAGE_KEY)
        console.log('Verification - saved fields:', saved)
        if (window.$showNotification) {
            window.$showNotification('Fields saved successfully', 'success')
        }
    }
}

function hasValue(val) {
    if (Array.isArray(val)) return val.length > 0
    if (typeof val === 'number') return true
    return val !== null && val !== undefined && val !== ''
}


function normalizeBudgetString(value) {
    return String(value ?? '').replace(/[^\d]/g, '')
}

function formatBudgetWithCommas(value) {
    const digits = normalizeBudgetString(value)
    if (!digits) return ''
    return Number(digits).toLocaleString('en-US')
}

function parseBudgetNumber(value) {
    const digits = normalizeBudgetString(value)
    return digits ? Number(digits) : undefined
}

function setBudgetValue(key, value) {
    form.value[key] = formatBudgetWithCommas(value)
}

function getBudgetTriggerElement() {
    let el = budgetTriggerRef.value
    if (Array.isArray(el)) el = el.find(Boolean)
    if (el && typeof el.getBoundingClientRect === 'function') return el
    if (el?.$el && typeof el.$el.getBoundingClientRect === 'function') return el.$el
    return null
}

function updateBudgetDropdownPosition() {
    const el = getBudgetTriggerElement()
    if (!el) return
    const r = el.getBoundingClientRect()
    budgetDropdownStyle.value = {
        position: 'fixed',
        top: `${Math.round(r.bottom + 6)}px`,
        left: `${Math.round(r.left)}px`,
        width: `${Math.max(Math.round(r.width), 220)}px`,
        zIndex: '10060'
    }
}

function removeBudgetDropdownListeners() {
    window.removeEventListener('scroll', updateBudgetDropdownPosition, true)
    window.removeEventListener('resize', updateBudgetDropdownPosition)
}

async function toggleBudgetDropdown() {
    const next = !showBudgetDropdown.value
    showBudgetDropdown.value = next
    if (next) {
        await nextTick()
        updateBudgetDropdownPosition()
        window.addEventListener('scroll', updateBudgetDropdownPosition, true)
        window.addEventListener('resize', updateBudgetDropdownPosition)
    } else {
        removeBudgetDropdownListeners()
    }
}

function onDocumentClick(event) {
    if (!showBudgetDropdown.value) return
    const t = event.target
    const triggerEl = getBudgetTriggerElement()
    if (triggerEl?.contains(t) || budgetDropdownPanelRef.value?.contains(t)) return
    showBudgetDropdown.value = false
    removeBudgetDropdownListeners()
}

function getDisplayValue(field, rawValue) {
    if (rawValue === null || rawValue === undefined || rawValue === '') return null
    if (field.id === 'created_on') {
        if (form.value.createdOn === 'custom_date' && form.value.createdFrom && form.value.createdTo) {
            if (form.value.createdFrom !== form.value.createdTo) {
                return `${form.value.createdFrom} to ${form.value.createdTo}`
            }
            return form.value.createdFrom
        }
        const preset = createdOnOptions.find(opt => opt.value === form.value.createdOn)
        return preset?.text || null
    }
    
    if (field.id === 'assigned_on') {
        if (form.value.assignedOn === 'custom_date' && form.value.assignedFrom && form.value.assignedTo) {
            if (form.value.assignedFrom !== form.value.assignedTo) {
                return `${form.value.assignedFrom} to ${form.value.assignedTo}`
            }
            return form.value.assignedFrom
        }
        const preset = createdOnOptions.find(opt => opt.value === form.value.assignedOn)
        return preset?.text || null
    }
    if (field.formKey === 'areaId') {
        const area = (areaOptions.value || []).find(a => String(a.id) === String(rawValue))
        if (!area) return String(rawValue)
        const first = locationFirstLine(area)
        const second = locationSecondLine(area)
        if (!second) return first
        const a = String(first || '').trim().toLowerCase()
        const b = String(second || '').trim()
        const bl = b.toLowerCase()
        if (!a) return b
        if (bl === a || bl.startsWith(`${a},`) || bl.startsWith(`${a} -`)) {
            return b
        }
        return `${first} - ${b}`
    }
    if (field.formKey === 'budgetFrom') {
        const from = form.value.budgetFrom || ''
        const to = form.value.budgetTo || ''
        if (from && to) return `${from} - ${to}`
        if (from) return `From ${from}`
        if (to) return `To ${to}`
        return null
    }
    if (field.formKey === 'source' && rawValue === 'website') {
        const sites = Array.isArray(form.value.sourceWebsite)
            ? form.value.sourceWebsite.filter(v => v != null && v !== '')
            : (form.value.sourceWebsite ? [form.value.sourceWebsite] : [])
        if (sites.length) {
            const opts = websiteSourceOptions.value
            const names = sites.map(val => {
                const opt = opts.find(o => o.value === val)
                return opt ? opt.text : String(val)
            })
            return `Website (${names.join(', ')})`
        }
        return 'Website'
    }
      if (field.formKey === 'source' && rawValue === 'portal') {
        const portals = Array.isArray(form.value.sourcePortal) 
            ? form.value.sourcePortal.filter(v => v != null && v !== '')
            : (form.value.sourcePortal ? [form.value.sourcePortal] : [])
        
        if (portals.length) {
            const opts = portalSourceOptions.value
            const names = portals.map(val => {
                const opt = opts.find(o => o.value === val)
                return opt ? opt.text : String(val)
            })
            return `Portal (${names.join(', ')})`
        }
        return 'Portal'
    }
    if (Array.isArray(rawValue)) {
        if (field.type === 'select') {
            const opts = field.options || []
            const selectedTexts = rawValue.map(val => {
                const opt = opts.find(
                    (o) =>
                        o &&
                        (o.value === val ||
                            (val != null &&
                                o.value != null &&
                                Number(o.value) === Number(val))),
                )
                return opt ? opt.text : String(val)
            })
            return selectedTexts.join(', ')
        }
        return rawValue.join(', ')
    }
    if (field.type === 'select') {
        const opts = field.formKey === 'responsible'
            ? personOptions.value
            : (field.formKey === 'branchSource' ? branchSourceOptions.value : (field.options || []))
        const opt = opts.find(
            (o) =>
                o &&
                (o.value === rawValue ||
                    (rawValue != null &&
                        o.value != null &&
                        Number(o.value) === Number(rawValue))),
        )
        return opt ? opt.text : String(rawValue)
    }
    return String(rawValue)
}

/** Normalize lead date filters to created_from / created_to (API filters leads.created_at). */
function normalizeLeadDateRange(from, to, exact) {
    let dateFrom = from || undefined
    let dateTo = to || undefined
    if (exact && !dateFrom && !dateTo) {
        dateFrom = exact
        dateTo = exact
    }
    if (dateFrom && !dateTo) dateTo = dateFrom
    if (dateTo && !dateFrom) dateFrom = dateTo
    return { from: dateFrom, to: dateTo }
}

function applySearch(options = {}) {
    let createdFrom = undefined
    let createdTo = undefined
    let createdAt = undefined
    let branchSource = form.value.branchSource || undefined
    let queryOfficeBranch = undefined
    let responsiblePersonId = form.value.responsible ?? undefined
    let teamId = form.value.team ?? undefined
    
    let officeBranches = form.value.office && form.value.office.length ? form.value.office : undefined
    
    const currentUser = user.value
    const isSuperAdmin = currentUser?.roles?.includes('super_admin')
    const userBranchName = currentUser?.admin_parent_name?.toLowerCase() || ''
    
    switch (activePill.value) {
        case 'dubai':
            const dubaiBranches = officeOptions.value.filter(opt => {
                if (!opt.value) return false
                const matchesCity = opt.text?.toLowerCase().includes('dubai') || 
                                   opt.raw?.admin_parent_name?.toLowerCase().includes('dubai')
                if (!matchesCity) return false
                
                if (isSuperAdmin) return true
                
                return opt.raw?.admin_parent_name?.toLowerCase() === userBranchName ||
                       opt.text?.toLowerCase() === userBranchName
            })
            
            if (dubaiBranches.length > 0) {
                officeBranches = dubaiBranches.map(o => o.value)
            }
            break
            
        case 'abu-dhabi':
            const abuDhabiBranches = officeOptions.value.filter(opt => {
                if (!opt.value) return false
                const matchesCity = opt.text?.toLowerCase().includes('abu dhabi') || 
                                   opt.raw?.admin_parent_name?.toLowerCase().includes('abu dhabi')
                if (!matchesCity) return false
                
                if (isSuperAdmin) return true
                
                return opt.raw?.admin_parent_name?.toLowerCase() === userBranchName ||
                       opt.text?.toLowerCase() === userBranchName
            })
            
            if (abuDhabiBranches.length > 0) {
                officeBranches = abuDhabiBranches.map(o => o.value)
            }
            break
            
        case 'my-leads':
            const user = JSON.parse(localStorage.getItem('user') || '{}')
            responsiblePersonId = user.id
            break
            
    }
    
    // Local-timezone YYYY-MM-DD — replaces `toISOString().split('T')[0]` which shifts to UTC
    // and produced 17-05 in UAE (UTC+4) before 04:00 local when the user expected 18-05.
    const toLocalDateStr = (d) => {
      const yyyy = d.getFullYear()
      const mm = String(d.getMonth() + 1).padStart(2, '0')
      const dd = String(d.getDate()).padStart(2, '0')
      return `${yyyy}-${mm}-${dd}`
    }

    if (form.value.createdOn) {
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        
        switch (form.value.createdOn) {
            case 'today':
                createdAt = toLocalDateStr(today)
                break
                
            case 'yesterday':
                const yesterday = new Date(today)
                yesterday.setDate(yesterday.getDate() - 1)
                createdAt = toLocalDateStr(yesterday)
                break
                
            case 'tomorrow':
                const tomorrow = new Date(today)
                tomorrow.setDate(tomorrow.getDate() + 1)
                createdAt = toLocalDateStr(tomorrow)
                break
                
            case 'this_week': {
                // حساب بداية الأسبوع (الاثنين)
                let dayOfWeek = today.getDay() // 0 = الأحد, 1 = الاثنين, ..., 6 = السبت
                const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1
                const startOfWeek = new Date(today)
                startOfWeek.setDate(today.getDate() - daysFromMonday)
                const endOfWeek = new Date(startOfWeek)
                endOfWeek.setDate(startOfWeek.getDate() + 6)
                createdFrom = toLocalDateStr(startOfWeek)
                createdTo = toLocalDateStr(endOfWeek)
                break
            }
                
            case 'this_month':
                createdFrom = toLocalDateStr(new Date(today.getFullYear(), today.getMonth(), 1))
                createdTo = toLocalDateStr(new Date(today.getFullYear(), today.getMonth() + 1, 0))
                break
                
            case 'current_quarter':
                const quarter = Math.floor(today.getMonth() / 3)
                createdFrom = toLocalDateStr(new Date(today.getFullYear(), quarter * 3, 1))
                createdTo = toLocalDateStr(new Date(today.getFullYear(), (quarter + 1) * 3, 0))
                break
                
            case 'last_7_days':
                createdTo = toLocalDateStr(today)
                const last7Days = new Date(today)
                last7Days.setDate(last7Days.getDate() - 7)
                createdFrom = toLocalDateStr(last7Days)
                break
                
            case 'last_30_days':
                createdTo = toLocalDateStr(today)
                const last30Days = new Date(today)
                last30Days.setDate(last30Days.getDate() - 30)
                createdFrom = toLocalDateStr(last30Days)
                break
                
            case 'last_60_days':
                createdTo = toLocalDateStr(today)
                const last60Days = new Date(today)
                last60Days.setDate(last60Days.getDate() - 60)
                createdFrom = toLocalDateStr(last60Days)
                break
                
            case 'last_90_days':
                createdTo = toLocalDateStr(today)
                const last90Days = new Date(today)
                last90Days.setDate(last90Days.getDate() - 90)
                createdFrom = toLocalDateStr(last90Days)
                break
                
            case 'last_week': {
                // الأسبوع الماضي: من الاثنين إلى الأحد
                let dayOfWeek = today.getDay()
                const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1
                // نهاية الأسبوع الماضي (الأحد)
                const endOfLastWeek = new Date(today)
                endOfLastWeek.setDate(today.getDate() - daysFromMonday - 1)
                // بداية الأسبوع الماضي (الاثنين)
                const startOfLastWeek = new Date(endOfLastWeek)
                startOfLastWeek.setDate(endOfLastWeek.getDate() - 6)
                createdFrom = toLocalDateStr(startOfLastWeek)
                createdTo = toLocalDateStr(endOfLastWeek)
                break
            }
                
            case 'last_month':
                createdFrom = toLocalDateStr(new Date(today.getFullYear(), today.getMonth() - 1, 1))
                createdTo = toLocalDateStr(new Date(today.getFullYear(), today.getMonth(), 0))
                break

            case 'last_year':
                createdFrom = toLocalDateStr(new Date(today.getFullYear() - 1, 0, 1))
                createdTo = toLocalDateStr(new Date(today.getFullYear() - 1, 11, 31))
                break
                
            case 'next_week': {
                // الأسبوع القادم: من الاثنين إلى الأحد
                let dayOfWeek = today.getDay()
                const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1
                // بداية الأسبوع القادم (الاثنين)
                const startOfNextWeek = new Date(today)
                startOfNextWeek.setDate(today.getDate() - daysFromMonday + 7)
                const endOfNextWeek = new Date(startOfNextWeek)
                endOfNextWeek.setDate(startOfNextWeek.getDate() + 6)
                createdFrom = toLocalDateStr(startOfNextWeek)
                createdTo = toLocalDateStr(endOfNextWeek)
                break
            }
                
            case 'next_month':
                createdFrom = toLocalDateStr(new Date(today.getFullYear(), today.getMonth() + 1, 1))
                createdTo = toLocalDateStr(new Date(today.getFullYear(), today.getMonth() + 2, 0))
                break
                
            case 'exact_date':
                if (form.value.exactDate) {
                    createdAt = form.value.exactDate
                }
                break
            case 'custom_date':
                if (form.value.createdFrom) {
                    createdFrom = form.value.createdFrom
                }
                if (form.value.createdTo) {
                    createdTo = form.value.createdTo
                }
                createdAt = undefined
                break
        }

        const normalizedCreated = normalizeLeadDateRange(createdFrom, createdTo, createdAt)
        createdFrom = normalizedCreated.from
        createdTo = normalizedCreated.to
        createdAt = undefined
    }

    let assignedFrom = undefined
    let assignedTo = undefined
    let assignedAt = undefined
    if (form.value.assignedOn) {
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        switch (form.value.assignedOn) {
            case 'today':
                assignedAt = toLocalDateStr(today)
                break
            case 'yesterday': {
                const yesterday = new Date(today)
                yesterday.setDate(yesterday.getDate() - 1)
                assignedAt = toLocalDateStr(yesterday)
                break
            }
            case 'this_week': {
                // حساب بداية الأسبوع (الاثنين)
                let dayOfWeek = today.getDay()
                const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1
                const startOfWeek = new Date(today)
                startOfWeek.setDate(today.getDate() - daysFromMonday)
                const endOfWeek = new Date(startOfWeek)
                endOfWeek.setDate(startOfWeek.getDate() + 6)
                assignedFrom = toLocalDateStr(startOfWeek)
                assignedTo = toLocalDateStr(endOfWeek)
                break
            }
            case 'this_month':
                assignedFrom = toLocalDateStr(new Date(today.getFullYear(), today.getMonth(), 1))
                assignedTo = toLocalDateStr(new Date(today.getFullYear(), today.getMonth() + 1, 0))
                break
            case 'last_7_days': {
                assignedTo = toLocalDateStr(today)
                const d = new Date(today)
                d.setDate(d.getDate() - 7)
                assignedFrom = toLocalDateStr(d)
                break
            }
            case 'last_30_days': {
                assignedTo = toLocalDateStr(today)
                const d = new Date(today)
                d.setDate(d.getDate() - 30)
                assignedFrom = toLocalDateStr(d)
                break
            }
            case 'last_60_days': {
                assignedTo = toLocalDateStr(today)
                const d = new Date(today)
                d.setDate(d.getDate() - 60)
                assignedFrom = toLocalDateStr(d)
                break
            }
            case 'last_90_days': {
                assignedTo = toLocalDateStr(today)
                const d = new Date(today)
                d.setDate(d.getDate() - 90)
                assignedFrom = toLocalDateStr(d)
                break
            }
            case 'last_week': {
                // الأسبوع الماضي: من الاثنين إلى الأحد
                let dayOfWeek = today.getDay()
                const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1
                // نهاية الأسبوع الماضي (الأحد)
                const endOfLastWeek = new Date(today)
                endOfLastWeek.setDate(today.getDate() - daysFromMonday - 1)
                // بداية الأسبوع الماضي (الاثنين)
                const startOfLastWeek = new Date(endOfLastWeek)
                startOfLastWeek.setDate(endOfLastWeek.getDate() - 6)
                assignedFrom = toLocalDateStr(startOfLastWeek)
                assignedTo = toLocalDateStr(endOfLastWeek)
                break
            }
            case 'last_month':
                assignedFrom = toLocalDateStr(new Date(today.getFullYear(), today.getMonth() - 1, 1))
                assignedTo = toLocalDateStr(new Date(today.getFullYear(), today.getMonth(), 0))
                break
            case 'custom_date':
                assignedFrom = form.value.assignedFrom || undefined
                assignedTo = form.value.assignedTo || undefined
                assignedAt = undefined
                break

            case 'last_year':
                assignedFrom = toLocalDateStr(new Date(today.getFullYear() - 1, 0, 1))
                assignedTo = toLocalDateStr(new Date(today.getFullYear() - 1, 11, 31))
                assignedAt = undefined
                break
        }

        const normalizedAssigned = normalizeLeadDateRange(assignedFrom, assignedTo, assignedAt)
        assignedFrom = normalizedAssigned.from
        assignedTo = normalizedAssigned.to
        assignedAt = undefined
    }

    let sourceParam = undefined
    if (form.value.source === 'website') {
        const sites = Array.isArray(form.value.sourceWebsite)
            ? form.value.sourceWebsite.filter(v => v != null && v !== '')
            : (form.value.sourceWebsite ? [form.value.sourceWebsite] : [])
        if (sites.length > 1) {
            sourceParam = sites
        } else if (sites.length === 1) {
            sourceParam = sites[0]
        } else {
            sourceParam = 'website'
        }
        }else if (form.value.source === 'portal') {
            const portals = Array.isArray(form.value.sourcePortal)
                ? form.value.sourcePortal.filter(v => v != null && v !== '')
                : (form.value.sourcePortal ? [form.value.sourcePortal] : [])
            if (portals.length > 1) {
                sourceParam = portals
            } else if (portals.length === 1) {
                sourceParam = portals[0]
            } else {
                sourceParam = 'portal'
            }
        }   else if (form.value.source) {
            sourceParam = form.value.source
        }

    const query = {
        lead_name: form.value.leadName || undefined,
        first_name: form.value.firstName || undefined,
        responsible_person_id: responsiblePersonId,
        lead_branch_source: branchSource,
        work_phone: form.value.workPhone || undefined,
        email: form.value.email || undefined,
        bedrooms: form.value.bedrooms ?? undefined,
        search: form.value.search || undefined,
        source: sourceParam,
        interaction_result: form.value.interactionResult || undefined,
        status_lead: form.value.qualityStatus || undefined,
        why_lost_lead: (getSelectedStageOrder(form.value.stageId) === 8) ? (form.value.qualityStatus || undefined) : undefined,

        created_from: createdFrom || undefined,
        created_to: createdTo || undefined,
        assigned_from: assignedFrom || undefined,
        assigned_to: assignedTo || undefined,
        team_id: teamId || undefined,
        office_branch: officeBranches || undefined,
        lead_type: form.value.leadType || undefined,
        property_status: form.value.propertyStatus || undefined,
        budget_from: parseBudgetNumber(form.value.budgetFrom),
        budget_to: parseBudgetNumber(form.value.budgetTo),
        area_id: form.value.areaId || undefined,
        property_type_id: form.value.propertyType || undefined ,
        purpose_buying: form.value.purposePurchase || undefined,
     
        
    }
    
    const quickTerm = options.extraSearch != null ? String(options.extraSearch).trim() : ''
    if (quickTerm) {
        query.search = quickTerm
    }

    Object.keys(query).forEach(k => { 
        if (query[k] === '' || query[k] === undefined || (Array.isArray(query[k]) && query[k].length === 0)) delete query[k] 
    })
    
    console.log('Search Query:', query)

    const activeFilters = []
    const visibleFields = searchFieldsConfig.value.filter(f => selectedLeadFieldIds.value.includes(f.id))
    
    visibleFields.forEach(field => {
        const raw = form.value[field.formKey]
       if (field.id === 'stage') return
        
        if (!hasValue(raw)) return
        
        const displayValue = getDisplayValue(
            { 
                ...field, 
                options: 
                    field.formKey === 'responsible' ? personOptions.value : 
                    field.formKey === 'branchSource' ? branchSourceOptions.value : 
                    field.formKey === 'source' ? sourceOptions.value :
                    field.formKey === 'team' ? computedTeamOptions.value : 
                    field.formKey === 'office' ? branchSelectOptions.value :
                    field.formKey === 'areaId' ? areaOptions.value : 
                    field.formKey === 'qualityStatus' ? qualityStatusOptions.value :
                    field.formKey === 'leadType' ? leadTypeOptions :
                    field.formKey === 'propertyStatus' ? propertyStatusOptions :
                    (field.options || [])
            },
            raw
        )
        
        if (displayValue) {
            activeFilters.push({
                id: field.id,
                queryKey: field.queryKey,
                label: field.label,
                value: displayValue
            })
        }
    })

    const appendFilterIfMissing = (id, queryKey, label, value) => {
        const text = value != null ? String(value).trim() : ''
        if (!text || activeFilters.some((f) => f.id === id)) return
        activeFilters.push({ id, queryKey, label, value: text })
    }

    if (createdFrom || createdTo) {
        let createdLabel = createdFrom || ''
        if (createdFrom && createdTo && createdFrom !== createdTo) {
            createdLabel = `${createdFrom} to ${createdTo}`
        } else if (createdTo) {
            createdLabel = createdTo
        }
        appendFilterIfMissing('created_on', 'created_at', 'Created On', createdLabel)
    }

    if (sourceParam) {
        const sourceField = searchFieldsConfig.value.find((f) => f.id === 'source')
        const sourceDisplay = getDisplayValue(
            {
                ...sourceField,
                options: sourceOptions.value,
            },
            form.value.source,
        )
        appendFilterIfMissing('source', 'source', 'Source', sourceDisplay || String(sourceParam))
    }
    
    const pill = sidebarPills.value.find(p => p.id === activePill.value)
    const pillData = pill ? { id: pill.id, label: pill.label } : null
    
    if (!options.keepOpen) {
        show.value = false
    }
    emit('search', { query, activePill: pillData, activeFilters, keepOpen: !!options.keepOpen })
}

defineExpose({
    applySearch,
})

async function handleSidebarPillClick(pill) {
    console.log('Sidebar pill clicked:', pill)
    activePill.value = pill.id
    
    if (pill.type === 'city') {
        // City pill now only opens its branch list; selection happens per branch button.
        // Keep current selected branches unchanged so users can mix multiple branches easily.
        const normalizedOffices = normalizeOfficeSelection(form.value.office)
        form.value.office = normalizedOffices
        selectedOffice.value = normalizedOffices.length ? [...normalizedOffices] : null
        selectedPillType.value = pill.id

        // Refresh dependent dropdowns based on current office selection
        await Promise.all([
            fetchResponsiblePersonsWithFilter(),
            fetchTeamsWithFilter()
        ])
    } else {
        // For non-city pills, clear office selection
        form.value.office = []
        selectedOffice.value = null
        selectedPillType.value = null
    showBudgetDropdown.value = false
        
        // Clear responsible and team
        form.value.responsible = ''
        form.value.team = ''
        
        // Fetch filtered data
        await Promise.all([
            fetchResponsiblePersonsWithFilter(),
            fetchTeamsWithFilter()
        ])
    }
}

async function selectCityBranch(cityPill, child) {
    console.log('City branch selected:', cityPill, child)
    activePill.value = cityPill.id
    
    // Toggle selection for multi-select
    const branchId = normalizeOfficeId(child.value)
    const offices = normalizeOfficeSelection(form.value.office)
    
    const index = offices.indexOf(branchId)
    if (index === -1) {
        offices.push(branchId)
    } else {
        offices.splice(index, 1)
    }
    form.value.office = offices
    
    // Update selectedOffice and trigger filtering
    if (offices.length) {
        selectedOffice.value = [...offices]
    } else {
        selectedOffice.value = null
    }
    selectedPillType.value = cityPill.id

    await Promise.all([
        fetchResponsiblePersonsWithFilter(),
        fetchTeamsWithFilter()
    ])
    await nextTick()
    pruneTeamAndResponsible()
}

async function fetchResponsiblePersonsWithFilter() {
    try {
        const params = {}
        
        // Add office filter if selected (now supports multiple offices)
        if (selectedOffice.value && Array.isArray(selectedOffice.value) && selectedOffice.value.length) {
            params.office_ids = selectedOffice.value.join(',')
        } else if (selectedOffice.value && !Array.isArray(selectedOffice.value)) {
            params.office_id = selectedOffice.value
        }
        
        // Add pill filter if needed
        if (selectedPillType.value) {
            params.pill_type = selectedPillType.value
        }
        
        const res = await api.get('/available-responsible-persons', { params })
        if (res.data.data) {
            allResponsiblePersons.value = res.data.data.map(person => ({
                ...person,
            }))
        } else {
            allResponsiblePersons.value = []
        }
    } catch (error) {
        console.error('Error fetching responsible persons with filter:', error)
        allResponsiblePersons.value = []
    }
}

async function fetchBranchSources() {
    try {
        const res = await api.get('/get/lead/branch_source')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            branchSourceOptions.value = [
                // { value: null, text: 'Select Branch Source' },
                ...data.map(b => ({ value: b.name, text: b.name }))
            ]
        }
    } catch (_) {}
}

async function fetchStages() {
    try {
        const res = await api.get('/stages')
        const raw = res.data?.data
        const data = Array.isArray(raw?.data) ? raw.data : (Array.isArray(raw) ? raw : [])
        if (data.length) {
            // ✅ تصفية أول اتنين Stage (بناءً على order)
            const filteredStages = data.filter(s => {
                const order = Number(s.order || 0)
                return order !== 1 && order !== 2
            })
            
            stageOptions.value = [
                // { value: null, text: 'Select Stage' },
                ...filteredStages.map(s => ({ 
                    value: s.id, 
                    text: s.name, 
                    order: Number(s.order || 0) 
                }))
            ]
        }
    } catch (_) {}
}

async function fetchSources() {
    // Source options are fixed by requirement:
    // Meta or Website, with a second selector for website source.
}

async function fetchTeams() {
    try {
        const res = await api.get('/teams-with-leads')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            allTeams.value = data.map(team => ({
                id: team.id,
                name: team.name,
                office_id: team.office_id || team.admin_parent_id || null,
                admin_parent_id: team.admin_parent_id || null,
                admin_parent_name: team.admin_parent_name || null,
                city: team.city || null,
                // Preserve display fields the API actually sends — otherwise the team v-select
                // template falls through to DEFAULT_TEAM_AVATAR and loses the role/parent/branch
                // lines that match the responsible-person row.
                avatar: team.avatar || null,
                role_name: team.role_name || null,
                parent_name: team.parent_name || null,
                branch_name: team.branch_name || null,
                team_size: team.team_size ?? null,
            }))
        }
    } catch (error) {
        console.error('Error fetching teams:', error)
    }
}

async function fetchTeamsWithFilter() {
    try {
        const params = {}
        
        // Add office filter if selected (now supports multiple offices)
        if (selectedOffice.value && Array.isArray(selectedOffice.value) && selectedOffice.value.length) {
            params.office_ids = selectedOffice.value.join(',')
        } else if (selectedOffice.value && !Array.isArray(selectedOffice.value)) {
            params.office_id = selectedOffice.value
        }
        
        // Add pill filter if needed
        if (selectedPillType.value) {
            params.pill_type = selectedPillType.value
        }
        
        const res = await api.get('/teams-with-leads', { params })
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            allTeams.value = data.map(team => ({
                id: team.id,
                name: team.name,
                office_id: team.office_id || team.admin_parent_id || null,
                city: team.city || null,
                admin_parent_id: team.admin_parent_id || null,
                admin_parent_name: team.admin_parent_name || null,
                // See fetchTeams() — same fields are required by the v-select template.
                avatar: team.avatar || null,
                role_name: team.role_name || null,
                parent_name: team.parent_name || null,
                branch_name: team.branch_name || null,
                team_size: team.team_size ?? null,
            }))
        } else {
            allTeams.value = []
        }
    } catch (error) {
        console.error('Error fetching teams with filter:', error)
        allTeams.value = []
    }
}

async function fetchOffices() {
    try {
        const res = await api.get('/get-offices')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            officeOptions.value = [
                // { value: null, text: 'Select Office' },
                ...data.map(office => ({
                    value: office.id,
                    text: office.name,
                    cityKey: detectCityKeyFromOffice(office),
                    raw: office,
                }))
            ]
        }
    } catch (error) {
        console.error('Error fetching offices:', error)
        try {
            const res2 = await api.get('/users', {
                params: {
                    role: 'admin',
                    has_parent: true
                }
            })
            const admins = res2.data?.data
            if (Array.isArray(admins) && admins.length) {
                officeOptions.value = [
                    // { value: null, text: 'Select Office' },
                    ...admins.map(admin => ({
                        value: admin.id,
                        text: admin.name,
                        cityKey: detectCityKeyFromOffice(admin),
                        raw: admin,
                    }))
                ]
            }
        } catch (err) {
            console.error('Error fetching admin users:', err)
        }
    }
}

function resetFormValues() {
    form.value = {
        search: '',
        id: '',
        firstName: '',
        responsible: '',
        createdOn: '',
        assignedOn: '',
        createdFrom: '',     
        createdTo: '',  
        assignedFrom: '',
        assignedTo: '',
        created_from: '',     
        created_to: '',  
        stageChangedBy: '',
        branchSource: '',
        workPhone: '',
        email: '',
        bedrooms: '',
        source: '',
        sourceWebsite: [],
        interactionResult: '',
        qualityStatus: '',
        team: '',
        office: [],
         leadType: '',
        propertyStatus: '',
        budgetFrom: '',
        budgetTo: '',
        areaId: '',
         propertyType: '',
           stageId: '',
        purposePurchase: '',
        interactionResult: '',
        qualityStatus: '',
    }
    selectedOffice.value = null
    selectedPillType.value = null
    showBudgetDropdown.value = false
    removeBudgetDropdownListeners()
}

const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
const formatYmd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
const inRange = (d, a, b) => a && b && startOfDay(d) >= startOfDay(a) && startOfDay(d) <= startOfDay(b)

const calendarCells = computed(() => {
    const y = calendarMonth.value.getFullYear()
    const m = calendarMonth.value.getMonth()
    const first = new Date(y, m, 1)
    const offset = first.getDay()
    const daysInMonth = new Date(y, m + 1, 0).getDate()
    const prevDays = new Date(y, m, 0).getDate()
    const cells = []

    for (let i = offset - 1; i >= 0; i -= 1) {
        const day = prevDays - i
        const date = new Date(y, m - 1, day)
        cells.push({ key: `p-${day}`, day, date, currentMonth: false })
    }
    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = new Date(y, m, day)
        cells.push({ key: `c-${day}`, day, date, currentMonth: true })
    }
    while (cells.length < 42) {
        const day = cells.length - (offset + daysInMonth) + 1
        const date = new Date(y, m + 1, day)
        cells.push({ key: `n-${day}`, day, date, currentMonth: false })
    }
    return cells
})

function openDatePicker(fieldId = 'created_on', event) {
    event?.stopPropagation?.()
    activeDateField.value = fieldId
    const isAssigned = fieldId === 'assigned_on'
    const dateKey = isAssigned ? 'assignedOn' : 'createdOn'
    const fromKey = isAssigned ? 'assignedFrom' : 'createdFrom'
    const toKey = isAssigned ? 'assignedTo' : 'createdTo'

    if (form.value[dateKey] && form.value[dateKey] !== 'custom_date') {
        selectedPreset.value = form.value[dateKey]
        selectPresetRange(form.value[dateKey])
    } else if (form.value[dateKey] === 'custom_date' && form.value[fromKey] && form.value[toKey]) {
        selectedPreset.value = 'custom_date'
        startDate.value = startOfDay(new Date(form.value[fromKey]))
        endDate.value = startOfDay(new Date(form.value[toKey]))
        calendarMonth.value = new Date(startDate.value.getFullYear(), startDate.value.getMonth(), 1)
    }
    showDateModal.value = true
}

function selectPresetRange(preset) {
    selectedPreset.value = preset
    const today = new Date()
    const y = today.getFullYear()
    const m = today.getMonth()
    const weekDays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']

    if (preset === 'custom_date') return
    if (preset === 'today') {
        startDate.value = startOfDay(today)
        endDate.value = startOfDay(today)
    } else if (preset === 'yesterday') {
        const d = new Date(y, m, today.getDate() - 1)
        startDate.value = startOfDay(d)
        endDate.value = startOfDay(d)
    } else if (preset === 'this_week') {
        // حساب بداية الأسبوع (الاثنين)
        let dayOfWeek = today.getDay() // 0 = الأحد, 1 = الاثنين, ..., 6 = السبت
        // تحويل الأحد (0) إلى 7
        const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1
        const s = new Date(today)
        s.setDate(today.getDate() - daysFromMonday)
        const e = new Date(s)
        e.setDate(s.getDate() + 6)
        startDate.value = startOfDay(s)
        endDate.value = startOfDay(e)
    } else if (preset === 'last_week') {
        // الأسبوع الماضي: من الاثنين إلى الأحد
        let dayOfWeek = today.getDay()
        const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1
        // نهاية الأسبوع الماضي (الأحد)
        const end = new Date(today)
        end.setDate(today.getDate() - daysFromMonday - 1)
        // بداية الأسبوع الماضي (الاثنين)
        const start = new Date(end)
        start.setDate(end.getDate() - 6)
        startDate.value = startOfDay(start)
        endDate.value = startOfDay(end)
    } else if (preset === 'this_month') {
        startDate.value = new Date(y, m, 1)
        endDate.value = new Date(y, m + 1, 0)
    } else if (preset === 'last_month') {
        startDate.value = new Date(y, m - 1, 1)
        endDate.value = new Date(y, m, 0)
    } else if (preset === 'last_year') {
        startDate.value = new Date(y - 1, 0, 1)
        endDate.value = new Date(y - 1, 11, 31)
    }
    calendarMonth.value = new Date(startDate.value.getFullYear(), startDate.value.getMonth(), 1)
}
function pickDate(date) {
    if (!startDate.value || (startDate.value && endDate.value)) {
        startDate.value = startOfDay(date)
        endDate.value = null
        selectedPreset.value = 'custom_date'
        return
    }
    if (startOfDay(date) < startOfDay(startDate.value)) {
        endDate.value = startDate.value
        startDate.value = startOfDay(date)
    } else {
        endDate.value = startOfDay(date)
    }
}

const isSelectedDate = (date) => sameDay(date, startDate.value) || sameDay(date, endDate.value)
const isInRange = (date) => inRange(date, startDate.value, endDate.value)

function changeMonth(delta) {
    calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + delta, 1)
}

function applyDateRange() {
    const isAssigned = activeDateField.value === 'assigned_on'
    const dateKey = isAssigned ? 'assignedOn' : 'createdOn'
    const fromKey = isAssigned ? 'assignedFrom' : 'createdFrom'
    const toKey = isAssigned ? 'assignedTo' : 'createdTo'

    if (selectedPreset.value && selectedPreset.value !== 'custom_date') {
        form.value[dateKey] = selectedPreset.value
        form.value[fromKey] = ''
        form.value[toKey] = ''
    } else if (startDate.value && !endDate.value) {
        // Treat a single picked day as one-day custom range.
        const oneDay = formatYmd(startDate.value)
        form.value[dateKey] = 'custom_date'
        form.value[fromKey] = oneDay
        form.value[toKey] = oneDay
    } else if (startDate.value && endDate.value) {
        form.value[dateKey] = 'custom_date'
        form.value[fromKey] = formatYmd(startDate.value)
        form.value[toKey] = formatYmd(endDate.value)
    }
    showDateModal.value = false
}

const resetForm = () => {
    resetFormValues()
    show.value = false
    emit('search', { query: null, activePill: null, activeFilters: [] })
}
watch(() => form.value.responsible, async (newResponsibleId) => {
    if (hydratingFromQuery.value) return
    if (!newResponsibleId) return

    const selectedPerson = allResponsiblePersons.value.find(p => p.id === newResponsibleId)
    if (!selectedPerson) return

    syncingFromResponsible.value = true
    try {
        if (selectedPerson.team_id != null && selectedPerson.team_id !== '') {
            form.value.team = Number(selectedPerson.team_id)
        }

        const branchIdFromApi =
            selectedPerson.branch_id || selectedPerson.office_id || selectedPerson.officeId

        if (branchIdFromApi) {
            const normalizedBranch = normalizeOfficeId(branchIdFromApi)
            if (normalizedBranch !== null && normalizedBranch !== undefined) {
                form.value.office = [normalizedBranch]
                selectedOffice.value = [normalizedBranch]
            }
        }

        await Promise.all([
            fetchResponsiblePersonsWithFilter(),
            fetchTeamsWithFilter()
        ])
        await nextTick()
        pruneTeamAndResponsible()
    } finally {
        syncingFromResponsible.value = false
    }
}, { immediate: false })

watch(
    () => form.value.team,
    async (teamId) => {
        if (hydratingFromQuery.value) return
        if (syncingFromResponsible.value) return
        if (!teamId) {
            await Promise.all([
                fetchResponsiblePersonsWithFilter(),
                fetchTeamsWithFilter()
            ])
            await nextTick()
            pruneTeamAndResponsible()
            return
        }
        const team = allTeams.value.find((t) => Number(t.id) === Number(teamId))
        const bid = teamBranchId(team)
        if (bid != null) {
            form.value.office = [bid]
            selectedOffice.value = [bid]
        }
        await Promise.all([
            fetchResponsiblePersonsWithFilter(),
            fetchTeamsWithFilter()
        ])
        await nextTick()
        pruneTeamAndResponsible()
    },
)
watch(officeOptions, (newOptions) => {
    if (form.value.office && form.value.office.length && newOptions.length) {
        const normalizedSelection = normalizeOfficeSelection(form.value.office)
        const validOffices = normalizedSelection.filter(officeId =>
            newOptions.some(opt => normalizeOfficeId(opt.value) === officeId)
        )
        if (validOffices.length !== normalizedSelection.length) {
            form.value.office = validOffices
        } else {
            form.value.office = normalizedSelection
        }
        selectedOffice.value = [...form.value.office]
    }
}, { deep: true })

watch(() => form.value.createdOn, (newVal, oldVal) => {
    if (oldVal === 'custom_date' && newVal !== 'custom_date') {
        form.value.createdFrom = ''
        form.value.createdTo = ''
    }
})

watch(() => form.value.assignedOn, (newVal, oldVal) => {
    if (oldVal === 'custom_date' && newVal !== 'custom_date') {
        form.value.assignedFrom = ''
        form.value.assignedTo = ''
    }
})

watch(() => form.value.source, (newVal) => {
    // Skip while hydrating from currentQuery — otherwise sourceWebsite/sourcePortal
    // from the saved search get wiped the moment we assign form.source.
    if (hydratingFromQuery.value) return
    if (newVal === 'website') {
        form.value.sourceWebsite = []
    } else if (newVal === 'portal') {
        form.value.sourceWebsite = []
    } else {
        form.value.sourceWebsite = []
        form.value.sourcePortal = []
    }
})

// Watch for stage changes to update quality_status options and interaction_result visibility
watch(() => form.value.stageId, (newVal) => {
    // Same hydration guard — don't strip qualityStatus/interactionResult that we just restored.
    if (hydratingFromQuery.value) return
    const stageOrder = getSelectedStageOrder(newVal)
    
    // Clear quality_status if it doesn't match the new stage
    if (form.value.qualityStatus) {
        const currentQuality = form.value.qualityStatus
        let isValidForStage = false
        
        if (stageOrder === 4) {
            isValidForStage = ['cold', 'warm', 'hot'].includes(currentQuality)
        } else if (stageOrder === 8) {
            isValidForStage = ['lost_by_other_company', 'lost_by_our_company'].includes(currentQuality)
        } else if (stageOrder === 9) {
            isValidForStage = ['no_answer', 'contacted', 'wrong_person'].includes(currentQuality)
        } else if (stageOrder === 10) {
            isValidForStage = [
                'wrong_contact_details', 'no_answer_multiple_calls',
            'job_seeker', 'broker', 'registered_by_mistake','spam_leads', 'blacklist'
            ].includes(currentQuality)
        }
        
        if (!isValidForStage) {
            form.value.qualityStatus = ''
        }
    }
    
    // Clear interaction_result if stage is not 2
    if (stageOrder !== 2) {
        form.value.interactionResult = ''
    }
    if (newVal) {
        // Force re-render of qualityStatusOptions by triggering a computed refresh
        nextTick(() => {
            const temp = [...selectedLeadFieldIds.value]
            selectedLeadFieldIds.value = temp
        })
    }
})
watch(() => form.value.leadType, () => {
    if (validationErrors?.value?.leadType) {
        delete validationErrors.value.leadType
    }
})

watch(() => form.value.propertyStatus, () => {
    if (validationErrors?.value?.propertyStatus) {
        delete validationErrors.value.propertyStatus
    }
})

watch(() => form.value.budgetFrom, () => {
    if (validationErrors?.value?.budgetFrom) {
        delete validationErrors.value.budgetFrom
    }
})

watch(() => form.value.budgetTo, () => {
    if (validationErrors?.value?.budgetTo) {
        delete validationErrors.value.budgetTo
    }
})

watch(() => props.modelValue, (val) => {
    show.value = val
    if (val) {
        console.log('Modal opening with initialActivePill:', props.initialActivePill)
        if (props.initialActivePill) {
            activePill.value = props.initialActivePill
            console.log('Setting activePill to:', props.initialActivePill)
        }
        
        restoreSavedFields()
        
        if (!props.hasActiveFilters) {
            resetFormValues()
        } else {
            syncFormFromQuery(props.currentQuery)
        }
    }
})

// تأكد من أن restoreSavedFields هي async
const restoreSavedFields = async () => {
    try {
        const savedFields = localStorage.getItem(FIELD_STORAGE_KEY)
        console.log('Raw savedFields from localStorage:', savedFields)
        
        if (savedFields) {
            const parsed = JSON.parse(savedFields)
            console.log('Parsed saved fields:', parsed)
            
            if (Array.isArray(parsed) && parsed.length) {
                const allFieldIds = getAllFieldIds.value
                console.log('All available field IDs:', allFieldIds)
                
                const validFields = parsed.filter(id => allFieldIds.includes(id))
                console.log('Valid fields after filtering:', validFields)
                
                if (validFields.length === 0) {
                    console.warn('No valid fields found, using all fields')
                    selectedLeadFieldIds.value = [...allFieldIds]
                } else {
                    selectedLeadFieldIds.value = validFields
                }
            } else {
                selectedLeadFieldIds.value = [...getAllFieldIds.value]
            }
        } else {
            selectedLeadFieldIds.value = [...getAllFieldIds.value]
        }
        
        console.log('Final selectedLeadFieldIds after restore:', selectedLeadFieldIds.value)
        
        // انتظر حتى يتم تحديث Vue
        await nextTick()
        
        // فرض تحديث visibleSearchSections
        const sections = visibleSearchSections.value
        console.log('Sections after restore:', sections.map(s => ({
            title: s.title,
            fieldCount: s.fields.length,
            fieldIds: s.fields.map(f => f.id)
        })))
        
    } catch (error) {
        console.error('Error restoring saved fields:', error)
        selectedLeadFieldIds.value = [...getAllFieldIds.value]
    }
}

const defaultLeadFieldIds = computed(() => {
    // قائمة الحقول الافتراضية تشمل كل الحقول الأساسية
    return searchFieldsConfig.value
        .filter(f => !['quality_status', 'lead_type', 'property_status', 'budget_from', 'interaction_result'].includes(f.id))
        .map(f => f.id)
})

const getAllFieldIds = computed(() => {
    return searchFieldsConfig.value.map(f => f.id)
})

function restoreDefaultFields() {
    selectedLeadFieldIds.value = [...defaultLeadFieldIds.value]
    
    localStorage.setItem(FIELD_STORAGE_KEY, JSON.stringify(selectedLeadFieldIds.value))
    console.log('Reset to default fields:', selectedLeadFieldIds.value)
    if (window.$showNotification) {
        window.$showNotification('Default fields restored', 'success')
    }
}

onMounted(async () => {
    console.log('LeadSearchModal mounted, key:', props.key)
    document.addEventListener('click', onDocumentClick)
    updateUserFromStorage()

    await Promise.all([
        fetchResponsiblePersonsWithFilter(),
        fetchBranchSources(),
        fetchStages(),
        fetchSources(),
        fetchTeamsWithFilter(),
        fetchOffices(),
        fetchAreas(),
        fetchPropertyTypes()
    ])

    // تحميل الحقول المحفوظة عند التحميل
    await restoreSavedFields()

    // Hydrate the form from the last-applied query NOW that all dropdown options have loaded.
    // The modal is v-if'd in the navbar, so each open is a fresh mount — `props.modelValue`
    // is already `true` on mount, which means the open-side watchers never fire (they only
    // react to changes). This is the one place we know we have both `currentQuery` and
    // all the option lists, so the saved selections will display correctly.
    if (props.modelValue) {
        syncFormFromQuery(props.currentQuery)
    }

    // Hydration is done — wait one tick so the form-value assignment has flushed,
    // then drop the overlay and let the user interact with the populated fields.
    await nextTick()
    isInitializing.value = false

    console.log('Initial data loaded, selected fields:', selectedLeadFieldIds.value)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    removeBudgetDropdownListeners()
})
</script>

<style scoped>
/* Add selected indicator style */
.selected-indicator {
    margin-left: 4px;
    font-size: 12px;
}

/* Keep all existing styles from your original code */
.lead-search-dropdown-panel {
    width: 1000px;
    max-width: calc(100vw - 32px);
    min-height: 460px;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
    background: #fff;
    /* overflow: hidden; */
    position: relative;
}

.lead-search-shell {
    position: relative;
}

.lead-search-initial-loader {
    position: absolute;
    inset: 0;
    z-index: 10070;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(2px);
    border-radius: 12px;
}

.lead-search-initial-loader__spinner {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid #E2E8F0;
    border-top-color: #733E87;
    animation: lead-search-spin 0.8s linear infinite;
}

.lead-search-initial-loader__text {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    letter-spacing: 0.02em;
}

@keyframes lead-search-spin {
    to { transform: rotate(360deg); }
}

.lead-search-container {
    min-height: 460px;
    background: #fff;
    border-radius: 12px;
    /* overflow: hidden; */
}

.sidebar-pills {
    min-width: 221px;
    background: #f8fafc;
    padding: 16px 14px !important;
}

.pill-btn {
    border: none;
    background: #fff;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    min-height: 30px;
    padding: 0 12px;
    text-align: center;
    transition: all 0.2s;
    border: 1px solid #E2E8F0;
    width: fit-content;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.pill-btn.active {
    background: #0B0736;
    color: #fff;
    border-color: #0B0736;
}

.pill-count {
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #e2e8f0;
    color: #334155;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
}

.pill-btn.active .pill-count {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

.city-children-wrap {
    margin-top: -6px;
    padding: 8px 8px 2px 8px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.city-children-title {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    padding: 0 4px;
}

.city-child-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    border-radius: 10px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    transition: all 0.16s ease;
}

.city-child-btn:hover {
    background: #eef2ff;
    border-color: #c7d2fe;
}

.city-child-btn.active {
    background: #eaf3ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.city-children-empty {
    padding: 4px 8px 8px;
    font-size: 11px;
    color: #94a3b8;
}

.city-child-list-enter-active,
.city-child-list-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.city-child-list-enter-from,
.city-child-list-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.form-content-wrapper {
    padding: 20px 14px !important;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.search-sections-wrap {
    max-height: 58vh;
    overflow-y: auto;
    padding-right: 4px;
    flex: 1 1 auto;
    min-height: 0;
}

.search-sections-wrap {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.search-modal-footer {
    flex-shrink: 0;
    border-top: 1px solid #eef2f7;
    padding-top: 12px !important;
    margin-top: 12px !important;
    background: #fff;
}

.search-section-card {
    background: #fff;
    border: 1px solid #F1F5F9;
    border-radius: 14px;
    padding: 10px 10px 6px;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.search-section-title {
    font-size: 13px;
    font-weight: 600;
    color: #0B0736;
    margin-bottom: 10px;
    padding-bottom: 0;
    border-bottom: none;
}

@media (max-width: 1199px) {
    .lead-search-dropdown-panel {
        width: calc(100vw - 24px);
        max-width: calc(100vw - 24px);
    }

    .lead-search-container {
        min-height: auto;
    }

    .sidebar-pills {
        min-width: 190px;
        padding: 14px !important;
    }
}

@media (max-width: 991px) {
    .lead-search-container {
        flex-direction: column;
    }

    .sidebar-pills {
        width: 100%;
        min-width: 100%;
        border-right: none !important;
        border-bottom: 1px solid #E2E8F0;
        padding: 12px !important;
        flex-direction: row !important;
        flex-wrap: wrap;
        gap: 8px !important;
    }

    .form-content-wrapper {
        padding: 14px 12px !important;
    }
}

@media (max-width: 767px) {
    .lead-search-dropdown-panel {
        width: calc(100vw - 12px);
        max-width: calc(100vw - 12px);
        min-height: auto;
        border-radius: 10px;
    }

    .lead-search-container {
        border-radius: 10px;
    }

    .search-section-card .row {
        --bs-gutter-x: 0.75rem;
        --bs-gutter-y: 0.5rem;
    }

    .search-section-card .col-md-6 {
        width: 100%;
    }

    .close-btn {
        right: 6px;
    }

    .btn-reset, .btn-search, .btn-cancel, .btn-apply {
        padding: 8px 16px;
        font-size: 13px;
    }

    .search-modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 5;
        padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
    }
}

.close-btn {
        position: absolute;
    top: 8px;
    right: -61px;
    width: 83px;
    height: 49px;
    color: rgb(255, 255, 255);
    font-size: 18px;
    line-height: 1;
    box-shadow: rgba(15, 23, 42, 0.2) 0px 8px 16px;
    z-index: -1;
    display: flex;
    justify-content: center;
    align-items: center;
    border-width: 1px;
    border-style: solid;
    border-color: rgba(115, 62, 135, 0.75);
    border-image: initial;
    border-radius: 999px;
    background: var(--gradient-crm, linear-gradient(135deg, #0b0736 0%, #733e87 100%));
    padding: 0px;
    transition: filter 0.2s;
}

.form-label-custom {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 4px;
}

.custom-input {
    height: 40px !important;
    border-radius: 9px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 12px !important;
    color: #64748B !important;
    font-family: 'Montserrat';
}

.custom-input::placeholder {
    color: #94a3b8 !important;
    opacity: 1;
    font-size: 12px !important;
    font-family: 'Montserrat';
}

.custom-date-trigger {
    width: 100%;
    height: 40px;
    border-radius: 9px;
    border: 1px solid #E2E8F0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    font-size: 12px;
    color: #64748B;
    font-family: 'Montserrat';
}

.custom-date-trigger:hover {
    border-color: #cbd5e1;
}


.budget-field-wrap {
    position: relative;
}

/* Teleported to body so modal overflow:hidden does not clip the panel. Must sit above
 * the search modal (which uses z-index 15000+ when teleported from the navbar dropdown). */
.budget-dropdown--portal {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
    padding: 10px;
    z-index: 100003 !important;
}

.budget-from-to-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.budget-col {
    min-width: 0;
}

.budget-input-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.budget-dropdown-input {
    height: 38px !important;
}

.location-option {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.location-option-icon {
    color: #64748b;
    margin-top: 2px;
}

.location-option-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.location-option-name {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.location-option-subtitle {
    font-size: 11px;
    color: #64748b;
}

.location-selected {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.location-selected-name {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.2;
}

.location-selected-subtitle {
    font-size: 11px;
    color: #64748b;
    line-height: 1.2;
}


/* Teleported to body so search modal/dropdown overflow does not clip the calendar */
.lr-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    /* Above navbar search panel (15000) and kanban overlays */
    z-index: 100002;
    padding: 12px;
}

.lr-date-modal {
    width: min(860px, 96vw);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 25px 80px rgba(2, 6, 23, 0.25);
    display: grid;
    grid-template-columns: 220px 1fr;
    overflow: hidden;
}

.lr-date-left {
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lr-date-preset {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    padding: 7px 10px;
    font-size: 12px;
    color: #334155;
    text-align: left;
    transition: all .15s ease;
}

.lr-date-preset.active {
    background: #0B0736;
    border-color: #0B0736;
    color: #fff;
}

.lr-date-right {
    padding: 14px;
}

.lr-calendar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 700;
    color: #0f172a;
}

.lr-calendar-head button {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 9px;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
}

.lr-calendar-head button iconify-icon {
    font-size: 16px;
    line-height: 1;
}

.lr-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    margin-bottom: 6px;
}

.lr-weekdays span {
    text-align: center;
    font-size: 11px;
    color: #64748b;
    font-weight: 700;
}

.lr-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
}

.lr-day {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    min-height: 34px;
    font-size: 12px;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0;
}

.lr-day.muted {
    opacity: .45;
}

.lr-day.selected {
    background: #0B0736;
    border-color: #0B0736;
    color: #fff;
}

.lr-day.inrange:not(.selected) {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.lr-date-actions.large {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Custom v-select styles */
:deep(.custom-v-select) {
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__dropdown-toggle) {
    height: 40px;
    border-radius: 9px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
    display: flex !important;
    align-items: center !important;
    box-sizing: border-box;
}

:deep(.custom-v-select .vs__selected-options) {
    display: flex !important;
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
    min-width: 0;
    flex: 1 1 auto;
    align-items: center !important;
    align-self: center !important;
    height: auto !important;
}

:deep(.custom-v-select.vs--single .vs__selected-options) {
    align-items: center !important;
}

:deep(.custom-v-select:not(.office-multi-select) .vs__selected) {
    font-size: 12px;
    color: #64748B;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
    max-width: 100%;
    display: flex !important;
    align-items: center !important;
    align-self: center !important;
    height: auto !important;
    min-width: 0;
    line-height: 1.35 !important;
    box-sizing: border-box;
}

/* Branch multi-select: show all selected options clearly */
:deep(.office-multi-select .vs__selected-options) {
    flex-wrap: wrap !important;
    overflow: visible !important;
    max-width: calc(100% - 30px);
    gap: 4px;
    padding-top: 4px;
    padding-bottom: 4px;
    align-items: center !important;
    align-self: center !important;
    height: auto !important;
}

:deep(.office-multi-select .vs__selected) {
    display: inline-flex !important;
    align-items: center;
    line-height: 1.2 !important;
    margin: 0 !important;
    padding: 2px 8px !important;
    border-radius: 999px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #1e3a8a;
    font-size: 12px;
    white-space: nowrap;
}

:deep(.custom-v-select .vs__search) {
    font-size: 12px;
    color: #64748B;
    margin: 0;
}

:deep(.office-multi-select .vs__search) {
    min-width: 80px;
    line-height: 1.4;
}

:deep(.custom-v-select:not(.office-multi-select) .vs__search) {
    padding: 0 4px;
    flex: 1 1 0% !important;
    min-width: 0 !important;
    width: auto !important;
    align-self: center !important;
    height: auto !important;
    line-height: 1.35 !important;
    box-sizing: border-box !important;
}

:deep(.custom-v-select.vs--single input.vs__search) {
    line-height: 1.35 !important;
}

:deep(.custom-v-select .vs__placeholder) {
    align-self: center !important;
    display: flex !important;
    align-items: center !important;
    height: auto !important;
    margin: 0 !important;
}

:deep(.custom-v-select .vs__search::placeholder) {
    color: #94a3b8;
    font-size: 12px;
}

:deep(.custom-v-select .vs__actions) {
    padding: 0 8px;
    align-self: center !important;
    display: flex !important;
    align-items: center !important;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 13px;
    color: #cfdbec;
}

:deep(.custom-v-select svg) {
    vertical-align: middle !important;
}

:deep(.custom-v-select .vs__dropdown-menu) {
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0;
    /*margin-top: 5px;*/
    z-index: 1100;
}

:deep(.custom-v-select .vs__dropdown-option) {
    padding: 5px 10px;
    font-size: 12px;
    color: #475569;
    transition: all 0.2s;
      font-size: 14px !important;
}

:deep(.custom-v-select .vs__dropdown-option--highlight) {
    background: #733E87 !important;
    color: #fff !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: #733E87 !important;
    color: #fff !important;
}

.footer-link {
    font-size: 14px;
    color: #3B82F6;
    font-weight: 500;
}

.btn-reset,.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #0B0736;
}

.btn-search,.btn-apply {
    background: #000;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-search:disabled,
.btn-reset:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.btn-search-spinner {
    font-size: 16px;
    animation: lead-search-spin 0.75s linear infinite;
}

@keyframes lead-search-spin {
    to { transform: rotate(360deg); }
}

/* Responsible person select — same info density as ResponsiblePersonSection modal */
:deep(.lead-search-rp-select .vs__dropdown-menu) {
    max-height: min(360px, 55vh) !important;
}

:deep(.lead-search-rp-select .vs__dropdown-option) {
    padding: 8px 10px !important;
    white-space: normal !important;
      font-size: 14px !important;
}

:deep(.lead-search-rp-select .vs__selected) {
    line-height: 1.35 !important;
    white-space: normal !important;
    min-height: 0 !important;
    display: flex !important;
    align-items: center !important;
    align-self: center !important;
    height: auto !important;
    padding: 0 2px !important;
    box-sizing: border-box;
}

.lead-rp-opt-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
}

.lead-rp-sel-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
}

.lead-rp-sel-name {
    font-size: 13px;
    color: #1e293b;
}

.lead-rp-sel-meta {
    font-size: 11px;
    margin-top: 1px;
}

.lead-rp-opt .user-item-name {
    font-weight: 600;
    font-size: 14px;
    color: #0B0736;
    text-transform: capitalize;
}

.lead-rp-opt .user-position-badge {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
}

.lead-rp-opt .user-item-meta-line {
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    font-size: 11px;
    color: #64748b;
}

.lead-rp-opt .meta-value {
    font-weight: 500;
    color: #334155;
}

.lead-rp-opt .meta-divider {
    color: #cbd5e1;
}

:deep(.lead-search-rp-select .vs__dropdown-option--highlight .user-item-name),
:deep(.lead-search-rp-select .vs__dropdown-option--selected .user-item-name) {
    color: #fff !important;
}

:deep(.lead-search-rp-select .vs__dropdown-option--highlight .user-item-meta-line),
:deep(.lead-search-rp-select .vs__dropdown-option--selected .user-item-meta-line) {
    color: rgba(255, 255, 255, 0.92) !important;
}

:deep(.lead-search-rp-select .vs__dropdown-option--highlight .meta-value),
:deep(.lead-search-rp-select .vs__dropdown-option--selected .meta-value) {
    color: rgba(255, 255, 255, 0.95) !important;
}

:deep(.lead-search-rp-select .vs__dropdown-option--highlight .user-position-badge),
:deep(.lead-search-rp-select .vs__dropdown-option--selected .user-position-badge) {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.45) !important;
    color: #fff !important;
}

</style>
<style>
    /* Global: teleported date picker must sit above search dropdown (z-index 15000+) */
    .lead-search-date-backdrop.lr-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, 0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100002 !important;
        padding: 12px;
    }

    .lead-search-date-backdrop .lr-date-modal {
        position: relative;
        z-index: 1;
    }

    .modal-dialog {
        z-index: 1060 !important;
    }

    /* Field Settings modal: search modal is teleported with z-index 15000+, so the default
     * Bootstrap modal/backdrop (~1055) ends up behind. Boost both above the search modal. */
    #filter-field-settings-modal {
        z-index: 100005 !important;
    }
    #filter-field-settings-modal .modal-dialog {
        z-index: 100006 !important;
    }
    /* The backdrop Bootstrap injects right before the modal element. */
    .modal-backdrop:has(+ #filter-field-settings-modal),
    body > .modal-backdrop.show {
        z-index: 100004 !important;
    }
    .vs__dropdown-menu {
        z-index: 9999 !important;
        max-height:150px;
    }
    .vs__dropdown-option--highlight {
        background: #733E87 !important;
        color: #fff !important;
    }
    .vs__dropdown-option--selected {
        background: #733E87 !important;
        color: #fff !important;
    }
    .vs__dropdown-option{
        font-size: 14px !important;
    }
</style>