<template>
  <div class="scoring-page">
    <div class="scoring-header">
      <div>
        <h1>Lead Scoring Engine</h1>
        <p>Adjust how AI evaluates and prioritizes leads in real-time.</p>
      </div>
      <div class="header-actions">
        <span class="status-badge"><span class="dot"></span>Active AI Model</span>
        <button class="btn btn-light" :disabled="loading || saving" @click="resetFromServer">Reset</button>
        <button class="btn btn-dark" :disabled="loading || saving" @click="saveSettings">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
      </div>
    </div>

    <div v-if="loading" class="panel">Loading scoring dashboard...</div>
    <div v-else class="layout">
      <div class="left-col">
        <section class="panel">
          <h2>1. Core Factors</h2>
          <p class="desc">Each factor shows exactly how many points it adds.</p>
          <div class="factor-grid">
            <article v-for="factor in factors" :key="factor.key" class="factor-card">
              <h3>{{ factor.title }}</h3>
              <p>{{ factor.description }}</p>
              <div class="option-grid">
                <button v-for="level in levels" :key="`${factor.key}-${level}`" type="button" class="option-btn" :class="{ active: getFactorLevel(factor.key) === level }" @click="setFactorLevel(factor.key, level)">
                  <strong>{{ capitalize(level) }}</strong>
                  <small>+{{ levelPoints(factor.key, level) }}</small>
                </button>
              </div>
            </article>
          </div>
        </section>

        <section class="panel">
          <h2>2. Priority Rules</h2>
          <p class="desc">Set Hot and Warm thresholds. Cold is below Warm.</p>
          <div class="threshold-grid">
            <label>Hot >= <input v-model.number="form.thresholds.hot" type="number" min="0" max="100" /></label>
            <label>Warm >= <input v-model.number="form.thresholds.warm" type="number" min="0" max="100" /></label>
            <div class="cold-note">Cold below <b>{{ form.thresholds.warm }}</b></div>
          </div>
          <div class="priority-bar"></div>
          <div class="priority-labels"><span>Cold</span><span>Warm >= {{ form.thresholds.warm }}</span><span>Hot >= {{ form.thresholds.hot }}</span></div>
        </section>

        <section class="panel">
          <h2>3. Automation</h2>
          <p class="desc">Enable smart automation options.</p>
          <div class="toggle-grid">
            <button class="toggle-card" :class="{ on: form.automation_flags.on_create }" @click="form.automation_flags.on_create = !form.automation_flags.on_create">Auto scoring</button>
            <button class="toggle-card" :class="{ on: form.automation_flags.on_update }" @click="form.automation_flags.on_update = !form.automation_flags.on_update">Recalculate on update</button>
            <button class="toggle-card" :class="{ on: aiEnhancementEnabled }" @click="setAiEnhancement(!aiEnhancementEnabled)">AI enhancement mode</button>
          </div>
        </section>

        <section class="panel">
          <h2>4. AI Mode</h2>
          <p class="desc">Pick how scoring should think.</p>
          <div class="mode-grid">
            <label class="mode-card" :class="{ selected: aiModeUi === 'rules' }"><input v-model="aiModeUi" value="rules" type="radio" />Rules only</label>
            <label class="mode-card" :class="{ selected: aiModeUi === 'ai' }"><input v-model="aiModeUi" value="ai" type="radio" />AI enhanced</label>
            <label class="mode-card" :class="{ selected: aiModeUi === 'hybrid' }"><input v-model="aiModeUi" value="hybrid" type="radio" />Hybrid <span class="pill">Recommended</span></label>
          </div>
        </section>
      </div>

      <div class="right-col">
        <section class="panel sticky">
          <h2>Live AI Preview</h2>
          <p class="desc">Changes update this lead instantly.</p>
          <div class="lead-signals">
            <div><span>Budget</span><b>{{ testLead.budget || 0 }}</b></div>
            <div><span>Source</span><b>{{ testLead.lead_source || 'N/A' }}</b></div>
            <div><span>WhatsApp</span><b>{{ testLead.whatsapp_number ? 'Yes' : 'No' }}</b></div>
            <div><span>Email</span><b>{{ testLead.email ? 'Yes' : 'No' }}</b></div>
          </div>
          <div class="live-output" :class="livePreview.priority">
            <div class="score-box">
              <span>Score</span>
              <strong>{{ animatedScore }}</strong>
            </div>
            <div class="priority-pill">{{ livePreview.priority }}</div>
            <div class="breakdown">
              <div v-for="line in liveBreakdown" :key="line.label"><span>{{ line.label }}</span><b>+{{ line.points }}</b></div>
            </div>
          </div>
          <div class="impact-note">If {{ strongestFactor.label }} is set to High, this lead gets <b>+{{ strongestFactor.highDelta }}</b> points.</div>
          <div class="test-inputs">
            <input v-model.number="testLead.budget" type="number" placeholder="Budget" />
            <input v-model="testLead.lead_source" type="text" placeholder="Source" />
            <input v-model="testLead.whatsapp_number" type="text" placeholder="WhatsApp" />
            <input v-model="testLead.email" type="text" placeholder="Email" />
            <textarea v-model="testLead.comment" rows="3" placeholder="Intent comment"></textarea>
            <button class="btn btn-light w-100" :disabled="testing" @click="runServerTest">{{ testing ? 'Refreshing...' : 'Refresh with Server AI Test' }}</button>
          </div>
        </section>

        <section class="panel">
          <h2>AI Insights</h2>
          <p class="desc">Suggestions and expected impact.</p>
          <div class="insight success">Increasing WhatsApp weight may improve conversion by <b>+14%</b>.</div>
          <div class="insight warn">Warm threshold is close to Hot. Wider gap improves triage clarity.</div>
          <div class="insight info">Hybrid mode usually gives best accuracy for mixed lead quality.</div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/plugins/axios'

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const levels = ['low', 'medium', 'high']

