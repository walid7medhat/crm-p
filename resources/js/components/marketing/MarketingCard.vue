<template>
  <article
    class="mk-card"
    :class="{ 'is-done': stageDone }"
    :data-task-id="task.id"
    tabindex="0"
    @click="$emit('open', task)"
    @keydown.enter="$emit('open', task)"
  >
    <div class="mk-card__top">
      <span class="mk-tag mk-tag--type" :style="{ '--tag': tagColor }">{{ tagLabel }}</span>
      <span v-if="task.tov" class="mk-tag mk-tag--tov" :title="task.tov">{{ task.tov }}</span>
      <span class="mk-card__spacer" />
      <iconify-icon v-if="task.is_approved" icon="lucide:badge-check" class="mk-card__state mk-card__state--ok" :title="`Approved by ${task.approved_by || ''}`" />
      <iconify-icon v-if="stageDone" icon="lucide:circle-check" class="mk-card__state mk-card__state--ok" title="Done" />
    </div>

    <h6 class="mk-card__title">{{ task.title }}</h6>

    <p v-if="task.category === 'content' && task.notes" class="mk-card__note">{{ task.notes }}</p>

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
      <div class="mk-card__people">
        <template v-if="task.category === 'production'">
          <div v-if="task.shooter || task.editor" class="mk-card__stack">
            <MkAvatar v-if="task.shooter" :user="task.shooter" :size="24" badge="lucide:camera" badge-color="#475569" />
            <MkAvatar v-if="task.editor" :user="task.editor" :size="24" badge="lucide:scissors" badge-color="#475569" />
          </div>
          <span class="mk-card__who">
            <template v-if="task.shooter && task.editor && task.shooter.id === task.editor.id">{{ firstName(task.shooter) }}</template>
            <template v-else-if="task.shooter || task.editor">
              {{ [task.shooter && firstName(task.shooter), task.editor && firstName(task.editor)].filter(Boolean).join(' · ') }}
            </template>
            <em v-else>Unassigned</em>
          </span>
        </template>
        <template v-else>
          <MkAvatar v-if="task.assignee" :user="task.assignee" :size="24" />
          <span class="mk-card__who">
            <template v-if="task.assignee">{{ firstName(task.assignee) }}</template>
            <em v-else>Unassigned</em>
          </span>
        </template>
      </div>

      <div class="mk-card__metas">
        <a
          v-if="task.links?.length"
          class="mk-card__meta mk-card__meta--link"
          :href="task.links[0].url"
          target="_blank"
          rel="noopener"
          :title="task.links.map((l) => l.label).join('\n')"
          @click.stop
        >
          <iconify-icon :icon="LINK_KINDS[task.links[0].kind]?.icon || 'lucide:link'" /> {{ task.links.length }}
        </a>
        <button v-else type="button" class="mk-card__meta mk-card__add-link" title="Add link" @click.stop="$emit('add-link', task)">
          <iconify-icon icon="lucide:link-2" />
        </button>
        <span v-if="task.posts_count" class="mk-card__meta" :title="`${task.posted_count}/${task.posts_count} posted`">
          <iconify-icon icon="lucide:send" /> {{ task.posted_count }}/{{ task.posts_count }}
        </span>
        <span v-if="task.comments_count" class="mk-card__meta">
          <iconify-icon icon="lucide:message-circle" /> {{ task.comments_count }}
        </span>
        <span v-if="task.due_date" class="mk-card__meta" :class="{ 'is-late': overdue }" :title="overdue ? 'Overdue' : 'Due date'">
          <iconify-icon icon="lucide:calendar" /> {{ formatDate(task.due_date) }}
        </span>
      </div>
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import MkAvatar from './shared/MkAvatar.vue'
import {
  MARKETING_CATEGORIES, CONTENT_TYPES, DELIVERABLES, CHANNELS, LINK_KINDS, formatDate, isoDate,
} from '@/config/marketingConfig.js'

const props = defineProps({
  task: { type: Object, required: true },
  stageDone: { type: Boolean, default: false },
})
defineEmits(['open', 'add-link'])

