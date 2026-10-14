<template>
  <MkModal
    :model-value="modelValue"
    :title="celebrate ? 'Nice — it\'s done! 🎉' : 'Add link'"
    :subtitle="task ? (celebrate ? `Add the final link for “${task.title}”?` : task.title) : ''"
    :icon="celebrate ? 'lucide:party-popper' : 'lucide:link'"
    :accent="celebrate ? '#16a34a' : ''"
    width="520px"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <p class="mk-done__hint">
      <template v-if="celebrate">Paste the Drive folder, file or Google Sheet so everyone can find the finished work. You can skip this and add it later from the card.</template>
      <template v-else>Work-in-progress folders, drafts or finals — add as many links as you need.</template>
    </p>
    <form id="mk-done-link" @submit.prevent="save">
      <div class="mk-field">
        <label for="mk-done-url">Link</label>
        <input id="mk-done-url" ref="urlInput" v-model.trim="url" type="url" class="mk-input" placeholder="https://drive.google.com/…" required />
      </div>
      <div class="mk-field">
        <label for="mk-done-label">Label <small>(optional)</small></label>
        <input id="mk-done-label" v-model.trim="label" class="mk-input" placeholder="e.g. Final V3.mp4" />
      </div>
    </form>
    <template #footer>
      <button type="button" class="mk-btn mk-btn--ghost" @click="$emit('update:modelValue', false)">{{ celebrate ? 'Skip for now' : 'Cancel' }}</button>
      <button type="submit" form="mk-done-link" class="mk-btn" :class="celebrate ? 'mk-btn--success' : 'mk-btn--primary'" :disabled="saving || !url">
        <iconify-icon :icon="saving ? 'lucide:loader-2' : 'lucide:link'" :class="{ 'mk-spin': saving }" /> Add link
      </button>
    </template>
  </MkModal>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkModal from './shared/MkModal.vue'
import { apiError } from '@/config/marketingConfig.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  task: { type: Object, default: null },
  /** true = shown after a card reaches Done; false = plain "Add link". */
  celebrate: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const url = ref('')
const label = ref('')
const saving = ref(false)
const urlInput = ref(null)

watch(() => props.modelValue, (open) => {
  if (!open) return
  url.value = ''
  label.value = ''
  nextTick(() => urlInput.value?.focus())
})

async function save() {
  if (!props.task || !url.value) return
  saving.value = true
  try {
    await api.post(`/marketing/tasks/${props.task.id}/links`, { url: url.value, label: label.value || null })
    emit('saved', props.task.id)
    emit('update:modelValue', false)
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not add link', text: apiError(err) })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.mk-done__hint { margin: 0 0 16px; font-size: 14px !important; color: var(--text-secondary-light); line-height: 1.5; }
small { text-transform: none; letter-spacing: 0; font-weight: 500 !important; }
</style>
