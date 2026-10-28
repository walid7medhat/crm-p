import { ref } from 'vue'

const CLOSE_DELAY_MS = 180

/** Hover/click state for the merged-leads popover anchored to a card's merged flag. */
export function useMergedLeadsPopover() {
    const show = ref(false)
    const leadId = ref(null)
    const trigger = ref(null)
    let closeTimer = null

    const cancelClose = () => {
        clearTimeout(closeTimer)
        closeTimer = null
    }

    const open = (id, el) => {
        if (!id) return
        cancelClose()
        leadId.value = id
        trigger.value = el || null
        show.value = true
    }

    const scheduleClose = () => {
        cancelClose()
        closeTimer = setTimeout(() => {
            show.value = false
        }, CLOSE_DELAY_MS)
    }

    // Hover only on real desktops. Below 768px the popover is a bottom sheet whose backdrop
    // covers the flag, so hover would fire leave -> close -> enter -> open in a loop.
    const canHover = (event) =>
        event?.pointerType === 'mouse'
        && window.innerWidth > 768
        && window.matchMedia?.('(hover: hover) and (pointer: fine)').matches

    const hoverOpen = (id, event) => {
        if (!canHover(event)) return
        open(id, event.currentTarget)
    }
    const hoverLeave = (event) => {
        if (!canHover(event)) return
        scheduleClose()
    }

    return { show, leadId, trigger, open, scheduleClose, cancelClose, hoverOpen, hoverLeave }
}