const category = computed(() => MARKETING_CATEGORIES[props.task.category] || MARKETING_CATEGORIES.production)
const type = computed(() => (props.task.category === 'production' ? CONTENT_TYPES[props.task.type] : null))
// The tag is the card's only color hint: a small colored icon on a neutral pill.
const tagLabel = computed(() => (type.value ? type.value.label : category.value.label))
const tagColor = computed(() => (type.value ? type.value.color : category.value.color))
const overdue = computed(() => !props.stageDone && props.task.due_date && props.task.due_date < isoDate(new Date()))

function firstName(user) {
  return String(user?.name || '').split(/\s+/)[0]
}
</script>

<style scoped>
.mk-card {
  position: relative;
  padding: 12px 12px 10px;
  border-radius: 12px;
  background: var(--white);
  border: 1px solid var(--border-color);
  box-shadow: 0 1px 2px rgba(15, 10, 30, 0.04);
  cursor: grab;
  outline: none;
  transition: box-shadow 0.18s, border-color 0.18s, transform 0.18s;
}
.mk-card:hover, .mk-card:focus-visible {
  border-color: color-mix(in srgb, var(--text-secondary-light) 35%, var(--border-color));
  box-shadow: 0 6px 18px -10px rgba(15, 10, 30, 0.3);
  transform: translateY(-1px);
}
.mk-card.is-done .mk-card__title { color: var(--text-secondary-light); }

/* Top row never grows past the card: tags shrink and truncate with "…". */
.mk-card__top { display: flex; align-items: center; gap: 6px; min-width: 0; }
.mk-card__spacer { flex: 1 1 0; min-width: 0; }
.mk-tag {
  display: inline-block;
  min-width: 0;
  max-width: 100%;
  padding: 2px 7px;
  border-radius: 5px;
  font-size: 10.5px !important;
  font-weight: 600 !important;
  line-height: 1.5;
  color: var(--text-secondary-light);
  background: var(--neutral-100);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
/* Type label: small uppercase text with a thin colored marker — no icon. */
.mk-tag--type {
  flex: 0 0 auto;
  position: relative;
  padding-left: 15px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-primary-light);
}
.mk-tag--type::before {
  content: '';
  position: absolute;
  left: 7px;
  top: 50%;
  width: 3px;
  height: 10px;
  margin-top: -5px;
  border-radius: 2px;
  background: var(--tag);
}
/* TOV / version can be long free text — let it shrink and truncate. */
.mk-tag--tov { flex: 0 1 auto; max-width: 140px; }
.mk-card__state { font-size: 16px !important; flex-shrink: 0; }
.mk-card__state--ok { color: #16a34a; }

.mk-card__title {
  margin: 8px 0 6px;
  font-size: 13px !important;
  font-weight: 600 !important;
  line-height: 1.4;
  color: var(--text-primary-light);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}
.mk-card__note {
  margin: 0 0 8px;
  font-size: 12px !important;
  color: var(--text-secondary-light);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.mk-card__chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px; }
.mk-chiplet {
  padding: 1px 7px;
  border-radius: 6px;
  font-size: 11px !important;
  font-weight: 500 !important;
  color: var(--text-secondary-light);
  border: 1px solid var(--border-color);
}

.mk-card__channels { display: flex; gap: 6px; margin-bottom: 8px; }
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
  gap: 8px;
  padding-top: 8px;
  border-top: 1px solid var(--border-color);
}
.mk-card__people { display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1; }
.mk-card__stack { display: flex; }
.mk-card__stack > * + * { margin-left: -6px; }
.mk-card__who {
  font-size: 12px !important;
  font-weight: 500 !important;
  color: var(--text-secondary-light);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mk-card__who em { font-style: normal; opacity: 0.7; }

.mk-card__metas { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.mk-card__meta {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11.5px !important;
  font-weight: 500 !important;
  color: var(--text-secondary-light);
  text-decoration: none;
  white-space: nowrap;
}
.mk-card__meta--link:hover { color: var(--color-primary); }
.mk-card__meta.is-late { color: #dc2626; font-weight: 600 !important; }
.mk-card__add-link {
  border: 0;
  background: transparent;
  padding: 0;
  font-size: 13px !important;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s;
}
.mk-card:hover .mk-card__add-link, .mk-card:focus-within .mk-card__add-link { opacity: 1; }
.mk-card__add-link:hover { color: var(--color-primary); }
@media (hover: none) { .mk-card__add-link { opacity: 1; } }

@media (prefers-reduced-motion: reduce) {
  .mk-card { transition: none; }
  .mk-card:hover { transform: none; }
}
</style>
