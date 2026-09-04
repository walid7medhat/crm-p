<template>
  <div class="ev-page">
    <div class="ev-shell">
      <div class="ev-top">
        <div class="ev-top__left">
          <h6 class="ev-title">Evaluations</h6>
          <p class="ev-sub">Manage recurrence, sections, and questions</p>
        </div>
        <div class="ev-tabs">
          <button type="button" :class="{ 'is-active': sub === 'sections' }" @click="sub = 'sections'">Sections</button>
          <button type="button" :class="{ 'is-active': sub === 'recurrence' }" @click="sub = 'recurrence'">Recurrence</button>
        </div>
      </div>

      <section v-if="sub === 'recurrence'" class="ev-recurrence">
        <div v-if="settingsLoading" class="ev-empty">Loading…</div>
        <template v-else>
          <label class="ev-switch">
            <input
              type="checkbox"
              :checked="recurrenceMode === 'recurring'"
              :disabled="settingsSaving"
              @change="toggleRecurrence"
            />
            <span class="ev-switch__track" />
            <span class="ev-switch__copy">
              <strong>{{ recurrenceMode === 'recurring' ? 'Recurring' : 'Single (one-time)' }}</strong>
              <small>
                {{ recurrenceMode === 'recurring'
                  ? 'After their fixed check-ins, employees keep being evaluated again at the same interval for their whole tenure.'
                  : 'Sales gets two check-ins (1 month, then 2 months). Everyone else gets one, at 6 months.' }}
              </small>
            </span>
          </label>
        </template>
      </section>

      <div v-else class="ev-grid">
        <!-- Sections -->
        <aside class="ev-pane">
          <div class="ev-pane__head">
            <div>
              <p class="ev-pane__title">Sections</p>
              <p class="ev-pane__hint">{{ sections.length }} total</p>
            </div>
            <button type="button" class="ev-btn ev-btn--primary" @click="startCreateSection">
              <iconify-icon icon="lucide:plus" />
              Add
            </button>
          </div>

          <label class="ev-search">
            <iconify-icon icon="lucide:search" />
            <input v-model="sectionSearch" type="text" placeholder="Search sections…" />
          </label>

          <form v-if="sectionFormOpen" class="ev-inline" @submit.prevent="submitSection">
            <p class="ev-inline__title">{{ editingSection ? 'Edit section' : 'New section' }}</p>
            <label class="ev-field">
              <span>Title</span>
              <input v-model="sectionForm.title" type="text" placeholder="e.g. Communication" required />
            </label>
            <div class="ev-inline__row">
              <label class="ev-field">
                <span>Type</span>
                <select v-model="sectionForm.question_type" required>
                  <option value="">Select</option>
                  <option v-for="opt in questionTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </label>
              <label class="ev-field">
                <span>Order</span>
                <input v-model.number="sectionForm.sort_order" type="number" min="0" />
              </label>
            </div>
            <label class="ev-switch ev-switch--compact">
              <input type="checkbox" v-model="sectionForm.is_active" />
              <span class="ev-switch__track" />
              <span class="ev-switch__copy"><strong>{{ sectionForm.is_active ? 'Active' : 'Inactive' }}</strong></span>
            </label>
            <div class="ev-inline__actions">
              <button type="button" class="ev-btn" @click="cancelSectionForm">Cancel</button>
              <button type="submit" class="ev-btn ev-btn--primary" :disabled="sectionSaving">
                {{ sectionSaving ? 'Saving…' : (editingSection ? 'Save' : 'Add section') }}
              </button>
            </div>
          </form>

          <div v-if="sectionsLoading" class="ev-empty">Loading…</div>
          <div v-else-if="!filteredSections.length" class="ev-empty">
            {{ sectionSearch ? 'No matching sections.' : 'No sections yet.' }}
          </div>
          <div v-else class="ev-list">
            <button
              v-for="section in filteredSections"
              :key="section.id"
              type="button"
              class="ev-item"
              :class="{ 'is-active': selectedSectionId === section.id }"
              @click="selectSection(section)"
            >
              <span class="ev-item__body">
                <strong>{{ section.title }}</strong>
                <small>{{ sectionSubtitle(section) }}</small>
              </span>
              <span class="ev-badge" :class="section.is_active ? 'is-on' : 'is-off'">
                {{ section.is_active ? 'Active' : 'Off' }}
              </span>
              <span class="ev-item__actions">
                <button type="button" title="Edit" @click.stop="startEditSection(section)">
                  <iconify-icon icon="lucide:pencil" />
                </button>
                <button type="button" title="Delete" @click.stop="removeSection(section)">
                  <iconify-icon icon="lucide:trash-2" />
                </button>
              </span>
            </button>
          </div>
        </aside>

        <!-- Questions -->
        <section class="ev-pane">
          <template v-if="selectedSectionId">
            <div class="ev-pane__head">
              <div>
                <p class="ev-pane__title">Questions</p>
                <p class="ev-pane__hint">{{ selectedSectionTitle }} · {{ questions.length }}</p>
              </div>
              <button type="button" class="ev-btn ev-btn--primary" @click="startCreateQuestion">
                <iconify-icon icon="lucide:plus" />
                Add
              </button>
            </div>

            <label class="ev-search">
              <iconify-icon icon="lucide:search" />
              <input v-model="questionSearch" type="text" placeholder="Search questions…" />
            </label>

            <form v-if="questionFormOpen" class="ev-inline" @submit.prevent="submitQuestion">
              <p class="ev-inline__title">{{ editingQuestion ? 'Edit question' : 'New question' }}</p>
              <label class="ev-field">
                <span>Question</span>
                <textarea v-model="questionForm.question_text" rows="3" placeholder="Write the question…" required />
              </label>
              <div class="ev-inline__row">
                <label class="ev-field">
                  <span>Order</span>
                  <input v-model.number="questionForm.sort_order" type="number" min="0" />
                </label>
                <label class="ev-switch ev-switch--compact">
                  <input type="checkbox" v-model="questionForm.is_active" />
                  <span class="ev-switch__track" />
                  <span class="ev-switch__copy"><strong>{{ questionForm.is_active ? 'Active' : 'Inactive' }}</strong></span>
                </label>
              </div>
              <div class="ev-inline__actions">
                <button type="button" class="ev-btn" @click="cancelQuestionForm">Cancel</button>
                <button type="submit" class="ev-btn ev-btn--primary" :disabled="questionSaving">
                  {{ questionSaving ? 'Saving…' : (editingQuestion ? 'Save' : 'Add question') }}
                </button>
              </div>
            </form>

            <div v-if="questionsLoading" class="ev-empty">Loading…</div>
            <div v-else-if="!filteredQuestions.length" class="ev-empty">
              {{ questionSearch ? 'No matching questions.' : 'No questions yet.' }}
            </div>
            <div v-else class="ev-list">
              <article v-for="(q, index) in filteredQuestions" :key="q.id" class="ev-item ev-item--static">
                <span class="ev-item__index">{{ index + 1 }}</span>
                <span class="ev-item__body">
                  <strong>{{ q.question_text }}</strong>
                  <small>Order {{ q.sort_order ?? 0 }}</small>
                </span>
                <span class="ev-badge" :class="q.is_active ? 'is-on' : 'is-off'">
                  {{ q.is_active ? 'Active' : 'Off' }}
                </span>
                <span class="ev-item__actions">
                  <button type="button" title="Edit" @click="startEditQuestion(q)">
                    <iconify-icon icon="lucide:pencil" />
                  </button>
                  <button type="button" title="Delete" @click="removeQuestion(q)">
                    <iconify-icon icon="lucide:trash-2" />
                  </button>
                </span>
              </article>
            </div>
          </template>

          <div v-else class="ev-placeholder">
            <iconify-icon icon="lucide:clipboard-list" width="22" />
            <p>Select a section</p>
            <small>Pick a section on the left to manage its questions.</small>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'

