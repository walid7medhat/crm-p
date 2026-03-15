<template>
  <div class="message-bubble-wrapper" :class="{ 'mine': isMine }">
    <div class="message-bubble">
      <div v-if="!isMine && showAvatar" class="bubble-avatar">
        <img v-if="sender?.avatar" :src="sender.avatar" :alt="sender?.name" class="avatar-img" />
        <div v-else class="avatar-placeholder">{{ (sender?.name || '?')[0] }}</div>
      </div>
      <div class="bubble-content">
        <div v-if="!isMine && sender?.name" class="bubble-sender-name">{{ sender.name }}</div>
        <div class="bubble-text">{{ message.message }}</div>
        <div class="bubble-meta">
          <span class="bubble-time">{{ formatTime(message.created_at) }}</span>
          <span v-if="isMine && message.read_at" class="bubble-read">✓✓</span>
          <span v-else-if="isMine" class="bubble-sent">✓</span>
        </div>
      </div>
      <div v-if="isMine && showAvatar" class="bubble-avatar">
        <img v-if="currentUserAvatar" :src="currentUserAvatar" alt="Me" class="avatar-img" />
        <div v-else class="avatar-placeholder mine">{{ (currentUserName || 'Me')[0] }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  message: { type: Object, required: true },
  sender: { type: Object, default: null },
  isMine: { type: Boolean, default: false },
  showAvatar: { type: Boolean, default: true },
  currentUserAvatar: { type: String, default: '' },
  currentUserName: { type: String, default: '' },
})

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const sameDay = d.toDateString() === now.toDateString()
  if (sameDay) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.message-bubble-wrapper {
  display: flex;
  justify-content: flex-start;
  margin-bottom: 10px;
}
.message-bubble-wrapper.mine {
  justify-content: flex-end;
}
.message-bubble {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  max-width: 88%;
  min-width: 60px;
}
.message-bubble-wrapper.mine .message-bubble {
  flex-direction: row-reverse;
}
.bubble-avatar {
  flex-shrink: 0;
}
.avatar-img, .avatar-placeholder {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
}
.avatar-placeholder {
  background: #e0e0e0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: #555;
}
.avatar-placeholder.mine {
  background: #0d6efd;
  color: #fff;
}
.bubble-content {
  background: #f1f5f9;
  border-radius: 14px 14px 14px 4px;
  padding: 10px 14px;
  padding-bottom: 6px;
  max-width: 100%;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.message-bubble-wrapper.mine .bubble-content {
  background: #0d6efd;
  color: #fff;
  border-radius: 14px 14px 4px 14px;
  box-shadow: 0 1px 2px rgba(13, 110, 253, 0.2);
}
.bubble-sender-name {
  font-size: 11px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
}
.message-bubble-wrapper.mine .bubble-sender-name {
  color: rgba(255, 255, 255, 0.9);
}
.bubble-text {
  font-size: 14px;
  line-height: 1.5;
  word-break: break-word;
  white-space: pre-wrap;
  overflow-wrap: break-word;
  max-width: 100%;
}
.bubble-meta {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  margin-top: 4px;
}
.bubble-time {
  font-size: 10px;
  opacity: 0.8;
}
.bubble-read, .bubble-sent {
  font-size: 12px;
  opacity: 0.9;
}
</style>
