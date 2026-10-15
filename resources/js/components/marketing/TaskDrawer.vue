<template>
  <Teleport to="body">
    <Transition name="mk-drawer">
      <div v-if="modelValue" class="mk-drawer-modal-overlay" @mousedown.self="close">
        <aside class="mk-drawer" :style="{ '--accent': accent }" role="dialog" aria-modal="true">
          <div v-if="loading && !task" class="mk-drawer__loading">
            <iconify-icon icon="lucide:loader-2" class="mk-spin" />
          </div>

          <template v-else-if="task">
            <header class="mk-drawer__head">
              <div class="mk-drawer__head-top">
                <span v-if="task.category === 'production' && type" class="mk-pill mk-pill--solid" :style="{ '--pill': type.color }">
                  <iconify-icon :icon="type.icon" /> {{ type.label.toUpperCase() }}
                </span>
                <span v-else class="mk-pill mk-pill--solid" :style="{ '--pill': cat.color }">
                  <iconify-icon :icon="cat.icon" /> {{ cat.label }}
                </span>
                <span v-if="task.tov" class="mk-pill mk-drawer__tov" :style="{ '--pill': '#64748b' }" :title="task.tov">{{ task.tov }}</span>
                <span class="mk-drawer__spacer" />
                <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" title="Edit" @click="$emit('edit', task)">
                  <iconify-icon icon="lucide:pencil" />
                </button>
                <button v-if="canManage" type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon mk-drawer__del" title="Delete card" @click="remove">
                  <iconify-icon icon="lucide:trash-2" />
                </button>
                <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" title="Close" @click="close">
                  <iconify-icon icon="lucide:x" />
                </button>
              </div>
              <h4 class="mk-drawer__title">{{ task.title }}</h4>
              <div class="mk-drawer__actions">
                <MkSelect
                  :model-value="task.stage_id"
                  :options="stages.map((s) => ({ value: s.id, label: s.name, dot: s.color }))"
                  :disabled="busy"
                  aria-label="Stage"
                  @change="changeStage"
                />
                <button
                  v-if="task.category === 'design'"
                  type="button"
                  class="mk-btn mk-btn--sm"
                  :class="task.is_approved ? 'mk-btn--success' : 'mk-btn--ghost'"
                  :disabled="busy"
                  @click="toggleApprove"
                >
                  <iconify-icon :icon="task.is_approved ? 'lucide:badge-check' : 'lucide:badge'" />
                  {{ task.is_approved ? 'Approved' : 'Approve' }}
                </button>
                <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm" @click="$emit('schedule', task)">
                  <iconify-icon icon="lucide:calendar-plus" /> Schedule post
                </button>
              </div>
            </header>

            <nav class="mk-drawer__tabs">
              <button v-for="t in tabs" :key="t.id" type="button" :class="{ active: tab === t.id }" @click="tab = t.id">
                <iconify-icon :icon="t.icon" /> {{ t.label }}
                <span v-if="t.count" class="mk-drawer__tab-count">{{ t.count }}</span>
              </button>
            </nav>

            <div class="mk-drawer__body">
              <Transition name="mk-tab" mode="out-in">
              <section v-if="tab === 'details'" class="mk-details">
                <div v-if="task.category === 'production'" class="mk-details__people">
                  <div class="mk-person">
                    <MkAvatar :user="task.shooter" :size="40" badge="lucide:camera" badge-color="#2563eb" />
                    <div><small>Shooting by</small><b>{{ task.shooter?.name || '—' }}</b></div>
                  </div>
                  <div class="mk-person">
                    <MkAvatar :user="task.editor" :size="40" badge="lucide:scissors" badge-color="#db2777" />
                    <div><small>Editing by</small><b>{{ task.editor?.name || '—' }}</b></div>
                  </div>
                </div>
                <div v-else class="mk-details__people">
                  <div class="mk-person">
                    <MkAvatar :user="task.assignee" :size="40" />
                    <div><small>{{ task.category === 'design' ? 'Designer' : 'Assigned to' }}</small><b>{{ task.assignee?.name || '—' }}</b></div>
                  </div>
                </div>

                <div v-if="task.category === 'production'" class="mk-details__block">
                  <span class="mk-label">Channels</span>
                  <div class="mk-chips">
                    <span
                      v-for="(ch, key) in CHANNELS"
                      :key="key"
                      class="mk-chip"
                      :class="{ 'is-on': task.channels?.[key] }"
                      :style="{ ...chipStyle(ch), cursor: 'default' }"
                    ><iconify-icon :icon="ch.icon" /> {{ ch.label }}</span>
                  </div>
                </div>

                <div v-if="task.category === 'design'" class="mk-details__block">
                  <span class="mk-label">Deliverables</span>
                  <div class="mk-chips">
                    <span v-for="d in task.deliverables" :key="d" class="mk-pill" :style="{ '--pill': DELIVERABLES[d]?.color }">{{ DELIVERABLES[d]?.label || d }}</span>
                    <span v-if="!task.deliverables?.length" class="mk-muted">None selected</span>
                  </div>
                </div>

                <dl class="mk-details__grid">
                  <div><dt>Due date</dt><dd>{{ task.due_date ? formatDate(task.due_date, { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }}</dd></div>
                  <div v-if="task.department"><dt>Department</dt><dd>{{ DEPARTMENTS[task.department]?.label || task.department }}</dd></div>
                  <div><dt>Completed</dt><dd>{{ task.completed_at ? formatDate(task.completed_at, { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }}</dd></div>
                  <div><dt>Created by</dt><dd>{{ task.created_by || '—' }}</dd></div>
                  <div><dt>Created</dt><dd>{{ timeAgo(task.created_at) }}</dd></div>
                  <div v-if="task.is_approved"><dt>Approved by</dt><dd>{{ task.approved_by }} · {{ timeAgo(task.approved_at) }}</dd></div>
                </dl>

                <div v-if="task.reference" class="mk-details__block">
                  <span class="mk-label">Reference</span>
                  <p class="mk-details__text" v-html="linkify(task.reference)" />
                </div>
                <div v-if="task.notes" class="mk-details__block">
                  <span class="mk-label">{{ task.category === 'content' ? 'Note / tag' : 'Notes' }}</span>
                  <p class="mk-details__text">{{ task.notes }}</p>
                </div>

                <div v-if="task.posts?.length" class="mk-details__block">
                  <span class="mk-label">Scheduled posts</span>
                  <ul class="mk-posts">
                    <li v-for="p in task.posts" :key="p.id" :class="{ done: p.is_posted }">
                      <iconify-icon :icon="PLATFORMS[p.platform]?.icon" :style="{ color: PLATFORMS[p.platform]?.color }" />
                      <span>{{ PLATFORMS[p.platform]?.label }}</span>
                      <small>{{ formatDate(p.scheduled_date, { weekday: 'short', day: '2-digit', month: 'short' }) }}</small>
                      <iconify-icon :icon="p.is_posted ? 'lucide:check-circle-2' : 'lucide:clock'" class="mk-posts__state" />
                    </li>
                  </ul>
                </div>
              </section>

              <section v-else-if="tab === 'links'">
                <form class="mk-addlink" autocomplete="off" @submit.prevent="addLink">
                  <input v-model.trim="newLink.url" type="url" class="mk-input" placeholder="Paste Drive / file / Sheet link" required />
                  <input v-model.trim="newLink.label" class="mk-input" placeholder="Label (optional)" />
                  <button type="submit" class="mk-btn mk-btn--primary" :disabled="busy || !newLink.url">
                    <iconify-icon icon="lucide:plus" /> Add
                  </button>
                </form>
                <ul v-if="task.links?.length" class="mk-links">
                  <li v-for="l in task.links" :key="l.id">
                    <span class="mk-links__icon" :style="{ '--k': LINK_KINDS[l.kind]?.color }"><iconify-icon :icon="LINK_KINDS[l.kind]?.icon || 'lucide:link'" /></span>
                    <a :href="l.url" target="_blank" rel="noopener">
                      <b>{{ l.label }}</b>
                      <small>{{ LINK_KINDS[l.kind]?.label }} · {{ timeAgo(l.created_at) }}</small>
                    </a>
                    <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" title="Copy link" @click="copy(l.url)"><iconify-icon icon="lucide:copy" /></button>
                    <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon mk-drawer__del" title="Remove" @click="removeLink(l)"><iconify-icon icon="lucide:trash-2" /></button>
                  </li>
                </ul>
                <div v-else class="mk-empty"><iconify-icon icon="lucide:link-2-off" />No links yet — add WIP folders, drafts or finals any time.</div>
              </section>

              <section v-else-if="tab === 'comments'" class="mk-comments">
                <form class="mk-comments__composer" @submit.prevent="addComment">
                  <textarea v-model="newComment" class="mk-input" rows="2" placeholder="Write a comment…" @keydown.ctrl.enter="addComment" @keydown.meta.enter="addComment" />
                  <button type="submit" class="mk-btn mk-btn--primary" :disabled="busy || !newComment.trim()">
                    <iconify-icon icon="lucide:send" />
                  </button>
                </form>
                <ul v-if="task.comments?.length">
                  <li v-for="(c, i) in task.comments" :key="c.id" class="mk-rise" :style="{ '--i': Math.min(i, 10) }">
                    <MkAvatar :user="c.user" :size="34" />
                    <div class="mk-comments__bubble">
                      <header><b>{{ c.user?.name }}</b><small>{{ timeAgo(c.created_at) }}</small></header>
                      <p>{{ c.body }}</p>
                    </div>
                  </li>
                </ul>
                <div v-else class="mk-empty"><iconify-icon icon="lucide:message-circle" />No comments yet.</div>
              </section>

              <section v-else-if="tab === 'history'">
                <ol class="mk-timeline">
                  <li v-for="(h, i) in task.histories" :key="h.id" class="mk-rise" :style="{ '--h': HISTORY_ACTIONS[h.action]?.color || '#64748b', '--i': Math.min(i, 12) }">
                    <span class="mk-timeline__dot"><iconify-icon :icon="HISTORY_ACTIONS[h.action]?.icon || 'lucide:dot'" /></span>
                    <div class="mk-timeline__body">
                      <p><b>{{ h.user?.name || 'System' }}</b> {{ HISTORY_ACTIONS[h.action]?.label || h.action }}</p>
                      <ul v-if="h.changes?.length && h.action !== 'commented'" class="mk-timeline__changes">
                        <li v-for="(c, i) in h.changes" :key="i">
                          <span class="mk-timeline__field">{{ c.label }}</span>
                          <template v-if="c.old || h.action === 'updated' || h.action === 'moved'">
                            <s v-if="c.old">{{ c.old }}</s><em v-else>empty</em>
                            <iconify-icon icon="lucide:arrow-right" />
                          </template>
                          <strong v-if="c.new">{{ c.new }}</strong><em v-else>empty</em>
                        </li>
                      </ul>
                      <p v-else-if="h.action === 'commented' && h.changes?.[0]" class="mk-timeline__quote">“{{ h.changes[0].new }}”</p>
                      <small>{{ timeAgo(h.created_at) }}</small>
                    </div>
                  </li>
                </ol>
              </section>
              </Transition>
            </div>
          </template>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkAvatar from './shared/MkAvatar.vue'
import MkSelect from './shared/MkSelect.vue'
import { showCrmToast } from '@/utils/crmToast.js'
import {
  MARKETING_CATEGORIES, CONTENT_TYPES, DELIVERABLES, CHANNELS, PLATFORMS, LINK_KINDS, HISTORY_ACTIONS,
  formatDate, timeAgo, apiError, chipStyle,DEPARTMENTS
} from '@/config/marketingConfig.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  taskId: { type: [Number, null], default: null },
  stages: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  initialTab: { type: String, default: 'details' },
})
const emit = defineEmits(['update:modelValue', 'updated', 'deleted', 'moved', 'edit', 'schedule'])

const task = ref(null)
const loading = ref(false)
const busy = ref(false)
const tab = ref('details')
const newLink = reactive({ url: '', label: '' })
const newComment = ref('')

const cat = computed(() => MARKETING_CATEGORIES[task.value?.category] || MARKETING_CATEGORIES.production)
const type = computed(() => CONTENT_TYPES[task.value?.type])
const accent = computed(() => (task.value?.category === 'production' && type.value ? type.value.color : cat.value.color))
const tabs = computed(() => [
  { id: 'details', label: 'Details', icon: 'lucide:info' },
  { id: 'links', label: 'Links', icon: 'lucide:link', count: task.value?.links?.length },
  { id: 'comments', label: 'Comments', icon: 'lucide:message-circle', count: task.value?.comments?.length },
  { id: 'history', label: 'History', icon: 'lucide:history' },
])

/** Same rich top-right toast as lead / deal pop-ups: headline + card title + board chip. */
const toast = (headline, kind = 'updated', subtitle = null) => showCrmToast({
  kind,
  headline,
  subtitle: subtitle ?? task.value?.title ?? '',
  chip: cat.value.label,
})

async function load() {
  if (!props.taskId) return
  loading.value = true
  try {
    const { data } = await api.get(`/marketing/tasks/${props.taskId}`)
    task.value = data.task
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not open card', text: apiError(err) })
    close()
  } finally {
    loading.value = false
  }
}

/** Reload the drawer and tell the board so the card face (counts, links) stays in sync. */
async function refresh() {
  await load()
  if (task.value) emit('updated', task.value)
}

watch(() => [props.modelValue, props.taskId], ([open, id], [wasOpen, oldId] = []) => {
  if (open && (!wasOpen || id !== oldId)) {
    task.value = null
    tab.value = props.initialTab
    newLink.url = ''
    newLink.label = ''
    newComment.value = ''
    load()
  }
})

function close() {
  emit('update:modelValue', false)
}

function onKey(e) {
  if (e.key === 'Escape' && props.modelValue && !document.querySelector('.mk-modal')) close()
}
window.addEventListener('keydown', onKey)
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

async function changeStage(stageId) {
  const id = Number(stageId)
  if (!task.value || id === task.value.stage_id) return
  busy.value = true
  try {
    const { data } = await api.post(`/marketing/tasks/${task.value.id}/move`, { stage_id: id, position: 0 })
    emit('moved', { task: data.task, suggestLink: data.suggest_link })
    await load()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not move card', text: apiError(err) })
  } finally {
    busy.value = false
  }
}

async function toggleApprove() {
  busy.value = true
  try {
    await api.post(`/marketing/tasks/${task.value.id}/approve`, { approved: !task.value.is_approved })
    toast(task.value.is_approved ? 'Approval removed' : 'Design approved', task.value.is_approved ? 'updated' : 'approved')
    await refresh()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not update approval', text: apiError(err) })
  } finally {
    busy.value = false
  }
}

async function addLink() {
  if (!newLink.url) return
  busy.value = true
  try {
    await api.post(`/marketing/tasks/${task.value.id}/links`, { url: newLink.url, label: newLink.label || null })
    newLink.url = ''
    newLink.label = ''
    toast('Link added', 'link')
    await refresh()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not add link', text: apiError(err) })
  } finally {
    busy.value = false
  }
}

async function removeLink(link) {
  const res = await Swal.fire({ icon: 'warning', title: 'Remove this link?', text: link.label, showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#dc2626' })
  if (!res.isConfirmed) return
  busy.value = true
  try {
    await api.delete(`/marketing/tasks/${task.value.id}/links/${link.id}`)
    await refresh()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not remove link', text: apiError(err) })
  } finally {
    busy.value = false
  }
}

async function addComment() {
  const body = newComment.value.trim()
  if (!body || busy.value) return
  busy.value = true
  try {
    await api.post(`/marketing/tasks/${task.value.id}/comments`, { body })
    newComment.value = ''
    await refresh()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not post comment', text: apiError(err) })
  } finally {
    busy.value = false
  }
}

async function remove() {
  const res = await Swal.fire({
    icon: 'warning',
    title: 'Delete this card?',
    text: `"${task.value.title}" will be removed from the board.`,
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  })
  if (!res.isConfirmed) return
  try {
    await api.delete(`/marketing/tasks/${task.value.id}`)
    emit('deleted', task.value.id)
    toast('Card deleted', 'deleted')
    close()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not delete', text: apiError(err) })
  }
}

async function copy(url) {
  try {
    await navigator.clipboard.writeText(url)
    toast('Link copied', 'copied', url)
  } catch {
    /* clipboard unavailable */
  }
}

function linkify(text) {
  const esc = String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
  return esc.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
}

defineExpose({ refresh, openTab: (t) => { tab.value = t } })
</script>

<style scoped>
.mk-drawer-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2040;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  background: rgba(28, 20, 48, 0.28);
  backdrop-filter: blur(3px);
}
.mk-drawer {
  --accent: var(--color-primary);
  width: min(460px, 100%);
  max-height: min(78vh, 640px);
  display: flex;
  flex-direction: column;
  background: #fff;
  color: #241c30;
  border: 1px solid #eceaf2;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 28px 70px -24px rgba(28, 20, 48, 0.45);
}
.mk-drawer__loading { flex: 1; display: grid; place-items: center; font-size: 30px !important; color: var(--color-primary); }

/* Header in the CRM theme color, matching the pop-up headers. */
.mk-drawer__head {
  padding: 16px 16px 12px;
  color: #241c30;
  background: #fff;
  border-bottom: 1px solid #f0eef4;
}
.mk-drawer__head .mk-drawer__title { color: #241c30; }
.mk-drawer__head-top .mk-btn--ghost {
  color: #6f6880;
  background: #fff;
  border-color: #eceaf2;
}
.mk-drawer__head-top .mk-btn--ghost:not(:disabled):hover { color: var(--color-primary); border-color: #ddd6ea; background: #faf8fc; }
.mk-drawer__head-top .mk-drawer__del:hover { color: #fff !important; background: #dc2626 !important; border-color: #dc2626 !important; }
.mk-drawer__head-top { display: flex; align-items: center; gap: 6px; min-width: 0; }
.mk-drawer__tov { display: inline-block; min-width: 0; max-width: 220px; overflow: hidden; text-overflow: ellipsis; flex: 0 1 auto; }
.mk-drawer__spacer { flex: 1; }
.mk-drawer__del:hover { color: #dc2626 !important; border-color: #dc2626 !important; }
.mk-drawer__title { margin: 10px 0 12px; font-size: 18px !important; font-weight: 650 !important; line-height: 1.3; word-break: break-word; }
.mk-drawer__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }

.mk-drawer__tabs {
  display: flex;
  gap: 4px;
  padding: 0 16px;
  border-bottom: 1px solid var(--border-color);
  overflow-x: auto;
}
.mk-drawer__tabs button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 11px 10px;
  border: 0;
  border-bottom: 2.5px solid transparent;
  background: transparent;
  font-size: 13.5px !important;
  font-weight: 600 !important;
  color: var(--text-secondary-light);
  white-space: nowrap;
}
.mk-drawer__tabs button.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }
.mk-drawer__tab-count {
  min-width: 18px;
  padding: 0 6px;
  border-radius: 999px;
  font-size: 11px !important;
  line-height: 18px;
  color: #fff;
  background: var(--color-primary);
}
.mk-drawer__body { flex: 1; overflow-y: auto; padding: 20px 22px 28px; }
.mk-muted { font-size: 13px !important; color: var(--text-secondary-light); }

/* Details */
.mk-details__people { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 18px; }
.mk-person {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 14px;
  background: #faf8fc;
  border: 1px solid #f0eef4;
}
.mk-person small { display: block; font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary-light); }
.mk-person b { font-size: 14px !important; }
.mk-details__block { margin-bottom: 18px; }
.mk-details__block .mk-label { display: block; margin-bottom: 8px; }
.mk-details__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
  margin: 0 0 18px;
  padding: 14px;
  border-radius: 14px;
  background: var(--neutral-50);
}
.mk-details__grid dt { font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary-light); }
.mk-details__grid dd { margin: 2px 0 0; font-size: 14px !important; font-weight: 600 !important; }
.mk-details__text { margin: 0; font-size: 14px !important; line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
.mk-details__text :deep(a) { color: var(--color-primary); }
.mk-posts { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.mk-posts li { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 10px; background: var(--neutral-50); font-size: 13px !important; font-weight: 600 !important; }
.mk-posts small { margin-left: auto; color: var(--text-secondary-light); }
.mk-posts__state { color: var(--text-secondary-light); }
.mk-posts li.done .mk-posts__state { color: #16a34a; }

/* Links */
.mk-addlink { display: grid; grid-template-columns: 1.6fr 1fr auto; gap: 8px; margin-bottom: 16px; }
@media (max-width: 576px) { .mk-addlink { grid-template-columns: 1fr; } }
.mk-links { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.mk-links li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 14px;
  border: 1px solid var(--border-color);
  transition: border-color 0.15s;
}
.mk-links li:hover { border-color: var(--color-primary); }
.mk-links__icon {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  display: grid;
  place-items: center;
  font-size: 20px !important;
  color: var(--k);
  background: color-mix(in srgb, var(--k) 12%, transparent);
  flex-shrink: 0;
}
.mk-links a { flex: 1; min-width: 0; text-decoration: none; color: var(--text-primary-light); }
.mk-links a b { display: block; font-size: 14px !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mk-links a small { font-size: 12px !important; color: var(--text-secondary-light); }
.mk-links a:hover b { color: var(--color-primary); }

/* Comments */
.mk-comments__composer { display: flex; gap: 8px; align-items: flex-end; margin-bottom: 18px; }
.mk-comments__composer textarea { min-height: 46px; }
.mk-comments ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.mk-comments li { display: flex; gap: 10px; }
.mk-comments__bubble { flex: 1; padding: 10px 13px; border-radius: 4px 14px 14px 14px; background: var(--neutral-50); border: 1px solid var(--border-color); }
.mk-comments__bubble header { display: flex; align-items: baseline; gap: 8px; margin-bottom: 3px; }
.mk-comments__bubble header b { font-size: 13px !important; }
.mk-comments__bubble header small { font-size: 11.5px !important; color: var(--text-secondary-light); }
.mk-comments__bubble p { margin: 0; font-size: 14px !important; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }

/* History timeline */
.mk-timeline { list-style: none; margin: 0; padding: 0; position: relative; }
.mk-timeline::before { content: ''; position: absolute; left: 15px; top: 6px; bottom: 6px; width: 2px; background: var(--border-color); }
.mk-timeline > li { position: relative; display: flex; gap: 12px; padding-bottom: 18px; }
.mk-timeline__dot {
  position: relative;
  z-index: 1;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 15px !important;
  color: var(--h);
  background: color-mix(in srgb, var(--h) 14%, var(--white));
  box-shadow: 0 0 0 3px var(--white);
  flex-shrink: 0;
}
.mk-timeline__body { flex: 1; min-width: 0; padding-top: 5px; }
.mk-timeline__body > p { margin: 0; font-size: 14px !important; }
.mk-timeline__body > small { font-size: 12px !important; color: var(--text-secondary-light); }
.mk-timeline__changes { list-style: none; margin: 6px 0; padding: 8px 10px; border-radius: 10px; background: var(--neutral-50); display: flex; flex-direction: column; gap: 4px; }
.mk-timeline__changes li { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px !important; }
.mk-timeline__field { font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary-light); min-width: 78px; }
.mk-timeline__changes s { color: var(--text-secondary-light); }
.mk-timeline__changes em { color: var(--text-secondary-light); font-size: 12px !important; }
.mk-timeline__changes strong { color: var(--h); }
.mk-timeline__quote { margin: 4px 0 !important; font-style: italic; color: var(--text-secondary-light); }

/* Tab switch fade + list items rising in one after another */
.mk-tab-enter-active, .mk-tab-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.mk-tab-enter-from { opacity: 0; transform: translateY(8px); }
.mk-tab-leave-to { opacity: 0; }
.mk-rise { animation: mk-rise 0.38s cubic-bezier(.2,.8,.2,1) both; animation-delay: calc(var(--i, 0) * 45ms); }
@keyframes mk-rise {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: none; }
}
.mk-timeline__dot { transition: transform 0.2s; }
.mk-timeline > li:hover .mk-timeline__dot { transform: scale(1.12); }

.mk-drawer-enter-active, .mk-drawer-leave-active { transition: opacity 0.2s ease; }
.mk-drawer-enter-active .mk-drawer, .mk-drawer-leave-active .mk-drawer { transition: transform 0.28s cubic-bezier(.2,.8,.2,1); }
.mk-drawer-enter-from, .mk-drawer-leave-to { opacity: 0; }
.mk-drawer-enter-from .mk-drawer, .mk-drawer-leave-to .mk-drawer { transform: translateY(12px) scale(0.98); }
@media (prefers-reduced-motion: reduce) {
  .mk-drawer-enter-active .mk-drawer, .mk-drawer-leave-active .mk-drawer { transition: none; }
  .mk-tab-enter-active, .mk-tab-leave-active { transition: none; }
  .mk-rise { animation: none; }
}
</style>
