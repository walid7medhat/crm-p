<template>
  <div class="ps-agent-bar" role="region" aria-label="Property actions">
    <div class="ps-agent-bar__sheet">
      <button
        type="button"
        class="ps-agent-bar__avatar-btn"
        :aria-label="`View ${agentName}`"
        @click="emit('profile')"
      >
        <img
          :src="agent.avatar || defaultAvatar"
          :alt="agentName"
          class="ps-agent-bar__avatar"
          @error="onAvatarError"
        />
      </button>
      <button
        v-if="showActions"
        type="button"
        class="ps-agent-bar__action"
        @click="emit('actions')"
      >
        Property Action
        <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
      </button>
      <button
        v-if="canChat"
        type="button"
        class="ps-agent-bar__chat"
        aria-label="Chat with agent"
        @click="emit('chat')"
      >
        <i class="ri-chat-3-line"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  agent: { type: Object, required: true },
  canChat: { type: Boolean, default: false },
  showActions: { type: Boolean, default: true },
})

const emit = defineEmits(['chat', 'profile', 'actions'])

const defaultAvatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
const avatarFailed = ref(false)

const agentName = computed(() => {
  const a = props.agent
  return a.name || [a.first_name, a.last_name].filter(Boolean).join(' ') || 'Agent'
})

function onAvatarError(event) {
  if (!avatarFailed.value) {
    avatarFailed.value = true
    event.target.src = defaultAvatar
  }
}
</script>
