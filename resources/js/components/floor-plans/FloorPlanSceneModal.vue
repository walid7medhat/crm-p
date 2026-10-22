<template>
  <div v-if="open" class="scene-modal" role="dialog" aria-modal="true" aria-labelledby="scene-title">
    <div class="scene-dialog" ref="dialog" tabindex="-1">
      <header class="scene-head">
        <div>
          <p class="eyebrow">OIA Properties · conceptual study</p>
          <h2 id="scene-title">{{ plan?.name || 'Floor plan' }}</h2>
          <p class="meta">{{ modeLabel }}</p>
        </div>
        <button type="button" class="icon-btn" @click="close" aria-label="Close">
          <i class="fas fa-times"></i>
        </button>
      </header>

      <div class="stage" ref="stage">
        <canvas v-show="step === 'model'" ref="view"></canvas>
        <canvas
          v-show="step === 'review'"
          ref="review"
          class="review"
          :class="{ framing: tool === 'area' }"
          @pointerdown="onReviewDown"
          @pointermove="onReviewMove"
          @pointerup="onReviewUp"
          @wheel.prevent="onReviewWheel"
        ></canvas>
        <p v-if="status" class="status">{{ status }}</p>
        <div v-if="step === 'review' && regions.length > 1" class="regions">
          <button
            v-for="(region, index) in regions"
            :key="index"
            type="button"
            :class="{ on: index === regionIndex }"
            @click="chooseRegion(index)"
          >Plan {{ index + 1 }}</button>
        </div>
        <p class="notice">Conceptual 3D reconstruction — dimensions and unseen details are approximate.</p>
        <div v-if="step === 'review'" class="tools">
          <button type="button" :class="{ on: tool === 'area' }" @click="tool = 'area'">Plan area</button>
          <button type="button" :class="{ on: tool === 'wall' }" @click="tool = 'wall'">Add wall</button>
          <button type="button" @click="useWholeSheet">Full sheet</button>
        </div>
        <div v-if="step === 'model'" class="tools">
          <button type="button" @click="zoomOut">Zoom out</button>
          <button type="button" @click="zoomIn">Zoom in</button>
          <button type="button" @click="resetView">Whole plan</button>
          <button type="button" @click="enterWalk">Go inside</button>
          <button type="button" @click="showDollhouse">Back</button>
          <button type="button" @click="backToReview">Edit</button>
        </div>
        <p v-if="step === 'model'" class="prompt">{{ prompt }}</p>
      </div>

      <footer class="scene-foot">
        <p v-if="step === 'review'">{{ note }} Plan area: drag a box around every room. Scroll to look closer.</p>
        <p v-else>Scroll to zoom out or in. Go inside to walk the rooms. Back returns to the full plan.</p>
        <div class="actions">
          <button v-if="step === 'review'" type="button" class="ghost" :disabled="!sheetReady || tracing" @click="traceArea">
            {{ tracing ? 'Tracing…' : 'Trace this area' }}
          </button>
          <button v-if="step === 'review'" type="button" class="save" :disabled="!sheetReady || tracing" @click="buildModel">Build 3D</button>
          <button v-else type="button" class="save" :disabled="!ready || saving || saved" @click="save">
            {{ saving ? 'Saving…' : saved ? 'Saved' : 'Save 3D' }}
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>

<script>
import { ref, watch, onBeforeUnmount, nextTick, computed } from 'vue'
import { mountFloorPlanScene } from './floorPlanScene.js'
import { analyzeFloorPlan, sceneFromWalls } from './analyzeFloorPlan.js'

