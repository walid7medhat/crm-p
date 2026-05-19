<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isVisible" class="confirmation-modal-overlay" @click="handleOverlayClick">
                <div class="confirmation-modal" @click.stop>
                    <div class="confirmation-modal-header">
                        <p class="confirmation-modal-title">{{ title }}</p>
                        <button class="confirmation-modal-close" @click="handleCancel">
                            <iconify-icon icon="lucide:x"></iconify-icon>
                        </button>
                    </div>
                    <div class="confirmation-modal-body">
                        <p class="confirmation-modal-message">{{ message }}</p>
                    </div>
                    <div class="confirmation-modal-footer">
                        <button class="confirmation-btn cancel-btn" @click="handleCancel">
                            {{ cancelText }}
                        </button>
                        <button 
                            class="confirmation-btn confirm-btn" 
                            :class="confirmButtonClass"
                            @click="handleConfirm"
                        >
                            {{ confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Confirm Action'
    },
    message: {
        type: String,
        default: 'Are you sure you want to proceed?'
    },
    confirmText: {
        type: String,
        default: 'Confirm'
    },
    cancelText: {
        type: String,
        default: 'Cancel'
    },
    type: {
        type: String,
        default: 'warning', // warning, danger, info, success
        validator: (value) => ['warning', 'danger', 'info', 'success'].includes(value)
    },
    closeOnOverlay: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['confirm', 'cancel', 'close'])

const isVisible = ref(false)

onMounted(() => {
    // Show modal when component is mounted
    isVisible.value = true
})

const confirmButtonClass = computed(() => {
    return {
        'btn-danger': props.type === 'danger' || props.type === 'warning',
        'btn-primary': props.type === 'info',
        'btn-success': props.type === 'success'
    }
})

const show = () => {
    isVisible.value = true
}

const hide = () => {
    isVisible.value = false
}

const handleConfirm = () => {
    emit('confirm')
    hide()
}

const handleCancel = () => {
    emit('cancel')
    hide()
}

const handleOverlayClick = () => {
    if (props.closeOnOverlay) {
        handleCancel()
    }
}

defineExpose({
    show,
    hide
})
</script>

<style scoped>
.confirmation-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(2px);
}

.confirmation-modal {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.2);
    max-width: 480px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.confirmation-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
}

.confirmation-modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #0B0736;
    margin: 0;
    font-family: 'Montserrat', sans-serif;
}

.confirmation-modal-close {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748B;
    border-radius: 4px;
    transition: all 0.2s;
}

.confirmation-modal-close:hover {
    background: #F1F5F9;
    color: #0B0736;
}

.confirmation-modal-body {
    padding: 24px;
    flex: 1;
    overflow-y: auto;
}

.confirmation-modal-message {
    font-size: 14px;
    font-weight: 400;
    color: #475569;
    line-height: 1.6;
    margin: 0;
    font-family: 'Montserrat', sans-serif;
}

.confirmation-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid #E5E7EB;
    background: #F8FAFC;
}

.confirmation-btn {
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-family: 'Montserrat', sans-serif;
}

.cancel-btn {
    background: #fff;
    color: #64748B;
    border: 1px solid #E2E8F0;
}

.cancel-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.confirm-btn {
    color: #fff;
}

.confirm-btn.btn-danger {
    background: #DC2626;
}

.confirm-btn.btn-danger:hover {
    background: #B91C1C;
}

.confirm-btn.btn-primary {
    background: #3B82F6;
}

.confirm-btn.btn-primary:hover {
    background: #2563EB;
}

.confirm-btn.btn-success {
    background: #10B981;
}

.confirm-btn.btn-success:hover {
    background: #059669;
}

/* Transition animations */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-active .confirmation-modal,
.modal-leave-active .confirmation-modal {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .confirmation-modal,
.modal-leave-to .confirmation-modal {
    transform: scale(0.95);
    opacity: 0;
}
</style>
