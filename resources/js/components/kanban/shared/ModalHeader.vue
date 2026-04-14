<template>
    <div class="header-modal-wrapper d-flex justify-content-between align-items-center mx-3 border-bottom">
        <span class="header-modal-title">{{ title }}</span>
        <button class="header-close-btn-top" @click="$emit('close')">
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
    color: #01062C;
}

.header-close-btn-top {
    background: transparent;
    border: none;
    font-size: 20px;
    color: #000;
    font-weight: 500;
    cursor: pointer;
    margin-bottom: 10px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-close-btn-top:hover {
    opacity: 0.7;
}

@media (max-width: 768px) {
    .header-modal-wrapper {
        margin: 0 !important;
        padding: 4px 2px 10px !important;
        border-bottom: 1px solid #eef2f7;
    }

    .header-modal-title {
        font-size: 18px;
        font-weight: 700;
    }

    .header-close-btn-top {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        margin-bottom: 0;
        font-size: 18px;
    }
}
</style>
