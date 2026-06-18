<template>
  <div class="ps-agent-bar" role="region" aria-label="Contact agent">
    <div class="ps-agent-bar__sheet">
      <div class="ps-agent-bar__profile" @click="emit('profile')">
        <div class="ps-agent-bar__avatar-wrap">
          <img
            :src="agent.avatar || defaultAvatar"
            :alt="agentName"
            class="ps-agent-bar__avatar"
            @error="onAvatarError"
          />
        </div>
        <div class="ps-agent-bar__info">
          <p class="ps-agent-bar__name">{{ agentName }}</p>
          <span class="ps-agent-bar__link">View agent profile <i class="ri-arrow-right-s-line"></i></span>
        </div>
      </div>

      <div class="ps-agent-bar__actions">
        <button
          v-if="canChat"
          type="button"
          class="ps-agent-bar__btn ps-agent-bar__btn--chat"
          @click="emit('chat')"
        >
          <i class="ri-chat-3-line"></i>
          <span>Chat</span>
        </button>
        <button
          v-if="showActions"
          type="button"
          class="ps-agent-bar__btn ps-agent-bar__btn--actions"
          :class="{ 'ps-agent-bar__btn--solo': !canChat }"
          @click="emit('actions')"
        >
          <i class="ri-more-2-line"></i>
          <span>Actions</span>
        </button>
      </div>
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
