<template>
    <div class="kanban-outer" :class="{ 'kanban-outer--mobile': kanbanIsMobile, 'kanban-outer--mobile-stage': kanbanIsMobile && mobileListFilterStageId !== MOBILE_FILTER_ALL, 'kanban-outer--searching': showKanbanSearchOverlay, 'kanban-outer--list': boardView === 'list', 'is-lead-dragging': isLeadDragging }">
        <div
            v-if="showKanbanSearchOverlay"
            class="kanban-search-overlay"
            role="status"
            aria-live="polite"
            aria-label="Searching leads"
        >
            <div class="kanban-search-overlay__beam" aria-hidden="true"></div>
            <div class="kanban-search-overlay__card">
                <span class="kanban-search-overlay__ring" aria-hidden="true"></span>
                <iconify-icon icon="lucide:search" class="kanban-search-overlay__icon" aria-hidden="true" />
                <span class="kanban-search-overlay__text">Searching leads</span>
                <span class="kanban-search-overlay__dots" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            </div>
            <div class="kanban-search-overlay__shimmer" aria-hidden="true"></div>
        </div>
        <div
            v-if="showNoSearchResults"
            class="kanban-no-results-overlay"
            role="status"
            aria-live="polite"
        >
            <p class="kanban-no-results-overlay__text">There are currently no data on this page</p>
        </div>
        <div 
            v-if="showRevertAlert" 
            class="revert-alert-wrapper"
            :class="{ 'revert-alert-visible': showRevertAlert }"
        >
            <div class="revert-alert">
                <!-- المحتوى الموجود -->
                <div class="revert-alert-icon-wrap">
                    <iconify-icon icon="lucide:clock-alert" class="revert-alert-icon" />
                </div>
                <div class="revert-alert-content">
                    <div class="revert-alert-header">
                        <span class="revert-alert-title">⚠️ Revert Warning</span>
                        <span class="revert-alert-time">{{ revertAlertTimeLeft }}</span>
                    </div>
                    <p class="revert-alert-message">
                        Lead <strong>{{ revertAlertLead }}</strong> will be reverted to 
                        <strong>{{ revertAlertTarget }}</strong>
                    </p>
                </div>
                <button 
                    type="button" 
                    class="revert-alert-close"
                    @click="closeRevertAlert"
                    aria-label="Close alert"
                >
                    <iconify-icon icon="lucide:x" />
                </button>
                <!-- ✅ Progress Bar -->
                <div class="revert-alert-progress"></div>
            </div>
        </div>
        <div class="board-head">
            <!-- Mobile: one tab per stage (no "All leads"), each outlined in its stage colour;
                 the active tab is filled with that colour. -->
            <div v-if="kanbanIsMobile" class="mobile-stage-carousel" role="tablist" aria-label="Lead stages">
                <button
                    v-for="col in columns"
                    :key="'stage-pill-' + col.status"
                    type="button"
                    class="mobile-stage-carousel__pill"
                    role="tab"
                    :class="{ 'is-active': String(mobileListFilterStageId) === String(col.status) }"
                    :style="mobileStagePillStyle(col)"
                    :aria-selected="String(mobileListFilterStageId) === String(col.status) ? 'true' : 'false'"
                    @click="selectMobileStage(col.status, $event)"
                >
                    {{ col.title }} ({{ stageCountForColumn(col) }})
                </button>
            </div>

            <LeadAnalyticsShortcuts
                v-if="!kanbanIsMobile"
                :metrics="leadAnalyticsMetrics"
                :active-filter="activeShortcutFilter"
                @toggle-filter="onShortcutFilterToggle"
            />
            <Teleport v-else-if="mobileSearchChipsHost" to="#lead-search-mobile-chips">
                <LeadAnalyticsShortcuts
                    :metrics="leadAnalyticsMetrics"
                    :active-filter="activeShortcutFilter"
                    @toggle-filter="onShortcutFilterToggle"
                />
            </Teleport>

            <div class="board-view-switch" role="tablist" aria-label="Lead layout">
                <button
                    type="button"
                    class="board-view-switch__btn"
                    role="tab"
                    :aria-selected="boardView === 'kanban'"
                    :class="{ 'is-active': boardView === 'kanban' }"
                    @click="setBoardView('kanban')"
                >
                    <iconify-icon icon="lucide:columns-3" aria-hidden="true" />
                    <span class="board-view-switch__label">Kanban</span>
                </button>
                <button
                    type="button"
                    class="board-view-switch__btn"
                    role="tab"
                    :aria-selected="boardView === 'list'"
                    :class="{ 'is-active': boardView === 'list' }"
                    @click="setBoardView('list')"
                >
                    <iconify-icon icon="lucide:list" aria-hidden="true" />
                    <span class="board-view-switch__label">List</span>
                </button>
            </div>
        </div>

        <Transition name="lead-select-bar">
            <div
                v-if="isAdminOrSuperAdmin && leadSelectionActive"
                class="lead-select-bar"
                role="toolbar"
                aria-label="Selected leads"
            >
                <div class="lead-select-bar__cluster">
                    <span class="lead-select-bar__count-label">Selected</span>
                    <strong>{{ selectedLeadIds.length }}</strong>
                    <button type="button" class="lead-select-bar__clear" aria-label="Clear selection" @click="clearLeadSelection">
                        <iconify-icon icon="lucide:x" />
                    </button>
                    <span class="lead-select-bar__rule" aria-hidden="true"></span>
                    <button v-if="canDeleteLeads" type="button" class="lead-select-bar__btn lead-select-bar__btn--danger" :disabled="!selectedLeadIds.length || bulkActionBusy" @click="deleteSelectedLeads">
                        <iconify-icon icon="lucide:trash-2" />
                        Delete
                    </button>
                    <button type="button" class="lead-select-bar__btn" :disabled="!selectedLeadIds.length || bulkActionBusy" @click="openBulkStagePicker">
                        <iconify-icon icon="lucide:git-branch" />
                        Change stage
                    </button>
                    <button type="button" class="lead-select-bar__btn" :disabled="!selectedLeadIds.length || bulkActionBusy" @click="openBulkAssignPicker">
                        <iconify-icon icon="lucide:user-round" />
                        Assign responsible person
                    </button>
                </div>
            </div>
        </Transition>

        <div
            ref="kanbanContainerRef"
            class="kanban-container"
            :class="{ 'kanban-container--list': boardView === 'list' }"
            @scroll="onKanbanContainerScroll"
        >
        <!-- Error state -->
        <div v-if="error && columns.length === 0 && !loading" class="kanban-empty-state kanban-error-state">
            <iconify-icon icon="lucide:alert-circle" class="kanban-empty-icon"></iconify-icon>
            <p class="kanban-empty-title">Could not load stages</p>
            <p class="kanban-empty-text">{{ error }}</p>
            <button type="button" class="kanban-empty-btn" @click="fetchLeads(true)">Try again</button>
        </div>
        <div
            v-else-if="loading && columns.length === 0"
            class="kanban-board-skeleton"
            :class="boardView === 'list' ? 'kanban-board-skeleton--list' : 'kanban-wrapper kanban-wrapper-tight'"
            role="status"
            aria-live="polite"
            aria-label="Loading leads"
        >
            <template v-if="boardView === 'list'">
                <div v-for="n in 8" :key="'sk-row-' + n" class="kanban-skeleton-row"></div>
            </template>
            <template v-else>
                <div
                    v-for="(col, index) in skeletonColumns"
                    :key="'sk-col-' + index"
                    class="kanban-column"
                    :style="{ '--column-color': col.color }"
                >
                    <div class="p-0 overflow-visible shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <div
                                class="column-header d-flex align-items-center flex-shrink-0"
                                :class="{ 'column-header--mobile': kanbanIsMobile }"
                                :style="{ backgroundColor: col.color }"
                            >
                                <p v-if="col.title" class="header-title">{{ col.title }}</p>
                                <span v-else class="kanban-skeleton-title"></span>
                            </div>
                            <div class="column-content column-content-scrollable p-8 flex-grow-1 d-flex flex-column">
                                <div class="tasks-list flex-grow-1">
                                    <div v-for="n in 4" :key="'sk-card-' + index + '-' + n" class="kanban-skeleton-card"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <!-- No stages yet -->
        <div v-else-if="!loading && columns.length === 0" class="kanban-empty-state">
            <iconify-icon icon="lucide:columns-3" class="kanban-empty-icon"></iconify-icon>
            <p class="kanban-empty-title">No stages yet</p>
            <p class="kanban-empty-text">Use the menu above to add a new stage and start organizing your leads.</p>
        </div>
        <!-- Draggable Columns -->
        <draggable v-else-if="columns.length > 0 && boardView === 'kanban'" v-model="columns" item-key="status" class="kanban-wrapper kanban-wrapper-tight d-flex h-100" :group="'columns'"
            handle=".column-header"
            :disabled="kanbanIsMobile"
            :delay="200"
            :delay-on-touch-only="true"
            :ghost-class="'ghost'" :drag-class="'dragging'">
            <template #item="{ element: column }">
                <div
                    v-show="isColumnVisibleOnMobile(column)"
                    class="kanban-column radius-12 flex-column"
                    :data-stage-id="column.status"
                    :data-stage-name="column.title"
                    :data-stage-order="column.order"
                    :style="{ '--column-color': column.color }"
                >
                    <div class="p-0 overflow-visible shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <!-- Column Header -->
                            <div
                                class="column-header d-flex align-items-center justify-content-between flex-shrink-0"
                                :class="{ 'column-header--mobile': kanbanIsMobile, 'cursor-move': !kanbanIsMobile }"
                                :style="stageFlowStyle(column)"
                            >
                                <div class="d-flex align-items-center gap-2 column-header__title-block">
                                    <iconify-icon
                                        v-if="!kanbanIsMobile"
                                        :icon="stageFlowIcon(column.title)"
                                        class="column-header__icon"
                                        aria-hidden="true"
                                    />
                                    <span v-if="kanbanIsMobile" class="column-header__dot" aria-hidden="true" />
                                    <div v-if="editingStageId !== column.status" class="header-title-wrapper" @click="startEditingStage(column)">
                                        <p class="header-title">{{ column.title }}</p>
                                        <small class="leads-count-badge">
                                            {{ formatStageCount(stageCountForColumn(column)) }}
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
                                :ref="(el) => setColumnScrollEl(column.status, el)"
                                @scroll="(e) => onColumnScroll(column, e)"
                            >
                                <!-- Tasks -->
                                <draggable
                                    v-model="column.leads"
                                    :group="leadDragGroup"
                                    item-key="id"
                                    class="tasks-list flex-grow-1"
                                    :data-stage-id="column.status"
                                    :ghost-class="'ghost'"
                                    :drag-class="'dragging'"
                                    :chosen-class="'kanban-card-chosen'"
                                    :fallback-class="'kanban-card-fallback'"
                                    :disabled="leadCardDragDisabled"
                                    :move="canDragLeadToColumn"
                                    :force-fallback="true"
                                    :fallback-on-body="true"
                                    :fallback-tolerance="3"
                                    :fallback-offset="leadDragFallbackOffset"
                                    :animation="150"
                                    easing="cubic-bezier(0.2, 0, 0, 1)"
                                    :delay="140"
                                    :delay-on-touch-only="true"
                                    :touch-start-threshold="8"
                                    :empty-insert-threshold="48"
                                    :swap-threshold="0.65"
                                    direction="vertical"
                                    :scroll="true"
                                    :bubble-scroll="true"
                                    :scroll-sensitivity="72"
                                    :scroll-speed="12"
                                    :filter="'.lead-card-check, .duplicate-badge, .person-hover-anchor, a, button, input, textarea, select'"
                                    @start="onLeadDragStart"
                                    @end="onLeadDragEnd"
                                    @change="(evt) => onLeadDragChange(evt, column)"
                                >
                                    <template #item="{ element: task }">
                                            <div
                                                :key="task.id"
                                                class="kanban-card bg-white p-12 radius-12 mb-10 cursor-pointer"
                                                :data-lead-id="task.id"
                                                :class="{
                                                    'kanban-card--mobile': kanbanIsMobile,
                                                    'kanban-card--selected': isLeadSelected(task.id),
                                                    'kanban-card--motion-hold': motionHeld[task.id],
                                                }"
                                                v-show="leadMatchesShortcutFilter(task)"
                                                @touchstart="onMobileCardTouchStart(column, task, $event)"
                                                @touchmove="onMobileCardTouchMove(column, $event)"
                                                @touchend="onMobileCardTouchEnd(column, $event)"
                                                @touchcancel="clearMobileLongPress"
                                                @contextmenu="onMobileCardContextMenu"
                                                @pointermove="onLeadCardTilt"
                                                @pointerdown="onLeadCardPointerDown"
                                                @pointerup="onLeadCardTiltUp"
                                                @pointerleave="releaseLeadCardTilt($event.currentTarget)"
                                                @pointercancel="releaseLeadCardTilt($event.currentTarget)"
                                                @click="onLeadCardClick(task, column, $event)"
                                                @dblclick.stop.prevent="onLeadCardDblClick(task)"
                                            >
                                                <button
                                                    v-if="isAdminOrSuperAdmin && leadSelectionActive"
                                                    type="button"
                                                    class="lead-card-check"
                                                    :class="{ 'lead-card-check--on': isLeadSelected(task.id) }"
                                                    :aria-pressed="isLeadSelected(task.id)"
                                                    aria-label="Select lead"
                                                    @click.stop="toggleLeadSelection(task)"
                                                >
                                                    <iconify-icon :icon="isLeadSelected(task.id) ? 'lucide:check' : 'lucide:square'" />
                                                </button>
                                                <!-- Task Header - Lead Name + badges (single row) -->
                                                <div class="task-header d-flex align-items-center gap-2 mb-12 min-w-0">
                                                    <p class="task-title flex-grow-1 mb-0 min-w-0 text-truncate" :title="task.lead_name">{{ task.lead_name }}</p>
                                                    <div class="task-header-badges d-inline-flex align-items-center gap-1 flex-shrink-0">
                                                        <span
                                                            v-if="task.has_service_duplicate"
                                                            class="lead-blacklist-badge"
                                                            title="Blacklist"
                                                        >
                                                           Blacklisted
                                                        </span>
                                                        <div
                                                            v-if="canSeeDuplicates && task.duplicate_no > 0"
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
                                                        <button
                                                            v-if="Number(task.merged_count) > 0"
                                                            type="button"
                                                            class="merged-flag"
                                                            aria-label="Show merged leads"
                                                            @click.stop="openMergedLeadsModal(task.id, $event.currentTarget)"
                                                            @pointerenter="mergedHoverOpen(task.id, $event)"
                                                            @pointerleave="mergedHoverLeave"
                                                            @dblclick.stop
                                                            @pointerdown.stop
                                                        >
                                                            <iconify-icon icon="lucide:git-merge" />
                                                            <span>{{ task.merged_count }}</span>
                                                        </button>
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

                                                            <div v-else-if="field.key === 'api_first_question' && task.api_first_question" class="info-item info-item--span mb-8">
                                                                <div class="info-label text-secondary-light text-xs">More Information</div>
                                                                <div class="info-value">{{formatMaskedQuestion(task.api_first_question)}}</div>
                                                            </div>

                                                            
                                                            
                                                            <!-- Responsible Person -->
                                                            <div v-else-if="field.key === 'responsible_person' && hasResponsiblePerson(task)" class="responsible-info info-item--span d-flex align-items-center justify-content-between mb-12">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div
                                                                        class="person-hover-anchor"
                                                                        @mouseenter.stop="showPersonHoverCard(task, 'responsible', $event)"
                                                                        @mouseleave.stop="hidePersonHoverCard"
                                                                        @click.stop="openPersonProfile(task, 'responsible', $event)"
                                                                    >
                                                                        <img
                                                                            v-if="responsiblePersonAvatar(task)"
                                                                            :src="responsiblePersonAvatar(task)"
                                                                            alt=""
                                                                            class="avatar-sm rounded-circle"
                                                                        />
                                                                    </div>
                                                                    <div>
                                                                        <div
                                                                            class="info-value"
                                                                            @mouseenter.stop="showPersonHoverCard(task, 'responsible', $event)"
                                                                            @mouseleave.stop="hidePersonHoverCard"
                                                                            @click.stop="openPersonProfile(task, 'responsible', $event)"
                                                                        >{{ task.responsible_person?.name }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Activity date, plus the avatar of the last person who commented or logged an activity. Hidden when neither exists. -->
                                                            <div v-else-if="field.key === 'assigned_by' && hasAssignedBy(task)" class="lead-activity-row">
                                                                <hr class="mb-2 border-neutral-200">
                                                                <div class="mt-1 d-flex align-items-center justify-content-between assignedBy">
                                                                    <div class="info-item">
                                                                        <div class="info-label text-secondary-light text-xs mb-1">Activity</div>
                                                                        <div class="info-value">{{ formatActivityDate(task) }}</div>
                                                                    </div>
                                                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                                        <div
                                                                            v-if="showActivityPersonAvatar && activityPerson(task)"
                                                                            class="person-hover-anchor person-hover-clickable"
                                                                            :title="activityPerson(task)?.name || ''"
                                                                            @mouseenter.stop="showPersonHoverCard(task, 'activity', $event)"
                                                                            @mouseleave.stop="hidePersonHoverCard"
                                                                            @click.stop="openPersonProfile(task, 'activity', $event)"
                                                                        >
                                                                            <img
                                                                                :src="activityPersonAvatar(task)"
                                                                                :alt="activityPerson(task)?.name || ''"
                                                                                class="avatar-sm rounded-circle"
                                                                                @click.stop="openPersonProfile(task, 'activity', $event)"
                                                                            />
                                                                        </div>
                                                                        <LeadSourceMark :source="task.lead_source" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div v-else-if="hasDynamicFieldValue(task, field.key)" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">{{ field.label || field.key }}</div>
                                                                <div class="info-value">{{ getDynamicFieldDisplay(task, field.key) }}</div>
                                                            </div>
                                                          
                                                        </template>
                                                        <div
                                                            v-if="task.lead_source && !cardShowsActivityRow(column, task)"
                                                            class="lead-source-mark-row d-flex justify-content-end"
                                                        >
                                                            <LeadSourceMark :source="task.lead_source" />
                                                        </div>
                                                    </div>
                                            </div>
                                        </template>
                                </draggable>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>
        <div v-else-if="columns.length > 0 && boardView === 'list'" class="lead-list">
            <div class="lead-list__bar">
                <p class="lead-list__count">
                    <template v-if="listRows.length">{{ listRangeStart }}–{{ listRangeEnd }} of {{ listTotalLabel }}</template>
                    <template v-else>0 leads</template>
                </p>
                <div v-if="showListPager" class="lead-list-pager">
                    <button type="button" class="lead-list-pager__btn" :disabled="listPage <= 1 || listMoreLoading" @click="goListPrev">
                        Previous
                    </button>
                    <span class="lead-list-pager__status">Page {{ listPage }}<template v-if="!listHasMore"> of {{ listPageCount }}</template></span>
                    <button type="button" class="lead-list-pager__btn" :disabled="!canListNext || listMoreLoading" @click="goListNext">
                        {{ listMoreLoading ? 'Loading…' : 'Next' }}
                    </button>
                </div>
            </div>

            <div v-if="listRows.length === 0" class="lead-list-empty">
                No leads match this view.
            </div>

            <div v-else class="lead-list-table-shell">
            <div
                ref="listTableWrapRef"
                class="lead-list-table-wrap"
                @scroll="updateListScrollArrows"
            >
                <table class="lead-list-table">
                    <thead>
                        <tr>
                            <th v-if="showListCheckColumn" class="lead-list-table__check" scope="col" aria-label="Highlight"></th>
                            <th class="lead-list-col-num" scope="col">#</th>
                            <th class="lead-list-col-lead" scope="col">Lead</th>
                            <th class="lead-list-col-stage" scope="col">Stage</th>
                            <th class="lead-list-col-optional" scope="col">Activity</th>
                            <th class="lead-list-col-optional" scope="col">Name</th>
                            <th class="lead-list-col-created" scope="col">Created</th>
                            <th class="lead-list-col-person" scope="col">Responsible</th>
                            <th class="lead-list-col-phone" scope="col">Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in pagedListRows"
                            :key="'r-' + row.task.id"
                            :data-lead-id="row.task.id"
                            :style="{ '--column-color': row.column?.color }"
                            :class="{
                                'is-selected': isLeadSelected(row.task.id),
                                'is-highlighted': isLeadHighlighted(row.task.id),
                                'lead-row--motion-hold': motionHeld[row.task.id],
                            }"
                            @touchstart="onMobileCardTouchStart(row.column, row.task, $event)"
                            @touchmove="onMobileCardTouchMove(row.column, $event)"
                            @touchend="onMobileCardTouchEnd(row.column, $event)"
                            @touchcancel="clearMobileLongPress"
                            @contextmenu="onMobileCardContextMenu"
                            @click="onLeadCardClick(row.task, row.column, $event)"
                            @dblclick.stop.prevent="onLeadCardDblClick(row.task)"
                        >
                            <td v-if="showListCheckColumn" class="lead-list-table__check" @click.stop>
                                <button
                                    v-if="canHighlightLeads"
                                    type="button"
                                    class="lead-list-check lead-list-check--highlight"
                                    :class="{ 'is-on': isLeadHighlighted(row.task.id) }"
                                    :aria-pressed="isLeadHighlighted(row.task.id)"
                                    aria-label="Highlight lead"
                                    @click.stop="toggleLeadHighlight(row.task)"
                                >
                                    <iconify-icon :icon="isLeadHighlighted(row.task.id) ? 'lucide:check' : 'lucide:square'" />
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="lead-list-check"
                                    :class="{ 'is-on': isLeadSelected(row.task.id) }"
                                    :aria-pressed="isLeadSelected(row.task.id)"
                                    aria-label="Select lead"
                                    @click.stop="toggleLeadSelection(row.task)"
                                >
                                    <iconify-icon :icon="isLeadSelected(row.task.id) ? 'lucide:check' : 'lucide:square'" />
                                </button>
                            </td>
                            <td class="lead-list-col-num">{{ listRangeStart + index }}</td>
                            <td class="lead-list-col-lead">
                                <div class="lead-list-name">
                                    <div class="lead-list-name__row">
                                        <span class="lead-list-name__title" :title="row.task.lead_name">{{ row.task.lead_name || 'Untitled' }}</span>
                                        <span v-if="row.task.has_service_duplicate" class="lead-list-chip lead-list-chip--alert">Blacklisted</span>
                                        <button
                                            v-if="canSeeDuplicates && row.task.duplicate_no > 0"
                                            type="button"
                                            class="lead-list-chip lead-list-chip--dup"
                                            @click.stop="openDuplicateLeadsModal(row.task.id, $event)"
                                        >
                                            {{ row.task.duplicate_no }}
                                        </button>
                                        <button
                                            v-if="Number(row.task.merged_count) > 0"
                                            type="button"
                                            class="merged-flag merged-flag--sm"
                                            aria-label="Show merged leads"
                                            @click.stop="openMergedLeadsModal(row.task.id, $event.currentTarget)"
                                            @pointerenter="mergedHoverOpen(row.task.id, $event)"
                                            @pointerleave="mergedHoverLeave"
                                            @dblclick.stop
                                        >
                                            <iconify-icon icon="lucide:git-merge" />
                                            <span>{{ row.task.merged_count }}</span>
                                        </button>
                                    </div>
                                    <span v-if="row.task.lead_source || row.task.lead_branch_source" class="lead-list-name__source">
                                        {{ row.task.lead_source || row.task.lead_branch_source }}
                                    </span>
                                </div>
                            </td>
                            <td class="lead-list-col-stage">
                                <div class="lead-list-stage">
                                    <div class="lead-list-stage__track" aria-hidden="true">
                                        <span
                                            v-for="(col, segIndex) in columns"
                                            :key="'seg-' + row.task.id + '-' + col.status"
                                            class="lead-list-stage__seg"
                                            :style="{ background: segIndex <= listStageIndex(row.column) ? (row.column.color || '#64748b') : '#e8eef6' }"
                                        />
                                    </div>
                                    <span class="lead-list-stage__name">{{ row.column.title }}</span>
                                </div>
                            </td>
                            <td class="lead-list-col-optional">
                                <span class="lead-list-clip" :class="listActivityLabel(row.task) ? 'lead-list-value' : 'lead-list-muted'" :title="formatDate(activityDisplayAt(row.task))">
                                    {{ listActivityLabel(row.task) || 'No activities' }}
                                </span>
                            </td>
                            <td class="lead-list-col-optional">
                                <span class="lead-list-clip lead-list-value" :title="listLeadFullName(row.task)">{{ listLeadFullName(row.task) }}</span>
                            </td>
                            <td class="lead-list-col-created">
                                <span class="lead-list-clip lead-list-value" :title="formatDate(row.task.created_at)">{{ formatListCreated(row.task.created_at) }}</span>
                            </td>
                            <td class="lead-list-col-person">
                                <div
                                    v-if="hasResponsiblePerson(row.task)"
                                    class="lead-list-person"
                                    @click.stop="openPersonProfile(row.task, 'responsible', $event)"
                                    @mouseenter.stop="showPersonHoverCard(row.task, 'responsible', $event)"
                                    @mouseleave.stop="hidePersonHoverCard"
                                >
                                    <img :src="responsiblePersonAvatar(row.task)" alt="" />
                                    <span>{{ row.task.responsible_person?.name }}</span>
                                </div>
                                <span v-else class="lead-list-muted">—</span>
                            </td>
                            <td class="lead-list-col-phone">
                                <span class="lead-list-clip lead-list-value lead-list-value--phone">{{ listPhone(row.task) || '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-show="showListLeftZone"
                class="lead-list-nav lead-list-nav--left"
                title="Move left"
                aria-label="Move left"
                @mouseenter="startListScrollLeft"
                @mouseleave="stopListScroll"
            >
                <span class="kanban-nav-arrow kanban-nav-arrow-left">
                    <iconify-icon icon="lucide:chevron-left" class="kanban-nav-arrow-icon" />
                </span>
            </div>
            <div
                v-show="showListRightZone"
                class="lead-list-nav lead-list-nav--right"
                title="Move right"
                aria-label="Move right"
                @mouseenter="startListScrollRight"
                @mouseleave="stopListScroll"
            >
                <span class="kanban-nav-arrow kanban-nav-arrow-right">
                    <iconify-icon icon="lucide:chevron-right" class="kanban-nav-arrow-icon" />
                </span>
            </div>
            </div>

        </div>
        </div>
        <template v-if="!loading && !error && columns.length > 0 && boardView === 'kanban'">
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

    <!-- Mobile: long-press or double-tap delete, for roles that can delete leads -->
    <Teleport to="body">
        <div
            v-if="kanbanIsMobile && showMobileDeleteSheet && canDeleteLeads"
            class="mobile-kanban-overlay mobile-kanban-overlay--quick"
            @click.self="closeMobileDeleteSheet"
        >
            <div class="mobile-kanban-sheet mobile-kanban-sheet--quick" @click.stop>
                <button type="button" class="mobile-kanban-sheet__close" aria-label="Close" @click="closeMobileDeleteSheet">
                    <iconify-icon icon="lucide:x" />
                </button>
                <p class="mobile-kanban-sheet__hint">{{ mobileDeleteLead?.lead_name || 'Lead' }}</p>
                <div class="mobile-kanban-quick-actions">
                    <button
                        type="button"
                        class="mobile-kanban-quick-btn mobile-kanban-quick-btn--danger"
                        :disabled="bulkActionBusy"
                        @click="confirmMobileDeleteLead"
                    >
                        <iconify-icon icon="lucide:trash-2" class="me-1" />
                        Delete
                    </button>
                    <button
                        type="button"
                        class="mobile-kanban-quick-btn mobile-kanban-quick-btn--cancel"
                        @click="closeMobileDeleteSheet"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

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
                    <h6 class="mobile-kanban-sheet__title">Select Stage</h6>
                    <button type="button" class="mobile-kanban-sheet__close" aria-label="Close" @click="closeMobileListFilterSheet">
                        <iconify-icon icon="lucide:x" />
                    </button>
                </div>
                <div class="mobile-stage-picker">
                    <button
                        type="button"
                        class="mobile-stage-chip"
                        :class="{ 'is-selected': mobileListFilterDraft === MOBILE_FILTER_ALL }"
                        @click="mobileListFilterDraft = MOBILE_FILTER_ALL"
                    >
                        <iconify-icon icon="lucide:layers" />
                        <span>All Stages</span>
                        <iconify-icon v-if="mobileListFilterDraft === MOBILE_FILTER_ALL" icon="lucide:check" class="mobile-stage-chip__check" />
                    </button>
                    <button
                        v-for="col in columns"
                        :key="'filter-' + col.status"
                        type="button"
                        class="mobile-stage-chip"
                        :class="{ 'is-selected': String(mobileListFilterDraft) === String(col.status) }"
                        @click="mobileListFilterDraft = String(col.status)"
                    >
                        <iconify-icon :icon="mobileStageIcon(col.title)" :style="{ color: stageTextOn(col.color) === '#1c1730' ? '#1c1730' : (col.color || 'var(--color-primary)') }" />
                        <span class="mobile-stage-chip__label">{{ col.title }}</span>
                        <iconify-icon v-if="String(mobileListFilterDraft) === String(col.status)" icon="lucide:check" class="mobile-stage-chip__check" />
                    </button>
                </div>
                <div class="mobile-kanban-sheet__footer">
                    <button type="button" class="mobile-stage-confirm" @click="confirmMobileListFilter">
                        Confirm
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
    <Teleport to="body">
        <transition name="person-hover-pop">
            <div
                v-if="activePersonHover?.data"
                class="person-hover-card person-hover-card--portal"
                :class="{ 'person-hover-card-right': activePersonHover.type === 'activity' }"
                :style="personHoverCardStyle"
                @mouseenter.stop="cancelPersonHoverHide"
                @mouseleave.stop="hidePersonHoverCard"
                @click.stop="openPersonProfileFromHover($event)"
            >
                <div class="person-hover-head">
                    <img
                        v-if="hoverCardPersonAvatar(activePersonHover.data)"
                        :src="hoverCardPersonAvatar(activePersonHover.data)"
                        alt=""
                        class="person-hover-avatar"
                    />
                    <div
                        v-else
                        class="person-hover-avatar person-hover-avatar-fallback d-flex align-items-center justify-content-center"
                    >
                        <iconify-icon icon="solar:user-bold" />
                    </div>
                    <div class="person-hover-head-text">
                        <div class="person-hover-name">{{ activePersonHover.data.name }}</div>
                        <div class="person-hover-role">{{ activePersonHover.data.position }}</div>
                    </div>
                </div>
                <div class="person-hover-meta">
                    <div class="person-hover-meta-item">
                        <span>Reports to</span>
                        <b v-if="activePersonHover.pending && activePersonHover.data.manager === 'Not specified'" class="person-hover-pending">Loading</b>
                        <b v-else>{{ activePersonHover.data.manager }}</b>
                    </div>
                    <div class="person-hover-meta-item">
                        <span>Branch</span>
                        <b v-if="activePersonHover.pending && activePersonHover.data.branch === 'Not specified'" class="person-hover-pending">Loading</b>
                        <b v-else>{{ activePersonHover.data.branch }}</b>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
    <!-- Duplicate Leads Dropdown -->
    <DuplicateLeadsModal 
        v-model="showDuplicateModal" 
        :leadId="selectedLeadForDuplicates"
        :triggerElement="currentTriggerElement"
        @view-lead="handleViewDuplicateLead"
        @merge="openMergeModal"
    />
    <MergedLeadsModal
        v-if="showMergedModal"
        v-model="showMergedModal"
        :lead-id="mergedModalLeadId"
        :trigger-element="mergedTrigger"
        @hover-enter="cancelMergedClose"
        @hover-leave="mergedHoverLeave"
    />
    <LeadMergeModal
        v-if="showMergeModal"
        v-model="showMergeModal"
        :leadId="mergeLeadId"
        @merged="onLeadsMerged"
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

    <div v-if="showBulkStagePicker" class="lead-bulk-overlay" @click.self="showBulkStagePicker = false">
        <div class="lead-bulk-panel" role="dialog" aria-label="Change stage">
            <header class="lead-bulk-panel__head">
                <span class="lead-bulk-panel__title">Change stage</span>
                <button type="button" class="lead-bulk-panel__close" aria-label="Close" @click="showBulkStagePicker = false">
                    <iconify-icon icon="lucide:x" />
                </button>
            </header>
            <p class="lead-bulk-panel__hint">{{ selectedLeadIds.length }} selected</p>
            <div class="lead-bulk-list">
                <button
                    v-for="column in columns"
                    :key="column.status"
                    type="button"
                    class="lead-bulk-option"
                    :disabled="bulkActionBusy"
                    @click="applyBulkStage(column)"
                >
                    <span class="lead-bulk-option__dot" :style="{ background: column.color }"></span>
                    <span>{{ column.title }}</span>
                </button>
            </div>
        </div>
    </div>

    <div v-if="showBulkAssignPicker" class="lead-bulk-overlay" @click.self="showBulkAssignPicker = false">
        <div class="lead-bulk-panel" role="dialog" aria-label="Assign responsible person">
            <header class="lead-bulk-panel__head">
                <span class="lead-bulk-panel__title">Assign person</span>
                <button type="button" class="lead-bulk-panel__close" aria-label="Close" @click="showBulkAssignPicker = false">
                    <iconify-icon icon="lucide:x" />
                </button>
            </header>
            <label class="lead-bulk-search">
                <iconify-icon icon="lucide:search" />
                <input v-model="bulkAssignQuery" type="search" placeholder="Search by name" />
            </label>
            <div class="lead-bulk-list">
                <p v-if="bulkAssignLoading" class="lead-bulk-panel__hint">Loading…</p>
                <button
                    v-for="person in visibleBulkAssignees"
                    :key="person.id"
                    type="button"
                    class="lead-bulk-option"
                    :disabled="bulkActionBusy"
                    @click="applyBulkAssign(person)"
                >
                    <span class="lead-bulk-option__avatar">{{ personInitial(person.name) }}</span>
                    <span class="lead-bulk-option__copy">
                        <strong>{{ person.name }}</strong>
                        <small v-if="person.branch_name">{{ person.branch_name }}</small>
                    </span>
                </button>
                <p v-if="!bulkAssignLoading && bulkAssignQuery.trim() && !visibleBulkAssignees.length" class="lead-bulk-panel__hint">No match. Try another name.</p>
                <p v-else-if="!bulkAssignLoading && !bulkAssignQuery.trim() && bulkAssignees.length > visibleBulkAssignees.length" class="lead-bulk-panel__hint">Search to see the rest.</p>
            </div>
        </div>
    </div>

    <div v-if="showLeadArchive" class="lead-bulk-overlay" @click.self="showLeadArchive = false">
        <div class="lead-bulk-panel lead-bulk-panel--wide" role="dialog" aria-label="Archived leads">
            <header class="lead-bulk-panel__head">
                <h3>Archived leads</h3>
                <button type="button" class="lead-select-bar__clear" aria-label="Close" @click="closeLeadArchive">
                    <iconify-icon icon="lucide:x" />
                </button>
            </header>
            <input v-model="archiveSearch" type="search" class="lead-bulk-search" placeholder="Search archived leads" @input="onArchiveSearchInput" />
            <div v-if="archiveDetail" class="lead-archive-detail">
                <button type="button" class="lead-select-bar__btn lead-select-bar__btn--ghost" @click="archiveDetail = null">
                    <iconify-icon icon="lucide:arrow-left" />
                    Back
                </button>
                <h4>{{ archiveDetail.lead_name || 'Lead' }}</h4>
                <p>Stage: {{ archiveDetail.stage?.name || '—' }}</p>
                <p>Responsible: {{ archiveDetail.responsible_person?.name || archiveDetail.responsiblePerson?.name || '—' }}</p>
                <p>Archived: {{ formatDate(archiveDetail.deleted_at) }}</p>
                <p>Phone: {{ archiveDetail.work_phone || '—' }}</p>
                <p>Email: {{ archiveDetail.email || '—' }}</p>
                <button type="button" class="lead-select-bar__btn lead-select-bar__btn--danger" :disabled="bulkActionBusy" @click="forceDeleteArchived(archiveDetail)">
                    <iconify-icon icon="lucide:trash-2" />
                    Delete forever
                </button>
            </div>
            <div v-else class="lead-bulk-list">
                <p v-if="archiveLoading" class="lead-bulk-panel__hint">Loading archive…</p>
                <article v-for="lead in archiveLeads" :key="lead.id" class="lead-archive-row">
                    <div>
                        <strong>{{ lead.lead_name || 'Untitled lead' }}</strong>
                        <span>{{ lead.stage?.name || 'No stage' }} · {{ formatDate(lead.deleted_at) }}</span>
                    </div>
                    <div class="lead-archive-row__actions">
                        <button type="button" class="lead-select-bar__btn" @click="openArchivedLead(lead)">Open</button>
                        <button type="button" class="lead-select-bar__btn lead-select-bar__btn--danger" :disabled="bulkActionBusy" @click="forceDeleteArchived(lead)">Delete forever</button>
                    </div>
                </article>
                <p v-if="!archiveLoading && !archiveLeads.length" class="lead-bulk-panel__hint">No archived leads.</p>
            </div>
            <footer v-if="!archiveDetail && archivePagination.last_page > 1" class="lead-archive-pager">
                <button type="button" class="lead-select-bar__btn" :disabled="archivePagination.current_page <= 1" @click="loadArchivedLeads(archivePagination.current_page - 1)">Previous</button>
                <span>{{ archivePagination.current_page }} / {{ archivePagination.last_page }}</span>
                <button type="button" class="lead-select-bar__btn" :disabled="archivePagination.current_page >= archivePagination.last_page" @click="loadArchivedLeads(archivePagination.current_page + 1)">Next</button>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick, inject, defineAsyncComponent } from 'vue'
import draggable from 'vuedraggable'
import { useRoute, useRouter } from 'vue-router'

import avatar1 from '@/assets/images/users/user1.png'
import leadsIcon from '@/assets/images/kanban/leads-icon.png'
import avatar2 from '@/assets/images/users/user2.png'
import LeadSourceMark from './LeadSourceMark.vue'
import LeadAnalyticsShortcuts from './LeadAnalyticsShortcuts.vue'
import StageChangeReasonModal from './StageChangeReasonModal.vue'
const DuplicateLeadsModal = defineAsyncComponent(() => import('./DuplicateLeadsModal.vue'))
const convertLeadModalLoader = () => import('./ConvertLeadModal.vue')
const ConvertLeadModal = defineAsyncComponent(convertLeadModalLoader)
const ProfilePopup = defineAsyncComponent(() => import('../shared/ProfilePopup.vue'))
const LeadMergeModal = defineAsyncComponent(() => import('./LeadMergeModal.vue'))
const MergedLeadsModal = defineAsyncComponent(() => import('./MergedLeadsModal.vue'))
import { useMergedLeadsPopover } from './composables/useMergedLeadsPopover.js'
convertLeadModalLoader()


import api, { getApiErrorMessage } from '@/plugins/axios'
import { markKanbanReady } from '@/composables/useKanbanReady.js'
import { peekLeadBoardPrefetchPromise, peekSettledLeadBoardPrefetch, takeLeadBoardPrefetch } from '@/composables/leadBoardPrefetch.js'
import { openLeadView, onLeadViewUpdated, closeLeadView, showLeadViewModal } from '@/composables/useLeadViewModal.js'
import { rememberLeadDetailOrigin } from '@/motion/leadDetailMotion'
import {
    clearLeadDragImage,
    mountLeadDragImage,
    onLeadCardTilt,
    onLeadCardTiltDown,
    onLeadCardTiltUp,
    releaseLeadCardTilt,
    settleLeadCard,
} from '@/motion/leadCardTilt'
import {
    armLeadMotion,
    beginConversionCelebration,
    beginLeadMove,
    cancelLeadMotion,
    consumeSupersededStage,
    destroyLeadMotion,
    isLatestLeadMove,
    motionHeld,
    noteLeadDragOrigin,
    playLeadMotion,
    refreshLeadMotionCard,
    rememberSupersededStage,
} from '@/motion/leadStageMotion'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl.js'
import { formatLeadBudgetRange } from '@/utils/budgetInput'
import { shouldSuppressLeadUpdateNotification, normalizeLeadRealtimeEvent, buildCrmLeadNotificationEvent } from '@/utils/leadRealtimeNotifications.js'
import Swal from 'sweetalert2'

// Import Bootstrap
import * as bootstrap from 'bootstrap'
const emit = defineEmits(['deal-created'])

const leadPoolRef = ref(null)

const route = useRoute()
const router = useRouter()

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

/** Last person who commented or logged an activity. Null hides the avatar. */
const activityPerson = (task) => task?.last_activity_user ?? null

/** Temporarily hide the Activity-row person photo (date + source icon stay). */
const showActivityPersonAvatar = false

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
const mobileSearchChipsHost = ref(false)

function onLeadSearchMobileSheet(event) {
    const open = !!event?.detail?.open && kanbanIsMobile.value
    if (!open) {
        mobileSearchChipsHost.value = false
        return
    }
    nextTick(() => {
        const host = document.getElementById('lead-search-mobile-chips')
        if (host) {
            mobileSearchChipsHost.value = true
            return
        }
        requestAnimationFrame(() => {
            mobileSearchChipsHost.value = !!document.getElementById('lead-search-mobile-chips')
        })
    })
}
const kanbanOpenCreateLead = inject('kanbanOpenCreateLead', null)

/** Mobile list filter: show all stacked stages or focus one column */
const MOBILE_FILTER_ALL = 'all'
const mobileListFilterStageId = ref(MOBILE_FILTER_ALL)
const mobileListFilterDraft = ref(MOBILE_FILTER_ALL)
const showMobileQuickSheet = ref(false)
const showMobileDeleteSheet = ref(false)
const mobileDeleteLead = ref(null)
const showMobilePickStageSheet = ref(false)
const showMobileListFilterSheet = ref(false)
const mobileQuickLead = ref(null)
const mobileQuickSourceColumn = ref(null)
const mobilePickStageId = ref(null)
const mobileTouchStartX = ref({})
const mobileTouchStartY = ref({})
const mobileTouchLastX = ref({})
const mobileTouchLastY = ref({})
const mobileSwipeMoved = ref({})

// Applied search params (from search modal, not from URL)
const appliedSearchParams = ref(null)

// Check if user is admin or super_admin (same pattern as header/index.vue) — branch_admin
// gets the same bulk-assign/bulk-stage tools, scoped to their branch by the backend.
const isAdminOrSuperAdmin = computed(() => {
    if (!user.value) return false
    return user.value.roles?.includes('super_admin') || user.value.roles?.includes('admin') || user.value.roles?.includes('branch_admin')
})

// Duplicate badge: super_admin, admin, branch_admin.
const canSeeDuplicates = computed(() => {
    if (!user.value) return false
    const roles = user.value.roles || []
    return roles.includes('super_admin')
        || roles.includes('admin')
        || roles.includes('branch_admin')
})

// Deleting leads is super_admin/admin only — branch_admin keeps the other bulk tools.
const canDeleteLeads = computed(() => {
    if (!user.value) return false
    return user.value.roles?.includes('super_admin') || user.value.roles?.includes('admin')
})

const leadSelectionActive = ref(false)
const selectedLeadIds = ref([])

// Visual mark on the list for every signed-in user. Stored on this browser.
// It does not change the lead and does not open the bulk-action bar.
const LEAD_HIGHLIGHT_KEY = 'lead_list_highlights_v1'
const canHighlightLeads = computed(() => !!user.value)

function highlightStorageKey() {
    const uid = user.value?.id ?? getUserFromStorage()?.id ?? 'anon'
    return `${LEAD_HIGHLIGHT_KEY}:${uid}`
}

function loadHighlightedLeadIds() {
    try {
        const raw = JSON.parse(localStorage.getItem(highlightStorageKey()) || '[]')
        if (!Array.isArray(raw)) return []
        return raw.map(Number).filter((id) => Number.isFinite(id) && id > 0).slice(0, 500)
    } catch {
        return []
    }
}

const highlightedLeadIds = ref(loadHighlightedLeadIds())

const showListCheckColumn = computed(() => {
    if (canHighlightLeads.value) return true
    return isAdminOrSuperAdmin.value && leadSelectionActive.value
})

function persistHighlightedLeadIds() {
    try {
        localStorage.setItem(highlightStorageKey(), JSON.stringify(highlightedLeadIds.value.slice(0, 500)))
    } catch {
        // Highlighting stays in memory if storage is unavailable.
    }
}

function isLeadHighlighted(id) {
    return highlightedLeadIds.value.includes(Number(id))
}

function toggleLeadHighlight(task) {
    const id = Number(task?.id)
    if (!id || !canHighlightLeads.value) return
    if (isLeadHighlighted(id)) {
        highlightedLeadIds.value = highlightedLeadIds.value.filter((item) => item !== id)
    } else {
        highlightedLeadIds.value = [id, ...highlightedLeadIds.value].slice(0, 500)
    }
    persistHighlightedLeadIds()
}

const bulkActionBusy = ref(false)
const showBulkStagePicker = ref(false)
const showBulkAssignPicker = ref(false)
const showLeadArchive = ref(false)
const bulkAssignQuery = ref('')
const bulkAssignees = ref([])
const bulkAssignLoading = ref(false)
const archiveSearch = ref('')
const archiveLeads = ref([])
const archiveLoading = ref(false)
const archiveDetail = ref(null)
const archivePagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
let leadCardClickTimer = null
let mobileLongPressTimer = null
let mobileLongPressFired = false
let suppressLeadClickUntil = 0
let mobileLastTap = { id: 0, at: 0 }
let archiveSearchTimer = null

function isLeadSelected(id) {
    return selectedLeadIds.value.includes(Number(id))
}

function toggleLeadSelection(task) {
    const id = Number(task?.id)
    if (!id) return
    leadSelectionActive.value = true
    if (isLeadSelected(id)) {
        selectedLeadIds.value = selectedLeadIds.value.filter((item) => item !== id)
        if (!selectedLeadIds.value.length) leadSelectionActive.value = false
        return
    }
    selectedLeadIds.value = [...selectedLeadIds.value, id]
}

function clearLeadSelection() {
    selectedLeadIds.value = []
    leadSelectionActive.value = false
}

function removeLeadsFromBoard(ids) {
    const list = (Array.isArray(ids) ? ids : []).map(Number).filter(Boolean)
    if (!list.length) return
    const drop = new Set(list)
    columns.value.forEach((column) => {
        const before = column.leads?.length || 0
        column.leads = (column.leads || []).filter((lead) => !drop.has(Number(lead.id)))
        const removed = before - column.leads.length
        const page = stagePagination.value[column.status]
        if (page && typeof page.total === 'number' && removed > 0) {
            page.total = Math.max(0, page.total - removed)
        }
    })
}

/**
 * Soft-merged leads stay in DB but leave active Kanban columns immediately.
 * Uses API meta from create / change-stage (and realtime create payloads).
 */
function applyAutoMergeVisibility(meta, currentLead = null) {
    const info = meta && typeof meta === 'object' ? meta : {}
    const mergedIds = info.auto_merged_lead_ids || []
    if (mergedIds.length) removeLeadsFromBoard(mergedIds)
    
    const mergedIntoId = info.auto_merge_primary_id != null ? Number(info.auto_merge_primary_id) : null
    if (mergedIds.length && mergedIntoId) {
        const primaryOnBoard = findLeadInColumns(mergedIntoId)
        if (primaryOnBoard) {
            primaryOnBoard.merged_count = (Number(primaryOnBoard.merged_count) || 0) + mergedIds.length
        }
    }
    if (info.lead_absorbed) {
        if (currentLead?.id) removeLeadsFromBoard([currentLead.id])
        return { absorbed: true, meta: info }
    }

    const primaryId = info.auto_merge_primary_id != null ? Number(info.auto_merge_primary_id) : null
    const primaryStageId = info.auto_merge_primary_stage_id != null
        ? Number(info.auto_merge_primary_stage_id)
        : null
    if (currentLead?.id && primaryId === Number(currentLead.id) && primaryStageId) {
        currentLead.stage_id = primaryStageId
        if (typeof settleLeadOnBoard === 'function') {
            settleLeadOnBoard(currentLead, primaryStageId)
        }
    }

    return { absorbed: false, meta: info }
}

async function archiveLeadIds(ids) {
    if (!canDeleteLeads.value || !ids.length || bulkActionBusy.value) return false
    const result = await Swal.fire({
        title: ids.length === 1 ? 'Archive this lead?' : `Archive ${ids.length} leads?`,
        text: 'They move to the archive. You can delete them forever from there.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
        cancelButtonText: 'Cancel',
    })
    if (!result.isConfirmed) return false
    bulkActionBusy.value = true
    const failed = []
    const failureReasons = new Set()
    for (const id of ids) {
        try {
            await api.delete(`/leads/${id}`)
        } catch (error) {
            failed.push(id)
            const message = error.response?.data?.message
            if (message) failureReasons.add(message)
        }
    }
    const archived = ids.filter((id) => !failed.includes(id))
    removeLeadsFromBoard(archived)
    bulkActionBusy.value = false
    if (failed.length) {
        // Surface the backend's actual reason (e.g. "has a deal and cannot be deleted")
        // when every failure shares it, instead of just a generic failure count.
        const reason = failureReasons.size === 1 ? ` — ${[...failureReasons][0]}` : ''
        window.$showNotification?.(`${archived.length} archived, ${failed.length} could not be archived${reason}.`, 'error')
    } else {
        window.$showNotification?.(archived.length === 1 ? 'Lead deleted.' : `${archived.length} leads deleted.`, 'success')
    }
    return true
}

async function deleteSelectedLeads() {
    const archived = await archiveLeadIds([...selectedLeadIds.value])
    if (archived) clearLeadSelection()
}

function openBulkStagePicker() {
    if (!selectedLeadIds.value.length) return
    showBulkStagePicker.value = true
}

async function applyBulkStage(column) {
    if (!column || bulkActionBusy.value) return
    bulkActionBusy.value = true
    const ids = [...selectedLeadIds.value]
    const failed = []
    for (const id of ids) {
        try {
            await api.post(`/leads/${id}/change-stage`, { stage_id: column.status })
        } catch (error) {
            failed.push(id)
        }
    }
    showBulkStagePicker.value = false
    clearLeadSelection()
    bulkActionBusy.value = false
    if (failed.length) {
        window.$showNotification?.('Some leads need extra fields before that stage. Open each one to finish the move.', 'error')
    } else {
        window.$showNotification?.('Stage updated.', 'success')
    }
    fetchLeads(true)
}

const ASSIGN_PREVIEW_LIMIT = 8

function personInitial(name) {
    const part = String(name || '').trim().charAt(0)
    return part ? part.toUpperCase() : '?'
}

const visibleBulkAssignees = computed(() => {
    const q = bulkAssignQuery.value.trim()
    if (!q) return bulkAssignees.value.slice(0, ASSIGN_PREVIEW_LIMIT)
    return bulkAssignees.value.slice(0, 30)
})

let bulkAssignTimer = null

async function loadBulkAssignees(search = '') {
    bulkAssignLoading.value = true
    try {
        const params = { limit: 30 }
        const term = String(search || '').trim()
        if (term) params.search = term
        const response = await api.get('/available-responsible-persons', { params })
        const payload = response.data?.data
        bulkAssignees.value = Array.isArray(payload) ? payload : []
    } catch (error) {
        bulkAssignees.value = []
    } finally {
        bulkAssignLoading.value = false
    }
}

watch(bulkAssignQuery, (value) => {
    if (!showBulkAssignPicker.value) return
    clearTimeout(bulkAssignTimer)
    bulkAssignTimer = setTimeout(() => loadBulkAssignees(value), 300)
})

async function openBulkAssignPicker() {
    if (!selectedLeadIds.value.length) return
    bulkAssignQuery.value = ''
    showBulkAssignPicker.value = true
    await loadBulkAssignees('')
}

async function applyBulkAssign(person) {
    if (!person?.id || bulkActionBusy.value) return
    bulkActionBusy.value = true
    const ids = [...selectedLeadIds.value]
    let failed = 0
    for (const id of ids) {
        try {
            await api.post(`/leads/${id}/assign-responsible-person`, { responsible_person_id: person.id })
        } catch (error) {
            failed += 1
        }
    }
    showBulkAssignPicker.value = false
    clearLeadSelection()
    bulkActionBusy.value = false
    window.$showNotification?.(
        failed ? `Assigned with ${failed} error${failed === 1 ? '' : 's'}.` : `Assigned to ${person.name}.`,
        failed ? 'error' : 'success'
    )
    fetchLeads(true)
}

function openLeadArchive() {
    showLeadArchive.value = true
    archiveDetail.value = null
    loadArchivedLeads(1)
}

function closeLeadArchive() {
    showLeadArchive.value = false
    archiveDetail.value = null
}

function onArchiveSearchInput() {
    clearTimeout(archiveSearchTimer)
    archiveSearchTimer = setTimeout(() => loadArchivedLeads(1), 300)
}

async function loadArchivedLeads(page = 1) {
    archiveLoading.value = true
    try {
        const response = await api.get('/leads/archived', {
            params: {
                search: archiveSearch.value.trim() || undefined,
                per_page: 20,
                page,
            },
        })
        const body = response.data?.data || {}
        archiveLeads.value = Array.isArray(body.data) ? body.data : []
        archivePagination.value = body.pagination || { current_page: 1, last_page: 1, per_page: 20, total: 0 }
    } catch (error) {
        archiveLeads.value = []
        window.$showNotification?.('Could not load archived leads.', 'error')
    } finally {
        archiveLoading.value = false
    }
}

async function openArchivedLead(lead) {
    archiveLoading.value = true
    try {
        const response = await api.get(`/leads/archived/${lead.id}`)
        archiveDetail.value = response.data?.data || lead
    } catch (error) {
        window.$showNotification?.('Could not open that archived lead.', 'error')
    } finally {
        archiveLoading.value = false
    }
}

async function forceDeleteArchived(lead) {
    const result = await Swal.fire({
        title: 'Delete forever?',
        text: 'This archived lead cannot be restored.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete forever',
        cancelButtonText: 'Cancel',
    })
    if (!result.isConfirmed) return
    bulkActionBusy.value = true
    try {
        await api.delete(`/leads/archived/${lead.id}/force`)
        archiveDetail.value = null
        window.$showNotification?.('Lead permanently deleted.', 'success')
        await loadArchivedLeads(archivePagination.value.current_page || 1)
    } catch (error) {
        window.$showNotification?.('Could not permanently delete that lead.', 'error')
    } finally {
        bulkActionBusy.value = false
    }
}

const columns = ref([])

function stageCountForColumn(col) {
    if (stagePagination.value[col.status]?.total != null) {
        return stagePagination.value[col.status].total
    }
    return col.leads?.length ?? 0
}

const STAGE_FLOW_LOOK = [
    { test: /new\s*lead/, color: '#f6c445', ink: '#1f2937', icon: 'lucide:folder' },
    { test: /assign/, color: '#f5a524', ink: '#1f2937', icon: 'lucide:user' },
    { test: /contact/, color: '#7c5cbf', ink: '#ffffff', icon: 'lucide:phone' },
    // Before /qualif/ — "unqualified" contains "qualif" and was getting the Qualified look.
    { test: /lost|unqualif/, color: '#ef4444', ink: '#ffffff', icon: 'lucide:x' },
    { test: /qualif/, color: '#3dcf8e', ink: '#14532d', icon: 'lucide:badge-check' },
    { test: /convert/, color: '#14b8a6', ink: '#042f2e', icon: 'lucide:check' },
    { test: /shared/, color: '#22c55e', ink: '#052e16', icon: 'lucide:share-2' },
    { test: /pool/, color: '#94a3b8', ink: '#1f2937', icon: 'lucide:inbox' },
]

function stageFlowMeta(title) {
    const name = String(title || '').toLowerCase()
    return STAGE_FLOW_LOOK.find((item) => item.test.test(name)) || null
}

// Header colour comes from the stage's own colour in stage settings (column.color), so
// changing it there changes the board. The fixed STAGE_FLOW_LOOK colour is only a
// fallback for a stage that has no colour set.
function stageFlowStyle(column) {
    if (column?.color) {
        return { backgroundColor: column.color, color: stageTextOn(column.color) }
    }
    const meta = stageFlowMeta(column?.title)
    if (meta) {
        return { backgroundColor: meta.color, color: meta.ink }
    }
    return { backgroundColor: 'var(--color-primary)', color: '#ffffff' }
}

function stageFlowIcon(title) {
    return stageFlowMeta(title)?.icon || 'lucide:circle-dot'
}

function formatStageCount(value) {
    const n = Number(value)
    if (!Number.isFinite(n)) return '0'
    return n.toLocaleString()
}

const totalLeadsCount = computed(() => {
    let sum = 0
    for (const col of columns.value) {
        const t = stagePagination.value[col.status]?.total
        sum += typeof t === 'number' ? t : (col.leads?.length || 0)
    }
    return sum
})

const hasActiveSearchOrFilters = computed(() => {
    const q = appliedSearchParams.value
    return !!(q && typeof q === 'object' && Object.keys(q).length > 0)
})

const showNoSearchResults = computed(() => {
    return (
        !loading.value &&
        !isSearching.value &&
        !error.value &&
        hasActiveSearchOrFilters.value &&
        columns.value.length > 0 &&
        totalLeadsCount.value === 0
    )
})

const activeShortcutFilter = ref(null)

const isBoardFilterActive = computed(() => {
    return hasActiveSearchOrFilters.value || !!activeShortcutFilter.value
})

const showKanbanSearchOverlay = computed(() => {
    return (
        isFetching.value &&
        columns.value.length > 0 &&
        isBoardFilterActive.value
    )
})

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

// Matches StageController::getStagesWithLeads' chip-count SQL exactly:
// SUM(CASE WHEN stage_id = 5 AND interaction_result = 'answered'/'no_answer' ...).
// normalizeLeadInteraction() alone isn't enough here — it's shared with the shortcut-chip
// filter, which intentionally matches interaction_result across every stage, not just 5.
function normalizeLeadInteractionForAnalytics(lead) {
    if (Number(lead?.stage_id) !== 5) return null
    return normalizeLeadInteraction(lead)
}

let analyticsRefreshTimer = null
// Debounced — a stage-change save can trigger this from more than one place
// (optimistic update + server response reconcile) within the same tick.
function scheduleLeadAnalyticsRefresh() {
    if (analyticsRefreshTimer) clearTimeout(analyticsRefreshTimer)
    analyticsRefreshTimer = setTimeout(refreshLeadAnalytics, 250)
}

// Re-fetches the authoritative "Answered" / "No Answer" (and temp) chip counts from the
// backend — same filters/permissions as the board fetch, just without the stages+leads
// payload. Called instead of estimating the delta locally, so it can't drift out of sync.
async function refreshLeadAnalytics() {
    try {
        const params = buildLeadSearchApiParams(effectiveSearchParams.value)
        const response = await api.get('/stages/kanban/lead-analytics', { params })
        const analytics = response?.data?.data
        if (analytics && typeof analytics === 'object') {
            leadAnalyticsServer.value = {
                tempCold: Number(analytics.tempCold) || 0,
                tempWarm: Number(analytics.tempWarm) || 0,
                tempHot: Number(analytics.tempHot) || 0,
                callAnswered: Number(analytics.callAnswered) || 0,
                callNoAnswer: Number(analytics.callNoAnswer) || 0,
            }
        }
    } catch (error) {
        console.error('Failed to refresh lead analytics', error)
    }
}

// Refresh the "Answered" / "No Answer" chip counts from the backend when a lead's
// interaction_result (or stage) changes locally (modal save, real-time update, drag
// revert) — otherwise they only reflect the last /stages-with-leads fetch until the
// board is refreshed.
function adjustInteractionAnalytics(oldLead, newLead) {
    const oldBucket = normalizeLeadInteractionForAnalytics(oldLead)
    const newBucket = normalizeLeadInteractionForAnalytics(newLead)
    if (oldBucket === newBucket) return
    scheduleLeadAnalyticsRefresh()
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

const KANBAN_LEADS_CACHE_KEY = 'kanban_leads_stages_cache_v2'
const KANBAN_LEADS_CACHE_TTL_MS = 5 * 60 * 1000 // keep board visible across refreshes

// Namespaced per user id — otherwise switching accounts on the same browser
// paints the previous user's cached board (including leads they can't see)
// for the moment before the background refetch corrects it.
function getKanbanCacheKey() {
    const uid = user.value?.id ?? getUserFromStorage()?.id ?? 'anon'
    return `${KANBAN_LEADS_CACHE_KEY}:${uid}`
}
const responsiblePersons = ref([])
const loading = ref(true)
const isSearching = ref(false)
const error = ref(null)
const kanbanContainerRef = ref(null)
const scrollInterval = ref(null)
const showLeftZone = ref(true)
const showRightZone = ref(true)
const stagePagination = ref({})          
const loadingMoreLeads = ref({})          
const leadsPerPage = ref(15)              
const SCROLL_SPEED = 10
const SCROLL_TICK_MS = 16
const isLeadDragging = ref(false)
let dragGestureStartedAt = 0
let dragDropAt = 0

// One visible phone column cannot accept a cross-stage drop. All-stages
// (and every desktop/tablet width) keeps the real drag path.
const leadCardDragDisabled = computed(() => {
    if (!kanbanIsMobile.value) return false
    return mobileListFilterStageId.value !== MOBILE_FILTER_ALL
})
const leadDragGroup = { name: 'tasks', pull: true, put: true }
const leadDragFallbackOffset = { x: 0, y: 0 }

function noteDragMetric(patch) {
    if (typeof window === 'undefined') return
    const previous = window.__kanbanLeadDragMetrics || {}
    window.__kanbanLeadDragMetrics = { ...previous, ...patch, at: Date.now() }
}

function onLeadCardPointerDown(event) {
    const card = event.currentTarget
    const id = Number(card?.getAttribute?.('data-lead-id'))
    if (id && motionHeld[id]) cancelLeadMotion(id)
    dragGestureStartedAt = performance.now()
    onLeadCardTiltDown(event)
}

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

const listTableWrapRef = ref(null)
const showListLeftZone = ref(false)
const showListRightZone = ref(false)
let listScrollInterval = null

function updateListScrollArrows() {
    const el = listTableWrapRef.value
    if (!el) {
        showListLeftZone.value = false
        showListRightZone.value = false
        return
    }
    const canScroll = el.scrollWidth - el.clientWidth > 2
    showListLeftZone.value = canScroll && el.scrollLeft > 2
    showListRightZone.value = canScroll && el.scrollWidth - el.clientWidth > el.scrollLeft + 2
}

function startListScrollLeft() {
    stopListScroll()
    listScrollInterval = setInterval(() => {
        const el = listTableWrapRef.value
        if (!el) return
        el.scrollLeft -= SCROLL_SPEED
        updateListScrollArrows()
    }, SCROLL_TICK_MS)
}

function startListScrollRight() {
    stopListScroll()
    listScrollInterval = setInterval(() => {
        const el = listTableWrapRef.value
        if (!el) return
        el.scrollLeft += SCROLL_SPEED
        updateListScrollArrows()
    }, SCROLL_TICK_MS)
}

function stopListScroll() {
    if (listScrollInterval) {
        clearInterval(listScrollInterval)
        listScrollInterval = null
    }
}

function stageNameKey(column) {
    return String(column?.title || '').toLowerCase().replace(/[^a-z0-9]/g, '')
}

function isNewLeadColumn(column) {
    if (!column) return false
    const order = Number(column.order ?? stageOrderMap.value[column.status])
    return stageNameKey(column).includes('newlead') || order === 1
}

function isAssignColumn(column) {
    if (!column) return false
    const order = Number(column.order ?? stageOrderMap.value[column.status])
    const name = stageNameKey(column)
    return name.includes('assign') || order === 2
}

function columnFromDragList(el) {
    const id = el?.getAttribute?.('data-stage-id')
    if (id == null || id === '') return null
    return columns.value.find((column) => String(column.status) === String(id)) || null
}

function canDragLeadToColumn(evt) {
    if (!leadSelectionActive.value) return true
    const lead = evt?.draggedContext?.element
    if (!lead || !isLeadSelected(lead.id)) return false
    const from = columnFromDragList(evt.from)
    const to = columnFromDragList(evt.to)
    if (!from || !to) return false
    if (String(from.status) === String(to.status)) return false
    return isNewLeadColumn(from) && isAssignColumn(to)
}

function markPackDropTargets(active) {
    document.querySelectorAll('.tasks-list[data-stage-id]').forEach((el) => {
        const column = columnFromDragList(el)
        const allow = active && isAssignColumn(column)
        el.classList.toggle('lead-pack-target', allow)
        el.classList.toggle('lead-pack-blocked', active && !allow && !isNewLeadColumn(column))
    })
}

function polishDragFallback(source) {
    const fallback = document.querySelector('.kanban-card-fallback')
    if (!fallback) return
    fallback.classList.remove('is-tilting', 'is-pressed', 'is-releasing', 'kanban-card--settle', 'kanban-card--motion-hold', 'ghost', 'kanban-card-chosen')
    fallback.style.transition = 'none'
    fallback.style.animation = 'none'
    fallback.style.opacity = '1'
    ;['--rx', '--ry', '--px', '--py', '--lz', '--gx', '--gy'].forEach((name) => fallback.style.removeProperty(name))
    if (source?.classList?.contains('lead-pack-drag')) {
        fallback.classList.add('lead-pack-drag')
        const count = source.getAttribute('data-pack-count')
        if (count) fallback.setAttribute('data-pack-count', count)
    }
}

function onLeadDragStart(event) {
    noteLeadDragOrigin(event)
    clearTimeout(leadCardClickTimer)
    clearMobileLongPress()
    isLeadDragging.value = true
    const item = event?.item
    const draggedId = Number(item?.getAttribute?.('data-lead-id'))
    if (draggedId && motionHeld[draggedId]) cancelLeadMotion(draggedId)
    releaseLeadCardTilt(item)
    mountLeadDragImage(event)
    const now = performance.now()
    noteDragMetric({
        pointerToDragMs: dragGestureStartedAt ? Math.round(now - dragGestureStartedAt) : 0,
    })
    if (leadSelectionActive.value && item) {
        const from = columnFromDragList(event.from)
        const count = isNewLeadColumn(from)
            ? from.leads.filter((lead) => lead && isLeadSelected(lead.id)).length
            : 0
        if (count > 1) {
            item.classList.add('lead-pack-drag')
            item.setAttribute('data-pack-count', String(count))
            markPackDropTargets(true)
        }
    }
    polishDragFallback(item)
}

function onLeadDragEnd(event) {
    clearLeadDragImage()
    if (event?.item?.style?.willChange) event.item.style.willChange = ''
    settleLeadCard(event?.item)
    isLeadDragging.value = false
    dragDropAt = performance.now()
    noteDragMetric({
        gestureMs: dragGestureStartedAt ? Math.round(dragDropAt - dragGestureStartedAt) : null,
    })
    document.querySelectorAll('.lead-pack-drag').forEach((el) => {
        el.classList.remove('lead-pack-drag')
        el.removeAttribute('data-pack-count')
    })
    markPackDropTargets(false)
    stopScroll()
}

const echoListeners = ref([])
/** Debounce rapid assignment broadcasts → single board refresh */
const leadAssignmentBoardRefreshTimer = ref(null)
const pollingInterval = ref(null)
const isFetching = ref(false)
const abortController = ref(null)
const moreLeadsAbortController = ref(null)
const fetchDebounceTimer = ref(null)
let fetchGeneration = 0
let moreLeadsGeneration = 0

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
        // Search "Inactive Sales" select — same filter as responsible person on the API side.
        ...(q.inactive_person_id != null && q.inactive_person_id !== '' && { inactive_person_id: q.inactive_person_id }),
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

const fetchLeads = async (immediate = false, queryOverride = undefined, options = {}) => {
    if (queryOverride !== undefined) {
        appliedSearchParams.value = queryOverride && Object.keys(queryOverride).length ? { ...queryOverride } : null
    }
    const silent = !!options.silent
    const hasSearchCriteria =
        (appliedSearchParams.value && Object.keys(appliedSearchParams.value).length > 0) ||
        !!activeShortcutFilter.value
    if (!silent && hasSearchCriteria) {
        isSearching.value = true
        window.dispatchEvent(new CustomEvent('kanban-lead-search-loading', { detail: { loading: true } }))
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
                await executeFetchLeads({ silent })
                resolve()
            }, 300) // 300ms debounce
        })
    }
    
    return executeFetchLeads({ silent })
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

