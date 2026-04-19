import { ref, watch } from 'vue'

/**
 * Pair of immediate + debounced refs for search fields.
 * @param {any} initial
 * @param {number} delayMs
 */
export function useDebouncedRef(initial, delayMs = 300) {
  const immediate = ref(initial)
  const debounced = ref(initial)
  let t = null

  watch(
    immediate,
    (v) => {
      clearTimeout(t)
      t = setTimeout(() => {
        debounced.value = v
      }, delayMs)
    },
    { flush: 'post' },
  )

  return { immediate, debounced }
}
