import { ref, computed, shallowRef } from 'vue'

const MAX_SELECTION = 5

/**
 * Lead Pool multi-select state (IDs only, shallow Set for perf).
 * @param {() => Array<number|string>} getOrderedIds - visible lead ids in grid order
 */
export function useLeadPoolSelection(getOrderedIds) {
  const selectedSet = shallowRef(new Set())
  const anchorId = ref(null)
  const lastClickedId = ref(null)

  const selectedIds = computed(() => [...selectedSet.value])
  const count = computed(() => selectedSet.value.size)
  const hasSelection = computed(() => count.value > 0)

  function isSelected(id) {
    return selectedSet.value.has(id)
  }

  function replaceSet(next) {
    selectedSet.value = next
  }

  function toggle(id) {
    const next = new Set(selectedSet.value)

    if (next.has(id)) {
      next.delete(id)
    } else {
      // Maximum 5 selected leads
      if (next.size >= MAX_SELECTION) {
        window.$showNotification?.(
          'You can select a maximum of 5 leads at a time.',
          'warning'
        )

        return
      }

      next.add(id)
    }

    replaceSet(next)

    anchorId.value = id
    lastClickedId.value = id
  }

  function selectRange(toId) {
    const ids = getOrderedIds()
    const from = anchorId.value ?? lastClickedId.value

    if (from == null || !ids.length) {
      toggle(toId)
      return
    }

    const a = ids.indexOf(from)
    const b = ids.indexOf(toId)

    if (a === -1 || b === -1) {
      toggle(toId)
      return
    }

    const [start, end] = a < b ? [a, b] : [b, a]

    const rangeIds = ids.slice(start, end + 1)

    // Only add enough IDs to reach maximum 5
    const next = new Set(selectedSet.value)

    for (const id of rangeIds) {
      if (next.has(id)) continue

      if (next.size >= MAX_SELECTION) {
        break
      }

      next.add(id)
    }

    if (rangeIds.length + selectedSet.value.size > MAX_SELECTION) {
      window.$showNotification?.(
        'You can select a maximum of 5 leads at a time.',
        'warning'
      )
    }

    replaceSet(next)

    lastClickedId.value = toId
  }

  function selectAllOnPage() {
    const ids = getOrderedIds()

    // Select maximum 5 only
    const limitedIds = ids.slice(0, MAX_SELECTION)

    replaceSet(new Set(limitedIds))

    if (ids.length > MAX_SELECTION) {
      window.$showNotification?.(
        'Only 5 leads can be selected at a time.',
        'warning'
      )
    }
  }

  function clear() {
    replaceSet(new Set())
    anchorId.value = null
    lastClickedId.value = null
  }

  function handleCardClick(id, event) {
    if (event?.shiftKey) {
      selectRange(id)
      return
    }

    toggle(id)
  }

  function handleCheckboxClick(id, event) {
    event?.stopPropagation?.()

    if (event?.shiftKey) {
      selectRange(id)
      return
    }

    toggle(id)
  }

  return {
    selectedIds,
    count,
    hasSelection,
    isSelected,
    toggle,
    selectRange,
    selectAllOnPage,
    clear,
    handleCardClick,
    handleCheckboxClick,
  }
}