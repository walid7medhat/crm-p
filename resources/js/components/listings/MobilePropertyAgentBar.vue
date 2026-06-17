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
        <a
          v-if="agent.email"
          :href="`mailto:${agent.email}`"
          class="ps-agent-bar__btn ps-agent-bar__btn--email"
          @click.stop
        >
          <i class="ri-mail-line"></i>
          <span>Email</span>
        </a>
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
          v-else-if="agent.email"
          type="button"
          class="ps-agent-bar__btn ps-agent-bar__btn--chat"
          @click="emit('profile')"
        >
          <i class="ri-phone-line"></i>
          <span>Contact</span>
        </button>
        <button
          type="button"
          class="ps-agent-bar__btn ps-agent-bar__btn--profile"
          aria-label="View agent profile"
          @click="emit('profile')"
        >
          <i class="ri-user-line"></i>
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
})

const emit = defineEmits(['chat', 'profile'])

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
