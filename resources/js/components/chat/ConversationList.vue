<template>
  <div class="conversation-list">
    <div v-if="loading" class="list-loading">Loading conversations...</div>
    <template v-else>
      <div
        v-for="conv in conversations"
        :key="conv.id"
        class="conversation-item"
        :class="{ active: conv.id === selectedId }"
        @click="$emit('select', conv)"
      >
        <div class="conv-avatar">
          <img v-if="conv.other_user?.avatar" :src="conv.other_user.avatar" :alt="conv.other_user?.name" />
          <span v-else class="conv-avatar-placeholder">{{ (conv.other_user?.name || '?')[0] }}</span>
        </div>
        <div class="conv-body">
          <div class="conv-name">{{ conv.other_user?.name || 'Unknown' }}</div>
          <div class="conv-preview">{{ lastMessagePreview(conv) }}</div>
        </div>
        <div class="conv-meta">
          <span v-if="conv.unread_count > 0" class="unread-badge">{{ conv.unread_count }}</span>
          <span class="conv-time">{{ formatTime(conv.last_message?.created_at || conv.updated_at) }}</span>
        </div>
      </div>
      <div v-if="conversations.length === 0" class="list-empty">No conversations yet.</div>
    </template>
  </div>
</template>

<script setup>
defineProps({
  conversations: { type: Array, default: () => [] },
  selectedId: { type: Number, default: null },
  loading: { type: Boolean, default: false },
})

function lastMessagePreview(conv) {
  const lm = conv.last_message
  if (!lm?.message) return 'No messages yet'
  const prefix = lm.is_from_me ? 'You: ' : (lm.sender_name ? lm.sender_name + ': ' : '')
  return prefix + lm.message
}

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const diff = now - d
  if (diff < 60000) return 'Now'
  if (diff < 3600000) return Math.floor(diff / 60000) + 'm'
  if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  if (diff < 86400000 * 7) return d.toLocaleDateString([], { weekday: 'short' })
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' })
}
</script>

<style scoped>
.conversation-list {
  overflow-y: auto;
  max-height: 360px;
}
.list-loading, .list-empty {
  padding: 24px;
  text-align: center;
  color: #666;
  font-size: 14px;
}
.conversation-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  cursor: pointer;
  border-bottom: 1px solid #f0f0f0;
  transition: background 0.15s;
}
.conversation-item:hover {
  background: #f5f5f5;
}
.conversation-item.active {
  background: #e7f1ff;
}
.conv-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
}
.conv-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.conv-avatar-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0d6efd;
  color: #fff;
  font-weight: 600;
  font-size: 18px;
}
.conv-body {
  flex: 1;
  min-width: 0;
}
.conv-name {
  font-weight: 600;
  font-size: 14px;
  color: #1a1a1a;
  margin-bottom: 2px;
}
.conv-preview {
  font-size: 13px;
  color: #666;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.conv-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.conv-time {
  font-size: 11px;
  color: #999;
}
.unread-badge {
  background: #0d6efd;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 10px;
}
</style>
