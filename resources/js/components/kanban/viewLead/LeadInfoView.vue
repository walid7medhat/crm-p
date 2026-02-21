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
                    <span class="blurred-stars">{{ maskValue(lead?.email?.slice(3)) }}</span>
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
        <div class="info-group" v-if="lead.lead_source">
            <label class="form-label-custom">lead_source</label>
            <div class="info-value">{{ lead?.lead_source || '—' }}</div>
        </div>
        <div class="info-group" v-if="lead.bedrooms">
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

// Function to mask value with stars (email or phone)
const maskValue = (value) => {
  if (!value) return '—'

  // For email
  if (value.includes('@')) {
    const [name, domain] = value.split('@')
    const visibleChars = Math.min(3, name.length)
    const stars = '★'.repeat(name.length - visibleChars)
    return name.slice(0, visibleChars) + stars + '@' + domain
  }

  // For phone numbers
  const visibleDigits = Math.min(3, value.length)
  const stars = '★'.repeat(value.length - visibleDigits)
  return value.slice(0, visibleDigits) + stars
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
</style>