function applyStagesWithLeadsResponse(response, params) {
    const responseData = response?.data?.data
    const stagesData = responseData?.stages || []
    const analytics = responseData?.analytics
    if (analytics && typeof analytics === 'object') {
        const nextAnalytics = {
            tempCold: Number(analytics.tempCold) || 0,
            tempWarm: Number(analytics.tempWarm) || 0,
            tempHot: Number(analytics.tempHot) || 0,
            callAnswered: Number(analytics.callAnswered) || 0,
            callNoAnswer: Number(analytics.callNoAnswer) || 0,
        }
        // Free-text search skips server analytics for speed — keep previous chip totals.
        const searching = !!(params.search && String(params.search).trim())
        const hasAny = Object.values(nextAnalytics).some((n) => n > 0)
        if (!searching || hasAny) {
            leadAnalyticsServer.value = nextAnalytics
        }
    }

    const searching = !!(params.search && String(params.search).trim())
    const newData = stagesData.map((stage, index) => ({
        title: stage.name,
        status: stage.id,
        color: stage.color || getColorByIndex(index),
        order: stage.order ?? index,
        // Keep server order during free-text search (no client re-rank by updated_at).
        leads: searching
            ? [...(stage.leads || [])]
            : sortLeadsByUpdatedAt([...(stage.leads || [])]),
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
}

const executeFetchLeads = async (options = {}) => {
    // Latest request wins: cancel older in-flight fetches instead of dropping
    // the newest user intent.
    if (abortController.value) {
        abortController.value.abort()
    }
    // Cancel in-flight load-more so a stale page cannot append after a new search.
    if (moreLeadsAbortController.value) {
        moreLeadsAbortController.value.abort()
        moreLeadsAbortController.value = null
    }
    moreLeadsGeneration += 1

    const generation = ++fetchGeneration
    abortController.value = new AbortController()
    const hadColumns = columns.value.length > 0
    const silent = !!options.silent
    const hasSearchCriteria =
        (appliedSearchParams.value && Object.keys(appliedSearchParams.value).length > 0) ||
        !!activeShortcutFilter.value
    isFetching.value = true
    // Never block the board with "Updating…" when we already have columns (refresh / cache hit).
    if (!silent && !hadColumns) {
        isSearching.value = true
        loading.value = true
        window.dispatchEvent(new CustomEvent('kanban-lead-search-loading', { detail: { loading: true } }))
    } else if (!silent && hasSearchCriteria) {
        isSearching.value = true
        window.dispatchEvent(new CustomEvent('kanban-lead-search-loading', { detail: { loading: true } }))
    }
    try {
        const q = effectiveSearchParams.value

        const params = {
            per_page: leadsPerPage.value,
            ...buildLeadSearchApiParams(q),
        }

        const prefetched = takeLeadBoardPrefetch(params)
        let response
        if (prefetched) {
            try {
                response = await prefetched
            } catch (prefetchError) {
                if (generation !== fetchGeneration) return
                response = await api.get('/stages/kanban/stages-with-leads', {
                    params,
                    signal: abortController.value.signal
                })
            }
        } else {
            response = await api.get('/stages/kanban/stages-with-leads', {
                params,
                signal: abortController.value.signal
            })
        }

        if (generation !== fetchGeneration) return

        if (paintedPrefetchResponse !== response) {
            applyStagesWithLeadsResponse(response, params)
        }
        saveColumnsToCache()
        
    } catch (err) {
        if (generation !== fetchGeneration) return
        if (err.name !== 'AbortError' && err.name !== 'CanceledError') {
            error.value = getApiErrorMessage(err, 'Failed to load data')
        }
    } finally {
        if (generation !== fetchGeneration) return
        isFetching.value = false
        isSearching.value = false
        loading.value = false
        abortController.value = null
        markKanbanReady()
        window.dispatchEvent(new CustomEvent('kanban-lead-search-loading', { detail: { loading: false } }))
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
                  // cap cached cards to one page size (badge total comes from stagePagination)
                  leads: Array.isArray(col.leads) ? col.leads.slice(0, leadsPerPage.value) : []
              }))
            : []

        const payload = {
            cachedAt: Date.now(),
            columns: snapshot,
            // Persist exact stage totals so the badge is not the loaded page size
            stagePagination: stagePagination.value || {},
        }
        localStorage.setItem(getKanbanCacheKey(), JSON.stringify(payload))
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

function readCachedStageShells() {
    try {
        const raw = localStorage.getItem(getKanbanCacheKey())
        if (!raw) return []
        const parsed = JSON.parse(raw)
        if (!parsed || !Array.isArray(parsed.columns)) return []
        return parsed.columns
            .filter((col) => col && col.title)
            .map((col, index) => ({
                title: col.title,
                color: col.color || getColorByIndex(index),
            }))
    } catch (e) {
        return []
    }
}

function loadCachedColumns() {
    try {
        const raw = localStorage.getItem(getKanbanCacheKey())
        if (!raw) return false
        const parsed = JSON.parse(raw)
        if (!parsed || !Array.isArray(parsed.columns)) return false

        const now = Date.now()
        if (parsed.cachedAt && now - parsed.cachedAt > KANBAN_LEADS_CACHE_TTL_MS) {
            return false
        }

        columns.value = parsed.columns
        syncStageOrderMapFromColumns(parsed.columns)
        if (parsed.stagePagination && typeof parsed.stagePagination === 'object') {
            stagePagination.value = parsed.stagePagination
        }
        sortAllColumnLeads()

        loading.value = false
        error.value = null
        return true
    } catch (e) {
        // ignore cache errors
        return false
    }
}

// Paint a saved board on the first render. Reading it in onMounted is one
// frame too late, so the loader flashes even when the cards are already here.
const hadCachedBoard = loadCachedColumns()
// Stage names from an older visit. Cards stay placeholders until the real response.
const pendingStageShells = ref(hadCachedBoard ? [] : readCachedStageShells())
// A resolved prefetch is applied here, before the first render. An in-flight
// one is applied on the microtask that delivers it, still owned by fetchLeads().
let paintedSettledPrefetch = false
let paintedPrefetchResponse = null
if (!hadCachedBoard) {
    const earlyParams = {
        per_page: leadsPerPage.value,
        ...buildLeadSearchApiParams(effectiveSearchParams.value),
    }
    const paintPrefetchedBoard = (response) => {
        if (!response || paintedPrefetchResponse === response) return
        if (hadCachedBoard || fetchGeneration !== 0) return
        paintedPrefetchResponse = response
        applyStagesWithLeadsResponse(response, earlyParams)
        loading.value = false
        paintedSettledPrefetch = true
    }
    const settled = peekSettledLeadBoardPrefetch(earlyParams)
    if (settled) {
        paintPrefetchedBoard(settled)
    } else {
        const inflight = peekLeadBoardPrefetchPromise(earlyParams)
        if (inflight) {
            inflight.then(paintPrefetchedBoard).catch(() => {})
        }
    }
}

async function fetchMoreLeadsFromApi(stageId) {
    // لو بتحمل حالياً، متعملش حاجة
    if (loadingMoreLeads.value[stageId]) return
    
    // لووصلت لآخر صفحة، متعملش حاجة
    const stage = columns.value.find(c => c.status === stageId)
    if (!stage || !stage.pagination?.has_more_pages) return
    
    loadingMoreLeads.value = {
        ...loadingMoreLeads.value,
        [stageId]: true
    }

    if (moreLeadsAbortController.value) {
        moreLeadsAbortController.value.abort()
    }
    moreLeadsAbortController.value = new AbortController()
    const generation = ++moreLeadsGeneration
    const signal = moreLeadsAbortController.value.signal
    
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
            params,
            signal,
        })

        if (generation !== moreLeadsGeneration) return
        
        const responseData = response?.data?.data
        const newLeads = responseData?.leads || []
        const newPagination = responseData?.pagination || {}
        
        // إضافة الـ leads الجديدة للـ column
        const columnIndex = columns.value.findIndex(c => c.status === stageId)
        if (columnIndex !== -1) {
            // ضيف الـ leads الجديدة تحت القديمة — skip ones already shown (a lead that moved
            // between pages, or arrived live via websocket, must not appear twice).
            const shownIds = new Set(columns.value[columnIndex].leads.map(l => String(l?.id)))
            columns.value[columnIndex].leads = sortLeadsByUpdatedAt([
                ...columns.value[columnIndex].leads,
                ...newLeads.filter(l => !shownIds.has(String(l?.id))),
            ])
            
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
        
    } catch (error) {
        if (error?.name === 'CanceledError' || error?.name === 'AbortError' || error?.code === 'ERR_CANCELED') {
            return
        }
        console.error('Error loading more leads:', error)
        $showNotification('Failed to load more leads', 'error')
    } finally {
        if (generation === moreLeadsGeneration) {
            loadingMoreLeads.value = {
                ...loadingMoreLeads.value,
                [stageId]: false
            }
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
        const response = await api.get('/available-responsible-persons', { params: { limit: 30 } })
        
        if (response.data && response.data.data) {
            responsiblePersons.value = response.data.data
        } else {
            responsiblePersons.value = []
        }
    } catch (error) {
        // Don't throw error for this, we can still work without it
    }
}

async function showConvertLeadModalWhenReady() {
    if (!convertModalRef.value?.show) await nextTick()
    if (!convertModalRef.value?.show) {
        await new Promise((resolve) => {
            const stop = watch(convertModalRef, (value) => {
                if (!value?.show) return
                stop()
                resolve()
            })
        })
    }
    convertModalRef.value.show(selectedLeadForConversion.value, selectedLeadData.value)
}

function openConvertLeadModal(lead) {
    selectedLeadForConversion.value = lead?.id || lead?.lead_id || null
    selectedLeadData.value = lead
    showConvertLeadModalWhenReady()
}

function handleLeadConverted(deal) {
    const payload = {
        ...(deal && typeof deal === 'object' ? deal : {}),
        deal_type: deal?.deal_type || deal?.type || null,
        id: deal?.id ?? deal?.deal_id ?? null,
    }

    // The "already has a deal" case already showed its own message in ConvertLeadModal —
    // don't pile a generic "converted successfully" toast on top of it.
    if (!deal?._alreadyExisted) {
        $showNotification('Lead converted to deal successfully', 'success')
    }
    // Move/remove the card locally from the fresh post-conversion lead instead of
    // refetching the whole board — much faster and avoids a visible reload.
    const updatedLead = deal?._lead
    let existing = null
    if (updatedLead?.id) {
        for (const col of columns.value) {
            existing = col.leads?.find((l) => l && l.id === updatedLead.id) || null
            if (existing) break
        }
    }
    const celebrate = !deal?._alreadyExisted && updatedLead?.id && beginConversionCelebration({
        leadId: updatedLead.id,
        leadName: existing?.lead_name || selectedLeadData.value?.lead_name,
        stageId: updatedLead.stage_id,
    })
    if (updatedLead?.id) {
        handleUpdatedLead({ ...(existing || {}), ...updatedLead }, 'updated')
    } else {
        fetchLeads(true)
    }
    selectedLeadForConversion.value = null
    selectedLeadData.value = null
    if (celebrate) {
        void playLeadMotion(updatedLead.id).catch(() => cancelLeadMotion(updatedLead.id)).finally(() => emit('deal-created', payload))
        return
    }
    emit('deal-created', payload)
}


watch(() => columns.value?.length, () => {
    nextTick(() => updateScrollArrows())
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

const cardShowsActivityRow = (column, task) => {
    return hasAssignedBy(task) && enabledFieldsForColumn(column, task).some((field) => field.key === 'assigned_by')
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
const personHoverCardStyle = ref({})
const personHoverTaskById = ref(null)

const positionPersonHoverCard = (event, type = 'responsible') => {
    const el = event?.currentTarget
    if (!el || typeof window === 'undefined') {
        personHoverCardStyle.value = {}
        return
    }
    const rect = el.getBoundingClientRect()
    const width = 188
    const estimatedHeight = 92
    const gap = 8
    const isRight = type === 'activity'
    let left = isRight ? rect.right - width : rect.left - 10
    let top = rect.bottom + gap
    left = Math.max(8, Math.min(left, window.innerWidth - width - 8))
    if (top + estimatedHeight > window.innerHeight - 8) {
        top = Math.max(8, rect.top - estimatedHeight - gap)
    }
    personHoverCardStyle.value = {
        position: 'fixed',
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
        right: 'auto',
        bottom: 'auto',
        height: 'auto',
        width: `${width}px`,
        zIndex: 12080,
    }
}

const normalizePersonHoverData = (person, task = {}, type = 'responsible', fallbackName = 'Unknown') => {
    const name = person?.name || person?.full_name || fallbackName
    const position = person?.position || person?.designation || person?.job_title || person?.role_name || person?.role || 'Team Member'
    const manager =
        person?.parent_name ||
        person?.parent?.name ||
        person?.manager_name ||
        person?.team_lead_name ||
        person?.reports_to_name ||
        person?.manager?.name ||
        person?.team_lead?.name ||
        // NOTE: task?.parent is the lead's added-by user, not the responsible/activity
        // person's manager — never fall back to it here, it silently shows the wrong name.
        'Not specified'
    const personBranch =
        person?.office_name ||
        (typeof person?.branch === 'string' ? person.branch : '') ||
        person?.branch_name ||
        person?.branch?.name ||
        person?.admin_parent_name ||
        person?.office ||
        person?.department ||
        person?.team_name ||
        ''
    const leadBranch = type === 'responsible'
        ? ''
        : (
            task?.lead_branch_source ||
            task?.branch_name ||
            task?.branch?.name ||
            task?.office_branch_name ||
            task?.office_branch ||
            ''
        )
    const branch = personBranch || leadBranch || 'Not specified'
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
        if (!user?.id) {
            if (activePersonHover.value?.leadId === leadId && activePersonHover.value?.type === type) {
                activePersonHover.value = { ...activePersonHover.value, pending: false }
            }
            return
        }
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
            pending: false,
            data: normalizePersonHoverData(
                {
                    ...basePerson,
                    ...user,
                    position: user.position || user.role_name || basePerson?.position,
                    branch_name: user.branch || user.branch_name || basePerson?.branch_name,
                    office_name: user.office_name || basePerson?.office_name,
                    admin_parent_name: user.admin_parent_name || basePerson?.admin_parent_name,
                    parent_name: user.parent_name || basePerson?.parent_name,
                },
                task,
                type,
                fallbackName,
            ),
        }
    } catch {
        if (activePersonHover.value?.leadId === leadId && activePersonHover.value?.type === type) {
            activePersonHover.value = { ...activePersonHover.value, pending: false }
        }
    }
}

const showPersonHoverCard = (task, type, event) => {
    cancelPersonHoverHide()
    const person = isActivityPersonType(type) ? activityPerson(task) : task?.responsible_person
    const fallbackName = isActivityPersonType(type)
        ? (activityPerson(task)?.name || 'Activity')
        : (task?.responsible_person?.name || 'Responsible Person')
    const hoverType = isActivityPersonType(type) ? 'activity' : type
    personHoverTaskById.value = task || null
    positionPersonHoverCard(event, hoverType)
    const hoverData = normalizePersonHoverData(person, task, hoverType, fallbackName)
    // Card payloads don't carry the employee-profile designation (`position`), so fetch
    // the profile whenever it's missing — cached per user, so one request per person.
    const needsProfile = Boolean(person?.id) && (
        hoverData.manager === 'Not specified' || hoverData.branch === 'Not specified' || !person?.role_name
        || person?.position === undefined
    )
    activePersonHover.value = {
        leadId: task?.id,
        type: hoverType,
        pending: needsProfile,
        data: hoverData,
    }
    if (needsProfile || (isActivityPersonType(type) && person?.id)) {
        enrichPersonHoverFromApi(Number(person.id), task?.id, hoverType, person, task, fallbackName)
    }
}

const hidePersonHoverCard = () => {
    cancelPersonHoverHide()
    personHoverHideTimer.value = setTimeout(() => {
        activePersonHover.value = null
        personHoverTaskById.value = null
    }, 90)
}

const openPersonProfileFromHover = (event) => {
    const task = personHoverTaskById.value
    const type = activePersonHover.value?.type || 'responsible'
    if (!task) return
    openPersonProfile(task, type, event)
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

const isCreatorInactive = (task) => {
    const u = task?.added_by_user
    return !!(u && typeof u === 'object' && u.status && u.status !== 'active')
}

const getCreatedByDisplay = (task) => {
    const u = task?.added_by_user
    console.log('CREATED BY', task?.id, JSON.stringify(u))

    if (isCreatorInactive(task)) {
        return (
            u.branch_name ||
            u.office_name ||
            u.admin_parent_name ||
            task?.lead_branch_source ||
            '—'
        )
    }

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
    
    // Thousands separators on the card: 2000000 / "2000000.00" / "2,000,000" → 2,000,000.
    const money = (value) => {
        const n = Number(String(value ?? '').replace(/,/g, '').trim())
        return Number.isFinite(n) && n > 0 ? Math.round(n).toLocaleString('en-US') : ''
    }
    const from = money(priorityReq.budget_from)
    const to = money(priorityReq.budget_to)

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
let unsubscribeLeadViewUpdated = null

onMounted(async () => {
    unsubscribeLeadViewUpdated = onLeadViewUpdated(handleLeadUpdatedFromModal)
    markKanbanReady()

    if (hadCachedBoard || paintedSettledPrefetch) {
        markKanbanReady()
        // Paint instantly from cache or an already-resolved prefetch, refresh quietly.
        fetchLeads(true, undefined, { silent: true })
    } else {
        try {
            await fetchLeads(true)
        } finally {
            markKanbanReady()
        }
    }

    fetchResponsiblePersons()
    fetchCardSettings()


  setupRevertAlertListener();
    fetchRevertNotifications();
    
    nextTick(() => {
        updateScrollArrows()
        updateListScrollArrows()
    })
    window.addEventListener('resize', updateScrollArrows)
    window.addEventListener('resize', updateListScrollArrows)
    window.addEventListener('echo-ready', onEchoReady)
    window.addEventListener('lead-search-mobile-sheet', onLeadSearchMobileSheet)
    initializeLeadUpdates()
     const leadIdFromUrl = route.query.lead
    if (leadIdFromUrl) {
        const numericId = Number(leadIdFromUrl)
        if (!isNaN(numericId) && numericId > 0) {
            await nextTick()
            openLeadView(numericId)
        }
    }
})

onUnmounted(() => {
    clearTimeout(realtimeFilteredRefreshTimer)
    clearTimeout(leadCardClickTimer)
    clearMobileLongPress()
    if (typeof unsubscribeLeadViewUpdated === 'function') {
        unsubscribeLeadViewUpdated()
        unsubscribeLeadViewUpdated = null
    }
    onLeadDragEnd()
    stopScroll()
    stopListScroll()
    cancelPersonHoverHide()
    window.removeEventListener('resize', updateScrollArrows)
    window.removeEventListener('resize', updateListScrollArrows)
    window.removeEventListener('echo-ready', onEchoReady)
    window.removeEventListener('lead-search-mobile-sheet', onLeadSearchMobileSheet)
    cleanup()
    destroyLeadMotion()
})

// Expose fetchLeads so parent can call it
defineExpose({
    fetchLeads,
    isSearching,
    isFetching,
    // Wrapped (not referenced directly) because handleNewLead is declared
    // further down the script — a direct reference here would hit the
    // temporal dead zone before setup() finishes running.
    handleNewLead: (lead) => handleNewLead(lead),
    applyAutoMergeVisibility: (meta, currentLead) => applyAutoMergeVisibility(meta, currentLead),
    removeLeadsFromBoard: (ids) => removeLeadsFromBoard(ids),
})

// Initialize real-time updates with Echo/Pusher
/** Normalize revert warning payloads from Pusher (event or Laravel notification broadcast). */
const parseRevertWarningPayload = (event) => {
    if (!event || typeof event !== 'object') return null

    const nested = event.data && typeof event.data === 'object' ? event.data : null
    const root = nested?.action_type === 'revert_warning' ? nested : event

    if (root.action_type !== 'revert_warning') return null

    const leadId = root.lead_id ?? root.lead?.id
    const leadName = root.lead_name
        ?? root.lead?.lead_name
        ?? (root.lead?.lead_number ? `Lead #${root.lead.lead_number}` : null)
        ?? (leadId ? `Lead #${leadId}` : 'Lead')

    const minutesLeft = root.minutes_left ?? 30
    const targetStage = root.target_stage ?? 'previous stage'

    return {
        id: event.id ?? Date.now(),
        data: {
            action_type: 'revert_warning',
            lead_id: leadId,
            lead_name: leadName,
            minutes_left: minutesLeft,
            target_stage: targetStage,
            message: root.message ?? `Lead ${leadName} will be reverted to ${targetStage} in ${minutesLeft} minutes`,
        },
    }
}

const onEchoReady = () => {
    initializeLeadUpdates()
}

const initializeLeadUpdates = () => {
    if (echoListeners.value.length > 0) {
        return
    }

    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        startPolling()
        return
    }

    stopPolling()

    try {
        const channel = window.Echo.private(`user.${user.id}`)
        
        channel.error((error) => {
               console.error('❌ Channel error:', error)
            startPolling()
        })

        // Dedicated revert-warning event (CheckLeadRevert → LeadReverted)
        channel.listen('.lead.revert.warning', (event) => {
            const notification = parseRevertWarningPayload(event)
            if (notification) addRevertAlert(notification)
        })

        // Laravel notification broadcast (LeadRevertWarningNotification)
        channel.listen('.notification.revert.warning', (event) => {
            const notification = parseRevertWarningPayload(event)
            if (notification) addRevertAlert(notification)
        })

        // Legacy notification event name (older Laravel default)
        channel.listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (event) => {
            const notification = parseRevertWarningPayload(event)
            if (notification) addRevertAlert(notification)
        })
       
        channel.listen('.lead.updated', (event) => {
            handleLeadUpdate(event, 'updated')
        })
        
        channel.listen('.lead.assigned', (event) => {
            handleLeadUpdate(event, 'assigned')
        })
      
      
     

        echoListeners.value.push(channel)
   console.log('✅ Revert alert listener connected via Pusher')
        console.log('📡 Listening on channel: user.' + user.id)
        // Engine emits `lead.assignment.updated` on `private-lead-assignment` (immediate, small payload).
        // Kanban must subscribe here — `user.{id}` may omit sales users / dual-role admins for `lead.updated`.
        const assignmentChannel = window.Echo.private('lead-assignment')
        assignmentChannel.listen('.lead.assignment.updated', (event) => {
            const payload = normalizeLeadRealtimeEvent(event)
            const leadId = payload?.lead_id
            const assignedId = payload?.assigned_user_id
            // This event has no card body. If the board card already shows that
            // owner, lead.updated already patched it. Otherwise reload so the
            // server decides visibility.
            if (leadId && assignedId != null) {
                const existing = findLeadInColumns(leadId)
                if (existing && String(existing.responsible_person_id) === String(assignedId)) {
                    return
                }
            }
            if (leadAssignmentBoardRefreshTimer.value) {
                clearTimeout(leadAssignmentBoardRefreshTimer.value)
            }
            leadAssignmentBoardRefreshTimer.value = setTimeout(() => {
                leadAssignmentBoardRefreshTimer.value = null
                if (!isFetching.value) {
                    fetchLeads(true, undefined, { silent: true })
                }
            }, 400)
        })
        echoListeners.value.push(assignmentChannel)
    } catch (error) {
        startPolling()
    }
}
// ================= Revert Alert Handler =================
const handleRevertAlertFromEvent = (event) => {
    const notification = parseRevertWarningPayload(event)
    if (notification) addRevertAlert(notification)
}
const handleLeadUpdate = (event, eventType = 'unknown') => {
    const payload = normalizeLeadRealtimeEvent(event)

    // Extract lead data - handle different possible structures
    let leadData = payload.lead
    
    // If lead is wrapped in a data property
    if (leadData?.data) {
        leadData = leadData.data
    }
    
    // If lead is an object with nested structure
    if (!leadData && payload.lead) {
        leadData = payload.lead
    }
    
    if (!leadData || !leadData.id) {
        if (!isFetching.value) {
            fetchLeads(true, undefined, { silent: true })
        }
        return
    }

    // With a search/filter applied, a live lead may only show up if it matches it.
    if (payload.action_type !== 'deleted' && !realtimeLeadPassesActiveFilters(leadData)) {
        showLeadNotification(payload)
        return
    }

    switch (payload.action_type) {
        case 'created':
            applyAutoMergeVisibility(payload.changes || {})
            handleNewLead(leadData)
            break
        case 'updated': {
            const updateMerge = applyAutoMergeVisibility(payload.changes || {}, leadData)
            if (updateMerge.absorbed) break
            handleUpdatedLead(leadData, 'updated')
            break
        }
        case 'assigned': {
            applyAutoMergeVisibility(payload.changes || {}, leadData)
            handleAssignedLead(leadData, payload.changes)

            break
        }
        case 'deleted':
            handleDeletedLead(leadData)
            break
        case 'stage_changed':
            handleStageChanged(leadData, payload.changes)
            break
        case 'revert':
            console.log("revert");
            handleStageChanged(leadData, payload.changes)
            break
        default:
            // For unknown action types, try to handle as update
            handleUpdatedLead(leadData, eventType)
    }
    
    showLeadNotification(payload)
}
// ================= Realtime vs active filters =================
const REALTIME_DATE_FILTER_KEYS = ['created_from', 'created_to', 'created_at', 'assigned_from', 'assigned_to', 'assigned_at']
let realtimeFilteredRefreshTimer = null

/** 'YYYY-MM-DD' of a timestamp as a UAE calendar day (matches backend UaeDateRange). */
function uaeDay(raw) {
    if (!raw) return null
    const d = new Date(raw)
    return Number.isFinite(d.getTime()) ? d.toLocaleDateString('en-CA', { timeZone: 'Asia/Dubai' }) : null
}

function dayInRange(day, from, to, exact) {
    if (exact) { from = exact; to = exact }
    if (!from && !to) return true
    if (!day) return false
    if (from && day < String(from).slice(0, 10)) return false
    if (to && day > String(to).slice(0, 10)) return false
    return true
}

/**
 * Should a Pusher lead be placed on the board under the current search/filters?
 * - no filters: yes (unchanged behaviour)
 * - only date filters: check created/assigned UAE day locally; a lead that no longer
 *   matches is taken off the board
 * - any other filter: we can't evaluate it reliably here — skip the event and quietly
 *   reload the board so the server (same filters) decides what shows.
 */
function realtimeLeadPassesActiveFilters(lead) {
    const params = buildLeadSearchApiParams(effectiveSearchParams.value)
    const activeKeys = Object.keys(params).filter((k) => {
        const v = params[k]
        return v !== undefined && v !== null && v !== '' && !(Array.isArray(v) && v.length === 0)
    })
    if (activeKeys.length === 0) return true

    if (activeKeys.every((k) => REALTIME_DATE_FILTER_KEYS.includes(k))) {
        const matches =
            dayInRange(uaeDay(lead.created_at), params.created_from, params.created_to, params.created_at) &&
            dayInRange(uaeDay(lead.assigned_at), params.assigned_from, params.assigned_to, params.assigned_at)
        if (!matches) removeLeadFromColumns(lead.id)
        return matches
    }

    // Already on the board → it matched the search, so update the card in place. Reloading
    // the whole board here (e.g. for the user's own stage change) made the move lag/jump
    // and cut the stage motion. Only leads we don't have yet need the server to decide.
    if (findLeadInColumns(lead.id)) return true

    clearTimeout(realtimeFilteredRefreshTimer)
    realtimeFilteredRefreshTimer = setTimeout(() => {
        realtimeFilteredRefreshTimer = null
        if (!isFetching.value) fetchLeads(true, undefined, { silent: true })
    }, 600)
    return false
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
        sortColumnLeadsByUpdatedAt(columnIndex)
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

function leadUpdatedAtMs(lead) {
    // Sort by the same "activity time" shown on the card, not the raw updated_at —
    // otherwise a freshly stage-changed card can land behind cards that only look
    // less recently active because their DB updated_at ticked more recently.
    const raw = lead?.last_activity_at ?? lead?.bitrix24_last_activity_at ?? lead?.assigned_at
        ?? lead?.updated_at ?? lead?.created_at
    if (!raw) return 0
    // Bare "Y-m-d H:i:s" (no zone) is UAE wall time — make it parseable everywhere.
    const str = String(raw)
    const iso = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(str) ? `${str.replace(' ', 'T')}+04:00` : str
    const t = new Date(iso).getTime()
    return Number.isFinite(t) ? t : 0
}

function sortLeadsByUpdatedAt(leads) {
    if (!Array.isArray(leads)) return []
    return leads.sort((a, b) => {
        const diff = leadUpdatedAtMs(b) - leadUpdatedAtMs(a)
        if (diff !== 0) return diff
        return (Number(b?.id) || 0) - (Number(a?.id) || 0)
    })
}

function sortColumnLeadsByUpdatedAt(columnIndex) {
    const col = columns.value[columnIndex]
    if (!col?.leads) return
    sortLeadsByUpdatedAt(col.leads)
}

function sortAllColumnLeads() {
    columns.value.forEach((_, index) => sortColumnLeadsByUpdatedAt(index))
}

const handleLeadUpdatedFromModal = (updatedLead) => {
    if (!updatedLead?.id) return

    const mergeResult = applyAutoMergeVisibility({
        auto_merged_lead_ids: updatedLead.auto_merged_lead_ids || [],
        auto_merge_primary_id: updatedLead.auto_merge_primary_id,
        auto_merge_primary_stage_id: updatedLead.auto_merge_primary_stage_id,
        lead_absorbed: !!updatedLead.lead_absorbed,
    }, updatedLead)

    if (mergeResult.absorbed) return

    handleUpdatedLead(
        {
            ...updatedLead,
            updated_at: updatedLead.updated_at || new Date().toISOString(),
        },
        'updated',
    )
    showLeadNotification(
        buildCrmLeadNotificationEvent(updatedLead, 'updated', user.value),
    )
}

const columnScrollEls = new Map()
function setColumnScrollEl(status, el) {
    if (el) columnScrollEls.set(status, el)
    else columnScrollEls.delete(status)
}

function columnStageKind(column) {
    const order = Number(column?.order)
    const title = String(column?.title || '').trim().toLowerCase()
    if (order === 10 || title.includes('unqualified')) return 'unqualified'
    if (order === 4 || title === 'qualified' || title === 'qualified leads') return 'qualified'
    return null
}

function captureQualifiedListScroll(stageStatus) {
    const columnEl = columnScrollEls.get(stageStatus)
    return {
        stageStatus,
        columnTop: columnEl ? columnEl.scrollTop : null,
        boardTop: kanbanContainerRef.value ? kanbanContainerRef.value.scrollTop : null,
        boardLeft: kanbanContainerRef.value ? kanbanContainerRef.value.scrollLeft : null,
        pageTop: window.scrollY || document.documentElement.scrollTop || 0,
    }
}

function restoreQualifiedListScroll(state) {
    if (!state) return
    const apply = () => {
        const columnEl = columnScrollEls.get(state.stageStatus)
        if (columnEl && state.columnTop != null) columnEl.scrollTop = state.columnTop
        const board = kanbanContainerRef.value
        if (board) {
            if (state.boardTop != null) board.scrollTop = state.boardTop
            if (state.boardLeft != null) board.scrollLeft = state.boardLeft
        }
        const pageNow = window.scrollY || document.documentElement.scrollTop || 0
        if (Math.abs(pageNow - state.pageTop) > 1) window.scrollTo(0, state.pageTop)
    }
    apply()
    nextTick(() => {
        apply()
        requestAnimationFrame(() => {
            apply()
            requestAnimationFrame(apply)
        })
    })
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
    const sameId = (left, right) => String(left) === String(right)
    
    // First, try to find and update existing lead
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && sameId(l.id, lead.id))
            if (index !== -1) {
                leadFound = true
                adjustInteractionAnalytics(column.leads[index], lead)

                if (!sameId(column.status, stageId)) {
                    // Lead moved to different stage
                    const destination = columns.value.find((col) => sameId(col.status, stageId))
                    const keepQualifiedPlace = columnStageKind(column) === 'qualified'
                        && columnStageKind(destination) === 'unqualified'
                    const qualifiedScroll = keepQualifiedPlace ? captureQualifiedListScroll(column.status) : null
                    column.leads.splice(index, 1)
                    
                    const newColumnIndex = columns.value.findIndex(c => sameId(c.status, stageId))
                    if (newColumnIndex !== -1) {
                        if (!columns.value[newColumnIndex].leads) {
                            columns.value[newColumnIndex].leads = []
                        }
                        
                        // Check if lead already exists in new column to avoid duplicates
                        const existingIndex = columns.value[newColumnIndex].leads.findIndex(l => l && sameId(l.id, lead.id))
                        if (existingIndex === -1) {
                            columns.value[newColumnIndex].leads.unshift(lead)
                        } else {
                            columns.value[newColumnIndex].leads[existingIndex] = lead
                        }
                        sortColumnLeadsByUpdatedAt(newColumnIndex)
                    }
                    if (qualifiedScroll) restoreQualifiedListScroll(qualifiedScroll)
                } else {
                    column.leads[index] = lead
                    sortColumnLeadsByUpdatedAt(i)
                }
                break
            }
        }
    }
    
    // If lead not found in any column, add it only when this role's board has that stage.
    // A missing column means the stage is not rendered here — do not park the card on
    // the first column, which left it in the wrong stage after a successful move.
    if (!leadFound) {
        const columnIndex = columns.value.findIndex(col => sameId(col.status, stageId))
        if (columnIndex !== -1) {
            if (!columns.value[columnIndex].leads) {
                columns.value[columnIndex].leads = []
            }
            
            // Check if lead already exists to avoid duplicates
            const existingIndex = columns.value[columnIndex].leads.findIndex(l => l && sameId(l.id, lead.id))
            if (existingIndex === -1) {
                // Ensure lead has stage_id set
                const leadToAdd = { ...lead, stage_id: stageId }
                columns.value[columnIndex].leads.unshift(leadToAdd)
            } else {
                // Update existing lead
                columns.value[columnIndex].leads[existingIndex] = { ...lead, stage_id: stageId }
            }
            sortColumnLeadsByUpdatedAt(columnIndex)
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
        .find(l => l && String(l.id) === String(leadId))

    if (existingLead && String(existingLead.stage_id) === String(leadStageId)) {
        return
    }
    
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && String(l.id) === String(leadId))
            if (index !== -1) {
                if (String(column.status) !== String(leadStageId)) {
                    const destination = columns.value.find((col) => String(col.status) === String(leadStageId))
                    const keepQualifiedPlace = columnStageKind(column) === 'qualified'
                        && columnStageKind(destination) === 'unqualified'
                    const qualifiedScroll = keepQualifiedPlace ? captureQualifiedListScroll(column.status) : null
                    column.leads.splice(index, 1)
                    
                    const newColumnIndex = columns.value.findIndex(c => String(c.status) === String(leadStageId))
                    if (newColumnIndex !== -1) {
                        if (!columns.value[newColumnIndex].leads) {
                            columns.value[newColumnIndex].leads = []
                        }
                        
                        const leadToAdd = lead.data || lead
                        columns.value[newColumnIndex].leads.unshift(leadToAdd)
                        sortColumnLeadsByUpdatedAt(newColumnIndex)
                    }
                    if (qualifiedScroll) restoreQualifiedListScroll(qualifiedScroll)
                }
                break
            }
        }
    }
}

