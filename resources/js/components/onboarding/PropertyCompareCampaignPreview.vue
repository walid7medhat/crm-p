<template>
  <Teleport to="body">
    <Transition name="pcc-fade">
      <div
        v-if="open && phase === 'intro'"
        class="pcc-intro"
        role="dialog"
        aria-modal="true"
        aria-label="New feature"
      >
        <div class="pcc-intro__stage">
          <div class="pcc-intro__orbit" aria-hidden="true">
            <span /><span /><span />
          </div>
          <div class="pcc-intro__card">
            <p class="pcc-intro__badge">New feature</p>
            <p class="pcc-intro__name">Property Compare</p>
            <p class="pcc-intro__tag">Compare properties like a pro</p>
          </div>
          <button type="button" class="pcc-intro__skip" @click="close">Close</button>
        </div>
      </div>
    </Transition>

    <div v-if="open && phase === 'tour' && !clearView" class="pcc-tour" role="dialog" aria-modal="true" aria-label="Property Compare">
      <div class="pcc-tour__shade" :style="shadeStyle" />
      <div v-if="hole" class="pcc-tour__spot" :style="spotStyle" aria-hidden="true" />
      <aside class="pcc-tour__panel">
        <div class="pcc-tour__head">
          <p class="pcc-tour__meta">Step {{ step + 1 }} of {{ steps.length }}</p>
          <button type="button" class="pcc-tour__x" aria-label="Close" @click="close">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>
        <p class="pcc-tour__label">{{ steps[step].label }}</p>
        <p class="pcc-tour__body">{{ caption }}</p>
        <div class="pcc-tour__nav">
          <button type="button" @click="togglePause">{{ paused ? 'Resume' : 'Pause' }}</button>
          <button type="button" @click="replay">Replay</button>
          <button type="button" :disabled="step === 0" @click="jump(-1)">Back</button>
          <button type="button" :disabled="step === steps.length - 1" @click="jump(1)">Next</button>
          <button type="button" @click="close">Close</button>
        </div>
      </aside>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { initPropertyCompareCampaignLive } from '@/composables/usePropertyCompareCampaignLive.js'
import {
  closePropertyCompareCampaignPreview,
  propertyCompareCampaignLaunchId,
  propertyCompareCampaignMode,
  propertyCompareCampaignNonce,
  propertyCompareCampaignOpen,
} from '@/composables/usePropertyCompareCampaignPreview.js'
import {
  compareTourUnlocked,
  listingPurpose,
  restoreCompareSession,
  snapshotCompareSession,
  startPropertyCompare,
  usePropertyCompareSelection,
} from '@/composables/propertyCompareSelection'

const INTRO_MS = 3200

const router = useRouter()
const { selected, closeResults, cancelCompare, openResults } = usePropertyCompareSelection()

const steps = [
  { id: 'listings', label: 'All Listings' },
  { id: 'header', label: 'Click Compare' },
  { id: 'select', label: 'Select the units' },
  { id: 'dock', label: 'Click Compare Now' },
  { id: 'result', label: 'Comparison' },
]

const open = propertyCompareCampaignOpen
const phase = ref('idle')
const step = ref(0)
const caption = ref('Opening All Listings.')
const paused = ref(false)
const clearView = ref(false)
const hole = ref(null)
let savedSession = null
let captured = false
let runId = 0
let introTimer = null
let shownLaunch = null
let teardownLive = null

const shadeStyle = computed(() => {
  const spot = hole.value
  if (!spot) return { background: 'rgba(11, 7, 54, 0.55)' }
  const { top, left, width, height } = spot
  const path = `polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 0%, ${left}px ${top}px, ${left}px ${top + height}px, ${left + width}px ${top + height}px, ${left + width}px ${top}px, ${left}px ${top}px)`
  return { clipPath: path, background: 'rgba(11, 7, 54, 0.55)' }
})

