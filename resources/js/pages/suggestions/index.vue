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

      <div v-if="!isAdmin" class="suggestion-panel suggestion-form-panel">
        <h6 class="suggestion-panel-title">New Suggestion</h6>
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

      <div class="suggestion-panel suggestion-list-panel">
        <h6 class="suggestion-panel-title">{{ isAdmin ? 'All Suggestions' : 'My Suggestions' }}</h6>
        <div v-if="listLoading" class="suggestion-list-loading">
          <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
          Loading suggestions...
        </div>
        <div v-else-if="listError" class="suggestion-list-empty">{{ listError }}</div>
        <div v-else-if="suggestions.length === 0" class="suggestion-list-empty">
          {{ isAdmin ? 'No suggestions yet.' : "You haven't submitted any suggestions yet." }}
        </div>
        <div v-else class="suggestion-list">
          <article
            v-for="s in suggestions"
            :key="s.id"
            class="suggestion-card"
          >
            <div class="suggestion-card-main">
              <div class="suggestion-card-top">
                <div class="suggestion-kicker">
                  <span class="suggestion-kicker-mark" aria-hidden="true">
                    <iconify-icon icon="lucide:lightbulb" />
                  </span>
                  <span>{{ isAdmin ? 'Suggestion' : 'My Suggestion' }}</span>
                </div>
                <span
                  class="suggestion-status"
                  :class="hasReplies(s) ? 'is-replied' : 'is-waiting'"
                >
                  {{ hasReplies(s) ? 'Replied' : 'Awaiting response' }}
                </span>
              </div>

              <p class="suggestion-card-content">{{ s.content }}</p>

              <div class="suggestion-meta">
                <template v-if="isAdmin">
                  <div class="suggestion-user">
                    <img
                      v-if="s.user && s.user.avatar && !avatarErrorIds.has(s.id)"
                      :src="s.user.avatar"
                      :alt="s.user.name"
                      class="suggestion-user-avatar"
                      @error="markAvatarError(s.id)"
                    />
                    <div v-else class="suggestion-user-avatar suggestion-user-avatar-placeholder" aria-hidden="true">
                      <iconify-icon icon="lucide:user" />
                    </div>
                    <span class="suggestion-sender-name">{{ s.user ? s.user.name : 'Unknown' }}</span>
                  </div>
                </template>
                <span class="suggestion-date">Submitted {{ formatDate(s.created_at) }}</span>
              </div>
            </div>

            <div class="suggestion-thread">
              <div v-if="hasReplies(s)" class="suggestion-reply-list">
                <div
                  v-for="reply in s.replies"
                  :key="reply.id"
                  class="suggestion-reply-note"
                >
                  <div class="suggestion-reply-brand">
                    <span class="suggestion-reply-mark" aria-hidden="true">
                      <iconify-icon icon="lucide:message-circle" />
                    </span>
                    <span>OIA Properties</span>
                    <span class="suggestion-reply-time">{{ formatDate(reply.created_at) }}</span>
                  </div>
                  <p class="suggestion-reply-text">{{ reply.content }}</p>
                </div>
              </div>
              <p v-else class="suggestion-awaiting">
                <iconify-icon icon="lucide:clock-3" aria-hidden="true" />
                <span>Awaiting response</span>
              </p>

              <div v-if="isAdmin" class="suggestion-composer">
                <button
                  v-if="replyingTo !== s.id"
                  type="button"
                  class="suggestion-reply-btn"
                  @click="openReply(s.id)"
                >
                  Reply
                </button>
                <form v-else class="suggestion-composer-form" @submit.prevent="sendReply(s)">
                  <textarea
                    v-model="replyDrafts[s.id]"
                    class="suggestion-reply-input"
                    placeholder="Write a reply..."
                    rows="3"
                    maxlength="5000"
                    autofocus
                    :disabled="sendingReplyId === s.id"
                  />
                  <p v-if="replyErrorId === s.id && replyError" class="suggestion-error-msg">{{ replyError }}</p>
                  <div class="suggestion-composer-actions">
                    <button
                      type="button"
                      class="suggestion-cancel-btn"
                      :disabled="sendingReplyId === s.id"
                      @click="cancelReply(s.id)"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      class="suggestion-send-btn"
                      :disabled="sendingReplyId === s.id || !(replyDrafts[s.id] || '').trim()"
                    >
                      {{ sendingReplyId === s.id ? 'Sending...' : 'Send Reply' }}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import api from '@/plugins/axios';