const ensureCrmToastStyles = () => {
    if (document.getElementById('crm-toast-styles')) return
    const style = document.createElement('style')
    style.id = 'crm-toast-styles'
    style.textContent = `
        .crm-toast-popup {
            padding: 0 !important;
            border-radius: 16px !important;
            overflow: hidden;
            background: #ffffff !important;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 18px 40px -12px rgba(15, 23, 42, 0.28), 0 6px 14px -6px rgba(15, 23, 42, 0.16) !important;
        }
        .crm-toast {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 14px 14px 16px;
            position: relative;
            min-width: 308px;
        }
        .crm-toast__close {
            position: absolute;
            top: 8px; right: 8px;
            width: 22px; height: 22px;
            display: flex; align-items: center; justify-content: center;
            padding: 0; margin: 0;
            border: none; border-radius: 7px;
            background: transparent; color: #94a3b8;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, transform .15s ease;
        }
        .crm-toast__close:hover { background: rgba(15,23,42,.08); color: #334155; transform: rotate(90deg); }
        .crm-toast__close svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 2.4; fill: none; stroke-linecap: round; }
        .crm-toast::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--crm-accent, #3b82f6);
        }
        .crm-toast__icon {
            flex: 0 0 auto;
            width: 44px; height: 44px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            position: relative;
            color: #fff; line-height: 1;
            background: var(--crm-grad, linear-gradient(135deg,#3b82f6,#2563eb));
            animation: crmToastPop .45s cubic-bezier(.18,.89,.32,1.28), crmHalo 2.6s ease-out .45s infinite;
        }
        .crm-toast__icon::after {
            content: '';
            position: absolute; inset: 0;
            border-radius: inherit;
            background: linear-gradient(180deg, rgba(255,255,255,.4), rgba(255,255,255,0) 55%);
            pointer-events: none;
        }
        .crm-toast__icon svg {
            width: 23px; height: 23px;
            stroke: #fff; fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
            filter: drop-shadow(0 1px 1.5px rgba(0,0,0,.22));
            animation: crmIconPop .6s cubic-bezier(.18,.89,.32,1.4) .08s both;
        }
        .crm-toast__body {
            display: flex; flex-direction: column; gap: 3px;
            text-align: left; min-width: 0;
        }
        .crm-toast__title {
            font-size: 14px; font-weight: 700; color: #0f172a;
            line-height: 1.25; letter-spacing: -0.01em;
        }
        .crm-toast__title b { color: var(--crm-accent, #3b82f6); }
        .crm-toast__sub {
            font-size: 12.5px; color: #64748b; line-height: 1.35;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 240px;
        }
        .crm-toast__chip {
            display: inline-block;
            font-size: 11px; font-weight: 600;
            padding: 1px 7px; border-radius: 999px;
            background: var(--crm-soft, rgba(59,130,246,0.12));
            color: var(--crm-accent, #3b82f6);
            margin-left: 4px; vertical-align: middle;
        }
        .crm-toast-progress { background: var(--crm-accent, #3b82f6) !important; height: 3px !important; }
        @keyframes crmToastPop {
            0% { transform: scale(.3) rotate(-8deg); opacity: 0; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }
        @keyframes crmIconPop {
            0% { transform: scale(0) rotate(-35deg); opacity: 0; }
            60% { transform: scale(1.18) rotate(9deg); }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }
        @keyframes crmHalo {
            0% { box-shadow: 0 8px 18px -6px var(--crm-accent,#3b82f6), 0 0 0 0 var(--crm-ring,rgba(59,130,246,.45)); }
            70%, 100% { box-shadow: 0 8px 18px -6px var(--crm-accent,#3b82f6), 0 0 0 12px rgba(0,0,0,0); }
        }
        @media (prefers-color-scheme: dark) {
            .crm-toast-popup { background: #1e293b !important; border-color: rgba(255,255,255,0.06); }
            .crm-toast__title { color: #f1f5f9; }
            .crm-toast__sub { color: #94a3b8; }
            .crm-toast__close:hover { background: rgba(255,255,255,.1); color: #e2e8f0; }
        }
    `
    document.head.appendChild(style)
}

