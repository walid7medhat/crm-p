<template>
    <div class="dashboard-main-body">
        <Breadcrumb
            title="Employee Evaluation"
            :breadcrumbs="[
                { name: 'Dashboard', path: '/' },
                { name: 'Evaluation' }
            ]"
        />

        <div v-if="loading" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="mb-0">Loading evaluation form...</p>
            </div>
        </div>

        <div v-else-if="error" class="card">
            <div class="card-body text-center py-5">
                <i class="ri-error-warning-line display-1 text-danger mb-3"></i>
                <h4>{{ error }}</h4>
                <button class="btn btn-primary mt-3" @click="$router.back()">
                    <i class="ri-arrow-left-line me-2"></i>Back
                </button>
            </div>
        </div>

        <div v-else-if="alreadySubmitted" class="card">
            <div class="card-body text-center py-5">
                <i class="ri-checkbox-circle-line display-1 text-success mb-3"></i>
                <h4>This evaluation has already been submitted</h4>
            </div>
        </div>

        <div v-else class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Evaluation for {{ employeeName }}</h5>
                <p class="text-muted small mb-0">{{ milestoneMonths }}-month review</p>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div v-for="section in sections" :key="section.id" class="mb-4">
                        <h6 class="fw-bold mb-3">{{ section.title }}</h6>

                        <div v-for="question in section.questions" :key="question.id" class="mb-3">
                            <label class="form-label">{{ question.question_text }}</label>

                            <div v-if="section.question_type === 'rating_1_5'" class="d-flex flex-wrap gap-2">
                                <button
                                    v-for="opt in ratingOptions"
                                    :key="opt.value"
                                    type="button"
                                    class="btn btn-sm"
                                    :class="String(answers[question.id]) === opt.value ? 'btn-primary' : 'btn-outline-secondary'"
                                    @click="answers[question.id] = opt.value"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>

                            <div v-else-if="section.question_type === 'yes_no'" class="d-flex gap-2">
                                <button
                                    type="button"
                                    class="btn"
                                    :class="answers[question.id] === 'yes' ? 'btn-success' : 'btn-outline-secondary'"
                                    @click="answers[question.id] = 'yes'"
                                >
                                    Yes
                                </button>
                                <button
                                    type="button"
                                    class="btn"
                                    :class="answers[question.id] === 'no' ? 'btn-danger' : 'btn-outline-secondary'"
                                    @click="answers[question.id] = 'no'"
                                >
                                    No
                                </button>
                            </div>

                            <textarea
                                v-else
                                v-model="answers[question.id]"
                                class="form-control"
                                rows="3"
                            ></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        {{ submitting ? 'Submitting...' : 'Submit Evaluation' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import api from '@/plugins/axios';

export default {
    name: 'FillEvaluation',
    components: { Breadcrumb },
    data() {
        return {
            loading: false,
            submitting: false,
            error: null,
            alreadySubmitted: false,
            employeeName: '',
            milestoneMonths: null,
            sections: [],
            answers: {},
            ratingOptions: [
                { value: '1', label: 'Unsatisfactory (1)' },
                { value: '2', label: 'Marginal (2)' },
                { value: '3', label: 'Satisfactory (3)' },
                { value: '4', label: 'Highly Satisfactory (4)' },
                { value: '5', label: 'Exceptional (5)' },
                { value: 'N/A', label: 'N/A' },
            ],
        };
    },
    mounted() {
        this.fetchEvaluation();
    },
    methods: {
        async fetchEvaluation() {
            this.loading = true;
            try {
                const { data } = await api.get(`/evaluations/${this.$route.params.id}`);
                const payload = data?.data;
                const evaluation = payload?.evaluation;

                if (evaluation?.status === 'submitted') {
                    this.alreadySubmitted = true;
                    return;
                }

                this.employeeName = evaluation?.user?.display_name || evaluation?.user?.name || '';
                this.milestoneMonths = evaluation?.milestone_months;
                this.sections = payload?.sections || [];

                this.sections.forEach((section) => {
                    section.questions.forEach((question) => {
                        if (question.answer_value != null) {
                            this.answers[question.id] = question.answer_value;
                        }
                    });
                });
            } catch (e) {
                this.error = e?.response?.data?.message || 'Evaluation not found or access denied.';
            } finally {
                this.loading = false;
            }
        },

        async submit() {
            this.submitting = true;
            try {
                const answers = Object.entries(this.answers).map(([questionId, value]) => ({
                    question_id: Number(questionId),
                    answer_value: value != null ? String(value) : null,
                }));

                await api.post(`/evaluations/${this.$route.params.id}/submit`, { answers });
                this.showNotification('Evaluation submitted successfully', 'success');
                this.$router.push('/view-profile');
            } catch (e) {
                this.showNotification(e?.response?.data?.message || 'Failed to submit evaluation', 'error');
            } finally {
                this.submitting = false;
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
</style>
