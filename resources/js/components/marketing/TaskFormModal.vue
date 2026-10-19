<template>
  <MkModal
    :model-value="modelValue"
    :title="isEdit ? 'Edit card' : `New ${cat.label.toLowerCase()} card`"
    :subtitle="cat.hint"
    :icon="cat.icon"
    :accent="cat.color"
    width="620px"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <form id="mk-task-form" autocomplete="off" @submit.prevent="submit">
      <div class="mk-field">
        <label for="mk-title">Title</label>
        <input
          id="mk-title"
          ref="titleInput"
          v-model.trim="form.title"
          class="mk-input"
          :class="{ 'mk-input--error': errors.title }"
          maxlength="255"
          :placeholder="titlePlaceholder"
        />
        <span v-if="errors.title" class="mk-error">{{ errors.title }}</span>

      </div>
        <div class="mk-field">
          <span class="mk-label">Department</span>
          <div class="mk-chips">
            <button
              v-for="(d, key) in DEPARTMENTS"
              :key="key"
              type="button"
              class="mk-chip"
              :class="{ 'is-on': form.department === key }"
              :style="chipStyle(d)"
              @click="form.department = key; errors.department = ''"
            ><iconify-icon :icon="d.icon" /> {{ d.label }}</button>
          </div>
          <span v-if="errors.department" class="mk-error">{{ errors.department }}</span>
        </div>
      <!-- PRODUCTION -->
      <template v-if="category === 'production'">
   
        <div class="mk-field">
          <span class="mk-label">Content type</span>
          <div class="mk-chips">
            <button
              v-for="(t, key) in CONTENT_TYPES"
              :key="key"
              type="button"
              class="mk-chip"
              :class="{ 'is-on': form.type === key }"
              :style="chipStyle(t)"
              @click="form.type = key"
            ><iconify-icon :icon="t.icon" /> {{ t.label }}</button>
          </div>
        </div>
        <div class="mk-row">
          <div class="mk-field">
            <span class="mk-label">Shooting by</span>
            <MemberPicker v-model="form.shooting_by" :members="members" :fallback="task?.shooter" icon="lucide:camera" placeholder="Who shoots?" />
          </div>
          <div class="mk-field">
            <span class="mk-label">Editing by</span>
            <MemberPicker v-model="form.editing_by" :members="members" :fallback="task?.editor" icon="lucide:scissors" placeholder="Who edits?" />
          </div>
        </div>
        <div class="mk-field">
          <span class="mk-label">Distribution channels</span>
          <div class="mk-chips">
            <button
              v-for="(ch, key) in CHANNELS"
              :key="key"
              type="button"
              class="mk-chip"
              :class="{ 'is-on': form.channels[key] }"
              :style="chipStyle(ch)"
              @click="form.channels[key] = !form.channels[key]"
            ><iconify-icon :icon="ch.icon" /> {{ ch.label }}</button>
          </div>
        </div>
      </template>

      <!-- DESIGN / CONTENT -->
      <template v-else>
        <div class="mk-row">
          <div class="mk-field">
            <span class="mk-label">{{ category === 'design' ? 'Designer' : 'Assigned to' }}</span>
            <MemberPicker v-model="form.assigned_to" :members="members" :fallback="task?.assignee" />
          </div>
          <div v-if="category === 'design'" class="mk-field">
            <label for="mk-tov">TOV / version</label>
            <input id="mk-tov" v-model.trim="form.tov" class="mk-input" maxlength="50" placeholder="e.g. TOV 1, V.2" />
          </div>
          <div v-if="category === 'content'" class="mk-field">
            <span class="mk-label">Designer</span>
            <MemberPicker
              v-model="form.designed_by"
              :members="members"
              :fallback="task?.designer"
              icon="lucide:palette"
              placeholder="Also on Design"
            />
          </div>
        </div>
        <div v-if="category === 'content'" class="mk-field">
          <span class="mk-label">Video</span>
          <div class="mk-chips">
            <button
              type="button"
              class="mk-chip"
              :class="{ 'is-on': form.also_video }"
              :style="chipStyle(CONTENT_TYPES.video)"
              @click="form.also_video = !form.also_video"
            ><iconify-icon icon="lucide:video" /> Video</button>
          </div>
          <span class="mk-hint">A designer also puts this card on Design, in the first stage. Video also puts it on Production. The card stays on Content.</span>
        </div>
        <div v-if="category === 'design'" class="mk-field">
          <span class="mk-label">Deliverables</span>
          <div class="mk-chips">
            <button
              v-for="(d, key) in DELIVERABLES"
              :key="key"
              type="button"
              class="mk-chip"
              :class="{ 'is-on': form.deliverables.includes(key) }"
              :style="chipStyle(d)"
              @click="toggleDeliverable(key)"
            >{{ d.label }}</button>
          </div>
        </div>
      </template>

      <div class="mk-row">
        <div class="mk-field">
          <label for="mk-due">Due date</label>
          <AdvancedDatePicker
            id="mk-due"
            v-model="form.due_date"
            class="mk-date"
            date-only
            dob-layout
            :block-future-dates="false"
            placeholder="Select due date"
          />
        </div>
        <div v-if="!isEdit && stages.length" class="mk-field">
          <span class="mk-label">Stage</span>
          <MkSelect v-model="form.stage_id" :options="stages.map((s) => ({ value: s.id, label: s.name, dot: s.color }))" block aria-label="Stage" />
        </div>
      </div>

      <div class="mk-field">
        <label for="mk-ref">Reference</label>
        <input id="mk-ref" v-model.trim="form.reference" class="mk-input" placeholder="Brief, reference link or inspiration" />
        <div v-if="referencePreview" class="mk-ref">
          <img :src="referencePreview" alt="Reference image" />
        </div>
        <div class="mk-ref__actions">
          <label class="mk-btn mk-btn--ghost mk-btn--sm">
            <iconify-icon icon="lucide:image-plus" />
            {{ referencePreview ? 'Change image' : 'Upload image' }}
            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden @change="onReferenceImage" />
          </label>
          <button v-if="referencePreview" type="button" class="mk-btn mk-btn--ghost mk-btn--sm" @click="clearReferenceImage">Remove image</button>
        </div>
        <span v-if="errors.reference" class="mk-error">{{ errors.reference }}</span>
      </div>

      <div class="mk-field">
        <label for="mk-notes">{{ category === 'content' ? 'Note / tag' : 'Notes' }}</label>
        <textarea id="mk-notes" v-model="form.notes" class="mk-input" rows="3" :placeholder="category === 'content' ? 'e.g. Eid, Arafah…' : 'Anything the team should know'" />
      </div>

      <div v-if="!isEdit" class="mk-field">
        <span class="mk-label">Link <small>(optional — you can add more any time)</small></span>
        <div class="mk-row">
          <input v-model.trim="form.link_url" type="url" class="mk-input" :class="{ 'mk-input--error': errors.link }" placeholder="https://drive.google.com/…" />
          <input v-model.trim="form.link_label" class="mk-input" placeholder="Label (e.g. T.1.png)" />
        </div>
        <span v-if="errors.link" class="mk-error">{{ errors.link }}</span>
      </div>
    </form>

    <template #footer>
      <button type="button" class="mk-btn mk-btn--ghost" @click="$emit('update:modelValue', false)">Cancel</button>
      <button type="submit" form="mk-task-form" class="mk-btn mk-btn--primary" :disabled="saving">
        <iconify-icon :icon="saving ? 'lucide:loader-2' : (isEdit ? 'lucide:save' : 'lucide:plus')" :class="{ 'mk-spin': saving }" />
        {{ isEdit ? 'Save changes' : 'Create card' }}
      </button>
    </template>
  </MkModal>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkModal from './shared/MkModal.vue'
