<template>
    <div class="tab-content other-settings-tab">
        <p class="section-title">Other Settings</p>

        <!-- Integration Name -->
        <div class="field-group">
            <label class="field-label">Integration Name</label>
            <input 
                v-model="integrationName"
                type="text"
                class="form-input"
                placeholder="Enter integration name"
            />
        </div>
            <!-- Track Toggle -->
        <div class="field-group track-group">
            <label class="field-label">Tracking Settings</label>
            <div class="track-toggle-wrapper">
                <div class="toggle-container" @click="toggleTrack">
                    <div class="toggle-switch" :class="{ 'active': trackEnabled }">
                        <div class="toggle-slider" :class="{ 'active': trackEnabled }"></div>
                    </div>
                    <span class="toggle-label" :class="{ 'active': trackEnabled }">
                        {{ trackEnabled ? 'Tracking Enabled' : 'Tracking Disabled' }}
                    </span>
                </div>
                <span class="toggle-description">Enable keyword tracking for leads from this integration</span>
            </div>

            <!-- Keyword Input (visible when track is enabled) -->
            <div v-if="trackEnabled" class="keyword-field-wrapper">
                <label class="field-label keyword-label">
                    Keyword to Track <span class="required-star">*</span>
                </label>
                <div class="input-field-wrapper">
                    <input 
                        type="text" 
                        class="form-input keyword-input" 
                        placeholder="e.g., urgent, vip, discount"
                        :value="trackKeyword"
                        @input="updateTrackKeyword"
                    />
                    <iconify-icon icon="lucide:search" class="input-icon"></iconify-icon>
                </div>
                <p class="keyword-hint">Leads containing this keyword in any field will be flagged</p>
            </div>
        </div>
        
        <!-- Responsible Person -->
        <div class="field-group responsible-person-group">
            <label class="field-label">Responsible Person</label>
            <ResponsiblePersonSelector
                v-model="responsiblePersonId"
                :responsible-person="responsiblePerson"
                :users="users"
                :validation-error="validationError"
                hide-section-title
                class="other-settings-responsible-selector"
                @user-selected="handleUserSelected"
            />
        </div>

        <!-- Checkbox -->
        <!--<div class="checkbox-wrapper" @click="dontMakeResponsibleIfNotClockedIn = !dontMakeResponsibleIfNotClockedIn">-->
        <!--    <input-->
        <!--        type="checkbox"-->
        <!--        id="dont-make-responsible-checkbox"-->
        <!--        v-model="dontMakeResponsibleIfNotClockedIn"-->
        <!--        class="other-settings-checkbox"-->
        <!--        @click.stop-->
        <!--    />-->
        <!--    <label for="dont-make-responsible-checkbox" class="checkbox-label" @click.stop>-->
        <!--        Don't make user a responsible person if not clocked in or on scheduled break-->
        <!--    </label>-->
        <!--</div>-->
    </div>
</template>

<script setup>
import { ref, watch, onMounted ,computed } from 'vue'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue'
import api from '@/plugins/axios'

const props = defineProps({
    integrationName: {
        type: String,
        default: ''
    },
    responsiblePersonId: {
        type: Number,
        default: null
    },
    responsiblePerson: {
        type: Object,
        default: null
    },
    dontMakeResponsibleIfNotClockedIn: {
        type: Boolean,
        default: true
    },
    trackEnabled: {
        type: Boolean,
        default: false
    },
    trackKeyword: {
        type: String,
        default: ''
    }
})

const emit = defineEmits([
    'update:integrationName',
    'update:responsiblePersonId',
    'update:responsiblePerson',
    'update:dontMakeResponsibleIfNotClockedIn',
     'update:trackEnabled',
    'update:trackKeyword'
])

const integrationName = ref(props.integrationName)
const responsiblePersonId = ref(props.responsiblePersonId)
const responsiblePerson = ref(props.responsiblePerson)
const dontMakeResponsibleIfNotClockedIn = ref(props.dontMakeResponsibleIfNotClockedIn)
const trackEnabled = ref(props.trackEnabled)
const trackKeyword = ref(props.trackKeyword)
const users = ref([])
const validationError = ref(null)

onMounted(() => {
    fetchUsers()
})

async function fetchUsers() {
    try {
        const response = await api.get('/available-responsible-persons')
        users.value = response.data?.data ?? response.data ?? []
    } catch (e) {
        users.value = []
    }
}

function handleUserSelected(user) {
    responsiblePerson.value = user
    emit('update:responsiblePerson', user)
}
const toggleTrack = () => {
    const newValue = !trackEnabled.value
    trackEnabled.value = newValue
    emit('update:trackEnabled', newValue)
    
    // Clear keyword if disabling track
    if (!newValue) {
        trackKeyword.value = ''
        emit('update:trackKeyword', '')
    }
}

