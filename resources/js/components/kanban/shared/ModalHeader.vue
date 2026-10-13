<template>
    <div class="header-modal-wrapper d-flex justify-content-between align-items-center mx-3 border-bottom">
        <span class="header-modal-title">{{ title }}</span>
        <button type="button" class="header-close-btn-top" aria-label="Close" @click="$emit('close')">
            <iconify-icon icon="lucide:x"></iconify-icon>
        </button>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true
    }
})

defineEmits(['close'])
</script>

<style scoped>
.header-modal-wrapper {
    border-bottom: 1px solid #F4F4F4;
    padding-bottom: 12px !important;
}

.px-3 {
    padding-left: 1.5rem !important;
    padding-right: 1.5rem !important;
}
.header-modal-title {
    font-family: Montserrat;
    font-weight: 600;
    font-style: SemiBold;
    font-size: 16px;
    color: #0B0736;
}

.header-close-btn-top {
    position: relative;
    top: auto;
    right: auto;
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 2px solid #ffffff;
    border-radius: 50%;
    background: var(--gradient-primary);
    color: #ffffff !important;
    box-shadow:
        0 0 0 3px rgba(var(--color-primary-rgb), 0.22),
        0 8px 16px rgba(var(--color-primary-hover-rgb), 0.28);
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.header-close-btn-top:hover {
    color: #ffffff !important;
    background: var(--gradient-primary-hover);
    transform: scale(1.06);
}
.header-close-btn-top iconify-icon,
.header-close-btn-top svg {
    color: #ffffff !important;
    width: 16px;
    height: 16px;
}

@media (max-width: 768px) {
    .header-modal-wrapper {
        position: relative;
        margin: 0 !important;
        padding: 8px 12px 12px !important;
        border-bottom: 1px solid #eef2f7;
    }

    .header-modal-title {
        font-size: 18px;
        font-weight: 700;
        flex: 1 1 auto;
        min-width: 0;
        padding-right: 8px;
    }

    .header-close-btn-top {
        position: relative;
        top: auto;
        right: auto;
        width: 34px;
        height: 34px;
        color: #ffffff !important;
    }
}
</style>
