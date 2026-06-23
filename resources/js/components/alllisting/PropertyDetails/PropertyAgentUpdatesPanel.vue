<template>
  <div class="agent-updates-panel" :class="panelClasses">
    <div class="agent-updates-panel__head">
      <div class="agent-updates-panel__title-row">
        <i class="ri-chat-history-line"></i>
        <span class="agent-updates-panel__title">Agent Updates</span>
        <span v-if="updates.length" class="agent-updates-panel__count">{{ updates.length }}</span>
      </div>
      <p class="agent-updates-panel__hint">Internal · Team lead & manager</p>
    </div>

    <div v-if="canAdd" class="agent-updates-panel__composer">
      <textarea
        :value="newText"
        class="agent-updates-panel__input"
        rows="2"
        maxlength="5000"
        placeholder="Call note, follow-up, status…"
        @input="$emit('update:newText', $event.target.value)"
        @keydown.ctrl.enter.prevent="$emit('submit')"
      ></textarea>
      <div class="agent-updates-panel__composer-foot">
        <span class="agent-updates-panel__meta">{{ (newText || '').length }}/5000</span>
        <button
          type="button"
          class="agent-updates-panel__send"
          :disabled="!newText?.trim() || submitting"
          @click="$emit('submit')"
        >
          <i v-if="submitting" class="ri-loader-4-line agent-updates-panel__spin"></i>
          <i v-else class="ri-send-plane-2-fill"></i>
        </button>
      </div>
    </div>

    <div v-if="loading" class="agent-updates-panel__state">
      <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    </div>

    <div v-else-if="!updates.length" class="agent-updates-panel__state agent-updates-panel__state--empty">
      <i class="ri-chat-3-line"></i>
      <span>No updates yet</span>
    </div>

    <div v-else class="agent-updates-panel__list">
      <article
        v-for="update in updates"
        :key="update.id"
        class="agent-updates-panel__item"
      >
        <img
          :src="update.user?.avatar || defaultAvatar"
          class="agent-updates-panel__avatar"
          alt=""
        >
        <div class="agent-updates-panel__body">
          <div class="agent-updates-panel__item-head">
            <strong>{{ update.user?.name || 'Agent' }}</strong>
            <span>{{ update.created_at_human || update.created_at }}</span>
            <button
              v-if="canDelete?.(update)"
              type="button"
              class="agent-updates-panel__delete"
              aria-label="Delete update"
              @click="$emit('delete', update.id)"
            >
              <i class="ri-delete-bin-line"></i>
            </button>
          </div>
          <p class="agent-updates-panel__text">{{ update.content }}</p>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  updates: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  canAdd: { type: Boolean, default: false },
  newText: { type: String, default: '' },
  submitting: { type: Boolean, default: false },
  canDelete: { type: Function, default: null },
  mobile: { type: Boolean, default: false },
  underAgent: { type: Boolean, default: false },
})

defineEmits(['update:newText', 'submit', 'delete'])

const panelClasses = computed(() => ({
  'agent-updates-panel--mobile': props.mobile,
  'agent-updates-panel--under-agent': props.underAgent,
}))

const defaultAvatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
</script>

<style scoped>
.agent-updates-panel {
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  padding: 12px;
  margin-top: 0;
}

.agent-updates-panel--mobile {
  margin-top: 0;
  margin-bottom: 16px;
}

.agent-updates-panel--under-agent {
  margin-top: 0;
  margin-bottom: 0;
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.agent-updates-panel--under-agent .agent-updates-panel__list {
  max-height: 200px;
}

.agent-updates-panel__head {
  margin-bottom: 10px;
}

.agent-updates-panel__title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #0b0736;
  font-size: 13px;
  font-weight: 600;
}

.agent-updates-panel__title-row i {
  font-size: 15px;
  color: #733e87;
}

.agent-updates-panel__count {
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: #733e87;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.agent-updates-panel__hint {
  margin: 2px 0 0;
  font-size: 10px;
  color: #94a3b8;
  line-height: 1.3;
}

.agent-updates-panel__composer {
  margin-bottom: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  overflow: hidden;
}

.agent-updates-panel__input {
  width: 100%;
  border: none;
  background: transparent;
  padding: 8px 10px 4px;
  font-size: 12px;
  line-height: 1.45;
  color: #334155;
  resize: none;
  outline: none;
}

.agent-updates-panel__input::placeholder {
  color: #94a3b8;
}

.agent-updates-panel__composer-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 8px 8px;
}

.agent-updates-panel__meta {
  font-size: 10px;
  color: #94a3b8;
}

.agent-updates-panel__send {
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 8px;
  background: #733e87;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.agent-updates-panel__send:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.agent-updates-panel__send i {
  font-size: 14px;
}

.agent-updates-panel__spin {
  animation: agent-updates-spin 0.8s linear infinite;
}

@keyframes agent-updates-spin {
  to { transform: rotate(360deg); }
}

.agent-updates-panel__state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 8px;
  color: #94a3b8;
  font-size: 12px;
}

.agent-updates-panel__state--empty {
  flex-direction: column;
  gap: 4px;
}

.agent-updates-panel__state--empty i {
  font-size: 22px;
  opacity: 0.6;
}

.agent-updates-panel__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 260px;
  overflow-y: auto;
  padding-right: 2px;
}

.agent-updates-panel__list::-webkit-scrollbar {
  width: 4px;
}

.agent-updates-panel__list::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.agent-updates-panel__item {
  display: flex;
  gap: 8px;
  padding: 8px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #eef2f7;
}

.agent-updates-panel__avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}

.agent-updates-panel__body {
  flex: 1;
  min-width: 0;
}

.agent-updates-panel__item-head {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 3px;
}

.agent-updates-panel__item-head strong {
  font-size: 11px;
  font-weight: 600;
  color: #0f172a;
}

.agent-updates-panel__item-head span {
  font-size: 10px;
  color: #94a3b8;
}

.agent-updates-panel__delete {
  margin-left: auto;
  width: 22px;
  height: 22px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: #cbd5e1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.agent-updates-panel__delete:hover {
  background: #fee2e2;
  color: #dc2626;
}

.agent-updates-panel__text {
  margin: 0;
  font-size: 12px;
  line-height: 1.45;
  color: #475569;
  white-space: pre-wrap;
  word-break: break-word;
}

.agent-updates-panel--mobile .agent-updates-panel__list {
  max-height: 320px;
}
</style>