const sub = ref('sections')
const settingsLoading = ref(false)
const settingsSaving = ref(false)
const recurrenceMode = ref('single')

const sections = ref([])
const sectionsLoading = ref(false)
const sectionSaving = ref(false)
const sectionSearch = ref('')
const sectionFormOpen = ref(false)
const editingSection = ref(null)
const sectionForm = reactive({
  title: '',
  question_type: '',
  sort_order: 0,
  is_active: true,
})

const selectedSectionId = ref(null)
const selectedSectionTitle = ref('')
const questions = ref([])
const questionsLoading = ref(false)
const questionSaving = ref(false)
const questionSearch = ref('')
const questionFormOpen = ref(false)
const editingQuestion = ref(null)
const questionForm = reactive({
  question_text: '',
  sort_order: 0,
  is_active: true,
})

const questionTypeOptions = [
  { value: 'rating_1_5', label: 'Rating (1–5)' },
  { value: 'yes_no', label: 'Yes / No' },
  { value: 'text', label: 'Free text' },
]

const filteredSections = computed(() => {
  const q = sectionSearch.value.trim().toLowerCase()
  const list = [...sections.value].sort((a, b) =>
    String(a.title || '').localeCompare(String(b.title || ''), undefined, { sensitivity: 'base' }),
  )
  if (!q) return list
  return list.filter((s) =>
    String(s.title || '').toLowerCase().includes(q)
    || String(s.question_type || '').toLowerCase().includes(q),
  )
})