const formContent = ref('');
const submitting = ref(false);
const submitSuccess = ref(false);
const submitError = ref('');
const suggestions = ref([]);
const listLoading = ref(false);
const listError = ref('');
const avatarErrorIds = ref(new Set());
const replyingTo = ref(null);
const sendingReplyId = ref(null);
const replyError = ref('');
const replyErrorId = ref(null);
const replyDrafts = reactive({});

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
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function hasReplies(suggestion) {
  return Array.isArray(suggestion?.replies) && suggestion.replies.length > 0;
}

function notify(message, type = 'info') {
  if (typeof window.$showNotification === 'function') {
    window.$showNotification(message, type);
  }
}

async function submitSuggestion() {
  const content = formContent.value?.trim();
  if (!content || submitting.value) return;
  submitting.value = true;
  submitSuccess.value = false;
  submitError.value = '';
  try {
    await api.post('/suggestions', { content });
    submitSuccess.value = true;
    formContent.value = '';
    await fetchSuggestions();
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Failed to send suggestion.';
  } finally {
    submitting.value = false;
  }
}

function markAvatarError(id) {
  avatarErrorIds.value = new Set([...avatarErrorIds.value, id]);
}

function openReply(id) {
  if (sendingReplyId.value != null) return;
  if (replyDrafts[id] == null) replyDrafts[id] = '';
  replyError.value = '';
  replyErrorId.value = null;
  replyingTo.value = id;
}

function cancelReply(id) {
  if (sendingReplyId.value === id) return;
  replyDrafts[id] = '';
  if (replyingTo.value === id) replyingTo.value = null;
  if (replyErrorId.value === id) {
    replyError.value = '';
    replyErrorId.value = null;
  }
}

async function sendReply(suggestion) {
  if (!isAdmin.value || sendingReplyId.value != null) return;
  const content = (replyDrafts[suggestion.id] || '').trim();
  if (!content) return;

  sendingReplyId.value = suggestion.id;
  replyError.value = '';
  replyErrorId.value = null;

  try {
    const response = await api.post(`/suggestions/${suggestion.id}/replies`, { content });
    const reply = response.data?.reply;
    if (reply && !suggestion.replies?.some((item) => item.id === reply.id)) {
      suggestion.replies = [...(suggestion.replies || []), reply];
    }
    replyDrafts[suggestion.id] = '';
    replyingTo.value = null;
    notify(response.status === 201 ? 'Reply sent.' : 'Reply already sent.', response.status === 201 ? 'success' : 'info');
  } catch (e) {
    replyError.value = e.response?.data?.message || 'Failed to send reply.';
    replyErrorId.value = suggestion.id;
    notify(replyError.value, 'error');
  } finally {
    sendingReplyId.value = null;
  }
}

async function fetchSuggestions() {
  listLoading.value = true;
  listError.value = '';
  avatarErrorIds.value = new Set();
  try {
    const { data } = await api.get('/suggestions');
    suggestions.value = data.suggestions || [];
  } catch (e) {
    suggestions.value = [];
    listError.value = e.response?.data?.message || 'Could not load suggestions.';
  } finally {
    listLoading.value = false;
  }
}

onMounted(() => {
  fetchSuggestions();
});
</script>

<style scoped>
.suggestions-page-wrap {
  min-height: 100vh;
  padding: 1.25rem 1.25rem 2rem;
  overflow-x: hidden;
}

.suggestions-page {
  max-width: 760px;
  margin: 0 auto;
  min-width: 0;
}

.suggestion-panel {
  background: #fff;
  border-radius: 16px;
  padding: 1.1rem 1.15rem 1rem;
  margin-bottom: 1rem;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
  min-width: 0;
}

.suggestion-panel-title {
  font-size: 15px;
  font-weight: 650;
  letter-spacing: -0.01em;
  color: #0f172a;
  margin: 0 0 0.85rem;
}

.suggestion-form .suggestion-textarea,
.suggestion-reply-input {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  padding: 11px 12px;
  border: 1px solid #e6e1d6;
  border-radius: 10px;
  font-size: 14px;
  line-height: 1.5;
  color: #0f172a;
  background: #fff;
  resize: vertical;
  min-height: 88px;
  font-family: inherit;
}

.suggestion-form .suggestion-textarea:focus,
.suggestion-reply-input:focus {
  outline: none;
  border-color: #e8a317;
  box-shadow: 0 0 0 3px rgba(232, 163, 23, 0.16);
}

.suggestion-form-footer,
.suggestion-composer-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 8px;
}

.suggestion-composer-actions {
  justify-content: flex-end;
}

.suggestion-char-count {
  font-size: 12px;
  color: #64748b;
}

