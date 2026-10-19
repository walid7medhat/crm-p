<template>
  <article
    class="mk-card"
    :class="{ 'is-done': stageDone, 'is-scheduled': scheduled }"
    :data-task-id="task.id"
    tabindex="0"
    @click="$emit('open', task)"
    @keydown.enter="$emit('open', task)"
  >
    <div class="mk-card__top">
      <span class="mk-card__check" :class="{ 'is-on': stageDone }" aria-hidden="true">
        <iconify-icon v-if="stageDone" icon="lucide:check" />
      </span>
      <span class="mk-card__dot" :style="{ background: tagColor }" />
      <h6 class="mk-card__title">{{ task.title }}</h6>
      <span v-if="task.source_task_id" class="mk-card__origin">Content</span>
      <span v-if="scheduled" class="mk-card__scheduled">Scheduled</span>
    </div>

    <img v-if="task.reference_image_url" class="mk-card__ref" :src="task.reference_image_url" alt="Reference" />

    <p v-if="task.category === 'content' && task.notes" class="mk-card__note">{{ task.notes }}</p>

    <div v-if="task.category === 'content' && (task.designer || task.also_video)" class="mk-card__chips">
      <span v-if="task.designer" class="mk-chiplet">Design</span>
      <span v-if="task.also_video" class="mk-chiplet mk-chiplet--video">Video</span>
    </div>

    <div v-if="task.category === 'design' && task.deliverables?.length" class="mk-card__chips">
      <span v-for="d in task.deliverables" :key="d" class="mk-chiplet">{{ DELIVERABLES[d]?.label || d }}</span>
    </div>

    <div v-if="task.category === 'production'" class="mk-card__channels">
      <span
        v-for="(ch, key) in CHANNELS"
        :key="key"
        class="mk-card__channel"
        :class="{ on: task.channels?.[key] }"
        :title="`${ch.label}${task.channels?.[key] ? ' ✓' : ''}`"
      ><iconify-icon :icon="ch.icon" /></span>
    </div>

    <footer class="mk-card__foot">
      <span class="mk-card__date" :class="{ 'is-late': overdue }">
        {{ task.due_date ? formatDate(task.due_date) : tagLabel }}
        <template v-if="dept"> · {{ dept.label }}</template>
      </span>
      <div class="mk-card__people">
        <template v-if="task.category === 'production'">
          <MkAvatar v-if="task.shooter" :user="task.shooter" :size="26" />
          <MkAvatar v-if="task.editor && task.editor.id !== task.shooter?.id" :user="task.editor" :size="26" />
        </template>
        <template v-else-if="task.category === 'content'">
          <MkAvatar v-if="task.assignee" :user="task.assignee" :size="26" />
          <MkAvatar v-if="task.designer && task.designer.id !== task.assignee?.id" :user="task.designer" :size="26" />
        </template>
        <MkAvatar v-else-if="task.assignee" :user="task.assignee" :size="26" />
      </div>
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import MkAvatar from './shared/MkAvatar.vue'
import {
  MARKETING_CATEGORIES, CONTENT_TYPES, DELIVERABLES, CHANNELS, formatDate, isoDate,DEPARTMENTS
} from '@/config/marketingConfig.js'

const props = defineProps({
  task: { type: Object, required: true },
  stageDone: { type: Boolean, default: false },
})
defineEmits(['open'])

const category = computed(() => MARKETING_CATEGORIES[props.task.category] || MARKETING_CATEGORIES.production)
const type = computed(() => (props.task.category === 'production' ? CONTENT_TYPES[props.task.type] : null))
const dept = computed(() => DEPARTMENTS[props.task.department])
// The tag is the card's only color hint: a small colored icon on a neutral pill.
const tagLabel = computed(() => (type.value ? type.value.label : category.value.label))
const tagColor = computed(() => (type.value ? type.value.color : category.value.color))
const overdue = computed(() => !props.stageDone && props.task.due_date && props.task.due_date < isoDate(new Date()))
const scheduled = computed(() => Number(props.task.posts_count || 0) > Number(props.task.posted_count || 0))

