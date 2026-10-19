import { computed, ref } from 'vue'
import api from '@/plugins/axios'
import {
  closeSalesActionCenter,
  openSalesActionCenter,
  salesActionCenterOpen,
} from '@/composables/useSalesActionCenter.js'

/** Phases: idle → intro (3D bumper) → tour (real tool walkthrough). */
export const tourPhase = ref('idle')
export const tourStepIndex = ref(0)
export const tourAutoPlaying = ref(false)
export const tourSteps = ref([])
/** null = admin Preview; uuid = live campaign for this user */
export const liveLaunchId = ref(null)
export const tourMode = ref('preview') // preview | live

const INTRO_MS = 4200
const STEP_MS = 3600

const STAGE_COPY = {
  urgent_overdue: 'These leads have a follow-up that is already past due. Work them first.',
  assigned_needs_first_contact: 'Assigned leads with no saved activity yet — make the first contact.',
  contacted_follow_up_due: 'Contacted leads with a reminder due today.',
  contacted_repeated_no_answer: 'Contacted, but the client has not answered more than once.',
  no_answer_call_again: 'Last result was no answer — try calling again.',
  answered_gone_quiet: 'They answered before, then went quiet. Re-engage them.',
  qualified_missing_requirements: 'Qualified, but area, type, bedrooms, or budget is still missing.',
  converted_deal_missing: 'Marked converted, but no deal is linked yet.',
  inactive: 'No recent human engagement for a week. Decide the next move.',
  approaching_deadline: 'A stage deadline is close. Act before it hits.',
}

export const tourStep = computed(() => tourSteps.value[tourStepIndex.value] || null)
export const isTourPreviewActive = computed(() => tourPhase.value !== 'idle')
export const isIntro = computed(() => tourPhase.value === 'intro')
export const isTourWalkthrough = computed(() => tourPhase.value === 'tour')
/** @deprecated kept so older imports do not break */
export const isAnnouncement = isIntro
export const TOUR_STEPS = tourSteps

let autoTimer = null
let autoToken = 0

function clearAutoTimer() {
  if (autoTimer) {
    clearTimeout(autoTimer)
    autoTimer = null
  }
}

function scheduleAuto(ms, fn) {
  clearAutoTimer()
  const token = autoToken
  autoTimer = setTimeout(() => {
    autoTimer = null
    if (token !== autoToken) return
    fn()
  }, ms)
}

function ensureDockExpanded() {
  const dock = document.getElementById('shortcut-dock')
  if (dock?.classList.contains('is-collapsed')) {
    dock.querySelector('.shortcut-dock__launcher')?.click()
  }
}

function pillEl() {
  return document.querySelector('[data-tour="next-actions-pill"]')
}

export function dispatchTourAction(action, extra = {}) {
  if (typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent('next-action-tour:action', { detail: { action, ...extra } }))
}