.suggestion-submit-btn,
.suggestion-send-btn,
.suggestion-cancel-btn,
.suggestion-reply-btn {
  min-height: 36px;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

.suggestion-submit-btn,
.suggestion-send-btn {
  background: #0f172a;
  color: #fff;
  border: none;
}

.suggestion-submit-btn:disabled,
.suggestion-send-btn:disabled,
.suggestion-cancel-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.suggestion-cancel-btn,
.suggestion-reply-btn {
  background: #fff;
  color: #0f172a;
  border: 1px solid #e2e8f0;
}

.suggestion-reply-btn:hover,
.suggestion-cancel-btn:hover:not(:disabled) {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.suggestion-success-msg,
.suggestion-error-msg {
  margin: 8px 0 0;
  font-size: 13px;
}

.suggestion-success-msg {
  color: #059669;
}

.suggestion-error-msg {
  color: #dc2626;
}

.suggestion-list-loading,
.suggestion-list-empty {
  padding: 1.25rem 0.5rem;
  text-align: center;
  color: #64748b;
  font-size: 14px;
}

.suggestion-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 0;
}

.suggestion-card {
  border: 1px solid #ece7dc;
  border-radius: 12px;
  background: #fff;
  overflow: hidden;
  min-width: 0;
}

.suggestion-card-main {
  padding: 12px 14px 11px;
  min-width: 0;
}

.suggestion-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-width: 0;
}

.suggestion-kicker {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #92700c;
}

.suggestion-kicker-mark,
.suggestion-reply-mark {
  width: 22px;
  height: 22px;
  border-radius: 7px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 13px;
}

.suggestion-kicker-mark {
  background: #fff6dc;
  color: #b8860b;
}

.suggestion-status {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  padding: 5px 8px;
  border-radius: 999px;
}

.suggestion-status.is-waiting {
  color: #78716c;
  background: #f5f5f4;
}

.suggestion-status.is-replied {
  color: #92700c;
  background: #fff6dc;
}

.suggestion-card-content,
.suggestion-reply-text {
  margin: 8px 0 0;
  font-size: 14px;
  line-height: 1.5;
  color: #1e293b;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.suggestion-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px 12px;
  margin-top: 10px;
  min-width: 0;
}

.suggestion-user {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.suggestion-user-avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.suggestion-user-avatar-placeholder {
  background: #f1f5f9;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
}

.suggestion-sender-name {
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  overflow-wrap: anywhere;
}

.suggestion-date,
.suggestion-reply-time {
  font-size: 12px;
  color: #94a3b8;
}

.suggestion-thread {
  background: #fbf8f3;
  border-top: 1px solid #efeae1;
  padding: 10px 14px 12px;
  min-width: 0;
}

.suggestion-reply-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.suggestion-reply-note {
  background: #fff;
  border: 1px solid #efe6d4;
  border-left: 3px solid #e8a317;
  border-radius: 10px;
  padding: 8px 10px 9px;
  min-width: 0;
}

.suggestion-reply-brand {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  font-size: 12px;
  font-weight: 700;
  color: #0f172a;
}

.suggestion-reply-mark {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #0f172a;
  color: #f5c518;
  font-size: 11px;
}

.suggestion-reply-time {
  margin-left: auto;
  font-weight: 500;
  white-space: nowrap;
}

.suggestion-reply-text {
  margin-top: 6px;
  color: #334155;
}

.suggestion-awaiting {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 12px;
  color: #a8a29e;
}

.suggestion-composer {
  margin-top: 10px;
}

.suggestion-composer-form {
  min-width: 0;
}

.suggestion-reply-input {
  min-height: 72px;
  background: #fff;
}

@media (max-width: 640px) {
  .suggestions-page-wrap {
    padding: 0.75rem 0.75rem 1.5rem;
  }

  .suggestion-panel {
    padding: 0.9rem 0.85rem 0.85rem;
    border-radius: 14px;
  }

  .suggestion-card-main,
  .suggestion-thread {
    padding-left: 12px;
    padding-right: 12px;
  }

  .suggestion-card-top {
    align-items: flex-start;
  }

  .suggestion-form-footer,
  .suggestion-composer-actions {
    flex-wrap: wrap;
  }

  .suggestion-submit-btn,
  .suggestion-send-btn,
  .suggestion-cancel-btn,
  .suggestion-reply-btn {
    min-height: 40px;
  }

  .suggestion-composer-actions .suggestion-cancel-btn,
  .suggestion-composer-actions .suggestion-send-btn {
    flex: 1 1 0;
  }

  .suggestion-reply-time {
    white-space: normal;
  }
}
</style>
