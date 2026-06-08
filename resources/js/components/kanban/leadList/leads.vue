<template>
    <div class="kanban-outer" :class="{ 'kanban-outer--mobile': kanbanIsMobile }">
        <!-- Mobile: pipeline filter (matches design — Current Stage) -->
        <div
            v-if="kanbanIsMobile"
            class="mobile-current-stage-bar"
            role="button"
            tabindex="0"
            @click="openMobileListFilterSheet"
            @keydown.enter.prevent="openMobileListFilterSheet"
        >
            <span class="mobile-current-stage-bar__icon" aria-hidden="true">
                <iconify-icon icon="lucide:git-branch" />
            </span>
            <div class="mobile-current-stage-bar__text">
                <span class="mobile-current-stage-bar__label">Current Stage</span>
                <span class="mobile-current-stage-bar__value">{{ mobileListFilterLabel }}</span>
            </div>
        </div>


        <LeadAnalyticsShortcuts
            :metrics="leadAnalyticsMetrics"
            :active-filter="activeShortcutFilter"
            @toggle-filter="onShortcutFilterToggle"
        />

        <div
            ref="kanbanContainerRef"
            class="kanban-container"
            @scroll="updateScrollArrows"
            @dragover.prevent="onContainerDragOver"
        >
        <!-- Error state -->
        <div v-if="error && columns.length === 0 && !loading" class="kanban-empty-state kanban-error-state">
            <iconify-icon icon="lucide:alert-circle" class="kanban-empty-icon"></iconify-icon>
            <p class="kanban-empty-title">Could not load stages</p>
            <p class="kanban-empty-text">{{ error }}</p>
            <button type="button" class="kanban-empty-btn" @click="fetchLeads(true)">Try again</button>
        </div>
        <!-- No stages yet -->
        <div v-else-if="!loading && columns.length === 0" class="kanban-empty-state">
            <iconify-icon icon="lucide:columns-3" class="kanban-empty-icon"></iconify-icon>
            <p class="kanban-empty-title">No stages yet</p>
            <p class="kanban-empty-text">Use the menu above to add a new stage and start organizing your leads.</p>
        </div>
        <!-- Draggable Columns -->
        <draggable v-else-if="columns.length > 0" v-model="columns" item-key="status" class="kanban-wrapper kanban-wrapper-tight d-flex h-100" :group="'columns'"
            handle=".column-header"
            :disabled="kanbanIsMobile"
            :ghost-class="'ghost'" :drag-class="'dragging'">
            <template #item="{ element: column }">
                <div
                    v-show="isColumnVisibleOnMobile(column)"
                    class="kanban-column radius-12 flex-column"
                    :style="{ '--column-color': column.color }"
                >
                    <div class="p-0 overflow-visible shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <!-- Column Header -->
                            <div
                                class="column-header d-flex align-items-center justify-content-between flex-shrink-0"
                                :class="{ 'column-header--mobile': kanbanIsMobile, 'cursor-move': !kanbanIsMobile }"
                                :style="{ backgroundColor: column.color }"
                            >
                                <div class="d-flex align-items-center gap-2 column-header__title-block">
                                    <span v-if="kanbanIsMobile" class="column-header__dot" aria-hidden="true" />
                                    <div v-if="editingStageId !== column.status" class="header-title-wrapper" @click="startEditingStage(column)">
                                        <p class="header-title">{{ column.title }}</p>
                                         <small class="leads-count-badge" v-if="column.leads.length>0 && stagePagination[column.status] && stagePagination[column.status].total > column.leads.length ">
                                             {{ stagePagination[column.status]?.total || column.leads.length }}
                                        </small>
                                        <small class="leads-count-badge" v-else >
                                            {{ column.leads.length }}
                                        </small>
                                    </div>
                                    <input 
                                        v-else
                                        v-model="editingStageTitle"
                                        @keyup.enter="saveStageName(column)"
                                        @keyup.esc="cancelEditingStage"
                                        @blur="saveStageName(column)"
                                        class="header-title-input"
                                        ref="stageTitleInput"
                                        type="text"
                                    />
                                </div>
                            </div>

                            <div
                                class="column-content column-content-scrollable p-8 flex-grow-1 d-flex flex-column"
                                @scroll="(e) => onColumnScroll(column, e)"
                            >
                                <!-- Tasks -->
                                <draggable
                                    v-model="column.leads"
                                    :group="'tasks'"
                                    item-key="id"
                                    class="tasks-list flex-grow-1"
                                    :ghost-class="'ghost'"
                                    :drag-class="'dragging'"
                                    :disabled="kanbanIsMobile"
                                    :force-fallback="kanbanIsMobile"
                                    :scroll="true"
                                    :bubble-scroll="true"
                                    :scroll-sensitivity="220"
                                    :scroll-speed="22"
                                    @start="onLeadDragStart"
                                    @end="onLeadDragEnd"
                                    @change="(evt) => onLeadDragChange(evt, column)"
                                >
                                    <template #item="{ element: task, index }">
                                            <div
                                                :key="task.id"
                                                class="kanban-card bg-white p-12 radius-12 mb-10 shadow-sm border-0 cursor-pointer"
                                                :class="{ 'kanban-card--mobile': kanbanIsMobile }"
                                                v-show="leadMatchesShortcutFilter(task) && (!kanbanIsMobile || mobileListFilterStageId !== MOBILE_FILTER_ALL || index === getMobileCardIndex(column))"
                                                @touchstart="onMobileCardTouchStart(column, $event)"
                                                @touchmove="onMobileCardTouchMove(column, $event)"
                                                @touchend="onMobileCardTouchEnd(column, $event)"
                                                @click="onLeadCardClick(task, column)"
                                            >
                                                <!-- Task Header - Lead Name + badges (single row) -->
                                                <div class="task-header d-flex align-items-center gap-2 mb-12 min-w-0">
                                                    <p class="task-title flex-grow-1 mb-0 min-w-0 text-truncate">{{ task.lead_name }}</p>
                                                    <div class="task-header-badges d-inline-flex align-items-center gap-1 flex-shrink-0">
                                                        <span
                                                            v-if="task.has_service_duplicate"
                                                            class="lead-blacklist-badge"
                                                            title="Blacklist"
                                                        >
                                                            Black List
                                                        </span>
                                                        <div
                                                            v-if="Number(task.duplicate_no) > 0"
                                                            class="duplicate-badge position-relative cursor-pointer"
                                                            @click.stop="openDuplicateLeadsModal(task.id, $event)"
                                                        >
                                                            <div class="duplicate-icon-wrapper">
                                                                <div class="duplicate-rectangle duplicate-rectangle-back"></div>
                                                                <div class="duplicate-rectangle duplicate-rectangle-front">
                                                                    <span class="duplicate-number">{{ task.duplicate_no || 0 }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                 <div class="task-info">
                                                        <template v-for="field in enabledFieldsForColumn(column, task)" :key="field.key">
                                                            <!-- Created at (timestamp) — API: created_at -->
                                                            <div
                                                                v-if="field.key === 'created_at'"
                                                                class="info-item date-info d-flex align-items-center gap-1 mb-8"
                                                            >
                                                                <span class="text-secondary-light text-xs">Created</span>
                                                                <span>{{ formatDate(task.created_at) }}</span>
                                                            </div>
                                                            <!-- Created by (person) — settings key created_by maps to added_by / added_by_user on lead -->
                                                            <div
                                                                v-else-if="field.key === 'created_by'"
                                                                class="info-item mb-8"
                                                            >
                                                                <div class="info-label text-secondary-light text-xs">Created By</div>
                                                                <div class="info-value">{{ getCreatedByDisplay(task) }}</div>
                                                            </div>
                                                            
                                                            <!-- First Name -->
                                                            <div v-else-if="field.key === 'first_name'" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Name</div>
                                                                <div class="info-value">{{ task.salutation }} {{ task.first_name }}</div>
                                                            </div>
                                                            
                                                            <!-- Source -->
                                                            <div v-else-if="field.key === 'lead_source' && task.lead_source" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs mb-1">Source</div>
                                                                <div class="info-value">{{ task.lead_source }}</div>
                                                            </div>
                                                            
                                                            <!-- Lead Branch Source -->
                                                            <div v-else-if="field.key === 'lead_branch_source' && task.lead_branch_source" class="info-item mb-12">
                                                                <div class="info-label text-secondary-light text-xs mb-1">Lead Branch Source</div>
                                                                <div class="info-value">{{ task.lead_branch_source }}</div>
                                                            </div>
                                                              
                                                            <!-- Work Phone -->
                                                            <div v-else-if="field.key === 'work_phone' && task?.work_phone" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Phone</div>
                                                                <div class="info-value">{{ task.work_phone.slice(0,8) + '....' }}</div>
                                                            </div>
                                                            
                                                            <!-- Email -->
                                                            <div v-else-if="field.key === 'email' && task.email" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Email</div>
                                                                <div class="info-value">{{ formatMaskedEmail(task.email) }}</div>
                                                            </div>
                                                            
                                                            <!-- Bedrooms -->
                                                                <div v-else-if="field.key === 'bedrooms' && shouldShowPriorityBedrooms(task)" class="info-item mb-8">
                                                                    <div class="info-label text-secondary-light text-xs">Bedrooms</div>
                                                                    <div class="info-value">{{ getPriorityBedrooms(task) }}</div>
                                                                </div>
                                                                
                                                                <!-- Property Type - من الـ priority requirement -->
                                                                <div v-else-if="field.key === 'property_type' && getPriorityPropertyType(task)" class="info-item mb-8">
                                                                    <div class="info-label text-secondary-light text-xs">Property Type</div>
                                                                    <div class="info-value">{{ getPriorityPropertyType(task) }}</div>
                                                                </div>
                                                                
                                                                <!-- Lead Type - من الـ priority requirement -->
                                                                <div v-else-if="field.key === 'lead_type' && getPriorityLeadType(task)" class="info-item mb-8">
                                                                    <div class="info-label text-secondary-light text-xs">Lead Type</div>
                                                                    <div class="info-value">{{ displayLeadType(getPriorityLeadType(task)) }}</div>
                                                                </div>
                                                                
                                                                <!-- Property Status - من الـ priority requirement -->
                                                                <div v-else-if="field.key === 'property_status' && getPriorityPropertyStatus(task)" class="info-item mb-8">
                                                                    <div class="info-label text-secondary-light text-xs">Property Status</div>
                                                                    <div class="info-value">{{ displayPropertyStatus(getPriorityPropertyStatus(task)) }}</div>
                                                                </div>
                                                                
                                                                <!-- Purpose Of Purchase - من الـ priority requirement -->
                                                                <div v-else-if="field.key === 'purpose_buying' && getPriorityPurposeBuying(task)" class="info-item mb-8">
                                                                    <div class="info-label text-secondary-light text-xs">Purpose</div>
                                                                    <div class="info-value">{{ getPriorityPurposeBuying(task) }}</div>
                                                                </div>
                                                                
                                                                <!-- Budget - من الـ priority requirement -->
                                                                <div v-else-if="field.key === 'budget' && getPriorityBudget(task)" class="info-item mb-8">
                                                                    <div class="info-label text-secondary-light text-xs">Budget</div>
                                                                    <div class="info-value">{{ getPriorityBudget(task) }} AED</div>
                                                                </div>
                                                            
                                                            <!-- WhatsApp -->
                                                            <div v-else-if="field.key === 'whatsapp_number' && task.whatsapp_number" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">WhatsApp</div>
                                                                <div class="info-value">{{ task.whatsapp_number }}</div>
                                                            </div>

                                                            <div v-else-if="field.key === 'api_first_question' && task.api_first_question" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">More Information</div>
                                                                <div class="info-value">{{formatMaskedQuestion(task.api_first_question)}}</div>
                                                            </div>

                                                            
                                                            
                                                            <!-- Responsible Person -->
                                                            <div v-else-if="field.key === 'responsible_person' && hasResponsiblePerson(task)" class="responsible-info d-flex align-items-center justify-content-between mb-12">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div
                                                                        class="person-hover-anchor"
                                                                        @mouseenter.stop="showPersonHoverCard(task, 'responsible')"
                                                                        @mouseleave.stop="hidePersonHoverCard"
                                                                        @click.stop="openPersonProfile(task, 'responsible', $event)"
                                                                    >
                                                                        <img
                                                                            v-if="responsiblePersonAvatar(task)"
                                                                            :src="responsiblePersonAvatar(task)"
                                                                            alt=""
                                                                            class="avatar-sm rounded-circle"
                                                                        />
                                                                        <transition name="person-hover-pop">
                                                                            <div
                                                                                v-if="isPersonHoverVisible(task, 'responsible') && activePersonHover?.data"
                                                                                class="person-hover-card"
                                                                                @mouseenter.stop="cancelPersonHoverHide"
                                                                                @mouseleave.stop="hidePersonHoverCard"
                                                                                  @click.stop="openPersonProfile(task, 'responsible', $event)"
                                                                            >
                                                                                <div class="person-hover-head">
                                                                                    <img
                                                                                        :src="hoverCardPersonAvatar(activePersonHover.data)"
                                                                                        alt=""
                                                                                        class="person-hover-avatar"
                                                                                    />
                                                                                    <div class="person-hover-head-text">
                                                                                        <div class="person-hover-name">{{ activePersonHover.data.name }}</div>
                                                                                        <div class="person-hover-role">{{ activePersonHover.data.position }}</div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="person-hover-line"><span>Reports To</span><b>{{ activePersonHover.data.manager }}</b></div>
                                                                                <div class="person-hover-line"><span>Branch</span><b>{{ activePersonHover.data.branch }}</b></div>
                                                                            </div>
                                                                        </transition>
                                                                    </div>
                                                                    <div>
                                                                        <div class="info-value" @mouseenter.stop="showPersonHoverCard(task, 'responsible')"
                                                                        @mouseleave.stop="hidePersonHoverCard"   @click.stop="openPersonProfile(task, 'responsible', $event)">{{ task.responsible_person?.name }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Activity: date + avatar of Bitrix24 LAST_ACTIVITY_BY user only -->
                                                            <div v-else-if="field.key === 'assigned_by' && hasAssignedBy(task)">
                                                                <hr class="mb-2 border-neutral-200">
                                                                <div class="mt-1 d-flex align-items-center justify-content-between assignedBy">
                                                                    <div class="info-item">
                                                                        <div class="info-label text-secondary-light text-xs mb-1">Activity</div>
                                                                        <div class="info-value">{{ formatActivityDate(task) }}</div>
                                                                    </div>
                                                                    <div
                                                                        v-if="activityPerson(task)"
                                                                        class="person-hover-anchor person-hover-clickable"
                                                                        :title="activityPerson(task)?.name || ''"
                                                                        @mouseenter.stop="showPersonHoverCard(task, 'activity')"
                                                                        @mouseleave.stop="hidePersonHoverCard"
                                                                        @click.stop="openPersonProfile(task, 'activity', $event)"
                                                                    >
                                                                        <img
                                                                            :src="activityPersonAvatar(task)"
                                                                            :alt="activityPerson(task)?.name || ''"
                                                                            class="avatar-sm rounded-circle"
                                                                            @click.stop="openPersonProfile(task, 'activity', $event)"
                                                                        />
                                                                        <transition name="person-hover-pop">
                                                                            <div
                                                                                v-if="isPersonHoverVisible(task, 'activity') && activePersonHover?.data"
                                                                                class="person-hover-card person-hover-card-right"
                                                                                @mouseenter.stop="cancelPersonHoverHide"
                                                                                @mouseleave.stop="hidePersonHoverCard"
                                                                                 @click.stop="openPersonProfile(task, 'activity', $event)"
                                                                            >
                                                                                <div class="person-hover-head">
                                                                                    <img
                                                                                        :src="hoverCardPersonAvatar(activePersonHover.data)"
                                                                                        alt=""
                                                                                        class="person-hover-avatar"
                                                                                    />
                                                                                    <div class="person-hover-head-text">
                                                                                        <div class="person-hover-name">{{ activePersonHover.data.name }}</div>
                                                                                        <div class="person-hover-role">{{ activePersonHover.data.position }}</div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="person-hover-line"><span>Reports To</span><b>{{ activePersonHover.data.manager }}</b></div>
                                                                                <div class="person-hover-line"><span>Branch</span><b>{{ activePersonHover.data.branch }}</b></div>
                                                                            </div>
                                                                        </transition>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div v-else-if="hasDynamicFieldValue(task, field.key)" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">{{ field.label || field.key }}</div>
                                                                <div class="info-value">{{ getDynamicFieldDisplay(task, field.key) }}</div>
                                                            </div>
                                                          
                                                        </template>
                                                    </div>
                                            </div>
                                        </template>
                                </draggable>
                                <div
                                    v-if="kanbanIsMobile && mobileListFilterStageId === MOBILE_FILTER_ALL && column.leads.length > 1"
                                    class="mobile-stage-carousel-controls"
                                >
                                    <div class="mobile-stage-carousel-dots">
                                        <button
                                            v-for="(leadItem, dotIndex) in column.leads"
                                            :key="`dot-${column.status}-${leadItem.id}`"
                                            type="button"
                                            class="mobile-stage-carousel-dot"
                                            :class="{ 'is-active': dotIndex === getMobileCardIndex(column) }"
                                            :aria-label="`Go to card ${dotIndex + 1}`"
                                            @click.stop="setMobileCardIndex(column, dotIndex)"
                                        />
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>
        </div>
        <template v-if="!loading && !error && columns.length > 0">
            <!-- Left: hide when at start -->
            <div
                v-show="showLeftZone"
                class="kanban-nav-zone kanban-nav-zone-left"
                title="Move left"
                aria-label="Move left"
                @mouseenter="startScrollLeft"
                @mouseleave="stopScroll"
            >
                <span class="kanban-nav-arrow kanban-nav-arrow-left">
                    <iconify-icon icon="lucide:chevron-left" class="kanban-nav-arrow-icon" />
                </span>
            </div>
            <!-- Right: hide when at end -->
            <div
                v-show="showRightZone"
                class="kanban-nav-zone kanban-nav-zone-right"
                title="Move the stages"
                aria-label="Move the stages"
                @mouseenter="startScrollRight"
                @mouseleave="stopScroll"
            >
                <span class="kanban-nav-arrow kanban-nav-arrow-right">
                    <iconify-icon icon="lucide:chevron-right" class="kanban-nav-arrow-icon" />
                </span>
            </div>
        </template>
    </div>

    <!-- Mobile: stage change + pick stage (bottom sheets) -->
    <Teleport to="body">
        <div
            v-if="kanbanIsMobile && showMobileQuickSheet"
            class="mobile-kanban-overlay mobile-kanban-overlay--quick"
            @click.self="closeMobileQuickSheet"
        >
            <div class="mobile-kanban-sheet mobile-kanban-sheet--quick" @click.stop>
                <button type="button" class="mobile-kanban-sheet__close" aria-label="Close" @click="closeMobileQuickSheet">
                    <iconify-icon icon="lucide:x" />
                </button>
                <p class="mobile-kanban-sheet__hint">Quick Actions</p>
                <div class="mobile-kanban-stage-pair">
                    <span
                        class="mobile-kanban-pill mobile-kanban-pill--from"
                        :style="{ backgroundColor: mobileQuickSourceColumn?.color || '#4dbdc2' }"
                    >
                        {{ mobileQuickSourceColumn?.title || '—' }}
                    </span>
                    <span class="mobile-kanban-stage-pair__sep" aria-hidden="true">
                        <iconify-icon icon="lucide:chevrons-right" />
                    </span>
                    <button
                        type="button"
                        class="mobile-kanban-pill mobile-kanban-pill--pick"
                        @click="openMobilePickStageFromQuick"
                    >
                        <iconify-icon icon="lucide:plus" class="me-1" />
                        Select Stage
                    </button>
                </div>
                <div class="mobile-kanban-quick-actions">
                    <button
                        type="button"
                        class="mobile-kanban-quick-btn mobile-kanban-quick-btn--assign"
                        @click="openMobilePickStageFromQuick"
                    >
                        Assign
                    </button>
                    <button
                        type="button"
                        class="mobile-kanban-quick-btn mobile-kanban-quick-btn--view"
                        @click="openViewLeadFromMobileSheet"
                    >
                        View
                    </button>
                    <button
                        type="button"
                        class="mobile-kanban-quick-btn mobile-kanban-quick-btn--stage"
                        @click="openMobilePickStageFromQuick"
                    >
                        Select Stage
                    </button>
                    <button
                        type="button"
                        class="mobile-kanban-quick-btn mobile-kanban-quick-btn--cancel"
                        @click="closeMobileQuickSheet"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="kanbanIsMobile && showMobilePickStageSheet"
            class="mobile-kanban-overlay mobile-kanban-overlay--tall"
            @click.self="closeMobilePickStageSheet"
        >
            <div class="mobile-kanban-sheet mobile-kanban-sheet--pick" @click.stop>
                <div class="mobile-kanban-sheet__head">
                    <h6 class="ui-h-section mobile-kanban-sheet__title">Select Stage</h6>
                    <button type="button" class="mobile-kanban-sheet__close" aria-label="Close" @click="closeMobilePickStageSheet">
                        <iconify-icon icon="lucide:x" />
                    </button>
                </div>
                <div class="mobile-kanban-stage-list">
                    <label
                        v-for="col in columns"
                        :key="'pick-' + col.status"
                        class="mobile-kanban-stage-row"
                    >
                        <input
                            v-model="mobilePickStageId"
                            type="radio"
                            class="mobile-kanban-stage-row__radio"
                            :value="col.status"
                        />
                        <span
                            class="mobile-kanban-stage-row__pill"
                            :style="{ backgroundColor: col.color }"
                        >
                            <span class="mobile-kanban-stage-row__pill-dot" aria-hidden="true" />
                            <span class="mobile-kanban-stage-row__pill-text">{{ col.title }} ({{ stageCountForColumn(col) }})</span>
                        </span>
                    </label>
                </div>
                <div v-if="mobileQuickSourceColumn && mobilePickStageColumn" class="mobile-kanban-preview-box">
                    <p class="mobile-kanban-preview-box__label">Stage change to</p>
                    <div class="mobile-kanban-stage-pair mobile-kanban-stage-pair--compact">
                        <span
                            class="mobile-kanban-pill mobile-kanban-pill--from"
                            :style="{ backgroundColor: mobileQuickSourceColumn.color }"
                        >
                            {{ mobileQuickSourceColumn.title }}
                        </span>
                        <span class="mobile-kanban-stage-pair__sep" aria-hidden="true">
                            <iconify-icon icon="lucide:chevrons-right" />
                        </span>
                        <span
                            class="mobile-kanban-pill mobile-kanban-pill--from"
                            :style="{ backgroundColor: mobilePickStageColumn.color }"
                        >
                            {{ mobilePickStageColumn.title }}
                        </span>
                    </div>
                </div>
                <div class="mobile-kanban-sheet__footer">
                    <button type="button" class="mobile-kanban-btn mobile-kanban-btn--muted" @click="closeMobilePickStageSheet">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="mobile-kanban-btn mobile-kanban-btn--dark"
                        :disabled="!mobilePickStageId || mobilePickStageId === mobileQuickLead?.stage_id"
                        @click="confirmMobilePickStage"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="kanbanIsMobile && showMobileListFilterSheet"
            class="mobile-kanban-overlay mobile-kanban-overlay--tall"
            @click.self="closeMobileListFilterSheet"
        >
            <div class="mobile-kanban-sheet mobile-kanban-sheet--pick" @click.stop>
                <div class="mobile-kanban-sheet__head">
                    <h6 class="mobile-kanban-sheet__title">Current Stage</h6>
                    <button type="button" class="mobile-kanban-sheet__close" aria-label="Close" @click="closeMobileListFilterSheet">
                        <iconify-icon icon="lucide:x" />
                    </button>
                </div>
                <div class="mobile-kanban-stage-list">
                    <label class="mobile-kanban-stage-row">
                        <input
                            v-model="mobileListFilterStageId"
                            name="mobile-stage-filter"
                            type="radio"
                            class="mobile-kanban-stage-row__radio"
                            :value="MOBILE_FILTER_ALL"
                            @change="selectMobileListFilter(MOBILE_FILTER_ALL)"
                        />
                        <span class="mobile-kanban-stage-row__pill mobile-kanban-stage-row__pill--all">
                            <span class="mobile-kanban-stage-row__pill-dot" aria-hidden="true" />
                            <span class="mobile-kanban-stage-row__pill-text">All Stages ({{ totalLeadsCount }})</span>
                        </span>
                    </label>
                    <label
                        v-for="col in columns"
                        :key="'filter-' + col.status"
                        class="mobile-kanban-stage-row"
                    >
                        <input
                            v-model="mobileListFilterStageId"
                            name="mobile-stage-filter"
                            type="radio"
                            class="mobile-kanban-stage-row__radio"
                            :value="String(col.status)"
                            @change="selectMobileListFilter(col.status)"
                        />
                        <span
                            class="mobile-kanban-stage-row__pill"
                            :style="{ backgroundColor: col.color }"
                        >
                            <span class="mobile-kanban-stage-row__pill-dot" aria-hidden="true" />
                            <span class="mobile-kanban-stage-row__pill-text">{{ col.title }} ({{ stageCountForColumn(col) }})</span>
                        </span>
                    </label>
                </div>
                <div class="mobile-kanban-sheet__footer">
                    <button type="button" class="mobile-kanban-btn mobile-kanban-btn--dark w-100" @click="closeMobileListFilterSheet">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
   <ProfilePopup
        v-if="showProfilePopup && profileUserId"
        v-model="showProfilePopup"
        :user-id="profileUserId"
        @update:model-value="onProfilePopupUpdate"
    />
    <!-- View Lead Modal -->
    <ViewLeadModal
        v-model="showViewModal"
        :leadId="selectedLead"
        @lead-updated="handleLeadUpdatedFromModal"
    />

    <!-- Duplicate Leads Dropdown -->
    <DuplicateLeadsModal 
        v-model="showDuplicateModal" 
        :leadId="selectedLeadForDuplicates"
        :triggerElement="currentTriggerElement"
        @view-lead="handleViewDuplicateLead"
    />
 <StageChangeReasonModal
        ref="stageChangeReasonModal"
        v-model="showStageChangeModal"
        :leadId="pendingStageChange?.leadId"
        :targetStageId="pendingStageChange?.targetStageId"
        :targetStageName="pendingStageChange?.targetStageName"
        :targetStageOrder="pendingStageChange?.targetStageOrder"
        :missingFields="missingFieldsForLead"
        :leadData="pendingStageChange?.leadData"
        :isConversion="pendingStageChange?.isConversion || false"
        :interactionMode="pendingStageChange?.interactionMode || false"
        @submit="handleStageChangeWithReason"
        @closed="clearPendingStageChange"
    />
    <ConvertLeadModal
        ref="convertModalRef"
        :leadId="selectedLeadForConversion || selectedLeadData?.id || selectedLeadData?.lead_id || null"
        :leadData="selectedLeadData"
        @converted="handleLeadConverted"
        @closed="selectedLeadForConversion = null"
    />
    <!-- Add/Edit Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title text-xl mb-0" id="addTaskModalLabel">
                        {{ isEditing ? 'Edit Task' : 'Add New Task' }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="taskForleadsm">
                        <input type="hidden" id="editTaskId" v-model="currentTask.id">
                        <div class="mb-3">
                            <label for="taskTitle"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Title</label>
                            <input type="text" class="form-control" v-model="currentTask.title"
                                placeholder="Enter Event Title" id="taskTitle" required>
                        </div>
                        <div class="mb-3">
                            <label for="taskName"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Name</label>
                            <input type="text" class="form-control" v-model="currentTask.name"
                                placeholder="Enter Name" id="taskName">
                        </div>
                        <div class="mb-3">
                            <label for="taskSource"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Source</label>
                            <input type="text" class="form-control" v-model="currentTask.source"
                                placeholder="Enter Source" id="taskSource">
                        </div>
                        <div class="mb-3">
                            <label for="taskBranch"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Branch Source</label>
                            <input type="text" class="form-control" v-model="currentTask.branchSource"
                                placeholder="Enter Branch Source" id="taskBranch">
                        </div>
                        <div class="mb-3">
                            <label for="taskDescription"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Description</label>
                            <textarea class="form-control" v-model="currentTask.description" id="taskDescription"
                                rows="3" placeholder="Write some text"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center gap-3">
                    <button type="button"
                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8"
                        data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary border border-primary-600 text-md px-28 py-12 radius-8"
                        @click="saveTask">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showStageModal" class="stage-modal-overlay">
    <div class="stage-modal">
            <h6 class="mb-3">
                {{ isEditingStage ? 'Edit Stage' : 'Create Stage' }}
            </h6>
    
             <!-- Stage Tittle -->
            <div class="form-group">
                <label class="form-label">Stage Title</label>
                <input
                    type="text"
                    v-model="stageForm.name"
                    class="form-control"
                />
                
            </div>
            <div class="form-group">
                <label class="form-label">Stage Color</label>
            
                <div class="color-field-wrapper">

                                <!-- hex input -->
                   
                    <input
                    placeholder="#000000"
                        type="color"
                        v-model="stageForm.color"
                        class="form-control"
                    />

                    <input
                        ref="colorInput"
                        type="color"
                        v-model="stageForm.color"
                        class="hidden-color-input"
                    />

                </div>
            
                
            </div>
    
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button class="btn btn-light" @click="closeStageModal">
                    Cancel
                </button>
                <button class="btn btn-primary" @click="saveStage">
                    Save
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick, inject } from 'vue'
import draggable from 'vuedraggable'
import avatar1 from '@/assets/images/users/user1.png'
import leadsIcon from '@/assets/images/kanban/leads-icon.png'
import avatar2 from '@/assets/images/users/user2.png'
import ViewLeadModal from '../viewLead/ViewLeadModal.vue'
import DuplicateLeadsModal from './DuplicateLeadsModal.vue'
import StageChangeReasonModal from './StageChangeReasonModal.vue'
import ConvertLeadModal from './ConvertLeadModal.vue'
import ProfilePopup from '../shared/ProfilePopup.vue'
import LeadAnalyticsShortcuts from './LeadAnalyticsShortcuts.vue'


import api from '@/plugins/axios'
import { markKanbanReady } from '@/composables/useKanbanReady.js'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl.js'
import { formatLeadBudgetRange } from '@/utils/budgetInput'
import Swal from 'sweetalert2'

// Import Bootstrap
import * as bootstrap from 'bootstrap'
const emit = defineEmits(['deal-created'])

const leadPoolRef = ref(null)


const showStageChangeModal = ref(false)

const showConvertModal = ref(false)
const selectedLeadForConversion = ref(null)
const selectedLeadData = ref(null)
const convertModalRef = ref(null)
const CONVERTED_STAGE_ID = 8

const stageOrderMap = ref({})




const showProfilePopup = ref(false)
const profileUserId = ref(null)
const profileTriggerType = ref(null)


const isActivityPersonType = (type) => type === 'activity' || type === 'assigned'

const activityUserIdByNameCache = ref({})

const normalizePersonNameKey = (name) => String(name || '').trim().toLowerCase()

const resolveKanbanProfileUserId = (task, type) => {
    if (!task) return null
    const toId = (raw) => {
        if (raw == null || raw === '') return null
        const n = Number(raw)
        return Number.isFinite(n) && n > 0 ? n : null
    }
    if (isActivityPersonType(type)) {
        const person = task?.last_activity_user
        const directId = toId(person?.id)
        if (directId) return directId
        const nameKey = normalizePersonNameKey(person?.name)
        if (nameKey && activityUserIdByNameCache.value[nameKey]) {
            return activityUserIdByNameCache.value[nameKey]
        }
        return null
    }
    return toId(task?.responsible_person?.id) ?? toId(task?.responsible_person_id) ?? null
}

const lookupActivityUserIdByName = async (name) => {
    const key = normalizePersonNameKey(name)
    if (!key) return null
    if (activityUserIdByNameCache.value[key]) {
        return activityUserIdByNameCache.value[key]
    }
    try {
        const response = await api.get('/users', { params: { search: String(name).trim() } })
        const raw = response.data?.data
        const users = Array.isArray(raw) ? raw : []
        const exact = users.find((u) => normalizePersonNameKey(u?.name) === key)
        const match = exact || users[0]
        const id = match?.id != null ? Number(match.id) : null
        if (id && Number.isFinite(id) && id > 0) {
            activityUserIdByNameCache.value = { ...activityUserIdByNameCache.value, [key]: id }
            return id
        }
    } catch (error) {
        console.error('Failed to resolve activity user by name:', error)
    }
    return null
}

const openPersonProfile = async (task, type, event) => {
    if (event) event.stopPropagation()
    hidePersonHoverCard()
    activePersonHover.value = null

    let userId = resolveKanbanProfileUserId(task, type)
    
    // إذا كان المستخدم من Bitrix24 فقط (id = null) لا نفتح الملف الشخصي
    if (isActivityPersonType(type) && userId === null) {
        console.log('External Bitrix24 user - no profile to open')
        // يمكن إظهار رسالة للمستخدم
        $showNotification('This user is from external system and has no profile', 'info')
        return
    }
    
    if (!userId && isActivityPersonType(type)) {
        userId = await lookupActivityUserIdByName(activityPerson(task)?.name)
    }
    
    if (!userId) {
        $showNotification('User profile not available', 'warning')
        return
    }

    profileUserId.value = userId
    profileTriggerType.value = type
    showProfilePopup.value = true
}

const onProfilePopupUpdate = (open) => {
    showProfilePopup.value = !!open
    if (!open) {
        profileUserId.value = null
        profileTriggerType.value = null
    }
}

/** Bitrix24 LAST_ACTIVITY_BY user for the Activity tile (never the assignee/parent). */
const activityPerson = (task) => task?.last_activity_user ?? null

/** Cached CRM avatars loaded after hover/API (user id → avatar URL). */
const activityAvatarCache = ref({})

const KANBAN_DEFAULT_AVATAR_PATH = 'users/user.png'

const resolveKanbanAvatarUrl = (raw) => {
    if (raw == null || raw === '') {
        return normalizePublicStorageUrl(KANBAN_DEFAULT_AVATAR_PATH) || ''
    }
    const trimmed = String(raw).trim()
    if (/^https?:\/\//i.test(trimmed)) {
        return normalizePublicStorageUrl(trimmed) || trimmed
    }
    return normalizePublicStorageUrl(trimmed) || ''
}

/** Always returns a profile image URL (real photo or default users/user.png). */
const kanbanCardPersonAvatar = (person) => {
    if (!person) return ''
    const userId = person.id != null ? Number(person.id) : null
    if (userId && activityAvatarCache.value[userId]) {
        return activityAvatarCache.value[userId]
    }
    const raw = person.avatar || person.photo_url || ''
    if (typeof raw === 'string' && raw.trim() !== '') {
        const url = resolveKanbanAvatarUrl(raw)
        if (url) return url
    }
    return resolveKanbanAvatarUrl(KANBAN_DEFAULT_AVATAR_PATH)
}

const responsiblePersonAvatar = (task) => {
    if (!task?.responsible_person?.name && !task?.responsible_person?.avatar) return ''
    return kanbanCardPersonAvatar(task.responsible_person)
}

const seedActivityAvatarCacheFromColumns = (cols) => {
    const next = { ...activityAvatarCache.value }
    const nameIds = { ...activityUserIdByNameCache.value }
    for (const col of cols || []) {
        for (const lead of col.leads || []) {
            const person = lead?.last_activity_user
            if (!person) continue
            const id = Number(person.id)
            if (Number.isFinite(id) && id > 0) {
                if (person.avatar) {
                    const url = resolveKanbanAvatarUrl(person.avatar)
                    if (url) next[id] = url
                }
                const nameKey = normalizePersonNameKey(person.name)
                if (nameKey) nameIds[nameKey] = id
            }
        }
    }
    activityAvatarCache.value = next
    activityUserIdByNameCache.value = nameIds
}

const activityPersonAvatar = (task) => kanbanCardPersonAvatar(activityPerson(task))

const hoverCardPersonAvatar = (hoverData) => {
    if (!hoverData) return resolveKanbanAvatarUrl(KANBAN_DEFAULT_AVATAR_PATH)
    return kanbanCardPersonAvatar({
        id: hoverData.id,
        avatar: hoverData.avatar,
        photo_url: hoverData.photo_url,
        name: hoverData.name,
    })
}

const closeProfilePopup = () => {
    onProfilePopupUpdate(false)
}

// Get user from storage (same pattern as header/index.vue)
const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}

const user = ref(getUserFromStorage())

const kanbanIsMobile = inject('kanbanIsMobile', ref(false))
const kanbanOpenCreateLead = inject('kanbanOpenCreateLead', null)

/** Mobile list filter: show all stacked stages or focus one column */
const MOBILE_FILTER_ALL = 'all'
const mobileListFilterStageId = ref(MOBILE_FILTER_ALL)
const showMobileQuickSheet = ref(false)
const showMobilePickStageSheet = ref(false)
const showMobileListFilterSheet = ref(false)
const mobileQuickLead = ref(null)
const mobileQuickSourceColumn = ref(null)
const mobilePickStageId = ref(null)
const mobileStageCardIndex = ref({})
const mobileTouchStartX = ref({})
const mobileTouchStartY = ref({})
const mobileTouchLastX = ref({})
const mobileTouchLastY = ref({})
const mobileSwipeMoved = ref({})

// Applied search params (from search modal, not from URL)
const appliedSearchParams = ref(null)

// Check if user is admin or super_admin (same pattern as header/index.vue)
const isAdminOrSuperAdmin = computed(() => {
    if (!user.value) return false
    
    const isAdminUser = user.value.roles?.includes('super_admin') || 
                       user.value.roles?.includes('admin')
    
    return isAdminUser
})

const columns = ref([])

function stageCountForColumn(col) {
    if (stagePagination.value[col.status]?.total != null) {
        return stagePagination.value[col.status].total
    }
    return col.leads?.length ?? 0
}

const totalLeadsCount = computed(() => {
    let sum = 0
    for (const col of columns.value) {
        const t = stagePagination.value[col.status]?.total
        sum += typeof t === 'number' ? t : (col.leads?.length || 0)
    }
    return sum
})

const activeShortcutFilter = ref(null)

function normalizeLeadHeat(lead) {
    const status = String(lead?.status_lead || '').toLowerCase()
    const priority = String(lead?.priority || '').toLowerCase()
    if (status.includes('hot') || priority.includes('hot')) return 'hot'
    if (status.includes('warm') || priority.includes('warm')) return 'warm'
    if (status.includes('cold') || priority === 'cold') return 'cold'
    return null
}

function normalizeLeadInteraction(lead) {
    const r = String(lead?.interaction_result || '').toLowerCase()
    if (r === 'answered') return 'answered'
    if (r === 'no_answer') return 'no_answer'
    return null
}

// Populated from the backend `analytics` field on every /stages-with-leads response.
// `countLoadedLeads` would only see the first 20 leads per stage that are currently
// loaded — the chip totals must reflect the whole filtered set, not the visible page.
const leadAnalyticsServer = ref({
    tempCold: 0,
    tempWarm: 0,
    tempHot: 0,
    callAnswered: 0,
    callNoAnswer: 0,
})

const leadAnalyticsMetrics = computed(() => ({ ...leadAnalyticsServer.value }))

function leadMatchesShortcutFilter(lead) {
    if (!activeShortcutFilter.value) return true

    switch (activeShortcutFilter.value) {
        case 'temp_cold':
            return normalizeLeadHeat(lead) === 'cold'
        case 'temp_warm':
            return normalizeLeadHeat(lead) === 'warm'
        case 'temp_hot':
            return normalizeLeadHeat(lead) === 'hot'
        case 'call_answered':
            return normalizeLeadInteraction(lead) === 'answered'
        case 'call_no_answer':
            return normalizeLeadInteraction(lead) === 'no_answer'
        default:
            return true
    }
}

// Maps a shortcut chip key to the API filter params the backend understands,
// so the kanban refetches with the filter applied (instead of only hiding loaded cards).
function shortcutFilterApiParams(filterKey) {
    switch (filterKey) {
        // `heat` is a dedicated server-side filter that OR-matches status_lead/priority,
        // mirroring `normalizeLeadHeat` so the chip count and the filtered list agree.
        case 'temp_cold': return { heat: 'cold' }
        case 'temp_warm': return { heat: 'warm' }
        case 'temp_hot': return { heat: 'hot' }
        case 'call_answered': return { interaction_result: 'answered' }
        case 'call_no_answer': return { interaction_result: 'no_answer' }
        default: return {}
    }
}

// Merge of the search-modal query and the active shortcut chip — used by every
// /stages/kanban request so column counts and pagination reflect the filter.
const effectiveSearchParams = computed(() => ({
    ...(appliedSearchParams.value || {}),
    ...shortcutFilterApiParams(activeShortcutFilter.value),
}))

function onShortcutFilterToggle(filterKey) {
    const next = filterKey || null
    if (activeShortcutFilter.value === next) return
    activeShortcutFilter.value = next
    fetchLeads(true)
}

watch(appliedSearchParams, () => {
    activeShortcutFilter.value = null
})

const mobileListFilterLabel = computed(() => {
    if (mobileListFilterStageId.value === MOBILE_FILTER_ALL) {
        return `All Stages (${totalLeadsCount.value})`
    }
    const col = columns.value.find(c => String(c.status) === String(mobileListFilterStageId.value))
    return col ? `${col.title} (${stageCountForColumn(col)})` : 'All Stages'
})

const mobilePickStageColumn = computed(() => {
    if (mobilePickStageId.value == null) return null
    return columns.value.find(c => c.status === mobilePickStageId.value) || null
})

const INITIAL_VISIBLE_LEADS_PER_STAGE = 20
const VISIBLE_LEADS_INCREMENT = 20
const visibleLeadCounts = ref({})
const KANBAN_LEADS_CACHE_KEY = 'kanban_leads_stages_cache_v1'
const KANBAN_LEADS_CACHE_TTL_MS =30000
const responsiblePersons = ref([])
const loading = ref(true)
const error = ref(null)
const kanbanContainerRef = ref(null)
const scrollInterval = ref(null)
const showLeftZone = ref(true)
const showRightZone = ref(true)
const stagePagination = ref({})          
const loadingMoreLeads = ref({})          
const leadsPerPage = ref(20)              
const SCROLL_SPEED = 10
const SCROLL_TICK_MS = 16
const isLeadDragging = ref(false)
const dragPointerX = ref(null)
let dragAutoScrollRaf = null
const DRAG_SCROLL_EDGE_THRESHOLD = 220
const DRAG_SCROLL_MAX_SPEED = 10

function updateScrollArrows() {
    const el = kanbanContainerRef.value
    if (!el) return
    const atStart = el.scrollLeft <= 2
    const atEnd = el.scrollWidth - el.clientWidth <= el.scrollLeft + 2
    showLeftZone.value = !atStart
    showRightZone.value = !atEnd
}

function startScrollLeft() {
    stopScroll()
    scrollInterval.value = setInterval(() => {
        const el = kanbanContainerRef.value
        if (!el) return
        el.scrollLeft -= SCROLL_SPEED
    }, SCROLL_TICK_MS)
}

function startScrollRight() {
    stopScroll()
    scrollInterval.value = setInterval(() => {
        const el = kanbanContainerRef.value
        if (!el) return
        el.scrollLeft += SCROLL_SPEED
    }, SCROLL_TICK_MS)
}

function stopScroll() {
    if (scrollInterval.value) {
        clearInterval(scrollInterval.value)
        scrollInterval.value = null
    }
}

function onGlobalPointerMove(event) {
    const x = event?.touches?.[0]?.clientX ?? event?.clientX
    if (typeof x === 'number') {
        dragPointerX.value = x
    }
}

function onContainerDragOver(event) {
    if (!isLeadDragging.value) return
    const x = event?.clientX
    if (typeof x === 'number') {
        dragPointerX.value = x
    }
}

function onGlobalDragOver(event) {
    if (!isLeadDragging.value) return
    const x = event?.clientX
    if (typeof x === 'number') {
        dragPointerX.value = x
    }
}

function stepDragAutoScroll() {
    if (!isLeadDragging.value) {
        dragAutoScrollRaf = null
        return
    }

    const container = kanbanContainerRef.value
    if (container && typeof dragPointerX.value === 'number') {
        const rect = container.getBoundingClientRect()
        const threshold = DRAG_SCROLL_EDGE_THRESHOLD
        const maxSpeed = DRAG_SCROLL_MAX_SPEED
        let delta = 0

        if (dragPointerX.value < rect.left + threshold) {
            const ratio = Math.min(1, (rect.left + threshold - dragPointerX.value) / threshold)
            delta = -Math.ceil(maxSpeed * ratio)
        } else if (dragPointerX.value > rect.right - threshold) {
            const ratio = Math.min(1, (dragPointerX.value - (rect.right - threshold)) / threshold)
            delta = Math.ceil(maxSpeed * ratio)
        }

        if (delta !== 0) {
            container.scrollLeft += delta
            updateScrollArrows()
        }
    }

    dragAutoScrollRaf = requestAnimationFrame(stepDragAutoScroll)
}

function onLeadDragStart(event) {
    isLeadDragging.value = true
    onGlobalPointerMove(event?.originalEvent || event)

    document.addEventListener('pointermove', onGlobalPointerMove, { passive: true })
    document.addEventListener('mousemove', onGlobalPointerMove, { passive: true })
    document.addEventListener('touchmove', onGlobalPointerMove, { passive: true })
    document.addEventListener('dragover', onGlobalDragOver)

    if (!dragAutoScrollRaf) {
        dragAutoScrollRaf = requestAnimationFrame(stepDragAutoScroll)
    }
}

function onLeadDragEnd() {
    isLeadDragging.value = false
    dragPointerX.value = null
    stopScroll()

    document.removeEventListener('pointermove', onGlobalPointerMove)
    document.removeEventListener('mousemove', onGlobalPointerMove)
    document.removeEventListener('touchmove', onGlobalPointerMove)
    document.removeEventListener('dragover', onGlobalDragOver)

    if (dragAutoScrollRaf) {
        cancelAnimationFrame(dragAutoScrollRaf)
        dragAutoScrollRaf = null
    }
}

const echoListeners = ref([])
/** Debounce rapid assignment broadcasts → single board refresh */
const leadAssignmentBoardRefreshTimer = ref(null)
const pollingInterval = ref(null)
const isFetching = ref(false)
const abortController = ref(null)
const fetchDebounceTimer = ref(null)

// Stage editing state
const editingStageId = ref(null)
const editingStageTitle = ref('')
const stageTitleInput = ref(null)

const cardFields = ref([])
const allFields = ref([])
const KANBAN_CARD_BEHAVIOR_STORAGE_KEY = 'kanban_card_behavior_settings_v1'
const cardBehavior = ref({
    showMoreFromQualified: true,
    qualifiedStartOrder: 4,
    qualifiedFieldKeys: [],
})


const stageChangeReasonModal = ref(null)
const pendingStageChange = ref(null)


const showStageModal = ref(false)
const isEditingStage = ref(false)

const stageForm = ref({
    id: null,
    name: '',
    color: null
})

const colorInput = ref(null)

const openColorPicker = () => {
    colorInput.value?.click()
}


const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

function getColorByIndex(index) {
    return colors[index % colors.length]
}
const fetchCardSettings = async () => {
    try {
        const response = await api.get('/settings/kanban')
        const data = response.data.data
        cardFields.value = data.card_fields || []
        allFields.value = data.all_fields || []
        const apiBehavior = data.card_behavior || {}
        const localBehaviorRaw = localStorage.getItem(KANBAN_CARD_BEHAVIOR_STORAGE_KEY)
        let localBehavior = {}
        try {
            localBehavior = localBehaviorRaw ? JSON.parse(localBehaviorRaw) : {}
        } catch {
            localBehavior = {}
        }
        cardBehavior.value = {
            ...cardBehavior.value,
            ...apiBehavior,
            ...localBehavior,
        }
        if (!Number.isFinite(Number(cardBehavior.value.qualifiedStartOrder)) || Number(cardBehavior.value.qualifiedStartOrder) < 1) {
            cardBehavior.value.qualifiedStartOrder = 4
        }
        const allKeys = allFields.value.map(f => f.key)
        if (!Array.isArray(cardBehavior.value.qualifiedFieldKeys) || cardBehavior.value.qualifiedFieldKeys.length === 0) {
            cardBehavior.value.qualifiedFieldKeys = [...allKeys]
        } else {
            cardBehavior.value.qualifiedFieldKeys = cardBehavior.value.qualifiedFieldKeys.filter(key => allKeys.includes(key))
        }
    } catch (error) {
        console.error('Error fetching card settings:', error)
    }
}
function buildLeadSearchApiParams(q = {}) {
    const params = {
        ...(q.search && { search: q.search }),
        ...(q.lead_name && { lead_name: q.lead_name }),
        ...(q.first_name && { first_name: q.first_name }),
        ...(q.responsible_person_id != null && q.responsible_person_id !== '' && { responsible_person_id: q.responsible_person_id }),
        ...(q.created_at && { created_at: q.created_at }),
        ...(q.created_from && { created_from: q.created_from }),
        ...(q.created_to && { created_to: q.created_to }),
        ...(q.source != null && q.source !== '' && (!Array.isArray(q.source) || q.source.length > 0) && { source: q.source }),
        ...(q.lead_branch_source && { lead_branch_source: q.lead_branch_source }),
        ...(q.stage_id != null && q.stage_id !== '' && { stage_id: q.stage_id }),
        ...(q.closed !== undefined && q.closed !== null && q.closed !== '' && { closed: q.closed }),
        ...(q.work_phone && { work_phone: q.work_phone }),
        ...(q.email && { email: q.email }),
        ...(q.bedrooms !== undefined && q.bedrooms !== null && q.bedrooms !== '' && { bedrooms: q.bedrooms }),
        ...(q.team_id != null && q.team_id !== '' && { team_id: q.team_id }),
        ...(q.budget_from != null && q.budget_from !== '' && { budget_from: q.budget_from }),
        ...(q.budget_to != null && q.budget_to !== '' && { budget_to: q.budget_to }),
        ...(q.interaction_result != null && q.interaction_result !== '' && { interaction_result: q.interaction_result }),
        ...(q.lead_type != null && q.lead_type !== '' && { lead_type: q.lead_type }),
        ...(q.property_status != null && q.property_status !== '' && { property_status: q.property_status }),
        ...(q.property_type_id != null && q.property_type_id !== '' && { property_type_id: q.property_type_id }),
        ...(q.area_id != null && q.area_id !== '' && { area_id: q.area_id }),
        ...(q.assigned_at != null && q.assigned_at !== '' && { assigned_at: q.assigned_at }),
        ...(q.assigned_from != null && q.assigned_from !== '' && { assigned_from: q.assigned_from }),
        ...(q.assigned_to != null && q.assigned_to !== '' && { assigned_to: q.assigned_to }),
        ...(q.status_lead != null && q.status_lead !== '' && { status_lead: q.status_lead }),
        ...(q.purpose_buying != null && q.purpose_buying !== '' && { purpose_buying: q.purpose_buying }),
        ...(q.why_lost_lead != null && q.why_lost_lead !== '' && { status_lead: q.why_lost_lead }),
        ...(q.heat != null && q.heat !== '' && { heat: q.heat }),
    }

    if (q.office_branch != null && q.office_branch !== '') {
        if (Array.isArray(q.office_branch) && q.office_branch.length > 0) {
            params.office_branch = q.office_branch
        } else if (!Array.isArray(q.office_branch)) {
            params.office_branch = q.office_branch
        }
    }

    return params
}

const fetchLeads = async (immediate = false, queryOverride = undefined) => {
    if (queryOverride !== undefined) {
        appliedSearchParams.value = queryOverride && Object.keys(queryOverride).length ? { ...queryOverride } : null
    }
    // Clear any pending debounce
    if (fetchDebounceTimer.value) {
        clearTimeout(fetchDebounceTimer.value)
        fetchDebounceTimer.value = null
    }
    
    // If not immediate, debounce rapid calls
    if (!immediate) {
        return new Promise((resolve) => {
            fetchDebounceTimer.value = setTimeout(async () => {
                await executeFetchLeads()
                resolve()
            }, 300) // 300ms debounce
        })
    }
    
    return executeFetchLeads()
}
function closeStageModal() {
    showStageModal.value = false
    isEditingStage.value = false
    stageForm.value = { id: null, name: '', color: null }
}

async function saveStage() {
    if (!stageForm.value.name.trim()) {
        $showNotification('Stage name is required', 'warning')
        return
    }

    try {
        await api.put(`/stages/${stageForm.value.id}`, {
            name: stageForm.value.name,
            color: stageForm.value.color
        })

        // Update local column
        const column = columns.value.find(c => c.status === stageForm.value.id)
        if (column) {
            column.title = stageForm.value.name
            column.color = stageForm.value.color
        }

        $showNotification('Stage updated successfully', 'success')
        closeStageModal()
    } catch (error) {
        $showNotification('Failed to update stage', 'error')
    }
}

const executeFetchLeads = async () => {
    if (isFetching.value) return
    
    if (abortController.value) {
        abortController.value.abort()
    }
    
    abortController.value = new AbortController()
    isFetching.value = true
    if (!columns.value.length) {
        loading.value = true
    }
    
    try {
        const q = effectiveSearchParams.value

        const params = {
            per_page: leadsPerPage.value,
            ...buildLeadSearchApiParams(q),
        }

        const response = await api.get('/stages/kanban/stages-with-leads', {
            params,
            signal: abortController.value.signal
        })
        
        const responseData = response?.data?.data
        const stagesData = responseData?.stages || []
        const analytics = responseData?.analytics
        if (analytics && typeof analytics === 'object') {
            leadAnalyticsServer.value = {
                tempCold: Number(analytics.tempCold) || 0,
                tempWarm: Number(analytics.tempWarm) || 0,
                tempHot: Number(analytics.tempHot) || 0,
                callAnswered: Number(analytics.callAnswered) || 0,
                callNoAnswer: Number(analytics.callNoAnswer) || 0,
            }
        }

        // تحويل البيانات
        const newData = stagesData.map((stage, index) => ({
            title: stage.name,
            status: stage.id,
            color: stage.color || getColorByIndex(index),
            order: stage.order ?? index,
            leads: stage.leads || [],
            pagination: stage.pagination || {
                current_page: 1,
                last_page: 1,
                per_page: leadsPerPage.value,
                total: stage.lead_count || 0,
                has_more_pages: false
            }
        }))
        
        columns.value = newData
        seedActivityAvatarCacheFromColumns(newData)
        syncStageOrderMapFromColumns(newData)
        
        // تحديث visibleLeadCounts (العدد المرئي)
        const nextCounts = {}
        columns.value.forEach(col => {
            const total = Array.isArray(col.leads) ? col.leads.length : 0
            nextCounts[col.status] = Math.min(INITIAL_VISIBLE_LEADS_PER_STAGE, total)
        })
        visibleLeadCounts.value = nextCounts
        
        // تخزين pagination info
        const newStagePagination = {}
        stagesData.forEach(stage => {
            newStagePagination[stage.id] = {
                currentPage: stage.pagination?.current_page || 1,
                lastPage: stage.pagination?.last_page || 1,
                perPage: stage.pagination?.per_page || leadsPerPage.value,
                total: stage.pagination?.total || stage.lead_count || 0,
                hasMorePages: stage.pagination?.has_more_pages || false
            }
        })
        stagePagination.value = newStagePagination
        
        error.value = null
        saveColumnsToCache()
        
    } catch (err) {
        if (err.name !== 'AbortError' && err.name !== 'CanceledError') {
            error.value = err.message || 'Failed to load data'
        }
    } finally {
        isFetching.value = false
        loading.value = false
        abortController.value = null
        markKanbanReady()
    }
}

function saveColumnsToCache() {
    try {
        const snapshot = Array.isArray(columns.value)
            ? columns.value.map(col => ({
                  // keep only what we need for fast first paint
                  title: col.title,
                  status: col.status,
                  color: col.color,
                  order: col.order,
                  // cap number of cached leads per stage to keep localStorage small
                  leads: Array.isArray(col.leads) ? col.leads.slice(0, 100) : []
              }))
            : []

        const payload = {
            cachedAt: Date.now(),
            columns: snapshot
        }
        localStorage.setItem(KANBAN_LEADS_CACHE_KEY, JSON.stringify(payload))
    } catch (e) {
        // ignore cache errors
    }
}

function syncStageOrderMapFromColumns(cols) {
    const map = {}
    ;(cols || []).forEach((col) => {
        if (col?.status != null) {
            map[col.status] = col.order ?? 0
        }
    })
    stageOrderMap.value = map
}

function loadCachedColumns() {
    try {
        const raw = localStorage.getItem(KANBAN_LEADS_CACHE_KEY)
        if (!raw) return false
        const parsed = JSON.parse(raw)
        if (!parsed || !Array.isArray(parsed.columns)) return false

        const now = Date.now()
        if (parsed.cachedAt && now - parsed.cachedAt > KANBAN_LEADS_CACHE_TTL_MS) {
            return false
        }

        columns.value = parsed.columns
        syncStageOrderMapFromColumns(parsed.columns)

        // Initialize visible counts based on cached data
        const nextCounts = {}
        columns.value.forEach(col => {
            const total = Array.isArray(col.leads) ? col.leads.length : 0
            nextCounts[col.status] = Math.min(INITIAL_VISIBLE_LEADS_PER_STAGE, total)
        })
        visibleLeadCounts.value = nextCounts

        loading.value = false
        error.value = null
        return true
    } catch (e) {
        // ignore cache errors
        return false
    }
}

function getVisibleLeadCount(stageId) {
    const current = visibleLeadCounts.value[stageId]
    if (current == null) {
        return INITIAL_VISIBLE_LEADS_PER_STAGE
    }
    return current
}


function loadMoreLeads(stageId) {
    const current = getVisibleLeadCount(stageId)
    visibleLeadCounts.value = {
        ...visibleLeadCounts.value,
        [stageId]: current + VISIBLE_LEADS_INCREMENT
    }
}
async function fetchMoreLeadsFromApi(stageId) {
    // لو بتحمل حالياً، متعملش حاجة
    if (loadingMoreLeads.value[stageId]) return
    
    // لوصلت لآخر صفحة، متعملش حاجة
    const stage = columns.value.find(c => c.status === stageId)
    if (!stage || !stage.pagination?.has_more_pages) return
    
    loadingMoreLeads.value = {
        ...loadingMoreLeads.value,
        [stageId]: true
    }
    
    try {
        const nextPage = (stage.pagination?.current_page || 1) + 1

        // جمع معاملات الفلترة الحالية
        const q = effectiveSearchParams.value

        const params = {
            page: nextPage,
            per_page: leadsPerPage.value,
            ...buildLeadSearchApiParams(q),
        }
        const response = await api.get(`/stages/kanban/stage/${stageId}/more-leads`, {
            params
        })
        
        const responseData = response?.data?.data
        const newLeads = responseData?.leads || []
        const newPagination = responseData?.pagination || {}
        
        // إضافة الـ leads الجديدة للـ column
        const columnIndex = columns.value.findIndex(c => c.status === stageId)
        if (columnIndex !== -1) {
            // ضيف الـ leads الجديدة تحت القديمة
            columns.value[columnIndex].leads = [
                ...columns.value[columnIndex].leads,
                ...newLeads
            ]
            
            // تحديث الـ pagination
            columns.value[columnIndex].pagination = {
                current_page: newPagination.current_page,
                last_page: newPagination.last_page,
                per_page: newPagination.per_page,
                total: newPagination.total,
                has_more_pages: newPagination.has_more_pages
            }
        }
        
        // تحديث stagePagination
        stagePagination.value = {
            ...stagePagination.value,
            [stageId]: {
                currentPage: newPagination.current_page,
                lastPage: newPagination.last_page,
                perPage: newPagination.per_page,
                total: newPagination.total,
                hasMorePages: newPagination.has_more_pages
            }
        }
        
        // بعد ما تجيب الليدا الجديدة، زود العدد المرئي عشان تظهر
        // لكن هنا أحنا ضفناها بالفعل للـ leads array، فمحتاجين نحدث visibleLeadCounts
        const totalLeadsNow = columns.value[columnIndex].leads.length
        visibleLeadCounts.value = {
            ...visibleLeadCounts.value,
            [stageId]: totalLeadsNow // خلي الكل مرئي
        }
        
    } catch (error) {
        console.error('Error loading more leads:', error)
        $showNotification('Failed to load more leads', 'error')
    } finally {
        loadingMoreLeads.value = {
            ...loadingMoreLeads.value,
            [stageId]: false
        }
    }
}
function onColumnScroll(column, event) {
    const el = event?.target
    if (!el) return
    
    const threshold = 100
    const reachedBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - threshold
    
    if (reachedBottom) {
        const stageId = column.status
        const pagination = stagePagination.value[stageId]
        const isLoading = loadingMoreLeads.value[stageId]
        
        // لو فيه صفحات تانية ومش بنحمل دلوقتي
        if (pagination?.hasMorePages && !isLoading) {
            console.log(`Loading more leads for stage ${stageId} - Page ${pagination.currentPage + 1}`)
            fetchMoreLeadsFromApi(stageId)
        }
    }
}

// Fetch responsible persons
async function fetchResponsiblePersons() {
    try {
        const response = await api.get('/available-responsible-persons')
        
        if (response.data && response.data.data) {
            responsiblePersons.value = response.data.data
        } else {
            responsiblePersons.value = []
        }
    } catch (error) {
        // Don't throw error for this, we can still work without it
    }
}

function openConvertLeadModal(lead) {
    selectedLeadForConversion.value = lead?.id || lead?.lead_id || null
    selectedLeadData.value = lead
    
    nextTick(() => {
        if (convertModalRef.value) {
            convertModalRef.value.show(selectedLeadForConversion.value, selectedLeadData.value)
        }
    })
}

function handleLeadConverted(deal) {

   

    $showNotification('Lead converted to deal successfully', 'success')
    fetchLeads(true)
 emit('deal-created', deal)
    selectedLeadForConversion.value = null
    selectedLeadData.value = null
}


watch(() => columns.value?.length, () => {
    nextTick(() => updateScrollArrows())
    columns.value.forEach((column) => {
        const key = String(column.status)
        const max = Math.max((column.leads?.length || 1) - 1, 0)
        const current = mobileStageCardIndex.value[key] ?? 0
        mobileStageCardIndex.value[key] = Math.min(current, max)
    })
})
const enabledFields = computed(() => {
    return cardFields.value
        .filter(field => field.enabled)
        .sort((a, b) => a.order - b.order)
})

const orderCardFields = (fields) => {
    const filtered = fields.filter(field => field.key !== 'lead_name')
    const normal = filtered.filter(field => field.key !== 'responsible_person' && field.key !== 'assigned_by')
    const responsible = filtered.find(field => field.key === 'responsible_person')
    const assigned = filtered.find(field => field.key === 'assigned_by')
    if (responsible) normal.push(responsible)
    if (assigned) normal.push(assigned)
    return normal
}

const hasResponsiblePerson = (task) => {
    return !!(task?.responsible_person?.name || task?.responsible_person?.avatar)
}

const activityDisplayAt = (task) =>
    task?.last_activity_at ?? task?.bitrix24_last_activity_at ?? task?.assigned_at ?? null

const hasAssignedBy = (task) => {
    return !!(activityDisplayAt(task) || activityPerson(task)?.name || task?.bitrix24_last_activity_by_id)
}

const enabledFieldsForColumn = (column, task) => {
    const fields = enabledFields.value
    const allLeadFields = Array.isArray(allFields.value) && allFields.value.length > 0 ? allFields.value : fields
    const stageOrder = Number(column?.order || 0)
    const qualifiedStart = Number(cardBehavior.value.qualifiedStartOrder || 4)
    const isFromQualified = stageOrder >= qualifiedStart

    if (cardBehavior.value.showMoreFromQualified) {
        if (isFromQualified) {
            const qualifiedKeys = Array.isArray(cardBehavior.value.qualifiedFieldKeys)
                ? cardBehavior.value.qualifiedFieldKeys
                : []
            return orderCardFields(
                allLeadFields
                    .filter(field => qualifiedKeys.includes(field.key))
                    .filter(field => hasDynamicFieldValue(task, field.key) || field.key === 'responsible_person' || field.key === 'assigned_by')
            )
        }
        // First stages stay on normal card control
        return orderCardFields(
            fields
                .filter(field => hasDynamicFieldValue(task, field.key) || field.key === 'responsible_person' || field.key === 'assigned_by')
        )
    }

    return orderCardFields(
        fields
            .filter(field => hasDynamicFieldValue(task, field.key) || field.key === 'responsible_person' || field.key === 'assigned_by')
    )
}
const isFieldEnabled = (fieldKey) => {
    return cardFields.value.some(field => field.key === fieldKey && field.enabled)
}

const activePersonHover = ref(null)
const personHoverHideTimer = ref(null)
const personHoverDetailsCache = new Map()

const normalizePersonHoverData = (person, task = {}, type = 'responsible', fallbackName = 'Unknown') => {
    const name = person?.name || person?.full_name || fallbackName
    const position = person?.position || person?.designation || person?.job_title || person?.role_name || person?.role || 'Team Member'
    const manager =
        person?.manager_name ||
        person?.team_lead_name ||
        person?.reports_to_name ||
        person?.parent_name ||
        person?.manager?.name ||
        person?.team_lead?.name ||
        person?.parent?.name ||
        (type === 'responsible' ? (task?.parent?.name || task?.manager?.name || task?.team_lead?.name) : null) ||
        (isActivityPersonType(type) ? (person?.parent_name || task?.parent?.manager_name || task?.parent?.manager?.name || task?.manager?.name) : null) ||
        'Not specified'
    const branch =
        person?.branch_name ||
        person?.branch?.name ||
        person?.office ||
        person?.team ||
        person?.department ||
        person?.location ||
        person?.team_name ||
        task?.lead_branch_source ||
        task?.branch_name ||
        task?.branch?.name ||
        task?.office_branch_name ||
        task?.office_branch ||
        'Not specified'
    const avatar = person?.avatar || person?.image || person?.photo || ''
    return { name, position, manager, branch, avatar }
}

const enrichPersonHoverFromApi = async (userId, leadId, type, basePerson, task, fallbackName) => {
    if (!userId) return
    try {
        let user = personHoverDetailsCache.get(userId)
        if (!user) {
            const response = await api.get(`/users/${userId}`)
            const payload = response.data?.data
            user = payload?.data && typeof payload.data === 'object' ? payload.data : payload
            if (user?.id) {
                personHoverDetailsCache.set(userId, user)
            }
        }
        if (!user?.id) return
        if (user.avatar) {
            const resolved = resolveKanbanAvatarUrl(user.avatar)
            if (resolved) {
                activityAvatarCache.value = {
                    ...activityAvatarCache.value,
                    [userId]: resolved,
                }
            }
        }
        if (activePersonHover.value?.leadId !== leadId || activePersonHover.value?.type !== type) return
        activePersonHover.value = {
            leadId,
            type,
            data: normalizePersonHoverData(
                {
                    ...basePerson,
                    ...user,
                    position: user.position || user.role_name || basePerson?.position,
                    branch_name: user.branch || user.branch_name || basePerson?.branch_name,
                    parent_name: user.parent_name || basePerson?.parent_name,
                },
                task,
                type,
                fallbackName,
            ),
        }
    } catch {
        // keep card data from kanban payload
    }
}

const showPersonHoverCard = (task, type) => {
    cancelPersonHoverHide()
    const person = isActivityPersonType(type) ? activityPerson(task) : task?.responsible_person
    const fallbackName = isActivityPersonType(type)
        ? (activityPerson(task)?.name || 'Activity')
        : (task?.responsible_person?.name || 'Responsible Person')
    const hoverType = isActivityPersonType(type) ? 'activity' : type
    activePersonHover.value = {
        leadId: task?.id,
        type: hoverType,
        data: normalizePersonHoverData(person, task, hoverType, fallbackName),
    }
    if (isActivityPersonType(type) && person?.id) {
        enrichPersonHoverFromApi(Number(person.id), task?.id, hoverType, person, task, fallbackName)
    }
}

const hidePersonHoverCard = () => {
    cancelPersonHoverHide()
    personHoverHideTimer.value = setTimeout(() => {
        activePersonHover.value = null
    }, 90)
}

const cancelPersonHoverHide = () => {
    if (personHoverHideTimer.value) {
        clearTimeout(personHoverHideTimer.value)
        personHoverHideTimer.value = null
    }
}

const isPersonHoverVisible = (task, type) => {
    return activePersonHover.value?.leadId === task?.id && activePersonHover.value?.type === type
}

const getCreatedByDisplay = (task) => {
    const u = task?.added_by_user
    if (u?.name) return u.name
    if (typeof u === 'string' && u.trim()) return u.trim()
    if (task?.added_by != null && task?.added_by !== '') {
        return String(task.added_by)
    }
    return '—'
}

const hasDynamicFieldValue = (task, key) => {
    if (key === 'created_by') {
        return !!(
            task?.added_by_user?.name ||
            (typeof task?.added_by_user === 'object' && task.added_by_user && Object.keys(task.added_by_user).length > 0) ||
            (task?.added_by != null && task?.added_by !== '')
        )
    }
    // For bedrooms: show root task.bedrooms only when we are not hiding bedrooms because the *priority* requirement is plot/land.
    if (key === 'bedrooms') {
        if (shouldHideBedroomsDueToPlotPriority(task)) {
            return false
        }
        const value = task?.bedrooms
        if (value == null) return false
        if (typeof value === 'string') return value.trim().length > 0
        if (Array.isArray(value)) return value.length > 0
        if (typeof value === 'object') return Object.keys(value).length > 0
        return true
    }
    const value = task?.[key]
    if (value == null) return false
    if (typeof value === 'string') return value.trim().length > 0
    if (Array.isArray(value)) return value.length > 0
    if (typeof value === 'object') return Object.keys(value).length > 0
    return true
}

const getDynamicFieldDisplay = (task, key) => {
    if (key === 'created_by') {
        return getCreatedByDisplay(task)
    }
    const value = task?.[key]
    if (value == null) return '—'
    if (key === 'email') return formatMaskedEmail(value)
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') return String(value)
    if (Array.isArray(value)) return value.map(v => (typeof v === 'object' ? (v?.name || v?.label || JSON.stringify(v)) : String(v))).join(', ')
    if (typeof value === 'object') return value?.name || value?.label || JSON.stringify(value)
    return String(value)
}

// ==================== Priority Requirement Logic ====================
const QUAL_META_ID = '__qualification_meta__'
const QUAL_META_KIND = 'qualification_meta'
const PLOT_TYPE_IDS = [24, 31, 35, 36]

/** Build a single searchable label for plot/land detection (API shapes vary). */
const getRequirementPropertyTypeText = (req) => {
    if (!req || typeof req !== 'object') return ''
    const parts = []
    const push = (v) => {
        if (v == null) return
        if (typeof v === 'string' && v.trim()) parts.push(v.trim())
        else if (typeof v === 'number') parts.push(String(v))
    }
    push(req.property_type_label)
    push(req.property_type_name)
    if (typeof req.property_type === 'string') push(req.property_type)
    else if (req.property_type && typeof req.property_type === 'object') {
        push(req.property_type.name)
        push(req.property_type.label)
        push(req.property_type.text)
        push(req.property_type.title)
    }
    return parts.join(' ').trim()
}

/**
 * Bedrooms are not applicable for land / plot types (incl. Residential Plot, Commercial Plot(s)).
 */
const isNoBedroomPropertyType = (req) => {
    const combined = getRequirementPropertyTypeText(req).toLowerCase()
    if (!combined) {
        const typeId = req?.property_type_id
        if (typeId != null && PLOT_TYPE_IDS.includes(Number(typeId))) return true
        return false
    }
    if (/\bland\b/.test(combined)) return true
    if (/\bplots?\b/.test(combined)) return true
    if (/residential\s+plot/.test(combined)) return true
    if (/commercial\s+plots?/.test(combined)) return true
    return false
}
// دالة للحصول على الـ extra requirements من الـ lead
const getExtraClientRequirements = (task) => {
    if (!task?.extra_client_requirements) return []
    return task.extra_client_requirements.filter(item => item?._kind !== QUAL_META_KIND)
}

// دالة للحصول على مصدر الـ qualification (الـ priority requirement)
const getQualificationSourceId = (task) => {
    const extraReqs = task?.extra_client_requirements || []
    const meta = extraReqs.find(item => item?._kind === QUAL_META_KIND)
    const source = meta?.source ?? 'primary'
    if (source === 'primary') return 'primary'
    const extras = getExtraClientRequirements(task)
    const exists = extras.some((req) => String(req?.id) === String(source))
    return exists ? source : 'primary'
}

const getPriorityRequirement = (task) => {
    const sourceId = getQualificationSourceId(task)

    // إذا كان المصدر ليس 'primary'، جلب من extra requirements
    if (sourceId !== 'primary') {
        const extraReqs = getExtraClientRequirements(task)
        const priorityReq = extraReqs.find((req) => String(req?.id) === String(sourceId))

        if (priorityReq) {
            priorityReq.isPlotsOrLand = isNoBedroomPropertyType(priorityReq)
            return priorityReq
        }
    }

    // Fallback إلى primary
    const primaryPtLabel =
        typeof task.property_type === 'string'
            ? task.property_type
            : task.property_type?.name ||
              task.property_type?.label ||
              task.property_type?.text ||
              task.property_type_name ||
              task.property_type_label ||
              ''
    const primaryReqShape = {
        property_type_id: task.property_type_id,
        property_type_label: primaryPtLabel,
        property_type: task.property_type,
        property_type_name: task.property_type_name,
    }
    return {
        area_id: task.area_id,
        area_label: task.area,
        property_type_id: task.property_type_id,
        property_type_label: primaryPtLabel,
        lead_type: task.lead_type,
        property_status: task.property_status,
        bedrooms: task.bedrooms,
        budget_from: task.budget_from,
        budget_to: task.budget_to,
        purpose_buying: task.purpose_buying,
        status_lead: task.status_lead,
        isPrimary: true,
        isPlotsOrLand: isNoBedroomPropertyType(primaryReqShape),
    }
}
const getPriorityBedrooms = (task) => {
    const req = getPriorityRequirement(task)

    if (!req) {
        return null
    }

    const hideBedrooms = req.isPlotsOrLand || isNoBedroomPropertyType(req)

    if (hideBedrooms) {
        return null
    }

    return req.bedrooms ?? null
}
// دالة للتحقق مما إذا كان يجب إظهار الـ bedrooms
const shouldShowPriorityBedrooms = (task) => {
    const bedrooms = getPriorityBedrooms(task)
    return bedrooms && bedrooms !== '' && bedrooms !== null
}

/** Hide bedroom line on the card when the resolved priority requirement is plot/land (incl. residential/commercial plots). */
const shouldHideBedroomsDueToPlotPriority = (task) => {
    const req = getPriorityRequirement(task)
    if (!req) return false
    return !!(req.isPlotsOrLand || isNoBedroomPropertyType(req))
}

// دالة للحصول على الـ property type من الـ priority requirement
const getPriorityPropertyType = (task) => {
    const priorityReq = getPriorityRequirement(task)
    if (!priorityReq) return null
    return priorityReq.property_type_label || null
}

// دالة للحصول على الـ lead type من الـ priority requirement
const getPriorityLeadType = (task) => {
    const priorityReq = getPriorityRequirement(task)
    if (!priorityReq) return null
    return priorityReq.lead_type || null
}

// دالة للحصول على الـ property status من الـ priority requirement
const getPriorityPropertyStatus = (task) => {
    const priorityReq = getPriorityRequirement(task)
    if (!priorityReq) return null
    
    // إذا كان lead type = rent، لا نعرض property status
    if (priorityReq.lead_type?.toLowerCase() === 'rent') {
        return null
    }
    return priorityReq.property_status || null
}

// دالة للحصول على الـ purpose of purchase من الـ priority requirement
const getPriorityPurposeBuying = (task) => {
    const priorityReq = getPriorityRequirement(task)
    if (!priorityReq) return null
    
    // إذا كان lead type = rent، لا نعرض purpose of purchase
    if (priorityReq.lead_type?.toLowerCase() === 'rent') {
        return null
    }
    return priorityReq.purpose_buying || null
}

// دالة للحصول على الـ budget من الـ priority requirement
const getPriorityBudget = (task) => {
    const priorityReq = getPriorityRequirement(task)
    if (!priorityReq) return null
    
    const from = priorityReq.budget_from
    const to = priorityReq.budget_to
    
    if (from && to) return `${from} - ${to}`
    if (from) return `From ${from}`
    if (to) return `To ${to}`
    return null
}
/** @deprecated use isNoBedroomPropertyType — kept name for any external refs */
const isPlotOrLand = (req) => isNoBedroomPropertyType(req)
const formatMaskedEmail = (email) => {
    const raw = String(email || '').trim()
    if (!raw) return ''
    if (raw.length <= 8) return `${raw}....`
    return `${raw.slice(0, 8)}....`
}
const formatMaskedQuestion = (questionData) => {
    if (!questionData) return '—'
    

    
    if (typeof questionData === 'string') {
        let questionText = questionData.replace(/_/g, ' ')
        questionText = questionText.replace(/\b\w/g, l => l.toUpperCase())
        
        if (questionText.length > 20) {
            questionText = questionText.substring(0, 30) + '...'
        }
        
        return questionText
    }
    
    return '—'
}
watch(cardFields, () => {
    console.log('Card fields updated:', cardFields.value)
}, { deep: true })
onMounted(async () => {
    const hadCache = loadCachedColumns()
    if (hadCache) {
        markKanbanReady()
    }

    try {
        await fetchLeads(true)
    } finally {
        markKanbanReady()
    }

    fetchResponsiblePersons()
    fetchCardSettings()

    nextTick(() => updateScrollArrows())
    window.addEventListener('resize', updateScrollArrows)
    setTimeout(() => {
        initializeLeadUpdates()
    }, 1000)
})

onUnmounted(() => {
    onLeadDragEnd()
    stopScroll()
    cancelPersonHoverHide()
    window.removeEventListener('resize', updateScrollArrows)
    cleanup()
})

// Expose fetchLeads so parent can call it
defineExpose({
    fetchLeads
})

// Initialize real-time updates with Echo/Pusher
const initializeLeadUpdates = () => {
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        startPolling()
        return
    }

    try {
        const channel = window.Echo.private(`user.${user.id}`)
        
        channel.error((error) => {
            startPolling()
        })
        
        channel.listen('.lead.updated', (event) => {
            handleLeadUpdate(event, 'updated')
        })
        
        channel.listen('.lead.assigned', (event) => {
            handleLeadUpdate(event, 'assigned')
        })


        echoListeners.value.push(channel)

        // Engine emits `lead.assignment.updated` on `private-lead-assignment` (immediate, small payload).
        // Kanban must subscribe here — `user.{id}` may omit sales users / dual-role admins for `lead.updated`.
        const assignmentChannel = window.Echo.private('lead-assignment')
        assignmentChannel.listen('.lead.assignment.updated', () => {
            if (leadAssignmentBoardRefreshTimer.value) {
                clearTimeout(leadAssignmentBoardRefreshTimer.value)
            }
            leadAssignmentBoardRefreshTimer.value = setTimeout(() => {
                leadAssignmentBoardRefreshTimer.value = null
                if (!isFetching.value) {
                    fetchLeads(true)
                }
            }, 400)
        })
        echoListeners.value.push(assignmentChannel)
    } catch (error) {
        startPolling()
    }
}

