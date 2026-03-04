<template>
  <div class="suggestions-page-wrap">
    <div class="suggestions-page">
      <Breadcrumb
        title="Suggestion"
        :breadcrumbs="[
          { name: 'Dashboard', path: '/' },
          { name: 'Suggestion' }
        ]"
      />

      <!-- Submit form: only for non-admin (agents) -->
      <div v-if="!isAdmin" class="suggestion-panel suggestion-form-panel">
        <h6 class="suggestion-panel-title">Submit a suggestion</h6>
        <form @submit.prevent="submitSuggestion" class="suggestion-form">
          <textarea
            v-model="formContent"
            class="suggestion-textarea"
            placeholder="Write your suggestion here..."
            rows="4"
            maxlength="5000"
          />
          <div class="suggestion-form-footer">
            <span class="suggestion-char-count">{{ formContent.length }} / 5000</span>
            <button type="submit" class="suggestion-submit-btn" :disabled="submitting || !formContent.trim()">
              {{ submitting ? 'Sending...' : 'Send suggestion' }}
            </button>
          </div>
        </form>
        <p v-if="submitSuccess" class="suggestion-success-msg">Suggestion sent successfully.</p>
        <p v-if="submitError" class="suggestion-error-msg">{{ submitError }}</p>
      </div>

      <!-- Admin/Super admin: list of suggestions with avatar and name -->
      <div v-if="isAdmin" class="suggestion-panel suggestion-list-panel">
        <h6 class="suggestion-panel-title">All suggestions</h6>
        <div v-if="listLoading" class="suggestion-list-loading">
          <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
          Loading suggestions...
        </div>
        <div v-else-if="suggestions.length === 0" class="suggestion-list-empty">
          No suggestions yet.
        </div>
        <div v-else class="suggestion-list">
          <div
            v-for="s in suggestions"
            :key="s.id"
            class="suggestion-card"
          >
            <div class="suggestion-card-header">
              <div class="suggestion-user-avatar-wrap">
                <img
                  v-if="s.user && s.user.avatar && !avatarErrorIds.has(s.id)"
                  :src="s.user.avatar"
                  :alt="s.user.name"
                  class="suggestion-user-avatar"
                  @error="markAvatarError(s.id)"
                />
                <div v-else class="suggestion-user-avatar-placeholder">
                  <iconify-icon icon="lucide:user" class="suggestion-avatar-icon" />
                </div>
              </div>
              <div class="suggestion-card-meta">
                <span class="suggestion-sender-name">{{ s.user ? s.user.name : 'Unknown' }}</span>
                <span class="suggestion-date">{{ formatDate(s.created_at) }}</span>
              </div>
            </div>
            <div class="suggestion-card-content">{{ s.content }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import api from '@/plugins/axios';

const formContent = ref('');
const submitting = ref(false);
const submitSuccess = ref(false);
const submitError = ref('');
const suggestions = ref([]);
const listLoading = ref(false);
const avatarErrorIds = ref(new Set());

const getUserFromStorage = () => {
  try {
    const userData = localStorage.getItem('user');
    return userData ? JSON.parse(userData) : null;
  } catch {
    return null;
  }
};

const user = ref(getUserFromStorage());

const isAdmin = computed(() => {
  if (!user.value) return false;
  const roles = user.value.roles || [];
  return roles.includes('super_admin') || roles.includes('admin');
});

function formatDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

async function submitSuggestion() {
  const content = formContent.value?.trim();
  if (!content) return;
  submitting.value = true;
  submitSuccess.value = false;
  submitError.value = '';
  try {
    await api.post('/suggestions', { content });
    submitSuccess.value = true;
    formContent.value = '';
    if (isAdmin.value) fetchSuggestions();
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Failed to send suggestion.';
  } finally {
    submitting.value = false;
  }
}

function markAvatarError(id) {
  avatarErrorIds.value = new Set([...avatarErrorIds.value, id]);
}

async function fetchSuggestions() {
  if (!isAdmin.value) return;
  listLoading.value = true;
  avatarErrorIds.value = new Set();
  try {
    const { data } = await api.get('/suggestions');
    suggestions.value = data.suggestions || [];
  } catch {
    suggestions.value = [];
  } finally {
    listLoading.value = false;
  }
}

onMounted(() => {
  if (isAdmin.value) fetchSuggestions();
});
</script>

<style scoped>
.suggestions-page-wrap {
  min-height: 100vh;
  padding: 1.5rem;
  position: relative;
}

.suggestions-page {
  max-width: 1100px;
  margin: 0 auto;
}

.suggestion-panel {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  padding: 2rem 2.25rem;
  margin-bottom: 1.5rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.suggestion-panel-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1rem;
}

.suggestion-form .suggestion-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  resize: vertical;
  min-height: 100px;
}

.suggestion-form-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10px;
  gap: 12px;
}

.suggestion-char-count {
  font-size: 12px;
  color: #64748b;
}

.suggestion-submit-btn {
  padding: 8px 20px;
  background: #0f172a;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
}

.suggestion-submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.suggestion-success-msg {
  margin-top: 10px;
  font-size: 14px;
  color: #059669;
}

.suggestion-error-msg {
  margin-top: 10px;
  font-size: 14px;
  color: #dc2626;
}

.suggestion-list-loading,
.suggestion-list-empty {
  padding: 2rem;
  text-align: center;
  color: #64748b;
  font-size: 14px;
}

.suggestion-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.suggestion-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1rem 1.25rem;
}

.suggestion-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.suggestion-user-avatar-wrap {
  flex-shrink: 0;
}

.suggestion-user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.suggestion-user-avatar-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.suggestion-avatar-icon {
  font-size: 20px;
  color: #64748b;
}

.suggestion-card-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.suggestion-sender-name {
  font-weight: 600;
  font-size: 14px;
  color: #1e293b;
}

.suggestion-date {
  font-size: 12px;
  color: #64748b;
}

.suggestion-card-content {
  font-size: 14px;
  color: #475569;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
