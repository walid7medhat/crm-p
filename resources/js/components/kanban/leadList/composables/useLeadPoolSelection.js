import { ref, computed, shallowRef } from 'vue'

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
    if (next.has(id)) next.delete(id)
    else next.add(id)
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
    const next = new Set(selectedSet.value)
    for (let i = start; i <= end; i++) next.add(ids[i])
    replaceSet(next)
    lastClickedId.value = toId
  }

  function selectAllOnPage() {
    replaceSet(new Set(getOrderedIds()))
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
