<template>
  <MkModal
    :model-value="modelValue"
    :title="`${cat.label} stages`"
    subtitle="Add, rename, recolor, reorder or delete stages for this board"
    icon="lucide:columns-3"
    :accent="cat.color"
    width="600px"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <draggable v-model="list" item-key="id" handle=".mk-stage__grip" class="mk-stages" :animation="180" @end="saveOrder">
      <template #item="{ element }">
        <div class="mk-stage" :style="{ '--stage': element.color }">
          <span class="mk-stage__grip" title="Drag to reorder"><iconify-icon icon="lucide:grip-vertical" /></span>
          <label class="mk-stage__color" :title="'Color'">
            <input type="color" :value="element.color" @change="update(element, { color: $event.target.value })" />
          </label>
          <input
            class="mk-input mk-stage__name"
            :value="element.name"
            maxlength="60"
            @change="update(element, { name: $event.target.value.trim() || element.name })"
          />
          <button
            type="button"
            class="mk-stage__done"
            :class="{ on: element.is_done }"
            title="Counts as completed (stamps the completion date)"
            @click="update(element, { is_done: !element.is_done })"
          >
            <iconify-icon icon="lucide:flag" /> Done
          </button>
          <span class="mk-stage__count">{{ element.tasks?.length ?? 0 }}</span>
          <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon mk-stage__del" title="Delete stage" @click="remove(element)">
            <iconify-icon icon="lucide:trash-2" />
          </button>
        </div>
      </template>
    </draggable>

    <form class="mk-stage-new" @submit.prevent="add">
      <div class="mk-stage-new__swatches">
        <button
          v-for="c in STAGE_COLOR_SWATCHES"
          :key="c"
          type="button"
          :class="{ active: newColor === c }"
          :style="{ background: c }"
          :aria-label="c"
          @click="newColor = c"
        />
      </div>
      <div class="mk-stage-new__row">
        <input v-model.trim="newName" class="mk-input" maxlength="60" placeholder="New stage name (e.g. Scheduled)" />
        <button type="submit" class="mk-btn mk-btn--primary" :disabled="!newName || busy">
          <iconify-icon icon="lucide:plus" /> Add stage
        </button>
      </div>
    </form>
  </MkModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkModal from './shared/MkModal.vue'
import { MARKETING_CATEGORIES, STAGE_COLOR_SWATCHES, apiError } from '@/config/marketingConfig.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  category: { type: String, required: true },
  stages: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue', 'changed'])

const cat = computed(() => MARKETING_CATEGORIES[props.category])
const list = ref([])
const newName = ref('')
const newColor = ref(STAGE_COLOR_SWATCHES[0])
const busy = ref(false)

watch(() => [props.modelValue, props.stages], ([open]) => {
  if (open) list.value = props.stages.map((s) => ({ ...s }))
}, { immediate: true })

async function run(fn, errorTitle) {
  busy.value = true
  try {
    await fn()
    emit('changed')
  } catch (err) {
    Swal.fire({ icon: 'error', title: errorTitle, text: apiError(err) })
    emit('changed')
  } finally {
    busy.value = false
  }
}

function update(stage, patch) {
  Object.assign(stage, patch)
  run(() => api.put(`/marketing/stages/${stage.id}`, patch), 'Could not update stage')
}

function saveOrder() {
  run(() => api.post('/marketing/stages/reorder', { category: props.category, ids: list.value.map((s) => s.id) }), 'Could not reorder')
}

function add() {
  if (!newName.value) return
  const payload = { category: props.category, name: newName.value, color: newColor.value, is_done: false }
  run(async () => {
    await api.post('/marketing/stages', payload)
    newName.value = ''
  }, 'Could not add stage')
}

async function remove(stage) {
  const count = stage.tasks?.length ?? 0
  const others = list.value.filter((s) => s.id !== stage.id)
  if (!others.length) {
    Swal.fire({ icon: 'info', title: 'A board needs at least one stage.' })
    return
  }

  let moveTo = null
  if (count > 0) {
    const res = await Swal.fire({
      icon: 'warning',
      title: `Delete “${stage.name}”?`,
      html: `It has <b>${count}</b> card(s). Move them to:`,
      input: 'select',
      inputOptions: Object.fromEntries(others.map((s) => [s.id, s.name])),
      showCancelButton: true,
      confirmButtonText: 'Move & delete',
      confirmButtonColor: '#dc2626',
    })
    if (!res.isConfirmed) return
    moveTo = Number(res.value)
  } else {
    const res = await Swal.fire({ icon: 'warning', title: `Delete “${stage.name}”?`, showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' })
    if (!res.isConfirmed) return
  }

  run(() => api.delete(`/marketing/stages/${stage.id}`, { data: { move_to_stage_id: moveTo } }), 'Could not delete stage')
}
</script>

<style scoped>
.mk-stages { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
.mk-stage {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 14px;
  border: 1px solid var(--border-color);
  border-left: 5px solid var(--stage);
  background: var(--white);
}
.mk-stage__grip { cursor: grab; color: var(--text-secondary-light); display: inline-flex; font-size: 18px !important; }
.mk-stage__color { position: relative; width: 28px; height: 28px; border-radius: 9px; overflow: hidden; background: var(--stage); flex-shrink: 0; cursor: pointer; margin: 0; }
.mk-stage__color input { position: absolute; inset: -6px; width: 40px; height: 40px; opacity: 0; cursor: pointer; }
.mk-stage__name { flex: 1; min-height: 36px; padding: 6px 10px; font-weight: 600 !important; }
.mk-stage__done {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 9px;
  border-radius: 9px;
  border: 1px solid var(--border-color);
  background: transparent;
  font-size: 12px !important;
  font-weight: 700 !important;
  color: var(--text-secondary-light);
}
.mk-stage__done.on { color: #fff; background: #16a34a; border-color: #16a34a; }
.mk-stage__count { min-width: 26px; text-align: center; font-size: 12px !important; font-weight: 800 !important; color: var(--text-secondary-light); }
.mk-stage__del:hover { color: #dc2626 !important; border-color: #dc2626 !important; }
.mk-stage-new { padding: 14px; border-radius: 14px; border: 1.5px dashed var(--border-color); }
.mk-stage-new__swatches { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.mk-stage-new__swatches button { width: 24px; height: 24px; border-radius: 50%; border: 0; box-shadow: 0 0 0 2px var(--white), 0 0 0 3px transparent; transition: box-shadow 0.15s; }
.mk-stage-new__swatches button.active { box-shadow: 0 0 0 2px var(--white), 0 0 0 4px var(--text-primary-light); }
.mk-stage-new__row { display: flex; gap: 8px; }
@media (max-width: 576px) {
  .mk-stage { flex-wrap: wrap; }
  .mk-stage-new__row { flex-direction: column; }
}
</style>
