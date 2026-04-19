<template>
  <div class="dashboard-main-body city-settings-page">
    <Breadcrumb title="City Investment Settings" :breadcrumbs="[{ name: 'Market Assumptions' }]" />

    <div class="settings-grid">
      <div v-for="city in localRows" :key="city.city" class="city-card">
        <div class="city-head">
          <div>
            <p class="eyebrow">Market Defaults</p>
            <h6 class="ui-h-mini">{{ city.city }}</h6>
          </div>
          <span class="badge-soft">{{ city.is_default ? 'Global Default' : 'Regional Profile' }}</span>
        </div>

        <div class="field-grid">
          <label class="field">
            Down Payment (%)
            <input v-model.number="city.down_payment_percent" type="number" min="0" max="100">
          </label>
          <label class="field">
            Loan Interest (%)
            <input v-model.number="city.loan_interest_percent" type="number" min="0" max="100" step="0.01">
          </label>
          <label class="field">
            Hold Years
            <input v-model.number="city.hold_years" type="number" min="1" max="60">
          </label>
          <label class="field">
            Vacancy Rate (%)
            <input v-model.number="city.vacancy_rate_percent" type="number" min="0" max="100" step="0.01">
          </label>
        </div>

        <div class="actions">
          <button class="btn btn-primary btn-sm" @click="saveCity(city)">Save</button>
          <button class="btn btn-light btn-sm" @click="resetCity(city.city)">Reset</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import { useInvestmentAnalysis } from '@/composables/useInvestmentAnalysis.js'

const { citySettings, fetchCitySettings, updateCitySetting } = useInvestmentAnalysis()
const localRows = ref([])

watch(
  citySettings,
  (rows) => {
    localRows.value = (rows || []).map((r) => ({ ...r }))
  },
  { immediate: true, deep: true }
)

onMounted(async () => {
  await fetchCitySettings()
})

async function saveCity(city) {
  await updateCitySetting({
    city: city.city,
    purchase_price_min: city.purchase_price_min,
    purchase_price_max: city.purchase_price_max,
    down_payment_percent: city.down_payment_percent,
    loan_interest_percent: city.loan_interest_percent,
    hold_years: city.hold_years,
    vacancy_rate_percent: city.vacancy_rate_percent,
    is_default: city.is_default
  })
}

function resetCity(cityName) {
  const original = citySettings.value.find((r) => r.city === cityName)
  const idx = localRows.value.findIndex((r) => r.city === cityName)
  if (original && idx >= 0) localRows.value[idx] = { ...original }
}
</script>

<style scoped>
.settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; }
.city-card { background: linear-gradient(170deg, rgba(255,255,255,.97), rgba(255,255,255,.86)); border: 1px solid rgba(148,163,184,.3); border-radius: 1rem; padding: 1rem; box-shadow: 0 10px 24px rgba(15,23,42,.08); }
.city-head { display: flex; justify-content: space-between; gap: .6rem; margin-bottom: .8rem; }
.eyebrow { margin: 0; letter-spacing: .08em; text-transform: uppercase; font-size: .65rem; color: #6366f1; font-weight: 700; }
h5 { margin: .2rem 0 0; font-weight: 800; }
.badge-soft { background: #eef2ff; color: #4338ca; border-radius: 999px; padding: .2rem .5rem; font-size: .72rem; font-weight: 700; height: fit-content; }
.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
.field { display: flex; flex-direction: column; gap: .3rem; font-size: .76rem; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; color: #475569; }
.field input { border: 1px solid #dbe3ee; border-radius: .72rem; padding: .48rem .62rem; font-size: .84rem; color: #0f172a; }
.actions { display: flex; gap: .45rem; margin-top: .8rem; }
@media (max-width: 640px) { .field-grid { grid-template-columns: 1fr; } }
</style>