import MemberPicker from './shared/MemberPicker.vue'
import MkSelect from './shared/MkSelect.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import { showCrmToast } from '@/utils/crmToast.js'
import { MARKETING_CATEGORIES, CONTENT_TYPES, DELIVERABLES, CHANNELS, apiError, chipStyle,DEPARTMENTS } from '@/config/marketingConfig.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  category: { type: String, required: true },
  task: { type: Object, default: null },
  members: { type: Array, default: () => [] },
  stages: { type: Array, default: () => [] },
  initialStageId: { type: [Number, null], default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const isEdit = computed(() => !!props.task)
const cat = computed(() => MARKETING_CATEGORIES[props.category])
const titlePlaceholder = computed(() => ({
  production: 'e.g. Nawayef Villas — walkthrough video',
  design: 'e.g. Manchester City — Story — Slide 7',
  content: 'e.g. Eid Al Adha — Story copy',
}[props.category]))

const saving = ref(false)
const titleInput = ref(null)
const errors = reactive({ title: '', link: '', department: '', reference: '' })
const referenceFile = ref(null)
const referencePreview = ref('')
const removeReferenceImage = ref(false)
const form = reactive(blankForm())

function blankForm() {
  return {
    title: '',
    type: 'video',
    shooting_by: null,
    editing_by: null,
    assigned_to: null,
    designed_by: null,
    also_video: false,
    channels: Object.fromEntries(Object.keys(CHANNELS).map((k) => [k, false])),
    deliverables: [],
    tov: '',
    reference: '',
    notes: '',
    due_date: '',
    stage_id: null,
    link_url: '',
    link_label: '',
    department: '',

  }
}

watch(() => props.modelValue, (open) => {
  if (!open) {
    setReferenceImage(null)
    return
  }
  Object.assign(form, blankForm())
  errors.title = ''
  errors.link = ''
  errors.reference = ''
  const t = props.task
  setReferenceImage(t)
  if (t) {
    Object.assign(form, {
      title: t.title,
      type: t.type || 'video',
      shooting_by: t.shooter?.id ?? null,
      editing_by: t.editor?.id ?? null,
      assigned_to: t.assignee?.id ?? null,
      designed_by: t.designer?.id ?? null,
      also_video: !!t.also_video,
      channels: { ...form.channels, ...(t.channels || {}) },
      deliverables: [...(t.deliverables || [])],
      tov: t.tov || '',
      reference: t.reference || '',
      notes: t.notes || '',
      due_date: t.due_date || '',
      department: t.department || '',

    })
  } else {
    form.stage_id = props.initialStageId ?? props.stages[0]?.id ?? null
  }
  nextTick(() => titleInput.value?.focus())
})

function setReferenceImage(task) {
  if (referencePreview.value?.startsWith('blob:')) URL.revokeObjectURL(referencePreview.value)
  referenceFile.value = null
  removeReferenceImage.value = false
  referencePreview.value = task?.reference_image_url || ''
}

function onReferenceImage(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  if (!file.type.startsWith('image/')) {
    errors.reference = 'Reference must be an image.'
    return
  }
  if (file.size > 8 * 1024 * 1024) {
    errors.reference = 'Image must be 8 MB or smaller.'
    return
  }
  errors.reference = ''
  if (referencePreview.value?.startsWith('blob:')) URL.revokeObjectURL(referencePreview.value)
  referenceFile.value = file
  removeReferenceImage.value = false
  referencePreview.value = URL.createObjectURL(file)
}

function clearReferenceImage() {
  if (referencePreview.value?.startsWith('blob:')) URL.revokeObjectURL(referencePreview.value)
  referenceFile.value = null
  referencePreview.value = ''
  removeReferenceImage.value = true
  errors.reference = ''
}

onBeforeUnmount(() => {
  if (referencePreview.value?.startsWith('blob:')) URL.revokeObjectURL(referencePreview.value)
})

function appendPayload(fd, data) {
  Object.entries(data).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      if (['assigned_to', 'shooting_by', 'editing_by', 'designed_by', 'due_date', 'reference', 'notes', 'tov', 'type'].includes(key)) {
        fd.append(key, '')
      }
      return
    }
    if (key === 'links' && Array.isArray(value)) {
      value.forEach((link, i) => {
        fd.append(`links[${i}][url]`, link.url)
        if (link.label) fd.append(`links[${i}][label]`, link.label)
      })
      return
    }
    if (key === 'deliverables' && Array.isArray(value)) {
      if (!value.length) fd.append('deliverables', '')
      value.forEach((item) => fd.append('deliverables[]', item))
      return
    }
    if (key === 'channels' && typeof value === 'object') {
      Object.entries(value).forEach(([name, on]) => fd.append(`channels[${name}]`, on ? '1' : '0'))
      return
    }
    fd.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value)
  })
}

