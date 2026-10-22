import { createApp, h } from 'vue'
import ConfirmationModal from '@/components/ConfirmationModal.vue'

const showConfirmation = (options = {}) => {
    return new Promise((resolve) => {
        // Default options
        const {
            title = 'Confirm Action',
            message = 'Are you sure you want to proceed?',
            confirmText = 'Confirm',
            extraText = '',
            cancelText = 'Cancel',
            type = 'warning',
            closeOnOverlay = true,
            zIndex = 9999,
            resolveChoice = false,
        } = options

        // Create a container for the modal
        const container = document.createElement('div')
        document.body.appendChild(container)

        let confirmationApp = null

        const finish = (choice, booleanValue) => {
            cleanup()
            resolve(resolveChoice ? choice : booleanValue)
        }

        const handleConfirm = () => finish('save', true)
        const handleExtra = () => finish('discard', false)
        const handleCancel = () => finish('continue', false)

        const cleanup = () => {
            if (confirmationApp) {
                setTimeout(() => {
                    confirmationApp.unmount()
                    if (container && container.parentNode) {
                        container.parentNode.removeChild(container)
                    }
                    confirmationApp = null
                }, 300)
            }
        }

        // Create Vue app instance for the modal
        confirmationApp = createApp({
            setup() {
                return () => h(ConfirmationModal, {
                    title,
                    message,
                    confirmText,
                    extraText,
                    cancelText,
                    type,
                    closeOnOverlay,
                    zIndex,
                    onConfirm: handleConfirm,
                    onExtra: handleExtra,
                    onCancel: handleCancel
                })
            }
        })

        // Mount the app
        confirmationApp.mount(container)
    })
}

export function showUnsavedChangesDialog() {
    return showConfirmation({
        title: 'Unsaved Changes',
        message: "You have changes that haven't been saved. What would you like to do?",
        confirmText: 'Save Changes',
        extraText: 'Discard Changes',
        cancelText: 'Continue Editing',
        type: 'success',
        closeOnOverlay: false,
        zIndex: 160000,
        resolveChoice: true,
    })
}

export { showConfirmation }
export default showConfirmation