const handleLeadUpdate = (event, eventType = 'unknown') => {
    // Extract lead data - handle different possible structures
    let leadData = event.lead
    
    // If lead is wrapped in a data property
    if (leadData?.data) {
        leadData = leadData.data
    }
    
    // If lead is an object with nested structure
    if (!leadData && event.lead) {
        leadData = event.lead
    }
    
    if (!leadData || !leadData.id) {
        return
    }
    
    switch (event.action_type) {
        case 'created':
            handleNewLead(leadData)
            break
        case 'updated':
            handleUpdatedLead(leadData, 'updated')
            break
        case 'assigned':
            handleAssignedLead(leadData, event.changes)

            break
        case 'deleted':
            handleDeletedLead(leadData)
            break
        case 'stage_changed':
            handleStageChanged(leadData, event.changes)
            break
        case 'revert':
            console.log("revert");
            handleStageChanged(leadData, event.changes)
            break
        default:
            // For unknown action types, try to handle as update
            handleUpdatedLead(leadData, eventType)
    }
    
    showLeadNotification(event)
}
const handleAssignedLead = (lead, changes) => {
    const user = JSON.parse(localStorage.getItem('user'))
    const currentUserId = user?.id

    if (!lead || !lead.id) return

    const oldPersonId = changes?.old_person_id ?? null
    const newPersonId = lead.responsible_person_id

    if (oldPersonId && oldPersonId === currentUserId && oldPersonId != newPersonId) {
        removeLeadFromColumns(lead.id)
        return
    }
      console.log(newPersonId == currentUserId);
    if (newPersonId == currentUserId) {
        handleUpdatedLead(lead, 'assigned')
        return
    }
     handleUpdatedLead(lead, 'assigned')
        return
}