const showLeadNotification = (event) => {
    const payload = normalizeLeadRealtimeEvent(event)

    if (shouldSuppressLeadUpdateNotification(payload)) {
        return
    }

    ensureCrmToastStyles()

    const leadData = payload.lead?.data || payload.lead
    const leadName = leadData?.lead_name || leadData?.lead_number || 'Unknown Lead'
    const leadNumber = leadData?.lead_number ? `#${leadData.lead_number}` : ''
    const userName = payload.user_name || user.value?.name || 'Someone'

    const svg = {
        created:       `<svg viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9.5 13.9 11.4 12 16l-1.9-4.6L5.5 9.5 10.1 7.6z"/><path d="M19 13.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/></svg>`,
        updated:       `<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>`,
        assigned:      `<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m15 11 2 2 4-4"/></svg>`,
        stage_changed: `<svg viewBox="0 0 24 24"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg>`,
        deleted:       `<svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>`,
        default:       `<svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 15 3-4 3 3 4-6"/></svg>`,
    }
    const themes = {
        created:       { grad: 'linear-gradient(135deg,#34d399,#059669)', accent: '#10b981', soft: 'rgba(16,185,129,.12)', ring: 'rgba(16,185,129,.45)', icon: svg.created,       headline: `New Lead` },
        updated:       { grad: 'linear-gradient(135deg,#60a5fa,#2563eb)', accent: '#3b82f6', soft: 'rgba(59,130,246,.12)', ring: 'rgba(59,130,246,.45)', icon: svg.updated,       headline: `updated` },
        assigned:      { grad: 'linear-gradient(135deg,#fbbf24,#d97706)', accent: '#f59e0b', soft: 'rgba(245,158,11,.14)', ring: 'rgba(245,158,11,.5)',  icon: svg.assigned,      headline: `assigned` },
        stage_changed: { grad: 'linear-gradient(135deg,#a78bfa,#7c3aed)', accent: '#8b5cf6', soft: 'rgba(139,92,246,.13)', ring: 'rgba(139,92,246,.45)', icon: svg.stage_changed, headline: `moved` },
        deleted:       { grad: 'linear-gradient(135deg,#f87171,#dc2626)', accent: '#ef4444', soft: 'rgba(239,68,68,.12)',  ring: 'rgba(239,68,68,.45)',  icon: svg.deleted,       headline: `deleted` },
    }
    const t = themes[payload.action_type] || { grad: 'linear-gradient(135deg,#94a3b8,#475569)', accent: '#64748b', soft: 'rgba(100,116,139,.13)', ring: 'rgba(100,116,139,.45)', icon: svg.default, headline: `Lead updated` }

    const headline = t.headline
    const subtitle = `${leadName}${leadNumber ? ` <span class="crm-toast__chip">${leadNumber}</span>` : ''}`

    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        customClass: {
            popup: 'crm-toast-popup',
            timerProgressBar: 'crm-toast-progress'
        },
        html: `
            <div class="crm-toast">
                <div class="crm-toast__icon">${t.icon}</div>
                <div class="crm-toast__body">
                    <div class="crm-toast__title">${headline}</div>
                    <div class="crm-toast__sub">${subtitle}</div>
                </div>
                <button type="button" class="crm-toast__close" aria-label="Dismiss">
                    <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        `,
        didOpen: (toast) => {
            toast.style.setProperty('--crm-accent', t.accent)
            toast.style.setProperty('--crm-grad', t.grad)
            toast.style.setProperty('--crm-soft', t.soft)
            toast.style.setProperty('--crm-ring', t.ring)
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
            toast.querySelector('.crm-toast__close')?.addEventListener('click', () => Swal.close())
        }
    })
}

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
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
const showDuplicateModal = ref(false)
const selectedLeadForDuplicates = ref(null)
const currentTriggerElement = ref(null)
const showMergeModal = ref(false)
const {
    show: showMergedModal,
    leadId: mergedModalLeadId,
    trigger: mergedTrigger,
    open: openMergedLeadsModal,
    scheduleClose: scheduleMergedClose,
    cancelClose: cancelMergedClose,
    hoverOpen: mergedHoverOpen,
    hoverLeave: mergedHoverLeave,
} = useMergedLeadsPopover()


