<template>
  <div class="mk-board-outer">
  <!-- Hover zones (like the leads board): hovering an edge keeps scrolling that way. -->
  <div
    v-show="canLeft"
    class="mk-nav-zone mk-nav-zone--left"
    title="Move left"
    @mouseenter="startScroll(-1)"
    @mouseleave="stopScroll"
    @click="scrollBy(-1)"
  >
    <span class="mk-nav"><iconify-icon icon="lucide:chevron-left" /></span>
  </div>
  <div
    v-show="canRight"
    class="mk-nav-zone mk-nav-zone--right"
    title="Move the stages"
    @mouseenter="startScroll(1)"
    @mouseleave="stopScroll"
    @click="scrollBy(1)"
  >
    <span class="mk-nav"><iconify-icon icon="lucide:chevron-right" /></span>
  </div>
  <div ref="scroller" class="mk-board" :class="{ 'is-loading': loading }" @scroll.passive="updateArrows">
    <section
      v-for="(stage, index) in stages"
      :key="stage.id"
      class="mk-col"
      :data-stage-id="stage.id"
      :class="{ 'is-collapsed': collapsed[stage.id], 'is-done': stage.is_done }"
      :style="{ '--stage': stage.color }"
    >
      <!-- Pipeline step header: number · icon · name · share of cards -->
      <header class="mk-col__head" @click="toggleCollapse(stage.id)">
        <div class="mk-col__row">
          <span class="mk-col__icon" :class="`mk-col__icon--${stageMood(stage)}`">
            <iconify-icon :icon="stage.icon || 'lucide:circle-dot'" />
          </span>
          <div class="mk-col__titles">
            <span class="mk-col__step">Step {{ String(index + 1).padStart(2, '0') }}</span>
            <h6>{{ stage.name }}</h6>
          </div>
          <span :key="stage.tasks.length" class="mk-col__count">{{ stage.tasks.length }}</span>
          <button
            type="button"
            class="mk-col__add"
            :title="`New card in ${stage.name}`"
            @click.stop="$emit('create', stage.id)"
          >
            <iconify-icon icon="lucide:plus" />
          </button>
          <iconify-icon icon="lucide:chevron-down" class="mk-col__chev" />
        </div>
        <div v-if="stage.is_done || overdueIn(stage)" class="mk-col__sub">
          <span v-if="stage.is_done" class="mk-col__flag"><iconify-icon icon="lucide:flag" /> Finish line</span>
          <span v-else class="mk-col__late"><iconify-icon icon="lucide:alarm-clock" /> {{ overdueIn(stage) }} overdue</span>
        </div>
      </header>

      <draggable
        v-show="!collapsed[stage.id]"
        :list="stage.tasks"
        item-key="id"
        :group="`marketing-${category}`"
        class="mk-col__list"
        :animation="200"
        :delay="isTouch ? 180 : 0"
        :delay-on-touch-only="true"
        ghost-class="mk-ghost"
        chosen-class="mk-chosen"
        drag-class="mk-dragging"
        :disabled="locked"
        @start="(evt) => pickUp(evt.item)"
        @change="(evt) => onChange(evt, stage)"
      >
        <template #item="{ element }">
          <MarketingCard
            :task="element"
            :stage-done="stage.is_done"
            @open="$emit('open', $event)"
            @add-link="$emit('add-link', $event)"
          />
        </template>
      </draggable>
    </section>
  </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import MarketingCard from './MarketingCard.vue'
import { isoDate } from '@/config/marketingConfig.js'
import { revealBoard, landCard, pickUp, stageMood } from '@/motion/marketingMotion.js'

const props = defineProps({
  stages: { type: Array, required: true },
  category: { type: String, required: true },
  loading: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },

})
const emit = defineEmits(['move', 'open', 'create', 'add-link'])

function overdueIn(stage) {
  const today = isoDate(new Date())
  return stage.tasks.filter((t) => t.due_date && t.due_date < today).length
}

const isTouch = typeof window !== 'undefined' && window.matchMedia?.('(hover: none)').matches
const collapsed = reactive({})

// Horizontal scrolling between stages (like the leads board): arrows show only when there's more to see.
const scroller = ref(null)
const canLeft = ref(false)
const canRight = ref(false)

