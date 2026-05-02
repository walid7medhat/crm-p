<template>
    <div class="activity-input-section">
        <label class="mb-2 d-block modal-title">Contact Customer</label>

        <div
            class="activity-box border radius-12 p-3 position-relative"
            :class="{ 'has-error': validationErrors.title }"
        >
            <textarea
                class="form-control border-0 p-0 text-sm shadow-none custom-textarea"
                :class="{ 'is-invalid': validationErrors.title }"
                placeholder="Type @ to mention someone"
                rows="4"
                v-model="activityText"
            ></textarea>
            <div v-if="validationErrors.title" class="invalid-feedback">
                {{ validationErrors.title[0] }}
            </div>
            <div class="activity-avatar">
                <div class="avatar-wrapper position-relative">
                    <img
                        v-if="!avatarError && currentUser?.avatar"
                        :src="currentUser.avatar"
                        class="avatar-sm rounded-circle"
                        @error="handleAvatarError"
                    />
                    <div v-else class="avatar-placeholder-sm">
                        <iconify-icon icon="lucide:user" class="avatar-icon-sm"></iconify-icon>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center margin-top-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="position-relative">
                        <button
                            class="activity-control-btn d-flex align-items-center gap-2"
                            :class="{ 'has-error': validationErrors.reminder_date }"
                            @click.stop="showDateTimePicker = true"
                        >
                            <iconify-icon icon="lucide:calendar" class="activity-control-icon"></iconify-icon>
                            <span class="activity-control-text">{{ formattedCustomDate }}</span>
                        </button>
                        <div v-if="validationErrors.reminder_date" class="invalid-feedback mt-1">
                            {{ validationErrors.reminder_date[0] }}
                        </div>
                    </div>
                    <div class="reminder-dropdown-wrapper position-relative">
                        <button
                            class="activity-control-btn activity-control-btn-bell d-flex align-items-center gap-2"
                            :class="{ 'has-error': validationErrors.reminder_option }"
                            @click="showReminderDropdown = !showReminderDropdown"
                        >
                            <iconify-icon icon="lucide:bell" class="activity-control-icon bell-icon"></iconify-icon>
                        </button>
                        <div
                            v-if="showReminderDropdown"
                            class="reminder-dropdown"
                            @click.stop
                        >
                            <div class="reminder-options">
                                <div
                                    class="reminder-option"
                                    v-for="option in reminderOptions"
                                    :key="option.value"
                                    @click="selectReminderOption(option.value)"
                                >
                                    <span class="reminder-option-text">{{ option.label }}</span>
                                    <div
                                        class="reminder-checkbox"
                                        :class="{ checked: reminders.includes(option.value) }"
                                    >
                                        <iconify-icon
                                            v-if="reminders.includes(option.value)"
                                            icon="lucide:check"
                                            class="check-icon"
                                        ></iconify-icon>
                                    </div>
                                </div>
                            </div>
                            <div class="custom-date-section">
                                <div
                                    class="custom-date-input"
                                    @click.stop="showDateTimePicker = true"
                                >
                                    <iconify-icon icon="lucide:calendar" class="custom-date-icon"></iconify-icon>
                                    <span class="custom-date-text">{{ formattedCustomDate }}</span>
                                    <iconify-icon icon="lucide:chevron-right" class="custom-date-arrow"></iconify-icon>
                                </div>
                            </div>
                        </div>
                        <div v-if="validationErrors.reminder_option" class="invalid-feedback mt-1">
                            {{ validationErrors.reminder_option[0] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer-custom">
            <button class="btn-cancel" @click="handleCancel">
                Cancel
            </button>
            <button
                class="btn-save"
                @click="handleSave"
                :disabled="isSubmitting"
            >
                <span v-if="isSubmitting">Saving...</span>
                <span v-else>Save</span>
            </button>
        </div>

        <DateTimePicker
            :show="showDateTimePicker"
            :model-value="reminderDate || selectedDateTime"
            @update:show="showDateTimePicker = $event"
            @update:model-value="handleCustomDateSelected"
            @apply="handleCustomDateApply"
            @cancel="handleCustomDateCancel"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import DateTimePicker from '../shared/DateTimePicker.vue'
import api from '@/plugins/axios'
import { formatReminderStyle } from '@/composables/useAdvancedDateModel'

const props = defineProps({
    dealId: {
        type: [Number, String],
        default: null,
    },
})

const emit = defineEmits(['activity-created'])

const activityText = ref('')
const selectedDateTime = ref(new Date())
const showReminderDropdown = ref(false)
const showDateTimePicker = ref(false)
const reminderDate = ref(new Date())
const reminders = ref([])
const avatarError = ref(false)
const currentUser = ref(null)
const isSubmitting = ref(false)
const validationErrors = ref({})
const errorMessage = ref('')

const reminderOptions = [
    { label: 'When event starts', value: '0' },
    { label: '15 minutes before', value: '15' },
    { label: '30 minutes before', value: '30' },
    { label: '1 hour before', value: '60' },
    { label: '2 hours before', value: '120' },
    { label: '1 day before', value: '1440' },
]

const formattedDateTime = computed(() => formatReminderStyle(selectedDateTime.value))

const formattedCustomDate = computed(() => {
    if (!reminderDate.value) {
        return formattedDateTime.value
    }
    return formatReminderStyle(reminderDate.value)
})

const selectReminderOption = (value) => {
    const index = reminders.value.indexOf(value)
    if (index > -1) {
        reminders.value.splice(index, 1)
    } else {
        reminders.value.push(value)
    }
}

const handleCustomDateSelected = (date) => {
    reminderDate.value = date
}

const handleCustomDateApply = (date) => {
    reminderDate.value = date
    showReminderDropdown.value = false
}

const handleCustomDateCancel = () => {}

const loadCurrentUser = () => {
    try {
        const userData = localStorage.getItem('user')
        if (userData) {
            currentUser.value = JSON.parse(userData)
        }
    } catch {
        currentUser.value = null
    }
}

watch(() => currentUser.value?.avatar, () => {
    avatarError.value = false
})

watch(() => activityText.value, () => {
    if (validationErrors.value.title) {
        delete validationErrors.value.title
        if (Object.keys(validationErrors.value).length === 0) {
            errorMessage.value = ''
        }
    }
})

watch(() => reminderDate.value, () => {
    if (validationErrors.value.reminder_date) {
        delete validationErrors.value.reminder_date
        if (Object.keys(validationErrors.value).length === 0) {
            errorMessage.value = ''
        }
    }
})

watch(() => reminders.value, () => {
    if (validationErrors.value.reminder_option) {
        delete validationErrors.value.reminder_option
        if (Object.keys(validationErrors.value).length === 0) {
            errorMessage.value = ''
        }
    }
}, { deep: true })

const handleAvatarError = () => {
    avatarError.value = true
}

const handleCancel = () => {
    activityText.value = ''
    reminderDate.value = new Date()
    reminders.value = []
    showReminderDropdown.value = false
    validationErrors.value = {}
    errorMessage.value = ''
}

const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    }
}