const filteredQuestions = computed(() => {
  const q = questionSearch.value.trim().toLowerCase()
  const list = [...questions.value].sort((a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0))
  if (!q) return list
  return list.filter((item) => String(item.question_text || '').toLowerCase().includes(q))
})

function notify(message, type = 'success') {
  window.$showNotification?.(message, type)
}

function sectionSubtitle(section) {
  const type = questionTypeOptions.find((o) => o.value === section.question_type)
  const count = section.questions?.length
  const parts = [type?.label || section.question_type]
  if (count != null) parts.push(`${count} q`)
  return parts.filter(Boolean).join(' · ')
}

function resetSectionForm() {
  sectionForm.title = ''
  sectionForm.question_type = ''
  sectionForm.sort_order = 0
  sectionForm.is_active = true
  editingSection.value = null
}

function resetQuestionForm() {
  questionForm.question_text = ''
  questionForm.sort_order = 0
  questionForm.is_active = true
  editingQuestion.value = null
}

function startCreateSection() {
  resetSectionForm()
  sectionFormOpen.value = true
}

function startEditSection(section) {
  editingSection.value = section
  sectionForm.title = section.title || ''
  sectionForm.question_type = section.question_type || ''
  sectionForm.sort_order = Number(section.sort_order) || 0
  sectionForm.is_active = section.is_active !== false
  sectionFormOpen.value = true
  selectSection(section)
}

function cancelSectionForm() {
  sectionFormOpen.value = false
  resetSectionForm()
}

function startCreateQuestion() {
  resetQuestionForm()
  questionFormOpen.value = true
}

function startEditQuestion(question) {
  editingQuestion.value = question
  questionForm.question_text = question.question_text || ''
  questionForm.sort_order = Number(question.sort_order) || 0
  questionForm.is_active = question.is_active !== false
  questionFormOpen.value = true
}

function cancelQuestionForm() {
  questionFormOpen.value = false
  resetQuestionForm()
}

async function loadSettings() {
  settingsLoading.value = true
  try {
    const { data } = await api.get('/evaluations/settings')
    recurrenceMode.value = data?.data?.recurrence_mode || 'single'
  } catch {
    notify('Failed to load recurrence settings', 'error')
  } finally {
    settingsLoading.value = false
  }
}

async function toggleRecurrence(event) {
  const mode = event.target.checked ? 'recurring' : 'single'
  settingsSaving.value = true
  try {
    await api.put('/evaluations/settings', { recurrence_mode: mode })
    recurrenceMode.value = mode
    notify('Recurrence updated')
  } catch {
    event.target.checked = recurrenceMode.value === 'recurring'
    notify('Failed to update recurrence', 'error')
  } finally {
    settingsSaving.value = false
  }
}

async function loadSections() {
  sectionsLoading.value = true
  try {
    const { data } = await api.get('/evaluations/sections', { params: { all: 1 } })
    sections.value = data?.data || []
    if (selectedSectionId.value) {
      const still = sections.value.find((s) => s.id === selectedSectionId.value)
      if (still) selectedSectionTitle.value = still.title
      else {
        selectedSectionId.value = null
        selectedSectionTitle.value = ''
        questions.value = []
      }
    }
  } catch {
    notify('Failed to load sections', 'error')
  } finally {
    sectionsLoading.value = false
  }
}