function wait(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

function requestCategories() {
  return new Promise((resolve) => {
    const onList = (event) => {
      window.removeEventListener('next-action-tour:categories', onList)
      resolve(Array.isArray(event.detail?.categories) ? event.detail.categories : [])
    }
    window.addEventListener('next-action-tour:categories', onList)
    dispatchTourAction('list-categories')
    setTimeout(() => {
      window.removeEventListener('next-action-tour:categories', onList)
      resolve([])
    }, 800)
  })
}

async function ensureCenterOpen() {
  ensureDockExpanded()
  await wait(120)
  const pill = pillEl()
  if (!salesActionCenterOpen.value) {
    if (pill) openSalesActionCenter({ currentTarget: pill })
    else openSalesActionCenter({ currentTarget: null })
    await wait(420)
  }
}

function buildSteps(categories) {
  const steps = [
    {
      id: 'pill',
      label: 'Shortcut',
      body: 'Next Actions sits in your shortcut dock. The preview opens the real tool next.',
      selector: '[data-tour="next-actions-pill"]',
      pad: 10,
      action: 'ensure-dock',
    },
    {
      id: 'tree',
      label: 'Your stages',
      body: categories.length
        ? `Your tree shows ${categories.length} stage${categories.length === 1 ? '' : 's'} built from leads you are allowed to see.`
        : 'No stages need action right now for your account — the tree stays empty until there is work.',
      selector: 'tree',
      pad: 14,
      action: 'open-center',
    },
  ]

  categories.forEach((cat, index) => {
    const count = Math.min(Number(cat.count) || 0, 20)
    const explain = STAGE_COPY[cat.key] || 'Leads in this stage need a clear next move.'
    steps.push({
      id: `stage-${cat.key}`,
      label: cat.title,
      body: `${explain} Showing up to ${count} card${count === 1 ? '' : 's'} from your own list.`,
      selector: `[data-tour-stage="${cat.key}"]`,
      pad: 8,
      action: 'open-stage',
      stageIndex: index,
      stageKey: cat.key,
    })
    steps.push({
      id: `cards-${cat.key}`,
      label: `${cat.title} · cards`,
      body: 'These are the exact tasks for this stage — name, reason, need, budget, and note from your data. The tour never opens a lead.',
      selector: '[data-tour="next-actions-cards"]',
      pad: 10,
      action: 'open-stage',
      stageIndex: index,
      stageKey: cat.key,
    })
  })

  return steps
}

export async function runStepAction(step = tourStep.value) {
  if (!step?.action) return
  ensureDockExpanded()
  await wait(40)

  if (step.action === 'ensure-dock') {
    ensureDockExpanded()
    return
  }

  if (step.action === 'open-center' || step.action === 'open-stage') {
    await ensureCenterOpen()
  }

  if (step.action === 'open-stage' && step.stageIndex != null) {
    dispatchTourAction('open-stage', { index: step.stageIndex, key: step.stageKey })
    await wait(320)
  }
}

function queueAutoAdvance() {
  if (!tourAutoPlaying.value || tourPhase.value !== 'tour') return
  scheduleAuto(STEP_MS, async () => {
    if (!tourAutoPlaying.value || tourPhase.value !== 'tour') return
    if (tourStepIndex.value >= tourSteps.value.length - 1) {
      scheduleAuto(2000, () => {
        if (tourAutoPlaying.value) endNextActionTourPreview({ completed: true })
      })
      return
    }
    tourStepIndex.value += 1
    await runStepAction()
    queueAutoAdvance()
  })
}

async function beginWalkthrough() {
  tourPhase.value = 'tour'
  tourStepIndex.value = 0
  await ensureCenterOpen()
  await wait(200)
  let categories = await requestCategories()
  if (!categories.length) {
    await wait(500)
    categories = await requestCategories()
  }
  tourSteps.value = buildSteps(categories)
  await runStepAction(tourSteps.value[0])
  queueAutoAdvance()
}

async function recordLive(path) {
  if (tourMode.value !== 'live' || !liveLaunchId.value) return
  try {
    await api.post(`/next-action-tour/${path}`, { launch_id: liveLaunchId.value })
  } catch {
    // Progress tracking is best-effort; the tour UI still works.
  }
}

function startTourSession({ mode = 'preview', launchId = null } = {}) {
  autoToken += 1
  clearAutoTimer()
  tourMode.value = mode
  liveLaunchId.value = launchId
  tourAutoPlaying.value = true
  tourStepIndex.value = 0
  tourSteps.value = []
  tourPhase.value = 'intro'

  if (mode === 'live' && launchId) {
    recordLive('shown')
  }

  scheduleAuto(INTRO_MS, async () => {
    if (!tourAutoPlaying.value || tourPhase.value !== 'intro') return
    await beginWalkthrough()
  })
}

/** Admin Preview: local only — no campaign, no other users. */
export function startNextActionTourPreview() {
  startTourSession({ mode: 'preview', launchId: null })
}

/** Live campaign for the signed-in user (Echo /me). Max 3 views enforced by API. */
export function startNextActionTourLive(launchId) {
  if (!launchId) return
  if (tourPhase.value !== 'idle') return
  startTourSession({ mode: 'live', launchId: String(launchId) })
}

export async function continueTourFromAnnouncement() {
  autoToken += 1
  clearAutoTimer()
  tourAutoPlaying.value = true
  await beginWalkthrough()
}

export async function goTourNext() {
  autoToken += 1
  clearAutoTimer()
  tourAutoPlaying.value = false
  if (tourPhase.value === 'intro') {
    await beginWalkthrough()
    return
  }
  if (tourPhase.value !== 'tour') return
  if (tourStepIndex.value >= tourSteps.value.length - 1) {
    endNextActionTourPreview({ completed: true })
    return
  }
  tourStepIndex.value += 1
  await runStepAction()
}

export async function goTourPrev() {
  autoToken += 1
  clearAutoTimer()
  tourAutoPlaying.value = false
  if (tourPhase.value !== 'tour' || tourStepIndex.value <= 0) return
  tourStepIndex.value -= 1
  await runStepAction()
}

export function endNextActionTourPreview({ completed = false } = {}) {
  const wasLive = tourMode.value === 'live' && liveLaunchId.value
  const launchId = liveLaunchId.value
  autoToken += 1
  clearAutoTimer()
  tourAutoPlaying.value = false
  tourPhase.value = 'idle'
  tourStepIndex.value = 0
  tourSteps.value = []
  tourMode.value = 'preview'
  liveLaunchId.value = null
  dispatchTourAction('close-stage')
  closeSalesActionCenter()
  if (wasLive && launchId) {
    api.post(`/next-action-tour/${completed ? 'completed' : 'dismissed'}`, { launch_id: launchId }).catch(() => {})
  }
}

export function useNextActionTourPreview() {
  return {
    tourPhase,
    tourStepIndex,
    tourStep,
    tourSteps,
    tourAutoPlaying,
    tourMode,
    liveLaunchId,
    TOUR_STEPS,
    isTourPreviewActive,
    isIntro,
    isAnnouncement,
    isTourWalkthrough,
    startNextActionTourPreview,
    startNextActionTourLive,
    continueTourFromAnnouncement,
    goTourNext,
    goTourPrev,
    endNextActionTourPreview,
    runStepAction,
  }
}