const mergeLeadId = ref(null)
// Always UAE time — the API sends UTC ("…Z"), and without a timeZone the browser
// showed it in the viewer's own zone (e.g. an hour early on a computer set to Egypt).
const CARD_TIME_ZONE = 'Asia/Dubai'

function formatDate(dateString) {
    if (!dateString) return ''
    const date = new Date(dateString)
    const options = { month: 'short', day: 'numeric', year: 'numeric', timeZone: CARD_TIME_ZONE }
    const formattedDate = date.toLocaleDateString('en-US', options)
    const formattedTime = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: CARD_TIME_ZONE,
    })
    return `${formattedDate}  |  ${formattedTime}`
}
/** Activity tile: Bitrix24 LAST_ACTIVITY_TIME (last_activity_at / bitrix24_last_activity_at). */
function formatActivityDate(task) {
    return formatDate(activityDisplayAt(task))
}

function clearMobileLongPress() {
    clearTimeout(mobileLongPressTimer)
    mobileLongPressTimer = null
}

function openMobileDeleteSheet(task) {
    if (!kanbanIsMobile.value || !canDeleteLeads.value || !task?.id) return
    clearTimeout(leadCardClickTimer)
    mobileDeleteLead.value = task
    showMobileDeleteSheet.value = true
    try { navigator.vibrate?.(12) } catch (_) { /* optional */ }
}

function closeMobileDeleteSheet() {
    showMobileDeleteSheet.value = false
    mobileDeleteLead.value = null
}

async function confirmMobileDeleteLead() {
    const lead = mobileDeleteLead.value
    closeMobileDeleteSheet()
    if (!lead?.id) return
    await archiveLeadIds([Number(lead.id)])
}

function onMobileCardContextMenu(event) {
    if (!kanbanIsMobile.value || !canDeleteLeads.value) return
    event.preventDefault()
}

function onMobileCardTouchStart(column, task, event) {
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
    mobileLongPressFired = false
    clearMobileLongPress()
    if (!canDeleteLeads.value || !task?.id) return
    mobileLongPressTimer = setTimeout(() => {
        mobileLongPressFired = true
        suppressLeadClickUntil = Date.now() + 700
        openMobileDeleteSheet(task)
    }, 520)
}

function onMobileCardTouchMove(column, event) {
    if (!kanbanIsMobile.value) return
    const key = String(column.status)
    const touch = event.changedTouches?.[0]
    if (!touch) return
    const startX = mobileTouchStartX.value[key] ?? touch.clientX
    const startY = mobileTouchStartY.value[key] ?? touch.clientY
    if (Math.abs(touch.clientX - startX) > 12 || Math.abs(touch.clientY - startY) > 12) {
        clearMobileLongPress()
    }
    mobileTouchLastX.value[key] = touch.clientX
    mobileTouchLastY.value[key] = touch.clientY
}

function onMobileCardTouchEnd(column, event) {
    if (!kanbanIsMobile.value) return
    clearMobileLongPress()
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
    // Vertical gestures stay with page/column scrolling.
    if (Math.abs(deltaX) < Math.abs(deltaY) * SWIPE_DOMINANCE_RATIO) return
    // Horizontal swipe scrolls the stage board. Don't also flip the card,
    // but swallow the click that follows the swipe.
    mobileSwipeMoved.value[key] = true
}

function findLeadInColumns(leadId) {
    const id = Number(leadId)
    if (!id) return null
    for (const col of columns.value) {
        const hit = (col.leads || []).find((lead) => Number(lead.id) === id)
        if (hit) return hit
    }
    return null
}

const viewLead = (task) => {
    const dealId = task?.converted_to_deal_id
    if (dealId) {
        emit('deal-created', {
            id: dealId,
            deal_id: dealId,
            deal_type: task?.converted_deal_type || task?.deal_type || null,
            lead_id: task?.id ?? null,
        })
        return
    }
    if (task?.id) {
            console.log('📌 Opening lead from card:', task.id)
             openLeadView(task.id, task)
        
    }
}

watch(() => route.query.lead, (leadId) => {
    if (!leadId) return
    const numericId = Number(leadId)
    if (!isNaN(numericId) && numericId > 0) {
        openLeadView(numericId, findLeadInColumns(numericId))
    }
}, { immediate: true })



function isColumnVisibleOnMobile(column) {
    if (!kanbanIsMobile.value) return true
    if (mobileListFilterStageId.value === MOBILE_FILTER_ALL) return true
    return String(column.status) === String(mobileListFilterStageId.value)
}

const skeletonColumns = computed(() => {
    if (pendingStageShells.value.length) return pendingStageShells.value
    return Array.from({ length: 6 }, (_, index) => ({
        title: '',
        color: getColorByIndex(index),
    }))
})

const BOARD_VIEW_KEY = 'kanban_leads_board_view'
const boardView = ref((() => {
    try {
        return localStorage.getItem(BOARD_VIEW_KEY) === 'list' ? 'list' : 'kanban'
    } catch (e) {
        return 'kanban'
    }
})())

function setBoardView(view) {
    boardView.value = view === 'list' ? 'list' : 'kanban'
    try {
        localStorage.setItem(BOARD_VIEW_KEY, boardView.value)
    } catch (e) {
        // ignore private-mode storage failures
    }
    nextTick(() => updateListScrollArrows())
}

const LIST_PAGE_SIZE = 10
const listPage = ref(1)

const listRows = computed(() => {
    const rows = []
    for (const column of columns.value) {
        if (!isColumnVisibleOnMobile(column)) continue
        for (const task of column.leads || []) {
            if (!task || !leadMatchesShortcutFilter(task)) continue
            rows.push({ task, column })
        }
    }
    return rows
})

const listPageCount = computed(() => Math.max(1, Math.ceil(listRows.value.length / LIST_PAGE_SIZE)))

const pagedListRows = computed(() => {
    const start = (listPage.value - 1) * LIST_PAGE_SIZE
    return listRows.value.slice(start, start + LIST_PAGE_SIZE)
})

watch(pagedListRows, () => {
    nextTick(() => updateListScrollArrows())
})

const listRangeStart = computed(() => (listRows.value.length ? (listPage.value - 1) * LIST_PAGE_SIZE + 1 : 0))
const listRangeEnd = computed(() => Math.min(listPage.value * LIST_PAGE_SIZE, listRows.value.length))
const listTotalLabel = computed(() => Math.max(totalLeadsCount.value || 0, listRows.value.length))
const showListPager = computed(() => listTotalLabel.value > LIST_PAGE_SIZE || listRows.value.length > LIST_PAGE_SIZE || listHasMore.value)
const canListNext = computed(() => listPage.value < listPageCount.value || listHasMore.value)

watch(listPageCount, (count) => {
    if (listPage.value > count) listPage.value = count
})

const listHasMore = computed(() =>
    columns.value.some((column) => {
        if (!isColumnVisibleOnMobile(column)) return false
        return !!stagePagination.value[column.status]?.hasMorePages
    })
)

const listMoreLoading = computed(() =>
    columns.value.some((column) => !!loadingMoreLeads.value[column.status])
)

let listLoadLock = false
function loadNextListPage() {
    if (listLoadLock || boardView.value !== 'list') return Promise.resolve()
    const column = columns.value.find((col) => {
        if (!isColumnVisibleOnMobile(col)) return false
        return stagePagination.value[col.status]?.hasMorePages && !loadingMoreLeads.value[col.status]
    })
    if (!column) return Promise.resolve()
    listLoadLock = true
    return Promise.resolve(fetchMoreLeadsFromApi(column.status)).finally(() => {
        listLoadLock = false
    })
}

function goListPrev() {
    if (listPage.value <= 1) return
    listPage.value -= 1
}

async function goListNext() {
    if (listMoreLoading.value) return
    if (listPage.value < listPageCount.value) {
        listPage.value += 1
        return
    }
    if (!listHasMore.value) return
    const before = listRows.value.length
    await loadNextListPage()
    if (listRows.value.length > before && listPage.value < listPageCount.value) {
        listPage.value += 1
    }
}

function onKanbanContainerScroll() {
    updateScrollArrows()
}

function listStageIndex(column) {
    const index = columns.value.findIndex((col) => String(col.status) === String(column?.status))
    return index < 0 ? 0 : index
}

function listLeadFullName(task) {
    const parts = [task?.salutation, task?.first_name, task?.last_name].filter((part) => part && String(part).trim())
    return parts.length ? parts.join(' ') : '—'
}

function listPhone(task) {
    const phone = task?.work_phone
    if (!phone) return ''
    const value = String(phone)
    return value.length > 8 ? `${value.slice(0, 8)}…` : value
}

function formatListStamp(dateString) {
    if (!dateString) return ''
    const date = new Date(dateString)
    if (Number.isNaN(date.getTime())) return ''
    const now = new Date()
    const time = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
    if (date.toDateString() === now.toDateString()) return time
    const day = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    if (date.getFullYear() !== now.getFullYear()) return `${day}, ${date.getFullYear()}`
    return `${day} · ${time}`
}

function listActivityLabel(task) {
    if (!hasAssignedBy(task)) return ''
    return formatListStamp(activityDisplayAt(task))
}

function formatListCreated(dateString) {
    if (!dateString) return '—'
    const date = new Date(dateString)
    if (Number.isNaN(date.getTime())) return '—'
    const minutes = Math.round((Date.now() - date.getTime()) / 60000)
    if (minutes < 1) return 'Just now'
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.round(minutes / 60)
    if (hours < 24) return hours === 1 ? '1 hour ago' : `${hours} hours ago`
    const days = Math.round(hours / 24)
    if (days < 14) return days === 1 ? '1 day ago' : `${days} days ago`
    return formatListStamp(dateString) || '—'
}

function motionAssignee(lead) {
    const person = lead?.responsible_person
    const name = String(person?.name || '').trim()
    if (!name) return null
    return {
        name,
        avatar: person?.avatar || person?.photo_url || '',
    }
}

function stageMotionMeta(column, fallback = null) {
    if (column) {
        return { id: column.status, name: column.title, order: column.order }
    }
    return fallback
}

function onLeadCardClick(task, column, event) {
    rememberLeadDetailOrigin(event?.currentTarget)
    if (mobileLongPressFired || Date.now() < suppressLeadClickUntil) {
        mobileLongPressFired = false
        return
    }
    if (kanbanIsMobile.value && mobileSwipeMoved.value[String(column.status)]) {
        mobileSwipeMoved.value[String(column.status)] = false
        return
    }
    if (event?.detail > 1) return
    if (isAdminOrSuperAdmin.value && leadSelectionActive.value) {
        toggleLeadSelection(task)
        return
    }
    if (kanbanIsMobile.value && canDeleteLeads.value) {
        const now = Date.now()
        const id = Number(task?.id)
        if (mobileLastTap.id === id && now - mobileLastTap.at < 380) {
            mobileLastTap = { id: 0, at: 0 }
            openMobileDeleteSheet(task)
            return
        }
        mobileLastTap = { id, at: now }
    }
    clearTimeout(leadCardClickTimer)
    const openDelay = kanbanIsMobile.value && canDeleteLeads.value ? 380 : 220
    leadCardClickTimer = setTimeout(() => viewLead(task), openDelay)
}

function onLeadCardDblClick(task) {
    clearTimeout(leadCardClickTimer)
    if (kanbanIsMobile.value) {
        openMobileDeleteSheet(task)
        return
    }
    if (!isAdminOrSuperAdmin.value) return
    toggleLeadSelection(task)
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
    mobileListFilterDraft.value = mobileListFilterStageId.value
    showMobileListFilterSheet.value = true
}

function closeMobileListFilterSheet() {
    showMobileListFilterSheet.value = false
}

function selectMobileListFilter(stageId) {
    mobileListFilterStageId.value = stageId === MOBILE_FILTER_ALL ? MOBILE_FILTER_ALL : String(stageId)
    closeMobileListFilterSheet()
}

function selectMobileStage(stageId, event) {
    selectMobileListFilter(stageId)
    const pill = event?.currentTarget
    pill?.scrollIntoView?.({ inline: 'center', block: 'nearest', behavior: 'smooth' })
}

function confirmMobileListFilter() {
    selectMobileListFilter(mobileListFilterDraft.value)
}

function mobileStageIcon(title) {
    const name = String(title || '').toLowerCase()
    if (name.includes('new')) return 'lucide:filter'
    if (name.includes('assign')) return 'lucide:user-round'
    if (name.includes('follow')) return 'lucide:message-circle'
    if (name.includes('unqual')) return 'lucide:badge-x'
    if (name.includes('qualif')) return 'lucide:badge-check'
    if (name.includes('convert')) return 'lucide:refresh-cw'
    if (name.includes('share')) return 'lucide:send'
    if (name.includes('lost')) return 'lucide:user-x'
    return 'lucide:layers'
}

function stageRgb(hex) {
    const raw = String(hex || '').trim().replace('#', '')
    const full = raw.length === 3 ? raw.split('').map((part) => part + part).join('') : raw
    if (!/^[0-9a-fA-F]{6}$/.test(full)) return null
    return {
        r: parseInt(full.slice(0, 2), 16),
        g: parseInt(full.slice(2, 4), 16),
        b: parseInt(full.slice(4, 6), 16),
    }
}