export default {
  name: 'FloorPlanSceneModal',
  props: {
    open: { type: Boolean, default: false },
    plan: { type: Object, default: null }
  },
  emits: ['close', 'saved'],
  setup(props, { emit }) {
    const dialog = ref(null)
    const stage = ref(null)
    const view = ref(null)
    const review = ref(null)
    const status = ref('')
    const ready = ref(false)
    const sheetReady = ref(false)
    const saving = ref(false)
    const saved = ref(false)
    const mode = ref('dollhouse')
    const step = ref('review')
    const note = ref('Reading the drawing…')
    const regions = ref([])
    const regionIndex = ref(0)
    const tool = ref('area')
    const tracing = ref(false)
    let reviewZoom = 1
    let reviewPan = { x: 0, y: 0 }
    let scene = null
    let ticket = 0
    let observer = null
    let sheet = null
    let drag = null

    const modeLabel = computed(() => {
      if (step.value === 'review') return regions.value.length > 1 ? 'Choose the plan' : 'Check the walls'
      return mode.value === 'walk' ? 'Inside' : 'Dollhouse'
    })
    const prompt = computed(() => (
      mode.value === 'walk'
        ? 'Walk with W A S D · scroll to look closer · Back returns to the plan'
        : 'Scroll to zoom out or in · drag to turn · Go inside to walk'
    ))

    function teardownScene() {
      observer?.disconnect()
      observer = null
      scene?.dispose()
      scene = null
      ready.value = false
    }

    function teardown() {
      ticket += 1
      teardownScene()
      sheet = null
      regions.value = []
      sheetReady.value = false
      reviewZoom = 1
      reviewPan = { x: 0, y: 0 }
      tool.value = 'area'
    }

    function activeRegion() {
      return regions.value[regionIndex.value] || null
    }

    function centerline(wall) {
      const vertical = wall.axis === 'v' || (wall.axis !== 'h' && Math.abs(wall.x1 - wall.x0) < Math.abs(wall.y1 - wall.y0))
      if (vertical) {
        const x = (wall.x0 + wall.x1) / 2
        return [x, Math.min(wall.y0, wall.y1), x, Math.max(wall.y0, wall.y1)]
      }
      const y = (wall.y0 + wall.y1) / 2
      return [Math.min(wall.x0, wall.x1), y, Math.max(wall.x0, wall.x1), y]
    }

    function drawReview() {
      const canvas = review.value
      const region = activeRegion()
      if (!canvas || !sheet || !region) return
      const rect = canvas.parentElement.getBoundingClientRect()
      canvas.width = Math.max(1, Math.floor(rect.width))
      canvas.height = Math.max(1, Math.floor(rect.height))
      const context = canvas.getContext('2d')
      context.clearRect(0, 0, canvas.width, canvas.height)
      context.fillStyle = '#17141a'
      context.fillRect(0, 0, canvas.width, canvas.height)
      const fit = viewFit()
      context.drawImage(sheet, fit.ox, fit.oy, sheet.width * fit.s, sheet.height * fit.s)
      const left = fit.ox + region.bounds.x0 * fit.s
      const top = fit.oy + region.bounds.y0 * fit.s
      const boxWidth = (region.bounds.x1 - region.bounds.x0) * fit.s
      const boxHeight = (region.bounds.y1 - region.bounds.y0) * fit.s
      context.fillStyle = 'rgba(12, 10, 16, 0.5)'
      context.fillRect(0, 0, canvas.width, Math.max(0, top))
      context.fillRect(0, top, Math.max(0, left), boxHeight)
      context.fillRect(left + boxWidth, top, Math.max(0, canvas.width - left - boxWidth), boxHeight)
      context.fillRect(0, top + boxHeight, canvas.width, Math.max(0, canvas.height - top - boxHeight))
      region.walls.forEach((wall) => {
        const [x0, y0, x1, y1] = centerline(wall)
        const dropped = wall.enabled === false
        const uncertain = wall.confident === false
        context.strokeStyle = uncertain ? '#d4af37' : (dropped ? 'rgba(196, 189, 201, 0.45)' : '#d23b3b')
        context.lineWidth = uncertain || dropped ? 2 : 3
        context.setLineDash(uncertain ? [7, 5] : [])
        context.beginPath()
        context.moveTo(fit.ox + x0 * fit.s, fit.oy + y0 * fit.s)
        context.lineTo(fit.ox + x1 * fit.s, fit.oy + y1 * fit.s)
        context.stroke()
      })
      context.setLineDash([])
      context.strokeStyle = '#d4af37'
      context.lineWidth = 2
      context.strokeRect(left, top, boxWidth, boxHeight)
      context.fillStyle = '#d4af37'
      ;[[left, top], [left + boxWidth, top], [left, top + boxHeight], [left + boxWidth, top + boxHeight]].forEach(([x, y]) => {
        context.fillRect(x - 5, y - 5, 10, 10)
      })
    }

    function viewFit() {
      const canvas = review.value
      const base = Math.min(canvas.width / sheet.width, canvas.height / sheet.height)
      const s = base * reviewZoom
      return {
        s,
        ox: (canvas.width - sheet.width * s) / 2 + reviewPan.x,
        oy: (canvas.height - sheet.height * s) / 2 + reviewPan.y
      }
    }

    function imagePoint(event) {
      const rect = review.value.getBoundingClientRect()
      const fit = viewFit()
      return {
        x: (event.clientX - rect.left - fit.ox) / fit.s,
        y: (event.clientY - rect.top - fit.oy) / fit.s
      }
    }

    function wallAt(point) {
      const region = activeRegion()
      if (!region) return -1
      let best = -1
      let bestDistance = 12
      region.walls.forEach((wall, index) => {
        const [x0, y0, x1, y1] = centerline(wall)
        const dx = x1 - x0
        const dy = y1 - y0
        const length = Math.hypot(dx, dy) || 1
        const t = Math.max(0, Math.min(1, ((point.x - x0) * dx + (point.y - y0) * dy) / (length * length)))
        const distance = Math.hypot(point.x - (x0 + dx * t), point.y - (y0 + dy * t))
        if (distance < bestDistance) {
          bestDistance = distance
          best = index
        }
      })
      return best
    }

    function onReviewDown(event) {
      const region = activeRegion()
      if (!region || step.value !== 'review' || tracing.value) return
      review.value?.setPointerCapture?.(event.pointerId)
      const point = imagePoint(event)
      const box = region.bounds
      const fit = viewFit()
      const corner = 16 / fit.s
      const nearLeft = Math.abs(point.x - box.x0) < corner
      const nearRight = Math.abs(point.x - box.x1) < corner
      const nearTop = Math.abs(point.y - box.y0) < corner
      const nearBottom = Math.abs(point.y - box.y1) < corner
      if ((nearLeft || nearRight) && (nearTop || nearBottom)) {
        drag = { kind: 'corner', x: point.x, y: point.y, left: nearLeft, top: nearTop, bounds: { ...box } }
        return
      }
      if (tool.value === 'area') {
        drag = { kind: 'area', x: point.x, y: point.y, cx: point.x, cy: point.y }
        return
      }
      if (nearLeft || nearRight || nearTop || nearBottom) {
        drag = { kind: 'move', x: point.x, y: point.y, bounds: { ...box } }
        return
      }
      const hit = wallAt(point)
      if (hit >= 0) {
        region.walls[hit].enabled = region.walls[hit].enabled === false
        drawReview()
        return
      }
      drag = { kind: 'draw', x: point.x, y: point.y, cx: point.x, cy: point.y }
    }

    function onReviewMove(event) {
      if (!drag) return
      const region = activeRegion()
      const point = imagePoint(event)
      const start = drag.bounds
      if (drag.kind === 'move') {
        const dx = point.x - drag.x
        const dy = point.y - drag.y
        region.bounds = {
          x0: start.x0 + dx,
          y0: start.y0 + dy,
          x1: start.x1 + dx,
          y1: start.y1 + dy
        }
      } else if (drag.kind === 'corner') {
        region.bounds = {
          x0: drag.left ? point.x : start.x0,
          y0: drag.top ? point.y : start.y0,
          x1: drag.left ? start.x1 : point.x,
          y1: drag.top ? start.y1 : point.y
        }
        if (region.bounds.x1 < region.bounds.x0) {
          const swap = region.bounds.x0
          region.bounds.x0 = region.bounds.x1
          region.bounds.x1 = swap
        }
        if (region.bounds.y1 < region.bounds.y0) {
          const swap = region.bounds.y0
          region.bounds.y0 = region.bounds.y1
          region.bounds.y1 = swap
        }
      } else if (drag.kind === 'draw' || drag.kind === 'area') {
        drag.cx = point.x
        drag.cy = point.y
      }
      drawReview()
      if (drag.kind === 'area') {
        const fit = viewFit()
        const context = review.value.getContext('2d')
        const x = Math.min(drag.x, drag.cx)
        const y = Math.min(drag.y, drag.cy)
        context.strokeStyle = '#d4af37'
        context.lineWidth = 2
        context.strokeRect(fit.ox + x * fit.s, fit.oy + y * fit.s, Math.abs(drag.cx - drag.x) * fit.s, Math.abs(drag.cy - drag.y) * fit.s)
      } else if (drag.kind === 'draw') {
        const fit = viewFit()
        const context = review.value.getContext('2d')
        context.strokeStyle = '#733e87'
        context.lineWidth = 3
        context.beginPath()
        context.moveTo(fit.ox + drag.x * fit.s, fit.oy + drag.y * fit.s)
        context.lineTo(fit.ox + drag.cx * fit.s, fit.oy + drag.cy * fit.s)
        context.stroke()
      }
    }

    function onReviewUp() {
      const region = activeRegion()
      if (drag?.kind === 'area' && region && sheet) {
        const x0 = Math.max(0, Math.min(drag.x, drag.cx))
        const y0 = Math.max(0, Math.min(drag.y, drag.cy))
        const x1 = Math.min(sheet.width, Math.max(drag.x, drag.cx))
        const y1 = Math.min(sheet.height, Math.max(drag.y, drag.cy))
        drag = null
        if (x1 - x0 > 48 && y1 - y0 > 48) {
          region.bounds = { x0, y0, x1, y1 }
          region.walls = []
          drawReview()
          traceArea()
          return
        }
        drawReview()
        return
      }
      if (drag?.kind === 'draw' && region) {
        const dx = drag.cx - drag.x
        const dy = drag.cy - drag.y
        if (Math.hypot(dx, dy) > 16) {
          const horizontal = Math.abs(dx) >= Math.abs(dy)
          const thickness = 8
          region.walls.push(horizontal
            ? {
              axis: 'h',
              x0: Math.min(drag.x, drag.cx),
              x1: Math.max(drag.x, drag.cx),
              y0: drag.y - thickness / 2,
              y1: drag.y + thickness / 2,
              confident: true,
              enabled: true
            }
            : {
              axis: 'v',
              x0: drag.x - thickness / 2,
              x1: drag.x + thickness / 2,
              y0: Math.min(drag.y, drag.cy),
              y1: Math.max(drag.y, drag.cy),
              confident: true,
              enabled: true
            })
        }
      }
      drag = null
      drawReview()
    }

    function onReviewWheel(event) {
      if (step.value !== 'review' || !sheet || !review.value) return
      const before = imagePoint(event)
      const nextZoom = Math.max(1, Math.min(5, reviewZoom * (event.deltaY > 0 ? 0.88 : 1.14)))
      const canvas = review.value
      const base = Math.min(canvas.width / sheet.width, canvas.height / sheet.height)
      const newS = base * nextZoom
      const rect = canvas.getBoundingClientRect()
      reviewZoom = nextZoom
      reviewPan = nextZoom === 1
        ? { x: 0, y: 0 }
        : {
          x: event.clientX - rect.left - before.x * newS - (canvas.width - sheet.width * newS) / 2,
          y: event.clientY - rect.top - before.y * newS - (canvas.height - sheet.height * newS) / 2
        }
      drawReview()
    }

    function useWholeSheet() {
      const region = activeRegion()
      if (!region || !sheet || tracing.value) return
      region.bounds = { x0: 0, y0: 0, x1: sheet.width, y1: sheet.height }
      drawReview()
    }

    async function traceArea() {
      const region = activeRegion()
      if (!region || !sheet || tracing.value) return
      const current = ticket
      tracing.value = true
      saved.value = false
      status.value = 'Reading the area you drew…'
      try {
        const analysis = await analyzeFloorPlan(sheet, async (message) => {
          if (current !== ticket) return
          status.value = message
          await new Promise((resolve) => setTimeout(resolve, 20))
        }, {
          x0: region.bounds.x0,
          y0: region.bounds.y0,
          x1: region.bounds.x1,
          y1: region.bounds.y1
        })
        if (current !== ticket) return
        const next = analysis.regions[0]
        if (next) {
          regions.value = [next]
          regionIndex.value = 0
        }
        note.value = analysis.note
        status.value = ''
      } catch (error) {
        if (current !== ticket) return
        status.value = error?.message || 'That area could not be traced.'
      } finally {
        if (current === ticket) {
          tracing.value = false
          await nextTick()
          drawReview()
        }
      }
    }

    function chooseRegion(index) {
      regionIndex.value = index
      drawReview()
    }

    function applySaved(data) {
      const walls = (data.walls || []).map((wall) => {
        const values = Array.isArray(wall) ? wall : [wall.x0, wall.y0, wall.x1, wall.y1]
        return {
          x0: values[0],
          y0: values[1],
          x1: values[2],
          y1: values[3],
          axis: Math.abs(values[2] - values[0]) >= Math.abs(values[3] - values[1]) ? 'h' : 'v',
          confident: Array.isArray(wall) ? true : wall.confident !== false,
          enabled: true
        }
      })
      const bounds = data.bounds || {
        x0: Math.min(...walls.map((wall) => Math.min(wall.x0, wall.x1))),
        y0: Math.min(...walls.map((wall) => Math.min(wall.y0, wall.y1))),
        x1: Math.max(...walls.map((wall) => Math.max(wall.x0, wall.x1))),
        y1: Math.max(...walls.map((wall) => Math.max(wall.y0, wall.y1)))
      }
      regions.value = [{ bounds, walls }]
      regionIndex.value = 0
      note.value = 'This is the reconstruction you saved. Click a line to drop it, or read the drawing again.'
    }

    async function loadSheet(current) {
      const response = await fetch(props.plan.image_url)
      if (!response.ok) throw new Error('The floor plan image did not load.')
      if (current !== ticket) return false
      const bitmap = await createImageBitmap(await response.blob())
      if (current !== ticket) {
        bitmap.close?.()
        return false
      }
      sheet = document.createElement('canvas')
      sheet.width = bitmap.width
      sheet.height = bitmap.height
      sheet.getContext('2d').drawImage(bitmap, 0, 0)
      bitmap.close?.()
      return true
    }

    async function build() {
      const current = ++ticket
      teardownScene()
      saved.value = Boolean(props.plan?.scene_saved_at)
      mode.value = 'dollhouse'
      step.value = 'review'
      status.value = 'Reading the floor plan…'
      sheetReady.value = false
      note.value = 'Finding the plan and its walls…'
      await nextTick()
      dialog.value?.focus()
      try {
        const loaded = await loadSheet(current)
        if (!loaded || current !== ticket) return
        status.value = 'Finding the floor-plan region…'
        await new Promise((resolve) => setTimeout(resolve, 30))
        if (current !== ticket) return
        const savedScene = props.plan?.scene_data
        const savedMatches = savedScene?.walls?.length && (!savedScene.source || savedScene.source === props.plan.image_url)
        if (savedMatches) {
          status.value = 'Opening the saved reconstruction…'
          applySaved(savedScene)
        } else {
          const analysis = await analyzeFloorPlan(sheet, async (message) => {
            if (current !== ticket) return
            status.value = message
            await new Promise((resolve) => setTimeout(resolve, 16))
          })
          regions.value = analysis.regions
          regionIndex.value = 0
          note.value = analysis.note
          if (!regions.value.length) {
            regions.value = [{
              bounds: { x0: 0, y0: 0, x1: sheet.width, y1: sheet.height },
              walls: []
            }]
          }
        }
        status.value = ''
        sheetReady.value = true
        step.value = 'review'
        await nextTick()
        drawReview()
      } catch (error) {
        if (current !== ticket) return
        status.value = error?.message || 'The plan could not be read.'
      }
    }

    async function detectAgain() {
      if (!sheet) return
      status.value = 'Finding the floor-plan region…'
      await new Promise((resolve) => setTimeout(resolve, 30))
      const analysis = await analyzeFloorPlan(sheet, async (message) => {
        status.value = message
        await new Promise((resolve) => setTimeout(resolve, 16))
      })
      regions.value = analysis.regions.length ? analysis.regions : [{
        bounds: { x0: 0, y0: 0, x1: sheet.width, y1: sheet.height },
        walls: []
      }]
      regionIndex.value = 0
      note.value = analysis.note
      status.value = ''
      saved.value = false
      await nextTick()
      drawReview()
    }

    async function buildModel() {
      const region = activeRegion()
      if (!region || !sheet || !view.value) return
      teardownScene()
      step.value = 'model'
      status.value = 'Building the dollhouse from the approved lines…'
      await nextTick()
      const traced = sceneFromWalls(region.bounds, region.walls.filter((wall) => {
        const cx = (wall.x0 + wall.x1) / 2
        const cy = (wall.y0 + wall.y1) / 2
        return cx >= region.bounds.x0 && cx <= region.bounds.x1 && cy >= region.bounds.y0 && cy <= region.bounds.y1
      }))
      scene = mountFloorPlanScene(view.value, sheet, (state) => {
        mode.value = state.mode
      }, traced)
      observer = new ResizeObserver(() => scene?.resize())
      if (stage.value) observer.observe(stage.value)
      scene.resize()
      status.value = ''
      ready.value = true
    }

    function backToReview() {
      teardownScene()
      step.value = 'review'
      nextTick(() => drawReview())
    }

    async function save() {
      if (!props.plan?.id || saving.value) return
      const region = activeRegion()
      const payload = region ? { ...sceneFromWalls(region.bounds, region.walls), source: props.plan.image_url } : null
      saving.value = true
      status.value = ''
      try {
        const token = localStorage.getItem('token')
        const response = await fetch(`/api/listings/projects/floor-plan-images/${props.plan.id}/scene`, {
          method: 'PUT',
          headers: {
            Authorization: 'Bearer ' + token,
            Accept: 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ scene: payload })
        })
        const data = await response.json().catch(() => ({}))
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'The 3D could not be saved.')
        }
        saved.value = true
        emit('saved', { ...data.data, scene_data: payload })
      } catch (error) {
        status.value = error?.message || 'The 3D could not be saved.'
      } finally {
        saving.value = false
      }
    }

    function showDollhouse() { scene?.showDollhouse() }
    function enterWalk() { scene?.enterWalk() }
    function resetView() { scene?.reset() }
    function zoomIn() { scene?.zoomIn() }
    function zoomOut() { scene?.zoomOut() }

    function close() {
      teardown()
      emit('close')
    }

    function onKey(event) {
      if (event.key === 'Escape') close()
    }

    watch(() => [props.open, props.plan?.id], ([open]) => {
      teardown()
      if (open && props.plan?.image_url) build()
    })

    watch(() => props.open, (open) => {
      if (open) window.addEventListener('keydown', onKey)
      else window.removeEventListener('keydown', onKey)
    })

    onBeforeUnmount(() => {
      teardown()
      window.removeEventListener('keydown', onKey)
    })

    return {
      dialog,
      stage,
      view,
      review,
      status,
      ready,
      sheetReady,
      saving,
      saved,
      step,
      note,
      regions,
      regionIndex,
      modeLabel,
      prompt,
      showDollhouse,
      enterWalk,
      resetView,
      zoomIn,
      zoomOut,
      tool,
      tracing,
      save,
      close,
      buildModel,
      backToReview,
      detectAgain,
      chooseRegion,
      useWholeSheet,
      traceArea,
      onReviewDown,
      onReviewMove,
      onReviewUp,
      onReviewWheel
    }
  }
}
</script>

