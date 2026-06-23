<template>
  <div class="rec-kanban" :class="{ 'rec-kanban--mobile': isMobile }">
    <div
      v-for="stage in stages"
      :key="stage.id"
      class="rec-kanban__column"
      :style="{ '--stage-color': stage.color }"
    >
      <header class="rec-kanban__column-head">
        <span class="rec-kanban__dot" />
        <h6>{{ stage.label }}</h6>
        <span class="rec-kanban__count">{{ (board[stage.id] || []).length }}</span>
      </header>

      <draggable
        :list="board[stage.id]"
        item-key="id"
        group="recruitment-pipeline"
        class="rec-kanban__list"
        :animation="180"
        ghost-class="rec-kanban__ghost"
        @change="(evt) => onChange(evt, stage.id)"
      >
        <template #item="{ element }">
          <div class="rec-kanban__card" @click="$emit('select-applicant', element)">
            <img :src="element.avatar" :alt="element.name" />
            <div>
              <strong>{{ element.name }}</strong>
              <p>{{ element.appliedPosition }}</p>
              <small>{{ element.experienceLevel }} · {{ formatDate(element.applicationDate) }}</small>
            </div>
            <button type="button" class="rec-kanban__card-menu" @click.stop="$emit('move-applicant', element)">
              <iconify-icon icon="lucide:more-horizontal" />
            </button>
          </div>
        </template>
      </draggable>
    </div>
  </div>
</template>

<script setup>
import draggable from 'vuedraggable'

const props = defineProps({
  board: { type: Object, required: true },
  stages: { type: Array, required: true },
  isMobile: { type: Boolean, default: false },
})

const emit = defineEmits(['move-stage', 'select-applicant', 'move-applicant'])

function onChange(evt, stageId) {
  if (evt.added) {
    emit('move-stage', { applicant: evt.added.element, stageId })
  }
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}
</script>