const spotStyle = computed(() => {
  const spot = hole.value
  if (!spot) return { display: 'none' }
  return {
    top: `${spot.top}px`,
    left: `${spot.left}px`,
    width: `${spot.width}px`,
    height: `${spot.height}px`,
  }
})

function reduced() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

async function hold(ms, alive, full = false) {
  let left = !full && reduced() ? Math.min(ms, 160) : ms
  while (alive() && left > 0) {
    if (paused.value) {
      await wait(60)
      continue
    }
    const slice = Math.min(30, left)
    await wait(slice)
    left -= slice
  }
  return alive()
}

function shown(selector) {
  return [...document.querySelectorAll(selector)].find((el) => {
    const rect = el.getBoundingClientRect()
    const style = getComputedStyle(el)
    return rect.width > 8 && rect.height > 8 && style.display !== 'none' && style.visibility !== 'hidden'
  }) || null
}

function spotlight(el, pad = 8) {
  if (!el) {
    hole.value = null
    return
  }
  const rect = el.getBoundingClientRect()
  hole.value = {
    top: Math.max(6, rect.top - pad),
    left: Math.max(6, rect.left - pad),
    width: Math.min(window.innerWidth - 12, rect.width + pad * 2),
    height: Math.min(window.innerHeight - 12, rect.height + pad * 2),
  }
}

async function waitFor(selector, alive, ms = 5000) {
  const start = Date.now()
  while (alive() && Date.now() - start < ms) {
    const el = shown(selector)
    if (el) return el
    await wait(80)
  }
  return null
}

function clickWithoutLeaving(el) {
  if (!el) return
  const block = (event) => {
    event.preventDefault()
  }
  el.addEventListener('click', block, true)
  el.click()
  el.removeEventListener('click', block, true)
}

async function waitForListingsReady(alive) {
  const start = Date.now()
  while (alive() && Date.now() - start < 15000) {
    const loading = document.body.classList.contains('app-loader-active')
    if (!loading && shown('.property-card-link, .ml-card__link')) return true
    await wait(100)
  }
  return alive()
}

function markShown() {
  const launch = propertyCompareCampaignLaunchId.value
  if (propertyCompareCampaignMode.value !== 'live' || !launch || shownLaunch === launch) return
  shownLaunch = launch
  api.post('/next-action-tour/shown', { launch_id: launch }).catch(() => {})
}

function reportLive(kind) {
  const launch = propertyCompareCampaignLaunchId.value
  if (propertyCompareCampaignMode.value !== 'live' || !launch) return
  const path = kind === 'complete' ? '/next-action-tour/completed' : '/next-action-tour/dismissed'
  api.post(path, { launch_id: launch }).catch(() => {})
}

async function openCompareFromHeader(alive) {
  compareTourUnlocked.value = true
  startPropertyCompare()
  let button = await waitFor('[data-tour="property-compare-open"]', alive, 8000)
  if (!button) {
    const title = shown('.kanban-mob-title-btn')
    if (title) {
      spotlight(title)
      caption.value = 'On a phone, open the header menu, then click Compare.'
      if (!(await hold(900, alive))) return false
      title.click()
      await wait(280)
      button = await waitFor('[data-tour="property-compare-open"]', alive, 2500)
    }
  }
  if (!button) {
    caption.value = 'Compare is open. Next, select two units of the same type.'
    return hold(1200, alive)
  }
  spotlight(button)
  caption.value = 'Click Compare. This starts the selection.'
  if (!(await hold(1500, alive))) return false
  clickWithoutLeaving(button)
  startPropertyCompare()
  await wait(280)
  return alive()
}

function cardMarket(link) {
  const marked = link.getAttribute('data-compare-market')
  if (marked === 'sale' || marked === 'rent') return marked
  const card = link.closest('.property-listing-card, .ml-card')
  const text = (card?.querySelector('.status-badge, .ml-card__badge--sale, .ml-card__badge--rent')?.textContent || '').toLowerCase()
  if (text.includes('rent')) return 'rent'
  if (text.includes('sale')) return 'sale'
  return null
}