function updateArrows() {
  const el = scroller.value
  if (!el || window.innerWidth <= 768) {
    canLeft.value = canRight.value = false
    return
  }
  canLeft.value = el.scrollLeft > 2
  canRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 2
  // A zone hidden at the edge never fires mouseleave — stop here instead.
  if ((scrollDir === -1 && !canLeft.value) || (scrollDir === 1 && !canRight.value)) stopScroll()
}

const SCROLL_SPEED = 10
const SCROLL_TICK_MS = 16
let scrollTimer = null
let scrollDir = 0

function startScroll(dir) {
  stopScroll()
  scrollDir = dir
  scrollTimer = setInterval(() => {
    if (scroller.value) scroller.value.scrollLeft += dir * SCROLL_SPEED
  }, SCROLL_TICK_MS)
}

function stopScroll() {
  if (scrollTimer) clearInterval(scrollTimer)
  scrollTimer = null
  scrollDir = 0
}

function scrollBy(dir) {
  const el = scroller.value
  if (!el) return
  el.scrollBy({ left: dir * Math.max(300, el.clientWidth * 0.7), behavior: 'smooth' })
}

// Entrance animation the first time this board's stages arrive (the board remounts per tab).
let revealed = false
watch(() => props.stages.length, (n) => {
  nextTick(() => {
    updateArrows()
    if (n && !revealed) {
      revealed = true
      revealBoard(scroller.value)
    }
  })
}, { immediate: true })
onMounted(() => {
  nextTick(updateArrows)
  window.addEventListener('resize', updateArrows)
})
onBeforeUnmount(() => {
  stopScroll()
  window.removeEventListener('resize', updateArrows)
})

function toggleCollapse(stageId) {
  // Collapsing only matters on the stacked (mobile) layout.
  if (window.innerWidth > 768) return
  collapsed[stageId] = !collapsed[stageId]
}

function onChange(evt, stage) {
  const change = evt.added || evt.moved
  if (!change) return
  emit('move', { task: change.element, stage, position: change.newIndex, sameStage: !!evt.moved })
  // Landing motion only when the card changed stage (a reorder stays quiet).
  if (evt.added) {
    nextTick(() => {
      const el = scroller.value?.querySelector(`.mk-card[data-task-id="${change.element.id}"]`)
      const stageIcon = scroller.value?.querySelector(`.mk-col[data-stage-id="${stage.id}"] .mk-col__icon`)
      landCard(el, { mood: stageMood(stage), stageIcon })
    })
  }
}
</script>

<style scoped>
/* Same behaviour as the leads board: the row scrolls sideways, each stage fills the
   viewport height and scrolls its own cards. */
.mk-board-outer { position: relative; }
.mk-board {
  display: flex;
  gap: 14px;
  align-items: stretch;
  height: calc(100dvh - 230px);
  min-height: 460px;
  overflow-x: auto;
  overflow-y: hidden;
  padding: 2px 2px 8px;
  overscroll-behavior-x: contain;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
  transition: opacity 0.2s;
}
.mk-board.is-loading { opacity: 0.6; pointer-events: none; }