const factors = [
  { key: 'budget', label: 'budget', title: 'Budget Strength', description: 'Higher budget usually means stronger buying power.' },
  { key: 'source', label: 'source', title: 'Source Quality', description: 'Higher-quality channels convert more often.' },
  { key: 'whatsapp', label: 'whatsapp', title: 'WhatsApp Reachability', description: 'Immediate messaging increases conversion speed.' },
  { key: 'email', label: 'email', title: 'Email Reachability', description: 'Email helps with nurturing and follow-up.' },
  { key: 'recency', label: 'recency', title: 'Lead Freshness', description: 'Recent leads are easier to activate.' },
  { key: 'stage', label: 'stage', title: 'Stage Progress', description: 'Advanced stage indicates stronger momentum.' },
]

const factorBase = {
  budget: { low: 8, medium: 18, high: 30 },
  source: { low: 4, medium: 10, high: 18 },
  whatsapp: { low: 5, medium: 12, high: 20 },
  email: { low: 4, medium: 10, high: 16 },
  recency: { low: 6, medium: 14, high: 24 },
  stage: { low: 2, medium: 5, high: 10 },
}

const form = reactive({
  weights: { budget: 30, whatsapp: 15, email: 10, source: 10, recency: 20, stage: 5 },
  thresholds: { hot: 80, warm: 50 },
  automation_flags: { on_create: true, on_update: true, scheduled_enabled: true },
  ai_mode: 'fallback',
})

const testLead = reactive({
  budget: 1200000,
  email: 'lead@example.com',
  whatsapp_number: '+971500000000',
  lead_source: 'Facebook',
  comment: 'urgent and ready now',
  created_at: new Date().toISOString(),
})

const aiModeUi = computed({
  get() {
    if (form.ai_mode === 'off') return 'rules'
    if (form.ai_mode === 'strict') return 'ai'
    return 'hybrid'
  },
  set(v) {
    if (v === 'rules') form.ai_mode = 'off'
    else if (v === 'ai') form.ai_mode = 'strict'
    else form.ai_mode = 'fallback'
  },
})

