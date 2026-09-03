<template>
    <div class="dashboard-main-body">
        <Breadcrumb
            title="Evaluation Settings"
            :breadcrumbs="[
                { name: 'Settings', path: '/settings' },
                { name: 'Evaluations' }
            ]"
        />

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <iconify-icon icon="lucide:repeat" class="me-2"></iconify-icon>
                    Recurrence
                </h5>
            </div>
            <div class="card-body">
                <div v-if="settingsLoading" class="text-muted">Loading...</div>
                <div v-else class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="recurrenceSwitch"
                            :checked="recurrenceMode === 'recurring'"
                            :disabled="settingsSaving"
                            @change="toggleRecurrence"
                        >
                        <label class="form-check-label" for="recurrenceSwitch">
                            {{ recurrenceMode === 'recurring' ? 'Recurring' : 'Single (one-time per employee)' }}
                        </label>
                    </div>
                    <p class="text-muted small mb-0">
                        {{ recurrenceMode === 'recurring'
                            ? 'Employees are evaluated again every 3/6 months for their whole tenure.'
                            : 'Each employee gets exactly one evaluation, at their 3-month (Sales) or 6-month (other) milestone.' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5">
                <HrSettingsCatalog
                    title="Sections"
                    description="Each section has one question type applied to all its questions."
                    add-label="Add section"
                    search-placeholder="Search sections"
                    empty-text="No sections yet."
                    name-key="title"
                    :items="sections"
                    :loading="sectionsLoading"
                    :saving="sectionSaving"
                    :fields="sectionFields"
                    :subtitle="sectionSubtitle"
                    :badge="(s) => s.is_active ? 'Active' : 'Inactive'"
                    :badge-class="(s) => s.is_active ? 'is-info' : 'is-muted'"
                    @save="saveSection"
                    @remove="removeSection"
                />
                <p class="text-muted small mt-2">Click a section below to manage its questions.</p>
                <div class="list-group">
                    <button
                        v-for="section in sections"
                        :key="section.id"
                        type="button"
                        class="list-group-item list-group-item-action"
                        :class="{ active: selectedSectionId === section.id }"
                        @click="selectSection(section)"
                    >
                        {{ section.title }}
                        <span class="badge bg-secondary float-end">{{ section.questions?.length || 0 }} questions</span>
                    </button>
                </div>
            </div>

            <div class="col-lg-7">
                <HrSettingsCatalog
                    v-if="selectedSectionId"
                    :key="selectedSectionId"
                    :title="`Questions — ${selectedSectionTitle}`"
                    description="Questions shown to the evaluator for this section."
                    add-label="Add question"
                    search-placeholder="Search questions"
                    empty-text="No questions yet."
                    name-key="question_text"
                    :items="questions"
                    :loading="questionsLoading"
                    :saving="questionSaving"
                    :fields="questionFields"
                    @save="saveQuestion"
                    @remove="removeQuestion"
                />
                <div v-else class="card">
                    <div class="card-body text-center text-muted py-5">
                        Select a section to manage its questions.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import HrSettingsCatalog from '@/pages/hr/settings/HrSettingsCatalog.vue';
import api from '@/plugins/axios';

export default {
    name: 'EvaluationSettings',
    components: { Breadcrumb, HrSettingsCatalog },
    data() {
        return {
            settingsLoading: false,
            settingsSaving: false,
            recurrenceMode: 'single',

            sections: [],
            sectionsLoading: false,
            sectionSaving: false,
            sectionFields: [
                { key: 'title', label: 'Title', type: 'text', required: true },
                {
                    key: 'question_type',
                    label: 'Question Type',
                    type: 'select',
                    required: true,
                    options: [
                        { value: 'rating_1_5', label: 'Rating (1-5)' },
                        { value: 'yes_no', label: 'Yes / No' },
                        { value: 'text', label: 'Free text' },
                    ],
                },
                { key: 'sort_order', label: 'Order', type: 'number', default: 0 },
                { key: 'is_active', label: 'Active', type: 'toggle', default: true, onLabel: 'Active', offLabel: 'Inactive' },
            ],

            selectedSectionId: null,
            selectedSectionTitle: '',
            questions: [],
            questionsLoading: false,
            questionSaving: false,
            questionFields: [
                { key: 'question_text', label: 'Question', type: 'textarea', required: true },
                { key: 'sort_order', label: 'Order', type: 'number', default: 0 },
                { key: 'is_active', label: 'Active', type: 'toggle', default: true, onLabel: 'Active', offLabel: 'Inactive' },
            ],
        };
    },
    mounted() {
        this.loadSettings();
        this.loadSections();
    },
    methods: {
        sectionSubtitle(section) {
            const type = this.sectionFields[1].options.find((o) => o.value === section.question_type);
            return type?.label || section.question_type;
        },

        async loadSettings() {
            this.settingsLoading = true;
            try {
                const { data } = await api.get('/evaluations/settings');
                this.recurrenceMode = data?.data?.recurrence_mode || 'single';
            } catch (e) {
                this.showNotification('Failed to load recurrence settings', 'error');
            } finally {
                this.settingsLoading = false;
            }
        },

        async toggleRecurrence(event) {
            const mode = event.target.checked ? 'recurring' : 'single';
            this.settingsSaving = true;
            try {
                await api.put('/evaluations/settings', { recurrence_mode: mode });
                this.recurrenceMode = mode;
                this.showNotification('Recurrence updated', 'success');
            } catch (e) {
                event.target.checked = this.recurrenceMode === 'recurring';
                this.showNotification('Failed to update recurrence', 'error');
            } finally {
                this.settingsSaving = false;
            }
        },

        async loadSections() {
            this.sectionsLoading = true;
            try {
                const { data } = await api.get('/evaluations/sections', { params: { all: 1 } });
                this.sections = data?.data || [];
            } catch (e) {
                this.showNotification('Failed to load sections', 'error');
            } finally {
                this.sectionsLoading = false;
            }
        },

        async saveSection(payload, editing) {
            this.sectionSaving = true;
            try {
                if (editing?.id) {
                    await api.put(`/evaluations/sections/${editing.id}`, payload);
                } else {
                    await api.post('/evaluations/sections', payload);
                }
                await this.loadSections();
                this.showNotification('Section saved', 'success');
            } catch (e) {
                this.showNotification(e?.response?.data?.message || 'Failed to save section', 'error');
            } finally {
                this.sectionSaving = false;
            }
        },

        async removeSection(item) {
            try {
                await api.delete(`/evaluations/sections/${item.id}`);
                if (this.selectedSectionId === item.id) {
                    this.selectedSectionId = null;
                    this.questions = [];
                }
                await this.loadSections();
                this.showNotification('Section removed', 'success');
            } catch (e) {
                this.showNotification(e?.response?.data?.message || 'Failed to remove section', 'error');
            }
        },

        selectSection(section) {
            this.selectedSectionId = section.id;
            this.selectedSectionTitle = section.title;
            this.loadQuestions(section.id);
        },

        async loadQuestions(sectionId) {
            this.questionsLoading = true;
            try {
                const { data } = await api.get(`/evaluations/sections/${sectionId}/questions`);
                this.questions = data?.data || [];
            } catch (e) {
                this.showNotification('Failed to load questions', 'error');
            } finally {
                this.questionsLoading = false;
            }
        },

        async saveQuestion(payload, editing) {
            this.questionSaving = true;
            try {
                if (editing?.id) {
                    await api.put(`/evaluations/questions/${editing.id}`, payload);
                } else {
                    await api.post(`/evaluations/sections/${this.selectedSectionId}/questions`, payload);
                }
                await this.loadQuestions(this.selectedSectionId);
                await this.loadSections();
                this.showNotification('Question saved', 'success');
            } catch (e) {
                this.showNotification(e?.response?.data?.message || 'Failed to save question', 'error');
            } finally {
                this.questionSaving = false;
            }
        },

        async removeQuestion(item) {
            try {
                await api.delete(`/evaluations/questions/${item.id}`);
                await this.loadQuestions(this.selectedSectionId);
                await this.loadSections();
                this.showNotification('Question removed', 'success');
            } catch (e) {
                this.showNotification(e?.response?.data?.message || 'Failed to remove question', 'error');
            }
        },

        showNotification(message, type = 'info') {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            }
        },
    },
};
</script>

<style scoped>
.dashboard-main-body {
    padding: 1rem;
}

/*
 * HrSettingsCatalog (resources/js/pages/hr/settings/HrSettingsCatalog.vue) ships with
 * no background/list/badge styling of its own — it was built to live inside
 * HrSettingsHub.vue's white modal, which supplies all of this via :deep() selectors
 * scoped to that component. Since this page uses the catalog standalone (not inside
 * that modal), the same rules are duplicated here so it renders on its own.
 */
:deep(.hr-set-catalog) {
    background: #fff;
    border: 1px solid #eee8f4;
    border-radius: 16px;
    padding: 16px;
}
:deep(.hr-set-catalog__head) {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 12px;
}
:deep(.hr-set-catalog__title) {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #0b0736;
    line-height: 1.3;
}
:deep(.hr-set-catalog__head p) {
    margin: 4px 0 0;
    font-size: 12px;
    color: #6b7280;
}
:deep(.hr-set-search) {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #eceff5;
    border-radius: 999px;
    padding: 8px 12px;
    color: #9ca3af;
    margin-bottom: 12px;
}
:deep(.hr-set-search input) {
    border: none;
    outline: none;
    width: 100%;
    background: transparent;
    font-size: 13px;
    color: #111827;
}
:deep(.hr-set-btn) {
    border: 1px solid #e5e7eb;
    background: #f3f4f6;
    color: #111827;
    border-radius: 999px;
    height: 36px;
    padding: 0 14px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
:deep(.hr-set-btn--primary) {
    background: #0b0736;
    border-color: #0b0736;
    color: #fff;
}
:deep(.hr-set-list) {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
:deep(.hr-set-item) {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid #f0ecf5;
    border-radius: 12px;
}
:deep(.hr-set-item__body) { min-width: 0; flex: 1; }
:deep(.hr-set-item__body strong),
:deep(.hr-set-item__body small) { display: block; }
:deep(.hr-set-item__body strong) { font-size: 14px; color: #111827; }
:deep(.hr-set-item__body small) { font-size: 12px; color: #9ca3af; }
:deep(.hr-set-item__actions) { display: inline-flex; gap: 4px; }
:deep(.hr-set-item__actions button) {
    width: 30px;
    height: 30px;
    border: none;
    background: transparent;
    color: #9ca3af;
    border-radius: 8px;
}
:deep(.hr-set-item__actions button:hover) { background: #f3f4f6; color: #4b5563; }
:deep(.hr-set-badge) {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 999px;
    background: #f4f0f8;
    color: #733e87;
}
:deep(.hr-set-badge.is-info) { background: #eef2ff; color: #3730a3; }
:deep(.hr-set-badge.is-muted) { background: #f3f4f6; color: #6b7280; }
:deep(.hr-set-empty) {
    text-align: center;
    color: #9ca3af;
    padding: 24px 8px;
    font-size: 13px;
}

/* Add/Edit modal form fields — likewise only styled via HrSettingsHub.vue's :deep() rules. */
:deep(.hr-set-form__grid) {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}
:deep(.hr-set-form__grid.is-wide) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
:deep(.hr-set-field) {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
:deep(.hr-set-field.is-full) { grid-column: 1 / -1; }
:deep(.hr-set-field label) {
    font-size: 12px;
    font-weight: 700;
    color: #0b0736;
}
:deep(.hr-set-field em) { color: #dc2626; }
:deep(.hr-set-field input:not([type="checkbox"]):not([type="radio"])),
:deep(.hr-set-field select),
:deep(.hr-set-field textarea) {
    height: 42px;
    border: 1px solid #eceff5;
    border-radius: 10px;
    padding: 0 12px;
    background: #fff;
    color: #111827;
    width: 100%;
}
:deep(.hr-set-field textarea) {
    height: auto;
    padding: 10px 12px;
}
:deep(.hr-set-form__actions) {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
}
</style>
