import { ref, watch, onBeforeUnmount } from 'vue'
import axios from '@/plugins/axios'

/**
 * Completeness of a deal against ALL fields its stage rules require up to (and including)
 * its current stage — computed by the backend stage validators, so it matches what the
 * stage-move gate demands.
 *
 * @param {() => object|null} getDeal  getter returning the deal prop
 * @param {() => {count:number, labels:string[]}} fallback  local check used if the request fails
 */
export function useDealCompleteness(getDeal, fallback) {
  const summary = ref({ count: 0, labels: [], loading: true })
  let timer = null
  let seq = 0

  async function load() {
    const deal = getDeal()
    if (!deal?.id) {
      summary.value = { count: 0, labels: [], loading: false }
      return
    }
    const mine = ++seq
    try {
      const { data } = await axios.get(`/deals/${deal.id}/completeness`)
      if (mine !== seq) return
      const missing = Array.isArray(data?.missing) ? data.missing : []
      summary.value = {
        count: missing.length,
        labels: missing.map((m) => m.label),
        loading: false,
      }
    } catch {
      if (mine !== seq) return
      const fb = fallback ? fallback() : { count: 0, labels: [] }
      summary.value = { ...fb, loading: false }
    }
  }

  // Deep: inline edits / property updates mutate the deal in place. Debounced so a burst
  // of changes (a save that refreshes several relations) triggers a single request.
  watch(
    getDeal,
    () => {
      clearTimeout(timer)
      timer = setTimeout(load, 300)
    },
    { deep: true, immediate: true }
  )

  onBeforeUnmount(() => clearTimeout(timer))

  return { completeness: summary, reloadCompleteness: load }
}