function stageIsLight(hex) {
    const rgb = stageRgb(hex)
    if (!rgb) return false
    return (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255 > 0.62
}

function stageTextOn(hex) {
    return stageIsLight(hex) ? '#1c1730' : '#ffffff'
}

// Mobile stage tab: border in the stage colour; filled with it when active.
// Light colours (e.g. yellow) use dark text so the label stays readable.
function mobileStagePillStyle(col) {
    const color = col?.color || '#733E87'
    return {
        '--pill-color': color,
        '--pill-text': stageIsLight(color) ? '#1c1730' : color,
        '--pill-active-text': stageTextOn(color),
    }
}

// "All leads" tab is gone on mobile — start on the first stage instead of the
// all-stages view (once; picking "All" from the filter sheet still works).
let mobileStageDefaulted = false
watch(
    () => [kanbanIsMobile.value, columns.value.length],
    ([isMobile, count]) => {
        if (!isMobile || !count || mobileStageDefaulted) return
        mobileStageDefaulted = true
        if (mobileListFilterStageId.value === MOBILE_FILTER_ALL) {
            mobileListFilterStageId.value = String(columns.value[0].status)
        }
    },
    { immediate: true },
)

const mobileActiveStageColumn = computed(() => {
    if (mobileListFilterStageId.value === MOBILE_FILTER_ALL) return null
    return columns.value.find(c => String(c.status) === String(mobileListFilterStageId.value)) || null
})

const mobileStageBarStyle = computed(() => {
    const color = mobileActiveStageColumn.value?.color || '#14b8a6'
    return {
        background: color,
        '--stage-label': stageTextOn(color),
        color: stageTextOn(color),
    }
})

function shiftMobileStage(direction) {
    const cols = columns.value
    if (!cols.length) return
    const current = mobileListFilterStageId.value
    let index = cols.findIndex(c => String(c.status) === String(current))
    if (current === MOBILE_FILTER_ALL || index < 0) {
        index = direction > 0 ? -1 : 0
    }
    const nextIndex = (index + direction + cols.length) % cols.length
    selectMobileListFilter(cols[nextIndex].status)
}


function onMobileColumnAddLead() {
    if (typeof kanbanOpenCreateLead === 'function') {
        kanbanOpenCreateLead()
    }
}

function openViewLeadFromMobileSheet() {
    const lead = mobileQuickLead.value
    const id = lead?.id
    if (id) {
        openLeadView(id, lead)
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
    if (leadId) {
        openLeadView(leadId)
    }
}


function openMergeModal(leadId) {
    mergeLeadId.value = leadId || selectedLeadForDuplicates.value
    showDuplicateModal.value = false
    // Lead modal sits under the merge overlay; close it so merge is not "hidden".
    closeLeadView()
    showMergeModal.value = true
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
async function onLeadsMerged(payload) {
    const id = typeof payload === 'object' && payload !== null
        ? payload.default_lead_id
        : payload
    const shouldOpen = typeof payload === 'object' && payload !== null
        ? payload.open !== false
        : true
    showMergeModal.value = false
    await nextTick()
    await fetchLeads(true, undefined, { silent: true })   // merged leads leave the board
    if (shouldOpen && id) openLeadView(id)
}

// If a lead opens while merge/duplicate overlays are still mounted, tear them down
// so the lead is not trapped under the purple high-z-index wash.
watch(showLeadViewModal, (open) => {
    if (!open) return
    showMergeModal.value = false
    showDuplicateModal.value = false
})
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

// Snap a dragged card back into its old column at its sorted place (where it was), not
// at the end — the stage-change motion starts from the card's position, and at the end
// of the column it flew in from the bottom of the page.
function restoreLeadToColumn(sourceColumn, lead) {
    if (!sourceColumn?.leads || sourceColumn.leads.find(l => l && String(l.id) === String(lead.id))) return
    sourceColumn.leads.push(lead)
    sortLeadsByUpdatedAt(sourceColumn.leads)
}

function flyLeadCard(leadId, fromRect) {
    const el = document.querySelector(`[data-lead-id="${leadId}"]`)
    if (!el || !fromRect) return
    const to = el.getBoundingClientRect()
    const dx = fromRect.left - to.left
    const dy = fromRect.top - to.top
    el.style.transition = 'none'
    el.style.transform = `translate(${dx}px, ${dy}px) scale(0.94)`
    el.style.zIndex = '6'
    requestAnimationFrame(() => {
        el.style.transition = 'transform 0.62s cubic-bezier(0.22, 1, 0.36, 1)'
        el.style.transform = 'translate(0, 0) scale(1)'
        const clear = () => {
            el.style.transition = ''
            el.style.transform = ''
            el.style.zIndex = ''
            el.removeEventListener('transitionend', clear)
        }
        el.addEventListener('transitionend', clear)
    })
}

async function moveSelectedLeadsToAssign(draggedLead, sourceColumn, targetColumn) {
    const selected = new Set(selectedLeadIds.value.map((id) => Number(id)))
    const extras = (sourceColumn?.leads || []).filter((lead) => lead && selected.has(Number(lead.id)) && String(lead.id) !== String(draggedLead.id))
    const fromRects = new Map()
    extras.forEach((lead) => {
        const el = document.querySelector(`[data-lead-id="${lead.id}"]`)
        if (el) fromRects.set(String(lead.id), el.getBoundingClientRect())
    })
    extras.forEach((lead) => {
        const index = sourceColumn.leads.findIndex((item) => String(item.id) === String(lead.id))
        if (index !== -1) sourceColumn.leads.splice(index, 1)
    })
    const insertAt = targetColumn.leads.findIndex((item) => String(item.id) === String(draggedLead.id))
    const at = insertAt === -1 ? targetColumn.leads.length : insertAt + 1
    targetColumn.leads.splice(at, 0, ...extras)
    const moving = [draggedLead, ...extras]
    moving.forEach((lead) => {
        lead.stage_id = targetColumn.status
    })
    await nextTick()
    extras.forEach((lead, index) => {
        window.setTimeout(() => flyLeadCard(lead.id, fromRects.get(String(lead.id))), index * 46)
    })
    clearLeadSelection()
    const results = await Promise.all(moving.map(async (lead) => {
        try {
            const response = await api.post(`/leads/${lead.id}/change-stage`, { stage_id: targetColumn.status })
            const mergeResult = applyAutoMergeVisibility(response?.data?.meta, lead)
            if (mergeResult.absorbed) return true
            const fresh = response?.data?.data
            if (fresh) Object.assign(lead, fresh)
            lead.stage_id = Number(lead.stage_id) || targetColumn.status
            return true
        } catch {
            return false
        }
    }))
    const failed = results.filter((ok) => !ok).length
    if (failed) {
        window.$showNotification?.('Some leads could not move to Assign.', 'error')
        if (!isFetching.value) fetchLeads(true)
        return
    }
    window.$showNotification?.(moving.length === 1 ? 'Lead moved to Assign.' : `${moving.length} leads moved to Assign.`, 'success')
}

function revertLeadDrop(lead, sourceColumn, newStageId) {
    if (!sourceColumn || !lead) return
    const sameId = (left, right) => String(left) === String(right)
    const targetColumnIndex = columns.value.findIndex(c => sameId(c.status, newStageId))
    if (targetColumnIndex !== -1) {
        columns.value[targetColumnIndex].leads =
            columns.value[targetColumnIndex].leads.filter(l => !l || !sameId(l.id, lead.id))
    }
    restoreLeadToColumn(sourceColumn, lead)
}

function openStageRequirementModal(payload, missingFields) {
    if (showStageChangeModal.value) return false
    pendingStageChange.value = payload
    missingFieldsForLead.value = missingFields
    showStageChangeModal.value = true
    const dropAt = dragDropAt || performance.now()
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            noteDragMetric({ dropToModalPaintMs: Math.round(performance.now() - dropAt) })
        })
    })
    return true
}

async function onLeadDragChange(evt, column) {
    if (evt.added) {
        dragDropAt = performance.now()
        const lead = evt.added.element
        const newStageId = column.status
        if (leadSelectionActive.value && isLeadSelected(lead.id)) {
            const sourceColumn = columns.value.find((item) => String(item.status) === String(lead.stage_id))
            if (!isNewLeadColumn(sourceColumn) || !isAssignColumn(column)) {
                const targetColumnIndex = columns.value.findIndex((item) => item.status === newStageId)
                if (targetColumnIndex !== -1) {
                    columns.value[targetColumnIndex].leads = columns.value[targetColumnIndex].leads.filter((item) => item.id !== lead.id)
                }
                if (sourceColumn) restoreLeadToColumn(sourceColumn, lead)
                window.$showNotification?.('Selected leads can only move from New Lead to Assign.', 'error')
                return
            }
            await moveSelectedLeadsToAssign(lead, sourceColumn, column)
            return
        }
        const newStageOrder = stageOrderMap.value[newStageId] ?? column.order ?? 0
        const sourceColumn = columns.value.find(c => String(c.status) === String(lead.stage_id))
        const normalizeStageName = (name) => String(name || '').toLowerCase().replace(/[^a-z]/g, '')
        const sourceStageName = normalizeStageName(sourceColumn?.title)
        const targetStageName = normalizeStageName(column?.title)
        const isAssignToFollowUpOrContacted =
          (  sourceStageName.includes('assign') || sourceStageName.includes('new lead')) &&
            (targetStageName.includes('followup') || targetStageName.includes('contacted'))

        // Only the lead's responsible person (or super_admin/admin/branch_admin, for leads
        // they can see) may release it into Lead Pool — the server enforces this too.
        // Snap the card back before any stage modal opens.
        const isLeadPoolTarget = newStageOrder === 9 || targetStageName === 'leadpool'
        const isLeadPoolSource = sourceStageName === 'leadpool'
            || (stageOrderMap.value[lead.stage_id] ?? sourceColumn?.order) === 9
        if (isLeadPoolTarget && !isLeadPoolSource && !isAdminOrSuperAdmin.value
            && Number(lead.responsible_person_id) !== Number(user.value?.id)) {
            const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
            if (targetColumnIndex !== -1) {
                columns.value[targetColumnIndex].leads =
                    columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
            }
            if (sourceColumn) restoreLeadToColumn(sourceColumn, lead)
            $showNotification('Only the responsible person can move this lead to Lead Pool', 'error')
            return
        }

        // [NEW] Logic for moving to Contacted stage from ANY stage
        const isMovingToContacted = targetStageName.includes('contacted')
        const isSalutationMissing = !lead.salutation || lead.salutation === ''

        if (isMovingToContacted && isSalutationMissing) {
            if (showStageChangeModal.value) {
                revertLeadDrop(lead, sourceColumn, newStageId)
                $showNotification('Finish the open stage change before moving another lead', 'info')
                return
            }
            beginLeadMove(lead.id)
            revertLeadDrop(lead, sourceColumn, newStageId)
            openStageRequirementModal({
                leadId: lead.id,
                targetStageId: newStageId,
                targetStageName: column.title,
                targetStageOrder: newStageOrder,
                originalStageId: lead.stage_id,
                leadData: { ...lead },
                isConversion: false,
                interactionMode: true,
            }, ['salutation'])
            return
        }

        if (isAssignToFollowUpOrContacted) {
            if (showStageChangeModal.value) {
                revertLeadDrop(lead, sourceColumn, newStageId)
                $showNotification('Finish the open stage change before moving another lead', 'info')
                return
            }
            beginLeadMove(lead.id)
            revertLeadDrop(lead, sourceColumn, newStageId)
            openStageRequirementModal({
                leadId: lead.id,
                targetStageId: newStageId,
                targetStageName: column.title,
                targetStageOrder: newStageOrder,
                originalStageId: lead.stage_id,
                leadData: { ...lead },
                isConversion: false,
                interactionMode: true,
            }, [])
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
            if (!missingFields.includes('status_lead')) missingFields.push('status_lead')

            if (missingFields.length > 0) {
                if (showStageChangeModal.value) {
                    revertLeadDrop(lead, sourceColumn, newStageId)
                    $showNotification('Finish the open stage change before moving another lead', 'info')
                    return
                }
                beginLeadMove(lead.id)
                revertLeadDrop(lead, sourceColumn, newStageId)
                openStageRequirementModal({
                    leadId: lead.id,
                    targetStageId: newStageId,
                    targetStageName: column.title,
                    targetStageOrder: newStageOrder,
                    originalStageId: lead.stage_id,
                    leadData: { ...lead },
                    isConversion: true,
                }, missingFields)
                return
            }

            beginLeadMove(lead.id)
            selectedLeadForConversion.value = lead?.id || lead?.lead_id || null
            selectedLeadData.value = lead
            
            const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
            if (targetColumnIndex !== -1) {
                columns.value[targetColumnIndex].leads = 
                    columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
            }
            
            await showConvertLeadModalWhenReady()
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
             4: ['status_lead'],
            8: ['why_lost_lead'],
            9: ['status_lead'],
            10: ['status_lead']
        }
        
        const requiredFields = requiredFieldsMap[newStageOrder] || []
        const alwaysFields = alwaysRequiredFieldsMap[newStageOrder] || []
        
        const leadMissingFields = requiredFields.filter(f => !lead.value?.[f])
        
        const fieldsToShow = [...new Set([...leadMissingFields, ...alwaysFields])]
        if (newStageOrder === 9 || newStageOrder === 10 || newStageOrder === 4) {
                if (!fieldsToShow.includes('status_lead')) {
                    fieldsToShow.push('status_lead')
                }
            }
        if (fieldsToShow.length > 0 || [3,4,5,7,8,9,10].includes(newStageOrder)) {
            if (showStageChangeModal.value) {
                revertLeadDrop(lead, sourceColumn, newStageId)
                $showNotification('Finish the open stage change before moving another lead', 'info')
                return
            }
            beginLeadMove(lead.id)
            revertLeadDrop(lead, sourceColumn, newStageId)
            openStageRequirementModal({
                leadId: lead.id,
                targetStageId: newStageId,
                targetStageName: column.title,
                targetStageOrder: newStageOrder,
                originalStageId: lead.stage_id,
                leadData: { ...lead },
                isConversion: false,
            }, fieldsToShow)
            return
        }

        void moveLeadWithStageChange(lead, newStageId)
    }
}

async function applyProgrammaticStageChange(lead, targetColumn) {
    const newStageId = targetColumn.status
    if (lead.stage_id === newStageId) return
    const sourceCol = columns.value.find(c => String(c.status) === String(lead.stage_id))
    if (!sourceCol) return
    const idx = sourceCol.leads.findIndex(l => l.id === lead.id)
    if (idx === -1) return
    const [moved] = sourceCol.leads.splice(idx, 1)
    targetColumn.leads.push(moved)
    await nextTick()
    await onLeadDragChange({ added: { element: moved } }, targetColumn)
}

function settleLeadOnBoard(lead, stageId) {
    if (!lead?.id || stageId == null || stageId === '') return false
    lead.stage_id = stageId
    const sameId = (left, right) => String(left) === String(right)
    const target = columns.value.find((column) => sameId(column.status, stageId)) || null
    columns.value.forEach((column) => {
        if (!column.leads) return
        column.leads = column.leads.filter((item) => !item || !sameId(item.id, lead.id))
    })
    if (!target) {
        // This role's board does not render the new stage. Reload from the server
        // so a column that should be visible can still receive the card.
        if (!isFetching.value) fetchLeads(true, undefined, { silent: true })
        return false
    }
    if (!target.leads) target.leads = []
    target.leads.unshift(lead)
    sortColumnLeadsByUpdatedAt(columns.value.indexOf(target))
    return true
}

async function moveLeadWithStageChange(lead, newStageId, moveToken = null) {
    const token = moveToken == null ? beginLeadMove(lead.id) : moveToken
    const oldLeadSnapshot = { ...lead }
    // String compare: stage ids arrive as numbers or strings depending on the source.
    const fromCol = columns.value.find((column) => String(column.status) === String(lead.stage_id))
    const toCol = columns.value.find((column) => String(column.status) === String(newStageId))
    const motionArmed = armLeadMotion({
        leadId: lead.id,
        leadName: lead.lead_name,
        from: stageMotionMeta(fromCol),
        to: stageMotionMeta(toCol, { id: newStageId, name: toCol?.title, order: toCol?.order }),
    })
    // The dragged card is already in its new column — play now instead of after the
    // server answers (the parked card froze until then, so the move felt laggy).
    let motionPlayed = false
    if (motionArmed && toCol) {
        await nextTick()
        void playLeadMotion(lead.id, { assignee: motionAssignee(lead), toId: newStageId }).catch(() => {
            cancelLeadMotion(lead.id)
        })
        motionPlayed = true
    }
    try {
        const apiStarted = performance.now()
        const response = await api.post(`/leads/${lead.id}/change-stage`, {
            stage_id: newStageId
        })
        noteDragMetric({ stageApiMs: Math.round(performance.now() - apiStarted) })
        if (!isLatestLeadMove(lead.id, token)) {
            rememberSupersededStage(lead.id, newStageId)
            return
        }
        consumeSupersededStage(lead.id)
        const mergeResult = applyAutoMergeVisibility(response?.data?.meta, lead)
        if (mergeResult.absorbed) {
            if (motionArmed) cancelLeadMotion(lead.id)
            return
        }
        const freshLead = response?.data?.data
        if (freshLead) {
            Object.assign(lead, freshLead)
        }
        const landedStageId = Number(lead.stage_id) || newStageId
        lead.stage_id = landedStageId
        adjustInteractionAnalytics(oldLeadSnapshot, lead)
        // Just-moved card must land on top of its new column, sorted by the same
        // "activity time" shown on the card rather than being appended at the end.
        settleLeadOnBoard(lead, landedStageId)
        // Motion started before the server answered — show the fresh card while it flies.
        if (motionPlayed) nextTick(() => refreshLeadMotionCard(lead.id))
        showLeadNotification(
            buildCrmLeadNotificationEvent(
                { ...lead, stage_id: newStageId },
                'stage_changed',
                user.value,
            ),
        )
        if (motionArmed && !motionPlayed) {
            void playLeadMotion(lead.id, { assignee: motionAssignee(lead), toId: newStageId }).catch(() => {
                cancelLeadMotion(lead.id)
            })
        }
    } catch (error) {
        if (!isLatestLeadMove(lead.id, token)) return
        consumeSupersededStage(lead.id)
        if (motionArmed) cancelLeadMotion(lead.id)
        const originalStageId = oldLeadSnapshot.stage_id
        Object.assign(lead, oldLeadSnapshot)
        lead.stage_id = originalStageId
        const restored = settleLeadOnBoard(lead, originalStageId)
        if (!restored && !isFetching.value) {
            await fetchLeads(true)
        }
        const errorMessage = error.response?.data?.message
            || error.response?.data?.error
            || 'Failed to move lead'
        $showNotification(errorMessage, 'error')
    }
}



async function handleStageChangeWithReason({ leadId, targetStageId, reason, ...additionalData }) {
    const lead = pendingStageChange.value?.leadData
    if (!lead) {
        console.error('No lead data found')
        return
    }

    const moveToken = beginLeadMove(leadId)

    const isConversion = pendingStageChange.value?.isConversion || false
    const targetStageOrder = pendingStageChange.value?.targetStageOrder || 0
    // Closing the modal below clears `pendingStageChange` (see the showStageChangeModal
    // watcher), so grab this now — the catch block needs it to revert the optimistic move.
    const originalStageId = pendingStageChange.value?.originalStageId ?? lead.stage_id
    const fromCol = columns.value.find((column) => String(column.status) === String(originalStageId))
    const toCol = columns.value.find((column) => String(column.status) === String(targetStageId))
    const converting = isConversion && targetStageOrder === 6
    const motionArmed = armLeadMotion({
        leadId,
        leadName: lead.lead_name,
        from: stageMotionMeta(fromCol, { id: originalStageId, name: fromCol?.title, order: fromCol?.order }),
        to: stageMotionMeta(toCol, {
            id: targetStageId,
            name: pendingStageChange.value?.targetStageName || toCol?.title,
            order: targetStageOrder,
        }),
        interactionResult: additionalData.interaction_result || null,
        force: converting ? 'stash' : null,
    })

    try {
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
        if (additionalData.activity_title) payload.activity_title = additionalData.activity_title
        if (additionalData.activity_reminder_date) payload.activity_reminder_date = additionalData.activity_reminder_date
        if (additionalData.activity_reminders) payload.activity_reminders = additionalData.activity_reminders
        
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

        // Move the card and close the modal right away — don't make the user wait
        // on the network round trip to see the result of what they just submitted.
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
        if (payload.interaction_result) lead.interaction_result = payload.interaction_result
        if (additionalData.area) lead.area = additionalData.area
        if (additionalData.property_type) lead.property_type = additionalData.property_type
        // The column sorts by last_activity_at — stamp it too, or the card lands at its old
        // activity time (bottom) until the server answers.
        const movedAt = new Date().toISOString()
        lead.updated_at = movedAt
        lead.last_activity_at = movedAt
        lead.bitrix24_last_activity_at = movedAt
        handleUpdatedLead(lead, 'updated')
        suppressStageRestore = true
        showStageChangeModal.value = false
        $showNotification('Lead stage updated successfully', 'success')
        clearPendingStageChange()

        // Open convert deal-type picker immediately (don't wait for change-stage).
        if (isConversion && targetStageOrder === 6) {
            selectedLeadForConversion.value = lead?.id || lead?.lead_id || leadId
            selectedLeadData.value = lead
            showConvertLeadModalWhenReady()
        }

        // The card already moved above — play the motion now with it. Waiting for the
        // server left the parked "Assigned → Contacted" card frozen, then the board just
        // switched. Only when this board renders the target stage; a failed save still
        // cancels the motion and moves the card back.
        let motionPlayed = false
        const targetOnBoard = columns.value.some((column) => String(column.status) === String(targetStageId))
        if (motionArmed && targetOnBoard) {
            await nextTick()
            void playLeadMotion(leadId, { assignee: motionAssignee(lead), toId: targetStageId }).catch(() => {
                cancelLeadMotion(leadId)
            })
            motionPlayed = true
        }

        // Fire API in background; reconcile lightly / revert on failure.
        try {
            const apiStarted = performance.now()
            const response = await api.post(`/leads/${leadId}/change-stage`, payload)
            noteDragMetric({ stageApiMs: Math.round(performance.now() - apiStarted) })
            if (!isLatestLeadMove(leadId, moveToken)) {
                rememberSupersededStage(leadId, targetStageId)
                return
            }
            consumeSupersededStage(leadId)
            const mergeResult = applyAutoMergeVisibility(response?.data?.meta, lead)
            if (mergeResult.absorbed) {
                if (motionArmed || converting) cancelLeadMotion(leadId)
                return
            }
            const freshLead = response.data?.data
            if (freshLead && lead) {
                const preserve = {
                    duplicate_no: lead.duplicate_no,
                    duplicate_ids: lead.duplicate_ids,
                    has_service_duplicate: lead.has_service_duplicate,
                }
                Object.assign(lead, freshLead, preserve)
            }
            const landedStageId = Number(lead?.stage_id) || targetStageId
            const landed = settleLeadOnBoard(lead, landedStageId)
            // Motion started before the server answered — show the fresh card while it flies.
            if (landed && motionPlayed) nextTick(() => refreshLeadMotionCard(leadId))
            if (!landed && motionArmed) cancelLeadMotion(leadId)
            else if (motionArmed && !motionPlayed) void playLeadMotion(leadId, { assignee: motionAssignee(lead), toId: targetStageId }).catch(() => {
                cancelLeadMotion(leadId)
            })
        } catch (error) {
            if (!isLatestLeadMove(leadId, moveToken)) return
            const supersededStageId = consumeSupersededStage(leadId)
            if (motionArmed || converting) cancelLeadMotion(leadId)
            console.error('Error in handleStageChangeWithReason:', error)
            const errorMessage = error.response?.data?.message ||
                                error.response?.data?.error ||
                                'Failed to update lead data'
            $showNotification(errorMessage, 'error')

            if (lead?.id) {
                lead.stage_id = supersededStageId || originalStageId
                if (lead.stage_id) handleUpdatedLead(lead, 'updated')
            }
            throw error
        }

    } catch (error) {
        if (!isLatestLeadMove(leadId, moveToken)) return
        console.error('Error preparing stage change:', error)
        cancelLeadMotion(leadId)
        clearPendingStageChange()
        throw error
    }
}

let suppressStageRestore = false

function restoreLeadToStage(leadId, stageId) {
    if (!stageId) return
    for (const column of columns.value) {
        const lead = column.leads?.find((item) => item && item.id === leadId)
        if (!lead) continue
        if (String(lead.stage_id) === String(stageId) && String(column.status) === String(stageId)) return
        lead.stage_id = stageId
        handleUpdatedLead(lead, 'updated')
        return
    }
}