function candidateLinks(locked) {
  return [...document.querySelectorAll('.property-card-link[data-compare-market], .ml-card__link[data-compare-market], .property-card-link, .ml-card__link')].filter((link) => {
    const card = link.closest('.property-listing-card, .ml-card')
    if (card?.classList.contains('is-compare-selected')) return false
    const rect = link.getBoundingClientRect()
    if (rect.width < 8 || rect.height < 8) return false
    const market = cardMarket(link)
    return !locked || !market || market === locked
  })
}

async function showOnlyMarket(market, alive) {
  const label = market === 'rent' ? 'Rent' : 'Sale'
  caption.value = `Unit 2 has to be for ${label.toLowerCase()} as well. The list is filtered to ${label}.`
  const opener = shown('.listing-sale-rent-btn')
  if (!opener) return alive()
  spotlight(opener)
  if (!(await hold(900, alive))) return false
  opener.click()
  await wait(250)
  const option = [...document.querySelectorAll('.listing-tab-switch-purpose .listing-tab-btn')]
    .find((button) => button.textContent.trim().toLowerCase() === label.toLowerCase())
  option?.click()
  await wait(200)
  const done = [...document.querySelectorAll('.listing-sale-rent-popover .btn-primary')]
    .find((button) => /done/i.test(button.textContent || ''))
  done?.click()
  await waitFor('.property-card-link, .ml-card__link', alive, 6000)
  await wait(400)
  return alive()
}

async function selectListings(alive) {
  let filtered = false
  while (alive() && selected.value.length < 2) {
    const locked = selected.value.map(listingPurpose).find(Boolean)
    const links = candidateLinks(locked)
    let clicked = false
    for (const link of links) {
      if (!alive() || selected.value.length >= 2) break
      const card = link.closest('.property-listing-card, .ml-card') || link
      link.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' })
      await wait(reduced() ? 40 : 320)
      spotlight(card)
      const next = selected.value.length + 1
      caption.value = next === 1
        ? 'Click unit 1. Sale stays with sale, and rent stays with rent.'
        : 'Click unit 2. It matches unit 1.'
      if (!(await hold(1400, alive))) return false
      const before = selected.value.length
      startPropertyCompare()
      clickWithoutLeaving(link)
      await wait(280)
      clicked = true
      if (selected.value.length > before) {
        caption.value = next === 1
          ? 'Unit 1 is selected. Now click unit 2.'
          : 'Unit 2 is selected. Next, click Compare Now.'
        if (!(await hold(1000, alive))) return false
      }
    }
    if (selected.value.length >= 2 || !alive()) break
    if (!filtered && locked) {
      filtered = true
      if (!(await showOnlyMarket(locked, alive))) return false
      continue
    }
    if (!clicked) break
    break
  }
  return alive()
}

