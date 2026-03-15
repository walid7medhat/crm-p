<template>
  <button type="button" class="chat-with-agent-btn" @click.stop="openChat">
    <i class="ri-chat-3-line"></i>
    <span>Chat with Agent</span>
    <span v-if="unreadCount > 0" class="chat-unread-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
  </button>
</template>

<script setup>
defineProps({
  unreadCount: { type: Number, default: 0 },
})

const emit = defineEmits(['open'])

function openChat() {
  emit('open')
}
</script>

<style scoped>
.chat-with-agent-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #0d6efd;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  position: relative;
  transition: background 0.2s;
}
.chat-with-agent-btn:hover {
  background: #0b5ed7;
}
.chat-with-agent-btn i {
  font-size: 18px;
}
.chat-unread-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  background: #dc3545;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
