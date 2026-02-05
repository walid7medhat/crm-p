<template>
    <div>
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
            <label class="info-label">Contact</label>
            <div class="d-flex align-items-center gap-2">
                <span class="info-value">{{ lead?.work_phone || '----' }}</span>
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
            <label class="info-label mb-3">Responsible Person</label>
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-wrapper">
                    <img 
                        v-if="!avatarError && lead?.responsible_person?.avatar" 
                        :src="lead.responsible_person.avatar" 
                        class="avatar-md rounded-circle" 
                        @error="handleAvatarError"
                    />
                    <div v-else class="avatar-placeholder">
                        <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex mb-1">
                        <span class="text-xs text-secondary-light">Name</span>
                        <span class="text-xs fw-medium">: {{ lead?.responsible_person?.name || '----' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    lead: {
        type: Object,
        default: null
    }
})

const avatarError = ref(false)

// Watch for lead changes to reset avatar error
watch(() => props.lead?.responsible_person?.avatar, () => {
    avatarError.value = false
})

const handleAvatarError = () => {
    avatarError.value = true
}
</script>

<style scoped>
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
}

.info-group {
    margin-bottom: 15px;
}

.responsible-person-box {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.avatar-wrapper {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.avatar-md {
    width: 48px;
    height: 48px;
    object-fit: cover;
}

.avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.avatar-icon {
    font-size: 24px;
    color: #9CA3AF;
}

.radius-8 {
    border-radius: 8px;
}
</style>