const aiEnhancementEnabled = computed(() => form.ai_mode !== 'off')

const levelPoints = (key, level) => factorBase[key]?.[level] ?? 0
const capitalize = (v) => v.charAt(0).toUpperCase() + v.slice(1)

const getFactorLevel = (key) => {
  const value = Number(form.weights[key] || 0)
  const base = factorBase[key]
  if (!base) return 'medium'
  return levels.reduce((closest, current) => {
    const currentDiff = Math.abs(value - base[current])
    const closestDiff = Math.abs(value - base[closest])
    return currentDiff < closestDiff ? current : closest
  }, 'medium')
}

const setFactorLevel = (key, level) => {
  const points = levelPoints(key, level)
  form.weights[key] = points
}

const levelButtonClass = (key, level) => {
  const selected = getFactorLevel(key) === level
  if (selected) return 'border-slate-900 bg-slate-900 text-white shadow-sm'
  return 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
}

const setAiEnhancement = (enabled) => {
  if (!enabled) form.ai_mode = 'off'
  else if (form.ai_mode === 'off') form.ai_mode = 'fallback'
}

const normalizeThresholds = () => {
  form.thresholds.hot = Math.max(0, Math.min(100, Number(form.thresholds.hot || 0)))
  form.thresholds.warm = Math.max(0, Math.min(100, Number(form.thresholds.warm || 0)))
  if (form.thresholds.warm > form.thresholds.hot) form.thresholds.warm = form.thresholds.hot
}

const liveBreakdown = computed(() => {
  const lines = []
  if ((Number(testLead.budget) || 0) > 0) lines.push({ label: 'Budget Strength', points: Number(form.weights.budget || 0) })
  if ((testLead.lead_source || '').toLowerCase().includes('facebook')) lines.push({ label: 'Source Quality', points: Number(form.weights.source || 0) })
  if (testLead.whatsapp_number) lines.push({ label: 'WhatsApp Reachability', points: Number(form.weights.whatsapp || 0) })
  if (testLead.email) lines.push({ label: 'Email Reachability', points: Number(form.weights.email || 0) })
  if (testLead.created_at) lines.push({ label: 'Lead Freshness', points: Number(form.weights.recency || 0) })
  lines.push({ label: 'Stage Progress', points: Number(form.weights.stage || 0) })
  return lines
})

const livePreview = computed(() => {
  normalizeThresholds()
  const score = Math.max(
    0,
    Math.min(
      100,
      liveBreakdown.value.reduce((sum, item) => sum + Number(item.points || 0), 0),
    ),
  )

  let priority = 'cold'
  if (score >= form.thresholds.hot) priority = 'hot'
  else if (score >= form.thresholds.warm) priority = 'warm'

  return { score, priority }
})

const strongestFactor = computed(() => {
  const ranked = factors.map((f) => {
    const current = Number(form.weights[f.key] || 0)
    const high = Number(factorBase[f.key]?.high || current)
    return { label: f.title, highDelta: Math.max(0, high - current) }
  })
  ranked.sort((a, b) => b.highDelta - a.highDelta)
  return ranked[0] || { label: 'Budget Strength', highDelta: 0 }
})

const priorityBadgeClass = computed(() => {
  if (livePreview.value.priority === 'hot') return 'bg-emerald-100 text-emerald-700'
  if (livePreview.value.priority === 'warm') return 'bg-amber-100 text-amber-700'
  return 'bg-sky-100 text-sky-700'
})

const priorityContainerClass = computed(() => {
  if (livePreview.value.priority === 'hot') return 'border-emerald-200 bg-emerald-50'
  if (livePreview.value.priority === 'warm') return 'border-amber-200 bg-amber-50'
  return 'border-sky-200 bg-sky-50'
})

const animatedScore = ref(0)
let animationFrame = null