function toggleDeliverable(key) {
  const i = form.deliverables.indexOf(key)
  if (i === -1) form.deliverables.push(key)
  else form.deliverables.splice(i, 1)
}

function payload() {
  const base = {
    title: form.title,
    reference: form.reference || null,
    notes: form.notes?.trim() || null,
    due_date: form.due_date || null,
    department: form.department,

  }
  if (props.category === 'production') {
    Object.assign(base, { type: form.type, shooting_by: form.shooting_by, editing_by: form.editing_by, channels: form.channels })
  } else {
    base.assigned_to = form.assigned_to
    if (props.category === 'design') Object.assign(base, { deliverables: form.deliverables, tov: form.tov || null })
    if (props.category === 'content') Object.assign(base, { designed_by: form.designed_by, also_video: !!form.also_video })
  }
  if (!isEdit.value) {
    base.category = props.category
    base.stage_id = form.stage_id
    if (form.link_url) base.links = [{ url: form.link_url, label: form.link_label || null }]
  }
  return base
}

async function submit() {
  errors.title = form.title ? '' : 'Title is required.'
  errors.link = form.link_url && !/^https?:\/\//i.test(form.link_url) ? 'Links must start with http:// or https://' : ''
  errors.department = form.department ? '' : 'Choose a department.'
  if (errors.title || errors.link || errors.department || errors.reference) return

  const body = payload()
  if (removeReferenceImage.value && !referenceFile.value) body.remove_reference_image = true

  saving.value = true
  try {
    let data
    if (referenceFile.value) {
      const fd = new FormData()
      appendPayload(fd, body)
      fd.append('reference_image', referenceFile.value)
      if (isEdit.value) fd.append('_method', 'PUT')
      const url = isEdit.value ? `/marketing/tasks/${props.task.id}` : '/marketing/tasks'
      ;({ data } = await api.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } }))
    } else {
      ;({ data } = isEdit.value
        ? await api.put(`/marketing/tasks/${props.task.id}`, body)
        : await api.post('/marketing/tasks', body))
    }
    emit('saved', data.task)
    emit('update:modelValue', false)
    showCrmToast({
      kind: isEdit.value ? 'updated' : 'created',
      headline: isEdit.value ? 'Card updated' : 'Card created successfully',
      subtitle: data.task.title,
      chip: cat.value.label,
    })
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not save', text: apiError(err) })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.mk-label small { text-transform: none; letter-spacing: 0; font-weight: 500 !important; }
.mk-hint { font-size: 12px !important; line-height: 1.45; color: #8d869c; }
.mk-ref { margin-top: 8px; }
.mk-ref img {
  display: block;
  width: 100%;
  max-height: 180px;
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: #f6f4f8;
}
.mk-ref__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.mk-ref__actions input { display: none; }
</style>
