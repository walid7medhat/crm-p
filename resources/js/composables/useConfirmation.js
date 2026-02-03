import { createApp, h } from 'vue'
import ConfirmationModal from '@/components/ConfirmationModal.vue'

const showConfirmation = (options = {}) => {
    return new Promise((resolve) => {
        // Default options
        const {
            title = 'Confirm Action',
            message = 'Are you sure you want to proceed?',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            type = 'warning',
            closeOnOverlay = true
        } = options

        // Create a container for the modal
        const container = document.createElement('div')
        document.body.appendChild(container)

        let confirmationApp = null

        const handleConfirm = () => {
            cleanup()
            resolve(true)
        }

        const handleCancel = () => {
            cleanup()
            resolve(false)
        }

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
                    cancelText,
                    type,
                    closeOnOverlay,
                    onConfirm: handleConfirm,
                    onCancel: handleCancel
                })
            }
        })

        // Mount the app
        confirmationApp.mount(container)
    })
}

export default showConfirmation