const handleNewLead = (lead) => {
    if (!lead || !lead.id) {
        return
    }
    
    // Extract stage_id from different possible locations
    const stageId = lead.stage_id || lead.stage?.id || null
    
    if (!stageId) {
        // If no stage_id, try to add to first column as fallback
        if (columns.value.length > 0 && columns.value[0].status) {
            const firstStageId = columns.value[0].status
            const leadWithStage = { ...lead, stage_id: firstStageId }
            handleNewLead(leadWithStage)
            return
        } else {
            return
        }
    }
    
    const columnIndex = columns.value.findIndex(col => col.status === stageId)
    
    if (columnIndex !== -1) {
        if (!columns.value[columnIndex].leads) {
            columns.value[columnIndex].leads = []
        }
        
        const existingIndex = columns.value[columnIndex].leads.findIndex(l => l && l.id === lead.id)
        if (existingIndex === -1) {
            // Ensure lead has stage_id set
            const leadToAdd = { ...lead, stage_id: stageId }
            columns.value[columnIndex].leads.unshift(leadToAdd)
        } else {
            columns.value[columnIndex].leads[existingIndex] = { ...lead, stage_id: stageId }
        }
        // sortColumnLeadsByScore(columnIndex)
    }
}

const handleDeletedLead = (lead) => {
    const leadId = lead?.data?.id || lead?.id
    
    if (!leadId) {
        return
    }
    
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                column.leads.splice(index, 1)
                break
            }
        }
    }
}