<style scoped>
.scene-modal {
  position: fixed;
  inset: 0;
  z-index: 120000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(12, 10, 16, 0.72);
}
.scene-dialog {
  width: min(1100px, 100%);
  height: min(820px, calc(100vh - 48px));
  display: flex;
  flex-direction: column;
  background: #17141a;
  color: #f7f3ec;
  border: 1px solid rgba(212, 175, 55, 0.45);
  border-radius: 18px;
  overflow: hidden;
  outline: none;
}
.scene-head, .scene-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 18px;
}
.eyebrow {
  margin: 0;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  font-size: 11px;
  color: #d4af37;
}
h2 {
  margin: 2px 0 0;
  font-family: Palatino, "Iowan Old Style", Georgia, serif;
  font-size: 22px;
  font-weight: 500;
}
.meta, .scene-foot p, .prompt {
  margin: 0;
  color: #c4bdc9;
  font-size: 12px;
}
.scene-foot p {
  max-width: 46rem;
  line-height: 1.45;
}
.icon-btn, .tools button, .save, .ghost, .regions button {
  border: 1px solid rgba(212, 175, 55, 0.45);
  background: rgba(18, 15, 22, 0.82);
  color: #f7f3ec;
  border-radius: 999px;
  cursor: pointer;
}
.icon-btn {
  width: 36px;
  height: 36px;
}
.stage {
  position: relative;
  flex: 1;
  min-height: 0;
  background: #17141a;
}
canvas {
  width: 100%;
  height: 100%;
  display: block;
  touch-action: none;
}
canvas.framing {
  cursor: crosshair;
}
.status {
  position: absolute;
  left: 50%;
  top: 46%;
  transform: translate(-50%, -50%);
  width: min(28rem, calc(100% - 32px));
  margin: 0;
  padding: 18px 22px;
  background: #f7f1e6;
  color: #1c1424;
  border: 1px solid #d4af37;
  border-radius: 16px;
  text-align: center;
}
.notice, .prompt {
  position: absolute;
  left: 16px;
  right: 16px;
  pointer-events: none;
}
.notice {
  bottom: 52px;
  margin: 0;
  color: #f7f3ec;
  font-size: 12px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
}
.prompt {
  bottom: 14px;
  text-align: right;
}
.tools, .regions {
  position: absolute;
  top: 12px;
  right: 12px;
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  max-width: min(640px, calc(100% - 24px));
}
.regions {
  left: 12px;
  right: auto;
}
.tools button, .save, .ghost, .regions button {
  padding: 8px 14px;
  font-size: 12px;
}
.tools button.on, .regions button.on, .save {
  background: #733e87;
  border-color: #733e87;
}
.save:disabled {
  opacity: 0.55;
  cursor: default;
}
.actions {
  display: flex;
  gap: 8px;
  flex: 0 0 auto;
}
</style>
