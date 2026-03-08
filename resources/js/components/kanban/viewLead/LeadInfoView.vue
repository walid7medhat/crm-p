<template>
    <div class="lead-info-view">
        <div class="info-group">
            <label class="form-label-custom">Lead Name</label>
            <div class="info-value">{{ lead?.lead_name || '—' }}</div>
        </div>
        
        <div class="info-group">
            <label class="form-label-custom">Salutation</label>
            <div class="info-value">{{ lead?.salutation || '—' }}</div>
        </div>
        <div class="info-group">
            <label class="form-label-custom">First Name</label>
            <div class="info-value">{{ lead?.first_name || '—' }}</div>
        </div>
        <div class="info-group">
            <label class="form-label-custom">Last Name</label>
            <div class="info-value">{{ lead?.last_name || '—' }}</div>
        </div>
        <div class="info-group">
            <label class="form-label-custom" >Contact</label>
            <div class="info-value" >
                  <span v-if="canView">{{ lead?.work_phone || '—' }}</span>
                 <span v-else>
                    {{ lead?.work_phone?.slice(0,3) || '' }}
                    <span class="blurred-stars">{{ maskValue(lead?.work_phone?.slice(3)) }}</span>
                </span>
            </div>
        </div>
        <div class="info-group">
            <label class="form-label-custom ">Email</label>
            <div class="info-value"  >
                <span v-if="canView">{{ lead?.email || '—' }}</span>
                <span v-else>
                    {{ lead?.email?.slice(0,3) || '' }}
                    <span class="blurred-stars">{{ maskValue(lead?.email?.slice(3))}}</span>
                </span>
            </div>
        </div>
        <div class="info-group" >
            <label class="form-label-custom">Secondary Phone</label>
            <div class="info-value" >
                 <span v-if="canView">{{ lead?.work_phone_2 || '—' }}</span>
                <span v-else>
                    {{ lead?.work_phone_2?.slice(0,3) || '' }}
                    <span class="blurred-stars">{{ maskValue(lead?.work_phone_2?.slice(3)) }}</span>
                </span>
            </div>
        </div>
        <div class="info-group" v-if="lead?.lead_source">
            <label class="form-label-custom">lead source</label>
            <div class="info-value">{{ lead?.lead_source || '—' }}</div>
        </div>
        <div class="info-group" v-if="lead?.lead_source">
            <label class="form-label-custom">Lead Branch Source</label>
            <div class="info-value">{{ lead?.lead_branch_source || '—' }}</div>
        </div>
        <div class="info-group" v-if="lead?.bedrooms">
            <label class="form-label-custom">Bedrooms</label>
            <div class="info-value">{{ lead?.bedrooms || '—' }}</div>
        </div>
        <div class="info-group">
            <label class="form-label-custom">Comment</label>
            <div class="info-value info-value-block">{{ lead?.comment || '—' }}</div>
        </div>
        <div class="info-group">
            <label class="form-label-custom">Budget</label>
            <div class="info-value">{{ lead?.budget != null ? lead.budget : '—' }} {{ lead?.currency || '' }}</div>
        </div>
         <template v-if="hasAdditionalFacebookQuestions">
            <div class="info-group" v-for="(answer, question) in facebookQuestions" :key="question">
                <label class="form-label-custom">{{ formatQuestion(question) }}</label>
            
                <div class="info-value">
                    <a v-if="question === 'link'" :href="answer" target="_blank" class="facebook-link">
                        {{ answer }}
                    </a>
            
                    <span v-else>
                        {{ answer }}
                    </span>
                </div>
            </div>
        </template>
        <div class="info-group">
             <div class="d-flex align-items-center gap-3">
                <div class="avatar-wrapper">
                    <img 
                        v-if="lead?.responsible_person?.avatar" 
                        :src="lead?.responsible_person?.avatar" 
                        class="avatar-md rounded-circle" 
                        :title="lead?.responsible_person?.name"
                     
                    />
                    <div v-else class="avatar-placeholder">
                        <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="">
                        <label class="form-label-custom">Responsible Person</label>
                        <div class="info-value">{{ lead?.responsible_person?.name || '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
        
           
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  lead: Object,
})

const getUserFromStorage = () => {
  try {
    const userData = localStorage.getItem('user')
    return userData ? JSON.parse(userData) : null
  } catch {
    return null
  }
}

const user = ref(getUserFromStorage())

const canView = computed(() => {
  if (!user.value?.roles) return false
  const isAdmin = user.value.roles.includes('super_admin') || user.value.roles.includes('admin')
  const isResponsible = props.lead?.responsible_person_id === user.value.id
  return isAdmin || isResponsible
})
const basicFields = ['email', 'phone', 'full_name', 'name', 'work_phone', 'first_name', 'last_name']

const facebookQuestions = computed(() => {
    if (!props.lead?.facebook_questions_answers) {
        return {}
    }
    
    // فلترة الحقول - نرجع الحقول اللي مش أساسية
    const fields = {}
    Object.keys(props.lead.facebook_questions_answers).forEach(key => {
        if (!basicFields.includes(key) && props.lead.facebook_questions_answers[key]) {
            fields[key] = props.lead.facebook_questions_answers[key]
        }
    })
    
    return fields
})

const hasAdditionalFacebookQuestions = computed(() => {
    return Object.keys(facebookQuestions.value).length > 0
})

// Function to mask value with stars (email or phone)
const maskValue = (value) => {
  if (!value) return ''

  return '★'.repeat(value.length)
}
const formatQuestion = (question) => {
  if (!question) return ''
  
  return question
    .replace(/_/g, ' ')      // يشيل _
    .replace(/\b\w/g, l => l.toUpperCase()) // يكبر أول حرف
}
</script>
<style scoped>
.lead-info-view .info-group {
    margin-bottom: 1rem;
}
.lead-info-view .form-label-custom {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #64748B;
    margin-bottom: 6px;
}
.lead-info-view .info-value {
    font-size: 14px;
    color: #1E293B;
}
.lead-info-view .info-value-block {
    white-space: pre-wrap;
}
.blurred-stars {
  filter: blur(3px);
  user-select: none;
}
.facebook-link {
  color: #2563eb; /* Blue color */
  text-decoration: underline;
  text-decoration-color: #2563eb;
}

.facebook-link:hover {
  color: #1d4ed8; /* Darker blue on hover */
  text-decoration: none;
}
</style>
