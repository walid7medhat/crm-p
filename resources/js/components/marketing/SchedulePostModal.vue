<template>
  <MkModal
    :model-value="modelValue"
    title="Schedule post"
    :subtitle="task ? task.title : 'Add something to the posting calendar'"
    icon="lucide:calendar-plus"
    accent="#9333ea"
    width="560px"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <form id="mk-schedule" @submit.prevent="save">
      <div v-if="task?.links?.length" class="mk-field">
        <span class="mk-label">Which file?</span>
        <div class="mk-files">
          <button type="button" class="mk-file" :class="{ active: !form.link_id }" @click="form.link_id = null">
            <iconify-icon icon="lucide:layers" /> Whole card
          </button>
          <button
            v-for="l in task.links"
            :key="l.id"
            type="button"
            class="mk-file"
            :class="{ active: form.link_id === l.id }"
            @click="form.link_id = l.id"
          >
            <iconify-icon :icon="LINK_KINDS[l.kind]?.icon || 'lucide:link'" :style="{ color: LINK_KINDS[l.kind]?.color }" /> {{ l.label }}
          </button>
        </div>
      </div>

      <div class="mk-field">
        <label for="mk-sch-title">Title</label>
        <input id="mk-sch-title" v-model.trim="form.title" class="mk-input" :placeholder="defaultTitle || 'What is being posted?'" />
      </div>

      <div class="mk-field">
        <label for="mk-sch-date">Date</label>
        <AdvancedDatePicker
          id="mk-sch-date"
          v-model="form.scheduled_date"
          class="mk-date"
          date-only
          dob-layout
          :block-future-dates="false"
          placeholder="Select post date"
          :invalid="!form.scheduled_date && !!error"
        />
      </div>
      <div class="mk-field">
        <span class="mk-label">Link <small>(optional)</small></span>
        <div class="mk-row">
          <input v-model.trim="form.link_url" type="url" class="mk-input" :class="{ 'mk-input--error': linkError }" placeholder="https://drive.google.com/…" />
          <input v-model.trim="form.link_label" class="mk-input" placeholder="Label (e.g. T.1.png)" />
        </div>
        <span v-if="linkError" class="mk-error">{{ linkError }}</span>
      </div>

      <div class="mk-field">
        <span class="mk-label">Platforms</span>
        <div class="mk-chips">
          <button
            v-for="(p, key) in PLATFORMS"
            :key="key"
            type="button"
            class="mk-chip"
            :class="{ 'is-on': form.platforms.includes(key) }"
            :style="chipStyle(p)"
            @click="togglePlatform(key)"
          ><iconify-icon :icon="p.icon" /> {{ p.label }}</button>
        </div>
        <span v-if="error" class="mk-error">{{ error }}</span>
      </div>
    </form>
    <template #footer>
      <button type="button" class="mk-btn mk-btn--ghost" @click="$emit('update:modelValue', false)">Cancel</button>
      <button type="submit" form="mk-schedule" class="mk-btn mk-btn--primary" :disabled="saving">
        <iconify-icon :icon="saving ? 'lucide:loader-2' : 'lucide:calendar-check'" :class="{ 'mk-spin': saving }" /> Schedule
      </button>
    </template>
  </MkModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkModal from './shared/MkModal.vue'
import { showCrmToast } from '@/utils/crmToast.js'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import { PLATFORMS, LINK_KINDS, isoDate, apiError, chipStyle } from '@/config/marketingConfig.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /** Optional: scheduling from a card. Without it, a free calendar entry is created. */
  task: { type: Object, default: null },
  date: { type: String, default: '' },
  platform: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const saving = ref(false)
const error = ref('')
const form = reactive({ link_id: null, title: '', scheduled_date: '', platforms: [] })

const defaultTitle = computed(() => {
  const link = props.task?.links?.find((l) => l.id === form.link_id)
  return link?.label || props.task?.title || ''
})

watch(() => props.modelValue, (open) => {
  if (!open) return
  error.value = ''
  Object.assign(form, {
    link_id: null,
    title: '',
    scheduled_date: props.date || isoDate(new Date()),
    platforms: props.platform ? [props.platform] : [],
  })
})

function togglePlatform(key) {
  const i = form.platforms.indexOf(key)
  if (i === -1) form.platforms.push(key)
  else form.platforms.splice(i, 1)
}

async function save() {
  error.value = form.platforms.length ? '' : 'Pick at least one platform.'
  if (!props.task && !form.title) error.value = error.value || 'Add a title.'
  if (error.value) return
  saving.value = true
  try {
    const { data } = await api.post('/marketing/calendar', {
      task_id: props.task?.id ?? null,
      link_id: form.link_id,
      link_url: form.link_url || null,
      link_label: form.link_label || null,
      title: form.title || null,
      platforms: form.platforms,
      scheduled_date: form.scheduled_date,
    })
    emit('saved', data.posts)
    emit('update:modelValue', false)
    showCrmToast({
      kind: 'scheduled',
      headline: 'Added to calendar',
      subtitle: data.posts[0]?.title || props.task?.title || '',
      chip: `${data.posts.length} post${data.posts.length === 1 ? '' : 's'}`,
    })
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not schedule', text: apiError(err) })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.mk-files { display: flex; flex-wrap: wrap; gap: 8px; }
.mk-file {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
  padding: 7px 12px;
  border-radius: 10px;
  border: 1.5px solid var(--border-color);
  background: var(--white);
  color: var(--text-primary-light);
  font-size: 13px !important;
  font-weight: 600 !important;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mk-file.active { border-color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); color: var(--color-primary); }
</style>
