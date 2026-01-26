<template>
    <b-modal 
        id="view-lead-modal" 
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0"
    >
        <div class="view-lead-modal-content">
            <!-- Header -->
            <div class="modal-header-custom d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <span class="modal-title">Compleate CRM From “{{ lead?.title || 'Reem Hills' }}”</span>
                    <button class="settings-btn">
                        <iconify-icon icon="lucide:settings" class="text-secondary"></iconify-icon>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="header-actions d-flex gap-2">
                        <b-dropdown variant="outline-neutral-200" class="custom-dropdown" toggle-class="d-flex align-items-center gap-2 py-1 px-3 radius-8">
                            <template #button-content>
                                <span class="text-xs text-secondary-light">Documents</span>
                                <iconify-icon icon="lucide:chevron-down" class="text-xs"></iconify-icon>
                            </template>
                        </b-dropdown>
                        <b-dropdown variant="outline-neutral-200" class="custom-dropdown" toggle-class="d-flex align-items-center gap-2 py-1 px-3 radius-8">
                            <template #button-content>
                                <span class="text-xs text-secondary-light">Deal + Contact</span>
                                <iconify-icon icon="lucide:chevron-down" class="text-xs"></iconify-icon>
                            </template>
                        </b-dropdown>
                    </div>
                    <button class="close-btn" @click="show = false">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>
                </div>
            </div>

            <!-- Stages Progress -->
            <div class="stages-container px-4 py-3 border-bottom overflow-auto">
                <div class="stages-wrapper d-flex align-items-center gap-0">
                    <template v-for="(stage, index) in stages" :key="index">
                        <div class="stage-item d-flex align-items-center gap-2" :class="{ 'active': currentStage === stage.name, 'completed': isCompleted(stage.name) }">
                            <div class="stage-dot" :style="{ backgroundColor: stage.color }"></div>
                            <span class="stage-name text-nowrap">{{ stage.name }}</span>
                        </div>
                        <iconify-icon v-if="index < stages.length - 1" icon="lucide:chevrons-right" class="stage-separator mx-2"></iconify-icon>
                    </template>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container px-4 border-bottom">
                <div class="d-flex gap-4">
                    <button class="tab-item active">General</button>
                    <button class="tab-item">History</button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="modal-body-custom p-4 bg-light-gray">
                <div class="row g-4">
                    <!-- Left Column: Lead Information -->
                    <div class="col-md-4">
                        <div class="info-card bg-white p-4 radius-12 shadow-sm h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="section-title mb-0">Lead Information</h6>
                                <iconify-icon icon="lucide:paperclip" class="text-warning"></iconify-icon>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Stages</label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stage-dot-small" style="background-color: #FAA300;"></div>
                                    <span class="info-value">Follow-up / Contacted</span>
                                </div>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Salutation</label>
                                <span class="info-value">Mr.</span>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">First Name</label>
                                <span class="info-value">{{ lead?.name?.split(' ')[0] || 'Ahamd Mahfouz' }}</span>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Last Name</label>
                                <span class="info-value">{{ lead?.name?.split(' ').slice(1).join(' ') || '----' }}</span>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Phone Number</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="info-value">+971 56 123 4567</span>
                                    <span class="text-xs text-secondary-light">(Work Phone)</span>
                                    <iconify-icon icon="lucide:edit-2" class="text-primary text-xs cursor-pointer"></iconify-icon>
                                </div>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Email</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="info-value">usertestmail@gmail.com</span>
                                    <span class="text-xs text-secondary-light">(Work)</span>
                                    <iconify-icon icon="lucide:edit-2" class="text-primary text-xs cursor-pointer"></iconify-icon>
                                </div>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Secondary Phone</label>
                                <span class="info-value">----</span>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Comment</label>
                                <p class="info-value text-xs line-height-1-5">
                                    Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's <a href="#" class="text-primary text-decoration-none">Read More</a>
                                </p>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">what's your budget</label>
                                <span class="info-value">45000 AED</span>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Bedrooms</label>
                                <span class="info-value">4 BHK</span>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Purpose Of Purchase</label>
                                <span class="info-value">Lorum ipsum dummy text</span>
                            </div>

                            <div class="info-group mb-3">
                                <label class="info-label">Source</label>
                                <span class="info-value">{{ lead?.source || 'Mata Ads - Lead Form' }}</span>
                            </div>

                            <div class="info-group mb-4">
                                <label class="info-label">Source Information</label>
                                <span class="info-value">Lorum ipsum dummy text</span>
                            </div>

                            <!-- Responsible Person -->
                            <div class="responsible-person-box p-3 radius-8 border">
                                <label class="info-label mb-2">Responsible Person</label>
                                <div class="d-flex align-items-center gap-3">
                                    <img :src="lead?.responsible?.avatar || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'" class="avatar-md rounded-circle" />
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-xs text-secondary-light">Name</span>
                                            <span class="text-xs fw-medium">: {{ lead?.responsible?.name || 'Ahmad Mahfoz' }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-xs text-secondary-light">Email</span>
                                            <span class="text-xs fw-medium">: {{ lead?.responsible?.email || 'testuseremail@gmail.com' }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-xs text-secondary-light">Position</span>
                                            <span class="text-xs fw-medium">: {{ lead?.responsible?.position || 'UI/UX Designer' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Match List -->
                            <div class="match-list-section mt-4">
                                <h6 class="section-title mb-3">Match List ( REEM Hills )</h6>
                                <div class="match-card p-2 radius-8 border d-flex gap-2">
                                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s" class="match-img radius-4" />
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="text-xs fw-bold text-primary">AED 1,475,100</span>
                                            <iconify-icon icon="lucide:arrow-up-right" class="text-warning text-xs"></iconify-icon>
                                        </div>
                                        <div class="text-xs fw-medium mb-1">Cozy 1BR+Balcony | Amara</div>
                                        <div class="d-flex align-items-center gap-1 text-xxs text-secondary-light">
                                            <iconify-icon icon="lucide:map-pin"></iconify-icon>
                                            <span>Amara, Reem Hills, Al Reem Island, AbuDhabi, UAE</span>
                                        </div>
                                    </div>
                                </div>
                                <a href="#" class="text-xs text-primary text-decoration-none mt-2 d-inline-block">Explore More</a>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Activity & Comments -->
                    <div class="col-md-8">
                        <div class="activity-card bg-white p-4 radius-12 shadow-sm h-100">
                            <!-- Activity/Comments Toggle -->
                            <div class="d-flex gap-2 mb-4 bg-light-gray p-1 radius-100 w-fit-content">
                                <button class="btn-toggle active d-flex align-items-center gap-2 px-3 py-1 radius-100">
                                    <iconify-icon icon="lucide:clock-3"></iconify-icon>
                                    Activity
                                </button>
                                <button class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100">
                                    <iconify-icon icon="lucide:message-square"></iconify-icon>
                                    Comments
                                </button>
                            </div>

                            <div class="comment-input-section mb-5">
                                <label class="text-xs fw-medium mb-2 d-block">Contact Customer</label>
                                <div class="comment-box border radius-12 p-3">
                                    <textarea class="form-control border-0 p-0 text-sm shadow-none" placeholder="Type @ to mention someone" rows="4"></textarea>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="d-flex align-items-center gap-2 border radius-8 px-3 py-1">
                                                <iconify-icon icon="lucide:calendar" class="text-secondary"></iconify-icon>
                                                <span class="text-xs text-secondary-light">Wed, January 21, 6:00 pm</span>
                                            </div>
                                            <button class="notification-btn">
                                                <iconify-icon icon="lucide:bell" class="text-warning"></iconify-icon>
                                            </button>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <b-dropdown variant="outline-neutral-200" class="custom-dropdown" toggle-class="d-flex align-items-center gap-2 py-1 px-3 radius-8">
                                                <template #button-content>
                                                    <span class="text-xs text-secondary-light">Actions</span>
                                                    <iconify-icon icon="lucide:chevron-down" class="text-xs"></iconify-icon>
                                                </template>
                                            </b-dropdown>
                                            <button class="btn btn-light radius-100 px-4 text-sm">Cancel</button>
                                            <button class="btn btn-primary radius-100 px-4 text-sm">Save</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="lead-activity-timeline">
                                <h6 class="section-title mb-4">Lead Activity</h6>
                                
                                <div class="timeline-group mb-4">
                                    <div class="timeline-item d-flex gap-3 mb-4">
                                        <div class="timeline-icon bg-success-soft text-success radius-100 p-2 h-fit-content">
                                            <iconify-icon icon="lucide:refresh-cw"></iconify-icon>
                                        </div>
                                        <div class="timeline-content flex-grow-1">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-sm fw-medium">Stage Changed</span>
                                                <span class="text-xs text-secondary-light">3:15 PM</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-xs text-secondary-light">Follow-up / Contacted</span>
                                                <iconify-icon icon="lucide:chevrons-right" class="text-xs text-secondary-light"></iconify-icon>
                                                <div class="d-flex align-items-center gap-1">
                                                    <div class="stage-dot-small" style="background-color: #10B981;"></div>
                                                    <span class="text-xs fw-medium">Qualified</span>
                                                </div>
                                            </div>
                                        </div>
                                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s" class="avatar-sm rounded-circle" />
                                    </div>

                                    <div class="timeline-item d-flex gap-3 mb-4">
                                        <div class="timeline-icon bg-success-soft text-success radius-100 p-2 h-fit-content">
                                            <iconify-icon icon="lucide:refresh-cw"></iconify-icon>
                                        </div>
                                        <div class="timeline-content flex-grow-1">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-sm fw-medium">Stage Changed</span>
                                                <span class="text-xs text-secondary-light">3:15 PM</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-xs text-secondary-light">Qualified</span>
                                                <iconify-icon icon="lucide:chevrons-right" class="text-xs text-secondary-light"></iconify-icon>
                                                <div class="d-flex align-items-center gap-1">
                                                    <div class="stage-dot-small" style="background-color: #3B82F6;"></div>
                                                    <span class="text-xs fw-medium">Future Respected</span>
                                                </div>
                                            </div>
                                        </div>
                                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s" class="avatar-sm rounded-circle" />
                                    </div>
                                </div>

                                <div class="timeline-date mb-3">
                                    <span class="text-xs fw-bold text-secondary-light">15 AUG, 2025</span>
                                </div>

                                <div class="timeline-item d-flex gap-3 mb-4">
                                    <div class="timeline-icon bg-success-soft text-success radius-100 p-2 h-fit-content">
                                        <iconify-icon icon="lucide:refresh-cw"></iconify-icon>
                                    </div>
                                    <div class="timeline-content flex-grow-1">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-sm fw-medium">Stage Changed</span>
                                            <span class="text-xs text-secondary-light">3:15 PM</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-xs text-secondary-light">Future Respected</span>
                                            <iconify-icon icon="lucide:chevrons-right" class="text-xs text-secondary-light"></iconify-icon>
                                            <div class="d-flex align-items-center gap-1">
                                                <div class="stage-dot-small" style="background-color: #10B981;"></div>
                                                <span class="text-xs fw-medium">Converted</span>
                                            </div>
                                        </div>
                                    </div>
                                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s" class="avatar-sm rounded-circle" />
                                </div>

                                <div class="show-older text-center mt-4">
                                    <button class="btn btn-link text-primary text-xs text-decoration-none d-flex align-items-center gap-1 mx-auto">
                                        <iconify-icon icon="lucide:chevron-down"></iconify-icon>
                                        Show older
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { BModal, BDropdown } from 'bootstrap-vue-3'

const props = defineProps({
    modelValue: Boolean,
    lead: Object
})

const emit = defineEmits(['update:modelValue'])

const show = ref(props.modelValue)

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    emit('update:modelValue', val)
})

const stages = [
    { name: 'New Leads', color: '#00CFE8' },
    { name: 'Assigned', color: '#FAA300' },
    { name: 'Follow-up / Contacted', color: '#FAA300' },
    { name: 'Qualified', color: '#10B981' },
    { name: 'Future Prospected', color: '#3B82F6' },
    { name: 'Converted', color: '#10B981' },
    { name: 'Shared Leads', color: '#FAA300' },
    { name: 'Lost Lead', color: '#EF4444' },
    { name: 'Lead Pool', color: '#64748B' },
    { name: 'Unqualified', color: '#EF4444' }
]

const currentStage = 'Follow-up / Contacted'

const isCompleted = (stageName) => {
    const stageIndex = stages.findIndex(s => s.name === stageName)
    const currentIndex = stages.findIndex(s => s.name === currentStage)
    return stageIndex < currentIndex
}
</script>

<style scoped>
.view-lead-modal-content {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    font-family: 'Montserrat', sans-serif;
}

.modal-header-custom {
    background: #fff;
}

.modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #01062C;
}

.settings-btn, .close-btn, .notification-btn {
    background: none;
    border: none;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.close-btn {
    font-size: 20px;
    color: #000;
}

.stage-item {
    padding: 6px 12px;
    border-radius: 100px;
    border: 1px solid #E2E8F0;
    background: #fff;
    transition: all 0.2s;
}

.stage-item.active {
    background: #FAA30015;
    border-color: #FAA300;
}

.stage-item.active .stage-name {
    color: #01062C;
    font-weight: 600;
}

.stage-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.stage-name {
    font-size: 11px;
    font-weight: 500;
    color: #64748B;
}

.stage-separator {
    color: #CBD5E1;
    font-size: 12px;
}

.tab-item {
    background: none;
    border: none;
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 500;
    color: #64748B;
    position: relative;
    cursor: pointer;
}

.tab-item.active {
    color: #01062C;
}

.tab-item.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 2px;
    background: #FAA300;
}

.bg-light-gray {
    background-color: #F8FAFC;
}

.radius-12 { border-radius: 12px; }
.radius-8 { border-radius: 8px; }
.radius-4 { border-radius: 4px; }
.radius-100 { border-radius: 100px; }

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.info-label {
    display: block;
    font-size: 12px;
    color: #64748B;
    margin-bottom: 2px;
}

.info-value {
    font-size: 13px;
    font-weight: 600;
    color: #01062C;
}

.info-group {
    margin-bottom: 12px;
}

.responsible-person-box {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.match-card {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.btn-toggle {
    background: none;
    border: none;
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-toggle.active {
    background: #01062C;
    color: #fff;
    box-shadow: 0px 4px 8px rgba(1, 6, 44, 0.2);
}

.comment-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
}

.btn-primary {
    background: #01062C;
    border: none;
    font-weight: 500;
}

.btn-light {
    background: #F1F5F9;
    border: none;
    color: #475569;
    font-weight: 500;
}
</style>