const animateScore = (target) => {
  const start = Number(animatedScore.value || 0)
  const duration = 300
  const startTime = performance.now()

  const tick = (now) => {
    const progress = Math.min(1, (now - startTime) / duration)
    animatedScore.value = Math.round(start + (target - start) * progress)
    if (progress < 1) {
      animationFrame = requestAnimationFrame(tick)
    }
  }

  if (animationFrame) cancelAnimationFrame(animationFrame)
  animationFrame = requestAnimationFrame(tick)
}

watch(
  () => livePreview.value.score,
  (val) => animateScore(val),
  { immediate: true },
)

const toggleCardClass = (enabled) => {
  if (enabled) return 'border-emerald-300 bg-emerald-50 shadow-[0_0_0_1px_rgba(16,185,129,0.15)]'
  return 'border-slate-200 bg-white hover:border-slate-300'
}

const loadSettings = async () => {
  loading.value = true
  try {
    const response = await api.get('/scoring-settings')
    const data = response?.data?.data || {}
    Object.assign(form.weights, data.weights || {})
    Object.assign(form.thresholds, data.thresholds || {})
    Object.assign(form.automation_flags, data.automation_flags || {})
    form.ai_mode = data.ai_mode || 'fallback'
    normalizeThresholds()
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    normalizeThresholds()
    await api.post('/scoring-settings', {
      weights: form.weights,
      thresholds: form.thresholds,
      automation_flags: form.automation_flags,
      ai_mode: form.ai_mode,
    })
    window.$showNotification?.('Lead scoring settings saved', 'success')
  } catch (e) {
    window.$showNotification?.(e?.response?.data?.message || 'Failed to save settings', 'error')
  } finally {
    saving.value = false
  }
}

const resetFromServer = async () => {
  await loadSettings()
}

const runServerTest = async () => {
  testing.value = true
  try {
    await api.post('/scoring/test', {
      lead: testLead,
      settings: {
        weights: form.weights,
        thresholds: form.thresholds,
        automation_flags: form.automation_flags,
        ai_mode: form.ai_mode,
      },
    })
  } catch (e) {
    window.$showNotification?.(e?.response?.data?.message || 'Unable to refresh AI preview', 'warning')
  } finally {
    testing.value = false
  }
}

onMounted(loadSettings)
</script>