function getPriorityLabel(priority) {
    if (priority === 'hot') return '🔥 HOT'
    if (priority === 'warm') return '🟡 WARM'
    return '❄️ COLD'
}

function getLeadPriorityClass(lead) {
    if (lead?.priority === 'hot') return 'lead-priority-hot-border'
    if (lead?.priority === 'warm') return 'lead-priority-warm-border'
    return 'lead-priority-cold-border'
}

function sortColumnLeadsByScore(columnIndex) {
    const leads = columns.value[columnIndex]?.leads
    if (!Array.isArray(leads)) return

    leads.sort((a, b) => {
        // const scoreA = Number(a?.score ?? 0)
        // const scoreB = Number(b?.score ?? 0)
        // if (scoreB !== scoreA) return scoreB - scoreA

        const aTime = a?.created_at ? new Date(a.created_at).getTime() : 0
        const bTime = b?.created_at ? new Date(b.created_at).getTime() : 0
        return bTime - aTime
    })
}

const handleLeadUpdatedFromModal = (updatedLead) => {
    if (updatedLead?.id) {
        handleUpdatedLead(updatedLead, 'updated')
    }
}

const handleUpdatedLead = (lead, updateType = 'updated') => {
    if (!lead || !lead.id) {
        return
    }
    if (!isAdminOrSuperAdmin.value  && lead.is_reverted) {
        removeLeadFromColumns(lead.id)
        return
    }
    // Extract stage_id from different possible locations
    const stageId = lead.stage_id || lead.stage?.id || null
    
    if (!stageId) {
        // If no stage_id, try to add to first column as fallback
        if (columns.value.length > 0 && columns.value[0].status) {
            const firstStageId = columns.value[0].status
            // Create a lead copy with the first stage_id
            const leadWithStage = { ...lead, stage_id: firstStageId }
            handleUpdatedLead(leadWithStage, updateType)
            return
        } else {
            return
        }
    }
    
    let leadFound = false
    
    // First, try to find and update existing lead
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === lead.id)
            if (index !== -1) {
                leadFound = true
                
                if (column.status !== stageId) {
                    // Lead moved to different stage
                    column.leads.splice(index, 1)
                    
                    const newColumnIndex = columns.value.findIndex(c => c.status === stageId)
                    if (newColumnIndex !== -1) {
                        if (!columns.value[newColumnIndex].leads) {
                            columns.value[newColumnIndex].leads = []
                        }
                        
                        // Check if lead already exists in new column to avoid duplicates
                        const existingIndex = columns.value[newColumnIndex].leads.findIndex(l => l && l.id === lead.id)
                        if (existingIndex === -1) {
                            columns.value[newColumnIndex].leads.unshift(lead)
                        } else {
                            columns.value[newColumnIndex].leads[existingIndex] = lead
                        }
                        // sortColumnLeadsByScore(newColumnIndex)
                    }
                } else {
                    column.leads[index] = lead
                    // sortColumnLeadsByScore(i)
                }
                break
            }
        }
    }
    
    // If lead not found in any column, add it to the appropriate column (newly assigned lead)
    if (!leadFound) {
        const columnIndex = columns.value.findIndex(col => col.status === stageId)
        if (columnIndex !== -1) {
            if (!columns.value[columnIndex].leads) {
                columns.value[columnIndex].leads = []
            }
            
            // Check if lead already exists to avoid duplicates
            const existingIndex = columns.value[columnIndex].leads.findIndex(l => l && l.id === lead.id)
            if (existingIndex === -1) {
                // Ensure lead has stage_id set
                const leadToAdd = { ...lead, stage_id: stageId }
                columns.value[columnIndex].leads.unshift(leadToAdd)
            } else {
                // Update existing lead
                columns.value[columnIndex].leads[existingIndex] = { ...lead, stage_id: stageId }
            }
            // sortColumnLeadsByScore(columnIndex)
        } else {
            // Try to add to first available column as fallback
            if (columns.value.length > 0) {
                const firstColumn = columns.value[0]
                
                if (!firstColumn.leads) {
                    firstColumn.leads = []
                }
                
                const existingIndex = firstColumn.leads.findIndex(l => l && l.id === lead.id)
                if (existingIndex === -1) {
                    const leadToAdd = { ...lead, stage_id: firstColumn.status }
                    firstColumn.leads.unshift(leadToAdd)
                }
                // sortColumnLeadsByScore(0)
            }
        }
    }
}
const removeLeadFromColumns = (leadId) => {
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                column.leads.splice(index, 1)
                break
            }
        }
    }
}
const handleStageChanged = (lead, changes) => {
    const leadId = lead?.data?.id || lead?.id
    const leadStageId = lead?.data?.stage_id || lead?.stage_id

    if (!leadId || !leadStageId) return

    const existingLead = columns.value
        .flatMap(c => c.leads)
        .find(l => l.id === leadId)

    if (existingLead && existingLead.stage_id === leadStageId) {
        return
    }
    
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                if (column.status !== leadStageId) {
                    column.leads.splice(index, 1)
                    
                    const newColumnIndex = columns.value.findIndex(c => c.status === leadStageId)
                    if (newColumnIndex !== -1) {
                        if (!columns.value[newColumnIndex].leads) {
                            columns.value[newColumnIndex].leads = []
                        }
                        
                        const leadToAdd = lead.data || lead
                        columns.value[newColumnIndex].leads.unshift(leadToAdd)
                        // sortColumnLeadsByScore(newColumnIndex)
                    }
                }
                break
            }
        }
    }
}

