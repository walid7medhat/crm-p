<template>
    <div class="history-content">
        <div class="row g-4">
            <div class="col-12">
                <div class="history-card bg-white p-4 radius-12 shadow-sm">
                    <h6 class="section-title mb-4">Lead History</h6>
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
.history-content {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.timeline-date {
    padding-left: 44px;
}

.bg-info-soft {
    background-color: #E0F2FE;
}

.text-info {
    color: #0EA5E9;
}

.bg-success-soft {
    background-color: #D1FAE5;
}

.text-success {
    color: #10B981;
}

.bg-warning-soft {
    background-color: #FEF3C7;
}

.text-warning {
    color: #FAA300;
}

.bg-primary-soft {
    background-color: #DBEAFE;
}

.text-primary {
    color: #3B82F6;
}

.h-fit-content {
    height: fit-content;
}

.radius-12 { border-radius: 12px; }
.radius-100 { border-radius: 100px; }
</style>
