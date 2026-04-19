import { ref } from 'vue'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'

function buildRulesPayload(rules) {
  return (rules || []).map((r) => ({
    factor_name: r.factor_name,
    weight: Number(r.weight),
    low_value: r.low_value,
    medium_value: r.medium_value,
    high_value: r.high_value,
    direction: r.direction || 'higher_better',
  }))
}

/**
 * Debounced preview-score; always sends current rules so engine uses draft path.
 */
export function useSalesIntelligencePreview(rulesRef, delay = 420) {
  const preview = ref(null)
  const loading = ref(false)
  const error = ref(null)
  let timer = null

  function cancel() {
    if (timer) clearTimeout(timer)
    timer = null
  }

  function schedule(userId) {
    cancel()
    if (!userId) {
      preview.value = null
      return
    }
    timer = setTimeout(() => runNow(userId), delay)
  }

  async function runNow(userId) {
    if (!userId) return
    loading.value = true
    error.value = null
    try {
      preview.value = await salesIntelligenceApi.previewScore({
        user_id: userId,
        rules: buildRulesPayload(rulesRef.value),
      })
    } catch (e) {
      error.value = e?.message || 'Preview failed'
      preview.value = null
    } finally {
      loading.value = false
    }
  }

  return {
    preview,
    loading,
    error,
    schedule,
    runNow,
    cancel,
  }
}