const showLeadNotification = (event) => {
    
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })

    const leadData = event.lead?.data || event.lead
    const leadName = leadData?.lead_name || leadData?.lead_number || 'Unknown Lead'
    const leadNumber = leadData?.lead_number ? `#${leadData.lead_number}` : ''
    
    const userName = event.user_name || user.value?.name

    let title = ''
    let icon = 'info'

    switch (event.action_type) {
        case 'created':
            title = `📝 New Lead: ${leadName} ${leadNumber}`
            icon = 'success'
            break
        case 'updated':
            title = `✏️ ${userName} updated: ${leadName} ${leadNumber}`
            icon = 'info'
            break
        case 'assigned':
            title = `👤 ${userName} assigned: ${leadName} ${leadNumber}`
            icon = 'warning'
            break
        case 'stage_changed':
            title = `🔄 ${userName} moved: ${leadName} ${leadNumber}`
            icon = 'info'
            break
        case 'deleted':
            title = `🗑️ ${userName} deleted: ${leadName} ${leadNumber}`
            icon = 'error'
            break
        default:
            title = `📊 Lead updated: ${leadName} ${leadNumber}`
    }

    Toast.fire({
        icon: icon,
        title: title,
        text: event.message || 'Lead has been updated'
    })
}

const startPolling = () => {
    // Only start polling if not already polling and Echo is not available
    if (pollingInterval.value) {
        return
    }
    
    pollingInterval.value = setInterval(() => {
        // Only poll if not currently fetching
        // Use immediate=false to allow debouncing (though polling shouldn't need it)
        if (!isFetching.value) {
            fetchLeads(false)
        }
    }, 15000)
}

