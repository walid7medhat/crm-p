<template>
  <div class="pf-dock" role="region" aria-label="Agent">
    <div class="pf-dock__card">
      <button type="button" class="pf-dock__who" @click="emit('profile')">
        <img
          :src="avatarFailed ? defaultAvatar : (agent.avatar || defaultAvatar)"
          :alt="agentName"
          @error="avatarFailed = true"
        />
        <span class="pf-dock__who-text">
          <span class="pf-dock__line"><em>Name :</em> {{ agentName }}</span>
          <span v-if="agentEmail" class="pf-dock__line"><em>Email :</em> {{ agentEmail }}</span>
        </span>
      </button>
      <button type="button" class="pf-dock__profile" @click="emit('profile')">
        View Agent
      </button>
    </div>

    <div class="pf-dock__actions">
      <button
        v-if="showActions"
        type="button"
        class="pf-dock__btn pf-dock__btn--action"
        @click.stop="emit('actions')"
      >
        Property Action
        <i class="ri-arrow-up-s-line" aria-hidden="true"></i>
      </button>
      <button
        v-if="canChat"
        type="button"
        class="pf-dock__chat"
        aria-label="Chat with agent"
        @click="emit('chat')"
      >
        <i class="ri-chat-3-fill"></i>
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

const agentEmail = computed(() => props.agent?.email || props.agent?.user_email || '')
</script>
