<template>
    <div class="row g-4">
        <!-- Left Column: Lead Information -->
        <div class="col-md-4">
            <div class="info-card bg-white p-3 radius-12 shadow-sm">
                <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="modal-title">Lead Information</span>
                </div>
                    <button>
                        <iconify-icon class="edit-icon-btn" color="#FAA300" icon="lucide:pencil"></iconify-icon>
                    </button>
                </div>

                <div class="info-group">
                    <label class="info-label">Stages</label>
                    <span class="info-value">{{ lead?.stage?.name }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">Salutation</label>
                    <span class="info-value">{{ lead?.salutation || '----' }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">First Name</label>
                    <span class="info-value">{{ lead?.first_name || '----' }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">Last Name</label>
                    <span class="info-value">{{ lead?.last_name || '----' }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">Phone Number</label>
                    <div class="d-flex align-items-center gap-2">
                        <span class="info-value">{{ lead?.whatsapp_number || '----' }}</span>
                    </div>
                </div>

                <div class="info-group">
                    <label class="info-label">Email</label>
                    <div class="d-flex align-items-center gap-2">
                        <span class="info-value">{{ lead?.email || '----' }}</span>
                    </div>
                </div>

                <div class="info-group">
                    <label class="info-label">Secondary Phone</label>
                    <span class="info-value">{{ lead?.work_phone_2 || '----' }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">Comment</label>
                    <p class="info-value text-xs line-height-1-5">
                        {{ lead?.comment || '----' }}
                        <!-- <a href="#" class="text-primary text-decoration-none">Read More</a> -->
                    </p>
                </div>

                <div class="info-group">
                    <label class="info-label">what's your budget</label>
                    <span class="info-value">{{ lead?.budget || '0' }} {{ lead?.currency || 'AED' }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">Bedrooms</label>
                    <span class="info-value">{{ lead?.bedrooms !== 'Studio' ? `${lead?.bedrooms} BHK` : 'Studio' }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">Purpose Of Purchase</label>
                    <span class="info-value">{{ lead?.purpose_buying || '----' }}</span>
                </div>

                <div class="info-group">
                    <label class="info-label">Source</label>
                    <span class="info-value">{{ lead?.lead_source || '----' }}</span>
                </div>

                <div class="info-group mb-3">
                    <label class="info-label">Source Information</label>
                    <span class="info-value">{{ lead?.source_information || '----' }}</span>
                </div>

                <!-- Responsible Person -->
                <div class="responsible-person-box p-3 radius-8 shadow-sm">
                    <label class="info-label mb-2">Responsible Person</label>
                    <div class="d-flex align-items-center gap-3">
                        <img :src="lead?.responsible_person?.avatar || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'" class="avatar-md rounded-circle" />
                        <div class="flex-grow-1">
                            <div class="d-flex mb-1">
                                <span class="text-xs text-secondary-light">Name</span>
                                <span class="text-xs fw-medium">: {{ lead?.responsible_person?.name || '----' }}</span>
                            </div>
                            <div class="d-flex mb-1">
                                <span class="text-xs text-secondary-light">Email</span>
                                <span class="text-xs fw-medium">: {{ lead?.responsible_person?.email || '----' }}</span>
                            </div>
                            <div class="d-flex">
                                <span class="text-xs text-secondary-light">Position</span>
                                <span class="text-xs fw-medium">: {{ lead?.responsible_person?.role_name || '----' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Activity & Comments -->
        <div class="col-md-8">
            <div class="activity-card bg-white p-3 radius-12 shadow-sm">
                <!-- Activity/Comments Toggle -->
                <div class="d-flex gap-2 mb-4 p-1 radius-100 w-fit-content toggle-buttons-container">
                    <button class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100">
                        <iconify-icon icon="lucide:clock-3"></iconify-icon>
                        Activity
                    </button>
                    <button class="btn-toggle active d-flex align-items-center gap-2 px-3 py-1 radius-100">
                        <iconify-icon icon="lucide:message-square"></iconify-icon>
                        Comments
                    </button>
                </div>

                <div class="comment-input-section">
                    <label class="mb-2 d-block modal-title">Contact Customer</label>
                    <div class="comment-box border radius-12 p-3">
                        <textarea class="form-control border-0 p-0 text-sm shadow-none custom-textarea" placeholder="Type @ to mention someone" rows="4"></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="d-flex align-items-center gap-3">
                                <button class="icon-btn d-flex align-items-center gap-2">
                                    <iconify-icon icon="lucide:paperclip" class="icon-btn-icon"></iconify-icon>
                                    <span class="icon-btn-text">File</span>
                                </button>
                                <button class="icon-btn d-flex align-items-center gap-2">
                                    <iconify-icon icon="lucide:file-text" class="icon-btn-icon"></iconify-icon>
                                    <span class="icon-btn-text">Create Document</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                 <!-- Comment Buttons -->
                <div class="modal-footer-custom">
                <button class="btn-cancel" @click="handleClose">
                    Cancel
                </button>
                <button class="btn-save" @click="handleSave">
                    Save
                </button>
            </div>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    lead: {
        type: Object,
        default: null
    }
})
</script>

<style scoped>
.info-card {
    border: 1px solid #F4F4F4;
}

.activity-card {
    border: 1px solid #F4F4F4;
}

.edit-icon-btn {
    vertical-align: middle !important;  
}
.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
}

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.info-label {
    display: block;
    font-size: 12px;
    font-weight: 300;
    color: #666666;
    margin-top: 5px;
    line-height: 10px;
}

.info-value {
    font-size: 12px;
    font-weight: 500;
    color: #000000;
    /* line-height: 10px; */
}

.info-group {
    margin-bottom: 15px;
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

.match-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.avatar-md {
    width: 48px;
    height: 48px;
    object-fit: cover;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.stage-dot-small {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.custom-textarea::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

.toggle-buttons-container {
    border: 1px solid #EDEDED;
    box-shadow: 2px 2px 20px 4px #7090B014;
} 
.btn-toggle {
    background: none;
    border: none;
    font-size: 13px;
    font-weight: 400;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-toggle.active {
    background: #01062C;
    color: #fff;
    font-weight: 400;
    box-shadow: 0px 4px 8px rgba(1, 6, 44, 0.2);
}

.comment-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
}

.notification-btn {
    background: none;
    border: none;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.icon-btn {
    background: transparent;
    border: none;
    padding: 4px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.icon-btn:hover {
    background: #E9ECEF;
}

.icon-btn-icon {
    font-size: 16px;
    color: #979797;
}

.icon-btn-text {
    font-size: 13px;
    font-weight: 400;
    color: #979797;
    white-space: nowrap;
}

.bg-light-gray {
    background-color: #F8FAFC;
}

.bg-success-soft {
    background-color: #D1FAE5;
}

.text-success {
    color: #10B981;
}

.radius-12 { border-radius: 12px; }
.radius-8 { border-radius: 8px; }
.radius-4 { border-radius: 4px; }
.radius-100 { border-radius: 100px; }

.h-fit-content {
    height: fit-content;
}

.timeline-date {
    padding-left: 44px;
}

.w-fit-content {
    width: fit-content;
}
.modal-footer-custom {
    padding-top: 15px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    border-top: 1px solid #F1F5F9;
}

.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 5px 20px;
    border-radius: 100px;
    font-size: 14px;
    color: #01062C;
    cursor: pointer;
}

.btn-cancel:hover {
    background: #E2E8F0 !important;
}

.btn-save {
    background: #01062C;
    border: none;
    padding: 5px 20px;
    border-radius: 100px;
    font-size: 14px;
    color: #fff;
    font-weight: 400;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-save:hover {
    background: #060a2b;
}
</style>