</script>

<style scoped>
.mk-card {
  position: relative;
  padding: 14px 14px 12px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid #eceaf2;
  box-shadow: 0 1px 2px rgba(28, 20, 48, 0.04);
  cursor: grab;
  outline: none;
  transition: box-shadow 0.18s, border-color 0.18s, transform 0.18s;
}
.mk-card:hover, .mk-card:focus-visible {
  border-color: #ddd8ea;
  box-shadow: 0 10px 24px -16px rgba(28, 20, 48, 0.35);
  transform: translateY(-1px);
}
.mk-card.is-done .mk-card__title { color: #9a93a8; }
.mk-card.is-scheduled {
  border-color: #e7c56a;
  background: linear-gradient(180deg, #fff8e8 0%, #fff 56%);
  box-shadow: 0 0 0 1px rgba(212, 160, 40, 0.35), 0 10px 22px -16px rgba(160, 110, 10, 0.55);
}
.mk-card__scheduled {
  flex-shrink: 0;
  margin-top: 1px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px !important;
  font-weight: 700 !important;
  letter-spacing: 0.02em;
  color: #8a5a00;
  background: #fff3d1;
  border: 1px solid #f0d48a;
}
.mk-card__origin {
  flex-shrink: 0;
  margin-top: 1px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px !important;
  font-weight: 700 !important;
  color: #0f766e;
  background: #e7f6f4;
  border: 1px solid #b7e4de;
}
.mk-card__ref {
  display: block;
  width: calc(100% - 24px);
  height: 96px;
  margin: 10px 0 0 24px;
  border-radius: 10px;
  object-fit: cover;
  background: #f6f4f8;
}
.mk-chiplet--video { color: #9f1239; background: #ffe4e6; }

.mk-card__top { display: flex; align-items: flex-start; gap: 8px; min-width: 0; }
.mk-card__check {
  width: 16px;
  height: 16px;
  margin-top: 2px;
  border-radius: 5px;
  border: 1.5px solid #d9d4e4;
  background: #fff;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: #fff;
  font-size: 11px !important;
}
.mk-card__check.is-on { background: var(--color-primary); border-color: var(--color-primary); }
.mk-card__dot {
  width: 8px;
  height: 8px;
  margin-top: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

.mk-card__title {
  margin: 0;
  flex: 1;
  min-width: 0;
  font-size: 14px !important;
  font-weight: 600 !important;
  line-height: 1.35;
  color: #241c30;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}
.mk-card__note {
  margin: 8px 0 0 24px;
  font-size: 12px !important;
  color: #8d869c;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.mk-card__chips { display: flex; flex-wrap: wrap; gap: 4px; margin: 8px 0 0 24px; }
.mk-chiplet {
  padding: 2px 8px;
  border-radius: 8px;
  font-size: 11px !important;
  font-weight: 600 !important;
  color: #6d4ea3;
  background: #f3eefb;
  border: 0;
}

.mk-card__channels { display: flex; gap: 6px; margin: 8px 0 0 24px; }
.mk-card__channel {
  display: inline-flex;
  font-size: 14px !important;
  color: var(--text-secondary-light);
  opacity: 0.3;
}
.mk-card__channel.on { opacity: 1; color: var(--text-primary-light); }

.mk-card__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 12px;
  padding-left: 24px;
}
.mk-card__date {
  font-size: 12px !important;
  font-weight: 500 !important;
  color: #9a93a8;
}
.mk-card__date.is-late { color: #c2410c; }
.mk-card__people { display: flex; align-items: center; }
.mk-card__people > * + * { margin-left: -8px; }

@media (prefers-reduced-motion: reduce) {
  .mk-card { transition: none; }
  .mk-card:hover { transform: none; }
}
</style>
