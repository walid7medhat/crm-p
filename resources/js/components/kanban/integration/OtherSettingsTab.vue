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
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
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
    }
})

const emit = defineEmits([
    'update:integrationName',
    'update:responsiblePersonId',
    'update:responsiblePerson',
    'update:dontMakeResponsibleIfNotClockedIn'
])

const integrationName = ref(props.integrationName)
const responsiblePersonId = ref(props.responsiblePersonId)
const responsiblePerson = ref(props.responsiblePerson)
const dontMakeResponsibleIfNotClockedIn = ref(props.dontMakeResponsibleIfNotClockedIn)
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

watch(() => props.integrationName, (v) => { integrationName.value = v })
watch(() => props.responsiblePersonId, (v) => { responsiblePersonId.value = v })
watch(() => props.responsiblePerson, (v) => { responsiblePerson.value = v })
watch(() => props.dontMakeResponsibleIfNotClockedIn, (v) => { dontMakeResponsibleIfNotClockedIn.value = v })

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
    margin-bottom: 20px;
}

.field-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #1E293B;
}

.form-input {
    width: 100%;
    height: 44px;
    padding: 0 16px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #1E293B;
    background: #FFFFFF;
    transition: all 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #01062C;
    box-shadow: 0 0 0 3px rgba(1, 6, 44, 0.1);
}

.form-input::placeholder {
    color: #94A3B8;
}

.responsible-person-group {
    margin-bottom: 20px;
}

/* Checkbox */
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
</style>