function clearPendingStageChange() {
    pendingStageChange.value = null
    missingFieldsForLead.value = []
    showStageChangeModal.value = false
}
watch(showStageChangeModal, (newVal) => {
    if (newVal) return
    const pending = pendingStageChange.value
    const suppress = suppressStageRestore
    suppressStageRestore = false
    clearPendingStageChange()
    if (suppress || !pending?.leadId) return
    restoreLeadToStage(pending.leadId, consumeSupersededStage(pending.leadId))
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


// ================= Revert Alert State =================
const revertAlerts = ref([])
const showRevertAlert = ref(false)
let revertAlertTimer = null
const REVERT_ALERT_DURATION = 20000 // 20 ثواني

// ================= Computed =================

// الحصول على آخر إشعار Revert
const latestRevertAlert = computed(() => {
    if (revertAlerts.value.length === 0) return null
    return revertAlerts.value[revertAlerts.value.length - 1]
})

// رسالة الإشعار
const revertAlertMessage = computed(() => {
    if (!latestRevertAlert.value) return ''
    const data = latestRevertAlert.value.data || {}
    return data.message || 'A lead will be reverted soon'
})

// الوقت المتبقي
const revertAlertTimeLeft = computed(() => {
    if (!latestRevertAlert.value) return ''
    const minutes = latestRevertAlert.value.data?.minutes_left || 0
    if (minutes >= 60) {
        const hours = Math.floor(minutes / 60)
        const mins = minutes % 60
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`
    }
    return `${minutes}m`
})

// Lead Name
const revertAlertLead = computed(() => {
    if (!latestRevertAlert.value) return ''
    return latestRevertAlert.value.data?.lead_name || `Lead #${latestRevertAlert.value.data?.lead_id || 'unknown'}`
})

// Target Stage
const revertAlertTarget = computed(() => {
    if (!latestRevertAlert.value) return ''
    return latestRevertAlert.value.data?.target_stage || 'previous stage'
})

// ================= Methods =================

// إغلاق الـ Alert
const closeRevertAlert = () => {
    showRevertAlert.value = false
    if (revertAlertTimer) {
        clearTimeout(revertAlertTimer)
        revertAlertTimer = null
    }
}

// عرض الـ Alert مع تأخير
const showRevertAlertWithTimeout = () => {
    showRevertAlert.value = true
    
    // إخفاء بعد 8 ثواني
    if (revertAlertTimer) {
        clearTimeout(revertAlertTimer)
    }
    revertAlertTimer = setTimeout(() => {
        showRevertAlert.value = false
        revertAlertTimer = null
    }, REVERT_ALERT_DURATION)
}

// إضافة إشعار Revert جديد
const addRevertAlert = (notification) => {
    // التحقق من أن الإشعار من نوع Revert
    if (notification?.data?.action_type !== 'revert_warning') return
    
    // إضافة الإشعار إلى القائمة
    revertAlerts.value.push(notification)
    
    // إظهار الـ Alert
    showRevertAlertWithTimeout()
}

// الاستماع للإشعارات الجديدة عبر Pusher
const setupRevertAlertListener  = () => {
    window.addEventListener('revert-notification', (event) => {
        handleRevertAlertFromEvent(event.detail)
    })
}

// جلب الإشعارات غير المقروءة عند التحميل
const fetchRevertNotifications = async () => {
    try {
        const token = localStorage.getItem('token')
        const revertType = encodeURIComponent('App\\Notifications\\LeadRevertWarningNotification')
        const response = await fetch(`${import.meta.env.VITE_API_URL || '/api'}/auth/notifications?type=${revertType}`, {
            headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        })
        if (!response.ok) return
        const data = await response.json()
        
        // تصفية إشعارات الـ Revert فقط
        const revertNotifications = (data.data || []).filter(
            n => n?.data?.action_type === 'revert_warning' || n?.type === 'revert_warning'
        )
        
        // إضافة أحدث إشعار Revert
        if (revertNotifications.length > 0 && !showRevertAlert.value) {
            // عرض أحدث إشعار غير مقروء
            const unreadRevert = revertNotifications.filter(n => !n.read_at)
            if (unreadRevert.length > 0) {
                addRevertAlert(unreadRevert[0])
            } else {
                // إذا كانت كلها مقروءة، عرض أحدث واحد
                addRevertAlert(revertNotifications[0])
            }
        }
    } catch (e) {
        console.warn('Failed to fetch revert notifications:', e)
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
    overflow-x: clip;
    min-height: calc(100dvh - 240px);
    height: calc(100dvh - 240px);
    display: flex;
    flex-direction: column;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-x pan-y;
    overscroll-behavior-x: auto;
    overscroll-behavior-y: contain;
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
    touch-action: pan-x pan-y;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
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

/* Arrow style: large soft circular buttons like reference */
.kanban-nav-arrow {
    width: 44px;
    height: 44px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 4px 16px rgba(30, 27, 46, 0.12);
    border: 1px solid rgba(235, 230, 242, 0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: box-shadow 0.2s ease, background 0.2s ease, transform 0.15s ease;
    pointer-events: none;
}
.kanban-nav-zone:hover .kanban-nav-arrow {
    box-shadow: 0 6px 20px rgba(30, 27, 46, 0.16);
    background: #ffffff;
    transform: scale(1.04);
}
.kanban-nav-arrow-icon {
    font-size: 22px;
    font-weight: 600;
    color: #1a1528;
}
.kanban-nav-arrow-left {
    border-radius: 50%;
    padding-left: 0;
}
.kanban-nav-arrow-right {
    border-radius: 50%;
    padding-right: 0;
}

.kanban-board-skeleton {
    min-height: calc(100dvh - 200px);
}
.kanban-skeleton-title {
    display: block;
    width: 46%;
    height: 10px;
    border-radius: 999px;
    background: rgba(15, 15, 18, 0.18);
}
.kanban-skeleton-card,
.kanban-skeleton-row {
    border-radius: 14px;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.45) 0%, rgba(255, 255, 255, 0.92) 50%, rgba(255, 255, 255, 0.45) 100%);
    background-size: 200% 100%;
    animation: kanban-skeleton-shimmer 1.2s ease-in-out infinite;
    border: 1px solid rgba(30, 27, 46, 0.08);
}
.kanban-skeleton-card {
    height: 156px;
    margin: 0 2px 10px;
}
.kanban-board-skeleton--list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    padding: 8px;
}
.kanban-skeleton-row {
    height: 52px;
    border-radius: 10px;
}
@keyframes kanban-skeleton-shimmer {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

@media (prefers-reduced-motion: reduce) {
    .kanban-skeleton-card,
    .kanban-skeleton-row,
    .kanban-search-overlay__beam,
    .kanban-search-overlay__ring,
    .kanban-search-overlay__dots span,
    .kanban-search-overlay__shimmer {
        animation: none;
    }

    .kanban-card,
    .kanban-card:hover,
    .kanban-card:active,
    .kanban-card.is-tilting,
    .kanban-card--settle,
    .dragging {
        transition: none;
        transform: none;
        animation: none;
        scale: none;
        will-change: auto;
    }

    .kanban-card::before {
        opacity: 0;
    }
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
    width: 44px;
    height: 44px;
    border: 3px solid #efeaf7;
    border-top-color: var(--color-primary);
    border-right-color: #a855f7;
    border-radius: 50%;
    animation: kanban-spin 0.85s linear infinite;
}
@keyframes kanban-spin {
    to { transform: rotate(360deg); }
}
.kanban-error-state .kanban-empty-icon {
    color: #ef4444;
}

.kanban-outer {
    position: relative;
}

.kanban-search-overlay {
    position: absolute;
    inset: 0;
    z-index: 40;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 20px;
    background: linear-gradient(
        180deg,
        rgba(11, 7, 54, 0.08) 0%,
        rgba(11, 7, 54, 0.02) 38%,
        transparent 100%
    );
    pointer-events: none;
    overflow: hidden;
}

.kanban-search-overlay__beam {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(var(--color-primary-rgb), 0.15) 20%,
        rgba(var(--color-primary-rgb), 0.95) 50%,
        rgba(var(--color-primary-rgb), 0.15) 80%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: kanban-search-beam 1.1s ease-in-out infinite;
}

@keyframes kanban-search-beam {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

.kanban-search-overlay__shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        105deg,
        transparent 42%,
        rgba(255, 255, 255, 0.07) 50%,
        transparent 58%
    );
    background-size: 220% 100%;
    animation: kanban-search-shimmer 1.6s ease-in-out infinite;
}

@keyframes kanban-search-shimmer {
    0% { background-position: 120% 0; }
    100% { background-position: -120% 0; }
}

.kanban-no-results-overlay {
    position: absolute;
    inset: 0;
    z-index: 35;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    padding: 24px;
}

.kanban-no-results-overlay__text {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.88);
    text-align: center;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
    max-width: 420px;
}

.kanban-search-overlay__card {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 11px 18px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(var(--color-primary-rgb), 0.22);
    box-shadow:
        0 10px 32px rgba(11, 7, 54, 0.14),
        0 0 0 1px rgba(255, 255, 255, 0.65) inset;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 1;
}

.kanban-search-overlay__ring {
    position: absolute;
    inset: -4px;
    border-radius: 999px;
    border: 2px solid rgba(var(--color-primary-rgb), 0.35);
    animation: kanban-search-ring 1.4s ease-out infinite;
}

@keyframes kanban-search-ring {
    0% {
        transform: scale(0.92);
        opacity: 0.85;
    }
    70% {
        transform: scale(1.08);
        opacity: 0;
    }
    100% {
        transform: scale(1.08);
        opacity: 0;
    }
}

.kanban-search-overlay__icon {
    font-size: 16px;
    color: var(--color-primary);
    animation: kanban-search-icon 1.2s ease-in-out infinite;
}

@keyframes kanban-search-icon {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.12); opacity: 0.82; }
}

.kanban-search-overlay__text {
    font-size: 13px;
    font-weight: 600;
    color: #0b0736;
    letter-spacing: 0.01em;
}

.kanban-search-overlay__dots {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.kanban-search-overlay__dots span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--gradient-primary) !important;
    animation: kanban-search-dot 1s ease-in-out infinite;
}

.kanban-search-overlay__dots span:nth-child(2) {
    animation-delay: 0.15s;
}

.kanban-search-overlay__dots span:nth-child(3) {
    animation-delay: 0.3s;
}

@keyframes kanban-search-dot {
    0%, 80%, 100% {
        transform: translateY(0);
        opacity: 0.35;
    }
    40% {
        transform: translateY(-3px);
        opacity: 1;
    }
}

.kanban-outer--searching .kanban-column {
    opacity: 0.78;
    filter: saturate(0.88);
    transition: opacity 0.25s ease, filter 0.25s ease;
}

.kanban-outer--searching .kanban-container {
    pointer-events: none;
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
    gap: 14px;
}

/* Stage wells: highly translucent so system bg shows behind cards */
.kanban-column {
    position: relative;
    min-width: 252px;
    width: 252px;
    max-width: 252px;
    display: flex;
    flex-direction: column;
    background:
      linear-gradient(
        165deg,
        rgba(255, 255, 255, 0.18) 0%,
        rgba(255, 255, 255, 0.05) 40%,
        rgba(255, 255, 255, 0) 100%
      );
    border-radius: 18px;
    border: 1px solid rgba(30, 27, 46, 0.1);
    box-shadow: none;
    align-self: stretch;
    min-height: calc(100dvh - 200px);
    height: calc(100dvh - 200px);
    flex-shrink: 0;
    overflow: hidden;
    box-sizing: border-box;
    padding: 0 0 8px;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
}

.kanban-column:not(:first-child) {
    border-left: 1px solid rgba(30, 27, 46, 0.12);
    padding-left: 0;
    margin-left: 0;
}

.kanban-column:not(:first-child)::before {
    display: none;
    content: none;
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
    margin: 8px 8px 0;
    padding: 7px 12px;
    border-radius: 999px !important;
    position: relative;
    overflow: hidden;
    z-index: 1;
    clip-path: none !important;
    border: 1px solid rgba(15, 23, 42, 0.22) !important;
    box-shadow:
      0 0 0 1px rgba(255, 255, 255, 0.55),
      0 2px 6px rgba(15, 23, 42, 0.16);
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

.lead-select-bar {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    margin: 0 0 8px;
    padding: 4px 8px;
    min-height: 36px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e6e8ee;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    position: sticky;
    top: 6px;
    z-index: 30;
}

.lead-select-bar__cluster {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.lead-select-bar__rule {
    width: 1px;
    height: 16px;
    background: #e2e8f0;
    margin: 0 4px;
}

.lead-archive-row,
.lead-archive-row__actions,
.lead-archive-pager,
.lead-bulk-panel__head {
    display: flex;
    align-items: center;
    gap: 10px;
}

.lead-select-bar__count-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
}

.lead-select-bar__cluster strong {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #111827;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
}

.lead-select-bar__clear {
    width: 20px;
    height: 20px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.lead-select-bar__clear:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.lead-select-bar .lead-select-bar__btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 0;
    background: transparent;
    color: #334155;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
}

.lead-select-bar .lead-select-bar__btn iconify-icon {
    font-size: 14px;
}

.lead-select-bar .lead-select-bar__btn:hover:not(:disabled) {
    background: #f4f6f8;
}

.lead-select-bar .lead-select-bar__btn:disabled {
    opacity: 0.4;
}

.lead-select-bar .lead-select-bar__btn--danger {
    color: #e11d48;
    background: transparent;
}

.lead-select-bar .lead-select-bar__btn--danger:hover:not(:disabled) {
    background: #fff1f2;
}

.lead-bulk-panel .lead-select-bar__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #0f172a;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 600;
}

.lead-bulk-panel .lead-select-bar__btn--danger {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #be123c;
}
.lead-select-bar-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.lead-select-bar-enter-from,
.lead-select-bar-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

.lead-card-check {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.lead-card-check--on {
    background: #0f172a;
    border-color: #0f172a;
    color: #fff;
}

.kanban-card--selected {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.18), 0 8px 18px rgba(15, 23, 42, 0.12) !important;
}

.lead-bulk-overlay {
    position: fixed;
    inset: 0;
    z-index: 1200;
    background: rgba(11, 7, 54, 0.28);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
}

.lead-bulk-panel {
    width: min(460px, 100%);
    max-height: min(78vh, 640px);
    overflow: auto;
    background: #fff;
    color: #0B0736;
    border-radius: 16px;
    padding: 18px 16px 14px;
    border: 1px solid rgba(11, 7, 54, 0.08);
    box-shadow: 0 18px 48px rgba(11, 7, 54, 0.22);
}

.lead-bulk-panel--wide {
    width: min(760px, 100%);
}

.lead-bulk-panel__head {
    justify-content: space-between;
    margin-bottom: 6px;
    gap: 8px;
}

.lead-bulk-panel__title {
    font-size: 16px;
    font-weight: 650;
    letter-spacing: -0.01em;
    line-height: 1.2;
    color: #0B0736;
}

.lead-bulk-panel__close {
    width: 28px;
    height: 28px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #0B0736;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.lead-bulk-panel__close:hover {
    background: rgba(11, 7, 54, 0.08);
    color: #0B0736;
}

.lead-bulk-panel__head h3,
.lead-archive-detail h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 650;
    line-height: 1.2;
    color: #0B0736;
}

.lead-bulk-panel__hint {
    color: rgba(11, 7, 54, 0.55);
    font-size: 12px;
    margin: 0 2px 10px;
}

.lead-bulk-search {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    border: 1px solid rgba(11, 7, 54, 0.14);
    border-radius: 10px;
    padding: 0 12px;
    margin: 8px 0 10px;
    background: #f7f6fb;
    color: #0B0736;
}

.lead-bulk-search:focus-within {
    border-color: var(--color-primary);
    background: #fff;
}

.lead-bulk-search input {
    width: 100%;
    border: 0;
    background: transparent;
    padding: 10px 0;
    font-size: 14px;
    color: #0B0736;
    outline: none;
}

.lead-bulk-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lead-bulk-option {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    border-radius: 10px;
    padding: 10px 12px;
    color: #0B0736;
    font-size: 14px;
    font-weight: 500;
}

.lead-bulk-option:hover:not(:disabled) {
    background: rgba(11, 7, 54, 0.06);
}

.lead-bulk-option__dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    flex-shrink: 0;
    box-shadow: 0 0 0 3px rgba(11, 7, 54, 0.06);
}

.lead-bulk-option__avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: var(--gradient-primary) !important;
    color: #ffffff !important;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.lead-bulk-option__copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.lead-bulk-option__copy strong {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.25;
    color: #0B0736;
}

.lead-bulk-option__copy small {
    color: rgba(11, 7, 54, 0.5);
    font-size: 12px;
    line-height: 1.3;
}

.lead-archive-row {
    justify-content: space-between;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 12px;
}

.lead-archive-row span,
.lead-archive-detail p {
    display: block;
    color: #64748b;
    font-size: 12px;
}

.lead-archive-detail {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}

.lead-archive-pager {
    justify-content: flex-end;
    margin-top: 12px;
}

.kanban-card {
    --rx: 0deg;
    --ry: 0deg;
    --px: 0px;
    --py: 0px;
    --lz: 0px;
    --gx: 50%;
    --gy: 18%;
    color: #1e293b;
    border: 1px solid rgba(30, 27, 46, 0.14) !important;
    border-radius: 14px !important;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #ffffff 42%, #fbfbfd 100%) !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.92),
        0 1px 2px rgba(1, 6, 45, 0.04),
        0 8px 16px -12px rgba(1, 6, 45, 0.18) !important;
    margin-left: 2px;
    margin-right: 2px;
    position: relative;
    z-index: 1;
    /* transform: translate3d(var(--px), var(--py), 0) rotateX(var(--rx)) rotateY(var(--ry)); */
    transform-style: preserve-3d;
    transition:
        box-shadow 0.08s ease,
        border-color 0.08s ease,
        scale 0.08s ease;
    --tilt-strength: 0.2;  /* قلله أكتر لو لسه كبير */
    --max-tilt: 2deg;      /* أقصى ميل حتى لو الماوس في الطرف */
    --max-shift: 2px;      /* أقصى إزاحة */
    transform:
    translate3d(
        clamp(calc(var(--max-shift) * -1), calc(var(--px) * var(--tilt-strength)), var(--max-shift)),
        clamp(calc(var(--max-shift) * -1), calc(var(--py) * var(--tilt-strength)), var(--max-shift)),
        0
    )
    rotateX(clamp(calc(var(--max-tilt) * -1), calc(var(--rx) * var(--tilt-strength)), var(--max-tilt)))
    rotateY(clamp(calc(var(--max-tilt) * -1), calc(var(--ry) * var(--tilt-strength)), var(--max-tilt)));
}

.kanban-outer:not(.kanban-outer--mobile) .kanban-card > * {
    position: relative;
    z-index: 1;
}

.kanban-outer:not(.kanban-outer--mobile) .kanban-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    pointer-events: none;
    z-index: 0;
    opacity: 0;
    transition: none;
    background: transparent;
}