const cleanup = () => {
    // Cancel any pending request
    if (abortController.value) {
        abortController.value.abort()
        abortController.value = null
    }
    
    // Clear debounce timer
    if (fetchDebounceTimer.value) {
        clearTimeout(fetchDebounceTimer.value)
        fetchDebounceTimer.value = null
    }
    
    if (leadAssignmentBoardRefreshTimer.value) {
        clearTimeout(leadAssignmentBoardRefreshTimer.value)
        leadAssignmentBoardRefreshTimer.value = null
    }

    echoListeners.value.forEach((channel) => {
        if (channel) {
            try {
                // Stop listening to specific events
                channel.stopListening('.lead.updated')
                channel.stopListening('.lead.assigned')
                channel.stopListening('.lead.assignment.updated')
            } catch (error) {
                // Silently handle errors
            }
        }
    })
    echoListeners.value = []

    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}

const currentTask = ref({
    id: null,
    title: '',
    description: '',
    name: '',
    source: '',
    branchSource: '',
    responsible: { name: '', avatar: '' },
    assignedBy: { date: '', avatar: '' },
    createdAt: '',
    image: ''
})

const isEditing = ref(false)
const showViewModal = ref(false)
const selectedLead = ref(null)
const showDuplicateModal = ref(false)
const selectedLeadForDuplicates = ref(null)
const currentTriggerElement = ref(null)

function formatDate(dateString) {
    if (!dateString) return ''
    const date = new Date(dateString)
    const options = { month: 'short', day: 'numeric', year: 'numeric' }
    const formattedDate = date.toLocaleDateString('en-US', options)
    const formattedTime = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    })
    return `${formattedDate}  |  ${formattedTime}`
}

/** Activity tile: Bitrix24 LAST_ACTIVITY_TIME (last_activity_at / bitrix24_last_activity_at). */
function formatActivityDate(task) {
    return formatDate(activityDisplayAt(task))
}

function getMobileCardIndex(column) {
    const key = String(column.status)
    const current = mobileStageCardIndex.value[key] ?? 0
    const max = Math.max((column.leads?.length || 1) - 1, 0)
    if (current > max) {
        mobileStageCardIndex.value[key] = max
        return max
    }
    if (current < 0) {
        mobileStageCardIndex.value[key] = 0
        return 0
    }
    return current
}

function setMobileCardIndex(column, index) {
    const key = String(column.status)
    const max = Math.max((column.leads?.length || 1) - 1, 0)
    mobileStageCardIndex.value[key] = Math.min(Math.max(index, 0), max)
}

function nextMobileCard(column) {
    const max = Math.max((column.leads?.length || 1) - 1, 0)
    const next = Math.min(getMobileCardIndex(column) + 1, max)
    setMobileCardIndex(column, next)
}

function prevMobileCard(column) {
    const prev = Math.max(getMobileCardIndex(column) - 1, 0)
    setMobileCardIndex(column, prev)
}

function onMobileCardTouchStart(column, event) {
    if (!kanbanIsMobile.value) return
    const key = String(column.status)
    const touch = event.changedTouches?.[0]
    const x = touch?.clientX ?? 0
    const y = touch?.clientY ?? 0
    mobileTouchStartX.value[key] = x
    mobileTouchStartY.value[key] = y
    mobileTouchLastX.value[key] = x
    mobileTouchLastY.value[key] = y
    mobileSwipeMoved.value[key] = false
}

function onMobileCardTouchMove(column, event) {
    if (!kanbanIsMobile.value) return
    const key = String(column.status)
    const touch = event.changedTouches?.[0]
    if (!touch) return
    mobileTouchLastX.value[key] = touch.clientX
    mobileTouchLastY.value[key] = touch.clientY
}

function onMobileCardTouchEnd(column, event) {
    if (!kanbanIsMobile.value) return
    const key = String(column.status)
    const startX = mobileTouchStartX.value[key] ?? 0
    const startY = mobileTouchStartY.value[key] ?? 0
    const endX = event.changedTouches?.[0]?.clientX ?? mobileTouchLastX.value[key] ?? startX
    const endY = event.changedTouches?.[0]?.clientY ?? mobileTouchLastY.value[key] ?? startY
    const deltaX = endX - startX
    const deltaY = endY - startY
    const SWIPE_THRESHOLD_X = 18
    const SWIPE_DOMINANCE_RATIO = 1.2
    if (Math.abs(deltaX) < SWIPE_THRESHOLD_X) return
    // Ignore mostly-vertical gestures so scrolling still feels natural.
    if (Math.abs(deltaX) < Math.abs(deltaY) * SWIPE_DOMINANCE_RATIO) return
    mobileSwipeMoved.value[key] = true
    if (deltaX < 0) {
        nextMobileCard(column)
    } else {
        prevMobileCard(column)
    }
}

function viewLead(task) {
    selectedLead.value = task?.id
    showViewModal.value = true
    if (task?.id) {
        // Fire-and-forget: stamp a "view" entry in the lead history so the activity timeline
        // shows who opened the lead and when. Failures are non-fatal — never block the modal.
        api.get(`/leads/${task.id}/history/view`).catch(() => {})
    }
}

function isColumnVisibleOnMobile(column) {
    if (!kanbanIsMobile.value) return true
    if (mobileListFilterStageId.value === MOBILE_FILTER_ALL) return true
    return String(column.status) === String(mobileListFilterStageId.value)
}

function onLeadCardClick(task, column) {
    if (kanbanIsMobile.value && mobileSwipeMoved.value[String(column.status)]) {
        mobileSwipeMoved.value[String(column.status)] = false
        return
    }
    if (kanbanIsMobile.value) {
        viewLead(task)
        return
    }
    viewLead(task)
}

function closeMobileQuickSheet() {
    showMobileQuickSheet.value = false
    mobileQuickLead.value = null
    mobileQuickSourceColumn.value = null
}

function openMobilePickStageFromQuick() {
    mobilePickStageId.value = mobileQuickLead.value?.stage_id ?? mobileQuickSourceColumn.value?.status ?? null
    showMobilePickStageSheet.value = true
}

function closeMobilePickStageSheet() {
    showMobilePickStageSheet.value = false
}

function openMobileListFilterSheet() {
    showMobileListFilterSheet.value = true
}

function closeMobileListFilterSheet() {
    showMobileListFilterSheet.value = false
}

function selectMobileListFilter(stageId) {
    mobileListFilterStageId.value = stageId === MOBILE_FILTER_ALL ? MOBILE_FILTER_ALL : String(stageId)
    closeMobileListFilterSheet()
}


function onMobileColumnAddLead() {
    if (typeof kanbanOpenCreateLead === 'function') {
        kanbanOpenCreateLead()
    }
}

function openViewLeadFromMobileSheet() {
    const id = mobileQuickLead.value?.id
    if (id) {
        selectedLead.value = id
        showViewModal.value = true
        api.get(`/leads/${id}/history/view`).catch(() => {})
    }
    closeMobileQuickSheet()
}

async function confirmMobilePickStage() {
    const lead = mobileQuickLead.value
    const targetCol = mobilePickStageColumn.value
    if (!lead || !targetCol) return
    if (lead.stage_id === targetCol.status) {
        closeMobilePickStageSheet()
        return
    }
    closeMobilePickStageSheet()
    showMobileQuickSheet.value = false
    const leadCopy = lead
    mobileQuickLead.value = null
    mobileQuickSourceColumn.value = null
    mobilePickStageId.value = null
    await nextTick()
    await applyProgrammaticStageChange(leadCopy, targetCol)
}


function openDuplicateLeadsModal(leadId, event) {
    selectedLeadForDuplicates.value = leadId
    // Get the trigger element from event target
    if (event && event.currentTarget) {
        currentTriggerElement.value = event.currentTarget
    }
    showDuplicateModal.value = true
}

function handleViewDuplicateLead(leadId) {
    selectedLead.value = leadId
    showViewModal.value = true
    if (leadId) {
        api.get(`/leads/${leadId}/history/view`).catch(() => {})
    }
}

function openModal(task = null, status = '') {
    if (task) {
        currentTask.value = { ...task }
        isEditing.value = true
    } else {
        currentTask.value = {
            id: Date.now(),
            title: 'Compleate CRM From "Mamsha Gardens Plots"',
            name: '',
            source: '',
            branchSource: '',
            responsible: { name: 'Ahmad al mahfouz', avatar: '' },
            assignedBy: { date: new Date().toLocaleString(), avatar: '' },
            createdAt: new Date().toLocaleString(),
            status: status
        }
        isEditing.value = false
    }
    const modal = new bootstrap.Modal(document.getElementById('addTaskModal'))
    modal.show()
}

function saveTask() {
    const column = columns.value.find(c => c.status === currentTask.value.status)
    if (isEditing.value) {
        const index = column.leads.findIndex(t => t.id === currentTask.value.id)
        column.leads[index] = { ...currentTask.value }
    } else {
        column.leads.push({ ...currentTask.value })
    }
    const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'))
    modal.hide()
}

function handleFileChange(event) {
    const file = event.target.files[0]
    if (file) {
        const reader = new FileReader()
        reader.onload = () => {
            currentTask.value.image = reader.result
        }
        reader.readAsDataURL(file)
    }
}

function deleteTask(taskId) {
    for (const column of columns.value) {
        const idx = column.leads.findIndex(t => t.id === taskId)
        if (idx !== -1) {
            column.leads.splice(idx, 1)
            break
        }
    }
}
function editStage(stage) {
    stageForm.value = {
        id: stage.status,
        name: stage.title,
        color: stage.color
    }

    isEditingStage.value = true
    showStageModal.value = true
}


async function startEditingStage(column) {
    editingStageId.value = column.status
    editingStageTitle.value = column.title
    // Focus the input after it's rendered
    await nextTick()
    if (stageTitleInput.value) {
        stageTitleInput.value.focus()
        stageTitleInput.value.select()
    }
}

function cancelEditingStage() {
    editingStageId.value = null
    editingStageTitle.value = ''
}