const updateTrackKeyword = (event) => {
    trackKeyword.value = event.target.value
    emit('update:trackKeyword', event.target.value)
}


watch(() => props.integrationName, (v) => { integrationName.value = v })
watch(() => props.responsiblePersonId, (v) => { responsiblePersonId.value = v })
watch(() => props.responsiblePerson, (v) => { responsiblePerson.value = v })
watch(() => props.dontMakeResponsibleIfNotClockedIn, (v) => { dontMakeResponsibleIfNotClockedIn.value = v })
watch(() => props.trackEnabled, (newVal) => {
    trackEnabled.value = newVal
})

watch(() => props.trackKeyword, (newVal) => {
    trackKeyword.value = newVal
})
watch(integrationName, (v) => emit('update:integrationName', v))
watch(responsiblePersonId, (v) => emit('update:responsiblePersonId', v))
watch(responsiblePerson, (v) => emit('update:responsiblePerson', v), { deep: true })
watch(dontMakeResponsibleIfNotClockedIn, (v) => emit('update:dontMakeResponsibleIfNotClockedIn', v))
</script>

<style scoped>
.other-settings-tab {
    padding: 0;
}

.section-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #01062C;
    margin: 0 0 24px 0;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 24px;
}

.field-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #1E293B;
    margin-bottom: 4px;
}

/* Form Input Styles */
.form-input {
    width: 100%;
    height: 42px;
    padding: 0 16px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #1E293B;
    background: #FFFFFF;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #01062C;
    box-shadow: 0 0 0 3px rgba(1, 6, 44, 0.1);
}

.form-input::placeholder {
    color: #94A3B8;
    font-size: 13px;
}

/* Track Toggle Styles */
.track-group {
    background: #F8FAFC;
    padding: 16px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
}

.track-toggle-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.toggle-container {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    width: fit-content;
}

.toggle-switch {
    width: 48px;
    height: 24px;
    background-color: #CBD5E1;
    border-radius: 24px;
    position: relative;
    transition: all 0.3s ease;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
}

.toggle-switch.active {
    background-color: #01062C;
}

.toggle-slider {
    width: 20px;
    height: 20px;
    background-color: #FFFFFF;
    border-radius: 50%;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: transform 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-slider.active {
    transform: translateX(24px);
    background-color: #FFFFFF;
}

.toggle-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #64748B;
    transition: color 0.3s ease;
}

.toggle-label.active {
    color: #01062C;
    font-weight: 600;
}

.toggle-description {
    font-family: 'Montserrat', sans-serif;
    font-size: 12px;
    color: #94A3B8;
    margin-left: 60px;
}

/* Keyword Field Styles */
.keyword-field-wrapper {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px dashed #CBD5E1;
}

.keyword-label {
    color: #01062C;
    font-weight: 600;
    margin-bottom: 8px;
}

.required-star {
    color: #EF4444;
    margin-left: 2px;
}

.input-field-wrapper {
    position: relative;
    width: 100%;
}

.keyword-input {
    padding-right: 40px;
    border-color: #01062C;
    background-color: #FFFFFF;
}

.input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #94A3B8;
    pointer-events: none;
}

.keyword-hint {
    font-family: 'Montserrat', sans-serif;
    font-size: 11px;
    color: #94A3B8;
    margin-top: 6px;
    margin-bottom: 0;
}

/* Responsible Person Selector */
.responsible-person-group {
    margin-bottom: 24px;
}

:deep(.other-settings-responsible-selector) {
    width: 100%;
}

:deep(.other-settings-responsible-selector .responsible-person-selector) {
    border: none;
    padding: 0;
}

/* Checkbox Styles */
.checkbox-wrapper {
    margin-top: 8px;
}

.checkbox-container {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    user-select: none;
}

.custom-checkbox {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.checkmark {
    position: relative;
    display: inline-block;
    width: 18px;
    height: 18px;
    background-color: #FFFFFF;
    border: 2px solid #CBD5E1;
    border-radius: 4px;
    transition: all 0.2s ease;
    flex-shrink: 0;
    margin-top: 2px;
}

.checkbox-container:hover .checkmark {
    border-color: #FAA300;
}

.custom-checkbox:checked ~ .checkmark {
    background-color: #FAA300;
    border-color: #FAA300;
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
    left: 5px;
    top: 1px;
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.custom-checkbox:checked ~ .checkmark:after {
    display: block;
}

.checkbox-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #64748B;
    line-height: 1.5;
    flex: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .track-group {
        padding: 12px;
    }
    
    .toggle-description {
        margin-left: 0;
    }
}
</style>