async function play(targetStep, alive) {
  clearView.value = false
  compareTourUnlocked.value = true
  try {
    if (router.currentRoute.value.path !== '/alllisting') {
      try {
        await router.push('/alllisting')
      } catch {
        // Another navigation can cancel this one. All Listings can still be the page we land on.
      }
    }
    caption.value = 'Opening All Listings.'
    step.value = 0
    await waitForListingsReady(alive)
    if (!alive()) return
    caption.value = 'This is All Listings. Next, click Compare in the header.'
    const firstCard = shown('.property-card-link, .ml-card__link')
    spotlight(firstCard?.closest('.property-listing-card, .ml-card') || document.querySelector('.dashboard-main-router'))
    if (targetStep === 0) {
      await hold(1400, alive)
      return
    }
    if (!(await hold(1200, alive))) return

    step.value = 1
    if (!(await openCompareFromHeader(alive))) return
    if (targetStep === 1) return

    step.value = 2
    if (!(await selectListings(alive))) return
    if (selected.value.length < 2) {
      caption.value = 'This page does not have 2 active units of the same type yet.'
      await hold(4000, alive)
      if (alive() && targetStep === 4) finish('complete')
      return
    }
    if (targetStep === 2) return

    step.value = 3
    await waitForListingsReady(alive)
    const dock = await waitFor('.property-compare-dock', alive, 12000)
    const go = shown('.property-compare-dock__go')
    spotlight(go || dock, 10)
    caption.value = selected.value.length >= 3
      ? 'Three units are selected. Click Compare Now.'
      : 'Two units are selected. Click Compare Now.'
    if (!(await hold(1600, alive))) return
    if (go && !go.disabled) clickWithoutLeaving(go)
    openResults()
    await wait(360)
    if (targetStep === 3) return

    step.value = 4
    let sheet = await waitFor('.property-compare__sheet', alive, 8000)
    if (!sheet && !shown('.property-compare__bar-actions')) {
      openResults()
      sheet = await waitFor('.property-compare__sheet', alive, 5000)
    }
    if (!sheet && !shown('.property-compare__bar-actions')) {
      caption.value = 'The comparison could not open on this page.'
      await hold(4000, alive)
      if (alive() && targetStep === 4) finish('complete')
      return
    }
    hole.value = null
    clearView.value = true
    markShown()
    if (!(await hold(10000, alive, true))) return
    if (alive() && targetStep === 4) finish('complete')
  } catch {
    if (!alive()) return
    caption.value = 'Opening All Listings.'
  }
}

function startRun(targetStep) {
  const id = ++runId
  const alive = () => id === runId && open.value && phase.value === 'tour'
  play(targetStep, alive)
}

function releaseSession() {
  hole.value = null
  if (!captured) return
  closeResults()
  restoreCompareSession(savedSession)
  captured = false
}

function finish(kind) {
  clearTimeout(introTimer)
  runId += 1
  clearView.value = false
  releaseSession()
  phase.value = 'idle'
  compareTourUnlocked.value = false
  reportLive(kind)
  closePropertyCompareCampaignPreview()
}

function close() {
  finish('dismissed')
}

function togglePause() {
  paused.value = !paused.value
}

function replay() {
  paused.value = false
  closeResults()
  cancelCompare()
  step.value = 0
  phase.value = 'tour'
  startRun(4)
}

function jump(delta) {
  const next = Math.min(steps.length - 1, Math.max(0, step.value + delta))
  if (next === step.value) return
  paused.value = false
  if (next < 3) closeResults()
  if (next < 2) cancelCompare()
  const id = ++runId
  play(next, () => id === runId && open.value && phase.value === 'tour')
}

function onKey(event) {
  if (!open.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
  }
}

function begin() {
  clearTimeout(introTimer)
  runId += 1
  paused.value = false
  clearView.value = false
  hole.value = null
  step.value = 0
  compareTourUnlocked.value = true
  if (captured) releaseSession()
  phase.value = 'intro'
  introTimer = setTimeout(() => {
    if (!open.value || phase.value !== 'intro') return
    savedSession = snapshotCompareSession()
    captured = true
    closeResults()
    cancelCompare()
    compareTourUnlocked.value = true
    phase.value = 'tour'
    startRun(4)
  }, reduced() ? 280 : INTRO_MS)
}

watch(open, (isOpen) => {
  if (isOpen) begin()
  else {
    clearTimeout(introTimer)
    runId += 1
    if (phase.value !== 'idle') {
      releaseSession()
      phase.value = 'idle'
    }
    compareTourUnlocked.value = false
  }
}, { immediate: true })

watch(propertyCompareCampaignNonce, () => {
  if (open.value) begin()
})

window.addEventListener('keydown', onKey)
onMounted(() => {
  teardownLive = initPropertyCompareCampaignLive()
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  clearTimeout(introTimer)
  runId += 1
  teardownLive?.()
  teardownLive = null
})
</script>