async function saveStageName(column) {
    const newTitle = editingStageTitle.value.trim()
    
    // If title is empty or unchanged, cancel editing
    if (!newTitle || newTitle === column.title) {
        cancelEditingStage()
        return
    }
    
    try {
        // Update stage name via API
        // Ensure order is included and is a valid number
        const orderValue = column.order !== undefined && column.order !== null ? column.order : 0
        
        await api.put(`/stages/${column.status}`, {
            name: newTitle,
            order: orderValue
        })
        
        // Update local state
        column.title = newTitle
        
        // Dispatch custom event to notify StageSelector components to refresh
        window.dispatchEvent(new CustomEvent('stage-updated', {
            detail: { stageId: column.status, newName: newTitle }
        }))
        
        // Show success notification
        $showNotification('Stage name updated successfully', 'success')
        
        // Cancel editing
        cancelEditingStage()
    } catch (error) {
        $showNotification('Failed to update stage name', 'error')
        // Revert to original title on error
        editingStageTitle.value = column.title
    }
}
const missingFieldsForLead = ref([]) 


// When loading stages, also store the order mapping
const fetchStageOrders = async () => {
    try {
        const response = await api.get('/stages')
        let stages = []
        
        const payload = response.data?.data
        if (payload?.data && Array.isArray(payload.data)) {
            stages = payload.data
        } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
            stages = response.data.data
        } else if (response.data && Array.isArray(response.data)) {
            stages = response.data
        } else if (response.data && response.data.stages) {
            stages = response.data.stages
        }
        
        // تأكد من أن stages هي array
        if (!Array.isArray(stages)) {
            console.warn('Stages is not an array:', stages)
            stages = []
        }
        
        const map = {}
        stages.forEach(stage => {
            if (stage && stage.id) {
                map[stage.id] = stage.order || 0
            }
        })
        stageOrderMap.value = map
        console.log('Stage order map loaded:', stageOrderMap.value) // للتتبع
    } catch (error) {
        console.error('Error fetching stage orders:', error)
        // لا تفشل التطبيق إذا فشل جلب الـ orders
        stageOrderMap.value = {}
    }
}

async function onLeadDragChange(evt, column) {
    if (evt.added) {
        const lead = evt.added.element
        const newStageId = column.status
        const newStageOrder = stageOrderMap.value[newStageId] ?? column.order ?? 0
        const sourceColumn = columns.value.find(c => c.status === lead.stage_id)
        const normalizeStageName = (name) => String(name || '').toLowerCase().replace(/[^a-z]/g, '')
        const sourceStageName = normalizeStageName(sourceColumn?.title)
        const targetStageName = normalizeStageName(column?.title)
        const isAssignToFollowUpOrContacted =
          (  sourceStageName.includes('assign') || sourceStageName.includes('new lead')) &&
            (targetStageName.includes('followup') || targetStageName.includes('contacted'))

        // [NEW] Logic for moving to Contacted stage from ANY stage
        const isMovingToContacted = targetStageName.includes('contacted')
        const isSalutationMissing = !lead.salutation || lead.salutation === ''

        console.log('Lead drag change:', { 
            leadId: lead.id, 
            newStageId, 
            newStageOrder,
            leadData: lead,
            isMovingToContacted,
            isSalutationMissing
        })

        // [NEW] Check if moving to Contacted and salutation is missing
        if (isMovingToContacted && isSalutationMissing) {
            console.log('Moving to Contacted stage but salutation is missing. Showing modal.')
            
            pendingStageChange.value = {
                leadId: lead.id,
                targetStageId: newStageId,
                targetStageName: column.title,
                targetStageOrder: newStageOrder,
                originalStageId: lead.stage_id,
                leadData: { ...lead },
                isConversion: false,
                interactionMode: true // or false? Based on your modal, interactionMode shows call results. Set to false to show reason field.
            }
            // Specify that 'salutation' is the missing field
            missingFieldsForLead.value = ['salutation']

            // Revert the drag and drop UI change
            if (sourceColumn) {
                const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
                if (targetColumnIndex !== -1) {
                    columns.value[targetColumnIndex].leads =
                        columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
                }
                if (!sourceColumn.leads.find(l => l.id === lead.id)) {
                    sourceColumn.leads.push(lead)
                }
            }

            await nextTick()
            showStageChangeModal.value = true
            return
        }

        if (isAssignToFollowUpOrContacted) {
            pendingStageChange.value = {
                leadId: lead.id,
                targetStageId: newStageId,
                targetStageName: column.title,
                targetStageOrder: newStageOrder,
                originalStageId: lead.stage_id,
                leadData: { ...lead },
                isConversion: false,
                interactionMode: true
            }
            missingFieldsForLead.value = []

            if (sourceColumn) {
                const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
                if (targetColumnIndex !== -1) {
                    columns.value[targetColumnIndex].leads =
                        columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
                }
                if (!sourceColumn.leads.find(l => l.id === lead.id)) {
                    sourceColumn.leads.push(lead)
                }
            }

            await nextTick()
            showStageChangeModal.value = true
            return
        }

        // Check if this is the converted stage (order 6)
        if (newStageOrder === 6) {
            const requiredFieldsForConversion = [
                'salutation',
                'property_type_id',
                'area_id',
                'budget_from',
                'budget_to',
                'lead_type',
                'property_status',
                'lead_source',
                'purpose_buying',
                'bedrooms',
                'status_lead',
                'deal_name'
              
            ]
            
            const missingFields = requiredFieldsForConversion.filter(field => {
                const value = lead[field]
                return !value || value === '' || value === null || value === undefined
            })
            
            console.log('Conversion - Missing fields:', missingFields)
            
            if (missingFields.length > 0) {
                console.log('Showing modal to complete missing fields for conversion')
                
                pendingStageChange.value = {
                    leadId: lead.id,
                    targetStageId: newStageId,
                    targetStageName: column.title,
                    targetStageOrder: newStageOrder,
                    originalStageId: lead.stage_id,
                    leadData: { ...lead },
                    isConversion: true
                }
                
                missingFieldsForLead.value = missingFields
                
                // إرجاع الـ lead للمرحلة السابقة
                const sourceColumn = columns.value.find(c => c.status === lead.stage_id)
                if (sourceColumn) {
                    const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
                    if (targetColumnIndex !== -1) {
                        columns.value[targetColumnIndex].leads =
                            columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
                    }
                    
                    if (!sourceColumn.leads.find(l => l.id === lead.id)) {
                        sourceColumn.leads.push(lead)
                    }
                }
                
                await nextTick()
                showStageChangeModal.value = true
                return
            }
            
            // جميع البيانات موجودة، انتقل إلى التحويل
            console.log('All data complete, showing conversion modal')
            selectedLeadForConversion.value = lead?.id || lead?.lead_id || null
            selectedLeadData.value = lead
            
            const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
            if (targetColumnIndex !== -1) {
                columns.value[targetColumnIndex].leads = 
                    columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
            }
            
            await nextTick()
            if (convertModalRef.value) {
                convertModalRef.value.show(selectedLeadForConversion.value, selectedLeadData.value)
            }
            return
        }

        const requiredFieldsMap = {
            3: ['salutation'],
            4: ['salutation','property_type_id', 'area_id','budget_from',
                'budget_to',
                'lead_type',
                'property_status', 'purpose_buying', 'bedrooms', 'status_lead'],
            5: ['salutation','available_date'],
            7: ['salutation','branch'],
            8: ['why_lost_lead'],
            9: ['status_lead'],
            10: ['status_lead']
        }
        
        const alwaysRequiredFieldsMap = {
            9: ['status_lead'],
            10: ['status_lead']
        }
        
        const requiredFields = requiredFieldsMap[newStageOrder] || []
        const alwaysFields = alwaysRequiredFieldsMap[newStageOrder] || []
        
        const leadMissingFields = requiredFields.filter(f => !lead[f])
        
        const fieldsToShow = [...new Set([...leadMissingFields, ...alwaysFields])]
        if (newStageOrder === 9 || newStageOrder === 10) {
                if (!fieldsToShow.includes('status_lead')) {
                    fieldsToShow.push('status_lead')
                }
            }
        if (fieldsToShow.length > 0 || [3,4,5,7,8,9,10].includes(newStageOrder)) {
            console.log('Showing modal for stage order:', newStageOrder, 'Missing fields:', leadMissingFields)
            
            pendingStageChange.value = {
                leadId: lead.id,
                targetStageId: newStageId,
                targetStageName: column.title,
                targetStageOrder: newStageOrder,
                originalStageId: lead.stage_id,
                leadData: { ...lead },
                isConversion: false
            }
            
            missingFieldsForLead.value = fieldsToShow
            
            const sourceColumn = columns.value.find(c => c.status === lead.stage_id)
            if (sourceColumn) {
                const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
                if (targetColumnIndex !== -1) {
                    columns.value[targetColumnIndex].leads =
                        columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
                }
                
                if (!sourceColumn.leads.find(l => l.id === lead.id)) {
                    sourceColumn.leads.push(lead)
                }
            }
            
            await nextTick()
            showStageChangeModal.value = true
            return
        }
        
        await moveLeadWithStageChange(lead, newStageId)
    }
}

async function applyProgrammaticStageChange(lead, targetColumn) {
    const newStageId = targetColumn.status
    if (lead.stage_id === newStageId) return
    const sourceCol = columns.value.find(c => c.status === lead.stage_id)
    if (!sourceCol) return
    const idx = sourceCol.leads.findIndex(l => l.id === lead.id)
    if (idx === -1) return
    const [moved] = sourceCol.leads.splice(idx, 1)
    targetColumn.leads.push(moved)
    await nextTick()
    await onLeadDragChange({ added: { element: moved } }, targetColumn)
}

async function moveLeadWithStageChange(lead, newStageId) {
    try {
        await api.post(`/leads/${lead.id}/change-stage`, {
            stage_id: newStageId
        })
        lead.stage_id = newStageId
        columns.value.forEach((col) => {
            col.leads = col.leads.filter((l) => l.id !== lead.id)
        })
        const targetCol = columns.value.find((c) => c.status === newStageId)
        if (targetCol && !targetCol.leads.some((l) => l.id === lead.id)) {
            targetCol.leads.push(lead)
        }
    } catch (error) {
        // Revert the UI change if API fails - only refetch if not already fetching
        if (!isFetching.value) {
            await fetchLeads(true) // Immediate refetch on error
        }
        $showNotification('Failed to move lead', 'error')
    }
}



async function handleStageChangeWithReason({ leadId, targetStageId, reason, ...additionalData }) {
    console.log('handleStageChangeWithReason called:', { leadId, targetStageId, reason, additionalData })
    
    try {
        const lead = pendingStageChange.value?.leadData
        if (!lead) {
            console.error('No lead data found')
            return
        }

        const isConversion = pendingStageChange.value?.isConversion || false
        const targetStageOrder = pendingStageChange.value?.targetStageOrder || 0

        // Prepare payload with correct field names for backend
        const payload = {
            stage_id: targetStageId,
            reason: reason || null,
        }
        
        if (additionalData.salutation) payload.salutation = additionalData.salutation
        if (additionalData.budget_from) payload.budget_from = additionalData.budget_from
        if (additionalData.budget_to) payload.budget_to = additionalData.budget_to
        if (additionalData.property_status) payload.property_status = additionalData.property_status
        if (additionalData.lead_type) payload.lead_type = additionalData.lead_type
        if (additionalData.area_id) payload.area_id = additionalData.area_id
        if (additionalData.property_type_id) payload.property_type_id = additionalData.property_type_id
        if (additionalData.bedrooms) payload.bedrooms = additionalData.bedrooms
        if (additionalData.purpose_buying) payload.purpose_buying = additionalData.purpose_buying
        if (additionalData.lead_source) payload.lead_source = additionalData.lead_source
        if (additionalData.available_date) payload.available_date = additionalData.available_date
        if (additionalData.branch) payload.branch = additionalData.branch
        if (additionalData.lost_reason) payload.why_lost_lead = additionalData.lost_reason
        if (additionalData.interaction_result) payload.interaction_result = additionalData.interaction_result
        if (additionalData.deal_name) payload.deal_name = additionalData.deal_name
        
        if (additionalData.lead_status) {
            if (targetStageOrder === 4 || (isConversion && targetStageOrder === 6)) {
                payload.status_lead = additionalData.lead_status
            }
            else if (targetStageOrder === 9) {
                payload.status_lead_pool = additionalData.lead_status
            }
            else if (targetStageOrder === 10) {
                payload.unqualified_status = additionalData.lead_status
            }
        }

        console.log('Sending payload to backend:', payload)

        // Send request
        const response = await api.post(`/leads/${leadId}/change-stage`, payload)
        
        console.log('Backend response:', response.data)
        
        $showNotification(response.data?.message || 'Lead data updated successfully', 'success')
        
        // Update lead data locally
        if (lead) {
            lead.stage_id = targetStageId
            if (payload.salutation) lead.salutation = payload.salutation
            if (payload.budget_from) lead.budget_from = payload.budget_from
            if (payload.budget_to) lead.budget_to = payload.budget_to
            if (payload.lead_type) lead.lead_type = payload.lead_type
            if (payload.property_status) lead.property_status = payload.property_status
            if (payload.area_id) lead.area_id = payload.area_id
            if (payload.property_type_id) lead.property_type_id = payload.property_type_id
            if (payload.bedrooms) lead.bedrooms = payload.bedrooms
            if (payload.purpose_buying) lead.purpose_buying = payload.purpose_buying
            if (payload.lead_source) lead.lead_source = payload.lead_source
            if (payload.available_date) lead.available_date = payload.available_date
            if (payload.branch) lead.branch = payload.branch
            if (payload.why_lost_lead) lead.why_lost_lead = payload.why_lost_lead
            
            if (payload.status_lead) lead.status_lead = payload.status_lead
            if (payload.status_lead_pool) lead.status_lead = payload.status_lead_pool
            if (payload.unqualified_status) lead.status_lead = payload.unqualified_status
            if (payload.deal_name) lead.deal_name = payload.deal_name
        }
        
        // Close modal
        showStageChangeModal.value = false
        
        await nextTick()
        
        // If this was for conversion (stage 6), open conversion modal
        if (isConversion && targetStageOrder === 6) {
            console.log('Opening conversion modal')
            selectedLeadForConversion.value = lead?.id || lead?.lead_id || null
            selectedLeadData.value = lead
            
            await nextTick()
            if (convertModalRef.value) {
                convertModalRef.value.show(selectedLeadForConversion.value, selectedLeadData.value)
            }
        }
        
        clearPendingStageChange()
        await fetchLeads(true)
        
    } catch (error) {
        console.error('Error in handleStageChangeWithReason:', error)
        const errorMessage = error.response?.data?.message || 
                            error.response?.data?.error || 
                            'Failed to update lead data'
        $showNotification(errorMessage, 'error')
        throw error
    }
}

function clearPendingStageChange() {
    pendingStageChange.value = null
    missingFieldsForLead.value = []
    showStageChangeModal.value = false
}
watch(showStageChangeModal, (newVal) => {
    if (!newVal) {
        clearPendingStageChange()
    }
})

// Notification helper
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        // Fallback notification using Swal
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        })
        
        const iconMap = {
            'success': 'success',
            'error': 'error',
            'warning': 'warning',
            'info': 'info'
        }
        
        Toast.fire({
            icon: iconMap[type] || 'info',
            title: message
        })
    }
}
</script>


<style scoped>
/* Column content: visible when not scrollable (horizontal board scroll) */
.column-content {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.column-content::-webkit-scrollbar {
    display: none;
}

/* Vertical scroll inside each stage (fills column below header) */
.column-content-scrollable {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: hidden;
    min-height: calc(100dvh - 240px);
    height: calc(100dvh - 240px);
    display: flex;
    flex-direction: column;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    scrollbar-width: none;
    transition: scrollbar-color 0.2s ease;
}

.column-content-scrollable::-webkit-scrollbar {
    width: 0;
    transition: width 0.2s ease;
}

.column-content-scrollable::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 3px;
}

.column-content-scrollable::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 3px;
}

/* Show scrollbar when hovering the stage column */
.kanban-column:hover .column-content-scrollable {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar {
    width: 6px;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar-thumb {
    background: #cbd5e1;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.kanban-outer {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    height: calc(100dvh - 118px);
    min-height: 520px;
    padding-bottom: 12px;
    box-sizing: border-box;
}

.kanban-container {
    padding: 6px 4px 8px 4px;
    flex: 1;
    min-height: 0;
    height: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    width: 100%;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    position: relative;
}

/* Full-height hover zones: hover anywhere on the line to scroll */
.kanban-nav-zone {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    cursor: pointer;
}
.kanban-nav-zone-left {
    left: 0;
}
.kanban-nav-zone-right {
    right: 0;
}

/* Arrow style same as before: small semi-circular pill */
.kanban-nav-arrow {
    width: 36px;
    height: 72px;
    background: #ffffff5c;
    box-shadow: 2px 0 12px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.06);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: box-shadow 0.2s ease, background 0.2s ease;
    pointer-events: none;
}
.kanban-nav-zone:hover .kanban-nav-arrow {
    box-shadow: 3px 0 16px rgba(0, 0, 0, 0.1), 0 3px 12px rgba(0, 0, 0, 0.08);
}
.kanban-nav-arrow-icon {
    font-size: 24px;
    font-weight: 600;
    color: #0f172a;
}
.kanban-nav-arrow-left {
    border-radius: 0 36px 36px 0;
    padding-left: 4px;
}
.kanban-nav-arrow-right {
    border-radius: 36px 0 0 36px;
    padding-right: 4px;
}

/* Empty / loading / error states */
.kanban-empty-state {
    position: absolute;
    inset: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #64748B;
    text-align: center;
    padding: 24px;
}
.kanban-empty-icon {
    font-size: 48px;
    color: #94a3b8;
}
.kanban-empty-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #334155;
}
.kanban-empty-text {
    margin: 0;
    font-size: 14px;
    max-width: 360px;
}
.kanban-empty-btn {
    margin-top: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #334155;
    font-size: 14px;
    cursor: pointer;
}
.kanban-empty-btn:hover {
    background: #f8fafc;
}
.kanban-loading .kanban-empty-title {
    color: #64748B;
}
.kanban-empty-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: kanban-spin 0.8s linear infinite;
}
@keyframes kanban-spin {
    to { transform: rotate(360deg); }
}
.kanban-error-state .kanban-empty-icon {
    color: #ef4444;
}

