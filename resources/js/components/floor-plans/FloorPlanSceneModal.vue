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
        <canvas ref="view"></canvas>
        <p v-if="status" class="status">{{ status }}</p>
        <p class="notice">Conceptual 3D reconstruction — dimensions and unseen details are approximate.</p>
        <div class="tools">
          <button type="button" @click="showDollhouse">Dollhouse</button>
          <button type="button" @click="enterWalk">Walk inside</button>
          <button type="button" @click="resetView">Reset</button>
        </div>
        <p class="prompt">{{ prompt }}</p>
      </div>

      <footer class="scene-foot">
        <p>The sheet is the floor. Preview it, then save this 3D on the floor plan.</p>
        <button type="button" class="save" :disabled="!ready || saving || saved" @click="save">
          {{ saving ? 'Saving…' : saved ? 'Saved' : 'Save 3D' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script>
import { ref, watch, onBeforeUnmount, nextTick, computed } from 'vue'
import { mountFloorPlanScene } from './floorPlanScene.js'

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
    const status = ref('')
    const ready = ref(false)
    const saving = ref(false)
    const saved = ref(false)
    const mode = ref('dollhouse')
    let scene = null
    let ticket = 0
    let observer = null

    const modeLabel = computed(() => (mode.value === 'walk' ? 'Inside' : 'Dollhouse'))
    const prompt = computed(() => (
      mode.value === 'walk'
        ? 'Walk with W A S D · scroll to zoom a little · drag to look'
        : 'Drag to turn · scroll to zoom in or out · click Walk inside'
    ))

    function teardown() {
      ticket += 1
      observer?.disconnect()
      observer = null
      scene?.dispose()
      scene = null
      ready.value = false
    }

    async function build() {
      const current = ++ticket
      ready.value = false
      saved.value = Boolean(props.plan?.scene_saved_at)
      mode.value = 'dollhouse'
      status.value = 'Reading the floor plan…'
      await nextTick()
      dialog.value?.focus()
      try {
        const response = await fetch(props.plan.image_url)
        if (!response.ok) throw new Error('The floor plan image did not load.')
        if (current !== ticket) return
        const bitmap = await createImageBitmap(await response.blob())
        if (current !== ticket) {
          bitmap.close?.()
          return
        }
        const sheet = document.createElement('canvas')
        sheet.width = bitmap.width
        sheet.height = bitmap.height
        sheet.getContext('2d').drawImage(bitmap, 0, 0)
        bitmap.close?.()
        status.value = 'Building this plan in 3D…'
        await nextTick()
        if (current !== ticket || !view.value) return
        scene = mountFloorPlanScene(view.value, sheet, (state) => {
          mode.value = state.mode
        })
        observer = new ResizeObserver(() => scene?.resize())
        if (stage.value) observer.observe(stage.value)
        scene.resize()
        if (current !== ticket) return
        status.value = ''
        ready.value = true
      } catch (error) {
        if (current !== ticket) return
        status.value = error?.message || 'The plan could not be turned into a model.'
      }
    }

    async function save() {
      if (!props.plan?.id || saving.value) return
      saving.value = true
      status.value = ''
      try {
        const token = localStorage.getItem('token')
        const response = await fetch(`/api/listings/projects/floor-plan-images/${props.plan.id}/scene`, {
          method: 'PUT',
          headers: {
            Authorization: 'Bearer ' + token,
            Accept: 'application/json'
          }
        })
        const data = await response.json().catch(() => ({}))
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'The 3D could not be saved.')
        }
        saved.value = true
        emit('saved', data.data)
      } catch (error) {
        status.value = error?.message || 'The 3D could not be saved.'
      } finally {
        saving.value = false
      }
    }

    function showDollhouse() { scene?.showDollhouse() }
    function enterWalk() { scene?.enterWalk() }
    function resetView() { scene?.reset() }

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
      status,
      ready,
      saving,
      saved,
      modeLabel,
      prompt,
      showDollhouse,
      enterWalk,
      resetView,
      save,
      close
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
.icon-btn, .tools button, .save {
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
.tools {
  position: absolute;
  top: 12px;
  right: 12px;
  display: flex;
  gap: 8px;
}
.tools button, .save {
  padding: 8px 14px;
  font-size: 12px;
}
.save {
  background: #733e87;
  border-color: #733e87;
}
.save:disabled {
  opacity: 0.55;
  cursor: default;
}
</style>