const handleSave = async () => {
    try {
        isSubmitting.value = true
        errorMessage.value = ''
        validationErrors.value = {}

        if (!props.dealId) {
            errorMessage.value = 'Deal ID is required'
            $showNotification('Deal ID is required', 'error')
            return
        }

        const reminderDateTime = reminderDate.value || selectedDateTime.value
        const formattedReminderDate = reminderDateTime instanceof Date
            ? reminderDateTime.toISOString()
            : new Date(reminderDateTime).toISOString()

        const payload = {
            deal_id: props.dealId,
            title: activityText.value,
            reminder_date: formattedReminderDate,
        }

        if (reminders.value.length > 0) {
            payload.reminders = reminders.value
        }

        const response = await api.post('/deals/activities', payload)
        handleCancel()
        $showNotification('Activity created successfully!', 'success')
        const activityData = response.data?.data || response.data
        emit('activity-created', activityData)
    } catch (error) {
        if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors || {}
            validationErrors.value = errors

            if (errors.title) {
                errorMessage.value = errors.title[0] || 'Title is required'
            } else if (errors.reminder_date) {
                errorMessage.value = errors.reminder_date[0] || 'Reminder date is invalid'
            } else if (errors.reminder_option) {
                errorMessage.value = errors.reminder_option[0] || 'Reminder option is invalid'
            } else {
                errorMessage.value = 'Please fix the validation errors below.'
            }

            $showNotification('Please check the form for errors', 'warning')
        } else {
            errorMessage.value = error.response?.data?.message || 'Failed to create activity. Please try again.'
            $showNotification(errorMessage.value, 'error')
        }
    } finally {
        isSubmitting.value = false
    }
}