/* Full-height hover zones at both edges (same as leads .kanban-nav-zone) */
.mk-nav-zone {
  position: absolute;
  top: 0;
  bottom: 0;
  z-index: 5;
  width: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.mk-nav-zone--left { left: 0; }
.mk-nav-zone--right { right: 0; }
.mk-nav {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 22px !important;
  color: var(--text-primary-light);
  background: var(--white);
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 16px rgba(30, 27, 46, 0.12);
  pointer-events: none;
  transition: box-shadow 0.2s, transform 0.15s;
}
.mk-nav-zone:hover .mk-nav { transform: scale(1.04); box-shadow: 0 6px 20px rgba(30, 27, 46, 0.16); }
@media (hover: none) { .mk-nav-zone { display: none !important; } }

/* Every stage has the same fixed width, whatever its name or content. */
.mk-col {
  flex: 0 0 290px;
  width: 290px;
  min-width: 290px;
  max-width: 290px;
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  position: relative;
  border-radius: 18px;
  border: 1px solid #eceaf2;
  background: #f7f6f9;
  scroll-snap-align: start;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.mk-col:hover { border-color: color-mix(in srgb, var(--stage) 35%, var(--border-color)); }

/* Pipeline step header: the stage color is used only on the icon and a thin top edge. */
.mk-col__head {
  position: relative;
  padding: 12px 12px 10px;
  border-radius: 15px 15px 0 0;
  background: var(--white);
  border-bottom: 1px solid var(--border-color);
  box-shadow: inset 0 3px 0 var(--stage);
}
.mk-col__row { display: flex; align-items: center; gap: 9px; }
.mk-col__icon {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 16px !important;
  color: var(--stage);
  background: color-mix(in srgb, var(--stage) 11%, var(--white));
  border: 1px solid color-mix(in srgb, var(--stage) 22%, transparent);
  flex-shrink: 0;
}
.mk-col__titles { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.15; }
.mk-col__step {
  font-size: 10px !important;
  font-weight: 700 !important;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-secondary-light);
  opacity: 0.8;
}
.mk-col__head h6 {
  margin: 1px 0 0;
  font-size: 13px !important;
  font-weight: 700 !important;
  color: var(--text-primary-light);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mk-col__count {
  min-width: 28px;
  padding: 2px 8px;
  border-radius: 8px;
  text-align: center;
  font-size: 12px !important;
  font-weight: 700 !important;
  font-variant-numeric: tabular-nums;
  color: var(--text-primary-light);
  background: var(--neutral-100);
  /* re-keyed on change → pops whenever the number changes */
  animation: mk-bump 0.4s cubic-bezier(.2,.8,.2,1.4);
}
@keyframes mk-bump {
  0% { transform: scale(1.4) rotate(-8deg); color: var(--color-on-primary); background: var(--color-primary); }
  60% { transform: scale(0.92) rotate(3deg); }
  100% { transform: scale(1); }
}
/*
 * Stage icons idle with a motion that fits the stage — a mix of playful and professional.
 * The motion runs on the inner svg, so the hover hop on the box can play on top.
 *   Pending      → hourglass flips over, then waits          (playful)
 *   Working      → steady, smooth spin                       (professional)
 *   On Revision  → one turn backwards, then a pause          (professional)
 *   Done         → calm heartbeat with a soft halo           (professional)
 *   Paused       → sleepy sway                               (playful)
 *   Cancelled    → quick "no-no" shake every few seconds     (playful)
 */
.mk-col__icon :deep(svg) { transform-origin: 50% 50%; }
.mk-col__icon--default :deep(svg) { animation: mk-flip 4s cubic-bezier(.6, 0, .3, 1) infinite; }
.mk-col__icon--working :deep(svg) { animation: mk-spin-soft 2.4s linear infinite; }
.mk-col__icon--revision :deep(svg) { animation: mk-rewind 3.2s cubic-bezier(.5, 0, .3, 1) infinite; }
.mk-col__icon--done { animation: mk-halo 2.4s ease-out infinite; }
.mk-col__icon--done :deep(svg) { animation: mk-beat 2.4s ease-in-out infinite; }
.mk-col__icon--paused :deep(svg) { animation: mk-sway 3s ease-in-out infinite; }
.mk-col__icon--cancelled :deep(svg) { animation: mk-nope 3.6s ease-in-out infinite; }

@keyframes mk-flip {
  0%, 55% { transform: rotate(0); }
  75%, 100% { transform: rotate(180deg); }
}
@keyframes mk-spin-soft { to { transform: rotate(360deg); } }
@keyframes mk-rewind {
  0%, 60% { transform: rotate(0); }
  100% { transform: rotate(-360deg); }
}
@keyframes mk-beat {
  0%, 40%, 100% { transform: scale(1); }
  12% { transform: scale(1.18); }
  24% { transform: scale(0.96); }
}
@keyframes mk-halo {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--stage) 35%, transparent); }
  70%, 100% { box-shadow: 0 0 0 8px color-mix(in srgb, var(--stage) 0%, transparent); }
}
@keyframes mk-sway {
  0%, 100% { transform: rotate(-12deg) translateY(0); }
  50% { transform: rotate(12deg) translateY(1px); }
}
@keyframes mk-nope {
  0%, 80%, 100% { transform: translateX(0) rotate(0); }
  84% { transform: translateX(-2px) rotate(-12deg); }
  88% { transform: translateX(2px) rotate(12deg); }
  92% { transform: translateX(-2px) rotate(-8deg); }
  96% { transform: translateX(1px) rotate(4deg); }
}