@media (hover: hover) and (pointer: fine) {
    .kanban-outer:not(.kanban-outer--mobile) .kanban-card:hover:not(.ghost):not(.dragging):not(.kanban-card--motion-hold):not(.is-tilting) {
        border-color: color-mix(in srgb, var(--column-color, var(--color-primary, #733E87)) 42%, #ffffff) !important;
    }

    .kanban-outer:not(.kanban-outer--mobile) .kanban-card.is-tilting:not(.ghost):not(.kanban-card--motion-hold),
    .kanban-outer:not(.kanban-outer--mobile) .kanban-card.is-releasing:not(.ghost),
    .kanban-outer:not(.kanban-outer--mobile) .kanban-card.dragging {
        z-index: 4;
        border-color: color-mix(in srgb, var(--column-color, var(--color-primary, #733E87)) 42%, #ffffff) !important;
    }

    .kanban-outer:not(.kanban-outer--mobile) .kanban-card.is-tilting {
        transition: transform 45ms linear, scale 0.12s cubic-bezier(0.22, 1, 0.36, 1);
        will-change: transform;
    }

    .kanban-outer:not(.kanban-outer--mobile) .kanban-card.is-releasing:not(.ghost) {
        transition: transform 0.12s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.08s ease, border-color 0.08s ease;
    }

    .kanban-card.lead-priority-hot-border:hover,
    .kanban-card.lead-priority-hot-border.is-tilting {
        border-color: #ef4444 !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.9),
            0 16px 28px -12px rgba(239, 68, 68, 0.28) !important;
    }

    .kanban-card.lead-priority-warm-border:hover,
    .kanban-card.lead-priority-warm-border.is-tilting {
        border-color: #f59e0b !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.9),
            0 16px 28px -12px rgba(245, 158, 11, 0.28) !important;
    }

    .kanban-card.lead-priority-cold-border:hover,
    .kanban-card.lead-priority-cold-border.is-tilting {
        border-color: #64748b !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.9),
            0 16px 28px -12px rgba(100, 116, 139, 0.24) !important;
    }

    .kanban-card--selected:hover,
    .kanban-card--selected.is-tilting {
        box-shadow:
            0 0 0 2px rgba(15, 23, 42, 0.18),
            0 16px 28px -12px rgba(1, 6, 45, 0.22) !important;
    }
}

.kanban-outer:not(.kanban-outer--mobile) .kanban-card.is-pressed:not(.dragging) {
    scale: 0.988;
}

.kanban-card:focus-visible {
    outline: 2px solid var(--color-primary, #733E87);
    outline-offset: 2px;
}

.kanban-card--motion-hold,
.kanban-card--motion-hold:hover,
.kanban-card--motion-hold:active {
    transform: none;
    scale: none;
    will-change: auto;
}

.kanban-card--settle {
    animation: kanban-card-drop 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes kanban-card-drop {
    0% { transform: translate3d(0, -4px, 0) scale(1.01); }
    100% { transform: translate3d(0, 0, 0) scale(1); }
}

.kanban-card--drag-image {
    position: fixed !important;
    top: -1400px !important;
    left: 0 !important;
    margin: 0 !important;
    pointer-events: none !important;
    z-index: 1;
    transform: translate3d(0, -6px, 0) rotateX(6deg) rotate(-1deg) scale(1.015) !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.96),
        0 16px 24px -14px rgba(1, 6, 45, 0.32),
        0 0 0 1px rgba(250, 163, 0, 0.35) !important;
}

.lead-priority-hot-border {
    border-color: #ef4444 !important;
    box-shadow: 0 2px 10px rgba(239, 68, 68, 0.18) !important;
}

.lead-priority-warm-border {
    border-color: #f59e0b !important;
    box-shadow: 0 2px 10px rgba(245, 158, 11, 0.18) !important;
}

.lead-priority-cold-border {
    border-color: #64748b !important;
    box-shadow: 0 2px 10px rgba(100, 116, 139, 0.14) !important;
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
    color: #1a1528 !important;
}

.kanban-card .task-title {
    font-size: 13.5px !important;
    font-weight: 700 !important;
    letter-spacing: -0.01em;
    line-height: 1.3;
}

.kanban-card .task-title:hover {
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
    word-break: break-word;
}

.kanban-card .info-label {
    color: #8b8798 !important;
    font-size: 10px !important;
    letter-spacing: 0.01em;
    line-height: 1.25;
}

.kanban-card .info-value {
    color: #353535 !important;
    font-size: 12px !important;
    line-height: 1.35;
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
    bottom: calc(100% + 6px);
    top: auto;
    left: 0;
    width: 188px;
    z-index: 60;
    border-radius: 11px;
    border: 1px solid #eadff0;
    background: #fff;
    box-shadow: 0 12px 28px rgba(76, 29, 110, 0.16);
    padding: 8px 9px 7px;
    pointer-events: auto;
    font-family: 'Montserrat', sans-serif;
}

.person-hover-card--portal {
    position: fixed !important;
    top: auto;
    left: auto;
    right: auto !important;
    bottom: auto !important;
    height: auto;
    z-index: 12080 !important;
    width: 188px;
    border: 1px solid #eadff0;
    border-radius: 11px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(76, 29, 110, 0.16);
    padding: 8px 9px 7px;
    overflow: hidden;
    font-family: 'Montserrat', sans-serif;
}

.person-hover-card--portal::before {
    content: none;
}

.person-hover-card-right {
    right: -10px;
    left: auto;
}

.person-hover-card--portal.person-hover-card-right {
    right: auto;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 6px;
    min-width: 0;
}

.person-hover-avatar {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 0 0 2px #f4e9f8;
    flex-shrink: 0;
}

.person-hover-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f7f1fa;
    color: var(--color-primary);
    font-size: 13px;
}

.person-hover-head-text {
    min-width: 0;
}

.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #1c1424;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.person-hover-role {
    display: block;
    margin-top: 1px;
    font-size: 10px;
    font-weight: 600;
    color: var(--color-primary);
    line-height: 1.15;
    background: transparent;
    border: none;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.person-hover-meta {
    display: grid;
    gap: 0;
}

.person-hover-meta-item {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 4px;
    margin-top: 4px;
    border-radius: 0;
    background: transparent;
    border: none;
    border-top: 1px solid #f3eaf6;
}

.person-hover-meta-item span {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0;
    text-transform: none;
    color: #94a3b8;
    flex-shrink: 0;
}

.person-hover-meta-item b {
    font-size: 10px;
    font-weight: 700;
    color: #1c1424;
    text-align: right;
    max-width: 108px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.15;
}

.person-hover-pending {
    color: #94a3b8 !important;
    font-weight: 600 !important;
}

.person-hover-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 10px;
    padding-top: 4px;
    margin-top: 4px;
    border-top: 1px solid #f3eaf6;
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
    gap: 12px;
    font-family: Montserrat;
    perspective: 1100px;
    perspective-origin: 50% 20%;
}

.tasks-list > .kanban-card {
    margin-bottom: 0 !important;
}

/* Draggable styles */
.tasks-list:has(.ghost) {
    border-radius: 12px;
    background: rgba(var(--color-primary-rgb), 0.05);
    box-shadow: inset 0 0 0 1.5px rgba(var(--color-primary-rgb), 0.34);
    transition: background 80ms ease, box-shadow 80ms ease;
}

.kanban-column:has(.tasks-list .ghost) .column-content-scrollable {
    background: rgba(var(--color-primary-rgb), 0.035);
    box-shadow: inset 0 0 0 1px rgba(var(--color-primary-rgb), 0.12);
}

.ghost {
    opacity: 0.45;
    background: rgba(1, 6, 45, 0.04);
    border: 1px dashed rgba(250, 163, 0, 0.85) !important;
    box-shadow: none !important;
    transform: none !important;
}

.kanban-card.ghost:not(.dragging) {
    opacity: 0.8;
    background:
        linear-gradient(165deg, rgba(250, 163, 0, 0.16), rgba(115, 62, 135, 0.08) 58%, rgba(1, 6, 45, 0.04)) !important;
    border: 1.5px dashed rgba(250, 163, 0, 0.95) !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.7),
        inset 0 0 22px rgba(250, 163, 0, 0.14) !important;
    transform: none !important;
    scale: 1;
    animation: none;
}

.dragging {
    cursor: grabbing;
    z-index: 4;
    box-shadow: 0 14px 28px rgba(1, 6, 45, 0.16) !important;
    transition: none;
}

.kanban-card.dragging.lead-pack-drag {
    box-shadow:
        0 22px 34px -12px rgba(1, 6, 45, 0.32),
        10px 12px 0 rgba(115, 62, 135, 0.28),
        18px 22px 0 rgba(115, 62, 135, 0.16),
        0 0 0 1px rgba(250, 163, 0, 0.4) !important;
}

.kanban-card.dragging {
    z-index: 6;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.96),
        0 22px 36px -18px rgba(1, 6, 45, 0.42),
        0 8px 16px -12px rgba(var(--color-primary-rgb), 0.35),
        0 0 0 1px rgba(var(--color-primary-rgb), 0.28) !important;
    transition: none !important;
    animation: none !important;
}

.kanban-card.kanban-card-fallback,
.kanban-card.sortable-fallback {
    opacity: 1 !important;
    cursor: grabbing;
    pointer-events: none !important;
    touch-action: none;
    border-color: rgba(var(--color-primary-rgb), 0.45) !important;
    border-radius: 14px !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.96),
        0 2px 4px rgba(1, 6, 45, 0.06),
        0 24px 40px -18px rgba(1, 6, 45, 0.46),
        0 10px 18px -12px rgba(var(--color-primary-rgb), 0.38) !important;
    transition: none !important;
    animation: none !important;
    will-change: transform;
}

.kanban-card-chosen:not(.ghost) {
    border-color: rgba(var(--color-primary-rgb), 0.4) !important;
}

.lead-pack-drag {
    box-shadow:
        0 16px 30px rgba(76, 29, 110, 0.22),
        10px 12px 0 rgba(115, 62, 135, 0.28),
        18px 22px 0 rgba(115, 62, 135, 0.16) !important;
}

.lead-pack-drag::after {
    content: attr(data-pack-count);
    position: absolute;
    z-index: 5;
    top: -10px;
    right: -10px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 999px;
    background: var(--gradient-primary);
    color: #fff;
    font-size: 12px;
    font-weight: 750;
    line-height: 22px;
    text-align: center;
    box-shadow: 0 6px 14px rgba(76, 29, 110, 0.28);
}

.tasks-list.lead-pack-target {
    border-radius: 14px;
    background: rgba(115, 62, 135, 0.08);
    box-shadow: inset 0 0 0 2px rgba(115, 62, 135, 0.45);
}

.tasks-list.lead-pack-blocked {
    opacity: 0.55;
}

@media (prefers-reduced-motion: reduce) {
    .kanban-card,
    .kanban-card:hover,
    .kanban-card:active,
    .kanban-card.is-tilting,
    .kanban-card--settle,
    .dragging,
    .kanban-outer--mobile .kanban-card--mobile,
    .mobile-stage-nav__arrow,
    .mobile-stage-nav__pill {
        transition: none;
        transform: none;
        animation: none;
        scale: none;
        will-change: auto;
    }

    .kanban-card::before {
        opacity: 0;
    }
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
    font-weight: 700;
    font-style: normal;
    font-size: 11px;
    line-height: 1.2;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: inherit !important;
    margin: 0;
}

.header-title-wrapper {
    cursor: pointer;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.header-title-wrapper:hover .header-title {
    text-decoration: none;
    opacity: 0.85;
}

.header-title-input {
    font-weight: 600;
    font-style: normal;
    font-size: 12px;
    color: #0f0f12;
    border: 1px solid rgba(15, 15, 18, 0.25);
    border-radius: 6px;
    padding: 2px 6px;
    outline: none;
    flex: 1;
    min-width: 0;
    background: rgba(255, 255, 255, 0.75);
}

.header-title-input:focus {
    border-color: rgba(15, 15, 18, 0.45);
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
    font-size: 12px;
    line-height: 1.1;
    color: inherit !important;
    margin-left: 2px;
    font-weight: 800 !important;
}

.column-header__icon {
    font-size: 15px;
    flex-shrink: 0;
}

/* —— Mobile Kanban (injected kanbanIsMobile) —— */
.kanban-outer--mobile {
    height: auto;
    min-height: 0;
    padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px));
    overflow-x: hidden;
    overflow-y: visible;
    /* Avoid 100vw overflow/cropping on some mobile browsers that can visually shift the board */
    width: 100%;
    max-width: 100%;
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
    overflow-x: hidden !important;
    overflow-y: visible;
    height: auto;
    min-height: 0;
    max-height: none;
    padding: 8px 8px 16px;
    touch-action: pan-y;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: none;
    overscroll-behavior-y: auto;
    scroll-snap-type: none;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.kanban-outer--mobile .kanban-container::-webkit-scrollbar {
    width: 0;
    height: 0;
}

.kanban-outer--mobile .kanban-wrapper {
    flex-direction: column !important;
    flex-wrap: nowrap !important;
    align-items: stretch !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    gap: 12px;
}

.kanban-outer--mobile .kanban-board-skeleton {
    min-height: 0;
}

.kanban-outer--mobile .kanban-column {
    flex: 0 0 auto !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    scroll-snap-align: none;
    /* Stage column outlined in a soft tint of its stage colour (matches the stage tabs
       above), with a faint glow of the same colour so it reads as one card. */
    border: 1px solid #e9d5ff !important;
    border: 1px solid color-mix(in srgb, var(--column-color, #733E87) 45%, #ffffff) !important;
    border-radius: 20px;
    overflow: hidden;
    position: relative;
    padding-top: 6px;
    /* Soft wash of the stage colour fading to white behind the cards. */
    background: #ffffff;
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--column-color, #733E87) 10%, #ffffff) 0%,
        #ffffff 220px
    );
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 14px 30px -18px var(--column-color, rgba(115, 62, 135, 0.45));
    height: auto !important;
    min-height: 0 !important;
    max-height: none;
    flex-shrink: 0;
}

/* No top strip on mobile stage columns — same (none) for every stage, first included. */
.kanban-outer--mobile .kanban-column::before {
    display: none !important;
    content: none !important;
}

/* Cards inside: light tint of the stage colour as the border, and a soft lift. */
.kanban-outer--mobile .kanban-column .kanban-card--mobile {
    border-color: color-mix(in srgb, var(--column-color, #733E87) 28%, #ffffff) !important;
    box-shadow: 0 6px 16px -12px rgba(15, 23, 42, 0.35) !important;
}

/* Stage-colour stripe on the left — only while the card is hovered / pressed. */
.kanban-outer--mobile .kanban-column .kanban-card--mobile::after {
    content: '';
    position: absolute;
    top: 12px;
    bottom: 12px;
    left: 0;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: var(--column-color, #733E87);
    opacity: 0;
    transition: opacity 0.15s ease;
    pointer-events: none;
}

.kanban-outer--mobile .kanban-column .kanban-card--mobile:hover,
.kanban-outer--mobile .kanban-column .kanban-card--mobile:active,
.kanban-outer--mobile .kanban-column .kanban-card--mobile:focus-visible {
    border-color: color-mix(in srgb, var(--column-color, #733E87) 42%, #ffffff) !important;
    transition: border-color 0.08s ease;
}

.kanban-outer--mobile .kanban-column > div,
.kanban-outer--mobile .kanban-column .card-body {
    height: auto !important;
    min-height: 0 !important;
    flex: 0 1 auto;
}

.kanban-outer--mobile .column-content-scrollable {
    flex: 1 1 auto;
    min-height: 0 !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
    touch-action: pan-y;
}

.kanban-outer--mobile .tasks-list {
    flex: 0 1 auto;
    flex-grow: 0;
    min-height: 0;
}

.kanban-outer--mobile .column-header--mobile {
    display: none !important;
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
    color: inherit !important;
    font-weight: 700 !important;
}

.kanban-outer--mobile .column-header--mobile .header-title-input {
    color: #0f0f12;
    border-color: rgba(15, 15, 18, 0.3);
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

.kanban-outer--mobile-stage .column-header--mobile {
    display: none !important;
}

.kanban-outer--mobile .kanban-card,
.kanban-outer--mobile .kanban-card:hover,
.kanban-outer--mobile .kanban-card:active {
    transform: none;
    scale: none;
}

.kanban-outer--mobile .kanban-card--mobile {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--color-purple-light) !important;
    border-radius: 16px !important;
    background: #fff !important;
    box-shadow: none !important;
    padding: 14px 14px 12px !important;
    margin-bottom: 12px !important;
    touch-action: manipulation;
    -webkit-user-select: none;
    user-select: none;
}

.kanban-outer--mobile .kanban-card--mobile::before {
    display: none;
}

.kanban-outer--mobile .kanban-card--mobile:active {
    background: #fafafa;
}

.kanban-outer--mobile .kanban-card--mobile:nth-child(2) { animation-delay: 40ms; }
.kanban-outer--mobile .kanban-card--mobile:nth-child(3) { animation-delay: 80ms; }
.kanban-outer--mobile .kanban-card--mobile:nth-child(4) { animation-delay: 120ms; }
.kanban-outer--mobile .kanban-card--mobile:nth-child(5) { animation-delay: 160ms; }
.kanban-outer--mobile .kanban-card--mobile:nth-child(6) { animation-delay: 200ms; }
.kanban-outer--mobile .kanban-card--mobile:nth-child(n + 7) { animation-delay: 220ms; }

@keyframes mlead-in {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: none; }
}

.kanban-outer--mobile .kanban-card--mobile .task-header {
    margin-bottom: 6px !important;
}

.kanban-outer--mobile .kanban-card--mobile .task-title {
    font-size: 14px;
    font-weight: 700;
    line-height: 1.3;
    white-space: normal;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.kanban-outer--mobile .kanban-card--mobile .task-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.kanban-outer--mobile .kanban-card--mobile .info-item,
.kanban-outer--mobile .kanban-card--mobile .date-info {
    margin-bottom: 0 !important;
    min-width: 0;
}

.kanban-outer--mobile .kanban-card--mobile .date-info {
    grid-column: 1 / -1;
}

/* Activity row (date left, avatar + source mark right) and the lone source mark take the
   full card width instead of one half-width grid cell. */
.kanban-outer--mobile .kanban-card--mobile .lead-activity-row,
.kanban-outer--mobile .kanban-card--mobile .lead-source-mark-row {
    grid-column: 1 / -1;
}

.kanban-outer--mobile .kanban-card--mobile .lead-activity-row hr {
    margin-top: 2px;
}

.kanban-outer--mobile .kanban-card--mobile .info-item--span {
    grid-column: 1 / -1;
}

.kanban-outer--mobile .kanban-card--mobile .info-label,
.kanban-outer--mobile .kanban-card--mobile .text-secondary-light {
    font-size: 11px !important;
    color: #9aa0ad !important;
}

.kanban-outer--mobile .kanban-card--mobile .info-value {
    font-size: 13px;
    font-weight: 600;
    color: #1c1730;
}

.mobile-stage-carousel {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    margin: 4px 0 12px;
    padding: 6px;
    overflow-x: auto;
    overflow-y: hidden;
    border: 1px solid var(--color-purple-light);
    border-radius: 16px;
    background: #ffffff;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-x;
    scrollbar-width: none;
}

.mobile-stage-carousel::-webkit-scrollbar {
    display: none;
}

.mobile-stage-carousel__pill {
    flex: 0 0 auto;
    height: 36px;
    padding: 0 14px;
    border: 1.5px solid var(--pill-color, #733E87);
    border-radius: 999px;
    background: #ffffff;
    color: var(--pill-text, #733E87);
    font-size: 14px;
    font-weight: 600;
    letter-spacing: -0.02em;
    white-space: nowrap;
    cursor: pointer;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.mobile-stage-carousel__pill.is-active {
    background: var(--pill-color, #733E87);
    color: var(--pill-active-text, #ffffff);
    box-shadow: 0 6px 14px -8px var(--pill-color, #733E87);
}

.mobile-stage-carousel__pill:active {
    transform: scale(0.97);
}

.mobile-stage-nav__arrow,
.mobile-stage-nav__pill {
    border: none;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.mobile-stage-nav__arrow {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 14px;
    background: #fff;
    color: #01062d;
    box-shadow: 0 6px 16px rgba(1, 6, 45, 0.06);
    transition: transform 140ms ease;
}

.mobile-stage-nav__arrow:active,
.mobile-stage-nav__pill:active {
    transform: scale(0.96);
}

.mobile-stage-nav__pill {
    flex: 1;
    min-width: 0;
    height: 40px;
    gap: 8px;
    padding: 0 16px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 700;
    box-shadow: 0 8px 18px rgba(1, 6, 45, 0.08);
    text-shadow: none;
    transition: transform 140ms ease;
}

.mobile-stage-nav__pill,
.mobile-stage-nav__pill span,
.mobile-stage-nav__pill iconify-icon {
    color: var(--stage-label, #1c1730) !important;
}

.mobile-stage-nav__pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mobile-stage-picker {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    max-height: min(52dvh, 420px);
    overflow-y: auto;
    padding: 4px 2px 8px;
}

.mobile-stage-chip {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 42px;
    padding: 8px 28px 8px 12px;
    border-radius: 999px;
    border: 1px solid #ece8f2;
    background: #fff;
    color: #2a2438;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
}

.mobile-stage-chip__label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #1c1730 !important;
}

.mobile-stage-chip.is-selected {
    border-color: #f0b429;
    box-shadow: 0 0 0 1px #f0b429;
}

.mobile-stage-chip__check {
    position: absolute;
    top: -4px;
    right: 8px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #f0b429;
    color: #fff;
    font-size: 11px;
}

.mobile-stage-confirm {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    flex: 1 1 auto;
    height: 48px;
    padding: 0 16px;
    border: none;
    border-radius: 999px;
    background: var(--gradient-primary) !important;
    color: #ffffff !important;
    font-size: 15px;
    font-weight: 700;
    line-height: 1;
    text-align: center;
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

.mobile-kanban-quick-btn--danger {
    grid-column: 1 / -1;
    border-color: #fecaca;
    background: #fef2f2;
    color: #b91c1c;
}

.kanban-card--mobile,
.lead-list-table tbody tr {
    -webkit-touch-callout: none;
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
/* ✅ Revert Alert Styles - يظهر في الأسفل يمين */
.revert-alert-wrapper {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 99999;
    max-width: 420px;
    width: 100%;
    transform: translateX(120%);
    opacity: 0;
    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    pointer-events: none;
}

.revert-alert-visible {
    transform: translateX(0);
    opacity: 1;
    pointer-events: auto;
}

.revert-alert {
    background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
    border: 1px solid #fca5a5;
    border-left: 5px solid #ef4444;
    border-radius: 14px;
    padding: 16px 20px;
    box-shadow: 0 12px 40px rgba(239, 68, 68, 0.25), 0 4px 16px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: flex-start;
    gap: 14px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    animation: revertAlertSlideUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes revertAlertSlideUp {
    from {
        transform: translateY(40px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

.revert-alert-icon-wrap {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fca5a5;
}

.revert-alert-icon {
    font-size: 22px;
    color: #dc2626;
    animation: revertIconPulse 1.5s ease-in-out infinite;
}

@keyframes revertIconPulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.15);
    }
}

.revert-alert-content {
    flex: 1;
    min-width: 0;
}

.revert-alert-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.revert-alert-title {
    font-size: 13px;
    font-weight: 700;
    color: #991b1b;
    letter-spacing: 0.3px;
}

.revert-alert-time {
    font-size: 11px;
    font-weight: 700;
    color: #dc2626;
    background: #fef2f2;
    padding: 2px 10px;
    border-radius: 12px;
    border: 1px solid #fca5a5;
    white-space: nowrap;
}

.revert-alert-message {
    font-size: 13px;
    color: #1f2937;
    margin: 0;
    line-height: 1.4;
    font-weight: 500;
}

.revert-alert-message strong {
    color: #991b1b;
}

.revert-alert-close {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.1);
    color: #991b1b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0;
}

.revert-alert-close:hover {
    background: rgba(239, 68, 68, 0.2);
    transform: scale(1.1);
}

.revert-alert-close iconify-icon {
    font-size: 14px;
}

/* ✅ Progress bar في الأسفل */
.revert-alert-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, #ef4444, #fca5a5);
    border-radius: 0 0 14px 14px;
    animation: revertAlertProgress 8s linear forwards;
    z-index: 1;
}

@keyframes revertAlertProgress {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .revert-alert-wrapper {
        bottom: 20px;
        right: 12px;
        max-width: calc(100% - 24px);
        left: 12px;
    }
    
    .revert-alert {
        padding: 14px 16px;
        gap: 10px;
    }
    
    .revert-alert-icon-wrap {
        width: 36px;
        height: 36px;
    }
    
    .revert-alert-icon {
        font-size: 18px;
    }
    
    .revert-alert-title {
        font-size: 12px;
    }
    
    .revert-alert-time {
        font-size: 10px;
        padding: 2px 8px;
    }
    
    .revert-alert-message {
        font-size: 12px;
    }
}

.board-head {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 0 8px;
}

.board-head > :deep(.lead-analytics-row) {
    flex: 1 1 auto;
    width: auto !important;
    min-width: 0;
    margin-bottom: 0;
}

.board-view-switch {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    align-self: center;
    gap: 4px;
    margin: 0 0 8px;
    padding: 4px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.board-view-switch__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 34px;
    padding: 0 14px;
    border: none;
    border-radius: 999px;
    background: transparent;
    color: #64748b;
    font-size: 13px;
    font-weight: 650;
    letter-spacing: -0.01em;
    cursor: pointer;
}

.board-view-switch__btn iconify-icon {
    font-size: 15px;
}

.board-view-switch__btn.is-active {
    background: var(--gradient-primary);
    color: #fff;
    -webkit-text-fill-color: #fff;
    box-shadow: 0 6px 16px rgba(var(--color-primary-rgb), 0.28);
}

.board-view-switch__btn.is-active iconify-icon {
    color: #fff;
}

.kanban-outer--list {
    overflow: hidden;
}

.kanban-outer--list .board-head,
.kanban-outer--list .lead-select-bar {
    flex-shrink: 0;
}

.kanban-outer--list:not(.kanban-outer--mobile) .kanban-container.kanban-container--list {
    display: flex;
    flex-direction: column;
    flex: 1 1 0 !important;
    height: auto !important;
    min-height: 0 !important;
    overflow: hidden !important;
    padding: 0 8px 8px;
}

.kanban-outer--mobile.kanban-outer--list .kanban-container {
    overflow: hidden !important;
    height: auto !important;
    min-height: 0;
}

.lead-list {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    width: 100%;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 18px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
}

.lead-list__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-shrink: 0;
    padding: 12px 16px;
    border-bottom: 1px solid #eef2f7;
}

.lead-list__count {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    letter-spacing: 0.01em;
}

.lead-list-empty {
    padding: 48px 16px;
    text-align: center;
    color: #64748b;
    font-size: 14px;
}

.lead-list-table-shell {
    position: relative;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
}

.lead-list-table-wrap {
    flex: 1 1 auto;
    width: 100%;
    min-height: 0;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}

.lead-list-nav {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 4;
    cursor: pointer;
}

.lead-list-nav--left {
    left: 14px;
}

.lead-list-nav--right {
    right: 14px;
}

.lead-list-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1120px;
}

.lead-list-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    padding: 12px 14px;
    background: #f8fafc;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    text-align: left;
    border-bottom: 1px solid #eef2f7;
    white-space: nowrap;
}

.lead-list-col-num,
.lead-list-num {
    width: 36px;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.lead-list-num {
    flex: 0 0 auto;
    width: auto;
    min-width: 18px;
}

.lead-list-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    background: transparent;
    overflow: hidden;
}

.lead-list-clip {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-list-table tbody tr {
    cursor: pointer;
    transition: background 0.15s ease;
}

.lead-list-table tbody tr:hover td {
    background: #f8fafc;
}

.lead-list-table tbody tr.is-selected td,
.lead-list-table tbody tr.is-selected:hover td {
    background: #eef2ff;
}

.lead-list-table tbody tr.is-highlighted td,
.lead-list-table tbody tr.is-highlighted:hover td {
    background: #fce4c8;
}

.lead-list-table__check {
    width: 42px;
    padding-right: 0 !important;
}

.lead-list-name {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
    max-width: 320px;
}

.lead-list-name__row {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.lead-list-name__title {
    font-size: 14px;
    font-weight: 650;
    color: #3730a3;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-list-name__source {
    font-size: 12px;
    color: #94a3b8;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-list-chip {
    flex-shrink: 0;
    border: none;
    border-radius: 999px;
    padding: 2px 7px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.4;
    cursor: pointer;
}

.lead-list-chip--alert {
    background: #fee2e2;
    color: #b91c1c;
}

.lead-list-chip--dup {
    background: #fef3c7;
    color: #92400e;
}

.lead-list-stage {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 128px;
    max-width: 180px;
}

.lead-list-stage__track {
    display: flex;
    gap: 3px;
    height: 6px;
}

.lead-list-stage__seg {
    flex: 1;
    border-radius: 99px;
    min-width: 4px;
}

.lead-list-stage__name {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    line-height: 1.2;
}

.lead-list-value {
    font-size: 13px;
    font-weight: 550;
    color: #334155;
}

.lead-list-value--phone {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.lead-list-muted {
    font-size: 13px;
    color: #94a3b8;
}

.lead-list-person {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 180px;
    min-width: 0;
    color: #1e293b;
    font-size: 13px;
    font-weight: 600;
}

.lead-list-person img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px #e2e8f0;
}

.lead-list-person span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-list-check {
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    cursor: pointer;
    font-size: 16px;
}

.lead-list-check.is-on {
    color: #4f46e5;
}

.lead-list-check--highlight {
    width: 22px;
    height: 22px;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    color: transparent;
    font-size: 14px;
}

.lead-list-check--highlight.is-on {
    border-color: #3f2d7a;
    background: #3f2d7a;
    color: #fff;
}

.lead-list-pager {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-shrink: 0;
    gap: 10px;
    margin: 0;
}

.lead-list-pager__status {
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.lead-list-pager__btn {
    height: 40px;
    padding: 0 14px;
    border: 1px solid var(--color-primary-border);
    border-radius: 10px;
    background: #fff;
    color: var(--color-primary);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.lead-list-pager__btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.lead-list-pager__btn:hover:not(:disabled),
.lead-archive-pager .lead-select-bar__btn:hover:not(:disabled) {
    background: var(--color-primary-soft);
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.lead-list-pager__status,
.lead-archive-pager > span {
    color: var(--color-primary);
}

.lead-list-cards {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    gap: 10px;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    padding: 10px 12px 16px;
}

.kanban-outer--list:not(.kanban-outer--mobile) .lead-list {
    flex: 1 1 0;
    min-height: 0;
}

.kanban-outer--list:not(.kanban-outer--mobile) .lead-list-cards,
.kanban-outer--list:not(.kanban-outer--mobile) .lead-list-table-shell {
    flex: 1 1 0;
    height: 0;
    min-height: 0;
}

.kanban-outer--list:not(.kanban-outer--mobile) .lead-list-table-wrap {
    overflow-y: auto;
}

.lead-list-cards--grid {
    display: grid;
    grid-template-columns: 1fr;
    grid-auto-rows: max-content;
    align-content: start;
    gap: 12px;
    padding: 12px 12px 16px;
    align-items: stretch;
}

.lead-list-card {
    border: 1px solid #eef2f7;
    border-radius: 16px;
    background: #fff;
    padding: 14px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    cursor: pointer;
}

.lead-list-card.is-selected {
    border-color: #c7d2fe;
    background: #f5f7ff;
}

.lead-list-card__top {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.lead-list-card .lead-list-name {
    max-width: none;
    flex: 1;
}

.lead-list-card .lead-list-name__title {
    white-space: normal;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.lead-list-card .lead-list-stage {
    max-width: none;
    margin-top: 12px;
}

.lead-list-card__meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 12px;
    margin-top: 12px;
}

.lead-list-kicker {
    display: block;
    margin-bottom: 2px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
}

.lead-list-card .lead-list-person {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid #f1f5f9;
    max-width: none;
}

@media (max-width: 768px) {
    .board-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        column-gap: 8px;
        row-gap: 0;
        padding: 0 8px;
    }

    .board-head .mobile-stage-carousel {
        grid-column: 1 / -1;
        grid-row: 1;
        margin: 2px 0 8px;
    }

    .board-head .board-view-switch {
        display: none;
    }

    .board-head > :deep(.lead-analytics-row) {
        grid-column: 1 / -1;
        grid-row: 2;
    }

    .board-view-switch__label {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .board-view-switch__btn {
        width: 36px;
        height: 36px;
        padding: 0;
        justify-content: center;
    }
}
.merged-flag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 22px;
    padding: 0 8px 0 6px;
    border: 0;
    border-radius: 8px 8px 8px 3px;
    background: var(--gradient-primary, var(--color-primary));
    color: #fff !important;
    -webkit-text-fill-color: #fff;
    font-family: Montserrat, system-ui, sans-serif;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transform: translateY(-2px);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.38),
        inset 0 -2px 0 rgba(0, 0, 0, 0.16),
        0 3px 0 color-mix(in srgb, var(--color-primary) 62%, #000),
        0 7px 10px -4px rgba(var(--color-primary-rgb), 0.5);
    transition: transform 0.1s ease, box-shadow 0.1s ease, filter 0.1s ease;
}
.merged-flag iconify-icon { font-size: 13px; color: #fff; }
.merged-flag:hover { filter: brightness(1.06); }
.merged-flag:active {
    transform: translateY(1px);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.3),
        inset 0 -1px 0 rgba(0, 0, 0, 0.16),
        0 1px 0 color-mix(in srgb, var(--color-primary) 62%, #000);
}
.merged-flag--sm { height: 20px; font-size: 10px; flex-shrink: 0; }
</style>