const handleClickOutside = (event) => {
    if (!event.target.closest('.reminder-dropdown-wrapper')) {
        showReminderDropdown.value = false
    }
}

onMounted(() => {
    loadCurrentUser()
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
}

.custom-textarea {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

.custom-textarea:focus {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

.custom-textarea::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.activity-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
    padding-right: 60px !important;
}

.activity-avatar {
    position: absolute;
    top: 12px;
    right: 12px;
}

.avatar-wrapper {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.avatar-placeholder-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.avatar-icon-sm {
    font-size: 16px;
    color: #9CA3AF;
}

.activity-control-btn {
    background: transparent;
    border: 1px solid rgba(237, 237, 237, 1);
    padding: 5px;
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
}

.activity-control-btn-bell {
    padding: 7px;
}

.activity-control-btn:hover {
    background: #F8FAFC;
}

.activity-control-icon {
    font-size: 16px;
    color: #64748B;
}

.activity-control-icon.bell-icon {
    color: #FAA300;
}

.activity-control-text {
    font-size: 13px;
    font-weight: 400;
    color: #64748B;
}

.modal-footer-custom {
    padding-top: 15px;
    margin-top: 15px;
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
    text-align: center !important;
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
    text-align: center !important;
}

.btn-save:hover {
    background: #060a2b;
}

.radius-12 { border-radius: 12px; }
.radius-8 { border-radius: 8px; }
.radius-4 { border-radius: 4px; }
.radius-100 { border-radius: 100px; }

.reminder-dropdown-wrapper {
    position: relative;
}

.reminder-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 280px;
    padding: 12px 0;
    z-index: 1000;
    border: 1px solid #E2E8F0;
}

.reminder-options {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.reminder-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.2s;
}

.reminder-option:hover {
    background: #F8FAFC;
}

.reminder-option-text {
    font-size: 13px;
    font-weight: 400;
    color: #475569;
    flex: 1;
}

.reminder-checkbox {
    width: 18px;
    height: 18px;
    border: 1.5px solid #CBD5E1;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    transition: all 0.2s;
    flex-shrink: 0;
}

.reminder-checkbox.checked {
    background: #FAA300;
    border-color: #FAA300;
}

.check-icon {
    font-size: 12px;
    color: #fff;
    font-weight: bold;
}

.custom-date-section {
    padding: 8px 16px;
    margin-top: 4px;
    border-top: 1px solid #F1F5F9;
}

.custom-date-input {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}

.custom-date-input:hover {
    border-color: #CBD5E1;
    background: #F8FAFC;
}

.custom-date-icon {
    font-size: 16px;
    color: #64748B;
    flex-shrink: 0;
}

.custom-date-text {
    font-size: 13px;
    font-weight: 400;
    color: #475569;
    flex: 1;
    text-align: left;
}

.custom-date-arrow {
    font-size: 16px;
    color: #64748B;
    flex-shrink: 0;
}

.margin-top-2 {
    margin-top: 0.75rem !important;
}

.has-error {
    border-color: #dc3545 !important;
}

.is-invalid {
    border-color: #dc3545 !important;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 4px;
    font-size: 12px;
    color: #dc3545;
}

.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