/* Hover a stage → its icon box does a little hop */
.mk-col:hover .mk-col__icon { animation: mk-hop 0.5s cubic-bezier(.3, 1.6, .5, 1); }
.mk-col:hover .mk-col__icon--done { animation: mk-hop 0.5s cubic-bezier(.3, 1.6, .5, 1), mk-halo 2.4s ease-out infinite; }
@keyframes mk-hop {
  0%, 100% { transform: translateY(0) scale(1); }
  40% { transform: translateY(-5px) scale(1.08) rotate(-6deg); }
  70% { transform: translateY(1px) scale(0.97); }
}
@keyframes mk-wiggle {
  0%, 100% { transform: rotate(0); }
  30% { transform: rotate(-10deg) scale(1.06); }
  60% { transform: rotate(8deg) scale(1.06); }
}
.mk-col__sub {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  font-size: 11px !important;
  font-weight: 500 !important;
  color: var(--text-secondary-light);
}
.mk-col__sub span { display: inline-flex; align-items: center; gap: 3px; }
.mk-col__flag { color: #16a34a; font-weight: 600 !important; }
.mk-col__late { color: #dc2626; font-weight: 600 !important; }
.mk-col.is-done .mk-col__head { background: linear-gradient(180deg, color-mix(in srgb, var(--stage) 7%, var(--white)), var(--white)); }
.mk-col__add {
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 9px;
  display: grid;
  place-items: center;
  font-size: 16px !important;
  color: var(--text-secondary-light);
  background: transparent;
  transition: all 0.15s;
}
.mk-col__add:hover { color: var(--text-primary-light); background: var(--neutral-200); }
.mk-col__chev { display: none; color: var(--text-secondary-light); transition: transform 0.2s; }

.mk-col__list {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  gap: 11px;
  padding: 12px 11px 14px;
  min-height: 0;
  overflow-y: auto;
  overflow-x: clip;
  overscroll-behavior-y: contain;
  /* scrollbar hidden until the stage is hovered, like leads */
  scrollbar-width: none;
}
.mk-col__list::-webkit-scrollbar { width: 0; }
.mk-col:hover .mk-col__list { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
.mk-col:hover .mk-col__list::-webkit-scrollbar { width: 6px; }
.mk-col:hover .mk-col__list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.mk-col:hover .mk-col__list::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.mk-col__list > * { flex-shrink: 0; }
/* Drop target: the stage you're dragging over lights up in the theme color */
.mk-col:has(.mk-ghost) {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.15);
}
.mk-col:has(.mk-ghost) .mk-col__icon { animation: mk-wiggle 0.5s ease infinite; }

:deep(.mk-ghost) {
  opacity: 1;
  border: 1.5px dashed #c4b6e0 !important;
  background: repeating-linear-gradient(-45deg, #f7f4fb, #f7f4fb 6px, #f1ebf8 6px, #f1ebf8 12px) !important;
  box-shadow: none !important;
}
:deep(.mk-ghost > *) { visibility: hidden; }
:deep(.mk-dragging) { transform: rotate(2.5deg) scale(1.02); box-shadow: 0 24px 40px -14px rgba(15, 10, 30, 0.45) !important; }
:deep(.mk-chosen) { cursor: grabbing; }

@media (max-width: 768px) {
  .mk-board { flex-direction: column; overflow-x: visible; overflow-y: visible; height: auto; min-height: 0; }
  .mk-col { flex: 1 1 auto; width: 100%; min-width: 0; max-width: none; height: auto; overflow: visible; }
  .mk-col__list { min-height: 56px; }
  .mk-col__head { cursor: pointer; }
  .mk-col__chev { display: inline-flex; }
  .mk-col.is-collapsed .mk-col__chev { transform: rotate(-90deg); }
  .mk-col.is-collapsed .mk-col__head { border-radius: 15px; border-bottom: 0; }
  .mk-col__list { overflow: visible; }
}
@media (prefers-reduced-motion: reduce) {
  :deep(.mk-dragging) { transform: none; }
  .mk-col__count,
  .mk-col__icon,
  .mk-col:hover .mk-col__icon,
  .mk-col:hover .mk-col__icon--done,
  .mk-col__icon :deep(svg),
  .mk-col:has(.mk-ghost) .mk-col__icon { animation: none !important; }
}
</style>
