<template>
    <div class="tab-content other-settings-tab">
        <p class="section-title">Other Settings</p>

        <!-- Form Name -->
        <div class="field-group">
            <label class="field-label">Form Name</label>
            <v-select
                v-model="formName"
                :options="formNameOptions"
                :reduce="option => option.value"
                label="text"
                placeholder="Select form"
                class="custom-v-select form-name-select"
            >
                <template #open-indicator="{ attributes }">
                    <span v-bind="attributes" class="from-indicator-wrapper">
                        <iconify-icon icon="lucide:chevron-up" class="vs__open-indicator-icon up-icon"></iconify-icon>
                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon down-icon"></iconify-icon>
                    </span>
                </template>
            </v-select>
        </div>

        <!-- Responsible Person (shared component) -->
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
        <div class="checkbox-wrapper" @click="dontMakeResponsibleIfNotClockedIn = !dontMakeResponsibleIfNotClockedIn">
            <input
                type="checkbox"
                id="dont-make-responsible-checkbox"
                v-model="dontMakeResponsibleIfNotClockedIn"
                class="other-settings-checkbox"
                @click.stop
            />
            <label for="dont-make-responsible-checkbox" class="checkbox-label" @click.stop>
                Don't make user a responsible person if not clocked in or on scheduled break
            </label>
        </div>

        <!-- Action Buttons (inside card, bottom right) -->
        <div class="other-settings-footer">
            <button type="button" class="footer-btn cancel-btn" @click="handleCancel">Cancel</button>
            <button type="button" class="footer-btn save-btn" @click="handleSave">Save</button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelFormName: {
        type: String,
        default: ''
    },
    modelResponsiblePersonId: {
        type: Number,
        default: null
    },
    modelResponsiblePerson: {
        type: Object,
        default: null
    },
    modelDontMakeResponsibleIfNotClockedIn: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits([
    'update:model-form-name',
    'update:model-responsible-person-id',
    'update:model-responsible-person',
    'update:model-dont-make-responsible-if-not-clocked-in',
    'cancel',
    'save'
])

const formName = ref(props.modelFormName)
const formNameOptions = ref([
    { value: 'facebook-lead-form-jan-22', text: 'Facebook Lead Form of January 22' }
    // Add more options or load from API
])
const responsiblePersonId = ref(props.modelResponsiblePersonId)
const responsiblePerson = ref(props.modelResponsiblePerson)
const dontMakeResponsibleIfNotClockedIn = ref(props.modelDontMakeResponsibleIfNotClockedIn)
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
    emit('update:model-responsible-person', user)
}

function handleCancel() {
    emit('cancel')
}

function handleSave() {
    emit('save', {
        formName: formName.value,
        responsiblePersonId: responsiblePersonId.value,
        responsiblePerson: responsiblePerson.value,
        dontMakeResponsibleIfNotClockedIn: dontMakeResponsibleIfNotClockedIn.value
    })
}

watch(() => props.modelFormName, (v) => { formName.value = v })
watch(() => props.modelResponsiblePersonId, (v) => { responsiblePersonId.value = v })
watch(() => props.modelResponsiblePerson, (v) => { responsiblePerson.value = v })
watch(() => props.modelDontMakeResponsibleIfNotClockedIn, (v) => { dontMakeResponsibleIfNotClockedIn.value = v })

watch(formName, (v) => emit('update:model-form-name', v))
watch(responsiblePersonId, (v) => emit('update:model-responsible-person-id', v))
watch(responsiblePerson, (v) => emit('update:model-responsible-person', v), { deep: true })
watch(dontMakeResponsibleIfNotClockedIn, (v) => emit('update:model-dont-make-responsible-if-not-clocked-in', v))
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
    margin-bottom: 20px;
}

.field-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #1E293B;
}

/* Form Name dropdown - match FacebookLeadAdsTab from-select */
:deep(.form-name-select .vs__dropdown-toggle) {
    height: 44px;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
    display: flex;
    align-items: center;
}

:deep(.form-name-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
    display: flex;
    align-items: center;
    align-self: stretch;
    flex: 1;
    min-height: 0;
}

:deep(.form-name-select .vs__selected) {
    font-size: 14px;
    color: #1E293B;
    margin: 0;
    padding: 0;
    font-family: 'Montserrat', sans-serif;
    line-height: 44px;
    display: block;
}

:deep(.form-name-select .vs__search) {
    font-size: 14px;
    color: #64748B;
    margin: 0;
    padding: 0;
    height: 44px;
    line-height: 44px;
    border: none;
    background: transparent;
}

:deep(.form-name-select .vs__search::placeholder) {
    color: #94A3B8;
}

:deep(.form-name-select .vs__actions) {
    padding: 0 8px;
    display: flex;
    align-items: center;
}

:deep(.form-name-select .vs__clear) {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
}

:deep(.form-name-select .vs__open-indicator) {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    height: 100%;
}

:deep(.form-name-select .from-indicator-wrapper) {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    line-height: 1;
}

:deep(.form-name-select .vs__open-indicator-icon) {
    font-size: 14px;
    color: #94A3B8;
    line-height: 1;
}

:deep(.form-name-select .up-icon) {
    margin-bottom: -4px;
}

:deep(.form-name-select .down-icon) {
    margin-top: -4px;
}

:deep(.form-name-select.custom-v-select) {
    font-family: 'Montserrat', sans-serif;
}

:deep(.form-name-select .vs__dropdown-menu) {
    border: none;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    padding: 8px 0;
    margin-top: 4px;
    z-index: 1100;
    border-radius: 8px;
    overflow: hidden;
    background: #FFFFFF;
}

:deep(.form-name-select .vs__dropdown-option) {
    padding: 8px 12px;
    font-size: 14px;
    color: #1E293B;
    font-family: 'Montserrat', sans-serif;
}

:deep(.form-name-select .vs__dropdown-option--highlight) {
    background: #F8FAFC !important;
    color: #1E293B !important;
}

.responsible-person-group {
    margin-bottom: 20px;
}

.responsible-person-group :deep(.other-settings-responsible-selector) {
    margin: 0;
}

.responsible-person-group :deep(.other-settings-responsible-selector .col-12) {
    margin-top: 0 !important;
}

/* Checkbox - orange when checked */
.checkbox-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    margin-bottom: 24px;
}

.other-settings-checkbox {
    width: 18px;
    height: 18px;
    border: 2px solid #CBD5E1;
    border-radius: 4px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    position: relative;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
    margin: 0;
    flex-shrink: 0;
    margin-top: 2px;
}

.other-settings-checkbox:checked {
    background-color: #FAA300;
    border-color: #FAA300;
}

.other-settings-checkbox:checked::after {
    content: "";
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%) rotate(45deg);
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    border-radius: 0;
}

.other-settings-checkbox:hover {
    border-color: #FAA300;
}

.checkbox-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 400;
    color: #64748B;
    cursor: pointer;
    user-select: none;
    line-height: 1.5;
    margin: 0;
}

/* Footer buttons inside card */
.other-settings-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 8px;
    border-top: none;
}

.footer-btn {
    padding: 10px 24px;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.cancel-btn {
    background: #FFFFFF;
    color: #64748B;
    border: 1px solid #E2E8F0;
}

.cancel-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.save-btn {
    background: #01062C;
    color: #FFFFFF;
}

.save-btn:hover {
    background: #020A3D;
}
</style>