.kanban-container::-webkit-scrollbar {
    height: 8px;
}

.kanban-container::-webkit-scrollbar-track {
    background: transparent;
}

.kanban-container::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}

.kanban-container::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}

.kanban-wrapper {
    height: 100%;
    width: max-content;
    min-width: 100%;
    min-height: 100%;
    display: flex !important;
    flex-wrap: nowrap !important;
    flex-shrink: 0;
    align-items: stretch !important;
}

.kanban-wrapper-tight {
    gap: 10px;
}

/* Equal-height stages; same full-height dashed border-left on each (not first) */
.kanban-column {
    position: relative;
    min-width: 247px;
    width: 247px;
    max-width: 247px;
    display: flex;
    flex-direction: column;
    background-color: transparent;
    border-radius: 12px;
    border: none;
    align-self: stretch;
    min-height: calc(100dvh - 200px);
    height: calc(100dvh - 200px);
    flex-shrink: 0;
    overflow: visible;
    box-sizing: border-box;
}

.kanban-column:not(:first-child) {
    border-left: 1px dashed rgba(255, 255, 255, 0.72);
}

.kanban-column > div {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 100%;
    height: 100%;
}

.kanban-column .card-body {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 100%;
    height: 100%;
}

.column-header {
    min-height: 36px;
    padding: 3px 8px 3px 10px;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    position: relative;
    overflow: visible;
    z-index: 1;
    clip-path: polygon(0 0, calc(100% - 7px) 0, 100% 50%, calc(100% - 7px) 100%, 0 100%);
}


.leads-icon {
    width: 11px;
    height: 11px;
    object-fit: contain;
}

.add-new-btn {
    height: 36px;
    transition: all 0.3s ease;
    border: 1px solid #E5E7EB !important;
    gap: 10px;
}

.add-new-btn .btn-text {
    width: 61px;
    height: 16px;
    font-family: 'Montserrat', sans-serif;
    font-weight: 500;
    font-size: 13px;
    line-height: 12px;
    letter-spacing: 0%;
    color: #0B0736;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.add-new-btn:hover {
    background-color: #f8f9fa !important;
    border-color: #d1d5db !important;
}

.kanban-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    color: #1e293b;
    border-width: 1px !important;
    border-style: solid !important;
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}

.lead-priority-hot-border {
    border-color: #ef4444 !important;
}

.lead-priority-warm-border {
    border-color: #f59e0b !important;
}

.lead-priority-cold-border {
    border-color: #9ca3af !important;
}

.lead-intelligence-row {
    gap: 8px;
}

.lead-priority-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.4;
}

.lead-priority-hot {
    background: #fee2e2;
    color: #991b1b;
}

.lead-priority-warm {
    background: #fef3c7;
    color: #92400e;
}

.lead-priority-cold {
    background: #e5e7eb;
    color: #374151;
}

.lead-score-text {
    color: #64748b;
    font-size: 10px;
    font-weight: 600;
}

/* Ensure all card text is visible on white background (override parent/theme) */
.kanban-card .task-title,
.kanban-card .info-item,
.kanban-card .info-item span,
.kanban-card .date-info,
.kanban-card .date-info span,
.kanban-card .info-label,
.kanban-card .info-value {
    color: #1e293b !important;
}

.kanban-card .info-label {
    color: #64748b !important;
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-move {
    cursor: move;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}
.assignedBy .avatar-sm{
      width: 28px;
    height: 28px;
}

.person-hover-anchor {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.person-hover-clickable {
    cursor: pointer;
}

.person-hover-clickable:hover {
    text-decoration: underline;
}

.person-hover-card {
    position: absolute;
    top: calc(100% + 8px);
    left: -10px;
    width: 200px;
    z-index: 60;
    border-radius: 12px;
    border: 1px solid #dbe3ef;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.2);
    backdrop-filter: blur(8px);
    padding: 10px;
}

.person-hover-card-right {
    right: -10px;
    left: auto;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.person-hover-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}

.person-hover-avatar-fallback {
    background: #f1f5f9;
}

.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.person-hover-role {
    margin-top: 1px;
    font-size: 11px;
    color: #64748b;
    line-height: 1.2;
}

.person-hover-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 11px;
    padding: 4px 0;
    border-top: 1px dashed #e2e8f0;
}

.person-hover-line span {
    color: #64748b;
}

.person-hover-line b {
    color: #0f172a;
    font-weight: 700;
    text-align: right;
    max-width: 130px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.person-hover-pop-enter-active,
.person-hover-pop-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.person-hover-pop-enter-from,
.person-hover-pop-leave-to {
    opacity: 0;
    transform: translateY(4px) scale(0.98);
}

.info-label {
    color: #979797;
    font-weight: 500;
    font-style: Medium;
    font-size: 11px !important;

}

.info-value {
    font-weight: 500;
    font-size: 11px;
    line-height: 12px;
    color: #353535;
}

.border-neutral-200 {
    top: 233px;
    left: 12px;
    opacity: 1;
    border-width: 1px;

}

.tasks-list {
    min-height: 100%;
    height: 100%;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-family: Montserrat;
}

/* Draggable styles */
.ghost {
    opacity: 0.5;
    background: #c8ebfb;
}

.dragging {
    cursor: grabbing;
}

.task-title {
    font-family: Montserrat;
    font-weight: 700;
    font-style: Bold;
    font-size: 12px;
    line-height: 19px;
    letter-spacing: -0.25px;
    color: #0B0736;

    }

.task-header {
    align-items: center;
}

.task-header-badges {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    gap: 6px;
    white-space: nowrap;
}

.lead-blacklist-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 3px 8px;
    border-radius: 999px;
    font-family: Montserrat, system-ui, sans-serif;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    line-height: 1;
    white-space: nowrap;
    color: #fff;
    background: linear-gradient(135deg, #be6666 0%, #b66262 100%);
    border: 1px solid rgba(127, 29, 29, 0.35);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
}

.duplicate-badge {
    flex-shrink: 0;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease;
}

.duplicate-badge:hover {
    opacity: 0.7;
}

.duplicate-badge.cursor-pointer {
    cursor: pointer;
}

.duplicate-icon-wrapper {
    position: relative;
    width: 24px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.duplicate-rectangle {
    position: absolute;
    width: 20px;
    height: 24px;
    background-color: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.duplicate-rectangle-back {
    top: 4px;
    left: 4px;
    z-index: 1;
}

.duplicate-rectangle-front {
    top: 0;
    left: 0;
    z-index: 2;
}

.duplicate-number {
    font-family: Montserrat;
    font-weight: 600;
    font-size: 11px;
    line-height: 1;
    color: #0B0736;
    display: flex;
    align-items: center;
    justify-content: center;
}

.date-info {
    font-family: Montserrat;
    font-weight: 500;
    font-style: Medium;
    font-size: 10px;
    line-height: 9px;
    letter-spacing: 0%;
    color: #64748b;
}

.date-info span {
    color: #1e293b;
}

.header-title {
    font-weight: 600;
    font-style: SemiBold;
    font-size: 11px;
    line-height: 1.1;
    color: #0B0736;
    margin: 0;
}

.header-title-wrapper {
    cursor: pointer;
    flex: 1;
    display: flex;
    align-items: center;
}

.header-title-wrapper:hover .header-title {
    text-decoration: underline;
}

.header-title-input {
    font-weight: 600;
    font-style: SemiBold;
    font-size: 13px;
    color: #0B0736;
    /* background: rgba(255, 255, 255, 0.2); */
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 4px;
    padding: 2px 6px;
    outline: none;
    flex: 1;
    min-width: 0;
}

.header-title-input:focus {
    /* background: rgba(255, 255, 255, 0.3); */
    border-color: rgba(255, 255, 255, 0.6);
}

.stage-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}

.stage-modal {
    background: white;
    padding: 24px;
    border-radius: 12px;
    width: 400px;
}
.leads-count-badge {
    font-size: 11px;
    line-height: 1.1;
    color: rgba(1, 6, 44, 0.45);
    margin-left: 3px;
    font-weight: 600 !important;
}

/* —— Mobile Kanban (injected kanbanIsMobile) —— */
.kanban-outer--mobile {
    height: auto;
    min-height: 0;
    padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px));
    overflow-x: hidden;
    max-width: 100vw;
    box-sizing: border-box;
}

.kanban-outer--mobile .lead-analytics-row {
    flex-shrink: 0;
}

/* duplicate selector block below may exist — ensure analytics doesn't steal column height */
.kanban-outer > .lead-analytics-row {
    padding-left: 4px;
    padding-right: 4px;
    flex-shrink: 0;
}

.kanban-outer--mobile .kanban-nav-zone {
    display: none;
}

.kanban-outer--mobile .kanban-container {
    overflow-x: hidden;
    overflow-y: auto;
    height: auto;
    min-height: 50vh;
    padding: 8px 10px 24px;
    -webkit-overflow-scrolling: touch;
}

.kanban-outer--mobile .kanban-wrapper {
    flex-direction: column;
    width: 100%;
    min-width: 0;
    height: auto;
    gap: 14px;
}

.kanban-outer--mobile .kanban-column {
    width: 100%;
    min-width: 0;
    max-width: none;
    border-left: none;
    height: auto;
    max-height: none;
}

.kanban-outer--mobile .column-content-scrollable {
    max-height: min(70vh, 520px);
}

.kanban-outer--mobile .column-header--mobile {
    min-height: 34px;
    padding: 4px 8px;
    clip-path: none;
    border-radius: 14px 14px 0 0;
}

.kanban-outer--mobile .column-header__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fff;
    flex-shrink: 0;
}

.kanban-outer--mobile .column-header--mobile .header-title,
.kanban-outer--mobile .column-header--mobile .leads-count-badge {
    color: #000 !important;
    font-weight: 700 !important;
}

.kanban-outer--mobile .column-header--mobile .header-title-input {
    color: #fff;
    border-color: rgba(255, 255, 255, 0.5);
}

.kanban-outer--mobile .column-header__actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.kanban-outer--mobile .column-header__icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.65);
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.kanban-outer--mobile .kanban-card--mobile {
    border-radius: 14px !important;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08) !important;
    touch-action: pan-y;
    -webkit-user-select: none;
    user-select: none;
}

.mobile-current-stage-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 6px 8px 8px;
    padding: 10px 12px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #eef2f7;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
    cursor: pointer;
    user-select: none;
}

.mobile-current-stage-bar__icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    font-size: 15px;
}

.mobile-current-stage-bar__text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.mobile-current-stage-bar__label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
    line-height: 1.1;
}

.mobile-current-stage-bar__value {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mobile-kanban-overlay {
    position: fixed;
    inset: 0;
    z-index: 100100;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}

.mobile-kanban-overlay--quick {
    padding-bottom: calc(58px + env(safe-area-inset-bottom, 0px));
}

.mobile-kanban-overlay--tall .mobile-kanban-sheet {
    max-height: min(88dvh, 760px);
}

.mobile-kanban-sheet {
    width: 100%;
    background: #fff;
    border-radius: 22px 22px 0 0;
    padding: 16px 16px calc(20px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -8px 40px rgba(15, 23, 42, 0.12);
    animation: mobile-sheet-up 0.22s ease-out;
}

@keyframes mobile-sheet-up {
    from {
        transform: translateY(100%);
        opacity: 0.85;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.mobile-kanban-sheet--quick {
    max-height: 42vh;
}

.mobile-kanban-sheet__close {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 50%;
    background: #f1f5f9;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.mobile-kanban-sheet--quick {
    position: relative;
    padding-top: 40px;
}

.mobile-kanban-sheet__hint {
    font-size: 13px;
    color: #94a3b8;
    margin: 0 0 14px;
    font-weight: 600;
}

.mobile-kanban-stage-pair {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.mobile-kanban-stage-pair--compact {
    margin-bottom: 0;
}

.mobile-kanban-stage-pair__sep {
    color: #94a3b8;
    display: flex;
    font-size: 18px;
}

.mobile-kanban-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    border: none;
    max-width: 42%;
    text-align: center;
    line-height: 1.2;
}

.mobile-kanban-pill--pick {
    background: #fff;
    border: 2px solid #f59e0b;
    color: #0f172a;
    cursor: pointer;
}

.mobile-kanban-link-btn {
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: #f8fafc;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: #2563eb;
    cursor: pointer;
}

.mobile-kanban-quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.mobile-kanban-quick-btn {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    min-height: 38px;
    font-size: 12px;
    font-weight: 600;
    background: #fff;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1.2;
}

.mobile-kanban-quick-btn--assign,
.mobile-kanban-quick-btn--stage {
    border-color: #cbd5e1;
    background: #f8fafc;
    color: #0f172a;
}

.mobile-kanban-quick-btn--view {
    border-color: #bae6fd;
    background: #f0f9ff;
    color: #0c4a6e;
}

.mobile-kanban-quick-btn--cancel {
    border-color: #e2e8f0;
    background: #ffffff;
    color: #475569;
}

.mobile-kanban-sheet__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    padding-right: 40px;
}

.mobile-kanban-sheet__title {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.mobile-kanban-stage-list {
    max-height: min(48vh, 400px);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 12px;
}

.mobile-kanban-stage-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    cursor: pointer;
}

.mobile-kanban-stage-row__radio {
    width: 20px;
    height: 20px;
    accent-color: #f59e0b;
    flex-shrink: 0;
}

.mobile-kanban-stage-row__pill {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 46px;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

.mobile-kanban-stage-row__pill--all {
    background: linear-gradient(90deg, #e2e8f0, #cbd5e1);
}

.mobile-kanban-stage-row__pill-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.95);
    flex-shrink: 0;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.mobile-kanban-stage-row__pill-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mobile-kanban-preview-box {
    background: #f8fafc;
    border-radius: 14px;
    padding: 12px;
    margin-bottom: 12px;
}

.mobile-kanban-preview-box__label {
    font-size: 11px;
    color: #94a3b8;
    margin: 0 0 8px;
    font-weight: 600;
}

.mobile-kanban-sheet__footer {
    display: flex;
    gap: 10px;
}

.mobile-kanban-btn {
    flex: 1;
    padding: 14px 16px;
    border: none;
    border-radius: 999px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.mobile-kanban-btn--muted {
    background: #e2e8f0;
    color: #0f172a;
}

.mobile-kanban-btn--dark {
    background: #0f172a;
    color: #fff;
}

.mobile-kanban-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.kanban-outer--mobile .mobile-stage-carousel-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-top: 6px;
    margin-bottom: 2px;
}

.kanban-outer--mobile .mobile-stage-carousel-dots {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex: 1;
}

.kanban-outer--mobile .mobile-stage-carousel-dot {
    width: 8px;
    height: 8px;
    border: none;
    border-radius: 50%;
    background: #d1d5db;
    cursor: pointer;
    padding: 0;
}

.kanban-outer--mobile .mobile-stage-carousel-dot.is-active {
    width: 20px;
    border-radius: 999px;
    background: #f59e0b;
}

.mobile-create-new-fixed {
    position: fixed;
    left: 50%;
    bottom: calc(72px + env(safe-area-inset-bottom, 0px));
    transform: translateX(-50%);
    z-index: 10020;
    height: 46px;
    min-width: 164px;
    padding: 0 20px 0 12px;
    border: 1px solid #eef2f7;
    border-radius: 999px;
    background: #ffffff;
    box-shadow: 0 10px 28px rgba(2, 6, 23, 0.12);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.mobile-create-new-fixed__plus {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    background: #fff;
    border: 2px solid #f59e0b;
    color: #f59e0b;
    font-size: 18px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.mobile-create-new-fixed__text {
    color: #0f172a;
    font-size: 16px;
    line-height: 1;
    font-weight: 600;
}
</style>