<style scoped>
.pcc-intro {
  position: fixed;
  inset: 0;
  z-index: 1000002;
  display: grid;
  place-items: center;
  background:
    radial-gradient(ellipse at 30% 20%, rgba(155, 89, 182, 0.35), transparent 55%),
    radial-gradient(ellipse at 70% 80%, rgba(115, 62, 135, 0.4), transparent 50%),
    linear-gradient(160deg, #12081f 0%, #1a1028 45%, #0b0736 100%);
  perspective: 1200px;
  overflow: hidden;
}
.pcc-intro__stage {
  position: relative;
  width: min(420px, 92vw);
  height: min(280px, 56vh);
  transform-style: preserve-3d;
  animation: pcc-cam 3.2s ease-in-out both;
}
.pcc-intro__orbit {
  position: absolute;
  inset: 8%;
  border-radius: 28px;
  border: 1px solid rgba(216, 180, 254, 0.28);
  transform: rotateX(58deg) rotateZ(-18deg);
  animation: pcc-orbit 3.2s ease-in-out both;
}
.pcc-intro__orbit span {
  position: absolute;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #e9d5ff;
  box-shadow: 0 0 18px #d8b4fe;
}
.pcc-intro__orbit span:nth-child(1) { top: 12%; left: 18%; animation: pcc-dot 1.1s ease-in-out infinite; }
.pcc-intro__orbit span:nth-child(2) { top: 48%; right: 14%; animation: pcc-dot 1.1s ease-in-out 0.2s infinite; }
.pcc-intro__orbit span:nth-child(3) { bottom: 16%; left: 42%; animation: pcc-dot 1.1s ease-in-out 0.4s infinite; }
.pcc-intro__card {
  position: absolute;
  inset: 18% 10%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 22px;
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow:
    0 30px 60px rgba(0, 0, 0, 0.35),
    inset 0 1px 0 rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(14px);
  transform: translateZ(48px) rotateY(-8deg) rotateX(6deg);
  animation: pcc-card 3.2s cubic-bezier(0.2, 0.8, 0.2, 1) both;
  text-align: center;
  padding: 20px;
}
.pcc-intro__badge {
  margin: 0;
  padding: 4px 10px;
  border-radius: 999px;
  color: #f5e9ff;
  background: rgba(115, 62, 135, 0.55);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.pcc-intro__name {
  margin: 0;
  color: #fff;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-shadow: 0 10px 30px rgba(115, 62, 135, 0.55);
}
.pcc-intro__tag {
  margin: 0;
  color: rgba(255, 255, 255, 0.72);
  font-size: 12px;
  font-weight: 500;
}
.pcc-intro__skip {
  position: absolute;
  top: -42px;
  right: 0;
  border: 0;
  border-radius: 999px;
  padding: 6px 12px;
  color: #efe6f8;
  background: rgba(255, 255, 255, 0.12);
  font-size: 11px;
  font-weight: 650;
  cursor: pointer;
}

.pcc-tour {
  position: fixed;
  inset: 0;
  z-index: 1000001;
  pointer-events: none;
  perspective: 900px;
}
.pcc-tour__shade {
  position: absolute;
  inset: 0;
  pointer-events: auto;
}
.pcc-tour__spot {
  position: fixed;
  border-radius: 14px;
  box-shadow: 0 0 0 2px #fff, 0 0 0 5px rgba(115, 62, 135, 0.85);
  pointer-events: none;
  transform: translateZ(18px);
  animation: pcc-pulse 1.05s ease-in-out infinite;
}
.pcc-tour__panel {
  position: fixed;
  left: 16px;
  right: 16px;
  bottom: calc(16px + env(safe-area-inset-bottom, 0px));
  z-index: 1;
  pointer-events: auto;
  padding: 14px;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 16px 40px rgba(11, 7, 54, 0.28);
  transform: translateZ(24px) rotateX(3deg);
  animation: pcc-pop 0.28s ease both;
}
.pcc-tour__head,
.pcc-tour__nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.pcc-tour__head {
  justify-content: space-between;
}
.pcc-tour__meta,
.pcc-tour__label,
.pcc-tour__body {
  margin: 0;
}
.pcc-tour__meta {
  color: #733e87;
  font-size: 12px;
  font-weight: 800;
}
.pcc-tour__label {
  margin-top: 4px;
  color: #0b0736;
  font-size: 15px;
  font-weight: 800;
}
.pcc-tour__body {
  margin: 6px 0 12px;
  color: #5b5572;
  font-size: 13px;
  line-height: 1.45;
}
.pcc-tour__x,
.pcc-tour__nav button {
  border: 0;
  border-radius: 999px;
  background: #f4f1f7;
  color: #3b3558;
  font: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.pcc-tour__x {
  width: 32px;
  height: 32px;
}
.pcc-tour__nav button {
  padding: 8px 12px;
}
.pcc-tour__nav button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.pcc-fade-enter-active,
.pcc-fade-leave-active { transition: opacity 0.22s ease; }
.pcc-fade-enter-from,
.pcc-fade-leave-to { opacity: 0; }

@keyframes pcc-cam {
  0% { opacity: 0; transform: translateZ(-180px) scale(0.7); }
  18% { opacity: 1; transform: translateZ(0) scale(1); }
  82% { opacity: 1; transform: translateZ(20px) scale(1.02); }
  100% { opacity: 1; transform: translateZ(20px) scale(1.02); }
}
@keyframes pcc-card {
  0% { opacity: 0; transform: translateZ(-40px) rotateY(28deg) rotateX(18deg) scale(0.8); }
  22% { opacity: 1; transform: translateZ(48px) rotateY(-8deg) rotateX(6deg) scale(1); }
  78% { opacity: 1; transform: translateZ(56px) rotateY(4deg) rotateX(2deg) scale(1.02); }
  100% { opacity: 1; transform: translateZ(56px) rotateY(4deg) rotateX(2deg) scale(1.02); }
}
@keyframes pcc-orbit {
  0% { opacity: 0; transform: rotateX(58deg) rotateZ(-40deg) scale(0.85); }
  20% { opacity: 1; transform: rotateX(58deg) rotateZ(-18deg) scale(1); }
  100% { opacity: 0.4; transform: rotateX(58deg) rotateZ(12deg) scale(1.05); }
}
@keyframes pcc-dot {
  0%, 100% { transform: scale(1); opacity: 0.7; }
  50% { transform: scale(1.35); opacity: 1; }
}
@keyframes pcc-pop {
  from { opacity: 0; transform: translateY(10px) translateZ(0) rotateX(8deg) scale(0.96); }
  to { opacity: 1; transform: translateY(0) translateZ(24px) rotateX(3deg) scale(1); }
}
@keyframes pcc-pulse {
  0%, 100% { box-shadow: 0 0 0 2px rgba(216, 180, 254, 0.95), 0 0 0 6px rgba(115, 62, 135, 0.2); }
  50% { box-shadow: 0 0 0 2px #fff, 0 18px 28px rgba(115, 62, 135, 0.28), 0 0 0 12px rgba(115, 62, 135, 0.08); }
}

@media (min-width: 900px) {
  .pcc-tour__panel {
    left: auto;
    right: 20px;
    top: 84px;
    bottom: auto;
    width: min(340px, calc(100vw - 40px));
  }
}
@media (max-width: 800px) {
  .pcc-intro__name { font-size: 22px; }
}
@media (prefers-reduced-motion: reduce) {
  .pcc-intro__stage,
  .pcc-intro__card,
  .pcc-intro__orbit,
  .pcc-intro__orbit span,
  .pcc-tour__spot,
  .pcc-tour__panel {
    animation: none;
  }
}
</style>
