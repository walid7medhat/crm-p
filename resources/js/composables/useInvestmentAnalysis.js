import { ref } from 'vue'
import api from '@/plugins/axios'

/**
 * City investment defaults + related API helpers for investment / city-settings pages.
 */
export function useInvestmentAnalysis() {
  const citySettings = ref([])

  async function fetchCitySettings() {
    try {
      const { data } = await api.get('/city-settings')
      if (data?.status && data?.data != null) {
        citySettings.value = Array.isArray(data.data) ? data.data : []
      } else {
        citySettings.value = []
      }
    } catch (e) {
      console.error('fetchCitySettings', e)
      citySettings.value = []
    }
  }

  async function updateCitySetting(payload) {
    await api.post('/city-settings/update', payload)
    await fetchCitySettings()
  }

  return {
    citySettings,
    fetchCitySettings,
    updateCitySetting,
  }
}
