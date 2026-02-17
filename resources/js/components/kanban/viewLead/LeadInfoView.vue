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
            <label class="form-label-custom">Contact</label>
            <div class="info-value">{{ lead?.work_phone || '—' }}</div>
        </div>
        <div class="info-group">
            <label class="form-label-custom">Email</label>
            <div class="info-value">{{ lead?.email || '—' }}</div>
        </div>
        <div class="info-group">
            <label class="form-label-custom">Secondary Phone</label>
            <div class="info-value">{{ lead?.work_phone_2 || '—' }}</div>
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
            <label class="form-label-custom">Responsible Person</label>
            <div class="info-value">{{ lead?.responsible_person?.name || '—' }}</div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    lead: {
        type: Object,
        default: null
    }
})
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
</style>