<style scoped>
.scoring-page{padding:16px;border:1px solid #e5e7eb;background:#f8fafc;border-radius:16px}
.scoring-header{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:12px}
.scoring-header h1{margin:0;font-size:16px !important;line-height:1.2;font-weight:700;color:#0f172a}
.scoring-header p{margin:4px 0 0;color:#64748b;font-size:11px !important}
.header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.status-badge{display:inline-flex;align-items:center;gap:6px;border:1px solid #bbf7d0;background:#f0fdf4;color:#15803d;border-radius:999px;padding:3px 10px;font-size:10px;font-weight:600}
.dot{width:8px;height:8px;border-radius:50%;background:#22c55e}
.btn{border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;font-size:11px !important;font-weight:600;line-height:1.2}
.btn-light{background:#fff;color:#334155}
.btn-dark{background:#0f172a;color:#fff;border-color:#0f172a}
.w-100{width:100%}
.layout{display:grid;grid-template-columns:1.35fr 1fr;gap:12px}
.left-col,.right-col{display:flex;flex-direction:column;gap:10px}
.panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px;box-shadow:0 1px 6px rgba(15,23,42,.04)}
.panel h2{margin:0;color:#0f172a;font-size:13px !important;font-weight:700;line-height:1.25}
.desc{margin:3px 0 8px;color:#64748b;font-size:11px !important}
.factor-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.factor-card{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc;transition:.2s}
.factor-card:hover{border-color:#cbd5e1;transform:translateY(-1px)}
.factor-card h3{margin:0 0 3px;font-size:11px !important;font-weight:700;line-height:1.2;color:#0f172a}
.factor-card p{margin:0 0 8px;font-size:10px !important;line-height:1.25;color:#64748b}
.option-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.option-btn{border:1px solid #d1d5db;background:#fff;border-radius:8px;padding:6px 5px;display:flex;flex-direction:column;align-items:flex-start;gap:1px}
.option-btn strong{font-size:10px !important;line-height:1.1}
.option-btn small{font-size:9px !important;color:#64748b;line-height:1.1}
.option-btn.active{background:#0f172a;border-color:#0f172a;color:#fff}
.option-btn.active small{color:#cbd5e1}
.threshold-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;align-items:end}
.threshold-grid label{font-size:10px !important;color:#475569;font-weight:600}
.threshold-grid input{margin-top:4px;width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:11px !important}
.cold-note{font-size:10px !important;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px}
.priority-bar{height:10px;border-radius:999px;background:linear-gradient(90deg,#38bdf8 0%,#f59e0b 50%,#22c55e 100%);margin-top:8px}
.priority-labels{margin-top:5px;display:flex;justify-content:space-between;font-size:10px !important;color:#64748b}
.toggle-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.toggle-card{border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:9px;text-align:left;font-size:11px !important;font-weight:600;color:#334155;transition:.2s;line-height:1.25}
.toggle-card.on{border-color:#86efac;background:#f0fdf4;box-shadow:0 0 0 3px rgba(34,197,94,.14);color:#14532d}
.mode-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.mode-card{border:1px solid #d1d5db;border-radius:10px;padding:9px;font-size:11px !important;font-weight:600;color:#334155;display:flex;align-items:center;gap:6px;line-height:1.2}
.mode-card input{accent-color:#0f172a}
.mode-card.selected{border-color:#0f172a;background:#0f172a;color:#fff}
.pill{font-size:9px !important;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:999px}
.sticky{position:sticky;top:10px}
.lead-signals{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.lead-signals>div{border:1px solid #e2e8f0;border-radius:8px;padding:6px;background:#f8fafc}
.lead-signals span{display:block;font-size:9px !important;color:#64748b;line-height:1.1}
.lead-signals b{font-size:11px !important;color:#0f172a;line-height:1.2}
.live-output{margin-top:10px;border:1px solid #dbeafe;border-radius:12px;padding:10px;background:#f8fbff;transition:.25s}
.live-output.hot{border-color:#bbf7d0;background:#f0fdf4}
.live-output.warm{border-color:#fde68a;background:#fffbeb}
.live-output.cold{border-color:#bae6fd;background:#f0f9ff}
.score-box span{font-size:9px !important;color:#64748b;display:block}
.score-box strong{font-size:22px !important;line-height:1;font-weight:800;color:#0f172a}
.priority-pill{margin-top:5px;display:inline-block;border-radius:999px;padding:3px 8px;background:#e2e8f0;color:#334155;font-size:10px !important;font-weight:700;text-transform:uppercase}
.breakdown{margin-top:8px;display:grid;gap:6px}
.breakdown div{display:flex;justify-content:space-between;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:5px 7px;font-size:10px !important;line-height:1.2}
.impact-note{margin-top:10px;font-size:10px !important;color:#334155;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px;line-height:1.3}
.test-inputs{margin-top:10px;display:grid;gap:8px}
.test-inputs input,.test-inputs textarea{border:1px solid #cbd5e1;border-radius:8px;padding:7px 9px;font-size:11px !important;line-height:1.25}
.insight{border-radius:10px;padding:8px 10px;font-size:11px !important;line-height:1.3;margin-top:8px}
.insight.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#14532d}
.insight.warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
.insight.info{background:#f0f9ff;border:1px solid #bae6fd;color:#0c4a6e}
@media (max-width:1200px){.layout{grid-template-columns:1fr}.sticky{position:static}.factor-grid,.threshold-grid,.toggle-grid,.mode-grid{grid-template-columns:1fr}}
</style>