async function submitSection() {
  if (!sectionForm.title.trim() || !sectionForm.question_type) {
    notify('Please fill title and type', 'error')
    return
  }
  sectionSaving.value = true
  try {
    const body = {
      title: sectionForm.title.trim(),
      question_type: sectionForm.question_type,
      sort_order: Number(sectionForm.sort_order) || 0,
      is_active: sectionForm.is_active !== false,
    }
    if (editingSection.value?.id) {
      await api.put(`/evaluations/sections/${editingSection.value.id}`, body)
    } else {
      await api.post('/evaluations/sections', body)
    }
    cancelSectionForm()
    await loadSections()
    notify('Section saved')
  } catch (e) {
    notify(e?.response?.data?.message || 'Failed to save section', 'error')
  } finally {
    sectionSaving.value = false
  }
}

async function removeSection(item) {
  const result = await Swal.fire({
    title: 'Delete section?',
    text: `Delete "${item.title}"? This cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Delete',
  })
  if (!result.isConfirmed) return
  try {
    await api.delete(`/evaluations/sections/${item.id}`)
    if (selectedSectionId.value === item.id) {
      selectedSectionId.value = null
      selectedSectionTitle.value = ''
      questions.value = []
      cancelQuestionForm()
    }
    await loadSections()
    notify('Section removed')
  } catch (e) {
    notify(e?.response?.data?.message || 'Failed to remove section', 'error')
  }
}

function selectSection(section) {
  selectedSectionId.value = section.id
  selectedSectionTitle.value = section.title
  cancelQuestionForm()
  loadQuestions(section.id)
}

async function loadQuestions(sectionId) {
  questionsLoading.value = true
  try {
    const { data } = await api.get(`/evaluations/sections/${sectionId}/questions`)
    questions.value = data?.data || []
  } catch {
    notify('Failed to load questions', 'error')
  } finally {
    questionsLoading.value = false
  }
}

async function submitQuestion() {
  if (!questionForm.question_text.trim()) {
    notify('Please enter the question', 'error')
    return
  }
  questionSaving.value = true
  try {
    const body = {
      question_text: questionForm.question_text.trim(),
      sort_order: Number(questionForm.sort_order) || 0,
      is_active: questionForm.is_active !== false,
    }
    if (editingQuestion.value?.id) {
      await api.put(`/evaluations/questions/${editingQuestion.value.id}`, body)
    } else {
      await api.post(`/evaluations/sections/${selectedSectionId.value}/questions`, body)
    }
    cancelQuestionForm()
    await loadQuestions(selectedSectionId.value)
    await loadSections()
    notify('Question saved')
  } catch (e) {
    notify(e?.response?.data?.message || 'Failed to save question', 'error')
  } finally {
    questionSaving.value = false
  }
}

async function removeQuestion(item) {
  const result = await Swal.fire({
    title: 'Delete question?',
    text: 'This cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Delete',
  })
  if (!result.isConfirmed) return
  try {
    await api.delete(`/evaluations/questions/${item.id}`)
    await loadQuestions(selectedSectionId.value)
    await loadSections()
    notify('Question removed')
  } catch (e) {
    notify(e?.response?.data?.message || 'Failed to remove question', 'error')
  }
}

onMounted(() => {
  loadSettings()
  loadSections()
})
</script>

<style scoped>
.ev-page {
  --navy: #0b0736;
  --purple: #733e87;
  --border: #ece8f3;
  --muted: #6b7280;
  padding: 0;
  font-size: 13px;
  color: #111827;
}
.ev-shell {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 8px 24px rgba(11, 7, 54, 0.06);
}
.ev-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f3f0f7;
}
.ev-title {
  margin: 0 !important;
  font-size: 15px !important;
  font-weight: 700 !important;
  color: var(--navy) !important;
  line-height: 1.3 !important;
}
.ev-sub {
  margin: 2px 0 0 !important;
  font-size: 12px !important;
  color: var(--muted) !important;
}
.ev-tabs {
  display: flex;
  gap: 6px;
}
.ev-tabs button {
  height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #4b5563;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.ev-tabs button.is-active {
  background: var(--navy);
  border-color: var(--navy);
  color: #fff;
}

.ev-recurrence {
  padding: 4px 0;
}
.ev-switch {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #faf8fc;
}
.ev-switch--compact {
  align-items: center;
  padding: 8px 10px;
  min-height: 34px;
}
.ev-switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}
.ev-switch__track {
  width: 40px;
  height: 22px;
  border-radius: 999px;
  background: #d1d5db;
  position: relative;
  flex-shrink: 0;
  margin-top: 1px;
  transition: background 0.15s ease;
}
.ev-switch__track::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.2);
  transition: transform 0.15s ease;
}
.ev-switch input:checked + .ev-switch__track {
  background: var(--navy);
}
.ev-switch input:checked + .ev-switch__track::after {
  transform: translateX(18px);
}
.ev-switch__copy {
  min-width: 0;
}
.ev-switch__copy strong,
.ev-switch__copy small {
  display: block;
}
.ev-switch__copy strong {
  font-size: 13px !important;
  color: var(--navy);
}
.ev-switch__copy small {
  margin-top: 2px;
  font-size: 12px !important;
  color: var(--muted);
  line-height: 1.4;
}

.ev-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
  gap: 12px;
  align-items: start;
}
.ev-pane {
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px;
  background: #fff;
  min-height: 360px;
}
.ev-pane__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}
.ev-pane__title {
  margin: 0 !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  color: var(--navy) !important;
}
.ev-pane__hint {
  margin: 2px 0 0 !important;
  font-size: 11px !important;
  color: var(--muted) !important;
}

.ev-btn {
  height: 30px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
  font-size: 12px !important;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  white-space: nowrap;
}
.ev-btn--primary {
  background: var(--navy);
  border-color: var(--navy);
  color: #fff;
}

.ev-search {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  padding: 0 10px;
  margin-bottom: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #faf8fc;
  color: #9ca3af;
}
.ev-search input {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background: transparent !important;
  width: 100%;
  font-size: 12px !important;
  color: #111827 !important;
  padding: 0 !important;
}

.ev-inline {
  margin-bottom: 10px;
  padding: 10px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: #faf8fc;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ev-inline__title {
  margin: 0 !important;
  font-size: 12px !important;
  font-weight: 700 !important;
  color: var(--navy) !important;
}
.ev-inline__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  align-items: end;
}
.ev-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ev-field span {
  font-size: 10px !important;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.ev-field input,
.ev-field select,
.ev-field textarea {
  height: 34px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 0 10px;
  font-size: 12px !important;
  color: #111827;
  background: #fff;
  width: 100%;
}
.ev-field textarea {
  height: auto;
  min-height: 72px;
  padding: 8px 10px;
  resize: vertical;
  line-height: 1.4;
}
.ev-field input:focus,
.ev-field select:focus,
.ev-field textarea:focus {
  outline: none;
  border-color: var(--navy);
}
.ev-inline__actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

.ev-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 420px;
  overflow: auto;
}
.ev-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  text-align: left;
  padding: 10px;
  border: 1px solid #f0ecf5;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}
.ev-item--static {
  cursor: default;
}
.ev-item.is-active {
  border-color: var(--navy);
  background: #f7f5fa;
  box-shadow: inset 3px 0 0 var(--navy);
}
.ev-item__index {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: #f3f0f7;
  color: var(--muted);
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ev-item__body {
  min-width: 0;
  flex: 1;
}
.ev-item__body strong,
.ev-item__body small {
  display: block;
}
.ev-item__body strong {
  font-size: 12px !important;
  font-weight: 600;
  color: var(--navy);
  line-height: 1.35;
  word-break: break-word;
}
.ev-item__body small {
  margin-top: 2px;
  font-size: 11px !important;
  color: #9ca3af;
}
.ev-badge {
  font-size: 10px !important;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 999px;
  flex-shrink: 0;
}
.ev-badge.is-on {
  background: #eef2ff;
  color: #3730a3;
}
.ev-badge.is-off {
  background: #f3f4f6;
  color: #6b7280;
}
.ev-item__actions {
  display: inline-flex;
  gap: 2px;
  flex-shrink: 0;
}
.ev-item__actions button {
  width: 26px;
  height: 26px;
  border: none;
  background: transparent;
  color: #9ca3af;
  border-radius: 6px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.ev-item__actions button:hover {
  background: #f3f4f6;
  color: #4b5563;
}

.ev-empty,
.ev-placeholder {
  text-align: center;
  color: #9ca3af;
  padding: 28px 12px;
  font-size: 12px !important;
}
.ev-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 280px;
}
.ev-placeholder p {
  margin: 6px 0 0 !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  color: var(--navy) !important;
}
.ev-placeholder small {
  font-size: 12px !important;
  color: var(--muted);
  max-width: 220px;
}

@media (max-width: 860px) {
  .ev-grid {
    grid-template-columns: 1fr;
  }
  .ev-inline__row {
    grid-template-columns: 1fr;
  }
}
</style>
